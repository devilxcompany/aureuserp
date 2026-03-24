# 🐙 GitHub Integration Guide

## Overview

The GitHub integration connects Aureus ERP to your GitHub repository, enabling:

- **Order → Issue Sync**: Every ERP order becomes a tracked GitHub issue
- **Product Updates → Issues**: Product inventory changes are tracked
- **Webhook Events**: Push, PR, release, and issue events are logged
- **Release Tracking**: Monitor repository releases from the ERP dashboard

---

## Setup

### 1. Create a GitHub Personal Access Token

1. Go to [GitHub Settings → Developer Settings → Personal Access Tokens](https://github.com/settings/tokens)
2. Click **Generate new token (classic)**
3. Select scopes:
   - ✅ `repo` (full repository access)
   - ✅ `read:user`
4. Copy the token and add to `.env`:

```env
GITHUB_TOKEN=ghp_your_token_here
GITHUB_OWNER=devilxcompany
GITHUB_REPO=aureuserp
```

### 2. Configure Webhook in GitHub

1. Go to your repository → **Settings → Webhooks → Add webhook**
2. Set **Payload URL**: `https://your-domain.com/api/webhooks/github`
3. Set **Content type**: `application/json`
4. Set **Secret**: copy to `GITHUB_WEBHOOK_SECRET` in `.env`
5. Select events:
   - ✅ Pushes
   - ✅ Issues
   - ✅ Pull requests
   - ✅ Releases

### 3. Verify Connection

```bash
curl http://localhost:8000/api/integrations/github/status
```

Expected response:
```json
{
  "success": true,
  "connection": {"success": true, "user": "devilxcompany"},
  "repository": {
    "name": "aureuserp",
    "url": "https://github.com/devilxcompany/aureuserp",
    "open_issues": 5
  }
}
```

---

## API Endpoints

### Check GitHub Status
```bash
GET /api/integrations/github/status
```

### Receive Webhook (GitHub → ERP)
```bash
POST /api/webhooks/github
Headers:
  X-GitHub-Event: push
  X-Hub-Signature-256: sha256=<signature>
  X-GitHub-Delivery: <uuid>
```

### List Issues
```bash
GET /api/integrations/github/issues
GET /api/integrations/github/issues?state=open&labels=erp-order
```

### Create Issue Manually
```bash
POST /api/integrations/github/issues
{
  "title": "New Order #ORD-001",
  "body": "Order details here",
  "labels": ["erp-order"]
}
```

### Sync ERP Order to GitHub Issue
```bash
POST /api/integrations/github/sync/order
{
  "order_number": "ORD-001",
  "customer_name": "John Doe",
  "total_amount": 1000.00,
  "status": "processing",
  "created_at": "2026-03-24",
  "items": [
    {"name": "Laptop", "quantity": 1, "unit_price": 1000.00}
  ]
}
```

This creates a GitHub issue like:
```markdown
## ERP Order Details

| Field | Value |
|-------|-------|
| **Order Number** | `ORD-001` |
| **Customer** | John Doe |
| **Total** | $1000.00 |
| **Status** | processing |

### Items
- **Laptop** × 1 @ $1000.00

---
*Auto-synced from Aureus ERP*
```

### List Releases
```bash
GET /api/integrations/github/releases
GET /api/integrations/github/releases/latest
```

### List Commits
```bash
GET /api/integrations/github/commits
GET /api/integrations/github/commits?branch=main&per_page=10
```

### View GitHub Logs
```bash
GET /api/integrations/github/logs
GET /api/integrations/github/logs?status=failed
```

---

## Webhook Events Handled

| Event | Action |
|-------|--------|
| `push` | Logs commit info (branch, pusher, commit count) |
| `issues` | Tracks issue open/close/label events |
| `pull_request` | Tracks PR open/merge/close events |
| `release` | Logs release tag and name |

---

## Automatic Syncing

When `GITHUB_SYNC_ISSUES=true`, every new ERP order is automatically synced to GitHub as an issue tagged with:
- `erp-order` — marks it as an ERP-synced order
- `status-{status}` — e.g., `status-pending`, `status-completed`

When an order's status changes, the GitHub issue is updated with a comment.

---

## Troubleshooting

### Webhook Signature Fails
- Verify `GITHUB_WEBHOOK_SECRET` matches the secret set in GitHub
- Check the server's clock is accurate (signature includes timestamp)
- Ensure raw body is used for signature verification (not parsed JSON)

### Token Permission Error
```json
{"success": false, "error": "Resource not accessible by integration"}
```
→ Regenerate token with `repo` scope enabled

### Issues Not Creating
```bash
# Check logs
curl "http://localhost:8000/api/integrations/github/logs?status=failed"
```

### Rate Limiting
GitHub allows 5,000 API requests/hour. The integration batches requests.
Monitor usage at: `https://api.github.com/rate_limit`

---

## Security

- Webhook signatures use `sha256` HMAC with your `GITHUB_WEBHOOK_SECRET`
- The ERP verifies every incoming webhook before processing
- Personal Access Tokens should be rotated every 90 days
- Use fine-grained tokens in production for minimal permissions
