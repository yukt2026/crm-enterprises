<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Marketing\Models\CampaignRecipient;
use App\Domains\Marketing\Models\MarketingCampaign;
use App\Domains\Marketing\Services\CampaignApprovalService;
use App\Domains\Marketing\Services\CampaignDispatchService;
use App\Domains\Marketing\Services\MarketingAnalyticsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingCampaignController extends Controller
{
    public function __construct(
        protected CampaignDispatchService $dispatchService,
        protected CampaignApprovalService $approvalService,
        protected MarketingAnalyticsService $analyticsService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $query = MarketingCampaign::where('tenant_id', $tenantId)
            ->with([
                'segment:id,name,target_entity',
                'whatsappTemplate:id,name,category',
                'assignedOwner:id,name,email',
                'approvedByUser:id,name,email',
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }
        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }
        if ($request->filled('objective')) {
            $query->where('objective', $request->objective);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $campaigns = $query->orderByDesc('updated_at')->get();

        return response()->json([
            'success' => true,
            'data' => $campaigns,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'channel' => 'required|in:whatsapp,email,sms',
            'type' => 'nullable|in:broadcast,drip,triggered',
            'objective' => 'nullable|string|max:64',
            'segment_id' => 'nullable|exists:marketing_segments,id',
            'whatsapp_template_id' => 'nullable|exists:whatsapp_templates,id',
            'whatsapp_account_id' => 'nullable|exists:whatsapp_accounts,id',
            'email_account_id' => 'nullable|exists:email_accounts,id',
            'sms_config_id' => 'nullable|exists:sms_configurations,id',
            'subject' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'variable_mappings' => 'nullable|array',
            'scheduled_at' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'budget' => 'nullable|numeric|min:0',
            'assigned_owner_id' => 'nullable|exists:users,id',
            'branch_id' => 'nullable|integer',
            'business_hours_only' => 'nullable|boolean',
            'frequency_cap_days' => 'nullable|integer|min:0',
            'campaign_notes' => 'nullable|string',
            'ai_drafted' => 'nullable|boolean',
            'timezone' => 'nullable|string',
            'rate_limit_per_minute' => 'nullable|integer',
        ]);

        $tenantId = $request->user()->current_tenant_id;
        $status = !empty($validated['scheduled_at']) ? 'scheduled' : 'draft';

        $campaign = MarketingCampaign::create(array_merge($validated, [
            'tenant_id' => $tenantId,
            'status' => $status,
            'approval_status' => 'not_submitted',
            'created_by_user_id' => $request->user()->id,
            'assigned_owner_id' => $validated['assigned_owner_id'] ?? $request->user()->id,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Marketing campaign created successfully.',
            'data' => $campaign->load(['segment', 'whatsappTemplate', 'assignedOwner']),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $campaign = MarketingCampaign::where('tenant_id', $tenantId)
            ->with([
                'segment',
                'whatsappTemplate',
                'whatsappAccount',
                'emailAccount',
                'smsConfig',
                'createdByUser:id,name',
                'assignedOwner:id,name,email',
                'approvedByUser:id,name,email',
                'approvals.approver:id,name,email',
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $campaign,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $campaign = MarketingCampaign::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'channel' => 'sometimes|required|in:whatsapp,email,sms',
            'type' => 'nullable|in:broadcast,drip,triggered',
            'objective' => 'nullable|string|max:64',
            'segment_id' => 'nullable|exists:marketing_segments,id',
            'whatsapp_template_id' => 'nullable|exists:whatsapp_templates,id',
            'whatsapp_account_id' => 'nullable|exists:whatsapp_accounts,id',
            'email_account_id' => 'nullable|exists:email_accounts,id',
            'sms_config_id' => 'nullable|exists:sms_configurations,id',
            'subject' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'variable_mappings' => 'nullable|array',
            'scheduled_at' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'budget' => 'nullable|numeric|min:0',
            'assigned_owner_id' => 'nullable|exists:users,id',
            'branch_id' => 'nullable|integer',
            'business_hours_only' => 'nullable|boolean',
            'frequency_cap_days' => 'nullable|integer|min:0',
            'campaign_notes' => 'nullable|string',
            'timezone' => 'nullable|string',
            'rate_limit_per_minute' => 'nullable|integer',
        ]);

        $campaign->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Marketing campaign updated.',
            'data' => $campaign->fresh(['segment', 'whatsappTemplate', 'assignedOwner']),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $campaign = MarketingCampaign::where('tenant_id', $tenantId)->findOrFail($id);
        $campaign->delete();

        return response()->json([
            'success' => true,
            'message' => 'Marketing campaign deleted.',
        ]);
    }

    public function submitForApproval(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $campaign = MarketingCampaign::where('tenant_id', $tenantId)->findOrFail($id);

        $comments = $request->input('comments');
        $updated = $this->approvalService->submitForApproval($campaign, $request->user(), $comments);

        return response()->json([
            'success' => true,
            'message' => 'Campaign submitted for approval.',
            'data' => $updated->load(['approvals']),
        ]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $campaign = MarketingCampaign::where('tenant_id', $tenantId)->findOrFail($id);

        $comments = $request->input('comments');
        $updated = $this->approvalService->approve($campaign, $request->user(), $comments);

        return response()->json([
            'success' => true,
            'message' => 'Campaign approved successfully.',
            'data' => $updated->load(['approvals', 'approvedByUser']),
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $campaign = MarketingCampaign::where('tenant_id', $tenantId)->findOrFail($id);

        $reason = $request->validate(['reason' => 'required|string|min:3'])['reason'];
        $updated = $this->approvalService->reject($campaign, $request->user(), $reason);

        return response()->json([
            'success' => true,
            'message' => 'Campaign rejected.',
            'data' => $updated->load(['approvals']),
        ]);
    }

    public function duplicate(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $source = MarketingCampaign::where('tenant_id', $tenantId)->findOrFail($id);

        $clone = $source->replicate([
            'sent_count', 'delivered_count', 'read_count', 'replied_count',
            'failed_count', 'unsubscribed_count', 'clicks_count', 'opens_count',
            'conversions_count', 'spend_amount', 'attributed_revenue',
            'started_at', 'completed_at', 'approved_at', 'approved_by_user_id',
        ]);
        $clone->name = $source->name . ' (Copy)';
        $clone->status = 'draft';
        $clone->approval_status = 'not_submitted';
        $clone->created_by_user_id = $request->user()->id;
        $clone->save();

        return response()->json([
            'success' => true,
            'message' => 'Campaign duplicated successfully.',
            'data' => $clone->fresh(['segment', 'whatsappTemplate']),
        ], 201);
    }

    public function archive(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $campaign = MarketingCampaign::where('tenant_id', $tenantId)->findOrFail($id);
        $campaign->update(['status' => 'archived']);

        return response()->json([
            'success' => true,
            'message' => 'Campaign archived.',
            'data' => $campaign->fresh(),
        ]);
    }

    public function dispatch(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $campaign = MarketingCampaign::where('tenant_id', $tenantId)->findOrFail($id);

        $result = $this->dispatchService->dispatch($campaign, false);

        return response()->json([
            'success' => true,
            'message' => 'Campaign broadcast dispatched.',
            'data' => [
                'campaign' => $campaign->fresh(['segment']),
                'dispatch_result' => $result,
            ],
        ]);
    }

    public function testSend(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $campaign = MarketingCampaign::where('tenant_id', $tenantId)->findOrFail($id);

        $recipientAddress = $request->validate([
            'recipient' => 'required|string',
        ])['recipient'];

        $result = $this->dispatchService->dispatch($campaign, true, $recipientAddress);

        return response()->json([
            'success' => $result['sent'] > 0,
            'message' => $result['sent'] > 0
                ? "Test message dispatched to {$recipientAddress}."
                : ($result['skipped'] > 0 ? "Test message skipped: recipient is suppressed or opted out." : "Test send failed."),
            'data' => $result,
        ]);
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $campaign = MarketingCampaign::where('tenant_id', $tenantId)->findOrFail($id);
        $campaign->update(['status' => 'paused']);

        return response()->json([
            'success' => true,
            'message' => 'Campaign paused.',
            'data' => $campaign->fresh(),
        ]);
    }

    public function resume(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $campaign = MarketingCampaign::where('tenant_id', $tenantId)->findOrFail($id);
        $campaign->update(['status' => 'running']);

        return response()->json([
            'success' => true,
            'message' => 'Campaign resumed.',
            'data' => $campaign->fresh(),
        ]);
    }

    public function analytics(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $campaign = MarketingCampaign::where('tenant_id', $tenantId)->findOrFail($id);

        $data = $this->analyticsService->getCampaignAnalytics($campaign);

        $recentLogs = CampaignRecipient::where('tenant_id', $tenantId)
            ->where('campaign_id', $campaign->id)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $data['recent_logs'] = $recentLogs;

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function overviewSummary(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $summary = $this->analyticsService->getDashboardSummary($tenantId);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }
}
