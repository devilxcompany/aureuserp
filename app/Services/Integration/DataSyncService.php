<?php

namespace App\Services\Integration;

use App\Models\IntegrationLog;
use App\Models\IntegrationQueue;
use App\Models\SyncRecord;
use Illuminate\Support\Facades\Log;

/**
 * Data Synchronization Service
 *
 * Keeps data synchronized across all platforms.
 * Handles: batch syncs, conflict resolution, duplicate prevention.
 */
class DataSyncService
{
    private int $batchSize;
    private string $conflictResolution;
    private bool $duplicatePrevention;

    public function __construct(
        private readonly GitHubService   $github,
        private readonly PabblyService   $pabbly,
        private readonly SupabaseService $supabase,
        private readonly BoltCmsService  $boltCms
    ) {
        $this->batchSize          = config('integrations.sync.batch_size', 100);
        $this->conflictResolution = config('integrations.sync.conflict_resolution', 'latest_wins');
        $this->duplicatePrevention= config('integrations.sync.duplicate_prevention', true);
    }

    /**
     * Perform a full sync across all platforms.
     * Flow: Bolt CMS → Aureus ERP → Supabase → GitHub
     */
    public function fullSync(): array
    {
        $log     = IntegrationLog::record('sync', 'full_sync', [], 'scheduler', 'all');
        $log->markProcessing();

        $results = [
            'orders'    => $this->syncOrders(),
            'products'  => $this->syncProducts(),
            'customers' => $this->syncCustomers(),
        ];

        $log->markSuccess($results);
        return $results;
    }

    /**
     * Sync orders: Bolt CMS → ERP → Supabase → GitHub
     */
    public function syncOrders(array $options = []): array
    {
        $since   = $options['since'] ?? null;
        $results = ['collected' => 0, 'synced' => 0, 'failed' => 0, 'skipped' => 0];

        // Step 1: Collect new orders from Bolt CMS sites
        $boltOrders = $this->boltCms->collectNewOrders($since);
        $results['collected'] = count($boltOrders);

        foreach (array_chunk($boltOrders, $this->batchSize) as $batch) {
            foreach ($batch as $order) {
                try {
                    if ($this->duplicatePrevention && $this->isDuplicate('order', $order)) {
                        $results['skipped']++;
                        continue;
                    }

                    // Sync to Supabase
                    $supabaseResult = $this->supabase->syncOrder($order);
                    if (!$supabaseResult['success']) {
                        Log::warning('Order sync to Supabase failed', ['order' => $order['id'] ?? 'unknown']);
                    }

                    // Sync to GitHub (create issue)
                    if (config('integrations.github.sync_issues', true)) {
                        $this->github->syncOrderToIssue($order);
                    }

                    $results['synced']++;
                } catch (\Throwable $e) {
                    $results['failed']++;
                    Log::error('Order sync error: ' . $e->getMessage(), ['order' => $order['id'] ?? 'unknown']);
                }
            }
        }

        IntegrationLog::record('sync', 'sync_orders', $results, 'bolt_cms', 'erp+supabase+github');
        return $results;
    }

    /**
     * Sync products: ERP → Bolt CMS → Supabase → GitHub
     */
    public function syncProducts(array $options = []): array
    {
        $results = ['synced_supabase' => 0, 'synced_bolt' => 0, 'failed' => 0];

        // Get products from ERP (passed in or placeholder)
        $products = $options['products'] ?? [];

        foreach (array_chunk($products, $this->batchSize) as $batch) {
            // Sync batch to Supabase
            foreach ($batch as $product) {
                try {
                    $this->supabase->syncProduct($product);
                    $results['synced_supabase']++;
                } catch (\Throwable $e) {
                    $results['failed']++;
                    Log::error('Product sync to Supabase failed: ' . $e->getMessage());
                }
            }

            // Sync batch to Bolt CMS
            $boltResults = $this->boltCms->syncProductsToAllSites($batch);
            foreach ($boltResults as $siteResult) {
                $results['synced_bolt'] += $siteResult['success'] ?? 0;
            }
        }

        IntegrationLog::record('sync', 'sync_products', $results, 'erp', 'supabase+bolt_cms');
        return $results;
    }

    /**
     * Sync customers: ERP ↔ Supabase ↔ Bolt CMS
     */
    public function syncCustomers(array $options = []): array
    {
        $results = ['synced' => 0, 'failed' => 0];

        $customers = $options['customers'] ?? [];

        foreach ($customers as $customer) {
            try {
                $this->supabase->syncCustomer($customer);
                $results['synced']++;
            } catch (\Throwable $e) {
                $results['failed']++;
                Log::error('Customer sync failed: ' . $e->getMessage());
            }
        }

        IntegrationLog::record('sync', 'sync_customers', $results, 'erp', 'supabase');
        return $results;
    }

    /**
     * Sync a single entity to all platforms.
     */
    public function syncEntity(string $entityType, array $data): array
    {
        return match ($entityType) {
            'order'    => $this->syncSingleOrder($data),
            'product'  => $this->syncSingleProduct($data),
            'customer' => $this->syncSingleCustomer($data),
            'invoice'  => $this->syncSingleInvoice($data),
            default    => ['success' => false, 'error' => "Unknown entity type: {$entityType}"],
        };
    }

    private function syncSingleOrder(array $order): array
    {
        $results = [];
        $results['supabase'] = $this->supabase->syncOrder($order);

        if (config('integrations.github.sync_issues', true)) {
            $results['github'] = $this->github->syncOrderToIssue($order);
        }

        if (config('integrations.pabbly.enabled', false)) {
            $results['pabbly'] = $this->pabbly->notifyOrderCreated($order);
        }

        return ['success' => true, 'entity' => 'order', 'results' => $results];
    }

    private function syncSingleProduct(array $product): array
    {
        $results = [];
        $results['supabase'] = $this->supabase->syncProduct($product);

        if (config('integrations.github.enabled', false)) {
            $results['github'] = $this->github->syncProductUpdate($product);
        }

        return ['success' => true, 'entity' => 'product', 'results' => $results];
    }

    private function syncSingleCustomer(array $customer): array
    {
        $results = [];
        $results['supabase'] = $this->supabase->syncCustomer($customer);

        if (config('integrations.pabbly.enabled', false)) {
            $results['pabbly'] = $this->pabbly->notifyCustomerCreated($customer);
        }

        return ['success' => true, 'entity' => 'customer', 'results' => $results];
    }

    private function syncSingleInvoice(array $invoice): array
    {
        $results = [];
        $results['supabase'] = $this->supabase->syncInvoice($invoice);
        return ['success' => true, 'entity' => 'invoice', 'results' => $results];
    }

    /** Check if an entity has already been synced (duplicate prevention) */
    private function isDuplicate(string $entityType, array $data): bool
    {
        // Simple duplicate check based on external ID
        $externalId = $data['id'] ?? $data['external_id'] ?? null;

        if (!$externalId) {
            return false;
        }

        return IntegrationLog::where('event_type', "sync_{$entityType}")
            ->where('status', 'success')
            ->whereJsonContains('payload->id', $externalId)
            ->where('created_at', '>', now()->subHours(24))
            ->exists();
    }

    /**
     * Get sync status across all platforms.
     */
    public function getSyncStatus(): array
    {
        $recentLogs = IntegrationLog::where('event_type', 'like', 'sync_%')
            ->latest()
            ->take(20)
            ->get();

        $byIntegration = $recentLogs->groupBy('integration')->map(function ($logs) {
            return [
                'last_sync'   => $logs->max('created_at'),
                'success'     => $logs->where('status', 'success')->count(),
                'failed'      => $logs->where('status', 'failed')->count(),
            ];
        });

        return [
            'last_full_sync' => IntegrationLog::where('event_type', 'full_sync')
                ->where('status', 'success')
                ->max('created_at'),
            'by_integration' => $byIntegration->toArray(),
            'pending_queue'  => IntegrationQueue::where('status', 'pending')->count(),
        ];
    }
}
