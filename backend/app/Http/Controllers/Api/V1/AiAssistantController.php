<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\AI\Models\AiAssistantSession;
use App\Domains\AI\Models\AiDocumentExtraction;
use App\Domains\AI\Models\AiMeetingRecording;
use App\Domains\AI\Models\AiProposedAction;
use App\Domains\AI\Models\AiRequestLog;
use App\Domains\AI\Models\TenantAiConfiguration;
use App\Domains\AI\Services\AiActionExecutionService;
use App\Domains\AI\Services\AiBusinessAssistantService;
use App\Domains\AI\Services\AiContentGeneratorService;
use App\Domains\AI\Services\AiDocumentIntelligenceService;
use App\Domains\AI\Services\AiMeetingIntelligenceService;
use App\Domains\AI\Services\AiProviderGatewayService;
use App\Domains\AI\Services\AiSalesAssistantService;
use App\Domains\Identity\Services\RbacService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAssistantController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected AiProviderGatewayService $aiGateway,
        protected AiSalesAssistantService $salesAssistant,
        protected AiBusinessAssistantService $businessAssistant,
        protected AiActionExecutionService $actionExecutor,
        protected AiMeetingIntelligenceService $meetingService,
        protected AiDocumentIntelligenceService $documentService,
        protected AiContentGeneratorService $contentService,
        protected RbacService $rbacService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * Check permission helper
     */
    protected function authorizePermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if (!$user) return;

        $role = str_replace(' ', '', strtolower($user->role ?? ''));
        if (in_array($role, ['admin', 'owner', 'superadmin', 'super_admin'])) {
            return;
        }

        if (!$this->rbacService->hasPermission($user, $permission)) {
            abort(403, "Forbidden. You do not possess the required '{$permission}' permission.");
        }
    }

    // =========================================================================
    // 1. AI PROVIDER CONFIGURATION & STATUS
    // =========================================================================

    public function getProviders(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'ai.manage_providers');
        $tenantId = $this->getTenantId();

        $configs = TenantAiConfiguration::where('tenant_id', $tenantId)->get();
        if ($configs->isEmpty()) {
            $configs = collect([$this->aiGateway->getTenantConfiguration($tenantId)]);
        }

        return $this->successResponse($configs, 'AI provider configurations retrieved.');
    }

    public function updateProvider(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'ai.manage_providers');
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'provider_name' => 'required|string',
            'display_name' => 'nullable|string',
            'api_key' => 'nullable|string',
            'base_url' => 'nullable|string',
            'default_model' => 'nullable|string',
            'fallback_model' => 'nullable|string',
            'temperature' => 'nullable|numeric|between:0,1',
            'max_tokens' => 'nullable|integer',
            'rate_limit_rpm' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        $config = $this->aiGateway->updateProviderSettings($tenantId, $validated);
        return $this->successResponse($config, 'AI provider configuration saved successfully.');
    }

    public function pingProvider(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'ai.manage_providers');
        $tenantId = $this->getTenantId();

        $result = $this->aiGateway->testConnection($tenantId, $request->input('config_id'));
        return $this->successResponse($result, 'AI provider connectivity ping executed.');
    }

    // =========================================================================
    // 2. AI BUSINESS ASSISTANT & CHAT SESSIONS
    // =========================================================================

    public function getSessions(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'ai.use_assistant');
        $tenantId = $this->getTenantId();
        $user = $request->user();

        $sessions = AiAssistantSession::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->withCount('messages')
            ->orderBy('is_pinned', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();

        return $this->successResponse($sessions, 'AI assistant sessions retrieved.');
    }

    public function getSessionMessages(Request $request, int $sessionId): JsonResponse
    {
        $this->authorizePermission($request, 'ai.use_assistant');
        $tenantId = $this->getTenantId();
        $user = $request->user();

        $session = AiAssistantSession::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->with('messages')
            ->findOrFail($sessionId);

        return $this->successResponse($session, 'Session messages retrieved.');
    }

    public function askAssistant(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'ai.use_assistant');
        $tenantId = $this->getTenantId();
        $user = $request->user();

        $validated = $request->validate([
            'prompt' => 'required|string|max:5000',
            'session_id' => 'nullable|integer',
            'context_filters' => 'nullable|array',
        ]);

        $result = $this->businessAssistant->askBusinessAssistant(
            $tenantId,
            $user,
            $validated['prompt'],
            $validated['session_id'] ?? null,
            $validated['context_filters'] ?? []
        );

        return $this->successResponse($result, 'AI Assistant response generated.');
    }

    // =========================================================================
    // 3. AI SALES ASSISTANT & INTENT SCORING
    // =========================================================================

    public function analyzeSales(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'ai.analyze_leads');
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'entity_type' => 'required|string|in:lead,deal',
            'entity_id' => 'required|integer',
        ]);

        $insight = $this->salesAssistant->analyzeSalesEntity(
            $tenantId,
            $validated['entity_type'],
            $validated['entity_id'],
            $request->user()
        );

        return $this->successResponse($insight, 'AI sales intelligence analysis complete.');
    }

    public function getSalesInsight(Request $request, string $entityType, int $entityId): JsonResponse
    {
        $this->authorizePermission($request, 'ai.view_insights');
        $tenantId = $this->getTenantId();

        $insight = $this->salesAssistant->getInsight($tenantId, $entityType, $entityId);
        return $this->successResponse($insight, 'AI sales insight retrieved.');
    }

    // =========================================================================
    // 4. ACTION PROPOSAL & EXECUTION GATE
    // =========================================================================

    public function getProposedActions(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'ai.view_insights');
        $tenantId = $this->getTenantId();

        $actions = AiProposedAction::where('tenant_id', $tenantId)
            ->with(['user', 'confirmedBy'])
            ->orderBy('id', 'desc')
            ->paginate($request->integer('per_page', 20));

        return $this->successResponse($actions, 'Proposed AI actions retrieved.');
    }

    public function confirmAction(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission($request, 'ai.execute_actions');
        $tenantId = $this->getTenantId();

        $action = $this->actionExecutor->confirmAndExecute($tenantId, $id, $request->user());
        return $this->successResponse($action, 'Proposed AI action executed successfully.');
    }

    public function rejectAction(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission($request, 'ai.execute_actions');
        $tenantId = $this->getTenantId();

        $action = $this->actionExecutor->rejectAction($tenantId, $id, $request->user(), $request->input('reason'));
        return $this->successResponse($action, 'Proposed AI action rejected.');
    }

    // =========================================================================
    // 5. AI MEETING INTELLIGENCE
    // =========================================================================

    public function getMeetings(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'ai.read_transcripts');
        $tenantId = $this->getTenantId();

        $meetings = AiMeetingRecording::where('tenant_id', $tenantId)
            ->with('creator')
            ->orderBy('id', 'desc')
            ->paginate($request->integer('per_page', 15));

        return $this->successResponse($meetings, 'Meeting recordings retrieved.');
    }

    public function processMeeting(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'ai.read_transcripts');
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'audio_file' => 'nullable|file|mimes:mp3,wav,m4a,ogg,webm|max:50000',
            'mock_transcript' => 'nullable|string',
            'language' => 'nullable|string|max:10',
            'contact_id' => 'nullable|integer',
            'deal_id' => 'nullable|integer',
        ]);

        $recording = $this->meetingService->processMeetingRecording(
            $tenantId,
            $request->user(),
            $validated['title'],
            $request->file('audio_file'),
            $validated['mock_transcript'] ?? null,
            $validated['language'] ?? 'en',
            $validated['contact_id'] ?? null,
            $validated['deal_id'] ?? null
        );

        return $this->successResponse($recording, 'Meeting processed and intelligence extracted.', 201);
    }

    // =========================================================================
    // 6. AI DOCUMENT INTELLIGENCE
    // =========================================================================

    public function getDocuments(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'ai.read_documents');
        $tenantId = $this->getTenantId();

        $docs = AiDocumentExtraction::where('tenant_id', $tenantId)
            ->with('uploader')
            ->orderBy('id', 'desc')
            ->paginate($request->integer('per_page', 15));

        return $this->successResponse($docs, 'Extracted documents retrieved.');
    }

    public function uploadDocument(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'ai.read_documents');
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'file' => 'required|file|mimes:pdf,docx,png,jpg,jpeg|max:20000',
            'document_classification' => 'nullable|string|in:invoice,purchase_order,contract_agreement,quotation,identity,general',
        ]);

        $extraction = $this->documentService->extractDocumentData(
            $tenantId,
            $request->user(),
            $request->file('file'),
            $validated['document_classification'] ?? 'invoice'
        );

        return $this->successResponse($extraction, 'Document uploaded and analyzed by AI.', 201);
    }

    public function updateDocumentCorrection(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission($request, 'ai.read_documents');
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'corrections' => 'required|array',
        ]);

        $extraction = $this->documentService->updateCorrections($tenantId, $id, $validated['corrections']);
        return $this->successResponse($extraction, 'Document corrections saved.');
    }

    public function commitDocument(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission($request, 'ai.read_documents');
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'target_entity_type' => 'required|string',
            'target_entity_id' => 'required|integer',
        ]);

        $extraction = $this->documentService->commitExtraction(
            $tenantId,
            $id,
            $validated['target_entity_type'],
            $validated['target_entity_id']
        );

        return $this->successResponse($extraction, 'Document committed to CRM record.');
    }

    // =========================================================================
    // 7. AI CONTENT GENERATION
    // =========================================================================

    public function generateContent(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'ai.generate_content');
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'content_type' => 'required|string',
            'prompt_or_context' => 'required|string|max:4000',
            'tone' => 'nullable|string',
            'language' => 'nullable|string',
            'template_variables' => 'nullable|array',
        ]);

        $content = $this->contentService->generateContent(
            $tenantId,
            $request->user(),
            $validated['content_type'],
            $validated['prompt_or_context'],
            $validated['tone'] ?? 'professional',
            $validated['language'] ?? 'en',
            $validated['template_variables'] ?? []
        );

        return $this->successResponse($content, 'AI content draft generated successfully.', 201);
    }

    // =========================================================================
    // 8. AI USAGE, METERING & ADMIN REPORTS
    // =========================================================================

    public function getUsageReports(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'ai.view_usage_reports');
        $tenantId = $this->getTenantId();

        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $query = AiRequestLog::where('tenant_id', $tenantId)
            ->whereDate('recorded_date', '>=', $startDate)
            ->whereDate('recorded_date', '<=', $endDate);

        $totalRequests = (clone $query)->count();
        $totalTokens = (clone $query)->sum('total_tokens');
        $totalCost = (float) (clone $query)->sum('cost_incurred');
        $failedRequests = (clone $query)->where('status', 'error')->count();

        // Model breakdown
        $byModel = (clone $query)
            ->selectRaw('model, count(*) as count, sum(total_tokens) as tokens, sum(cost_incurred) as cost')
            ->groupBy('model')
            ->get();

        // Feature breakdown
        $byFeature = (clone $query)
            ->selectRaw('feature_code, count(*) as count, sum(cost_incurred) as cost')
            ->groupBy('feature_code')
            ->get();

        // User breakdown
        $byUser = (clone $query)
            ->selectRaw('user_id, count(*) as count, sum(cost_incurred) as cost')
            ->groupBy('user_id')
            ->with('user:id,name,email')
            ->get();

        return $this->successResponse([
            'summary' => [
                'total_requests' => $totalRequests,
                'total_tokens' => $totalTokens,
                'total_cost' => round($totalCost, 4),
                'failed_requests' => $failedRequests,
                'success_rate' => $totalRequests > 0 ? round((($totalRequests - $failedRequests) / $totalRequests) * 100, 1) : 100,
            ],
            'by_model' => $byModel,
            'by_feature' => $byFeature,
            'by_user' => $byUser,
        ], 'AI usage reports retrieved.');
    }

    public function getRequestLogs(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'ai.view_usage_reports');
        $tenantId = $this->getTenantId();

        $logs = AiRequestLog::where('tenant_id', $tenantId)
            ->with('user:id,name,email')
            ->orderBy('id', 'desc')
            ->paginate($request->integer('per_page', 25));

        return $this->successResponse($logs, 'AI request logs retrieved.');
    }
}
