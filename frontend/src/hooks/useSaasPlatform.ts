import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import {
  IndustryTemplateItem,
  TenantAppliedTemplateItem,
  FeatureEntitlementCatalog,
  FeatureEntitlementVerification,
  PlatformConsoleOverview,
  PlatformTenantItem,
  PlatformPlanItem,
  PlatformAnnouncementItem,
  PlatformBackupRecordItem,
  PlatformRevenueReport,
  SaasOnboardingRegisterPayload,
  SaasOnboardingStepPayload,
} from '../api/types';

// ==========================================
// Industry Templates Hooks
// ==========================================

export function useIndustryTemplates() {
  return useQuery({
    queryKey: ['saas-industry-templates'],
    queryFn: () => api.getIndustryTemplates(),
  });
}

export function useCurrentIndustryTemplate() {
  return useQuery({
    queryKey: ['saas-current-industry-template'],
    queryFn: () => api.getCurrentIndustryTemplate(),
  });
}

export function useApplyIndustryTemplate() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ templateCode, replaceExisting }: { templateCode: string; replaceExisting?: boolean }) =>
      api.applyIndustryTemplate(templateCode, replaceExisting),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['saas-current-industry-template'] });
      queryClient.invalidateQueries({ queryKey: ['pipelines'] });
      queryClient.invalidateQueries({ queryKey: ['custom-fields'] });
      queryClient.invalidateQueries({ queryKey: ['owner-dashboard-metrics'] });
    },
  });
}

// ==========================================
// SaaS Onboarding Hooks
// ==========================================

export function useSaasOnboardingStatus() {
  return useQuery({
    queryKey: ['saas-onboarding-status'],
    queryFn: () => api.getSaasOnboardingStatus(),
  });
}

export function useSaveSaasOnboardingStep() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: SaasOnboardingStepPayload) => api.saveSaasOnboardingStep(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['saas-onboarding-status'] });
    },
  });
}

export function useRegisterSaasOrganization() {
  return useMutation({
    mutationFn: (payload: SaasOnboardingRegisterPayload) => api.registerSaasOrganization(payload),
  });
}

// ==========================================
// Feature Entitlements Engine Hooks
// ==========================================

export function useFeatureEntitlements() {
  return useQuery({
    queryKey: ['feature-entitlements'],
    queryFn: () => api.getFeatureEntitlements(),
  });
}

export function useVerifyFeatureEntitlement() {
  return useMutation({
    mutationFn: (featureKey: string) => api.verifyFeatureEntitlement(featureKey),
  });
}

// ==========================================
// Platform Super Admin Console Hooks
// ==========================================

export function usePlatformConsoleOverview() {
  return useQuery({
    queryKey: ['platform-console-overview'],
    queryFn: () => api.getPlatformConsoleOverview(),
    refetchInterval: 30000,
  });
}

export function usePlatformTenants(params: { status?: string; search?: string } = {}) {
  return useQuery({
    queryKey: ['platform-console-tenants', params],
    queryFn: () => api.getPlatformTenants(params),
  });
}

export function useSuspendTenant() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => api.suspendTenant(id, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platform-console-tenants'] });
      queryClient.invalidateQueries({ queryKey: ['platform-console-overview'] });
    },
  });
}

export function useReactivateTenant() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.reactivateTenant(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platform-console-tenants'] });
      queryClient.invalidateQueries({ queryKey: ['platform-console-overview'] });
    },
  });
}

export function useChangeTenantPlan() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, planCode, billingCycle }: { id: number; planCode: string; billingCycle?: string }) =>
      api.changeTenantPlan(id, planCode, billingCycle),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platform-console-tenants'] });
      queryClient.invalidateQueries({ queryKey: ['platform-console-overview'] });
      queryClient.invalidateQueries({ queryKey: ['platform-console-revenue'] });
    },
  });
}

export function usePlatformPlans() {
  return useQuery({
    queryKey: ['platform-console-plans'],
    queryFn: () => api.getPlatformPlans(),
  });
}

export function useSavePlatformPlan() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: Partial<PlatformPlanItem>) => api.savePlatformPlan(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platform-console-plans'] });
    },
  });
}

export function usePlatformRevenueReport(year?: number) {
  return useQuery({
    queryKey: ['platform-console-revenue', year],
    queryFn: () => api.getPlatformRevenueReport(year),
  });
}

export function usePlatformAnnouncements() {
  return useQuery({
    queryKey: ['platform-console-announcements'],
    queryFn: () => api.getPlatformAnnouncements(),
  });
}

export function useCreatePlatformAnnouncement() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: Partial<PlatformAnnouncementItem>) => api.createPlatformAnnouncement(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platform-console-announcements'] });
    },
  });
}

export function usePlatformBackups() {
  return useQuery({
    queryKey: ['platform-console-backups'],
    queryFn: () => api.getPlatformBackups(),
  });
}

export function useTriggerPlatformBackup() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: { backup_type?: string; notes?: string } = {}) => api.triggerPlatformBackup(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platform-console-backups'] });
    },
  });
}

export function usePlatformAuditLogs() {
  return useQuery({
    queryKey: ['platform-console-audit-logs'],
    queryFn: () => api.getPlatformAuditLogs(),
  });
}
