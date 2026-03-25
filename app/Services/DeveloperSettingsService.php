<?php

namespace App\Services;

class DeveloperSettingsService
{
    /**
     * The list of keys that belong to the developer settings config.
     */
    private const KEYS = [
        'debug_mode',
        'log_level',
        'log_http_requests',
        'log_queries',
        'api_rate_limit',
        'cache_driver',
        'enable_cors',
        'cors_origins',
        'api_docs_enabled',
        'maintenance_mode',
    ];

    /**
     * The valid log levels accepted by the application.
     */
    private const LOG_LEVELS = [
        'emergency',
        'alert',
        'critical',
        'error',
        'warning',
        'notice',
        'info',
        'debug',
    ];

    /**
     * The valid cache drivers accepted by the application.
     */
    private const CACHE_DRIVERS = ['file', 'array', 'redis', 'memcached', 'database'];

    /**
     * Return all current developer settings.
     */
    public function all(): array
    {
        $settings = [];

        foreach (self::KEYS as $key) {
            $settings[$key] = config("developer.{$key}");
        }

        return $settings;
    }

    /**
     * Validate a settings payload and return the cleaned values or a list of errors.
     *
     * @return array{valid: bool, data: array, errors: array}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $data   = [];

        // debug_mode
        if (array_key_exists('debug_mode', $input)) {
            $data['debug_mode'] = (bool) $input['debug_mode'];
        }

        // log_level
        if (array_key_exists('log_level', $input)) {
            $level = strtolower((string) $input['log_level']);
            if (!in_array($level, self::LOG_LEVELS, true)) {
                $errors['log_level'] = 'Invalid log level. Allowed: ' . implode(', ', self::LOG_LEVELS);
            } else {
                $data['log_level'] = $level;
            }
        }

        // log_http_requests
        if (array_key_exists('log_http_requests', $input)) {
            $data['log_http_requests'] = (bool) $input['log_http_requests'];
        }

        // log_queries
        if (array_key_exists('log_queries', $input)) {
            $data['log_queries'] = (bool) $input['log_queries'];
        }

        // api_rate_limit
        if (array_key_exists('api_rate_limit', $input)) {
            $limit = (int) $input['api_rate_limit'];
            if ($limit < 1 || $limit > 10000) {
                $errors['api_rate_limit'] = 'api_rate_limit must be between 1 and 10000.';
            } else {
                $data['api_rate_limit'] = $limit;
            }
        }

        // cache_driver
        if (array_key_exists('cache_driver', $input)) {
            $driver = strtolower((string) $input['cache_driver']);
            if (!in_array($driver, self::CACHE_DRIVERS, true)) {
                $errors['cache_driver'] = 'Invalid cache driver. Allowed: ' . implode(', ', self::CACHE_DRIVERS);
            } else {
                $data['cache_driver'] = $driver;
            }
        }

        // enable_cors
        if (array_key_exists('enable_cors', $input)) {
            $data['enable_cors'] = (bool) $input['enable_cors'];
        }

        // cors_origins — require a non-empty explicit value; do not fall back to '*' silently
        if (array_key_exists('cors_origins', $input)) {
            $origins = trim((string) $input['cors_origins']);
            if ($origins === '') {
                $errors['cors_origins'] = 'cors_origins must not be empty. Use "*" to allow all origins explicitly.';
            } else {
                $data['cors_origins'] = $origins;
            }
        }

        // api_docs_enabled
        if (array_key_exists('api_docs_enabled', $input)) {
            $data['api_docs_enabled'] = (bool) $input['api_docs_enabled'];
        }

        // maintenance_mode
        if (array_key_exists('maintenance_mode', $input)) {
            $data['maintenance_mode'] = (bool) $input['maintenance_mode'];
        }

        return [
            'valid'  => empty($errors),
            'data'   => $data,
            'errors' => $errors,
        ];
    }

    /**
     * Apply validated settings to the running config (runtime only; does not write .env).
     */
    public function apply(array $settings): void
    {
        foreach ($settings as $key => $value) {
            config(["developer.{$key}" => $value]);
        }
    }

    /**
     * Return the default values for all developer settings.
     */
    public function defaults(): array
    {
        return [
            'debug_mode'         => false,
            'log_level'          => 'error',
            'log_http_requests'  => false,
            'log_queries'        => false,
            'api_rate_limit'     => 60,
            'cache_driver'       => 'file',
            'enable_cors'        => false,
            'cors_origins'       => '*',
            'api_docs_enabled'   => false,
            'maintenance_mode'   => false,
        ];
    }

    /**
     * Return only the settings keys that differ from their defaults.
     */
    public function diff(): array
    {
        $current  = $this->all();
        $defaults = $this->defaults();
        $changed  = [];

        foreach ($current as $key => $value) {
            if (array_key_exists($key, $defaults) && $defaults[$key] !== $value) {
                $changed[$key] = ['default' => $defaults[$key], 'current' => $value];
            }
        }

        return $changed;
    }
}
