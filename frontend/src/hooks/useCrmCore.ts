import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useCRMStore } from '../store/crmStore';
import { LeadConversionPayload } from '../api/types';

// ==========================================
// 1. CUSTOMER 360 HOOKS
// ==========================================

export function useCustomer360(customerId: number | string | null | undefined) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['customer-360', workspaceId, customerId],
    queryFn: async () => {
      if (!customerId) return null;
      const res = await api.getCustomer360(customerId);
      return res.data;
    },
    enabled: !!customerId,
  });
}

// ==========================================
// 2. CHRONOLOGICAL ACTIVITIES HOOKS
// ==========================================

export function useEntityActivities(params: {
  lead_id?: number | string;
  contact_id?: number | string;
  company_id?: number | string;
  deal_id?: number | string;
  customer_id?: number | string;
  activity_type?: string;
}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['crm-activities', workspaceId, params],
    queryFn: async () => {
      const res = await api.getActivities(params);
      return res.data || [];
    },
    enabled: !!(params.lead_id || params.contact_id || params.company_id || params.deal_id || params.customer_id),
  });
}

export function useLogCrmActivity() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: {
      activity_type: string;
      title: string;
      description?: string;
      metadata?: Record<string, any>;
      lead_id?: number | string;
      contact_id?: number | string;
      company_id?: number | string;
      deal_id?: number | string;
      customer_id?: number | string;
      scheduled_at?: string;
      completed_at?: string;
    }) => api.logActivity(data),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['crm-activities'] });
      if (vars.customer_id) {
        queryClient.invalidateQueries({ queryKey: ['customer-360', vars.customer_id] });
      }
      addToast('Activity logged successfully to timeline.', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to log activity', 'error');
    },
  });
}

// ==========================================
// 3. LEADS LIFECYCLE & CONVERSION HOOKS
// ==========================================

export function useConvertLead() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ leadId, payload }: { leadId: number | string; payload: LeadConversionPayload }) =>
      api.convertLead(leadId, payload),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['crm', 'leads'] });
      queryClient.invalidateQueries({ queryKey: ['crm', 'deals'] });
      queryClient.invalidateQueries({ queryKey: ['crm', 'contacts'] });
      queryClient.invalidateQueries({ queryKey: ['crm', 'companies'] });
      queryClient.invalidateQueries({ queryKey: ['crm', 'customers'] });
      addToast(`Lead successfully converted into Contact, Company & Deal!`, 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Lead conversion failed.', 'error');
    },
  });
}

export function useAssignLead() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ leadId, userId, reason }: { leadId: number | string; userId: number | string; reason?: string }) =>
      api.assignLead(leadId, userId, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['crm', 'leads'] });
      addToast('Lead successfully assigned.', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to assign lead.', 'error');
    },
  });
}

export function useAutoDistributeRoundRobin() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: () => api.autoDistributeLeadsRoundRobin(),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['crm', 'leads'] });
      addToast(`Round-robin distributed ${res.data?.distributed_count || 0} leads across sales reps.`, 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Round-robin distribution failed.', 'error');
    },
  });
}

export function useMergeLead() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ masterId, duplicateId, overrides }: { masterId: number | string; duplicateId: number | string; overrides?: Record<string, any> }) =>
      api.mergeLead(masterId, duplicateId, overrides),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['crm', 'leads'] });
      addToast('Duplicate lead merged and consolidated successfully.', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Lead merge failed.', 'error');
    },
  });
}

export function useArchiveLead() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (leadId: number | string) => api.archiveLead(leadId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['crm', 'leads'] });
      addToast('Lead moved to archive.', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to archive lead.', 'error');
    },
  });
}

export function useRecalculateLeadScore() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (leadId: number | string) => api.recalculateLeadScore(leadId),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['crm', 'leads'] });
      addToast(`Lead score recalculated: ${res.data?.score || 0} pts (${res.data?.qualification_status}).`, 'success');
    },
  });
}
