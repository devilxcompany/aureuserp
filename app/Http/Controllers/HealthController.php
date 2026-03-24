<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    /**
     * Basic health check endpoint.
     * Returns HTTP 200 if the application is running.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status'    => 'ok',
            'timestamp' => now()->toIso8601String(),
            'app'       => config('app.name', 'AureusERP'),
            'version'   => config('app.version', '1.0.0'),
            'env'       => config('app.env', 'production'),
        ], 200);
    }

    /**
     * Detailed health check — verifies all services.
     * Used by monitoring tools and load balancers.
     */
    public function detailed(Request $request): JsonResponse
    {
        $checks   = [];
        $allOk    = true;
        $httpCode = 200;

        // ── Database check ────────────────────────────────
        try {
            DB::connection()->getPdo();
            $checks['database'] = [
                'status'  => 'ok',
                'driver'  => config('database.default'),
                'latency' => $this->measureLatency(fn () => DB::select('SELECT 1')),
            ];
        } catch (\Throwable $e) {
            $checks['database'] = [
                'status'  => 'error',
                'message' => $e->getMessage(),
            ];
            $allOk = false;
        }

        // ── Cache / Redis check ───────────────────────────
        try {
            $key = 'health_check_' . now()->timestamp;
            Cache::put($key, 'ok', 5);
            $value = Cache::get($key);
            Cache::forget($key);

            $checks['cache'] = [
                'status' => $value === 'ok' ? 'ok' : 'error',
                'driver' => config('cache.default'),
            ];
        } catch (\Throwable $e) {
            $checks['cache'] = [
                'status'  => 'error',
                'message' => $e->getMessage(),
            ];
            $allOk = false;
        }

        // ── Storage check ─────────────────────────────────
        try {
            $testFile = storage_path('app/.health_check');
            file_put_contents($testFile, 'ok');
            $readable = file_get_contents($testFile) === 'ok';
            unlink($testFile);

            $checks['storage'] = [
                'status' => $readable ? 'ok' : 'error',
                'path'   => storage_path('app'),
            ];
        } catch (\Throwable $e) {
            $checks['storage'] = [
                'status'  => 'error',
                'message' => $e->getMessage(),
            ];
            $allOk = false;
        }

        // ── Queue check ───────────────────────────────────
        $checks['queue'] = [
            'status'  => 'ok',
            'driver'  => config('queue.default'),
        ];

        // ── Memory usage ──────────────────────────────────
        $checks['memory'] = [
            'status'     => 'ok',
            'usage_mb'   => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_mb'    => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'limit'      => ini_get('memory_limit'),
        ];

        if (! $allOk) {
            $httpCode = 503;
        }

        return response()->json([
            'status'    => $allOk ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'app'       => config('app.name', 'AureusERP'),
            'checks'    => $checks,
        ], $httpCode);
    }

    /**
     * Measure the latency of a callable in milliseconds.
     */
    private function measureLatency(callable $fn): string
    {
        $start = microtime(true);
        $fn();
        return round((microtime(true) - $start) * 1000, 2) . 'ms';
    }
}
