# 🔗 Integration Orchestration System — Complete Guide

## Overview

AureusERP Integration Layer connects four platforms in real-time:

```
┌──────────────────────────────────────────────────────────────────┐
│                  AUREUS ERP INTEGRATION LAYER                    │
│                                                                  │
│  GitHub ◄────────────────────────────────────────► Pabbly        │
│    │         Master Integration Service             │            │
│    │         Event Dispatcher                       │            │
│    │         Data Sync Service                      │            │
│    │         Webhook Router                         │            │
│    │         Integration Queue                      │            │
│    └─────────────────────┬──────────────────────────┘            │
│                          │                                       │
│               ┌──────────▼──────────┐                            │
│               │     Aureus ERP      │                            │
│               │  (Laravel Backend)  │                            │
│               └──────────┬──────────┘                            │
│                          │                                       │
│          ┌───────────────┴───────────────┐                       │
│          ▼                               ▼                       │
│      Supabase                       Bolt CMS                     │
│   (PostgreSQL DB)               (2 Websites)                     │
└──────────────────────────────────────────────────────────────────┘
```

---

## 🚀 Quick Setup

### 1. Configure Environment Variables

Copy the variables from `.env` and fill in your credentials:

```env
# GitHub
GITHUB_TOKEN=ghp_xxxxxxxxxxxx
GITHUB_OWNER=devilxcompany
GITHUB_REPO=aureuserp
GITHUB_WEBHOOK_SECRET=your_secret

# Pabbly
PABBLY_WEBHOOK_URL=https://your-domain.com/api/pabbly/webhook

# Supabase
SUPABASE_URL=https://xxxx.supabase.co
SUPABASE_KEY=eyJ...
SUPABASE_SERVICE_KEY=eyJ...

# Bolt CMS
BOLT_CMS_SITE1_URL=https://site1.com
BOLT_CMS_SITE1_API_KEY=your_key
BOLT_CMS_SITE2_URL=https://site2.com
BOLT_CMS_SITE2_API_KEY=your_key
```

### 2. Run Migrations

```bash
php artisan migrate
```

### 3. Test All Connections

```bash
curl -X POST http://localhost:8000/api/integrations/test
```

---

## 📡 Complete API Endpoint Reference

### Base URL
```
http://localhost:8000/api
```

---

### 🔔 Webhook Endpoints (Public)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/webhooks/github` | Receive GitHub events |
| `POST` | `/webhooks/pabbly` | Receive Pabbly workflows |
| `POST` | `/webhooks/bolt-cms/site1` | Receive Bolt CMS Site 1 events |
| `POST` | `/webhooks/bolt-cms/site2` | Receive Bolt CMS Site 2 events |
| `POST` | `/webhooks/supabase` | Receive Supabase DB events |
| `GET`  | `/webhooks/events` | List recent webhook events |
| `POST` | `/webhooks/events/{id}/retry` | Retry a failed webhook event |

---

### 🔗 Pabbly Data Export Endpoints (Public)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/pabbly/webhook` | Receive action from Pabbly |
| `GET`  | `/pabbly/orders/export` | Export orders to Pabbly |
| `GET`  | `/pabbly/products/export` | Export products to Pabbly |
| `GET`  | `/pabbly/customers/export` | Export customers to Pabbly |
| `GET`  | `/pabbly/invoices/export` | Export invoices to Pabbly |

---

### 🎛️ Master Integration Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET`  | `/integrations/status` | Full system status |
| `GET`  | `/integrations/health` | Quick health check |
| `GET`  | `/integrations/dashboard` | Aggregated dashboard |
| `POST` | `/integrations/test` | Test all connections |
| `POST` | `/integrations/sync` | Trigger full sync |
| `POST` | `/integrations/sync/{entity}` | Sync specific entity |
| `POST` | `/integrations/event` | Dispatch integration event |
| `POST` | `/integrations/{name}/pause` | Pause integration |
| `POST` | `/integrations/{name}/resume` | Resume integration |
| `POST` | `/integrations/retry` | Retry failed jobs |
| `GET`  | `/integrations/logs` | Get integration logs |
| `DELETE`| `/integrations/logs` | Clear old logs |
| `GET`  | `/integrations/queue` | Queue status |
| `POST` | `/integrations/queue/{id}/cancel` | Cancel a queue job |

---

### 🐙 GitHub Integration Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET`  | `/integrations/github/status` | GitHub connection status |
| `POST` | `/integrations/github/webhook` | Receive GitHub webhook |
| `GET`  | `/integrations/github/issues` | List GitHub issues |
| `POST` | `/integrations/github/issues` | Create GitHub issue |
| `POST` | `/integrations/github/sync/order` | Sync order → GitHub issue |
| `GET`  | `/integrations/github/releases` | List releases |
| `GET`  | `/integrations/github/releases/latest` | Latest release |
| `GET`  | `/integrations/github/commits` | List commits |
| `GET`  | `/integrations/github/logs` | GitHub integration logs |

---

### 📊 Monitoring Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET`  | `/integrations/monitor/health` | Full health check |
| `GET`  | `/integrations/monitor/ping` | Simple ping |
| `GET`  | `/integrations/monitor/metrics` | Performance metrics (24h) |
| `GET`  | `/integrations/monitor/errors` | Recent errors |

---

### 🔀 Unified API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET`  | `/unified/dashboard` | Aggregated dashboard |
| `GET`  | `/unified/orders` | Orders from all platforms |
| `GET`  | `/unified/products` | Products from all platforms |
| `GET`  | `/unified/customers` | Customers from all platforms |
| `GET`  | `/unified/github` | GitHub data aggregate |
| `GET`  | `/unified/config` | All integration configs |
| `PUT`  | `/unified/config/{integration}` | Update integration config |

---

## 🔄 Data Flow Diagrams

### Order Flow (Bolt CMS → ERP → Supabase → GitHub)
```
1. Customer places order on Bolt CMS
        ↓
2. Bolt CMS fires webhook → /api/webhooks/bolt-cms/site1
        ↓
3. WebhookRouterController routes to BoltCmsService
        ↓
4. EventDispatcherService fires "order.created" event
        ↓
5. Handlers run in parallel:
   ├── SupabaseService.syncOrder()    → Supabase
   ├── GitHubService.syncOrderToIssue() → GitHub Issue
   └── PabblyService.notifyOrderCreated() → Pabbly workflow
        ↓
6. IntegrationLog records all events
        ↓
7. IntegrationQueue schedules follow-up jobs if needed
```

### Pabbly Workflow Trigger
```
1. Pabbly workflow executes
        ↓
2. Pabbly sends POST to /api/webhooks/pabbly
        ↓
3. WebhookRouterController routes to PabblyService
        ↓
4. PabblyService.processIncomingWebhook() handles action:
   - create_order    → Creates order in ERP
   - create_customer → Creates customer in ERP
   - sync_product    → Updates product inventory
   - create_invoice  → Generates invoice
        ↓
5. EventDispatcherService fires appropriate events
        ↓
6. Cross-platform sync runs automatically
```

### GitHub Webhook Flow
```
1. Push/PR/Issue/Release event on GitHub
        ↓
2. GitHub sends POST to /api/webhooks/github
        ↓
3. Signature verified (X-Hub-Signature-256)
        ↓
4. WebhookRouterController routes to GitHubService
        ↓
5. GitHubService.processWebhookEvent() handles:
   - push         → Logs commit activity
   - issues       → Syncs issue state
   - pull_request → Tracks PR lifecycle
   - release      → Triggers version update
        ↓
6. WebhookEvent recorded for audit trail
```

---

## ⚙️ Configuration Reference

### Pause/Resume an Integration

```bash
# Pause GitHub integration
curl -X POST http://localhost:8000/api/integrations/github/pause

# Resume GitHub integration
curl -X POST http://localhost:8000/api/integrations/github/resume
```

### Manually Trigger a Full Sync

```bash
# Async (default) - queues the job
curl -X POST http://localhost:8000/api/integrations/sync

# Synchronous - runs immediately
curl -X POST http://localhost:8000/api/integrations/sync \
  -H "Content-Type: application/json" \
  -d '{"async": false}'
```

### Sync a Specific Order to All Platforms

```bash
curl -X POST http://localhost:8000/api/integrations/sync/order \
  -H "Content-Type: application/json" \
  -d '{
    "id": 1,
    "order_number": "ORD-001",
    "customer_name": "John Doe",
    "total_amount": 1000.00,
    "status": "processing",
    "items": [
      {"name": "Laptop", "quantity": 1, "unit_price": 1000.00}
    ]
  }'
```

### Create a GitHub Issue from an ERP Order

```bash
curl -X POST http://localhost:8000/api/integrations/github/sync/order \
  -H "Content-Type: application/json" \
  -d '{
    "order_number": "ORD-001",
    "customer_name": "John Doe",
    "total_amount": 1000.00,
    "status": "pending",
    "created_at": "2026-03-24",
    "items": []
  }'
```

### Manually Dispatch an Integration Event

```bash
curl -X POST http://localhost:8000/api/integrations/event \
  -H "Content-Type: application/json" \
  -d '{
    "event": "order.created",
    "source": "erp",
    "payload": {
      "id": 1,
      "order_number": "ORD-001",
      "total_amount": 500.00
    }
  }'
```

---

## 🐞 Troubleshooting

### Check System Health
```bash
curl http://localhost:8000/api/integrations/monitor/health
```

### View Recent Errors
```bash
curl "http://localhost:8000/api/integrations/monitor/errors?hours=24"
```

### View Integration Logs
```bash
# All logs
curl http://localhost:8000/api/integrations/logs

# Filter by integration and status
curl "http://localhost:8000/api/integrations/logs?integration=github&status=failed"
```

### Retry Failed Jobs
```bash
# Retry all failed jobs
curl -X POST http://localhost:8000/api/integrations/retry

# Retry only GitHub jobs
curl -X POST http://localhost:8000/api/integrations/retry \
  -H "Content-Type: application/json" \
  -d '{"integration": "github"}'
```

### Check Queue Status
```bash
curl http://localhost:8000/api/integrations/queue
```

### Test All Connections
```bash
curl -X POST http://localhost:8000/api/integrations/test
```

---

## 📦 Database Tables

| Table | Purpose |
|-------|---------|
| `integration_logs` | Audit trail of all integration events |
| `webhook_events` | Raw incoming webhooks from all sources |
| `integration_queue` | Async job queue with retry logic |
| `integration_configs` | Runtime configuration store |
| `sync_records` | Track sync state per entity/platform |

---

## 🔐 Security Notes

1. **GitHub Webhooks**: Always set `GITHUB_WEBHOOK_SECRET` and the system verifies `X-Hub-Signature-256`.
2. **Bolt CMS Webhooks**: Set `BOLT_CMS_WEBHOOK_TOKEN`; the system verifies `X-Bolt-Token` header.
3. **Pabbly**: Use HTTPS for all webhook URLs. Optionally verify `PABBLY_API_KEY`.
4. **Admin Endpoints**: In production, protect `/api/integrations/*` with authentication middleware.
5. **Supabase**: Use the service role key only server-side; never expose it to the frontend.

---

## 📈 Performance Tips

- Set `SYNC_BATCH_SIZE=100` to process records in batches.
- Use `async: true` for sync triggers to avoid blocking HTTP responses.
- Enable `SYNC_DUPLICATE_PREVENTION=true` to avoid reprocessing records.
- Run `php artisan queue:work --queue=integrations` to process async jobs.
- Schedule `RetryFailedIntegrationJob` every 15 minutes to auto-retry failures.

---

## 🗓️ Scheduled Tasks (add to Kernel.php)

```php
// Run full sync every 5 minutes
$schedule->call(function () {
    app(\App\Services\Integration\DataSyncService::class)->fullSync();
})->everyFiveMinutes();

// Auto-retry failed jobs every 15 minutes
$schedule->job(new \App\Jobs\RetryFailedIntegrationJob())->everyFifteenMinutes();

// Clean up old logs daily
$schedule->call(function () {
    app(\App\Services\Integration\MasterIntegrationService::class)->clearOldLogs(30);
})->daily();
```
