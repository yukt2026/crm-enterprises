<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\CRM\Models\Branch;
use App\Domains\CRM\Models\BranchSalesTarget;
use App\Domains\CRM\Models\BranchTransfer;
use App\Domains\CRM\Services\MultiBranchService;
use App\Domains\Identity\Services\RbacService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function __construct(
        protected MultiBranchService $branchService,
        protected RbacService $rbacService
    ) {}

    /**
     * List all branches accessible to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->current_tenant_id;

        $query = Branch::where('tenant_id', $tenantId)
            ->with(['manager:id,name,email', 'departments:id,name']);

        // Enforce RBAC branch data scope
        if (!$this->rbacService->isOrganizationOwner($user, $tenantId) && !$user->is_platform_admin) {
            $effective = $this->rbacService->getEffectivePermissions($user, $tenantId);
            $modulePerms = $effective['modules']['branches'] ?? null;
            $dataScope = $modulePerms['actions']['view']['data_scope'] ?? $modulePerms['actions']['*']['data_scope'] ?? 'all';

            if ($dataScope === 'branch' && $user->branch_id) {
                $query->where('id', $user->branch_id);
            }
        }

        if ($request->filled('search')) {
            $search = '%' . trim($request->input('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('code', 'like', $search)
                  ->orWhere('city', 'like', $search)
                  ->orWhere('state', 'like', $search);
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('city')) {
            $query->where('city', $request->input('city'));
        }

        $branches = $query->withCount(['users', 'employees', 'leads', 'deals', 'projects', 'supportTickets'])
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $branches,
            'meta' => [
                'total' => $branches->count(),
                'active_count' => $branches->where('is_active', true)->count(),
            ],
        ]);
    }

    /**
     * Show detailed branch profile.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->current_tenant_id;

        $branch = Branch::where('tenant_id', $tenantId)
            ->with(['manager:id,name,email', 'departments:id,name', 'salesTargets'])
            ->withCount(['users', 'employees', 'leads', 'deals', 'projects', 'supportTickets', 'customers', 'meetings'])
            ->findOrFail($id);

        $this->authorizeBranchAccess($user, $branch);

        return response()->json([
            'success' => true,
            'data' => $branch,
        ]);
    }

    /**
     * Create a new branch.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->current_tenant_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'max:60'],
            'is_active' => ['nullable', 'boolean'],
            'manager_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'business_hours' => ['nullable', 'array'],
            'working_days' => ['nullable', 'array'],
            'connected_channels' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
        ]);

        $branch = $this->branchService->createBranch($validated, $tenantId);

        return response()->json([
            'success' => true,
            'message' => 'Branch created successfully.',
            'data' => $branch,
        ], 201);
    }

    /**
     * Update an existing branch.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->current_tenant_id;

        $branch = Branch::where('tenant_id', $tenantId)->findOrFail($id);
        $this->authorizeBranchAccess($user, $branch);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'max:60'],
            'is_active' => ['nullable', 'boolean'],
            'manager_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'business_hours' => ['nullable', 'array'],
            'working_days' => ['nullable', 'array'],
            'connected_channels' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
        ]);

        $updated = $this->branchService->updateBranch($branch, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Branch updated successfully.',
            'data' => $updated,
        ]);
    }

    /**
     * Soft delete a branch.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->current_tenant_id;

        $branch = Branch::where('tenant_id', $tenantId)->findOrFail($id);
        $branch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Branch deactivated and deleted successfully.',
        ]);
    }

    /**
     * Toggle branch active status.
     */
    public function setStatus(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->current_tenant_id;

        $branch = Branch::where('tenant_id', $tenantId)->findOrFail($id);
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $updated = $this->branchService->setBranchStatus($branch, (bool)$validated['is_active']);

        return response()->json([
            'success' => true,
            'message' => 'Branch status updated successfully.',
            'data' => $updated,
        ]);
    }

    /**
     * Assign employees/users to a branch.
     */
    public function assignEmployees(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->current_tenant_id;

        $branch = Branch::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
        ]);

        $result = $this->branchService->assignEmployeesToBranch($branch, $validated['user_ids'], $validated['department_id'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Employees assigned to branch successfully.',
            'data' => $result,
        ]);
    }

    /**
     * Execute record transfer between branches.
     */
    public function transfer(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->current_tenant_id;

        $validated = $request->validate([
            'transferable_type' => ['required', 'string', 'in:lead,employee,project,ticket,meeting,appointment,customer'],
            'transferable_id' => ['required', 'integer'],
            'to_branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'reason' => ['nullable', 'string', 'max:500'],
            'approval_notes' => ['nullable', 'string', 'max:500'],
            'metadata' => ['nullable', 'array'],
        ]);

        $transfer = $this->branchService->transferRecord($validated, $user);

        return response()->json([
            'success' => true,
            'message' => 'Record transferred successfully to new branch.',
            'data' => $transfer->load(['fromBranch', 'toBranch', 'initiatedBy']),
        ]);
    }

    /**
     * Get transfer history audit logs.
     */
    public function transferHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->current_tenant_id;

        $query = BranchTransfer::where('tenant_id', $tenantId)
            ->with(['fromBranch:id,name,code', 'toBranch:id,name,code', 'initiatedBy:id,name,email', 'approvedBy:id,name,email']);

        if ($request->filled('transferable_type')) {
            $query->where('transferable_type', strtolower($request->input('transferable_type')));
        }
        if ($request->filled('branch_id')) {
            $branchId = (int)$request->input('branch_id');
            $query->where(function ($q) use ($branchId) {
                $q->where('from_branch_id', $branchId)
                  ->orWhere('to_branch_id', $branchId);
            });
        }

        $transfers = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $transfers->items(),
            'meta' => [
                'current_page' => $transfers->currentPage(),
                'total' => $transfers->total(),
                'per_page' => $transfers->perPage(),
            ],
        ]);
    }

    /**
     * Branch Manager Dashboard: branch-specific metrics.
     */
    public function dashboard(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->current_tenant_id;

        $branch = Branch::where('tenant_id', $tenantId)->with('manager')->findOrFail($id);
        $this->authorizeBranchAccess($user, $branch);

        $metrics = $this->branchService->getBranchDashboardMetrics($branch);

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    /**
     * Central Owner Consolidated Multi-Branch Dashboard.
     */
    public function consolidated(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->current_tenant_id;

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $departmentId = $request->filled('department_id') ? (int)$request->input('department_id') : null;

        $analytics = $this->branchService->getConsolidatedAnalytics($tenantId, $startDate, $endDate, $departmentId);

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    /**
     * Get branch operational settings.
     */
    public function getSettings(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->current_tenant_id;

        $branch = Branch::where('tenant_id', $tenantId)->findOrFail($id);
        $this->authorizeBranchAccess($user, $branch);

        return response()->json([
            'success' => true,
            'data' => [
                'business_hours' => $branch->business_hours,
                'working_days' => $branch->working_days,
                'connected_channels' => $branch->connected_channels,
                'settings' => $branch->settings,
            ],
        ]);
    }

    /**
     * Update branch operational settings.
     */
    public function updateSettings(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->current_tenant_id;

        $branch = Branch::where('tenant_id', $tenantId)->findOrFail($id);
        $this->authorizeBranchAccess($user, $branch);

        $validated = $request->validate([
            'business_hours' => ['nullable', 'array'],
            'working_days' => ['nullable', 'array'],
            'connected_channels' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ]);

        $branch->update(array_filter($validated, fn($val) => !is_null($val)));

        return response()->json([
            'success' => true,
            'message' => 'Branch settings updated successfully.',
            'data' => [
                'business_hours' => $branch->business_hours,
                'working_days' => $branch->working_days,
                'connected_channels' => $branch->connected_channels,
                'settings' => $branch->settings,
            ],
        ]);
    }

    /**
     * Create or list branch sales targets.
     */
    public function salesTargets(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->current_tenant_id;

        $branch = Branch::where('tenant_id', $tenantId)->findOrFail($id);
        $this->authorizeBranchAccess($user, $branch);

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'period_type' => ['required', 'string', 'in:monthly,quarterly,annual'],
                'period_year' => ['required', 'integer'],
                'period_number' => ['nullable', 'integer'],
                'target_amount' => ['required', 'numeric', 'min:0'],
                'achieved_amount' => ['nullable', 'numeric', 'min:0'],
                'target_leads' => ['nullable', 'integer', 'min:0'],
                'achieved_leads' => ['nullable', 'integer', 'min:0'],
                'target_deals' => ['nullable', 'integer', 'min:0'],
                'achieved_deals' => ['nullable', 'integer', 'min:0'],
                'notes' => ['nullable', 'string', 'max:500'],
            ]);

            $target = BranchSalesTarget::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'branch_id' => $branch->id,
                    'period_type' => $validated['period_type'],
                    'period_year' => $validated['period_year'],
                    'period_number' => $validated['period_number'] ?? null,
                ],
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'Branch sales target saved successfully.',
                'data' => $target,
            ], 201);
        }

        $targets = BranchSalesTarget::where('tenant_id', $tenantId)
            ->where('branch_id', $branch->id)
            ->orderBy('period_year', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $targets,
        ]);
    }

    /**
     * Strict cross-branch authorization check.
     * Prevents branch users from accessing other branch records through modified IDs.
     */
    protected function authorizeBranchAccess($user, Branch $branch): void
    {
        $tenantId = $user->tenant_id ?? $user->current_tenant_id;

        // Organization owner and platform admins have full cross-branch access
        if ($this->rbacService->isOrganizationOwner($user, $tenantId) || $user->is_platform_admin) {
            return;
        }

        $effective = $this->rbacService->getEffectivePermissions($user, $tenantId);
        $modulePerms = $effective['modules']['branches'] ?? null;
        $hasViewAll = isset($modulePerms['actions']['view_all']) || (isset($modulePerms['actions']['*']) && ($modulePerms['actions']['*']['data_scope'] ?? 'all') === 'all');
        $dataScope = $modulePerms['actions']['view']['data_scope'] ?? $modulePerms['actions']['*']['data_scope'] ?? 'branch';

        if (!$hasViewAll || $dataScope === 'branch') {
            if ($user->branch_id && (int)$user->branch_id !== (int)$branch->id) {
                abort(403, 'Unauthorized cross-branch access denied. You are only authorized for branch ID: ' . $user->branch_id);
            }
        }
    }
}
