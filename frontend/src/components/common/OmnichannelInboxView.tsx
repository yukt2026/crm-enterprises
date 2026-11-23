import React, { useState, useEffect, useRef } from 'react';
import {
  Inbox,
  MessageSquare,
  Mail,
  MessageCircle,
  PhoneCall,
  Globe,
  Search,
  Filter,
  UserCheck,
  Users2,
  Clock,
  Sparkles,
  Bot,
  User,
  Paperclip,
  Send,
  MoreVertical,
  Check,
  CheckCheck,
  AlertCircle,
  ShieldCheck,
  Settings,
  Plus,
  RefreshCw,
  Trash2,
  Eye,
  Sliders,
  ChevronRight,
  ChevronLeft,
  ExternalLink,
  ChevronDown,
  Building2,
  Calendar,
  Layers,
  Tag,
  Share2,
  Play,
  Volume2,
  Code,
  Copy,
  X,
  Facebook,
  Instagram,
  BarChart2,
  Zap,
  History,
  CheckCircle2,
  XCircle,
  ShieldAlert,
  Wand2,
  Lock,
  Unlock,
  Archive,
  ArchiveRestore,
  FileText,
  AlertTriangle,
  Activity,
  CheckCircle,
  Radio,
  CornerDownRight
} from 'lucide-react';
import {
  useOmnichannelConversations,
  useOmnichannelConversation,
  useSendOmnichannelMessage,
  useAssignOmnichannelConversation,
  useUpdateOmnichannelStatus,
  useToggleOmnichannelAi,
  useMarkOmnichannelRead,
  useAddOmnichannelNote,
  useUnifiedTimeline,
  useEmailAccounts,
  useSaveEmailAccount,
  useSmsConfigs,
  useSaveSmsConfig,
  useWebChatWidgets,
  useSaveWebChatWidget,
  useCommunicationSuppressions,
  useAddCommunicationSuppression,
  useRemoveCommunicationSuppression,
  usePendingIdentityMerges,
  useReviewIdentityMerge,
  useAutoAssignOmnichannelConversation,
  useOmnichannelAssignmentHistory,
  useOmnichannelAnalytics,
  useOmnichannelConsents,
  useUpdateOmnichannelConsent,
  useOmnichannelQuickReplies,
  useCreateOmnichannelQuickReply,
  useDeleteOmnichannelQuickReply,
  useTelegramAccounts,
  useSaveTelegramAccount,
  useDisconnectTelegramAccount,
  useOmnichannelChannelSettings,
  useSaveOmnichannelChannelSettings,
  useTestOmnichannelConnection,
  useOmnichannelChannelHealth,
  useLockOmnichannelConversation,
  useUnlockOmnichannelConversation,
  useArchiveOmnichannelConversation,
  useOmnichannelCollision,
  useOmnichannelRoutingRules,
  useSaveOmnichannelRoutingRule,
  useDeleteOmnichannelRoutingRule,
  useOmnichannelSlaPolicies,
  useSaveOmnichannelSlaPolicy,
  useDeleteOmnichannelSlaPolicy,
  useRecalculateOmnichannelSla,
  useOmnichannelDetailedReports
} from '../../hooks/useOmnichannelInbox';
import { api } from '../../api/client';
import { useCRMStore } from '../../store/crmStore';
import {
  OmnichannelConversationItem,
  OmnichannelMessageItem,
  EmailAccountItem,
  SmsConfigurationItem,
  WebsiteChatWidgetItem,
  CommunicationSuppressionItem,
  IdentityMergePendingItem,
  OmnichannelAnalyticsData,
  OmnichannelQuickReplyItem,
  CustomerCommunicationConsentItem,
  ConversationAssignmentLogItem,
  TelegramAccountItem,
  OmnichannelChannelSettingItem,
  OmnichannelRoutingRuleItem,
  OmnichannelSlaPolicyItem,
  OmnichannelAgentPresenceItem,
  OmnichannelDetailedReportsData
} from '../../api/types';

interface OmnichannelInboxViewProps {
  theme?: 'light' | 'dark';
}

export default function OmnichannelInboxView({ theme = 'light' }: OmnichannelInboxViewProps) {
  // Navigation filters
  const [selectedChannel, setSelectedChannel] = useState<string>('all');
  const [filterScope, setFilterScope] = useState<'all' | 'my' | 'team' | 'unassigned' | 'priority' | 'archived'>('all');
  const [statusFilter, setStatusFilter] = useState<'open' | 'pending' | 'resolved'>('open');
  const [searchQuery, setSearchQuery] = useState<string>('');

  // Selected conversation
  const [activeConversationId, setActiveConversationId] = useState<number | null>(null);
  const selectedConversationId = activeConversationId;
  const setSelectedConversationId = setActiveConversationId;

  // Queries
  const { data: convsData, isLoading: loadingConvs } = useOmnichannelConversations({
    channel: selectedChannel !== 'all' ? selectedChannel : undefined,
    filter: filterScope === 'archived' ? 'all' : filterScope,
    status: statusFilter,
    priority: filterScope === 'priority' ? 'high' : undefined,
    search: searchQuery || undefined,
  });

  const rawConversations = convsData?.conversations || [];
  const conversations = rawConversations.filter(c => {
    if (filterScope === 'archived') return c.is_archived === true;
    if (filterScope === 'priority') return c.priority === 'high' || c.priority === 'urgent';
    return !c.is_archived;
  });

  const stats = convsData?.stats || {
    all: 0,
    whatsapp: 0,
    facebook: 0,
    instagram: 0,
    telegram: 0,
    email: 0,
    sms: 0,
    call: 0,
    webchat: 0,
    unassigned: 0,
    my: 0,
    resolved: 0,
  };

  const { data: activeConversation, isLoading: loadingActiveConv } = useOmnichannelConversation(activeConversationId);

  // Unified timeline query for active conversation's customer or lead
  const { data: unifiedTimeline = [] } = useUnifiedTimeline({
    lead_id: activeConversation?.lead_id ?? undefined,
    customer_id: activeConversation?.customer_id ?? undefined,
    contact_id: activeConversation?.contact_id ?? undefined,
  });

  // Settings & Merges Queries
  const { data: emailAccounts = [] } = useEmailAccounts();
  const { data: smsConfigs = [] } = useSmsConfigs();
  const { data: webChatWidgets = [] } = useWebChatWidgets();
  const { data: suppressions = [] } = useCommunicationSuppressions();
  const { data: pendingMerges = [] } = usePendingIdentityMerges();

  // Store users, teams, and current user
  const users = useCRMStore((s) => s.currentWorkspace?.users || []);
  const teams = useCRMStore((s) => (s as any).teams || []);
  const currentUser = useCRMStore((s) => s.user);

  // Mutations
  const sendMessageMutation = useSendOmnichannelMessage();
  const assignMutation = useAssignOmnichannelConversation();
  const updateStatusMutation = useUpdateOmnichannelStatus();
  const toggleAiMutation = useToggleOmnichannelAi();
  const markReadMutation = useMarkOmnichannelRead();
  const addNoteMutation = useAddOmnichannelNote();
  const reviewMergeMutation = useReviewIdentityMerge();

  // Enterprise Omnichannel Mutations & Queries (Prompt 29 & 33)
  const autoAssignMutation = useAutoAssignOmnichannelConversation();
  const { data: assignmentHistory = [] } = useOmnichannelAssignmentHistory(activeConversationId);
  const { data: omnichannelAnalytics } = useOmnichannelAnalytics();
  const destinationForConsent = activeConversation?.phone_number || activeConversation?.customer_email || undefined;
  const { data: customerConsents = [] } = useOmnichannelConsents({ destination: destinationForConsent });
  const updateConsentMutation = useUpdateOmnichannelConsent();
  const { data: quickReplies = [] } = useOmnichannelQuickReplies(activeConversation?.channel);
  const createQuickReplyMutation = useCreateOmnichannelQuickReply();
  const deleteQuickReplyMutation = useDeleteOmnichannelQuickReply();

  // Prompt 33 Omnichannel Communication Hub Hooks
  const lockMutation = useLockOmnichannelConversation();
  const unlockMutation = useUnlockOmnichannelConversation();
  const archiveMutation = useArchiveOmnichannelConversation();
  const recalculateSlaMutation = useRecalculateOmnichannelSla();
  const testConnectionMutation = useTestOmnichannelConnection();

  const { data: telegramAccounts = [] } = useTelegramAccounts();
  const saveTelegramMutation = useSaveTelegramAccount();
  const disconnectTelegramMutation = useDisconnectTelegramAccount();

  const { data: channelSettings = [] } = useOmnichannelChannelSettings();
  const saveChannelSettingMutation = useSaveOmnichannelChannelSettings();
  const { data: channelHealth = {} } = useOmnichannelChannelHealth();

  const { data: routingRules = [] } = useOmnichannelRoutingRules();
  const saveRoutingRuleMutation = useSaveOmnichannelRoutingRule();
  const deleteRoutingRuleMutation = useDeleteOmnichannelRoutingRule();

  const { data: slaPolicies = [] } = useOmnichannelSlaPolicies();
  const saveSlaPolicyMutation = useSaveOmnichannelSlaPolicy();
  const deleteSlaPolicyMutation = useDeleteOmnichannelSlaPolicy();

  const { data: collisionData } = useOmnichannelCollision(activeConversationId);
  const activeOtherAgents = (collisionData?.active_agents || []).filter(
    (a: any) => !currentUser || a.user_id !== currentUser.id
  );

  const saveEmailAccountMutation = useSaveEmailAccount();
  const saveSmsConfigMutation = useSaveSmsConfig();
  const saveWebChatWidgetMutation = useSaveWebChatWidget();
  const addSuppressionMutation = useAddCommunicationSuppression();
  const removeSuppressionMutation = useRemoveCommunicationSuppression();

  // Active right sidebar view
  const [rightPanelTab, setRightPanelTab] = useState<'profile' | 'timeline' | 'identity' | 'settings'>('profile');

  // Composer state
  const [messageText, setMessageText] = useState('');
  const [emailSubject, setEmailSubject] = useState('');
  const [isInternalNoteMode, setIsInternalNoteMode] = useState(false);
  const [attachmentUrls, setAttachmentUrls] = useState<Array<{ name: string; url: string; size?: number }>>([]);

  // Modals & Sub-tabs
  const [isSettingsModalOpen, setIsSettingsModalOpen] = useState(false);
  const [settingsTab, setSettingsTab] = useState<'channels' | 'telegram' | 'routing' | 'sla' | 'gateways'>('channels');
  const [isAnalyticsModalOpen, setIsAnalyticsModalOpen] = useState(false);
  const [isAssignmentHistoryOpen, setIsAssignmentHistoryOpen] = useState(false);
  const [isQuickRepliesOpen, setIsQuickRepliesOpen] = useState(false);
  const [newQuickReplyForm, setNewQuickReplyForm] = useState({ shortcut: '', title: '', content: '', channel: 'all' });
  const [isWidgetPreviewOpen, setIsWidgetPreviewOpen] = useState(false);
  const [activeWidgetForPreview, setActiveWidgetForPreview] = useState<WebsiteChatWidgetItem | null>(null);

  // New Forms for Prompt 33
  const [newTelegramForm, setNewTelegramForm] = useState({
    bot_name: 'CRM Support Bot',
    bot_username: '',
    bot_token: '',
    is_active: true,
  });

  const [newChannelSettingForm, setNewChannelSettingForm] = useState<Partial<OmnichannelChannelSettingItem>>({
    channel: 'whatsapp',
    is_active: true,
    timezone: 'Asia/Kolkata',
    working_hours_start: '09:00',
    working_hours_end: '18:00',
    working_days: [1, 2, 3, 4, 5],
  });

  const [newRoutingRuleForm, setNewRoutingRuleForm] = useState<Partial<OmnichannelRoutingRuleItem>>({
    name: 'Default Round Robin',
    priority: 10,
    channel: 'all',
    routing_type: 'round_robin',
    is_active: true,
  });

  const [newSlaPolicyForm, setNewSlaPolicyForm] = useState<Partial<OmnichannelSlaPolicyItem>>({
    name: 'Standard Priority SLA',
    priority: 'medium',
    first_response_time_minutes: 15,
    resolution_time_minutes: 240,
    pause_outside_business_hours: true,
    is_active: true,
  });

  // Forms
  const [newEmailForm, setNewEmailForm] = useState<Partial<EmailAccountItem> & { smtp_password?: string; imap_password?: string }>({
    name: 'Primary Support',
    email_address: '',
    provider: 'smtp_imap',
    smtp_host: 'smtp.mailgun.org',
    smtp_port: 587,
    smtp_encryption: 'tls',
    smtp_username: '',
    smtp_password: '',
    signature_html: '<p>Best Regards,<br>Support Team</p>',
    is_active: true,
    is_default: true,
  });

  const [newSmsForm, setNewSmsForm] = useState<Partial<SmsConfigurationItem> & { auth_token?: string }>({
    name: 'Twilio SMS Gateway',
    provider: 'twilio',
    account_sid: '',
    auth_token: '',
    sender_id: 'CRMTXN',
    dlt_entity_id: '',
    is_active: true,
    is_default: true,
  });

  const [newWidgetForm, setNewWidgetForm] = useState<Partial<WebsiteChatWidgetItem>>({
    name: 'Main Website Live Chat',
    brand_title: 'Nexus Customer Support',
    brand_color: '#4f46e5',
    welcome_message: 'Hi there! What can we help you solve today?',
    agent_availability_mode: 'hybrid',
    is_active: true,
    pre_chat_fields: ['name', 'email', 'phone', 'requirement'],
  });

  const [newSuppressionForm, setNewSuppressionForm] = useState({
    channel: 'email',
    identifier: '',
    reason: 'unsubscribe',
  });

  const [selectedMessageTag, setSelectedMessageTag] = useState<string>('');

  const messagesEndRef = useRef<HTMLDivElement>(null);

  // Auto-select first conversation if none selected
  useEffect(() => {
    if (!activeConversationId && conversations.length > 0) {
      setActiveConversationId(conversations[0].id);
    }
  }, [conversations, activeConversationId]);

  // Mark read when conversation becomes active
  useEffect(() => {
    if (activeConversationId && activeConversation && activeConversation.unread_count > 0) {
      markReadMutation.mutate(activeConversationId);
    }
    if (activeConversation?.subject) {
      setEmailSubject(activeConversation.subject.startsWith('Re: ') ? activeConversation.subject : `Re: ${activeConversation.subject}`);
    } else {
      setEmailSubject('');
    }
  }, [activeConversationId, activeConversation?.id]);

  // Scroll to bottom on message updates
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [activeConversation?.messages]);

  // Handle Send
  const handleSendMessage = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!messageText.trim() || !activeConversationId) return;

    const content = messageText.trim();
    setMessageText('');

    if (isInternalNoteMode) {
      await addNoteMutation.mutateAsync({
        id: activeConversationId,
        content,
      });
      return;
    }

    await sendMessageMutation.mutateAsync({
      conversationId: activeConversationId,
      data: {
        content,
        subject: activeConversation?.channel === 'email' ? emailSubject : undefined,
        attachments: attachmentUrls.length > 0 ? attachmentUrls : undefined,
        tag: (activeConversation?.channel === 'facebook' || activeConversation?.channel === 'instagram') ? (selectedMessageTag || undefined) : undefined,
      },
    });

    setAttachmentUrls([]);
    setSelectedMessageTag('');
  };

  // Presence heartbeat for agent collision detection (Prompt 33)
  useEffect(() => {
    if (!activeConversationId) return;
    api.sendOmnichannelPresence({ conversation_id: activeConversationId });
    const interval = setInterval(() => {
      api.sendOmnichannelPresence({ conversation_id: activeConversationId });
    }, 10000);
    return () => clearInterval(interval);
  }, [activeConversationId]);

  const cardBg = theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200';
  const innerBg = theme === 'dark' ? 'bg-slate-800/60 border-slate-700/60' : 'bg-slate-50 border-slate-200';
  const textMuted = theme === 'dark' ? 'text-slate-400' : 'text-slate-500';
  const textPrimary = theme === 'dark' ? 'text-white' : 'text-slate-900';
  const borderSubtle = theme === 'dark' ? 'border-slate-800' : 'border-slate-200';

  const channelIcons: Record<string, { icon: any; color: string; bg: string; label: string }> = {
    whatsapp: { icon: MessageSquare, color: 'text-emerald-500', bg: 'bg-emerald-500/10 text-emerald-600', label: 'WhatsApp' },
    facebook: { icon: Facebook, color: 'text-blue-600', bg: 'bg-blue-600/10 text-blue-600', label: 'Messenger' },
    instagram: { icon: Instagram, color: 'text-pink-600', bg: 'bg-pink-600/10 text-pink-600', label: 'Instagram' },
    telegram: { icon: Send, color: 'text-sky-500', bg: 'bg-sky-500/10 text-sky-600', label: 'Telegram' },
    email: { icon: Mail, color: 'text-amber-500', bg: 'bg-amber-500/10 text-amber-600', label: 'Email' },
    sms: { icon: MessageCircle, color: 'text-purple-500', bg: 'bg-purple-500/10 text-purple-600', label: 'SMS' },
    call: { icon: PhoneCall, color: 'text-emerald-500', bg: 'bg-emerald-500/10 text-emerald-600', label: 'Voice' },
    webchat: { icon: Globe, color: 'text-indigo-500', bg: 'bg-indigo-500/10 text-indigo-600', label: 'Website Chat' },
  };

  return (
    <div className="flex flex-col h-[calc(100vh-6.5rem)] space-y-3">
      {/* Top Bar: Channel Filter Ribbons & Inbox Controls */}
      <div className={`p-3 rounded-2xl border ${cardBg} shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3`}>
        {/* Channel Navigation Pills */}
        <div className="flex items-center gap-1.5 overflow-x-auto pb-1 md:pb-0 scrollbar-none">
          <button
            onClick={() => setSelectedChannel('all')}
            className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl transition ${
              selectedChannel === 'all'
                ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900 shadow-sm'
                : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'
            }`}
          >
            <Inbox className="w-3.5 h-3.5" />
            <span>All Channels</span>
            <span className="ml-1 px-1.5 py-0.2 rounded-full text-[10px] bg-slate-200 dark:bg-slate-700 font-mono">
              {stats.all}
            </span>
          </button>

          {(['whatsapp', 'facebook', 'instagram', 'telegram', 'email', 'sms', 'webchat', 'call'] as const).map((ch) => {
            const chMeta = channelIcons[ch] || channelIcons.whatsapp;
            const ChIcon = chMeta.icon;
            const count = (stats as any)[ch] || 0;
            const isSelected = selectedChannel === ch;

            return (
              <button
                key={ch}
                onClick={() => setSelectedChannel(ch)}
                className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl transition whitespace-nowrap ${
                  isSelected
                    ? `${chMeta.bg} ring-1 ring-current font-bold shadow-sm`
                    : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'
                }`}
              >
                <ChIcon className={`w-3.5 h-3.5 ${chMeta.color}`} />
                <span>{chMeta.label}</span>
                {count > 0 && (
                  <span className="ml-1 px-1.5 py-0.2 rounded-full text-[10px] bg-slate-200/80 dark:bg-slate-700/80 font-mono">
                    {count}
                  </span>
                )}
              </button>
            );
          })}
        </div>

        {/* Global Settings & Channel Integrations Trigger */}
        <div className="flex items-center gap-2 flex-shrink-0">
          {pendingMerges.length > 0 && (
            <button
              onClick={() => setRightPanelTab('identity')}
              className="flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 text-xs font-medium hover:bg-amber-500/20 transition"
              title="Identity Merges Pending Review"
            >
              <AlertCircle className="w-3.5 h-3.5 text-amber-500 animate-pulse" />
              <span>{pendingMerges.length} Review</span>
            </button>
          )}

          <button
            onClick={() => setIsAnalyticsModalOpen(true)}
            className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:hover:bg-indigo-900/40 text-xs font-semibold text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 transition shadow-xs"
          >
            <BarChart2 className="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" />
            <span>Omnichannel Analytics</span>
          </button>

          <button
            onClick={() => setIsSettingsModalOpen(true)}
            className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-medium text-slate-700 dark:text-slate-300 transition"
          >
            <Settings className="w-3.5 h-3.5 text-slate-500" />
            <span>Channel Settings</span>
          </button>
        </div>
      </div>

      {/* Main Omnichannel Layout: 3-Column Split */}
      <div className="grid grid-cols-1 md:grid-cols-12 gap-3 flex-1 min-h-0">
        {/* LEFT COLUMN: Conversation Threads List */}
        <div className={`${selectedConversationId ? 'hidden md:flex' : 'flex'} md:col-span-4 lg:col-span-3 rounded-2xl border ${cardBg} shadow-sm flex-col min-h-0 overflow-hidden`}>
          {/* Sub-filter tabs & Search bar */}
          <div className="p-3 border-b border-slate-100 dark:border-slate-800 space-y-2">
            <div className="flex items-center justify-between text-xs font-medium">
              <div className="flex items-center gap-1 bg-slate-100 dark:bg-slate-800 p-0.5 rounded-lg overflow-x-auto">
                <button
                  onClick={() => setFilterScope('all')}
                  className={`px-2 py-1 rounded-md text-[11px] transition ${filterScope === 'all' ? 'bg-white dark:bg-slate-900 shadow-xs font-bold text-slate-900 dark:text-white' : 'text-slate-500'}`}
                >
                  All
                </button>
                <button
                  onClick={() => setFilterScope('my')}
                  className={`px-2 py-1 rounded-md text-[11px] transition ${filterScope === 'my' ? 'bg-white dark:bg-slate-900 shadow-xs font-bold text-slate-900 dark:text-white' : 'text-slate-500'}`}
                >
                  My
                </button>
                <button
                  onClick={() => setFilterScope('unassigned')}
                  className={`px-2 py-1 rounded-md text-[11px] transition ${filterScope === 'unassigned' ? 'bg-white dark:bg-slate-900 shadow-xs font-bold text-slate-900 dark:text-white' : 'text-slate-500'}`}
                >
                  Queue
                </button>
                <button
                  onClick={() => setFilterScope('priority')}
                  className={`px-2 py-1 rounded-md text-[11px] transition ${filterScope === 'priority' ? 'bg-white dark:bg-slate-900 shadow-xs font-bold text-amber-600 dark:text-amber-400' : 'text-slate-500'}`}
                >
                  Priority
                </button>
                <button
                  onClick={() => setFilterScope('archived')}
                  className={`px-2 py-1 rounded-md text-[11px] transition ${filterScope === 'archived' ? 'bg-white dark:bg-slate-900 shadow-xs font-bold text-indigo-600 dark:text-indigo-400' : 'text-slate-500'}`}
                >
                  Archived
                </button>
              </div>

              {/* Status Picker (Open, Pending, Resolved) */}
              <div className="flex items-center gap-1">
                {(['open', 'pending', 'resolved'] as const).map((st) => (
                  <button
                    key={st}
                    onClick={() => setStatusFilter(st)}
                    className={`px-2 py-0.5 text-[10px] uppercase font-semibold rounded ${
                      statusFilter === st
                        ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300'
                        : 'text-slate-400 hover:text-slate-700'
                    }`}
                  >
                    {st}
                  </button>
                ))}
              </div>
            </div>

            {/* Search Input */}
            <div className="relative">
              <Search className="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" />
              <input
                type="text"
                placeholder="Search name, email, phone..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className={`w-full pl-8 pr-3 py-1.5 text-xs rounded-xl border ${innerBg} ${textPrimary} focus:outline-none focus:ring-1 focus:ring-indigo-500`}
              />
            </div>
          </div>

          {/* Conversations Scrollable List */}
          <div className="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/60">
            {loadingConvs ? (
              <div className="py-12 text-center text-xs text-slate-400 flex items-center justify-center gap-2">
                <RefreshCw className="w-4 h-4 animate-spin text-indigo-500" />
                <span>Loading unified inbox...</span>
              </div>
            ) : conversations.length === 0 ? (
              <div className="py-12 text-center text-xs text-slate-400 p-4">
                No {statusFilter} conversations matching current filters.
              </div>
            ) : (
              conversations.map((conv) => {
                const isSelected = activeConversationId === conv.id;
                const ch = conv.channel || 'whatsapp';
                const ChMeta = channelIcons[ch] || channelIcons.whatsapp;
                const ChIcon = ChMeta.icon;

                return (
                  <div
                    key={conv.id}
                    onClick={() => setActiveConversationId(conv.id)}
                    className={`p-3 cursor-pointer transition flex items-start gap-2.5 ${
                      isSelected
                        ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border-l-4 border-indigo-600'
                        : 'hover:bg-slate-50 dark:hover:bg-slate-800/40'
                    }`}
                  >
                    {/* Channel Icon Badge */}
                    <div className={`p-2 rounded-xl flex-shrink-0 relative ${ChMeta.bg}`}>
                      <ChIcon className="w-4 h-4" />
                      {conv.locked_by_user_id && (
                        <span className="absolute -top-1 -right-1 p-0.5 bg-rose-600 text-white rounded-full text-[8px]" title="Locked">
                          <Lock className="w-2.5 h-2.5" />
                        </span>
                      )}
                    </div>

                    {/* Metadata & Message Preview */}
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center justify-between">
                        <span className={`text-xs font-semibold truncate ${textPrimary}`}>
                          {conv.customer_name || conv.customer_email || conv.phone_number}
                        </span>
                        <span className="text-[10px] text-slate-400 flex-shrink-0">
                          {conv.last_message_at ? new Date(conv.last_message_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''}
                        </span>
                      </div>

                      {/* Subject for Email, or text preview for others */}
                      {conv.subject && (
                        <div className="text-[11px] font-medium text-slate-700 dark:text-slate-300 truncate">
                          {conv.subject}
                        </div>
                      )}

                      <div className="text-[11px] text-slate-400 dark:text-slate-500 truncate mt-0.5">
                        {conv.last_message_preview || 'No messages yet'}
                      </div>

                      {/* Indicators row (AI handling, SLA, Unread, Assigned) */}
                      <div className="flex items-center flex-wrap gap-1.5 mt-1.5">
                        {conv.unread_count > 0 && (
                          <span className="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-indigo-600 text-white">
                            {conv.unread_count}
                          </span>
                        )}

                        <span className={`text-[9px] font-semibold px-1.5 py-0.2 rounded uppercase ${
                          conv.is_ai_handled
                            ? 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300'
                            : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                        }`}>
                          {conv.is_ai_handled ? 'AI' : 'Agent'}
                        </span>

                        {/* SLA Indicator */}
                        {conv.sla_status === 'breached' && (
                          <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                            Breached
                          </span>
                        )}
                        {conv.sla_status === 'warning' && (
                          <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                            SLA Warning
                          </span>
                        )}
                        {conv.sla_status === 'on_track' && (
                          <span className="text-[9px] font-semibold px-1.5 py-0.2 rounded bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                            On Track
                          </span>
                        )}

                        {conv.assigned_user && (
                          <span className="text-[10px] text-slate-400 truncate max-w-[90px]">
                            • {conv.assigned_user.name.split(' ')[0]}
                          </span>
                        )}

                        {conv.first_response_time_seconds ? (
                          <span className="text-[9px] font-mono px-1.5 py-0.2 rounded bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 font-semibold" title={`First response time: ${conv.first_response_time_seconds}s`}>
                            {Math.round(conv.first_response_time_seconds / 60)}m FRT
                          </span>
                        ) : null}

                        {conv.branch && (
                          <span className="text-[9px] font-medium px-1.5 py-0.2 rounded bg-slate-100 dark:bg-slate-800 text-slate-500">
                            {conv.branch.name}
                          </span>
                        )}
                      </div>
                    </div>
                  </div>
                );
              })
            )}
          </div>
        </div>

        {/* MIDDLE COLUMN: Message Stream & Composer */}
        <div className={`${!selectedConversationId ? 'hidden md:flex' : 'flex'} md:col-span-8 lg:col-span-6 rounded-2xl border ${cardBg} shadow-sm flex-col min-h-0 overflow-hidden`}>
          {activeConversation ? (
            <>
              {/* Conversation Top Header */}
              <div className="p-3.5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
                <div className="flex items-center gap-2.5 min-w-0">
                  <button
                    onClick={() => setSelectedConversationId(null)}
                    className="md:hidden p-1.5 -ml-1 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white rounded-lg transition"
                    title="Back to conversations"
                  >
                    <ChevronLeft className="w-5 h-5" />
                  </button>
                  <div className={`p-2 rounded-xl ${channelIcons[activeConversation.channel]?.bg || 'bg-slate-100'}`}>
                    {React.createElement(channelIcons[activeConversation.channel]?.icon || MessageSquare, {
                      className: 'w-4 h-4'
                    })}
                  </div>
                  <div className="min-w-0">
                    <div className="flex items-center gap-2">
                      <h2 className={`text-sm font-bold truncate ${textPrimary}`}>
                        {activeConversation.customer_name || activeConversation.customer_email || activeConversation.phone_number}
                      </h2>
                      <span className="text-[10px] font-mono px-1.5 py-0.2 rounded bg-slate-100 dark:bg-slate-800 text-slate-500 uppercase">
                        {activeConversation.channel}
                      </span>
                    </div>
                    <div className={`text-[11px] ${textMuted} truncate flex items-center gap-2`}>
                      <span>{activeConversation.phone_number !== 'N/A' ? activeConversation.phone_number : ''} {activeConversation.customer_email ? `• ${activeConversation.customer_email}` : ''}</span>
                      {activeConversation.branch && (
                        <span className="text-[10px] font-medium text-slate-400">({activeConversation.branch.name})</span>
                      )}
                    </div>
                  </div>
                </div>

                {/* Right controls: SLA Status, Lock/Unlock, Archive, Auto-Assign, History, AI Takeover & Status picker */}
                <div className="flex items-center gap-1.5 flex-shrink-0">
                  {/* SLA Policy Badge */}
                  {activeConversation.sla_status && (
                    <span className={`text-[10px] font-bold px-2 py-0.5 rounded-md border ${
                      activeConversation.sla_status === 'breached'
                        ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border-rose-200 dark:border-rose-800'
                        : activeConversation.sla_status === 'warning'
                        ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border-amber-200 dark:border-amber-800'
                        : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
                    }`}>
                      {activeConversation.sla_status.toUpperCase()}
                    </span>
                  )}

                  {/* Lock / Unlock Toggle */}
                  {activeConversation.locked_by_user_id ? (
                    <button
                      onClick={() => unlockMutation.mutate(activeConversation.id)}
                      disabled={unlockMutation.isPending}
                      className="flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800 hover:bg-rose-100 transition"
                      title={`Locked by ${activeConversation.locked_by?.name || 'Agent'}. Click to unlock.`}
                    >
                      <Lock className="w-3.5 h-3.5 text-rose-600" />
                      <span className="hidden sm:inline">Locked</span>
                    </button>
                  ) : (
                    <button
                      onClick={() => lockMutation.mutate(activeConversation.id)}
                      disabled={lockMutation.isPending}
                      className="flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-semibold border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition"
                      title="Lock conversation for your exclusive reply"
                    >
                      <Unlock className="w-3.5 h-3.5 text-slate-400" />
                      <span className="hidden sm:inline">Lock</span>
                    </button>
                  )}

                  {/* Archive Toggle */}
                  <button
                    onClick={() => archiveMutation.mutate({ id: activeConversation.id, archive: !activeConversation.is_archived })}
                    disabled={archiveMutation.isPending}
                    className="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition"
                    title={activeConversation.is_archived ? "Unarchive conversation" : "Archive conversation"}
                  >
                    {activeConversation.is_archived ? <ArchiveRestore className="w-3.5 h-3.5 text-indigo-500" /> : <Archive className="w-3.5 h-3.5" />}
                  </button>

                  <button
                    onClick={() => autoAssignMutation.mutate({ id: activeConversation.id })}
                    disabled={autoAssignMutation.isPending}
                    className="flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300 border border-violet-200 dark:border-violet-800 hover:bg-violet-100 transition"
                    title="Auto-Assign via workload round-robin"
                  >
                    <Wand2 className="w-3.5 h-3.5" />
                    <span className="hidden sm:inline">Auto-Assign</span>
                  </button>

                  <button
                    onClick={() => setIsAssignmentHistoryOpen(true)}
                    className="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition"
                    title="Assignment & Reassignment History"
                  >
                    <History className="w-3.5 h-3.5" />
                  </button>

                  <button
                    onClick={() => toggleAiMutation.mutate({
                      id: activeConversation.id,
                      isAiHandled: !activeConversation.is_ai_handled
                    })}
                    className={`flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold transition ${
                      activeConversation.is_ai_handled
                        ? 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300 hover:bg-purple-200'
                        : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'
                    }`}
                    title={activeConversation.is_ai_handled ? 'Takeover from AI agent' : 'Hand back to autonomous AI'}
                  >
                    {activeConversation.is_ai_handled ? <Bot className="w-3.5 h-3.5" /> : <User className="w-3.5 h-3.5" />}
                    <span>{activeConversation.is_ai_handled ? 'AI Active' : 'Human Agent'}</span>
                  </button>

                  <select
                    value={activeConversation.status}
                    onChange={(e) => updateStatusMutation.mutate({
                      id: activeConversation.id,
                      status: e.target.value as any
                    })}
                    className={`px-2 py-1 text-xs rounded-lg border ${innerBg} ${textPrimary} focus:outline-none font-semibold`}
                  >
                    <option value="open">Open</option>
                    <option value="pending">Pending</option>
                    <option value="resolved">Resolved</option>
                  </select>
                </div>
              </div>

              {/* Agent Collision Warning Banner (Prompt 33) */}
              {activeOtherAgents.length > 0 && (
                <div className="bg-amber-500/15 border-b border-amber-500/30 px-3 py-2 text-xs flex items-center justify-between text-amber-800 dark:text-amber-300 animate-pulse">
                  <div className="flex items-center gap-2">
                    <AlertTriangle className="w-4 h-4 text-amber-600 flex-shrink-0" />
                    <span>
                      <strong>Agent Collision Warning:</strong> {activeOtherAgents.map((a: any) => a.user?.name || `Agent #${a.user_id}`).join(', ')} {activeOtherAgents.length === 1 ? 'is' : 'are'} currently viewing this conversation!
                    </span>
                  </div>
                  <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-amber-500/20 font-bold uppercase">Active Collision</span>
                </div>
              )}

              {/* Conversation Locked Banner */}
              {activeConversation.locked_by_user_id && (
                <div className="bg-slate-100 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700 px-3 py-1.5 text-[11px] flex items-center justify-between text-slate-600 dark:text-slate-400">
                  <div className="flex items-center gap-1.5">
                    <Lock className="w-3 h-3 text-rose-500" />
                    <span>Locked by <strong>{activeConversation.locked_by?.name || `User #${activeConversation.locked_by_user_id}`}</strong> {activeConversation.locked_at ? `(${new Date(activeConversation.locked_at).toLocaleTimeString()})` : ''}</span>
                  </div>
                  <span className="text-[10px] font-medium text-slate-500">Exclusive session active</span>
                </div>
              )}

              {/* Messages / Events Thread Stream */}
              <div className="flex-1 overflow-y-auto p-4 space-y-3 bg-slate-50/60 dark:bg-slate-950/40">
                {activeConversation.subject && (
                  <div className={`p-2.5 rounded-xl border ${cardBg} shadow-xs text-xs flex items-center justify-between`}>
                    <div className="flex items-center gap-2 truncate">
                      <Mail className="w-3.5 h-3.5 text-sky-500 flex-shrink-0" />
                      <span className="font-semibold text-slate-700 dark:text-slate-300 truncate">
                        Subject: {activeConversation.subject}
                      </span>
                    </div>
                  </div>
                )}

                {activeConversation.messages && activeConversation.messages.length > 0 ? (
                  activeConversation.messages.map((msg) => {
                    const isOutbound = msg.direction === 'outbound';

                    return (
                      <div
                        key={msg.id}
                        className={`flex flex-col ${isOutbound ? 'items-end' : 'items-start'}`}
                      >
                        {/* Sender Label, Actor Badge & Channel Tag */}
                        <div className="flex items-center gap-1.5 text-[10px] text-slate-400 mb-0.5 px-1 font-medium">
                          <span>{msg.sender_identity || (isOutbound ? 'Support Agent' : 'Customer')}</span>
                          {msg.sender_actor === 'ai' ? (
                            <span className="inline-flex items-center gap-0.5 px-1 py-0.2 rounded text-[9px] bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300 font-semibold">
                              <Bot className="w-2.5 h-2.5" /> AI
                            </span>
                          ) : msg.sender_actor === 'automation' ? (
                            <span className="inline-flex items-center gap-0.5 px-1 py-0.2 rounded text-[9px] bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300 font-semibold">
                              <Zap className="w-2.5 h-2.5" /> Auto
                            </span>
                          ) : msg.sender_actor === 'human' ? (
                            <span className="inline-flex items-center gap-0.5 px-1 py-0.2 rounded text-[9px] bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300 font-semibold">
                              <User className="w-2.5 h-2.5" /> Agent
                            </span>
                          ) : null}
                          <span>•</span>
                          <span className="uppercase">{msg.channel || activeConversation.channel}</span>
                        </div>

                        {/* Bubble */}
                        <div
                          className={`max-w-[85%] rounded-2xl px-3.5 py-2.5 text-xs leading-relaxed shadow-xs ${
                            isOutbound
                              ? 'bg-indigo-600 text-white rounded-br-none'
                              : 'bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 border border-slate-200 dark:border-slate-700 rounded-bl-none'
                          }`}
                        >
                          {/* Email In-Reply-To Threading header */}
                          {msg.in_reply_to && (
                            <div className="text-[10px] opacity-75 mb-1 flex items-center gap-1 border-b border-white/20 pb-0.5">
                              <span>Threaded response</span>
                            </div>
                          )}

                          {/* Message Body Content */}
                          <div className="whitespace-pre-wrap">{msg.content}</div>

                          {/* Attachments Pills */}
                          {msg.attachments && msg.attachments.length > 0 && (
                            <div className="mt-2 space-y-1">
                              {msg.attachments.map((att, idx) => (
                                <a
                                  key={idx}
                                  href={att.url}
                                  target="_blank"
                                  rel="noreferrer"
                                  className={`flex items-center gap-1.5 p-1.5 rounded-lg text-[11px] font-medium transition ${
                                    isOutbound
                                      ? 'bg-indigo-700 text-white hover:bg-indigo-800'
                                      : 'bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-200 hover:bg-slate-200'
                                  }`}
                                >
                                  <Paperclip className="w-3 h-3" />
                                  <span className="truncate">{att.name}</span>
                                </a>
                              ))}
                            </div>
                          )}

                          {/* Time & Delivery Checkmark */}
                          <div className={`flex items-center justify-end gap-1 mt-1 text-[9px] ${isOutbound ? 'text-indigo-200' : 'text-slate-400'}`}>
                            <span>{new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                            {isOutbound && (
                              <span>
                                {msg.delivery_status === 'read' ? (
                                  <CheckCheck className="w-3 h-3 text-sky-300" />
                                ) : msg.delivery_status === 'delivered' ? (
                                  <CheckCheck className="w-3 h-3" />
                                ) : (
                                  <Check className="w-3 h-3" />
                                )}
                              </span>
                            )}
                          </div>
                        </div>
                      </div>
                    );
                  })
                ) : (
                  <div className="py-12 text-center text-xs text-slate-400">
                    No messages in this conversation thread yet.
                  </div>
                )}

                {/* Team Internal Notes stream */}
                {activeConversation.notes && activeConversation.notes.length > 0 && (
                  <div className="pt-2 border-t border-slate-200 dark:border-slate-800">
                    <span className="text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 flex items-center gap-1 mb-2">
                      <Tag className="w-3 h-3" /> Team-Only Internal Comments
                    </span>
                    {activeConversation.notes.map((note) => (
                      <div key={note.id} className="p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 mb-2">
                        <div className="flex items-center justify-between text-[10px] text-amber-700 dark:text-amber-400 font-semibold mb-1">
                          <span>{note.author?.name || 'Team Member'}</span>
                          <span>{new Date(note.created_at).toLocaleTimeString()}</span>
                        </div>
                        <div className="text-xs text-amber-900 dark:text-amber-200">{note.content}</div>
                      </div>
                    ))}
                  </div>
                )}

                <div ref={messagesEndRef} />
              </div>

              {/* Omnichannel Composer */}
              <div className="p-3 border-t border-slate-100 dark:border-slate-800 space-y-2">
                {/* Note vs Customer message mode switcher */}
                <div className="flex items-center justify-between text-xs">
                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => setIsInternalNoteMode(false)}
                      className={`text-xs font-semibold px-2 py-0.5 rounded transition ${!isInternalNoteMode ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' : 'text-slate-400'}`}
                    >
                      Reply to Customer ({activeConversation.channel.toUpperCase()})
                    </button>
                    <button
                      type="button"
                      onClick={() => setIsInternalNoteMode(true)}
                      className={`text-xs font-semibold px-2 py-0.5 rounded transition ${isInternalNoteMode ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' : 'text-slate-400'}`}
                    >
                      Internal Team Note
                    </button>

                    <button
                      type="button"
                      onClick={() => setIsQuickRepliesOpen(true)}
                      className="flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 hover:bg-amber-100 transition"
                      title="Use canned response or quick template"
                    >
                      <Zap className="w-3 h-3 text-amber-500" />
                      <span>Quick Replies ({quickReplies.length})</span>
                    </button>
                  </div>

                  <span className={`text-[11px] ${textMuted}`}>
                    {activeConversation.channel === 'sms' ? 'SMS: 160 chars/segment' : activeConversation.channel === 'email' ? 'Email threading active' : ''}
                  </span>
                </div>

                {/* Meta 24-Hour Policy Window & Tag selector if Facebook or Instagram */}
                {!isInternalNoteMode && (activeConversation.channel === 'facebook' || activeConversation.channel === 'instagram') && (
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-2 rounded-lg bg-blue-50/60 dark:bg-blue-950/20 border border-blue-200/60 dark:border-blue-900/40 text-xs">
                    <div className="flex items-center gap-1.5 text-blue-700 dark:text-blue-300">
                      <Clock className="w-3.5 h-3.5" />
                      <span>
                        {activeConversation.window_expires_at && new Date(activeConversation.window_expires_at) < new Date() ? (
                          <strong className="text-amber-600 dark:text-amber-400">24h Window Expired: Select an approved Message Tag to send.</strong>
                        ) : (
                          <span>Meta 24h Window Active: Standard replies allowed.</span>
                        )}
                      </span>
                    </div>

                    <div className="flex items-center gap-1.5">
                      <span className="text-[11px] text-gray-500">Message Tag:</span>
                      <select
                        value={selectedMessageTag}
                        onChange={(e) => setSelectedMessageTag(e.target.value)}
                        className={`text-xs px-2 py-1 rounded border ${innerBg} ${textPrimary} focus:outline-none`}
                      >
                        <option value="">None (Standard Response)</option>
                        <option value="POST_PURCHASE_UPDATE">POST_PURCHASE_UPDATE</option>
                        <option value="CONFIRMED_EVENT_UPDATE">CONFIRMED_EVENT_UPDATE</option>
                        <option value="ACCOUNT_UPDATE">ACCOUNT_UPDATE</option>
                      </select>
                    </div>
                  </div>
                )}

                {/* Email Subject line if replying via Email */}
                {!isInternalNoteMode && activeConversation.channel === 'email' && (
                  <input
                    type="text"
                    placeholder="Email Subject..."
                    value={emailSubject}
                    onChange={(e) => setEmailSubject(e.target.value)}
                    className={`w-full px-3 py-1.5 text-xs rounded-lg border ${innerBg} ${textPrimary} focus:outline-none`}
                  />
                )}

                <form onSubmit={handleSendMessage} className="flex items-end gap-2">
                  <div className="flex-1">
                    <textarea
                      rows={2}
                      placeholder={isInternalNoteMode ? "Add team comment (only visible internally)..." : `Type your reply via ${activeConversation.channel}...`}
                      value={messageText}
                      onChange={(e) => setMessageText(e.target.value)}
                      className={`w-full px-3 py-2 text-xs rounded-xl border ${
                        isInternalNoteMode
                          ? 'border-amber-300 bg-amber-50/50 dark:bg-amber-950/20 text-amber-900 dark:text-amber-100'
                          : `${cardBg} ${textPrimary}`
                      } focus:outline-none focus:ring-1 focus:ring-indigo-500`}
                    />
                  </div>

                  <button
                    type="submit"
                    disabled={!messageText.trim() || sendMessageMutation.isPending || addNoteMutation.isPending}
                    className={`px-4 py-2.5 rounded-xl text-white font-medium text-xs transition shadow-sm flex items-center gap-1.5 flex-shrink-0 ${
                      isInternalNoteMode
                        ? 'bg-amber-600 hover:bg-amber-700'
                        : 'bg-indigo-600 hover:bg-indigo-700'
                    }`}
                  >
                    {sendMessageMutation.isPending || addNoteMutation.isPending ? (
                      <RefreshCw className="w-3.5 h-3.5 animate-spin" />
                    ) : (
                      <Send className="w-3.5 h-3.5" />
                    )}
                    <span>{isInternalNoteMode ? 'Note' : 'Send'}</span>
                  </button>
                </form>
              </div>
            </>
          ) : (
            <div className="flex-1 flex flex-col items-center justify-center p-6 text-center text-slate-400">
              <Inbox className="w-12 h-12 mb-3 opacity-40 text-slate-400" />
              <h3 className={`text-sm font-semibold ${textPrimary}`}>No Conversation Selected</h3>
              <p className={`text-xs ${textMuted} mt-1 max-w-xs`}>
                Select a conversation from the left thread list to view messages, timeline events, and reply.
              </p>
            </div>
          )}
        </div>

        {/* RIGHT COLUMN: Customer Profile & Unified Timeline */}
        <div className={`hidden lg:flex lg:col-span-3 rounded-2xl border ${cardBg} shadow-sm flex-col min-h-0 overflow-hidden`}>
          {/* Tabs: Profile | Timeline | Identity */}
          <div className="p-2 border-b border-slate-100 dark:border-slate-800 flex items-center justify-around text-xs font-semibold">
            <button
              onClick={() => setRightPanelTab('profile')}
              className={`pb-1 border-b-2 transition ${rightPanelTab === 'profile' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-400'}`}
            >
              Profile
            </button>
            <button
              onClick={() => setRightPanelTab('timeline')}
              className={`pb-1 border-b-2 transition ${rightPanelTab === 'timeline' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-400'}`}
            >
              Timeline ({unifiedTimeline.length})
            </button>
            <button
              onClick={() => setRightPanelTab('identity')}
              className={`pb-1 border-b-2 transition ${rightPanelTab === 'identity' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-400'}`}
            >
              Identity
            </button>
          </div>

          <div className="flex-1 overflow-y-auto p-4 space-y-4">
            {/* TAB 1: CUSTOMER PROFILE */}
            {rightPanelTab === 'profile' && activeConversation && (
              <div className="space-y-4 text-xs">
                <div>
                  <h4 className={`font-bold text-sm ${textPrimary}`}>
                    {activeConversation.customer_name || 'Anonymous Visitor'}
                  </h4>
                  <p className={`text-xs ${textMuted}`}>{activeConversation.customer_email || 'No email registered'}</p>
                  <p className="text-xs font-mono text-slate-600 dark:text-slate-400 mt-0.5">{activeConversation.phone_number}</p>
                </div>

                {/* Assignment Controls */}
                <div className={`p-3 rounded-xl border ${innerBg} space-y-2`}>
                  <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Assignment</span>
                  <div>
                    <label className="text-[10px] text-slate-500 mb-0.5 block">Assigned Agent</label>
                    <select
                      value={activeConversation.assigned_user_id || ''}
                      onChange={(e) => assignMutation.mutate({
                        id: activeConversation.id,
                        assigned_user_id: Number(e.target.value) || null,
                        assigned_team_id: activeConversation.assigned_team_id
                      })}
                      className={`w-full px-2 py-1 text-xs rounded-lg border ${cardBg} ${textPrimary} focus:outline-none`}
                    >
                      <option value="">-- Unassigned --</option>
                      {users.map((u: any) => (
                        <option key={u.id} value={u.id}>{u.name}</option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label className="text-[10px] text-slate-500 mb-0.5 block">Assigned Sales Team</label>
                    <select
                      value={activeConversation.assigned_team_id || ''}
                      onChange={(e) => assignMutation.mutate({
                        id: activeConversation.id,
                        assigned_user_id: activeConversation.assigned_user_id,
                        assigned_team_id: Number(e.target.value) || null
                      })}
                      className={`w-full px-2 py-1 text-xs rounded-lg border ${cardBg} ${textPrimary} focus:outline-none`}
                    >
                      <option value="">-- No Team Assigned --</option>
                      {teams.map((t: any) => (
                        <option key={t.id} value={t.id}>{t.name}</option>
                      ))}
                    </select>
                  </div>

                  <div className="flex items-center gap-2 pt-1 border-t border-slate-200 dark:border-slate-700">
                    <button
                      type="button"
                      onClick={() => autoAssignMutation.mutate({ id: activeConversation.id })}
                      disabled={autoAssignMutation.isPending}
                      className="flex-1 py-1 rounded-md text-[11px] font-semibold bg-violet-600 hover:bg-violet-700 text-white flex items-center justify-center gap-1 transition"
                    >
                      <Wand2 className="w-3 h-3" />
                      <span>Auto-Assign</span>
                    </button>
                    <button
                      type="button"
                      onClick={() => setIsAssignmentHistoryOpen(true)}
                      className="px-2 py-1 rounded-md text-[11px] font-semibold border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center gap-1 transition"
                      title="View assignment audit logs"
                    >
                      <History className="w-3 h-3" />
                      <span>History</span>
                    </button>
                  </div>
                </div>

                {/* Channel Communication Consent & Suppression Status */}
                <div className={`p-3 rounded-xl border ${innerBg} space-y-2`}>
                  <div className="flex items-center justify-between">
                    <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Communication Consents</span>
                    <ShieldCheck className="w-3.5 h-3.5 text-emerald-500" />
                  </div>
                  <div className="space-y-1.5">
                    {(['whatsapp', 'sms', 'email', 'facebook', 'instagram'] as const).map((ch) => {
                      const consentRecord = customerConsents.find((c) => c.channel === ch);
                      const isOptedOut = consentRecord?.consent_status === 'opted_out' || consentRecord?.consent_status === 'revoked';
                      const chMeta = channelIcons[ch] || channelIcons.whatsapp;
                      const ChIcon = chMeta.icon;

                      return (
                        <div key={ch} className="flex items-center justify-between py-1 px-1.5 rounded-lg bg-slate-100/80 dark:bg-slate-800/80 text-[11px]">
                          <div className="flex items-center gap-1.5">
                            <ChIcon className={`w-3.5 h-3.5 ${chMeta.color}`} />
                            <span className="font-medium capitalize">{ch}</span>
                          </div>
                          <div className="flex items-center gap-1.5">
                            <span className={`text-[10px] font-semibold px-1.5 py-0.2 rounded ${
                              isOptedOut
                                ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300'
                                : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                            }`}>
                              {isOptedOut ? 'Opted Out' : 'Consented'}
                            </span>
                            <button
                              type="button"
                              onClick={() => {
                                const dest = activeConversation.phone_number || activeConversation.customer_email || 'default';
                                updateConsentMutation.mutate({
                                  channel: ch,
                                  destination: dest,
                                  consent_status: isOptedOut ? 'opted_in' : 'opted_out',
                                  consent_source: 'admin',
                                  lead_id: activeConversation.lead_id,
                                  customer_id: activeConversation.customer_id,
                                });
                              }}
                              className="text-[10px] text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 font-semibold"
                            >
                              {isOptedOut ? 'Allow' : 'Revoke'}
                            </button>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>

                {/* Linked CRM Lead Details */}
                {activeConversation.lead && (
                  <div className={`p-3 rounded-xl border ${innerBg} space-y-1`}>
                    <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Linked Lead</span>
                    <div className="font-semibold text-slate-800 dark:text-slate-200">{activeConversation.lead.name}</div>
                    <div className="text-[11px] text-slate-500">{activeConversation.lead.company_name || 'Individual'}</div>
                    <div className="mt-1">
                      <span className="px-2 py-0.5 rounded text-[10px] font-semibold bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                        Lead: {activeConversation.lead.status}
                      </span>
                    </div>
                  </div>
                )}

                {/* Financial Invoices & Balance Due (Strictly Permission Gated - Prompt 33) */}
                <div className={`p-3 rounded-xl border ${innerBg} space-y-2`}>
                  <div className="flex items-center justify-between">
                    <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Financial Records</span>
                    <FileText className="w-3.5 h-3.5 text-indigo-500" />
                  </div>

                  {activeConversation.financials ? (
                    activeConversation.financials.has_permission ? (
                      <div className="space-y-2">
                        <div className="grid grid-cols-2 gap-2 text-xs">
                          <div className={`p-2 rounded-lg border ${cardBg}`}>
                            <span className="text-[10px] text-slate-400">Total Billed</span>
                            <div className="font-bold text-slate-900 dark:text-white">
                              ${(activeConversation.financials.summary?.total_invoiced || 0).toLocaleString()}
                            </div>
                          </div>
                          <div className={`p-2 rounded-lg border ${cardBg}`}>
                            <span className="text-[10px] text-rose-500">Balance Due</span>
                            <div className="font-bold text-rose-600">
                              ${(activeConversation.financials.summary?.balance_due || 0).toLocaleString()}
                            </div>
                          </div>
                        </div>

                        {activeConversation.financials.invoices && activeConversation.financials.invoices.length > 0 ? (
                          <div className="space-y-1">
                            <span className="text-[10px] text-slate-500 font-semibold">Recent Invoices:</span>
                            {activeConversation.financials.invoices.slice(0, 3).map((inv: any) => (
                              <div key={inv.id} className="flex items-center justify-between text-[11px] p-1.5 rounded bg-slate-100 dark:bg-slate-800">
                                <span className="font-mono font-medium">{inv.invoice_number}</span>
                                <span className={`px-1.5 py-0.2 rounded text-[10px] font-bold ${
                                  inv.status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'
                                }`}>
                                  ${inv.total_amount} ({inv.status})
                                </span>
                              </div>
                            ))}
                          </div>
                        ) : (
                          <p className="text-[11px] text-slate-400">No invoices issued for this contact.</p>
                        )}
                      </div>
                    ) : (
                      <div className="p-2.5 rounded-lg bg-slate-100/80 dark:bg-slate-800/80 text-center space-y-1">
                        <div className="flex items-center justify-center gap-1.5 text-slate-500 text-xs font-semibold">
                          <Lock className="w-3.5 h-3.5 text-slate-400" />
                          <span>Financial Data Restricted</span>
                        </div>
                        <p className="text-[10px] text-slate-400">
                          {activeConversation.financials.message || 'Your role does not have permission to view customer financial records.'}
                        </p>
                      </div>
                    )
                  ) : (
                    <p className="text-[11px] text-slate-400">No financial account linked.</p>
                  )}
                </div>
              </div>
            )}

            {/* TAB 2: UNIFIED TIMELINE ACROSS ALL CHANNELS & CALLS */}
            {rightPanelTab === 'timeline' && (
              <div className="space-y-3">
                <div className="text-[10px] uppercase font-bold text-slate-400">Omnichannel History</div>
                {unifiedTimeline.length === 0 ? (
                  <div className="text-xs text-slate-400 text-center py-6">
                    No timeline events linked to this contact.
                  </div>
                ) : (
                  unifiedTimeline.map((ev) => (
                    <div key={ev.id} className={`p-2.5 rounded-xl border ${innerBg} text-xs space-y-1`}>
                      <div className="flex items-center justify-between text-[10px] text-slate-400">
                        <span className="font-semibold uppercase text-slate-600 dark:text-slate-300">{ev.title}</span>
                        <span>{new Date(ev.timestamp).toLocaleDateString()}</span>
                      </div>
                      <div className="text-slate-700 dark:text-slate-200 leading-relaxed text-[11px] line-clamp-3">
                        {ev.content}
                      </div>
                      {ev.recording_url && (
                        <div className="pt-1">
                          <audio controls src={ev.recording_url} className="h-6 w-full" />
                        </div>
                      )}
                    </div>
                  ))
                )}
              </div>
            )}

            {/* TAB 3: IDENTITY RESOLUTION & PENDING MERGES */}
            {rightPanelTab === 'identity' && (
              <div className="space-y-3">
                <div className="text-[10px] uppercase font-bold text-slate-400">Identity Matches</div>
                {pendingMerges.length === 0 ? (
                  <div className="text-xs text-slate-400 text-center py-6">
                    No uncertain identity matches pending supervisor review.
                  </div>
                ) : (
                  pendingMerges.map((pm) => (
                    <div key={pm.id} className={`p-3 rounded-xl border border-amber-200 dark:border-amber-800/60 bg-amber-50/50 dark:bg-amber-950/20 text-xs space-y-2`}>
                      <div className="font-bold text-amber-800 dark:text-amber-300">
                        Uncertain Match #{pm.id}
                      </div>
                      <p className="text-[11px] text-amber-700 dark:text-amber-400 leading-tight">
                        {pm.notes}
                      </p>
                      <div className="text-[10px] text-slate-500">
                        Confidence: <strong>{pm.confidence_score}%</strong> • Criteria: {pm.match_criteria}
                      </div>

                      {pm.status === 'pending_review' ? (
                        <div className="flex items-center gap-1.5 pt-1">
                          <button
                            onClick={() => reviewMergeMutation.mutate({ id: pm.id, decision: 'merged' })}
                            className="px-2.5 py-1 bg-emerald-600 text-white rounded text-[11px] font-medium"
                          >
                            Approve Merge
                          </button>
                          <button
                            onClick={() => reviewMergeMutation.mutate({ id: pm.id, decision: 'rejected' })}
                            className="px-2.5 py-1 border border-rose-300 text-rose-600 rounded text-[11px] font-medium"
                          >
                            Reject
                          </button>
                        </div>
                      ) : (
                        <span className="text-[10px] font-bold uppercase text-slate-500">
                          Status: {pm.status}
                        </span>
                      )}
                    </div>
                  ))
                )}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* CHANNEL CONFIGURATION & OMNICHANNEL HUB MODAL */}
      {isSettingsModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
          <div className={`w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-2xl border ${cardBg} p-6 shadow-2xl space-y-4`}>
            {/* Modal Header */}
            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
              <div className="flex items-center gap-2">
                <Settings className="w-5 h-5 text-indigo-600" />
                <div>
                  <h3 className={`font-bold text-base ${textPrimary}`}>Omnichannel Communication Hub Settings</h3>
                  <p className={`text-xs ${textMuted}`}>Configure channels, routing engine, SLA policies & gateways</p>
                </div>
              </div>
              <button onClick={() => setIsSettingsModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Modal Navigation Tabs */}
            <div className="flex items-center gap-1 border-b border-slate-200 dark:border-slate-800 pb-2 overflow-x-auto text-xs font-semibold">
              <button
                onClick={() => setSettingsTab('channels')}
                className={`px-3 py-1.5 rounded-lg transition whitespace-nowrap flex items-center gap-1.5 ${
                  settingsTab === 'channels'
                    ? 'bg-indigo-600 text-white shadow-xs'
                    : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                }`}
              >
                <Radio className="w-3.5 h-3.5" />
                <span>Channel Hub (8 Channels)</span>
              </button>
              <button
                onClick={() => setSettingsTab('telegram')}
                className={`px-3 py-1.5 rounded-lg transition whitespace-nowrap flex items-center gap-1.5 ${
                  settingsTab === 'telegram'
                    ? 'bg-indigo-600 text-white shadow-xs'
                    : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                }`}
              >
                <Send className="w-3.5 h-3.5" />
                <span>Telegram Bot</span>
              </button>
              <button
                onClick={() => setSettingsTab('routing')}
                className={`px-3 py-1.5 rounded-lg transition whitespace-nowrap flex items-center gap-1.5 ${
                  settingsTab === 'routing'
                    ? 'bg-indigo-600 text-white shadow-xs'
                    : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                }`}
              >
                <Wand2 className="w-3.5 h-3.5" />
                <span>Routing Engine ({routingRules.length})</span>
              </button>
              <button
                onClick={() => setSettingsTab('sla')}
                className={`px-3 py-1.5 rounded-lg transition whitespace-nowrap flex items-center gap-1.5 ${
                  settingsTab === 'sla'
                    ? 'bg-indigo-600 text-white shadow-xs'
                    : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                }`}
              >
                <Clock className="w-3.5 h-3.5" />
                <span>SLA Policies ({slaPolicies.length})</span>
              </button>
              <button
                onClick={() => setSettingsTab('gateways')}
                className={`px-3 py-1.5 rounded-lg transition whitespace-nowrap flex items-center gap-1.5 ${
                  settingsTab === 'gateways'
                    ? 'bg-indigo-600 text-white shadow-xs'
                    : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                }`}
              >
                <Layers className="w-3.5 h-3.5" />
                <span>Email, SMS & Suppressions</span>
              </button>
            </div>

            {/* TAB 1: CHANNEL HUB */}
            {settingsTab === 'channels' && (
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Connected Channels & Health Status</span>
                  <span className="text-[11px] text-slate-500">Official Provider Adapters</span>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  {(['whatsapp', 'facebook', 'instagram', 'telegram', 'email', 'sms', 'webchat', 'call'] as const).map((ch) => {
                    const chMeta = channelIcons[ch] || channelIcons.whatsapp;
                    const ChIcon = chMeta.icon;
                    const health = channelHealth[ch] || { status: 'connected', message: 'Operational' };
                    const setting = channelSettings.find(s => s.channel === ch);

                    return (
                      <div key={ch} className={`p-3.5 rounded-xl border ${innerBg} space-y-2 text-xs`}>
                        <div className="flex items-center justify-between">
                          <div className="flex items-center gap-2">
                            <div className={`p-1.5 rounded-lg ${chMeta.bg}`}>
                              <ChIcon className={`w-4 h-4 ${chMeta.color}`} />
                            </div>
                            <span className="font-bold text-slate-800 dark:text-slate-200">{chMeta.label}</span>
                          </div>
                          <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                            health.status === 'connected'
                              ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                              : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                          }`}>
                            {health.status}
                          </span>
                        </div>

                        <div className="text-[11px] text-slate-500 space-y-0.5">
                          <div>Timezone: <strong className="text-slate-700 dark:text-slate-300">{setting?.timezone || 'Asia/Kolkata'}</strong></div>
                          <div>Hours: <strong className="text-slate-700 dark:text-slate-300">{setting?.working_hours_start || '09:00'} - {setting?.working_hours_end || '18:00'}</strong></div>
                          {setting?.branch && <div>Branch: <strong>{setting.branch.name}</strong></div>}
                        </div>

                        <div className="flex items-center gap-2 pt-2 border-t border-slate-200 dark:border-slate-700">
                          <button
                            type="button"
                            onClick={() => testConnectionMutation.mutate({ channel: ch })}
                            disabled={testConnectionMutation.isPending}
                            className="flex-1 py-1 px-2 rounded-lg bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 font-semibold text-[11px] transition text-center"
                          >
                            Test Connection
                          </button>
                        </div>
                      </div>
                    );
                  })}
                </div>

                {/* Edit Channel Settings Form */}
                <div className={`p-4 rounded-xl border ${innerBg} space-y-3 text-xs`}>
                  <div className="flex items-center gap-2">
                    <Sliders className="w-4 h-4 text-indigo-600" />
                    <h4 className={`font-semibold text-xs uppercase ${textPrimary}`}>Configure Channel Working Hours & Branch</h4>
                  </div>
                  <form
                    onSubmit={async (e) => {
                      e.preventDefault();
                      await saveChannelSettingMutation.mutateAsync(newChannelSettingForm);
                    }}
                    className="grid grid-cols-1 sm:grid-cols-3 gap-3"
                  >
                    <div>
                      <label className="text-[10px] font-semibold text-slate-500 uppercase">Channel</label>
                      <select
                        value={newChannelSettingForm.channel}
                        onChange={(e) => setNewChannelSettingForm(p => ({ ...p, channel: e.target.value }))}
                        className={`w-full px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary}`}
                      >
                        <option value="whatsapp">WhatsApp Business</option>
                        <option value="facebook">Facebook Messenger</option>
                        <option value="instagram">Instagram Direct</option>
                        <option value="telegram">Telegram Bot</option>
                        <option value="email">Email</option>
                        <option value="sms">SMS</option>
                        <option value="webchat">Website Live Chat</option>
                        <option value="call">Cloud Telephony</option>
                      </select>
                    </div>

                    <div>
                      <label className="text-[10px] font-semibold text-slate-500 uppercase">Working Hours Start</label>
                      <input
                        type="time"
                        value={newChannelSettingForm.working_hours_start || '09:00'}
                        onChange={(e) => setNewChannelSettingForm(p => ({ ...p, working_hours_start: e.target.value }))}
                        className={`w-full px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary}`}
                      />
                    </div>

                    <div>
                      <label className="text-[10px] font-semibold text-slate-500 uppercase">Working Hours End</label>
                      <input
                        type="time"
                        value={newChannelSettingForm.working_hours_end || '18:00'}
                        onChange={(e) => setNewChannelSettingForm(p => ({ ...p, working_hours_end: e.target.value }))}
                        className={`w-full px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary}`}
                      />
                    </div>

                    <div className="sm:col-span-3 flex justify-end">
                      <button
                        type="submit"
                        disabled={saveChannelSettingMutation.isPending}
                        className="px-4 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-medium hover:bg-indigo-700 transition"
                      >
                        Save Channel Configuration
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            )}

            {/* TAB 2: TELEGRAM BOT INTEGRATION */}
            {settingsTab === 'telegram' && (
              <div className="space-y-4">
                <div className={`p-4 rounded-xl border ${innerBg} space-y-3`}>
                  <div className="flex items-center gap-2">
                    <Send className="w-4 h-4 text-sky-500" />
                    <h4 className={`font-semibold text-xs uppercase ${textPrimary}`}>Official Telegram Bot Account</h4>
                  </div>
                  <p className="text-[11px] text-slate-500">
                    Connect an official bot created via @BotFather. Incoming messages will be routed directly to the Unified Inbox.
                  </p>
                  <form
                    onSubmit={async (e) => {
                      e.preventDefault();
                      if (!newTelegramForm.bot_token) return;
                      await saveTelegramMutation.mutateAsync(newTelegramForm);
                      setNewTelegramForm({ bot_name: 'CRM Support Bot', bot_username: '', bot_token: '', is_active: true });
                    }}
                    className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs"
                  >
                    <div>
                      <label className="text-[10px] font-semibold text-slate-500 uppercase">Bot Display Name</label>
                      <input
                        type="text"
                        required
                        value={newTelegramForm.bot_name}
                        onChange={(e) => setNewTelegramForm(p => ({ ...p, bot_name: e.target.value }))}
                        className={`w-full px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary}`}
                      />
                    </div>
                    <div>
                      <label className="text-[10px] font-semibold text-slate-500 uppercase">Bot Username (@tag)</label>
                      <input
                        type="text"
                        placeholder="@my_company_crm_bot"
                        value={newTelegramForm.bot_username}
                        onChange={(e) => setNewTelegramForm(p => ({ ...p, bot_username: e.target.value }))}
                        className={`w-full px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary}`}
                      />
                    </div>
                    <div className="sm:col-span-2">
                      <label className="text-[10px] font-semibold text-slate-500 uppercase">Telegram Bot Token (from @BotFather)</label>
                      <input
                        type="password"
                        required
                        placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ"
                        value={newTelegramForm.bot_token}
                        onChange={(e) => setNewTelegramForm(p => ({ ...p, bot_token: e.target.value }))}
                        className={`w-full px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary} font-mono`}
                      />
                    </div>
                    <div className="sm:col-span-2 flex justify-end">
                      <button
                        type="submit"
                        disabled={saveTelegramMutation.isPending}
                        className="px-4 py-1.5 bg-sky-600 text-white rounded-lg text-xs font-medium hover:bg-sky-700 transition"
                      >
                        Connect Telegram Bot
                      </button>
                    </div>
                  </form>
                </div>

                {/* Connected Bots List */}
                <div className="space-y-2">
                  <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Connected Telegram Bots</span>
                  {telegramAccounts.length === 0 ? (
                    <div className="text-xs text-slate-400 text-center py-4">No Telegram bots connected yet.</div>
                  ) : (
                    telegramAccounts.map(bot => (
                      <div key={bot.id} className={`p-3 rounded-xl border ${innerBg} flex items-center justify-between text-xs`}>
                        <div className="flex items-center gap-2">
                          <Send className="w-4 h-4 text-sky-500" />
                          <div>
                            <div className="font-bold text-slate-800 dark:text-slate-200">{bot.bot_name}</div>
                            <div className="text-[11px] text-slate-500">{bot.bot_username ? `@${bot.bot_username}` : 'No username'}</div>
                          </div>
                        </div>
                        <div className="flex items-center gap-2">
                          <button
                            type="button"
                            onClick={() => testConnectionMutation.mutate({ channel: 'telegram', channel_account_id: String(bot.id) })}
                            className="px-2.5 py-1 bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-300 rounded text-[11px] font-semibold"
                          >
                            Test
                          </button>
                          <button
                            type="button"
                            onClick={() => disconnectTelegramMutation.mutate(bot.id)}
                            className="p-1 text-rose-500 hover:text-rose-700 transition"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </div>
                      </div>
                    ))
                  )}
                </div>
              </div>
            )}

            {/* TAB 3: ROUTING RULES */}
            {settingsTab === 'routing' && (
              <div className="space-y-4">
                <div className={`p-4 rounded-xl border ${innerBg} space-y-3 text-xs`}>
                  <div className="flex items-center gap-2">
                    <Wand2 className="w-4 h-4 text-violet-600" />
                    <h4 className={`font-semibold text-xs uppercase ${textPrimary}`}>Create Automatic Routing Rule</h4>
                  </div>
                  <form
                    onSubmit={async (e) => {
                      e.preventDefault();
                      await saveRoutingRuleMutation.mutateAsync(newRoutingRuleForm);
                      setNewRoutingRuleForm({ name: '', priority: 10, channel: 'all', routing_type: 'round_robin', is_active: true });
                    }}
                    className="grid grid-cols-1 sm:grid-cols-2 gap-3"
                  >
                    <div>
                      <label className="text-[10px] font-semibold text-slate-500 uppercase">Rule Name</label>
                      <input
                        type="text"
                        required
                        placeholder="e.g. Inbound WhatsApp Round-Robin"
                        value={newRoutingRuleForm.name}
                        onChange={(e) => setNewRoutingRuleForm(p => ({ ...p, name: e.target.value }))}
                        className={`w-full px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary}`}
                      />
                    </div>
                    <div>
                      <label className="text-[10px] font-semibold text-slate-500 uppercase">Routing Method</label>
                      <select
                        value={newRoutingRuleForm.routing_type}
                        onChange={(e) => setNewRoutingRuleForm(p => ({ ...p, routing_type: e.target.value as any }))}
                        className={`w-full px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary}`}
                      >
                        <option value="round_robin">Round-Robin (Fair Rotation)</option>
                        <option value="least_active">Least-Active (Workload-Aware)</option>
                        <option value="branch">Branch-Based Assignment</option>
                        <option value="department">Department-Based Assignment</option>
                        <option value="language">Language-Based Routing</option>
                        <option value="lead_source">Lead Source Attribution</option>
                        <option value="working_hours">Working Hours / After-Hours</option>
                      </select>
                    </div>
                    <div>
                      <label className="text-[10px] font-semibold text-slate-500 uppercase">Channel Scope</label>
                      <select
                        value={newRoutingRuleForm.channel}
                        onChange={(e) => setNewRoutingRuleForm(p => ({ ...p, channel: e.target.value }))}
                        className={`w-full px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary}`}
                      >
                        <option value="all">All Channels</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="facebook">Facebook</option>
                        <option value="instagram">Instagram</option>
                        <option value="telegram">Telegram</option>
                        <option value="email">Email</option>
                        <option value="sms">SMS</option>
                        <option value="webchat">WebChat</option>
                      </select>
                    </div>
                    <div>
                      <label className="text-[10px] font-semibold text-slate-500 uppercase">Priority (1 = Highest)</label>
                      <input
                        type="number"
                        min={1}
                        max={100}
                        value={newRoutingRuleForm.priority}
                        onChange={(e) => setNewRoutingRuleForm(p => ({ ...p, priority: Number(e.target.value) }))}
                        className={`w-full px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary}`}
                      />
                    </div>
                    <div className="sm:col-span-2 flex justify-end">
                      <button
                        type="submit"
                        disabled={saveRoutingRuleMutation.isPending}
                        className="px-4 py-1.5 bg-violet-600 text-white rounded-lg text-xs font-medium hover:bg-violet-700 transition"
                      >
                        Save Routing Rule
                      </button>
                    </div>
                  </form>
                </div>

                {/* Routing Rules List */}
                <div className="space-y-2">
                  <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Configured Routing Rules</span>
                  {routingRules.length === 0 ? (
                    <div className="text-xs text-slate-400 text-center py-4">No routing rules configured. System defaults to Fair Round-Robin.</div>
                  ) : (
                    routingRules.map(rule => (
                      <div key={rule.id} className={`p-3 rounded-xl border ${innerBg} flex items-center justify-between text-xs`}>
                        <div>
                          <div className="flex items-center gap-2">
                            <span className="font-bold text-slate-800 dark:text-slate-200">{rule.name}</span>
                            <span className="px-1.5 py-0.2 rounded text-[10px] font-mono bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300 font-bold uppercase">
                              {rule.routing_type}
                            </span>
                          </div>
                          <div className="text-[11px] text-slate-500 mt-0.5">
                            Channel: <strong>{rule.channel.toUpperCase()}</strong> • Priority: #{rule.priority}
                          </div>
                        </div>
                        <button
                          type="button"
                          onClick={() => deleteRoutingRuleMutation.mutate(rule.id)}
                          className="p-1 text-slate-400 hover:text-rose-500 transition"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    ))
                  )}
                </div>
              </div>
            )}

            {/* TAB 4: SLA POLICIES */}
            {settingsTab === 'sla' && (
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-bold uppercase tracking-wider text-slate-400">SLA Enforcement & Business Hours</span>
                  <button
                    type="button"
                    onClick={() => recalculateSlaMutation.mutate()}
                    disabled={recalculateSlaMutation.isPending}
                    className="flex items-center gap-1.5 px-3 py-1 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 transition"
                  >
                    <RefreshCw className={`w-3.5 h-3.5 ${recalculateSlaMutation.isPending ? 'animate-spin' : ''}`} />
                    <span>Recalculate All Conversation SLAs</span>
                  </button>
                </div>

                <div className={`p-4 rounded-xl border ${innerBg} space-y-3 text-xs`}>
                  <div className="flex items-center gap-2">
                    <Clock className="w-4 h-4 text-amber-500" />
                    <h4 className={`font-semibold text-xs uppercase ${textPrimary}`}>Create SLA Policy</h4>
                  </div>
                  <form
                    onSubmit={async (e) => {
                      e.preventDefault();
                      await saveSlaPolicyMutation.mutateAsync(newSlaPolicyForm);
                      setNewSlaPolicyForm({ name: '', priority: 'medium', first_response_time_minutes: 15, resolution_time_minutes: 240, pause_outside_business_hours: true, is_active: true });
                    }}
                    className="grid grid-cols-1 sm:grid-cols-2 gap-3"
                  >
                    <div>
                      <label className="text-[10px] font-semibold text-slate-500 uppercase">Policy Name</label>
                      <input
                        type="text"
                        required
                        placeholder="e.g. VIP Urgent SLA"
                        value={newSlaPolicyForm.name}
                        onChange={(e) => setNewSlaPolicyForm(p => ({ ...p, name: e.target.value }))}
                        className={`w-full px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary}`}
                      />
                    </div>
                    <div>
                      <label className="text-[10px] font-semibold text-slate-500 uppercase">Priority Tier</label>
                      <select
                        value={newSlaPolicyForm.priority}
                        onChange={(e) => setNewSlaPolicyForm(p => ({ ...p, priority: e.target.value as any }))}
                        className={`w-full px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary}`}
                      >
                        <option value="urgent">Urgent</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                      </select>
                    </div>
                    <div>
                      <label className="text-[10px] font-semibold text-slate-500 uppercase">First Response Target (Minutes)</label>
                      <input
                        type="number"
                        min={1}
                        value={newSlaPolicyForm.first_response_time_minutes}
                        onChange={(e) => setNewSlaPolicyForm(p => ({ ...p, first_response_time_minutes: Number(e.target.value) }))}
                        className={`w-full px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary}`}
                      />
                    </div>
                    <div>
                      <label className="text-[10px] font-semibold text-slate-500 uppercase">Resolution Target (Minutes)</label>
                      <input
                        type="number"
                        min={1}
                        value={newSlaPolicyForm.resolution_time_minutes}
                        onChange={(e) => setNewSlaPolicyForm(p => ({ ...p, resolution_time_minutes: Number(e.target.value) }))}
                        className={`w-full px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary}`}
                      />
                    </div>
                    <div className="sm:col-span-2 flex items-center gap-2">
                      <input
                        type="checkbox"
                        id="pause_biz_hours"
                        checked={newSlaPolicyForm.pause_outside_business_hours}
                        onChange={(e) => setNewSlaPolicyForm(p => ({ ...p, pause_outside_business_hours: e.target.checked }))}
                        className="rounded text-indigo-600 focus:ring-indigo-500"
                      />
                      <label htmlFor="pause_biz_hours" className="text-xs text-slate-600 dark:text-slate-300">
                        Pause SLA target timers outside configured business working hours & holidays
                      </label>
                    </div>
                    <div className="sm:col-span-2 flex justify-end">
                      <button
                        type="submit"
                        disabled={saveSlaPolicyMutation.isPending}
                        className="px-4 py-1.5 bg-amber-600 text-white rounded-lg text-xs font-medium hover:bg-amber-700 transition"
                      >
                        Save SLA Policy
                      </button>
                    </div>
                  </form>
                </div>

                {/* Policies List */}
                <div className="space-y-2">
                  <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Active SLA Policies</span>
                  {slaPolicies.length === 0 ? (
                    <div className="text-xs text-slate-400 text-center py-4">No custom SLA policies defined.</div>
                  ) : (
                    slaPolicies.map(pol => (
                      <div key={pol.id} className={`p-3 rounded-xl border ${innerBg} flex items-center justify-between text-xs`}>
                        <div>
                          <div className="flex items-center gap-2">
                            <span className="font-bold text-slate-800 dark:text-slate-200">{pol.name}</span>
                            <span className="px-1.5 py-0.2 rounded text-[10px] font-bold uppercase bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                              {pol.priority}
                            </span>
                          </div>
                          <div className="text-[11px] text-slate-500 mt-0.5">
                            FRT: <strong>{pol.first_response_time_minutes}m</strong> • Resolution: <strong>{pol.resolution_time_minutes}m</strong> {pol.pause_outside_business_hours ? '• (Business Hours Only)' : ''}
                          </div>
                        </div>
                        <button
                          type="button"
                          onClick={() => deleteSlaPolicyMutation.mutate(pol.id)}
                          className="p-1 text-slate-400 hover:text-rose-500 transition"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    ))
                  )}
                </div>
              </div>
            )}

            {/* TAB 5: GATEWAYS & SUPPRESSIONS */}
            {settingsTab === 'gateways' && (
              <div className="space-y-4">
                {/* Email Accounts Setup */}
                <div className={`p-4 rounded-xl border ${innerBg} space-y-3`}>
                  <div className="flex items-center gap-2">
                    <Mail className="w-4 h-4 text-sky-500" />
                    <h4 className={`font-semibold text-xs uppercase ${textPrimary}`}>Email Integration (SMTP / IMAP)</h4>
                  </div>
                  <form
                    onSubmit={async (e) => {
                      e.preventDefault();
                      await saveEmailAccountMutation.mutateAsync(newEmailForm);
                    }}
                    className="grid grid-cols-2 gap-3"
                  >
                    <div>
                      <label className="text-[10px] font-semibold text-slate-500 uppercase">Account Name</label>
                      <input
                        type="text"
                        required
                        value={newEmailForm.name}
                        onChange={(e) => setNewEmailForm(prev => ({ ...prev, name: e.target.value }))}
                        className={`w-full px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary}`}
                      />
                    </div>
                    <div>
                      <label className="text-[10px] font-semibold text-slate-500 uppercase">Email Address</label>
                      <input
                        type="email"
                        required
                        value={newEmailForm.email_address}
                        onChange={(e) => setNewEmailForm(prev => ({ ...prev, email_address: e.target.value }))}
                        className={`w-full px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary}`}
                      />
                    </div>
                    <div>
                      <label className="text-[10px] font-semibold text-slate-500 uppercase">SMTP Host</label>
                      <input
                        type="text"
                        value={newEmailForm.smtp_host || ''}
                        onChange={(e) => setNewEmailForm(prev => ({ ...prev, smtp_host: e.target.value }))}
                        className={`w-full px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary}`}
                      />
                    </div>
                    <div>
                      <label className="text-[10px] font-semibold text-slate-500 uppercase">SMTP Password / Token</label>
                      <input
                        type="password"
                        placeholder="Enter password..."
                        value={newEmailForm.smtp_password || ''}
                        onChange={(e) => setNewEmailForm(prev => ({ ...prev, smtp_password: e.target.value }))}
                        className={`w-full px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary}`}
                      />
                    </div>
                    <div className="col-span-2 flex justify-end">
                      <button type="submit" className="px-3 py-1.5 bg-sky-600 text-white rounded-lg text-xs font-medium">
                        Save Email Account
                      </button>
                    </div>
                  </form>
                </div>

                {/* SMS Provider & DLT Config */}
                <div className={`p-4 rounded-xl border ${innerBg} space-y-3`}>
                  <div className="flex items-center gap-2">
                    <MessageCircle className="w-4 h-4 text-purple-500" />
                    <h4 className={`font-semibold text-xs uppercase ${textPrimary}`}>SMS Provider & India DLT Entity</h4>
                  </div>
                  <form
                    onSubmit={async (e) => {
                      e.preventDefault();
                      await saveSmsConfigMutation.mutateAsync(newSmsForm);
                    }}
                    className="grid grid-cols-2 gap-3"
                  >
                    <div>
                      <label className="text-[10px] font-semibold text-slate-500 uppercase">Provider</label>
                      <select
                        value={newSmsForm.provider}
                        onChange={(e) => setNewSmsForm(prev => ({ ...prev, provider: e.target.value as any }))}
                        className={`w-full px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary}`}
                      >
                        <option value="twilio">Twilio Global</option>
                        <option value="exotel">Exotel SMS</option>
                        <option value="fast2sms">Fast2SMS (India)</option>
                        <option value="custom_http">Custom Gateway</option>
                      </select>
                    </div>
                    <div>
                      <label className="text-[10px] font-semibold text-slate-500 uppercase">DLT Header / Sender ID</label>
                      <input
                        type="text"
                        value={newSmsForm.sender_id || ''}
                        onChange={(e) => setNewSmsForm(prev => ({ ...prev, sender_id: e.target.value }))}
                        className={`w-full px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary} font-mono`}
                      />
                    </div>
                    <div>
                      <label className="text-[10px] font-semibold text-slate-500 uppercase">DLT Principal Entity ID</label>
                      <input
                        type="text"
                        value={newSmsForm.dlt_entity_id || ''}
                        onChange={(e) => setNewSmsForm(prev => ({ ...prev, dlt_entity_id: e.target.value }))}
                        className={`w-full px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary} font-mono`}
                      />
                    </div>
                    <div>
                      <label className="text-[10px] font-semibold text-slate-500 uppercase">Account SID / Token</label>
                      <input
                        type="password"
                        placeholder="Enter auth token..."
                        value={newSmsForm.auth_token || ''}
                        onChange={(e) => setNewSmsForm(prev => ({ ...prev, auth_token: e.target.value }))}
                        className={`w-full px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary}`}
                      />
                    </div>
                    <div className="col-span-2 flex justify-end">
                      <button type="submit" className="px-3 py-1.5 bg-purple-600 text-white rounded-lg text-xs font-medium">
                        Save SMS Gateway
                      </button>
                    </div>
                  </form>
                </div>

                {/* Website Live Chat Widget Embed Code */}
                <div className={`p-4 rounded-xl border ${innerBg} space-y-3`}>
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <Globe className="w-4 h-4 text-indigo-500" />
                      <h4 className={`font-semibold text-xs uppercase ${textPrimary}`}>Website Live Chat Widgets</h4>
                    </div>
                    <button
                      type="button"
                      onClick={async () => {
                        await saveWebChatWidgetMutation.mutateAsync(newWidgetForm);
                      }}
                      className="px-2.5 py-1 bg-indigo-600 text-white rounded-lg text-xs font-medium"
                    >
                      Create Chat Widget
                    </button>
                  </div>

                  {webChatWidgets.map((w) => (
                    <div key={w.id} className={`p-3 rounded-xl border ${cardBg} space-y-2`}>
                      <div className="flex items-center justify-between">
                        <span className="font-bold text-xs">{w.name} ({w.brand_title})</span>
                        <button
                          onClick={() => {
                            setActiveWidgetForPreview(w);
                            setIsWidgetPreviewOpen(true);
                          }}
                          className="px-2 py-0.5 rounded bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-300 text-[11px] font-semibold flex items-center gap-1"
                        >
                          <Eye className="w-3 h-3" /> Preview
                        </button>
                      </div>
                      <div className="text-[11px] text-slate-400 font-mono bg-slate-100 dark:bg-slate-800 p-2 rounded-lg flex items-center justify-between">
                        <span>{`<script src="${window.location.origin}/chat-widget.js" data-key="${w.widget_key}"></script>`}</span>
                        <button
                          onClick={() => {
                            navigator.clipboard.writeText(`<script src="${window.location.origin}/chat-widget.js" data-key="${w.widget_key}"></script>`);
                            alert('Embed code snippet copied!');
                          }}
                          className="p-1 hover:text-indigo-600"
                        >
                          <Copy className="w-3.5 h-3.5" />
                        </button>
                      </div>
                    </div>
                  ))}
                </div>

                {/* Communication Suppression Rules */}
                <div className={`p-4 rounded-xl border ${innerBg} space-y-3`}>
                  <div className="flex items-center gap-2">
                    <ShieldCheck className="w-4 h-4 text-rose-500" />
                    <h4 className={`font-semibold text-xs uppercase ${textPrimary}`}>Communication Suppression Registry</h4>
                  </div>
                  <form
                    onSubmit={async (e) => {
                      e.preventDefault();
                      if (!newSuppressionForm.identifier) return;
                      await addSuppressionMutation.mutateAsync(newSuppressionForm);
                      setNewSuppressionForm({ channel: 'email', identifier: '', reason: 'unsubscribe' });
                    }}
                    className="flex items-center gap-2"
                  >
                    <select
                      value={newSuppressionForm.channel}
                      onChange={(e) => setNewSuppressionForm(prev => ({ ...prev, channel: e.target.value }))}
                      className={`px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary}`}
                    >
                      <option value="email">Email</option>
                      <option value="sms">SMS</option>
                      <option value="whatsapp">WhatsApp</option>
                      <option value="all">All Channels</option>
                    </select>
                    <input
                      type="text"
                      required
                      placeholder="email@example.com or +919876543210"
                      value={newSuppressionForm.identifier}
                      onChange={(e) => setNewSuppressionForm(prev => ({ ...prev, identifier: e.target.value }))}
                      className={`flex-1 px-2.5 py-1.5 text-xs rounded-lg border ${cardBg} ${textPrimary}`}
                    />
                    <button type="submit" className="px-3 py-1.5 bg-rose-600 text-white rounded-lg text-xs font-medium">
                      Add Suppression
                    </button>
                  </form>

                  {/* Suppression List */}
                  <div className="space-y-1 max-h-32 overflow-y-auto">
                    {suppressions.map((sup) => (
                      <div key={sup.id} className="flex items-center justify-between text-[11px] p-1.5 rounded bg-slate-100 dark:bg-slate-800">
                        <span>{sup.identifier} ({sup.channel.toUpperCase()} - {sup.reason})</span>
                        <button
                          onClick={() => removeSuppressionMutation.mutate(sup.id)}
                          className="text-rose-500 hover:text-rose-700 p-0.5"
                        >
                          <Trash2 className="w-3 h-3" />
                        </button>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {/* EMBEDDABLE WEBCHAT LIVE PREVIEW MODAL */}
      {isWidgetPreviewOpen && activeWidgetForPreview && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
          <div className="w-full max-w-sm rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-2xl overflow-hidden flex flex-col h-[500px]">
            {/* Widget Brand Header */}
            <div
              className="p-4 text-white flex items-center justify-between"
              style={{ backgroundColor: activeWidgetForPreview.brand_color || '#4f46e5' }}
            >
              <div className="flex items-center gap-2">
                <div className="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center">
                  <Globe className="w-4 h-4 text-white" />
                </div>
                <div>
                  <h3 className="font-bold text-xs">{activeWidgetForPreview.brand_title}</h3>
                  <p className="text-[10px] text-white/80">Online • AI & Agent Hybrid</p>
                </div>
              </div>
              <button onClick={() => setIsWidgetPreviewOpen(false)} className="text-white/80 hover:text-white">
                <X className="w-4 h-4" />
              </button>
            </div>

            {/* Widget Simulated Conversation */}
            <div className="flex-1 p-3 overflow-y-auto space-y-2 bg-slate-50 dark:bg-slate-950 text-xs">
              <div className="p-2.5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 rounded-bl-none shadow-xs">
                {activeWidgetForPreview.welcome_message || 'Hello! How can we assist you today?'}
              </div>
              <div className="flex justify-end">
                <div
                  className="p-2.5 rounded-2xl text-white rounded-br-none shadow-xs"
                  style={{ backgroundColor: activeWidgetForPreview.brand_color || '#4f46e5' }}
                >
                  I'm interested in enterprise CRM integration.
                </div>
              </div>
            </div>

            {/* Widget Input Bar */}
            <div className="p-2.5 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 flex items-center gap-2">
              <input
                type="text"
                placeholder="Type your message..."
                className="flex-1 text-xs px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 focus:outline-none"
              />
              <button
                className="p-2 rounded-lg text-white"
                style={{ backgroundColor: activeWidgetForPreview.brand_color || '#4f46e5' }}
              >
                <Send className="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 1: ENTERPRISE OMNICHANNEL ANALYTICS DASHBOARD */}
      {isAnalyticsModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
          <div className={`w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-2xl border ${cardBg} p-6 shadow-2xl space-y-6`}>
            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
              <div className="flex items-center gap-2.5">
                <div className="p-2 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                  <BarChart2 className="w-5 h-5" />
                </div>
                <div>
                  <h3 className={`font-bold text-base ${textPrimary}`}>Omnichannel Communication Analytics</h3>
                  <p className={`text-xs ${textMuted}`}>Live real-time metrics across all 8 communication channels</p>
                </div>
              </div>
              <button onClick={() => setIsAnalyticsModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Top KPI Cards Grid */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div className={`p-4 rounded-xl border ${innerBg}`}>
                <span className={`text-[11px] font-semibold ${textMuted} uppercase`}>Total Conversations</span>
                <div className={`text-2xl font-black mt-1 ${textPrimary}`}>{omnichannelAnalytics?.total_conversations ?? 0}</div>
                <span className="text-[10px] text-emerald-600 font-medium">Across all channels</span>
              </div>
              <div className={`p-4 rounded-xl border ${innerBg}`}>
                <span className={`text-[11px] font-semibold ${textMuted} uppercase`}>Total Messages</span>
                <div className={`text-2xl font-black mt-1 ${textPrimary}`}>{omnichannelAnalytics?.messages_volume?.total ?? 0}</div>
                <span className="text-[10px] text-slate-500">
                  {omnichannelAnalytics?.messages_volume?.inbound ?? 0} In / {omnichannelAnalytics?.messages_volume?.outbound ?? 0} Out
                </span>
              </div>
              <div className={`p-4 rounded-xl border ${innerBg}`}>
                <span className={`text-[11px] font-semibold ${textMuted} uppercase`}>Avg First Response</span>
                <div className={`text-2xl font-black mt-1 text-amber-600 dark:text-amber-400`}>
                  {omnichannelAnalytics?.first_response_time?.average_minutes ? `${omnichannelAnalytics.first_response_time.average_minutes}m` : '0m'}
                </div>
                <span className="text-[10px] text-slate-500">
                  SLA Target &lt; 15 mins
                </span>
              </div>
              <div className={`p-4 rounded-xl border ${innerBg}`}>
                <span className={`text-[11px] font-semibold ${textMuted} uppercase`}>Avg Resolution Time</span>
                <div className={`text-2xl font-black mt-1 text-blue-600 dark:text-blue-400`}>
                  {omnichannelAnalytics?.resolution_time?.average_minutes ? `${omnichannelAnalytics.resolution_time.average_minutes}m` : '0m'}
                </div>
                <span className="text-[10px] text-slate-500">
                  From creation to resolved
                </span>
              </div>
            </div>

            {/* Channel Breakdown & Voice Calls Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {/* Channel Distribution */}
              <div className={`p-4 rounded-xl border ${innerBg} space-y-3`}>
                <h4 className={`text-xs font-bold uppercase tracking-wider ${textPrimary}`}>Conversations by Channel</h4>
                <div className="space-y-2">
                  {Object.entries(omnichannelAnalytics?.conversations_by_channel || {}).map(([ch, count]) => {
                    const chMeta = channelIcons[ch] || channelIcons.whatsapp;
                    const ChIcon = chMeta.icon;
                    const total = omnichannelAnalytics?.total_conversations || 1;
                    const pct = Math.round(((count as number) / total) * 100);

                    return (
                      <div key={ch} className="space-y-1">
                        <div className="flex items-center justify-between text-xs">
                          <div className="flex items-center gap-1.5 font-medium capitalize">
                            <ChIcon className={`w-3.5 h-3.5 ${chMeta.color}`} />
                            <span>{chMeta.label}</span>
                          </div>
                          <span className="font-semibold">{count as number} ({pct}%)</span>
                        </div>
                        <div className="w-full bg-slate-200 dark:bg-slate-700 h-1.5 rounded-full overflow-hidden">
                          <div className="bg-indigo-600 h-1.5 rounded-full" style={{ width: `${pct}%` }} />
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>

              {/* Voice Telephony & Provider Costs */}
              <div className={`p-4 rounded-xl border ${innerBg} space-y-3`}>
                <h4 className={`text-xs font-bold uppercase tracking-wider ${textPrimary}`}>Voice Calls & Telephony</h4>
                <div className="grid grid-cols-2 gap-2 text-xs">
                  <div className={`p-3 rounded-lg border ${cardBg}`}>
                    <span className="text-[10px] text-slate-500 uppercase">Total Calls</span>
                    <div className="text-lg font-bold">{omnichannelAnalytics?.voice_calls_overview?.total_calls ?? 0}</div>
                  </div>
                  <div className={`p-3 rounded-lg border ${cardBg}`}>
                    <span className="text-[10px] text-slate-500 uppercase">Completed Calls</span>
                    <div className="text-lg font-bold text-emerald-600">{omnichannelAnalytics?.voice_calls_overview?.completed ?? 0}</div>
                  </div>
                </div>

                <div className={`p-3 rounded-lg border ${cardBg} space-y-1 text-xs`}>
                  <div className="flex justify-between text-slate-500">
                    <span>Voice Duration Metered:</span>
                    <span className="font-semibold text-slate-800 dark:text-slate-200">{omnichannelAnalytics?.provider_costs?.voice_minutes ?? 0} mins</span>
                  </div>
                  <div className="flex justify-between text-slate-500">
                    <span>SMS Segments Dispatched:</span>
                    <span className="font-semibold text-slate-800 dark:text-slate-200">{omnichannelAnalytics?.provider_costs?.sms_segments ?? 0}</span>
                  </div>
                  <div className="flex justify-between pt-1 border-t border-slate-200 dark:border-slate-700 font-bold">
                    <span>Est. Provider Usage Cost:</span>
                    <span className="text-indigo-600 dark:text-indigo-400">${omnichannelAnalytics?.provider_costs?.estimated_total_usd?.toFixed(4) ?? '0.0000'}</span>
                  </div>
                </div>
              </div>
            </div>

            {/* Agent Workload & AI Handover */}
            <div className={`p-4 rounded-xl border ${innerBg} space-y-3`}>
              <h4 className={`text-xs font-bold uppercase tracking-wider ${textPrimary}`}>Agent Workload & AI Co-Pilot Handling</h4>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="space-y-1.5 max-h-40 overflow-y-auto">
                  <span className="text-[10px] text-slate-500 uppercase font-semibold">Active Agent Workloads</span>
                  {(omnichannelAnalytics?.agent_workload || []).map((agent) => (
                    <div key={agent.user_id} className={`flex items-center justify-between p-2 rounded-lg border ${cardBg} text-xs`}>
                      <span className="font-medium">{agent.name}</span>
                      <div className="flex items-center gap-2">
                        <span className="px-1.5 py-0.5 rounded text-[10px] bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300 font-semibold">
                          {agent.open_conversations} Open
                        </span>
                        <span className="px-1.5 py-0.5 rounded text-[10px] bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 font-semibold">
                          {agent.resolved_conversations} Resolved
                        </span>
                      </div>
                    </div>
                  ))}
                </div>

                <div className={`p-3 rounded-lg border ${cardBg} flex flex-col justify-around text-xs`}>
                  <div className="flex items-center justify-between">
                    <span className="text-slate-500">Autonomous AI Handled:</span>
                    <span className="font-bold text-purple-600">{omnichannelAnalytics?.ai_metrics?.ai_handled_conversations ?? 0} threads</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-slate-500">Human Agent Takeovers:</span>
                    <span className="font-bold text-indigo-600">{omnichannelAnalytics?.ai_metrics?.human_handovers ?? 0} events</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-slate-500">Message Delivery Success:</span>
                    <span className="font-bold text-emerald-600">
                      {omnichannelAnalytics?.delivery_rates?.delivered ?? 0} delivered / {omnichannelAnalytics?.delivery_rates?.read ?? 0} read
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 2: ASSIGNMENT AUDIT LOG & ROUTING HISTORY */}
      {isAssignmentHistoryOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
          <div className={`w-full max-w-lg rounded-2xl border ${cardBg} p-6 shadow-2xl space-y-4`}>
            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
              <div className="flex items-center gap-2">
                <History className="w-5 h-5 text-violet-600" />
                <h3 className={`font-bold text-sm ${textPrimary}`}>Assignment & Transfer Audit Trail</h3>
              </div>
              <button onClick={() => setIsAssignmentHistoryOpen(false)} className="text-slate-400 hover:text-slate-600">
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="space-y-2 max-h-96 overflow-y-auto">
              {assignmentHistory.length === 0 ? (
                <div className="text-xs text-slate-400 text-center py-8">
                  No previous assignment logs recorded for this conversation.
                </div>
              ) : (
                assignmentHistory.map((log) => (
                  <div key={log.id} className={`p-3 rounded-xl border ${innerBg} text-xs space-y-1`}>
                    <div className="flex items-center justify-between">
                      <span className="font-bold text-violet-700 dark:text-violet-300">
                        {log.assigned_to ? log.assigned_to.name : 'Unassigned'}
                      </span>
                      <span className="text-[10px] text-slate-400 font-mono">
                        {new Date(log.created_at).toLocaleString()}
                      </span>
                    </div>
                    <div className="text-[11px] text-slate-600 dark:text-slate-300">
                      {log.reason || 'No specific reason provided'}
                    </div>
                    <div className="flex items-center gap-2 text-[10px] text-slate-400 pt-1">
                      <span>Method: <strong className="uppercase">{log.routing_method}</strong></span>
                      {log.assigned_by && <span>• By: {log.assigned_by.name}</span>}
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      )}

      {/* MODAL 3: QUICK REPLIES / CANNED RESPONSES */}
      {isQuickRepliesOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
          <div className={`w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-2xl border ${cardBg} p-6 shadow-2xl space-y-4`}>
            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
              <div className="flex items-center gap-2">
                <Zap className="w-5 h-5 text-amber-500" />
                <h3 className={`font-bold text-sm ${textPrimary}`}>Canned Responses & Quick Replies</h3>
              </div>
              <button onClick={() => setIsQuickRepliesOpen(false)} className="text-slate-400 hover:text-slate-600">
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Quick Template Picker */}
            <div className="space-y-2 max-h-56 overflow-y-auto">
              <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Insert Saved Template</span>
              {quickReplies.length === 0 ? (
                <div className="text-xs text-slate-400 text-center py-4">
                  No quick replies configured yet. Add your first canned template below!
                </div>
              ) : (
                quickReplies.map((qr) => (
                  <div
                    key={qr.id}
                    className={`p-2.5 rounded-xl border ${innerBg} flex items-center justify-between gap-3 text-xs`}
                  >
                    <div className="min-w-0">
                      <div className="flex items-center gap-2">
                        <span className="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400">{qr.shortcut}</span>
                        <span className="font-semibold text-slate-700 dark:text-slate-300">{qr.title}</span>
                      </div>
                      <p className="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5">{qr.content}</p>
                    </div>
                    <div className="flex items-center gap-1.5 flex-shrink-0">
                      <button
                        type="button"
                        onClick={() => {
                          setMessageText((prev) => (prev ? `${prev}\n${qr.content}` : qr.content));
                          setIsQuickRepliesOpen(false);
                        }}
                        className="px-2.5 py-1 rounded bg-indigo-600 text-white font-medium text-xs hover:bg-indigo-700 transition"
                      >
                        Insert
                      </button>
                      <button
                        type="button"
                        onClick={() => deleteQuickReplyMutation.mutate(qr.id)}
                        className="p-1 text-slate-400 hover:text-rose-500 transition"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                ))
              )}
            </div>

            {/* Create New Quick Reply */}
            <form
              onSubmit={async (e) => {
                e.preventDefault();
                if (!newQuickReplyForm.shortcut || !newQuickReplyForm.title || !newQuickReplyForm.content) return;
                await createQuickReplyMutation.mutateAsync(newQuickReplyForm);
                setNewQuickReplyForm({ shortcut: '', title: '', content: '', channel: 'all' });
              }}
              className={`p-3 rounded-xl border ${innerBg} space-y-2.5 text-xs`}
            >
              <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Create New Quick Reply</span>
              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="text-[10px] text-slate-500 block mb-0.5 font-semibold">Shortcut (e.g. /pricing)</label>
                  <input
                    type="text"
                    required
                    placeholder="/hours, /pricing"
                    value={newQuickReplyForm.shortcut}
                    onChange={(e) => setNewQuickReplyForm((p) => ({ ...p, shortcut: e.target.value }))}
                    className={`w-full px-2.5 py-1.5 rounded-lg border ${cardBg} ${textPrimary} font-mono`}
                  />
                </div>
                <div>
                  <label className="text-[10px] text-slate-500 block mb-0.5 font-semibold">Title</label>
                  <input
                    type="text"
                    required
                    placeholder="Pricing Inquiry Response"
                    value={newQuickReplyForm.title}
                    onChange={(e) => setNewQuickReplyForm((p) => ({ ...p, title: e.target.value }))}
                    className={`w-full px-2.5 py-1.5 rounded-lg border ${cardBg} ${textPrimary}`}
                  />
                </div>
              </div>
              <div>
                <label className="text-[10px] text-slate-500 block mb-0.5 font-semibold">Message Content</label>
                <textarea
                  rows={2}
                  required
                  placeholder="Template text inserted when shortcut is used..."
                  value={newQuickReplyForm.content}
                  onChange={(e) => setNewQuickReplyForm((p) => ({ ...p, content: e.target.value }))}
                  className={`w-full px-2.5 py-1.5 rounded-lg border ${cardBg} ${textPrimary}`}
                />
              </div>
              <div className="flex justify-end">
                <button
                  type="submit"
                  disabled={createQuickReplyMutation.isPending}
                  className="px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-medium text-xs hover:bg-indigo-700 transition"
                >
                  Save Quick Reply
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
