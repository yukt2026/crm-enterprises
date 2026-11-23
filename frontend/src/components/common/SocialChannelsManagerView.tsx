import React, { useState } from 'react';
import {
  MessageSquare,
  Instagram,
  Facebook,
  ShieldCheck,
  CheckCircle2,
  AlertTriangle,
  RefreshCw,
  Plus,
  Link,
  Bot,
  UserCheck,
  Clock,
  Layers,
  Copy,
  ExternalLink,
  Trash2,
  Lock,
  GitMerge,
  Search,
  Check,
  Activity,
  Code
} from 'lucide-react';
import { useSocialChannels } from '../../hooks/useSocialChannels';
import { SocialChannelAccountItem, CustomerSocialIdentityItem } from '../../api/types';

export const SocialChannelsManagerView: React.FC = () => {
  const {
    accounts,
    identities,
    logs,
    diagnostics,
    loading,
    error,
    refresh,
    connectAccount,
    disconnectAccount,
    linkIdentity,
    mergeIdentities,
  } = useSocialChannels();

  const [activeTab, setActiveTab] = useState<'accounts' | 'identities' | 'webhook_setup' | 'logs'>('accounts');
  const [showConnectModal, setShowConnectModal] = useState<boolean>(false);
  const [connectProvider, setConnectProvider] = useState<'facebook' | 'instagram'>('facebook');
  const [pageId, setPageId] = useState<string>('');
  const [pageName, setPageName] = useState<string>('');
  const [accessToken, setAccessToken] = useState<string>('');
  const [instagramId, setInstagramId] = useState<string>('');
  const [instagramUsername, setInstagramUsername] = useState<string>('');
  const [aiEnabled, setAiEnabled] = useState<boolean>(true);
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [copiedUrl, setCopiedUrl] = useState<boolean>(false);
  const [copiedToken, setCopiedToken] = useState<boolean>(false);

  // Identity Linking & Merge State
  const [selectedIdentity, setSelectedIdentity] = useState<CustomerSocialIdentityItem | null>(null);
  const [linkLeadId, setLinkLeadId] = useState<string>('');
  const [searchIdentityTerm, setSearchIdentityTerm] = useState<string>('');
  const [mergeSecondaryId, setMergeSecondaryId] = useState<string>('');
  const [showMergeModal, setShowMergeModal] = useState<boolean>(false);

  const handleConnect = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!pageId || !accessToken) return;
    setIsSubmitting(true);
    try {
      await connectAccount({
        provider: connectProvider,
        page_id: pageId,
        page_name: pageName || (connectProvider === 'facebook' ? `Facebook Page ${pageId}` : `IG ${instagramUsername || pageId}`),
        page_access_token: accessToken,
        instagram_business_id: connectProvider === 'instagram' ? instagramId : undefined,
        instagram_username: connectProvider === 'instagram' ? instagramUsername : undefined,
        ai_enabled: aiEnabled,
      });
      setShowConnectModal(false);
      setPageId('');
      setPageName('');
      setAccessToken('');
      setInstagramId('');
      setInstagramUsername('');
    } catch (err: any) {
      alert(err?.message || 'Failed to connect social account.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleLinkSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedIdentity || !linkLeadId) return;
    setIsSubmitting(true);
    try {
      await linkIdentity(selectedIdentity.id, { lead_id: parseInt(linkLeadId, 10) });
      setSelectedIdentity(null);
      setLinkLeadId('');
    } catch (err: any) {
      alert(err?.message || 'Failed to link identity to lead.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleMergeSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedIdentity || !mergeSecondaryId) return;
    setIsSubmitting(true);
    try {
      await mergeIdentities(selectedIdentity.id, parseInt(mergeSecondaryId, 10));
      setShowMergeModal(false);
      setSelectedIdentity(null);
      setMergeSecondaryId('');
    } catch (err: any) {
      alert(err?.message || 'Failed to merge identities.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const callbackUrl = diagnostics?.webhook_endpoint || `${window.location.origin}/api/v1/webhooks/meta/social`;
  const verifyToken = diagnostics?.webhook_verify_token || 'crm_meta_webhook_secret_2026';

  const copyToClipboard = (text: string, type: 'url' | 'token') => {
    navigator.clipboard.writeText(text);
    if (type === 'url') {
      setCopiedUrl(true);
      setTimeout(() => setCopiedUrl(false), 2000);
    } else {
      setCopiedToken(true);
      setTimeout(() => setCopiedToken(false), 2000);
    }
  };

  const filteredIdentities = identities.filter((item) => {
    const term = searchIdentityTerm.toLowerCase();
    return (
      item.scoped_id.toLowerCase().includes(term) ||
      (item.display_name && item.display_name.toLowerCase().includes(term)) ||
      (item.username && item.username.toLowerCase().includes(term)) ||
      (item.lead?.name && item.lead.name.toLowerCase().includes(term))
    );
  });

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-gray-200 dark:border-gray-800 pb-5">
        <div>
          <div className="flex items-center gap-3">
            <div className="p-2 rounded-lg bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400">
              <MessageSquare className="w-6 h-6" />
            </div>
            <div>
              <h1 className="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                Meta Messaging Integration
                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                  <ShieldCheck className="w-3.5 h-3.5 mr-1" /> Official Meta Graph API
                </span>
              </h1>
              <p className="text-sm text-gray-500 dark:text-gray-400">
                Connect Facebook Pages & Instagram Professional accounts directly to your Unified Inbox with 24h Meta compliance.
              </p>
            </div>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() => refresh()}
            disabled={loading}
            className="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-700 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none"
          >
            <RefreshCw className={`w-4 h-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
            Refresh
          </button>
          <button
            onClick={() => setShowConnectModal(true)}
            className="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none"
          >
            <Plus className="w-4 h-4 mr-1.5" />
            Connect Account
          </button>
        </div>
      </div>

      {error && (
        <div className="p-4 rounded-lg bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900 text-red-700 dark:text-red-300 flex items-center gap-2">
          <AlertTriangle className="w-5 h-5 flex-shrink-0" />
          <p className="text-sm">{error}</p>
        </div>
      )}

      {/* KPI Overview */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
          <div className="flex items-center justify-between text-gray-500 dark:text-gray-400 mb-2">
            <span className="text-xs font-semibold uppercase tracking-wider">Connected Accounts</span>
            <Facebook className="w-5 h-5 text-blue-600" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-bold text-gray-900 dark:text-white">
              {diagnostics?.connected_accounts_count ?? accounts.length}
            </span>
            <span className="text-xs text-gray-500">
              ({diagnostics?.facebook_pages_count ?? 0} FB, {diagnostics?.instagram_accounts_count ?? 0} IG)
            </span>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
          <div className="flex items-center justify-between text-gray-500 dark:text-gray-400 mb-2">
            <span className="text-xs font-semibold uppercase tracking-wider">Active 24h Windows</span>
            <Clock className="w-5 h-5 text-amber-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-bold text-gray-900 dark:text-white">
              {diagnostics?.active_24h_windows ?? 0}
            </span>
            <span className="text-xs text-emerald-600 font-medium">Standard reply allowed</span>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
          <div className="flex items-center justify-between text-gray-500 dark:text-gray-400 mb-2">
            <span className="text-xs font-semibold uppercase tracking-wider">Social Identities</span>
            <UserCheck className="w-5 h-5 text-indigo-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-bold text-gray-900 dark:text-white">
              {diagnostics?.total_identities ?? identities.length}
            </span>
            <span className="text-xs text-gray-500">
              ({diagnostics?.linked_identities ?? 0} linked to CRM)
            </span>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
          <div className="flex items-center justify-between text-gray-500 dark:text-gray-400 mb-2">
            <span className="text-xs font-semibold uppercase tracking-wider">Webhook Ingestion (24h)</span>
            <Activity className="w-5 h-5 text-emerald-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-bold text-gray-900 dark:text-white">
              {diagnostics?.webhook_events_last_24h ?? logs.length}
            </span>
            <span className="text-xs text-emerald-600 font-medium">HMAC Verified</span>
          </div>
        </div>
      </div>

      {/* Navigation Sub-Tabs */}
      <div className="border-b border-gray-200 dark:border-gray-700">
        <nav className="flex space-x-8" aria-label="Tabs">
          <button
            onClick={() => setActiveTab('accounts')}
            className={`py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 ${
              activeTab === 'accounts'
                ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200'
            }`}
          >
            <Facebook className="w-4 h-4" />
            Connected Accounts ({accounts.length})
          </button>

          <button
            onClick={() => setActiveTab('identities')}
            className={`py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 ${
              activeTab === 'identities'
                ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200'
            }`}
          >
            <UserCheck className="w-4 h-4" />
            Social Identities ({identities.length})
          </button>

          <button
            onClick={() => setActiveTab('webhook_setup')}
            className={`py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 ${
              activeTab === 'webhook_setup'
                ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200'
            }`}
          >
            <Code className="w-4 h-4" />
            Meta Webhook & OAuth Setup
          </button>

          <button
            onClick={() => setActiveTab('logs')}
            className={`py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 ${
              activeTab === 'logs'
                ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200'
            }`}
          >
            <Activity className="w-4 h-4" />
            Webhook Ingestion Logs
          </button>
        </nav>
      </div>

      {/* Tab 1: Connected Accounts */}
      {activeTab === 'accounts' && (
        <div className="space-y-4">
          {accounts.length === 0 ? (
            <div className="bg-white dark:bg-gray-800 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 p-8 text-center">
              <MessageSquare className="w-12 h-12 text-gray-400 mx-auto mb-3" />
              <h3 className="text-base font-semibold text-gray-900 dark:text-white">No social accounts connected</h3>
              <p className="text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto mt-1 mb-4">
                Connect your Facebook Page or Instagram Professional account to receive messages in the Unified Inbox.
              </p>
              <button
                onClick={() => setShowConnectModal(true)}
                className="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-sm"
              >
                <Plus className="w-4 h-4 mr-1.5" />
                Connect Your First Account
              </button>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {accounts.map((acc) => (
                <div
                  key={acc.id}
                  className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm space-y-4"
                >
                  <div className="flex items-start justify-between">
                    <div className="flex items-center gap-3">
                      <div className={`p-2.5 rounded-lg ${
                        acc.provider === 'facebook'
                          ? 'bg-blue-50 text-blue-600 dark:bg-blue-950/60 dark:text-blue-400'
                          : 'bg-pink-50 text-pink-600 dark:bg-pink-950/60 dark:text-pink-400'
                      }`}>
                        {acc.provider === 'facebook' ? <Facebook className="w-6 h-6" /> : <Instagram className="w-6 h-6" />}
                      </div>
                      <div>
                        <h4 className="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                          {acc.page_name}
                          {acc.is_connected ? (
                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                              Active
                            </span>
                          ) : (
                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                              Disconnected
                            </span>
                          )}
                        </h4>
                        <p className="text-xs text-gray-500 font-mono mt-0.5">
                          ID: {acc.page_id} {acc.instagram_business_id && `• IG ID: ${acc.instagram_business_id}`}
                        </p>
                      </div>
                    </div>

                    <button
                      onClick={() => {
                        if (confirm(`Disconnect ${acc.page_name}? Incoming webhooks will be ignored.`)) {
                          disconnectAccount(acc.id);
                        }
                      }}
                      className="text-gray-400 hover:text-red-600 dark:hover:text-red-400 p-1"
                      title="Disconnect Account"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>

                  <div className="grid grid-cols-2 gap-2 text-xs text-gray-600 dark:text-gray-300 pt-2 border-t border-gray-100 dark:border-gray-700/60">
                    <div>
                      <span className="text-gray-400 block">AI Auto-Reply</span>
                      <span className="font-medium flex items-center gap-1 mt-0.5">
                        <Bot className="w-3.5 h-3.5 text-blue-500" />
                        {acc.ai_enabled ? (acc.ai_agent?.name || 'Enabled') : 'Disabled'}
                      </span>
                    </div>
                    <div>
                      <span className="text-gray-400 block">Webhook Status</span>
                      <span className="font-medium flex items-center gap-1 mt-0.5 text-emerald-600">
                        <CheckCircle2 className="w-3.5 h-3.5" />
                        Subscribed
                      </span>
                    </div>
                  </div>

                  {acc.permissions && acc.permissions.length > 0 && (
                    <div className="flex flex-wrap gap-1 pt-1">
                      {acc.permissions.map((perm) => (
                        <span key={perm} className="px-1.5 py-0.5 rounded text-[10px] font-mono bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                          {perm}
                        </span>
                      ))}
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Tab 2: Customer Social Identities */}
      {activeTab === 'identities' && (
        <div className="space-y-4">
          <div className="flex items-center justify-between gap-4">
            <div className="relative flex-1 max-w-md">
              <Search className="w-4 h-4 text-gray-400 absolute left-3 top-2.5" />
              <input
                type="text"
                placeholder="Search PSID, username, or linked lead..."
                value={searchIdentityTerm}
                onChange={(e) => setSearchIdentityTerm(e.target.value)}
                className="w-full pl-9 pr-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
              />
            </div>
          </div>

          <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
              <thead className="bg-gray-50 dark:bg-gray-900/50">
                <tr>
                  <th className="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Channel / Scoped ID</th>
                  <th className="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Display Name</th>
                  <th className="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Linked Lead / Customer</th>
                  <th className="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Merge Status</th>
                  <th className="px-4 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                {filteredIdentities.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="px-4 py-8 text-center text-gray-500">
                      No customer social identities recorded yet. Identities are created automatically when users message your Page or Instagram profile.
                    </td>
                  </tr>
                ) : (
                  filteredIdentities.map((item) => (
                    <tr key={item.id} className="hover:bg-gray-50 dark:hover:bg-gray-750">
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2">
                          {item.provider === 'facebook' ? (
                            <Facebook className="w-4 h-4 text-blue-600" />
                          ) : (
                            <Instagram className="w-4 h-4 text-pink-600" />
                          )}
                          <span className="font-mono text-xs text-gray-700 dark:text-gray-300">{item.scoped_id}</span>
                        </div>
                      </td>
                      <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">
                        {item.display_name || item.username || 'Visitor'}
                      </td>
                      <td className="px-4 py-3">
                        {item.lead ? (
                          <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                            Lead: {item.lead.name}
                          </span>
                        ) : item.customer ? (
                          <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                            Customer: {item.customer.name}
                          </span>
                        ) : (
                          <span className="text-xs text-gray-400 italic">Unlinked</span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-xs">
                        {item.merged_with_id ? (
                          <span className="text-amber-600 dark:text-amber-400 font-medium">
                            Merged into #{item.merged_with_id}
                          </span>
                        ) : (
                          <span className="text-gray-400">Primary</span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-right space-x-2">
                        <button
                          onClick={() => setSelectedIdentity(item)}
                          className="text-xs text-blue-600 dark:text-blue-400 hover:underline"
                        >
                          Link Lead
                        </button>
                        <button
                          onClick={() => {
                            setSelectedIdentity(item);
                            setShowMergeModal(true);
                          }}
                          className="text-xs text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                          Merge
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Tab 3: Webhook & Meta Setup */}
      {activeTab === 'webhook_setup' && (
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-6 shadow-sm">
          <div>
            <h3 className="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
              <ShieldCheck className="w-5 h-5 text-emerald-500" />
              Meta App Webhook Configuration
            </h3>
            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
              Configure these endpoints in your Meta Developer App under <strong>Webhooks &gt; Page</strong> and <strong>Webhooks &gt; Instagram</strong>.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="p-4 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 space-y-2">
              <label className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">
                Callback URL (Public Webhook)
              </label>
              <div className="flex items-center gap-2">
                <code className="text-xs bg-white dark:bg-gray-800 p-2 rounded border border-gray-200 dark:border-gray-700 flex-1 font-mono text-gray-800 dark:text-gray-200 truncate">
                  {callbackUrl}
                </code>
                <button
                  onClick={() => copyToClipboard(callbackUrl, 'url')}
                  className="p-2 border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-100"
                  title="Copy Callback URL"
                >
                  {copiedUrl ? <Check className="w-4 h-4 text-emerald-500" /> : <Copy className="w-4 h-4" />}
                </button>
              </div>
            </div>

            <div className="p-4 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 space-y-2">
              <label className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">
                Verify Token (hub.verify_token)
              </label>
              <div className="flex items-center gap-2">
                <code className="text-xs bg-white dark:bg-gray-800 p-2 rounded border border-gray-200 dark:border-gray-700 flex-1 font-mono text-gray-800 dark:text-gray-200 truncate">
                  {verifyToken}
                </code>
                <button
                  onClick={() => copyToClipboard(verifyToken, 'token')}
                  className="p-2 border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-100"
                  title="Copy Verify Token"
                >
                  {copiedToken ? <Check className="w-4 h-4 text-emerald-500" /> : <Copy className="w-4 h-4" />}
                </button>
              </div>
            </div>
          </div>

          <div className="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-3">
            <h4 className="text-sm font-semibold text-gray-900 dark:text-white">Required Webhook Subscription Fields</h4>
            <div className="flex flex-wrap gap-2 text-xs">
              <span className="px-2.5 py-1 bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 rounded-md font-mono">
                messages
              </span>
              <span className="px-2.5 py-1 bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 rounded-md font-mono">
                messaging_postbacks
              </span>
              <span className="px-2.5 py-1 bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 rounded-md font-mono">
                message_deliveries
              </span>
              <span className="px-2.5 py-1 bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 rounded-md font-mono">
                message_reads
              </span>
            </div>
          </div>

          <div className="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-2 text-xs text-gray-500 dark:text-gray-400">
            <p><strong>Meta 24-Hour Messaging Policy:</strong> Standard replies can be sent within 24 hours of the customer’s latest inbound message. Responses outside the 24-hour window require an approved Message Tag (e.g. <code>POST_PURCHASE_UPDATE</code>, <code>CONFIRMED_EVENT_UPDATE</code>, <code>ACCOUNT_UPDATE</code>).</p>
            <p><strong>Data Privacy & Security:</strong> Page Access Tokens are stored securely with 256-bit AES encryption at rest in your tenant database and never transmitted to the browser bundle.</p>
          </div>
        </div>
      )}

      {/* Tab 4: Webhook Logs */}
      {activeTab === 'logs' && (
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
          <div className="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h4 className="text-sm font-semibold text-gray-900 dark:text-white">Recent Webhook Ingestion Events</h4>
            <span className="text-xs text-gray-500">Showing last 50 events</span>
          </div>
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs">
            <thead className="bg-gray-50 dark:bg-gray-900/50">
              <tr>
                <th className="px-4 py-2.5 text-left font-medium text-gray-500">Timestamp</th>
                <th className="px-4 py-2.5 text-left font-medium text-gray-500">Provider</th>
                <th className="px-4 py-2.5 text-left font-medium text-gray-500">Event ID</th>
                <th className="px-4 py-2.5 text-left font-medium text-gray-500">Event Type</th>
                <th className="px-4 py-2.5 text-left font-medium text-gray-500">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
              {logs.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-gray-400">
                    No webhook events recorded yet.
                  </td>
                </tr>
              ) : (
                logs.map((log) => (
                  <tr key={log.id} className="hover:bg-gray-50 dark:hover:bg-gray-750 font-mono">
                    <td className="px-4 py-2 text-gray-600 dark:text-gray-400">{log.created_at}</td>
                    <td className="px-4 py-2 uppercase">{log.provider}</td>
                    <td className="px-4 py-2 text-gray-800 dark:text-gray-200 truncate max-w-[140px]">{log.event_id}</td>
                    <td className="px-4 py-2">{log.event_type}</td>
                    <td className="px-4 py-2">
                      <span className={`px-2 py-0.5 rounded text-[10px] font-medium ${
                        log.status === 'processed'
                          ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                          : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
                      }`}>
                        {log.status}
                      </span>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}

      {/* Connect Account Modal */}
      {showConnectModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 max-w-md w-full p-6 space-y-4">
            <h3 className="text-lg font-bold text-gray-900 dark:text-white">Connect Meta Social Account</h3>
            <form onSubmit={handleConnect} className="space-y-4">
              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Channel Provider
                </label>
                <div className="grid grid-cols-2 gap-2">
                  <button
                    type="button"
                    onClick={() => setConnectProvider('facebook')}
                    className={`py-2 px-3 rounded-lg border text-sm font-medium flex items-center justify-center gap-2 ${
                      connectProvider === 'facebook'
                        ? 'border-blue-600 bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:border-blue-500'
                        : 'border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300'
                    }`}
                  >
                    <Facebook className="w-4 h-4" /> Facebook Page
                  </button>
                  <button
                    type="button"
                    onClick={() => setConnectProvider('instagram')}
                    className={`py-2 px-3 rounded-lg border text-sm font-medium flex items-center justify-center gap-2 ${
                      connectProvider === 'instagram'
                        ? 'border-pink-600 bg-pink-50 text-pink-600 dark:bg-pink-950/50 dark:border-pink-500'
                        : 'border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300'
                    }`}
                  >
                    <Instagram className="w-4 h-4" /> Instagram DM
                  </button>
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Facebook Page ID *
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. 104829104810294"
                  value={pageId}
                  onChange={(e) => setPageId(e.target.value)}
                  className="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Page Display Name
                </label>
                <input
                  type="text"
                  placeholder="e.g. Acme Official Page"
                  value={pageName}
                  onChange={(e) => setPageName(e.target.value)}
                  className="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
                />
              </div>

              {connectProvider === 'instagram' && (
                <>
                  <div>
                    <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                      Instagram Business Account ID *
                    </label>
                    <input
                      type="text"
                      required
                      placeholder="e.g. 178414000000000"
                      value={instagramId}
                      onChange={(e) => setInstagramId(e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                      Instagram Username
                    </label>
                    <input
                      type="text"
                      placeholder="e.g. acme_official"
                      value={instagramUsername}
                      onChange={(e) => setInstagramUsername(e.target.value)}
                      className="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
                    />
                  </div>
                </>
              )}

              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Page Access Token * (Encrypted at rest)
                </label>
                <input
                  type="password"
                  required
                  placeholder="EAAB..."
                  value={accessToken}
                  onChange={(e) => setAccessToken(e.target.value)}
                  className="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-mono"
                />
              </div>

              <div className="flex items-center gap-2 pt-2">
                <input
                  type="checkbox"
                  id="aiEnabled"
                  checked={aiEnabled}
                  onChange={(e) => setAiEnabled(e.target.checked)}
                  className="rounded text-blue-600 focus:ring-blue-500"
                />
                <label htmlFor="aiEnabled" className="text-xs text-gray-700 dark:text-gray-300">
                  Enable AI Agent auto-replies with human handover
                </label>
              </div>

              <div className="flex items-center justify-end gap-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                <button
                  type="button"
                  onClick={() => setShowConnectModal(false)}
                  className="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={isSubmitting}
                  className="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg disabled:opacity-50"
                >
                  {isSubmitting ? 'Connecting...' : 'Save & Connect'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Link Identity Modal */}
      {selectedIdentity && !showMergeModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 max-w-sm w-full p-6 space-y-4">
            <h3 className="text-base font-bold text-gray-900 dark:text-white">
              Link Identity to CRM Lead
            </h3>
            <p className="text-xs text-gray-500">
              Linking <strong>{selectedIdentity.display_name || selectedIdentity.scoped_id}</strong> ({selectedIdentity.provider})
            </p>
            <form onSubmit={handleLinkSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  CRM Lead ID *
                </label>
                <input
                  type="number"
                  required
                  placeholder="e.g. 42"
                  value={linkLeadId}
                  onChange={(e) => setLinkLeadId(e.target.value)}
                  className="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
                />
              </div>
              <div className="flex items-center justify-end gap-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                <button
                  type="button"
                  onClick={() => setSelectedIdentity(null)}
                  className="px-3 py-1.5 text-xs text-gray-600 dark:text-gray-300 hover:bg-gray-100 rounded-lg"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={isSubmitting}
                  className="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg disabled:opacity-50"
                >
                  {isSubmitting ? 'Linking...' : 'Confirm Link'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Merge Identities Modal */}
      {selectedIdentity && showMergeModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 max-w-sm w-full p-6 space-y-4">
            <h3 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
              <GitMerge className="w-5 h-5 text-indigo-500" />
              Merge Social Identities
            </h3>
            <p className="text-xs text-gray-500">
              Primary: <strong>{selectedIdentity.display_name || selectedIdentity.scoped_id}</strong> (#{selectedIdentity.id})
            </p>
            <form onSubmit={handleMergeSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Secondary Identity ID (to merge into primary) *
                </label>
                <input
                  type="number"
                  required
                  placeholder="e.g. 8"
                  value={mergeSecondaryId}
                  onChange={(e) => setMergeSecondaryId(e.target.value)}
                  className="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
                />
              </div>
              <div className="flex items-center justify-end gap-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                <button
                  type="button"
                  onClick={() => {
                    setShowMergeModal(false);
                    setSelectedIdentity(null);
                  }}
                  className="px-3 py-1.5 text-xs text-gray-600 dark:text-gray-300 hover:bg-gray-100 rounded-lg"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={isSubmitting}
                  className="px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-50"
                >
                  {isSubmitting ? 'Merging...' : 'Merge Identities'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
export default SocialChannelsManagerView;
