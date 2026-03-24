<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\IntegrationLog;
use App\Models\WebhookEvent;
use App\Services\Integration\PabblyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Pabbly Integration Controller
 *
 * Handles:
 * - Incoming Pabbly webhooks (actions from Pabbly → ERP)
 * - Data export endpoints for Pabbly to poll
 * - Outbound notifications to Pabbly workflows
 */
class PabblyIntegrationController extends Controller
{
    public function __construct(private readonly PabblyService $pabbly) {}

    /**
     * POST /api/integrations/pabbly/webhook
     * Receive and process incoming Pabbly webhook actions.
     */
    public function receiveWebhook(Request $request): JsonResponse
    {
        $payload    = $request->all();
        $deliveryId = $request->header('X-Pabbly-Delivery', uniqid('pabbly_'));

        // Check for duplicate delivery
        if (WebhookEvent::where('delivery_id', $deliveryId)->exists()) {
            return response()->json([
                'success' => true,
                'message' => 'Duplicate webhook ignored',
            ]);
        }

        // Record webhook
        $webhookEvent = WebhookEvent::create([
            'source'      => 'pabbly',
            'event_type'  => $payload['action'] ?? 'unknown',
            'delivery_id' => $deliveryId,
            'headers'     => $request->headers->all(),
            'payload'     => $payload,
            'status'      => 'received',
        ]);

        $webhookEvent->markProcessing('PabblyIntegrationController');

        // Process the action
        $result = $this->pabbly->processIncomingWebhook($payload);

        if ($result['success']) {
            $webhookEvent->markProcessed();
        } else {
            $webhookEvent->markFailed($result['error'] ?? 'Processing failed');
        }

        return response()->json([
            'success'   => $result['success'],
            'action'    => $payload['action'] ?? 'unknown',
            'message'   => $result['message'] ?? ($result['error'] ?? 'Processed'),
            'data'      => $result['data'] ?? null,
        ], $result['success'] ? 200 : 422);
    }

    /**
     * GET /api/integrations/pabbly/orders/export
     * Export orders in Pabbly-compatible format.
     */
    public function exportOrders(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'since', 'limit']);
        $result  = $this->pabbly->exportOrders($filters);

        // In a real implementation this would query the orders model
        return response()->json([
            'success'   => true,
            'source'    => 'aureus_erp',
            'exported'  => now()->toISOString(),
            'filters'   => $filters,
            'data'      => [], // populated by Order model query in full installation
        ]);
    }

    /**
     * GET /api/integrations/pabbly/products/export
     * Export products in Pabbly-compatible format.
     */
    public function exportProducts(Request $request): JsonResponse
    {
        return response()->json([
            'success'  => true,
            'source'   => 'aureus_erp',
            'exported' => now()->toISOString(),
            'data'     => [], // populated by Product model query in full installation
        ]);
    }

    /**
     * GET /api/integrations/pabbly/customers/export
     * Export customers in Pabbly-compatible format.
     */
    public function exportCustomers(Request $request): JsonResponse
    {
        return response()->json([
            'success'  => true,
            'source'   => 'aureus_erp',
            'exported' => now()->toISOString(),
            'data'     => [], // populated by Customer model query in full installation
        ]);
    }

    /**
     * GET /api/integrations/pabbly/invoices/export
     * Export invoices in Pabbly-compatible format.
     */
    public function exportInvoices(Request $request): JsonResponse
    {
        return response()->json([
            'success'  => true,
            'source'   => 'aureus_erp',
            'exported' => now()->toISOString(),
            'data'     => [], // populated by Invoice model query in full installation
        ]);
    }

    /**
     * POST /api/integrations/pabbly/trigger
     * Manually trigger a Pabbly workflow.
     */
    public function triggerWorkflow(Request $request): JsonResponse
    {
        $request->validate([
            'event'   => 'required|string',
            'data'    => 'nullable|array',
            'webhook' => 'nullable|url',
        ]);

        $result = $this->pabbly->triggerWebhook(
            array_merge(
                ['event' => $request->input('event'), 'source' => 'manual_trigger'],
                $request->input('data', [])
            ),
            $request->input('webhook')
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * GET /api/integrations/pabbly/logs
     * Get Pabbly integration logs.
     */
    public function getLogs(Request $request): JsonResponse
    {
        $logs = IntegrationLog::forIntegration('pabbly')
            ->latest()
            ->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'data'    => $logs->items(),
            'meta'    => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }
}
