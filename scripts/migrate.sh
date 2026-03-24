#!/usr/bin/env bash
# =============================================================
# AureusERP - Database Migration Script
# =============================================================
# Usage: bash scripts/migrate.sh [--fresh] [--seed]
# =============================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log()   { echo -e "${GREEN}[$(date +"%T")] ✓ $1${NC}"; }
info()  { echo -e "${BLUE}[$(date +"%T")] ℹ $1${NC}"; }
warn()  { echo -e "${YELLOW}[$(date +"%T")] ⚠ $1${NC}"; }

FRESH=false
SEED=false
FORCE=false

for arg in "$@"; do
    case $arg in
        --fresh)  FRESH=true ;;
        --seed)   SEED=true  ;;
        --force)  FORCE=true ;;
    esac
done

cd "$PROJECT_DIR"

info "Running AureusERP database migrations..."

# Wait for database to be ready
MAX_RETRIES=30
RETRY=0
while ! docker compose exec -T db mysqladmin ping -h localhost --silent 2>/dev/null; do
    RETRY=$((RETRY + 1))
    if [ $RETRY -ge $MAX_RETRIES ]; then
        warn "DB not available via docker compose, trying artisan directly..."
        break
    fi
    info "Waiting for database... ($RETRY/$MAX_RETRIES)"
    sleep 2
done

# Run migrations
if [ "$FRESH" = "true" ]; then
    warn "Running FRESH migrations (all data will be lost)!"
    if [ "$FORCE" = "true" ]; then
        docker compose exec -T app php artisan migrate:fresh --force
    else
        docker compose exec -T app php artisan migrate:fresh
    fi
else
    docker compose exec -T app php artisan migrate --force
fi

log "Migrations completed"

# Run seeders if requested
if [ "$SEED" = "true" ]; then
    info "Running database seeders..."
    docker compose exec -T app php artisan db:seed --force
    log "Seeding completed"
fi
