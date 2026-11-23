import React, { useState } from 'react';
import {
  LifeBuoy,
  HeartPulse,
  ShieldCheck,
  Star,
  BookOpen,
  Plus,
  Search,
  CheckCircle,
  AlertTriangle,
  Clock,
  Sparkles,
  TrendingUp,
  ThumbsUp,
  ThumbsDown,
  Building,
  User,
  Layers,
  MessageSquare,
  Wrench,
  ChevronRight,
} from 'lucide-react';
import {
  useCustomerHealthScores,
  useRecordCustomerCheckIn,
  useWarrantyContracts,
  useCreateWarrantyContract,
  useRecordContractMaintenance,
  useCustomerFeedback,
  useSubmitCustomerFeedback,
  useKnowledgeCategories,
  useKnowledgeArticles,
  useCreateKnowledgeArticle,
  useVoteKnowledgeArticle,
  useSuggestAiKnowledgeAnswer,
  useBulkUpdateTickets,
} from '../../hooks/useCustomerSuccess';
import { useTickets, useCreateTicket, useResolveTicket, useSendTicketMessage } from '../../hooks/useSupport';

export const CustomerSupportSuccessHubView: React.FC = () => {
  const [activeTab, setActiveTab] = useState<'tickets' | 'health_scores' | 'contracts' | 'feedback' | 'kb'>('tickets');

  // Queries
  const { data: ticketsData, isLoading: ticketsLoading } = useTickets();
  const { data: healthScores, isLoading: healthLoading } = useCustomerHealthScores();
  const { data: contracts, isLoading: contractsLoading } = useWarrantyContracts();
  const { data: feedbackList, isLoading: feedbackLoading } = useCustomerFeedback();
  const { data: kbCategories } = useKnowledgeCategories();
  const { data: kbArticles } = useKnowledgeArticles();

  // Mutations
  const createTicketMut = useCreateTicket();
  const resolveTicketMut = useResolveTicket();
  const sendMessageMut = useSendTicketMessage();
  const recordCheckInMut = useRecordCustomerCheckIn();
  const createContractMut = useCreateWarrantyContract();
  const recordMaintenanceMut = useRecordContractMaintenance();
  const submitFeedbackMut = useSubmitCustomerFeedback();
  const createArticleMut = useCreateKnowledgeArticle();
  const voteArticleMut = useVoteKnowledgeArticle();
  const suggestAiMut = useSuggestAiKnowledgeAnswer();
  const bulkUpdateMut = useBulkUpdateTickets();

  // Modals & form state
  const [showCreateTicketModal, setShowCreateTicketModal] = useState(false);
  const [ticketSubject, setTicketSubject] = useState('');
  const [ticketDesc, setTicketDesc] = useState('');
  const [ticketPriority, setTicketPriority] = useState('medium');
  const [ticketChannel, setTicketChannel] = useState('portal');

  // Check-In Modal
  const [showCheckInModal, setShowCheckInModal] = useState(false);
  const [checkInCustId, setCheckInCustId] = useState<number | null>(null);
  const [checkInNotes, setCheckInNotes] = useState('');
  const [checkInStatus, setCheckInStatus] = useState('completed');

  // New Contract Modal
  const [showContractModal, setShowContractModal] = useState(false);
  const [contractCustId, setContractCustId] = useState('1');
  const [contractType, setContractType] = useState('amc');
  const [contractProduct, setContractProduct] = useState('Enterprise Cloud Server Cluster');
  const [contractPrice, setContractPrice] = useState('75000');

  // AI KB Search State
  const [kbQuery, setKbQuery] = useState('');
  const [aiSuggestion, setAiSuggestion] = useState<any>(null);

  const tickets = (ticketsData as any)?.data || [];

  const handleCreateTicket = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await createTicketMut.mutateAsync({
        subject: ticketSubject,
        description: ticketDesc,
        priority: ticketPriority,
        source_channel: ticketChannel,
        status: 'new',
      } as any);
      setShowCreateTicketModal(false);
      setTicketSubject('');
      setTicketDesc('');
      alert('Support ticket created with SLA calculation.');
    } catch (err: any) {
      alert(err.message || 'Error creating ticket');
    }
  };

  const handleCheckInSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!checkInCustId) return;
    try {
      await recordCheckInMut.mutateAsync({
        customerId: checkInCustId,
        data: {
          notes: checkInNotes,
          onboarding_status: checkInStatus,
        },
      });
      setShowCheckInModal(false);
      alert('Customer check-in recorded and health score recalculated.');
    } catch (err: any) {
      alert(err.message || 'Error recording check-in');
    }
  };

  const handleCreateContract = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await createContractMut.mutateAsync({
        customer_id: parseInt(contractCustId) || 1,
        contract_type: contractType as any,
        product_name: contractProduct,
        renewal_price: parseFloat(contractPrice) || 50000,
        start_date: new Date().toISOString().split('T')[0],
        end_date: new Date(Date.now() + 365 * 86400000).toISOString().split('T')[0],
      });
      setShowContractModal(false);
      alert('Warranty / AMC Contract created.');
    } catch (err: any) {
      alert(err.message || 'Error creating contract');
    }
  };

  const handleAiKbSearch = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!kbQuery.trim()) return;
    try {
      const res = await suggestAiMut.mutateAsync(kbQuery);
      setAiSuggestion(res.data);
    } catch (err: any) {
      alert(err.message || 'AI search failed');
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
        <div>
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
              Customer Support, Help Desk & Customer Success
            </h1>
            <span className="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400">
              Prompt 42 Active
            </span>
          </div>
          <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Omnichannel ticket lifecycle, dynamic SLA countdowns, explainable Customer Health Scores, Warranty/AMC agreements, and Knowledge Base AI answers.
          </p>
        </div>
        <div className="flex items-center gap-3">
          <button
            onClick={() => setShowCreateTicketModal(true)}
            className="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium shadow-sm transition-colors"
          >
            <Plus className="w-4 h-4" />
            New Support Ticket
          </button>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex border-b border-gray-200 dark:border-gray-700 overflow-x-auto gap-2">
        {[
          { key: 'tickets', label: 'Tickets & SLA Tracking', icon: LifeBuoy },
          { key: 'health_scores', label: 'Customer Health & Retention', icon: HeartPulse },
          { key: 'contracts', label: 'Warranty & AMC Agreements', icon: ShieldCheck },
          { key: 'feedback', label: 'CSAT & NPS Feedback', icon: Star },
          { key: 'kb', label: 'Knowledge Base & AI Answers', icon: BookOpen },
        ].map(({ key, label, icon: Icon }) => (
          <button
            key={key}
            onClick={() => setActiveTab(key as any)}
            className={`flex items-center gap-2 py-3 px-4 border-b-2 text-sm font-medium whitespace-nowrap transition-colors ${
              activeTab === key
                ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
            }`}
          >
            <Icon className="w-4 h-4" />
            {label}
          </button>
        ))}
      </div>

      {/* TAB 1: TICKETS & SLA TRACKING */}
      {activeTab === 'tickets' && (
        <div className="space-y-4">
          <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
            <div className="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
              <h3 className="font-semibold text-gray-900 dark:text-white">Active Support Tickets</h3>
              <span className="text-xs text-gray-500">Showing {tickets.length} tickets</span>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-sm text-left">
                <thead className="bg-gray-50 dark:bg-gray-900/50 text-gray-600 dark:text-gray-400 uppercase text-xs">
                  <tr>
                    <th className="px-4 py-3">Ticket #</th>
                    <th className="px-4 py-3">Subject & Channel</th>
                    <th className="px-4 py-3">Customer</th>
                    <th className="px-4 py-3">Priority</th>
                    <th className="px-4 py-3">SLA Status</th>
                    <th className="px-4 py-3">Status</th>
                    <th className="px-4 py-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                  {tickets.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="text-center py-8 text-gray-500">
                        No support tickets found. Click "New Support Ticket" to create one.
                      </td>
                    </tr>
                  ) : (
                    tickets.map((t: any) => (
                      <tr key={t.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td className="px-4 py-3 font-semibold text-indigo-600 dark:text-indigo-400">
                          {t.ticket_number}
                        </td>
                        <td className="px-4 py-3">
                          <div className="font-medium text-gray-900 dark:text-white">{t.subject}</div>
                          <span className="text-xs text-gray-500 capitalize">{t.source_channel || 'portal'}</span>
                        </td>
                        <td className="px-4 py-3">
                          <span className="text-sm">{t.customer?.name || 'Walk-in Client'}</span>
                        </td>
                        <td className="px-4 py-3">
                          <span
                            className={`px-2 py-0.5 rounded text-xs font-semibold uppercase ${
                              t.priority === 'urgent'
                                ? 'bg-red-100 text-red-800'
                                : t.priority === 'high'
                                ? 'bg-orange-100 text-orange-800'
                                : 'bg-gray-100 text-gray-800'
                            }`}
                          >
                            {t.priority}
                          </span>
                        </td>
                        <td className="px-4 py-3">
                          <span
                            className={`px-2 py-0.5 rounded-full text-xs font-semibold ${
                              t.sla_status === 'breached'
                                ? 'bg-red-100 text-red-800'
                                : t.sla_status === 'warning'
                                ? 'bg-amber-100 text-amber-800'
                                : 'bg-emerald-100 text-emerald-800'
                            }`}
                          >
                            {t.sla_status || 'within_sla'}
                          </span>
                        </td>
                        <td className="px-4 py-3">
                          <span className="capitalize px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 dark:bg-gray-700">
                            {t.status}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-right space-x-2">
                          {t.status !== 'resolved' && t.status !== 'closed' && (
                            <button
                              onClick={() => {
                                const notes = prompt('Enter resolution notes:');
                                if (notes) resolveTicketMut.mutate({ id: t.id, resolution_notes: notes });
                              }}
                              className="px-2 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-medium"
                            >
                              Resolve
                            </button>
                          )}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 2: CUSTOMER HEALTH & RETENTION */}
      {activeTab === 'health_scores' && (
        <div className="space-y-6">
          <div className="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-2">
              Customer Success Health & Retention Dashboard
            </h3>
            <p className="text-sm text-gray-500 mb-6">
              Explainable Health Scores (0 - 100) combining support SLA performance, CSAT satisfaction, AR financial compliance, and onboarding progress.
            </p>

            <div className="space-y-4">
              {(!healthScores || healthScores.length === 0) ? (
                <div className="text-center py-8 text-gray-400">Loading customer health scores...</div>
              ) : (
                healthScores.map((h: any) => (
                  <div
                    key={h.id}
                    className="p-5 border border-gray-200 dark:border-gray-700 rounded-xl flex flex-col md:flex-row justify-between gap-4"
                  >
                    <div className="space-y-2">
                      <div className="flex items-center gap-3">
                        <span className="font-bold text-base text-gray-900 dark:text-white">
                          {h.customer?.name || `Customer #${h.customer_id}`}
                        </span>
                        <span
                          className={`px-2.5 py-0.5 rounded-full text-xs font-bold uppercase ${
                            h.health_status === 'healthy'
                              ? 'bg-emerald-100 text-emerald-800'
                              : h.health_status === 'neutral'
                              ? 'bg-blue-100 text-blue-800'
                              : 'bg-red-100 text-red-800'
                          }`}
                        >
                          {h.health_status} (Score: {h.health_score}/100)
                        </span>
                        <span className="text-xs px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 capitalize">
                          Churn Risk: <strong>{h.churn_risk_level}</strong>
                        </span>
                      </div>

                      <p className="text-xs text-gray-600 dark:text-gray-400 italic max-w-2xl">
                        "{h.factors?.summary_narrative || 'Explainable breakdown calculated from CRM metrics.'}"
                      </p>

                      <div className="flex flex-wrap gap-2 pt-2">
                        <span className="text-xs px-2.5 py-1 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700">
                          Support: <strong>{h.factors?.ticket_support?.score || 100}%</strong>
                        </span>
                        <span className="text-xs px-2.5 py-1 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700">
                          CSAT: <strong>{h.factors?.customer_satisfaction?.score || 85}%</strong>
                        </span>
                        <span className="text-xs px-2.5 py-1 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700">
                          Finance: <strong>{h.factors?.financial_health?.score || 100}%</strong>
                        </span>
                        <span className="text-xs px-2.5 py-1 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700">
                          Onboarding: <strong>{h.factors?.onboarding_engagement?.score || 100}%</strong>
                        </span>
                      </div>
                    </div>

                    <div className="flex items-center shrink-0">
                      <button
                        onClick={() => {
                          setCheckInCustId(h.customer_id);
                          setShowCheckInModal(true);
                        }}
                        className="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-medium"
                      >
                        Log Check-In
                      </button>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      )}

      {/* TAB 3: WARRANTY & AMC AGREEMENTS */}
      {activeTab === 'contracts' && (
        <div className="space-y-6">
          <div className="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div className="flex justify-between items-center mb-4">
              <div>
                <h3 className="text-lg font-bold text-gray-900 dark:text-white">
                  Warranty & Annual Maintenance Contracts (AMC)
                </h3>
                <p className="text-sm text-gray-500">
                  Track ongoing hardware warranties, AMC service coverage, preventive maintenance schedules, and renewals.
                </p>
              </div>
              <button
                onClick={() => setShowContractModal(true)}
                className="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-medium inline-flex items-center gap-1.5"
              >
                <Plus className="w-3.5 h-3.5" />
                Add Agreement
              </button>
            </div>

            <div className="divide-y divide-gray-200 dark:divide-gray-700">
              {(!contracts || contracts.length === 0) ? (
                <div className="text-center py-8 text-gray-500">
                  No warranty or AMC agreements registered yet. Click "Add Agreement" to register one.
                </div>
              ) : (
                contracts.map((c: any) => (
                  <div key={c.id} className="py-4 flex flex-col md:flex-row justify-between gap-4">
                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <span className="font-bold text-gray-900 dark:text-white font-mono">{c.contract_number}</span>
                        <span className="px-2 py-0.5 text-xs font-semibold rounded bg-blue-100 text-blue-800 uppercase">
                          {c.contract_type}
                        </span>
                        <span className="text-xs px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 capitalize">
                          {c.status}
                        </span>
                      </div>
                      <h4 className="font-semibold text-sm text-gray-800 dark:text-gray-200">{c.product_name}</h4>
                      <p className="text-xs text-gray-500">{c.coverage_details}</p>
                      <div className="flex gap-4 text-xs text-gray-400">
                        <span>Customer: <strong>{c.customer?.name || `Customer #${c.customer_id}`}</strong></span>
                        <span>Next Maintenance: <strong>{c.next_service_date || 'Scheduled'}</strong></span>
                        <span>Renewal: <strong>{c.renewal_date}</strong> (₹{parseFloat(c.renewal_price).toLocaleString()})</span>
                      </div>
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                      <button
                        onClick={async () => {
                          const notes = prompt('Enter maintenance observations:');
                          if (notes) await recordMaintenanceMut.mutateAsync({ id: c.id, data: { notes } });
                        }}
                        className="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 text-xs font-medium rounded inline-flex items-center gap-1"
                      >
                        <Wrench className="w-3.5 h-3.5" />
                        Log Maintenance
                      </button>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      )}

      {/* TAB 4: CSAT & NPS FEEDBACK */}
      {activeTab === 'feedback' && (
        <div className="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm space-y-4">
          <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-2">
            Customer Experience, CSAT & NPS Feedback
          </h3>
          <p className="text-sm text-gray-500 mb-4">
            Direct survey feedback collected post-ticket resolution across portals, email, and WhatsApp.
          </p>

          <div className="divide-y divide-gray-200 dark:divide-gray-700">
            {(!feedbackList || feedbackList.length === 0) ? (
              <div className="text-center py-8 text-gray-500">No customer feedback responses recorded yet.</div>
            ) : (
              feedbackList.map((f: any) => (
                <div key={f.id} className="py-4 space-y-1">
                  <div className="flex items-center gap-2">
                    <span className="font-semibold text-sm text-gray-900 dark:text-white">
                      {f.customer?.name || `Customer #${f.customer_id}`}
                    </span>
                    <span className="flex items-center text-amber-500 text-xs font-bold">
                      <Star className="w-3.5 h-3.5 fill-current mr-0.5" /> {f.csat_score}/5
                    </span>
                    {f.nps_score !== null && (
                      <span className="text-xs px-2 py-0.5 bg-purple-100 text-purple-800 rounded font-semibold">
                        NPS: {f.nps_score}/10
                      </span>
                    )}
                    <span className="text-xs text-gray-400 capitalize">via {f.channel}</span>
                  </div>
                  {f.feedback_text && (
                    <p className="text-xs text-gray-600 dark:text-gray-300 italic">"{f.feedback_text}"</p>
                  )}
                  <span className="text-xs text-gray-400 block">{f.submitted_at}</span>
                </div>
              ))
            )}
          </div>
        </div>
      )}

      {/* TAB 5: KNOWLEDGE BASE & AI ANSWERS */}
      {activeTab === 'kb' && (
        <div className="space-y-6">
          <div className="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-2">
              Knowledge Base & AI Grounded Search
            </h3>
            <p className="text-sm text-gray-500 mb-6">
              Search approved articles or generate AI customer responses grounded in verified documentation.
            </p>

            <form onSubmit={handleAiKbSearch} className="flex gap-3 mb-6">
              <input
                type="text"
                placeholder="Ask a question or enter keywords (e.g. GST reconciliation, onboarding)..."
                value={kbQuery}
                onChange={(e) => setKbQuery(e.target.value)}
                className="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-transparent"
              />
              <button
                type="submit"
                disabled={suggestAiMut.isPending}
                className="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium flex items-center gap-2"
              >
                <Sparkles className="w-4 h-4" />
                AI Suggest Answer
              </button>
            </form>

            {aiSuggestion && (
              <div className="p-4 bg-indigo-50 dark:bg-indigo-950/20 border border-indigo-200 dark:border-indigo-800 rounded-xl mb-6">
                <div className="flex items-center gap-2 font-bold text-sm text-indigo-900 dark:text-indigo-300 mb-2">
                  <Sparkles className="w-4 h-4" /> AI Grounded Answer (Confidence: {Math.round(aiSuggestion.confidence_score * 100)}%)
                </div>
                <p className="text-sm text-gray-800 dark:text-gray-200">{aiSuggestion.suggested_answer}</p>
              </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {kbArticles?.map((art: any) => (
                <div
                  key={art.id}
                  className="p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:shadow-sm transition-shadow flex flex-col justify-between"
                >
                  <div>
                    <h4 className="font-semibold text-gray-900 dark:text-white text-sm mb-1">{art.title}</h4>
                    <p className="text-xs text-gray-500 line-clamp-2">{art.excerpt || art.content}</p>
                  </div>
                  <div className="mt-4 pt-2 border-t border-gray-100 dark:border-gray-700 flex justify-between items-center text-xs text-gray-400">
                    <span>Views: {art.view_count || 0}</span>
                    <button
                      onClick={() => voteArticleMut.mutate({ id: art.id, helpful: true })}
                      className="hover:text-indigo-600 flex items-center gap-1"
                    >
                      <ThumbsUp className="w-3.5 h-3.5" /> Helpful ({art.helpful_count || 0})
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* CREATE TICKET MODAL */}
      {showCreateTicketModal && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-gray-800 rounded-xl max-w-lg w-full p-6 space-y-4 shadow-xl">
            <h3 className="text-lg font-bold text-gray-900 dark:text-white">Create Support Ticket</h3>
            <form onSubmit={handleCreateTicket} className="space-y-3">
              <div>
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Subject</label>
                <input
                  type="text"
                  required
                  value={ticketSubject}
                  onChange={(e) => setTicketSubject(e.target.value)}
                  className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent"
                  placeholder="e.g. Payment Gateway Failure"
                />
              </div>

              <div>
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Description</label>
                <textarea
                  rows={3}
                  required
                  value={ticketDesc}
                  onChange={(e) => setTicketDesc(e.target.value)}
                  className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent"
                  placeholder="Describe the incident or request..."
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Priority</label>
                  <select
                    value={ticketPriority}
                    onChange={(e) => setTicketPriority(e.target.value)}
                    className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent"
                  >
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                  </select>
                </div>

                <div>
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Source Channel</label>
                  <select
                    value={ticketChannel}
                    onChange={(e) => setTicketChannel(e.target.value)}
                    className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent"
                  >
                    <option value="portal">Customer Portal</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="email">Email</option>
                    <option value="web_chat">Live Chat</option>
                  </select>
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-3">
                <button
                  type="button"
                  onClick={() => setShowCreateTicketModal(false)}
                  className="px-4 py-2 border rounded-lg text-sm"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createTicketMut.isPending}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium"
                >
                  Create Ticket
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* CHECK-IN MODAL */}
      {showCheckInModal && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-gray-800 rounded-xl max-w-md w-full p-6 space-y-4 shadow-xl">
            <h3 className="text-lg font-bold text-gray-900 dark:text-white">Log Customer Success Check-In</h3>
            <form onSubmit={handleCheckInSubmit} className="space-y-3">
              <div>
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Onboarding Status</label>
                <select
                  value={checkInStatus}
                  onChange={(e) => setCheckInStatus(e.target.value)}
                  className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent"
                >
                  <option value="completed">Completed (100% factor)</option>
                  <option value="in_progress">In Progress (75% factor)</option>
                  <option value="stalled">Stalled (35% factor)</option>
                </select>
              </div>

              <div>
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Check-in Notes & Observations</label>
                <textarea
                  rows={3}
                  required
                  value={checkInNotes}
                  onChange={(e) => setCheckInNotes(e.target.value)}
                  className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent"
                  placeholder="Review findings, client sentiments, next steps..."
                />
              </div>

              <div className="flex justify-end gap-2 pt-3">
                <button
                  type="button"
                  onClick={() => setShowCheckInModal(false)}
                  className="px-4 py-2 border rounded-lg text-sm"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={recordCheckInMut.isPending}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium"
                >
                  Save Check-In
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* CREATE CONTRACT MODAL */}
      {showContractModal && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-gray-800 rounded-xl max-w-md w-full p-6 space-y-4 shadow-xl">
            <h3 className="text-lg font-bold text-gray-900 dark:text-white">New Warranty / AMC Agreement</h3>
            <form onSubmit={handleCreateContract} className="space-y-3">
              <div>
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Contract Type</label>
                <select
                  value={contractType}
                  onChange={(e) => setContractType(e.target.value)}
                  className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent"
                >
                  <option value="amc">Annual Maintenance Contract (AMC)</option>
                  <option value="warranty">Hardware / Service Warranty</option>
                  <option value="service_agreement">Custom Service Agreement</option>
                </select>
              </div>

              <div>
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Product / System Covered</label>
                <input
                  type="text"
                  required
                  value={contractProduct}
                  onChange={(e) => setContractProduct(e.target.value)}
                  className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent"
                />
              </div>

              <div>
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Annual Renewal Price (₹)</label>
                <input
                  type="number"
                  required
                  value={contractPrice}
                  onChange={(e) => setContractPrice(e.target.value)}
                  className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent"
                  min="0"
                />
              </div>

              <div className="flex justify-end gap-2 pt-3">
                <button
                  type="button"
                  onClick={() => setShowContractModal(false)}
                  className="px-4 py-2 border rounded-lg text-sm"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createContractMut.isPending}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium"
                >
                  Save Contract
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
