<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\Finance\Models\ComplianceDocument;
use App\Domains\Finance\Models\ComplianceReminder;
use App\Domains\Finance\Models\GstRegistration;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Services\GstTaxCalculationService;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterpriseIndianGstAndComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Bharat Tech Enterprises',
            'slug' => 'bharat-tech',
            'status' => 'active',
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'role' => 'Business Admin',
        ]);

        $this->user->workspaces()->attach($this->tenant->id, [
            'role' => 'Business Admin',
            'status' => 'active',
        ]);

        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_organization_gst_registration_configuration_and_multi_state_support(): void
    {
        // 1. Get default registration
        $res = $this->withHeaders($this->authHeaders())->getJson('/api/v1/crm/gst/config');
        $res->assertStatus(200);
        $res->assertJsonPath('status', 'success');

        // 2. Save a new Karnataka state GST registration
        $saveRes = $this->withHeaders($this->authHeaders())->postJson('/api/v1/crm/gst/config', [
            'business_legal_name' => 'Bharat Tech Enterprises Pvt Ltd',
            'trade_name' => 'Bharat Tech Bangalore',
            'gstin' => '29AABCB9999K1Z2',
            'pan' => 'AABCB9999K',
            'registered_address' => 'Outer Ring Road, Bellandur',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'state_code' => '29',
            'pincode' => '560103',
            'business_type' => 'private_limited',
            'tax_calculation_preference' => 'exclusive',
            'default_place_of_supply' => '29',
            'invoice_prefix' => 'BLR-',
            'financial_year' => '2026-27',
            'is_default' => false,
        ]);

        $saveRes->assertStatus(200);
        $saveRes->assertJsonPath('data.state_code', '29');
        $this->assertDatabaseHas('gst_registrations', [
            'tenant_id' => $this->tenant->id,
            'state_code' => '29',
            'gstin' => '29AABCB9999K1Z2',
        ]);
    }

    public function test_gstin_verification_and_taxpayer_lookup(): void
    {
        // Valid GSTIN format
        $res = $this->withHeaders($this->authHeaders())->postJson('/api/v1/crm/gst/verify-gstin', [
            'gstin' => '27AABCA1234D1Z5',
        ]);

        $res->assertStatus(200);
        $res->assertJsonPath('data.is_verified', true);
        $res->assertJsonPath('data.state_code', '27');
        $res->assertJsonPath('data.state_name', 'Maharashtra');

        // Invalid GSTIN format
        $invalidRes = $this->withHeaders($this->authHeaders())->postJson('/api/v1/crm/gst/verify-gstin', [
            'gstin' => 'INVALID_GSTIN_123',
        ]);
        $invalidRes->assertStatus(200);
        $invalidRes->assertJsonPath('data.is_verified', false);
    }

    public function test_tax_calculation_engine_intra_vs_inter_state_and_tax_inclusive(): void
    {
        $service = app(GstTaxCalculationService::class);

        // 1. Intra-state calculation (e.g. Maharashtra to Maharashtra, 18% splits into 9% CGST + 9% SGST)
        $intra = $service->calculateInvoiceGst(
            [
                ['quantity' => 2, 'unit_price' => 50000, 'discount_amount' => 0, 'tax_rate' => 18, 'cess_rate' => 0]
            ],
            'intra_state',
            false
        );

        $this->assertEquals(100000.0, $intra['subtotal']);
        $this->assertEquals(9000.0, $intra['cgst_amount']);
        $this->assertEquals(9000.0, $intra['sgst_amount']);
        $this->assertEquals(0.0, $intra['igst_amount']);
        $this->assertEquals(118000.0, $intra['grand_total']);
        $this->assertStringContainsString('One Lakh Eighteen Thousand', $intra['amount_in_words']);

        // 2. Inter-state calculation (e.g. Maharashtra to Karnataka, 18% IGST)
        $inter = $service->calculateInvoiceGst(
            [
                ['quantity' => 1, 'unit_price' => 100000, 'discount_amount' => 10000, 'tax_rate' => 18, 'cess_rate' => 0]
            ],
            'inter_state',
            false
        );

        $this->assertEquals(90000.0, $inter['subtotal']);
        $this->assertEquals(0.0, $inter['cgst_amount']);
        $this->assertEquals(0.0, $inter['sgst_amount']);
        $this->assertEquals(16200.0, $inter['igst_amount']);
        $this->assertEquals(106200.0, $inter['grand_total']);

        // 3. Tax-inclusive pricing (MRP ₹11800 includes 18% tax -> Taxable ₹10000, Tax ₹1800)
        $inclusive = $service->calculateInvoiceGst(
            [
                ['quantity' => 1, 'unit_price' => 11800, 'discount_amount' => 0, 'tax_rate' => 18, 'cess_rate' => 0]
            ],
            'inter_state',
            true
        );

        $this->assertEquals(10000.0, $inclusive['subtotal']);
        $this->assertEquals(1800.0, $inclusive['igst_amount']);
        $this->assertEquals(11800.0, $inclusive['grand_total']);
    }

    public function test_gst_invoice_creation_finalization_and_credit_note(): void
    {
        // 1. Create Tax Invoice
        $res = $this->withHeaders($this->authHeaders())->postJson('/api/v1/crm/gst/invoices', [
            'document_type' => 'tax_invoice',
            'buyer_gstin' => '29BBBBB4321E1Z9',
            'buyer_legal_name' => 'Infosys Limited',
            'place_of_supply_state_code' => '29', // Inter-state from 27 to 29
            'status' => 'draft',
            'items' => [
                [
                    'item_name' => 'Cloud Migration Consulting',
                    'quantity' => 1,
                    'unit_price' => 200000,
                    'tax_rate' => 18,
                    'hsn_sac_code' => '998311',
                    'unit' => 'HRS',
                    'is_service' => true,
                ]
            ],
        ]);

        $res->assertStatus(201);
        $res->assertJsonPath('data.supply_type', 'inter_state');
        $res->assertJsonPath('data.igst_amount', '36000.00');
        $res->assertJsonPath('data.total_amount', '236000.00');
        $invoiceId = $res->json('data.id');

        // 2. Finalize Invoice
        $finalizeRes = $this->withHeaders($this->authHeaders())->postJson("/api/v1/crm/gst/invoices/{$invoiceId}/finalize");
        $finalizeRes->assertStatus(200);
        $finalizeRes->assertJsonPath('data.status', 'issued');

        // 3. Issue Credit Note
        $cnRes = $this->withHeaders($this->authHeaders())->postJson("/api/v1/crm/gst/invoices/{$invoiceId}/credit-note", [
            'amount' => 50000,
            'reason' => 'Service level agreement discount adjustment',
        ]);

        $cnRes->assertStatus(201);
        $cnRes->assertJsonPath('data.document_type', 'credit_note');
        $cnRes->assertJsonPath('data.original_invoice_id', $invoiceId);
    }

    public function test_e_invoice_generation_and_cancellation(): void
    {
        // Create B2B invoice
        $createRes = $this->withHeaders($this->authHeaders())->postJson('/api/v1/crm/gst/invoices', [
            'document_type' => 'tax_invoice',
            'buyer_gstin' => '27AAACG0561F1Z8',
            'buyer_legal_name' => 'Godrej Industries Ltd',
            'place_of_supply_state_code' => '27',
            'status' => 'issued',
            'items' => [
                [
                    'item_name' => 'Enterprise Software Subscription',
                    'quantity' => 1,
                    'unit_price' => 500000,
                    'tax_rate' => 18,
                ]
            ],
        ]);

        $createRes->assertStatus(201);
        $invId = $createRes->json('data.id');

        // Submit for E-Invoice (IRP)
        $irnRes = $this->withHeaders($this->authHeaders())->postJson("/api/v1/crm/gst/invoices/{$invId}/einvoice");
        $irnRes->assertStatus(200);
        $irnRes->assertJsonPath('data.einvoice_status', 'generated');
        $this->assertNotEmpty($irnRes->json('data.irn'));
        $this->assertNotEmpty($irnRes->json('data.signed_qr_code'));

        // Cancel E-Invoice
        $cancelRes = $this->withHeaders($this->authHeaders())->postJson("/api/v1/crm/gst/invoices/{$invId}/einvoice/cancel", [
            'reason' => 'Commercial dispute with buyer regarding delivery milestones',
        ]);
        $cancelRes->assertStatus(200);
        $cancelRes->assertJsonPath('data.einvoice_status', 'cancelled');
    }

    public function test_e_way_bill_generation_and_vehicle_update(): void
    {
        $createRes = $this->withHeaders($this->authHeaders())->postJson('/api/v1/crm/gst/invoices', [
            'document_type' => 'tax_invoice',
            'buyer_gstin' => '29AAACH1234D1Z2',
            'buyer_legal_name' => 'Wipro Technologies',
            'place_of_supply_state_code' => '29',
            'status' => 'issued',
            'items' => [
                [
                    'item_name' => 'Server Racks Hardware Goods',
                    'quantity' => 5,
                    'unit_price' => 100000,
                    'tax_rate' => 18,
                    'is_service' => false,
                ]
            ],
        ]);
        $invId = $createRes->json('data.id');

        // Generate E-Way Bill
        $ewbRes = $this->withHeaders($this->authHeaders())->postJson("/api/v1/crm/gst/invoices/{$invId}/eway-bill", [
            'distance_km' => 950,
            'vehicle_number' => 'MH04AB5678',
            'transporter_name' => 'VRL Logistics',
            'transport_mode' => 'road',
        ]);

        $ewbRes->assertStatus(200);
        $ewbRes->assertJsonPath('data.eway_bill_status', 'generated');
        $this->assertNotEmpty($ewbRes->json('data.eway_bill_number'));

        // Update Vehicle (Part B update)
        $updateRes = $this->withHeaders($this->authHeaders())->postJson("/api/v1/crm/gst/invoices/{$invId}/eway-bill/vehicle", [
            'vehicle_number' => 'KA01CD9999',
        ]);
        $updateRes->assertStatus(200);
        $updateRes->assertJsonPath('data.eway_bill_status', 'updated');
        $updateRes->assertJsonPath('data.vehicle_number', 'KA01CD9999');
    }

    public function test_gst_reports_summary_sales_register_and_hsn(): void
    {
        // Create an invoice to ensure reporting data
        $this->withHeaders($this->authHeaders())->postJson('/api/v1/crm/gst/invoices', [
            'document_type' => 'tax_invoice',
            'buyer_gstin' => '27AAACG0561F1Z8',
            'buyer_legal_name' => 'Reliance Retail Ltd',
            'place_of_supply_state_code' => '27',
            'status' => 'issued',
            'items' => [
                [
                    'item_name' => 'Consulting Advisory',
                    'quantity' => 1,
                    'unit_price' => 100000,
                    'tax_rate' => 18,
                    'hsn_sac_code' => '998311',
                ]
            ],
        ]);

        // 1. Tax Summary
        $sumRes = $this->withHeaders($this->authHeaders())->getJson('/api/v1/crm/gst/reports/summary');
        $sumRes->assertStatus(200);
        $sumRes->assertJsonStructure([
            'status',
            'data' => [
                'outward_supplies',
                'inward_supplies_itc',
                'net_tax_liability',
                'compliance_disclaimer',
            ],
        ]);

        // 2. Sales Register GSTR-1 format
        $salesRes = $this->withHeaders($this->authHeaders())->getJson('/api/v1/crm/gst/reports/sales-register');
        $salesRes->assertStatus(200);
        $salesRes->assertJsonStructure([
            'status',
            'data' => [
                'b2b_invoices',
                'b2c_large',
                'b2c_small',
                'credit_debit_notes',
                'exports',
            ],
        ]);

        // 3. HSN Summary
        $hsnRes = $this->withHeaders($this->authHeaders())->getJson('/api/v1/crm/gst/reports/hsn-summary');
        $hsnRes->assertStatus(200);
        $this->assertIsArray($hsnRes->json('data'));
    }

    public function test_compliance_calendar_and_document_vault(): void
    {
        // 1. Calendar Reminders (auto-seeded statutory reminders)
        $remRes = $this->withHeaders($this->authHeaders())->getJson('/api/v1/crm/gst/compliance/reminders');
        $remRes->assertStatus(200);
        $this->assertNotEmpty($remRes->json('data'));
        $remId = $remRes->json('data.0.id');

        // Complete a reminder
        $compRes = $this->withHeaders($this->authHeaders())->postJson("/api/v1/crm/gst/compliance/reminders/{$remId}/complete");
        $compRes->assertStatus(200);
        $compRes->assertJsonPath('data.status', 'completed');

        // 2. Document Vault
        $docRes = $this->withHeaders($this->authHeaders())->getJson('/api/v1/crm/gst/compliance/documents');
        $docRes->assertStatus(200);
        $this->assertNotEmpty($docRes->json('data'));

        // Store new certificate
        $storeRes = $this->withHeaders($this->authHeaders())->postJson('/api/v1/crm/gst/compliance/documents', [
            'title' => 'MSME Udyam Registration Certificate',
            'document_type' => 'msme_certificate',
            'document_number' => 'UDYAM-MH-01-0012345',
            'file_name' => 'Udyam_Certificate.pdf',
            'issue_date' => '2023-01-15',
        ]);
        $storeRes->assertStatus(201);
        $storeRes->assertJsonPath('data.title', 'MSME Udyam Registration Certificate');
    }
}
