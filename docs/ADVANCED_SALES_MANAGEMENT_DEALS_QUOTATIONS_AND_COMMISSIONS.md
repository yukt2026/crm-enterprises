# Advanced Sales Management, Deal Pipelines, Quotations, Proposals & Commission System

## Overview
This module completes enterprise-grade sales operations inside the existing Enterprise CRM (Prompt 39). It extends existing Deals, Leads, Contacts, Companies, Quotations, and Invoices without introducing duplicate models or isolated schemas, ensuring strict multi-tenant isolation, tamper-evident cryptographic audit logs, and clear separation between internal approval workflows and legally-binding digital e-signatures.

---

## 1. Architecture & Multi-Pipeline System

### Multiple Sales Pipelines
Administrators can create distinct pipelines tailored for specific products, departments, or sales motions (e.g., Enterprise Direct, Channel Partner, SaaS Inbound):
- **Custom Stages & Probabilities**: Each stage features a unique probability percentage ($0\% - 100\%$) used in forecast models.
- **Enforced Transitions**: Explicit transition matrix rules preventing unauthorized stage jumps (e.g., direct move from Qualified to Closed-Won without an active Proposal stage).
- **Stage Ordering & Mandatory Fields**: Enforce required attributes before progressing deals.
- **Active/Inactive Status**: Safely retire obsolete pipelines without breaking historic deal audit records.
- **Dual Visual Modes**: Seamless toggle between interactive Kanban drag-and-drop boards and dense sortable tabular list views.

### Deal Workflow & Ownership Governance
- **CRM Entity Linking**: Seamless bidirectional linking to Accounts (Companies), Contacts, and Source Leads.
- **Ownership Reassignment Audit**: Every transfer of a deal records the previous owner, new owner, reason for transfer, timestamp, and operator in `crm_activities`.
- **Closed Deal Reopen Guard**: Reopening closed-won or closed-lost deals is guarded by permission checks, requires an explicit justification, and sets an authorized open stage with timestamped activity logging.
- **Won Deal to Project Onboarding**: Closed-Won deals can be converted with 1-click into client delivery projects (with default milestones, tasks, and budgets synced from the deal value).

---

## 2. Statistical Sales Forecasting Engine

The forecasting engine provides multi-dimensional projections while enforcing rigorous legal and statistical transparency:
- **Unweighted Pipeline**: Nominal sum of all active, open deal values ($Value_{total} = \sum V_i$).
- **Weighted Forecast**: Probabilistic revenue expectation computed as $Forecast_{weighted} = \sum (V_i \times P_i)$.
- **Committed Revenue Category**: Conservative projection filtering deals with $\ge 80\%$ stage probability.
- **Best-Case Category**: Upside projection including deals with $\ge 50\%$ stage probability.
- **Dimensional Breakdowns**: Grouped by employee/sales rep, branch/location, deal stage, and expected closing month bucket.
- **Explicit Assumptions Disclaimer**: All UI views and API endpoints feature the standard statistical notice:
  > *"Forecast values represent statistical projections based on deal stage probability matrices and are not guaranteed confirmed revenue."*

---

## 3. Quotation Builder & Discount Governance

- **Line Item Catalog & Pricing**: Interactive quotation builder with quantity, unit rates, and GST/Tax breakdown.
- **Multi-Level Discount Approval Matrix**:
  - Deals with discounts under rule thresholds auto-approve.
  - High discounts (e.g., $>15\%$ or $>25\%$) require designated manager/VP sign-off before dispatch.
- **Digital Customer Acceptance**: Public/authenticated acceptance recording timestamp, customer signatory, and IP address.
- **Duplicate-Guarded Invoice Conversion**: Prevents multiple invoices from being generated from a single quotation by atomically tracking `invoice_id` and locking already converted quotes.

---

## 4. Proposals, Contracts & Cryptographic E-Signatures

- **Contract Lifecycle**: Draft $\rightarrow$ Sent for Signature $\rightarrow$ Signed / Declined $\rightarrow$ Active.
- **Provider Support**: Configured for Aadhaar eSign, DocuSign, Internal OTP, and Clickwrap digital signatures.
- **Internal Approval vs. Legal E-Signature**: Strict separation: internal commercial sign-off does *not* substitute for external digital execution.
- **Tamper-Evident SHA-256 Audit Trail**:
  Every executed digital signature computes and records an immutable cryptographic audit hash:
  $$\text{Hash} = \text{SHA-256}(\text{ContractID} \parallel \text{ContractNumber} \parallel \text{SignerEmail} \parallel \text{SignedAtTimestamp} \parallel \text{ClientIP} \parallel \text{Provider})$$

---

## 5. Sales Commission Engine

- **Flexible Commission Plans**: Supports Fixed Percentage, Tiered Marginal Rates (e.g., 5% up to ₹50k, 8% up to ₹100k, 10% above ₹100k), and Milestone models.
- **Calculation Triggers**:
  - `deal_won`: Calculated immediately upon successful contract closing.
  - `payment_received`: Calculated upon confirmed customer invoice payment receipt.
- **Governance & Payout Workflow**: `pending_approval` $\rightarrow$ `approved` $\rightarrow$ `paid` (with bank payout reference tracking).
- **Clawback Protection**: When deals or contracts are cancelled/reversed, commissions are marked as `clawback_reversed` with full historical audit preserving financial reconciliation integrity.

---

## 6. Verification & Test Coverage

- **Backend Feature Tests**: `backend/tests/Feature/EnterpriseSalesAndCommissionTest.php`
  - 8 / 8 tests passing (100%), 50 assertions covering:
    - Pipeline and stage creation with transition rules.
    - Deal ownership transfer with audit trail.
    - Closed deal reopening with justification.
    - Converting won deal to onboarding delivery project.
    - Sales forecasting with statistical legal disclaimer.
    - Quotation discount governance, digital acceptance, and duplicate-guarded invoice conversion.
    - Contract dispatch and cryptographic SHA-256 e-signature execution.
    - Commission plans, calculation, approval, payout, and clawback reversal.
- **Frontend Integration Test Suite**: `frontend/src/tests/apiIntegration.test.ts`
  - Step 54 end-to-end integration test passes seamlessly.
  - **Overall suite result: 54 / 54 tests passed (0 failures)**.
- **Production Build**: Clean Vite/Node build (`dist/index.html`, bundle generated with 0 errors).
