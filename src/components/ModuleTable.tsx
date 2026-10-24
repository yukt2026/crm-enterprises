import React, { useState, useEffect } from 'react';
import { useCRMStore } from '../store/crmStore';
import {
  Search,
  Filter,
  Download,
  Upload,
  Trash2,
  ChevronLeft,
  ChevronRight,
  ArrowUpDown,
  RefreshCw,
  Plus,
  FileText,
  LayoutGrid,
  List
} from 'lucide-react';

interface Column {
  key: string;
  label: string;
  render?: (val: any, row: any) => React.ReactNode;
}

interface ModuleTableProps {
  module: string;
  columns: Column[];
  filtersConfig?: {
    key: string;
    label: string;
    options: { value: string; label: string }[];
  }[];
  onAddClick?: () => void;
  onEditClick?: (row: any) => void;
  onDeleteClick?: (row: any) => void;
  customActions?: (row: any) => React.ReactNode;
}

export default function ModuleTable({
  module,
  columns,
  filtersConfig = [],
  onAddClick,
  onEditClick,
  onDeleteClick,
  customActions
}: ModuleTableProps) {
  const {
    user,
    moduleData,
    fetchModuleRecords,
    bulkDeleteRecords,
    exportBackup,
    importBackup,
    isLoading,
    totalRecords,
    currentPage,
    totalPages,
    activeFilters,
    setFilters,
    clearFilters,
    searchQuery,
    setSearchQuery,
    theme
  } = useCRMStore();

  const [selectedIds, setSelectedIds] = useState<string[]>([]);
  const [localSearch, setLocalSearch] = useState(searchQuery);
  const [sortBy, setSortBy] = useState('createdAt');
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('desc');
  const [isExporting, setIsExporting] = useState(false);
  const [viewMode, setViewMode] = useState<'card' | 'table'>('table');

  // Pagination query bug fix: track last parameters to reset page
  const [lastModule, setLastModule] = useState(module);
  const [lastSearch, setLastSearch] = useState(searchQuery);
  const [lastFilters, setLastFilters] = useState(activeFilters);

  // Trigger search on debounce
  useEffect(() => {
    const handler = setTimeout(() => {
      setSearchQuery(localSearch);
    }, 400);
    return () => clearTimeout(handler);
  }, [localSearch]);

  // Fetch data when module, page, filters or search query changes
  useEffect(() => {
    let pageToFetch = currentPage;
    if (module !== lastModule || searchQuery !== lastSearch || JSON.stringify(activeFilters) !== JSON.stringify(lastFilters)) {
      pageToFetch = 1;
      setLastModule(module);
      setLastSearch(searchQuery);
      setLastFilters(activeFilters);
    }
    fetchModuleRecords(module, pageToFetch);
    setSelectedIds([]);
  }, [module, currentPage, activeFilters, searchQuery]);

  const handleExportPDF = async () => {
    setIsExporting(true);
    try {
      const token = localStorage.getItem('crm_token');
      const headers: Record<string, string> = {
        'Authorization': `Bearer ${token}`
      };
      if (user?.id) {
        headers['X-User-Id'] = user.id;
      }
      const response = await fetch(`/api/crm/export/${module}?format=json`, {
        headers
      });
      if (!response.ok) throw new Error('Export failed');
      const data = await response.json();
      
      const { generatePDFReport } = await import('../lib/exportUtils');
      await generatePDFReport(module, columns, data);
    } catch (err) {
      console.error(err);
      alert('Failed to generate PDF Report.');
    } finally {
      setIsExporting(false);
    }
  };

  const handleExportCSV = async () => {
    setIsExporting(true);
    try {
      const token = localStorage.getItem('crm_token');
      const headers: Record<string, string> = {
        'Authorization': `Bearer ${token}`
      };
      if (user?.id) {
        headers['X-User-Id'] = user.id;
      }
      const response = await fetch(`/api/crm/export/${module}?format=json`, {
        headers
      });
      if (!response.ok) throw new Error('Export failed');
      const data = await response.json();
      
      const { generateCSVReport } = await import('../lib/exportUtils');
      generateCSVReport(module, columns, data);
    } catch (err) {
      console.error(err);
      alert('Failed to export CSV Report.');
    } finally {
      setIsExporting(false);
    }
  };

  const handlePageChange = (newPage: number) => {
    if (newPage >= 1 && newPage <= totalPages) {
      fetchModuleRecords(module, newPage);
    }
  };

  const handleSelectAll = (e: React.ChangeEvent<HTMLInputElement>) => {
    const data = moduleData[module as keyof typeof moduleData] || [];
    if (e.target.checked) {
      setSelectedIds(data.map((item: any) => item.id));
    } else {
      setSelectedIds([]);
    }
  };

  const handleSelectRow = (id: string, checked: boolean) => {
    if (checked) {
      setSelectedIds([...selectedIds, id]);
    } else {
      setSelectedIds(selectedIds.filter((x) => x !== id));
    }
  };

  const handleBulkDelete = async () => {
    if (window.confirm(`Are you sure you want to delete the ${selectedIds.length} selected records?`)) {
      const success = await bulkDeleteRecords(module, selectedIds);
      if (success) {
        setSelectedIds([]);
      }
    }
  };

  const handleImport = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async (event) => {
      try {
        const rawJson = JSON.parse(event.target?.result as string);
        if (Array.isArray(rawJson)) {
          const success = await importBackup(module, rawJson);
          if (success) {
            e.target.value = ''; // Reset input
          }
        } else {
          alert('Invalid file format. Must be a JSON array.');
        }
      } catch (err) {
        alert('Failed to parse JSON file.');
      }
    };
    reader.readAsText(file);
  };

  const dataList = moduleData[module as keyof typeof moduleData] || [];

  return (
    <div className={`rounded-2xl border transition ${theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'} shadow-sm p-4`}>
      
      {/* TABLE CONTROLS TOOLBAR */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
        {/* Search Input */}
        <div className="relative flex-1 max-w-md">
          <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input
            type="text"
            placeholder={`Search across ${module}...`}
            value={localSearch}
            onChange={(e) => setLocalSearch(e.target.value)}
            className={`w-full pl-9 pr-4 py-2 text-sm rounded-xl border focus:outline-none focus:ring-2 focus:ring-emerald-500 transition ${
              theme === 'dark' ? 'bg-slate-950 border-slate-800 text-slate-100 placeholder-slate-500' : 'bg-slate-50 border-slate-200 placeholder-slate-500 text-slate-800'
            }`}
          />
        </div>

        {/* Action Controls */}
        <div className="flex flex-wrap items-center gap-2">
          {/* Filters dropdowns */}
          {filtersConfig.map((filter) => (
            <select
              key={filter.key}
              value={activeFilters[filter.key] || ''}
              onChange={(e) => {
                const nextFilters = { ...activeFilters, [filter.key]: e.target.value };
                if (!e.target.value) delete nextFilters[filter.key];
                setFilters(nextFilters);
              }}
              className={`text-xs px-3 py-2 rounded-xl border focus:outline-none ${
                theme === 'dark' ? 'bg-slate-950 border-slate-800 text-slate-200' : 'bg-slate-50 border-slate-200 text-slate-800'
              }`}
            >
              <option value="">Filter {filter.label}</option>
              {filter.options.map((opt) => (
                <option key={opt.value} value={opt.value}>
                  {opt.label}
                </option>
              ))}
            </select>
          ))}

          {activeFilters && Object.keys(activeFilters).length > 0 && (
            <button
              onClick={clearFilters}
              className="text-xs text-rose-500 hover:underline font-semibold"
            >
              Clear Filters
            </button>
          )}

          <div className="h-6 w-[1px] bg-slate-800 mx-1 hidden sm:block"></div>

          {/* View Mode Switcher */}
          <div className={`flex items-center border rounded-xl overflow-hidden ${theme === 'dark' ? 'border-slate-800 bg-slate-950/20' : 'border-slate-200 bg-slate-50'}`}>
            <button
              onClick={() => setViewMode('table')}
              className={`p-2 transition-colors flex items-center gap-1.5 text-xs font-semibold ${
                viewMode === 'table'
                  ? 'bg-emerald-600 text-white'
                  : theme === 'dark' ? 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/40' : 'text-slate-600 hover:text-slate-800 hover:bg-slate-200/50'
              }`}
              title="Table View"
            >
              <List className="h-3.5 w-3.5" />
              <span className="hidden sm:inline">Table</span>
            </button>
            <button
              onClick={() => setViewMode('card')}
              className={`p-2 transition-colors flex items-center gap-1.5 text-xs font-semibold ${
                viewMode === 'card'
                  ? 'bg-emerald-600 text-white'
                  : theme === 'dark' ? 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/40' : 'text-slate-600 hover:text-slate-800 hover:bg-slate-200/50'
              }`}
              title="Grid Cards View"
            >
              <LayoutGrid className="h-3.5 w-3.5" />
              <span className="hidden sm:inline">Grid</span>
            </button>
          </div>

          <div className="h-6 w-[1px] bg-slate-800 mx-1 hidden sm:block"></div>

          {/* Import / Export / Add buttons */}
          <button
            onClick={handleExportCSV}
            disabled={isExporting}
            className={`p-2 rounded-xl border hover:opacity-80 transition flex items-center gap-1.5 text-xs ${
              theme === 'dark' ? 'bg-slate-950 border-slate-800 text-slate-300' : 'bg-slate-50 border-slate-200 text-slate-700'
            } ${isExporting ? 'cursor-not-allowed opacity-50' : ''}`}
            title="Export to CSV"
          >
            {isExporting ? (
              <RefreshCw className="h-3.5 w-3.5 animate-spin" />
            ) : (
              <Download className="h-3.5 w-3.5" />
            )}
            <span>CSV</span>
          </button>

          <button
            onClick={handleExportPDF}
            disabled={isExporting}
            className={`p-2 rounded-xl border hover:opacity-80 transition flex items-center gap-1.5 text-xs ${
              theme === 'dark' ? 'bg-slate-950 border-slate-800 text-slate-300' : 'bg-slate-50 border-slate-200 text-slate-700'
            } ${isExporting ? 'cursor-not-allowed opacity-50' : ''}`}
            title="Export professional PDF Report"
          >
            {isExporting ? (
              <RefreshCw className="h-3.5 w-3.5 animate-spin" />
            ) : (
              <FileText className="h-3.5 w-3.5 text-emerald-500" />
            )}
            <span>PDF Report</span>
          </button>

          <label
            className={`p-2 rounded-xl border hover:opacity-80 cursor-pointer transition flex items-center gap-1.5 text-xs ${
              theme === 'dark' ? 'bg-slate-950 border-slate-800 text-slate-300' : 'bg-slate-50 border-slate-200 text-slate-700'
            }`}
            title="Import from JSON File"
          >
            <Upload className="h-3.5 w-3.5" />
            <span>Import</span>
            <input type="file" accept=".json" onChange={handleImport} className="hidden" />
          </label>

          {onAddClick && (
            <button
              onClick={onAddClick}
              className="bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-2 rounded-xl font-semibold text-xs flex items-center gap-1.5 shadow-lg shadow-emerald-500/10 transition"
            >
              <Plus className="h-4 w-4" />
              <span>Add Record</span>
            </button>
          )}
        </div>
      </div>

      {/* BULK ACTION PANEL */}
      {selectedIds.length > 0 && (
        <div className="flex items-center justify-between p-3 mb-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-700 dark:text-rose-300">
          <span className="text-xs font-semibold font-mono">
            {selectedIds.length} records selected for bulk operations
          </span>
          <div className="flex items-center gap-3">
            <button
              onClick={() => exportBackup(module, 'json')}
              className="text-xs text-emerald-600 dark:text-emerald-400 font-bold hover:underline"
            >
              Export JSON
            </button>
            <button
              onClick={handleBulkDelete}
              className="bg-rose-600 hover:bg-rose-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5"
            >
              <Trash2 className="h-3.5 w-3.5" />
              <span>Delete Selected</span>
            </button>
          </div>
        </div>
      )}

      {/* THE DATA CONTROLLERS: CARD VIEW OR TABLE VIEW */}
      {viewMode === 'table' ? (
        <div className="overflow-x-auto w-full rounded-xl scrollbar-thin border border-slate-200 dark:border-slate-800">
          <table className="w-full text-left text-sm border-collapse min-w-[800px] lg:min-w-full">
            <thead>
              <tr className={`border-b border-inherit font-medium ${theme === 'dark' ? 'text-slate-400 bg-slate-950/40' : 'text-slate-500 bg-slate-50/50'}`}>
                <th className="p-3 w-10">
                  <input
                    type="checkbox"
                    onChange={handleSelectAll}
                    checked={dataList.length > 0 && selectedIds.length === dataList.length}
                    className="rounded border-slate-700 text-emerald-600 focus:ring-emerald-500 h-4 w-4"
                  />
                </th>
                {columns.map((col) => (
                  <th key={col.key} className="p-3 whitespace-nowrap">
                    <div className="flex items-center gap-1">
                      <span>{col.label}</span>
                    </div>
                  </th>
                ))}
                <th className="p-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {isLoading ? (
                Array.from({ length: 4 }).map((_, rIdx) => (
                  <tr key={rIdx} className="border-b border-inherit last:border-0">
                    <td className="p-3">
                      <div className="h-4 w-4 rounded bg-slate-800 animate-pulse"></div>
                    </td>
                    {columns.map((_, cIdx) => (
                      <td key={cIdx} className="p-3">
                        <div className="h-4 w-28 rounded bg-slate-800 animate-pulse"></div>
                      </td>
                    ))}
                    <td className="p-3 text-right">
                      <div className="h-8 w-16 bg-slate-800 rounded animate-pulse inline-block"></div>
                    </td>
                  </tr>
                ))
              ) : dataList.length === 0 ? (
                <tr>
                  <td colSpan={columns.length + 2} className="p-12 text-center text-slate-400">
                    <div className="flex flex-col items-center justify-center">
                      <Trash2 className="h-10 w-10 text-slate-600 mb-3" />
                      <p className="font-semibold text-sm">No records found matching current query</p>
                      <p className="text-xs text-slate-500 mt-1">Try tweaking filters or adding a new record to seed this module.</p>
                    </div>
                  </td>
                </tr>
              ) : (
                dataList.map((row) => (
                  <tr
                    key={row.id}
                    className={`border-b border-inherit last:border-0 transition hover:bg-slate-800/10`}
                  >
                    <td className="p-3">
                      <input
                        type="checkbox"
                        checked={selectedIds.includes(row.id)}
                        onChange={(e) => handleSelectRow(row.id, e.target.checked)}
                        className="rounded border-slate-700 text-emerald-600 focus:ring-emerald-500 h-4 w-4"
                      />
                    </td>
                    {columns.map((col) => (
                      <td key={col.key} className="p-3 whitespace-nowrap text-xs">
                        {col.render ? (
                          col.render(row[col.key], row)
                        ) : (
                          <span className="font-medium text-slate-700 dark:text-slate-300">
                            {row[col.key] !== undefined ? String(row[col.key]) : '-'}
                          </span>
                        )}
                      </td>
                    ))}
                    <td className="p-3 text-right whitespace-nowrap">
                      <div className="flex items-center justify-end gap-2.5">
                        {customActions && customActions(row)}
                        {onEditClick && (
                          <button
                            onClick={() => onEditClick(row)}
                            className="text-xs font-semibold text-emerald-500 hover:underline"
                          >
                            Edit
                          </button>
                        )}
                        {onDeleteClick && (
                          <button
                            onClick={() => onDeleteClick(row)}
                            className="text-xs font-semibold text-rose-500 hover:underline"
                          >
                            Delete
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
      ) : (
        /* RESPONSIVE GRID CARDS Layout for mobile views */
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {isLoading ? (
            Array.from({ length: 4 }).map((_, rIdx) => (
              <div
                key={rIdx}
                className={`p-4 rounded-xl border animate-pulse space-y-3 ${
                  theme === 'dark' ? 'bg-slate-900/40 border-slate-800' : 'bg-white border-slate-200'
                }`}
              >
                <div className="flex justify-between items-center">
                  <div className="h-4 w-4 rounded bg-slate-800"></div>
                  <div className="h-4 w-12 rounded bg-slate-800"></div>
                </div>
                <div className="h-4 w-3/4 rounded bg-slate-800"></div>
                <div className="space-y-2 pt-2 border-t border-slate-800/30">
                  <div className="h-3 w-1/2 rounded bg-slate-800"></div>
                  <div className="h-3 w-2/3 rounded bg-slate-800"></div>
                </div>
              </div>
            ))
          ) : dataList.length === 0 ? (
            <div className={`col-span-full p-12 text-center rounded-xl border ${
              theme === 'dark' ? 'bg-slate-900/40 border-slate-800 text-slate-400' : 'bg-slate-50 border-slate-200 text-slate-500'
            }`}>
              <div className="flex flex-col items-center justify-center">
                <Trash2 className="h-10 w-10 text-slate-600 mb-3" />
                <p className="font-semibold text-sm">No records found matching current query</p>
                <p className="text-xs text-slate-500 mt-1">Try tweaking filters or adding a new record to seed this module.</p>
              </div>
            </div>
          ) : (
            dataList.map((row) => (
              <div
                key={row.id}
                className={`p-4 rounded-xl border transition hover:shadow-md ${
                  theme === 'dark' ? 'bg-slate-900 border-slate-800 hover:border-slate-700 text-slate-100' : 'bg-white border-slate-200 hover:border-slate-300 text-slate-800'
                }`}
              >
                {/* Card Header */}
                <div className="flex items-center justify-between gap-2 pb-2.5 mb-2.5 border-b border-slate-100 dark:border-slate-800/50">
                  <div className="flex items-center gap-2">
                    <input
                      type="checkbox"
                      checked={selectedIds.includes(row.id)}
                      onChange={(e) => handleSelectRow(row.id, e.target.checked)}
                      className="rounded border-slate-700 text-emerald-600 focus:ring-emerald-500 h-4 w-4"
                    />
                    <span className="text-[10px] font-mono font-semibold text-slate-400 dark:text-slate-500">#{row.id?.substring(0, 8)}</span>
                  </div>
                </div>

                {/* Primary Column Display */}
                {columns.length > 0 && (
                  <div className="mb-3">
                    <span className="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider block mb-0.5">{columns[0].label}</span>
                    <span className="text-sm font-bold tracking-tight text-slate-900 dark:text-slate-100">
                      {columns[0].render ? columns[0].render(row[columns[0].key], row) : (row[columns[0].key] !== undefined ? String(row[columns[0].key]) : '-')}
                    </span>
                  </div>
                )}

                {/* Other Column Details Key-Values */}
                <div className="space-y-2">
                  {columns.slice(1).map((col) => (
                    <div key={col.key} className="flex justify-between items-start gap-4 text-xs py-1.5 border-b border-slate-100 dark:border-slate-800/20 last:border-0 last:pb-0">
                      <span className="text-slate-500 dark:text-slate-400 font-medium shrink-0">{col.label}</span>
                      <span className="font-semibold text-slate-800 dark:text-slate-200 text-right">
                        {col.render ? col.render(row[col.key], row) : (row[col.key] !== undefined ? String(row[col.key]) : '-')}
                      </span>
                    </div>
                  ))}
                </div>

                {/* Card Action Controls */}
                <div className="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/50 flex flex-wrap items-center justify-end gap-2">
                  {customActions && customActions(row)}
                  {onEditClick && (
                    <button
                      onClick={() => onEditClick(row)}
                      className="text-xs font-bold text-emerald-500 hover:text-emerald-400 px-3 py-1.5 rounded-lg bg-emerald-500/5 hover:bg-emerald-500/10 border border-emerald-500/10 transition"
                    >
                      Edit
                    </button>
                  )}
                  {onDeleteClick && (
                    <button
                      onClick={() => onDeleteClick(row)}
                      className="text-xs font-bold text-rose-500 hover:text-rose-400 px-3 py-1.5 rounded-lg bg-rose-500/5 hover:bg-rose-500/10 border border-rose-500/10 transition"
                    >
                      Delete
                    </button>
                  )}
                </div>
              </div>
            ))
          )}
        </div>
      )}

      {/* FOOTER PAGINATION */}
      {!isLoading && dataList.length > 0 && (
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mt-4 pt-4 border-t border-inherit">
          <p className="text-xs text-slate-500 dark:text-slate-400">
            Showing <span className="font-bold font-mono">{(currentPage - 1) * 10 + 1}</span> to{' '}
            <span className="font-bold font-mono">
              {Math.min(currentPage * 10, totalRecords)}
            </span>{' '}
            of <span className="font-bold font-mono">{totalRecords}</span> entries
          </p>
          <div className="flex items-center justify-end gap-1.5">
            <button
              onClick={() => handlePageChange(currentPage - 1)}
              disabled={currentPage === 1}
              className={`p-1.5 rounded-lg border transition ${
                currentPage === 1
                  ? 'text-slate-600 border-inherit cursor-not-allowed opacity-50'
                  : 'hover:bg-emerald-500/10 border-inherit hover:text-emerald-500'
              }`}
            >
              <ChevronLeft className="h-4 w-4" />
            </button>
            {Array.from({ length: totalPages }).map((_, idx) => {
              const p = idx + 1;
              return (
                <button
                  key={p}
                  onClick={() => handlePageChange(p)}
                  className={`px-3 py-1.5 rounded-lg text-xs font-bold transition ${
                    currentPage === p
                      ? 'bg-emerald-600 text-white shadow-md'
                      : 'border border-inherit hover:bg-emerald-500/10'
                  }`}
                >
                  {p}
                </button>
              );
            })}
            <button
              onClick={() => handlePageChange(currentPage + 1)}
              disabled={currentPage === totalPages}
              className={`p-1.5 rounded-lg border transition ${
                currentPage === totalPages
                  ? 'text-slate-600 border-inherit cursor-not-allowed opacity-50'
                  : 'hover:bg-emerald-500/10 border-inherit hover:text-emerald-500'
              }`}
            >
              <ChevronRight className="h-4 w-4" />
            </button>
          </div>
        </div>
      )}

    </div>
  );
}
