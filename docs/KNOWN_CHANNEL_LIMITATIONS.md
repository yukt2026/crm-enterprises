# Known Channel Limitations & Regulatory Constraints

This document outlines the operational boundaries, provider rate limits, regulatory rules, and architectural constraints across all 8 supported communication channels.

---

## 1. Meta 24-Hour Messaging Window & Policies (WhatsApp, Facebook, Instagram)

### The 24-Hour Customer Care Window
Under Meta platform policies:
- A business may send free-form messages to a user only within **24 hours** of the user's most recent incoming message.
- Once this 24-hour window expires:
  - **WhatsApp**: The business cannot send regular text messages. It must use a pre-approved, paid **Template Message** (e.g. Utility, Authentication, or Marketing).
  - **Facebook Messenger**: The business can only send outbound messages if accompanied by an authorized **Message Tag** (`POST_PURCHASE_UPDATE`, `CONFIRMED_EVENT_UPDATE`, `ACCOUNT_UPDATE`) or if granted the extended **Human Agent Tag** (allows 7-day manual human response). Sending marketing or promotional content under message tags violates Meta terms and leads to page suspension.
  - **Instagram Direct Messages**: No promotional outbound messages are permitted outside the 24-hour window. Only approved standard response flows are permitted.

### WhatsApp Account Tiers & Messaging Limits
- Unverified WhatsApp Business Accounts start at **Tier 1** (1,000 unique business-initiated conversations per rolling 24 hours).
- Automatic tier upgrades to 10k, 100k, and unlimited require sustained high quality ratings and Meta Business Verification.
- Template submissions are subject to automated and human review by Meta, which can take between 5 minutes and 24 hours.

---

## 2. SMS Constraints & Telecommunications Regulations

### Character Segmentation & Encoding Costs
- **GSM-7 Standard**: Messages using standard GSM characters allow **160 characters** for single-segment SMS. Multi-part messages are segmented at 153 characters per part.
- **UCS-2 (Unicode / Emojis)**: Including even a single emoji or non-GSM character switches the entire SMS to UCS-2 encoding, reducing single-segment capacity to **70 characters** (or 67 characters per segment for multi-part messages). This significantly increases provider billing costs.

### India TRAI DLT Compliance
- Under TRAI regulations in India:
  - Every enterprise sender must have a registered Principal Entity ID (PE ID) on an Indian telecom operator portal (Jio, Airtel, Vodafone).
  - Every outbound SMS template (Transactional, Service, or Promotional) must have an approved Template ID.
  - The outbound SMS payload must include registered Sender Headers and exact variable formats; deviations will result in immediate carrier drop (`ERR_DLT_MISMATCH`).

### US A2P 10DLC Regulations
- All application-to-person (A2P) messaging over standard 10-digit local numbers to US phone numbers requires registered Brand and Campaign IDs through The Campaign Registry (TCR) to prevent carrier spam filtering and surcharges.

---

## 3. Email Ingestion & Deliverability

### Domain Authentication (SPF, DKIM, DMARC)
- Custom domains used for outbound business emailing must have valid SPF, DKIM, and DMARC TXT records published in DNS.
- Sending from unauthenticated domains or shared IP pools risks immediate classification into spam/junk folders by Gmail, Yahoo, and Microsoft 365.

### Webhook vs IMAP Latency
- Inbound email ingestion via webhook (SendGrid Inbound Parse or Postmark) delivers real-time message arrival (< 2 seconds).
- Inbound email ingestion via IMAP polling operates on a scheduled cron cadence (default 2-5 minutes), introducing slight latency before new email threads reflect in the CRM Unified Inbox.

---

## 4. Voice Telephony & AI Calling

### Latency Budget in Voice AI Conversations
- Conversational voice AI requires low latency between the user stopping speech and the AI commencing audio playback:
  - Speech-to-Text (STT): 250ms - 400ms
  - LLM Token Generation: 300ms - 600ms
  - Text-to-Speech (TTS): 200ms - 350ms
  - Network round-trip: 100ms - 200ms
- Total response latency should remain under 1.2 to 1.5 seconds to feel natural. Slower connections or heavy LLM reasoning models can cause perceptible pauses.

### Compliance & Consent (TCPA, NDNC)
- Automated outbound AI voice calls to mobile numbers require explicit prior written consent under the US TCPA (Telephone Consumer Protection Act) and adherence to National Do Not Call (NDNC) registries.
- The CRM automatically checks `customer_communication_consents` and rejects unconsented marketing dials.

---

## 5. Webhook Timeouts & Asynchronous Ingestion

- Meta, Twilio, and Stripe require incoming webhook endpoints to acknowledge receipt with an HTTP `200 OK` within **5 to 10 seconds**.
- Synchronous processing (such as executing LLM calls or downloading large media attachments) directly inside the webhook HTTP turn will cause request timeouts and webhook retry storms.
- **Architectural Solution**: The CRM acknowledges webhooks immediately with `200 OK` after signature verification and dispatches heavy processing to queued background jobs (`ProcessOmnichannelWebhookJob`).
