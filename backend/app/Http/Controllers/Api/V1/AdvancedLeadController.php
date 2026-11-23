<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\CRM\Models\CrmActivity;
use App\Domains\Identity\Services\RbacService;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\LeadAcquisition\Models\LeadEscalationLog;
use App\Domains\LeadAcquisition\Models\LeadFollowup;
use App\Domains\LeadAcquisition\Models\LeadMergeRequest;
use App\Domains\LeadAcquisition\Models\LeadPortalIntegration;
use App\Domains\LeadAcquisition\Models\LeadRoutingRule;
use App\Domains\LeadAcquisition\Models\LeadScoringConfiguration;
use App\Domains\LeadAcquisition\Models\LeadSlaPolicy;
use App\Domains\LeadAcquisition\Models\LeadStageHistory;
use App\Domains\LeadAcquisition\Services\Adapters\IndianB2BPortalNormalizer;
use App\Domains\LeadAcquisition\Services\LeadCaptureService;
use App\Domains\LeadAcquisition\Services\LeadDuplicateMergeService;
use App\Domains\LeadAcquisition\Services\LeadFollowupService;
use App\Domains\LeadAcquisition\Services\LeadLifecycleService;
use App\Domains\LeadAcquisition\Services\LeadPerformanceReportService;
use App\Domains\LeadAcquisition\Services\LeadRoutingEngine;
use App\Domains\LeadAcquisition\Services\LeadSlaEngine;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdvancedLeadController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LeadCaptureService $captureService,
        protected LeadRoutingEngine $routingEngine,
        protected LeadSlaEngine $slaEngine,
        protected LeadLifecycleService $lifecycleService,
        protected LeadFollowupService $followupService,
        protected LeadDuplicateMergeService $mergeService,
        protected LeadPerformanceReportService $reportService,
        protected RbacService $rbacService
    ) {}

    private function getTenantId(): int
    {
        return TenantContext::getTenantId() ?? 1;
    }

    private function authorizePermission(string $action = 'view'): void
    {
        $user = Auth::user();
        if ($user && !$this->rbacService->can($user, 'leads', $action)) {
            abort(403, "Unauthorized: You lack the 'leads.{$action}' permission.");
        }
    }

    // ==========================================
    // 1. MULTI-SOURCE & PORTAL CAPTURE
    // ==========================================

    /**
     * Unified multi-source lead capture endpoint
     */
    public function capture(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'alternate_phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'company_name' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'designation' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'product_interest' => 'nullable|string|max:255',
            'requirement' => 'nullable|string',
            'message' => 'nullable|string',
            'estimated_value' => 'nullable|numeric|min:0',
            'budget' => 'nullable|numeric|min:0',
            'source' => 'nullable|string|max:100',
            'campaign' => 'nullable|string|max:100',
            'utm_source' => 'nullable|string|max:100',
            'utm_medium' => 'nullable|string|max:100',
            'utm_campaign' => 'nullable|string|max:100',
            'utm_term' => 'nullable|string|max:100',
            'utm_content' => 'nullable|string|max:100',
            'landing_page_url' => 'nullable|string|max:255',
            'branch_id' => 'nullable|integer',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'tags' => 'nullable|array',
            'notes' => 'nullable|string',
            'custom_fields' => 'nullable|array',
            'external_lead_id' => 'nullable|string|max:150',
        ]);

        $result = $this->captureService->captureLead($validated, $tenantId, Auth::id());

        return $this->successResponse($result, 'Lead captured and processed through ingestion pipeline.', 201);
    }

    /**
     * Ingestion from Indian B2B Portals (IndiaMART, Justdial, TradeIndia)
     */
    public function capturePortal(Request $request, string $portal): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $payload = $request->all();

        // 1. Verify portal integration configuration
        $config = LeadPortalIntegration::where('tenant_id', $tenantId)
            ->where('portal', $portal)
            ->first();

        if ($config && !$config->is_active) {
            return $this->errorResponse("Portal integration for {$portal} is currently disabled.", 403);
        }

        // Verify webhook secret / API key if configured
        if ($config && $config->webhook_secret) {
            $providedSecret = $request->header('X-Webhook-Secret') ?? $request->header('X-Lead-Secret') ?? $request->input('secret');
            if ($providedSecret !== $config->webhook_secret) {
                return $this->errorResponse("Invalid portal authentication secret.", 401);
            }
        }

        // 2. Normalize by Portal
        $normalized = match (strtolower($portal)) {
            'indiamart' => IndianB2BPortalNormalizer::parseIndiaMart($payload),
            'justdial' => IndianB2BPortalNormalizer::parseJustdial($payload),
            'tradeindia' => IndianB2BPortalNormalizer::parseTradeIndia($payload),
            default => [
                'name' => $payload['name'] ?? 'B2B Lead',
                'phone' => IndianB2BPortalNormalizer::normalizeIndianPhone($payload['phone'] ?? $payload['mobile'] ?? ''),
                'email' => $payload['email'] ?? null,
                'company_name' => $payload['company'] ?? null,
                'product_interest' => $payload['product'] ?? 'General Enquiry',
                'source' => strtolower($portal),
                'raw' => $payload,
            ]
        };

        if ($config && $config->default_campaign) {
            $normalized['campaign'] = $config->default_campaign;
        }

        $result = $this->captureService->captureLead($normalized, $tenantId, Auth::id());

        // Increment total captured count
        if ($config) {
            $config->increment('total_captured_count');
            $config->update(['last_sync_at' => now()]);
        }

        return $this->successResponse($result, "Lead captured successfully from {$portal}.", 201);
    }

    /**
     * List portal integrations
     */
    public function listPortals(): JsonResponse
    {
        $this->authorizePermission('manage_settings');
        $tenantId = $this->getTenantId();

        $portals = LeadPortalIntegration::where('tenant_id', $tenantId)->get();
        return $this->successResponse($portals);
    }

    /**
     * Create or update portal integration
     */
    public function savePortal(Request $request): JsonResponse
    {
        $this->authorizePermission('manage_settings');
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'portal' => 'required|string|in:indiamart,justdial,tradeindia,generic_webhook,referral',
            'name' => 'required|string|max:150',
            'api_key' => 'nullable|string|max:255',
            'webhook_secret' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'auto_assign' => 'boolean',
            'auto_score' => 'boolean',
            'default_campaign' => 'nullable|string|max:100',
        ]);

        $portal = LeadPortalIntegration::updateOrCreate(
            ['tenant_id' => $tenantId, 'portal' => $validated['portal']],
            $validated
        );

        return $this->successResponse($portal, 'Portal configuration saved successfully.');
    }

    // ==========================================
    // 2. ADVANCED LEAD ROUTING RULES
    // ==========================================

    public function listRoutingRules(): JsonResponse
    {
        $this->authorizePermission('manage_settings');
        $tenantId = $this->getTenantId();

        $rules = LeadRoutingRule::where('tenant_id', $tenantId)
            ->with('fallbackUser:id,name,email')
            ->orderBy('priority', 'asc')
            ->get();

        return $this->successResponse($rules);
    }

    public function saveRoutingRule(Request $request): JsonResponse
    {
        $this->authorizePermission('manage_settings');
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'name' => 'required|string|max:150',
            'priority' => 'nullable|integer',
            'is_active' => 'boolean',
            'conditions' => 'nullable|array',
            'assignment_method' => 'required|string|in:round_robin,least_active,availability,branch_based,location_based,department_based,product_based,source_based,campaign_based,manual',
            'target_user_ids' => 'nullable|array',
            'target_team_id' => 'nullable|integer',
            'target_branch_id' => 'nullable|integer',
            'fallback_user_id' => 'nullable|exists:users,id',
        ]);

        $validated['tenant_id'] = $tenantId;
        $rule = LeadRoutingRule::updateOrCreate(
            ['id' => $validated['id'] ?? null, 'tenant_id' => $tenantId],
            $validated
        );

        return $this->successResponse($rule->load('fallbackUser:id,name'), 'Routing rule saved successfully.');
    }

    public function deleteRoutingRule(int $id): JsonResponse
    {
        $this->authorizePermission('manage_settings');
        $tenantId = $this->getTenantId();

        LeadRoutingRule::where('tenant_id', $tenantId)->where('id', $id)->delete();
        return $this->successResponse(null, 'Routing rule deleted successfully.');
    }

    public function autoRouteLead(Lead $lead): JsonResponse
    {
        $this->authorizePermission('assign');
        $updated = $this->routingEngine->routeAndAssign($lead, Auth::id());
        return $this->successResponse($updated->load('assignedUser'), 'Lead successfully routed via rules engine.');
    }

    // ==========================================
    // 3. SLA POLICIES & LEAKAGE ESCALATION
    // ==========================================

    public function listSlaPolicies(): JsonResponse
    {
        $this->authorizePermission('manage_settings');
        $tenantId = $this->getTenantId();

        $policies = LeadSlaPolicy::where('tenant_id', $tenantId)->with('managerUser:id,name,email')->get();
        return $this->successResponse($policies);
    }

    public function saveSlaPolicy(Request $request): JsonResponse
    {
        $this->authorizePermission('manage_settings');
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'name' => 'required|string|max:150',
            'is_default' => 'boolean',
            'first_response_time_minutes' => 'required|integer|min:1',
            'followup_deadline_hours' => 'required|integer|min:1',
            'max_idle_hours' => 'required|integer|min:1',
            'working_hours_only' => 'boolean',
            'business_hours_start' => 'nullable|string',
            'business_hours_end' => 'nullable|string',
            'working_days' => 'nullable|array',
            'holiday_calendar' => 'nullable|array',
            'escalation_level_1_minutes' => 'required|integer|min:1',
            'escalation_level_2_minutes' => 'required|integer|min:1',
            'auto_reassign_on_escalation' => 'boolean',
            'manager_user_id' => 'nullable|exists:users,id',
        ]);

        $validated['tenant_id'] = $tenantId;

        if (!empty($validated['is_default'])) {
            LeadSlaPolicy::where('tenant_id', $tenantId)->update(['is_default' => false]);
        }

        $policy = LeadSlaPolicy::updateOrCreate(
            ['id' => $validated['id'] ?? null, 'tenant_id' => $tenantId],
            $validated
        );

        return $this->successResponse($policy->load('managerUser:id,name'), 'Lead SLA policy saved successfully.');
    }

    public function evaluateSlaEscalations(): JsonResponse
    {
        $this->authorizePermission('manage_settings');
        $tenantId = $this->getTenantId();

        $result = $this->slaEngine->evaluateAndEscalatePendingLeads($tenantId);
        return $this->successResponse($result, 'Lead SLA scan completed and escalations triggered.');
    }

    public function listEscalationLogs(): JsonResponse
    {
        $this->authorizePermission('view_reports');
        $tenantId = $this->getTenantId();

        $logs = LeadEscalationLog::where('tenant_id', $tenantId)
            ->with(['lead:id,name,phone,source,status', 'previousUser:id,name', 'newUser:id,name'])
            ->orderBy('triggered_at', 'desc')
            ->paginate(20);

        return $this->paginatedResponse($logs);
    }

    public function recordResponse(Request $request, Lead $lead): JsonResponse
    {
        $this->authorizePermission('edit');
        $channel = $request->input('channel', 'call');

        $updated = $this->slaEngine->recordFirstResponse($lead, $channel, Auth::id());
        return $this->successResponse($updated, 'First response logged and SLA evaluated.');
    }

    // ==========================================
    // 4. FOLLOW-UP MANAGEMENT
    // ==========================================

    public function listFollowups(Lead $lead): JsonResponse
    {
        $this->authorizePermission('view');
        $followups = LeadFollowup::where('lead_id', $lead->id)
            ->with(['assignedUser:id,name', 'createdBy:id,name'])
            ->orderBy('scheduled_at', 'desc')
            ->get();

        return $this->successResponse($followups);
    }

    public function listOverdueFollowups(): JsonResponse
    {
        $this->authorizePermission('view');
        $tenantId = $this->getTenantId();

        $user = Auth::user();
        $assignedId = ($user && !$this->rbacService->can($user, 'leads', 'view_all')) ? $user->id : null;

        $overdue = $this->followupService->getOverdueFollowups($tenantId, $assignedId);
        return $this->successResponse($overdue);
    }

    public function scheduleFollowup(Request $request, Lead $lead): JsonResponse
    {
        $this->authorizePermission('edit');
        $validated = $request->validate([
            'followup_type' => 'required|string|in:call,whatsapp,email,meeting,task_reminder',
            'scheduled_at' => 'required|date',
            'assigned_user_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $followup = $this->followupService->scheduleFollowup($lead, $validated, Auth::id());
        return $this->successResponse($followup->load('assignedUser:id,name'), 'Follow-up scheduled successfully.', 201);
    }

    public function completeFollowup(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission('edit');
        $validated = $request->validate([
            'outcome' => 'required|string|in:connected,busy,no_answer,interested,callback_requested,not_interested,meeting_scheduled',
            'notes' => 'nullable|string',
        ]);

        $followup = LeadFollowup::where('tenant_id', $this->getTenantId())->findOrFail($id);
        $completed = $this->followupService->completeFollowup($followup, $validated['outcome'], $validated['notes'] ?? null, Auth::id());

        return $this->successResponse($completed, 'Follow-up completed and outcome recorded.');
    }

    public function rescheduleFollowup(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission('edit');
        $validated = $request->validate([
            'new_scheduled_at' => 'required|date',
            'reason' => 'nullable|string',
        ]);

        $followup = LeadFollowup::where('tenant_id', $this->getTenantId())->findOrFail($id);
        $rescheduled = $this->followupService->rescheduleFollowup($followup, $validated['new_scheduled_at'], $validated['reason'] ?? null, Auth::id());

        return $this->successResponse($rescheduled, 'Follow-up rescheduled successfully.');
    }

    // ==========================================
    // 5. LIFECYCLE STAGE TRANSITIONS
    // ==========================================

    public function transitionStage(Request $request, Lead $lead): JsonResponse
    {
        $this->authorizePermission('edit');
        $validated = $request->validate([
            'to_stage' => 'required|string|max:50',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $updated = $this->lifecycleService->transitionStage(
            $lead,
            $validated['to_stage'],
            Auth::id(),
            $validated['reason'] ?? null,
            $validated['notes'] ?? null
        );

        return $this->successResponse($updated, "Lead lifecycle stage changed to {$validated['to_stage']}.");
    }

    public function listStageHistory(Lead $lead): JsonResponse
    {
        $this->authorizePermission('view');
        $history = LeadStageHistory::where('lead_id', $lead->id)
            ->with('user:id,name')
            ->orderBy('id', 'desc')
            ->get();

        return $this->successResponse($history);
    }

    // ==========================================
    // 6. DUPLICATE MERGE REQUESTS WORKFLOW
    // ==========================================

    public function listMergeRequests(): JsonResponse
    {
        $this->authorizePermission('view');
        $tenantId = $this->getTenantId();

        $requests = LeadMergeRequest::where('tenant_id', $tenantId)
            ->with(['masterLead:id,name,phone,email,company_name', 'duplicateLead:id,name,phone,email,company_name', 'requestedBy:id,name', 'approvedBy:id,name'])
            ->orderBy('id', 'desc')
            ->get();

        return $this->successResponse($requests);
    }

    public function createMergeRequest(Request $request): JsonResponse
    {
        $this->authorizePermission('edit');
        $validated = $request->validate([
            'master_lead_id' => 'required|exists:leads,id',
            'duplicate_lead_id' => 'required|exists:leads,id|different:master_lead_id',
            'field_overrides' => 'nullable|array',
            'similarity_reasons' => 'nullable|array',
        ]);

        $master = Lead::where('tenant_id', $this->getTenantId())->findOrFail($validated['master_lead_id']);
        $duplicate = Lead::where('tenant_id', $this->getTenantId())->findOrFail($validated['duplicate_lead_id']);

        $mergeReq = $this->mergeService->requestMerge(
            $master,
            $duplicate,
            $validated['field_overrides'] ?? [],
            $validated['similarity_reasons'] ?? [],
            Auth::id() ?? 1
        );

        return $this->successResponse($mergeReq, 'Lead merge request submitted for manager review.', 201);
    }

    public function approveMergeRequest(int $id): JsonResponse
    {
        $this->authorizePermission('approve');
        $mergeReq = LeadMergeRequest::where('tenant_id', $this->getTenantId())->findOrFail($id);

        $mergedMaster = $this->mergeService->approveMerge($mergeReq, Auth::id() ?? 1);
        return $this->successResponse($mergedMaster, 'Duplicate lead successfully merged into master record.');
    }

    public function rejectMergeRequest(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission('reject');
        $reason = $request->input('reason', 'Duplicate rejected by manager');

        $mergeReq = LeadMergeRequest::where('tenant_id', $this->getTenantId())->findOrFail($id);
        $rejected = $this->mergeService->rejectMerge($mergeReq, $reason, Auth::id() ?? 1);

        return $this->successResponse($rejected, 'Lead merge request rejected.');
    }

    // ==========================================
    // 7. SCORING CONFIGURATION
    // ==========================================

    public function getScoringConfig(): JsonResponse
    {
        $this->authorizePermission('manage_settings');
        $tenantId = $this->getTenantId();

        $config = LeadScoringConfiguration::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'hot_threshold' => 70,
                'warm_threshold' => 40,
                'weights' => [
                    'completeness' => 15,
                    'budget' => 25,
                    'timeline' => 20,
                    'source' => 20,
                    'engagement' => 20,
                ],
                'is_active' => true,
            ]
        );

        return $this->successResponse($config);
    }

    public function saveScoringConfig(Request $request): JsonResponse
    {
        $this->authorizePermission('manage_settings');
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'hot_threshold' => 'required|integer|min:1|max:100',
            'warm_threshold' => 'required|integer|min:1|max:100',
            'weights' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $config = LeadScoringConfiguration::updateOrCreate(
            ['tenant_id' => $tenantId],
            $validated
        );

        return $this->successResponse($config, 'Lead scoring thresholds and weights updated.');
    }

    // ==========================================
    // 8. INTELLIGENCE & LEAKAGE REPORTING
    // ==========================================

    public function reportsDashboard(): JsonResponse
    {
        $this->authorizePermission('view_reports');
        $tenantId = $this->getTenantId();

        $data = $this->reportService->getPerformanceDashboard($tenantId);
        return $this->successResponse($data);
    }
}
