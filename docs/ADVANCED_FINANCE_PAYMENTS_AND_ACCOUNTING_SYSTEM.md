# Complete Advanced Finance and Payment Management System for Indian Businesses

## Executive Overview
The **Advanced Finance and Payment Management System** (Prompt 40) upgrades the existing CRM Finance and Invoicing modules into a multi-gateway, provider-independent, accounting-compliant financial management and reconciliation system.

It links together Customer Accounts, Deals, Quotations, Invoices, Accounts Receivable (AR), Accounts Payable (AP), Expense Reimbursements, and Bank/Gateway Settlements into a single unified financial ledger.

---

## Key Pillars of Implementation

### 1. Provider-Independent Payment Gateway Architecture & Dynamic UPI QR
- **Configurable Gateways**: Supports Razorpay, Cashfree, Stripe, PayU, and dynamic NPCI UPI QR codes.
- **Dynamic UPI QR Code Generation**: 
  - Standard compliant `upi://pay?pa={vpa}&pn={payee}&am={amount}&cu=INR&tr={transaction_ref}&tn={notes}` deep link.
  - Automatically embedded in invoice receipts and payment links with Base64 QR visual rendering.
- **Strict Verification Safeguard**: Payments are **never** marked as successful purely from browser redirects. Webhook signature validation and server-to-server gateway queries (`/verify` / `/webhook`) are enforced.

### 2. Universal Payment Links
- **Polymorphic Link Attachment**: Links can be generated for Customers, Invoices, Quotations, Deals, Projects, or Custom Orders.
- **Customizable Billing**: Supports Full Payment, Advance/Partial Amount, Fixed Deposit, or Minimum Due.
- **Secure Public Access**: Tokenized public links (`/p/{token}`) allow clients to review invoice line items and settle payments without requiring CRM credentials.

### 3. Payment Reconciliation Register & Financial Ledger
- **Transaction Register (`finance_transactions`)**:
  - Captures gross amounts, gateway fees, GST on transaction fees, and net realized amounts.
  - Reconciliation states: `matched`, `unreconciled`, `discrepancy`, `manual_override`.
- **Idempotency Protection**: Webhook deliveries are deduplicated by gateway reference and idempotency key, preventing double-crediting.
- **Immutable Ledger Record**: Posted financial ledger entries cannot be deleted; adjustments are handled via balanced reversal entries.

### 4. Advance & Partial Payments with Atomic Multi-Invoice Allocation
- **Unallocated Advance Deposits**: Advance payments can be accepted without an immediate invoice.
- **Atomic Allocation (`payment_allocations`)**:
  - Unallocated balances can be apportioned across one or multiple outstanding invoices.
  - Uses `DB::transaction()` and database row locks (`lockForUpdate()`) to prevent race conditions and over-allocation.
  - Automatically transitions invoices (`unpaid` $\to$ `partially_paid` $\to$ `paid`).

### 5. Accounts Receivable (AR) & Debt Recovery
- **Standard 4-Bracket Ageing**:
  - `0 - 30 days` (Current)
  - `31 - 45 days` (Attention)
  - `46 - 90 days` (Delinquent)
  - `90+ days` (Critical / Bad Debt Risk)
- **Recovery Operations**:
  - **Promise-to-Pay (PTP)**: Records committed date, promised amount, and notes.
  - **Dispute Flagging**: Flags invoices in commercial dispute, pausing automated dunning.
  - **Collection Agent Assignment**: Assigns recovery tasks to internal staff or external legal agencies.

### 6. Accounts Payable (AP) & Vendor Disbursements
- **Vendor Profiles (`suppliers`) & Vendor Bills (`vendor_bills`)**:
  - Tracks purchase bills with GSTIN, HSN/SAC codes, and purchase order references.
  - 3-tier bill status: `draft` $\to$ `pending_approval` $\to$ `approved` $\to$ `partially_paid` $\to$ `paid`.
- **Vendor Disbursements (`vendor_payments`)**:
  - Records payments to vendors with payment mode (NEFT/RTGS, UPI, Cheque), reference numbers, and UTRs.
  - Updates vendor bill outstanding amounts atomically.
- **AP Ageing**: Calculates overdue liabilities owed to suppliers.

### 7. Refund Management & Credit Notes
- **Two-Step Approval**: Refunds start in `pending_approval` state, preventing unauthorized cash disbursements.
- **Credit Note Issuance**: Approving a refund automatically issues a balanced Credit Note (`CN-YYYYMM-XXXXXX`) and updates invoice paid amounts.

### 8. Employee Expense Reimbursements
- **Expense Register (`expenses`)**:
  - Categorized under `travel`, `food_entertainment`, `office_supplies`, `software_saas`, `marketing`, `logistics`, `utilities`, `professional_services`, etc.
  - Receipts attached with approval workflows (`pending_approval`, `approved`, `rejected`, `reimbursed`).
- **Disbursement Tracking**: Records payment reference and reimbursement method.

### 9. Comprehensive Financial Reporting & Revenue Distinctions
The reporting engine (`AdvancedFinanceReportService`) mathematically distinguishes between three revenue tiers:
1. **Cash Collected (Liquid)**: Actual cleared bank/gateway inflows realized during the period.
2. **Invoiced Revenue (Demanded)**: Value of GST tax invoices raised during the period (accrual accounting).
3. **CRM Pipeline Forecast (Projected)**: Weighted potential deal pipeline values, clearly labeled with disclaimers indicating they are not realizable bank deposits.

---

## Backend Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/crm/payments/gateways` | List configured payment gateways |
| `POST` | `/api/v1/crm/payments/gateways` | Save/update payment gateway credentials |
| `POST` | `/api/v1/crm/payments/upi-qr` | Generate dynamic NPCI UPI QR code |
| `GET` | `/api/v1/crm/payments/links` | List generated payment links |
| `POST` | `/api/v1/crm/payments/links` | Create universal payment link |
| `GET` | `/api/v1/crm/payments/links/public/{token}` | Public token retrieval for client checkout |
| `POST` | `/api/v1/crm/payments/{id}/allocate` | Atomically allocate payment to invoices |
| `GET` | `/api/v1/crm/payments/transactions` | Transaction ledger with gateway fees & net amount |
| `POST` | `/api/v1/crm/payments/transactions/{id}/reconcile` | Reconcile transaction against bank statement |
| `POST` | `/api/v1/crm/payments/refunds` | Initiate refund request |
| `POST` | `/api/v1/crm/payments/refunds/{id}/approve` | Approve refund & auto-issue Credit Note |
| `GET` | `/api/v1/crm/receivables/ageing` | Accounts Receivable 4-bracket ageing report |
| `POST` | `/api/v1/crm/receivables/invoices/{id}/promise-to-pay` | Set Promise-to-Pay commitment |
| `POST` | `/api/v1/crm/receivables/invoices/{id}/dispute` | Flag/unflag invoice in dispute |
| `POST` | `/api/v1/crm/receivables/invoices/{id}/assign` | Assign collection agent |
| `GET` | `/api/v1/crm/payables/bills` | List vendor purchase bills |
| `POST` | `/api/v1/crm/payables/bills` | Create vendor purchase bill |
| `POST` | `/api/v1/crm/payables/bills/{id}/approve` | Manager approval for vendor bill |
| `GET` | `/api/v1/crm/payables/payments` | List vendor disbursements |
| `POST` | `/api/v1/crm/payables/payments` | Record vendor disbursement |
| `GET` | `/api/v1/crm/payables/ageing` | Accounts Payable ageing analysis |
| `GET` | `/api/v1/crm/expenses` | List employee/corporate expenses |
| `POST` | `/api/v1/crm/expenses` | Submit expense claim |
| `POST` | `/api/v1/crm/expenses/{id}/approve` | Approve expense claim |
| `POST` | `/api/v1/crm/expenses/{id}/pay` | Disburse expense reimbursement |
| `GET` | `/api/v1/crm/finance/reports/comprehensive` | Comprehensive multi-tier financial report |

---

## Frontend Hub Views
Available under `FinanceBillingHubView.tsx`:
1. **Overview & Analytics** (Cash vs Invoiced KPIs, liquidity, collection efficiency)
2. **Invoices** (GST billing, line items, PDF generation, instant payment links)
3. **Payment Gateways & UPI QR** (Gateway credential forms, NPCI dynamic QR tester)
4. **Payment Links** (Shareable links with multi-entity binding and public token view)
5. **Reconciliation & Transactions** (Transaction register, fee deduction, reconciliation tags)
6. **Accounts Receivable (AR)** (Ageing brackets, dispute triggers, recovery agent assignments)
7. **Accounts Payable (AP)** (Vendor purchase bills, approval workflows, disbursements)
8. **Expense Reimbursements** (Claims, category breakdowns, approval actions)
