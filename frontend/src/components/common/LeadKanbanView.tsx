import React from 'react';
import {
  Layers,
  Sparkles,
  Building2,
  DollarSign,
  User,
  ArrowRight,
  TrendingUp,
  Clock,
  CheckCircle2,
  AlertCircle,
} from 'lucide-react';
import { LeadItem } from '../../api/types';

interface LeadKanbanViewProps {
  leads: any[];
  onConvert: (lead: any) => void;
  onEdit: (lead: any) => void;
  theme?: 'light' | 'dark';
}

const KANBAN_STAGES = [
  { key: 'new', label: 'New Leads', color: 'border-sky-500/30 bg-sky-500/10 text-sky-400' },
  { key: 'attempted', label: 'Attempted', color: 'border-indigo-500/30 bg-indigo-500/10 text-indigo-400' },
  { key: 'contacted', label: 'Contacted', color: 'border-amber-500/30 bg-amber-500/10 text-amber-400' },
  { key: 'qualified', label: 'Qualified', color: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400' },
  { key: 'nurturing', label: 'Nurturing', color: 'border-violet-500/30 bg-violet-500/10 text-violet-400' },
  { key: 'converted', label: 'Converted', color: 'border-teal-500/30 bg-teal-500/10 text-teal-400' },
  { key: 'lost', label: 'Closed Lost', color: 'border-rose-500/30 bg-rose-500/10 text-rose-400' },
];

export default function LeadKanbanView({
  leads,
  onConvert,
  onEdit,
  theme = 'dark',
}: LeadKanbanViewProps) {
  return (
    <div className="flex gap-4 overflow-x-auto pb-4 pt-1">
      {KANBAN_STAGES.map((stage) => {
        const stageLeads = leads.filter(
          (l) => (l.status || 'new').toLowerCase() === stage.key
        );

        const stageTotalValue = stageLeads.reduce(
          (sum, l) => sum + (Number(l.estimated_value || l.value) || 0),
          0
        );

        return (
          <div
            key={stage.key}
            className={`w-72 shrink-0 rounded-2xl border flex flex-col max-h-[75vh] ${
              theme === 'dark' ? 'bg-slate-900/60 border-slate-800' : 'bg-slate-50 border-slate-200'
            }`}
          >
            {/* Column Header */}
            <div className="p-3 border-b border-inherit flex items-center justify-between">
              <div className="flex items-center gap-2">
                <span className={`px-2 py-0.5 rounded-full text-xs font-semibold border ${stage.color}`}>
                  {stageLeads.length}
                </span>
                <h4 className="font-bold text-xs text-slate-200">{stage.label}</h4>
              </div>
              <span className="text-[11px] font-mono text-slate-400">
                ${stageTotalValue.toLocaleString()}
              </span>
            </div>

            {/* Cards List */}
            <div className="flex-1 overflow-y-auto p-2.5 space-y-2.5">
              {stageLeads.length === 0 ? (
                <div className="py-12 text-center text-[11px] text-slate-500">
                  No leads in this stage
                </div>
              ) : (
                stageLeads.map((lead) => (
                  <div
                    key={lead.id}
                    className={`p-3 rounded-xl border transition-all hover:border-slate-600 shadow-xs cursor-pointer ${
                      theme === 'dark' ? 'bg-slate-950/80 border-slate-800' : 'bg-white border-slate-200'
                    }`}
                    onClick={() => onEdit(lead)}
                  >
                    <div className="flex items-start justify-between gap-1 mb-1.5">
                      <h5 className="font-semibold text-xs text-slate-100 truncate">{lead.name}</h5>
                      {lead.score !== undefined && (
                        <span className="text-[10px] font-mono font-bold px-1.5 py-0.2 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 shrink-0">
                          {lead.score} pts
                        </span>
                      )}
                    </div>

                    <p className="text-[11px] text-slate-400 truncate flex items-center gap-1">
                      <Building2 className="h-3 w-3 text-slate-500 shrink-0" />
                      <span>{lead.company_name || lead.company || 'Direct Contact'}</span>
                    </p>

                    <div className="mt-3 pt-2 border-t border-slate-800/60 flex items-center justify-between text-[11px]">
                      <span className="font-mono font-semibold text-emerald-400">
                        ${Number(lead.estimated_value || lead.value || 0).toLocaleString()}
                      </span>

                      {stage.key !== 'converted' && (
                        <button
                          onClick={(e) => {
                            e.stopPropagation();
                            onConvert(lead);
                          }}
                          className="px-2 py-0.5 rounded-lg bg-emerald-600/20 hover:bg-emerald-600 text-emerald-300 hover:text-white transition-all text-[10px] font-semibold flex items-center gap-1"
                          title="Convert to Customer"
                        >
                          <Sparkles className="h-2.5 w-2.5" />
                          <span>Convert</span>
                        </button>
                      )}
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
}
