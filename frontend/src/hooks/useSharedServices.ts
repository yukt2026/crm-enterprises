import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useCRMStore } from '../store/crmStore';
import {
  NotificationItem,
  NotificationPreference,
  MediaFileItem,
  CustomFieldDefinition,
  SearchResultItem,
  AuditLogEntry,
} from '../api/types';

// ==========================================
// 1. NOTIFICATIONS HOOKS
// ==========================================

export function useNotifications(unreadOnly = false, page = 1, perPage = 20) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['notifications', workspaceId, unreadOnly, page, perPage],
    queryFn: async () => {
      const res = await api.getNotifications(unreadOnly, page, perPage);
      return res;
    },
    refetchInterval: 30000, // Poll every 30s for background notifications
  });
}

export function useUnreadNotificationCount() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['notifications', 'unread-count', workspaceId],
    queryFn: async () => {
      const res = await api.getUnreadNotificationCount();
      return res.data?.unread_count ?? 0;
    },
    refetchInterval: 15000,
  });
}

export function useMarkNotificationRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string | number) => api.markNotificationRead(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
  });
}

export function useMarkAllNotificationsRead() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: () => api.markAllNotificationsRead(),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      addToast(`Marked ${res.data?.marked_count || 0} notifications as read`, 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to mark notifications read', 'error');
    },
  });
}

export function useNotificationPreferences() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['notification-preferences', workspaceId],
    queryFn: async () => {
      const res = await api.getNotificationPreferences();
      return res.data || [];
    },
  });
}

export function useUpdateNotificationPreferences() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (preferences: Array<{
      notification_type: string;
      in_app_enabled?: boolean;
      email_enabled?: boolean;
      push_enabled?: boolean;
    }>) => api.updateNotificationPreferences(preferences),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notification-preferences'] });
      addToast('Notification preferences saved successfully.', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to update preferences', 'error');
    },
  });
}

// ==========================================
// 2. FILE & MEDIA MANAGER HOOKS
// ==========================================

export function useMediaFiles(params: {
  mime_type?: string;
  fileable_type?: string;
  fileable_id?: string | number;
  page?: number;
  per_page?: number;
} = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['media-files', workspaceId, params],
    queryFn: async () => {
      const res = await api.getMediaFiles(params);
      return res;
    },
  });
}

export function useUploadMediaFile() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (formData: FormData) => api.uploadMediaFile(formData),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['media-files'] });
      addToast(`File "${res.data?.original_name || 'attachment'}" uploaded successfully.`, 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to upload file. Ensure size is <= 25MB.', 'error');
    },
  });
}

export function useDeleteMediaFile() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (uuid: string) => api.deleteMediaFile(uuid),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['media-files'] });
      addToast('File moved to trash / deleted.', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to delete file.', 'error');
    },
  });
}

// ==========================================
// 3. CUSTOM FIELDS HOOKS
// ==========================================

export function useCustomFieldDefinitions(entityType?: string) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['custom-fields', workspaceId, entityType],
    queryFn: async () => {
      const res = await api.getCustomFieldDefinitions(entityType);
      return res.data || [];
    },
  });
}

export function useCreateCustomFieldDefinition() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<CustomFieldDefinition>) => api.createCustomFieldDefinition(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['custom-fields'] });
      addToast('Custom field definition created.', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create custom field.', 'error');
    },
  });
}

export function useDeleteCustomFieldDefinition() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number | string) => api.deleteCustomFieldDefinition(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['custom-fields'] });
      addToast('Custom field definition deleted.', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to delete custom field.', 'error');
    },
  });
}

export function useEntityCustomFields(entityType: string, entityId: number | string | undefined) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['entity-custom-fields', workspaceId, entityType, entityId],
    queryFn: async () => {
      if (!entityId) return { fields: [], values: {} };
      const res = await api.getEntityCustomFieldValues(entityType, entityId);
      return res.data || { fields: [], values: {} };
    },
    enabled: !!entityId,
  });
}

export function useSaveEntityCustomFields(entityType: string, entityId: number | string) {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (fields: Record<string, any>) => api.saveEntityCustomFieldValues(entityType, entityId, fields),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['entity-custom-fields', entityType, entityId] });
      addToast('Custom field values saved.', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to save custom field values.', 'error');
    },
  });
}

// ==========================================
// 4. GLOBAL UNIFIED SEARCH HOOK
// ==========================================

export function useGlobalSearch(query: string, module?: string) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['global-search', workspaceId, query, module],
    queryFn: async () => {
      if (!query || query.trim().length === 0) return [];
      const res = await api.globalSearch(query.trim(), module);
      return res.data || [];
    },
    enabled: query.trim().length >= 2,
    staleTime: 5000,
  });
}

// ==========================================
// 5. AUDIT LOGS & DIFF HOOKS
// ==========================================

export function useAuditLogs(params: {
  action?: string;
  user_id?: string | number;
  date_from?: string;
  date_to?: string;
  page?: number;
  per_page?: number;
} = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['audit-logs', workspaceId, params],
    queryFn: async () => {
      const res = await api.getAuditLogs(params);
      return res;
    },
  });
}

export function useAuditLogDetail(id: number | string | null) {
  return useQuery({
    queryKey: ['audit-log-detail', id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getAuditLog(id);
      return res.data;
    },
    enabled: !!id,
  });
}
