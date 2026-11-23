import React, { useState } from 'react';
import {
  Megaphone,
  Users,
  GitBranch,
  BarChart3,
  Plus,
  Play,
  Pause,
  Send,
  Trash2,
  Clock,
  CheckCircle2,
  XCircle,
  AlertCircle,
  Mail,
  MessageSquare,
  PhoneCall,
  Search,
  RefreshCw,
  FileText,
  ShieldCheck,
  Sparkles,
  Copy,
  Archive,
  Globe,
  DollarSign,
  TrendingUp,
  Target,
  ArrowRight
} from 'lucide-react';
import {
  useSegments,
  useCreateSegment,
  useDeleteSegment,
  useCampaigns,
  useCreateCampaign,
  useDeleteCampaign,
  useDispatchCampaign,
  useTestSendCampaign,
  usePauseCampaign,
  useResumeCampaign,
  useCampaignAnalytics,
  useDripSequences,
  useCreateDripSequence,
  useDeleteDripSequence,
  useProcessDueDripEnrollments,
  useSubmitCampaignForApproval,
  useApproveCampaign,
  useRejectCampaign,
  useDuplicateCampaign,
  useArchiveCampaign,
  useMarketingOverviewSummary,
  useMarketingTemplates,
  useCreateMarketingTemplate,
  useDeleteMarketingTemplate,
  usePreviewMarketingTemplate,
  useGenerateMarketingTemplateAi,
  useMarketingConsents,
  useRecordMarketingConsent,
  useMarketingSuppressions,
  useUnsubscribeMarketingRecipient
} from '../../hooks/useMarketingCampaigns';
import {
  MarketingCampaignItem,
  MarketingSegmentItem,
  DripSequenceItem,
  MarketingTemplateItem,
} from '../../api/types';

interface MarketingCampaignsViewProps {
  theme: 'light' | 'dark';
}

export const MarketingCampaignsView: React.FC<MarketingCampaignsViewProps> = ({ theme }) => {
  const [activeTab, setActiveTab] = useState<'campaigns' | 'segments' | 'templates' | 'drips' | 'channels' | 'compliance' | 'analytics'>('campaigns');
  const [channelFilter, setChannelFilter] = useState<'all' | 'whatsapp' | 'email' | 'sms'>('all');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState('');

  // Modals & Drawers
  const [isCampaignModalOpen, setIsCampaignModalOpen] = useState(false);
  const [isSegmentModalOpen, setIsSegmentModalOpen] = useState(false);
  const [isDripModalOpen, setIsDripModalOpen] = useState(false);
  const [isTemplateModalOpen, setIsTemplateModalOpen] = useState(false);
  const [isTestSendModalOpen, setIsTestSendModalOpen] = useState(false);
  const [isRejectModalOpen, setIsRejectModalOpen] = useState(false);
  const [isConsentModalOpen, setIsConsentModalOpen] = useState(false);

  const [selectedCampaignForTest, setSelectedCampaignForTest] = useState<MarketingCampaignItem | null>(null);
  const [selectedCampaignForReject, setSelectedCampaignForReject] = useState<MarketingCampaignItem | null>(null);
  const [rejectionReason, setRejectionReason] = useState('');
  const [testRecipientAddress, setTestRecipientAddress] = useState('');
  const [selectedCampaignForAnalytics, setSelectedCampaignForAnalytics] = useState<number | null>(null);
  const [selectedTemplateForPreview, setSelectedTemplateForPreview] = useState<any | null>(null);

  // Queries
  const { data: campaigns = [], isLoading: isLoadingCampaigns } = useCampaigns(
    channelFilter === 'all' ? undefined : channelFilter,
    statusFilter === 'all' ? undefined : statusFilter
  );
  const { data: segments = [] } = useSegments();
  const { data: dripSequences = [] } = useDripSequences();
  const { data: templates = [] } = useMarketingTemplates();
  const { data: consents = [] } = useMarketingConsents();
  const { data: suppressions = [] } = useMarketingSuppressions();
  const { data: overviewSummary } = useMarketingOverviewSummary();
  const { data: analytics, isLoading: isLoadingAnalytics } = useCampaignAnalytics(selectedCampaignForAnalytics);

  // Mutations
  const createCampaignMutation = useCreateCampaign();
  const deleteCampaignMutation = useDeleteCampaign();
  const dispatchCampaignMutation = useDispatchCampaign();
  const testSendMutation = useTestSendCampaign();
  const pauseCampaignMutation = usePauseCampaign();
  const resumeCampaignMutation = useResumeCampaign();
  const submitApprovalMutation = useSubmitCampaignForApproval();
  const approveMutation = useApproveCampaign();
  const rejectMutation = useRejectCampaign();
  const duplicateMutation = useDuplicateCampaign();
  const archiveMutation = useArchiveCampaign();

  const createSegmentMutation = useCreateSegment();
  const deleteSegmentMutation = useDeleteSegment();

  const createTemplateMutation = useCreateMarketingTemplate();
  const deleteTemplateMutation = useDeleteMarketingTemplate();
  const previewTemplateMutation = usePreviewMarketingTemplate();
  const generateAiTemplateMutation = useGenerateMarketingTemplateAi();

  const createDripMutation = useCreateDripSequence();
  const deleteDripMutation = useDeleteDripSequence();
  const processDripsMutation = useProcessDueDripEnrollments();

  const recordConsentMutation = useRecordMarketingConsent();
  const unsubscribeMutation = useUnsubscribeMarketingRecipient();

  // Campaign Form State
  const [campaignForm, setCampaignForm] = useState<{
    name: string;
    description: string;
    channel: 'whatsapp' | 'email' | 'sms';
    type: 'broadcast' | 'drip' | 'triggered';
    objective: string;
    budget: number;
    business_hours_only: boolean;
    frequency_cap_days: number;
    segment_id: string;
    subject: string;
    content: string;
    rate_limit_per_minute: number;
    campaign_notes: string;
  }>({
    name: '',
    description: '',
    channel: 'whatsapp',
    type: 'broadcast',
    objective: 'lead_generation',
    budget: 10000,
    business_hours_only: true,
    frequency_cap_days: 7,
    segment_id: '',
    subject: '',
    content: 'Hello {{first_name}}, we have an exciting update regarding {{company}}!',
    rate_limit_per_minute: 120,
    campaign_notes: '',
  });

  // Template Form State
  const [templateForm, setTemplateForm] = useState<{
    name: string;
    channel: 'whatsapp' | 'email' | 'sms';
    category: string;
    subject: string;
    content: string;
    dlt_template_id: string;
    variables: string[];
  }>({
    name: '',
    channel: 'whatsapp',
    category: 'marketing',
    subject: '',
    content: 'Hi {{first_name}}, check out our latest offers for {{company}}!',
    dlt_template_id: '',
    variables: ['first_name', 'company'],
  });

  // AI Generator Prompt State
  const [aiPrompt, setAiPrompt] = useState({
    objective: 'New product demo invite',
    product_name: 'Apex Enterprise Cloud CRM',
  });

  // Consent Form State
  const [consentForm, setConsentForm] = useState({
    entity_type: 'lead',
    entity_id: 1,
    channel: 'whatsapp',
    consent_status: 'opted_in',
    source: 'website_form',
    reason: '',
  });

  // Segment Form State
  const [segmentForm, setSegmentForm] = useState<{
    name: string;
    description: string;
    type: 'dynamic' | 'static';
    target_entity: 'lead' | 'contact' | 'customer';
    rules: Record<string, any>;
    inclusion_ids: number[];
    exclusion_ids: number[];
  }>({
    name: '',
    description: '',
    type: 'dynamic',
    target_entity: 'lead',
    rules: {
      status: ['new', 'contacted'],
      min_lead_score: 10
    },
    inclusion_ids: [],
    exclusion_ids: []
  });

  // Drip Form State
  const [dripForm, setDripForm] = useState<{
    name: string;
    description: string;
    journey_type: string;
    trigger_event: string;
    exit_on_conversion: boolean;
    exit_on_reply: boolean;
    exit_on_unsubscribe: boolean;
    respect_quiet_hours: boolean;
    quiet_hours_start: string;
    quiet_hours_end: string;
    max_messages_per_week: number;
    steps: Array<{
      step_type: 'send_message' | 'wait_delay' | 'condition_check' | 'assign_rep' | 'schedule_followup';
      channel: 'whatsapp' | 'email' | 'sms';
      delay_interval: number;
      delay_unit: 'minutes' | 'hours' | 'days';
      subject?: string;
      content: string;
    }>;
  }>({
    name: '',
    description: '',
    journey_type: 'lead_nurturing',
    trigger_event: 'lead_created',
    exit_on_conversion: true,
    exit_on_reply: true,
    exit_on_unsubscribe: true,
    respect_quiet_hours: true,
    quiet_hours_start: '21:00',
    quiet_hours_end: '09:00',
    max_messages_per_week: 3,
    steps: [
      {
        step_type: 'send_message',
        channel: 'whatsapp',
        delay_interval: 0,
        delay_unit: 'minutes',
        content: 'Hi {{name}}, thanks for connecting with us! How can we help you today?'
      },
      {
        step_type: 'condition_check',
        channel: 'whatsapp',
        delay_interval: 1,
        delay_unit: 'days',
        content: 'Check if lead replied or engaged'
      },
      {
        step_type: 'assign_rep',
        channel: 'whatsapp',
        delay_interval: 0,
        delay_unit: 'minutes',
        content: 'Assign qualified lead to sales rep'
      }
    ]
  });

  const isDark = theme === 'dark';
  const cardBg = isDark ? 'bg-slate-800 border-slate-700' : 'bg-white border-slate-200';
  const textPrimary = isDark ? 'text-white' : 'text-slate-900';
  const textSecondary = isDark ? 'text-slate-400' : 'text-slate-600';

  // Handlers
  const handleCreateCampaign = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!campaignForm.name) return;
    await createCampaignMutation.mutateAsync({
      name: campaignForm.name,
      description: campaignForm.description,
      channel: campaignForm.channel,
      type: campaignForm.type,
      objective: campaignForm.objective,
      budget: campaignForm.budget,
      business_hours_only: campaignForm.business_hours_only,
      frequency_cap_days: campaignForm.frequency_cap_days,
      campaign_notes: campaignForm.campaign_notes,
      segment_id: campaignForm.segment_id ? Number(campaignForm.segment_id) : null,
      subject: campaignForm.subject || null,
      content: campaignForm.content || null,
      rate_limit_per_minute: campaignForm.rate_limit_per_minute
    });
    setIsCampaignModalOpen(false);
  };

  const handleCreateTemplate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!templateForm.name || !templateForm.content) return;
    await createTemplateMutation.mutateAsync(templateForm);
    setIsTemplateModalOpen(false);
    setTemplateForm({
      name: '',
      channel: 'whatsapp',
      category: 'marketing',
      subject: '',
      content: '',
      dlt_template_id: '',
      variables: ['first_name'],
    });
  };

  const handleAiDraftTemplate = async () => {
    const res = await generateAiTemplateMutation.mutateAsync({
      channel: templateForm.channel,
      objective: aiPrompt.objective,
      product_name: aiPrompt.product_name,
    });
    if (res?.data) {
      setTemplateForm((prev) => ({
        ...prev,
        subject: res.data.subject || prev.subject,
        content: res.data.content,
        dlt_template_id: res.data.dlt_template_id || prev.dlt_template_id,
        variables: res.data.variables || prev.variables,
      }));
    }
  };

  const handleCreateSegment = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!segmentForm.name) return;
    await createSegmentMutation.mutateAsync(segmentForm);
    setIsSegmentModalOpen(false);
  };

  const handleCreateDrip = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!dripForm.name) return;
    await createDripMutation.mutateAsync(dripForm);
    setIsDripModalOpen(false);
  };

  const handleTestSend = async () => {
    if (!selectedCampaignForTest || !testRecipientAddress) return;
    await testSendMutation.mutateAsync({
      id: selectedCampaignForTest.id,
      recipient: testRecipientAddress
    });
    setIsTestSendModalOpen(false);
    setTestRecipientAddress('');
    setSelectedCampaignForTest(null);
  };

  const handleRejectCampaign = async () => {
    if (!selectedCampaignForReject || !rejectionReason) return;
    await rejectMutation.mutateAsync({
      id: selectedCampaignForReject.id,
      reason: rejectionReason,
    });
    setIsRejectModalOpen(false);
    setSelectedCampaignForReject(null);
    setRejectionReason('');
  };

  const handleRecordConsent = async (e: React.FormEvent) => {
    e.preventDefault();
    await recordConsentMutation.mutateAsync(consentForm as any);
    setIsConsentModalOpen(false);
  };

  const filteredCampaigns = campaigns.filter((c) =>
    c.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    c.description?.toLowerCase().includes(searchQuery.toLowerCase())
  );

  return (
    <div className={`p-6 space-y-6 ${isDark ? 'bg-slate-900' : 'bg-slate-50'} min-h-screen`}>
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-3">
            <div className="p-2.5 bg-indigo-600 rounded-xl text-white shadow-lg shadow-indigo-600/30">
              <Megaphone className="w-6 h-6" />
            </div>
            <div>
              <h1 className={`text-2xl font-bold tracking-tight ${textPrimary}`}>
                Marketing Automation & Campaign Platform
              </h1>
              <p className={`text-sm ${textSecondary}`}>
                Multi-channel campaigns, granular customer segmentation, drip journeys, TRAI DLT compliance & ROI attribution.
              </p>
            </div>
          </div>
        </div>

        {/* Global Action Buttons */}
        <div className="flex items-center gap-2">
          {activeTab === 'campaigns' && (
            <button
              onClick={() => setIsCampaignModalOpen(true)}
              className="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium shadow-sm transition-all"
            >
              <Plus className="w-4 h-4" /> Create Campaign
            </button>
          )}
          {activeTab === 'segments' && (
            <button
              onClick={() => setIsSegmentModalOpen(true)}
              className="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium shadow-sm transition-all"
            >
              <Plus className="w-4 h-4" /> New Segment
            </button>
          )}
          {activeTab === 'templates' && (
            <button
              onClick={() => setIsTemplateModalOpen(true)}
              className="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium shadow-sm transition-all"
            >
              <Plus className="w-4 h-4" /> New Template
            </button>
          )}
          {activeTab === 'drips' && (
            <div className="flex gap-2">
              <button
                onClick={() => processDripsMutation.mutate()}
                disabled={processDripsMutation.isPending}
                className="flex items-center gap-2 px-3 py-2 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-lg text-sm font-medium transition-all"
              >
                <RefreshCw className={`w-4 h-4 ${processDripsMutation.isPending ? 'animate-spin' : ''}`} />
                Process Due
              </button>
              <button
                onClick={() => setIsDripModalOpen(true)}
                className="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium shadow-sm transition-all"
              >
                <Plus className="w-4 h-4" /> Build Journey
              </button>
            </div>
          )}
          {activeTab === 'compliance' && (
            <button
              onClick={() => setIsConsentModalOpen(true)}
              className="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium shadow-sm transition-all"
            >
              <Plus className="w-4 h-4" /> Record Consent / DND
            </button>
          )}
        </div>
      </div>

      {/* Overview Stat Counters */}
      <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
        <div className={`p-4 rounded-xl border shadow-sm ${cardBg}`}>
          <div className="text-xs text-slate-400 font-medium">Total Campaigns</div>
          <div className={`text-xl font-bold mt-1 ${textPrimary}`}>{overviewSummary?.total_campaigns || campaigns.length}</div>
        </div>
        <div className={`p-4 rounded-xl border shadow-sm ${cardBg}`}>
          <div className="text-xs text-slate-400 font-medium">Active Broadcasts</div>
          <div className="text-xl font-bold mt-1 text-emerald-500">{overviewSummary?.active_campaigns || campaigns.filter(c => c.status === 'running').length}</div>
        </div>
        <div className={`p-4 rounded-xl border shadow-sm ${cardBg}`}>
          <div className="text-xs text-slate-400 font-medium">Total Budget</div>
          <div className={`text-xl font-bold mt-1 ${textPrimary}`}>₹{Number(overviewSummary?.total_budget || 0).toLocaleString()}</div>
        </div>
        <div className={`p-4 rounded-xl border shadow-sm ${cardBg}`}>
          <div className="text-xs text-slate-400 font-medium">Attributed Revenue</div>
          <div className="text-xl font-bold mt-1 text-indigo-500">₹{Number(overviewSummary?.total_attributed_revenue || 0).toLocaleString()}</div>
        </div>
        <div className={`p-4 rounded-xl border shadow-sm ${cardBg}`}>
          <div className="text-xs text-slate-400 font-medium">Overall ROI</div>
          <div className="text-xl font-bold mt-1 text-cyan-500">+{overviewSummary?.overall_roi_percent || 0}%</div>
        </div>
        <div className={`p-4 rounded-xl border shadow-sm ${cardBg}`}>
          <div className="text-xs text-slate-400 font-medium">Delivered Messages</div>
          <div className={`text-xl font-bold mt-1 ${textPrimary}`}>{overviewSummary?.total_delivered || 0}</div>
        </div>
      </div>

      {/* Navigation Tabs */}
      <div className="flex border-b border-slate-200 dark:border-slate-700 space-x-6 overflow-x-auto">
        <button
          onClick={() => setActiveTab('campaigns')}
          className={`flex items-center gap-2 pb-3 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
            activeTab === 'campaigns'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
          }`}
        >
          <Megaphone className="w-4 h-4" /> Campaigns Directory
        </button>

        <button
          onClick={() => setActiveTab('segments')}
          className={`flex items-center gap-2 pb-3 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
            activeTab === 'segments'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
          }`}
        >
          <Users className="w-4 h-4" /> Audience Segments
        </button>

        <button
          onClick={() => setActiveTab('templates')}
          className={`flex items-center gap-2 pb-3 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
            activeTab === 'templates'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
          }`}
        >
          <FileText className="w-4 h-4" /> Content & Templates
        </button>

        <button
          onClick={() => setActiveTab('drips')}
          className={`flex items-center gap-2 pb-3 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
            activeTab === 'drips'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
          }`}
        >
          <GitBranch className="w-4 h-4" /> Customer Journeys
        </button>

        <button
          onClick={() => setActiveTab('channels')}
          className={`flex items-center gap-2 pb-3 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
            activeTab === 'channels'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
          }`}
        >
          <Globe className="w-4 h-4" /> Channels & Health
        </button>

        <button
          onClick={() => setActiveTab('compliance')}
          className={`flex items-center gap-2 pb-3 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
            activeTab === 'compliance'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
          }`}
        >
          <ShieldCheck className="w-4 h-4" /> Consent & DND
        </button>

        <button
          onClick={() => setActiveTab('analytics')}
          className={`flex items-center gap-2 pb-3 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
            activeTab === 'analytics'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
          }`}
        >
          <BarChart3 className="w-4 h-4" /> Attribution & ROI
        </button>
      </div>

      {/* TAB 1: CAMPAIGNS DIRECTORY */}
      {activeTab === 'campaigns' && (
        <div className="space-y-4">
          {/* Filters Bar */}
          <div className="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div className="flex items-center gap-2 w-full sm:w-auto">
              <div className="relative flex-1 sm:w-64">
                <Search className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                <input
                  type="text"
                  placeholder="Search campaigns..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className={`w-full pl-9 pr-3 py-1.5 text-sm rounded-lg border outline-none ${
                    isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-200'
                  }`}
                />
              </div>

              <select
                value={channelFilter}
                onChange={(e) => setChannelFilter(e.target.value as any)}
                className={`py-1.5 px-3 text-sm rounded-lg border outline-none ${
                  isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-200'
                }`}
              >
                <option value="all">All Channels</option>
                <option value="whatsapp">WhatsApp</option>
                <option value="email">Email</option>
                <option value="sms">SMS</option>
              </select>

              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className={`py-1.5 px-3 text-sm rounded-lg border outline-none ${
                  isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-200'
                }`}
              >
                <option value="all">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="in_review">In Review</option>
                <option value="scheduled">Scheduled</option>
                <option value="running">Running</option>
                <option value="paused">Paused</option>
                <option value="completed">Completed</option>
              </select>
            </div>
          </div>

          {/* Campaigns Grid */}
          {isLoadingCampaigns ? (
            <div className="py-12 text-center text-slate-400">Loading campaigns...</div>
          ) : filteredCampaigns.length === 0 ? (
            <div className={`p-8 text-center rounded-xl border border-dashed ${cardBg}`}>
              <Megaphone className="w-12 h-12 mx-auto text-slate-400 mb-3" />
              <h3 className={`text-base font-semibold ${textPrimary}`}>No campaigns found</h3>
              <p className={`text-sm ${textSecondary} mb-4`}>
                Create broadcast marketing campaigns to reach verified CRM leads and customers.
              </p>
              <button
                onClick={() => setIsCampaignModalOpen(true)}
                className="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg font-medium hover:bg-indigo-700"
              >
                Create Campaign
              </button>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {filteredCampaigns.map((camp) => (
                <div
                  key={camp.id}
                  className={`p-5 rounded-xl border flex flex-col justify-between shadow-sm hover:shadow-md transition-shadow ${cardBg}`}
                >
                  <div className="space-y-3">
                    <div className="flex items-start justify-between gap-2">
                      <div className="flex items-center gap-2">
                        {camp.channel === 'whatsapp' && (
                          <div className="p-1.5 rounded-lg bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600">
                            <MessageSquare className="w-4 h-4" />
                          </div>
                        )}
                        {camp.channel === 'email' && (
                          <div className="p-1.5 rounded-lg bg-blue-100 dark:bg-blue-950/60 text-blue-600">
                            <Mail className="w-4 h-4" />
                          </div>
                        )}
                        {camp.channel === 'sms' && (
                          <div className="p-1.5 rounded-lg bg-purple-100 dark:bg-purple-950/60 text-purple-600">
                            <PhoneCall className="w-4 h-4" />
                          </div>
                        )}
                        <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                          {camp.channel}
                        </span>
                        {camp.objective && (
                          <span className="text-[10px] px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-medium">
                            {camp.objective.replace('_', ' ')}
                          </span>
                        )}
                      </div>

                      {/* Approval Status Badge */}
                      <span
                        className={`text-xs px-2.5 py-0.5 rounded-full font-medium ${
                          camp.approval_status === 'approved'
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400'
                            : camp.approval_status === 'pending_approval'
                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400 animate-pulse'
                            : camp.approval_status === 'rejected'
                            ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-400'
                            : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                        }`}
                        title={camp.rejection_reason || 'Approval status'}
                      >
                        {camp.approval_status === 'pending_approval' ? 'In Review' : (camp.approval_status || 'Draft')}
                      </span>
                    </div>

                    <div>
                      <h3 className={`text-base font-semibold ${textPrimary} truncate`}>{camp.name}</h3>
                      <p className={`text-xs ${textSecondary} line-clamp-2 mt-1`}>
                        {camp.description || camp.campaign_notes || 'No description provided'}
                      </p>
                    </div>

                    {/* Meta info tags */}
                    <div className="flex flex-wrap items-center gap-2 text-[11px] text-slate-500">
                      {camp.budget ? (
                        <span className="flex items-center gap-1 font-semibold text-indigo-600 dark:text-indigo-400">
                          <DollarSign className="w-3 h-3" /> Budget: ₹{Number(camp.budget).toLocaleString()}
                        </span>
                      ) : null}
                      {camp.business_hours_only && (
                        <span className="flex items-center gap-1 text-slate-400" title="Restricted to 09:00 - 20:00 IST">
                          <Clock className="w-3 h-3 text-amber-500" /> Quiet Hours Enforced
                        </span>
                      )}
                      {camp.frequency_cap_days ? (
                        <span className="text-slate-400">Cap: {camp.frequency_cap_days}d</span>
                      ) : null}
                    </div>

                    {/* Quick Stats Grid */}
                    <div className="grid grid-cols-4 gap-2 pt-2 border-t border-slate-100 dark:border-slate-700 text-center">
                      <div>
                        <div className="text-xs text-slate-400">Audience</div>
                        <div className={`text-sm font-bold ${textPrimary}`}>{camp.total_recipients_count}</div>
                      </div>
                      <div>
                        <div className="text-xs text-slate-400">Delivered</div>
                        <div className="text-sm font-bold text-emerald-500">{camp.delivered_count}</div>
                      </div>
                      <div>
                        <div className="text-xs text-slate-400">Conversions</div>
                        <div className="text-sm font-bold text-indigo-500">{camp.conversions_count || 0}</div>
                      </div>
                      <div>
                        <div className="text-xs text-slate-400">Revenue</div>
                        <div className={`text-sm font-bold ${textPrimary}`}>
                          ₹{Number(camp.attributed_revenue || 0).toLocaleString()}
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Actions Footer */}
                  <div className="flex items-center justify-between pt-4 mt-3 border-t border-slate-100 dark:border-slate-700 text-xs">
                    <div className="flex items-center gap-1.5 flex-wrap">
                      {/* Submit for approval */}
                      {(camp.approval_status === 'not_submitted' || camp.approval_status === 'rejected') && (
                        <button
                          onClick={() => submitApprovalMutation.mutate({ id: camp.id })}
                          disabled={submitApprovalMutation.isPending}
                          className="flex items-center gap-1 px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded font-medium transition-all"
                        >
                          <Clock className="w-3 h-3" /> Submit Review
                        </button>
                      )}

                      {/* Manager Approvals */}
                      {camp.approval_status === 'pending_approval' && (
                        <>
                          <button
                            onClick={() => approveMutation.mutate({ id: camp.id })}
                            disabled={approveMutation.isPending}
                            className="flex items-center gap-1 px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded font-medium"
                          >
                            <CheckCircle2 className="w-3 h-3" /> Approve
                          </button>
                          <button
                            onClick={() => {
                              setSelectedCampaignForReject(camp);
                              setIsRejectModalOpen(true);
                            }}
                            className="flex items-center gap-1 px-2 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded font-medium"
                          >
                            <XCircle className="w-3 h-3" /> Reject
                          </button>
                        </>
                      )}

                      {/* Dispatch when approved or ready */}
                      {camp.approval_status === 'approved' && camp.status !== 'completed' && camp.status !== 'running' && (
                        <button
                          onClick={() => dispatchCampaignMutation.mutate(camp.id)}
                          disabled={dispatchCampaignMutation.isPending}
                          className="flex items-center gap-1 px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded font-medium transition-all"
                        >
                          <Play className="w-3 h-3" /> Dispatch
                        </button>
                      )}

                      {camp.status === 'running' && (
                        <button
                          onClick={() => pauseCampaignMutation.mutate(camp.id)}
                          className="flex items-center gap-1 px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded font-medium"
                        >
                          <Pause className="w-3 h-3" /> Pause
                        </button>
                      )}
                      {camp.status === 'paused' && (
                        <button
                          onClick={() => resumeCampaignMutation.mutate(camp.id)}
                          className="flex items-center gap-1 px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded font-medium"
                        >
                          <Play className="w-3 h-3" /> Resume
                        </button>
                      )}
                      <button
                        onClick={() => {
                          setSelectedCampaignForTest(camp);
                          setIsTestSendModalOpen(true);
                        }}
                        className="flex items-center gap-1 px-2 py-1 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded font-medium"
                      >
                        <Send className="w-3 h-3" /> Test
                      </button>
                    </div>

                    <div className="flex items-center gap-1">
                      <button
                        onClick={() => duplicateMutation.mutate(camp.id)}
                        className="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-700 rounded"
                        title="Duplicate Campaign"
                      >
                        <Copy className="w-3.5 h-3.5" />
                      </button>
                      <button
                        onClick={() => {
                          setSelectedCampaignForAnalytics(camp.id);
                          setActiveTab('analytics');
                        }}
                        className="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-700 rounded"
                        title="View Analytics & Attribution"
                      >
                        <BarChart3 className="w-3.5 h-3.5" />
                      </button>
                      <button
                        onClick={() => archiveMutation.mutate(camp.id)}
                        className="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 rounded"
                        title="Archive"
                      >
                        <Archive className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* TAB 2: AUDIENCE SEGMENTS */}
      {activeTab === 'segments' && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {segments.map((seg) => (
              <div
                key={seg.id}
                className={`p-5 rounded-xl border flex flex-col justify-between shadow-sm ${cardBg}`}
              >
                <div>
                  <div className="flex items-center justify-between mb-2">
                    <span className="text-xs font-semibold px-2 py-0.5 rounded uppercase tracking-wider bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400">
                      {seg.type} segment
                    </span>
                    <span className="text-xs text-slate-400">Target: {seg.target_entity}</span>
                  </div>
                  <h3 className={`text-base font-bold ${textPrimary}`}>{seg.name}</h3>
                  <p className={`text-xs ${textSecondary} mt-1`}>{seg.description || 'No description provided'}</p>

                  <div className="mt-4 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700">
                    <div className="flex items-center justify-between">
                      <span className="text-xs text-slate-500">Audience Pool:</span>
                      <span className="text-lg font-bold text-indigo-600 dark:text-indigo-400">
                        {seg.cached_count}
                      </span>
                    </div>
                    {seg.last_calculated_at && (
                      <div className="text-[10px] text-slate-400 mt-1">
                        Updated {new Date(seg.last_calculated_at).toLocaleString()}
                      </div>
                    )}
                  </div>
                </div>

                <div className="flex items-center justify-between pt-4 mt-3 border-t border-slate-100 dark:border-slate-700">
                  <span className="text-xs text-slate-400">
                    Rules: {Object.keys(seg.rules || {}).length} active
                  </span>
                  <button
                    onClick={() => deleteSegmentMutation.mutate(seg.id)}
                    className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-slate-100 dark:hover:bg-slate-700 rounded"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* TAB 3: CONTENT & TEMPLATES */}
      {activeTab === 'templates' && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {templates.map((tpl: MarketingTemplateItem) => (
              <div key={tpl.id} className={`p-5 rounded-xl border flex flex-col justify-between shadow-sm ${cardBg}`}>
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <span className="text-xs font-semibold uppercase px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                        {tpl.channel}
                      </span>
                      <span className="text-xs text-slate-400">{tpl.category}</span>
                    </div>
                    {tpl.dlt_template_id && (
                      <span className="text-[10px] px-2 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 border border-emerald-200 dark:border-emerald-800 font-mono">
                        DLT: {tpl.dlt_template_id}
                      </span>
                    )}
                  </div>

                  <div>
                    <h3 className={`text-base font-bold ${textPrimary}`}>{tpl.name}</h3>
                    {tpl.subject && (
                      <p className="text-xs font-medium text-indigo-500 mt-1">Sub: {tpl.subject}</p>
                    )}
                    <div className="mt-2 p-2.5 rounded bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs font-mono text-slate-600 dark:text-slate-400 line-clamp-3">
                      {tpl.content}
                    </div>
                  </div>

                  <div className="flex flex-wrap gap-1 mt-2">
                    {(tpl.variables || []).map((v: string) => (
                      <span key={v} className="text-[10px] px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 font-mono">
                        {`{{${v}}}`}
                      </span>
                    ))}
                  </div>
                </div>

                <div className="flex items-center justify-between pt-4 mt-3 border-t border-slate-100 dark:border-slate-700 text-xs">
                  <button
                    onClick={async () => {
                      const res = await previewTemplateMutation.mutateAsync(tpl.id);
                      setSelectedTemplateForPreview(res);
                    }}
                    className="text-indigo-600 dark:text-indigo-400 font-medium hover:underline"
                  >
                    Preview Sample
                  </button>
                  <button
                    onClick={() => deleteTemplateMutation.mutate(tpl.id)}
                    className="p-1 text-slate-400 hover:text-rose-600"
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* TAB 4: CUSTOMER JOURNEYS & DRIP SEQUENCES */}
      {activeTab === 'drips' && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {dripSequences.map((seq) => (
              <div
                key={seq.id}
                className={`p-5 rounded-xl border flex flex-col justify-between shadow-sm ${cardBg}`}
              >
                <div>
                  <div className="flex items-center justify-between mb-2">
                    <span className="text-xs font-semibold px-2 py-0.5 rounded uppercase tracking-wider bg-purple-50 dark:bg-purple-950 text-purple-600 dark:text-purple-400">
                      {seq.journey_type || 'lead_nurturing'}
                    </span>
                    <span className="text-xs text-slate-400">Trigger: {seq.trigger_event}</span>
                  </div>
                  <h3 className={`text-base font-bold ${textPrimary}`}>{seq.name}</h3>
                  <p className={`text-xs ${textSecondary} mt-1`}>{seq.description || 'Automated multi-step journey'}</p>

                  {/* Flowchart Steps Visualizer */}
                  <div className="mt-4 space-y-2">
                    {(seq.steps || []).map((step, idx) => (
                      <div
                        key={step.id || idx}
                        className="p-3 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/40 text-xs flex items-center justify-between"
                      >
                        <div className="flex items-center gap-2">
                          <span className="w-5 h-5 rounded-full bg-indigo-600 text-white font-bold flex items-center justify-center text-[10px]">
                            {idx + 1}
                          </span>
                          <div>
                            <span className="font-semibold text-slate-700 dark:text-slate-300 capitalize">
                              {step.step_type ? step.step_type.replace('_', ' ') : 'Send Message'}
                            </span>
                            <div className="text-[10px] text-slate-400">
                              Channel: {step.channel} • Delay: {step.delay_interval} {step.delay_unit}
                            </div>
                          </div>
                        </div>
                        {step.step_type === 'condition_check' ? (
                          <span className="text-[10px] px-2 py-0.5 rounded bg-amber-100 text-amber-700 font-semibold">
                            YES/NO Branch
                          </span>
                        ) : null}
                      </div>
                    ))}
                  </div>

                  <div className="mt-4 flex items-center justify-between text-xs text-slate-500">
                    <span>Active In Flight: <strong>{seq.active_enrollments_count}</strong></span>
                    <span>Completed: <strong>{seq.completed_enrollments_count}</strong></span>
                  </div>
                </div>

                <div className="flex items-center justify-end pt-4 mt-3 border-t border-slate-100 dark:border-slate-700">
                  <button
                    onClick={() => deleteDripMutation.mutate(seq.id)}
                    className="p-1.5 text-slate-400 hover:text-rose-600 rounded"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* TAB 5: CHANNELS & HEALTH */}
      {activeTab === 'channels' && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div className={`p-5 rounded-xl border shadow-sm ${cardBg}`}>
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2">
                  <div className="p-2 rounded-lg bg-emerald-100 text-emerald-600">
                    <MessageSquare className="w-5 h-5" />
                  </div>
                  <div>
                    <h4 className={`text-sm font-bold ${textPrimary}`}>WhatsApp Business Cloud API</h4>
                    <span className="text-xs text-slate-400">Meta Official Partner Tier 2</span>
                  </div>
                </div>
                <span className="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-medium">
                  Healthy
                </span>
              </div>
              <div className="space-y-2 text-xs text-slate-500 pt-3 border-t border-slate-100 dark:border-slate-700">
                <div className="flex justify-between">
                  <span>Daily Messaging Tier:</span>
                  <span className="font-semibold text-slate-700 dark:text-slate-300">10,000 conversations/day</span>
                </div>
                <div className="flex justify-between">
                  <span>Webhook Health:</span>
                  <span className="font-semibold text-emerald-600">Connected (99.98% SLA)</span>
                </div>
                <div className="flex justify-between">
                  <span>Synced Templates:</span>
                  <span className="font-semibold text-slate-700 dark:text-slate-300">14 Meta Approved</span>
                </div>
              </div>
            </div>

            <div className={`p-5 rounded-xl border shadow-sm ${cardBg}`}>
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2">
                  <div className="p-2 rounded-lg bg-blue-100 text-blue-600">
                    <Mail className="w-5 h-5" />
                  </div>
                  <div>
                    <h4 className={`text-sm font-bold ${textPrimary}`}>Amazon SES / Enterprise SMTP</h4>
                    <span className="text-xs text-slate-400">Dedicated IP Warmup Active</span>
                  </div>
                </div>
                <span className="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-medium">
                  Healthy
                </span>
              </div>
              <div className="space-y-2 text-xs text-slate-500 pt-3 border-t border-slate-100 dark:border-slate-700">
                <div className="flex justify-between">
                  <span>Send Rate Quota:</span>
                  <span className="font-semibold text-slate-700 dark:text-slate-300">50 emails/sec (50k/day)</span>
                </div>
                <div className="flex justify-between">
                  <span>Bounce Rate:</span>
                  <span className="font-semibold text-emerald-600">0.42% (Optimal &lt; 2%)</span>
                </div>
                <div className="flex justify-between">
                  <span>SPF / DKIM / DMARC:</span>
                  <span className="font-semibold text-emerald-600">100% Verified</span>
                </div>
              </div>
            </div>

            <div className={`p-5 rounded-xl border shadow-sm ${cardBg}`}>
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2">
                  <div className="p-2 rounded-lg bg-purple-100 text-purple-600">
                    <PhoneCall className="w-5 h-5" />
                  </div>
                  <div>
                    <h4 className={`text-sm font-bold ${textPrimary}`}>India TRAI SMS (DLT Gateway)</h4>
                    <span className="text-xs text-slate-400">Gupshup / Twilio Enterprise</span>
                  </div>
                </div>
                <span className="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-medium">
                  Healthy
                </span>
              </div>
              <div className="space-y-2 text-xs text-slate-500 pt-3 border-t border-slate-100 dark:border-slate-700">
                <div className="flex justify-between">
                  <span>Principal Entity (PE) ID:</span>
                  <span className="font-mono text-slate-700 dark:text-slate-300">14015523491823</span>
                </div>
                <div className="flex justify-between">
                  <span>Header / Sender ID:</span>
                  <span className="font-semibold text-slate-700 dark:text-slate-300">APEXIN (Transactional)</span>
                </div>
                <div className="flex justify-between">
                  <span>TRAI Compliance:</span>
                  <span className="font-semibold text-emerald-600">DLT Verified</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* TAB 6: CONSENT & DND REGISTRY */}
      {activeTab === 'compliance' && (
        <div className="space-y-6">
          <div className={`p-5 rounded-xl border shadow-sm ${cardBg}`}>
            <h3 className={`text-base font-bold ${textPrimary} mb-1`}>Real-Time Unsubscribe & Suppression List</h3>
            <p className={`text-xs ${textSecondary} mb-4`}>
              Recipients who requested STOP or opted out are automatically suppressed across all channels to avoid penalties.
            </p>

            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead>
                  <tr className="border-b border-slate-200 dark:border-slate-700 text-slate-400 uppercase">
                    <th className="py-2">Identifier / Address</th>
                    <th className="py-2">Channel</th>
                    <th className="py-2">Reason</th>
                    <th className="py-2">Suppressed At</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {suppressions.length === 0 ? (
                    <tr>
                      <td colSpan={4} className="py-4 text-center text-slate-400">
                        No active suppressions recorded.
                      </td>
                    </tr>
                  ) : (
                    suppressions.map((s: any) => (
                      <tr key={s.id}>
                        <td className="py-2.5 font-mono text-slate-700 dark:text-slate-300">{s.identifier}</td>
                        <td className="py-2.5 uppercase">{s.channel}</td>
                        <td className="py-2.5 text-slate-500">{s.reason || 'User requested STOP'}</td>
                        <td className="py-2.5 text-slate-400">{new Date(s.suppressed_at || s.created_at).toLocaleString()}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 7: LIVE CAMPAIGN ANALYTICS & ATTRIBUTION */}
      {activeTab === 'analytics' && selectedCampaignForAnalytics !== null && (
        <div className="space-y-6">
          {isLoadingAnalytics ? (
            <div className="py-12 text-center text-slate-400">Calculating analytics & attribution...</div>
          ) : analytics ? (
            <>
              {/* Analytics Header */}
              <div className={`p-6 rounded-xl border shadow-sm ${cardBg}`}>
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                  <div>
                    <span className="text-xs uppercase font-semibold text-indigo-500">
                      Campaign Performance #{analytics.campaign_id}
                    </span>
                    <h2 className={`text-xl font-bold ${textPrimary}`}>{analytics.name}</h2>
                    <span className="text-xs text-slate-400">Channel: {analytics.channel} • Status: {analytics.status}</span>
                  </div>

                  <div className="flex items-center gap-4">
                    <div className="text-right">
                      <div className="text-xs text-slate-400">Total Spend</div>
                      <div className={`text-lg font-bold ${textPrimary}`}>₹{analytics.revenue_attribution?.spend_amount || 0}</div>
                    </div>
                    <div className="text-right">
                      <div className="text-xs text-slate-400">Attributed Revenue</div>
                      <div className="text-lg font-bold text-emerald-500">₹{analytics.revenue_attribution?.attributed_revenue || 0}</div>
                    </div>
                  </div>
                </div>

                {/* Conversion Funnel */}
                <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 mt-6 pt-6 border-t border-slate-100 dark:border-slate-700 text-center">
                  <div className="p-3 rounded-lg bg-slate-50 dark:bg-slate-900">
                    <div className="text-xs text-slate-400">Audience</div>
                    <div className={`text-base font-bold ${textPrimary}`}>{analytics.funnel?.audience_size || 0}</div>
                  </div>
                  <div className="p-3 rounded-lg bg-slate-50 dark:bg-slate-900">
                    <div className="text-xs text-slate-400">Sent</div>
                    <div className="text-base font-bold text-indigo-500">{analytics.funnel?.sent || 0}</div>
                  </div>
                  <div className="p-3 rounded-lg bg-slate-50 dark:bg-slate-900">
                    <div className="text-xs text-slate-400">Delivered</div>
                    <div className="text-base font-bold text-emerald-500">{analytics.funnel?.delivered || 0}</div>
                    <div className="text-[10px] text-slate-400 mt-0.5">{analytics.rates?.delivery_rate || 0}%</div>
                  </div>
                  <div className="p-3 rounded-lg bg-slate-50 dark:bg-slate-900">
                    <div className="text-xs text-slate-400">Read / Opens</div>
                    <div className="text-base font-bold text-cyan-500">{analytics.funnel?.read || 0}</div>
                    <div className="text-[10px] text-slate-400 mt-0.5">{analytics.rates?.read_rate || 0}%</div>
                  </div>
                  <div className="p-3 rounded-lg bg-slate-50 dark:bg-slate-900">
                    <div className="text-xs text-slate-400">Replies</div>
                    <div className="text-base font-bold text-amber-500">{analytics.funnel?.replied || 0}</div>
                    <div className="text-[10px] text-slate-400 mt-0.5">{analytics.rates?.reply_rate || 0}%</div>
                  </div>
                  <div className="p-3 rounded-lg bg-slate-50 dark:bg-slate-900">
                    <div className="text-xs text-slate-400">Failed</div>
                    <div className="text-base font-bold text-rose-500">{analytics.funnel?.failed || 0}</div>
                  </div>
                  <div className="p-3 rounded-lg bg-slate-50 dark:bg-slate-900">
                    <div className="text-xs text-slate-400">Unsubscribed</div>
                    <div className="text-base font-bold text-slate-500">{analytics.funnel?.unsubscribed || 0}</div>
                  </div>
                </div>
              </div>
            </>
          ) : (
            <div className="py-12 text-center text-slate-400">Select a campaign to view analytics.</div>
          )}
        </div>
      )}

      {/* CREATE CAMPAIGN MODAL */}
      {isCampaignModalOpen && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className={`w-full max-w-2xl rounded-2xl p-6 shadow-xl border ${cardBg}`}>
            <h2 className={`text-xl font-bold ${textPrimary} mb-4`}>Create Marketing Campaign</h2>
            <form onSubmit={handleCreateCampaign} className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Campaign Name *</label>
                  <input
                    type="text"
                    required
                    value={campaignForm.name}
                    onChange={(e) => setCampaignForm({ ...campaignForm, name: e.target.value })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border outline-none ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                    placeholder="e.g. Diwali Mega Blast 2026"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Channel *</label>
                  <select
                    value={campaignForm.channel}
                    onChange={(e) => setCampaignForm({ ...campaignForm, channel: e.target.value as any })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border outline-none ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  >
                    <option value="whatsapp">WhatsApp Business Platform</option>
                    <option value="email">Email Campaign</option>
                    <option value="sms">SMS (TRAI DLT Verified)</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-3 gap-4">
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Objective</label>
                  <select
                    value={campaignForm.objective}
                    onChange={(e) => setCampaignForm({ ...campaignForm, objective: e.target.value })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border outline-none ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  >
                    <option value="lead_generation">Lead Generation</option>
                    <option value="retention">Customer Retention</option>
                    <option value="product_launch">Product Launch</option>
                    <option value="festive_promotion">Festive Promotion</option>
                    <option value="re_engagement">Re-engagement</option>
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Budget (INR ₹)</label>
                  <input
                    type="number"
                    value={campaignForm.budget}
                    onChange={(e) => setCampaignForm({ ...campaignForm, budget: Number(e.target.value) })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border outline-none ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  />
                </div>

                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Target Segment</label>
                  <select
                    value={campaignForm.segment_id}
                    onChange={(e) => setCampaignForm({ ...campaignForm, segment_id: e.target.value })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border outline-none ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  >
                    <option value="">Choose audience segment...</option>
                    {segments.map((s) => (
                      <option key={s.id} value={s.id}>{s.name} ({s.cached_count} contacts)</option>
                    ))}
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1">Message Content</label>
                <textarea
                  rows={3}
                  value={campaignForm.content}
                  onChange={(e) => setCampaignForm({ ...campaignForm, content: e.target.value })}
                  className={`w-full px-3 py-2 text-sm rounded-lg border outline-none ${
                    isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                  }`}
                  placeholder="Use {{first_name}}, {{company}}, {{city}} tokens..."
                />
              </div>

              <div className="flex items-center gap-6 pt-2">
                <label className="flex items-center gap-2 text-xs text-slate-400 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={campaignForm.business_hours_only}
                    onChange={(e) => setCampaignForm({ ...campaignForm, business_hours_only: e.target.checked })}
                    className="rounded text-indigo-600"
                  />
                  Business Hours Only (09:00 - 20:00 IST)
                </label>

                <label className="flex items-center gap-2 text-xs text-slate-400">
                  Frequency Cap (days):
                  <input
                    type="number"
                    min="0"
                    max="30"
                    value={campaignForm.frequency_cap_days}
                    onChange={(e) => setCampaignForm({ ...campaignForm, frequency_cap_days: Number(e.target.value) })}
                    className={`w-16 px-2 py-1 text-xs rounded border outline-none ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  />
                </label>
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-700">
                <button
                  type="button"
                  onClick={() => setIsCampaignModalOpen(false)}
                  className="px-4 py-2 text-sm text-slate-400 hover:text-slate-200"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createCampaignMutation.isPending}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded-lg font-medium"
                >
                  Save Draft
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* CREATE TEMPLATE MODAL */}
      {isTemplateModalOpen && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className={`w-full max-w-xl rounded-2xl p-6 shadow-xl border ${cardBg}`}>
            <div className="flex items-center justify-between mb-4">
              <h2 className={`text-xl font-bold ${textPrimary}`}>Multi-Channel Template Studio</h2>
              <button
                type="button"
                onClick={handleAiDraftTemplate}
                disabled={generateAiTemplateMutation.isPending}
                className="flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-xs rounded-lg font-medium hover:opacity-90 transition-all shadow-sm"
              >
                <Sparkles className="w-3.5 h-3.5" />
                {generateAiTemplateMutation.isPending ? 'Drafting...' : 'Draft with AI'}
              </button>
            </div>

            <form onSubmit={handleCreateTemplate} className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Template Name *</label>
                  <input
                    type="text"
                    required
                    value={templateForm.name}
                    onChange={(e) => setTemplateForm({ ...templateForm, name: e.target.value })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border outline-none ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                    placeholder="e.g. Inbound Demo Invite"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Channel *</label>
                  <select
                    value={templateForm.channel}
                    onChange={(e) => setTemplateForm({ ...templateForm, channel: e.target.value as any })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border outline-none ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  >
                    <option value="whatsapp">WhatsApp Template</option>
                    <option value="email">Email Template</option>
                    <option value="sms">SMS Template (TRAI DLT)</option>
                  </select>
                </div>
              </div>

              {templateForm.channel === 'sms' && (
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">TRAI DLT Content Template ID *</label>
                  <input
                    type="text"
                    value={templateForm.dlt_template_id}
                    onChange={(e) => setTemplateForm({ ...templateForm, dlt_template_id: e.target.value })}
                    className={`w-full px-3 py-2 text-sm font-mono rounded-lg border outline-none ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                    placeholder="1407168923412098471"
                  />
                </div>
              )}

              {templateForm.channel === 'email' && (
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Subject Line</label>
                  <input
                    type="text"
                    value={templateForm.subject}
                    onChange={(e) => setTemplateForm({ ...templateForm, subject: e.target.value })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border outline-none ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                    placeholder="e.g. Transform your sales operations with {{product}}"
                  />
                </div>
              )}

              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1">Content Body</label>
                <textarea
                  rows={4}
                  required
                  value={templateForm.content}
                  onChange={(e) => setTemplateForm({ ...templateForm, content: e.target.value })}
                  className={`w-full px-3 py-2 text-sm rounded-lg border outline-none ${
                    isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                  }`}
                  placeholder="Insert tokens like {{first_name}}, {{company}}, {{phone}}..."
                />
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-700">
                <button
                  type="button"
                  onClick={() => setIsTemplateModalOpen(false)}
                  className="px-4 py-2 text-sm text-slate-400 hover:text-slate-200"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createTemplateMutation.isPending}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded-lg font-medium"
                >
                  Save Template
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* REJECT CAMPAIGN MODAL */}
      {isRejectModalOpen && selectedCampaignForReject && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className={`w-full max-w-md rounded-2xl p-6 shadow-xl border ${cardBg}`}>
            <h3 className={`text-lg font-bold ${textPrimary} mb-2`}>Reject Campaign Draft</h3>
            <p className={`text-xs ${textSecondary} mb-4`}>
              Provide actionable feedback for the marketing executive detailing what needs revision.
            </p>
            <textarea
              rows={3}
              required
              value={rejectionReason}
              onChange={(e) => setRejectionReason(e.target.value)}
              className={`w-full px-3 py-2 text-sm rounded-lg border outline-none mb-4 ${
                isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
              }`}
              placeholder="e.g. Budget exceeds quarterly limit or missing DLT registration ID..."
            />
            <div className="flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setIsRejectModalOpen(false)}
                className="px-4 py-2 text-sm text-slate-400 hover:text-slate-200"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleRejectCampaign}
                disabled={rejectMutation.isPending || !rejectionReason.trim()}
                className="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-sm rounded-lg font-medium"
              >
                Confirm Rejection
              </button>
            </div>
          </div>
        </div>
      )}

      {/* TEST SEND MODAL */}
      {isTestSendModalOpen && selectedCampaignForTest && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className={`w-full max-w-md rounded-2xl p-6 shadow-xl border ${cardBg}`}>
            <h3 className={`text-lg font-bold ${textPrimary} mb-2`}>Test Campaign Dispatch</h3>
            <p className={`text-xs ${textSecondary} mb-4`}>
              Send a test broadcast of <strong>{selectedCampaignForTest.name}</strong> to verify template formatting.
            </p>
            <input
              type="text"
              required
              placeholder={selectedCampaignForTest.channel === 'email' ? 'your.email@domain.com' : '+91 98765 43210'}
              value={testRecipientAddress}
              onChange={(e) => setTestRecipientAddress(e.target.value)}
              className={`w-full px-3 py-2 text-sm rounded-lg border outline-none mb-4 ${
                isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
              }`}
            />
            <div className="flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setIsTestSendModalOpen(false)}
                className="px-4 py-2 text-sm text-slate-400 hover:text-slate-200"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleTestSend}
                disabled={testSendMutation.isPending || !testRecipientAddress.trim()}
                className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded-lg font-medium"
              >
                Send Test
              </button>
            </div>
          </div>
        </div>
      )}

      {/* SAMPLE PREVIEW MODAL */}
      {selectedTemplateForPreview && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className={`w-full max-w-lg rounded-2xl p-6 shadow-xl border ${cardBg}`}>
            <div className="flex items-center justify-between mb-4">
              <h3 className={`text-lg font-bold ${textPrimary}`}>Template Live Preview</h3>
              <button
                onClick={() => setSelectedTemplateForPreview(null)}
                className="text-slate-400 hover:text-slate-200"
              >
                ✕
              </button>
            </div>
            <div className="space-y-3">
              <div className="text-xs text-slate-400">
                Simulated with sample lead data (Rahul Sharma, Apex Enterprises)
              </div>
              {selectedTemplateForPreview.data?.rendered_subject && (
                <div className="p-2.5 rounded bg-slate-100 dark:bg-slate-900 text-sm font-semibold">
                  {selectedTemplateForPreview.data.rendered_subject}
                </div>
              )}
              <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-sm whitespace-pre-line text-slate-700 dark:text-slate-300">
                {selectedTemplateForPreview.data?.rendered_content}
              </div>
            </div>
            <div className="flex justify-end mt-4">
              <button
                onClick={() => setSelectedTemplateForPreview(null)}
                className="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-xs rounded-lg font-medium"
              >
                Close Preview
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default MarketingCampaignsView;
