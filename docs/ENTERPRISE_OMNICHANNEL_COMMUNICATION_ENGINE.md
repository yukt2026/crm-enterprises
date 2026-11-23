# Enterprise Omnichannel Communication Engine & Unified Inbox

## 1. Executive Summary

The **Enterprise Omnichannel Communication Engine** extends the existing React 19 + TypeScript frontend and Laravel 12 REST API backend into a unified, high-throughput communication platform connecting all 8 enterprise channels:

1. **WhatsApp Business Cloud API**
2. **Facebook Messenger Platform**
3. **Instagram Direct Messages**
4. **Email (SMTP / IMAP / Webhook ingestion)**
5. **SMS (Twilio / Exotel / Fast2SMS with India DLT Entity ID)**
6. **Website Live Chat (Embeddable widget with AI & human hybrid fallback)**
7. **Human Phone Calls**
8. **AI Voice Calling (Inbound / Outbound telephony with Barge-In and Summary)**

The architecture strictly adheres to tenant isolation, reuses the existing conversation engine (`whatsapp_conversations` and `whatsapp_messages`), incorporates first response and resolution SLA tracking, workload-aware round-robin routing, legal consent and opt-out suppression verification, quick replies (canned responses), and centralized omnichannel communication analytics.

---

## 2. Omnichannel Architecture & Data Model

### 2.1 Unified Conversation Storage
All 8 channels share the same thread ID space and message architecture without maintaining separate disconnected inboxes:
- **`whatsapp_conversations`**:
  - `tenant_id` (Scoped multi-tenancy)
  - `channel` (`whatsapp`, `facebook`, `instagram`, `email`, `sms`, `webchat`, `call`)
  - `phone_number` / `customer_email` / `customer_name`
  - `status` (`open`, `pending`, `resolved`)
  - `priority` (`low`, `medium`, `high`, `urgent`)
  - `unread_count`
  - `first_response_at` & `first_response_time_seconds` (First response SLA)
  - `resolved_at` & `resolution_time_seconds` (Resolution SLA)
  - `last_actor_type` (`customer`, `human`, `ai`, `automation`, `system`)
  - `window_expires_at` (Meta 24-hour customer service window)
- **`whatsapp_messages`**:
  - `channel`
  - `direction` (`inbound`, `outbound`)
  - `sender_actor` (`customer`, `human`, `ai`, `automation`, `system`)
  - `sender_identity` & `recipient_identity`
  - `delivery_status` (`pending`, `sent`, `delivered`, `read`, `failed`)
  - `attachments` & `media_url`

### 2.2 Assignment Audit Trail (`conversation_assignment_logs`)
Maintains an immutable record of every assignment, transfer, and escalation:
- `conversation_id`, `assigned_by_user_id`, `assigned_to_user_id`, `assigned_to_team_id`
- `routing_method` (`manual`, `round_robin`, `workload_aware`, `rule_based`, `handover`)
- `reason`

### 2.3 Canned Responses (`omnichannel_quick_replies`)
Template storage for accelerated agent replies:
- `channel` (`all` or specific channel)
- `shortcut` (e.g. `/hours`, `/pricing`, `/support`)
- `title` & `content`
- `tags` & `is_shared`

### 2.4 Customer Communication Consents (`customer_communication_consents`)
Channel-level legal consent and opt-out registry:
- `channel`, `destination` (Phone / Email / Social Handle)
- `consent_status` (`opted_in`, `opted_out`, `pending`, `revoked`)
- `consent_source` (`form`, `verbal`, `direct_message`, `admin`, `unsubscribe_link`)
- `is_marketing_allowed` & `is_transactional_allowed`

---

## 3. Key Operational Capabilities

### 3.1 Workload-Aware Round Robin Routing
When auto-assign is triggered (`POST /api/v1/crm/inbox/conversations/{id}/auto-assign`):
1. Evaluates all eligible agents belonging to the tenant or assigned sales team.
2. Dynamically counts the number of active `open` conversations for each agent:
   ```sql
   SELECT COUNT(*) FROM whatsapp_conversations WHERE assigned_user_id = user.id AND status = 'open'
   ```
3. Assigns the thread to the agent with the lowest active workload.
4. Generates a timestamped `conversation_assignment_logs` entry with reason: `"Auto assigned via workload-aware round robin (Workload: X active)"`.

### 3.2 SLA Tracking (First Response & Resolution)
- **First Response Time (FRT)**:
  When the first non-customer reply (`human`, `ai`, `automation`) is sent on an open thread, the engine calculates:
  $$\text{FRT} = \max(0, \text{now}() - \text{conversation.created\_at})$$
  Persists `first_response_at` and `first_response_time_seconds`.
- **Resolution Time**:
  When status is updated to `resolved`, the engine calculates:
  $$\text{Resolution Time} = \max(0, \text{now}() - \text{conversation.created\_at})$$
  Persists `resolved_at` and `resolution_time_seconds`.

### 3.3 Consent & Suppression Enforcement
Prior to outbound dispatch on any channel:
1. Re-verifies `CommunicationSuppression`: checks if customer destination is present in suppression registry.
2. Re-verifies `CustomerCommunicationConsent`: checks if customer destination has opted out or revoked consent for the target channel.
3. If opted out, the send is blocked and returns HTTP 422:
   `"Outbound message blocked. Destination is suppressed on channel due to an opt-out/DND request."`

### 3.4 Cross-Channel Customer 360 Timeline
Aggregates interactions chronologically across all touchpoints:
- Inbound & outbound messages (WhatsApp, Facebook, Instagram, Email, SMS, WebChat)
- Voice calls with audio player for `recording_url`, duration, disposition, and AI summary
- Internal comments and agent notes
- CRM lifecycle activities (assignments, status updates, AI handovers)

### 3.5 Omnichannel Communication Analytics
Centralized metrics reporting endpoint (`GET /api/v1/crm/inbox/analytics`):
- Total conversations & channel distribution percentages
- Message volume (inbound, outbound, total)
- First response time average (seconds & minutes)
- Resolution time average (seconds & minutes)
- Active agent workload (open vs resolved per agent)
- AI agent handling vs human agent takeovers
- Voice call metrics (total, completed, missed/failed, average duration)
- Delivery rates (sent, delivered, read, failed)
- Estimated provider costs (telephony minutes + SMS segments in USD)

---

## 4. REST API Endpoint Reference

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/crm/inbox/conversations` | List conversation threads with channel counts |
| `GET` | `/api/v1/crm/inbox/conversations/{id}` | Full thread with messages, notes & assignment logs |
| `POST` | `/api/v1/crm/inbox/conversations/{id}/messages` | Outbound message dispatch with consent check |
| `POST` | `/api/v1/crm/inbox/conversations/{id}/assign` | Manual assignment or team transfer |
| `POST` | `/api/v1/crm/inbox/conversations/{id}/auto-assign` | Workload-aware round-robin auto-assignment |
| `GET` | `/api/v1/crm/inbox/conversations/{id}/assignment-history` | Audit trail of all previous assignments |
| `PATCH` | `/api/v1/crm/inbox/conversations/{id}/status` | Status change (`open`, `pending`, `resolved`) with SLA |
| `POST` | `/api/v1/crm/inbox/conversations/{id}/toggle-ai` | AI handover toggle |
| `POST` | `/api/v1/crm/inbox/conversations/{id}/typing` | Dispatch live typing indicators |
| `GET` | `/api/v1/crm/inbox/timeline` | Cross-channel customer timeline stream |
| `GET` | `/api/v1/crm/inbox/analytics` | Real omnichannel communication metrics & KPIs |
| `GET` | `/api/v1/crm/inbox/consents` | Customer communication consent registry |
| `POST` | `/api/v1/crm/inbox/consents` | Update customer opt-in / opt-out preferences |
| `GET` | `/api/v1/crm/inbox/quick-replies` | Get canned responses by channel |
| `POST` | `/api/v1/crm/inbox/quick-replies` | Create canned response with shortcut |
| `DELETE` | `/api/v1/crm/inbox/quick-replies/{id}` | Delete canned response |

---

## 5. Verification Results

### Backend Feature Tests (`php artisan test`)
- `OmnichannelCommunicationEngineTest`: **8 passed, 0 failed (69 assertions)**
- Full Backend Test Suite: **210 passed, 0 failed (1,397 assertions)**

### Frontend Integration Tests (`npm run test:integration`)
- Total Integration Steps: **45 passed, 0 failed**
- Step 44 Verified:
  - Conversations list & channel count calculation
  - Workload-aware round-robin auto-assign
  - Assignment history audit trail
  - Canned response quick reply creation & deletion
  - Customer communication consent registry
  - Omnichannel analytics live metrics schema

### Production Build (`npm run build`)
- Vite production build: **Passed in 8.24s**
