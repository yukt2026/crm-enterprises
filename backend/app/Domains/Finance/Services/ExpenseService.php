<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\Expense;
use App\Domains\Finance\Models\FinanceTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    /**
     * Create an expense entry.
     */
    public function createExpense(int $tenantId, array $data, ?User $user = null): Expense
    {
        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Expense amount must be greater than zero.']);
        }

        $taxAmount = round((float) ($data['tax_amount'] ?? 0), 2);
        $expenseNo = 'EXP-' . date('Ym') . '-' . strtoupper(Str::random(6));

        return Expense::create([
            'tenant_id' => $tenantId,
            'expense_number' => $expenseNo,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? 'office_supplies',
            'supplier_id' => $data['supplier_id'] ?? null,
            'expense_date' => $data['expense_date'] ?? Carbon::now()->toDateString(),
            'amount' => $amount,
            'tax_amount' => $taxAmount,
            'is_tax_inclusive' => (bool) ($data['is_tax_inclusive'] ?? false),
            'currency' => $data['currency'] ?? 'INR',
            'receipt_url' => $data['receipt_url'] ?? null,
            'is_reimbursement' => (bool) ($data['is_reimbursement'] ?? false),
            'employee_user_id' => $data['employee_user_id'] ?? ($data['is_reimbursement'] ? $user?->id : null),
            'status' => $data['status'] ?? 'pending_approval',
            'branch_id' => $data['branch_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'payment_reference' => $data['payment_reference'] ?? null,
        ]);
    }

    /**
     * Approve expense for reimbursement/payout.
     */
    public function approveExpense(int $tenantId, int $expenseId, ?User $approver = null): Expense
    {
        $expense = Expense::where('tenant_id', $tenantId)->findOrFail($expenseId);
        $expense->update([
            'status' => 'approved',
            'approved_by_user_id' => $approver?->id,
            'approved_at' => Carbon::now(),
            'rejection_reason' => null,
        ]);

        return $expense->fresh(['supplier', 'employee', 'approvedByUser']);
    }

    /**
     * Reject expense with reason.
     */
    public function rejectExpense(int $tenantId, int $expenseId, string $reason, ?User $approver = null): Expense
    {
        $expense = Expense::where('tenant_id', $tenantId)->findOrFail($expenseId);
        $expense->update([
            'status' => 'rejected',
            'approved_by_user_id' => $approver?->id,
            'approved_at' => Carbon::now(),
            'rejection_reason' => $reason,
        ]);

        return $expense->fresh(['supplier', 'employee', 'approvedByUser']);
    }

    /**
     * Mark approved expense as paid/reimbursed.
     */
    public function markPaid(int $tenantId, int $expenseId, array $payoutData, ?User $user = null): Expense
    {
        return DB::transaction(function () use ($tenantId, $expenseId, $payoutData, $user) {
            $expense = Expense::where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($expenseId);

            if ($expense->status === 'rejected') {
                throw ValidationException::withMessages(['expense' => 'Cannot disburse payment for a rejected expense.']);
            }

            $method = $payoutData['payment_method'] ?? 'bank_transfer';
            $ref = $payoutData['payment_reference'] ?? ('UTR' . strtoupper(Str::random(10)));

            $expense->update([
                'status' => $expense->is_reimbursement ? 'reimbursed' : 'paid',
                'payment_method' => $method,
                'payment_reference' => $ref,
                'paid_at' => Carbon::now(),
            ]);

            // Record cash outflow transaction
            FinanceTransaction::create([
                'tenant_id' => $tenantId,
                'transaction_number' => 'TXN-EXP-' . strtoupper(Str::random(8)),
                'internal_transaction_id' => (string) Str::uuid(),
                'provider_transaction_id' => $ref,
                'gateway' => $method,
                'payment_method' => $method,
                'amount' => -$expense->amount, // negative cash outflow
                'currency' => $expense->currency,
                'fee_amount' => 0.0,
                'tax_on_fee' => 0.0,
                'net_amount' => -$expense->amount,
                'transaction_date' => Carbon::now()->toDateString(),
                'provider_status' => 'captured',
                'internal_status' => 'posted',
                'reconciliation_status' => 'matched',
                'reconciled_at' => Carbon::now(),
                'is_posted' => true,
                'notes' => "Disbursed expense {$expense->expense_number}: {$expense->title}",
            ]);

            return $expense->fresh(['supplier', 'employee', 'approvedByUser']);
        });
    }

    /**
     * Get Expense Report breakdown.
     */
    public function getExpenseReport(int $tenantId): array
    {
        $expenses = Expense::where('tenant_id', $tenantId)->get();

        $totalExpenses = 0.0;
        $totalPaid = 0.0;
        $totalPending = 0.0;
        $byCategory = [];
        $byStatus = [];

        foreach ($expenses as $exp) {
            $amt = (float) $exp->amount;
            $totalExpenses += $amt;

            if (in_array($exp->status, ['paid', 'reimbursed'])) {
                $totalPaid += $amt;
            } elseif (in_array($exp->status, ['draft', 'pending_approval', 'approved'])) {
                $totalPending += $amt;
            }

            $cat = $exp->category ?: 'other';
            $byCategory[$cat] = ($byCategory[$cat] ?? 0.0) + $amt;

            $st = $exp->status;
            $byStatus[$st] = ($byStatus[$st] ?? 0.0) + $amt;
        }

        return [
            'total_expenses' => round($totalExpenses, 2),
            'total_paid' => round($totalPaid, 2),
            'total_pending' => round($totalPending, 2),
            'by_category' => $byCategory,
            'by_status' => $byStatus,
            'count' => $expenses->count(),
        ];
    }
}
