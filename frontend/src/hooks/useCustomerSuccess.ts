import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import {
  CustomerHealthScoreItem,
  WarrantyContractItem,
  CustomerFeedbackItem,
  KnowledgeCategoryItem,
  KnowledgeArticleItem,
  SupportTicketTransitionItem,
} from '../api/types';

export const CS_KEYS = {
  healthScores: (params: any) => ['customerSuccess', 'healthScores', params] as const,
  healthScore: (id: number) => ['customerSuccess', 'healthScore', id] as const,
  contracts: (params: any) => ['customerSuccess', 'contracts', params] as const,
  feedback: (params: any) => ['customerSuccess', 'feedback', params] as const,
  kbCategories: ['knowledgeBase', 'categories'] as const,
  kbArticles: (params: any) => ['knowledgeBase', 'articles', params] as const,
  ticketTransitions: (id: number) => ['support', 'transitions', id] as const,
};

export function useCustomerHealthScores(params: any = {}) {
  return useQuery({
    queryKey: CS_KEYS.healthScores(params),
    queryFn: async () => {
      const res = await api.getCustomerHealthScores(params);
      return res.data;
    },
  });
}

export function useCustomerHealthScore(customerId: number) {
  return useQuery({
    queryKey: CS_KEYS.healthScore(customerId),
    queryFn: async () => {
      const res = await api.getCustomerHealthScore(customerId);
      return res.data;
    },
    enabled: !!customerId,
  });
}

export function useRecordCustomerCheckIn() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ customerId, data }: { customerId: number; data: any }) =>
      api.recordCustomerCheckIn(customerId, data),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: CS_KEYS.healthScore(vars.customerId) });
      queryClient.invalidateQueries({ queryKey: ['customerSuccess', 'healthScores'] });
    },
  });
}

export function useWarrantyContracts(params: any = {}) {
  return useQuery({
    queryKey: CS_KEYS.contracts(params),
    queryFn: async () => {
      const res = await api.getWarrantyContracts(params);
      return res.data;
    },
  });
}

export function useCreateWarrantyContract() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: Partial<WarrantyContractItem>) => api.createWarrantyContract(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['customerSuccess', 'contracts'] });
    },
  });
}

export function useRecordContractMaintenance() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: any }) => api.recordContractMaintenance(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['customerSuccess', 'contracts'] });
    },
  });
}

export function useCustomerFeedback(params: any = {}) {
  return useQuery({
    queryKey: CS_KEYS.feedback(params),
    queryFn: async () => {
      const res = await api.getCustomerFeedback(params);
      return res.data;
    },
  });
}

export function useSubmitCustomerFeedback() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: Partial<CustomerFeedbackItem>) => api.submitCustomerFeedback(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['customerSuccess', 'feedback'] });
      queryClient.invalidateQueries({ queryKey: ['customerSuccess', 'healthScores'] });
    },
  });
}

export function useKnowledgeCategories() {
  return useQuery({
    queryKey: CS_KEYS.kbCategories,
    queryFn: async () => {
      const res = await api.getKnowledgeCategories();
      return res.data;
    },
  });
}

export function useKnowledgeArticles(params: any = {}) {
  return useQuery({
    queryKey: CS_KEYS.kbArticles(params),
    queryFn: async () => {
      const res = await api.getKnowledgeArticles(params);
      return res.data;
    },
  });
}

export function useCreateKnowledgeArticle() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: Partial<KnowledgeArticleItem>) => api.createKnowledgeArticle(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['knowledgeBase', 'articles'] });
    },
  });
}

export function useVoteKnowledgeArticle() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, helpful }: { id: number; helpful: boolean }) =>
      api.voteKnowledgeArticle(id, helpful),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['knowledgeBase', 'articles'] });
    },
  });
}

export function useSuggestAiKnowledgeAnswer() {
  return useMutation({
    mutationFn: (query: string) => api.suggestAiKnowledgeAnswer(query),
  });
}

export function useTicketTransitions(ticketId: number) {
  return useQuery({
    queryKey: CS_KEYS.ticketTransitions(ticketId),
    queryFn: async () => {
      const res = await api.getTicketTransitions(ticketId);
      return res.data;
    },
    enabled: !!ticketId,
  });
}

export function useBulkUpdateTickets() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: { ticket_ids: number[]; status?: string; assigned_user_id?: number; priority?: string }) =>
      api.bulkUpdateTickets(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tickets'] });
    },
  });
}
