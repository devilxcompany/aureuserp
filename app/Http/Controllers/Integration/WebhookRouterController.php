<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\IntegrationLog;
use App\Models\WebhookEvent;
use App\Services\Integration\GitHubService;
use App\Services\Integration\PabblyService;
use App\Services\Integration\SupabaseService;
use App\Services\Integration\BoltCmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook Router Controller
 *
 * Central entry point for all incoming webhooks.
 * Routes webhooks from Pabbly, GitHub, and Bolt CMS to the appropriate handlers.
 */
class WebhookRouterController extends Controller
{
    public function __construct(
        private readonly GitHubService   $github,
        private readonly PabblyService   $pabbly,
        private readonly SupabaseService $supabase,
        private readonly BoltCmsService  $boltCms
    ) {}

    /**
     * POST /api/webhooks/github
     * Central GitHub webhook entry point.
     */
    public function github(Request $request): JsonResponse
    {
        return $this->routeToHandler('github', $request, function () use ($request) {
            $event   = $request->header('X-GitHub-Event', 'unknown');
            $rawBody = $request->getContent();
            $sig     = $request->header('X-Hub-Signature-256', '');

            if (!$this->github->verifyWebhookSignature($rawBody, $sig)) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $result = $this->github->processWebhookEvent($event, $request->all());
            return response()->json(['success' => $result['success'], 'event' => $event, 'result' => $result]);
        });
    }

    /**
     * POST /api/webhooks/pabbly
     * Central Pabbly Connect webhook entry point.
     */
    public function pabbly(Request $request): JsonResponse
    {
        return $this->routeToHandler('pabbly', $request, function () use ($request) {
            $result = $this->pabbly->processIncomingWebhook($request->all());
            return response()->json(['success' => $result['success'], 'result' => $result]);
        });
    }

    /**
     * POST /api/webhooks/bolt-cms/{site}
     * Bolt CMS webhook entry point (supports site1, site2).
     */
    public function boltCms(Request $request, string $site = 'site1'): JsonResponse
    {
        // Validate site parameter to prevent enumeration attacks
        if (!in_array($site, ['site1', 'site2'])) {
            return response()->json(['error' => 'Invalid site'], 404);
        }

        // Verify Bolt CMS webhook token
        $token = $request->header('X-Bolt-Token', $request->input('_token', ''));
        if (!$this->boltCms->verifyWebhookToken($token)) {
            return response()->json(['error' => 'Invalid webhook token'], 401);
        }

        return $this->routeToHandler('bolt_cms', $request, function () use ($request, $site) {
            $event   = $request->input('event', $request->header('X-Bolt-Event', 'unknown'));
            $payload = $request->all();

            $result  = $this->boltCms->processWebhook($site, $event, $payload);
            return response()->json(['success' => $result['success'], 'site' => $site, 'event' => $event, 'result' => $result]);
        });
    }

    /**
     * POST /api/webhooks/supabase
     * Supabase realtime/database webhook entry point.
     */
    public function supabase(Request $request): JsonResponse
    {
        return $this->routeToHandler('supabase', $request, function () use ($request) {
            $event   = $request->input('type', 'INSERT');
            $table   = $request->input('table', 'unknown');
            $record  = $request->input('record', []);
            $old     = $request->input('old_record', []);

            IntegrationLog::record('supabase', "db_{$event}_{$table}", [
                'table'  => $table,
                'event'  => $event,
                'record' => $record,
                'old'    => $old,
            ], 'supabase', 'erp');

            return response()->json([
                'success' => true,
                'event'   => $event,
                'table'   => $table,
                'handled' => true,
            ]);
        });
    }

    /**
     * GET /api/webhooks/events
     * List recent webhook events with filtering.
     */
    public function listEvents(Request $request): JsonResponse
    {
        $query = WebhookEvent::latest();

        if ($source = $request->input('source')) {
            $query->fromSource($source);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $events = $query->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'data'    => $events->items(),
            'meta'    => [
                'current_page' => $events->currentPage(),
                'last_page'    => $events->lastPage(),
                'total'        => $events->total(),
            ],
        ]);
    }

    /**
     * POST /api/webhooks/events/{id}/retry
     * Retry a failed webhook event.
     */
    public function retryEvent(int $id): JsonResponse
    {
        $event = WebhookEvent::findOrFail($id);

        if (!in_array($event->status, ['failed', 'skipped'])) {
            return response()->json(['error' => 'Event cannot be retried in its current status'], 422);
        }

        $event->update(['status' => 'received', 'retry_count' => $event->retry_count + 1]);

        return response()->json([
            'success' => true,
            'message' => 'Webhook event queued for retry',
            'event'   => $event->id,
        ]);
    }

    /**
     * Shared webhook routing helper.
     * Records the webhook event and delegates to a handler callback.
     */
    private function routeToHandler(string $source, Request $request, callable $handler): JsonResponse
    {
        $deliveryId = $request->header('X-Delivery-ID', uniqid("{$source}_"));

        Log::info("Webhook received from {$source}", ['delivery' => $deliveryId]);

        try {
            return $handler();
        } catch (\Throwable $e) {
            Log::error("Webhook routing error [{$source}]: " . $e->getMessage());

            IntegrationLog::record($source, 'webhook_error', [
                'error' => $e->getMessage(),
            ], $source, 'erp');

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}
