<?php

namespace App\Services;

class BoltService
{
    /**
     * The base URL for StackBlitz Bolt.
     */
    protected string $baseUrl;

    /**
     * The StackBlitz embed URL.
     */
    protected string $embedUrl;

    /**
     * The API key for StackBlitz.
     */
    protected string $apiKey;

    /**
     * The StackBlitz project ID.
     */
    protected string $projectId;

    public function __construct()
    {
        $this->baseUrl   = config('stackblitz.base_url', 'https://bolt.new');
        $this->embedUrl  = config('stackblitz.embed_url', 'https://stackblitz.com/edit');
        $this->apiKey    = config('stackblitz.api_key', '');
        $this->projectId = config('stackblitz.project_id', '');
    }

    /**
     * Generate the URL to open or create a project in StackBlitz Bolt.
     */
    public function getBoltUrl(array $params = []): string
    {
        $query = array_filter(array_merge([
            'template' => config('stackblitz.template', 'node'),
            'file'     => config('stackblitz.open_file', 'index.js'),
        ], $params));

        return $this->baseUrl . ($query ? '?' . http_build_query($query) : '');
    }

    /**
     * Generate the embed URL for a specific StackBlitz project.
     */
    public function getEmbedUrl(string $projectId = '', array $params = []): string
    {
        $id = $projectId ?: $this->projectId;

        $query = array_filter(array_merge([
            'embed'        => '1',
            'hideNavigation' => '1',
            'view'         => 'editor',
        ], $params));

        return rtrim($this->embedUrl, '/') . '/' . ltrim($id, '/') . '?' . http_build_query($query);
    }

    /**
     * Generate a Bolt.new URL that pre-loads an AI prompt.
     *
     * Visiting the returned URL opens bolt.new with the given prompt already
     * typed into the AI chat, so the developer can hit Enter and start building.
     *
     * @param  string  $prompt      Natural-language description of what to build.
     * @param  array   $extraParams Additional query-string parameters to append.
     */
    public function getAiPromptUrl(string $prompt, array $extraParams = []): string
    {
        if ($prompt === '') {
            return $this->baseUrl;
        }

        $params = array_filter(array_merge(
            ['prompt' => $this->sanitizePrompt($prompt)],
            $extraParams,
        ));

        return $this->baseUrl . '?' . http_build_query($params);
    }

    /**
     * Generate a Bolt.new import URL for a public GitHub repository.
     *
     * Visiting the returned URL opens the repo inside bolt.new so the AI can
     * analyse the existing code and the developer can iterate on it.
     *
     * @param  string  $repoUrl     Full GitHub HTTPS URL, e.g. https://github.com/org/repo
     * @param  string  $prompt      Optional follow-up AI prompt to show after import.
     */
    public function getImportUrl(string $repoUrl, string $prompt = ''): string
    {
        // Bolt.new accepts GitHub URLs directly: https://bolt.new/~/github.com/org/repo
        $githubPath = preg_replace('#^https?://#', '', rtrim($repoUrl, '/'));
        $importUrl  = $this->baseUrl . '/~/'. $githubPath;

        if ($prompt !== '') {
            $importUrl .= '?' . http_build_query(['prompt' => $this->sanitizePrompt($prompt)]);
        }

        return $importUrl;
    }

    /**
     * Validate the HMAC-SHA256 signature on an inbound Bolt webhook request.
     *
     * Bolt sends an X-Bolt-Signature header in the form "sha256=<hex>".
     * Returns true when the signature matches, false otherwise.
     *
     * @param  string  $rawBody   Raw request body string.
     * @param  string  $signature Header value of X-Bolt-Signature.
     */
    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $secret = config('stackblitz.webhook_secret', '');

        if ($secret === '') {
            // No secret configured — skip verification (warn in production).
            return true;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Check whether the Bolt integration is properly configured.
     * An API key is sufficient; a project ID is optional (new projects can be created without one).
     */
    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Return the current connection status information.
     */
    public function getStatus(): array
    {
        return [
            'connected'      => $this->isConfigured(),
            'base_url'       => $this->baseUrl,
            'project_id'     => $this->projectId ?: null,
            'template'       => config('stackblitz.template'),
            'webhook_secret' => config('stackblitz.webhook_secret', '') !== '' ? '***' : null,
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Trim and collapse whitespace in a prompt string so the URL stays clean.
     */
    private function sanitizePrompt(string $prompt): string
    {
        return trim(preg_replace('/\s+/', ' ', $prompt));
    }
}
