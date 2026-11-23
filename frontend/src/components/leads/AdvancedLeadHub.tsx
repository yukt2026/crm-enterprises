import React, { useState } from 'react';
import {
  useLeadReportsDashboard,
  useLeadPortals,
  useSaveLeadPortal,
  useLeadRoutingRules,
  useSaveLeadRoutingRule,
  useDeleteLeadRoutingRule,
  useLeadSlaPolicies,
  useSaveLeadSlaPolicy,
  useEvaluateLeadSla,
  useLeadEscalationLogs,
  useOverdueFollowups,
  useCompleteLeadFollowup,
  useLeadMergeRequests,
  useApproveLeadMergeRequest,
  useRejectLeadMergeRequest,
  useLeadScoringConfig,
  useSaveLeadScoringConfig,
} from '../../hooks/useAdvancedLeads';
import {
  Target,
  ShieldAlert,
  Zap,
  Clock,
  GitMerge,
  Sliders,
  BarChart3,
  Globe,
  PhoneCall,
  CheckCircle,
  AlertTriangle,
  Play,
  RotateCw,
  Plus,
  Trash2,
  Check,
  X,
  ExternalLink,
  Users,
} from 'lucide-react';

export const AdvancedLeadHub: React.FC = () => {
  const [activeTab, setActiveTab] = useState<
    'analytics' | 'portals' | 'routing' | 'sla' | 'followups' | 'merges' | 'scoring'
  >('analytics');

  // Queries
  const { data: dashboard, isLoading: loadingDashboard, refetch: refetchDashboard } = useLeadReportsDashboard();
  const { data: portals = [], isLoading: loadingPortals } = useLeadPortals();
  const { data: rules = [], isLoading: loadingRules } = useLeadRoutingRules();
  const { data: slaPolicies = [] } = useLeadSlaPolicies();
  const { data: escalationLogs } = useLeadEscalationLogs();
  const { data: overdueFollowups = [] } = useOverdueFollowups();
  const { data: mergeRequests = [] } = useLeadMergeRequests();
  const { data: scoringConfig } = useLeadScoringConfig();

  // Mutations
  const evaluateSlaMutation = useEvaluateLeadSla();
  const savePortalMutation = useSaveLeadPortal();
  const saveRuleMutation = useSaveLeadRoutingRule();
  const deleteRuleMutation = useDeleteLeadRoutingRule();
  const saveSlaMutation = useSaveLeadSlaPolicy();
  const completeFollowupMutation = useCompleteLeadFollowup();
  const approveMergeMutation = useApproveLeadMergeRequest();
  const rejectMergeMutation = useRejectLeadMergeRequest();
  const saveScoringMutation = useSaveLeadScoringConfig();

  // Local Form States
  const [selectedOutcome, setSelectedOutcome] = useState<'connected' | 'interested' | 'callback_requested' | 'not_interested'>('connected');
  const [outcomeNotes, setOutcomeNotes] = useState('');
  const [activeFollowupId, setActiveFollowupId] = useState<number | null>(null);

  // New Rule Form
  const [showRuleModal, setShowRuleModal] = useState(false);
  const [ruleName, setRuleName] = useState('');
  const [ruleMethod, setRuleMethod] = useState<'round_robin' | 'least_active' | 'branch_based' | 'location_based'>('round_robin');
  const [ruleSources, setRuleSources] = useState('indiamart, website_form, justdial');

  const handleEvaluateSla = () => {
    evaluateSlaMutation.mutate();
  };

  const handleCompleteFollowup = (id: number) => {
    completeFollowupMutation.mutate({
      id,
      data: { outcome: selectedOutcome, notes: outcomeNotes },
    });
    setActiveFollowupId(null);
    setOutcomeNotes('');
  };

  const handleSaveRule = () => {
    if (!ruleName.trim()) return;
    saveRuleMutation.mutate({
      name: ruleName,
      priority: 1,
      is_active: true,
      assignment_method: ruleMethod,
      conditions: {
        sources: ruleSources.split(',').map((s) => s.trim().toLowerCase()),
      },
    });
    setShowRuleModal(false);
    setRuleName('');
  };

  return (
    <div className="space-y-6">
      {/* Header Banner */}
      <div className="bg-gradient-to-r from-blue-900 via-indigo-900 to-slate-900 rounded-xl p-6 text-white shadow-lg flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <div className="flex items-center gap-3">
            <Target className="w-8 h-8 text-blue-400" />
            <h1 className="text-2xl font-bold tracking-tight">Enterprise Lead Engine & SLA Guard</h1>
          </div>
          <p className="text-blue-200 text-sm mt-1">
            Multi-source Indian B2B capture (IndiaMART, Justdial, TradeIndia), routing balancer & zero-leakage SLA escalation.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={handleEvaluateSla}
            disabled={evaluateSlaMutation.isPending}
            className="flex items-center gap-2 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-slate-900 font-semibold rounded-lg text-sm shadow transition disabled:opacity-50"
          >
            <ShieldAlert className="w-4 h-4" />
            {evaluateSlaMutation.isPending ? 'Scanning SLA...' : 'Scan & Escalate SLA'}
          </button>
          <button
            onClick={() => refetchDashboard()}
            className="p-2 bg-slate-800/80 hover:bg-slate-700 text-slate-200 rounded-lg text-sm transition"
            title="Refresh Metrics"
          >
            <RotateCw className="w-4 h-4" />
          </button>
        </div>
      </div>

      {/* Navigation Tabs */}
      <div className="flex overflow-x-auto border-b border-slate-200 dark:border-slate-800 gap-1 pb-1">
        {[
          { id: 'analytics', label: 'Intelligence & Leakage', icon: BarChart3 },
          { id: 'portals', label: 'Indian B2B Portals', icon: Globe },
          { id: 'routing', label: 'Routing Rules', icon: Zap },
          { id: 'sla', label: 'SLA & Escalations', icon: Clock },
          { id: 'followups', label: `Follow-ups (${overdueFollowups.length} Overdue)`, icon: PhoneCall },
          { id: 'merges', label: `Duplicate Merges (${mergeRequests.length})`, icon: GitMerge },
          { id: 'scoring', label: 'Scoring Rules', icon: Sliders },
        ].map((tab) => {
          const Icon = tab.icon;
          const isActive = activeTab === tab.id;
          return (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id as any)}
              className={`flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition whitespace-nowrap ${
                isActive
                  ? 'bg-blue-600 text-white shadow-sm'
                  : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800'
              }`}
            >
              <Icon className="w-4 h-4" />
              {tab.label}
            </button>
          );
        })}
      </div>

      {/* TAB 1: INTELLIGENCE & LEAKAGE DASHBOARD */}
      {activeTab === 'analytics' && (
        <div className="space-y-6">
          {/* KPI Cards */}
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
              <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Leads Captured</span>
              <p className="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">
                {dashboard?.summary.total_leads ?? 0}
              </p>
              <span className="text-xs text-blue-600 font-medium">
                Active: {dashboard?.summary.active_leads ?? 0} leads
              </span>
            </div>
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
              <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Conversion Rate</span>
              <p className="text-2xl font-bold text-emerald-600 mt-1">
                {dashboard?.summary.conversion_rate_percent ?? 0}%
              </p>
              <span className="text-xs text-slate-500">
                Converted: {dashboard?.summary.converted_leads ?? 0} | Lost: {dashboard?.summary.lost_leads ?? 0}
              </span>
            </div>
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
              <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pipeline Value</span>
              <p className="text-2xl font-bold text-indigo-600 mt-1">
                ₹{(dashboard?.summary.total_pipeline_value ?? 0).toLocaleString()}
              </p>
              <span className="text-xs text-slate-500">Active pipeline volume</span>
            </div>
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
              <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">SLA Compliance Rate</span>
              <p
                className={`text-2xl font-bold mt-1 ${
                  (dashboard?.leakage_and_sla.compliance_rate_percent ?? 100) >= 90
                    ? 'text-emerald-600'
                    : 'text-rose-600'
                }`}
              >
                {dashboard?.leakage_and_sla.compliance_rate_percent ?? 100}%
              </p>
              <span className="text-xs text-rose-500">
                Breaches: {dashboard?.leakage_and_sla.breached_sla ?? 0} | Escalated: {dashboard?.leakage_and_sla.escalated_leads ?? 0}
              </span>
            </div>
          </div>

          {/* SLA Leakage & Escalations Summary */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm space-y-4">
              <div className="flex items-center justify-between">
                <h3 className="text-base font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                  <ShieldAlert className="w-5 h-5 text-amber-500" />
                  Lead Leakage Prevention (First Response SLA)
                </h3>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div className="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
                  <span className="text-xs text-slate-500">Tracked Leads</span>
                  <p className="text-lg font-bold text-slate-800 dark:text-slate-200">
                    {dashboard?.leakage_and_sla.total_sla_tracked_leads ?? 0}
                  </p>
                </div>
                <div className="p-3 bg-emerald-50 dark:bg-emerald-950/30 rounded-lg">
                  <span className="text-xs text-emerald-600 dark:text-emerald-400">Within SLA</span>
                  <p className="text-lg font-bold text-emerald-700 dark:text-emerald-300">
                    {dashboard?.leakage_and_sla.within_sla ?? 0}
                  </p>
                </div>
                <div className="p-3 bg-amber-50 dark:bg-amber-950/30 rounded-lg">
                  <span className="text-xs text-amber-600 dark:text-amber-400">Tier 1 Rep Warnings</span>
                  <p className="text-lg font-bold text-amber-700 dark:text-amber-300">
                    {dashboard?.leakage_and_sla.level_1_warnings ?? 0}
                  </p>
                </div>
                <div className="p-3 bg-rose-50 dark:bg-rose-950/30 rounded-lg">
                  <span className="text-xs text-rose-600 dark:text-rose-400">Tier 2 Auto-Reassigned</span>
                  <p className="text-lg font-bold text-rose-700 dark:text-rose-300">
                    {dashboard?.leakage_and_sla.level_2_reassignments ?? 0}
                  </p>
                </div>
              </div>
              <p className="text-xs text-slate-500">
                Policy: If uncontacted within 15 mins during business hours, Level 1 alert is sent. At 45 mins, lead is automatically reassigned to management.
              </p>
            </div>

            {/* Lead Ageing Distribution */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm space-y-4">
              <h3 className="text-base font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                <Clock className="w-5 h-5 text-blue-500" />
                Active Lead Ageing Breakdown
              </h3>
              <div className="space-y-2">
                {[
                  { label: '< 24 Hours', count: dashboard?.lead_ageing.under_24_hours ?? 0, color: 'bg-emerald-500' },
                  { label: '1 - 3 Days', count: dashboard?.lead_ageing['1_to_3_days'] ?? 0, color: 'bg-blue-500' },
                  { label: '4 - 7 Days', count: dashboard?.lead_ageing['4_to_7_days'] ?? 0, color: 'bg-amber-500' },
                  { label: '8 - 14 Days', count: dashboard?.lead_ageing['8_to_14_days'] ?? 0, color: 'bg-orange-500' },
                  { label: '15 - 30 Days', count: dashboard?.lead_ageing['15_to_30_days'] ?? 0, color: 'bg-rose-400' },
                  { label: '> 30 Days (Stale)', count: dashboard?.lead_ageing.over_30_days ?? 0, color: 'bg-rose-600' },
                ].map((item) => (
                  <div key={item.label} className="flex items-center justify-between text-xs">
                    <span className="w-32 text-slate-600 dark:text-slate-400">{item.label}</span>
                    <div className="flex-1 mx-3 bg-slate-100 dark:bg-slate-800 rounded-full h-2 overflow-hidden">
                      <div
                        className={`h-full ${item.color}`}
                        style={{
                          width: `${Math.min(
                            ((item.count || 0) / Math.max(dashboard?.summary.active_leads || 1, 1)) * 100,
                            100
                          )}%`,
                        }}
                      />
                    </div>
                    <span className="font-semibold text-slate-800 dark:text-slate-200 w-10 text-right">
                      {item.count}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Source Performance Table */}
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
            <div className="p-4 border-b border-slate-200 dark:border-slate-800">
              <h3 className="text-base font-semibold text-slate-900 dark:text-slate-100">
                Lead Source Attribution & Performance
              </h3>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                <thead className="bg-slate-50 dark:bg-slate-800/60 uppercase font-semibold text-slate-500">
                  <tr>
                    <th className="px-4 py-3">Source Channel</th>
                    <th className="px-4 py-3">Leads Count</th>
                    <th className="px-4 py-3">Converted</th>
                    <th className="px-4 py-3">Conversion Rate</th>
                    <th className="px-4 py-3">Pipeline Value</th>
                    <th className="px-4 py-3">Avg Lead Score</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {dashboard?.source_performance.map((sp) => (
                    <tr key={sp.source} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                      <td className="px-4 py-3 font-medium capitalize text-slate-900 dark:text-slate-100">
                        {sp.source}
                      </td>
                      <td className="px-4 py-3">{sp.total_leads}</td>
                      <td className="px-4 py-3 text-emerald-600 font-semibold">{sp.converted_count}</td>
                      <td className="px-4 py-3 font-semibold">{sp.conversion_rate}%</td>
                      <td className="px-4 py-3">₹{sp.pipeline_value.toLocaleString()}</td>
                      <td className="px-4 py-3">{sp.avg_score} / 100</td>
                    </tr>
                  ))}
                  {(!dashboard?.source_performance || dashboard.source_performance.length === 0) && (
                    <tr>
                      <td colSpan={6} className="text-center py-6 text-slate-400">
                        No source performance data logged yet.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 2: INDIAN B2B PORTALS */}
      {activeTab === 'portals' && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {[
              {
                id: 'indiamart',
                name: 'IndiaMART Lead Push API',
                desc: 'Instant lead ingestion from IndiaMART BuyLeads & Query push webhooks.',
                icon: '🇮🇳',
              },
              {
                id: 'justdial',
                name: 'Justdial Lead API',
                desc: 'Real-time local service & caller enquiry webhook ingestion.',
                icon: '📞',
              },
              {
                id: 'tradeindia',
                name: 'TradeIndia Inquiries',
                desc: 'B2B export & domestic trade inquiry normalization and routing.',
                icon: '🌐',
              },
            ].map((portal) => {
              const existing = portals.find((p) => p.portal === portal.id);
              return (
                <div
                  key={portal.id}
                  className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm space-y-4"
                >
                  <div className="flex items-center justify-between">
                    <span className="text-3xl">{portal.icon}</span>
                    <span
                      className={`text-xs px-2.5 py-0.5 rounded-full font-semibold ${
                        existing?.is_active
                          ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400'
                          : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                      }`}
                    >
                      {existing?.is_active ? 'Active' : 'Not Connected'}
                    </span>
                  </div>
                  <div>
                    <h3 className="font-semibold text-slate-900 dark:text-slate-100 text-base">{portal.name}</h3>
                    <p className="text-xs text-slate-500 mt-1">{portal.desc}</p>
                  </div>
                  <div className="text-xs bg-slate-50 dark:bg-slate-800/60 p-3 rounded-lg space-y-1">
                    <div className="flex justify-between">
                      <span className="text-slate-500">Captured Leads:</span>
                      <span className="font-bold text-slate-800 dark:text-slate-200">
                        {existing?.total_captured_count ?? 0}
                      </span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-slate-500">Webhook URL:</span>
                      <span className="font-mono text-[10px] text-blue-600 truncate max-w-[150px]">
                        /api/v1/crm/leads/portal/{portal.id}
                      </span>
                    </div>
                  </div>
                  <button
                    onClick={() =>
                      savePortalMutation.mutate({
                        portal: portal.id as any,
                        name: portal.name,
                        is_active: !existing?.is_active,
                        auto_assign: true,
                        auto_score: true,
                      })
                    }
                    disabled={savePortalMutation.isPending}
                    className={`w-full py-2 rounded-lg text-xs font-semibold transition ${
                      existing?.is_active
                        ? 'border border-rose-300 text-rose-600 hover:bg-rose-50'
                        : 'bg-blue-600 text-white hover:bg-blue-700'
                    }`}
                  >
                    {existing?.is_active ? 'Disable Integration' : 'Enable Integration'}
                  </button>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* TAB 3: ROUTING RULES */}
      {activeTab === 'routing' && (
        <div className="space-y-6">
          <div className="flex justify-between items-center">
            <div>
              <h3 className="font-semibold text-slate-900 dark:text-slate-100">Smart Lead Routing Rules</h3>
              <p className="text-xs text-slate-500">
                Automatic lead distribution based on location, source, budget, or least-active balancing.
              </p>
            </div>
            <button
              onClick={() => setShowRuleModal(true)}
              className="flex items-center gap-1.5 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold shadow transition"
            >
              <Plus className="w-4 h-4" />
              Add Routing Rule
            </button>
          </div>

          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
            <table className="w-full text-left text-xs text-slate-700 dark:text-slate-300">
              <thead className="bg-slate-50 dark:bg-slate-800/60 uppercase font-semibold text-slate-500">
                <tr>
                  <th className="px-4 py-3">Rule Name</th>
                  <th className="px-4 py-3">Assignment Method</th>
                  <th className="px-4 py-3">Conditions</th>
                  <th className="px-4 py-3">Priority</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {rules.map((rule) => (
                  <tr key={rule.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                    <td className="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">{rule.name}</td>
                    <td className="px-4 py-3 capitalize">
                      <span className="px-2 py-0.5 bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 rounded font-semibold">
                        {rule.assignment_method.replace('_', ' ')}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-slate-500">
                      {rule.conditions?.sources
                        ? `Sources: ${rule.conditions.sources.join(', ')}`
                        : 'Default / Catch-all'}
                    </td>
                    <td className="px-4 py-3 font-semibold">{rule.priority}</td>
                    <td className="px-4 py-3">
                      <span
                        className={`px-2 py-0.5 rounded text-[11px] font-semibold ${
                          rule.is_active
                            ? 'bg-emerald-100 text-emerald-700'
                            : 'bg-slate-100 text-slate-600'
                        }`}
                      >
                        {rule.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-right">
                      <button
                        onClick={() => deleteRuleMutation.mutate(rule.id)}
                        className="text-rose-600 hover:text-rose-800 p-1"
                        title="Delete Rule"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </td>
                  </tr>
                ))}
                {rules.length === 0 && (
                  <tr>
                    <td colSpan={6} className="text-center py-6 text-slate-400">
                      No routing rules configured yet. Standard round-robin applies.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          {/* Add Rule Modal */}
          {showRuleModal && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
              <div className="bg-white dark:bg-slate-900 rounded-xl p-6 max-w-md w-full shadow-xl space-y-4">
                <div className="flex justify-between items-center">
                  <h3 className="font-bold text-slate-900 dark:text-slate-100">Create Lead Routing Rule</h3>
                  <button onClick={() => setShowRuleModal(false)} className="text-slate-400 hover:text-slate-600">
                    <X className="w-5 h-5" />
                  </button>
                </div>
                <div className="space-y-3 text-xs">
                  <div>
                    <label className="font-semibold text-slate-700 dark:text-slate-300 block mb-1">Rule Name</label>
                    <input
                      type="text"
                      value={ruleName}
                      onChange={(e) => setRuleName(e.target.value)}
                      placeholder="e.g. Wholesale Portals Balancing"
                      className="w-full px-3 py-2 border rounded-lg dark:bg-slate-800 dark:border-slate-700"
                    />
                  </div>
                  <div>
                    <label className="font-semibold text-slate-700 dark:text-slate-300 block mb-1">
                      Assignment Method
                    </label>
                    <select
                      value={ruleMethod}
                      onChange={(e) => setRuleMethod(e.target.value as any)}
                      className="w-full px-3 py-2 border rounded-lg dark:bg-slate-800 dark:border-slate-700"
                    >
                      <option value="round_robin">Round Robin (Cyclic)</option>
                      <option value="least_active">Least Active (Fewest Open Leads)</option>
                      <option value="location_based">Location / Territory Based</option>
                      <option value="branch_based">Branch Based</option>
                    </select>
                  </div>
                  <div>
                    <label className="font-semibold text-slate-700 dark:text-slate-300 block mb-1">
                      Target Lead Sources (comma separated)
                    </label>
                    <input
                      type="text"
                      value={ruleSources}
                      onChange={(e) => setRuleSources(e.target.value)}
                      className="w-full px-3 py-2 border rounded-lg dark:bg-slate-800 dark:border-slate-700"
                    />
                  </div>
                </div>
                <div className="flex justify-end gap-2 pt-2">
                  <button
                    onClick={() => setShowRuleModal(false)}
                    className="px-4 py-2 border rounded-lg text-xs font-semibold text-slate-600"
                  >
                    Cancel
                  </button>
                  <button
                    onClick={handleSaveRule}
                    className="px-4 py-2 bg-blue-600 text-white rounded-lg text-xs font-semibold"
                  >
                    Save Rule
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {/* TAB 4: SLA POLICIES & ESCALATIONS */}
      {activeTab === 'sla' && (
        <div className="space-y-6">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm space-y-4">
            <h3 className="font-semibold text-slate-900 dark:text-slate-100 text-base">
              Configured SLA Policy & Timers
            </h3>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
              <div className="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
                <span className="text-slate-500 font-medium">First-Contact SLA</span>
                <p className="text-xl font-bold text-slate-800 dark:text-slate-200 mt-1">15 Minutes</p>
                <span className="text-slate-400 text-[11px]">During configured business hours</span>
              </div>
              <div className="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
                <span className="text-slate-500 font-medium">Tier 1 Rep Warning</span>
                <p className="text-xl font-bold text-amber-600 mt-1">At 15 Mins Overdue</p>
                <span className="text-slate-400 text-[11px]">Urgent push & in-app reminder</span>
              </div>
              <div className="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
                <span className="text-slate-500 font-medium">Tier 2 Auto-Reassignment</span>
                <p className="text-xl font-bold text-rose-600 mt-1">At 45 Mins Overdue</p>
                <span className="text-slate-400 text-[11px]">Reassigned to sales manager</span>
              </div>
            </div>
          </div>

          {/* Recent Escalations Log */}
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
            <div className="p-4 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
              <h3 className="font-semibold text-slate-900 dark:text-slate-100 text-sm">
                Recent Lead Escalation & Leakage Audit Trail
              </h3>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                <thead className="bg-slate-50 dark:bg-slate-800/60 uppercase font-semibold text-slate-500">
                  <tr>
                    <th className="px-4 py-3">Lead</th>
                    <th className="px-4 py-3">Escalation Level</th>
                    <th className="px-4 py-3">Reason</th>
                    <th className="px-4 py-3">Action Taken</th>
                    <th className="px-4 py-3">Triggered At</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {escalationLogs?.data?.map((log) => (
                    <tr key={log.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                      <td className="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                        {log.lead?.name ?? `Lead #${log.lead_id}`}
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`px-2 py-0.5 rounded text-[11px] font-bold ${
                            log.escalation_level === 2
                              ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300'
                              : 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300'
                          }`}
                        >
                          Level {log.escalation_level}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-slate-600 dark:text-slate-400">{log.reason}</td>
                      <td className="px-4 py-3 font-semibold text-slate-800 dark:text-slate-200">
                        {log.action_taken}
                      </td>
                      <td className="px-4 py-3 text-slate-400">
                        {new Date(log.triggered_at).toLocaleString()}
                      </td>
                    </tr>
                  ))}
                  {(!escalationLogs?.data || escalationLogs.data.length === 0) && (
                    <tr>
                      <td colSpan={5} className="text-center py-6 text-slate-400">
                        No SLA escalations recorded. All leads currently handled within SLA.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 5: FOLLOW-UPS & OVERDUE WATCH */}
      {activeTab === 'followups' && (
        <div className="space-y-6">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
            <div className="p-4 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center bg-rose-50/40 dark:bg-rose-950/20">
              <div>
                <h3 className="font-semibold text-rose-800 dark:text-rose-300 text-sm flex items-center gap-2">
                  <AlertTriangle className="w-4 h-4 text-rose-600" />
                  Overdue Follow-ups Requiring Immediate Contact
                </h3>
                <span className="text-xs text-rose-600 dark:text-rose-400">
                  {overdueFollowups.length} scheduled follow-ups have passed their deadline.
                </span>
              </div>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                <thead className="bg-slate-50 dark:bg-slate-800/60 uppercase font-semibold text-slate-500">
                  <tr>
                    <th className="px-4 py-3">Lead Name</th>
                    <th className="px-4 py-3">Type</th>
                    <th className="px-4 py-3">Scheduled For</th>
                    <th className="px-4 py-3">Assigned Rep</th>
                    <th className="px-4 py-3 text-right">Action</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {overdueFollowups.map((fu) => (
                    <tr key={fu.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                      <td className="px-4 py-3 font-semibold text-slate-900 dark:text-slate-100">
                        {fu.lead?.name ?? `Lead #${fu.lead_id}`}
                        <div className="text-[11px] text-slate-400 font-normal">{fu.lead?.phone}</div>
                      </td>
                      <td className="px-4 py-3 capitalize">
                        <span className="px-2 py-0.5 bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 rounded font-semibold">
                          {fu.followup_type}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-rose-600 font-semibold">
                        {new Date(fu.scheduled_at).toLocaleString()}
                      </td>
                      <td className="px-4 py-3">{fu.assigned_user?.name ?? 'Unassigned'}</td>
                      <td className="px-4 py-3 text-right">
                        <button
                          onClick={() => setActiveFollowupId(fu.id)}
                          className="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-semibold transition"
                        >
                          Log Outcome
                        </button>
                      </td>
                    </tr>
                  ))}
                  {overdueFollowups.length === 0 && (
                    <tr>
                      <td colSpan={5} className="text-center py-6 text-slate-400">
                        No overdue follow-ups. All customer touchpoints are on track!
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>

          {/* Outcome Modal */}
          {activeFollowupId && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
              <div className="bg-white dark:bg-slate-900 rounded-xl p-6 max-w-sm w-full shadow-xl space-y-4">
                <div className="flex justify-between items-center">
                  <h3 className="font-bold text-slate-900 dark:text-slate-100">Complete Follow-up</h3>
                  <button onClick={() => setActiveFollowupId(null)} className="text-slate-400 hover:text-slate-600">
                    <X className="w-5 h-5" />
                  </button>
                </div>
                <div className="space-y-3 text-xs">
                  <div>
                    <label className="font-semibold text-slate-700 dark:text-slate-300 block mb-1">
                      Call / Interaction Outcome
                    </label>
                    <select
                      value={selectedOutcome}
                      onChange={(e) => setSelectedOutcome(e.target.value as any)}
                      className="w-full px-3 py-2 border rounded-lg dark:bg-slate-800 dark:border-slate-700"
                    >
                      <option value="connected">Connected & Discussed</option>
                      <option value="interested">Hot Interest - Proposal Requested</option>
                      <option value="callback_requested">Call Back Requested</option>
                      <option value="not_interested">Not Interested / Closed</option>
                    </select>
                  </div>
                  <div>
                    <label className="font-semibold text-slate-700 dark:text-slate-300 block mb-1">
                      Interaction Notes
                    </label>
                    <textarea
                      value={outcomeNotes}
                      onChange={(e) => setOutcomeNotes(e.target.value)}
                      placeholder="Discussed requirements..."
                      className="w-full px-3 py-2 border rounded-lg dark:bg-slate-800 dark:border-slate-700 h-20"
                    />
                  </div>
                </div>
                <div className="flex justify-end gap-2 pt-2">
                  <button
                    onClick={() => setActiveFollowupId(null)}
                    className="px-3 py-1.5 border rounded-lg text-xs font-semibold text-slate-600"
                  >
                    Cancel
                  </button>
                  <button
                    onClick={() => handleCompleteFollowup(activeFollowupId)}
                    className="px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-semibold"
                  >
                    Save & Complete
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {/* TAB 6: DUPLICATE MERGE REQUESTS */}
      {activeTab === 'merges' && (
        <div className="space-y-6">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
            <div className="p-4 border-b border-slate-200 dark:border-slate-800">
              <h3 className="font-semibold text-slate-900 dark:text-slate-100 text-sm">
                Formal Duplicate Lead Merge Requests
              </h3>
              <p className="text-xs text-slate-500">
                Authorized review before merging duplicate customer records to preserve full timeline history.
              </p>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                <thead className="bg-slate-50 dark:bg-slate-800/60 uppercase font-semibold text-slate-500">
                  <tr>
                    <th className="px-4 py-3">Master Lead</th>
                    <th className="px-4 py-3">Duplicate Lead</th>
                    <th className="px-4 py-3">Requested By</th>
                    <th className="px-4 py-3">Status</th>
                    <th className="px-4 py-3 text-right">Action</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {mergeRequests.map((mr) => (
                    <tr key={mr.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                      <td className="px-4 py-3 font-semibold text-slate-900 dark:text-slate-100">
                        {mr.master_lead?.name}
                        <div className="text-[11px] text-slate-400 font-normal">
                          {mr.master_lead?.phone} • {mr.master_lead?.company_name}
                        </div>
                      </td>
                      <td className="px-4 py-3 font-semibold text-slate-900 dark:text-slate-100">
                        {mr.duplicate_lead?.name}
                        <div className="text-[11px] text-slate-400 font-normal">
                          {mr.duplicate_lead?.phone} • {mr.duplicate_lead?.company_name}
                        </div>
                      </td>
                      <td className="px-4 py-3">{mr.requested_by?.name ?? 'Sales Rep'}</td>
                      <td className="px-4 py-3">
                        <span
                          className={`px-2 py-0.5 rounded text-[11px] font-semibold ${
                            mr.status === 'approved'
                              ? 'bg-emerald-100 text-emerald-700'
                              : mr.status === 'rejected'
                              ? 'bg-rose-100 text-rose-700'
                              : 'bg-amber-100 text-amber-700'
                          }`}
                        >
                          {mr.status.toUpperCase()}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-right space-x-2">
                        {mr.status === 'pending' && (
                          <>
                            <button
                              onClick={() => approveMergeMutation.mutate(mr.id)}
                              className="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-semibold"
                            >
                              Approve Merge
                            </button>
                            <button
                              onClick={() => rejectMergeMutation.mutate({ id: mr.id, reason: 'Duplicate rejected' })}
                              className="px-2.5 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded text-xs font-semibold"
                            >
                              Reject
                            </button>
                          </>
                        )}
                      </td>
                    </tr>
                  ))}
                  {mergeRequests.length === 0 && (
                    <tr>
                      <td colSpan={5} className="text-center py-6 text-slate-400">
                        No pending merge requests.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 7: SCORING RULES */}
      {activeTab === 'scoring' && (
        <div className="space-y-6">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm space-y-4 max-w-xl">
            <h3 className="font-semibold text-slate-900 dark:text-slate-100 text-base">
              Lead Intent & Scoring Thresholds
            </h3>
            <p className="text-xs text-slate-500">
              Customize dynamic classification thresholds for Indian business prospects.
            </p>
            <div className="space-y-3 text-xs">
              <div className="flex justify-between items-center p-3 bg-rose-50 dark:bg-rose-950/20 rounded-lg">
                <span className="font-semibold text-rose-800 dark:text-rose-300">🔥 Hot Lead Threshold</span>
                <span className="text-rose-700 font-bold text-sm">Score &gt;= 70 pts</span>
              </div>
              <div className="flex justify-between items-center p-3 bg-amber-50 dark:bg-amber-950/20 rounded-lg">
                <span className="font-semibold text-amber-800 dark:text-amber-300">⚡ Warm Lead Threshold</span>
                <span className="text-amber-700 font-bold text-sm">Score 40 - 69 pts</span>
              </div>
              <div className="flex justify-between items-center p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
                <span className="font-semibold text-slate-800 dark:text-slate-300">❄️ Cold Lead Threshold</span>
                <span className="text-slate-700 font-bold text-sm">Score &lt; 40 pts</span>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
