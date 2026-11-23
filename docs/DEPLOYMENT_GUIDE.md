# Production Deployment & Operations Guide: CRM Enterprises SaaS

**Target Environment:** Ubuntu 22.04 / 24.04 LTS (x86_64 or ARM64 VPS / Dedicated Server)  
**Stack:** Nginx 1.24+, PHP 8.3-FPM, MySQL 8.0+, Redis 7.0+, Node.js 20+, Laravel Reverb  
**Reference Domain:** `app.crmenterprises.com` (Tenant Web App) & `admin.crmenterprises.com` (Super Admin)

---

## 1. Prerequisites & Server Sizing

### Minimum Hardware Sizing
- **Small (up to 20 tenants / 100 concurrent users):** 2 vCPU, 4GB RAM, 50GB NVMe SSD.
- **Medium (up to 100 tenants / 500 concurrent users):** 4 vCPU, 8GB RAM, 150GB NVMe SSD.
- **Large (enterprise scale / high volume AI calling):** 8+ vCPU, 16GB+ RAM, 300GB NVMe SSD.

### Software Stack Installation
Execute on a fresh Ubuntu 24.04 server:

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl git unzip ufw supervisor nginx software-properties-common

# Add PHP 8.3 PPA
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
    php8.3-xml php8.3-curl php8.3-bcmath php8.3-zip php8.3-gd php8.3-redis \
    php8.3-intl php8.3-opcache

# Install Redis
sudo apt install -y redis-server
sudo systemctl enable redis-server && sudo systemctl start redis-server

# Install MySQL 8.0
sudo apt install -y mysql-server
sudo systemctl enable mysql && sudo systemctl start mysql

# Install Node.js 20.x & Composer
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Certbot for Let's Encrypt SSL
sudo apt install -y certbot python3-certbot-nginx
```

---

## 2. Directory Structure & Permissions Setup

```bash
# Create application root directory
sudo mkdir -p /var/www/crm-enterprises
sudo chown -R $USER:www-data /var/www/crm-enterprises

# Clone repository
git clone <GIT_REPOSITORY_URL> /var/www/crm-enterprises
cd /var/www/crm-enterprises

# Setup storage and cache write permissions
sudo chown -R www-data:www-data /var/www/crm-enterprises/backend/storage
sudo chown -R www-data:www-data /var/www/crm-enterprises/backend/bootstrap/cache
sudo chmod -R 775 /var/www/crm-enterprises/backend/storage
sudo chmod -R 775 /var/www/crm-enterprises/backend/bootstrap/cache
```

---

## 3. Database & Environment Configuration

1. **Create MySQL Database & User:**
```sql
CREATE DATABASE crm_enterprises_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'crm_prod_user'@'127.0.0.1' IDENTIFIED BY '<STRONG_PASSWORD>';
GRANT ALL PRIVILEGES ON crm_enterprises_prod.* TO 'crm_prod_user'@'127.0.0.1';
FLUSH PRIVILEGES;
```

2. **Configure `.env` File:**
```bash
cp /var/www/crm-enterprises/deployment/.env.production.example /var/www/crm-enterprises/backend/.env
cd /var/www/crm-enterprises/backend
php artisan key:generate
```
Edit `/var/www/crm-enterprises/backend/.env` and supply the actual MySQL credentials, Redis credentials, and Razorpay/WhatsApp/Twilio keys.

---

## 4. Backend Dependencies & Database Migrations

```bash
cd /var/www/crm-enterprises/backend
composer install --no-dev --optimize-autoloader --prefer-dist
php artisan migrate --force
```

---

## 5. Frontend SPA & PWA Build

```bash
cd /var/www/crm-enterprises/frontend
npm ci --prefer-offline
npm run build
```
Verify that `frontend/dist/` contains `index.html`, `assets/`, `manifest.webmanifest`, and `service-worker.js`.

---

## 6. Nginx & SSL Setup

```bash
# Link Nginx server block
sudo cp /var/www/crm-enterprises/deployment/nginx/crm-enterprises.conf /etc/nginx/sites-available/crm-enterprises.conf
sudo ln -s /etc/nginx/sites-available/crm-enterprises.conf /etc/nginx/sites-enabled/

# Obtain SSL Certificate via Certbot
sudo certbot --nginx -d app.crmenterprises.com -d admin.crmenterprises.com

# Test syntax and reload Nginx
sudo nginx -t
sudo systemctl reload nginx
```

---

## 7. PHP 8.3 OPcache Production Tuning

Edit `/etc/php/8.3/fpm/conf.d/10-opcache.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1
```
Reload PHP-FPM:
```bash
sudo systemctl reload php8.3-fpm
```

---

## 8. Supervisord Background Workers & Reverb WebSockets

```bash
# Link Supervisor configuration
sudo cp /var/www/crm-enterprises/deployment/supervisor/supervisord.conf /etc/supervisor/conf.d/crm-enterprises.conf

# Reread and update supervisor
sudo supervisorctl reread
sudo supervisorctl update

# Verify processes are RUNNING
sudo supervisorctl status
# Output should show:
# crm-queue-worker:crm-queue-worker_00   RUNNING
# crm-queue-worker:crm-queue-worker_01   RUNNING
# crm-queue-worker:crm-queue-worker_02   RUNNING
# crm-queue-worker:crm-queue-worker_03   RUNNING
# crm-scheduler                         RUNNING
# crm-reverb-websockets                 RUNNING
```

---

## 9. Automated Deployment Execution

To deploy updates in the future, invoke the deployment script:
```bash
/var/www/crm-enterprises/deployment/scripts/deploy.sh main
```

### Rollback Procedure
If a production issue occurs:
```bash
# 1. Enable maintenance mode
cd /var/www/crm-enterprises/backend
php artisan down

# 2. Checkout previous known good git tag or commit
git checkout <PREVIOUS_STABLE_COMMIT_OR_TAG>

# 3. Restore database snapshot if schema was altered
/var/www/crm-enterprises/deployment/scripts/restore.sh /var/backups/crm-enterprises/<PRE_DEPLOY_BACKUP> --force

# 4. Rebuild assets and warm caches
composer install --no-dev --optimize-autoloader
npm run build --prefix ../frontend
php artisan optimize

# 5. Restart services and disable maintenance mode
sudo supervisorctl restart all
sudo systemctl reload php8.3-fpm
php artisan up
```
