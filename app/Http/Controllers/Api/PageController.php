<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\JsonResponse;

class PageController extends Controller
{
    public function index(): JsonResponse
    {
        $pages = Page::published()
            ->select(['id', 'title', 'slug', 'meta_description', 'published_at'])
            ->orderByDesc('published_at')
            ->paginate(15);

        return response()->json($pages);
    }

    public function show(string $slug): JsonResponse
    {
        $page = Page::published()
            ->where('slug', $slug)
            ->with(['contentBlocks' => function ($q) {
                $q->where('is_active', true);
            }])
            ->firstOrFail();

        return response()->json([
            'data' => $page,
        ]);
    }
}
