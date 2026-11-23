import React, { useState } from 'react';
import {
  Megaphone,
  Plus,
  Play,
  Pause,
  XCircle,
  CheckCircle2,
  Clock,
  AlertTriangle,
  Users,
  FileSpreadsheet,
  BarChart2,
  Send,
  Eye,
  RefreshCw,
} from 'lucide-react';
import {
  useWhatsAppCampaigns,
  useCreateWhatsAppCampaign,
  useWhatsAppCampaign,
  useSubmitWhatsAppCampaignForApproval,
  useApproveWhatsAppCampaign,
  useStartWhatsAppCampaign,
  usePauseWhatsAppCampaign,
  useProcessWhatsAppCampaignBatch,
  useWhatsAppPhoneNumbers,
  useWhatsAppTemplates,
} from '../../hooks/useWhatsApp';
import { useCRMStore } from '../../store/crmStore';

interface Props {
  isDark: boolean;
}

export default function WhatsAppCampaignsTab({ isDark }: Props) {
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [selectedCampaignId, setSelectedCampaignId] = useState<number | null>(null);

  const currentUser = useCRMStore((s) => s.user);
  const userRole = (currentUser?.role || '').toLowerCase();
  const canApprove = ['admin', 'owner', 'superadmin', 'super_admin'].includes(userRole);

  const { data: campaignsData, isLoading, refetch } = useWhatsAppCampaigns({
    status: statusFilter === 'all' ? undefined : statusFilter,
  });
  const { data: phoneNumbers = [] } = useWhatsAppPhoneNumbers();
  const { data: templates = [] } = useWhatsAppTemplates();

  const createCampaignMutation = useCreateWhatsAppCampaign();
  const submitForApprovalMutation = useSubmitWhatsAppCampaignForApproval();
  const approveCampaignMutation = useApproveWhatsAppCampaign();
  const startCampaignMutation = useStartWhatsAppCampaign();
  const pauseCampaignMutation = usePauseWhatsAppCampaign();
  const processBatchMutation = useProcessWhatsAppCampaignBatch();

  const { data: campaignDetails } = useWhatsAppCampaign(selectedCampaignId);

  // New Campaign Form State
  const [name, setName] = useState('');
  const [phoneId, setPhoneId] = useState<number | ''>('');
  const [templateId, setTemplateId] = useState<number | ''>('');
  const [audienceType, setAudienceType] = useState<'csv_import' | 'contacts' | 'segment'>('csv_import');
  const [csvContent, setCsvContent] = useState('');
  const [varMapping1, setVarMapping1] = useState('name');
  const [varMapping2, setVarMapping2] = useState('company');

  const campaigns = (campaignsData?.data || []) as any[];

  const handleCreateCampaign = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name || !phoneId || !templateId) return;

    // Parse simple CSV rows
    const rows = csvContent
      .split('\n')
      .map((line) => line.trim())
      .filter(Boolean)
      .map((line) => {
        const parts = line.split(',').map((p) => p.trim());
        return { phone: parts[0], name: parts[1] || 'Customer', company: parts[2] || 'Enterprise' };
      });

    await createCampaignMutation.mutateAsync({
      name,
      whatsapp_phone_number_id: Number(phoneId),
      whatsapp_template_id: Number(templateId),
      audience_type: audienceType,
      csv_rows: rows,
      variable_mappings: { '1': varMapping1, '2': varMapping2 },
    });

    setIsCreateModalOpen(false);
    setName('');
    setCsvContent('');
  };

  return (
    <div className="flex-1 flex flex-col overflow-hidden p-6 space-y-6">
      {/* Header & Controls */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold flex items-center gap-2">
            <Megaphone className="w-5 h-5 text-emerald-500" />
            WhatsApp Bulk Campaign Manager
          </h2>
          <p className="text-xs text-slate-500 dark:text-slate-400">
            Launch verified WhatsApp marketing and utility campaigns with opt-in suppression & administrative approval gates.
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
            Create Campaign
          </button>
        </div>
      </div>

      {/* Filter Tabs */}
      <div className="flex items-center gap-2 border-b dark:border-slate-800 pb-2 text-xs">
        {['all', 'draft', 'pending_approval', 'approved', 'queued', 'processing', 'completed', 'paused'].map((st) => (
          <button
            key={st}
            onClick={() => setStatusFilter(st)}
            className={`capitalize px-3 py-1.5 rounded-lg font-medium transition ${
              statusFilter === st
                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold'
                : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
            }`}
          >
            {st.replace('_', ' ')}
          </button>
        ))}
      </div>

      {/* Campaigns Table */}
      <div className="flex-1 overflow-y-auto rounded-xl border dark:border-slate-800 bg-white/50 dark:bg-slate-900/40">
        {isLoading ? (
          <div className="p-12 text-center text-xs text-slate-500">Loading campaigns...</div>
        ) : campaigns.length === 0 ? (
          <div className="p-12 text-center text-slate-500 space-y-2">
            <Megaphone className="w-10 h-10 mx-auto text-slate-400" />
            <div className="text-sm font-semibold text-slate-700 dark:text-slate-300">No campaigns found</div>
            <p className="text-xs">Create your first bulk WhatsApp campaign with pre-approved Meta templates.</p>
          </div>
        ) : (
          <table className="w-full text-left text-xs">
            <thead className="bg-slate-100/70 dark:bg-slate-800/60 uppercase font-semibold text-slate-500 text-[10px]">
              <tr>
                <th className="py-3 px-4">Campaign Name</th>
                <th className="py-3 px-4">Template</th>
                <th className="py-3 px-4">Audience</th>
                <th className="py-3 px-4">Recipients</th>
                <th className="py-3 px-4">Status</th>
                <th className="py-3 px-4">Est. Cost</th>
                <th className="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {campaigns.map((c) => (
                <tr key={c.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                  <td className="py-3 px-4 font-semibold text-slate-900 dark:text-white">
                    {c.name}
                    <div className="text-[10px] font-normal text-slate-400">{c.phoneNumber?.display_phone_number}</div>
                  </td>
                  <td className="py-3 px-4">
                    <span className="font-mono text-[11px] text-emerald-600 dark:text-emerald-400">
                      {c.template?.template_name || 'N/A'}
                    </span>
                    <span className="ml-1 text-[10px] text-slate-400">({c.template?.category || 'MARKETING'})</span>
                  </td>
                  <td className="py-3 px-4 uppercase text-[10px] text-slate-500 font-medium">
                    {c.audience_type}
                  </td>
                  <td className="py-3 px-4 font-mono">
                    {c.sent_count} / {c.total_recipients}
                    {c.opted_out_count > 0 && (
                      <span className="ml-1 text-[10px] text-amber-500 font-normal">
                        ({c.opted_out_count} suppressed)
                      </span>
                    )}
                  </td>
                  <td className="py-3 px-4">
                    <span
                      className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${
                        c.status === 'completed'
                          ? 'bg-emerald-500/10 text-emerald-500'
                          : c.status === 'processing'
                          ? 'bg-blue-500/10 text-blue-500 animate-pulse'
                          : c.status === 'approved'
                          ? 'bg-teal-500/10 text-teal-500'
                          : c.status === 'pending_approval'
                          ? 'bg-amber-500/10 text-amber-500'
                          : 'bg-slate-500/10 text-slate-500'
                      }`}
                    >
                      {c.status.replace('_', ' ')}
                    </span>
                  </td>
                  <td className="py-3 px-4 font-mono text-slate-600 dark:text-slate-300">
                    ${Number(c.estimated_cost || 0).toFixed(2)}
                  </td>
                  <td className="py-3 px-4 text-right space-x-1.5">
                    {c.status === 'draft' && (
                      <button
                        onClick={() => submitForApprovalMutation.mutate(c.id)}
                        className="px-2 py-1 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 font-semibold text-[11px] hover:bg-amber-500/20"
                      >
                        Submit Approval
                      </button>
                    )}

                    {c.status === 'pending_approval' && canApprove && (
                      <button
                        onClick={() => approveCampaignMutation.mutate(c.id)}
                        className="px-2 py-1 rounded bg-teal-500/10 text-teal-600 dark:text-teal-400 font-semibold text-[11px] hover:bg-teal-500/20"
                      >
                        Approve
                      </button>
                    )}

                    {(c.status === 'approved' || c.status === 'paused') && (
                      <button
                        onClick={() => startCampaignMutation.mutate(c.id)}
                        className="px-2 py-1 rounded bg-emerald-500 text-white font-semibold text-[11px] hover:bg-emerald-600 inline-flex items-center gap-1"
                      >
                        <Play className="w-3 h-3" /> Launch
                      </button>
                    )}

                    {c.status === 'processing' && (
                      <>
                        <button
                          onClick={() => processBatchMutation.mutate({ id: c.id, batchSize: 50 })}
                          className="px-2 py-1 rounded bg-blue-500 text-white font-semibold text-[11px] hover:bg-blue-600"
                        >
                          Run Batch
                        </button>
                        <button
                          onClick={() => pauseCampaignMutation.mutate(c.id)}
                          className="p-1 rounded bg-slate-200 dark:bg-slate-700 hover:bg-slate-300"
                          title="Pause"
                        >
                          <Pause className="w-3 h-3" />
                        </button>
                      </>
                    )}

                    <button
                      onClick={() => setSelectedCampaignId(c.id)}
                      className="p-1 rounded text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                      title="View Details"
                    >
                      <Eye className="w-4 h-4" />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* Campaign Details Drawer / Modal */}
      {selectedCampaignId && campaignDetails && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
          <div className="w-full max-w-xl rounded-2xl bg-white dark:bg-slate-900 border dark:border-slate-800 p-6 space-y-5 shadow-2xl">
            <div className="flex items-center justify-between border-b dark:border-slate-800 pb-3">
              <div>
                <h3 className="text-base font-bold text-slate-900 dark:text-white">
                  {campaignDetails.campaign?.name}
                </h3>
                <span className="text-xs text-slate-500">ID #{campaignDetails.campaign?.id}</span>
              </div>
              <button
                onClick={() => setSelectedCampaignId(null)}
                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
              >
                ✕
              </button>
            </div>

            {/* Funnel Metrics */}
            <div className="grid grid-cols-4 gap-2 text-center text-xs">
              <div className="p-3 rounded-xl bg-slate-100 dark:bg-slate-800/60">
                <div className="text-[10px] text-slate-400 uppercase">Total</div>
                <div className="text-base font-bold text-slate-900 dark:text-white">
                  {campaignDetails.analytics?.funnel?.total || 0}
                </div>
              </div>
              <div className="p-3 rounded-xl bg-blue-500/10 text-blue-500">
                <div className="text-[10px] uppercase">Sent</div>
                <div className="text-base font-bold">
                  {campaignDetails.analytics?.funnel?.sent || 0}
                </div>
              </div>
              <div className="p-3 rounded-xl bg-emerald-500/10 text-emerald-500">
                <div className="text-[10px] uppercase">Delivered</div>
                <div className="text-base font-bold">
                  {campaignDetails.analytics?.funnel?.delivered || 0}
                </div>
              </div>
              <div className="p-3 rounded-xl bg-purple-500/10 text-purple-500">
                <div className="text-[10px] uppercase">Read</div>
                <div className="text-base font-bold">
                  {campaignDetails.analytics?.funnel?.read || 0}
                </div>
              </div>
            </div>

            {/* Cost & Suppression Summary */}
            <div className="space-y-2 text-xs bg-slate-50 dark:bg-slate-800/40 p-4 rounded-xl">
              <div className="flex justify-between">
                <span className="text-slate-500">Estimated Cost:</span>
                <span className="font-mono font-semibold">
                  ${Number(campaignDetails.campaign?.estimated_cost || 0).toFixed(4)} USD
                </span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Actual Billed:</span>
                <span className="font-mono font-semibold text-emerald-500">
                  ${Number(campaignDetails.analytics?.costs?.actual_billed_usd || 0).toFixed(4)} USD
                </span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Opt-Out Suppressed:</span>
                <span className="font-mono text-amber-500">
                  {campaignDetails.campaign?.opted_out_count || 0} contacts
                </span>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Create Campaign Modal */}
      {isCreateModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
          <div className="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border dark:border-slate-800 p-6 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between border-b dark:border-slate-800 pb-3">
              <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <Plus className="w-4 h-4 text-emerald-500" />
                Create WhatsApp Campaign
              </h3>
              <button
                onClick={() => setIsCreateModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleCreateCampaign} className="space-y-4 text-xs">
              <div>
                <label className="block text-slate-500 mb-1">Campaign Name</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Q4 Festive Discount Broadcast"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-slate-500 mb-1">Dispatch Number</label>
                  <select
                    required
                    value={phoneId}
                    onChange={(e) => setPhoneId(Number(e.target.value))}
                    className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent"
                  >
                    <option value="">Select Phone Number</option>
                    {phoneNumbers.map((p) => (
                      <option key={p.id} value={p.id}>
                        {p.display_phone_number} ({p.quality_rating})
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
                        {t.template_name} ({t.category})
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-slate-500 mb-1">
                  Recipient Audience (Phone, Name, Company - 1 per line)
                </label>
                <textarea
                  rows={4}
                  required
                  placeholder="+919876543210, Rohan Sharma, Acme Corp&#10;+919876543211, Priya Patel, TechSoft"
                  value={csvContent}
                  onChange={(e) => setCsvContent(e.target.value)}
                  className="w-full p-2.5 font-mono text-[11px] rounded-xl border dark:border-slate-700 bg-transparent"
                />
                <p className="text-[10px] text-slate-400 mt-1">
                  Duplicates and contacts on the suppression opt-out list are automatically removed.
                </p>
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
                  disabled={createCampaignMutation.isPending}
                  className="px-5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-semibold transition"
                >
                  {createCampaignMutation.isPending ? 'Creating...' : 'Create Campaign'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
