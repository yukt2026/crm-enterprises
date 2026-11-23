import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useCRMStore } from '../store/crmStore';

export interface UseModuleRecordsParams {
  page?: number;
  limit?: number;
  q?: string;
  sortBy?: string;
  sortOrder?: 'asc' | 'desc';
  filters?: Record<string, string>;
}

export function useModuleRecords<T = any>(module: string, params: UseModuleRecordsParams = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['crm', module, workspaceId, params],
    queryFn: async () => {
      const queryParams: Record<string, any> = {
        page: params.page || 1,
        limit: params.limit || 10,
      };
      if (params.q) queryParams.q = params.q;
      if (params.sortBy) queryParams.sort_by = params.sortBy;
      if (params.sortOrder) queryParams.sort_order = params.sortOrder;
      if (params.filters) {
        Object.entries(params.filters).forEach(([k, v]) => {
          if (v) queryParams[k] = v;
        });
      }

      const res = await api.getRecords<T>(module, queryParams);
      return res;
    },
    placeholderData: (prev) => prev,
  });
}

export function useCreateRecord<T = any>(module: string) {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: async (data: any) => {
      return api.createRecord<T>(module, data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['crm', module] });
      queryClient.invalidateQueries({ queryKey: ['crm', 'reports'] });
      addToast(`Record added to ${module} successfully`, 'success');
    },
    onError: (err: any) => {
      addToast(err.message || `Failed to add ${module} record`, 'error');
    },
  });
}

export function useUpdateRecord<T = any>(module: string) {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: async ({ id, data }: { id: string | number; data: any }) => {
      return api.updateRecord<T>(module, id, data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['crm', module] });
      queryClient.invalidateQueries({ queryKey: ['crm', 'reports'] });
      addToast(`Updated ${module} successfully`, 'success');
    },
    onError: (err: any) => {
      addToast(err.message || `Failed to update ${module}`, 'error');
    },
  });
}

export function useDeleteRecord(module: string) {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: async (id: string | number) => {
      return api.deleteRecord(module, id);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['crm', module] });
      queryClient.invalidateQueries({ queryKey: ['crm', 'reports'] });
      addToast(`Deleted record from ${module}`, 'success');
    },
    onError: (err: any) => {
      addToast(err.message || `Failed to delete record`, 'error');
    },
  });
}

export function useBulkDelete(module: string) {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: async (ids: (string | number)[]) => {
      return api.bulkDeleteRecords(module, ids);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['crm', module] });
      queryClient.invalidateQueries({ queryKey: ['crm', 'reports'] });
      addToast(`Bulk deleted records from ${module}`, 'success');
    },
    onError: (err: any) => {
      addToast(err.message || `Failed to bulk delete`, 'error');
    },
  });
}

export function useReports() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['crm', 'reports', workspaceId],
    queryFn: async () => {
      return api.getReports();
    },
  });
}

export function useHealthCheck() {
  return useQuery({
    queryKey: ['system', 'health'],
    queryFn: async () => {
      return api.getHealth();
    },
    refetchInterval: 30000, // Check health every 30 seconds
    retry: 1,
  });
}
