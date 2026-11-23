# Advanced Business Analytics, Custom Report Builder, AI Insights & India-First Field Sales Module

## Architectural Overview & Objectives
This module elevates the CRM into an enterprise-grade Business Intelligence (BI) and operational performance platform tailored for Indian businesses, multi-branch conglomerates, and mobile-first field sales teams. It establishes a unified analytics architecture with complete domain integration across Leads, Deals, Invoices, Payments, Expenses, Projects, Support Tickets, and Field Sales.

---

## Key Capabilities & Pillars

### 1. Owner & Executive BI Cockpit
- **Strict Distinction between Recorded Actuals vs. Pipeline Projections**:
  - **Recorded Actuals**: Mathematically derived solely from booked invoices (`invoices`), recorded payments (`payments`), converted leads (`leads`), and closed deals (`deals`).
  - **Pipeline Projections**: Forecast figures derived from unclosed deals with explicit probability weighting and non-binding advisory disclaimers.
- **Cross-Domain Executive Metrics**:
  - Net Profit Margin (`(Collected Cash - Incurred Expenses) / Invoiced Revenue * 100`).
  - Customer Retention Rate (`Customers with >= 2 paid invoices / Total Customers * 100`).
  - Branch and Employee Performance rankings.
  - Support SLA breach rate and project delivery status.
- **Customizable Dashboard Layouts**:
  - Personalized widget ordering, visibility, and filter scopes stored in `dashboard_widget_layouts` table with tenant and role/user isolation.

### 2. Multi-Domain Custom Report Builder
- **Catalog of 8 Modules**:
  1. `leads`: Acquisition channels, status, scores, owners, branch.
  2. `deals`: Pipeline stage, value, close date, lost reason.
  3. `invoices`: Total amount, tax, status, balance due.
  4. `customers`: Industry, GSTIN, lifetime value, branch.
  5. `tickets`: Priority, category, status, SLA status, resolution time.
  6. `projects`: Budget, status, priority, manager.
  7. `field_sales`: Check-in/out coordinates, distance km, outcome, expenses.
  8. `expenses`: Category, payment method, tax amount.
- **Capabilities**:
  - Dynamic filtering with operators (`=`, `!=`, `>`, `<`, `contains`, `between`).
  - Aggregations: `sum`, `avg`, `count`, `min`, `max`.
  - Grouping & Categorization: Group records by status, owner, branch, or category.
  - Visual Format Preview: Table view, bar chart, line chart, pie chart.
  - Streaming CSV Export.
  - Saved & Scheduled Reports (`saved_reports`) with automated daily/weekly/monthly email delivery.

### 3. Analytics Deep-Dives
- **Sales Analytics**: Lead-to-deal conversion, deal-to-customer conversion, average deal value, weighted vs unweighted pipeline, sales cycle duration, lost reason distribution.
- **Marketing Analytics**: Total campaign spend, Cost Per Lead (CPL), Cost Per Acquisition (CPA), Return on Ad Spend (ROAS), source-wise acquisition breakdown.
- **Finance Analytics**: Total invoiced revenue, cash collections, accounts receivable aging (4 distinct legal brackets: 0-30, 31-45, 46-90, 90+ days), net operating cash flow, and strict masking of confidential payroll/banking data.

### 4. Grounded AI Business Insights Engine
- **Grounded & Verifiable**: All observations are generated deterministically from recorded transactional data (`leads`, `deals`, `tasks`, `invoices`, `branch_sales_targets`, `tickets`).
- **Confidence Scores & Disclaimers**: Every generated insight features a quantified confidence score (e.g. 0.92), supporting data key-value pairs, relevant record IDs, and an explicit advisory limitation disclaimer.
- **Actionable Diagnostic Recommendations**: Direct suggested corrective actions (e.g., prompt sales reps for immediate follow-up on stalling deals, redistribute support tickets breaching SLA, reassign overdue follow-ups).

### 5. Automated KPI Alerts Engine
- **Configurable Threshold Rules** stored in `custom_analytics_alerts`.
- **Supported Operational Metrics**:
  - `low_lead_conversion`, `overdue_payments`, `missed_followups`, `high_ticket_backlog`, `low_sales`, `high_expenses`, `branch_target_shortfall`, `subscription_usage_limit`.
- **Evaluation Engine**: Automated evaluation against condition operators (`>`, `<`, `>=`, `<=`, `==`) with dispatch to `email` and `in_app` channels.

### 6. India-First CRM Experience & Localization
- **Currency & Number Grouping**: Standard Indian numbering system in Lakhs (L) and Crores (Cr) with the Rupee symbol (`₹`). e.g., `₹12,34,567.89` and `₹1.23 Cr`.
- **Canonical Timezone**: `Asia/Kolkata` (IST, UTC+05:30) with Indian standard date display (`DD/MM/YYYY`).
- **Indian Business Hours**: Real-time status indicator (`09:00 AM - 06:00 PM IST`, Mon - Sat).
- **2026/2027 Gazetted Holidays**: Central government gazetted and restricted holiday calendar.
- **Phone Validation**: Normalization to E.164 +91 10-digit mobile phone numbers.
- **Regional Language Dictionaries**: 9 comprehensive business dictionaries:
  - English (`en`), Hindi (`hi`), Hinglish (`hinglish`), Tamil (`ta`), Telugu (`te`), Marathi (`mr`), Gujarati (`gu`), Kannada (`kn`), Bengali (`bn`).

### 7. Mobile Field Sales & Transparent GPS Check-In
- **Purpose-Limited GPS Verification**: Check-in and check-out capture latitude, longitude, and accuracy only at the moment of user-initiated action. Continuous background tracking is strictly avoided to protect employee battery and privacy.
- **Haversine Distance Computation**: Precise travel distance calculation between check-in and check-out coordinates.
- **Automated Workflow Actions**:
  - Automatic follow-up task creation in the Projects/Tasks module.
  - Automatic travel reimbursement claim creation in the Expenses ledger (`expenses`).
- **Safe Offline Synchronization**: Mobile field sales agents can capture visit notes offline; synchronizer employs UUID deduplication (`offline_client_uuid`) ensuring unsynchronized drafts are never prematurely treated as completed financial transactions.

---

## Database Architecture
1. **`dashboard_widget_layouts`**: Stores user/role widget configuration, positions, sizes, and active filters.
2. **`custom_analytics_alerts`**: Stores threshold rules, operators, target metrics, channel lists, and trigger history.
3. **`field_sales_visits`**: Stores appointment schedules, GPS coordinates, timestamps, distance km, outcome notes, follow-up links, and reimbursement links.
4. **`saved_reports`**: Extended with module, group_by, aggregations, and automated scheduling fields.

---

## API Endpoints Reference
- `GET /api/v1/crm/analytics/owner-dashboard`: Executive metrics with segregated actuals vs forecasts.
- `GET/POST /api/v1/crm/analytics/dashboard-layouts`: Retrieve and update personalized dashboard layouts.
- `GET /api/v1/crm/analytics/report-builder/catalog`: Catalog of 8 modules, fields, and relationships.
- `POST /api/v1/crm/analytics/report-builder/preview`: Live report query execution with aggregations.
- `POST /api/v1/crm/analytics/report-builder/save`: Save custom report definition.
- `POST /api/v1/crm/analytics/report-builder/export`: Export custom report to CSV.
- `GET /api/v1/crm/analytics/sales-deep-dive`: Sales cycle, conversion rates, and pipeline forecasting.
- `GET /api/v1/crm/analytics/marketing-deep-dive`: Campaign ROI, CPL, CPA, and attribution models.
- `GET /api/v1/crm/analytics/finance-deep-dive`: Invoicing, cash collections, and 4-bracket AR aging.
- `GET /api/v1/crm/analytics/ai-insights`: Permission-aware grounded business diagnostic insights.
- `GET/POST /api/v1/crm/analytics/alerts`: Manage metric threshold alert rules.
- `POST /api/v1/crm/analytics/alerts/evaluate`: Manually or chronologically evaluate all active alert rules.
- `GET /api/v1/crm/india/settings`: Default India-first settings (IST, INR, holidays, languages).
- `POST /api/v1/crm/india/format-inr`: Format amounts into Lakhs and Crores.
- `GET /api/v1/crm/india/translations/{lang}`: Fetch localized dictionary for regional language.
- `GET/POST /api/v1/crm/field-sales/visits`: List and schedule customer visits.
- `POST /api/v1/crm/field-sales/visits/{id}/check-in`: Transparent GPS check-in.
- `POST /api/v1/crm/field-sales/visits/{id}/check-out`: Transparent GPS check-out with distance & expense logging.
- `GET /api/v1/crm/field-sales/daily-plan`: Fetch daily itinerary for assigned field representative.
- `GET /api/v1/crm/field-sales/analytics`: Field visit completion and distance analytics.
- `POST /api/v1/crm/field-sales/sync-offline`: Synchronize offline mobile drafts with UUID idempotency.

---

## Automated Verification Status
- **Backend PHPUnit Tests**: `AdvancedBusinessAnalyticsAndFieldSalesTest.php` passing (12 tests, 173 assertions).
- **Total Backend Tests**: 390 tests passing, 2,661 assertions.
- **Frontend Integration Tests**: Step 64 verified, full suite passing (64 passed, 0 failed).
- **Frontend Production Build**: Vite production build passing with 0 errors (`dist/` assets generated).
