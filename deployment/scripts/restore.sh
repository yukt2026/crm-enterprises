#!/usr/bin/env bash
# ==============================================================================
# CRM Enterprises - Production Database and Storage Restore Script
# Usage: ./restore.sh /path/to/backup/YYYYMMDD_HHMMSS [--force]
# ==============================================================================

set -euo pipefail

if [ $# -lt 1 ]; then
    echo "Usage: $0 <backup_directory_path> [--force]"
    echo "Example: $0 /var/backups/crm-enterprises/20261123_101500"
    exit 1
fi

BACKUP_DIR="$1"
FORCE_FLAG="${2:-}"
APP_DIR="/var/www/crm-enterprises"

if [ ! -d "${BACKUP_DIR}" ]; then
    echo "[ERROR] Backup directory '${BACKUP_DIR}' does not exist!"
    exit 1
fi

echo "=========================================================="
echo "CRM Enterprises - Disaster Recovery / Restore Tool"
echo "Target Backup: ${BACKUP_DIR}"
echo "=========================================================="

# 1. Verify Checksums
echo "[1/5] Verifying SHA256 checksums..."
if [ -f "${BACKUP_DIR}/SHA256SUMS" ]; then
    cd "${BACKUP_DIR}"
    sha256sum -c "SHA256SUMS"
    echo "  Checksums verified successfully."
else
    echo "  [WARN] No SHA256SUMS file found. Proceeding with caution."
fi

if [[ "${FORCE_FLAG}" != "--force" ]]; then
    read -p "WARNING: This will OVERWRITE current database and storage files. Continue? (y/N): " confirm
    if [[ "${confirm}" != "y" && "${confirm}" != "Y" ]]; then
        echo "Restore aborted by operator."
        exit 0
    fi
fi

# Load environment
if [ -f "${APP_DIR}/backend/.env" ]; then
    export $(grep -v '^#' "${APP_DIR}/backend/.env" | xargs)
fi

DB_DATABASE="${DB_DATABASE:-crm_enterprises}"
DB_USERNAME="${DB_USERNAME:-root}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

# 2. Put application into maintenance mode
echo "[2/5] Entering maintenance mode..."
cd "${APP_DIR}/backend"
php artisan down --render="errors::503" --secret="crm-restore-bypass-key" || true

# 3. Restore Database
echo "[3/5] Restoring database to '${DB_DATABASE}'..."
DB_FILE=$(find "${BACKUP_DIR}" -name "database_*.sql.gz" | head -n 1)
if [ -f "${DB_FILE}" ]; then
    gunzip -c "${DB_FILE}" | MYSQL_PWD="${DB_PASSWORD}" mysql \
        --host="${DB_HOST}" \
        --port="${DB_PORT}" \
        --user="${DB_USERNAME}" \
        --default-character-set=utf8mb4 \
        "${DB_DATABASE}"
    echo "  Database restored successfully."
else
    echo "  [ERROR] No database .sql.gz file found in ${BACKUP_DIR}!"
    exit 1
fi

# 4. Restore Storage Archive
echo "[4/5] Restoring storage files & media..."
STORAGE_FILE=$(find "${BACKUP_DIR}" -name "storage_*.tar.gz" | head -n 1)
if [ -f "${STORAGE_FILE}" ]; then
    tar -xzf "${STORAGE_FILE}" -C "${APP_DIR}/backend/storage/app"
    chown -R www-data:www-data "${APP_DIR}/backend/storage" "${APP_DIR}/backend/bootstrap/cache"
    chmod -R 775 "${APP_DIR}/backend/storage" "${APP_DIR}/backend/bootstrap/cache"
    echo "  Storage files restored and permissions fixed."
else
    echo "  [WARN] No storage .tar.gz file found."
fi

# 5. Run Migrations & Cache Optimization
echo "[5/5] Rebuilding application caches..."
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Bring application back up
php artisan up

echo "=========================================================="
echo "Restore process completed successfully at $(date)!"
echo "=========================================================="
