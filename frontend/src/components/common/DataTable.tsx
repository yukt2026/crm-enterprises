import React from 'react';
import {
  ChevronLeft,
  ChevronRight,
  ArrowUpDown,
  ArrowUp,
  ArrowDown,
  Inbox,
  AlertCircle,
  RefreshCw,
  MoreVertical,
  Trash2,
  Edit,
  Eye
} from 'lucide-react';

export interface ColumnDef<T = any> {
  key: string;
  label: string;
  sortable?: boolean;
  className?: string;
  render?: (row: T, index: number) => React.ReactNode;
}

interface DataTableProps<T = any> {
  columns: ColumnDef<T>[];
  data: T[];
  isLoading?: boolean;
  isError?: boolean;
  errorMessage?: string;
  onRetry?: () => void;
  // Sorting
  sortBy?: string;
  sortOrder?: 'asc' | 'desc';
  onSort?: (key: string) => void;
  // Pagination
  currentPage?: number;
  totalPages?: number;
  totalRecords?: number;
  perPage?: number;
  onPageChange?: (page: number) => void;
  onPerPageChange?: (limit: number) => void;
  // Selection
  selectedIds?: (string | number)[];
  onToggleSelect?: (id: string | number) => void;
  onToggleSelectAll?: () => void;
  bulkActions?: React.ReactNode;
  // Empty State
  emptyTitle?: string;
  emptyDescription?: string;
  onEmptyAction?: () => void;
  emptyActionLabel?: string;
  // Row Actions
  onRowClick?: (row: T) => void;
  theme?: 'light' | 'dark';
}

export default function DataTable<T extends { id?: string | number }>({
  columns,
  data,
  isLoading = false,
  isError = false,
  errorMessage,
  onRetry,
  sortBy,
  sortOrder = 'asc',
  onSort,
  currentPage = 1,
  totalPages = 1,
  totalRecords = 0,
  perPage = 10,
  onPageChange,
  onPerPageChange,
  selectedIds = [],
  onToggleSelect,
  onToggleSelectAll,
  bulkActions,
  emptyTitle = 'No records found',
  emptyDescription = 'There are no records matching your current filter criteria.',
  onEmptyAction,
  emptyActionLabel = 'Create New Record',
  onRowClick,
  theme = 'dark',
}: DataTableProps<T>) {
  const allSelected = data.length > 0 && selectedIds.length === data.length;
  const isIndeterminate = selectedIds.length > 0 && selectedIds.length < data.length;

  return (
    <div
      className={`rounded-2xl border overflow-hidden flex flex-col transition-colors ${
        theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200 shadow-xs'
      }`}
    >
      {/* Bulk Actions Header */}
      {selectedIds.length > 0 && bulkActions && (
        <div className="bg-emerald-500/10 border-b border-emerald-500/20 px-4 py-2.5 flex items-center justify-between gap-3 text-xs text-emerald-400">
          <span className="font-semibold">
            {selectedIds.length} {selectedIds.length === 1 ? 'item' : 'items'} selected
          </span>
          <div className="flex items-center gap-2">{bulkActions}</div>
        </div>
      )}

      {/* Table Container */}
      <div className="overflow-x-auto min-h-[300px] flex-1">
        <table className="w-full text-left text-xs">
          {/* Table Header */}
          <thead
            className={`border-b border-inherit font-semibold select-none ${
              theme === 'dark' ? 'bg-slate-950/40 text-slate-400' : 'bg-slate-50 text-slate-500'
            }`}
          >
            <tr>
              {onToggleSelect && (
                <th className="py-3 px-4 w-10">
                  <input
                    type="checkbox"
                    checked={allSelected}
                    ref={(el) => {
                      if (el) el.indeterminate = isIndeterminate;
                    }}
                    onChange={() => onToggleSelectAll && onToggleSelectAll()}
                    className="rounded border-slate-700 text-emerald-500 focus:ring-emerald-500 cursor-pointer"
                  />
                </th>
              )}

              {columns.map((col) => {
                const isSorted = sortBy === col.key;
                return (
                  <th
                    key={col.key}
                    onClick={() => col.sortable && onSort && onSort(col.key)}
                    className={`py-3 px-4 uppercase tracking-wider text-[11px] font-semibold transition-colors ${
                      col.sortable ? 'cursor-pointer hover:text-slate-200' : ''
                    } ${col.className || ''}`}
                  >
                    <div className="flex items-center gap-1.5">
                      <span>{col.label}</span>
                      {col.sortable && (
                        <span className="text-slate-500">
                          {isSorted ? (
                            sortOrder === 'desc' ? (
                              <ArrowDown className="h-3.5 w-3.5 text-emerald-400" />
                            ) : (
                              <ArrowUp className="h-3.5 w-3.5 text-emerald-400" />
                            )
                          ) : (
                            <ArrowUpDown className="h-3 w-3 opacity-40 hover:opacity-100" />
                          )}
                        </span>
                      )}
                    </div>
                  </th>
                );
              })}
            </tr>
          </thead>

          {/* Table Body */}
          <tbody className="divide-y divide-inherit">
            {isLoading ? (
              // Skeleton loading rows
              Array.from({ length: perPage > 8 ? 8 : perPage }).map((_, rIdx) => (
                <tr key={rIdx} className="animate-pulse">
                  {onToggleSelect && (
                    <td className="py-3.5 px-4">
                      <div className="h-4 w-4 rounded bg-slate-800/60" />
                    </td>
                  )}
                  {columns.map((col, cIdx) => (
                    <td key={cIdx} className="py-3.5 px-4">
                      <div
                        className="h-3.5 rounded bg-slate-800/60"
                        style={{ width: `${Math.floor(40 + (cIdx * 17) % 55)}%` }}
                      />
                    </td>
                  ))}
                </tr>
              ))
            ) : isError ? (
              // Error state
              <tr>
                <td colSpan={columns.length + (onToggleSelect ? 1 : 0)} className="py-12 text-center">
                  <div className="flex flex-col items-center justify-center gap-3">
                    <div className="h-10 w-10 rounded-full bg-rose-500/15 text-rose-400 flex items-center justify-center">
                      <AlertCircle className="h-5 w-5" />
                    </div>
                    <div className="max-w-md">
                      <p className="font-semibold text-sm text-slate-200">Failed to load data</p>
                      <p className="text-xs text-slate-400 mt-1">
                        {errorMessage || 'An error occurred while fetching module records from Laravel backend.'}
                      </p>
                    </div>
                    {onRetry && (
                      <button
                        onClick={onRetry}
                        className="mt-2 px-3.5 py-1.5 rounded-xl text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 flex items-center gap-1.5 transition-colors"
                      >
                        <RefreshCw className="h-3.5 w-3.5" />
                        <span>Retry</span>
                      </button>
                    )}
                  </div>
                </td>
              </tr>
            ) : data.length === 0 ? (
              // Empty state
              <tr>
                <td colSpan={columns.length + (onToggleSelect ? 1 : 0)} className="py-14 text-center">
                  <div className="flex flex-col items-center justify-center gap-3">
                    <div className="h-12 w-12 rounded-2xl bg-slate-800/50 text-slate-400 flex items-center justify-center border border-slate-700/50">
                      <Inbox className="h-6 w-6" />
                    </div>
                    <div className="max-w-sm">
                      <h4 className="font-bold text-sm text-slate-200">{emptyTitle}</h4>
                      <p className="text-xs text-slate-400 mt-1">{emptyDescription}</p>
                    </div>
                    {onEmptyAction && (
                      <button
                        onClick={onEmptyAction}
                        className="mt-2 px-4 py-2 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white transition-colors shadow-lg shadow-emerald-600/20"
                      >
                        {emptyActionLabel}
                      </button>
                    )}
                  </div>
                </td>
              </tr>
            ) : (
              // Data rows
              data.map((row, index) => {
                const isSelected = row.id !== undefined && selectedIds.includes(row.id);
                return (
                  <tr
                    key={row.id ?? index}
                    onClick={() => onRowClick && onRowClick(row)}
                    className={`transition-colors ${
                      isSelected
                        ? 'bg-emerald-500/10'
                        : theme === 'dark'
                        ? 'hover:bg-slate-800/40 text-slate-200'
                        : 'hover:bg-slate-50 text-slate-700'
                    } ${onRowClick ? 'cursor-pointer' : ''}`}
                  >
                    {onToggleSelect && (
                      <td
                        className="py-3 px-4 w-10"
                        onClick={(e) => {
                          e.stopPropagation();
                          if (row.id !== undefined) onToggleSelect(row.id);
                        }}
                      >
                        <input
                          type="checkbox"
                          checked={isSelected}
                          onChange={() => {}}
                          className="rounded border-slate-700 text-emerald-500 focus:ring-emerald-500 cursor-pointer"
                        />
                      </td>
                    )}

                    {columns.map((col) => (
                      <td key={col.key} className={`py-3 px-4 ${col.className || ''}`}>
                        {col.render ? col.render(row, index) : (row as any)[col.key] ?? '—'}
                      </td>
                    ))}
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>

      {/* Pagination Footer */}
      <div
        className={`px-4 py-3 border-t border-inherit flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs ${
          theme === 'dark' ? 'bg-slate-950/40 text-slate-400' : 'bg-slate-50 text-slate-500'
        }`}
      >
        <div className="flex items-center gap-3">
          <span>
            Showing page <strong className="text-slate-200">{currentPage}</strong> of{' '}
            <strong className="text-slate-200">{totalPages || 1}</strong> ({totalRecords} records)
          </span>

          {onPerPageChange && (
            <div className="flex items-center gap-1.5 ml-2">
              <span>Per page:</span>
              <select
                value={perPage}
                onChange={(e) => onPerPageChange(Number(e.target.value))}
                className={`py-1 px-2 rounded-lg border text-xs ${
                  theme === 'dark' ? 'bg-slate-900 border-slate-700 text-slate-200' : 'bg-white border-slate-200'
                }`}
              >
                <option value={10}>10</option>
                <option value={25}>25</option>
                <option value={50}>50</option>
                <option value={100}>100</option>
              </select>
            </div>
          )}
        </div>

        <div className="flex items-center gap-2 self-end sm:self-auto">
          <button
            onClick={() => onPageChange && onPageChange(currentPage - 1)}
            disabled={currentPage <= 1 || isLoading}
            className="p-1.5 rounded-lg border border-inherit hover:bg-slate-800 disabled:opacity-40 disabled:hover:bg-transparent transition-colors"
          >
            <ChevronLeft className="h-4 w-4" />
          </button>
          <span className="font-mono px-2">{currentPage}</span>
          <button
            onClick={() => onPageChange && onPageChange(currentPage + 1)}
            disabled={currentPage >= totalPages || isLoading}
            className="p-1.5 rounded-lg border border-inherit hover:bg-slate-800 disabled:opacity-40 disabled:hover:bg-transparent transition-colors"
          >
            <ChevronRight className="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>
  );
}
