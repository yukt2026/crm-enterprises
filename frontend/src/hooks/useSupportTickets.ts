import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useCRMStore } from '../store/crmStore';
import {
  SupportTicketItem,
  SupportTicketMessageItem,
  SupportTicketCategoryItem,
  SupportSlaPolicyItem,
  SupportAnalyticsData,
  SupportAiAssistanceData,
} from '../api/types';

// ==========================================
// 1. TICKETS HOOKS
// ==========================================

export function useTickets(params?: {
  status?: string;
  priority?: string;
  source_channel?: string;
  category_id?: number;
  assigned_user_id?: number;
  customer_id?: number;
  sla_status?: string;
  q?: string;
  view?: 'all' | 'my_tickets' | 'open' | 'resolved' | 'closed' | 'breached';
  page?: number;
  per_page?: number;
}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['supportTickets', workspaceId, params],
    queryFn: async () => {
      const res = await api.getTickets(params);
      return (res.data || []) as SupportTicketItem[];
    },
  });
}

export function useTicket(id: number | null | undefined) {
  return useQuery({
    queryKey: ['supportTicket', id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getTicket(id);
      return res.data as SupportTicketItem;
    },
    enabled: Boolean(id),
  });
}

export function useCreateTicket() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: Partial<SupportTicketItem>) => {
      const res = await api.createTicket(data);
      return res.data as SupportTicketItem;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['supportTickets'] });
      queryClient.invalidateQueries({ queryKey: ['supportTicketAnalytics'] });
    },
  });
}

export function useUpdateTicket() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Partial<SupportTicketItem> }) => {
      const res = await api.updateTicket(id, data);
      return res.data as SupportTicketItem;
    },
    onSuccess: (updated) => {
      queryClient.invalidateQueries({ queryKey: ['supportTickets'] });
      queryClient.invalidateQueries({ queryKey: ['supportTicket', updated.id] });
      queryClient.invalidateQueries({ queryKey: ['supportTicketAnalytics'] });
    },
  });
}

export function useDeleteTicket() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.deleteTicket(id);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['supportTickets'] });
      queryClient.invalidateQueries({ queryKey: ['supportTicketAnalytics'] });
    },
  });
}

export function useAssignTicket() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: { assigned_user_id: number; assigned_team_id?: number } }) => {
      const res = await api.assignTicket(id, data);
      return res.data as SupportTicketItem;
    },
    onSuccess: (updated) => {
      queryClient.invalidateQueries({ queryKey: ['supportTickets'] });
      queryClient.invalidateQueries({ queryKey: ['supportTicket', updated.id] });
    },
  });
}

// ==========================================
// 2. MESSAGES & COMMUNICATION HOOKS
// ==========================================

export function useTicketMessages(ticketId: number | null | undefined) {
  return useQuery({
    queryKey: ['supportTicketMessages', ticketId],
    queryFn: async () => {
      if (!ticketId) return [];
      const res = await api.getTicketMessages(ticketId);
      return (res.data || []) as SupportTicketMessageItem[];
    },
    enabled: Boolean(ticketId),
  });
}

export function useSendTicketMessage() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({
      id,
      data,
    }: {
      id: number;
      data: {
        message: string;
        is_internal?: boolean;
        is_draft?: boolean;
        channel?: string;
        attachments?: any[];
        status?: string;
      };
    }) => {
      const res = await api.sendTicketMessage(id, data);
      return res.data as SupportTicketMessageItem;
    },
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['supportTicketMessages', vars.id] });
      queryClient.invalidateQueries({ queryKey: ['supportTicket', vars.id] });
      queryClient.invalidateQueries({ queryKey: ['supportTickets'] });
    },
  });
}

// ==========================================
// 3. WORKFLOW: RESOLVE, CLOSE, REOPEN, CSAT
// ==========================================

export function useResolveTicket() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, resolutionNotes }: { id: number; resolutionNotes: string }) => {
      const res = await api.resolveTicket(id, { resolution_notes: resolutionNotes });
      return res.data as SupportTicketItem;
    },
    onSuccess: (updated) => {
      queryClient.invalidateQueries({ queryKey: ['supportTickets'] });
      queryClient.invalidateQueries({ queryKey: ['supportTicket', updated.id] });
      queryClient.invalidateQueries({ queryKey: ['supportTicketAnalytics'] });
    },
  });
}

export function useCloseTicket() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, closureReason }: { id: number; closureReason?: string }) => {
      const res = await api.closeTicket(id, { closure_reason: closureReason });
      return res.data as SupportTicketItem;
    },
    onSuccess: (updated) => {
      queryClient.invalidateQueries({ queryKey: ['supportTickets'] });
      queryClient.invalidateQueries({ queryKey: ['supportTicket', updated.id] });
      queryClient.invalidateQueries({ queryKey: ['supportTicketAnalytics'] });
    },
  });
}

export function useReopenTicket() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, reopenReason }: { id: number; reopenReason: string }) => {
      const res = await api.reopenTicket(id, { reopen_reason: reopenReason });
      return res.data as SupportTicketItem;
    },
    onSuccess: (updated) => {
      queryClient.invalidateQueries({ queryKey: ['supportTickets'] });
      queryClient.invalidateQueries({ queryKey: ['supportTicket', updated.id] });
      queryClient.invalidateQueries({ queryKey: ['supportTicketAnalytics'] });
    },
  });
}

export function useSubmitTicketCsat() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, rating, comment }: { id: number; rating: number; comment?: string }) => {
      const res = await api.submitTicketCsat(id, { rating, comment });
      return res.data as SupportTicketItem;
    },
    onSuccess: (updated) => {
      queryClient.invalidateQueries({ queryKey: ['supportTickets'] });
      queryClient.invalidateQueries({ queryKey: ['supportTicket', updated.id] });
      queryClient.invalidateQueries({ queryKey: ['supportTicketAnalytics'] });
    },
  });
}

// ==========================================
// 4. AI ASSISTANCE & ANALYTICS HOOKS
// ==========================================

export function useTicketAiAssistance() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, context }: { id: number; context?: string }) => {
      const res = await api.getTicketAiAssistance(id, context);
      return res.data as SupportAiAssistanceData;
    },
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['supportTicket', vars.id] });
    },
  });
}

export function useTicketAnalytics() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['supportTicketAnalytics', workspaceId],
    queryFn: async () => {
      const res = await api.getTicketAnalytics();
      return res.data as SupportAnalyticsData;
    },
  });
}

// ==========================================
// 5. CATEGORIES & SLA POLICIES HOOKS
// ==========================================

export function useTicketCategories() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['supportTicketCategories', workspaceId],
    queryFn: async () => {
      const res = await api.getTicketCategories();
      return (res.data || []) as SupportTicketCategoryItem[];
    },
  });
}

export function useCreateTicketCategory() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: Partial<SupportTicketCategoryItem>) => {
      const res = await api.createTicketCategory(data);
      return res.data as SupportTicketCategoryItem;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['supportTicketCategories'] });
    },
  });
}

export function useTicketSlaPolicies() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['supportTicketSlaPolicies', workspaceId],
    queryFn: async () => {
      const res = await api.getTicketSlaPolicies();
      return (res.data || []) as SupportSlaPolicyItem[];
    },
  });
}

export function useCreateTicketSlaPolicy() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: Partial<SupportSlaPolicyItem>) => {
      const res = await api.createTicketSlaPolicy(data);
      return res.data as SupportSlaPolicyItem;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['supportTicketSlaPolicies'] });
    },
  });
}
