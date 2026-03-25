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
     * Return a bolt.new AI prompt URL.
     *
     * GET /bolt/prompt?prompt=Build+a+Laravel+dashboard&...
     *
     * Required query parameter:
     *   prompt  – natural-language description of what the AI should build
     *
     * Optional query parameters are forwarded to bolt.new as-is.
     */
    public function prompt(Request $request): JsonResponse
    {
        $prompt = trim((string) $request->query('prompt', ''));

        if ($prompt === '') {
            return response()->json([
                'message' => 'The "prompt" query parameter is required.',
            ], 422);
        }

        $extra = $request->except('prompt');

        return response()->json([
            'prompt_url' => $this->boltService->getAiPromptUrl($prompt, $extra),
        ]);
    }

    /**
     * Return a bolt.new import URL for a GitHub repository.
     *
     * GET /bolt/import?repo=https://github.com/org/repo&prompt=optional+followup
     *
     * Required query parameter:
     *   repo    – full HTTPS GitHub URL of the repository to import
     *
     * Optional:
     *   prompt  – AI follow-up prompt shown after import
     */
    public function import(Request $request): JsonResponse
    {
        $repoUrl = trim((string) $request->query('repo', ''));
        $prompt  = trim((string) $request->query('prompt', ''));

        if ($repoUrl === '') {
            return response()->json([
                'message' => 'The "repo" query parameter is required (full GitHub HTTPS URL).',
            ], 422);
        }

        if (!str_starts_with($repoUrl, 'https://github.com/')) {
            return response()->json([
                'message' => 'Only public GitHub repositories are supported (https://github.com/...).',
            ], 422);
        }

        return response()->json([
            'import_url' => $this->boltService->getImportUrl($repoUrl, $prompt),
        ]);
    }

    /**
     * Receive inbound webhook events from Bolt.new.
     *
     * POST /bolt/webhook
     *
     * Bolt sends an X-Bolt-Signature header (sha256=<hmac>) that is verified
     * against STACKBLITZ_WEBHOOK_SECRET.  Unknown event types are acknowledged
     * but not acted upon so that future Bolt events are handled gracefully.
     */
    public function webhook(Request $request): JsonResponse
    {
        $rawBody   = $request->getContent();
        $signature = $request->header('X-Bolt-Signature', '');

        if (!$this->boltService->verifyWebhookSignature($rawBody, $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payload   = $request->json()->all();
        $eventType = $payload['event'] ?? 'unknown';

        // Log the incoming event for observability.  In a full application this
        // would dispatch a queued job or fire a domain event.
        \Illuminate\Support\Facades\Log::info('Bolt webhook received', [
            'event'   => $eventType,
            'payload' => $payload,
        ]);

        return response()->json([
            'received' => true,
            'event'    => $eventType,
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
