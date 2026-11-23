# Visual No-Code Workflow Builder & Complete CRM Automation Engine

## 1. Executive Summary
The **Visual No-Code Workflow Builder and Complete CRM Automation Engine** provides an enterprise-grade automation platform enabling non-technical CRM administrators and operations teams to automate complex business processes across all 9 core CRM modules. The module pairs an interactive drag-and-drop workflow canvas with a multi-layered, fault-tolerant execution engine supporting conditional branching, working-hour schedule rollovers, human approval nodes, idempotency keys, safe dry-run simulations, and loop-protection guardrails.

---

## 2. Core Architecture & Capabilities

```
+---------------------------------------------------------------------------------------------------+
|                                 VISUAL WORKFLOW CANVAS (REACT)                                     |
|  - Drag-and-Drop Nodes: Trigger | Condition | Action | Delay | Approval                           |
|  - Interactive Ports, Edge Routing, Node Config Drawer, Live Mini-Map, Version History & Templates|
+-------------------------------------------------+-------------------------------------------------+
                                                  |
                                                  v
+---------------------------------------------------------------------------------------------------+
|                                     REST API & RBAC LAYER                                         |
|  /api/v1/crm/workflows/*          /api/v1/crm/workflow-runs/*       /api/v1/crm/workflows/approvals|
|  Permissions: test_workflows, publish_workflows, pause_workflows, approve_automation, limits     |
+-------------------------------------------------+-------------------------------------------------+
                                                  |
                                                  v
+---------------------------------------------------------------------------------------------------+
|                                 AUTOMATION EXECUTION ENGINE                                       |
|  - TriggerRegistry: 9 Modules & 45 Business Events (Leads, Deals, Quotes, Invoices, Tickets, HR)  |
|  - ConditionEvaluator: AND/OR Groups, Operators (=, !=, >, <, contains), Business Hours, Consent |
|  - Delay Scheduler: Minutes, Hours, Days, 09:00-18:00 IST Business Hours Rollover (Command Cron) |
|  - Approval Engine: Human Sign-Off (Discount, Refund, Expense), Role Gates, Expiry & Resumption   |
|  - ActionExecutor: 14 Domain Actions (Tasks, Reassign, Status, Notes, Tickets, Email, WhatsApp)   |
|  - Loop Protection: Depth Limit <= 10, Entity Flood Limit <= 10 runs per 15 min                   |
|  - Idempotency & Quota: Deduplication keys, Monthly Execution Limits & Real-Time Monitoring       |
+---------------------------------------------------------------------------------------------------+
```

---

## 3. Database Architecture

### 3.1 Migration: `2026_11_23_000043_create_advanced_visual_workflow_and_automation_engine_tables.php`

1. **`workflow_automation_limits`**:
   - `tenant_id`: Organization scope.
   - `max_active_workflows`: Default 50.
   - `max_monthly_executions`: Default 100,000.
   - `current_month_executions`: Tracked usage counter.
   - `max_external_api_calls`, `max_whatsapp_actions`, `max_email_actions`, `max_sms_actions`, `max_ai_actions`, `max_delayed_tasks`.
   - `last_reset_at`: Monthly reset timestamp.

2. **`workflow_approvals`**:
   - `workflow_run_id`, `workflow_id`, `node_id`.
   - `title`, `category` (discount, refund, expense, campaign, deal_transfer, general).
   - `status`: `pending`, `approved`, `rejected`, `expired`.
   - `approver_role`: Target authority (`sales_manager`, `admin`, `finance_manager`, etc.).
   - `action_type`, `action_params`, `context_summary`.
   - `comments`, `rejection_reason`, `decision_by_user_id`, `decision_at`, `expires_at`.

3. **Schema Enhancements**:
   - `workflow_runs`: Added `parent_run_id`, `depth`, `is_dry_run`, `execution_duration_ms`, `approval_id`.
   - `workflows`: Added `execution_mode`, `timezone`, `working_hours_only`.

---

## 4. Trigger Registry (9 Core Modules & 45 Triggers)

| Module | Sample Events | Payload Context |
| :--- | :--- | :--- |
| **Leads** | `lead.created`, `lead.updated`, `lead.assigned`, `lead.score_changed`, `lead.converted` | `lead_id, score, status, source, branch_id` |
| **Customers** | `customer.created`, `customer.tier_upgraded`, `customer.churn_risk_high`, `customer.tag_added` | `customer_id, segment, health_score, balance` |
| **Sales** | `deal.created`, `deal.stage_changed`, `deal.won`, `deal.lost`, `quote.approved`, `quote.sent` | `deal_id, value, stage, probability, owner_id` |
| **Finance** | `invoice.created`, `invoice.paid`, `payment.received`, `invoice.overdue`, `refund.processed` | `invoice_id, amount, due_date, gateway_ref` |
| **Communication** | `whatsapp.message_received`, `email.opened`, `sms.delivered`, `call.completed` | `sender, message, channel, recording_url` |
| **Support** | `ticket.created`, `ticket.priority_escalated`, `ticket.sla_breached`, `ticket.resolved` | `ticket_id, priority, customer_id, sla_status` |
| **Projects** | `project.created`, `project.milestone_reached`, `task.completed`, `task.overdue` | `project_id, task_id, progress_percent, deadline` |
| **HR** | `employee.joined`, `attendance.check_in`, `leave.approved`, `payroll.batch_approved` | `employee_id, department, check_in_time, net_pay` |
| **Appointments** | `appointment.booked`, `appointment.rescheduled`, `appointment.cancelled` | `booking_id, service_id, slot_time, attendee` |

---

## 5. Condition Evaluator & Delay Engine

### 5.1 Advanced Operators Supported
- **String / Generic**: `equals`, `not_equals`, `contains`, `not_contains`, `starts_with`, `ends_with`, `is_empty`, `is_not_empty`.
- **Numeric**: `greater_than`, `less_than`, `greater_or_equal`, `less_or_equal`, `between`.
- **Date & Time**: `before_date`, `after_date`, `within_days`, `is_today`, `is_in_past`, `is_in_future`.
- **India Compliance & Business Hours**:
  - `in_business_hours`: Evaluates whether execution time falls within standard IST working hours (09:00 - 18:00 IST Monday - Saturday).
  - `has_consent`: Enforces explicit customer opt-in for marketing messages.

### 5.2 Working-Hour Delay Rollover
Delays with "Respect Working Hours" enabled automatically compute the target resumption timestamp. If a delay finishes outside 09:00 - 18:00 IST or on Sunday, it rolls forward to 09:00 AM on the next business day.
- Resumption background worker: `php artisan workflow:process-delays`.

---

## 6. Action Execution Catalog

1. `crm.create_task`: Schedules a prioritized follow-up task with assigned employee and relative due dates.
2. `crm.assign_employee`: Automatically sets or reassigns record ownership.
3. `crm.update_record`: Updates model attributes across Leads, Deals, Tickets, Customers.
4. `crm.add_tags`: Appends organizational tags to entity profiles.
5. `crm.change_status`: Changes lifecycle stage.
6. `crm.create_ticket`: Raises support tickets with SLA category.
7. `crm.create_appointment`: Schedules client calendar meetings.
8. `crm.generate_reminder`: Emits system bell reminders.
9. `comm.send_email`: Dispatches transactional emails.
10. `comm.send_whatsapp`: Sends approved WhatsApp template messages with consent validation.
11. `comm.send_sms`: Sends DLT-compliant SMS notifications.
12. `webhook.post`: Triggers authorized third-party webhook payloads.
13. `approval.request`: Pauses execution and registers an actionable approval item for designated manager roles.
14. `project.create_draft`: Spawns a structured draft project from a won deal.

---

## 7. Reliability, Testing & Guardrails

- **Loop Protection**:
  1. Recursion depth limit $\le 10$ ($>10 \rightarrow \text{status: loop\_detected}$).
  2. Entity flood limit: No more than 10 executions for the same entity within a 15-minute window.
- **Idempotency**: External webhook and API calls support unique `Idempotency-Key` headers to prevent duplicate executions.
- **Dry-Run Simulation**: `POST /api/v1/crm/workflows/{id}/simulate` simulates execution paths, branch traversals, and evaluated conditions without altering production database records or sending external communications.
- **Automated Verification**:
  - Backend Feature Tests: 10/10 passed (`AdvancedWorkflowAutomationEngineTest.php`, 67 assertions). Total test suite: 377 passing tests (2,453 assertions).
  - Frontend E2E Integration: 63/63 steps passed in `apiIntegration.test.ts`.
  - Production Build: Vite + Node bundle compiled cleanly in 10.07s.
