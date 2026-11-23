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
  Building2,
  Award,
  Key,
  ShieldAlert,
  Sliders,
  Calendar as CalendarIcon,
  Copy,
  Check,
  Compass,
  FileText,
  Mail,
  Phone,
  ShieldCheck,
  UserPlus,
  History,
  Palette,
  CalendarDays,
  Lock,
  RefreshCw,
  Globe,
  Bell,
  X,
  LayoutGrid,
  List,
  GitMerge,
  ArrowRightLeft,
  Download,
  Upload,
  Eye,
  FileSpreadsheet
} from 'lucide-react';
import MediaFileManagerView from './common/MediaFileManagerView';
import CustomFieldsBuilder from './common/CustomFieldsBuilder';
import NotificationPreferencesView from './common/NotificationPreferencesView';
import AuditLogViewer from './common/AuditLogViewer';
import AiAssistantHub from './ai/AiAssistantHub';
import { AdvancedLeadHub } from './leads/AdvancedLeadHub';
import RoleManagementMatrixView from './common/RoleManagementMatrixView';
import Customer360Drawer from './common/Customer360Drawer';
import LeadConversionModal from './common/LeadConversionModal';
import LeadKanbanView from './common/LeadKanbanView';
import DuplicateDetectionModal from './common/DuplicateDetectionModal';
import LeadAcquisitionHubView from './common/LeadAcquisitionHubView';
import DealKanbanBoard from './common/DealKanbanBoard';
import TasksManagerView from './common/TasksManagerView';
import SalesTeamLeaderboardView from './common/SalesTeamLeaderboardView';
import ProductCatalogView from './common/ProductCatalogView';
import FinanceBillingHubView from './common/FinanceBillingHubView';
import WhatsAppInboxView from './common/WhatsAppInboxView';
import WhatsAppAiAgentBuilderView from './common/WhatsAppAiAgentBuilderView';
import VoiceTelephonyView from './common/VoiceTelephonyView';
import OmnichannelInboxView from './common/OmnichannelInboxView';
import { VisualWorkflowBuilderView } from './common/VisualWorkflowBuilderView';
import { LandingPageBuilderView } from './common/LandingPageBuilderView';
import { MarketingCampaignsView } from './common/MarketingCampaignsView';
import { HrmManagementView } from './common/HrmManagementView';
import { ProjectsTasksMeetingsView } from './common/ProjectsTasksMeetingsView';
import { SupportTicketHubView } from './common/SupportTicketHubView';
import { AnalyticsReportingHubView } from './common/AnalyticsReportingHubView';
import { IntegrationManagementHubView } from './common/IntegrationManagementHubView';
import { SocialChannelsManagerView } from './common/SocialChannelsManagerView';
import { SubscriptionBillingHubView } from './common/SubscriptionBillingHubView';
import { PlatformSuperAdminConsoleView } from './platform/PlatformSuperAdminConsoleView';
import { useAutoDistributeRoundRobin } from '../hooks/useCrmCore';
import { api } from '../api/client';
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
  const {
    currentWorkspace,
    updateWorkspaceSettings,
    workspaceMembers,
    fetchWorkspaceMembers,
    inviteWorkspaceMember,
    loginHistories,
    fetchLoginHistory
  } = useCRMStore();

  const [activeTab, setActiveTab] = useState<'profile' | 'regional' | 'team' | 'security' | 'custom_fields' | 'notifications'>('profile');

  // Business Profile & Branding State
  const [cName, setCName] = useState(settings?.companyName || currentWorkspace?.name || '');
  const [cEmail, setCEmail] = useState(settings?.companyEmail || '');
  const [cPhone, setCPhone] = useState(settings?.companyPhone || '');
  const [address, setAddress] = useState(settings?.address || '');
  const [gstin, setGstin] = useState(settings?.gstinTaxId || settings?.gstin_tax_id || '');
  const [logoUrl, setLogoUrl] = useState(settings?.logoUrl || settings?.logo_url || '');
  const [brandPrimary, setBrandPrimary] = useState(settings?.brandPrimaryColor || settings?.brand_primary_color || '#10b981');
  const [brandSecondary, setBrandSecondary] = useState(settings?.brandSecondaryColor || settings?.brand_secondary_color || '#0f172a');

  // Regional & Operating State
  const [curr, setCurr] = useState(settings?.currency || 'USD');
  const [tax, setTax] = useState(settings?.taxRate ?? settings?.tax_rate ?? 15);
  const [timezone, setTimezone] = useState(settings?.timezone || 'UTC');
  const [dateFormat, setDateFormat] = useState(settings?.dateFormat || settings?.date_format || 'YYYY-MM-DD');
  const [timeFormat, setTimeFormat] = useState(settings?.timeFormat || settings?.time_format || '24h');
  const [defaultCountry, setDefaultCountry] = useState(settings?.defaultCountry || settings?.default_country || 'US');
  const [workingDays, setWorkingDays] = useState<string[]>(
    settings?.workingDays || settings?.working_days || ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']
  );
  const [hoursStart, setHoursStart] = useState(settings?.businessHoursStart || settings?.business_hours_start || '09:00');
  const [hoursEnd, setHoursEnd] = useState(settings?.businessHoursEnd || settings?.business_hours_end || '18:00');

  // Team Invite State
  const [inviteEmail, setInviteEmail] = useState('');
  const [inviteRole, setInviteRole] = useState('Sales Executive');
  const [isInviting, setIsInviting] = useState(false);
  const [isSaving, setIsSaving] = useState(false);

  useEffect(() => {
    if (settings) {
      setCName(settings.companyName || currentWorkspace?.name || '');
      setCEmail(settings.companyEmail || '');
      setCPhone(settings.companyPhone || '');
      setAddress(settings.address || '');
      setGstin(settings.gstinTaxId || settings.gstin_tax_id || '');
      setLogoUrl(settings.logoUrl || settings.logo_url || '');
      setBrandPrimary(settings.brandPrimaryColor || settings.brand_primary_color || '#10b981');
      setBrandSecondary(settings.brandSecondaryColor || settings.brand_secondary_color || '#0f172a');
      setCurr(settings.currency || 'USD');
      setTax(settings.taxRate ?? settings.tax_rate ?? 15);
      setTimezone(settings.timezone || 'UTC');
      setDateFormat(settings.dateFormat || settings.date_format || 'YYYY-MM-DD');
      setTimeFormat(settings.timeFormat || settings.time_format || '24h');
      setDefaultCountry(settings.defaultCountry || settings.default_country || 'US');
      if (settings.workingDays || settings.working_days) {
        setWorkingDays(settings.workingDays || settings.working_days);
      }
      setHoursStart(settings.businessHoursStart || settings.business_hours_start || '09:00');
      setHoursEnd(settings.businessHoursEnd || settings.business_hours_end || '18:00');
    }
  }, [settings, currentWorkspace]);

  useEffect(() => {
    if (activeTab === 'team') {
      fetchWorkspaceMembers();
    } else if (activeTab === 'security') {
      fetchLoginHistory();
    }
  }, [activeTab]);

  const handleSaveProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSaving(true);
    await updateWorkspaceSettings({
      company_name: cName,
      company_email: cEmail,
      company_phone: cPhone,
      address,
      gstin_tax_id: gstin,
      logo_url: logoUrl,
      brand_primary_color: brandPrimary,
      brand_secondary_color: brandSecondary
    });
    // Also sync old settings format for legacy screens
    updateSettings({
      companyName: cName,
      companyEmail: cEmail,
      companyPhone: cPhone,
      taxRate: tax,
      currency: curr
    });
    setIsSaving(false);
  };

  const handleSaveRegional = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSaving(true);
    await updateWorkspaceSettings({
      currency: curr,
      tax_rate: tax,
      timezone,
      date_format: dateFormat,
      time_format: timeFormat,
      default_country: defaultCountry,
      working_days: workingDays,
      business_hours_start: hoursStart,
      business_hours_end: hoursEnd
    });
    updateSettings({
      companyName: cName,
      companyEmail: cEmail,
      companyPhone: cPhone,
      taxRate: tax,
      currency: curr
    });
    setIsSaving(false);
  };

  const handleInvite = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!inviteEmail.trim()) return;
    setIsInviting(true);
    const success = await inviteWorkspaceMember(inviteEmail.trim(), inviteRole);
    setIsInviting(false);
    if (success) {
      setInviteEmail('');
    }
  };

  const toggleDay = (day: string) => {
    if (workingDays.includes(day)) {
      setWorkingDays(workingDays.filter((d) => d !== day));
    } else {
      setWorkingDays([...workingDays, day]);
    }
  };

  const inputClass = `w-full px-3 py-2 text-sm rounded-xl border focus:ring-2 focus:ring-emerald-500 outline-hidden transition ${
    theme === 'dark'
      ? 'bg-slate-950/20 border-slate-700 text-slate-100 focus:border-slate-600'
      : 'bg-slate-50 border-slate-200 text-slate-800 focus:bg-white focus:border-slate-300'
  }`;

  const labelClass = "text-xs font-semibold text-slate-500 dark:text-slate-400 block mb-1";
  const daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

  return (
    <div className="space-y-6 max-w-4xl">
      {/* Settings Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100 flex items-center gap-2">
            <Building2 className="h-5 w-5 text-emerald-500" />
            <span>Workspace & Business Settings</span>
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Active Tenant: <strong className="text-emerald-500">{currentWorkspace?.name || 'Default Workspace'}</strong> ({currentWorkspace?.subscription_tier || 'Enterprise'} Tier)
          </p>
        </div>

        {/* Tab Navigation */}
        <div className={`flex items-center gap-1 p-1 rounded-xl border ${
          theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-slate-100 border-slate-200'
        }`}>
          <button
            onClick={() => setActiveTab('profile')}
            className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-all flex items-center gap-1.5 ${
              activeTab === 'profile'
                ? 'bg-emerald-600 text-white shadow-xs'
                : 'text-slate-400 hover:text-slate-200'
            }`}
          >
            <Palette className="h-3.5 w-3.5" />
            <span>Profile & Branding</span>
          </button>
          <button
            onClick={() => setActiveTab('regional')}
            className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-all flex items-center gap-1.5 ${
              activeTab === 'regional'
                ? 'bg-emerald-600 text-white shadow-xs'
                : 'text-slate-400 hover:text-slate-200'
            }`}
          >
            <Globe className="h-3.5 w-3.5" />
            <span>Regional & Hours</span>
          </button>
          <button
            onClick={() => setActiveTab('team')}
            className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-all flex items-center gap-1.5 ${
              activeTab === 'team'
                ? 'bg-emerald-600 text-white shadow-xs'
                : 'text-slate-400 hover:text-slate-200'
            }`}
          >
            <Users className="h-3.5 w-3.5" />
            <span>Team & Access</span>
          </button>
          <button
            onClick={() => setActiveTab('security')}
            className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-all flex items-center gap-1.5 ${
              activeTab === 'security'
                ? 'bg-emerald-600 text-white shadow-xs'
                : 'text-slate-400 hover:text-slate-200'
            }`}
          >
            <ShieldCheck className="h-3.5 w-3.5" />
            <span>Login Audits</span>
          </button>
          <button
            onClick={() => setActiveTab('custom_fields')}
            className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-all flex items-center gap-1.5 ${
              activeTab === 'custom_fields'
                ? 'bg-emerald-600 text-white shadow-xs'
                : 'text-slate-400 hover:text-slate-200'
            }`}
          >
            <Sliders className="h-3.5 w-3.5" />
            <span>Custom Fields</span>
          </button>
          <button
            onClick={() => setActiveTab('notifications')}
            className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-all flex items-center gap-1.5 ${
              activeTab === 'notifications'
                ? 'bg-emerald-600 text-white shadow-xs'
                : 'text-slate-400 hover:text-slate-200'
            }`}
          >
            <Bell className="h-3.5 w-3.5" />
            <span>Notifications</span>
          </button>
        </div>
      </div>

      {/* TAB 1: PROFILE & BRANDING */}
      {activeTab === 'profile' && (
        <form onSubmit={handleSaveProfile} className={`p-6 rounded-2xl border space-y-4 ${
          theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
        }`}>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className={labelClass}>Business Legal / Company Name</label>
              <input
                type="text"
                required
                value={cName}
                onChange={(e) => setCName(e.target.value)}
                className={inputClass}
                placeholder="e.g. Acme Enterprise Global Inc."
              />
            </div>
            <div>
              <label className={labelClass}>GSTIN / Corporate Tax ID</label>
              <input
                type="text"
                value={gstin}
                onChange={(e) => setGstin(e.target.value)}
                className={inputClass}
                placeholder="e.g. 29ABCDE1234F1Z5"
              />
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className={labelClass}>Official Company Email</label>
              <input
                type="email"
                value={cEmail}
                onChange={(e) => setCEmail(e.target.value)}
                className={inputClass}
                placeholder="contact@company.com"
              />
            </div>
            <div>
              <label className={labelClass}>Corporate Phone Line</label>
              <input
                type="text"
                value={cPhone}
                onChange={(e) => setCPhone(e.target.value)}
                className={inputClass}
                placeholder="+1 (555) 000-0000"
              />
            </div>
          </div>

          <div>
            <label className={labelClass}>Registered Business Address</label>
            <textarea
              rows={2}
              value={address}
              onChange={(e) => setAddress(e.target.value)}
              className={inputClass}
              placeholder="Suite 100, Nexus Business Tower, Financial District"
            />
          </div>

          <div>
            <label className={labelClass}>Workspace Logo URL</label>
            <input
              type="url"
              value={logoUrl}
              onChange={(e) => setLogoUrl(e.target.value)}
              className={inputClass}
              placeholder="https://example.com/logo.png"
            />
          </div>

          {/* Brand Colors */}
          <div className="pt-2">
            <label className={labelClass}>Workspace Brand Colors</label>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="flex items-center gap-3 p-3 rounded-xl border border-inherit">
                <input
                  type="color"
                  value={brandPrimary}
                  onChange={(e) => setBrandPrimary(e.target.value)}
                  className="h-9 w-9 rounded-lg border-0 cursor-pointer bg-transparent"
                />
                <div>
                  <p className="text-xs font-semibold">Primary Brand Color</p>
                  <p className="text-[11px] font-mono text-slate-400">{brandPrimary}</p>
                </div>
              </div>
              <div className="flex items-center gap-3 p-3 rounded-xl border border-inherit">
                <input
                  type="color"
                  value={brandSecondary}
                  onChange={(e) => setBrandSecondary(e.target.value)}
                  className="h-9 w-9 rounded-lg border-0 cursor-pointer bg-transparent"
                />
                <div>
                  <p className="text-xs font-semibold">Secondary Accent Color</p>
                  <p className="text-[11px] font-mono text-slate-400">{brandSecondary}</p>
                </div>
              </div>
            </div>
          </div>

          <div className="border-t border-slate-200 dark:border-slate-800 pt-4 mt-6 flex justify-end">
            <button
              type="submit"
              disabled={isSaving}
              className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-6 py-2 rounded-xl text-xs transition shadow-lg shadow-emerald-500/10 cursor-pointer flex items-center gap-2 disabled:opacity-50"
            >
              {isSaving ? 'Saving Changes...' : 'Save Profile & Branding'}
            </button>
          </div>
        </form>
      )}

      {/* TAB 2: REGIONAL & OPERATING HOURS */}
      {activeTab === 'regional' && (
        <form onSubmit={handleSaveRegional} className={`p-6 rounded-2xl border space-y-5 ${
          theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
        }`}>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label className={labelClass}>Global Currency</label>
              <select value={curr} onChange={(e) => setCurr(e.target.value)} className={inputClass}>
                <option value="USD" className="text-slate-800">USD ($)</option>
                <option value="EUR" className="text-slate-800">EUR (€)</option>
                <option value="GBP" className="text-slate-800">GBP (£)</option>
                <option value="INR" className="text-slate-800">INR (₹)</option>
                <option value="AED" className="text-slate-800">AED (د.إ)</option>
                <option value="CAD" className="text-slate-800">CAD ($)</option>
                <option value="AUD" className="text-slate-800">AUD ($)</option>
              </select>
            </div>
            <div>
              <label className={labelClass}>Standard Sales Tax (%)</label>
              <input
                type="number"
                step="0.01"
                value={tax}
                onChange={(e) => setTax(parseFloat(e.target.value) || 0)}
                className={inputClass}
              />
            </div>
            <div>
              <label className={labelClass}>Default Country</label>
              <select value={defaultCountry} onChange={(e) => setDefaultCountry(e.target.value)} className={inputClass}>
                <option value="US" className="text-slate-800">United States (US)</option>
                <option value="IN" className="text-slate-800">India (IN)</option>
                <option value="GB" className="text-slate-800">United Kingdom (GB)</option>
                <option value="AE" className="text-slate-800">United Arab Emirates (AE)</option>
                <option value="CA" className="text-slate-800">Canada (CA)</option>
                <option value="AU" className="text-slate-800">Australia (AU)</option>
                <option value="DE" className="text-slate-800">Germany (DE)</option>
                <option value="SG" className="text-slate-800">Singapore (SG)</option>
              </select>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label className={labelClass}>Timezone</label>
              <select value={timezone} onChange={(e) => setTimezone(e.target.value)} className={inputClass}>
                <option value="UTC" className="text-slate-800">UTC (Universal)</option>
                <option value="America/New_York" className="text-slate-800">Eastern Time (US & Canada)</option>
                <option value="America/Chicago" className="text-slate-800">Central Time (US & Canada)</option>
                <option value="America/Los_Angeles" className="text-slate-800">Pacific Time (US & Canada)</option>
                <option value="Europe/London" className="text-slate-800">London (GMT/BST)</option>
                <option value="Europe/Paris" className="text-slate-800">Paris (CET)</option>
                <option value="Asia/Kolkata" className="text-slate-800">India Standard Time (IST)</option>
                <option value="Asia/Dubai" className="text-slate-800">Gulf Standard Time (GST)</option>
                <option value="Asia/Singapore" className="text-slate-800">Singapore Time (SGT)</option>
              </select>
            </div>
            <div>
              <label className={labelClass}>Date Format</label>
              <select value={dateFormat} onChange={(e) => setDateFormat(e.target.value)} className={inputClass}>
                <option value="YYYY-MM-DD" className="text-slate-800">YYYY-MM-DD (2026-11-23)</option>
                <option value="DD/MM/YYYY" className="text-slate-800">DD/MM/YYYY (23/11/2026)</option>
                <option value="MM/DD/YYYY" className="text-slate-800">MM/DD/YYYY (11/23/2026)</option>
              </select>
            </div>
            <div>
              <label className={labelClass}>Time Format</label>
              <select value={timeFormat} onChange={(e) => setTimeFormat(e.target.value)} className={inputClass}>
                <option value="24h" className="text-slate-800">24 Hours (14:30)</option>
                <option value="12h" className="text-slate-800">12 Hours (02:30 PM)</option>
              </select>
            </div>
          </div>

          <div>
            <label className={labelClass}>Working Business Days</label>
            <div className="flex flex-wrap gap-2 pt-1">
              {daysOfWeek.map((day) => {
                const isSelected = workingDays.includes(day);
                return (
                  <button
                    type="button"
                    key={day}
                    onClick={() => toggleDay(day)}
                    className={`px-3 py-1.5 rounded-xl text-xs font-semibold transition-all border ${
                      isSelected
                        ? 'bg-emerald-600 border-emerald-500 text-white shadow-xs'
                        : theme === 'dark'
                        ? 'bg-slate-800/60 border-slate-700 text-slate-400 hover:text-slate-200'
                        : 'bg-slate-100 border-slate-200 text-slate-600 hover:text-slate-900'
                    }`}
                  >
                    {day.substring(0, 3)}
                  </button>
                );
              })}
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className={labelClass}>Business Hours Start</label>
              <input
                type="time"
                value={hoursStart}
                onChange={(e) => setHoursStart(e.target.value)}
                className={inputClass}
              />
            </div>
            <div>
              <label className={labelClass}>Business Hours End</label>
              <input
                type="time"
                value={hoursEnd}
                onChange={(e) => setHoursEnd(e.target.value)}
                className={inputClass}
              />
            </div>
          </div>

          <div className="border-t border-slate-200 dark:border-slate-800 pt-4 mt-6 flex justify-end">
            <button
              type="submit"
              disabled={isSaving}
              className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-6 py-2 rounded-xl text-xs transition shadow-lg shadow-emerald-500/10 cursor-pointer disabled:opacity-50"
            >
              {isSaving ? 'Saving Changes...' : 'Save Regional & Operations'}
            </button>
          </div>
        </form>
      )}

      {/* TAB 3: TEAM & INVITATIONS */}
      {activeTab === 'team' && (
        <div className="space-y-6">
          {/* Invite Form Card */}
          <form onSubmit={handleInvite} className={`p-5 rounded-2xl border space-y-3 ${
            theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
          }`}>
            <div className="flex items-center gap-2">
              <UserPlus className="h-4 w-4 text-emerald-500" />
              <h3 className="font-bold text-sm">Invite Team Member to Workspace</h3>
            </div>
            <p className="text-xs text-slate-400">
              Generate a secure invitation token and grant role permissions within {currentWorkspace?.name || 'this workspace'}.
            </p>
            <div className="grid grid-cols-1 sm:grid-cols-12 gap-3 pt-1">
              <div className="sm:col-span-6">
                <input
                  type="email"
                  required
                  placeholder="colleague@enterprise.com"
                  value={inviteEmail}
                  onChange={(e) => setInviteEmail(e.target.value)}
                  className={inputClass}
                />
              </div>
              <div className="sm:col-span-4">
                <select value={inviteRole} onChange={(e) => setInviteRole(e.target.value)} className={inputClass}>
                  <option value="Business Admin" className="text-slate-800">Business Admin</option>
                  <option value="Manager" className="text-slate-800">Manager</option>
                  <option value="Sales Executive" className="text-slate-800">Sales Executive</option>
                  <option value="Support Executive" className="text-slate-800">Support Executive</option>
                  <option value="HR" className="text-slate-800">HR Specialist</option>
                  <option value="Accountant" className="text-slate-800">Accountant</option>
                  <option value="Employee" className="text-slate-800">Employee</option>
                </select>
              </div>
              <div className="sm:col-span-2">
                <button
                  type="submit"
                  disabled={isInviting || !inviteEmail.trim()}
                  className="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-2 px-3 rounded-xl text-xs transition disabled:opacity-50"
                >
                  {isInviting ? 'Inviting...' : 'Send Invite'}
                </button>
              </div>
            </div>
          </form>

          {/* Members Roster Table */}
          <div className={`rounded-2xl border overflow-hidden ${
            theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
          }`}>
            <div className="p-4 border-b border-inherit flex items-center justify-between">
              <div>
                <h3 className="font-bold text-sm">Workspace Members</h3>
                <p className="text-xs text-slate-400">Active roster enrolled in this tenant</p>
              </div>
              <button
                onClick={fetchWorkspaceMembers}
                className="p-1.5 rounded-lg border border-inherit text-slate-400 hover:text-slate-200 transition-colors"
                title="Refresh roster"
              >
                <RefreshCw className="h-3.5 w-3.5" />
              </button>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead className={`border-b border-inherit ${
                  theme === 'dark' ? 'bg-slate-950/40 text-slate-400' : 'bg-slate-50 text-slate-500'
                }`}>
                  <tr>
                    <th className="py-2.5 px-4 font-semibold">User</th>
                    <th className="py-2.5 px-4 font-semibold">Workspace Role</th>
                    <th className="py-2.5 px-4 font-semibold">Status</th>
                    <th className="py-2.5 px-4 font-semibold">Joined At</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-inherit">
                  {workspaceMembers.length > 0 ? (
                    workspaceMembers.map((member: any) => (
                      <tr key={member.id} className="hover:bg-slate-800/30 transition-colors">
                        <td className="py-3 px-4">
                          <div className="flex items-center gap-2.5">
                            <div className="h-7 w-7 rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white font-bold text-xs">
                              {member.name ? member.name.charAt(0).toUpperCase() : 'U'}
                            </div>
                            <div>
                              <p className="font-semibold text-slate-200">{member.name}</p>
                              <p className="text-[11px] text-slate-400 font-mono">{member.email}</p>
                            </div>
                          </div>
                        </td>
                        <td className="py-3 px-4">
                          <span className={`px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider ${
                            member.is_owner
                              ? 'bg-amber-500/15 text-amber-400 border border-amber-500/30'
                              : 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'
                          }`}>
                            {member.role || 'Member'} {member.is_owner ? '★ Owner' : ''}
                          </span>
                        </td>
                        <td className="py-3 px-4">
                          <span className="flex items-center gap-1.5 text-emerald-400 font-medium">
                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                            {member.status || 'Active'}
                          </span>
                        </td>
                        <td className="py-3 px-4 font-mono text-slate-400">
                          {member.joined_at ? new Date(member.joined_at).toLocaleDateString() : 'N/A'}
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={4} className="py-8 text-center text-slate-400">
                        No team members loaded.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 4: SECURITY & LOGIN AUDITS */}
      {activeTab === 'security' && (
        <div className={`rounded-2xl border overflow-hidden ${
          theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
        }`}>
          <div className="p-4 border-b border-inherit flex items-center justify-between">
            <div>
              <h3 className="font-bold text-sm flex items-center gap-2">
                <ShieldAlert className="h-4 w-4 text-emerald-500" />
                <span>Authentication & Login Audit Trail</span>
              </h3>
              <p className="text-xs text-slate-400">
                Tamper-evident logs of recent authentication events, device signatures, and IP addresses
              </p>
            </div>
            <button
              onClick={fetchLoginHistory}
              className="p-1.5 rounded-lg border border-inherit text-slate-400 hover:text-slate-200 transition-colors"
              title="Refresh logs"
            >
              <RefreshCw className="h-3.5 w-3.5" />
            </button>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className={`border-b border-inherit ${
                theme === 'dark' ? 'bg-slate-950/40 text-slate-400' : 'bg-slate-50 text-slate-500'
              }`}>
                <tr>
                  <th className="py-2.5 px-4 font-semibold">Status</th>
                  <th className="py-2.5 px-4 font-semibold">IP Address</th>
                  <th className="py-2.5 px-4 font-semibold">Device / Client</th>
                  <th className="py-2.5 px-4 font-semibold">Timestamp</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-inherit">
                {loginHistories.length > 0 ? (
                  loginHistories.map((log: any) => (
                    <tr key={log.id} className="hover:bg-slate-800/30 transition-colors">
                      <td className="py-3 px-4">
                        <span className={`px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase ${
                          log.status === 'success'
                            ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'
                            : 'bg-rose-500/15 text-rose-400 border border-rose-500/30'
                        }`}>
                          {log.status}
                        </span>
                      </td>
                      <td className="py-3 px-4 font-mono text-slate-300">
                        {log.ip_address || '127.0.0.1'}
                      </td>
                      <td className="py-3 px-4">
                        <span className="font-semibold text-slate-200 uppercase text-[10px] bg-slate-800 px-1.5 py-0.5 rounded mr-2">
                          {log.device_type || 'Desktop'}
                        </span>
                        <span className="text-slate-400 truncate max-w-xs inline-block align-bottom">
                          {log.user_agent ? log.user_agent.substring(0, 45) + '...' : 'Browser Client'}
                        </span>
                      </td>
                      <td className="py-3 px-4 font-mono text-slate-400">
                        {log.created_at ? new Date(log.created_at).toLocaleString() : 'Just now'}
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={4} className="py-8 text-center text-slate-400">
                      No recent login entries recorded for this session.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 5: CUSTOM FIELDS BUILDER */}
      {activeTab === 'custom_fields' && (
        <CustomFieldsBuilder theme={theme} />
      )}

      {/* TAB 6: NOTIFICATION PREFERENCES */}
      {activeTab === 'notifications' && (
        <NotificationPreferencesView theme={theme} />
      )}

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

  // CRM Core States
  const [leadsViewMode, setLeadsViewMode] = useState<'table' | 'kanban' | 'advanced'>('table');
  const [dealViewMode, setDealViewMode] = useState<'kanban' | 'table'>('kanban');
  const [convertingLead, setConvertingLead] = useState<any>(null);
  const [duplicateCheckLead, setDuplicateCheckLead] = useState<any>(null);
  const [selectedCustomer360Id, setSelectedCustomer360Id] = useState<number | string | null>(null);
  const [isExportingCsv, setIsExportingCsv] = useState(false);
  const [isImportingCsv, setIsImportingCsv] = useState(false);

  const autoDistributeMutation = useAutoDistributeRoundRobin();

  const handleExportLeadsCsv = async () => {
    try {
      setIsExportingCsv(true);
      const csv = await api.exportLeadsCsv();
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `leads_export_${new Date().toISOString().split('T')[0]}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      useCRMStore.getState().addToast('Leads exported successfully.', 'success');
    } catch (e: any) {
      useCRMStore.getState().addToast(e.message || 'Export failed', 'error');
    } finally {
      setIsExportingCsv(false);
    }
  };

  const handleImportLeadsCsv = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    try {
      setIsImportingCsv(true);
      const text = await file.text();
      const res = await api.importLeadsCsv(text);
      useCRMStore.getState().addToast(`Imported ${res.data?.imported_count || 0} leads (${res.data?.skipped_count || 0} skipped).`, 'success');
      useCRMStore.getState().fetchModuleRecords('leads');
    } catch (e: any) {
      useCRMStore.getState().addToast(e.message || 'Import failed', 'error');
    } finally {
      setIsImportingCsv(false);
      e.target.value = '';
    }
  };

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
        key: 'score',
        label: 'Score & Qualification',
        render: (val: any, row: any) => {
          const score = val ?? row.score ?? 50;
          const qStatus = row.qualification_status || 'unqualified';
          const color = score >= 70 ? 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20' : score >= 40 ? 'text-amber-400 bg-amber-500/10 border-amber-500/20' : 'text-rose-400 bg-rose-500/10 border-rose-500/20';
          return (
            <div className="flex items-center gap-1.5">
              <span className={`px-2 py-0.5 rounded-md font-mono text-xs font-bold border ${color}`}>
                {score}/100
              </span>
              <span className="text-[10px] text-slate-500 capitalize">{qStatus}</span>
            </div>
          );
        }
      },
      {
        key: 'status',
        label: 'Status',
        render: (val: string) => {
          const color = val === 'qualified' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' : val === 'converted' ? 'bg-teal-500/10 text-teal-600 dark:text-teal-400 border-teal-500/20' : val === 'lost' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20' : 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20';
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
          { value: 'attempted', label: 'Attempted' },
          { value: 'contacted', label: 'Contacted' },
          { value: 'qualified', label: 'Qualified' },
          { value: 'nurturing', label: 'Nurturing' },
          { value: 'converted', label: 'Converted' },
          { value: 'lost', label: 'Lost' }
        ]
      }
    ];

    const handleAIEvaluation = (row: any) => {
      setActiveAILeadId(row.id);
      analyzeLeadWithAI(row.id);
    };

    const leadsList = moduleData.leads || [];

    return (
      <div className="space-y-6">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h1 className="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Leads Acquisition & Conversion Pipeline</h1>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Convert high value target enterprise accounts with smart scoring, round-robin distribution and duplicate resolution.</p>
          </div>

          <div className="flex items-center gap-2 flex-wrap">
            {/* View Mode Toggle */}
            <div className={`p-0.5 rounded-xl border flex items-center ${theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-slate-100 border-slate-200'}`}>
              <button
                onClick={() => setLeadsViewMode('table')}
                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition ${
                  leadsViewMode === 'table'
                    ? 'bg-emerald-600 text-white shadow-xs'
                    : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                }`}
              >
                <List className="h-3.5 w-3.5" />
                <span>Table</span>
              </button>
              <button
                onClick={() => setLeadsViewMode('kanban')}
                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition ${
                  leadsViewMode === 'kanban'
                    ? 'bg-emerald-600 text-white shadow-xs'
                    : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                }`}
              >
                <LayoutGrid className="h-3.5 w-3.5" />
                <span>Kanban</span>
              </button>
              <button
                onClick={() => setLeadsViewMode('advanced')}
                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition ${
                  leadsViewMode === 'advanced'
                    ? 'bg-blue-600 text-white shadow-xs'
                    : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                }`}
              >
                <ShieldAlert className="h-3.5 w-3.5" />
                <span>SLA & Portals</span>
              </button>
            </div>

            {/* Round-Robin Auto-Distribute */}
            <button
              onClick={() => autoDistributeMutation.mutate()}
              disabled={autoDistributeMutation.isPending}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-bold transition ${
                theme === 'dark'
                  ? 'bg-slate-900 border-slate-800 text-slate-200 hover:bg-slate-800'
                  : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'
              }`}
              title="Automatically distribute unassigned leads to active sales reps"
            >
              <RefreshCw className={`h-3.5 w-3.5 text-emerald-500 ${autoDistributeMutation.isPending ? 'animate-spin' : ''}`} />
              <span>Round-Robin</span>
            </button>

            {/* CSV Export */}
            <button
              onClick={handleExportLeadsCsv}
              disabled={isExportingCsv}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-bold transition ${
                theme === 'dark'
                  ? 'bg-slate-900 border-slate-800 text-slate-200 hover:bg-slate-800'
                  : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'
              }`}
            >
              <Download className="h-3.5 w-3.5 text-emerald-500" />
              <span>{isExportingCsv ? 'Exporting...' : 'Export'}</span>
            </button>

            {/* CSV Import */}
            <label
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-bold cursor-pointer transition ${
                theme === 'dark'
                  ? 'bg-slate-900 border-slate-800 text-slate-200 hover:bg-slate-800'
                  : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'
              }`}
            >
              <Upload className="h-3.5 w-3.5 text-emerald-500" />
              <span>{isImportingCsv ? 'Importing...' : 'Import'}</span>
              <input
                type="file"
                accept=".csv"
                onChange={handleImportLeadsCsv}
                className="hidden"
                disabled={isImportingCsv}
              />
            </label>
          </div>
        </div>

        {leadsViewMode === 'advanced' ? (
          <AdvancedLeadHub />
        ) : leadsViewMode === 'kanban' ? (
          <LeadKanbanView
            leads={leadsList}
            onConvert={(lead) => setConvertingLead(lead)}
            onEdit={(lead) => triggerEdit('leads', lead)}
            theme={theme}
          />
        ) : (
          <ModuleTable
            module="leads"
            columns={leadColumns}
            filtersConfig={leadFilters}
            onAddClick={() => triggerAdd('leads')}
            onEditClick={(row) => triggerEdit('leads', row)}
            onDeleteClick={(row) => useCRMStore.getState().deleteRecord('leads', row.id)}
            customActions={(row) => (
              <div className="flex items-center gap-1">
                {row.status !== 'converted' && (
                  <button
                    onClick={() => setConvertingLead(row)}
                    className="text-xs bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 px-2 py-1 rounded-lg font-bold flex items-center gap-1 transition"
                    title="Convert into Contact, Company & Deal"
                  >
                    <ArrowRightLeft className="h-3 w-3" />
                    <span>Convert</span>
                  </button>
                )}
                <button
                  onClick={() => setDuplicateCheckLead(row)}
                  className="text-xs bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/20 px-2 py-1 rounded-lg font-bold flex items-center gap-1 transition"
                  title="Detect & Merge Duplicates"
                >
                  <GitMerge className="h-3 w-3" />
                  <span>Duplicates</span>
                </button>
                <button
                  onClick={() => handleAIEvaluation(row)}
                  className="text-xs bg-purple-600/10 hover:bg-purple-600/20 text-purple-400 border border-purple-500/20 px-2 py-1 rounded-lg font-bold flex items-center gap-1 transition"
                  title="Generate AI Lead Score Playbook"
                >
                  <Sparkles className="h-3 w-3" />
                  <span>AI Score</span>
                </button>
              </div>
            )}
          />
        )}

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

        {/* Lead Conversion Modal */}
        <LeadConversionModal
          lead={convertingLead}
          isOpen={!!convertingLead}
          onClose={() => setConvertingLead(null)}
          theme={theme}
        />

        {/* Duplicate Detection & Merge Modal */}
        <DuplicateDetectionModal
          masterLead={duplicateCheckLead}
          isOpen={!!duplicateCheckLead}
          onClose={() => setDuplicateCheckLead(null)}
          theme={theme}
        />

        {/* Customer 360° Profile Drawer */}
        <Customer360Drawer
          customerId={selectedCustomer360Id}
          isOpen={!!selectedCustomer360Id}
          onClose={() => setSelectedCustomer360Id(null)}
          theme={theme}
        />

        <ModuleModals module={modalModule} isOpen={modalOpen} onClose={() => setModalOpen(false)} editItem={editingItem} />
      </div>
    );
  }

  // --- LEAD ACQUISITION HUB VIEW ---
  if (activeModule === 'lead_acquisition') {
    return <LeadAcquisitionHubView theme={theme} />;
  }

  // --- SALES TEAMS & QUOTAS VIEW ---
  if (activeModule === 'sales_teams') {
    return <SalesTeamLeaderboardView theme={theme} />;
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
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h1 className="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Sales Deals & Pipelines</h1>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Track strategic corporate accounts through custom multi-pipeline stages, weighted forecasts and close workflows.</p>
          </div>

          <div className="flex items-center gap-1 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
            <button
              onClick={() => setDealViewMode('kanban')}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                dealViewMode === 'kanban'
                  ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm'
                  : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
              }`}
            >
              <LayoutGrid className="w-3.5 h-3.5" />
              Kanban Board
            </button>
            <button
              onClick={() => setDealViewMode('table')}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                dealViewMode === 'table'
                  ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm'
                  : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
              }`}
            >
              <List className="w-3.5 h-3.5" />
              Data Table
            </button>
          </div>
        </div>

        {dealViewMode === 'kanban' ? (
          <DealKanbanBoard
            theme={theme}
            onNewDeal={() => triggerAdd('deals')}
          />
        ) : (
          <ModuleTable
            module="deals"
            columns={dealColumns}
            onAddClick={() => triggerAdd('deals')}
            onEditClick={(row) => triggerEdit('deals', row)}
            onDeleteClick={(row) => useCRMStore.getState().deleteRecord('deals', row.id)}
          />
        )}
        <ModuleModals module={modalModule} isOpen={modalOpen} onClose={() => setModalOpen(false)} editItem={editingItem} />
      </div>
    );
  }

  // --- HR SYSTEM VIEW ---
  if (activeModule === 'employees') {
    return <HrmManagementView theme={theme} />;
  }

  // --- SUPPORT TICKETS VIEW ---
  if (activeModule === 'tickets') {
    return <SupportTicketHubView />;
  }

  // --- ANALYTICS, ATTRIBUTION & REPORTS VIEW ---
  if (activeModule === 'analytics' || activeModule === 'reports') {
    return <AnalyticsReportingHubView />;
  }

  // --- INTEGRATIONS, API KEYS & WEBHOOK PLATFORM VIEW ---
  if (activeModule === 'integrations') {
    return <IntegrationManagementHubView />;
  }

  // --- FACEBOOK MESSENGER & INSTAGRAM DIRECT MESSAGES ---
  if (activeModule === 'social' || activeModule === 'meta' || activeModule === 'messenger') {
    return <SocialChannelsManagerView />;
  }

  // --- SAAS SUBSCRIPTIONS, HYBRID BILLING & CREDIT LEDGER VIEW ---
  if (activeModule === 'billing') {
    return <SubscriptionBillingHubView />;
  }

  // --- SEPARATE PLATFORM SUPER ADMIN COMMAND CONSOLE VIEW ---
  if (activeModule === 'platform_console') {
    return <PlatformSuperAdminConsoleView />;
  }

  // --- ROLES & PERMISSIONS VIEW (ENTERPRISE RBAC & DATA SCOPES) ---
  if (activeModule === 'roles') {
    return <RoleManagementMatrixView theme={theme} />;
  }

  // --- MEDIA & FILES MANAGER ---
  if (activeModule === 'files') {
    return (
      <MediaFileManagerView theme={theme} />
    );
  }

  // --- AUDIT TRAIL LOGS ---
  if (activeModule === 'audit') {
    return (
      <AuditLogViewer theme={theme} />
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
  }

  if (activeModule === 'ai_assistant' || activeModule === 'ai') {
    return <AiAssistantHub isDark={theme === 'dark'} />;
  }

  if (activeModule === 'advanced_leads' || activeModule === 'lead_leakage') {
    return <AdvancedLeadHub />;
  }

  if (activeModule === 'landing_pages') {
    return <LandingPageBuilderView theme={theme} />;
  }

  if (activeModule === 'marketing') {
    return <MarketingCampaignsView theme={theme} />;
  }

  if (activeModule === 'workflows') {
    return <VisualWorkflowBuilderView theme={theme} />;
  }

  if (activeModule === 'inbox') {
    return <OmnichannelInboxView theme={theme} />;
  }

  if (activeModule === 'whatsapp') {
    return <WhatsAppInboxView theme={theme} />;
  }

  if (activeModule === 'whatsapp_ai') {
    return <WhatsAppAiAgentBuilderView theme={theme} />;
  }

  if (activeModule === 'voice') {
    return <VoiceTelephonyView theme={theme} />;
  }

  if (activeModule === 'products') {
    return <ProductCatalogView theme={theme} />;
  }

  if (activeModule === 'quotations') {
    return <FinanceBillingHubView defaultTab="quotations" theme={theme} />;
  }

  if (activeModule === 'invoices') {
    return <FinanceBillingHubView defaultTab="invoices" theme={theme} />;
  }

  if (activeModule === 'projects' || activeModule === 'tasks' || activeModule === 'meetings') {
    return <ProjectsTasksMeetingsView />;
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
        customActions={
          activeModule === 'customers'
            ? (row) => (
                <button
                  onClick={() => setSelectedCustomer360Id(row.id)}
                  className="text-xs bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 px-2 py-1 rounded-lg font-bold flex items-center gap-1 transition"
                  title="View Customer 360° Profile"
                >
                  <Eye className="h-3 w-3" />
                  <span>360° Profile</span>
                </button>
              )
            : undefined
        }
      />

      {/* Customer 360° Profile Drawer */}
      <Customer360Drawer
        customerId={selectedCustomer360Id}
        isOpen={!!selectedCustomer360Id}
        onClose={() => setSelectedCustomer360Id(null)}
        theme={theme}
      />

      <ModuleModals module={modalModule} isOpen={modalOpen} onClose={() => setModalOpen(false)} editItem={editingItem} />
    </div>
  );
}
