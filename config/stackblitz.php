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

    'api_key'    => env('STACKBLITZ_API_KEY', ''),
    'base_url'   => env('STACKBLITZ_BASE_URL', 'https://bolt.new'),
    'embed_url'  => env('STACKBLITZ_EMBED_URL', 'https://stackblitz.com/edit'),
    'project_id' => env('STACKBLITZ_PROJECT_ID', ''),
    'template'   => env('STACKBLITZ_TEMPLATE', 'node'),
    'open_file'  => env('STACKBLITZ_OPEN_FILE', 'index.js'),
];
