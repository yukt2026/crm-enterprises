import React, { useState } from 'react';
import {
  Zap,
  Plus,
  Play,
  Clock,
  CheckCircle2,
  AlertCircle,
  FileText,
  ToggleLeft,
  ToggleRight,
  Send,
  History,
  X,
  RefreshCw,
} from 'lucide-react';
import {
  useWhatsAppAutomations,
  useCreateWhatsAppAutomation,
  useToggleWhatsAppAutomation,
  useWhatsAppAutomationLogs,
  useWhatsAppPhoneNumbers,
  useWhatsAppTemplates,
} from '../../hooks/useWhatsApp';
import { api } from '../../api/client';
import { useCRMStore } from '../../store/crmStore';

interface Props {
  isDark: boolean;
}

const JOURNEY_PRESETS = [
  { type: 'new_enquiry_welcome', label: 'New Enquiry Welcome', event: 'lead_created', desc: 'Instant welcome greeting when a new enquiry is captured.' },
  { type: 'lead_qualification', label: 'Lead Qualification', event: 'lead_created', desc: 'Interactive qualifying questions dispatched to inbound leads.' },
  { type: 'appointment_confirmation', label: 'Appointment Confirmation', event: 'appointment_booked', desc: 'Automated date, time, and link confirmation for demo bookings.' },
  { type: 'payment_reminder', label: 'Payment Reminder', event: 'invoice_due', desc: 'Gentle reminder sent 3 days before payment due date.' },
  { type: 'invoice_reminder', label: 'Urgent Invoice Overdue', event: 'invoice_overdue', desc: 'Escalation notice with payment gateway link for overdue invoices.' },
  { type: 'renewal_reminder', label: 'Contract Renewal', event: 'subscription_expiring', desc: 'Dispatched 15 days before contract or subscription expiration.' },
  { type: 'customer_onboarding', label: 'Customer Onboarding', event: 'deal_won', desc: 'Step-by-step setup instructions upon closing a deal.' },
  { type: 'service_update', label: 'Service & Maintenance Update', event: 'system_maintenance', desc: 'Broadcast service announcements or scheduled maintenance.' },
  { type: 'sales_followup', label: 'Sales Follow-up', event: 'proposal_sent', desc: 'Automated follow-up dispatched 48 hours after proposal.' },
  { type: 'abandoned_enquiry', label: 'Abandoned Enquiry Re-engagement', event: 'abandoned_form', desc: 'Re-engage users who abandoned lead forms without completing.' },
];

export default function WhatsAppAutomationsTab({ isDark }: Props) {
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [selectedAutomationId, setSelectedAutomationId] = useState<number | null>(null);
  const [isLogsModalOpen, setIsLogsModalOpen] = useState(false);
  const [testPhone, setTestPhone] = useState('');
  const [testEvent, setTestEvent] = useState('lead_created');

  const addToast = useCRMStore((s) => s.addToast);
  const { data: automations = [], isLoading, refetch } = useWhatsAppAutomations();
  const { data: phoneNumbers = [] } = useWhatsAppPhoneNumbers();
  const { data: templates = [] } = useWhatsAppTemplates();

  const createMutation = useCreateWhatsAppAutomation();
  const toggleMutation = useToggleWhatsAppAutomation();
  const { data: logsData } = useWhatsAppAutomationLogs({ automation_id: selectedAutomationId || undefined });

  // Form State
  const [name, setName] = useState('');
  const [journeyType, setJourneyType] = useState('new_enquiry_welcome');
  const [triggerEvent, setTriggerEvent] = useState('lead_created');
  const [phoneId, setPhoneId] = useState<number | ''>('');
  const [templateId, setTemplateId] = useState<number | ''>('');
  const [delayMinutes, setDelayMinutes] = useState(0);
  const [stopCondition, setStopCondition] = useState('customer_replied');

  const handleSelectPreset = (preset: typeof JOURNEY_PRESETS[0]) => {
    setJourneyType(preset.type);
    setTriggerEvent(preset.event);
    setName(preset.label + ' Workflow');
  };

  const handleCreateAutomation = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name || !phoneId || !templateId) return;

    await createMutation.mutateAsync({
      name,
      journey_type: journeyType as any,
      trigger_event: triggerEvent,
      whatsapp_phone_number_id: Number(phoneId),
      whatsapp_template_id: Number(templateId),
      delay_minutes: Number(delayMinutes),
      stop_condition: stopCondition,
      is_active: true,
    });

    setIsCreateModalOpen(false);
    setName('');
  };

  const handleTriggerTest = async () => {
    if (!testPhone) {
      addToast('Please enter a test phone number', 'error');
      return;
    }

    try {
      await api.testWhatsAppAutomationTrigger({
        event: testEvent,
        phone_number: testPhone,
        payload: { lead_name: 'Test Customer', company_name: 'Enterprise Inc' },
      });
      addToast('Automation pipeline triggered successfully', 'success');
      refetch();
    } catch (err: any) {
      addToast(err.message || 'Trigger test failed', 'error');
    }
  };

  return (
    <div className="flex-1 flex flex-col overflow-hidden p-6 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold flex items-center gap-2">
            <Zap className="w-5 h-5 text-amber-500" />
            Automated Customer Journeys
          </h2>
          <p className="text-xs text-slate-500 dark:text-slate-400">
            Configure event-triggered multi-step WhatsApp communication workflows with automatic opt-out & reply suppression.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() => refetch()}
            className="p-2 rounded-lg border dark:border-slate-800 text-slate-500 hover:text-slate-900 dark:hover:text-white transition"
          >
            <RefreshCw className="w-4 h-4" />
          </button>
          <button
            onClick={() => setIsCreateModalOpen(true)}
            className="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-semibold shadow-xs transition"
          >
            <Plus className="w-4 h-4" />
            New Customer Journey
          </button>
        </div>
      </div>

      {/* Test Trigger Simulator Box */}
      <div className="p-4 rounded-xl border dark:border-slate-800 bg-slate-50 dark:bg-slate-900/40 flex flex-wrap items-center justify-between gap-3 text-xs">
        <div className="flex items-center gap-2">
          <span className="font-semibold text-slate-700 dark:text-slate-300">Live Journey Simulator:</span>
          <select
            value={testEvent}
            onChange={(e) => setTestEvent(e.target.value)}
            className="p-1.5 rounded-lg border dark:border-slate-700 bg-transparent text-xs"
          >
            <option value="lead_created">lead_created (New Enquiry)</option>
            <option value="appointment_booked">appointment_booked</option>
            <option value="invoice_due">invoice_due (Payment Reminder)</option>
            <option value="deal_won">deal_won (Onboarding)</option>
          </select>
          <input
            type="text"
            placeholder="+91 98765 43210"
            value={testPhone}
            onChange={(e) => setTestPhone(e.target.value)}
            className="p-1.5 rounded-lg border dark:border-slate-700 bg-transparent text-xs w-44"
          />
        </div>
        <button
          onClick={handleTriggerTest}
          className="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-semibold text-xs transition"
        >
          <Send className="w-3.5 h-3.5" /> Simulate Event Trigger
        </button>
      </div>

      {/* Journeys Grid */}
      <div className="flex-1 overflow-y-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {isLoading ? (
          <div className="col-span-full p-12 text-center text-xs text-slate-500">Loading automations...</div>
        ) : automations.length === 0 ? (
          <div className="col-span-full p-12 text-center text-slate-500 space-y-3">
            <Zap className="w-10 h-10 mx-auto text-amber-500/40" />
            <div className="text-sm font-semibold text-slate-700 dark:text-slate-300">No active journeys</div>
            <p className="text-xs">Create an automated welcome, payment reminder, or lead qualification workflow.</p>
          </div>
        ) : (
          automations.map((a: any) => (
            <div
              key={a.id}
              className={`p-4 rounded-xl border flex flex-col justify-between transition ${
                a.is_active
                  ? isDark
                    ? 'bg-slate-900/60 border-slate-800'
                    : 'bg-white border-slate-200'
                  : 'bg-slate-100/50 dark:bg-slate-900/20 border-dashed border-slate-300 dark:border-slate-800 opacity-70'
              }`}
            >
              <div className="space-y-2.5">
                <div className="flex items-center justify-between">
                  <span className="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-500">
                    {a.journey_type?.replace(/_/g, ' ')}
                  </span>
                  <button
                    onClick={() => toggleMutation.mutate(a.id)}
                    className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                    title={a.is_active ? 'Deactivate' : 'Activate'}
                  >
                    {a.is_active ? (
                      <ToggleRight className="w-6 h-6 text-emerald-500" />
                    ) : (
                      <ToggleLeft className="w-6 h-6 text-slate-400" />
                    )}
                  </button>
                </div>

                <h3 className="text-sm font-bold text-slate-900 dark:text-white">{a.name}</h3>

                <div className="space-y-1 text-[11px] text-slate-500">
                  <div className="flex justify-between">
                    <span>Trigger:</span>
                    <span className="font-mono font-medium text-slate-700 dark:text-slate-300">{a.trigger_event}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Template:</span>
                    <span className="font-mono font-medium text-emerald-600 dark:text-emerald-400">
                      {a.template?.template_name || 'N/A'}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span>Delay:</span>
                    <span>{a.delay_minutes ? `${a.delay_minutes} min` : 'Instant (0m)'}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Stop Rule:</span>
                    <span className="capitalize">{a.stop_condition?.replace('_', ' ') || 'Customer replied'}</span>
                  </div>
                </div>
              </div>

              <div className="pt-3 mt-3 border-t dark:border-slate-800 flex items-center justify-between text-xs">
                <span className="text-[10px] text-slate-400">
                  Executed <strong className="text-slate-700 dark:text-slate-200">{a.execution_count || 0}</strong> times
                </span>
                <button
                  onClick={() => {
                    setSelectedAutomationId(a.id);
                    setIsLogsModalOpen(true);
                  }}
                  className="flex items-center gap-1 text-[11px] font-semibold text-emerald-500 hover:underline"
                >
                  <History className="w-3.5 h-3.5" /> Logs
                </button>
              </div>
            </div>
          ))
        )}
      </div>

      {/* Create Automation Modal */}
      {isCreateModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
          <div className="w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white dark:bg-slate-900 border dark:border-slate-800 p-6 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between border-b dark:border-slate-800 pb-3">
              <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <Zap className="w-4 h-4 text-amber-500" />
                Configure Automated Customer Journey
              </h3>
              <button
                onClick={() => setIsCreateModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
              >
                ✕
              </button>
            </div>

            {/* Presets Selection */}
            <div>
              <label className="block text-xs font-semibold text-slate-500 mb-1.5">Choose Journey Preset</label>
              <div className="grid grid-cols-2 gap-2 text-[11px]">
                {JOURNEY_PRESETS.slice(0, 6).map((preset) => (
                  <button
                    key={preset.type}
                    type="button"
                    onClick={() => handleSelectPreset(preset)}
                    className={`p-2 rounded-lg border text-left transition ${
                      journeyType === preset.type
                        ? 'border-emerald-500 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold'
                        : 'border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/40 text-slate-600 dark:text-slate-400'
                    }`}
                  >
                    <div>{preset.label}</div>
                  </button>
                ))}
              </div>
            </div>

            <form onSubmit={handleCreateAutomation} className="space-y-3.5 text-xs">
              <div>
                <label className="block text-slate-500 mb-1">Journey Name</label>
                <input
                  type="text"
                  required
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-slate-500 mb-1">Trigger Event</label>
                  <input
                    type="text"
                    required
                    value={triggerEvent}
                    onChange={(e) => setTriggerEvent(e.target.value)}
                    className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent font-mono"
                  />
                </div>

                <div>
                  <label className="block text-slate-500 mb-1">Delay (Minutes)</label>
                  <input
                    type="number"
                    min="0"
                    value={delayMinutes}
                    onChange={(e) => setDelayMinutes(Number(e.target.value))}
                    className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-slate-500 mb-1">WhatsApp Phone Line</label>
                  <select
                    required
                    value={phoneId}
                    onChange={(e) => setPhoneId(Number(e.target.value))}
                    className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent"
                  >
                    <option value="">Select Phone Number</option>
                    {phoneNumbers.map((p) => (
                      <option key={p.id} value={p.id}>
                        {p.display_phone_number}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-slate-500 mb-1">Approved Template</label>
                  <select
                    required
                    value={templateId}
                    onChange={(e) => setTemplateId(Number(e.target.value))}
                    className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent"
                  >
                    <option value="">Select Template</option>
                    {templates.map((t) => (
                      <option key={t.id} value={t.id}>
                        {t.template_name}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-slate-500 mb-1">Stop Condition</label>
                <select
                  value={stopCondition}
                  onChange={(e) => setStopCondition(e.target.value)}
                  className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent"
                >
                  <option value="customer_replied">Customer Replied (Prevent redundant messages)</option>
                  <option value="payment_received">Payment Received</option>
                  <option value="invoice_paid">Invoice Paid</option>
                  <option value="none">None (Always dispatch)</option>
                </select>
              </div>

              <div className="flex justify-end gap-2 pt-2 border-t dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsCreateModalOpen(false)}
                  className="px-4 py-2 rounded-xl border dark:border-slate-700 font-medium"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createMutation.isPending}
                  className="px-5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-semibold transition"
                >
                  {createMutation.isPending ? 'Saving...' : 'Save Journey'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Logs Drawer */}
      {isLogsModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
          <div className="w-full max-w-2xl max-h-[85vh] flex flex-col rounded-2xl bg-white dark:bg-slate-900 border dark:border-slate-800 p-6 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between border-b dark:border-slate-800 pb-3">
              <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <History className="w-4 h-4 text-emerald-500" />
                Journey Execution Logs
              </h3>
              <button
                onClick={() => setIsLogsModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
              >
                ✕
              </button>
            </div>

            <div className="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800 text-xs">
              {(logsData?.data || []).length === 0 ? (
                <div className="p-8 text-center text-slate-400">No execution logs yet.</div>
              ) : (
                (logsData?.data || []).map((l: any) => (
                  <div key={l.id} className="py-2.5 flex items-center justify-between">
                    <div>
                      <div className="font-semibold text-slate-900 dark:text-white">{l.phone_number}</div>
                      <div className="text-[10px] text-slate-400">{l.status} {l.stopped_reason ? `(${l.stopped_reason})` : ''}</div>
                    </div>
                    <span className="font-mono text-[10px] text-slate-400">{l.created_at || 'Just now'}</span>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
