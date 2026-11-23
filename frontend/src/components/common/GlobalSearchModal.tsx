import React, { useState, useEffect } from 'react';
import {
  Search,
  X,
  Layers,
  Briefcase,
  Building2,
  Users2,
  FolderKanban,
  HelpCircle,
  CreditCard,
  UserCheck2,
  Calendar,
  ArrowRight,
  Loader2,
  Sparkles,
} from 'lucide-react';
import { useGlobalSearch } from '../../hooks/useSharedServices';
import { useCRMStore } from '../../store/crmStore';

interface GlobalSearchModalProps {
  isOpen: boolean;
  onClose: () => void;
  onNavigate: (module: string) => void;
  theme?: 'light' | 'dark';
}

const MODULE_ICONS: Record<string, React.ElementType> = {
  leads: Layers,
  deals: Briefcase,
  companies: Building2,
  contacts: Users2,
  projects: FolderKanban,
  tickets: HelpCircle,
  invoices: CreditCard,
  employees: UserCheck2,
  meetings: Calendar,
};

const MODULE_COLORS: Record<string, string> = {
  leads: 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20',
  deals: 'text-teal-400 bg-teal-500/10 border-teal-500/20',
  companies: 'text-sky-400 bg-sky-500/10 border-sky-500/20',
  contacts: 'text-indigo-400 bg-indigo-500/10 border-indigo-500/20',
  projects: 'text-amber-400 bg-amber-500/10 border-amber-500/20',
  tickets: 'text-rose-400 bg-rose-500/10 border-rose-500/20',
  invoices: 'text-violet-400 bg-violet-500/10 border-violet-500/20',
  employees: 'text-cyan-400 bg-cyan-500/10 border-cyan-500/20',
  meetings: 'text-fuchsia-400 bg-fuchsia-500/10 border-fuchsia-500/20',
};

export default function GlobalSearchModal({
  isOpen,
  onClose,
  onNavigate,
  theme = 'dark',
}: GlobalSearchModalProps) {
  const [query, setQuery] = useState('');
  const [debouncedQuery, setDebouncedQuery] = useState('');
  const [selectedModule, setSelectedModule] = useState<string | undefined>(undefined);
  const moduleData = useCRMStore((s) => s.moduleData);

  // Debounce input to prevent spamming backend
  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedQuery(query);
    }, 250);
    return () => clearTimeout(timer);
  }, [query]);

  // Backend real-time search hook
  const { data: searchResults = [], isLoading, isFetching } = useGlobalSearch(
    debouncedQuery,
    selectedModule
  );

  // Keyboard shortcut listener for ESC
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isOpen) {
        onClose();
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  // Fallback to local store data when query is short (1-2 chars) or offline
  const lowerQ = query.toLowerCase().trim();
  const localResults = lowerQ.length > 0 && searchResults.length === 0 && !isLoading
    ? [
        ...(moduleData.leads || [])
          .filter((l) => l.name?.toLowerCase().includes(lowerQ) || l.company?.toLowerCase().includes(lowerQ))
          .slice(0, 3)
          .map((l) => ({
            id: String(l.id),
            title: l.name,
            subtitle: l.company || 'Lead',
            module: 'leads',
            url: `/leads/${l.id}`,
            snippet: `Status: ${l.status}`,
          })),
        ...(moduleData.deals || [])
          .filter((d) => d.name?.toLowerCase().includes(lowerQ) || d.companyName?.toLowerCase().includes(lowerQ))
          .slice(0, 3)
          .map((d) => ({
            id: String(d.id),
            title: d.name,
            subtitle: d.companyName || d.stage,
            module: 'deals',
            url: `/deals/${d.id}`,
            snippet: `Stage: ${d.stage} • $${d.value || 0}`,
          })),
      ]
    : [];

  const finalResults = searchResults.length > 0 ? searchResults : localResults;

  const modulesFilterList = [
    { id: undefined, label: 'All' },
    { id: 'leads', label: 'Leads' },
    { id: 'deals', label: 'Deals' },
    { id: 'contacts', label: 'Contacts' },
    { id: 'companies', label: 'Companies' },
    { id: 'projects', label: 'Projects' },
    { id: 'tickets', label: 'Tickets' },
    { id: 'invoices', label: 'Invoices' },
  ];

  return (
    <div className="fixed inset-0 bg-black/70 backdrop-blur-xs z-50 flex items-start justify-center p-4 pt-16 sm:pt-20">
      <div
        className={`w-full max-w-2xl rounded-2xl border shadow-2xl overflow-hidden animate-in zoom-in-95 duration-150 ${
          theme === 'dark' ? 'bg-slate-900 border-slate-800 text-slate-100' : 'bg-white border-slate-200 text-slate-800'
        }`}
      >
        {/* Search Input Bar */}
        <div className="relative border-b border-inherit p-4 flex items-center gap-3">
          {isLoading || isFetching ? (
            <Loader2 className="h-5 w-5 text-emerald-400 animate-spin shrink-0" />
          ) : (
            <Search className="h-5 w-5 text-emerald-400 shrink-0" />
          )}
          <input
            type="text"
            autoFocus
            placeholder="Search leads, deals, contacts, companies, tickets, invoices..."
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            className="w-full bg-transparent text-sm sm:text-base focus:outline-none placeholder-slate-500"
          />
          {query && (
            <button
              onClick={() => setQuery('')}
              className="p-1 rounded-lg hover:bg-slate-800 text-slate-400 hover:text-slate-200 text-xs"
            >
              Clear
            </button>
          )}
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg hover:bg-slate-800 text-slate-400 hover:text-slate-200"
            title="Close (ESC)"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Filter Pills */}
        <div className={`px-4 py-2 border-b border-inherit flex items-center gap-1.5 overflow-x-auto text-xs ${
          theme === 'dark' ? 'bg-slate-950/40' : 'bg-slate-50'
        }`}>
          {modulesFilterList.map((m) => (
            <button
              key={m.label}
              onClick={() => setSelectedModule(m.id)}
              className={`px-2.5 py-1 rounded-lg font-medium transition-all shrink-0 ${
                selectedModule === m.id
                  ? 'bg-emerald-600 text-white shadow-xs'
                  : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
              }`}
            >
              {m.label}
            </button>
          ))}
        </div>

        {/* Results List */}
        <div className="max-h-96 overflow-y-auto p-3 space-y-2">
          {query.trim().length === 0 ? (
            <div className="py-12 text-center text-xs text-slate-400 flex flex-col items-center gap-2">
              <Sparkles className="h-6 w-6 text-emerald-500/40" />
              <span>Type at least 2 characters to search across multi-tenant records.</span>
            </div>
          ) : finalResults.length === 0 && !isLoading ? (
            <div className="py-12 text-center text-xs text-slate-400">
              No matching records found for "{query}".
            </div>
          ) : (
            finalResults.map((item) => {
              const IconComp = MODULE_ICONS[item.module] || Layers;
              const colorCls = MODULE_COLORS[item.module] || 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20';

              return (
                <button
                  key={`${item.module}-${item.id}`}
                  onClick={() => {
                    onNavigate(item.module);
                    onClose();
                  }}
                  className={`w-full flex items-center justify-between p-3 rounded-xl border text-left transition-all ${
                    theme === 'dark'
                      ? 'border-slate-800 hover:bg-slate-800/80 hover:border-slate-700'
                      : 'border-slate-200 hover:bg-slate-50 hover:border-slate-300'
                  }`}
                >
                  <div className="flex items-center gap-3 min-w-0">
                    <div className={`h-8 w-8 rounded-lg flex items-center justify-center border shrink-0 ${colorCls}`}>
                      <IconComp className="h-4 w-4" />
                    </div>
                    <div className="min-w-0">
                      <div className="flex items-center gap-2">
                        <p className="font-semibold text-sm truncate text-slate-100">{item.title}</p>
                        <span className={`text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded border shrink-0 ${colorCls}`}>
                          {item.module}
                        </span>
                      </div>
                      <p className="text-xs text-slate-400 truncate">
                        {item.subtitle}
                        {item.snippet ? ` • ${item.snippet}` : ''}
                      </p>
                    </div>
                  </div>
                  <ArrowRight className="h-4 w-4 text-slate-500 shrink-0 ml-2" />
                </button>
              );
            })
          )}
        </div>

        {/* Footer shortcuts */}
        <div
          className={`p-3 border-t border-inherit flex items-center justify-between text-[11px] ${
            theme === 'dark' ? 'bg-slate-950/40 text-slate-400' : 'bg-slate-50 text-slate-500'
          }`}
        >
          <span>Press <kbd className="px-1.5 py-0.5 rounded bg-slate-800 font-mono text-[10px] text-slate-300">ESC</kbd> to exit</span>
          <span className="text-emerald-400 font-medium">Tenant Isolated Search • Realtime</span>
        </div>
      </div>
    </div>
  );
}
