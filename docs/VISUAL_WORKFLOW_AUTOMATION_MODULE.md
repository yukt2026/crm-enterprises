# Visual Workflow Automation Builder Architecture & Module Specification

## 1. System Overview
The **Visual Workflow Automation Builder** is an enterprise-grade, deterministic, and sandboxed orchestration engine designed to automate end-to-end workflows across CRM, Sales Pipeline, Marketing, Finance/Billing, and Omnichannel Communications.

### Key Architectural Tenets
1. **Zero Arbitrary Code Execution**: No `eval()`, dynamic PHP string evaluation, or user-submitted scripts are permitted. All conditions and actions are declaratively parsed and executed using strict schema validators.
2. **Deterministic Graph Traversal**: Workflows are directed acyclic graphs (DAGs) composed of typed nodes (`trigger`, `condition`, `action`, `delay`, `wait`, `stop`) and directed edges with branching criteria (`true` / `false`).
3. **Idempotency & Duplicate Protection**: Every triggered run checks unique idempotency keys (`{workflow_id}:{trigger_event}:{entity_type}:{entity_id}:{timestamp_minute}`) to prevent duplicate webhook/event executions.
4. **Mandatory Consent & Permission Re-checks**: Even if a customer entered a workflow, any downstream communication action (WhatsApp, Email, SMS, Calling) immediately re-queries real-time tenant suppressions and DND flags before dispatch.
5. **Multi-Tenant State Isolation**: Every workflow, version, run, and step log enforces foreign-keyed `tenant_id` constraints matching authenticated user workspace context.

---

## 2. Database Schema

### `workflows`
- `id` (bigint, PK)
- `tenant_id` (bigint, indexed)
- `name` (string)
- `description` (text, nullable)
- `category` (`crm`, `sales`, `marketing`, `billing`, `support`, `custom`)
- `status` (`draft`, `published`, `paused`)
- `trigger_type` (string: `lead.created`, `deal.stage_changed`, etc.)
- `current_version` (integer, default: 1)
- `is_active` (boolean, default: false)
- `total_runs_count` (integer, default: 0)
- `last_run_at` (datetime, nullable)
- `created_by_user_id` (bigint, nullable)

### `workflow_versions`
- `id` (bigint, PK)
- `tenant_id` (bigint, indexed)
- `workflow_id` (bigint, FK)
- `version` (integer)
- `definition` (json: `{ nodes: [...], edges: [...] }`)
- `changelog` (text, nullable)
- `is_published` (boolean)
- `created_by_user_id` (bigint, nullable)

### `workflow_runs`
- `id` (bigint, PK)
- `tenant_id` (bigint, indexed)
- `workflow_id` (bigint, FK)
- `workflow_version_id` (bigint, FK)
- `trigger_event` (string)
- `idempotency_key` (string, nullable, indexed)
- `entity_type` (string, nullable)
- `entity_id` (bigint, nullable)
- `status` (`pending`, `running`, `waiting`, `completed`, `failed`, `cancelled`)
- `current_node_id` (string, nullable)
- `resume_at` (datetime, nullable)
- `wait_for_event` (string, nullable)
- `wait_timeout_at` (datetime, nullable)
- `trigger_payload` (json)
- `context_data` (json)
- `retry_count` (integer, default: 0)
- `max_retries` (integer, default: 3)
- `error_message` (text, nullable)
- `started_at` (datetime, nullable)
- `completed_at` (datetime, nullable)

### `workflow_step_logs`
- `id` (bigint, PK)
- `tenant_id` (bigint, indexed)
- `workflow_run_id` (bigint, FK)
- `node_id` (string)
- `node_type` (string)
- `node_name` (string)
- `status` (`completed`, `failed`, `waiting`, `skipped`)
- `input_state` (json, nullable)
- `output_state` (json, nullable)
- `branch_taken` (string, nullable: `true` / `false`)
- `error_message` (text, nullable)
- `execution_duration_ms` (integer, default: 0)

### `workflow_templates`
- `id` (bigint, PK)
- `name` (string)
- `category` (string)
- `description` (text)
- `trigger_type` (string)
- `definition` (json)
- `is_system` (boolean, default: true)

---

## 3. Node Types & Graph Architecture

| Node Type | Purpose | Configuration Parameters |
| :--- | :--- | :--- |
| **`trigger`** | Entry point of execution graph | `action_type`: e.g. `lead.created`, `deal.stage_changed`, `whatsapp.message_received` |
| **`condition`** | Evaluates criteria & branches graph | `logical_operator` (`and` / `or`), array of rules (`field`, `operator`, `value`). Outgoing branches: `true` and `false`. |
| **`action`** | Safely mutates CRM or dispatches communication | `action_type`: e.g. `crm.update_lead_status`, `crm.create_task`, `communication.send_whatsapp_template`, `sales.create_deal`, `webhook.send` |
| **`delay`** | Suspends run until specific duration elapses | `duration` (integer), `unit` (`minutes`, `hours`, `days`) |
| **`wait`** | Suspends run until event occurs or timeout elapses | `event` (e.g. `customer.response`), `timeout_hours` (integer) |
| **`stop`** | Terminates current path cleanly | Marks status as completed and halts loop |

---

## 4. Condition Evaluator
Supports recursive evaluation of AND/OR logic rules against entity payload attributes, including:
- Numeric comparisons (`greater_than`, `less_than`, `equals`) on lead score, budget, deal value.
- String comparisons (`equals`, `contains`, `not_equals`, `in`) on lead status, source, city.
- Tag matching (`contains`, `in`) against lead array tags.
- Business hours checks (`is_business_hours`).
- Communication consent checks (`has_opted_in`).

---

## 5. Action Execution Engine
- **`crm.update_lead_status`**: Validates lead exists in current tenant and updates status.
- **`crm.assign_lead`**: Reassigns lead to target team member and logs audit event.
- **`crm.add_tags`**: Merges new categorization tags onto the lead.
- **`crm.create_task`**: Creates actionable task with due date and priority linked to lead.
- **`crm.add_note`**: Appends note to unified timeline.
- **`communication.*`**: Rechecks communication suppressions (DND, bounces, unsubscribes) before dispatching WhatsApp templates, Emails, or SMS.
- **`sales.create_deal`**: Converts or associates opportunity with configured deal pipeline and stage.
- **`webhook.send`**: Safely transmits HTTP POST payload with tenant-specific signatures.

---

## 6. Dry-Run Test Simulation
The engine features a **Zero-Side-Effect Simulator** (`POST /api/v1/crm/workflows/{id}/simulate`).
- Evaluates the provided JSON payload against the full node graph.
- Calculates rule conditions, branch decisions, and mock action responses.
- Generates a step-by-step trace timeline with node durations without executing database inserts or dispatching external messages.

---

## 7. API Reference

| Endpoint | Method | Description |
| :--- | :--- | :--- |
| `/api/v1/crm/workflows` | GET | List tenant workflows with category, status, and search filters |
| `/api/v1/crm/workflows` | POST | Create new workflow definition |
| `/api/v1/crm/workflows/{id}` | GET | Get single workflow with current version definition |
| `/api/v1/crm/workflows/{id}` | PUT | Update workflow metadata or definition (increments version) |
| `/api/v1/crm/workflows/{id}/publish` | POST | Set workflow status to published |
| `/api/v1/crm/workflows/{id}/pause` | POST | Set workflow status to paused |
| `/api/v1/crm/workflows/{id}` | DELETE | Soft delete workflow and associated versions |
| `/api/v1/crm/workflows/{id}/simulate` | POST | Dry-run test simulation on custom JSON payload |
| `/api/v1/crm/workflows/templates` | GET | List pre-built system workflow templates |
| `/api/v1/crm/workflows/import-template`| POST | Clone template into tenant's active workflows |
| `/api/v1/crm/workflow-runs` | GET | List execution runs with status and workflow filters |
| `/api/v1/crm/workflow-runs/{id}` | GET | View execution run details and step logs |
| `/api/v1/crm/workflow-runs/{id}/retry` | POST | Manually retry a failed execution run |
| `/api/v1/crm/workflow-runs/{id}/cancel`| POST | Cancel a waiting/suspended run |
| `/api/v1/crm/workflow-runs/manual-trigger`| POST | Manually dispatch a workflow run |
