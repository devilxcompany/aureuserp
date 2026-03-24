<?php

namespace App\Services\Integration;

use App\Models\IntegrationLog;
use App\Models\IntegrationQueue;
use Illuminate\Support\Facades\Log;

/**
 * Event Dispatcher Service
 *
 * Routes events between all services in real-time.
 * Acts as a central message bus for the integration layer.
 */
class EventDispatcherService
{
    private array $listeners = [];

    public function __construct(
        private readonly GitHubService $github,
        private readonly PabblyService $pabbly,
        private readonly SupabaseService $supabase,
        private readonly BoltCmsService $boltCms
    ) {
        $this->registerDefaultListeners();
    }

    /**
     * Dispatch an integration event to all registered handlers.
     *
     * @param string $event   e.g. "order.created", "product.updated"
     * @param string $source  originating system
     * @param array  $payload event data
     */
    public function dispatch(string $event, string $source, array $payload): array
    {
        $traceId  = uniqid('evt_', true);
        $results  = [];
        $handlers = $this->listeners[$event] ?? [];

        IntegrationLog::record('dispatcher', "dispatch_{$event}", $payload, $source, 'all', $traceId);

        foreach ($handlers as $target => $handler) {
            // Skip dispatching back to the source
            if ($target === $source) {
                continue;
            }

            try {
                $results[$target] = $handler($payload, $source, $traceId);
            } catch (\Throwable $e) {
                Log::error("Event dispatch error [{$event} → {$target}]: " . $e->getMessage());
                $results[$target] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return ['trace_id' => $traceId, 'event' => $event, 'results' => $results];
    }

    /**
     * Queue an event for asynchronous dispatch.
     */
    public function queueDispatch(string $event, string $source, array $payload, int $delaySeconds = 0): array
    {
        $job = IntegrationQueue::enqueue('dispatcher', "dispatch_{$event}", [
            'event'   => $event,
            'source'  => $source,
            'payload' => $payload,
        ], 3, $delaySeconds);

        return ['success' => true, 'job_id' => $job->id, 'event' => $event];
    }

    /**
     * Register a listener for an event on a target system.
     *
     * @param string   $event   event name (e.g. "order.created")
     * @param string   $target  target system name
     * @param callable $handler function(array $payload, string $source, string $traceId): array
     */
    public function listen(string $event, string $target, callable $handler): void
    {
        $this->listeners[$event][$target] = $handler;
    }

    /**
     * Register a listener for multiple events at once.
     */
    public function listenMany(array $events, string $target, callable $handler): void
    {
        foreach ($events as $event) {
            $this->listen($event, $target, $handler);
        }
    }

    /** Get all registered event listeners */
    public function getListeners(): array
    {
        return array_map(fn($listeners) => array_keys($listeners), $this->listeners);
    }

    /**
     * Register default cross-service event listeners.
     */
    private function registerDefaultListeners(): void
    {
        // Order created: sync to GitHub, Pabbly, Supabase
        $this->listen('order.created', 'github', function (array $payload) {
            if (!config('integrations.github.enabled', false)) {
                return ['success' => true, 'skipped' => 'GitHub integration disabled'];
            }
            return $this->github->syncOrderToIssue($payload);
        });

        $this->listen('order.created', 'pabbly', function (array $payload) {
            if (!config('integrations.pabbly.enabled', false)) {
                return ['success' => true, 'skipped' => 'Pabbly integration disabled'];
            }
            return $this->pabbly->notifyOrderCreated($payload);
        });

        $this->listen('order.created', 'supabase', function (array $payload) {
            if (!config('integrations.supabase.enabled', false)) {
                return ['success' => true, 'skipped' => 'Supabase integration disabled'];
            }
            return $this->supabase->syncOrder($payload);
        });

        // Order status changed: notify Pabbly, update GitHub issue
        $this->listen('order.status_changed', 'pabbly', function (array $payload) {
            if (!config('integrations.pabbly.enabled', false)) {
                return ['success' => true, 'skipped' => 'Pabbly integration disabled'];
            }
            return $this->pabbly->notifyOrderStatusChanged($payload, $payload['old_status'] ?? '');
        });

        $this->listen('order.status_changed', 'supabase', function (array $payload) {
            if (!config('integrations.supabase.enabled', false)) {
                return ['success' => true, 'skipped' => 'Supabase integration disabled'];
            }
            return $this->supabase->syncOrder($payload);
        });

        // Product updated: sync to Supabase and Bolt CMS
        $this->listen('product.updated', 'supabase', function (array $payload) {
            if (!config('integrations.supabase.enabled', false)) {
                return ['success' => true, 'skipped' => 'Supabase integration disabled'];
            }
            return $this->supabase->syncProduct($payload);
        });

        $this->listen('product.updated', 'pabbly', function (array $payload) {
            if (!config('integrations.pabbly.enabled', false)) {
                return ['success' => true, 'skipped' => 'Pabbly integration disabled'];
            }
            return $this->pabbly->notifyProductStockChanged($payload);
        });

        $this->listen('product.updated', 'github', function (array $payload) {
            if (!config('integrations.github.enabled', false)) {
                return ['success' => true, 'skipped' => 'GitHub integration disabled'];
            }
            return $this->github->syncProductUpdate($payload);
        });

        // Customer created: sync to Supabase and notify Pabbly
        $this->listen('customer.created', 'supabase', function (array $payload) {
            if (!config('integrations.supabase.enabled', false)) {
                return ['success' => true, 'skipped' => 'Supabase integration disabled'];
            }
            return $this->supabase->syncCustomer($payload);
        });

        $this->listen('customer.created', 'pabbly', function (array $payload) {
            if (!config('integrations.pabbly.enabled', false)) {
                return ['success' => true, 'skipped' => 'Pabbly integration disabled'];
            }
            return $this->pabbly->notifyCustomerCreated($payload);
        });

        // Invoice created: sync to Supabase
        $this->listen('invoice.created', 'supabase', function (array $payload) {
            if (!config('integrations.supabase.enabled', false)) {
                return ['success' => true, 'skipped' => 'Supabase integration disabled'];
            }
            return $this->supabase->syncInvoice($payload);
        });
    }
}
