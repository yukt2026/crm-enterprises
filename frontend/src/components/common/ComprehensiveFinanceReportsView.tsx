import React from 'react';
import {
  PieChart,
  TrendingUp,
  DollarSign,
  ShieldCheck,
  ArrowUpRight,
  ArrowDownRight,
  Scale,
  FileSpreadsheet,
  AlertCircle,
  Activity,
} from 'lucide-react';
import { useComprehensiveFinanceReport } from '../../hooks/useFinance';

interface ComprehensiveFinanceReportsViewProps {
  theme?: 'light' | 'dark';
}

export default function ComprehensiveFinanceReportsView({
  theme = 'dark',
}: ComprehensiveFinanceReportsViewProps) {
  const isDark = theme === 'dark';
  const { data: report, isLoading, refetch } = useComprehensiveFinanceReport();

  const metrics = report?.revenue_vs_cash_metrics || {
    cash_collected: 0,
    net_cash_collected: 0,
    invoiced_revenue: 0,
    forecast_pipeline_revenue: 0,
    variance_invoiced_vs_collected: 0,
    accounting_notice:
      'Cash collected reflects confirmed liquid deposits. Invoiced revenue reflects issued legal tax demands. Forecast pipeline reflects unclosed opportunity probabilistic estimates.',
  };

  const cashFlow = report?.cash_flow || {
    total_inflow: 0,
    total_outflow: 0,
    net_cash_flow: 0,
  };

  const ledger = report?.ledger_integrity || {
    posted_transactions_count: 0,
    reconciled_transactions_count: 0,
    unposted_deletions_prohibited: true,
  };

  const paymentMethods = report?.payment_methods_breakdown || {};

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h2 className="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
          <PieChart className="w-5 h-5 text-emerald-500" />
          Enterprise Financial Intelligence & Cash-Flow Ledger
        </h2>
        <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          Audited financial reconciliation strictly distinguishing liquid cash collected from invoiced and pipeline revenue.
        </p>
      </div>

      {/* Accounting Distinction Banner */}
      <div className="p-4 rounded-2xl border border-indigo-500/20 bg-indigo-50/50 dark:bg-indigo-950/20 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div className="flex items-start gap-3">
          <div className="p-2 rounded-xl bg-indigo-600 text-white mt-0.5 shadow-sm">
            <Scale className="w-5 h-5" />
          </div>
          <div>
            <h4 className="text-xs font-bold text-indigo-950 dark:text-indigo-200 uppercase tracking-wide">
              Accounting Governance Standard
            </h4>
            <p className="text-xs text-indigo-900/80 dark:text-indigo-300/80 mt-0.5 max-w-3xl leading-relaxed">
              {metrics.accounting_notice}
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
            <ShieldCheck className="w-4 h-4" /> GAAP / Ind AS Compliant
          </span>
        </div>
      </div>

      {/* Revenue vs Cash Distinctions 3-Pillar Comparison */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {/* Pillar 1: Cash Collected */}
        <div className="p-5 rounded-2xl border-2 border-emerald-500/30 bg-emerald-500/5 dark:bg-emerald-500/10 shadow-sm relative overflow-hidden">
          <div className="flex items-center justify-between">
            <span className="text-[11px] font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">
              1. Actual Cash Collected
            </span>
            <span className="p-1.5 rounded-lg bg-emerald-500 text-white">
              <ArrowDownRight className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <div className="text-2xl font-black text-slate-900 dark:text-white font-mono">
              ₹{metrics.cash_collected.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
            </div>
            <p className="text-xs text-emerald-600 dark:text-emerald-400 font-semibold mt-1">
              Net Confirmed: ₹{metrics.net_cash_collected.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
            </p>
            <p className="text-[10px] text-slate-500 dark:text-slate-400 mt-2">
              Liquid funds in tenant bank & verified payment gateway accounts.
            </p>
          </div>
        </div>

        {/* Pillar 2: Invoiced Revenue */}
        <div className="p-5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm relative overflow-hidden">
          <div className="flex items-center justify-between">
            <span className="text-[11px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider">
              2. Total Invoiced Revenue
            </span>
            <span className="p-1.5 rounded-lg bg-blue-500 text-white">
              <FileSpreadsheet className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <div className="text-2xl font-black text-slate-900 dark:text-white font-mono">
              ₹{metrics.invoiced_revenue.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
            </div>
            <p className="text-xs text-blue-600 dark:text-blue-400 font-semibold mt-1">
              Variance (Uncollected): ₹
              {metrics.variance_invoiced_vs_collected.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
            </p>
            <p className="text-[10px] text-slate-500 dark:text-slate-400 mt-2">
              Legally binding tax invoices issued with pending accounts receivable.
            </p>
          </div>
        </div>

        {/* Pillar 3: Forecast Revenue */}
        <div className="p-5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm relative overflow-hidden">
          <div className="flex items-center justify-between">
            <span className="text-[11px] font-bold text-purple-600 dark:text-purple-400 uppercase tracking-wider">
              3. CRM Forecast Pipeline
            </span>
            <span className="p-1.5 rounded-lg bg-purple-500 text-white">
              <TrendingUp className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <div className="text-2xl font-black text-slate-900 dark:text-white font-mono">
              ₹{metrics.forecast_pipeline_revenue.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
            </div>
            <p className="text-xs text-purple-600 dark:text-purple-400 font-semibold mt-1">
              Unclosed Sales Opportunities
            </p>
            <p className="text-[10px] text-slate-500 dark:text-slate-400 mt-2">
              Probabilistic sales expectations; not recognized in accounting ledger.
            </p>
          </div>
        </div>
      </div>

      {/* Cash Flow Statement & Ledger Integrity */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {/* Cash Flow */}
        <div className="p-5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
          <div className="flex items-center justify-between border-b pb-3 dark:border-slate-800">
            <h3 className="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-2">
              <Activity className="w-4 h-4 text-emerald-500" />
              Actual Cash Flow Statement
            </h3>
            <span
              className={`text-xs font-bold font-mono px-2.5 py-1 rounded-lg ${
                cashFlow.net_cash_flow >= 0
                  ? 'bg-emerald-500/10 text-emerald-500'
                  : 'bg-rose-500/10 text-rose-500'
              }`}
            >
              Net: ₹{cashFlow.net_cash_flow.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
            </span>
          </div>

          <div className="divide-y divide-slate-100 dark:divide-slate-800 text-xs mt-3">
            <div className="py-2.5 flex justify-between items-center">
              <span className="text-slate-600 dark:text-slate-400 flex items-center gap-1.5">
                <ArrowDownRight className="w-3.5 h-3.5 text-emerald-500" /> Total Inflow (Customer Collections)
              </span>
              <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                +₹{cashFlow.total_inflow.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
              </span>
            </div>

            <div className="py-2.5 flex justify-between items-center">
              <span className="text-slate-600 dark:text-slate-400 flex items-center gap-1.5">
                <ArrowUpRight className="w-3.5 h-3.5 text-rose-500" /> Total Outflow (Vendors, Expenses & Refunds)
              </span>
              <span className="font-mono font-bold text-rose-600 dark:text-rose-400">
                -₹{cashFlow.total_outflow.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
              </span>
            </div>

            <div className="py-3 flex justify-between items-center font-bold">
              <span className="text-slate-900 dark:text-white">Net Liquid Working Capital</span>
              <span
                className={`font-mono text-sm ${
                  cashFlow.net_cash_flow >= 0 ? 'text-emerald-500' : 'text-rose-500'
                }`}
              >
                ₹{cashFlow.net_cash_flow.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
              </span>
            </div>
          </div>
        </div>

        {/* Ledger Integrity & Payment Methods */}
        <div className="p-5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
          <div className="flex items-center justify-between border-b pb-3 dark:border-slate-800">
            <h3 className="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-2">
              <ShieldCheck className="w-4 h-4 text-indigo-500" />
              General Transaction Ledger Integrity
            </h3>
            <span className="text-[10px] px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-500 font-semibold">
              Immutable Records
            </span>
          </div>

          <div className="space-y-3 mt-3 text-xs">
            <div className="flex justify-between items-center">
              <span className="text-slate-500 dark:text-slate-400">Posted Financial Transactions</span>
              <span className="font-mono font-bold text-slate-900 dark:text-white">
                {ledger.posted_transactions_count} posted
              </span>
            </div>

            <div className="flex justify-between items-center">
              <span className="text-slate-500 dark:text-slate-400">Reconciled Transactions</span>
              <span className="font-mono font-bold text-emerald-500">
                {ledger.reconciled_transactions_count} matched
              </span>
            </div>

            <div className="flex justify-between items-center">
              <span className="text-slate-500 dark:text-slate-400">Audit Rule (No Hard Deletes)</span>
              <span className="text-emerald-500 font-semibold">Reversal Entries Enforced</span>
            </div>

            <div className="pt-2 border-t dark:border-slate-800">
              <span className="text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wide block mb-2">
                Collections by Gateway / Channel
              </span>
              <div className="flex flex-wrap gap-2">
                {Object.keys(paymentMethods).length === 0 ? (
                  <span className="text-[11px] text-slate-400">No transactions recorded yet</span>
                ) : (
                  Object.entries(paymentMethods).map(([method, amount]) => (
                    <span
                      key={method}
                      className="px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-[10px] font-semibold text-slate-700 dark:text-slate-300 uppercase"
                    >
                      {method}: ₹{amount.toLocaleString('en-IN')}
                    </span>
                  ))
                )}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
