import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useCRMStore } from '../store/crmStore';
import {
  VoiceAiAgentItem,
  VoiceCallItem,
  VoiceCallingCampaignItem,
  TelephonyProviderItem,
  TelephonyPhoneNumberItem,
  CallIvrFlowItem,
  CallQueueItem,
  CallQualityReviewItem,
  CallerProfileLookupData,
  CallReportingAnalyticsData,
  TelephonyProviderLogItem,
} from '../api/types';

// ==========================================
// 1. VOICE AI AGENTS HOOKS
// ==========================================

export function useVoiceAgents() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['voice-agents', workspaceId],
    queryFn: async () => {
      const res = await api.getVoiceAgents();
      return (res.data || []) as VoiceAiAgentItem[];
    },
  });
}

export function useVoiceAgent(id: number | null | undefined) {
  return useQuery({
    queryKey: ['voice-agent', id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getVoiceAgent(id);
      return res.data as VoiceAiAgentItem;
    },
    enabled: !!id,
  });
}

export function useCreateVoiceAgent() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<VoiceAiAgentItem>) => api.createVoiceAgent(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['voice-agents'] });
      addToast('Voice AI Agent created successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create Voice AI Agent', 'error');
    },
  });
}

export function useUpdateVoiceAgent() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<VoiceAiAgentItem> }) =>
      api.updateVoiceAgent(id, data),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['voice-agents'] });
      queryClient.invalidateQueries({ queryKey: ['voice-agent', vars.id] });
      addToast('Voice AI Agent updated', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to update Voice Agent', 'error');
    },
  });
}

export function useDeleteVoiceAgent() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.deleteVoiceAgent(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['voice-agents'] });
      addToast('Voice Agent removed', 'info');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to delete Voice Agent', 'error');
    },
  });
}

// ==========================================
// 2. VOICE CALLS & DIALER HOOKS
// ==========================================

export function useVoiceCalls(params?: {
  agent_id?: number;
  campaign_id?: number;
  lead_id?: number;
  status?: string;
  direction?: string;
  page?: number;
  per_page?: number;
}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['voice-calls', workspaceId, params],
    queryFn: async () => {
      const res = await api.getVoiceCalls(params);
      return {
        calls: (res.data || []) as VoiceCallItem[],
        stats: res.stats || {
          total_calls: 0,
          total_minutes: 0,
          total_cost: 0,
          qualified_count: 0,
        },
      };
    },
  });
}

export function useVoiceCall(id: number | null | undefined) {
  return useQuery({
    queryKey: ['voice-call', id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getVoiceCall(id);
      return res.data as VoiceCallItem;
    },
    enabled: !!id,
  });
}

export function useInitiateVoiceCall() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: {
      lead_id?: number;
      contact_id?: number;
      to_number: string;
      voice_agent_id?: number;
      campaign_id?: number;
    }) => api.initiateVoiceCall(data),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['voice-calls'] });
      addToast(`Call initiated to ${res.data?.to_number}`, 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to dispatch voice call', 'error');
    },
  });
}

export function useSimulateVoiceCallTurn() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      callId,
      user_speech,
      is_barge_in,
    }: {
      callId: number;
      user_speech: string;
      is_barge_in?: boolean;
    }) =>
      api.simulateVoiceCallTurn(callId, {
        user_speech,
        is_barge_in,
      }),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['voice-calls'] });
      queryClient.invalidateQueries({ queryKey: ['voice-call', vars.callId] });
    },
  });
}

// ==========================================
// 3. VOICE CALLING CAMPAIGNS HOOKS
// ==========================================

export function useVoiceCampaigns() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['voice-campaigns', workspaceId],
    queryFn: async () => {
      const res = await api.getVoiceCampaigns();
      return (res.data || []) as VoiceCallingCampaignItem[];
    },
  });
}

export function useCreateVoiceCampaign() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<VoiceCallingCampaignItem>) => api.createVoiceCampaign(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['voice-campaigns'] });
      addToast('Voice Calling Campaign created', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create campaign', 'error');
    },
  });
}

export function useLaunchVoiceCampaign() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, batchLimit }: { id: number; batchLimit?: number }) =>
      api.launchVoiceCampaign(id, batchLimit),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['voice-campaigns'] });
      queryClient.invalidateQueries({ queryKey: ['voice-calls'] });
      addToast(res.data?.message || 'Campaign batch launched successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to launch campaign', 'error');
    },
  });
}

export function usePauseVoiceCampaign() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.pauseVoiceCampaign(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['voice-campaigns'] });
      addToast('Campaign paused', 'info');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to pause campaign', 'error');
    },
  });
}

export function useDeleteVoiceCampaign() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.deleteVoiceCampaign(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['voice-campaigns'] });
      addToast('Campaign removed', 'info');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to delete campaign', 'error');
    },
  });
}

// ==========================================
// 4. TELEPHONY PROVIDERS & PHONE NUMBERS HOOKS
// ==========================================

export function useTelephonyProviders() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['telephony-providers', workspaceId],
    queryFn: async () => {
      const res = await api.getTelephonyProviders();
      return (res.data || []) as TelephonyProviderItem[];
    },
  });
}

export function useUpdateTelephonyProvider() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<TelephonyProviderItem> & { auth_token?: string }) =>
      api.updateTelephonyProvider(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['telephony-providers'] });
      addToast('Telephony provider configuration saved', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to update provider configuration', 'error');
    },
  });
}

export function useTelephonyPhoneNumbers() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['telephony-phone-numbers', workspaceId],
    queryFn: async () => {
      const res = await api.getTelephonyPhoneNumbers();
      return (res.data || []) as TelephonyPhoneNumberItem[];
    },
  });
}

export function useRegisterTelephonyPhoneNumber() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<TelephonyPhoneNumberItem>) =>
      api.registerTelephonyPhoneNumber(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['telephony-phone-numbers'] });
      addToast('Telephony phone number registered', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to register phone number', 'error');
    },
  });
}

// ==========================================
// 5. CALL CENTRE HUB, SOFTPHONE & IVR HOOKS
// ==========================================

export function useClickToCall() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: {
      to_number: string;
      from_number?: string;
      lead_id?: number;
      contact_id?: number;
      deal_id?: number;
      company_id?: number;
      provider?: string;
    }) => api.clickToCall(data),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['voice-calls'] });
      addToast('Call initiated successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to initiate click-to-call', 'error');
    },
  });
}

export function useCallerLookup() {
  return useMutation({
    mutationFn: (phoneNumber: string) => api.lookupCaller(phoneNumber),
  });
}

export function useSimulateInboundCall() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: {
      from_number: string;
      to_number: string;
      provider?: string;
      provider_call_id?: string;
      idempotency_key?: string;
    }) => api.simulateInboundCall(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['voice-calls'] });
      addToast('Inbound call processed', 'info');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to process inbound call', 'error');
    },
  });
}

export function useUpdateCallDisposition() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ callId, data }: { callId: number; data: any }) =>
      api.updateCallDisposition(callId, data),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['voice-calls'] });
      queryClient.invalidateQueries({ queryKey: ['voice-call', vars.callId] });
      addToast('Call disposition saved & CRM synchronized', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to save disposition', 'error');
    },
  });
}

export function useSubmitCallQualityReview() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ callId, data }: { callId: number; data: any }) =>
      api.submitCallQualityReview(callId, data),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['voice-calls'] });
      queryClient.invalidateQueries({ queryKey: ['voice-call', vars.callId] });
      addToast('Call quality review submitted', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to submit quality review', 'error');
    },
  });
}

export function useTransferVoiceCall() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ callId, data }: { callId: number; data: any }) =>
      api.transferVoiceCall(callId, data),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['voice-calls'] });
      queryClient.invalidateQueries({ queryKey: ['voice-call', vars.callId] });
      addToast('Call transfer initiated', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to transfer call', 'error');
    },
  });
}

export function useVoiceCallReports(params?: { start_date?: string; end_date?: string }) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['voice-call-reports', workspaceId, params?.start_date, params?.end_date],
    queryFn: async () => {
      const res = await api.getVoiceCallReports(params);
      return res.data as CallReportingAnalyticsData;
    },
  });
}

export function useIvrFlows() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['ivr-flows', workspaceId],
    queryFn: async () => {
      const res = await api.getIvrFlows();
      return (res.data || []) as CallIvrFlowItem[];
    },
  });
}

export function useCreateIvrFlow() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<CallIvrFlowItem>) => api.createIvrFlow(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['ivr-flows'] });
      addToast('IVR flow created successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create IVR flow', 'error');
    },
  });
}

export function useUpdateIvrFlow() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<CallIvrFlowItem> }) =>
      api.updateIvrFlow(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['ivr-flows'] });
      addToast('IVR flow updated', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to update IVR flow', 'error');
    },
  });
}

export function useDeleteIvrFlow() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.deleteIvrFlow(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['ivr-flows'] });
      addToast('IVR flow removed', 'info');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to delete IVR flow', 'error');
    },
  });
}

export function useCallQueues() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['call-queues', workspaceId],
    queryFn: async () => {
      const res = await api.getCallQueues();
      return (res.data || []) as CallQueueItem[];
    },
  });
}

export function useCreateCallQueue() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<CallQueueItem>) => api.createCallQueue(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['call-queues'] });
      addToast('Call queue created', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create queue', 'error');
    },
  });
}

export function useUpdateCallQueue() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<CallQueueItem> }) =>
      api.updateCallQueue(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['call-queues'] });
      addToast('Call queue updated', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to update queue', 'error');
    },
  });
}

export function useDeleteCallQueue() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.deleteCallQueue(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['call-queues'] });
      addToast('Call queue removed', 'info');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to delete queue', 'error');
    },
  });
}

export function useTestTelephonyProvider() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.testTelephonyProvider(id),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['telephony-providers'] });
      queryClient.invalidateQueries({ queryKey: ['telephony-provider-logs'] });
      addToast(res.data?.message || 'Provider connection verified', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Provider test connection failed', 'error');
    },
  });
}

export function useTelephonyProviderLogs(params?: { provider?: string; status?: string }) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['telephony-provider-logs', workspaceId, params?.provider, params?.status],
    queryFn: async () => {
      const res = await api.getTelephonyProviderLogs(params);
      return (res.data || []) as TelephonyProviderLogItem[];
    },
  });
}
