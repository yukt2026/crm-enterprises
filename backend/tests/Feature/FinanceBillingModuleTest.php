<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\Quotation;
use App\Domains\Finance\Models\Refund;
use App\Domains\Finance\Services\InvoiceBillingService;
use App\Domains\Finance\Services\PaymentGatewayService;
use App\Domains\Finance\Services\QuotationService;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\InventoryService;
use App\Domains\Sales\Models\Product;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tenancy\Services\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceBillingModuleTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userAdmin;
    protected User $userB;
    protected string $tokenAdmin;
    protected string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Acme Corporation',
            'slug' => 'acme-corp',
            'is_active' => true,
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Beta Global',
            'slug' => 'beta-global',
            'is_active' => true,
        ]);

        $this->userAdmin = User::create([
            'name' => 'Alice Admin',
            'email' => 'alice.finance@acme.com',
            'password' => bcrypt('password123'),
            'role' => 'Business Admin',
            'current_tenant_id' => $this->tenantA->id,
        ]);
        $this->userAdmin->workspaces()->attach($this->tenantA->id, ['role' => 'Business Admin', 'status' => 'active']);

        $this->userB = User::create([
            'name' => 'Bob Beta',
            'email' => 'bob.finance@beta.com',
            'password' => bcrypt('password123'),
            'role' => 'Business Admin',
            'current_tenant_id' => $this->tenantB->id,
        ]);
        $this->userB->workspaces()->attach($this->tenantB->id, ['role' => 'Business Admin', 'status' => 'active']);

        $this->tokenAdmin = $this->userAdmin->createToken('test_token')->plainTextToken;
        $this->tokenB = $this->userB->createToken('test_token_b')->plainTextToken;
    }

    protected function getAuthHeaders(Tenant $tenant, string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'X-Workspace-Id' => (string) $tenant->id,
            'Accept' => 'application/json',
        ];
    }

    /**
     * Test Tax Inclusive and Exclusive Mathematical Calculations
     */
    public function test_tax_inclusive_and_exclusive_quotation_calculations(): void
    {
        $headers = $this->getAuthHeaders($this->tenantA, $this->tokenAdmin);

        // 1. Tax Exclusive: 2 units @ 1000 = 2000. 10% line discount = 200. Net = 1800. 18% tax = 324. Total = 2124.
        $resExclusive = $this->postJson('/api/v1/crm/quotations', [
            'title' => 'Tax Exclusive Quote',
            'is_tax_inclusive' => false,
            'items' => [
                [
                    'item_name' => 'Enterprise License',
                    'quantity' => 2,
                    'unit_price' => 1000,
                    'discount_type' => 'percentage',
                    'discount_rate' => 10,
                    'tax_rate' => 18,
                ],
            ],
        ], $headers);

        $resExclusive->assertStatus(201);
        $dataEx = $resExclusive->json('data');
        $this->assertEquals(1800.0, (float) $dataEx['subtotal']);
        $this->assertEquals(324.0, (float) $dataEx['tax_amount']);
        $this->assertEquals(2124.0, (float) $dataEx['total_amount']);

        // 2. Tax Inclusive: 1 unit @ 1180 with 18% tax included.
        // Base = 1180 / 1.18 = 1000. Tax = 180. Total = 1180.
        $resInclusive = $this->postJson('/api/v1/crm/quotations', [
            'title' => 'Tax Inclusive Quote',
            'is_tax_inclusive' => true,
            'items' => [
                [
                    'item_name' => 'All-in-one Hardware Bundle',
                    'quantity' => 1,
                    'unit_price' => 1180,
                    'discount_type' => 'percentage',
                    'discount_rate' => 0,
                    'tax_rate' => 18,
                ],
            ],
        ], $headers);

        $resInclusive->assertStatus(201);
        $dataIn = $resInclusive->json('data');
        $this->assertEquals(1000.0, (float) $dataIn['subtotal']);
        $this->assertEquals(180.0, (float) $dataIn['tax_amount']);
        $this->assertEquals(1180.0, (float) $dataIn['total_amount']);
    }

    /**
     * Test Quotation Creation and Revision Branching
     */
    public function test_quotation_creation_and_revision_branching(): void
    {
        $headers = $this->getAuthHeaders($this->tenantA, $this->tokenAdmin);

        $createRes = $this->postJson('/api/v1/crm/quotations', [
            'title' => 'Consulting Quote Initial',
            'items' => [
                [
                    'item_name' => 'Architecture Review',
                    'quantity' => 10,
                    'unit_price' => 150,
                    'tax_rate' => 10,
                ],
            ],
        ], $headers);

        $createRes->assertStatus(201);
        $quoteId = $createRes->json('data.id');
        $quoteNumber = $createRes->json('data.quotation_number');
        $this->assertEquals(1, $createRes->json('data.version'));

        // Create Revision R2
        $revRes = $this->postJson("/api/v1/crm/quotations/{$quoteId}/revise", [
            'title' => 'Consulting Quote Revised Scope',
            'items' => [
                [
                    'item_name' => 'Architecture Review Expanded',
                    'quantity' => 15,
                    'unit_price' => 150,
                    'tax_rate' => 10,
                ],
            ],
        ], $headers);

        $revRes->assertStatus(201);
        $this->assertEquals(2, $revRes->json('data.version'));
        $this->assertEquals($quoteId, $revRes->json('data.parent_quotation_id'));
        $this->assertStringContainsString('-R2', $revRes->json('data.quotation_number'));
    }

    /**
     * Test Quotation Conversion to Invoice
     */
    public function test_quotation_conversion_to_invoice(): void
    {
        $headers = $this->getAuthHeaders($this->tenantA, $this->tokenAdmin);

        $customer = Customer::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Stark Industries',
            'email' => 'tony@stark.com',
            'phone' => '+1999888777',
        ]);

        $quoteRes = $this->postJson('/api/v1/crm/quotations', [
            'customer_id' => $customer->id,
            'title' => 'Arc Reactor Parts',
            'items' => [
                [
                    'item_name' => 'Palladium Core',
                    'quantity' => 2,
                    'unit_price' => 5000,
                    'tax_rate' => 10,
                ],
            ],
        ], $headers);

        $quoteId = $quoteRes->json('data.id');

        $convertRes = $this->postJson("/api/v1/crm/quotations/{$quoteId}/convert-to-invoice", [], $headers);
        $convertRes->assertStatus(201);

        $invoiceData = $convertRes->json('data');
        $this->assertEquals($quoteId, $invoiceData['quotation_id']);
        $this->assertEquals($customer->id, $invoiceData['customer_id']);
        $this->assertEquals(11000.0, (float) $invoiceData['total_amount']);
        $this->assertEquals(11000.0, (float) $invoiceData['outstanding_amount']);

        // Assert quotation is marked accepted
        $quoteCheck = Quotation::find($quoteId);
        $this->assertEquals('accepted', $quoteCheck->status);
        $this->assertNotNull($quoteCheck->accepted_at);
    }

    /**
     * Test Invoice Creation, Issuance and Stock Deduction
     */
    public function test_invoice_creation_issuance_and_stock_deduction(): void
    {
        $headers = $this->getAuthHeaders($this->tenantA, $this->tokenAdmin);

        // Create product with inventory tracking
        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Enterprise Server 2U',
            'sku' => 'SRV-2U-001',
            'type' => 'product',
            'track_inventory' => true,
            'price' => 3000,
            'cost_price' => 2000,
        ]);

        // Seed initial stock
        $invService = app(InventoryService::class);
        $wh = $invService->ensureDefaultWarehouse($this->tenantA->id);
        $invService->recordStockMovement([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $product->id,
            'warehouse_id' => $wh->id,
            'movement_type' => 'opening_stock',
            'quantity' => 20,
            'unit_cost' => 2000,
            'notes' => 'Opening batch',
        ], $this->userAdmin);

        $stockBefore = Product::find($product->id)->stocks()->sum('current_stock');
        $this->assertEquals(20.0, (float) $stockBefore);

        // Create invoice in draft status
        $invRes = $this->postJson('/api/v1/crm/invoices', [
            'status' => 'draft',
            'billing_name' => 'Wayne Enterprises',
            'items' => [
                [
                    'product_id' => $product->id,
                    'item_name' => $product->name,
                    'quantity' => 4,
                    'unit_price' => 3000,
                    'tax_rate' => 0,
                ],
            ],
        ], $headers);

        $invRes->assertStatus(201);
        $invoiceId = $invRes->json('data.id');

        // Issuing invoice deducts stock
        $issueRes = $this->postJson("/api/v1/crm/invoices/{$invoiceId}/issue", [], $headers);
        $issueRes->assertStatus(200);
        $this->assertEquals('issued', $issueRes->json('data.status'));

        $stockAfter = Product::find($product->id)->stocks()->sum('current_stock');
        $this->assertEquals(16.0, (float) $stockAfter);
    }

    /**
     * Test Partial and Full Payments Flow
     */
    public function test_manual_partial_and_full_payment_flow(): void
    {
        $headers = $this->getAuthHeaders($this->tenantA, $this->tokenAdmin);

        $invRes = $this->postJson('/api/v1/crm/invoices', [
            'status' => 'issued',
            'billing_name' => 'Cyberdyne Systems',
            'items' => [
                [
                    'item_name' => 'Neural Net Processor',
                    'quantity' => 1,
                    'unit_price' => 1000,
                    'tax_rate' => 0,
                ],
            ],
        ], $headers);

        $invRes->assertStatus(201);
        $invoiceId = $invRes->json('data.id');
        $this->assertEquals(1000.0, (float) $invRes->json('data.total_amount'));

        // 1. Partial Payment: 400
        $pay1Res = $this->postJson("/api/v1/crm/invoices/{$invoiceId}/payments", [
            'amount' => 400,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'TXN-BANK-001',
        ], $headers);

        $pay1Res->assertStatus(201);
        $invoice1 = Invoice::find($invoiceId);
        $this->assertEquals('partially_paid', $invoice1->status);
        $this->assertEquals(400.0, (float) $invoice1->paid_amount);
        $this->assertEquals(600.0, (float) $invoice1->outstanding_amount);

        // 2. Remaining Payment: 600
        $pay2Res = $this->postJson("/api/v1/crm/invoices/{$invoiceId}/payments", [
            'amount' => 600,
            'payment_method' => 'upi',
            'reference_number' => 'UPI-REF-002',
        ], $headers);

        $pay2Res->assertStatus(201);
        $invoice2 = Invoice::find($invoiceId);
        $this->assertEquals('paid', $invoice2->status);
        $this->assertEquals(1000.0, (float) $invoice2->paid_amount);
        $this->assertEquals(0.0, (float) $invoice2->outstanding_amount);
        $this->assertNotNull($invoice2->paid_at);
    }

    /**
     * Test Payment Refund and Invoice Status Reversion
     */
    public function test_payment_refund_and_invoice_reversion(): void
    {
        $headers = $this->getAuthHeaders($this->tenantA, $this->tokenAdmin);

        $invRes = $this->postJson('/api/v1/crm/invoices', [
            'status' => 'issued',
            'items' => [
                [
                    'item_name' => 'Consulting Package',
                    'quantity' => 1,
                    'unit_price' => 2000,
                    'tax_rate' => 0,
                ],
            ],
        ], $headers);

        $invoiceId = $invRes->json('data.id');

        // Pay in full
        $payRes = $this->postJson("/api/v1/crm/invoices/{$invoiceId}/payments", [
            'amount' => 2000,
            'payment_method' => 'card',
        ], $headers);

        $paymentId = $payRes->json('data.id');
        $this->assertEquals('paid', Invoice::find($invoiceId)->status);

        // Refund partial: 500
        $refRes = $this->postJson("/api/v1/crm/payments/{$paymentId}/refund", [
            'amount' => 500,
            'reason' => 'Client requested partial scope cancellation',
        ], $headers);

        $refRes->assertStatus(201);
        $this->assertEquals('processed', $refRes->json('data.status'));

        $invUpdated = Invoice::find($invoiceId);
        $this->assertEquals('partially_paid', $invUpdated->status);
        $this->assertEquals(1500.0, (float) $invUpdated->paid_amount);
        $this->assertEquals(500.0, (float) $invUpdated->outstanding_amount);
        $this->assertNull($invUpdated->paid_at);
    }

    /**
     * Test Razorpay Cryptographic Client Signature Verification
     */
    public function test_razorpay_order_and_verified_payment_signature(): void
    {
        $headers = $this->getAuthHeaders($this->tenantA, $this->tokenAdmin);

        $invRes = $this->postJson('/api/v1/crm/invoices', [
            'items' => [
                [
                    'item_name' => 'Cloud Hosting 1 Year',
                    'quantity' => 1,
                    'unit_price' => 1200,
                    'tax_rate' => 0,
                ],
            ],
        ], $headers);

        $invoiceId = $invRes->json('data.id');

        // 1. Generate Order / Payment Link
        $linkRes = $this->postJson("/api/v1/crm/invoices/{$invoiceId}/payment-link", [], $headers);
        $linkRes->assertStatus(200);
        $orderId = $linkRes->json('data.order.id');
        $this->assertNotEmpty($orderId);

        // 2. Forged signature should fail
        $fakeVerify = $this->postJson("/api/v1/crm/invoices/{$invoiceId}/verify-payment", [
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => 'pay_fake_123',
            'razorpay_signature' => 'invalid_signature_hash',
        ], $headers);
        $fakeVerify->assertStatus(400);

        // 3. Genuine signature computed with key secret must pass
        $gatewayService = app(PaymentGatewayService::class);
        $paymentId = 'pay_real_' . uniqid();
        $validSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $gatewayService->getKeySecret());

        $realVerify = $this->postJson("/api/v1/crm/invoices/{$invoiceId}/verify-payment", [
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $validSignature,
        ], $headers);

        $realVerify->assertStatus(200);
        $this->assertEquals('paid', Invoice::find($invoiceId)->status);
        $this->assertEquals('razorpay', $realVerify->json('data.payment_channel'));
    }

    /**
     * Test Razorpay Webhook Cryptographic Verification and Idempotency
     */
    public function test_razorpay_webhook_cryptographic_verification_and_idempotency(): void
    {
        $headers = $this->getAuthHeaders($this->tenantA, $this->tokenAdmin);

        $invRes = $this->postJson('/api/v1/crm/invoices', [
            'items' => [
                [
                    'item_name' => 'SaaS Subscription Tier 3',
                    'quantity' => 1,
                    'unit_price' => 5000,
                    'tax_rate' => 0,
                ],
            ],
        ], $headers);

        $invoiceId = $invRes->json('data.id');
        $invoice = Invoice::find($invoiceId);

        $orderId = 'order_wh_' . uniqid();
        $invoice->razorpay_order_id = $orderId;
        $invoice->save();

        $gatewayService = app(PaymentGatewayService::class);
        $paymentId = 'pay_wh_' . uniqid();

        $payloadArray = [
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => $paymentId,
                        'order_id' => $orderId,
                        'amount' => 500000, // 5000 in paise
                        'currency' => 'INR',
                        'method' => 'card',
                        'notes' => [
                            'invoice_id' => $invoiceId,
                            'tenant_id' => $this->tenantA->id,
                        ],
                    ],
                ],
            ],
        ];

        $rawPayload = json_encode($payloadArray);
        $webhookSecret = $gatewayService->getWebhookSecret();
        $validWebhookSig = hash_hmac('sha256', $rawPayload, $webhookSecret);

        // 1. Invalid signature must return 400
        $forgedRes = $this->call(
            'POST',
            '/api/v1/webhooks/razorpay',
            [],
            [],
            [],
            ['HTTP_X-Razorpay-Signature' => 'forged_signature', 'CONTENT_TYPE' => 'application/json'],
            $rawPayload
        );
        $forgedRes->assertStatus(400);

        // 2. Valid signature succeeds and marks invoice paid
        $validRes = $this->call(
            'POST',
            '/api/v1/webhooks/razorpay',
            [],
            [],
            [],
            ['HTTP_X-Razorpay-Signature' => $validWebhookSig, 'CONTENT_TYPE' => 'application/json'],
            $rawPayload
        );
        $validRes->assertStatus(200);
        $this->assertEquals('processed', $validRes->json('data.status'));
        $this->assertEquals('paid', Invoice::find($invoiceId)->status);

        // 3. Duplicate delivery of identical webhook must be gracefully ignored (Idempotency)
        $duplicateRes = $this->call(
            'POST',
            '/api/v1/webhooks/razorpay',
            [],
            [],
            [],
            ['HTTP_X-Razorpay-Signature' => $validWebhookSig, 'CONTENT_TYPE' => 'application/json'],
            $rawPayload
        );
        $duplicateRes->assertStatus(200);
        $this->assertEquals('duplicate_ignored', $duplicateRes->json('data.status'));

        // Verify only 1 payment was created
        $paymentsCount = Payment::where('gateway_payment_id', $paymentId)->count();
        $this->assertEquals(1, $paymentsCount);
    }

    /**
     * Test Multi-tenant Isolation across Finance Operations
     */
    public function test_tenant_isolation_on_finance_billing(): void
    {
        $headersA = $this->getAuthHeaders($this->tenantA, $this->tokenAdmin);
        $headersB = $this->getAuthHeaders($this->tenantB, $this->tokenB);

        // Tenant A creates invoice
        $this->actingAs($this->userAdmin, 'sanctum');
        $invResA = $this->postJson('/api/v1/crm/invoices', [
            'items' => [
                [
                    'item_name' => 'Secret Enterprise Contract',
                    'quantity' => 1,
                    'unit_price' => 50000,
                    'tax_rate' => 0,
                ],
            ],
        ], $headersA);

        $invIdA = $invResA->json('data.id');

        // Switch authenticated user to Tenant B
        $this->actingAs($this->userB, 'sanctum');

        // Tenant B cannot view Tenant A's invoice
        $forbiddenView = $this->getJson("/api/v1/crm/invoices/{$invIdA}", $headersB);
        $this->assertTrue(in_array($forbiddenView->status(), [404, 403]));

        // Tenant B cannot pay Tenant A's invoice
        $forbiddenPay = $this->postJson("/api/v1/crm/invoices/{$invIdA}/payments", [
            'amount' => 5000,
        ], $headersB);
        $this->assertTrue(in_array($forbiddenPay->status(), [404, 403]));

        // Finance summary for Tenant B should show 0 for Tenant A's invoice
        $summaryB = $this->getJson('/api/v1/crm/finance/summary', $headersB);
        $summaryB->assertStatus(200);
        $this->assertEquals(0.0, (float) $summaryB->json('data.total_invoiced'));
    }
}
