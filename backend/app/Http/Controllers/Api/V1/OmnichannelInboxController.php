<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Payment;
use App\Domains\Identity\Services\RbacService;
use App\Domains\Omnichannel\Models\ConversationAssignmentLog;
use App\Domains\Omnichannel\Models\CustomerCommunicationConsent;
use App\Domains\Omnichannel\Models\OmnichannelConversation;
use App\Domains\Omnichannel\Models\OmnichannelQuickReply;
use App\Domains\Omnichannel\Models\OmnichannelRoutingRule;
use App\Domains\Omnichannel\Models\OmnichannelSlaPolicy;
use App\Domains\Omnichannel\Services\OmnichannelInboxService;
use App\Domains\Omnichannel\Services\OmnichannelRoutingService;
use App\Domains\Omnichannel\Services\OmnichannelSlaService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OmnichannelInboxController extends Controller
{
    public function __construct(
        protected OmnichannelInboxService $inboxService,
        protected OmnichannelRoutingService $routingService,
        protected OmnichannelSlaService $slaService,
        protected RbacService $rbacService
    ) {}

    protected function getTenantId(Request $request): int
    {
        return $request->user()?->tenant_id ?? (TenantContext::getTenantId() ?? 1);
    }

    /**
     * List all conversations with multi-channel, SLA, priority and team filters.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $userId = $request->user()?->id;

        $query = OmnichannelConversation::where('tenant_id', $tenantId)
            ->with([
                'assignedUser:id,name,email',
                'assignedTeam:id,name',
                'lead:id,name,company_name,status',
                'contact:id,name,email,phone',
                'customer:id,name,email,phone',
                'lockedBy:id,name,email',
                'branch:id,name',
                'department:id,name',
                'slaPolicy:id,name'
            ])
            ->latest('last_message_at');

        // Channel filter (whatsapp, facebook, instagram, email, sms, webchat, call, telegram)
        if ($request->filled('channel') && $request->channel !== 'all') {
            $query->where('channel', $request->channel);
        }

        // Status filter (open, pending, resolved)
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Priority filter
        if ($request->filled('priority') && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        // SLA status filter (on_track, warning, breached)
        if ($request->filled('sla_status') && $request->sla_status !== 'all') {
            $query->where('sla_status', $request->sla_status);
        }

        // Ownership / Assignment / Custom filter
        $filter = $request->get('filter', 'all');
        if ($filter === 'my' && $userId) {
            $query->where('assigned_user_id', $userId);
        } elseif ($filter === 'unassigned') {
            $query->whereNull('assigned_user_id');
        } elseif ($filter === 'team') {
            $query->whereNotNull('assigned_team_id');
        } elseif ($filter === 'high_priority') {
            $query->whereIn('priority', ['high', 'urgent']);
        } elseif ($filter === 'unread') {
            $query->where('unread_count', '>', 0);
        }

        // Archival filter: default active unless requesting archived
        if ($filter === 'archived' || $request->boolean('is_archived')) {
            $query->where('is_archived', true);
        } else {
            $query->where('is_archived', false);
        }

        // Branch & Department filters
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        // AI vs Human filter
        if ($request->has('is_ai_handled') && $request->is_ai_handled !== '') {
            $query->where('is_ai_handled', filter_var($request->is_ai_handled, FILTER_VALIDATE_BOOLEAN));
        }

        // Search query
        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('customer_name', 'like', "%{$s}%")
                    ->orWhere('customer_email', 'like', "%{$s}%")
                    ->orWhere('phone_number', 'like', "%{$s}%")
                    ->orWhere('subject', 'like', "%{$s}%")
                    ->orWhere('last_message_preview', 'like', "%{$s}%");
            });
        }

        $perPage = (int) $request->get('per_page', 25);
        $conversations = $query->paginate($perPage);

        // Aggregate real counts across all 8+ channels & assignment scopes
        $channelCounts = [
            'all' => OmnichannelConversation::where('tenant_id', $tenantId)->where('is_archived', false)->where('status', 'open')->count(),
            'whatsapp' => OmnichannelConversation::where('tenant_id', $tenantId)->where('is_archived', false)->where('channel', 'whatsapp')->where('status', 'open')->count(),
            'facebook' => OmnichannelConversation::where('tenant_id', $tenantId)->where('is_archived', false)->where('channel', 'facebook')->where('status', 'open')->count(),
            'instagram' => OmnichannelConversation::where('tenant_id', $tenantId)->where('is_archived', false)->where('channel', 'instagram')->where('status', 'open')->count(),
            'telegram' => OmnichannelConversation::where('tenant_id', $tenantId)->where('is_archived', false)->where('channel', 'telegram')->where('status', 'open')->count(),
            'email' => OmnichannelConversation::where('tenant_id', $tenantId)->where('is_archived', false)->where('channel', 'email')->where('status', 'open')->count(),
            'sms' => OmnichannelConversation::where('tenant_id', $tenantId)->where('is_archived', false)->where('channel', 'sms')->where('status', 'open')->count(),
            'call' => OmnichannelConversation::where('tenant_id', $tenantId)->where('is_archived', false)->where('channel', 'call')->where('status', 'open')->count(),
            'webchat' => OmnichannelConversation::where('tenant_id', $tenantId)->where('is_archived', false)->where('channel', 'webchat')->where('status', 'open')->count(),
            'unassigned' => OmnichannelConversation::where('tenant_id', $tenantId)->where('is_archived', false)->whereNull('assigned_user_id')->where('status', 'open')->count(),
            'my' => $userId ? OmnichannelConversation::where('tenant_id', $tenantId)->where('is_archived', false)->where('assigned_user_id', $userId)->where('status', 'open')->count() : 0,
            'unread' => OmnichannelConversation::where('tenant_id', $tenantId)->where('is_archived', false)->where('unread_count', '>', 0)->count(),
            'high_priority' => OmnichannelConversation::where('tenant_id', $tenantId)->where('is_archived', false)->whereIn('priority', ['high', 'urgent'])->count(),
            'resolved' => OmnichannelConversation::where('tenant_id', $tenantId)->where('status', 'resolved')->count(),
            'archived' => OmnichannelConversation::where('tenant_id', $tenantId)->where('is_archived', true)->count(),
            'breached' => OmnichannelConversation::where('tenant_id', $tenantId)->where('is_archived', false)->where('sla_status', 'breached')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $conversations->items(),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
            'stats' => $channelCounts,
        ]);
    }

    /**
     * Show full conversation thread with messages, internal notes, assignment history, and financial access gating.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);

        $conversation = OmnichannelConversation::where('tenant_id', $tenantId)
            ->with([
                'assignedUser:id,name,email',
                'assignedTeam:id,name',
                'lead',
                'contact',
                'customer',
                'branch:id,name',
                'department:id,name',
                'lockedBy:id,name,email',
                'slaPolicy:id,name',
                'messages.senderUser:id,name',
                'notes.author:id,name',
                'assignmentLogs.assignedBy:id,name',
                'assignmentLogs.assignedTo:id,name',
            ])
            ->findOrFail($id);

        // Strict Financial Permission Check for Right Panel Pending Invoices / Payments
        $user = $request->user();
        $canViewFinancials = $user && (
            in_array($user->role, ['Business Owner', 'Business Admin', 'Super Admin', 'Accountant', 'Platform Super Admin', 'org_owner', 'org_admin', 'super_admin'])
            || $this->rbacService->can($user, 'invoices', 'view')
        );

        $financials = null;
        if ($canViewFinancials) {
            $customerId = $conversation->customer_id;
            $contactId = $conversation->contact_id;
            $invoices = Invoice::where('tenant_id', $tenantId)
                ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
                ->when(!$customerId && $contactId, fn($q) => $q->where('contact_id', $contactId))
                ->whereIn('status', ['unpaid', 'partially_paid', 'overdue'])
                ->latest()
                ->take(5)
                ->get(['id', 'invoice_number', 'total_amount', 'outstanding_amount as balance_due', 'status', 'due_date']);

            $financials = [
                'has_permission' => true,
                'pending_invoices_count' => $invoices->count(),
                'total_balance_due' => (float) $invoices->sum('balance_due'),
                'invoices' => $invoices,
            ];
        } else {
            $financials = [
                'has_permission' => false,
                'message' => 'Restricted: Financial permissions required to view invoices and payments.',
            ];
        }

        $activePresences = $this->inboxService->getConversationPresence($tenantId, $id, $user?->id);

        return response()->json([
            'success' => true,
            'data' => $conversation,
            'financials' => $financials,
            'active_presences' => $activePresences,
        ]);
    }

    /**
     * Dispatch an outbound message on the conversation's channel with consent check.
     */
    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $conversation = OmnichannelConversation::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'content' => 'required|string',
            'subject' => 'nullable|string|max:255',
            'to' => 'nullable|string',
            'attachments' => 'nullable|array',
            'media_url' => 'nullable|string',
            'dlt_template_id' => 'nullable|string',
            'tag' => 'nullable|string',
            'message_tag' => 'nullable|string',
            'direction' => 'nullable|string|in:inbound,outbound',
            'sender_actor' => 'nullable|string|in:customer,human,ai,automation,system',
        ]);

        if (empty($validated['tag']) && !empty($validated['message_tag'])) {
            $validated['tag'] = $validated['message_tag'];
        }

        $validated['user_id'] = $request->user()?->id;

        try {
            $isCustomerInbound = ($validated['direction'] ?? '') === 'inbound' || ($validated['sender_actor'] ?? '') === 'customer';
            $message = $isCustomerInbound
                ? $this->inboxService->recordInboundMessage($conversation, $validated)
                : $this->inboxService->sendMessage($conversation, $validated);

            return response()->json([
                'success' => true,
                'data' => $message->load('senderUser:id,name'),
                'conversation' => $conversation->fresh(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Assign or reassign conversation to user and/or team.
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $conversation = OmnichannelConversation::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'assigned_user_id' => 'nullable|exists:users,id',
            'assigned_team_id' => 'nullable|exists:sales_teams,id',
            'routing_method' => 'nullable|string',
            'reason' => 'nullable|string',
        ]);

        $updated = $this->inboxService->assign(
            $conversation,
            $validated['assigned_user_id'] ?? null,
            $validated['assigned_team_id'] ?? null,
            $validated['routing_method'] ?? 'manual',
            $validated['reason'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => $updated,
        ]);
    }

    /**
     * Automated round-robin assignment within team based on agent workload.
     */
    public function autoAssign(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $conversation = OmnichannelConversation::where('tenant_id', $tenantId)->findOrFail($id);

        $updated = $this->routingService->routeConversation($conversation, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Conversation auto-assigned successfully via routing rules & workload distribution.',
            'data' => $updated,
        ]);
    }

    /**
     * Get assignment and transfer audit history for a conversation.
     */
    public function assignmentHistory(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $logs = ConversationAssignmentLog::where('tenant_id', $tenantId)
            ->where('conversation_id', $id)
            ->with(['assignedBy:id,name', 'assignedTo:id,name'])
            ->latest('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Update conversation status (open, pending, resolved).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $conversation = OmnichannelConversation::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:open,pending,resolved',
        ]);

        $updated = $this->inboxService->updateStatus($conversation, $validated['status']);

        return response()->json([
            'success' => true,
            'data' => $updated,
        ]);
    }

    /**
     * Toggle AI vs Human Takeover.
     */
    public function toggleAi(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $conversation = OmnichannelConversation::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'is_ai_handled' => 'required|boolean',
        ]);

        $updated = $this->inboxService->toggleAiHandling($conversation, $validated['is_ai_handled']);

        return response()->json([
            'success' => true,
            'data' => $updated,
        ]);
    }

    /**
     * Mark conversation messages as read.
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $conversation = OmnichannelConversation::where('tenant_id', $tenantId)->findOrFail($id);

        $updated = $this->inboxService->markAsRead($conversation);

        return response()->json([
            'success' => true,
            'data' => $updated,
        ]);
    }

    /**
     * Add team internal comment/note.
     */
    public function addNote(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $conversation = OmnichannelConversation::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'content' => 'required|string',
            'mentions' => 'nullable|array',
        ]);

        $note = $this->inboxService->addNote(
            $conversation,
            $request->user()->id,
            $validated['content'],
            $validated['mentions'] ?? []
        );

        return response()->json([
            'success' => true,
            'data' => $note->load('author:id,name'),
        ]);
    }

    /**
     * Unified Customer Timeline across all 8 channels, calls, notes, and activities.
     */
    public function timeline(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);

        $customerId = $request->query('customer_id');
        $leadId = $request->query('lead_id');
        $contactId = $request->query('contact_id');

        $timeline = $this->inboxService->getUnifiedTimeline(
            $tenantId,
            $customerId ? (int)$customerId : null,
            $leadId ? (int)$leadId : null,
            $contactId ? (int)$contactId : null
        );

        return response()->json([
            'success' => true,
            'data' => $timeline,
        ]);
    }

    /**
     * Omnichannel Real Communication Analytics & SLA Performance.
     */
    public function analytics(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $from = $request->query('from');
        $to = $request->query('to');

        $analytics = $this->inboxService->getOmnichannelAnalytics($tenantId, $from, $to);

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    /**
     * List Customer Communication Preferences & Consent.
     */
    public function getConsents(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $channel = $request->query('channel');

        $consents = CustomerCommunicationConsent::where('tenant_id', $tenantId)
            ->when($channel, fn($q) => $q->where('channel', $channel))
            ->with(['lead:id,name,phone,email', 'customer:id,name,phone,email'])
            ->latest('updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $consents,
        ]);
    }

    /**
     * Update or Record Customer Communication Consent / Opt-Out.
     */
    public function updateConsent(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);

        $validated = $request->validate([
            'destination' => 'required|string',
            'channel' => 'required|string|in:whatsapp,facebook,instagram,email,sms,call,all',
            'consent_status' => 'required|string|in:opted_in,opted_out,suppressed',
            'consent_source' => 'nullable|string',
            'is_marketing_allowed' => 'nullable|boolean',
            'is_transactional_allowed' => 'nullable|boolean',
            'lead_id' => 'nullable|integer',
            'customer_id' => 'nullable|integer',
        ]);

        $attributes = [
            'consent_status' => $validated['consent_status'],
            'consent_source' => $validated['consent_source'] ?? 'manual',
            'is_marketing_allowed' => $validated['is_marketing_allowed'] ?? ($validated['consent_status'] === 'opted_in'),
            'is_transactional_allowed' => $validated['is_transactional_allowed'] ?? ($validated['consent_status'] !== 'suppressed'),
            'lead_id' => $validated['lead_id'] ?? null,
            'customer_id' => $validated['customer_id'] ?? null,
        ];

        if ($validated['consent_status'] === 'opted_out') {
            $attributes['opt_out_timestamp'] = now();
        } elseif ($validated['consent_status'] === 'opted_in') {
            $attributes['consent_timestamp'] = now();
            $attributes['opt_out_timestamp'] = null;
        }

        $consent = CustomerCommunicationConsent::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'channel' => $validated['channel'],
                'destination' => $validated['destination'],
            ],
            $attributes
        );

        return response()->json([
            'success' => true,
            'message' => 'Communication consent preferences updated successfully.',
            'data' => $consent,
        ]);
    }

    /**
     * List Quick Replies / Canned Responses.
     */
    public function getQuickReplies(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $channel = $request->query('channel');

        $replies = OmnichannelQuickReply::where('tenant_id', $tenantId)
            ->when($channel && $channel !== 'all', fn($q) => $q->where(fn($sub) => $sub->where('channel', $channel)->orWhere('channel', 'all')))
            ->orderBy('shortcut')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $replies,
        ]);
    }

    /**
     * Create Quick Reply / Canned Response.
     */
    public function createQuickReply(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);

        $validated = $request->validate([
            'channel' => 'nullable|string|in:all,whatsapp,facebook,instagram,email,sms,webchat',
            'shortcut' => 'required|string|max:50',
            'title' => 'required|string|max:150',
            'content' => 'required|string',
            'tags' => 'nullable|array',
            'is_shared' => 'nullable|boolean',
        ]);

        $reply = OmnichannelQuickReply::create(array_merge($validated, [
            'tenant_id' => $tenantId,
            'created_by_user_id' => $request->user()?->id,
            'channel' => $validated['channel'] ?? 'all',
            'is_shared' => $validated['is_shared'] ?? true,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Quick reply template created successfully.',
            'data' => $reply,
        ], 201);
    }

    /**
     * Delete Quick Reply.
     */
    public function deleteQuickReply(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $reply = OmnichannelQuickReply::where('tenant_id', $tenantId)->findOrFail($id);
        $reply->delete();

        return response()->json([
            'success' => true,
            'message' => 'Quick reply template deleted.',
        ]);
    }

    /**
     * Typing / Presence indicator signal.
     */
    public function typing(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $conversation = OmnichannelConversation::where('tenant_id', $tenantId)->findOrFail($id);

        $presence = $this->inboxService->recordPresenceHeartbeat(
            $tenantId,
            $request->user()->id,
            $conversation->id,
            $request->boolean('is_typing', true)
        );

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->id,
            'user' => [
                'id' => $request->user()?->id,
                'name' => $request->user()?->name ?? 'Agent',
            ],
            'typing' => true,
            'presence' => $presence,
        ]);
    }

    /**
     * Lock conversation to active agent.
     */
    public function lock(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $conversation = OmnichannelConversation::where('tenant_id', $tenantId)->findOrFail($id);

        $locked = $this->inboxService->lockConversation($conversation, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Conversation locked to you.',
            'data' => $locked,
        ]);
    }

    /**
     * Unlock conversation.
     */
    public function unlock(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $conversation = OmnichannelConversation::where('tenant_id', $tenantId)->findOrFail($id);

        $unlocked = $this->inboxService->unlockConversation($conversation);

        return response()->json([
            'success' => true,
            'message' => 'Conversation unlocked.',
            'data' => $unlocked,
        ]);
    }

    /**
     * Archive or unarchive conversation.
     */
    public function archive(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $conversation = OmnichannelConversation::where('tenant_id', $tenantId)->findOrFail($id);
        $archive = $request->boolean('archive', true);

        $updated = $this->inboxService->archiveConversation($conversation, $archive);

        return response()->json([
            'success' => true,
            'message' => $archive ? 'Conversation archived.' : 'Conversation restored from archive.',
            'data' => $updated,
        ]);
    }

    /**
     * Record presence heartbeat and collision detection.
     */
    public function presence(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $userId = $request->user()->id;
        $conversationId = $request->input('conversation_id');
        $isTyping = $request->boolean('is_typing', false);

        $presence = $this->inboxService->recordPresenceHeartbeat($tenantId, $userId, $conversationId, $isTyping);

        $activeAgents = $conversationId
            ? $this->inboxService->getConversationPresence($tenantId, (int) $conversationId, $userId)
            : [];

        return response()->json([
            'success' => true,
            'presence' => $presence,
            'active_agents' => $activeAgents,
            'collision_detected' => count($activeAgents) > 0,
        ]);
    }

    /**
     * Check if another agent is currently active on this conversation.
     */
    public function collisionCheck(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $userId = $request->user()?->id;

        $activeAgents = $this->inboxService->getConversationPresence($tenantId, $id, $userId);

        return response()->json([
            'success' => true,
            'collision_detected' => count($activeAgents) > 0,
            'active_agents' => $activeAgents,
        ]);
    }

    // ==========================================
    // ROUTING RULES CRUD
    // ==========================================

    public function getRoutingRules(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $rules = OmnichannelRoutingRule::where('tenant_id', $tenantId)
            ->with(['targetUser:id,name,email', 'targetTeam:id,name'])
            ->orderBy('priority', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rules,
        ]);
    }

    public function saveRoutingRule(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $validated = $request->validate([
            'id' => 'nullable|exists:omnichannel_routing_rules,id',
            'name' => 'required|string|max:150',
            'rule_type' => 'required|string|in:round_robin,least_active,branch,department,language,lead_source,product,working_hours,direct',
            'channel' => 'required|string',
            'conditions' => 'nullable|array',
            'target_user_id' => 'nullable|exists:users,id',
            'target_team_id' => 'nullable|exists:sales_teams,id',
            'priority' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $rule = OmnichannelRoutingRule::updateOrCreate(
            ['id' => $validated['id'] ?? null, 'tenant_id' => $tenantId],
            [
                'name' => $validated['name'],
                'rule_type' => $validated['rule_type'],
                'channel' => $validated['channel'] ?? 'all',
                'conditions' => $validated['conditions'] ?? null,
                'target_user_id' => $validated['target_user_id'] ?? null,
                'target_team_id' => $validated['target_team_id'] ?? null,
                'priority' => $validated['priority'] ?? 10,
                'is_active' => $validated['is_active'] ?? true,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Routing rule saved successfully.',
            'data' => $rule->load(['targetUser:id,name,email', 'targetTeam:id,name']),
        ]);
    }

    public function deleteRoutingRule(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $rule = OmnichannelRoutingRule::where('tenant_id', $tenantId)->findOrFail($id);
        $rule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Routing rule deleted.',
        ]);
    }

    // ==========================================
    // SLA POLICIES CRUD
    // ==========================================

    public function getSlaPolicies(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $policies = OmnichannelSlaPolicy::where('tenant_id', $tenantId)
            ->with('escalateToUser:id,name,email')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $policies,
        ]);
    }

    public function saveSlaPolicy(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $validated = $request->validate([
            'id' => 'nullable|exists:omnichannel_sla_policies,id',
            'name' => 'required|string|max:150',
            'priority' => 'required|string',
            'channel' => 'required|string',
            'first_response_target_minutes' => 'required|integer|min:1',
            'resolution_target_minutes' => 'required|integer|min:1',
            'business_hours_enabled' => 'nullable|boolean',
            'business_hours' => 'nullable|array',
            'escalation_target_minutes' => 'nullable|integer',
            'escalate_to_user_id' => 'nullable|exists:users,id',
            'is_active' => 'nullable|boolean',
        ]);

        $policy = OmnichannelSlaPolicy::updateOrCreate(
            ['id' => $validated['id'] ?? null, 'tenant_id' => $tenantId],
            [
                'name' => $validated['name'],
                'priority' => $validated['priority'],
                'channel' => $validated['channel'],
                'first_response_target_minutes' => $validated['first_response_target_minutes'],
                'resolution_target_minutes' => $validated['resolution_target_minutes'],
                'business_hours_enabled' => $validated['business_hours_enabled'] ?? true,
                'business_hours' => $validated['business_hours'] ?? null,
                'escalation_target_minutes' => $validated['escalation_target_minutes'] ?? null,
                'escalate_to_user_id' => $validated['escalate_to_user_id'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'SLA policy saved successfully.',
            'data' => $policy->load('escalateToUser:id,name,email'),
        ]);
    }

    public function deleteSlaPolicy(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $policy = OmnichannelSlaPolicy::where('tenant_id', $tenantId)->findOrFail($id);
        $policy->delete();

        return response()->json([
            'success' => true,
            'message' => 'SLA policy deleted.',
        ]);
    }

    public function recalculateSla(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $summary = $this->slaService->checkOverdueConversations($tenantId);

        return response()->json([
            'success' => true,
            'message' => 'SLA compliance re-evaluated across open conversations.',
            'summary' => $summary,
            'data' => [
                'summary' => $summary,
            ],
        ]);
    }

    // ==========================================
    // DETAILED OMNICHANNEL REPORTS & BI
    // ==========================================

    public function detailedReports(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $analytics = $this->inboxService->getOmnichannelAnalytics($tenantId);

        $slaStats = $this->slaService->checkOverdueConversations($tenantId);

        // Calculate SLA compliance percentage
        $totalResolved = OmnichannelConversation::where('tenant_id', $tenantId)->where('status', 'resolved')->count();
        $breachedCount = OmnichannelConversation::where('tenant_id', $tenantId)->where('sla_status', 'breached')->count();
        $complianceRate = ($totalResolved + $slaStats['total']) > 0
            ? round((($totalResolved + $slaStats['on_track']) / ($totalResolved + $slaStats['total'])) * 100, 1)
            : 100;

        return response()->json([
            'success' => true,
            'data' => array_merge($analytics, [
                'sla_compliance' => [
                    'compliance_rate' => $complianceRate,
                    'on_track' => $slaStats['on_track'],
                    'warning' => $slaStats['warning'],
                    'breached' => $slaStats['breached'],
                    'total_open' => $slaStats['total'],
                ],
            ]),
        ]);
    }
}
