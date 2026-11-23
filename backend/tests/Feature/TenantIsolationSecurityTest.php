<?php

namespace Tests\Feature;

use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * Test cross-tenant access is blocked with 403 Forbidden
     */
    public function test_user_cannot_access_unauthorized_workspace(): void
    {
        $marcus = User::where('email', 'marcus@starlight.io')->first();
        $token = $marcus->createToken('test_token')->plainTextToken;

        // Acme Enterprise is Tenant 1; Marcus only belongs to Tenant 2
        $acmeTenant = Tenant::where('slug', 'acme-enterprise')->first();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Workspace-Id' => (string) $acmeTenant->id,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/crm/leads');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'status_code' => 403,
            ]);
    }

    /**
     * Test tenant-scoped data queries strictly isolate records
     */
    public function test_tenant_records_are_strictly_isolated(): void
    {
        $elena = User::where('email', 'elena@nexus.com')->first();
        $token = $elena->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->getJson('/api/v1/crm/leads');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Elena should only see Tenant 1's lead ("Sarah Connor")
        $this->assertNotEmpty($data);
        foreach ($data as $lead) {
            $this->assertEquals('Sarah Connor', $lead['name']);
            $this->assertNotEquals('Dave Bowman', $lead['name']);
        }
    }

    /**
     * Test authorized workspace switching changes active data scope
     */
    public function test_multi_workspace_user_can_switch_context(): void
    {
        $john = User::where('email', 'john@nexus.com')->first();
        $token = $john->createToken('test_token')->plainTextToken;

        $starlightTenant = Tenant::where('slug', 'starlight-logistics')->first();

        // Switch to Tenant 2 (Starlight)
        $switchRes = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->postJson('/api/v1/workspaces/switch', [
            'workspace_id' => $starlightTenant->id,
        ]);

        $switchRes->assertStatus(200);

        // Query leads under Tenant 2 context
        $leadsRes = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Workspace-Id' => (string) $starlightTenant->id,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/crm/leads');

        $leadsRes->assertStatus(200);
        $data = $leadsRes->json('data');

        // Under Tenant 2, Dave Bowman is returned
        $names = array_column($data, 'name');
        $this->assertContains('Dave Bowman', $names);
    }

    /**
     * Test workspace invitation workflow
     */
    public function test_workspace_invitation_generation_and_acceptance(): void
    {
        $john = User::where('email', 'john@nexus.com')->first();
        $token = $john->createToken('test_token')->plainTextToken;

        // Invite new developer
        $inviteRes = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->postJson('/api/v1/workspaces/invitations', [
            'email' => 'newdev@example.com',
            'role' => 'Employee',
        ]);

        $inviteRes->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['invitationId', 'email', 'token', 'inviteUrl'],
            ]);

        $inviteToken = $inviteRes->json('data.token');

        // Create new user for invitation recipient
        $newUser = User::factory()->create([
            'email' => 'newdev@example.com',
        ]);
        $recipientToken = $newUser->createToken('recipient_token')->plainTextToken;

        $invitation = \App\Domains\Tenancy\Models\WorkspaceInvitation::where('token', $inviteToken)->first();
        $this->assertNotNull($invitation);

        // Reset auth guards so Sanctum resolves the new recipient token
        $this->app['auth']->forgetGuards();

        // Recipient accepts invitation
        $acceptRes = $this->withHeaders([
            'Authorization' => "Bearer {$recipientToken}",
            'Accept' => 'application/json',
        ])->postJson("/api/v1/workspaces/invitations/{$inviteToken}/accept");

        $acceptRes->assertStatus(200);
        $this->assertEquals(1, \App\Domains\Tenancy\Models\TenantUser::where('user_id', $newUser->id)->where('tenant_id', $invitation->tenant_id)->count());
    }
}
