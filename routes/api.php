<?php

use App\Http\Controllers\Api\ContentBlockController;
use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\PageController;
use Illuminate\Support\Facades\Route;

Route::prefix('content')->group(function () {
    // Pages
    Route::get('/pages', [PageController::class, 'index']);
    Route::get('/pages/{slug}', [PageController::class, 'show']);

    // Forms
    Route::get('/forms/{form}', [FormController::class, 'show']);
    Route::post('/forms/{form}/submit', [FormController::class, 'submit']);

    // Content Blocks
    Route::get('/blocks', [ContentBlockController::class, 'index']);
    Route::post('/blocks', [ContentBlockController::class, 'store']);

    // Media
    Route::get('/media', [MediaController::class, 'index']);
    Route::post('/media', [MediaController::class, 'store']);
});
