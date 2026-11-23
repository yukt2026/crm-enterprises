import { useState, useEffect, useCallback } from 'react';
import { api } from '../api/client';
import {
  SocialChannelAccountItem,
  CustomerSocialIdentityItem,
  SocialWebhookEventItem,
  SocialDiagnosticsData,
} from '../api/types';

export function useSocialChannels() {
  const [accounts, setAccounts] = useState<SocialChannelAccountItem[]>([]);
  const [identities, setIdentities] = useState<CustomerSocialIdentityItem[]>([]);
  const [logs, setLogs] = useState<SocialWebhookEventItem[]>([]);
  const [diagnostics, setDiagnostics] = useState<SocialDiagnosticsData | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  const fetchAccounts = useCallback(async (provider?: string) => {
    try {
      const res = await api.getSocialAccounts(provider ? { provider } : undefined);
      if (res.data) setAccounts(res.data);
    } catch (err: any) {
      setError(err?.message || 'Failed to fetch social channel accounts.');
    }
  }, []);

  const fetchIdentities = useCallback(async (provider?: string) => {
    try {
      const res = await api.getSocialIdentities(provider ? { provider } : undefined);
      if (res.data) setIdentities(res.data);
    } catch (err: any) {
      console.warn('Failed to fetch social identities', err);
    }
  }, []);

  const fetchLogs = useCallback(async () => {
    try {
      const res = await api.getSocialLogs({ limit: 50 });
      if (res.data) setLogs(res.data);
    } catch (err: any) {
      console.warn('Failed to fetch social webhook logs', err);
    }
  }, []);

  const fetchDiagnostics = useCallback(async () => {
    try {
      const res = await api.getSocialDiagnostics();
      if (res.data) setDiagnostics(res.data);
    } catch (err: any) {
      console.warn('Failed to fetch social diagnostics', err);
    }
  }, []);

  const refresh = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      await Promise.all([fetchAccounts(), fetchIdentities(), fetchLogs(), fetchDiagnostics()]);
    } finally {
      setLoading(false);
    }
  }, [fetchAccounts, fetchIdentities, fetchLogs, fetchDiagnostics]);

  useEffect(() => {
    refresh();
  }, [refresh]);

  const connectAccount = async (data: {
    provider: 'facebook' | 'instagram';
    page_id: string;
    page_name?: string;
    page_access_token: string;
    instagram_business_id?: string;
    instagram_username?: string;
    permissions?: string[];
    ai_enabled?: boolean;
    ai_agent_id?: number;
    ai_instructions?: string;
  }) => {
    const res = await api.connectSocialAccount(data);
    await refresh();
    return res;
  };

  const disconnectAccount = async (id: number) => {
    const res = await api.disconnectSocialAccount(id);
    await refresh();
    return res;
  };

  const linkIdentity = async (id: number, data: { lead_id?: number; customer_id?: number }) => {
    const res = await api.linkSocialIdentity(id, data);
    await fetchIdentities();
    await fetchDiagnostics();
    return res;
  };

  const mergeIdentities = async (primaryId: number, secondaryId: number) => {
    const res = await api.mergeSocialIdentities({ primary_id: primaryId, secondary_id: secondaryId });
    await fetchIdentities();
    await fetchDiagnostics();
    return res;
  };

  return {
    accounts,
    identities,
    logs,
    diagnostics,
    loading,
    error,
    refresh,
    connectAccount,
    disconnectAccount,
    linkIdentity,
    mergeIdentities,
  };
}
