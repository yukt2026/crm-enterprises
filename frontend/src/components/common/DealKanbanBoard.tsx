import React, { useState, useMemo } from 'react';
import {
  TrendingUp,
  DollarSign,
  AlertTriangle,
  Clock,
  Building2,
  User,
  ArrowRight,
  Plus,
  CheckCircle2,
  XCircle,
  Filter,
  BarChart2,
  Sparkles,
  ChevronDown,
  Layers,
  Settings2,
  Calendar,
  Tag,
  ShieldAlert,
  FileSignature,
  Award,
  FileText,
  CheckCheck,
  List,
  Kanban,
  PieChart,
  HelpCircle,
  Send,
  Key,
  RefreshCw,
  FileSpreadsheet,
  FileCheck,
  AlertCircle,
  ExternalLink,
  Receipt,
  Percent,
} from 'lucide-react';
import {
  usePipelines,
  useDeals,
  useMoveDealStage,
  useDealForecast,
  useStaleDeals,
  useAdvancedSalesForecast,
  useContracts,
  useContractTemplates,
  useCreateContract,
  useSendContractForSignature,
  useSignContract,
  useSalesCommissions,
  useCommissionPlans,
  useCreateCommissionPlan,
  useApproveCommission,
  useMarkCommissionPaid,
  useReverseCommission,
} from '../../hooks/useSalesPipeline';
import {
  useQuotations,
  useSubmitQuotationApproval,
  useApproveQuotation,
  useRejectQuotation,
  useConvertQuotationToInvoice,
} from '../../hooks/useFinance';
import { PipelineItem, PipelineStageItem, DealItem, ContractItem, SalesCommissionItem, QuotationRecord } from '../../api/types';
import DealDetailsDrawer from './DealDetailsDrawer';

interface DealKanbanBoardProps {
  theme?: 'light' | 'dark';
  onNewDeal?: () => void;
}

export default function DealKanbanBoard({
  theme = 'dark',
  onNewDeal,
}: DealKanbanBoardProps) {
  const [viewMode, setViewMode] = useState<'kanban' | 'list' | 'forecast' | 'contracts' | 'commissions' | 'quotations'>('kanban');
  const { data: pipelines = [], isLoading: loadingPipelines } = usePipelines();
  const [selectedPipelineId, setSelectedPipelineId] = useState<number | undefined>(undefined);
  const [selectedDeal, setSelectedDeal] = useState<DealItem | null>(null);
  const [onlyStale, setOnlyStale] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');

  // Stage transition modal state
  const [transitioningDeal, setTransitioningDeal] = useState<DealItem | null>(null);
  const [targetStage, setTargetStage] = useState<PipelineStageItem | null>(null);
  const [lostReason, setLostReason] = useState('');
  const [competitor, setCompetitor] = useState('');
  const [closureNotes, setClosureNotes] = useState('');
  const [closureValue, setClosureValue] = useState<number>(0);

  // Active pipeline resolution
  const activePipeline = useMemo(() => {
    if (selectedPipelineId) {
      return pipelines.find((p) => p.id === selectedPipelineId) || pipelines[0];
    }
    return pipelines.find((p) => p.is_default) || pipelines[0];
  }, [pipelines, selectedPipelineId]);

  const activePipelineId = activePipeline?.id;

  // Forecast data for active pipeline
  const { data: forecast } = useDealForecast({ pipeline_id: activePipelineId });
  const { data: advancedForecast } = useAdvancedSalesForecast({ pipeline_id: activePipelineId });

  // Stale deals data
  const { data: staleDeals = [] } = useStaleDeals(14);
  const staleDealIds = useMemo(() => new Set(staleDeals.map((s) => s.deal.id)), [staleDeals]);

  // Deals query for active pipeline
  const { data: allDeals = [], isLoading: loadingDeals } = useDeals({
    pipeline_id: activePipelineId,
  });

  const moveStageMutation = useMoveDealStage();

  // Contracts & Proposals
  const { data: contracts = [] } = useContracts({ deal_id: activePipelineId ? undefined : undefined });
  const { data: contractTemplates = [] } = useContractTemplates();
  const createContractMutation = useCreateContract();
  const sendContractMutation = useSendContractForSignature();
  const signContractMutation = useSignContract();
  const [showNewContractModal, setShowNewContractModal] = useState(false);
  const [newContractTitle, setNewContractTitle] = useState('');
  const [newContractValue, setNewContractValue] = useState(100000);
  const [newContractProvider, setNewContractProvider] = useState<'aadhaar_esign' | 'docusign' | 'internal_otp' | 'clickwrap'>('aadhaar_esign');
  const [selectedContractForSign, setSelectedContractForSign] = useState<ContractItem | null>(null);
  const [signatureText, setSignatureText] = useState('');

  // Commissions
  const { data: commissions = [] } = useSalesCommissions();
  const { data: commissionPlans = [] } = useCommissionPlans();
  const createPlanMutation = useCreateCommissionPlan();
  const approveCommissionMutation = useApproveCommission();
  const markCommissionPaidMutation = useMarkCommissionPaid();
  const reverseCommissionMutation = useReverseCommission();
  const [showNewPlanModal, setShowNewPlanModal] = useState(false);
  const [planName, setPlanName] = useState('Standard Executive Tier');
  const [planRate, setPlanRate] = useState(5.0);

  // Quotations & Approvals
  const { data: quotations = [] } = useQuotations();
  const submitQuoteApprovalMutation = useSubmitQuotationApproval();
  const approveQuoteMutation = useApproveQuotation();
  const rejectQuoteMutation = useRejectQuotation();
  const convertQuoteToInvoiceMutation = useConvertQuotationToInvoice();

  // Filtered deals
  const deals = useMemo(() => {
    return allDeals.filter((deal) => {
      if (onlyStale && !staleDealIds.has(deal.id)) return false;
      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase();
        const matchName = deal.name?.toLowerCase().includes(q);
        const matchCompany = deal.company?.name?.toLowerCase().includes(q);
        const matchContact = deal.contact?.name?.toLowerCase().includes(q);
        if (!matchName && !matchCompany && !matchContact) return false;
      }
      return true;
    });
  }, [allDeals, onlyStale, staleDealIds, searchQuery]);

  // Stages
  const stages: PipelineStageItem[] = useMemo(() => {
    if (activePipeline?.stages && activePipeline.stages.length > 0) {
      return [...activePipeline.stages].sort((a, b) => a.order_column - b.order_column);
    }
    return [
      { id: 1, pipeline_id: 1, name: 'Lead Qualified', code: 'qualified', probability: 20, order_column: 1, color: '#6366f1', is_won: false, is_lost: false, stale_days_threshold: 14 },
      { id: 2, pipeline_id: 1, name: 'Discovery / Demo', code: 'demo', probability: 40, order_column: 2, color: '#3b82f6', is_won: false, is_lost: false, stale_days_threshold: 14 },
      { id: 3, pipeline_id: 1, name: 'Proposal / Quote', code: 'proposal', probability: 60, order_column: 3, color: '#8b5cf6', is_won: false, is_lost: false, stale_days_threshold: 14 },
      { id: 4, pipeline_id: 1, name: 'Negotiation', code: 'negotiation', probability: 80, order_column: 4, color: '#f59e0b', is_won: false, is_lost: false, stale_days_threshold: 10 },
      { id: 5, pipeline_id: 1, name: 'Closed Won', code: 'won', probability: 100, order_column: 5, color: '#10b981', is_won: true, is_lost: false, stale_days_threshold: 999 },
      { id: 6, pipeline_id: 1, name: 'Closed Lost', code: 'lost', probability: 0, order_column: 6, color: '#ef4444', is_won: false, is_lost: true, stale_days_threshold: 999 },
    ];
  }, [activePipeline]);

  const handleStageDrop = (deal: DealItem, stage: PipelineStageItem) => {
    if (stage.is_won || stage.is_lost) {
      setTransitioningDeal(deal);
      setTargetStage(stage);
      setClosureValue(deal.value);
      setLostReason('');
      setCompetitor('');
      setClosureNotes('');
      return;
    }

    moveStageMutation.mutate({
      dealId: deal.id,
      data: {
        pipeline_stage_id: stage.id,
      },
    });
  };

  const handleConfirmTransition = () => {
    if (!transitioningDeal || !targetStage) return;

    const payload: any = {
      pipeline_stage_id: targetStage.id,
      notes: closureNotes || undefined,
    };

    if (targetStage.is_lost) {
      payload.lost_reason = lostReason || 'No reason specified';
      payload.competitor = competitor || undefined;
    }

    if (targetStage.is_won) {
      payload.value = closureValue || transitioningDeal.value;
    }

    moveStageMutation.mutate(
      {
        dealId: transitioningDeal.id,
        data: payload,
      },
      {
        onSettled: () => {
          setTransitioningDeal(null);
          setTargetStage(null);
        },
      }
    );
  };

  return (
    <div className="space-y-4">
      {/* Top Navigation & View Switcher */}
      <div className="flex flex-wrap items-center justify-between gap-3 p-4 rounded-2xl border bg-slate-900/60 border-slate-800">
        <div className="flex flex-wrap items-center gap-3">
          {/* Pipeline Selector */}
          <div className="flex items-center gap-2">
            <Layers className="w-5 h-5 text-indigo-400" />
            <select
              value={activePipelineId || ''}
              onChange={(e) => setSelectedPipelineId(Number(e.target.value))}
              className="bg-slate-800 border border-slate-700 text-slate-100 font-semibold rounded-xl px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              {pipelines.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name} {p.is_default ? '(Default)' : ''}
                </option>
              ))}
            </select>
          </div>

          {/* View Mode Buttons */}
          <div className="flex items-center bg-slate-800/80 p-1 rounded-xl border border-slate-700/80 text-xs font-medium">
            <button
              onClick={() => setViewMode('kanban')}
              className={`flex items-center gap-1.5 px-3 py-1 rounded-lg transition-colors ${
                viewMode === 'kanban' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              <Kanban className="w-3.5 h-3.5" />
              Kanban
            </button>
            <button
              onClick={() => setViewMode('list')}
              className={`flex items-center gap-1.5 px-3 py-1 rounded-lg transition-colors ${
                viewMode === 'list' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              <List className="w-3.5 h-3.5" />
              Deals Table
            </button>
            <button
              onClick={() => setViewMode('forecast')}
              className={`flex items-center gap-1.5 px-3 py-1 rounded-lg transition-colors ${
                viewMode === 'forecast' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              <PieChart className="w-3.5 h-3.5" />
              Forecast
            </button>
            <button
              onClick={() => setViewMode('contracts')}
              className={`flex items-center gap-1.5 px-3 py-1 rounded-lg transition-colors ${
                viewMode === 'contracts' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              <FileSignature className="w-3.5 h-3.5" />
              Contracts & E-Sign
            </button>
            <button
              onClick={() => setViewMode('commissions')}
              className={`flex items-center gap-1.5 px-3 py-1 rounded-lg transition-colors ${
                viewMode === 'commissions' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              <Award className="w-3.5 h-3.5" />
              Commissions
            </button>
            <button
              onClick={() => setViewMode('quotations')}
              className={`flex items-center gap-1.5 px-3 py-1 rounded-lg transition-colors ${
                viewMode === 'quotations' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              <Receipt className="w-3.5 h-3.5" />
              Quotations
            </button>
          </div>

          <div className="relative">
            <input
              type="text"
              placeholder="Filter deals..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-48 bg-slate-800/80 border border-slate-700/80 rounded-xl px-3 py-1.5 text-xs text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            />
          </div>

          <button
            onClick={() => setOnlyStale(!onlyStale)}
            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-medium border transition-colors ${
              onlyStale
                ? 'bg-amber-500/20 border-amber-500/50 text-amber-300'
                : 'bg-slate-800/60 border-slate-700 text-slate-400 hover:text-slate-200'
            }`}
          >
            <Clock className="w-3.5 h-3.5" />
            Stale ({staleDeals.length})
          </button>
        </div>

        <div className="flex items-center gap-2">
          {onNewDeal && (
            <button
              onClick={onNewDeal}
              className="flex items-center gap-2 px-3.5 py-1.5 bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white rounded-xl text-xs font-semibold shadow-sm transition-all"
            >
              <Plus className="w-4 h-4" />
              New Deal
            </button>
          )}
        </div>
      </div>

      {/* METRIC STRIP (Unweighted, Weighted, Committed, Won, Lost) */}
      {forecast && (
        <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
          <div className="p-3.5 rounded-2xl border bg-slate-900/60 border-slate-800 flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400 shrink-0">
              <DollarSign className="w-5 h-5" />
            </div>
            <div>
              <p className="text-[11px] font-medium text-slate-400">Open Pipeline</p>
              <p className="text-base font-bold text-slate-100 font-mono">
                ${Number(forecast.total_open_pipeline_value || 0).toLocaleString()}
              </p>
            </div>
          </div>

          <div className="p-3.5 rounded-2xl border bg-slate-900/60 border-slate-800 flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400 shrink-0">
              <TrendingUp className="w-5 h-5" />
            </div>
            <div>
              <p className="text-[11px] font-medium text-slate-400">Weighted Forecast</p>
              <p className="text-base font-bold text-purple-300 font-mono">
                ${Number(forecast.total_weighted_forecast || 0).toLocaleString()}
              </p>
            </div>
          </div>

          <div className="p-3.5 rounded-2xl border bg-slate-900/60 border-slate-800 flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 shrink-0">
              <CheckCheck className="w-5 h-5" />
            </div>
            <div>
              <p className="text-[11px] font-medium text-slate-400">Committed (≥80%)</p>
              <p className="text-base font-bold text-indigo-300 font-mono">
                ${Number(advancedForecast?.committed_forecast || 0).toLocaleString()}
              </p>
            </div>
          </div>

          <div className="p-3.5 rounded-2xl border bg-slate-900/60 border-slate-800 flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 shrink-0">
              <CheckCircle2 className="w-5 h-5" />
            </div>
            <div>
              <p className="text-[11px] font-medium text-slate-400">Closed Won</p>
              <p className="text-base font-bold text-emerald-300 font-mono">
                ${Number(forecast.total_won_value || 0).toLocaleString()}
              </p>
            </div>
          </div>

          <div className="p-3.5 rounded-2xl border bg-slate-900/60 border-slate-800 flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400 shrink-0">
              <XCircle className="w-5 h-5" />
            </div>
            <div>
              <p className="text-[11px] font-medium text-slate-400">Win Rate</p>
              <p className="text-base font-bold text-slate-100 font-mono">
                {forecast.win_rate_percentage}%
              </p>
            </div>
          </div>
        </div>
      )}

      {/* VIEW 1: KANBAN BOARD */}
      {viewMode === 'kanban' && (
        <div className="flex gap-4 overflow-x-auto pb-4 pt-1 min-h-[550px]">
          {stages.map((stage) => {
            const stageDeals = deals.filter((d) => {
              if (d.pipeline_stage_id) return d.pipeline_stage_id === stage.id;
              return (d.stage || '').toLowerCase() === stage.code.toLowerCase();
            });

            const stageValue = stageDeals.reduce((sum, d) => sum + (Number(d.value) || 0), 0);

            return (
              <div
                key={stage.id}
                className="w-80 shrink-0 rounded-2xl border flex flex-col bg-slate-900/60 border-slate-800/80 max-h-[75vh]"
              >
                {/* Column Header */}
                <div className="p-3 border-b border-slate-800 flex items-center justify-between bg-slate-900/80 rounded-t-2xl">
                  <div className="flex items-center gap-2">
                    <div
                      className="w-2.5 h-2.5 rounded-full"
                      style={{ backgroundColor: stage.color || '#6366f1' }}
                    />
                    <h4 className="font-bold text-xs text-slate-200">{stage.name}</h4>
                    <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-slate-800 text-slate-300">
                      {stageDeals.length}
                    </span>
                  </div>
                  <div className="text-right">
                    <span className="text-[11px] font-bold font-mono text-slate-300 block">
                      ${stageValue.toLocaleString()}
                    </span>
                    <span className="text-[9px] text-slate-400">
                      {stage.probability}% prob
                    </span>
                  </div>
                </div>

                {/* Cards Container */}
                <div className="flex-1 overflow-y-auto p-2.5 space-y-2.5">
                  {stageDeals.length === 0 ? (
                    <div className="py-16 text-center text-xs text-slate-600">
                      No deals in this stage
                    </div>
                  ) : (
                    stageDeals.map((deal) => {
                      const isStale = staleDealIds.has(deal.id);
                      return (
                        <div
                          key={deal.id}
                          onClick={() => setSelectedDeal(deal)}
                          className={`p-3.5 rounded-xl border bg-slate-800/80 hover:bg-slate-800 cursor-pointer transition-all shadow-sm group ${
                            isStale
                              ? 'border-amber-500/50 hover:border-amber-400/80 ring-1 ring-amber-500/20'
                              : 'border-slate-700/60 hover:border-indigo-500/50'
                          }`}
                        >
                          <div className="flex items-start justify-between gap-2 mb-2">
                            <h5 className="font-semibold text-xs text-slate-100 group-hover:text-indigo-300 transition-colors line-clamp-1">
                              {deal.name}
                            </h5>
                            <span className="text-xs font-bold font-mono text-emerald-400 shrink-0">
                              ${Number(deal.value || 0).toLocaleString()}
                            </span>
                          </div>

                          <div className="flex items-center justify-between text-[11px] text-slate-400 mb-2">
                            <span>Weighted: ${(Number(deal.weighted_value) || 0).toLocaleString()}</span>
                            <span>{deal.probability}%</span>
                          </div>

                          <div className="flex items-center justify-between text-[11px] text-slate-400 pt-2 border-t border-slate-700/40">
                            <div className="flex items-center gap-1.5 truncate">
                              <Building2 className="w-3 h-3 text-slate-500 shrink-0" />
                              <span className="truncate">{deal.company?.name || deal.customer?.name || 'Individual'}</span>
                            </div>
                            <div className="flex items-center gap-1 shrink-0">
                              <User className="w-3 h-3 text-slate-500" />
                              <span>{deal.assigned_user?.name?.split(' ')[0] || 'Unassigned'}</span>
                            </div>
                          </div>
                        </div>
                      );
                    })
                  )}
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* VIEW 2: LIST VIEW */}
      {viewMode === 'list' && (
        <div className="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden">
          <table className="w-full text-left text-xs">
            <thead className="bg-slate-800/60 text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-800">
              <tr>
                <th className="p-3">Deal Name</th>
                <th className="p-3">Account / Contact</th>
                <th className="p-3">Stage</th>
                <th className="p-3">Value</th>
                <th className="p-3">Weighted ($)</th>
                <th className="p-3">Probability</th>
                <th className="p-3">Assigned Owner</th>
                <th className="p-3">Close Date</th>
                <th className="p-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800/60 text-slate-200">
              {deals.length === 0 ? (
                <tr>
                  <td colSpan={9} className="p-8 text-center text-slate-500">No deals matching query</td>
                </tr>
              ) : (
                deals.map((deal) => (
                  <tr key={deal.id} className="hover:bg-slate-800/40 transition-colors">
                    <td className="p-3 font-semibold text-slate-100 flex items-center gap-2">
                      <span className="truncate max-w-[200px]">{deal.name}</span>
                      {deal.project_id && (
                        <span className="px-1.5 py-0.5 rounded text-[9px] bg-emerald-500/20 text-emerald-300">
                          Proj #{deal.project_id}
                        </span>
                      )}
                    </td>
                    <td className="p-3 text-slate-300">
                      {deal.company?.name || deal.contact?.name || 'Direct'}
                    </td>
                    <td className="p-3">
                      <span className="px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-800 text-indigo-300 border border-slate-700">
                        {deal.stage}
                      </span>
                    </td>
                    <td className="p-3 font-mono font-bold text-emerald-400">
                      ${Number(deal.value || 0).toLocaleString()}
                    </td>
                    <td className="p-3 font-mono text-purple-300">
                      ${Number(deal.weighted_value || 0).toLocaleString()}
                    </td>
                    <td className="p-3">{deal.probability}%</td>
                    <td className="p-3 text-slate-300">{deal.assigned_user?.name || 'Unassigned'}</td>
                    <td className="p-3 text-slate-400">{deal.expected_close_date || 'TBD'}</td>
                    <td className="p-3 text-right">
                      <button
                        onClick={() => setSelectedDeal(deal)}
                        className="px-2.5 py-1 bg-indigo-600/20 hover:bg-indigo-600/40 text-indigo-300 rounded text-[11px] font-medium"
                      >
                        Inspect
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}

      {/* VIEW 3: SALES FORECASTING & ASSUMPTIONS */}
      {viewMode === 'forecast' && (
        <div className="space-y-4">
          {/* Statutory / Statistical Disclaimer Banner */}
          <div className="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-start gap-3 text-amber-200">
            <AlertCircle className="w-5 h-5 text-amber-400 shrink-0 mt-0.5" />
            <div>
              <p className="text-xs font-bold uppercase tracking-wider text-amber-300">Statistical Projection Disclaimer</p>
              <p className="text-xs text-amber-200/90 leading-relaxed mt-0.5">
                {advancedForecast?.assumptions_notice ||
                  'Forecast projections are statistical models based on historical pipeline stage conversion probabilities and do not represent guaranteed or legally confirmed revenue.'}
              </p>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {/* Stage Breakdown Table */}
            <div className="p-4 rounded-2xl bg-slate-900/60 border border-slate-800 space-y-3">
              <h4 className="text-xs font-bold uppercase tracking-wider text-slate-300 flex items-center gap-2">
                <BarChart2 className="w-4 h-4 text-indigo-400" />
                Pipeline Stage Conversion & Weighting
              </h4>
              <div className="overflow-x-auto">
                <table className="w-full text-xs text-left">
                  <thead className="bg-slate-800 text-slate-400 text-[10px]">
                    <tr>
                      <th className="p-2">Stage</th>
                      <th className="p-2">Deals</th>
                      <th className="p-2">Probability</th>
                      <th className="p-2">Total ($)</th>
                      <th className="p-2">Weighted ($)</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-800 text-slate-200">
                    {advancedForecast?.stage_breakdown?.map((stg, i) => (
                      <tr key={i} className="hover:bg-slate-800/30">
                        <td className="p-2 font-medium">{stg.stage}</td>
                        <td className="p-2">{stg.deals_count}</td>
                        <td className="p-2 font-mono text-indigo-300">{stg.probability}%</td>
                        <td className="p-2 font-mono">${Number(stg.total_value).toLocaleString()}</td>
                        <td className="p-2 font-mono text-emerald-400 font-bold">${Number(stg.weighted_value).toLocaleString()}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            {/* Rep Performance */}
            <div className="p-4 rounded-2xl bg-slate-900/60 border border-slate-800 space-y-3">
              <h4 className="text-xs font-bold uppercase tracking-wider text-slate-300 flex items-center gap-2">
                <User className="w-4 h-4 text-indigo-400" />
                Sales Rep Pipeline Contribution
              </h4>
              <div className="overflow-x-auto">
                <table className="w-full text-xs text-left">
                  <thead className="bg-slate-800 text-slate-400 text-[10px]">
                    <tr>
                      <th className="p-2">Sales Rep</th>
                      <th className="p-2">Open Deals</th>
                      <th className="p-2">Open Pipeline ($)</th>
                      <th className="p-2">Weighted ($)</th>
                      <th className="p-2">Won ($)</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-800 text-slate-200">
                    {advancedForecast?.employee_performance?.map((emp, i) => (
                      <tr key={i} className="hover:bg-slate-800/30">
                        <td className="p-2 font-medium">{emp.user_name}</td>
                        <td className="p-2">{emp.open_deals_count}</td>
                        <td className="p-2 font-mono">${Number(emp.open_pipeline_value).toLocaleString()}</td>
                        <td className="p-2 font-mono text-purple-300">${Number(emp.weighted_value).toLocaleString()}</td>
                        <td className="p-2 font-mono text-emerald-400 font-bold">${Number(emp.won_value).toLocaleString()}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* VIEW 4: PROPOSALS & CONTRACTS WITH E-SIGNATURES */}
      {viewMode === 'contracts' && (
        <div className="space-y-4">
          <div className="flex items-center justify-between p-4 rounded-2xl bg-slate-900/60 border border-slate-800">
            <div>
              <h3 className="text-sm font-bold text-slate-100 flex items-center gap-2">
                <FileSignature className="w-4 h-4 text-indigo-400" />
                Proposals, MSAs & Cryptographic E-Signatures
              </h3>
              <p className="text-xs text-slate-400">
                Aadhaar eSign, DocuSign, OTP & Clickwrap with tamper-evident SHA-256 audit hashes
              </p>
            </div>
            <button
              onClick={() => setShowNewContractModal(true)}
              className="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-colors"
            >
              <Plus className="w-4 h-4" />
              New Proposal / Contract
            </button>
          </div>

          <div className="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-800/60 text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-800">
                <tr>
                  <th className="p-3">Contract #</th>
                  <th className="p-3">Title & Type</th>
                  <th className="p-3">Value</th>
                  <th className="p-3">E-Sign Provider</th>
                  <th className="p-3">Status</th>
                  <th className="p-3">Audit Hash</th>
                  <th className="p-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/60 text-slate-200">
                {contracts.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="p-8 text-center text-slate-500">
                      No contracts created yet. Click "New Proposal / Contract" to create one.
                    </td>
                  </tr>
                ) : (
                  contracts.map((c) => (
                    <tr key={c.id} className="hover:bg-slate-800/40 transition-colors">
                      <td className="p-3 font-mono font-bold text-indigo-400">{c.contract_number}</td>
                      <td className="p-3">
                        <span className="font-semibold block">{c.title}</span>
                        <span className="text-[10px] text-slate-400 uppercase">{c.contract_type} (v{c.version})</span>
                      </td>
                      <td className="p-3 font-mono font-bold text-emerald-400">
                        ${Number(c.contract_value || 0).toLocaleString()}
                      </td>
                      <td className="p-3">
                        <span className="px-2 py-0.5 rounded text-[10px] bg-slate-800 border border-slate-700 text-slate-300 font-mono">
                          {c.e_signature_provider}
                        </span>
                      </td>
                      <td className="p-3">
                        <span
                          className={`px-2 py-0.5 rounded-full text-[10px] font-semibold ${
                            c.status === 'signed'
                              ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                              : c.status === 'sent_for_signature'
                              ? 'bg-blue-500/20 text-blue-300 border border-blue-500/40'
                              : 'bg-slate-800 text-slate-400'
                          }`}
                        >
                          {c.status.replace('_', ' ')}
                        </span>
                      </td>
                      <td className="p-3 font-mono text-[10px] text-slate-400">
                        {c.audit_hash ? (
                          <span title={c.audit_hash} className="text-emerald-400 underline cursor-help">
                            {c.audit_hash.substring(0, 16)}...
                          </span>
                        ) : (
                          <span className="text-slate-600">Pending Execution</span>
                        )}
                      </td>
                      <td className="p-3 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          {c.status === 'draft' && (
                            <button
                              onClick={() => {
                                sendContractMutation.mutate({
                                  id: c.id,
                                  data: {
                                    signer_name: 'Authorized Signer',
                                    signer_email: 'signer@enterprise.com',
                                    provider: c.e_signature_provider,
                                  },
                                });
                              }}
                              className="px-2.5 py-1 bg-blue-600/20 hover:bg-blue-600/40 text-blue-300 rounded text-[11px] font-medium"
                            >
                              Dispatch
                            </button>
                          )}
                          {c.status === 'sent_for_signature' && (
                            <button
                              onClick={() => {
                                setSelectedContractForSign(c);
                                setSignatureText(`Signed by ${c.signer_name || 'Client Representative'}`);
                              }}
                              className="px-2.5 py-1 bg-emerald-600/20 hover:bg-emerald-600/40 text-emerald-300 rounded text-[11px] font-medium"
                            >
                              Sign Now
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          {/* New Contract Modal */}
          {showNewContractModal && (
            <div className="p-4 rounded-2xl bg-slate-900 border border-slate-700 space-y-3">
              <h4 className="text-xs font-bold text-slate-200">Generate Master Agreement / Proposal</h4>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                <input
                  type="text"
                  placeholder="Contract Title (e.g. Enterprise Cloud Agreement)"
                  value={newContractTitle}
                  onChange={(e) => setNewContractTitle(e.target.value)}
                  className="bg-slate-800 border border-slate-700 rounded-xl px-3 py-1.5 text-xs text-slate-100"
                />
                <input
                  type="number"
                  placeholder="Contract Value ($)"
                  value={newContractValue}
                  onChange={(e) => setNewContractValue(Number(e.target.value))}
                  className="bg-slate-800 border border-slate-700 rounded-xl px-3 py-1.5 text-xs text-slate-100 font-mono"
                />
                <select
                  value={newContractProvider}
                  onChange={(e: any) => setNewContractProvider(e.target.value)}
                  className="bg-slate-800 border border-slate-700 rounded-xl px-3 py-1.5 text-xs text-slate-100"
                >
                  <option value="aadhaar_esign">Aadhaar eSign (India IT Act §3A)</option>
                  <option value="docusign">DocuSign Global</option>
                  <option value="internal_otp">Dual OTP Verification</option>
                  <option value="clickwrap">Clickwrap Audit Log</option>
                </select>
              </div>
              <div className="flex gap-2">
                <button
                  onClick={() => {
                    createContractMutation.mutate(
                      {
                        title: newContractTitle || 'Master Services Agreement',
                        contract_type: 'contract',
                        contract_value: newContractValue,
                        e_signature_provider: newContractProvider,
                        content_markdown: '## Master Services Agreement\n\nStandard commercial and legal terms.',
                      },
                      { onSuccess: () => setShowNewContractModal(false) }
                    );
                  }}
                  className="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold"
                >
                  Generate Agreement
                </button>
                <button
                  onClick={() => setShowNewContractModal(false)}
                  className="px-3 py-1.5 bg-slate-800 text-slate-400 rounded-xl text-xs"
                >
                  Cancel
                </button>
              </div>
            </div>
          )}

          {/* E-Signature Modal */}
          {selectedContractForSign && (
            <div className="p-4 rounded-2xl bg-slate-900 border border-emerald-500/40 space-y-3">
              <h4 className="text-xs font-bold text-emerald-300 flex items-center gap-2">
                <FileCheck className="w-4 h-4" />
                Execute Cryptographic E-Signature (Provider: {selectedContractForSign.e_signature_provider})
              </h4>
              <p className="text-xs text-slate-400">
                Signing Contract: <strong className="text-slate-200">{selectedContractForSign.contract_number}</strong>
              </p>
              <input
                type="text"
                value={signatureText}
                onChange={(e) => setSignatureText(e.target.value)}
                placeholder="Digital signature / Signer Full Name"
                className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100"
              />
              <div className="flex gap-2">
                <button
                  onClick={() => {
                    signContractMutation.mutate(
                      {
                        id: selectedContractForSign.id,
                        data: {
                          signature_data: signatureText || 'Legally Executed Signature',
                          ip_address: '203.0.113.195',
                        },
                      },
                      { onSuccess: () => setSelectedContractForSign(null) }
                    );
                  }}
                  className="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-semibold"
                >
                  Confirm & Stamp SHA-256 Hash
                </button>
                <button
                  onClick={() => setSelectedContractForSign(null)}
                  className="px-3 py-1.5 bg-slate-800 text-slate-400 rounded-xl text-xs"
                >
                  Cancel
                </button>
              </div>
            </div>
          )}
        </div>
      )}

      {/* VIEW 5: SALES COMMISSIONS & INCENTIVES */}
      {viewMode === 'commissions' && (
        <div className="space-y-4">
          <div className="flex items-center justify-between p-4 rounded-2xl bg-slate-900/60 border border-slate-800">
            <div>
              <h3 className="text-sm font-bold text-slate-100 flex items-center gap-2">
                <Award className="w-4 h-4 text-amber-400" />
                Sales Commission Engine & Incentive Plans
              </h3>
              <p className="text-xs text-slate-400">
                Fixed, percentage and tiered compensation with duplicate prevention & clawback reversal
              </p>
            </div>
            <button
              onClick={() => setShowNewPlanModal(true)}
              className="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-colors"
            >
              <Plus className="w-4 h-4" />
              Configure Plan
            </button>
          </div>

          <div className="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-800/60 text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-800">
                <tr>
                  <th className="p-3">Rep User</th>
                  <th className="p-3">Deal Value</th>
                  <th className="p-3">Basis Amount</th>
                  <th className="p-3">Rate</th>
                  <th className="p-3">Commission ($)</th>
                  <th className="p-3">Status</th>
                  <th className="p-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/60 text-slate-200">
                {commissions.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="p-8 text-center text-slate-500">
                      No commission records yet. Commission is automatically computed on Won deals or payments.
                    </td>
                  </tr>
                ) : (
                  commissions.map((comm) => (
                    <tr key={comm.id} className="hover:bg-slate-800/40 transition-colors">
                      <td className="p-3 font-semibold text-slate-100">
                        {comm.user?.name || `User #${comm.user_id}`}
                      </td>
                      <td className="p-3 font-mono">${Number(comm.deal_value).toLocaleString()}</td>
                      <td className="p-3 font-mono">${Number(comm.commission_basis_amount).toLocaleString()}</td>
                      <td className="p-3 font-mono text-indigo-300">{comm.rate_applied_percentage}%</td>
                      <td className="p-3 font-mono font-bold text-emerald-400">
                        ${Number(comm.calculated_commission_amount).toLocaleString()}
                      </td>
                      <td className="p-3">
                        <span
                          className={`px-2 py-0.5 rounded-full text-[10px] font-semibold ${
                            comm.status === 'paid'
                              ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                              : comm.status === 'approved'
                              ? 'bg-blue-500/20 text-blue-300 border border-blue-500/40'
                              : comm.status === 'clawback_reversed'
                              ? 'bg-rose-500/20 text-rose-300 border border-rose-500/40'
                              : 'bg-amber-500/20 text-amber-300 border border-amber-500/40'
                          }`}
                        >
                          {comm.status.replace('_', ' ')}
                        </span>
                      </td>
                      <td className="p-3 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          {comm.status === 'pending_approval' && (
                            <button
                              onClick={() => approveCommissionMutation.mutate({ id: comm.id, notes: 'Approved standard tier' })}
                              className="px-2.5 py-1 bg-blue-600/20 hover:bg-blue-600/40 text-blue-300 rounded text-[11px] font-medium"
                            >
                              Approve
                            </button>
                          )}
                          {comm.status === 'approved' && (
                            <button
                              onClick={() => markCommissionPaidMutation.mutate({ id: comm.id, payoutReference: 'PAYOUT-OCT-01' })}
                              className="px-2.5 py-1 bg-emerald-600/20 hover:bg-emerald-600/40 text-emerald-300 rounded text-[11px] font-medium"
                            >
                              Mark Paid
                            </button>
                          )}
                          {comm.status === 'approved' && (
                            <button
                              onClick={() => reverseCommissionMutation.mutate({ id: comm.id, reason: 'Deal cancelled / refund issued' })}
                              className="px-2.5 py-1 bg-rose-600/20 hover:bg-rose-600/40 text-rose-300 rounded text-[11px] font-medium"
                            >
                              Clawback
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          {/* New Commission Plan Modal */}
          {showNewPlanModal && (
            <div className="p-4 rounded-2xl bg-slate-900 border border-slate-700 space-y-3">
              <h4 className="text-xs font-bold text-slate-200">Configure Commission Plan</h4>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <input
                  type="text"
                  value={planName}
                  onChange={(e) => setPlanName(e.target.value)}
                  placeholder="Plan Name"
                  className="bg-slate-800 border border-slate-700 rounded-xl px-3 py-1.5 text-xs text-slate-100"
                />
                <input
                  type="number"
                  value={planRate}
                  onChange={(e) => setPlanRate(Number(e.target.value))}
                  placeholder="Base Rate (%)"
                  className="bg-slate-800 border border-slate-700 rounded-xl px-3 py-1.5 text-xs text-slate-100 font-mono"
                />
              </div>
              <div className="flex gap-2">
                <button
                  onClick={() => {
                    createPlanMutation.mutate(
                      {
                        name: planName,
                        code: planName.toLowerCase().replace(/\s+/g, '_'),
                        plan_type: 'percentage',
                        calculation_basis: 'deal_won',
                        base_rate_percentage: planRate,
                        is_active: true,
                      },
                      { onSuccess: () => setShowNewPlanModal(false) }
                    );
                  }}
                  className="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold"
                >
                  Save Plan
                </button>
                <button
                  onClick={() => setShowNewPlanModal(false)}
                  className="px-3 py-1.5 bg-slate-800 text-slate-400 rounded-xl text-xs"
                >
                  Cancel
                </button>
              </div>
            </div>
          )}
        </div>
      )}

      {/* VIEW 6: QUOTATIONS & APPROVALS */}
      {viewMode === 'quotations' && (
        <div className="space-y-4">
          <div className="p-4 rounded-2xl bg-slate-900/60 border border-slate-800 flex items-center justify-between">
            <div>
              <h3 className="text-sm font-bold text-slate-100 flex items-center gap-2">
                <Receipt className="w-4 h-4 text-emerald-400" />
                Quotation Builder, Discount Approval & Invoicing
              </h3>
              <p className="text-xs text-slate-400">
                Discount approval governance and one-click duplicate-guarded invoice conversion
              </p>
            </div>
          </div>

          <div className="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-800/60 text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-800">
                <tr>
                  <th className="p-3">Quote #</th>
                  <th className="p-3">Customer / Deal</th>
                  <th className="p-3">Total Amount</th>
                  <th className="p-3">Discount</th>
                  <th className="p-3">Approval Status</th>
                  <th className="p-3">Invoice Status</th>
                  <th className="p-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/60 text-slate-200">
                {quotations.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="p-8 text-center text-slate-500">
                      No quotations found.
                    </td>
                  </tr>
                ) : (
                  quotations.map((q) => (
                    <tr key={q.id} className="hover:bg-slate-800/40 transition-colors">
                      <td className="p-3 font-mono font-bold text-indigo-400">{q.quotation_number || q.quote_number}</td>
                      <td className="p-3 font-semibold text-slate-100">
                        {q.customer?.name || q.deal?.name || 'Standard Account'}
                      </td>
                      <td className="p-3 font-mono font-bold text-emerald-400">
                        ${Number(q.total_amount).toLocaleString()}
                      </td>
                      <td className="p-3 font-mono text-purple-300">
                        {q.discount_rate || 0}%
                      </td>
                      <td className="p-3">
                        <span
                          className={`px-2 py-0.5 rounded-full text-[10px] font-semibold ${
                            q.approval_status === 'approved'
                              ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                              : q.approval_status === 'pending_approval'
                              ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40'
                              : q.approval_status === 'rejected'
                              ? 'bg-rose-500/20 text-rose-300 border border-rose-500/40'
                              : 'bg-slate-800 text-slate-400'
                          }`}
                        >
                          {q.approval_status || 'not_required'}
                        </span>
                      </td>
                      <td className="p-3">
                        {q.converted_to_invoice_id ? (
                          <span className="px-2 py-0.5 rounded text-[10px] bg-emerald-500/10 text-emerald-300 border border-emerald-500/30">
                            Invoice #{q.converted_to_invoice_id}
                          </span>
                        ) : (
                          <span className="text-slate-500 text-[11px]">Not Converted</span>
                        )}
                      </td>
                      <td className="p-3 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          {q.approval_status === 'pending_approval' && (
                            <button
                              onClick={() => approveQuoteMutation.mutate({ id: q.id, notes: 'Discount approved by manager' })}
                              className="px-2.5 py-1 bg-emerald-600/20 hover:bg-emerald-600/40 text-emerald-300 rounded text-[11px] font-medium"
                            >
                              Approve
                            </button>
                          )}
                          {!q.converted_to_invoice_id && (
                            <button
                              onClick={() => convertQuoteToInvoiceMutation.mutate(q.id)}
                              className="px-2.5 py-1 bg-indigo-600/20 hover:bg-indigo-600/40 text-indigo-300 rounded text-[11px] font-medium"
                            >
                              Convert to Invoice
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Stage Transition Modal */}
      {transitioningDeal && targetStage && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4">
            <h3 className="font-bold text-base text-slate-100 flex items-center gap-2">
              {targetStage.is_won ? (
                <CheckCircle2 className="w-5 h-5 text-emerald-400" />
              ) : (
                <XCircle className="w-5 h-5 text-rose-400" />
              )}
              {targetStage.is_won ? 'Close Deal as Won' : 'Mark Deal as Lost'}
            </h3>

            <p className="text-xs text-slate-400">
              Deal: <span className="font-semibold text-slate-200">{transitioningDeal.name}</span>
            </p>

            {targetStage.is_won && (
              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">
                  Final Closed Revenue ($)
                </label>
                <input
                  type="number"
                  value={closureValue}
                  onChange={(e) => setClosureValue(Number(e.target.value))}
                  className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>
            )}

            {targetStage.is_lost && (
              <div className="space-y-3">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">
                    Lost Reason <span className="text-rose-400">*</span>
                  </label>
                  <select
                    value={lostReason}
                    onChange={(e) => setLostReason(e.target.value)}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-rose-500"
                  >
                    <option value="">Select lost reason...</option>
                    <option value="Price too high / Budget constraint">Price too high / Budget constraint</option>
                    <option value="Lost to competitor">Lost to competitor</option>
                    <option value="Feature gap / Technical mismatch">Feature gap / Technical mismatch</option>
                    <option value="Project cancelled / Postponed">Project cancelled / Postponed</option>
                    <option value="Unresponsive / Ghosted">Unresponsive / Ghosted</option>
                    <option value="Other">Other</option>
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">
                    Winning Competitor (if applicable)
                  </label>
                  <input
                    type="text"
                    placeholder="e.g. Salesforce, HubSpot, Zoho..."
                    value={competitor}
                    onChange={(e) => setCompetitor(e.target.value)}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-rose-500"
                  />
                </div>
              </div>
            )}

            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">
                Closure Notes
              </label>
              <textarea
                rows={2}
                placeholder="Optional closure notes or context..."
                value={closureNotes}
                onChange={(e) => setClosureNotes(e.target.value)}
                className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>

            <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
              <button
                onClick={() => {
                  setTransitioningDeal(null);
                  setTargetStage(null);
                }}
                className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold"
              >
                Cancel
              </button>
              <button
                onClick={handleConfirmTransition}
                disabled={moveStageMutation.isPending || (targetStage.is_lost && !lostReason)}
                className={`px-4 py-2 text-white rounded-xl text-xs font-semibold disabled:opacity-50 ${
                  targetStage.is_won
                    ? 'bg-emerald-600 hover:bg-emerald-500'
                    : 'bg-rose-600 hover:bg-rose-500'
                }`}
              >
                {moveStageMutation.isPending ? 'Updating...' : 'Confirm Closure'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Deal Details Drawer */}
      {selectedDeal && (
        <DealDetailsDrawer
          deal={selectedDeal}
          pipelineStages={stages}
          onClose={() => setSelectedDeal(null)}
          onUpdate={() => {
            // Refetches automatically through React Query invalidation
          }}
        />
      )}
    </div>
  );
}
