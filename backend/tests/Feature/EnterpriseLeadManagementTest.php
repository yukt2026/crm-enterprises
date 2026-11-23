<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Company;
use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\CrmActivity;
use App\Domains\CRM\Models\Deal;
use App\Domains\CRM\Models\DuplicateMergeLog;
use App\Domains\Identity\Models\CrmRole;
use App\Domains\Identity\Models\CrmRolePermission;
use App\Domains\Identity\Models\CrmUserRole;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\LeadAcquisition\Models\LeadFollowup;
use App\Domains\LeadAcquisition\Models\LeadMergeRequest;
use App\Domains\LeadAcquisition\Models\LeadPortalIntegration;
use App\Domains\LeadAcquisition\Models\LeadRoutingRule;
use App\Domains\LeadAcquisition\Models\LeadSlaPolicy;
use App\Domains\LeadAcquisition\Models\LeadStageHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tenancy\Models\TenantUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterpriseLeadManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $adminUser;
    protected User $salesUser;
    protected User $managerUser;
    protected User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Tenants
        $this->tenantA = Tenant::create([
            'name' => 'Acme Technologies India',
            'slug' => 'acme-tech-in',
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Competitor Corp',
            'slug' => 'competitor-corp',
            'status' => 'active',
        ]);

        // 2. Setup Users
        $this->adminUser = User::create([
            'name' => 'Vikram Sharma',
            'email' => 'vikram@acmetech.in',
            'password' => bcrypt('Secret@123'),
            'role' => 'Business Owner',
        ]);

        $this->salesUser = User::create([
            'name' => 'Rahul Verma',
            'email' => 'rahul@acmetech.in',
            'password' => bcrypt('Secret@123'),
            'role' => 'Sales Representative',
        ]);

        $this->managerUser = User::create([
            'name' => 'Pooja Nair',
            'email' => 'pooja@acmetech.in',
            'password' => bcrypt('Secret@123'),
            'role' => 'Sales Manager',
        ]);

        $this->unauthorizedUser = User::create([
            'name' => 'Guest User',
            'email' => 'guest@acmetech.in',
            'password' => bcrypt('Secret@123'),
            'role' => 'Guest',
        ]);

        // 3. Workspace Memberships
        foreach ([$this->adminUser, $this->salesUser, $this->managerUser, $this->unauthorizedUser] as $u) {
            TenantUser::create([
                'tenant_id' => $this->tenantA->id,
                'user_id' => $u->id,
                'role' => $u->role,
                'status' => 'active',
            ]);
        }

        // 4. RBAC Roles & Permissions for salesUser
        $salesRole = CrmRole::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Sales Representative',
            'slug' => 'sales-rep',
            'description' => 'Sales rep role',
            'is_system' => false,
        ]);

        $actions = ['view', 'create', 'edit', 'assign', 'approve', 'reject', 'view_reports', 'manage_settings'];
        foreach ($actions as $act) {
            CrmRolePermission::create([
                'tenant_id' => $this->tenantA->id,
                'role_id' => $salesRole->id,
                'module' => 'leads',
                'action' => $act,
                'data_scope' => 'all',
            ]);
        }

        CrmUserRole::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->salesUser->id,
            'role_id' => $salesRole->id,
            'assigned_by_user_id' => $this->adminUser->id,
        ]);
    }

    /**
     * 1. Multi-source lead capture and phone number normalization
     */
    public function test_multi_source_lead_capture_and_phone_normalization(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenantA->id])
            ->postJson('/api/v1/crm/leads/capture', [
                'name' => 'Amitabh Bachchan',
                'phone' => '+91 98200 12345',
                'email' => 'AMITABH@JALSA.COM',
                'company_name' => 'AB Corp Ltd',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'product_interest' => 'Enterprise Cloud ERP',
                'requirement' => 'Looking for complete Indian GST & CRM suite',
                'budget' => 250000,
                'source' => 'website_form',
                'campaign' => 'q4_enterprise_growth',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.lead.phone', '+919820012345')
            ->assertJsonPath('data.lead.email', 'amitabh@jalsa.com')
            ->assertJsonPath('data.lead.original_source', 'website_form')
            ->assertJsonPath('data.lead.original_campaign', 'q4_enterprise_growth')
            ->assertJsonPath('data.lead.status', 'new')
            ->assertJsonPath('data.is_duplicate', false);

        $leadId = $response->json('data.lead.id');

        // Verify initial follow-up task was auto-created
        $this->assertDatabaseHas('lead_followups', [
            'tenant_id' => $this->tenantA->id,
            'lead_id' => $leadId,
            'followup_type' => 'call',
            'status' => 'pending',
        ]);

        // Verify initial stage history
        $this->assertDatabaseHas('lead_stage_histories', [
            'tenant_id' => $this->tenantA->id,
            'lead_id' => $leadId,
            'to_stage' => 'new',
        ]);
    }

    /**
     * 2. Indian B2B portal ingestion (IndiaMART and Justdial)
     */
    public function test_indiamart_and_justdial_portal_ingestion(): void
    {
        // 1. IndiaMART Ingestion
        $imResponse = $this->actingAs($this->adminUser)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenantA->id])
            ->postJson('/api/v1/crm/leads/portal/indiamart', [
                'SENDER_NAME' => 'Rajesh Sharma',
                'SENDER_MOBILE' => '9811098765',
                'SENDER_EMAIL' => 'rajesh@sharmatraders.in',
                'GLUSR_USR_COMPANYNAME' => 'Sharma Traders Pvt Ltd',
                'PRODUCT_NAME' => 'Industrial Hydraulic Valves',
                'QUERY_MESSAGE' => 'Requirement of 50 units valves urgently for Delhi factory',
                'SENDER_CITY' => 'New Delhi',
                'SENDER_STATE' => 'Delhi',
                'UNIQUE_QUERY_ID' => 'IM_QRY_998822',
            ]);

        $imResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.lead.name', 'Rajesh Sharma')
            ->assertJsonPath('data.lead.phone', '+919811098765')
            ->assertJsonPath('data.lead.source', 'indiamart')
            ->assertJsonPath('data.lead.external_lead_id', 'IM_QRY_998822')
            ->assertJsonPath('data.lead.city', 'New Delhi');

        // 2. Justdial Ingestion
        $jdResponse = $this->actingAs($this->adminUser)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenantA->id])
            ->postJson('/api/v1/crm/leads/portal/justdial', [
                'name' => 'Suresh Reddy',
                'mobile' => '+91-9944011223',
                'category' => 'Commercial Solar Rooftop Installation',
                'city' => 'Hyderabad',
                'area' => 'Hitech City',
                'pincode' => '500081',
                'leadid' => 'JD_LEAD_554433',
            ]);

        $jdResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.lead.name', 'Suresh Reddy')
            ->assertJsonPath('data.lead.phone', '+919944011223')
            ->assertJsonPath('data.lead.source', 'justdial')
            ->assertJsonPath('data.lead.external_lead_id', 'JD_LEAD_554433');
    }

    /**
     * 3. Duplicate detection, merge request submission and approval workflow
     */
    public function test_duplicate_detection_and_formal_merge_approval(): void
    {
        // Create master lead
        $master = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Deepak Chopra',
            'phone' => '+919876500001',
            'email' => 'deepak@wellness.com',
            'company_name' => 'Chopra Center',
            'status' => 'contacted',
            'city' => 'Bengaluru',
            'notes' => 'Master record notes',
        ]);

        // Create duplicate lead
        $duplicate = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Deepak C.',
            'phone' => '+919876500001',
            'email' => 'deepak@wellness.com',
            'company_name' => 'Chopra Center Worldwide',
            'status' => 'new',
            'city' => 'Bangalore',
            'notes' => 'Duplicate enquiry received via WhatsApp',
        ]);

        // Attach an activity and a followup to duplicate
        CrmActivity::create([
            'tenant_id' => $this->tenantA->id,
            'lead_id' => $duplicate->id,
            'user_id' => $this->salesUser->id,
            'activity_type' => 'note',
            'title' => 'Client called regarding demo',
            'description' => 'Demo requested for Friday',
        ]);

        LeadFollowup::create([
            'tenant_id' => $this->tenantA->id,
            'lead_id' => $duplicate->id,
            'assigned_user_id' => $this->salesUser->id,
            'followup_type' => 'call',
            'scheduled_at' => now()->addDay(),
            'status' => 'pending',
        ]);

        // 1. Submit Merge Request
        $submitRes = $this->actingAs($this->salesUser)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenantA->id])
            ->postJson('/api/v1/crm/leads/merge-requests', [
                'master_lead_id' => $master->id,
                'duplicate_lead_id' => $duplicate->id,
                'field_overrides' => [
                    'company_name' => 'Chopra Center Worldwide',
                ],
                'similarity_reasons' => ['Exact Phone Match', 'Exact Email Match'],
            ]);

        $submitRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $mergeReqId = $submitRes->json('data.id');

        // 2. Approve Merge Request
        $approveRes = $this->actingAs($this->adminUser)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenantA->id])
            ->postJson("/api/v1/crm/leads/merge-requests/{$mergeReqId}/approve");

        $approveRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.company_name', 'Chopra Center Worldwide');

        // Verify duplicate is soft-deleted
        $this->assertSoftDeleted('leads', ['id' => $duplicate->id]);

        // Verify activities and followups were transferred to master
        $this->assertDatabaseHas('crm_activities', [
            'lead_id' => $master->id,
            'title' => 'Client called regarding demo',
        ]);

        $this->assertDatabaseHas('lead_followups', [
            'lead_id' => $master->id,
            'followup_type' => 'call',
        ]);

        // Verify audit log
        $this->assertDatabaseHas('duplicate_merge_logs', [
            'tenant_id' => $this->tenantA->id,
            'master_record_id' => $master->id,
            'merged_record_id' => $duplicate->id,
        ]);
    }

    /**
     * 4. Advanced routing rules engine with round-robin and least-active assignment
     */
    public function test_routing_rules_engine_and_least_active_assignment(): void
    {
        // Create least-active routing rule for wholesale/indiamart leads
        $rule = LeadRoutingRule::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Wholesale Leads Distribution',
            'priority' => 1,
            'is_active' => true,
            'conditions' => [
                'sources' => ['indiamart', 'tradeindia'],
                'min_budget' => 50000,
            ],
            'assignment_method' => 'least_active',
            'target_user_ids' => [$this->salesUser->id, $this->managerUser->id],
            'fallback_user_id' => $this->adminUser->id,
        ]);

        // Give salesUser 3 open leads, managerUser 0 open leads
        for ($i = 0; $i < 3; $i++) {
            Lead::create([
                'tenant_id' => $this->tenantA->id,
                'name' => "Open Lead {$i}",
                'status' => 'new',
                'assigned_user_id' => $this->salesUser->id,
            ]);
        }

        // Now capture an IndiaMART lead meeting the rule criteria
        $response = $this->actingAs($this->adminUser)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenantA->id])
            ->postJson('/api/v1/crm/leads/capture', [
                'name' => 'Kishore Kumar',
                'phone' => '+919876540099',
                'source' => 'indiamart',
                'budget' => 100000,
            ]);

        $response->assertStatus(201);
        // Should be assigned to managerUser because managerUser has 0 open leads (least active)
        $this->assertEquals($this->managerUser->id, $response->json('data.lead.assigned_user_id'));
    }

    /**
     * 5. First-contact SLA calculation, Level 1 warning and Level 2 manager escalation
     */
    public function test_sla_calculation_and_escalation_tiers(): void
    {
        $policy = LeadSlaPolicy::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Fast Response SLA',
            'is_default' => true,
            'first_response_time_minutes' => 15,
            'followup_deadline_hours' => 24,
            'max_idle_hours' => 48,
            'working_hours_only' => false,
            'escalation_level_1_minutes' => 15,
            'escalation_level_2_minutes' => 45,
            'auto_reassign_on_escalation' => true,
            'manager_user_id' => $this->managerUser->id,
        ]);

        // Create an uncontacted lead with due date 20 minutes ago (Level 1 breach)
        $lead1 = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Overdue Lead 1',
            'phone' => '+919999900001',
            'status' => 'new',
            'assigned_user_id' => $this->salesUser->id,
            'sla_policy_id' => $policy->id,
            'sla_first_response_due_at' => now()->subMinutes(20),
            'sla_status' => 'within_sla',
        ]);

        // Create an uncontacted lead with due date 50 minutes ago (Level 2 breach)
        $lead2 = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Severe Overdue Lead 2',
            'phone' => '+919999900002',
            'status' => 'new',
            'assigned_user_id' => $this->salesUser->id,
            'sla_policy_id' => $policy->id,
            'sla_first_response_due_at' => now()->subMinutes(50),
            'sla_status' => 'within_sla',
        ]);

        // Trigger SLA evaluation scan
        $scanRes = $this->actingAs($this->adminUser)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenantA->id])
            ->postJson('/api/v1/crm/leads/sla/evaluate');

        $scanRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.escalated_count', 2);

        // Verify Lead 1 received Level 1 escalation (sla_status: breached, rep remains salesUser)
        $lead1->refresh();
        $this->assertEquals('breached', $lead1->sla_status);
        $this->assertEquals($this->salesUser->id, $lead1->assigned_user_id);
        $this->assertDatabaseHas('lead_escalation_logs', [
            'lead_id' => $lead1->id,
            'escalation_level' => 1,
        ]);

        // Verify Lead 2 received Level 2 escalation (sla_status: escalated, reassigned to managerUser)
        $lead2->refresh();
        $this->assertEquals('escalated', $lead2->sla_status);
        $this->assertEquals($this->managerUser->id, $lead2->assigned_user_id);
        $this->assertEquals(1, $lead2->reassigned_count);
        $this->assertDatabaseHas('lead_escalation_logs', [
            'lead_id' => $lead2->id,
            'escalation_level' => 2,
            'new_user_id' => $this->managerUser->id,
        ]);
    }

    /**
     * 6. First response logging and SLA compliance
     */
    public function test_first_response_logging_and_compliance(): void
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Prompt Contact Lead',
            'phone' => '+919876543210',
            'status' => 'new',
            'assigned_user_id' => $this->salesUser->id,
            'sla_first_response_due_at' => now()->addMinutes(10),
            'sla_status' => 'within_sla',
        ]);

        $response = $this->actingAs($this->salesUser)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenantA->id])
            ->postJson("/api/v1/crm/leads/{$lead->id}/record-response", [
                'channel' => 'call',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sla_status', 'within_sla')
            ->assertJsonPath('data.status', 'contacted');

        $lead->refresh();
        $this->assertNotNull($lead->sla_first_responded_at);
        $this->assertNotNull($lead->last_contacted_at);
        $this->assertEquals('within_sla', $lead->sla_status);
    }

    /**
     * 7. Lead lifecycle stage transitions and history log
     */
    public function test_lifecycle_stage_transitions_and_history(): void
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Lifecycle Progression Lead',
            'phone' => '+919988776655',
            'status' => 'new',
            'assigned_user_id' => $this->salesUser->id,
        ]);

        // Transition 1: new -> contacted
        $res1 = $this->actingAs($this->salesUser)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenantA->id])
            ->postJson("/api/v1/crm/leads/{$lead->id}/transition-stage", [
                'to_stage' => 'contacted',
                'reason' => 'First phone discussion completed',
            ]);
        $res1->assertStatus(200)->assertJsonPath('data.status', 'contacted');

        // Transition 2: contacted -> lost
        $res2 = $this->actingAs($this->salesUser)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenantA->id])
            ->postJson("/api/v1/crm/leads/{$lead->id}/transition-stage", [
                'to_stage' => 'lost',
                'reason' => 'Competitor offered 40% discount',
            ]);
        $res2->assertStatus(200)->assertJsonPath('data.status', 'lost');

        $lead->refresh();
        $this->assertEquals('Competitor offered 40% discount', $lead->lost_reason);

        // Fetch stage history
        $histRes = $this->actingAs($this->salesUser)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenantA->id])
            ->getJson("/api/v1/crm/leads/{$lead->id}/stage-history");

        $histRes->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /**
     * 8. Follow-up scheduling, completion and overdue filter
     */
    public function test_followup_scheduling_and_completion(): void
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Follow-up Target Lead',
            'phone' => '+919911223344',
            'status' => 'contacted',
            'assigned_user_id' => $this->salesUser->id,
        ]);

        // Schedule follow-up
        $schedRes = $this->actingAs($this->salesUser)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenantA->id])
            ->postJson("/api/v1/crm/leads/{$lead->id}/followups", [
                'followup_type' => 'meeting',
                'scheduled_at' => now()->addDays(2)->toIso8601String(),
                'notes' => 'Product demo over Zoom',
            ]);

        $schedRes->assertStatus(201)
            ->assertJsonPath('data.followup_type', 'meeting')
            ->assertJsonPath('data.status', 'pending');

        $followupId = $schedRes->json('data.id');

        // Complete follow-up
        $compRes = $this->actingAs($this->salesUser)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenantA->id])
            ->postJson("/api/v1/crm/leads/followups/{$followupId}/complete", [
                'outcome' => 'interested',
                'notes' => 'Demo went exceptionally well. Proposal requested.',
            ]);

        $compRes->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.outcome', 'interested');

        $this->assertDatabaseHas('lead_followups', [
            'id' => $followupId,
            'status' => 'completed',
            'outcome' => 'interested',
        ]);
    }

    /**
     * 9. Lead conversion with contact deduplication and optional project creation
     */
    public function test_lead_conversion_with_contact_deduplication(): void
    {
        // Existing contact with phone
        $existingContact = Contact::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Sunil Gavaskar',
            'phone' => '+919822233444',
            'email' => 'sunil@cricket.in',
        ]);

        $lead = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Sunil Gavaskar',
            'phone' => '+919822233444',
            'email' => 'sunil@cricket.in',
            'company_name' => 'Sunny Media Ltd',
            'estimated_value' => 150000,
            'product_interest' => 'Sports Management SaaS',
            'status' => 'qualified',
            'assigned_user_id' => $this->salesUser->id,
        ]);

        $response = $this->actingAs($this->salesUser)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenantA->id])
            ->postJson("/api/v1/crm/leads/{$lead->id}/convert", [
                'deal_name' => 'Sunny Media Annual Contract',
                'create_project' => true,
                'project_name' => 'Sunny Media Onboarding',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Verify existing contact was reused, not duplicated!
        $this->assertEquals($existingContact->id, $response->json('data.contact.id'));
        $this->assertEquals(1, Contact::where('tenant_id', $this->tenantA->id)->where('phone', '+919822233444')->count());

        // Verify deal and project created
        $this->assertNotNull($response->json('data.deal.id'));
        $this->assertNotNull($response->json('data.project.id'));

        $lead->refresh();
        $this->assertEquals('converted', $lead->status);
    }

    /**
     * 10. Performance, attribution and SLA leakage reporting dashboard
     */
    public function test_lead_performance_and_leakage_reports(): void
    {
        // Create sample data for reports
        Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Report Lead 1',
            'source' => 'indiamart',
            'status' => 'converted',
            'estimated_value' => 50000,
            'assigned_user_id' => $this->salesUser->id,
        ]);

        Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Report Lead 2',
            'source' => 'website_form',
            'status' => 'new',
            'estimated_value' => 75000,
            'assigned_user_id' => $this->salesUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenantA->id])
            ->getJson('/api/v1/crm/leads/reports/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_leads',
                        'active_leads',
                        'converted_leads',
                        'conversion_rate_percent',
                        'total_pipeline_value',
                    ],
                    'source_performance',
                    'lead_ageing',
                    'followup_metrics',
                    'leakage_and_sla',
                    'lost_and_recycled',
                ],
            ]);
    }

    /**
     * 11. Strict RBAC permission enforcement
     */
    public function test_granular_rbac_permission_enforcement(): void
    {
        // unauthorizedUser does not have leads.manage_settings permission
        $response = $this->actingAs($this->unauthorizedUser)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenantA->id])
            ->postJson('/api/v1/crm/leads/routing/rules', [
                'name' => 'Unauthorized Rule',
                'assignment_method' => 'round_robin',
            ]);

        $response->assertStatus(403);
    }

    /**
     * 12. Strict multi-tenant isolation
     */
    public function test_strict_cross_tenant_isolation_on_leads_and_routing(): void
    {
        // Create lead in Tenant B
        $tenantBLead = Lead::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Secret Competitor Lead',
            'phone' => '+919999988888',
            'status' => 'new',
        ]);

        // Attempt to access or modify Tenant B lead from Tenant A session
        $response = $this->actingAs($this->adminUser)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenantA->id])
            ->getJson("/api/v1/crm/leads/{$tenantBLead->id}");

        $response->assertStatus(404);
    }
}
