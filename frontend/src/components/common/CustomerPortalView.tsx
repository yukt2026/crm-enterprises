import React, { useState } from 'react';
import {
  usePortalDashboard,
  usePortalProfile,
  useUpdatePortalProfile,
  usePortalQuotations,
  useAcceptPortalQuotation,
  useRejectPortalQuotation,
  useRequestPortalQuotationRevision,
  usePortalInvoices,
  usePayPortalInvoice,
  usePortalProjects,
  usePortalDocuments,
  usePortalTickets,
  useCreatePortalTicket,
  useReplyPortalTicket,
  usePortalAppointments,
  useBookPortalAppointment,
  useReschedulePortalAppointment,
  useCancelPortalAppointment,
  usePortalServices,
  usePortalSettings,
  useUpdatePortalSettings,
  useBookableServices,
  useCreateBookableService,
  useUpdateBookableService,
  useDeleteBookableService,
} from '../../hooks/useCustomerPortal';
import {
  LayoutDashboard,
  FileText,
  CreditCard,
  Briefcase,
  Headphones,
  Calendar,
  Settings,
  User,
  CheckCircle,
  XCircle,
  Clock,
  AlertCircle,
  ChevronRight,
  ExternalLink,
  Plus,
  Send,
  Star,
  ShieldCheck,
  Building,
  DollarSign,
  Copy,
  RefreshCw,
} from 'lucide-react';

export const CustomerPortalView: React.FC = () => {
  const [activeTab, setActiveTab] = useState<
    'dashboard' | 'quotations' | 'invoices' | 'projects' | 'tickets' | 'appointments' | 'profile' | 'admin_settings'
  >('dashboard');

  // Queries
  const { data: dashboardRes, isLoading: loadingDash } = usePortalDashboard();
  const { data: profileRes } = usePortalProfile();
  const { data: quotesRes, refetch: refetchQuotes } = usePortalQuotations();
  const { data: invoicesRes, refetch: refetchInvoices } = usePortalInvoices();
  const { data: projectsRes } = usePortalProjects();
  const { data: documentsRes } = usePortalDocuments();
  const { data: ticketsRes, refetch: refetchTickets } = usePortalTickets();
  const { data: appointmentsRes, refetch: refetchAppointments } = usePortalAppointments();
  const { data: servicesRes } = usePortalServices();
  const { data: adminSettingsRes } = usePortalSettings();
  const { data: bookableServicesRes } = useBookableServices();

  // Mutations
  const acceptQuoteMutation = useAcceptPortalQuotation();
  const rejectQuoteMutation = useRejectPortalQuotation();
  const revisionQuoteMutation = useRequestPortalQuotationRevision();
  const payInvoiceMutation = usePayPortalInvoice();
  const createTicketMutation = useCreatePortalTicket();
  const replyTicketMutation = useReplyPortalTicket();
  const bookAppointmentMutation = useBookPortalAppointment();
  const rescheduleAppointmentMutation = useReschedulePortalAppointment();
  const cancelAppointmentMutation = useCancelPortalAppointment();
  const updateSettingsMutation = useUpdatePortalSettings();
  const createServiceMutation = useCreateBookableService();

  // Modal / Form States
  const [selectedQuote, setSelectedQuote] = useState<any | null>(null);
  const [rejectReason, setRejectReason] = useState('');
  const [revisionNotes, setRevisionNotes] = useState('');
  const [paymentModalInvoice, setPaymentModalInvoice] = useState<any | null>(null);
  const [payAmount, setPayAmount] = useState<number>(0);
  const [paymentMethod, setPaymentMethod] = useState('razorpay');
  const [newTicketModal, setNewTicketModal] = useState(false);
  const [ticketSubject, setTicketSubject] = useState('');
  const [ticketDescription, setTicketDescription] = useState('');
  const [ticketPriority, setTicketPriority] = useState('medium');
  const [selectedTicket, setSelectedTicket] = useState<any | null>(null);
  const [ticketReplyText, setTicketReplyText] = useState('');
  const [bookingModal, setBookingModal] = useState(false);
  const [selectedServiceId, setSelectedServiceId] = useState<number | ''>('');
  const [bookingDate, setBookingDate] = useState('');
  const [bookingTime, setBookingTime] = useState('10:00:00');
  const [bookingNotes, setBookingNotes] = useState('');
  const [adminSlug, setAdminSlug] = useState('');
  const [adminBrandColor, setAdminBrandColor] = useState('#4F46E5');
  const [copiedLink, setCopiedLink] = useState(false);

  const dashboard = dashboardRes?.data;
  const profile = profileRes?.data;
  const quotes = quotesRes?.data?.data || quotesRes?.data || [];
  const invoices = invoicesRes?.data?.data || invoicesRes?.data || [];
  const projects = projectsRes?.data?.data || projectsRes?.data || [];
  const documents = documentsRes?.data || [];
  const tickets = ticketsRes?.data?.data || ticketsRes?.data || [];
  const appointments = appointmentsRes?.data?.data || appointmentsRes?.data || [];
  const services = servicesRes?.data || [];
  const settings = adminSettingsRes?.data;
  const adminServices = bookableServicesRes?.data?.data || bookableServicesRes?.data || [];

  const handlePayInvoice = async () => {
    if (!paymentModalInvoice) return;
    try {
      await payInvoiceMutation.mutateAsync({
        id: paymentModalInvoice.id,
        data: {
          amount: payAmount,
          payment_method: paymentMethod,
          transaction_reference: `GATEWAY-SIM-${Date.now()}`,
        },
      });
      setPaymentModalInvoice(null);
      refetchInvoices();
    } catch (err: any) {
      alert(err.message || 'Payment processing failed');
    }
  };

  const handleCreateTicket = async () => {
    if (!ticketSubject || !ticketDescription) return;
    try {
      await createTicketMutation.mutateAsync({
        subject: ticketSubject,
        description: ticketDescription,
        priority: ticketPriority,
      });
      setNewTicketModal(false);
      setTicketSubject('');
      setTicketDescription('');
      refetchTickets();
    } catch (err: any) {
      alert(err.message || 'Failed to submit ticket');
    }
  };

  const handleBookAppointment = async () => {
    if (!selectedServiceId || !bookingDate || !bookingTime) return;
    try {
      await bookAppointmentMutation.mutateAsync({
        service_id: Number(selectedServiceId),
        meeting_date: bookingDate,
        start_time: bookingTime,
        notes: bookingNotes,
      });
      setBookingModal(false);
      setSelectedServiceId('');
      setBookingDate('');
      setBookingNotes('');
      refetchAppointments();
    } catch (err: any) {
      alert(err.message || 'Failed to book appointment');
    }
  };

  return (
    <div className="flex flex-col min-h-screen bg-slate-50 text-slate-900 font-sans">
      {/* Top Header / Portal Navbar */}
      <header className="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
          <div className="flex items-center space-x-3">
            <div className="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-lg shadow-sm">
              CP
            </div>
            <div>
              <span className="font-bold text-lg text-slate-900 tracking-tight">Customer Portal</span>
              <span className="ml-2 text-xs font-medium px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded-full border border-indigo-100">
                Self-Service & Appointments
              </span>
            </div>
          </div>

          <div className="flex items-center space-x-4">
            <div className="text-right hidden sm:block">
              <p className="text-sm font-semibold text-slate-800">
                {profile?.customer?.name || dashboard?.profile?.name || 'Authorized Customer'}
              </p>
              <p className="text-xs text-slate-500">
                {profile?.account?.email || dashboard?.profile?.email || 'customer@client.com'}
              </p>
            </div>
            <div className="w-9 h-9 rounded-full bg-slate-200 border border-slate-300 flex items-center justify-center text-slate-600 font-semibold text-sm">
              <User className="w-4 h-4" />
            </div>
          </div>
        </div>

        {/* Tab Navigation Navigation */}
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 overflow-x-auto scrollbar-none flex space-x-1 border-t border-slate-100">
          {[
            { id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
            { id: 'quotations', label: 'Quotations', icon: FileText },
            { id: 'invoices', label: 'Invoices & Payments', icon: CreditCard },
            { id: 'projects', label: 'Projects & Deliverables', icon: Briefcase },
            { id: 'tickets', label: 'Support & Help Desk', icon: Headphones },
            { id: 'appointments', label: 'Appointments & Booking', icon: Calendar },
            { id: 'profile', label: 'Profile & Security', icon: User },
            { id: 'admin_settings', label: 'CRM Admin Settings', icon: Settings },
          ].map((tab) => {
            const Icon = tab.icon;
            const active = activeTab === tab.id;
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id as any)}
                className={`flex items-center space-x-2 py-3 px-4 border-b-2 text-sm font-medium transition-colors whitespace-nowrap ${
                  active
                    ? 'border-indigo-600 text-indigo-600'
                    : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300'
                }`}
              >
                <Icon className={`w-4 h-4 ${active ? 'text-indigo-600' : 'text-slate-400'}`} />
                <span>{tab.label}</span>
              </button>
            );
          })}
        </div>
      </header>

      {/* Main Container */}
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">
        {/* ========================================================================= */}
        {/* TAB 1: DASHBOARD */}
        {/* ========================================================================= */}
        {activeTab === 'dashboard' && (
          <div className="space-y-6">
            {/* Welcome Banner */}
            <div className="bg-gradient-to-r from-indigo-700 via-indigo-800 to-slate-900 rounded-2xl p-6 text-white shadow-md relative overflow-hidden">
              <div className="relative z-10">
                <h1 className="text-2xl font-bold tracking-tight">
                  Welcome back, {dashboard?.profile?.name || 'Customer'}!
                </h1>
                <p className="mt-1 text-sm text-indigo-200 max-w-xl">
                  Manage your active projects, review and accept quotes, pay pending invoices, and schedule business appointments with our dedicated specialists.
                </p>
                <div className="mt-4 flex flex-wrap gap-3">
                  <button
                    onClick={() => setActiveTab('appointments')}
                    className="inline-flex items-center space-x-2 px-4 py-2 bg-white text-indigo-700 rounded-lg text-sm font-semibold hover:bg-indigo-50 shadow-xs transition"
                  >
                    <Calendar className="w-4 h-4" />
                    <span>Book an Appointment</span>
                  </button>
                  <button
                    onClick={() => setActiveTab('invoices')}
                    className="inline-flex items-center space-x-2 px-4 py-2 bg-indigo-600/60 border border-indigo-400/40 text-white rounded-lg text-sm font-semibold hover:bg-indigo-600 shadow-xs transition"
                  >
                    <CreditCard className="w-4 h-4" />
                    <span>View Outstanding Invoices</span>
                  </button>
                </div>
              </div>
            </div>

            {/* Metric KPI Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">Outstanding Balance</span>
                  <div className="p-2 bg-rose-50 rounded-lg text-rose-600">
                    <DollarSign className="w-4 h-4" />
                  </div>
                </div>
                <p className="text-2xl font-bold text-slate-900 mt-2">
                  ₹{(dashboard?.financials?.outstanding_due || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                </p>
                <span className="text-xs text-slate-400 mt-1 block">Pending payment across issued invoices</span>
              </div>

              <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">Shared Quotations</span>
                  <div className="p-2 bg-amber-50 rounded-lg text-amber-600">
                    <FileText className="w-4 h-4" />
                  </div>
                </div>
                <p className="text-2xl font-bold text-slate-900 mt-2">{dashboard?.counts?.pending_quotations || 0}</p>
                <span className="text-xs text-amber-600 mt-1 block font-medium">Awaiting your review or approval</span>
              </div>

              <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">Active Projects</span>
                  <div className="p-2 bg-blue-50 rounded-lg text-blue-600">
                    <Briefcase className="w-4 h-4" />
                  </div>
                </div>
                <p className="text-2xl font-bold text-slate-900 mt-2">{dashboard?.counts?.active_projects || 0}</p>
                <span className="text-xs text-slate-400 mt-1 block">Live milestones & deliverable tracking</span>
              </div>

              <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">Upcoming Appointments</span>
                  <div className="p-2 bg-emerald-50 rounded-lg text-emerald-600">
                    <Calendar className="w-4 h-4" />
                  </div>
                </div>
                <p className="text-2xl font-bold text-slate-900 mt-2">{dashboard?.counts?.upcoming_appointments || 0}</p>
                <span className="text-xs text-slate-400 mt-1 block">Confirmed client consultations</span>
              </div>
            </div>

            {/* Quick Overview Tables */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              {/* Invoices List */}
              <div className="bg-white rounded-xl border border-slate-200 p-5 shadow-2xs">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="font-semibold text-slate-800 text-base flex items-center gap-2">
                    <CreditCard className="w-4 h-4 text-indigo-600" />
                    Recent Invoices
                  </h3>
                  <button onClick={() => setActiveTab('invoices')} className="text-xs text-indigo-600 hover:underline font-medium">
                    View all
                  </button>
                </div>
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-sm">
                    <thead>
                      <tr className="border-b border-slate-100 text-xs font-semibold text-slate-400 uppercase">
                        <th className="pb-2">Invoice #</th>
                        <th className="pb-2">Amount</th>
                        <th className="pb-2">Status</th>
                        <th className="pb-2 text-right">Action</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {dashboard?.recent_invoices?.length ? (
                        dashboard.recent_invoices.map((inv: any) => (
                          <tr key={inv.id} className="hover:bg-slate-50/50">
                            <td className="py-2.5 font-medium text-slate-800">{inv.invoice_number}</td>
                            <td className="py-2.5 text-slate-600">₹{Number(inv.total_amount).toLocaleString('en-IN')}</td>
                            <td className="py-2.5">
                              <span
                                className={`text-xs px-2 py-0.5 rounded-full font-medium ${
                                  inv.status === 'paid'
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : inv.status === 'partially_paid'
                                    ? 'bg-amber-50 text-amber-700'
                                    : 'bg-rose-50 text-rose-700'
                                }`}
                              >
                                {inv.status}
                              </span>
                            </td>
                            <td className="py-2.5 text-right">
                              {inv.outstanding_amount > 0 && (
                                <button
                                  onClick={() => {
                                    setPaymentModalInvoice(inv);
                                    setPayAmount(Number(inv.outstanding_amount));
                                  }}
                                  className="text-xs bg-indigo-600 text-white px-2.5 py-1 rounded-md font-medium hover:bg-indigo-700 transition"
                                >
                                  Pay
                                </button>
                              )}
                            </td>
                          </tr>
                        ))
                      ) : (
                        <tr>
                          <td colSpan={4} className="py-4 text-center text-xs text-slate-400">
                            No invoices generated yet.
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
              </div>

              {/* Quotations List */}
              <div className="bg-white rounded-xl border border-slate-200 p-5 shadow-2xs">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="font-semibold text-slate-800 text-base flex items-center gap-2">
                    <FileText className="w-4 h-4 text-indigo-600" />
                    Pending Quotations
                  </h3>
                  <button onClick={() => setActiveTab('quotations')} className="text-xs text-indigo-600 hover:underline font-medium">
                    View all
                  </button>
                </div>
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-sm">
                    <thead>
                      <tr className="border-b border-slate-100 text-xs font-semibold text-slate-400 uppercase">
                        <th className="pb-2">Quote #</th>
                        <th className="pb-2">Total</th>
                        <th className="pb-2">Status</th>
                        <th className="pb-2 text-right">Action</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {dashboard?.recent_quotations?.length ? (
                        dashboard.recent_quotations.map((quote: any) => (
                          <tr key={quote.id} className="hover:bg-slate-50/50">
                            <td className="py-2.5 font-medium text-slate-800">{quote.quote_number}</td>
                            <td className="py-2.5 text-slate-600">₹{Number(quote.total_amount).toLocaleString('en-IN')}</td>
                            <td className="py-2.5">
                              <span
                                className={`text-xs px-2 py-0.5 rounded-full font-medium ${
                                  quote.status === 'accepted'
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : quote.status === 'rejected'
                                    ? 'bg-rose-50 text-rose-700'
                                    : 'bg-amber-50 text-amber-700'
                                }`}
                              >
                                {quote.status}
                              </span>
                            </td>
                            <td className="py-2.5 text-right">
                              <button
                                onClick={() => {
                                  setSelectedQuote(quote);
                                  setActiveTab('quotations');
                                }}
                                className="text-xs text-indigo-600 font-medium hover:underline"
                              >
                                Review
                              </button>
                            </td>
                          </tr>
                        ))
                      ) : (
                        <tr>
                          <td colSpan={4} className="py-4 text-center text-xs text-slate-400">
                            No shared quotations awaiting action.
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* ========================================================================= */}
        {/* TAB 2: QUOTATIONS */}
        {/* ========================================================================= */}
        {activeTab === 'quotations' && (
          <div className="space-y-6">
            <div className="flex items-center justify-between">
              <div>
                <h2 className="text-xl font-bold text-slate-900">Commercial Quotations & Proposals</h2>
                <p className="text-sm text-slate-500">Digitally review, accept, or request revisions on commercial offers.</p>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {quotes.map((quote: any) => (
                <div key={quote.id} className="bg-white border border-slate-200 rounded-xl p-5 shadow-2xs flex flex-col justify-between">
                  <div>
                    <div className="flex items-center justify-between mb-2">
                      <span className="font-semibold text-slate-800">{quote.quote_number}</span>
                      <span
                        className={`text-xs font-medium px-2 py-0.5 rounded-full ${
                          quote.status === 'accepted'
                            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                            : quote.status === 'rejected'
                            ? 'bg-rose-50 text-rose-700 border border-rose-200'
                            : 'bg-amber-50 text-amber-700 border border-amber-200'
                        }`}
                      >
                        {quote.status}
                      </span>
                    </div>
                    <p className="text-2xl font-bold text-slate-900 mt-2">
                      ₹{Number(quote.total_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                    </p>
                    <p className="text-xs text-slate-400 mt-1">
                      Valid until: {quote.valid_until ? new Date(quote.valid_until).toLocaleDateString() : 'N/A'}
                    </p>

                    {quote.items?.length > 0 && (
                      <div className="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-600 space-y-1">
                        {quote.items.slice(0, 3).map((item: any) => (
                          <div key={item.id} className="flex justify-between">
                            <span className="truncate max-w-[180px]">{item.description || item.item_name}</span>
                            <span className="font-medium">₹{Number(item.total_price).toLocaleString('en-IN')}</span>
                          </div>
                        ))}
                        {quote.items.length > 3 && (
                          <p className="text-slate-400 italic">+{quote.items.length - 3} more items</p>
                        )}
                      </div>
                    )}
                  </div>

                  <div className="mt-6 pt-4 border-t border-slate-100 flex gap-2">
                    {quote.status !== 'accepted' && quote.status !== 'rejected' ? (
                      <>
                        <button
                          onClick={async () => {
                            if (confirm('Are you sure you want to digitally accept this quotation?')) {
                              await acceptQuoteMutation.mutateAsync({ id: quote.id });
                              refetchQuotes();
                            }
                          }}
                          className="flex-1 bg-emerald-600 text-white text-xs font-semibold py-2 rounded-lg hover:bg-emerald-700 transition"
                        >
                          Accept Quote
                        </button>
                        <button
                          onClick={() => {
                            const reason = prompt('Please provide reason for declining this quotation:');
                            if (reason) {
                              rejectQuoteMutation.mutateAsync({ id: quote.id, reason }).then(() => refetchQuotes());
                            }
                          }}
                          className="px-3 border border-slate-300 text-slate-600 text-xs font-semibold py-2 rounded-lg hover:bg-slate-50 transition"
                        >
                          Decline
                        </button>
                        <button
                          onClick={() => {
                            const notes = prompt('Please specify requested changes or adjustments:');
                            if (notes) {
                              revisionQuoteMutation.mutateAsync({ id: quote.id, notes }).then(() => refetchQuotes());
                            }
                          }}
                          className="px-3 border border-indigo-200 text-indigo-600 text-xs font-semibold py-2 rounded-lg hover:bg-indigo-50 transition"
                        >
                          Revise
                        </button>
                      </>
                    ) : (
                      <div className="w-full text-center py-2 bg-slate-50 rounded-lg text-xs font-medium text-slate-500">
                        {quote.status === 'accepted' ? 'Accepted on ' + new Date(quote.accepted_at).toLocaleDateString() : 'Declined'}
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* ========================================================================= */}
        {/* TAB 3: INVOICES & PAYMENTS */}
        {/* ========================================================================= */}
        {activeTab === 'invoices' && (
          <div className="space-y-6">
            <div className="flex items-center justify-between">
              <div>
                <h2 className="text-xl font-bold text-slate-900">Invoices & Payment Records</h2>
                <p className="text-sm text-slate-500">Track issued tax invoices, payment histories, and complete pending payments.</p>
              </div>
            </div>

            <div className="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                  <thead className="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600 uppercase">
                    <tr>
                      <th className="py-3 px-4">Invoice #</th>
                      <th className="py-3 px-4">Date</th>
                      <th className="py-3 px-4">Due Date</th>
                      <th className="py-3 px-4">Total Amount</th>
                      <th className="py-3 px-4">Paid</th>
                      <th className="py-3 px-4">Outstanding</th>
                      <th className="py-3 px-4">Status</th>
                      <th className="py-3 px-4 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {invoices.map((inv: any) => (
                      <tr key={inv.id} className="hover:bg-slate-50/50">
                        <td className="py-3.5 px-4 font-semibold text-slate-800">{inv.invoice_number}</td>
                        <td className="py-3.5 px-4 text-slate-600">{inv.issued_at ? new Date(inv.issued_at).toLocaleDateString() : 'N/A'}</td>
                        <td className="py-3.5 px-4 text-slate-600">{inv.due_date ? new Date(inv.due_date).toLocaleDateString() : 'N/A'}</td>
                        <td className="py-3.5 px-4 font-medium text-slate-900">₹{Number(inv.total_amount).toLocaleString('en-IN')}</td>
                        <td className="py-3.5 px-4 text-emerald-600 font-medium">₹{Number(inv.paid_amount || 0).toLocaleString('en-IN')}</td>
                        <td className="py-3.5 px-4 text-rose-600 font-medium">₹{Number(inv.outstanding_amount || 0).toLocaleString('en-IN')}</td>
                        <td className="py-3.5 px-4">
                          <span
                            className={`text-xs px-2.5 py-0.5 rounded-full font-medium ${
                              inv.status === 'paid'
                                ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                : inv.status === 'partially_paid'
                                ? 'bg-amber-50 text-amber-700 border border-amber-200'
                                : 'bg-rose-50 text-rose-700 border border-rose-200'
                            }`}
                          >
                            {inv.status}
                          </span>
                        </td>
                        <td className="py-3.5 px-4 text-right">
                          {inv.outstanding_amount > 0 ? (
                            <button
                              onClick={() => {
                                setPaymentModalInvoice(inv);
                                setPayAmount(Number(inv.outstanding_amount));
                              }}
                              className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition"
                            >
                              Pay Now
                            </button>
                          ) : (
                            <span className="text-xs text-emerald-600 font-medium flex items-center justify-end gap-1">
                              <CheckCircle className="w-3.5 h-3.5" /> Paid in Full
                            </span>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            {/* Payment Modal */}
            {paymentModalInvoice && (
              <div className="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
                <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200">
                  <h3 className="text-lg font-bold text-slate-900">Pay Invoice {paymentModalInvoice.invoice_number}</h3>
                  <p className="text-xs text-slate-500 mt-1">Select payment method and confirm the amount to pay.</p>

                  <div className="mt-4 space-y-4">
                    <div>
                      <label className="text-xs font-semibold text-slate-700 uppercase">Payment Amount (INR)</label>
                      <input
                        type="number"
                        min="1"
                        max={paymentModalInvoice.outstanding_amount}
                        value={payAmount}
                        onChange={(e) => setPayAmount(Number(e.target.value))}
                        className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-hidden"
                      />
                      <span className="text-xs text-slate-400 mt-1 block">
                        Maximum outstanding: ₹{Number(paymentModalInvoice.outstanding_amount).toLocaleString('en-IN')}
                      </span>
                    </div>

                    <div>
                      <label className="text-xs font-semibold text-slate-700 uppercase">Payment Provider / Method</label>
                      <select
                        value={paymentMethod}
                        onChange={(e) => setPaymentMethod(e.target.value)}
                        className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-hidden"
                      >
                        <option value="razorpay">Razorpay (Cards, Netbanking, UPI)</option>
                        <option value="cashfree">Cashfree Payments</option>
                        <option value="upi">Direct UPI QR Code</option>
                        <option value="card">Credit / Debit Card</option>
                      </select>
                    </div>

                    <div className="p-3 bg-indigo-50 border border-indigo-100 rounded-lg text-xs text-indigo-700 flex items-start gap-2">
                      <ShieldCheck className="w-4 h-4 shrink-0 text-indigo-600 mt-0.5" />
                      <span>Transactions are encrypted with 256-bit SSL. Receipts are issued automatically upon gateway confirmation.</span>
                    </div>

                    <div className="flex gap-3 pt-2">
                      <button
                        onClick={() => setPaymentModalInvoice(null)}
                        className="flex-1 py-2 text-xs font-semibold border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50"
                      >
                        Cancel
                      </button>
                      <button
                        onClick={handlePayInvoice}
                        disabled={payInvoiceMutation.isPending}
                        className="flex-1 py-2 text-xs font-semibold bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 shadow-sm transition disabled:opacity-50"
                      >
                        {payInvoiceMutation.isPending ? 'Processing...' : `Pay ₹${payAmount.toLocaleString('en-IN')}`}
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            )}
          </div>
        )}

        {/* ========================================================================= */}
        {/* TAB 4: PROJECTS & DELIVERABLES */}
        {/* ========================================================================= */}
        {activeTab === 'projects' && (
          <div className="space-y-6">
            <div className="flex items-center justify-between">
              <div>
                <h2 className="text-xl font-bold text-slate-900">Active Projects & Client Deliverables</h2>
                <p className="text-sm text-slate-500">Live project milestones, progress tracking, and shared client deliverables.</p>
              </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              {projects.map((proj: any) => (
                <div key={proj.id} className="bg-white border border-slate-200 rounded-xl p-5 shadow-2xs">
                  <div className="flex items-center justify-between mb-3">
                    <h3 className="font-bold text-slate-900 text-base">{proj.name}</h3>
                    <span className="text-xs px-2.5 py-0.5 rounded-full font-medium bg-blue-50 text-blue-700 border border-blue-200">
                      {proj.status}
                    </span>
                  </div>
                  <p className="text-xs text-slate-600 line-clamp-2">{proj.description || 'Enterprise project delivery engagement.'}</p>

                  <div className="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400">
                    <span>Deadline: {proj.deadline ? new Date(proj.deadline).toLocaleDateString() : 'TBD'}</span>
                    <span>Started: {proj.start_date ? new Date(proj.start_date).toLocaleDateString() : 'N/A'}</span>
                  </div>

                  {/* Milestones List */}
                  {proj.milestones?.length > 0 && (
                    <div className="mt-4 space-y-2">
                      <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Milestones</span>
                      {proj.milestones.map((m: any) => (
                        <div key={m.id} className="p-2.5 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-between text-xs">
                          <div>
                            <p className="font-semibold text-slate-800">{m.title}</p>
                            <p className="text-slate-400 text-[11px]">Due: {m.due_date ? new Date(m.due_date).toLocaleDateString() : 'Ongoing'}</p>
                          </div>
                          <div className="text-right">
                            <span className="font-bold text-indigo-600">{m.completion_percentage || 0}%</span>
                            <span className="text-[11px] block text-slate-400">{m.status}</span>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              ))}
            </div>

            {/* Documents Vault */}
            <div className="mt-8 bg-white border border-slate-200 rounded-xl p-6 shadow-2xs">
              <h3 className="font-bold text-slate-900 text-base mb-1">Shared Documents & Project Vault</h3>
              <p className="text-xs text-slate-500 mb-4">Official contracts, deliverables, and reports shared for your company.</p>

              <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                {documents.map((doc: any) => (
                  <div key={doc.id} className="p-3 border border-slate-200 rounded-lg flex items-center justify-between bg-slate-50/50 hover:bg-slate-50">
                    <div className="truncate mr-2">
                      <p className="text-xs font-semibold text-slate-800 truncate">{doc.name}</p>
                      <p className="text-[11px] text-slate-400">{doc.project?.name || 'General'}</p>
                    </div>
                    <a
                      href={doc.file_path || '#'}
                      target="_blank"
                      rel="noreferrer"
                      className="p-1.5 text-indigo-600 hover:bg-indigo-50 rounded-md"
                    >
                      <ExternalLink className="w-4 h-4" />
                    </a>
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}

        {/* ========================================================================= */}
        {/* TAB 5: SUPPORT TICKETS & HELP DESK */}
        {/* ========================================================================= */}
        {activeTab === 'tickets' && (
          <div className="space-y-6">
            <div className="flex items-center justify-between">
              <div>
                <h2 className="text-xl font-bold text-slate-900">Support Tickets & Service Desk</h2>
                <p className="text-sm text-slate-500">Raise assistance requests and converse with our customer service experts.</p>
              </div>
              <button
                onClick={() => setNewTicketModal(true)}
                className="inline-flex items-center space-x-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold px-4 py-2 rounded-lg shadow-xs transition"
              >
                <Plus className="w-4 h-4" />
                <span>New Ticket</span>
              </button>
            </div>

            <div className="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                  <thead className="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600 uppercase">
                    <tr>
                      <th className="py-3 px-4">Ticket #</th>
                      <th className="py-3 px-4">Subject</th>
                      <th className="py-3 px-4">Priority</th>
                      <th className="py-3 px-4">Status</th>
                      <th className="py-3 px-4">Created Date</th>
                      <th className="py-3 px-4 text-right">Action</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {tickets.map((t: any) => (
                      <tr key={t.id} className="hover:bg-slate-50/50">
                        <td className="py-3.5 px-4 font-semibold text-indigo-600">{t.ticket_number}</td>
                        <td className="py-3.5 px-4 font-medium text-slate-800">{t.subject}</td>
                        <td className="py-3.5 px-4">
                          <span
                            className={`text-xs px-2 py-0.5 rounded-full font-medium ${
                              t.priority === 'urgent'
                                ? 'bg-rose-50 text-rose-700'
                                : t.priority === 'high'
                                ? 'bg-amber-50 text-amber-700'
                                : 'bg-slate-100 text-slate-700'
                            }`}
                          >
                            {t.priority}
                          </span>
                        </td>
                        <td className="py-3.5 px-4">
                          <span className="text-xs px-2.5 py-0.5 rounded-full font-medium bg-slate-100 text-slate-700">
                            {t.status}
                          </span>
                        </td>
                        <td className="py-3.5 px-4 text-xs text-slate-500">
                          {t.created_at ? new Date(t.created_at).toLocaleDateString() : 'N/A'}
                        </td>
                        <td className="py-3.5 px-4 text-right">
                          <button
                            onClick={() => setSelectedTicket(t)}
                            className="text-xs text-indigo-600 hover:underline font-semibold"
                          >
                            View Thread
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            {/* Create Ticket Modal */}
            {newTicketModal && (
              <div className="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
                <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200">
                  <h3 className="text-lg font-bold text-slate-900">Create Support Ticket</h3>
                  <p className="text-xs text-slate-500 mt-1">Our support specialists will reply promptly to your issue.</p>

                  <div className="mt-4 space-y-4">
                    <div>
                      <label className="text-xs font-semibold text-slate-700 uppercase">Subject</label>
                      <input
                        type="text"
                        value={ticketSubject}
                        onChange={(e) => setTicketSubject(e.target.value)}
                        placeholder="Brief summary of your request"
                        className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-hidden"
                      />
                    </div>

                    <div>
                      <label className="text-xs font-semibold text-slate-700 uppercase">Priority</label>
                      <select
                        value={ticketPriority}
                        onChange={(e) => setTicketPriority(e.target.value)}
                        className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-hidden"
                      >
                        <option value="low">Low - General Inquiry</option>
                        <option value="medium">Medium - Standard Request</option>
                        <option value="high">High - High Importance</option>
                        <option value="urgent">Urgent - Critical Business Blocker</option>
                      </select>
                    </div>

                    <div>
                      <label className="text-xs font-semibold text-slate-700 uppercase">Description</label>
                      <textarea
                        rows={4}
                        value={ticketDescription}
                        onChange={(e) => setTicketDescription(e.target.value)}
                        placeholder="Provide full details, steps to reproduce, or relevant background..."
                        className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-hidden"
                      />
                    </div>

                    <div className="flex gap-3 pt-2">
                      <button
                        onClick={() => setNewTicketModal(false)}
                        className="flex-1 py-2 text-xs font-semibold border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50"
                      >
                        Cancel
                      </button>
                      <button
                        onClick={handleCreateTicket}
                        disabled={createTicketMutation.isPending}
                        className="flex-1 py-2 text-xs font-semibold bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition disabled:opacity-50"
                      >
                        {createTicketMutation.isPending ? 'Submitting...' : 'Submit Ticket'}
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* Ticket Reply Drawer / Modal */}
            {selectedTicket && (
              <div className="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
                <div className="bg-white rounded-2xl max-w-xl w-full p-6 shadow-xl border border-slate-200 max-h-[85vh] flex flex-col">
                  <div className="flex items-center justify-between pb-3 border-b border-slate-200">
                    <div>
                      <h3 className="text-base font-bold text-slate-900">{selectedTicket.ticket_number}: {selectedTicket.subject}</h3>
                      <span className="text-xs text-slate-400">Status: {selectedTicket.status}</span>
                    </div>
                    <button onClick={() => setSelectedTicket(null)} className="text-slate-400 hover:text-slate-600">
                      <XCircle className="w-5 h-5" />
                    </button>
                  </div>

                  <div className="flex-1 overflow-y-auto py-4 space-y-3">
                    <div className="p-3 bg-slate-50 rounded-lg text-xs text-slate-700 border border-slate-100">
                      <span className="font-bold block text-slate-900">Original Inquiry:</span>
                      {selectedTicket.description}
                    </div>
                  </div>

                  <div className="pt-3 border-t border-slate-200 flex gap-2">
                    <input
                      type="text"
                      value={ticketReplyText}
                      onChange={(e) => setTicketReplyText(e.target.value)}
                      placeholder="Type your response to support..."
                      className="flex-1 text-xs border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-hidden"
                    />
                    <button
                      onClick={async () => {
                        if (!ticketReplyText) return;
                        await replyTicketMutation.mutateAsync({ id: selectedTicket.id, message: ticketReplyText });
                        setTicketReplyText('');
                        alert('Reply posted to ticket.');
                      }}
                      className="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg text-xs font-semibold flex items-center gap-1"
                    >
                      <Send className="w-3.5 h-3.5" /> Send
                    </button>
                  </div>
                </div>
              </div>
            )}
          </div>
        )}

        {/* ========================================================================= */}
        {/* TAB 6: APPOINTMENTS & BOOKING */}
        {/* ========================================================================= */}
        {activeTab === 'appointments' && (
          <div className="space-y-6">
            <div className="flex items-center justify-between">
              <div>
                <h2 className="text-xl font-bold text-slate-900">Appointments & Consultation Booking</h2>
                <p className="text-sm text-slate-500">Book business meetings, consultations, and support sessions.</p>
              </div>
              <button
                onClick={() => setBookingModal(true)}
                className="inline-flex items-center space-x-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold px-4 py-2 rounded-lg shadow-xs transition"
              >
                <Plus className="w-4 h-4" />
                <span>Book Appointment</span>
              </button>
            </div>

            {/* Existing Appointments */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {appointments.map((apt: any) => (
                <div key={apt.id} className="bg-white border border-slate-200 rounded-xl p-5 shadow-2xs flex flex-col justify-between">
                  <div>
                    <div className="flex items-center justify-between mb-2">
                      <span className="text-xs font-semibold text-indigo-600">{apt.booking_reference || 'APT-REF'}</span>
                      <span
                        className={`text-xs px-2 py-0.5 rounded-full font-medium ${
                          apt.status === 'scheduled'
                            ? 'bg-emerald-50 text-emerald-700'
                            : apt.status === 'cancelled'
                            ? 'bg-rose-50 text-rose-700'
                            : 'bg-slate-100 text-slate-700'
                        }`}
                      >
                        {apt.status}
                      </span>
                    </div>

                    <h4 className="font-bold text-slate-900 text-base">{apt.title}</h4>
                    <p className="text-xs text-slate-500 mt-1 flex items-center gap-1">
                      <Clock className="w-3.5 h-3.5 text-slate-400" />
                      {apt.meeting_date} at {apt.start_time} - {apt.end_time}
                    </p>

                    {apt.service && (
                      <p className="text-xs text-indigo-700 bg-indigo-50 px-2 py-1 rounded-md mt-2 font-medium">
                        Service: {apt.service.name} ({apt.service.duration_minutes} mins)
                      </p>
                    )}
                  </div>

                  {apt.status === 'scheduled' && (
                    <div className="mt-4 pt-3 border-t border-slate-100 flex gap-2">
                      <button
                        onClick={async () => {
                          const newDate = prompt('Enter new date (YYYY-MM-DD):', apt.meeting_date);
                          const newTime = prompt('Enter new start time (HH:MM:SS):', apt.start_time);
                          if (newDate && newTime) {
                            await rescheduleAppointmentMutation.mutateAsync({
                              id: apt.id,
                              data: { meeting_date: newDate, start_time: newTime },
                            });
                            refetchAppointments();
                          }
                        }}
                        className="flex-1 text-xs border border-slate-300 text-slate-700 py-1.5 rounded-lg hover:bg-slate-50 font-medium"
                      >
                        Reschedule
                      </button>
                      <button
                        onClick={async () => {
                          const reason = prompt('Cancellation reason:');
                          if (reason) {
                            await cancelAppointmentMutation.mutateAsync({ id: apt.id, reason });
                            refetchAppointments();
                          }
                        }}
                        className="px-3 text-xs border border-rose-200 text-rose-600 py-1.5 rounded-lg hover:bg-rose-50 font-medium"
                      >
                        Cancel
                      </button>
                    </div>
                  )}
                </div>
              ))}
            </div>

            {/* Appointment Booking Modal */}
            {bookingModal && (
              <div className="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
                <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200">
                  <h3 className="text-lg font-bold text-slate-900">Book New Appointment</h3>
                  <p className="text-xs text-slate-500 mt-1">Select consultation service, date, and available time slot.</p>

                  <div className="mt-4 space-y-4">
                    <div>
                      <label className="text-xs font-semibold text-slate-700 uppercase">Consultation Service</label>
                      <select
                        value={selectedServiceId}
                        onChange={(e) => setSelectedServiceId(Number(e.target.value))}
                        className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-hidden"
                      >
                        <option value="">Select Service...</option>
                        {services.map((s: any) => (
                          <option key={s.id} value={s.id}>
                            {s.name} ({s.duration_minutes} mins) - ₹{Number(s.price).toLocaleString('en-IN')}
                          </option>
                        ))}
                      </select>
                    </div>

                    <div>
                      <label className="text-xs font-semibold text-slate-700 uppercase">Preferred Date</label>
                      <input
                        type="date"
                        value={bookingDate}
                        onChange={(e) => setBookingDate(e.target.value)}
                        className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-hidden"
                      />
                    </div>

                    <div>
                      <label className="text-xs font-semibold text-slate-700 uppercase">Preferred Start Time</label>
                      <input
                        type="time"
                        value={bookingTime}
                        onChange={(e) => setBookingTime(e.target.value + ':00')}
                        className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-hidden"
                      />
                    </div>

                    <div>
                      <label className="text-xs font-semibold text-slate-700 uppercase">Agenda / Notes</label>
                      <textarea
                        rows={2}
                        value={bookingNotes}
                        onChange={(e) => setBookingNotes(e.target.value)}
                        placeholder="Topics you'd like to discuss during the session..."
                        className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-hidden"
                      />
                    </div>

                    <div className="flex gap-3 pt-2">
                      <button
                        onClick={() => setBookingModal(false)}
                        className="flex-1 py-2 text-xs font-semibold border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50"
                      >
                        Cancel
                      </button>
                      <button
                        onClick={handleBookAppointment}
                        disabled={bookAppointmentMutation.isPending}
                        className="flex-1 py-2 text-xs font-semibold bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition disabled:opacity-50"
                      >
                        {bookAppointmentMutation.isPending ? 'Confirming...' : 'Confirm Booking'}
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            )}
          </div>
        )}

        {/* ========================================================================= */}
        {/* TAB 7: PROFILE & SECURITY */}
        {/* ========================================================================= */}
        {activeTab === 'profile' && (
          <div className="space-y-6 max-w-3xl">
            <div>
              <h2 className="text-xl font-bold text-slate-900">Profile & Security Settings</h2>
              <p className="text-sm text-slate-500">Manage company contact info, billing GSTIN, and account credentials.</p>
            </div>

            <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-2xs space-y-4">
              <h3 className="text-base font-semibold text-slate-800">Business & Billing Details</h3>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="text-xs font-semibold text-slate-600 uppercase">Customer Name</label>
                  <p className="text-sm font-medium text-slate-900 mt-1">{profile?.customer?.name || 'N/A'}</p>
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-600 uppercase">Corporate Email</label>
                  <p className="text-sm font-medium text-slate-900 mt-1">{profile?.account?.email || 'N/A'}</p>
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-600 uppercase">GSTIN</label>
                  <p className="text-sm font-medium text-slate-900 mt-1">{profile?.customer?.gstin || 'Not Configured'}</p>
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-600 uppercase">Branch / Location</label>
                  <p className="text-sm font-medium text-slate-900 mt-1">{profile?.customer?.branch?.name || 'Main Office'}</p>
                </div>
              </div>

              <div className="pt-4 border-t border-slate-100">
                <label className="text-xs font-semibold text-slate-600 uppercase">Registered Billing Address</label>
                <p className="text-sm text-slate-700 mt-1">{profile?.customer?.billing_address || 'No billing address specified.'}</p>
              </div>
            </div>
          </div>
        )}

        {/* ========================================================================= */}
        {/* TAB 8: CRM ADMIN PORTAL SETTINGS */}
        {/* ========================================================================= */}
        {activeTab === 'admin_settings' && (
          <div className="space-y-6">
            <div className="flex items-center justify-between">
              <div>
                <h2 className="text-xl font-bold text-slate-900">Customer Portal & Online Booking Configuration</h2>
                <p className="text-sm text-slate-500">Configure client portal permissions, public booking page, and bookable services catalog.</p>
              </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              {/* Portal Settings Card */}
              <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-2xs space-y-4">
                <h3 className="text-base font-semibold text-slate-800 flex items-center gap-2">
                  <ShieldCheck className="w-5 h-5 text-indigo-600" />
                  Portal Access & Permissions
                </h3>

                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-800">Customer Self-Registration</p>
                      <p className="text-xs text-slate-400">Allow customers to register accounts publicly or require admin invitations.</p>
                    </div>
                    <input
                      type="checkbox"
                      checked={settings?.allow_self_registration || false}
                      onChange={(e) => updateSettingsMutation.mutate({ allow_self_registration: e.target.checked })}
                      className="w-4 h-4 text-indigo-600 rounded-md border-slate-300"
                    />
                  </div>

                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-slate-800">Public Online Booking Page</p>
                      <p className="text-xs text-slate-400">Enable booking slug for direct appointment scheduling.</p>
                    </div>
                    <input
                      type="checkbox"
                      checked={settings?.public_booking_page_enabled || false}
                      onChange={(e) => updateSettingsMutation.mutate({ public_booking_page_enabled: e.target.checked })}
                      className="w-4 h-4 text-indigo-600 rounded-md border-slate-300"
                    />
                  </div>
                </div>

                <div className="pt-4 border-t border-slate-100">
                  <label className="text-xs font-semibold text-slate-700 uppercase">Public Booking Slug</label>
                  <div className="flex gap-2 mt-1">
                    <input
                      type="text"
                      placeholder="e.g. acme-consulting"
                      value={adminSlug || settings?.booking_slug || ''}
                      onChange={(e) => setAdminSlug(e.target.value)}
                      className="flex-1 text-xs border border-slate-300 rounded-lg px-3 py-2 outline-hidden"
                    />
                    <button
                      onClick={() => updateSettingsMutation.mutate({ booking_slug: adminSlug })}
                      className="bg-indigo-600 text-white text-xs px-3 py-2 rounded-lg font-medium hover:bg-indigo-700"
                    >
                      Save Slug
                    </button>
                  </div>
                </div>
              </div>

              {/* Bookable Services Catalog */}
              <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-2xs space-y-4">
                <div className="flex items-center justify-between">
                  <h3 className="text-base font-semibold text-slate-800 flex items-center gap-2">
                    <Calendar className="w-5 h-5 text-indigo-600" />
                    Bookable Services Catalog
                  </h3>
                  <button
                    onClick={() => {
                      const name = prompt('Service Name (e.g. 1-on-1 Strategy Session):');
                      const duration = prompt('Duration in minutes:', '30');
                      const price = prompt('Price in INR:', '1500');
                      if (name && duration && price) {
                        createServiceMutation.mutate({
                          name,
                          duration_minutes: Number(duration),
                          price: Number(price),
                        });
                      }
                    }}
                    className="text-xs bg-indigo-600 text-white px-3 py-1.5 rounded-lg font-semibold hover:bg-indigo-700"
                  >
                    + Add Service
                  </button>
                </div>

                <div className="divide-y divide-slate-100">
                  {adminServices.map((srv: any) => (
                    <div key={srv.id} className="py-2.5 flex items-center justify-between text-xs">
                      <div>
                        <p className="font-semibold text-slate-800">{srv.name}</p>
                        <p className="text-slate-400">{srv.duration_minutes} mins • ₹{Number(srv.price).toLocaleString('en-IN')}</p>
                      </div>
                      <span className={`px-2 py-0.5 rounded-full text-[11px] font-medium ${srv.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>
                        {srv.is_active ? 'Active' : 'Disabled'}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        )}
      </main>
    </div>
  );
};

export default CustomerPortalView;
