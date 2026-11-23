import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import {
  BranchItem,
  BranchTransferItem,
  BranchSalesTargetItem,
  BranchDashboardMetrics,
  ConsolidatedBranchAnalytics,
} from '../api/types';

export function useBranches(params: { search?: string; is_active?: boolean; city?: string } = {}) {
  return useQuery({
    queryKey: ['branches', params],
    queryFn: () => api.getBranches(params),
  });
}

export function useBranch(id?: number) {
  return useQuery({
    queryKey: ['branch', id],
    queryFn: () => (id ? api.getBranch(id) : null),
    enabled: !!id,
  });
}

export function useCreateBranch() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: Partial<BranchItem> & { department_ids?: number[] }) => api.createBranch(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['branches'] });
      queryClient.invalidateQueries({ queryKey: ['consolidated-branch-analytics'] });
    },
  });
}

export function useUpdateBranch() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<BranchItem> & { department_ids?: number[] } }) =>
      api.updateBranch(id, data),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['branches'] });
      queryClient.invalidateQueries({ queryKey: ['branch', variables.id] });
      queryClient.invalidateQueries({ queryKey: ['branch-dashboard', variables.id] });
      queryClient.invalidateQueries({ queryKey: ['consolidated-branch-analytics'] });
    },
  });
}

export function useDeleteBranch() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.deleteBranch(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['branches'] });
      queryClient.invalidateQueries({ queryKey: ['consolidated-branch-analytics'] });
    },
  });
}

export function useSetBranchStatus() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, is_active }: { id: number; is_active: boolean }) => api.setBranchStatus(id, is_active),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['branches'] });
      queryClient.invalidateQueries({ queryKey: ['branch', variables.id] });
      queryClient.invalidateQueries({ queryKey: ['consolidated-branch-analytics'] });
    },
  });
}

export function useAssignEmployeesToBranch() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, user_ids, department_id }: { id: number; user_ids: number[]; department_id?: number }) =>
      api.assignEmployeesToBranch(id, user_ids, department_id),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['branches'] });
      queryClient.invalidateQueries({ queryKey: ['branch', variables.id] });
      queryClient.invalidateQueries({ queryKey: ['branch-dashboard', variables.id] });
    },
  });
}

export function useTransferBranchRecord() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: {
      transferable_type: string;
      transferable_id: number;
      to_branch_id: number;
      reason?: string;
      approval_notes?: string;
      metadata?: Record<string, any>;
    }) => api.transferBranchRecord(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['branch-transfers'] });
      queryClient.invalidateQueries({ queryKey: ['branches'] });
      queryClient.invalidateQueries({ queryKey: ['consolidated-branch-analytics'] });
    },
  });
}

export function useBranchTransferHistory(params: {
  transferable_type?: string;
  branch_id?: number;
  per_page?: number;
  page?: number;
} = {}) {
  return useQuery({
    queryKey: ['branch-transfers', params],
    queryFn: () => api.getBranchTransferHistory(params),
  });
}

export function useBranchDashboard(id?: number) {
  return useQuery({
    queryKey: ['branch-dashboard', id],
    queryFn: () => (id ? api.getBranchDashboard(id) : null),
    enabled: !!id,
  });
}

export function useConsolidatedBranchAnalytics(params: {
  start_date?: string;
  end_date?: string;
  department_id?: number;
} = {}) {
  return useQuery({
    queryKey: ['consolidated-branch-analytics', params],
    queryFn: () => api.getConsolidatedBranchAnalytics(params),
  });
}

export function useBranchSettings(id?: number) {
  return useQuery({
    queryKey: ['branch-settings', id],
    queryFn: () => (id ? api.getBranchSettings(id) : null),
    enabled: !!id,
  });
}

export function useUpdateBranchSettings() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: any }) => api.updateBranchSettings(id, data),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['branch-settings', variables.id] });
    },
  });
}

export function useBranchSalesTargets(id?: number) {
  return useQuery({
    queryKey: ['branch-sales-targets', id],
    queryFn: () => (id ? api.getBranchSalesTargets(id) : null),
    enabled: !!id,
  });
}

export function useSaveBranchSalesTarget() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<BranchSalesTargetItem> }) =>
      api.saveBranchSalesTarget(id, data),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['branch-sales-targets', variables.id] });
      queryClient.invalidateQueries({ queryKey: ['branch-dashboard', variables.id] });
    },
  });
}
