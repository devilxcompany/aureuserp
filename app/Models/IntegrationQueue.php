<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IntegrationQueue extends Model
{
    use HasFactory;

    protected $table = 'integration_queue';

    protected $fillable = [
        'integration',
        'action',
        'data',
        'status',
        'priority',
        'attempts',
        'max_attempts',
        'last_error',
        'available_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'data'         => 'array',
        'available_at' => 'datetime',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** Scope: pending jobs ready to run */
    public function scopeReady($query)
    {
        return $query->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('available_at')
                  ->orWhere('available_at', '<=', now());
            })
            ->orderBy('priority')
            ->orderBy('created_at');
    }

    /** Scope: paused jobs */
    public function scopePaused($query)
    {
        return $query->where('status', 'paused');
    }

    /** Scope: failed jobs that can be retried */
    public function scopeRetryable($query)
    {
        return $query->where('status', 'failed')
            ->whereColumn('attempts', '<', 'max_attempts');
    }

    /** Start processing this job */
    public function start(): void
    {
        $this->update(['status' => 'processing', 'started_at' => now(), 'attempts' => $this->attempts + 1]);
    }

    /** Mark job as completed */
    public function complete(): void
    {
        $this->update(['status' => 'completed', 'completed_at' => now()]);
    }

    /** Mark job as failed */
    public function fail(string $error): void
    {
        $this->update([
            'status'     => 'failed',
            'last_error' => $error,
        ]);
    }

    /** Pause this job */
    public function pause(): void
    {
        $this->update(['status' => 'paused']);
    }

    /** Resume this job */
    public function resume(): void
    {
        $this->update(['status' => 'pending', 'available_at' => now()]);
    }

    /** Schedule retry with exponential backoff */
    public function scheduleRetry(): void
    {
        $delay = pow(2, $this->attempts) * 30; // 30s, 60s, 120s, ...
        $this->update([
            'status'       => 'pending',
            'available_at' => now()->addSeconds($delay),
        ]);
    }

    /** Enqueue a new integration job */
    public static function enqueue(
        string $integration,
        string $action,
        array $data = [],
        int $priority = 5,
        int $delaySeconds = 0
    ): self {
        return self::create([
            'integration'  => $integration,
            'action'       => $action,
            'data'         => $data,
            'status'       => 'pending',
            'priority'     => $priority,
            'max_attempts' => config('integrations.queue.max_attempts', 3),
            'available_at' => $delaySeconds > 0 ? now()->addSeconds($delaySeconds) : null,
        ]);
    }
}
