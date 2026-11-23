import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useCRMStore } from '../store/crmStore';
import {
  LandingPageItem,
  LandingPageTemplateItem,
  LandingPageAnalyticsData,
  FormItem,
  FormSubmissionItem,
  CustomDomainItem,
} from '../api/types';

// ==========================================
// 1. LANDING PAGES HOOKS
// ==========================================

export function useLandingPages(params?: { status?: string; search?: string }) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['landing-pages', workspaceId, params],
    queryFn: async () => {
      const res = await api.getLandingPages(params);
      return (res.data || []) as LandingPageItem[];
    },
  });
}

export function useLandingPage(id: number | null | undefined) {
  return useQuery({
    queryKey: ['landing-page', id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getLandingPage(id);
      return res.data as LandingPageItem;
    },
    enabled: Boolean(id),
  });
}

export function useCreateLandingPage() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: Partial<LandingPageItem>) => {
      const res = await api.createLandingPage(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['landing-pages'] });
    },
  });
}

export function useUpdateLandingPage() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Partial<LandingPageItem> }) => {
      const res = await api.updateLandingPage(id, data);
      return res.data;
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['landing-pages'] });
      queryClient.invalidateQueries({ queryKey: ['landing-page', variables.id] });
    },
  });
}

export function useDeleteLandingPage() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      await api.deleteLandingPage(id);
      return id;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['landing-pages'] });
    },
  });
}

export function usePublishLandingPage() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, changelog }: { id: number; changelog?: string }) => {
      const res = await api.publishLandingPage(id, changelog);
      return res.data;
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['landing-pages'] });
      queryClient.invalidateQueries({ queryKey: ['landing-page', variables.id] });
    },
  });
}

export function useUnpublishLandingPage() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.unpublishLandingPage(id);
      return res.data;
    },
    onSuccess: (_, id) => {
      queryClient.invalidateQueries({ queryKey: ['landing-pages'] });
      queryClient.invalidateQueries({ queryKey: ['landing-page', id] });
    },
  });
}

export function useRollbackLandingPage() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, version }: { id: number; version: number }) => {
      const res = await api.rollbackLandingPage(id, version);
      return res.data;
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['landing-pages'] });
      queryClient.invalidateQueries({ queryKey: ['landing-page', variables.id] });
    },
  });
}

export function useLandingPageTemplates() {
  return useQuery({
    queryKey: ['landing-page-templates'],
    queryFn: async () => {
      const res = await api.getLandingPageTemplates();
      return (res.data || []) as LandingPageTemplateItem[];
    },
  });
}

export function useCreateLandingPageFromTemplate() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({
      templateId,
      name,
      slug,
    }: {
      templateId: number;
      name: string;
      slug?: string;
    }) => {
      const res = await api.createLandingPageFromTemplate(templateId, name, slug);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['landing-pages'] });
    },
  });
}

export function useLandingPageAnalytics(id: number | null | undefined, params?: { start_date?: string; end_date?: string }) {
  return useQuery({
    queryKey: ['landing-page-analytics', id, params],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getLandingPageAnalytics(id, params);
      return res.data;
    },
    enabled: Boolean(id),
  });
}

// ==========================================
// 2. FORM BUILDER HOOKS
// ==========================================

export function useForms() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['forms', workspaceId],
    queryFn: async () => {
      const res = await api.getForms();
      return (res.data || []) as FormItem[];
    },
  });
}

export function useForm(id: number | null | undefined) {
  return useQuery({
    queryKey: ['form', id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getForm(id);
      return res.data as FormItem;
    },
    enabled: Boolean(id),
  });
}

export function useCreateForm() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: Partial<FormItem>) => {
      const res = await api.createForm(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['forms'] });
    },
  });
}

export function useUpdateForm() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Partial<FormItem> }) => {
      const res = await api.updateForm(id, data);
      return res.data;
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['forms'] });
      queryClient.invalidateQueries({ queryKey: ['form', variables.id] });
    },
  });
}

export function useDeleteForm() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      await api.deleteForm(id);
      return id;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['forms'] });
    },
  });
}

export function useFormSubmissions(formId: number | null | undefined, page = 1) {
  return useQuery({
    queryKey: ['form-submissions', formId, page],
    queryFn: async () => {
      if (!formId) return { data: [], pagination: null };
      const res = await api.getFormSubmissions(formId, page);
      return {
        data: (res.data || []) as FormSubmissionItem[],
        pagination: res.pagination,
      };
    },
    enabled: Boolean(formId),
  });
}

// ==========================================
// 3. CUSTOM DOMAINS HOOKS
// ==========================================

export function useCustomDomains() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['custom-domains', workspaceId],
    queryFn: async () => {
      const res = await api.getCustomDomains();
      return (res.data || []) as CustomDomainItem[];
    },
  });
}

export function useAddCustomDomain() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (domain: string) => {
      const res = await api.addCustomDomain(domain);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['custom-domains'] });
    },
  });
}

export function useVerifyCustomDomain() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.verifyCustomDomain(id);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['custom-domains'] });
      queryClient.invalidateQueries({ queryKey: ['landing-pages'] });
    },
  });
}

export function useDeleteCustomDomain() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      await api.deleteCustomDomain(id);
      return id;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['custom-domains'] });
    },
  });
}
