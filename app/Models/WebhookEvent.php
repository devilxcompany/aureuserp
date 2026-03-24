<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'source',
        'event_type',
        'delivery_id',
        'headers',
        'payload',
        'status',
        'handler',
        'error_message',
        'retry_count',
        'processed_at',
    ];

    protected $casts = [
        'headers'      => 'array',
        'payload'      => 'array',
        'processed_at' => 'datetime',
    ];

    /** Scope: unprocessed events */
    public function scopePending($query)
    {
        return $query->where('status', 'received');
    }

    /** Scope: failed events */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /** Scope: by source */
    public function scopeFromSource($query, string $source)
    {
        return $query->where('source', $source);
    }

    /** Mark as processing */
    public function markProcessing(string $handler): void
    {
        $this->update(['status' => 'processing', 'handler' => $handler]);
    }

    /** Mark as processed */
    public function markProcessed(): void
    {
        $this->update(['status' => 'processed', 'processed_at' => now()]);
    }

    /** Mark as failed */
    public function markFailed(string $error): void
    {
        $this->update([
            'status'        => 'failed',
            'error_message' => $error,
            'retry_count'   => $this->retry_count + 1,
        ]);
    }

    /** Mark as skipped (duplicate or irrelevant) */
    public function markSkipped(string $reason): void
    {
        $this->update([
            'status'        => 'skipped',
            'error_message' => $reason,
            'processed_at'  => now(),
        ]);
    }
}
