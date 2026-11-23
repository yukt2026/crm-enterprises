import React, { useState } from 'react';
import {
  PhoneCall,
  PhoneForwarded,
  PhoneIncoming,
  PhoneOutgoing,
  PhoneMissed,
  Sparkles,
  Bot,
  Play,
  Pause,
  Plus,
  Trash2,
  Edit,
  ShieldCheck,
  AlertCircle,
  CheckCircle2,
  Clock,
  Send,
  Zap,
  Globe,
  Languages,
  Check,
  X,
  RefreshCw,
  Search,
  Eye,
  Sliders,
  DollarSign,
  Activity,
  Mic,
  MicOff,
  Radio,
  FileText,
  Volume2,
  Server,
  UserCheck,
  Building2,
  Layers,
  Star,
  Download,
  Headphones,
  UserPlus,
  ArrowRight,
  TrendingUp,
  BarChart3,
  PhoneOff
} from 'lucide-react';
import {
  useVoiceAgents,
  useCreateVoiceAgent,
  useUpdateVoiceAgent,
  useDeleteVoiceAgent,
  useVoiceCalls,
  useInitiateVoiceCall,
  useSimulateVoiceCallTurn,
  useVoiceCampaigns,
  useCreateVoiceCampaign,
  useLaunchVoiceCampaign,
  usePauseVoiceCampaign,
  useDeleteVoiceCampaign,
  useTelephonyProviders,
  useUpdateTelephonyProvider,
  useTelephonyPhoneNumbers,
  useRegisterTelephonyPhoneNumber,
  useClickToCall,
  useCallerLookup,
  useSimulateInboundCall,
  useUpdateCallDisposition,
  useSubmitCallQualityReview,
  useTransferVoiceCall,
  useVoiceCallReports,
  useIvrFlows,
  useCreateIvrFlow,
  useDeleteIvrFlow,
  useCallQueues,
  useCreateCallQueue,
  useDeleteCallQueue,
  useTestTelephonyProvider,
  useTelephonyProviderLogs
} from '../../hooks/useVoiceTelephony';
import { useCRMStore } from '../../store/crmStore';
import {
  VoiceAiAgentItem,
  VoiceCallItem,
  VoiceCallingCampaignItem,
  TelephonyProviderItem,
  TelephonyPhoneNumberItem,
  CallIvrFlowItem,
  CallQueueItem,
  CallerProfileLookupData
} from '../../api/types';

interface VoiceTelephonyViewProps {
  theme?: 'light' | 'dark';
}

export default function VoiceTelephonyView({ theme = 'light' }: VoiceTelephonyViewProps) {
  const [activeTab, setActiveTab] = useState<'dialer' | 'calls' | 'incoming' | 'agents' | 'ivr' | 'campaigns' | 'providers' | 'analytics'>('dialer');

  // Queries
  const { data: agents = [], isLoading: loadingAgents } = useVoiceAgents();
  const { data: callsData, isLoading: loadingCalls } = useVoiceCalls({ per_page: 50 });
  const { data: campaigns = [], isLoading: loadingCampaigns } = useVoiceCampaigns();
  const { data: providers = [], isLoading: loadingProviders } = useTelephonyProviders();
  const { data: phoneNumbers = [], isLoading: loadingNumbers } = useTelephonyPhoneNumbers();
  const { data: ivrFlows = [], isLoading: loadingIvr } = useIvrFlows();
  const { data: queues = [], isLoading: loadingQueues } = useCallQueues();
  const { data: reportsData } = useVoiceCallReports();
  const { data: providerLogs = [] } = useTelephonyProviderLogs();
  const leads = useCRMStore((s) => (s.data.leads || []) as any[]);
  const contacts = useCRMStore((s) => (s.data.contacts || []) as any[]);
  const deals = useCRMStore((s) => (s.data.deals || []) as any[]);
  const addToast = useCRMStore((s) => s.addToast);

  const calls = callsData?.calls || [];
  const stats = callsData?.stats || {
    total_calls: 0,
    total_minutes: 0,
    total_cost: 0,
    qualified_count: 0
  };

  // Mutations
  const createAgentMutation = useCreateVoiceAgent();
  const updateAgentMutation = useUpdateVoiceAgent();
  const deleteAgentMutation = useDeleteVoiceAgent();

  const clickToCallMutation = useClickToCall();
  const initiateCallMutation = useInitiateVoiceCall();
  const simulateTurnMutation = useSimulateVoiceCallTurn();
  const callerLookupMutation = useCallerLookup();
  const inboundCallMutation = useSimulateInboundCall();
  const updateDispositionMutation = useUpdateCallDisposition();
  const submitReviewMutation = useSubmitCallQualityReview();
  const transferCallMutation = useTransferVoiceCall();

  const createIvrMutation = useCreateIvrFlow();
  const deleteIvrMutation = useDeleteIvrFlow();
  const createQueueMutation = useCreateCallQueue();
  const deleteQueueMutation = useDeleteCallQueue();
  const testProviderMutation = useTestTelephonyProvider();
  const updateProviderMutation = useUpdateTelephonyProvider();
  const registerNumberMutation = useRegisterTelephonyPhoneNumber();

  // Dialer State
  const [dialNumber, setDialNumber] = useState('');
  const [selectedAgentId, setSelectedAgentId] = useState<number | ''>('');
  const [selectedLeadId, setSelectedLeadId] = useState<number | ''>('');
  const [selectedDealId, setSelectedDealId] = useState<number | ''>('');
  const [activeCall, setActiveCall] = useState<VoiceCallItem | null>(null);
  const [callDuration, setCallDuration] = useState(0);
  const [isMuted, setIsMuted] = useState(false);
  const [isOnHold, setIsOnHold] = useState(false);
  const [userSpeechInput, setUserSpeechInput] = useState('');
  const [callerLookupData, setCallerLookupData] = useState<CallerProfileLookupData | null>(null);

  // Modals & Panels
  const [showCallConfirmModal, setShowCallConfirmModal] = useState(false);
  const [showDispositionModal, setShowDispositionModal] = useState(false);
  const [showReviewModal, setShowReviewModal] = useState(false);
  const [showTransferModal, setShowTransferModal] = useState(false);
  const [selectedCallForReview, setSelectedCallForReview] = useState<VoiceCallItem | null>(null);
  const [reviewScore, setReviewScore] = useState(5);
  const [reviewFeedback, setReviewFeedback] = useState('');
  const [transferTarget, setTransferTarget] = useState('');
  const [playbackAudioUrl, setPlaybackAudioUrl] = useState<string | null>(null);

  // Inbound Simulation
  const [inboundFrom, setInboundFrom] = useState('+919876543210');
  const [inboundTo, setInboundTo] = useState('+918047192000');

  // Agent Form
  const [showAgentModal, setShowAgentModal] = useState(false);
  const [editingAgent, setEditingAgent] = useState<VoiceAiAgentItem | null>(null);
  const [agentForm, setAgentForm] = useState({
    name: '',
    description: '',
    business_purpose: 'lead_qualification',
    status: 'active' as 'active' | 'paused' | 'draft',
    language: 'hinglish',
    voice_engine: 'elevenlabs',
    voice_id: 'alloy',
    tone: 'consultative' as 'consultative' | 'professional' | 'friendly' | 'persuasive',
    system_instructions: 'You are an intelligent voice AI executive for an enterprise CRM.',
    call_opening: 'Namaste! Main Acme Enterprise CRM se bol raha hoon. Kya aapse do minute baat ho sakti hai?',
    knowledge_base: 'Enterprise CRM pricing: ₹1,500/user/month. All modules included: WhatsApp CRM, Invoicing, Inventory, HR.',
    confidence_threshold: 0.70,
    max_call_duration_seconds: 300,
    human_transfer_number: '+919876500000',
    monthly_call_minutes_budget: 1000,
  });

  // IVR & Queue Form
  const [showIvrModal, setShowIvrModal] = useState(false);
  const [ivrForm, setIvrForm] = useState({
    name: 'Main Business IVR',
    welcome_audio_text: 'Welcome to Enterprise CRM. Press 1 for Sales, Press 2 for Technical Support, Press 9 for Voicemail.',
    language: 'hi',
    unanswered_action: 'voicemail' as any,
  });

  const [showQueueModal, setShowQueueModal] = useState(false);
  const [queueForm, setQueueForm] = useState({
    name: 'Sales Inbound Queue',
    department: 'Sales',
    strategy: 'round_robin' as any,
    max_wait_time_seconds: 60,
  });

  // Handle number click on keypad
  const handleKeypadPress = (val: string) => {
    setDialNumber((prev) => prev + val);
  };

  // Perform caller lookup
  const handleLookup = async (phone: string) => {
    if (!phone || phone.length < 5) return;
    try {
      const res = await callerLookupMutation.mutateAsync(phone);
      if (res.data) {
        setCallerLookupData(res.data);
      }
    } catch (e) {
      // Ignore
    }
  };

  // Initiate Call Confirmation
  const openCallConfirmation = () => {
    if (!dialNumber) {
      addToast('Please enter a valid phone number', 'error');
      return;
    }
    handleLookup(dialNumber);
    setShowCallConfirmModal(true);
  };

  // Execute Outbound Call
  const handleExecuteCall = async () => {
    try {
      setShowCallConfirmModal(false);
      let callResult: VoiceCallItem;

      if (selectedAgentId) {
        const res = await initiateCallMutation.mutateAsync({
          to_number: dialNumber,
          voice_agent_id: Number(selectedAgentId),
          lead_id: selectedLeadId ? Number(selectedLeadId) : undefined,
          deal_id: selectedDealId ? Number(selectedDealId) : undefined,
        });
        callResult = res.data;
      } else {
        const res = await clickToCallMutation.mutateAsync({
          to_number: dialNumber,
          lead_id: selectedLeadId ? Number(selectedLeadId) : undefined,
          deal_id: selectedDealId ? Number(selectedDealId) : undefined,
          provider: 'exotel',
        });
        callResult = res.data;
      }

      setActiveCall(callResult);
      setCallDuration(0);
      addToast(`Call connected to ${dialNumber}`, 'success');
    } catch (err: any) {
      addToast(err.message || 'Call failed', 'error');
    }
  };

  // Speech turn simulation in active call
  const handleSendSpeechTurn = async () => {
    if (!activeCall || !userSpeechInput.trim()) return;
    try {
      const res = await simulateTurnMutation.mutateAsync({
        callId: activeCall.id,
        user_speech: userSpeechInput,
      });

      if (res.data?.call) {
        setActiveCall(res.data.call);
      }
      setUserSpeechInput('');
    } catch (err: any) {
      addToast(err.message || 'Speech simulation error', 'error');
    }
  };

  // End Call & Show Disposition
  const handleEndCall = () => {
    if (!activeCall) return;
    setShowDispositionModal(true);
  };

  const handleSaveDisposition = async (disposition: string, result: string) => {
    if (!activeCall) return;
    try {
      await updateDispositionMutation.mutateAsync({
        callId: activeCall.id,
        data: {
          status: 'completed',
          disposition,
          qualification_result: result,
          duration_seconds: Math.max(15, callDuration),
          follow_up_required: disposition === 'interested' || disposition === 'callback_requested',
        },
      });
      setActiveCall(null);
      setShowDispositionModal(false);
      setCallDuration(0);
    } catch (e: any) {
      addToast(e.message || 'Failed to save disposition', 'error');
    }
  };

  // Submit Manager Quality Review
  const handleSaveReview = async () => {
    if (!selectedCallForReview) return;
    try {
      await submitReviewMutation.mutateAsync({
        callId: selectedCallForReview.id,
        data: {
          score: reviewScore,
          feedback: reviewFeedback,
          criteria_scores: {
            greeting: reviewScore,
            product_knowledge: reviewScore,
            compliance: 5,
            politeness: reviewScore,
          },
        },
      });
      setShowReviewModal(false);
      setSelectedCallForReview(null);
      setReviewFeedback('');
    } catch (e: any) {
      addToast(e.message || 'Review submission failed', 'error');
    }
  };

  // Execute Call Transfer
  const handleExecuteTransfer = async () => {
    if (!activeCall || !transferTarget) return;
    try {
      await transferCallMutation.mutateAsync({
        callId: activeCall.id,
        data: {
          transfer_target: transferTarget,
        },
      });
      setShowTransferModal(false);
      setActiveCall(null);
    } catch (e: any) {
      addToast(e.message || 'Transfer failed', 'error');
    }
  };

  return (
    <div className="space-y-6">
      {/* Top Header & Metrics Banner */}
      <div className="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 bg-gradient-to-r from-blue-700 via-indigo-700 to-purple-800 text-white p-6 rounded-2xl shadow-lg">
        <div>
          <div className="flex items-center gap-2">
            <span className="bg-white/20 text-xs px-2.5 py-1 rounded-full font-semibold uppercase tracking-wider backdrop-blur-sm">
              Cloud Telephony & AI Calling
            </span>
            <span className="bg-emerald-500/80 text-white text-xs px-2.5 py-1 rounded-full font-medium flex items-center gap-1">
              <CheckCircle2 size={12} /> TRAI / DoT Compliant
            </span>
          </div>
          <h1 className="text-2xl font-bold mt-2">Enterprise Call Centre & AI Voice Hub</h1>
          <p className="text-blue-100 text-sm mt-1">
            Browser WebRTC softphone, multi-lingual Indian AI voice agents, IVR trees & call quality intelligence.
          </p>
        </div>

        <div className="flex items-center gap-3">
          <button
            onClick={() => {
              setEditingAgent(null);
              setShowAgentModal(true);
            }}
            className="flex items-center gap-2 bg-white text-blue-700 px-4 py-2.5 rounded-xl font-semibold shadow hover:bg-blue-50 transition"
          >
            <Plus size={18} /> New AI Agent
          </button>
        </div>
      </div>

      {/* Quick KPI Stat Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div className="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center gap-3">
          <div className="p-3 bg-blue-50 dark:bg-blue-900/40 text-blue-600 rounded-lg">
            <PhoneCall size={22} />
          </div>
          <div>
            <div className="text-xs text-gray-500 font-medium">Total Calls Logged</div>
            <div className="text-xl font-bold text-gray-800 dark:text-gray-100">{stats.total_calls}</div>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center gap-3">
          <div className="p-3 bg-purple-50 dark:bg-purple-900/40 text-purple-600 rounded-lg">
            <Clock size={22} />
          </div>
          <div>
            <div className="text-xs text-gray-500 font-medium">Total Talk Minutes</div>
            <div className="text-xl font-bold text-gray-800 dark:text-gray-100">{stats.total_minutes} min</div>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center gap-3">
          <div className="p-3 bg-emerald-50 dark:bg-emerald-900/40 text-emerald-600 rounded-lg">
            <Sparkles size={22} />
          </div>
          <div>
            <div className="text-xs text-gray-500 font-medium">Qualified Leads</div>
            <div className="text-xl font-bold text-emerald-600">{stats.qualified_count}</div>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center gap-3">
          <div className="p-3 bg-amber-50 dark:bg-amber-900/40 text-amber-600 rounded-lg">
            <TrendingUp size={22} />
          </div>
          <div>
            <div className="text-xs text-gray-500 font-medium">Active Providers</div>
            <div className="text-xl font-bold text-amber-600">{providers.filter(p => p.is_active).length} Connected</div>
          </div>
        </div>
      </div>

      {/* Navigation Tabs */}
      <div className="border-b border-gray-200 dark:border-gray-700 flex flex-wrap gap-2">
        <button
          onClick={() => setActiveTab('dialer')}
          className={`flex items-center gap-2 py-3 px-4 border-b-2 font-medium text-sm transition ${
            activeTab === 'dialer'
              ? 'border-blue-600 text-blue-600 dark:text-blue-400'
              : 'border-transparent text-gray-500 hover:text-gray-700'
          }`}
        >
          <PhoneCall size={16} /> Web Softphone Dialer
        </button>

        <button
          onClick={() => setActiveTab('calls')}
          className={`flex items-center gap-2 py-3 px-4 border-b-2 font-medium text-sm transition ${
            activeTab === 'calls'
              ? 'border-blue-600 text-blue-600 dark:text-blue-400'
              : 'border-transparent text-gray-500 hover:text-gray-700'
          }`}
        >
          <FileText size={16} /> Call Logs & Recordings
        </button>

        <button
          onClick={() => setActiveTab('incoming')}
          className={`flex items-center gap-2 py-3 px-4 border-b-2 font-medium text-sm transition ${
            activeTab === 'incoming'
              ? 'border-blue-600 text-blue-600 dark:text-blue-400'
              : 'border-transparent text-gray-500 hover:text-gray-700'
          }`}
        >
          <PhoneIncoming size={16} /> Inbound & Caller ID
        </button>

        <button
          onClick={() => setActiveTab('agents')}
          className={`flex items-center gap-2 py-3 px-4 border-b-2 font-medium text-sm transition ${
            activeTab === 'agents'
              ? 'border-blue-600 text-blue-600 dark:text-blue-400'
              : 'border-transparent text-gray-500 hover:text-gray-700'
          }`}
        >
          <Bot size={16} /> AI Voice Agents ({agents.length})
        </button>

        <button
          onClick={() => setActiveTab('ivr')}
          className={`flex items-center gap-2 py-3 px-4 border-b-2 font-medium text-sm transition ${
            activeTab === 'ivr'
              ? 'border-blue-600 text-blue-600 dark:text-blue-400'
              : 'border-transparent text-gray-500 hover:text-gray-700'
          }`}
        >
          <Layers size={16} /> IVR Flows & Queues
        </button>

        <button
          onClick={() => setActiveTab('providers')}
          className={`flex items-center gap-2 py-3 px-4 border-b-2 font-medium text-sm transition ${
            activeTab === 'providers'
              ? 'border-blue-600 text-blue-600 dark:text-blue-400'
              : 'border-transparent text-gray-500 hover:text-gray-700'
          }`}
        >
          <Server size={16} /> Telephony Providers
        </button>

        <button
          onClick={() => setActiveTab('analytics')}
          className={`flex items-center gap-2 py-3 px-4 border-b-2 font-medium text-sm transition ${
            activeTab === 'analytics'
              ? 'border-blue-600 text-blue-600 dark:text-blue-400'
              : 'border-transparent text-gray-500 hover:text-gray-700'
          }`}
        >
          <BarChart3 size={16} /> Analytics & BI
        </button>
      </div>

      {/* ============================================================== */}
      {/* TAB 1: WEBRTC SOFTPHONE DIALER & ACTIVE CALL CONSOLE */}
      {/* ============================================================== */}
      {activeTab === 'dialer' && (
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
          {/* Dialer Keypad (Left 5 Cols) */}
          <div className="lg:col-span-5 bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-5">
            <div className="flex items-center justify-between">
              <h3 className="font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                <Headphones className="text-blue-600" size={18} /> Browser Softphone
              </h3>
              <span className="text-xs px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 font-medium">
                Line 1: Ready
              </span>
            </div>

            {/* Dial Screen & Inputs */}
            <div className="space-y-3">
              <div className="relative">
                <input
                  type="text"
                  value={dialNumber}
                  onChange={(e) => setDialNumber(e.target.value)}
                  placeholder="Enter phone number (+91...)"
                  className="w-full text-center text-xl font-bold py-3 px-4 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl tracking-wider text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                {dialNumber && (
                  <button
                    onClick={() => setDialNumber('')}
                    className="absolute right-3 top-3.5 text-gray-400 hover:text-gray-600"
                  >
                    <X size={18} />
                  </button>
                )}
              </div>

              {/* CRM Linking dropdowns */}
              <div className="grid grid-cols-2 gap-2 text-xs">
                <div>
                  <label className="text-gray-500 block mb-1">Link CRM Lead</label>
                  <select
                    value={selectedLeadId}
                    onChange={(e) => {
                      const id = e.target.value;
                      setSelectedLeadId(id ? Number(id) : '');
                      const match = leads.find((l) => l.id === Number(id));
                      if (match?.phone) setDialNumber(match.phone);
                    }}
                    className="w-full p-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg"
                  >
                    <option value="">-- No Lead --</option>
                    {leads.map((l) => (
                      <option key={l.id} value={l.id}>{l.name} ({l.phone})</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="text-gray-500 block mb-1">Calling Mode</label>
                  <select
                    value={selectedAgentId}
                    onChange={(e) => setSelectedAgentId(e.target.value ? Number(e.target.value) : '')}
                    className="w-full p-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg font-medium text-blue-600 dark:text-blue-400"
                  >
                    <option value="">Direct Human Rep Call</option>
                    {agents.filter(a => a.status === 'active').map((a) => (
                      <option key={a.id} value={a.id}>AI Agent: {a.name} ({a.language})</option>
                    ))}
                  </select>
                </div>
              </div>
            </div>

            {/* Keypad Grid */}
            <div className="grid grid-cols-3 gap-2.5">
              {['1', '2', '3', '4', '5', '6', '7', '8', '9', '*', '0', '#'].map((digit) => (
                <button
                  key={digit}
                  onClick={() => handleKeypadPress(digit)}
                  className="py-3.5 bg-gray-50 hover:bg-blue-50 dark:bg-gray-700/60 dark:hover:bg-gray-700 text-lg font-semibold text-gray-800 dark:text-gray-100 rounded-xl border border-gray-200 dark:border-gray-600 transition shadow-sm active:scale-95"
                >
                  {digit}
                </button>
              ))}
            </div>

            {/* Dial Action Buttons */}
            <div className="pt-2">
              {!activeCall ? (
                <button
                  onClick={openCallConfirmation}
                  disabled={!dialNumber}
                  className="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-lg flex items-center justify-center gap-2 transition disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <PhoneCall size={20} />
                  Initiate Outbound Call
                </button>
              ) : (
                <button
                  onClick={handleEndCall}
                  className="w-full py-3.5 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl shadow-lg flex items-center justify-center gap-2 transition"
                >
                  <PhoneOff size={20} />
                  Disconnect Call
                </button>
              )}
            </div>
          </div>

          {/* Active Call / Simulator Console (Right 7 Cols) */}
          <div className="lg:col-span-7 bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm flex flex-col justify-between">
            {activeCall ? (
              <div className="space-y-4">
                {/* Active Call Header */}
                <div className="flex items-center justify-between p-4 bg-blue-50 dark:bg-blue-900/30 rounded-xl border border-blue-100 dark:border-blue-800">
                  <div className="flex items-center gap-3">
                    <span className="relative flex h-3 w-3">
                      <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                      <span className="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                    </span>
                    <div>
                      <div className="font-bold text-gray-900 dark:text-gray-100">
                        Connected: {activeCall.to_number}
                      </div>
                      <div className="text-xs text-blue-600 dark:text-blue-300">
                        {activeCall.call_type === 'ai_voice' ? 'AI Voice Conversation' : 'Direct Agent Call'} • Provider: {activeCall.provider}
                      </div>
                    </div>
                  </div>

                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => setIsMuted(!isMuted)}
                      className={`p-2 rounded-lg border text-xs font-semibold ${
                        isMuted ? 'bg-red-100 text-red-600 border-red-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'
                      }`}
                    >
                      {isMuted ? <MicOff size={16} /> : <Mic size={16} />}
                    </button>
                    <button
                      onClick={() => setShowTransferModal(true)}
                      className="p-2 bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 rounded-lg border border-indigo-200 flex items-center gap-1 text-xs font-medium"
                    >
                      <PhoneForwarded size={14} /> Transfer
                    </button>
                  </div>
                </div>

                {/* Lawful Recording Notice Banner */}
                <div className="bg-amber-50 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 p-2.5 rounded-lg text-xs flex items-center gap-2 border border-amber-200 dark:border-amber-700">
                  <ShieldCheck size={16} className="text-amber-600 shrink-0" />
                  <span>
                    Compliance Notice: Standard TRAI recording announcement was delivered to customer upon call connection.
                  </span>
                </div>

                {/* Live Dialogue Transcript Stream */}
                <div className="h-64 overflow-y-auto p-4 bg-gray-50 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 font-mono text-xs space-y-2 whitespace-pre-wrap">
                  {activeCall.transcript || '[Call Connected. Awaiting customer speech turn...]'}
                </div>

                {/* Speech Turn Simulation Input */}
                <div className="flex gap-2">
                  <input
                    type="text"
                    value={userSpeechInput}
                    onChange={(e) => setUserSpeechInput(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && handleSendSpeechTurn()}
                    placeholder="Type customer speech (e.g. 'Haanji budget 5 lakh rupees hai')..."
                    className="flex-1 p-2.5 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl"
                  />
                  <button
                    onClick={handleSendSpeechTurn}
                    className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-semibold flex items-center gap-1.5"
                  >
                    <Send size={14} /> Speak
                  </button>
                </div>
              </div>
            ) : (
              <div className="h-full flex flex-col items-center justify-center p-8 text-center text-gray-400">
                <Headphones size={48} className="text-gray-300 dark:text-gray-600 mb-3" />
                <h4 className="font-semibold text-gray-700 dark:text-gray-200">Softphone is Standby</h4>
                <p className="text-sm max-w-sm mt-1">
                  Enter a customer phone number on the left or select a lead to initiate click-to-call.
                </p>
              </div>
            )}
          </div>
        </div>
      )}

      {/* ============================================================== */}
      {/* TAB 2: CALL LOGS & RECORDINGS TABLE */}
      {/* ============================================================== */}
      {activeTab === 'calls' && (
        <div className="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
          <div className="p-4 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-center justify-between gap-3">
            <h3 className="font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
              <FileText className="text-blue-600" size={18} /> Enterprise Call Records & Ledger
            </h3>
            <span className="text-xs text-gray-500 font-medium">
              Showing {calls.length} recent calls
            </span>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-gray-50 dark:bg-gray-900/60 text-gray-600 dark:text-gray-400 text-xs uppercase tracking-wider">
                <tr>
                  <th className="p-3.5">Direction / Type</th>
                  <th className="p-3.5">Customer & Number</th>
                  <th className="p-3.5">Linked CRM Record</th>
                  <th className="p-3.5">Duration</th>
                  <th className="p-3.5">Disposition</th>
                  <th className="p-3.5">Quality Rating</th>
                  <th className="p-3.5">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {calls.map((call) => (
                  <tr key={call.id} className="hover:bg-gray-50 dark:hover:bg-gray-750 transition">
                    <td className="p-3.5">
                      <div className="flex items-center gap-2">
                        {call.direction === 'inbound' ? (
                          <span className="p-1.5 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 rounded-lg">
                            <PhoneIncoming size={14} />
                          </span>
                        ) : (
                          <span className="p-1.5 bg-blue-100 text-blue-700 dark:bg-blue-900/40 rounded-lg">
                            <PhoneOutgoing size={14} />
                          </span>
                        )}
                        <div>
                          <div className="font-semibold capitalize text-gray-800 dark:text-gray-200">
                            {call.direction}
                          </div>
                          <div className="text-xs text-gray-400">
                            {call.call_type === 'ai_voice' ? 'AI Voice' : 'Human Agent'}
                          </div>
                        </div>
                      </div>
                    </td>

                    <td className="p-3.5">
                      <div className="font-medium text-gray-900 dark:text-gray-100">{call.to_number}</div>
                      <div className="text-xs text-gray-500">From: {call.from_number}</div>
                    </td>

                    <td className="p-3.5">
                      {call.lead ? (
                        <span className="inline-flex items-center gap-1 text-xs font-medium text-blue-600 dark:text-blue-400">
                          Lead: {call.lead.name}
                        </span>
                      ) : call.deal ? (
                        <span className="inline-flex items-center gap-1 text-xs font-medium text-purple-600 dark:text-purple-400">
                          Deal: {call.deal.name}
                        </span>
                      ) : (
                        <span className="text-xs text-gray-400">None linked</span>
                      )}
                    </td>

                    <td className="p-3.5 font-medium text-gray-700 dark:text-gray-300">
                      {call.duration_seconds}s
                    </td>

                    <td className="p-3.5">
                      <span className={`text-xs px-2.5 py-1 rounded-full font-medium ${
                        call.disposition === 'interested'
                          ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40'
                          : call.disposition === 'callback_requested'
                          ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40'
                          : call.disposition === 'dnd_requested'
                          ? 'bg-red-100 text-red-700 dark:bg-red-900/40'
                          : 'bg-gray-100 text-gray-700 dark:bg-gray-700'
                      }`}>
                        {call.disposition || call.status}
                      </span>
                    </td>

                    <td className="p-3.5">
                      {call.quality_score ? (
                        <div className="flex items-center text-amber-500 gap-1 text-xs font-semibold">
                          <Star size={14} className="fill-amber-400" />
                          <span>{call.quality_score} / 5</span>
                        </div>
                      ) : (
                        <span className="text-xs text-gray-400">Not reviewed</span>
                      )}
                    </td>

                    <td className="p-3.5">
                      <div className="flex items-center gap-2">
                        {call.recording_url && (
                          <button
                            onClick={() => setPlaybackAudioUrl(call.recording_url!)}
                            title="Play Recording"
                            className="p-1.5 text-blue-600 hover:bg-blue-50 rounded"
                          >
                            <Play size={16} />
                          </button>
                        )}
                        <button
                          onClick={() => {
                            setSelectedCallForReview(call);
                            setReviewScore(call.quality_score || 5);
                            setReviewFeedback(call.manager_feedback || '');
                            setShowReviewModal(true);
                          }}
                          title="Manager Review"
                          className="p-1.5 text-amber-600 hover:bg-amber-50 rounded"
                        >
                          <Star size={16} />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* ============================================================== */}
      {/* TAB 3: INBOUND CALL & CALLER ID LOOKUP */}
      {/* ============================================================== */}
      {activeTab === 'incoming' && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {/* Inbound Simulator Form */}
          <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-4">
            <h3 className="font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
              <PhoneIncoming className="text-emerald-600" size={18} /> Inbound Call Ingestion & Test
            </h3>
            <p className="text-xs text-gray-500">
              Simulate an incoming phone call to test customer number matching, IVR menu triggers, and caller ID popups.
            </p>

            <div className="space-y-3">
              <div>
                <label className="text-xs font-medium text-gray-600 dark:text-gray-400">Caller Number (From)</label>
                <input
                  type="text"
                  value={inboundFrom}
                  onChange={(e) => setInboundFrom(e.target.value)}
                  className="w-full mt-1 p-2.5 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl"
                />
              </div>

              <div>
                <label className="text-xs font-medium text-gray-600 dark:text-gray-400">Virtual Helpline Number (To)</label>
                <input
                  type="text"
                  value={inboundTo}
                  onChange={(e) => setInboundTo(e.target.value)}
                  className="w-full mt-1 p-2.5 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl"
                />
              </div>

              <button
                onClick={async () => {
                  try {
                    const res = await inboundCallMutation.mutateAsync({
                      from_number: inboundFrom,
                      to_number: inboundTo,
                    });
                    if (res.data) {
                      setActiveCall(res.data);
                      addToast('Incoming call successfully routed through IVR', 'success');
                    }
                  } catch (e: any) {
                    addToast(e.message || 'Inbound routing failed', 'error');
                  }
                }}
                className="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl flex items-center justify-center gap-2"
              >
                <PhoneIncoming size={18} /> Simulate Inbound Call
              </button>
            </div>
          </div>

          {/* Caller ID Card Preview */}
          <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-4">
            <h3 className="font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
              <UserCheck className="text-blue-600" size={18} /> Caller Identification Card
            </h3>

            {callerLookupData ? (
              <div className="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-xl border border-gray-200 dark:border-gray-700 space-y-3">
                <div className="flex items-center justify-between">
                  <span className="font-bold text-gray-900 dark:text-gray-100 text-base">
                    {callerLookupData.customer_name}
                  </span>
                  <span className={`text-xs px-2 py-0.5 rounded font-medium ${
                    callerLookupData.is_known_customer
                      ? 'bg-emerald-100 text-emerald-700'
                      : 'bg-amber-100 text-amber-700'
                  }`}>
                    {callerLookupData.is_known_customer ? 'Verified Contact' : 'Unknown Caller'}
                  </span>
                </div>

                <div className="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                  <div>Phone: {callerLookupData.normalized_phone}</div>
                  {callerLookupData.customer_email && <div>Email: {callerLookupData.customer_email}</div>}
                  {callerLookupData.company_name && <div>Company: {callerLookupData.company_name}</div>}
                </div>

                {callerLookupData.open_deals && callerLookupData.open_deals.length > 0 && (
                  <div className="pt-2 border-t border-gray-200 dark:border-gray-700">
                    <div className="text-xs font-semibold text-purple-600 mb-1">Open Opportunities:</div>
                    {callerLookupData.open_deals.map((d) => (
                      <div key={d.id} className="text-xs flex justify-between text-gray-700 dark:text-gray-300">
                        <span>{d.name}</span>
                        <span className="font-bold">₹{d.value.toLocaleString()}</span>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            ) : (
              <div className="text-center py-12 text-gray-400 text-sm">
                Enter a caller number to test live caller identification card.
              </div>
            )}
          </div>
        </div>
      )}

      {/* ============================================================== */}
      {/* TAB 4: AI VOICE AGENTS MANAGER */}
      {/* ============================================================== */}
      {activeTab === 'agents' && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {agents.map((agent) => (
              <div
                key={agent.id}
                className="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-4 flex flex-col justify-between"
              >
                <div>
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-gray-900 dark:text-gray-100 text-base">
                      {agent.name}
                    </span>
                    <span className="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 font-medium capitalize">
                      {agent.status}
                    </span>
                  </div>

                  <p className="text-xs text-gray-500 mt-1 line-clamp-2">{agent.description}</p>

                  <div className="mt-3 space-y-1.5 text-xs text-gray-600 dark:text-gray-400">
                    <div className="flex items-center gap-1.5">
                      <Languages size={14} className="text-blue-500" />
                      <span>Language: <strong className="capitalize">{agent.language}</strong></span>
                    </div>
                    <div className="flex items-center gap-1.5">
                      <Sparkles size={14} className="text-amber-500" />
                      <span>Purpose: <strong className="capitalize">{agent.business_purpose?.replace('_', ' ')}</strong></span>
                    </div>
                    <div className="flex items-center gap-1.5">
                      <Sliders size={14} className="text-purple-500" />
                      <span>Confidence Threshold: <strong>{Math.round((agent.confidence_threshold || 0.65) * 100)}%</strong></span>
                    </div>
                  </div>
                </div>

                <div className="flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-700">
                  <button
                    onClick={() => {
                      setDialNumber('+919876543210');
                      setSelectedAgentId(agent.id);
                      setActiveTab('dialer');
                    }}
                    className="text-xs bg-blue-50 text-blue-600 dark:bg-blue-900/30 px-3 py-1.5 rounded-lg font-semibold hover:bg-blue-100"
                  >
                    Test in Softphone
                  </button>
                  <button
                    onClick={() => deleteAgentMutation.mutate(agent.id)}
                    className="text-gray-400 hover:text-red-500 p-1"
                  >
                    <Trash2 size={16} />
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* ============================================================== */}
      {/* TAB 5: IVR FLOWS & QUEUES */}
      {/* ============================================================== */}
      {activeTab === 'ivr' && (
        <div className="space-y-6">
          <div className="flex justify-between items-center">
            <h3 className="font-semibold text-gray-800 dark:text-gray-100">Configured IVR Menus & Queues</h3>
            <div className="flex gap-2">
              <button
                onClick={() => setShowIvrModal(true)}
                className="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1"
              >
                <Plus size={14} /> Add IVR Flow
              </button>
              <button
                onClick={() => setShowQueueModal(true)}
                className="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1"
              >
                <Plus size={14} /> Add Queue
              </button>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {ivrFlows.map((flow) => (
              <div key={flow.id} className="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-3">
                <div className="flex items-center justify-between">
                  <span className="font-bold text-gray-800 dark:text-gray-200">{flow.name}</span>
                  <span className="text-xs px-2 py-0.5 rounded bg-blue-100 text-blue-700">Language: {flow.language}</span>
                </div>
                <p className="text-xs text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-900 p-2.5 rounded-lg font-mono">
                  "{flow.welcome_audio_text}"
                </p>
                <div className="text-xs text-gray-500">Unanswered fallback: <strong>{flow.unanswered_action}</strong></div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* ============================================================== */}
      {/* TAB 6: TELEPHONY PROVIDERS */}
      {/* ============================================================== */}
      {activeTab === 'providers' && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {providers.map((p) => (
              <div key={p.id} className="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-3">
                <div className="flex items-center justify-between">
                  <span className="font-bold text-gray-800 dark:text-gray-200">{p.name} ({p.provider.toUpperCase()})</span>
                  <button
                    onClick={() => testProviderMutation.mutate(p.id)}
                    className="text-xs bg-emerald-50 text-emerald-700 px-3 py-1 rounded-lg border border-emerald-200 font-semibold hover:bg-emerald-100"
                  >
                    Test API Connection
                  </button>
                </div>
                <div className="text-xs text-gray-500 space-y-1">
                  <div>Account SID: {p.account_sid}</div>
                  <div>Token: {p.masked_token}</div>
                  <div>Incoming Webhook: <code className="bg-gray-100 px-1 py-0.5 rounded">{p.incoming_webhook_url}</code></div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* ============================================================== */}
      {/* TAB 7: ANALYTICS & BI DASHBOARD */}
      {/* ============================================================== */}
      {activeTab === 'analytics' && reportsData && (
        <div className="space-y-6">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div className="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
              <div className="text-xs text-gray-500 font-medium">Inbound vs Outbound</div>
              <div className="text-lg font-bold text-gray-800 dark:text-gray-200 mt-1">
                {reportsData.summary.inbound_calls} / {reportsData.summary.outbound_calls}
              </div>
            </div>

            <div className="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
              <div className="text-xs text-gray-500 font-medium">Call Answer Rate</div>
              <div className="text-lg font-bold text-emerald-600 mt-1">
                {reportsData.summary.answer_rate_pct}%
              </div>
            </div>

            <div className="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
              <div className="text-xs text-gray-500 font-medium">AI Qualification Rate</div>
              <div className="text-lg font-bold text-purple-600 mt-1">
                {reportsData.ai_performance.ai_qualification_rate_pct}%
              </div>
            </div>

            <div className="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
              <div className="text-xs text-gray-500 font-medium">Total Spend (INR)</div>
              <div className="text-lg font-bold text-blue-600 mt-1">
                ₹{reportsData.summary.total_cost_inr.toFixed(2)}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Call Confirmation Modal */}
      {showCallConfirmModal && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-gray-800 rounded-2xl p-6 max-w-md w-full shadow-2xl space-y-4">
            <h3 className="font-bold text-lg text-gray-900 dark:text-gray-100">Confirm Outbound Call</h3>
            <p className="text-sm text-gray-600 dark:text-gray-400">
              You are about to initiate an enterprise call to <strong>{dialNumber}</strong>.
            </p>

            {callerLookupData?.is_known_customer && (
              <div className="p-3 bg-blue-50 dark:bg-blue-900/30 rounded-xl text-xs text-blue-700 dark:text-blue-300">
                Contact: <strong>{callerLookupData.customer_name}</strong>
              </div>
            )}

            <div className="flex justify-end gap-2 pt-2">
              <button
                onClick={() => setShowCallConfirmModal(false)}
                className="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-xl"
              >
                Cancel
              </button>
              <button
                onClick={handleExecuteCall}
                className="px-5 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow"
              >
                Connect Call
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Disposition Modal */}
      {showDispositionModal && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-gray-800 rounded-2xl p-6 max-w-md w-full shadow-2xl space-y-4">
            <h3 className="font-bold text-lg text-gray-900 dark:text-gray-100">Record Call Outcome</h3>
            <p className="text-xs text-gray-500">Select disposition code to trigger CRM updates & follow-up tasks.</p>

            <div className="grid grid-cols-2 gap-2">
              <button
                onClick={() => handleSaveDisposition('interested', 'qualified')}
                className="p-3 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 rounded-xl text-xs font-bold border border-emerald-200"
              >
                Interested & Qualified
              </button>
              <button
                onClick={() => handleSaveDisposition('callback_requested', 'follow_up_needed')}
                className="p-3 bg-blue-50 hover:bg-blue-100 text-blue-800 rounded-xl text-xs font-bold border border-blue-200"
              >
                Callback Requested
              </button>
              <button
                onClick={() => handleSaveDisposition('not_interested', 'unqualified')}
                className="p-3 bg-gray-50 hover:bg-gray-100 text-gray-800 rounded-xl text-xs font-bold border border-gray-200"
              >
                Not Interested
              </button>
              <button
                onClick={() => handleSaveDisposition('dnd_requested', 'unqualified')}
                className="p-3 bg-red-50 hover:bg-red-100 text-red-800 rounded-xl text-xs font-bold border border-red-200"
              >
                DND / Opt-Out
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Quality Review Modal */}
      {showReviewModal && selectedCallForReview && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-gray-800 rounded-2xl p-6 max-w-md w-full shadow-2xl space-y-4">
            <h3 className="font-bold text-lg text-gray-900 dark:text-gray-100">Manager Quality Audit</h3>
            <div className="flex items-center gap-2 justify-center py-3">
              {[1, 2, 3, 4, 5].map((star) => (
                <button
                  key={star}
                  onClick={() => setReviewScore(star)}
                  className={`p-1 ${reviewScore >= star ? 'text-amber-500' : 'text-gray-300'}`}
                >
                  <Star size={24} className={reviewScore >= star ? 'fill-amber-400' : ''} />
                </button>
              ))}
            </div>

            <textarea
              value={reviewFeedback}
              onChange={(e) => setReviewFeedback(e.target.value)}
              placeholder="Enter quality evaluation feedback..."
              className="w-full p-3 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-xs"
              rows={3}
            />

            <div className="flex justify-end gap-2">
              <button
                onClick={() => setShowReviewModal(false)}
                className="px-4 py-2 text-sm text-gray-600 rounded-xl"
              >
                Cancel
              </button>
              <button
                onClick={handleSaveReview}
                className="px-5 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow"
              >
                Submit Review
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
