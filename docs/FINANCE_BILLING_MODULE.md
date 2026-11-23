# Prompt 09: Finance, Quotations, Invoices, Payments & Refunds

## Executive Overview
The **Finance & Billing Module** provides a robust, multi-tenant billing engine designed for the Enterprise AI CRM SaaS platform. It bridges CRM deals and contacts directly with product catalog pricing, warehouse inventory tracking, progressive billing, payment receipts, refunds, and verified online payments.

---

## Architecture & Core Capabilities

### 1. Quotations & Revisions Engine
- **Numbering Sequence**: `QT-YYYYMM-XXXX`.
- **Relationship Hierarchy**: Linked with Leads, Contacts, Companies, and Deals.
- **Revision Branching**:
  - Automatically branches new version (`v1` $\rightarrow$ `v2` $\rightarrow$ `v3`) with format `QT-YYYYMM-XXXX-R2`.
  - Maintains `parent_quotation_id` pointing to the root proposal for complete lifecycle traceability.
- **Tax & Discount Mathematical Calculations**:
  - **Tax-Exclusive Formula**:
    $$\text{Net Line} = (\text{Quantity} \times \text{Unit Price}) - \text{Discount}$$
    $$\text{Line Tax} = \text{Net Line} \times \left(\frac{\text{Tax Rate}}{100}\right)$$
    $$\text{Line Total} = \text{Net Line} + \text{Line Tax}$$
    $$\text{Grand Total} = \sum \text{Net Lines} + \sum \text{Line Taxes} - \text{Document Discount}$$
  - **Tax-Inclusive Formula**:
    $$\text{Base Subtotal} = \frac{\text{Net Line}}{1 + \frac{\text{Tax Rate}}{100}}$$
    $$\text{Line Tax} = \text{Net Line} - \text{Base Subtotal}$$
    $$\text{Line Total} = \text{Net Line}$$
    $$\text{Grand Total} = \sum \text{Line Totals} - \text{Document Discount}$$
- **One-Click Conversion**: Accepted quotations can be directly converted into official tax invoices.

### 2. Invoices & Billing Engine
- **Numbering Sequence**: `INV-YYYYMM-XXXX`.
- **Statuses**: `draft`, `issued`, `partially_paid`, `paid`, `overdue`, `void`, `cancelled`.
- **Inventory Integration (`InventoryService`)**:
  - Automatically identifies tangible stock products with `track_inventory === true`.
  - Upon invoice issuance (`issueInvoice`), records an immutable `sale_deduction` stock movement ledger entry, decrementing warehouse stock on hand.
- **Overdue Tracking**: Identifies unpaid bills where `due_date < today`.
- **Document Output**: Native printable HTML/PDF generator rendering logo, tenant metadata, customer billing details, line items table, and bank terms.

### 3. Payments & Gateway Integration (Razorpay)
- **Golden Rule Enforced**:
  - *Never marks an invoice as paid based only on client-side redirects or unverified frontend requests.*
  - Strict server-side verification via `hash_hmac('sha256', order_id . '|' . payment_id, secret)`.
- **HMAC SHA256 Webhook Verification**:
  - Ingestion endpoint: `/api/v1/webhooks/razorpay` verifies incoming `X-Razorpay-Signature` against `RAZORPAY_WEBHOOK_SECRET`.
  - **Idempotency Protection**: Verifies existing `gateway_payment_id` on the `payments` table to reject duplicate webhook deliveries gracefully.
- **Partial & Split Payments**:
  - Supports multiple installment payments against a single invoice.
  - Dynamically recalculates `paid_amount` and `outstanding_amount`.
  - Transitions invoice from `issued` $\rightarrow$ `partially_paid` $\rightarrow$ `paid`.
- **Receipts**:
  - Sequential receipt generation: `RCT-YYYYMM-XXXX`.
- **Refunds Workflow**:
  - Sequential refund numbers: `REF-YYYYMM-XXXX`.
  - Limits refund amount strictly to `amount - refunded_amount`.
  - Automatically updates payment `refunded_amount` and restores `outstanding_amount` on the invoice, reverting status from `paid` back to `partially_paid` or `issued`.

---

## Database Schema Additions

### `quotations` & `quotation_items`
- Columns added: `quote_number`, `version`, `parent_quotation_id`, `lead_id`, `contact_id`, `company_id`, `deal_id`, `discount_type`, `discount_rate`, `discount_amount`, `is_tax_inclusive`, `accepted_at`, `rejected_at`, `viewed_at`.

### `invoices` & `invoice_items`
- Columns added: `quotation_id`, `deal_id`, `contact_id`, `company_id`, `currency`, `discount_type`, `discount_rate`, `discount_amount`, `shipping_amount`, `paid_amount`, `outstanding_amount`, `is_tax_inclusive`, `billing_name`, `billing_email`, `billing_phone`, `billing_address`, `billing_tax_id`, `razorpay_order_id`, `razorpay_payment_link`, `issued_at`, `paid_at`, `voided_at`.

### `payments`
- Columns added: `receipt_number`, `payment_channel`, `gateway_order_id`, `gateway_payment_id`, `gateway_signature`, `refunded_amount`, `user_id`.

### `refunds` & `credit_notes`
- Tables created with multi-tenant foreign keys, status tracking, and audit relationships.

---

## API Routes Reference

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/crm/quotations` | List quotations with status, customer, and search filters |
| `POST` | `/api/v1/crm/quotations` | Create draft quotation with items and tax calculations |
| `GET` | `/api/v1/crm/quotations/{id}` | Show quotation with items and revision history |
| `POST` | `/api/v1/crm/quotations/{id}/revise` | Create a new revision branching from this quotation |
| `PATCH` | `/api/v1/crm/quotations/{id}/status` | Update status (`sent`, `viewed`, `accepted`, `rejected`) |
| `POST` | `/api/v1/crm/quotations/{id}/convert-to-invoice` | Convert accepted quotation to official Tax Invoice |
| `GET` | `/api/v1/crm/quotations/{id}/pdf` | Printable HTML/PDF proposal |
| `GET` | `/api/v1/crm/invoices` | List invoices with status (including `overdue`), search |
| `POST` | `/api/v1/crm/invoices` | Create invoice |
| `GET` | `/api/v1/crm/invoices/{id}` | Show invoice details, payments ledger, and refunds |
| `POST` | `/api/v1/crm/invoices/{id}/issue` | Issue invoice & trigger catalog warehouse stock deduction |
| `POST` | `/api/v1/crm/invoices/{id}/void` | Void invoice with audit reason |
| `POST` | `/api/v1/crm/invoices/{id}/payments` | Record manual payment (bank transfer, cash, card, upi) |
| `POST` | `/api/v1/crm/invoices/{id}/payment-link` | Generate Razorpay order & checkout link |
| `POST` | `/api/v1/crm/invoices/{id}/verify-payment` | Verify online Razorpay checkout cryptographic HMAC signature |
| `GET` | `/api/v1/crm/invoices/{id}/pdf` | Printable HTML/PDF Tax Invoice |
| `GET` | `/api/v1/crm/payments` | List payments and receipts ledger |
| `POST` | `/api/v1/crm/payments/{id}/refund` | Process partial or full refund against payment |
| `GET` | `/api/v1/crm/finance/summary` | Executive KPIs (Total Invoiced, Collected, Receivables, Overdue) |
| `POST` | `/api/v1/webhooks/razorpay` | Public Razorpay Webhook with HMAC SHA256 validation & duplicate idempotency protection |

---

## Frontend Architecture

- **`FinanceBillingHubView.tsx`**: Executive container housing `FinanceSummaryDashboard` KPI cards and tabbed navigation across Invoices, Quotations, and Payments.
- **`QuotationsManagerView.tsx`**: Interactive quotation list, filter tabs, dynamic Line Item editor with live computed tax-exclusive/inclusive breakdown, Revision Drawer, and Convert-to-Invoice action.
- **`InvoicesManagerView.tsx`**: Invoicing ledger, payment collection progress bars, manual payment modal, Razorpay online checkout simulator, and Tax Invoice PDF generator.
- **`PaymentsTransactionsView.tsx`**: Receipts ledger with transaction UTR tracking and refund initiation modal.
- **React Query Hooks (`useFinance.ts`)**: Cache management with automatic invalidation across invoices, quotations, inventory, and finance KPIs.

---

## Verification Results
- **PHPUnit Backend Feature Tests**: 50 passed (296 assertions), including all 9 test cases in `FinanceBillingModuleTest`.
- **Frontend Production Build**: `vite build` completed cleanly in 13.56s with 0 errors.
- **End-to-End Integration Suite**: `npm.cmd run test:integration` completed: 12 passed, 0 failed.
