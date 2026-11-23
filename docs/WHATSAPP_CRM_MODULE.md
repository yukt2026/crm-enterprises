# Official WhatsApp CRM, Team Inbox & Meta Cloud API Module Documentation

## 1. Overview & Architecture

The Official WhatsApp CRM module is built strictly on top of the **Meta WhatsApp Business Platform / Cloud API (v21.0)**. It deliberately rejects and avoids any unofficial browser automation, Puppeteer/Playwright scripts, WebSockets reverse-engineering, or fragile QR-session hacks that violate Meta Terms of Service and risk account bans.

### Core Architectural Principles
1. **Official Cloud API Integration**: Direct integration via Meta Graph API (`https://graph.facebook.com/v21.0/`) for outbound message dispatch and incoming webhooks for real-time bidirectional messaging.
2. **24-Hour Customer Service Window**: Automatic enforcement of Meta's 24-hour customer service window (`window_expires_at = inbound_timestamp + 24 hours`). Outside this window, freeform messages are blocked with an explicit 422 validation error requiring an approved Meta template.
3. **Opt-Out Suppression Compliance**: Built-in opt-out registry (`whatsapp_opt_outs`). Inbound messages with keywords like `STOP`, `UNSUBSCRIBE`, `CANCEL`, `QUIT` immediately flag the contact as opted-out and abort any subsequent automated or manual outbound messages.
4. **Shared Team Inbox with Collaboration**: Multi-agent shared inbox supporting conversation filtering (`all`, `assigned_to_me`, `unassigned`, `open`, `pending`, `resolved`), priority filtering, internal team notes (in post-it style, never sent to customers), AI/human handover switch (`is_ai_handled`), and real-time read/delivery receipts.
5. **Multi-Tenant CRM Data Binding**: Automated E.164 phone normalization that links conversations to existing CRM `Contact`, `Lead`, and `Customer` records, with a 1-click "Create Lead from WhatsApp" conversion action.

```
       +-----------------------+
       |   Meta WhatsApp API   |
       +-----------+-----------+
                   |
         Webhook   |   Outbound Graph API
       HMAC-SHA256 |   (Bearer Token Encrypted)
                   v
       +-----------------------+
       | WhatsAppWebhookService |
       +-----------+-----------+
                   |
         +---------+---------+
         | Deduplication     | (Meta wamid idempotency)
         | Opt-out detection | (STOP, UNSUBSCRIBE)
         | 24h Window reset  | (now + 24 hours)
         | CRM Contact link  | (E.164 auto matching)
         +---------+---------+
                   |
                   v
       +-----------------------+
       | WhatsAppInboxService  | <---> React Shared Inbox UI
       +-----------------------+
```

---

## 2. Database Schema

The module introduces 7 dedicated tables in MySQL (`crm_enterprises`):

### 1. `whatsapp_accounts`
- `id` (bigint unsigned PK)
- `tenant_id` (foreign key -> `tenants`)
- `name` (varchar 191)
- `waba_id` (varchar 191, indexed) – Meta WhatsApp Business Account ID
- `app_id` (varchar 191)
- `system_user_token` (text, encrypted via Laravel `Crypt`)
- `webhook_verify_token` (varchar 191)
- `status` (enum: `pending`, `connected`, `disconnected`, `banned`)
- `business_verification_status` (enum: `unverified`, `pending`, `verified`, `failed`)
- `settings` / `metadata` (json)

### 2. `whatsapp_phone_numbers`
- `id` (bigint unsigned PK)
- `tenant_id`, `whatsapp_account_id` (foreign keys)
- `phone_number_id` (varchar 191, unique per tenant) – Meta Phone Number ID
- `display_phone_number` (varchar 64)
- `normalized_number` (varchar 32, indexed) – E.164 normalized format
- `verified_name` (varchar 191)
- `quality_rating` (varchar 32: `GREEN`, `YELLOW`, `RED`, `UNKNOWN`)
- `status` (varchar 32: `ACTIVE`, `PENDING`, `FLAGGED`, `BLOCKED`)
- `default_assigned_user_id` (foreign key -> `users`, nullable)
- `is_default` (boolean)

### 3. `whatsapp_conversations`
- `id` (bigint unsigned PK)
- `tenant_id`, `whatsapp_phone_number_id`
- `customer_id`, `contact_id`, `lead_id` (foreign keys, nullable)
- `phone_number` (varchar 32, E.164 indexed)
- `customer_name` (varchar 191, nullable)
- `status` (enum: `open`, `pending`, `resolved`, `closed`)
- `is_ai_handled` (boolean, default false)
- `assigned_user_id` (foreign key -> `users`, nullable)
- `priority` (enum: `low`, `normal`, `high`, `urgent`)
- `unread_count` (int unsigned, default 0)
- `window_expires_at` (timestamp, nullable) – Meta 24-hour customer service window
- `last_message_at` (timestamp, nullable)
- `last_message_preview` (varchar 255, nullable)
- `last_message_direction` (enum: `inbound`, `outbound`)
- `tags` (json)

### 4. `whatsapp_messages`
- `id` (bigint unsigned PK)
- `tenant_id`, `conversation_id`
- `provider_message_id` (varchar 191, unique/indexed) – Meta `wamid.HB...`
- `direction` (enum: `inbound`, `outbound`)
- `message_type` (enum: `text`, `image`, `document`, `audio`, `video`, `template`, `interactive`)
- `sender_actor` (enum: `customer`, `agent`, `ai`, `system`)
- `user_id` (foreign key -> `users`, nullable)
- `content` (text, nullable)
- `media_url`, `media_id`, `mime_type`
- `template_name`, `template_params` (json)
- `delivery_status` (enum: `queued`, `sent`, `delivered`, `read`, `failed`)
- `error_code`, `error_message`
- `sent_at`, `delivered_at`, `read_at`
- `raw_payload` (json)

### 5. `whatsapp_conversation_notes`
- `id` (bigint unsigned PK)
- `tenant_id`, `conversation_id`, `user_id`
- `content` (text)
- `mentions` (json, nullable)

### 6. `whatsapp_templates`
- `id` (bigint unsigned PK)
- `tenant_id`, `whatsapp_account_id`
- `template_name` (varchar 191)
- `category` (enum: `AUTHENTICATION`, `MARKETING`, `UTILITY`)
- `language` (varchar 16: `en_US`, `es_ES`, `hi_IN`, etc.)
- `status` (enum: `APPROVED`, `PENDING`, `REJECTED`, `PAUSED`)
- `header_type`, `body_text`, `footer_text`, `buttons` (json)
- `variables_schema` (json)
- `meta_template_id` (varchar 191, nullable)

### 7. `whatsapp_opt_outs`
- `id` (bigint unsigned PK)
- `tenant_id`, `phone_number` (unique index per tenant)
- `opt_out_type` (enum: `stop_keyword`, `manual_agent`, `gdpr_request`)
- `reason` (varchar 255, nullable)
- `opted_out_at` (timestamp)

---

## 3. Webhook Infrastructure & Cryptographic Security

### Webhook Verification Challenge (GET)
Meta sends a GET request to verify the webhook URL with query parameters:
- `hub.mode=subscribe`
- `hub.verify_token=<configured_verify_token>`
- `hub.challenge=<random_string>`

The endpoint `/api/v1/webhooks/whatsapp` returns `hub.challenge` with HTTP 200 plain text when verified, or HTTP 403 on invalid tokens.

### Webhook Payload Verification (POST)
Every incoming webhook contains the header:
```
X-Hub-Signature-256: sha256=<hmac_sha256_hash>
```
The signature is verified against the raw request body using `hash_hmac('sha256', $rawBody, $appSecret)`. Any mismatch or unsigned request is rejected with HTTP 403.

### Idempotency
All incoming message events have a unique Meta `wamid`. The system checks `whatsapp_messages` for `provider_message_id`. Duplicate webhook deliveries from Meta are discarded safely with HTTP 200 without duplicate database entries.

---

## 4. Meta 24-Hour Customer Service Session Rule

Meta enforces that businesses can only respond with freeform messages within **24 hours** of the customer's last incoming message.

In this system:
1. When an inbound message is received:
   ```php
   $conversation->window_expires_at = now()->addHours(24);
   $conversation->save();
   ```
2. When sending an outbound message (`WhatsAppMessagingService::sendMessage`):
   ```php
   if ($messageType !== 'template') {
       if (!$conversation->isWindowActive()) {
           throw ValidationException::withMessages([
               'message' => 'The Meta 24-hour customer service window has expired. You must use an approved Meta template to re-engage this customer.'
           ]);
       }
   }
   ```
3. In the React UI, the composer dynamically reflects the session window state:
   - When active: displays a green badge `24h Session Window Active` with a live countdown timer (`Xh Ym remaining`) and enables freeform text & media messaging.
   - When expired: displays a yellow badge `24h Session Window Expired`, locks freeform text, and provides a direct button to pick an **Approved Template**.

---

## 5. Opt-Out Suppression List

To guarantee privacy and compliance with WhatsApp anti-spam policies:
- Customers can reply `STOP`, `UNSUBSCRIBE`, `CANCEL`, or `QUIT`.
- Inbound webhook processing intercepts these keywords and adds the normalized phone number to `whatsapp_opt_outs`.
- Prior to sending any message or template, `WhatsAppMessagingService` checks:
  ```php
  $isOptedOut = WhatsAppOptOut::where('tenant_id', $tenantId)
      ->where('phone_number', $normalized)
      ->exists();
  if ($isOptedOut) {
      throw ValidationException::withMessages([
          'phone_number' => 'Recipient has opted out of WhatsApp communications.'
      ]);
  }
  ```

---

## 6. Shared WhatsApp Team Inbox (Frontend)

The frontend is implemented in `WhatsAppInboxView.tsx` with a responsive 3-pane layout:

1. **Left Pane – Conversations & Filters**:
   - Filter pills: All, Assigned to Me, Unassigned, Open, Pending, Resolved.
   - Search bar by contact name or phone number.
   - Conversation list displaying unread counters, 24h window status indicators, last message timestamp, and assigned agent avatar.
   - WABA Accounts and Phone Numbers status modal.
2. **Center Pane – Message Thread & Composer**:
   - Header with contact details, phone number, CRM entity link, AI handover toggle switch, and status/priority selectors.
   - Message bubble stream with incoming/outgoing styling, agent sender tags, timestamps, and WhatsApp checkmarks (`sent` single tick, `delivered` double grey tick, `read` double blue tick).
   - Internal post-it note messages displayed distinctly in amber with author details.
   - Active 24-hour window countdown banner.
   - Composer with text input, template picker modal (with dynamic variable inputs `{{1}}`, `{{2}}`), and media attachment modal (URL + caption).
   - Internal Note creation tab allowing agents to leave private notes without alerting the customer.
3. **Right Pane – CRM 360 & Quick Actions**:
   - Full customer context: Contact details, Lead status, Company name.
   - "Convert to CRM Lead" 1-click modal with pre-filled phone number and customer name.
   - Opt-out status badge and manual opt-out toggle.
   - Connected WABA phone number details and quality rating (`GREEN`, `YELLOW`, `RED`).

---

## 7. API Endpoints Reference

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/webhooks/whatsapp` | Meta Webhook challenge verification |
| `POST` | `/api/v1/webhooks/whatsapp` | Meta Webhook events dispatcher (HMAC verified) |
| `GET` | `/api/v1/crm/whatsapp/accounts` | List tenant WABA accounts |
| `POST` | `/api/v1/crm/whatsapp/accounts` | Connect new WABA account |
| `POST` | `/api/v1/crm/whatsapp/accounts/{id}/test` | Test connectivity & token validity against Graph API |
| `GET` | `/api/v1/crm/whatsapp/phone-numbers` | List connected WhatsApp phone numbers |
| `POST` | `/api/v1/crm/whatsapp/phone-numbers` | Register new WhatsApp phone number |
| `GET` | `/api/v1/crm/whatsapp/conversations` | List conversations with team filters & search |
| `GET` | `/api/v1/crm/whatsapp/conversations/{id}` | Get conversation thread & messages |
| `POST` | `/api/v1/crm/whatsapp/conversations/{id}/messages` | Send outbound text or media message (24h checked) |
| `POST` | `/api/v1/crm/whatsapp/conversations/{id}/assign` | Assign conversation to team member |
| `PATCH` | `/api/v1/crm/whatsapp/conversations/{id}/status` | Update conversation status (`open`, `resolved`, etc.) |
| `POST` | `/api/v1/crm/whatsapp/conversations/{id}/notes` | Post private internal team note |
| `POST` | `/api/v1/crm/whatsapp/conversations/{id}/ai-toggle` | Toggle AI vs Human handling mode |
| `POST` | `/api/v1/crm/whatsapp/conversations/{id}/create-lead` | Convert conversation into a CRM Lead |
| `GET` | `/api/v1/crm/whatsapp/templates` | List approved Meta templates |
| `POST` | `/api/v1/crm/whatsapp/templates/send` | Send approved template message with parameters |
| `POST` | `/api/v1/crm/whatsapp/opt-out` | Manually opt-out a phone number |

---

## 8. Automated Test Coverage

The module is verified via `backend/tests/Feature/WhatsAppCloudApiModuleTest.php`:
1. `test_webhook_challenge_verification`: Validates `hub.challenge` echoing and token verification.
2. `test_webhook_cryptographic_signature_verification_and_rejection`: Validates HMAC-SHA256 signature enforcement and HTTP 403 on tampered payload.
3. `test_inbound_message_ingestion_and_automatic_crm_linking`: Tests E.164 normalization, CRM contact linking, conversation creation, and 24h window opening.
4. `test_webhook_idempotency_prevents_duplicate_messages`: Ensures duplicate `wamid` payloads are ignored.
5. `test_24_hour_customer_service_window_enforcement`: Asserts freeform messaging fails with HTTP 422 once the 24-hour window has expired.
6. `test_template_sending_and_variable_mapping`: Validates approved template transmission and `{{1}}` parameter mapping outside the 24-hour window.
7. `test_delivery_status_updates`: Validates webhook receipt callbacks updating `delivered` and `read` statuses with timestamps.
8. `test_opt_out_suppression_enforcement`: Tests keyword opt-out detection (`STOP`) and outbound suppression blocking.
9. `test_internal_team_notes_and_ai_handover`: Tests internal notes creation and AI handover toggle.
10. `test_tenant_isolation_on_whatsapp_crm`: Asserts strict tenant boundaries across conversations and accounts.
