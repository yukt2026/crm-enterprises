# Advanced Multi-Branch and Multi-Location Business Management Module

## 1. Executive Summary & Architectural Overview

The **Advanced Multi-Branch and Multi-Location Business Management Module** provides enterprise organizations with centralized multi-location governance, allowing businesses to operate across regional hubs, city offices, outlets, and distributed sales teams from a unified CRM installation.

All branch operations remain strictly linked to the parent organization while honoring centralized RBAC, tenant isolation, and row-level data scoping:
1. **Branch Profile & Operations**: Full configuration of physical location, enterprise codes (`BR-DEL-01`, `BR-BOM-01`), branch managers, business hours, working days, contact channels (WhatsApp lines, caller IDs), and department linkages.
2. **Shared Customer Directory**: Customers are not duplicated per branch. Rather, customers maintain a primary/home branch association, while seamlessly participating in branch-specific interactions (deals, leads, tickets, meetings, invoices) without record fragmentation.
3. **Cross-Branch Transfer Engine & Audit Trail**: Automated workflows for reassigning leads, employees, projects, tickets, appointments, and inventory between branches. Every transfer immutably logs the previous branch, new branch, initiating user, approval details, reason, and timestamps in `branch_transfers`.
4. **Branch Manager Dashboard**: Regional leaders access scoped real-time telemetry: leads & conversion rates, deals & open pipeline value, invoiced sales vs collected cash vs outstanding receivables, approved expenses & net profit, workforce headcount, open support tickets & SLA breaches, and sales quota progress.
5. **Central Owner Consolidated Analytics**: Organization owners and executive leadership compare all regional locations side-by-side, evaluating sales performance, collection efficiency, expense ratios, employee productivity, and quota achievement with customizable date and department filters.
6. **Local Operational Settings & Sales Targets**: Branch-level overrides for working hours, regional holidays, appointment availability, lead auto-assignment rules, and monthly/annual sales targets (`branch_sales_targets`).
7. **Security, Scope Enforcing & Anti-Tampering**: Backend requests enforce strict branch validation. Users scoped to a single branch are barred (HTTP 403 Forbidden) from accessing or modifying other branch records via parameter manipulation or ID tampering, while authorized corporate managers retain verified cross-branch access (`branches.view_all`).

---

## 2. Database Schema & Architecture

```
                  ┌──────────────────────────────────────────────┐
                  │                 branches                     │
                  ├──────────────────────────────────────────────┤
                  │ id, tenant_id, code, name                    │
                  │ city, state, country, timezone               │
                  │ is_active, manager_user_id                   │
                  │ email, phone, address_line1, address_line2   │
                  │ pincode, latitude, longitude                 │
                  │ business_hours (json), working_days (json)   │
                  │ connected_channels (json), settings (json)   │
                  │ created_at, updated_at, deleted_at           │
                  └──────────────┬───────────────────────────────┘
                                 │
         ┌───────────────────────┼────────────────────────┐
         │ 1:N                   │ 1:N                    │ 1:N
┌────────┴─────────────┐ ┌───────┴──────────────┐ ┌───────┴──────────────┐
│  branch_departments  │ │ branch_sales_targets │ │   branch_transfers   │
├──────────────────────┤ ├──────────────────────┤ ├──────────────────────┤
│id, branch_id         │ │id, tenant_id         │ │id, tenant_id         │
│department_id         │ │branch_id, period_year│ │transferable_type     │
│created_at            │ │period_type, number   │ │transferable_id       │
│                      │ │target_amount         │ │from_branch_id        │
│                      │ │achieved_amount       │ │to_branch_id          │
│                      │ │target_leads/deals    │ │initiated_by_user_id  │
│                      │ │achieved_leads/deals  │ │approved_by_user_id   │
│                      │ │notes                 │ │status, reason        │
│                      │ └──────────────────────┘ │transferred_at        │
└──────────────────────┘                          └──────────────────────┘

       Core Tables Directly Scoped & Associated via branch_id:
       ───────────────────────────────────────────────────────
       • leads               (branch_id foreign key)
       • deals               (branch_id foreign key)
       • customers           (primary branch_id foreign key)
       • contacts            (branch_id foreign key)
       • projects            (branch_id foreign key)
       • support_tickets     (branch_id foreign key)
       • meetings            (branch_id foreign key)
       • employees           (branch_id foreign key)
       • users               (branch_id foreign key)
       • invoices            (branch_id foreign key)
       • payments            (branch_id foreign key)
       • expenses            (branch_id foreign key)
       • warehouses          (branch_id foreign key)
```

---

## 3. Financial Telemetry & Consolidation Formulas

$$\text{Branch Net Profit} = \text{Branch Invoiced Revenue} - \text{Branch Approved Expenses}$$

$$\text{Branch Collection Rate \%} = \frac{\text{Branch Collected Cash}}{\text{Branch Invoiced Revenue}} \times 100$$

$$\text{Branch Target Achievement \%} = \frac{\text{Branch Achieved Sales}}{\text{Branch Sales Target}} \times 100$$

$$\text{Organization Collection Efficiency \%} = \frac{\sum \text{All Branch Collections}}{\sum \text{All Branch Invoiced Sales}} \times 100$$

---

## 4. REST API Endpoint Catalog

| Method | Endpoint | Description | RBAC / Auth Gate |
|---|---|---|---|
| `GET` | `/api/v1/crm/branches` | List branches with search, city, and active filters | `branches.view` / `branches.view_own` |
| `POST` | `/api/v1/crm/branches` | Create new branch with auto code and departments | `branches.create` |
| `GET` | `/api/v1/crm/branches/{id}` | Get detailed branch profile and entity counts | `branches.view` (scoped) |
| `PUT` | `/api/v1/crm/branches/{id}` | Update branch details and manager | `branches.edit` |
| `DELETE` | `/api/v1/crm/branches/{id}` | Soft delete / deactivate branch | `branches.deactivate` |
| `PATCH` | `/api/v1/crm/branches/{id}/status` | Activate or deactivate branch | `branches.edit` |
| `POST` | `/api/v1/crm/branches/{id}/employees` | Bulk assign employees & users to branch | `branches.assign_employees` |
| `POST` | `/api/v1/crm/branches/transfers` | Transfer lead, staff, project, ticket, meeting | `branches.transfer_records` |
| `GET` | `/api/v1/crm/branches/transfers/history` | Audit log of cross-branch record transfers | `branches.transfer_records` |
| `GET` | `/api/v1/crm/branches/{id}/dashboard` | Branch Manager Dashboard with scoped metrics | `branches.view_reports` |
| `GET` | `/api/v1/crm/branches/analytics/consolidated`| Central Owner multi-branch comparative report | `branches.view_consolidated_reports` |
| `GET` | `/api/v1/crm/branches/{id}/settings` | Get regional operational preferences | `branches.view` |
| `PUT` | `/api/v1/crm/branches/{id}/settings` | Update regional operational preferences | `branches.manage_settings` |
| `GET` | `/api/v1/crm/branches/{id}/sales-targets` | Fetch branch monthly/annual sales targets | `branches.view` |
| `POST` | `/api/v1/crm/branches/{id}/sales-targets` | Save branch monthly/annual sales quota | `branches.edit` |

---

## 5. Verification & Test Coverage Summary

- **Backend Feature Tests (`MultiBranchManagementTest.php`)**:
  - 10 comprehensive feature tests with 58 assertions passed in 10.60s.
  - Zero SQL errors, strict anti-tampering validation, cross-branch 403 prevention, and tenant isolation verified.
- **Frontend-to-Backend Integration Suite (`apiIntegration.test.ts`)**:
  - Step 60: Advanced Multi-Branch, Locations, Transfers & Central Consolidation verified.
  - All 60 integration steps passed (0 failures).
- **Vite & Server Production Build**:
  - `dist/server.cjs` and React production bundle built with 0 errors.
