<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Company;
use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\CrmActivity;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Deal;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmCoreModuleTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->tenantA = Tenant::where('slug', 'acme-enterprise')->first();
        $this->userA = User::where('email', 'john@nexus.com')->first();

        $this->tenantB = Tenant::where('slug', 'starlight-logistics')->first();
        $this->userB = User::where('email', 'marcus@starlight.io')->first();
    }

    public function test_lead_creation_with_attribution_and_automatic_scoring(): void
    {
        Sanctum::actingAs($this->userA);

        $response = $this->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson('/api/v1/crm/leads', [
                'name' => 'Enterprise Buyer',
                'company_name' => 'Global Logistics Inc',
                'email' => 'buyer@globallogistics.com',
                'phone' => '+15552345678',
                'estimated_value' => 75000,
                'expected_purchase_date' => now()->addDays(20)->format('Y-m-d'),
                'source' => 'Partner Referral',
                'priority' => 'high',
                'utm_source' => 'linkedin',
                'utm_campaign' => 'q4_enterprise',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Enterprise Buyer')
            ->assertJsonPath('data.qualification_status', 'qualified');

        $leadId = $response->json('data.id');
        $lead = Lead::find($leadId);

        $this->assertNotNull($lead);
        $this->assertGreaterThanOrEqual(60, $lead->score);
        $this->assertArrayHasKey('contact_completeness', $lead->score_breakdown);
        $this->assertArrayHasKey('budget_tier', $lead->score_breakdown);

        // Verify creation activity logged
        $activity = CrmActivity::where('lead_id', $leadId)->first();
        $this->assertNotNull($activity);
        $this->assertEquals('status_change', $activity->activity_type);
    }

    public function test_lead_assignment_and_round_robin_distribution(): void
    {
        Sanctum::actingAs($this->userA);

        // 1. Create unassigned lead
        $lead = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Unassigned Prospect',
            'email' => 'unassigned@prospect.com',
            'phone' => '+15559876543',
            'status' => 'new',
        ]);

        // 2. Direct manual assignment
        $assignRes = $this->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson("/api/v1/crm/leads/{$lead->id}/assign", [
                'assigned_user_id' => $this->userA->id,
                'reason' => 'Designated sales territory owner',
            ]);

        $assignRes->assertStatus(200)
            ->assertJsonPath('data.assigned_user_id', $this->userA->id);

        $this->assertDatabaseHas('lead_assignments', [
            'lead_id' => $lead->id,
            'assigned_to_user_id' => $this->userA->id,
        ]);

        // 3. Test Round-Robin Balancer
        $lead2 = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Queue Lead',
            'email' => 'queue@lead.com',
            'phone' => '+15559876544',
            'status' => 'new',
        ]);

        $rrRes = $this->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson('/api/v1/crm/leads/auto-distribute-round-robin');

        $rrRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotNull($lead2->fresh()->assigned_user_id);
    }

    public function test_duplicate_detection_and_controlled_merge(): void
    {
        Sanctum::actingAs($this->userA);

        // Master Lead
        $master = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Original Prospect',
            'email' => 'duplicate_test@acmecorp.com',
            'phone' => '+15551112222',
            'company_name' => 'Acme Technologies',
            'status' => 'contacted',
            'notes' => 'Master lead notes.',
        ]);

        // Duplicate Lead
        $duplicate = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Duplicate Prospect Secondary',
            'email' => 'duplicate_test@acmecorp.com',
            'phone' => '+15551112222',
            'company_name' => 'Acme Technologies',
            'status' => 'new',
            'notes' => 'Duplicate lead notes to preserve.',
        ]);

        // 1. Check duplicate API
        $checkRes = $this->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson('/api/v1/crm/leads/check-duplicates', [
                'email' => 'duplicate_test@acmecorp.com',
                'phone' => '15551112222',
                'company_name' => 'Acme Technologies',
                'ignore_id' => $master->id,
            ]);

        $checkRes->assertStatus(200)
            ->assertJsonPath('data.has_duplicates', true);

        // 2. Perform controlled merge
        $mergeRes = $this->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson("/api/v1/crm/leads/{$master->id}/merge", [
                'duplicate_id' => $duplicate->id,
                'field_overrides' => [
                    'designation' => 'Chief Technology Officer',
                ],
            ]);

        $mergeRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('leads', ['id' => $duplicate->id]);
        $this->assertDatabaseHas('duplicate_merge_logs', [
            'master_record_id' => $master->id,
            'merged_record_id' => $duplicate->id,
        ]);

        $freshMaster = $master->fresh();
        $this->assertEquals('Chief Technology Officer', $freshMaster->designation);
        $this->assertStringContainsString('Duplicate lead notes to preserve.', $freshMaster->notes);
    }

    public function test_lead_conversion_into_contact_company_and_deal(): void
    {
        Sanctum::actingAs($this->userA);

        $lead = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Dr. Robert Vance',
            'email' => 'robert@vancerefrigeration.com',
            'phone' => '+15558889999',
            'company_name' => 'Vance Refrigeration',
            'designation' => 'Managing Director',
            'estimated_value' => 45000,
            'status' => 'qualified',
            'assigned_user_id' => $this->userA->id,
        ]);

        $convertRes = $this->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson("/api/v1/crm/leads/{$lead->id}/convert", [
                'create_deal' => true,
                'deal_name' => 'HVAC Modernization Deal',
                'deal_value' => 48000,
                'deal_stage' => 'proposal',
            ]);

        $convertRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.contact.name', 'Dr. Robert Vance')
            ->assertJsonPath('data.company.name', 'Vance Refrigeration')
            ->assertJsonPath('data.deal.name', 'HVAC Modernization Deal');

        $this->assertEquals('converted', $lead->fresh()->status);
        $this->assertNotNull($lead->fresh()->converted_at);

        $this->assertDatabaseHas('contacts', [
            'tenant_id' => $this->tenantA->id,
            'email' => 'robert@vancerefrigeration.com',
        ]);
        $this->assertDatabaseHas('companies', [
            'tenant_id' => $this->tenantA->id,
            'name' => 'Vance Refrigeration',
        ]);
        $this->assertDatabaseHas('deals', [
            'tenant_id' => $this->tenantA->id,
            'name' => 'HVAC Modernization Deal',
        ]);
    }

    public function test_customer_360_profile_aggregation(): void
    {
        Sanctum::actingAs($this->userA);

        $company = Company::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'OmniCorp Global',
            'industry' => 'Software & Telecom',
        ]);

        $contact = Contact::create([
            'tenant_id' => $this->tenantA->id,
            'company_id' => $company->id,
            'name' => 'Sarah Connor',
            'email' => 'sarah@omnicorp.com',
            'phone' => '+15553334444',
        ]);

        $customer = Customer::create([
            'tenant_id' => $this->tenantA->id,
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'name' => 'Sarah Connor',
            'email' => 'sarah@omnicorp.com',
            'status' => 'active',
        ]);

        Deal::create([
            'tenant_id' => $this->tenantA->id,
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'name' => 'Cloud Migration Contract',
            'value' => 85000,
            'stage' => 'negotiation',
        ]);

        CrmActivity::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'customer_id' => $customer->id,
            'contact_id' => $contact->id,
            'activity_type' => 'call',
            'title' => 'Executive Discovery Call',
            'description' => 'Discussed migration deadlines and SOC2 compliance.',
        ]);

        $response = $this->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->getJson("/api/v1/crm/customers/{$customer->id}/360");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Sarah Connor')
            ->assertJsonPath('data.company.name', 'OmniCorp Global')
            ->assertJsonPath('data.deals.total_count', 1)
            ->assertJsonPath('data.deals.total_value', 85000);

        $this->assertNotEmpty($response->json('data.timeline'));
    }

    public function test_tenant_isolation_on_crm_core(): void
    {
        Sanctum::actingAs($this->userB); // User B from Tenant B

        // Lead created in Tenant A
        $leadA = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Confidential Lead A',
            'email' => 'confidential@tenant-a.com',
            'phone' => '+15559990000',
            'status' => 'new',
        ]);

        // Tenant B agent attempts to access Tenant A lead
        $response = $this->withHeader('X-Workspace-Id', $this->tenantB->id)
            ->getJson("/api/v1/crm/leads/{$leadA->id}");

        $response->assertStatus(404);
    }
}
