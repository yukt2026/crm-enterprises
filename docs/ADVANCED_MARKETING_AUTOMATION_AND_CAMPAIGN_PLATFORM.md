# PROMPT 38: COMPLETE MARKETING AUTOMATION AND CAMPAIGN MANAGEMENT PLATFORM

## 1. Executive Summary & Capabilities

Prompt 38 elevates the Enterprise CRM into a complete, compliance-certified, omnichannel marketing automation platform. Businesses can plan, budget, draft, test, approve, launch, automate, and attribute multi-channel marketing campaigns without leaving the unified CRM interface.

The system natively bridges:
- **Connected Marketing Channels**: WhatsApp Business Cloud API, Amazon SES / SMTP, SMS (DLT TRAI registered gateway), Meta Ads (Facebook & Instagram lead sync), and Google Ads enhanced conversion tracking.
- **Audience Segmentation Engine**: Dynamic criteria builder supporting `leads`, `contacts`, and `customers` with demographic, behavioral, and deal-stage filters, exclusion lists, and real-time count recalculation.
- **Content & Multi-Channel Template Studio**: Meta Cloud API approved templates, rich HTML email composer, India TRAI Content Template ID mapping (`dlt_template_id`), and AI-powered copy drafting with human approval gates.
- **Automated Customer Journeys (Drip Sequences)**: Multi-step workflows with delay intervals, quiet hour protection (09:00 - 20:00 IST), condition check nodes (`replied`, `clicked`, `deal_advanced`), YES/NO branch routing, automatic sales rep assignment (`assign_rep`), and follow-up scheduling.
- **Multi-Tier Approval Workflow**: Governance structure where Marketing Executives create drafts, Marketing Managers review and approve, and High-Budget (> ₹50,000) or High-Audience (> 10,000) campaigns require Organization Admin approval.
- **Compliance, Consent & Suppression Registry**: Explicit channel opt-in records (`marketing_consents`), real-time STOP keyword handler to instantly populate `communication_suppressions`, and frequency capping (e.g. maximum 1 message per recipient every 7 days).
- **Revenue Attribution & ROI Analytics**: Distinct segregation between provider-reported metrics (Delivered, Opens, Clicks, Replies) and CRM business outcomes (Leads Generated, Conversions Won, Attributed Revenue, CPL, CPA, ROI %, and ROAS).

---

## 2. Architecture & Data Model

### 2.1 Database Migrations
Migration: `2026_11_23_000032_create_advanced_marketing_automation_and_campaign_tables.php`

1. **`marketing_campaigns` extensions**:
   - `objective` (`lead_generation`, `retention`, `product_launch`, `festive_promotion`, `re_engagement`)
   - `budget` & `spend_amount`
   - `start_date` & `end_date`
   - `approval_status` (`not_submitted`, `pending_approval`, `approved`, `rejected`)
   - `approved_by_user_id` & `approved_at`
   - `rejection_reason`
   - `assigned_owner_id` & `branch_id`
   - `business_hours_only` (TRAI quiet hours restriction)
   - `frequency_cap_days` (Days between dispatches to identical recipient address)
   - `clicks_count`, `opens_count`, `conversions_count`
   - `cost_per_lead` (CPL) & `cost_per_acquisition` (CPA)
   - `campaign_notes` & `ai_drafted`

2. **`campaign_approvals`**:
   - Immutable audit trail storing `tenant_id`, `campaign_id`, `approver_user_id`, `role_level`, `action` (`requested`, `approved`, `rejected`), `comments`, and timestamps.

3. **`marketing_consents`**:
   - Explicit channel permissions: `entity_type`, `entity_id`, `channel` (`whatsapp`, `email`, `sms`, `voice`, `all`), `consent_status` (`opted_in`, `opted_out`, `pending`), `source`, and `reason`.

4. **`marketing_templates`**:
   - Multi-channel reusable templates with `channel`, `category`, `subject`, `content`, `variables`, `dlt_template_id` (TRAI), and `status`.

5. **`drip_sequences` & `drip_steps` extensions**:
   - `journey_type` (`lead_nurturing`, `welcome_sequence`, `customer_onboarding`, etc.)
   - `step_type` (`send_message`, `wait_delay`, `condition_check`, `assign_rep`, `schedule_followup`)
   - `condition_rules` (JSON storing condition checks and target branch step orders)

---

## 3. Endpoints & API Reference

| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/api/v1/crm/campaigns` | List campaigns with channel, objective, status & approval filters |
| `POST` | `/api/v1/crm/campaigns` | Create campaign draft with budget, quiet hours, and frequency cap |
| `GET` | `/api/v1/crm/campaigns/summary` | Aggregate marketing stats (budget, spend, revenue, ROI %, delivered) |
| `POST` | `/api/v1/crm/campaigns/{id}/submit-approval` | Submit campaign draft for manager review |
| `POST` | `/api/v1/crm/campaigns/{id}/approve` | Approve campaign (enforces admin approval if budget > ₹50,000) |
| `POST` | `/api/v1/crm/campaigns/{id}/reject` | Reject campaign with mandatory feedback reason |
| `POST` | `/api/v1/crm/campaigns/{id}/duplicate` | Duplicate campaign into clean draft with reset counters |
| `POST` | `/api/v1/crm/campaigns/{id}/archive` | Move campaign to archived state |
| `POST` | `/api/v1/crm/campaigns/{id}/dispatch` | Execute campaign broadcast with quiet hour & consent gates |
| `POST` | `/api/v1/crm/campaigns/{id}/test-send` | Send personalized sample to a test recipient address |
| `GET` | `/api/v1/crm/campaigns/{id}/analytics` | Comprehensive provider metrics and CRM revenue attribution |
| `GET` | `/api/v1/crm/marketing/templates` | List reusable WhatsApp, Email and SMS templates |
| `POST` | `/api/v1/crm/marketing/templates` | Create template with TRAI DLT Template ID |
| `GET` | `/api/v1/crm/marketing/templates/{id}/preview` | Render template with sample contact variables |
| `POST` | `/api/v1/crm/marketing/templates/ai-generate` | Generate marketing content using AI assistance |
| `GET` | `/api/v1/crm/marketing/consents` | List customer channel consent preferences |
| `POST` | `/api/v1/crm/marketing/consents` | Record explicit customer opt-in or opt-out |
| `GET` | `/api/v1/crm/marketing/consents/suppressions` | Real-time global suppression and DND list |
| `POST` | `/api/v1/crm/marketing/consents/unsubscribe` | Handle inbound STOP keyword or manual opt-out |

---

## 4. Verification & Testing

### 4.1 Backend PHPUnit Feature Tests
- File: `backend/tests/Feature/EnterpriseMarketingAutomationTest.php`
- **Result**: **9 passed, 0 failed (47 assertions)**
- File: `backend/tests/Feature/MarketingAutomationAndCampaignsTest.php`
- **Result**: **8 passed, 0 failed (32 assertions)**
- **Total Marketing Domain Tests**: **17 passed, 0 failed (79 assertions)**

### 4.2 End-to-End API Integration Suite
- File: `frontend/src/tests/apiIntegration.test.ts` (Step 53)
- **Result**: **53 passed, 0 failed** across all CRM modules.

### 4.3 Frontend Production Build
- Vite production bundle built cleanly in **8.71s** without TypeScript or Rollup errors.
