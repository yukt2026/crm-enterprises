import React, { useState } from 'react';
import {
  FileText,
  ShieldCheck,
  Calendar,
  Layers,
  Search,
  Download,
  AlertTriangle,
  CheckCircle,
  Truck,
  QrCode,
  Plus,
  RefreshCw,
  Building,
  ArrowRight,
  Clock,
  ExternalLink,
  ChevronRight,
} from 'lucide-react';
import {
  useGstRegistrations,
  useSaveGstRegistration,
  useVerifyGstin,
  useGstInvoices,
  useCreateGstInvoice,
  useFinalizeGstInvoice,
  useCreateGstCreditNote,
  useGenerateEInvoice,
  useCancelEInvoice,
  useGenerateEWayBill,
  useUpdateEWayBillVehicle,
  useCancelEWayBill,
  useGstTaxSummary,
  useGstr1SalesRegister,
  useHsnSummary,
  useComplianceReminders,
  useCompleteComplianceReminder,
  useCreateComplianceReminder,
  useComplianceDocuments,
  useStoreComplianceDocument,
} from '../../hooks/useGstCompliance';

export const GstComplianceHubView: React.FC = () => {
  const [activeTab, setActiveTab] = useState<
    'overview' | 'invoices' | 'einvoice' | 'verify' | 'reports' | 'calendar' | 'vault' | 'settings'
  >('overview');

  // Queries
  const { data: taxSummary, isLoading: summaryLoading } = useGstTaxSummary();
  const { data: invoicesData, isLoading: invoicesLoading } = useGstInvoices();
  const { data: registrations } = useGstRegistrations();
  const { data: reminders } = useComplianceReminders();
  const { data: documents } = useComplianceDocuments();
  const { data: salesRegister } = useGstr1SalesRegister();
  const { data: hsnData } = useHsnSummary();

  // Mutations
  const verifyGstinMut = useVerifyGstin();
  const createInvoiceMut = useCreateGstInvoice();
  const finalizeInvoiceMut = useFinalizeGstInvoice();
  const creditNoteMut = useCreateGstCreditNote();
  const einvoiceMut = useGenerateEInvoice();
  const cancelEinvoiceMut = useCancelEInvoice();
  const ewayBillMut = useGenerateEWayBill();
  const updateVehicleMut = useUpdateEWayBillVehicle();
  const cancelEwbMut = useCancelEWayBill();
  const completeReminderMut = useCompleteComplianceReminder();
  const createReminderMut = useCreateComplianceReminder();
  const saveRegMut = useSaveGstRegistration();

  // Modals & form state
  const [gstinInput, setGstinInput] = useState('');
  const [verificationResult, setVerificationResult] = useState<any>(null);

  // New Invoice Modal
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [newInvDocType, setNewInvDocType] = useState('tax_invoice');
  const [newInvBuyerName, setNewInvBuyerName] = useState('');
  const [newInvBuyerGstin, setNewInvBuyerGstin] = useState('');
  const [newInvPos, setNewInvPos] = useState('27');
  const [newInvItemName, setNewInvItemName] = useState('Enterprise Consulting');
  const [newInvAmount, setNewInvAmount] = useState('100000');
  const [newInvTaxRate, setNewInvTaxRate] = useState('18');
  const [newInvIsService, setNewInvIsService] = useState(true);

  // E-Way Bill Modal
  const [showEwbModal, setShowEwbModal] = useState(false);
  const [selectedInvForEwb, setSelectedInvForEwb] = useState<number | null>(null);
  const [ewbDistance, setEwbDistance] = useState('450');
  const [ewbVehicle, setEwbVehicle] = useState('MH02CB4567');
  const [ewbTransporter, setEwbTransporter] = useState('VRL Logistics');

  // Vehicle Update Modal
  const [showVehicleModal, setShowVehicleModal] = useState(false);
  const [selectedInvForVehicle, setSelectedInvForVehicle] = useState<number | null>(null);
  const [newVehicleNo, setNewVehicleNo] = useState('');

  // New Reminder Modal
  const [showReminderModal, setShowReminderModal] = useState(false);
  const [remTitle, setRemTitle] = useState('');
  const [remType, setRemType] = useState('license_renewal');
  const [remDate, setRemDate] = useState('');

  const handleVerifyGstin = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!gstinInput.trim()) return;
    try {
      const res = await verifyGstinMut.mutateAsync(gstinInput.trim());
      setVerificationResult(res.data);
    } catch (err: any) {
      alert(err.message || 'Verification request failed');
    }
  };

  const handleCreateInvoice = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await createInvoiceMut.mutateAsync({
        document_type: newInvDocType,
        buyer_legal_name: newInvBuyerName || 'Client Corp',
        buyer_gstin: newInvBuyerGstin || null,
        place_of_supply_state_code: newInvPos,
        status: 'draft',
        items: [
          {
            item_name: newInvItemName,
            quantity: 1,
            unit_price: parseFloat(newInvAmount) || 10000,
            tax_rate: parseFloat(newInvTaxRate) || 18,
            is_service: newInvIsService,
            hsn_sac_code: newInvIsService ? '998311' : '8471',
            unit: newInvIsService ? 'HRS' : 'PCS',
          },
        ],
      });
      setShowCreateModal(false);
      alert('GST Invoice document generated successfully in draft status.');
    } catch (err: any) {
      alert(err.message || 'Error generating invoice');
    }
  };

  const handleGenerateEwb = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedInvForEwb) return;
    try {
      await ewayBillMut.mutateAsync({
        id: selectedInvForEwb,
        data: {
          distance_km: parseInt(ewbDistance) || 100,
          vehicle_number: ewbVehicle,
          transporter_name: ewbTransporter,
        },
      });
      setShowEwbModal(false);
      alert('E-Way Bill generated successfully with valid Part A & Part B.');
    } catch (err: any) {
      alert(err.message || 'Error generating E-Way Bill');
    }
  };

  const handleUpdateVehicle = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedInvForVehicle || !newVehicleNo) return;
    try {
      await updateVehicleMut.mutateAsync({
        id: selectedInvForVehicle,
        vehicleNumber: newVehicleNo,
      });
      setShowVehicleModal(false);
      alert('E-Way Bill Part B vehicle update recorded successfully.');
    } catch (err: any) {
      alert(err.message || 'Error updating vehicle');
    }
  };

  const invoices = invoicesData?.data || [];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
        <div>
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
              Indian GST Invoicing & Compliance Engine
            </h1>
            <span className="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
              Prompt 41 Compliance Gate
            </span>
          </div>
          <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Real working business accounting support: Intra/Inter-state tax determination, E-Invoice (IRN), E-Way Bills, GSTR-1 Sales Registers, and Statutory Compliance Calendar.
          </p>
        </div>
        <div className="flex items-center gap-3">
          <button
            onClick={() => setShowCreateModal(true)}
            className="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium shadow-sm transition-colors"
          >
            <Plus className="w-4 h-4" />
            Create GST Document
          </button>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex border-b border-gray-200 dark:border-gray-700 overflow-x-auto gap-2">
        {[
          { key: 'overview', label: 'Overview & Tax Summary', icon: Layers },
          { key: 'invoices', label: 'GST Invoices & Documents', icon: FileText },
          { key: 'einvoice', label: 'E-Invoice (IRN) & E-Way Bill', icon: QrCode },
          { key: 'verify', label: 'GSTIN Verification Tool', icon: Search },
          { key: 'reports', label: 'GSTR-1 & HSN Registers', icon: Download },
          { key: 'calendar', label: 'Compliance Calendar', icon: Calendar },
          { key: 'vault', label: 'Document Vault', icon: ShieldCheck },
          { key: 'settings', label: 'GST Registrations & Multi-State', icon: Building },
        ].map(({ key, label, icon: Icon }) => (
          <button
            key={key}
            onClick={() => setActiveTab(key as any)}
            className={`flex items-center gap-2 py-3 px-4 border-b-2 text-sm font-medium whitespace-nowrap transition-colors ${
              activeTab === key
                ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
            }`}
          >
            <Icon className="w-4 h-4" />
            {label}
          </button>
        ))}
      </div>

      {/* TAB 1: OVERVIEW & TAX SUMMARY */}
      {activeTab === 'overview' && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
              <span className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Outward Taxable Value</span>
              <p className="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                ₹{(taxSummary?.outward_supplies?.taxable_value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
              </p>
              <span className="text-xs text-gray-400 mt-2 block">Total invoiced outward sales demand</span>
            </div>

            <div className="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
              <span className="text-xs font-semibold text-blue-600 uppercase tracking-wider">Gross Output Tax</span>
              <p className="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">
                ₹{(taxSummary?.outward_supplies?.total_output_tax || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
              </p>
              <div className="flex gap-2 text-xs text-gray-500 mt-2">
                <span>CGST: ₹{(taxSummary?.outward_supplies?.cgst || 0).toLocaleString()}</span>
                <span>•</span>
                <span>SGST: ₹{(taxSummary?.outward_supplies?.sgst || 0).toLocaleString()}</span>
                <span>•</span>
                <span>IGST: ₹{(taxSummary?.outward_supplies?.igst || 0).toLocaleString()}</span>
              </div>
            </div>

            <div className="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
              <span className="text-xs font-semibold text-emerald-600 uppercase tracking-wider">Input Tax Credit (ITC)</span>
              <p className="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                ₹{(taxSummary?.inward_supplies_itc?.total_itc_available || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
              </p>
              <span className="text-xs text-gray-400 mt-2 block">Reconciled inward purchase bills (GSTR-2B)</span>
            </div>

            <div className="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
              <span className="text-xs font-semibold text-purple-600 uppercase tracking-wider">Net Tax Payable (Cash Ledger)</span>
              <p className="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-1">
                ₹{(taxSummary?.net_tax_liability?.total_net_payable || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
              </p>
              <span className="text-xs text-gray-400 mt-2 block">Liability remaining after eligible ITC offset</span>
            </div>
          </div>

          <div className="bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-xl p-4 flex items-start gap-3">
            <AlertTriangle className="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
            <div className="text-sm text-amber-800 dark:text-amber-300">
              <span className="font-semibold">Compliance Notice:</span>{' '}
              {taxSummary?.compliance_disclaimer ||
                'Internal business reconciliation report for accounting and audit reference. Not an official GSTN filing receipt.'}
            </div>
          </div>
        </div>
      )}

      {/* TAB 2: INVOICES & DOCUMENTS */}
      {activeTab === 'invoices' && (
        <div className="space-y-4">
          <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
            <div className="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
              <h3 className="font-semibold text-gray-900 dark:text-white">GST Invoices & Documents Register</h3>
              <span className="text-xs text-gray-500">Showing {invoices.length} documents</span>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-sm text-left">
                <thead className="bg-gray-50 dark:bg-gray-900/50 text-gray-600 dark:text-gray-400 uppercase text-xs">
                  <tr>
                    <th className="px-4 py-3">Doc #</th>
                    <th className="px-4 py-3">Type</th>
                    <th className="px-4 py-3">Buyer / Recipient</th>
                    <th className="px-4 py-3">Supply Type & POS</th>
                    <th className="px-4 py-3">Tax Breakdown</th>
                    <th className="px-4 py-3">Total Amount</th>
                    <th className="px-4 py-3">Status</th>
                    <th className="px-4 py-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                  {invoices.length === 0 ? (
                    <tr>
                      <td colSpan={8} className="text-center py-8 text-gray-500">
                        No GST invoices found. Click "Create GST Document" to generate one.
                      </td>
                    </tr>
                  ) : (
                    invoices.map((inv: any) => (
                      <tr key={inv.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td className="px-4 py-3 font-semibold text-indigo-600 dark:text-indigo-400">
                          {inv.invoice_number}
                        </td>
                        <td className="px-4 py-3">
                          <span className="capitalize px-2 py-0.5 rounded text-xs bg-gray-100 dark:bg-gray-700 font-mono">
                            {inv.document_type || 'tax_invoice'}
                          </span>
                        </td>
                        <td className="px-4 py-3">
                          <div className="font-medium text-gray-900 dark:text-white">{inv.buyer_legal_name || inv.billing_name}</div>
                          {inv.buyer_gstin && (
                            <span className="text-xs text-gray-500 font-mono block">GSTIN: {inv.buyer_gstin}</span>
                          )}
                        </td>
                        <td className="px-4 py-3">
                          <span className="text-xs capitalize font-medium block">{inv.supply_type || 'intra_state'}</span>
                          <span className="text-xs text-gray-500">POS: State {inv.place_of_supply_state_code || '27'}</span>
                        </td>
                        <td className="px-4 py-3 text-xs space-y-0.5">
                          {inv.cgst_amount > 0 && <div>CGST: ₹{parseFloat(inv.cgst_amount).toLocaleString()}</div>}
                          {inv.sgst_amount > 0 && <div>SGST: ₹{parseFloat(inv.sgst_amount).toLocaleString()}</div>}
                          {inv.igst_amount > 0 && <div>IGST: ₹{parseFloat(inv.igst_amount).toLocaleString()}</div>}
                          <div className="font-semibold text-gray-700 dark:text-gray-300">
                            Total Tax: ₹{parseFloat(inv.tax_amount || 0).toLocaleString()}
                          </div>
                        </td>
                        <td className="px-4 py-3 font-bold text-gray-900 dark:text-white">
                          ₹{parseFloat(inv.total_amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                        </td>
                        <td className="px-4 py-3">
                          <span
                            className={`px-2 py-0.5 rounded-full text-xs font-semibold ${
                              inv.status === 'issued'
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400'
                                : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
                            }`}
                          >
                            {inv.status}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-right space-x-2">
                          {inv.status === 'draft' && (
                            <button
                              onClick={() => finalizeInvoiceMut.mutate(inv.id)}
                              className="px-2 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-medium"
                            >
                              Finalize
                            </button>
                          )}
                          {inv.status === 'issued' && (
                            <>
                              <button
                                onClick={() => {
                                  setSelectedInvForEwb(inv.id);
                                  setShowEwbModal(true);
                                }}
                                className="px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs font-medium"
                              >
                                E-Way Bill
                              </button>
                              <button
                                onClick={() => einvoiceMut.mutate(inv.id)}
                                disabled={inv.einvoice_status === 'generated'}
                                className="px-2 py-1 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded text-xs font-medium"
                              >
                                {inv.einvoice_status === 'generated' ? 'IRN Done' : 'IRN'}
                              </button>
                            </>
                          )}
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

      {/* TAB 3: E-INVOICE & E-WAY BILL */}
      {activeTab === 'einvoice' && (
        <div className="space-y-6">
          <div className="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-2">
              Invoice Registration Portal (IRP) & E-Way Bill Status
            </h3>
            <p className="text-sm text-gray-500 mb-6">
              Track government-issued IRN hashes, signed QR payloads, and Part A/Part B transport transit validity.
            </p>

            <div className="space-y-4">
              {invoices.filter((i: any) => i.irn || i.eway_bill_number).length === 0 ? (
                <div className="text-center py-8 text-gray-400">
                  No invoices with generated E-Invoice IRN or E-Way Bills yet. Generate one from the Invoices tab.
                </div>
              ) : (
                invoices
                  .filter((i: any) => i.irn || i.eway_bill_number)
                  .map((inv: any) => (
                    <div
                      key={inv.id}
                      className="p-4 border border-gray-200 dark:border-gray-700 rounded-lg flex flex-col md:flex-row justify-between gap-4"
                    >
                      <div className="space-y-1">
                        <div className="flex items-center gap-2">
                          <span className="font-bold text-gray-900 dark:text-white">{inv.invoice_number}</span>
                          <span className="text-xs px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 font-mono">
                            {inv.buyer_legal_name}
                          </span>
                        </div>
                        {inv.irn && (
                          <div className="text-xs font-mono text-indigo-600 dark:text-indigo-400 truncate max-w-xl">
                            IRN: {inv.irn}
                          </div>
                        )}
                        {inv.ack_number && (
                          <div className="text-xs text-gray-500">
                            Ack No: {inv.ack_number} | Ack Date: {inv.ack_date}
                          </div>
                        )}
                        {inv.eway_bill_number && (
                          <div className="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                            E-Way Bill: {inv.eway_bill_number} (Vehicle: {inv.vehicle_number || 'N/A'}, Status: {inv.eway_bill_status})
                          </div>
                        )}
                      </div>

                      <div className="flex items-center gap-2 shrink-0">
                        {inv.eway_bill_number && inv.eway_bill_status !== 'cancelled' && (
                          <button
                            onClick={() => {
                              setSelectedInvForVehicle(inv.id);
                              setShowVehicleModal(true);
                            }}
                            className="px-3 py-1.5 text-xs font-medium bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded"
                          >
                            Update Vehicle
                          </button>
                        )}
                        {inv.irn && inv.einvoice_status === 'generated' && (
                          <button
                            onClick={() => {
                              const reason = prompt('Enter cancellation reason (within 24 hours):');
                              if (reason) cancelEinvoiceMut.mutate({ id: inv.id, reason });
                            }}
                            className="px-3 py-1.5 text-xs font-medium bg-red-100 hover:bg-red-200 text-red-700 rounded"
                          >
                            Cancel IRN
                          </button>
                        )}
                      </div>
                    </div>
                  ))
              )}
            </div>
          </div>
        </div>
      )}

      {/* TAB 4: GSTIN VERIFICATION TOOL */}
      {activeTab === 'verify' && (
        <div className="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm max-w-3xl">
          <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-2">
            GSTIN Structure & Taxpayer Verification
          </h3>
          <p className="text-sm text-gray-500 mb-6">
            Validate 15-character Indian Goods & Services Tax Identification Numbers, decode state jurisdictions, and verify active registration status.
          </p>

          <form onSubmit={handleVerifyGstin} className="flex gap-3 mb-6">
            <input
              type="text"
              placeholder="e.g. 27AABCA1234D1Z5"
              value={gstinInput}
              onChange={(e) => setGstinInput(e.target.value.toUpperCase())}
              maxLength={15}
              className="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-transparent font-mono tracking-wider"
            />
            <button
              type="submit"
              disabled={verifyGstinMut.isPending}
              className="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium flex items-center gap-2"
            >
              {verifyGstinMut.isPending ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Search className="w-4 h-4" />}
              Verify GSTIN
            </button>
          </form>

          {verificationResult && (
            <div
              className={`p-5 rounded-xl border ${
                verificationResult.is_verified
                  ? 'bg-emerald-50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800'
                  : 'bg-red-50 dark:bg-red-950/20 border-red-200 dark:border-red-800'
              }`}
            >
              <div className="flex items-center gap-2 mb-3">
                {verificationResult.is_verified ? (
                  <CheckCircle className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                ) : (
                  <AlertTriangle className="w-5 h-5 text-red-600 dark:text-red-400" />
                )}
                <span className="font-bold text-sm text-gray-900 dark:text-white">
                  {verificationResult.is_verified ? 'Verified Taxpayer Entity' : 'Invalid or Unverified GSTIN'}
                </span>
              </div>

              {verificationResult.is_verified ? (
                <div className="grid grid-cols-2 gap-4 text-sm">
                  <div>
                    <span className="text-xs text-gray-500 block">Legal Name</span>
                    <span className="font-semibold">{verificationResult.legal_name || 'N/A'}</span>
                  </div>
                  <div>
                    <span className="text-xs text-gray-500 block">Trade Name</span>
                    <span className="font-semibold">{verificationResult.trade_name || 'N/A'}</span>
                  </div>
                  <div>
                    <span className="text-xs text-gray-500 block">Jurisdiction State</span>
                    <span className="font-semibold">{verificationResult.state_name} (Code: {verificationResult.state_code})</span>
                  </div>
                  <div>
                    <span className="text-xs text-gray-500 block">Registration Status</span>
                    <span className="capitalize font-semibold text-emerald-600">{verificationResult.registration_status}</span>
                  </div>
                  <div className="col-span-2">
                    <span className="text-xs text-gray-500 block">Registered Business Address</span>
                    <span className="text-gray-700 dark:text-gray-300">{verificationResult.registered_address}</span>
                  </div>
                </div>
              ) : (
                <p className="text-xs text-red-600 dark:text-red-400">
                  GSTIN format check failed or checksum did not match standard 15-character alphanumeric format.
                </p>
              )}
            </div>
          )}
        </div>
      )}

      {/* TAB 5: GSTR-1 & HSN REGISTERS */}
      {activeTab === 'reports' && (
        <div className="space-y-6">
          <div className="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div className="flex justify-between items-center mb-4">
              <div>
                <h3 className="text-lg font-bold text-gray-900 dark:text-white">
                  GSTR-1 Outward Supplies Register
                </h3>
                <p className="text-sm text-gray-500">
                  Separated tables for B2B Tax Invoices, B2C Large, B2C Small, and Credit/Debit Notes (CDNR).
                </p>
              </div>
              <a
                href="/api/v1/crm/gst/reports/export-csv"
                target="_blank"
                rel="noreferrer"
                className="px-4 py-2 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-medium rounded-lg inline-flex items-center gap-2"
              >
                <Download className="w-4 h-4" />
                Export CSV
              </a>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
              <div className="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
                <span className="text-xs text-gray-500 uppercase">B2B Invoices</span>
                <p className="text-xl font-bold mt-1">{salesRegister?.b2b_invoices?.length || 0} Records</p>
              </div>
              <div className="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
                <span className="text-xs text-gray-500 uppercase">Credit / Debit Notes</span>
                <p className="text-xl font-bold mt-1">{salesRegister?.credit_debit_notes?.length || 0} Records</p>
              </div>
              <div className="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
                <span className="text-xs text-gray-500 uppercase">Total Tax Demanded</span>
                <p className="text-xl font-bold text-indigo-600 mt-1">
                  ₹{(salesRegister?.total_tax_value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                </p>
              </div>
            </div>

            <h4 className="font-semibold text-sm text-gray-800 dark:text-gray-200 mb-3">HSN/SAC Summary Breakdown</h4>
            <div className="overflow-x-auto">
              <table className="w-full text-xs text-left">
                <thead className="bg-gray-50 dark:bg-gray-900/50 uppercase">
                  <tr>
                    <th className="px-3 py-2">HSN/SAC</th>
                    <th className="px-3 py-2">Description</th>
                    <th className="px-3 py-2">UQC</th>
                    <th className="px-3 py-2">Total Qty</th>
                    <th className="px-3 py-2">Taxable Value</th>
                    <th className="px-3 py-2">CGST</th>
                    <th className="px-3 py-2">SGST</th>
                    <th className="px-3 py-2">IGST</th>
                    <th className="px-3 py-2">Total Value</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                  {(!hsnData || hsnData.length === 0) ? (
                    <tr>
                      <td colSpan={9} className="text-center py-4 text-gray-500">
                        No HSN summary data available.
                      </td>
                    </tr>
                  ) : (
                    hsnData.map((h: any) => (
                      <tr key={h.hsn_sac}>
                        <td className="px-3 py-2 font-mono font-semibold">{h.hsn_sac}</td>
                        <td className="px-3 py-2">{h.description}</td>
                        <td className="px-3 py-2">{h.unit}</td>
                        <td className="px-3 py-2">{h.total_quantity}</td>
                        <td className="px-3 py-2 font-semibold">₹{h.total_taxable.toLocaleString()}</td>
                        <td className="px-3 py-2">₹{h.cgst.toLocaleString()}</td>
                        <td className="px-3 py-2">₹{h.sgst.toLocaleString()}</td>
                        <td className="px-3 py-2">₹{h.igst.toLocaleString()}</td>
                        <td className="px-3 py-2 font-bold">₹{h.total_value.toLocaleString()}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 6: COMPLIANCE CALENDAR */}
      {activeTab === 'calendar' && (
        <div className="space-y-6">
          <div className="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div className="flex justify-between items-center mb-4">
              <div>
                <h3 className="text-lg font-bold text-gray-900 dark:text-white">
                  Statutory GST & Corporate Tax Compliance Calendar
                </h3>
                <p className="text-sm text-gray-500">
                  Never miss GSTR-1, GSTR-3B, TDS payments, or trade license renewals.
                </p>
              </div>
              <button
                onClick={() => setShowReminderModal(true)}
                className="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-medium inline-flex items-center gap-1.5"
              >
                <Plus className="w-3.5 h-3.5" />
                Add Compliance Task
              </button>
            </div>

            <div className="divide-y divide-gray-200 dark:divide-gray-700">
              {(!reminders || reminders.length === 0) ? (
                <div className="text-center py-6 text-gray-500">Loading statutory compliance calendar...</div>
              ) : (
                reminders.map((rem: any) => (
                  <div key={rem.id} className="py-4 flex items-center justify-between gap-4">
                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <span className="font-semibold text-gray-900 dark:text-white">{rem.title}</span>
                        <span
                          className={`text-xs px-2 py-0.5 rounded-full font-semibold ${
                            rem.status === 'completed'
                              ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400'
                              : rem.status === 'overdue'
                              ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                              : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400'
                          }`}
                        >
                          {rem.status}
                        </span>
                        <span className="text-xs uppercase px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400">
                          {rem.frequency}
                        </span>
                      </div>
                      <p className="text-xs text-gray-500">{rem.notes}</p>
                      <div className="flex items-center gap-4 text-xs text-gray-400">
                        <span>Due Date: <strong className="text-gray-700 dark:text-gray-300">{rem.due_date}</strong></span>
                        <span>Assignee: {rem.responsible_accountant_name || 'Finance Team'}</span>
                      </div>
                    </div>

                    <div>
                      {rem.status !== 'completed' && (
                        <button
                          onClick={() => completeReminderMut.mutate(rem.id)}
                          className="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-medium"
                        >
                          Mark Complete
                        </button>
                      )}
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      )}

      {/* TAB 7: DOCUMENT VAULT */}
      {activeTab === 'vault' && (
        <div className="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm space-y-4">
          <div className="flex justify-between items-center mb-2">
            <div>
              <h3 className="text-lg font-bold text-gray-900 dark:text-white">
                Tax & Legal Document Vault
              </h3>
              <p className="text-sm text-gray-500">
                Securely store official certificates, GST REG-06, PAN, trade licenses, and contracts with expiry tracking.
              </p>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {(!documents || documents.length === 0) ? (
              <div className="col-span-3 text-center py-8 text-gray-500">No documents stored in vault.</div>
            ) : (
              documents.map((doc: any) => (
                <div
                  key={doc.id}
                  className="p-4 border border-gray-200 dark:border-gray-700 rounded-lg flex flex-col justify-between hover:shadow-sm transition-shadow"
                >
                  <div>
                    <div className="flex items-center justify-between mb-2">
                      <span className="text-xs uppercase font-semibold text-indigo-600 dark:text-indigo-400">
                        {doc.document_type}
                      </span>
                      {doc.is_expired ? (
                        <span className="text-xs text-red-600 bg-red-100 px-2 py-0.5 rounded">Expired</span>
                      ) : (
                        <span className="text-xs text-emerald-600 bg-emerald-100 px-2 py-0.5 rounded">Active</span>
                      )}
                    </div>
                    <h4 className="font-semibold text-gray-900 dark:text-white text-sm">{doc.title}</h4>
                    {doc.document_number && (
                      <p className="text-xs font-mono text-gray-500 mt-1">Doc #: {doc.document_number}</p>
                    )}
                    {doc.expiry_date && (
                      <p className="text-xs text-gray-400 mt-1">Expires: {doc.expiry_date}</p>
                    )}
                  </div>
                  <div className="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex justify-between items-center">
                    <span className="text-xs text-gray-400">Version {doc.version}</span>
                    <a
                      href={doc.file_path}
                      className="text-xs text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
                    >
                      Download <ExternalLink className="w-3 h-3" />
                    </a>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>
      )}

      {/* TAB 8: GST REGISTRATIONS & SETTINGS */}
      {activeTab === 'settings' && (
        <div className="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm space-y-6">
          <div>
            <h3 className="text-lg font-bold text-gray-900 dark:text-white">
              Multi-State GST Registrations
            </h3>
            <p className="text-sm text-gray-500">
              Configure legally distinct GSTINs per state or business location. Ordinary branch addresses do not automatically create distinct registrations.
            </p>
          </div>

          <div className="space-y-4">
            {registrations?.map((reg: any) => (
              <div
                key={reg.id}
                className="p-5 border border-gray-200 dark:border-gray-700 rounded-xl flex flex-col md:flex-row justify-between gap-4"
              >
                <div className="space-y-1">
                  <div className="flex items-center gap-2">
                    <span className="font-bold text-gray-900 dark:text-white">{reg.business_legal_name}</span>
                    {reg.is_default && (
                      <span className="px-2 py-0.5 text-xs font-semibold rounded bg-indigo-100 text-indigo-800">
                        Default
                      </span>
                    )}
                    <span className="text-xs px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 capitalize">
                      {reg.gst_registration_status}
                    </span>
                  </div>
                  <div className="text-xs font-mono text-gray-600 dark:text-gray-400">
                    GSTIN: <strong>{reg.gstin}</strong> | PAN: <strong>{reg.pan || reg.gstin.substr(2, 10)}</strong>
                  </div>
                  <div className="text-xs text-gray-500">
                    Jurisdiction: {reg.state} (State Code: {reg.state_code}) | Series Prefix: {reg.invoice_prefix} | FY: {reg.financial_year}
                  </div>
                  <p className="text-xs text-gray-400">{reg.registered_address}, {reg.city} - {reg.pincode}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* CREATE INVOICE MODAL */}
      {showCreateModal && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-gray-800 rounded-xl max-w-lg w-full p-6 space-y-4 shadow-xl">
            <h3 className="text-lg font-bold text-gray-900 dark:text-white">Create GST Document</h3>
            <form onSubmit={handleCreateInvoice} className="space-y-3">
              <div>
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Document Type</label>
                <select
                  value={newInvDocType}
                  onChange={(e) => setNewInvDocType(e.target.value)}
                  className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent"
                >
                  <option value="tax_invoice">GST Tax Invoice (Standard)</option>
                  <option value="bill_of_supply">Bill of Supply (Exempt / Composition)</option>
                  <option value="proforma">Proforma Invoice</option>
                  <option value="export_invoice">Export Invoice (SEZ / Overseas)</option>
                  <option value="delivery_challan">Delivery Challan</option>
                </select>
              </div>

              <div>
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Buyer Legal Name</label>
                <input
                  type="text"
                  required
                  value={newInvBuyerName}
                  onChange={(e) => setNewInvBuyerName(e.target.value)}
                  className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent"
                  placeholder="e.g. Tata Steel Limited"
                />
              </div>

              <div>
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Buyer GSTIN (Leave empty if unregistered/B2C)</label>
                <input
                  type="text"
                  value={newInvBuyerGstin}
                  onChange={(e) => setNewInvBuyerGstin(e.target.value.toUpperCase())}
                  className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent font-mono"
                  placeholder="e.g. 27AAACT2727Q1ZW"
                  maxLength={15}
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Place of Supply (State Code)</label>
                  <input
                    type="text"
                    required
                    value={newInvPos}
                    onChange={(e) => setNewInvPos(e.target.value)}
                    className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent font-mono"
                    placeholder="27 for MH, 29 for KA"
                    maxLength={2}
                  />
                </div>
                <div>
                  <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">GST Rate (%)</label>
                  <select
                    value={newInvTaxRate}
                    onChange={(e) => setNewInvTaxRate(e.target.value)}
                    className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent"
                  >
                    <option value="0">0% (Nil / Exempt)</option>
                    <option value="5">5%</option>
                    <option value="12">12%</option>
                    <option value="18">18% (Standard Services)</option>
                    <option value="28">28% (Luxury / Capital)</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Item Description</label>
                <input
                  type="text"
                  required
                  value={newInvItemName}
                  onChange={(e) => setNewInvItemName(e.target.value)}
                  className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent"
                  placeholder="e.g. Cloud Hosting & Consulting"
                />
              </div>

              <div>
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Taxable Amount (₹)</label>
                <input
                  type="number"
                  required
                  value={newInvAmount}
                  onChange={(e) => setNewInvAmount(e.target.value)}
                  className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent"
                  min="1"
                />
              </div>

              <div className="flex justify-end gap-2 pt-3">
                <button
                  type="button"
                  onClick={() => setShowCreateModal(false)}
                  className="px-4 py-2 border rounded-lg text-sm"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createInvoiceMut.isPending}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium"
                >
                  Generate Document
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* E-WAY BILL MODAL */}
      {showEwbModal && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-gray-800 rounded-xl max-w-md w-full p-6 space-y-4 shadow-xl">
            <h3 className="text-lg font-bold text-gray-900 dark:text-white">Generate E-Way Bill</h3>
            <form onSubmit={handleGenerateEwb} className="space-y-3">
              <div>
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Distance in KM</label>
                <input
                  type="number"
                  required
                  value={ewbDistance}
                  onChange={(e) => setEwbDistance(e.target.value)}
                  className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent"
                  min="1"
                />
              </div>

              <div>
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Vehicle Registration Number</label>
                <input
                  type="text"
                  required
                  value={ewbVehicle}
                  onChange={(e) => setEwbVehicle(e.target.value.toUpperCase())}
                  className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent font-mono"
                  placeholder="e.g. MH02CB4567"
                />
              </div>

              <div>
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Transporter Name</label>
                <input
                  type="text"
                  required
                  value={ewbTransporter}
                  onChange={(e) => setEwbTransporter(e.target.value)}
                  className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent"
                />
              </div>

              <div className="flex justify-end gap-2 pt-3">
                <button
                  type="button"
                  onClick={() => setShowEwbModal(false)}
                  className="px-4 py-2 border rounded-lg text-sm"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={ewayBillMut.isPending}
                  className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium"
                >
                  Issue E-Way Bill
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* UPDATE VEHICLE MODAL */}
      {showVehicleModal && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-gray-800 rounded-xl max-w-md w-full p-6 space-y-4 shadow-xl">
            <h3 className="text-lg font-bold text-gray-900 dark:text-white">Update E-Way Bill Vehicle (Part B)</h3>
            <form onSubmit={handleUpdateVehicle} className="space-y-3">
              <div>
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">New Vehicle Registration Number</label>
                <input
                  type="text"
                  required
                  value={newVehicleNo}
                  onChange={(e) => setNewVehicleNo(e.target.value.toUpperCase())}
                  className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent font-mono"
                  placeholder="e.g. KA01AB1234"
                />
              </div>

              <div className="flex justify-end gap-2 pt-3">
                <button
                  type="button"
                  onClick={() => setShowVehicleModal(false)}
                  className="px-4 py-2 border rounded-lg text-sm"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={updateVehicleMut.isPending}
                  className="px-4 py-2 bg-gray-900 dark:bg-gray-100 dark:text-gray-900 text-white rounded-lg text-sm font-medium"
                >
                  Update Part B
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* CREATE REMINDER MODAL */}
      {showReminderModal && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-gray-800 rounded-xl max-w-md w-full p-6 space-y-4 shadow-xl">
            <h3 className="text-lg font-bold text-gray-900 dark:text-white">Add Compliance Reminder</h3>
            <form
              onSubmit={async (e) => {
                e.preventDefault();
                await createReminderMut.mutateAsync({
                  title: remTitle,
                  compliance_type: remType,
                  due_date: remDate,
                });
                setShowReminderModal(false);
              }}
              className="space-y-3"
            >
              <div>
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Task Title</label>
                <input
                  type="text"
                  required
                  value={remTitle}
                  onChange={(e) => setRemTitle(e.target.value)}
                  className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent"
                  placeholder="e.g. Factory License Renewal"
                />
              </div>

              <div>
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Type</label>
                <select
                  value={remType}
                  onChange={(e) => setRemType(e.target.value)}
                  className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent"
                >
                  <option value="license_renewal">Business License Renewal</option>
                  <option value="contract_expiry">Contract / Agreement Expiry</option>
                  <option value="tds_payment">TDS Payment</option>
                  <option value="gstr1">GSTR-1 Return</option>
                  <option value="gstr3b">GSTR-3B Return</option>
                  <option value="advance_tax">Advance Tax Installment</option>
                </select>
              </div>

              <div>
                <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">Due Date</label>
                <input
                  type="date"
                  required
                  value={remDate}
                  onChange={(e) => setRemDate(e.target.value)}
                  className="w-full mt-1 px-3 py-2 border rounded-lg text-sm bg-transparent"
                />
              </div>

              <div className="flex justify-end gap-2 pt-3">
                <button
                  type="button"
                  onClick={() => setShowReminderModal(false)}
                  className="px-4 py-2 border rounded-lg text-sm"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createReminderMut.isPending}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium"
                >
                  Add Task
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
