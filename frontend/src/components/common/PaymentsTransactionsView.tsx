import React, { useState } from 'react';
import {
  DollarSign,
  Receipt,
  Search,
  Filter,
  CheckCircle2,
  Clock,
  RotateCcw,
  Printer,
  ShieldCheck,
  X,
  CreditCard,
  AlertTriangle,
} from 'lucide-react';
import { usePayments, useRefundPayment } from '../../hooks/useFinance';
import { PaymentRecord } from '../../api/types';
import { api } from '../../api/client';

interface PaymentsTransactionsViewProps {
  theme?: 'light' | 'dark';
}

export default function PaymentsTransactionsView({ theme = 'dark' }: PaymentsTransactionsViewProps) {
  const isDark = theme === 'dark';

  const [search, setSearch] = useState('');
  const [selectedPayment, setSelectedPayment] = useState<PaymentRecord | null>(null);
  const [isRefundModalOpen, setIsRefundModalOpen] = useState(false);
  const [refundForm, setRefundForm] = useState({
    amount: 0,
    reason: '',
  });

  const { data: payments = [], isLoading, refetch } = usePayments({
    search: search || undefined,
  });

  const refundMutation = useRefundPayment();

  const handleOpenRefund = (pm: PaymentRecord) => {
    setSelectedPayment(pm);
    const available = Math.max(0, Number(pm.amount) - Number(pm.refunded_amount || 0));
    setRefundForm({
      amount: available,
      reason: 'Customer requested refund / scope cancellation',
    });
    setIsRefundModalOpen(true);
  };

  const handleProcessRefund = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedPayment) return;

    try {
      await refundMutation.mutateAsync({
        id: selectedPayment.id,
        data: {
          amount: refundForm.amount,
          reason: refundForm.reason,
        },
      });
      setIsRefundModalOpen(false);
      setSelectedPayment(null);
      refetch();
    } catch (err) {
      console.error(err);
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h2 className="text-xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <ShieldCheck className="w-5 h-5 text-emerald-500" />
            Payments & Collections Ledger
          </h2>
          <p className="text-sm text-slate-500">
            Real-time audit log of customer payment receipts, gateway transactions, and refunds.
          </p>
        </div>

        <div className="relative min-w-[260px]">
          <Search className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
          <input
            type="text"
            placeholder="Search receipt # or transaction..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className={`w-full pl-9 pr-4 py-1.5 text-xs rounded-lg border outline-none transition ${
              isDark
                ? 'bg-slate-800/80 border-slate-700 text-white placeholder-slate-500 focus:border-emerald-500'
                : 'bg-white border-slate-300 text-slate-900 placeholder-slate-400 focus:border-emerald-500'
            }`}
          />
        </div>
      </div>

      {/* Payments Table */}
      <div className={`rounded-xl border overflow-hidden ${
        isDark ? 'bg-slate-900/40 border-slate-800' : 'bg-white border-slate-200'
      }`}>
        {isLoading ? (
          <div className="p-8 text-center text-slate-500 text-sm">Loading transactions...</div>
        ) : payments.length === 0 ? (
          <div className="p-12 text-center text-slate-500 space-y-3">
            <Receipt className="w-10 h-10 mx-auto text-slate-400" />
            <div className="font-semibold text-slate-700 dark:text-slate-300">No payment records yet</div>
            <p className="text-xs max-w-sm mx-auto">
              Payments recorded manually or settled via Razorpay will be logged in this ledger.
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs border-collapse">
              <thead>
                <tr className={isDark ? 'bg-slate-800/60 text-slate-400' : 'bg-slate-50 text-slate-600'}>
                  <th className="py-3 px-4 font-semibold">Receipt #</th>
                  <th className="py-3 px-4 font-semibold">Date</th>
                  <th className="py-3 px-4 font-semibold">Invoice #</th>
                  <th className="py-3 px-4 font-semibold">Client / Account</th>
                  <th className="py-3 px-4 font-semibold">Channel</th>
                  <th className="py-3 px-4 font-semibold text-right">Settled Amount</th>
                  <th className="py-3 px-4 font-semibold text-right">Refunded</th>
                  <th className="py-3 px-4 font-semibold">Status</th>
                  <th className="py-3 px-4 font-semibold text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                {payments.map((pm) => {
                  const availableToRefund = Math.max(0, Number(pm.amount) - Number(pm.refunded_amount || 0));
                  const isPartiallyRefunded = Number(pm.refunded_amount || 0) > 0 && availableToRefund > 0;
                  const isFullyRefunded = Number(pm.refunded_amount || 0) >= Number(pm.amount);

                  return (
                    <tr key={pm.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                      <td className="py-3 px-4 font-mono font-bold text-emerald-600 dark:text-emerald-400">
                        {pm.receipt_number}
                      </td>
                      <td className="py-3 px-4 text-slate-500 font-mono">
                        {new Date(pm.payment_date).toLocaleDateString()}
                      </td>
                      <td className="py-3 px-4 font-mono text-slate-700 dark:text-slate-300">
                        {pm.invoice?.invoice_number || `INV-${pm.invoice_id}`}
                      </td>
                      <td className="py-3 px-4 font-medium text-slate-900 dark:text-white">
                        {pm.customer?.name || pm.invoice?.billing_name || 'Customer'}
                      </td>
                      <td className="py-3 px-4 capitalize">
                        <span className="inline-flex items-center gap-1">
                          {pm.payment_channel === 'razorpay' ? (
                            <CreditCard className="w-3 h-3 text-indigo-400" />
                          ) : (
                            <DollarSign className="w-3 h-3 text-emerald-400" />
                          )}
                          {pm.payment_method}
                        </span>
                      </td>
                      <td className="py-3 px-4 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                        ₹{Number(pm.amount).toLocaleString()}
                      </td>
                      <td className="py-3 px-4 text-right font-mono text-rose-500">
                        {Number(pm.refunded_amount || 0) > 0 ? `- ₹${Number(pm.refunded_amount).toLocaleString()}` : '-'}
                      </td>
                      <td className="py-3 px-4">
                        {isFullyRefunded ? (
                          <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-500/10 text-rose-500 border border-rose-500/20">
                            Refunded
                          </span>
                        ) : isPartiallyRefunded ? (
                          <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-500 border border-amber-500/20">
                            Partially Refunded
                          </span>
                        ) : (
                          <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
                            Verified Settled
                          </span>
                        )}
                      </td>
                      <td className="py-3 px-4 text-right">
                        {availableToRefund > 0 && (
                          <button
                            onClick={() => handleOpenRefund(pm)}
                            className="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded transition"
                            title="Issue Refund against Transaction"
                          >
                            <RotateCcw className="w-3 h-3" /> Refund
                          </button>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Refund Modal */}
      {isRefundModalOpen && selectedPayment && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div className={`w-full max-w-md rounded-2xl border p-6 space-y-4 shadow-2xl ${
            isDark ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
          }`}>
            <div className="flex items-center justify-between border-b pb-3 dark:border-slate-800">
              <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <RotateCcw className="w-4 h-4 text-rose-500" /> Process Refund (Receipt #{selectedPayment.receipt_number})
              </h3>
              <button onClick={() => setIsRefundModalOpen(false)} className="text-slate-400 hover:text-white">
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={handleProcessRefund} className="space-y-4 text-xs">
              <div className="p-3 rounded-lg border bg-amber-500/10 border-amber-500/20 text-amber-700 dark:text-amber-300">
                <div className="flex items-center gap-1.5 font-bold">
                  <AlertTriangle className="w-4 h-4" /> Reversible Transaction
                </div>
                <p className="mt-1 text-[11px] opacity-90">
                  Refunding will decrement the paid amount and automatically restore outstanding balance on Invoice #{selectedPayment.invoice?.invoice_number}.
                </p>
              </div>

              <div>
                <label className="block font-semibold mb-1 text-slate-600 dark:text-slate-400">
                  Refund Amount (Max Available: ₹{Math.max(0, Number(selectedPayment.amount) - Number(selectedPayment.refunded_amount || 0)).toLocaleString()}) *
                </label>
                <input
                  type="number"
                  required
                  min="0.01"
                  step="any"
                  max={Math.max(0, Number(selectedPayment.amount) - Number(selectedPayment.refunded_amount || 0))}
                  value={refundForm.amount}
                  onChange={(e) => setRefundForm({ ...refundForm, amount: parseFloat(e.target.value) || 0 })}
                  className={`w-full px-3 py-2 text-sm font-mono font-bold rounded-lg border outline-none ${
                    isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                  }`}
                />
              </div>

              <div>
                <label className="block font-semibold mb-1 text-slate-600 dark:text-slate-400">
                  Refund Reason *
                </label>
                <textarea
                  required
                  rows={2}
                  value={refundForm.reason}
                  onChange={(e) => setRefundForm({ ...refundForm, reason: e.target.value })}
                  placeholder="State reason for issuing refund..."
                  className={`w-full px-3 py-2 rounded-lg border outline-none ${
                    isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                  }`}
                />
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsRefundModalOpen(false)}
                  className="px-4 py-2 border rounded-lg font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={refundMutation.isPending}
                  className="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg font-semibold shadow-sm transition disabled:opacity-50"
                >
                  Confirm Refund
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
