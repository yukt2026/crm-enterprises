import React, { useState } from 'react';
import {
  Bell,
  X,
  Check,
  CheckCheck,
  Activity,
  ShieldAlert,
  Clock,
  Sparkles,
  ExternalLink,
  Send,
  Loader2,
  FileText,
  AlertCircle,
  Inbox,
} from 'lucide-react';
import {
  useNotifications,
  useMarkNotificationRead,
  useMarkAllNotificationsRead,
} from '../../hooks/useSharedServices';
import { api } from '../../api/client';
import { useCRMStore } from '../../store/crmStore';

interface NotificationCenterProps {
  isOpen: boolean;
  onClose: () => void;
  theme?: 'light' | 'dark';
}

function timeAgo(dateStr: string): string {
  try {
    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
  } catch {
    return dateStr;
  }
}

export default function NotificationCenter({
  isOpen,
  onClose,
  theme = 'dark',
}: NotificationCenterProps) {
  const [unreadOnly, setUnreadOnly] = useState(false);
  const [isSendingTest, setIsSendingTest] = useState(false);
  const addToast = useCRMStore((s) => s.addToast);

  const { data: response, isLoading, refetch } = useNotifications(unreadOnly, 1, 30);
  const markReadMutation = useMarkNotificationRead();
  const markAllReadMutation = useMarkAllNotificationsRead();

  if (!isOpen) return null;

  const notifications = response?.data || [];
  const unreadCount = response?.meta?.unread_count ?? notifications.filter((n) => !n.read_at).length;

  const handleSendTestNotification = async () => {
    setIsSendingTest(true);
    try {
      await api.dispatchTestNotification('system.announcement');
      addToast('Test system announcement notification created.', 'success');
      refetch();
    } catch (err: any) {
      addToast(err.message || 'Failed to dispatch test notification', 'error');
    } finally {
      setIsSendingTest(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex justify-end">
      <div
        className={`w-full max-w-sm sm:max-w-md h-full p-6 shadow-2xl flex flex-col border-l animate-in slide-in-from-right duration-200 ${
          theme === 'dark' ? 'bg-slate-900 border-slate-800 text-slate-100' : 'bg-white border-slate-200 text-slate-800'
        }`}
      >
        {/* Header */}
        <div className="flex items-center justify-between border-b border-inherit pb-4 mb-4">
          <div className="flex items-center gap-2.5">
            <div className="h-9 w-9 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
              <Bell className="h-4 w-4" />
            </div>
            <div>
              <h2 className="font-bold text-base">Notification Center</h2>
              <p className="text-xs text-slate-400">
                {unreadCount > 0 ? `${unreadCount} unread workspace alerts` : 'All caught up!'}
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1 rounded-lg hover:bg-slate-800 text-slate-400 hover:text-slate-200 transition-colors"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Filter & Action Bar */}
        <div className="flex items-center justify-between gap-2 mb-3">
          <div
            className={`flex items-center p-1 rounded-xl border flex-1 ${
              theme === 'dark' ? 'bg-slate-950/40 border-slate-800' : 'bg-slate-100 border-slate-200'
            }`}
          >
            <button
              onClick={() => setUnreadOnly(false)}
              className={`flex-1 py-1 rounded-lg text-xs font-semibold transition-all ${
                !unreadOnly
                  ? 'bg-emerald-600 text-white shadow-xs'
                  : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              All Alerts
            </button>
            <button
              onClick={() => setUnreadOnly(true)}
              className={`flex-1 py-1 rounded-lg text-xs font-semibold transition-all ${
                unreadOnly
                  ? 'bg-emerald-600 text-white shadow-xs'
                  : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              Unread {unreadCount > 0 ? `(${unreadCount})` : ''}
            </button>
          </div>

          {unreadCount > 0 && (
            <button
              onClick={() => markAllReadMutation.mutate()}
              disabled={markAllReadMutation.isPending}
              className="px-2.5 py-1.5 rounded-xl border border-slate-700 hover:bg-slate-800 text-slate-300 text-xs font-medium flex items-center gap-1.5 transition-colors shrink-0"
              title="Mark all as read"
            >
              <CheckCheck className="h-3.5 w-3.5 text-emerald-400" />
              <span className="hidden sm:inline">Mark read</span>
            </button>
          )}
        </div>

        {/* Notifications List */}
        <div className="flex-1 overflow-y-auto space-y-2.5 pr-1">
          {isLoading ? (
            <div className="py-16 text-center text-xs text-slate-400 flex flex-col items-center gap-2">
              <Loader2 className="h-6 w-6 text-emerald-500 animate-spin" />
              <span>Loading workspace alerts...</span>
            </div>
          ) : notifications.length === 0 ? (
            <div className="py-20 text-center text-xs text-slate-400 flex flex-col items-center gap-2">
              <Inbox className="h-8 w-8 text-slate-600" />
              <span className="font-medium text-slate-300">No notifications found</span>
              <span>Workspace alerts for leads, invoices, and tasks will appear here.</span>
            </div>
          ) : (
            notifications.map((item) => {
              const isUnread = !item.read_at;

              return (
                <div
                  key={item.id}
                  className={`p-3.5 rounded-xl border text-xs transition-all relative ${
                    isUnread
                      ? 'bg-emerald-950/20 border-emerald-500/30'
                      : theme === 'dark'
                      ? 'bg-slate-950/40 border-slate-800/80'
                      : 'bg-white border-slate-200'
                  }`}
                >
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex items-center gap-2">
                      {isUnread && (
                        <span className="h-2 w-2 rounded-full bg-emerald-400 shrink-0" />
                      )}
                      <span className="font-semibold text-slate-100">{item.title}</span>
                    </div>
                    <span className="text-[10px] text-slate-400 flex items-center gap-1 shrink-0 font-mono">
                      <Clock className="h-3 w-3" />
                      {timeAgo(item.created_at)}
                    </span>
                  </div>

                  <p className="text-slate-300 mt-1 text-[11px] leading-relaxed">
                    {item.message}
                  </p>

                  <div className="mt-2.5 flex items-center justify-between border-t border-slate-800/50 pt-2 text-[10px]">
                    <span className="font-mono text-slate-400 uppercase tracking-wider">
                      {item.type || 'SYSTEM'}
                    </span>

                    <div className="flex items-center gap-2">
                      {item.action_url && (
                        <a
                          href={item.action_url}
                          className="text-emerald-400 hover:underline flex items-center gap-1"
                        >
                          View <ExternalLink className="h-2.5 w-2.5" />
                        </a>
                      )}
                      {isUnread && (
                        <button
                          onClick={() => markReadMutation.mutate(item.id)}
                          className="text-slate-400 hover:text-emerald-400 flex items-center gap-1"
                          title="Mark read"
                        >
                          <Check className="h-3 w-3" /> Mark read
                        </button>
                      )}
                    </div>
                  </div>
                </div>
              );
            })
          )}
        </div>

        {/* Footer with Test Notification Trigger */}
        <div className="pt-3 border-t border-inherit flex items-center justify-between text-[11px]">
          <span className="text-slate-400">Push & In-App synced</span>
          <button
            onClick={handleSendTestNotification}
            disabled={isSendingTest}
            className="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs transition-colors"
          >
            {isSendingTest ? (
              <Loader2 className="h-3 w-3 animate-spin text-emerald-400" />
            ) : (
              <Send className="h-3 w-3 text-emerald-400" />
            )}
            <span>Send Test Alert</span>
          </button>
        </div>
      </div>
    </div>
  );
}
