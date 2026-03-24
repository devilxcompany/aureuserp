<?php

namespace App\Jobs;

use App\Models\IntegrationQueue;
use App\Models\IntegrationLog;
use App\Services\Integration\MasterIntegrationService;
use App\Services\Integration\DataSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Process Integration Queue Job
 *
 * Processes pending jobs from the integration_queue table.
 * Handles sync operations, webhook dispatches, and data transfers.
 */
class ProcessIntegrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(private readonly int $queueJobId) {}

    public function handle(DataSyncService $dataSync, MasterIntegrationService $master): void
    {
        $job = IntegrationQueue::find($this->queueJobId);

        if (!$job || !in_array($job->status, ['pending', 'failed'])) {
            return;
        }

        $job->start();

        try {
            $result = $this->executeJob($job, $dataSync, $master);

            if ($result['success'] ?? false) {
                $job->complete();
            } else {
                $error = $result['error'] ?? 'Job returned unsuccessful result';
                $this->handleFailure($job, $error);
            }
        } catch (\Throwable $e) {
            $this->handleFailure($job, $e->getMessage());
            Log::error("Integration job #{$job->id} failed: " . $e->getMessage(), [
                'integration' => $job->integration,
                'action'      => $job->action,
            ]);
        }
    }

    private function executeJob(IntegrationQueue $job, DataSyncService $dataSync, MasterIntegrationService $master): array
    {
        return match ($job->action) {
            'full_sync'        => $dataSync->fullSync(),
            'sync_orders'      => $dataSync->syncOrders($job->data ?? []),
            'sync_products'    => $dataSync->syncProducts($job->data ?? []),
            'sync_customers'   => $dataSync->syncCustomers($job->data ?? []),
            'sync_order'       => $dataSync->syncEntity('order', $job->data ?? []),
            'sync_product'     => $dataSync->syncEntity('product', $job->data ?? []),
            'sync_customer'    => $dataSync->syncEntity('customer', $job->data ?? []),
            'sync_invoice'     => $dataSync->syncEntity('invoice', $job->data ?? []),
            'dispatch_event'   => $master->dispatchEvent(
                $job->data['event'] ?? 'unknown',
                $job->data['source'] ?? 'system',
                $job->data['payload'] ?? []
            ),
            default            => ['success' => false, 'error' => "Unknown action: {$job->action}"],
        };
    }

    private function handleFailure(IntegrationQueue $job, string $error): void
    {
        $job->fail($error);

        if ($job->attempts < $job->max_attempts) {
            $job->scheduleRetry();
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessIntegrationJob fatally failed for queue job #{$this->queueJobId}: " . $exception->getMessage());

        $job = IntegrationQueue::find($this->queueJobId);
        if ($job) {
            $job->fail($exception->getMessage());
        }
    }
}
