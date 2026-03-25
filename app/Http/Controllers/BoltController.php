<?php

namespace App\Http\Controllers;

use App\Services\BoltService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoltController extends Controller
{
    public function __construct(protected BoltService $boltService)
    {
    }

    /**
     * Return the Bolt/StackBlitz connection status.
     */
    public function status(): JsonResponse
    {
        return response()->json($this->boltService->getStatus());
    }

    /**
     * Return the URL to open a new or existing project in StackBlitz Bolt.
     */
    public function launch(Request $request): JsonResponse
    {
        $params = $request->only(['template', 'file', 'title', 'description']);

        return response()->json([
            'url' => $this->boltService->getBoltUrl($params),
        ]);
    }

    /**
     * Return an embeddable StackBlitz URL for the configured project.
     */
    public function embed(Request $request): JsonResponse
    {
        $projectId = $request->query('project_id', '');
        $params    = $request->only(['view', 'hideNavigation', 'theme']);

        return response()->json([
            'embed_url' => $this->boltService->getEmbedUrl($projectId, $params),
        ]);
    }
}
