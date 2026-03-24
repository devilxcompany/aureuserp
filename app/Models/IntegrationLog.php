<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IntegrationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'integration',
        'event_type',
        'source',
        'destination',
        'status',
        'payload',
        'response',
        'error_message',
        'trace_id',
        'retry_count',
        'processed_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'response'     => 'array',
        'processed_at' => 'datetime',
    ];

    /** Scope: filter by integration */
    public function scopeForIntegration($query, string $integration)
    {
        return $query->where('integration', $integration);
    }

    /** Scope: filter by status */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /** Scope: failed logs */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /** Mark log as processing */
    public function markProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    /** Mark log as successful */
    public function markSuccess(array $response = []): void
    {
        $this->update([
            'status'       => 'success',
            'response'     => $response,
            'processed_at' => now(),
        ]);
    }

    /** Mark log as failed */
    public function markFailed(string $error, array $response = []): void
    {
        $this->update([
            'status'        => 'failed',
            'error_message' => $error,
            'response'      => $response,
            'retry_count'   => $this->retry_count + 1,
        ]);
    }

    /** Create a new log entry */
    public static function record(
        string $integration,
        string $eventType,
        array $payload = [],
        string $source = null,
        string $destination = null,
        string $traceId = null
    ): self {
        return self::create([
            'integration' => $integration,
            'event_type'  => $eventType,
            'source'      => $source,
            'destination' => $destination,
            'status'      => 'pending',
            'payload'     => $payload,
            'trace_id'    => $traceId ?? uniqid('trace_', true),
        ]);
    }
}
