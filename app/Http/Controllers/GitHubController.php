<?php

namespace App\Http\Controllers;

use App\Models\GitHubIntegration;
use App\Models\GitHubSyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Handles GitHub OAuth authentication, repository operations,
 * and data-export / sync functionality.
 */
class GitHubController extends Controller
{
    private const API_BASE = 'https://api.github.com';

    // -------------------------------------------------------------------------
    // OAuth
    // -------------------------------------------------------------------------

    /**
     * Redirect the user to GitHub's OAuth authorisation page.
     *
     * GET /api/github/oauth/redirect
     */
    public function oauthRedirect(): JsonResponse
    {
        $clientId = config('services.github.client_id', env('GITHUB_CLIENT_ID'));
        $redirectUri = config('services.github.redirect', env('GITHUB_REDIRECT_URI'));
        $scope = 'repo,read:user,user:email,admin:repo_hook';
        $state = bin2hex(random_bytes(16));

        session(['github_oauth_state' => $state]);

        $url = 'https://github.com/login/oauth/authorize?' . http_build_query([
            'client_id'    => $clientId,
            'redirect_uri' => $redirectUri,
            'scope'        => $scope,
            'state'        => $state,
        ]);

        return response()->json(['redirect_url' => $url]);
    }

    /**
     * Handle the GitHub OAuth callback and store the access token.
     *
     * GET /api/github/oauth/callback?code=...&state=...
     */
    public function oauthCallback(Request $request): JsonResponse
    {
        $request->validate([
            'code'  => 'required|string',
            'state' => 'required|string',
        ]);

        if ($request->state !== session('github_oauth_state')) {
            return response()->json(['error' => 'Invalid OAuth state'], 400);
        }

        $response = Http::post('https://github.com/login/oauth/access_token', [
            'client_id'     => env('GITHUB_CLIENT_ID'),
            'client_secret' => env('GITHUB_CLIENT_SECRET'),
            'code'          => $request->code,
            'redirect_uri'  => env('GITHUB_REDIRECT_URI'),
        ])->withHeaders(['Accept' => 'application/json'])->throw();

        $tokenData = $response->json();

        if (empty($tokenData['access_token'])) {
            return response()->json(['error' => 'Failed to obtain access token', 'details' => $tokenData], 400);
        }

        $userResponse = Http::withToken($tokenData['access_token'])
            ->get(self::API_BASE . '/user')
            ->throw();

        $githubUser = $userResponse->json();

        $integration = GitHubIntegration::updateOrCreate(
            ['github_user_id' => (string) $githubUser['id']],
            [
                'user_id'          => $request->user()?->id,
                'github_username'  => $githubUser['login'],
                'github_email'     => $githubUser['email'] ?? null,
                'access_token'     => $tokenData['access_token'],
                'token_type'       => $tokenData['token_type'] ?? 'bearer',
                'scope'            => $tokenData['scope'] ?? null,
                'avatar_url'       => $githubUser['avatar_url'] ?? null,
                'is_active'        => true,
            ]
        );

        return response()->json([
            'message'     => 'GitHub account connected successfully',
            'integration' => $integration->makeVisible('access_token'),
            'github_user' => [
                'id'         => $githubUser['id'],
                'login'      => $githubUser['login'],
                'name'       => $githubUser['name'] ?? null,
                'email'      => $githubUser['email'] ?? null,
                'avatar_url' => $githubUser['avatar_url'] ?? null,
            ],
        ]);
    }

    /**
     * Return the current GitHub connection status.
     *
     * GET /api/github/status
     */
    public function status(Request $request): JsonResponse
    {
        $integration = GitHubIntegration::where('user_id', $request->user()?->id)
            ->where('is_active', true)
            ->latest()
            ->first();

        if (! $integration) {
            return response()->json(['connected' => false]);
        }

        return response()->json([
            'connected'   => true,
            'integration' => [
                'id'               => $integration->id,
                'github_username'  => $integration->github_username,
                'github_email'     => $integration->github_email,
                'avatar_url'       => $integration->avatar_url,
                'scope'            => $integration->scope,
                'default_repo'     => $integration->default_repo_owner
                    ? "{$integration->default_repo_owner}/{$integration->default_repo_name}"
                    : null,
                'connected_at'     => $integration->created_at,
            ],
        ]);
    }

    /**
     * Disconnect (deactivate) the GitHub integration.
     *
     * DELETE /api/github/disconnect
     */
    public function disconnect(Request $request): JsonResponse
    {
        GitHubIntegration::where('user_id', $request->user()?->id)
            ->update(['is_active' => false]);

        return response()->json(['message' => 'GitHub account disconnected']);
    }

    // -------------------------------------------------------------------------
    // Repository operations
    // -------------------------------------------------------------------------

    /**
     * List repositories accessible to the authenticated GitHub account.
     *
     * GET /api/github/repos
     */
    public function listRepos(Request $request): JsonResponse
    {
        $integration = $this->getActiveIntegration($request);
        if ($integration instanceof JsonResponse) {
            return $integration;
        }

        $page    = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 30)));

        $response = Http::withToken($integration->access_token)
            ->get(self::API_BASE . '/user/repos', [
                'per_page' => $perPage,
                'page'     => $page,
                'sort'     => 'updated',
                'type'     => 'all',
            ])
            ->throw();

        return response()->json([
            'repositories' => $response->json(),
            'page'         => $page,
            'per_page'     => $perPage,
        ]);
    }

    /**
     * Retrieve details for a single repository.
     *
     * GET /api/github/repos/{owner}/{repo}
     */
    public function getRepo(Request $request, string $owner, string $repo): JsonResponse
    {
        $integration = $this->getActiveIntegration($request);
        if ($integration instanceof JsonResponse) {
            return $integration;
        }

        $response = Http::withToken($integration->access_token)
            ->get(self::API_BASE . "/repos/{$owner}/{$repo}")
            ->throw();

        return response()->json($response->json());
    }

    /**
     * Create a new repository.
     *
     * POST /api/github/repos
     */
    public function createRepo(Request $request): JsonResponse
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'private'     => 'nullable|boolean',
            'auto_init'   => 'nullable|boolean',
        ]);

        $integration = $this->getActiveIntegration($request);
        if ($integration instanceof JsonResponse) {
            return $integration;
        }

        $response = Http::withToken($integration->access_token)
            ->post(self::API_BASE . '/user/repos', [
                'name'        => $request->name,
                'description' => $request->description ?? '',
                'private'     => $request->boolean('private', false),
                'auto_init'   => $request->boolean('auto_init', true),
            ])
            ->throw();

        return response()->json($response->json(), 201);
    }

    /**
     * Set the default repository for ERP→GitHub sync operations.
     *
     * POST /api/github/repos/default
     */
    public function setDefaultRepo(Request $request): JsonResponse
    {
        $request->validate([
            'owner' => 'required|string',
            'repo'  => 'required|string',
        ]);

        $integration = $this->getActiveIntegration($request);
        if ($integration instanceof JsonResponse) {
            return $integration;
        }

        $integration->update([
            'default_repo_owner' => $request->owner,
            'default_repo_name'  => $request->repo,
        ]);

        return response()->json(['message' => 'Default repository updated']);
    }

    // -------------------------------------------------------------------------
    // Webhook registration
    // -------------------------------------------------------------------------

    /**
     * Register a webhook on a GitHub repository.
     *
     * POST /api/github/repos/{owner}/{repo}/webhooks
     */
    public function createRepoWebhook(Request $request, string $owner, string $repo): JsonResponse
    {
        $request->validate([
            'events' => 'nullable|array',
            'events.*' => 'string',
        ]);

        $integration = $this->getActiveIntegration($request);
        if ($integration instanceof JsonResponse) {
            return $integration;
        }

        $webhookUrl = env('APP_URL', 'http://localhost:8000') . '/api/github/webhooks/receive';
        $events     = $request->input('events', ['push', 'pull_request', 'issues', 'release']);

        $response = Http::withToken($integration->access_token)
            ->post(self::API_BASE . "/repos/{$owner}/{$repo}/hooks", [
                'name'   => 'web',
                'active' => true,
                'events' => $events,
                'config' => [
                    'url'          => $webhookUrl,
                    'content_type' => 'json',
                    'secret'       => env('GITHUB_WEBHOOK_SECRET', ''),
                    'insecure_ssl' => '0',
                ],
            ])
            ->throw();

        return response()->json($response->json(), 201);
    }

    /**
     * List webhooks registered on a GitHub repository.
     *
     * GET /api/github/repos/{owner}/{repo}/webhooks
     */
    public function listRepoWebhooks(Request $request, string $owner, string $repo): JsonResponse
    {
        $integration = $this->getActiveIntegration($request);
        if ($integration instanceof JsonResponse) {
            return $integration;
        }

        $response = Http::withToken($integration->access_token)
            ->get(self::API_BASE . "/repos/{$owner}/{$repo}/hooks")
            ->throw();

        return response()->json($response->json());
    }

    // -------------------------------------------------------------------------
    // Issue & Pull-Request creation from ERP data
    // -------------------------------------------------------------------------

    /**
     * Create a GitHub issue from an ERP record.
     *
     * POST /api/github/repos/{owner}/{repo}/issues
     */
    public function createIssue(Request $request, string $owner, string $repo): JsonResponse
    {
        $request->validate([
            'title'  => 'required|string|max:255',
            'body'   => 'nullable|string',
            'labels' => 'nullable|array',
            'labels.*' => 'string',
        ]);

        $integration = $this->getActiveIntegration($request);
        if ($integration instanceof JsonResponse) {
            return $integration;
        }

        $response = Http::withToken($integration->access_token)
            ->post(self::API_BASE . "/repos/{$owner}/{$repo}/issues", [
                'title'  => $request->title,
                'body'   => $request->body ?? '',
                'labels' => $request->input('labels', []),
            ])
            ->throw();

        $this->logSync($integration->id, 'erp_to_github', 'issue', null, $response->json('html_url'), 'success', $response->json());

        return response()->json($response->json(), 201);
    }

    /**
     * Create a GitHub pull request.
     *
     * POST /api/github/repos/{owner}/{repo}/pulls
     */
    public function createPullRequest(Request $request, string $owner, string $repo): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'head'  => 'required|string',
            'base'  => 'required|string',
            'body'  => 'nullable|string',
            'draft' => 'nullable|boolean',
        ]);

        $integration = $this->getActiveIntegration($request);
        if ($integration instanceof JsonResponse) {
            return $integration;
        }

        $response = Http::withToken($integration->access_token)
            ->post(self::API_BASE . "/repos/{$owner}/{$repo}/pulls", [
                'title' => $request->title,
                'head'  => $request->head,
                'base'  => $request->base,
                'body'  => $request->body ?? '',
                'draft' => $request->boolean('draft', false),
            ])
            ->throw();

        $this->logSync($integration->id, 'erp_to_github', 'pull_request', null, $response->json('html_url'), 'success', $response->json());

        return response()->json($response->json(), 201);
    }

    // -------------------------------------------------------------------------
    // Data-export endpoints
    // -------------------------------------------------------------------------

    /**
     * Export ERP sync log entries as a JSON array suitable for
     * creating a GitHub Gist or committing to a repository.
     *
     * GET /api/github/export/sync-logs
     */
    public function exportSyncLogs(Request $request): JsonResponse
    {
        $logs = GitHubSyncLog::latest()->limit(200)->get([
            'id', 'direction', 'resource_type', 'resource_id',
            'github_url', 'status', 'synced_at',
        ]);

        return response()->json(['sync_logs' => $logs]);
    }

    /**
     * Export recent webhook events.
     *
     * GET /api/github/export/webhooks
     */
    public function exportWebhooks(Request $request): JsonResponse
    {
        $webhooks = \App\Models\GitHubWebhook::latest()->limit(200)->get([
            'id', 'delivery_id', 'event', 'action', 'status', 'processed_at', 'retry_count',
        ]);

        return response()->json(['webhooks' => $webhooks]);
    }

    /**
     * Push a file to a GitHub repository (create or update).
     *
     * POST /api/github/repos/{owner}/{repo}/files
     */
    public function pushFileToRepo(Request $request, string $owner, string $repo): JsonResponse
    {
        $request->validate([
            'path'    => 'required|string',
            'content' => 'required|string',
            'message' => 'required|string',
            'branch'  => 'nullable|string',
            'sha'     => 'nullable|string',
        ]);

        $integration = $this->getActiveIntegration($request);
        if ($integration instanceof JsonResponse) {
            return $integration;
        }

        $payload = [
            'message' => $request->message,
            'content' => base64_encode($request->content),
            'branch'  => $request->input('branch', 'main'),
        ];

        if ($request->has('sha')) {
            $payload['sha'] = $request->sha;
        }

        $response = Http::withToken($integration->access_token)
            ->put(self::API_BASE . "/repos/{$owner}/{$repo}/contents/{$request->path}", $payload)
            ->throw();

        return response()->json($response->json(), $response->status());
    }

    // -------------------------------------------------------------------------
    // Sync-log list
    // -------------------------------------------------------------------------

    /**
     * Return paginated sync logs.
     *
     * GET /api/github/sync-logs
     */
    public function syncLogs(Request $request): JsonResponse
    {
        $logs = GitHubSyncLog::latest()->paginate(50);

        return response()->json($logs);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Return the active GitHub integration for the authenticated user,
     * or a 401 JSON response if none is found.
     */
    private function getActiveIntegration(Request $request): GitHubIntegration|JsonResponse
    {
        $query = GitHubIntegration::where('is_active', true);

        if ($request->user()) {
            $query->where('user_id', $request->user()->id);
        }

        $integration = $query->latest()->first();

        if (! $integration) {
            return response()->json([
                'error' => 'No active GitHub integration found. Please connect your GitHub account first.',
            ], 401);
        }

        return $integration;
    }

    /**
     * Record a sync operation in github_sync_logs.
     */
    private function logSync(
        int $integrationId,
        string $direction,
        string $resourceType,
        ?string $resourceId,
        ?string $githubUrl,
        string $status,
        array $response = []
    ): void {
        GitHubSyncLog::create([
            'integration_id' => $integrationId,
            'direction'      => $direction,
            'resource_type'  => $resourceType,
            'resource_id'    => $resourceId,
            'github_url'     => $githubUrl,
            'status'         => $status,
            'response'       => $response,
            'synced_at'      => now(),
        ]);
    }
}
