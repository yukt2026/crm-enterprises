import React, { useState } from 'react';
import {
  Plug,
  Key,
  Webhook,
  BookOpen,
  RefreshCw,
  Plus,
  CheckCircle2,
  AlertTriangle,
  Clock,
  Shield,
  Copy,
  ExternalLink,
  Trash2,
  RotateCcw,
  Send,
  Search,
  Filter,
  Check,
  Globe,
  Radio,
  FileCode,
  Activity,
  Layers,
  Zap,
} from 'lucide-react';
import {
  useIntegrationsDirectory,
  useConnectCredentials,
  useDisconnectIntegration,
  useTestIntegration,
  useSyncIntegration,
  useIntegrationSyncLogs,
  useApiKeys,
  useCreateApiKey,
  useRevokeApiKey,
  useRotateApiKey,
  useApiKeyLogs,
  useWebhooks,
  useCreateWebhook,
  useUpdateWebhook,
  useDeleteWebhook,
  useTestPingWebhook,
  useWebhookLogs,
  useRedeliverWebhook,
  useOpenApiSpec,
} from '../../hooks/useIntegrations';
import {
  IntegrationDirectoryItem,
  TenantApiKeyItem,
  OutboundWebhookItem,
} from '../../api/types';

export const IntegrationManagementHubView: React.FC = () => {
  const [activeTab, setActiveTab] = useState<'directory' | 'connected' | 'apikeys' | 'webhooks' | 'docs'>('directory');
  const [categoryFilter, setCategoryFilter] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState('');

  // Queries
  const { data: directory = [], isLoading: dirLoading, refetch: refetchDir } = useIntegrationsDirectory();
  const { data: apiKeys = [], isLoading: keysLoading } = useApiKeys();
  const { data: webhooks = [], isLoading: webhooksLoading } = useWebhooks();
  const { data: openApiSpec } = useOpenApiSpec();

  // Mutations
  const connectMutation = useConnectCredentials();
  const disconnectMutation = useDisconnectIntegration();
  const testMutation = useTestIntegration();
  const syncMutation = useSyncIntegration();
  const createApiKeyMutation = useCreateApiKey();
  const revokeApiKeyMutation = useRevokeApiKey();
  const rotateApiKeyMutation = useRotateApiKey();
  const createWebhookMutation = useCreateWebhook();
  const deleteWebhookMutation = useDeleteWebhook();
  const testPingMutation = useTestPingWebhook();
  const redeliverMutation = useRedeliverWebhook();

  // Modals & Drawers
  const [selectedIntegration, setSelectedIntegration] = useState<IntegrationDirectoryItem | null>(null);
  const [showConnectModal, setShowConnectModal] = useState(false);
  const [credInputs, setCredInputs] = useState<Record<string, string>>({});

  const [showApiKeyModal, setShowApiKeyModal] = useState(false);
  const [newKeyName, setNewKeyName] = useState('');
  const [selectedScopes, setSelectedScopes] = useState<string[]>(['leads:read', 'leads:write', 'deals:read']);
  const [generatedSecret, setGeneratedSecret] = useState<string | null>(null);
  const [copiedKey, setCopiedKey] = useState(false);

  const [showWebhookModal, setShowWebhookModal] = useState(false);
  const [webhookName, setWebhookName] = useState('');
  const [webhookUrl, setWebhookUrl] = useState('');
  const [webhookEvents, setWebhookEvents] = useState<string[]>(['lead.created', 'deal.won']);

  const [viewingSyncLogsIntegrationId, setViewingSyncLogsIntegrationId] = useState<number | null>(null);
  const { data: syncLogs = [] } = useIntegrationSyncLogs(viewingSyncLogsIntegrationId);

  const [viewingWebhookLogsId, setViewingWebhookLogsId] = useState<number | null>(null);
  const { data: webhookLogs = [] } = useWebhookLogs(viewingWebhookLogsId || undefined);

  // Filtered directory
  const filteredDirectory = directory.filter((item) => {
    const matchesCat = categoryFilter === 'all' || item.category === categoryFilter;
    const matchesSearch =
      item.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      item.description.toLowerCase().includes(searchQuery.toLowerCase());
    return matchesCat && matchesSearch;
  });

  const connectedList = directory.filter((item) => item.is_connected && item.connection);

  const handleOpenConnect = (item: IntegrationDirectoryItem) => {
    setSelectedIntegration(item);
    setCredInputs({});
    setShowConnectModal(true);
  };

  const handleSaveCredentials = async () => {
    if (!selectedIntegration) return;
    await connectMutation.mutateAsync({
      provider: selectedIntegration.provider,
      name: selectedIntegration.name,
      credentials: credInputs,
    });
    setShowConnectModal(false);
  };

  const handleCreateApiKey = async () => {
    if (!newKeyName.trim()) return;
    const res = await createApiKeyMutation.mutateAsync({
      name: newKeyName,
      scopes: selectedScopes,
    });
    if (res?.raw_secret) {
      setGeneratedSecret(res.raw_secret);
    }
  };

  const handleCreateWebhook = async () => {
    if (!webhookName.trim() || !webhookUrl.trim()) return;
    await createWebhookMutation.mutateAsync({
      name: webhookName,
      url: webhookUrl,
      events: webhookEvents,
    });
    setWebhookName('');
    setWebhookUrl('');
    setShowWebhookModal(false);
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    setCopiedKey(true);
    setTimeout(() => setCopiedKey(false), 2000);
  };

  return (
    <div className="space-y-6 max-w-7xl mx-auto p-4 sm:p-6" data-testid="integrations-management-hub">
      {/* Top Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-5">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
            <Plug className="w-7 h-7 text-indigo-600 dark:text-indigo-400" />
            Integration Management, OAuth & Webhook Platform
          </h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Tenant-scoped integrations directory, encrypted credentials, public API keys, and signed outbound webhooks
          </p>
        </div>

        <button
          onClick={() => refetchDir()}
          className="self-start md:self-auto px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 flex items-center gap-2 transition-colors"
        >
          <RefreshCw className="w-3.5 h-3.5" />
          Refresh Status
        </button>
      </div>

      {/* Tabs */}
      <div className="flex border-b border-slate-200 dark:border-slate-800 space-x-2 sm:space-x-6 overflow-x-auto">
        <button
          onClick={() => setActiveTab('directory')}
          className={`flex items-center gap-2 py-3 px-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${
            activeTab === 'directory'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400'
          }`}
        >
          <Plug className="w-4 h-4" />
          Integration Directory ({directory.length})
        </button>

        <button
          onClick={() => setActiveTab('connected')}
          className={`flex items-center gap-2 py-3 px-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${
            activeTab === 'connected'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400'
          }`}
        >
          <Zap className="w-4 h-4" />
          Connected Accounts & Sync ({connectedList.length})
        </button>

        <button
          onClick={() => setActiveTab('apikeys')}
          className={`flex items-center gap-2 py-3 px-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${
            activeTab === 'apikeys'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400'
          }`}
        >
          <Key className="w-4 h-4" />
          Tenant API Keys ({apiKeys.length})
        </button>

        <button
          onClick={() => setActiveTab('webhooks')}
          className={`flex items-center gap-2 py-3 px-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${
            activeTab === 'webhooks'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400'
          }`}
        >
          <Webhook className="w-4 h-4" />
          Outbound Webhooks ({webhooks.length})
        </button>

        <button
          onClick={() => setActiveTab('docs')}
          className={`flex items-center gap-2 py-3 px-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${
            activeTab === 'docs'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400'
          }`}
        >
          <BookOpen className="w-4 h-4" />
          OpenAPI 3.0 Documentation
        </button>
      </div>

      {/* TAB 1: INTEGRATION DIRECTORY */}
      {activeTab === 'directory' && (
        <div className="space-y-6">
          {/* Filter Pills & Search */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div className="flex flex-wrap items-center gap-1.5">
              {['all', 'advertising', 'marketing', 'communication', 'telephony', 'payment', 'productivity', 'storage', 'developer'].map(
                (cat) => (
                  <button
                    key={cat}
                    onClick={() => setCategoryFilter(cat)}
                    className={`px-3 py-1 rounded-full text-xs font-semibold capitalize transition-colors ${
                      categoryFilter === cat
                        ? 'bg-indigo-600 text-white'
                        : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'
                    }`}
                  >
                    {cat}
                  </button>
                )
              )}
            </div>

            <div className="relative w-full sm:w-64">
              <Search className="w-3.5 h-3.5 text-slate-400 absolute left-3 top-3" />
              <input
                type="text"
                placeholder="Search integrations..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full pl-9 pr-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg text-xs bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none"
              />
            </div>
          </div>

          {/* Cards Grid */}
          {dirLoading ? (
            <div className="py-16 text-center text-slate-500">
              <RefreshCw className="w-7 h-7 animate-spin mx-auto mb-2 text-indigo-600" />
              Loading integration catalog...
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
              {filteredDirectory.map((item) => (
                <div
                  key={item.provider}
                  className="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm flex flex-col justify-between hover:border-indigo-300 dark:hover:border-indigo-700 transition-colors"
                >
                  <div className="space-y-3">
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <span className="text-[10px] font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                          {item.category}
                        </span>
                        <h3 className="text-sm font-bold text-slate-900 dark:text-white mt-0.5">{item.name}</h3>
                      </div>
                      {item.is_connected ? (
                        <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded-full border border-emerald-200 dark:border-emerald-800">
                          <CheckCircle2 className="w-3 h-3" />
                          Connected
                        </span>
                      ) : (
                        <span className="text-[11px] font-medium text-slate-400 bg-slate-50 dark:bg-slate-800 px-2 py-0.5 rounded-full">
                          Available
                        </span>
                      )}
                    </div>

                    <p className="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">{item.description}</p>

                    {/* Features Badges */}
                    <div className="flex flex-wrap gap-1 pt-1">
                      {item.supported_features.slice(0, 3).map((f, i) => (
                        <span
                          key={i}
                          className="text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 px-2 py-0.5 rounded font-medium"
                        >
                          {f}
                        </span>
                      ))}
                    </div>
                  </div>

                  <div className="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between mt-4">
                    <button
                      onClick={() => handleOpenConnect(item)}
                      className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 ${
                        item.is_connected
                          ? 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200'
                          : 'bg-indigo-600 hover:bg-indigo-700 text-white'
                      }`}
                    >
                      {item.is_connected ? 'Manage Settings' : 'Connect Account'}
                    </button>
                    {item.limitations?.length > 0 && (
                      <span className="text-[10px] text-slate-400" title={item.limitations[0]}>
                        Limitations apply
                      </span>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* TAB 2: CONNECTED ACCOUNTS & SYNC */}
      {activeTab === 'connected' && (
        <div className="space-y-6">
          <h3 className="text-base font-bold text-slate-900 dark:text-white">Active Integrations & Sync Health</h3>

          {connectedList.length === 0 ? (
            <div className="py-12 text-center text-xs text-slate-400 border border-dashed border-slate-200 dark:border-slate-800 rounded-xl">
              No accounts currently connected. Browse the Integration Directory tab to connect your first provider.
            </div>
          ) : (
            <div className="space-y-4">
              {connectedList.map((item) => {
                const conn = item.connection!;
                return (
                  <div
                    key={conn.id}
                    className="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4"
                  >
                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <span className="font-bold text-sm text-slate-900 dark:text-white">{conn.name}</span>
                        <span
                          className={`text-[10px] font-bold px-2 py-0.5 rounded-full capitalize ${
                            conn.status === 'connected'
                              ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                              : 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300'
                          }`}
                        >
                          {conn.status}
                        </span>
                      </div>
                      <div className="text-xs text-slate-500 flex flex-wrap items-center gap-3">
                        <span>Provider: {item.name}</span>
                        <span>Auth Type: {conn.auth_type}</span>
                        {conn.last_sync_at && (
                          <span>Last Sync: {new Date(conn.last_sync_at).toLocaleString()}</span>
                        )}
                      </div>
                      {conn.last_error && <p className="text-xs text-rose-600 font-medium">Error: {conn.last_error}</p>}
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                      <button
                        onClick={() => testMutation.mutate(conn.id)}
                        disabled={testMutation.isPending}
                        className="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                      >
                        {testMutation.isPending ? 'Verifying...' : 'Diagnostics'}
                      </button>
                      <button
                        onClick={() => syncMutation.mutate(conn.id)}
                        disabled={syncMutation.isPending}
                        className="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-medium transition-colors flex items-center gap-1.5"
                      >
                        <RefreshCw className={`w-3 h-3 ${syncMutation.isPending ? 'animate-spin' : ''}`} />
                        Sync Now
                      </button>
                      <button
                        onClick={() => setViewingSyncLogsIntegrationId(conn.id)}
                        className="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50"
                      >
                        Sync Logs
                      </button>
                      <button
                        onClick={() => disconnectMutation.mutate(conn.id)}
                        className="px-3 py-1.5 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg text-xs font-semibold transition-colors"
                      >
                        Disconnect
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          )}

          {/* Sync Logs Table */}
          {viewingSyncLogsIntegrationId && (
            <div className="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm space-y-3">
              <div className="flex items-center justify-between">
                <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                  Data Synchronization History (Integration #{viewingSyncLogsIntegrationId})
                </h4>
                <button
                  onClick={() => setViewingSyncLogsIntegrationId(null)}
                  className="text-xs text-slate-400 hover:text-slate-600"
                >
                  Close
                </button>
              </div>

              {syncLogs.length === 0 ? (
                <div className="py-6 text-center text-xs text-slate-400">No sync execution logs recorded yet.</div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-xs text-left">
                    <thead className="bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700">
                      <tr>
                        <th className="py-2 px-3">Sync Type</th>
                        <th className="py-2 px-3">Status</th>
                        <th className="py-2 px-3">Items Processed</th>
                        <th className="py-2 px-3">Duration</th>
                        <th className="py-2 px-3">Timestamp</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                      {syncLogs.map((log) => (
                        <tr key={log.id}>
                          <td className="py-2 px-3 font-medium capitalize">{log.sync_type}</td>
                          <td className="py-2 px-3">
                            <span
                              className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                log.status === 'success'
                                  ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                  : 'bg-rose-50 text-rose-700'
                              }`}
                            >
                              {log.status}
                            </span>
                          </td>
                          <td className="py-2 px-3">{log.items_processed} items</td>
                          <td className="py-2 px-3">{log.duration_ms} ms</td>
                          <td className="py-2 px-3 text-slate-400">{new Date(log.started_at).toLocaleString()}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          )}
        </div>
      )}

      {/* TAB 3: TENANT API KEYS */}
      {activeTab === 'apikeys' && (
        <div className="space-y-6">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <h3 className="text-base font-bold text-slate-900 dark:text-white">Tenant Public API Keys</h3>
              <p className="text-xs text-slate-500">
                Generate high-entropy scoped API keys for external services, ERPs, and custom integrations.
              </p>
            </div>
            <button
              onClick={() => {
                setGeneratedSecret(null);
                setNewKeyName('');
                setShowApiKeyModal(true);
              }}
              className="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-colors self-start"
            >
              <Plus className="w-3.5 h-3.5" />
              Generate API Key
            </button>
          </div>

          {keysLoading ? (
            <div className="py-12 text-center text-xs text-slate-400">Loading API keys...</div>
          ) : apiKeys.length === 0 ? (
            <div className="py-12 text-center text-xs text-slate-400 border border-dashed border-slate-200 dark:border-slate-800 rounded-xl">
              No active API keys found. Click "Generate API Key" to authorize custom applications.
            </div>
          ) : (
            <div className="space-y-3">
              {apiKeys.map((k) => (
                <div
                  key={k.id}
                  className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4"
                >
                  <div className="space-y-1">
                    <div className="flex items-center gap-2">
                      <span className="font-semibold text-xs text-slate-900 dark:text-white">{k.name}</span>
                      <code className="bg-slate-100 dark:bg-slate-800 text-[11px] px-2 py-0.5 rounded font-mono text-indigo-600 dark:text-indigo-400">
                        {k.key_prefix}••••••••
                      </code>
                      {k.is_revoked && (
                        <span className="text-[10px] font-bold text-rose-600 bg-rose-50 dark:bg-rose-950/40 px-2 py-0.5 rounded-full">
                          Revoked
                        </span>
                      )}
                    </div>
                    <div className="text-[11px] text-slate-500 flex flex-wrap items-center gap-3">
                      <span>Scopes: {k.scopes.join(', ')}</span>
                      <span>Rate Limit: {k.rate_limit_per_minute} req/min</span>
                      {k.last_used_at && <span>Last Used: {new Date(k.last_used_at).toLocaleDateString()}</span>}
                    </div>
                  </div>

                  {!k.is_revoked && (
                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => rotateApiKeyMutation.mutate(k.id)}
                        className="px-2.5 py-1 text-xs border border-slate-200 dark:border-slate-700 rounded text-slate-600 hover:bg-slate-50 dark:text-slate-300"
                        title="Rotate Secret Key"
                      >
                        Rotate
                      </button>
                      <button
                        onClick={() => revokeApiKeyMutation.mutate(k.id)}
                        className="px-2.5 py-1 text-xs text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded font-semibold"
                        title="Revoke API Key"
                      >
                        Revoke
                      </button>
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* TAB 4: OUTBOUND WEBHOOKS */}
      {activeTab === 'webhooks' && (
        <div className="space-y-6">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <h3 className="text-base font-bold text-slate-900 dark:text-white">Outbound Webhook Subscriptions</h3>
              <p className="text-xs text-slate-500">
                Stream real-time CRM events to external HTTPS endpoints with cryptographic HMAC SHA-256 signatures.
              </p>
            </div>
            <button
              onClick={() => setShowWebhookModal(true)}
              className="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-colors self-start"
            >
              <Plus className="w-3.5 h-3.5" />
              Register Webhook Endpoint
            </button>
          </div>

          {webhooksLoading ? (
            <div className="py-12 text-center text-xs text-slate-400">Loading webhooks...</div>
          ) : webhooks.length === 0 ? (
            <div className="py-12 text-center text-xs text-slate-400 border border-dashed border-slate-200 dark:border-slate-800 rounded-xl">
              No outbound webhooks registered yet. Click "Register Webhook Endpoint" to configure event streaming.
            </div>
          ) : (
            <div className="space-y-4">
              {webhooks.map((wh) => (
                <div
                  key={wh.id}
                  className="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4"
                >
                  <div className="space-y-1.5">
                    <div className="flex items-center gap-2">
                      <span className="font-bold text-xs text-slate-900 dark:text-white">{wh.name}</span>
                      <span
                        className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${
                          wh.is_active
                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                            : 'bg-slate-100 text-slate-500'
                        }`}
                      >
                        {wh.is_active ? 'Active' : 'Paused'}
                      </span>
                    </div>
                    <code className="text-xs font-mono text-slate-600 dark:text-slate-300 block truncate max-w-md">
                      {wh.url}
                    </code>
                    <div className="text-[11px] text-slate-500 flex flex-wrap items-center gap-3">
                      <span>Events: {wh.events.join(', ')}</span>
                      <span>Signing Secret: {wh.masked_signing_secret || 'whsec_••••••••'}</span>
                      {wh.last_delivery_status && <span>Status: {wh.last_delivery_status}</span>}
                    </div>
                  </div>

                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => testPingMutation.mutate(wh.id)}
                      disabled={testPingMutation.isPending}
                      className="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 flex items-center gap-1.5"
                    >
                      <Send className="w-3 h-3" />
                      Test Ping
                    </button>
                    <button
                      onClick={() => setViewingWebhookLogsId(wh.id)}
                      className="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50"
                    >
                      Delivery Logs
                    </button>
                    <button
                      onClick={() => deleteWebhookMutation.mutate(wh.id)}
                      className="p-1.5 text-slate-400 hover:text-rose-600 transition-colors"
                      title="Delete Webhook"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}

          {/* Webhook Delivery Logs Drawer */}
          {viewingWebhookLogsId && (
            <div className="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm space-y-3">
              <div className="flex items-center justify-between">
                <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                  Webhook Event Dispatch Logs (Webhook #{viewingWebhookLogsId})
                </h4>
                <button onClick={() => setViewingWebhookLogsId(null)} className="text-xs text-slate-400 hover:text-slate-600">
                  Close
                </button>
              </div>

              {webhookLogs.length === 0 ? (
                <div className="py-6 text-center text-xs text-slate-400">No delivery logs for this webhook.</div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-xs text-left">
                    <thead className="bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700">
                      <tr>
                        <th className="py-2 px-3">Event Type</th>
                        <th className="py-2 px-3">Event ID</th>
                        <th className="py-2 px-3">Status</th>
                        <th className="py-2 px-3">HTTP Code</th>
                        <th className="py-2 px-3">Attempt</th>
                        <th className="py-2 px-3">Timestamp</th>
                        <th className="py-2 px-3 text-right">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                      {webhookLogs.map((log) => (
                        <tr key={log.id}>
                          <td className="py-2 px-3 font-semibold text-slate-900 dark:text-white">{log.event_type}</td>
                          <td className="py-2 px-3 font-mono text-[10px] text-slate-400">{log.event_id.slice(0, 8)}...</td>
                          <td className="py-2 px-3">
                            <span
                              className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                log.status === 'success'
                                  ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                  : 'bg-rose-50 text-rose-700'
                              }`}
                            >
                              {log.status}
                            </span>
                          </td>
                          <td className="py-2 px-3">{log.response_status_code || '-'}</td>
                          <td className="py-2 px-3">#{log.attempt_number}</td>
                          <td className="py-2 px-3 text-slate-400">{new Date(log.created_at).toLocaleString()}</td>
                          <td className="py-2 px-3 text-right">
                            <button
                              onClick={() => redeliverMutation.mutate(log.id)}
                              className="text-xs text-indigo-600 hover:text-indigo-700 font-medium"
                            >
                              Redeliver
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          )}
        </div>
      )}

      {/* TAB 5: OPENAPI 3.0 DOCUMENTATION */}
      {activeTab === 'docs' && (
        <div className="space-y-6">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <FileCode className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                OpenAPI 3.0.3 Enterprise REST Specification
              </h3>
              <p className="text-xs text-slate-500">
                Interactive reference for Leads, Deals, Invoices, Tasks, Conversations, and Outbound Webhooks.
              </p>
            </div>
            <a
              href="/api/v1/crm/docs/openapi.json"
              target="_blank"
              rel="noreferrer"
              className="px-3.5 py-1.5 border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 rounded-lg text-xs font-semibold flex items-center gap-1.5 hover:bg-indigo-100 transition-colors self-start"
            >
              <ExternalLink className="w-3.5 h-3.5" />
              Download openapi.json
            </a>
          </div>

          {openApiSpec ? (
            <div className="space-y-5">
              {/* Auth Header Guide */}
              <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm space-y-2">
                <span className="text-xs font-bold uppercase tracking-wider text-slate-500">Authentication Schemes</span>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-1">
                  <div className="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 text-xs space-y-1">
                    <span className="font-semibold text-slate-900 dark:text-white">1. Service API Key Header</span>
                    <code className="block font-mono text-[11px] text-indigo-600 dark:text-indigo-400">
                      X-API-KEY: crm_live_xxxxxxxxxxxxxxxxxxxxxxxx
                    </code>
                    <p className="text-[11px] text-slate-500">Used by automated backend daemons and server scripts.</p>
                  </div>
                  <div className="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 text-xs space-y-1">
                    <span className="font-semibold text-slate-900 dark:text-white">2. Bearer Session Token</span>
                    <code className="block font-mono text-[11px] text-indigo-600 dark:text-indigo-400">
                      Authorization: Bearer 17|GFDRmdjVBl4P...
                    </code>
                    <p className="text-[11px] text-slate-500">Obtained from Sanctum auth login sessions.</p>
                  </div>
                </div>
              </div>

              {/* Endpoints Table */}
              <div className="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm space-y-3">
                <span className="text-xs font-bold uppercase tracking-wider text-slate-500">Core RESTful Endpoints</span>
                <div className="space-y-3 pt-1">
                  {Object.entries(openApiSpec.paths || {}).map(([path, methods]: [string, any]) => (
                    <div key={path} className="border border-slate-100 dark:border-slate-800 rounded-lg p-3 space-y-2">
                      {Object.entries(methods).map(([method, def]: [string, any]) => (
                        <div key={method} className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                          <div className="flex items-center gap-2">
                            <span
                              className={`text-[10px] font-bold px-2 py-0.5 rounded uppercase ${
                                method === 'get'
                                  ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300'
                                  : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                              }`}
                            >
                              {method}
                            </span>
                            <code className="text-xs font-mono font-semibold text-slate-900 dark:text-white">{path}</code>
                          </div>
                          <span className="text-xs text-slate-500">{def.summary}</span>
                        </div>
                      ))}
                    </div>
                  ))}
                </div>
              </div>
            </div>
          ) : (
            <div className="py-12 text-center text-xs text-slate-400">Loading OpenAPI documentation...</div>
          )}
        </div>
      )}

      {/* MODAL: CONNECT CREDENTIALS */}
      {showConnectModal && selectedIntegration && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-xl space-y-4">
            <h3 className="text-base font-bold text-slate-900 dark:text-white">
              Connect {selectedIntegration.name}
            </h3>

            <div className="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg text-xs text-slate-600 dark:text-slate-300 space-y-1">
              <span className="font-semibold block text-slate-900 dark:text-white">Setup Instructions:</span>
              <p>{selectedIntegration.setup_instructions}</p>
            </div>

            <div className="space-y-3 text-xs">
              <div>
                <label className="block font-medium text-slate-700 dark:text-slate-300 mb-1">
                  API Key / Client ID / Token
                </label>
                <input
                  type="password"
                  placeholder="Paste provider key or token..."
                  value={credInputs['api_key'] || ''}
                  onChange={(e) => setCredInputs({ ...credInputs, api_key: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-transparent text-slate-900 dark:text-white focus:outline-none"
                />
              </div>

              <div>
                <label className="block font-medium text-slate-700 dark:text-slate-300 mb-1">
                  API Secret / Secret Key (Encrypted at rest)
                </label>
                <input
                  type="password"
                  placeholder="Paste secret..."
                  value={credInputs['api_secret'] || ''}
                  onChange={(e) => setCredInputs({ ...credInputs, api_secret: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-transparent text-slate-900 dark:text-white focus:outline-none"
                />
              </div>
            </div>

            <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
              <button
                onClick={() => setShowConnectModal(false)}
                className="px-3 py-1.5 text-xs text-slate-600 dark:text-slate-400 hover:text-slate-900"
              >
                Cancel
              </button>
              <button
                onClick={handleSaveCredentials}
                disabled={connectMutation.isPending}
                className="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold transition-colors disabled:opacity-50"
              >
                {connectMutation.isPending ? 'Verifying & Saving...' : 'Save & Connect'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL: GENERATE API KEY */}
      {showApiKeyModal && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
            <h3 className="text-base font-bold text-slate-900 dark:text-white">Generate Service API Key</h3>

            {generatedSecret ? (
              <div className="space-y-3 text-xs">
                <div className="p-3 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 rounded-lg text-emerald-800 dark:text-emerald-300 space-y-1">
                  <span className="font-bold flex items-center gap-1.5">
                    <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                    API Key Created Successfully
                  </span>
                  <p className="text-[11px]">
                    Copy your secret key immediately. For security, it cannot be displayed again.
                  </p>
                </div>

                <div className="p-3 bg-slate-100 dark:bg-slate-800 rounded-lg font-mono text-[11px] break-all select-all text-slate-900 dark:text-white border border-slate-200 dark:border-slate-700">
                  {generatedSecret}
                </div>

                <button
                  onClick={() => copyToClipboard(generatedSecret)}
                  className="w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold flex items-center justify-center gap-2 transition-colors"
                >
                  {copiedKey ? <Check className="w-4 h-4" /> : <Copy className="w-4 h-4" />}
                  {copiedKey ? 'Copied to Clipboard!' : 'Copy Secret Key'}
                </button>
              </div>
            ) : (
              <div className="space-y-3 text-xs">
                <div>
                  <label className="block font-medium text-slate-700 dark:text-slate-300 mb-1">Key Name / Description</label>
                  <input
                    type="text"
                    placeholder="e.g. Production Mobile App Backend"
                    value={newKeyName}
                    onChange={(e) => setNewKeyName(e.target.value)}
                    className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-transparent text-slate-900 dark:text-white focus:outline-none"
                  />
                </div>

                <div>
                  <label className="block font-medium text-slate-700 dark:text-slate-300 mb-1">Permission Scopes</label>
                  <div className="space-y-1.5 pt-1">
                    {['*', 'leads:read', 'leads:write', 'deals:read', 'deals:write', 'invoices:read', 'webhooks:manage'].map(
                      (scope) => (
                        <label key={scope} className="flex items-center gap-2 cursor-pointer text-slate-700 dark:text-slate-300">
                          <input
                            type="checkbox"
                            checked={selectedScopes.includes(scope)}
                            onChange={(e) => {
                              if (e.target.checked) {
                                setSelectedScopes([...selectedScopes, scope]);
                              } else {
                                setSelectedScopes(selectedScopes.filter((s) => s !== scope));
                              }
                            }}
                            className="rounded border-slate-300 text-indigo-600"
                          />
                          <span>{scope}</span>
                        </label>
                      )
                    )}
                  </div>
                </div>
              </div>
            )}

            <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
              <button
                onClick={() => setShowApiKeyModal(false)}
                className="px-3 py-1.5 text-xs text-slate-600 dark:text-slate-400 hover:text-slate-900"
              >
                {generatedSecret ? 'Done' : 'Cancel'}
              </button>
              {!generatedSecret && (
                <button
                  onClick={handleCreateApiKey}
                  disabled={!newKeyName.trim() || createApiKeyMutation.isPending}
                  className="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold transition-colors disabled:opacity-50"
                >
                  {createApiKeyMutation.isPending ? 'Generating...' : 'Generate Key'}
                </button>
              )}
            </div>
          </div>
        </div>
      )}

      {/* MODAL: REGISTER WEBHOOK */}
      {showWebhookModal && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
            <h3 className="text-base font-bold text-slate-900 dark:text-white">Register Outbound Webhook</h3>

            <div className="space-y-3 text-xs">
              <div>
                <label className="block font-medium text-slate-700 dark:text-slate-300 mb-1">Webhook Name</label>
                <input
                  type="text"
                  placeholder="e.g. ERP Fulfillment Service"
                  value={webhookName}
                  onChange={(e) => setWebhookName(e.target.value)}
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-transparent text-slate-900 dark:text-white focus:outline-none"
                />
              </div>

              <div>
                <label className="block font-medium text-slate-700 dark:text-slate-300 mb-1">Destination URL (HTTPS)</label>
                <input
                  type="url"
                  placeholder="https://your-domain.test/api/webhooks/crm"
                  value={webhookUrl}
                  onChange={(e) => setWebhookUrl(e.target.value)}
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-transparent text-slate-900 dark:text-white focus:outline-none"
                />
              </div>

              <div>
                <label className="block font-medium text-slate-700 dark:text-slate-300 mb-1">Subscribed Events</label>
                <div className="space-y-1.5 pt-1">
                  {['*', 'lead.created', 'lead.converted', 'deal.won', 'deal.lost', 'invoice.paid', 'ticket.resolved'].map(
                    (evt) => (
                      <label key={evt} className="flex items-center gap-2 cursor-pointer text-slate-700 dark:text-slate-300">
                        <input
                          type="checkbox"
                          checked={webhookEvents.includes(evt)}
                          onChange={(e) => {
                            if (e.target.checked) {
                              setWebhookEvents([...webhookEvents, evt]);
                            } else {
                              setWebhookEvents(webhookEvents.filter((ev) => ev !== evt));
                            }
                          }}
                          className="rounded border-slate-300 text-indigo-600"
                        />
                        <span>{evt}</span>
                      </label>
                    )
                  )}
                </div>
              </div>
            </div>

            <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
              <button
                onClick={() => setShowWebhookModal(false)}
                className="px-3 py-1.5 text-xs text-slate-600 dark:text-slate-400 hover:text-slate-900"
              >
                Cancel
              </button>
              <button
                onClick={handleCreateWebhook}
                disabled={!webhookName.trim() || !webhookUrl.trim() || createWebhookMutation.isPending}
                className="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold transition-colors disabled:opacity-50"
              >
                {createWebhookMutation.isPending ? 'Registering...' : 'Register Webhook'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
