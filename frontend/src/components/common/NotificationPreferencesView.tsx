import React, { useState, useEffect } from 'react';
import {
  Bell,
  Mail,
  Smartphone,
  Save,
  CheckCircle2,
  Loader2,
  AlertCircle,
  HelpCircle,
} from 'lucide-react';
import {
  useNotificationPreferences,
  useUpdateNotificationPreferences,
} from '../../hooks/useSharedServices';
import { NotificationPreference } from '../../api/types';

interface NotificationPreferencesViewProps {
  theme?: 'light' | 'dark';
}

const EVENT_DEFINITIONS = [
  {
    type: 'lead.assigned',
    title: 'New Lead Assignment',
    desc: 'When a new sales lead is assigned or routed to you.',
    category: 'CRM Sales',
  },
  {
    type: 'lead.reassigned',
    title: 'Lead Reassignment',
    desc: 'When an existing lead ownership transfers to another rep or team.',
    category: 'CRM Sales',
  },
  {
    type: 'lead.followup_due',
    title: 'Follow-up Due / Overdue',
    desc: 'When a scheduled customer follow-up reaches its due timestamp.',
    category: 'CRM Sales',
  },
  {
    type: 'message.new',
    title: 'New Inbound Message',
    desc: 'When an incoming message arrives via WhatsApp, SMS, or live chat.',
    category: 'Communications',
  },
  {
    type: 'call.missed',
    title: 'Missed Call Alert',
    desc: 'When an inbound client call is missed or unattended.',
    category: 'Communications',
  },
  {
    type: 'ai.handover',
    title: 'AI Copilot Handover',
    desc: 'When an autonomous AI agent escalates a conversation to human agents.',
    category: 'AI & Automation',
  },
  {
    type: 'ticket.new',
    title: 'New Support Ticket',
    desc: 'When a customer submits a new helpdesk ticket or escalation.',
    category: 'Support',
  },
  {
    type: 'task.assigned',
    title: 'Task Assignment & Deadlines',
    desc: 'When a task is delegated to you or deadline approaches within 24h.',
    category: 'Productivity',
  },
  {
    type: 'meeting.reminder',
    title: 'Meeting Calendar Reminder',
    desc: '15-minute advance reminder for scheduled customer conferences.',
    category: 'Productivity',
  },
  {
    type: 'hr.leave_status',
    title: 'HR Leave Status Update',
    desc: 'When your leave application is approved or rejected by management.',
    category: 'Human Resources',
  },
  {
    type: 'finance.invoice_event',
    title: 'Invoice Issued & Payment Received',
    desc: 'When a client pays an invoice or an invoice becomes overdue.',
    category: 'Finance',
  },
  {
    type: 'workflow.failure',
    title: 'Automation Workflow Failure',
    desc: 'When a triggered webhook, action, or branch logic encounters errors.',
    category: 'Platform & System',
  },
  {
    type: 'billing.usage_warning',
    title: 'Subscription & Usage Quota Warnings',
    desc: 'When workspace reaches 85% of monthly AI or message credits.',
    category: 'Platform & System',
  },
  {
    type: 'integration.failure',
    title: 'Integration & Webhook Failure',
    desc: 'When third-party API sync (WhatsApp, Twilio, Stripe) fails.',
    category: 'Platform & System',
  },
];

export default function NotificationPreferencesView({ theme = 'dark' }: NotificationPreferencesViewProps) {
  const { data: initialPrefs = [], isLoading } = useNotificationPreferences();
  const updateMutation = useUpdateNotificationPreferences();

  const [prefs, setPrefs] = useState<Record<string, { in_app: boolean; email: boolean; push: boolean }>>({});

  useEffect(() => {
    const map: Record<string, { in_app: boolean; email: boolean; push: boolean }> = {};
    EVENT_DEFINITIONS.forEach((ev) => {
      const match = initialPrefs.find((p) => p.notification_type === ev.type);
      map[ev.type] = {
        in_app: match ? !!match.in_app_enabled : true,
        email: match ? !!match.email_enabled : true,
        push: match ? !!match.push_enabled : false,
      };
    });
    setPrefs(map);
  }, [initialPrefs]);

  const toggleChannel = (type: string, channel: 'in_app' | 'email' | 'push') => {
    setPrefs((prev) => ({
      ...prev,
      [type]: {
        ...prev[type],
        [channel]: !prev[type]?.[channel],
      },
    }));
  };

  const handleSave = () => {
    const payload = Object.entries(prefs).map(([type, channels]) => {
      const c = channels as { in_app: boolean; email: boolean; push: boolean };
      return {
        notification_type: type,
        in_app_enabled: c.in_app,
        email_enabled: c.email,
        push_enabled: c.push,
      };
    });

    updateMutation.mutate(payload);
  };

  if (isLoading) {
    return (
      <div className="py-24 text-center text-xs text-slate-400 flex flex-col items-center gap-2">
        <Loader2 className="h-6 w-6 text-emerald-500 animate-spin" />
        <span>Loading notification preference matrix...</span>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-base font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
            <Bell className="h-5 w-5 text-emerald-500" />
            Configurable Notification Channels
          </h2>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Choose exactly which workspace events trigger in-app bell alerts, direct email notifications, and browser push.
          </p>
        </div>

        <button
          onClick={handleSave}
          disabled={updateMutation.isPending}
          className="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold flex items-center gap-2 shadow-xs transition-colors self-start sm:self-auto"
        >
          {updateMutation.isPending ? (
            <Loader2 className="h-4 w-4 animate-spin" />
          ) : (
            <Save className="h-4 w-4" />
          )}
          <span>Save Preferences</span>
        </button>
      </div>

      {/* Preferences Matrix */}
      <div
        className={`rounded-2xl border overflow-hidden ${
          theme === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
        }`}
      >
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className={`border-b ${theme === 'dark' ? 'bg-slate-950/60 border-slate-800 text-slate-400' : 'bg-slate-50 border-slate-200 text-slate-600'}`}>
              <tr>
                <th className="py-3 px-4 font-semibold">Event Trigger & Description</th>
                <th className="py-3 px-4 font-semibold text-center w-28">
                  <div className="flex items-center justify-center gap-1.5">
                    <Bell className="h-3.5 w-3.5 text-emerald-400" />
                    <span>In-App</span>
                  </div>
                </th>
                <th className="py-3 px-4 font-semibold text-center w-28">
                  <div className="flex items-center justify-center gap-1.5">
                    <Mail className="h-3.5 w-3.5 text-sky-400" />
                    <span>Email</span>
                  </div>
                </th>
                <th className="py-3 px-4 font-semibold text-center w-28">
                  <div className="flex items-center justify-center gap-1.5">
                    <Smartphone className="h-3.5 w-3.5 text-violet-400" />
                    <span>Push</span>
                  </div>
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800/40">
              {EVENT_DEFINITIONS.map((ev) => {
                const setting = prefs[ev.type] || { in_app: true, email: true, push: false };

                return (
                  <tr
                    key={ev.type}
                    className={`transition-colors ${
                      theme === 'dark' ? 'hover:bg-slate-800/40' : 'hover:bg-slate-50'
                    }`}
                  >
                    <td className="py-3.5 px-4">
                      <div className="flex items-center gap-2">
                        <span className="font-semibold text-slate-200">{ev.title}</span>
                        <span className="text-[10px] px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 border border-slate-700">
                          {ev.category}
                        </span>
                      </div>
                      <p className="text-[11px] text-slate-400 mt-0.5">{ev.desc}</p>
                    </td>

                    {/* In-App Toggle */}
                    <td className="py-3.5 px-4 text-center">
                      <input
                        type="checkbox"
                        checked={setting.in_app}
                        onChange={() => toggleChannel(ev.type, 'in_app')}
                        className="h-4 w-4 rounded accent-emerald-500 cursor-pointer"
                      />
                    </td>

                    {/* Email Toggle */}
                    <td className="py-3.5 px-4 text-center">
                      <input
                        type="checkbox"
                        checked={setting.email}
                        onChange={() => toggleChannel(ev.type, 'email')}
                        className="h-4 w-4 rounded accent-emerald-500 cursor-pointer"
                      />
                    </td>

                    {/* Push Toggle */}
                    <td className="py-3.5 px-4 text-center">
                      <input
                        type="checkbox"
                        checked={setting.push}
                        onChange={() => toggleChannel(ev.type, 'push')}
                        className="h-4 w-4 rounded accent-emerald-500 cursor-pointer"
                      />
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
