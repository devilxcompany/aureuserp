<?php

namespace App\Services\Integration;

use App\Models\IntegrationLog;
use App\Models\IntegrationQueue;
use App\Models\IntegrationConfig;
use Illuminate\Support\Facades\Log;

/**
 * Master Integration Service
 *
 * Orchestrates all integrations (GitHub, Pabbly, Supabase, Bolt CMS).
 * Acts as the central controller for all integration operations.
 */
class MasterIntegrationService
{
    public function __construct(
        private readonly GitHubService        $github,
        private readonly PabblyService        $pabbly,
        private readonly SupabaseService      $supabase,
        private readonly BoltCmsService       $boltCms,
        private readonly EventDispatcherService $dispatcher,
        private readonly DataSyncService      $dataSync
    ) {}

    /**
     * Get the health status of all integrations.
     */
    public function getSystemStatus(): array
    {
        $integrations = [
            'github'   => $this->getIntegrationStatus('github'),
            'pabbly'   => $this->getIntegrationStatus('pabbly'),
            'supabase' => $this->getIntegrationStatus('supabase'),
            'bolt_cms' => $this->getIntegrationStatus('bolt_cms'),
        ];

        $allHealthy  = collect($integrations)->every(fn($i) => $i['healthy'] !== false);
        $syncStatus  = $this->dataSync->getSyncStatus();
        $queueStatus = $this->getQueueStatus();

        return [
            'status'          => $allHealthy ? 'healthy' : 'degraded',
            'timestamp'       => now()->toISOString(),
            'integrations'    => $integrations,
            'sync'            => $syncStatus,
            'queue'           => $queueStatus,
            'event_listeners' => $this->dispatcher->getListeners(),
        ];
    }

    /**
     * Test connectivity to all integration services.
     */
    public function testAllConnections(): array
    {
        return [
            'github'   => $this->github->testConnection(),
            'pabbly'   => $this->pabbly->testConnection(),
            'supabase' => $this->supabase->testConnection(),
            'bolt_cms' => $this->boltCms->testConnection(),
        ];
    }

    /**
     * Trigger a full synchronization across all platforms.
     */
    public function triggerFullSync(bool $async = true): array
    {
        if ($async) {
            $job = IntegrationQueue::enqueue('master', 'full_sync', [], 1);
            return [
                'success'   => true,
                'message'   => 'Full sync queued',
                'job_id'    => $job->id,
                'estimated' => 'Starts within 60 seconds',
            ];
        }

        return $this->dataSync->fullSync();
    }

    /**
     * Trigger synchronization for a specific entity type.
     */
    public function triggerEntitySync(string $entityType, array $data, bool $async = false): array
    {
        if ($async) {
            $job = IntegrationQueue::enqueue('master', "sync_{$entityType}", $data, 3);
            return ['success' => true, 'message' => "{$entityType} sync queued", 'job_id' => $job->id];
        }

        return $this->dataSync->syncEntity($entityType, $data);
    }

    /**
     * Dispatch an event through the event bus.
     */
    public function dispatchEvent(string $event, string $source, array $payload): array
    {
        return $this->dispatcher->dispatch($event, $source, $payload);
    }

    /**
     * Pause an integration (stops processing its queue jobs).
     */
    public function pauseIntegration(string $integration): array
    {
        $paused = IntegrationQueue::where('integration', $integration)
            ->where('status', 'pending')
            ->get()
            ->each(fn($job) => $job->pause());

        IntegrationConfig::setValue($integration, 'paused', 'true', 'boolean');

        return [
            'success'     => true,
            'integration' => $integration,
            'jobs_paused' => $paused->count(),
        ];
    }

    /**
     * Resume a paused integration.
     */
    public function resumeIntegration(string $integration): array
    {
        $resumed = IntegrationQueue::where('integration', $integration)
            ->where('status', 'paused')
            ->get()
            ->each(fn($job) => $job->resume());

        IntegrationConfig::setValue($integration, 'paused', 'false', 'boolean');

        return [
            'success'      => true,
            'integration'  => $integration,
            'jobs_resumed' => $resumed->count(),
        ];
    }

    /**
     * Retry all failed jobs for an integration.
     */
    public function retryFailedJobs(string $integration = null): array
    {
        $query = IntegrationQueue::retryable();

        if ($integration) {
            $query->where('integration', $integration);
        }

        $jobs    = $query->get();
        $retried = 0;

        foreach ($jobs as $job) {
            $job->scheduleRetry();
            $retried++;
        }

        return [
            'success'        => true,
            'integration'    => $integration ?? 'all',
            'jobs_scheduled' => $retried,
        ];
    }

    /**
     * Get integration logs with filtering.
     */
    public function getLogs(array $filters = [], int $perPage = 50): array
    {
        $query = IntegrationLog::latest();

        if (!empty($filters['integration'])) {
            $query->where('integration', $filters['integration']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (!empty($filters['since'])) {
            $query->where('created_at', '>=', $filters['since']);
        }

        $logs = $query->paginate($perPage);

        return [
            'success' => true,
            'data'    => $logs->items(),
            'meta'    => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'total'        => $logs->total(),
                'per_page'     => $logs->perPage(),
            ],
        ];
    }

    /**
     * Clear old integration logs.
     */
    public function clearOldLogs(int $daysOld = 30): array
    {
        $deleted = IntegrationLog::where('created_at', '<', now()->subDays($daysOld))->delete();

        return [
            'success' => true,
            'deleted' => $deleted,
            'message' => "Cleared logs older than {$daysOld} days",
        ];
    }

    /**
     * Get the current queue status.
     */
    public function getQueueStatus(): array
    {
        return [
            'pending'    => IntegrationQueue::where('status', 'pending')->count(),
            'processing' => IntegrationQueue::where('status', 'processing')->count(),
            'completed'  => IntegrationQueue::where('status', 'completed')->count(),
            'failed'     => IntegrationQueue::where('status', 'failed')->count(),
            'paused'     => IntegrationQueue::where('status', 'paused')->count(),
            'retryable'  => IntegrationQueue::retryable()->count(),
        ];
    }

    /**
     * Get the status of a specific integration.
     */
    private function getIntegrationStatus(string $integration): array
    {
        $enabled = config("integrations.{$integration}.enabled", false);
        $paused  = IntegrationConfig::getValue($integration, 'paused', false);

        $recentLogs = IntegrationLog::forIntegration($integration)
            ->latest()
            ->take(10)
            ->get();

        $failureCount = $recentLogs->where('status', 'failed')->count();
        $maxFailures  = config('integrations.monitoring.max_failure_threshold', 5);
        $healthy      = $failureCount < $maxFailures;

        return [
            'enabled'       => $enabled,
            'paused'        => (bool) $paused,
            'healthy'       => $healthy,
            'failure_count' => $failureCount,
            'last_event'    => $recentLogs->first()?->created_at,
            'pending_jobs'  => IntegrationQueue::where('integration', $integration)
                ->where('status', 'pending')
                ->count(),
        ];
    }
}
