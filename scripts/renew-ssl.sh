#!/usr/bin/env bash
# =============================================================
# AureusERP - SSL Certificate Auto-Renewal
# =============================================================
# Add to crontab: 0 3 * * * bash /var/www/aureuserp/scripts/renew-ssl.sh
# =============================================================
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

echo "[$(date)] Starting SSL certificate renewal check..."

docker run --rm \
    -v "$(pwd)/nginx/ssl:/etc/letsencrypt" \
    -v "$(pwd)/nginx/certbot:/var/www/certbot" \
    certbot/certbot renew --quiet --no-self-upgrade

docker compose exec nginx nginx -s reload
echo "[$(date)] SSL renewal check complete"
