<?php

namespace Tests\Feature;

use App\Domains\AuditSecurity\Models\AuditLog;
use App\Domains\AuditSecurity\Services\AuditLogger;
use App\Domains\Communications\Services\NotificationService;
use App\Domains\CustomFields\Models\CustomFieldDefinition;
use App\Domains\CustomFields\Models\CustomFieldValue;
use App\Domains\Identity\Models\Notification;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Media\Models\MediaFile;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tenancy\Models\TenantUser;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class SharedPlatformServicesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Tenant $tenant;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->user = User::where('email', 'john@nexus.com')->first();
        $this->tenant = Tenant::first();

        $tokenResult = $this->user->createToken('test_token');
        $this->token = $tokenResult->plainTextToken;
    }

    public function test_notification_creation_and_preferences(): void
    {
        // 1. Dispatch notification
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'X-Workspace-Id' => (string) $this->tenant->id,
        ])->postJson('/api/v1/notifications/dispatch-test', [
            'type' => 'lead.assigned',
            'title' => 'New High-Value Lead Assigned',
            'message' => 'Apex Global has been assigned to your sales roster.',
            'action_url' => '/leads/1',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'New High-Value Lead Assigned');

        // 2. Fetch unread count
        $countRes = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'X-Workspace-Id' => (string) $this->tenant->id,
        ])->getJson('/api/v1/notifications/unread-count');

        $countRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(1, $countRes->json('data.unread_count'));

        // 3. Mark notification as read
        $notifId = $response->json('data.id');
        $readRes = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'X-Workspace-Id' => (string) $this->tenant->id,
        ])->putJson("/api/v1/notifications/{$notifId}/read");

        $readRes->assertStatus(200)
            ->assertJsonPath('data.is_read', true);
    }

    public function test_secure_file_upload_and_signed_url(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('contract_nda.pdf', 500, 'application/pdf');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'X-Workspace-Id' => (string) $this->tenant->id,
        ])->postJson('/api/v1/media', [
            'file' => $file,
            'is_private' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.original_name', 'contract_nda.pdf')
            ->assertJsonPath('data.mime_type', 'application/pdf');

        $uuid = $response->json('data.uuid');
        $downloadUrl = $response->json('data.download_url');

        $this->assertNotEmpty($uuid);
        $this->assertNotEmpty($downloadUrl);
        $this->assertStringContainsString('signature=', $downloadUrl);

        // Fetch details
        $detailRes = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'X-Workspace-Id' => (string) $this->tenant->id,
        ])->getJson("/api/v1/media/{$uuid}");

        $detailRes->assertStatus(200)
            ->assertJsonPath('data.uuid', $uuid);
    }

    public function test_custom_fields_definition_and_values(): void
    {
        // 1. Create custom field definition for 'lead'
        $defRes = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'X-Workspace-Id' => (string) $this->tenant->id,
        ])->postJson('/api/v1/custom-fields', [
            'entity_type' => 'lead',
            'name' => 'Tax Identification Number',
            'field_key' => 'tax_id_number',
            'type' => 'text',
            'is_required' => true,
            'default_value' => 'N/A',
        ]);

        $defRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.field_key', 'tax_id_number');

        $defId = $defRes->json('data.id');

        // 2. Attach value to Lead #1
        $lead = Lead::first();
        $this->assertNotNull($lead);

        $valRes = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'X-Workspace-Id' => (string) $this->tenant->id,
        ])->postJson("/api/v1/custom-fields/values/lead/{$lead->id}", [
            'fields' => [
                'tax_id_number' => 'GSTIN-9948271',
            ],
        ]);

        $valRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // 3. Retrieve values
        $getRes = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'X-Workspace-Id' => (string) $this->tenant->id,
        ])->getJson("/api/v1/custom-fields/values/lead/{$lead->id}");

        $getRes->assertStatus(200);
        $fieldItems = collect($getRes->json('data'));
        $taxField = $fieldItems->firstWhere('field_key', 'tax_id_number');

        $this->assertNotNull($taxField);
        $this->assertEquals('GSTIN-9948271', $taxField['value']);
    }

    public function test_global_search_across_tenant_entities(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'X-Workspace-Id' => (string) $this->tenant->id,
        ])->getJson('/api/v1/search?q=Apex');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $results = $response->json('data');
        $this->assertIsArray($results);
    }

    public function test_audit_log_immutability(): void
    {
        // 1. Create Audit record
        $log = AuditLogger::log('manual_system_checkpoint', null, ['step' => 1], ['step' => 2], $this->user);
        $this->assertNotNull($log->id);

        // 2. Attempt update - must throw RuntimeException
        $updateBlocked = false;
        try {
            $log->update(['action' => 'tampered_action']);
        } catch (RuntimeException $e) {
            $updateBlocked = true;
        }
        $this->assertTrue($updateBlocked, 'Audit log update must be blocked by immutability enforcement.');

        // 3. Attempt delete - must throw RuntimeException
        $deleteBlocked = false;
        try {
            $log->delete();
        } catch (RuntimeException $e) {
            $deleteBlocked = true;
        }
        $this->assertTrue($deleteBlocked, 'Audit log deletion must be blocked by immutability enforcement.');
    }
}
