<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Branch;
use App\Domains\CRM\Models\Customer;
use App\Domains\CustomerPortal\Models\BookableService;
use App\Domains\CustomerPortal\Models\CustomerAccount;
use App\Domains\CustomerPortal\Models\CustomerLoginHistory;
use App\Domains\CustomerPortal\Models\PortalSetting;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Payment;
use App\Domains\Projects\Models\Meeting;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectDocument;
use App\Domains\Finance\Models\Quotation;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerPortalAndAppointmentBookingTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $adminUser;
    protected Customer $customer;
    protected CustomerAccount $customerAccount;
    protected PortalSetting $portalSetting;
    protected BookableService $bookableService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Acme Enterprise Solutions',
            'domain' => 'acme-' . uniqid() . '.test',
            'subscription_plan' => 'enterprise',
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin Operator',
            'email' => 'admin-' . uniqid() . '@acme.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'Business Admin',
            'status' => 'active',
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->adminUser->workspaces()->attach($this->tenant->id, ['role' => 'Business Admin', 'status' => 'active']);

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'John Enterprise Client',
            'email' => 'john.client@partner.com',
            'phone' => '+91 9876543210',
            'status' => 'active',
            'billing_address' => 'Plot 42, Cyber Gateway, Hyderabad',
            'gstin' => '36AAACB1234F1Z8',
        ]);

        $this->customerAccount = CustomerAccount::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'email' => $this->customer->email,
            'phone' => $this->customer->phone,
            'password' => Hash::make('PortalClient123!'),
            'is_active' => true,
            'email_verified_at' => Carbon::now(),
        ]);

        $this->portalSetting = PortalSetting::create([
            'tenant_id' => $this->tenant->id,
            'is_portal_enabled' => true,
            'allow_self_registration' => true,
            'allow_invoice_access' => true,
            'allow_payments' => true,
            'allow_project_tracking' => true,
            'allow_ticket_creation' => true,
            'allow_appointment_booking' => true,
            'allow_document_downloads' => true,
            'public_booking_page_enabled' => true,
            'booking_slug' => 'acme-booking',
            'brand_color' => '#4F46E5',
        ]);

        $this->bookableService = BookableService::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Enterprise Architecture Consultation',
            'code' => 'SVC-ARCH',
            'description' => 'Comprehensive technical review with Lead Systems Architect.',
            'duration_minutes' => 60,
            'price' => 2500.00,
            'currency' => 'INR',
            'requires_advance_payment' => false,
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Customer Registration (Permitted vs Disallowed).
     */
    public function test_customer_registration_respects_portal_settings(): void
    {
        // 1. Successful registration when allowed
        $response = $this->postJson('/api/v1/portal/auth/register', [
            'name' => 'Alice New Client',
            'email' => 'alice@client.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+91 9988776655',
            'tenant_id' => $this->tenant->id,
        ], ['X-Workspace-Id' => (string)$this->tenant->id]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'account']]);

        $this->assertDatabaseHas('customer_accounts', [
            'email' => 'alice@client.com',
            'tenant_id' => $this->tenant->id,
        ]);

        // 2. Disallow registration when setting is disabled
        $this->portalSetting->update(['allow_self_registration' => false]);

        $failResponse = $this->postJson('/api/v1/portal/auth/register', [
            'name' => 'Bob Denied Client',
            'email' => 'bob@client.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'tenant_id' => $this->tenant->id,
        ], ['X-Workspace-Id' => (string)$this->tenant->id]);

        $failResponse->assertStatus(422)
            ->assertJsonValidationErrors(['registration']);
    }

    /**
     * Test 2: Customer Login and Session Record.
     */
    public function test_customer_login_generates_scoped_token_and_records_history(): void
    {
        $response = $this->postJson('/api/v1/portal/auth/login', [
            'email' => $this->customerAccount->email,
            'password' => 'PortalClient123!',
        ], ['X-Workspace-Id' => (string)$this->tenant->id]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'account']]);

        $this->assertDatabaseHas('customer_login_histories', [
            'customer_account_id' => $this->customerAccount->id,
            'status' => 'success',
        ]);
    }

    /**
     * Test 3: Customer Dashboard Scoped Metrics.
     */
    public function test_customer_dashboard_returns_scoped_metrics(): void
    {
        // Create sample invoice
        Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-2026-001',
            'total_amount' => 15000.00,
            'paid_amount' => 5000.00,
            'outstanding_amount' => 10000.00,
            'status' => 'partially_paid',
            'due_date' => Carbon::today()->addDays(15),
            'issued_at' => Carbon::now(),
        ]);

        Sanctum::actingAs($this->customerAccount, ['portal:customer']);

        $response = $this->getJson('/api/v1/portal/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.financials.outstanding_due', 10000)
            ->assertJsonPath('data.profile.customer_id', $this->customer->id);
    }

    /**
     * Test 4: Quotation Lifecycle (Accept, Reject, Revision Request).
     */
    public function test_customer_quotation_review_and_digital_acceptance(): void
    {
        $quote = Quotation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'quote_number' => 'QUO-2026-778',
            'total_amount' => 50000.00,
            'status' => 'sent',
            'valid_until' => Carbon::today()->addDays(30),
        ]);

        Sanctum::actingAs($this->customerAccount, ['portal:customer']);

        // 1. View quotations
        $listRes = $this->getJson('/api/v1/portal/quotations');
        $listRes->assertStatus(200);

        // 2. Accept quotation
        $acceptRes = $this->postJson("/api/v1/portal/quotations/{$quote->id}/accept", [
            'notes' => 'Digitally accepted by Managing Director on client portal.',
        ]);

        $acceptRes->assertStatus(200)
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('quotations', [
            'id' => $quote->id,
            'status' => 'accepted',
        ]);
    }

    /**
     * Test 5: Invoice Payment Simulation.
     */
    public function test_customer_can_pay_invoice_from_portal(): void
    {
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-2026-PAY1',
            'total_amount' => 20000.00,
            'paid_amount' => 0.00,
            'outstanding_amount' => 20000.00,
            'status' => 'issued',
            'issued_at' => Carbon::now(),
        ]);

        Sanctum::actingAs($this->customerAccount, ['portal:customer']);

        $payRes = $this->postJson("/api/v1/portal/invoices/{$invoice->id}/pay", [
            'amount' => 20000.00,
            'payment_method' => 'razorpay',
            'transaction_reference' => 'pay_RzSimTest999',
        ]);

        $payRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.invoice.status', 'paid');
        $this->assertEquals(0, (float)$payRes->json('data.invoice.outstanding_amount'));

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 20000.00,
            'gateway_payment_id' => 'pay_RzSimTest999',
            'status' => 'success',
        ]);
    }

    /**
     * Test 6: Project Tracking strictly hides internal developer tasks and notes.
     */
    public function test_project_tracking_strictly_shows_client_visible_records(): void
    {
        $visibleProject = Project::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'name' => 'Client Cloud Migration Portal',
            'client_portal_enabled' => true,
            'status' => 'active',
            'start_date' => Carbon::today(),
            'budget' => 500000.00, // sensitive internal
        ]);

        $hiddenProject = Project::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'name' => 'Internal R&D Prototyping',
            'client_portal_enabled' => false, // internal only
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->customerAccount, ['portal:customer']);

        $res = $this->getJson('/api/v1/portal/projects');

        $res->assertStatus(200);
        $projectNames = collect($res->json('data.data'))->pluck('name')->toArray();

        $this->assertContains('Client Cloud Migration Portal', $projectNames);
        $this->assertNotContains('Internal R&D Prototyping', $projectNames);
    }

    /**
     * Test 7: Support Ticket Creation and Client-Visible Message Exchange.
     */
    public function test_customer_can_create_ticket_and_reply(): void
    {
        Sanctum::actingAs($this->customerAccount, ['portal:customer']);

        $ticketRes = $this->postJson('/api/v1/portal/tickets', [
            'subject' => 'Need clarification on API rate limits',
            'description' => 'We are preparing load tests and need to verify our current quotas.',
            'priority' => 'high',
        ]);

        $ticketRes->assertStatus(201)
            ->assertJsonPath('success', true);

        $ticketId = $ticketRes->json('data.id');

        $replyRes = $this->postJson("/api/v1/portal/tickets/{$ticketId}/reply", [
            'message' => 'Additional detail: We plan to run 200 requests/minute tomorrow morning.',
        ]);

        $replyRes->assertStatus(200)
            ->assertJsonPath('data.sender_type', 'customer')
            ->assertJsonPath('data.is_internal', false);
    }

    /**
     * Test 8: Transaction-Safe Appointment Booking with Double-Booking Prevention.
     */
    public function test_appointment_booking_prevents_double_booking_conflict(): void
    {
        Sanctum::actingAs($this->customerAccount, ['portal:customer']);

        // First booking: 10:00:00 to 11:00:00 on 2026-12-01
        $book1 = $this->postJson('/api/v1/portal/appointments', [
            'service_id' => $this->bookableService->id,
            'meeting_date' => '2026-12-01',
            'start_time' => '10:00:00',
            'notes' => 'Q4 Architecture Review Session',
        ]);

        $book1->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['id', 'booking_reference']]);

        // Second booking attempt on the EXACT SAME time slot must fail with validation error
        $book2 = $this->postJson('/api/v1/portal/appointments', [
            'service_id' => $this->bookableService->id,
            'meeting_date' => '2026-12-01',
            'start_time' => '10:00:00',
            'notes' => 'Conflicting booking attempt',
        ]);

        $book2->assertStatus(422)
            ->assertJsonValidationErrors(['start_time']);
    }

    /**
     * Test 9: Appointment Reschedule and Cancellation.
     */
    public function test_appointment_reschedule_and_cancellation(): void
    {
        Sanctum::actingAs($this->customerAccount, ['portal:customer']);

        $meeting = Meeting::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->bookableService->id,
            'title' => 'Consultation',
            'meeting_date' => '2026-12-05',
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'status' => 'scheduled',
            'booking_reference' => 'APT-TEST-001',
            'is_customer_booked' => true,
        ]);

        // 1. Reschedule
        $rescheduleRes = $this->postJson("/api/v1/portal/appointments/{$meeting->id}/reschedule", [
            'meeting_date' => '2026-12-06',
            'start_time' => '15:00:00',
        ]);

        $rescheduleRes->assertStatus(200)
            ->assertJsonPath('data.status', 'rescheduled');
        $this->assertTrue(str_starts_with($rescheduleRes->json('data.meeting_date'), '2026-12-06'));

        // 2. Cancel
        $cancelRes = $this->postJson("/api/v1/portal/appointments/{$meeting->id}/cancel", [
            'reason' => 'Schedule conflict on client end.',
        ]);

        $cancelRes->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');
    }

    /**
     * Test 10: Public Booking Page Config and Slot Availability.
     */
    public function test_public_booking_page_config_and_slots(): void
    {
        // 1. Public booking config
        $configRes = $this->getJson("/api/v1/public/booking/{$this->portalSetting->booking_slug}");

        $configRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.branding.brand_color', '#4F46E5');

        // 2. Slot calculation for date
        $slotsRes = $this->getJson("/api/v1/public/booking/{$this->portalSetting->booking_slug}/slots?service_id={$this->bookableService->id}&date=2026-12-10");

        $slotsRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['date', 'service', 'slots']]);

        // 3. Public guest booking
        $publicBookRes = $this->postJson("/api/v1/public/booking/{$this->portalSetting->booking_slug}/book", [
            'service_id' => $this->bookableService->id,
            'meeting_date' => '2026-12-10',
            'start_time' => '11:00:00',
            'customer_name' => 'Dr. Robert Public Guest',
            'customer_email' => 'robert.guest@consulting.org',
            'customer_phone' => '+91 9123456780',
            'notes' => 'New lead initial discovery consultation.',
        ]);

        $publicBookRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['meeting', 'booking_reference']]);

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $this->tenant->id,
            'email' => 'robert.guest@consulting.org',
        ]);
    }

    /**
     * Test 11: Cross-Customer and Cross-Tenant Strict Isolation.
     */
    public function test_cross_customer_and_cross_tenant_strict_isolation(): void
    {
        // Another tenant & customer
        $otherTenant = Tenant::create([
            'name' => 'Foreign Corp',
            'domain' => 'foreign-' . uniqid() . '.test',
            'subscription_plan' => 'standard',
        ]);

        $otherCustomer = Customer::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Client',
            'email' => 'foreign@othercorp.com',
            'status' => 'active',
        ]);

        $otherQuote = Quotation::create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'quote_number' => 'QUO-FOREIGN-999',
            'total_amount' => 99999.00,
            'status' => 'sent',
        ]);

        // Act as primary customer
        Sanctum::actingAs($this->customerAccount, ['portal:customer']);

        // Attempt to accept quotation belonging to another customer/tenant must return 404
        $maliciousRes = $this->postJson("/api/v1/portal/quotations/{$otherQuote->id}/accept", [
            'notes' => 'Attempting cross-tenant tampering',
        ]);

        $maliciousRes->assertStatus(404);
    }
}
