import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import {
  DashboardWidgetLayoutItem,
  CustomAnalyticsAlertItem,
} from '../api/types';

export const useOwnerDashboardMetrics = (params?: {
  from_date?: string;
  to_date?: string;
  branch_id?: number;
  employee_id?: number;
  department_id?: number;
}) => {
  return useQuery({
    queryKey: ['owner-dashboard-metrics', params],
    queryFn: async () => {
      const res = await api.getOwnerDashboardMetrics(params);
      return res.data;
    },
    staleTime: 60 * 1000,
  });
};

export const useDashboardLayouts = () => {
  return useQuery({
    queryKey: ['dashboard-layouts'],
    queryFn: async () => {
      const res = await api.getDashboardLayouts();
      return res.data;
    },
  });
};

export const useSaveDashboardLayout = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: {
      name: string;
      role?: string;
      is_default?: boolean;
      widgets: any[];
      filters?: Record<string, any>;
    }) => api.saveDashboardLayout(data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['dashboard-layouts'] });
    },
  });
};

export const useCustomReportCatalog = () => {
  return useQuery({
    queryKey: ['custom-report-catalog'],
    queryFn: async () => {
      const res = await api.getCustomReportCatalog();
      return res.data;
    },
    staleTime: 10 * 60 * 1000,
  });
};

export const usePreviewCustomReport = () => {
  return useMutation({
    mutationFn: (config: any) => api.previewCustomReport(config),
  });
};

export const useSaveCustomReport = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: any) => api.saveCustomReport(data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['saved-reports'] });
    },
  });
};

export const useSalesDeepDive = (params?: { from_date?: string; to_date?: string }) => {
  return useQuery({
    queryKey: ['sales-deep-dive', params],
    queryFn: async () => {
      const res = await api.getSalesDeepDive(params);
      return res.data;
    },
  });
};

export const useMarketingDeepDive = (params?: { from_date?: string; to_date?: string }) => {
  return useQuery({
    queryKey: ['marketing-deep-dive', params],
    queryFn: async () => {
      const res = await api.getMarketingDeepDive(params);
      return res.data;
    },
  });
};

export const useFinanceDeepDive = (params?: { from_date?: string; to_date?: string }) => {
  return useQuery({
    queryKey: ['finance-deep-dive', params],
    queryFn: async () => {
      const res = await api.getFinanceDeepDive(params);
      return res.data;
    },
  });
};

export const useAiBusinessInsights = (params?: { from_date?: string; to_date?: string }) => {
  return useQuery({
    queryKey: ['ai-business-insights', params],
    queryFn: async () => {
      const res = await api.getAiBusinessInsights(params);
      return res.data;
    },
  });
};

export const useCustomAnalyticsAlerts = () => {
  return useQuery({
    queryKey: ['custom-analytics-alerts'],
    queryFn: async () => {
      const res = await api.getCustomAnalyticsAlerts();
      return res.data;
    },
  });
};

export const useCreateCustomAnalyticsAlert = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: any) => api.createCustomAnalyticsAlert(data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['custom-analytics-alerts'] });
    },
  });
};

export const useEvaluateCustomAnalyticsAlerts = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => api.evaluateCustomAnalyticsAlerts(),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['custom-analytics-alerts'] });
    },
  });
};

export const useFieldSalesVisits = (params?: {
  employee_id?: number;
  branch_id?: number;
  status?: string;
  date?: string;
  page?: number;
}) => {
  return useQuery({
    queryKey: ['field-sales-visits', params],
    queryFn: async () => {
      const res = await api.getFieldSalesVisits(params);
      return res.data;
    },
  });
};

export const useScheduleFieldSalesVisit = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: any) => api.scheduleFieldSalesVisit(data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['field-sales-visits'] });
      qc.invalidateQueries({ queryKey: ['field-sales-daily-plan'] });
    },
  });
};

export const useCheckInFieldSalesVisit = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: any }) => api.checkInFieldSalesVisit(id, data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['field-sales-visits'] });
      qc.invalidateQueries({ queryKey: ['field-sales-daily-plan'] });
    },
  });
};

export const useCheckOutFieldSalesVisit = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: any }) => api.checkOutFieldSalesVisit(id, data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['field-sales-visits'] });
      qc.invalidateQueries({ queryKey: ['field-sales-daily-plan'] });
      qc.invalidateQueries({ queryKey: ['field-sales-analytics'] });
    },
  });
};

export const useFieldSalesDailyPlan = (employeeId?: number, date?: string) => {
  return useQuery({
    queryKey: ['field-sales-daily-plan', employeeId, date],
    queryFn: async () => {
      const res = await api.getFieldSalesDailyPlan(employeeId, date);
      return res.data;
    },
  });
};

export const useFieldSalesAnalytics = (params?: any) => {
  return useQuery({
    queryKey: ['field-sales-analytics', params],
    queryFn: async () => {
      const res = await api.getFieldSalesAnalytics(params);
      return res.data;
    },
  });
};

export const useIndiaSettings = () => {
  return useQuery({
    queryKey: ['india-settings'],
    queryFn: async () => {
      const res = await api.getIndiaSettings();
      return res.data;
    },
    staleTime: 60 * 60 * 1000,
  });
};
