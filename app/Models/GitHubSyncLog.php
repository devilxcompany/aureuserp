<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tracks two-way synchronisation operations between Aureus ERP and GitHub.
 */
class GitHubSyncLog extends Model
{
    protected $table = 'github_sync_logs';

    protected $fillable = [
        'integration_id',
        'direction',
        'resource_type',
        'resource_id',
        'github_url',
        'status',
        'response',
        'error_message',
        'synced_at',
    ];

    protected $casts = [
        'response'  => 'array',
        'synced_at' => 'datetime',
    ];

    public function integration()
    {
        return $this->belongsTo(GitHubIntegration::class, 'integration_id');
    }
}
