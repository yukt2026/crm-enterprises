# Analytics, Reporting, Attribution & Business Intelligence Module

## Executive Overview
The **Analytics, Reporting, Attribution & Business Intelligence** module unifies actual enterprise CRM, sales, marketing, telephony, WhatsApp, customer support, and operational records into real-time dashboards, algorithmic multi-touch attribution models, and automated custom reporting.

This module guarantees strict multi-tenant isolation, automated delta/trend calculations against comparative historical periods, transparent distinction between API-reported ad spend and manual estimates, and mathematical attribution modeling with transparent analytical disclaimers.

---

## 1. Architecture & Core Components

```
                                  +------------------------------------+
                                  |      Unified Analytics Hub         |
                                  |   (React 19 Frontend Dashboard)    |
                                  +-----------------+------------------+
                                                    |
                                    REST API Requests with Sanctum & TenantContext
                                                    |
                                                    v
                                  +------------------------------------+
                                  |        AnalyticsController         |
                                  +--------+--------+--------+---------+
                                           |        |        |
         +---------------------------------+        |        +---------------------------------+
         |                                          |                                          |
         v                                          v                                          v
+-----------------------------+   +-----------------------------+   +-----------------------------+
|  ExecutiveAnalyticsService  |   |    LeadAnalyticsService     |   |    SalesAnalyticsService    |
| - Total Leads & Conversion  |   | - 0-7, 8-14, 15-30, 30+d    |   | - Weighted Forecast Pipeline|
| - Won Revenue & Pipeline    |   | - Source & Channel Share    |   | - Stage Velocity & Deals    |
| - Provider vs Manual Spend  |   | - Duplicate Lead Rate       |   | - Sales Rep Quota Targets   |
| - Comparative Trend Deltas  |   | - Response Time Tracking    |   | - Average Sales Cycle Days  |
+-----------------------------+   +-----------------------------+   +-----------------------------+
                                                    |
                                                    v
                                  +------------------------------------+
                                  |         AttributionService         |
                                  | - First-Touch Model (100% Top)     |
                                  | - Last-Touch Model (100% Close)    |
                                  | - Linear Model (1/N Split)         |
                                  | - U-Shaped Position (40/20/40)     |
                                  | - Traceable Attribution Touchpoints|
                                  +-----------------+------------------+
                                                    |
                                                    v
                                  +------------------------------------+
                                  |        ReportExportService         |
                                  | - Dynamic CSV Export (RFC 4180)    |
                                  | - Saved Reports & Scheduled Jobs   |
                                  | - Multi-Tenant Authorization Checks|
                                  +------------------------------------+
```

---

## 2. Database Schema & Data Models

### 2.1 `attribution_touchpoints`
Tracks granular touchpoints across the customer lifecycle for attribution credit computation:
- `id` (bigint, PK)
- `tenant_id` (bigint, indexed)
- `lead_id` (bigint, nullable)
- `customer_id` (bigint, nullable)
- `deal_id` (bigint, nullable)
- `touchpoint_type` (string: `ad_click`, `page_view`, `form_submit`, `chat_message`, `phone_call`)
- `channel` (string: `google_ads`, `meta_ads`, `organic`, `whatsapp`, `email`, `sms`, `direct`)
- `campaign_id` (bigint, nullable)
- `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content` (string, nullable)
- `landing_page_id`, `form_id` (bigint, nullable)
- `touchpoint_timestamp` (datetime)
- `metadata` (json)
- `created_at`, `updated_at` (timestamps)

### 2.2 `saved_reports`
Stores persistent user-configured queries and scheduled report configurations:
- `id` (bigint, PK)
- `tenant_id` (bigint, indexed)
- `user_id` (bigint, FK to users)
- `title` (string)
- `description` (text, nullable)
- `report_type` (string: `executive_summary`, `leads`, `deals`, `attribution`, `campaigns`, `tickets`)
- `filters` (json: date ranges, employee IDs, statuses)
- `selected_columns` (json, nullable)
- `chart_type` (string, nullable)
- `is_scheduled` (boolean, default: false)
- `schedule_frequency` (string: `daily`, `weekly`, `monthly`, nullable)
- `schedule_recipients` (json, nullable)
- `last_run_at` (datetime, nullable)
- `created_at`, `updated_at` (timestamps)

### 2.3 Marketing Spend Enhancements (`marketing_campaigns`)
- `spend_type`: distinguishes `provider_reported` (API metrics from Meta/Google Ads) from `manual_estimate`.
- `cpl`: automatically calculated Cost Per Lead ($Total Spend / Total Leads$).
- `roas`: Return on Ad Spend ($Won Deal Value / Marketing Spend$).

---

## 3. Algorithmic Attribution Engine

The attribution engine analyzes journeys leading up to won deals and evaluates channel revenue distribution across 4 distinct methodologies:

| Model | Formula / Logic | Best Use Case |
|---|---|---|
| **First-Touch** | $100\%$ to earliest touchpoint | Assessing top-of-funnel discovery & brand awareness campaigns |
| **Last-Touch** | $100\%$ to touchpoint preceding deal win | Evaluating high-intent closing channels & direct conversions |
| **Linear** | Equal $\frac{1}{N}$ credit to each touchpoint | Nurturing content and long multi-touch consultative sales cycles |
| **U-Shaped (Position-Based)** | $40\%$ first, $40\%$ last, $20\%$ split across intermediate touches | High-consideration B2B enterprise sales pipelines |

> [!NOTE]
> **Analytical Notice:** In accordance with enterprise attribution ethics, all attribution outputs explicitly carry the notice:
> *"Attribution models represent algorithmic statistical estimates to guide marketing and sales investments, rather than deterministic absolute causation."*

---

## 4. API Reference

All routes require `auth:sanctum` and enforce tenant context scoping (`tenant`, `audit`).

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/crm/analytics/executive` | Executive dashboard KPIs, comparative trend percentages, and spend breakdown. |
| `GET` | `/api/v1/crm/analytics/leads` | Lead aging buckets (`0_7_days`, `8_14_days`, `15_30_days`, `30_plus_days`), source distribution, lost reasons, duplicate rate. |
| `GET` | `/api/v1/crm/analytics/sales` | Weighted pipeline forecast, stage breakdown, quota achievement leaderboard, average sales cycle days. |
| `GET` | `/api/v1/crm/analytics/attribution` | Side-by-side comparison matrix of First-Touch, Last-Touch, Linear, and U-Shaped revenue by channel. |
| `POST` | `/api/v1/crm/analytics/attribution/touchpoint` | Ingests a customer touchpoint with UTM parameters and channel tags. |
| `GET` | `/api/v1/crm/analytics/export` | Generates RFC 4180 CSV export or JSON dataset with custom column filtering. |
| `GET` | `/api/v1/crm/analytics/saved-reports` | Lists saved and scheduled reports for current tenant. |
| `POST` | `/api/v1/crm/analytics/saved-reports` | Creates a new saved or scheduled report. |
| `DELETE` | `/api/v1/crm/analytics/saved-reports/{id}` | Deletes a saved report with tenant validation. |

---

## 5. Verification & Acceptance Testing

- **Backend Feature Tests (`AnalyticsReportingAttributionTest.php`)**:
  - `test_executive_metrics_returns_real_kpis_and_comparison_trends`: Passed (Verified against DB records).
  - `test_lead_analytics_returns_aging_buckets_response_time_and_conversion_funnel`: Passed.
  - `test_sales_analytics_calculates_weighted_forecast_win_rate_and_cycle_duration`: Passed.
  - `test_attribution_engine_compares_first_touch_last_touch_linear_and_u_shaped_models`: Passed.
  - `test_record_touchpoint_api_stores_touchpoint_with_utm_metadata`: Passed.
  - `test_report_export_service_generates_csv_and_filtered_columns`: Passed.
  - `test_saved_reports_crud_and_tenant_isolation`: Passed.
  - Full suite status: **150 passed, 860 assertions, 0 failed**.
- **Frontend Integration Test (`apiIntegration.test.ts`)**:
  - Step 36 testing all analytics endpoints passed.
  - Full integration test: **37 passed, 0 failed**.
- **Production Build**:
  - `npm.cmd run build`: Vite v6.4.3 production bundle built cleanly in 10.47s with zero errors.
