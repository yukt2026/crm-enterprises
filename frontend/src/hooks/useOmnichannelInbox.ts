import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useCRMStore } from '../store/crmStore';
import {
  OmnichannelConversationItem,
  OmnichannelMessageItem,
  ConversationAssignmentLogItem,
  OmnichannelQuickReplyItem,
  CustomerCommunicationConsentItem,
  OmnichannelTimelineItem,
  OmnichannelAnalyticsData,
  EmailAccountItem,
  SmsConfigurationItem,
  WebsiteChatWidgetItem,
  CommunicationSuppressionItem,
  IdentityMergePendingItem,
  UnifiedTimelineItem,
  TelegramAccountItem,
  OmnichannelChannelSettingItem,
  OmnichannelRoutingRuleItem,
  OmnichannelSlaPolicyItem,
  OmnichannelAgentPresenceItem,
  OmnichannelDetailedReportsData,
} from '../api/types';

// ==========================================
// 1. OMNICHANNEL CONVERSATIONS HOOKS
// ==========================================

export function useOmnichannelConversations(params?: {
  channel?: string;
  filter?: string;
  status?: string;
  priority?: string;
  is_ai_handled?: boolean;
  search?: string;
  page?: number;
  per_page?: number;
}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['omnichannel-conversations', workspaceId, params],
    queryFn: async () => {
      const res = await api.getOmnichannelConversations(params);
      return {
        conversations: (res.data || []) as OmnichannelConversationItem[],
        stats: res.stats || {
          all: 0,
          whatsapp: 0,
          email: 0,
          sms: 0,
          call: 0,
          webchat: 0,
          unassigned: 0,
          my: 0,
          resolved: 0,
        },
      };
    },
    refetchInterval: 10000, // Poll every 10s for new inbound messages across all channels
  });
}

export function useOmnichannelConversation(id: number | null | undefined) {
  return useQuery({
    queryKey: ['omnichannel-conversation', id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getOmnichannelConversation(id);
      return res.data as OmnichannelConversationItem;
    },
    enabled: !!id,
    refetchInterval: 5000,
  });
}

export function useSendOmnichannelMessage() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({
      conversationId,
      data,
    }: {
      conversationId: number;
      data: {
        content: string;
        subject?: string;
        to?: string;
        attachments?: any[];
        media_url?: string;
        dlt_template_id?: string;
        tag?: string;
        message_tag?: string;
      };
    }) => api.sendOmnichannelMessage(conversationId, data),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['omnichannel-conversation', vars.conversationId] });
      queryClient.invalidateQueries({ queryKey: ['omnichannel-conversations'] });
      queryClient.invalidateQueries({ queryKey: ['unified-timeline'] });
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to dispatch message', 'error');
    },
  });
}

export function useAssignOmnichannelConversation() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({
      id,
      assigned_user_id,
      assigned_team_id,
    }: {
      id: number;
      assigned_user_id?: number | null;
      assigned_team_id?: number | null;
    }) =>
      api.assignOmnichannelConversation(id, { assigned_user_id, assigned_team_id }),
    onSuccess: (res, vars) => {
      queryClient.invalidateQueries({ queryKey: ['omnichannel-conversation', vars.id] });
      queryClient.invalidateQueries({ queryKey: ['omnichannel-conversations'] });
      addToast('Conversation reassigned', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to assign conversation', 'error');
    },
  });
}

export function useUpdateOmnichannelStatus() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, status }: { id: number; status: 'open' | 'pending' | 'resolved' }) =>
      api.updateOmnichannelStatus(id, status),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['omnichannel-conversation', vars.id] });
      queryClient.invalidateQueries({ queryKey: ['omnichannel-conversations'] });
      addToast(`Status changed to ${vars.status}`, 'info');
    },
  });
}

export function useToggleOmnichannelAi() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, isAiHandled }: { id: number; isAiHandled: boolean }) =>
      api.toggleOmnichannelAi(id, isAiHandled),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['omnichannel-conversation', vars.id] });
      queryClient.invalidateQueries({ queryKey: ['omnichannel-conversations'] });
      addToast(vars.isAiHandled ? 'Returned to Autonomous AI' : 'Human agent takeover active', 'info');
    },
  });
}

export function useMarkOmnichannelRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.markOmnichannelRead(id),
    onSuccess: (_, id) => {
      queryClient.invalidateQueries({ queryKey: ['omnichannel-conversation', id] });
      queryClient.invalidateQueries({ queryKey: ['omnichannel-conversations'] });
    },
  });
}

export function useAddOmnichannelNote() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, content, mentions }: { id: number; content: string; mentions?: number[] }) =>
      api.addOmnichannelNote(id, content, mentions),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['omnichannel-conversation', vars.id] });
      addToast('Internal note saved', 'success');
    },
  });
}

export function useUnifiedTimeline(params: {
  customer_id?: number;
  lead_id?: number;
  contact_id?: number;
}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['unified-timeline', workspaceId, params],
    queryFn: async () => {
      if (!params.customer_id && !params.lead_id && !params.contact_id) return [];
      const res = await api.getUnifiedTimeline(params);
      return (res.data || []) as UnifiedTimelineItem[];
    },
    enabled: !!(params.customer_id || params.lead_id || params.contact_id),
  });
}

// ==========================================
// 2. CHANNEL CONFIGURATIONS HOOKS
// ==========================================

export function useEmailAccounts() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['email-accounts', workspaceId],
    queryFn: async () => {
      const res = await api.getEmailAccounts();
      return (res.data || []) as EmailAccountItem[];
    },
  });
}

export function useSaveEmailAccount() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<EmailAccountItem> & { smtp_password?: string; imap_password?: string }) =>
      api.saveEmailAccount(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['email-accounts'] });
      addToast('Email account configuration saved', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to save email account', 'error');
    },
  });
}

export function useSmsConfigs() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['sms-configs', workspaceId],
    queryFn: async () => {
      const res = await api.getSmsConfigs();
      return (res.data || []) as SmsConfigurationItem[];
    },
  });
}

export function useSaveSmsConfig() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<SmsConfigurationItem> & { auth_token?: string }) =>
      api.saveSmsConfig(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sms-configs'] });
      addToast('SMS configuration saved', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to save SMS provider', 'error');
    },
  });
}

export function useWebChatWidgets() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['webchat-widgets', workspaceId],
    queryFn: async () => {
      const res = await api.getWebChatWidgets();
      return (res.data || []) as WebsiteChatWidgetItem[];
    },
  });
}

export function useSaveWebChatWidget() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<WebsiteChatWidgetItem>) => api.saveWebChatWidget(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['webchat-widgets'] });
      addToast('Website live chat widget saved', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to save widget configuration', 'error');
    },
  });
}

export function useCommunicationSuppressions() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['communication-suppressions', workspaceId],
    queryFn: async () => {
      const res = await api.getCommunicationSuppressions();
      return (res.data || []) as CommunicationSuppressionItem[];
    },
  });
}

export function useAddCommunicationSuppression() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: { channel: string; identifier: string; reason: string }) =>
      api.addCommunicationSuppression(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['communication-suppressions'] });
      addToast('Suppression rule added', 'success');
    },
  });
}

export function useRemoveCommunicationSuppression() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.removeCommunicationSuppression(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['communication-suppressions'] });
      addToast('Suppression entry removed', 'info');
    },
  });
}

export function usePendingIdentityMerges() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['pending-identity-merges', workspaceId],
    queryFn: async () => {
      const res = await api.getPendingIdentityMerges();
      return (res.data || []) as IdentityMergePendingItem[];
    },
  });
}

export function useReviewIdentityMerge() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, decision, notes }: { id: number; decision: 'merged' | 'rejected'; notes?: string }) =>
      api.reviewIdentityMerge(id, { decision, notes }),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['pending-identity-merges'] });
      queryClient.invalidateQueries({ queryKey: ['omnichannel-conversations'] });
      addToast(`Identity match marked ${vars.decision}`, 'success');
    },
  });
}

// ==========================================
// 3. ENTERPRISE OMNICHANNEL HOOKS (PROMPT 29)
// ==========================================

export function useAutoAssignOmnichannelConversation() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, teamId }: { id: number; teamId?: number }) =>
      api.autoAssignOmnichannelConversation(id, teamId),
    onSuccess: (res, vars) => {
      queryClient.invalidateQueries({ queryKey: ['omnichannel-conversation', vars.id] });
      queryClient.invalidateQueries({ queryKey: ['omnichannel-conversations'] });
      queryClient.invalidateQueries({ queryKey: ['omnichannel-assignment-history', vars.id] });
      addToast('Conversation auto-assigned via workload round-robin', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Auto-assignment failed', 'error');
    },
  });
}

export function useOmnichannelAssignmentHistory(conversationId: number | null | undefined) {
  return useQuery({
    queryKey: ['omnichannel-assignment-history', conversationId],
    queryFn: async () => {
      if (!conversationId) return [];
      const res = await api.getOmnichannelAssignmentHistory(conversationId);
      return (res.data || []) as ConversationAssignmentLogItem[];
    },
    enabled: !!conversationId,
  });
}

export function useOmnichannelAnalytics(params?: { from?: string; to?: string }) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['omnichannel-analytics', workspaceId, params],
    queryFn: async () => {
      const res = await api.getOmnichannelAnalytics(params);
      return res.data as OmnichannelAnalyticsData;
    },
  });
}

export function useOmnichannelConsents(params?: { channel?: string; destination?: string }) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['omnichannel-consents', workspaceId, params],
    queryFn: async () => {
      const res = await api.getOmnichannelConsents(params);
      return (res.data || []) as CustomerCommunicationConsentItem[];
    },
  });
}

export function useUpdateOmnichannelConsent() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<CustomerCommunicationConsentItem>) =>
      api.updateOmnichannelConsent(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['omnichannel-consents'] });
      addToast('Customer communication consent updated', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to update consent', 'error');
    },
  });
}

export function useOmnichannelQuickReplies(channel?: string) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['omnichannel-quick-replies', workspaceId, channel],
    queryFn: async () => {
      const res = await api.getOmnichannelQuickReplies(channel);
      return (res.data || []) as OmnichannelQuickReplyItem[];
    },
  });
}

export function useCreateOmnichannelQuickReply() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<OmnichannelQuickReplyItem>) =>
      api.createOmnichannelQuickReply(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['omnichannel-quick-replies'] });
      addToast('Quick reply canned response saved', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to save canned response', 'error');
    },
  });
}

export function useDeleteOmnichannelQuickReply() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.deleteOmnichannelQuickReply(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['omnichannel-quick-replies'] });
      addToast('Quick reply deleted', 'info');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to delete quick reply', 'error');
    },
  });
}

// ==========================================
// 8. OMNICHANNEL HUB & CHANNEL SETTINGS HOOKS
// ==========================================

export function useTelegramAccounts() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['telegram-accounts', workspaceId],
    queryFn: async () => {
      const res = await api.getTelegramAccounts();
      return (res.data || []) as TelegramAccountItem[];
    },
  });
}

export function useSaveTelegramAccount() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<TelegramAccountItem>) => api.saveTelegramAccount(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['telegram-accounts'] });
      queryClient.invalidateQueries({ queryKey: ['omnichannel-channel-health'] });
      addToast('Telegram Bot Account connected', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to save Telegram Account', 'error');
    },
  });
}

export function useDisconnectTelegramAccount() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.disconnectTelegramAccount(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['telegram-accounts'] });
      queryClient.invalidateQueries({ queryKey: ['omnichannel-channel-health'] });
      addToast('Telegram Account disconnected', 'info');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to disconnect Telegram Account', 'error');
    },
  });
}

export function useOmnichannelChannelSettings() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['omnichannel-channel-settings', workspaceId],
    queryFn: async () => {
      const res = await api.getOmnichannelChannelSettings();
      return (res.data || []) as OmnichannelChannelSettingItem[];
    },
  });
}

export function useSaveOmnichannelChannelSettings() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<OmnichannelChannelSettingItem>) =>
      api.saveOmnichannelChannelSettings(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['omnichannel-channel-settings'] });
      queryClient.invalidateQueries({ queryKey: ['omnichannel-channel-health'] });
      addToast('Channel settings & business hours saved', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to save channel settings', 'error');
    },
  });
}

export function useTestOmnichannelConnection() {
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: { channel: string; channel_account_id?: string }) =>
      api.testOmnichannelConnection(data),
    onSuccess: (res) => {
      if (res?.data?.status === 'connected') {
        addToast(`${res.data.channel.toUpperCase()} Connection Successful!`, 'success');
      } else {
        addToast(res?.data?.message || 'Connection test returned a warning', 'info');
      }
    },
    onError: (err: any) => {
      addToast(err.message || 'Connection test failed', 'error');
    },
  });
}

export function useOmnichannelChannelHealth() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['omnichannel-channel-health', workspaceId],
    queryFn: async () => {
      const res = await api.getOmnichannelChannelHealth();
      return res.data || {};
    },
    refetchInterval: 30000,
  });
}

// ==========================================
// 9. LOCK, ARCHIVE & COLLISION HOOKS
// ==========================================

export function useLockOmnichannelConversation() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.lockOmnichannelConversation(id),
    onSuccess: (_, id) => {
      queryClient.invalidateQueries({ queryKey: ['omnichannel-conversation', id] });
      queryClient.invalidateQueries({ queryKey: ['omnichannel-conversations'] });
      addToast('Conversation locked for your exclusive reply', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Could not lock conversation', 'error');
    },
  });
}

export function useUnlockOmnichannelConversation() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.unlockOmnichannelConversation(id),
    onSuccess: (_, id) => {
      queryClient.invalidateQueries({ queryKey: ['omnichannel-conversation', id] });
      queryClient.invalidateQueries({ queryKey: ['omnichannel-conversations'] });
      addToast('Conversation unlocked', 'info');
    },
    onError: (err: any) => {
      addToast(err.message || 'Could not unlock conversation', 'error');
    },
  });
}

export function useArchiveOmnichannelConversation() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, archive }: { id: number; archive: boolean }) =>
      api.archiveOmnichannelConversation(id, archive),
    onSuccess: (res, vars) => {
      queryClient.invalidateQueries({ queryKey: ['omnichannel-conversation', vars.id] });
      queryClient.invalidateQueries({ queryKey: ['omnichannel-conversations'] });
      addToast(vars.archive ? 'Conversation archived' : 'Conversation unarchived', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to archive conversation', 'error');
    },
  });
}

export function useOmnichannelCollision(conversationId: number | null | undefined) {
  return useQuery({
    queryKey: ['omnichannel-collision', conversationId],
    queryFn: async () => {
      if (!conversationId) return { collision_detected: false, active_agents: [] };
      const res = await api.checkOmnichannelCollision(conversationId);
      return res.data || { collision_detected: false, active_agents: [] };
    },
    enabled: !!conversationId,
    refetchInterval: 5000,
  });
}

// ==========================================
// 10. ROUTING RULES & SLA HOOKS
// ==========================================

export function useOmnichannelRoutingRules() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['omnichannel-routing-rules', workspaceId],
    queryFn: async () => {
      const res = await api.getOmnichannelRoutingRules();
      return (res.data || []) as OmnichannelRoutingRuleItem[];
    },
  });
}

export function useSaveOmnichannelRoutingRule() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<OmnichannelRoutingRuleItem>) =>
      api.saveOmnichannelRoutingRule(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['omnichannel-routing-rules'] });
      addToast('Routing rule saved', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to save routing rule', 'error');
    },
  });
}

export function useDeleteOmnichannelRoutingRule() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.deleteOmnichannelRoutingRule(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['omnichannel-routing-rules'] });
      addToast('Routing rule removed', 'info');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to delete routing rule', 'error');
    },
  });
}

export function useOmnichannelSlaPolicies() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['omnichannel-sla-policies', workspaceId],
    queryFn: async () => {
      const res = await api.getOmnichannelSlaPolicies();
      return (res.data || []) as OmnichannelSlaPolicyItem[];
    },
  });
}

export function useSaveOmnichannelSlaPolicy() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<OmnichannelSlaPolicyItem>) =>
      api.saveOmnichannelSlaPolicy(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['omnichannel-sla-policies'] });
      addToast('SLA policy saved', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to save SLA policy', 'error');
    },
  });
}

export function useDeleteOmnichannelSlaPolicy() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.deleteOmnichannelSlaPolicy(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['omnichannel-sla-policies'] });
      addToast('SLA policy deleted', 'info');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to delete SLA policy', 'error');
    },
  });
}

export function useRecalculateOmnichannelSla() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: () => api.recalculateOmnichannelSla(),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['omnichannel-conversations'] });
      queryClient.invalidateQueries({ queryKey: ['omnichannel-conversation'] });
      addToast(
        `SLA Recalculated: ${res.data?.summary?.breached ?? 0} breached, ${res.data?.summary?.warning ?? 0} warnings`,
        'success'
      );
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to recalculate SLAs', 'error');
    },
  });
}

export function useOmnichannelDetailedReports() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['omnichannel-detailed-reports', workspaceId],
    queryFn: async () => {
      const res = await api.getOmnichannelDetailedReports();
      return res.data as OmnichannelDetailedReportsData;
    },
    refetchInterval: 30000,
  });
}
