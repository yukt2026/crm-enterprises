import React, { useState } from 'react';
import {
  Activity,
  ShieldCheck,
  Search,
  Filter,
  Eye,
  X,
  Clock,
  User,
  Globe,
  Database,
  ArrowRight,
  Loader2,
  FileCode,
  Calendar,
} from 'lucide-react';
import { useAuditLogs } from '../../hooks/useSharedServices';
import { AuditLogEntry } from '../../api/types';

interface AuditLogViewerProps {
  theme?: 'light' | 'dark';
}

function getActionColor(action: string) {
  if (action.includes('delete') || action.includes('destroy') || action.includes('failed')) {
    return 'text-rose-400 bg-rose-500/10 border-rose-500/20';
  }
  if (action.includes('update') || action.includes('modify') || action.includes('patch')) {
    return 'text-amber-400 bg-amber-500/10 border-amber-500/20';
  }
  if (action.includes('create') || action.includes('store') || action.includes('insert')) {
    return 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20';
  }
  if (action.includes('login') || action.includes('auth')) {
    return 'text-sky-400 bg-sky-500/10 border-sky-500/20';
  }
  return 'text-slate-400 bg-slate-500/10 border-slate-500/20';
}

export default function AuditLogViewer({ theme = 'dark' }: AuditLogViewerProps) {
  const [actionFilter, setActionFilter] = useState('');
  const [page, setPage] = useState(1);
  const [selectedLog, setSelectedLog] = useState<AuditLogEntry | null>(null);

  const { data: response, isLoading } = useAuditLogs({
    action: actionFilter || undefined,
    page,
    per_page: 25,
  });

  const logs = response?.data || [];
  const meta = response?.meta;

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100 flex items-center gap-2.5">
            <Activity className="h-6 w-6 text-emerald-500" />
            Immutable Audit Log & Security Telemetry
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Tamper-proof ledger tracking actor mutations, before/after record diffs, IP addresses, and workspace events.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <span className="px-3 py-1.5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 text-xs font-semibold flex items-center gap-1.5">
            <ShieldCheck className="h-4 w-4" />
            <span>Tamper-Proof Ledger</span>
          </span>
        </div>
      </div>

      {/* Filter Bar */}
      <div
        className={`p-3 rounded-2xl border flex flex-col sm:flex-row items-center justify-between gap-3 ${
          theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
        }`}
      >
        <div className="flex items-center gap-2 w-full sm:w-auto">
          <Filter className="h-4 w-4 text-slate-400" />
          <select
            value={actionFilter}
            onChange={(e) => {
              setActionFilter(e.target.value);
              setPage(1);
            }}
            className="px-2.5 py-1.5 rounded-xl border border-slate-700 bg-transparent text-xs focus:outline-none text-slate-300"
          >
            <option value="" className="bg-slate-900">All Audit Actions</option>
            <option value="create" className="bg-slate-900">Create Records</option>
            <option value="update" className="bg-slate-900">Update Records</option>
            <option value="delete" className="bg-slate-900">Delete Records</option>
            <option value="auth.login" className="bg-slate-900">Authentication / Login</option>
            <option value="upload" className="bg-slate-900">File Uploads</option>
          </select>
        </div>

        {meta && (
          <div className="text-xs text-slate-400">
            Total recorded events: <span className="font-semibold text-slate-200">{meta.total}</span>
          </div>
        )}
      </div>

      {/* Audit Log Table */}
      {isLoading ? (
        <div className="py-24 text-center text-xs text-slate-400 flex flex-col items-center gap-2">
          <Loader2 className="h-8 w-8 text-emerald-500 animate-spin" />
          <span>Loading immutable audit ledger...</span>
        </div>
      ) : logs.length === 0 ? (
        <div
          className={`py-20 rounded-2xl border text-center flex flex-col items-center justify-center p-6 ${
            theme === 'dark' ? 'bg-slate-900/40 border-slate-800' : 'bg-white border-slate-200'
          }`}
        >
          <Activity className="h-10 w-10 text-slate-600 mb-3" />
          <h3 className="font-semibold text-sm text-slate-200">No audit logs recorded</h3>
          <p className="text-xs text-slate-400 mt-1 max-w-sm">
            Audit logs are generated automatically on workspace data modifications and logins.
          </p>
        </div>
      ) : (
        <div
          className={`rounded-2xl border overflow-hidden ${
            theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
          }`}
        >
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead
                className={`border-b ${
                  theme === 'dark' ? 'bg-slate-950/60 border-slate-800 text-slate-400' : 'bg-slate-50 border-slate-200 text-slate-600'
                }`}
              >
                <tr>
                  <th className="py-3 px-4 font-semibold">Actor</th>
                  <th className="py-3 px-4 font-semibold">Action</th>
                  <th className="py-3 px-4 font-semibold">Target Entity</th>
                  <th className="py-3 px-4 font-semibold">IP & Client</th>
                  <th className="py-3 px-4 font-semibold">Timestamp</th>
                  <th className="py-3 px-4 font-semibold text-right">Details & Diff</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/40">
                {logs.map((log) => {
                  const colorCls = getActionColor(log.action);
                  const hasDiff = !!(log.old_values || log.new_values);

                  return (
                    <tr
                      key={log.id}
                      className={`transition-colors ${
                        theme === 'dark' ? 'hover:bg-slate-800/40' : 'hover:bg-slate-50'
                      }`}
                    >
                      <td className="py-3.5 px-4">
                        <div className="flex items-center gap-2">
                          <div className="h-7 w-7 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-[11px] font-bold text-slate-300 shrink-0">
                            {(log.user?.name || log.user_name || 'U').charAt(0)}
                          </div>
                          <div>
                            <span className="font-semibold text-slate-200 block truncate max-w-[140px]">
                              {log.user?.name || log.user_name || 'System Actor'}
                            </span>
                            <span className="text-[10px] text-slate-400 font-mono">
                              {log.user?.email || log.role || 'service'}
                            </span>
                          </div>
                        </div>
                      </td>

                      <td className="py-3.5 px-4">
                        <span className={`px-2 py-0.5 rounded-full border text-[10px] font-mono font-semibold uppercase ${colorCls}`}>
                          {log.action}
                        </span>
                      </td>

                      <td className="py-3.5 px-4">
                        <div className="text-slate-300 font-medium">
                          {log.auditable_type ? log.auditable_type.split('\\').pop() : 'System'}
                        </div>
                        {log.auditable_id && (
                          <div className="text-[10px] text-slate-400 font-mono">
                            ID: #{log.auditable_id}
                          </div>
                        )}
                      </td>

                      <td className="py-3.5 px-4 font-mono text-[11px] text-slate-400">
                        <div>{log.ip_address || '127.0.0.1'}</div>
                        <div className="text-[10px] truncate max-w-[120px] text-slate-500">
                          {log.user_agent ? log.user_agent.split(' ')[0] : 'Browser Client'}
                        </div>
                      </td>

                      <td className="py-3.5 px-4 font-mono text-[11px] text-slate-400">
                        {log.created_at ? new Date(log.created_at).toLocaleString() : '—'}
                      </td>

                      <td className="py-3.5 px-4 text-right">
                        <button
                          onClick={() => setSelectedLog(log)}
                          className={`px-2.5 py-1 rounded-lg text-xs font-semibold flex items-center gap-1.5 ml-auto transition-colors ${
                            hasDiff
                              ? 'bg-slate-800 hover:bg-slate-700 text-emerald-400 border border-slate-700'
                              : 'text-slate-400 hover:bg-slate-800'
                          }`}
                        >
                          <Eye className="h-3.5 w-3.5" />
                          <span>{hasDiff ? 'View Diff' : 'Info'}</span>
                        </button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {/* Pagination Controls */}
          {meta && meta.last_page > 1 && (
            <div className="p-3 border-t border-inherit flex items-center justify-between text-xs text-slate-400">
              <span>
                Page {meta.current_page} of {meta.last_page}
              </span>
              <div className="flex items-center gap-2">
                <button
                  disabled={page <= 1}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  className="px-2.5 py-1 rounded-lg border border-slate-700 disabled:opacity-40 hover:bg-slate-800"
                >
                  Previous
                </button>
                <button
                  disabled={page >= meta.last_page}
                  onClick={() => setPage((p) => p + 1)}
                  className="px-2.5 py-1 rounded-lg border border-slate-700 disabled:opacity-40 hover:bg-slate-800"
                >
                  Next
                </button>
              </div>
            </div>
          )}
        </div>
      )}

      {/* DIFF INSPECTION MODAL */}
      {selectedLog && (
        <div className="fixed inset-0 bg-black/75 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div
            className={`w-full max-w-3xl rounded-2xl border shadow-2xl overflow-hidden p-6 max-h-[85vh] flex flex-col ${
              theme === 'dark' ? 'bg-slate-900 border-slate-800 text-slate-100' : 'bg-white border-slate-200 text-slate-800'
            }`}
          >
            {/* Modal Header */}
            <div className="flex items-center justify-between border-b border-inherit pb-3 mb-4">
              <div className="flex items-center gap-2">
                <ShieldCheck className="h-5 w-5 text-emerald-400" />
                <div>
                  <h3 className="font-bold text-sm">
                    Audit Log Entry: #{selectedLog.id} ({selectedLog.action})
                  </h3>
                  <p className="text-[11px] font-mono text-slate-400">
                    UUID: {selectedLog.uuid}
                  </p>
                </div>
              </div>
              <button
                onClick={() => setSelectedLog(null)}
                className="p-1 rounded-lg hover:bg-slate-800 text-slate-400 hover:text-slate-200"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            {/* Modal Body */}
            <div className="flex-1 overflow-y-auto space-y-4 pr-1 text-xs">
              {/* Telemetry info */}
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 p-3 rounded-xl bg-slate-950/40 border border-slate-800">
                <div>
                  <span className="text-[10px] text-slate-500 block">Actor</span>
                  <span className="font-medium text-slate-200">
                    {selectedLog.user_name || selectedLog.user?.name || 'System'}
                  </span>
                </div>
                <div>
                  <span className="text-[10px] text-slate-500 block">IP Address</span>
                  <span className="font-mono text-slate-200">{selectedLog.ip_address || '—'}</span>
                </div>
                <div>
                  <span className="text-[10px] text-slate-500 block">Method & Route</span>
                  <span className="font-mono text-slate-200">
                    {selectedLog.method || 'POST'} {selectedLog.url || '—'}
                  </span>
                </div>
                <div>
                  <span className="text-[10px] text-slate-500 block">Created At</span>
                  <span className="font-mono text-slate-200">
                    {new Date(selectedLog.created_at).toLocaleString()}
                  </span>
                </div>
              </div>

              {/* Side-by-Side Diff */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                {/* Old Values */}
                <div className="rounded-xl border border-slate-800 p-3 bg-slate-950/60">
                  <span className="text-xs font-semibold text-rose-400 flex items-center gap-1.5 mb-2">
                    <Database className="h-3.5 w-3.5" />
                    Previous State (Before)
                  </span>
                  <pre className="font-mono text-[11px] text-slate-300 p-2.5 rounded-lg bg-slate-900 border border-slate-800/80 overflow-x-auto max-h-60">
                    {selectedLog.old_values && Object.keys(selectedLog.old_values).length > 0
                      ? JSON.stringify(selectedLog.old_values, null, 2)
                      : '// No previous state (New Record Inserted)'}
                  </pre>
                </div>

                {/* New Values */}
                <div className="rounded-xl border border-slate-800 p-3 bg-slate-950/60">
                  <span className="text-xs font-semibold text-emerald-400 flex items-center gap-1.5 mb-2">
                    <Database className="h-3.5 w-3.5" />
                    Updated State (After)
                  </span>
                  <pre className="font-mono text-[11px] text-slate-300 p-2.5 rounded-lg bg-slate-900 border border-slate-800/80 overflow-x-auto max-h-60">
                    {selectedLog.new_values && Object.keys(selectedLog.new_values).length > 0
                      ? JSON.stringify(selectedLog.new_values, null, 2)
                      : '// Record Deleted / Removed'}
                  </pre>
                </div>
              </div>

              <div className="p-3 rounded-xl border border-emerald-500/20 bg-emerald-500/5 text-emerald-400 text-[11px] flex items-center gap-2">
                <ShieldCheck className="h-4 w-4 shrink-0" />
                <span>
                  This record is write-once and permanently immutable. Eloquent updating and deleting hooks strictly reject modifications.
                </span>
              </div>
            </div>

            {/* Modal Footer */}
            <div className="pt-3 border-t border-inherit flex items-center justify-end mt-4">
              <button
                onClick={() => setSelectedLog(null)}
                className="px-4 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium transition-colors"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
