<?php

use App\Http\Controllers\BoltController;
use App\Http\Controllers\DeveloperSettingsController;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Authentication routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// StackBlitz Bolt integration routes
Route::prefix('bolt')->group(function () {
    Route::get('/status', [BoltController::class, 'status'])->name('bolt.status');
    Route::get('/launch', [BoltController::class, 'launch'])->name('bolt.launch');
    Route::get('/embed', [BoltController::class, 'embed'])->name('bolt.embed');
});

// Developer settings routes (requires authentication)
Route::middleware('auth')->prefix('developer')->group(function () {
    Route::get('/settings', [DeveloperSettingsController::class, 'show'])->name('developer.settings.show');
    Route::patch('/settings', [DeveloperSettingsController::class, 'update'])->name('developer.settings.update');
    Route::post('/settings/reset', [DeveloperSettingsController::class, 'reset'])->name('developer.settings.reset');
});
