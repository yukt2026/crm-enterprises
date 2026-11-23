import React, { useState } from 'react';
import {
  User,
  Building2,
  Mail,
  Phone,
  Clock,
  Briefcase,
  DollarSign,
  Paperclip,
  X,
  Plus,
  Send,
  Loader2,
  Calendar,
  CheckCircle2,
  Award,
  Globe,
  Tag,
  ShieldAlert,
  ArrowUpRight,
  TrendingUp,
  FileText,
  MessageSquare,
  PhoneCall,
  Sparkles,
  ExternalLink,
} from 'lucide-react';
import { useCustomer360, useLogCrmActivity } from '../../hooks/useCrmCore';
import { useCRMStore } from '../../store/crmStore';

interface Customer360DrawerProps {
  customerId: number | string | null;
  isOpen: boolean;
  onClose: () => void;
  theme?: 'light' | 'dark';
}

function getActivityIcon(type: string) {
  switch (type) {
    case 'call': return PhoneCall;
    case 'email': return Mail;
    case 'meeting': return Calendar;
    case 'whatsapp':
    case 'sms': return MessageSquare;
    case 'conversion': return Sparkles;
    case 'status_change': return TrendingUp;
    default: return FileText;
  }
}

export default function Customer360Drawer({
  customerId,
  isOpen,
  onClose,
  theme = 'dark',
}: Customer360DrawerProps) {
  const [activeTab, setActiveTab] = useState<'timeline' | 'deals' | 'attribution' | 'attachments'>('timeline');

  // New activity form state
  const [newActivityType, setNewActivityType] = useState('note');
  const [newActivityTitle, setNewActivityTitle] = useState('');
  const [newActivityDesc, setNewActivityDesc] = useState('');

  const { data: profile, isLoading } = useCustomer360(isOpen ? customerId : null);
  const logActivityMutation = useLogCrmActivity();
  const addToast = useCRMStore((s) => s.addToast);

  if (!isOpen || !customerId) return null;

  const handleLogActivity = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newActivityTitle.trim()) return;

    logActivityMutation.mutate(
      {
        customer_id: customerId,
        contact_id: profile?.contact?.id,
        company_id: profile?.company?.id,
        activity_type: newActivityType,
        title: newActivityTitle.trim(),
        description: newActivityDesc.trim() || undefined,
      },
      {
        onSuccess: () => {
          setNewActivityTitle('');
          setNewActivityDesc('');
        },
      }
    );
  };

  return (
    <div className="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex justify-end">
      <div
        className={`w-full max-w-2xl h-full shadow-2xl flex flex-col border-l animate-in slide-in-from-right duration-200 ${
          theme === 'dark' ? 'bg-slate-900 border-slate-800 text-slate-100' : 'bg-white border-slate-200 text-slate-800'
        }`}
      >
        {/* DRAWER HEADER */}
        <div className="p-5 border-b border-inherit flex items-start justify-between">
          <div className="flex items-center gap-3.5">
            <div className="h-12 w-12 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-lg border border-emerald-500/30 shrink-0">
              {profile?.name ? profile.name.charAt(0).toUpperCase() : 'C'}
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h2 className="font-bold text-lg text-slate-100">{profile?.name || 'Customer Profile'}</h2>
                <span className="text-[10px] font-mono uppercase px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                  {profile?.status || 'Active'}
                </span>
                {profile?.tier && (
                  <span className="text-[10px] uppercase font-semibold px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 border border-slate-700">
                    {profile.tier}
                  </span>
                )}
              </div>
              <p className="text-xs text-slate-400 flex items-center gap-2 mt-0.5">
                {profile?.company?.name && (
                  <span className="flex items-center gap-1">
                    <Building2 className="h-3 w-3 text-slate-500" />
                    {profile.company.name}
                  </span>
                )}
                {profile?.email && (
                  <span className="flex items-center gap-1 font-mono">
                    <Mail className="h-3 w-3 text-slate-500" />
                    {profile.email}
                  </span>
                )}
              </p>
            </div>
          </div>

          <button
            onClick={onClose}
            className="p-1.5 rounded-lg hover:bg-slate-800 text-slate-400 hover:text-slate-200 transition-colors"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* METRICS SUMMARY STRIP */}
        <div className={`grid grid-cols-3 gap-2 px-5 py-3 border-b text-xs ${
          theme === 'dark' ? 'bg-slate-950/40 border-slate-800' : 'bg-slate-50 border-slate-200'
        }`}>
          <div>
            <span className="text-[10px] text-slate-500 block">Total Deals</span>
            <span className="font-semibold text-slate-200 font-mono text-sm">
              {profile?.deals?.total_count || 0}
            </span>
          </div>
          <div>
            <span className="text-[10px] text-slate-500 block">Pipeline Value</span>
            <span className="font-semibold text-emerald-400 font-mono text-sm">
              ${(profile?.deals?.total_value || 0).toLocaleString()}
            </span>
          </div>
          <div>
            <span className="text-[10px] text-slate-500 block">Won Revenue</span>
            <span className="font-semibold text-teal-400 font-mono text-sm">
              ${(profile?.deals?.won_value || 0).toLocaleString()}
            </span>
          </div>
        </div>

        {/* TABS NAVIGATION */}
        <div className="flex items-center gap-1 px-5 pt-3 border-b border-inherit text-xs overflow-x-auto">
          <button
            onClick={() => setActiveTab('timeline')}
            className={`pb-2.5 px-3 font-semibold border-b-2 transition-all flex items-center gap-1.5 ${
              activeTab === 'timeline'
                ? 'border-emerald-500 text-emerald-400'
                : 'border-transparent text-slate-400 hover:text-slate-200'
            }`}
          >
            <Clock className="h-3.5 w-3.5" />
            <span>Activity Timeline</span>
          </button>
          <button
            onClick={() => setActiveTab('deals')}
            className={`pb-2.5 px-3 font-semibold border-b-2 transition-all flex items-center gap-1.5 ${
              activeTab === 'deals'
                ? 'border-emerald-500 text-emerald-400'
                : 'border-transparent text-slate-400 hover:text-slate-200'
            }`}
          >
            <Briefcase className="h-3.5 w-3.5" />
            <span>Sales Deals ({profile?.deals?.total_count || 0})</span>
          </button>
          <button
            onClick={() => setActiveTab('attribution')}
            className={`pb-2.5 px-3 font-semibold border-b-2 transition-all flex items-center gap-1.5 ${
              activeTab === 'attribution'
                ? 'border-emerald-500 text-emerald-400'
                : 'border-transparent text-slate-400 hover:text-slate-200'
            }`}
          >
            <Sparkles className="h-3.5 w-3.5" />
            <span>Attribution & Origin</span>
          </button>
          <button
            onClick={() => setActiveTab('attachments')}
            className={`pb-2.5 px-3 font-semibold border-b-2 transition-all flex items-center gap-1.5 ${
              activeTab === 'attachments'
                ? 'border-emerald-500 text-emerald-400'
                : 'border-transparent text-slate-400 hover:text-slate-200'
            }`}
          >
            <Paperclip className="h-3.5 w-3.5" />
            <span>Files ({profile?.attachments?.length || 0})</span>
          </button>
        </div>

        {/* TAB CONTENTS */}
        <div className="flex-1 overflow-y-auto p-5 space-y-4">
          {isLoading ? (
            <div className="py-24 text-center text-xs text-slate-400 flex flex-col items-center gap-2">
              <Loader2 className="h-8 w-8 text-emerald-500 animate-spin" />
              <span>Loading 360-degree customer intelligence...</span>
            </div>
          ) : (
            <>
              {/* TAB 1: TIMELINE */}
              {activeTab === 'timeline' && (
                <div className="space-y-4">
                  {/* Quick Activity Creator */}
                  <form
                    onSubmit={handleLogActivity}
                    className={`p-3.5 rounded-2xl border space-y-2.5 ${
                      theme === 'dark' ? 'bg-slate-950/60 border-slate-800' : 'bg-slate-50 border-slate-200'
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <span className="text-xs font-semibold text-slate-200 flex items-center gap-1.5">
                        <Plus className="h-3.5 w-3.5 text-emerald-400" />
                        Log Activity / Touchpoint
                      </span>
                      <select
                        value={newActivityType}
                        onChange={(e) => setNewActivityType(e.target.value)}
                        className="px-2 py-0.5 rounded-lg border border-slate-700 bg-slate-900 text-[11px] text-slate-200 focus:outline-none"
                      >
                        <option value="note">Note</option>
                        <option value="call">Phone Call</option>
                        <option value="meeting">Meeting</option>
                        <option value="email">Email Sent</option>
                        <option value="task">Task</option>
                      </select>
                    </div>

                    <input
                      type="text"
                      required
                      placeholder="Title: e.g. Executive follow-up call"
                      value={newActivityTitle}
                      onChange={(e) => setNewActivityTitle(e.target.value)}
                      className="w-full px-3 py-1.5 rounded-xl border border-slate-700 bg-transparent text-xs focus:outline-none focus:border-emerald-500 text-slate-200"
                    />

                    <textarea
                      rows={2}
                      placeholder="Notes, discussion points, or next steps..."
                      value={newActivityDesc}
                      onChange={(e) => setNewActivityDesc(e.target.value)}
                      className="w-full px-3 py-1.5 rounded-xl border border-slate-700 bg-transparent text-xs focus:outline-none focus:border-emerald-500 text-slate-200"
                    />

                    <div className="flex justify-end">
                      <button
                        type="submit"
                        disabled={logActivityMutation.isPending}
                        className="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold flex items-center gap-1.5 shadow-xs transition-colors"
                      >
                        {logActivityMutation.isPending ? (
                          <Loader2 className="h-3 w-3 animate-spin" />
                        ) : (
                          <Send className="h-3 w-3" />
                        )}
                        <span>Save to Timeline</span>
                      </button>
                    </div>
                  </form>

                  {/* Chronological List */}
                  {profile?.timeline?.length === 0 ? (
                    <div className="py-16 text-center text-xs text-slate-400">
                      No activities logged yet on this customer timeline.
                    </div>
                  ) : (
                    <div className="relative pl-6 space-y-4 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-800">
                      {profile?.timeline?.map((act) => {
                        const Icon = getActivityIcon(act.activity_type);

                        return (
                          <div key={act.id} className="relative">
                            <div className="absolute -left-6 top-1 h-4 w-4 rounded-full bg-slate-900 border border-emerald-500 flex items-center justify-center">
                              <div className="h-1.5 w-1.5 rounded-full bg-emerald-400" />
                            </div>

                            <div className={`p-3 rounded-xl border text-xs ${
                              theme === 'dark' ? 'bg-slate-950/40 border-slate-800' : 'bg-white border-slate-200'
                            }`}>
                              <div className="flex items-center justify-between gap-2">
                                <span className="font-semibold text-slate-200 flex items-center gap-1.5">
                                  <Icon className="h-3.5 w-3.5 text-emerald-400" />
                                  {act.title}
                                </span>
                                <span className="text-[10px] font-mono text-slate-500">
                                  {act.created_at ? new Date(act.created_at).toLocaleString() : '—'}
                                </span>
                              </div>

                              {act.description && (
                                <p className="text-slate-300 mt-1 text-[11px] leading-relaxed">
                                  {act.description}
                                </p>
                              )}

                              <div className="mt-2 flex items-center justify-between text-[10px] text-slate-500 border-t border-slate-800/50 pt-1.5">
                                <span className="uppercase font-mono tracking-wider text-emerald-500">
                                  {act.activity_type}
                                </span>
                                <span>{act.user?.name || 'Automated CRM Engine'}</span>
                              </div>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  )}
                </div>
              )}

              {/* TAB 2: DEALS */}
              {activeTab === 'deals' && (
                <div className="space-y-3">
                  {profile?.deals?.items?.length === 0 ? (
                    <div className="py-16 text-center text-xs text-slate-400">
                      No sales deals linked to this customer account.
                    </div>
                  ) : (
                    profile?.deals?.items?.map((d: any) => (
                      <div
                        key={d.id}
                        className={`p-3.5 rounded-xl border flex items-center justify-between text-xs ${
                          theme === 'dark' ? 'bg-slate-950/40 border-slate-800' : 'bg-white border-slate-200'
                        }`}
                      >
                        <div>
                          <div className="flex items-center gap-2">
                            <span className="font-semibold text-slate-200">{d.name}</span>
                            <span className="text-[10px] font-mono uppercase px-2 py-0.5 rounded-full bg-slate-800 text-emerald-400 border border-slate-700">
                              {d.stage}
                            </span>
                          </div>
                          <p className="text-[11px] text-slate-400 mt-0.5">
                            Assigned to: {d.assignedUser?.name || 'Unassigned'} • Close Target: {d.close_date || 'TBD'}
                          </p>
                        </div>

                        <div className="text-right">
                          <span className="font-mono font-bold text-sm text-emerald-400">
                            ${Number(d.value).toLocaleString()}
                          </span>
                          <span className="block text-[10px] text-slate-500 font-mono">
                            {d.probability}% Prob
                          </span>
                        </div>
                      </div>
                    ))
                  )}
                </div>
              )}

              {/* TAB 3: ATTRIBUTION & ORIGIN */}
              {activeTab === 'attribution' && (
                <div className={`p-4 rounded-2xl border space-y-4 text-xs ${
                  theme === 'dark' ? 'bg-slate-950/40 border-slate-800' : 'bg-white border-slate-200'
                }`}>
                  <h4 className="font-semibold text-slate-200 flex items-center gap-2">
                    <Sparkles className="h-4 w-4 text-emerald-400" />
                    First-Touch & Campaign Attribution
                  </h4>

                  {profile?.lead_origin ? (
                    <div className="grid grid-cols-2 gap-3">
                      <div className="p-2.5 rounded-xl bg-slate-900 border border-slate-800">
                        <span className="text-[10px] text-slate-500 block">Lead Source</span>
                        <span className="font-semibold text-slate-200">{profile.lead_origin.source || 'Direct Inbound'}</span>
                      </div>
                      <div className="p-2.5 rounded-xl bg-slate-900 border border-slate-800">
                        <span className="text-[10px] text-slate-500 block">Campaign</span>
                        <span className="font-mono text-slate-200">{profile.lead_origin.campaign || 'Organic'}</span>
                      </div>
                      <div className="p-2.5 rounded-xl bg-slate-900 border border-slate-800">
                        <span className="text-[10px] text-slate-500 block">UTM Source / Medium</span>
                        <span className="font-mono text-slate-300">
                          {profile.lead_origin.utm_source || '—'} / {profile.lead_origin.utm_medium || '—'}
                        </span>
                      </div>
                      <div className="p-2.5 rounded-xl bg-slate-900 border border-slate-800">
                        <span className="text-[10px] text-slate-500 block">Conversion Date</span>
                        <span className="font-mono text-slate-300">
                          {profile.lead_origin.converted_at ? new Date(profile.lead_origin.converted_at).toLocaleDateString() : '—'}
                        </span>
                      </div>
                    </div>
                  ) : (
                    <div className="py-6 text-center text-xs text-slate-400">
                      Direct customer record with no upstream lead attribution.
                    </div>
                  )}
                </div>
              )}

              {/* TAB 4: ATTACHMENTS */}
              {activeTab === 'attachments' && (
                <div className="space-y-3">
                  {profile?.attachments?.length === 0 ? (
                    <div className="py-16 text-center text-xs text-slate-400">
                      No files or media attachments linked to this customer.
                    </div>
                  ) : (
                    profile?.attachments?.map((f) => (
                      <div
                        key={f.uuid}
                        className={`p-3 rounded-xl border flex items-center justify-between text-xs ${
                          theme === 'dark' ? 'bg-slate-950/40 border-slate-800' : 'bg-white border-slate-200'
                        }`}
                      >
                        <div className="flex items-center gap-2.5 truncate">
                          <Paperclip className="h-4 w-4 text-emerald-400 shrink-0" />
                          <div className="truncate">
                            <span className="font-semibold text-slate-200 block truncate">{f.original_name}</span>
                            <span className="text-[10px] font-mono text-slate-400">{f.mime_type}</span>
                          </div>
                        </div>

                        {f.download_url && (
                          <a
                            href={f.download_url}
                            target="_blank"
                            rel="noreferrer"
                            className="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-emerald-400 text-xs font-semibold flex items-center gap-1 transition-colors shrink-0"
                          >
                            <span>Download</span>
                            <ArrowUpRight className="h-3 w-3" />
                          </a>
                        )}
                      </div>
                    ))
                  )}
                </div>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
}
