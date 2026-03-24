<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stores received GitHub webhook events for auditing and replay.
 */
class GitHubWebhook extends Model
{
    protected $table = 'github_webhooks';

    protected $fillable = [
        'integration_id',
        'delivery_id',
        'event',
        'action',
        'payload',
        'signature',
        'status',
        'processed_at',
        'error_message',
        'retry_count',
    ];

    protected $casts = [
        'payload'      => 'array',
        'processed_at' => 'datetime',
        'retry_count'  => 'integer',
    ];

    public function integration()
    {
        return $this->belongsTo(GitHubIntegration::class, 'integration_id');
    }
}
