import React, { useState } from 'react';
import {
  X,
  TrendingUp,
  DollarSign,
  Building2,
  User,
  Calendar,
  Clock,
  Archive,
  CheckCircle2,
  XCircle,
  Plus,
  Trash2,
  Package,
  History,
  FileText,
  Activity,
  Layers,
  Tag,
  ArrowRight,
} from 'lucide-react';
import {
  useMoveDealStage,
  useAddDealProduct,
  useRemoveDealProduct,
  useArchiveDeal,
  useTasks,
  useTransferDealOwnership,
  useReopenDeal,
  useConvertDealToProject,
} from '../../hooks/useSalesPipeline';
import { useEntityActivities } from '../../hooks/useCrmCore';
import { DealItem, PipelineStageItem } from '../../api/types';

interface DealDetailsDrawerProps {
  deal: DealItem;
  pipelineStages: PipelineStageItem[];
  onClose: () => void;
  onUpdate?: () => void;
}

export default function DealDetailsDrawer({
  deal,
  pipelineStages,
  onClose,
  onUpdate,
}: DealDetailsDrawerProps) {
  const [activeTab, setActiveTab] = useState<'overview' | 'products' | 'history' | 'activities'>('overview');

  // Add Product form state
  const [showAddProduct, setShowAddProduct] = useState(false);
  const [productName, setProductName] = useState('');
  const [productQty, setProductQty] = useState(1);
  const [productPrice, setProductPrice] = useState(0);
  const [productDiscount, setProductDiscount] = useState(0);

  // Prompt 39 Extended States
  const [showTransferModal, setShowTransferModal] = useState(false);
  const [newUserId, setNewUserId] = useState<number>(deal.assigned_user_id || 1);
  const [transferReason, setTransferReason] = useState('');

  const [showReopenModal, setShowReopenModal] = useState(false);
  const [reopenStageId, setReopenStageId] = useState<number>(pipelineStages[0]?.id || 1);
  const [reopenReason, setReopenReason] = useState('');

  const [showConvertModal, setShowConvertModal] = useState(false);
  const [projectName, setProjectName] = useState(`Onboarding: ${deal.name}`);
  const [projectDescription, setProjectDescription] = useState(`Client delivery & onboarding for Deal #${deal.id}`);

  // Mutations
  const moveStageMutation = useMoveDealStage();
  const addProductMutation = useAddDealProduct();
  const removeProductMutation = useRemoveDealProduct();
  const archiveMutation = useArchiveDeal();
  const transferOwnershipMutation = useTransferDealOwnership();
  const reopenDealMutation = useReopenDeal();
  const convertToProjectMutation = useConvertDealToProject();

  // Queries
  const { data: activities = [] } = useEntityActivities({ deal_id: deal.id });
  const { data: tasks = [] } = useTasks({ deal_id: deal.id });

  const handleStageSelect = (stageId: number) => {
    moveStageMutation.mutate({
      dealId: deal.id,
      data: { pipeline_stage_id: stageId },
    });
  };

  const handleAddProduct = (e: React.FormEvent) => {
    e.preventDefault();
    if (!productName || productPrice <= 0) return;

    addProductMutation.mutate(
      {
        dealId: deal.id,
        data: {
          name: productName,
          quantity: productQty,
          unit_price: productPrice,
          discount: productDiscount,
        },
      },
      {
        onSuccess: () => {
          setShowAddProduct(false);
          setProductName('');
          setProductQty(1);
          setProductPrice(0);
          setProductDiscount(0);
          onUpdate?.();
        },
      }
    );
  };

  const handleRemoveProduct = (productId: number) => {
    removeProductMutation.mutate({ dealId: deal.id, productId }, { onSuccess: () => onUpdate?.() });
  };

  const handleToggleArchive = () => {
    archiveMutation.mutate(
      { dealId: deal.id, archive: !deal.is_archived },
      { onSuccess: () => onUpdate?.() }
    );
  };

  const currentStage = pipelineStages.find((s) => s.id === deal.pipeline_stage_id);

  return (
    <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex justify-end">
      <div className="w-full max-w-2xl bg-slate-900 border-l border-slate-800 h-full flex flex-col shadow-2xl animate-in slide-in-from-right duration-200">
        {/* Drawer Header */}
        <div className="p-6 border-b border-slate-800 bg-slate-900/90">
          <div className="flex items-start justify-between gap-4">
            <div className="space-y-1">
              <div className="flex items-center gap-2">
                <span className="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                  {currentStage?.name || deal.stage}
                </span>
                {deal.is_archived && (
                  <span className="px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                    Archived
                  </span>
                )}
                <span className="text-xs text-slate-400 font-mono">
                  Probability: {deal.probability || currentStage?.probability || 0}%
                </span>
              </div>
              <h2 className="text-xl font-bold text-slate-100">{deal.name}</h2>
            </div>

            <button
              onClick={onClose}
              className="p-2 rounded-xl text-slate-400 hover:text-slate-200 hover:bg-slate-800 transition-colors"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          {/* Value and Weighted Forecast Bar */}
          <div className="mt-4 grid grid-cols-3 gap-3 p-3 bg-slate-800/60 rounded-xl border border-slate-700/60">
            <div>
              <p className="text-[10px] uppercase font-semibold text-slate-400">Deal Value</p>
              <p className="text-base font-bold font-mono text-emerald-400">
                ${Number(deal.value || 0).toLocaleString()} {deal.currency || 'USD'}
              </p>
            </div>
            <div>
              <p className="text-[10px] uppercase font-semibold text-slate-400">Weighted Forecast</p>
              <p className="text-base font-bold font-mono text-purple-300">
                ${Number(deal.weighted_value || (deal.value * ((deal.probability || 0) / 100))).toFixed(0)}
              </p>
            </div>
            <div>
              <p className="text-[10px] uppercase font-semibold text-slate-400">Expected Close</p>
              <p className="text-sm font-semibold text-slate-200">
                {deal.expected_close_date || 'Not set'}
              </p>
            </div>
          </div>

          {/* Quick Stage Progression */}
          <div className="mt-4 flex items-center justify-between gap-2 overflow-x-auto pb-1">
            <span className="text-xs text-slate-400 shrink-0">Stage:</span>
            <div className="flex items-center gap-1.5 flex-1">
              {pipelineStages.map((stg) => {
                const isActive = stg.id === deal.pipeline_stage_id;
                return (
                  <button
                    key={stg.id}
                    onClick={() => handleStageSelect(stg.id)}
                    className={`px-2.5 py-1 rounded-lg text-xs font-medium transition-all shrink-0 ${
                      isActive
                        ? 'bg-indigo-600 text-white font-bold shadow'
                        : 'bg-slate-800 text-slate-300 hover:bg-slate-700 hover:text-white'
                    }`}
                  >
                    {stg.name}
                  </button>
                );
              })}
            </div>

            <button
              onClick={handleToggleArchive}
              className="p-1.5 rounded-lg text-slate-400 hover:text-slate-200 hover:bg-slate-800"
              title={deal.is_archived ? 'Restore Deal' : 'Archive Deal'}
            >
              <Archive className="w-4 h-4" />
            </button>
          </div>
        </div>

        {/* Tab Navigation */}
        <div className="flex border-b border-slate-800 px-6 gap-6 bg-slate-900/60">
          <button
            onClick={() => setActiveTab('overview')}
            className={`py-3 text-xs font-semibold border-b-2 transition-colors flex items-center gap-1.5 ${
              activeTab === 'overview'
                ? 'border-indigo-500 text-indigo-400'
                : 'border-transparent text-slate-400 hover:text-slate-200'
            }`}
          >
            <FileText className="w-4 h-4" />
            Overview
          </button>
          <button
            onClick={() => setActiveTab('products')}
            className={`py-3 text-xs font-semibold border-b-2 transition-colors flex items-center gap-1.5 ${
              activeTab === 'products'
                ? 'border-indigo-500 text-indigo-400'
                : 'border-transparent text-slate-400 hover:text-slate-200'
            }`}
          >
            <Package className="w-4 h-4" />
            Products ({deal.products?.length || 0})
          </button>
          <button
            onClick={() => setActiveTab('history')}
            className={`py-3 text-xs font-semibold border-b-2 transition-colors flex items-center gap-1.5 ${
              activeTab === 'history'
                ? 'border-indigo-500 text-indigo-400'
                : 'border-transparent text-slate-400 hover:text-slate-200'
            }`}
          >
            <History className="w-4 h-4" />
            Stage History ({deal.stage_histories?.length || 0})
          </button>
          <button
            onClick={() => setActiveTab('activities')}
            className={`py-3 text-xs font-semibold border-b-2 transition-colors flex items-center gap-1.5 ${
              activeTab === 'activities'
                ? 'border-indigo-500 text-indigo-400'
                : 'border-transparent text-slate-400 hover:text-slate-200'
            }`}
          >
            <Activity className="w-4 h-4" />
            Activities & Tasks ({activities.length + tasks.length})
          </button>
        </div>

        {/* Tab Content Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          {/* OVERVIEW TAB */}
          {activeTab === 'overview' && (
            <div className="space-y-6">
              <div className="grid grid-cols-2 gap-4">
                <div className="p-4 rounded-xl bg-slate-800/40 border border-slate-700/60 space-y-1">
                  <p className="text-[11px] text-slate-400 flex items-center gap-1.5">
                    <Building2 className="w-3.5 h-3.5 text-slate-500" />
                    Linked Company
                  </p>
                  <p className="text-sm font-semibold text-slate-200">
                    {deal.company?.name || 'No company linked'}
                  </p>
                  {deal.company?.email && (
                    <p className="text-xs text-slate-400">{deal.company.email}</p>
                  )}
                </div>

                <div className="p-4 rounded-xl bg-slate-800/40 border border-slate-700/60 space-y-1">
                  <p className="text-[11px] text-slate-400 flex items-center gap-1.5">
                    <User className="w-3.5 h-3.5 text-slate-500" />
                    Primary Contact
                  </p>
                  <p className="text-sm font-semibold text-slate-200">
                    {deal.contact?.name || 'No contact linked'}
                  </p>
                  {deal.contact?.email && (
                    <p className="text-xs text-slate-400">{deal.contact.email}</p>
                  )}
                </div>
              </div>

              {/* Attribution & Campaign Metadata */}
              <div className="p-4 rounded-xl bg-slate-800/40 border border-slate-700/60 space-y-3">
                <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                  Lead & Campaign Attribution
                </h4>
                <div className="grid grid-cols-2 gap-3 text-xs">
                  <div>
                    <span className="text-slate-500">Source:</span>{' '}
                    <span className="text-slate-200 font-medium">{deal.source || 'Organic / Direct'}</span>
                  </div>
                  <div>
                    <span className="text-slate-500">Campaign:</span>{' '}
                    <span className="text-slate-200 font-medium">{deal.campaign || 'None'}</span>
                  </div>
                  <div>
                    <span className="text-slate-500">UTM Source:</span>{' '}
                    <span className="text-slate-200 font-medium">{deal.utm_source || 'N/A'}</span>
                  </div>
                  <div>
                    <span className="text-slate-500">UTM Medium:</span>{' '}
                    <span className="text-slate-200 font-medium">{deal.utm_medium || 'N/A'}</span>
                  </div>
                </div>
              </div>

              {/* Competitor & Lost Reason (if applicable) */}
              {(deal.competitor || deal.lost_reason) && (
                <div className="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 space-y-2">
                  <h4 className="text-xs font-bold text-rose-400 flex items-center gap-1.5">
                    <XCircle className="w-4 h-4" />
                    Competition & Lost Intelligence
                  </h4>
                  {deal.lost_reason && (
                    <p className="text-xs text-slate-300">
                      <span className="text-slate-400 font-medium">Reason:</span> {deal.lost_reason}
                    </p>
                  )}
                  {deal.competitor && (
                    <p className="text-xs text-slate-300">
                      <span className="text-slate-400 font-medium">Winning Competitor:</span> {deal.competitor}
                    </p>
                  )}
                </div>
              )}

              {/* Deal Owner & Collaborators */}
              <div className="p-4 rounded-xl bg-slate-800/40 border border-slate-700/60 space-y-3">
                <div className="flex items-center justify-between">
                  <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                    Deal Ownership & Team
                  </h4>
                  <button
                    onClick={() => setShowTransferModal(!showTransferModal)}
                    className="text-xs text-indigo-400 hover:text-indigo-300 font-medium underline"
                  >
                    {showTransferModal ? 'Cancel' : 'Reassign Owner'}
                  </button>
                </div>

                <div className="flex items-center gap-3">
                  <div className="w-8 h-8 rounded-full bg-indigo-500/20 border border-indigo-500/40 flex items-center justify-center font-bold text-xs text-indigo-300">
                    {(deal.assigned_user?.name || 'U').charAt(0).toUpperCase()}
                  </div>
                  <div className="flex-1">
                    <p className="text-xs font-semibold text-slate-200">
                      {deal.assigned_user?.name || 'Unassigned'}
                    </p>
                    <p className="text-[11px] text-slate-400">{deal.assigned_user?.email || 'No email'}</p>
                  </div>
                  {deal.sales_team && (
                    <span className="px-2 py-0.5 rounded text-[10px] bg-slate-700 text-slate-300">
                      Team: {deal.sales_team}
                    </span>
                  )}
                </div>

                {/* Transfer Modal / Inline Form */}
                {showTransferModal && (
                  <div className="mt-3 p-3 rounded-lg bg-slate-900 border border-indigo-500/30 space-y-2">
                    <p className="text-xs font-semibold text-indigo-300">Transfer Deal Ownership</p>
                    <div className="space-y-1.5">
                      <label className="text-[11px] text-slate-400">Target User ID</label>
                      <input
                        type="number"
                        value={newUserId}
                        onChange={(e) => setNewUserId(Number(e.target.value))}
                        className="w-full bg-slate-800 border border-slate-700 rounded px-2.5 py-1 text-xs text-slate-200"
                      />
                    </div>
                    <div className="space-y-1.5">
                      <label className="text-[11px] text-slate-400">Audit Reason</label>
                      <input
                        type="text"
                        placeholder="e.g. Account rebalancing or territory shift"
                        value={transferReason}
                        onChange={(e) => setTransferReason(e.target.value)}
                        className="w-full bg-slate-800 border border-slate-700 rounded px-2.5 py-1 text-xs text-slate-200"
                      />
                    </div>
                    <button
                      onClick={() => {
                        transferOwnershipMutation.mutate(
                          { dealId: deal.id, data: { user_id: newUserId, reason: transferReason } },
                          {
                            onSuccess: () => {
                              setShowTransferModal(false);
                              onUpdate?.();
                            },
                          }
                        );
                      }}
                      disabled={transferOwnershipMutation.isPending}
                      className="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-xs font-medium"
                    >
                      {transferOwnershipMutation.isPending ? 'Transferring...' : 'Confirm Reassignment'}
                    </button>
                  </div>
                )}
              </div>

              {/* Lifecycle Actions: Reopen / Convert to Project */}
              <div className="p-4 rounded-xl bg-slate-800/40 border border-slate-700/60 space-y-3">
                <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                  Lifecycle Operations & Delivery
                </h4>

                <div className="flex flex-wrap gap-2">
                  {/* Convert to Project (if won) */}
                  {deal.won_at ? (
                    deal.project_id ? (
                      <span className="px-3 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs font-medium flex items-center gap-1.5">
                        <CheckCircle2 className="w-3.5 h-3.5" />
                        Converted to Delivery Project #{deal.project_id}
                      </span>
                    ) : (
                      <button
                        onClick={() => setShowConvertModal(true)}
                        className="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-sm"
                      >
                        <Package className="w-3.5 h-3.5" />
                        Convert to Onboarding Project
                      </button>
                    )
                  ) : null}

                  {/* Reopen Deal (if closed won or lost) */}
                  {(deal.won_at || deal.lost_at) && (
                    <button
                      onClick={() => setShowReopenModal(true)}
                      className="px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs font-medium flex items-center gap-1.5 transition-colors"
                    >
                      <History className="w-3.5 h-3.5" />
                      Reopen Closed Deal
                    </button>
                  )}
                </div>

                {deal.reopened_at && (
                  <p className="text-[11px] text-amber-400/90 bg-amber-500/10 p-2 rounded border border-amber-500/20">
                    Deal was reopened on {new Date(deal.reopened_at).toLocaleDateString()}
                  </p>
                )}

                {/* Reopen Modal */}
                {showReopenModal && (
                  <div className="p-3 rounded-lg bg-slate-900 border border-slate-700 space-y-2">
                    <p className="text-xs font-semibold text-slate-200">Reopen Deal to Pipeline Stage</p>
                    <select
                      value={reopenStageId}
                      onChange={(e) => setReopenStageId(Number(e.target.value))}
                      className="w-full bg-slate-800 border border-slate-700 rounded px-2.5 py-1 text-xs text-slate-200"
                    >
                      {pipelineStages.filter((s) => !s.is_won && !s.is_lost).map((stg) => (
                        <option key={stg.id} value={stg.id}>
                          {stg.name} ({stg.probability}% prob)
                        </option>
                      ))}
                    </select>
                    <input
                      type="text"
                      placeholder="Reason for reopening (e.g. Client requested re-quote)"
                      value={reopenReason}
                      onChange={(e) => setReopenReason(e.target.value)}
                      className="w-full bg-slate-800 border border-slate-700 rounded px-2.5 py-1 text-xs text-slate-200"
                    />
                    <div className="flex gap-2">
                      <button
                        onClick={() => {
                          reopenDealMutation.mutate(
                            { dealId: deal.id, data: { stage_id: reopenStageId, reason: reopenReason } },
                            {
                              onSuccess: () => {
                                setShowReopenModal(false);
                                onUpdate?.();
                              },
                            }
                          );
                        }}
                        disabled={reopenDealMutation.isPending}
                        className="px-3 py-1 bg-amber-600 hover:bg-amber-500 text-white rounded text-xs font-medium"
                      >
                        {reopenDealMutation.isPending ? 'Reopening...' : 'Confirm Reopen'}
                      </button>
                      <button
                        onClick={() => setShowReopenModal(false)}
                        className="px-2 py-1 bg-slate-800 text-slate-400 rounded text-xs"
                      >
                        Cancel
                      </button>
                    </div>
                  </div>
                )}

                {/* Convert to Project Modal */}
                {showConvertModal && (
                  <div className="p-3 rounded-lg bg-slate-900 border border-emerald-500/30 space-y-2">
                    <p className="text-xs font-semibold text-emerald-300">Convert Deal into Client Project</p>
                    <input
                      type="text"
                      value={projectName}
                      onChange={(e) => setProjectName(e.target.value)}
                      className="w-full bg-slate-800 border border-slate-700 rounded px-2.5 py-1 text-xs text-slate-200"
                    />
                    <input
                      type="text"
                      value={projectDescription}
                      onChange={(e) => setProjectDescription(e.target.value)}
                      className="w-full bg-slate-800 border border-slate-700 rounded px-2.5 py-1 text-xs text-slate-200"
                    />
                    <div className="flex gap-2">
                      <button
                        onClick={() => {
                          convertToProjectMutation.mutate(
                            { dealId: deal.id, data: { name: projectName, description: projectDescription } },
                            {
                              onSuccess: () => {
                                setShowConvertModal(false);
                                onUpdate?.();
                              },
                            }
                          );
                        }}
                        disabled={convertToProjectMutation.isPending}
                        className="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-xs font-medium"
                      >
                        {convertToProjectMutation.isPending ? 'Converting...' : 'Launch Delivery Project'}
                      </button>
                      <button
                        onClick={() => setShowConvertModal(false)}
                        className="px-2 py-1 bg-slate-800 text-slate-400 rounded text-xs"
                      >
                        Cancel
                      </button>
                    </div>
                  </div>
                )}
              </div>
            </div>
          )}

          {/* PRODUCTS TAB */}
          {activeTab === 'products' && (
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                  Associated Products / Services
                </h4>
                <button
                  onClick={() => setShowAddProduct(!showAddProduct)}
                  className="flex items-center gap-1.5 px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold transition-colors"
                >
                  <Plus className="w-3.5 h-3.5" />
                  Add Line Item
                </button>
              </div>

              {/* Add Product Form */}
              {showAddProduct && (
                <form
                  onSubmit={handleAddProduct}
                  className="p-4 rounded-xl bg-slate-800/80 border border-slate-700 space-y-3"
                >
                  <h5 className="text-xs font-bold text-slate-200">New Product Line Item</h5>
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="block text-[11px] text-slate-400 mb-1">Item Name</label>
                      <input
                        type="text"
                        placeholder="e.g. Enterprise License..."
                        value={productName}
                        onChange={(e) => setProductName(e.target.value)}
                        required
                        className="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                      />
                    </div>
                    <div>
                      <label className="block text-[11px] text-slate-400 mb-1">Unit Price ($)</label>
                      <input
                        type="number"
                        step="0.01"
                        value={productPrice}
                        onChange={(e) => setProductPrice(Number(e.target.value))}
                        required
                        className="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500 font-mono"
                      />
                    </div>
                    <div>
                      <label className="block text-[11px] text-slate-400 mb-1">Quantity</label>
                      <input
                        type="number"
                        min="1"
                        value={productQty}
                        onChange={(e) => setProductQty(Number(e.target.value))}
                        className="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500 font-mono"
                      />
                    </div>
                    <div>
                      <label className="block text-[11px] text-slate-400 mb-1">Discount ($)</label>
                      <input
                        type="number"
                        step="0.01"
                        value={productDiscount}
                        onChange={(e) => setProductDiscount(Number(e.target.value))}
                        className="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500 font-mono"
                      />
                    </div>
                  </div>
                  <div className="flex justify-end gap-2 pt-2">
                    <button
                      type="button"
                      onClick={() => setShowAddProduct(false)}
                      className="px-3 py-1 bg-slate-700 text-slate-300 rounded-lg text-xs"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      disabled={addProductMutation.isPending}
                      className="px-3 py-1 bg-indigo-600 text-white rounded-lg text-xs font-semibold"
                    >
                      {addProductMutation.isPending ? 'Adding...' : 'Save Product'}
                    </button>
                  </div>
                </form>
              )}

              {/* Product List */}
              <div className="space-y-2">
                {(!deal.products || deal.products.length === 0) ? (
                  <div className="py-12 text-center text-xs text-slate-500">
                    No products added to this deal yet.
                  </div>
                ) : (
                  deal.products.map((p) => (
                    <div
                      key={p.id}
                      className="p-3 rounded-xl bg-slate-800/50 border border-slate-700/60 flex items-center justify-between"
                    >
                      <div>
                        <p className="text-xs font-semibold text-slate-200">{p.name}</p>
                        <p className="text-[11px] text-slate-400 font-mono">
                          {p.quantity} &times; ${Number(p.unit_price).toLocaleString()}
                          {p.discount > 0 && ` (Discount: -$${Number(p.discount).toLocaleString()})`}
                        </p>
                      </div>
                      <div className="flex items-center gap-3">
                        <span className="text-xs font-bold font-mono text-emerald-400">
                          ${Number(p.total_price).toLocaleString()}
                        </span>
                        <button
                          onClick={() => handleRemoveProduct(p.id)}
                          className="p-1 text-slate-400 hover:text-rose-400 transition-colors"
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </button>
                      </div>
                    </div>
                  ))
                )}
              </div>
            </div>
          )}

          {/* STAGE HISTORY TAB */}
          {activeTab === 'history' && (
            <div className="space-y-4">
              <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                Pipeline Stage Progression History
              </h4>

              {(!deal.stage_histories || deal.stage_histories.length === 0) ? (
                <div className="py-12 text-center text-xs text-slate-500">
                  No historical stage transitions recorded yet.
                </div>
              ) : (
                <div className="space-y-3 relative before:absolute before:left-3 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-800">
                  {deal.stage_histories.map((hist) => (
                    <div key={hist.id} className="flex items-start gap-4 pl-2 relative">
                      <div className="w-2.5 h-2.5 rounded-full bg-indigo-500 ring-4 ring-slate-900 mt-1.5 shrink-0" />
                      <div className="flex-1 p-3 rounded-xl bg-slate-800/40 border border-slate-700/60 space-y-1">
                        <div className="flex items-center justify-between text-xs">
                          <span className="font-bold text-slate-200">
                            Moved to: <span className="text-indigo-400">{hist.stage}</span>
                          </span>
                          <span className="text-[10px] text-slate-500">
                            {new Date(hist.created_at).toLocaleString()}
                          </span>
                        </div>
                        {hist.duration_days !== null && hist.duration_days !== undefined && (
                          <p className="text-[11px] text-slate-400">
                            Duration in previous stage: <span className="font-mono text-slate-300">{hist.duration_days} days</span>
                          </p>
                        )}
                        {hist.notes && (
                          <p className="text-xs text-slate-300 italic">{hist.notes}</p>
                        )}
                        {hist.changed_user && (
                          <p className="text-[10px] text-slate-500">
                            By {hist.changed_user.name}
                          </p>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {/* ACTIVITIES & TASKS TAB */}
          {activeTab === 'activities' && (
            <div className="space-y-6">
              {/* Linked Tasks */}
              <div className="space-y-3">
                <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                  Associated Tasks ({tasks.length})
                </h4>
                {tasks.length === 0 ? (
                  <div className="py-6 text-center text-xs text-slate-500 bg-slate-800/20 rounded-xl">
                    No tasks linked to this deal.
                  </div>
                ) : (
                  <div className="space-y-2">
                    {tasks.map((t) => (
                      <div
                        key={t.id}
                        className="p-3 rounded-xl bg-slate-800/50 border border-slate-700/60 flex items-center justify-between"
                      >
                        <div className="space-y-0.5">
                          <div className="flex items-center gap-2">
                            <span
                              className={`w-2 h-2 rounded-full ${
                                t.status === 'done' ? 'bg-emerald-400' : 'bg-amber-400'
                              }`}
                            />
                            <p className="text-xs font-semibold text-slate-200">{t.title}</p>
                          </div>
                          {t.due_date && (
                            <p className="text-[10px] text-slate-400">
                              Due: {t.due_date}
                            </p>
                          )}
                        </div>
                        <span className="px-2 py-0.5 rounded text-[10px] font-semibold uppercase bg-slate-800 text-slate-300">
                          {t.priority}
                        </span>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* Linked Activities */}
              <div className="space-y-3">
                <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                  Sales Activities ({activities.length})
                </h4>
                {activities.length === 0 ? (
                  <div className="py-6 text-center text-xs text-slate-500 bg-slate-800/20 rounded-xl">
                    No activities recorded for this deal.
                  </div>
                ) : (
                  <div className="space-y-2">
                    {activities.map((act) => (
                      <div
                        key={act.id}
                        className="p-3 rounded-xl bg-slate-800/50 border border-slate-700/60 space-y-1"
                      >
                        <div className="flex items-center justify-between text-xs">
                          <span className="font-semibold text-slate-200">{act.title}</span>
                          <span className="text-[10px] text-slate-500">
                            {new Date(act.created_at).toLocaleDateString()}
                          </span>
                        </div>
                        {act.description && (
                          <p className="text-xs text-slate-400">{act.description}</p>
                        )}
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
