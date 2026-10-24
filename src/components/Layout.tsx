import React, { useState, useEffect } from 'react';
import { useCRMStore, Toast } from '../store/crmStore';
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
  Award
} from 'lucide-react';

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
    markNotificationRead
  } = useCRMStore();

  const [notifOpen, setNotifOpen] = useState(false);
  const [profileOpen, setProfileOpen] = useState(false);
  const [currentTime, setCurrentTime] = useState(new Date());

  useEffect(() => {
    const timer = setInterval(() => setCurrentTime(new Date()), 1000);
    return () => clearInterval(timer);
  }, []);

  const navItems = [
    { id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { id: 'leads', label: 'Leads Pipeline', icon: Layers },
    { id: 'deals', label: 'Deals & Pipeline', icon: Briefcase },
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
    { id: 'audit', label: 'Audit Log Trail', icon: Activity },
    { id: 'settings', label: 'System Settings', icon: Settings }
  ];

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
              Æ
            </div>
            {(sidebarOpen || !sidebarOpen) && (
              <span className={`font-semibold tracking-wide text-lg bg-gradient-to-r from-emerald-400 to-teal-500 bg-clip-text text-transparent truncate ${!sidebarOpen ? 'md:hidden' : ''}`}>
                Acme CRM
              </span>
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
          </div>

          {/* Header Right */}
          <div className="flex items-center gap-2 md:gap-3">
            {/* Theme Toggle */}
            <button
              onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
              className={`p-2 rounded-xl transition-colors border ${
                theme === 'dark' ? 'bg-slate-900 border-slate-800 text-emerald-400' : 'bg-slate-100 border-slate-200 text-slate-600'
              }`}
            >
              {theme === 'dark' ? <Sun className="h-4.5 w-4.5 md:h-5 md:w-5" /> : <Moon className="h-4.5 w-4.5 md:h-5 md:w-5" />}
            </button>

            {/* Notification Dropdown */}
            <div className="relative">
              <button
                onClick={() => setNotifOpen(!notifOpen)}
                className={`p-2 rounded-xl transition-colors border relative ${
                  theme === 'dark' ? 'bg-slate-900 border-slate-800 text-slate-300' : 'bg-slate-100 border-slate-200 text-slate-600'
                }`}
              >
                <Bell className="h-4.5 w-4.5 md:h-5 md:w-5" />
                <span className="absolute top-1 right-1 h-2 w-2 rounded-full bg-rose-500 ring-2 ring-inherit animate-pulse"></span>
              </button>

              {notifOpen && (
                <div className={`absolute right-0 mt-2 w-72 md:w-80 rounded-2xl border shadow-2xl p-4 z-40 ${
                  theme === 'dark' ? 'bg-slate-900 border-slate-800 text-slate-100' : 'bg-white border-slate-200 text-slate-800'
                }`}>
                  <h3 className="font-semibold text-sm border-b border-inherit pb-2 mb-2 flex justify-between items-center">
                    <span>Recent System Activity</span>
                    <span className="text-xs text-slate-400">Audit logs</span>
                  </h3>
                  <div className="space-y-3 max-h-60 overflow-y-auto">
                    {unreadNotifs.map((log) => (
                      <div key={log.id} className="text-xs border-b border-inherit pb-2 last:border-0 last:pb-0">
                        <div className="flex justify-between font-medium">
                          <span className="text-emerald-500">{log.action}</span>
                          <span className="text-slate-400 font-mono text-[10px]">
                            {new Date(log.createdAt).toLocaleTimeString()}
                          </span>
                        </div>
                        <p className={`mt-1 text-[11px] ${theme === 'dark' ? 'text-slate-300' : 'text-slate-600'}`}>{log.details}</p>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>

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
        <main className="flex-1 p-4 md:p-6 overflow-y-auto">
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

    </div>
  );
}
