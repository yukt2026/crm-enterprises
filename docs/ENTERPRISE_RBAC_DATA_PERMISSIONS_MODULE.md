# Enterprise Role-Based Access Control (RBAC), Custom Roles & Data-Level Permissions

## 1. Overview
Prompt 32 implements a centralized, enterprise-grade Role-Based Access Control (RBAC) and row-level data permission system across all 31 existing and future CRM modules. It enforces authorization strictly at the Laravel API and database query levels, preventing unauthorized data exposure regardless of frontend state.

---

## 2. Architecture & Data Model

### Database Schema
- **`crm_roles`**: Tenant-isolated custom roles and protected system presets.
  - `tenant_id`, `name`, `slug`, `description`, `color`, `is_system`, `is_active`, `parent_role_id`, `created_by_user_id`.
  - Unique composite index on `(tenant_id, slug)`. System presets (`is_system = true`) are protected from deletion.
- **`crm_role_permissions`**: Granular module-action-scope mapping.
  - `crm_role_id`, `module`, `action`, `data_scope`, `scope_constraints` (JSON for specific department/employee IDs).
  - Unique composite index on `(crm_role_id, module, action)`.
- **`crm_user_roles`**: Many-to-many role assignments to users within a tenant.
  - `user_id`, `crm_role_id`, `tenant_id`, `is_primary`, `assigned_by_user_id`.
  - Unique composite index on `(user_id, crm_role_id, tenant_id)`.
- **User Hierarchy Extensions**:
  - `branch_id`, `department_id` added to `users` table for organizational data scoping.

---

## 3. Supported Scopes, Actions & Modules

### 8 Data Scopes (Row-Level Authorization)
| Scope | Description |
| :--- | :--- |
| `all` | Full tenant-wide access across all branches and departments. |
| `branch` | Records associated with the user's assigned `branch_id`. |
| `department` | Records associated with the user's assigned `department_id`. |
| `team` | Records assigned to or owned by members of the user's `sales_teams`. |
| `assigned` | Records explicitly assigned to the user (`assigned_user_id = user.id`). |
| `own` | Records created by the user (`created_by_user_id = user.id`). |
| `selected_employees` | Records owned/assigned to explicitly whitelisted employee IDs. |
| `selected_resources` | Records matching specific resource tag/category constraints. |

### 21 Granular Actions
`view`, `create`, `edit`, `delete`, `restore`, `permanent_delete`, `import`, `export`, `download`, `print`, `assign`, `reassign`, `approve`, `reject`, `archive`, `share`, `manage_settings`, `view_reports`, `manage_integrations`, `manage_templates`, `execute_actions`.

### 31 Protected CRM Modules
`leads`, `contacts`, `companies`, `deals`, `sales_teams`, `products`, `quotations`, `invoices`, `payments`, `gst_billing`, `whatsapp`, `whatsapp_ai`, `voice_calling`, `omnichannel_inbox`, `marketing_campaigns`, `drip_sequences`, `landing_pages`, `custom_forms`, `hrm_employees`, `hrm_attendance`, `hrm_leaves`, `projects`, `tasks`, `meetings`, `support_tickets`, `inventory`, `analytics_bi`, `automations_workflows`, `branches`, `settings_audit`, `saas_billing`.

---

## 4. 18 Default System Role Presets
1. **Organization Owner** (`org_owner`) - Full unrestricted root tenant permissions across all modules.
2. **Super Admin** (`super_admin`) - Full administrative access to operational and configuration modules.
3. **Organization Admin** (`org_admin`) - Tenant administration, user provisioning, security, and global settings.
4. **Branch Manager** (`branch_manager`) - Branch-scoped access across leads, deals, quotes, team members, and attendance.
5. **Department Manager** (`dept_manager`) - Department-scoped approvals, project execution, and task management.
6. **Sales Manager** (`sales_manager`) - Team-scoped pipeline management, deal assignment, quotes, and sales reporting.
7. **Sales Executive** (`sales_executive`) - Assigned/own lead nurturing, deal pipeline, WhatsApp messaging, and quotation drafting.
8. **Accountant** (`accountant`) - Finance, GST billing, tax invoices, payments, credit ledger, and financial reporting.
9. **HR Manager** (`hr_manager`) - Staff directory, shift rules, leave approvals, attendance adjustments, and HR reporting.
10. **HR Executive** (`hr_executive`) - Employee onboarding, leave records, and daily attendance monitoring.
11. **Marketing Manager** (`marketing_manager`) - Landing pages, audience segments, drip sequences, ad campaigns, and attribution analytics.
12. **Marketing Executive** (`marketing_executive`) - Content publishing, campaign drafting, and form lead tracking.
13. **Support Manager** (`support_manager`) - Helpdesk ticket escalation, SLA policy management, and support team analytics.
14. **Support Executive** (`support_executive`) - Assigned ticket responses, live chat handover, and customer contact timeline viewing.
15. **Project Manager** (`project_manager`) - Project milestone creation, task delegation, budget tracking, and meeting scheduling.
16. **Project Employee** (`project_employee`) - Assigned task time logging, project task status updates, and calendar events.
17. **Standard Employee** (`employee`) - Self-service profile, personal attendance clocking, leave requests, and company meetings.
18. **Customer Portal User** (`customer_portal_user`) - Self-service quotation viewing, invoice download, and support ticket submission.

---

## 5. Authorization Enforcement & Caching

### Backend Authorization Engine (`RbacService`)
- **`can(User $user, string $module, string $action, $record = null): bool`**: Checks if the user holds permission for the action and satisfies data scope on the target record.
- **`applyDataScope(Builder $query, User $user, string $module, string $action = 'view'): Builder`**: Applies database query filters (`where`, `whereIn`) enforcing `own`, `assigned`, `team` (via `sales_teams` pivot), `department`, or `branch`.
- **`CheckRbacPermission` Middleware**: Protects API routes via route attributes or parameter binding (registered as `'rbac'` alias in `bootstrap/app.php`).

### High-Performance Invalidation & Caching
- Effective permissions are cached for 1 hour (`rbac_effective_perm_user_{id}_tenant_{id}`).
- When a role is updated, deleted, or assigned/removed, `invalidateUserCache()` and `invalidateRoleUsersCache()` instantly flush affected cache keys across Redis/file stores.

---

## 6. Frontend UI Components

### `RoleManagementMatrixView.tsx` (Settings > Roles & Permissions)
- **Role Roster**: Search, active badges, preset shields, user counts, color pills, duplicate, and delete actions.
- **KPI Summary**: Total Roles, System Presets, Custom Roles, Active Configured Permissions counter.
- **Interactive Permission Matrix**: Expandable module rows, 21 action checkboxes per module, per-module data scope selector (`all`, `branch`, `department`, `team`, `assigned`, `own`), Select All / Deselect All, real-time search.
- **Side-by-Side Role Comparison Modal**: Differential analysis highlighting matched vs divergent permissions between two roles.
- **Role Duplication & User Assignment Modals**: Safe cloning of complex permission matrices and instant team member assignment.

---

## 7. Verification & Automated Test Coverage

### Backend Test Suite (`EnterpriseRbacDataPermissionsTest.php`)
- `test_system_role_presets_are_seeded_with_correct_permissions` (PASS)
- `test_create_custom_role_with_granular_permissions_and_data_scope` (PASS)
- `test_duplicate_role_and_copy_permissions` (PASS)
- `test_compare_roles_identifies_differences` (PASS)
- `test_assign_and_remove_role_for_user_with_cache_invalidation` (PASS)
- `test_own_and_assigned_data_scope_enforcement_on_leads` (PASS)
- `test_action_permissions_prevent_unauthorized_deletion_and_export` (PASS)
- `test_module_cross_boundary_separation` (PASS)
- `test_protected_system_roles_cannot_be_deleted` (PASS)
- **Total Backend Result**: **9 passed, 0 failed (99 assertions)**
- **Full Backend Suite**: **226 passed, 0 failed (1,545 assertions)**

### Frontend Integration Suite (`apiIntegration.test.ts`)
- **Step 46**: Validated RBAC matrix metadata (31 modules, 21 actions, 8 scopes), preset role retrieval, custom role creation, role cloning, comparison diff, effective permissions query, and role deletion.
- **Result**: **47 passed, 0 failed** (Total suite duration ~25s).
- **Production Build**: `npm run build` succeeded in 8.23s with 0 errors.
