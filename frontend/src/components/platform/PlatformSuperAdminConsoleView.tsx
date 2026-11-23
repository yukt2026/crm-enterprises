import React, { useState } from 'react';
import {
  ShieldAlert,
  Server,
  Building2,
  Users,
  CreditCard,
  TrendingUp,
  Cpu,
  Layers,
  Settings,
  AlertTriangle,
  CheckCircle2,
  XCircle,
  Clock,
  RefreshCw,
  PlusCircle,
  Eye,
  Key,
  Lock,
  Search,
  Filter,
  DollarSign,
  ArrowUpRight,
  Activity,
  Flame,
  Radio,
  FileText,
  ToggleLeft,
  ToggleRight,
  Terminal,
  ShieldCheck,
  Zap,
  Sparkles,
  Download,
  Database,
  Send,
  HardDrive,
  Copy,
  ExternalLink,
} from 'lucide-react';
import {
  usePlatformAnnouncements,
  useCreatePlatformAnnouncement,
  usePlatformBackups,
  useTriggerPlatformBackup,
  useIndustryTemplates,
  usePlatformRevenueReport,
} from '../../hooks/useSaasPlatform';
import {
  usePlatformOverview,
  usePlatformTenants,
  usePlatformTenantDetails,
  useUpdatePlatformTenantStatus,
  useChangePlatformTenantPlan,
  useCreateBreakglassSession,
  useRevokeBreakglassSession,
  usePlatformBillingPlans,
  useCreatePlatformPlan,
  useUpdatePlatformPlan,
  usePlatformSubscriptions,
  usePlatformInvoices,
  useAdjustTenantCredits,
  usePlatformProviders,
  useUpdatePlatformProvider,
  useTestPlatformProviderHealth,
  usePlatformQueueStats,
  useRetryPlatformQueueJob,
  usePurgePlatformQueueJobs,
  usePlatformFeatureFlags,
  useUpdatePlatformFeatureFlag,
  usePlatformMaintenanceMode,
  useSetPlatformMaintenanceMode,
  usePlatformAuditLogs,
} from '../../hooks/usePlatformAdmin';
import { PlatformTenantItem, PlatformProviderItem, SubscriptionPlanItem } from '../../api/types';

export const PlatformSuperAdminConsoleView: React.FC = () => {
  const [activeTab, setActiveTab] = useState<'overview' | 'tenants' | 'billing' | 'providers' | 'system' | 'templates' | 'announcements' | 'backups'>('overview');

  // Modals & Drawers state
  const [selectedTenantId, setSelectedTenantId] = useState<number | null>(null);
  const [statusModalTenant, setStatusModalTenant] = useState<PlatformTenantItem | null>(null);
  const [newStatus, setNewStatus] = useState<'active' | 'suspended' | 'cancelled'>('suspended');
  const [statusReason, setStatusReason] = useState<string>('');

  const [planModalTenant, setPlanModalTenant] = useState<PlatformTenantItem | null>(null);
  const [targetPlanId, setTargetPlanId] = useState<number>(1);
  const [proratePlan, setProratePlan] = useState<boolean>(true);

  const [breakglassModalTenant, setBreakglassModalTenant] = useState<PlatformTenantItem | null>(null);
  const [justificationReason, setJustificationReason] = useState<string>('');
  const [breakglassResult, setBreakglassResult] = useState<any>(null);

  const [editProviderModal, setEditProviderModal] = useState<PlatformProviderItem | null>(null);
  const [providerCredentials, setProviderCredentials] = useState<string>('');
  const [providerDefaultModel, setProviderDefaultModel] = useState<string>('');

  const [isPlanCreateModalOpen, setIsPlanCreateModalOpen] = useState(false);
  const [newPlanForm, setNewPlanForm] = useState<Partial<SubscriptionPlanItem>>({
    name: '',
    code: '',
    billing_cycle: 'monthly',
    price_monthly: 99,
    price_annual: 990,
    trial_days: 14,
    grace_period_days: 7,
    seat_limit: 10,
    whatsapp_number_limit: 2,
    ai_agent_limit: 5,
    workflow_limit: 25,
    api_rpm_limit: 300,
    features: ['leads', 'deals', 'whatsapp_inbox', 'workflow_automations'],
  });

  const [isCreditAdjustModalOpen, setIsCreditAdjustModalOpen] = useState(false);
  const [adjustTenantId, setAdjustTenantId] = useState<number>(1);
  const [adjustAmount, setAdjustAmount] = useState<number>(50);
  const [adjustReason, setAdjustReason] = useState<string>('');

  // Prompt 50 Super Admin state
  const [newAnnouncement, setNewAnnouncement] = useState({
    title: '',
    message: '',
    type: 'info' as 'info' | 'warning' | 'critical' | 'release',
    target_audience: 'all' as 'all' | 'specific_tenants' | 'admins_only',
    action_url: '',
  });
  const [backupNotes, setBackupNotes] = useState<string>('');
  const [backupType, setBackupType] = useState<string>('full');

  // Search & filter state
  const [tenantSearch, setTenantSearch] = useState<string>('');
  const [tenantStatusFilter, setTenantStatusFilter] = useState<string>('');

  // Queries
  const { data: overview, isLoading: loadingOverview, refetch: refetchOverview } = usePlatformOverview();
  const { data: tenantsData, isLoading: loadingTenants } = usePlatformTenants({
    search: tenantSearch,
    status: tenantStatusFilter,
  });
  const { data: tenantDetail } = usePlatformTenantDetails(selectedTenantId);
  const { data: plans = [] } = usePlatformBillingPlans();
  const { data: subscriptionsData } = usePlatformSubscriptions();
  const { data: invoicesData } = usePlatformInvoices();
  const { data: providers = [], isLoading: loadingProviders } = usePlatformProviders();
  const { data: queueStats } = usePlatformQueueStats();
  const { data: featureFlags = [] } = usePlatformFeatureFlags();
  const { data: maintenanceMode } = usePlatformMaintenanceMode();
  const { data: auditLogsData } = usePlatformAuditLogs({ per_page: 30 });

  // Prompt 50 SaaS Queries & Mutations
  const { data: industryTemplates = [] } = useIndustryTemplates();
  const { data: announcementsData = [], refetch: refetchAnnouncements } = usePlatformAnnouncements();
  const { data: backupsData = [], refetch: refetchBackups } = usePlatformBackups();
  const { data: saasRevenueData } = usePlatformRevenueReport();

  const createAnnouncementMutation = useCreatePlatformAnnouncement();
  const triggerBackupMutation = useTriggerPlatformBackup();

  // Mutations
  const updateStatusMutation = useUpdatePlatformTenantStatus();
  const changePlanMutation = useChangePlatformTenantPlan();
  const breakglassMutation = useCreateBreakglassSession();
  const revokeBreakglassMutation = useRevokeBreakglassSession();
  const createPlanMutation = useCreatePlatformPlan();
  const adjustCreditMutation = useAdjustTenantCredits();
  const updateProviderMutation = useUpdatePlatformProvider();
  const testHealthMutation = useTestPlatformProviderHealth();
  const retryQueueMutation = useRetryPlatformQueueJob();
  const purgeQueueMutation = usePurgePlatformQueueJobs();
  const updateFlagMutation = useUpdatePlatformFeatureFlag();
  const setMaintenanceMutation = useSetPlatformMaintenanceMode();

  const handleStatusSubmit = async () => {
    if (!statusModalTenant || !statusReason.trim()) return;
    try {
      await updateStatusMutation.mutateAsync({
        id: statusModalTenant.id,
        status: newStatus,
        reason: statusReason,
      });
      setStatusModalTenant(null);
      setStatusReason('');
    } catch (err: any) {
      alert(err?.message || 'Failed to update tenant status');
    }
  };

  const handlePlanSubmit = async () => {
    if (!planModalTenant) return;
    try {
      await changePlanMutation.mutateAsync({
        id: planModalTenant.id,
        planId: targetPlanId,
        prorate: proratePlan,
      });
      setPlanModalTenant(null);
    } catch (err: any) {
      alert(err?.message || 'Failed to change plan');
    }
  };

  const handleBreakglassSubmit = async () => {
    if (!breakglassModalTenant || !justificationReason.trim()) return;
    try {
      const res = await breakglassMutation.mutateAsync({
        tenantId: breakglassModalTenant.id,
        justificationReason,
        durationMinutes: 60,
      });
      setBreakglassResult(res);
      setJustificationReason('');
    } catch (err: any) {
      alert(err?.message || 'Breakglass session denied');
    }
  };

  const handleProviderSave = async () => {
    if (!editProviderModal) return;
    try {
      await updateProviderMutation.mutateAsync({
        key: editProviderModal.provider_key,
        data: {
          default_model: providerDefaultModel || editProviderModal.default_model || undefined,
          credentials: providerCredentials ? { api_key: providerCredentials } : undefined,
        },
      });
      setEditProviderModal(null);
      setProviderCredentials('');
    } catch (err: any) {
      alert(err?.message || 'Failed to update provider');
    }
  };

  const handleCreditAdjustSubmit = async () => {
    if (!adjustReason.trim()) return;
    try {
      await adjustCreditMutation.mutateAsync({
        tenantId: adjustTenantId,
        amount: adjustAmount,
        reason: adjustReason,
      });
      setIsCreditAdjustModalOpen(false);
      setAdjustReason('');
    } catch (err: any) {
      alert(err?.message || 'Failed to adjust credits');
    }
  };

  const handleCreatePlanSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await createPlanMutation.mutateAsync(newPlanForm);
      setIsPlanCreateModalOpen(false);
    } catch (err: any) {
      alert(err?.message || 'Failed to create plan');
    }
  };

  return (
    <div className="space-y-6">
      {/* Top Banner with Distinctive Platform Command Center Styling */}
      <div className="bg-gradient-to-r from-red-950 via-slate-900 to-indigo-950 border border-red-500/30 rounded-2xl p-6 text-white shadow-xl">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div className="flex items-center gap-4">
            <div className="p-3 bg-red-600/20 text-red-400 rounded-2xl border border-red-500/30 shadow-inner">
              <ShieldAlert className="w-8 h-8" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h1 className="text-2xl font-black tracking-tight text-white">Platform Super Admin Console</h1>
                <span className="bg-red-500/20 text-red-400 text-[10px] font-extrabold px-2.5 py-0.5 rounded-full uppercase tracking-wider border border-red-500/40">
                  Global Root Authority
                </span>
              </div>
              <p className="text-xs text-slate-300 mt-1">
                Cross-tenant governance, billing reconciliations, AI routing engines, and infrastructure operations
              </p>
            </div>
          </div>

          {/* Quick System Status & Maintenance Switch */}
          <div className="flex items-center gap-3">
            <div
              className={`px-3 py-1.5 rounded-xl border text-xs font-semibold flex items-center gap-2 ${
                maintenanceMode?.is_enabled
                  ? 'bg-amber-500/20 text-amber-300 border-amber-500/40'
                  : 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30'
              }`}
            >
              <span className={`w-2 h-2 rounded-full ${maintenanceMode?.is_enabled ? 'bg-amber-400 animate-pulse' : 'bg-emerald-400'}`} />
              {maintenanceMode?.is_enabled ? 'Maintenance Active' : 'All Systems Operational'}
            </div>

            <button
              onClick={() =>
                setMaintenanceMutation.mutate({
                  is_enabled: !maintenanceMode?.is_enabled,
                  message: maintenanceMode?.is_enabled ? undefined : 'Scheduled core platform maintenance.',
                })
              }
              className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl text-xs font-semibold transition"
            >
              {maintenanceMode?.is_enabled ? 'Disable Maintenance' : 'Enable Maintenance'}
            </button>

            <button
              onClick={() => refetchOverview()}
              className="p-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl transition"
              title="Refresh Global Telemetry"
            >
              <RefreshCw className="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      {/* Navigation Tabs */}
      <div className="flex border-b border-slate-800 space-x-1 overflow-x-auto">
        {[
          { id: 'overview', label: 'Platform Telemetry', icon: Activity },
          { id: 'tenants', label: 'Tenants Directory', icon: Building2 },
          { id: 'billing', label: 'Plans & GST Billing', icon: CreditCard },
          { id: 'templates', label: 'Industry Blueprints', icon: Sparkles },
          { id: 'announcements', label: 'Announcements', icon: Radio },
          { id: 'backups', label: 'Backups & DR', icon: HardDrive },
          { id: 'providers', label: 'AI & Telephony', icon: Cpu },
          { id: 'system', label: 'Security & Ops', icon: Server },
        ].map((tab) => {
          const Icon = tab.icon;
          const isActive = activeTab === tab.id;
          return (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id as any)}
              className={`flex items-center gap-2 px-4 py-3 text-sm font-semibold border-b-2 transition -mb-px ${
                isActive
                  ? 'border-red-500 text-red-400 bg-red-500/5'
                  : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-700'
              }`}
            >
              <Icon className="w-4 h-4" />
              {tab.label}
            </button>
          );
        })}
      </div>

      {/* TAB 1: Platform Telemetry & Overview */}
      {activeTab === 'overview' && (
        <div className="space-y-6">
          {/* Top KPI Cards */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div className="bg-slate-900 border border-slate-800 p-5 rounded-2xl">
              <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-slate-400">Total Registered Tenants</span>
                <Building2 className="w-4 h-4 text-blue-400" />
              </div>
              <div className="text-3xl font-extrabold text-white mt-2">
                {overview?.tenants.total ?? 0}
              </div>
              <div className="text-[11px] text-slate-400 mt-2 flex items-center gap-2">
                <span className="text-emerald-400 font-semibold">{overview?.tenants.active ?? 0} Active</span>
                <span>•</span>
                <span className="text-amber-400">{overview?.tenants.trialing ?? 0} Trial</span>
                <span>•</span>
                <span className="text-red-400">{overview?.tenants.suspended ?? 0} Suspended</span>
              </div>
            </div>

            <div className="bg-slate-900 border border-slate-800 p-5 rounded-2xl">
              <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-slate-400">Monthly Recurring Revenue (MRR)</span>
                <DollarSign className="w-4 h-4 text-emerald-400" />
              </div>
              <div className="text-3xl font-extrabold text-emerald-400 mt-2">
                ${overview?.financials.mrr.toFixed(2) ?? '0.00'}
              </div>
              <div className="text-[11px] text-slate-400 mt-2">
                Annual Run Rate (ARR): <span className="text-white font-semibold">${overview?.financials.arr.toFixed(2) ?? '0.00'}</span>
              </div>
            </div>

            <div className="bg-slate-900 border border-slate-800 p-5 rounded-2xl">
              <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-slate-400">Total Collections & Top-ups</span>
                <CreditCard className="w-4 h-4 text-purple-400" />
              </div>
              <div className="text-3xl font-extrabold text-white mt-2">
                ${overview?.financials.total_collections.toFixed(2) ?? '0.00'}
              </div>
              <div className="text-[11px] text-slate-400 mt-2">
                {overview?.financials.active_subscriptions ?? 0} active subscriptions billed
              </div>
            </div>

            <div className="bg-slate-900 border border-slate-800 p-5 rounded-2xl">
              <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-slate-400">Gross Margin & Wholesale COGS</span>
                <TrendingUp className="w-4 h-4 text-cyan-400" />
              </div>
              <div className="text-3xl font-extrabold text-cyan-400 mt-2">
                {overview?.financials.gross_margin_percent ?? 100}%
              </div>
              <div className="text-[11px] text-slate-400 mt-2">
                Billed: ${overview?.financials.retail_usage_billed.toFixed(2) ?? '0.00'} • Upstream COGS: ${overview?.financials.wholesale_provider_cost.toFixed(2) ?? '0.00'}
              </div>
            </div>
          </div>

          {/* Usage Volumes Breakdown */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="bg-slate-900 border border-slate-800 p-6 rounded-2xl">
              <h3 className="text-base font-bold text-white mb-4 flex items-center gap-2">
                <Zap className="w-4 h-4 text-amber-400" />
                Cross-Tenant Consumption Volumes
              </h3>
              <div className="space-y-4 text-xs">
                <div className="flex justify-between p-3 bg-slate-800/40 rounded-xl">
                  <span className="text-slate-400">AI LLM Tokens Consumed</span>
                  <span className="text-white font-bold font-mono">
                    {overview?.usage_volumes.ai_tokens.toLocaleString() ?? 0} tokens
                  </span>
                </div>
                <div className="flex justify-between p-3 bg-slate-800/40 rounded-xl">
                  <span className="text-slate-400">Voice & Telephony Minutes</span>
                  <span className="text-white font-bold font-mono">
                    {overview?.usage_volumes.calling_minutes ?? 0} mins
                  </span>
                </div>
                <div className="flex justify-between p-3 bg-slate-800/40 rounded-xl">
                  <span className="text-slate-400">WhatsApp Official Messages</span>
                  <span className="text-white font-bold font-mono">
                    {overview?.usage_volumes.whatsapp_messages.toLocaleString() ?? 0} msgs
                  </span>
                </div>
                <div className="flex justify-between p-3 bg-slate-800/40 rounded-xl">
                  <span className="text-slate-400">SMS Segments</span>
                  <span className="text-white font-bold font-mono">
                    {overview?.usage_volumes.sms_segments.toLocaleString() ?? 0} segments
                  </span>
                </div>
                <div className="flex justify-between p-3 bg-slate-800/40 rounded-xl">
                  <span className="text-slate-400">Marketing Email Volume</span>
                  <span className="text-white font-bold font-mono">
                    {overview?.usage_volumes.email_volume.toLocaleString() ?? 0} emails
                  </span>
                </div>
              </div>
            </div>

            <div className="bg-slate-900 border border-slate-800 p-6 rounded-2xl">
              <h3 className="text-base font-bold text-white mb-4 flex items-center gap-2">
                <Server className="w-4 h-4 text-emerald-400" />
                Platform Infrastructure & Health Matrix
              </h3>
              <div className="space-y-4 text-xs">
                <div className="flex justify-between items-center p-3 bg-slate-800/40 rounded-xl">
                  <span className="text-slate-400">Database Core (MySQL Enterprise)</span>
                  <span className="flex items-center gap-1.5 text-emerald-400 font-bold uppercase">
                    <CheckCircle2 className="w-3.5 h-3.5" />
                    {overview?.infrastructure.system_health.database ?? 'healthy'}
                  </span>
                </div>
                <div className="flex justify-between items-center p-3 bg-slate-800/40 rounded-xl">
                  <span className="text-slate-400">In-Memory Cache (Redis Cluster)</span>
                  <span className="flex items-center gap-1.5 text-emerald-400 font-bold uppercase">
                    <CheckCircle2 className="w-3.5 h-3.5" />
                    {overview?.infrastructure.system_health.cache ?? 'healthy'}
                  </span>
                </div>
                <div className="flex justify-between items-center p-3 bg-slate-800/40 rounded-xl">
                  <span className="text-slate-400">Failed Background Queue Jobs</span>
                  <span
                    className={`font-bold font-mono ${
                      (overview?.infrastructure.failed_jobs ?? 0) > 0 ? 'text-red-400' : 'text-slate-300'
                    }`}
                  >
                    {overview?.infrastructure.failed_jobs ?? 0} failures
                  </span>
                </div>
                <div className="flex justify-between items-center p-3 bg-slate-800/40 rounded-xl">
                  <span className="text-slate-400">Third-Party Integration Errors</span>
                  <span
                    className={`font-bold font-mono ${
                      (overview?.infrastructure.failed_integrations ?? 0) > 0 ? 'text-amber-400' : 'text-slate-300'
                    }`}
                  >
                    {overview?.infrastructure.failed_integrations ?? 0} degraded
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* TAB 2: Tenants Directory & Governance */}
      {activeTab === 'tenants' && (
        <div className="space-y-4">
          {/* Filter Bar */}
          <div className="flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-900 border border-slate-800 p-4 rounded-xl">
            <div className="flex items-center gap-2 w-full sm:w-auto">
              <div className="relative w-full sm:w-64">
                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
                <input
                  type="text"
                  placeholder="Search business name, slug..."
                  value={tenantSearch}
                  onChange={(e) => setTenantSearch(e.target.value)}
                  className="w-full pl-9 pr-3 py-1.5 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:border-red-500"
                />
              </div>

              <select
                value={tenantStatusFilter}
                onChange={(e) => setTenantStatusFilter(e.target.value)}
                className="px-3 py-1.5 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none"
              >
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="trialing">Trialing</option>
                <option value="suspended">Suspended</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>

            <div className="text-xs text-slate-400">
              Showing <span className="font-bold text-white">{tenantsData?.items.length ?? 0}</span> tenants
            </div>
          </div>

          {/* Tenants Table */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow">
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs text-slate-300">
                <thead className="bg-slate-800/60 uppercase text-[10px] font-semibold text-slate-400">
                  <tr>
                    <th className="px-4 py-3">Business Name</th>
                    <th className="px-4 py-3">Slug</th>
                    <th className="px-4 py-3">Status</th>
                    <th className="px-4 py-3">Current Plan</th>
                    <th className="px-4 py-3">Credit Wallet</th>
                    <th className="px-4 py-3">Users</th>
                    <th className="px-4 py-3 text-right">Governance Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800">
                  {tenantsData?.items.map((t) => (
                    <tr key={t.id} className="hover:bg-slate-800/30">
                      <td className="px-4 py-3 font-bold text-white flex items-center gap-2">
                        <Building2 className="w-4 h-4 text-blue-400" />
                        {t.name}
                      </td>
                      <td className="px-4 py-3 font-mono text-slate-400">{t.slug}</td>
                      <td className="px-4 py-3">
                        <span
                          className={`px-2 py-0.5 rounded text-[10px] uppercase font-bold ${
                            t.status === 'active'
                              ? 'bg-emerald-500/20 text-emerald-400'
                              : t.status === 'trialing'
                              ? 'bg-blue-500/20 text-blue-400'
                              : t.status === 'suspended'
                              ? 'bg-red-500/20 text-red-400'
                              : 'bg-slate-700 text-slate-400'
                          }`}
                        >
                          {t.status}
                        </span>
                      </td>
                      <td className="px-4 py-3 font-semibold text-slate-200">
                        {t.subscription?.plan?.name || t.subscription_tier || 'Starter'}
                      </td>
                      <td className="px-4 py-3 font-mono font-bold text-emerald-400">
                        ${t.wallet ? t.wallet.balance.toFixed(2) : '0.00'}
                      </td>
                      <td className="px-4 py-3">{t.users_count ?? 1}</td>
                      <td className="px-4 py-3 text-right space-x-2">
                        <button
                          onClick={() => setSelectedTenantId(t.id)}
                          className="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded text-[11px] font-semibold border border-slate-700"
                          title="Inspect Details"
                        >
                          Inspect
                        </button>
                        <button
                          onClick={() => {
                            setPlanModalTenant(t);
                            setTargetPlanId(t.subscription?.plan_id || 1);
                          }}
                          className="px-2 py-1 bg-blue-600/20 hover:bg-blue-600/30 text-blue-300 rounded text-[11px] font-semibold border border-blue-500/30"
                        >
                          Plan
                        </button>
                        <button
                          onClick={() => {
                            setStatusModalTenant(t);
                            setNewStatus(t.status === 'suspended' ? 'active' : 'suspended');
                          }}
                          className={`px-2 py-1 rounded text-[11px] font-semibold border ${
                            t.status === 'suspended'
                              ? 'bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 border-emerald-500/30'
                              : 'bg-red-600/20 hover:bg-red-600/30 text-red-300 border-red-500/30'
                          }`}
                        >
                          {t.status === 'suspended' ? 'Restore' : 'Suspend'}
                        </button>
                        <button
                          onClick={() => setBreakglassModalTenant(t)}
                          className="px-2 py-1 bg-purple-600/20 hover:bg-purple-600/30 text-purple-300 rounded text-[11px] font-semibold border border-purple-500/30"
                          title="Breakglass Justified Support Access"
                        >
                          Breakglass
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 3: Plans & Commercial Billing */}
      {activeTab === 'billing' && (
        <div className="space-y-6">
          <div className="flex items-center justify-between bg-slate-900 border border-slate-800 p-4 rounded-xl">
            <div>
              <h3 className="text-sm font-bold text-white">Commercial Subscription Plans Catalog</h3>
              <p className="text-xs text-slate-400">Configure global pricing tiers, features, allowances, and overage rates</p>
            </div>
            <div className="flex items-center gap-2">
              <button
                onClick={() => setIsCreditAdjustModalOpen(true)}
                className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl text-xs font-semibold"
              >
                Manual Credit Adjustment
              </button>
              <button
                onClick={() => setIsPlanCreateModalOpen(true)}
                className="px-3 py-1.5 bg-red-600 hover:bg-red-500 text-white rounded-xl text-xs font-semibold flex items-center gap-1 shadow"
              >
                <PlusCircle className="w-3.5 h-3.5" />
                New Plan
              </button>
            </div>
          </div>

          {/* Plans Grid */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {plans.map((p) => (
              <div key={p.id} className="bg-slate-900 border border-slate-800 p-5 rounded-2xl space-y-3">
                <div className="flex justify-between items-center">
                  <h4 className="text-base font-bold text-white">{p.name}</h4>
                  <span className="font-mono text-[10px] text-slate-400 uppercase bg-slate-800 px-2 py-0.5 rounded">
                    {p.plan_code}
                  </span>
                </div>
                <div className="text-2xl font-extrabold text-white">
                  ${p.price_monthly.toFixed(2)} <span className="text-xs text-slate-400 font-normal">/ mo</span>
                </div>
                <div className="text-xs text-slate-300 space-y-1 border-t border-slate-800 pt-3">
                  <div className="flex justify-between">
                    <span className="text-slate-400">Seats Limit:</span>
                    <span>{p.seat_limit} users</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-400">Included Tokens:</span>
                    <span>{p.allowances?.llm_tokens?.toLocaleString() || 0}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-400">AI Calling Mins:</span>
                    <span>{p.allowances?.ai_call_minutes || 0} mins</span>
                  </div>
                </div>
              </div>
            ))}
          </div>

          {/* Subscriptions Ledger */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6">
            <h3 className="text-base font-bold text-white mb-4">Active Subscriptions Roster</h3>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs text-slate-300">
                <thead className="bg-slate-800/60 uppercase text-[10px] font-semibold text-slate-400">
                  <tr>
                    <th className="px-4 py-3">Tenant</th>
                    <th className="px-4 py-3">Plan</th>
                    <th className="px-4 py-3">Status</th>
                    <th className="px-4 py-3">Billing Cycle</th>
                    <th className="px-4 py-3">Price</th>
                    <th className="px-4 py-3">Current Period End</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800">
                  {subscriptionsData?.items.map((s) => (
                    <tr key={s.id} className="hover:bg-slate-800/30">
                      <td className="px-4 py-3 font-semibold text-white">{s.plan?.name ? 'Tenant #' + s.tenant_id : 'Default'}</td>
                      <td className="px-4 py-3">{s.plan?.name || 'Starter'}</td>
                      <td className="px-4 py-3">
                        <span className="px-2 py-0.5 rounded text-[10px] uppercase font-bold bg-emerald-500/20 text-emerald-400">
                          {s.status}
                        </span>
                      </td>
                      <td className="px-4 py-3 capitalize">{s.billing_cycle}</td>
                      <td className="px-4 py-3 font-mono">${(s.current_price ?? 49).toFixed(2)}</td>
                      <td className="px-4 py-3 text-slate-400">
                        {s.current_period_end ? new Date(s.current_period_end).toLocaleDateString() : 'Active'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 4: AI & Telephony Providers */}
      {activeTab === 'providers' && (
        <div className="space-y-6">
          <div className="bg-slate-900 border border-slate-800 p-4 rounded-xl">
            <h3 className="text-sm font-bold text-white">Platform Upstream AI & Telephony Adapters</h3>
            <p className="text-xs text-slate-400">
              Provider API secrets are strictly encrypted at rest using AES-256 and never exposed in plain text.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {providers.map((pr) => (
              <div key={pr.id} className="bg-slate-900 border border-slate-800 rounded-2xl p-5 space-y-4">
                <div className="flex items-center justify-between">
                  <div>
                    <h4 className="text-base font-bold text-white">{pr.name}</h4>
                    <span className="text-[10px] font-mono uppercase text-slate-400 bg-slate-800 px-2 py-0.5 rounded">
                      {pr.category}
                    </span>
                  </div>
                  <span
                    className={`px-2 py-0.5 rounded text-[10px] uppercase font-bold ${
                      pr.health_status === 'healthy'
                        ? 'bg-emerald-500/20 text-emerald-400'
                        : 'bg-amber-500/20 text-amber-400'
                    }`}
                  >
                    {pr.health_status}
                  </span>
                </div>

                <div className="text-xs space-y-1.5 text-slate-300 border-t border-slate-800 pt-3">
                  <div className="flex justify-between">
                    <span className="text-slate-400">Configured:</span>
                    <span className="font-semibold text-emerald-400">{pr.is_configured ? 'Yes (Encrypted)' : 'No'}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-400">Default Model:</span>
                    <span className="font-mono">{pr.default_model || 'Standard'}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-400">Rate Limit:</span>
                    <span>{pr.rate_limit_rpm} RPM</span>
                  </div>
                </div>

                <div className="flex items-center gap-2 pt-2 border-t border-slate-800">
                  <button
                    onClick={() => {
                      setEditProviderModal(pr);
                      setProviderDefaultModel(pr.default_model || '');
                    }}
                    className="flex-1 py-1.5 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-semibold border border-slate-700 transition"
                  >
                    Configure Keys
                  </button>
                  <button
                    onClick={() => testHealthMutation.mutate(pr.provider_key)}
                    disabled={testHealthMutation.isPending}
                    className="px-3 py-1.5 bg-blue-600/20 hover:bg-blue-600/30 text-blue-300 rounded-xl text-xs font-semibold border border-blue-500/30 transition"
                  >
                    Ping Health
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* TAB 5: System Operations & Security Guardrails */}
      {activeTab === 'system' && (
        <div className="space-y-6">
          {/* Queues & Background Workers */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6">
            <div className="flex items-center justify-between mb-4">
              <div>
                <h3 className="text-base font-bold text-white">Async Queues & Failed Jobs</h3>
                <p className="text-xs text-slate-400">
                  Pending: {queueStats?.pending_jobs_count ?? 0} • Failed: {queueStats?.failed_jobs_count ?? 0}
                </p>
              </div>
              {(queueStats?.failed_jobs_count ?? 0) > 0 && (
                <button
                  onClick={() => purgeQueueMutation.mutate()}
                  className="px-3 py-1.5 bg-red-600 hover:bg-red-500 text-white rounded-xl text-xs font-semibold"
                >
                  Purge All Failed Jobs
                </button>
              )}
            </div>

            {queueStats?.failed_jobs && queueStats.failed_jobs.length > 0 ? (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs text-slate-300">
                  <thead className="bg-slate-800/60 uppercase text-[10px] font-semibold text-slate-400">
                    <tr>
                      <th className="px-4 py-2">ID</th>
                      <th className="px-4 py-2">Queue</th>
                      <th className="px-4 py-2">Failed At</th>
                      <th className="px-4 py-2 text-right">Action</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-800">
                    {queueStats.failed_jobs.map((job) => (
                      <tr key={job.id}>
                        <td className="px-4 py-2 font-mono text-slate-400">{job.id}</td>
                        <td className="px-4 py-2">{job.queue}</td>
                        <td className="px-4 py-2 text-slate-400">{job.failed_at}</td>
                        <td className="px-4 py-2 text-right">
                          <button
                            onClick={() => retryQueueMutation.mutate(job.id)}
                            className="px-2 py-1 bg-blue-600 text-white rounded text-[10px] font-bold"
                          >
                            Retry
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="text-xs text-slate-500 p-4 text-center bg-slate-800/30 rounded-xl">
                No failed queue jobs detected. Workflows and messaging tasks executing smoothly.
              </div>
            )}
          </div>

          {/* Feature Flags */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6">
            <h3 className="text-base font-bold text-white mb-4">Platform Feature Flags</h3>
            <div className="space-y-3">
              {featureFlags.map((flag) => (
                <div
                  key={flag.id}
                  className="flex items-center justify-between p-4 bg-slate-800/40 rounded-xl border border-slate-700/60"
                >
                  <div>
                    <h4 className="text-sm font-semibold text-white">{flag.name}</h4>
                    <p className="text-xs text-slate-400">{flag.description}</p>
                    <span className="text-[10px] font-mono text-slate-500">{flag.flag_key}</span>
                  </div>
                  <button
                    onClick={() =>
                      updateFlagMutation.mutate({
                        key: flag.flag_key,
                        data: { is_enabled: !flag.is_enabled },
                      })
                    }
                    className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition ${
                      flag.is_enabled
                        ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                        : 'bg-slate-800 text-slate-400 border border-slate-700'
                    }`}
                  >
                    {flag.is_enabled ? <ToggleRight className="w-4 h-4" /> : <ToggleLeft className="w-4 h-4" />}
                    {flag.is_enabled ? 'Enabled' : 'Disabled'}
                  </button>
                </div>
              ))}
            </div>
          </div>

          {/* Platform Security Audit Trail */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6">
            <h3 className="text-base font-bold text-white mb-4">Platform Administrative Audit Trail</h3>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs text-slate-300">
                <thead className="bg-slate-800/60 uppercase text-[10px] font-semibold text-slate-400">
                  <tr>
                    <th className="px-4 py-3">Timestamp</th>
                    <th className="px-4 py-3">Admin</th>
                    <th className="px-4 py-3">Action</th>
                    <th className="px-4 py-3">Target</th>
                    <th className="px-4 py-3">Details</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800">
                  {auditLogsData?.items.map((log) => (
                    <tr key={log.id} className="hover:bg-slate-800/30">
                      <td className="px-4 py-3 text-slate-400">{new Date(log.created_at).toLocaleString()}</td>
                      <td className="px-4 py-3 font-semibold text-white">{log.admin_name}</td>
                      <td className="px-4 py-3">
                        <span className="px-2 py-0.5 rounded text-[10px] uppercase font-bold bg-slate-800 text-blue-400">
                          {log.action}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-slate-400 font-mono">
                        {log.target_type} #{log.target_id || '—'}
                      </td>
                      <td className="px-4 py-3 text-slate-400 font-mono text-[11px] truncate max-w-xs">
                        {JSON.stringify(log.details)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 6: Industry Templates Management */}
      {activeTab === 'templates' && (
        <div className="space-y-6">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
              <div>
                <h3 className="text-lg font-bold text-white flex items-center gap-2">
                  <Sparkles className="w-5 h-5 text-indigo-400" />
                  Industry-Specific SaaS Blueprints ({industryTemplates.length} Verticals)
                </h3>
                <p className="text-xs text-slate-400 mt-1">
                  Ready-to-deploy domain templates with pre-configured pipeline stages, domain fields, and automations for Indian businesses.
                </p>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {industryTemplates.map((template) => (
                <div
                  key={template.template_code}
                  className="bg-slate-800/60 border border-slate-700/60 rounded-xl p-5 hover:border-slate-600 transition space-y-3"
                >
                  <div className="flex items-start justify-between">
                    <div>
                      <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 uppercase tracking-wider">
                        {template.industry_sector}
                      </span>
                      <h4 className="text-base font-bold text-white mt-1.5">{template.name}</h4>
                    </div>
                    <span className="text-xs font-mono text-slate-400">v{template.version}</span>
                  </div>

                  <p className="text-xs text-slate-400 line-clamp-2">{template.description}</p>

                  <div className="pt-2 border-t border-slate-700/40 grid grid-cols-3 gap-2 text-center text-xs">
                    <div className="bg-slate-900/60 p-2 rounded-lg">
                      <div className="font-bold text-white">{template.pipeline_stages?.length || 0}</div>
                      <div className="text-[10px] text-slate-400">Stages</div>
                    </div>
                    <div className="bg-slate-900/60 p-2 rounded-lg">
                      <div className="font-bold text-white">{template.custom_fields?.length || 0}</div>
                      <div className="text-[10px] text-slate-400">Fields</div>
                    </div>
                    <div className="bg-slate-900/60 p-2 rounded-lg">
                      <div className="font-bold text-white">{template.dashboard_widgets?.length || 0}</div>
                      <div className="text-[10px] text-slate-400">Widgets</div>
                    </div>
                  </div>

                  <div className="text-[11px] text-slate-400 space-y-1">
                    <div className="font-medium text-slate-300">Pipeline Stages:</div>
                    <div className="flex flex-wrap gap-1">
                      {template.pipeline_stages?.slice(0, 4).map((s, i) => (
                        <span key={i} className="px-1.5 py-0.5 bg-slate-900/80 rounded text-[10px] text-slate-300">
                          {s.name}
                        </span>
                      ))}
                      {(template.pipeline_stages?.length || 0) > 4 && (
                        <span className="px-1.5 py-0.5 bg-slate-900/80 rounded text-[10px] text-indigo-400">
                          +{(template.pipeline_stages?.length || 0) - 4} more
                        </span>
                      )}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* TAB 7: Platform Announcements */}
      {activeTab === 'announcements' && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Create Announcement Form */}
            <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4">
              <h3 className="text-base font-bold text-white flex items-center gap-2">
                <Radio className="w-5 h-5 text-red-400" />
                Dispatch Platform Broadcast
              </h3>
              <p className="text-xs text-slate-400">
                Broadcast operational alerts, scheduled maintenance notices, or new release highlights to active tenants.
              </p>

              <div>
                <label className="block text-xs font-semibold text-slate-300 mb-1">Announcement Title</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Scheduled GST Portal Maintenance"
                  value={newAnnouncement.title}
                  onChange={(e) => setNewAnnouncement({ ...newAnnouncement, title: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 mb-1">Message Content</label>
                <textarea
                  required
                  rows={3}
                  placeholder="Detailed notification message shown to tenants..."
                  value={newAnnouncement.message}
                  onChange={(e) => setNewAnnouncement({ ...newAnnouncement, message: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-300 mb-1">Severity / Type</label>
                  <select
                    value={newAnnouncement.type}
                    onChange={(e) => setNewAnnouncement({ ...newAnnouncement, type: e.target.value as any })}
                    className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none"
                  >
                    <option value="info">Info</option>
                    <option value="warning">Warning</option>
                    <option value="critical">Critical</option>
                    <option value="release">Release Update</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-300 mb-1">Target Audience</label>
                  <select
                    value={newAnnouncement.target_audience}
                    onChange={(e) => setNewAnnouncement({ ...newAnnouncement, target_audience: e.target.value as any })}
                    className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none"
                  >
                    <option value="all">All Tenants</option>
                    <option value="admins_only">Tenant Admins Only</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 mb-1">Action URL (Optional)</label>
                <input
                  type="text"
                  placeholder="https://..."
                  value={newAnnouncement.action_url}
                  onChange={(e) => setNewAnnouncement({ ...newAnnouncement, action_url: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none"
                />
              </div>

              <button
                onClick={async () => {
                  if (!newAnnouncement.title || !newAnnouncement.message) return;
                  await createAnnouncementMutation.mutateAsync(newAnnouncement);
                  setNewAnnouncement({ title: '', message: '', type: 'info', target_audience: 'all', action_url: '' });
                  refetchAnnouncements();
                }}
                disabled={createAnnouncementMutation.isPending || !newAnnouncement.title || !newAnnouncement.message}
                className="w-full py-2.5 bg-red-600 hover:bg-red-500 disabled:opacity-50 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-2"
              >
                <Send className="w-4 h-4" />
                {createAnnouncementMutation.isPending ? 'Broadcasting...' : 'Broadcast to Platform'}
              </button>
            </div>

            {/* Recent Announcements List */}
            <div className="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl p-6">
              <h3 className="text-base font-bold text-white mb-4">Published Platform Announcements</h3>
              <div className="space-y-3">
                {announcementsData.length === 0 ? (
                  <div className="text-center py-12 text-slate-500 text-xs">
                    No active platform announcements.
                  </div>
                ) : (
                  announcementsData.map((ann) => (
                    <div
                      key={ann.id}
                      className="p-4 bg-slate-800/60 border border-slate-700/60 rounded-xl space-y-2"
                    >
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <span
                            className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                              ann.type === 'critical'
                                ? 'bg-red-500/20 text-red-300 border border-red-500/30'
                                : ann.type === 'warning'
                                ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30'
                                : ann.type === 'release'
                                ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30'
                                : 'bg-blue-500/20 text-blue-300 border border-blue-500/30'
                            }`}
                          >
                            {ann.type}
                          </span>
                          <h4 className="text-sm font-bold text-white">{ann.title}</h4>
                        </div>
                        <span className="text-[11px] text-slate-400">
                          {new Date(ann.created_at).toLocaleDateString()}
                        </span>
                      </div>
                      <p className="text-xs text-slate-300">{ann.message}</p>
                      <div className="flex items-center justify-between text-[11px] text-slate-400 pt-1">
                        <span>Target: {ann.target_audience.replace('_', ' ')}</span>
                        {ann.action_url && (
                          <a
                            href={ann.action_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-indigo-400 hover:underline flex items-center gap-1"
                          >
                            <span>Learn more</span>
                            <ExternalLink className="w-3 h-3" />
                          </a>
                        )}
                      </div>
                    </div>
                  ))
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* TAB 8: Encrypted Automated Backups & Disaster Recovery */}
      {activeTab === 'backups' && (
        <div className="space-y-6">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
              <div>
                <h3 className="text-lg font-bold text-white flex items-center gap-2">
                  <HardDrive className="w-5 h-5 text-emerald-400" />
                  Automated Encrypted Backups & Disaster Recovery
                </h3>
                <p className="text-xs text-slate-400 mt-1">
                  SHA-256 verified snapshots with AES-256 encryption at rest. Point-in-time recovery for cross-tenant resilience.
                </p>
              </div>

              <div className="flex items-center gap-3">
                <select
                  value={backupType}
                  onChange={(e) => setBackupType(e.target.value)}
                  className="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none"
                >
                  <option value="full">Full Platform Snapshot</option>
                  <option value="tenant_data_only">Tenant Data Only</option>
                  <option value="schema_only">Schema Only</option>
                </select>

                <button
                  onClick={async () => {
                    await triggerBackupMutation.mutateAsync({ backup_type: backupType, notes: backupNotes });
                    refetchBackups();
                  }}
                  disabled={triggerBackupMutation.isPending}
                  className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-xl text-xs font-bold transition flex items-center gap-2 shadow"
                >
                  <Database className="w-4 h-4" />
                  {triggerBackupMutation.isPending ? 'Generating Snapshot...' : 'Trigger Backup Now'}
                </button>
              </div>
            </div>

            {/* Backups Table */}
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead className="bg-slate-800/60 text-slate-400 border-b border-slate-700">
                  <tr>
                    <th className="px-4 py-3">Timestamp</th>
                    <th className="px-4 py-3">Snapshot File</th>
                    <th className="px-4 py-3">Type</th>
                    <th className="px-4 py-3">Size</th>
                    <th className="px-4 py-3">SHA-256 Checksum</th>
                    <th className="px-4 py-3">Encryption</th>
                    <th className="px-4 py-3">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800">
                  {backupsData.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="text-center py-8 text-slate-500">
                        No backup archives found. Trigger a backup to create the initial snapshot.
                      </td>
                    </tr>
                  ) : (
                    backupsData.map((backup) => (
                      <tr key={backup.id} className="hover:bg-slate-800/30">
                        <td className="px-4 py-3 text-slate-400">
                          {new Date(backup.created_at).toLocaleString()}
                        </td>
                        <td className="px-4 py-3 font-mono font-medium text-white">
                          {backup.file_name}
                        </td>
                        <td className="px-4 py-3 capitalize text-slate-300">
                          {backup.backup_type}
                        </td>
                        <td className="px-4 py-3 font-semibold text-white">
                          {backup.size_formatted}
                        </td>
                        <td className="px-4 py-3 font-mono text-[10px] text-slate-400 truncate max-w-xs" title={backup.checksum_sha256 || ''}>
                          {backup.checksum_sha256 ? `${backup.checksum_sha256.substring(0, 16)}...` : '—'}
                        </td>
                        <td className="px-4 py-3">
                          {backup.encryption_enabled ? (
                            <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                              AES-256
                            </span>
                          ) : (
                            <span className="text-slate-500">Disabled</span>
                          )}
                        </td>
                        <td className="px-4 py-3">
                          <span
                            className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                              backup.status === 'completed'
                                ? 'bg-emerald-500/20 text-emerald-400'
                                : backup.status === 'in_progress'
                                ? 'bg-blue-500/20 text-blue-400 animate-pulse'
                                : 'bg-red-500/20 text-red-400'
                            }`}
                          >
                            {backup.status}
                          </span>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 1: Tenant Status (Suspend / Restore) */}
      {statusModalTenant && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl">
            <h3 className="text-lg font-bold text-white">
              {newStatus === 'suspended' ? 'Suspend Business Tenant' : 'Restore Business Tenant'}
            </h3>
            <p className="text-xs text-slate-300">
              You are altering the operational status for{' '}
              <span className="font-bold text-white">{statusModalTenant.name}</span>.
            </p>

            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">
                Mandatory Audit Justification
              </label>
              <textarea
                required
                rows={3}
                placeholder="State precise justification reason for audit compliance..."
                value={statusReason}
                onChange={(e) => setStatusReason(e.target.value)}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:border-red-500"
              />
            </div>

            <div className="flex items-center justify-end gap-3 pt-2">
              <button
                onClick={() => setStatusModalTenant(null)}
                className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition"
              >
                Cancel
              </button>
              <button
                onClick={handleStatusSubmit}
                disabled={updateStatusMutation.isPending || !statusReason.trim()}
                className={`px-4 py-2 text-white rounded-xl text-xs font-semibold shadow transition ${
                  newStatus === 'suspended' ? 'bg-red-600 hover:bg-red-500' : 'bg-emerald-600 hover:bg-emerald-500'
                }`}
              >
                {updateStatusMutation.isPending ? 'Processing...' : 'Confirm Action'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 2: Change Tenant Plan */}
      {planModalTenant && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl">
            <h3 className="text-lg font-bold text-white">Change Subscription Plan</h3>
            <p className="text-xs text-slate-300">
              Modifying plan tier for <span className="font-bold text-white">{planModalTenant.name}</span>.
            </p>

            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">Select New Tier</label>
              <select
                value={targetPlanId}
                onChange={(e) => setTargetPlanId(parseInt(e.target.value))}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:border-blue-500"
              >
                {plans.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name} (${p.price_monthly.toFixed(2)}/mo)
                  </option>
                ))}
              </select>
            </div>

            <div className="flex items-center gap-2 pt-1">
              <input
                type="checkbox"
                id="proratePlan"
                checked={proratePlan}
                onChange={(e) => setProratePlan(e.target.checked)}
                className="w-4 h-4 accent-blue-600 rounded cursor-pointer"
              />
              <label htmlFor="proratePlan" className="text-xs text-slate-300 cursor-pointer">
                Apply prorated credit for remaining billing days
              </label>
            </div>

            <div className="flex items-center justify-end gap-3 pt-3">
              <button
                onClick={() => setPlanModalTenant(null)}
                className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition"
              >
                Cancel
              </button>
              <button
                onClick={handlePlanSubmit}
                disabled={changePlanMutation.isPending}
                className="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-semibold shadow transition"
              >
                {changePlanMutation.isPending ? 'Updating...' : 'Save Plan Change'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 3: Scoped Breakglass Support Session */}
      {breakglassModalTenant && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-xl">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-purple-600/20 text-purple-400 rounded-xl border border-purple-500/20">
                <Lock className="w-6 h-6" />
              </div>
              <div>
                <h3 className="text-lg font-bold text-white">Breakglass Customer Support Session</h3>
                <p className="text-xs text-slate-400">Casual browsing of customer data is strictly prohibited.</p>
              </div>
            </div>

            <div className="bg-amber-500/10 border border-amber-500/30 rounded-xl p-3 text-xs text-amber-300">
              All actions taken during this temporary 60-minute session will be recorded in the security audit log
              and attributed directly to your administrator account.
            </div>

            {breakglassResult ? (
              <div className="bg-slate-800 p-4 rounded-xl border border-slate-700 space-y-2 text-xs">
                <div className="text-emerald-400 font-bold flex items-center gap-1.5">
                  <CheckCircle2 className="w-4 h-4" />
                  Breakglass Session Token Generated:
                </div>
                <div className="font-mono bg-black/40 p-2 rounded text-slate-300 break-all select-all">
                  {breakglassResult.access_token}
                </div>
                <div className="text-slate-400">
                  Expires at: {new Date(breakglassResult.expires_at).toLocaleTimeString()}
                </div>
                <button
                  onClick={() => {
                    setBreakglassModalTenant(null);
                    setBreakglassResult(null);
                  }}
                  className="w-full mt-2 py-2 bg-slate-700 text-white rounded-lg font-semibold"
                >
                  Close
                </button>
              </div>
            ) : (
              <div>
                <label className="block text-xs font-semibold text-slate-300 mb-1">
                  Mandatory Ticket / Justification Reason
                </label>
                <textarea
                  required
                  rows={3}
                  placeholder="e.g. Ticket #INC-9482: Customer authorized review of broken webhook payload..."
                  value={justificationReason}
                  onChange={(e) => setJustificationReason(e.target.value)}
                  className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:border-purple-500"
                />

                <div className="flex items-center justify-end gap-3 pt-3">
                  <button
                    onClick={() => setBreakglassModalTenant(null)}
                    className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition"
                  >
                    Cancel
                  </button>
                  <button
                    onClick={handleBreakglassSubmit}
                    disabled={breakglassMutation.isPending || !justificationReason.trim()}
                    className="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white rounded-xl text-xs font-semibold shadow transition"
                  >
                    {breakglassMutation.isPending ? 'Verifying...' : 'Initialize Session'}
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {/* MODAL 4: Provider Configuration & Encrypted Credentials */}
      {editProviderModal && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl">
            <h3 className="text-lg font-bold text-white">Configure {editProviderModal.name}</h3>

            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">
                API Secret Key (Stored Encrypted At Rest)
              </label>
              <input
                type="password"
                placeholder={editProviderModal.is_configured ? '••••••••••••••••' : 'Enter API Secret'}
                value={providerCredentials}
                onChange={(e) => setProviderCredentials(e.target.value)}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:border-blue-500"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">Default Model</label>
              <select
                value={providerDefaultModel}
                onChange={(e) => setProviderDefaultModel(e.target.value)}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none"
              >
                {editProviderModal.available_models?.map((m) => (
                  <option key={m} value={m}>
                    {m}
                  </option>
                ))}
              </select>
            </div>

            <div className="flex items-center justify-end gap-3 pt-3">
              <button
                onClick={() => setEditProviderModal(null)}
                className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition"
              >
                Cancel
              </button>
              <button
                onClick={handleProviderSave}
                disabled={updateProviderMutation.isPending}
                className="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-semibold shadow transition"
              >
                {updateProviderMutation.isPending ? 'Saving...' : 'Save Configuration'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 5: Manual Credit Adjustment */}
      {isCreditAdjustModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl">
            <h3 className="text-lg font-bold text-white">Manual Credit Adjustment</h3>
            <p className="text-xs text-slate-400">
              Direct credit adjustment to a tenant's immutable ledger for SLA compensation or refunds.
            </p>

            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">Tenant ID</label>
              <input
                type="number"
                value={adjustTenantId}
                onChange={(e) => setAdjustTenantId(parseInt(e.target.value) || 1)}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">Adjustment Amount ($)</label>
              <input
                type="number"
                step="0.01"
                value={adjustAmount}
                onChange={(e) => setAdjustAmount(parseFloat(e.target.value) || 0)}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">Reason / SLA Reference</label>
              <input
                type="text"
                required
                placeholder="e.g. Compensation for downtime event"
                value={adjustReason}
                onChange={(e) => setAdjustReason(e.target.value)}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none"
              />
            </div>

            <div className="flex items-center justify-end gap-3 pt-3">
              <button
                onClick={() => setIsCreditAdjustModalOpen(false)}
                className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition"
              >
                Cancel
              </button>
              <button
                onClick={handleCreditAdjustSubmit}
                disabled={adjustCreditMutation.isPending || !adjustReason.trim()}
                className="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-semibold shadow transition"
              >
                {adjustCreditMutation.isPending ? 'Processing...' : 'Apply Adjustment'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
