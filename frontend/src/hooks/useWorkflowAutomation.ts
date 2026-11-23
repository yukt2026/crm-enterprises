import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useCRMStore } from '../store/crmStore';
import {
  WorkflowItem,
  WorkflowRunItem,
  WorkflowTemplateItem,
  WorkflowSimulationResult,
  WorkflowDefinition,
  WorkflowApprovalItem,
  WorkflowAutomationLimitItem,
  WorkflowMetricsItem,
} from '../api/types';

// ==========================================
// 1. WORKFLOW HOOKS
// ==========================================

export function useWorkflows(params?: { category?: string; status?: string; search?: string }) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['workflows', workspaceId, params],
    queryFn: async () => {
      const res = await api.getWorkflows(params);
      return (res.data || []) as WorkflowItem[];
    },
  });
}

export function useWorkflow(id: number | null | undefined) {
  return useQuery({
    queryKey: ['workflow', id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getWorkflow(id);
      return res.data as WorkflowItem;
    },
    enabled: Boolean(id),
  });
}

export function useCreateWorkflow() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: {
      name: string;
      description?: string;
      category: string;
      trigger_type: string;
      definition: WorkflowDefinition;
    }) => {
      const res = await api.createWorkflow(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['workflows'] });
    },
  });
}

export function useUpdateWorkflow() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({
      id,
      data,
    }: {
      id: number;
      data: {
        name?: string;
        description?: string;
        category?: string;
        trigger_type?: string;
        definition?: WorkflowDefinition;
        changelog?: string;
      };
    }) => {
      const res = await api.updateWorkflow(id, data);
      return res.data;
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['workflows'] });
      queryClient.invalidateQueries({ queryKey: ['workflow', variables.id] });
    },
  });
}

export function usePublishWorkflow() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.publishWorkflow(id);
      return res.data;
    },
    onSuccess: (_, id) => {
      queryClient.invalidateQueries({ queryKey: ['workflows'] });
      queryClient.invalidateQueries({ queryKey: ['workflow', id] });
    },
  });
}

export function usePauseWorkflow() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.pauseWorkflow(id);
      return res.data;
    },
    onSuccess: (_, id) => {
      queryClient.invalidateQueries({ queryKey: ['workflows'] });
      queryClient.invalidateQueries({ queryKey: ['workflow', id] });
    },
  });
}

export function useDeleteWorkflow() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      await api.deleteWorkflow(id);
      return id;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['workflows'] });
    },
  });
}

export function useSimulateWorkflow() {
  return useMutation({
    mutationFn: async ({
      id,
      samplePayload,
      definition,
    }: {
      id: number;
      samplePayload: Record<string, any>;
      definition?: WorkflowDefinition;
    }) => {
      const res = await api.simulateWorkflow(id, samplePayload, definition);
      return res.data as WorkflowSimulationResult;
    },
  });
}

// ==========================================
// 2. TEMPLATES HOOKS
// ==========================================

export function useWorkflowTemplates() {
  return useQuery({
    queryKey: ['workflow-templates'],
    queryFn: async () => {
      const res = await api.getWorkflowTemplates();
      return (res.data || []) as WorkflowTemplateItem[];
    },
  });
}

export function useImportWorkflowTemplate() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ templateId, name }: { templateId: number; name?: string }) => {
      const res = await api.importWorkflowTemplate(templateId, name);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['workflows'] });
    },
  });
}

// ==========================================
// 3. WORKFLOW RUNS & LOGS HOOKS
// ==========================================

export function useWorkflowRuns(params?: {
  workflow_id?: number;
  status?: string;
  page?: number;
  per_page?: number;
}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['workflow-runs', workspaceId, params],
    queryFn: async () => {
      const res = await api.getWorkflowRuns(params);
      return (res.data || []) as WorkflowRunItem[];
    },
    refetchInterval: 8000,
  });
}

export function useWorkflowRun(id: number | null | undefined) {
  return useQuery({
    queryKey: ['workflow-run', id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getWorkflowRun(id);
      return res.data as WorkflowRunItem;
    },
    enabled: Boolean(id),
    refetchInterval: (query) => {
      const data = query.state.data;
      if (data && (data.status === 'running' || data.status === 'pending')) {
        return 3000;
      }
      return false;
    },
  });
}

export function useRetryWorkflowRun() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.retryWorkflowRun(id);
      return res.data;
    },
    onSuccess: (_, id) => {
      queryClient.invalidateQueries({ queryKey: ['workflow-runs'] });
      queryClient.invalidateQueries({ queryKey: ['workflow-run', id] });
    },
  });
}

export function useCancelWorkflowRun() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.cancelWorkflowRun(id);
      return res.data;
    },
    onSuccess: (_, id) => {
      queryClient.invalidateQueries({ queryKey: ['workflow-runs'] });
      queryClient.invalidateQueries({ queryKey: ['workflow-run', id] });
    },
  });
}

export function useTriggerManualWorkflow() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({
      workflowId,
      entityType,
      entityId,
      payload,
    }: {
      workflowId: number;
      entityType?: string;
      entityId?: number;
      payload?: Record<string, any>;
    }) => {
      const res = await api.triggerManualWorkflow(workflowId, entityType, entityId, payload);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['workflow-runs'] });
      queryClient.invalidateQueries({ queryKey: ['workflows'] });
    },
  });
}

// ==========================================
// 4. WORKFLOW APPROVALS HOOKS
// ==========================================

export function useWorkflowApprovals(params?: {
  status?: string;
  category?: string;
  approver_role?: string;
  page?: number;
  per_page?: number;
}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['workflow-approvals', workspaceId, params],
    queryFn: async () => {
      const res = await api.getWorkflowApprovals(params);
      return (res.data || []) as WorkflowApprovalItem[];
    },
  });
}

export function useWorkflowApproval(id: number | null | undefined) {
  return useQuery({
    queryKey: ['workflow-approval', id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getWorkflowApproval(id);
      return res.data as WorkflowApprovalItem;
    },
    enabled: Boolean(id),
  });
}

export function useDecideWorkflowApproval() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({
      id,
      decision,
      comments,
    }: {
      id: number;
      decision: 'approve' | 'reject';
      comments?: string;
    }) => {
      const res = await api.decideWorkflowApproval(id, { decision, comments });
      return res.data;
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['workflow-approvals'] });
      queryClient.invalidateQueries({ queryKey: ['workflow-approval', variables.id] });
      queryClient.invalidateQueries({ queryKey: ['workflow-runs'] });
      queryClient.invalidateQueries({ queryKey: ['workflow-metrics'] });
    },
  });
}

// ==========================================
// 5. WORKFLOW LIMITS & METRICS HOOKS
// ==========================================

export function useWorkflowLimits() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['workflow-limits', workspaceId],
    queryFn: async () => {
      const res = await api.getWorkflowLimits();
      return res.data;
    },
  });
}

export function useUpdateWorkflowLimits() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: Partial<WorkflowAutomationLimitItem>) => {
      const res = await api.updateWorkflowLimits(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['workflow-limits'] });
      queryClient.invalidateQueries({ queryKey: ['workflow-metrics'] });
    },
  });
}

export function useWorkflowMetrics() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['workflow-metrics', workspaceId],
    queryFn: async () => {
      const res = await api.getWorkflowMetrics();
      return res.data as WorkflowMetricsItem;
    },
    refetchInterval: 15000,
  });
}

export function useWorkflowTriggersCatalog() {
  return useQuery({
    queryKey: ['workflow-triggers-catalog'],
    queryFn: async () => {
      const res = await api.getWorkflowTriggersCatalog();
      return res.data as Record<string, any>;
    },
    staleTime: 1000 * 60 * 30, // 30 minutes cache
  });
}
