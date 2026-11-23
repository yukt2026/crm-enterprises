# Facebook Messenger & Instagram Direct Messages Integration Module

## Executive Summary
This document outlines the architecture, implementation, and compliance controls for the **Facebook Messenger & Instagram Direct Messages** omnichannel communication module. Built strictly using official **Meta Graph API v21.0** standards, this module seamlessly connects Facebook Pages and Instagram Professional accounts directly to the existing **WhatsApp CRM** engine and **Unified Omnichannel Inbox**.

Instead of introducing a siloed or disconnected messaging system, this module reuses the core tenant-isolated conversation engine (`whatsapp_conversations`), unified messaging schema (`whatsapp_messages`), AI auto-reply infrastructure (`whatsapp_ai_agents`), and CRM customer linking matrix (`customer_social_identities`).

---

## 1. Key Capabilities & Architectural Highlights

### 1.1 Multi-Tenant Account Connection & OAuth Management
- **Facebook Page Connection**: Connect business Facebook Pages with granular OAuth permission scopes:
  - `pages_messaging`
  - `pages_read_engagement`
  - `pages_manage_metadata`
  - `pages_show_list`
- **Instagram Direct Messages**: Connect Instagram Professional/Business accounts linked to authorized Facebook Pages:
  - `instagram_manage_messages`
  - `instagram_basic`
  - Personal accounts are detected and flagged as ineligible per official Meta API guidelines.
- **Credential Protection**: Page Access Tokens are encrypted with AES-256 at rest in the `social_channel_accounts` table and never exposed to client-side browser bundles.

### 1.2 Webhook Ingestion & Cryptographic Verification
- **Challenge Verification**: Handles Meta's `GET /api/v1/webhooks/meta/social` handshake (`hub.mode=subscribe`, `hub.challenge`, `hub.verify_token`).
- **Timing-Safe HMAC Verification**: Validates incoming `POST` payloads using `hash_equals(hash_hmac('sha256', payload, app_secret), X-Hub-Signature-256)`.
- **Deduplication & Replay Defense**: Inbound messages are deduplicated on Meta's message ID (`mid`). Duplicate deliveries return `200 OK` idempotently without inserting duplicate records or triggering duplicate AI replies.
- **Audit Logging**: All webhook events are logged in `social_webhook_events` with status tracking (`received`, `processed`, `ignored`, `failed`).

### 1.3 Unified Inbox Integration
- **Channels**: Integrated as first-class channels (`facebook` and `instagram`) alongside `whatsapp`, `email`, `sms`, `call`, and `webchat`.
- **Channel Filters & Badges**: Dedicated badges, Meta brand icons, and live conversation counts.
- **Conversation Threading**: Reuses `whatsapp_conversations` with PSID / IGSID mapped to the communication identifier, storing original identifiers in `metadata.psid` and `metadata.igsid`.

### 1.4 Meta 24-Hour Messaging Policy Enforcement
- **Standard Messaging Window**: Enforces Meta's standard 24-hour response window (`window_expires_at = Carbon::now()->addHours(24)` from customer's latest message).
- **Policy Enforcement**: Standard replies outside the 24-hour window are blocked by the backend API (`422 Unprocessable Entity`) with actionable guidance.
- **Approved Message Tags**: Allows outbound replies outside the 24-hour window when tagged with an approved Meta Message Tag:
  - `POST_PURCHASE_UPDATE`
  - `CONFIRMED_EVENT_UPDATE`
  - `ACCOUNT_UPDATE`

### 1.5 Customer Social Identity Resolution & Merging
- **Scoped IDs**: Tracks Page-Scoped IDs (PSID) and Instagram-Scoped IDs (IGSID) in `customer_social_identities`.
- **CRM Linking**: Link social identities to CRM `leads` or `customers` either automatically (by phone/email if provided) or via manual review in the UI.
- **Identity Merging**: Supports merging multiple identities (e.g., a customer's Facebook PSID and Instagram IGSID) into a single consolidated profile with complete audit history.

### 1.6 AI Omnichannel Assistance & Human Handover
- **Configurable AI Agents**: Tenants can bind WhatsApp AI agents to social accounts with channel-specific prompts and handover keywords.
- **Instant Handover**: Inbound messages containing keywords like `human`, `agent`, `representative`, or `support` automatically deactivate AI handling (`is_ai_handled = false`), escalate priority to `high`, and dispatch a human handover notification.

---

## 2. Database Schema

### 2.1 `social_channel_accounts`
| Column | Type | Description |
|---|---|---|
| `id` | BIGINT UNSIGNED | Primary Key |
| `tenant_id` | BIGINT UNSIGNED | Multi-tenant workspace ID (FK to `tenants`) |
| `provider` | ENUM('facebook', 'instagram') | Channel provider |
| `page_id` | VARCHAR(120) | Facebook Page ID |
| `page_name` | VARCHAR(255) | Facebook Page or IG Business Name |
| `page_access_token` | TEXT | Encrypted Meta Page Access Token (AES-256) |
| `instagram_business_id` | VARCHAR(120) NULL | Instagram Professional/Business ID |
| `instagram_username` | VARCHAR(120) NULL | Instagram @handle |
| `is_connected` | BOOLEAN | Connection status |
| `connected_at` | TIMESTAMP NULL | When authorized |
| `token_expires_at` | TIMESTAMP NULL | Token expiry date |
| `webhook_subscribed` | BOOLEAN | Whether webhook fields are subscribed |
| `webhook_verify_token` | VARCHAR(255) | Tenant-specific verification token |
| `permissions` | JSON NULL | Authorized Meta scopes |
| `metadata` | JSON NULL | Category, profile photo, eligibility flags |
| `ai_enabled` | BOOLEAN | Whether automated AI reply is enabled |
| `ai_agent_id` | BIGINT UNSIGNED NULL | Bound AI agent (FK to `whatsapp_ai_agents`) |
| `ai_instructions` | TEXT NULL | Custom channel system prompt override |
| `ai_handover_keywords` | JSON NULL | Trigger words for human escalation |

### 2.2 `customer_social_identities`
| Column | Type | Description |
|---|---|---|
| `id` | BIGINT UNSIGNED | Primary Key |
| `tenant_id` | BIGINT UNSIGNED | Multi-tenant workspace ID (FK to `tenants`) |
| `customer_id` | BIGINT UNSIGNED NULL | Linked CRM Customer ID |
| `lead_id` | BIGINT UNSIGNED NULL | Linked CRM Lead ID |
| `contact_id` | BIGINT UNSIGNED NULL | Linked CRM Contact ID |
| `provider` | ENUM('facebook', 'instagram') | Social channel provider |
| `scoped_id` | VARCHAR(191) | PSID or IGSID |
| `username` | VARCHAR(191) NULL | Public username / handle |
| `display_name` | VARCHAR(255) NULL | Full display name |
| `is_verified` | BOOLEAN | Whether manually verified or matched |
| `merged_with_id` | BIGINT UNSIGNED NULL | Primary identity ID if merged |
| `metadata` | JSON NULL | Profile URL, avatar, telemetry |

### 2.3 `social_webhook_events`
| Column | Type | Description |
|---|---|---|
| `id` | BIGINT UNSIGNED | Primary Key |
| `tenant_id` | BIGINT UNSIGNED NULL | Resolved tenant ID |
| `provider` | ENUM('facebook', 'instagram') | Event provider |
| `page_id` | VARCHAR(120) NULL | Target Facebook Page ID |
| `event_id` | VARCHAR(191) | Meta message ID (`mid`) or receipt ID |
| `event_type` | VARCHAR(80) | `messages`, `deliveries`, `reads`, etc. |
| `payload` | JSON | Raw payload from Meta |
| `status` | ENUM | `received`, `processed`, `ignored`, `failed` |
| `error_message` | TEXT NULL | Failure description if any |

---

## 3. API Endpoints

### 3.1 Public Meta Webhooks
- `GET /api/v1/webhooks/meta/social`: Webhook challenge verification (`hub.challenge`).
- `POST /api/v1/webhooks/meta/social`: Webhook ingestion for Messenger and Instagram messages, delivery receipts, and read receipts.

### 3.2 Tenant Social Messaging Management (Protected)
- `GET /api/v1/crm/social/accounts`: List connected Facebook Pages and Instagram accounts.
- `POST /api/v1/crm/social/accounts`: Connect a new Facebook Page or Instagram Business account.
- `POST /api/v1/crm/social/accounts/{id}/disconnect`: Disconnect an account.
- `POST /api/v1/crm/social/conversations/{id}/messages`: Dispatch outbound reply over Facebook Messenger or Instagram DM (validates 24-hour window and approved tags).
- `GET /api/v1/crm/social/identities`: List customer social identities with linking status.
- `POST /api/v1/crm/social/identities/{id}/link`: Link a social identity to a CRM Lead or Customer.
- `POST /api/v1/crm/social/identities/merge`: Merge a secondary social identity into a primary profile.
- `GET /api/v1/crm/social/logs`: View recent webhook ingestion events.
- `GET /api/v1/crm/social/diagnostics`: Overall Meta health diagnostics and eligibility checks.

---

## 4. Test Verification Summary

### Backend Feature Tests (`SocialMessengerInstagramIntegrationTest.php`)
- **10/10 Tests Passed** (54 assertions):
  1. `test_it_connects_facebook_page_and_instagram_professional_account`
  2. `test_it_verifies_meta_webhook_challenge`
  3. `test_it_handles_incoming_facebook_messenger_webhook_and_creates_conversation`
  4. `test_it_dispatches_outbound_facebook_messenger_reply_within_24h_window`
  5. `test_it_enforces_meta_24h_window_policy`
  6. `test_it_handles_incoming_instagram_dm_webhook_and_outbound_reply`
  7. `test_it_rejects_duplicate_webhook_events_idempotently`
  8. `test_it_triggers_ai_handover_on_social_keywords`
  9. `test_it_links_social_identity_to_crm_lead_and_merges_identities`
  10. `test_it_strictly_enforces_cross_tenant_isolation`

### Frontend Integration Tests (`apiIntegration.test.ts`)
- **Step 43 Passed**: Verified end-to-end Facebook Page and Instagram Account connection, webhook verification, inbound HMAC-signed webhook delivery, conversation creation, identity creation, and diagnostics retrieval.
- **Full Suite**: 44 passed, 0 failed.
- **Production Build**: `vite build` completed successfully in 8.65s without errors.
