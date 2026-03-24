<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IntegrationConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'integration',
        'key',
        'value',
        'type',
        'is_encrypted',
        'is_enabled',
        'description',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
        'is_enabled'   => 'boolean',
    ];

    /** Get the typed value */
    public function getTypedValue(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'json'    => json_decode($this->value, true),
            default   => $this->value,
        };
    }

    /** Get a config value by integration and key */
    public static function getValue(string $integration, string $key, mixed $default = null): mixed
    {
        $config = self::where('integration', $integration)
            ->where('key', $key)
            ->where('is_enabled', true)
            ->first();

        if (!$config) {
            return $default;
        }

        return $config->getTypedValue();
    }

    /** Set or update a config value */
    public static function setValue(
        string $integration,
        string $key,
        mixed $value,
        string $type = 'string',
        string $description = null
    ): self {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
            $type  = 'json';
        }

        return self::updateOrCreate(
            ['integration' => $integration, 'key' => $key],
            [
                'value'       => (string) $value,
                'type'        => $type,
                'description' => $description,
                'is_enabled'  => true,
            ]
        );
    }

    /** Get all configs for an integration as key-value array */
    public static function getIntegrationConfig(string $integration): array
    {
        return self::where('integration', $integration)
            ->where('is_enabled', true)
            ->get()
            ->mapWithKeys(fn($c) => [$c->key => $c->getTypedValue()])
            ->toArray();
    }

    /** Scope: enabled configs */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /** Scope: by integration */
    public function scopeForIntegration($query, string $integration)
    {
        return $query->where('integration', $integration);
    }
}
