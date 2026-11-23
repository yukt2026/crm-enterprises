<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Company;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Identity\Models\CrmRole;
use App\Domains\Identity\Models\CrmRolePermission;
use App\Domains\Identity\Services\RbacService;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tenancy\Models\TenantUser;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EnterpriseRbacDataPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $adminUser;
    protected User $salesUserA;
    protected User $salesUserB;
    protected User $accountantUser;
    protected RbacService $rbacService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->rbacService = app(RbacService::class);
        $this->rbacService->seedSystemRolePresets();

        $this->tenant = Tenant::where('slug', 'acme-enterprise')->first();
        $this->adminUser = User::where('email', 'john@nexus.com')->first();

        // Create Sales User A (Assigned Sales Executive role)
        $this->salesUserA = User::create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'name' => 'Agent Alpha',
            'email' => 'alpha@nexus.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'Sales Executive',
            'status' => 'active',
            'department' => 'Sales',
        ]);
        TenantUser::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->salesUserA->id,
            'role' => 'Sales Executive',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        // Create Sales User B
        $this->salesUserB = User::create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'name' => 'Agent Beta',
            'email' => 'beta@nexus.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'Sales Executive',
            'status' => 'active',
            'department' => 'Sales',
        ]);
        TenantUser::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->salesUserB->id,
            'role' => 'Sales Executive',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        // Create Accountant User
        $this->accountantUser = User::create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'name' => 'Carol Ledger',
            'email' => 'carol@nexus.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'Accountant',
            'status' => 'active',
            'department' => 'Finance',
        ]);
        TenantUser::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->accountantUser->id,
            'role' => 'Accountant',
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }

    /**
     * 1. Test all 18 default system role presets exist with granular permissions
     */
    public function test_system_role_presets_are_seeded_with_correct_permissions(): void
    {
        $rolesCount = CrmRole::whereNull('tenant_id')->where('is_system', true)->count();
        $this->assertGreaterThanOrEqual(18, $rolesCount);

        // Verify specific preset roles exist
        $requiredSlugs = [
            'org_owner', 'super_admin', 'org_admin', 'branch_manager', 'dept_manager',
            'sales_manager', 'sales_executive', 'accountant', 'hr_manager', 'hr_executive',
            'marketing_manager', 'marketing_executive', 'support_manager', 'support_executive',
            'project_manager', 'project_employee', 'employee', 'customer_portal_user',
        ];

        foreach ($requiredSlugs as $slug) {
            $role = CrmRole::where('slug', $slug)->first();
            $this->assertNotNull($role, "System preset role '{$slug}' should exist.");
            $this->assertTrue($role->is_system);
            $this->assertNotEmpty($role->permissions, "Role '{$slug}' should have permissions configured.");
        }
    }

    /**
     * 2. Test Custom Role creation with granular module action permissions and data scopes
     */
    public function test_create_custom_role_with_granular_permissions_and_data_scope(): void
    {
        $payload = [
            'name' => 'Regional Territory Auditor',
            'description' => 'Reviews branch sales operations without editing capabilities',
            'color' => '#10B981',
            'is_active' => true,
            'permissions' => [
                ['module' => 'leads', 'action' => 'view', 'data_scope' => 'branch'],
                ['module' => 'deals', 'action' => 'view', 'data_scope' => 'branch'],
                ['module' => 'invoices', 'action' => 'view', 'data_scope' => 'branch'],
                ['module' => 'quotations', 'action' => 'view', 'data_scope' => 'branch'],
            ],
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/crm/roles', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Regional Territory Auditor')
            ->assertJsonPath('data.is_system', false);

        $roleId = $response->json('data.id');
        $this->assertDatabaseHas('crm_roles', [
            'id' => $roleId,
            'name' => 'Regional Territory Auditor',
            'tenant_id' => $this->tenant->id,
            'is_system' => false,
        ]);

        $this->assertDatabaseHas('crm_role_permissions', [
            'role_id' => $roleId,
            'module' => 'leads',
            'action' => 'view',
            'data_scope' => 'branch',
        ]);
    }

    /**
     * 3. Test duplicating a role and copying permissions
     */
    public function test_duplicate_role_and_copy_permissions(): void
    {
        $sourceRole = CrmRole::where('slug', 'sales_executive')->first();
        $this->assertNotNull($sourceRole);

        // 1. Duplicate
        $dupResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/crm/roles/{$sourceRole->id}/duplicate", [
                'name' => 'Senior Enterprise SDR',
                'description' => 'Extended sales role with higher capabilities',
            ]);

        $dupResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Senior Enterprise SDR');

        $clonedRoleId = $dupResponse->json('data.id');
        $clonedRole = CrmRole::with('permissions')->find($clonedRoleId);

        $this->assertEquals($sourceRole->permissions->count(), $clonedRole->permissions->count());

        // 2. Create another empty role and copy permissions from cloned
        $targetRole = CrmRole::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Junior SDR',
            'slug' => 'junior-sdr',
            'is_system' => false,
            'is_active' => true,
        ]);

        $copyResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/crm/roles/{$targetRole->id}/copy-from", [
                'source_role_id' => $clonedRoleId,
            ]);

        $copyResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals($clonedRole->permissions->count(), $targetRole->fresh()->permissions->count());
    }

    /**
     * 4. Test comparing two roles side-by-side
     */
    public function test_compare_roles_identifies_differences(): void
    {
        $salesRole = CrmRole::where('slug', 'sales_executive')->first();
        $accountantRole = CrmRole::where('slug', 'accountant')->first();

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/crm/roles/compare', [
                'role_a_id' => $salesRole->id,
                'role_b_id' => $accountantRole->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $comparison = $response->json('data.comparison');
        $this->assertNotEmpty($comparison);

        // Invoices should show difference (Accountant has it, Sales Executive does not)
        $invoiceDiff = collect($comparison)->firstWhere('module', 'invoices');
        $this->assertNotNull($invoiceDiff);
        $this->assertTrue($invoiceDiff['is_different']);
    }

    /**
     * 5. Test assigning and removing roles for users with permission cache invalidation
     */
    public function test_assign_and_remove_role_for_user_with_cache_invalidation(): void
    {
        $role = CrmRole::where('slug', 'sales_executive')->first();

        // 1. Assign Role
        $assignResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/crm/roles/assign', [
                'user_id' => $this->salesUserA->id,
                'role_id' => $role->id,
                'is_primary' => true,
            ]);

        $assignResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('crm_user_roles', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->salesUserA->id,
            'role_id' => $role->id,
            'is_primary' => true,
        ]);

        // 2. Fetch effective permissions
        $permResponse = $this->actingAs($this->salesUserA, 'sanctum')
            ->getJson('/api/v1/crm/roles/effective-permissions');

        $permResponse->assertStatus(200)
            ->assertJsonPath('data.user_id', $this->salesUserA->id);

        $modules = $permResponse->json('data.modules');
        $this->assertArrayHasKey('leads', $modules);

        // 3. Remove Role
        $removeResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/crm/roles/remove-user', [
                'user_id' => $this->salesUserA->id,
                'role_id' => $role->id,
            ]);

        $removeResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('crm_user_roles', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->salesUserA->id,
            'role_id' => $role->id,
        ]);
    }

    /**
     * 6. Test Data-Level Permissions: Own and Assigned Scopes on Leads
     */
    public function test_own_and_assigned_data_scope_enforcement_on_leads(): void
    {
        $salesRole = CrmRole::where('slug', 'sales_executive')->first();
        $this->rbacService->assignUserRole($this->salesUserA, $salesRole, true);
        $this->rbacService->assignUserRole($this->salesUserB, $salesRole, true);

        // Seed Lead assigned to Sales User A
        $leadA = Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Lead For Agent Alpha',
            'email' => 'client-alpha@corp.com',
            'phone' => '+15551110001',
            'assigned_user_id' => $this->salesUserA->id,
            'status' => 'new',
            'estimated_value' => 25000,
        ]);

        // Seed Lead assigned to Sales User B
        $leadB = Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Lead For Agent Beta',
            'email' => 'client-beta@corp.com',
            'phone' => '+15552220002',
            'assigned_user_id' => $this->salesUserB->id,
            'status' => 'new',
            'estimated_value' => 45000,
        ]);

        // 1. Agent Alpha queries leads list -> should only see Lead A, not Lead B
        $alphaList = $this->actingAs($this->salesUserA, 'sanctum')
            ->getJson('/api/v1/crm/leads');

        $alphaList->assertStatus(200);
        $alphaLeads = collect($alphaList->json('data'));
        $this->assertTrue($alphaLeads->contains('id', $leadA->id));
        $this->assertFalse($alphaLeads->contains('id', $leadB->id));

        // 2. Agent Alpha attempts to access Lead B directly by ID -> HTTP 403 Forbidden
        $directAccess = $this->actingAs($this->salesUserA, 'sanctum')
            ->getJson("/api/v1/crm/leads/{$leadB->id}");

        $directAccess->assertStatus(403);
    }

    /**
     * 7. Test Action-Level Permissions: Restrict Deletion and Export
     */
    public function test_action_permissions_prevent_unauthorized_deletion_and_export(): void
    {
        $salesRole = CrmRole::where('slug', 'sales_executive')->first();
        $this->rbacService->assignUserRole($this->salesUserA, $salesRole, true);

        $leadA = Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Protected Lead',
            'email' => 'protected@corp.com',
            'assigned_user_id' => $this->salesUserA->id,
            'status' => 'new',
        ]);

        // 1. Sales Executive does not have delete permission on leads -> HTTP 403
        $delResponse = $this->actingAs($this->salesUserA, 'sanctum')
            ->deleteJson("/api/v1/crm/leads/{$leadA->id}");

        $delResponse->assertStatus(403);
        $this->assertDatabaseHas('leads', ['id' => $leadA->id]);

        // 2. Sales Executive does not have export permission -> HTTP 403
        $exportResponse = $this->actingAs($this->salesUserA, 'sanctum')
            ->get('/api/v1/crm/leads/export');

        $exportResponse->assertStatus(403);
    }

    /**
     * 8. Test Module Cross-Boundary Separation: Accountant cannot view HR and Support cannot view Invoices
     */
    public function test_module_cross_boundary_separation(): void
    {
        $accountantRole = CrmRole::where('slug', 'accountant')->first();
        $this->rbacService->assignUserRole($this->accountantUser, $accountantRole, true);

        // 1. Accountant can access invoices list
        $invResponse = $this->actingAs($this->accountantUser, 'sanctum')
            ->getJson('/api/v1/crm/invoices');
        $invResponse->assertStatus(200);

        // 2. Sales Executive cannot access invoices list -> HTTP 403
        $salesInvResponse = $this->actingAs($this->salesUserA, 'sanctum')
            ->getJson('/api/v1/crm/invoices');
        $salesInvResponse->assertStatus(403);
    }

    /**
     * 9. Test protected system roles cannot be deleted, and custom roles in use cannot be deleted
     */
    public function test_protected_system_roles_cannot_be_deleted(): void
    {
        $systemRole = CrmRole::where('slug', 'org_owner')->first();

        // 1. Attempt to delete system preset role -> 422
        $sysDelete = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/v1/crm/roles/{$systemRole->id}");

        $sysDelete->assertStatus(422)
            ->assertJsonPath('success', false);

        // 2. Create custom role and assign to user
        $customRole = CrmRole::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Temporary Dispatcher',
            'slug' => 'temp-dispatcher',
            'is_system' => false,
        ]);
        $this->rbacService->assignUserRole($this->salesUserA, $customRole);

        // 3. Attempt to delete role with active user -> 422
        $inUseDelete = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/v1/crm/roles/{$customRole->id}");

        $inUseDelete->assertStatus(422)
            ->assertJsonPath('success', false);

        // 4. Remove user, then delete -> 200
        $this->rbacService->removeUserRole($this->salesUserA, $customRole);
        $safeDelete = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/v1/crm/roles/{$customRole->id}");

        $safeDelete->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
