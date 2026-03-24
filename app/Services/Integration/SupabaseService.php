<?php

namespace App\Services\Integration;

use App\Models\IntegrationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Supabase Integration Service
 *
 * Handles data synchronization with Supabase PostgreSQL.
 * Supports reading from and writing to Supabase REST API and Realtime.
 */
class SupabaseService
{
    private string $url;
    private string $key;
    private string $serviceKey;

    public function __construct()
    {
        $this->url        = config('integrations.supabase.url', '');
        $this->key        = config('integrations.supabase.key', '');
        $this->serviceKey = config('integrations.supabase.service_key', $this->key);
    }

    /** Test Supabase connectivity */
    public function testConnection(): array
    {
        if (empty($this->url) || empty($this->key)) {
            return ['success' => false, 'error' => 'Supabase URL or key not configured'];
        }

        $response = $this->makeRequest('GET', '/rest/v1/');

        return $response['success']
            ? ['success' => true, 'message' => 'Supabase connection successful']
            : ['success' => false, 'error' => $response['error'] ?? 'Connection failed'];
    }

    /** Fetch records from a Supabase table */
    public function fetchTable(string $table, array $filters = [], int $limit = 1000): array
    {
        $query = '?select=*';

        foreach ($filters as $column => $value) {
            $query .= "&{$column}=eq.{$value}";
        }

        if ($limit > 0) {
            $query .= "&limit={$limit}";
        }

        return $this->makeRequest('GET', "/rest/v1/{$table}{$query}");
    }

    /** Insert a record into Supabase */
    public function insert(string $table, array $data): array
    {
        $log = IntegrationLog::record('supabase', 'insert', ['table' => $table, 'data' => $data], 'erp', 'supabase');
        $log->markProcessing();

        $result = $this->makeRequest('POST', "/rest/v1/{$table}", $data, [
            'Prefer' => 'return=representation',
        ]);

        if ($result['success']) {
            $log->markSuccess($result['data'] ?? []);
        } else {
            $log->markFailed($result['error'] ?? 'Insert failed');
        }

        return $result;
    }

    /** Upsert a record in Supabase (insert or update) */
    public function upsert(string $table, array $data, string $onConflict = 'id'): array
    {
        $log = IntegrationLog::record('supabase', 'upsert', ['table' => $table], 'erp', 'supabase');
        $log->markProcessing();

        $result = $this->makeRequest('POST', "/rest/v1/{$table}", $data, [
            'Prefer'        => 'return=representation,resolution=merge-duplicates',
            'on-conflict'   => $onConflict,
        ]);

        if ($result['success']) {
            $log->markSuccess($result['data'] ?? []);
        } else {
            $log->markFailed($result['error'] ?? 'Upsert failed');
        }

        return $result;
    }

    /** Update a record in Supabase */
    public function update(string $table, string $column, mixed $value, array $data): array
    {
        return $this->makeRequest('PATCH', "/rest/v1/{$table}?{$column}=eq.{$value}", $data, [
            'Prefer' => 'return=representation',
        ]);
    }

    /** Delete a record from Supabase */
    public function delete(string $table, string $column, mixed $value): array
    {
        return $this->makeRequest('DELETE', "/rest/v1/{$table}?{$column}=eq.{$value}");
    }

    /**
     * Sync an ERP order to Supabase.
     */
    public function syncOrder(array $order): array
    {
        return $this->upsert('orders', [
            'erp_id'       => $order['id'],
            'order_number' => $order['order_number'] ?? null,
            'customer_id'  => $order['customer_id'] ?? null,
            'status'       => $order['status'] ?? 'pending',
            'total_amount' => $order['total_amount'] ?? 0,
            'created_at'   => $order['created_at'] ?? now()->toISOString(),
            'updated_at'   => now()->toISOString(),
            'raw_data'     => json_encode($order),
        ], 'erp_id');
    }

    /**
     * Sync an ERP product to Supabase.
     */
    public function syncProduct(array $product): array
    {
        return $this->upsert('products', [
            'erp_id'   => $product['id'],
            'sku'      => $product['sku'] ?? null,
            'name'     => $product['name'] ?? null,
            'price'    => $product['price'] ?? 0,
            'quantity' => $product['quantity'] ?? 0,
            'status'   => $product['status'] ?? 'active',
            'updated_at' => now()->toISOString(),
            'raw_data' => json_encode($product),
        ], 'erp_id');
    }

    /**
     * Sync an ERP customer to Supabase.
     */
    public function syncCustomer(array $customer): array
    {
        return $this->upsert('customers', [
            'erp_id'     => $customer['id'],
            'first_name' => $customer['first_name'] ?? null,
            'last_name'  => $customer['last_name'] ?? null,
            'email'      => $customer['email'] ?? null,
            'phone'      => $customer['phone'] ?? null,
            'status'     => $customer['status'] ?? 'active',
            'updated_at' => now()->toISOString(),
            'raw_data'   => json_encode($customer),
        ], 'erp_id');
    }

    /**
     * Sync an ERP invoice to Supabase.
     */
    public function syncInvoice(array $invoice): array
    {
        return $this->upsert('invoices', [
            'erp_id'         => $invoice['id'],
            'invoice_number' => $invoice['invoice_number'] ?? null,
            'order_id'       => $invoice['order_id'] ?? null,
            'amount'         => $invoice['amount'] ?? 0,
            'status'         => $invoice['status'] ?? 'pending',
            'due_date'       => $invoice['due_date'] ?? null,
            'updated_at'     => now()->toISOString(),
            'raw_data'       => json_encode($invoice),
        ], 'erp_id');
    }

    /**
     * Fetch all orders from Supabase for import into ERP.
     */
    public function fetchOrders(array $filters = []): array
    {
        return $this->fetchTable('orders', $filters);
    }

    /**
     * Fetch all products from Supabase.
     */
    public function fetchProducts(array $filters = []): array
    {
        return $this->fetchTable('products', $filters);
    }

    /**
     * Fetch all customers from Supabase.
     */
    public function fetchCustomers(array $filters = []): array
    {
        return $this->fetchTable('customers', $filters);
    }

    /** Make an authenticated request to the Supabase REST API */
    private function makeRequest(
        string $method,
        string $endpoint,
        array $data = [],
        array $extraHeaders = []
    ): array {
        if (empty($this->url)) {
            return ['success' => false, 'error' => 'Supabase URL not configured'];
        }

        try {
            $http = Http::withHeaders(array_merge([
                'apikey'        => $this->serviceKey,
                'Authorization' => "Bearer {$this->serviceKey}",
                'Content-Type'  => 'application/json',
            ], $extraHeaders))->timeout(30);

            $url      = rtrim($this->url, '/') . $endpoint;
            $response = match (strtoupper($method)) {
                'GET'    => $http->get($url),
                'POST'   => $http->post($url, $data),
                'PATCH'  => $http->patch($url, $data),
                'PUT'    => $http->put($url, $data),
                'DELETE' => $http->delete($url),
                default  => throw new \InvalidArgumentException("Unsupported method: {$method}"),
            };

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            return [
                'success' => false,
                'error'   => $response->json()['message'] ?? "Supabase error {$response->status()}",
                'status'  => $response->status(),
            ];
        } catch (\Throwable $e) {
            Log::error("Supabase request failed [{$method} {$endpoint}]: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
