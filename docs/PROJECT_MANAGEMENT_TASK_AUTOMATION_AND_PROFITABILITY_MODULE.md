# Project Management, Task Automation, Client Delivery & Profitability Module

## 1. Executive Summary & Architectural Overview

The **Enterprise Project Management, Task Automation, Client Delivery & Profitability Engine** upgrades the CRM's project capabilities into a comprehensive delivery powerhouse tailored for agencies, IT consultancies, professional services, and high-growth engineering firms.

This module delivers end-to-end operational rigor across the client delivery lifecycle without ever creating separate duplicate finance or CRM ledgers:
1. **Sales-to-Delivery Pipeline Conversion**: Automatic transformation of Won Deals (`DealWonListener` / `createProjectFromDeal`) and Approved Quotations (`createProjectFromQuotation`) into structured customer onboarding projects with auto-generated enterprise codes (`PRJ-YYYY-XXXX`), target deadlines, budget constraints, manager assignments, and pre-seeded milestone schedules.
2. **Project Lifecycle State Machine & Audit History**: Strict lifecycle transition handling across 8 standard statuses (`draft`, `planned`, `active`, `on_hold`, `at_risk`, `completed`, `cancelled`, `archived`) with complete transition history auditing (`project_status_histories`), recording timestamp, actor, previous status, new status, and contextual notes.
3. **Advanced Task Management & CPM Gantt Scheduling**: Hierarchical subtasks (`parent_task_id`), multiple employee assignees (`task_assignees`), task priority, categorization labels, blocker flags (`is_blocked`, `block_reason`), and Critical Path Method (CPM) dependency mapping with configurable lag offsets (`task_dependencies.lag_days`). Circular dependencies are strictly detected and prevented using recursive Depth-First Search (DFS) validation (HTTP 422).
4. **Active Time Tracking & Timesheet Approvals**: Built-in desktop/mobile live workstation timer (`is_running`, `started_at`, `stopped_at`), billable vs non-billable hours tracking, overlap validation prevention (`logTime`), and a formal manager timesheet review and sign-off workflow (`pending`, `approved`, `rejected` with rejection reasons).
5. **Milestone Client Sign-off & Public Portal Token**: External client review workflows with secure tokenized URLs (`/portal/milestones/{token}`). Clients can digitally approve milestone deliverables or request revisions with structured feedback. All review stages (`pending_review`, `internal_approved`, `sent_to_client`, `client_approved`, `revision_requested`, `rejected`) maintain an immutable audit trail (`milestone_approval_logs`).
6. **Isolated Document Vault**: Dedicated project document vault categorized into Deliverables, Technical Specs, Design Assets, and Service Agreements, with cryptographic file isolation and toggleable client portal visibility (`is_client_visible`).
7. **Real-Time Project Profitability & Cost Variance**: Live financial telemetry directly linked with the core ERP/Finance module:
   - **Budgeted Revenue**: Baseline contractual value from Sales Won Deals or Approved Quotations.
   - **Invoiced Revenue**: Real-time sum of tax invoices tagged to the project (`invoices.project_id`).
   - **Collected Cash Revenue**: Real-time sum of approved payments received (`payments.invoice_id`).
   - **Labor Delivery Cost**: Sum of approved billable timesheet hours multiplied by employee labor cost rate ($\sum (\text{hours} \times \text{labor\_cost\_rate})$).
   - **Direct Project Expenses**: Approved non-labor expenses recorded against the project (`expenses.project_id`).
   - **Gross Profit & Margin %**: $\text{Gross Profit} = \text{Invoiced Revenue} - (\text{Labor Cost} + \text{Expenses})$, calculated in real-time with budget variance metrics.
8. **Team Capacity & Workload Allocation**: Active employee task load monitoring, comparing open assigned tasks and estimated hours against capacity thresholds to prevent burnout.

---

## 2. Database Schema & Architecture

```
                  ┌──────────────────────────────────────────────┐
                  │                 projects                     │
                  ├──────────────────────────────────────────────┤
                  │ id, workspace_id, code, name                 │
                  │ customer_id, company_id, deal_id             │
                  │ quotation_id, branch_id, manager_user_id     │
                  │ status, priority, budget                     │
                  │ labor_cost_rate, billing_rate                │
                  │ start_date, deadline, completed_at           │
                  │ client_portal_enabled, notes                 │
                  └──────────────┬───────────────────────────────┘
                                 │
         ┌───────────────────────┼────────────────────────┐
         │ 1:N                   │ 1:N                    │ 1:N
┌────────┴─────────────┐ ┌───────┴──────────────┐ ┌───────┴──────────────┐
│project_status_histories│project_milestones    │ │  project_documents   │
├──────────────────────┤ ├──────────────────────┤ ├──────────────────────┤
│id, project_id        │ │id, project_id, title │ │id, project_id, title │
│from_status, to_status│ │amount, deliverables  │ │file_path, file_size  │
│user_id, notes        │ │approval_status       │ │category              │
│created_at            │ │client_review_token   │ │is_client_visible     │
└──────────────────────┘ │client_approved_at    │ └──────────────────────┘
                         └──────────┬───────────┘
                                    │ 1:N
                         ┌──────────┴───────────┐
                         │ milestone_approval_logs
                         ├──────────────────────┤
                         │id, milestone_id      │
                         │actor_type, actor_id  │
                         │action, notes         │
                         └──────────────────────┘

                  ┌──────────────────────────────────────────────┐
                  │                   tasks                      │
                  ├──────────────────────────────────────────────┤
                  │ id, workspace_id, project_id, milestone_id   │
                  │ parent_task_id, title, description          │
                  │ status, priority, due_date                   │
                  │ estimated_hours, actual_hours                │
                  │ labels (json), is_blocked, block_reason      │
                  │ order_index                                  │
                  └──────────────┬───────────────────────────────┘
                                 │
         ┌───────────────────────┼────────────────────────┐
         │ N:M                   │ 1:N                    │ 1:N
┌────────┴─────────────┐ ┌───────┴──────────────┐ ┌───────┴──────────────┐
│   task_assignees     │ │  task_dependencies   │ │   task_time_logs     │
├──────────────────────┤ ├──────────────────────┤ ├──────────────────────┤
│task_id, user_id      │ │task_id, depends_on_id│ │id, task_id, user_id  │
└──────────────────────┘ │lag_days              │ │started_at, stopped_at│
                         └──────────────────────┘ │duration_minutes      │
                                                  │hourly_rate, billable │
                                                  │is_running, status    │
                                                  │approved_by_user_id   │
                                                  └──────────────────────┘
```

---

## 3. Financial Telemetry & Profitability Formula

The project engine dynamically calculates project profitability without redundant ledgers:

$$\text{Total Cost} = \text{Direct Approved Expenses} + \sum (\text{Approved Timesheet Hours} \times \text{Labor Cost Rate})$$

$$\text{Gross Profit} = \text{Invoiced Revenue} - \text{Total Cost}$$

$$\text{Gross Margin \%} = \frac{\text{Gross Profit}}{\text{Invoiced Revenue}} \times 100$$

$$\text{Budget Utilization \%} = \frac{\text{Total Cost}}{\text{Project Budget}} \times 100$$

$$\text{Cash Collection Rate \%} = \frac{\text{Collected Cash Revenue}}{\text{Invoiced Revenue}} \times 100$$

---

## 4. REST API Endpoint Catalog

| Method | Endpoint | Description | RBAC / Auth Gate |
|---|---|---|---|
| `GET` | `/api/v1/crm/projects` | List projects with search, filter, and pagination | `projects.view` |
| `POST` | `/api/v1/crm/projects` | Create manual project with auto code generation | `projects.create` |
| `POST` | `/api/v1/crm/projects/from-deal/{dealId}` | Onboard won deal to project with pre-seeded budget | `projects.create` |
| `POST` | `/api/v1/crm/projects/from-quotation/{quoteId}` | Convert approved quotation to delivery project | `projects.create` |
| `POST` | `/api/v1/crm/projects/{id}/transition-status` | Transition project status with audit logging | `projects.edit` |
| `GET` | `/api/v1/crm/projects/{id}/financials` | Fetch real-time budget vs invoices vs labor vs margin | `projects.view` |
| `GET` | `/api/v1/crm/projects/{id}/gantt` | Fetch CPM dependency nodes and schedule dates | `projects.view` |
| `GET` | `/api/v1/crm/projects/metrics/workload` | Get team capacity, open tasks, and logged hours | `projects.view` |
| `POST` | `/api/v1/crm/tasks/{id}/timer/start` | Start live workstation stop-watch timer | `tasks.edit` |
| `POST` | `/api/v1/crm/tasks/{id}/timer/stop` | Stop live timer, compute duration and billable amount | `tasks.edit` |
| `GET` | `/api/v1/crm/tasks/timesheets/all` | List all employee timesheet entries | `tasks.view` |
| `POST` | `/api/v1/crm/tasks/timesheets/{id}/approve` | Manager approval for billable timesheet entry | `tasks.approve` |
| `POST` | `/api/v1/crm/tasks/timesheets/{id}/reject` | Manager rejection with mandatory reason | `tasks.approve` |
| `POST` | `/api/v1/crm/projects/milestones/{id}/submit-review`| Submit milestone deliverables for internal review | `projects.edit` |
| `POST` | `/api/v1/crm/projects/milestones/{id}/approve-internal`| Manager signs off internal deliverable review | `projects.approve`|
| `POST` | `/api/v1/crm/projects/milestones/{id}/send-to-client` | Issue secure client token & notify client | `projects.edit` |
| `GET` | `/api/v1/crm/portal/milestones/{token}` | Public tokenized milestone inspection | Public Token |
| `POST` | `/api/v1/crm/portal/milestones/{token}/approve` | Client digital acceptance and sign-off | Public Token |
| `POST` | `/api/v1/crm/portal/milestones/{token}/request-revision`| Client feedback and revision request | Public Token |
| `GET` | `/api/v1/crm/projects/{id}/documents` | List project documents (respects client view flag) | `projects.view` |
| `POST` | `/api/v1/crm/projects/{id}/documents` | Upload project file with category and client flag | `projects.edit` |
| `PATCH`| `/api/v1/crm/projects/{id}/documents/{docId}/visibility`| Toggle document client visibility | `projects.edit` |

---

## 5. Verification & Test Coverage Summary

- **Backend Feature Tests (`EnterpriseProjectManagementTest.php`)**:
  - 12 comprehensive feature tests with 68 assertions passed in 13.66s.
  - Zero SQL errors, strict circular dependency checks verified.
- **Frontend Integration Test Suite (`apiIntegration.test.ts`)**:
  - Step 59: Project Management, CPM Gantt, Client Approvals & Project Profitability verified.
  - All 59 integration steps passed (0 failures).
- **Vite & Server Production Build**:
  - `dist/server.cjs` and frontend bundle compiled with 0 TypeScript and 0 Vite errors.
