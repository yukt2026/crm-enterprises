<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Services\SlaService;
use App\Domains\Support\Services\SupportAiService;
use App\Domains\Support\Services\TicketService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected TicketService $ticketService,
        protected SlaService $slaService,
        protected SupportAiService $supportAiService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $query = SupportTicket::where('tenant_id', $tenantId)
            ->with(['customer', 'contact', 'company', 'assignedUser', 'category', 'slaPolicy'])
            ->withCount('messages');

        // Filter scopes
        $view = $request->get('view', 'all');
        if ($view === 'my_tickets' && auth()->check()) {
            $query->myTickets(auth()->id());
        } elseif ($view === 'open') {
            $query->open();
        } elseif ($view === 'resolved') {
            $query->resolved();
        } elseif ($view === 'closed') {
            $query->closed();
        } elseif ($view === 'breached') {
            $query->breached();
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('source_channel')) {
            $query->where('source_channel', $request->source_channel);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('assigned_user_id')) {
            $query->where('assigned_user_id', $request->assigned_user_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('sla_status')) {
            $query->where('sla_status', $request->sla_status);
        }

        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('subject', 'like', '%' . $request->q . '%')
                  ->orWhere('ticket_number', 'like', '%' . $request->q . '%')
                  ->orWhere('description', 'like', '%' . $request->q . '%');
            });
        }

        $query->orderByRaw("CASE WHEN status IN ('resolved', 'closed') THEN 2 ELSE 1 END")
              ->orderByDesc('id');

        $perPage = (int) $request->get('limit', $request->get('per_page', 20));
        return $this->paginatedResponse($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'status' => 'nullable|string|in:new,open,in_progress,waiting_customer,waiting_internal,resolved,closed',
            'source_channel' => 'nullable|string|in:email,whatsapp,web_chat,phone,portal',
            'customer_id' => 'nullable|exists:customers,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'company_id' => 'nullable|exists:companies,id',
            'category_id' => 'nullable|exists:support_ticket_categories,id',
            'subcategory' => 'nullable|string|max:150',
            'assigned_user_id' => 'nullable|exists:users,id',
            'assigned_team_id' => 'nullable|exists:sales_teams,id',
            'lead_id' => 'nullable|exists:leads,id',
            'deal_id' => 'nullable|exists:deals,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'sla_policy_id' => 'nullable|exists:support_sla_policies,id',
            'tags' => 'nullable|array',
            'attachments' => 'nullable|array',
        ]);

        $ticket = $this->ticketService->createTicket($validated, $tenantId, auth()->id());

        return $this->successResponse($ticket, 'Support ticket created successfully.', 201);
    }

    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $ticket = SupportTicket::where('tenant_id', $tenantId)
            ->with([
                'customer',
                'contact',
                'company',
                'assignedUser',
                'escalatedTo',
                'category',
                'slaPolicy',
                'lead',
                'deal',
                'invoice',
                'messages' => function ($q) {
                    $q->orderBy('created_at', 'asc')->with(['user', 'customer']);
                },
            ])
            ->findOrFail($id);

        // Run live SLA check
        $this->slaService->evaluateAndEscalateTicket($ticket);

        return $this->successResponse($ticket->fresh([
            'customer', 'contact', 'company', 'assignedUser', 'escalatedTo', 'category', 'slaPolicy', 'messages.user', 'messages.customer'
        ]));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $ticket = SupportTicket::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'subject' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'sometimes|string|in:low,medium,high,urgent',
            'status' => 'sometimes|string|in:new,open,in_progress,waiting_customer,waiting_internal,resolved,closed',
            'category_id' => 'nullable|exists:support_ticket_categories,id',
            'subcategory' => 'nullable|string|max:150',
            'assigned_user_id' => 'nullable|exists:users,id',
            'assigned_team_id' => 'nullable|exists:sales_teams,id',
            'tags' => 'nullable|array',
            'attachments' => 'nullable|array',
        ]);

        $ticket = $this->ticketService->updateTicket($ticket, $validated);

        return $this->successResponse($ticket, 'Ticket updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $ticket = SupportTicket::where('tenant_id', $tenantId)->findOrFail($id);
        $ticket->delete();

        return $this->successResponse(null, 'Ticket deleted successfully.');
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $ticket = SupportTicket::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'assigned_user_id' => 'required|exists:users,id',
            'assigned_team_id' => 'nullable|exists:sales_teams,id',
        ]);

        $ticket = $this->ticketService->assignTicket($ticket, $validated['assigned_user_id'], $validated['assigned_team_id'] ?? null);

        return $this->successResponse($ticket, 'Ticket assigned successfully.');
    }

    public function messages(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $ticket = SupportTicket::where('tenant_id', $tenantId)->findOrFail($id);

        return $this->successResponse(
            $ticket->messages()->with(['user', 'customer'])->orderBy('created_at', 'asc')->get()
        );
    }

    public function addMessage(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $ticket = SupportTicket::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'message' => 'required|string',
            'is_internal' => 'nullable|boolean',
            'is_draft' => 'nullable|boolean',
            'sender_type' => 'nullable|string|in:user,customer,system,ai',
            'channel' => 'nullable|string|in:email,whatsapp,web_chat,internal',
            'attachments' => 'nullable|array',
            'status' => 'nullable|string|in:open,in_progress,waiting_customer,waiting_internal',
        ]);

        $message = $this->ticketService->addMessage($ticket, $validated, $tenantId, auth()->id());

        return $this->successResponse($message->load(['user', 'customer']), 'Message sent.', 201);
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $ticket = SupportTicket::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'resolution_notes' => 'required|string',
        ]);

        $ticket = $this->ticketService->resolveTicket($ticket, $validated['resolution_notes'], auth()->id());

        return $this->successResponse($ticket, 'Ticket resolved successfully.');
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $ticket = SupportTicket::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'closure_reason' => 'nullable|string',
        ]);

        $ticket = $this->ticketService->closeTicket($ticket, $validated['closure_reason'] ?? null);

        return $this->successResponse($ticket, 'Ticket closed successfully.');
    }

    public function reopen(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $ticket = SupportTicket::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'reopen_reason' => 'required|string',
        ]);

        $ticket = $this->ticketService->reopenTicket($ticket, $validated['reopen_reason'], auth()->id());

        return $this->successResponse($ticket, 'Ticket reopened successfully.');
    }

    public function csat(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $ticket = SupportTicket::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string',
        ]);

        $ticket = $this->ticketService->submitCsat($ticket, $validated['rating'], $validated['comment'] ?? null);

        return $this->successResponse($ticket, 'Customer satisfaction feedback recorded.');
    }

    public function aiAssistance(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $ticket = SupportTicket::where('tenant_id', $tenantId)->findOrFail($id);

        $assistance = $this->supportAiService->generateAiAssistance(
            $ticket,
            $request->get('context')
        );

        return $this->successResponse($assistance);
    }

    public function analytics(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $analytics = $this->ticketService->getSupportAnalytics($tenantId);

        return $this->successResponse($analytics);
    }

    public function transitions(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $ticket = SupportTicket::where('tenant_id', $tenantId)->findOrFail($id);

        return $this->successResponse(
            $ticket->transitions()->with('user')->orderBy('created_at', 'desc')->get()
        );
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'ticket_ids' => 'required|array|min:1',
            'ticket_ids.*' => 'integer',
            'status' => 'nullable|string',
            'assigned_user_id' => 'nullable|integer',
            'priority' => 'nullable|string',
        ]);

        $ticketIds = $validated['ticket_ids'];
        unset($validated['ticket_ids']);

        $count = $this->ticketService->bulkUpdateTickets($tenantId, $ticketIds, $validated, auth()->id());

        return $this->successResponse(['updated_count' => $count], "{$count} tickets updated successfully.");
    }
}
