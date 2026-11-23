import React, { useState } from 'react';
import {
  LifeBuoy,
  MessageSquare,
  AlertTriangle,
  Clock,
  CheckCircle2,
  Plus,
  Search,
  Filter,
  User,
  Send,
  Lock,
  Globe,
  Bot,
  Sparkles,
  Star,
  RotateCcw,
  Tag,
  ShieldCheck,
  ChevronRight,
  HelpCircle,
  TrendingUp,
  FolderOpen,
  ArrowRight,
  Check,
  Phone,
  Mail,
  Smartphone,
} from 'lucide-react';
import {
  useTickets,
  useTicket,
  useCreateTicket,
  useUpdateTicket,
  useAssignTicket,
  useSendTicketMessage,
  useResolveTicket,
  useCloseTicket,
  useReopenTicket,
  useSubmitTicketCsat,
  useTicketAiAssistance,
  useTicketAnalytics,
  useTicketCategories,
  useTicketSlaPolicies,
} from '../../hooks/useSupportTickets';
import { SupportTicketItem, SupportAiAssistanceData } from '../../api/types';

export const SupportTicketHubView: React.FC = () => {
  // Filter states
  const [selectedTicketId, setSelectedTicketId] = useState<number | null>(null);
  const [statusFilter, setStatusFilter] = useState('');
  const [priorityFilter, setPriorityFilter] = useState('');
  const [slaFilter, setSlaFilter] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [channelFilter, setChannelFilter] = useState('');

  // Modals & Panels
  const [isCreateTicketOpen, setIsCreateTicketOpen] = useState(false);
  const [isResolveModalOpen, setIsResolveModalOpen] = useState(false);
  const [isReopenModalOpen, setIsReopenModalOpen] = useState(false);
  const [isCsatModalOpen, setIsCsatModalOpen] = useState(false);
  const [resolutionNotes, setResolutionNotes] = useState('');
  const [reopenReason, setReopenReason] = useState('');
  const [csatRating, setCsatRating] = useState(5);
  const [csatComment, setCsatComment] = useState('');

  // Composer state
  const [replyMessage, setReplyMessage] = useState('');
  const [composerMode, setComposerMode] = useState<'public' | 'internal'>('public');

  // Queries
  const { data: tickets = [], isLoading: isLoadingTickets } = useTickets({
    status: statusFilter || undefined,
    priority: priorityFilter || undefined,
    sla_status: slaFilter || undefined,
    source_channel: channelFilter || undefined,
    q: searchQuery || undefined,
  });

  const { data: selectedTicket } = useTicket(selectedTicketId);
  const { data: analytics } = useTicketAnalytics();
  const { data: categories = [] } = useTicketCategories();
  const { data: slaPolicies = [] } = useTicketSlaPolicies();

  // Mutations
  const createTicketMutation = useCreateTicket();
  const assignTicketMutation = useAssignTicket();
  const sendMessageMutation = useSendTicketMessage();
  const resolveTicketMutation = useResolveTicket();
  const closeTicketMutation = useCloseTicket();
  const reopenTicketMutation = useReopenTicket();
  const submitCsatMutation = useSubmitTicketCsat();
  const aiAssistanceMutation = useTicketAiAssistance();

  // Create ticket form state
  const [newTicket, setNewTicket] = useState({
    subject: '',
    description: '',
    priority: 'medium' as const,
    source_channel: 'web_chat' as const,
    category_id: '' as string | number,
    tags: [] as string[],
  });

  const handleCreateTicket = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newTicket.subject) return;

    const res = await createTicketMutation.mutateAsync({
      subject: newTicket.subject,
      description: newTicket.description,
      priority: newTicket.priority,
      source_channel: newTicket.source_channel,
      category_id: newTicket.category_id ? Number(newTicket.category_id) : undefined,
    });

    setNewTicket({
      subject: '',
      description: '',
      priority: 'medium',
      source_channel: 'web_chat',
      category_id: '',
      tags: [],
    });
    setIsCreateTicketOpen(false);
    if (res?.id) setSelectedTicketId(res.id);
  };

  const handleSendMessage = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedTicket || !replyMessage.trim()) return;

    await sendMessageMutation.mutateAsync({
      id: selectedTicket.id,
      data: {
        message: replyMessage,
        is_internal: composerMode === 'internal',
        channel: composerMode === 'internal' ? 'internal' : selectedTicket.source_channel,
        status: composerMode === 'public' ? 'waiting_customer' : undefined,
      },
    });

    setReplyMessage('');
  };

  const handleTriggerAiAssistance = async () => {
    if (!selectedTicket) return;
    const aiData = await aiAssistanceMutation.mutateAsync({
      id: selectedTicket.id,
    });
  };

  const insertAiDraft = () => {
    if (selectedTicket?.ai_reply_draft) {
      setReplyMessage(selectedTicket.ai_reply_draft);
      setComposerMode('public');
    }
  };

  const handleResolve = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedTicket || !resolutionNotes.trim()) return;

    await resolveTicketMutation.mutateAsync({
      id: selectedTicket.id,
      resolutionNotes,
    });

    setResolutionNotes('');
    setIsResolveModalOpen(false);
  };

  const handleReopen = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedTicket || !reopenReason.trim()) return;

    await reopenTicketMutation.mutateAsync({
      id: selectedTicket.id,
      reopenReason,
    });

    setReopenReason('');
    setIsReopenModalOpen(false);
  };

  const handleCsat = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedTicket) return;

    await submitCsatMutation.mutateAsync({
      id: selectedTicket.id,
      rating: csatRating,
      comment: csatComment,
    });

    setCsatComment('');
    setIsCsatModalOpen(false);
  };

  return (
    <div className="flex flex-col h-full bg-slate-50 dark:bg-slate-950 p-6 space-y-6 overflow-y-auto">
      {/* Top Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-200 dark:border-slate-800 pb-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <LifeBuoy className="w-7 h-7 text-indigo-600 dark:text-indigo-400" />
            Customer Support & Helpdesk
          </h1>
          <p className="text-sm text-slate-500 dark:text-slate-400">
            Multi-channel ticket resolution, SLA compliance tracking, and AI-assisted troubleshooting.
          </p>
        </div>

        <button
          onClick={() => setIsCreateTicketOpen(true)}
          className="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3.5 py-2 rounded-lg text-xs font-semibold shadow-sm transition"
        >
          <Plus className="w-4 h-4" />
          New Ticket
        </button>
      </div>

      {/* KPI Analytics Strip */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-3.5 rounded-xl shadow-sm">
          <div className="flex items-center justify-between text-slate-500 text-xs">
            <span>Open Tickets</span>
            <LifeBuoy className="w-4 h-4 text-blue-500" />
          </div>
          <div className="text-2xl font-bold text-slate-900 dark:text-white mt-1">
            {analytics?.open_tickets ?? tickets.filter((t) => t.status !== 'closed' && t.status !== 'resolved').length}
          </div>
          <div className="text-[11px] text-slate-400 mt-0.5">
            {analytics?.total_tickets ?? tickets.length} total created
          </div>
        </div>

        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-3.5 rounded-xl shadow-sm">
          <div className="flex items-center justify-between text-slate-500 text-xs">
            <span>SLA Health</span>
            <AlertTriangle className="w-4 h-4 text-rose-500" />
          </div>
          <div className="text-2xl font-bold text-slate-900 dark:text-white mt-1">
            {analytics?.sla_compliance_rate ?? 100}%
          </div>
          <div className="text-[11px] text-rose-500 mt-0.5">
            {analytics?.breached_tickets ?? tickets.filter((t) => t.sla_status === 'breached').length} breached
          </div>
        </div>

        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-3.5 rounded-xl shadow-sm">
          <div className="flex items-center justify-between text-slate-500 text-xs">
            <span>Avg Response</span>
            <Clock className="w-4 h-4 text-amber-500" />
          </div>
          <div className="text-2xl font-bold text-slate-900 dark:text-white mt-1">
            {analytics?.avg_response_minutes ?? 0}m
          </div>
          <div className="text-[11px] text-slate-400 mt-0.5">
            First response target
          </div>
        </div>

        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-3.5 rounded-xl shadow-sm">
          <div className="flex items-center justify-between text-slate-500 text-xs">
            <span>Resolution Rate</span>
            <CheckCircle2 className="w-4 h-4 text-emerald-500" />
          </div>
          <div className="text-2xl font-bold text-slate-900 dark:text-white mt-1">
            {analytics?.resolved_tickets ?? tickets.filter((t) => t.status === 'resolved').length}
          </div>
          <div className="text-[11px] text-slate-400 mt-0.5">
            Reopen rate: {analytics?.reopen_rate ?? 0}%
          </div>
        </div>

        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-3.5 rounded-xl shadow-sm">
          <div className="flex items-center justify-between text-slate-500 text-xs">
            <span>CSAT Score</span>
            <Star className="w-4 h-4 text-amber-400 fill-amber-400" />
          </div>
          <div className="text-2xl font-bold text-slate-900 dark:text-white mt-1">
            {analytics?.csat_average ? `${analytics.csat_average} / 5` : '4.8 / 5'}
          </div>
          <div className="text-[11px] text-emerald-500 mt-0.5">
            Customer satisfaction
          </div>
        </div>
      </div>

      {/* Main Helpdesk Workspace (Split Pane) */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {/* LEFT / TICKET LIST COLUMN */}
        <div className="lg:col-span-5 space-y-3">
          {/* Filters Bar */}
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-3 rounded-xl shadow-sm space-y-2">
            <div className="relative">
              <Search className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
              <input
                type="text"
                placeholder="Search ticket #, subject..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white"
              />
            </div>

            <div className="grid grid-cols-3 gap-1.5">
              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className="py-1 px-2 text-[11px] bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded text-slate-700 dark:text-slate-300"
              >
                <option value="">All Status</option>
                <option value="new">New</option>
                <option value="open">Open</option>
                <option value="in_progress">In Progress</option>
                <option value="waiting_customer">Waiting Customer</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
              </select>

              <select
                value={priorityFilter}
                onChange={(e) => setPriorityFilter(e.target.value)}
                className="py-1 px-2 text-[11px] bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded text-slate-700 dark:text-slate-300"
              >
                <option value="">All Priority</option>
                <option value="urgent">Urgent</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
              </select>

              <select
                value={slaFilter}
                onChange={(e) => setSlaFilter(e.target.value)}
                className="py-1 px-2 text-[11px] bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded text-slate-700 dark:text-slate-300"
              >
                <option value="">All SLA</option>
                <option value="within_sla">Within SLA</option>
                <option value="warning">Warning (80%)</option>
                <option value="breached">Breached</option>
              </select>
            </div>
          </div>

          {/* Ticket Cards Roster */}
          <div className="space-y-2 max-h-[700px] overflow-y-auto pr-1">
            {isLoadingTickets ? (
              <div className="text-center py-8 text-xs text-slate-400">Loading tickets...</div>
            ) : tickets.length === 0 ? (
              <div className="bg-white dark:bg-slate-900 border border-dashed border-slate-200 dark:border-slate-800 p-8 rounded-xl text-center text-xs text-slate-400">
                No tickets matching criteria.
              </div>
            ) : (
              tickets.map((t) => {
                const isSelected = selectedTicketId === t.id;
                return (
                  <div
                    key={t.id}
                    onClick={() => setSelectedTicketId(t.id)}
                    className={`p-3.5 rounded-xl border cursor-pointer transition flex flex-col justify-between ${
                      isSelected
                        ? 'bg-indigo-50/60 dark:bg-indigo-950/40 border-indigo-500 shadow-sm'
                        : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700'
                    }`}
                  >
                    <div>
                      <div className="flex items-center justify-between text-[11px] mb-1.5">
                        <span className="font-mono font-bold text-indigo-600 dark:text-indigo-400">
                          {t.ticket_number || `#${t.id}`}
                        </span>
                        <div className="flex items-center gap-1.5">
                          <span
                            className={`text-[9px] uppercase font-bold px-1.5 py-0.5 rounded ${
                              t.priority === 'urgent'
                                ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400'
                                : t.priority === 'high'
                                ? 'bg-orange-100 text-orange-700'
                                : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                            }`}
                          >
                            {t.priority}
                          </span>
                          <span
                            className={`text-[9px] px-1.5 py-0.5 rounded font-medium ${
                              t.status === 'resolved'
                                ? 'bg-emerald-100 text-emerald-700'
                                : t.status === 'closed'
                                ? 'bg-slate-100 text-slate-600'
                                : t.status === 'waiting_customer'
                                ? 'bg-purple-100 text-purple-700'
                                : 'bg-blue-100 text-blue-700'
                            }`}
                          >
                            {t.status.replace('_', ' ')}
                          </span>
                        </div>
                      </div>

                      <h3 className="font-semibold text-xs text-slate-900 dark:text-white line-clamp-1 mb-1">
                        {t.subject}
                      </h3>

                      <div className="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 mt-2">
                        <span className="truncate max-w-[180px]">
                          {t.customer?.name || 'Guest Customer'}
                        </span>
                        <span
                          className={`text-[9px] px-1.5 py-0.5 rounded font-semibold ${
                            t.sla_status === 'breached'
                              ? 'bg-rose-50 text-rose-600 border border-rose-200'
                              : t.sla_status === 'warning'
                              ? 'bg-amber-50 text-amber-600 border border-amber-200'
                              : 'text-slate-400'
                          }`}
                        >
                          {t.sla_status === 'breached' ? 'SLA Breached' : t.sla_status === 'warning' ? 'SLA Warning' : 'Within SLA'}
                        </span>
                      </div>
                    </div>
                  </div>
                );
              })
            )}
          </div>
        </div>

        {/* RIGHT / TICKET CONVERSATION & AI ASSISTANCE COLUMN */}
        <div className="lg:col-span-7">
          {selectedTicket ? (
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm overflow-hidden flex flex-col">
              {/* Ticket Top Action Bar */}
              <div className="p-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-2">
                  <div className="flex items-center gap-2">
                    <span className="font-mono text-sm font-bold text-indigo-600 dark:text-indigo-400">
                      {selectedTicket.ticket_number}
                    </span>
                    <span
                      className={`text-[10px] font-bold px-2 py-0.5 rounded uppercase ${
                        selectedTicket.priority === 'urgent'
                          ? 'bg-rose-100 text-rose-700'
                          : 'bg-slate-100 text-slate-700'
                      }`}
                    >
                      {selectedTicket.priority}
                    </span>
                    <span className="text-[10px] bg-slate-200 dark:bg-slate-700 px-2 py-0.5 rounded font-medium capitalize">
                      {selectedTicket.status.replace('_', ' ')}
                    </span>
                  </div>

                  {/* Lifecycle actions */}
                  <div className="flex items-center gap-1.5 text-xs">
                    {selectedTicket.status !== 'resolved' && selectedTicket.status !== 'closed' && (
                      <button
                        onClick={() => setIsResolveModalOpen(true)}
                        className="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[11px] font-medium"
                      >
                        Resolve Ticket
                      </button>
                    )}
                    {selectedTicket.status === 'resolved' && (
                      <button
                        onClick={() => closeTicketMutation.mutate({ id: selectedTicket.id })}
                        className="px-2.5 py-1 bg-slate-700 hover:bg-slate-800 text-white rounded text-[11px] font-medium"
                      >
                        Close
                      </button>
                    )}
                    {(selectedTicket.status === 'resolved' || selectedTicket.status === 'closed') && (
                      <button
                        onClick={() => setIsReopenModalOpen(true)}
                        className="px-2.5 py-1 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 rounded text-[11px] font-medium flex items-center gap-1"
                      >
                        <RotateCcw className="w-3 h-3" />
                        Reopen
                      </button>
                    )}
                    <button
                      onClick={() => setIsCsatModalOpen(true)}
                      className="px-2.5 py-1 border border-amber-300 text-amber-700 dark:text-amber-400 rounded text-[11px] font-medium flex items-center gap-1"
                    >
                      <Star className="w-3 h-3" />
                      CSAT
                    </button>
                  </div>
                </div>

                <h2 className="text-base font-bold text-slate-900 dark:text-white">
                  {selectedTicket.subject}
                </h2>
                <div className="flex items-center gap-4 text-xs text-slate-500 mt-1">
                  <span>Customer: <strong className="text-slate-700 dark:text-slate-300">{selectedTicket.customer?.name || 'Guest'}</strong></span>
                  <span>Channel: <strong className="text-slate-700 dark:text-slate-300">{selectedTicket.source_channel}</strong></span>
                  <span>Agent: <strong className="text-slate-700 dark:text-slate-300">{selectedTicket.assigned_user?.name || 'Unassigned'}</strong></span>
                </div>
              </div>

              {/* AI Support Assistant Strip */}
              <div className="bg-indigo-50/70 dark:bg-indigo-950/40 p-4 border-b border-indigo-100 dark:border-indigo-900/40">
                <div className="flex items-center justify-between mb-2">
                  <div className="flex items-center gap-1.5 text-xs font-bold text-indigo-900 dark:text-indigo-200">
                    <Sparkles className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                    AI Support Assistant (Knowledge Base Grounded)
                  </div>
                  <button
                    onClick={handleTriggerAiAssistance}
                    disabled={aiAssistanceMutation.isPending}
                    className="text-[11px] bg-indigo-600 hover:bg-indigo-700 text-white px-2.5 py-1 rounded font-medium transition"
                  >
                    {aiAssistanceMutation.isPending ? 'Analyzing...' : 'Generate AI Assistance'}
                  </button>
                </div>

                {selectedTicket.ai_summary && (
                  <p className="text-xs text-indigo-950 dark:text-indigo-300 mb-2 font-medium">
                    {selectedTicket.ai_summary}
                  </p>
                )}

                {selectedTicket.ai_reply_draft && (
                  <div className="mt-2 bg-white dark:bg-slate-900 p-3 rounded-lg border border-indigo-200 dark:border-indigo-800 text-xs text-slate-700 dark:text-slate-300 space-y-2">
                    <div className="flex justify-between items-center text-[10px] text-slate-400 font-semibold uppercase">
                      <span>Suggested Draft (Draft Only - Review before sending)</span>
                      <button
                        onClick={insertAiDraft}
                        className="text-indigo-600 hover:text-indigo-700 font-bold"
                      >
                        Insert into Composer
                      </button>
                    </div>
                    <p className="whitespace-pre-line text-xs font-mono bg-slate-50 dark:bg-slate-800/60 p-2 rounded">
                      {selectedTicket.ai_reply_draft}
                    </p>
                  </div>
                )}
              </div>

              {/* Message Thread History */}
              <div className="p-4 space-y-3 overflow-y-auto max-h-[400px] bg-slate-50/30 dark:bg-slate-900/20">
                {selectedTicket.messages?.map((msg) => (
                  <div
                    key={msg.id}
                    className={`p-3.5 rounded-xl border text-xs ${
                      msg.is_internal
                        ? 'bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-900 text-amber-900 dark:text-amber-200'
                        : msg.sender_type === 'customer'
                        ? 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200 mr-8'
                        : 'bg-indigo-50/50 dark:bg-indigo-950/20 border-indigo-200 dark:border-indigo-800 text-slate-800 dark:text-slate-200 ml-8'
                    }`}
                  >
                    <div className="flex justify-between items-center text-[10px] opacity-75 mb-1.5">
                      <span className="font-bold flex items-center gap-1">
                        {msg.is_internal ? (
                          <>
                            <Lock className="w-3 h-3 text-amber-600" />
                            Internal Note by {msg.user?.name || 'Agent'}
                          </>
                        ) : msg.sender_type === 'customer' ? (
                          <>
                            <User className="w-3 h-3 text-slate-500" />
                            {msg.customer?.name || selectedTicket.customer?.name || 'Customer'}
                          </>
                        ) : (
                          <>
                            <LifeBuoy className="w-3 h-3 text-indigo-500" />
                            {msg.user?.name || 'Support Agent'}
                          </>
                        )}
                      </span>
                      <span>{msg.created_at}</span>
                    </div>
                    <p className="whitespace-pre-wrap">{msg.message}</p>
                  </div>
                ))}
              </div>

              {/* Reply Composer */}
              <div className="p-4 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
                <div className="flex items-center gap-2 mb-2">
                  <button
                    onClick={() => setComposerMode('public')}
                    className={`px-3 py-1 rounded text-xs font-semibold ${
                      composerMode === 'public'
                        ? 'bg-indigo-600 text-white'
                        : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'
                    }`}
                  >
                    Customer Reply
                  </button>
                  <button
                    onClick={() => setComposerMode('internal')}
                    className={`px-3 py-1 rounded text-xs font-semibold ${
                      composerMode === 'internal'
                        ? 'bg-amber-600 text-white'
                        : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'
                    }`}
                  >
                    Internal Note
                  </button>
                </div>

                <form onSubmit={handleSendMessage} className="space-y-2">
                  <textarea
                    rows={3}
                    value={replyMessage}
                    onChange={(e) => setReplyMessage(e.target.value)}
                    placeholder={
                      composerMode === 'internal'
                        ? 'Add an internal note visible only to your teammates...'
                        : 'Type your message to the customer...'
                    }
                    className="w-full p-2.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  />
                  <div className="flex justify-end">
                    <button
                      type="submit"
                      disabled={sendMessageMutation.isPending || !replyMessage.trim()}
                      className={`flex items-center gap-1.5 px-4 py-1.5 rounded-lg text-xs font-medium text-white shadow-sm transition ${
                        composerMode === 'internal' ? 'bg-amber-600 hover:bg-amber-700' : 'bg-indigo-600 hover:bg-indigo-700'
                      }`}
                    >
                      <Send className="w-3.5 h-3.5" />
                      {composerMode === 'internal' ? 'Add Internal Note' : 'Send Reply'}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          ) : (
            <div className="bg-white dark:bg-slate-900 border border-dashed border-slate-300 dark:border-slate-800 rounded-xl p-16 text-center text-slate-400">
              <LifeBuoy className="w-12 h-12 mx-auto mb-3 opacity-30 text-indigo-500" />
              <h3 className="font-semibold text-slate-700 dark:text-slate-300 text-sm mb-1">
                Select a ticket to begin resolution
              </h3>
              <p className="text-xs text-slate-400 max-w-sm mx-auto">
                Review incoming customer inquiries, inspect SLA countdowns, and generate AI-grounded troubleshooting draft replies.
              </p>
            </div>
          )}
        </div>
      </div>

      {/* MODAL: CREATE TICKET */}
      {isCreateTicketOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 dark:border-slate-800">
            <h2 className="text-base font-bold text-slate-900 dark:text-white mb-4">
              Create Support Ticket
            </h2>
            <form onSubmit={handleCreateTicket} className="space-y-4 text-xs">
              <div>
                <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Subject *</label>
                <input
                  type="text"
                  required
                  value={newTicket.subject}
                  onChange={(e) => setNewTicket({ ...newTicket, subject: e.target.value })}
                  placeholder="e.g. Cannot complete transaction via Stripe gateway"
                  className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Problem Description</label>
                <textarea
                  rows={3}
                  value={newTicket.description}
                  onChange={(e) => setNewTicket({ ...newTicket, description: e.target.value })}
                  placeholder="Full error message, steps to reproduce..."
                  className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Priority</label>
                  <select
                    value={newTicket.priority}
                    onChange={(e) => setNewTicket({ ...newTicket, priority: e.target.value as any })}
                    className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                  >
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                  </select>
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Source Channel</label>
                  <select
                    value={newTicket.source_channel}
                    onChange={(e) => setNewTicket({ ...newTicket, source_channel: e.target.value as any })}
                    className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                  >
                    <option value="web_chat">Website Chat</option>
                    <option value="whatsapp">WhatsApp Business</option>
                    <option value="email">Email</option>
                    <option value="phone">Phone / Voice</option>
                    <option value="portal">Customer Portal</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Category</label>
                <select
                  value={newTicket.category_id}
                  onChange={(e) => setNewTicket({ ...newTicket, category_id: e.target.value })}
                  className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                >
                  <option value="">General Support</option>
                  {categories.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name}
                    </option>
                  ))}
                </select>
              </div>

              <div className="flex justify-end gap-2 pt-3">
                <button
                  type="button"
                  onClick={() => setIsCreateTicketOpen(false)}
                  className="px-4 py-2 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-600 dark:text-slate-400"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createTicketMutation.isPending}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium"
                >
                  Create Ticket
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL: RESOLVE TICKET */}
      {isResolveModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-2xl max-w-sm w-full p-5 shadow-xl border border-slate-200 dark:border-slate-800">
            <h3 className="font-bold text-sm text-slate-900 dark:text-white mb-2">
              Mark Ticket as Resolved
            </h3>
            <form onSubmit={handleResolve} className="space-y-3 text-xs">
              <div>
                <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Resolution Notes *</label>
                <textarea
                  required
                  rows={3}
                  value={resolutionNotes}
                  onChange={(e) => setResolutionNotes(e.target.value)}
                  placeholder="Explain what steps or fixes were implemented..."
                  className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setIsResolveModalOpen(false)}
                  className="px-3 py-1.5 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-600"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium"
                >
                  Confirm Resolved
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL: REOPEN TICKET */}
      {isReopenModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-2xl max-w-sm w-full p-5 shadow-xl border border-slate-200 dark:border-slate-800">
            <h3 className="font-bold text-sm text-slate-900 dark:text-white mb-2">
              Reopen Support Ticket
            </h3>
            <form onSubmit={handleReopen} className="space-y-3 text-xs">
              <div>
                <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Reopen Reason *</label>
                <textarea
                  required
                  rows={3}
                  value={reopenReason}
                  onChange={(e) => setReopenReason(e.target.value)}
                  placeholder="Explain why the issue is still persisting..."
                  className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setIsReopenModalOpen(false)}
                  className="px-3 py-1.5 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-600"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium"
                >
                  Reopen Ticket
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL: CSAT FEEDBACK */}
      {isCsatModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-2xl max-w-sm w-full p-5 shadow-xl border border-slate-200 dark:border-slate-800">
            <h3 className="font-bold text-sm text-slate-900 dark:text-white mb-2">
              Customer Satisfaction (CSAT)
            </h3>
            <form onSubmit={handleCsat} className="space-y-3 text-xs">
              <div>
                <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Rating (1 to 5 Stars)</label>
                <div className="flex items-center gap-2">
                  {[1, 2, 3, 4, 5].map((star) => (
                    <button
                      key={star}
                      type="button"
                      onClick={() => setCsatRating(star)}
                      className="p-1 hover:scale-110 transition"
                    >
                      <Star
                        className={`w-6 h-6 ${
                          star <= csatRating
                            ? 'text-amber-400 fill-amber-400'
                            : 'text-slate-300 dark:text-slate-700'
                        }`}
                      />
                    </button>
                  ))}
                </div>
              </div>

              <div>
                <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Customer Feedback</label>
                <textarea
                  rows={2}
                  value={csatComment}
                  onChange={(e) => setCsatComment(e.target.value)}
                  placeholder="Any extra comments regarding service..."
                  className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setIsCsatModalOpen(false)}
                  className="px-3 py-1.5 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-600"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-medium"
                >
                  Submit CSAT
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
