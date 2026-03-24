<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\IntegrationLog;
use App\Models\IntegrationConfig;
use App\Services\Integration\GitHubService;
use App\Services\Integration\SupabaseService;
use App\Services\Integration\BoltCmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Unified API Controller
 *
 * Aggregates data from all integration services into unified endpoints.
 * Provides a single API surface to query data across all platforms.
 */
class UnifiedApiController extends Controller
{
    public function __construct(
        private readonly GitHubService   $github,
        private readonly SupabaseService $supabase,
        private readonly BoltCmsService  $boltCms
    ) {}

    /**
     * GET /api/unified/dashboard
     * Aggregated dashboard data from all platforms.
     */
    public function dashboard(): JsonResponse
    {
        $data = [
            'timestamp'    => now()->toISOString(),
            'github'       => $this->getGitHubSummary(),
            'supabase'     => $this->getSupabaseSummary(),
            'bolt_cms'     => $this->getBoltCmsSummary(),
            'integration'  => $this->getIntegrationSummary(),
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/unified/orders
     * Aggregated orders from all platforms.
     */
    public function orders(Request $request): JsonResponse
    {
        $since   = $request->input('since');
        $sources = $request->input('sources', ['erp', 'supabase', 'bolt_cms']);

        $data = [];

        if (in_array('bolt_cms', $sources)) {
            $boltOrders = $this->boltCms->collectNewOrders($since);
            $data['bolt_cms'] = $boltOrders;
        }

        if (in_array('supabase', $sources)) {
            $supabaseResult  = $this->supabase->fetchOrders($since ? ['updated_at' => $since] : []);
            $data['supabase'] = $supabaseResult['success'] ? ($supabaseResult['data'] ?? []) : [];
        }

        return response()->json([
            'success' => true,
            'sources' => $sources,
            'data'    => $data,
        ]);
    }

    /**
     * GET /api/unified/products
     * Aggregated products from all platforms.
     */
    public function products(Request $request): JsonResponse
    {
        $sources = $request->input('sources', ['erp', 'supabase']);
        $data    = [];

        if (in_array('supabase', $sources)) {
            $result          = $this->supabase->fetchProducts();
            $data['supabase'] = $result['success'] ? ($result['data'] ?? []) : [];
        }

        if (in_array('bolt_cms', $sources)) {
            $site1    = $this->boltCms->getProducts('site1');
            $site2    = $this->boltCms->getProducts('site2');
            $data['bolt_cms'] = [
                'site1' => $site1['success'] ? ($site1['data'] ?? []) : [],
                'site2' => $site2['success'] ? ($site2['data'] ?? []) : [],
            ];
        }

        return response()->json([
            'success' => true,
            'sources' => $sources,
            'data'    => $data,
        ]);
    }

    /**
     * GET /api/unified/customers
     * Aggregated customers from all platforms.
     */
    public function customers(Request $request): JsonResponse
    {
        $sources = $request->input('sources', ['erp', 'supabase']);
        $data    = [];

        if (in_array('supabase', $sources)) {
            $result          = $this->supabase->fetchCustomers();
            $data['supabase'] = $result['success'] ? ($result['data'] ?? []) : [];
        }

        return response()->json([
            'success' => true,
            'sources' => $sources,
            'data'    => $data,
        ]);
    }

    /**
     * GET /api/unified/github
     * Aggregated GitHub data (issues, releases, commits).
     */
    public function github(Request $request): JsonResponse
    {
        $types = $request->input('types', ['issues', 'releases']);

        $data = [];

        if (in_array('issues', $types)) {
            $result       = $this->github->listIssues();
            $data['issues'] = $result['success'] ? ($result['data'] ?? []) : [];
        }

        if (in_array('releases', $types)) {
            $result         = $this->github->listReleases();
            $data['releases'] = $result['success'] ? ($result['data'] ?? []) : [];
        }

        if (in_array('commits', $types)) {
            $branch         = $request->input('branch', 'main');
            $result         = $this->github->listCommits($branch, 10);
            $data['commits'] = $result['success'] ? ($result['data'] ?? []) : [];
        }

        return response()->json([
            'success' => true,
            'types'   => $types,
            'data'    => $data,
        ]);
    }

    /**
     * GET /api/unified/config
     * Get all integration configuration (non-sensitive values only).
     */
    public function config(): JsonResponse
    {
        $configs = [];

        foreach (['github', 'pabbly', 'supabase', 'bolt_cms', 'master'] as $integration) {
            $configs[$integration] = IntegrationConfig::getIntegrationConfig($integration);
        }

        return response()->json([
            'success' => true,
            'configs' => $configs,
        ]);
    }

    /**
     * PUT /api/unified/config/{integration}
     * Update integration configuration.
     */
    public function updateConfig(Request $request, string $integration): JsonResponse
    {
        if (!in_array($integration, ['github', 'pabbly', 'supabase', 'bolt_cms'])) {
            return response()->json(['error' => "Unknown integration: {$integration}"], 422);
        }

        $settings = $request->except(['_token', '_method']);

        foreach ($settings as $key => $value) {
            IntegrationConfig::setValue($integration, $key, $value);
        }

        return response()->json([
            'success'     => true,
            'integration' => $integration,
            'updated'     => array_keys($settings),
        ]);
    }

    private function getGitHubSummary(): array
    {
        $enabled = config('integrations.github.enabled', false);

        if (!$enabled) {
            return ['enabled' => false];
        }

        $connection = $this->github->testConnection();
        return [
            'enabled'   => true,
            'connected' => $connection['success'],
            'owner'     => config('integrations.github.owner'),
            'repo'      => config('integrations.github.repo'),
        ];
    }

    private function getSupabaseSummary(): array
    {
        $enabled = config('integrations.supabase.enabled', false);

        if (!$enabled) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'url'     => config('integrations.supabase.url'),
        ];
    }

    private function getBoltCmsSummary(): array
    {
        $enabled = config('integrations.bolt_cms.enabled', false);

        return [
            'enabled'  => $enabled,
            'site1_url'=> config('integrations.bolt_cms.site1_url'),
            'site2_url'=> config('integrations.bolt_cms.site2_url'),
        ];
    }

    private function getIntegrationSummary(): array
    {
        $total   = IntegrationLog::where('created_at', '>', now()->subHours(24))->count();
        $success = IntegrationLog::where('created_at', '>', now()->subHours(24))->where('status', 'success')->count();
        $failed  = IntegrationLog::where('created_at', '>', now()->subHours(24))->where('status', 'failed')->count();

        return [
            'events_24h'     => $total,
            'success_24h'    => $success,
            'failed_24h'     => $failed,
            'success_rate'   => $total > 0 ? round(($success / $total) * 100, 1) : 100.0,
        ];
    }
}
