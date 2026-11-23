import React, { useState } from 'react';
import {
  Phone,
  Plus,
  ShieldCheck,
  Building2,
  Clock,
  Activity,
  AlertTriangle,
  RefreshCw,
  ExternalLink,
  Power,
  Edit2,
  Layers,
  History,
  X,
} from 'lucide-react';
import {
  useWhatsAppPhoneNumbers,
  useWhatsAppAccounts,
  useConnectWhatsAppPhoneNumber,
  useCreateWhatsAppAccount,
  useUpdateWhatsAppPhoneNumber,
  useDisconnectWhatsAppPhoneNumber,
  useTestWhatsAppConnection,
  useWhatsAppIntegrationLogs,
} from '../../hooks/useWhatsApp';
import { WhatsAppPhoneNumberItem } from '../../api/types';

interface Props {
  isDark: boolean;
}

export default function WhatsAppNumbersTab({ isDark }: Props) {
  const [selectedPhone, setSelectedPhone] = useState<WhatsAppPhoneNumberItem | null>(null);
  const [isConnectModalOpen, setIsConnectModalOpen] = useState(false);
  const [isWabaModalOpen, setIsWabaModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [isLogsModalOpen, setIsLogsModalOpen] = useState(false);

  const { data: phoneNumbers = [], isLoading, refetch } = useWhatsAppPhoneNumbers();
  const { data: accounts = [] } = useWhatsAppAccounts();
  const { data: logsData } = useWhatsAppIntegrationLogs(1, 20);

  const testConnectionMutation = useTestWhatsAppConnection();
  const connectPhoneMutation = useConnectWhatsAppPhoneNumber();
  const createAccountMutation = useCreateWhatsAppAccount();
  const updatePhoneMutation = useUpdateWhatsAppPhoneNumber();
  const disconnectPhoneMutation = useDisconnectWhatsAppPhoneNumber();

  // Connect Phone State
  const [accountId, setAccountId] = useState<number | ''>('');
  const [phoneId, setPhoneId] = useState('');
  const [displayNumber, setDisplayNumber] = useState('');
  const [verifiedName, setVerifiedName] = useState('');

  // WABA Account Form State
  const [wabaName, setWabaName] = useState('');
  const [wabaId, setWabaId] = useState('');
  const [token, setToken] = useState('');

  // Edit Phone Settings State
  const [branchId, setBranchId] = useState<number | ''>('');
  const [departmentId, setDepartmentId] = useState<number | ''>('');
  const [tier, setTier] = useState('TIER_10K');
  const [autoReply, setAutoReply] = useState('');

  const handleConnectPhone = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!accountId || !phoneId || !displayNumber) return;

    await connectPhoneMutation.mutateAsync({
      whatsapp_account_id: Number(accountId),
      phone_number_id: phoneId,
      display_phone_number: displayNumber,
      verified_name: verifiedName || undefined,
    });

    setIsConnectModalOpen(false);
    setPhoneId('');
    setDisplayNumber('');
    setVerifiedName('');
  };

  const handleCreateAccount = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!wabaName || !wabaId || !token) return;

    await createAccountMutation.mutateAsync({
      name: wabaName,
      waba_id: wabaId,
      system_user_token: token,
      webhook_verify_token: 'meta_webhook_' + Math.random().toString(36).substring(7),
    });

    setIsWabaModalOpen(false);
    setWabaName('');
    setWabaId('');
    setToken('');
  };

  const handleOpenEdit = (phone: WhatsAppPhoneNumberItem) => {
    setSelectedPhone(phone);
    setBranchId(phone.branch_id || '');
    setDepartmentId(phone.department_id || '');
    setTier(phone.messaging_limit_tier || 'TIER_10K');
    setAutoReply(phone.default_reply_rules?.after_hours_message || '');
    setIsEditModalOpen(true);
  };

  const handleSavePhoneSettings = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedPhone) return;

    await updatePhoneMutation.mutateAsync({
      id: selectedPhone.id,
      data: {
        branch_id: branchId ? Number(branchId) : null,
        department_id: departmentId ? Number(departmentId) : null,
        messaging_limit_tier: tier,
        default_reply_rules: autoReply ? { after_hours_message: autoReply } : undefined,
      },
    });

    setIsEditModalOpen(false);
  };

  return (
    <div className="flex-1 flex flex-col overflow-hidden p-6 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold flex items-center gap-2">
            <Phone className="w-5 h-5 text-emerald-500" />
            WhatsApp Numbers & WABA Accounts
          </h2>
          <p className="text-xs text-slate-500 dark:text-slate-400">
            Multi-number routing, department assignment, messaging tier limit tracking, and Meta webhook health.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() => setIsLogsModalOpen(true)}
            className="flex items-center gap-1.5 px-3 py-2 rounded-xl border dark:border-slate-800 text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition"
          >
            <History className="w-3.5 h-3.5" />
            Audit Logs
          </button>
          <button
            onClick={() => setIsWabaModalOpen(true)}
            className="flex items-center gap-1.5 px-3 py-2 rounded-xl border dark:border-slate-800 text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition"
          >
            <ShieldCheck className="w-3.5 h-3.5 text-emerald-500" />
            Connect WABA
          </button>
          <button
            onClick={() => setIsConnectModalOpen(true)}
            className="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-semibold shadow-xs transition"
          >
            <Plus className="w-4 h-4" />
            Register Phone Number
          </button>
        </div>
      </div>

      {/* Numbers Table */}
      <div className="flex-1 overflow-y-auto rounded-xl border dark:border-slate-800 bg-white/50 dark:bg-slate-900/40">
        {isLoading ? (
          <div className="p-12 text-center text-xs text-slate-500">Loading numbers...</div>
        ) : phoneNumbers.length === 0 ? (
          <div className="p-12 text-center text-slate-500 space-y-2">
            <Phone className="w-10 h-10 mx-auto text-slate-400" />
            <div className="text-sm font-semibold text-slate-700 dark:text-slate-300">No phone numbers connected</div>
            <p className="text-xs">Connect your official WhatsApp Business Account to register your numbers.</p>
          </div>
        ) : (
          <table className="w-full text-left text-xs">
            <thead className="bg-slate-100/70 dark:bg-slate-800/60 uppercase font-semibold text-slate-500 text-[10px]">
              <tr>
                <th className="py-3 px-4">Display Number</th>
                <th className="py-3 px-4">Verified Business Name</th>
                <th className="py-3 px-4">Quality Rating</th>
                <th className="py-3 px-4">Limit Tier</th>
                <th className="py-3 px-4">Sent Today</th>
                <th className="py-3 px-4">Status</th>
                <th className="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {phoneNumbers.map((p) => (
                <tr key={p.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                  <td className="py-3 px-4 font-mono font-semibold text-slate-900 dark:text-white">
                    {p.display_phone_number}
                    {p.is_default && (
                      <span className="ml-1.5 px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-500 text-[9px] font-bold uppercase">
                        Default
                      </span>
                    )}
                  </td>
                  <td className="py-3 px-4 font-medium text-slate-700 dark:text-slate-200">
                    {p.verified_name || 'N/A'}
                  </td>
                  <td className="py-3 px-4">
                    <span
                      className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${
                        p.quality_rating === 'GREEN'
                          ? 'bg-emerald-500/10 text-emerald-500'
                          : p.quality_rating === 'YELLOW'
                          ? 'bg-amber-500/10 text-amber-500'
                          : 'bg-red-500/10 text-red-500'
                      }`}
                    >
                      {p.quality_rating}
                    </span>
                  </td>
                  <td className="py-3 px-4 font-mono text-[11px] text-slate-600 dark:text-slate-300">
                    {p.messaging_limit_tier || 'TIER_10K'}
                  </td>
                  <td className="py-3 px-4 font-mono">
                    {p.messages_sent_today || 0}
                  </td>
                  <td className="py-3 px-4">
                    <span
                      className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${
                        p.channel_status === 'active' || p.status === 'CONNECTED'
                          ? 'bg-emerald-500/10 text-emerald-500'
                          : 'bg-slate-500/10 text-slate-500'
                      }`}
                    >
                      {p.channel_status || p.status}
                    </span>
                  </td>
                  <td className="py-3 px-4 text-right space-x-1.5">
                    <button
                      onClick={() => testConnectionMutation.mutate(p.id)}
                      disabled={testConnectionMutation.isPending}
                      className="px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-[11px] font-semibold"
                    >
                      Test
                    </button>
                    <button
                      onClick={() => handleOpenEdit(p)}
                      className="px-2 py-1 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20 text-[11px] font-semibold"
                    >
                      Configure
                    </button>
                    <button
                      onClick={() => disconnectPhoneMutation.mutate(p.id)}
                      className="p-1 rounded text-red-400 hover:text-red-600 dark:hover:text-red-300"
                      title="Disconnect Phone Number"
                    >
                      <Power className="w-3.5 h-3.5" />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* Connect Phone Modal */}
      {isConnectModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
          <div className="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border dark:border-slate-800 p-6 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between border-b dark:border-slate-800 pb-3">
              <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <Phone className="w-4 h-4 text-emerald-500" />
                Register WhatsApp Number
              </h3>
              <button onClick={() => setIsConnectModalOpen(false)}>✕</button>
            </div>

            <form onSubmit={handleConnectPhone} className="space-y-3 text-xs">
              <div>
                <label className="block text-slate-500 mb-1">WhatsApp Business Account</label>
                <select
                  required
                  value={accountId}
                  onChange={(e) => setAccountId(Number(e.target.value))}
                  className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent"
                >
                  <option value="">Select Account</option>
                  {accounts.map((a) => (
                    <option key={a.id} value={a.id}>
                      {a.name} ({a.waba_id})
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-slate-500 mb-1">Phone Number ID (from Meta App)</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. 102938475610293"
                  value={phoneId}
                  onChange={(e) => setPhoneId(e.target.value)}
                  className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent font-mono"
                />
              </div>

              <div>
                <label className="block text-slate-500 mb-1">Display Phone Number</label>
                <input
                  type="text"
                  required
                  placeholder="+91 98765 43210"
                  value={displayNumber}
                  onChange={(e) => setDisplayNumber(e.target.value)}
                  className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent font-mono"
                />
              </div>

              <div>
                <label className="block text-slate-500 mb-1">Verified Business Name (Optional)</label>
                <input
                  type="text"
                  placeholder="e.g. Acme Corp Support"
                  value={verifiedName}
                  onChange={(e) => setVerifiedName(e.target.value)}
                  className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2 border-t dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsConnectModalOpen(false)}
                  className="px-4 py-2 rounded-xl border dark:border-slate-700 font-medium"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={connectPhoneMutation.isPending}
                  className="px-5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-semibold transition"
                >
                  {connectPhoneMutation.isPending ? 'Connecting...' : 'Connect Number'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Connect WABA Modal */}
      {isWabaModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
          <div className="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border dark:border-slate-800 p-6 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between border-b dark:border-slate-800 pb-3">
              <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <ShieldCheck className="w-4 h-4 text-emerald-500" />
                Connect Meta WABA Account
              </h3>
              <button onClick={() => setIsWabaModalOpen(false)}>✕</button>
            </div>

            <form onSubmit={handleCreateAccount} className="space-y-3 text-xs">
              <div>
                <label className="block text-slate-500 mb-1">Business Account Name</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Acme Official WABA"
                  value={wabaName}
                  onChange={(e) => setWabaName(e.target.value)}
                  className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent"
                />
              </div>

              <div>
                <label className="block text-slate-500 mb-1">WhatsApp Business Account ID (WABA ID)</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. 192837465019283"
                  value={wabaId}
                  onChange={(e) => setWabaId(e.target.value)}
                  className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent font-mono"
                />
              </div>

              <div>
                <label className="block text-slate-500 mb-1">Permanent System User Access Token</label>
                <input
                  type="password"
                  required
                  placeholder="EAAB..."
                  value={token}
                  onChange={(e) => setToken(e.target.value)}
                  className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent font-mono"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2 border-t dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsWabaModalOpen(false)}
                  className="px-4 py-2 rounded-xl border dark:border-slate-700 font-medium"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createAccountMutation.isPending}
                  className="px-5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-semibold transition"
                >
                  {createAccountMutation.isPending ? 'Connecting...' : 'Connect WABA'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Configure Phone Modal */}
      {isEditModalOpen && selectedPhone && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
          <div className="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border dark:border-slate-800 p-6 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between border-b dark:border-slate-800 pb-3">
              <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <Edit2 className="w-4 h-4 text-emerald-500" />
                Configure Line Settings
              </h3>
              <button onClick={() => setIsEditModalOpen(false)}>✕</button>
            </div>

            <form onSubmit={handleSavePhoneSettings} className="space-y-3.5 text-xs">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-slate-500 mb-1">Branch ID</label>
                  <input
                    type="number"
                    value={branchId}
                    onChange={(e) => setBranchId(Number(e.target.value))}
                    className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent"
                  />
                </div>
                <div>
                  <label className="block text-slate-500 mb-1">Department ID</label>
                  <input
                    type="number"
                    value={departmentId}
                    onChange={(e) => setDepartmentId(Number(e.target.value))}
                    className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent"
                  />
                </div>
              </div>

              <div>
                <label className="block text-slate-500 mb-1">Messaging Tier Limit</label>
                <select
                  value={tier}
                  onChange={(e) => setTier(e.target.value)}
                  className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent font-mono"
                >
                  <option value="TIER_250">TIER_250 (250 unique users/24h)</option>
                  <option value="TIER_1K">TIER_1K (1,000 unique users/24h)</option>
                  <option value="TIER_10K">TIER_10K (10,000 unique users/24h)</option>
                  <option value="TIER_100K">TIER_100K (100,000 unique users/24h)</option>
                  <option value="TIER_UNLIMITED">TIER_UNLIMITED (Unlimited unique users/24h)</option>
                </select>
              </div>

              <div>
                <label className="block text-slate-500 mb-1">After-Hours Auto-Reply Message</label>
                <textarea
                  rows={2}
                  placeholder="Thank you for reaching out. We will respond during business hours."
                  value={autoReply}
                  onChange={(e) => setAutoReply(e.target.value)}
                  className="w-full p-2.5 rounded-xl border dark:border-slate-700 bg-transparent"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2 border-t dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsEditModalOpen(false)}
                  className="px-4 py-2 rounded-xl border dark:border-slate-700 font-medium"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={updatePhoneMutation.isPending}
                  className="px-5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-semibold transition"
                >
                  {updatePhoneMutation.isPending ? 'Saving...' : 'Save Settings'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Audit Logs Drawer */}
      {isLogsModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
          <div className="w-full max-w-2xl max-h-[85vh] flex flex-col rounded-2xl bg-white dark:bg-slate-900 border dark:border-slate-800 p-6 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between border-b dark:border-slate-800 pb-3">
              <h3 className="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <History className="w-4 h-4 text-emerald-500" />
                WhatsApp Integration Logs
              </h3>
              <button onClick={() => setIsLogsModalOpen(false)}>✕</button>
            </div>

            <div className="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800 text-xs">
              {(logsData?.data || []).length === 0 ? (
                <div className="p-8 text-center text-slate-400">No integration logs recorded.</div>
              ) : (
                (logsData?.data || []).map((l: any) => (
                  <div key={l.id} className="py-2.5 flex items-center justify-between">
                    <div>
                      <div className="font-semibold text-slate-900 dark:text-white flex items-center gap-1.5">
                        <span
                          className={`w-2 h-2 rounded-full ${
                            l.status === 'success' ? 'bg-emerald-500' : 'bg-red-500'
                          }`}
                        />
                        {l.event_type}
                      </div>
                      {l.error_message && (
                        <div className="text-[10px] text-red-400 mt-0.5">{l.error_message}</div>
                      )}
                    </div>
                    <span className="font-mono text-[10px] text-slate-400">{l.created_at}</span>
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
