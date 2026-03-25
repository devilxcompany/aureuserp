<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     *
     * When the developer setting `maintenance_mode` is enabled the middleware
     * short-circuits all incoming requests and returns a 503 Service Unavailable
     * response.  This lets a developer toggle downtime without redeploying.
     *
     * Browser requests (Accept: text/html) receive an HTML page; API / XHR
     * requests receive a JSON body.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('developer.maintenance_mode', false)) {
            return $next($request);
        }

        $message = 'The application is currently undergoing maintenance. Please try again later.';

        if ($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) {
            return response()->json(['message' => $message], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 – Maintenance</title>
    <style>
        body { font-family: sans-serif; display: flex; align-items: center; justify-content: center;
               min-height: 100vh; margin: 0; background: #f8f9fa; color: #343a40; }
        .box { text-align: center; max-width: 480px; padding: 2rem; }
        h1   { font-size: 3rem; margin: 0 0 .5rem; }
        p    { font-size: 1.1rem; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="box">
        <h1>🔧</h1>
        <h2>Under Maintenance</h2>
        <p>{$message}</p>
    </div>
</body>
</html>
HTML;

        return response($html, Response::HTTP_SERVICE_UNAVAILABLE)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
