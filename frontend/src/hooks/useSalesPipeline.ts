import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useCRMStore } from '../store/crmStore';
import {
  PipelineItem,
  PipelineStageItem,
  DealItem,
  DealForecastData,
  StaleDealItem,
  TaskItem,
  SalesTeamItem,
  SalesQuotaItem,
  SalesLeaderboardData,
  ContractItem,
  ContractTemplateItem,
  CommissionPlanItem,
  SalesCommissionItem,
  AdvancedSalesForecastData,
} from '../api/types';

// ==========================================
// 1. PIPELINE & CUSTOM STAGES HOOKS
// ==========================================

export function usePipelines() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['sales-pipelines', workspaceId],
    queryFn: async () => {
      const res = await api.getPipelines();
      return (res.data || []) as PipelineItem[];
    },
  });
}

export function useCreatePipeline() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: { name: string; description?: string; is_default?: boolean }) =>
      api.createPipeline(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales-pipelines'] });
      addToast('Pipeline created successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create pipeline', 'error');
    },
  });
}

export function useUpdatePipeline() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<PipelineItem> }) =>
      api.updatePipeline(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales-pipelines'] });
      addToast('Pipeline updated', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to update pipeline', 'error');
    },
  });
}

export function useDeletePipeline() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.deletePipeline(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales-pipelines'] });
      addToast('Pipeline deleted', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to delete pipeline', 'error');
    },
  });
}

export function useCreatePipelineStage() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ pipelineId, data }: { pipelineId: number; data: any }) =>
      api.createPipelineStage(pipelineId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales-pipelines'] });
      addToast('Pipeline stage created', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create stage', 'error');
    },
  });
}

export function useUpdatePipelineStage() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ stageId, data }: { stageId: number; data: Partial<PipelineStageItem> }) =>
      api.updatePipelineStage(stageId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales-pipelines'] });
      addToast('Stage updated', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to update stage', 'error');
    },
  });
}

export function useDeletePipelineStage() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (stageId: number) => api.deletePipelineStage(stageId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales-pipelines'] });
      addToast('Stage removed', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to delete stage', 'error');
    },
  });
}

// ==========================================
// 2. DEALS, KANBAN & STAGE TRANSITIONS
// ==========================================

export function useDeals(params: Record<string, any> = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['deals', workspaceId, params],
    queryFn: async () => {
      const res = await api.getRecords<DealItem>('deals', params);
      return (res.data || []) as DealItem[];
    },
  });
}

export function useMoveDealStage() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ dealId, data }: { dealId: number; data: any }) =>
      api.moveDealToStage(dealId, data),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['deals'] });
      queryClient.invalidateQueries({ queryKey: ['deal-forecast'] });
      queryClient.invalidateQueries({ queryKey: ['stale-deals'] });
      queryClient.invalidateQueries({ queryKey: ['crm-activities'] });
      addToast(`Deal moved to ${res.data.stage}`, 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to transition deal stage', 'error');
    },
  });
}

export function useAddDealProduct() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ dealId, data }: { dealId: number; data: any }) =>
      api.addDealProduct(dealId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['deals'] });
      queryClient.invalidateQueries({ queryKey: ['deal-forecast'] });
      addToast('Product added to deal', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to add product', 'error');
    },
  });
}

export function useRemoveDealProduct() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ dealId, productId }: { dealId: number; productId: number }) =>
      api.removeDealProduct(dealId, productId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['deals'] });
      queryClient.invalidateQueries({ queryKey: ['deal-forecast'] });
      addToast('Product removed from deal', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to remove product', 'error');
    },
  });
}

export function useDealForecast(params: { pipeline_id?: number; start_date?: string; end_date?: string } = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['deal-forecast', workspaceId, params],
    queryFn: async () => {
      const res = await api.getDealForecast(params);
      return res.data as DealForecastData;
    },
  });
}

export function useStaleDeals(days?: number) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['stale-deals', workspaceId, days],
    queryFn: async () => {
      const res = await api.getStaleDeals(days);
      return (res.data || []) as StaleDealItem[];
    },
  });
}

export function useArchiveDeal() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ dealId, archive }: { dealId: number; archive: boolean }) =>
      archive ? api.archiveDeal(dealId) : api.unarchiveDeal(dealId),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['deals'] });
      queryClient.invalidateQueries({ queryKey: ['deal-forecast'] });
      addToast(variables.archive ? 'Deal archived' : 'Deal restored', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to archive/unarchive deal', 'error');
    },
  });
}

// ==========================================
// 3. TASKS & FOLLOW-UPS HOOKS
// ==========================================

export function useTasks(params: {
  view?: 'my_tasks' | 'team_tasks' | 'today' | 'upcoming' | 'overdue' | 'completed';
  status?: string;
  priority?: string;
  deal_id?: number;
  lead_id?: number;
} = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['crm-tasks', workspaceId, params],
    queryFn: async () => {
      const res = await api.getTasks(params);
      return (res.data || []) as TaskItem[];
    },
  });
}

export function useCreateTask() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<TaskItem>) => api.createTask(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['crm-tasks'] });
      addToast('Task created', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create task', 'error');
    },
  });
}

export function useUpdateTask() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<TaskItem> }) =>
      api.updateTask(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['crm-tasks'] });
      addToast('Task updated', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to update task', 'error');
    },
  });
}

export function useCompleteTask() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.completeTask(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['crm-tasks'] });
      addToast('Task marked as completed', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to complete task', 'error');
    },
  });
}

export function useToggleTaskChecklist() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ taskId, itemId, completed }: { taskId: number; itemId: string; completed: boolean }) =>
      api.toggleTaskChecklist(taskId, itemId, completed),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['crm-tasks'] });
    },
  });
}

export function useDeleteTask() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.deleteTask(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['crm-tasks'] });
      addToast('Task deleted', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to delete task', 'error');
    },
  });
}

// ==========================================
// 4. SALES TEAMS, QUOTAS & LEADERBOARD HOOKS
// ==========================================

export function useSalesTeams() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['sales-teams', workspaceId],
    queryFn: async () => {
      const res = await api.getSalesTeams();
      return (res.data || []) as SalesTeamItem[];
    },
  });
}

export function useCreateSalesTeam() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: { name: string; leader_id?: number; member_ids?: number[]; description?: string }) =>
      api.createSalesTeam(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales-teams'] });
      addToast('Sales team created', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create sales team', 'error');
    },
  });
}

export function useSalesLeaderboard(params: { period?: 'monthly' | 'quarterly' | 'yearly'; team_id?: number } = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['sales-leaderboard', workspaceId, params],
    queryFn: async () => {
      const res = await api.getSalesLeaderboard(params);
      return res.data as SalesLeaderboardData;
    },
  });
}

export function useSalesQuotas(params: { team_id?: number; period?: string } = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['sales-quotas', workspaceId, params],
    queryFn: async () => {
      const res = await api.getSalesQuotas(params);
      return (res.data || []) as SalesQuotaItem[];
    },
  });
}

export function useSaveSalesQuota() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: {
      sales_team_id?: number;
      user_id?: number;
      period: string;
      start_date: string;
      end_date: string;
      target_revenue: number;
      target_deals_count?: number;
      target_leads_count?: number;
    }) => api.saveSalesQuota(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales-quotas'] });
      queryClient.invalidateQueries({ queryKey: ['sales-leaderboard'] });
      addToast('Sales quota saved', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to save sales quota', 'error');
    },
  });
}

// ==========================================
// 6. PROMPT 39: EXTENDED DEALS, FORECAST, CONTRACTS & COMMISSIONS HOOKS
// ==========================================

export function useTransferDealOwnership() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ dealId, data }: { dealId: number; data: { user_id: number; reason?: string } }) =>
      api.transferDealOwnership(dealId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['deals'] });
      addToast('Deal ownership reassigned successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to transfer deal ownership', 'error');
    },
  });
}

export function useReopenDeal() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ dealId, data }: { dealId: number; data: { stage_id: number; reason?: string } }) =>
      api.reopenDeal(dealId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['deals'] });
      queryClient.invalidateQueries({ queryKey: ['deal-forecast'] });
      addToast('Deal reopened successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to reopen deal', 'error');
    },
  });
}

export function useConvertDealToProject() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ dealId, data }: { dealId: number; data?: { name?: string; description?: string } }) =>
      api.convertDealToProject(dealId, data),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['deals'] });
      queryClient.invalidateQueries({ queryKey: ['projects'] });
      addToast('Deal converted into onboarding project successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to convert deal to project', 'error');
    },
  });
}

export function useAdvancedSalesForecast(params: {
  pipeline_id?: number;
  branch_id?: number;
  start_date?: string;
  end_date?: string;
} = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['advanced-sales-forecast', workspaceId, params],
    queryFn: async () => {
      const res = await api.getAdvancedSalesForecast(params);
      return res.data as AdvancedSalesForecastData;
    },
  });
}

export function useContracts(params: {
  deal_id?: number;
  customer_id?: number;
  status?: string;
  contract_type?: string;
  search?: string;
} = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['crm-contracts', workspaceId, params],
    queryFn: async () => {
      const res = await api.getContracts(params);
      return (res.data || []) as ContractItem[];
    },
  });
}

export function useContractTemplates(params: { category?: string } = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['crm-contract-templates', workspaceId, params],
    queryFn: async () => {
      const res = await api.getContractTemplates(params);
      return (res.data || []) as ContractTemplateItem[];
    },
  });
}

export function useCreateContract() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<ContractItem>) => api.createContract(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['crm-contracts'] });
      addToast('Contract created successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create contract', 'error');
    },
  });
}

export function useSendContractForSignature() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, data }: {
      id: number;
      data: { signer_name: string; signer_email: string; signer_phone?: string; provider?: string };
    }) => api.sendContractForSignature(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['crm-contracts'] });
      addToast('Contract dispatched for digital e-signature', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to send contract for signature', 'error');
    },
  });
}

export function useSignContract() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, data }: {
      id: number;
      data: { signature_data: string; otp_code?: string; ip_address?: string };
    }) => api.signContract(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['crm-contracts'] });
      addToast('Contract legally executed with tamper-evident audit hash', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to sign contract', 'error');
    },
  });
}

export function useDeclineContract() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) =>
      api.declineContract(id, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['crm-contracts'] });
      addToast('Contract declined', 'warning');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to decline contract', 'error');
    },
  });
}

export function useSalesCommissions(params: {
  user_id?: number;
  deal_id?: number;
  status?: string;
} = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['sales-commissions', workspaceId, params],
    queryFn: async () => {
      const res = await api.getSalesCommissions(params);
      return (res.data || []) as SalesCommissionItem[];
    },
  });
}

export function useCommissionPlans() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['sales-commission-plans', workspaceId],
    queryFn: async () => {
      const res = await api.getCommissionPlans();
      return (res.data || []) as CommissionPlanItem[];
    },
  });
}

export function useCreateCommissionPlan() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<CommissionPlanItem>) => api.createCommissionPlan(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales-commission-plans'] });
      addToast('Commission plan configured successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create commission plan', 'error');
    },
  });
}

export function useApproveCommission() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, notes }: { id: number; notes?: string }) =>
      api.approveCommission(id, notes),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales-commissions'] });
      addToast('Commission approved for payout', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to approve commission', 'error');
    },
  });
}

export function useMarkCommissionPaid() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, payoutReference }: { id: number; payoutReference?: string }) =>
      api.markCommissionPaid(id, payoutReference),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales-commissions'] });
      addToast('Commission recorded as paid', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to mark commission paid', 'error');
    },
  });
}

export function useReverseCommission() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) =>
      api.reverseCommission(id, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales-commissions'] });
      addToast('Commission clawback reversal executed', 'warning');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to reverse commission', 'error');
    },
  });
}

export function useCommissionReport(params: {
  user_id?: number;
  start_date?: string;
  end_date?: string;
} = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['sales-commission-report', workspaceId, params],
    queryFn: async () => {
      const res = await api.getCommissionReport(params);
      return res.data;
    },
  });
}

