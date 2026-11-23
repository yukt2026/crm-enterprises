import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import {
  LeadRoutingRuleItem,
  LeadSlaPolicyItem,
  LeadPortalIntegrationItem,
  LeadFollowupItem,
  LeadScoringConfigurationItem,
} from '../api/types';

export function useLeadPortals() {
  return useQuery({
    queryKey: ['lead-portals'],
    queryFn: async () => {
      const res = await api.getLeadPortals();
      return res.data ?? [];
    },
  });
}

export function useSaveLeadPortal() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: Partial<LeadPortalIntegrationItem>) => api.saveLeadPortal(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['lead-portals'] });
    },
  });
}

export function useLeadRoutingRules() {
  return useQuery({
    queryKey: ['lead-routing-rules'],
    queryFn: async () => {
      const res = await api.getLeadRoutingRules();
      return res.data ?? [];
    },
  });
}

export function useSaveLeadRoutingRule() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: Partial<LeadRoutingRuleItem>) => api.saveLeadRoutingRule(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['lead-routing-rules'] });
    },
  });
}

export function useDeleteLeadRoutingRule() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.deleteLeadRoutingRule(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['lead-routing-rules'] });
    },
  });
}

export function useLeadSlaPolicies() {
  return useQuery({
    queryKey: ['lead-sla-policies'],
    queryFn: async () => {
      const res = await api.getLeadSlaPolicies();
      return res.data ?? [];
    },
  });
}

export function useSaveLeadSlaPolicy() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: Partial<LeadSlaPolicyItem>) => api.saveLeadSlaPolicy(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['lead-sla-policies'] });
    },
  });
}

export function useEvaluateLeadSla() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => api.evaluateLeadSlaEscalations(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['leads'] });
      queryClient.invalidateQueries({ queryKey: ['lead-escalation-logs'] });
      queryClient.invalidateQueries({ queryKey: ['lead-reports-dashboard'] });
    },
  });
}

export function useLeadEscalationLogs() {
  return useQuery({
    queryKey: ['lead-escalation-logs'],
    queryFn: async () => {
      const res = await api.getLeadEscalationLogs();
      return res.data ?? { data: [], total: 0 };
    },
  });
}

export function useOverdueFollowups() {
  return useQuery({
    queryKey: ['overdue-followups'],
    queryFn: async () => {
      const res = await api.getOverdueFollowups();
      return res.data ?? [];
    },
  });
}

export function useLeadFollowups(leadId?: number) {
  return useQuery({
    queryKey: ['lead-followups', leadId],
    queryFn: async () => {
      if (!leadId) return [];
      const res = await api.getLeadFollowups(leadId);
      return res.data ?? [];
    },
    enabled: Boolean(leadId),
  });
}

export function useScheduleLeadFollowup() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ leadId, data }: { leadId: number; data: Partial<LeadFollowupItem> }) =>
      api.scheduleLeadFollowup(leadId, data),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['lead-followups', vars.leadId] });
      queryClient.invalidateQueries({ queryKey: ['overdue-followups'] });
      queryClient.invalidateQueries({ queryKey: ['leads'] });
    },
  });
}

export function useCompleteLeadFollowup() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: { outcome: string; notes?: string } }) =>
      api.completeLeadFollowup(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['lead-followups'] });
      queryClient.invalidateQueries({ queryKey: ['overdue-followups'] });
      queryClient.invalidateQueries({ queryKey: ['leads'] });
    },
  });
}

export function useLeadStageHistory(leadId?: number) {
  return useQuery({
    queryKey: ['lead-stage-history', leadId],
    queryFn: async () => {
      if (!leadId) return [];
      const res = await api.getLeadStageHistory(leadId);
      return res.data ?? [];
    },
    enabled: Boolean(leadId),
  });
}

export function useTransitionLeadStage() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ leadId, data }: { leadId: number; data: { to_stage: string; reason?: string; notes?: string } }) =>
      api.transitionLeadStage(leadId, data),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['lead-stage-history', vars.leadId] });
      queryClient.invalidateQueries({ queryKey: ['leads'] });
      queryClient.invalidateQueries({ queryKey: ['lead-reports-dashboard'] });
    },
  });
}

export function useLeadMergeRequests() {
  return useQuery({
    queryKey: ['lead-merge-requests'],
    queryFn: async () => {
      const res = await api.getLeadMergeRequests();
      return res.data ?? [];
    },
  });
}

export function useApproveLeadMergeRequest() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.approveLeadMergeRequest(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['lead-merge-requests'] });
      queryClient.invalidateQueries({ queryKey: ['leads'] });
    },
  });
}

export function useRejectLeadMergeRequest() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason?: string }) => api.rejectLeadMergeRequest(id, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['lead-merge-requests'] });
    },
  });
}

export function useLeadScoringConfig() {
  return useQuery({
    queryKey: ['lead-scoring-config'],
    queryFn: async () => {
      const res = await api.getLeadScoringConfig();
      return res.data;
    },
  });
}

export function useSaveLeadScoringConfig() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: Partial<LeadScoringConfigurationItem>) => api.saveLeadScoringConfig(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['lead-scoring-config'] });
    },
  });
}

export function useLeadReportsDashboard() {
  return useQuery({
    queryKey: ['lead-reports-dashboard'],
    queryFn: async () => {
      const res = await api.getLeadReportsDashboard();
      return res.data;
    },
  });
}
