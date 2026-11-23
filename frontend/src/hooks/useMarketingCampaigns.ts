import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useCRMStore } from '../store/crmStore';
import {
  MarketingSegmentItem,
  MarketingCampaignItem,
  CampaignAnalyticsData,
  DripSequenceItem,
  DripEnrollmentItem,
} from '../api/types';

// ==========================================
// 1. MARKETING SEGMENTS HOOKS
// ==========================================

export function useMarketingSegments() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['marketing-segments', workspaceId],
    queryFn: async () => {
      const res = await api.getSegments();
      return (res.data || []) as MarketingSegmentItem[];
    },
  });
}

export function useMarketingSegment(id: number | null | undefined) {
  return useQuery({
    queryKey: ['marketing-segment', id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getSegment(id);
      return res.data as MarketingSegmentItem;
    },
    enabled: Boolean(id),
  });
}

export function useCreateMarketingSegment() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: Partial<MarketingSegmentItem>) => {
      const res = await api.createSegment(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['marketing-segments'] });
    },
  });
}

export function useUpdateMarketingSegment() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Partial<MarketingSegmentItem> }) => {
      const res = await api.updateSegment(id, data);
      return res.data;
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['marketing-segments'] });
      queryClient.invalidateQueries({ queryKey: ['marketing-segment', variables.id] });
    },
  });
}

export function useDeleteMarketingSegment() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      await api.deleteSegment(id);
      return id;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['marketing-segments'] });
    },
  });
}

export function useMarketingSegmentMembers(id: number | null | undefined, channel?: string) {
  return useQuery({
    queryKey: ['marketing-segment-members', id, channel],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getSegmentMembers(id, channel);
      return res.data;
    },
    enabled: Boolean(id),
  });
}

// ==========================================
// 2. MARKETING CAMPAIGNS HOOKS
// ==========================================

export function useMarketingCampaigns(params?: { status?: string; channel?: string; search?: string }) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['marketing-campaigns', workspaceId, params],
    queryFn: async () => {
      const res = await api.getCampaigns(params);
      return (res.data || []) as MarketingCampaignItem[];
    },
  });
}

export function useMarketingCampaign(id: number | null | undefined) {
  return useQuery({
    queryKey: ['marketing-campaign', id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getCampaign(id);
      return res.data as MarketingCampaignItem;
    },
    enabled: Boolean(id),
  });
}

export function useCreateMarketingCampaign() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: Partial<MarketingCampaignItem>) => {
      const res = await api.createCampaign(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['marketing-campaigns'] });
    },
  });
}

export function useUpdateMarketingCampaign() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Partial<MarketingCampaignItem> }) => {
      const res = await api.updateCampaign(id, data);
      return res.data;
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['marketing-campaigns'] });
      queryClient.invalidateQueries({ queryKey: ['marketing-campaign', variables.id] });
    },
  });
}

export function useDeleteMarketingCampaign() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      await api.deleteCampaign(id);
      return id;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['marketing-campaigns'] });
    },
  });
}

export function useDispatchMarketingCampaign() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.dispatchCampaign(id);
      return res.data;
    },
    onSuccess: (_, id) => {
      queryClient.invalidateQueries({ queryKey: ['marketing-campaigns'] });
      queryClient.invalidateQueries({ queryKey: ['marketing-campaign', id] });
      queryClient.invalidateQueries({ queryKey: ['campaign-analytics', id] });
    },
  });
}

export function useTestSendMarketingCampaign() {
  return useMutation({
    mutationFn: async ({ id, recipient }: { id: number; recipient: string }) => {
      const res = await api.testSendCampaign(id, recipient);
      return res.data;
    },
  });
}

export function usePauseMarketingCampaign() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.pauseCampaign(id);
      return res.data;
    },
    onSuccess: (_, id) => {
      queryClient.invalidateQueries({ queryKey: ['marketing-campaigns'] });
      queryClient.invalidateQueries({ queryKey: ['marketing-campaign', id] });
    },
  });
}

export function useResumeMarketingCampaign() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.resumeCampaign(id);
      return res.data;
    },
    onSuccess: (_, id) => {
      queryClient.invalidateQueries({ queryKey: ['marketing-campaigns'] });
      queryClient.invalidateQueries({ queryKey: ['marketing-campaign', id] });
    },
  });
}

export function useCampaignAnalytics(id: number | null | undefined) {
  return useQuery({
    queryKey: ['campaign-analytics', id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getCampaignAnalytics(id);
      return res.data as CampaignAnalyticsData;
    },
    enabled: Boolean(id),
  });
}

// ==========================================
// 3. DRIP SEQUENCES HOOKS
// ==========================================

export function useDripSequences() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['drip-sequences', workspaceId],
    queryFn: async () => {
      const res = await api.getDripSequences();
      return (res.data || []) as DripSequenceItem[];
    },
  });
}

export function useDripSequence(id: number | null | undefined) {
  return useQuery({
    queryKey: ['drip-sequence', id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getDripSequence(id);
      return res.data;
    },
    enabled: Boolean(id),
  });
}

export function useCreateDripSequence() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: Partial<DripSequenceItem>) => {
      const res = await api.createDripSequence(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['drip-sequences'] });
    },
  });
}

export function useUpdateDripSequence() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Partial<DripSequenceItem> }) => {
      const res = await api.updateDripSequence(id, data);
      return res.data;
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['drip-sequences'] });
      queryClient.invalidateQueries({ queryKey: ['drip-sequence', variables.id] });
    },
  });
}

export function useDeleteDripSequence() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      await api.deleteDripSequence(id);
      return id;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['drip-sequences'] });
    },
  });
}

export function useEnrollInDripSequence() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({
      id,
      entityType,
      entityId,
      context,
    }: {
      id: number;
      entityType: string;
      entityId: number;
      context?: Record<string, any>;
    }) => {
      const res = await api.enrollInDripSequence(id, entityType, entityId, context);
      return res.data;
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['drip-sequences'] });
      queryClient.invalidateQueries({ queryKey: ['drip-sequence', variables.id] });
    },
  });
}

export function useProcessDueDripEnrollments() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async () => {
      const res = await api.processDueDripEnrollments();
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['drip-sequences'] });
    },
  });
}

export function usePreviewMarketingSegment() {
  return useMutation({
    mutationFn: async (data: { rules?: any; target_entity?: string; inclusion_ids?: number[]; exclusion_ids?: number[] }) => {
      const res = await api.previewSegment(data);
      return res.data;
    },
  });
}

// Aliases for component convenience
export const useSegments = useMarketingSegments;
export const useSegment = useMarketingSegment;
export const useCreateSegment = useCreateMarketingSegment;
export const useUpdateSegment = useUpdateMarketingSegment;
export const useDeleteSegment = useDeleteMarketingSegment;
export const usePreviewSegment = usePreviewMarketingSegment;

export const useCampaigns = (channel?: string, status?: string) =>
  useMarketingCampaigns({ channel, status });
export const useCampaign = useMarketingCampaign;
export const useCreateCampaign = useCreateMarketingCampaign;
export const useUpdateCampaign = useUpdateMarketingCampaign;
export const useDeleteCampaign = useDeleteMarketingCampaign;
export const useDispatchCampaign = useDispatchMarketingCampaign;
export const useTestSendCampaign = useTestSendMarketingCampaign;
export const usePauseCampaign = usePauseMarketingCampaign;
export const useResumeCampaign = useResumeMarketingCampaign;

export function useSubmitCampaignForApproval() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, comments }: { id: number; comments?: string }) => {
      const res = await api.submitCampaignForApproval(id, comments);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['marketing-campaigns'] });
    },
  });
}

export function useApproveCampaign() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, comments }: { id: number; comments?: string }) => {
      const res = await api.approveCampaign(id, comments);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['marketing-campaigns'] });
    },
  });
}

export function useRejectCampaign() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, reason }: { id: number; reason: string }) => {
      const res = await api.rejectCampaign(id, reason);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['marketing-campaigns'] });
    },
  });
}

export function useDuplicateCampaign() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.duplicateCampaign(id);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['marketing-campaigns'] });
    },
  });
}

export function useArchiveCampaign() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.archiveCampaign(id);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['marketing-campaigns'] });
    },
  });
}

export function useMarketingOverviewSummary() {
  return useQuery({
    queryKey: ['marketing-summary'],
    queryFn: async () => {
      const res = await api.getCampaignsSummary();
      return res.data;
    },
  });
}

export function useMarketingTemplates(params?: { channel?: string; category?: string }) {
  return useQuery({
    queryKey: ['marketing-templates', params?.channel, params?.category],
    queryFn: async () => {
      const res = await api.getMarketingTemplates(params);
      return res.data || [];
    },
  });
}

export function useCreateMarketingTemplate() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: any) => {
      const res = await api.createMarketingTemplate(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['marketing-templates'] });
    },
  });
}

export function useUpdateMarketingTemplate() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: any }) => {
      const res = await api.updateMarketingTemplate(id, data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['marketing-templates'] });
    },
  });
}

export function useDeleteMarketingTemplate() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      await api.deleteMarketingTemplate(id);
      return id;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['marketing-templates'] });
    },
  });
}

export function usePreviewMarketingTemplate() {
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.previewMarketingTemplate(id);
      return res.data;
    },
  });
}

export function useGenerateMarketingTemplateAi() {
  return useMutation({
    mutationFn: async (params: { channel: string; objective: string; product_name?: string; tone?: string }) => {
      const res = await api.generateMarketingTemplateAi(params);
      return res.data;
    },
  });
}

export function useMarketingConsents(params?: { channel?: string; entity_type?: string }) {
  return useQuery({
    queryKey: ['marketing-consents', params?.channel, params?.entity_type],
    queryFn: async () => {
      const res = await api.getMarketingConsents(params);
      return res.data?.data || res.data || [];
    },
  });
}

export function useRecordMarketingConsent() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: {
      entity_type: string;
      entity_id: number;
      channel: string;
      consent_status: string;
      source?: string;
      reason?: string;
    }) => {
      const res = await api.recordMarketingConsent(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['marketing-consents'] });
    },
  });
}

export function useMarketingSuppressions(search?: string) {
  return useQuery({
    queryKey: ['marketing-suppressions', search],
    queryFn: async () => {
      const res = await api.getMarketingSuppressions(search);
      return res.data?.data || res.data || [];
    },
  });
}

export function useUnsubscribeMarketingRecipient() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ channel, address }: { channel: string; address: string }) => {
      const res = await api.unsubscribeMarketingRecipient(channel, address);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['marketing-suppressions'] });
      queryClient.invalidateQueries({ queryKey: ['marketing-consents'] });
    },
  });
}

