<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Finance\Models\Expense;
use App\Domains\Finance\Services\ExpenseService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected ExpenseService $expenseService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * List expenses.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $query = Expense::where('tenant_id', $tenantId)
            ->with(['supplier', 'employee', 'approvedByUser'])
            ->orderBy('id', 'desc');

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('is_reimbursement')) {
            $query->where('is_reimbursement', filter_var($request->query('is_reimbursement'), FILTER_VALIDATE_BOOLEAN));
        }

        $expenses = $query->paginate($request->query('per_page', 15));

        return $this->paginatedResponse($expenses, 'Expenses retrieved.');
    }

    /**
     * Create new expense or reimbursement request.
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:50',
            'supplier_id' => 'nullable|integer',
            'expense_date' => 'nullable|date',
            'amount' => 'required|numeric|min:0.01',
            'tax_amount' => 'nullable|numeric|min:0',
            'is_tax_inclusive' => 'boolean',
            'currency' => 'nullable|string|max:10',
            'receipt_url' => 'nullable|string|max:500',
            'is_reimbursement' => 'boolean',
            'employee_user_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'status' => 'nullable|string|in:draft,pending_approval,approved',
        ]);

        $expense = $this->expenseService->createExpense($tenantId, $validated, $request->user());

        return $this->successResponse($expense, 'Expense recorded successfully.');
    }

    /**
     * Show expense details.
     */
    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $expense = Expense::where('tenant_id', $tenantId)
            ->with(['supplier', 'employee', 'approvedByUser'])
            ->findOrFail($id);

        return $this->successResponse($expense, 'Expense details retrieved.');
    }

    /**
     * Approve expense.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $expense = $this->expenseService->approveExpense($tenantId, $id, $request->user());

        return $this->successResponse($expense, 'Expense approved successfully.');
    }

    /**
     * Reject expense.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $expense = $this->expenseService->rejectExpense($tenantId, $id, $validated['reason'], $request->user());

        return $this->successResponse($expense, 'Expense rejected.');
    }

    /**
     * Disburse payment for expense / reimburse employee.
     */
    public function pay(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'payment_method' => 'nullable|string|max:50',
            'payment_reference' => 'nullable|string|max:100',
        ]);

        $expense = $this->expenseService->markPaid($tenantId, $id, $validated, $request->user());

        return $this->successResponse($expense, 'Expense marked as paid / reimbursed.');
    }

    /**
     * Expense analytics report.
     */
    public function report(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $report = $this->expenseService->getExpenseReport($tenantId);

        return $this->successResponse($report, 'Expense analytics retrieved.');
    }
}
