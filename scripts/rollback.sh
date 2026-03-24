#!/usr/bin/env bash
# =============================================================
# AureusERP - Rollback Script
# =============================================================
# Usage: bash scripts/rollback.sh [--image-tag=sha-abc123]
# =============================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

log()   { echo -e "${GREEN}[$(date +"%T")] ✓ $1${NC}"; }
info()  { echo -e "${BLUE}[$(date +"%T")] ℹ $1${NC}"; }
warn()  { echo -e "${YELLOW}[$(date +"%T")] ⚠ $1${NC}"; }
error() { echo -e "${RED}[$(date +"%T")] ✗ $1${NC}" >&2; exit 1; }

ROLLBACK_IMAGE_TAG="${ROLLBACK_IMAGE_TAG:-}"

for arg in "$@"; do
    case $arg in
        --image-tag=*) ROLLBACK_IMAGE_TAG="${arg#*=}" ;;
    esac
done

cd "$PROJECT_DIR"

info "══════════════════════════════════════════"
info "  AureusERP Rollback Starting"
info "  Date: $(date)"
info "══════════════════════════════════════════"

# ── Enable maintenance mode ────────────────────────────────
info "Enabling maintenance mode..."
docker compose exec -T app php artisan down || true

# ── Determine image to rollback to ────────────────────────
if [ -z "$ROLLBACK_IMAGE_TAG" ]; then
    info "No image tag specified. Showing available images:"
    docker images ghcr.io/*/aureuserp --format "table {{.Tag}}\t{{.CreatedAt}}\t{{.ID}}" | head -20
    read -r -p "Enter the image tag to rollback to: " ROLLBACK_IMAGE_TAG
fi

# ── Pull and switch to rollback image ─────────────────────
info "Rolling back to image tag: $ROLLBACK_IMAGE_TAG"
export IMAGE_TAG="$ROLLBACK_IMAGE_TAG"

docker compose pull app
docker compose up -d --remove-orphans app

# ── Restore database from backup (optional) ───────────────
if [ -n "${ROLLBACK_DB_BACKUP:-}" ]; then
    warn "Database rollback requested: $ROLLBACK_DB_BACKUP"
    warn "Please restore the database manually from: $ROLLBACK_DB_BACKUP"
fi

# ── Disable maintenance mode ───────────────────────────────
info "Taking application live..."
docker compose exec -T app php artisan up

log "════════════════════════════════════════"
log "Rollback to $ROLLBACK_IMAGE_TAG completed!"
log "════════════════════════════════════════"
