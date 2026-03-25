<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleDeveloperCors
{
    /**
     * Handle an incoming request.
     *
     * When the developer setting `enable_cors` is true, the middleware attaches
     * CORS headers to every response using the `cors_origins` setting as the
     * value for the Access-Control-Allow-Origin header.
     *
     * Pre-flight OPTIONS requests are answered immediately with 204 No Content
     * so that browsers can proceed without hitting the full application stack.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('developer.enable_cors', false)) {
            return $next($request);
        }

        $origins = config('developer.cors_origins', '*');

        // Answer pre-flight requests immediately.
        if ($request->isMethod('OPTIONS')) {
            return response('', Response::HTTP_NO_CONTENT)
                ->withHeaders($this->corsHeaders($origins));
        }

        $response = $next($request);

        foreach ($this->corsHeaders($origins) as $header => $value) {
            $response->headers->set($header, $value);
        }

        return $response;
    }

    /**
     * Build the CORS header set for a given origin specification.
     *
     * Per the CORS spec, Access-Control-Allow-Credentials: true is incompatible
     * with a wildcard origin.  Credentials headers are only attached when a
     * specific (non-wildcard) origin is configured.
     */
    private function corsHeaders(string $origins): array
    {
        $headers = [
            'Access-Control-Allow-Origin'  => $origins,
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
            'Access-Control-Max-Age'       => '86400',
        ];

        // Credentials require a specific origin; wildcard is forbidden.
        if ($origins !== '*') {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        return $headers;
    }
}
