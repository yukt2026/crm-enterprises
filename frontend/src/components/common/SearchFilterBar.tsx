import React, { useState, useEffect } from 'react';
import { Search, X, Filter, Download, Plus, FileSpreadsheet, FileText } from 'lucide-react';
import { canPerformAction, PermissionCheckUser } from '../../utils/permissions';

interface FilterOption {
  label: string;
  value: string;
}

interface FilterField {
  key: string;
  label: string;
  options: FilterOption[];
}

interface SearchFilterBarProps {
  moduleName: string;
  user: PermissionCheckUser | null;
  searchQuery: string;
  onSearchChange: (query: string) => void;
  filters?: Record<string, string>;
  onFilterChange?: (key: string, value: string) => void;
  onResetFilters?: () => void;
  filterFields?: FilterField[];
  onAddClick?: () => void;
  onExportCsv?: () => void;
  onExportPdf?: () => void;
  theme?: 'light' | 'dark';
}

export default function SearchFilterBar({
  moduleName,
  user,
  searchQuery,
  onSearchChange,
  filters = {},
  onFilterChange,
  onResetFilters,
  filterFields = [],
  onAddClick,
  onExportCsv,
  onExportPdf,
  theme = 'dark',
}: SearchFilterBarProps) {
  const [localSearch, setLocalSearch] = useState(searchQuery);

  // Debounce search query by 300ms
  useEffect(() => {
    const handler = setTimeout(() => {
      if (localSearch !== searchQuery) {
        onSearchChange(localSearch);
      }
    }, 300);
    return () => clearTimeout(handler);
  }, [localSearch]);

  useEffect(() => {
    setLocalSearch(searchQuery);
  }, [searchQuery]);

  const activeFilterCount = Object.values(filters).filter((v) => v !== '' && v !== undefined).length;
  const canCreate = canPerformAction(user, moduleName, 'create');
  const canExport = canPerformAction(user, moduleName, 'export');

  return (
    <div
      className={`p-3.5 rounded-2xl border transition-colors flex flex-col md:flex-row md:items-center justify-between gap-3 ${
        theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200 shadow-xs'
      }`}
    >
      {/* Search & Active Filters */}
      <div className="flex flex-1 items-center flex-wrap gap-2.5">
        {/* Search Input */}
        <div className="relative flex-1 min-w-[220px] max-w-md">
          <Search className="absolute left-3.5 top-2.5 h-4 w-4 text-slate-400" />
          <input
            type="text"
            placeholder={`Search ${moduleName}...`}
            value={localSearch}
            onChange={(e) => setLocalSearch(e.target.value)}
            className={`w-full pl-10 pr-9 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors ${
              theme === 'dark'
                ? 'bg-slate-800/80 border-slate-700 text-slate-100 placeholder-slate-500'
                : 'bg-slate-50 border-slate-200 text-slate-800 placeholder-slate-400'
            }`}
          />
          {localSearch && (
            <button
              onClick={() => {
                setLocalSearch('');
                onSearchChange('');
              }}
              className="absolute right-3 top-2.5 text-slate-400 hover:text-slate-200"
            >
              <X className="h-4 w-4" />
            </button>
          )}
        </div>

        {/* Dynamic Filters */}
        {filterFields.map((field) => (
          <select
            key={field.key}
            value={filters[field.key] || ''}
            onChange={(e) => onFilterChange && onFilterChange(field.key, e.target.value)}
            className={`py-2 px-3 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors ${
              theme === 'dark'
                ? 'bg-slate-800/80 border-slate-700 text-slate-200'
                : 'bg-slate-50 border-slate-200 text-slate-700'
            }`}
          >
            <option value="">{field.label}: All</option>
            {field.options.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
          </select>
        ))}

        {/* Reset Filters Pill */}
        {activeFilterCount > 0 && onResetFilters && (
          <button
            onClick={onResetFilters}
            className="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl text-xs font-medium text-rose-400 hover:bg-rose-500/10 border border-rose-500/20 transition-colors"
          >
            <X className="h-3.5 w-3.5" />
            <span>Reset ({activeFilterCount})</span>
          </button>
        )}
      </div>

      {/* Action Buttons: Add & Export */}
      <div className="flex items-center gap-2 shrink-0">
        {canExport && onExportCsv && (
          <button
            onClick={onExportCsv}
            title="Export data as CSV spreadsheet"
            className={`flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-medium border transition-colors ${
              theme === 'dark'
                ? 'bg-slate-800/80 border-slate-700 text-slate-300 hover:bg-slate-700'
                : 'bg-slate-100 border-slate-200 text-slate-700 hover:bg-slate-200'
            }`}
          >
            <FileSpreadsheet className="h-3.5 w-3.5 text-emerald-400" />
            <span className="hidden sm:inline">Export CSV</span>
          </button>
        )}

        {canExport && onExportPdf && (
          <button
            onClick={onExportPdf}
            title="Export summary document as PDF"
            className={`flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-medium border transition-colors ${
              theme === 'dark'
                ? 'bg-slate-800/80 border-slate-700 text-slate-300 hover:bg-slate-700'
                : 'bg-slate-100 border-slate-200 text-slate-700 hover:bg-slate-200'
            }`}
          >
            <FileText className="h-3.5 w-3.5 text-rose-400" />
            <span className="hidden sm:inline">PDF</span>
          </button>
        )}

        {canCreate && onAddClick && (
          <button
            onClick={onAddClick}
            className="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white shadow-lg shadow-emerald-600/20 transition-all"
          >
            <Plus className="h-4 w-4" />
            <span>New {moduleName.slice(0, -1) || 'Record'}</span>
          </button>
        )}
      </div>
    </div>
  );
}
