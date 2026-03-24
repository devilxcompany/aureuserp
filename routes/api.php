<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check
Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'service' => 'AureusERP', 'timestamp' => now()->toISOString()]);
});

// Load integration routes
require __DIR__ . '/integration.php';
