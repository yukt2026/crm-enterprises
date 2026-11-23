import React, { useState } from 'react';
import {
  CreditCard,
  Plus,
  CheckCircle,
  Clock,
  Building,
  DollarSign,
  AlertCircle,
  FileCheck,
} from 'lucide-react';
import {
  useVendorBills,
  useCreateVendorBill,
  useApproveVendorBill,
  usePayablesAgeing,
  useRecordVendorPayment,
} from '../../hooks/useFinance';

interface AccountsPayableViewProps {
  theme?: 'light' | 'dark';
}

export default function AccountsPayableView({ theme = 'dark' }: AccountsPayableViewProps) {
  const isDark = theme === 'dark';
  const { data: billsData, isLoading, refetch: refetchBills } = useVendorBills();
  const { data: ageingData, refetch: refetchAgeing } = usePayablesAgeing();
  const createBillMutation = useCreateVendorBill();
  const approveBillMutation = useApproveVendorBill();
  const recordPaymentMutation = useRecordVendorPayment();

  const [isBillModalOpen, setIsBillModalOpen] = useState(false);
  const [selectedBillForPay, setSelectedBillForPay] = useState<any | null>(null);

  const [billForm, setBillForm] = useState({
    supplier_id: '1',
    bill_number: '',
    total_amount: '',
    due_date: '',
    notes: '',
  });

  const [payForm, setPayForm] = useState({
    amount: '',
    payment_method: 'bank_transfer',
    reference_number: '',
    notes: '',
  });

  const bills = (billsData as any)?.data || [];
  const summary = ageingData?.summary || {
    total_payables: 0,
    not_due_current: 0,
    total_overdue: 0,
    bucket_0_30: 0,
    bucket_31_45: 0,
    bucket_46_90: 0,
    bucket_90_plus: 0,
    bills_count: 0,
  };

  const handleCreateBill = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!billForm.total_amount) return;

    await createBillMutation.mutateAsync({
      supplier_id: parseInt(billForm.supplier_id),
      bill_number: billForm.bill_number || undefined,
      total_amount: parseFloat(billForm.total_amount),
      due_date: billForm.due_date || undefined,
      notes: billForm.notes,
    });

    setIsBillModalOpen(false);
    setBillForm({
      supplier_id: '1',
      bill_number: '',
      total_amount: '',
      due_date: '',
      notes: '',
    });
    refetchBills();
    refetchAgeing();
  };

  const handleDisbursePayment = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedBillForPay || !payForm.amount) return;

    await recordPaymentMutation.mutateAsync({
      vendor_bill_id: selectedBillForPay.id,
      supplier_id: selectedBillForPay.supplier_id,
      amount: parseFloat(payForm.amount),
      payment_method: payForm.payment_method,
      reference_number: payForm.reference_number,
      notes: payForm.notes,
    });

    setSelectedBillForPay(null);
    setPayForm({
      amount: '',
      payment_method: 'bank_transfer',
      reference_number: '',
      notes: '',
    });
    refetchBills();
    refetchAgeing();
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <Building className="w-5 h-5 text-amber-500" />
            Accounts Payable (AP) & Vendor Obligations
          </h2>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Manage vendor bills, approvals, outgoing tranches, and payable ageing buckets.
          </p>
        </div>

        <button
          onClick={() => setIsBillModalOpen(true)}
          className="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-lg shadow-sm transition"
        >
          <Plus className="w-4 h-4" /> Record Vendor Bill
        </button>
      </div>

      {/* AP Ageing Summary */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
          <p className="text-[10px] uppercase font-bold text-slate-400">Total Payables</p>
          <p className="text-base font-bold text-slate-900 dark:text-white mt-1 font-mono">
            ₹{summary.total_payables.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
          </p>
          <span className="text-[10px] text-slate-400">{summary.bills_count} active bills</span>
        </div>

        <div className="p-4 rounded-xl border border-emerald-500/20 bg-emerald-500/5 dark:bg-emerald-500/10">
          <p className="text-[10px] uppercase font-bold text-emerald-600 dark:text-emerald-400">Current (Not Due)</p>
          <p className="text-base font-bold text-emerald-600 dark:text-emerald-400 mt-1 font-mono">
            ₹{summary.not_due_current.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
          </p>
          <span className="text-[10px] text-emerald-600/80">Pending payment window</span>
        </div>

        <div className="p-4 rounded-xl border border-blue-500/20 bg-blue-500/5 dark:bg-blue-500/10">
          <p className="text-[10px] uppercase font-bold text-blue-600 dark:text-blue-400">0 – 30 Days Due</p>
          <p className="text-base font-bold text-blue-600 dark:text-blue-400 mt-1 font-mono">
            ₹{summary.bucket_0_30.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
          </p>
          <span className="text-[10px] text-blue-600/80">Schedule disbursement</span>
        </div>

        <div className="p-4 rounded-xl border border-amber-500/20 bg-amber-500/5 dark:bg-amber-500/10">
          <p className="text-[10px] uppercase font-bold text-amber-600 dark:text-amber-400">31 – 45 Days</p>
          <p className="text-base font-bold text-amber-600 dark:text-amber-400 mt-1 font-mono">
            ₹{summary.bucket_31_45.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
          </p>
          <span className="text-[10px] text-amber-600/80">Payment reminder</span>
        </div>

        <div className="p-4 rounded-xl border border-rose-500/20 bg-rose-500/5 dark:bg-rose-500/10">
          <p className="text-[10px] uppercase font-bold text-rose-600 dark:text-rose-400">46+ Days</p>
          <p className="text-base font-bold text-rose-600 dark:text-rose-400 mt-1 font-mono">
            ₹{(summary.bucket_46_90 + summary.bucket_90_plus).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
          </p>
          <span className="text-[10px] text-rose-600/80">Priority clearance</span>
        </div>
      </div>

      {/* Vendor Bills Register */}
      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div className="px-5 py-3 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
          <h3 className="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
            Vendor Bills & Disbursement Ledger
          </h3>
          <span className="text-xs text-slate-400">{bills.length} bills listed</span>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/20 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                <th className="py-3 px-4">Bill #</th>
                <th className="py-3 px-4">Vendor / Supplier</th>
                <th className="py-3 px-4">Total Amount</th>
                <th className="py-3 px-4">Paid</th>
                <th className="py-3 px-4">Outstanding</th>
                <th className="py-3 px-4">Due Date</th>
                <th className="py-3 px-4">Status</th>
                <th className="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/50 text-xs">
              {isLoading ? (
                <tr>
                  <td colSpan={8} className="py-8 text-center text-slate-400">
                    Loading vendor bills...
                  </td>
                </tr>
              ) : bills.length === 0 ? (
                <tr>
                  <td colSpan={8} className="py-8 text-center text-slate-400">
                    No vendor bills recorded yet. Click "Record Vendor Bill" above.
                  </td>
                </tr>
              ) : (
                bills.map((bill: any) => (
                  <tr key={bill.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                    <td className="py-3 px-4 font-mono font-semibold text-slate-800 dark:text-slate-200">
                      {bill.bill_number}
                    </td>
                    <td className="py-3 px-4 font-semibold text-slate-700 dark:text-slate-300">
                      {bill.supplier?.name || `Vendor #${bill.supplier_id}`}
                    </td>
                    <td className="py-3 px-4 font-mono text-slate-600 dark:text-slate-400">
                      ₹{parseFloat(bill.total_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                    </td>
                    <td className="py-3 px-4 font-mono text-emerald-600 dark:text-emerald-400">
                      ₹{parseFloat(bill.paid_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                    </td>
                    <td className="py-3 px-4 font-mono font-bold text-amber-600 dark:text-amber-400">
                      ₹{parseFloat(bill.outstanding_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                    </td>
                    <td className="py-3 px-4 text-[11px] text-slate-500 dark:text-slate-400">
                      {bill.due_date ? new Date(bill.due_date).toLocaleDateString() : 'N/A'}
                    </td>
                    <td className="py-3 px-4">
                      <span
                        className={`capitalize px-2 py-0.5 rounded text-[10px] font-semibold ${
                          bill.status === 'paid'
                            ? 'bg-emerald-500/10 text-emerald-500'
                            : bill.status === 'approved'
                            ? 'bg-blue-500/10 text-blue-500'
                            : 'bg-amber-500/10 text-amber-500'
                        }`}
                      >
                        {bill.status}
                      </span>
                    </td>
                    <td className="py-3 px-4 text-right">
                      <div className="inline-flex items-center gap-1.5">
                        {bill.status === 'pending_approval' && (
                          <button
                            onClick={() => approveBillMutation.mutate(bill.id)}
                            className="px-2.5 py-1 rounded bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 text-[10px] font-semibold hover:bg-blue-100 transition"
                          >
                            Approve
                          </button>
                        )}
                        {bill.status !== 'paid' && (
                          <button
                            onClick={() => {
                              setSelectedBillForPay(bill);
                              setPayForm({
                                ...payForm,
                                amount: String(bill.outstanding_amount),
                              });
                            }}
                            className="px-2.5 py-1 rounded bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 text-[10px] font-semibold hover:bg-amber-100 transition"
                          >
                            Disburse
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Modal: Record Vendor Bill */}
      {isBillModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
            <h3 className="text-base font-bold text-slate-900 dark:text-white mb-1">
              Record New Vendor Bill
            </h3>
            <p className="text-xs text-slate-500 dark:text-slate-400 mb-4">
              Enter invoice received from supplier/vendor for compliance and settlement.
            </p>

            <form onSubmit={handleCreateBill} className="space-y-4 text-xs">
              <div>
                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Vendor / Supplier ID
                </label>
                <input
                  type="number"
                  required
                  min="1"
                  value={billForm.supplier_id}
                  onChange={(e) => setBillForm({ ...billForm, supplier_id: e.target.value })}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Vendor Bill #
                  </label>
                  <input
                    type="text"
                    placeholder="e.g. VEN-2026-081"
                    value={billForm.bill_number}
                    onChange={(e) => setBillForm({ ...billForm, bill_number: e.target.value })}
                    className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                  />
                </div>

                <div>
                  <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Total Amount (₹ INR)
                  </label>
                  <input
                    type="number"
                    required
                    min="1"
                    step="0.01"
                    placeholder="45000"
                    value={billForm.total_amount}
                    onChange={(e) => setBillForm({ ...billForm, total_amount: e.target.value })}
                    className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white font-mono font-bold"
                  />
                </div>
              </div>

              <div>
                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Payment Due Date
                </label>
                <input
                  type="date"
                  value={billForm.due_date}
                  onChange={(e) => setBillForm({ ...billForm, due_date: e.target.value })}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Bill Notes / Items Description
                </label>
                <textarea
                  rows={2}
                  placeholder="Procured hardware or professional services reference..."
                  value={billForm.notes}
                  onChange={(e) => setBillForm({ ...billForm, notes: e.target.value })}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setIsBillModalOpen(false)}
                  className="px-4 py-2 border rounded-lg text-slate-700 dark:text-slate-300 font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createBillMutation.isPending}
                  className="px-4 py-2 bg-amber-600 text-white rounded-lg font-semibold"
                >
                  {createBillMutation.isPending ? 'Saving...' : 'Record Bill'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal: Disburse Vendor Payment */}
      {selectedBillForPay && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl">
            <h3 className="text-base font-bold text-slate-900 dark:text-white mb-1">
              Disburse Vendor Payment
            </h3>
            <p className="text-xs text-slate-500 dark:text-slate-400 mb-4">
              Bill #{selectedBillForPay.bill_number} (Outstanding: ₹
              {parseFloat(selectedBillForPay.outstanding_amount).toLocaleString('en-IN')})
            </p>

            <form onSubmit={handleDisbursePayment} className="space-y-4 text-xs">
              <div>
                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Disbursement Amount (₹)
                </label>
                <input
                  type="number"
                  required
                  min="1"
                  step="0.01"
                  max={selectedBillForPay.outstanding_amount}
                  value={payForm.amount}
                  onChange={(e) => setPayForm({ ...payForm, amount: e.target.value })}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white font-mono font-bold"
                />
              </div>

              <div>
                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Payment Method
                </label>
                <select
                  value={payForm.payment_method}
                  onChange={(e) => setPayForm({ ...payForm, payment_method: e.target.value })}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                >
                  <option value="bank_transfer">Bank Transfer (NEFT/RTGS)</option>
                  <option value="upi">Direct UPI Transfer</option>
                  <option value="cheque">Bank Cheque</option>
                  <option value="cash">Petty Cash</option>
                </select>
              </div>

              <div>
                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Bank Reference / UTR #
                </label>
                <input
                  type="text"
                  placeholder="e.g. UTR99201994821"
                  value={payForm.reference_number}
                  onChange={(e) => setPayForm({ ...payForm, reference_number: e.target.value })}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white font-mono"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setSelectedBillForPay(null)}
                  className="px-4 py-2 border rounded-lg text-slate-700 dark:text-slate-300 font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={recordPaymentMutation.isPending}
                  className="px-4 py-2 bg-amber-600 text-white rounded-lg font-semibold"
                >
                  {recordPaymentMutation.isPending ? 'Disbursing...' : 'Confirm Disbursement'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
