# Integration Management, OAuth, API Keys & Webhook Platform

## Executive Summary
The **Integration Management, OAuth, API Keys & Webhook Platform** provides an enterprise integration plane connecting CRM Enterprises with external advertising networks, payment gateways, communication APIs, telephony providers, calendar services, and cloud storage.

It enforces encrypted-at-rest credential protection, CSRF-resistant OAuth state exchange, tenant-scoped API keys with permission scopes, cryptographic HMAC SHA-256 outbound webhook dispatching with replay defense, and dynamic OpenAPI 3.0.3 specification endpoints.

---

## 1. System Architecture

```
                                +-----------------------------------+
                                |    Enterprise Integration Hub     |
                                |       (React 19 Frontend)         |
                                +-----------------+-----------------+
                                                  |
                                  REST APIs (Sanctum / TenantContext)
                                                  |
                                                  v
                                +-----------------------------------+
                                |      Integration Controllers      |
                                | - IntegrationController           |
                                | - ApiKeyController                |
                                | - OutboundWebhookController       |
                                | - OpenApiController               |
                                +-----------------+-----------------+
                                                  |
          +---------------------------------------+---------------------------------------+
          |                                       |                                       |
          v                                       v                                       v
+-----------------------+               +-----------------------+               +-----------------------+
|  IntegrationService   |               |     ApiKeyService     |               | OutboundWebhookService|
| - Directory Catalog   |               | - Secret Generation   |               | - HTTPS Destination   |
| - CSRF OAuth State    |               | - SHA-256 Hash Lookup |               | - HMAC SHA-256 Signing|
| - Token Exchange      |               | - Granular Scopes     |               | - X-CRM-Signature     |
| - Credential Encrypt  |               | - Key Rotation        |               | - Replay Protection   |
| - Sync Execution Logs |               | - Audit Request Logs  |               | - Retry / Redelivery  |
+-----------+-----------+               +-----------------------+               +-----------------------+
            |
            v
+-----------------------+
|  IntegrationRegistry  |
| - Google Ads          |
| - Meta Ads / CAPI     |
| - Razorpay Gateway    |
| - Google Analytics 4  |
| - Google Forms        |
| - WhatsApp Cloud API  |
| - Email (SMTP / SES)  |
| - SMS (Twilio / DLT)  |
| - Telephony (Exotel)  |
| - Google Calendar     |
| - Cloud Storage (S3)  |
| - Custom Adapter      |
+-----------------------+
```

---

## 2. Supported Integrations Directory

| Provider | Category | Auth Type | Supported Capabilities |
|---|---|---|---|
| **Google Ads** | Advertising | OAuth2 | Campaign metrics sync, ad spend attribution, offline conversion import, Customer Match audiences. |
| **Meta Ads / Instagram** | Advertising | OAuth2 | Real-time Lead Ads ingestion, Conversions API (CAPI), ad set spend, custom audience sync. |
| **Razorpay** | Payments | API Credentials | UPI & Card payment links, order reconciliation, automated webhook verification, refund processing. |
| **Google Analytics 4** | Analytics | Credentials / API | Measurement Protocol server ingestion, UTM attribution, user ID cross-device stitching. |
| **Google Forms** | Marketing | Webhook / OAuth | Real-time form submission capture, automated CRM lead creation, dynamic field mapping. |
| **WhatsApp Business Cloud API** | Communication | System Token | Interactive button templates, 2-way team chat, AI agent responses, 24h window compliance. |
| **Email (SMTP / SES / SendGrid)** | Communication | Credentials | Transactional emails, marketing drip broadcasts, DKIM/SPF verification, open/bounce tracking. |
| **SMS (Twilio / India DLT)** | Communication | API Credentials | High-priority SMS alerts, OTP dispatch, India TRAI/DLT template compliance, delivery receipts. |
| **Telephony (Exotel / Twilio Voice)**| Telephony | API Credentials | Inbound IVR call queues, AI voice agent streaming, call recording, India virtual numbers. |
| **Google & Outlook Calendar** | Productivity | OAuth2 | Two-way meeting synchronization, automated video meeting links (Google Meet/Teams), RSVPs. |
| **Cloud Object Storage (AWS S3 / R2)**| Storage | Access Keys | Pre-signed secure asset downloads, invoice PDF archives, call recording audio storage, daily backups.|
| **Custom Webhook Adapter** | Developer | Custom HTTP | Proprietary enterprise ERP connectivity, custom REST endpoints, payload schema mapping. |

---

## 3. Database Schema & Data Models

### 3.1 `tenant_integrations`
- `id` (bigint, PK)
- `tenant_id` (bigint, indexed, FK to tenants)
- `provider` (string: `google_ads`, `meta_ads`, `razorpay`, etc.)
- `name` (string)
- `status` (string: `connected`, `disconnected`, `expired`, `error`)
- `auth_type` (string: `oauth2`, `api_key`, `credentials`, `webhook_only`)
- `encrypted_credentials` (encrypted text: client secrets, API keys, access/refresh tokens)
- `scopes` (json: granted scopes)
- `metadata` (json: account IDs, external email, merchant IDs)
- `connected_by_user_id` (bigint, nullable)
- `connected_at` (timestamp)
- `last_sync_at`, `next_sync_at` (timestamp, nullable)
- `last_error` (text, nullable)
- `is_sync_enabled` (boolean, default true)
- `sync_frequency_minutes` (integer, default 60)

### 3.2 `integration_sync_logs`
- `id` (bigint, PK)
- `tenant_id` (bigint, indexed)
- `integration_id` (bigint, FK to tenant_integrations)
- `sync_type` (`full`, `incremental`, `webhook_triggered`, `manual`)
- `status` (`pending`, `running`, `success`, `failed`, `partial`)
- `items_processed` (integer)
- `items_failed` (integer)
- `error_details` (text, nullable)
- `duration_ms` (integer)
- `started_at`, `completed_at` (timestamp)

### 3.3 `tenant_api_keys`
- `id` (bigint, PK)
- `tenant_id` (bigint, indexed)
- `user_id` (bigint, FK to users)
- `name` (string)
- `key_prefix` (string: unmasked 15 characters, e.g. `crm_live_abc123`)
- `key_hash` (string: sha256 hash of entire key)
- `encrypted_secret` (encrypted text: for one-time developer reveal)
- `scopes` (json: array of granted permissions)
- `allowed_ips` (json, nullable)
- `rate_limit_per_minute` (integer, default 60)
- `expires_at`, `last_used_at`, `last_used_ip`
- `is_revoked` (boolean), `revoked_at` (timestamp)

### 3.4 `outbound_webhooks` & `webhook_delivery_logs`
- **Subscription Config:** `url` (strictly validated HTTPS), `signing_secret` (`whsec_...`), `events` array, `retry_limit` (default 5), `timeout_seconds`.
- **Delivery Log:** `event_id` (UUID replay protection), `event_type`, `payload` (JSON), `status` (`success`, `failed`, `retrying`), `response_status_code`, `response_body`, `delivered_at`, `next_retry_at`.

---

## 4. Cryptographic Security & Signatures

### Credential Protection
1. **At-Rest Encryption:** Handled via Laravel's `encrypted:array` attribute casting using AES-256-CBC cipher.
2. **Secrets Masking:** Model appends `masked_credentials` which replaces internal secrets with `••••••••`, guaranteeing raw credentials never reach client browsers.
3. **CSRF State Validation:** `OAuthState` tokens expire in 15 minutes and can only be consumed once.

### Outbound Webhook Verification
Every outgoing webhook request includes:
- `X-CRM-Signature: t={timestamp},v1={hmac_sha256}`
- `X-CRM-Timestamp: {timestamp}`
- `X-CRM-Event-Id: {uuid}`
- `X-CRM-Delivery-Attempt: {attempt_number}`

**Signature Calculation:**
$$HMAC = \text{hash\_hmac}('sha256', timestamp + '.' + payloadJson, signingSecret)$$

**Replay Attack Defense:**
The webhook receiver verifies that $|current\_time - timestamp| \le 300\text{ seconds}$ before verifying cryptographic signature equivalence using `hash_equals()`.

---

## 5. API Reference

All routes require `auth:sanctum` and enforce tenant scoping:

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/crm/integrations` | Returns integration catalog with tenant connection status and masked credentials. |
| `POST` | `/api/v1/crm/integrations/oauth-url` | Generates secure OAuth authorization URL with CSRF state token. |
| `POST` | `/api/v1/crm/integrations/oauth-callback` | Validates OAuth state token, exchanges code, and saves encrypted credentials. |
| `POST` | `/api/v1/crm/integrations/connect-credentials` | Connects credential-based integrations (Razorpay, SMTP, S3). |
| `POST` | `/api/v1/crm/integrations/{id}/disconnect` | Revokes external token and marks integration disconnected. |
| `POST` | `/api/v1/crm/integrations/{id}/test` | Runs live connectivity and latency diagnostics. |
| `POST` | `/api/v1/crm/integrations/{id}/sync` | Triggers manual background synchronization and records execution log. |
| `GET` | `/api/v1/crm/integrations/{id}/sync-logs` | Retrieves synchronization history and error details. |
| `GET` | `/api/v1/crm/api-keys` | Lists active API keys (secrets masked). |
| `POST` | `/api/v1/crm/api-keys` | Generates a new API key; returns raw secret only once. |
| `POST` | `/api/v1/crm/api-keys/{id}/rotate` | Revokes existing key and generates replacement with identical scopes. |
| `DELETE`| `/api/v1/crm/api-keys/{id}` | Immediately revokes an API key. |
| `GET` | `/api/v1/crm/api-keys/logs` | Retrieves API key request audit trail. |
| `GET` | `/api/v1/crm/webhooks` | Lists outbound webhook subscriptions. |
| `POST` | `/api/v1/crm/webhooks` | Registers an HTTPS outbound webhook endpoint with HMAC secret. |
| `PUT` | `/api/v1/crm/webhooks/{id}` | Updates webhook URL, active state, or subscribed events. |
| `DELETE`| `/api/v1/crm/webhooks/{id}` | Deletes a webhook subscription. |
| `POST` | `/api/v1/crm/webhooks/{id}/test-ping` | Dispatches a test ping event to destination URL. |
| `GET` | `/api/v1/crm/webhooks/logs` | Retrieves webhook delivery attempt logs. |
| `POST` | `/api/v1/crm/webhooks/logs/{id}/redeliver` | Manually re-dispatches a failed delivery event. |
| `GET` | `/api/v1/crm/docs/openapi.json` | Returns complete OpenAPI 3.0.3 specification JSON. |

---

## 6. Verification & Acceptance Testing

- **Backend Feature Tests (`IntegrationManagementPlatformTest.php`):**
  - `test_integrations_directory_returns_supported_providers_and_statuses`: Passed.
  - `test_oauth_state_generation_and_csrf_validation`: Passed.
  - `test_credential_encryption_and_secrets_masking`: Passed.
  - `test_integration_sync_execution_and_log_recording`: Passed.
  - `test_tenant_api_key_lifecycle_scope_enforcement_and_rotation`: Passed.
  - `test_outbound_webhook_hmac_signature_generation_and_verification`: Passed.
  - `test_openapi_documentation_endpoint_returns_valid_spec`: Passed.
  - `test_strict_cross_tenant_isolation_on_integrations_keys_and_webhooks`: Passed.
  - Full backend suite status: **158 passed tests (939 assertions), 0 failed**.
- **Frontend Integration Test (`apiIntegration.test.ts`):**
  - Step 37 testing integrations, API keys, webhooks, and OpenAPI specification passed.
  - Full frontend suite: **38 passed, 0 failed**.
- **Production Build:**
  - `npm.cmd run build`: Vite v6.4.3 production bundle built cleanly in 10.40s with zero errors.
