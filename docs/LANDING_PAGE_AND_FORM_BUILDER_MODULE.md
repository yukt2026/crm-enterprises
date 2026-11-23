# Landing Page Builder, Form Builder & Website Lead Capture Specification

## 1. Architectural Overview
The **Landing Page Builder & Form Capture System** empowers tenants to launch modular, conversion-optimized landing pages and embeddable forms with direct real-time integration into the CRM Core and Lead Acquisition Hub.

### Core Architectural Guarantees:
1. **Server-Side Validation & Sanitization**: Every submission is strictly verified against its dynamic JSON field schema and type constraints (email format, phone structure, number bounds, required fields).
2. **Central CRM Ingestion Pipeline**: Ingested submissions automatically run duplicate detection (email/phone), preserve full UTM attribution (`utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`), attach referrer headers, and trigger workflow automations (`form.submitted`, `lead.created`).
3. **Multi-Layered Spam & Bot Defense**:
   - Hidden Honeypot fields (`_hp_verify`) silently flag automated bot submissions with a `spam_score = 1.00`, preserving submission logs for audit without polluting CRM leads.
   - Timestamp validation ensures human submission latency threshold.
   - IP-level rate limiting prevents Denial-of-Service and scraping abuse.
4. **Custom Domain Isolation & DNS Ownership**:
   - Custom domains generate cryptographically secure TXT tokens (`crm-verify-{random}`).
   - Pages cannot be published to a custom domain until DNS verification requirements are satisfied.
   - SSL certificates are dynamically tracked and mapped.
5. **Full Versioning & Rollback**: Every published page snapshot is immutably archived in `landing_page_revisions`. Rolling back to any previous version restores all section structures, branding color schemes, and SEO metadata with an audit log entry.
6. **Consent-Aware Telemetry**: Lawful tracking of page views, CTA interactions, and form impressions with zero non-consented tracking cookies.

---

## 2. Database Schema

### `landing_pages`
- `id` (bigint, PK)
- `tenant_id` (bigint, FK to `tenants`)
- `name` (string)
- `slug` (string, unique per tenant)
- `title` (string, nullable)
- `description` (text, nullable)
- `status` (`draft`, `published`, `unpublished`, `archived`)
- `custom_domain_id` (bigint, FK to `custom_domains`, nullable)
- `current_version` (int, default: 1)
- `branding` (json: `primary_color`, `secondary_color`, `font_family`, `logo_url`, `favicon_url`)
- `seo_metadata` (json: `meta_title`, `meta_description`, `keywords`, `og_image`)
- `sections` (json: modular layout blocks)
- `settings` (json: custom CSS, tracking IDs)
- `view_count` (bigint, default: 0)
- `submission_count` (bigint, default: 0)
- `published_at` (datetime, nullable)
- `created_by_user_id` (bigint, FK to `users`, nullable)
- timestamps, softDeletes

### `landing_page_revisions`
- `id` (bigint, PK)
- `tenant_id` (bigint, FK)
- `landing_page_id` (bigint, FK)
- `version` (int)
- `snapshot` (json: sections, branding, seo)
- `changelog` (string, nullable)
- `created_by_user_id` (bigint, nullable)
- timestamps

### `custom_domains`
- `id` (bigint, PK)
- `tenant_id` (bigint, FK)
- `domain` (string, unique)
- `verification_token` (string)
- `verification_type` (`txt`, `cname`)
- `verification_status` (`pending`, `verified`, `failed`)
- `ssl_status` (`pending`, `active`, `failed`)
- `verified_at` (datetime, nullable)
- `last_verification_error` (text, nullable)
- timestamps

### `forms`
- `id` (bigint, PK)
- `tenant_id` (bigint, FK)
- `name` (string)
- `slug` (string, unique per tenant)
- `title` (string, nullable)
- `description` (text, nullable)
- `status` (`active`, `inactive`)
- `schema` (json: fields, conditional rules, validations)
- `settings` (json: submit button text, success message, redirect URL, consent disclosure, spam defense)
- `submission_count` (bigint, default: 0)
- `created_by_user_id` (bigint, nullable)
- timestamps, softDeletes

### `form_submissions`
- `id` (bigint, PK)
- `tenant_id` (bigint, FK)
- `form_id` (bigint, FK)
- `landing_page_id` (bigint, FK, nullable)
- `lead_id` (bigint, FK to `leads`, nullable)
- `data` (json: raw submitted field map)
- `utm_data` (json: `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`)
- `referrer` (string, nullable)
- `consent_given` (boolean, default: true)
- `consent_text` (text, nullable)
- `ip_address` (string, nullable)
- `user_agent` (text, nullable)
- `spam_score` (decimal(3,2), default: 0.00)
- timestamps

### `landing_page_analytics`
- `id` (bigint, PK)
- `tenant_id` (bigint, FK)
- `landing_page_id` (bigint, FK)
- `event_type` (`page_view`, `cta_click`, `form_view`)
- `device_type` (`desktop`, `mobile`, `tablet`)
- `referrer` (string, nullable)
- `utm_source` (string, nullable)
- `utm_medium` (string, nullable)
- `utm_campaign` (string, nullable)
- `meta` (json, nullable)
- timestamps

---

## 3. Section Types Supported

| Section Type | Content & Functionality |
| :--- | :--- |
| **`hero`** | High-impact above-the-fold banner with headline, subheadline, promotional badge, and CTA anchor button. |
| **`features`** | Grid of value propositions with title, description, and custom icons. |
| **`form_embed`** | Dynamic form embedded directly on the page, mapped to a tenant custom form slug. |
| **`testimonials`** | Customer quote cards with reviewer name, company, designation, and star ratings. |
| **`faq`** | Accordion-style frequently asked questions and answers for objection handling. |
| **`cta`** | Bottom banner with urgency messaging and lead capture action. |
| **`footer`** | Copyright, privacy policy link, terms of service link, and business branding. |

---

## 4. API Endpoints

### Landing Page Management
- `GET /api/v1/crm/landing-pages`: List landing pages with search, status filters, and domain details.
- `POST /api/v1/crm/landing-pages`: Create a new landing page draft.
- `GET /api/v1/crm/landing-pages/{id}`: Retrieve landing page with versioned revision history.
- `PUT /api/v1/crm/landing-pages/{id}`: Update sections, branding, and SEO metadata.
- `DELETE /api/v1/crm/landing-pages/{id}`: Soft delete landing page.
- `POST /api/v1/crm/landing-pages/{id}/publish`: Publish page and generate a revision snapshot.
- `POST /api/v1/crm/landing-pages/{id}/unpublish`: Take page offline.
- `POST /api/v1/crm/landing-pages/{id}/rollback`: Rollback to a specific historical version.
- `GET /api/v1/crm/landing-pages/templates`: List pre-built system landing page templates.
- `POST /api/v1/crm/landing-pages/from-template`: Clone system template into a tenant page.
- `GET /api/v1/crm/landing-pages/{id}/analytics`: View page views, CTA clicks, conversion funnel, and device distribution.

### Form Builder
- `GET /api/v1/crm/forms`: List tenant forms with submission counts.
- `POST /api/v1/crm/forms`: Create custom form schema with fields, validations, and consent settings.
- `GET /api/v1/crm/forms/{id}`: Retrieve form details.
- `PUT /api/v1/crm/forms/{id}`: Update schema or settings.
- `DELETE /api/v1/crm/forms/{id}`: Soft delete form.
- `GET /api/v1/crm/forms/{id}/submissions`: Paginated ledger of form submissions with linked CRM leads.

### Custom Domains
- `GET /api/v1/crm/custom-domains`: List mapped domains for tenant.
- `POST /api/v1/crm/custom-domains`: Connect domain and generate DNS TXT token.
- `POST /api/v1/crm/custom-domains/{id}/verify`: Verify DNS ownership and activate SSL.
- `DELETE /api/v1/crm/custom-domains/{id}`: Remove custom domain.

### Public Endpoints (Unauthenticated & Rate Limited)
- `GET /api/v1/public/landing-pages/{slug}`: Fetch published page HTML/JSON payload, tracking `page_view` telemetry.
- `POST /api/v1/public/landing-pages/{slug}/cta-click`: Log lawful CTA button click interaction.
- `POST /api/v1/public/forms/{slug}/submit-custom`: Ingest form submission, validate honeypot, and create CRM Lead.
