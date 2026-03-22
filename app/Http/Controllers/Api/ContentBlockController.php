<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentBlockController extends Controller
{
    public function index(): JsonResponse
    {
        $blocks = ContentBlock::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $blocks]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:' . implode(',', array_keys(ContentBlock::getTypes())),
            'content' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $block = ContentBlock::create($validated);

        return response()->json(['data' => $block], 201);
    }
}
