import React, { useState } from 'react';
import {
  Globe,
  Radio,
  ShieldCheck,
  CheckCircle2,
  AlertTriangle,
  Copy,
  ExternalLink,
  Code,
  Sparkles,
  BarChart3,
  TrendingUp,
  Users,
  Eye,
  Filter,
  RefreshCw,
  Sliders,
  Settings,
  Link,
  Zap,
  HelpCircle,
  Clock,
  Layers,
  Activity,
  Bot
} from 'lucide-react';
import {
  useTrackingSettings,
  useUpdateTrackingSettings,
  useTrackingEvents,
  useTrackingAttribution,
  useTrackingDiagnostics,
  useConnectGA4,
  useDisconnectGA4,
  useGA4Properties,
  useGA4Report,
} from '../../hooks/useAnalytics';

export const AnalyticsTrackingSdkGa4View: React.FC = () => {
  const [activeSubTab, setActiveSubTab] = useState<'ga4' | 'sdk_settings' | 'diagnostics' | 'event_stream'>('ga4');

  // GA4 Connection Modal State
  const [showConnectModal, setShowConnectModal] = useState(false);
  const [propertyId, setPropertyId] = useState('');
  const [measurementId, setMeasurementId] = useState('');
  const [apiSecret, setApiSecret] = useState('');
  const [displayName, setDisplayName] = useState('');
  const [copiedKey, setCopiedKey] = useState(false);
  const [copiedScript, setCopiedScript] = useState(false);

  // Queries
  const { data: settings, isLoading: settingsLoading } = useTrackingSettings();
  const { data: diagnostics, isLoading: diagLoading, refetch: refetchDiag } = useTrackingDiagnostics();
  const { data: ga4Props } = useGA4Properties();
  const { data: ga4Report, isLoading: reportLoading, refetch: refetchReport } = useGA4Report();
  const { data: eventsData, isLoading: eventsLoading, refetch: refetchEvents } = useTrackingEvents({ per_page: 20 });
  const { data: attributionData } = useTrackingAttribution();

  // Mutations
  const updateSettingsMutation = useUpdateTrackingSettings();
  const connectGa4Mutation = useConnectGA4();
  const disconnectGa4Mutation = useDisconnectGA4();

  const trackingKey = settings?.tracking_key || 'trk_loading...';
  const apiBase = window.location.origin;
  const scriptTag = `<script src="${apiBase}/api/v1/tracking/script/${trackingKey}.js" async></script>`;

  const copyToClipboard = (text: string, type: 'key' | 'script') => {
    navigator.clipboard.writeText(text);
    if (type === 'key') {
      setCopiedKey(true);
      setTimeout(() => setCopiedKey(false), 2000);
    } else {
      setCopiedScript(true);
      setTimeout(() => setCopiedScript(false), 2000);
    }
  };

  const handleConnectGa4 = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!propertyId.trim()) return;

    await connectGa4Mutation.mutateAsync({
      property_id: propertyId.trim(),
      measurement_id: measurementId.trim() || undefined,
      api_secret: apiSecret.trim() || undefined,
      display_name: displayName.trim() || undefined,
    });

    setShowConnectModal(false);
    setPropertyId('');
    setMeasurementId('');
    setApiSecret('');
  };

  const handleDisconnectGa4 = async () => {
    if (confirm('Are you sure you want to disconnect this Google Analytics 4 property?')) {
      await disconnectGa4Mutation.mutateAsync();
    }
  };

  const handleToggleSetting = async (key: string, currentValue: boolean) => {
    await updateSettingsMutation.mutateAsync({ [key]: !currentValue });
  };

  const handleRetentionChange = async (days: number) => {
    await updateSettingsMutation.mutateAsync({ data_retention_days: days });
  };

  return (
    <div className="space-y-6" data-testid="analytics-tracking-sdk-ga4-view">
      {/* Sub Tabs Navigation */}
      <div className="flex border-b border-slate-200 dark:border-slate-800 space-x-2 sm:space-x-4 overflow-x-auto">
        <button
          onClick={() => setActiveSubTab('ga4')}
          className={`flex items-center gap-2 py-2.5 px-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${
            activeSubTab === 'ga4'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-semibold'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
          }`}
        >
          <Globe className="w-4 h-4 text-amber-500" />
          Google Analytics 4 (GA4)
        </button>

        <button
          onClick={() => setActiveSubTab('sdk_settings')}
          className={`flex items-center gap-2 py-2.5 px-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${
            activeSubTab === 'sdk_settings'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-semibold'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
          }`}
        >
          <Code className="w-4 h-4 text-emerald-500" />
          First-Party Tracking SDK
        </button>

        <button
          onClick={() => setActiveSubTab('diagnostics')}
          className={`flex items-center gap-2 py-2.5 px-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${
            activeSubTab === 'diagnostics'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-semibold'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
          }`}
        >
          <Radio className="w-4 h-4 text-sky-500" />
          Real-Time Diagnostics
        </button>

        <button
          onClick={() => setActiveSubTab('event_stream')}
          className={`flex items-center gap-2 py-2.5 px-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${
            activeSubTab === 'event_stream'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-semibold'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
          }`}
        >
          <Activity className="w-4 h-4 text-purple-500" />
          Event Stream & Attribution
        </button>
      </div>

      {/* ========================================================================= */}
      {/* 1. GOOGLE ANALYTICS 4 (GA4) SUBTAB */}
      {/* ========================================================================= */}
      {activeSubTab === 'ga4' && (
        <div className="space-y-6">
          {/* Header Card */}
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 sm:p-6 shadow-sm">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
              <div className="flex items-start gap-3.5">
                <div className="p-3 bg-amber-500/10 text-amber-600 dark:text-amber-400 rounded-xl border border-amber-500/20">
                  <Globe className="w-6 h-6" />
                </div>
                <div>
                  <div className="flex items-center gap-2.5">
                    <h2 className="text-lg font-bold text-slate-900 dark:text-white">Google Analytics 4 Property</h2>
                    {settings?.ga4_connected_at ? (
                      <span className="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                        <CheckCircle2 className="w-3.5 h-3.5" />
                        Connected
                      </span>
                    ) : (
                      <span className="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                        Not Connected
                      </span>
                    )}
                  </div>
                  <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Connect your official GA4 Property to stream marketing attribution, sessions, and traffic trends.
                  </p>
                </div>
              </div>

              <div className="flex items-center gap-2.5">
                {settings?.ga4_connected_at ? (
                  <>
                    <button
                      onClick={() => refetchReport()}
                      className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition"
                    >
                      <RefreshCw className="w-3.5 h-3.5" />
                      Refresh Data
                    </button>
                    <button
                      onClick={handleDisconnectGa4}
                      className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium rounded-lg border border-rose-200 dark:border-rose-900/50 bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400 hover:bg-rose-100 transition"
                    >
                      Disconnect
                    </button>
                  </>
                ) : (
                  <button
                    onClick={() => setShowConnectModal(true)}
                    className="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition shadow-sm"
                  >
                    <Link className="w-3.5 h-3.5" />
                    Connect GA4 Property
                  </button>
                )}
              </div>
            </div>

            {/* Privacy Notice Banner */}
            <div className="mt-4 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 flex items-start gap-2.5 text-xs text-slate-600 dark:text-slate-300">
              <ShieldCheck className="w-4 h-4 text-indigo-500 shrink-0 mt-0.5" />
              <span>
                <strong>Privacy-Conscious Integration:</strong> Google Analytics 4 is strictly an aggregated attribution and traffic analytics source. Per Google Analytics Data API policies, individual visitor identities, private phone numbers, and individual viewer records are never requested, stored, or extracted.
              </span>
            </div>
          </div>

          {/* GA4 Report Dashboard */}
          {settings?.ga4_connected_at && ga4Report && (
            <div className="space-y-6">
              {/* Metric KPI Cards */}
              <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5">
                <div className="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                  <span className="text-[11px] font-medium text-slate-500 dark:text-slate-400">Total Sessions</span>
                  <div className="text-xl font-bold text-slate-900 dark:text-white mt-1">
                    {ga4Report.summary.sessions.toLocaleString()}
                  </div>
                  <span className="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold">GA4 Verified</span>
                </div>

                <div className="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                  <span className="text-[11px] font-medium text-slate-500 dark:text-slate-400">Total Users</span>
                  <div className="text-xl font-bold text-slate-900 dark:text-white mt-1">
                    {ga4Report.summary.users.toLocaleString()}
                  </div>
                  <span className="text-[10px] text-slate-400">Unique Visitors</span>
                </div>

                <div className="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                  <span className="text-[11px] font-medium text-slate-500 dark:text-slate-400">Page Views</span>
                  <div className="text-xl font-bold text-slate-900 dark:text-white mt-1">
                    {ga4Report.summary.page_views.toLocaleString()}
                  </div>
                  <span className="text-[10px] text-slate-400">Screen Views</span>
                </div>

                <div className="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                  <span className="text-[11px] font-medium text-slate-500 dark:text-slate-400">Conversions</span>
                  <div className="text-xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                    {ga4Report.summary.conversions.toLocaleString()}
                  </div>
                  <span className="text-[10px] text-indigo-500 font-medium">Form & Purchase Goals</span>
                </div>

                <div className="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                  <span className="text-[11px] font-medium text-slate-500 dark:text-slate-400">Bounce Rate</span>
                  <div className="text-xl font-bold text-slate-900 dark:text-white mt-1">
                    {ga4Report.summary.bounce_rate}%
                  </div>
                  <span className="text-[10px] text-slate-400">Engaged Sessions</span>
                </div>

                <div className="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                  <span className="text-[11px] font-medium text-slate-500 dark:text-slate-400">Avg Duration</span>
                  <div className="text-xl font-bold text-slate-900 dark:text-white mt-1">
                    {ga4Report.summary.avg_session_duration}
                  </div>
                  <span className="text-[10px] text-slate-400">Per Session</span>
                </div>
              </div>

              {/* Two Column Tables: Traffic Sources & Campaigns */}
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Traffic Source / Medium */}
                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5">
                  <h3 className="text-sm font-bold text-slate-900 dark:text-white mb-3 flex items-center justify-between">
                    <span>Traffic Source / Medium</span>
                    <span className="text-xs font-normal text-slate-400">GA4 Attribution</span>
                  </h3>
                  <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                      <thead>
                        <tr className="border-b border-slate-200 dark:border-slate-800 text-slate-400">
                          <th className="pb-2 font-medium">Source / Medium</th>
                          <th className="pb-2 font-medium text-right">Sessions</th>
                          <th className="pb-2 font-medium text-right">Users</th>
                          <th className="pb-2 font-medium text-right">Conversions</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                        {ga4Report.traffic_sources.map((ts, idx) => (
                          <tr key={idx} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                            <td className="py-2.5 font-medium text-slate-800 dark:text-slate-200">{ts.source_medium}</td>
                            <td className="py-2.5 text-right text-slate-600 dark:text-slate-400">{ts.sessions.toLocaleString()}</td>
                            <td className="py-2.5 text-right text-slate-600 dark:text-slate-400">{ts.users.toLocaleString()}</td>
                            <td className="py-2.5 text-right font-semibold text-emerald-600 dark:text-emerald-400">{ts.conversions.toLocaleString()}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>

                {/* Top Campaigns */}
                <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5">
                  <h3 className="text-sm font-bold text-slate-900 dark:text-white mb-3 flex items-center justify-between">
                    <span>Campaign Performance</span>
                    <span className="text-xs font-normal text-slate-400">UTM & Paid Campaigns</span>
                  </h3>
                  <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                      <thead>
                        <tr className="border-b border-slate-200 dark:border-slate-800 text-slate-400">
                          <th className="pb-2 font-medium">Campaign Name</th>
                          <th className="pb-2 font-medium text-right">Sessions</th>
                          <th className="pb-2 font-medium text-right">Conversions</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                        {ga4Report.campaigns.map((c, idx) => (
                          <tr key={idx} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                            <td className="py-2.5 font-mono text-slate-800 dark:text-slate-200">{c.campaign}</td>
                            <td className="py-2.5 text-right text-slate-600 dark:text-slate-400">{c.sessions.toLocaleString()}</td>
                            <td className="py-2.5 text-right font-semibold text-indigo-600 dark:text-indigo-400">{c.conversions.toLocaleString()}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              {/* Landing Page Performance */}
              <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5">
                <h3 className="text-sm font-bold text-slate-900 dark:text-white mb-3">Landing Page Performance</h3>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                  {ga4Report.landing_pages.map((lp, idx) => (
                    <div key={idx} className="p-3.5 rounded-lg border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40">
                      <div className="font-mono text-xs font-semibold text-slate-800 dark:text-slate-200 truncate" title={lp.page_path}>
                        {lp.page_path}
                      </div>
                      <div className="flex items-center justify-between text-xs mt-2 text-slate-500 dark:text-slate-400">
                        <span>Sessions: <strong>{lp.sessions.toLocaleString()}</strong></span>
                        <span>Bounce: <strong>{lp.bounce_rate}</strong></span>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}

          {/* Connect Modal */}
          {showConnectModal && (
            <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
              <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-md w-full shadow-2xl space-y-4">
                <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                  <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <Globe className="w-5 h-5 text-amber-500" />
                    Connect Google Analytics 4
                  </h3>
                  <button onClick={() => setShowConnectModal(false)} className="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <form onSubmit={handleConnectGa4} className="space-y-3.5 text-xs">
                  <div>
                    <label className="block font-medium text-slate-700 dark:text-slate-300 mb-1">
                      GA4 Property ID <span className="text-rose-500">*</span>
                    </label>
                    <input
                      type="text"
                      required
                      placeholder="e.g. 123456789 or properties/123456789"
                      value={propertyId}
                      onChange={(e) => setPropertyId(e.target.value)}
                      className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-mono"
                    />
                    <span className="text-[10px] text-slate-500">Found in GA4 Admin → Property Settings</span>
                  </div>

                  <div>
                    <label className="block font-medium text-slate-700 dark:text-slate-300 mb-1">
                      Measurement ID (Optional)
                    </label>
                    <input
                      type="text"
                      placeholder="e.g. G-XXXXXXXXXX"
                      value={measurementId}
                      onChange={(e) => setMeasurementId(e.target.value)}
                      className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-mono"
                    />
                  </div>

                  <div>
                    <label className="block font-medium text-slate-700 dark:text-slate-300 mb-1">
                      Measurement Protocol API Secret (Optional)
                    </label>
                    <input
                      type="password"
                      placeholder="Enter API secret for offline conversion streaming"
                      value={apiSecret}
                      onChange={(e) => setApiSecret(e.target.value)}
                      className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                    />
                  </div>

                  <div>
                    <label className="block font-medium text-slate-700 dark:text-slate-300 mb-1">
                      Display Name
                    </label>
                    <input
                      type="text"
                      placeholder="e.g. Production Corporate Website"
                      value={displayName}
                      onChange={(e) => setDisplayName(e.target.value)}
                      className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                    />
                  </div>

                  <div className="flex justify-end gap-2 pt-2">
                    <button
                      type="button"
                      onClick={() => setShowConnectModal(false)}
                      className="px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      disabled={connectGa4Mutation.isPending}
                      className="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-700 disabled:opacity-50"
                    >
                      {connectGa4Mutation.isPending ? 'Connecting...' : 'Authorize & Connect'}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}
        </div>
      )}

      {/* ========================================================================= */}
      {/* 2. FIRST-PARTY TRACKING SDK & SETTINGS SUBTAB */}
      {/* ========================================================================= */}
      {activeSubTab === 'sdk_settings' && (
        <div className="space-y-6">
          {/* Tracking Snippet Embed Card */}
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 sm:p-6 shadow-sm">
            <h2 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
              <Code className="w-5 h-5 text-emerald-500" />
              Embed Lightweight Tracking Script
            </h2>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
              Add this lightweight script (<span className="font-semibold text-emerald-600">under 3KB, zero cookies, zero invasive fingerprinting</span>) to your website or landing pages before the closing <code>&lt;/head&gt;</code> tag.
            </p>

            <div className="mt-4 relative bg-slate-900 rounded-xl p-4 text-xs font-mono text-emerald-400 overflow-x-auto border border-slate-800">
              <code>{scriptTag}</code>
              <button
                onClick={() => copyToClipboard(scriptTag, 'script')}
                className="absolute top-3 right-3 px-2.5 py-1 text-[11px] font-sans font-medium rounded-md bg-slate-800 text-slate-200 hover:bg-slate-700 border border-slate-700 flex items-center gap-1.5 transition"
              >
                {copiedScript ? <CheckCircle2 className="w-3.5 h-3.5 text-emerald-400" /> : <Copy className="w-3.5 h-3.5" />}
                {copiedScript ? 'Copied!' : 'Copy Snippet'}
              </button>
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-4 text-xs text-slate-600 dark:text-slate-300">
              <div className="flex items-center gap-1.5 font-mono">
                <span className="text-slate-400">Tracking Key:</span>
                <span className="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-bold">{trackingKey}</span>
                <button
                  onClick={() => copyToClipboard(trackingKey, 'key')}
                  className="text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-0.5 ml-1"
                >
                  <Copy className="w-3 h-3" />
                  {copiedKey ? 'Copied' : 'Copy Key'}
                </button>
              </div>
            </div>
          </div>

          {/* Privacy & Configuration Toggles */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Privacy & Compliance Settings */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm space-y-4">
              <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <ShieldCheck className="w-4 h-4 text-indigo-500" />
                Privacy & Compliance Controls
              </h3>

              <div className="space-y-3 text-xs">
                {/* Require Consent Toggle */}
                <div className="flex items-center justify-between p-3 rounded-lg border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40">
                  <div>
                    <div className="font-semibold text-slate-800 dark:text-slate-200">GDPR / ePrivacy Consent Mode</div>
                    <div className="text-slate-500 text-[11px]">Require explicit visitor consent before recording analytical events</div>
                  </div>
                  <button
                    onClick={() => handleToggleSetting('require_consent', settings?.require_consent ?? false)}
                    className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out ${
                      settings?.require_consent ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-700'
                    }`}
                  >
                    <span className={`pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                      settings?.require_consent ? 'translate-x-4' : 'translate-x-0'
                    }`} />
                  </button>
                </div>

                {/* Anonymize IP Toggle */}
                <div className="flex items-center justify-between p-3 rounded-lg border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40">
                  <div>
                    <div className="font-semibold text-slate-800 dark:text-slate-200">IP Address Anonymization</div>
                    <div className="text-slate-500 text-[11px]">Mask the last octet (e.g. 192.168.1.0) to eliminate raw IP storage</div>
                  </div>
                  <button
                    onClick={() => handleToggleSetting('anonymize_ip', settings?.anonymize_ip ?? true)}
                    className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out ${
                      settings?.anonymize_ip ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-700'
                    }`}
                  >
                    <span className={`pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                      settings?.anonymize_ip ? 'translate-x-4' : 'translate-x-0'
                    }`} />
                  </button>
                </div>

                {/* Bot Filtering Toggle */}
                <div className="flex items-center justify-between p-3 rounded-lg border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40">
                  <div>
                    <div className="font-semibold text-slate-800 dark:text-slate-200">Bot & Spam Crawler Filtering</div>
                    <div className="text-slate-500 text-[11px]">Automatically discard automated web scrapers, spiders, and headless crawlers</div>
                  </div>
                  <button
                    onClick={() => handleToggleSetting('bot_filtering', settings?.bot_filtering ?? true)}
                    className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out ${
                      settings?.bot_filtering ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-700'
                    }`}
                  >
                    <span className={`pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                      settings?.bot_filtering ? 'translate-x-4' : 'translate-x-0'
                    }`} />
                  </button>
                </div>
              </div>
            </div>

            {/* Retention & Operational Rules */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm space-y-4">
              <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <Clock className="w-4 h-4 text-amber-500" />
                Data Retention & Event Rules
              </h3>

              <div className="space-y-4 text-xs">
                <div>
                  <label className="block font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                    Data Retention Policy
                  </label>
                  <select
                    value={settings?.data_retention_days ?? 90}
                    onChange={(e) => handleRetentionChange(Number(e.target.value))}
                    className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                  >
                    <option value={30}>30 Days (Strict Compliance)</option>
                    <option value={60}>60 Days</option>
                    <option value={90}>90 Days (Recommended)</option>
                    <option value={180}>180 Days (Half Year)</option>
                    <option value={365}>365 Days (1 Year)</option>
                  </select>
                  <p className="text-[11px] text-slate-500 mt-1">Events older than this period are automatically pruned.</p>
                </div>

                <div>
                  <label className="block font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                    Allowed Domains Whitelist
                  </label>
                  <input
                    type="text"
                    disabled
                    value={(settings?.allowed_domains || ['*']).join(', ')}
                    className="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-500 font-mono text-xs"
                  />
                  <p className="text-[11px] text-slate-500 mt-1"><code>*</code> permits tracking on all verified custom landing pages and tenant websites.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* 3. REAL-TIME TRACKING DIAGNOSTICS SUBTAB */}
      {/* ========================================================================= */}
      {activeSubTab === 'diagnostics' && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div className="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
              <span className="text-[11px] font-medium text-slate-500 dark:text-slate-400">Events in Past 24h</span>
              <div className="text-2xl font-bold text-slate-900 dark:text-white mt-1">
                {(diagnostics?.events_past_24h ?? 0).toLocaleString()}
              </div>
              <span className="text-[10px] text-emerald-600 font-medium">Ingested via SDK</span>
            </div>

            <div className="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
              <span className="text-[11px] font-medium text-slate-500 dark:text-slate-400">Active Sessions (30m)</span>
              <div className="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                {(diagnostics?.active_sessions_now ?? 0).toLocaleString()}
              </div>
              <span className="text-[10px] text-slate-400">Concurrent Visitors</span>
            </div>

            <div className="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
              <span className="text-[11px] font-medium text-slate-500 dark:text-slate-400">Last Event Received</span>
              <div className="text-sm font-semibold text-slate-800 dark:text-slate-200 mt-1 truncate">
                {diagnostics?.last_event_at ? new Date(diagnostics.last_event_at).toLocaleTimeString() : 'None'}
              </div>
              <span className="text-[10px] font-mono text-slate-500">{diagnostics?.last_event_type || 'No recent activity'}</span>
            </div>

            <div className="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
              <span className="text-[11px] font-medium text-slate-500 dark:text-slate-400">SDK Status</span>
              <div className="flex items-center gap-1.5 mt-1">
                <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse" />
                <span className="text-sm font-bold text-slate-900 dark:text-white">Active & Healthy</span>
              </div>
              <span className="text-[10px] text-slate-400">Deduplication: Enabled</span>
            </div>
          </div>

          {/* Diagnostics Tables */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Top UTM Sources */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5">
              <h3 className="text-sm font-bold text-slate-900 dark:text-white mb-3">Top Ingested UTM Sources</h3>
              <div className="space-y-2.5 text-xs">
                {(diagnostics?.top_utm_sources || []).length > 0 ? (
                  diagnostics!.top_utm_sources.map((item, idx) => (
                    <div key={idx} className="flex items-center justify-between p-2 rounded bg-slate-50 dark:bg-slate-800/40">
                      <span className="font-mono text-slate-700 dark:text-slate-300">{item.utm_source}</span>
                      <span className="font-bold text-indigo-600 dark:text-indigo-400">{item.count} events</span>
                    </div>
                  ))
                ) : (
                  <p className="text-slate-400 text-center py-4">No UTM events recorded yet.</p>
                )}
              </div>
            </div>

            {/* Events by Type */}
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5">
              <h3 className="text-sm font-bold text-slate-900 dark:text-white mb-3">Event Volume by Type</h3>
              <div className="space-y-2 text-xs">
                {Object.entries(diagnostics?.events_by_type || {}).map(([type, count]) => (
                  <div key={type} className="flex items-center justify-between p-2 rounded bg-slate-50 dark:bg-slate-800/40">
                    <span className="font-medium text-slate-700 dark:text-slate-300 capitalize">{type.replace('_', ' ')}</span>
                    <span className="font-bold text-slate-900 dark:text-white">{count}</span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* 4. EVENT STREAM & MULTI-TOUCH JOURNEYS SUBTAB */}
      {/* ========================================================================= */}
      {activeSubTab === 'event_stream' && (
        <div className="space-y-6">
          {/* Multi-Touch Lead Attribution Journeys */}
          {attributionData?.lead_attribution_journeys && attributionData.lead_attribution_journeys.length > 0 && (
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm">
              <h3 className="text-sm font-bold text-slate-900 dark:text-white mb-3 flex items-center justify-between">
                <span>Multi-Touch Lead Attribution Journeys</span>
                <span className="text-xs font-normal text-slate-400">First-Touch to Conversion Linking</span>
              </h3>
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs">
                  <thead>
                    <tr className="border-b border-slate-200 dark:border-slate-800 text-slate-400">
                      <th className="pb-2 font-medium">Lead Name</th>
                      <th className="pb-2 font-medium">First Touch (Source / Campaign)</th>
                      <th className="pb-2 font-medium">Last Touch (Conversion)</th>
                      <th className="pb-2 font-medium text-center">Touches</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                    {attributionData.lead_attribution_journeys.map((lead: any, idx: number) => (
                      <tr key={idx} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                        <td className="py-2.5 font-semibold text-slate-900 dark:text-white">
                          {lead.lead_name}
                          <div className="text-[10px] text-slate-400">{lead.lead_email}</div>
                        </td>
                        <td className="py-2.5">
                          <span className="px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 font-mono text-[10px]">
                            {lead.first_touch.source} {lead.first_touch.campaign ? `(${lead.first_touch.campaign})` : ''}
                          </span>
                        </td>
                        <td className="py-2.5">
                          <span className="px-1.5 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 font-mono text-[10px]">
                            {lead.last_touch.source}
                          </span>
                        </td>
                        <td className="py-2.5 text-center font-bold text-slate-700 dark:text-slate-300">
                          {lead.total_touches}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* Raw Event Stream Table */}
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm">
            <div className="flex items-center justify-between mb-3">
              <h3 className="text-sm font-bold text-slate-900 dark:text-white">Recent Ingested Events Stream</h3>
              <button
                onClick={() => refetchEvents()}
                className="text-xs text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
              >
                <RefreshCw className="w-3 h-3" /> Refresh
              </button>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead>
                  <tr className="border-b border-slate-200 dark:border-slate-800 text-slate-400">
                    <th className="pb-2 font-medium">Timestamp</th>
                    <th className="pb-2 font-medium">Event</th>
                    <th className="pb-2 font-medium">Session ID</th>
                    <th className="pb-2 font-medium">Page URL</th>
                    <th className="pb-2 font-medium">UTM Source</th>
                    <th className="pb-2 font-medium">Linked Lead</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60 font-mono">
                  {(eventsData?.data || []).length > 0 ? (
                    eventsData.data.map((evt: any) => (
                      <tr key={evt.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                        <td className="py-2 text-slate-400">{new Date(evt.created_at).toLocaleTimeString()}</td>
                        <td className="py-2">
                          <span className={`px-1.5 py-0.5 rounded text-[10px] font-sans font-semibold ${
                            evt.event_name === 'form_submission'
                              ? 'bg-emerald-500/10 text-emerald-600'
                              : evt.event_name === 'cta_click'
                              ? 'bg-amber-500/10 text-amber-600'
                              : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300'
                          }`}>
                            {evt.event_name}
                          </span>
                        </td>
                        <td className="py-2 text-slate-500 text-[10px] truncate max-w-[120px]">{evt.session_id}</td>
                        <td className="py-2 text-slate-700 dark:text-slate-300 font-sans truncate max-w-[200px]" title={evt.page_url}>
                          {evt.page_url || '-'}
                        </td>
                        <td className="py-2 text-indigo-600 dark:text-indigo-400">{evt.utm_source || '-'}</td>
                        <td className="py-2 font-sans font-medium text-slate-800 dark:text-slate-200">
                          {evt.lead ? (
                            <span className="text-emerald-600 dark:text-emerald-400 font-semibold">{evt.lead.name}</span>
                          ) : (
                            <span className="text-slate-400 text-[11px]">Anonymous</span>
                          )}
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={6} className="text-center py-6 text-slate-400 font-sans">
                        No events recorded in this workspace yet. Embed the tracking script to begin streaming events.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
