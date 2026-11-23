import React, { useState, useEffect } from 'react';
import { useCRMStore, Toast } from '../store/crmStore';
import { canAccessModule } from '../utils/permissions';
import ApiStatusBanner from './common/ApiStatusBanner';
import GlobalSearchModal from './common/GlobalSearchModal';
import NotificationCenter from './common/NotificationCenter';
import PwaInstallBanner from './common/PwaInstallBanner';
import OfflineStatusIndicator from './common/OfflineStatusIndicator';
import MobileBottomNavBar from './common/MobileBottomNavBar';
import {
  LayoutDashboard,
  Users2,
  Building2,
  FolderKanban,
  ClipboardList,
  Calendar,
  Layers,
  FileSpreadsheet,
  Settings,
  LogOut,
  Bell,
  Sun,
  Moon,
  Menu,
  ChevronLeft,
  ChevronDown,
  Check,
  Plus,
  Shield,
  Activity,
  UserCheck,
  ShieldAlert,
  Clock,
  HelpCircle,
  FileCheck,
  CreditCard,
  Briefcase,
  AlertTriangle,
  UserCheck2,
  Award,
  Search,
  FolderArchive,
  Share2,
  Trophy,
  MessageSquare,
  Bot,
  PhoneCall,
  Inbox,
  GitBranch,
  Globe,
  Megaphone,
  BarChart3,
  Plug,
  Wallet,
  Receipt,
  Sparkles,
  X
} from 'lucide-react';
import { useUnreadNotificationCount } from '../hooks/useSharedServices';
import { IndustryTemplatesModal } from './platform/IndustryTemplatesModal';

interface LayoutProps {
  children: React.ReactNode;
  activeModule: string;
  setActiveModule: (mod: string) => void;
}

export default function Layout({ children, activeModule, setActiveModule }: LayoutProps) {
  const {
    user,
    logout,
    theme,
    setTheme,
    sidebarOpen,
    setSidebarOpen,
    toasts,
    removeToast,
    clockIn,
    clockOut,
    moduleData,
    markNotificationRead,
    workspaces,
    currentWorkspace,
    switchWorkspace,
    createWorkspace
  } = useCRMStore();

  const [notifOpen, setNotifOpen] = useState(false);
  const [profileOpen, setProfileOpen] = useState(false);
  const [globalSearchOpen, setGlobalSearchOpen] = useState(false);
  const [workspaceDropdownOpen, setWorkspaceDropdownOpen] = useState(false);
  const [createWorkspaceModalOpen, setCreateWorkspaceModalOpen] = useState(false);
  const [industryTemplatesModalOpen, setIndustryTemplatesModalOpen] = useState(false);
  const [newWorkspaceName, setNewWorkspaceName] = useState('');
  const [newWorkspaceTier, setNewWorkspaceTier] = useState('enterprise');
  const [isSubmittingWorkspace, setIsSubmittingWorkspace] = useState(false);
  const [currentTime, setCurrentTime] = useState(new Date());

  useEffect(() => {
    const timer = setInterval(() => setCurrentTime(new Date()), 1000);
    return () => clearInterval(timer);
  }, []);

  // Keyboard shortcut for Global Search (Cmd+K / Ctrl+K)
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        setGlobalSearchOpen((prev) => !prev);
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  const handleCreateWorkspace = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newWorkspaceName.trim()) return;
    setIsSubmittingWorkspace(true);
    const success = await createWorkspace(newWorkspaceName.trim(), newWorkspaceTier);
    setIsSubmittingWorkspace(false);
    if (success) {
      setNewWorkspaceName('');
      setCreateWorkspaceModalOpen(false);
      setWorkspaceDropdownOpen(false);
    }
  };

  const navItems = [
    { id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { id: 'ai_assistant', label: 'AI Business Co-pilot', icon: Sparkles },
    { id: 'analytics', label: 'Analytics & Reports', icon: BarChart3 },
    { id: 'integrations', label: 'Integrations & APIs', icon: Plug },
    { id: 'billing', label: 'Subscription & Billing', icon: Wallet },
    { id: 'leads', label: 'Leads Pipeline', icon: Layers },
    { id: 'lead_acquisition', label: 'Lead Acquisition Hub', icon: Share2 },
    { id: 'landing_pages', label: 'Landing Pages & Forms', icon: Globe },
    { id: 'marketing', label: 'Marketing & Campaigns', icon: Megaphone },
    { id: 'workflows', label: 'Workflow Automations', icon: GitBranch },
    { id: 'inbox', label: 'Omnichannel Inbox', icon: Inbox },
    { id: 'whatsapp', label: 'WhatsApp Team Inbox', icon: MessageSquare },
    { id: 'whatsapp_ai', label: 'WhatsApp AI Agent Builder', icon: Bot },
    { id: 'voice', label: 'AI Voice & Telephony', icon: PhoneCall },
    { id: 'deals', label: 'Deals & Pipeline', icon: Briefcase },
    { id: 'sales_teams', label: 'Sales Teams & Quotas', icon: Trophy },
    { id: 'customers', label: 'Customers', icon: UserCheck },
    { id: 'companies', label: 'Companies', icon: Building2 },
    { id: 'contacts', label: 'Contacts', icon: Users2 },
    { id: 'products', label: 'Products & Services', icon: FileSpreadsheet },
    { id: 'quotations', label: 'Quotations', icon: FileCheck },
    { id: 'invoices', label: 'Invoices & Payments', icon: CreditCard },
    { id: 'projects', label: 'Projects', icon: FolderKanban },
    { id: 'tasks', label: 'Tasks Manager', icon: ClipboardList },
    { id: 'meetings', label: 'Meetings Calendar', icon: Calendar },
    { id: 'employees', label: 'HR & Attendance', icon: UserCheck2 },
    { id: 'tickets', label: 'Support Helpdesk', icon: HelpCircle },
    { id: 'roles', label: 'Roles & Access', icon: Award },
    { id: 'files', label: 'Media & Files', icon: FolderArchive },
    { id: 'audit', label: 'Audit Log Trail', icon: Activity },
    { id: 'platform_console', label: 'Platform Console', icon: ShieldAlert },
    { id: 'settings', label: 'System Settings', icon: Settings }
  ];

  const { data: unreadNotifCount = 0 } = useUnreadNotificationCount();

  const handleLogout = () => {
    logout();
  };

  const unreadNotifs = moduleData.activityLogs?.slice(0, 5) || []; // Simulating quick logs as notifs

  return (
    <div className={`min-h-screen flex transition-colors duration-300 ${theme === 'dark' ? 'bg-slate-950 text-slate-100' : 'bg-slate-50 text-slate-800'}`}>
      
      {/* MOBILE BACKDROP OVERLAY */}
      {sidebarOpen && (
        <div
          onClick={() => setSidebarOpen(false)}
          className="fixed inset-0 bg-black/50 backdrop-blur-xs z-20 md:hidden"
        />
      )}

      {/* SIDEBAR */}
      <aside
        className={`fixed top-0 bottom-0 left-0 z-30 flex flex-col border-r transition-all duration-300 ${
          sidebarOpen 
            ? 'translate-x-0 w-64' 
            : 'max-md:-translate-x-full md:w-20 w-64'
        } ${theme === 'dark' ? 'bg-slate-900/90 border-slate-800' : 'bg-white/95 border-slate-200'} backdrop-blur-md`}
      >
        {/* Sidebar Header */}
        <div className="h-16 flex items-center justify-between px-4 border-b border-inherit">
          <div className="flex items-center gap-3 overflow-hidden">
            <div className="h-9 w-9 rounded-lg bg-emerald-500 flex items-center justify-center shadow-lg shadow-emerald-500/20 text-white font-bold text-lg shrink-0">
              {currentWorkspace?.name ? currentWorkspace.name.charAt(0).toUpperCase() : 'Æ'}
            </div>
            {(sidebarOpen || !sidebarOpen) && (
              <div className={`overflow-hidden ${!sidebarOpen ? 'md:hidden' : ''}`}>
                <span className="font-semibold tracking-wide text-sm bg-gradient-to-r from-emerald-400 to-teal-500 bg-clip-text text-transparent truncate block">
                  {currentWorkspace?.name || 'Acme CRM'}
                </span>
                <span className="text-[10px] text-slate-400 block truncate">
                  Enterprise SaaS
                </span>
              </div>
            )}
          </div>
          <button
            onClick={() => setSidebarOpen(!sidebarOpen)}
            className={`p-1.5 rounded-lg border transition-colors ${
              theme === 'dark' ? 'hover:bg-slate-800 border-slate-800 text-slate-400' : 'hover:bg-slate-100 border-slate-200 text-slate-500'
            }`}
          >
            <ChevronLeft className={`h-4 w-4 transition-transform duration-300 ${!sidebarOpen ? 'rotate-180' : ''}`} />
          </button>
        </div>

        {/* Navigation Items */}
        <nav className="flex-1 overflow-y-auto py-4 px-3 space-y-1 scrollbar-thin">
          {navItems.map((item) => {
            const hasAccess = canAccessModule(user, item.id);
            if (!hasAccess) return null;
            const IconComp = item.icon;
            const isActive = activeModule === item.id;
            return (
              <button
                key={item.id}
                onClick={() => {
                  setActiveModule(item.id);
                  // Auto-close sidebar on mobile after selecting an item
                  if (window.innerWidth < 768) {
                    setSidebarOpen(false);
                  }
                }}
                className={`w-full flex items-center gap-3.5 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${
                  isActive
                    ? theme === 'dark'
                      ? 'bg-gradient-to-r from-emerald-600/30 to-teal-600/10 text-emerald-400 border-l-4 border-emerald-500'
                      : 'bg-emerald-50/80 text-emerald-700 border-l-4 border-emerald-500'
                    : theme === 'dark'
                    ? 'text-slate-400 hover:bg-slate-800/50 hover:text-slate-200'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                }`}
              >
                <IconComp className={`h-5 w-5 shrink-0 ${isActive ? 'text-emerald-500' : 'text-slate-400'}`} />
                <span className={`truncate ${!sidebarOpen ? 'md:hidden' : ''}`}>{item.label}</span>
              </button>
            );
          })}
        </nav>

        {/* User Footer Profile */}
        <div className="p-4 border-t border-inherit flex items-center justify-between gap-3 shrink-0">
          <div className="flex items-center gap-3 min-w-0">
            <div
              onClick={() => setProfileOpen(!profileOpen)}
              className="h-10 w-10 rounded-full bg-slate-700 overflow-hidden cursor-pointer border-2 border-emerald-500 shrink-0 animate-pulse-slow"
            >
              {user?.avatar ? (
                <img src={user.avatar} alt="Avatar" className="h-full w-full object-cover" />
              ) : (
                <div className="h-full w-full flex items-center justify-center text-white font-semibold text-sm bg-gradient-to-br from-emerald-500 to-teal-600">
                  {user?.name.charAt(0)}
                </div>
              )}
            </div>
            <div className={`min-w-0 ${!sidebarOpen ? 'md:hidden' : ''}`}>
              <p className="text-sm font-medium truncate">{user?.name}</p>
              <p className="text-xs text-slate-400 truncate capitalize">{user?.role}</p>
            </div>
          </div>
          {(sidebarOpen || window.innerWidth < 768) && (
            <button
              onClick={handleLogout}
              className={`p-1.5 rounded-lg hover:text-rose-500 transition-colors shrink-0 ${
                theme === 'dark' ? 'hover:bg-slate-800' : 'hover:bg-slate-100'
              } ${!sidebarOpen ? 'md:hidden' : ''}`}
              title="Logout"
            >
              <LogOut className="h-4 w-4" />
            </button>
          )}
        </div>
      </aside>

      {/* MAIN CONTAINER */}
      <div className={`flex-1 flex flex-col transition-all duration-300 min-w-0 ${sidebarOpen ? 'md:pl-64' : 'md:pl-20'} pl-0`}>
        {/* Network Offline / Online Reconnect Alert */}
        <OfflineStatusIndicator />

        {/* Backend API Connection Status Alert */}
        <ApiStatusBanner />

        {/* PWA Install Invitation Banner */}
        <PwaInstallBanner />
        
        {/* HEADER */}
        <header className={`h-16 flex items-center justify-between px-4 md:px-6 border-b sticky top-0 z-20 backdrop-blur-md ${
          theme === 'dark' ? 'bg-slate-950/80 border-slate-900' : 'bg-white/80 border-slate-100'
        }`}>
          {/* Header Left */}
          <div className="flex items-center gap-2 md:gap-4">
            <button
              onClick={() => setSidebarOpen(!sidebarOpen)}
              className="p-1.5 rounded-lg border border-inherit text-slate-400 hover:text-slate-200 transition-colors"
            >
              <Menu className="h-5 w-5" />
            </button>
            <div className="hidden sm:flex items-center gap-2 text-xs font-mono text-slate-400 border px-3 py-1.5 rounded-xl border-slate-800">
              <Clock className="h-4.5 w-4.5 text-emerald-500 animate-spin-slow" />
              <span>
                {currentTime.toLocaleString('en-US', {
                  hour: '2-digit',
                  minute: '2-digit',
                  second: '2-digit',
                  hour12: false
                })}{' '}
                UTC
              </span>
            </div>
            <div className="flex items-center gap-1.5 md:gap-2">
              <button
                onClick={clockIn}
                className="text-[10px] md:text-xs bg-emerald-600 hover:bg-emerald-700 text-white px-2.5 py-1.5 rounded-xl font-semibold transition"
              >
                In
              </button>
              <button
                onClick={clockOut}
                className="text-[10px] md:text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 px-2.5 py-1.5 rounded-xl font-semibold border border-slate-700 transition"
              >
                Out
              </button>
            </div>

            {/* Active Workspace Selector Dropdown */}
            <div className="relative">
              <button
                onClick={() => setWorkspaceDropdownOpen(!workspaceDropdownOpen)}
                className={`flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-medium border transition-all ${
                  theme === 'dark'
                    ? 'bg-slate-900 border-slate-800 hover:border-slate-700 text-slate-200'
                    : 'bg-slate-100 border-slate-200 hover:border-slate-300 text-slate-700'
                }`}
                title="Switch business workspace"
              >
                <Building2 className="h-4 w-4 text-emerald-500 shrink-0" />
                <span className="font-semibold max-w-[110px] sm:max-w-[160px] truncate">
                  {currentWorkspace?.name || 'Workspace'}
                </span>
                {currentWorkspace?.subscription_tier && (
                  <span className="hidden sm:inline-block text-[10px] uppercase font-bold px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    {currentWorkspace.subscription_tier}
                  </span>
                )}
                <ChevronDown className={`h-3.5 w-3.5 text-slate-400 transition-transform ${workspaceDropdownOpen ? 'rotate-180' : ''}`} />
              </button>

              {workspaceDropdownOpen && (
                <div className={`absolute left-0 mt-2 w-72 rounded-2xl border shadow-2xl p-3 z-50 animate-in fade-in zoom-in-95 duration-150 ${
                  theme === 'dark' ? 'bg-slate-900 border-slate-800 text-slate-100' : 'bg-white border-slate-200 text-slate-800'
                }`}>
                  <div className="flex items-center justify-between pb-2 mb-2 border-b border-inherit px-1">
                    <span className="text-xs font-bold text-slate-400 uppercase tracking-wider">Business Workspaces</span>
                    <span className="text-[10px] font-mono text-emerald-500 font-semibold">{workspaces.length} Available</span>
                  </div>

                  <div className="space-y-1 max-h-56 overflow-y-auto">
                    {workspaces.map((ws: any) => {
                      const isSelected = currentWorkspace?.id === ws.id;
                      return (
                        <button
                          key={ws.id}
                          onClick={async () => {
                            setWorkspaceDropdownOpen(false);
                            await switchWorkspace(String(ws.id));
                          }}
                          className={`w-full flex items-center justify-between p-2 rounded-xl text-left text-xs transition-all ${
                            isSelected
                              ? 'bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 font-semibold'
                              : theme === 'dark'
                              ? 'hover:bg-slate-800/70 text-slate-300 border border-transparent'
                              : 'hover:bg-slate-100 text-slate-700 border border-transparent'
                          }`}
                        >
                          <div className="flex items-center gap-2.5 min-w-0">
                            <div className={`h-7 w-7 rounded-lg flex items-center justify-center shrink-0 ${
                              isSelected ? 'bg-emerald-500 text-white font-bold' : 'bg-slate-800 text-slate-400'
                            }`}>
                              {ws.name.charAt(0).toUpperCase()}
                            </div>
                            <div className="min-w-0">
                              <p className="truncate font-medium">{ws.name}</p>
                              <p className="text-[10px] text-slate-400 truncate">
                                {ws.user_role || ws.subscription_tier || 'Member'}
                              </p>
                            </div>
                          </div>
                          {isSelected && <Check className="h-4 w-4 text-emerald-400 shrink-0 ml-2" />}
                        </button>
                      );
                    })}
                  </div>

                  <div className="pt-2 mt-2 border-t border-inherit space-y-1.5">
                    <button
                      onClick={() => {
                        setWorkspaceDropdownOpen(false);
                        setIndustryTemplatesModalOpen(true);
                      }}
                      className="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-xl text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white transition-colors"
                    >
                      <Sparkles className="h-3.5 w-3.5" />
                      <span>Industry Templates</span>
                    </button>
                    <button
                      onClick={() => {
                        setWorkspaceDropdownOpen(false);
                        setCreateWorkspaceModalOpen(true);
                      }}
                      className="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white transition-colors"
                    >
                      <Plus className="h-3.5 w-3.5" />
                      <span>Register New Business</span>
                    </button>
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Header Right */}
          <div className="flex items-center gap-2 md:gap-3">
            {/* Quick Global Search Trigger */}
            <button
              onClick={() => setGlobalSearchOpen(true)}
              className={`flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs border transition-colors ${
                theme === 'dark'
                  ? 'bg-slate-900 border-slate-800 text-slate-400 hover:text-slate-200'
                  : 'bg-slate-100 border-slate-200 text-slate-600 hover:text-slate-900'
              }`}
              title="Global quick search (Ctrl+K)"
            >
              <Search className="h-4 w-4 text-emerald-500" />
              <span className="hidden sm:inline">Search...</span>
              <kbd className="hidden sm:inline-block px-1.5 py-0.5 text-[10px] font-mono rounded bg-slate-800 border border-slate-700 text-slate-400">
                ⌘K
              </kbd>
            </button>

            {/* Theme Toggle */}
            <button
              onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
              className={`p-2 rounded-xl transition-colors border ${
                theme === 'dark' ? 'bg-slate-900 border-slate-800 text-emerald-400' : 'bg-slate-100 border-slate-200 text-slate-600'
              }`}
            >
              {theme === 'dark' ? <Sun className="h-4.5 w-4.5 md:h-5 md:w-5" /> : <Moon className="h-4.5 w-4.5 md:h-5 md:w-5" />}
            </button>

            {/* Notification Drawer Trigger */}
            <button
              onClick={() => setNotifOpen(true)}
              className={`p-2 rounded-xl transition-colors border relative ${
                theme === 'dark' ? 'bg-slate-900 border-slate-800 text-slate-300' : 'bg-slate-100 border-slate-200 text-slate-600'
              }`}
              title="Workspace Notification Center"
            >
              <Bell className="h-4.5 w-4.5 md:h-5 md:w-5" />
              {unreadNotifCount > 0 ? (
                <span className="absolute -top-1 -right-1 h-5 min-w-[20px] px-1 rounded-full bg-rose-500 text-white font-mono text-[10px] font-bold flex items-center justify-center ring-2 ring-inherit animate-pulse">
                  {unreadNotifCount > 99 ? '99+' : unreadNotifCount}
                </span>
              ) : null}
            </button>

            {/* Profile Drawer Trigger */}
            <div
              onClick={() => setProfileOpen(!profileOpen)}
              className="h-9 w-9 rounded-full bg-slate-700 border border-emerald-500 cursor-pointer overflow-hidden flex items-center justify-center text-xs text-white font-semibold bg-gradient-to-br from-emerald-500 to-teal-600 shrink-0"
            >
              {user?.name.charAt(0)}
            </div>
          </div>
        </header>

        {/* COMPONENT BODY VIEW */}
        <main className="flex-1 p-4 md:p-6 pb-20 md:pb-6 overflow-y-auto">
          {children}
        </main>
      </div>

      {/* PROFILE SLIDEOUT SIDEBAR DRAWER */}
      {profileOpen && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex justify-end">
          <div className={`w-full max-w-sm md:max-w-md h-full p-6 shadow-2xl flex flex-col border-l animate-in slide-in-from-right duration-200 ${
            theme === 'dark' ? 'bg-slate-900 border-slate-800 text-slate-100' : 'bg-white border-slate-200 text-slate-800'
          }`}>
            <div className="flex items-center justify-between border-b border-inherit pb-4 mb-6">
              <h2 className="font-bold text-lg">My Corporate Profile</h2>
              <button
                onClick={() => setProfileOpen(false)}
                className="text-sm font-medium hover:text-emerald-500"
              >
                Close
              </button>
            </div>

            <div className="flex-1 space-y-6 overflow-y-auto pr-1">
              <div className="flex flex-col items-center">
                <div className="h-20 w-20 rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white text-3xl font-bold shadow-lg border-2 border-emerald-500 mb-3">
                  {user?.name.charAt(0)}
                </div>
                <h3 className="font-bold text-md">{user?.name}</h3>
                <span className="text-xs bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 px-2.5 py-0.5 rounded-full font-medium mt-1 uppercase">
                  {user?.role}
                </span>
              </div>

              <div className="space-y-4">
                <div>
                  <label className="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Corporate Email</label>
                  <p className="text-sm bg-slate-800/40 border border-slate-800 px-3 py-2 rounded-xl font-mono">{user?.email}</p>
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Department</label>
                  <p className="text-sm bg-slate-800/40 border border-slate-800 px-3 py-2 rounded-xl">{user?.department || 'Operations'}</p>
                </div>
                <div className="flex items-center justify-between p-3 border border-inherit rounded-xl bg-slate-800/20">
                  <div>
                    <h4 className="text-xs font-bold uppercase text-slate-400">Two-Factor Authentication</h4>
                    <p className="text-xs text-slate-300 mt-1">Strengthen login security checks.</p>
                  </div>
                  <span className={`h-2.5 w-2.5 rounded-full ${user?.twoFactorEnabled ? 'bg-emerald-500 animate-pulse' : 'bg-slate-600'}`}></span>
                </div>
              </div>

              <div className="pt-4 border-t border-inherit">
                <button
                  onClick={handleLogout}
                  className="w-full bg-rose-600/10 hover:bg-rose-600/20 border border-rose-500/30 text-rose-500 py-2.5 rounded-xl text-sm font-semibold transition flex items-center justify-center gap-2"
                >
                  <LogOut className="h-4 w-4" />
                  Logout Session
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* REGISTER NEW WORKSPACE MODAL */}
      {createWorkspaceModalOpen && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className={`w-full max-w-md rounded-2xl border p-6 shadow-2xl animate-in zoom-in-95 duration-200 ${
            theme === 'dark' ? 'bg-slate-900 border-slate-800 text-slate-100' : 'bg-white border-slate-200 text-slate-800'
          }`}>
            <div className="flex items-center justify-between border-b border-inherit pb-3 mb-4">
              <div className="flex items-center gap-2.5">
                <div className="h-8 w-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                  <Building2 className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="font-bold text-base">Register Business Workspace</h3>
                  <p className="text-xs text-slate-400">Launch an isolated multi-tenant organization</p>
                </div>
              </div>
              <button
                onClick={() => setCreateWorkspaceModalOpen(false)}
                className="p-1 rounded-lg hover:bg-slate-800 text-slate-400 hover:text-slate-200"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <form onSubmit={handleCreateWorkspace} className="space-y-4">
              <div>
                <label className="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">
                  Business / Company Name *
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Apex Global Solutions"
                  value={newWorkspaceName}
                  onChange={(e) => setNewWorkspaceName(e.target.value)}
                  className={`w-full px-3.5 py-2.5 rounded-xl text-sm border focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors ${
                    theme === 'dark'
                      ? 'bg-slate-800/80 border-slate-700 text-white placeholder-slate-500'
                      : 'bg-slate-50 border-slate-200 text-slate-800 placeholder-slate-400'
                  }`}
                />
              </div>

              <div>
                <label className="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">
                  Subscription Tier
                </label>
                <select
                  value={newWorkspaceTier}
                  onChange={(e) => setNewWorkspaceTier(e.target.value)}
                  className={`w-full px-3.5 py-2.5 rounded-xl text-sm border focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors ${
                    theme === 'dark'
                      ? 'bg-slate-800/80 border-slate-700 text-white'
                      : 'bg-slate-50 border-slate-200 text-slate-800'
                  }`}
                >
                  <option value="starter">Starter Plan (Up to 5 Users)</option>
                  <option value="pro">Pro Plan (Up to 25 Users, AI Enabled)</option>
                  <option value="enterprise">Enterprise Tier (Unlimited, WhatsApp + Voice AI)</option>
                </select>
              </div>

              <div className="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-400">
                You will automatically become the <strong>Business Owner</strong> with full administrative authority, isolated CRM data, and billing controls.
              </div>

              <div className="flex items-center justify-end gap-3 pt-2">
                <button
                  type="button"
                  onClick={() => setCreateWorkspaceModalOpen(false)}
                  className="px-4 py-2 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={isSubmittingWorkspace || !newWorkspaceName.trim()}
                  className="px-5 py-2.5 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white transition-colors disabled:opacity-50 flex items-center gap-2"
                >
                  {isSubmittingWorkspace ? (
                    <>
                      <div className="h-3.5 w-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                      Creating...
                    </>
                  ) : (
                    <>
                      <Plus className="h-3.5 w-3.5" />
                      Launch Workspace
                    </>
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* GLOBAL UNIFIED SEARCH MODAL */}
      <GlobalSearchModal
        isOpen={globalSearchOpen}
        onClose={() => setGlobalSearchOpen(false)}
        onNavigate={setActiveModule}
        theme={theme}
      />

      {/* NOTIFICATION CENTER SLIDEOUT */}
      <NotificationCenter
        isOpen={notifOpen}
        onClose={() => setNotifOpen(false)}
        theme={theme}
      />

      {/* INDUSTRY TEMPLATES MODAL */}
      <IndustryTemplatesModal
        isOpen={industryTemplatesModalOpen}
        onClose={() => setIndustryTemplatesModalOpen(false)}
      />

      {/* TOAST SYSTEM VIEWPORTS */}
      <div className="fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full">
        {toasts.map((toast) => (
          <div
            key={toast.id}
            className={`p-4 rounded-xl shadow-2xl border flex items-center justify-between gap-3 text-sm animate-in slide-in-from-bottom duration-200 ${
              toast.type === 'success'
                ? 'bg-emerald-950/90 border-emerald-800 text-emerald-300'
                : toast.type === 'error'
                ? 'bg-rose-950/90 border-rose-800 text-rose-300'
                : 'bg-slate-900/90 border-slate-800 text-slate-300'
            } backdrop-blur-md`}
          >
            <span>{toast.message}</span>
            <button
              onClick={() => removeToast(toast.id)}
              className="text-xs font-bold hover:text-white shrink-0 ml-4"
            >
              ✕
            </button>
          </div>
        ))}
      </div>

      {/* MOBILE PERSISTENT BOTTOM NAVIGATION BAR */}
      <MobileBottomNavBar
        currentView={activeModule}
        onNavigate={setActiveModule}
        onOpenMenu={() => setSidebarOpen(true)}
        isMenuOpen={sidebarOpen}
      />

    </div>
  );
}
