# GitHub Integration Guide

This document describes the complete GitHub integration for **Aureus ERP**, covering OAuth setup, webhook management, data export, and two-way synchronisation.

---

## Table of Contents

1. [Overview](#overview)
2. [Environment Variables](#environment-variables)
3. [Database Migrations](#database-migrations)
4. [GitHub OAuth App Setup](#github-oauth-app-setup)
5. [API Endpoints](#api-endpoints)
   - [OAuth](#oauth)
   - [Connection Status](#connection-status)
   - [Repository Management](#repository-management)
   - [Webhook Management](#webhook-management)
   - [Issues & Pull Requests](#issues--pull-requests)
   - [Data Export](#data-export)
   - [Sync Logs](#sync-logs)
6. [Webhook Event Handling](#webhook-event-handling)
7. [Example Payloads](#example-payloads)
8. [Error Handling & Retries](#error-handling--retries)
9. [Security Notes](#security-notes)

---

## Overview

```
Aureus ERP  ←──── REST API ────→  GitHub API
                       │
               GitHub Webhooks
                       │
             (push / PR / issues / release)
```

The integration supports:
- GitHub OAuth 2.0 authentication
- Repository listing and creation
- Webhook registration on any repository
- Receiving and processing GitHub events
- Creating issues and pull requests from ERP data
- Pushing files directly to a repository
- Full audit trail (webhook log + sync log)

---

## Environment Variables

Add the following to your `.env` file:

```env
# Create an OAuth App at https://github.com/settings/developers
GITHUB_CLIENT_ID=your-github-oauth-app-client-id
GITHUB_CLIENT_SECRET=your-github-oauth-app-client-secret
GITHUB_REDIRECT_URI=http://localhost:8000/api/github/oauth/callback

# Secret used to verify inbound webhook payloads
GITHUB_WEBHOOK_SECRET=your-webhook-secret

# Optional defaults used for data-export operations
GITHUB_DEFAULT_REPO_OWNER=your-github-username
GITHUB_DEFAULT_REPO_NAME=aureuserp-data
```

---

## Database Migrations

Run the migrations to create the required tables:

```bash
php artisan migrate
```

Tables created:

| Table | Purpose |
|-------|---------|
| `github_integrations` | OAuth tokens and repo defaults per user |
| `github_webhooks` | Raw inbound webhook events (audit log) |
| `github_sync_logs` | Two-way sync operation history |

---

## GitHub OAuth App Setup

1. Go to **GitHub → Settings → Developer settings → OAuth Apps → New OAuth App**.
2. Fill in:
   - **Application name**: `Aureus ERP`
   - **Homepage URL**: `http://localhost:8000`
   - **Authorization callback URL**: `http://localhost:8000/api/github/oauth/callback`
3. Copy the **Client ID** and generate a **Client Secret**.
4. Add both to your `.env`.

---

## API Endpoints

### Base URL
```
http://localhost:8000/api
```

All authenticated endpoints require:
```
Authorization: Bearer <sanctum-token>
```

---

### OAuth

#### Initiate OAuth flow
```
GET /api/github/oauth/redirect
```
Returns a `redirect_url` to send the user to GitHub.

**Response:**
```json
{
  "redirect_url": "https://github.com/login/oauth/authorize?client_id=...&scope=repo,read:user,user:email,admin:repo_hook&state=..."
}
```

---

#### Handle OAuth callback
```
GET /api/github/oauth/callback?code=GITHUB_CODE&state=STATE
```
Exchanges the code for an access token and stores the integration.

**Response:**
```json
{
  "message": "GitHub account connected successfully",
  "integration": { "id": 1, "github_username": "octocat", ... },
  "github_user": {
    "id": 1,
    "login": "octocat",
    "name": "The Octocat",
    "email": "octocat@github.com",
    "avatar_url": "https://github.com/images/error/octocat_happy.gif"
  }
}
```

---

### Connection Status

#### Get connection status
```
GET /api/github/status        (auth required)
```

**Response (connected):**
```json
{
  "connected": true,
  "integration": {
    "id": 1,
    "github_username": "octocat",
    "github_email": "octocat@github.com",
    "avatar_url": "...",
    "scope": "repo,read:user,user:email,admin:repo_hook",
    "default_repo": "octocat/aureuserp-data",
    "connected_at": "2026-01-01T00:00:00.000000Z"
  }
}
```

---

#### Disconnect GitHub
```
DELETE /api/github/disconnect    (auth required)
```

---

### Repository Management

#### List repositories
```
GET /api/github/repos?page=1&per_page=30    (auth required)
```

#### Get a single repository
```
GET /api/github/repos/{owner}/{repo}    (auth required)
```

#### Create a repository
```
POST /api/github/repos    (auth required)
Content-Type: application/json

{
  "name": "my-erp-repo",
  "description": "Aureus ERP data repository",
  "private": true,
  "auto_init": true
}
```

#### Set default repository for sync
```
POST /api/github/repos/default    (auth required)
Content-Type: application/json

{
  "owner": "octocat",
  "repo": "aureuserp-data"
}
```

#### Push a file to a repository
```
POST /api/github/repos/{owner}/{repo}/files    (auth required)
Content-Type: application/json

{
  "path": "exports/orders.json",
  "content": "[{\"id\":1,\"status\":\"paid\"}]",
  "message": "Export orders from Aureus ERP",
  "branch": "main",
  "sha": "existing-file-sha-if-updating"
}
```

---

### Webhook Management

#### Register a webhook on a repository
```
POST /api/github/repos/{owner}/{repo}/webhooks    (auth required)
Content-Type: application/json

{
  "events": ["push", "pull_request", "issues", "release"]
}
```
GitHub will send events to `POST /api/github/webhooks/receive`.

#### List webhooks on a repository
```
GET /api/github/repos/{owner}/{repo}/webhooks    (auth required)
```

#### Receive GitHub events (called by GitHub)
```
POST /api/github/webhooks/receive    (public – no auth)
```
Required headers sent automatically by GitHub:
- `X-GitHub-Event` – event type
- `X-GitHub-Delivery` – unique delivery UUID
- `X-Hub-Signature-256` – HMAC signature

#### List stored webhook events
```
GET /api/github/webhooks?event=push&status=processed    (auth required)
```

#### Get a single webhook event
```
GET /api/github/webhooks/{id}    (auth required)
```

#### Retry a failed webhook
```
POST /api/github/webhooks/{id}/retry    (auth required)
```

---

### Issues & Pull Requests

#### Create an issue
```
POST /api/github/repos/{owner}/{repo}/issues    (auth required)
Content-Type: application/json

{
  "title": "Bug report from ERP order #1234",
  "body": "Order #1234 failed to process. Details:\n\nCustomer: John Doe\nAmount: $500",
  "labels": ["bug", "erp-import"]
}
```

#### Create a pull request
```
POST /api/github/repos/{owner}/{repo}/pulls    (auth required)
Content-Type: application/json

{
  "title": "Sync: ERP product catalogue update",
  "head": "feature/product-sync",
  "base": "main",
  "body": "Automated product sync from Aureus ERP.",
  "draft": false
}
```

---

### Data Export

#### Export sync logs
```
GET /api/github/export/sync-logs    (auth required)
```

#### Export webhook history
```
GET /api/github/export/webhooks    (auth required)
```

---

### Sync Logs

#### List sync log entries
```
GET /api/github/sync-logs    (auth required)
```

---

## Webhook Event Handling

Events supported out of the box:

| Event | Handler behaviour |
|-------|------------------|
| `push` | Logs commit count, branch, and pusher; records sync entry |
| `pull_request` | Logs PR number, title, and action; records sync entry |
| `issues` | Logs issue number, title, and action; records sync entry |
| `release` | Logs release tag and action; records sync entry |
| `ping` | Logs the GitHub zen quote |
| anything else | Logs as "unhandled" – stored for inspection |

---

## Example Payloads

### Push event (sent by GitHub to `/api/github/webhooks/receive`)

```json
{
  "ref": "refs/heads/main",
  "before": "0000000000000000000000000000000000000000",
  "after": "abc123def456",
  "repository": {
    "id": 123,
    "full_name": "octocat/aureuserp-data",
    "html_url": "https://github.com/octocat/aureuserp-data"
  },
  "pusher": { "name": "octocat", "email": "octocat@github.com" },
  "commits": [
    {
      "id": "abc123",
      "message": "Export orders",
      "author": { "name": "octocat" }
    }
  ]
}
```

### Issue opened event

```json
{
  "action": "opened",
  "issue": {
    "number": 42,
    "title": "Bug: order not processing",
    "body": "Steps to reproduce...",
    "state": "open",
    "html_url": "https://github.com/octocat/aureuserp-data/issues/42"
  },
  "repository": { "full_name": "octocat/aureuserp-data" },
  "sender": { "login": "octocat" }
}
```

### Release published event

```json
{
  "action": "published",
  "release": {
    "id": 1,
    "tag_name": "v1.0.0",
    "name": "First release",
    "body": "Release notes...",
    "html_url": "https://github.com/octocat/aureuserp-data/releases/tag/v1.0.0"
  },
  "repository": { "full_name": "octocat/aureuserp-data" }
}
```

---

## Error Handling & Retries

- All inbound webhooks are stored in `github_webhooks` before processing.
- If processing fails the record is marked `status = failed` and `retry_count` is incremented.
- Use `POST /api/github/webhooks/{id}/retry` to replay any failed event.
- All outbound GitHub API calls use Laravel's `Http` client with `.throw()` – HTTP errors are automatically converted to exceptions and bubbled up as 500 responses with descriptive messages.

---

## Security Notes

- **Webhook signature**: Set `GITHUB_WEBHOOK_SECRET` to a strong random string. The controller verifies every inbound request using `hash_hmac('sha256', …)` and `hash_equals` to prevent timing attacks. If the secret is blank, verification is skipped (not recommended for production).
- **Access tokens**: Stored encrypted in `github_integrations.access_token`. The field is hidden from JSON serialisation by default.
- **OAuth state**: A random `state` parameter is generated per-request and verified on callback to prevent CSRF attacks.
- **Sanctum auth**: All endpoints except the OAuth flow and webhook receiver require a valid Sanctum token.
