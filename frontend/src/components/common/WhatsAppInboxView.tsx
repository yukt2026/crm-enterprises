import React, { useState, useEffect, useRef } from 'react';
import {
  MessageSquare,
  Send,
  Phone,
  User as UserIcon,
  Search,
  Filter,
  Check,
  CheckCheck,
  Clock,
  AlertTriangle,
  Bot,
  UserCheck,
  Tag,
  Paperclip,
  FileText,
  Lock,
  Plus,
  RefreshCw,
  Settings,
  ShieldCheck,
  ExternalLink,
  ChevronRight,
  AlertCircle,
  X,
  Sparkles,
  Layers,
  Inbox,
  UserPlus,
  HelpCircle,
  Megaphone,
  Zap,
  DollarSign,
} from 'lucide-react';
import WhatsAppCampaignsTab from '../whatsapp/WhatsAppCampaignsTab';
import WhatsAppAutomationsTab from '../whatsapp/WhatsAppAutomationsTab';
import WhatsAppTemplatesTab from '../whatsapp/WhatsAppTemplatesTab';
import WhatsAppNumbersTab from '../whatsapp/WhatsAppNumbersTab';
import WhatsAppBillingTab from '../whatsapp/WhatsAppBillingTab';
import {
  useWhatsAppConversations,
  useWhatsAppConversation,
  useWhatsAppPhoneNumbers,
  useWhatsAppTemplates,
  useAssignWhatsAppConversation,
  useUpdateWhatsAppConversationStatus,
  useAddWhatsAppConversationNote,
  useToggleWhatsAppAiHandover,
  useCreateLeadFromWhatsAppConversation,
  useSendWhatsAppTextMessage,
  useSendWhatsAppTemplateMessage,
  useSendWhatsAppMediaMessage,
  useTestWhatsAppConnection,
  useCreateWhatsAppAccount,
  useConnectWhatsAppPhoneNumber,
  useCreateWhatsAppTemplate,
} from '../../hooks/useWhatsApp';
import { WhatsAppConversationItem, WhatsAppMessageItem, WhatsAppTemplateItem } from '../../api/types';
import { useCRMStore } from '../../store/crmStore';

interface WhatsAppInboxViewProps {
  theme?: 'light' | 'dark';
}

export default function WhatsAppInboxView({ theme = 'dark' }: WhatsAppInboxViewProps) {
  const isDark = theme === 'dark';
  const currentUser = useCRMStore((s) => s.user);

  // Main Active Tab
  const [mainTab, setMainTab] = useState<'inbox' | 'campaigns' | 'automations' | 'templates' | 'numbers' | 'billing'>('inbox');

  // Filters & Selection
  const [scope, setScope] = useState<'all' | 'assigned_to_me' | 'unassigned'>('all');
  const [statusFilter, setStatusFilter] = useState<'all' | 'open' | 'pending' | 'resolved'>('all');
  const [search, setSearch] = useState('');
  const [selectedConversationId, setSelectedConversationId] = useState<number | null>(null);

  // Composer & UI State
  const [composerMode, setComposerMode] = useState<'message' | 'note'>('message');
  const [messageText, setMessageText] = useState('');
  const [internalNoteText, setInternalNoteText] = useState('');
  const [isTemplateModalOpen, setIsTemplateModalOpen] = useState(false);
  const [selectedTemplate, setSelectedTemplate] = useState<WhatsAppTemplateItem | null>(null);
  const [templateParams, setTemplateParams] = useState<string[]>(['', '', '']);
  const [isMediaModalOpen, setIsMediaModalOpen] = useState(false);
  const [mediaUrl, setMediaUrl] = useState('');
  const [mediaType, setMediaType] = useState<'image' | 'document' | 'video'>('image');
  const [mediaCaption, setMediaCaption] = useState('');
  const [isSettingsModalOpen, setIsSettingsModalOpen] = useState(false);
  const [isCreateLeadOpen, setIsCreateLeadOpen] = useState(false);
  const [leadForm, setLeadForm] = useState({ name: '', email: '', company: '' });

  // Settings Sub-forms
  const [wabaForm, setWabaForm] = useState({
    name: 'Official Meta WABA',
    waba_id: '',
    app_id: '',
    system_user_token: '',
  });
  const [phoneForm, setPhoneForm] = useState({
    whatsapp_account_id: 1,
    phone_number_id: '',
    display_phone_number: '',
    verified_name: '',
  });

  const chatEndRef = useRef<HTMLDivElement>(null);

  // React Queries & Mutations
  const { data: conversations = [], isLoading: isLoadingConversations } = useWhatsAppConversations({
    scope,
    status: statusFilter === 'all' ? undefined : statusFilter,
    search: search || undefined,
  });

  const { data: activeConversation, isLoading: isLoadingActive } = useWhatsAppConversation(selectedConversationId);
  const { data: phoneNumbers = [] } = useWhatsAppPhoneNumbers();
  const { data: templates = [] } = useWhatsAppTemplates();

  const assignMutation = useAssignWhatsAppConversation();
  const updateStatusMutation = useUpdateWhatsAppConversationStatus();
  const addNoteMutation = useAddWhatsAppConversationNote();
  const toggleAiMutation = useToggleWhatsAppAiHandover();
  const createLeadMutation = useCreateLeadFromWhatsAppConversation();
  const sendTextMutation = useSendWhatsAppTextMessage();
  const sendTemplateMutation = useSendWhatsAppTemplateMessage();
  const sendMediaMutation = useSendWhatsAppMediaMessage();
  const testConnMutation = useTestWhatsAppConnection();
  const createWabaMutation = useCreateWhatsAppAccount();
  const connectPhoneMutation = useConnectWhatsAppPhoneNumber();
  const createTemplateMutation = useCreateWhatsAppTemplate();

  // Auto-select first conversation if none selected
  useEffect(() => {
    if (!selectedConversationId && conversations.length > 0) {
      setSelectedConversationId(conversations[0].id);
    }
  }, [conversations, selectedConversationId]);

  // Scroll to bottom when new messages arrive
  useEffect(() => {
    chatEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [activeConversation?.messages, activeConversation?.notes]);

  const handleSendMessage = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedConversationId) return;

    if (composerMode === 'note') {
      if (!internalNoteText.trim()) return;
      await addNoteMutation.mutateAsync({
        id: selectedConversationId,
        content: internalNoteText.trim(),
      });
      setInternalNoteText('');
    } else {
      if (!messageText.trim()) return;
      await sendTextMutation.mutateAsync({
        conversationId: selectedConversationId,
        text: messageText.trim(),
      });
      setMessageText('');
    }
  };

  const handleSendTemplate = async () => {
    if (!selectedConversationId || !selectedTemplate) return;
    const cleanParams = templateParams.filter((p) => p.trim() !== '');

    await sendTemplateMutation.mutateAsync({
      conversationId: selectedConversationId,
      templateName: selectedTemplate.template_name,
      language: selectedTemplate.language || 'en_US',
      parameters: cleanParams,
    });

    setIsTemplateModalOpen(false);
    setSelectedTemplate(null);
  };

  const handleSendMedia = async () => {
    if (!selectedConversationId || !mediaUrl.trim()) return;

    await sendMediaMutation.mutateAsync({
      conversationId: selectedConversationId,
      mediaType,
      mediaUrl: mediaUrl.trim(),
      caption: mediaCaption.trim() || undefined,
    });

    setIsMediaModalOpen(false);
    setMediaUrl('');
    setMediaCaption('');
  };

  const handleCreateLead = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedConversationId) return;

    await createLeadMutation.mutateAsync({
      id: selectedConversationId,
      data: leadForm,
    });
    setIsCreateLeadOpen(false);
  };

  // Combine and sort messages and internal notes chronologically
  const timelineItems = useMemo(() => {
    if (!activeConversation) return [];
    const msgs = (activeConversation.messages || []).map((m) => ({
      type: 'message' as const,
      id: m.id,
      timestamp: new Date(m.created_at).getTime(),
      data: m,
    }));
    const notes = (activeConversation.notes || []).map((n) => ({
      type: 'note' as const,
      id: n.id,
      timestamp: new Date(n.created_at).getTime(),
      data: n,
    }));

    return [...msgs, ...notes].sort((a, b) => a.timestamp - b.timestamp);
  }, [activeConversation]);

  // Check 24-hour window status
  const isWindowActive = activeConversation?.window_expires_at
    ? new Date(activeConversation.window_expires_at).getTime() > Date.now()
    : false;

  const defaultPhone = phoneNumbers.find((p) => p.is_default) || phoneNumbers[0];

  return (
    <div className="flex flex-col gap-4">
      {/* ========================================================================= */}
      {/* TOP NAVIGATION / MODULE TABS                                              */}
      {/* ========================================================================= */}
      <div className={`p-2 rounded-xl border flex flex-wrap items-center justify-between gap-2 shadow-sm ${
        isDark ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
      }`}>
        <div className="flex items-center gap-1.5 overflow-x-auto">
          <button
            type="button"
            onClick={() => setMainTab('inbox')}
            className={`flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold transition ${
              mainTab === 'inbox'
                ? 'bg-emerald-600 text-white shadow-sm'
                : isDark
                ? 'text-slate-400 hover:text-white hover:bg-slate-800'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
            }`}
          >
            <MessageSquare className="w-4 h-4" />
            <span>Customer Inbox</span>
          </button>

          <button
            type="button"
            onClick={() => setMainTab('campaigns')}
            className={`flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold transition ${
              mainTab === 'campaigns'
                ? 'bg-emerald-600 text-white shadow-sm'
                : isDark
                ? 'text-slate-400 hover:text-white hover:bg-slate-800'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
            }`}
          >
            <Megaphone className="w-4 h-4" />
            <span>Campaigns</span>
          </button>

          <button
            type="button"
            onClick={() => setMainTab('automations')}
            className={`flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold transition ${
              mainTab === 'automations'
                ? 'bg-emerald-600 text-white shadow-sm'
                : isDark
                ? 'text-slate-400 hover:text-white hover:bg-slate-800'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
            }`}
          >
            <Zap className="w-4 h-4" />
            <span>Automated Journeys</span>
          </button>

          <button
            type="button"
            onClick={() => setMainTab('templates')}
            className={`flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold transition ${
              mainTab === 'templates'
                ? 'bg-emerald-600 text-white shadow-sm'
                : isDark
                ? 'text-slate-400 hover:text-white hover:bg-slate-800'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
            }`}
          >
            <Layers className="w-4 h-4" />
            <span>Templates</span>
          </button>

          <button
            type="button"
            onClick={() => setMainTab('numbers')}
            className={`flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold transition ${
              mainTab === 'numbers'
                ? 'bg-emerald-600 text-white shadow-sm'
                : isDark
                ? 'text-slate-400 hover:text-white hover:bg-slate-800'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
            }`}
          >
            <Phone className="w-4 h-4" />
            <span>Phone Lines & Routing</span>
          </button>

          <button
            type="button"
            onClick={() => setMainTab('billing')}
            className={`flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold transition ${
              mainTab === 'billing'
                ? 'bg-emerald-600 text-white shadow-sm'
                : isDark
                ? 'text-slate-400 hover:text-white hover:bg-slate-800'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
            }`}
          >
            <DollarSign className="w-4 h-4" />
            <span>Usage & Billing</span>
          </button>
        </div>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => setIsSettingsModalOpen(true)}
            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-medium transition ${
              isDark
                ? 'bg-slate-800 border-slate-700 text-slate-300 hover:text-white'
                : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50'
            }`}
          >
            <Settings className="w-3.5 h-3.5" />
            <span>WABA Config</span>
          </button>
        </div>
      </div>

      {/* Main Content Area */}
      {mainTab === 'campaigns' && <WhatsAppCampaignsTab isDark={isDark} />}
      {mainTab === 'automations' && <WhatsAppAutomationsTab isDark={isDark} />}
      {mainTab === 'templates' && <WhatsAppTemplatesTab isDark={isDark} />}
      {mainTab === 'numbers' && <WhatsAppNumbersTab isDark={isDark} />}
      {mainTab === 'billing' && <WhatsAppBillingTab isDark={isDark} />}

      {mainTab === 'inbox' && (
        <div className={`h-[calc(100vh-210px)] rounded-2xl border flex overflow-hidden shadow-sm ${
          isDark ? 'bg-slate-950 border-slate-800' : 'bg-white border-slate-200'
        }`}>
          {/* ========================================================================= */}
          {/* 1. LEFT PANE: Conversations List & Filters                                */}
          {/* ========================================================================= */}
      <div className={`w-80 sm:w-96 flex flex-col border-r flex-shrink-0 ${
        isDark ? 'bg-slate-900/60 border-slate-800' : 'bg-slate-50 border-slate-200'
      }`}>
        {/* Top Header & Connected Line Info */}
        <div className="p-3.5 border-b dark:border-slate-800 space-y-3">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <div className="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-500">
                <MessageSquare className="w-4 h-4" />
              </div>
              <div>
                <h3 className="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                  WhatsApp CRM
                </h3>
                <div className="text-[10px] text-slate-500 flex items-center gap-1">
                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
                  Meta Cloud API Official
                </div>
              </div>
            </div>

            <div className="flex items-center gap-1.5">
              <button
                onClick={() => useCRMStore.getState().setActiveModule('whatsapp_ai')}
                className="flex items-center gap-1 px-2.5 py-1 rounded-lg border text-xs font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 hover:bg-emerald-500/20 border-emerald-500/20 transition"
                title="Open WhatsApp AI Agent Builder & Knowledge Base"
              >
                <Bot className="w-3.5 h-3.5" />
                AI Builder
              </button>
              <button
                onClick={() => setIsSettingsModalOpen(true)}
                className="p-1.5 rounded-lg border text-slate-500 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 dark:border-slate-700 transition"
                title="WhatsApp Cloud API Settings"
              >
                <Settings className="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          {/* Connected Number Pill */}
          {defaultPhone && (
            <div className="flex items-center justify-between px-2.5 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-xs">
              <div className="flex items-center gap-1.5">
                <Phone className="w-3 h-3 text-emerald-500" />
                <span className="font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                  {defaultPhone.display_phone_number}
                </span>
              </div>
              <span className="text-[10px] font-bold text-emerald-500 uppercase px-1 rounded bg-emerald-500/10">
                {defaultPhone.quality_rating} Rating
              </span>
            </div>
          )}

          {/* Search Box */}
          <div className="relative">
            <Search className="w-3.5 h-3.5 absolute left-3 top-2.5 text-slate-400" />
            <input
              type="text"
              placeholder="Search chats, phone, leads..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className={`w-full pl-8 pr-3 py-1.5 text-xs rounded-lg border outline-none transition ${
                isDark
                  ? 'bg-slate-800/80 border-slate-700 text-white placeholder-slate-500 focus:border-emerald-500'
                  : 'bg-white border-slate-300 text-slate-900 placeholder-slate-400 focus:border-emerald-500'
              }`}
            />
          </div>

          {/* Team Scope Filter Pills */}
          <div className="flex items-center gap-1 p-0.5 rounded-lg bg-slate-200/60 dark:bg-slate-800 text-[11px] font-medium">
            <button
              onClick={() => setScope('all')}
              className={`flex-1 py-1 rounded text-center transition ${
                scope === 'all'
                  ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-semibold shadow-xs'
                  : 'text-slate-600 dark:text-slate-400'
              }`}
            >
              All Chats
            </button>
            <button
              onClick={() => setScope('assigned_to_me')}
              className={`flex-1 py-1 rounded text-center transition ${
                scope === 'assigned_to_me'
                  ? 'bg-white dark:bg-slate-900 text-emerald-600 dark:text-emerald-400 font-semibold shadow-xs'
                  : 'text-slate-600 dark:text-slate-400'
              }`}
            >
              Mine
            </button>
            <button
              onClick={() => setScope('unassigned')}
              className={`flex-1 py-1 rounded text-center transition ${
                scope === 'unassigned'
                  ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-semibold shadow-xs'
                  : 'text-slate-600 dark:text-slate-400'
              }`}
            >
              Unassigned
            </button>
          </div>
        </div>

        {/* Status Tab Filters */}
        <div className="flex items-center justify-around border-b dark:border-slate-800 text-xs px-2 py-1.5">
          {(['all', 'open', 'pending', 'resolved'] as const).map((st) => (
            <button
              key={st}
              onClick={() => setStatusFilter(st)}
              className={`capitalize px-2 py-1 rounded text-[11px] font-medium transition ${
                statusFilter === st
                  ? 'text-emerald-600 dark:text-emerald-400 font-bold'
                  : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
              }`}
            >
              {st}
            </button>
          ))}
        </div>

        {/* Conversation List */}
        <div className="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/60">
          {isLoadingConversations ? (
            <div className="p-6 text-center text-xs text-slate-500">Loading chats...</div>
          ) : conversations.length === 0 ? (
            <div className="p-8 text-center text-slate-500 space-y-2">
              <Inbox className="w-8 h-8 mx-auto text-slate-400" />
              <div className="text-xs font-semibold text-slate-700 dark:text-slate-300">No conversations</div>
              <p className="text-[11px] max-w-[200px] mx-auto text-slate-400">
                Inbound WhatsApp inquiries will arrive here via official webhook.
              </p>
            </div>
          ) : (
            conversations.map((conv) => {
              const isSelected = selectedConversationId === conv.id;
              const hasUnread = conv.unread_count > 0;
              const contactName = conv.customer_name || conv.contact?.name || conv.lead?.name || conv.phone_number;
              const isExpired = conv.window_expires_at
                ? new Date(conv.window_expires_at).getTime() < Date.now()
                : true;

              return (
                <div
                  key={conv.id}
                  onClick={() => setSelectedConversationId(conv.id)}
                  className={`p-3 transition cursor-pointer flex items-start gap-2.5 ${
                    isSelected
                      ? isDark
                        ? 'bg-slate-800/90'
                        : 'bg-emerald-50/80'
                      : isDark
                      ? 'hover:bg-slate-800/40'
                      : 'hover:bg-white'
                  }`}
                >
                  {/* Initials Avatar */}
                  <div className="w-9 h-9 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold flex items-center justify-center flex-shrink-0 text-xs border border-emerald-500/20">
                    {contactName.slice(0, 2).toUpperCase()}
                  </div>

                  <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between">
                      <div className="font-semibold text-xs text-slate-900 dark:text-white truncate">
                        {contactName}
                      </div>
                      <span className="text-[10px] text-slate-400 font-mono">
                        {conv.last_message_at ? new Date(conv.last_message_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''}
                      </span>
                    </div>

                    <div className="text-[11px] text-slate-500 dark:text-slate-400 font-mono">
                      {conv.phone_number}
                    </div>

                    <div className="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5">
                      {conv.last_message_preview || 'No messages yet'}
                    </div>

                    {/* Tags, Unread Counter & 24h Indicator */}
                    <div className="flex items-center justify-between mt-1.5 pt-1 border-t border-slate-100 dark:border-slate-800/40">
                      <div className="flex items-center gap-1.5">
                        {conv.is_ai_handled ? (
                          <span className="inline-flex items-center gap-0.5 text-[9px] px-1 py-0.2 rounded bg-purple-500/10 text-purple-400 font-bold">
                            <Bot className="w-2.5 h-2.5" /> AI Handled
                          </span>
                        ) : conv.assigned_user ? (
                          <span className="text-[9px] text-slate-400 truncate max-w-[90px]">
                            {conv.assigned_user.name}
                          </span>
                        ) : (
                          <span className="text-[9px] text-amber-500">Unassigned</span>
                        )}

                        {isExpired ? (
                          <span className="text-[9px] text-amber-500 font-semibold flex items-center gap-0.5" title="24h session expired">
                            <Clock className="w-2.5 h-2.5" /> Template
                          </span>
                        ) : (
                          <span className="text-[9px] text-emerald-500 font-semibold flex items-center gap-0.5" title="24h active window">
                            <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" /> 24h
                          </span>
                        )}
                      </div>

                      {hasUnread && (
                        <span className="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-emerald-500 text-white">
                          {conv.unread_count}
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

      {/* ========================================================================= */}
      {/* 2. CENTER PANE: Active Conversation Message Thread                        */}
      {/* ========================================================================= */}
      <div className="flex-1 flex flex-col min-w-0 bg-slate-50/50 dark:bg-slate-900/40">
        {activeConversation ? (
          <>
            {/* Thread Header */}
            <div className={`p-3.5 border-b flex items-center justify-between ${
              isDark ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
            }`}>
              <div className="flex items-center gap-3 min-w-0">
                <div className="w-9 h-9 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center text-xs flex-shrink-0">
                  {(activeConversation.customer_name || activeConversation.phone_number).slice(0, 2).toUpperCase()}
                </div>
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <h4 className="text-xs font-bold text-slate-900 dark:text-white truncate">
                      {activeConversation.customer_name || 'WhatsApp Contact'}
                    </h4>
                    <span className="text-[10px] font-mono px-1.5 py-0.2 rounded bg-slate-100 dark:bg-slate-800 text-slate-500">
                      {activeConversation.phone_number}
                    </span>
                  </div>
                  <div className="text-[10px] text-slate-400 flex items-center gap-2 mt-0.5">
                    <span>Priority: <strong className="capitalize">{activeConversation.priority}</strong></span>
                    <span>•</span>
                    <span>Status: <strong className="capitalize text-emerald-500">{activeConversation.status}</strong></span>
                  </div>
                </div>
              </div>

              {/* Header Quick Actions */}
              <div className="flex items-center gap-2">
                {/* AI / Human Takeover Toggle */}
                <button
                  onClick={() =>
                    toggleAiMutation.mutate({
                      id: activeConversation.id,
                      isAiHandled: !activeConversation.is_ai_handled,
                    })
                  }
                  className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold border transition ${
                    activeConversation.is_ai_handled
                      ? 'bg-purple-500/10 border-purple-500/30 text-purple-400 hover:bg-purple-500/20'
                      : 'bg-slate-100 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200'
                  }`}
                  title={activeConversation.is_ai_handled ? 'AI active. Click to take over as human.' : 'Human agent active. Click to let AI handle.'}
                >
                  <Bot className="w-3.5 h-3.5" />
                  {activeConversation.is_ai_handled ? 'AI Active' : 'Human Takeover'}
                </button>

                {/* Status Toggle */}
                {activeConversation.status !== 'resolved' ? (
                  <button
                    onClick={() => updateStatusMutation.mutate({ id: activeConversation.id, status: 'resolved' })}
                    className="px-2.5 py-1 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition flex items-center gap-1"
                  >
                    <Check className="w-3.5 h-3.5" /> Resolve
                  </button>
                ) : (
                  <button
                    onClick={() => updateStatusMutation.mutate({ id: activeConversation.id, status: 'open' })}
                    className="px-2.5 py-1 text-xs font-semibold rounded-lg border dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                  >
                    Reopen Chat
                  </button>
                )}
              </div>
            </div>

            {/* 24-Hour Policy Window Alert Banner */}
            <div className={`px-4 py-2 border-b flex items-center justify-between text-xs font-medium ${
              isWindowActive
                ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-700 dark:text-emerald-300'
                : 'bg-amber-500/10 border-amber-500/20 text-amber-700 dark:text-amber-300'
            }`}>
              <div className="flex items-center gap-2">
                {isWindowActive ? (
                  <ShieldCheck className="w-4 h-4 text-emerald-500 flex-shrink-0" />
                ) : (
                  <AlertTriangle className="w-4 h-4 text-amber-500 flex-shrink-0" />
                )}
                <span>
                  {isWindowActive
                    ? '24-hour service session active. Freeform responses allowed until ' +
                      new Date(activeConversation.window_expires_at!).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                    : '24-hour session expired. Meta Cloud API policy requires pre-approved message templates.'}
                </span>
              </div>

              {!isWindowActive && (
                <button
                  onClick={() => setIsTemplateModalOpen(true)}
                  className="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded text-[11px] font-bold transition flex items-center gap-1"
                >
                  <FileText className="w-3 h-3" /> Select Approved Template
                </button>
              )}
            </div>

            {/* Messages Stream */}
            <div className="flex-1 overflow-y-auto p-4 space-y-3">
              {timelineItems.map((item) => {
                if (item.type === 'note') {
                  const note = item.data;
                  return (
                    <div
                      key={`note-${note.id}`}
                      className="max-w-lg mx-auto p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 shadow-xs space-y-1"
                    >
                      <div className="flex items-center justify-between text-[11px] text-amber-700 dark:text-amber-300 font-bold">
                        <span className="flex items-center gap-1">
                          <Lock className="w-3 h-3" /> Internal Team Note
                        </span>
                        <span className="font-normal opacity-75">{new Date(note.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                      </div>
                      <p className="text-xs text-amber-900 dark:text-amber-200 leading-relaxed whitespace-pre-wrap">
                        {note.content}
                      </p>
                      <div className="text-[10px] text-amber-600 dark:text-amber-400 font-medium">
                        By {note.author?.name || 'Agent'}
                      </div>
                    </div>
                  );
                }

                const msg = item.data;
                const isInbound = msg.direction === 'inbound';

                return (
                  <div
                    key={`msg-${msg.id}`}
                    className={`flex flex-col ${isInbound ? 'items-start' : 'items-end'}`}
                  >
                    <div
                      className={`max-w-[75%] rounded-2xl p-3 shadow-xs space-y-1.5 text-xs ${
                        isInbound
                          ? isDark
                            ? 'bg-slate-800 text-slate-100 rounded-tl-xs'
                            : 'bg-white text-slate-800 border border-slate-200 rounded-tl-xs'
                          : 'bg-emerald-600 text-white rounded-tr-xs'
                      }`}
                    >
                      {/* Sender actor badge */}
                      <div className="flex items-center justify-between gap-3 text-[10px] opacity-75">
                        <span className="font-semibold capitalize">
                          {isInbound ? activeConversation.customer_name || 'Customer' : msg.sender_actor === 'ai' ? 'AI Assistant' : msg.sender_user?.name || 'Agent'}
                        </span>
                        {msg.message_type === 'template' && (
                          <span className="px-1.5 py-0.2 rounded bg-black/20 text-[9px] uppercase font-mono">
                            Template
                          </span>
                        )}
                      </div>

                      {/* Message Content */}
                      <p className="whitespace-pre-wrap leading-relaxed text-xs">
                        {msg.content}
                      </p>

                      {/* Media Preview if attached */}
                      {msg.media_url && (
                        <div className="mt-1 pt-1 border-t border-white/20">
                          {msg.message_type === 'image' ? (
                            <img src={msg.media_url} alt="Attachment" className="max-h-48 rounded-lg object-cover" />
                          ) : (
                            <a
                              href={msg.media_url}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="inline-flex items-center gap-1.5 underline font-bold"
                            >
                              <Paperclip className="w-3.5 h-3.5" /> View {msg.message_type}
                            </a>
                          )}
                        </div>
                      )}

                      {/* Timestamp & Delivery Checks */}
                      <div className="flex items-center justify-end gap-1 text-[10px] opacity-75 font-mono pt-0.5">
                        <span>{new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                        {!isInbound && (
                          <>
                            {msg.delivery_status === 'read' ? (
                              <CheckCheck className="w-3.5 h-3.5 text-sky-300" title="Read" />
                            ) : msg.delivery_status === 'delivered' ? (
                              <CheckCheck className="w-3.5 h-3.5" title="Delivered" />
                            ) : msg.delivery_status === 'failed' ? (
                              <AlertCircle className="w-3.5 h-3.5 text-rose-300" title={msg.error_message || 'Delivery failed'} />
                            ) : (
                              <Check className="w-3.5 h-3.5" title="Sent" />
                            )}
                          </>
                        )}
                      </div>
                    </div>
                  </div>
                );
              })}
              <div ref={chatEndRef} />
            </div>

            {/* Composer Footer */}
            <div className={`p-3 border-t space-y-2 ${
              isDark ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
            }`}>
              {/* Mode Selector Pill */}
              <div className="flex items-center justify-between text-xs">
                <div className="flex items-center gap-1">
                  <button
                    type="button"
                    onClick={() => setComposerMode('message')}
                    className={`px-3 py-1 rounded-lg text-xs font-semibold transition ${
                      composerMode === 'message'
                        ? 'bg-emerald-600 text-white shadow-xs'
                        : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                    }`}
                  >
                    Reply Customer
                  </button>
                  <button
                    type="button"
                    onClick={() => setComposerMode('note')}
                    className={`px-3 py-1 rounded-lg text-xs font-semibold transition flex items-center gap-1 ${
                      composerMode === 'note'
                        ? 'bg-amber-600 text-white shadow-xs'
                        : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                    }`}
                  >
                    <Lock className="w-3 h-3" /> Internal Team Note
                  </button>
                </div>

                {composerMode === 'message' && (
                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => setIsTemplateModalOpen(true)}
                      className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded transition"
                    >
                      <FileText className="w-3.5 h-3.5 text-blue-500" /> Templates
                    </button>
                    <button
                      type="button"
                      onClick={() => setIsMediaModalOpen(true)}
                      disabled={!isWindowActive}
                      className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded transition disabled:opacity-30"
                    >
                      <Paperclip className="w-3.5 h-3.5 text-emerald-500" /> Media
                    </button>
                  </div>
                )}
              </div>

              {/* Form Input */}
              <form onSubmit={handleSendMessage} className="flex items-end gap-2">
                {composerMode === 'note' ? (
                  <textarea
                    rows={2}
                    value={internalNoteText}
                    onChange={(e) => setInternalNoteText(e.target.value)}
                    placeholder="Write private team note (invisible to customer)..."
                    className={`flex-1 p-2.5 text-xs rounded-xl border outline-none ${
                      isDark ? 'bg-slate-800 border-amber-900/60 text-amber-200' : 'bg-amber-50 border-amber-300 text-amber-950'
                    }`}
                  />
                ) : (
                  <textarea
                    rows={2}
                    value={messageText}
                    onChange={(e) => setMessageText(e.target.value)}
                    disabled={!isWindowActive}
                    placeholder={
                      isWindowActive
                        ? 'Type WhatsApp message (Enter to send)...'
                        : '24-hour window expired. Please send an approved template.'
                    }
                    className={`flex-1 p-2.5 text-xs rounded-xl border outline-none ${
                      isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-slate-50 border-slate-300 text-slate-900'
                    } disabled:opacity-40`}
                  />
                )}

                <button
                  type="submit"
                  disabled={composerMode === 'message' && !isWindowActive}
                  className={`p-3 rounded-xl text-white font-semibold transition disabled:opacity-40 ${
                    composerMode === 'note'
                      ? 'bg-amber-600 hover:bg-amber-700'
                      : 'bg-emerald-600 hover:bg-emerald-700'
                  }`}
                >
                  <Send className="w-4 h-4" />
                </button>
              </form>
            </div>
          </>
        ) : (
          <div className="flex-1 flex items-center justify-center p-8 text-center text-slate-500">
            <div className="space-y-2">
              <MessageSquare className="w-12 h-12 mx-auto text-slate-400" />
              <div className="font-semibold text-slate-700 dark:text-slate-300">Select a Conversation</div>
              <p className="text-xs max-w-xs text-slate-400">
                Choose a customer conversation from the left to read messages, reply, or add team notes.
              </p>
            </div>
          </div>
        )}
      </div>

      {/* ========================================================================= */}
      {/* 3. RIGHT PANE: Customer 360 CRM Sidebar                                   */}
      {/* ========================================================================= */}
      {activeConversation && (
        <div className={`w-72 lg:w-80 flex flex-col border-l flex-shrink-0 overflow-y-auto p-4 space-y-5 ${
          isDark ? 'bg-slate-900/70 border-slate-800' : 'bg-slate-50 border-slate-200'
        }`}>
          <div>
            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
              Customer CRM Profile
            </h4>

            {/* Contact / Lead Card */}
            <div className={`p-3.5 rounded-xl border space-y-2 ${
              isDark ? 'bg-slate-800/60 border-slate-700' : 'bg-white border-slate-200'
            }`}>
              <div className="font-bold text-sm text-slate-900 dark:text-white">
                {activeConversation.customer_name || 'WhatsApp Contact'}
              </div>
              <div className="text-xs font-mono text-emerald-600 dark:text-emerald-400">
                {activeConversation.phone_number}
              </div>

              {activeConversation.contact ? (
                <div className="text-xs text-slate-500 pt-1 border-t dark:border-slate-700">
                  <span>Linked Contact: </span>
                  <strong className="text-slate-800 dark:text-slate-200">{activeConversation.contact.name}</strong>
                  {activeConversation.contact.email && <div>{activeConversation.contact.email}</div>}
                </div>
              ) : activeConversation.lead ? (
                <div className="text-xs text-slate-500 pt-1 border-t dark:border-slate-700">
                  <span>Linked CRM Lead: </span>
                  <strong className="text-slate-800 dark:text-slate-200">{activeConversation.lead.name}</strong>
                  <div>Status: <span className="font-semibold text-blue-500">{activeConversation.lead.status}</span></div>
                </div>
              ) : (
                <div className="pt-2">
                  <button
                    onClick={() => {
                      setLeadForm({
                        name: activeConversation.customer_name || '',
                        email: '',
                        company: '',
                      });
                      setIsCreateLeadOpen(true);
                    }}
                    className="w-full py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold transition flex items-center justify-center gap-1.5"
                  >
                    <UserPlus className="w-3.5 h-3.5" /> Convert to CRM Lead
                  </button>
                </div>
              )}
            </div>
          </div>

          {/* Conversation Settings */}
          <div className="space-y-3 text-xs">
            <h4 className="font-bold uppercase tracking-wider text-slate-500 text-[11px]">
              Conversation Metadata
            </h4>

            <div>
              <label className="block text-slate-500 text-[10px] uppercase font-semibold mb-1">
                Assigned Agent
              </label>
              <select
                value={activeConversation.assigned_user_id || ''}
                onChange={(e) =>
                  assignMutation.mutate({
                    id: activeConversation.id,
                    assignedUserId: e.target.value ? parseInt(e.target.value) : null,
                  })
                }
                className={`w-full p-2 rounded-lg border outline-none ${
                  isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                }`}
              >
                <option value="">Unassigned</option>
                {currentUser && <option value={currentUser.id}>{currentUser.name} (You)</option>}
              </select>
            </div>

            <div className="p-3 rounded-xl border bg-slate-100/50 dark:bg-slate-800/40 dark:border-slate-800 space-y-1.5 text-[11px]">
              <div className="flex justify-between">
                <span className="text-slate-500">Messaging Window:</span>
                <span className={`font-semibold ${isWindowActive ? 'text-emerald-500' : 'text-amber-500'}`}>
                  {isWindowActive ? 'Active' : 'Expired'}
                </span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">AI Mode:</span>
                <span className="font-semibold text-slate-700 dark:text-slate-300">
                  {activeConversation.is_ai_handled ? 'Enabled' : 'Human Only'}
                </span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Messages Logged:</span>
                <span className="font-mono font-bold text-slate-800 dark:text-slate-200">
                  {activeConversation.messages?.length || 0}
                </span>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )}

      {/* ========================================================================= */}
      {/* 4. MODALS: Templates, Media, Settings, Create Lead                        */}
      {/* ========================================================================= */}

      {/* Template Selection Modal */}
      {isTemplateModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div className={`w-full max-w-lg rounded-2xl border p-6 space-y-4 shadow-2xl ${
            isDark ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
          }`}>
            <div className="flex items-center justify-between border-b pb-3 dark:border-slate-800">
              <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <FileText className="w-4 h-4 text-blue-500" /> Send Approved WhatsApp Template
              </h3>
              <button onClick={() => setIsTemplateModalOpen(false)} className="text-slate-400 hover:text-white">
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="space-y-3 text-xs">
              <div>
                <label className="block font-semibold mb-1 text-slate-600 dark:text-slate-400">
                  Select Meta Approved Template *
                </label>
                <select
                  value={selectedTemplate?.id || ''}
                  onChange={(e) => {
                    const t = templates.find((tmp) => tmp.id === parseInt(e.target.value));
                    setSelectedTemplate(t || null);
                  }}
                  className={`w-full p-2.5 rounded-lg border outline-none ${
                    isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                  }`}
                >
                  <option value="">-- Choose Template --</option>
                  {templates.map((t) => (
                    <option key={t.id} value={t.id}>
                      {t.template_name} ({t.category} - {t.language})
                    </option>
                  ))}
                </select>
              </div>

              {selectedTemplate && (
                <div className="p-3 rounded-lg border bg-slate-50 dark:bg-slate-800/40 dark:border-slate-700 space-y-2">
                  <div className="font-semibold text-slate-700 dark:text-slate-300">
                    Template Body Preview:
                  </div>
                  <p className="font-mono text-[11px] text-slate-600 dark:text-slate-400 whitespace-pre-wrap">
                    {selectedTemplate.body_text}
                  </p>
                </div>
              )}

              {/* Template Parameters Inputs */}
              {selectedTemplate && (
                <div className="space-y-2">
                  <label className="block font-semibold text-slate-600 dark:text-slate-400">
                    Template Variables (&#123;&#123;1&#125;&#125;, &#123;&#123;2&#125;&#125;...):
                  </label>
                  {[0, 1, 2].map((idx) => (
                    <input
                      key={idx}
                      type="text"
                      placeholder={`Variable {{${idx + 1}}} value`}
                      value={templateParams[idx]}
                      onChange={(e) => {
                        const next = [...templateParams];
                        next[idx] = e.target.value;
                        setTemplateParams(next);
                      }}
                      className={`w-full p-2 rounded-lg border outline-none font-mono ${
                        isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                      }`}
                    />
                  ))}
                </div>
              )}

              <div className="flex justify-end gap-2 pt-3 border-t dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsTemplateModalOpen(false)}
                  className="px-4 py-2 border rounded-lg font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  onClick={handleSendTemplate}
                  disabled={!selectedTemplate}
                  className="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold shadow-xs transition disabled:opacity-40"
                >
                  Send Template
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Media Attachment Modal */}
      {isMediaModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div className={`w-full max-w-md rounded-2xl border p-6 space-y-4 shadow-2xl ${
            isDark ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
          }`}>
            <div className="flex items-center justify-between border-b pb-3 dark:border-slate-800">
              <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <Paperclip className="w-4 h-4 text-emerald-500" /> Send Media Attachment
              </h3>
              <button onClick={() => setIsMediaModalOpen(false)} className="text-slate-400 hover:text-white">
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="space-y-3 text-xs">
              <div>
                <label className="block font-semibold mb-1 text-slate-600 dark:text-slate-400">Media Type</label>
                <select
                  value={mediaType}
                  onChange={(e) => setMediaType(e.target.value as any)}
                  className={`w-full p-2.5 rounded-lg border outline-none ${
                    isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                  }`}
                >
                  <option value="image">Image (JPEG/PNG)</option>
                  <option value="document">Document (PDF/DOCX)</option>
                  <option value="video">Video (MP4)</option>
                </select>
              </div>

              <div>
                <label className="block font-semibold mb-1 text-slate-600 dark:text-slate-400">Public Media URL *</label>
                <input
                  type="url"
                  required
                  placeholder="https://example.com/proposal.pdf"
                  value={mediaUrl}
                  onChange={(e) => setMediaUrl(e.target.value)}
                  className={`w-full p-2.5 rounded-lg border outline-none font-mono ${
                    isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                  }`}
                />
              </div>

              <div>
                <label className="block font-semibold mb-1 text-slate-600 dark:text-slate-400">Caption (Optional)</label>
                <input
                  type="text"
                  placeholder="Here is the requested document"
                  value={mediaCaption}
                  onChange={(e) => setMediaCaption(e.target.value)}
                  className={`w-full p-2.5 rounded-lg border outline-none ${
                    isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                  }`}
                />
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsMediaModalOpen(false)}
                  className="px-4 py-2 border rounded-lg font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  onClick={handleSendMedia}
                  disabled={!mediaUrl.trim()}
                  className="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-semibold shadow-xs transition disabled:opacity-40"
                >
                  Dispatch Media
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Convert to Lead Modal */}
      {isCreateLeadOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div className={`w-full max-w-md rounded-2xl border p-6 space-y-4 shadow-2xl ${
            isDark ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
          }`}>
            <div className="flex items-center justify-between border-b pb-3 dark:border-slate-800">
              <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <UserPlus className="w-4 h-4 text-blue-500" /> Convert WhatsApp Contact to CRM Lead
              </h3>
              <button onClick={() => setIsCreateLeadOpen(false)} className="text-slate-400 hover:text-white">
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={handleCreateLead} className="space-y-3 text-xs">
              <div>
                <label className="block font-semibold mb-1 text-slate-600 dark:text-slate-400">Lead Full Name *</label>
                <input
                  type="text"
                  required
                  value={leadForm.name}
                  onChange={(e) => setLeadForm({ ...leadForm, name: e.target.value })}
                  className={`w-full p-2.5 rounded-lg border outline-none ${
                    isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                  }`}
                />
              </div>

              <div>
                <label className="block font-semibold mb-1 text-slate-600 dark:text-slate-400">Email Address</label>
                <input
                  type="email"
                  value={leadForm.email}
                  onChange={(e) => setLeadForm({ ...leadForm, email: e.target.value })}
                  placeholder="lead@company.com"
                  className={`w-full p-2.5 rounded-lg border outline-none ${
                    isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                  }`}
                />
              </div>

              <div>
                <label className="block font-semibold mb-1 text-slate-600 dark:text-slate-400">Company Name</label>
                <input
                  type="text"
                  value={leadForm.company}
                  onChange={(e) => setLeadForm({ ...leadForm, company: e.target.value })}
                  placeholder="Wayne Corp"
                  className={`w-full p-2.5 rounded-lg border outline-none ${
                    isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                  }`}
                />
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsCreateLeadOpen(false)}
                  className="px-4 py-2 border rounded-lg font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createLeadMutation.isPending}
                  className="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold shadow-xs transition disabled:opacity-40"
                >
                  Create CRM Lead
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Cloud API Settings Modal */}
      {isSettingsModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div className={`w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl border p-6 space-y-6 shadow-2xl ${
            isDark ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
          }`}>
            <div className="flex items-center justify-between border-b pb-3 dark:border-slate-800">
              <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <Settings className="w-4 h-4 text-emerald-500" /> WhatsApp Cloud API Settings & Numbers
              </h3>
              <button onClick={() => setIsSettingsModalOpen(false)} className="text-slate-400 hover:text-white">
                <X className="w-4 h-4" />
              </button>
            </div>

            {/* Connected Numbers List */}
            <div className="space-y-3">
              <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                Connected Phone Numbers
              </h4>

              {phoneNumbers.length === 0 ? (
                <div className="p-4 rounded-xl border text-xs text-slate-500 dark:border-slate-800 text-center">
                  No phone numbers connected yet. Register one below.
                </div>
              ) : (
                <div className="space-y-2">
                  {phoneNumbers.map((phone) => (
                    <div
                      key={phone.id}
                      className={`p-3.5 rounded-xl border flex items-center justify-between ${
                        isDark ? 'bg-slate-800/50 border-slate-700' : 'bg-slate-50 border-slate-200'
                      }`}
                    >
                      <div>
                        <div className="font-bold text-xs text-slate-900 dark:text-white flex items-center gap-2">
                          <Phone className="w-3.5 h-3.5 text-emerald-500" />
                          {phone.display_phone_number}
                          {phone.is_default && (
                            <span className="text-[10px] px-1.5 rounded bg-emerald-500/10 text-emerald-500 font-semibold">
                              Default
                            </span>
                          )}
                        </div>
                        <div className="text-[11px] text-slate-500 font-mono mt-0.5">
                          ID: {phone.phone_number_id} | Verified Name: {phone.verified_name || 'N/A'}
                        </div>
                      </div>

                      <div className="flex items-center gap-2">
                        <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-500">
                          {phone.quality_rating}
                        </span>
                        <button
                          type="button"
                          onClick={() => testConnMutation.mutate(phone.id)}
                          className="px-2.5 py-1 text-xs border rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                        >
                          Ping Test
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>

            {/* Connect New Phone Form */}
            <div className="space-y-3 pt-3 border-t dark:border-slate-800">
              <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                Register Phone Number
              </h4>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div>
                  <label className="block text-slate-500 mb-1">Phone Number ID (Meta Graph)</label>
                  <input
                    type="text"
                    placeholder="e.g. 109283746501928"
                    value={phoneForm.phone_number_id}
                    onChange={(e) => setPhoneForm({ ...phoneForm, phone_number_id: e.target.value })}
                    className={`w-full p-2 rounded-lg border outline-none font-mono ${
                      isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                    }`}
                  />
                </div>
                <div>
                  <label className="block text-slate-500 mb-1">Display Phone Number (E.164)</label>
                  <input
                    type="text"
                    placeholder="+1 555-123-4567"
                    value={phoneForm.display_phone_number}
                    onChange={(e) => setPhoneForm({ ...phoneForm, display_phone_number: e.target.value })}
                    className={`w-full p-2 rounded-lg border outline-none font-mono ${
                      isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                    }`}
                  />
                </div>
              </div>
              <button
                type="button"
                onClick={async () => {
                  if (!phoneForm.phone_number_id || !phoneForm.display_phone_number) return;
                  await connectPhoneMutation.mutateAsync({
                    whatsapp_account_id: phoneForm.whatsapp_account_id,
                    phone_number_id: phoneForm.phone_number_id,
                    display_phone_number: phoneForm.display_phone_number,
                    is_default: true,
                  });
                  setPhoneForm({ ...phoneForm, phone_number_id: '', display_phone_number: '' });
                }}
                className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold transition"
              >
                Register Phone Number
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
