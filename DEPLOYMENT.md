# AureusERP Deployment Guide

Complete guide for deploying AureusERP with Docker, GitHub Actions CI/CD, and production infrastructure.

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Quick Start (Docker)](#quick-start-docker)
3. [GitHub Actions CI/CD](#github-actions-cicd)
4. [Production Setup](#production-setup)
5. [SSL/TLS Configuration](#ssltls-configuration)
6. [Database Management](#database-management)
7. [Backup System](#backup-system)
8. [Monitoring](#monitoring)
9. [Scaling & Load Balancing](#scaling--load-balancing)
10. [Platform Guides](#platform-guides)
11. [Rollback](#rollback)
12. [Troubleshooting](#troubleshooting)

---

## Architecture Overview

```
Internet
    │
    ▼
┌───────────────┐
│  Nginx        │  Port 80/443 (HTTPS)
│  (Reverse     │  Rate limiting, SSL termination, static files
│   Proxy)      │
└───────┬───────┘
        │ FastCGI (port 9000)
        ▼
┌───────────────┐    ┌───────────────┐    ┌───────────────┐
│  PHP-FPM App  │    │  Queue Worker │    │   Scheduler   │
│  (Laravel 11) │    │  (Async jobs) │    │   (Cron)      │
└───────┬───────┘    └───────┬───────┘    └───────┬───────┘
        │                    │                    │
        └────────────────────┼────────────────────┘
                             │
               ┌─────────────┼─────────────┐
               ▼             ▼             ▼
        ┌──────────┐  ┌──────────┐  ┌──────────┐
        │  MySQL   │  │  Redis   │  │  Storage │
        │  (DB)    │  │  (Cache) │  │  (Files) │
        └──────────┘  └──────────┘  └──────────┘
```

---

## Quick Start (Docker)

### Prerequisites
- Docker 24.0+
- Docker Compose v2.20+
- Git

### 1. Clone and Configure

```bash
git clone https://github.com/devilxcompany/aureuserp.git
cd aureuserp

# Copy environment file
cp .env.production .env

# Edit with your values
nano .env
```

### 2. Required Environment Variables

Edit `.env` and set:

```bash
APP_KEY=                    # Run: openssl rand -base64 32
APP_URL=https://yourdomain.com
DB_PASSWORD=your_secure_password
DB_ROOT_PASSWORD=your_root_password
REDIS_PASSWORD=your_redis_password
```

### 3. Start All Services

```bash
# Start all services
docker compose up -d

# Check status
docker compose ps

# View logs
docker compose logs -f app
```

### 4. Initialize Application

```bash
# Generate application key
docker compose exec app php artisan key:generate

# Run migrations
docker compose exec app php artisan migrate --force

# Seed initial data (optional)
docker compose exec app php artisan db:seed --force

# Create storage symlink
docker compose exec app php artisan storage:link
```

### 5. Access the Application

- **Application**: http://localhost (or your domain)
- **Admin Panel**: http://localhost/admin/login
- **Default Admin**: admin@admin.com / admin123456
- **Health Check**: http://localhost/health

---

## GitHub Actions CI/CD

### Repository Secrets Setup

Go to **GitHub → Repository → Settings → Secrets and variables → Actions** and add:

#### Required Secrets

| Secret | Description |
|--------|-------------|
| `STAGING_HOST` | Staging server IP/hostname |
| `STAGING_USER` | SSH username for staging |
| `STAGING_SSH_KEY` | Private SSH key for staging |
| `PRODUCTION_HOST` | Production server IP/hostname |
| `PRODUCTION_USER` | SSH username for production |
| `PRODUCTION_SSH_KEY` | Private SSH key for production |

#### Optional Secrets

| Secret | Description |
|--------|-------------|
| `SLACK_WEBHOOK_URL` | Slack notifications |
| `CODECOV_TOKEN` | Code coverage reporting |

### Repository Variables

Go to **GitHub → Repository → Settings → Variables** and add:

| Variable | Example |
|----------|---------|
| `STAGING_URL` | `https://staging.yourdomain.com` |
| `PRODUCTION_URL` | `https://yourdomain.com` |
| `DEPLOY_PATH` | `/var/www/aureuserp` |

### Workflow Overview

```
Push to develop/staging  →  lint → test → security → build → deploy-staging
Push to main             →  lint → test → security → build → deploy-production
Manual trigger           →  Choose environment
```

### Workflow Files

| File | Purpose |
|------|---------|
| `.github/workflows/ci-cd.yml` | Main CI/CD pipeline |
| `.github/workflows/backup.yml` | Scheduled database backups |
| `.github/workflows/health-monitor.yml` | Periodic health monitoring |

### Setting Up SSH Deployment

On your server:

```bash
# Create deploy user
useradd -m -s /bin/bash deploy
usermod -aG docker deploy

# Create SSH key pair (on your local machine)
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/aureuserp_deploy

# Add public key to server
ssh-copy-id -i ~/.ssh/aureuserp_deploy.pub deploy@your-server.com

# Add private key to GitHub Secrets as PRODUCTION_SSH_KEY
cat ~/.ssh/aureuserp_deploy
```

---

## Production Setup

### Server Requirements

- **OS**: Ubuntu 22.04 LTS (recommended)
- **RAM**: 2GB minimum, 4GB recommended
- **CPU**: 2 cores minimum
- **Storage**: 20GB minimum
- **Ports**: 80, 443 open

### Initial Server Setup

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER

# Install Docker Compose
sudo apt install docker-compose-plugin -y

# Create application directory
sudo mkdir -p /var/www/aureuserp
sudo chown $USER:$USER /var/www/aureuserp

# Clone repository
git clone https://github.com/devilxcompany/aureuserp.git /var/www/aureuserp
cd /var/www/aureuserp

# Setup environment
cp .env.production .env
nano .env  # Fill in your values

# Deploy
bash scripts/deploy.sh
```

---

## SSL/TLS Configuration

### Option A: Let's Encrypt (Recommended)

```bash
# Setup SSL (replace with your domain and email)
bash scripts/setup-ssl.sh \
  --domain=yourdomain.com \
  --email=admin@yourdomain.com
```

This script:
1. Generates a temporary self-signed certificate
2. Starts Nginx with the temporary cert
3. Obtains a real Let's Encrypt certificate
4. Reloads Nginx with the real certificate

### Auto-Renewal

```bash
# Add to crontab (runs at 3am daily)
echo "0 3 * * * bash /var/www/aureuserp/scripts/renew-ssl.sh >> /var/log/ssl-renew.log 2>&1" | crontab -
```

### Option B: Self-Signed Certificate (Development)

```bash
mkdir -p nginx/ssl
openssl req -x509 -nodes -newkey rsa:4096 \
  -keyout nginx/ssl/privkey.pem \
  -out nginx/ssl/fullchain.pem \
  -days 365 \
  -subj "/CN=localhost"
openssl dhparam -out nginx/ssl/dhparam.pem 2048
```

---

## Database Management

### Migrations

```bash
# Run all pending migrations
bash scripts/migrate.sh

# Fresh migration (deletes all data!)
bash scripts/migrate.sh --fresh --seed

# Via Docker directly
docker compose exec app php artisan migrate --force
```

### Database Access

```bash
# MySQL shell
docker compose exec db mysql -u aureus -p aureuserp

# MySQL via docker compose
docker compose exec db mysqladmin -u root -p status
```

---

## Backup System

### Manual Backup

```bash
# Backup current database
bash scripts/backup.sh

# Backups are stored in /var/backups/aureuserp/
ls -la /var/backups/aureuserp/
```

### Automated Backups (Cron)

```bash
# Add to crontab for daily backups at 2am
echo "0 2 * * * bash /var/www/aureuserp/scripts/backup.sh >> /var/log/aureuserp-backup.log 2>&1" | crontab -
```

### S3 Backup Upload

Set in `.env`:
```bash
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=us-east-1
AWS_S3_BACKUP_BUCKET=your-backup-bucket
```

Backups will automatically be uploaded to S3 after local backup.

### Restore from Backup

```bash
# MySQL restore
gunzip < /var/backups/aureuserp/aureuserp_production_20250324_020000.sql.gz | \
  docker compose exec -T db mysql -u aureus -p aureuserp

# SQLite restore
gunzip < /var/backups/aureuserp/aureuserp_production_20250324_020000.sqlite.gz > \
  database/database.sqlite
```

---

## Monitoring

### Health Checks

| Endpoint | Description |
|----------|-------------|
| `GET /health` | Basic health check (HTTP 200 = ok) |
| `GET /health/detailed` | Full service status (DB, cache, storage) |

Example response:
```json
{
  "status": "ok",
  "timestamp": "2025-03-24T16:00:00Z",
  "app": "AureusERP",
  "checks": {
    "database": { "status": "ok", "latency": "2.3ms" },
    "cache":    { "status": "ok", "driver": "redis" },
    "storage":  { "status": "ok" },
    "memory":   { "usage_mb": 48.5, "limit": "256M" }
  }
}
```

### Start Monitoring Stack (Prometheus + Grafana)

```bash
docker compose -f docker-compose.yml -f docker-compose.monitoring.yml up -d

# Access:
# Prometheus: http://localhost:9090
# Grafana:    http://localhost:3000 (admin / your_password)
```

### GitHub Actions Health Monitor

The `health-monitor.yml` workflow runs every 15 minutes and sends a Slack alert if the application is down.

---

## Scaling & Load Balancing

### Scale Application Instances

```bash
# Run 3 PHP-FPM instances behind load balancer
docker compose -f docker-compose.yml -f docker-compose.scale.yml up -d --scale app=3

# Scale queue workers
docker compose -f docker-compose.yml -f docker-compose.scale.yml up -d --scale queue=2
```

The `docker-compose.scale.yml` configures Nginx to load balance across all instances using the `least_conn` strategy.

---

## Platform Guides

### Deploy to AWS EC2

```bash
# Launch EC2 instance (Ubuntu 22.04, t3.small or larger)
# Open ports 22, 80, 443 in security group

# Connect and setup
ssh ubuntu@your-ec2-ip
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker ubuntu

# Clone and deploy
git clone https://github.com/devilxcompany/aureuserp.git
cd aureuserp
cp .env.production .env && nano .env
bash scripts/deploy.sh
```

### Deploy to DigitalOcean Droplet

```bash
# Create Droplet (Ubuntu 22.04, 2GB RAM)
# Enable SSH key

ssh root@your-droplet-ip
curl -fsSL https://get.docker.com | sh

git clone https://github.com/devilxcompany/aureuserp.git /var/www/aureuserp
cd /var/www/aureuserp
cp .env.production .env && nano .env
bash scripts/deploy.sh
```

### Deploy to Heroku (using Docker)

```bash
# Login to Heroku
heroku login
heroku container:login

# Create app
heroku create aureuserp

# Add addons
heroku addons:create heroku-postgresql:mini
heroku addons:create heroku-redis:mini

# Push and release
heroku container:push web --app aureuserp
heroku container:release web --app aureuserp

# Run migrations
heroku run php artisan migrate --force --app aureuserp
```

---

## Rollback

### Rollback via Script

```bash
# List available images
docker images ghcr.io/devilxcompany/aureuserp

# Rollback to specific version
ROLLBACK_IMAGE_TAG=sha-abc123 bash scripts/rollback.sh

# Or with argument
bash scripts/rollback.sh --image-tag=sha-abc123
```

### Rollback via GitHub Actions

1. Go to **GitHub → Actions → AureusERP CI/CD Pipeline**
2. Click **Run workflow**
3. Select environment: `rollback`
4. Click **Run workflow**

---

## Troubleshooting

### Common Issues

#### Application not starting
```bash
# Check logs
docker compose logs app
docker compose logs nginx

# Check container status
docker compose ps
```

#### Database connection issues
```bash
# Check DB is running
docker compose exec db mysqladmin -h localhost -u root -p ping

# Test connection from app
docker compose exec app php artisan db:show
```

#### Permission errors
```bash
# Fix storage permissions
docker compose exec app chmod -R 775 storage bootstrap/cache
docker compose exec app chown -R www:www storage bootstrap/cache
```

#### Nginx 502 Bad Gateway
```bash
# Check PHP-FPM is running
docker compose ps app
docker compose logs app | tail -50

# Restart services
docker compose restart app nginx
```

### Useful Commands

```bash
# View all logs in real time
docker compose logs -f

# Execute artisan commands
docker compose exec app php artisan list
docker compose exec app php artisan tinker

# Clear all caches
docker compose exec app php artisan optimize:clear

# Queue management
docker compose exec app php artisan queue:monitor

# Check application status
curl -s http://localhost/health | python3 -m json.tool
```
