import React, { useState } from 'react';
import {
  TrendingUp,
  DollarSign,
  Users,
  Target,
  Briefcase,
  AlertTriangle,
  Building2,
  Award,
  Sparkles,
  LayoutGrid,
  Layers,
  CheckCircle2,
  HelpCircle,
} from 'lucide-react';
import {
  useOwnerDashboardMetrics,
  useDashboardLayouts,
  useSaveDashboardLayout,
} from '../../hooks/useAnalyticsBI';

interface Props {
  fromDate: string;
  toDate: string;
}

export const OwnerExecutiveBiTab: React.FC<Props> = ({ fromDate, toDate }) => {
  const [selectedBranch, setSelectedBranch] = useState<string>('');
  const { data: metrics, isLoading } = useOwnerDashboardMetrics({
    from_date: fromDate,
    to_date: toDate,
    branch_id: selectedBranch ? Number(selectedBranch) : undefined,
  });

  const { data: layouts } = useDashboardLayouts();
  const saveLayoutMutation = useSaveDashboardLayout();

  const [layoutName, setLayoutName] = useState('');
  const [showLayoutModal, setShowLayoutModal] = useState(false);

  const handleSaveCurrentLayout = () => {
    if (!layoutName) return;
    saveLayoutMutation.mutate({
      name: layoutName,
      role: 'owner',
      is_default: true,
      widgets: [
        { id: 'kpi_won', type: 'kpi', title: 'Won Revenue', w: 3, h: 2 },
        { id: 'kpi_pipeline', type: 'kpi', title: 'Pipeline Value', w: 3, h: 2 },
        { id: 'kpi_collected', type: 'kpi', title: 'Collected Cash', w: 3, h: 2 },
        { id: 'kpi_profit', type: 'kpi', title: 'Net Margin', w: 3, h: 2 },
      ],
    });
    setShowLayoutModal(false);
    setLayoutName('');
  };

  if (isLoading) {
    return (
      <div className="py-16 text-center text-slate-500">
        <div className="w-8 h-8 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto mb-3" />
        <p className="font-medium">Aggregating actual and forecast BI metrics...</p>
      </div>
    );
  }

  const actuals = metrics?.actuals;
  const forecasts = metrics?.forecasts;
  const performance = metrics?.performance;

  return (
    <div className="space-y-6">
      {/* Top Banner / Controls */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-5 rounded-2xl shadow-lg border border-indigo-900/50">
        <div>
          <div className="inline-flex items-center gap-2 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 mb-2">
            <Sparkles className="w-3.5 h-3.5" /> India-First Executive Cockpit
          </div>
          <h2 className="text-xl font-bold">Executive & Owner Command Center</h2>
          <p className="text-xs text-slate-300 mt-0.5">
            Real recorded business figures mathematically segregated from pipeline projections
          </p>
        </div>

        <div className="flex items-center gap-2.5">
          {layouts && layouts.length > 0 && (
            <div className="text-xs text-indigo-300 bg-white/10 px-3 py-1.5 rounded-lg border border-white/10">
              Active Layout: <span className="font-semibold text-white">{layouts[0].name}</span>
            </div>
          )}
          <button
            onClick={() => setShowLayoutModal(true)}
            className="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow transition-colors"
          >
            <LayoutGrid className="w-3.5 h-3.5" /> Save View Layout
          </button>
        </div>
      </div>

      {/* SECTION 1: ACTUAL REVENUE & CASH (RECORDED ONLY) */}
      <div>
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center gap-2">
            <span className="w-2.5 h-2.5 rounded-full bg-emerald-500" />
            <h3 className="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
              Actual Realized Performance (Real Recorded Data)
            </h3>
          </div>
          <span className="text-xs text-slate-500 font-medium">INR Indian Numbering (Lakhs & Crores)</span>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {/* Won Revenue */}
          <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
            <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs mb-1">
              <span>Won Sales Revenue</span>
              <DollarSign className="w-4 h-4 text-emerald-500" />
            </div>
            <div className="text-2xl font-bold text-slate-900 dark:text-white">
              {actuals?.won_revenue_short || '₹0'}
            </div>
            <div className="text-xs text-slate-500 mt-1">
              Exact: <span className="font-mono text-slate-700 dark:text-slate-300">{actuals?.won_revenue_inr}</span>
            </div>
          </div>

          {/* Collected Cash */}
          <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
            <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs mb-1">
              <span>Liquid Cash Collected</span>
              <CheckCircle2 className="w-4 h-4 text-blue-500" />
            </div>
            <div className="text-2xl font-bold text-slate-900 dark:text-white">
              {actuals?.collected_revenue_short || '₹0'}
            </div>
            <div className="text-xs text-slate-500 mt-1">
              Invoiced: <span className="font-mono text-slate-700 dark:text-slate-300">{actuals?.invoiced_sales_short}</span>
            </div>
          </div>

          {/* Monthly Expenses */}
          <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
            <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs mb-1">
              <span>Recorded Business Expenses</span>
              <Briefcase className="w-4 h-4 text-amber-500" />
            </div>
            <div className="text-2xl font-bold text-slate-900 dark:text-white">
              {actuals?.monthly_expenses_short || '₹0'}
            </div>
            <div className="text-xs text-slate-500 mt-1">
              Pending Collections: <span className="font-mono text-slate-700 dark:text-slate-300">{actuals?.pending_payments_short}</span>
            </div>
          </div>

          {/* Net Realized Profit */}
          <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
            <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs mb-1">
              <span>Net Cash Operating Margin</span>
              <TrendingUp className="w-4 h-4 text-indigo-500" />
            </div>
            <div className="text-2xl font-bold text-slate-900 dark:text-white">
              {actuals?.net_profit_short || '₹0'}
            </div>
            <div className="text-xs text-emerald-600 dark:text-emerald-400 mt-1 font-medium">
              Collected minus operational expenses
            </div>
          </div>
        </div>
      </div>

      {/* SECTION 2: FORECAST & PIPELINE (ESTIMATED PROJECTIONS) */}
      <div className="bg-indigo-50/50 dark:bg-indigo-950/20 p-5 rounded-2xl border border-indigo-100 dark:border-indigo-900/40">
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center gap-2">
            <span className="w-2.5 h-2.5 rounded-full bg-amber-500" />
            <h3 className="text-sm font-bold uppercase tracking-wider text-indigo-900 dark:text-indigo-300">
              Pipeline Opportunities & Forecasted Revenue (Estimated Projections)
            </h3>
          </div>
          <div className="flex items-center gap-1 text-xs text-amber-700 dark:text-amber-400 bg-amber-100 dark:bg-amber-950/50 px-2.5 py-0.5 rounded-full">
            <AlertTriangle className="w-3 h-3" />
            <span>{forecasts?.forecast_disclaimer}</span>
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-indigo-200 dark:border-indigo-900/50 shadow-sm">
            <div className="text-xs text-slate-500 mb-1">Total Open Pipeline</div>
            <div className="text-2xl font-bold text-indigo-700 dark:text-indigo-400">
              {forecasts?.pipeline_value_short || '₹0'}
            </div>
            <div className="text-xs text-slate-400 mt-1 font-mono">{forecasts?.pipeline_value_inr}</div>
          </div>

          <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-indigo-200 dark:border-indigo-900/50 shadow-sm">
            <div className="text-xs text-slate-500 mb-1">Probability-Weighted Forecast</div>
            <div className="text-2xl font-bold text-slate-900 dark:text-white">
              {forecasts?.weighted_forecast_short || '₹0'}
            </div>
            <div className="text-xs text-slate-400 mt-1">Sum(Deal Value × Stage Probability %)</div>
          </div>

          <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-indigo-200 dark:border-indigo-900/50 shadow-sm">
            <div className="text-xs text-slate-500 mb-1">Committed Deals (≥80%)</div>
            <div className="text-2xl font-bold text-slate-900 dark:text-white">
              {forecasts?.committed_forecast_short || '₹0'}
            </div>
            <div className="text-xs text-emerald-600 dark:text-emerald-400 mt-1">High confidence closures</div>
          </div>

          <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-indigo-200 dark:border-indigo-900/50 shadow-sm">
            <div className="text-xs text-slate-500 mb-1">Best-Case Forecast (≥50%)</div>
            <div className="text-2xl font-bold text-slate-900 dark:text-white">
              {forecasts?.best_case_forecast_short || '₹0'}
            </div>
            <div className="text-xs text-slate-400 mt-1">Realistic upside ceiling</div>
          </div>
        </div>
      </div>

      {/* SECTION 3: BRANCH & EMPLOYEE PERFORMANCE TABLES */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Branch Performance */}
        <div className="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
          <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2 mb-4">
            <Building2 className="w-4 h-4 text-indigo-600" />
            Branch Performance & Margin Breakdown
          </h3>

          <div className="overflow-x-auto">
            <table className="w-full text-xs text-left">
              <thead>
                <tr className="border-b border-slate-200 dark:border-slate-800 text-slate-500 font-semibold">
                  <th className="pb-2">Branch</th>
                  <th className="pb-2">Leads</th>
                  <th className="pb-2">Won Sales</th>
                  <th className="pb-2">Expenses</th>
                  <th className="pb-2 text-right">Net Margin</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {performance?.branches?.map((b) => (
                  <tr key={b.branch_id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                    <td className="py-2.5 font-medium text-slate-900 dark:text-white">
                      {b.name} <span className="text-slate-400 font-normal">({b.code})</span>
                    </td>
                    <td className="py-2.5 text-slate-600 dark:text-slate-300">{b.leads_count}</td>
                    <td className="py-2.5 font-bold text-emerald-600">{b.sales_formatted}</td>
                    <td className="py-2.5 text-slate-500">₹{b.expenses.toLocaleString('en-IN')}</td>
                    <td className={`py-2.5 text-right font-bold ${b.net_margin >= 0 ? 'text-indigo-600' : 'text-rose-500'}`}>
                      ₹{b.net_margin.toLocaleString('en-IN')}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Top Sales Representatives */}
        <div className="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
          <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2 mb-4">
            <Award className="w-4 h-4 text-amber-500" />
            Top Sales Producers
          </h3>

          <div className="overflow-x-auto">
            <table className="w-full text-xs text-left">
              <thead>
                <tr className="border-b border-slate-200 dark:border-slate-800 text-slate-500 font-semibold">
                  <th className="pb-2">Representative</th>
                  <th className="pb-2">Dept</th>
                  <th className="pb-2">Leads</th>
                  <th className="pb-2 text-right">Won Sales</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {performance?.employees?.map((emp) => (
                  <tr key={emp.employee_id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                    <td className="py-2.5 font-semibold text-slate-900 dark:text-white">{emp.name}</td>
                    <td className="py-2.5 text-slate-500">{emp.department}</td>
                    <td className="py-2.5 text-slate-600 dark:text-slate-300">{emp.leads_count}</td>
                    <td className="py-2.5 text-right font-bold text-emerald-600">{emp.sales_won_formatted}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* SAVE LAYOUT MODAL */}
      {showLayoutModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800">
            <h3 className="text-base font-bold text-slate-900 dark:text-white mb-2">Save Dashboard Layout</h3>
            <p className="text-xs text-slate-500 mb-4">
              Save your current widget arrangements and filters as a personalized dashboard view.
            </p>
            <input
              type="text"
              placeholder="Layout Name (e.g., Executive Field Operations)"
              value={layoutName}
              onChange={(e) => setLayoutName(e.target.value)}
              className="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-700 rounded-lg dark:bg-slate-800 dark:text-white mb-4 focus:outline-none focus:border-indigo-600"
            />
            <div className="flex justify-end gap-2">
              <button
                onClick={() => setShowLayoutModal(false)}
                className="px-4 py-2 text-xs font-medium text-slate-600 dark:text-slate-400 hover:text-slate-900"
              >
                Cancel
              </button>
              <button
                onClick={handleSaveCurrentLayout}
                disabled={!layoutName || saveLayoutMutation.isPending}
                className="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-50"
              >
                {saveLayoutMutation.isPending ? 'Saving...' : 'Save Layout'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
