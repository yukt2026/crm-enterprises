import React, { useState } from 'react';
import {
  TrendingDown,
  Clock,
  AlertTriangle,
  Calendar,
  UserCheck,
  CheckCircle2,
  FileText,
  ShieldAlert,
} from 'lucide-react';
import { useReceivablesAgeing, useUpdatePromiseToPay, useSetInvoiceDispute } from '../../hooks/useFinance';

interface AccountsReceivableViewProps {
  theme?: 'light' | 'dark';
}

export default function AccountsReceivableView({ theme = 'dark' }: AccountsReceivableViewProps) {
  const isDark = theme === 'dark';
  const { data: ageingData, isLoading, refetch } = useReceivablesAgeing();
  const updatePromiseMutation = useUpdatePromiseToPay();
  const setDisputeMutation = useSetInvoiceDispute();

  const [selectedInvoice, setSelectedInvoice] = useState<any | null>(null);
  const [promiseDate, setPromiseDate] = useState('');
  const [promiseNotes, setPromiseNotes] = useState('');

  const [disputeModalInvoice, setDisputeModalInvoice] = useState<any | null>(null);
  const [disputeReason, setDisputeReason] = useState('');

  const summary = ageingData?.summary || {
    total_receivables: 0,
    not_due_current: 0,
    total_overdue: 0,
    bucket_0_30: 0,
    bucket_31_45: 0,
    bucket_46_90: 0,
    bucket_90_plus: 0,
    disputed_amount: 0,
    total_invoices_count: 0,
  };

  const invoices = ageingData?.invoices || [];

  const handlePromiseSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedInvoice || !promiseDate) return;

    await updatePromiseMutation.mutateAsync({
      invoiceId: selectedInvoice.id,
      data: {
        promise_to_pay_date: promiseDate,
        notes: promiseNotes,
      },
    });

    setSelectedInvoice(null);
    setPromiseDate('');
    setPromiseNotes('');
    refetch();
  };

  const handleDisputeSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!disputeModalInvoice) return;

    await setDisputeMutation.mutateAsync({
      invoiceId: disputeModalInvoice.id,
      data: {
        is_disputed: !disputeModalInvoice.is_disputed,
        reason: disputeReason || 'Commercial discrepancy under review',
      },
    });

    setDisputeModalInvoice(null);
    setDisputeReason('');
    refetch();
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h2 className="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
          <TrendingDown className="w-5 h-5 text-rose-500" />
          Accounts Receivable (AR) & Ageing Analysis
        </h2>
        <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          Track customer outstanding balances, promise-to-pay commitments, and recovery across ageing brackets.
        </p>
      </div>

      {/* Ageing Brackets KPI Summary */}
      <div className="grid grid-cols-2 md:grid-cols-6 gap-3">
        <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
          <p className="text-[10px] uppercase font-bold text-slate-400">Total Receivables</p>
          <p className="text-base font-bold text-slate-900 dark:text-white mt-1 font-mono">
            ₹{summary.total_receivables.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
          </p>
          <span className="text-[10px] text-slate-400">{summary.total_invoices_count} pending invoices</span>
        </div>

        <div className="p-4 rounded-xl border border-emerald-500/20 bg-emerald-500/5 dark:bg-emerald-500/10">
          <p className="text-[10px] uppercase font-bold text-emerald-600 dark:text-emerald-400">Current (Not Due)</p>
          <p className="text-base font-bold text-emerald-600 dark:text-emerald-400 mt-1 font-mono">
            ₹{summary.not_due_current.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
          </p>
          <span className="text-[10px] text-emerald-600/80">Within payment terms</span>
        </div>

        <div className="p-4 rounded-xl border border-blue-500/20 bg-blue-500/5 dark:bg-blue-500/10">
          <p className="text-[10px] uppercase font-bold text-blue-600 dark:text-blue-400">0 – 30 Days</p>
          <p className="text-base font-bold text-blue-600 dark:text-blue-400 mt-1 font-mono">
            ₹{summary.bucket_0_30.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
          </p>
          <span className="text-[10px] text-blue-600/80">Follow-up reminder</span>
        </div>

        <div className="p-4 rounded-xl border border-amber-500/20 bg-amber-500/5 dark:bg-amber-500/10">
          <p className="text-[10px] uppercase font-bold text-amber-600 dark:text-amber-400">31 – 45 Days</p>
          <p className="text-base font-bold text-amber-600 dark:text-amber-400 mt-1 font-mono">
            ₹{summary.bucket_31_45.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
          </p>
          <span className="text-[10px] text-amber-600/80">Escalated reminder</span>
        </div>

        <div className="p-4 rounded-xl border border-orange-500/20 bg-orange-500/5 dark:bg-orange-500/10">
          <p className="text-[10px] uppercase font-bold text-orange-600 dark:text-orange-400">46 – 90 Days</p>
          <p className="text-base font-bold text-orange-600 dark:text-orange-400 mt-1 font-mono">
            ₹{summary.bucket_46_90.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
          </p>
          <span className="text-[10px] text-orange-600/80">High debt risk</span>
        </div>

        <div className="p-4 rounded-xl border border-rose-500/20 bg-rose-500/5 dark:bg-rose-500/10">
          <p className="text-[10px] uppercase font-bold text-rose-600 dark:text-rose-400">90+ Days (Severe)</p>
          <p className="text-base font-bold text-rose-600 dark:text-rose-400 mt-1 font-mono">
            ₹{summary.bucket_90_plus.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
          </p>
          <span className="text-[10px] text-rose-600/80">Legal recovery stage</span>
        </div>
      </div>

      {/* Receivables Invoices Register */}
      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div className="px-5 py-3 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
          <h3 className="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
            Outstanding Invoices & Collection Status
          </h3>
          {summary.disputed_amount > 0 && (
            <span className="text-xs font-semibold text-rose-500 flex items-center gap-1">
              <ShieldAlert className="w-3.5 h-3.5" /> Disputed: ₹
              {summary.disputed_amount.toLocaleString('en-IN')}
            </span>
          )}
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/20 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                <th className="py-3 px-4">Invoice #</th>
                <th className="py-3 px-4">Customer</th>
                <th className="py-3 px-4">Total Amount</th>
                <th className="py-3 px-4">Outstanding</th>
                <th className="py-3 px-4">Due Date / Overdue</th>
                <th className="py-3 px-4">Promise to Pay</th>
                <th className="py-3 px-4">Recovery Status</th>
                <th className="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/50 text-xs">
              {isLoading ? (
                <tr>
                  <td colSpan={8} className="py-8 text-center text-slate-400">
                    Loading Accounts Receivable report...
                  </td>
                </tr>
              ) : invoices.length === 0 ? (
                <tr>
                  <td colSpan={8} className="py-8 text-center text-slate-400">
                    No outstanding receivables found. All invoices settled.
                  </td>
                </tr>
              ) : (
                invoices.map((inv: any) => (
                  <tr key={inv.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                    <td className="py-3 px-4 font-mono font-semibold text-slate-800 dark:text-slate-200">
                      {inv.invoice_number}
                      {inv.is_disputed && (
                        <span className="ml-2 inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[9px] bg-rose-500/10 text-rose-500 border border-rose-500/20 font-sans font-semibold">
                          Disputed
                        </span>
                      )}
                    </td>
                    <td className="py-3 px-4 font-semibold text-slate-700 dark:text-slate-300">
                      {inv.customer_name}
                    </td>
                    <td className="py-3 px-4 font-mono text-slate-600 dark:text-slate-400">
                      ₹{parseFloat(inv.total_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                    </td>
                    <td className="py-3 px-4 font-mono font-bold text-rose-600 dark:text-rose-400">
                      ₹{parseFloat(inv.outstanding_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                    </td>
                    <td className="py-3 px-4">
                      <div className="text-[11px] text-slate-700 dark:text-slate-300">
                        {inv.due_date ? new Date(inv.due_date).toLocaleDateString() : 'N/A'}
                      </div>
                      <div className="text-[10px] text-rose-500 font-semibold">
                        {inv.days_overdue > 0 ? `${inv.days_overdue} days overdue` : 'Within terms'}
                      </div>
                    </td>
                    <td className="py-3 px-4">
                      {inv.promise_to_pay_date ? (
                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-indigo-500/10 text-indigo-500 border border-indigo-500/20">
                          <Clock className="w-3 h-3" /> {new Date(inv.promise_to_pay_date).toLocaleDateString()}
                        </span>
                      ) : (
                        <span className="text-[10px] text-slate-400">Not recorded</span>
                      )}
                    </td>
                    <td className="py-3 px-4">
                      <span className="capitalize px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                        {inv.recovery_status || 'current'}
                      </span>
                    </td>
                    <td className="py-3 px-4 text-right">
                      <div className="inline-flex items-center gap-1.5">
                        <button
                          onClick={() => setSelectedInvoice(inv)}
                          className="px-2 py-1 rounded bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 text-[10px] font-semibold hover:bg-indigo-100 dark:hover:bg-indigo-900 transition"
                        >
                          Promise-to-Pay
                        </button>
                        <button
                          onClick={() => setDisputeModalInvoice(inv)}
                          className="px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-[10px] font-semibold hover:bg-slate-200 dark:hover:bg-slate-700 transition"
                        >
                          {inv.is_disputed ? 'Resolve Dispute' : 'Dispute'}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Modal: Promise to Pay */}
      {selectedInvoice && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl">
            <h3 className="text-base font-bold text-slate-900 dark:text-white mb-1">
              Record Promise-to-Pay Commitment
            </h3>
            <p className="text-xs text-slate-500 dark:text-slate-400 mb-4">
              Invoice #{selectedInvoice.invoice_number} ({selectedInvoice.customer_name})
            </p>

            <form onSubmit={handlePromiseSubmit} className="space-y-4 text-xs">
              <div>
                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Commitment Date
                </label>
                <input
                  type="date"
                  required
                  value={promiseDate}
                  onChange={(e) => setPromiseDate(e.target.value)}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Commitment Notes & Follow-up Details
                </label>
                <textarea
                  rows={3}
                  placeholder="e.g. Spoke with CFO, agreed to RTGS clearance on committed date"
                  value={promiseNotes}
                  onChange={(e) => setPromiseNotes(e.target.value)}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setSelectedInvoice(null)}
                  className="px-4 py-2 border rounded-lg text-slate-700 dark:text-slate-300 font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={updatePromiseMutation.isPending}
                  className="px-4 py-2 bg-indigo-600 text-white rounded-lg font-semibold"
                >
                  {updatePromiseMutation.isPending ? 'Saving...' : 'Save Commitment'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal: Flag / Resolve Dispute */}
      {disputeModalInvoice && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl">
            <h3 className="text-base font-bold text-slate-900 dark:text-white mb-1">
              {disputeModalInvoice.is_disputed ? 'Resolve Invoice Dispute' : 'Flag Invoice as Disputed'}
            </h3>
            <p className="text-xs text-slate-500 dark:text-slate-400 mb-4">
              Invoice #{disputeModalInvoice.invoice_number}
            </p>

            <form onSubmit={handleDisputeSubmit} className="space-y-4 text-xs">
              {!disputeModalInvoice.is_disputed && (
                <div>
                  <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Dispute Reason / Commercial Issue
                  </label>
                  <textarea
                    rows={3}
                    required
                    placeholder="Describe billing discrepancy, deliverable contestation, or customer claim"
                    value={disputeReason}
                    onChange={(e) => setDisputeReason(e.target.value)}
                    className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                  />
                </div>
              )}

              {disputeModalInvoice.is_disputed && (
                <p className="text-xs text-slate-600 dark:text-slate-300">
                  Resolving the dispute will restore normal follow-up workflow and mark the recovery status as active.
                </p>
              )}

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setDisputeModalInvoice(null)}
                  className="px-4 py-2 border rounded-lg text-slate-700 dark:text-slate-300 font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={setDisputeMutation.isPending}
                  className={`px-4 py-2 font-semibold text-white rounded-lg ${
                    disputeModalInvoice.is_disputed ? 'bg-emerald-600' : 'bg-rose-600'
                  }`}
                >
                  {disputeModalInvoice.is_disputed ? 'Mark Dispute Resolved' : 'Flag Dispute'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
