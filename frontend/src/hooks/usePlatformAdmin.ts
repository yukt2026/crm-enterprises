import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import {
  PlatformOverviewData,
  PlatformTenantItem,
  PlatformTenantDetailData,
  PlatformProviderItem,
  PlatformFeatureFlagItem,
  PlatformMaintenanceModeData,
  PlatformAuditLogItem,
  PlatformQueueStatsData,
  SubscriptionPlanItem,
  TenantSubscriptionItem,
  SubscriptionInvoiceItem,
} from '../api/types';

export function usePlatformOverview() {
  return useQuery({
    queryKey: ['platformOverview'],
    queryFn: async () => {
      const res = await api.getPlatformOverview();
      return res.data as PlatformOverviewData;
    },
    refetchInterval: 30000,
  });
}

export function usePlatformTenants(params?: {
  search?: string;
  status?: string;
  tier?: string;
  page?: number;
  per_page?: number;
}) {
  return useQuery({
    queryKey: ['platformTenants', params],
    queryFn: async () => {
      const res = await api.getPlatformTenants(params);
      return {
        items: (res.data || []) as PlatformTenantItem[],
        meta: res.meta,
      };
    },
  });
}

export function usePlatformTenantDetails(id: number | null) {
  return useQuery({
    queryKey: ['platformTenantDetails', id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getPlatformTenantDetails(id);
      return res.data as PlatformTenantDetailData;
    },
    enabled: !!id,
  });
}

export function useUpdatePlatformTenantStatus() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, status, reason }: { id: number; status: string; reason: string }) => {
      const res = await api.updatePlatformTenantStatus(id, status, reason);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platformTenants'] });
      queryClient.invalidateQueries({ queryKey: ['platformTenantDetails'] });
      queryClient.invalidateQueries({ queryKey: ['platformOverview'] });
      queryClient.invalidateQueries({ queryKey: ['platformAuditLogs'] });
    },
  });
}

export function useChangePlatformTenantPlan() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, planId, prorate }: { id: number; planId: number; prorate?: boolean }) => {
      const res = await api.changePlatformTenantPlan(id, planId, prorate);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platformTenants'] });
      queryClient.invalidateQueries({ queryKey: ['platformTenantDetails'] });
      queryClient.invalidateQueries({ queryKey: ['platformOverview'] });
    },
  });
}

export function useUpdatePlatformTenantLimits() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, limits }: { id: number; limits: Record<string, any> }) => {
      const res = await api.updatePlatformTenantLimits(id, limits);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platformTenantDetails'] });
    },
  });
}

export function useCreateBreakglassSession() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({
      tenantId,
      justificationReason,
      durationMinutes,
    }: {
      tenantId: number;
      justificationReason: string;
      durationMinutes?: number;
    }) => {
      const res = await api.createBreakglassSupportSession(tenantId, justificationReason, durationMinutes);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platformTenantDetails'] });
      queryClient.invalidateQueries({ queryKey: ['platformAuditLogs'] });
    },
  });
}

export function useRevokeBreakglassSession() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (sessionId: number) => {
      await api.revokeBreakglassSupportSession(sessionId);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platformTenantDetails'] });
      queryClient.invalidateQueries({ queryKey: ['platformAuditLogs'] });
    },
  });
}

export function usePlatformBillingPlans() {
  return useQuery({
    queryKey: ['platformBillingPlans'],
    queryFn: async () => {
      const res = await api.getPlatformBillingPlans();
      return (res.data || []) as SubscriptionPlanItem[];
    },
  });
}

export function useCreatePlatformPlan() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: Partial<SubscriptionPlanItem>) => {
      const res = await api.createPlatformBillingPlan(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platformBillingPlans'] });
      queryClient.invalidateQueries({ queryKey: ['platformAuditLogs'] });
    },
  });
}

export function useUpdatePlatformPlan() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Partial<SubscriptionPlanItem> }) => {
      const res = await api.updatePlatformBillingPlan(id, data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platformBillingPlans'] });
      queryClient.invalidateQueries({ queryKey: ['platformAuditLogs'] });
    },
  });
}

export function usePlatformSubscriptions(status?: string) {
  return useQuery({
    queryKey: ['platformSubscriptions', status],
    queryFn: async () => {
      const res = await api.getPlatformSubscriptions(status);
      return {
        items: (res.data || []) as TenantSubscriptionItem[],
        meta: res.meta,
      };
    },
  });
}

export function usePlatformInvoices(status?: string) {
  return useQuery({
    queryKey: ['platformInvoices', status],
    queryFn: async () => {
      const res = await api.getPlatformInvoices(status);
      return {
        items: (res.data || []) as SubscriptionInvoiceItem[],
        meta: res.meta,
      };
    },
  });
}

export function useAdjustTenantCredits() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({
      tenantId,
      amount,
      reason,
    }: {
      tenantId: number;
      amount: number;
      reason: string;
    }) => {
      const res = await api.adjustTenantCredits(tenantId, amount, reason);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platformTenantDetails'] });
      queryClient.invalidateQueries({ queryKey: ['platformOverview'] });
      queryClient.invalidateQueries({ queryKey: ['platformAuditLogs'] });
    },
  });
}

export function usePlatformProviders() {
  return useQuery({
    queryKey: ['platformProviders'],
    queryFn: async () => {
      const res = await api.getPlatformProviders();
      return (res.data || []) as PlatformProviderItem[];
    },
  });
}

export function useUpdatePlatformProvider() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({
      key,
      data,
    }: {
      key: string;
      data: {
        name?: string;
        is_active?: boolean;
        credentials?: string | Record<string, any>;
        available_models?: string[];
        default_model?: string;
        routing_policy?: Record<string, any>;
        rate_limit_rpm?: number;
        rate_limit_tpm?: number;
      };
    }) => {
      const res = await api.updatePlatformProvider(key, data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platformProviders'] });
      queryClient.invalidateQueries({ queryKey: ['platformAuditLogs'] });
    },
  });
}

export function useTestPlatformProviderHealth() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (key: string) => {
      const res = await api.testPlatformProviderHealth(key);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platformProviders'] });
    },
  });
}

export function usePlatformQueueStats() {
  return useQuery({
    queryKey: ['platformQueueStats'],
    queryFn: async () => {
      const res = await api.getPlatformQueueStats();
      return res.data as PlatformQueueStatsData;
    },
    refetchInterval: 15000,
  });
}

export function useRetryPlatformQueueJob() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: string | number) => {
      await api.retryPlatformQueueJob(id);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platformQueueStats'] });
      queryClient.invalidateQueries({ queryKey: ['platformAuditLogs'] });
    },
  });
}

export function usePurgePlatformQueueJobs() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async () => {
      const res = await api.purgePlatformQueueJobs();
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platformQueueStats'] });
      queryClient.invalidateQueries({ queryKey: ['platformAuditLogs'] });
    },
  });
}

export function usePlatformFeatureFlags() {
  return useQuery({
    queryKey: ['platformFeatureFlags'],
    queryFn: async () => {
      const res = await api.getPlatformFeatureFlags();
      return (res.data || []) as PlatformFeatureFlagItem[];
    },
  });
}

export function useUpdatePlatformFeatureFlag() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({
      key,
      data,
    }: {
      key: string;
      data: { is_enabled?: boolean; name?: string; description?: string; rules?: Record<string, any> };
    }) => {
      const res = await api.updatePlatformFeatureFlag(key, data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platformFeatureFlags'] });
      queryClient.invalidateQueries({ queryKey: ['platformAuditLogs'] });
    },
  });
}

export function usePlatformMaintenanceMode() {
  return useQuery({
    queryKey: ['platformMaintenanceMode'],
    queryFn: async () => {
      const res = await api.getPlatformMaintenanceMode();
      return res.data as PlatformMaintenanceModeData;
    },
  });
}

export function useSetPlatformMaintenanceMode() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: { is_enabled: boolean; message?: string; bypass_secret?: string }) => {
      const res = await api.setPlatformMaintenanceMode(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platformMaintenanceMode'] });
      queryClient.invalidateQueries({ queryKey: ['platformAuditLogs'] });
    },
  });
}

export function usePlatformAuditLogs(params?: {
  action?: string;
  admin_id?: number;
  target_type?: string;
  page?: number;
  per_page?: number;
}) {
  return useQuery({
    queryKey: ['platformAuditLogs', params],
    queryFn: async () => {
      const res = await api.getPlatformAuditLogs(params);
      return {
        items: (res.data || []) as PlatformAuditLogItem[],
        meta: res.meta,
      };
    },
  });
}
