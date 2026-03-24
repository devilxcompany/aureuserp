<?php

namespace App\Services\Integration;

use App\Models\IntegrationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GitHub Integration Service
 *
 * Handles all communication with the GitHub API and webhook processing.
 * Supports: repository events, issues, releases, and order-to-issue tracking.
 */
class GitHubService
{
    private string $apiUrl;
    private string $token;
    private string $owner;
    private string $repo;

    public function __construct()
    {
        $this->apiUrl = config('integrations.github.api_url', 'https://api.github.com');
        $this->token  = config('integrations.github.token', '');
        $this->owner  = config('integrations.github.owner', '');
        $this->repo   = config('integrations.github.repo', '');
    }

    /** Test GitHub API connectivity */
    public function testConnection(): array
    {
        if (empty($this->token)) {
            return ['success' => false, 'error' => 'GitHub token not configured'];
        }

        $response = $this->makeRequest('GET', '/user');

        if ($response['success']) {
            return [
                'success' => true,
                'message' => 'GitHub connection successful',
                'user'    => $response['data']['login'] ?? 'unknown',
            ];
        }

        return $response;
    }

    /** Get repository information */
    public function getRepository(): array
    {
        return $this->makeRequest('GET', "/repos/{$this->owner}/{$this->repo}");
    }

    /** List open issues */
    public function listIssues(array $params = []): array
    {
        $query = http_build_query(array_merge(['state' => 'open', 'per_page' => 100], $params));
        return $this->makeRequest('GET', "/repos/{$this->owner}/{$this->repo}/issues?{$query}");
    }

    /** Create a GitHub issue (e.g., from an order) */
    public function createIssue(string $title, string $body, array $labels = []): array
    {
        $log = IntegrationLog::record('github', 'create_issue', compact('title', 'body', 'labels'));
        $log->markProcessing();

        $result = $this->makeRequest('POST', "/repos/{$this->owner}/{$this->repo}/issues", [
            'title'  => $title,
            'body'   => $body,
            'labels' => $labels,
        ]);

        if ($result['success']) {
            $log->markSuccess($result['data']);
        } else {
            $log->markFailed($result['error'] ?? 'Unknown error');
        }

        return $result;
    }

    /** Close a GitHub issue */
    public function closeIssue(int $issueNumber, string $comment = null): array
    {
        if ($comment) {
            $this->makeRequest('POST', "/repos/{$this->owner}/{$this->repo}/issues/{$issueNumber}/comments", [
                'body' => $comment,
            ]);
        }

        return $this->makeRequest('PATCH', "/repos/{$this->owner}/{$this->repo}/issues/{$issueNumber}", [
            'state' => 'closed',
        ]);
    }

    /** Add a comment to an issue */
    public function addIssueComment(int $issueNumber, string $body): array
    {
        return $this->makeRequest(
            'POST',
            "/repos/{$this->owner}/{$this->repo}/issues/{$issueNumber}/comments",
            ['body' => $body]
        );
    }

    /** List repository releases */
    public function listReleases(): array
    {
        return $this->makeRequest('GET', "/repos/{$this->owner}/{$this->repo}/releases");
    }

    /** Get the latest release */
    public function getLatestRelease(): array
    {
        return $this->makeRequest('GET', "/repos/{$this->owner}/{$this->repo}/releases/latest");
    }

    /** Create a new release */
    public function createRelease(string $tag, string $name, string $body, bool $draft = false): array
    {
        return $this->makeRequest('POST', "/repos/{$this->owner}/{$this->repo}/releases", [
            'tag_name' => $tag,
            'name'     => $name,
            'body'     => $body,
            'draft'    => $draft,
        ]);
    }

    /** List repository commits */
    public function listCommits(string $branch = 'main', int $perPage = 30): array
    {
        return $this->makeRequest(
            'GET',
            "/repos/{$this->owner}/{$this->repo}/commits?sha={$branch}&per_page={$perPage}"
        );
    }

    /**
     * Sync an ERP order to a GitHub issue.
     * Creates an issue tagged with ERP order metadata.
     */
    public function syncOrderToIssue(array $order): array
    {
        $title = "Order #{$order['order_number']} - {$order['customer_name']} - \${$order['total_amount']}";

        $body = "## ERP Order Details\n\n"
            . "| Field | Value |\n"
            . "|-------|-------|\n"
            . "| **Order Number** | `{$order['order_number']}` |\n"
            . "| **Customer** | {$order['customer_name']} |\n"
            . "| **Total** | \${$order['total_amount']} |\n"
            . "| **Status** | {$order['status']} |\n"
            . "| **Created** | {$order['created_at']} |\n\n"
            . "### Items\n\n";

        foreach ($order['items'] ?? [] as $item) {
            $body .= "- **{$item['name']}** × {$item['quantity']} @ \${$item['unit_price']}\n";
        }

        $body .= "\n\n---\n*Auto-synced from Aureus ERP*";

        return $this->createIssue($title, $body, ['erp-order', "status-{$order['status']}"]);
    }

    /**
     * Sync a product update to GitHub as an issue comment or new issue.
     */
    public function syncProductUpdate(array $product): array
    {
        $title = "Product Update: {$product['name']} (SKU: {$product['sku']})";

        $body = "## Product Update\n\n"
            . "| Field | Value |\n"
            . "|-------|-------|\n"
            . "| **SKU** | `{$product['sku']}` |\n"
            . "| **Name** | {$product['name']} |\n"
            . "| **Price** | \${$product['price']} |\n"
            . "| **Stock** | {$product['quantity']} units |\n"
            . "| **Status** | {$product['status']} |\n\n"
            . "---\n*Auto-synced from Aureus ERP*";

        return $this->createIssue($title, $body, ['erp-product', 'inventory-update']);
    }

    /** Verify a GitHub webhook signature */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = config('integrations.github.webhook_secret', '');

        if (empty($secret)) {
            return true; // Skip verification if no secret configured
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /** Process an incoming GitHub webhook event */
    public function processWebhookEvent(string $eventType, array $payload): array
    {
        $log = IntegrationLog::record('github', "webhook_{$eventType}", $payload, 'github', 'erp');
        $log->markProcessing();

        try {
            $result = match ($eventType) {
                'push'         => $this->handlePushEvent($payload),
                'issues'       => $this->handleIssuesEvent($payload),
                'pull_request' => $this->handlePullRequestEvent($payload),
                'release'      => $this->handleReleaseEvent($payload),
                default        => ['success' => true, 'message' => "Event '{$eventType}' acknowledged"],
            };

            $log->markSuccess($result);
            return $result;
        } catch (\Throwable $e) {
            $log->markFailed($e->getMessage());
            Log::error("GitHub webhook error [{$eventType}]: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function handlePushEvent(array $payload): array
    {
        return [
            'success'   => true,
            'action'    => 'push_logged',
            'branch'    => $payload['ref'] ?? 'unknown',
            'pusher'    => $payload['pusher']['name'] ?? 'unknown',
            'commits'   => count($payload['commits'] ?? []),
        ];
    }

    private function handleIssuesEvent(array $payload): array
    {
        return [
            'success' => true,
            'action'  => $payload['action'] ?? 'unknown',
            'issue'   => $payload['issue']['number'] ?? null,
            'title'   => $payload['issue']['title'] ?? null,
        ];
    }

    private function handlePullRequestEvent(array $payload): array
    {
        return [
            'success' => true,
            'action'  => $payload['action'] ?? 'unknown',
            'pr'      => $payload['pull_request']['number'] ?? null,
            'title'   => $payload['pull_request']['title'] ?? null,
        ];
    }

    private function handleReleaseEvent(array $payload): array
    {
        return [
            'success'     => true,
            'action'      => $payload['action'] ?? 'unknown',
            'release_tag' => $payload['release']['tag_name'] ?? null,
            'release_name'=> $payload['release']['name'] ?? null,
        ];
    }

    /** Make an authenticated request to the GitHub API */
    private function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        try {
            $http = Http::withHeaders([
                'Authorization' => "token {$this->token}",
                'Accept'        => 'application/vnd.github.v3+json',
                'User-Agent'    => 'AureusERP-Integration/1.0',
            ])->timeout(30);

            $url = $this->apiUrl . $endpoint;

            $response = match (strtoupper($method)) {
                'GET'   => $http->get($url),
                'POST'  => $http->post($url, $data),
                'PATCH' => $http->patch($url, $data),
                'PUT'   => $http->put($url, $data),
                'DELETE'=> $http->delete($url),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            return [
                'success' => false,
                'error'   => $response->json()['message'] ?? 'GitHub API error',
                'status'  => $response->status(),
            ];
        } catch (\Throwable $e) {
            Log::error("GitHub API request failed [{$method} {$endpoint}]: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
