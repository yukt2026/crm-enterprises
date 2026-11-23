# Enterprise AI CRM SaaS - System Architecture & Backend Foundation

**Date**: November 2026  
**Architecture Style**: Modular Monolith (Laravel 12 REST API + React 19 Frontend)  
**Database**: MySQL 8.0+ with Multi-Tenant Row Isolation (`TenantScope`)  
**Cache & Queues**: Redis + Laravel Horizon / Database fallback  
**Real-Time & Events**: Laravel Reverb WebSockets + Laravel Echo

---

## 1. High-Level Modular Monolith Architecture

The system is structured into domain packages to avoid a tangled monolith while avoiding premature microservices:

```text
backend/app/
├── Domains/
│   ├── Tenancy/                # Tenant model, TenantScope, BelongsToTenant, SystemSettings
│   ├── Identity/               # Users, Spatie Roles & Permissions, Notifications
│   ├── CRM/                    # Companies, Contacts, Customers, Deals
│   ├── LeadAcquisition/        # Leads, AI Scoring Cache, Lead Sources
│   ├── Sales/                  # Products, Services, Catalog Pricing
│   ├── Finance/                # Quotations, Invoices, InvoiceItems, Payments
│   ├── Projects/               # Projects, Tasks, Meetings, ProjectMembers
│   ├── HR/                     # Employees, Attendance, Leaves
│   ├── Support/                # SupportTickets, AI Auto-Reply Cache
│   ├── AuditSecurity/          # ActivityLogs, IdempotencyKeys
│   ├── Analytics/              # KPI Metrics, Chart Aggregators
│   └── AI/                     # Gemini Orchestrator Service, Prompt Templates
│
├── Http/
│   ├── Controllers/Api/V1/     # Thin versioned controllers (Health, Auth, Lead, Deal, etc.)
│   ├── Middleware/             # RecordActivityAudit, EnsureIdempotency, CORS, Tenant
│   ├── Requests/               # FormRequest validation rules and sanitization
│   └── Traits/                 # ApiResponse standard formatting
```

---

## 2. API Contract & Standards

### 2.1 API Versioning
All endpoints are versioned under `/api/v1/`. Direct aliases under `/api/` are maintained to guarantee backward compatibility with the frontend during the phased migration.

### 2.2 Standard Success Response Format
```json
{
  "success": true,
  "status_code": 200,
  "message": "Resource retrieved successfully.",
  "data": { ... },
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 10,
    "total": 48,
    "has_more_pages": true
  }
}
```

### 2.3 Central Error Format
```json
{
  "success": false,
  "status_code": 422,
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

### 2.4 Idempotency Protection
Clients can send an `Idempotency-Key: <UUID>` header on sensitive operations (`POST`, `PUT`). If a network retry occurs within 24 hours, the middleware returns the cached response with `X-Cache-Lookup: HIT-IDEMPOTENT` without executing duplicate database mutations.

---

## 3. Database Schema & Multi-Tenancy

Every tenant is identified in the `tenants` table. Tenant-owned records include a `tenant_id` foreign key:
```sql
ALTER TABLE leads ADD CONSTRAINT fk_leads_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;
CREATE INDEX idx_leads_tenant_status ON leads (tenant_id, status);
```

### Global Tenant Isolation Scope
The `BelongsToTenant` trait attaches `TenantScope`:
```php
public function apply(Builder $builder, Model $model): void
{
    if (Auth::check() && Auth::user()->tenant_id && !Auth::user()->hasRole('Super Admin')) {
        $builder->where($model->getTable() . '.tenant_id', Auth::user()->tenant_id);
    }
}
```

---

## 4. Local Development & Operational Instructions

### 4.1 Starting the Stack

1. **Start MySQL**:
   Ensure XAMPP MySQL is running on port `3306`. The database `crm_enterprises` is pre-configured.

2. **Run Migrations & Seeders**:
   ```bash
   cd backend
   php artisan migrate
   php artisan db:seed
   ```

3. **Start the Laravel API Server**:
   ```bash
   cd backend
   php artisan serve --host=127.0.0.1 --port=8000
   ```

4. **Verify Health Endpoint**:
   ```bash
   curl http://127.0.0.1:8000/api/v1/health
   ```

5. **Start Frontend Client**:
   ```bash
   cd frontend
   npm run dev
   ```
   The frontend runs at `http://localhost:3000` with requests to `/api/*` proxied to `http://127.0.0.1:8000`.

### 4.2 Automated Testing
Run automated unit and feature tests:
```bash
cd backend
php artisan test
```
All 6 automated tests run in in-memory SQLite and verify health diagnostics, authentication, and 2FA token generation.
