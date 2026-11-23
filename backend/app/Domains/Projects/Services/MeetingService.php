<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Models\Meeting;
use App\Domains\Projects\Models\MeetingAttendee;
use App\Domains\Projects\Models\Task;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class MeetingService
{
    /**
     * Schedule a new meeting
     */
    public function scheduleMeeting(array $data, int $tenantId, ?int $organizerId = null): Meeting
    {
        $meeting = Meeting::create([
            'tenant_id' => $tenantId,
            'organizer_id' => $data['organizer_id'] ?? $organizerId,
            'lead_id' => $data['lead_id'] ?? null,
            'contact_id' => $data['contact_id'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'deal_id' => $data['deal_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'agenda' => $data['agenda'] ?? null,
            'meeting_date' => $data['meeting_date'],
            'start_time' => $data['start_time'] ?? '10:00',
            'end_time' => $data['end_time'] ?? null,
            'timezone' => $data['timezone'] ?? 'UTC',
            'location_type' => $data['location_type'] ?? 'online',
            'location_address' => $data['location_address'] ?? null,
            'meet_link' => $data['meet_link'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? 'scheduled',
            'rescheduled_from_meeting_id' => $data['rescheduled_from_meeting_id'] ?? null,
        ]);

        if (!empty($data['attendees'])) {
            foreach ($data['attendees'] as $att) {
                MeetingAttendee::create([
                    'meeting_id' => $meeting->id,
                    'user_id' => $att['user_id'] ?? null,
                    'contact_id' => $att['contact_id'] ?? null,
                    'attendee_name' => $att['attendee_name'] ?? ($att['name'] ?? 'Attendee'),
                    'attendee_email' => $att['attendee_email'] ?? ($att['email'] ?? null),
                    'status' => $att['status'] ?? 'pending',
                    'notes' => $att['notes'] ?? null,
                ]);
            }
        }

        return $meeting->fresh(['organizer', 'lead', 'contact', 'company', 'deal', 'project', 'attendees']);
    }

    /**
     * Update an existing meeting
     */
    public function updateMeeting(Meeting $meeting, array $data): Meeting
    {
        $meeting->update($data);

        if (isset($data['attendees']) && is_array($data['attendees'])) {
            $meeting->attendees()->delete();
            foreach ($data['attendees'] as $att) {
                MeetingAttendee::create([
                    'meeting_id' => $meeting->id,
                    'user_id' => $att['user_id'] ?? null,
                    'contact_id' => $att['contact_id'] ?? null,
                    'attendee_name' => $att['attendee_name'] ?? ($att['name'] ?? 'Attendee'),
                    'attendee_email' => $att['attendee_email'] ?? ($att['email'] ?? null),
                    'status' => $att['status'] ?? 'pending',
                    'notes' => $att['notes'] ?? null,
                ]);
            }
        }

        return $meeting->fresh(['organizer', 'lead', 'contact', 'company', 'deal', 'project', 'attendees']);
    }

    /**
     * Reschedule meeting to a new date/time
     */
    public function rescheduleMeeting(Meeting $meeting, array $data): Meeting
    {
        $meeting->update(['status' => 'rescheduled']);

        $newMeetingData = array_merge([
            'tenant_id' => $meeting->tenant_id,
            'organizer_id' => $meeting->organizer_id,
            'lead_id' => $meeting->lead_id,
            'contact_id' => $meeting->contact_id,
            'company_id' => $meeting->company_id,
            'deal_id' => $meeting->deal_id,
            'project_id' => $meeting->project_id,
            'title' => $data['title'] ?? $meeting->title,
            'description' => $data['description'] ?? $meeting->description,
            'agenda' => $data['agenda'] ?? $meeting->agenda,
            'meeting_date' => $data['meeting_date'],
            'start_time' => $data['start_time'] ?? $meeting->start_time,
            'end_time' => $data['end_time'] ?? $meeting->end_time,
            'timezone' => $data['timezone'] ?? $meeting->timezone,
            'location_type' => $data['location_type'] ?? $meeting->location_type,
            'location_address' => $data['location_address'] ?? $meeting->location_address,
            'meet_link' => $data['meet_link'] ?? $meeting->meet_link,
            'status' => 'scheduled',
            'rescheduled_from_meeting_id' => $meeting->id,
        ], $data);

        // Copy attendees if not explicitly provided
        if (empty($newMeetingData['attendees'])) {
            $newMeetingData['attendees'] = $meeting->attendees->map(function ($att) {
                return [
                    'user_id' => $att->user_id,
                    'contact_id' => $att->contact_id,
                    'attendee_name' => $att->attendee_name,
                    'attendee_email' => $att->attendee_email,
                    'status' => 'pending',
                ];
            })->all();
        }

        return $this->scheduleMeeting($newMeetingData, $meeting->tenant_id, $meeting->organizer_id);
    }

    /**
     * Update Attendee RSVP status
     */
    public function updateAttendeeRsvp(int $meetingId, int|string $attendeeIdentifier, string $rsvpStatus, ?string $notes = null): MeetingAttendee
    {
        $query = MeetingAttendee::where('meeting_id', $meetingId);

        if (is_numeric($attendeeIdentifier)) {
            $query->where(function ($q) use ($attendeeIdentifier) {
                $q->where('id', $attendeeIdentifier)
                  ->orWhere('user_id', $attendeeIdentifier);
            });
        } else {
            $query->where('attendee_email', $attendeeIdentifier);
        }

        $attendee = $query->first();

        if (!$attendee) {
            throw ValidationException::withMessages([
                'attendee' => ['Attendee not found for this meeting.'],
            ]);
        }

        $attendee->update([
            'status' => $rsvpStatus,
            'notes' => $notes ?? $attendee->notes,
        ]);

        return $attendee;
    }

    /**
     * Create a follow-up task directly from a meeting
     */
    public function createFollowUpTask(Meeting $meeting, array $taskData): Task
    {
        $task = Task::create([
            'tenant_id' => $meeting->tenant_id,
            'project_id' => $taskData['project_id'] ?? $meeting->project_id,
            'created_by' => $taskData['created_by'] ?? $meeting->organizer_id,
            'lead_id' => $taskData['lead_id'] ?? $meeting->lead_id,
            'contact_id' => $taskData['contact_id'] ?? $meeting->contact_id,
            'company_id' => $taskData['company_id'] ?? $meeting->company_id,
            'deal_id' => $taskData['deal_id'] ?? $meeting->deal_id,
            'title' => $taskData['title'] ?? ('Follow-up: ' . $meeting->title),
            'description' => $taskData['description'] ?? ($meeting->outcome ? "Meeting outcome: {$meeting->outcome}" : "Follow-up task for meeting: {$meeting->title}"),
            'priority' => $taskData['priority'] ?? 'medium',
            'status' => $taskData['status'] ?? 'todo',
            'assigned_user_id' => $taskData['assigned_user_id'] ?? $meeting->organizer_id,
            'due_date' => $taskData['due_date'] ?? Carbon::parse($meeting->meeting_date)->addDays(2)->toDateString(),
        ]);

        return $task;
    }

    /**
     * Get meetings as calendar events
     */
    public function getCalendarEvents(int $tenantId, string $startDate, string $endDate, ?int $userId = null): array
    {
        $query = Meeting::where('tenant_id', $tenantId)
            ->whereBetween('meeting_date', [$startDate, $endDate])
            ->with(['organizer', 'attendees', 'project', 'lead', 'deal']);

        if ($userId) {
            $query->forUser($userId);
        }

        $meetings = $query->get();

        return $meetings->map(function ($meeting) {
            return [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'start' => "{$meeting->meeting_date->toDateString()}T{$meeting->start_time}:00",
                'end' => $meeting->end_time ? "{$meeting->meeting_date->toDateString()}T{$meeting->end_time}:00" : null,
                'status' => $meeting->status,
                'location_type' => $meeting->location_type,
                'meet_link' => $meeting->meet_link,
                'organizer' => $meeting->organizer ? $meeting->organizer->name : null,
                'attendees_count' => $meeting->attendees->count(),
                'project_name' => $meeting->project ? $meeting->project->name : null,
            ];
        })->all();
    }
}
