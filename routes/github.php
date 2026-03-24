<?php

use App\Http\Controllers\GitHubController;
use App\Http\Controllers\GitHubWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| GitHub Integration Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/github. The webhook receiver is public
| (GitHub calls it directly). All other endpoints require authentication.
|
*/

// ── Public ────────────────────────────────────────────────────────────────────

// GitHub OAuth flow
Route::get('/github/oauth/redirect', [GitHubController::class, 'oauthRedirect'])
    ->name('github.oauth.redirect');

Route::get('/github/oauth/callback', [GitHubController::class, 'oauthCallback'])
    ->name('github.oauth.callback');

// Webhook receiver (called by GitHub – no auth middleware)
Route::post('/github/webhooks/receive', [GitHubWebhookController::class, 'receive'])
    ->name('github.webhooks.receive');

// ── Authenticated ─────────────────────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {

    // Connection status & management
    Route::get('/github/status',     [GitHubController::class, 'status'])->name('github.status');
    Route::delete('/github/disconnect', [GitHubController::class, 'disconnect'])->name('github.disconnect');

    // Repository management
    Route::get('/github/repos',                         [GitHubController::class, 'listRepos'])->name('github.repos.index');
    Route::post('/github/repos',                        [GitHubController::class, 'createRepo'])->name('github.repos.create');
    Route::get('/github/repos/{owner}/{repo}',          [GitHubController::class, 'getRepo'])->name('github.repos.show');
    Route::post('/github/repos/default',                [GitHubController::class, 'setDefaultRepo'])->name('github.repos.default');

    // Webhook registration on a repo
    Route::get('/github/repos/{owner}/{repo}/webhooks',  [GitHubController::class, 'listRepoWebhooks'])->name('github.repos.webhooks.index');
    Route::post('/github/repos/{owner}/{repo}/webhooks', [GitHubController::class, 'createRepoWebhook'])->name('github.repos.webhooks.create');

    // Issue & PR creation from ERP data
    Route::post('/github/repos/{owner}/{repo}/issues', [GitHubController::class, 'createIssue'])->name('github.issues.create');
    Route::post('/github/repos/{owner}/{repo}/pulls',  [GitHubController::class, 'createPullRequest'])->name('github.pulls.create');

    // File push
    Route::post('/github/repos/{owner}/{repo}/files',  [GitHubController::class, 'pushFileToRepo'])->name('github.files.push');

    // Webhook log
    Route::get('/github/webhooks',          [GitHubWebhookController::class, 'index'])->name('github.webhooks.index');
    Route::get('/github/webhooks/{id}',     [GitHubWebhookController::class, 'show'])->name('github.webhooks.show');
    Route::post('/github/webhooks/{id}/retry', [GitHubWebhookController::class, 'retry'])->name('github.webhooks.retry');

    // Data exports
    Route::get('/github/export/sync-logs', [GitHubController::class, 'exportSyncLogs'])->name('github.export.sync-logs');
    Route::get('/github/export/webhooks',  [GitHubController::class, 'exportWebhooks'])->name('github.export.webhooks');

    // Sync log history
    Route::get('/github/sync-logs', [GitHubController::class, 'syncLogs'])->name('github.sync-logs');
});
