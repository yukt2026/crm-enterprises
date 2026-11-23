import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useCRMStore } from '../store/crmStore';

export function useWorkspaces() {
  return useQuery({
    queryKey: ['workspaces', 'list'],
    queryFn: async () => {
      const res = await api.getWorkspaces();
      return res.data || [];
    },
  });
}

export function useCurrentWorkspace() {
  return useQuery({
    queryKey: ['workspaces', 'current'],
    queryFn: async () => {
      const res = await api.getCurrentWorkspace();
      return res.data;
    },
  });
}

export function useWorkspaceMembers() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['workspaces', 'members', workspaceId],
    queryFn: async () => {
      const res = await api.getWorkspaceMembers();
      return res.data || [];
    },
  });
}

export function useLoginHistory() {
  return useQuery({
    queryKey: ['auth', 'login-history'],
    queryFn: async () => {
      const res = await api.getLoginHistory();
      return res.data || [];
    },
  });
}

export function useSwitchWorkspace() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);
  const switchWorkspaceStore = useCRMStore((s) => s.switchWorkspace);

  return useMutation({
    mutationFn: async (workspaceId: string | number) => {
      return switchWorkspaceStore(String(workspaceId));
    },
    onSuccess: () => {
      queryClient.invalidateQueries();
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to switch workspace', 'error');
    },
  });
}

export function useCreateWorkspace() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);
  const createWorkspaceStore = useCRMStore((s) => s.createWorkspace);

  return useMutation({
    mutationFn: async ({ name, tier }: { name: string; tier?: string }) => {
      return createWorkspaceStore(name, tier);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['workspaces'] });
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create workspace', 'error');
    },
  });
}

export function useInviteMember() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: async ({ email, role }: { email: string; role: string }) => {
      return api.inviteMember(email, role);
    },
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['workspaces', 'members'] });
      addToast(`Invitation generated for ${vars.email}`, 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to send invitation', 'error');
    },
  });
}
