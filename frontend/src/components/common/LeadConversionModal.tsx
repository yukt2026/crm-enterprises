import React, { useState } from 'react';
import {
  Sparkles,
  Building2,
  Briefcase,
  X,
  CheckCircle2,
  DollarSign,
  Calendar,
  Loader2,
  ArrowRight,
} from 'lucide-react';
import { useConvertLead } from '../../hooks/useCrmCore';
import { LeadItem } from '../../api/types';

interface LeadConversionModalProps {
  lead: LeadItem | null;
  isOpen: boolean;
  onClose: () => void;
  theme?: 'light' | 'dark';
}

export default function LeadConversionModal({
  lead,
  isOpen,
  onClose,
  theme = 'dark',
}: LeadConversionModalProps) {
  const convertMutation = useConvertLead();

  const [companyName, setCompanyName] = useState(lead?.company_name || `${lead?.name}'s Company`);
  const [createDeal, setCreateDeal] = useState(true);
  const [dealName, setDealName] = useState(`Contract for ${lead?.name || 'Prospect'}`);
  const [dealValue, setDealValue] = useState(lead?.estimated_value || 10000);
  const [dealStage, setDealStage] = useState('qualification');
  const [closeDate, setCloseDate] = useState(
    new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]
  );

  if (!isOpen || !lead) return null;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    convertMutation.mutate(
      {
        leadId: lead.id,
        payload: {
          company_name: companyName,
          create_deal: createDeal,
          deal_name: dealName,
          deal_value: Number(dealValue) || 10000,
          deal_stage: dealStage,
          close_date: closeDate,
        },
      },
      {
        onSuccess: () => {
          onClose();
        },
      }
    );
  };

  return (
    <div className="fixed inset-0 bg-black/70 backdrop-blur-xs z-50 flex items-center justify-center p-4">
      <div
        className={`w-full max-w-lg rounded-2xl border shadow-2xl overflow-hidden p-6 ${
          theme === 'dark' ? 'bg-slate-900 border-slate-800 text-slate-100' : 'bg-white border-slate-200 text-slate-800'
        }`}
      >
        <div className="flex items-center justify-between border-b border-inherit pb-3 mb-4">
          <div className="flex items-center gap-2">
            <Sparkles className="h-5 w-5 text-emerald-400" />
            <div>
              <h3 className="font-bold text-sm">Convert Lead to Customer</h3>
              <p className="text-[11px] text-slate-400">
                Transforms {lead.name} into verified Contact, Company, and Deal records.
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1 rounded-lg hover:bg-slate-800 text-slate-400 hover:text-slate-200"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4 text-xs">
          {/* Company Details */}
          <div>
            <label className="block font-medium text-slate-300 mb-1 flex items-center gap-1.5">
              <Building2 className="h-3.5 w-3.5 text-sky-400" />
              Company Organization Name *
            </label>
            <input
              type="text"
              required
              value={companyName}
              onChange={(e) => setCompanyName(e.target.value)}
              className="w-full px-3 py-2 rounded-xl border border-slate-700 bg-transparent focus:outline-none focus:border-emerald-500 text-slate-200"
            />
          </div>

          {/* Deal Toggle */}
          <div className="p-3 rounded-xl border border-slate-800 bg-slate-950/40 space-y-3">
            <div className="flex items-center justify-between">
              <label htmlFor="createDeal" className="font-semibold text-slate-200 flex items-center gap-1.5 cursor-pointer">
                <Briefcase className="h-3.5 w-3.5 text-teal-400" />
                Create Sales Opportunity / Deal
              </label>
              <input
                type="checkbox"
                id="createDeal"
                checked={createDeal}
                onChange={(e) => setCreateDeal(e.target.checked)}
                className="h-4 w-4 rounded accent-emerald-500 cursor-pointer"
              />
            </div>

            {createDeal && (
              <div className="space-y-3 pt-2 border-t border-slate-800">
                <div>
                  <label className="block text-slate-400 mb-1">Deal Title *</label>
                  <input
                    type="text"
                    required
                    value={dealName}
                    onChange={(e) => setDealName(e.target.value)}
                    className="w-full px-3 py-1.5 rounded-lg border border-slate-700 bg-transparent focus:outline-none focus:border-emerald-500 text-slate-200"
                  />
                </div>

                <div className="grid grid-cols-2 gap-2">
                  <div>
                    <label className="block text-slate-400 mb-1">Deal Value ($)</label>
                    <input
                      type="number"
                      min={0}
                      value={dealValue}
                      onChange={(e) => setDealValue(Number(e.target.value))}
                      className="w-full px-3 py-1.5 rounded-lg border border-slate-700 bg-transparent focus:outline-none focus:border-emerald-500 text-slate-200 font-mono"
                    />
                  </div>
                  <div>
                    <label className="block text-slate-400 mb-1">Initial Pipeline Stage</label>
                    <select
                      value={dealStage}
                      onChange={(e) => setDealStage(e.target.value)}
                      className="w-full px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-900 text-slate-200 focus:outline-none"
                    >
                      <option value="qualification">Qualification</option>
                      <option value="proposal">Proposal Sent</option>
                      <option value="negotiation">Negotiation</option>
                      <option value="won">Closed Won</option>
                    </select>
                  </div>
                </div>

                <div>
                  <label className="block text-slate-400 mb-1">Target Close Date</label>
                  <input
                    type="date"
                    value={closeDate}
                    onChange={(e) => setCloseDate(e.target.value)}
                    className="w-full px-3 py-1.5 rounded-lg border border-slate-700 bg-transparent text-slate-200 focus:outline-none"
                  />
                </div>
              </div>
            )}
          </div>

          <div className="pt-3 border-t border-inherit flex items-center justify-end gap-2">
            <button
              type="button"
              onClick={onClose}
              className="px-3.5 py-2 rounded-xl border border-slate-700 hover:bg-slate-800 text-slate-300 transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={convertMutation.isPending}
              className="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold flex items-center gap-1.5 shadow-xs transition-colors"
            >
              {convertMutation.isPending ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                <ArrowRight className="h-4 w-4" />
              )}
              <span>Complete Conversion</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
