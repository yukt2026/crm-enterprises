import { create } from 'zustand';
import {
  User,
  Lead,
  Company,
  Customer,
  Contact,
  Deal,
  Product,
  Service,
  Quotation,
  Invoice,
  Payment,
  Project,
  Task,
  Meeting,
  Employee,
  Attendance,
  Leave,
  SupportTicket,
  ActivityLog,
  Notification,
  SystemSettings,
  UserRole
} from '../types';

export interface Toast {
  id: string;
  message: string;
  type: 'success' | 'error' | 'info';
}

interface CRMStoreState {
  // Auth State
  user: User | null;
  token: string | null;
  isLoggedIn: boolean;
  requires2FA: boolean;
  userId2FA: string | null;
  loginMessage: string | null;
  rolesPermissions: any[];

  // UI State
  theme: 'light' | 'dark';
  sidebarOpen: boolean;
  toasts: Toast[];

  // Module Listings Cache
  moduleData: {
    leads: Lead[];
    companies: Company[];
    customers: Customer[];
    contacts: Contact[];
    deals: Deal[];
    products: Product[];
    services: Service[];
    quotations: Quotation[];
    invoices: Invoice[];
    payments: Payment[];
    projects: Project[];
    tasks: Task[];
    meetings: Meeting[];
    employees: Employee[];
    attendance: Attendance[];
    leaves: Leave[];
    tickets: SupportTicket[];
    users: User[];
    activityLogs: ActivityLog[];
  };

  // Report Metrics state
  reports: {
    summary: {
      totalLeads: number;
      activeLeadsCount: number;
      totalDeals: number;
      pipelineValue: number;
      closedWonValue: number;
      totalInvoices: number;
      totalReceivedRevenue: number;
      overdueInvoicesValue: number;
      openTickets: number;
      activeProjects: number;
      averageProjectProgress: number;
    } | null;
    charts: {
      monthlyRevenue: any[];
      leadsDistribution: any[];
      pipelineDistribution: any[];
    } | null;
  };

  // Pagination / Filter states
  isLoading: boolean;
  totalRecords: number;
  currentPage: number;
  totalPages: number;
  activeFilters: Record<string, string>;
  searchQuery: string;

  // AI Outputs State
  aiLeadAnalysis: Record<string, string>; // leadId -> analysis md
  aiTicketReply: Record<string, string>;  // ticketId -> reply email text

  // Settings
  settings: SystemSettings | null;

  // Actions
  addToast: (message: string, type?: 'success' | 'error' | 'info') => void;
  removeToast: (id: string) => void;
  setTheme: (theme: 'light' | 'dark') => void;
  setSidebarOpen: (open: boolean) => void;
  setSearchQuery: (query: string) => void;
  setFilters: (filters: Record<string, string>) => void;
  clearFilters: () => void;

  // Auth Actions
  login: (email: string, password: string) => Promise<boolean>;
  verify2FA: (userId: string, code: string) => Promise<boolean>;
  register: (name: string, email: string, passwordHash: string, companyName: string) => Promise<boolean>;
  logout: () => void;
  fetchProfile: () => Promise<void>;
  updateProfile: (data: Partial<User> & { avatar?: string }) => Promise<void>;
  changePassword: (current: string, newPass: string) => Promise<boolean>;
  forgotPassword: (email: string) => Promise<boolean>;

  // Data Actions
  fetchModuleRecords: (module: string, page?: number, limit?: number) => Promise<void>;
  addRecord: (module: string, recordData: any) => Promise<any>;
  updateRecord: (module: string, id: string, recordData: any) => Promise<any>;
  deleteRecord: (module: string, id: string) => Promise<boolean>;
  bulkDeleteRecords: (module: string, ids: string[]) => Promise<boolean>;
  importBackup: (module: string, data: any[]) => Promise<boolean>;
  exportBackup: (module: string, format?: 'json' | 'csv') => Promise<void>;
  fetchReports: () => Promise<void>;
  fetchRolesPermissions: () => Promise<void>;
  fetchSettings: () => Promise<void>;
  updateSettings: (data: Partial<SystemSettings>) => Promise<void>;

  // Custom Actions
  approveLeave: (id: string, status: 'approved' | 'rejected') => Promise<boolean>;
  clockIn: () => Promise<boolean>;
  clockOut: () => Promise<boolean>;
  markNotificationRead: (id: string) => Promise<void>;

  // AI Co-Pilot Actions
  analyzeLeadWithAI: (leadId: string) => Promise<void>;
  draftTicketReplyWithAI: (ticketId: string) => Promise<void>;
}

export const useCRMStore = create<CRMStoreState>((set, get) => {
  // Helper for requests
  const apiFetch = async (url: string, options: RequestInit = {}) => {
    const { token, user } = get();
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      ...(options.headers as Record<string, string> || {})
    };

    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }
    if (user?.id) {
      headers['X-User-Id'] = user.id;
    }

    const res = await fetch(url, { ...options, headers });
    if (!res.ok) {
      const errData = await res.json().catch(() => ({}));
      throw new Error(errData.error || `HTTP error ${res.status}`);
    }
    return res.json();
  };

  return {
    // Initial State
    user: JSON.parse(localStorage.getItem('crm_user') || 'null'),
    token: localStorage.getItem('crm_token'),
    isLoggedIn: !!localStorage.getItem('crm_token'),
    requires2FA: false,
    userId2FA: null,
    loginMessage: null,
    rolesPermissions: [],

    theme: (localStorage.getItem('crm_theme') as 'light' | 'dark') || 'dark',
    sidebarOpen: true,
    toasts: [],

    moduleData: {
      leads: [],
      companies: [],
      customers: [],
      contacts: [],
      deals: [],
      products: [],
      services: [],
      quotations: [],
      invoices: [],
      payments: [],
      projects: [],
      tasks: [],
      meetings: [],
      employees: [],
      attendance: [],
      leaves: [],
      tickets: [],
      users: [],
      activityLogs: []
    },

    reports: {
      summary: null,
      charts: null
    },

    isLoading: false,
    totalRecords: 0,
    currentPage: 1,
    totalPages: 1,
    activeFilters: {},
    searchQuery: '',

    aiLeadAnalysis: {},
    aiTicketReply: {},
    settings: null,

    // UI actions
    addToast: (message, type = 'success') => {
      const id = `${Date.now()}`;
      set((state) => ({
        toasts: [...state.toasts, { id, message, type }]
      }));
      // Auto dismiss
      setTimeout(() => {
        get().removeToast(id);
      }, 5000);
    },

    removeToast: (id) => {
      set((state) => ({
        toasts: state.toasts.filter((t) => t.id !== id)
      }));
    },

    setTheme: (theme) => {
      localStorage.setItem('crm_theme', theme);
      document.documentElement.classList.toggle('dark', theme === 'dark');
      set({ theme });
    },

    setSidebarOpen: (sidebarOpen) => set({ sidebarOpen }),
    setSearchQuery: (searchQuery) => set({ searchQuery }),
    setFilters: (activeFilters) => set({ activeFilters }),
    clearFilters: () => set({ activeFilters: {} }),

    // Auth actions
    login: async (email, password) => {
      set({ isLoading: true });
      try {
        const res = await apiFetch('/api/auth/login', {
          method: 'POST',
          body: JSON.stringify({ email, password })
        });

        if (res.requires2FA) {
          set({
            requires2FA: true,
            userId2FA: res.userId,
            loginMessage: res.message,
            isLoading: false
          });
          get().addToast('Two-Factor Code Required', 'info');
          return false;
        }

        localStorage.setItem('crm_token', res.token);
        localStorage.setItem('crm_user', JSON.stringify(res.user));
        set({
          user: res.user,
          token: res.token,
          isLoggedIn: true,
          requires2FA: false,
          userId2FA: null,
          isLoading: false
        });
        get().addToast(`Welcome back, ${res.user.name}!`, 'success');
        return true;
      } catch (err: any) {
        set({ isLoading: false });
        get().addToast(err.message, 'error');
        return false;
      }
    },

    verify2FA: async (userId, code) => {
      set({ isLoading: true });
      try {
        const res = await apiFetch('/api/auth/verify-2fa', {
          method: 'POST',
          body: JSON.stringify({ userId, code })
        });

        localStorage.setItem('crm_token', res.token);
        localStorage.setItem('crm_user', JSON.stringify(res.user));
        set({
          user: res.user,
          token: res.token,
          isLoggedIn: true,
          requires2FA: false,
          userId2FA: null,
          isLoading: false
        });
        get().addToast('Two-factor Authentication verified successfully.', 'success');
        return true;
      } catch (err: any) {
        set({ isLoading: false });
        get().addToast(err.message, 'error');
        return false;
      }
    },

    register: async (name, email, password, companyName) => {
      set({ isLoading: true });
      try {
        const res = await apiFetch('/api/auth/register', {
          method: 'POST',
          body: JSON.stringify({ name, email, password, companyName })
        });
        set({ isLoading: false });
        get().addToast(res.message, 'success');
        return true;
      } catch (err: any) {
        set({ isLoading: false });
        get().addToast(err.message, 'error');
        return false;
      }
    },

    logout: () => {
      localStorage.removeItem('crm_token');
      localStorage.removeItem('crm_user');
      set({
        user: null,
        token: null,
        isLoggedIn: false,
        requires2FA: false,
        userId2FA: null
      });
      get().addToast('You have logged out successfully', 'success');
    },

    fetchProfile: async () => {
      try {
        const user = await apiFetch('/api/auth/profile');
        set({ user });
        localStorage.setItem('crm_user', JSON.stringify(user));
      } catch (err: any) {
        console.error('Profile fetch failed', err);
      }
    },

    updateProfile: async (data) => {
      set({ isLoading: true });
      try {
        const updatedUser = await apiFetch('/api/auth/profile', {
          method: 'PUT',
          body: JSON.stringify(data)
        });
        set({ user: updatedUser, isLoading: false });
        localStorage.setItem('crm_user', JSON.stringify(updatedUser));
        get().addToast('Profile updated successfully', 'success');
      } catch (err: any) {
        set({ isLoading: false });
        get().addToast(err.message, 'error');
      }
    },

    changePassword: async (currentPassword, newPassword) => {
      set({ isLoading: true });
      try {
        await apiFetch('/api/auth/change-password', {
          method: 'PUT',
          body: JSON.stringify({ currentPassword, newPassword })
        });
        set({ isLoading: false });
        get().addToast('Password updated successfully', 'success');
        return true;
      } catch (err: any) {
        set({ isLoading: false });
        get().addToast(err.message, 'error');
        return false;
      }
    },

    forgotPassword: async (email) => {
      set({ isLoading: true });
      try {
        const res = await apiFetch('/api/auth/forgot-password', {
          method: 'POST',
          body: JSON.stringify({ email })
        });
        set({ isLoading: false });
        get().addToast(res.message, 'success');
        return true;
      } catch (err: any) {
        set({ isLoading: false });
        get().addToast(err.message, 'error');
        return false;
      }
    },

    // CRUD module records fetcher
    fetchModuleRecords: async (module, page = 1, limit = 10) => {
      set({ isLoading: true });
      try {
        const filters = get().activeFilters;
        const search = get().searchQuery;
        
        let queryParams = `?page=${page}&limit=${limit}`;
        if (search) queryParams += `&q=${encodeURIComponent(search)}`;
        
        Object.keys(filters).forEach((key) => {
          if (filters[key]) {
            queryParams += `&${key}=${encodeURIComponent(filters[key])}`;
          }
        });

        const res = await apiFetch(`/api/crm/${module}${queryParams}`);
        
        set((state) => ({
          moduleData: {
            ...state.moduleData,
            [module]: res.data
          },
          totalRecords: res.total,
          currentPage: res.page,
          totalPages: res.totalPages,
          isLoading: false
        }));
      } catch (err: any) {
        set({ isLoading: false });
        get().addToast(`Failed to load ${module}: ${err.message}`, 'error');
      }
    },

    addRecord: async (module, recordData) => {
      set({ isLoading: true });
      try {
        const newItem = await apiFetch(`/api/crm/${module}`, {
          method: 'POST',
          body: JSON.stringify(recordData)
        });
        set({ isLoading: false });
        get().addToast(`Record added to ${module} successfully`, 'success');
        // Refresh local view
        get().fetchModuleRecords(module, get().currentPage);
        return newItem;
      } catch (err: any) {
        set({ isLoading: false });
        get().addToast(err.message, 'error');
        throw err;
      }
    },

    updateRecord: async (module, id, recordData) => {
      set({ isLoading: true });
      try {
        const updatedItem = await apiFetch(`/api/crm/${module}/${id}`, {
          method: 'PUT',
          body: JSON.stringify(recordData)
        });
        set({ isLoading: false });
        get().addToast(`Record in ${module} updated successfully`, 'success');
        get().fetchModuleRecords(module, get().currentPage);
        return updatedItem;
      } catch (err: any) {
        set({ isLoading: false });
        get().addToast(err.message, 'error');
        throw err;
      }
    },

    deleteRecord: async (module, id) => {
      set({ isLoading: true });
      try {
        await apiFetch(`/api/crm/${module}/${id}`, { method: 'DELETE' });
        set({ isLoading: false });
        get().addToast('Record deleted successfully', 'success');
        get().fetchModuleRecords(module, get().currentPage);
        return true;
      } catch (err: any) {
        set({ isLoading: false });
        get().addToast(err.message, 'error');
        return false;
      }
    },

    bulkDeleteRecords: async (module, ids) => {
      set({ isLoading: true });
      try {
        await apiFetch(`/api/crm/${module}/bulk-delete`, {
          method: 'POST',
          body: JSON.stringify({ ids })
        });
        set({ isLoading: false });
        get().addToast(`Bulk deletion of ${ids.length} records successful`, 'success');
        get().fetchModuleRecords(module, 1);
        return true;
      } catch (err: any) {
        set({ isLoading: false });
        get().addToast(err.message, 'error');
        return false;
      }
    },

    importBackup: async (module, data) => {
      set({ isLoading: true });
      try {
        const res = await apiFetch('/api/crm/import', {
          method: 'POST',
          body: JSON.stringify({ module, data })
        });
        set({ isLoading: false });
        get().addToast(res.message, 'success');
        get().fetchModuleRecords(module, 1);
        return true;
      } catch (err: any) {
        set({ isLoading: false });
        get().addToast(err.message, 'error');
        return false;
      }
    },

    exportBackup: async (module, format = 'json') => {
      const { token, user } = get();
      try {
        let url = `/api/crm/export/${module}?format=${format}`;
        
        // Trigger a native browser download trigger
        const response = await fetch(url, {
          headers: {
            'Authorization': token ? `Bearer ${token}` : '',
            'X-User-Id': user?.id || ''
          }
        });
        
        if (!response.ok) throw new Error('Backup export compile failed');
        
        const blob = await response.blob();
        const blobUrl = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = `CRM_Backup_${module}.${format === 'csv' ? 'csv' : 'json'}`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(blobUrl);
        get().addToast(`Exported ${module} payload successfully.`, 'success');
      } catch (err: any) {
        get().addToast(err.message, 'error');
      }
    },

    fetchReports: async () => {
      try {
        const reports = await apiFetch('/api/crm/reports');
        set({ reports });
      } catch (err: any) {
        console.error('Reports load failed', err);
      }
    },

    fetchRolesPermissions: async () => {
      try {
        const rolesPermissions = await apiFetch('/api/crm/roles-permissions');
        set({ rolesPermissions });
      } catch (err: any) {
        console.error('Roles fetch failed', err);
      }
    },

    fetchSettings: async () => {
      try {
        const settings = await apiFetch('/api/crm/settings');
        set({ settings });
      } catch (err: any) {
        console.error('Settings load failed', err);
      }
    },

    updateSettings: async (data) => {
      set({ isLoading: true });
      try {
        const settings = await apiFetch('/api/crm/settings', {
          method: 'PUT',
          body: JSON.stringify(data)
        });
        set({ settings, isLoading: false });
        get().addToast('Global CRM configurations updated successfully', 'success');
      } catch (err: any) {
        set({ isLoading: false });
        get().addToast(err.message, 'error');
      }
    },

    // Custom Leave approval
    approveLeave: async (id, status) => {
      set({ isLoading: true });
      try {
        await apiFetch(`/api/crm/leaves/${id}/approve`, {
          method: 'PUT',
          body: JSON.stringify({ status })
        });
        set({ isLoading: false });
        get().addToast(`Leave application successfully ${status}`, 'success');
        get().fetchModuleRecords('leaves', get().currentPage);
        return true;
      } catch (err: any) {
        set({ isLoading: false });
        get().addToast(err.message, 'error');
        return false;
      }
    },

    // Custom Clock in/out
    clockIn: async () => {
      set({ isLoading: true });
      try {
        const clockData = await apiFetch('/api/crm/attendance/checkin', { method: 'POST' });
        set({ isLoading: false });
        get().addToast(`Duty Clocked In successfully at ${clockData.checkIn}!`, 'success');
        get().fetchModuleRecords('attendance', 1);
        return true;
      } catch (err: any) {
        set({ isLoading: false });
        get().addToast(err.message, 'error');
        return false;
      }
    },

    clockOut: async () => {
      set({ isLoading: true });
      try {
        await apiFetch('/api/crm/attendance/checkout', { method: 'POST' });
        set({ isLoading: false });
        get().addToast('Clocked Out successfully from duty.', 'success');
        get().fetchModuleRecords('attendance', 1);
        return true;
      } catch (err: any) {
        set({ isLoading: false });
        get().addToast(err.message, 'error');
        return false;
      }
    },

    markNotificationRead: async (id) => {
      try {
        await apiFetch(`/api/crm/notifications/${id}/read`, { method: 'PUT' });
      } catch (err) {
        console.error('Failed to mark read', err);
      }
    },

    // AI smart co-piloting
    analyzeLeadWithAI: async (leadId) => {
      try {
        const res = await apiFetch('/api/crm/ai/analyze-lead', {
          method: 'POST',
          body: JSON.stringify({ leadId })
        });
        set((state) => ({
          aiLeadAnalysis: {
            ...state.aiLeadAnalysis,
            [leadId]: res.analysis
          }
        }));
        get().addToast('AI Lead Scoring & Analysis playbook generated!', 'success');
      } catch (err: any) {
        get().addToast(`AI Analysis failed: ${err.message}`, 'error');
      }
    },

    draftTicketReplyWithAI: async (ticketId) => {
      try {
        const res = await apiFetch('/api/crm/ai/reply-ticket', {
          method: 'POST',
          body: JSON.stringify({ ticketId })
        });
        set((state) => ({
          aiTicketReply: {
            ...state.aiTicketReply,
            [ticketId]: res.reply
          }
        }));
        get().addToast('Smart Auto-Responder Email draft created!', 'success');
      } catch (err: any) {
        get().addToast(`AI Responder failed: ${err.message}`, 'error');
      }
    }
  };
});
