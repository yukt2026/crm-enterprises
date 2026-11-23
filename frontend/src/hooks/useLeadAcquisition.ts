import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useCRMStore } from '../store/crmStore';

export function useLeadSources() {
  return useQuery({
    queryKey: ['lead-acquisition', 'sources'],
    queryFn: async () => {
      const res = await api.getLeadSources();
      return res.data || { sources: [], supported_providers: [] };
    },
    staleTime: 1000 * 30,
  });
}

export function useLeadAcquisitionStats() {
  return useQuery({
    queryKey: ['lead-acquisition', 'stats'],
    queryFn: async () => {
      const res = await api.getLeadAcquisitionStats();
      return res.data;
    },
    staleTime: 1000 * 30,
  });
}

export function useConnectLeadSource() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: {
      provider: string;
      name: string;
      account_id?: string;
      credentials?: Record<string, any>;
      config?: Record<string, any>;
    }) => api.createLeadSource(data),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['lead-acquisition', 'sources'] });
      queryClient.invalidateQueries({ queryKey: ['lead-acquisition', 'stats'] });
      addToast(res.message || 'Lead source connected successfully!', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to connect lead source.', 'error');
    },
  });
}

export function useDisconnectLeadSource() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number | string) => api.deleteLeadSource(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['lead-acquisition', 'sources'] });
      queryClient.invalidateQueries({ queryKey: ['lead-acquisition', 'stats'] });
      addToast('Lead source disconnected.', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to disconnect source.', 'error');
    },
  });
}

export function useTestLeadSource() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number | string) => api.testLeadSource(id),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['lead-acquisition', 'sources'] });
      addToast(res.message || 'Connection verified successfully!', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Connection test failed.', 'error');
    },
  });
}

export function useSyncLeadSourceMetrics() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number | string) => api.syncLeadSourceMetrics(id),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['lead-acquisition', 'sources'] });
      queryClient.invalidateQueries({ queryKey: ['lead-acquisition', 'metrics'] });
      queryClient.invalidateQueries({ queryKey: ['lead-acquisition', 'stats'] });
      addToast(res.message || 'Campaign metrics synchronized!', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Metric synchronization failed.', 'error');
    },
  });
}

export function useLeadForms() {
  return useQuery({
    queryKey: ['lead-acquisition', 'forms'],
    queryFn: async () => {
      const res = await api.getLeadForms();
      return res.data || [];
    },
    staleTime: 1000 * 30,
  });
}

export function useCreateLeadForm() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: {
      name: string;
      provider?: string;
      lead_source_id?: number | null;
      fields_config?: any[];
      campaign_name?: string;
    }) => api.createLeadForm(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['lead-acquisition', 'forms'] });
      addToast('Lead form created successfully!', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create lead form.', 'error');
    },
  });
}

export function useDeleteLeadForm() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number | string) => api.deleteLeadForm(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['lead-acquisition', 'forms'] });
      addToast('Lead form deleted.', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to delete lead form.', 'error');
    },
  });
}

export function useLeadFieldMappings(provider?: string) {
  return useQuery({
    queryKey: ['lead-acquisition', 'field-mappings', provider],
    queryFn: async () => {
      const res = await api.getLeadFieldMappings(provider);
      return res.data || { mappings: [], available_crm_fields: [] };
    },
    staleTime: 1000 * 30,
  });
}

export function useSaveLeadFieldMapping() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: {
      provider: string;
      source_field: string;
      crm_field: string;
      transform_rule?: string;
      default_value?: string;
      is_required?: boolean;
      lead_source_id?: number | null;
    }) => api.saveLeadFieldMapping(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['lead-acquisition', 'field-mappings'] });
      addToast('Field mapping rule saved.', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to save field mapping.', 'error');
    },
  });
}

export function useDeleteLeadFieldMapping() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number | string) => api.deleteLeadFieldMapping(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['lead-acquisition', 'field-mappings'] });
      addToast('Field mapping deleted.', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to delete field mapping.', 'error');
    },
  });
}

export function useLeadIngestionEvents(params: {
  status?: string;
  provider?: string;
  page?: number;
  per_page?: number;
} = {}) {
  return useQuery({
    queryKey: ['lead-acquisition', 'events', params],
    queryFn: async () => {
      const res = await api.getLeadIngestionEvents(params);
      return res.data;
    },
    staleTime: 1000 * 15,
  });
}

export function useRetryLeadIngestionEvent() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number | string) => api.retryLeadIngestionEvent(id),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['lead-acquisition', 'events'] });
      queryClient.invalidateQueries({ queryKey: ['lead-acquisition', 'stats'] });
      queryClient.invalidateQueries({ queryKey: ['crm', 'leads'] });
      addToast(res.message || 'Ingestion event reprocessed successfully!', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Event retry failed.', 'error');
    },
  });
}

export function useAdCampaignMetrics(params: {
  provider?: string;
  page?: number;
  per_page?: number;
} = {}) {
  return useQuery({
    queryKey: ['lead-acquisition', 'metrics', params],
    queryFn: async () => {
      const res = await api.getAdCampaignMetrics(params);
      return res.data;
    },
    staleTime: 1000 * 30,
  });
}
