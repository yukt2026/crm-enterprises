# Marketing Automation & Campaign Management Architecture

## 1. Executive Overview

The Marketing Automation & Campaign Management module is a multi-channel outbound communication engine for **Enterprise AI CRM SaaS**. It bridges CRM audiences (leads, contacts, customers) with multi-channel broadcasting (**Meta WhatsApp Cloud API**, **SMTP/SES Email**, and **DLT-Compliant SMS**) and multi-step automated drip journeys.

The architecture emphasizes:
1. **Audience Segmentation**: Dynamic rule-based resolution and static inclusion/exclusion membership with zero data pollution across tenants.
2. **Channel Policy Compliance**: Native support for approved Meta WhatsApp templates, DLT template ID verification for SMS in India, and CAN-SPAM compliant email unsubscribe links.
3. **Strict Consent & Suppression**: Re-verification before dispatching every message against `CommunicationSuppression` and `WhatsAppOptOut`. Opted-out or suppressed recipients are skipped and recorded in the audit trail.
4. **Multi-Step Drip Journeys**: Multi-touch sequences with time intervals (minutes, hours, days), quiet-hours rescheduling (e.g. 21:00-09:00), weekly frequency caps, and exit triggers (conversion, customer reply, opt-out).
5. **Authentic Performance Analytics**: Strict avoidance of fabricated/simulated opens or clicks. All metrics (sent, delivered, read, reply, unsubscribed) reflect provider-verified webhook receipts.

---

## 2. Database Schema

### `marketing_segments`
- `id`: Primary key
- `tenant_id`: Multi-tenant isolation reference
- `name`: Human-readable segment label
- `description`: Segment overview
- `type`: `dynamic` (rule-evaluated on the fly) or `static` (explicit list of IDs)
- `target_entity`: `lead`, `contact`, or `customer`
- `rules`: JSON schema containing criteria (e.g. `status`, `min_lead_score`, `city`, `tags`)
- `inclusion_ids`: JSON array of explicitly included IDs
- `exclusion_ids`: JSON array of explicitly excluded IDs
- `cached_count`: Last calculated audience size
- `last_calculated_at`: Timestamp of last cache refresh
- `created_by_user_id`: Creator reference

### `marketing_campaigns`
- `id`: Primary key
- `tenant_id`: Multi-tenant isolation reference
- `name`: Campaign title
- `description`: Campaign objective
- `channel`: `whatsapp`, `email`, or `sms`
- `type`: `broadcast`, `drip`, or `triggered`
- `status`: `draft`, `scheduled`, `running`, `paused`, `completed`, or `cancelled`
- `segment_id`: Foreign key to `marketing_segments`
- `whatsapp_template_id`: Foreign key to `whatsapp_templates` (Meta approved)
- `whatsapp_account_id`: Foreign key to `whatsapp_accounts`
- `email_account_id`: Foreign key to email configuration
- `sms_config_id`: Foreign key to SMS provider configuration
- `subject`: Email subject or SMS DLT template title
- `content`: Message body with personalization variables (`{{name}}`, `{{company}}`, `{{first_name}}`, `{{city}}`)
- `variable_mappings`: JSON key-value map linking template placeholders to CRM attributes
- `scheduled_at`: Scheduled execution timestamp
- `timezone`: Timezone for execution
- `rate_limit_per_minute`: Throttling threshold (default 120/min)
- `total_recipients_count`: Total evaluated audience
- `sent_count`: Successfully dispatched messages
- `delivered_count`: Verified delivery receipts
- `read_count`: Verified read/open receipts
- `failed_count`: Hard/soft delivery failures
- `replied_count`: Downstream customer responses received
- `unsubscribed_count`: Opt-outs triggered
- `attributed_revenue`: Tracked revenue from won deals converted within attribution window
- `spend_amount`: Direct channel transmission cost
- `started_at`, `completed_at`: Execution lifecycle timestamps

### `campaign_recipients`
- `id`: Primary key
- `tenant_id`: Multi-tenant reference
- `campaign_id`: Foreign key to `marketing_campaigns`
- `entity_type`: `lead`, `contact`, or `customer`
- `entity_id`: Referenced CRM entity ID
- `recipient_address`: Destination phone number or email
- `status`: `queued`, `sent`, `delivered`, `read`, `failed`, or `skipped`
- `skip_reason`: Cause for skipping (e.g., `suppressed_opt_out`, `invalid_format`)
- `error_message`: Provider-returned error diagnostic
- `message_id`: Provider message ID for status tracking
- `sent_at`, `delivered_at`, `read_at`: Status timestamp progression

### `drip_sequences` & `drip_steps`
- `drip_sequences`: Workflow definition including trigger events, quiet-hours boundaries (`quiet_hours_start`, `quiet_hours_end`), frequency cap (`max_messages_per_week`), and automatic exit conditions (`exit_on_conversion`, `exit_on_reply`, `exit_on_unsubscribe`).
- `drip_steps`: Ordered sequence steps with channel, delay interval and unit (`minutes`, `hours`, `days`), templates, and skip conditions.
- `drip_enrollments`: Entity-level progression tracker maintaining `current_step_id`, `next_execution_at`, `last_message_sent_at`, and exit reason.

---

## 3. Core Services

### `SegmentQueryService`
- Dynamically resolves queries across `Leads`, `Contacts`, and `Customers` based on criteria rules:
  - Statuses (`status: ['new', 'contacted']`)
  - Minimum lead scores (`min_lead_score: 25`)
  - Geographic filters (`city: 'Bangalore'`)
  - Tags and custom fields
- Correctly scopes manual inclusion/exclusion lists using parenthesized SQL operator precedence:
  ```php
  $query->where(function (Builder $inner) use ($inclusionIds) {
      // Dynamic rules OR manual inclusions
      $inner->orWhereIn('id', $inclusionIds);
  })->whereNotIn('id', $exclusionIds);
  ```

### `CampaignConsentService`
- Enforces strict compliance before every outbound dispatch:
  1. Checks `CommunicationSuppression` table for globally or channel-suppressed addresses.
  2. For WhatsApp, verifies against `WhatsAppOptOut` table.
  3. Validates address format (E.164 phone format for SMS/WhatsApp, RFC-compliant email).
  4. Returns reasons (`suppressed_opt_out`, `invalid_phone_number`, `invalid_email`) when blocked.

### `CampaignDispatchService`
- Manages end-to-end dispatch execution:
  - Idempotency guard: prevents double-dispatching completed campaigns.
  - Rate limiting & throttling based on `rate_limit_per_minute`.
  - Token personalization: resolves `{{name}}`, `{{first_name}}`, `{{company}}`, `{{city}}` dynamically from recipient record.
  - Dedicated **Test Send Mode**: dispatches single messages with full personalization without incrementing campaign audience totals or revenue attribution metrics.

### `DripSequenceEngine`
- Automates drip lifecycle:
  - Enrolls leads and contacts on matching triggers (`lead_created`, `form_submitted`).
  - Advances steps according to relative delays.
  - Automatically reschedules executions falling within tenant quiet hours (e.g. 21:00 to 09:00).
  - Enforces weekly frequency caps to prevent customer spamming.
  - Auto-exits enrollments upon conversion (deal won / customer converted), inbound customer reply, or unsubscribe.

---

## 4. API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/crm/segments` | List audience segments |
| `POST` | `/api/v1/crm/segments` | Create new segment |
| `POST` | `/api/v1/crm/segments/preview` | Preview audience count for rules |
| `GET` | `/api/v1/crm/segments/{id}/members` | Inspect resolved segment members |
| `DELETE` | `/api/v1/crm/segments/{id}` | Delete segment |
| `GET` | `/api/v1/crm/campaigns` | List campaigns with filters |
| `POST` | `/api/v1/crm/campaigns` | Create marketing campaign |
| `POST` | `/api/v1/crm/campaigns/{id}/dispatch` | Dispatch campaign to segment |
| `POST` | `/api/v1/crm/campaigns/{id}/test-send` | Send personalized test message |
| `POST` | `/api/v1/crm/campaigns/{id}/pause` | Pause running campaign |
| `POST` | `/api/v1/crm/campaigns/{id}/resume` | Resume paused campaign |
| `GET` | `/api/v1/crm/campaigns/{id}/analytics` | Live conversion funnel & ROI metrics |
| `GET` | `/api/v1/crm/drip-sequences` | List drip journeys |
| `POST` | `/api/v1/crm/drip-sequences` | Create drip sequence & steps |
| `POST` | `/api/v1/crm/drip-sequences/{id}/enroll` | Manually enroll entity in sequence |
| `POST` | `/api/v1/crm/drip-sequences/process-due` | Process pending drip steps |

---

## 5. Verification & Testing

- **Backend Feature Tests**: `MarketingAutomationAndCampaignsTest.php` covers dynamic segmentation, manual inclusion/exclusion precedence, broadcast dispatch, strict consent enforcement, test sends, drip journeys, quiet-hour handling, and cross-tenant isolation. (8 passing tests, total suite: 115 passing tests, 615 assertions).
- **Frontend Integration Tests**: Integrated into `apiIntegration.test.ts` (Steps 24, 25, 26).
- **User Interface**: `MarketingCampaignsView.tsx` with tabs for Campaigns, Audience Segments, Drip Sequences, and Live Analytics Funnel.
