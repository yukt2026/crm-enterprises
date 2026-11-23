# Disaster Recovery, Backup & Restore Guide: CRM Enterprises SaaS

**Objective:** Ensure high-availability business continuity, point-in-time recovery, and zero data loss.  
**Recovery Point Objective (RPO):** < 1 hour (with binary logs) / 24 hours (nightly automated snapshot)  
**Recovery Time Objective (RTO):** < 30 minutes from disaster declaration to full online status  

---

## 1. Backup Strategy Overview

| Asset | Mechanism | Frequency | Storage Location | Retention |
| :--- | :--- | :--- | :--- | :--- |
| **MySQL Database** | `mysqldump` with `--single-transaction`, `--quick`, gzip | Nightly (02:00 UTC) | Local `/var/backups/` + AWS S3 Vault | 14 days local / 90 days S3 |
| **User Media & Documents** | `tar -czf` of `backend/storage/app` (excluding caches) | Nightly (02:30 UTC) | Local `/var/backups/` + AWS S3 Vault | 14 days local / 90 days S3 |
| **Integrity Hashes** | `sha256sum` manifest generation | On each backup run | Accompanying `SHA256SUMS` | Lifespan of archive |
| **Environment Configuration** | Encrypted versioned vault | On modification | AWS Secrets Manager / 1Password | Indefinite |

---

## 2. Automated Daily Backup Execution

The backup routine is encapsulated in `/var/www/crm-enterprises/deployment/scripts/backup.sh`.

### Crontab Setup
Configure the system cron job under `root`:
```bash
sudo crontab -e
```
Add the following line:
```cron
0 2 * * * /var/www/crm-enterprises/deployment/scripts/backup.sh --upload-s3 >> /var/log/crm-backup.log 2>&1
```

### Manual Backup Command
To execute an immediate ad-hoc snapshot (e.g. prior to major software upgrades):
```bash
sudo /var/www/crm-enterprises/deployment/scripts/backup.sh --upload-s3
```

### Verification of Backup Archive
Every backup directory contains:
- `database_YYYYMMDD_HHMMSS.sql.gz`: Compressed SQL schema and table data.
- `storage_YYYYMMDD_HHMMSS.tar.gz`: Compressed tenant uploads, documents, and RAG vectors.
- `SHA256SUMS`: Cryptographic checksums of both archives.

---

## 3. Step-by-Step Restoration Procedure

When recovering from data corruption, accidental deletion, or full server replacement, execute the restoration script:

```bash
sudo /var/www/crm-enterprises/deployment/scripts/restore.sh /var/backups/crm-enterprises/<BACKUP_TIMESTAMP_FOLDER>
```

### Execution Steps Carried Out by `restore.sh`:
1. **Cryptographic Checksum Verification:**
   Executes `sha256sum -c SHA256SUMS`. If any file is corrupted or tampered with, the process terminates immediately.
2. **Maintenance Mode Activation:**
   Runs `php artisan down` to disconnect web users and prevent race conditions.
3. **Database Restoration:**
   Streams `gunzip -c database_*.sql.gz` directly into `mysql` with UTF-8 character encoding.
4. **Storage Archive Extraction & Permission Fix:**
   Extracts `storage_*.tar.gz` into `storage/app`, and reapplies ownership to `www-data:www-data` with mode 775.
5. **Cache Invalidation & Schema Re-Sync:**
   Executes `php artisan migrate --force` followed by `php artisan optimize` to refresh configurations and route caches.
6. **Online Re-activation:**
   Executes `php artisan up` to restore live production access.

---

## 4. Disaster Recovery Drills & Testing Schedule

- **Frequency:** Drills are scheduled quarterly.
- **Drill Procedure:**
  1. A staging server (`staging.crmenterprises.com`) pulls the latest production backup from AWS S3.
  2. Runs `/var/www/crm-enterprises/deployment/scripts/restore.sh <S3_PULLED_DIR> --force`.
  3. Executes `npm run test:integration` against the staging instance.
  4. Records the exact time to recovery and verifies tenant isolation and record counts.
