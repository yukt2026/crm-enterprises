<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Analytics\Models\FieldSalesVisit;
use App\Domains\Analytics\Services\FieldSalesService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FieldSalesController extends Controller
{
    public function __construct(
        protected FieldSalesService $fieldSalesService
    ) {}

    /**
     * List Field Sales Visits with Filtering
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);

        $query = FieldSalesVisit::where('tenant_id', $tenantId)
            ->with(['employee', 'customer', 'lead', 'branch', 'followUpTask'])
            ->orderBy('scheduled_at', 'desc');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', (int)$request->employee_id);
        }
        if ($request->filled('branch_id')) {
            $query->where('branch_id', (int)$request->branch_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date')) {
            $query->whereDate('scheduled_at', $request->date);
        }

        $visits = $query->paginate((int)$request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $visits->items(),
            'meta' => [
                'current_page' => $visits->currentPage(),
                'last_page' => $visits->lastPage(),
                'total' => $visits->total(),
            ],
        ]);
    }

    /**
     * Schedule a New Customer Field Visit
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);

        if (!$request->has('employee_id')) {
            $emp = \App\Domains\HR\Models\Employee::where('tenant_id', $tenantId)->first();
            $request->merge(['employee_id' => $emp ? $emp->id : 1]);
        }
        if (!$request->has('scheduled_at') && $request->has('planned_at')) {
            $request->merge(['scheduled_at' => $request->input('planned_at')]);
        }
        if (!$request->has('scheduled_at')) {
            $request->merge(['scheduled_at' => now()->addHour()]);
        }

        $validated = $request->validate([
            'employee_id' => 'required|integer',
            'customer_id' => 'nullable|integer',
            'lead_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer',
            'title' => 'required|string|max:180',
            'scheduled_at' => 'required|date',
            'route_waypoints' => 'nullable|array',
            'offline_client_uuid' => 'nullable|string|max:64',
        ]);

        $visit = $this->fieldSalesService->scheduleVisit($tenantId, $validated, $request->user());

        return response()->json([
            'success' => true,
            'data' => $visit->load(['employee', 'customer', 'lead', 'branch']),
            'message' => 'Customer field visit scheduled successfully.',
        ], 201);
    }

    /**
     * Transparent, User-Initiated GPS Check-In
     */
    public function checkIn(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);

        $visit = FieldSalesVisit::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'address' => 'nullable|string|max:255',
        ]);

        $updated = $this->fieldSalesService->checkIn(
            $visit,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            $validated['address'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => $updated,
            'message' => 'Checked in successfully with verified GPS location.',
        ]);
    }

    /**
     * Transparent, User-Initiated GPS Check-Out with Outcome, Notes, Follow-up & Expense
     */
    public function checkOut(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);

        $visit = FieldSalesVisit::where('tenant_id', $tenantId)->findOrFail($id);

        if ($request->has('latitude') && !$request->has('check_out_lat')) {
            $request->merge(['check_out_lat' => $request->input('latitude')]);
        }
        if ($request->has('longitude') && !$request->has('check_out_lng')) {
            $request->merge(['check_out_lng' => $request->input('longitude')]);
        }
        if ($request->has('outcome_notes') && !$request->has('visit_notes')) {
            $request->merge(['visit_notes' => $request->input('outcome_notes')]);
        }
        if ($request->input('outcome') === 'completed') {
            $request->merge(['outcome' => 'deal_closed']);
        }

        $validated = $request->validate([
            'check_out_lat' => 'nullable|numeric',
            'check_out_lng' => 'nullable|numeric',
            'check_out_address' => 'nullable|string|max:255',
            'distance_travelled_km' => 'nullable|numeric|min:0',
            'visit_notes' => 'required|string',
            'outcome' => 'required|string|in:deal_closed,followup_required,not_interested,product_demo,payment_collected,rescheduled,completed',
            'follow_up_date' => 'nullable|date',
            'travel_expense_amount' => 'nullable|numeric|min:0',
        ]);

        $updated = $this->fieldSalesService->checkOut($visit, $validated, $request->user());

        return response()->json([
            'success' => true,
            'data' => $updated,
            'message' => 'Visit completed and checked out. Tasks and expense claims logged.',
        ]);
    }

    /**
     * Daily Visit Plan for Current Employee
     */
    public function dailyPlan(Request $request): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);
        $employeeId = (int) $request->get('employee_id', 1);
        $date = $request->get('date');

        $plan = $this->fieldSalesService->getDailyPlan($tenantId, $employeeId, $date);

        return response()->json([
            'success' => true,
            'data' => $plan,
        ]);
    }

    /**
     * Field Sales Analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);

        $filters = [
            'from_date' => $request->get('from_date'),
            'to_date' => $request->get('to_date'),
            'branch_id' => $request->get('branch_id'),
            'employee_id' => $request->get('employee_id'),
        ];

        $analytics = $this->fieldSalesService->getFieldSalesAnalytics($tenantId, $filters);

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    /**
     * Safe Offline Drafts Synchronization
     */
    public function syncOffline(Request $request): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);

        $drafts = $request->input('drafts', []);
        $emp = \App\Domains\HR\Models\Employee::where('tenant_id', $tenantId)->first();
        $defaultEmpId = $emp ? $emp->id : 1;

        foreach ($drafts as &$d) {
            if (isset($d['draft_uuid']) && !isset($d['offline_client_uuid'])) {
                $d['offline_client_uuid'] = $d['draft_uuid'];
            }
            if (!isset($d['employee_id'])) {
                $d['employee_id'] = $defaultEmpId;
            }
            if (!isset($d['scheduled_at'])) {
                $d['scheduled_at'] = now()->toDateTimeString();
            }
        }
        unset($d);
        $request->merge(['drafts' => $drafts]);

        $validated = $request->validate([
            'drafts' => 'required|array',
            'drafts.*.offline_client_uuid' => 'required|string',
            'drafts.*.employee_id' => 'required|integer',
            'drafts.*.title' => 'required|string',
            'drafts.*.scheduled_at' => 'required|date',
        ]);

        $result = $this->fieldSalesService->syncOfflineDrafts($tenantId, $validated['drafts'], $request->user());

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
