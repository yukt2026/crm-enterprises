import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useCRMStore } from '../store/crmStore';
import {
  WhatsAppAccountItem,
  WhatsAppPhoneNumberItem,
  WhatsAppConversationItem,
  WhatsAppMessageItem,
  WhatsAppConversationNoteItem,
  WhatsAppTemplateItem,
  LeadItem,
} from '../api/types';

// ==========================================
// 1. WABA ACCOUNTS & PHONE NUMBERS HOOKS
// ==========================================

export function useWhatsAppAccounts() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['whatsapp-accounts', workspaceId],
    queryFn: async () => {
      const res = await api.getWhatsAppAccounts();
      return (res.data || []) as WhatsAppAccountItem[];
    },
  });
}

export function useCreateWhatsAppAccount() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: {
      name: string;
      waba_id: string;
      app_id?: string;
      system_user_token: string;
      webhook_verify_token?: string;
    }) => api.createWhatsAppAccount(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-accounts'] });
      addToast('WhatsApp Business Account onboarded', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to connect WABA account', 'error');
    },
  });
}

export function useWhatsAppPhoneNumbers() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['whatsapp-phone-numbers', workspaceId],
    queryFn: async () => {
      const res = await api.getWhatsAppPhoneNumbers();
      return (res.data || []) as WhatsAppPhoneNumberItem[];
    },
  });
}

export function useConnectWhatsAppPhoneNumber() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: {
      whatsapp_account_id: number;
      phone_number_id: string;
      display_phone_number: string;
      verified_name?: string;
      is_default?: boolean;
      default_assigned_user_id?: number;
    }) => api.connectWhatsAppPhoneNumber(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-phone-numbers'] });
      addToast('WhatsApp phone number registered successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to connect phone number', 'error');
    },
  });
}

export function useTestWhatsAppConnection() {
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.testWhatsAppConnection(id),
    onSuccess: (res) => {
      if (res.data?.connected) {
        addToast(`Connection Verified: ${res.data.verified_name || 'Active'} (${res.data.quality_rating})`, 'success');
      } else {
        addToast(res.data?.message || 'Connection test failed', 'error');
      }
    },
    onError: (err: any) => {
      addToast(err.message || 'Diagnostic failed', 'error');
    },
  });
}

// ==========================================
// 2. TEMPLATES HOOKS
// ==========================================

export function useWhatsAppTemplates() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['whatsapp-templates', workspaceId],
    queryFn: async () => {
      const res = await api.getWhatsAppTemplates();
      return (res.data || []) as WhatsAppTemplateItem[];
    },
  });
}

export function useCreateWhatsAppTemplate() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: {
      whatsapp_account_id: number;
      template_name: string;
      category?: string;
      language?: string;
      body_text: string;
      footer_text?: string;
      header_type?: string;
      buttons?: any[];
    }) => api.createWhatsAppTemplate(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-templates'] });
      addToast('Message template registered', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create template', 'error');
    },
  });
}

// ==========================================
// 3. CONVERSATIONS & INBOX HOOKS
// ==========================================

export function useWhatsAppConversations(params: {
  scope?: 'all' | 'assigned_to_me' | 'unassigned';
  status?: string;
  priority?: string;
  search?: string;
  page?: number;
  per_page?: number;
} = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['whatsapp-conversations', workspaceId, params],
    queryFn: async () => {
      const res = await api.getWhatsAppConversations(params);
      return (res.data || []) as WhatsAppConversationItem[];
    },
    refetchInterval: 5000, // Real-time fallback polling
  });
}

export function useWhatsAppConversation(id: number | null) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['whatsapp-conversations', workspaceId, id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getWhatsAppConversation(id);
      return res.data as WhatsAppConversationItem;
    },
    enabled: !!id,
    refetchInterval: 3000, // Near real-time message stream polling
  });
}

export function useAssignWhatsAppConversation() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, assignedUserId }: { id: number; assignedUserId: number | null }) =>
      api.assignWhatsAppConversation(id, assignedUserId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-conversations'] });
      addToast('Conversation assigned', 'info');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to assign conversation', 'error');
    },
  });
}

export function useUpdateWhatsAppConversationStatus() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, status }: { id: number; status: 'open' | 'pending' | 'resolved' }) =>
      api.updateWhatsAppConversationStatus(id, status),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-conversations'] });
      addToast(`Conversation marked as ${variables.status}`, 'info');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to update conversation status', 'error');
    },
  });
}

export function useAddWhatsAppConversationNote() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, content }: { id: number; content: string }) =>
      api.addWhatsAppConversationNote(id, content),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-conversations'] });
      addToast('Team note added', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to add note', 'error');
    },
  });
}

export function useToggleWhatsAppAiHandover() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, isAiHandled }: { id: number; isAiHandled: boolean }) =>
      api.toggleWhatsAppAiHandover(id, isAiHandled),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-conversations'] });
      addToast(variables.isAiHandled ? 'AI Agent activated for conversation' : 'Human agent took over conversation', 'info');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to toggle AI mode', 'error');
    },
  });
}

export function useCreateLeadFromWhatsAppConversation() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: { name?: string; email?: string; company?: string } }) =>
      api.createLeadFromWhatsAppConversation(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-conversations'] });
      queryClient.invalidateQueries({ queryKey: ['leads'] });
      addToast('Lead successfully created from WhatsApp contact', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create lead', 'error');
    },
  });
}

// ==========================================
// 4. MESSAGES DISPATCH HOOKS
// ==========================================

export function useSendWhatsAppTextMessage() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ conversationId, text }: { conversationId: number; text: string }) =>
      api.sendWhatsAppTextMessage(conversationId, text),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-conversations'] });
    },
    onError: (err: any) => {
      addToast(err.message || 'Message dispatch failed', 'error');
    },
  });
}

export function useSendWhatsAppTemplateMessage() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({
      conversationId,
      templateName,
      language = 'en_US',
      parameters = [],
    }: {
      conversationId: number;
      templateName: string;
      language?: string;
      parameters?: any[];
    }) => api.sendWhatsAppTemplateMessage(conversationId, templateName, language, parameters),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-conversations'] });
      addToast('Template sent successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to send template', 'error');
    },
  });
}

export function useSendWhatsAppMediaMessage() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({
      conversationId,
      mediaType,
      mediaUrl,
      caption,
    }: {
      conversationId: number;
      mediaType: 'image' | 'document' | 'audio' | 'video';
      mediaUrl: string;
      caption?: string;
    }) => api.sendWhatsAppMediaMessage(conversationId, mediaType, mediaUrl, caption),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-conversations'] });
      addToast('Media attachment sent', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to send media', 'error');
    },
  });
}

// ==========================================
// 4. MULTI-NUMBER & INTEGRATION LOGS HOOKS
// ==========================================

export function useUpdateWhatsAppPhoneNumber() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<WhatsAppPhoneNumberItem> }) =>
      api.updateWhatsAppPhoneNumber(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-phone-numbers'] });
      addToast('Phone number settings updated', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to update phone number settings', 'error');
    },
  });
}

export function useDisconnectWhatsAppPhoneNumber() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.disconnectWhatsAppPhoneNumber(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-phone-numbers'] });
      addToast('Phone number disconnected', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to disconnect phone number', 'error');
    },
  });
}

export function useWhatsAppIntegrationLogs(page: number = 1, perPage: number = 25) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['whatsapp-integration-logs', workspaceId, page, perPage],
    queryFn: async () => {
      const res = await api.getWhatsAppIntegrationLogs(page, perPage);
      return res.data;
    },
  });
}

// ==========================================
// 5. TEMPLATE SYNC & ANALYTICS HOOKS
// ==========================================

export function useSyncWhatsAppTemplates() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (accountId?: number) => api.syncWhatsAppTemplates(accountId),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-templates'] });
      addToast(`Synchronized ${(res.data as any[])?.length || 0} templates from Meta WABA`, 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to sync templates with Meta WABA', 'error');
    },
  });
}

export function useTestWhatsAppTemplate() {
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: { phone_number: string; parameters?: any[] } }) =>
      api.testWhatsAppTemplate(id, data),
    onSuccess: () => {
      addToast('Test template dispatched successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to dispatch test template', 'error');
    },
  });
}

export function useWhatsAppTemplateAnalytics() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['whatsapp-template-analytics', workspaceId],
    queryFn: async () => {
      const res = await api.getWhatsAppTemplateAnalytics();
      return res.data;
    },
  });
}

// ==========================================
// 6. WHATSAPP BULK CAMPAIGNS HOOKS
// ==========================================

export function useWhatsAppCampaigns(params?: { status?: string; page?: number; per_page?: number }) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['whatsapp-campaigns', workspaceId, params?.status, params?.page, params?.per_page],
    queryFn: async () => {
      const res = await api.getWhatsAppCampaigns(params);
      return res.data;
    },
  });
}

export function useCreateWhatsAppCampaign() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: any) => api.createWhatsAppCampaign(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-campaigns'] });
      addToast('WhatsApp campaign created in draft mode', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create campaign', 'error');
    },
  });
}

export function useWhatsAppCampaign(id: number | null) {
  return useQuery({
    queryKey: ['whatsapp-campaign', id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getWhatsAppCampaign(id);
      return res.data;
    },
    enabled: Boolean(id),
  });
}

export function useSubmitWhatsAppCampaignForApproval() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.submitWhatsAppCampaignForApproval(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-campaigns'] });
      addToast('Campaign submitted for administrative authorization', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to submit campaign for approval', 'error');
    },
  });
}

export function useApproveWhatsAppCampaign() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.approveWhatsAppCampaign(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-campaigns'] });
      addToast('Campaign authorized and queued for dispatch', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to approve campaign', 'error');
    },
  });
}

export function useStartWhatsAppCampaign() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.startWhatsAppCampaign(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-campaigns'] });
      addToast('Campaign launched and queue processing started', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to launch campaign', 'error');
    },
  });
}

export function usePauseWhatsAppCampaign() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.pauseWhatsAppCampaign(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-campaigns'] });
      addToast('Campaign paused', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to pause campaign', 'error');
    },
  });
}

export function useProcessWhatsAppCampaignBatch() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, batchSize }: { id: number; batchSize?: number }) =>
      api.processWhatsAppCampaignBatch(id, batchSize),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-campaigns'] });
      addToast(`Batch executed: ${res.data?.processed_count || 0} messages processed`, 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Batch execution failed', 'error');
    },
  });
}

// ==========================================
// 7. AUTOMATED CUSTOMER JOURNEYS HOOKS
// ==========================================

export function useWhatsAppAutomations() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['whatsapp-automations', workspaceId],
    queryFn: async () => {
      const res = await api.getWhatsAppAutomations();
      return (res.data || []) as any[];
    },
  });
}

export function useCreateWhatsAppAutomation() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: any) => api.createWhatsAppAutomation(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-automations'] });
      addToast('Customer journey automation configured', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create automation journey', 'error');
    },
  });
}

export function useToggleWhatsAppAutomation() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.toggleWhatsAppAutomation(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['whatsapp-automations'] });
      addToast('Journey active status updated', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to toggle automation status', 'error');
    },
  });
}

export function useWhatsAppAutomationLogs(params?: { automation_id?: number; page?: number; per_page?: number }) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['whatsapp-automation-logs', workspaceId, params?.automation_id, params?.page],
    queryFn: async () => {
      const res = await api.getWhatsAppAutomationLogs(params);
      return res.data;
    },
  });
}

// ==========================================
// 8. USAGE & BILLING HOOKS
// ==========================================

export function useWhatsAppUsageSummary(params?: { start_date?: string; end_date?: string }) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['whatsapp-usage-summary', workspaceId, params?.start_date, params?.end_date],
    queryFn: async () => {
      const res = await api.getWhatsAppUsageSummary(params);
      return res.data;
    },
  });
}

export function useWhatsAppUsageRecords(params?: { phone_number_id?: number; category?: string; page?: number; per_page?: number }) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['whatsapp-usage-records', workspaceId, params?.phone_number_id, params?.category, params?.page],
    queryFn: async () => {
      const res = await api.getWhatsAppUsageRecords(params);
      return res.data;
    },
  });
}
