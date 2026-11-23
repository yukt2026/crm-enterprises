import React, { useState } from 'react';
import {
  Sparkles,
  TrendingUp,
  AlertTriangle,
  Clock,
  ShieldAlert,
  ArrowRight,
  Info,
  Calendar,
  CheckCircle2,
  RefreshCw,
} from 'lucide-react';
import { useAiBusinessInsights } from '../../hooks/useAnalyticsBI';

interface Props {
  fromDate?: string;
  toDate?: string;
}

export const AiBusinessInsightsTab: React.FC<Props> = ({ fromDate, toDate }) => {
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const { data: insights, isLoading, refetch, isFetching } = useAiBusinessInsights({
    from_date: fromDate,
    to_date: toDate,
  });

  const categories = [
    { id: 'all', label: 'All Insights' },
    { id: 'sales_conversion', label: 'Sales & Conversion' },
    { id: 'sales_pipeline', label: 'Pipeline & Deals' },
    { id: 'lead_followup', label: 'Follow-ups' },
    { id: 'finance_receivables', label: 'Accounts Receivable' },
    { id: 'branch_performance', label: 'Branch Targets' },
    { id: 'customer_support', label: 'Support & SLAs' },
  ];

  const filteredInsights = React.useMemo(() => {
    if (!insights) return [];
    if (selectedCategory === 'all') return insights;
    return insights.filter((i) => i.category === selectedCategory);
  }, [insights, selectedCategory]);

  const highPriorityCount = insights?.filter((i) => i.priority === 'high').length ?? 0;
  const avgConfidence = insights?.length
    ? Math.round((insights.reduce((acc, i) => acc + i.confidence_score, 0) / insights.length) * 100)
    : 0;

  return (
    <div className="space-y-6">
      {/* Top Banner / Disclaimer */}
      <div className="bg-gradient-to-r from-purple-900/40 via-indigo-900/30 to-blue-900/40 border border-purple-500/30 rounded-xl p-5 shadow-lg backdrop-blur-sm">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div className="flex items-start gap-3">
            <div className="p-3 bg-purple-600/20 text-purple-400 rounded-lg border border-purple-500/30">
              <Sparkles className="w-6 h-6 animate-pulse" />
            </div>
            <div>
              <h2 className="text-lg font-bold text-white flex items-center gap-2">
                AI Business Insights & Executive Diagnostic Engine
                <span className="text-xs px-2.5 py-0.5 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30">
                  Grounded RBAC Analysis
                </span>
              </h2>
              <p className="text-sm text-gray-300 mt-1 max-w-3xl">
                Diagnostic observations computed strictly from verified database records. Every observation
                features grounded metrics, confidence scores, and action plans.
              </p>
            </div>
          </div>
          <button
            onClick={() => refetch()}
            disabled={isFetching}
            className="flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium transition shadow disabled:opacity-50"
          >
            <RefreshCw className={`w-4 h-4 ${isFetching ? 'animate-spin' : ''}`} />
            Refresh Insights
          </button>
        </div>

        {/* Mandatory Non-binding Disclaimer */}
        <div className="mt-4 pt-3 border-t border-purple-500/20 flex items-center gap-2 text-xs text-purple-200/80">
          <Info className="w-4 h-4 flex-shrink-0 text-purple-400" />
          <span>
            <strong>Deterministic Audit Notice:</strong> AI insights are generated programmatically
            from enterprise transaction history. Projections and recommendations are advisory and do
            not supersede executive authorization.
          </span>
        </div>
      </div>

      {/* KPI Overview */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-gray-800/80 border border-gray-700/60 rounded-xl p-4 shadow">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider">
              Total Observations
            </span>
            <Sparkles className="w-4 h-4 text-purple-400" />
          </div>
          <div className="text-2xl font-bold text-white mt-2">{insights?.length ?? 0}</div>
          <div className="text-xs text-gray-400 mt-1">Cross-domain system audits</div>
        </div>

        <div className="bg-gray-800/80 border border-gray-700/60 rounded-xl p-4 shadow">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider">
              High Priority Alerts
            </span>
            <AlertTriangle className="w-4 h-4 text-red-400" />
          </div>
          <div className="text-2xl font-bold text-red-400 mt-2">{highPriorityCount}</div>
          <div className="text-xs text-gray-400 mt-1">Immediate intervention recommended</div>
        </div>

        <div className="bg-gray-800/80 border border-gray-700/60 rounded-xl p-4 shadow">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider">
              Mean Grounding Confidence
            </span>
            <CheckCircle2 className="w-4 h-4 text-emerald-400" />
          </div>
          <div className="text-2xl font-bold text-emerald-400 mt-2">{avgConfidence}%</div>
          <div className="text-xs text-gray-400 mt-1">Grounded in recorded CRM data</div>
        </div>

        <div className="bg-gray-800/80 border border-gray-700/60 rounded-xl p-4 shadow">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider">
              Audit Date Scope
            </span>
            <Calendar className="w-4 h-4 text-blue-400" />
          </div>
          <div className="text-sm font-semibold text-white mt-2">
            {fromDate || 'Earliest'} &rarr; {toDate || 'Today'}
          </div>
          <div className="text-xs text-gray-400 mt-1">Filtered reporting period</div>
        </div>
      </div>

      {/* Category Filter Pills */}
      <div className="flex flex-wrap gap-2 pb-2">
        {categories.map((c) => (
          <button
            key={c.id}
            onClick={() => setSelectedCategory(c.id)}
            className={`px-3 py-1.5 rounded-lg text-xs font-medium transition ${
              selectedCategory === c.id
                ? 'bg-purple-600 text-white shadow-sm'
                : 'bg-gray-800 text-gray-300 hover:bg-gray-700 border border-gray-700'
            }`}
          >
            {c.label}
          </button>
        ))}
      </div>

      {/* Insights Cards */}
      {isLoading ? (
        <div className="p-12 text-center text-gray-400 bg-gray-800/40 rounded-xl border border-gray-700">
          <Sparkles className="w-8 h-8 animate-spin mx-auto mb-3 text-purple-400" />
          Analyzing enterprise transactions across domains...
        </div>
      ) : filteredInsights.length === 0 ? (
        <div className="p-12 text-center text-gray-400 bg-gray-800/40 rounded-xl border border-gray-700">
          <CheckCircle2 className="w-10 h-10 mx-auto mb-3 text-emerald-400" />
          <h3 className="text-base font-medium text-white">No Critical Anomaly Found</h3>
          <p className="text-sm text-gray-400 mt-1">
            All evaluated metrics conform to organizational thresholds for this category.
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
          {filteredInsights.map((insight) => {
            const isHigh = insight.priority === 'high';
            const isMedium = insight.priority === 'medium';
            return (
              <div
                key={insight.id}
                className={`bg-gray-800/90 rounded-xl border p-5 flex flex-col justify-between shadow-md transition hover:border-purple-500/50 ${
                  isHigh
                    ? 'border-red-500/40 bg-gradient-to-br from-gray-800 to-red-950/20'
                    : isMedium
                    ? 'border-amber-500/40'
                    : 'border-gray-700'
                }`}
              >
                <div>
                  {/* Card Header */}
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-2">
                      <span
                        className={`text-xs px-2.5 py-0.5 rounded-full font-semibold uppercase tracking-wider ${
                          isHigh
                            ? 'bg-red-500/20 text-red-300 border border-red-500/30'
                            : isMedium
                            ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30'
                            : 'bg-blue-500/20 text-blue-300 border border-blue-500/30'
                        }`}
                      >
                        {insight.priority} Priority
                      </span>
                      <span className="text-xs px-2 py-0.5 rounded bg-gray-700/60 text-gray-300 border border-gray-600/50">
                        {insight.category.replace('_', ' ')}
                      </span>
                    </div>

                    <div className="flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-md bg-purple-900/30 text-purple-300 border border-purple-500/30">
                      <span>Confidence</span>
                      <span className="text-white font-bold">
                        {Math.round(insight.confidence_score * 100)}%
                      </span>
                    </div>
                  </div>

                  {/* Title & Description */}
                  <h3 className="text-base font-bold text-white mt-3">{insight.title}</h3>
                  <p className="text-sm text-gray-300 mt-1 leading-relaxed">
                    {insight.description}
                  </p>

                  {/* Supporting Grounded Data Grid */}
                  {insight.supporting_data && Object.keys(insight.supporting_data).length > 0 && (
                    <div className="mt-4 p-3 bg-gray-900/60 rounded-lg border border-gray-700/50">
                      <div className="text-xs font-semibold text-gray-400 mb-2 flex items-center gap-1.5">
                        <TrendingUp className="w-3.5 h-3.5 text-purple-400" />
                        Grounded Evidentiary Metrics:
                      </div>
                      <div className="grid grid-cols-2 gap-2 text-xs">
                        {Object.entries(insight.supporting_data).map(([key, val]) => (
                          <div key={key} className="bg-gray-800/80 p-2 rounded border border-gray-700/40">
                            <span className="text-gray-400 block capitalize">
                              {key.replace(/_/g, ' ')}
                            </span>
                            <span className="text-white font-semibold">
                              {typeof val === 'number'
                                ? val.toLocaleString()
                                : typeof val === 'object'
                                ? JSON.stringify(val)
                                : String(val)}
                            </span>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Recommended Action Plan */}
                  {insight.suggested_actions && insight.suggested_actions.length > 0 && (
                    <div className="mt-4 space-y-1.5">
                      <div className="text-xs font-semibold text-purple-300 flex items-center gap-1.5">
                        <ArrowRight className="w-3.5 h-3.5" />
                        Suggested Corrective Actions:
                      </div>
                      {insight.suggested_actions.map((act, idx) => (
                        <div
                          key={idx}
                          className="text-xs text-gray-200 bg-purple-950/20 border border-purple-500/20 rounded p-2 flex items-start gap-2"
                        >
                          <span className="text-purple-400 font-bold">{idx + 1}.</span>
                          <span>{act}</span>
                        </div>
                      ))}
                    </div>
                  )}
                </div>

                {/* Footer Disclaimer */}
                <div className="mt-5 pt-3 border-t border-gray-700/60 flex items-center justify-between text-xs text-gray-400">
                  <span className="italic">{insight.limitations_disclaimer}</span>
                  <span className="text-[10px] text-gray-500">ID: {insight.id}</span>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
};
