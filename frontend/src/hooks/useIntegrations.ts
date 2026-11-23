import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useCRMStore } from '../store/crmStore';
import {
  IntegrationDirectoryItem,
  IntegrationSyncLogItem,
  TenantApiKeyItem,
  TenantApiRequestLogItem,
  OutboundWebhookItem,
  WebhookDeliveryLogItem,
} from '../api/types';

export function useIntegrationsDirectory() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['integrationsDirectory', workspaceId],
    queryFn: async () => {
      const res = await api.getIntegrations();
      return (res.data || []) as IntegrationDirectoryItem[];
    },
  });
}

export function useConnectCredentials() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async (data: {
      provider: string;
      name?: string;
      credentials: Record<string, any>;
      metadata?: Record<string, any>;
    }) => {
      const res = await api.connectIntegrationCredentials(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['integrationsDirectory', workspaceId] });
    },
  });
}

export function useDisconnectIntegration() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async (id: number) => {
      await api.disconnectIntegration(id);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['integrationsDirectory', workspaceId] });
    },
  });
}

export function useTestIntegration() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.testIntegrationConnection(id);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['integrationsDirectory', workspaceId] });
    },
  });
}

export function useSyncIntegration() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.syncIntegration(id);
      return res.data;
    },
    onSuccess: (_data, id) => {
      queryClient.invalidateQueries({ queryKey: ['integrationsDirectory', workspaceId] });
      queryClient.invalidateQueries({ queryKey: ['integrationSyncLogs', workspaceId, id] });
    },
  });
}

export function useIntegrationSyncLogs(integrationId: number | null | undefined) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['integrationSyncLogs', workspaceId, integrationId],
    queryFn: async () => {
      if (!integrationId) return [];
      const res = await api.getIntegrationSyncLogs(integrationId);
      return (res.data || []) as IntegrationSyncLogItem[];
    },
    enabled: Boolean(integrationId),
  });
}

export function useApiKeys() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['tenantApiKeys', workspaceId],
    queryFn: async () => {
      const res = await api.getApiKeys();
      return (res.data || []) as TenantApiKeyItem[];
    },
  });
}

export function useCreateApiKey() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async (data: {
      name: string;
      scopes?: string[];
      allowed_ips?: string[];
      rate_limit_per_minute?: number;
      expires_at?: string;
    }) => {
      const res = await api.createApiKey(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tenantApiKeys', workspaceId] });
    },
  });
}

export function useRevokeApiKey() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async (id: number) => {
      await api.revokeApiKey(id);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tenantApiKeys', workspaceId] });
    },
  });
}

export function useRotateApiKey() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.rotateApiKey(id);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tenantApiKeys', workspaceId] });
    },
  });
}

export function useApiKeyLogs(apiKeyId?: number) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['apiKeyRequestLogs', workspaceId, apiKeyId],
    queryFn: async () => {
      const res = await api.getApiKeyLogs(apiKeyId);
      return (res.data || []) as TenantApiRequestLogItem[];
    },
  });
}

export function useWebhooks() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['outboundWebhooks', workspaceId],
    queryFn: async () => {
      const res = await api.getWebhooks();
      return (res.data || []) as OutboundWebhookItem[];
    },
  });
}

export function useCreateWebhook() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async (data: {
      name: string;
      url: string;
      events: string[];
      is_active?: boolean;
      retry_limit?: number;
      timeout_seconds?: number;
      headers?: Record<string, string>;
    }) => {
      const res = await api.createWebhook(data);
      return res.data as OutboundWebhookItem;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['outboundWebhooks', workspaceId] });
    },
  });
}

export function useUpdateWebhook() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Partial<OutboundWebhookItem> }) => {
      const res = await api.updateWebhook(id, data);
      return res.data as OutboundWebhookItem;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['outboundWebhooks', workspaceId] });
    },
  });
}

export function useDeleteWebhook() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async (id: number) => {
      await api.deleteWebhook(id);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['outboundWebhooks', workspaceId] });
    },
  });
}

export function useTestPingWebhook() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.testPingWebhook(id);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['webhookDeliveryLogs', workspaceId] });
      queryClient.invalidateQueries({ queryKey: ['outboundWebhooks', workspaceId] });
    },
  });
}

export function useWebhookLogs(webhookId?: number) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['webhookDeliveryLogs', workspaceId, webhookId],
    queryFn: async () => {
      const res = await api.getWebhookLogs(webhookId);
      return (res.data || []) as WebhookDeliveryLogItem[];
    },
  });
}

export function useRedeliverWebhook() {
  const queryClient = useQueryClient();
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useMutation({
    mutationFn: async (deliveryLogId: number) => {
      const res = await api.redeliverWebhook(deliveryLogId);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['webhookDeliveryLogs', workspaceId] });
    },
  });
}

export function useOpenApiSpec() {
  return useQuery({
    queryKey: ['openApiSpecification'],
    queryFn: async () => {
      return await api.getOpenApiSpec();
    },
  });
}
