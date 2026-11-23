# Enterprise CRM Core Module Documentation

## 1. Overview & Architecture

The **CRM Core Module** provides the business foundation for managing leads, contacts, companies, deals, customers, and their complete omnichannel interaction histories. It replaces simulated fallback data and flat-file stores with a multi-tenant, domain-driven Laravel backend backed by MySQL and Redis, integrated with the existing React 19 + Vite frontend.

```
+---------------------------------------------------------------------------------------+
|                                    React 19 Frontend                                  |
|  - Leads Kanban & Table View       - Duplicate Detection & Merge Modal                |
|  - Customer 360 Drawer             - Lead Conversion Flow Modal                       |
+-------------------------------------------+-------------------------------------------+
                                            | (REST JSON / Sanctum Token)
+-------------------------------------------v-------------------------------------------+
|                              Laravel API & Middleware Layer                           |
|  - auth:sanctum (Bearer Token)     - tenant (Strict Workspace Isolation Scope)       |
|  - audit (Immutable Audit Logging) - throttle (API Rate Limiting)                     |
+-------------------------------------------+-------------------------------------------+
                                            |
+-------------------------------------------v-------------------------------------------+
|                                  CRM Domain Services                                  |
|  +------------------------+  +--------------------------+  +-----------------------+  |
|  |   LeadScoringService   |  | LeadDistributionService  |  | DuplicateDetectionSvc |  |
|  | - Completeness (+15)   |  | - Manual Assignment      |  | - Phone/Email Match   |  |
|  | - Budget Tier (+25)    |  | - Workload Round-Robin   |  | - Controlled Merge    |  |
|  | - Timeline (+20)       |  | - Event Notifications    |  | - Audit Trail Record  |  |
|  | - Touchpoints (+20)    |  +--------------------------+  +-----------------------+  |
|  +------------------------+                                                           |
|  +------------------------+  +--------------------------+  +-----------------------+  |
|  | LeadConversionService  |  |   Customer360Service     |  | CrmImportExportSvc    |  |
|  | - Lead -> Contact/Co/D |  | - Unified 360 Profile    |  | - Multi-Field Mapping |  |
|  | - Preserves History    |  | - Omnichannel Activities |  | - Duplicate Warning   |  |
|  +------------------------+  +--------------------------+  +-----------------------+  |
+-------------------------------------------+-------------------------------------------+
                                            |
+-------------------------------------------v-------------------------------------------+
|                                MySQL Storage (Tenanted)                               |
|  - leads (score, attribution, UTM)   - contacts (multi-phone/email JSON, consent)     |
|  - companies (GSTIN, legal name)     - deals (pipelines, stage, probability)          |
|  - customers (tier, MRR, 360 link)   - crm_activities (unified chronological log)     |
|  - lead_assignments (audit history)  - duplicate_merge_logs (historical record)       |
+---------------------------------------------------------------------------------------+
```

---

## 2. Database Schema & Migration Details

Migration: `database/migrations/2026_11_23_000004_create_crm_core_tables.php`

### 2.1 Leads Table (`leads`)
- **Core Info**: `name`, `first_name`, `last_name`, `email`, `phone`, `alternate_phone`, `company_name`, `designation`.
- **Address**: `address_line1`, `address_line2`, `city`, `state`, `country`, `postal_code`.
- **Requirements**: `requirement`, `product_interest`, `estimated_value`, `expected_purchase_date`.
- **Status & Lifecycle**:
  - `status`: Configurable (`new`, `attempted`, `contacted`, `qualified`, `nurturing`, `converted`, `lost`, `disqualified`).
  - `lifecycle_stage`: `lead`, `marketing_qualified`, `sales_qualified`, `opportunity`, `customer`.
  - `priority`: `low`, `medium`, `high`, `urgent`.
  - `qualification_status`: `unqualified`, `qualified`, `disqualified`.
- **Scoring**: `score` (0-100), `score_breakdown` (JSON detailing points for completeness, budget, urgency, source, engagement).
- **Ownership & Attribution**:
  - `user_id` (Owner), `assigned_user_id` (Active Rep), `assigned_team_id`.
  - `source`, `source_account`, `campaign_name`, `campaign_id`, `ad_id`, `landing_page_url`.
  - UTM: `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`.
  - Attribution: `first_touch_attribution` (JSON), `last_touch_attribution` (JSON).
- **Follow-up & Conversion**:
  - `next_followup_at`, `reminder_at`, `lost_reason`, `disqualification_reason`, `is_archived`.
  - `converted_at`, `converted_contact_id`, `converted_company_id`, `converted_deal_id`.

### 2.2 Contacts Table (`contacts`)
- **Profiles**: `first_name`, `last_name`, `name`, `title` (Designation), `department`.
- **Multi-channel Communications**: `phone_numbers` (JSON array with label, number, primary flag), `email_addresses` (JSON array with label, email, primary flag).
- **Compliance & Consent**: `communication_preferences` (JSON), `consent_status` (`opted_in`, `opted_out`, `pending`), `consent_timestamp`.
- **Address & Tenant**: `address`, `city`, `state`, `country`, `postal_code`, `workspace_id`, `company_id`, `lead_id`.

### 2.3 Companies Table (`companies`)
- **Entity**: `name`, `legal_name`, `website`, `industry`, `size_range`, `annual_revenue`.
- **Taxation & Compliance**: `gstin`, `tax_number`, `registration_number`.
- **Addresses**: `billing_address` (JSON), `delivery_address` (JSON).
- **Ownership & Status**: `account_owner_id`, `account_status` (`prospect`, `active`, `churned`, `hold`), `lead_id`.

### 2.4 CRM Activities Table (`crm_activities`)
Unified chronological event stream supporting:
- `activity_type`: `note`, `call`, `email`, `sms`, `whatsapp`, `meeting`, `task`, `status_change`, `assignment`, `conversion`, `audit`.
- `direction`: `inbound`, `outbound`, `internal`.
- `entity_type` & `entity_id`: Polymorphic or direct link to Lead, Contact, Company, Customer, Deal.
- `metadata`: Flexible JSON for call duration, recording URLs, email subjects, status changes, etc.

### 2.5 Duplicate Merge Logs (`duplicate_merge_logs`)
Maintains an immutable audit record whenever two duplicate leads or contacts are merged:
- `master_entity_id`, `merged_entity_id`, `performed_by`, `merged_data` (JSON snapshot of merged record).

---

## 3. Lead Qualification & Scoring Engine

Implemented in `App\Domains\CRM\Services\LeadScoringService`.

The system evaluates incoming and updated leads across 5 distinct dimensions (0 to 100 points):

| Dimension | Max Points | Evaluation Criteria |
| :--- | :---: | :--- |
| **Profile Completeness** | 15 pts | Full Name (+3), Phone (+4), Email (+4), Company Name (+2), Requirement/Designation (+2) |
| **Budget & Financial Tier** | 25 pts | > $50,000 (+25 pts)<br>> $20,000 (+20 pts)<br>> $5,000 (+15 pts)<br>> $1,000 (+10 pts) |
| **Purchase Urgency** | 20 pts | Within 7 days (+20 pts)<br>Within 30 days (+15 pts)<br>Within 90 days (+10 pts) |
| **Source Quality Tier** | 20 pts | Inbound / Referral / Organic Search (+20 pts)<br>Google Ads / Campaign (+15 pts)<br>Cold Outreach / Social (+10 pts) |
| **Engagement Touchpoints** | 20 pts | > 5 Activities (+20 pts)<br>> 3 Activities (+15 pts)<br>> 1 Activity (+10 pts) |

- **Auto-Qualification Threshold**: Leads with score $\ge 70$ are automatically marked `qualified`, score 40–69 as `unqualified` (in progress), and score $< 40$ as `low_priority`.
- **Manual Override Protection**: If an agent manually disqualifies or qualifies a lead, the automatic engine respects the manual decision.

---

## 4. Lead Distribution & Workload Balancing

Implemented in `App\Domains\CRM\Services\LeadDistributionService`.

### Round-Robin Algorithm:
1. Identifies all unassigned active leads in the current workspace (`assigned_user_id IS NULL AND is_archived = 0`).
2. Retrieves all active workspace team members holding `Sales Executive`, `Manager`, or `Business Admin` roles.
3. Queries current workload (number of active assigned leads per user).
4. Sorts sales reps by current lowest workload, distributing leads round-robin to maintain equitable workload balancing.
5. Emits `lead_assignments` audit log and registers a `crm_activity` entry (`assignment`).
6. Triggers in-app and email notifications (`lead.assigned`) to the designated sales rep via `NotificationService`.

---

## 5. Duplicate Detection & Controlled Merge

Implemented in `App\Domains\CRM\Services\DuplicateDetectionService`.

### Detection Mechanism:
- **Phone Normalization**: Strips non-digit characters and matches on national/international formats.
- **Email Normalization**: Case-insensitive, trimmed email match.
- **Company Matching**: Soundex and substring matching on sanitized company titles.

### Controlled Merge Workflow:
1. Agent reviews duplicate candidate side-by-side with master lead via `DuplicateDetectionModal.tsx`.
2. Agent chooses which fields to retain or override.
3. Atomic transaction:
   - Preserves all notes, activities, attachments, and assignments by re-pointing them to `master_lead_id`.
   - Records an immutable snapshot in `duplicate_merge_logs`.
   - Flags the duplicate as `converted` / `is_archived = 1` or deletes it based on workspace configuration.

---

## 6. Lead Conversion Engine

Implemented in `App\Domains\CRM\Services\LeadConversionService`.

Converts an active lead into permanent enterprise assets without losing historical data:
1. **Contact Creation**: Copies contact profile, primary and alternate phone numbers, email addresses, and communication preferences.
2. **Company Creation / Association**: Links existing company or provisions a new company record with legal title, industry, and billing/tax data.
3. **Deal Creation (Optional)**: Creates a new deal in the specified sales pipeline stage with estimated contract value and target close date.
4. **Customer Record**: Creates or links a customer entity for ongoing relationship management.
5. **Timeline Re-anchoring**: Attaches all past lead activities, notes, and uploaded attachments to the newly generated contact and company.
6. **Lead Update**: Marks the lead status as `converted` with timestamps and direct pointers to `converted_contact_id`, `converted_company_id`, and `converted_deal_id`.

---

## 7. Customer 360-Degree Profile

Implemented in `App\Domains\CRM\Services\Customer360Service` and `Customer360Drawer.tsx`.

The Customer 360 view aggregates a unified overview of all customer dimensions:
- **Header Profile**: Contact name, designation, primary phone/email, company name, industry, customer tier (`prospect`, `standard`, `premium`, `enterprise`), and total lifetime revenue.
- **Tab 1: Omnichannel Activities**: Unified chronological timeline of calls, emails, meetings, WhatsApp notes, tasks, and system events, with an inline activity logging composer.
- **Tab 2: Sales Deals**: Active and historical deals, contract values, stages (`won`, `lost`, `negotiation`), and closing dates.
- **Tab 3: Origin & Attribution**: First-touch and last-touch attribution models, campaign names, UTM parameters, and initial landing page URLs.
- **Tab 4: Files & Attachments**: Uploaded contract PDFs, proposals, requirements documents, and specifications.

---

## 8. API Endpoints Reference

All endpoints require `Authorization: Bearer <token>` and `X-Workspace-Id: <id>` headers.

| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/api/v1/crm/leads` | List leads (filtered by status, search, pagination) |
| `POST` | `/api/v1/crm/leads` | Create a new lead (with automatic scoring & attribution) |
| `GET` | `/api/v1/crm/leads/{id}` | Lead details with score breakdown & activities |
| `PUT` | `/api/v1/crm/leads/{id}` | Update lead fields & re-evaluate score |
| `DELETE`| `/api/v1/crm/leads/{id}` | Soft delete / archive lead |
| `POST` | `/api/v1/crm/leads/check-duplicates` | Duplicate scan by phone, email, or company |
| `POST` | `/api/v1/crm/leads/{id}/merge` | Controlled merge of duplicate lead into master |
| `POST` | `/api/v1/crm/leads/{id}/assign` | Assign lead to user with reason & notification |
| `POST` | `/api/v1/crm/leads/auto-distribute-round-robin` | Distribute unassigned leads across sales team |
| `POST` | `/api/v1/crm/leads/{id}/convert` | Transactional conversion into Contact, Company & Deal |
| `POST` | `/api/v1/crm/leads/{id}/archive` | Archive lead |
| `POST` | `/api/v1/crm/leads/{id}/unarchive` | Unarchive lead |
| `POST` | `/api/v1/crm/leads/{id}/recalculate-score` | Re-run algorithmic scoring engine |
| `GET` | `/api/v1/crm/leads/export/csv` | Stream UTF-8 CSV export of all leads |
| `POST` | `/api/v1/crm/leads/import/csv` | Batch import leads from CSV |
| `GET` | `/api/v1/crm/customers/{id}/profile-360` | Full Customer 360-Degree Profile |
| `GET` | `/api/v1/crm/activities` | List chronological activities for an entity |
| `POST` | `/api/v1/crm/activities` | Log a new call, email, meeting, note, or event |

---

## 9. Verification & Test Suite

All CRM Core capabilities are verified with automated PHPUnit and TypeScript integration test suites:

- **PHPUnit Feature Suite**: `backend/tests/Feature/CrmCoreModuleTest.php`
  - `test_lead_creation_with_attribution_and_automatic_scoring`: Verified profile scoring and UTM storage.
  - `test_lead_assignment_and_round_robin_distribution`: Verified load balancing and assignment logs.
  - `test_duplicate_detection_and_controlled_merge`: Verified phone/email detection and transactional merge.
  - `test_lead_conversion_into_contact_company_and_deal`: Verified transactional creation of linked records without data loss.
  - `test_customer_360_profile_aggregation`: Verified multi-tab aggregation of customer interactions.
  - `test_tenant_isolation_on_crm_core`: Verified zero cross-tenant leakage across workspaces.
  - **Result**: `21 passed (112 assertions)`.

- **Frontend Integration Test**: `frontend/src/tests/apiIntegration.test.ts`
  - **Result**: `6 passed, 0 failed`.

- **Vite & TypeScript Production Build**: `npm run build`
  - **Result**: Clean build, 0 TypeScript errors.
