import React, { useState } from 'react';
import {
  Share2,
  Globe,
  MessageSquare,
  Webhook,
  Plus,
  RefreshCw,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  Play,
  RotateCcw,
  Code,
  Copy,
  Check,
  Trash2,
  Settings2,
  Sliders,
  DollarSign,
  TrendingUp,
  Target,
  Eye,
  Layers,
  FileSpreadsheet,
  Link2,
  ArrowRight,
  ExternalLink,
  ShieldCheck,
  Activity,
  X
} from 'lucide-react';
import {
  useLeadSources,
  useLeadAcquisitionStats,
  useConnectLeadSource,
  useDisconnectLeadSource,
  useTestLeadSource,
  useSyncLeadSourceMetrics,
  useLeadForms,
  useCreateLeadForm,
  useDeleteLeadForm,
  useLeadFieldMappings,
  useSaveLeadFieldMapping,
  useDeleteLeadFieldMapping,
  useLeadIngestionEvents,
  useRetryLeadIngestionEvent,
  useAdCampaignMetrics,
} from '../../hooks/useLeadAcquisition';
import {
  ResponsiveContainer,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  PieChart,
  Pie,
  Cell,
} from 'recharts';

interface LeadAcquisitionHubViewProps {
  theme?: 'light' | 'dark';
}

const PROVIDER_METADATA: Record<string, { name: string; color: string; icon: any; docsUrl: string }> = {
  google_ads: {
    name: 'Google Ads',
    color: 'from-blue-600 to-amber-500',
    icon: Target,
    docsUrl: 'https://developers.google.com/google-ads/api/docs/first-call/overview',
  },
  meta_ads: {
    name: 'Facebook & Instagram Ads',
    color: 'from-blue-600 to-indigo-600',
    icon: Share2,
    docsUrl: 'https://developers.facebook.com/docs/marketing-api/guides/lead-ads',
  },
  youtube: {
    name: 'YouTube Campaigns',
    color: 'from-red-600 to-rose-700',
    icon: Play,
    docsUrl: 'https://support.google.com/google-ads/answer/9914444',
  },
  website_form: {
    name: 'Website & Landing Pages',
    color: 'from-emerald-600 to-teal-700',
    icon: Globe,
    docsUrl: '#',
  },
  whatsapp: {
    name: 'WhatsApp Business',
    color: 'from-emerald-500 to-green-600',
    icon: MessageSquare,
    docsUrl: 'https://developers.facebook.com/docs/whatsapp/cloud-api',
  },
  webhook: {
    name: 'Generic & Google Forms',
    color: 'from-purple-600 to-violet-700',
    icon: Webhook,
    docsUrl: '#',
  },
};

const CHART_COLORS = ['#10b981', '#3b82f6', '#f59e0b', '#ec4899', '#8b5cf6', '#06b6d4'];

export default function LeadAcquisitionHubView({ theme = 'dark' }: LeadAcquisitionHubViewProps) {
  const [activeTab, setActiveTab] = useState<'overview' | 'sources' | 'forms' | 'mappings' | 'events' | 'campaigns'>('overview');

  // Queries
  const { data: sourcesData, isLoading: sourcesLoading } = useLeadSources();
  const { data: statsData } = useLeadAcquisitionStats();
  const { data: formsData, isLoading: formsLoading } = useLeadForms();
  const [selectedMappingProvider, setSelectedMappingProvider] = useState<string>('google_ads');
  const { data: mappingsData } = useLeadFieldMappings(selectedMappingProvider);
  const [eventFilterStatus, setEventFilterStatus] = useState<string>('');
  const { data: eventsData, isLoading: eventsLoading } = useLeadIngestionEvents({ status: eventFilterStatus });
  const { data: campaignMetricsData } = useAdCampaignMetrics();

  // Mutations
  const connectMutation = useConnectLeadSource();
  const disconnectMutation = useDisconnectLeadSource();
  const testMutation = useTestLeadSource();
  const syncMutation = useSyncLeadSourceMetrics();
  const createFormMutation = useCreateLeadForm();
  const deleteFormMutation = useDeleteLeadForm();
  const saveMappingMutation = useSaveLeadFieldMapping();
  const deleteMappingMutation = useDeleteLeadFieldMapping();
  const retryEventMutation = useRetryLeadIngestionEvent();

  // Modal States
  const [connectModalOpen, setConnectModalOpen] = useState(false);
  const [selectedProviderToConnect, setSelectedProviderToConnect] = useState<string>('google_ads');
  const [connectFormName, setConnectFormName] = useState('');
  const [connectAccountId, setConnectAccountId] = useState('');
  const [connectCredentials, setConnectCredentials] = useState<Record<string, string>>({});

  const [formModalOpen, setFormModalOpen] = useState(false);
  const [newFormName, setNewFormName] = useState('');
  const [newFormCampaign, setNewFormCampaign] = useState('');

  const [embedModalOpen, setEmbedModalOpen] = useState(false);
  const [selectedFormForEmbed, setSelectedFormForEmbed] = useState<any>(null);
  const [copiedSnippet, setCopiedSnippet] = useState(false);

  const [mappingModalOpen, setMappingModalOpen] = useState(false);
  const [newSourceField, setNewSourceField] = useState('');
  const [newCrmField, setNewCrmField] = useState('name');
  const [newTransformRule, setNewTransformRule] = useState('trim');

  const [payloadModalOpen, setPayloadModalOpen] = useState(false);
  const [selectedEventPayload, setSelectedEventPayload] = useState<any>(null);

  const sources = sourcesData?.sources || [];
  const supportedProviders = sourcesData?.supported_providers || [];

  const handleOpenConnect = (providerKey: string) => {
    setSelectedProviderToConnect(providerKey);
    const meta = PROVIDER_METADATA[providerKey];
    setConnectFormName(meta?.name || 'New Lead Source');
    setConnectAccountId('');
    setConnectCredentials({});
    setConnectModalOpen(true);
  };

  const handleSubmitConnect = (e: React.FormEvent) => {
    e.preventDefault();
    connectMutation.mutate({
      provider: selectedProviderToConnect,
      name: connectFormName,
      account_id: connectAccountId,
      credentials: connectCredentials,
    }, {
      onSuccess: () => setConnectModalOpen(false),
    });
  };

  const handleCreateForm = (e: React.FormEvent) => {
    e.preventDefault();
    createFormMutation.mutate({
      name: newFormName,
      campaign_name: newFormCampaign,
      provider: 'website',
    }, {
      onSuccess: () => {
        setFormModalOpen(false);
        setNewFormName('');
        setNewFormCampaign('');
      },
    });
  };

  const handleSaveMapping = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newSourceField.trim()) return;
    saveMappingMutation.mutate({
      provider: selectedMappingProvider,
      source_field: newSourceField.trim(),
      crm_field: newCrmField,
      transform_rule: newTransformRule,
    }, {
      onSuccess: () => {
        setMappingModalOpen(false);
        setNewSourceField('');
      },
    });
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    setCopiedSnippet(true);
    setTimeout(() => setCopiedSnippet(false), 2000);
  };

  // Prepare Chart Data
  const sourceStats = statsData?.leads_by_source || {};
  const pieChartData = Object.entries(sourceStats).map(([src, count]) => ({
    name: PROVIDER_METADATA[src]?.name || src,
    value: Number(count),
  }));

  const campaignMetrics = campaignMetricsData?.metrics?.data || [];
  const barChartData = campaignMetrics.slice(0, 6).map((c: any) => ({
    name: c.campaign_name.length > 18 ? c.campaign_name.substring(0, 18) + '...' : c.campaign_name,
    leads: c.leads_count,
    spend: Number(c.spend),
  }));

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <span className="p-2 rounded-xl bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
              <Share2 className="h-5 w-5" />
            </span>
            <h1 className="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
              Omnichannel Lead Acquisition Hub
            </h1>
          </div>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Centralized ingestion engine for Google Ads, Meta Lead Ads, YouTube, Website Forms, WhatsApp, and Webhooks.
          </p>
        </div>

        {/* Quick Nav Tabs */}
        <div className={`p-1 rounded-2xl border flex items-center gap-1 overflow-x-auto ${
          theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-slate-100 border-slate-200'
        }`}>
          {[
            { id: 'overview', label: 'Overview & KPIs' },
            { id: 'sources', label: 'Connected Channels' },
            { id: 'forms', label: 'Forms & Pages' },
            { id: 'mappings', label: 'Field Mappings' },
            { id: 'events', label: 'Ingestion Queue' },
            { id: 'campaigns', label: 'Ad Spend & ROI' },
          ].map((tab) => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id as any)}
              className={`px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition ${
                activeTab === tab.id
                  ? 'bg-emerald-600 text-white shadow-xs'
                  : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
              }`}
            >
              {tab.label}
            </button>
          ))}
        </div>
      </div>

      {/* KPI BENTO CARDS */}
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        {[
          {
            label: 'Total Acquired Leads',
            value: statsData?.total_leads ?? 0,
            icon: Target,
            color: 'text-emerald-500 bg-emerald-500/10',
          },
          {
            label: 'Total Ad Spend',
            value: `$${(statsData?.total_spend ?? 0).toLocaleString()}`,
            icon: DollarSign,
            color: 'text-amber-500 bg-amber-500/10',
          },
          {
            label: 'Cost Per Lead (CPL)',
            value: `$${(statsData?.cost_per_lead ?? 0).toFixed(2)}`,
            icon: TrendingUp,
            color: 'text-teal-500 bg-teal-500/10',
          },
          {
            label: 'Total Ad Clicks',
            value: (statsData?.total_clicks ?? 0).toLocaleString(),
            icon: Activity,
            color: 'text-indigo-500 bg-indigo-500/10',
          },
          {
            label: 'Ingestion Events',
            value: (statsData?.total_events ?? 0).toLocaleString(),
            icon: Layers,
            color: 'text-blue-500 bg-blue-500/10',
          },
          {
            label: 'Failed In Queue',
            value: statsData?.failed_events ?? 0,
            icon: AlertTriangle,
            color: statsData?.failed_events ? 'text-rose-500 bg-rose-500/10' : 'text-slate-500 bg-slate-500/10',
          },
        ].map((card, i) => {
          const Icon = card.icon;
          return (
            <div
              key={i}
              className={`p-4 rounded-2xl border ${
                theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-100'
              }`}
            >
              <div className="flex items-center justify-between">
                <span className={`p-2 rounded-xl ${card.color}`}>
                  <Icon className="h-4 w-4" />
                </span>
              </div>
              <p className="text-[11px] font-semibold text-slate-500 dark:text-slate-400 mt-2">{card.label}</p>
              <p className="text-xl font-bold tracking-tight mt-0.5">{card.value}</p>
            </div>
          );
        })}
      </div>

      {/* TAB 1: OVERVIEW & KPIS */}
      {activeTab === 'overview' && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Leads Breakdown by Acquisition Source */}
            <div className={`p-5 rounded-2xl border ${theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'}`}>
              <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100 mb-1">
                Leads Distribution by Channel
              </h3>
              <p className="text-xs text-slate-500 mb-4">Volume of leads acquired per connected source platform.</p>
              <div className="h-64 flex items-center justify-center">
                {pieChartData.length > 0 ? (
                  <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                      <Pie
                        data={pieChartData}
                        dataKey="value"
                        nameKey="name"
                        cx="50%"
                        cy="50%"
                        outerRadius={80}
                        label={({ name, percent }) => `${name}: ${(percent * 100).toFixed(0)}%`}
                      >
                        {pieChartData.map((_, index) => (
                          <Cell key={`cell-${index}`} fill={CHART_COLORS[index % CHART_COLORS.length]} />
                        ))}
                      </Pie>
                      <Tooltip />
                    </PieChart>
                  </ResponsiveContainer>
                ) : (
                  <p className="text-xs text-slate-500">No leads captured yet. Connect a channel below to begin.</p>
                )}
              </div>
            </div>

            {/* Campaign Spend & Lead Conversion */}
            <div className={`p-5 rounded-2xl border ${theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'}`}>
              <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100 mb-1">
                Campaign Volume vs Spend ($)
              </h3>
              <p className="text-xs text-slate-500 mb-4">Synchronized advertising spend and lead acquisition.</p>
              <div className="h-64 flex items-center justify-center">
                {barChartData.length > 0 ? (
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={barChartData}>
                      <XAxis dataKey="name" tick={{ fontSize: 10 }} />
                      <YAxis yAxisId="left" tick={{ fontSize: 10 }} />
                      <YAxis yAxisId="right" orientation="right" tick={{ fontSize: 10 }} />
                      <Tooltip />
                      <Bar yAxisId="left" dataKey="leads" fill="#10b981" name="Leads" radius={[4, 4, 0, 0]} />
                      <Bar yAxisId="right" dataKey="spend" fill="#f59e0b" name="Spend ($)" radius={[4, 4, 0, 0]} />
                    </BarChart>
                  </ResponsiveContainer>
                ) : (
                  <p className="text-xs text-slate-500">Sync campaign metrics to inspect ad performance.</p>
                )}
              </div>
            </div>
          </div>

          {/* Quick Channels Grid */}
          <div>
            <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100 mb-3">
              Supported Marketing Channels & Ingestion Connectors
            </h3>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {supportedProviders.map((provider) => {
                const meta = PROVIDER_METADATA[provider.provider] || {
                  name: provider.name,
                  icon: Globe,
                  color: 'from-slate-600 to-slate-800',
                  docsUrl: '#',
                };
                const Icon = meta.icon;
                const connectedInstance = sources.find((s) => s.provider === provider.provider);

                return (
                  <div
                    key={provider.provider}
                    className={`p-5 rounded-2xl border flex flex-col justify-between transition hover:-translate-y-0.5 ${
                      theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
                    }`}
                  >
                    <div>
                      <div className="flex items-center justify-between mb-3">
                        <div className={`p-2.5 rounded-xl bg-gradient-to-br ${meta.color} text-white shadow-xs`}>
                          <Icon className="h-5 w-5" />
                        </div>
                        {connectedInstance ? (
                          <span className="flex items-center gap-1 text-[11px] font-bold text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-0.5 rounded-full">
                            <CheckCircle2 className="h-3 w-3" />
                            <span>Connected</span>
                          </span>
                        ) : (
                          <span className="flex items-center gap-1 text-[11px] font-bold text-slate-400 bg-slate-500/10 border border-slate-500/20 px-2.5 py-0.5 rounded-full">
                            <XCircle className="h-3 w-3" />
                            <span>Disconnected</span>
                          </span>
                        )}
                      </div>

                      <h4 className="text-sm font-bold text-slate-900 dark:text-slate-100">{provider.name}</h4>
                      <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                        {provider.description}
                      </p>

                      {connectedInstance && (
                        <div className="mt-3 pt-3 border-t border-slate-200 dark:border-slate-800 text-[11px] text-slate-400 space-y-1">
                          <p>Account: <strong className="text-slate-200">{connectedInstance.account_id || connectedInstance.name}</strong></p>
                          <p>Last Sync: <span className="font-mono">{connectedInstance.last_synced_at ? new Date(connectedInstance.last_synced_at).toLocaleString() : 'Never'}</span></p>
                        </div>
                      )}
                    </div>

                    <div className="mt-5 pt-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2">
                      {connectedInstance ? (
                        <>
                          <button
                            onClick={() => testMutation.mutate(connectedInstance.id)}
                            disabled={testMutation.isPending}
                            className="text-xs font-semibold px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition flex items-center gap-1"
                          >
                            <ShieldCheck className="h-3.5 w-3.5 text-emerald-500" />
                            <span>Test</span>
                          </button>
                          <button
                            onClick={() => syncMutation.mutate(connectedInstance.id)}
                            disabled={syncMutation.isPending}
                            className="text-xs font-semibold px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition flex items-center gap-1"
                          >
                            <RefreshCw className={`h-3.5 w-3.5 text-blue-400 ${syncMutation.isPending ? 'animate-spin' : ''}`} />
                            <span>Sync Spend</span>
                          </button>
                          <button
                            onClick={() => disconnectMutation.mutate(connectedInstance.id)}
                            className="text-xs font-semibold text-rose-500 hover:underline px-2 py-1"
                          >
                            Disconnect
                          </button>
                        </>
                      ) : (
                        <button
                          onClick={() => handleOpenConnect(provider.provider)}
                          className="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 rounded-xl text-xs transition flex items-center justify-center gap-1.5 shadow-xs"
                        >
                          <Plus className="h-4 w-4" />
                          <span>Connect & Authorize</span>
                        </button>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      )}

      {/* TAB 2: CONNECTED CHANNELS */}
      {activeTab === 'sources' && (
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100">
              Active Ingestion Source Accounts ({sources.length})
            </h3>
            <button
              onClick={() => handleOpenConnect('google_ads')}
              className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1 transition"
            >
              <Plus className="h-3.5 w-3.5" />
              <span>Connect Channel</span>
            </button>
          </div>

          <div className="overflow-x-auto rounded-2xl border border-inherit">
            <table className="w-full text-xs text-left">
              <thead className={`text-[11px] font-bold uppercase tracking-wider ${theme === 'dark' ? 'bg-slate-800/60 text-slate-400' : 'bg-slate-100 text-slate-600'}`}>
                <tr>
                  <th className="p-3">Source Name</th>
                  <th className="p-3">Platform</th>
                  <th className="p-3">Account / Page ID</th>
                  <th className="p-3">Connection Health</th>
                  <th className="p-3">Sync Status</th>
                  <th className="p-3">Last Synced</th>
                  <th className="p-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-inherit">
                {sources.map((s) => (
                  <tr key={s.id} className="hover:bg-slate-800/10">
                    <td className="p-3 font-semibold">{s.name}</td>
                    <td className="p-3 uppercase font-mono font-bold text-emerald-500">{s.provider}</td>
                    <td className="p-3 font-mono text-slate-400">{s.account_id || '-'}</td>
                    <td className="p-3">
                      <span className={`px-2 py-0.5 rounded-full font-bold text-[10px] uppercase border ${
                        s.status === 'connected' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border-rose-500/20'
                      }`}>
                        {s.status}
                      </span>
                    </td>
                    <td className="p-3">
                      <span className={`px-2 py-0.5 rounded-full font-bold text-[10px] uppercase border ${
                        s.sync_status === 'success' ? 'bg-blue-500/10 text-blue-400 border-blue-500/20' : s.sync_status === 'syncing' ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 'bg-slate-500/10 text-slate-400'
                      }`}>
                        {s.sync_status}
                      </span>
                    </td>
                    <td className="p-3 text-slate-400 font-mono">
                      {s.last_synced_at ? new Date(s.last_synced_at).toLocaleString() : 'Never'}
                    </td>
                    <td className="p-3 text-right">
                      <div className="flex items-center justify-end gap-1.5">
                        <button
                          onClick={() => testMutation.mutate(s.id)}
                          className="px-2 py-1 rounded-md border text-slate-300 hover:bg-slate-800 text-[10px] font-bold"
                          title="Verify Connection"
                        >
                          Test
                        </button>
                        <button
                          onClick={() => syncMutation.mutate(s.id)}
                          className="px-2 py-1 rounded-md border text-blue-400 hover:bg-blue-900/20 text-[10px] font-bold"
                          title="Sync Campaign Spend & Metrics"
                        >
                          Sync
                        </button>
                        <button
                          onClick={() => disconnectMutation.mutate(s.id)}
                          className="px-2 py-1 rounded-md text-rose-400 hover:bg-rose-900/20 text-[10px] font-bold"
                          title="Disconnect"
                        >
                          Disconnect
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 3: FORMS & HOSTED PAGES */}
      {activeTab === 'forms' && (
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100">
              Lead Forms & Embedded Widgets ({formsData.length})
            </h3>
            <button
              onClick={() => setFormModalOpen(true)}
              className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1 transition"
            >
              <Plus className="h-3.5 w-3.5" />
              <span>Create Lead Form</span>
            </button>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {formsData.map((f: any) => (
              <div
                key={f.id}
                className={`p-5 rounded-2xl border flex flex-col justify-between ${
                  theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
                }`}
              >
                <div>
                  <div className="flex items-center justify-between mb-2">
                    <span className="text-[10px] font-mono font-bold uppercase px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400">
                      {f.provider}
                    </span>
                    <span className="text-xs text-slate-500 font-mono font-bold">
                      {f.submissions_count} Submissions
                    </span>
                  </div>
                  <h4 className="font-bold text-sm text-slate-900 dark:text-slate-100">{f.name}</h4>
                  <p className="text-xs text-slate-500 mt-1">
                    Campaign: <strong className="text-slate-300">{f.campaign_name || 'General Inbound'}</strong>
                  </p>
                  <div className="mt-3 text-[11px] text-slate-400 space-y-1">
                    <p>UUID: <span className="font-mono">{f.public_uuid.substring(0, 18)}...</span></p>
                    <p>Fields: {(f.fields_config || []).map((x: any) => x.name).join(', ')}</p>
                  </div>
                </div>

                <div className="mt-4 pt-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                  <button
                    onClick={() => {
                      setSelectedFormForEmbed(f);
                      setEmbedModalOpen(true);
                    }}
                    className="text-xs font-bold text-emerald-400 hover:underline flex items-center gap-1"
                  >
                    <Code className="h-3.5 w-3.5" />
                    <span>Get Embed Code</span>
                  </button>
                  <button
                    onClick={() => deleteFormMutation.mutate(f.id)}
                    className="text-xs text-rose-400 hover:underline"
                  >
                    Delete
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* TAB 4: TENANT FIELD MAPPINGS */}
      {activeTab === 'mappings' && (
        <div className="space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100">
                Inbound Payload Field Mapping
              </h3>
              <p className="text-xs text-slate-500 mt-0.5">
                Map raw parameters from external providers to standardized CRM Lead properties.
              </p>
            </div>

            <div className="flex items-center gap-2">
              <select
                value={selectedMappingProvider}
                onChange={(e) => setSelectedMappingProvider(e.target.value)}
                className={`text-xs px-3 py-1.5 rounded-xl border font-semibold ${
                  theme === 'dark' ? 'bg-slate-900 border-slate-700 text-slate-200' : 'bg-white border-slate-300'
                }`}
              >
                <option value="google_ads">Google Ads</option>
                <option value="meta_ads">Meta Ads (Facebook/Instagram)</option>
                <option value="website_form">Website & Landing Page</option>
                <option value="whatsapp">WhatsApp Business</option>
                <option value="generic">Generic & Google Forms</option>
              </select>

              <button
                onClick={() => setMappingModalOpen(true)}
                className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1 transition"
              >
                <Plus className="h-3.5 w-3.5" />
                <span>Add Mapping</span>
              </button>
            </div>
          </div>

          <div className="overflow-x-auto rounded-2xl border border-inherit">
            <table className="w-full text-xs text-left">
              <thead className={`text-[11px] font-bold uppercase tracking-wider ${theme === 'dark' ? 'bg-slate-800/60 text-slate-400' : 'bg-slate-100 text-slate-600'}`}>
                <tr>
                  <th className="p-3">Source Payload Field</th>
                  <th className="p-3">Target CRM Field</th>
                  <th className="p-3">Transformation Rule</th>
                  <th className="p-3">Default Fallback</th>
                  <th className="p-3 text-right">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-inherit">
                {(mappingsData?.mappings || []).map((m: any) => (
                  <tr key={m.id} className="hover:bg-slate-800/10">
                    <td className="p-3 font-mono font-bold text-amber-400">{m.source_field}</td>
                    <td className="p-3 font-mono font-bold text-emerald-400">{m.crm_field}</td>
                    <td className="p-3 font-mono text-slate-400">{m.transform_rule || 'none'}</td>
                    <td className="p-3 text-slate-400">{m.default_value || '-'}</td>
                    <td className="p-3 text-right">
                      <button
                        onClick={() => deleteMappingMutation.mutate(m.id)}
                        className="text-rose-400 hover:underline font-bold"
                      >
                        Delete
                      </button>
                    </td>
                  </tr>
                ))}
                {(mappingsData?.mappings || []).length === 0 && (
                  <tr>
                    <td colSpan={5} className="p-6 text-center text-slate-500">
                      No custom mappings defined for {selectedMappingProvider}. Standard normalization rules apply automatically.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 5: INGESTION QUEUE & RETRY */}
      {activeTab === 'events' && (
        <div className="space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100">
                Lead Ingestion Events Stream & Error Queue
              </h3>
              <p className="text-xs text-slate-500 mt-0.5">
                Every webhook and form submission with raw payload capture and safe retry controls.
              </p>
            </div>

            <div className="flex items-center gap-2">
              <select
                value={eventFilterStatus}
                onChange={(e) => setEventFilterStatus(e.target.value)}
                className={`text-xs px-3 py-1.5 rounded-xl border font-semibold ${
                  theme === 'dark' ? 'bg-slate-900 border-slate-700 text-slate-200' : 'bg-white border-slate-300'
                }`}
              >
                <option value="">All Statuses</option>
                <option value="processed">Processed</option>
                <option value="failed">Failed in Queue</option>
                <option value="duplicate">Duplicate (Skipped)</option>
                <option value="pending">Pending</option>
              </select>
            </div>
          </div>

          <div className="overflow-x-auto rounded-2xl border border-inherit">
            <table className="w-full text-xs text-left">
              <thead className={`text-[11px] font-bold uppercase tracking-wider ${theme === 'dark' ? 'bg-slate-800/60 text-slate-400' : 'bg-slate-100 text-slate-600'}`}>
                <tr>
                  <th className="p-3">Timestamp</th>
                  <th className="p-3">Provider</th>
                  <th className="p-3">Event ID</th>
                  <th className="p-3">Status</th>
                  <th className="p-3">Ingested Lead</th>
                  <th className="p-3">Error / Diagnostics</th>
                  <th className="p-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-inherit">
                {(eventsData?.data || []).map((ev: any) => (
                  <tr key={ev.id} className="hover:bg-slate-800/10">
                    <td className="p-3 text-slate-400 whitespace-nowrap font-mono text-[11px]">
                      {new Date(ev.created_at).toLocaleString()}
                    </td>
                    <td className="p-3 uppercase font-mono font-bold text-emerald-400">{ev.provider}</td>
                    <td className="p-3 font-mono text-slate-400 text-[11px]">{ev.event_id}</td>
                    <td className="p-3">
                      <span className={`px-2 py-0.5 rounded-full font-bold text-[10px] uppercase border ${
                        ev.status === 'processed'
                          ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                          : ev.status === 'failed'
                          ? 'bg-rose-500/10 text-rose-400 border-rose-500/20'
                          : 'bg-amber-500/10 text-amber-400 border-amber-500/20'
                      }`}>
                        {ev.status}
                      </span>
                    </td>
                    <td className="p-3 font-semibold">
                      {ev.lead ? (
                        <span className="text-slate-200">{ev.lead.name} (#{ev.lead.id})</span>
                      ) : (
                        <span className="text-slate-500">-</span>
                      )}
                    </td>
                    <td className="p-3 max-w-xs truncate text-rose-400 font-mono text-[11px]">
                      {ev.error_message || '-'}
                    </td>
                    <td className="p-3 text-right">
                      <div className="flex items-center justify-end gap-1.5">
                        <button
                          onClick={() => {
                            setSelectedEventPayload(ev);
                            setPayloadModalOpen(true);
                          }}
                          className="px-2 py-1 rounded-md border text-slate-300 hover:bg-slate-800 text-[10px] font-bold"
                          title="Inspect Payload"
                        >
                          Payload
                        </button>
                        {ev.status === 'failed' && (
                          <button
                            onClick={() => retryEventMutation.mutate(ev.id)}
                            disabled={retryEventMutation.isPending}
                            className="px-2 py-1 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-bold flex items-center gap-1"
                            title="Retry Ingestion"
                          >
                            <RotateCcw className="h-3 w-3" />
                            <span>Retry</span>
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 6: CAMPAIGN SPEND & PERFORMANCE */}
      {activeTab === 'campaigns' && (
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100">
                Synchronized Campaign Metrics & Ad Spend
              </h3>
              <p className="text-xs text-slate-500 mt-0.5">
                Official Google Ads, Meta Ads and YouTube campaign performance records.
              </p>
            </div>
          </div>

          <div className="overflow-x-auto rounded-2xl border border-inherit">
            <table className="w-full text-xs text-left">
              <thead className={`text-[11px] font-bold uppercase tracking-wider ${theme === 'dark' ? 'bg-slate-800/60 text-slate-400' : 'bg-slate-100 text-slate-600'}`}>
                <tr>
                  <th className="p-3">Campaign Name</th>
                  <th className="p-3">Platform</th>
                  <th className="p-3">Date</th>
                  <th className="p-3">Impressions</th>
                  <th className="p-3">Clicks</th>
                  <th className="p-3">Leads Captured</th>
                  <th className="p-3">Spend ($)</th>
                  <th className="p-3">Cost Per Lead (CPL)</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-inherit">
                {campaignMetrics.map((m: any) => (
                  <tr key={m.id} className="hover:bg-slate-800/10">
                    <td className="p-3 font-semibold text-slate-200">{m.campaign_name}</td>
                    <td className="p-3 font-mono font-bold uppercase text-emerald-400">{m.provider}</td>
                    <td className="p-3 text-slate-400 font-mono">{m.date}</td>
                    <td className="p-3 font-mono">{m.impressions?.toLocaleString()}</td>
                    <td className="p-3 font-mono">{m.clicks?.toLocaleString()}</td>
                    <td className="p-3 font-mono font-bold text-emerald-400">{m.leads_count}</td>
                    <td className="p-3 font-mono font-bold">${Number(m.spend).toFixed(2)}</td>
                    <td className="p-3 font-mono font-bold text-amber-400">${Number(m.cost_per_lead).toFixed(2)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* MODAL: CONNECT SOURCE */}
      {connectModalOpen && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div className={`w-full max-w-lg p-6 rounded-2xl border shadow-2xl relative ${
            theme === 'dark' ? 'bg-slate-950 border-slate-800 text-slate-100' : 'bg-white border-slate-200 text-slate-800'
          }`}>
            <button onClick={() => setConnectModalOpen(false)} className="absolute top-4 right-4 text-slate-400 hover:text-slate-100">✕</button>
            <h3 className="font-bold text-md mb-1">
              Connect {PROVIDER_METADATA[selectedProviderToConnect]?.name || selectedProviderToConnect}
            </h3>
            <p className="text-xs text-slate-500 mb-4">
              Enter your provider API keys, developer tokens, or webhook secrets.
            </p>

            <form onSubmit={handleSubmitConnect} className="space-y-4">
              <div>
                <label className="text-xs font-semibold block mb-1">Source Account Label</label>
                <input
                  type="text"
                  value={connectFormName}
                  onChange={(e) => setConnectFormName(e.target.value)}
                  required
                  className={`w-full text-xs p-2.5 rounded-xl border ${
                    theme === 'dark' ? 'bg-slate-900 border-slate-700' : 'bg-slate-50 border-slate-300'
                  }`}
                />
              </div>

              <div>
                <label className="text-xs font-semibold block mb-1">Account ID / Customer ID / Page ID</label>
                <input
                  type="text"
                  placeholder="e.g. 123-456-7890 or 987654321"
                  value={connectAccountId}
                  onChange={(e) => setConnectAccountId(e.target.value)}
                  className={`w-full text-xs p-2.5 rounded-xl border ${
                    theme === 'dark' ? 'bg-slate-900 border-slate-700' : 'bg-slate-50 border-slate-300'
                  }`}
                />
              </div>

              {selectedProviderToConnect === 'google_ads' && (
                <>
                  <div>
                    <label className="text-xs font-semibold block mb-1">Google Ads Developer Token</label>
                    <input
                      type="password"
                      placeholder="Developer Token from Google Ads Manager"
                      onChange={(e) => setConnectCredentials({ ...connectCredentials, developer_token: e.target.value })}
                      className={`w-full text-xs p-2.5 rounded-xl border ${
                        theme === 'dark' ? 'bg-slate-900 border-slate-700' : 'bg-slate-50 border-slate-300'
                      }`}
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold block mb-1">Google Lead Form Key (Webhook Secret)</label>
                    <input
                      type="text"
                      placeholder="Key configured in Google Ads Lead Form Asset"
                      onChange={(e) => setConnectCredentials({ ...connectCredentials, google_key: e.target.value })}
                      className={`w-full text-xs p-2.5 rounded-xl border ${
                        theme === 'dark' ? 'bg-slate-900 border-slate-700' : 'bg-slate-50 border-slate-300'
                      }`}
                    />
                  </div>
                </>
              )}

              {selectedProviderToConnect === 'meta_ads' && (
                <>
                  <div>
                    <label className="text-xs font-semibold block mb-1">Meta App Secret (HMAC Verification)</label>
                    <input
                      type="password"
                      placeholder="App Secret from Meta Developers Portal"
                      onChange={(e) => setConnectCredentials({ ...connectCredentials, app_secret: e.target.value })}
                      className={`w-full text-xs p-2.5 rounded-xl border ${
                        theme === 'dark' ? 'bg-slate-900 border-slate-700' : 'bg-slate-50 border-slate-300'
                      }`}
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold block mb-1">Page Access Token</label>
                    <input
                      type="password"
                      placeholder="Permanent Page Access Token for Lead Retrieval"
                      onChange={(e) => setConnectCredentials({ ...connectCredentials, page_access_token: e.target.value })}
                      className={`w-full text-xs p-2.5 rounded-xl border ${
                        theme === 'dark' ? 'bg-slate-900 border-slate-700' : 'bg-slate-50 border-slate-300'
                      }`}
                    />
                  </div>
                  <div className="p-3 rounded-xl bg-blue-500/10 border border-blue-500/20 text-[11px] text-blue-400 leading-relaxed">
                    <strong>Meta App Review Note:</strong> Requires <code>leads_retrieval</code> and <code>pages_manage_ads</code> permissions granted on your Meta App.
                  </div>
                </>
              )}

              {selectedProviderToConnect === 'whatsapp' && (
                <div>
                  <label className="text-xs font-semibold block mb-1">WhatsApp Webhook Secret / Verify Token</label>
                  <input
                    type="text"
                    placeholder="Secret verification token"
                    onChange={(e) => setConnectCredentials({ ...connectCredentials, webhook_secret: e.target.value })}
                    className={`w-full text-xs p-2.5 rounded-xl border ${
                      theme === 'dark' ? 'bg-slate-900 border-slate-700' : 'bg-slate-50 border-slate-300'
                    }`}
                  />
                </div>
              )}

              <div className="border-t border-slate-200 dark:border-slate-800 pt-4 flex justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setConnectModalOpen(false)}
                  className="px-4 py-2 rounded-xl text-xs font-semibold border"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={connectMutation.isPending}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold"
                >
                  {connectMutation.isPending ? 'Connecting...' : 'Authorize & Connect'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL: EMBED CODE */}
      {embedModalOpen && selectedFormForEmbed && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div className={`w-full max-w-lg p-6 rounded-2xl border shadow-2xl relative ${
            theme === 'dark' ? 'bg-slate-950 border-slate-800 text-slate-100' : 'bg-white border-slate-200 text-slate-800'
          }`}>
            <button onClick={() => setEmbedModalOpen(false)} className="absolute top-4 right-4 text-slate-400 hover:text-slate-100">✕</button>
            <h3 className="font-bold text-md mb-1">Embed {selectedFormForEmbed.name}</h3>
            <p className="text-xs text-slate-500 mb-4">
              Paste this JavaScript snippet into any website or landing page to automatically capture leads with UTM attribution.
            </p>

            <div className="space-y-4">
              <div>
                <label className="text-xs font-semibold block mb-1">HTML Embed Snippet</label>
                <pre className={`p-3 rounded-xl border font-mono text-[11px] overflow-x-auto ${
                  theme === 'dark' ? 'bg-slate-900 border-slate-800 text-emerald-400' : 'bg-slate-100 border-slate-300 text-emerald-700'
                }`}>
                  {selectedFormForEmbed.embed_snippet || `<script src="/api/v1/public/forms/${selectedFormForEmbed.public_uuid}/embed.js" async></script>\n<div id="crm-lead-form-${selectedFormForEmbed.public_uuid}"></div>`}
                </pre>
              </div>

              <div>
                <label className="text-xs font-semibold block mb-1">Direct REST API Submission Endpoint</label>
                <input
                  type="text"
                  readOnly
                  value={selectedFormForEmbed.submit_endpoint || `${window.location.origin}/api/v1/public/forms/${selectedFormForEmbed.public_uuid}/submit`}
                  className={`w-full text-xs font-mono p-2.5 rounded-xl border ${
                    theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-slate-50 border-slate-300'
                  }`}
                />
              </div>

              <div className="border-t border-slate-200 dark:border-slate-800 pt-4 flex justify-end gap-2">
                <button
                  onClick={() => copyToClipboard(selectedFormForEmbed.embed_snippet || `<script src="/api/v1/public/forms/${selectedFormForEmbed.public_uuid}/embed.js" async></script>\n<div id="crm-lead-form-${selectedFormForEmbed.public_uuid}"></div>`)}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5"
                >
                  {copiedSnippet ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
                  <span>{copiedSnippet ? 'Copied' : 'Copy Embed Code'}</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* MODAL: CREATE LEAD FORM */}
      {formModalOpen && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div className={`w-full max-w-md p-6 rounded-2xl border shadow-2xl relative ${
            theme === 'dark' ? 'bg-slate-950 border-slate-800 text-slate-100' : 'bg-white border-slate-200 text-slate-800'
          }`}>
            <button onClick={() => setFormModalOpen(false)} className="absolute top-4 right-4 text-slate-400 hover:text-slate-100">✕</button>
            <h3 className="font-bold text-md mb-1">Create Lead Capture Form</h3>
            <p className="text-xs text-slate-500 mb-4">Generate an embedded or hosted form widget with UTM tracking.</p>

            <form onSubmit={handleCreateForm} className="space-y-4">
              <div>
                <label className="text-xs font-semibold block mb-1">Form Name</label>
                <input
                  type="text"
                  placeholder="e.g. Website Pricing Quote Form"
                  value={newFormName}
                  onChange={(e) => setNewFormName(e.target.value)}
                  required
                  className={`w-full text-xs p-2.5 rounded-xl border ${
                    theme === 'dark' ? 'bg-slate-900 border-slate-700' : 'bg-slate-50 border-slate-300'
                  }`}
                />
              </div>

              <div>
                <label className="text-xs font-semibold block mb-1">Campaign Association</label>
                <input
                  type="text"
                  placeholder="e.g. Enterprise Q1 Inbound"
                  value={newFormCampaign}
                  onChange={(e) => setNewFormCampaign(e.target.value)}
                  className={`w-full text-xs p-2.5 rounded-xl border ${
                    theme === 'dark' ? 'bg-slate-900 border-slate-700' : 'bg-slate-50 border-slate-300'
                  }`}
                />
              </div>

              <div className="border-t border-slate-200 dark:border-slate-800 pt-4 flex justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setFormModalOpen(false)}
                  className="px-4 py-2 rounded-xl text-xs font-semibold border"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createFormMutation.isPending}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold"
                >
                  {createFormMutation.isPending ? 'Creating...' : 'Create Form'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL: ADD MAPPING */}
      {mappingModalOpen && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div className={`w-full max-w-md p-6 rounded-2xl border shadow-2xl relative ${
            theme === 'dark' ? 'bg-slate-950 border-slate-800 text-slate-100' : 'bg-white border-slate-200 text-slate-800'
          }`}>
            <button onClick={() => setMappingModalOpen(false)} className="absolute top-4 right-4 text-slate-400 hover:text-slate-100">✕</button>
            <h3 className="font-bold text-md mb-1">Add Field Mapping ({selectedMappingProvider})</h3>
            <p className="text-xs text-slate-500 mb-4">Map an inbound webhook property to a target CRM field.</p>

            <form onSubmit={handleSaveMapping} className="space-y-4">
              <div>
                <label className="text-xs font-semibold block mb-1">Source Inbound Field Key</label>
                <input
                  type="text"
                  placeholder="e.g. user_phone, org_name, custom_q1"
                  value={newSourceField}
                  onChange={(e) => setNewSourceField(e.target.value)}
                  required
                  className={`w-full text-xs p-2.5 rounded-xl border ${
                    theme === 'dark' ? 'bg-slate-900 border-slate-700' : 'bg-slate-50 border-slate-300'
                  }`}
                />
              </div>

              <div>
                <label className="text-xs font-semibold block mb-1">Target CRM Field</label>
                <select
                  value={newCrmField}
                  onChange={(e) => setNewCrmField(e.target.value)}
                  className={`w-full text-xs p-2.5 rounded-xl border ${
                    theme === 'dark' ? 'bg-slate-900 border-slate-700 text-slate-200' : 'bg-slate-50 border-slate-300'
                  }`}
                >
                  <option value="name">Full Name</option>
                  <option value="first_name">First Name</option>
                  <option value="last_name">Last Name</option>
                  <option value="email">Email Address</option>
                  <option value="phone">Primary Phone</option>
                  <option value="alternate_phone">Alternate Phone</option>
                  <option value="company_name">Company Name</option>
                  <option value="designation">Job Title / Designation</option>
                  <option value="requirement">Requirement / Project Notes</option>
                  <option value="estimated_value">Estimated Budget</option>
                </select>
              </div>

              <div>
                <label className="text-xs font-semibold block mb-1">Transformation Rule</label>
                <select
                  value={newTransformRule}
                  onChange={(e) => setNewTransformRule(e.target.value)}
                  className={`w-full text-xs p-2.5 rounded-xl border ${
                    theme === 'dark' ? 'bg-slate-900 border-slate-700 text-slate-200' : 'bg-slate-50 border-slate-300'
                  }`}
                >
                  <option value="trim">Trim Whitespace</option>
                  <option value="phone_normalize">Normalize Phone (Digits Only)</option>
                  <option value="lowercase">Convert to Lowercase</option>
                  <option value="uppercase">Convert to Uppercase</option>
                  <option value="default">Pass-through as is</option>
                </select>
              </div>

              <div className="border-t border-slate-200 dark:border-slate-800 pt-4 flex justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setMappingModalOpen(false)}
                  className="px-4 py-2 rounded-xl text-xs font-semibold border"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={saveMappingMutation.isPending}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold"
                >
                  Save Rule
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL: PAYLOAD INSPECTOR */}
      {payloadModalOpen && selectedEventPayload && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div className={`w-full max-w-2xl p-6 rounded-2xl border shadow-2xl relative ${
            theme === 'dark' ? 'bg-slate-950 border-slate-800 text-slate-100' : 'bg-white border-slate-200 text-slate-800'
          }`}>
            <button onClick={() => setPayloadModalOpen(false)} className="absolute top-4 right-4 text-slate-400 hover:text-slate-100">✕</button>
            <h3 className="font-bold text-md mb-1">
              Event Payload #{selectedEventPayload.id} ({selectedEventPayload.provider})
            </h3>
            <p className="text-xs text-slate-500 mb-4">
              Idempotency Event ID: <code className="text-emerald-400">{selectedEventPayload.event_id}</code>
            </p>

            <div className="space-y-4 max-h-[60vh] overflow-y-auto">
              <div>
                <label className="text-xs font-bold block mb-1 text-slate-400">Normalized CRM Data</label>
                <pre className={`p-3 rounded-xl border font-mono text-[11px] overflow-x-auto ${
                  theme === 'dark' ? 'bg-slate-900 border-slate-800 text-emerald-400' : 'bg-slate-100 border-slate-300 text-emerald-700'
                }`}>
                  {JSON.stringify(selectedEventPayload.normalized_payload || {}, null, 2)}
                </pre>
              </div>

              <div>
                <label className="text-xs font-bold block mb-1 text-slate-400">Raw Provider Payload</label>
                <pre className={`p-3 rounded-xl border font-mono text-[11px] overflow-x-auto ${
                  theme === 'dark' ? 'bg-slate-900 border-slate-800 text-slate-300' : 'bg-slate-100 border-slate-300 text-slate-700'
                }`}>
                  {selectedEventPayload.raw_payload || 'No raw payload stored'}
                </pre>
              </div>
            </div>

            <div className="border-t border-slate-200 dark:border-slate-800 pt-4 mt-4 flex justify-end">
              <button
                onClick={() => setPayloadModalOpen(false)}
                className="bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded-xl text-xs font-bold"
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
