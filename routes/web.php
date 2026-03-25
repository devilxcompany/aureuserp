<?php

use App\Http\Controllers\BoltController;
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
