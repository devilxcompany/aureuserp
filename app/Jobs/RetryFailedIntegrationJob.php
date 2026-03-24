<?php

namespace App\Jobs;

use App\Models\IntegrationQueue;
use App\Services\Integration\DataSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Retry Failed Integration Job
 *
 * Automatically retries failed integration queue jobs
 * with exponential backoff.
 */
class RetryFailedIntegrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 60;

    public function __construct(
        private readonly ?string $integration = null,
        private readonly int     $maxRetries  = 3
    ) {}

    public function handle(): void
    {
        $query = IntegrationQueue::retryable();

        if ($this->integration) {
            $query->where('integration', $this->integration);
        }

        $failedJobs = $query->get();

        if ($failedJobs->isEmpty()) {
            Log::info('RetryFailedIntegrationJob: No retryable jobs found.');
            return;
        }

        Log::info("RetryFailedIntegrationJob: Retrying {$failedJobs->count()} failed jobs.");

        foreach ($failedJobs as $job) {
            $job->scheduleRetry();
            ProcessIntegrationJob::dispatch($job->id)->onQueue('integrations');
        }
    }
}
