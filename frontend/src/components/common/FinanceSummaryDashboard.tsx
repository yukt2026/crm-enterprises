import React from 'react';
import {
  DollarSign,
  TrendingUp,
  AlertCircle,
  Clock,
  ArrowUpRight,
  ShieldCheck,
  CreditCard,
  FileText,
} from 'lucide-react';
import { useFinanceSummary } from '../../hooks/useFinance';

interface FinanceSummaryDashboardProps {
  theme?: 'light' | 'dark';
  onNavigateTab?: (tab: string) => void;
}

export default function FinanceSummaryDashboard({ theme = 'dark', onNavigateTab }: FinanceSummaryDashboardProps) {
  const { data: summary, isLoading } = useFinanceSummary();

  const isDark = theme === 'dark';

  if (isLoading) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 animate-pulse">
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className={`h-28 rounded-xl ${isDark ? 'bg-slate-800/60' : 'bg-slate-100'}`} />
        ))}
      </div>
    );
  }

  const totalInvoiced = summary?.total_invoiced ?? 0;
  const totalCollected = summary?.total_collected ?? 0;
  const totalReceivables = summary?.total_receivables ?? 0;
  const overdueAmount = summary?.overdue_amount ?? 0;
  const overdueCount = summary?.overdue_count ?? 0;

  const collectionRate = totalInvoiced > 0 ? Math.min(100, Math.round((totalCollected / totalInvoiced) * 100)) : 0;

  return (
    <div className="space-y-4">
      {/* 4 Primary KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Total Invoiced */}
        <div
          onClick={() => onNavigateTab && onNavigateTab('invoices')}
          className={`p-4 rounded-xl border transition-all cursor-pointer hover:shadow-md ${
            isDark
              ? 'bg-slate-900/50 border-slate-800 hover:border-slate-700'
              : 'bg-white border-slate-200 hover:border-slate-300'
          }`}
        >
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Invoiced</span>
            <div className="p-2 rounded-lg bg-blue-500/10 text-blue-500">
              <FileText className="w-4 h-4" />
            </div>
          </div>
          <div className="mt-2 flex items-baseline justify-between">
            <span className="text-2xl font-bold font-mono tracking-tight text-slate-900 dark:text-white">
              ₹{totalInvoiced.toLocaleString('en-IN', { maximumFractionDigits: 2 })}
            </span>
          </div>
          <div className="mt-2 text-xs text-slate-500 flex items-center gap-1">
            <span>Cumulative gross billing</span>
          </div>
        </div>

        {/* Total Collected */}
        <div
          onClick={() => onNavigateTab && onNavigateTab('payments')}
          className={`p-4 rounded-xl border transition-all cursor-pointer hover:shadow-md ${
            isDark
              ? 'bg-slate-900/50 border-slate-800 hover:border-slate-700'
              : 'bg-white border-slate-200 hover:border-slate-300'
          }`}
        >
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Collected</span>
            <div className="p-2 rounded-lg bg-emerald-500/10 text-emerald-500">
              <ShieldCheck className="w-4 h-4" />
            </div>
          </div>
          <div className="mt-2 flex items-baseline justify-between">
            <span className="text-2xl font-bold font-mono tracking-tight text-emerald-600 dark:text-emerald-400">
              ₹{totalCollected.toLocaleString('en-IN', { maximumFractionDigits: 2 })}
            </span>
            <span className="text-xs font-bold text-emerald-500 bg-emerald-500/10 px-1.5 py-0.5 rounded">
              {collectionRate}%
            </span>
          </div>
          <div className="mt-2 text-xs text-slate-500 flex items-center gap-1">
            <span>Verified payments settled</span>
          </div>
        </div>

        {/* Total Receivables */}
        <div
          onClick={() => onNavigateTab && onNavigateTab('invoices')}
          className={`p-4 rounded-xl border transition-all cursor-pointer hover:shadow-md ${
            isDark
              ? 'bg-slate-900/50 border-slate-800 hover:border-slate-700'
              : 'bg-white border-slate-200 hover:border-slate-300'
          }`}
        >
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">Outstanding Receivables</span>
            <div className="p-2 rounded-lg bg-amber-500/10 text-amber-500">
              <Clock className="w-4 h-4" />
            </div>
          </div>
          <div className="mt-2 flex items-baseline justify-between">
            <span className="text-2xl font-bold font-mono tracking-tight text-amber-600 dark:text-amber-400">
              ₹{totalReceivables.toLocaleString('en-IN', { maximumFractionDigits: 2 })}
            </span>
          </div>
          <div className="mt-2 text-xs text-slate-500 flex items-center gap-1">
            <span>Pending customer payment</span>
          </div>
        </div>

        {/* Overdue Receivables */}
        <div
          onClick={() => onNavigateTab && onNavigateTab('invoices')}
          className={`p-4 rounded-xl border transition-all cursor-pointer hover:shadow-md ${
            isDark
              ? 'bg-slate-900/50 border-slate-800 hover:border-slate-700'
              : 'bg-white border-slate-200 hover:border-slate-300'
          }`}
        >
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">Overdue Invoices</span>
            <div className="p-2 rounded-lg bg-rose-500/10 text-rose-500">
              <AlertCircle className="w-4 h-4" />
            </div>
          </div>
          <div className="mt-2 flex items-baseline justify-between">
            <span className="text-2xl font-bold font-mono tracking-tight text-rose-600 dark:text-rose-400">
              ₹{overdueAmount.toLocaleString('en-IN', { maximumFractionDigits: 2 })}
            </span>
            {overdueCount > 0 && (
              <span className="text-xs font-bold text-rose-500 bg-rose-500/10 px-1.5 py-0.5 rounded">
                {overdueCount} bills
              </span>
            )}
          </div>
          <div className="mt-2 text-xs text-slate-500 flex items-center gap-1">
            <span>Past scheduled due date</span>
          </div>
        </div>
      </div>
    </div>
  );
}
