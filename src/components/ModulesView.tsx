import React, { useState, useEffect } from 'react';
import { useCRMStore } from '../store/crmStore';
import {
  TrendingUp,
  DollarSign,
  Briefcase,
  Users,
  AlertCircle,
  Clock,
  Sparkles,
  Layers,
  HelpCircle,
  FolderKanban,
  UserCheck,
  Building,
  Award,
  Key,
  ShieldAlert,
  Sliders,
  Calendar as CalendarIcon,
  Copy,
  Check,
  Compass,
  FileText
} from 'lucide-react';
import {
  ResponsiveContainer,
  AreaChart,
  Area,
  XAxis,
  YAxis,
  Tooltip,
  PieChart,
  Pie,
  Cell,
  BarChart,
  Bar
} from 'recharts';
import ModuleTable from './ModuleTable';
import ModuleModals from './ModuleModals';

interface SettingsViewProps {
  settings: any;
  updateSettings: (settings: any) => void;
  theme: 'light' | 'dark';
}

function SettingsView({ settings, updateSettings, theme }: SettingsViewProps) {
  const [cName, setCName] = useState(settings?.companyName || '');
  const [cEmail, setCEmail] = useState(settings?.companyEmail || '');
  const [cPhone, setCPhone] = useState(settings?.companyPhone || '');
  const [tax, setTax] = useState(settings?.taxRate || 15);
  const [curr, setCurr] = useState(settings?.currency || 'USD');

  useEffect(() => {
    if (settings) {
      setCName(settings.companyName || '');
      setCEmail(settings.companyEmail || '');
      setCPhone(settings.companyPhone || '');
      setTax(settings.taxRate || 15);
      setCurr(settings.currency || 'USD');
    }
  }, [settings]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    updateSettings({
      companyName: cName,
      companyEmail: cEmail,
      companyPhone: cPhone,
      taxRate: tax,
      currency: curr
    });
  };

  const inputClass = `w-full px-3 py-2 text-sm rounded-xl border focus:ring-2 focus:ring-emerald-500 outline-hidden transition ${
    theme === 'dark'
      ? 'bg-slate-950/20 border-slate-700 text-slate-100 focus:border-slate-600'
      : 'bg-slate-50 border-slate-200 text-slate-800 focus:bg-white focus:border-slate-300'
  }`;

  const labelClass = "text-xs font-semibold text-slate-500 dark:text-slate-400 block mb-1";

  return (
    <div className="space-y-6 max-w-2xl">
      <div>
        <h1 className="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100">CRM Settings Configurations</h1>
        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Manage core platform billing properties, default taxation, and contact profiles.</p>
      </div>

      <form onSubmit={handleSubmit} className={`p-6 rounded-2xl border space-y-4 ${
        theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
      }`}>
        <div>
          <label className={labelClass}>Company Entity Title</label>
          <input
            type="text"
            value={cName}
            onChange={(e) => setCName(e.target.value)}
            className={inputClass}
          />
        </div>
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className={labelClass}>Primary Email</label>
            <input
              type="email"
              value={cEmail}
              onChange={(e) => setCEmail(e.target.value)}
              className={inputClass}
            />
          </div>
          <div>
            <label className={labelClass}>Phone Line</label>
            <input
              type="text"
              value={cPhone}
              onChange={(e) => setCPhone(e.target.value)}
              className={inputClass}
            />
          </div>
        </div>
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className={labelClass}>Global Currency Code</label>
            <select
              value={curr}
              onChange={(e) => setCurr(e.target.value)}
              className={inputClass}
            >
              <option value="USD" className="text-slate-800">USD ($)</option>
              <option value="EUR" className="text-slate-800">EUR (€)</option>
              <option value="GBP" className="text-slate-800">GBP (£)</option>
              <option value="INR" className="text-slate-800">INR (₹)</option>
            </select>
          </div>
          <div>
            <label className={labelClass}>Standard Sales Tax (%)</label>
            <input
              type="number"
              value={tax}
              onChange={(e) => setTax(parseFloat(e.target.value))}
              className={inputClass}
            />
          </div>
        </div>

        <div className="border-t border-slate-200 dark:border-slate-800 pt-4 mt-6 flex justify-end">
          <button
            type="submit"
            className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-6 py-2 rounded-xl text-xs transition shadow-lg shadow-emerald-500/10 cursor-pointer"
          >
            Save Configurations
          </button>
        </div>
      </form>
    </div>
  );
}

interface ModulesViewProps {
  activeModule: string;
}

export default function ModulesView({ activeModule }: ModulesViewProps) {
  const {
    reports,
    fetchReports,
    isLoading,
    theme,
    settings,
    fetchSettings,
    updateSettings,
    clockIn,
    clockOut,
    moduleData,
    approveLeave,
    analyzeLeadWithAI,
    aiLeadAnalysis,
    draftTicketReplyWithAI,
    aiTicketReply,
    rolesPermissions,
    fetchRolesPermissions
  } = useCRMStore();

  const [modalOpen, setModalOpen] = useState(false);
  const [modalModule, setModalModule] = useState('');
  const [editingItem, setEditingItem] = useState<any>(null);

  // AI Popups State
  const [activeAILeadId, setActiveAILeadId] = useState<string | null>(null);
  const [activeAITicketId, setActiveAITicketId] = useState<string | null>(null);
  const [copiedText, setCopiedText] = useState(false);

  useEffect(() => {
    fetchReports();
    fetchSettings();
    fetchRolesPermissions();
  }, [activeModule]);

  const triggerAdd = (mod: string) => {
    setModalModule(mod);
    setEditingItem(null);
    setModalOpen(true);
  };

  const triggerEdit = (mod: string, row: any) => {
    setModalModule(mod);
    setEditingItem(row);
    setModalOpen(true);
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    setCopiedText(true);
    setTimeout(() => setCopiedText(false), 2000);
  };

  const COLORS = ['#10b981', '#06b6d4', '#f59e0b', '#ef4444', '#8b5cf6'];

  // --- DASHBOARD MODULE VIEW ---
  if (activeModule === 'dashboard') {
    const sum = reports.summary || {
      totalLeads: 0,
      activeLeadsCount: 0,
      totalDeals: 0,
      pipelineValue: 0,
      closedWonValue: 0,
      totalInvoices: 0,
      totalReceivedRevenue: 0,
      overdueInvoicesValue: 0,
      openTickets: 0,
      activeProjects: 0,
      averageProjectProgress: 0
    };

    const statsCards = [
      {
        title: 'Active Leads Pipeline',
        value: `${sum.activeLeadsCount} / ${sum.totalLeads}`,
        desc: 'New & Contacted Leads',
        icon: Layers,
        color: 'text-emerald-500 bg-emerald-500/10'
      },
      {
        title: 'Deals Pipeline Value',
        value: `$${(sum.pipelineValue || 0).toLocaleString()}`,
        desc: 'Contract value pending win',
        icon: Briefcase,
        color: 'text-amber-500 bg-amber-500/10'
      },
      {
        title: 'Collected Revenue',
        value: `$${(sum.totalReceivedRevenue || 0).toLocaleString()}`,
        desc: 'Reconciled paid invoices',
        icon: DollarSign,
        color: 'text-teal-500 bg-teal-500/10'
      },
      {
        title: 'Open Support Tickets',
        value: sum.openTickets,
        desc: 'Active customer backlog',
        icon: HelpCircle,
        color: 'text-rose-500 bg-rose-500/10'
      }
    ];

    return (
      <div className="space-y-6">
        {/* Statistics Bento Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          {statsCards.map((card, idx) => {
            const Icon = card.icon;
            return (
              <div
                key={idx}
                className={`p-5 rounded-2xl border transition hover:-translate-y-0.5 duration-200 shadow-xs ${
                  theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-100'
                }`}
              >
                <div className="flex items-center justify-between">
                  <span className={`p-2.5 rounded-xl ${card.color}`}>
                    <Icon className="h-5 w-5" />
                  </span>
                  <span className="text-xs text-slate-500 dark:text-slate-400 font-mono font-bold">+12% MoM</span>
                </div>
                <h3 className="text-sm font-semibold text-slate-500 dark:text-slate-400 mt-4">{card.title}</h3>
                <p className="text-2xl font-bold tracking-tight mt-1">{card.value}</p>
                <p className="text-xs text-slate-500 mt-1">{card.desc}</p>
              </div>
            );
          })}
        </div>

        {/* Dashboard Analytics & Charts Section */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Main Revenue Chart */}
          <div className={`p-5 rounded-2xl border lg:col-span-2 ${
            theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-100'
          }`}>
            <h3 className="font-bold text-sm mb-4">Enterprise Revenue Trend (USD)</h3>
            <div className="h-72 w-full">
              {reports.charts?.monthlyRevenue ? (
                <ResponsiveContainer width="100%" height="100%">
                  <AreaChart data={reports.charts.monthlyRevenue}>
                    <defs>
                      <linearGradient id="colorRev" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%" stopColor="#10b981" stopOpacity={0.2}/>
                        <stop offset="95%" stopColor="#10b981" stopOpacity={0}/>
                      </linearGradient>
                    </defs>
                    <XAxis dataKey="name" stroke="#64748b" fontSize={11} tickLine={false} />
                    <YAxis stroke="#64748b" fontSize={11} tickLine={false} />
                    <Tooltip contentStyle={{ backgroundColor: theme === 'dark' ? '#0f172a' : '#fff', borderRadius: '12px' }} />
                    <Area type="monotone" dataKey="revenue" stroke="#10b981" strokeWidth={2.5} fillOpacity={1} fill="url(#colorRev)" />
                  </AreaChart>
                </ResponsiveContainer>
              ) : (
                <div className="h-full flex items-center justify-center text-slate-500">Loading charts...</div>
              )}
            </div>
          </div>

          {/* Leads Distribution Pie Chart */}
          <div className={`p-5 rounded-2xl border ${
            theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-100'
          }`}>
            <h3 className="font-bold text-sm mb-4">Lead Status Pipeline Breakdown</h3>
            <div className="h-64 w-full flex items-center justify-center">
              {reports.charts?.leadsDistribution ? (
                <ResponsiveContainer width="100%" height="100%">
                  <PieChart>
                    <Pie
                      data={reports.charts.leadsDistribution}
                      cx="50%"
                      cy="50%"
                      innerRadius={60}
                      outerRadius={80}
                      paddingAngle={5}
                      dataKey="value"
                    >
                      {reports.charts.leadsDistribution.map((entry, index) => (
                        <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                      ))}
                    </Pie>
                    <Tooltip contentStyle={{ backgroundColor: theme === 'dark' ? '#0f172a' : '#fff', borderRadius: '12px' }} />
                  </PieChart>
                </ResponsiveContainer>
              ) : (
                <div className="h-full flex items-center justify-center text-slate-500">Loading pipeline...</div>
              )}
            </div>
            <div className="flex flex-wrap justify-center gap-4 text-xs mt-2">
              {reports.charts?.leadsDistribution?.map((entry, index) => (
                <div key={entry.name} className="flex items-center gap-1.5">
                  <span className="h-3 w-3 rounded-full" style={{ backgroundColor: COLORS[index % COLORS.length] }}></span>
                  <span className="text-slate-400 font-medium">{entry.name} ({entry.value})</span>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Calendar quick activities list */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div className={`p-5 rounded-2xl border ${theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-100'}`}>
            <h3 className="font-bold text-sm mb-4 flex items-center gap-2">
              <CalendarIcon className="h-4.5 w-4.5 text-emerald-500" />
              <span>Upcoming Client Meetings</span>
            </h3>
            <div className="space-y-3">
              {moduleData.meetings?.slice(0, 2).map((meet) => (
                <div key={meet.id} className="p-3 border rounded-xl border-inherit bg-slate-800/10 flex justify-between items-center text-xs">
                  <div>
                    <h4 className="font-bold">{meet.title}</h4>
                    <p className="text-slate-400 mt-1">Attendees: {meet.attendees.join(', ')}</p>
                    {meet.meetLink && <a href={meet.meetLink} target="_blank" rel="noreferrer" className="text-emerald-500 hover:underline font-semibold block mt-1">Join Google Meet</a>}
                  </div>
                  <div className="text-right shrink-0">
                    <p className="font-semibold font-mono">{meet.date}</p>
                    <p className="text-slate-400 font-mono mt-0.5">{meet.time}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className={`p-5 rounded-2xl border ${theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-100'}`}>
            <h3 className="font-bold text-sm mb-4 flex items-center gap-2">
              <Sparkles className="h-4.5 w-4.5 text-emerald-500" />
              <span>SaaS System Quick Actions</span>
            </h3>
            <div className="grid grid-cols-2 gap-3">
              <button
                onClick={() => triggerAdd('leads')}
                className="p-3.5 rounded-xl border border-dashed border-inherit hover:bg-emerald-500/10 hover:border-emerald-500/30 transition text-left text-xs font-semibold"
              >
                + Register New Lead
              </button>
              <button
                onClick={() => triggerAdd('deals')}
                className="p-3.5 rounded-xl border border-dashed border-inherit hover:bg-emerald-500/10 hover:border-emerald-500/30 transition text-left text-xs font-semibold"
              >
                + Log Sales Deal
              </button>
              <button
                onClick={() => triggerAdd('tickets')}
                className="p-3.5 rounded-xl border border-dashed border-inherit hover:bg-emerald-500/10 hover:border-emerald-500/30 transition text-left text-xs font-semibold"
              >
                + Submit Support Ticket
              </button>
              <button
                onClick={clockIn}
                className="p-3.5 rounded-xl border border-dashed border-inherit hover:bg-emerald-500/10 hover:border-emerald-500/30 transition text-left text-xs font-semibold"
              >
                ✓ Clock Attendance Duty
              </button>
            </div>
          </div>
        </div>

        <ModuleModals module={modalModule} isOpen={modalOpen} onClose={() => setModalOpen(false)} editItem={editingItem} />
      </div>
    );
  }

  // --- LEADS MODULE VIEW ---
  if (activeModule === 'leads') {
    const leadColumns = [
      { key: 'name', label: 'Lead Name' },
      { key: 'company', label: 'Company' },
      { key: 'email', label: 'Email' },
      {
        key: 'value',
        label: 'Contract Value',
        render: (val: any) => <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">${(val || 0).toLocaleString()}</span>
      },
      {
        key: 'status',
        label: 'Status',
        render: (val: string) => {
          const color = val === 'qualified' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' : val === 'lost' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20' : 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20';
          return <span className={`px-2 py-0.5 rounded-full border text-[10px] font-bold uppercase ${color}`}>{val}</span>;
        }
      },
      { key: 'source', label: 'Acquisition Source' }
    ];

    const leadFilters = [
      {
        key: 'status',
        label: 'Status',
        options: [
          { value: 'new', label: 'New' },
          { value: 'contacted', label: 'Contacted' },
          { value: 'qualified', label: 'Qualified' },
          { value: 'lost', label: 'Lost' }
        ]
      }
    ];

    const handleAIEvaluation = (row: any) => {
      setActiveAILeadId(row.id);
      analyzeLeadWithAI(row.id);
    };

    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Leads Acquisition Pipeline</h1>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Convert high value target enterprise accounts with smart sales diagnostics.</p>
          </div>
        </div>

        <ModuleTable
          module="leads"
          columns={leadColumns}
          filtersConfig={leadFilters}
          onAddClick={() => triggerAdd('leads')}
          onEditClick={(row) => triggerEdit('leads', row)}
          onDeleteClick={(row) => useCRMStore.getState().deleteRecord('leads', row.id)}
          customActions={(row) => (
            <button
              onClick={() => handleAIEvaluation(row)}
              className="text-xs bg-purple-600/10 hover:bg-purple-600/20 text-purple-400 border border-purple-500/20 px-2 py-1 rounded-lg font-bold flex items-center gap-1 transition"
            >
              <Sparkles className="h-3 w-3" />
              <span>AI Score</span>
            </button>
          )}
        />

        {/* AI LEAD ANALYSIS POPUP WINDOW */}
        {activeAILeadId && (
          <div className="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div className={`w-full max-w-lg p-6 rounded-2xl border shadow-2xl relative animate-in zoom-in-95 duration-150 ${
              theme === 'dark' ? 'bg-slate-950 border-slate-800 text-slate-100' : 'bg-white border-slate-200 text-slate-800'
            }`}>
              <button onClick={() => setActiveAILeadId(null)} className="absolute top-4 right-4 text-slate-400 hover:text-slate-600 dark:hover:text-slate-100">✕</button>
              <div className="flex items-center gap-2 mb-4">
                <Sparkles className="h-5 w-5 text-purple-400 animate-pulse" />
                <h3 className="font-bold text-md text-slate-900 dark:text-slate-100">Gemini™ Enterprise Lead Score Playbook</h3>
              </div>
              <div className="space-y-4 max-h-[50vh] overflow-y-auto text-xs whitespace-pre-wrap leading-relaxed pr-1 text-slate-700 dark:text-slate-300 font-medium">
                {aiLeadAnalysis[activeAILeadId] || 'Consulting models, structuring deal parameters. Please wait...'}
              </div>
              <div className="border-t border-slate-200 dark:border-slate-800 pt-4 mt-6 flex justify-end gap-2">
                {aiLeadAnalysis[activeAILeadId] && (
                  <button
                    onClick={() => copyToClipboard(aiLeadAnalysis[activeAILeadId])}
                    className="bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700 px-4 py-2 rounded-xl text-xs font-semibold flex items-center gap-1.5 hover:bg-slate-200 dark:hover:bg-slate-700 transition"
                  >
                    {copiedText ? <Check className="h-3.5 w-3.5 text-emerald-500" /> : <Copy className="h-3.5 w-3.5" />}
                    <span>{copiedText ? 'Copied' : 'Copy Playbook'}</span>
                  </button>
                )}
                <button onClick={() => setActiveAILeadId(null)} className="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition">Done</button>
              </div>
            </div>
          </div>
        )}

        <ModuleModals module={modalModule} isOpen={modalOpen} onClose={() => setModalOpen(false)} editItem={editingItem} />
      </div>
    );
  }

  // --- DEALS MODULE VIEW ---
  if (activeModule === 'deals') {
    const dealColumns = [
      { key: 'name', label: 'Deal Title' },
      { key: 'companyName', label: 'Account Name' },
      {
        key: 'value',
        label: 'Contract Value',
        render: (val: any) => <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">${(val || 0).toLocaleString()}</span>
      },
      {
        key: 'stage',
        label: 'Pipeline Stage',
        render: (val: string) => {
          const color = val === 'won' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : val === 'lost' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' : 'bg-blue-500/10 text-blue-600 dark:text-blue-400';
          return <span className={`px-2 py-0.5 rounded-full border border-inherit text-[10px] font-bold uppercase ${color}`}>{val}</span>;
        }
      },
      { key: 'closeDate', label: 'Close Date' },
      { key: 'assignedTo', label: 'Assigned Lead' }
    ];

    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Sales Deals & Pipelines</h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Track strategic corporate accounts through standard enterprise negotiation stages.</p>
        </div>
        <ModuleTable
          module="deals"
          columns={dealColumns}
          onAddClick={() => triggerAdd('deals')}
          onEditClick={(row) => triggerEdit('deals', row)}
          onDeleteClick={(row) => useCRMStore.getState().deleteRecord('deals', row.id)}
        />
        <ModuleModals module={modalModule} isOpen={modalOpen} onClose={() => setModalOpen(false)} editItem={editingItem} />
      </div>
    );
  }

  // --- HR SYSTEM VIEW ---
  if (activeModule === 'employees') {
    const activeTab = 'leaves'; // default HR focus

    const employeeColumns = [
      { key: 'name', label: 'Employee Name' },
      { key: 'email', label: 'Corporate Email' },
      { key: 'department', label: 'Department' },
      { key: 'designation', label: 'Designation' },
      {
        key: 'salary',
        label: 'Annual Salary',
        render: (val: any) => <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">${(val || 0).toLocaleString()}</span>
      },
      { key: 'dateOfJoining', label: 'Date Joined' }
    ];

    const leaveColumns = [
      { key: 'employeeName', label: 'Employee Name' },
      { key: 'type', label: 'Leave Category' },
      { key: 'startDate', label: 'Start Date' },
      { key: 'endDate', label: 'End Date' },
      {
        key: 'status',
        label: 'Approval Status',
        render: (val: string) => {
          const color = val === 'approved' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : val === 'rejected' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' : 'bg-slate-500/10 text-slate-600 dark:text-slate-400';
          return <span className={`px-2 py-0.5 rounded-full border border-inherit text-[10px] font-bold uppercase ${color}`}>{val}</span>;
        }
      }
    ];

    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100">HR Roster, Leaves & Attendance Registers</h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Clock corporate hours, submit time-off, and authorize leaves seamlessly.</p>
        </div>

        <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
          <div className="xl:col-span-2 space-y-6">
            <div className={`p-4 rounded-xl border ${theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'}`}>
              <h2 className="font-bold text-sm mb-4">Staff Directory</h2>
              <ModuleTable
                module="employees"
                columns={employeeColumns}
                onAddClick={() => triggerAdd('employees')}
                onEditClick={(row) => triggerEdit('employees', row)}
                onDeleteClick={(row) => useCRMStore.getState().deleteRecord('employees', row.id)}
              />
            </div>

            <div className={`p-4 rounded-xl border ${theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'}`}>
              <h2 className="font-bold text-sm mb-4">Leave Application Management</h2>
              <ModuleTable
                module="leaves"
                columns={leaveColumns}
                onAddClick={() => triggerAdd('leaves')}
                customActions={(row) => (
                  row.status === 'pending' && (
                    <div className="flex items-center gap-1.5 shrink-0">
                      <button
                        onClick={() => approveLeave(row.id, 'approved')}
                        className="text-xs font-bold text-emerald-500 hover:underline px-2 py-1 rounded bg-emerald-500/5 hover:bg-emerald-500/10 border border-emerald-500/10"
                      >
                        Approve
                      </button>
                      <button
                        onClick={() => approveLeave(row.id, 'rejected')}
                        className="text-xs font-bold text-rose-500 hover:underline px-2 py-1 rounded bg-rose-500/5 hover:bg-rose-500/10 border border-rose-500/10"
                      >
                        Reject
                      </button>
                    </div>
                  )
                )}
              />
            </div>
          </div>

          <div className="space-y-6">
            {/* Quick clock module */}
            <div className={`p-5 rounded-xl border flex flex-col items-center justify-center text-center ${
              theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
            }`}>
              <Clock className="h-10 w-10 text-emerald-500 animate-pulse mb-3" />
              <h3 className="font-bold text-md text-slate-900 dark:text-slate-100">Daily Attendance Clock</h3>
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-xs">Log daily enterprise staging work hours securely for reporting.</p>
              <div className="flex items-center gap-3 w-full mt-6">
                <button
                  onClick={() => useCRMStore.getState().clockIn()}
                  className="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 rounded-xl text-xs transition shadow-lg shadow-emerald-500/15"
                >
                  Clock In
                </button>
                <button
                  onClick={() => useCRMStore.getState().clockOut()}
                  className="flex-1 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold border border-slate-700 py-2.5 rounded-xl text-xs transition"
                >
                  Clock Out
                </button>
              </div>
            </div>

            {/* Attendance logs lists */}
            <div className={`p-4 rounded-xl border ${theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'}`}>
              <h3 className="font-bold text-sm mb-3">Live Duty Register</h3>
              <div className="space-y-2.5 max-h-80 overflow-y-auto scrollbar-thin">
                {moduleData.attendance?.slice(0, 5).map((att) => (
                  <div key={att.id} className="p-2.5 rounded-xl border border-inherit bg-slate-800/10 flex justify-between items-center text-xs">
                    <div>
                      <p className="font-bold">{att.employeeName}</p>
                      <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Clocked in: {att.checkIn} {att.checkOut ? `- Clocked out: ${att.checkOut}` : ''}</p>
                    </div>
                    <span className={`px-2 py-0.5 rounded-full border border-inherit uppercase text-[9px] font-bold ${
                      att.status === 'present' ? 'bg-emerald-500/10 text-emerald-400' : att.status === 'late' ? 'bg-amber-500/10 text-amber-400' : 'bg-slate-500/10 text-slate-400'
                    }`}>{att.status}</span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>

        <ModuleModals module={modalModule} isOpen={modalOpen} onClose={() => setModalOpen(false)} editItem={editingItem} />
      </div>
    );
  }

  // --- SUPPORT TICKETS VIEW ---
  if (activeModule === 'tickets') {
    const tktColumns = [
      { key: 'subject', label: 'Ticket Issue Subject' },
      { key: 'customerName', label: 'Customer' },
      {
        key: 'priority',
        label: 'Priority',
        render: (val: string) => {
          const color = val === 'high' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' : val === 'medium' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400' : 'bg-slate-500/10 text-slate-600 dark:text-slate-400';
          return <span className={`px-2 py-0.5 rounded-full border border-inherit text-[10px] font-bold uppercase ${color}`}>{val}</span>;
        }
      },
      {
        key: 'status',
        label: 'Status',
        render: (val: string) => {
          const color = val === 'open' ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400' : val === 'resolved' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-slate-500/10 text-slate-600 dark:text-slate-400';
          return <span className={`px-2 py-0.5 rounded-full border border-inherit text-[10px] font-bold uppercase ${color}`}>{val}</span>;
        }
      },
      { key: 'assignedTo', label: 'Assigned Specialist' }
    ];

    const handleAITicketReply = (row: any) => {
      setActiveAITicketId(row.id);
      draftTicketReplyWithAI(row.id);
    };

    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Support Desk Helpdesk</h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Resolve corporate trouble tickets, manage priorities, and trigger smart auto-replies.</p>
        </div>

        <ModuleTable
          module="tickets"
          columns={tktColumns}
          onAddClick={() => triggerAdd('tickets')}
          onEditClick={(row) => triggerEdit('tickets', row)}
          onDeleteClick={(row) => useCRMStore.getState().deleteRecord('tickets', row.id)}
          customActions={(row) => (
            <button
              onClick={() => handleAITicketReply(row)}
              className="text-xs bg-purple-600/10 hover:bg-purple-600/20 text-purple-400 border border-purple-500/20 px-2 py-1 rounded-lg font-bold flex items-center gap-1 transition"
            >
              <Sparkles className="h-3 w-3" />
              <span>AI Reply</span>
            </button>
          )}
        />

        {/* AI TICKET EMAIL RESPONDER POPUP */}
        {activeAITicketId && (
          <div className="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div className={`w-full max-w-lg p-6 rounded-2xl border shadow-2xl relative animate-in zoom-in-95 duration-150 ${
              theme === 'dark' ? 'bg-slate-950 border-slate-800 text-slate-100' : 'bg-white border-slate-200 text-slate-800'
            }`}>
              <button onClick={() => setActiveAITicketId(null)} className="absolute top-4 right-4 text-slate-400 hover:text-slate-600 dark:hover:text-slate-100">✕</button>
              <div className="flex items-center gap-2 mb-4">
                <Sparkles className="h-5 w-5 text-purple-400 animate-pulse" />
                <h3 className="font-bold text-md text-slate-900 dark:text-slate-100">Gemini™ Support Helpdesk Draft</h3>
              </div>
              <div className="space-y-4 max-h-[50vh] overflow-y-auto text-xs whitespace-pre-wrap leading-relaxed pr-1 text-slate-700 dark:text-slate-300 font-medium">
                {aiTicketReply[activeAITicketId] || 'Synthesizing technical diagnostic responses...'}
              </div>
              <div className="border-t border-slate-200 dark:border-slate-800 pt-4 mt-6 flex justify-end gap-2">
                {aiTicketReply[activeAITicketId] && (
                  <button
                    onClick={() => copyToClipboard(aiTicketReply[activeAITicketId])}
                    className="bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700 px-4 py-2 rounded-xl text-xs font-semibold flex items-center gap-1.5 hover:bg-slate-200 dark:hover:bg-slate-700 transition"
                  >
                    {copiedText ? <Check className="h-3.5 w-3.5 text-emerald-500" /> : <Copy className="h-3.5 w-3.5" />}
                    <span>{copiedText ? 'Copied' : 'Copy Email Draft'}</span>
                  </button>
                )}
                <button onClick={() => setActiveAITicketId(null)} className="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition">Done</button>
              </div>
            </div>
          </div>
        )}

        <ModuleModals module={modalModule} isOpen={modalOpen} onClose={() => setModalOpen(false)} editItem={editingItem} />
      </div>
    );
  }

  // --- ROLES & PERMISSIONS VIEW ---
  if (activeModule === 'roles') {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Granular Role-Based Access Tiers</h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Examine strict Spatie-style access policies and modular authorization capabilities.</p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {rolesPermissions.map((item, idx) => (
            <div
              key={idx}
              className={`p-5 rounded-2xl border ${theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-100'}`}
            >
              <div className="flex items-center gap-2">
                <Key className="h-5 w-5 text-emerald-500" />
                <h3 className="font-bold text-md">{item.role}</h3>
              </div>
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">{item.description}</p>
              <div className="flex flex-wrap gap-1.5 mt-4">
                {item.permissions.map((perm: string) => (
                  <span
                    key={perm}
                    className="text-[9px] font-mono font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-400 border border-emerald-500/15 px-2 py-0.5 rounded-lg"
                  >
                    {perm}
                  </span>
                ))}
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  // --- AUDIT TRAIL LOGS ---
  if (activeModule === 'audit') {
    const auditColumns = [
      { key: 'userName', label: 'Active User' },
      {
        key: 'action',
        label: 'User Action',
        render: (val: string) => <span className="font-mono font-semibold text-emerald-600 dark:text-emerald-400">{val}</span>
      },
      { key: 'module', label: 'Affected Module' },
      { key: 'details', label: 'Detailed Description' },
      { key: 'ipAddress', label: 'IP Address' },
      { key: 'createdAt', label: 'Log Timestamp' }
    ];

    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Security Audit Logs & Telemetry</h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Broad visibility tracking user access logins, modification records, and backup exports.</p>
        </div>
        <ModuleTable
          module="activityLogs"
          columns={auditColumns}
        />
      </div>
    );
  }

  // --- GLOBAL SYSTEM SETTINGS ---
  if (activeModule === 'settings') {
    return (
      <SettingsView settings={settings} updateSettings={updateSettings} theme={theme} />
    );
  }

  // --- GENERAL MODULES FALLBACK LISTINGS ---
  // Simply map table structures for simple columns
  let simpleColumns: any[] = [];
  let filtersConfig: any[] = [];

  if (activeModule === 'companies') {
    simpleColumns = [
      { key: 'name', label: 'Company Name' },
      { key: 'industry', label: 'Industry' },
      { key: 'website', label: 'Website URL' },
      { key: 'size', label: 'Staff Count' },
      { key: 'revenue', label: 'Revenue' },
      { key: 'address', label: 'Corporate Address' }
    ];
  } else if (activeModule === 'customers') {
    simpleColumns = [
      { key: 'name', label: 'Customer Name' },
      { key: 'companyName', label: 'Company Name' },
      { key: 'email', label: 'Email' },
      { key: 'phone', label: 'Contact Phone' },
      {
        key: 'status',
        label: 'Status',
        render: (val: string) => <span className={`px-2 py-0.5 rounded-full border border-inherit text-[10px] font-bold uppercase ${val === 'active' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-slate-500/10 text-slate-600 dark:text-slate-400'}`}>{val}</span>
      }
    ];
  } else if (activeModule === 'contacts') {
    simpleColumns = [
      { key: 'name', label: 'Contact Name' },
      { key: 'companyName', label: 'Account Name' },
      { key: 'email', label: 'Email' },
      { key: 'phone', label: 'Phone' },
      { key: 'role', label: 'Designation Role' },
      {
        key: 'isPrimary',
        label: 'Primary Contact',
        render: (val: boolean) => <span className={`px-2 py-0.5 rounded-full border border-inherit text-[10px] font-bold uppercase ${val ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-slate-500/10 text-slate-600 dark:text-slate-400'}`}>{val ? 'Primary' : 'Secondary'}</span>
      }
    ];
  } else if (activeModule === 'products') {
    simpleColumns = [
      { key: 'name', label: 'Product Name' },
      { key: 'sku', label: 'SKU Code' },
      {
        key: 'price',
        label: 'Standard Unit Price',
        render: (val: any) => <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">${(val || 0).toLocaleString()}</span>
      },
      { key: 'category', label: 'Category' }
    ];
  } else if (activeModule === 'quotations') {
    simpleColumns = [
      { key: 'quoteNumber', label: 'Quote Code' },
      { key: 'customerName', label: 'Client Partner' },
      {
        key: 'total',
        label: 'Total Value',
        render: (val: any) => <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">${(val || 0).toLocaleString()}</span>
      },
      {
        key: 'status',
        label: 'Status',
        render: (val: string) => <span className={`px-2 py-0.5 rounded-full border border-inherit text-[10px] font-bold uppercase ${val === 'accepted' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-slate-500/10 text-slate-600 dark:text-slate-400'}`}>{val}</span>
      },
      { key: 'validUntil', label: 'Expiry Date' }
    ];
  } else if (activeModule === 'invoices') {
    simpleColumns = [
      { key: 'invoiceNumber', label: 'Invoice Code' },
      { key: 'customerName', label: 'Client Account' },
      {
        key: 'total',
        label: 'Gross Bill',
        render: (val: any) => <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">${(val || 0).toLocaleString()}</span>
      },
      {
        key: 'status',
        label: 'Payment Status',
        render: (val: string) => <span className={`px-2 py-0.5 rounded-full border border-inherit text-[10px] font-bold uppercase ${val === 'paid' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : val === 'overdue' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' : 'bg-slate-500/10 text-slate-600 dark:text-slate-400'}`}>{val}</span>
      },
      { key: 'dueDate', label: 'Due Date' }
    ];
  } else if (activeModule === 'projects') {
    simpleColumns = [
      { key: 'name', label: 'Project Name' },
      { key: 'customerName', label: 'Customer Client' },
      { key: 'startDate', label: 'Start Date' },
      { key: 'endDate', label: 'Target Delivery' },
      {
        key: 'progress',
        label: 'Completion Progress',
        render: (val: number) => (
          <div className="flex items-center gap-2">
            <div className="w-24 bg-slate-200 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
              <div className="bg-emerald-500 h-full rounded-full" style={{ width: `${val || 0}%` }}></div>
            </div>
            <span className="font-mono font-bold">{val || 0}%</span>
          </div>
        )
      },
      {
        key: 'status',
        label: 'Project Status',
        render: (val: string) => <span className={`px-2 py-0.5 rounded-full border border-inherit text-[10px] font-bold uppercase ${val === 'completed' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-blue-500/10 text-blue-600 dark:text-blue-400'}`}>{val}</span>
      }
    ];
  } else if (activeModule === 'tasks') {
    simpleColumns = [
      { key: 'title', label: 'Task Title' },
      {
        key: 'priority',
        label: 'Priority',
        render: (val: string) => <span className={`px-2 py-0.5 rounded-full border border-inherit text-[10px] font-bold uppercase ${val === 'high' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' : 'bg-slate-500/10 text-slate-600 dark:text-slate-400'}`}>{val}</span>
      },
      {
        key: 'status',
        label: 'Status',
        render: (val: string) => <span className={`px-2 py-0.5 rounded-full border border-inherit text-[10px] font-bold uppercase ${val === 'done' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-slate-500/10 text-slate-600 dark:text-slate-400'}`}>{val}</span>
      },
      { key: 'assignedTo', label: 'Assigned Specialist' },
      { key: 'dueDate', label: 'Due Date' }
    ];
  } else if (activeModule === 'meetings') {
    simpleColumns = [
      { key: 'title', label: 'Meeting Title' },
      { key: 'date', label: 'Scheduled Date' },
      { key: 'time', label: 'Time (HH:MM)' },
      {
        key: 'meetLink',
        label: 'Google Meet',
        render: (val: string) => val ? <a href={val} target="_blank" rel="noreferrer" className="text-emerald-600 dark:text-emerald-400 font-semibold hover:underline">Link</a> : '-'
      }
    ];
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div className="capitalize">
          <h1 className="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{activeModule} Management</h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Review active rosters, coordinate logistics records, and update lists.</p>
        </div>
      </div>

      <ModuleTable
        module={activeModule}
        columns={simpleColumns}
        filtersConfig={filtersConfig}
        onAddClick={() => triggerAdd(activeModule)}
        onEditClick={(row) => triggerEdit(activeModule, row)}
        onDeleteClick={(row) => useCRMStore.getState().deleteRecord(activeModule, row.id)}
      />

      <ModuleModals module={modalModule} isOpen={modalOpen} onClose={() => setModalOpen(false)} editItem={editingItem} />
    </div>
  );
}
