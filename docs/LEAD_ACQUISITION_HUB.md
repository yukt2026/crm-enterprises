# Omnichannel Lead Acquisition Hub

## 1. Overview & Architecture

The **Lead Acquisition Hub** provides a multi-tenant, unified ingestion pipeline that ingests leads across Google Ads, Meta Ads (Facebook & Instagram), YouTube campaigns, website forms, hosted CRM pages, WhatsApp Business enquiries, and generic webhooks (e.g. Google Forms, Zapier, Make).

Every supported lead source feeds into a centralized 15-step ingestion pipeline ensuring signature verification, token validation, idempotency, field normalization, duplicate handling, automatic qualification scoring, round-robin assignment, and multi-touch marketing attribution tracking.

```
+--------------------------------------------------------------------------------------------------------+
|                                        Lead Acquisition Sources                                        |
|  [Google Ads API]   [Meta Lead Ads]   [YouTube Campaigns]   [Website/Forms]   [WhatsApp]   [Webhooks]  |
+---------------------------------------------------+----------------------------------------------------+
                                                    |
                                                    v
+--------------------------------------------------------------------------------------------------------+
|                                    Central 15-Step Ingestion Engine                                    |
|                                                                                                        |
|  1. Receive source event (Payload, Headers, IP, User Agent)                                            |
|  2. Verify OAuth, HMAC SHA256 Signature or Secret Token                                                |
|  3. Resolve Tenant & Connected Account (lead_sources)                                                  |
|  4. Preserve Raw Source Event (lead_ingestion_events)                                                  |
|  5. Check Idempotency (Event ID deduplication)                                                         |
|  6. Normalize Field Names & Values (Adapter normalization)                                             |
|  7. Apply Tenant Field Mappings (lead_field_mappings with transform rules)                             |
|  8. Validate Required Data (name/email/phone check)                                                    |
|  9. Duplicate Detection (DuplicateDetectionService matching)                                          |
| 10. Create or Update CRM Lead (Without overwriting historical touchpoints)                             |
| 11. Save Attribution (UTM parameters, GCLID, FBCLID, landing page URL, Campaign metadata)               |
| 12. Lead Scoring & Round-Robin Assignment (LeadScoringService & LeadDistributionService)               |
| 13. Omnichannel Activity Recording (CrmActivity status_change event)                                   |
| 14. Notification Dispatch (NotificationService alert to assigned sales rep)                            |
| 15. Store Ingestion Result & Sync Status (Update lead_ingestion_events & lead_sources)                  |
+---------------------------------------------------+----------------------------------------------------+
                                                    |
                                                    v
+--------------------------------------------------------------------------------------------------------+
|                                      MySQL Storage & Relationships                                     |
|  - lead_sources (encrypted credentials, provider status, sync history)                                 |
|  - lead_forms (hosted forms, embed tokens, submission counters)                                        |
|  - lead_field_mappings (tenant field transform rules: trim, phone_normalize, uppercase)               |
|  - lead_ingestion_events (raw payloads, retry queue, audit history)                                    |
|  - ad_campaign_metrics (synced ad spend, impressions, clicks, leads, cost-per-lead)                    |
|  - leads (attributed CRM leads with score, tags, and touchpoint models)                                |
+--------------------------------------------------------------------------------------------------------+
```

---

## 2. Supported Lead Sources & Adapters

### A. Google Ads (`GoogleAdsSourceAdapter`)
- Integrates with Google Ads Lead Form assets and Performance Max campaigns.
- Webhook verification with `google_key`.
- Normalizes `user_column_data` field format (`FIRST_NAME`, `LAST_NAME`, `EMAIL`, `PHONE_NUMBER`, `COMPANY_NAME`).
- Preserves `gclid`, `campaign_id`, `campaign_name`, and form metadata.

### B. Facebook & Instagram Ads (`MetaAdsSourceAdapter`)
- Ingests Meta Lead Ads webhooks via Graph API.
- Secure signature verification with `X-Hub-Signature-256` HMAC SHA256 using the tenant's configured app secret.
- Webhook verification challenge handling (`hub.mode`, `hub.verify_token`, `hub.challenge`).
- Normalizes `field_data` pairs (`full_name`, `email`, `phone_number`, `job_title`).
- Preserves `fbclid`, `ad_id`, `adset_id`, `campaign_id`, and page identifiers.
- Clearly displays required Meta App Review permissions: `leads_retrieval`, `pages_manage_ads`, and `pages_read_engagement`.

### C. YouTube Campaigns
- Tracks enquiries driven by YouTube video action campaigns through connected Google Ads lead forms and landing page query strings (`utm_source=youtube`, `utm_medium=video`).
- Preserves campaign and creative attribution without attempting unauthorized personal data extraction from video views.

### D. Website & Hosted Landing Pages (`WebsiteFormSourceAdapter`)
- Provides hosted form endpoints (`/api/v1/public/forms/{uuid}`).
- One-click embeddable JavaScript snippet mounting directly into external websites (`/api/v1/public/forms/{uuid}/embed.js`).
- **Spam Protection**: Silent honeypot trap (`_hp_check` / `_honeypot`) that drops automated bot submissions without disruption.
- Automatic capture of referrer URL, user IP, user agent, and all query UTM parameters (`utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`).

### E. WhatsApp Business Enquiries (`WhatsAppEnquirySourceAdapter`)
- Ingests incoming WhatsApp messages.
- Automatic Click-to-WhatsApp ad attribution extraction from incoming `referral` metadata (`source_id`, `headline`, `body`).
- Phone number normalization and automated lead generation.

### F. Generic Signed Webhooks & Google Forms (`GenericWebhookSourceAdapter`)
- Ingests Google Forms, Zapier, Make, and Typeform submissions via `/api/v1/webhooks/generic/{sourceId}`.
- Secret token or HMAC header verification (`X-Webhook-Secret`).

---

## 3. Attribution Architecture

The pipeline captures and distinguishes **observed marketing touchpoints** from **inferred attribution**:
- **First-Touch Attribution**: Captures the initial channel, campaign, creative, and UTM parameters when the prospect was first created.
- **Last-Touch Attribution**: Updates with subsequent inbound touches, linking new message notes while preserving the original source.
- **Click Identifiers**: Safely stores `gclid` and `fbclid` for downstream conversion offline sync.
- **Attribution Policy**: Transparently notes that tracked touchpoints represent observed visitor interactions rather than absolute proof of purchase causation.

---

## 4. API Endpoints Reference

### Public & Webhook Endpoints (Unauthenticated / Signature-Verified)
| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/api/v1/public/forms/{uuid}` | Public form schema and metadata |
| `POST` | `/api/v1/public/forms/{uuid}/submit` | Public form submission with honeypot validation |
| `GET` | `/api/v1/public/forms/{uuid}/embed.js` | Dynamic embedding JavaScript snippet |
| `GET` | `/api/v1/webhooks/meta-ads` | Meta Webhook verify token challenge |
| `POST` | `/api/v1/webhooks/meta-ads` | Meta Lead Ads inbound webhook |
| `POST` | `/api/v1/webhooks/google-ads/{sourceId}` | Google Ads Lead Form webhook |
| `POST` | `/api/v1/webhooks/whatsapp/{sourceId}` | WhatsApp inbound message webhook |
| `POST` | `/api/v1/webhooks/generic/{sourceId}` | Generic / Google Forms webhook |

### Protected CRM Endpoints (Requires `auth:sanctum` & `X-Workspace-Id`)
| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/api/v1/crm/lead-sources` | List connected channels & provider catalog |
| `POST` | `/api/v1/crm/lead-sources` | Connect a new source account |
| `GET` | `/api/v1/crm/lead-sources/stats` | Aggregated channel metrics & lead statistics |
| `POST` | `/api/v1/crm/lead-sources/{id}/test` | Test connection & verify credentials |
| `POST` | `/api/v1/crm/lead-sources/{id}/sync-metrics` | Sync campaign advertising spend & clicks |
| `DELETE`| `/api/v1/crm/lead-sources/{id}` | Disconnect and remove source |
| `GET` | `/api/v1/crm/lead-forms` | List hosted & embedded lead forms |
| `POST` | `/api/v1/crm/lead-forms` | Create a new lead form |
| `GET` | `/api/v1/crm/lead-field-mappings` | List tenant field mappings |
| `POST` | `/api/v1/crm/lead-field-mappings` | Create/update field mapping rule |
| `GET` | `/api/v1/crm/lead-ingestion-events` | Stream raw ingestion events & failed queue |
| `POST` | `/api/v1/crm/lead-ingestion-events/{id}/retry` | Reprocess a failed event safely |
| `GET` | `/api/v1/crm/ad-campaign-metrics` | Synchronized campaign spend & CPL |

---

## 5. Frontend Screens & Controls

Accessible via the sidebar item **Lead Acquisition Hub**:
- **Overview & KPIs**: Real-time KPI tiles (Total Leads, Spend, CPL, Clicks, Ingestion Events, Failed Queue), Leads distribution pie chart, and Campaign volume vs spend bar chart.
- **Connected Channels**: Status badges (`Connected`, `Disconnected`), credentials configuration modals, connection testing, and spend sync.
- **Lead Forms & Hosted Pages**: Forms manager with embed code generator and direct REST endpoint URLs.
- **Field Mappings**: Inbound parameter mapping table with transformation rules (`trim`, `phone_normalize`, `lowercase`, `uppercase`).
- **Ingestion Queue & Retry**: Event log table with payload inspector modal and one-click retry for failed events.
- **Campaign Spend & Performance**: Comprehensive campaign table with impressions, clicks, leads, spend, and CPL breakdown.

---

## 6. Verification & Test Suite

Automated acceptance tests implemented in `backend/tests/Feature/LeadAcquisitionHubTest.php`:
1. `test_google_ads_lead_form_ingestion_and_attribution`: Verified Google Ads Lead Form column parsing and UTM storage.
2. `test_meta_lead_ads_signature_verification_and_rejection`: Verified rejection of invalid `X-Hub-Signature-256` and acceptance of authentic HMAC SHA256 payloads.
3. `test_duplicate_webhook_idempotency_prevents_duplicate_leads`: Verified that duplicate deliveries of the same `event_id` are recognized and do not create duplicate leads.
4. `test_tenant_field_mapping_and_transformation`: Verified custom field mappings and normalization (`phone_normalize`, `uppercase`).
5. `test_public_form_submission_and_honeypot_rejection`: Verified honeypot spam traps drop bots while legitimate human submissions succeed with full UTM attribution.
6. `test_failed_event_inspection_and_retry`: Verified error logging for incomplete events and successful reprocessing via the retry endpoint.
7. `test_tenant_isolation_on_lead_sources_and_events`: Verified strict cross-workspace data isolation.

- **Backend Test Suite**: **28 passed (141 assertions)** across all modules.
- **Frontend Production Build**: Vite + esbuild completed with 0 errors.
