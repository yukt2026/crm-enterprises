<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Tenancy\Services\TenantContext;
use App\Domains\WhatsApp\Services\WhatsAppInboxService;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppConversationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected WhatsAppInboxService $inboxService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * List conversations with team filters
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $filters = $request->only(['scope', 'status', 'priority', 'search']);
        $perPage = (int) $request->query('per_page', 25);

        $conversations = $this->inboxService->listConversations($tenantId, $filters, $request->user(), $perPage);

        return $this->paginatedResponse($conversations, 'Conversations retrieved successfully.');
    }

    /**
     * Show conversation thread
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $conversation = $this->inboxService->getConversationThread($tenantId, $id, $request->user());

        return $this->successResponse($conversation, 'Conversation thread retrieved.');
    }

    /**
     * Assign conversation to agent
     */
    public function assign(int $id, Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'assigned_user_id' => 'nullable|integer',
        ]);

        $conversation = $this->inboxService->assignConversation(
            $tenantId,
            $id,
            $validated['assigned_user_id'] ?? null,
            $request->user()
        );

        return $this->successResponse($conversation, 'Conversation assigned successfully.');
    }

    /**
     * Update conversation status (open, pending, resolved)
     */
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'status' => 'required|string|in:open,pending,resolved',
        ]);

        $conversation = $this->inboxService->updateStatus($tenantId, $id, $validated['status'], $request->user());

        return $this->successResponse($conversation, "Conversation marked as {$validated['status']}.");
    }

    /**
     * Add internal team note
     */
    public function addNote(int $id, Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $note = $this->inboxService->addNote($tenantId, $id, $validated['content'], $request->user());

        return $this->successResponse($note, 'Internal team note added.', 201);
    }

    /**
     * Toggle AI / Human Takeover mode
     */
    public function toggleAi(int $id, Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'is_ai_handled' => 'required|boolean',
        ]);

        $conversation = $this->inboxService->toggleAiHandover($tenantId, $id, $validated['is_ai_handled'], $request->user());

        return $this->successResponse($conversation, 'AI takeover mode updated.');
    }

    /**
     * Create CRM Lead directly from conversation
     */
    public function createLead(int $id, Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'name' => 'nullable|string|max:150',
            'email' => 'nullable|email|max:150',
            'company' => 'nullable|string|max:150',
        ]);

        $lead = $this->inboxService->createLeadFromConversation($tenantId, $id, $validated, $request->user());

        return $this->successResponse($lead, 'Lead created from WhatsApp conversation.', 201);
    }
}
