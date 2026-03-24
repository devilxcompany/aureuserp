<?php

namespace App\Http\Controllers;

use App\Models\GitHubWebhook;
use App\Models\GitHubSyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives and processes GitHub webhook events (push, pull_request,
 * issues, release, etc.).  Every inbound request is stored in the
 * github_webhooks table for auditing and potential replay.
 */
class GitHubWebhookController extends Controller
{
    /**
     * Receive a webhook event from GitHub.
     *
     * POST /api/github/webhooks/receive
     */
    public function receive(Request $request): JsonResponse
    {
        $deliveryId = $request->header('X-GitHub-Delivery');
        $event      = $request->header('X-GitHub-Event', 'unknown');
        $signature  = $request->header('X-Hub-Signature-256', '');

        // ── Signature verification ────────────────────────────────────────────
        if (! $this->verifySignature($request->getContent(), $signature)) {
            Log::warning('GitHub webhook signature mismatch', ['delivery_id' => $deliveryId]);

            return response()->json(['error' => 'Invalid webhook signature'], 401);
        }

        $payload = $request->json()->all();
        $action  = $payload['action'] ?? null;

        // ── Persist the raw event ────────────────────────────────────────────
        $webhook = GitHubWebhook::create([
            'delivery_id'  => $deliveryId,
            'event'        => $event,
            'action'       => $action,
            'payload'      => $payload,
            'signature'    => $signature,
            'status'       => 'received',
        ]);

        // ── Dispatch to specific handler ─────────────────────────────────────
        try {
            $this->dispatch($event, $action, $payload, $webhook);

            $webhook->update([
                'status'       => 'processed',
                'processed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $webhook->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'retry_count'   => $webhook->retry_count + 1,
            ]);

            Log::error('GitHub webhook processing failed', [
                'delivery_id' => $deliveryId,
                'event'       => $event,
                'error'       => $e->getMessage(),
            ]);
        }

        return response()->json(['message' => 'Webhook received'], 200);
    }

    /**
     * Return a paginated list of stored webhook events.
     *
     * GET /api/github/webhooks
     */
    public function index(Request $request): JsonResponse
    {
        $webhooks = GitHubWebhook::latest()
            ->when($request->query('event'), fn ($q, $e) => $q->where('event', $e))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->paginate(50);

        return response()->json($webhooks);
    }

    /**
     * Return the full payload for a specific webhook delivery.
     *
     * GET /api/github/webhooks/{id}
     */
    public function show(int $id): JsonResponse
    {
        $webhook = GitHubWebhook::findOrFail($id);

        return response()->json($webhook);
    }

    /**
     * Retry processing a previously failed webhook.
     *
     * POST /api/github/webhooks/{id}/retry
     */
    public function retry(int $id): JsonResponse
    {
        $webhook = GitHubWebhook::findOrFail($id);

        if ($webhook->status !== 'failed') {
            return response()->json(['error' => 'Only failed webhooks can be retried'], 422);
        }

        try {
            $this->dispatch($webhook->event, $webhook->action, $webhook->payload, $webhook);

            $webhook->update([
                'status'       => 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ]);

            return response()->json(['message' => 'Webhook reprocessed successfully']);
        } catch (\Throwable $e) {
            $webhook->increment('retry_count');
            $webhook->update(['error_message' => $e->getMessage()]);

            return response()->json(['error' => 'Retry failed: ' . $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Event handlers
    // -------------------------------------------------------------------------

    /**
     * Route an inbound event to the correct handler method.
     */
    private function dispatch(string $event, ?string $action, array $payload, GitHubWebhook $webhook): void
    {
        match ($event) {
            'push'         => $this->handlePush($payload, $webhook),
            'pull_request' => $this->handlePullRequest($action, $payload, $webhook),
            'issues'       => $this->handleIssues($action, $payload, $webhook),
            'release'      => $this->handleRelease($action, $payload, $webhook),
            'ping'         => $this->handlePing($payload, $webhook),
            default        => $this->handleUnknown($event, $payload, $webhook),
        };
    }

    private function handlePush(array $payload, GitHubWebhook $webhook): void
    {
        $repo   = $payload['repository']['full_name'] ?? 'unknown';
        $branch = ltrim($payload['ref'] ?? '', 'refs/heads/');
        $pusher = $payload['pusher']['name'] ?? 'unknown';
        $count  = count($payload['commits'] ?? []);

        Log::info("GitHub push event: {$count} commit(s) to {$repo}@{$branch} by {$pusher}");

        $this->recordSync($webhook, 'github_to_erp', 'push', $repo);
    }

    private function handlePullRequest(?string $action, array $payload, GitHubWebhook $webhook): void
    {
        $pr   = $payload['pull_request'] ?? [];
        $repo = $payload['repository']['full_name'] ?? 'unknown';

        Log::info("GitHub PR #{$pr['number']} {$action} in {$repo}: {$pr['title']}");

        $this->recordSync($webhook, 'github_to_erp', 'pull_request', (string) ($pr['number'] ?? ''));
    }

    private function handleIssues(?string $action, array $payload, GitHubWebhook $webhook): void
    {
        $issue = $payload['issue'] ?? [];
        $repo  = $payload['repository']['full_name'] ?? 'unknown';

        Log::info("GitHub issue #{$issue['number']} {$action} in {$repo}: {$issue['title']}");

        $this->recordSync($webhook, 'github_to_erp', 'issue', (string) ($issue['number'] ?? ''));
    }

    private function handleRelease(?string $action, array $payload, GitHubWebhook $webhook): void
    {
        $release = $payload['release'] ?? [];
        $repo    = $payload['repository']['full_name'] ?? 'unknown';

        Log::info("GitHub release {$release['tag_name']} {$action} in {$repo}");

        $this->recordSync($webhook, 'github_to_erp', 'release', $release['tag_name'] ?? null);
    }

    private function handlePing(array $payload, GitHubWebhook $webhook): void
    {
        Log::info('GitHub ping received', ['zen' => $payload['zen'] ?? '']);
    }

    private function handleUnknown(string $event, array $payload, GitHubWebhook $webhook): void
    {
        Log::info("Unhandled GitHub event: {$event}");
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Verify the HMAC-SHA256 signature sent by GitHub.
     */
    private function verifySignature(string $body, string $signature): bool
    {
        $secret = env('GITHUB_WEBHOOK_SECRET', '');

        if ($secret === '') {
            return true;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $body, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Record a github_to_erp sync log entry for a webhook event.
     */
    private function recordSync(GitHubWebhook $webhook, string $direction, string $resourceType, ?string $resourceId): void
    {
        GitHubSyncLog::create([
            'integration_id' => $webhook->integration_id,
            'direction'      => $direction,
            'resource_type'  => $resourceType,
            'resource_id'    => $resourceId,
            'status'         => 'success',
            'synced_at'      => now(),
        ]);
    }
}
