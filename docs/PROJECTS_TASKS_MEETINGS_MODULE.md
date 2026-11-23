# Projects, Tasks, Operations & Meetings Management Module

## 1. Executive Summary

The **Projects, Tasks, Operations & Meetings Management Module** delivers an enterprise-grade execution framework directly inside the CRM. It unifies project milestone delivery, task workflows with cycle-detected dependency guardrails, billable time tracking, and meeting lifecycle orchestration (with attendee RSVP tracking, meeting rescheduling audit links, and 1-click follow-up task creation).

---

## 2. Architecture & Data Model

### 2.1 Database Tables & Migrations
Created in `2026_11_23_000017_create_projects_tasks_meetings_tables.php`:

1. **`projects`**
   - `id`, `tenant_id`, `customer_id`, `company_id`, `owner_id`, `name`, `description`
   - `status` (`not_started`, `in_progress`, `on_hold`, `completed`, `cancelled`)
   - `priority` (`low`, `medium`, `high`, `urgent`)
   - `budget` (decimal: 15,2), `estimated_hours` (integer)
   - `start_date`, `end_date`, `progress` (0–100)
   - `is_archived`, `archived_at`, `completed_at`
2. **`project_milestones`**
   - `id`, `tenant_id`, `project_id`, `title`, `description`, `due_date`, `status` (`pending`, `in_progress`, `completed`), `progress` (0–100), `completed_at`
3. **`project_comments`**
   - `id`, `tenant_id`, `project_id`, `user_id`, `comment`, `attachments` (JSON)
4. **`project_members`**
   - `id`, `project_id`, `user_id`, `role` (`manager`, `member`, `viewer`)
5. **`tasks`**
   - `id`, `tenant_id`, `project_id`, `created_by`, `assigned_user_id`, `lead_id`, `contact_id`, `company_id`, `deal_id`, `ticket_id`
   - `title`, `description`, `priority` (`low`, `medium`, `high`, `urgent`), `status` (`todo`, `in_progress`, `in_review`, `completed`, `done`, `cancelled`)
   - `due_date`, `start_date`, `estimated_hours`, `reminder_at`, `is_recurring`, `recurring_rule`, `checklist_items` (JSON)
   - `completed_at`, `cancelled_at`
6. **`task_dependencies`**
   - `id`, `tenant_id`, `task_id`, `depends_on_task_id`, `dependency_type` (`finish_to_start`, `start_to_start`)
   - Composite unique key: `['task_id', 'depends_on_task_id']`
7. **`task_time_logs`**
   - `id`, `tenant_id`, `task_id`, `user_id`, `start_time`, `end_time`, `duration_minutes`, `description`, `is_billable`
8. **`meetings`**
   - `id`, `tenant_id`, `organizer_id`, `lead_id`, `contact_id`, `company_id`, `deal_id`, `project_id`
   - `title`, `description`, `agenda`, `meeting_date`, `start_time`, `end_time`, `timezone`, `location_type` (`online`, `physical`), `location_address`, `meet_link`, `notes`, `outcome`
   - `status` (`scheduled`, `in_progress`, `completed`, `cancelled`, `rescheduled`), `rescheduled_from_meeting_id`
9. **`meeting_attendees`**
   - `id`, `meeting_id`, `user_id`, `contact_id`, `attendee_name`, `attendee_email`, `status` (`pending`, `accepted`, `declined`, `tentative`), `notes`

---

## 3. Core Domain Services

### 3.1 `ProjectService`
- **Dynamic Progress Calculation**: Automatically recalculates overall project progress from child task completion ratios or milestone progress percentages.
- **Milestone & Team Member Management**: Handles milestone lifecycle (`pending` -> `in_progress` -> `completed`) and team assignment roles.
- **Project Archiving**: Supports soft archival (`archiveProject`) and restoration (`restoreProject`) with date-stamped audit metadata.
- **Metrics Aggregation**: Produces real-time project metrics (total projects, active projects, completed projects, capital allocated, average progress).

### 3.2 `TaskService`
- **Dependency Guardrail**: Prevents completing a task if any prerequisite task is not in `done` or `completed` status. Returns clear validation errors listing all blocking tasks.
- **Cycle Detection Algorithm (DFS)**: Automatically prevents circular dependencies (e.g. Task A -> Task B -> Task C -> Task A) using recursive graph traversal before saving to database.
- **Time Logging**: Captures employee hours, calculates durations from start/end times, and tracks billable indicators.
- **Task Productivity Metrics**: Computes task counts by status, priority breakdown, and aggregated hours logged.

### 3.3 `MeetingService`
- **Meeting Scheduling & Attendee Invitations**: Creates calendar meeting records and links internal users or external CRM contacts.
- **RSVP Tracking**: Allows updating attendee attendance statuses (`accepted`, `declined`, `tentative`).
- **Rescheduling Lifecycle**: Marks previous meeting as `rescheduled` and creates a new linked meeting inheriting attendees and agenda history.
- **Follow-up Task Creation**: Instantly generates an actionable task linked to the meeting's tenant, project, deal, and lead context.
- **Calendar Event Feeds**: Generates date-range ISO formatted calendar timeline events.

---

## 4. API Endpoints

### Projects:
- `GET /api/v1/crm/projects` - List projects with filters (`status`, `priority`, `archived`, `q`, etc.)
- `POST /api/v1/crm/projects` - Create project with milestones and team members
- `GET /api/v1/crm/projects/{id}` - Retrieve project details with relations
- `PUT /api/v1/crm/projects/{id}` - Update project
- `DELETE /api/v1/crm/projects/{id}` - Soft-delete project
- `POST /api/v1/crm/projects/{id}/archive` - Archive project
- `POST /api/v1/crm/projects/{id}/restore` - Restore archived project
- `GET /api/v1/crm/projects/{id}/milestones` - Get project milestones
- `POST /api/v1/crm/projects/{id}/milestones` - Add milestone
- `PUT /api/v1/crm/projects/{id}/milestones/{milestoneId}` - Update milestone
- `DELETE /api/v1/crm/projects/{id}/milestones/{milestoneId}` - Delete milestone
- `POST /api/v1/crm/projects/{id}/members` - Add team member
- `DELETE /api/v1/crm/projects/{id}/members/{userId}` - Remove team member
- `GET /api/v1/crm/projects/{id}/comments` - Get project discussion notes
- `POST /api/v1/crm/projects/{id}/comments` - Post comment
- `GET /api/v1/crm/projects/metrics` - Aggregated project metrics

### Tasks:
- `GET /api/v1/crm/tasks` - List tasks with view scoping (`my_tasks`, `today`, `upcoming`, `overdue`, `completed`)
- `POST /api/v1/crm/tasks` - Create task with optional checklist & dependencies
- `GET /api/v1/crm/tasks/{id}` - Get task details with time logs & dependencies
- `PUT /api/v1/crm/tasks/{id}` - Update task
- `DELETE /api/v1/crm/tasks/{id}` - Delete task
- `POST /api/v1/crm/tasks/{id}/complete` - Mark complete (dependency guardrail applied)
- `POST /api/v1/crm/tasks/{id}/toggle-checklist` - Quick-toggle checklist item
- `POST /api/v1/crm/tasks/{id}/log-time` - Log billable time
- `GET /api/v1/crm/tasks/{id}/time-logs` - Get task time logs
- `GET /api/v1/crm/tasks/{id}/dependencies` - Get dependencies
- `POST /api/v1/crm/tasks/{id}/dependencies` - Add dependency
- `DELETE /api/v1/crm/tasks/{id}/dependencies/{dependsOnTaskId}` - Remove dependency
- `GET /api/v1/crm/tasks/metrics` - Task productivity metrics

### Meetings:
- `GET /api/v1/crm/meetings` - List meetings with date range & status filters
- `POST /api/v1/crm/meetings` - Schedule meeting with attendees
- `GET /api/v1/crm/meetings/{id}` - Get meeting with attendee RSVPs
- `PUT /api/v1/crm/meetings/{id}` - Update meeting
- `DELETE /api/v1/crm/meetings/{id}` - Delete meeting
- `POST /api/v1/crm/meetings/{id}/reschedule` - Reschedule meeting
- `POST /api/v1/crm/meetings/{id}/rsvp` - Update attendee RSVP status
- `POST /api/v1/crm/meetings/{id}/follow-up-task` - Generate follow-up task
- `GET /api/v1/crm/meetings/calendar` - Calendar timeline events

---

## 5. Verification & Testing

### 5.1 Backend PHPUnit Feature Tests
File: `backend/tests/Feature/ProjectsTasksMeetingsManagementTest.php`
- `test_can_create_project_with_milestones_and_team_members` - Verified project creation, team roles, and initial milestone progress calculation.
- `test_project_progress_is_recalculated_when_milestones_or_tasks_update` - Verified automatic progress updates upon completing tasks.
- `test_can_archive_and_restore_projects` - Verified project archiving and active view isolation.
- `test_can_create_task_and_log_time_spent` - Verified task time logging and duration calculation.
- `test_cannot_complete_task_with_unresolved_dependencies` - Verified dependency guardrail blocks completion and permits it once dependencies resolve.
- `test_detects_and_prevents_circular_task_dependencies` - Verified cycle detection catches transitive loops.
- `test_can_schedule_meeting_with_attendees_and_update_rsvp` - Verified attendee invitations and RSVP status updates.
- `test_can_reschedule_meeting_preserving_history_and_attendees` - Verified reschedule history chain.
- `test_can_create_follow_up_task_from_meeting` - Verified CRM context inheritance on follow-up tasks.
- `test_tenant_isolation_for_projects_tasks_and_meetings` - Verified strict cross-tenant 404 access denial.

**Result**: 10 passed (53 assertions).

### 5.2 Frontend Integration Suite
File: `frontend/src/tests/apiIntegration.test.ts`
- Verified Step 32: Projects Management & Metrics
- Verified Step 33: Enhanced Tasks & Time Logs
- Verified Step 34: Meetings & Calendar Events

**Result**: 35 passed, 0 failed.

### 5.3 Production Build
- Vite production build succeeded cleanly with 0 TypeScript or bundling errors (`npm run build`).
