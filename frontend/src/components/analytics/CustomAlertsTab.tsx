import React, { useState } from 'react';
import {
  Bell,
  AlertTriangle,
  Play,
  Plus,
  CheckCircle,
  Clock,
  Mail,
  MessageSquare,
  ShieldAlert,
  X,
} from 'lucide-react';
import {
  useCustomAnalyticsAlerts,
  useCreateCustomAnalyticsAlert,
  useEvaluateCustomAnalyticsAlerts,
} from '../../hooks/useAnalyticsBI';

export const CustomAlertsTab: React.FC = () => {
  const { data: alerts, isLoading } = useCustomAnalyticsAlerts();
  const createAlert = useCreateCustomAnalyticsAlert();
  const evaluateAlerts = useEvaluateCustomAnalyticsAlerts();

  const [showCreateModal, setShowCreateModal] = useState(false);
  const [evalResult, setEvalResult] = useState<any>(null);

  const [name, setName] = useState('');
  const [metricType, setMetricType] = useState('lead_conversion_rate');
  const [operator, setOperator] = useState('<');
  const [threshold, setThreshold] = useState<number>(10);
  const [channels, setChannels] = useState<string[]>(['email', 'in_app']);
  const [recipients, setRecipients] = useState('admin@enterprise.in');

  const metricTypes = [
    { value: 'lead_conversion_rate', label: 'Lead Conversion Rate (%)' },
    { value: 'overdue_payments', label: 'Overdue Receivables (Count)' },
    { value: 'unassigned_leads', label: 'Unassigned Leads (Count)' },
    { value: 'deal_pipeline_shortfall', label: 'Deal Pipeline Value (INR)' },
    { value: 'support_sla_breaches', label: 'Support SLA Breaches (Count)' },
    { value: 'high_priority_unresolved_tickets', label: 'High Priority Unresolved Tickets' },
  ];

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) return;

    await createAlert.mutateAsync({
      name,
      metric_type: metricType,
      condition_operator: operator,
      threshold_value: Number(threshold),
      channels,
      notification_channels: channels,
      recipients: recipients.split(',').map((r) => r.trim()).filter(Boolean),
      is_active: true,
    });

    setShowCreateModal(false);
    setName('');
  };

  const handleEvaluate = async () => {
    try {
      const res = await evaluateAlerts.mutateAsync();
      setEvalResult(res.data);
    } catch (err: any) {
      console.error('Failed to evaluate alerts:', err);
    }
  };

  return (
    <div className="space-y-6">
      {/* Top Banner */}
      <div className="bg-gray-800/80 border border-gray-700/70 rounded-xl p-5 shadow flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 className="text-lg font-bold text-white flex items-center gap-2">
            <Bell className="w-5 h-5 text-amber-400" />
            Automated KPI & Metric Threshold Alerts
          </h2>
          <p className="text-sm text-gray-400 mt-1">
            Proactively monitor conversion rates, overdue receivables, and SLA violations with
            automatic incident dispatch.
          </p>
        </div>

        <div className="flex items-center gap-3">
          <button
            onClick={handleEvaluate}
            disabled={evaluateAlerts.isPending}
            className="flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-sm font-medium transition shadow disabled:opacity-50"
          >
            <Play className={`w-4 h-4 ${evaluateAlerts.isPending ? 'animate-spin' : ''}`} />
            Evaluate Alerts Now
          </button>
          <button
            onClick={() => setShowCreateModal(true)}
            className="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition shadow"
          >
            <Plus className="w-4 h-4" />
            New Alert Rule
          </button>
        </div>
      </div>

      {/* Evaluation Results Banner */}
      {evalResult && (
        <div className="bg-amber-950/30 border border-amber-500/40 rounded-xl p-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2 text-amber-300 font-semibold text-sm">
              <CheckCircle className="w-4 h-4" />
              Alert Evaluation Engine Complete
            </div>
            <button
              onClick={() => setEvalResult(null)}
              className="text-gray-400 hover:text-white"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
          <div className="text-xs text-gray-300 mt-2">
            Evaluated {evalResult.evaluated ?? 0} active rule(s). Triggered:{' '}
            <span className="font-bold text-amber-400">{evalResult.triggered ?? 0}</span> alert(s).
          </div>
          {evalResult.notifications && evalResult.notifications.length > 0 && (
            <div className="mt-3 space-y-1.5">
              {evalResult.notifications.map((n: any, i: number) => (
                <div
                  key={i}
                  className="text-xs bg-gray-900/60 p-2 rounded border border-amber-500/20 text-gray-200"
                >
                  <span className="font-semibold text-amber-300">{n.alert_name}:</span> {n.message}
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Alert Rules List */}
      {isLoading ? (
        <div className="p-12 text-center text-gray-400 bg-gray-800/40 rounded-xl border border-gray-700">
          Loading configured alert rules...
        </div>
      ) : !alerts || alerts.length === 0 ? (
        <div className="p-12 text-center text-gray-400 bg-gray-800/40 rounded-xl border border-gray-700">
          <Bell className="w-10 h-10 mx-auto mb-3 text-gray-500" />
          <h3 className="text-base font-medium text-white">No Custom Alerts Configured</h3>
          <p className="text-sm text-gray-400 mt-1">
            Create automated threshold rules to stay notified when key operational metrics breach expectations.
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {alerts.map((alert) => (
            <div
              key={alert.id}
              className="bg-gray-800/90 border border-gray-700 rounded-xl p-5 shadow flex flex-col justify-between"
            >
              <div>
                <div className="flex items-start justify-between">
                  <div className="flex items-center gap-2">
                    <span
                      className={`w-2.5 h-2.5 rounded-full ${
                        alert.is_active ? 'bg-emerald-400' : 'bg-gray-500'
                      }`}
                    />
                    <h3 className="font-bold text-white text-base">{alert.name}</h3>
                  </div>
                  <span className="text-xs px-2 py-0.5 rounded bg-gray-700 text-gray-300 font-mono">
                    ID #{alert.id}
                  </span>
                </div>

                <div className="mt-3 p-3 bg-gray-900/60 rounded-lg border border-gray-700/40 text-xs">
                  <span className="text-gray-400 block mb-1">Trigger Condition:</span>
                  <div className="font-mono text-amber-300 font-semibold text-sm">
                    {alert.metric_type} {alert.condition_operator} {alert.threshold_value}
                  </div>
                </div>

                <div className="mt-3 space-y-1 text-xs text-gray-400">
                  <div className="flex items-center gap-2">
                    <Clock className="w-3.5 h-3.5" />
                    <span>
                      Last Evaluated:{' '}
                      <span className="text-gray-300">
                        {alert.last_evaluated_at ? new Date(alert.last_evaluated_at).toLocaleString('en-IN') : 'Never'}
                      </span>
                    </span>
                  </div>
                  <div className="flex items-center gap-2">
                    <AlertTriangle className="w-3.5 h-3.5 text-amber-400" />
                    <span>
                      Trigger Count:{' '}
                      <span className="text-amber-400 font-bold">{alert.trigger_count ?? 0}</span>
                    </span>
                  </div>
                </div>

                {alert.channels && alert.channels.length > 0 && (
                  <div className="mt-3 flex items-center gap-1.5 flex-wrap">
                    {alert.channels.map((ch: string) => (
                      <span
                        key={ch}
                        className="text-[10px] uppercase font-semibold px-2 py-0.5 rounded bg-blue-900/30 text-blue-300 border border-blue-500/20"
                      >
                        {ch}
                      </span>
                    ))}
                  </div>
                )}
              </div>

              <div className="mt-4 pt-3 border-t border-gray-700 flex items-center justify-between text-xs text-gray-400">
                <span>Status: {alert.is_active ? 'Active Engine' : 'Paused'}</span>
                <span className="text-gray-500">
                  {alert.last_triggered_at
                    ? `Fired: ${new Date(alert.last_triggered_at).toLocaleDateString('en-IN')}`
                    : 'Not triggered yet'}
                </span>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Create Modal */}
      {showCreateModal && (
        <div className="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4 backdrop-blur-sm">
          <div className="bg-gray-800 border border-gray-700 rounded-xl max-w-lg w-full p-6 shadow-2xl">
            <div className="flex items-center justify-between pb-4 border-b border-gray-700">
              <h3 className="text-lg font-bold text-white flex items-center gap-2">
                <Bell className="w-5 h-5 text-amber-400" />
                Configure Analytics Alert Rule
              </h3>
              <button
                onClick={() => setShowCreateModal(false)}
                className="text-gray-400 hover:text-white"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleCreate} className="mt-4 space-y-4">
              <div>
                <label className="block text-xs font-semibold text-gray-300 mb-1">
                  Alert Name *
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Critical AR Overdue Spike"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  className="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-300 mb-1">
                  Target Metric *
                </label>
                <select
                  value={metricType}
                  onChange={(e) => setMetricType(e.target.value)}
                  className="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500"
                >
                  {metricTypes.map((m) => (
                    <option key={m.value} value={m.value}>
                      {m.label}
                    </option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-gray-300 mb-1">
                    Operator *
                  </label>
                  <select
                    value={operator}
                    onChange={(e) => setOperator(e.target.value)}
                    className="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500 font-mono"
                  >
                    <option value="<">&lt; Less Than</option>
                    <option value="<=">&lt;= Less Than or Equal</option>
                    <option value=">">&gt; Greater Than</option>
                    <option value=">=">&gt;= Greater Than or Equal</option>
                    <option value="==">== Equal To</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-semibold text-gray-300 mb-1">
                    Threshold Value *
                  </label>
                  <input
                    type="number"
                    required
                    value={threshold}
                    onChange={(e) => setThreshold(Number(e.target.value))}
                    className="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-300 mb-1">
                  Recipient Emails (comma separated)
                </label>
                <input
                  type="text"
                  value={recipients}
                  onChange={(e) => setRecipients(e.target.value)}
                  placeholder="admin@enterprise.in, saleshead@enterprise.in"
                  className="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500"
                />
              </div>

              <div className="flex items-center justify-end gap-3 pt-4 border-t border-gray-700">
                <button
                  type="button"
                  onClick={() => setShowCreateModal(false)}
                  className="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-lg text-sm"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createAlert.isPending}
                  className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium shadow disabled:opacity-50"
                >
                  Save Alert Rule
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
