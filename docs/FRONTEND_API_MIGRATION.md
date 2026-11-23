# Enterprise CRM SaaS — React Frontend Modernization & Laravel API Integration

This document outlines the frontend modernization, typed API client integration, TanStack React Query server state layer, reusable UI components, and RBAC guard architecture completed under **PROMPT 03**.

---

## 1. Preserved Architectural Stack

The existing React CRM stack was retained and modernized without framework rewrites:
- **Core Engine**: React 19 + TypeScript + Vite 6
- **Styling**: Tailwind CSS 4 (with persistent Dark and Light mode themes)
- **Client State**: Zustand (active workspace, session tokens, toasts, sidebar toggle, filters)
- **Server State**: `@tanstack/react-query` v5 (query caching, window focus handling, automatic cache invalidation)
- **Visuals & Charts**: Recharts, Lucide React, Framer Motion
- **Document Generators**: jsPDF (custom branded PDF export) and native CSV spreadsheet stream generators

---

## 2. Centralized Typed Laravel API Client

Located at `frontend/src/api/client.ts`, the `ApiClient` provides strongly-typed methods communicating with the Laravel 12 backend (`/api/v1/*`):

### 2.1 Context Injection & Authentication
- Every outgoing request automatically attaches:
  - `Authorization: Bearer <sanctum_token>`
  - `X-Workspace-Id: <active_tenant_id>`
  - `Accept: application/json`
  - `Content-Type: application/json`
- Supports browser storage (`localStorage`) and SSR/Node fallback (`memoryStore`).

### 2.2 Error Normalization & Validation Handling
When Laravel validation fails (`422 Unprocessable Entity`), the client throws a standardized `ApiClientError`:
```ts
class ApiClientError extends Error {
  statusCode: number;
  errors?: Record<string, string[]>;
}
```
Field-level validation messages are rendered using the new `<FormError />` component underneath affected inputs.

---

## 3. Server State Architecture with React Query

Configured in `frontend/src/lib/queryClient.ts` and wrapped globally in `main.tsx`:
- **Default Stale Time**: 2 minutes (prevents redundant HTTP roundtrips during tab switching)
- **Garbage Collection**: 10 minutes
- **Smart Retries**: Bypasses retries on 401, 403, 404, or 422 HTTP statuses

### 3.1 Custom CRM Hooks (`frontend/src/hooks/useCrmQuery.ts`)
- `useModuleRecords(module, params)`: Server-side pagination, search debouncing, and filter params.
- `useCreateRecord(module)`: Mutation hook that invalidates target module and dashboard metrics.
- `useUpdateRecord(module)`: Mutation hook invalidating module queries upon edit.
- `useDeleteRecord(module)`: Mutation hook removing records and refreshing pagination counts.
- `useBulkDelete(module)`: Multi-record batch deletion mutation.
- `useReports()`: Fetches live pipeline summaries and monthly revenue charts.
- `useHealthCheck()`: Background query checking `/api/v1/health` every 30 seconds.

### 3.2 Workspace & Identity Hooks (`frontend/src/hooks/useWorkspaceQuery.ts`)
- `useWorkspaces()`: Retrieves user's accessible tenants.
- `useCurrentWorkspace()`: Active tenant details and brand settings.
- `useWorkspaceMembers()`: Team roster and workspace roles.
- `useLoginHistory()`: Recent authentication attempts and device signatures.
- `useSwitchWorkspace()`: Switches tenant context and invalidates all CRM queries.
- `useInviteMember()`: Issues team invitation tokens.

---

## 4. Reusable Enterprise UI Component Catalog

Located in `frontend/src/components/common/`:

### 4.1 `DataTable`
A modern, responsive table component featuring:
- **Skeleton Loaders**: Smooth animated placeholders while fetching data.
- **Empty States**: Customized empty illustrations and action buttons.
- **Error States**: Inline error message display with a "Retry" button.
- **Sorting**: Visual ascending/descending indicators on sortable columns.
- **Pagination**: Server-side pagination controls (Previous, Next, page count, per-page selector).
- **Row Selection**: Bulk selection checkboxes for batch actions.

### 4.2 `SearchFilterBar`
- 300ms debounced search input with instant clear button (`X`).
- Dynamic filter dropdowns with an active filter counter pill.
- Permission-aware CSV & PDF export triggers.
- "+ New Record" primary action button.

### 4.3 `ConfirmDialog`
Accessible confirmation dialog replacing intrusive browser `window.confirm()` dialogs for single and bulk deletion operations.

### 4.4 `FormError`
Inspects Laravel 422 response bags (`Record<string, string[]>`) and renders error alerts below input controls.

### 4.5 `ApiStatusBanner`
Monitors Laravel health status via `useHealthCheck()`. If the backend becomes disconnected or degraded, an alert banner appears with real-time diagnostic latency and a "Retry Connection" button.

### 4.6 `GlobalSearchModal`
Unified enterprise search accessible via **`Cmd + K`** / **`Ctrl + K`** or the top navigation bar. Queries across Leads, Deals, Companies, Contacts, and Projects with arrow-key and ESC dismissal.

### 4.7 `NotificationCenter`
Slide-out drawer categorizing workspace activity logs into "All Activity" and "Security / Authentication Audits".

---

## 5. Permission & RBAC Helpers

Defined in `frontend/src/utils/permissions.ts` and enforced across the UI:
- `canAccessModule(user, module)`: Filters sidebar navigation items so users only see permitted modules.
- `canPerformAction(user, module, action)`:
  - **Create**: Allowed based on role capability.
  - **Update**: Allowed for owners, managers, and assigned staff.
  - **Delete**: Restricted from base employees and support executives.
  - **Export**: Requires Manager, Accountant (financials), or Admin privileges.
  - **Import**: Strictly reserved for Business Admins and Owners.

---

## 6. Migration & Verification Results

### 6.1 Frontend Integration Test Suite
Executed via `npm run test:integration`:
```bash
> react-example@0.0.0 test:integration
> tsx src/tests/apiIntegration.test.ts

--- Starting Frontend-to-Backend Integration Suite ---

1. Testing Health Check Endpoint (/api/v1/health)... PASSED (Laravel 12.73.0, DB: connected)
2. Testing Authentication Login (/api/v1/auth/login)... PASSED (Auth challenge/token issued)
   -> Verifying 2FA PIN (/api/v1/auth/verify-2fa)... PASSED (Bearer Token acquired: 5|Xeb4MrjT3ZRze...)
3. Testing Workspaces Roster (/api/v1/workspaces)... PASSED (2 workspaces available)
4. Testing CRM Leads Listing (/api/v1/crm/leads)... PASSED (3 records returned, total: 3)
5. Testing CRM Reports Summary (/api/v1/crm/reports)... PASSED (Pipeline Value: $235000)

--- Verification Completed: 6 passed, 0 failed ---
```

### 6.2 TypeScript & Lint Verification
```bash
> tsc --noEmit
# 0 errors
```

### 6.3 Vite Production Bundle
```bash
> vite build && esbuild server.ts --bundle --platform=node --format=cjs --packages=external --sourcemap --outfile=dist/server.cjs

✓ 2559 modules transformed.
dist/index.html                            0.42 kB
dist/assets/index-DXSxHi5s.css            57.34 kB
dist/assets/index-CfT4q_is.js            795.61 kB
dist/server.cjs                           56.8 kB
✓ built in 14.94s
```

### 6.4 Backend Security & Health Tests
```bash
PASS  Tests\Feature\ApiHealthAndAuthTest (4 tests)
PASS  Tests\Feature\ExampleTest (1 test)
PASS  Tests\Feature\TenantIsolationSecurityTest (4 tests)
PASS  Tests\Unit\ExampleTest (1 test)

Tests:    10 passed (36 assertions)
Duration: 2.40s
```
