import React, { useState } from 'react';
import {
  FileSpreadsheet,
  Play,
  Save,
  Download,
  Calendar,
  Filter,
  Layers,
  ArrowUpDown,
  Plus,
  Trash2,
  CheckCircle2,
  Sparkles,
} from 'lucide-react';
import {
  useCustomReportCatalog,
  usePreviewCustomReport,
  useSaveCustomReport,
} from '../../hooks/useAnalyticsBI';
import { api } from '../../api/client';

export const CustomReportBuilderTab: React.FC = () => {
  const { data: catalog, isLoading: catalogLoading } = useCustomReportCatalog();
  const previewMutation = usePreviewCustomReport();
  const saveMutation = useSaveCustomReport();

  const [selectedModule, setSelectedModule] = useState<string>('deals');
  const [selectedColumns, setSelectedColumns] = useState<string[]>(['name', 'value', 'stage']);
  const [groupBy, setGroupBy] = useState<string>('');
  const [sortBy, setSortBy] = useState<string>('id');
  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc');
  const [chartType, setChartType] = useState<string>('table');

  const [saveTitle, setSaveTitle] = useState<string>('');
  const [isScheduled, setIsScheduled] = useState<boolean>(false);
  const [scheduleFreq, setScheduleFreq] = useState<'daily' | 'weekly' | 'monthly'>('weekly');
  const [scheduleEmail, setScheduleEmail] = useState<string>('');
  const [saveSuccessMessage, setSaveSuccessMessage] = useState<string | null>(null);

  const currentModuleConfig = catalog ? catalog[selectedModule] : null;

  const handleToggleColumn = (colKey: string) => {
    setSelectedColumns((prev) =>
      prev.includes(colKey) ? prev.filter((k) => k !== colKey) : [...prev, colKey]
    );
  };

  const handlePreview = () => {
    previewMutation.mutate({
      module: selectedModule,
      selected_columns: selectedColumns,
      group_by: groupBy || undefined,
      sort_by: sortBy,
      sort_direction: sortDirection,
      chart_type: chartType,
      aggregations: {
        sum: ['value', 'total_amount', 'amount'].filter((f) => selectedColumns.includes(f)),
        avg: ['value', 'score', 'progress'].filter((f) => selectedColumns.includes(f)),
      },
    });
  };

  const handleSaveReport = () => {
    if (!saveTitle) return;
    saveMutation.mutate(
      {
        title: saveTitle,
        module: selectedModule,
        selected_columns: selectedColumns,
        group_by: groupBy || undefined,
        sort_by: sortBy,
        sort_direction: sortDirection,
        chart_type: chartType,
        is_scheduled: isScheduled,
        schedule_frequency: isScheduled ? scheduleFreq : undefined,
        schedule_recipients: isScheduled && scheduleEmail ? [scheduleEmail] : undefined,
      },
      {
        onSuccess: () => {
          setSaveSuccessMessage(`Report [${saveTitle}] saved successfully!`);
          setTimeout(() => setSaveSuccessMessage(null), 4000);
          setSaveTitle('');
        },
      }
    );
  };

  const handleExportCsv = async () => {
    try {
      const csv = await api.request<string>('/crm/analytics/report-builder/export', {
        method: 'POST',
        headers: { Accept: 'text/csv' },
        body: JSON.stringify({
          module: selectedModule,
          selected_columns: selectedColumns,
          group_by: groupBy || undefined,
          sort_by: sortBy,
          sort_direction: sortDirection,
        }),
      });

      const blob = new Blob([typeof csv === 'string' ? csv : (csv as any).data || ''], {
        type: 'text/csv;charset=utf-8;',
      });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `report_${selectedModule}_${Date.now()}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    } catch (err: any) {
      console.error('Export failed:', err);
    }
  };

  if (catalogLoading) {
    return <div className="py-12 text-center text-slate-500">Loading module catalog...</div>;
  }

  const previewData = previewMutation.data?.data;

  return (
    <div className="space-y-6">
      {/* Configuration Header */}
      <div className="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-5">
        <div>
          <h2 className="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <FileSpreadsheet className="w-5 h-5 text-indigo-600" />
            Custom Drag & Drop Report Builder
          </h2>
          <p className="text-xs text-slate-500">
            Build cross-domain custom reports across 8 CRM modules with live previews, aggregations & export schedules.
          </p>
        </div>

        {saveSuccessMessage && (
          <div className="p-3 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 rounded-lg text-xs font-semibold flex items-center gap-2">
            <CheckCircle2 className="w-4 h-4 text-emerald-600" />
            {saveSuccessMessage}
          </div>
        )}

        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          {/* Module Selector */}
          <div>
            <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              1. Select Module
            </label>
            <select
              value={selectedModule}
              onChange={(e) => {
                setSelectedModule(e.target.value);
                const mod = catalog?.[e.target.value];
                if (mod) {
                  setSelectedColumns(mod.fields.slice(0, 4).map((f) => f.key));
                }
              }}
              className="w-full text-xs px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:border-indigo-600"
            >
              {catalog &&
                Object.entries(catalog).map(([key, item]) => (
                  <option key={key} value={key}>
                    {item.name}
                  </option>
                ))}
            </select>
          </div>

          {/* Group By */}
          <div>
            <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              2. Group Data By
            </label>
            <select
              value={groupBy}
              onChange={(e) => setGroupBy(e.target.value)}
              className="w-full text-xs px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none"
            >
              <option value="">No Grouping (Flat Records)</option>
              {currentModuleConfig?.fields.map((f) => (
                <option key={f.key} value={f.key}>
                  {f.label}
                </option>
              ))}
            </select>
          </div>

          {/* Sort By & Direction */}
          <div>
            <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              3. Sort Order
            </label>
            <div className="flex gap-2">
              <select
                value={sortBy}
                onChange={(e) => setSortBy(e.target.value)}
                className="w-2/3 text-xs px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none"
              >
                {currentModuleConfig?.fields.map((f) => (
                  <option key={f.key} value={f.key}>
                    {f.label}
                  </option>
                ))}
              </select>
              <button
                type="button"
                onClick={() => setSortDirection((d) => (d === 'asc' ? 'desc' : 'asc'))}
                className="w-1/3 px-2 py-2 border border-slate-300 dark:border-slate-700 rounded-lg text-xs font-bold uppercase text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
              >
                {sortDirection}
              </button>
            </div>
          </div>

          {/* Visualization Type */}
          <div>
            <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              4. Display Mode
            </label>
            <select
              value={chartType}
              onChange={(e) => setChartType(e.target.value)}
              className="w-full text-xs px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none"
            >
              <option value="table">Data Table</option>
              <option value="bar">Bar Chart</option>
              <option value="pie">Pie Distribution</option>
            </select>
          </div>
        </div>

        {/* Available Fields Checklist */}
        <div>
          <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">
            Selected Fields & Metrics
          </label>
          <div className="flex flex-wrap gap-2">
            {currentModuleConfig?.fields.map((f) => {
              const isSelected = selectedColumns.includes(f.key);
              return (
                <button
                  key={f.key}
                  type="button"
                  onClick={() => handleToggleColumn(f.key)}
                  className={`text-xs px-3 py-1.5 rounded-lg border transition-all ${
                    isSelected
                      ? 'bg-indigo-600 border-indigo-600 text-white font-semibold'
                      : 'bg-slate-50 dark:bg-slate-800/60 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-slate-400'
                  }`}
                >
                  {isSelected ? '✓ ' : '+ '}
                  {f.label}
                </button>
              );
            })}
          </div>
        </div>

        {/* Action Buttons */}
        <div className="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
          <div className="flex items-center gap-2">
            <button
              onClick={handlePreview}
              disabled={previewMutation.isPending}
              className="flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow disabled:opacity-50"
            >
              <Play className="w-3.5 h-3.5" />
              {previewMutation.isPending ? 'Generating Preview...' : 'Run & Preview Report'}
            </button>

            <button
              onClick={handleExportCsv}
              className="flex items-center gap-1.5 px-3 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg hover:bg-slate-50"
            >
              <Download className="w-3.5 h-3.5" /> Export CSV
            </button>
          </div>

          {/* Save & Schedule Controls */}
          <div className="flex items-center gap-2">
            <input
              type="text"
              placeholder="Report Name to Save"
              value={saveTitle}
              onChange={(e) => setSaveTitle(e.target.value)}
              className="text-xs px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-lg dark:bg-slate-800 dark:text-white focus:outline-none w-48"
            />
            <button
              onClick={handleSaveReport}
              disabled={!saveTitle || saveMutation.isPending}
              className="flex items-center gap-1 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow disabled:opacity-50"
            >
              <Save className="w-3.5 h-3.5" /> Save
            </button>
          </div>
        </div>
      </div>

      {/* LIVE PREVIEW RESULTS */}
      {previewData && (
        <div className="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <span className="w-2.5 h-2.5 rounded-full bg-indigo-600" />
              <h3 className="text-sm font-bold text-slate-900 dark:text-white">
                Live Query Result ({previewData.total_records} Records Matched)
              </h3>
            </div>

            {/* Aggregations Banner */}
            {Object.keys(previewData.aggregations || {}).length > 0 && (
              <div className="flex items-center gap-3 text-xs bg-indigo-50 dark:bg-indigo-950/40 px-3 py-1 rounded-lg border border-indigo-200 dark:border-indigo-900 text-indigo-900 dark:text-indigo-300 font-semibold">
                {Object.entries(previewData.aggregations).map(([k, v]) => (
                  <span key={k}>
                    {k.toUpperCase()}: <span className="font-mono text-slate-900 dark:text-white">₹{v.toLocaleString('en-IN')}</span>
                  </span>
                ))}
              </div>
            )}
          </div>

          {/* Grouped View vs Flat Table */}
          {previewData.grouped && previewData.grouped.length > 0 ? (
            <div className="overflow-x-auto">
              <table className="w-full text-xs text-left">
                <thead>
                  <tr className="border-b border-slate-200 dark:border-slate-800 text-slate-500 font-semibold">
                    <th className="pb-2">Group ({groupBy})</th>
                    <th className="pb-2">Count</th>
                    <th className="pb-2 text-right">Aggregated Sum</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {previewData.grouped.map((g, idx) => (
                    <tr key={idx} className="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                      <td className="py-2.5 font-bold text-slate-900 dark:text-white">{g.group_key || 'Empty'}</td>
                      <td className="py-2.5 text-slate-600 dark:text-slate-300">{g.count}</td>
                      <td className="py-2.5 text-right font-mono font-bold text-emerald-600">
                        ₹{g.total_value.toLocaleString('en-IN')}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-xs text-left">
                <thead>
                  <tr className="border-b border-slate-200 dark:border-slate-800 text-slate-500 font-semibold">
                    {previewData.columns.map((c) => (
                      <th key={c} className="pb-2 uppercase tracking-wider">
                        {c.replace('_', ' ')}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800 font-mono">
                  {previewData.rows.slice(0, 15).map((row, idx) => (
                    <tr key={idx} className="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                      {previewData.columns.map((col) => (
                        <td key={col} className="py-2.5 text-slate-700 dark:text-slate-300">
                          {typeof row[col] === 'number' && col.includes('amount') || col.includes('value')
                            ? `₹${row[col].toLocaleString('en-IN')}`
                            : String(row[col] ?? '-')}
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}
    </div>
  );
};
