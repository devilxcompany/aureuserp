<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stores GitHub OAuth credentials and repository configuration
 * for each user/tenant connected to GitHub.
 */
class GitHubIntegration extends Model
{
    protected $table = 'github_integrations';

    protected $fillable = [
        'user_id',
        'github_user_id',
        'github_username',
        'github_email',
        'access_token',
        'token_type',
        'scope',
        'avatar_url',
        'default_repo_owner',
        'default_repo_name',
        'is_active',
    ];

    protected $hidden = [
        'access_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function webhooks()
    {
        return $this->hasMany(GitHubWebhook::class, 'integration_id');
    }

    public function syncLogs()
    {
        return $this->hasMany(GitHubSyncLog::class, 'integration_id');
    }
}
