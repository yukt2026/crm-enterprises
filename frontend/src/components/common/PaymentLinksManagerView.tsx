import React, { useState } from 'react';
import {
  Link2,
  QrCode,
  Plus,
  Copy,
  CheckCircle,
  Clock,
  ExternalLink,
  ShieldCheck,
  AlertCircle,
} from 'lucide-react';
import { usePaymentLinks, useCreatePaymentLink, usePaymentGateways } from '../../hooks/useFinance';
import { api } from '../../api/client';

interface PaymentLinksManagerViewProps {
  theme?: 'light' | 'dark';
}

export default function PaymentLinksManagerView({ theme = 'dark' }: PaymentLinksManagerViewProps) {
  const isDark = theme === 'dark';
  const { data: linksData, isLoading, refetch } = usePaymentLinks(1);
  const { data: gateways } = usePaymentGateways();
  const createLinkMutation = useCreatePaymentLink();

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [copiedToken, setCopiedToken] = useState<string | null>(null);

  const [form, setForm] = useState({
    title: '',
    amount: '',
    currency: 'INR',
    gateway_provider: 'razorpay',
    amount_type: 'due_amount',
    description: '',
  });

  const [qrModalData, setQrModalData] = useState<{
    upiString: string;
    amount: number;
    vpa: string;
    merchant: string;
  } | null>(null);

  const links = (linksData as any)?.data || [];

  const handleCopy = (text: string, token: string) => {
    navigator.clipboard.writeText(text);
    setCopiedToken(token);
    setTimeout(() => setCopiedToken(null), 2500);
  };

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.title || !form.amount) return;

    await createLinkMutation.mutateAsync({
      title: form.title,
      amount: parseFloat(form.amount),
      currency: form.currency,
      gateway_provider: form.gateway_provider,
      amount_type: form.amount_type,
      description: form.description,
    });

    setIsModalOpen(false);
    setForm({
      title: '',
      amount: '',
      currency: 'INR',
      gateway_provider: 'razorpay',
      amount_type: 'due_amount',
      description: '',
    });
    refetch();
  };

  const handleShowDynamicQr = async (amount: number, title: string) => {
    try {
      const res = await api.generateUpiQr({
        amount,
        note: title,
        vpa: 'billing@enterpriseupi',
        merchant_name: 'Acme Enterprise Solutions',
      });
      if (res.data) {
        setQrModalData({
          upiString: res.data.upi_string,
          amount: res.data.amount,
          vpa: res.data.vpa,
          merchant: res.data.merchant_name,
        });
      }
    } catch (err) {
      console.error('Failed to generate UPI QR', err);
    }
  };

  return (
    <div className="space-y-6">
      {/* Header & Actions */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <Link2 className="w-5 h-5 text-indigo-500" />
            Payment Links & Dynamic UPI QR Architecture
          </h2>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Create customer payment links with Razorpay, Cashfree, and instant NPCI Bharat UPI QR codes.
          </p>
        </div>

        <button
          onClick={() => setIsModalOpen(true)}
          className="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm transition"
        >
          <Plus className="w-4 h-4" /> Generate Payment Link
        </button>
      </div>

      {/* Gateway Status Strip */}
      <div className="grid grid-cols-1 sm:grid-cols-4 gap-3">
        <div className="p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 flex items-center justify-between">
          <div className="flex items-center gap-2.5">
            <div className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse" />
            <div>
              <p className="text-xs font-bold text-slate-800 dark:text-slate-200">Razorpay Gateway</p>
              <p className="text-[10px] text-slate-400">Cards, Netbanking & Wallets</p>
            </div>
          </div>
          <span className="text-[10px] px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-500 font-semibold">Active</span>
        </div>

        <div className="p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 flex items-center justify-between">
          <div className="flex items-center gap-2.5">
            <div className="w-2.5 h-2.5 rounded-full bg-blue-500" />
            <div>
              <p className="text-xs font-bold text-slate-800 dark:text-slate-200">Dynamic UPI QR</p>
              <p className="text-[10px] text-slate-400">Zero MDR instant settlement</p>
            </div>
          </div>
          <span className="text-[10px] px-2 py-0.5 rounded bg-blue-500/10 text-blue-500 font-semibold">NPCI Ready</span>
        </div>

        <div className="p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 flex items-center justify-between">
          <div className="flex items-center gap-2.5">
            <div className="w-2.5 h-2.5 rounded-full bg-purple-500" />
            <div>
              <p className="text-xs font-bold text-slate-800 dark:text-slate-200">Cashfree Payments</p>
              <p className="text-[10px] text-slate-400">Multi-channel fallback</p>
            </div>
          </div>
          <span className="text-[10px] px-2 py-0.5 rounded bg-purple-500/10 text-purple-500 font-semibold">Configured</span>
        </div>

        <div className="p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 flex items-center justify-between">
          <div className="flex items-center gap-2.5">
            <div className="w-2.5 h-2.5 rounded-full bg-amber-500" />
            <div>
              <p className="text-xs font-bold text-slate-800 dark:text-slate-200">Direct Bank Transfer</p>
              <p className="text-[10px] text-slate-400">NEFT / RTGS / IMPS</p>
            </div>
          </div>
          <span className="text-[10px] px-2 py-0.5 rounded bg-amber-500/10 text-amber-500 font-semibold">Manual Auth</span>
        </div>
      </div>

      {/* Payment Links Table */}
      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div className="px-5 py-3 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
          <h3 className="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
            Active Payment Links Register
          </h3>
          <span className="text-xs text-slate-400">{links.length} total links generated</span>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="border-b border-slate-200 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-800/20 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                <th className="py-3 px-4">Title / Purpose</th>
                <th className="py-3 px-4">Amount</th>
                <th className="py-3 px-4">Gateway</th>
                <th className="py-3 px-4">Status</th>
                <th className="py-3 px-4">Expires</th>
                <th className="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/50 text-xs">
              {isLoading ? (
                <tr>
                  <td colSpan={6} className="py-8 text-center text-slate-400">
                    Loading payment links...
                  </td>
                </tr>
              ) : links.length === 0 ? (
                <tr>
                  <td colSpan={6} className="py-8 text-center text-slate-400">
                    No payment links generated yet. Click "Generate Payment Link" above.
                  </td>
                </tr>
              ) : (
                links.map((link: any) => (
                  <tr key={link.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                    <td className="py-3 px-4">
                      <div className="font-semibold text-slate-800 dark:text-slate-200">{link.title}</div>
                      <div className="text-[10px] text-slate-400 font-mono mt-0.5">Token: {link.link_token.slice(0, 16)}...</div>
                    </td>
                    <td className="py-3 px-4 font-mono font-bold text-slate-900 dark:text-white">
                      ₹{parseFloat(link.amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                      {link.paid_amount > 0 && (
                        <div className="text-[10px] text-emerald-500 font-sans">
                          Paid: ₹{parseFloat(link.paid_amount).toLocaleString('en-IN')}
                        </div>
                      )}
                    </td>
                    <td className="py-3 px-4">
                      <span className="capitalize px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                        {link.gateway_provider}
                      </span>
                    </td>
                    <td className="py-3 px-4">
                      <span
                        className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold ${
                          link.status === 'paid'
                            ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20'
                            : link.status === 'partially_paid'
                            ? 'bg-amber-500/10 text-amber-500 border border-amber-500/20'
                            : 'bg-blue-500/10 text-blue-500 border border-blue-500/20'
                        }`}
                      >
                        {link.status === 'paid' && <CheckCircle className="w-3 h-3" />}
                        {link.status}
                      </span>
                    </td>
                    <td className="py-3 px-4 text-slate-500 dark:text-slate-400 text-[11px]">
                      {link.expires_at ? new Date(link.expires_at).toLocaleDateString() : 'Never'}
                    </td>
                    <td className="py-3 px-4 text-right">
                      <div className="inline-flex items-center gap-2">
                        <button
                          onClick={() => handleShowDynamicQr(parseFloat(link.amount), link.title)}
                          className="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition"
                          title="Generate Bharat UPI QR"
                        >
                          <QrCode className="w-4 h-4 text-blue-500" />
                        </button>

                        <button
                          onClick={() =>
                            handleCopy(`${window.location.origin}/pay/${link.link_token}`, link.link_token)
                          }
                          className="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition"
                          title="Copy Link"
                        >
                          {copiedToken === link.link_token ? (
                            <CheckCircle className="w-4 h-4 text-emerald-500" />
                          ) : (
                            <Copy className="w-4 h-4" />
                          )}
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

      {/* Modal: Create Payment Link */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
            <h3 className="text-base font-bold text-slate-900 dark:text-white mb-1">
              Generate New Payment Link
            </h3>
            <p className="text-xs text-slate-500 dark:text-slate-400 mb-4">
              Create an online collection request with multi-gateway failover or direct UPI.
            </p>

            <form onSubmit={handleCreate} className="space-y-4 text-xs">
              <div>
                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Purpose / Title
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. ERP Consulting Q1 Milestones"
                  value={form.title}
                  onChange={(e) => setForm({ ...form, title: e.target.value })}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Amount (₹ INR)
                  </label>
                  <input
                    type="number"
                    required
                    min="1"
                    step="0.01"
                    placeholder="25000"
                    value={form.amount}
                    onChange={(e) => setForm({ ...form, amount: e.target.value })}
                    className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono font-bold"
                  />
                </div>

                <div>
                  <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Gateway Provider
                  </label>
                  <select
                    value={form.gateway_provider}
                    onChange={(e) => setForm({ ...form, gateway_provider: e.target.value })}
                    className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  >
                    <option value="razorpay">Razorpay (All Methods)</option>
                    <option value="upi_qr">Dynamic UPI QR (NPCI)</option>
                    <option value="cashfree">Cashfree Gateway</option>
                    <option value="bank_transfer">Direct Bank (NEFT/RTGS)</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Payment Mode Policy
                </label>
                <select
                  value={form.amount_type}
                  onChange={(e) => setForm({ ...form, amount_type: e.target.value })}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                  <option value="due_amount">Full Due Amount</option>
                  <option value="partial">Allow Partial Payment</option>
                  <option value="advance">Advance Deposit</option>
                  <option value="fixed">Fixed Strict Amount</option>
                </select>
              </div>

              <div>
                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Customer Description / Memo
                </label>
                <textarea
                  rows={2}
                  placeholder="Terms, deliverables or milestone reference..."
                  value={form.description}
                  onChange={(e) => setForm({ ...form, description: e.target.value })}
                  className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createLinkMutation.isPending}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow-sm"
                >
                  {createLinkMutation.isPending ? 'Generating...' : 'Generate Link'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal: Dynamic UPI QR Preview */}
      {qrModalData && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-sm w-full p-6 text-center shadow-2xl">
            <h3 className="text-base font-bold text-slate-900 dark:text-white mb-1">
              BHIM UPI Dynamic QR Code
            </h3>
            <p className="text-xs text-slate-500 dark:text-slate-400 mb-4">
              Scan with Google Pay, PhonePe, Paytm, or BHIM app
            </p>

            <div className="p-4 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 mb-4 inline-block">
              {/* SVG QR Illustration */}
              <div className="w-44 h-44 mx-auto bg-white rounded-lg p-2 shadow-inner flex flex-col items-center justify-center border">
                <QrCode className="w-28 h-28 text-slate-900" />
                <div className="text-[10px] font-mono font-bold text-indigo-600 mt-1">
                  ₹{qrModalData.amount.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                </div>
              </div>
            </div>

            <div className="text-xs font-mono bg-slate-100 dark:bg-slate-800/80 p-2.5 rounded-lg text-slate-700 dark:text-slate-300 mb-4 break-all text-left">
              <span className="text-[10px] text-slate-400 block font-sans">UPI String (NPCI Format):</span>
              {qrModalData.upiString}
            </div>

            <div className="flex gap-2">
              <button
                onClick={() => handleCopy(qrModalData.upiString, 'upi_qr')}
                className="flex-1 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg"
              >
                Copy UPI Intent
              </button>
              <button
                onClick={() => setQrModalData(null)}
                className="px-4 py-2 border border-slate-300 dark:border-slate-700 text-xs font-semibold rounded-lg text-slate-700 dark:text-slate-300"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
