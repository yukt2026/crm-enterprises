import React, { useState } from 'react';
import {
  CreditCard,
  Zap,
  TrendingUp,
  ShieldCheck,
  Receipt,
  AlertTriangle,
  CheckCircle2,
  Clock,
  ArrowUpRight,
  RefreshCw,
  PlusCircle,
  Settings,
  HardDrive,
  MessageSquare,
  PhoneCall,
  Bot,
  Mail,
  Users,
  Layers,
  Lock,
  ChevronRight,
  Info,
  DollarSign,
  FileText,
  Calendar,
} from 'lucide-react';
import {
  useBillingPlans,
  useBillingSubscription,
  useSubscribePlan,
  useCancelSubscription,
  useUpdateBillingContact,
  useBillingInvoices,
  useUsageDashboard,
  useUsageRecords,
  useCreditWallet,
  useRechargeCreditWallet,
  useUpdateAutoRecharge,
  useCreditLedger,
  useVerifyCreditLedgerIntegrity,
  useCostProtection,
  useUpdateCostProtection,
} from '../../hooks/useBilling';
import { SubscriptionPlanItem } from '../../api/types';

export const SubscriptionBillingHubView: React.FC = () => {
  const [activeTab, setActiveTab] = useState<'plans' | 'usage' | 'wallet' | 'invoices' | 'cost-protection'>('plans');
  const [billingCycle, setBillingCycle] = useState<'monthly' | 'annual'>('monthly');

  // Modals state
  const [upgradePlanModal, setUpgradePlanModal] = useState<SubscriptionPlanItem | null>(null);
  const [prorateUpgrade, setProrateUpgrade] = useState<boolean>(true);
  const [isRechargeModalOpen, setIsRechargeModalOpen] = useState(false);
  const [rechargeAmount, setRechargeAmount] = useState<number>(50);
  const [rechargeRef, setRechargeRef] = useState<string>('');
  const [isContactModalOpen, setIsContactModalOpen] = useState(false);
  const [contactForm, setContactForm] = useState({
    billing_contact_name: '',
    billing_contact_email: '',
    billing_tax_id: '',
    billing_address: '',
  });

  // Queries
  const { data: plans = [], isLoading: loadingPlans } = useBillingPlans();
  const { data: subscription, isLoading: loadingSub } = useBillingSubscription();
  const { data: usageDashboard, isLoading: loadingUsage } = useUsageDashboard();
  const { data: usageRecords = [] } = useUsageRecords({ per_page: 20 });
  const { data: wallet, isLoading: loadingWallet } = useCreditWallet();
  const { data: ledger = [], isLoading: loadingLedger } = useCreditLedger(50);
  const { data: invoices = [], isLoading: loadingInvoices } = useBillingInvoices();
  const { data: costProtection, isLoading: loadingCost } = useCostProtection();

  // Mutations
  const subscribeMutation = useSubscribePlan();
  const cancelMutation = useCancelSubscription();
  const updateContactMutation = useUpdateBillingContact();
  const rechargeMutation = useRechargeCreditWallet();
  const autoRechargeMutation = useUpdateAutoRecharge();
  const verifyIntegrityMutation = useVerifyCreditLedgerIntegrity();
  const updateCostMutation = useUpdateCostProtection();

  // Integrity report state
  const [integrityReport, setIntegrityReport] = useState<{
    is_valid: boolean;
    current_wallet_balance: number;
    calculated_ledger_sum: number;
    difference: number;
    records_count: number;
    verified_at: string;
  } | null>(null);

  const handleUpgradeSubmit = async () => {
    if (!upgradePlanModal) return;
    try {
      await subscribeMutation.mutateAsync({
        plan_id: upgradePlanModal.id,
        billing_cycle: billingCycle,
        prorate: prorateUpgrade,
      });
      setUpgradePlanModal(null);
    } catch (err: any) {
      alert(err?.message || 'Failed to update subscription');
    }
  };

  const handleRechargeSubmit = async () => {
    if (rechargeAmount <= 0) return;
    try {
      const ref = rechargeRef.trim() || `pay_rzp_manual_${Date.now()}`;
      await rechargeMutation.mutateAsync({
        amount: rechargeAmount,
        reference_id: ref,
        description: `Wallet Balance Top-up ($${rechargeAmount})`,
      });
      setIsRechargeModalOpen(false);
      setRechargeRef('');
    } catch (err: any) {
      alert(err?.message || 'Failed to recharge wallet');
    }
  };

  const handleVerifyIntegrity = async () => {
    try {
      const res = await verifyIntegrityMutation.mutateAsync();
      setIntegrityReport(res);
    } catch (err: any) {
      alert(err?.message || 'Integrity check failed');
    }
  };

  const handleContactSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await updateContactMutation.mutateAsync(contactForm);
      setIsContactModalOpen(false);
    } catch (err: any) {
      alert(err?.message || 'Failed to update billing details');
    }
  };

  return (
    <div className="space-y-6">
      {/* Top Banner / Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-sm text-white">
        <div>
          <div className="flex items-center gap-3">
            <div className="p-2.5 bg-blue-600/20 text-blue-400 rounded-xl border border-blue-500/20">
              <CreditCard className="w-6 h-6" />
            </div>
            <div>
              <h1 className="text-2xl font-bold tracking-tight">SaaS Subscriptions & Hybrid Billing</h1>
              <p className="text-sm text-slate-400">
                Manage commercial plans, usage metering allowances, immutable credit ledger, and cost protection
              </p>
            </div>
          </div>
        </div>

        {/* Quick Wallet Summary Pill */}
        <div className="flex items-center gap-4 bg-slate-800/80 px-4 py-3 rounded-xl border border-slate-700/60">
          <div>
            <div className="text-xs text-slate-400 font-medium">Credit Balance</div>
            <div className="text-xl font-bold text-emerald-400">
              ${wallet ? wallet.balance.toFixed(2) : '0.00'}
            </div>
          </div>
          {wallet && wallet.reserved_balance > 0 && (
            <div className="border-l border-slate-700 pl-4">
              <div className="text-xs text-slate-400 font-medium">On Hold</div>
              <div className="text-sm font-semibold text-amber-400">
                ${wallet.reserved_balance.toFixed(2)}
              </div>
            </div>
          )}
          <button
            onClick={() => setIsRechargeModalOpen(true)}
            className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-semibold shadow-sm transition"
          >
            <PlusCircle className="w-3.5 h-3.5" />
            Top Up
          </button>
        </div>
      </div>

      {/* Grace Period / Dunning Warning Alert */}
      {subscription?.in_grace_period && (
        <div className="bg-amber-500/10 border border-amber-500/30 rounded-xl p-4 flex items-start gap-3">
          <AlertTriangle className="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
          <div className="text-sm">
            <h4 className="font-semibold text-amber-400">Subscription in Grace Period (Dunning Active)</h4>
            <p className="text-slate-300">
              Your last renewal payment failed. Account services remain active until{' '}
              <span className="font-medium text-white">
                {subscription.grace_period_ends_at ? new Date(subscription.grace_period_ends_at).toLocaleDateString() : 'grace period expires'}
              </span>
              . Please update your payment method or top up credits immediately to prevent service suspension.
            </p>
          </div>
        </div>
      )}

      {/* Navigation Tabs */}
      <div className="flex border-b border-slate-800 space-x-1">
        {[
          { id: 'plans', label: 'Plans & Pricing', icon: Zap },
          { id: 'usage', label: 'Usage Metering', icon: TrendingUp },
          { id: 'wallet', label: 'Credit Wallet & Ledger', icon: CreditCard },
          { id: 'invoices', label: 'Invoices & Tax Info', icon: Receipt },
          { id: 'cost-protection', label: 'Cost Protection Caps', icon: ShieldCheck },
        ].map((tab) => {
          const Icon = tab.icon;
          const isActive = activeTab === tab.id;
          return (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id as any)}
              className={`flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition -mb-px ${
                isActive
                  ? 'border-blue-500 text-blue-400 bg-blue-500/5'
                  : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-700'
              }`}
            >
              <Icon className="w-4 h-4" />
              {tab.label}
            </button>
          );
        })}
      </div>

      {/* TAB 1: Plans & Pricing */}
      {activeTab === 'plans' && (
        <div className="space-y-6">
          {/* Billing Cycle Switch */}
          <div className="flex items-center justify-between bg-slate-900 border border-slate-800 p-4 rounded-xl">
            <div className="flex items-center gap-2">
              <span className="text-sm font-semibold text-white">Billing Cycle:</span>
              <div className="flex items-center bg-slate-800 p-1 rounded-lg border border-slate-700">
                <button
                  onClick={() => setBillingCycle('monthly')}
                  className={`px-3 py-1 rounded-md text-xs font-semibold transition ${
                    billingCycle === 'monthly' ? 'bg-blue-600 text-white shadow' : 'text-slate-400 hover:text-white'
                  }`}
                >
                  Monthly
                </button>
                <button
                  onClick={() => setBillingCycle('annual')}
                  className={`px-3 py-1 rounded-md text-xs font-semibold flex items-center gap-1 transition ${
                    billingCycle === 'annual' ? 'bg-blue-600 text-white shadow' : 'text-slate-400 hover:text-white'
                  }`}
                >
                  Annual
                  <span className="text-[10px] bg-emerald-500 text-black px-1.5 py-0.5 rounded-full font-bold">
                    Save ~17%
                  </span>
                </button>
              </div>
            </div>

            {/* Current Active Plan Badge */}
            {subscription && subscription.plan && (
              <div className="text-xs text-slate-400">
                Current Plan: <span className="font-semibold text-blue-400">{subscription.plan.name}</span> (
                <span className="capitalize">{subscription.status}</span>) • Renews:{' '}
                {subscription.current_period_end ? new Date(subscription.current_period_end).toLocaleDateString() : 'N/A'}
              </div>
            )}
          </div>

          {/* Plan Directory Cards */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            {plans.map((p) => {
              const isCurrent = subscription?.plan_id === p.id && subscription.status !== 'cancelled';
              const price = billingCycle === 'annual' ? p.price_annual : p.price_monthly;
              const period = billingCycle === 'annual' ? '/yr' : '/mo';

              return (
                <div
                  key={p.id}
                  className={`relative flex flex-col justify-between bg-slate-900 border rounded-2xl p-6 transition shadow-sm ${
                    isCurrent
                      ? 'border-blue-500/60 ring-2 ring-blue-500/20'
                      : 'border-slate-800 hover:border-slate-700'
                  }`}
                >
                  {isCurrent && (
                    <div className="absolute -top-3 left-1/2 -translate-x-1/2 bg-blue-600 text-white text-[11px] font-bold px-3 py-0.5 rounded-full uppercase tracking-wider shadow">
                      Current Plan
                    </div>
                  )}

                  <div>
                    <div className="flex items-center justify-between mb-2">
                      <h3 className="text-xl font-bold text-white">{p.name}</h3>
                      <span className="text-xs font-mono text-slate-400 uppercase bg-slate-800 px-2 py-0.5 rounded">
                        {p.plan_code}
                      </span>
                    </div>
                    <p className="text-xs text-slate-400 mb-4 min-h-[32px]">{p.description}</p>

                    <div className="mb-6">
                      <div className="flex items-baseline gap-1">
                        <span className="text-3xl font-extrabold text-white">${price.toFixed(2)}</span>
                        <span className="text-sm text-slate-400">{period}</span>
                      </div>
                      {p.trial_days > 0 && (
                        <div className="text-xs text-emerald-400 mt-1 flex items-center gap-1">
                          <CheckCircle2 className="w-3.5 h-3.5" />
                          {p.trial_days}-day free trial included
                        </div>
                      )}
                    </div>

                    {/* Hard Plan Limits from API */}
                    <div className="space-y-2 border-t border-slate-800 pt-4 mb-4">
                      <div className="text-xs font-semibold text-slate-300 uppercase tracking-wider">
                        Included Platform Limits
                      </div>
                      <div className="text-xs text-slate-400 flex items-center justify-between">
                        <span>Team Seats</span>
                        <span className="text-white font-medium">{p.seat_limit}</span>
                      </div>
                      <div className="text-xs text-slate-400 flex items-center justify-between">
                        <span>WhatsApp Numbers</span>
                        <span className="text-white font-medium">{p.whatsapp_number_limit}</span>
                      </div>
                      <div className="text-xs text-slate-400 flex items-center justify-between">
                        <span>AI Agents</span>
                        <span className="text-white font-medium">{p.ai_agent_limit}</span>
                      </div>
                      <div className="text-xs text-slate-400 flex items-center justify-between">
                        <span>Automations</span>
                        <span className="text-white font-medium">{p.workflow_limit}</span>
                      </div>
                      <div className="text-xs text-slate-400 flex items-center justify-between">
                        <span>API Rate Limit</span>
                        <span className="text-white font-medium">{p.api_rpm_limit} RPM</span>
                      </div>
                    </div>

                    {/* Included Allowances */}
                    <div className="space-y-2 border-t border-slate-800 pt-4 mb-6">
                      <div className="text-xs font-semibold text-slate-300 uppercase tracking-wider">
                        Monthly Allowance
                      </div>
                      <div className="text-xs text-slate-400 flex items-center justify-between">
                        <span>LLM Tokens</span>
                        <span className="text-white font-medium">{p.allowances?.llm_tokens?.toLocaleString() || 0}</span>
                      </div>
                      <div className="text-xs text-slate-400 flex items-center justify-between">
                        <span>AI Calling</span>
                        <span className="text-white font-medium">{p.allowances?.ai_call_minutes || 0} mins</span>
                      </div>
                      <div className="text-xs text-slate-400 flex items-center justify-between">
                        <span>WhatsApp Messages</span>
                        <span className="text-white font-medium">{p.allowances?.whatsapp_messages?.toLocaleString() || 0}</span>
                      </div>
                    </div>
                  </div>

                  <div>
                    {isCurrent ? (
                      <button
                        disabled
                        className="w-full py-2 bg-slate-800 text-slate-400 rounded-xl text-xs font-bold cursor-not-allowed border border-slate-700"
                      >
                        Active Plan
                      </button>
                    ) : (
                      <button
                        onClick={() => {
                          setUpgradePlanModal(p);
                        }}
                        className="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-1 shadow"
                      >
                        Select {p.name}
                        <ArrowUpRight className="w-3.5 h-3.5" />
                      </button>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* TAB 2: Usage Metering Dashboard */}
      {activeTab === 'usage' && (
        <div className="space-y-6">
          {/* Period Summary */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="bg-slate-900 border border-slate-800 p-5 rounded-2xl">
              <div className="text-xs text-slate-400 font-medium">Billing Period</div>
              <div className="text-sm font-semibold text-white mt-1">
                {usageDashboard?.current_period_start
                  ? `${new Date(usageDashboard.current_period_start).toLocaleDateString()} - ${new Date(
                      usageDashboard.current_period_end || ''
                    ).toLocaleDateString()}`
                  : 'Current Cycle'}
              </div>
              <div className="text-xs text-blue-400 mt-2 flex items-center gap-1">
                <Clock className="w-3.5 h-3.5" />
                {usageDashboard?.days_remaining_in_cycle ?? 30} days remaining in cycle
              </div>
            </div>

            <div className="bg-slate-900 border border-slate-800 p-5 rounded-2xl">
              <div className="text-xs text-slate-400 font-medium">Estimated Period Overage</div>
              <div className="text-2xl font-bold text-amber-400 mt-1">
                ${usageDashboard ? usageDashboard.total_overage_charge.toFixed(2) : '0.00'}
              </div>
              <div className="text-xs text-slate-400 mt-1">
                Billed against Credit Wallet at settlement
              </div>
            </div>

            <div className="bg-slate-900 border border-slate-800 p-5 rounded-2xl">
              <div className="text-xs text-slate-400 font-medium">Platform COGS / Provider Cost</div>
              <div className="text-2xl font-bold text-slate-300 mt-1">
                ${usageDashboard?.total_provider_cost !== undefined ? usageDashboard.total_provider_cost.toFixed(2) : 'N/A'}
              </div>
              <div className="text-xs text-slate-400 mt-1">
                Wholesale upstream provider charges
              </div>
            </div>
          </div>

          {/* Usage Metering Breakdown Cards */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6">
            <h3 className="text-base font-bold text-white mb-4">Included Allowances & Real-Time Metering</h3>

            <div className="space-y-6">
              {usageDashboard?.categories &&
                Object.entries(usageDashboard.categories).map(([key, cat]) => {
                  const isExceeded = cat.overage_units > 0;
                  return (
                    <div key={key} className="space-y-2">
                      <div className="flex items-center justify-between text-sm">
                        <div className="flex items-center gap-2">
                          <span className="font-semibold text-white">{cat.label}</span>
                          <span className="text-xs text-slate-400">({cat.unit})</span>
                          {isExceeded && (
                            <span className="text-[10px] bg-red-500/20 text-red-400 px-2 py-0.5 rounded font-bold">
                              Overage Active
                            </span>
                          )}
                        </div>
                        <div className="text-xs text-slate-300">
                          <span className="font-bold text-white">{cat.used_units.toLocaleString()}</span> /{' '}
                          {cat.included_allowance.toLocaleString()} {cat.unit} (
                          {cat.percent_used.toFixed(1)}%)
                        </div>
                      </div>

                      {/* Progress bar */}
                      <div className="w-full bg-slate-800 rounded-full h-2.5 overflow-hidden">
                        <div
                          className={`h-2.5 rounded-full transition-all duration-500 ${
                            isExceeded
                              ? 'bg-red-500'
                              : cat.percent_used > 80
                              ? 'bg-amber-500'
                              : 'bg-blue-500'
                          }`}
                          style={{ width: `${Math.min(cat.percent_used, 100)}%` }}
                        />
                      </div>

                      <div className="flex items-center justify-between text-[11px] text-slate-400">
                        <span>
                          Remaining Allowance: <span className="text-slate-200">{cat.remaining_allowance.toLocaleString()} {cat.unit}</span>
                        </span>
                        <span>
                          Overage: <span className="text-amber-400">{cat.overage_units.toLocaleString()} {cat.unit}</span> (${cat.overage_charge.toFixed(2)})
                        </span>
                      </div>
                    </div>
                  );
                })}
            </div>
          </div>

          {/* Settled Usage Records Table */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6">
            <h3 className="text-base font-bold text-white mb-4">Recent Settled Metering Records</h3>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs text-slate-300">
                <thead className="bg-slate-800/60 uppercase text-[10px] font-semibold text-slate-400">
                  <tr>
                    <th className="px-4 py-3">Record ID</th>
                    <th className="px-4 py-3">Meter Type</th>
                    <th className="px-4 py-3">Consumed Units</th>
                    <th className="px-4 py-3">Tenant Charge</th>
                    <th className="px-4 py-3">Provider Cost</th>
                    <th className="px-4 py-3">Timestamp</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800">
                  {usageRecords.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="px-4 py-6 text-center text-slate-500">
                        No settled usage records in the current period.
                      </td>
                    </tr>
                  ) : (
                    usageRecords.map((r) => (
                      <tr key={r.id} className="hover:bg-slate-800/30">
                        <td className="px-4 py-3 font-mono text-slate-400">{r.record_id.slice(0, 8)}...</td>
                        <td className="px-4 py-3 font-semibold text-white capitalize">
                          {r.meter_type.replace(/_/g, ' ')}
                        </td>
                        <td className="px-4 py-3">{r.units_consumed}</td>
                        <td className="px-4 py-3 text-emerald-400 font-semibold">
                          ${r.tenant_charge_amount.toFixed(4)}
                        </td>
                        <td className="px-4 py-3 text-slate-400 font-mono">
                          ${r.provider_cost_amount.toFixed(4)}
                        </td>
                        <td className="px-4 py-3 text-slate-400">
                          {new Date(r.settled_at).toLocaleString()}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 3: Credit Wallet & Immutable Ledger */}
      {activeTab === 'wallet' && (
        <div className="space-y-6">
          {/* Wallet Cards */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div className="bg-slate-900 border border-slate-800 p-6 rounded-2xl">
              <div className="text-xs text-slate-400 font-medium">Total Credit Balance</div>
              <div className="text-3xl font-extrabold text-white mt-2">
                ${wallet ? wallet.balance.toFixed(2) : '0.00'}
              </div>
              <div className="text-xs text-slate-400 mt-2">
                Total available funds including holds
              </div>
            </div>

            <div className="bg-slate-900 border border-slate-800 p-6 rounded-2xl">
              <div className="text-xs text-slate-400 font-medium">Reserved Usage Hold</div>
              <div className="text-3xl font-extrabold text-amber-400 mt-2">
                ${wallet ? wallet.reserved_balance.toFixed(2) : '0.00'}
              </div>
              <div className="text-xs text-slate-400 mt-2">
                Active holds for in-flight AI calls & batch actions
              </div>
            </div>

            <div className="bg-slate-900 border border-slate-800 p-6 rounded-2xl">
              <div className="text-xs text-slate-400 font-medium">Available Unencumbered Balance</div>
              <div className="text-3xl font-extrabold text-emerald-400 mt-2">
                ${wallet ? wallet.available_balance.toFixed(2) : '0.00'}
              </div>
              <div className="mt-3">
                <button
                  onClick={() => setIsRechargeModalOpen(true)}
                  className="px-4 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-semibold shadow transition"
                >
                  Top Up Credits
                </button>
              </div>
            </div>
          </div>

          {/* Ledger Verification Action & Banner */}
          <div className="bg-slate-900 border border-slate-800 p-5 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-purple-600/20 text-purple-400 rounded-lg border border-purple-500/20">
                <Lock className="w-5 h-5" />
              </div>
              <div>
                <h4 className="text-sm font-bold text-white">Immutable Credit Ledger Audit</h4>
                <p className="text-xs text-slate-400">
                  Every debit, hold, release, and credit recharge is stored in an immutable, cryptographically verifiable ledger.
                </p>
              </div>
            </div>

            <div className="flex items-center gap-3">
              {integrityReport && (
                <div
                  className={`text-xs px-3 py-1.5 rounded-lg border flex items-center gap-1.5 font-medium ${
                    integrityReport.is_valid
                      ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30'
                      : 'bg-red-500/10 text-red-400 border-red-500/30'
                  }`}
                >
                  {integrityReport.is_valid ? (
                    <CheckCircle2 className="w-3.5 h-3.5" />
                  ) : (
                    <AlertTriangle className="w-3.5 h-3.5" />
                  )}
                  {integrityReport.is_valid
                    ? `Integrity Verified: ${integrityReport.records_count} records match balance ($${integrityReport.calculated_ledger_sum.toFixed(2)})`
                    : 'Mathematical discrepancy detected!'}
                </div>
              )}
              <button
                onClick={handleVerifyIntegrity}
                disabled={verifyIntegrityMutation.isPending}
                className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-lg text-xs font-medium transition flex items-center gap-1"
              >
                <RefreshCw className={`w-3.5 h-3.5 ${verifyIntegrityMutation.isPending ? 'animate-spin' : ''}`} />
                Verify Ledger Integrity
              </button>
            </div>
          </div>

          {/* Immutable Ledger Table */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6">
            <h3 className="text-base font-bold text-white mb-4">Credit Ledger Transactions</h3>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs text-slate-300">
                <thead className="bg-slate-800/60 uppercase text-[10px] font-semibold text-slate-400">
                  <tr>
                    <th className="px-4 py-3">Timestamp</th>
                    <th className="px-4 py-3">Type</th>
                    <th className="px-4 py-3">Amount</th>
                    <th className="px-4 py-3">Balance After</th>
                    <th className="px-4 py-3">Description</th>
                    <th className="px-4 py-3">Reference / Entry ID</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800">
                  {ledger.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="px-4 py-6 text-center text-slate-500">
                        No ledger transactions found.
                      </td>
                    </tr>
                  ) : (
                    ledger.map((e) => {
                      const isPositive = e.amount > 0;
                      return (
                        <tr key={e.id} className="hover:bg-slate-800/30">
                          <td className="px-4 py-3 text-slate-400">
                            {new Date(e.created_at).toLocaleString()}
                          </td>
                          <td className="px-4 py-3 font-semibold">
                            <span
                              className={`px-2 py-0.5 rounded text-[10px] uppercase font-bold ${
                                e.entry_type === 'recharge'
                                  ? 'bg-emerald-500/20 text-emerald-400'
                                  : e.entry_type === 'usage_settle'
                                  ? 'bg-amber-500/20 text-amber-400'
                                  : 'bg-blue-500/20 text-blue-400'
                              }`}
                            >
                              {e.entry_type.replace(/_/g, ' ')}
                            </span>
                          </td>
                          <td
                            className={`px-4 py-3 font-mono font-bold ${
                              isPositive ? 'text-emerald-400' : 'text-red-400'
                            }`}
                          >
                            {isPositive ? `+${e.amount.toFixed(2)}` : e.amount.toFixed(2)}
                          </td>
                          <td className="px-4 py-3 font-mono font-semibold text-white">
                            ${e.balance_after.toFixed(2)}
                          </td>
                          <td className="px-4 py-3 text-slate-300">{e.description}</td>
                          <td className="px-4 py-3 font-mono text-slate-500">
                            {e.reference_id || e.entry_id.slice(0, 8)}
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 4: Invoices & Tax Info */}
      {activeTab === 'invoices' && (
        <div className="space-y-6">
          {/* Billing Contact & Tax Details Card */}
          <div className="bg-slate-900 border border-slate-800 p-6 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <h3 className="text-base font-bold text-white">Billing Contact & Tax Configuration</h3>
              <p className="text-xs text-slate-400 mt-1">
                Details used for generating official compliance tax invoices (GSTIN / VAT).
              </p>
              <div className="mt-3 flex flex-wrap gap-4 text-xs text-slate-300">
                <div>
                  <span className="text-slate-400">Contact:</span>{' '}
                  <span className="font-medium text-white">{subscription?.billing_contact_name || 'Not Configured'}</span>
                </div>
                <div>
                  <span className="text-slate-400">Email:</span>{' '}
                  <span className="font-medium text-white">{subscription?.billing_contact_email || 'Not Configured'}</span>
                </div>
                <div>
                  <span className="text-slate-400">Tax ID:</span>{' '}
                  <span className="font-medium text-white">{subscription?.billing_tax_id || 'None'}</span>
                </div>
              </div>
            </div>

            <button
              onClick={() => {
                setContactForm({
                  billing_contact_name: subscription?.billing_contact_name || '',
                  billing_contact_email: subscription?.billing_contact_email || '',
                  billing_tax_id: subscription?.billing_tax_id || '',
                  billing_address: subscription?.billing_address || '',
                });
                setIsContactModalOpen(true);
              }}
              className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-semibold border border-slate-700 transition shrink-0"
            >
              Update Billing Details
            </button>
          </div>

          {/* Invoices History Table */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6">
            <h3 className="text-base font-bold text-white mb-4">Subscription & Usage Invoices</h3>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs text-slate-300">
                <thead className="bg-slate-800/60 uppercase text-[10px] font-semibold text-slate-400">
                  <tr>
                    <th className="px-4 py-3">Invoice Number</th>
                    <th className="px-4 py-3">Type</th>
                    <th className="px-4 py-3">Billing Period</th>
                    <th className="px-4 py-3">Subtotal</th>
                    <th className="px-4 py-3">Tax</th>
                    <th className="px-4 py-3">Total Amount</th>
                    <th className="px-4 py-3">Status</th>
                    <th className="px-4 py-3">Razorpay ID</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800">
                  {invoices.length === 0 ? (
                    <tr>
                      <td colSpan={8} className="px-4 py-6 text-center text-slate-500">
                        No subscription invoices issued yet.
                      </td>
                    </tr>
                  ) : (
                    invoices.map((inv) => (
                      <tr key={inv.id} className="hover:bg-slate-800/30">
                        <td className="px-4 py-3 font-mono font-semibold text-white">
                          {inv.invoice_number}
                        </td>
                        <td className="px-4 py-3 capitalize text-slate-300">
                          {inv.invoice_type.replace(/_/g, ' ')}
                        </td>
                        <td className="px-4 py-3 text-slate-400">
                          {inv.billing_period_start
                            ? `${new Date(inv.billing_period_start).toLocaleDateString()} - ${new Date(
                                inv.billing_period_end || ''
                              ).toLocaleDateString()}`
                            : 'N/A'}
                        </td>
                        <td className="px-4 py-3">${inv.subtotal.toFixed(2)}</td>
                        <td className="px-4 py-3">${inv.tax_amount.toFixed(2)}</td>
                        <td className="px-4 py-3 font-bold text-white">
                          ${inv.total_amount.toFixed(2)}
                        </td>
                        <td className="px-4 py-3">
                          <span
                            className={`px-2 py-0.5 rounded text-[10px] uppercase font-bold ${
                              inv.status === 'paid'
                                ? 'bg-emerald-500/20 text-emerald-400'
                                : inv.status === 'past_due'
                                ? 'bg-red-500/20 text-red-400'
                                : 'bg-slate-700 text-slate-300'
                            }`}
                          >
                            {inv.status}
                          </span>
                        </td>
                        <td className="px-4 py-3 font-mono text-slate-500">
                          {inv.razorpay_payment_id || '—'}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 5: Cost Protection Caps */}
      {activeTab === 'cost-protection' && (
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-6">
          <div className="border-b border-slate-800 pb-4">
            <h3 className="text-base font-bold text-white">Cost Protection & Spending Guardrails</h3>
            <p className="text-xs text-slate-400 mt-1">
              Configure daily and monthly spend caps to prevent runaway AI or telephony charges.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">
                Daily Spending Cap (USD)
              </label>
              <input
                type="number"
                value={costProtection?.daily_spend_limit ?? 50}
                onChange={(e) =>
                  updateCostMutation.mutate({ daily_spend_limit: parseFloat(e.target.value) || 0 })
                }
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-blue-500"
              />
              <p className="text-[11px] text-slate-400 mt-1">
                Hard cap for all usage charges across a 24-hour window.
              </p>
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">
                Monthly Spending Cap (USD)
              </label>
              <input
                type="number"
                value={costProtection?.monthly_spend_limit ?? 500}
                onChange={(e) =>
                  updateCostMutation.mutate({ monthly_spend_limit: parseFloat(e.target.value) || 0 })
                }
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-blue-500"
              />
              <p className="text-[11px] text-slate-400 mt-1">
                Total monthly overage allowance before outbound calls and agents are automatically paused.
              </p>
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">
                AI Provider Specific Spend Cap (USD)
              </label>
              <input
                type="number"
                value={costProtection?.ai_provider_spend_limit ?? 300}
                onChange={(e) =>
                  updateCostMutation.mutate({ ai_provider_spend_limit: parseFloat(e.target.value) || 0 })
                }
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-blue-500"
              />
              <p className="text-[11px] text-slate-400 mt-1">
                Limits maximum token & LLM consumption across Gemini, OpenAI, and Claude models.
              </p>
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">
                Low Balance Alert Threshold (USD)
              </label>
              <input
                type="number"
                value={costProtection?.low_balance_alert_threshold ?? 10}
                onChange={(e) =>
                  updateCostMutation.mutate({ low_balance_alert_threshold: parseFloat(e.target.value) || 0 })
                }
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-blue-500"
              />
              <p className="text-[11px] text-slate-400 mt-1">
                Sends proactive email alerts when credit balance dips below this amount.
              </p>
            </div>
          </div>

          <div className="pt-4 border-t border-slate-800 space-y-4">
            <div className="flex items-center justify-between p-4 bg-slate-800/40 rounded-xl border border-slate-700/60">
              <div>
                <h4 className="text-sm font-semibold text-white">Auto-Pause Overages on Cap Breach</h4>
                <p className="text-xs text-slate-400 mt-0.5">
                  Temporarily stops paid AI requests if the daily or monthly limit is breached.
                </p>
              </div>
              <input
                type="checkbox"
                checked={costProtection?.is_auto_pause_enabled ?? true}
                onChange={(e) =>
                  updateCostMutation.mutate({ is_auto_pause_enabled: e.target.checked })
                }
                className="w-5 h-5 accent-blue-600 rounded cursor-pointer"
              />
            </div>

            <div className="flex items-center justify-between p-4 bg-slate-800/40 rounded-xl border border-slate-700/60">
              <div>
                <div className="flex items-center gap-2">
                  <h4 className="text-sm font-semibold text-white">Allow Unlimited AI Voice Calling</h4>
                  <span className="text-[10px] bg-amber-500/20 text-amber-400 px-2 py-0.5 rounded font-bold uppercase">
                    Admin Approval Rule
                  </span>
                </div>
                <p className="text-xs text-slate-400 mt-0.5">
                  Requires explicit platform authorization. When disabled, single AI calls are capped at 120 minutes.
                </p>
              </div>
              <input
                type="checkbox"
                checked={costProtection?.is_ai_calling_unlimited_allowed ?? false}
                onChange={(e) =>
                  updateCostMutation.mutate({ is_ai_calling_unlimited_allowed: e.target.checked })
                }
                className="w-5 h-5 accent-blue-600 rounded cursor-pointer"
              />
            </div>
          </div>
        </div>
      )}

      {/* MODAL 1: Upgrade / Switch Plan Confirmation with Proration */}
      {upgradePlanModal && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl">
            <h3 className="text-lg font-bold text-white">Confirm Plan Selection</h3>
            <p className="text-xs text-slate-300">
              You are selecting the <span className="font-bold text-blue-400">{upgradePlanModal.name}</span> plan on a{' '}
              <span className="font-bold capitalize">{billingCycle}</span> billing cycle.
            </p>

            <div className="bg-slate-800/60 p-4 rounded-xl border border-slate-700 text-xs space-y-2">
              <div className="flex justify-between">
                <span className="text-slate-400">New Price:</span>
                <span className="text-white font-semibold">
                  ${billingCycle === 'annual' ? upgradePlanModal.price_annual.toFixed(2) : upgradePlanModal.price_monthly.toFixed(2)} / {billingCycle === 'annual' ? 'year' : 'month'}
                </span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-400">Team Seats:</span>
                <span className="text-white font-semibold">{upgradePlanModal.seat_limit}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-400">Included Tokens:</span>
                <span className="text-white font-semibold">{upgradePlanModal.allowances?.llm_tokens?.toLocaleString()}</span>
              </div>
            </div>

            <div className="flex items-center gap-2 pt-2">
              <input
                type="checkbox"
                id="prorate"
                checked={prorateUpgrade}
                onChange={(e) => setProrateUpgrade(e.target.checked)}
                className="w-4 h-4 accent-blue-600 rounded cursor-pointer"
              />
              <label htmlFor="prorate" className="text-xs text-slate-300 cursor-pointer">
                Apply prorated credit for remaining days in current billing cycle
              </label>
            </div>

            <div className="flex items-center justify-end gap-3 pt-3">
              <button
                onClick={() => setUpgradePlanModal(null)}
                className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition"
              >
                Cancel
              </button>
              <button
                onClick={handleUpgradeSubmit}
                disabled={subscribeMutation.isPending}
                className="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-semibold shadow transition"
              >
                {subscribeMutation.isPending ? 'Processing...' : 'Confirm Plan Update'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 2: Credit Wallet Top-Up */}
      {isRechargeModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl">
            <h3 className="text-lg font-bold text-white">Top Up Credit Wallet</h3>
            <p className="text-xs text-slate-400">
              Credits are consumed for AI voice calling minutes, LLM tokens, and WhatsApp template overages.
            </p>

            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-2">Select Amount (USD)</label>
              <div className="grid grid-cols-4 gap-2 mb-3">
                {[25, 50, 100, 250].map((amt) => (
                  <button
                    key={amt}
                    type="button"
                    onClick={() => setRechargeAmount(amt)}
                    className={`py-2 rounded-lg text-xs font-bold border transition ${
                      rechargeAmount === amt
                        ? 'bg-blue-600 text-white border-blue-500'
                        : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-700'
                    }`}
                  >
                    ${amt}
                  </button>
                ))}
              </div>
              <input
                type="number"
                min="5"
                value={rechargeAmount}
                onChange={(e) => setRechargeAmount(parseFloat(e.target.value) || 0)}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-blue-500"
                placeholder="Custom Amount"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">
                Payment Reference ID (Optional)
              </label>
              <input
                type="text"
                value={rechargeRef}
                onChange={(e) => setRechargeRef(e.target.value)}
                placeholder="pay_rzp_..."
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:border-blue-500"
              />
            </div>

            <div className="flex items-center justify-end gap-3 pt-3">
              <button
                onClick={() => setIsRechargeModalOpen(false)}
                className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition"
              >
                Cancel
              </button>
              <button
                onClick={handleRechargeSubmit}
                disabled={rechargeMutation.isPending || rechargeAmount <= 0}
                className="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-semibold shadow transition"
              >
                {rechargeMutation.isPending ? 'Processing...' : `Add $${rechargeAmount.toFixed(2)} Credits`}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 3: Update Billing Details */}
      {isContactModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
          <form
            onSubmit={handleContactSubmit}
            className="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl"
          >
            <h3 className="text-lg font-bold text-white">Update Tax & Billing Details</h3>

            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">Billing Contact Name</label>
              <input
                type="text"
                required
                value={contactForm.billing_contact_name}
                onChange={(e) => setContactForm({ ...contactForm, billing_contact_name: e.target.value })}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:border-blue-500"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">Billing Email Address</label>
              <input
                type="email"
                required
                value={contactForm.billing_contact_email}
                onChange={(e) => setContactForm({ ...contactForm, billing_contact_email: e.target.value })}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:border-blue-500"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">Tax ID / GSTIN / VAT</label>
              <input
                type="text"
                value={contactForm.billing_tax_id}
                onChange={(e) => setContactForm({ ...contactForm, billing_tax_id: e.target.value })}
                placeholder="27AABCU9603R1ZM"
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:border-blue-500"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">Billing Address</label>
              <textarea
                rows={2}
                value={contactForm.billing_address}
                onChange={(e) => setContactForm({ ...contactForm, billing_address: e.target.value })}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:border-blue-500"
              />
            </div>

            <div className="flex items-center justify-end gap-3 pt-3">
              <button
                type="button"
                onClick={() => setIsContactModalOpen(false)}
                className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={updateContactMutation.isPending}
                className="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-semibold shadow transition"
              >
                {updateContactMutation.isPending ? 'Saving...' : 'Save Changes'}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
};
