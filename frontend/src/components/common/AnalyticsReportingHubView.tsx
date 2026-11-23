import React, { useState } from 'react';
import {
  BarChart3,
  TrendingUp,
  TrendingDown,
  DollarSign,
  Users,
  Target,
  Share2,
  Download,
  Calendar,
  Filter,
  Layers,
  FileSpreadsheet,
  CheckCircle2,
  AlertTriangle,
  Clock,
  Sparkles,
  Search,
  Plus,
  Trash2,
  Info,
  ArrowRight,
  PieChart,
  Activity,
  Bot,
  PhoneCall,
  Briefcase,
  HelpCircle,
  RefreshCw,
  Globe,
} from 'lucide-react';
import { AnalyticsTrackingSdkGa4View } from './AnalyticsTrackingSdkGa4View';
import {
  useExecutiveAnalytics,
  useLeadAnalytics,
  useSalesAnalytics,
  useAttributionAnalytics,
  useSavedReports,
  useCreateSavedReport,
  useDeleteSavedReport,
  useRecordTouchpoint,
} from '../../hooks/useAnalytics';
import { api } from '../../api/client';

export const AnalyticsReportingHubView: React.FC = () => {
  const [activeTab, setActiveTab] = useState<'executive' | 'leads' | 'sales' | 'attribution' | 'reports' | 'tracking_ga4'>('executive');
  
  // Date range filters
  const [dateRangePreset, setDateRangePreset] = useState<'7d' | '30d' | '90d' | 'custom'>('30d');
  const [fromDate, setFromDate] = useState<string>(() => {
    const d = new Date();
    d.setDate(d.getDate() - 30);
    return d.toISOString().split('T')[0];
  });
  const [toDate, setToDate] = useState<string>(() => new Date().toISOString().split('T')[0]);

  const handlePresetChange = (preset: '7d' | '30d' | '90d') => {
    setDateRangePreset(preset);
    const d = new Date();
    const days = preset === '7d' ? 7 : preset === '30d' ? 30 : 90;
    d.setDate(d.getDate() - days);
    setFromDate(d.toISOString().split('T')[0]);
    setToDate(new Date().toISOString().split('T')[0]);
  };

  const filterParams = { from_date: fromDate, to_date: toDate };

  // Queries
  const { data: execData, isLoading: execLoading, refetch: refetchExec } = useExecutiveAnalytics(filterParams);
  const { data: leadData, isLoading: leadLoading, refetch: refetchLead } = useLeadAnalytics(filterParams);
  const { data: salesData, isLoading: salesLoading, refetch: refetchSales } = useSalesAnalytics(filterParams);
  const { data: attrData, isLoading: attrLoading, refetch: refetchAttr } = useAttributionAnalytics(filterParams);
  const { data: savedReports, isLoading: reportsLoading, refetch: refetchReports } = useSavedReports();

  // Mutations
  const createReportMutation = useCreateSavedReport();
  const deleteReportMutation = useDeleteSavedReport();
  const recordTouchpointMutation = useRecordTouchpoint();

  // Modal states
  const [showSaveModal, setShowSaveModal] = useState(false);
  const [newReportTitle, setNewReportTitle] = useState('');
  const [newReportType, setNewReportType] = useState('executive_summary');
  const [newReportDesc, setNewReportDesc] = useState('');
  const [isScheduled, setIsScheduled] = useState(false);
  const [scheduleFreq, setScheduleFreq] = useState<'daily' | 'weekly' | 'monthly'>('weekly');

  const [showTouchpointModal, setShowTouchpointModal] = useState(false);
  const [touchpointChannel, setTouchpointChannel] = useState('google_ads');
  const [touchpointUtmSource, setTouchpointUtmSource] = useState('google');
  const [touchpointUtmCampaign, setTouchpointUtmCampaign] = useState('q4_performance_max');
  const [touchpointType, setTouchpointType] = useState('ad_click');

  // Export State
  const [exportType, setExportType] = useState('leads');
  const [isExporting, setIsExporting] = useState(false);
  const [exportStatus, setExportStatus] = useState<string | null>(null);

  const handleSaveReport = async () => {
    if (!newReportTitle.trim()) return;
    await createReportMutation.mutateAsync({
      title: newReportTitle,
      report_type: newReportType,
      description: newReportDesc,
      filters: filterParams,
      is_scheduled: isScheduled,
      schedule_frequency: isScheduled ? scheduleFreq : undefined,
    });
    setNewReportTitle('');
    setNewReportDesc('');
    setShowSaveModal(false);
  };

  const handleRecordTouchpoint = async () => {
    await recordTouchpointMutation.mutateAsync({
      channel: touchpointChannel,
      utm_source: touchpointUtmSource,
      utm_campaign: touchpointUtmCampaign,
      touchpoint_type: touchpointType,
    });
    setShowTouchpointModal(false);
  };

  const handleExportCsv = async (type: string) => {
    try {
      setIsExporting(true);
      setExportStatus('Generating CSV...');
      const res = await api.exportAnalyticsReport({
        report_type: type,
        from_date: fromDate,
        to_date: toDate,
      });

      if (res.data?.csv_content) {
        const blob = new Blob([res.data.csv_content], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', res.data.filename || `export_${type}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setExportStatus('Export downloaded successfully!');
      } else {
        setExportStatus('Export generated successfully!');
      }
      setTimeout(() => setExportStatus(null), 3000);
    } catch (err: any) {
      setExportStatus(`Export failed: ${err.message || 'Unknown error'}`);
      setTimeout(() => setExportStatus(null), 4000);
    } finally {
      setIsExporting(false);
    }
  };

  const formatCurrency = (val?: number) => {
    if (val === undefined || val === null) return '$0.00';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val);
  };

  const renderTrendBadge = (trendKey: string) => {
    if (!execData?.trends || !execData.trends[trendKey]) return null;
    const trend = execData.trends[trendKey];
    const isPositive = trend.change >= 0;
    return (
      <span
        className={`inline-flex items-center gap-0.5 text-xs font-semibold px-1.5 py-0.5 rounded ${
          isPositive ? 'text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 dark:text-emerald-300' : 'text-rose-700 bg-rose-50 dark:bg-rose-950/40 dark:text-rose-300'
        }`}
        title={`Previous period: ${trend.previous}`}
      >
        {isPositive ? <TrendingUp className="w-3 h-3" /> : <TrendingDown className="w-3 h-3" />}
        {isPositive ? '+' : ''}{trend.change_percent}%
      </span>
    );
  };

  return (
    <div className="space-y-6 max-w-7xl mx-auto p-4 sm:p-6" data-testid="analytics-reporting-hub">
      {/* Top Header & Range Filters */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-5">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
            <BarChart3 className="w-7 h-7 text-indigo-600 dark:text-indigo-400" />
            Centralized Analytics, Attribution & Intelligence
          </h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Real multi-touch attribution, executive KPIs, sales forecasting, and cross-channel funnel metrics
          </p>
        </div>

        {/* Date Filter & Actions */}
        <div className="flex flex-wrap items-center gap-2.5">
          <div className="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-0.5">
            <button
              onClick={() => handlePresetChange('7d')}
              className={`px-3 py-1.5 text-xs font-medium rounded-md transition-colors ${
                dateRangePreset === '7d'
                  ? 'bg-indigo-600 text-white'
                  : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'
              }`}
            >
              7 Days
            </button>
            <button
              onClick={() => handlePresetChange('30d')}
              className={`px-3 py-1.5 text-xs font-medium rounded-md transition-colors ${
                dateRangePreset === '30d'
                  ? 'bg-indigo-600 text-white'
                  : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'
              }`}
            >
              30 Days
            </button>
            <button
              onClick={() => handlePresetChange('90d')}
              className={`px-3 py-1.5 text-xs font-medium rounded-md transition-colors ${
                dateRangePreset === '90d'
                  ? 'bg-indigo-600 text-white'
                  : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'
              }`}
            >
              90 Days
            </button>
          </div>

          <div className="flex items-center gap-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-slate-600 dark:text-slate-300">
            <Calendar className="w-3.5 h-3.5 text-slate-400" />
            <input
              type="date"
              value={fromDate}
              onChange={(e) => {
                setFromDate(e.target.value);
                setDateRangePreset('custom');
              }}
              className="bg-transparent border-none text-xs focus:outline-none dark:text-slate-200"
            />
            <span className="text-slate-400">to</span>
            <input
              type="date"
              value={toDate}
              onChange={(e) => {
                setToDate(e.target.value);
                setDateRangePreset('custom');
              }}
              className="bg-transparent border-none text-xs focus:outline-none dark:text-slate-200"
            />
          </div>

          <button
            onClick={() => {
              refetchExec();
              refetchLead();
              refetchSales();
              refetchAttr();
            }}
            className="p-2 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition-colors"
            title="Refresh All Analytics"
          >
            <RefreshCw className="w-4 h-4" />
          </button>
        </div>
      </div>

      {/* Tabs Navigation */}
      <div className="flex border-b border-slate-200 dark:border-slate-800 space-x-1 sm:space-x-4 overflow-x-auto">
        <button
          onClick={() => setActiveTab('executive')}
          className={`flex items-center gap-2 py-3 px-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${
            activeTab === 'executive'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
          }`}
        >
          <BarChart3 className="w-4 h-4" />
          Executive KPI Dashboard
        </button>

        <button
          onClick={() => setActiveTab('leads')}
          className={`flex items-center gap-2 py-3 px-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${
            activeTab === 'leads'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
          }`}
        >
          <Users className="w-4 h-4" />
          Lead & Funnel Analytics
        </button>

        <button
          onClick={() => setActiveTab('sales')}
          className={`flex items-center gap-2 py-3 px-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${
            activeTab === 'sales'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
          }`}
        >
          <DollarSign className="w-4 h-4" />
          Sales Pipeline & Forecasting
        </button>

        <button
          onClick={() => setActiveTab('attribution')}
          className={`flex items-center gap-2 py-3 px-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${
            activeTab === 'attribution'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
          }`}
        >
          <Share2 className="w-4 h-4" />
          Attribution Engine
        </button>

        <button
          onClick={() => setActiveTab('reports')}
          className={`flex items-center gap-2 py-3 px-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${
            activeTab === 'reports'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
          }`}
        >
          <FileSpreadsheet className="w-4 h-4" />
          Saved Reports & Exports
        </button>

        <button
          onClick={() => setActiveTab('tracking_ga4')}
          className={`flex items-center gap-2 py-3 px-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${
            activeTab === 'tracking_ga4'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-semibold'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
          }`}
        >
          <Globe className="w-4 h-4 text-amber-500" />
          Website Tracking SDK & GA4
        </button>
      </div>

      {/* TAB 1: EXECUTIVE KPI DASHBOARD */}
      {activeTab === 'executive' && (
        <div className="space-y-6">
          {execLoading ? (
            <div className="py-16 text-center text-slate-500">
              <RefreshCw className="w-8 h-8 animate-spin mx-auto mb-2 text-indigo-600" />
              Loading real executive metrics...
            </div>
          ) : execData ? (
            <>
              {/* Primary Financial & Pipeline KPIs */}
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                  <div className="flex items-center justify-between text-xs text-slate-500 font-medium">
                    <span>Won Revenue</span>
                    {renderTrendBadge('won_deal_value')}
                  </div>
                  <div className="mt-2 text-2xl font-bold text-slate-900 dark:text-white">
                    {formatCurrency(execData.kpis.won_deal_value)}
                  </div>
                  <div className="mt-1 text-xs text-slate-500">
                    {execData.kpis.won_deals_count} won deals ({execData.kpis.win_rate}% win rate)
                  </div>
                </div>

                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                  <div className="flex items-center justify-between text-xs text-slate-500 font-medium">
                    <span>Active Pipeline Value</span>
                    {renderTrendBadge('pipeline_value')}
                  </div>
                  <div className="mt-2 text-2xl font-bold text-slate-900 dark:text-white">
                    {formatCurrency(execData.kpis.pipeline_value)}
                  </div>
                  <div className="mt-1 text-xs text-slate-500">
                    {execData.kpis.open_deals} open opportunities
                  </div>
                </div>

                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                  <div className="flex items-center justify-between text-xs text-slate-500 font-medium">
                    <span>Total Marketing Spend</span>
                    {renderTrendBadge('total_marketing_spend')}
                  </div>
                  <div className="mt-2 text-2xl font-bold text-slate-900 dark:text-white">
                    {formatCurrency(execData.kpis.total_marketing_spend)}
                  </div>
                  <div className="mt-1 text-xs text-slate-500 flex items-center justify-between">
                    <span>Provider: {formatCurrency(execData.kpis.provider_reported_spend)}</span>
                    <span>Manual: {formatCurrency(execData.kpis.manual_estimated_spend)}</span>
                  </div>
                </div>

                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                  <div className="flex items-center justify-between text-xs text-slate-500 font-medium">
                    <span>ROAS & CPL</span>
                    {renderTrendBadge('roas')}
                  </div>
                  <div className="mt-2 text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                    {execData.kpis.roas}x ROAS
                  </div>
                  <div className="mt-1 text-xs text-slate-500">
                    CPL: {formatCurrency(execData.kpis.cost_per_lead)} ({execData.kpis.lead_conversion_rate}% conv rate)
                  </div>
                </div>
              </div>

              {/* Secondary Lead & Operational KPIs */}
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                  <div className="text-xs text-slate-500 font-medium">Total Leads Generated</div>
                  <div className="mt-2 text-xl font-bold text-slate-900 dark:text-white flex items-center justify-between">
                    <span>{execData.kpis.total_leads}</span>
                    {renderTrendBadge('total_leads')}
                  </div>
                  <div className="mt-1 text-xs text-slate-500">
                    {execData.kpis.qualified_leads} qualified, {execData.kpis.converted_leads} converted
                  </div>
                </div>

                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                  <div className="text-xs text-slate-500 font-medium">Invoiced & Collected</div>
                  <div className="mt-2 text-xl font-bold text-slate-900 dark:text-white">
                    {formatCurrency(execData.kpis.received_revenue)}
                  </div>
                  <div className="mt-1 text-xs text-rose-500">
                    {formatCurrency(execData.kpis.overdue_invoices_value)} overdue
                  </div>
                </div>

                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                  <div className="text-xs text-slate-500 font-medium">Omnichannel & AI Interactions</div>
                  <div className="mt-2 text-xl font-bold text-slate-900 dark:text-white">
                    {execData.kpis.whatsapp_conversations + execData.kpis.total_voice_calls}
                  </div>
                  <div className="mt-1 text-xs text-slate-500">
                    {execData.kpis.ai_conversations} AI chats, {execData.kpis.completed_voice_calls} voice calls
                  </div>
                </div>

                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                  <div className="text-xs text-slate-500 font-medium">Operations & Support Health</div>
                  <div className="mt-2 text-xl font-bold text-slate-900 dark:text-white">
                    {execData.kpis.open_support_tickets} Open Tickets
                  </div>
                  <div className="mt-1 text-xs text-slate-500">
                    {execData.kpis.breached_support_tickets} breached SLA, {execData.kpis.tasks_overdue} tasks overdue
                  </div>
                </div>
              </div>

              {/* Spend Transparency Banner */}
              <div className="p-4 rounded-xl border border-indigo-100 dark:border-indigo-900/50 bg-indigo-50/50 dark:bg-indigo-950/20 flex items-start gap-3">
                <Info className="w-5 h-5 text-indigo-600 dark:text-indigo-400 mt-0.5 flex-shrink-0" />
                <div className="text-xs text-indigo-900 dark:text-indigo-200 leading-relaxed">
                  <span className="font-semibold">Marketing Spend Attribution Rigor:</span> In accordance with enterprise governance, marketing spend is strictly segmented between <strong>Provider-Reported API Spend</strong> ({formatCurrency(execData.kpis.provider_reported_spend)}) from Meta/Google Ads platforms and <strong>Manual Estimated Spend</strong> ({formatCurrency(execData.kpis.manual_estimated_spend)}).
                </div>
              </div>
            </>
          ) : null}
        </div>
      )}

      {/* TAB 2: LEAD & FUNNEL ANALYTICS */}
      {activeTab === 'leads' && (
        <div className="space-y-6">
          {leadLoading ? (
            <div className="py-16 text-center text-slate-500">
              <RefreshCw className="w-8 h-8 animate-spin mx-auto mb-2 text-indigo-600" />
              Loading lead analytics and funnel...
            </div>
          ) : leadData ? (
            <>
              {/* Top Row Funnel Metrics */}
              <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                  <div className="text-xs text-slate-500 font-medium">Total Leads</div>
                  <div className="text-2xl font-bold text-slate-900 dark:text-white mt-1">{leadData.total_leads}</div>
                  <div className="text-xs text-slate-500 mt-1">100% Top of Funnel</div>
                </div>

                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                  <div className="text-xs text-slate-500 font-medium">Qualification Rate</div>
                  <div className="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{leadData.qualification_rate}%</div>
                  <div className="text-xs text-slate-500 mt-1">{leadData.qualified_leads} leads qualified</div>
                </div>

                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                  <div className="text-xs text-slate-500 font-medium">Conversion Rate</div>
                  <div className="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{leadData.conversion_rate}%</div>
                  <div className="text-xs text-slate-500 mt-1">{leadData.converted_leads} leads converted</div>
                </div>

                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                  <div className="text-xs text-slate-500 font-medium">Avg Lead Response Time</div>
                  <div className="text-2xl font-bold text-slate-900 dark:text-white mt-1">{leadData.avg_response_hours} hrs</div>
                  <div className="text-xs text-slate-500 mt-1">Duplicate Rate: {leadData.duplicate_rate}%</div>
                </div>
              </div>

              {/* Lead Aging Buckets */}
              <div className="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm space-y-4">
                <h3 className="text-sm font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                  <Clock className="w-4 h-4 text-indigo-500" />
                  Active Lead Aging Buckets
                </h3>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                  <div className="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/40 text-center">
                    <span className="text-xs text-emerald-800 dark:text-emerald-300 font-medium">0 – 7 Days</span>
                    <div className="text-xl font-bold text-emerald-900 dark:text-emerald-200 mt-1">
                      {leadData.aging_buckets['0_7_days']}
                    </div>
                  </div>
                  <div className="p-3 rounded-lg bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800/40 text-center">
                    <span className="text-xs text-blue-800 dark:text-blue-300 font-medium">8 – 14 Days</span>
                    <div className="text-xl font-bold text-blue-900 dark:text-blue-200 mt-1">
                      {leadData.aging_buckets['8_14_days']}
                    </div>
                  </div>
                  <div className="p-3 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/40 text-center">
                    <span className="text-xs text-amber-800 dark:text-amber-300 font-medium">15 – 30 Days</span>
                    <div className="text-xl font-bold text-amber-900 dark:text-amber-200 mt-1">
                      {leadData.aging_buckets['15_30_days']}
                    </div>
                  </div>
                  <div className="p-3 rounded-lg bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800/40 text-center">
                    <span className="text-xs text-rose-800 dark:text-rose-300 font-medium">30+ Days Stale</span>
                    <div className="text-xl font-bold text-rose-900 dark:text-rose-200 mt-1">
                      {leadData.aging_buckets['30_plus_days']}
                    </div>
                  </div>
                </div>
              </div>

              {/* By Source & Lost Reasons */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                  <h3 className="text-sm font-semibold text-slate-900 dark:text-white mb-3">Leads by Acquisition Source</h3>
                  <div className="space-y-3">
                    {leadData.by_source.map((src, i) => (
                      <div key={i} className="flex items-center justify-between text-xs border-b border-slate-100 dark:border-slate-800 pb-2">
                        <span className="font-medium text-slate-800 dark:text-slate-200">{src.source}</span>
                        <div className="flex items-center gap-3">
                          <span className="text-slate-500">{src.count} leads ({src.percentage}%)</span>
                          <span className="text-emerald-600 font-semibold">{src.converted} converted</span>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                <div className="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                  <h3 className="text-sm font-semibold text-slate-900 dark:text-white mb-3">Lost Reason Analysis</h3>
                  {leadData.lost_reasons.length === 0 ? (
                    <div className="text-xs text-slate-400 py-6 text-center">No lost leads recorded in this period.</div>
                  ) : (
                    <div className="space-y-3">
                      {leadData.lost_reasons.map((lr, i) => (
                        <div key={i} className="flex items-center justify-between text-xs border-b border-slate-100 dark:border-slate-800 pb-2">
                          <span className="text-slate-800 dark:text-slate-200">{lr.reason}</span>
                          <span className="font-bold text-rose-600">{lr.count} lost</span>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            </>
          ) : null}
        </div>
      )}

      {/* TAB 3: SALES PIPELINE & FORECASTING */}
      {activeTab === 'sales' && (
        <div className="space-y-6">
          {salesLoading ? (
            <div className="py-16 text-center text-slate-500">
              <RefreshCw className="w-8 h-8 animate-spin mx-auto mb-2 text-indigo-600" />
              Loading sales forecasts and stage analytics...
            </div>
          ) : salesData ? (
            <>
              {/* Sales Forecasting Cards */}
              <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                  <div className="text-xs text-slate-500 font-medium">Weighted Pipeline Forecast</div>
                  <div className="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                    {formatCurrency(salesData.weighted_forecast)}
                  </div>
                  <div className="text-xs text-slate-500 mt-1">From {formatCurrency(salesData.pipeline_value)} gross pipeline</div>
                </div>

                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                  <div className="text-xs text-slate-500 font-medium">Won Revenue</div>
                  <div className="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                    {formatCurrency(salesData.won_value)}
                  </div>
                  <div className="text-xs text-slate-500 mt-1">{salesData.won_deals_count} won deals ({salesData.win_rate}% win rate)</div>
                </div>

                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                  <div className="text-xs text-slate-500 font-medium">Average Deal Size</div>
                  <div className="text-2xl font-bold text-slate-900 dark:text-white mt-1">
                    {formatCurrency(salesData.avg_deal_size)}
                  </div>
                  <div className="text-xs text-slate-500 mt-1">Across all closed won opportunities</div>
                </div>

                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                  <div className="text-xs text-slate-500 font-medium">Avg Sales Cycle Duration</div>
                  <div className="text-2xl font-bold text-slate-900 dark:text-white mt-1">
                    {salesData.avg_sales_cycle_days} Days
                  </div>
                  <div className="text-xs text-slate-500 mt-1">Lead created to won contract</div>
                </div>
              </div>

              {/* Stage-wise Breakdown Table */}
              <div className="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <h3 className="text-sm font-semibold text-slate-900 dark:text-white mb-4">Pipeline Stage Velocity & Value Breakdown</h3>
                <div className="overflow-x-auto">
                  <table className="w-full text-xs text-left">
                    <thead className="bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700">
                      <tr>
                        <th className="py-2.5 px-3">Stage</th>
                        <th className="py-2.5 px-3">Deals Count</th>
                        <th className="py-2.5 px-3">Nominal Value</th>
                        <th className="py-2.5 px-3">Weighted Forecast Value</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                      {salesData.stage_breakdown.map((stage, i) => (
                        <tr key={i} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/50">
                          <td className="py-2.5 px-3 font-semibold capitalize text-slate-900 dark:text-slate-100">{stage.stage}</td>
                          <td className="py-2.5 px-3">{stage.count}</td>
                          <td className="py-2.5 px-3">{formatCurrency(stage.total_value)}</td>
                          <td className="py-2.5 px-3 font-medium text-indigo-600 dark:text-indigo-400">{formatCurrency(stage.weighted_value)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>

              {/* Sales Rep Quota Achievement Leaderboard */}
              <div className="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <h3 className="text-sm font-semibold text-slate-900 dark:text-white mb-4">Representative Quota vs Achievement</h3>
                <div className="space-y-4">
                  {salesData.rep_performance.map((rep, i) => (
                    <div key={i} className="space-y-1.5 border-b border-slate-100 dark:border-slate-800 pb-3">
                      <div className="flex items-center justify-between text-xs">
                        <span className="font-semibold text-slate-900 dark:text-slate-100">{rep.representative}</span>
                        <span className="text-slate-500">
                          {formatCurrency(rep.won_value)} of {formatCurrency(rep.quota_target)} target ({rep.achievement_percent}%)
                        </span>
                      </div>
                      <div className="w-full bg-slate-100 dark:bg-slate-800 h-2 rounded-full overflow-hidden">
                        <div
                          className={`h-full rounded-full transition-all ${
                            rep.achievement_percent >= 100 ? 'bg-emerald-500' : rep.achievement_percent >= 50 ? 'bg-indigo-500' : 'bg-amber-500'
                          }`}
                          style={{ width: `${Math.min(100, rep.achievement_percent)}%` }}
                        />
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </>
          ) : null}
        </div>
      )}

      {/* TAB 4: ATTRIBUTION ENGINE */}
      {activeTab === 'attribution' && (
        <div className="space-y-6">
          {/* Methodology Disclaimer Header */}
          <div className="p-4 rounded-xl border border-amber-200 dark:border-amber-900/60 bg-amber-50/50 dark:bg-amber-950/20 flex items-start gap-3">
            <Info className="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0" />
            <div className="text-xs text-amber-900 dark:text-amber-200 space-y-1">
              <span className="font-bold">Algorithmic Multi-Touch Attribution Notice:</span>
              <p>
                {attrData?.attribution_notice ||
                  'Attribution models represent algorithmic statistical estimates to guide marketing and sales investments, rather than deterministic absolute causation.'}
              </p>
            </div>
          </div>

          <div className="flex items-center justify-between">
            <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
              <Share2 className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
              Side-by-Side Model Comparison Matrix
            </h3>
            <button
              onClick={() => setShowTouchpointModal(true)}
              className="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-medium flex items-center gap-1.5 transition-colors shadow-sm"
            >
              <Plus className="w-3.5 h-3.5" />
              Simulate Customer Touchpoint
            </button>
          </div>

          {attrLoading ? (
            <div className="py-16 text-center text-slate-500">
              <RefreshCw className="w-8 h-8 animate-spin mx-auto mb-2 text-indigo-600" />
              Calculating attribution across models...
            </div>
          ) : attrData ? (
            <>
              {/* Attribution Comparison Table */}
              <div className="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm overflow-x-auto">
                <table className="w-full text-xs text-left">
                  <thead className="bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                      <th className="py-3 px-3">Channel / Source</th>
                      <th className="py-3 px-3">
                        First-Touch (100%)
                        <div className="text-[10px] font-normal text-slate-400">Discovery credit</div>
                      </th>
                      <th className="py-3 px-3">
                        Last-Touch (100%)
                        <div className="text-[10px] font-normal text-slate-400">Closing credit</div>
                      </th>
                      <th className="py-3 px-3">
                        Linear (1/N)
                        <div className="text-[10px] font-normal text-slate-400">Equal split</div>
                      </th>
                      <th className="py-3 px-3 font-semibold text-indigo-600 dark:text-indigo-400">
                        U-Shaped (40/20/40)
                        <div className="text-[10px] font-normal text-indigo-400">Position-based</div>
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                    {attrData.attribution_model_comparison.map((item, i) => (
                      <tr key={i} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/50">
                        <td className="py-3 px-3 font-semibold text-slate-900 dark:text-slate-100">{item.channel}</td>
                        <td className="py-3 px-3">{formatCurrency(item.first_touch_revenue)}</td>
                        <td className="py-3 px-3">{formatCurrency(item.last_touch_revenue)}</td>
                        <td className="py-3 px-3">{formatCurrency(item.linear_revenue)}</td>
                        <td className="py-3 px-3 font-bold text-indigo-600 dark:text-indigo-400">
                          {formatCurrency(item.u_shaped_revenue)}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {/* Attribution Models Guide */}
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                  <div className="text-xs font-bold text-slate-900 dark:text-white">First-Touch Model</div>
                  <p className="text-xs text-slate-500 mt-1">
                    Assigns 100% of revenue to the first campaign touchpoint. Ideal for evaluating top-of-funnel brand awareness.
                  </p>
                </div>
                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                  <div className="text-xs font-bold text-slate-900 dark:text-white">Last-Touch Model</div>
                  <p className="text-xs text-slate-500 mt-1">
                    Assigns 100% credit to the touchpoint right before the deal closed. Ideal for evaluating high-intent conversion channels.
                  </p>
                </div>
                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                  <div className="text-xs font-bold text-slate-900 dark:text-white">Linear Model</div>
                  <p className="text-xs text-slate-500 mt-1">
                    Distributes equal 1/N credit to each interaction in the journey. Prevents penalizing nurturing and mid-funnel content.
                  </p>
                </div>
                <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                  <div className="text-xs font-bold text-indigo-600 dark:text-indigo-400">U-Shaped Position Model</div>
                  <p className="text-xs text-slate-500 mt-1">
                    40% to first touch, 40% to closing touch, and 20% shared evenly across intermediate touches. Recommended for B2B cycles.
                  </p>
                </div>
              </div>
            </>
          ) : null}
        </div>
      )}

      {/* TAB 5: SAVED REPORTS & CSV EXPORT */}
      {activeTab === 'reports' && (
        <div className="space-y-6">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <FileSpreadsheet className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                Custom Reports & Scheduled Exports
              </h3>
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Generate immediate RFC 4180 CSV exports with authorized drill-down or save automated report schedules
              </p>
            </div>
            <button
              onClick={() => setShowSaveModal(true)}
              className="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-medium flex items-center gap-1.5 transition-colors shadow-sm self-start"
            >
              <Plus className="w-3.5 h-3.5" />
              Save New Report
            </button>
          </div>

          {/* Quick Export Generator */}
          <div className="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm space-y-4">
            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">Instant CSV Export Generator</h4>
            <div className="flex flex-wrap items-center gap-3">
              <select
                value={exportType}
                onChange={(e) => setExportType(e.target.value)}
                className="px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-xs bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none"
              >
                <option value="leads">Leads & Ingestion Dataset</option>
                <option value="deals">Deals & Pipeline Stage Dataset</option>
                <option value="campaigns">Marketing Campaigns & Spend</option>
                <option value="tickets">Support Tickets & SLA Compliance</option>
                <option value="attribution">Multi-Touch Channel Attribution</option>
              </select>

              <button
                onClick={() => handleExportCsv(exportType)}
                disabled={isExporting}
                className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold flex items-center gap-2 transition-colors disabled:opacity-50"
              >
                <Download className="w-3.5 h-3.5" />
                {isExporting ? 'Exporting...' : `Download ${exportType.toUpperCase()} CSV`}
              </button>

              {exportStatus && (
                <span className="text-xs text-emerald-600 dark:text-emerald-400 font-medium animate-pulse">
                  {exportStatus}
                </span>
              )}
            </div>
          </div>

          {/* Saved Reports List */}
          <div className="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">Saved Reports Repository</h4>
            {reportsLoading ? (
              <div className="py-8 text-center text-xs text-slate-400">Loading saved reports...</div>
            ) : savedReports && savedReports.length > 0 ? (
              <div className="divide-y divide-slate-100 dark:divide-slate-800">
                {savedReports.map((report) => (
                  <div key={report.id} className="py-3 flex items-center justify-between gap-4">
                    <div>
                      <div className="flex items-center gap-2">
                        <span className="font-semibold text-xs text-slate-900 dark:text-white">{report.title}</span>
                        {report.is_scheduled && (
                          <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                            Scheduled: {report.schedule_frequency}
                          </span>
                        )}
                      </div>
                      {report.description && <p className="text-xs text-slate-500 mt-0.5">{report.description}</p>}
                    </div>

                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => handleExportCsv(report.report_type)}
                        className="p-1.5 text-slate-600 hover:text-emerald-600 dark:text-slate-400 dark:hover:text-emerald-400 transition-colors"
                        title="Download CSV"
                      >
                        <Download className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => deleteReportMutation.mutate(report.id)}
                        className="p-1.5 text-slate-400 hover:text-rose-600 transition-colors"
                        title="Delete Saved Report"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="py-8 text-center text-xs text-slate-400">
                No saved reports created yet. Click "Save New Report" above to create automated dashboards.
              </div>
            )}
          </div>
        </div>
      )}

      {/* TAB 6: TRACKING SDK & GA4 */}
      {activeTab === 'tracking_ga4' && (
        <AnalyticsTrackingSdkGa4View />
      )}

      {/* MODAL: SAVE REPORT */}
      {showSaveModal && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
            <h3 className="text-base font-bold text-slate-900 dark:text-white">Save Custom Report Configuration</h3>

            <div className="space-y-3 text-xs">
              <div>
                <label className="block font-medium text-slate-700 dark:text-slate-300 mb-1">Report Title</label>
                <input
                  type="text"
                  value={newReportTitle}
                  onChange={(e) => setNewReportTitle(e.target.value)}
                  placeholder="e.g. Q4 Executive Sales & Marketing ROAS"
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-transparent text-slate-900 dark:text-white focus:outline-none"
                />
              </div>

              <div>
                <label className="block font-medium text-slate-700 dark:text-slate-300 mb-1">Report Type</label>
                <select
                  value={newReportType}
                  onChange={(e) => setNewReportType(e.target.value)}
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none"
                >
                  <option value="executive_summary">Executive Summary KPIs</option>
                  <option value="leads">Leads & Qualification Funnel</option>
                  <option value="deals">Deals & Forecast</option>
                  <option value="attribution">Channel Attribution</option>
                  <option value="campaigns">Marketing Campaigns</option>
                  <option value="tickets">Support Tickets</option>
                </select>
              </div>

              <div>
                <label className="block font-medium text-slate-700 dark:text-slate-300 mb-1">Description (Optional)</label>
                <textarea
                  value={newReportDesc}
                  onChange={(e) => setNewReportDesc(e.target.value)}
                  placeholder="Notes about this report..."
                  rows={2}
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-transparent text-slate-900 dark:text-white focus:outline-none"
                />
              </div>

              <div className="pt-2">
                <label className="flex items-center gap-2 text-slate-700 dark:text-slate-300 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={isScheduled}
                    onChange={(e) => setIsScheduled(e.target.checked)}
                    className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                  />
                  <span>Enable Automated Schedule</span>
                </label>
                {isScheduled && (
                  <div className="mt-2 pl-6">
                    <select
                      value={scheduleFreq}
                      onChange={(e) => setScheduleFreq(e.target.value as any)}
                      className="px-2.5 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs focus:outline-none"
                    >
                      <option value="daily">Daily Schedule</option>
                      <option value="weekly">Weekly Schedule</option>
                      <option value="monthly">Monthly Schedule</option>
                    </select>
                  </div>
                )}
              </div>
            </div>

            <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
              <button
                onClick={() => setShowSaveModal(false)}
                className="px-3 py-1.5 text-xs text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white"
              >
                Cancel
              </button>
              <button
                onClick={handleSaveReport}
                disabled={!newReportTitle.trim() || createReportMutation.isPending}
                className="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold transition-colors disabled:opacity-50"
              >
                {createReportMutation.isPending ? 'Saving...' : 'Save Report'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL: RECORD TEST TOUCHPOINT */}
      {showTouchpointModal && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
            <h3 className="text-base font-bold text-slate-900 dark:text-white">Simulate Customer Journey Touchpoint</h3>
            <p className="text-xs text-slate-500">Record a real touchpoint to test live multi-touch attribution credit distribution.</p>

            <div className="space-y-3 text-xs">
              <div>
                <label className="block font-medium text-slate-700 dark:text-slate-300 mb-1">Channel</label>
                <select
                  value={touchpointChannel}
                  onChange={(e) => setTouchpointChannel(e.target.value)}
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none"
                >
                  <option value="google_ads">Google Ads</option>
                  <option value="meta_ads">Meta Ads</option>
                  <option value="organic">Organic Search</option>
                  <option value="whatsapp">WhatsApp Inbound</option>
                  <option value="email">Email Campaign</option>
                  <option value="referral">Partner Referral</option>
                </select>
              </div>

              <div>
                <label className="block font-medium text-slate-700 dark:text-slate-300 mb-1">UTM Source</label>
                <input
                  type="text"
                  value={touchpointUtmSource}
                  onChange={(e) => setTouchpointUtmSource(e.target.value)}
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-transparent text-slate-900 dark:text-white focus:outline-none"
                />
              </div>

              <div>
                <label className="block font-medium text-slate-700 dark:text-slate-300 mb-1">UTM Campaign</label>
                <input
                  type="text"
                  value={touchpointUtmCampaign}
                  onChange={(e) => setTouchpointUtmCampaign(e.target.value)}
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-transparent text-slate-900 dark:text-white focus:outline-none"
                />
              </div>

              <div>
                <label className="block font-medium text-slate-700 dark:text-slate-300 mb-1">Touchpoint Type</label>
                <select
                  value={touchpointType}
                  onChange={(e) => setTouchpointType(e.target.value)}
                  className="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none"
                >
                  <option value="ad_click">Ad Click</option>
                  <option value="page_view">Page View</option>
                  <option value="form_submit">Form Submission</option>
                  <option value="chat_message">WhatsApp / Chat Message</option>
                  <option value="phone_call">Inbound Phone Call</option>
                </select>
              </div>
            </div>

            <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
              <button
                onClick={() => setShowTouchpointModal(false)}
                className="px-3 py-1.5 text-xs text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white"
              >
                Cancel
              </button>
              <button
                onClick={handleRecordTouchpoint}
                disabled={recordTouchpointMutation.isPending}
                className="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold transition-colors disabled:opacity-50"
              >
                {recordTouchpointMutation.isPending ? 'Recording...' : 'Record Touchpoint'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
