<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function index(): JsonResponse
    {
        $media = Media::orderByDesc('created_at')->paginate(20);

        return response()->json($media);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240',
            'name' => 'nullable|string|max:255',
        ]);

        $file = $request->file('file');
        $path = $file->store('content/media', 'public');

        $media = Media::create([
            'name' => $request->input('name', $file->getClientOriginalName()),
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'path' => $path,
            'disk' => 'public',
            'size' => $file->getSize(),
        ]);

        return response()->json([
            'data' => array_merge($media->toArray(), ['url' => $media->getUrl()]),
        ], 201);
    }
}
