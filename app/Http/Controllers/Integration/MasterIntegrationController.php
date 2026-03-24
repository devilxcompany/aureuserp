<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\IntegrationLog;
use App\Models\IntegrationQueue;
use App\Models\WebhookEvent;
use App\Services\Integration\MasterIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Master Integration Controller
 *
 * Central orchestration endpoint for all integrations.
 * Provides system status, manual triggers, and administrative controls.
 */
class MasterIntegrationController extends Controller
{
    public function __construct(private readonly MasterIntegrationService $master) {}

    /**
     * GET /api/integrations/status
     * Get the full system integration status.
     */
    public function status(): JsonResponse
    {
        return response()->json($this->master->getSystemStatus());
    }

    /**
     * GET /api/integrations/health
     * Quick health check endpoint (for uptime monitors).
     */
    public function health(): JsonResponse
    {
        $status = $this->master->getSystemStatus();

        return response()->json([
            'status'    => $status['status'],
            'timestamp' => $status['timestamp'],
            'healthy'   => $status['status'] === 'healthy',
        ], $status['status'] === 'healthy' ? 200 : 503);
    }

    /**
     * POST /api/integrations/test
     * Test connectivity to all integration services.
     */
    public function testConnections(): JsonResponse
    {
        $results = $this->master->testAllConnections();

        $allSuccess = collect($results)->every(fn($r) => $r['success'] ?? false);

        return response()->json([
            'success' => $allSuccess,
            'results' => $results,
            'tested'  => now()->toISOString(),
        ]);
    }

    /**
     * POST /api/integrations/sync
     * Trigger a full synchronization across all platforms.
     */
    public function triggerSync(Request $request): JsonResponse
    {
        $async = $request->boolean('async', true);
        $result = $this->master->triggerFullSync($async);

        return response()->json($result, 202);
    }

    /**
     * POST /api/integrations/sync/{entityType}
     * Sync a specific entity type.
     */
    public function syncEntity(Request $request, string $entityType): JsonResponse
    {
        if (!in_array($entityType, ['order', 'product', 'customer', 'invoice'])) {
            return response()->json(['error' => "Unknown entity type: {$entityType}"], 422);
        }

        $result = $this->master->triggerEntitySync($entityType, $request->all(), $request->boolean('async', false));

        return response()->json($result);
    }

    /**
     * POST /api/integrations/event
     * Manually dispatch an integration event.
     */
    public function dispatchEvent(Request $request): JsonResponse
    {
        $request->validate([
            'event'   => 'required|string',
            'source'  => 'required|string',
            'payload' => 'nullable|array',
        ]);

        $result = $this->master->dispatchEvent(
            $request->input('event'),
            $request->input('source'),
            $request->input('payload', [])
        );

        return response()->json($result);
    }

    /**
     * POST /api/integrations/{integration}/pause
     * Pause all pending jobs for an integration.
     */
    public function pause(string $integration): JsonResponse
    {
        if (!in_array($integration, ['github', 'pabbly', 'supabase', 'bolt_cms'])) {
            return response()->json(['error' => "Unknown integration: {$integration}"], 422);
        }

        return response()->json($this->master->pauseIntegration($integration));
    }

    /**
     * POST /api/integrations/{integration}/resume
     * Resume a paused integration.
     */
    public function resume(string $integration): JsonResponse
    {
        if (!in_array($integration, ['github', 'pabbly', 'supabase', 'bolt_cms'])) {
            return response()->json(['error' => "Unknown integration: {$integration}"], 422);
        }

        return response()->json($this->master->resumeIntegration($integration));
    }

    /**
     * POST /api/integrations/retry
     * Retry all failed integration jobs.
     */
    public function retryFailed(Request $request): JsonResponse
    {
        $integration = $request->input('integration');
        return response()->json($this->master->retryFailedJobs($integration));
    }

    /**
     * GET /api/integrations/logs
     * Get integration logs with filtering and pagination.
     */
    public function getLogs(Request $request): JsonResponse
    {
        $filters = $request->only(['integration', 'status', 'event_type', 'since']);
        $perPage = (int) $request->input('per_page', 50);

        return response()->json($this->master->getLogs($filters, $perPage));
    }

    /**
     * DELETE /api/integrations/logs
     * Clear old integration logs.
     */
    public function clearLogs(Request $request): JsonResponse
    {
        $daysOld = (int) $request->input('days_old', 30);
        return response()->json($this->master->clearOldLogs($daysOld));
    }

    /**
     * GET /api/integrations/queue
     * Get the integration queue status.
     */
    public function queueStatus(): JsonResponse
    {
        $status = $this->master->getQueueStatus();

        $recentJobs = IntegrationQueue::latest()
            ->take(20)
            ->get(['id', 'integration', 'action', 'status', 'attempts', 'created_at', 'completed_at']);

        return response()->json([
            'success' => true,
            'counts'  => $status,
            'recent'  => $recentJobs,
        ]);
    }

    /**
     * POST /api/integrations/queue/{id}/cancel
     * Cancel a pending queue job.
     */
    public function cancelJob(int $id): JsonResponse
    {
        $job = IntegrationQueue::findOrFail($id);

        if ($job->status !== 'pending') {
            return response()->json(['error' => 'Only pending jobs can be cancelled'], 422);
        }

        $job->update(['status' => 'cancelled']);

        return response()->json(['success' => true, 'job_id' => $id, 'status' => 'cancelled']);
    }

    /**
     * GET /api/integrations/dashboard
     * Get aggregated stats for the integration dashboard.
     */
    public function dashboard(): JsonResponse
    {
        $systemStatus = $this->master->getSystemStatus();

        $logStats = IntegrationLog::selectRaw('integration, status, count(*) as count')
            ->where('created_at', '>', now()->subDays(7))
            ->groupBy('integration', 'status')
            ->get()
            ->groupBy('integration')
            ->map(fn($rows) => $rows->pluck('count', 'status')->toArray());

        $webhookStats = WebhookEvent::selectRaw('source, status, count(*) as count')
            ->where('created_at', '>', now()->subDays(7))
            ->groupBy('source', 'status')
            ->get()
            ->groupBy('source')
            ->map(fn($rows) => $rows->pluck('count', 'status')->toArray());

        return response()->json([
            'success'        => true,
            'system_status'  => $systemStatus,
            'log_stats_7d'   => $logStats,
            'webhook_stats_7d'=> $webhookStats,
        ]);
    }
}
