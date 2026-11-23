import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import {
  TenantAiConfigurationItem,
  AiAssistantSessionItem,
  AiProposedActionItem,
  AiSalesInsightItem,
  AiMeetingRecordingItem,
  AiDocumentExtractionItem,
  AiGeneratedContentItem,
  AiUsageReportsData,
} from '../api/types';

export const AI_QUERY_KEYS = {
  providers: ['ai', 'providers'] as const,
  sessions: ['ai', 'sessions'] as const,
  sessionMessages: (id: number) => ['ai', 'sessions', id, 'messages'] as const,
  proposedActions: ['ai', 'proposedActions'] as const,
  salesInsight: (type: string, id: number) => ['ai', 'salesInsight', type, id] as const,
  meetings: ['ai', 'meetings'] as const,
  documents: ['ai', 'documents'] as const,
  usageReports: (start?: string, end?: string) => ['ai', 'usageReports', start, end] as const,
  requestLogs: ['ai', 'requestLogs'] as const,
};

// =========================================================================
// 1. Providers & Settings Hooks
// =========================================================================

export function useAiProviders() {
  return useQuery({
    queryKey: AI_QUERY_KEYS.providers,
    queryFn: async () => {
      const res = await api.getAiProviders();
      return res.data || [];
    },
  });
}

export function useUpdateAiProvider() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: Partial<TenantAiConfigurationItem> & { api_key?: string }) => api.updateAiProvider(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: AI_QUERY_KEYS.providers });
    },
  });
}

export function usePingAiProvider() {
  return useMutation({
    mutationFn: (configId?: number) => api.pingAiProvider(configId),
  });
}

// =========================================================================
// 2. Chat Assistant & Sessions Hooks
// =========================================================================

export function useAiSessions() {
  return useQuery({
    queryKey: AI_QUERY_KEYS.sessions,
    queryFn: async () => {
      const res = await api.getAiSessions();
      return res.data || [];
    },
  });
}

export function useAiSessionMessages(sessionId: number | null) {
  return useQuery({
    queryKey: sessionId ? AI_QUERY_KEYS.sessionMessages(sessionId) : ['ai', 'sessions', 'none'],
    queryFn: async () => {
      if (!sessionId) return null;
      const res = await api.getAiSessionMessages(sessionId);
      return res.data || null;
    },
    enabled: Boolean(sessionId),
  });
}

export function useAskAiAssistant() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (params: { prompt: string; session_id?: number; context_filters?: any }) =>
      api.askAiAssistant(params),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: AI_QUERY_KEYS.sessions });
      if (data?.data?.session_id) {
        queryClient.invalidateQueries({ queryKey: AI_QUERY_KEYS.sessionMessages(data.data.session_id) });
      }
      queryClient.invalidateQueries({ queryKey: AI_QUERY_KEYS.proposedActions });
    },
  });
}

// =========================================================================
// 3. Sales Intelligence Hooks
// =========================================================================

export function useSalesInsight(entityType: string, entityId: number | null) {
  return useQuery({
    queryKey: entityId ? AI_QUERY_KEYS.salesInsight(entityType, entityId) : ['ai', 'salesInsight', 'none'],
    queryFn: async () => {
      if (!entityId) return null;
      const res = await api.getSalesInsight(entityType, entityId);
      return res.data || null;
    },
    enabled: Boolean(entityId),
  });
}

export function useAnalyzeSalesEntity() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (params: { entity_type: 'lead' | 'deal'; entity_id: number }) => api.analyzeSalesEntity(params),
    onSuccess: (data, variables) => {
      queryClient.invalidateQueries({
        queryKey: AI_QUERY_KEYS.salesInsight(variables.entity_type, variables.entity_id),
      });
    },
  });
}

// =========================================================================
// 4. Proposed Action Execution Hooks
// =========================================================================

export function useAiProposedActions() {
  return useQuery({
    queryKey: AI_QUERY_KEYS.proposedActions,
    queryFn: async () => {
      const res = await api.getAiProposedActions();
      return (res.data as any)?.data || res.data || [];
    },
  });
}

export function useConfirmAiAction() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.confirmAiAction(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: AI_QUERY_KEYS.proposedActions });
    },
  });
}

export function useRejectAiAction() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason?: string }) => api.rejectAiAction(id, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: AI_QUERY_KEYS.proposedActions });
    },
  });
}

// =========================================================================
// 5. Meeting Intelligence Hooks
// =========================================================================

export function useAiMeetings() {
  return useQuery({
    queryKey: AI_QUERY_KEYS.meetings,
    queryFn: async () => {
      const res = await api.getAiMeetings();
      return (res.data as any)?.data || res.data || [];
    },
  });
}

export function useProcessAiMeeting() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (formData: FormData) => api.processAiMeeting(formData),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: AI_QUERY_KEYS.meetings });
    },
  });
}

export function useProcessAiMeetingMock() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (params: { title: string; mock_transcript: string; language?: string }) =>
      api.processAiMeetingMock(params),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: AI_QUERY_KEYS.meetings });
    },
  });
}

// =========================================================================
// 6. Document Intelligence Hooks
// =========================================================================

export function useAiDocuments() {
  return useQuery({
    queryKey: AI_QUERY_KEYS.documents,
    queryFn: async () => {
      const res = await api.getAiDocuments();
      return (res.data as any)?.data || res.data || [];
    },
  });
}

export function useUploadAiDocument() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ file, classification }: { file: File; classification?: string }) =>
      api.uploadAiDocument(file, classification),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: AI_QUERY_KEYS.documents });
    },
  });
}

export function useUpdateAiDocumentCorrections() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, corrections }: { id: number; corrections: Record<string, any> }) =>
      api.updateAiDocumentCorrections(id, corrections),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: AI_QUERY_KEYS.documents });
    },
  });
}

export function useCommitAiDocument() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, target }: { id: number; target: { target_entity_type: string; target_entity_id: number } }) =>
      api.commitAiDocument(id, target),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: AI_QUERY_KEYS.documents });
    },
  });
}

// =========================================================================
// 7. Content Generator & Usage Hooks
// =========================================================================

export function useGenerateAiContent() {
  return useMutation({
    mutationFn: (params: {
      content_type: string;
      prompt_or_context: string;
      tone?: string;
      language?: string;
      template_variables?: any;
    }) => api.generateAiContent(params),
  });
}

export function useAiUsageReports(startDate?: string, endDate?: string) {
  return useQuery({
    queryKey: AI_QUERY_KEYS.usageReports(startDate, endDate),
    queryFn: async () => {
      const res = await api.getAiUsageReports({ start_date: startDate, end_date: endDate });
      return res.data;
    },
  });
}

export function useAiRequestLogs() {
  return useQuery({
    queryKey: AI_QUERY_KEYS.requestLogs,
    queryFn: async () => {
      const res = await api.getAiRequestLogs();
      return (res.data as any)?.data || res.data || [];
    },
  });
}
