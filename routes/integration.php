<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Integration API Routes
|--------------------------------------------------------------------------
|
| All integration endpoints for GitHub, Pabbly, Supabase, and Bolt CMS.
|
| Base prefix: /api
*/

// ---------------------------------------------------------------------------
// Webhook endpoints (public – verified via signature/token per handler)
// ---------------------------------------------------------------------------
Route::prefix('webhooks')->group(function () {

    // GitHub webhooks
    Route::post('/github', [
        \App\Http\Controllers\Integration\WebhookRouterController::class,
        'github',
    ])->name('webhooks.github');

    // Pabbly Connect webhooks
    Route::post('/pabbly', [
        \App\Http\Controllers\Integration\WebhookRouterController::class,
        'pabbly',
    ])->name('webhooks.pabbly');

    // Bolt CMS webhooks (site1 / site2)
    Route::post('/bolt-cms/{site}', [
        \App\Http\Controllers\Integration\WebhookRouterController::class,
        'boltCms',
    ])->where('site', 'site1|site2')->name('webhooks.bolt_cms');

    // Supabase database webhooks
    Route::post('/supabase', [
        \App\Http\Controllers\Integration\WebhookRouterController::class,
        'supabase',
    ])->name('webhooks.supabase');

    // List recent webhook events
    Route::get('/events', [
        \App\Http\Controllers\Integration\WebhookRouterController::class,
        'listEvents',
    ])->name('webhooks.events.list');

    // Retry a specific webhook event
    Route::post('/events/{id}/retry', [
        \App\Http\Controllers\Integration\WebhookRouterController::class,
        'retryEvent',
    ])->name('webhooks.events.retry');
});

// ---------------------------------------------------------------------------
// Pabbly data-export endpoints (public – for Pabbly polling)
// ---------------------------------------------------------------------------
Route::prefix('pabbly')->group(function () {
    Route::post('/webhook', [
        \App\Http\Controllers\Integration\PabblyIntegrationController::class,
        'receiveWebhook',
    ])->name('pabbly.webhook');

    Route::get('/orders/export', [
        \App\Http\Controllers\Integration\PabblyIntegrationController::class,
        'exportOrders',
    ])->name('pabbly.export.orders');

    Route::get('/products/export', [
        \App\Http\Controllers\Integration\PabblyIntegrationController::class,
        'exportProducts',
    ])->name('pabbly.export.products');

    Route::get('/customers/export', [
        \App\Http\Controllers\Integration\PabblyIntegrationController::class,
        'exportCustomers',
    ])->name('pabbly.export.customers');

    Route::get('/invoices/export', [
        \App\Http\Controllers\Integration\PabblyIntegrationController::class,
        'exportInvoices',
    ])->name('pabbly.export.invoices');
});

// ---------------------------------------------------------------------------
// Master integration management (protected – admin only in production)
// ---------------------------------------------------------------------------
Route::prefix('integrations')->group(function () {

    // Status & health
    Route::get('/status',  [\App\Http\Controllers\Integration\MasterIntegrationController::class, 'status'])->name('integrations.status');
    Route::get('/health',  [\App\Http\Controllers\Integration\MasterIntegrationController::class, 'health'])->name('integrations.health');
    Route::get('/dashboard', [\App\Http\Controllers\Integration\MasterIntegrationController::class, 'dashboard'])->name('integrations.dashboard');

    // Test all connections
    Route::post('/test', [\App\Http\Controllers\Integration\MasterIntegrationController::class, 'testConnections'])->name('integrations.test');

    // Manual sync triggers
    Route::post('/sync', [\App\Http\Controllers\Integration\MasterIntegrationController::class, 'triggerSync'])->name('integrations.sync');
    Route::post('/sync/{entityType}', [\App\Http\Controllers\Integration\MasterIntegrationController::class, 'syncEntity'])->name('integrations.sync.entity');

    // Event dispatch
    Route::post('/event', [\App\Http\Controllers\Integration\MasterIntegrationController::class, 'dispatchEvent'])->name('integrations.event');

    // Pause / Resume per integration
    Route::post('/{integration}/pause',  [\App\Http\Controllers\Integration\MasterIntegrationController::class, 'pause'])->name('integrations.pause');
    Route::post('/{integration}/resume', [\App\Http\Controllers\Integration\MasterIntegrationController::class, 'resume'])->name('integrations.resume');

    // Retry failed jobs
    Route::post('/retry', [\App\Http\Controllers\Integration\MasterIntegrationController::class, 'retryFailed'])->name('integrations.retry');

    // Logs
    Route::get('/logs',  [\App\Http\Controllers\Integration\MasterIntegrationController::class, 'getLogs'])->name('integrations.logs');
    Route::delete('/logs', [\App\Http\Controllers\Integration\MasterIntegrationController::class, 'clearLogs'])->name('integrations.logs.clear');

    // Queue
    Route::get('/queue', [\App\Http\Controllers\Integration\MasterIntegrationController::class, 'queueStatus'])->name('integrations.queue');
    Route::post('/queue/{id}/cancel', [\App\Http\Controllers\Integration\MasterIntegrationController::class, 'cancelJob'])->name('integrations.queue.cancel');

    // ---------------------------------------------------------------------------
    // GitHub-specific endpoints
    // ---------------------------------------------------------------------------
    Route::prefix('github')->group(function () {
        Route::get('/status',        [\App\Http\Controllers\Integration\GitHubIntegrationController::class, 'status'])->name('integrations.github.status');
        Route::post('/webhook',      [\App\Http\Controllers\Integration\GitHubIntegrationController::class, 'receiveWebhook'])->name('integrations.github.webhook');
        Route::get('/issues',        [\App\Http\Controllers\Integration\GitHubIntegrationController::class, 'listIssues'])->name('integrations.github.issues');
        Route::post('/issues',       [\App\Http\Controllers\Integration\GitHubIntegrationController::class, 'createIssue'])->name('integrations.github.issues.create');
        Route::post('/sync/order',   [\App\Http\Controllers\Integration\GitHubIntegrationController::class, 'syncOrderToIssue'])->name('integrations.github.sync.order');
        Route::get('/releases',      [\App\Http\Controllers\Integration\GitHubIntegrationController::class, 'listReleases'])->name('integrations.github.releases');
        Route::get('/releases/latest',[\App\Http\Controllers\Integration\GitHubIntegrationController::class, 'latestRelease'])->name('integrations.github.releases.latest');
        Route::get('/commits',       [\App\Http\Controllers\Integration\GitHubIntegrationController::class, 'listCommits'])->name('integrations.github.commits');
        Route::get('/logs',          [\App\Http\Controllers\Integration\GitHubIntegrationController::class, 'getLogs'])->name('integrations.github.logs');
    });

    // ---------------------------------------------------------------------------
    // Pabbly-specific management endpoints
    // ---------------------------------------------------------------------------
    Route::prefix('pabbly')->group(function () {
        Route::post('/trigger', [\App\Http\Controllers\Integration\PabblyIntegrationController::class, 'triggerWorkflow'])->name('integrations.pabbly.trigger');
        Route::get('/logs',     [\App\Http\Controllers\Integration\PabblyIntegrationController::class, 'getLogs'])->name('integrations.pabbly.logs');
    });

    // ---------------------------------------------------------------------------
    // Monitoring endpoints
    // ---------------------------------------------------------------------------
    Route::prefix('monitor')->group(function () {
        Route::get('/health',  [\App\Http\Controllers\Integration\StatusMonitorController::class, 'healthCheck'])->name('integrations.monitor.health');
        Route::get('/ping',    [\App\Http\Controllers\Integration\StatusMonitorController::class, 'ping'])->name('integrations.monitor.ping');
        Route::get('/metrics', [\App\Http\Controllers\Integration\StatusMonitorController::class, 'metrics'])->name('integrations.monitor.metrics');
        Route::get('/errors',  [\App\Http\Controllers\Integration\StatusMonitorController::class, 'recentErrors'])->name('integrations.monitor.errors');
    });
});

// ---------------------------------------------------------------------------
// Unified API endpoints
// ---------------------------------------------------------------------------
Route::prefix('unified')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\API\UnifiedApiController::class, 'dashboard'])->name('unified.dashboard');
    Route::get('/orders',    [\App\Http\Controllers\API\UnifiedApiController::class, 'orders'])->name('unified.orders');
    Route::get('/products',  [\App\Http\Controllers\API\UnifiedApiController::class, 'products'])->name('unified.products');
    Route::get('/customers', [\App\Http\Controllers\API\UnifiedApiController::class, 'customers'])->name('unified.customers');
    Route::get('/github',    [\App\Http\Controllers\API\UnifiedApiController::class, 'github'])->name('unified.github');
    Route::get('/config',    [\App\Http\Controllers\API\UnifiedApiController::class, 'config'])->name('unified.config');
    Route::put('/config/{integration}', [\App\Http\Controllers\API\UnifiedApiController::class, 'updateConfig'])->name('unified.config.update');
});
