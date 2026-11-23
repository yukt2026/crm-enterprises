import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useCRMStore } from '../store/crmStore';
import {
  SubscriptionPlanItem,
  TenantSubscriptionItem,
  UsageDashboardData,
  UsageRecordItem,
  CreditWalletData,
  CreditLedgerEntryItem,
  CostProtectionRuleItem,
  SubscriptionInvoiceItem,
} from '../api/types';

export function useBillingPlans() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['billingPlans', workspaceId],
    queryFn: async () => {
      const res = await api.getBillingPlans();
      return (res.data || []) as SubscriptionPlanItem[];
    },
  });
}

export function useBillingSubscription() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['billingSubscription', workspaceId],
    queryFn: async () => {
      const res = await api.getBillingSubscription();
      return (res.data || null) as TenantSubscriptionItem | null;
    },
  });
}

export function useSubscribePlan() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async (data: {
      plan_id: number;
      billing_cycle?: 'monthly' | 'annual';
      prorate?: boolean;
      billing_contact_name?: string;
      billing_contact_email?: string;
      billing_tax_id?: string;
      billing_address?: string;
    }) => {
      const res = await api.subscribeBillingPlan(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['billingSubscription', workspaceId] });
      queryClient.invalidateQueries({ queryKey: ['billingInvoices', workspaceId] });
      queryClient.invalidateQueries({ queryKey: ['usageDashboard', workspaceId] });
      queryClient.invalidateQueries({ queryKey: ['creditWallet', workspaceId] });
      queryClient.invalidateQueries({ queryKey: ['creditLedger', workspaceId] });
    },
  });
}

export function useCancelSubscription() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async (reason?: string) => {
      const res = await api.cancelBillingSubscription(reason);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['billingSubscription', workspaceId] });
    },
  });
}

export function useUpdateBillingContact() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async (data: {
      billing_contact_name?: string;
      billing_contact_email?: string;
      billing_tax_id?: string;
      billing_address?: string;
    }) => {
      const res = await api.updateBillingContact(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['billingSubscription', workspaceId] });
    },
  });
}

export function useBillingInvoices() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['billingInvoices', workspaceId],
    queryFn: async () => {
      const res = await api.getBillingInvoices();
      return (res.data || []) as SubscriptionInvoiceItem[];
    },
  });
}

export function useUsageDashboard() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['usageDashboard', workspaceId],
    queryFn: async () => {
      const res = await api.getUsageDashboard();
      return res.data as UsageDashboardData;
    },
  });
}

export function useUsageRecords(params?: {
  meter_type?: string;
  start_date?: string;
  end_date?: string;
  per_page?: number;
}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['usageRecords', workspaceId, params],
    queryFn: async () => {
      const res = await api.getUsageRecords(params);
      return (res.data || []) as UsageRecordItem[];
    },
  });
}

export function useCreditWallet() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['creditWallet', workspaceId],
    queryFn: async () => {
      const res = await api.getCreditWallet();
      return res.data as CreditWalletData;
    },
  });
}

export function useRechargeCreditWallet() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async (data: {
      amount: number;
      reference_id?: string;
      description?: string;
      metadata?: Record<string, any>;
    }) => {
      const res = await api.rechargeCreditWallet(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['creditWallet', workspaceId] });
      queryClient.invalidateQueries({ queryKey: ['creditLedger', workspaceId] });
      queryClient.invalidateQueries({ queryKey: ['usageDashboard', workspaceId] });
    },
  });
}

export function useUpdateAutoRecharge() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async (data: {
      auto_recharge_enabled: boolean;
      auto_recharge_threshold?: number;
      auto_recharge_amount?: number;
    }) => {
      const res = await api.updateWalletAutoRecharge(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['creditWallet', workspaceId] });
    },
  });
}

export function useCreditLedger(perPage: number = 50) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['creditLedger', workspaceId, perPage],
    queryFn: async () => {
      const res = await api.getCreditLedger(perPage);
      return (res.data || []) as CreditLedgerEntryItem[];
    },
  });
}

export function useVerifyCreditLedgerIntegrity() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async () => {
      const res = await api.verifyCreditLedgerIntegrity();
      return res.data;
    },
  });
}

export function useCostProtection() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['costProtection', workspaceId],
    queryFn: async () => {
      const res = await api.getCostProtection();
      return res.data as CostProtectionRuleItem;
    },
  });
}

export function useUpdateCostProtection() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async (data: Partial<CostProtectionRuleItem>) => {
      const res = await api.updateCostProtection(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['costProtection', workspaceId] });
      queryClient.invalidateQueries({ queryKey: ['usageDashboard', workspaceId] });
    },
  });
}
