<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master Integration Configuration
    |--------------------------------------------------------------------------
    | Centralized configuration for all integration services.
    */

    'enabled' => env('INTEGRATIONS_ENABLED', true),

    'log_level' => env('INTEGRATION_LOG_LEVEL', 'info'), // debug, info, warning, error

    /*
    |--------------------------------------------------------------------------
    | GitHub Integration
    |--------------------------------------------------------------------------
    */
    'github' => [
        'enabled'       => env('GITHUB_INTEGRATION_ENABLED', true),
        'token'         => env('GITHUB_TOKEN', ''),
        'owner'         => env('GITHUB_OWNER', ''),
        'repo'          => env('GITHUB_REPO', ''),
        'webhook_secret'=> env('GITHUB_WEBHOOK_SECRET', ''),
        'api_url'       => env('GITHUB_API_URL', 'https://api.github.com'),
        'events'        => ['push', 'pull_request', 'issues', 'release', 'create'],
        'sync_issues'   => env('GITHUB_SYNC_ISSUES', true),
        'sync_releases' => env('GITHUB_SYNC_RELEASES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pabbly Connect Integration
    |--------------------------------------------------------------------------
    */
    'pabbly' => [
        'enabled'          => env('PABBLY_ENABLED', true),
        'api_key'          => env('PABBLY_API_KEY', ''),
        'webhook_url'      => env('PABBLY_WEBHOOK_URL', ''),
        'verify_signature' => env('PABBLY_VERIFY_SIGNATURE', true),
        'log_webhooks'     => env('PABBLY_LOG_WEBHOOKS', true),
        'retry_attempts'   => env('PABBLY_RETRY_ATTEMPTS', 3),
        'retry_delay'      => env('PABBLY_RETRY_DELAY', 60), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Supabase Integration
    |--------------------------------------------------------------------------
    */
    'supabase' => [
        'enabled'    => env('SUPABASE_ENABLED', true),
        'url'        => env('SUPABASE_URL', ''),
        'key'        => env('SUPABASE_KEY', ''),
        'service_key'=> env('SUPABASE_SERVICE_KEY', ''),
        'db_host'    => env('SUPABASE_DB_HOST', ''),
        'db_port'    => env('SUPABASE_DB_PORT', 5432),
        'db_name'    => env('SUPABASE_DB_NAME', 'postgres'),
        'db_user'    => env('SUPABASE_DB_USER', 'postgres'),
        'db_password'=> env('SUPABASE_DB_PASSWORD', ''),
        'sync_tables'=> ['orders', 'products', 'customers', 'invoices'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bolt CMS Integration
    |--------------------------------------------------------------------------
    */
    'bolt_cms' => [
        'enabled'        => env('BOLT_CMS_ENABLED', true),
        'site1_url'      => env('BOLT_CMS_SITE1_URL', ''),
        'site1_api_key'  => env('BOLT_CMS_SITE1_API_KEY', ''),
        'site2_url'      => env('BOLT_CMS_SITE2_URL', ''),
        'site2_api_key'  => env('BOLT_CMS_SITE2_API_KEY', ''),
        'webhook_token'  => env('BOLT_CMS_WEBHOOK_TOKEN', ''),
        'sync_content'   => env('BOLT_CMS_SYNC_CONTENT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'driver'        => env('INTEGRATION_QUEUE_DRIVER', 'database'),
        'connection'    => env('INTEGRATION_QUEUE_CONNECTION', 'default'),
        'max_attempts'  => env('INTEGRATION_QUEUE_MAX_ATTEMPTS', 3),
        'retry_after'   => env('INTEGRATION_QUEUE_RETRY_AFTER', 90),
        'timeout'       => env('INTEGRATION_QUEUE_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    */
    'sync' => [
        'batch_size'            => env('SYNC_BATCH_SIZE', 100),
        'conflict_resolution'   => env('SYNC_CONFLICT_RESOLUTION', 'latest_wins'), // latest_wins, erp_wins, cms_wins
        'duplicate_prevention'  => env('SYNC_DUPLICATE_PREVENTION', true),
        'auto_sync_interval'    => env('SYNC_AUTO_INTERVAL', 300), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Alerts
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'enabled'        => env('MONITORING_ENABLED', true),
        'alert_email'    => env('MONITORING_ALERT_EMAIL', ''),
        'health_check_interval' => env('HEALTH_CHECK_INTERVAL', 60),
        'max_failure_threshold' => env('MAX_FAILURE_THRESHOLD', 5),
    ],

];
