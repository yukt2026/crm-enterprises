import React, { useState } from 'react';
import {
  Receipt,
  Plus,
  CheckCircle2,
  XCircle,
  Clock,
  Tag,
  DollarSign,
  FileText,
  User,
  Filter,
} from 'lucide-react';
import {
  useExpenses,
  useCreateExpense,
  useApproveExpense,
  useRejectExpense,
  usePayExpense,
} from '../../hooks/useFinance';

interface ExpensesManagerViewProps {
  theme?: 'light' | 'dark';
}

export default function ExpensesManagerView({ theme = 'dark' }: ExpensesManagerViewProps) {
  const isDark = theme === 'dark';
  const { data: expensesData, isLoading, refetch } = useExpenses();
  const createMutation = useCreateExpense();
  const approveMutation = useApproveExpense();
  const rejectMutation = useRejectExpense();
  const payMutation = usePayExpense();

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [rejectModalItem, setRejectModalItem] = useState<any | null>(null);
  const [rejectionReason, setRejectionReason] = useState('');

  const [form, setForm] = useState({
    title: '',
    category: 'office_supplies',
    amount: '',
    tax_amount: '0',
    is_reimbursement: false,
    description: '',
    receipt_url: '',
  });

  const expenses = (expensesData as any)?.data || [];

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.title || !form.amount) return;

    await createMutation.mutateAsync({
      title: form.title,
      category: form.category,
      amount: parseFloat(form.amount),
      tax_amount: parseFloat(form.tax_amount || '0'),
      is_reimbursement: form.is_reimbursement,
      description: form.description,
      receipt_url: form.receipt_url || undefined,
    });

    setIsModalOpen(false);
    setForm({
      title: '',
      category: 'office_supplies',
      amount: '',
      tax_amount: '0',
      is_reimbursement: false,
      description: '',
      receipt_url: '',
    });
    refetch();
  };

  const handleRejectSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!rejectModalItem || !rejectionReason) return;

    await rejectMutation.mutateAsync({
      id: rejectModalItem.id,
      reason: rejectionReason,
    });

    setRejectModalItem(null);
    setRejectionReason('');
    refetch();
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <Receipt className="w-5 h-5 text-purple-500" />
            Corporate Expense & Reimbursement Management
          </h2>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Record operating expenses, employee travel claims, manager approvals, and disbursement receipts.
          </p>
        </div>

        <button
          onClick={() => setIsModalOpen(true)}
          className="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold rounded-lg shadow-sm transition"
        >
          <Plus className="w-4 h-4" /> Add Expense Claim
        </button>
      </div>

      {/* Expenses Table */}
      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div className="px-5 py-3 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
          <h3 className="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
            Operational Expenses Register
          </h3>
          <span className="text-xs text-slate-400">{expenses.length} claims recorded</span>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/20 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                <th className="py-3 px-4">Expense # / Title</th>
                <th className="py-3 px-4">Category</th>
                <th className="py-3 px-4">Type</th>
                <th className="py-3 px-4">Amount</th>
                <th className="py-3 px-4">Date</th>
                <th className="py-3 px-4">Status</th>
                <th className="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/50 text-xs">
              {isLoading ? (
                <tr>
                  <td colSpan={7} className="py-8 text-center text-slate-400">
                    Loading corporate expenses...
                  </td>
                </tr>
              ) : expenses.length === 0 ? (
                <tr>
                  <td colSpan={7} className="py-8 text-center text-slate-400">
                    No expenses recorded yet. Click "Add Expense Claim" above.
                  </td>
                </tr>
              ) : (
                expenses.map((exp: any) => (
                  <tr key={exp.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                    <td className="py-3 px-4">
                      <div className="font-semibold text-slate-800 dark:text-slate-200">{exp.title}</div>
                      <div className="text-[10px] text-slate-400 font-mono mt-0.5">{exp.expense_number}</div>
                    </td>
                    <td className="py-3 px-4">
                      <span className="capitalize px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                        {exp.category?.replace('_', ' ')}
                      </span>
                    </td>
                    <td className="py-3 px-4">
                      {exp.is_reimbursement ? (
                        <span className="inline-flex items-center gap-1 text-[10px] font-semibold text-indigo-500">
                          <User className="w-3 h-3" /> Reimbursement
                        </span>
                      ) : (
                        <span className="text-[10px] text-slate-400">Direct Expense</span>
                      )}
                    </td>
                    <td className="py-3 px-4 font-mono font-bold text-slate-900 dark:text-white">
                      ₹{parseFloat(exp.amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                    </td>
                    <td className="py-3 px-4 text-slate-500 dark:text-slate-400 text-[11px]">
                      {exp.expense_date ? new Date(exp.expense_date).toLocaleDateString() : 'N/A'}
                    </td>
                    <td className="py-3 px-4">
                      <span
                        className={`capitalize px-2.5 py-0.5 rounded-full text-[10px] font-semibold ${
                          exp.status === 'paid' || exp.status === 'reimbursed'
                            ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20'
                            : exp.status === 'approved'
                            ? 'bg-blue-500/10 text-blue-500 border border-blue-500/20'
                            : exp.status === 'rejected'
                            ? 'bg-rose-500/10 text-rose-500 border border-rose-500/20'
                            : 'bg-amber-500/10 text-amber-500 border border-amber-500/20'
                        }`}
                      >
                        {exp.status}
                      </span>
                    </td>
                    <td className="py-3 px-4 text-right">
                      <div className="inline-flex items-center gap-1.5">
                        {exp.status === 'pending_approval' && (
                          <>
                            <button
                              onClick={() => approveMutation.mutate(exp.id)}
                              className="px-2 py-1 rounded bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 text-[10px] font-semibold hover:bg-blue-100 transition"
                            >
                              Approve
                            </button>
                            <button
                              onClick={() => setRejectModalItem(exp)}
                              className="px-2 py-1 rounded bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 text-[10px] font-semibold hover:bg-rose-100 transition"
                            >
                              Reject
                            </button>
                          </>
                        )}
                        {exp.status === 'approved' && (
                          <button
                            onClick={() => payMutation.mutate({ id: exp.id })}
                            className="px-2.5 py-1 rounded bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 text-[10px] font-semibold hover:bg-emerald-100 transition"
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

      {/* Modal: Add Expense Claim */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
            <h3 className="text-base font-bold text-slate-900 dark:text-white mb-1">
              Record Operational Expense / Claim
            </h3>
            <p className="text-xs text-slate-500 dark:text-slate-400 mb-4">
              Enter bill details with tax and category allocation.
            </p>

            <form onSubmit={handleCreate} className="space-y-4 text-xs">
              <div>
                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Expense Title / Purpose
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. AWS Cloud Infrastructure - Monthly Hosting"
                  value={form.title}
                  onChange={(e) => setForm({ ...form, title: e.target.value })}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Category
                  </label>
                  <select
                    value={form.category}
                    onChange={(e) => setForm({ ...form, category: e.target.value })}
                    className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                  >
                    <option value="office_supplies">Office Supplies</option>
                    <option value="travel">Travel & Hospitality</option>
                    <option value="software_saas">Software & SaaS</option>
                    <option value="marketing">Marketing & Ads</option>
                    <option value="utilities">Utilities & Telecom</option>
                    <option value="professional_services">Professional Services</option>
                    <option value="meals_entertainment">Meals & Entertainment</option>
                    <option value="other">Other Operations</option>
                  </select>
                </div>

                <div>
                  <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Amount (₹ INR)
                  </label>
                  <input
                    type="number"
                    required
                    min="1"
                    step="0.01"
                    placeholder="15000"
                    value={form.amount}
                    onChange={(e) => setForm({ ...form, amount: e.target.value })}
                    className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white font-mono font-bold"
                  />
                </div>
              </div>

              <div className="flex items-center gap-2">
                <input
                  type="checkbox"
                  id="reimbursementCheck"
                  checked={form.is_reimbursement}
                  onChange={(e) => setForm({ ...form, is_reimbursement: e.target.checked })}
                  className="rounded text-purple-600 focus:ring-purple-500"
                />
                <label htmlFor="reimbursementCheck" className="text-slate-700 dark:text-slate-300 font-semibold cursor-pointer">
                  Employee Out-of-Pocket Reimbursement Claim
                </label>
              </div>

              <div>
                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Receipt URL / Attachment
                </label>
                <input
                  type="url"
                  placeholder="https://..."
                  value={form.receipt_url}
                  onChange={(e) => setForm({ ...form, receipt_url: e.target.value })}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-4 py-2 border rounded-lg text-slate-700 dark:text-slate-300 font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createMutation.isPending}
                  className="px-4 py-2 bg-purple-600 text-white rounded-lg font-semibold"
                >
                  {createMutation.isPending ? 'Saving...' : 'Submit Claim'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal: Reject Expense */}
      {rejectModalItem && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl">
            <h3 className="text-base font-bold text-slate-900 dark:text-white mb-1">
              Reject Expense Claim
            </h3>
            <p className="text-xs text-slate-500 dark:text-slate-400 mb-4">
              Expense #{rejectModalItem.expense_number} ({rejectModalItem.title})
            </p>

            <form onSubmit={handleRejectSubmit} className="space-y-4 text-xs">
              <div>
                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Rejection Reason / Justification
                </label>
                <textarea
                  rows={3}
                  required
                  placeholder="State corporate policy discrepancy or missing receipt"
                  value={rejectionReason}
                  onChange={(e) => setRejectionReason(e.target.value)}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setRejectModalItem(null)}
                  className="px-4 py-2 border rounded-lg text-slate-700 dark:text-slate-300 font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={rejectMutation.isPending}
                  className="px-4 py-2 bg-rose-600 text-white rounded-lg font-semibold"
                >
                  Confirm Rejection
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
