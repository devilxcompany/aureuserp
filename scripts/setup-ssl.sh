#!/usr/bin/env bash
# =============================================================
# AureusERP - SSL Certificate Setup (Let's Encrypt)
# =============================================================
# Usage: bash scripts/setup-ssl.sh --domain=yourapp.com --email=admin@yourapp.com
# =============================================================
set -euo pipefail

DOMAIN="${SSL_DOMAIN:-}"
EMAIL="${SSL_EMAIL:-}"
WEBROOT="/var/www/certbot"
SSL_DIR="./nginx/ssl"

for arg in "$@"; do
    case $arg in
        --domain=*) DOMAIN="${arg#*=}" ;;
        --email=*)  EMAIL="${arg#*=}"  ;;
    esac
done

GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

log()   { echo -e "${GREEN}[$(date +"%T")] ✓ $1${NC}"; }
info()  { echo -e "${BLUE}[$(date +"%T")] ℹ $1${NC}"; }
error() { echo -e "${RED}[$(date +"%T")] ✗ $1${NC}" >&2; exit 1; }

[ -z "$DOMAIN" ] && error "Please provide --domain=yourdomain.com"
[ -z "$EMAIL"  ] && error "Please provide --email=your@email.com"

info "Setting up SSL for: $DOMAIN"

# ── Create SSL directories ─────────────────────────────────
mkdir -p "$SSL_DIR" "$WEBROOT"

# ── Generate self-signed cert for initial nginx start ─────
if [ ! -f "$SSL_DIR/fullchain.pem" ]; then
    info "Generating temporary self-signed certificate..."
    openssl req -x509 -nodes -newkey rsa:4096 \
        -keyout "$SSL_DIR/privkey.pem" \
        -out "$SSL_DIR/fullchain.pem" \
        -days 365 \
        -subj "/CN=$DOMAIN/O=AureusERP/C=US"
    log "Temporary certificate created"
fi

# ── Generate DH parameters ────────────────────────────────
if [ ! -f "$SSL_DIR/dhparam.pem" ]; then
    info "Generating DH parameters (this may take a moment)..."
    openssl dhparam -out "$SSL_DIR/dhparam.pem" 2048
    log "DH parameters generated"
fi

# ── Start nginx with self-signed cert ─────────────────────
info "Starting nginx for ACME challenge..."
docker compose up -d nginx

sleep 3

# ── Run certbot ───────────────────────────────────────────
info "Obtaining Let's Encrypt certificate for $DOMAIN..."
docker run --rm \
    -v "$(pwd)/nginx/ssl:/etc/letsencrypt" \
    -v "$(pwd)/$WEBROOT:/var/www/certbot" \
    certbot/certbot certonly \
    --webroot \
    --webroot-path=/var/www/certbot \
    --email "$EMAIL" \
    --agree-tos \
    --no-eff-email \
    --domain "$DOMAIN"

# Copy issued certificate to the expected location
cp "$(pwd)/nginx/ssl/live/$DOMAIN/fullchain.pem" "$SSL_DIR/fullchain.pem"
cp "$(pwd)/nginx/ssl/live/$DOMAIN/privkey.pem"   "$SSL_DIR/privkey.pem"

# ── Reload nginx ──────────────────────────────────────────
docker compose exec nginx nginx -s reload
log "SSL certificate installed and nginx reloaded!"

log "════════════════════════════════════════"
log "SSL setup complete for: $DOMAIN"
log "Certificate stored at: $SSL_DIR/"
log ""
log "To auto-renew, add this cron job:"
log "  0 3 * * * bash $(pwd)/scripts/renew-ssl.sh"
log "════════════════════════════════════════"
