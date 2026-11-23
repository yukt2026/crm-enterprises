<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Projects\Models\Meeting;
use App\Domains\Projects\Services\MeetingService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected MeetingService $meetingService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $query = Meeting::where('tenant_id', $tenantId)
            ->with(['organizer', 'attendees', 'lead', 'contact', 'company', 'deal', 'project']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->where('meeting_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('meeting_date', '<=', $request->date_to);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }

        if ($request->filled('deal_id')) {
            $query->where('deal_id', $request->deal_id);
        }

        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->q . '%')
                  ->orWhere('description', 'like', '%' . $request->q . '%')
                  ->orWhere('agenda', 'like', '%' . $request->q . '%');
            });
        }

        $view = $request->get('view', 'all');
        if ($view === 'upcoming') {
            $query->upcoming();
        } else {
            $query->orderByDesc('meeting_date')->orderByDesc('start_time');
        }

        $perPage = (int) $request->get('limit', $request->get('per_page', 20));
        return $this->paginatedResponse($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'agenda' => 'nullable|string',
            'meeting_date' => 'required|date',
            'start_time' => 'required|string',
            'end_time' => 'nullable|string',
            'timezone' => 'nullable|string|max:60',
            'location_type' => 'nullable|string|in:online,physical',
            'location_address' => 'nullable|string|max:255',
            'meet_link' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'lead_id' => 'nullable|exists:leads,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'company_id' => 'nullable|exists:companies,id',
            'deal_id' => 'nullable|exists:deals,id',
            'project_id' => 'nullable|exists:projects,id',
            'attendees' => 'nullable|array',
        ]);

        $meeting = $this->meetingService->scheduleMeeting($validated, $tenantId, auth()->id());

        return $this->successResponse($meeting, 'Meeting scheduled successfully.', 201);
    }

    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $meeting = Meeting::where('tenant_id', $tenantId)
            ->with(['organizer', 'attendees.user', 'attendees.contact', 'lead', 'contact', 'company', 'deal', 'project', 'rescheduledFrom'])
            ->findOrFail($id);

        return $this->successResponse($meeting);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $meeting = Meeting::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'agenda' => 'nullable|string',
            'meeting_date' => 'sometimes|date',
            'start_time' => 'sometimes|string',
            'end_time' => 'nullable|string',
            'timezone' => 'nullable|string|max:60',
            'location_type' => 'nullable|string|in:online,physical',
            'location_address' => 'nullable|string|max:255',
            'meet_link' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'outcome' => 'nullable|string',
            'status' => 'sometimes|string|in:scheduled,in_progress,completed,cancelled,rescheduled',
            'attendees' => 'nullable|array',
        ]);

        $meeting = $this->meetingService->updateMeeting($meeting, $validated);

        return $this->successResponse($meeting, 'Meeting updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $meeting = Meeting::where('tenant_id', $tenantId)->findOrFail($id);
        $meeting->delete();

        return $this->successResponse(null, 'Meeting deleted successfully.');
    }

    public function reschedule(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $meeting = Meeting::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'meeting_date' => 'required|date',
            'start_time' => 'required|string',
            'end_time' => 'nullable|string',
            'agenda' => 'nullable|string',
            'meet_link' => 'nullable|string',
            'location_type' => 'nullable|string|in:online,physical',
            'location_address' => 'nullable|string',
        ]);

        $newMeeting = $this->meetingService->rescheduleMeeting($meeting, $validated);

        return $this->successResponse($newMeeting, 'Meeting rescheduled successfully.', 201);
    }

    public function updateRsvp(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $meeting = Meeting::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'attendee_identifier' => 'required', // ID or email
            'status' => 'required|string|in:accepted,declined,tentative,pending',
            'notes' => 'nullable|string',
        ]);

        $attendee = $this->meetingService->updateAttendeeRsvp(
            $meeting->id,
            $validated['attendee_identifier'],
            $validated['status'],
            $validated['notes'] ?? null
        );

        return $this->successResponse($attendee, 'RSVP updated.');
    }

    public function createFollowUpTask(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $meeting = Meeting::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'assigned_user_id' => 'nullable|exists:users,id',
            'priority' => 'nullable|in:low,medium,high,urgent',
        ]);

        $task = $this->meetingService->createFollowUpTask($meeting, $validated);

        return $this->successResponse($task, 'Follow-up task created successfully.', 201);
    }

    public function calendar(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $startDate = $request->get('start_date', Carbon::today()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', Carbon::today()->endOfMonth()->toDateString());
        $userId = $request->boolean('my_calendar') ? auth()->id() : null;

        $events = $this->meetingService->getCalendarEvents($tenantId, $startDate, $endDate, $userId);

        return $this->successResponse($events);
    }
}
