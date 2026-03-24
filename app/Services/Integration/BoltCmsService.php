<?php

namespace App\Services\Integration;

use App\Models\IntegrationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Bolt CMS Integration Service
 *
 * Handles integration with two Bolt CMS websites.
 * Supports content sync, order retrieval, and product updates.
 */
class BoltCmsService
{
    private array $sites;

    public function __construct()
    {
        $this->sites = [
            'site1' => [
                'url'     => config('integrations.bolt_cms.site1_url', ''),
                'api_key' => config('integrations.bolt_cms.site1_api_key', ''),
                'name'    => 'Site 1',
            ],
            'site2' => [
                'url'     => config('integrations.bolt_cms.site2_url', ''),
                'api_key' => config('integrations.bolt_cms.site2_api_key', ''),
                'name'    => 'Site 2',
            ],
        ];
    }

    /** Test connectivity to all Bolt CMS sites */
    public function testConnection(string $site = null): array
    {
        $results = [];
        $sitesToTest = $site ? [$site => $this->sites[$site]] : $this->sites;

        foreach ($sitesToTest as $siteKey => $siteConfig) {
            if (empty($siteConfig['url'])) {
                $results[$siteKey] = ['success' => false, 'error' => 'URL not configured'];
                continue;
            }

            $response = $this->makeRequest($siteKey, 'GET', '/api');
            $results[$siteKey] = $response['success']
                ? ['success' => true, 'message' => "{$siteConfig['name']} connected"]
                : ['success' => false, 'error' => $response['error'] ?? 'Connection failed'];
        }

        return $results;
    }

    /** Get all products from a Bolt CMS site */
    public function getProducts(string $site = 'site1', array $params = []): array
    {
        $query = !empty($params) ? '?' . http_build_query($params) : '';
        return $this->makeRequest($site, 'GET', "/api/products{$query}");
    }

    /** Get all orders from a Bolt CMS site */
    public function getOrders(string $site = 'site1', array $params = []): array
    {
        $query = !empty($params) ? '?' . http_build_query($params) : '';
        return $this->makeRequest($site, 'GET', "/api/orders{$query}");
    }

    /** Get customers from a Bolt CMS site */
    public function getCustomers(string $site = 'site1', array $params = []): array
    {
        $query = !empty($params) ? '?' . http_build_query($params) : '';
        return $this->makeRequest($site, 'GET', "/api/customers{$query}");
    }

    /** Push a product update to a Bolt CMS site */
    public function updateProduct(string $site, string $productId, array $data): array
    {
        $log = IntegrationLog::record('bolt_cms', 'update_product', $data, 'erp', "bolt_cms_{$site}");
        $log->markProcessing();

        $result = $this->makeRequest($site, 'PUT', "/api/products/{$productId}", $data);

        if ($result['success']) {
            $log->markSuccess($result['data'] ?? []);
        } else {
            $log->markFailed($result['error'] ?? 'Product update failed');
        }

        return $result;
    }

    /** Push a product to Bolt CMS */
    public function createProduct(string $site, array $data): array
    {
        $log = IntegrationLog::record('bolt_cms', 'create_product', $data, 'erp', "bolt_cms_{$site}");
        $log->markProcessing();

        $result = $this->makeRequest($site, 'POST', '/api/products', $data);

        if ($result['success']) {
            $log->markSuccess($result['data'] ?? []);
        } else {
            $log->markFailed($result['error'] ?? 'Product creation failed');
        }

        return $result;
    }

    /** Update order status on Bolt CMS site */
    public function updateOrderStatus(string $site, string $orderId, string $status): array
    {
        $log = IntegrationLog::record(
            'bolt_cms',
            'update_order_status',
            ['order_id' => $orderId, 'status' => $status],
            'erp',
            "bolt_cms_{$site}"
        );
        $log->markProcessing();

        $result = $this->makeRequest($site, 'PATCH', "/api/orders/{$orderId}", ['status' => $status]);

        if ($result['success']) {
            $log->markSuccess($result['data'] ?? []);
        } else {
            $log->markFailed($result['error'] ?? 'Order status update failed');
        }

        return $result;
    }

    /**
     * Sync all products from ERP to all Bolt CMS sites.
     */
    public function syncProductsToAllSites(array $products): array
    {
        $results = [];

        foreach (array_keys($this->sites) as $site) {
            $siteResults = [];

            foreach ($products as $product) {
                $siteResults[] = $this->makeRequest($site, 'POST', '/api/products/sync', $product);
            }

            $results[$site] = [
                'total'    => count($siteResults),
                'success'  => count(array_filter($siteResults, fn($r) => $r['success'])),
                'failed'   => count(array_filter($siteResults, fn($r) => !$r['success'])),
            ];
        }

        return $results;
    }

    /**
     * Collect all new orders from all Bolt CMS sites.
     */
    public function collectNewOrders(string $since = null): array
    {
        $allOrders = [];

        foreach (array_keys($this->sites) as $site) {
            $params   = $since ? ['since' => $since] : [];
            $result   = $this->getOrders($site, $params);

            if ($result['success'] && !empty($result['data'])) {
                foreach ($result['data'] as $order) {
                    $order['bolt_cms_site'] = $site;
                    $allOrders[]            = $order;
                }
            }
        }

        return $allOrders;
    }

    /** Verify a Bolt CMS webhook token */
    public function verifyWebhookToken(string $token): bool
    {
        $expected = config('integrations.bolt_cms.webhook_token', '');

        if (empty($expected)) {
            return true; // Skip verification if not configured
        }

        return hash_equals($expected, $token);
    }

    /** Process an incoming webhook from Bolt CMS */
    public function processWebhook(string $site, string $event, array $payload): array
    {
        $log = IntegrationLog::record('bolt_cms', "webhook_{$event}", $payload, "bolt_cms_{$site}", 'erp');
        $log->markProcessing();

        try {
            $result = match ($event) {
                'order.created'     => $this->handleOrderCreated($site, $payload),
                'order.updated'     => $this->handleOrderUpdated($site, $payload),
                'customer.created'  => $this->handleCustomerCreated($site, $payload),
                'product.updated'   => $this->handleProductUpdated($site, $payload),
                default             => ['success' => true, 'message' => "Event '{$event}' acknowledged"],
            };

            $log->markSuccess($result);
            return $result;
        } catch (\Throwable $e) {
            $log->markFailed($e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function handleOrderCreated(string $site, array $payload): array
    {
        return [
            'success' => true,
            'action'  => 'order_created',
            'site'    => $site,
            'order'   => $payload['order'] ?? $payload,
        ];
    }

    private function handleOrderUpdated(string $site, array $payload): array
    {
        return [
            'success' => true,
            'action'  => 'order_updated',
            'site'    => $site,
            'order'   => $payload['order'] ?? $payload,
        ];
    }

    private function handleCustomerCreated(string $site, array $payload): array
    {
        return [
            'success'  => true,
            'action'   => 'customer_created',
            'site'     => $site,
            'customer' => $payload['customer'] ?? $payload,
        ];
    }

    private function handleProductUpdated(string $site, array $payload): array
    {
        return [
            'success'  => true,
            'action'   => 'product_updated',
            'site'     => $site,
            'product'  => $payload['product'] ?? $payload,
        ];
    }

    /** Make an authenticated request to a Bolt CMS site */
    private function makeRequest(string $site, string $method, string $endpoint, array $data = []): array
    {
        $siteConfig = $this->sites[$site] ?? null;

        if (!$siteConfig || empty($siteConfig['url'])) {
            return ['success' => false, 'error' => "Bolt CMS site '{$site}' not configured"];
        }

        try {
            $http = Http::withHeaders([
                'Authorization' => "Bearer {$siteConfig['api_key']}",
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'User-Agent'    => 'AureusERP-Integration/1.0',
            ])->timeout(30);

            $url = rtrim($siteConfig['url'], '/') . $endpoint;

            $response = match (strtoupper($method)) {
                'GET'   => $http->get($url),
                'POST'  => $http->post($url, $data),
                'PUT'   => $http->put($url, $data),
                'PATCH' => $http->patch($url, $data),
                'DELETE'=> $http->delete($url),
                default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
            };

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            return [
                'success' => false,
                'error'   => "Bolt CMS error {$response->status()}",
                'status'  => $response->status(),
            ];
        } catch (\Throwable $e) {
            Log::error("Bolt CMS request failed [{$site} {$method} {$endpoint}]: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
