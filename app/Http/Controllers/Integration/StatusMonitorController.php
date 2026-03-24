<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\IntegrationLog;
use App\Services\Integration\MasterIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Status Monitor Controller
 *
 * Provides health checks and monitoring endpoints for all integrations.
 */
class StatusMonitorController extends Controller
{
    public function __construct(private readonly MasterIntegrationService $master) {}

    /**
     * GET /api/integrations/monitor/health
     * Comprehensive health check for all integrations.
     */
    public function healthCheck(): JsonResponse
    {
        $status = $this->master->getSystemStatus();

        $response = [
            'status'       => $status['status'],
            'timestamp'    => $status['timestamp'],
            'integrations' => array_map(function ($integration) {
                return [
                    'enabled'      => $integration['enabled'],
                    'healthy'      => $integration['healthy'],
                    'paused'       => $integration['paused'],
                    'failures_24h' => $integration['failure_count'],
                ];
            }, $status['integrations']),
            'queue' => $status['queue'],
            'sync'  => [
                'last_full_sync' => $status['sync']['last_full_sync'] ?? null,
                'pending_jobs'   => $status['sync']['pending_queue'] ?? 0,
            ],
        ];

        $httpStatus = $status['status'] === 'healthy' ? 200 : 503;

        return response()->json($response, $httpStatus);
    }

    /**
     * GET /api/integrations/monitor/ping
     * Simple ping endpoint to verify the integration layer is responding.
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status'    => 'ok',
            'service'   => 'AureusERP Integration Layer',
            'version'   => '1.0.0',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * GET /api/integrations/monitor/metrics
     * Performance metrics for monitoring dashboards (e.g., Grafana, Datadog).
     */
    public function metrics(): JsonResponse
    {
        $since = now()->subHours(24);

        $totalLogs     = IntegrationLog::where('created_at', '>', $since)->count();
        $successLogs   = IntegrationLog::where('created_at', '>', $since)->where('status', 'success')->count();
        $failedLogs    = IntegrationLog::where('created_at', '>', $since)->where('status', 'failed')->count();

        $successRate   = $totalLogs > 0 ? round(($successLogs / $totalLogs) * 100, 2) : 100.0;

        $byIntegration = IntegrationLog::selectRaw('integration, status, count(*) as count')
            ->where('created_at', '>', $since)
            ->groupBy('integration', 'status')
            ->get()
            ->groupBy('integration')
            ->map(fn($rows) => $rows->pluck('count', 'status')->toArray());

        return response()->json([
            'period'          => '24h',
            'since'           => $since->toISOString(),
            'total_events'    => $totalLogs,
            'success_events'  => $successLogs,
            'failed_events'   => $failedLogs,
            'success_rate_pct'=> $successRate,
            'by_integration'  => $byIntegration,
            'queue'           => $this->master->getQueueStatus(),
        ]);
    }

    /**
     * GET /api/integrations/monitor/errors
     * List recent integration errors for debugging.
     */
    public function recentErrors(Request $request): JsonResponse
    {
        $hours = (int) $request->input('hours', 24);
        $limit = min((int) $request->input('limit', 50), 200);

        $errors = IntegrationLog::where('status', 'failed')
            ->where('created_at', '>', now()->subHours($hours))
            ->latest()
            ->take($limit)
            ->get(['id', 'integration', 'event_type', 'error_message', 'retry_count', 'created_at']);

        return response()->json([
            'success' => true,
            'period'  => "{$hours}h",
            'count'   => $errors->count(),
            'errors'  => $errors,
        ]);
    }
}
