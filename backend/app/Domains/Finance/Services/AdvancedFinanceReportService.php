<?php

namespace App\Domains\Finance\Services;

use App\Domains\CRM\Models\Deal;
use App\Domains\Finance\Models\Expense;
use App\Domains\Finance\Models\FinanceTransaction;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\Refund;
use App\Domains\Finance\Models\VendorBill;
use App\Domains\Finance\Models\VendorPayment;
use Carbon\Carbon;

class AdvancedFinanceReportService
{
    /**
     * Get comprehensive financial report with strict distinction between Cash Collected, Invoiced Revenue, and Forecast Pipeline.
     */
    public function getComprehensiveReport(int $tenantId): array
    {
        $today = Carbon::today()->toDateString();

        // 1. Invoices & AR
        $activeInvoices = Invoice::where('tenant_id', $tenantId)->whereNotIn('status', ['void', 'cancelled'])->get();
        $totalInvoicedRevenue = (float) $activeInvoices->sum('total_amount');
        $totalCollectedAgainstInvoices = (float) $activeInvoices->sum('paid_amount');
        $totalOutstandingReceivables = (float) $activeInvoices->sum('outstanding_amount');

        // Overdue Invoices
        $overdueInvoices = $activeInvoices->filter(fn ($inv) => in_array($inv->status, ['issued', 'partially_paid']) && $inv->due_date && $inv->due_date->lt($today));
        $overdueAmount = (float) $overdueInvoices->sum('outstanding_amount');

        // 2. Pure Cash Ledger (Actual cash collected from customers)
        $successfulPayments = Payment::where('tenant_id', $tenantId)->where('status', 'successful')->get();
        $totalCashCollected = (float) $successfulPayments->sum('amount');
        $totalRefundsPaid = (float) Refund::where('tenant_id', $tenantId)->where('status', 'processed')->sum('amount');
        $netCashCollected = round($totalCashCollected - $totalRefundsPaid, 2);

        // 3. Accounts Payable (AP) & Outgoing Cash
        $activeBills = VendorBill::where('tenant_id', $tenantId)->whereNotIn('status', ['cancelled'])->get();
        $totalBilledPayables = (float) $activeBills->sum('total_amount');
        $totalPaidToVendors = (float) VendorPayment::where('tenant_id', $tenantId)->where('status', 'disbursed')->sum('amount');
        $totalOutstandingPayables = (float) $activeBills->sum('outstanding_amount');

        // 4. Expenses
        $paidExpenses = (float) Expense::where('tenant_id', $tenantId)->whereIn('status', ['paid', 'reimbursed'])->sum('amount');
        $pendingExpenses = (float) Expense::where('tenant_id', $tenantId)->whereIn('status', ['pending_approval', 'approved'])->sum('amount');

        // 5. Forecast Pipeline Revenue from CRM Deals (Distinct from actual revenue!)
        $openDeals = Deal::where('tenant_id', $tenantId)->whereNotIn('stage', ['closed_won', 'closed_lost'])->get();
        $forecastPipelineValue = (float) $openDeals->sum('value');

        // 6. Cash Flow Calculation (Inflow vs Outflow)
        $totalCashInflow = $totalCashCollected;
        $totalCashOutflow = round($totalPaidToVendors + $paidExpenses + $totalRefundsPaid, 2);
        $netCashFlow = round($totalCashInflow - $totalCashOutflow, 2);

        // 7. Payment Methods Breakdown
        $paymentMethods = [];
        foreach ($successfulPayments as $p) {
            $m = strtolower($p->payment_method ?: 'other');
            $paymentMethods[$m] = round(($paymentMethods[$m] ?? 0.0) + (float) $p->amount, 2);
        }

        // 8. Financial Reconciliation & Ledger Integrity
        $totalPostedTransactions = FinanceTransaction::where('tenant_id', $tenantId)->where('is_posted', true)->count();
        $reconciledCount = FinanceTransaction::where('tenant_id', $tenantId)->where('reconciliation_status', 'matched')->count();

        return [
            // Prominent distinction banner
            'revenue_vs_cash_metrics' => [
                'cash_collected' => round($totalCashCollected, 2),
                'net_cash_collected' => $netCashCollected,
                'invoiced_revenue' => round($totalInvoicedRevenue, 2),
                'forecast_pipeline_revenue' => round($forecastPipelineValue, 2),
                'variance_invoiced_vs_collected' => round($totalInvoicedRevenue - $totalCashCollected, 2),
                'accounting_notice' => 'Cash collected reflects confirmed liquid bank/gateway deposits. Invoiced revenue reflects issued legal tax demands. Forecast pipeline reflects unclosed opportunity probabilistic estimates.',
            ],
            'receivables_ar' => [
                'total_receivables' => round($totalOutstandingReceivables, 2),
                'overdue_receivables' => round($overdueAmount, 2),
                'overdue_count' => $overdueInvoices->count(),
            ],
            'payables_ap' => [
                'total_billed' => round($totalBilledPayables, 2),
                'total_disbursed' => round($totalPaidToVendors, 2),
                'total_outstanding' => round($totalOutstandingPayables, 2),
            ],
            'expenses' => [
                'paid_expenses' => round($paidExpenses, 2),
                'pending_expenses' => round($pendingExpenses, 2),
            ],
            'refunds' => [
                'total_refunded' => round($totalRefundsPaid, 2),
            ],
            'cash_flow' => [
                'total_inflow' => round($totalCashInflow, 2),
                'total_outflow' => round($totalCashOutflow, 2),
                'net_cash_flow' => $netCashFlow,
            ],
            'payment_methods_breakdown' => $paymentMethods,
            'ledger_integrity' => [
                'posted_transactions_count' => $totalPostedTransactions,
                'reconciled_transactions_count' => $reconciledCount,
                'unposted_deletions_prohibited' => true,
            ],
        ];
    }
}
