<?php

use App\Http\Controllers\BoltController;
use App\Http\Controllers\DeveloperSettingsController;
use App\Http\Controllers\LoginController;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\HandleDeveloperCors;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| All routes are wrapped with the developer middleware stack:
|   1. HandleDeveloperCors  — adds CORS headers when DEV_ENABLE_CORS=true
|   2. CheckMaintenanceMode — returns 503 when DEV_MAINTENANCE_MODE=true
|
*/

Route::middleware([HandleDeveloperCors::class, CheckMaintenanceMode::class])->group(function () {

    // Authentication routes
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // StackBlitz Bolt AI integration
    Route::prefix('bolt')->group(function () {
        Route::get('/status',  [BoltController::class, 'status'])->name('bolt.status');
        Route::get('/launch',  [BoltController::class, 'launch'])->name('bolt.launch');
        Route::get('/embed',   [BoltController::class, 'embed'])->name('bolt.embed');
        Route::get('/prompt',  [BoltController::class, 'prompt'])->name('bolt.prompt');
        Route::get('/import',  [BoltController::class, 'import'])->name('bolt.import');
        // Webhook endpoint: excluded from CSRF verification by design (raw POST from Bolt.new)
        Route::post('/webhook', [BoltController::class, 'webhook'])->name('bolt.webhook')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    });

    // Developer settings routes (requires authentication)
    Route::middleware('auth')->prefix('developer')->group(function () {
        Route::get('/settings', [DeveloperSettingsController::class, 'show'])->name('developer.settings.show');
        Route::patch('/settings', [DeveloperSettingsController::class, 'update'])->name('developer.settings.update');
        Route::post('/settings/reset', [DeveloperSettingsController::class, 'reset'])->name('developer.settings.reset');
    });

});
