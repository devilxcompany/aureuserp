<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Developer Settings
    |--------------------------------------------------------------------------
    |
    | Settings that are useful during development and integration work.
    | All values are driven by .env variables so they can be changed
    | per-environment without touching code.
    |
    */

    // Enable verbose debug output (stack traces, query logs, etc.)
    'debug_mode' => env('DEV_DEBUG_MODE', false),

    // Application log level: emergency, alert, critical, error, warning, notice, info, debug
    'log_level' => env('DEV_LOG_LEVEL', 'error'),

    // Whether to log every outgoing HTTP request made by the application
    'log_http_requests' => env('DEV_LOG_HTTP_REQUESTS', false),

    // Whether to log every database query executed
    'log_queries' => env('DEV_LOG_QUERIES', false),

    // Maximum number of API requests allowed per minute (rate-limiting)
    'api_rate_limit' => (int) env('DEV_API_RATE_LIMIT', 60),

    // Cache driver to use during development: file, array, redis, memcached, database
    'cache_driver' => env('DEV_CACHE_DRIVER', 'file'),

    // Whether to force HTTP responses to include CORS headers
    'enable_cors' => env('DEV_ENABLE_CORS', false),

    // Comma-separated list of origins allowed when CORS is enabled
    'cors_origins' => env('DEV_CORS_ORIGINS', '*'),

    // Expose the application's API documentation endpoint
    'api_docs_enabled' => env('DEV_API_DOCS_ENABLED', false),

    // Maintenance mode — when true the app returns 503 for all requests
    'maintenance_mode' => env('DEV_MAINTENANCE_MODE', false),
];
