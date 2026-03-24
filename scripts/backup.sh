#!/usr/bin/env bash
# =============================================================
# AureusERP - Database Backup Script
# =============================================================
# Usage: bash scripts/backup.sh [--env=production] [--dest=s3|local]
# =============================================================
set -euo pipefail

# ── Configuration ─────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/aureuserp}"
DATE=$(date +"%Y%m%d_%H%M%S")
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
APP_ENV="${APP_ENV:-production}"

# ── Colors ────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log()    { echo -e "${GREEN}[$(date +"%T")] ✓ $1${NC}"; }
warn()   { echo -e "${YELLOW}[$(date +"%T")] ⚠ $1${NC}"; }
error()  { echo -e "${RED}[$(date +"%T")] ✗ $1${NC}" >&2; }

# ── Load environment ──────────────────────────────────────
if [ -f "$PROJECT_DIR/.env" ]; then
    set -a
    # shellcheck disable=SC1090
    source <(grep -E '^[A-Z_]+=.*' "$PROJECT_DIR/.env" | sed 's/ *= */=/g')
    set +a
fi

# ── Ensure backup directory exists ────────────────────────
mkdir -p "$BACKUP_DIR"
log "Backup directory: $BACKUP_DIR"

# ── Determine DB connection type ──────────────────────────
DB_CONNECTION="${DB_CONNECTION:-sqlite}"
BACKUP_FILE="$BACKUP_DIR/aureuserp_${APP_ENV}_${DATE}"

backup_mysql() {
    log "Backing up MySQL database: ${DB_DATABASE}"
    if docker compose ps db 2>/dev/null | grep -q "Up"; then
        docker compose exec -T db mysqldump \
            --user="${DB_USERNAME:-aureus}" \
            --password="${DB_PASSWORD}" \
            --single-transaction \
            --routines \
            --triggers \
            --events \
            --hex-blob \
            "${DB_DATABASE:-aureuserp}" > "${BACKUP_FILE}.sql"
    else
        mysqldump \
            --host="${DB_HOST:-127.0.0.1}" \
            --port="${DB_PORT:-3306}" \
            --user="${DB_USERNAME:-aureus}" \
            --password="${DB_PASSWORD}" \
            --single-transaction \
            --routines \
            --triggers \
            --events \
            "${DB_DATABASE:-aureuserp}" > "${BACKUP_FILE}.sql"
    fi
    gzip "${BACKUP_FILE}.sql"
    BACKUP_PATH="${BACKUP_FILE}.sql.gz"
    log "MySQL backup created: $BACKUP_PATH"
}

backup_pgsql() {
    log "Backing up PostgreSQL database: ${DB_DATABASE}"
    PGPASSWORD="${DB_PASSWORD}" pg_dump \
        --host="${DB_HOST:-127.0.0.1}" \
        --port="${DB_PORT:-5432}" \
        --username="${DB_USERNAME:-aureus}" \
        --no-password \
        --format=custom \
        --compress=9 \
        "${DB_DATABASE:-aureuserp}" > "${BACKUP_FILE}.dump"
    BACKUP_PATH="${BACKUP_FILE}.dump"
    log "PostgreSQL backup created: $BACKUP_PATH"
}

backup_sqlite() {
    SQLITE_DB="${PROJECT_DIR}/database/database.sqlite"
    if [ -f "$SQLITE_DB" ]; then
        log "Backing up SQLite database: $SQLITE_DB"
        cp "$SQLITE_DB" "${BACKUP_FILE}.sqlite"
        gzip "${BACKUP_FILE}.sqlite"
        BACKUP_PATH="${BACKUP_FILE}.sqlite.gz"
        log "SQLite backup created: $BACKUP_PATH"
    else
        warn "SQLite database not found at: $SQLITE_DB"
        return 1
    fi
}

# ── Run the right backup ───────────────────────────────────
case "$DB_CONNECTION" in
    mysql|mariadb)  backup_mysql  ;;
    pgsql|postgres) backup_pgsql  ;;
    sqlite)         backup_sqlite ;;
    *)
        error "Unsupported DB_CONNECTION: $DB_CONNECTION"
        exit 1
        ;;
esac

# ── Upload to S3 (optional) ───────────────────────────────
if [ -n "${AWS_S3_BACKUP_BUCKET:-}" ] && command -v aws &>/dev/null; then
    log "Uploading backup to S3: s3://${AWS_S3_BACKUP_BUCKET}/aureuserp/"
    aws s3 cp "$BACKUP_PATH" \
        "s3://${AWS_S3_BACKUP_BUCKET}/aureuserp/$(basename "$BACKUP_PATH")" \
        --storage-class STANDARD_IA \
        --region "${AWS_DEFAULT_REGION:-us-east-1}"
    log "Backup uploaded to S3 successfully"
fi

# ── Clean up old backups ───────────────────────────────────
log "Removing backups older than ${RETENTION_DAYS} days..."
find "$BACKUP_DIR" -name "aureuserp_*" -mtime "+${RETENTION_DAYS}" -delete 2>/dev/null || true

# ── Summary ───────────────────────────────────────────────
BACKUP_SIZE=$(du -sh "$BACKUP_PATH" 2>/dev/null | cut -f1)
log "════════════════════════════════════════"
log "Backup completed successfully!"
log "  File:     $BACKUP_PATH"
log "  Size:     $BACKUP_SIZE"
log "  Date:     $DATE"
log "════════════════════════════════════════"
