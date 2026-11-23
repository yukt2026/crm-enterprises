<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\CRM\Models\CrmActivity;
use App\Domains\CRM\Services\CrmImportExportService;
use App\Domains\CRM\Services\DuplicateDetectionService;
use App\Domains\CRM\Services\LeadConversionService;
use App\Domains\CRM\Services\LeadDistributionService;
use App\Domains\CRM\Services\LeadScoringService;
use App\Domains\Identity\Services\RbacService;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class LeadController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LeadScoringService $scoringService,
        protected LeadDistributionService $distributionService,
        protected DuplicateDetectionService $duplicateService,
        protected LeadConversionService $conversionService,
        protected CrmImportExportService $importExportService,
        protected RbacService $rbacService
    ) {}

    /**
     * List leads with filtering, search, sorting, and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = TenantContext::getTenantId() ?? 1;

        $query = Lead::where('tenant_id', $tenantId)
            ->with(['company:id,name,industry', 'assignedUser:id,name,email']);

        // Apply RBAC row-level data scoping
        $user = $request->user();
        if ($user) {
            $query = $this->rbacService->applyDataScope($query, $user, 'leads', 'view');
        }

        // Archive Filter
        if ($request->boolean('archived')) {
            $query->archived();
        } else {
            $query->active();
        }

        // Search Filter
        if ($request->filled('q')) {
            $search = '%' . $request->q . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('email', 'like', $search)
                  ->orWhere('company_name', 'like', $search)
                  ->orWhere('phone', 'like', $search)
                  ->orWhere('requirement', 'like', $search)
                  ->orWhere('city', 'like', $search);
            });
        }

        // Categorical Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('qualification_status')) {
            $query->where('qualification_status', $request->qualification_status);
        }
        if ($request->filled('lifecycle_stage')) {
            $query->where('lifecycle_stage', $request->lifecycle_stage);
        }
        if ($request->filled('assigned_user_id')) {
            if ($request->assigned_user_id === 'unassigned') {
                $query->unassigned();
            } else {
                $query->where('assigned_user_id', $request->assigned_user_id);
            }
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = (int) $request->get('limit', $request->get('per_page', 15));
        $leads = $query->paginate($perPage);

        return $this->paginatedResponse($leads);
    }

    /**
     * Store new lead with auto-scoring and activity logging
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'designation' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'alternate_phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'requirement' => 'nullable|string',
            'product_interest' => 'nullable|string|max:255',
            'estimated_value' => 'nullable|numeric|min:0',
            'expected_purchase_date' => 'nullable|date',
            'status' => 'nullable|string|in:new,attempted,contacted,qualified,nurturing,converted,lost,disqualified',
            'lifecycle_stage' => 'nullable|string|in:subscriber,lead,mql,sql,opportunity,customer,evangelist',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'source' => 'nullable|string|max:100',
            'source_account' => 'nullable|string|max:100',
            'campaign' => 'nullable|string|max:100',
            'ad_details' => 'nullable|string|max:255',
            'utm_source' => 'nullable|string|max:100',
            'utm_medium' => 'nullable|string|max:100',
            'utm_campaign' => 'nullable|string|max:100',
            'landing_page_url' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'assigned_user_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
            'next_followup_at' => 'nullable|date',
        ]);

        $tenantId = TenantContext::getTenantId() ?? 1;
        $validated['tenant_id'] = $tenantId;
        $validated['status'] = $validated['status'] ?? 'new';
        $validated['priority'] = $validated['priority'] ?? 'medium';

        $lead = Lead::create($validated);

        // Calculate transparent score
        $this->scoringService->updateLeadScore($lead);

        // Record creation activity
        CrmActivity::create([
            'tenant_id' => $tenantId,
            'user_id' => Auth::id(),
            'lead_id' => $lead->id,
            'activity_type' => 'status_change',
            'title' => 'Lead Created',
            'description' => "Initial lead capture from source: " . ($lead->source ?: 'Direct Inbound'),
        ]);

        return $this->successResponse($lead->fresh(['company', 'assignedUser']), 'Lead created successfully.', 201);
    }

    private function authorizeTenantAccess(Lead $lead, string $action = 'view'): void
    {
        $tenantId = TenantContext::getTenantId();
        if ($tenantId && (int) $lead->tenant_id !== (int) $tenantId) {
            abort(404, 'Lead not found in this workspace context.');
        }

        $user = Auth::user();
        if ($user && !$this->rbacService->can($user, 'leads', $action, $lead)) {
            abort(403, "Unauthorized access: You do not have permission to execute '{$action}' on this lead record.");
        }
    }

    /**
     * Show single lead with activities and converted links
     */
    public function show(Lead $lead): JsonResponse
    {
        $this->authorizeTenantAccess($lead, 'view');

        return $this->successResponse($lead->load([
            'company',
            'assignedUser:id,name,email',
            'convertedContact',
            'convertedCompany',
            'convertedDeal',
            'activities.user:id,name',
            'assignments.assignedTo:id,name',
        ]));
    }

    /**
     * Update lead record and re-score
     */
    public function update(Request $request, Lead $lead): JsonResponse
    {
        $this->authorizeTenantAccess($lead, 'edit');
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'designation' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'alternate_phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'requirement' => 'nullable|string',
            'product_interest' => 'nullable|string|max:255',
            'estimated_value' => 'nullable|numeric|min:0',
            'expected_purchase_date' => 'nullable|date',
            'status' => 'sometimes|string',
            'lifecycle_stage' => 'nullable|string',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'qualification_status' => 'nullable|string|in:unqualified,in_qualification,qualified,disqualified',
            'source' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
            'assigned_user_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
            'lost_reason' => 'nullable|string',
            'disqualification_reason' => 'nullable|string',
            'next_followup_at' => 'nullable|date',
        ]);

        $oldStatus = $lead->status;
        $lead->update($validated);

        // Recalculate score
        $this->scoringService->updateLeadScore($lead);

        // Status change activity
        if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
            CrmActivity::create([
                'tenant_id' => $lead->tenant_id,
                'user_id' => Auth::id(),
                'lead_id' => $lead->id,
                'activity_type' => 'status_change',
                'title' => "Status Changed to " . ucfirst($validated['status']),
                'description' => "Transitioned from {$oldStatus} to {$validated['status']}.",
            ]);
        }

        return $this->successResponse($lead->fresh(['company', 'assignedUser']), 'Lead updated successfully.');
    }

    /**
     * Delete lead (soft delete)
     */
    public function destroy(Lead $lead): JsonResponse
    {
        $this->authorizeTenantAccess($lead, 'delete');
        $lead->delete();
        return $this->successResponse(null, 'Lead deleted successfully.');
    }

    /**
     * Bulk delete leads
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array']);
        Lead::whereIn('id', $request->ids)->delete();
        return $this->successResponse(null, 'Selected leads deleted successfully.');
    }

    /**
     * Check for potential duplicates
     */
    public function checkDuplicates(Request $request): JsonResponse
    {
        $params = $request->only(['email', 'phone', 'company_name', 'name']);
        $ignoreId = $request->input('ignore_id');

        $duplicates = $this->duplicateService->checkDuplicates($params, $ignoreId ? (int) $ignoreId : null);

        return $this->successResponse([
            'has_duplicates' => !empty($duplicates),
            'count' => count($duplicates),
            'duplicates' => $duplicates,
        ]);
    }

    /**
     * Merge duplicate lead into master lead
     */
    public function merge(Request $request, Lead $lead): JsonResponse
    {
        $this->authorizeTenantAccess($lead);
        $request->validate([
            'duplicate_id' => 'required|exists:leads,id',
            'field_overrides' => 'nullable|array',
        ]);

        $duplicateLead = Lead::findOrFail($request->duplicate_id);
        $this->authorizeTenantAccess($duplicateLead);
        $merged = $this->duplicateService->mergeLeads(
            master: $lead,
            duplicate: $duplicateLead,
            fieldOverrides: $request->input('field_overrides', []),
            userId: Auth::id()
        );

        return $this->successResponse($merged, 'Duplicate lead successfully merged into master record.');
    }

    /**
     * Assign lead to specific user
     */
    public function assign(Request $request, Lead $lead): JsonResponse
    {
        $this->authorizeTenantAccess($lead);
        $request->validate([
            'assigned_user_id' => 'required|exists:users,id',
            'reason' => 'nullable|string',
        ]);

        $updated = $this->distributionService->assignLead(
            lead: $lead,
            assignToUserId: (int) $request->assigned_user_id,
            assignedByUserId: Auth::id(),
            reason: $request->input('reason', 'Direct manual delegation')
        );

        return $this->successResponse($updated->load('assignedUser'), 'Lead successfully assigned.');
    }

    /**
     * Auto-distribute unassigned leads using round-robin balancer
     */
    public function autoDistributeRoundRobin(): JsonResponse
    {
        $result = $this->distributionService->autoDistributeRoundRobin();
        return $this->successResponse($result, 'Round-robin distribution executed.');
    }

    /**
     * Convert lead into Contact, Company, and Deal
     */
    public function convert(Request $request, Lead $lead): JsonResponse
    {
        $this->authorizeTenantAccess($lead);
        $options = $request->only([
            'company_id',
            'company_name',
            'create_deal',
            'deal_name',
            'deal_value',
            'deal_stage',
            'close_date',
            'create_project',
            'project_name',
        ]);

        $result = $this->conversionService->convertLead($lead, $options, Auth::id());
        return $this->successResponse($result, 'Lead successfully converted into Contact, Company, and Deal.');
    }

    /**
     * Archive lead
     */
    public function archive(Lead $lead): JsonResponse
    {
        $this->authorizeTenantAccess($lead);
        $lead->is_archived = true;
        $lead->save();

        CrmActivity::create([
            'tenant_id' => $lead->tenant_id,
            'user_id' => Auth::id(),
            'lead_id' => $lead->id,
            'activity_type' => 'status_change',
            'title' => 'Lead Archived',
            'description' => 'Lead moved to archive repository.',
        ]);

        return $this->successResponse($lead, 'Lead archived successfully.');
    }

    /**
     * Unarchive lead
     */
    public function unarchive(Lead $lead): JsonResponse
    {
        $this->authorizeTenantAccess($lead);
        $lead->is_archived = false;
        $lead->save();

        return $this->successResponse($lead, 'Lead restored from archive.');
    }

    /**
     * Recalculate lead score
     */
    public function recalculateScore(Lead $lead): JsonResponse
    {
        $this->authorizeTenantAccess($lead);
        $lead = $this->scoringService->updateLeadScore($lead);
        return $this->successResponse([
            'score' => $lead->score,
            'score_breakdown' => $lead->score_breakdown,
            'qualification_status' => $lead->qualification_status,
        ], 'Lead score recalculated.');
    }

    /**
     * Export leads to CSV
     */
    public function export(): Response
    {
        $user = request()->user();
        if ($user && !$this->rbacService->can($user, 'leads', 'export')) {
            abort(403, 'Unauthorized access: You do not have permission to export leads.');
        }

        $csv = $this->importExportService->exportCsv('leads');
        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="leads_export_' . date('Ymd_His') . '.csv"',
        ]);
    }

    /**
     * Import leads from CSV
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'csv_content' => 'required_without:file|string',
            'file' => 'nullable|file|mimes:csv,txt|max:5120',
        ]);

        $content = $request->input('csv_content');
        if ($request->hasFile('file')) {
            $content = file_get_contents($request->file('file')->getRealPath());
        }

        $result = $this->importExportService->importLeadsCsv($content);
        return $this->successResponse($result, 'CSV import processed.');
    }
}
