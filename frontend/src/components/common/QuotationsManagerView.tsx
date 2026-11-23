import React, { useState, useMemo } from 'react';
import {
  FileText,
  Plus,
  Search,
  Filter,
  CheckCircle2,
  Clock,
  XCircle,
  Eye,
  ArrowRight,
  GitBranch,
  Printer,
  Trash2,
  Copy,
  ChevronRight,
  DollarSign,
  AlertCircle,
  Send,
  X,
  FileCheck,
} from 'lucide-react';
import {
  useQuotations,
  useCreateQuotation,
  useReviseQuotation,
  useUpdateQuotationStatus,
  useConvertQuotationToInvoice,
  useDeleteQuotation,
} from '../../hooks/useFinance';
import { QuotationRecord, QuotationItemRecord } from '../../api/types';
import { api } from '../../api/client';

interface QuotationsManagerViewProps {
  theme?: 'light' | 'dark';
}

export default function QuotationsManagerView({ theme = 'dark' }: QuotationsManagerViewProps) {
  const isDark = theme === 'dark';

  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [selectedQuotation, setSelectedQuotation] = useState<QuotationRecord | null>(null);
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [isRevisionOpen, setIsRevisionOpen] = useState(false);

  // Queries & Mutations
  const { data: quotations = [], isLoading, refetch } = useQuotations({
    status: statusFilter === 'all' ? undefined : statusFilter,
    search: search || undefined,
  });

  const createQuotationMutation = useCreateQuotation();
  const reviseQuotationMutation = useReviseQuotation();
  const updateStatusMutation = useUpdateQuotationStatus();
  const convertToInvoiceMutation = useConvertQuotationToInvoice();
  const deleteQuotationMutation = useDeleteQuotation();

  // Form State
  const [formData, setFormData] = useState<{
    customer_id?: number;
    billing_name: string;
    billing_email: string;
    valid_until: string;
    currency: string;
    is_tax_inclusive: boolean;
    discount_type: 'percentage' | 'fixed';
    discount_rate: number;
    discount_amount: number;
    notes: string;
    terms_conditions: string;
    items: Array<{
      item_name: string;
      description?: string;
      quantity: number;
      unit_price: number;
      tax_rate: number;
      discount_type: 'percentage' | 'fixed';
      discount_rate: number;
      discount_amount: number;
    }>;
  }>({
    billing_name: '',
    billing_email: '',
    valid_until: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    currency: 'INR',
    is_tax_inclusive: false,
    discount_type: 'percentage',
    discount_rate: 0,
    discount_amount: 0,
    notes: 'Thank you for your business. We look forward to working with you.',
    terms_conditions: 'Valid for 30 days from issue date. Payment due according to agreed terms.',
    items: [
      {
        item_name: 'Consulting & Implementation Service',
        quantity: 1,
        unit_price: 15000,
        tax_rate: 18,
        discount_type: 'percentage',
        discount_rate: 0,
        discount_amount: 0,
      },
    ],
  });

  // Calculate local live totals for form
  const computedTotals = useMemo(() => {
    let subtotal = 0;
    let taxTotal = 0;

    const lineCalculations = formData.items.map((item) => {
      const qty = Math.max(0.01, Number(item.quantity) || 1);
      const price = Math.max(0, Number(item.unit_price) || 0);
      const gross = qty * price;

      let lineDiscount = 0;
      if (item.discount_type === 'percentage') {
        lineDiscount = gross * ((item.discount_rate || 0) / 100);
      } else {
        lineDiscount = Math.min(gross, item.discount_amount || 0);
      }

      const netLine = Math.max(0, gross - lineDiscount);
      const taxRate = item.tax_rate || 0;

      let lineSubtotal = 0;
      let lineTax = 0;
      let lineTotal = 0;

      if (formData.is_tax_inclusive) {
        lineSubtotal = taxRate > 0 ? netLine / (1 + taxRate / 100) : netLine;
        lineTax = netLine - lineSubtotal;
        lineTotal = netLine;
      } else {
        lineSubtotal = netLine;
        lineTax = netLine * (taxRate / 100);
        lineTotal = lineSubtotal + lineTax;
      }

      subtotal += lineSubtotal;
      taxTotal += lineTax;

      return { lineSubtotal, lineTax, lineTotal };
    });

    let docDiscount = 0;
    if (formData.discount_type === 'percentage') {
      docDiscount = subtotal * ((formData.discount_rate || 0) / 100);
    } else {
      docDiscount = Math.min(subtotal, formData.discount_amount || 0);
    }

    const grandTotal = formData.is_tax_inclusive
      ? lineCalculations.reduce((acc, curr) => acc + curr.lineTotal, 0) - docDiscount
      : subtotal + taxTotal - docDiscount;

    return {
      subtotal: Math.round(subtotal * 100) / 100,
      taxTotal: Math.round(taxTotal * 100) / 100,
      docDiscount: Math.round(docDiscount * 100) / 100,
      grandTotal: Math.max(0, Math.round(grandTotal * 100) / 100),
    };
  }, [formData]);

  const handleAddItem = () => {
    setFormData((prev) => ({
      ...prev,
      items: [
        ...prev.items,
        {
          item_name: '',
          quantity: 1,
          unit_price: 1000,
          tax_rate: 18,
          discount_type: 'percentage',
          discount_rate: 0,
          discount_amount: 0,
        },
      ],
    }));
  };

  const handleRemoveItem = (index: number) => {
    if (formData.items.length <= 1) return;
    setFormData((prev) => ({
      ...prev,
      items: prev.items.filter((_, i) => i !== index),
    }));
  };

  const handleItemChange = (index: number, field: string, val: any) => {
    setFormData((prev) => {
      const nextItems = [...prev.items];
      nextItems[index] = { ...nextItems[index], [field]: val };
      return { ...prev, items: nextItems };
    });
  };

  const handleSaveQuotation = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.billing_name) {
      alert('Please provide client/recipient name.');
      return;
    }

    try {
      if (isRevisionOpen && selectedQuotation) {
        await reviseQuotationMutation.mutateAsync({
          id: selectedQuotation.id,
          data: {
            valid_until: formData.valid_until,
            currency: formData.currency,
            is_tax_inclusive: formData.is_tax_inclusive,
            discount_type: formData.discount_type,
            discount_rate: formData.discount_rate,
            discount_amount: formData.discount_amount,
            terms_conditions: formData.terms_conditions,
            notes: formData.notes,
            items: formData.items as any,
          },
        });
        setIsRevisionOpen(false);
      } else {
        await createQuotationMutation.mutateAsync({
          valid_until: formData.valid_until,
          currency: formData.currency,
          is_tax_inclusive: formData.is_tax_inclusive,
          discount_type: formData.discount_type,
          discount_rate: formData.discount_rate,
          discount_amount: formData.discount_amount,
          terms_conditions: formData.terms_conditions,
          notes: formData.notes,
          items: formData.items as any,
        });
        setIsCreateOpen(false);
      }
      setSelectedQuotation(null);
    } catch (err) {
      console.error(err);
    }
  };

  const handleOpenRevision = (quote: QuotationRecord) => {
    setSelectedQuotation(quote);
    setFormData({
      billing_name: quote.customer?.name || 'Customer',
      billing_email: quote.customer?.email || '',
      valid_until: quote.valid_until || new Date().toISOString().split('T')[0],
      currency: quote.currency || 'INR',
      is_tax_inclusive: quote.is_tax_inclusive,
      discount_type: quote.discount_type || 'percentage',
      discount_rate: Number(quote.discount_rate) || 0,
      discount_amount: Number(quote.discount_amount) || 0,
      notes: quote.notes || '',
      terms_conditions: quote.terms_conditions || '',
      items: (quote.items || []).map((it) => ({
        item_name: it.item_name,
        description: it.description || '',
        quantity: Number(it.quantity),
        unit_price: Number(it.unit_price),
        tax_rate: Number(it.tax_rate),
        discount_type: it.discount_type || 'percentage',
        discount_rate: Number(it.discount_rate) || 0,
        discount_amount: Number(it.discount_amount) || 0,
      })),
    });
    setIsRevisionOpen(true);
  };

  const handleConvertToInvoice = async (quoteId: number) => {
    if (confirm('Convert this Quotation into an official Tax Invoice?')) {
      await convertToInvoiceMutation.mutateAsync(quoteId);
      setSelectedQuotation(null);
    }
  };

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'accepted':
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
            <CheckCircle2 className="w-3 h-3" /> Accepted
          </span>
        );
      case 'sent':
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-500/10 text-blue-500 border border-blue-500/20">
            <Send className="w-3 h-3" /> Sent
          </span>
        );
      case 'viewed':
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-500/10 text-purple-500 border border-purple-500/20">
            <Eye className="w-3 h-3" /> Viewed
          </span>
        );
      case 'rejected':
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-500 border border-rose-500/20">
            <XCircle className="w-3 h-3" /> Rejected
          </span>
        );
      case 'expired':
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-500 border border-amber-500/20">
            <Clock className="w-3 h-3" /> Expired
          </span>
        );
      default:
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-500/10 text-slate-500 border border-slate-500/20">
            Draft
          </span>
        );
    }
  };

  return (
    <div className="space-y-6">
      {/* Top Action Bar */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h2 className="text-xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <FileText className="w-5 h-5 text-blue-500" />
            Quotations & Scope Estimates
          </h2>
          <p className="text-sm text-slate-500">
            Create professional commercial proposals, manage revisions, and convert directly to invoices.
          </p>
        </div>
        <button
          onClick={() => {
            setIsRevisionOpen(false);
            setIsCreateOpen(true);
          }}
          className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold shadow-sm transition"
        >
          <Plus className="w-4 h-4" /> Create Quotation
        </button>
      </div>

      {/* Filter and Search Bar */}
      <div className={`p-4 rounded-xl border flex flex-wrap items-center justify-between gap-4 ${
        isDark ? 'bg-slate-900/50 border-slate-800' : 'bg-white border-slate-200'
      }`}>
        <div className="flex items-center gap-2 flex-wrap">
          {['all', 'draft', 'sent', 'viewed', 'accepted', 'rejected', 'expired'].map((st) => (
            <button
              key={st}
              onClick={() => setStatusFilter(st)}
              className={`px-3 py-1 rounded-lg text-xs font-medium capitalize transition ${
                statusFilter === st
                  ? 'bg-blue-600 text-white shadow-sm'
                  : isDark
                  ? 'bg-slate-800 text-slate-400 hover:bg-slate-700'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
              }`}
            >
              {st}
            </button>
          ))}
        </div>

        <div className="relative min-w-[240px]">
          <Search className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
          <input
            type="text"
            placeholder="Search quotations..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className={`w-full pl-9 pr-4 py-1.5 text-xs rounded-lg border outline-none transition ${
              isDark
                ? 'bg-slate-800/80 border-slate-700 text-white placeholder-slate-500 focus:border-blue-500'
                : 'bg-white border-slate-300 text-slate-900 placeholder-slate-400 focus:border-blue-500'
            }`}
          />
        </div>
      </div>

      {/* Quotations Table */}
      <div className={`rounded-xl border overflow-hidden ${
        isDark ? 'bg-slate-900/40 border-slate-800' : 'bg-white border-slate-200'
      }`}>
        {isLoading ? (
          <div className="p-8 text-center text-slate-500 text-sm">Loading quotations...</div>
        ) : quotations.length === 0 ? (
          <div className="p-12 text-center text-slate-500 space-y-3">
            <FileText className="w-10 h-10 mx-auto text-slate-400" />
            <div className="font-semibold text-slate-700 dark:text-slate-300">No quotations found</div>
            <p className="text-xs max-w-sm mx-auto">
              Create a new quotation for your customer leads or deals to send proposal estimates.
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs border-collapse">
              <thead>
                <tr className={isDark ? 'bg-slate-800/60 text-slate-400' : 'bg-slate-50 text-slate-600'}>
                  <th className="py-3 px-4 font-semibold">Quote Code</th>
                  <th className="py-3 px-4 font-semibold">Client / Account</th>
                  <th className="py-3 px-4 font-semibold">Version</th>
                  <th className="py-3 px-4 font-semibold text-right">Subtotal</th>
                  <th className="py-3 px-4 font-semibold text-right">Tax</th>
                  <th className="py-3 px-4 font-semibold text-right">Total Value</th>
                  <th className="py-3 px-4 font-semibold">Status</th>
                  <th className="py-3 px-4 font-semibold">Valid Until</th>
                  <th className="py-3 px-4 font-semibold text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                {quotations.map((quote) => {
                  const clientName = quote.customer?.name || quote.contact?.first_name ? `${quote.contact?.first_name || ''} ${quote.contact?.last_name || ''}`.trim() : 'Prospective Client';
                  const quoteNum = quote.quotation_number || quote.quote_number || `QT-${quote.id}`;

                  return (
                    <tr
                      key={quote.id}
                      className={`hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition cursor-pointer ${
                        selectedQuotation?.id === quote.id ? (isDark ? 'bg-slate-800/70' : 'bg-blue-50/50') : ''
                      }`}
                      onClick={() => setSelectedQuotation(quote)}
                    >
                      <td className="py-3 px-4 font-mono font-bold text-blue-600 dark:text-blue-400">
                        {quoteNum}
                      </td>
                      <td className="py-3 px-4 font-medium text-slate-900 dark:text-white">
                        {clientName}
                      </td>
                      <td className="py-3 px-4">
                        <span className="inline-flex items-center gap-1 font-mono text-[11px] px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                          <GitBranch className="w-3 h-3 text-slate-400" /> v{quote.version || 1}
                        </span>
                      </td>
                      <td className="py-3 px-4 text-right font-mono text-slate-600 dark:text-slate-400">
                        ₹{Number(quote.subtotal || 0).toLocaleString()}
                      </td>
                      <td className="py-3 px-4 text-right font-mono text-slate-500">
                        ₹{Number(quote.tax_amount || 0).toLocaleString()}
                      </td>
                      <td className="py-3 px-4 text-right font-mono font-bold text-slate-900 dark:text-white">
                        ₹{Number(quote.total_amount || 0).toLocaleString()}
                      </td>
                      <td className="py-3 px-4">
                        {getStatusBadge(quote.status)}
                      </td>
                      <td className="py-3 px-4 text-slate-500 font-mono">
                        {quote.valid_until ? new Date(quote.valid_until).toLocaleDateString() : '-'}
                      </td>
                      <td className="py-3 px-4 text-right" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center justify-end gap-1.5">
                          <a
                            href={api.getQuotationPdfUrl(quote.id)}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-800 dark:hover:text-white transition"
                            title="View / Print Proposal"
                          >
                            <Printer className="w-3.5 h-3.5" />
                          </a>
                          {quote.status !== 'accepted' && (
                            <button
                              onClick={() => handleConvertToInvoice(quote.id)}
                              className="px-2 py-1 text-[11px] font-semibold bg-emerald-600 hover:bg-emerald-700 text-white rounded transition"
                              title="Accept & Convert to Invoice"
                            >
                              To Invoice
                            </button>
                          )}
                          <button
                            onClick={() => handleOpenRevision(quote)}
                            className="p-1.5 rounded hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-800 dark:hover:text-white transition"
                            title="Branch New Revision"
                          >
                            <GitBranch className="w-3.5 h-3.5" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Quotation Detail Drawer */}
      {selectedQuotation && !isCreateOpen && !isRevisionOpen && (
        <div className={`p-6 rounded-xl border space-y-6 ${
          isDark ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
        }`}>
          <div className="flex items-start justify-between">
            <div>
              <div className="flex items-center gap-3">
                <h3 className="text-lg font-bold text-slate-900 dark:text-white font-mono">
                  {selectedQuotation.quotation_number || selectedQuotation.quote_number}
                </h3>
                {getStatusBadge(selectedQuotation.status)}
                <span className="text-xs px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-mono text-slate-500">
                  Version {selectedQuotation.version || 1}
                </span>
              </div>
              <p className="text-xs text-slate-500 mt-1">
                Recipient: <strong>{selectedQuotation.customer?.name || 'Customer'}</strong> | Valid Until: {selectedQuotation.valid_until || '30 days'}
              </p>
            </div>

            <div className="flex items-center gap-2">
              <a
                href={api.getQuotationPdfUrl(selectedQuotation.id)}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1.5 px-3 py-1.5 border rounded-lg text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition"
              >
                <Printer className="w-3.5 h-3.5" /> Print / PDF
              </a>

              {selectedQuotation.status !== 'accepted' && (
                <button
                  onClick={() => handleConvertToInvoice(selectedQuotation.id)}
                  className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold transition"
                >
                  <FileCheck className="w-3.5 h-3.5" /> Convert to Invoice
                </button>
              )}

              <button
                onClick={() => handleOpenRevision(selectedQuotation)}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold transition"
              >
                <GitBranch className="w-3.5 h-3.5" /> Revise (R{(selectedQuotation.version || 1) + 1})
              </button>

              <button
                onClick={() => setSelectedQuotation(null)}
                className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400"
              >
                <X className="w-4 h-4" />
              </button>
            </div>
          </div>

          {/* Line Items Table */}
          <div className="border rounded-lg overflow-hidden dark:border-slate-800">
            <table className="w-full text-left text-xs border-collapse">
              <thead className={isDark ? 'bg-slate-800/60 text-slate-400' : 'bg-slate-50 text-slate-600'}>
                <tr>
                  <th className="py-2.5 px-3 font-semibold">Item & Description</th>
                  <th className="py-2.5 px-3 font-semibold text-center">Qty</th>
                  <th className="py-2.5 px-3 font-semibold text-right">Unit Rate</th>
                  <th className="py-2.5 px-3 font-semibold text-right">Tax Rate</th>
                  <th className="py-2.5 px-3 font-semibold text-right">Discount</th>
                  <th className="py-2.5 px-3 font-semibold text-right">Line Total</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                {(selectedQuotation.items || []).map((it, idx) => (
                  <tr key={idx}>
                    <td className="py-2.5 px-3">
                      <div className="font-semibold text-slate-900 dark:text-white">{it.item_name}</div>
                      {it.description && <div className="text-[11px] text-slate-500">{it.description}</div>}
                    </td>
                    <td className="py-2.5 px-3 text-center font-mono">{it.quantity}</td>
                    <td className="py-2.5 px-3 text-right font-mono">₹{Number(it.unit_price).toLocaleString()}</td>
                    <td className="py-2.5 px-3 text-right font-mono">{it.tax_rate}%</td>
                    <td className="py-2.5 px-3 text-right font-mono text-slate-500">₹{Number(it.discount_amount || 0).toLocaleString()}</td>
                    <td className="py-2.5 px-3 text-right font-mono font-bold text-slate-900 dark:text-white">
                      ₹{Number(it.total_amount).toLocaleString()}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Terms and Totals */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t dark:border-slate-800">
            <div className="space-y-3 text-xs text-slate-500">
              <div>
                <strong className="text-slate-700 dark:text-slate-300 block mb-1">Terms & Conditions:</strong>
                <p className="bg-slate-50 dark:bg-slate-800/50 p-3 rounded-lg border dark:border-slate-800">
                  {selectedQuotation.terms_conditions || 'Standard commercial terms apply.'}
                </p>
              </div>
              <div>
                <strong className="text-slate-700 dark:text-slate-300 block mb-1">Notes:</strong>
                <p className="bg-slate-50 dark:bg-slate-800/50 p-3 rounded-lg border dark:border-slate-800">
                  {selectedQuotation.notes || 'None'}
                </p>
              </div>
            </div>

            <div className="space-y-2 text-xs font-mono">
              <div className="flex justify-between py-1 border-b dark:border-slate-800 text-slate-500">
                <span>Subtotal:</span>
                <span>₹{Number(selectedQuotation.subtotal || 0).toLocaleString()}</span>
              </div>
              <div className="flex justify-between py-1 border-b dark:border-slate-800 text-slate-500">
                <span>Tax Total:</span>
                <span>₹{Number(selectedQuotation.tax_amount || 0).toLocaleString()}</span>
              </div>
              <div className="flex justify-between py-1 border-b dark:border-slate-800 text-slate-500">
                <span>Discount:</span>
                <span>- ₹{Number(selectedQuotation.discount_amount || 0).toLocaleString()}</span>
              </div>
              <div className="flex justify-between py-2 text-base font-bold text-slate-900 dark:text-white">
                <span>Grand Total:</span>
                <span>₹{Number(selectedQuotation.total_amount || 0).toLocaleString()}</span>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Create / Revise Drawer Modal */}
      {(isCreateOpen || isRevisionOpen) && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div className={`w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-2xl border p-6 space-y-6 shadow-2xl ${
            isDark ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
          }`}>
            <div className="flex items-center justify-between border-b pb-4 dark:border-slate-800">
              <div className="flex items-center gap-2">
                <FileText className="w-5 h-5 text-blue-500" />
                <h3 className="text-lg font-bold text-slate-900 dark:text-white">
                  {isRevisionOpen
                    ? `Create Revision R${(selectedQuotation?.version || 1) + 1} for ${selectedQuotation?.quotation_number}`
                    : 'Draft Commercial Quotation'}
                </h3>
              </div>
              <button
                onClick={() => {
                  setIsCreateOpen(false);
                  setIsRevisionOpen(false);
                }}
                className="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSaveQuotation} className="space-y-6">
              {/* Header Fields */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label className="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-400">
                    Recipient / Client Name *
                  </label>
                  <input
                    type="text"
                    required
                    value={formData.billing_name}
                    onChange={(e) => setFormData({ ...formData, billing_name: e.target.value })}
                    placeholder="Acme Corp / Client Name"
                    className={`w-full px-3 py-2 text-xs rounded-lg border outline-none ${
                      isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                    }`}
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-400">
                    Valid Until (Expiry Date)
                  </label>
                  <input
                    type="date"
                    value={formData.valid_until}
                    onChange={(e) => setFormData({ ...formData, valid_until: e.target.value })}
                    className={`w-full px-3 py-2 text-xs rounded-lg border outline-none ${
                      isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                    }`}
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-400">
                    Pricing Mode
                  </label>
                  <select
                    value={formData.is_tax_inclusive ? 'inclusive' : 'exclusive'}
                    onChange={(e) => setFormData({ ...formData, is_tax_inclusive: e.target.value === 'inclusive' })}
                    className={`w-full px-3 py-2 text-xs rounded-lg border outline-none ${
                      isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                    }`}
                  >
                    <option value="exclusive">Tax-Exclusive (Tax added on top)</option>
                    <option value="inclusive">Tax-Inclusive (Tax included in item rate)</option>
                  </select>
                </div>
              </div>

              {/* Line Items Editor */}
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                    Quotation Line Items
                  </h4>
                  <button
                    type="button"
                    onClick={handleAddItem}
                    className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold bg-blue-500/10 text-blue-500 rounded-lg hover:bg-blue-500/20 transition"
                  >
                    <Plus className="w-3.5 h-3.5" /> Add Line Item
                  </button>
                </div>

                <div className="space-y-2">
                  {formData.items.map((it, idx) => (
                    <div
                      key={idx}
                      className={`p-3 rounded-xl border grid grid-cols-12 gap-2 items-center ${
                        isDark ? 'bg-slate-800/40 border-slate-700/60' : 'bg-slate-50 border-slate-200'
                      }`}
                    >
                      <div className="col-span-12 sm:col-span-4">
                        <input
                          type="text"
                          required
                          placeholder="Item name / Scope description"
                          value={it.item_name}
                          onChange={(e) => handleItemChange(idx, 'item_name', e.target.value)}
                          className={`w-full px-2.5 py-1.5 text-xs rounded-lg border outline-none ${
                            isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                          }`}
                        />
                      </div>

                      <div className="col-span-4 sm:col-span-2">
                        <div className="flex items-center gap-1">
                          <span className="text-[10px] text-slate-400">Qty:</span>
                          <input
                            type="number"
                            min="0.01"
                            step="any"
                            value={it.quantity}
                            onChange={(e) => handleItemChange(idx, 'quantity', parseFloat(e.target.value) || 1)}
                            className={`w-full px-2 py-1.5 text-xs rounded-lg border outline-none font-mono ${
                              isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                            }`}
                          />
                        </div>
                      </div>

                      <div className="col-span-4 sm:col-span-2">
                        <div className="flex items-center gap-1">
                          <span className="text-[10px] text-slate-400">Rate:</span>
                          <input
                            type="number"
                            min="0"
                            step="any"
                            value={it.unit_price}
                            onChange={(e) => handleItemChange(idx, 'unit_price', parseFloat(e.target.value) || 0)}
                            className={`w-full px-2 py-1.5 text-xs rounded-lg border outline-none font-mono ${
                              isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                            }`}
                          />
                        </div>
                      </div>

                      <div className="col-span-3 sm:col-span-2">
                        <div className="flex items-center gap-1">
                          <span className="text-[10px] text-slate-400">Tax:</span>
                          <select
                            value={it.tax_rate}
                            onChange={(e) => handleItemChange(idx, 'tax_rate', parseFloat(e.target.value) || 0)}
                            className={`w-full px-1.5 py-1.5 text-xs rounded-lg border outline-none font-mono ${
                              isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                            }`}
                          >
                            <option value="0">0%</option>
                            <option value="5">5%</option>
                            <option value="12">12%</option>
                            <option value="18">18%</option>
                            <option value="28">28%</option>
                          </select>
                        </div>
                      </div>

                      <div className="col-span-1 text-right">
                        <button
                          type="button"
                          onClick={() => handleRemoveItem(idx)}
                          disabled={formData.items.length <= 1}
                          className="p-1 rounded text-slate-400 hover:text-rose-500 disabled:opacity-30"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {/* Terms and Summary Breakdown */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t dark:border-slate-800">
                <div className="space-y-3">
                  <div>
                    <label className="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-400">
                      Terms & Conditions
                    </label>
                    <textarea
                      rows={2}
                      value={formData.terms_conditions}
                      onChange={(e) => setFormData({ ...formData, terms_conditions: e.target.value })}
                      className={`w-full px-3 py-2 text-xs rounded-lg border outline-none ${
                        isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                      }`}
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-400">
                      Notes
                    </label>
                    <textarea
                      rows={2}
                      value={formData.notes}
                      onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                      className={`w-full px-3 py-2 text-xs rounded-lg border outline-none ${
                        isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                      }`}
                    />
                  </div>
                </div>

                <div className="p-4 rounded-xl border bg-slate-50 dark:bg-slate-800/40 dark:border-slate-800 space-y-2 text-xs font-mono">
                  <div className="flex justify-between text-slate-500">
                    <span>Computed Subtotal:</span>
                    <span>₹{computedTotals.subtotal.toLocaleString()}</span>
                  </div>
                  <div className="flex justify-between text-slate-500">
                    <span>Tax Breakdown:</span>
                    <span>₹{computedTotals.taxTotal.toLocaleString()}</span>
                  </div>
                  <div className="flex justify-between items-center text-slate-500 pt-1">
                    <span>Overall Document Discount (%):</span>
                    <input
                      type="number"
                      min="0"
                      max="100"
                      value={formData.discount_rate}
                      onChange={(e) => setFormData({ ...formData, discount_rate: parseFloat(e.target.value) || 0 })}
                      className="w-16 px-1.5 py-0.5 border rounded text-right dark:bg-slate-800 text-xs"
                    />
                  </div>
                  <div className="flex justify-between text-base font-bold text-slate-900 dark:text-white pt-2 border-t dark:border-slate-700">
                    <span>Estimated Total:</span>
                    <span className="text-blue-600 dark:text-blue-400">
                      ₹{computedTotals.grandTotal.toLocaleString()}
                    </span>
                  </div>
                </div>
              </div>

              {/* Form Buttons */}
              <div className="flex justify-end gap-3 pt-4 border-t dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => {
                    setIsCreateOpen(false);
                    setIsRevisionOpen(false);
                  }}
                  className="px-4 py-2 border rounded-lg text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createQuotationMutation.isPending || reviseQuotationMutation.isPending}
                  className="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold shadow-sm transition disabled:opacity-50"
                >
                  {isRevisionOpen ? 'Save New Revision' : 'Save Quotation Draft'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
