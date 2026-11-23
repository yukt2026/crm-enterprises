# Shared Platform Services Specification & Implementation

## 1. Overview & Architectural Blueprint

The Enterprise AI CRM SaaS platform relies on a suite of reusable, tenant-isolated foundation services that empower all vertical modules (Leads, Deals, Contacts, Companies, Projects, Tasks, Tickets, Invoices, HR, and AI Agents). 

These services are built with strict security guarantees:
- **Tenant Isolation**: Every record is partitioned by `tenant_id` via global scopes and `TenantContext`.
- **Private File Protection**: Attachments are stored off-web-root and accessed exclusively via temporary HMAC-signed URLs (`URL::temporarySignedRoute`).
- **Schema Safety**: Custom fields leverage an EAV/metadata architecture (`custom_field_definitions` and `custom_field_values`) preventing unsafe runtime SQL DDL modifications.
- **Tamper-Proof Auditability**: Audit logs are append-only. Eloquent lifecycle hooks strictly reject updates and deletes with `RuntimeException`.

```
+-----------------------------------------------------------------------------------+
|                           REACT 19 / VITE FRONTEND                                |
|  +--------------------+  +----------------------+  +---------------------------+  |
|  | NotificationCenter |  | MediaFileManagerView |  |    GlobalSearchModal      |  |
|  +--------------------+  +----------------------+  +---------------------------+  |
|  | CustomFieldsBuilder|  | NotificationPrefs    |  |     AuditLogViewer        |  |
|  +--------------------+  +----------------------+  +---------------------------+  |
+-----------------------------------------|-----------------------------------------+
                                          | JSON REST APIs (/api/v1/...)
+-----------------------------------------v-----------------------------------------+
|                               LARAVEL 12 BACKEND                                  |
|  +--------------------+  +----------------------+  +---------------------------+  |
|  | NotificationService|  |  FileManagerService  |  |    GlobalSearchService    |  |
|  +--------------------+  +----------------------+  +---------------------------+  |
|  | CustomFieldService |  |   WorkspaceSettings  |  |        AuditLogger        |  |
|  +--------------------+  +----------------------+  +---------------------------+  |
+-----------------------------------------|-----------------------------------------+
                                          |
                               +----------v----------+
                               |   MySQL 8.0 Storage |
                               +---------------------+
```

---

## 2. Notification Center

### 2.1 Event Matrix (14 Standard Workspace Triggers)
1. `lead.assigned` – New lead assigned to user or team
2. `lead.reassigned` – Lead transferred to another owner
3. `lead.followup_due` – Follow-up task past due timestamp
4. `message.new` – Inbound message received across omni-channels
5. `call.missed` – Missed telephonic or AI agent call
6. `ai.handover` – AI agent escalation to human agent
7. `ticket.new` – New support ticket created
8. `task.assigned` – Task assigned or approaching 24h deadline
9. `meeting.reminder` – 15-minute advance meeting reminder
10. `hr.leave_status` – Leave approved or rejected by management
11. `finance.invoice_event` – Invoice issued or payment received
12. `workflow.failure` – Automation trigger or execution failure
13. `billing.usage_warning` – Quota usage reaching 85%+ threshold
14. `integration.failure` – WhatsApp, Twilio, or Stripe API sync failure

### 2.2 Schema & Model Architecture
- **Table `notifications`**:
  - `id` (bigint unsigned, auto-increment)
  - `user_id` (foreign key -> `users.id`)
  - `tenant_id` (foreign key -> `tenants.id`)
  - `type` (varchar 50, e.g., `lead.assigned`)
  - `title` (varchar 255)
  - `message` (text)
  - `action_url` (varchar 255, nullable)
  - `read_at` (timestamp, nullable)
  - `channel` (varchar 30, e.g., `in_app`, `email`, `push`)
  - `data` (json, nullable)
  - `created_at`, `updated_at`
- **Table `notification_preferences`**:
  - `id`, `user_id`, `tenant_id`, `notification_type`
  - `in_app_enabled` (boolean, default true)
  - `email_enabled` (boolean, default true)
  - `push_enabled` (boolean, default false)

### 2.3 Endpoints
- `GET /api/v1/notifications` – Paginated notifications for active user
- `GET /api/v1/notifications/unread-count` – Fast unread counter badge
- `POST /api/v1/notifications/{id}/read` – Mark single notification as read
- `POST /api/v1/notifications/mark-all-read` – Mark all workspace notifications read
- `GET /api/v1/notifications/preferences` – Retrieve user channel preferences
- `PUT /api/v1/notifications/preferences` – Update notification matrix preferences
- `POST /api/v1/notifications/test` – Dispatch a test notification

---

## 3. File & Media Manager

### 3.1 Security & Storage Isolation
- Files are stored in tenant-isolated paths: `storage/app/tenants/{tenant_id}/{YYYY}/{MM}/{uuid}.{ext}`.
- Max file size strictly enforced: **25 MB**.
- Allowed MIME types: images (`png`, `jpg`, `jpeg`, `webp`, `gif`), documents (`pdf`, `doc`, `docx`, `xls`, `xlsx`, `csv`, `txt`), archives (`zip`, `tar`, `gz`).
- **Temporary Signed URLs**: Files are never exposed via public static URLs. Access is granted through signed URLs (`URL::temporarySignedRoute('api.v1.media.download', now()->addMinutes(60), ['uuid' => $file->uuid])`) verified with HMAC signatures.

### 3.2 Schema: `media_files`
- `id`, `uuid` (char 36, unique)
- `tenant_id` (foreign key -> `tenants.id`)
- `user_id` (foreign key -> `users.id`)
- `fileable_type`, `fileable_id` (polymorphic relation)
- `file_name` (sanitized internal filename)
- `original_name` (uploaded client name)
- `mime_type` (e.g. `application/pdf`)
- `file_size` (size in bytes)
- `disk` (storage disk, defaults to `local` / `s3`)
- `storage_path` (relative disk path)
- `hash` (SHA-256 checksum)
- `is_private` (boolean, defaults to true)
- `retention_days` (optional automatic pruning)
- `metadata` (JSON: width, height, client metadata)
- `deleted_at` (soft deletes support)

### 3.3 Endpoints
- `GET /api/v1/media` – List tenant files (filtered by MIME or polymorphic parent)
- `POST /api/v1/media` – Secure multipart/form-data upload
- `GET /api/v1/media/{uuid}` – Retrieve file metadata and signed download URL
- `GET /api/v1/media/{uuid}/download` – Download file with cryptographic signature check
- `DELETE /api/v1/media/{uuid}` – Soft delete file

---

## 4. Custom Fields & Metadata Engine

### 4.1 Scalable EAV/JSON Metadata Design
Arbitrary SQL column modifications (`ALTER TABLE ... ADD COLUMN ...`) cause locking, schema drift, and security vulnerabilities in multi-tenant systems. The CRM implements a scalable metadata model:
1. `custom_field_definitions` defines metadata schemas per tenant and entity.
2. `custom_field_values` stores entity attribute values cleanly typed and indexed.

### 4.2 Supported 12 Field Types
- `text` (Single line text)
- `long_text` (Multi-line text)
- `number` (Numeric and integer quantities)
- `date` (ISO date picker)
- `select` (Single choice dropdown)
- `multi_select` (Multiple tags choice)
- `checkbox` (Boolean toggle)
- `email` (Email validation)
- `phone` (Phone format validation)
- `url` (Web link validation)
- `currency` (Formatted monetary currency)
- `file` (Media file UUID reference)

### 4.3 Schemas: `custom_field_definitions` & `custom_field_values`
- **`custom_field_definitions`**:
  - `id`, `tenant_id`, `entity_type` (`lead`, `deal`, `contact`, `company`, `customer`, `project`, `task`, `ticket`, `employee`, `invoice`)
  - `name` (Display label)
  - `field_key` (Slug identifier)
  - `type` (One of the 12 types)
  - `options` (JSON array for select options)
  - `is_required` (boolean)
  - `default_value` (string)
  - `display_order` (integer)
  - `validation_rules` (JSON validation constraints)
  - `visibility_roles` (JSON role visibility whitelist)
- **`custom_field_values`**:
  - `id`, `tenant_id`, `custom_field_definition_id`, `entity_type`, `entity_id`, `value` (text/json)

### 4.4 Endpoints
- `GET /api/v1/custom-fields` – List custom field definitions
- `POST /api/v1/custom-fields` – Create field definition
- `PUT /api/v1/custom-fields/{id}` – Update field definition
- `DELETE /api/v1/custom-fields/{id}` – Delete field definition
- `GET /api/v1/custom-fields/values/{entityType}/{entityId}` – Get record custom values
- `POST /api/v1/custom-fields/values/{entityType}/{entityId}` – Save record custom values

---

## 5. Global Unified Search

### 5.1 Multi-Entity Search Provider
The global search endpoint searches across 8+ authorized CRM models in parallel, respecting tenant boundaries and permissions:
- Leads (`name`, `company`, `email`, `phone`)
- Deals (`name`, `stage`)
- Contacts (`name`, `email`, `phone`)
- Companies (`name`, `industry`)
- Projects (`name`, `status`)
- Tasks (`title`, `status`)
- Tickets (`subject`, `ticket_number`)
- Invoices (`invoice_number`)

### 5.2 Uniform Contract
```json
{
  "id": "1",
  "title": "Acme Corp Expansion",
  "subtitle": "Stage: Qualified",
  "module": "deals",
  "url": "/deals/1",
  "snippet": "Stage: qualified • Value: $45000",
  "status": "qualified",
  "created_at": "2026-11-23T01:30:00Z"
}
```

### 5.3 Endpoint
- `GET /api/v1/search?q={query}&module={optionalModule}&limit=20`

---

## 6. Audit & Security Telemetry

### 6.1 Tamper-Proof Immutability
`App\Domains\AuditSecurity\Models\AuditLog` enforces lifecycle immutability at the model layer:
```php
protected static function booted(): void
{
    static::updating(function () {
        throw new \RuntimeException('Audit logs are immutable and cannot be modified.');
    });

    static::deleting(function () {
        throw new \RuntimeException('Audit logs are immutable and cannot be deleted.');
    });
}
```

### 6.2 Schema: `audit_logs`
- `id`, `uuid` (char 36, unique correlation ID)
- `tenant_id`, `user_id`, `user_name`, `role`
- `action` (`create`, `update`, `delete`, `auth.login`, etc.)
- `auditable_type`, `auditable_id` (polymorphic entity)
- `old_values` (JSON previous state)
- `new_values` (JSON mutated state)
- `ip_address`, `user_agent`, `url`, `method`
- `created_at` (no `updated_at` or `deleted_at`)

### 6.3 Endpoints
- `GET /api/v1/audit-logs` – Paginated audit entries with filter by action and dates
- `GET /api/v1/audit-logs/{id}` – Single log entry with full JSON diff

---

## 7. Frontend Integration & UI Architecture

| Component | Path | Responsibility |
|---|---|---|
| `GlobalSearchModal` | `frontend/src/components/common/GlobalSearchModal.tsx` | Debounced multi-entity search modal with module filter pills and hotkey (`Ctrl+K` / `Cmd+K`). |
| `NotificationCenter` | `frontend/src/components/common/NotificationCenter.tsx` | Slide-out drawer displaying real-time alerts, mark all read, and test dispatch. |
| `MediaFileManagerView` | `frontend/src/components/common/MediaFileManagerView.tsx` | Secure file explorer, drag & drop uploader (25MB limit), image preview, and signed downloads. |
| `NotificationPreferencesView` | `frontend/src/components/common/NotificationPreferencesView.tsx` | Configurable matrix for 14 workspace triggers across in-app, email, and push. |
| `CustomFieldsBuilder` | `frontend/src/components/common/CustomFieldsBuilder.tsx` | Custom field definition manager across 10 entity types with 12 field types. |
| `AuditLogViewer` | `frontend/src/components/common/AuditLogViewer.tsx` | Immutable audit log trail with side-by-side JSON before/after state diff modal. |
| `ModuleModals` | `frontend/src/components/ModuleModals.tsx` | Dynamic rendering of custom attributes during record creation and editing. |
| `Layout` | `frontend/src/components/Layout.tsx` | Sidebar item for Media & Files, dynamic notification badge reflecting unread count. |

---

## 8. Verification & Test Evidence

### 8.1 Backend Automated Tests (15 Passed, 69 Assertions)
```
   PASS  Tests\Unit\ExampleTest
  ✓ that true is true

   PASS  Tests\Feature\ApiHealthAndAuthTest
  ✓ health check endpoint returns healthy diagnostics
  ✓ login fails with invalid credentials
  ✓ login returns 2fa challenge for protected user
  ✓ verify 2fa issues sanctum token

   PASS  Tests\Feature\ExampleTest
  ✓ the application returns a successful response

   PASS  Tests\Feature\SharedPlatformServicesTest
  ✓ notification creation and preferences
  ✓ secure file upload and signed url
  ✓ custom fields definition and values
  ✓ global search across tenant entities
  ✓ audit log immutability

   PASS  Tests\Feature\TenantIsolationSecurityTest
  ✓ user cannot access unauthorized workspace
  ✓ tenant records are strictly isolated
  ✓ multi workspace user can switch context
  ✓ workspace invitation generation and acceptance

  Tests:    15 passed (69 assertions)
  Duration: 3.91s
```

### 8.2 Frontend End-to-End API Integration (6 Passed)
```
1. Testing Health Check Endpoint (/api/v1/health)... PASSED (Laravel 12.73.0, DB: connected)
2. Testing Authentication Login (/api/v1/auth/login)... PASSED (Auth challenge/token issued)
   -> Verifying 2FA PIN (/api/v1/auth/verify-2fa)... PASSED (Bearer Token acquired)
3. Testing Workspaces Roster (/api/v1/workspaces)... PASSED (2 workspaces available)
4. Testing CRM Leads Listing (/api/v1/crm/leads)... PASSED (3 records returned, total: 3)
5. Testing CRM Reports Summary (/api/v1/crm/reports)... PASSED (Pipeline Value: $235000)

--- Verification Completed: 6 passed, 0 failed ---
```

### 8.3 TypeScript Typecheck & Production Build
- `npm run lint` (`tsc --noEmit`): **0 errors**.
- `npm run build` (`vite build && esbuild`): **Built successfully in 14.10s**.
