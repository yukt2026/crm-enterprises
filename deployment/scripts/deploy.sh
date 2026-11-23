#!/usr/bin/env bash
# ==============================================================================
# CRM Enterprises - Production Zero-Downtime VPS Deployment Script
# Usage: ./deploy.sh [branch_name]
# ==============================================================================

set -euo pipefail

BRANCH="${1:-main}"
APP_DIR="/var/www/crm-enterprises"
PHP_FPM_SERVICE="php8.3-fpm"

echo "=========================================================="
echo "Starting CRM Enterprises Production Deployment [Branch: ${BRANCH}]"
echo "Timestamp: $(date +"%Y-%m-%d %H:%M:%S")"
echo "=========================================================="

cd "${APP_DIR}"

# 1. Enable Maintenance Mode with Secret Bypass
echo "[1/8] Entering maintenance mode..."
cd "${APP_DIR}/backend"
php artisan down --render="errors::503" --secret="crm-deploy-bypass-key" || true

# 2. Fetch Latest Source
echo "[2/8] Pulling latest changes from git repository..."
cd "${APP_DIR}"
git fetch origin "${BRANCH}"
git checkout "${BRANCH}"
git pull origin "${BRANCH}"

# 3. Backend PHP Dependencies
echo "[3/8] Installing production Composer dependencies..."
cd "${APP_DIR}/backend"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

# 4. Frontend Asset Compilation
echo "[4/8] Building frontend SPA & PWA assets..."
cd "${APP_DIR}/frontend"
npm ci --prefer-offline
npm run build

# 5. Database Migrations
echo "[5/8] Running database migrations..."
cd "${APP_DIR}/backend"
php artisan migrate --force

# 6. Production Cache Rebuilding & Optimization
echo "[6/8] Clearing and warming production caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 7. Restart Background Daemons & Services
echo "[7/8] Restarting queue workers, scheduler and Reverb websockets..."
sudo supervisorctl restart crm-queue-worker:*
sudo supervisorctl restart crm-scheduler
sudo supervisorctl restart crm-reverb-websockets
sudo systemctl reload "${PHP_FPM_SERVICE}"

# 8. Restore Application Online
echo "[8/8] Disabling maintenance mode..."
php artisan up

echo "=========================================================="
echo "Deployment completed successfully at $(date +"%Y-%m-%d %H:%M:%S")!"
echo "=========================================================="
