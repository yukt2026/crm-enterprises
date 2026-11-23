<?php

namespace App\Domains\WhatsApp\Services;

use App\Domains\AuditSecurity\Services\AuditLogger;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Domains\WhatsApp\Models\WhatsAppConversationNote;
use App\Domains\WhatsApp\Models\WhatsAppMessage;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WhatsAppInboxService
{
    /**
     * List conversations with team inbox filtering
     */
    public function listConversations(int $tenantId, array $filters = [], ?User $user = null, int $perPage = 25): LengthAwarePaginator
    {
        $query = WhatsAppConversation::query()
            ->where('tenant_id', $tenantId)
            ->with(['phoneNumber', 'contact', 'lead', 'customer', 'assignedUser'])
            ->orderBy('last_message_at', 'desc');

        // Team filter scopes
        $scope = $filters['scope'] ?? 'all';
        if ($scope === 'assigned_to_me' && $user) {
            $query->where('assigned_user_id', $user->id);
        } elseif ($scope === 'unassigned') {
            $query->whereNull('assigned_user_id');
        }

        // Status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Priority filter
        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        // Search by phone or name
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('phone_number', 'like', $search)
                  ->orWhere('customer_name', 'like', $search)
                  ->orWhere('last_message_preview', 'like', $search);
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Get full conversation thread and mark read
     */
    public function getConversationThread(int $tenantId, int $conversationId, ?User $user = null): WhatsAppConversation
    {
        $conversation = WhatsAppConversation::where('tenant_id', $tenantId)
            ->with([
                'phoneNumber.account',
                'contact',
                'lead',
                'customer.deals',
                'assignedUser',
                'messages.senderUser',
                'notes.author',
            ])
            ->findOrFail($conversationId);

        // Mark unread messages as read
        if ($conversation->unread_count > 0) {
            $conversation->unread_count = 0;
            $conversation->save();
        }

        return $conversation;
    }

    /**
     * Assign / reassign conversation
     */
    public function assignConversation(int $tenantId, int $conversationId, ?int $assignedUserId, ?User $actor = null): WhatsAppConversation
    {
        $conversation = WhatsAppConversation::where('tenant_id', $tenantId)->findOrFail($conversationId);

        $oldAssigned = $conversation->assigned_user_id;
        $conversation->assigned_user_id = $assignedUserId;
        $conversation->save();

        AuditLogger::log('whatsapp_conversation_assigned', $conversation, ['assigned_user_id' => $oldAssigned], ['assigned_user_id' => $assignedUserId], $actor);

        return $conversation->load('assignedUser');
    }

    /**
     * Update conversation status (open, pending, resolved)
     */
    public function updateStatus(int $tenantId, int $conversationId, string $status, ?User $actor = null): WhatsAppConversation
    {
        $conversation = WhatsAppConversation::where('tenant_id', $tenantId)->findOrFail($conversationId);

        $oldStatus = $conversation->status;
        $conversation->status = $status;
        $conversation->save();

        AuditLogger::log('whatsapp_conversation_status_updated', $conversation, ['status' => $oldStatus], ['status' => $status], $actor);

        return $conversation;
    }

    /**
     * Add internal team note (hidden from customer)
     */
    public function addNote(int $tenantId, int $conversationId, string $content, ?User $user = null): WhatsAppConversationNote
    {
        $conversation = WhatsAppConversation::where('tenant_id', $tenantId)->findOrFail($conversationId);

        if (!$user) {
            throw ValidationException::withMessages(['user' => 'Authenticated user required to add notes.']);
        }

        $note = WhatsAppConversationNote::create([
            'tenant_id' => $tenantId,
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'content' => $content,
        ]);

        AuditLogger::log('whatsapp_note_added', $note, null, ['conversation_id' => $conversationId], $user);

        return $note->load('author');
    }

    /**
     * Toggle AI / Human Takeover mode
     */
    public function toggleAiHandover(int $tenantId, int $conversationId, bool $isAiHandled, ?User $actor = null): WhatsAppConversation
    {
        $conversation = WhatsAppConversation::where('tenant_id', $tenantId)->findOrFail($conversationId);

        $conversation->is_ai_handled = $isAiHandled;
        $conversation->save();

        AuditLogger::log('whatsapp_ai_handover_toggled', $conversation, ['is_ai_handled' => !$isAiHandled], ['is_ai_handled' => $isAiHandled], $actor);

        return $conversation;
    }

    /**
     * Create Lead directly from WhatsApp conversation
     */
    public function createLeadFromConversation(int $tenantId, int $conversationId, array $data, ?User $actor = null): Lead
    {
        $conversation = WhatsAppConversation::where('tenant_id', $tenantId)->findOrFail($conversationId);

        $name = $data['name'] ?? ($conversation->customer_name ?? 'WhatsApp Lead');
        $cleanPhone = $conversation->phone_number;

        $lead = Lead::create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'phone' => $cleanPhone,
            'email' => $data['email'] ?? null,
            'company' => $data['company'] ?? null,
            'source' => 'whatsapp',
            'status' => 'new',
            'assigned_user_id' => $conversation->assigned_user_id ?? $actor?->id,
            'notes' => "Created from WhatsApp conversation: {$conversation->last_message_preview}",
        ]);

        $conversation->lead_id = $lead->id;
        $conversation->save();

        AuditLogger::log('lead_created_from_whatsapp', $lead, null, ['conversation_id' => $conversationId], $actor);

        return $lead;
    }
}
