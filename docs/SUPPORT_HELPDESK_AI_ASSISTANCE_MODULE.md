# Customer Support, Helpdesk & AI Support Assistance Module

## 1. Executive Summary

The **Customer Support, Helpdesk & AI Support Assistance Module** provides an enterprise omnichannel support desk integrated with the Unified Inbox, CRM Customer 360, Deals, Invoices, and AI Knowledge Base. It empowers support teams with automated ticket number generation, configurable business-hours SLA policies with proactive warning and breach escalations, internal notes vs customer replies, resolution/reopen lifecycles, customer satisfaction (CSAT) ratings, and AI-grounded troubleshooting draft assistance.

---

## 2. Architecture & Data Model

### 2.1 Database Tables & Migrations
Created in `2026_11_23_000018_create_support_helpdesk_enterprise_tables.php`:

1. **`support_tickets`** (Enhanced)
   - `id`, `tenant_id`, `ticket_number` (unique format: `TICK-YYYYMMDD-XXXX`)
   - `customer_id`, `contact_id`, `company_id`
   - `source_channel` (`web_chat`, `whatsapp`, `email`, `phone`, `portal`)
   - `category_id`, `subcategory`
   - `subject`, `description`
   - `priority` (`low`, `medium`, `high`, `urgent`)
   - `status` (`new`, `open`, `in_progress`, `waiting_customer`, `waiting_internal`, `resolved`, `closed`)
   - `assigned_user_id`, `assigned_team_id`
   - `lead_id`, `deal_id`, `invoice_id`
   - `tags` (JSON), `attachments` (JSON)
   - `sla_policy_id`, `first_response_due_at`, `resolution_due_at`, `first_responded_at`, `sla_status` (`within_sla`, `warning`, `breached`)
   - `escalated_to_user_id`, `escalated_at`, `escalation_reason`
   - `resolution_notes`, `closure_reason`, `resolved_at`, `closed_at`
   - `reopen_count`, `reopened_at`, `reopen_reason`
   - `csat_rating` (1–5), `csat_comment`
   - `ai_reply_draft`, `ai_summary`, `ai_troubleshooting_steps`, `ai_suggested_category`, `ai_confidence_score`, `ai_auto_reply_enabled`

2. **`support_ticket_categories`**
   - `id`, `tenant_id`, `name`, `description`, `parent_id` (hierarchical subcategories), `is_active`

3. **`support_sla_policies`**
   - `id`, `tenant_id`, `name`, `description`, `priority` (`all`, `urgent`, `high`, `medium`, `low`)
   - `response_target_minutes`, `resolution_target_minutes`
   - `business_hours_only` (Boolean: default Monday–Friday 09:00–18:00)
   - `escalation_threshold_percent` (Default 80%)
   - `escalate_to_user_id` (Manager to notify/escalate to)
   - `is_default`

4. **`support_ticket_messages`**
   - `id`, `tenant_id`, `ticket_id`
   - `sender_type` (`user`, `customer`, `system`, `ai`), `sender_id`
   - `message` (LongText)
   - `is_internal` (Boolean: `true` for private team notes, `false` for customer-visible messages)
   - `channel` (`email`, `whatsapp`, `web_chat`, `internal`)
   - `attachments` (JSON)
   - `is_ai_generated` (Boolean), `is_draft` (Boolean)

---

## 3. Core Domain Services

### 3.1 `TicketService`
- **Ticket Generation**: Generates sequence numbers (`TICK-YYYYMMDD-XXXX`) and applies matching SLA targets upon creation.
- **Assignment & State Machine**: Transitions statuses appropriately (`new` -> `open` upon assignment; `waiting_customer` upon agent response; `open` when customer replies).
- **Communication Threading**: Segregates internal team notes from customer-facing messages.
- **Resolution & Reopening**: Manages explicit resolution notes, closure reasons, and customer reopen audits (incrementing `reopen_count`).
- **CSAT Rating**: Captures 1–5 star ratings and qualitative customer feedback.
- **Helpdesk Analytics**: Calculates ticket volume, breach rates, avg first response time, avg resolution time, reopen rate, CSAT average, and priority/channel distributions.

### 3.2 `SlaService`
- **Business Hours Target Calculation**: Computes response and resolution due timestamps honoring operational schedules (09:00–18:00, Mon–Fri).
- **Proactive SLA Monitoring & Escalation**:
  - Automatically identifies tickets reaching 80% elapsed SLA (`warning`).
  - Flags overdue tickets as `breached`.
  - Automatically reassigns/escalates tickets to configured managers with audit reasons (`escalated_to_user_id`, `escalated_at`).

### 3.3 `SupportAiService`
- **Knowledge Base Retrieval**: Queries indexed corporate chunks from `WhatsAppKnowledgeChunk` for semantic grounding.
- **Intent & Category Classification**: Analyzes issue subject and description to recommend optimal support categories.
- **Troubleshooting Diagnostics**: Synthesizes relevant technical checkpoints and reproduction steps.
- **Safety Policy Enforcement**:
  - **Drafts by Default**: All AI suggestions are persisted as drafts (`is_draft = true`).
  - **No False Claims**: AI drafts explicitly state the investigation status rather than claiming an issue is fixed without human verification.
  - **Zero Key Leakage**: LLM provider credentials remain strictly isolated on the backend.

---

## 4. API Endpoints

### Support Tickets:
- `GET /api/v1/crm/tickets` - List tickets with filtering (`status`, `priority`, `source_channel`, `sla_status`, `q`, etc.)
- `POST /api/v1/crm/tickets` - Create new support ticket
- `GET /api/v1/crm/tickets/{id}` - Retrieve ticket details, SLA countdown, and message thread
- `PUT /api/v1/crm/tickets/{id}` - Update ticket metadata
- `DELETE /api/v1/crm/tickets/{id}` - Soft-delete ticket
- `POST /api/v1/crm/tickets/{id}/assign` - Reassign ticket to agent / team
- `GET /api/v1/crm/tickets/{id}/messages` - Retrieve message history thread
- `POST /api/v1/crm/tickets/{id}/messages` - Send public reply or internal team note
- `POST /api/v1/crm/tickets/{id}/resolve` - Mark resolved with resolution summary
- `POST /api/v1/crm/tickets/{id}/close` - Close ticket
- `POST /api/v1/crm/tickets/{id}/reopen` - Reopen ticket with customer reason
- `POST /api/v1/crm/tickets/{id}/csat` - Submit CSAT rating (1–5) and comment
- `POST /api/v1/crm/tickets/{id}/ai-assistance` - Generate AI summary, category, diagnostics & draft reply
- `GET /api/v1/crm/tickets/analytics` - Aggregate helpdesk performance KPIs

### Categories & SLAs:
- `GET /api/v1/crm/tickets/categories` - List categories & subcategories
- `POST /api/v1/crm/tickets/categories` - Create category
- `PUT /api/v1/crm/tickets/categories/{id}` - Update category
- `DELETE /api/v1/crm/tickets/categories/{id}` - Delete category
- `GET /api/v1/crm/tickets/sla-policies` - List SLA policies
- `POST /api/v1/crm/tickets/sla-policies` - Create SLA policy
- `PUT /api/v1/crm/tickets/sla-policies/{id}` - Update SLA policy
- `DELETE /api/v1/crm/tickets/sla-policies/{id}` - Delete SLA policy

---

## 5. Verification & Testing

### 5.1 Backend PHPUnit Feature Tests
File: `backend/tests/Feature/SupportTicketHelpdeskTest.php`
- `test_can_create_ticket_with_auto_generated_ticket_number_and_sla_target` :white_check_mark:
- `test_can_assign_ticket_to_agent_and_transition_status` :white_check_mark:
- `test_internal_note_vs_customer_visible_reply_handling` :white_check_mark:
- `test_sla_calculation_evaluates_business_hours_and_escalates_on_breach` :white_check_mark:
- `test_can_generate_ai_assistance_summary_and_draft_reply` :white_check_mark:
- `test_ai_reply_is_strictly_draft_by_default` :white_check_mark:
- `test_can_resolve_and_reopen_ticket_with_audit_trail` :white_check_mark:
- `test_can_record_customer_satisfaction_csat_feedback` :white_check_mark:
- `test_strictly_enforces_cross_tenant_isolation_on_tickets_and_messages` :white_check_mark:

**Result**: 9 passed (52 assertions), 0 failures.

### 5.2 Frontend Integration Suite
File: `frontend/src/tests/apiIntegration.test.ts`
- Verified Step 35: Support Tickets, Categories & SLAs
- **Result**: 36 passed, 0 failed.

### 5.3 Production Build
- Vite production build succeeded cleanly with 0 errors (`npm run build`).
