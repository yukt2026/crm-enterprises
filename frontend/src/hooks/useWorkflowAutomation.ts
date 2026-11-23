import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useCRMStore } from '../store/crmStore';
import {
  WorkflowItem,
  WorkflowRunItem,
  WorkflowTemplateItem,
  WorkflowSimulationResult,
  WorkflowDefinition,
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
