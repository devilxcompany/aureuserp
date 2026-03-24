# 📋 Complete API Documentation

## Base URL
```
http://localhost:8000/api
```

## Authentication

For protected admin endpoints, include the Bearer token:
```
Authorization: Bearer YOUR_TOKEN
```

---

## 🔐 Auth Endpoints

### POST /api/v1/auth/login
```json
{
  "email": "admin@admin.com",
  "password": "admin123456"
}
```

Response:
```json
{"token": "...", "user": {"id": 1, "name": "Admin"}}
```

---

## 🔔 Webhook Endpoints

### POST /api/webhooks/github
Receive GitHub webhook events.

```bash
curl -X POST http://localhost:8000/api/webhooks/github \
  -H "X-GitHub-Event: push" \
  -H "X-Hub-Signature-256: sha256=<sig>" \
  -H "X-GitHub-Delivery: abc-123" \
  -H "Content-Type: application/json" \
  -d '{"ref": "refs/heads/main", "commits": []}'
```

### POST /api/webhooks/pabbly
Receive Pabbly Connect actions.

```bash
curl -X POST http://localhost:8000/api/webhooks/pabbly \
  -H "Content-Type: application/json" \
  -d '{
    "action": "create_order",
    "customer_id": 1,
    "order_number": "ORD-001",
    "total_amount": 500.00,
    "items": [{"product_id": 1, "quantity": 2, "unit_price": 250.00}]
  }'
```

### POST /api/webhooks/bolt-cms/site1
Receive Bolt CMS webhook events.

```bash
curl -X POST http://localhost:8000/api/webhooks/bolt-cms/site1 \
  -H "X-Bolt-Token: your_webhook_token" \
  -H "Content-Type: application/json" \
  -d '{"event": "order.created", "order": {"id": "bolt-123", "total": 500}}'
```

### GET /api/webhooks/events
List recent webhook events.
```
?source=github    Filter by source
?status=failed    Filter by status
?per_page=50      Results per page
```

---

## 🔗 Pabbly Export Endpoints

### GET /api/pabbly/orders/export
Export orders for Pabbly polling.
```
?status=pending   Filter by order status
?since=2026-01-01 Filter by date
?limit=100        Max records
```

### GET /api/pabbly/products/export
Export products for Pabbly polling.

### GET /api/pabbly/customers/export
Export customers for Pabbly polling.

### GET /api/pabbly/invoices/export
Export invoices for Pabbly polling.

---

## 🎛️ Integration Management

### GET /api/integrations/status
Full system status including all integrations.

Response:
```json
{
  "status": "healthy",
  "timestamp": "2026-03-24T15:00:00Z",
  "integrations": {
    "github":   {"enabled": true, "healthy": true, "paused": false},
    "pabbly":   {"enabled": true, "healthy": true, "paused": false},
    "supabase": {"enabled": true, "healthy": true, "paused": false},
    "bolt_cms": {"enabled": true, "healthy": true, "paused": false}
  },
  "queue": {"pending": 0, "processing": 0, "failed": 0},
  "sync": {"last_full_sync": "2026-03-24T14:55:00Z"}
}
```

### GET /api/integrations/health
Quick health check (returns 200 if healthy, 503 if degraded).

### GET /api/integrations/dashboard
Aggregated dashboard stats.

### POST /api/integrations/test
Test connectivity to all services.

### POST /api/integrations/sync
Trigger a full sync.
```json
{"async": true}
```

### POST /api/integrations/sync/{entityType}
Sync a specific entity. Entity types: `order`, `product`, `customer`, `invoice`.

```bash
curl -X POST http://localhost:8000/api/integrations/sync/order \
  -H "Content-Type: application/json" \
  -d '{"id": 1, "order_number": "ORD-001", "status": "processing"}'
```

### POST /api/integrations/event
Manually dispatch an integration event.
```json
{
  "event": "order.created",
  "source": "erp",
  "payload": {"id": 1, "order_number": "ORD-001"}
}
```

### POST /api/integrations/{name}/pause
Pause an integration. Names: `github`, `pabbly`, `supabase`, `bolt_cms`.

### POST /api/integrations/{name}/resume
Resume a paused integration.

### POST /api/integrations/retry
Retry failed jobs.
```json
{"integration": "github"}
```

### GET /api/integrations/logs
Get integration logs.
```
?integration=github     Filter by integration
?status=failed          Filter by status
?event_type=sync_order  Filter by event type
?since=2026-01-01       Filter by date
?per_page=50            Results per page
```

### DELETE /api/integrations/logs
Clear old logs.
```json
{"days_old": 30}
```

### GET /api/integrations/queue
Get queue status and recent jobs.

---

## 🐙 GitHub Endpoints

### GET /api/integrations/github/status
Test GitHub connection and get repo info.

### GET /api/integrations/github/issues
List GitHub issues.
```
?state=open|closed  Filter by state
?labels=erp-order   Filter by label
```

### POST /api/integrations/github/issues
Create a GitHub issue.
```json
{
  "title": "New Order #ORD-001",
  "body": "Order details...",
  "labels": ["erp-order"]
}
```

### POST /api/integrations/github/sync/order
Sync an ERP order to a GitHub issue.
```json
{
  "order_number": "ORD-001",
  "customer_name": "John Doe",
  "total_amount": 1000.00,
  "status": "pending",
  "created_at": "2026-03-24",
  "items": [{"name": "Laptop", "quantity": 1, "unit_price": 1000.00}]
}
```

### GET /api/integrations/github/releases
List releases.

### GET /api/integrations/github/releases/latest
Get the latest release.

### GET /api/integrations/github/commits
List commits.
```
?branch=main    Branch name
?per_page=30    Results per page
```

---

## 📊 Monitoring Endpoints

### GET /api/integrations/monitor/health
Comprehensive health check.

### GET /api/integrations/monitor/ping
Simple ping.

### GET /api/integrations/monitor/metrics
24-hour performance metrics.

### GET /api/integrations/monitor/errors
Recent integration errors.
```
?hours=24     Look-back period
?limit=50     Max results
```

---

## 🔀 Unified API

### GET /api/unified/dashboard
Dashboard data aggregated from all platforms.

### GET /api/unified/orders
Orders from all platforms.
```
?sources=erp,supabase,bolt_cms   Platforms to query
?since=2026-01-01                Filter by date
```

### GET /api/unified/products
Products from all platforms.

### GET /api/unified/customers
Customers from all platforms.

### GET /api/unified/github
GitHub data aggregate.
```
?types=issues,releases,commits   What to fetch
?branch=main                     Branch for commits
```

### GET /api/unified/config
All integration configurations (non-sensitive).

### PUT /api/unified/config/{integration}
Update integration configuration.
```json
{"sync_issues": "true", "webhook_secret": "new_secret"}
```

---

## ⚡ Pabbly Connect Action Reference

Send these payloads to `/api/webhooks/pabbly` or `/api/pabbly/webhook`:

### Create Order
```json
{
  "action": "create_order",
  "customer_id": 1,
  "order_number": "ORD-001",
  "total_amount": 1000.00,
  "status": "pending",
  "items": [
    {"product_id": 1, "quantity": 2, "unit_price": 500.00}
  ]
}
```

### Create Customer
```json
{
  "action": "create_customer",
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "phone": "555-0001",
  "address": "123 Main St"
}
```

### Sync Product
```json
{
  "action": "sync_product",
  "sku": "LAPTOP-001",
  "name": "Gaming Laptop",
  "price": 1299.99,
  "quantity": 25,
  "status": "active"
}
```

### Create Invoice
```json
{
  "action": "create_invoice",
  "order_id": 1,
  "invoice_number": "INV-001",
  "amount": 1000.00,
  "due_date": "2026-04-23"
}
```

### Trigger Full Sync
```json
{
  "action": "sync_all"
}
```

---

## 🧪 cURL Quick Reference

```bash
# Health check
curl http://localhost:8000/api/integrations/monitor/ping

# Test all connections
curl -X POST http://localhost:8000/api/integrations/test

# Trigger full sync
curl -X POST http://localhost:8000/api/integrations/sync

# View system status
curl http://localhost:8000/api/integrations/status

# View queue status
curl http://localhost:8000/api/integrations/queue

# Check GitHub connection
curl http://localhost:8000/api/integrations/github/status

# Retry failed jobs
curl -X POST http://localhost:8000/api/integrations/retry

# View recent errors
curl "http://localhost:8000/api/integrations/monitor/errors?hours=24"

# Unified dashboard
curl http://localhost:8000/api/unified/dashboard
```
