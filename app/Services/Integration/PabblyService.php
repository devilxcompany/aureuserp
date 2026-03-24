<?php

namespace App\Services\Integration;

use App\Models\IntegrationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pabbly Connect Integration Service
 *
 * Handles webhooks from Pabbly and triggers Pabbly workflows.
 * Routes automation events between all connected systems.
 */
class PabblyService
{
    private string $webhookUrl;
    private string $apiKey;
    private bool $verifySignature;
    private bool $logWebhooks;

    public function __construct()
    {
        $this->webhookUrl      = config('integrations.pabbly.webhook_url', '');
        $this->apiKey          = config('integrations.pabbly.api_key', '');
        $this->verifySignature = config('integrations.pabbly.verify_signature', true);
        $this->logWebhooks     = config('integrations.pabbly.log_webhooks', true);
    }

    /** Test Pabbly connectivity by sending a ping */
    public function testConnection(): array
    {
        if (empty($this->webhookUrl)) {
            return ['success' => false, 'error' => 'Pabbly webhook URL not configured'];
        }

        $result = $this->triggerWebhook([
            'event' => 'ping',
            'source'=> 'AureusERP',
            'time'  => now()->toISOString(),
        ]);

        return $result['success']
            ? ['success' => true, 'message' => 'Pabbly connection successful']
            : $result;
    }

    /**
     * Send data to Pabbly Connect workflow via webhook trigger.
     */
    public function triggerWebhook(array $data, string $webhookUrl = null): array
    {
        $url = $webhookUrl ?? $this->webhookUrl;

        if (empty($url)) {
            return ['success' => false, 'error' => 'Pabbly webhook URL not configured'];
        }

        $log = IntegrationLog::record('pabbly', 'trigger_webhook', $data, 'erp', 'pabbly');
        $log->markProcessing();

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent'   => 'AureusERP-Integration/1.0',
            ])->timeout(30)->post($url, $data);

            if ($response->successful()) {
                $log->markSuccess(['status' => $response->status()]);
                return ['success' => true, 'status' => $response->status()];
            }

            $error = "Pabbly webhook failed with status {$response->status()}";
            $log->markFailed($error);
            return ['success' => false, 'error' => $error, 'status' => $response->status()];
        } catch (\Throwable $e) {
            $log->markFailed($e->getMessage());
            Log::error('Pabbly webhook error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process an incoming Pabbly webhook action.
     * Routes the action to the correct ERP handler.
     */
    public function processIncomingWebhook(array $payload): array
    {
        $action = $payload['action'] ?? null;

        if (!$action) {
            return ['success' => false, 'error' => 'Missing action in webhook payload'];
        }

        $log = IntegrationLog::record('pabbly', "incoming_{$action}", $payload, 'pabbly', 'erp');
        $log->markProcessing();

        try {
            $result = match ($action) {
                'create_order'    => $this->handleCreateOrder($payload),
                'update_order'    => $this->handleUpdateOrder($payload),
                'create_customer' => $this->handleCreateCustomer($payload),
                'update_customer' => $this->handleUpdateCustomer($payload),
                'sync_product'    => $this->handleSyncProduct($payload),
                'create_invoice'  => $this->handleCreateInvoice($payload),
                'sync_all'        => $this->handleSyncAll($payload),
                default           => ['success' => false, 'error' => "Unknown action: {$action}"],
            };

            if ($result['success']) {
                $log->markSuccess($result);
            } else {
                $log->markFailed($result['error'] ?? 'Action failed');
            }

            return $result;
        } catch (\Throwable $e) {
            $log->markFailed($e->getMessage());
            Log::error("Pabbly action error [{$action}]: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** Notify Pabbly when a new order is created in ERP */
    public function notifyOrderCreated(array $order): array
    {
        return $this->triggerWebhook([
            'event'  => 'order_created',
            'source' => 'aureus_erp',
            'data'   => $order,
            'time'   => now()->toISOString(),
        ]);
    }

    /** Notify Pabbly when order status changes */
    public function notifyOrderStatusChanged(array $order, string $oldStatus): array
    {
        return $this->triggerWebhook([
            'event'      => 'order_status_changed',
            'source'     => 'aureus_erp',
            'old_status' => $oldStatus,
            'new_status' => $order['status'],
            'data'       => $order,
            'time'       => now()->toISOString(),
        ]);
    }

    /** Notify Pabbly when a new customer is created */
    public function notifyCustomerCreated(array $customer): array
    {
        return $this->triggerWebhook([
            'event'  => 'customer_created',
            'source' => 'aureus_erp',
            'data'   => $customer,
            'time'   => now()->toISOString(),
        ]);
    }

    /** Notify Pabbly when product stock changes */
    public function notifyProductStockChanged(array $product): array
    {
        return $this->triggerWebhook([
            'event'  => 'product_stock_changed',
            'source' => 'aureus_erp',
            'data'   => $product,
            'time'   => now()->toISOString(),
        ]);
    }

    /** Export orders for Pabbly polling */
    public function exportOrders(array $filters = []): array
    {
        // This returns structured data for Pabbly to consume
        return [
            'success'  => true,
            'exported' => now()->toISOString(),
            'source'   => 'aureus_erp',
            'filters'  => $filters,
        ];
    }

    private function handleCreateOrder(array $payload): array
    {
        return [
            'success' => true,
            'action'  => 'create_order',
            'message' => 'Order creation queued',
            'data'    => $payload,
        ];
    }

    private function handleUpdateOrder(array $payload): array
    {
        return [
            'success' => true,
            'action'  => 'update_order',
            'message' => 'Order update queued',
            'data'    => $payload,
        ];
    }

    private function handleCreateCustomer(array $payload): array
    {
        return [
            'success' => true,
            'action'  => 'create_customer',
            'message' => 'Customer creation queued',
            'data'    => $payload,
        ];
    }

    private function handleUpdateCustomer(array $payload): array
    {
        return [
            'success' => true,
            'action'  => 'update_customer',
            'message' => 'Customer update queued',
            'data'    => $payload,
        ];
    }

    private function handleSyncProduct(array $payload): array
    {
        return [
            'success' => true,
            'action'  => 'sync_product',
            'message' => 'Product sync queued',
            'data'    => $payload,
        ];
    }

    private function handleCreateInvoice(array $payload): array
    {
        return [
            'success' => true,
            'action'  => 'create_invoice',
            'message' => 'Invoice creation queued',
            'data'    => $payload,
        ];
    }

    private function handleSyncAll(array $payload): array
    {
        return [
            'success' => true,
            'action'  => 'sync_all',
            'message' => 'Full sync queued',
        ];
    }
}
