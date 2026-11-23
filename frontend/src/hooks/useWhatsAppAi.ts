import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import {
  WhatsAppAiAgentItem,
  WhatsAppKnowledgeBaseItem,
  WhatsAppKnowledgeChunkItem,
  WhatsAppAiProviderItem,
  WhatsAppAiExecutionLogItem,
} from '../api/types';

export function useWhatsAppAiAgents() {
  return useQuery({
    queryKey: ['whatsapp-ai-agents'],
    queryFn: async () => {
      const res = await api.getWhatsAppAiAgents();
      return res.data;
    },
    staleTime: 30000,
  });
}

export function useWhatsAppAiAgent(id?: number) {
  return useQuery({
    queryKey: ['whatsapp-ai-agent', id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getWhatsAppAiAgent(id);
      return res.data;
    },
    enabled: !!id,
  });
}

export function useCreateWhatsAppAiAgent() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: Partial<WhatsAppAiAgentItem>) => api.createWhatsAppAiAgent(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-ai-agents'] });
    },
  });
}

export function useUpdateWhatsAppAiAgent() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<WhatsAppAiAgentItem> & { change_summary?: string } }) =>
      api.updateWhatsAppAiAgent(id, data),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-ai-agents'] });
      queryClient.invalidateQueries({ queryKey: ['whatsapp-ai-agent', vars.id] });
    },
  });
}

export function useDeleteWhatsAppAiAgent() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.deleteWhatsAppAiAgent(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-ai-agents'] });
    },
  });
}

export function useTestWhatsAppAiPlayground() {
  return useMutation({
    mutationFn: ({ id, message, history }: { id: number; message: string; history?: any[] }) =>
      api.testWhatsAppAiPlayground(id, message, history),
  });
}

export function useWhatsAppKnowledgeBases() {
  return useQuery({
    queryKey: ['whatsapp-knowledge-bases'],
    queryFn: async () => {
      const res = await api.getWhatsAppKnowledgeBases();
      return res.data;
    },
    staleTime: 30000,
  });
}

export function useUploadWhatsAppKnowledge() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (formData: FormData) => api.uploadWhatsAppKnowledge(formData),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-knowledge-bases'] });
    },
  });
}

export function useIngestWhatsAppKnowledgeText() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: { title: string; category: string; description?: string; content: string }) =>
      api.ingestWhatsAppKnowledgeText(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-knowledge-bases'] });
    },
  });
}

export function useSearchWhatsAppKnowledge() {
  return useMutation({
    mutationFn: ({ query, topK }: { query: string; topK?: number }) =>
      api.searchWhatsAppKnowledge(query, topK),
  });
}

export function useDeleteWhatsAppKnowledgeBase() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.deleteWhatsAppKnowledgeBase(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-knowledge-bases'] });
    },
  });
}

export function useWhatsAppAiProviders() {
  return useQuery({
    queryKey: ['whatsapp-ai-providers'],
    queryFn: async () => {
      const res = await api.getWhatsAppAiProviders();
      return res.data;
    },
    staleTime: 60000,
  });
}

export function useUpdateWhatsAppAiProvider() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: Partial<WhatsAppAiProviderItem> & { api_key?: string }) =>
      api.updateWhatsAppAiProvider(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-ai-providers'] });
    },
  });
}

export function useWhatsAppAiLogs(params?: { agent_id?: number; status?: string; page?: number; per_page?: number }) {
  return useQuery({
    queryKey: ['whatsapp-ai-logs', params],
    queryFn: async () => {
      const res = await api.getWhatsAppAiLogs(params);
      return res;
    },
    staleTime: 15000,
  });
}
