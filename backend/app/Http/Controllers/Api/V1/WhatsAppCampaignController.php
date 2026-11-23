<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Identity\Services\RbacService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Domains\WhatsApp\Models\WhatsAppCampaign;
use App\Domains\WhatsApp\Models\WhatsAppCampaignRecipient;
use App\Domains\WhatsApp\Services\WhatsAppCampaignService;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppCampaignController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected WhatsAppCampaignService $campaignService,
        protected RbacService $rbacService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * List campaigns
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $query = WhatsAppCampaign::where('tenant_id', $tenantId)
            ->with(['template', 'phoneNumber', 'creator', 'approver'])
            ->orderBy('id', 'desc');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $campaigns = $query->paginate($request->integer('per_page', 15));

        return $this->successResponse($campaigns, 'WhatsApp campaigns retrieved.');
    }

    /**
     * Create campaign
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'whatsapp_phone_number_id' => 'required|integer',
            'whatsapp_template_id' => 'required|integer',
            'audience_type' => 'required|string|in:segment,contacts,csv_import',
            'audience_segment_id' => 'nullable|integer',
            'selected_contact_ids' => 'nullable|array',
            'csv_rows' => 'nullable|array',
            'variable_mappings' => 'nullable|array',
            'scheduled_at' => 'nullable|date',
            'estimated_cost' => 'nullable|numeric',
        ]);

        $campaign = $this->campaignService->createCampaign($tenantId, $validated, $request->user());

        return $this->successResponse($campaign, 'Campaign created in draft mode.', 201);
    }

    /**
     * Get campaign details
     */
    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $campaign = WhatsAppCampaign::where('tenant_id', $tenantId)
            ->with(['template', 'phoneNumber', 'creator', 'approver'])
            ->findOrFail($id);

        $analytics = $this->campaignService->getCampaignAnalytics($campaign);

        return $this->successResponse([
            'campaign' => $campaign,
            'analytics' => $analytics,
        ], 'Campaign details retrieved.');
    }

    /**
     * List campaign recipients
     */
    public function recipients(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $campaign = WhatsAppCampaign::where('tenant_id', $tenantId)->findOrFail($id);

        $query = WhatsAppCampaignRecipient::where('campaign_id', $campaign->id)
            ->orderBy('id', 'asc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $recipients = $query->paginate($request->integer('per_page', 50));

        return $this->successResponse($recipients, 'Campaign recipients retrieved.');
    }

    /**
     * Send test message to an admin/tester phone
     */
    public function testMessage(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'phone_number' => 'required|string',
            'sample_parameters' => 'nullable|array',
        ]);

        $campaign = WhatsAppCampaign::where('tenant_id', $tenantId)->findOrFail($id);

        $result = $this->campaignService->sendTestMessage(
            $campaign,
            $validated['phone_number'],
            $validated['sample_parameters'] ?? []
        );

        return $this->successResponse($result, 'Test campaign dispatch successful.');
    }

    /**
     * Submit campaign for two-step approval
     */
    public function submitForApproval(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $campaign = WhatsAppCampaign::where('tenant_id', $tenantId)->findOrFail($id);
        $campaign = $this->campaignService->submitForApproval($campaign);

        return $this->successResponse($campaign, 'Campaign submitted for approval.');
    }

    /**
     * Approve campaign (Requires whatsapp.campaigns_approve permission or admin/owner)
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $user = $request->user();

        // Check RBAC permission
        $canApprove = false;
        if ($user) {
            $role = str_replace(' ', '', strtolower($user->role ?? ''));
            if (in_array($role, ['admin', 'owner', 'superadmin', 'super_admin'])) {
                $canApprove = true;
            } elseif ($this->rbacService->hasPermission($user, 'whatsapp.campaigns_approve')) {
                $canApprove = true;
            }
        }

        if (!$canApprove) {
            return $this->errorResponse('Unauthorized. You do not possess the `whatsapp.campaigns_approve` permission required to authorize bulk campaigns.', 403);
        }

        $campaign = WhatsAppCampaign::where('tenant_id', $tenantId)->findOrFail($id);
        $campaign = $this->campaignService->approveCampaign($campaign, $user?->id ?? 1);

        return $this->successResponse($campaign, 'Campaign authorized and approved.');
    }

    /**
     * Start / launch campaign
     */
    public function start(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $campaign = WhatsAppCampaign::where('tenant_id', $tenantId)->findOrFail($id);
        $campaign = $this->campaignService->startOrResumeCampaign($campaign);

        return $this->successResponse($campaign, 'Campaign launched and execution started.');
    }

    /**
     * Pause running campaign
     */
    public function pause(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $campaign = WhatsAppCampaign::where('tenant_id', $tenantId)->findOrFail($id);
        $campaign = $this->campaignService->pauseCampaign($campaign);

        return $this->successResponse($campaign, 'Campaign paused.');
    }

    /**
     * Cancel campaign
     */
    public function cancel(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $campaign = WhatsAppCampaign::where('tenant_id', $tenantId)->findOrFail($id);
        $campaign = $this->campaignService->cancelCampaign($campaign);

        return $this->successResponse($campaign, 'Campaign cancelled.');
    }

    /**
     * Process batch of campaign recipients
     */
    public function processBatch(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $batchSize = $request->integer('batch_size', 50);

        $campaign = WhatsAppCampaign::where('tenant_id', $tenantId)->findOrFail($id);
        $processed = $this->campaignService->processBatch($campaign, $batchSize);

        return $this->successResponse([
            'processed_count' => $processed,
        ], 'Batch processed successfully.');
    }
}
