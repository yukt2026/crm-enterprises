import React, { useState } from 'react';
import {
  FileText,
  Receipt,
  ShieldCheck,
  CreditCard,
  Link2,
  TrendingDown,
  Building,
  PieChart,
} from 'lucide-react';
import FinanceSummaryDashboard from './FinanceSummaryDashboard';
import QuotationsManagerView from './QuotationsManagerView';
import InvoicesManagerView from './InvoicesManagerView';
import PaymentsTransactionsView from './PaymentsTransactionsView';
import PaymentLinksManagerView from './PaymentLinksManagerView';
import AccountsReceivableView from './AccountsReceivableView';
import AccountsPayableView from './AccountsPayableView';
import ExpensesManagerView from './ExpensesManagerView';
import ComprehensiveFinanceReportsView from './ComprehensiveFinanceReportsView';
import { GstComplianceHubView } from './GstComplianceHubView';

interface FinanceBillingHubViewProps {
  theme?: 'light' | 'dark';
  defaultTab?: 'invoices' | 'quotations' | 'payments' | 'payment_links' | 'receivables' | 'payables' | 'expenses' | 'reports' | 'gst';
}

export default function FinanceBillingHubView({
  theme = 'dark',
  defaultTab = 'invoices',
}: FinanceBillingHubViewProps) {
  const [activeTab, setActiveTab] = useState<
    'invoices' | 'quotations' | 'payments' | 'payment_links' | 'receivables' | 'payables' | 'expenses' | 'reports' | 'gst'
  >(defaultTab);

  return (
    <div className="space-y-6">
      {/* Financial Executive KPI Summary at Top */}
      <FinanceSummaryDashboard
        theme={theme}
        onNavigateTab={(tab) => {
          if (
            tab === 'invoices' ||
            tab === 'quotations' ||
            tab === 'payments' ||
            tab === 'payment_links' ||
            tab === 'receivables' ||
            tab === 'payables' ||
            tab === 'expenses' ||
            tab === 'reports'
          ) {
            setActiveTab(tab);
          }
        }}
      />

      {/* Segmented Navigation Tabs */}
      <div className="flex items-center justify-between border-b pb-2 dark:border-slate-800 overflow-x-auto">
        <div className="flex items-center gap-1.5 p-1 rounded-xl bg-slate-100 dark:bg-slate-800/80 min-w-max">
          <button
            onClick={() => setActiveTab('invoices')}
            className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold transition ${
              activeTab === 'invoices'
                ? 'bg-white dark:bg-slate-900 text-emerald-600 dark:text-emerald-400 shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            }`}
          >
            <Receipt className="w-3.5 h-3.5" /> Invoices
          </button>

          <button
            onClick={() => setActiveTab('quotations')}
            className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold transition ${
              activeTab === 'quotations'
                ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            }`}
          >
            <FileText className="w-3.5 h-3.5" /> Quotes
          </button>

          <button
            onClick={() => setActiveTab('payments')}
            className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold transition ${
              activeTab === 'payments'
                ? 'bg-white dark:bg-slate-900 text-purple-600 dark:text-purple-400 shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            }`}
          >
            <ShieldCheck className="w-3.5 h-3.5" /> Ledger
          </button>

          <button
            onClick={() => setActiveTab('payment_links')}
            className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold transition ${
              activeTab === 'payment_links'
                ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            }`}
          >
            <Link2 className="w-3.5 h-3.5" /> Payment Links & UPI QR
          </button>

          <button
            onClick={() => setActiveTab('receivables')}
            className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold transition ${
              activeTab === 'receivables'
                ? 'bg-white dark:bg-slate-900 text-rose-600 dark:text-rose-400 shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            }`}
          >
            <TrendingDown className="w-3.5 h-3.5" /> Receivables (AR)
          </button>

          <button
            onClick={() => setActiveTab('payables')}
            className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold transition ${
              activeTab === 'payables'
                ? 'bg-white dark:bg-slate-900 text-amber-600 dark:text-amber-400 shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            }`}
          >
            <Building className="w-3.5 h-3.5" /> Payables (AP)
          </button>

          <button
            onClick={() => setActiveTab('expenses')}
            className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold transition ${
              activeTab === 'expenses'
                ? 'bg-white dark:bg-slate-900 text-pink-600 dark:text-pink-400 shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            }`}
          >
            <CreditCard className="w-3.5 h-3.5" /> Expenses
          </button>

          <button
            onClick={() => setActiveTab('reports')}
            className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold transition ${
              activeTab === 'reports'
                ? 'bg-white dark:bg-slate-900 text-emerald-600 dark:text-emerald-400 shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            }`}
          >
            <PieChart className="w-3.5 h-3.5" /> Cash-Flow BI
          </button>

          <button
            onClick={() => setActiveTab('gst')}
            className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold transition ${
              activeTab === 'gst'
                ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            }`}
          >
            <ShieldCheck className="w-3.5 h-3.5" /> GST & Tax Compliance
          </button>
        </div>
      </div>

      {/* Active Tab View */}
      {activeTab === 'invoices' && <InvoicesManagerView theme={theme} />}
      {activeTab === 'quotations' && <QuotationsManagerView theme={theme} />}
      {activeTab === 'payments' && <PaymentsTransactionsView theme={theme} />}
      {activeTab === 'payment_links' && <PaymentLinksManagerView theme={theme} />}
      {activeTab === 'receivables' && <AccountsReceivableView theme={theme} />}
      {activeTab === 'payables' && <AccountsPayableView theme={theme} />}
      {activeTab === 'expenses' && <ExpensesManagerView theme={theme} />}
      {activeTab === 'reports' && <ComprehensiveFinanceReportsView theme={theme} />}
      {activeTab === 'gst' && <GstComplianceHubView />}
    </div>
  );
}
