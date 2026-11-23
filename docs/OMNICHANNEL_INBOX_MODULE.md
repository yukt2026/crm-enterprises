# Unified Omnichannel Inbox Documentation

## 1. Overview & Architectural Principles

The **Unified Omnichannel Inbox Module** aggregates customer communications across five distinct channels (**WhatsApp Business Cloud API**, **Email**, **SMS**, **Voice Calls**, and **Website Live Chat**) into a unified conversational workspace. Instead of fragmenting conversations across isolated channel databases, it extends the enterprise WhatsApp conversation infrastructure into a polymorphic, multi-channel communication engine.

### Architectural Core Pillars:
1. **Reused Conversation Engine**:
   - `whatsapp_conversations` and `whatsapp_messages` serve as the universal communication spine, enhanced with channel designations (`whatsapp`, `email`, `sms`, `call`, `webchat`), email subject headers, participant identities, and message threading references.
2. **Channel Adapter Architecture (`ChannelAdapterInterface`)**:
   - `EmailChannelAdapter`: SMTP/IMAP and transactional API routing, RFC-compliant email threading (`Message-ID`, `In-Reply-To`, `References`), attachment downloads, and HTML signatures.
   - `SmsChannelAdapter`: Twilio, Exotel, and Fast2SMS carrier gateways, supporting India DLT Entity ID and 6-character Header/Sender ID requirements, segment counter, and delivery callback ingestion.
   - `WebChatChannelAdapter`: Embeddable live chat widget snippet, visitor session token authentication, origin whitelisting, and automated CRM lead capture from pre-chat forms.
   - Standard WhatsApp Business Cloud API & Voice Call records.
3. **Customer Identity Resolution (`IdentityResolutionService`)**:
   - Links conversations to existing CRM `Customer`, `Contact`, or `Lead` records strictly on verified identifiers (exact normalized phone or email).
   - **Protection Against False Merges**: When incoming inquiries match an existing name but have disparate phone numbers or emails, they are **never merged automatically**. They are flagged as `IdentityMergePending` (`status: 'pending_review'`) with calculated confidence scores for manual supervisor review.
4. **Unified Customer Timeline**:
   - Pulls all messages, inbound/outbound voice calls with recordings, internal team notes, and CRM sales activities into an aggregated, chronological customer timeline.
5. **Suppression & Compliance Registry (`CommunicationSuppression`)**:
   - Enforces cross-channel and channel-specific opt-out suppressions (`unsubscribe`, `bounce`, `spam_complaint`, `dnd`) preventing accidental sending to opted-out customers.

---

## 2. Database Schema & Data Models

### A. Extended `whatsapp_conversations`
- `channel`: enum/string (`whatsapp`, `email`, `sms`, `call`, `webchat`), default `'whatsapp'`
- `customer_email`: string nullable, indexed
- `subject`: string nullable (for email threads)
- `channel_account_id`: string nullable (e.g., visitor ID, email account ID)
- `assigned_team_id`: foreignId nullable, references `sales_teams`
- `metadata`: json nullable (e.g. widget ID, browser info, IP address)

### B. Extended `whatsapp_messages`
- `channel`: string nullable
- `subject`: string nullable
- `sender_identity`: string nullable (e.g., sender email, customer phone, agent name)
- `recipient_identity`: string nullable
- `in_reply_to`: string nullable (Message-ID threading)
- `attachments`: json nullable (name, url, size, mime_type)
- `channel_metadata`: json nullable (DLT template ID, provider info)

### C. `email_accounts`
- `id`, `tenant_id`
- `name`, `email_address`, `provider` (`smtp_imap`, `sendgrid`, `mailgun`, `ses`, `gmail_oauth`)
- `smtp_host`, `smtp_port`, `smtp_encryption`, `smtp_username`, `smtp_password` (encrypted)
- `imap_host`, `imap_port`, `imap_encryption`, `imap_username`, `imap_password` (encrypted)
- `signature_html`, `is_active`, `is_default`, `settings`

### D. `sms_configurations`
- `id`, `tenant_id`
- `name`, `provider` (`twilio`, `exotel`, `fast2sms`, `custom_http`)
- `account_sid`, `auth_token` (encrypted), `api_key`
- `sender_id` (6-character DLT Header), `dlt_entity_id`
- `is_active`, `is_default`, `settings`

### E. `website_chat_widgets`
- `id`, `tenant_id`
- `name`, `widget_key` (unique token for `<script data-key="...">`)
- `brand_color`, `brand_title`, `brand_logo_url`, `welcome_message`
- `pre_chat_fields` (name, email, phone, requirement)
- `agent_availability_mode` (`always_ai`, `hybrid`, `business_hours`)
- `is_active`, `allowed_domains`, `settings`

### F. `communication_suppressions`
- `id`, `tenant_id`
- `channel` (`email`, `sms`, `whatsapp`, `all`)
- `identifier` (email or phone)
- `reason` (`unsubscribe`, `bounce`, `spam_complaint`, `dnd`)
- `suppressed_at`

### G. `identity_merges_pending`
- `id`, `tenant_id`
- `primary_type` (`lead`, `contact`, `customer`), `primary_id`
- `secondary_type` (`lead`, `contact`, `customer`), `secondary_id`
- `match_criteria`, `confidence_score` (0-100), `status` (`pending_review`, `merged`, `rejected`)
- `reviewed_by_user_id`, `reviewed_at`, `notes`

---

## 3. API Endpoints

### Authenticated Endpoints (`/api/v1/crm/inbox/`)
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/crm/inbox/conversations` | Filterable conversations list with channel badges & counts |
| `GET` | `/crm/inbox/conversations/{id}` | Full thread with messages, attachments, notes, and profile |
| `POST` | `/crm/inbox/conversations/{id}/messages` | Send message over conversation's native channel |
| `POST` | `/crm/inbox/conversations/{id}/assign` | Reassign to specific user and/or sales team |
| `PATCH` | `/crm/inbox/conversations/{id}/status` | Update status (`open`, `pending`, `resolved`) |
| `POST` | `/crm/inbox/conversations/{id}/toggle-ai` | Switch between AI autonomous reply and human agent takeover |
| `POST` | `/crm/inbox/conversations/{id}/read` | Mark all unread messages as read |
| `POST` | `/crm/inbox/conversations/{id}/notes` | Add team-only internal note with mentions |
| `GET` | `/crm/inbox/timeline` | Unified customer activity timeline across all channels |
| `GET` | `/crm/inbox/channels/email` | List configured email accounts |
| `POST` | `/crm/inbox/channels/email` | Setup or update SMTP/IMAP email account |
| `GET` | `/crm/inbox/channels/sms` | List SMS provider gateways |
| `POST` | `/crm/inbox/channels/sms` | Configure Twilio / Exotel / DLT parameters |
| `GET` | `/crm/inbox/channels/webchat` | List website chat widgets and embed codes |
| `POST` | `/crm/inbox/channels/webchat` | Create or update live chat widget branding |
| `GET` | `/crm/inbox/suppressions` | List active communication suppressions |
| `POST` | `/crm/inbox/suppressions` | Add manual opt-out / suppression entry |
| `DELETE` | `/crm/inbox/suppressions/{id}` | Remove suppression entry |
| `GET` | `/crm/inbox/identity/pending` | List ambiguous identity matches awaiting review |
| `POST` | `/crm/inbox/identity/pending/{id}/review` | Approve merge or reject duplicate proposal |

### Public Live Chat Widget Endpoints (`/api/v1/public/webchat/`)
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/public/webchat/init` | Validates widget key, returns branding & visitor session |
| `POST` | `/public/webchat/send` | Visitor sends message, auto-captures CRM Lead |
| `GET` | `/public/webchat/messages` | Visitor fetches recent transcript |

---

## 4. Frontend Workspace Layout (`OmnichannelInboxView.tsx`)

- **Channel Navigation Ribbon**: One-click switching between All Channels, WhatsApp, Email, SMS, WebChat, and Voice Calls with real-time badge counts.
- **Queue Sub-Filters**: My Conversations, Team Conversations, Unassigned Queue; Open, Pending, and Resolved tabs.
- **Thread List**: Live conversation cards with channel icon badges, unread indicators, AI/Agent tags, customer identity, and relative timestamps.
- **Active Thread Center**:
  - Differentiated message cards per channel (Email with threading & attachments, WhatsApp checkmarks, SMS badges, Webchat visitor tags, Voice Call recordings).
  - Internal team comments view with golden card formatting.
  - Channel-adaptive composer with email subject inputs, SMS character hints, and attachment picker.
- **Context & CRM Sidebar**:
  - **Profile Tab**: Customer / Lead 360 view with one-click user & team reassignment controls.
  - **Timeline Tab**: Unified timeline bringing WhatsApp, Email, SMS, Voice calls (with inline audio player), and system activities together.
  - **Identity Tab**: Supervisor review tool for ambiguous duplicate contacts.
  - **Channel Settings Drawer**: Easy configuration for Email (SMTP/IMAP), SMS gateways, Webchat embed snippets, and opt-out suppressions.
  - **Live Widget Preview Simulator**: Interactive modal demonstrating how the embeddable chat widget renders for website visitors.

---

## 5. Verification & Acceptance Testing

- **Backend Feature Test Suite (`OmnichannelInboxTest.php`)**:
  - `it_supports_channel_filtering_and_counts`: PASSED
  - `it_handles_email_sending_and_threading`: PASSED
  - `it_enforces_communication_suppression_on_sending`: PASSED
  - `it_supports_assignment_and_team_routing`: PASSED
  - `it_keeps_uncertain_customer_matches_pending_review_without_automatic_merging`: PASSED
  - `it_handles_public_webchat_lead_capture_and_transcript`: PASSED
  - `it_aggregates_unified_timeline_across_messages_calls_and_activities`: PASSED
  - `it_strictly_enforces_cross_tenant_isolation_on_conversations`: PASSED
  - Total: **8 passed (46 assertions)**.
- **Complete PHPUnit Suite**: **91 passed (499 assertions)** with zero failures.
- **Frontend Integration Test Suite (`apiIntegration.test.ts`)**: **19 passed, 0 failed**.
- **Frontend Production Build**: Vite build compiled cleanly in 9.70s with **0 errors**.
