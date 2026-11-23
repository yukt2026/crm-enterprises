import React, { useState } from 'react';
import {
  Bot,
  Sparkles,
  Send,
  MessageSquare,
  FileText,
  Mic,
  Settings,
  Activity,
  CheckCircle,
  XCircle,
  HelpCircle,
  Layers,
  ShieldCheck,
  TrendingUp,
  Flame,
  AlertTriangle,
  RefreshCw,
  Plus,
  ArrowRight,
  Database,
  Lock,
} from 'lucide-react';
import {
  useAiSessions,
  useAiSessionMessages,
  useAskAiAssistant,
  useAiProposedActions,
  useConfirmAiAction,
  useRejectAiAction,
  useAiProviders,
  useUpdateAiProvider,
  usePingAiProvider,
  useAiMeetings,
  useProcessAiMeetingMock,
  useAiDocuments,
  useGenerateAiContent,
  useAiUsageReports,
} from '../../hooks/useAiAssistant';

interface AiAssistantHubProps {
  isDark?: boolean;
}

export default function AiAssistantHub({ isDark = true }: AiAssistantHubProps) {
  const [activeTab, setActiveTab] = useState<'assistant' | 'actions' | 'sales' | 'meetings' | 'documents' | 'content' | 'usage' | 'providers'>('assistant');

  // Business Chat State
  const [selectedSessionId, setSelectedSessionId] = useState<number | null>(null);
  const [promptInput, setPromptInput] = useState('');

  // Queries & Mutations
  const { data: sessions = [], isLoading: isLoadingSessions } = useAiSessions();
  const { data: activeSessionData } = useAiSessionMessages(selectedSessionId);
  const askMutation = useAskAiAssistant();

  const { data: proposedActions = [] } = useAiProposedActions();
  const confirmActionMutation = useConfirmAiAction();
  const rejectActionMutation = useRejectAiAction();

  const { data: providers = [] } = useAiProviders();
  const pingMutation = usePingAiProvider();
  const updateProviderMutation = useUpdateAiProvider();

  const { data: meetings = [] } = useAiMeetings();
  const processMeetingMutation = useProcessAiMeetingMock();
  const [mockMeetingTitle, setMockMeetingTitle] = useState('');
  const [mockTranscript, setMockTranscript] = useState('');

  const { data: documents = [] } = useAiDocuments();

  const { data: usageReport } = useAiUsageReports();
  const generateContentMutation = useGenerateAiContent();

  const [contentType, setContentType] = useState('whatsapp_reply');
  const [contentPrompt, setContentPrompt] = useState('');
  const [generatedResult, setGeneratedResult] = useState('');

  // Auto select first session
  React.useEffect(() => {
    if (!selectedSessionId && sessions.length > 0) {
      setSelectedSessionId(sessions[0].id);
    }
  }, [sessions, selectedSessionId]);

  const handleSendMessage = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!promptInput.trim()) return;

    const currentPrompt = promptInput.trim();
    setPromptInput('');

    await askMutation.mutateAsync({
      prompt: currentPrompt,
      session_id: selectedSessionId || undefined,
    });
  };

  const handleCreateMeetingMock = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!mockMeetingTitle.trim() || !mockTranscript.trim()) return;

    await processMeetingMutation.mutateAsync({
      title: mockMeetingTitle.trim(),
      mock_transcript: mockTranscript.trim(),
    });

    setMockMeetingTitle('');
    setMockTranscript('');
  };

  const handleGenerateContent = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!contentPrompt.trim()) return;

    const res = await generateContentMutation.mutateAsync({
      content_type: contentType,
      prompt_or_context: contentPrompt.trim(),
    });

    if (res.data?.generated_text) {
      setGeneratedResult(res.data.generated_text);
    }
  };

  return (
    <div className={`p-6 rounded-2xl border space-y-6 shadow-sm min-h-[calc(100vh-140px)] flex flex-col ${
      isDark ? 'bg-slate-950 border-slate-800 text-white' : 'bg-white border-slate-200 text-slate-900'
    }`}>
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4 dark:border-slate-800">
        <div>
          <div className="flex items-center gap-2.5">
            <div className="p-2 rounded-xl bg-purple-500/10 text-purple-500">
              <Sparkles className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-lg font-bold">AI Assistant & Business Intelligence Engine</h2>
              <p className="text-xs text-slate-500 dark:text-slate-400">
                Centralized enterprise AI co-pilot, sales intent scoring, meeting transcription, and automated actions.
              </p>
            </div>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <span className="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
            <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
            AI Providers Active
          </span>
        </div>
      </div>

      {/* Navigation Tabs */}
      <div className={`p-1.5 rounded-xl border flex flex-wrap gap-1.5 ${
        isDark ? 'bg-slate-900 border-slate-800' : 'bg-slate-100 border-slate-200'
      }`}>
        {[
          { key: 'assistant', label: 'Business Assistant', icon: Bot },
          { key: 'actions', label: `Action Gates (${proposedActions.filter((a: any) => a.status === 'pending_confirmation').length})`, icon: ShieldCheck },
          { key: 'meetings', label: 'Meeting Intelligence', icon: Mic },
          { key: 'documents', label: 'Document Intelligence', icon: FileText },
          { key: 'content', label: 'Content Generator', icon: MessageSquare },
          { key: 'usage', label: 'Usage & Metering', icon: Activity },
          { key: 'providers', label: 'Provider Config', icon: Settings },
        ].map((tab) => {
          const Icon = tab.icon;
          const isActive = activeTab === tab.key;
          return (
            <button
              key={tab.key}
              type="button"
              onClick={() => setActiveTab(tab.key as any)}
              className={`flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold transition ${
                isActive
                  ? 'bg-purple-600 text-white shadow-sm'
                  : isDark
                  ? 'text-slate-400 hover:text-white hover:bg-slate-800'
                  : 'text-slate-600 hover:text-slate-900 hover:bg-white'
              }`}
            >
              <Icon className="w-4 h-4" />
              <span>{tab.label}</span>
            </button>
          );
        })}
      </div>

      {/* Main View Area */}
      <div className="flex-1 flex flex-col">
        {/* ========================================================================= */}
        {/* TAB 1: BUSINESS ASSISTANT & CHAT SESSIONS                                 */}
        {/* ========================================================================= */}
        {activeTab === 'assistant' && (
          <div className="flex-1 grid grid-cols-1 lg:grid-cols-4 gap-4 h-[calc(100vh-310px)]">
            {/* Left: Chat Sessions List */}
            <div className={`p-3 rounded-xl border flex flex-col ${
              isDark ? 'bg-slate-900/60 border-slate-800' : 'bg-slate-50 border-slate-200'
            }`}>
              <div className="flex items-center justify-between mb-3">
                <span className="text-xs font-bold uppercase tracking-wider text-slate-500">Conversations</span>
                <button
                  type="button"
                  onClick={() => setSelectedSessionId(null)}
                  className="p-1 rounded-md text-xs bg-purple-500/10 text-purple-400 hover:bg-purple-500/20"
                >
                  <Plus className="w-3.5 h-3.5" />
                </button>
              </div>

              <div className="flex-1 overflow-y-auto space-y-1.5">
                {sessions.map((sess) => (
                  <button
                    key={sess.id}
                    type="button"
                    onClick={() => setSelectedSessionId(sess.id)}
                    className={`w-full text-left p-2.5 rounded-lg text-xs transition border ${
                      selectedSessionId === sess.id
                        ? 'bg-purple-600/10 border-purple-500/30 text-purple-400 font-semibold'
                        : isDark
                        ? 'border-transparent text-slate-400 hover:bg-slate-800/60'
                        : 'border-transparent text-slate-600 hover:bg-white'
                    }`}
                  >
                    <div className="truncate">{sess.title}</div>
                    <div className="text-[10px] text-slate-500 mt-0.5">
                      {new Date(sess.updated_at).toLocaleDateString()}
                    </div>
                  </button>
                ))}
              </div>
            </div>

            {/* Right: Message Stream & Prompt Box */}
            <div className={`lg:col-span-3 p-4 rounded-xl border flex flex-col justify-between ${
              isDark ? 'bg-slate-900/40 border-slate-800' : 'bg-slate-50/50 border-slate-200'
            }`}>
              {/* Message History */}
              <div className="flex-1 overflow-y-auto space-y-3.5 pr-2 mb-4">
                {(!activeSessionData?.messages || activeSessionData.messages.length === 0) ? (
                  <div className="h-full flex flex-col items-center justify-center text-center p-6 text-slate-500 space-y-3">
                    <Bot className="w-10 h-10 text-purple-500/40 animate-pulse" />
                    <div>
                      <p className="text-xs font-semibold text-slate-400">Ask the CRM Business Assistant</p>
                      <p className="text-[11px] text-slate-500 mt-1 max-w-sm">
                        "How many leads arrived today?", "What is today's collected revenue?", or "Create follow-up task for Acme Corp".
                      </p>
                    </div>
                  </div>
                ) : (
                  activeSessionData.messages.map((m) => (
                    <div
                      key={m.id}
                      className={`flex flex-col max-w-[85%] ${
                        m.role === 'user' ? 'ml-auto items-end' : 'mr-auto items-start'
                      }`}
                    >
                      <div className={`p-3 rounded-2xl text-xs leading-relaxed ${
                        m.role === 'user'
                          ? 'bg-purple-600 text-white rounded-br-none'
                          : isDark
                          ? 'bg-slate-800 border border-slate-700 text-slate-200 rounded-bl-none'
                          : 'bg-white border border-slate-300 text-slate-800 rounded-bl-none shadow-xs'
                      }`}>
                        <div className="whitespace-pre-wrap">{m.content}</div>

                        {/* Citations if available */}
                        {m.citations_or_records && (
                          <div className="mt-2 pt-2 border-t border-slate-700/50 text-[10px] text-slate-400 font-mono">
                            <span className="font-bold text-purple-400">Grounded Records: </span>
                            {Object.keys(m.citations_or_records).join(', ')}
                          </div>
                        )}
                      </div>

                      {/* Suggested Actions within message */}
                      {m.suggested_actions && m.suggested_actions.length > 0 && (
                        <div className="mt-2 space-y-1.5 w-full">
                          {m.suggested_actions.map((act: any) => (
                            <div
                              key={act.id}
                              className={`p-2.5 rounded-lg border text-xs flex items-center justify-between gap-2 ${
                                isDark ? 'bg-purple-950/20 border-purple-500/30' : 'bg-purple-50 border-purple-200'
                              }`}
                            >
                              <div>
                                <span className="font-semibold text-purple-400">Proposed Action: </span>
                                <span className="text-slate-300">{act.summary}</span>
                              </div>
                              <button
                                type="button"
                                onClick={() => setActiveTab('actions')}
                                className="px-2 py-1 bg-purple-600 text-white rounded text-[10px] font-semibold hover:bg-purple-700"
                              >
                                Review Action
                              </button>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  ))
                )}
              </div>

              {/* Input Form */}
              <form onSubmit={handleSendMessage} className="flex gap-2">
                <input
                  type="text"
                  placeholder="Ask a question about today's leads, revenue, deals, or propose actions..."
                  value={promptInput}
                  onChange={(e) => setPromptInput(e.target.value)}
                  disabled={askMutation.isPending}
                  className={`flex-1 p-3 rounded-xl border text-xs outline-none focus:ring-1 focus:ring-purple-500 ${
                    isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300 text-slate-900'
                  }`}
                />
                <button
                  type="submit"
                  disabled={askMutation.isPending || !promptInput.trim()}
                  className="px-5 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 transition disabled:opacity-40"
                >
                  <Send className="w-3.5 h-3.5" />
                  <span>Ask</span>
                </button>
              </form>
            </div>
          </div>
        )}

        {/* ========================================================================= */}
        {/* TAB 2: PROPOSED ACTIONS CONFIRMATION GATE                                 */}
        {/* ========================================================================= */}
        {activeTab === 'actions' && (
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-sm font-bold">Action Proposal & Human Confirmation Gate</h3>
                <p className="text-xs text-slate-500">
                  AI proposes actions based on discussions, but explicit employee confirmation is strictly required.
                </p>
              </div>
            </div>

            <div className="grid grid-cols-1 gap-3">
              {proposedActions.length === 0 ? (
                <div className="p-8 text-center text-xs text-slate-500">
                  No proposed actions pending review.
                </div>
              ) : (
                proposedActions.map((act: any) => (
                  <div
                    key={act.id}
                    className={`p-4 rounded-xl border flex flex-col sm:flex-row sm:items-center justify-between gap-4 ${
                      isDark ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200'
                    }`}
                  >
                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                          act.status === 'executed'
                            ? 'bg-emerald-500/10 text-emerald-500'
                            : act.status === 'rejected'
                            ? 'bg-red-500/10 text-red-500'
                            : 'bg-amber-500/10 text-amber-500'
                        }`}>
                          {act.status.replace('_', ' ')}
                        </span>
                        <span className="text-xs font-bold text-slate-300 font-mono">
                          [{act.action_type}]
                        </span>
                      </div>
                      <div className="text-xs font-medium text-white">{act.summary}</div>
                      <div className="text-[11px] text-slate-500 font-mono">
                        Payload: {JSON.stringify(act.payload)}
                      </div>
                    </div>

                    {act.status === 'pending_confirmation' && (
                      <div className="flex items-center gap-2 flex-shrink-0">
                        <button
                          type="button"
                          onClick={() => confirmActionMutation.mutate(act.id)}
                          disabled={confirmActionMutation.isPending}
                          className="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold transition"
                        >
                          Confirm & Execute
                        </button>
                        <button
                          type="button"
                          onClick={() => rejectActionMutation.mutate({ id: act.id })}
                          disabled={rejectActionMutation.isPending}
                          className="px-3 py-1.5 border border-red-500/30 text-red-400 hover:bg-red-500/10 rounded-lg text-xs font-semibold transition"
                        >
                          Reject
                        </button>
                      </div>
                    )}
                  </div>
                ))
              )}
            </div>
          </div>
        )}

        {/* ========================================================================= */}
        {/* TAB 3: MEETING INTELLIGENCE                                               */}
        {/* ========================================================================= */}
        {activeTab === 'meetings' && (
          <div className="space-y-4">
            <div className="p-4 rounded-xl border bg-slate-900/40 border-slate-800 space-y-3">
              <h4 className="text-xs font-bold uppercase tracking-wider text-purple-400">Process Meeting Transcript</h4>
              <form onSubmit={handleCreateMeetingMock} className="space-y-3 text-xs">
                <div>
                  <label className="block text-slate-400 mb-1">Meeting Title</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Acme Corp Enterprise Architecture Review"
                    value={mockMeetingTitle}
                    onChange={(e) => setMockMeetingTitle(e.target.value)}
                    className="w-full p-2.5 rounded-lg border bg-slate-800 border-slate-700 text-white outline-none"
                  />
                </div>
                <div>
                  <label className="block text-slate-400 mb-1">Meeting Conversation Transcript (Audio/Speech)</label>
                  <textarea
                    rows={3}
                    required
                    placeholder="Paste meeting conversation or call transcript to extract customer requirements, objections, and action items..."
                    value={mockTranscript}
                    onChange={(e) => setMockTranscript(e.target.value)}
                    className="w-full p-2.5 rounded-lg border bg-slate-800 border-slate-700 text-white outline-none font-mono text-xs"
                  />
                </div>
                <button
                  type="submit"
                  disabled={processMeetingMutation.isPending}
                  className="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-semibold transition disabled:opacity-40"
                >
                  Analyze & Extract Intelligence
                </button>
              </form>
            </div>

            <div className="space-y-3">
              <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">Processed Meeting Records</h4>
              {meetings.map((m: any) => (
                <div key={m.id} className="p-4 rounded-xl border border-slate-800 bg-slate-900/60 space-y-2">
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-bold text-white">{m.title}</span>
                    <span className="px-2 py-0.5 rounded text-[10px] bg-emerald-500/10 text-emerald-400 font-bold uppercase">
                      {m.processing_status}
                    </span>
                  </div>
                  <p className="text-xs text-slate-300">{m.meeting_summary}</p>
                  {m.action_items && (
                    <div className="text-[11px] text-slate-400 space-y-1">
                      <span className="font-semibold text-purple-400">Action Items: </span>
                      {m.action_items.map((act: any, idx: number) => (
                        <div key={idx} className="ml-2 font-mono">• {act.title} (Due: {act.due_date || 'N/A'})</div>
                      ))}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}

        {/* ========================================================================= */}
        {/* TAB 4: DOCUMENT INTELLIGENCE                                              */}
        {/* ========================================================================= */}
        {activeTab === 'documents' && (
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-sm font-bold">Document Intelligence & OCR Ingestion</h3>
                <p className="text-xs text-slate-500">
                  Extract invoice numbers, GSTINs, amounts, and purchase orders from uploaded customer documents.
                </p>
              </div>
            </div>

            <div className="grid grid-cols-1 gap-3">
              {documents.map((d: any) => (
                <div key={d.id} className="p-4 rounded-xl border border-slate-800 bg-slate-900/60 space-y-2">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <FileText className="w-4 h-4 text-purple-400" />
                      <span className="text-xs font-bold text-white">{d.original_filename}</span>
                    </div>
                    <span className="px-2 py-0.5 rounded text-[10px] bg-blue-500/10 text-blue-400 font-bold uppercase">
                      Confidence: {Math.round(d.overall_confidence * 100)}%
                    </span>
                  </div>
                  <div className="p-3 rounded-lg bg-slate-950/60 font-mono text-[11px] text-slate-300">
                    <pre className="whitespace-pre-wrap">{JSON.stringify(d.extracted_fields, null, 2)}</pre>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* ========================================================================= */}
        {/* TAB 5: CONTENT GENERATOR                                                  */}
        {/* ========================================================================= */}
        {activeTab === 'content' && (
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div className="p-4 rounded-xl border border-slate-800 bg-slate-900/40 space-y-3">
              <h4 className="text-xs font-bold uppercase tracking-wider text-purple-400">CRM Content Generator</h4>
              <form onSubmit={handleGenerateContent} className="space-y-3 text-xs">
                <div>
                  <label className="block text-slate-400 mb-1">Content Type</label>
                  <select
                    value={contentType}
                    onChange={(e) => setContentType(e.target.value)}
                    className="w-full p-2.5 rounded-lg border bg-slate-800 border-slate-700 text-white outline-none"
                  >
                    <option value="whatsapp_reply">WhatsApp Quick Reply</option>
                    <option value="email_reply">Personalized Sales Email</option>
                    <option value="sales_followup">High-Conversion Follow-up</option>
                    <option value="proposal_pitch">Quotation / Proposal Pitch</option>
                    <option value="support_response">Support Ticket Resolution</option>
                  </select>
                </div>
                <div>
                  <label className="block text-slate-400 mb-1">Context & Instructions</label>
                  <textarea
                    rows={4}
                    required
                    placeholder="e.g. Customer requested a 10% discount on 50 licenses. Offer 5% annual discount instead and emphasize 24/7 dedicated support."
                    value={contentPrompt}
                    onChange={(e) => setContentPrompt(e.target.value)}
                    className="w-full p-2.5 rounded-lg border bg-slate-800 border-slate-700 text-white outline-none font-mono text-xs"
                  />
                </div>
                <button
                  type="submit"
                  disabled={generateContentMutation.isPending}
                  className="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-semibold transition disabled:opacity-40"
                >
                  Generate Content Draft
                </button>
              </form>
            </div>

            <div className="p-4 rounded-xl border border-slate-800 bg-slate-900/60 space-y-2">
              <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">Generated Output</h4>
              <div className="p-3 rounded-lg bg-slate-950 min-h-[160px] text-xs font-mono text-slate-200 whitespace-pre-wrap">
                {generatedResult || 'Your AI-generated response will appear here...'}
              </div>
            </div>
          </div>
        )}

        {/* ========================================================================= */}
        {/* TAB 6: USAGE & METERING                                                   */}
        {/* ========================================================================= */}
        {activeTab === 'usage' && (
          <div className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
              <div className="p-4 rounded-xl border border-slate-800 bg-slate-900/60">
                <span className="text-[10px] font-bold text-slate-500 uppercase">Total Requests</span>
                <div className="text-xl font-bold font-mono mt-1 text-white">
                  {usageReport?.summary.total_requests || 0}
                </div>
              </div>
              <div className="p-4 rounded-xl border border-slate-800 bg-slate-900/60">
                <span className="text-[10px] font-bold text-slate-500 uppercase">Total Tokens</span>
                <div className="text-xl font-bold font-mono mt-1 text-white">
                  {(usageReport?.summary.total_tokens || 0).toLocaleString()}
                </div>
              </div>
              <div className="p-4 rounded-xl border border-slate-800 bg-slate-900/60">
                <span className="text-[10px] font-bold text-slate-500 uppercase">Estimated Cost</span>
                <div className="text-xl font-bold font-mono mt-1 text-emerald-400">
                  ${(usageReport?.summary.total_cost || 0).toFixed(4)}
                </div>
              </div>
              <div className="p-4 rounded-xl border border-slate-800 bg-slate-900/60">
                <span className="text-[10px] font-bold text-slate-500 uppercase">Success Rate</span>
                <div className="text-xl font-bold font-mono mt-1 text-purple-400">
                  {usageReport?.summary.success_rate || 100}%
                </div>
              </div>
            </div>
          </div>
        )}

        {/* ========================================================================= */}
        {/* TAB 7: PROVIDER CONFIGURATION                                             */}
        {/* ========================================================================= */}
        {activeTab === 'providers' && (
          <div className="space-y-4">
            <div className="grid grid-cols-1 gap-3">
              {providers.map((p) => (
                <div key={p.id} className="p-4 rounded-xl border border-slate-800 bg-slate-900/60 flex items-center justify-between gap-4">
                  <div>
                    <div className="flex items-center gap-2">
                      <span className="text-xs font-bold text-white">{p.display_name || p.provider_name}</span>
                      <span className="px-2 py-0.5 rounded text-[10px] bg-emerald-500/10 text-emerald-400 font-bold uppercase">
                        {p.health_status}
                      </span>
                    </div>
                    <div className="text-[11px] text-slate-500 font-mono mt-0.5">
                      Default Model: {p.default_model} | Fallback: {p.fallback_model || 'N/A'}
                    </div>
                  </div>

                  <button
                    type="button"
                    onClick={() => pingMutation.mutate(p.id)}
                    disabled={pingMutation.isPending}
                    className="px-3 py-1.5 border border-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-800 transition"
                  >
                    Test Ping
                  </button>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
