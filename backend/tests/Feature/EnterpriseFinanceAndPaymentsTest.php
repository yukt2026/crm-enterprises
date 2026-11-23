<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Deal;
use App\Domains\Finance\Models\CreditNote;
use App\Domains\Finance\Models\Expense;
use App\Domains\Finance\Models\FinanceTransaction;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\InvoiceItem;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\PaymentAllocation;
use App\Domains\Finance\Models\PaymentGatewayConfig;
use App\Domains\Finance\Models\PaymentLink;
use App\Domains\Finance\Models\Refund;
use App\Domains\Finance\Models\VendorBill;
use App\Domains\Finance\Models\VendorPayment;
use App\Domains\Inventory\Models\Supplier;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterpriseFinanceAndPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Bharat Fintech Solutions Pvt Ltd',
            'domain' => 'bharatfintech' . uniqid(),
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'role' => 'Business Admin',
            'is_platform_admin' => false,
        ]);
        $this->user->workspaces()->attach($this->tenant->id, ['role' => 'Business Admin', 'status' => 'active']);

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tata Technologies Enterprise',
            'email' => 'finance@tatatech.com',
            'phone' => '+919876500001',
            'currency' => 'INR',
        ]);
    }

    /**
     * 1. Test Payment Gateway Configuration & Dynamic NPCI UPI QR code generation.
     */
    public function test_payment_gateway_config_and_dynamic_upi_qr_generation(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/crm/payments/gateways', [
            'provider' => 'upi_qr',
            'display_name' => 'Bharat QR UPI',
            'upi_vpa' => 'tatatech@icici',
            'merchant_name' => 'Bharat Fintech Solutions',
            'is_active' => true,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('payment_gateways_config', [
            'tenant_id' => $this->tenant->id,
            'provider' => 'upi_qr',
            'upi_vpa' => 'tatatech@icici',
        ]);

        // Generate dynamic NPCI UPI QR
        $upiRes = $this->actingAs($this->user)->postJson('/api/v1/crm/payments/upi-qr', [
            'amount' => 45000.50,
            'vpa' => 'tatatech@icici',
            'merchant_name' => 'Bharat Fintech Solutions',
            'reference_id' => 'INV-2026-001',
            'note' => 'Quarterly ERP Implementation',
        ]);

        $upiRes->assertStatus(200)
            ->assertJsonPath('data.currency', 'INR')
            ->assertJsonPath('data.vpa', 'tatatech@icici');

        $upiString = $upiRes->json('data.upi_string');
        $this->assertStringContainsString('upi://pay?', $upiString);
        $this->assertStringContainsString('pa=tatatech@icici', $upiString);
        $this->assertStringContainsString('am=45000.50', $upiString);
        $this->assertStringContainsString('cu=INR', $upiString);
    }

    /**
     * 2. Test Payment Link Generation linked to Invoice.
     */
    public function test_payment_link_generation_linked_to_invoice(): void
    {
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-2026-9001',
            'currency' => 'INR',
            'subtotal' => 100000.00,
            'tax_amount' => 18000.00,
            'total_amount' => 118000.00,
            'paid_amount' => 0.00,
            'outstanding_amount' => 118000.00,
            'status' => 'issued',
            'due_date' => Carbon::now()->addDays(15),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/crm/payment-links', [
            'title' => 'Payment for Invoice ' . $invoice->invoice_number,
            'amount' => 118000.00,
            'currency' => 'INR',
            'customer_id' => $this->customer->id,
            'payable_type' => Invoice::class,
            'payable_id' => $invoice->id,
            'gateway_provider' => 'razorpay',
            'amount_type' => 'due_amount',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.amount', '118000.00')
            ->assertJsonPath('data.status', 'active');

        $token = $response->json('data.link_token');
        $this->assertNotEmpty($token);

        // Public retrieval of payment link
        $publicRes = $this->getJson("/api/v1/public/payment-links/{$token}");
        $publicRes->assertStatus(200)
            ->assertJsonPath('data.link_token', $token)
            ->assertJsonPath('data.amount', '118000.00');
    }

    /**
     * 3. Test Webhook Payment Ingestion with Idempotency Protection against duplicate events.
     */
    public function test_webhook_payment_processing_with_idempotency_deduplication(): void
    {
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-2026-9002',
            'currency' => 'INR',
            'subtotal' => 50000.00,
            'tax_amount' => 9000.00,
            'total_amount' => 59000.00,
            'paid_amount' => 0.00,
            'outstanding_amount' => 59000.00,
            'status' => 'issued',
        ]);

        $webhookPayload = [
            'event_id' => 'evt_rzp_test_unique_998877',
            'payment_id' => 'pay_rzp_live_123456789',
            'order_id' => 'order_rzp_live_987654321',
            'amount' => 59000.00,
            'currency' => 'INR',
            'method' => 'upi',
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
        ];

        // 1st delivery of webhook -> processed
        $firstRes = $this->actingAs($this->user)->postJson('/api/v1/crm/payments/webhook/razorpay', $webhookPayload);
        $firstRes->assertStatus(200)
            ->assertJsonPath('data.status', 'processed');

        $invoice->refresh();
        $this->assertEquals(59000.00, (float) $invoice->paid_amount);
        $this->assertEquals(0.00, (float) $invoice->outstanding_amount);
        $this->assertEquals('paid', $invoice->status);

        $this->assertDatabaseHas('payments', [
            'tenant_id' => $this->tenant->id,
            'gateway_payment_id' => 'pay_rzp_live_123456789',
            'amount' => 59000.00,
        ]);

        $this->assertDatabaseHas('finance_transactions', [
            'tenant_id' => $this->tenant->id,
            'webhook_event_id' => 'evt_rzp_test_unique_998877',
            'is_posted' => true,
        ]);

        // 2nd delivery of the EXACT same webhook event -> deduplicated safely!
        $secondRes = $this->actingAs($this->user)->postJson('/api/v1/crm/payments/webhook/razorpay', $webhookPayload);
        $secondRes->assertStatus(200)
            ->assertJsonPath('data.status', 'already_processed');

        // Verify no duplicate payment record created
        $this->assertEquals(1, Payment::where('gateway_payment_id', 'pay_rzp_live_123456789')->count());
    }

    /**
     * 4. Test Partial and Advance Payment Allocation across Invoices.
     */
    public function test_partial_and_advance_payment_allocation(): void
    {
        // Customer makes advance lump-sum deposit of ₹1,00,000
        $payment = Payment::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'receipt_number' => 'REC-ADV-100',
            'amount' => 100000.00,
            'currency' => 'INR',
            'payment_date' => Carbon::now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'payment_channel' => 'manual',
            'status' => 'successful',
        ]);

        // Invoice 1: ₹60,000
        $invoice1 = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-SPLIT-01',
            'currency' => 'INR',
            'total_amount' => 60000.00,
            'paid_amount' => 0.00,
            'outstanding_amount' => 60000.00,
            'status' => 'issued',
        ]);

        // Invoice 2: ₹50,000
        $invoice2 = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-SPLIT-02',
            'currency' => 'INR',
            'total_amount' => 50000.00,
            'paid_amount' => 0.00,
            'outstanding_amount' => 50000.00,
            'status' => 'issued',
        ]);

        // Allocate ₹60,000 from advance payment to Invoice 1 (full settlement)
        $alloc1 = $this->actingAs($this->user)->postJson("/api/v1/crm/payments/{$payment->id}/allocate", [
            'invoice_id' => $invoice1->id,
            'amount' => 60000.00,
        ]);
        $alloc1->assertStatus(200);

        $invoice1->refresh();
        $this->assertEquals(60000.00, (float) $invoice1->paid_amount);
        $this->assertEquals(0.00, (float) $invoice1->outstanding_amount);
        $this->assertEquals('paid', $invoice1->status);

        // Allocate remaining ₹40,000 to Invoice 2 (partial settlement)
        $alloc2 = $this->actingAs($this->user)->postJson("/api/v1/crm/payments/{$payment->id}/allocate", [
            'invoice_id' => $invoice2->id,
            'amount' => 40000.00,
        ]);
        $alloc2->assertStatus(200);

        $invoice2->refresh();
        $this->assertEquals(40000.00, (float) $invoice2->paid_amount);
        $this->assertEquals(10000.00, (float) $invoice2->outstanding_amount);
        $this->assertEquals('partially_paid', $invoice2->status);

        // Attempting to over-allocate from exhausted balance must fail with 422
        $allocOver = $this->actingAs($this->user)->postJson("/api/v1/crm/payments/{$payment->id}/allocate", [
            'invoice_id' => $invoice2->id,
            'amount' => 5000.00,
        ]);
        $allocOver->assertStatus(422);
    }

    /**
     * 5. Test Accounts Receivable (AR) Ageing Report, Promise-to-Pay & Disputes.
     */
    public function test_accounts_receivable_ageing_report_and_promise_to_pay(): void
    {
        // 0-30 days overdue
        Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-AGE-1',
            'currency' => 'INR',
            'total_amount' => 20000.00,
            'outstanding_amount' => 20000.00,
            'status' => 'issued',
            'due_date' => Carbon::now()->subDays(15),
        ]);

        // 31-45 days overdue
        Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-AGE-2',
            'currency' => 'INR',
            'total_amount' => 30000.00,
            'outstanding_amount' => 30000.00,
            'status' => 'issued',
            'due_date' => Carbon::now()->subDays(35),
        ]);

        // 90+ days overdue
        $disputedInv = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-AGE-3',
            'currency' => 'INR',
            'total_amount' => 50000.00,
            'outstanding_amount' => 50000.00,
            'status' => 'issued',
            'due_date' => Carbon::now()->subDays(100),
        ]);

        // Fetch AR Ageing report
        $reportRes = $this->actingAs($this->user)->getJson('/api/v1/crm/receivables/ageing');
        $reportRes->assertStatus(200)
            ->assertJsonPath('data.summary.bucket_0_30', 20000)
            ->assertJsonPath('data.summary.bucket_31_45', 30000)
            ->assertJsonPath('data.summary.bucket_90_plus', 50000);

        // Record customer promise to pay
        $promiseRes = $this->actingAs($this->user)->postJson("/api/v1/crm/receivables/invoices/{$disputedInv->id}/promise-to-pay", [
            'promise_to_pay_date' => Carbon::now()->addDays(10)->toDateString(),
            'notes' => 'Customer CFO promised clearance via RTGS next Friday',
        ]);
        $promiseRes->assertStatus(200)
            ->assertJsonPath('data.recovery_status', 'promise_to_pay');

        // Flag as disputed
        $disputeRes = $this->actingAs($this->user)->postJson("/api/v1/crm/receivables/invoices/{$disputedInv->id}/dispute", [
            'is_disputed' => true,
            'reason' => 'Discrepancy in milestone acceptance criteria deliverables',
        ]);
        $disputeRes->assertStatus(200)
            ->assertJsonPath('data.is_disputed', true);
    }

    /**
     * 6. Test Accounts Payable (AP), Vendor Bills & Disbursed Payments.
     */
    public function test_accounts_payable_vendor_bills_and_disbursement(): void
    {
        $vendor = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dell Technologies Hardware Vendor',
            'email' => 'billing@dell.com',
            'status' => 'active',
        ]);

        // Create vendor bill
        $billRes = $this->actingAs($this->user)->postJson('/api/v1/crm/payables/bills', [
            'supplier_id' => $vendor->id,
            'bill_number' => 'DELL-BILL-901',
            'total_amount' => 80000.00,
            'currency' => 'INR',
            'due_date' => Carbon::now()->addDays(30)->toDateString(),
            'notes' => 'Server rack hardware procurement',
        ]);

        $billRes->assertStatus(200)
            ->assertJsonPath('data.status', 'pending_approval')
            ->assertJsonPath('data.outstanding_amount', '80000.00');

        $billId = $billRes->json('data.id');

        // Approve vendor bill
        $approveRes = $this->actingAs($this->user)->postJson("/api/v1/crm/payables/bills/{$billId}/approve");
        $approveRes->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        // Disburse partial payment of ₹50,000 to vendor
        $payRes = $this->actingAs($this->user)->postJson('/api/v1/crm/payables/payments', [
            'supplier_id' => $vendor->id,
            'vendor_bill_id' => $billId,
            'amount' => 50000.00,
            'currency' => 'INR',
            'payment_method' => 'bank_transfer',
            'reference_number' => 'HDFCNEFT9920199',
            'notes' => 'Approved hardware advance tranche',
        ]);

        $payRes->assertStatus(200)
            ->assertJsonPath('data.status', 'disbursed');

        $this->assertDatabaseHas('vendor_bills', [
            'id' => $billId,
            'paid_amount' => 50000.00,
            'outstanding_amount' => 30000.00,
            'status' => 'partially_paid',
        ]);

        // Fetch AP Ageing report
        $apAgeing = $this->actingAs($this->user)->getJson('/api/v1/crm/payables/ageing');
        $apAgeing->assertStatus(200)
            ->assertJsonPath('data.summary.total_payables', 30000);
    }

    /**
     * 7. Test Refund Request, Approval Policy & Credit Note Issuance.
     */
    public function test_refund_request_approval_workflow_and_credit_note(): void
    {
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-REF-01',
            'total_amount' => 25000.00,
            'paid_amount' => 25000.00,
            'outstanding_amount' => 0.00,
            'status' => 'paid',
        ]);

        $payment = Payment::create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'receipt_number' => 'REC-REF-01',
            'amount' => 25000.00,
            'currency' => 'INR',
            'payment_date' => Carbon::now()->toDateString(),
            'payment_method' => 'upi',
            'status' => 'successful',
        ]);

        // 1. Submit refund request (pending approval)
        $reqRes = $this->actingAs($this->user)->postJson("/api/v1/crm/payments/{$payment->id}/refund-request", [
            'amount' => 10000.00,
            'reason' => 'Service downgrade requested within satisfaction period',
        ]);

        $reqRes->assertStatus(200)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.approval_status', 'pending_approval');

        $refundId = $reqRes->json('data.id');

        // 2. Manager approves refund
        $appRes = $this->actingAs($this->user)->postJson("/api/v1/crm/refunds/{$refundId}/approve", [
            'notes' => 'Concession approved by VP Finance',
        ]);

        $appRes->assertStatus(200)
            ->assertJsonPath('data.status', 'processed')
            ->assertJsonPath('data.approval_status', 'approved');

        $payment->refresh();
        $this->assertEquals(10000.00, (float) $payment->refunded_amount);

        // Verify Credit Note generated
        $this->assertDatabaseHas('credit_notes', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => 10000.00,
        ]);

        // Verify reversal ledger transaction created
        $this->assertDatabaseHas('finance_transactions', [
            'tenant_id' => $this->tenant->id,
            'provider_status' => 'refunded',
            'amount' => -10000.00,
        ]);
    }

    /**
     * 8. Test Expense Management, Reimbursements & Manager Approval.
     */
    public function test_expense_management_reimbursement_and_approval(): void
    {
        $createRes = $this->actingAs($this->user)->postJson('/api/v1/crm/expenses', [
            'title' => 'Client Onsite Workshop Travel & Hospitality',
            'category' => 'travel',
            'amount' => 14500.00,
            'tax_amount' => 2610.00,
            'is_tax_inclusive' => false,
            'currency' => 'INR',
            'is_reimbursement' => true,
            'receipt_url' => 'https://enterprise-cdn.com/receipts/flight_boarding_01.pdf',
        ]);

        $createRes->assertStatus(200)
            ->assertJsonPath('data.category', 'travel')
            ->assertJsonPath('data.status', 'pending_approval')
            ->assertJsonPath('data.is_reimbursement', true);

        $expenseId = $createRes->json('data.id');

        // Approve expense
        $approveRes = $this->actingAs($this->user)->postJson("/api/v1/crm/expenses/{$expenseId}/approve");
        $approveRes->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        // Disburse reimbursement to employee
        $payRes = $this->actingAs($this->user)->postJson("/api/v1/crm/expenses/{$expenseId}/pay", [
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'SALARY-REIMB-88910',
        ]);

        $payRes->assertStatus(200)
            ->assertJsonPath('data.status', 'reimbursed');

        $this->assertDatabaseHas('expenses', [
            'id' => $expenseId,
            'status' => 'reimbursed',
        ]);
    }

    /**
     * 9. Test Comprehensive Financial Reporting Distinguishes Cash Collected vs Invoiced vs Forecast Revenue.
     */
    public function test_comprehensive_financial_reporting_distinguishes_cash_vs_invoiced_vs_forecast(): void
    {
        // 1. Create an open Deal (Forecast pipeline only!)
        Deal::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Enterprise Cloud Migration Deal',
            'title' => 'Enterprise Cloud Migration Deal',
            'value' => 500000.00,
            'stage' => 'negotiation',
            'probability' => 80,
        ]);

        // 2. Create an Invoice (Invoiced revenue demand: ₹1,50,000)
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-REPORT-01',
            'total_amount' => 150000.00,
            'paid_amount' => 100000.00,
            'outstanding_amount' => 50000.00,
            'status' => 'partially_paid',
        ]);

        // 3. Create actual confirmed cash payment (Cash Collected: ₹1,00,000)
        Payment::create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'receipt_number' => 'REC-REPORT-01',
            'amount' => 100000.00,
            'currency' => 'INR',
            'payment_date' => Carbon::now()->toDateString(),
            'payment_method' => 'upi',
            'status' => 'successful',
        ]);

        $reportRes = $this->actingAs($this->user)->getJson('/api/v1/crm/finance/comprehensive-report');
        $reportRes->assertStatus(200);

        $metrics = $reportRes->json('data.revenue_vs_cash_metrics');

        // Verification of fundamental financial distinction:
        // Cash collected (₹1,00,000) != Invoiced revenue (₹1,50,000) != Forecast pipeline (₹5,00,000)
        $this->assertEquals(100000.00, $metrics['cash_collected']);
        $this->assertEquals(150000.00, $metrics['invoiced_revenue']);
        $this->assertEquals(500000.00, $metrics['forecast_pipeline_revenue']);
        $this->assertEquals(50000.00, $metrics['variance_invoiced_vs_collected']);
        $this->assertNotEmpty($metrics['accounting_notice']);

        // Check Cash Flow
        $cashflow = $reportRes->json('data.cash_flow');
        $this->assertEquals(100000.00, $cashflow['total_inflow']);
    }
}
