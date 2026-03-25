<?php

return [
    /*
    |--------------------------------------------------------------------------
    | StackBlitz Bolt Integration Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to StackBlitz Bolt (bolt.new), the AI-powered
    | full-stack web development environment by StackBlitz.
    |
    */

    'api_key'        => env('STACKBLITZ_API_KEY', ''),
    'base_url'       => env('STACKBLITZ_BASE_URL', 'https://bolt.new'),
    'embed_url'      => env('STACKBLITZ_EMBED_URL', 'https://stackblitz.com/edit'),
    'project_id'     => env('STACKBLITZ_PROJECT_ID', ''),
    'template'       => env('STACKBLITZ_TEMPLATE', 'node'),
    'open_file'      => env('STACKBLITZ_OPEN_FILE', 'index.js'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    |
    | When Bolt.new sends webhook events to /bolt/webhook it signs the payload
    | with HMAC-SHA256 using this secret and puts the result in the
    | X-Bolt-Signature header.  Set this to a strong random string and store
    | the same value in your Bolt.new webhook settings.
    |
    | Leave empty to skip signature verification (not recommended in production).
    |
    */
    'webhook_secret' => env('STACKBLITZ_WEBHOOK_SECRET', ''),
];
