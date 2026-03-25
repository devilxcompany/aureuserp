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
            'connected'   => $this->isConfigured(),
            'base_url'    => $this->baseUrl,
            'project_id'  => $this->projectId ?: null,
            'template'    => config('stackblitz.template'),
        ];
    }
}
