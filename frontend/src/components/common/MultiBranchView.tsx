import React, { useState } from 'react';
import {
  useBranches,
  useBranch,
  useCreateBranch,
  useUpdateBranch,
  useSetBranchStatus,
  useTransferBranchRecord,
  useBranchTransferHistory,
  useBranchDashboard,
  useConsolidatedBranchAnalytics,
  useUpdateBranchSettings,
  useSaveBranchSalesTarget,
} from '../../hooks/useMultiBranch';
import { BranchItem } from '../../api/types';

export const MultiBranchView: React.FC = () => {
  const [activeTab, setActiveTab] = useState<'directory' | 'dashboard' | 'consolidated' | 'transfers' | 'settings'>('directory');
  const [search, setSearch] = useState('');
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(null);

  // Queries
  const { data: branchesRes, isLoading: loadingBranches } = useBranches({ search });
  const branches = branchesRes?.data ?? [];

  // Auto select first branch if none selected
  React.useEffect(() => {
    if (!selectedBranchId && branches.length > 0) {
      setSelectedBranchId(branches[0].id);
    }
  }, [branches, selectedBranchId]);

  const { data: branchDetailRes } = useBranch(selectedBranchId ?? undefined);
  const selectedBranch = branchDetailRes?.data;

  const { data: branchDashboardRes, isLoading: loadingDashboard } = useBranchDashboard(selectedBranchId ?? undefined);
  const branchMetrics = branchDashboardRes?.data;

  const { data: consolidatedRes, isLoading: loadingConsolidated } = useConsolidatedBranchAnalytics();
  const consolidated = consolidatedRes?.data;

  const { data: transferHistoryRes, isLoading: loadingTransfers } = useBranchTransferHistory();
  const transferHistory = transferHistoryRes?.data ?? [];

  // Mutations
  const createBranchMutation = useCreateBranch();
  const updateBranchMutation = useUpdateBranch();
  const setStatusMutation = useSetBranchStatus();
  const transferMutation = useTransferBranchRecord();
  const updateSettingsMutation = useUpdateBranchSettings();
  const saveTargetMutation = useSaveBranchSalesTarget();

  // Create Branch Form State
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [newBranch, setNewBranch] = useState({
    name: '',
    code: '',
    city: '',
    state: '',
    email: '',
    phone: '',
    address_line1: '',
    pincode: '',
  });

  // Transfer Form State
  const [transferForm, setTransferForm] = useState({
    transferable_type: 'lead',
    transferable_id: '',
    to_branch_id: '',
    reason: '',
  });
  const [transferSuccessMsg, setTransferSuccessMsg] = useState<string | null>(null);

  // Handle Branch Creation
  const handleCreateBranch = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newBranch.name.trim()) return;
    await createBranchMutation.mutateAsync(newBranch);
    setShowCreateModal(false);
    setNewBranch({
      name: '',
      code: '',
      city: '',
      state: '',
      email: '',
      phone: '',
      address_line1: '',
      pincode: '',
    });
  };

  // Handle Record Transfer
  const handleExecuteTransfer = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!transferForm.transferable_id || !transferForm.to_branch_id) return;

    await transferMutation.mutateAsync({
      transferable_type: transferForm.transferable_type,
      transferable_id: parseInt(transferForm.transferable_id, 10),
      to_branch_id: parseInt(transferForm.to_branch_id, 10),
      reason: transferForm.reason || 'Manual branch reassignment',
    });

    setTransferSuccessMsg(`Successfully transferred ${transferForm.transferable_type.toUpperCase()} #${transferForm.transferable_id} to new branch!`);
    setTransferForm({
      transferable_type: 'lead',
      transferable_id: '',
      to_branch_id: '',
      reason: '',
    });
    setTimeout(() => setTransferSuccessMsg(null), 4000);
  };

  const formatCurrency = (val: number = 0) =>
    new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(val);

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      {/* Header Banner */}
      <div className="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 rounded-2xl p-6 text-white shadow-xl flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <div className="inline-flex items-center gap-2 px-3 py-1 bg-indigo-500/20 border border-indigo-400/30 rounded-full text-xs font-medium text-indigo-300 mb-2">
            <span>🏢 Multi-Branch & Multi-Location Enterprise Engine</span>
          </div>
          <h1 className="text-2xl font-bold tracking-tight">Organization Branch & Regional Management</h1>
          <p className="text-slate-400 text-sm mt-1">
            Centrally oversee regional offices, sales outlets, branch managers, workforce allocation, and cross-branch record transfers.
          </p>
        </div>
        <div className="flex gap-3">
          <button
            onClick={() => setShowCreateModal(true)}
            className="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-semibold shadow-lg hover:shadow-indigo-500/25 transition-all flex items-center gap-2"
          >
            <span>+</span> Add New Branch
          </button>
        </div>
      </div>

      {/* Top Level KPIs */}
      {consolidated?.summary && (
        <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
          <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">Total Branches</span>
            <div className="text-2xl font-bold text-slate-800 dark:text-white mt-1">
              {consolidated.summary.active_branches} <span className="text-xs text-slate-400">/ {consolidated.summary.total_branches}</span>
            </div>
            <span className="text-xs text-emerald-600 font-medium">Active Locations</span>
          </div>
          <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">Total Invoiced Sales</span>
            <div className="text-xl font-bold text-slate-800 dark:text-white mt-1">
              {formatCurrency(consolidated.summary.total_sales)}
            </div>
            <span className="text-xs text-indigo-500 font-medium">All Branches</span>
          </div>
          <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">Total Collections</span>
            <div className="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
              {formatCurrency(consolidated.summary.total_collections)}
            </div>
            <span className="text-xs text-slate-500">{consolidated.summary.collection_efficiency_percentage}% Efficiency</span>
          </div>
          <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">Net Profit</span>
            <div className="text-xl font-bold text-blue-600 dark:text-blue-400 mt-1">
              {formatCurrency(consolidated.summary.net_profit)}
            </div>
            <span className="text-xs text-slate-500">Less ₹{(consolidated.summary.total_expenses / 1000).toFixed(0)}k Exp</span>
          </div>
          <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">Workforce & Leads</span>
            <div className="text-xl font-bold text-slate-800 dark:text-white mt-1">
              {consolidated.summary.total_employees} <span className="text-xs text-slate-400">Staff</span>
            </div>
            <span className="text-xs text-amber-500 font-medium">{consolidated.summary.total_leads} Active Leads</span>
          </div>
        </div>
      )}

      {/* Navigation Tabs */}
      <div className="flex border-b border-slate-200 dark:border-slate-700 space-x-6 text-sm font-medium">
        <button
          onClick={() => setActiveTab('directory')}
          className={`pb-3 border-b-2 transition-colors ${
            activeTab === 'directory'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400'
          }`}
        >
          Branches Directory ({branches.length})
        </button>
        <button
          onClick={() => setActiveTab('dashboard')}
          className={`pb-3 border-b-2 transition-colors ${
            activeTab === 'dashboard'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400'
          }`}
        >
          Branch Manager Dashboard
        </button>
        <button
          onClick={() => setActiveTab('consolidated')}
          className={`pb-3 border-b-2 transition-colors ${
            activeTab === 'consolidated'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400'
          }`}
        >
          Central Owner Comparison
        </button>
        <button
          onClick={() => setActiveTab('transfers')}
          className={`pb-3 border-b-2 transition-colors ${
            activeTab === 'transfers'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400'
          }`}
        >
          Branch Transfers & Audit Log
        </button>
        <button
          onClick={() => setActiveTab('settings')}
          className={`pb-3 border-b-2 transition-colors ${
            activeTab === 'settings'
              ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400'
          }`}
        >
          Branch Settings & Operations
        </button>
      </div>

      {/* TAB 1: Branches Directory */}
      {activeTab === 'directory' && (
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <input
              type="text"
              placeholder="Search branches by name, city, state or code..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-xl w-full max-w-md bg-white dark:bg-slate-800 text-sm"
            />
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            {branches.map((b) => (
              <div
                key={b.id}
                className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm hover:shadow-md transition-shadow relative flex flex-col justify-between"
              >
                <div>
                  <div className="flex items-start justify-between">
                    <div>
                      <span className="text-xs font-mono px-2 py-0.5 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded font-semibold">
                        {b.code}
                      </span>
                      <h3 className="text-lg font-bold text-slate-900 dark:text-white mt-1.5">{b.name}</h3>
                      <p className="text-xs text-slate-500 dark:text-slate-400">
                        {b.city ? `${b.city}, ${b.state || ''}` : 'Location unconfigured'}
                      </p>
                    </div>
                    <span
                      className={`px-2.5 py-1 text-xs font-medium rounded-full ${
                        b.is_active
                          ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800'
                          : 'bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800'
                      }`}
                    >
                      {b.is_active ? 'Active' : 'Inactive'}
                    </span>
                  </div>

                  <div className="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700 text-xs text-slate-600 dark:text-slate-300 space-y-1.5">
                    <div className="flex justify-between">
                      <span className="text-slate-400">Manager:</span>
                      <span className="font-semibold">{b.manager?.name || 'Unassigned'}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-slate-400">Phone / Email:</span>
                      <span>{b.phone || b.email || 'N/A'}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-slate-400">Timezone:</span>
                      <span>{b.timezone || 'Asia/Kolkata'}</span>
                    </div>
                  </div>

                  {/* Resource Counts */}
                  <div className="grid grid-cols-4 gap-2 mt-4 pt-3 border-t border-slate-100 dark:border-slate-700 text-center">
                    <div className="bg-slate-50 dark:bg-slate-900/50 p-1.5 rounded-lg">
                      <div className="text-sm font-bold text-slate-800 dark:text-white">{b.employees_count || b.users_count || 0}</div>
                      <div className="text-[10px] text-slate-400">Staff</div>
                    </div>
                    <div className="bg-slate-50 dark:bg-slate-900/50 p-1.5 rounded-lg">
                      <div className="text-sm font-bold text-indigo-600 dark:text-indigo-400">{b.leads_count || 0}</div>
                      <div className="text-[10px] text-slate-400">Leads</div>
                    </div>
                    <div className="bg-slate-50 dark:bg-slate-900/50 p-1.5 rounded-lg">
                      <div className="text-sm font-bold text-emerald-600 dark:text-emerald-400">{b.deals_count || 0}</div>
                      <div className="text-[10px] text-slate-400">Deals</div>
                    </div>
                    <div className="bg-slate-50 dark:bg-slate-900/50 p-1.5 rounded-lg">
                      <div className="text-sm font-bold text-amber-600 dark:text-amber-400">{b.support_tickets_count || 0}</div>
                      <div className="text-[10px] text-slate-400">Tickets</div>
                    </div>
                  </div>
                </div>

                <div className="mt-5 pt-3 border-t border-slate-100 dark:border-slate-700 flex gap-2">
                  <button
                    onClick={() => {
                      setSelectedBranchId(b.id);
                      setActiveTab('dashboard');
                    }}
                    className="flex-1 py-1.5 px-3 bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 rounded-lg text-xs font-semibold hover:bg-indigo-100 dark:hover:bg-indigo-900/40 transition-colors"
                  >
                    View Dashboard
                  </button>
                  <button
                    onClick={() => {
                      setStatusMutation.mutate({ id: b.id, is_active: !b.is_active });
                    }}
                    className={`py-1.5 px-3 rounded-lg text-xs font-semibold transition-colors ${
                      b.is_active
                        ? 'bg-rose-50 text-rose-600 hover:bg-rose-100'
                        : 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100'
                    }`}
                  >
                    {b.is_active ? 'Deactivate' : 'Activate'}
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* TAB 2: Branch Manager Dashboard */}
      {activeTab === 'dashboard' && (
        <div className="space-y-6">
          <div className="flex items-center justify-between bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700">
            <div className="flex items-center gap-3">
              <span className="text-sm font-semibold text-slate-700 dark:text-slate-300">Select Branch:</span>
              <select
                value={selectedBranchId ?? ''}
                onChange={(e) => setSelectedBranchId(Number(e.target.value))}
                className="px-3 py-1.5 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white font-medium"
              >
                {branches.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name} ({b.code}) - {b.city || 'HQ'}
                  </option>
                ))}
              </select>
            </div>
            {selectedBranch && (
              <div className="text-xs text-slate-500">
                Manager: <span className="font-bold text-slate-800 dark:text-white">{selectedBranch.manager?.name || 'Unassigned'}</span> | Timezone: {selectedBranch.timezone}
              </div>
            )}
          </div>

          {branchMetrics && (
            <div className="space-y-6">
              {/* Branch Financial Summary */}
              <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div className="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700">
                  <span className="text-xs text-slate-500">Invoiced Sales</span>
                  <div className="text-2xl font-bold text-slate-900 dark:text-white mt-1">
                    {formatCurrency(branchMetrics.sales.invoiced_revenue)}
                  </div>
                  <span className="text-xs text-slate-400">Branch Revenue Demands</span>
                </div>
                <div className="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700">
                  <span className="text-xs text-slate-500">Collected Cash</span>
                  <div className="text-2xl font-bold text-emerald-600 mt-1">
                    {formatCurrency(branchMetrics.sales.collected_revenue)}
                  </div>
                  <span className="text-xs text-slate-400">Deposits Received</span>
                </div>
                <div className="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700">
                  <span className="text-xs text-slate-500">Pending Receivables</span>
                  <div className="text-2xl font-bold text-amber-600 mt-1">
                    {formatCurrency(branchMetrics.sales.pending_receivables)}
                  </div>
                  <span className="text-xs text-slate-400">Outstanding Invoices</span>
                </div>
                <div className="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700">
                  <span className="text-xs text-slate-500">Net Profit</span>
                  <div className="text-2xl font-bold text-blue-600 mt-1">
                    {formatCurrency(branchMetrics.sales.net_profit)}
                  </div>
                  <span className="text-xs text-slate-400">Less ₹{branchMetrics.sales.expenses.toLocaleString()} Exp</span>
                </div>
              </div>

              {/* Branch Operational Grids */}
              <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                {/* Leads & Deals */}
                <div className="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 space-y-4">
                  <h4 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                    Leads & Sales Pipeline
                  </h4>
                  <div className="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-700">
                    <span className="text-sm text-slate-500">Total Leads</span>
                    <span className="text-sm font-bold">{branchMetrics.leads.total}</span>
                  </div>
                  <div className="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-700">
                    <span className="text-sm text-slate-500">Conversion Rate</span>
                    <span className="text-sm font-bold text-emerald-600">{branchMetrics.leads.conversion_rate_percentage}%</span>
                  </div>
                  <div className="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-700">
                    <span className="text-sm text-slate-500">Open Deals Pipeline</span>
                    <span className="text-sm font-bold">{formatCurrency(branchMetrics.deals.pipeline_value)}</span>
                  </div>
                  <div className="flex justify-between items-center py-2">
                    <span className="text-sm text-slate-500">Won Deals Value</span>
                    <span className="text-sm font-bold text-indigo-600">{formatCurrency(branchMetrics.deals.won_value)}</span>
                  </div>
                </div>

                {/* Workforce & Support */}
                <div className="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 space-y-4">
                  <h4 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                    Workforce & Support
                  </h4>
                  <div className="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-700">
                    <span className="text-sm text-slate-500">Active Staff Headcount</span>
                    <span className="text-sm font-bold">{branchMetrics.employees.active_headcount} / {branchMetrics.employees.total_headcount}</span>
                  </div>
                  <div className="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-700">
                    <span className="text-sm text-slate-500">Open Support Tickets</span>
                    <span className="text-sm font-bold text-amber-600">{branchMetrics.support.open_tickets}</span>
                  </div>
                  <div className="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-700">
                    <span className="text-sm text-slate-500">SLA Breached Tickets</span>
                    <span className="text-sm font-bold text-rose-600">{branchMetrics.support.sla_breached}</span>
                  </div>
                  <div className="flex justify-between items-center py-2">
                    <span className="text-sm text-slate-500">Upcoming Appointments</span>
                    <span className="text-sm font-bold text-indigo-600">{branchMetrics.appointments.upcoming}</span>
                  </div>
                </div>

                {/* Target Progress */}
                <div className="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 space-y-4">
                  <h4 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                    Sales Target Progress
                  </h4>
                  <div className="pt-2">
                    <div className="flex justify-between text-sm mb-1.5">
                      <span className="text-slate-500">Target Achievement</span>
                      <span className="font-bold text-slate-800 dark:text-white">{branchMetrics.targets.progress_percentage}%</span>
                    </div>
                    <div className="w-full bg-slate-100 dark:bg-slate-700 h-3 rounded-full overflow-hidden">
                      <div
                        className="bg-emerald-500 h-full rounded-full transition-all"
                        style={{ width: `${Math.min(branchMetrics.targets.progress_percentage, 100)}%` }}
                      />
                    </div>
                    <div className="flex justify-between text-xs text-slate-400 mt-2">
                      <span>Achieved: {formatCurrency(branchMetrics.targets.achieved_amount)}</span>
                      <span>Target: {formatCurrency(branchMetrics.targets.target_amount)}</span>
                    </div>
                  </div>
                  <div className="pt-4 border-t border-slate-100 dark:border-slate-700 text-xs text-slate-500">
                    Active Projects: <span className="font-bold text-slate-800 dark:text-white">{branchMetrics.projects.active} active</span> ({branchMetrics.projects.completed} completed)
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {/* TAB 3: Central Owner Consolidated Analytics */}
      {activeTab === 'consolidated' && consolidated && (
        <div className="space-y-6">
          <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
            <div className="p-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center">
              <h3 className="font-bold text-slate-900 dark:text-white">Multi-Branch Performance Comparison</h3>
              <span className="text-xs text-slate-500">Consolidated across all active regional branches</span>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-sm text-left">
                <thead className="bg-slate-50 dark:bg-slate-900 text-xs text-slate-500 uppercase">
                  <tr>
                    <th className="py-3 px-4">Branch</th>
                    <th className="py-3 px-4">Manager</th>
                    <th className="py-3 px-4 text-right">Leads (Conv %)</th>
                    <th className="py-3 px-4 text-right">Invoiced Sales</th>
                    <th className="py-3 px-4 text-right">Collections</th>
                    <th className="py-3 px-4 text-right">Expenses</th>
                    <th className="py-3 px-4 text-right">Net Profit</th>
                    <th className="py-3 px-4 text-right">Target Achieved</th>
                    <th className="py-3 px-4 text-center">Staff</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                  {consolidated.branch_comparisons.map((bc) => (
                    <tr key={bc.branch_id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/50">
                      <td className="py-3 px-4 font-semibold text-slate-900 dark:text-white">
                        {bc.name}{' '}
                        <span className="text-xs font-mono text-slate-400">({bc.code})</span>
                        <div className="text-xs font-normal text-slate-400">{bc.city || 'HQ'}</div>
                      </td>
                      <td className="py-3 px-4 text-slate-600 dark:text-slate-300">{bc.manager_name}</td>
                      <td className="py-3 px-4 text-right font-medium">
                        {bc.leads_count}{' '}
                        <span className="text-xs text-emerald-600">({bc.conversion_rate}%)</span>
                      </td>
                      <td className="py-3 px-4 text-right font-bold text-slate-900 dark:text-white">
                        {formatCurrency(bc.sales_invoiced)}
                      </td>
                      <td className="py-3 px-4 text-right font-bold text-emerald-600">
                        {formatCurrency(bc.collections)}
                      </td>
                      <td className="py-3 px-4 text-right text-rose-600">
                        {formatCurrency(bc.expenses)}
                      </td>
                      <td className="py-3 px-4 text-right font-bold text-blue-600">
                        {formatCurrency(bc.net_profit)}
                      </td>
                      <td className="py-3 px-4 text-right">
                        <span className="inline-block px-2 py-0.5 rounded text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                          {bc.target_achievement_percentage}%
                        </span>
                      </td>
                      <td className="py-3 px-4 text-center text-slate-600 dark:text-slate-300">
                        {bc.employees_count}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 4: Record Transfers */}
      {activeTab === 'transfers' && (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Transfer Execution Form */}
          <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 h-fit space-y-4">
            <h3 className="font-bold text-slate-900 dark:text-white">Initiate Cross-Branch Transfer</h3>
            <p className="text-xs text-slate-500">
              Reassign leads, staff, projects, tickets, or appointments between branches while maintaining strict audit trails.
            </p>

            {transferSuccessMsg && (
              <div className="p-3 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 rounded-xl text-xs font-medium border border-emerald-200 dark:border-emerald-800">
                {transferSuccessMsg}
              </div>
            )}

            <form onSubmit={handleExecuteTransfer} className="space-y-3.5">
              <div>
                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                  Transferable Entity
                </label>
                <select
                  value={transferForm.transferable_type}
                  onChange={(e) => setTransferForm({ ...transferForm, transferable_type: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white"
                >
                  <option value="lead">Lead</option>
                  <option value="employee">Employee</option>
                  <option value="project">Project</option>
                  <option value="ticket">Support Ticket</option>
                  <option value="appointment">Appointment / Meeting</option>
                  <option value="customer">Customer Account</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                  Record ID
                </label>
                <input
                  type="number"
                  placeholder="e.g. 42"
                  value={transferForm.transferable_id}
                  onChange={(e) => setTransferForm({ ...transferForm, transferable_id: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white"
                  required
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                  Destination Branch
                </label>
                <select
                  value={transferForm.to_branch_id}
                  onChange={(e) => setTransferForm({ ...transferForm, to_branch_id: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white"
                  required
                >
                  <option value="">Select Target Branch...</option>
                  {branches.map((b) => (
                    <option key={b.id} value={b.id}>
                      {b.name} ({b.code}) - {b.city || 'HQ'}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                  Transfer Justification / Reason
                </label>
                <textarea
                  rows={3}
                  placeholder="e.g. Customer relocated, or staff reallocated for local regional rollout..."
                  value={transferForm.reason}
                  onChange={(e) => setTransferForm({ ...transferForm, reason: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white"
                />
              </div>

              <button
                type="submit"
                disabled={transferMutation.isPending}
                className="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-semibold shadow-md transition-colors"
              >
                {transferMutation.isPending ? 'Processing Transfer...' : 'Execute Branch Transfer'}
              </button>
            </form>
          </div>

          {/* Transfer History Table */}
          <div className="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
            <div className="p-4 border-b border-slate-200 dark:border-slate-700">
              <h3 className="font-bold text-slate-900 dark:text-white">Branch Transfer Audit Trail</h3>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-sm text-left">
                <thead className="bg-slate-50 dark:bg-slate-900 text-xs text-slate-500 uppercase">
                  <tr>
                    <th className="py-3 px-4">Type & ID</th>
                    <th className="py-3 px-4">From Branch</th>
                    <th className="py-3 px-4">To Branch</th>
                    <th className="py-3 px-4">Initiated By</th>
                    <th className="py-3 px-4">Reason</th>
                    <th className="py-3 px-4">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200 dark:divide-slate-700 text-xs">
                  {transferHistory.map((th) => (
                    <tr key={th.id} className="hover:bg-slate-50/50">
                      <td className="py-3 px-4 font-bold uppercase text-indigo-600">
                        {th.transferable_type} #{th.transferable_id}
                      </td>
                      <td className="py-3 px-4">{th.from_branch?.name || 'Unassigned / Global'}</td>
                      <td className="py-3 px-4 font-semibold text-slate-800 dark:text-white">
                        {th.to_branch?.name}
                      </td>
                      <td className="py-3 px-4">{th.initiated_by?.name || 'System'}</td>
                      <td className="py-3 px-4 max-w-xs truncate text-slate-500">{th.reason || 'N/A'}</td>
                      <td className="py-3 px-4">
                        <span className="px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded font-semibold">
                          {th.status}
                        </span>
                      </td>
                    </tr>
                  ))}
                  {transferHistory.length === 0 && (
                    <tr>
                      <td colSpan={6} className="text-center py-6 text-slate-400">
                        No branch transfer logs found.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 5: Branch Settings */}
      {activeTab === 'settings' && selectedBranch && (
        <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 max-w-3xl space-y-6">
          <div>
            <h3 className="font-bold text-slate-900 dark:text-white text-lg">
              Regional Operational Preferences: {selectedBranch.name} ({selectedBranch.code})
            </h3>
            <p className="text-xs text-slate-500">
              Configure working hours, appointment slots, lead auto-routing, and sales targets for this location.
            </p>
          </div>

          <div className="space-y-4 text-sm">
            <div className="p-4 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 space-y-2">
              <span className="font-bold text-slate-800 dark:text-white">Working Days & Business Hours</span>
              <div className="text-xs text-slate-500">
                Standard Working Days: {selectedBranch.working_days?.join(', ') || 'Monday to Saturday'}
              </div>
              <div className="text-xs text-slate-500">
                Operating Hours: 09:00 - 18:00 ({selectedBranch.timezone || 'Asia/Kolkata'})
              </div>
            </div>

            <div className="p-4 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 space-y-2">
              <span className="font-bold text-slate-800 dark:text-white">Lead Routing Strategy</span>
              <div className="text-xs text-slate-500">
                Strategy: Round Robin with automatic fallback to Branch Manager on SLA breach.
              </div>
            </div>

            <div className="p-4 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 space-y-2">
              <span className="font-bold text-slate-800 dark:text-white">Appointment Booking Availability</span>
              <div className="text-xs text-slate-500">
                Slot duration: 30 minutes | Max daily customer slots: 16
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Modal: Add New Branch */}
      {showCreateModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
          <div className="bg-white dark:bg-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-700 space-y-4">
            <div className="flex justify-between items-center">
              <h3 className="font-bold text-slate-900 dark:text-white text-lg">Add New Business Branch</h3>
              <button
                onClick={() => setShowCreateModal(false)}
                className="text-slate-400 hover:text-slate-600 text-lg"
              >
                &times;
              </button>
            </div>

            <form onSubmit={handleCreateBranch} className="space-y-3.5">
              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Branch Name *
                </label>
                <input
                  type="text"
                  placeholder="e.g. Connaught Place Hub or Mumbai West"
                  value={newBranch.name}
                  onChange={(e) => setNewBranch({ ...newBranch, name: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm"
                  required
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    City
                  </label>
                  <input
                    type="text"
                    placeholder="e.g. New Delhi"
                    value={newBranch.city}
                    onChange={(e) => setNewBranch({ ...newBranch, city: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    State
                  </label>
                  <input
                    type="text"
                    placeholder="e.g. Delhi"
                    value={newBranch.state}
                    onChange={(e) => setNewBranch({ ...newBranch, state: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Official Email
                  </label>
                  <input
                    type="email"
                    placeholder="branch@company.com"
                    value={newBranch.email}
                    onChange={(e) => setNewBranch({ ...newBranch, email: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Official Phone
                  </label>
                  <input
                    type="text"
                    placeholder="+91 98765 43210"
                    value={newBranch.phone}
                    onChange={(e) => setNewBranch({ ...newBranch, phone: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Street Address
                </label>
                <input
                  type="text"
                  placeholder="Block B, 4th Floor, Commercial Tower"
                  value={newBranch.address_line1}
                  onChange={(e) => setNewBranch({ ...newBranch, address_line1: e.target.value })}
                  className="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm"
                />
              </div>

              <div className="flex justify-end gap-3 pt-3">
                <button
                  type="button"
                  onClick={() => setShowCreateModal(false)}
                  className="px-4 py-2 border border-slate-300 text-slate-700 rounded-xl text-sm font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createBranchMutation.isPending}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-semibold shadow"
                >
                  {createBranchMutation.isPending ? 'Saving...' : 'Create Branch'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
