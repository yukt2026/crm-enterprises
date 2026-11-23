# API Integration Plan: Express to Laravel 12 REST API

**Date**: November 2026  
**Source API**: Node.js/Express (`frontend/server.ts`, Port 3000)  
**Target API**: Laravel 12 REST API (`backend/`, Port 8000)  
**Authentication Standard**: Laravel Sanctum (Stateful SPA / Bearer Token API) + Spatie Permission

---

## 1. Complete API Route Catalog

The existing Express backend defines 35+ routes. All will map cleanly to RESTful Laravel Resource Controllers.

### 1.1 Authentication & Profile Endpoints
| HTTP Method | Current Express Path | Target Laravel API Route | Target Controller & Method | Description |
|---|---|---|---|---|
| `POST` | `/api/auth/login` | `/api/v1/auth/login` | `AuthController@login` | Credentials validation, Sanctum token creation |
| `POST` | `/api/auth/verify-2fa` | `/api/v1/auth/verify-2fa` | `AuthController@verify2FA` | Verifies TOTP / Email passcode |
| `POST` | `/api/auth/register` | `/api/v1/auth/register` | `AuthController@register` | Tenant onboarding & admin creation |
| `POST` | `/api/auth/forgot-password` | `/api/v1/auth/forgot-password` | `PasswordResetController@sendResetLink` | Sends password reset email link |
| `POST` | `/api/auth/reset-password` | `/api/v1/auth/reset-password` | `PasswordResetController@resetPassword` | Validates token and updates password |
| `GET` | `/api/auth/profile` | `/api/v1/auth/profile` | `ProfileController@show` | Returns authenticated user & roles |
| `PUT` | `/api/auth/profile` | `/api/v1/auth/profile` | `ProfileController@update` | Updates name, avatar, department |
| `PUT` | `/api/auth/change-password` | `/api/v1/auth/change-password` | `ProfileController@changePassword` | Validates current password and updates |
| `POST` | None | `/api/v1/auth/logout` | `AuthController@logout` | Revokes current Sanctum access token |

---

### 1.2 CRM Core Modules (Standard REST Resources)
All endpoints require `auth:sanctum` and tenant isolation middleware.

| Module | Route Prefix | Laravel Controller | Permitted Roles |
|---|---|---|---|
| **Leads** | `/api/v1/crm/leads` | `LeadController` | `Super Admin`, `Admin`, `Manager`, `Sales Executive` |
| **Companies** | `/api/v1/crm/companies` | `CompanyController` | `Super Admin`, `Admin`, `Manager`, `Sales Executive` |
| **Customers** | `/api/v1/crm/customers` | `CustomerController` | `Super Admin`, `Admin`, `Manager`, `Sales Executive` |
| **Contacts** | `/api/v1/crm/contacts` | `ContactController` | `Super Admin`, `Admin`, `Manager`, `Sales Executive` |
| **Deals** | `/api/v1/crm/deals` | `DealController` | `Super Admin`, `Admin`, `Manager`, `Sales Executive` |
| **Products** | `/api/v1/crm/products` | `ProductController` | `Super Admin`, `Admin`, `Manager`, `Accountant` |
| **Services** | `/api/v1/crm/services` | `ServiceController` | `Super Admin`, `Admin`, `Manager`, `Accountant` |
| **Quotations** | `/api/v1/crm/quotations` | `QuotationController` | `Super Admin`, `Admin`, `Manager`, `Sales Executive`, `Accountant` |
| **Invoices** | `/api/v1/crm/invoices` | `InvoiceController` | `Super Admin`, `Admin`, `Manager`, `Accountant` |
| **Payments** | `/api/v1/crm/payments` | `PaymentController` | `Super Admin`, `Admin`, `Manager`, `Accountant` |
| **Projects** | `/api/v1/crm/projects` | `ProjectController` | `Super Admin`, `Admin`, `Manager`, `Employee` |
| **Tasks** | `/api/v1/crm/tasks` | `TaskController` | All Internal Users |
| **Meetings** | `/api/v1/crm/meetings` | `MeetingController` | All Internal Users |
| **Employees** | `/api/v1/crm/employees` | `EmployeeController` | `Super Admin`, `Admin`, `HR` |
| **Attendance** | `/api/v1/crm/attendance` | `AttendanceController` | `Super Admin`, `Admin`, `HR`, `Employee` |
| **Leaves** | `/api/v1/crm/leaves` | `LeaveController` | `Super Admin`, `Admin`, `HR`, `Employee` |
| **Support Tickets**| `/api/v1/crm/tickets` | `TicketController` | `Super Admin`, `Admin`, `Manager`, `Support Executive` |
| **Users** | `/api/v1/crm/users` | `UserController` | `Super Admin`, `Admin` |
| **Activity Logs** | `/api/v1/crm/activity-logs` | `ActivityLogController` | `Super Admin`, `Admin` |

Each resource provides standard HTTP methods:
- `GET /` (Paginated list, search, filter, sort)
- `POST /` (Create new record with FormRequest validation)
- `GET /{id}` (View details with eager-loaded relations)
- `PUT /{id}` (Update record)
- `DELETE /{id}` (Soft delete record)
- `POST /bulk-delete` (Bulk soft delete array of IDs)

---

### 1.3 Specialized Business & Intelligence Endpoints

| HTTP Method | Route | Controller & Action | Functionality |
|---|---|---|---|
| `POST` | `/api/v1/crm/attendance/checkin` | `AttendanceController@checkIn` | Clocks in current user with IP & time |
| `POST` | `/api/v1/crm/attendance/checkout` | `AttendanceController@checkOut` | Clocks out current user with elapsed time |
| `PUT` | `/api/v1/crm/leaves/{id}/approve` | `LeaveController@updateStatus` | Approves or rejects employee leave request |
| `GET` | `/api/v1/crm/reports` | `ReportController@dashboardMetrics` | Computes revenue, conversion rates, and charts |
| `GET` | `/api/v1/crm/roles-permissions` | `RolePermissionController@index`| Fetches Spatie roles and assigned permissions |
| `GET` | `/api/v1/crm/settings` | `SettingController@show` | Fetches company/tenant system preferences |
| `PUT` | `/api/v1/crm/settings` | `SettingController@update` | Updates tenant billing and currency preferences |
| `GET` | `/api/v1/crm/export/{module}` | `ExportController@export` | Streams server-side CSV/Excel/PDF export |
| `POST` | `/api/v1/crm/import` | `ImportController@import` | Parses uploaded file, validates rows in queue |
| `POST` | `/api/v1/crm/ai/analyze-lead` | `AIIntelligenceController@analyzeLead` | Queued Gemini AI lead qualification analysis |
| `POST` | `/api/v1/crm/ai/reply-ticket` | `AIIntelligenceController@draftTicketReply` | Queued Gemini AI ticket draft generator |

---

## 2. Standardized JSON Response Format

All Laravel API responses will adhere to a consistent enterprise structure:

### Success Response
```json
{
  "success": true,
  "message": "Resource retrieved successfully.",
  "data": { ... },
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 68
  }
}
```

### Validation Error Response (HTTP 422)
```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "email": ["The email address has already been taken."],
    "estimated_value": ["The estimated value must be a number."]
  }
}
```

---

## 3. Middleware Pipeline

1. **`HandleCors`**: Allows requests from `http://localhost:3000` and production domains with `credentials: true`.
2. **`auth:sanctum`**: Validates the Bearer token or session cookie.
3. **`IdentifyTenant`**: Resolves `tenant_id` from the authenticated user or subdomain header and applies `TenantScope`.
4. **`CheckRoleAndPermission`**: Enforces Spatie roles and fine-grained permissions.
5. **`RecordActivityAudit`**: Automatically captures mutating API requests (POST, PUT, DELETE) and writes to `activity_logs`.
6. **`ThrottleRequests`**: API rate limiting (e.g. 120 requests/minute for standard endpoints, 20 requests/minute for AI endpoints).

---

## 4. Frontend Integration & Migration Steps

### 4.1 Vite Development Proxy
In `frontend/vite.config.ts`, configure proxy routing to forward all `/api` requests to the Laravel backend on port 8000:
```ts
server: {
  proxy: {
    '/api': {
      target: 'http://127.0.0.1:8000',
      changeOrigin: true,
      secure: false,
    },
  },
}
```

### 4.2 Updating `crmStore.ts` Request Adapter
The existing `apiFetch` in `crmStore.ts` already attaches `Authorization: Bearer <token>`.
When transitioning:
1. Standardize query parameters: Map `limit` to `per_page`, `q` to `search`, `sort_by` to `sort_by`.
2. Unpack response payloads: Laravel returns records in `{ success: true, data: [...] }`. Update store methods to handle both array responses and paginated objects (`data.data` and `data.meta`).
3. Add 401 Unauthorized interceptor: Automatically clear local state and redirect to the login gate if a token expires.
