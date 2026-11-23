import React, { useState } from 'react';
import {
  Trophy,
  Award,
  Medal,
  TrendingUp,
  Target,
  DollarSign,
  Users,
  Calendar,
  CheckCircle2,
  XCircle,
  Plus,
  Settings,
  ChevronRight,
  BarChart2,
  Filter,
} from 'lucide-react';
import {
  useSalesLeaderboard,
  useSalesTeams,
  useSalesQuotas,
  useSaveSalesQuota,
  useCreateSalesTeam,
} from '../../hooks/useSalesPipeline';

interface SalesTeamLeaderboardViewProps {
  theme?: 'light' | 'dark';
}

export default function SalesTeamLeaderboardView({ theme = 'dark' }: SalesTeamLeaderboardViewProps) {
  const [period, setPeriod] = useState<'monthly' | 'quarterly' | 'yearly'>('monthly');
  const [selectedTeamId, setSelectedTeamId] = useState<number | undefined>(undefined);

  // Modals state
  const [showQuotaModal, setShowQuotaModal] = useState(false);
  const [showTeamModal, setShowTeamModal] = useState(false);

  // Quota form state
  const [quotaUserId, setQuotaUserId] = useState<number | ''>('');
  const [quotaTargetRevenue, setQuotaTargetRevenue] = useState<number>(50000);
  const [quotaDealsCount, setQuotaDealsCount] = useState<number>(5);
  const [quotaLeadsCount, setQuotaLeadsCount] = useState<number>(20);
  const [quotaStartDate, setQuotaStartDate] = useState('');
  const [quotaEndDate, setQuotaEndDate] = useState('');

  // Team form state
  const [newTeamName, setNewTeamName] = useState('');
  const [newTeamDescription, setNewTeamDescription] = useState('');

  // Queries
  const { data: leaderboardData, isLoading: loadingLeaderboard } = useSalesLeaderboard({
    period,
    team_id: selectedTeamId,
  });
  const { data: salesTeams = [] } = useSalesTeams();

  // Mutations
  const saveQuotaMutation = useSaveSalesQuota();
  const createTeamMutation = useCreateSalesTeam();

  const entries = leaderboardData?.leaderboard || [];

  // Summary Metrics
  const totalWonRevenue = entries.reduce((sum, e) => sum + (Number(e.revenue_won) || 0), 0);
  const totalTargetRevenue = entries.reduce((sum, e) => sum + (Number(e.target_revenue) || 0), 0);
  const avgAttainment =
    totalTargetRevenue > 0 ? Math.round((totalWonRevenue / totalTargetRevenue) * 100) : 0;
  const totalWonDeals = entries.reduce((sum, e) => sum + (Number(e.deals_won_count) || 0), 0);
  const totalLostDeals = entries.reduce((sum, e) => sum + (Number(e.deals_lost_count) || 0), 0);
  const teamWinRate =
    totalWonDeals + totalLostDeals > 0
      ? Math.round((totalWonDeals / (totalWonDeals + totalLostDeals)) * 100)
      : 0;
  const topPerformer = entries[0];

  const handleSaveQuota = (e: React.FormEvent) => {
    e.preventDefault();
    if (!quotaUserId || quotaTargetRevenue <= 0) return;

    const now = new Date();
    const start = quotaStartDate || new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
    const end = quotaEndDate || new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0];

    saveQuotaMutation.mutate(
      {
        user_id: Number(quotaUserId),
        sales_team_id: selectedTeamId,
        period,
        start_date: start,
        end_date: end,
        target_revenue: quotaTargetRevenue,
        target_deals_count: quotaDealsCount,
        target_leads_count: quotaLeadsCount,
      },
      {
        onSuccess: () => {
          setShowQuotaModal(false);
        },
      }
    );
  };

  const handleCreateTeam = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newTeamName.trim()) return;

    createTeamMutation.mutate(
      {
        name: newTeamName.trim(),
        description: newTeamDescription.trim() || undefined,
      },
      {
        onSuccess: () => {
          setShowTeamModal(false);
          setNewTeamName('');
          setNewTeamDescription('');
        },
      }
    );
  };

  return (
    <div className="space-y-6">
      {/* Header and Controls */}
      <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div className="space-y-1">
            <h3 className="text-lg font-bold text-slate-100 flex items-center gap-2">
              <Trophy className="w-5 h-5 text-amber-400" />
              Sales Team Performance & Leaderboard
            </h3>
            <p className="text-xs text-slate-400">
              Track revenue targets, quota attainment percentages, win rates, and rep rankings
            </p>
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={() => setShowTeamModal(true)}
              className="flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold border border-slate-700 transition-colors"
            >
              <Users className="w-4 h-4" />
              Manage Teams
            </button>
            <button
              onClick={() => setShowQuotaModal(true)}
              className="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold shadow-sm transition-colors"
            >
              <Target className="w-4 h-4" />
              Set Quotas
            </button>
          </div>
        </div>

        {/* Filter Controls */}
        <div className="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-800/80">
          <div className="flex items-center gap-2">
            <span className="text-xs text-slate-400">Period:</span>
            {(['monthly', 'quarterly', 'yearly'] as const).map((p) => (
              <button
                key={p}
                onClick={() => setPeriod(p)}
                className={`px-3 py-1 rounded-xl text-xs font-medium capitalize transition-all ${
                  period === p
                    ? 'bg-indigo-600 text-white font-bold'
                    : 'bg-slate-800 text-slate-400 hover:text-slate-200'
                }`}
              >
                {p}
              </button>
            ))}
          </div>

          <div className="flex items-center gap-2">
            <span className="text-xs text-slate-400">Sales Team:</span>
            <select
              value={selectedTeamId || ''}
              onChange={(e) => setSelectedTeamId(e.target.value ? Number(e.target.value) : undefined)}
              className="bg-slate-800 border border-slate-700 text-slate-200 text-xs rounded-xl px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            >
              <option value="">All Teams (Workspace-wide)</option>
              {salesTeams.map((team) => (
                <option key={team.id} value={team.id}>
                  {team.name}
                </option>
              ))}
            </select>
          </div>
        </div>
      </div>

      {/* Summary KPI Strip */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 flex items-center gap-3">
          <div className="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 shrink-0">
            <DollarSign className="w-6 h-6" />
          </div>
          <div>
            <p className="text-xs text-slate-400 font-medium">Total Revenue Won</p>
            <p className="text-lg font-bold font-mono text-emerald-300">
              ${totalWonRevenue.toLocaleString()}
            </p>
          </div>
        </div>

        <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 flex items-center gap-3">
          <div className="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400 shrink-0">
            <Target className="w-6 h-6" />
          </div>
          <div>
            <p className="text-xs text-slate-400 font-medium">Avg Attainment</p>
            <p className="text-lg font-bold font-mono text-purple-300">{avgAttainment}%</p>
          </div>
        </div>

        <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 flex items-center gap-3">
          <div className="w-12 h-12 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400 shrink-0">
            <BarChart2 className="w-6 h-6" />
          </div>
          <div>
            <p className="text-xs text-slate-400 font-medium">Team Win Rate</p>
            <p className="text-lg font-bold font-mono text-blue-300">{teamWinRate}%</p>
          </div>
        </div>

        <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 flex items-center gap-3">
          <div className="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 shrink-0">
            <Trophy className="w-6 h-6" />
          </div>
          <div>
            <p className="text-xs text-slate-400 font-medium">Top Performer</p>
            <p className="text-sm font-bold text-amber-300 truncate max-w-[120px]">
              {topPerformer?.name || 'N/A'}
            </p>
          </div>
        </div>
      </div>

      {/* Leaderboard Table */}
      <div className="rounded-2xl border bg-slate-900/60 border-slate-800 overflow-hidden">
        <div className="p-4 border-b border-slate-800 bg-slate-900/80 flex items-center justify-between">
          <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400">
            Sales Rep Rankings ({entries.length})
          </h4>
          <span className="text-xs text-slate-500">
            {leaderboardData?.start_date} &rarr; {leaderboardData?.end_date}
          </span>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="border-b border-slate-800 bg-slate-800/40 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                <th className="py-3 px-4 w-12 text-center">Rank</th>
                <th className="py-3 px-4">Sales Representative</th>
                <th className="py-3 px-4 text-right">Target Quota</th>
                <th className="py-3 px-4 text-right">Revenue Won</th>
                <th className="py-3 px-4 w-44">Attainment %</th>
                <th className="py-3 px-4 text-center">Deals (W/L)</th>
                <th className="py-3 px-4 text-right">Win Rate</th>
                <th className="py-3 px-4 text-right">Leads</th>
                <th className="py-3 px-4 text-right">Tasks</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800 text-xs">
              {loadingLeaderboard ? (
                <tr>
                  <td colSpan={9} className="py-16 text-center text-slate-500">
                    Loading sales leaderboard...
                  </td>
                </tr>
              ) : entries.length === 0 ? (
                <tr>
                  <td colSpan={9} className="py-16 text-center text-slate-500">
                    No sales activity found for this period.
                  </td>
                </tr>
              ) : (
                entries.map((entry) => {
                  const attainment = Number(entry.attainment_percent) || 0;
                  const isGold = entry.rank === 1;
                  const isSilver = entry.rank === 2;
                  const isBronze = entry.rank === 3;

                  return (
                    <tr
                      key={entry.user_id}
                      className="hover:bg-slate-800/40 transition-colors"
                    >
                      {/* Rank */}
                      <td className="py-3.5 px-4 text-center font-bold">
                        {isGold ? (
                          <span className="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-500/20 text-amber-400 border border-amber-500/30 text-xs">
                            🥇
                          </span>
                        ) : isSilver ? (
                          <span className="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-400/20 text-slate-300 border border-slate-400/30 text-xs">
                            🥈
                          </span>
                        ) : isBronze ? (
                          <span className="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-700/20 text-amber-600 border border-amber-700/30 text-xs">
                            🥉
                          </span>
                        ) : (
                          <span className="text-slate-400 font-mono">#{entry.rank}</span>
                        )}
                      </td>

                      {/* Rep Name & Email */}
                      <td className="py-3.5 px-4">
                        <div className="flex items-center gap-2.5">
                          <div className="w-8 h-8 rounded-full bg-indigo-500/20 border border-indigo-500/40 flex items-center justify-center font-bold text-xs text-indigo-300">
                            {entry.name.charAt(0).toUpperCase()}
                          </div>
                          <div>
                            <p className="font-semibold text-slate-100">{entry.name}</p>
                            <p className="text-[10px] text-slate-500">{entry.role || entry.email}</p>
                          </div>
                        </div>
                      </td>

                      {/* Target */}
                      <td className="py-3.5 px-4 text-right font-mono text-slate-400">
                        ${Number(entry.target_revenue || 0).toLocaleString()}
                      </td>

                      {/* Revenue Won */}
                      <td className="py-3.5 px-4 text-right font-mono font-bold text-emerald-400">
                        ${Number(entry.revenue_won || 0).toLocaleString()}
                      </td>

                      {/* Attainment % Progress Bar */}
                      <td className="py-3.5 px-4">
                        <div className="space-y-1">
                          <div className="flex items-center justify-between text-[11px] font-mono">
                            <span
                              className={`font-bold ${
                                attainment >= 100
                                  ? 'text-emerald-400'
                                  : attainment >= 70
                                  ? 'text-sky-400'
                                  : 'text-amber-400'
                              }`}
                            >
                              {attainment}%
                            </span>
                          </div>
                          <div className="w-full h-2 rounded-full bg-slate-800 overflow-hidden">
                            <div
                              className={`h-full rounded-full transition-all duration-500 ${
                                attainment >= 100
                                  ? 'bg-gradient-to-r from-emerald-500 to-teal-400'
                                  : attainment >= 70
                                  ? 'bg-gradient-to-r from-sky-500 to-indigo-500'
                                  : 'bg-gradient-to-r from-amber-500 to-rose-500'
                              }`}
                              style={{ width: `${Math.min(attainment, 100)}%` }}
                            />
                          </div>
                        </div>
                      </td>

                      {/* Deals Won / Lost */}
                      <td className="py-3.5 px-4 text-center font-mono">
                        <span className="text-emerald-400 font-bold">{entry.deals_won_count}</span>
                        <span className="text-slate-600 mx-1">/</span>
                        <span className="text-rose-400">{entry.deals_lost_count}</span>
                      </td>

                      {/* Win Rate */}
                      <td className="py-3.5 px-4 text-right font-mono text-slate-200">
                        {entry.win_rate}%
                      </td>

                      {/* Leads Handled */}
                      <td className="py-3.5 px-4 text-right font-mono text-slate-400">
                        {entry.leads_handled}
                      </td>

                      {/* Tasks Completed */}
                      <td className="py-3.5 px-4 text-right font-mono text-slate-400">
                        {entry.tasks_completed}
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Set Quotas Modal */}
      {showQuotaModal && (
        <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between">
              <h3 className="text-base font-bold text-slate-100 flex items-center gap-2">
                <Target className="w-5 h-5 text-indigo-400" />
                Assign Sales Quota & Targets
              </h3>
              <button
                onClick={() => setShowQuotaModal(false)}
                className="text-slate-400 hover:text-slate-200"
              >
                &times;
              </button>
            </div>

            <form onSubmit={handleSaveQuota} className="space-y-4">
              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">
                  Sales Representative <span className="text-rose-400">*</span>
                </label>
                <select
                  value={quotaUserId}
                  onChange={(e) => setQuotaUserId(Number(e.target.value))}
                  required
                  className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                  <option value="">Select sales rep...</option>
                  {entries.map((e) => (
                    <option key={e.user_id} value={e.user_id}>
                      {e.name} ({e.email})
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">
                  Target Revenue ($) <span className="text-rose-400">*</span>
                </label>
                <input
                  type="number"
                  step="1000"
                  value={quotaTargetRevenue}
                  onChange={(e) => setQuotaTargetRevenue(Number(e.target.value))}
                  required
                  className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">
                    Target Deals Count
                  </label>
                  <input
                    type="number"
                    value={quotaDealsCount}
                    onChange={(e) => setQuotaDealsCount(Number(e.target.value))}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">
                    Target Leads Count
                  </label>
                  <input
                    type="number"
                    value={quotaLeadsCount}
                    onChange={(e) => setQuotaLeadsCount(Number(e.target.value))}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
              </div>

              <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowQuotaModal(false)}
                  className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={saveQuotaMutation.isPending}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold disabled:opacity-50"
                >
                  {saveQuotaMutation.isPending ? 'Saving...' : 'Save Quota'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Manage Sales Teams Modal */}
      {showTeamModal && (
        <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between">
              <h3 className="text-base font-bold text-slate-100 flex items-center gap-2">
                <Users className="w-5 h-5 text-indigo-400" />
                Create Sales Team
              </h3>
              <button
                onClick={() => setShowTeamModal(false)}
                className="text-slate-400 hover:text-slate-200"
              >
                &times;
              </button>
            </div>

            <form onSubmit={handleCreateTeam} className="space-y-4">
              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">
                  Team Name <span className="text-rose-400">*</span>
                </label>
                <input
                  type="text"
                  placeholder="e.g. Enterprise North America..."
                  value={newTeamName}
                  onChange={(e) => setNewTeamName(e.target.value)}
                  required
                  className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">Description</label>
                <textarea
                  rows={2}
                  placeholder="Focus territory, accounts or industries..."
                  value={newTeamDescription}
                  onChange={(e) => setNewTeamDescription(e.target.value)}
                  className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowTeamModal(false)}
                  className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createTeamMutation.isPending}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold disabled:opacity-50"
                >
                  {createTeamMutation.isPending ? 'Creating...' : 'Create Team'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
