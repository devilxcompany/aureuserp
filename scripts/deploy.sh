#!/usr/bin/env bash
# =============================================================
# AureusERP - Production Deploy Script
# =============================================================
# Usage: bash scripts/deploy.sh [--skip-backup] [--skip-maintenance]
# =============================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log()    { echo -e "${GREEN}[$(date +"%T")] ✓ $1${NC}"; }
info()   { echo -e "${BLUE}[$(date +"%T")] ℹ $1${NC}"; }
warn()   { echo -e "${YELLOW}[$(date +"%T")] ⚠ $1${NC}"; }
error()  { echo -e "${RED}[$(date +"%T")] ✗ $1${NC}" >&2; exit 1; }

SKIP_BACKUP="${SKIP_BACKUP:-false}"
SKIP_MAINTENANCE="${SKIP_MAINTENANCE:-false}"

# Parse arguments
for arg in "$@"; do
    case $arg in
        --skip-backup)        SKIP_BACKUP=true ;;
        --skip-maintenance)   SKIP_MAINTENANCE=true ;;
    esac
done

cd "$PROJECT_DIR"

info "══════════════════════════════════════════"
info "  AureusERP Deployment Starting"
info "  Date: $(date)"
info "══════════════════════════════════════════"

# ── Step 1: Pre-deployment backup ─────────────────────────
if [ "$SKIP_BACKUP" = "false" ]; then
    info "Step 1/8: Creating pre-deployment backup..."
    bash scripts/backup.sh || warn "Backup failed, continuing..."
else
    warn "Step 1/8: Backup skipped"
fi

# ── Step 2: Enable maintenance mode ───────────────────────
if [ "$SKIP_MAINTENANCE" = "false" ]; then
    info "Step 2/8: Enabling maintenance mode..."
    docker compose exec -T app php artisan down --secret="${MAINTENANCE_SECRET:?MAINTENANCE_SECRET must be set in .env}" || true
else
    warn "Step 2/8: Maintenance mode skipped"
fi

# ── Step 3: Pull latest images ────────────────────────────
info "Step 3/8: Pulling latest Docker images..."
docker compose pull

# ── Step 4: Run database migrations ───────────────────────
info "Step 4/8: Running database migrations..."
docker compose run --rm app php artisan migrate --force

# ── Step 5: Start new containers ──────────────────────────
info "Step 5/8: Starting application containers..."
docker compose up -d --remove-orphans

# ── Step 6: Cache optimization ────────────────────────────
info "Step 6/8: Optimizing application cache..."
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache
docker compose exec -T app php artisan event:cache

# ── Step 7: Storage symlink ───────────────────────────────
info "Step 7/8: Setting up storage symlink..."
docker compose exec -T app php artisan storage:link || true

# ── Step 8: Disable maintenance mode ──────────────────────
if [ "$SKIP_MAINTENANCE" = "false" ]; then
    info "Step 8/8: Taking application live..."
    docker compose exec -T app php artisan up
else
    info "Step 8/8: Deployment complete"
fi

# ── Clean up ──────────────────────────────────────────────
docker system prune -f

# ── Health check ──────────────────────────────────────────
sleep 5
APP_URL="${APP_URL:-http://localhost}"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "${APP_URL}/health" || echo "000")
if [ "$STATUS" = "200" ]; then
    log "════════════════════════════════════════"
    log "Deployment completed successfully! ✅"
    log "URL: $APP_URL"
    log "════════════════════════════════════════"
else
    warn "Health check returned HTTP $STATUS — please verify the application manually"
fi
