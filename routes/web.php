<?php

use App\Models\Page;
use Illuminate\Support\Facades\Route;

Route::redirect('/login', '/admin/login')
    ->name('login');

// Public page rendering
Route::get('/{slug}', function (string $slug) {
    $page = Page::published()
        ->where('slug', $slug)
        ->with(['contentBlocks' => fn ($q) => $q->where('is_active', true)])
        ->firstOrFail();

    return view('cms.page', compact('page'));
})->where('slug', '^(?!admin|api)[a-z0-9-]+$');
