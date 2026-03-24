<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\IntegrationLog;
use App\Models\WebhookEvent;
use App\Services\Integration\GitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * GitHub Integration Controller
 *
 * Handles:
 * - Incoming GitHub webhooks
 * - GitHub API proxy operations
 * - Order/product → GitHub issue sync
 */
class GitHubIntegrationController extends Controller
{
    public function __construct(private readonly GitHubService $github) {}

    /**
     * GET /api/integrations/github/status
     * Test GitHub connectivity and return repository info.
     */
    public function status(): JsonResponse
    {
        $connection = $this->github->testConnection();
        $repository = [];

        if ($connection['success']) {
            $repoResult = $this->github->getRepository();
            $repository = $repoResult['success'] ? ($repoResult['data'] ?? []) : [];
        }

        return response()->json([
            'success'    => $connection['success'],
            'connection' => $connection,
            'repository' => [
                'name'        => $repository['name'] ?? null,
                'description' => $repository['description'] ?? null,
                'url'         => $repository['html_url'] ?? null,
                'stars'       => $repository['stargazers_count'] ?? null,
                'open_issues' => $repository['open_issues_count'] ?? null,
            ],
        ]);
    }

    /**
     * POST /api/integrations/github/webhook
     * Receive and process GitHub webhook events.
     */
    public function receiveWebhook(Request $request): JsonResponse
    {
        $event     = $request->header('X-GitHub-Event', 'unknown');
        $signature = $request->header('X-Hub-Signature-256', '');
        $deliveryId= $request->header('X-GitHub-Delivery', null);
        $payload   = $request->all();
        $rawBody   = $request->getContent();

        // Verify signature
        if (!$this->github->verifyWebhookSignature($rawBody, $signature)) {
            Log::warning('GitHub webhook signature verification failed', ['delivery' => $deliveryId]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Record webhook event
        $webhookEvent = WebhookEvent::create([
            'source'      => 'github',
            'event_type'  => $event,
            'delivery_id' => $deliveryId,
            'headers'     => $request->headers->all(),
            'payload'     => $payload,
            'status'      => 'received',
        ]);

        // Process the event
        $webhookEvent->markProcessing('GitHubIntegrationController');

        $result = $this->github->processWebhookEvent($event, $payload);

        if ($result['success']) {
            $webhookEvent->markProcessed();
        } else {
            $webhookEvent->markFailed($result['error'] ?? 'Processing failed');
        }

        return response()->json([
            'success'    => $result['success'],
            'event'      => $event,
            'delivery'   => $deliveryId,
            'processed'  => $result,
        ]);
    }

    /**
     * GET /api/integrations/github/issues
     * List GitHub issues.
     */
    public function listIssues(Request $request): JsonResponse
    {
        $params = $request->only(['state', 'labels', 'per_page', 'page']);
        $result = $this->github->listIssues($params);

        return response()->json($result);
    }

    /**
     * POST /api/integrations/github/issues
     * Create a GitHub issue manually.
     */
    public function createIssue(Request $request): JsonResponse
    {
        $request->validate([
            'title'  => 'required|string|max:255',
            'body'   => 'nullable|string',
            'labels' => 'nullable|array',
        ]);

        $result = $this->github->createIssue(
            $request->input('title'),
            $request->input('body', ''),
            $request->input('labels', [])
        );

        return response()->json($result, $result['success'] ? 201 : 422);
    }

    /**
     * POST /api/integrations/github/sync/order
     * Sync an ERP order to a GitHub issue.
     */
    public function syncOrderToIssue(Request $request): JsonResponse
    {
        $request->validate([
            'order_number'  => 'required|string',
            'customer_name' => 'required|string',
            'total_amount'  => 'required|numeric',
            'status'        => 'required|string',
        ]);

        $result = $this->github->syncOrderToIssue($request->all());

        return response()->json($result, $result['success'] ? 201 : 422);
    }

    /**
     * GET /api/integrations/github/releases
     * List GitHub releases.
     */
    public function listReleases(): JsonResponse
    {
        $result = $this->github->listReleases();
        return response()->json($result);
    }

    /**
     * GET /api/integrations/github/releases/latest
     * Get the latest GitHub release.
     */
    public function latestRelease(): JsonResponse
    {
        $result = $this->github->getLatestRelease();
        return response()->json($result);
    }

    /**
     * GET /api/integrations/github/commits
     * List recent commits.
     */
    public function listCommits(Request $request): JsonResponse
    {
        $branch  = $request->input('branch', 'main');
        $perPage = (int) $request->input('per_page', 30);
        $result  = $this->github->listCommits($branch, $perPage);
        return response()->json($result);
    }

    /**
     * GET /api/integrations/github/logs
     * Get GitHub integration logs.
     */
    public function getLogs(Request $request): JsonResponse
    {
        $logs = IntegrationLog::forIntegration('github')
            ->latest()
            ->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'data'    => $logs->items(),
            'meta'    => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }
}
