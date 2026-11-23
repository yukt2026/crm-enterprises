#!/usr/bin/env bash
# ==============================================================================
# CRM Enterprises - Production Automated Backup Script
# Usage: ./backup.sh [--upload-s3]
# Recommended cron: 0 2 * * * /var/www/crm-enterprises/deployment/scripts/backup.sh --upload-s3 >> /var/log/crm-backup.log 2>&1
# ==============================================================================

set -euo pipefail

# Configuration
APP_DIR="/var/www/crm-enterprises"
BACKUP_ROOT="/var/backups/crm-enterprises"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_DIR="${BACKUP_ROOT}/${TIMESTAMP}"
RETENTION_DAYS=14
S3_BUCKET="${BACKUP_S3_BUCKET:-s3://crm-enterprise-backups-vault/daily}"

# Load environment variables
if [ -f "${APP_DIR}/backend/.env" ]; then
    export $(grep -v '^#' "${APP_DIR}/backend/.env" | xargs)
fi

DB_DATABASE="${DB_DATABASE:-crm_enterprises}"
DB_USERNAME="${DB_USERNAME:-root}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

echo "=========================================================="
echo "Starting CRM Enterprise Backup: ${TIMESTAMP}"
echo "=========================================================="

mkdir -p "${BACKUP_DIR}"

# 1. Database Dump with single transaction to ensure consistency
echo "[1/4] Dumping MySQL database: ${DB_DATABASE}..."
MYSQL_PWD="${DB_PASSWORD}" mysqldump \
    --host="${DB_HOST}" \
    --port="${DB_PORT}" \
    --user="${DB_USERNAME}" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --events \
    --default-character-set=utf8mb4 \
    "${DB_DATABASE}" | gzip -9 > "${BACKUP_DIR}/database_${TIMESTAMP}.sql.gz"

echo "  Database dump complete: $(du -sh "${BACKUP_DIR}/database_${TIMESTAMP}.sql.gz" | cut -f1)"

# 2. Storage & Uploads Archive
echo "[2/4] Archiving storage documents, knowledge base and attachments..."
tar -czf "${BACKUP_DIR}/storage_${TIMESTAMP}.tar.gz" \
    -C "${APP_DIR}/backend/storage/app" \
    --exclude="backups" \
    --exclude="framework/cache" \
    --exclude="framework/sessions" \
    --exclude="framework/views" \
    .

echo "  Storage archive complete: $(du -sh "${BACKUP_DIR}/storage_${TIMESTAMP}.tar.gz" | cut -f1)"

# 3. Generate SHA256 Checksums
echo "[3/4] Generating verification checksums..."
cd "${BACKUP_DIR}"
sha256sum database_${TIMESTAMP}.sql.gz storage_${TIMESTAMP}.tar.gz > "SHA256SUMS"
cat "SHA256SUMS"

# 4. Optional S3 Upload and Retention Cleanup
if [[ "${1:-}" == "--upload-s3" ]]; then
    echo "[4/4] Uploading backup bundle to remote vault ${S3_BUCKET}..."
    if command -v aws &> /dev/null; then
        aws s3 sync "${BACKUP_DIR}/" "${S3_BUCKET}/${TIMESTAMP}/" --storage-class STANDARD_IA
        echo "  Remote S3 replication successful."
    else
        echo "  [WARN] aws-cli not found, skipping S3 upload."
    fi
fi

# Prune backups older than RETENTION_DAYS
echo "Pruning local backups older than ${RETENTION_DAYS} days..."
find "${BACKUP_ROOT}" -mindepth 1 -maxdepth 1 -type d -mtime +${RETENTION_DAYS} -exec rm -rf {} +

echo "=========================================================="
echo "Backup successfully finished at $(date) -> ${BACKUP_DIR}"
echo "=========================================================="
