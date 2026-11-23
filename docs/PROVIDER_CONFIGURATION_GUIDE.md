# Omnichannel Provider Configuration & Onboarding Guide

This guide provides step-by-step instructions for tenant administrators and platform super admins to connect and authenticate external communication providers with the Enterprise CRM SaaS platform.

---

## 1. Meta Business Suite: Facebook Messenger

### Prerequisites
- A Meta for Developers account ([developers.facebook.com](https://developers.facebook.com)).
- Admin access to your organization's Facebook Page.
- A Meta Developer App of type **Business**.

### Step-by-Step Setup
1. **Create/Select Meta App**:
   - In Meta App Dashboard, navigate to **Add Product** and select **Messenger**.
2. **Configure Required Permissions**:
   Ensure your app requests and is granted the following permissions during App Review:
   - `pages_messaging` (send/receive messages)
   - `pages_show_list` (list tenant's managed pages)
   - `pages_read_engagement` (read page posts and interactions)
   - `pages_manage_metadata` (subscribe webhooks to page)
3. **Configure Webhook Subscription**:
   - **Callback URL**: `https://<your-crm-domain>/api/v1/crm/social/webhook`
   - **Verify Token**: Generated from CRM Tenant Admin (*Settings → Integrations → Meta*).
   - **Subscription Fields**: Select `messages`, `messaging_postbacks`, `message_deliveries`, `message_reads`.
4. **Link Page in CRM**:
   - Go to CRM *Unified Inbox → Channels → Connect Facebook Page*.
   - Authorize with Meta OAuth or paste the Page Access Token.
   - The token will be encrypted using AES-256-CBC and stored in `social_channel_accounts`.

---

## 2. Meta: Instagram Direct Messages

### Prerequisites
- An Instagram Professional (Business or Creator) Account.
- Instagram Account linked to a Facebook Page managed by your organization.
- "Allow Access to Messages" toggled on in Instagram Settings (*Settings → Privacy → Messages*).

### Step-by-Step Setup
1. **Enable Instagram Messaging on Meta App**:
   - In Meta App Dashboard, add the **Instagram** product.
2. **Configure Required Permissions**:
   - `instagram_basic`
   - `instagram_manage_messages`
3. **Configure Webhook Subscription**:
   - Under App Dashboard → Webhooks → Select **Instagram**.
   - **Callback URL**: `https://<your-crm-domain>/api/v1/crm/social/webhook`
   - **Subscription Fields**: Select `messages`, `messaging_postbacks`, `messaging_seen`.
4. **Connect in CRM**:
   - Navigate to CRM *Unified Inbox → Channels → Connect Instagram Account*.
   - Verify the linked Instagram business profile displays a green checkmark.

---

## 3. WhatsApp Business Cloud API

### Prerequisites
- Meta Business Manager verified business account.
- A registered phone number not currently tied to a personal WhatsApp account.

### Step-by-Step Setup
1. **Set Up WhatsApp Cloud API in Meta Developer Portal**:
   - Add **WhatsApp** to your Meta Developer App.
   - Obtain your **Phone Number ID** and **WhatsApp Business Account ID (WABA ID)**.
2. **Generate Permanent System User Token**:
   - In Meta Business Settings → System Users, create a system user with `whatsapp_business_messaging` and `whatsapp_business_management` permissions.
   - Generate a permanent token.
3. **Configure Webhook**:
   - **Callback URL**: `https://<your-crm-domain>/api/v1/crm/whatsapp/webhook`
   - **Verify Token**: Token configured in CRM (*Settings → WhatsApp Settings*).
   - **Webhook Fields**: Subscribe to `messages`.
4. **Register in CRM**:
   - Paste Phone Number ID, WABA ID, and Permanent Bearer Token into CRM WhatsApp settings.
   - Test by sending a template message to an internal test phone number.

---

## 4. Email (SMTP / IMAP / Webhook)

### Outbound SMTP Configuration
Navigate to *CRM Settings → Integrations → Email*:
- **SMTP Host**: e.g. `smtp.sendgrid.net`, `smtp.mailgun.org`, or custom SMTP server.
- **Port**: `587` (TLS) or `465` (SSL).
- **Username / API Key**: Account username or API user.
- **Password**: Secure password or API secret key (encrypted at rest).
- **From Name & From Email**: Verified domain email address (e.g., `support@yourdomain.com`).

### Inbound Email Ingestion
Choose one of two ingestion modes:
1. **Inbound Webhook (Recommended for high volume)**:
   - Point your ESP Inbound Parse webhook (SendGrid, Mailgun, Postmark) to:
     `https://<your-crm-domain>/api/v1/crm/inbox/email/webhook`
2. **IMAP Polling (Standard mailbox)**:
   - Provide IMAP Host, Port (993 SSL), Email, and App Password.
   - Configure cron task `php artisan crm:poll-imap` to execute every 2 minutes.

---

## 5. SMS (Twilio / Exotel)

### Twilio Configuration
1. Obtain **Account SID** and **Auth Token** from the Twilio Console.
2. In CRM Settings → Integrations → SMS:
   - Select Provider: `Twilio`
   - Enter `Account SID`, `Auth Token`, and sender `Phone Number / Messaging Service SID`.
3. In Twilio Console:
   - Set the incoming message webhook URL to:
     `https://<your-crm-domain>/api/v1/crm/inbox/sms/webhook`

### Exotel Configuration (India DLT Compliant)
1. In Exotel Dashboard, obtain `Account SID`, `API Key`, and `API Token`.
2. Enter your approved TRAI DLT **Entity ID** (`PE ID`).
3. For transactional SMS templates, ensure the approved DLT `Template ID` is saved inside CRM SMS template management.

---

## 6. Telephony & AI Calling (Voice)

### Twilio Voice Configuration
1. In Twilio Console, navigate to **Voice → TwiML Apps** and create an app:
   - **Voice Request URL**: `https://<your-crm-domain>/api/v1/crm/voice/twiml` (HTTP POST)
   - **Status Callback URL**: `https://<your-crm-domain>/api/v1/crm/voice/status` (HTTP POST)
2. Assign your Twilio phone number to this TwiML App.
3. In CRM *Voice Settings*, toggle **AI Voice Agent** if autonomous IVR qualification is desired.

---

## 7. Website Live Chat Widget Embed

To install live chat on any tenant website or landing page:
1. Insert the lightweight embed snippet before the closing `</body>` tag:
```html
<script 
  src="https://<your-crm-domain>/js/chat-widget.js" 
  data-tenant-key="trk_live_xxxxxxxxxxxxxxxx"
  data-primary-color="#4F46E5"
  data-greeting="Hi! How can we help your business today?"
  async>
</script>
```
2. Whitelist your website's domain in CRM *Settings → Website Domains* to prevent cross-origin authorization issues.
