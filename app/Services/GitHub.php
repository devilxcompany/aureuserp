<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GitHub
{
    protected string $baseUrl = 'https://api.github.com';

    public function __construct(
        protected ?string $token = null,
    ) {
        $this->token = $token ?? config('services.github.token');
    }

    /**
     * Retrieve the latest release for a GitHub repository.
     *
     * @return array<string, mixed>|null
     */
    public function latestRelease(string $owner, string $repo): ?array
    {
        try {
            $response = $this->request()->get("{$this->baseUrl}/repos/{$owner}/{$repo}/releases/latest");

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (ConnectionException) {
            return null;
        }
    }

    /**
     * Retrieve all releases for a GitHub repository.
     *
     * @return array<int, array<string, mixed>>
     */
    public function releases(string $owner, string $repo, int $perPage = 30): array
    {
        try {
            $response = $this->request()->get("{$this->baseUrl}/repos/{$owner}/{$repo}/releases", [
                'per_page' => $perPage,
            ]);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            return [];
        } catch (ConnectionException) {
            return [];
        }
    }

    /**
     * Retrieve repository metadata from GitHub.
     *
     * @return array<string, mixed>|null
     */
    public function repository(string $owner, string $repo): ?array
    {
        try {
            $response = $this->request()->get("{$this->baseUrl}/repos/{$owner}/{$repo}");

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (ConnectionException) {
            return null;
        }
    }

    /**
     * Get the tag name of the latest release for a repository, or null when unavailable.
     */
    public function latestVersion(string $owner, string $repo): ?string
    {
        $release = $this->latestRelease($owner, $repo);

        if ($release === null) {
            return null;
        }

        return $release['tag_name'] ?? null;
    }

    /**
     * Build an HTTP client pre-configured with GitHub's required headers.
     */
    protected function request(): PendingRequest
    {
        $headers = [
            'Accept'               => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];

        if ($this->token) {
            $headers['Authorization'] = "Bearer {$this->token}";
        }

        return Http::withHeaders($headers)->timeout(10);
    }
}
