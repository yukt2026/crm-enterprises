# Enterprise CRM SaaS — Multi-Tenancy, Authentication & RBAC Architecture

This document details the multi-tenant architecture, role-based access control (RBAC), authentication flows, and security measures implemented for the Enterprise CRM SaaS platform in accordance with **PROMPT 02**.

---

## 1. Multi-Tenant Business Architecture

The platform supports multiple independent business workspaces on a single unified infrastructure (modular monolith with single-database multi-tenancy and row-level logical isolation).

### 1.1 Tenant Isolation Model
- **Tenancy Context Resolution**:
  - Every tenant-owned record is bound to a `tenant_id` foreign key.
  - Client requests specify the target workspace context via the `X-Workspace-Id` HTTP header.
  - The `ResolveActiveTenant` middleware intercepts every authenticated request, checks the user's active membership in `tenant_users`, and validates access.
  - **Zero Trust**: Client-supplied tenant IDs are never blindly accepted. If a user tries to access a workspace where they lack an active membership, a `403 Forbidden` (`Unauthorized workspace context.`) is returned immediately.
  - Platform Super Admins (`is_platform_admin = true`) can inspect across tenants for system maintenance and auditing, but default to their active pinned workspace.

```
                  ┌─────────────────────────────────────────┐
                  │           React SPA / Mobile            │
                  │   Header: X-Workspace-Id: {tenant_id}   │
                  │   Bearer Token (Sanctum)                │
                  └────────────────────┬────────────────────┘
                                       │
                                       ▼
                  ┌─────────────────────────────────────────┐
                  │    ResolveActiveTenant Middleware       │
                  │  1. Check user->workspaces()            │
                  │  2. Reject non-members (403 IDOR Block) │
                  │  3. TenantContext::setTenant($tenant)   │
                  └────────────────────┬────────────────────┘
                                       │
                                       ▼
                  ┌─────────────────────────────────────────┐
                  │             TenantScope                 │
                  │  Auto-appended to Eloquent Models       │
                  │  WHERE `tenant_id` = active_tenant_id   │
                  └─────────────────────────────────────────┘
```

### 1.2 Database Schema & Tenancy Tables

#### `tenants` Table
| Column | Type | Description |
|---|---|---|
| `id` | BIGINT UNSIGNED (PK) | Unique tenant identifier |
| `name` | VARCHAR(255) | Business legal entity name |
| `slug` | VARCHAR(255) UNIQUE | Subdomain or URL-safe slug |
| `domain` | VARCHAR(255) NULL | Custom domain mapping |
| `subscription_tier` | ENUM | `free`, `starter`, `pro`, `enterprise` |
| `status` | ENUM | `active`, `suspended`, `cancelled` |

#### `tenant_users` Table (Workspace Memberships)
| Column | Type | Description |
|---|---|---|
| `id` | BIGINT UNSIGNED (PK) | Membership identifier |
| `tenant_id` | BIGINT UNSIGNED (FK) | Reference to `tenants.id` |
| `user_id` | BIGINT UNSIGNED (FK) | Reference to `users.id` |
| `role` | VARCHAR(100) | Workspace-level role (e.g. `Business Owner`) |
| `is_owner` | BOOLEAN | Indicates primary business owner |
| `status` | ENUM | `active`, `invited`, `suspended` |
| `joined_at` | TIMESTAMP NULL | Date user joined workspace |

#### `workspace_invitations` Table
| Column | Type | Description |
|---|---|---|
| `id` | BIGINT UNSIGNED (PK) | Invitation identifier |
| `tenant_id` | BIGINT UNSIGNED (FK) | Target workspace |
| `invited_by` | BIGINT UNSIGNED (FK) | User who sent the invite |
| `email` | VARCHAR(255) | Recipient's corporate email |
| `role` | VARCHAR(100) | Assigned workspace role |
| `token` | VARCHAR(100) UNIQUE | 64-character cryptographic token |
| `status` | ENUM | `pending`, `accepted`, `revoked`, `expired` |
| `expires_at` | TIMESTAMP | Validity window (7 days) |

#### `login_histories` Table (Audit Trails)
| Column | Type | Description |
|---|---|---|
| `id` | BIGINT UNSIGNED (PK) | Log entry identifier |
| `user_id` | BIGINT UNSIGNED (FK) | Authenticating user |
| `tenant_id` | BIGINT UNSIGNED (FK) NULL | Active workspace during session |
| `ip_address` | VARCHAR(45) | Client IP address |
| `user_agent` | TEXT | Device and browser signature |
| `device_type` | VARCHAR(50) | Desktop / Mobile / Tablet / Bot |
| `status` | ENUM | `success`, `failed`, `blocked_mfa` |
| `created_at` | TIMESTAMP | Audit timestamp |

---

## 2. Authentication & Session Security

### 2.1 Multi-Workspace Switching Flow
Users can belong to multiple businesses (e.g., a consultant or agency manager).
1. `GET /api/v1/workspaces`: Retrieves all workspaces where the user has an active membership, returning their role and subscription tier in each.
2. `POST /api/v1/workspaces/switch`:
   - Validates that the requested `workspace_id` is linked to the user in `tenant_users`.
   - Updates `users.current_tenant_id`.
   - Returns a refreshed workspace session object.
   - Frontend stores the active workspace ID in `localStorage` and automatically attaches `X-Workspace-Id` to all subsequent API calls.
3. `POST /api/v1/workspaces`:
   - Allows users to register and launch a new business workspace instantly.
   - Generates default workspace settings, regional defaults, and marks creator as `Business Owner` (`is_owner = true`).

### 2.2 Two-Factor Authentication (MFA)
- Implemented via TOTP / challenge verification.
- When `two_factor_enabled` is true, the login endpoint issues a temporary 2FA challenge response (`requires_2fa: true`).
- A full Sanctum API bearer token is issued only upon successful verification via `POST /api/v1/auth/verify-2fa`.

### 2.3 Secure Session Logging
Every authentication attempt (successful, invalid credentials, or 2FA challenge) is logged in `login_histories` with client IP address, device type, and timestamp, accessible via `GET /api/v1/auth/login-history`.

---

## 3. Role-Based Access Control (RBAC)

The system supports 9 predefined roles plus tenant-specific custom roles.

### 3.1 Initial System Roles
1. **Platform Super Admin**:
   - Platform-wide maintenance, tenant provisioning, system health, and global audit oversight.
   - Distinct from regular business admins.
2. **Business Owner**:
   - Full ownership of the tenant workspace, subscription billing, custom branding, and workspace deletion.
3. **Business Admin**:
   - Workspace configuration, user role management, system settings, and integrations.
4. **Manager**:
   - Team-wide oversight, assignment of leads/deals, approving leave applications, and performance reports.
5. **Sales Executive**:
   - Lead acquisition, pipeline deals, customer quotations, and sales activities.
6. **Support Executive**:
   - Ticket helpdesk, SLA management, and AI draft auto-replies.
7. **HR**:
   - Employee records, attendance tracking, leave balances, and departmental policies.
8. **Accountant**:
   - Invoices, payment records, tax reports, and financial accounting.
9. **Employee**:
   - Self-service tasks, clock-in/out duty attendance, assigned meetings, and internal tickets.

### 3.2 Granular Permissions Matrix
Enforced strictly at the Laravel Policy and Service layer:
- **Module Access**: `leads`, `deals`, `contacts`, `companies`, `invoices`, `employees`, `tickets`, `settings`.
- **Action Scopes**:
  - `view_own` vs `view_team` vs `view_all`.
  - `create`, `update`, `delete`, `export`, `import`.
  - Sensitive field masking (e.g., financial margins, payroll figures).

---

## 4. Business Workspace Settings

Configurable per workspace via `PUT /api/v1/workspaces/settings` and the React frontend Settings module:

1. **Profile & Branding**:
   - Business Legal Name (`name` / `company_name`)
   - GSTIN / Corporate Tax ID (`gstin_tax_id`)
   - Registered Business Address (`address`)
   - Corporate Support Email & Phone
   - Workspace Logo URL (`logo_url`)
   - Brand Colors (`brand_primary_color`, `brand_secondary_color`)
2. **Regional & Operating Hours**:
   - Global Currency (`USD`, `EUR`, `GBP`, `INR`, `AED`, `CAD`, `AUD`)
   - Default Sales Tax Rate (%)
   - Default Country & Phone Prefix
   - Timezone (`UTC`, `America/New_York`, `Europe/London`, `Asia/Kolkata`, `Asia/Dubai`, etc.)
   - Date Format (`YYYY-MM-DD`, `DD/MM/YYYY`, `MM/DD/YYYY`)
   - Time Format (`24h`, `12h`)
   - Working Days (Mon–Sun selection)
   - Business Hours Window (`09:00` to `18:00`)
3. **Team Management & Invitations**:
   - Team roster display with member roles and join dates.
   - Token-based user invitation generation.
4. **Security & Login Audits**:
   - Auditable log of all user logins, IP addresses, user agents, and statuses.

---

## 5. Automated Security Test Suite

The test suite in `backend/tests/Feature/TenantIsolationSecurityTest.php` validates multi-tenancy guarantees:

1. `test_user_cannot_access_unauthorized_workspace`:
   - Simulates a user from Tenant A passing `X-Workspace-Id` of Tenant B.
   - Asserts a `403 Forbidden` response and zero data leakage.
2. `test_tenant_records_are_strictly_isolated`:
   - Seeds leads for Tenant 1 and Tenant 2.
   - Executes queries under Tenant 1 context and asserts Tenant 2 records are completely invisible.
3. `test_multi_workspace_user_can_switch_context`:
   - Adds a user to both Tenant 1 and Tenant 2.
   - Verifies seamless switching via `/api/v1/workspaces/switch` and context updates.
4. `test_workspace_invitation_generation_and_acceptance`:
   - Business Owner creates an invitation with role `Sales Executive`.
   - Recipient accepts token via `/api/v1/workspaces/invitations/{token}/accept`.
   - Verifies active membership creation in `tenant_users`.

### Test Execution Result:
```bash
PASS  Tests\Feature\ApiHealthAndAuthTest (4 tests)
PASS  Tests\Feature\ExampleTest (1 test)
PASS  Tests\Feature\TenantIsolationSecurityTest (4 tests)
PASS  Tests\Unit\ExampleTest (1 test)

Tests:    10 passed (36 assertions)
Duration: 2.48s
```
