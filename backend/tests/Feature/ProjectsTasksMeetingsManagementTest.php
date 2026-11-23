<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Meeting;
use App\Domains\Projects\Models\MeetingAttendee;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMilestone;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskDependency;
use App\Domains\Projects\Models\TaskTimeLog;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectsTasksMeetingsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $managerUser;
    protected User $memberUser;
    protected User $tenantBUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Acme Operations Corp',
            'domain' => 'acme-ops.test',
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Apex Global Corp',
            'domain' => 'apex-ops.test',
            'status' => 'active',
        ]);

        $this->managerUser = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'role' => 'Admin',
            'name' => 'Alex Project Lead',
            'email' => 'alex.lead@acme-ops.test',
        ]);
        $this->managerUser->workspaces()->attach($this->tenantA->id, ['role' => 'Admin', 'status' => 'active']);

        $this->memberUser = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'role' => 'Employee',
            'name' => 'Dev Sarah',
            'email' => 'sarah.dev@acme-ops.test',
        ]);
        $this->memberUser->workspaces()->attach($this->tenantA->id, ['role' => 'Employee', 'status' => 'active']);

        $this->tenantBUser = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'current_tenant_id' => $this->tenantB->id,
            'role' => 'Admin',
            'name' => 'Brian Apex',
            'email' => 'brian@apex-ops.test',
        ]);
        $this->tenantBUser->workspaces()->attach($this->tenantB->id, ['role' => 'Admin', 'status' => 'active']);
    }

    public function test_can_create_project_with_milestones_and_team_members(): void
    {
        Sanctum::actingAs($this->managerUser);

        $payload = [
            'name' => 'CRM Cloud Migration',
            'description' => 'Migrate on-premise infrastructure to Kubernetes multi-tenant cloud.',
            'status' => 'in_progress',
            'priority' => 'high',
            'budget' => 50000.00,
            'estimated_hours' => 320,
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addMonths(3)->toDateString(),
            'team_members' => [
                ['user_id' => $this->memberUser->id, 'role' => 'member'],
            ],
            'milestones' => [
                ['title' => 'Architecture Blueprint', 'due_date' => Carbon::today()->addWeeks(2)->toDateString(), 'status' => 'completed', 'progress' => 100],
                ['title' => 'Database Sync Pipeline', 'due_date' => Carbon::today()->addWeeks(6)->toDateString(), 'status' => 'in_progress', 'progress' => 40],
            ],
        ];

        $res = $this->postJson('/api/v1/crm/projects', $payload);
        $res->assertStatus(201);
        $res->assertJsonPath('data.name', 'CRM Cloud Migration');
        $res->assertJsonPath('data.priority', 'high');

        $projectId = $res->json('data.id');
        $project = Project::with(['teamMembers', 'milestones'])->findOrFail($projectId);

        $this->assertCount(1, $project->teamMembers);
        $this->assertCount(2, $project->milestones);
        $this->assertEquals(70, $project->progress); // average of 100 and 40
    }

    public function test_project_progress_is_recalculated_when_milestones_or_tasks_update(): void
    {
        Sanctum::actingAs($this->managerUser);

        $project = Project::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Mobile App Revamp',
            'status' => 'in_progress',
            'progress' => 0,
        ]);

        // Add 2 tasks
        $task1 = Task::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'title' => 'Design wireframes',
            'status' => 'completed',
        ]);

        $task2 = Task::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'title' => 'Implement API auth',
            'status' => 'in_progress',
        ]);

        $project->recalculateProgress();
        $this->assertEquals(50, $project->fresh()->progress);

        // Complete task2
        $res = $this->postJson("/api/v1/crm/tasks/{$task2->id}/complete");
        $res->assertStatus(200);

        $this->assertEquals(100, $project->fresh()->progress);
    }

    public function test_can_archive_and_restore_projects(): void
    {
        Sanctum::actingAs($this->managerUser);

        $project = Project::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Legacy Billing Upgrade',
            'status' => 'completed',
            'is_archived' => false,
        ]);

        $res = $this->postJson("/api/v1/crm/projects/{$project->id}/archive");
        $res->assertStatus(200);
        $this->assertTrue($project->fresh()->is_archived);
        $this->assertNotNull($project->fresh()->archived_at);

        // Active list should not include it by default
        $listRes = $this->getJson('/api/v1/crm/projects');
        $listRes->assertStatus(200);
        $this->assertEmpty($listRes->json('data'));

        // Archived filter should include it
        $archivedRes = $this->getJson('/api/v1/crm/projects?archived=true');
        $archivedRes->assertStatus(200);
        $this->assertCount(1, $archivedRes->json('data'));

        // Restore
        $restoreRes = $this->postJson("/api/v1/crm/projects/{$project->id}/restore");
        $restoreRes->assertStatus(200);
        $this->assertFalse($project->fresh()->is_archived);
    }

    public function test_can_create_task_and_log_time_spent(): void
    {
        Sanctum::actingAs($this->managerUser);

        $project = Project::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Security Audit',
            'status' => 'in_progress',
        ]);

        $taskRes = $this->postJson('/api/v1/crm/tasks', [
            'project_id' => $project->id,
            'title' => 'Penetration Testing on API Gateway',
            'priority' => 'urgent',
            'assigned_user_id' => $this->memberUser->id,
            'due_date' => Carbon::tomorrow()->toDateString(),
            'estimated_hours' => 12,
            'checklist_items' => [
                ['id' => '1', 'text' => 'Run OWASP ZAP scan', 'completed' => false],
                ['id' => '2', 'text' => 'Review JWT revocation', 'completed' => false],
            ],
        ]);
        $taskRes->assertStatus(201);
        $taskId = $taskRes->json('data.id');

        // Log time spent
        $timeRes = $this->postJson("/api/v1/crm/tasks/{$taskId}/log-time", [
            'duration_minutes' => 180,
            'description' => 'Discovered authorization bypass on internal webhooks and drafted fix.',
            'is_billable' => true,
        ]);
        $timeRes->assertStatus(201);
        $timeRes->assertJsonPath('data.duration_minutes', 180);

        // Fetch logs
        $logsRes = $this->getJson("/api/v1/crm/tasks/{$taskId}/time-logs");
        $logsRes->assertStatus(200);
        $this->assertCount(1, $logsRes->json('data'));
    }

    public function test_cannot_complete_task_with_unresolved_dependencies(): void
    {
        Sanctum::actingAs($this->managerUser);

        $taskA = Task::create([
            'tenant_id' => $this->tenantA->id,
            'title' => 'Database Schema Migration',
            'status' => 'in_progress',
        ]);

        $taskB = Task::create([
            'tenant_id' => $this->tenantA->id,
            'title' => 'Deploy Production Release',
            'status' => 'todo',
        ]);

        // Task B depends on Task A
        $depRes = $this->postJson("/api/v1/crm/tasks/{$taskB->id}/dependencies", [
            'depends_on_task_id' => $taskA->id,
        ]);
        $depRes->assertStatus(201);

        // Attempt to complete Task B while Task A is still in_progress
        $attemptRes = $this->postJson("/api/v1/crm/tasks/{$taskB->id}/complete");
        $attemptRes->assertStatus(422);
        $attemptRes->assertJsonValidationErrors(['status']);

        // Now complete Task A
        $completeARes = $this->postJson("/api/v1/crm/tasks/{$taskA->id}/complete");
        $completeARes->assertStatus(200);

        // Now Task B can be completed
        $completeBRes = $this->postJson("/api/v1/crm/tasks/{$taskB->id}/complete");
        $completeBRes->assertStatus(200);
        $this->assertTrue(in_array($taskB->fresh()->status, ['done', 'completed']));
    }

    public function test_detects_and_prevents_circular_task_dependencies(): void
    {
        Sanctum::actingAs($this->managerUser);

        $task1 = Task::create(['tenant_id' => $this->tenantA->id, 'title' => 'Step 1: Planning']);
        $task2 = Task::create(['tenant_id' => $this->tenantA->id, 'title' => 'Step 2: Coding']);
        $task3 = Task::create(['tenant_id' => $this->tenantA->id, 'title' => 'Step 3: Verification']);

        // 2 depends on 1
        $this->postJson("/api/v1/crm/tasks/{$task2->id}/dependencies", ['depends_on_task_id' => $task1->id])->assertStatus(201);
        // 3 depends on 2
        $this->postJson("/api/v1/crm/tasks/{$task3->id}/dependencies", ['depends_on_task_id' => $task2->id])->assertStatus(201);

        // Now attempt to make 1 depend on 3 -> cycle!
        $cycleRes = $this->postJson("/api/v1/crm/tasks/{$task1->id}/dependencies", ['depends_on_task_id' => $task3->id]);
        $cycleRes->assertStatus(422);
        $cycleRes->assertJsonValidationErrors(['depends_on_task_id']);
    }

    public function test_can_schedule_meeting_with_attendees_and_update_rsvp(): void
    {
        Sanctum::actingAs($this->managerUser);

        $payload = [
            'title' => 'Q4 Enterprise Roadmap Review',
            'description' => 'Review quarterly delivery milestones and team bandwidth.',
            'agenda' => "1. Architecture review\n2. Security compliance\n3. Q&A",
            'meeting_date' => Carbon::today()->addDays(5)->toDateString(),
            'start_time' => '14:30',
            'end_time' => '15:30',
            'timezone' => 'Asia/Kolkata',
            'location_type' => 'online',
            'meet_link' => 'https://meet.google.com/xyz-abcd-efg',
            'attendees' => [
                ['user_id' => $this->memberUser->id, 'attendee_name' => 'Sarah Dev', 'attendee_email' => 'sarah@acme.test'],
                ['attendee_name' => 'Client Stakeholder', 'attendee_email' => 'client@enterprise.com'],
            ],
        ];

        $res = $this->postJson('/api/v1/crm/meetings', $payload);
        $res->assertStatus(201);
        $res->assertJsonPath('data.title', 'Q4 Enterprise Roadmap Review');

        $meetingId = $res->json('data.id');
        $meeting = Meeting::with('attendees')->findOrFail($meetingId);
        $this->assertCount(2, $meeting->attendees);

        // Update RSVP
        $rsvpRes = $this->postJson("/api/v1/crm/meetings/{$meetingId}/rsvp", [
            'attendee_identifier' => 'client@enterprise.com',
            'status' => 'accepted',
            'notes' => 'Will join with our technical lead.',
        ]);
        $rsvpRes->assertStatus(200);
        $rsvpRes->assertJsonPath('data.status', 'accepted');
    }

    public function test_can_reschedule_meeting_preserving_history_and_attendees(): void
    {
        Sanctum::actingAs($this->managerUser);

        $meeting = Meeting::create([
            'tenant_id' => $this->tenantA->id,
            'organizer_id' => $this->managerUser->id,
            'title' => 'Sprint Retrospective',
            'meeting_date' => Carbon::today()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'scheduled',
        ]);

        MeetingAttendee::create([
            'meeting_id' => $meeting->id,
            'user_id' => $this->memberUser->id,
            'attendee_name' => 'Sarah Dev',
            'attendee_email' => 'sarah@acme.test',
            'status' => 'accepted',
        ]);

        $reschedulePayload = [
            'meeting_date' => Carbon::today()->addDays(2)->toDateString(),
            'start_time' => '11:00',
            'end_time' => '12:00',
            'agenda' => 'Rescheduled due to production release deployment.',
        ];

        $res = $this->postJson("/api/v1/crm/meetings/{$meeting->id}/reschedule", $reschedulePayload);
        $res->assertStatus(201);

        $this->assertEquals('rescheduled', $meeting->fresh()->status);

        $newMeetingId = $res->json('data.id');
        $newMeeting = Meeting::with('attendees')->findOrFail($newMeetingId);

        $this->assertEquals($meeting->id, $newMeeting->rescheduled_from_meeting_id);
        $this->assertEquals('scheduled', $newMeeting->status);
        $this->assertCount(1, $newMeeting->attendees);
    }

    public function test_can_create_follow_up_task_from_meeting(): void
    {
        Sanctum::actingAs($this->managerUser);

        $project = Project::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Client Integration',
            'status' => 'in_progress',
        ]);

        $meeting = Meeting::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'organizer_id' => $this->managerUser->id,
            'title' => 'Weekly Sync with Enterprise Client',
            'meeting_date' => Carbon::today()->toDateString(),
            'start_time' => '15:00',
            'status' => 'completed',
            'outcome' => 'Client requested custom SSO SAML configuration by Friday.',
        ]);

        $res = $this->postJson("/api/v1/crm/meetings/{$meeting->id}/follow-up-task", [
            'title' => 'Implement SAML 2.0 Identity Provider Config',
            'assigned_user_id' => $this->memberUser->id,
            'priority' => 'high',
        ]);

        $res->assertStatus(201);
        $res->assertJsonPath('data.title', 'Implement SAML 2.0 Identity Provider Config');
        $res->assertJsonPath('data.project_id', $project->id);
    }

    public function test_tenant_isolation_for_projects_tasks_and_meetings(): void
    {
        // Tenant A items
        $projectA = Project::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Acme Internal Redesign',
            'status' => 'in_progress',
        ]);

        $taskA = Task::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
            'title' => 'Acme Task Alpha',
            'status' => 'todo',
        ]);

        $meetingA = Meeting::create([
            'tenant_id' => $this->tenantA->id,
            'title' => 'Acme Secret Strategy Meeting',
            'meeting_date' => Carbon::today()->toDateString(),
            'start_time' => '09:00',
            'status' => 'scheduled',
        ]);

        // Login as Tenant B user
        Sanctum::actingAs($this->tenantBUser);

        // Attempt to access Project A -> 404
        $this->getJson("/api/v1/crm/projects/{$projectA->id}")->assertStatus(404);

        // Attempt to access Task A -> 404
        $this->getJson("/api/v1/crm/tasks/{$taskA->id}")->assertStatus(404);

        // Attempt to access Meeting A -> 404
        $this->getJson("/api/v1/crm/meetings/{$meetingA->id}")->assertStatus(404);

        // Listing projects should be empty for Tenant B
        $projectsList = $this->getJson('/api/v1/crm/projects');
        $projectsList->assertStatus(200);
        $this->assertEmpty($projectsList->json('data'));
    }
}
