<?php

namespace App\Domains\Support\Services;

use App\Domains\CRM\Models\CrmActivity;
use App\Domains\Support\Models\CustomerFeedback;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Models\SupportTicketMessage;
use App\Domains\Support\Models\SupportTicketTransition;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketService
{
    public function __construct(
        protected SlaService $slaService,
        protected SupportAiService $supportAiService
    ) {}

    /**
     * Create a new support ticket with SLA targets and optional AI assistance
     */
    public function createTicket(array $data, int $tenantId, ?int $creatorId = null): SupportTicket
    {
        $priority = $data['priority'] ?? 'medium';
        $slaTargets = $this->slaService->calculateSlaTarget(
            $tenantId,
            $priority,
            $data['sla_policy_id'] ?? null
        );

        $ticketNumber = $this->generateTicketNumber($tenantId);

        $ticket = SupportTicket::create([
            'tenant_id' => $tenantId,
            'ticket_number' => $ticketNumber,
            'customer_id' => $data['customer_id'] ?? null,
            'contact_id' => $data['contact_id'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'source_channel' => $data['source_channel'] ?? 'web_chat',
            'category_id' => $data['category_id'] ?? null,
            'subcategory' => $data['subcategory'] ?? null,
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
            'priority' => $priority,
            'status' => $data['status'] ?? 'new',
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'assigned_team_id' => $data['assigned_team_id'] ?? null,
            'lead_id' => $data['lead_id'] ?? null,
            'deal_id' => $data['deal_id'] ?? null,
            'invoice_id' => $data['invoice_id'] ?? null,
            'tags' => $data['tags'] ?? [],
            'attachments' => $data['attachments'] ?? [],
            'sla_policy_id' => $slaTargets['sla_policy_id'],
            'first_response_due_at' => $slaTargets['first_response_due_at'],
            'resolution_due_at' => $slaTargets['resolution_due_at'],
            'sla_status' => 'within_sla',
        ]);

        // Create initial message if description exists
        if (!empty($data['description'])) {
            SupportTicketMessage::create([
                'tenant_id' => $tenantId,
                'ticket_id' => $ticket->id,
                'sender_type' => 'customer',
                'sender_id' => $data['customer_id'] ?? null,
                'message' => $data['description'],
                'is_internal' => false,
                'channel' => $ticket->source_channel,
                'attachments' => $ticket->attachments,
            ]);
        }

        // Generate AI draft assistance
        try {
            $this->supportAiService->generateAiAssistance($ticket);
        } catch (\Throwable $e) {
            // Non-blocking AI fallback
        }

        // Register CRM activity if linked to customer or lead
        if ($ticket->customer_id || $ticket->lead_id) {
            try {
                CrmActivity::create([
                    'workspace_id' => $tenantId,
                    'customer_id' => $ticket->customer_id,
                    'lead_id' => $ticket->lead_id,
                    'deal_id' => $ticket->deal_id,
                    'activity_type' => 'support_ticket',
                    'title' => "Support Ticket Opened: {$ticket->ticket_number} - {$ticket->subject}",
                    'description' => $ticket->description ?? '',
                ]);
            } catch (\Throwable $e) {}
        }

        return $ticket->fresh(['customer', 'contact', 'company', 'assignedUser', 'category', 'slaPolicy', 'messages']);
    }

    /**
     * Update an existing ticket with transition logging
     */
    public function updateTicket(SupportTicket $ticket, array $data, ?int $userId = null): SupportTicket
    {
        $oldStatus = $ticket->status;
        $newStatus = $data['status'] ?? $oldStatus;

        $ticket->update($data);

        if ($oldStatus !== $newStatus) {
            SupportTicketTransition::create([
                'tenant_id' => $ticket->tenant_id,
                'ticket_id' => $ticket->id,
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'user_id' => $userId,
                'reason' => $data['status_reason'] ?? "Status updated from {$oldStatus} to {$newStatus}.",
            ]);
        }

        $this->slaService->evaluateAndEscalateTicket($ticket);

        return $ticket->fresh(['customer', 'contact', 'company', 'assignedUser', 'category', 'slaPolicy', 'messages', 'transitions']);
    }

    /**
     * Assign ticket to an agent or team
     */
    public function assignTicket(SupportTicket $ticket, int $userId, ?int $teamId = null): SupportTicket
    {
        $updates = [
            'assigned_user_id' => $userId,
            'assigned_team_id' => $teamId ?? $ticket->assigned_team_id,
        ];

        if ($ticket->status === 'new') {
            $updates['status'] = 'open';
        }

        $ticket->update($updates);

        // Internal audit note
        SupportTicketMessage::create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'sender_type' => 'system',
            'message' => "Ticket reassigned to user ID #{$userId}.",
            'is_internal' => true,
            'channel' => 'internal',
        ]);

        return $ticket->fresh(['customer', 'contact', 'company', 'assignedUser', 'category', 'slaPolicy', 'messages']);
    }

    /**
     * Add a message (internal note or public reply) to the ticket thread
     */
    public function addMessage(SupportTicket $ticket, array $data, int $tenantId, ?int $userId = null): SupportTicketMessage
    {
        $isInternal = (bool) ($data['is_internal'] ?? false);
        $isDraft = (bool) ($data['is_draft'] ?? false);

        $message = SupportTicketMessage::create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'sender_type' => $data['sender_type'] ?? ($isInternal ? 'user' : 'user'),
            'sender_id' => $data['sender_id'] ?? $userId,
            'message' => $data['message'],
            'is_internal' => $isInternal,
            'channel' => $data['channel'] ?? ($isInternal ? 'internal' : ($ticket->source_channel ?? 'web_chat')),
            'attachments' => $data['attachments'] ?? null,
            'is_ai_generated' => (bool) ($data['is_ai_generated'] ?? false),
            'is_draft' => $isDraft,
        ]);

        // If public reply from agent (not draft and not internal), record first response if not already done
        if (!$isInternal && !$isDraft && in_array($message->sender_type, ['user', 'system'])) {
            if (!$ticket->first_responded_at) {
                $ticket->update(['first_responded_at' => Carbon::now()]);
            }

            // Update ticket status if waiting on customer
            if (($data['status'] ?? '') === 'waiting_customer' || $ticket->status === 'open') {
                $ticket->update(['status' => $data['status'] ?? 'waiting_customer']);
            }
        } elseif (!$isInternal && $message->sender_type === 'customer') {
            // When customer replies on closed/resolved ticket, auto-reopen
            if (in_array($ticket->status, ['resolved', 'closed'])) {
                $old = $ticket->status;
                $ticket->update([
                    'status' => 'reopened',
                    'reopened_at' => Carbon::now(),
                    'reopen_count' => ($ticket->reopen_count ?? 0) + 1,
                    'reopen_reason' => 'Customer reply received on completed ticket.',
                ]);
                SupportTicketTransition::create([
                    'tenant_id' => $ticket->tenant_id,
                    'ticket_id' => $ticket->id,
                    'from_status' => $old,
                    'to_status' => 'reopened',
                    'user_id' => null,
                    'reason' => 'Auto-reopened upon inbound customer message.',
                ]);
            } elseif ($ticket->status === 'waiting_customer') {
                $ticket->update(['status' => 'open']);
            }
        }

        $this->slaService->evaluateAndEscalateTicket($ticket);

        return $message;
    }

    /**
     * Resolve ticket
     */
    public function resolveTicket(SupportTicket $ticket, string $resolutionNotes, ?int $userId = null): SupportTicket
    {
        $old = $ticket->status;
        $ticket->update([
            'status' => 'resolved',
            'resolved_at' => Carbon::now(),
            'resolution_notes' => $resolutionNotes,
        ]);

        SupportTicketTransition::create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'from_status' => $old,
            'to_status' => 'resolved',
            'user_id' => $userId,
            'reason' => "Resolved: {$resolutionNotes}",
        ]);

        SupportTicketMessage::create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'sender_type' => 'user',
            'sender_id' => $userId,
            'message' => "Issue marked as resolved: {$resolutionNotes}",
            'is_internal' => false,
            'channel' => $ticket->source_channel,
        ]);

        return $ticket->fresh(['customer', 'contact', 'company', 'assignedUser', 'category', 'slaPolicy', 'messages']);
    }

    /**
     * Close ticket
     */
    public function closeTicket(SupportTicket $ticket, ?string $closureReason = null, ?int $userId = null): SupportTicket
    {
        $old = $ticket->status;
        $ticket->update([
            'status' => 'closed',
            'closed_at' => Carbon::now(),
            'closure_reason' => $closureReason ?? 'Ticket closed after resolution confirmation.',
        ]);

        SupportTicketTransition::create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'from_status' => $old,
            'to_status' => 'closed',
            'user_id' => $userId,
            'reason' => $closureReason ?? 'Ticket closed.',
        ]);

        return $ticket->fresh(['customer', 'contact', 'company', 'assignedUser', 'category', 'slaPolicy', 'messages']);
    }

    /**
     * Reopen ticket
     */
    public function reopenTicket(SupportTicket $ticket, string $reason, ?int $userId = null): SupportTicket
    {
        $old = $ticket->status;
        $ticket->update([
            'status' => 'reopened',
            'reopened_at' => Carbon::now(),
            'reopen_count' => ($ticket->reopen_count ?? 0) + 1,
            'reopen_reason' => $reason,
        ]);

        SupportTicketTransition::create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'from_status' => $old,
            'to_status' => 'reopened',
            'user_id' => $userId,
            'reason' => "Reopened: {$reason}",
        ]);

        SupportTicketMessage::create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'sender_type' => 'customer',
            'sender_id' => $userId,
            'message' => "Ticket reopened. Reason: {$reason}",
            'is_internal' => false,
            'channel' => $ticket->source_channel,
        ]);

        return $ticket->fresh(['customer', 'contact', 'company', 'assignedUser', 'category', 'slaPolicy', 'messages']);
    }

    /**
     * Record customer satisfaction (CSAT) rating and feedback
     */
    public function submitCsat(SupportTicket $ticket, int $rating, ?string $comment = null, ?int $nps = null): SupportTicket
    {
        if ($rating < 1 || $rating > 5) {
            throw ValidationException::withMessages([
                'rating' => ['CSAT rating must be between 1 and 5.'],
            ]);
        }

        $ticket->update([
            'csat_rating' => $rating,
            'csat_comment' => $comment,
        ]);

        // Record customer feedback
        CustomerFeedback::create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'customer_id' => $ticket->customer_id ?? 1,
            'csat_score' => $rating,
            'nps_score' => $nps ?? ($rating * 2), // Default 1-5 scale mapped to 0-10 if not provided
            'rating_category' => 'support_resolution',
            'feedback_text' => $comment,
            'channel' => $ticket->source_channel ?: 'portal',
            'submitted_at' => now(),
        ]);

        return $ticket;
    }

    /**
     * Perform bulk operations on tickets (status, assignment, priority).
     */
    public function bulkUpdateTickets(int $tenantId, array $ticketIds, array $updates, ?int $userId = null): int
    {
        $tickets = SupportTicket::where('tenant_id', $tenantId)->whereIn('id', $ticketIds)->get();
        $count = 0;

        foreach ($tickets as $t) {
            $this->updateTicket($t, $updates, $userId);
            $count++;
        }

        return $count;
    }

    /**
     * Get aggregate helpdesk analytics
     */
    public function getSupportAnalytics(int $tenantId): array
    {
        $base = SupportTicket::where('tenant_id', $tenantId);

        $total = (clone $base)->count();
        $open = (clone $base)->whereIn('status', ['new', 'open', 'in_progress', 'waiting_customer', 'waiting_internal'])->count();
        $resolved = (clone $base)->where('status', 'resolved')->count();
        $closed = (clone $base)->where('status', 'closed')->count();
        $breached = (clone $base)->where('sla_status', 'breached')->count();
        $warning = (clone $base)->where('sla_status', 'warning')->count();

        // Calculate average first response time in minutes
        $respondedTickets = (clone $base)->whereNotNull('first_responded_at')->get();
        $avgResponseMinutes = 0;
        if ($respondedTickets->isNotEmpty()) {
            $totalMinutes = $respondedTickets->reduce(function ($carry, $t) {
                return $carry + abs($t->created_at->diffInMinutes($t->first_responded_at));
            }, 0);
            $avgResponseMinutes = (int) round($totalMinutes / $respondedTickets->count());
        }

        // Calculate average resolution time in minutes
        $resolvedTickets = (clone $base)->whereNotNull('resolved_at')->get();
        $avgResolutionMinutes = 0;
        if ($resolvedTickets->isNotEmpty()) {
            $totalResMinutes = $resolvedTickets->reduce(function ($carry, $t) {
                return $carry + abs($t->created_at->diffInMinutes($t->resolved_at));
            }, 0);
            $avgResolutionMinutes = (int) round($totalResMinutes / $resolvedTickets->count());
        }

        // Reopen rate %
        $reopenedCount = (clone $base)->where('reopen_count', '>', 0)->count();
        $reopenRate = $total > 0 ? round(($reopenedCount / $total) * 100, 1) : 0;

        // CSAT average
        $csatAvg = (clone $base)->whereNotNull('csat_rating')->avg('csat_rating') ?? 0;

        // SLA compliance rate %
        $slaMetCount = (clone $base)->where('sla_status', 'within_sla')->count();
        $slaComplianceRate = $total > 0 ? round(($slaMetCount / $total) * 100, 1) : 100;

        // By priority
        $byPriority = [
            'urgent' => (clone $base)->where('priority', 'urgent')->count(),
            'high' => (clone $base)->where('priority', 'high')->count(),
            'medium' => (clone $base)->where('priority', 'medium')->count(),
            'low' => (clone $base)->where('priority', 'low')->count(),
        ];

        // By source channel
        $byChannel = [
            'web_chat' => (clone $base)->where('source_channel', 'web_chat')->count(),
            'whatsapp' => (clone $base)->where('source_channel', 'whatsapp')->count(),
            'email' => (clone $base)->where('source_channel', 'email')->count(),
            'phone' => (clone $base)->where('source_channel', 'phone')->count(),
            'portal' => (clone $base)->where('source_channel', 'portal')->count(),
        ];

        return [
            'total_tickets' => $total,
            'open_tickets' => $open,
            'resolved_tickets' => $resolved,
            'closed_tickets' => $closed,
            'breached_tickets' => $breached,
            'warning_tickets' => $warning,
            'avg_response_minutes' => $avgResponseMinutes,
            'avg_resolution_minutes' => $avgResolutionMinutes,
            'reopen_rate' => $reopenRate,
            'csat_average' => round((float) $csatAvg, 1),
            'sla_compliance_rate' => $slaComplianceRate,
            'by_priority' => $byPriority,
            'by_channel' => $byChannel,
        ];
    }

    /**
     * Generate unique readable ticket number per tenant
     */
    protected function generateTicketNumber(int $tenantId): string
    {
        $datePrefix = Carbon::now()->format('Ymd');
        $count = SupportTicket::where('tenant_id', $tenantId)
            ->whereDate('created_at', Carbon::today())
            ->count() + 1;

        $sequence = str_pad($count, 4, '0', STR_PAD_LEFT);
        return "TICK-{$datePrefix}-{$sequence}";
    }
}
