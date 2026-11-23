import React, { useState } from 'react';
import {
  DollarSign,
  TrendingUp,
  CreditCard,
  Calendar,
  Layers,
  AlertCircle,
  RefreshCw,
  Phone,
} from 'lucide-react';
import { useWhatsAppUsageSummary, useWhatsAppUsageRecords } from '../../hooks/useWhatsApp';

interface Props {
  isDark: boolean;
}

export default function WhatsAppBillingTab({ isDark }: Props) {
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [page, setPage] = useState(1);

  const { data: summary, isLoading: isLoadingSummary, refetch: refetchSummary } = useWhatsAppUsageSummary();
  const { data: recordsData, isLoading: isLoadingRecords, refetch: refetchRecords } = useWhatsAppUsageRecords({
    category: selectedCategory === 'all' ? undefined : selectedCategory,
    page,
    per_page: 20,
  });

  const handleRefresh = () => {
    refetchSummary();
    refetchRecords();
  };

  const records = (recordsData?.data || []) as any[];

  return (
    <div className="flex-1 flex flex-col overflow-hidden p-6 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold flex items-center gap-2">
            <DollarSign className="w-5 h-5 text-emerald-500" />
            WhatsApp Usage & Billing Analytics
          </h2>
          <p className="text-xs text-slate-500 dark:text-slate-400">
            Official Meta conversation charges segmented by Marketing, Utility, Authentication, and Service categories.
          </p>
        </div>

        <button
          onClick={handleRefresh}
          className="flex items-center gap-1.5 px-3 py-2 rounded-xl border dark:border-slate-800 text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition"
        >
          <RefreshCw className="w-3.5 h-3.5" />
          Refresh Ledger
        </button>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
        <div className="p-4 rounded-xl border dark:border-slate-800 bg-emerald-500/10 border-emerald-500/20">
          <div className="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">
            Total Spend (30d)
          </div>
          <div className="text-2xl font-bold text-slate-900 dark:text-white mt-1">
            ${Number(summary?.total_cost || 0).toFixed(4)}
          </div>
          <div className="text-[10px] text-slate-500 mt-1">
            {summary?.total_conversations || 0} billable conversations
          </div>
        </div>

        <div className="p-4 rounded-xl border dark:border-slate-800 bg-purple-500/10 border-purple-500/20">
          <div className="text-[11px] font-semibold text-purple-600 dark:text-purple-400 uppercase tracking-wider">
            Marketing Category
          </div>
          <div className="text-2xl font-bold text-slate-900 dark:text-white mt-1">
            ${Number(summary?.by_category?.find((c: any) => c.conversation_category === 'marketing')?.cost || 0).toFixed(4)}
          </div>
          <div className="text-[10px] text-slate-500 mt-1">
            Rate: $0.0500 per conversation
          </div>
        </div>

        <div className="p-4 rounded-xl border dark:border-slate-800 bg-blue-500/10 border-blue-500/20">
          <div className="text-[11px] font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wider">
            Utility Category
          </div>
          <div className="text-2xl font-bold text-slate-900 dark:text-white mt-1">
            ${Number(summary?.by_category?.find((c: any) => c.conversation_category === 'utility')?.cost || 0).toFixed(4)}
          </div>
          <div className="text-[10px] text-slate-500 mt-1">
            Rate: $0.0150 per conversation
          </div>
        </div>

        <div className="p-4 rounded-xl border dark:border-slate-800 bg-amber-500/10 border-amber-500/20">
          <div className="text-[11px] font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wider">
            Service & Auth
          </div>
          <div className="text-2xl font-bold text-slate-900 dark:text-white mt-1">
            ${Number(summary?.by_category?.find((c: any) => c.conversation_category === 'service')?.cost || 0).toFixed(4)}
          </div>
          <div className="text-[10px] text-slate-500 mt-1">
            Free customer service window applied
          </div>
        </div>
      </div>

      {/* Phone Number Usage Breakdown */}
      <div className="space-y-3">
        <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500">
          Usage By Connected Phone Line
        </h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
          {(summary?.by_phone_number || []).map((p: any) => (
            <div
              key={p.phone_number_id}
              className="p-3.5 rounded-xl border dark:border-slate-800 bg-white/50 dark:bg-slate-900/40 text-xs space-y-1.5"
            >
              <div className="flex justify-between items-center">
                <span className="font-mono font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                  <Phone className="w-3.5 h-3.5 text-emerald-500" />
                  {p.display_phone_number}
                </span>
                <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-800">
                  {p.tier}
                </span>
              </div>
              <div className="flex justify-between text-slate-500 text-[11px]">
                <span>Today Sent:</span>
                <span className="font-mono">{p.messages_sent_today || 0}</span>
              </div>
              <div className="flex justify-between text-slate-500 text-[11px]">
                <span>Total Cost:</span>
                <span className="font-mono font-semibold text-emerald-500">
                  ${Number(p.cost || 0).toFixed(4)}
                </span>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Usage Records Ledger */}
      <div className="flex-1 flex flex-col overflow-hidden space-y-3">
        <div className="flex items-center justify-between text-xs">
          <h3 className="font-bold uppercase tracking-wider text-slate-500">
            Granular Conversation Ledger
          </h3>
          <div className="flex items-center gap-1 text-[11px]">
            {['all', 'marketing', 'utility', 'authentication', 'service'].map((cat) => (
              <button
                key={cat}
                onClick={() => setSelectedCategory(cat)}
                className={`capitalize px-2 py-1 rounded transition ${
                  selectedCategory === cat
                    ? 'bg-emerald-500 text-white font-semibold'
                    : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                }`}
              >
                {cat}
              </button>
            ))}
          </div>
        </div>

        <div className="flex-1 overflow-y-auto rounded-xl border dark:border-slate-800 bg-white/50 dark:bg-slate-900/40">
          <table className="w-full text-left text-xs">
            <thead className="bg-slate-100/70 dark:bg-slate-800/60 uppercase font-semibold text-slate-500 text-[10px]">
              <tr>
                <th className="py-2.5 px-4">Date</th>
                <th className="py-2.5 px-4">Line</th>
                <th className="py-2.5 px-4">Category</th>
                <th className="py-2.5 px-4">Conversations</th>
                <th className="py-2.5 px-4 text-right">Billed Cost</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {isLoadingRecords ? (
                <tr>
                  <td colSpan={5} className="py-8 text-center text-slate-400">Loading ledger...</td>
                </tr>
              ) : records.length === 0 ? (
                <tr>
                  <td colSpan={5} className="py-8 text-center text-slate-400">No usage records in this period.</td>
                </tr>
              ) : (
                records.map((r: any) => (
                  <tr key={r.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                    <td className="py-2.5 px-4 font-mono text-[11px] text-slate-600 dark:text-slate-300">
                      {r.recorded_date}
                    </td>
                    <td className="py-2.5 px-4 font-mono text-[11px]">
                      {r.phoneNumber?.display_phone_number || 'Default Line'}
                    </td>
                    <td className="py-2.5 px-4">
                      <span
                        className={`px-2 py-0.5 rounded-full text-[9px] font-bold uppercase ${
                          r.conversation_category === 'marketing'
                            ? 'bg-purple-500/10 text-purple-500'
                            : r.conversation_category === 'utility'
                            ? 'bg-blue-500/10 text-blue-500'
                            : 'bg-emerald-500/10 text-emerald-500'
                        }`}
                      >
                        {r.conversation_category}
                      </span>
                    </td>
                    <td className="py-2.5 px-4 font-mono">{r.billable_conversations_count || 1}</td>
                    <td className="py-2.5 px-4 text-right font-mono font-semibold text-emerald-500">
                      ${Number(r.provider_reported_charge || 0).toFixed(4)} USD
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
