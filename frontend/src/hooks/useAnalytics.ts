import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useCRMStore } from '../store/crmStore';
import {
  ExecutiveAnalyticsData,
  LeadAnalyticsData,
  SalesAnalyticsData,
  AttributionComparisonData,
  SavedReportItem,
  ReportExportResult,
} from '../api/types';

export function useExecutiveAnalytics(params?: {
  from_date?: string;
  to_date?: string;
  employee_id?: number;
}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['executiveAnalytics', workspaceId, params],
    queryFn: async () => {
      const res = await api.getExecutiveAnalytics(params);
      return res.data as ExecutiveAnalyticsData;
    },
  });
}

export function useLeadAnalytics(params?: {
  from_date?: string;
  to_date?: string;
  employee_id?: number;
}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['leadAnalytics', workspaceId, params],
    queryFn: async () => {
      const res = await api.getLeadAnalytics(params);
      return res.data as LeadAnalyticsData;
    },
  });
}

export function useSalesAnalytics(params?: {
  from_date?: string;
  to_date?: string;
  employee_id?: number;
}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['salesAnalytics', workspaceId, params],
    queryFn: async () => {
      const res = await api.getSalesAnalytics(params);
      return res.data as SalesAnalyticsData;
    },
  });
}

export function useAttributionAnalytics(params?: {
  from_date?: string;
  to_date?: string;
}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['attributionAnalytics', workspaceId, params],
    queryFn: async () => {
      const res = await api.getAttributionAnalytics(params);
      return res.data as AttributionComparisonData;
    },
  });
}

export function useSavedReports() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['savedReports', workspaceId],
    queryFn: async () => {
      const res = await api.getSavedReports();
      return (res.data || []) as SavedReportItem[];
    },
  });
}

export function useCreateSavedReport() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async (data: Partial<SavedReportItem>) => {
      const res = await api.createSavedReport(data);
      return res.data as SavedReportItem;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['savedReports', workspaceId] });
    },
  });
}

export function useDeleteSavedReport() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async (id: number) => {
      await api.deleteSavedReport(id);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['savedReports', workspaceId] });
    },
  });
}

export function useRecordTouchpoint() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async (data: Record<string, any>) => {
      const res = await api.recordTouchpoint(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['attributionAnalytics', workspaceId] });
      queryClient.invalidateQueries({ queryKey: ['executiveAnalytics', workspaceId] });
    },
  });
}

// ==========================================
// Tracking SDK, GA4 & Multi-Touch Hooks
// ==========================================

export function useTrackingSettings() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['trackingSettings', workspaceId],
    queryFn: async () => {
      const res = await api.getTrackingSettings();
      return res.data;
    },
  });
}

export function useUpdateTrackingSettings() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async (data: any) => {
      const res = await api.updateTrackingSettings(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['trackingSettings', workspaceId] });
      queryClient.invalidateQueries({ queryKey: ['trackingDiagnostics', workspaceId] });
    },
  });
}

export function useTrackingEvents(params?: any) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['trackingEvents', workspaceId, params],
    queryFn: async () => {
      const res = await api.getTrackingEvents(params);
      return res.data;
    },
  });
}

export function useTrackingAttribution(params?: any) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['trackingAttribution', workspaceId, params],
    queryFn: async () => {
      const res = await api.getTrackingAttribution(params);
      return res.data;
    },
  });
}

export function useTrackingDiagnostics() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['trackingDiagnostics', workspaceId],
    queryFn: async () => {
      const res = await api.getTrackingDiagnostics();
      return res.data;
    },
    refetchInterval: 30000,
  });
}

export function useConnectGA4() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async (data: any) => {
      const res = await api.connectGA4(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['trackingSettings', workspaceId] });
      queryClient.invalidateQueries({ queryKey: ['ga4Properties', workspaceId] });
      queryClient.invalidateQueries({ queryKey: ['ga4Report', workspaceId] });
      queryClient.invalidateQueries({ queryKey: ['trackingDiagnostics', workspaceId] });
    },
  });
}

export function useDisconnectGA4() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async () => {
      const res = await api.disconnectGA4();
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['trackingSettings', workspaceId] });
      queryClient.invalidateQueries({ queryKey: ['ga4Properties', workspaceId] });
      queryClient.invalidateQueries({ queryKey: ['ga4Report', workspaceId] });
      queryClient.invalidateQueries({ queryKey: ['trackingDiagnostics', workspaceId] });
    },
  });
}

export function useGA4Properties() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['ga4Properties', workspaceId],
    queryFn: async () => {
      const res = await api.getGA4Properties();
      return res.data;
    },
  });
}

export function useGA4Report(params?: { start_date?: string; end_date?: string }) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['ga4Report', workspaceId, params],
    queryFn: async () => {
      const res = await api.getGA4Report(params);
      return res.data;
    },
  });
}
