# Known Limitations & External Integration Prerequisites

**Document Version:** 1.0.0  
**Status:** Production Audit Disclosure  
**Purpose:** Honest, unambiguous disclosure of third-party external provider prerequisites, architectural boundary conditions, and planned roadmap items.

---

## 1. External Third-Party Integration Credentials Required

While all CRM business logic, webhook verification handlers, simulation sandboxes, and adapter interfaces are fully implemented and verified with automated tests, real-world live traffic to external commercial networks requires tenant or platform provisioning of external vendor credentials:

### 1.1 Meta WhatsApp Business Cloud API
- **Current Status:** Webhook signature verification (`X-Hub-Signature-256`), 24-hour customer service window, template mapping, and conversational message ingestion are fully operational.
- **Production Prerequisite:** Requires a verified Meta Business Account, an approved WhatsApp Business Phone Number ID, and a permanent System User Access Token issued in Meta Business Manager.
- **Limitation Without Keys:** Outbound WhatsApp messaging enters a sandbox simulated mode unless valid Meta Cloud API credentials are provided in `.env` or Tenant Integrations Settings.

### 1.2 Telephony Providers (Twilio & Exotel)
- **Current Status:** Dynamic TwiML and ExoML voice markup generators, DND suppression check, speech-to-text transcript processing, and call duration ledger calculations are fully implemented and tested.
- **Production Prerequisite:** Requires an active Twilio Account SID / Auth Token with purchased virtual numbers, or an Exotel Account SID / API Key / Subdomain.
- **Limitation Without Keys:** Outbound telephonic call dispatch functions in simulated dispatch mode, generating valid XML payloads without placing actual cellular carrier calls.

### 1.3 Google Ads & Meta Lead Ads APIs
- **Current Status:** Lead form ingestion webhook endpoints with HMAC verification, field mapping, and attribution tracking are operational.
- **Production Prerequisite:** Continuous polling or offline conversion uploads to Google Ads require an approved Google Ads Developer Token (Standard or Basic Access) and OAuth client consent screens approved by Google.
- **Limitation Without Keys:** Leads must be captured via incoming webhooks or native CRM landing page forms.

### 1.4 Razorpay Payment Gateway
- **Current Status:** Payment link generation, Razorpay order creation, and HMAC-SHA256 signature verification (`POST /invoices/{id}/verify-payment`) are fully functional.
- **Production Prerequisite:** Requires live merchant activation (`rzp_live_*` key ID and secret) and an active business bank account.
- **Limitation in Sandbox:** In development/staging, test keys (`rzp_test_*`) and simulated checkout flows operate normally.

### 1.5 LLM / AI Providers (OpenAI GPT-4o & Google Gemini 2.0)
- **Current Status:** RAG vector search, prompt injection filtering, tool invocation, and token consumption metering are active.
- **Production Prerequisite:** Requires active OpenAI API key or Google Gemini API key with billing enabled.
- **Fallback Behavior:** If external LLM keys are unavailable, the AI agent builder falls back to rule-based fallback responses and triggers human agent handover.

---

## 2. Architectural Boundaries & Non-Goals

1. **Email SMTP Delivery Throughput:**
   - Transactional emails are dispatched through configured SMTP (SendGrid, Mailgun, Amazon SES). In single-server deployments without an external relay, bulk marketing broadcasts exceeding 50,000 emails per hour should be routed through dedicated marketing relays to protect server IP reputation.
2. **Real-Time Video Conferencing:**
   - The Meetings module manages scheduling, agendas, attendees, calendar invites, and links to external video providers (Google Meet, Zoom, Microsoft Teams). It does not host in-browser WebRTC video grid calls directly within the PHP application server.
3. **Heavy Vector Embeddings On-Host:**
   - Document chunking and semantic embeddings use external provider APIs (e.g. OpenAI `text-embedding-3-small`). Generating high-dimensional transformer embeddings locally on the CPU is intentionally disabled to avoid VPS thread starvation.

---

## 3. Mobile App Packaging Roadmap

1. **Capacitor Android Packaging:**
   - The web frontend is engineered with PWA manifest, service worker offline handling, and native-friendly responsive layouts.
   - Wrapping the frontend with `@capacitor/core` and `@capacitor/android` requires zero modifications to Laravel backend APIs.
2. **Push Notifications:**
   - Web Push notifications work via standard VAPID Web Push APIs in supported Android browsers. Packaging into a standalone Google Play APK will wire Firebase Cloud Messaging (FCM) through Capacitor plugins.
