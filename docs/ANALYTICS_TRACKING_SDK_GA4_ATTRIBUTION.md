# Analytics Tracking SDK, Google Analytics 4 & Campaign Attribution

**Module Specification:** Prompt 27 Implementation  
**Status:** Complete & Verified  
**Date:** November 23, 2026  
**Backend Feature Tests:** 8/8 Passed (`Tests\Feature\AnalyticsTrackingSdkGa4Test`)  
**Frontend Integration:** Verified (Step 42 in `apiIntegration.test.ts`)

---

## 1. Overview & Architecture

Prompt 27 introduces a privacy-conscious, multi-tenant tracking integration combining:
1. **Google Analytics 4 (GA4) Property Integration:** Authorized connections via property ID, measurement ID, and Data API (`v1beta:runReport`) for traffic trends, sessions, users, and campaign attribution without harvesting private visitor contact information.
2. **First-Party Lightweight Tracking SDK:** A script under 3KB (`/api/v1/tracking/script/{key}.js`) that automatically captures page views, UTM parameters, CTA clicks, and form submissions with zero invasive hardware fingerprinting.
3. **Session-to-Lead Attribution Engine:** Multi-touch tracking connecting anonymous session journeys to CRM Lead records upon voluntary form submission or identification, capturing first-touch, last-touch, and intermediate touchpoints.

```
+-------------------------------------------------------------------------------------------------------+
|                                    Visitor Browser / Landing Page                                     |
|  - crm-tracker.js (< 3KB lightweight script)                                                          |
|  - Memory & SessionStorage session tracking (Zero permanent hardware/canvas/battery fingerprinting)   |
|  - UTM parameter persistence across sub-pages                                                         |
|  - Consent-mode check (window.crmConsentGranted / localStorage)                                       |
+-------------------------------------------------------------------------------------------------------+
                                                  |
                     POST /api/v1/tracking/events (Public, Rate-Limited)
                                                  v
+-------------------------------------------------------------------------------------------------------+
|                                        TrackingService Ingest                                         |
|  1. Tracking Key & Tenant Scoping Verification                                                        |
|  2. Domain Whitelist Verification (allowed_domains)                                                   |
|  3. Bot / Spider Crawler Detection & Rejection                                                        |
|  4. Consent Enforcement (if require_consent === true && !consent_given -> drop)                        |
|  5. Event Deduplication via (session_id, event_id)                                                     |
|  6. IP Anonymization (masking last octet: 192.168.1.0)                                                |
|  7. Lead Association on voluntary form submit (backfill session events & record AttributionTouchpoint)|
+-------------------------------------------------------------------------------------------------------+
                     |                                                    |
                     v                                                    v
      [tracking_events Table]                              [attribution_touchpoints Table]
                     |                                                    |
                     +-------------------------+--------------------------+
                                               v
+-------------------------------------------------------------------------------------------------------+
|                                     Frontend Analytics & Attribution                                  |
|  - Google Analytics 4 Property Connector & Aggregated Reports                                         |
|  - First-Party Tracking SDK Config & Script Generator                                                 |
|  - Real-Time Tracking Diagnostics & Event Stream                                                      |
|  - Multi-Touch Lead Attribution Journeys (First-Touch vs Last-Touch vs Linear)                         |
+-------------------------------------------------------------------------------------------------------+
```

---

## 2. Google Analytics 4 (GA4) Integration & Privacy Boundaries

### Authorized Connection Setup
Tenants can connect their GA4 property through the CRM console by supplying:
- **GA4 Property ID:** e.g. `properties/123456789` or numeric ID.
- **Measurement ID:** e.g. `G-XXXXXXXXXX`.
- **Measurement Protocol API Secret:** For offline conversion streaming.
- **OAuth Credentials:** Optional Google OAuth tokens for direct Google Analytics Data API queries.

### Standard Aggregated Metrics
The integration reports:
- **Sessions & Total Users**
- **Screen Page Views & Conversions**
- **Bounce Rate & Average Session Duration**
- **Traffic Source / Medium Breakdown:** (`google / cpc`, `meta / paid_social`, `organic`, `direct`, `email / newsletter`)
- **Campaign Performance:** Aggregated sessions and goal completions by campaign name.
- **Top Landing Pages:** Sessions and bounce rate per URL path.
- **30-Day Traffic Trends:** Daily volume curves.

### Privacy Boundary Enforcement
In strict compliance with Google Analytics Data API terms:
- GA4 is treated solely as an aggregated attribution and traffic source.
- Individual visitor identities, names, phone numbers, or private viewing logs are never requested from or exposed by GA4.

---

## 3. First-Party Tracking SDK & Embed Script

### Embed Snippet
```html
<script src="https://app.crmenterprises.com/api/v1/tracking/script/{tracking_key}.js" async></script>
```

### Key SDK Capabilities
- **Lightweight:** Pure vanilla JavaScript, under 3KB minified, zero third-party dependencies.
- **Session Management:** Ephemeral `sessionStorage` token (`_crm_sid`), zero invasive hardware/canvas/audio fingerprinting.
- **UTM Persistence:** Parses `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content` from URL query parameters and persists them across the browsing session.
- **Automatic Event Listeners:**
  - `page_view`: Fired on DOM ready.
  - `cta_click`: Fired when user clicks buttons or links with `data-crm-track`, `button[type="submit"]`, or `.btn`.
  - `form_submission`: Fired on form submission, securely capturing form metadata and voluntary email/phone fields for lead attribution.
- **Custom Event API:**
  - `window.crmTrack(eventName, metadata)`
  - `window.crmSetConsent(boolean)`

---

## 4. Privacy, Compliance & Bot Protection

1. **Consent-Aware Behavior:**
   When `require_consent` is enabled in tenant settings, the SDK and backend discard analytical events unless `consent_given: true` or `window.crmConsentGranted = true`.
2. **IP Anonymization:**
   When `anonymize_ip` is enabled, IPv4 addresses have their last octet masked to `0` (e.g., `203.0.113.195` -> `203.0.113.0`), and IPv6 addresses are truncated or hashed.
3. **Bot / Crawler Filtering:**
   User agents matching search engine crawlers, headless browsers, or automated spiders (`googlebot`, `bingbot`, `crawler`, `postman`, `curl`) are rejected at the edge before database persistence.
4. **Duplicate Event Deduplication:**
   Client-side `event_id` hashes prevent duplicate counts on network retries or page reloads within the session window.
5. **Configurable Data Retention:**
   Automated pruning removes events older than the configured threshold (30, 60, 90, 180, or 365 days).

---

## 5. Multi-Touch Attribution Engine & Lead Linking

### Anonymous Session to Lead Conversion
1. When a visitor browses anonymously, events are recorded with `session_id` and `lead_id = null`.
2. When the visitor voluntarily submits a form providing contact information (email or phone):
   - The CRM creates or resolves the matching `Lead`.
   - The backend executes `associateSessionWithLead`, backfilling the `lead_id` onto all earlier anonymous events for that `session_id`.
   - An `AttributionTouchpoint` is created, linking the original campaign and referrer to the converted lead.
3. The Attribution Engine calculates:
   - **First-Touch:** Original acquisition channel and campaign.
   - **Last-Touch:** Immediate conversion trigger.
   - **Multi-Touch Journeys:** Complete timeline of touchpoints leading to conversion.

---

## 6. API Route Roster

| Method | Endpoint | Access | Purpose |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/v1/tracking/events` | Public | Ingest tracking event from website SDK or landing page |
| `GET` | `/api/v1/tracking/script/{key}.js` | Public | Serve dynamic tenant-parameterized tracking script |
| `GET` | `/api/v1/crm/tracking/settings` | Protected | Retrieve tenant tracking configuration |
| `PUT` | `/api/v1/crm/tracking/settings` | Protected | Update consent, retention, domains, and event toggles |
| `GET` | `/api/v1/crm/tracking/events` | Protected | Paginated event stream with filters |
| `GET` | `/api/v1/crm/tracking/attribution` | Protected | Multi-touch lead journeys and channel summary |
| `GET` | `/api/v1/crm/tracking/diagnostics` | Protected | Real-time SDK health, active sessions, and top UTMs |
| `POST` | `/api/v1/crm/tracking/ga4/connect` | Protected | Connect and authorize GA4 property |
| `POST` | `/api/v1/crm/tracking/ga4/disconnect`| Protected | Disconnect active GA4 property |
| `GET` | `/api/v1/crm/tracking/ga4/properties`| Protected | List authorized GA4 properties |
| `GET` | `/api/v1/crm/tracking/ga4/report` | Protected | Fetch aggregated GA4 metrics report |

---

## 7. Automated Acceptance Test Verification

All 8 feature tests in `Tests\Feature\AnalyticsTrackingSdkGa4Test` passed:
1. `test_ga4_connection_and_reporting_permissions` -> PASS
2. `test_public_event_ingestion_and_utm_capture` -> PASS
3. `test_consent_restrictions_block_unauthorized_tracking` -> PASS
4. `test_bot_filtering_rejects_automated_crawlers` -> PASS
5. `test_duplicate_event_deduplication` -> PASS
6. `test_form_conversion_linking_and_multi_touch_attribution` -> PASS
7. `test_serve_dynamic_tracking_script` -> PASS
8. `test_strict_multi_tenant_isolation_on_tracking` -> PASS

The frontend-to-backend integration test harness passed all 43 steps:
- `42 passed, 0 failed` prior + `Step 42: Testing Tracking SDK, GA4 Connection & Attribution` -> **43 passed, 0 failed**.
