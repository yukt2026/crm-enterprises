import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useCRMStore } from '../store/crmStore';
import {
  QuotationRecord,
  QuotationItemRecord,
  InvoiceRecord,
  InvoiceItemRecord,
  PaymentRecord,
  RefundRecord,
  FinanceSummaryData,
} from '../api/types';

// ==========================================
// 1. QUOTATIONS HOOKS
// ==========================================

export function useQuotations(params: {
  status?: string;
  customer_id?: number;
  deal_id?: number;
  search?: string;
  page?: number;
  per_page?: number;
} = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['quotations', workspaceId, params],
    queryFn: async () => {
      const res = await api.getQuotations(params);
      return (res.data || []) as QuotationRecord[];
    },
  });
}

export function useQuotation(id: number | null) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['quotations', workspaceId, id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getQuotation(id);
      return res.data as QuotationRecord;
    },
    enabled: !!id,
  });
}

export function useCreateQuotation() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<QuotationRecord> & { items: QuotationItemRecord[] }) =>
      api.createQuotation(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['quotations'] });
      queryClient.invalidateQueries({ queryKey: ['finance-summary'] });
      addToast('Quotation created successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create quotation', 'error');
    },
  });
}

export function useReviseQuotation() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<QuotationRecord> & { items?: QuotationItemRecord[] } }) =>
      api.reviseQuotation(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['quotations'] });
      addToast('New revision created successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to revise quotation', 'error');
    },
  });
}

export function useUpdateQuotationStatus() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, status }: { id: number; status: string }) =>
      api.updateQuotationStatus(id, status),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['quotations'] });
      addToast('Quotation status updated', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to update quotation status', 'error');
    },
  });
}

export function useConvertQuotationToInvoice() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.convertQuotationToInvoice(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['quotations'] });
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
      queryClient.invalidateQueries({ queryKey: ['finance-summary'] });
      addToast('Quotation successfully converted to Invoice', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to convert quotation to invoice', 'error');
    },
  });
}

export function useDeleteQuotation() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.deleteQuotation(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['quotations'] });
      addToast('Quotation removed', 'info');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to delete quotation', 'error');
    },
  });
}

export function useSubmitQuotationApproval() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.submitQuotationApproval(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['quotations'] });
      addToast('Quotation submitted for manager approval', 'info');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to submit quotation for approval', 'error');
    },
  });
}

export function useApproveQuotation() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, notes }: { id: number; notes?: string }) =>
      api.approveQuotation(id, notes),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['quotations'] });
      addToast('Quotation approved successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to approve quotation', 'error');
    },
  });
}

export function useRejectQuotation() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) =>
      api.rejectQuotation(id, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['quotations'] });
      addToast('Quotation rejected', 'warning');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to reject quotation', 'error');
    },
  });
}

export function useSendQuotationVia() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, channel, recipient }: { id: number; channel: 'email' | 'whatsapp'; recipient?: string }) =>
      api.sendQuotationVia(id, { channel, recipient }),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['quotations'] });
      addToast(`Quotation sent via ${vars.channel.toUpperCase()}`, 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to send quotation', 'error');
    },
  });
}

// ==========================================
// 2. INVOICES HOOKS
// ==========================================

export function useInvoices(params: {
  status?: string;
  customer_id?: number;
  deal_id?: number;
  search?: string;
  page?: number;
  per_page?: number;
} = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['invoices', workspaceId, params],
    queryFn: async () => {
      const res = await api.getInvoices(params);
      return (res.data || []) as InvoiceRecord[];
    },
  });
}

export function useInvoice(id: number | null) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['invoices', workspaceId, id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getInvoice(id);
      return res.data as InvoiceRecord;
    },
    enabled: !!id,
  });
}

export function useCreateInvoice() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<InvoiceRecord> & { items: InvoiceItemRecord[] }) =>
      api.createInvoice(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
      queryClient.invalidateQueries({ queryKey: ['finance-summary'] });
      addToast('Invoice created successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create invoice', 'error');
    },
  });
}

export function useIssueInvoice() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.issueInvoice(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
      queryClient.invalidateQueries({ queryKey: ['finance-summary'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-stocks'] });
      addToast('Invoice issued and stock inventory adjusted', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to issue invoice', 'error');
    },
  });
}

export function useVoidInvoice() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) =>
      api.voidInvoice(id, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
      queryClient.invalidateQueries({ queryKey: ['finance-summary'] });
      addToast('Invoice marked as void', 'info');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to void invoice', 'error');
    },
  });
}

export function useRecordInvoicePayment() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, data }: {
      id: number;
      data: {
        amount: number;
        payment_method?: string;
        payment_date?: string;
        reference_number?: string;
        notes?: string;
      };
    }) => api.recordInvoicePayment(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
      queryClient.invalidateQueries({ queryKey: ['payments'] });
      queryClient.invalidateQueries({ queryKey: ['finance-summary'] });
      addToast('Payment recorded successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to record payment', 'error');
    },
  });
}

export function useCreateInvoicePaymentLink() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.createInvoicePaymentLink(id),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
      addToast('Razorpay payment order generated', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to generate payment link', 'error');
    },
  });
}

export function useVerifyInvoicePayment() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, data }: {
      id: number;
      data: { razorpay_order_id: string; razorpay_payment_id: string; razorpay_signature: string };
    }) => api.verifyInvoicePayment(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
      queryClient.invalidateQueries({ queryKey: ['payments'] });
      queryClient.invalidateQueries({ queryKey: ['finance-summary'] });
      addToast('Online payment verified successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Payment cryptographic verification failed', 'error');
    },
  });
}

export function useDeleteInvoice() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.deleteInvoice(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
      addToast('Draft invoice deleted', 'info');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to delete invoice', 'error');
    },
  });
}

// ==========================================
// 3. PAYMENTS & REFUNDS HOOKS
// ==========================================

export function usePayments(params: {
  invoice_id?: number;
  customer_id?: number;
  search?: string;
  page?: number;
  per_page?: number;
} = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['payments', workspaceId, params],
    queryFn: async () => {
      const res = await api.getPayments(params);
      return (res.data || []) as PaymentRecord[];
    },
  });
}

export function useRefundPayment() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: { amount: number; reason: string } }) =>
      api.refundPayment(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['payments'] });
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
      queryClient.invalidateQueries({ queryKey: ['finance-summary'] });
      addToast('Refund processed successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to process refund', 'error');
    },
  });
}

// ==========================================
// 4. FINANCE OVERVIEW & METRICS HOOKS
// ==========================================

export function useFinanceSummary() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['finance-summary', workspaceId],
    queryFn: async () => {
      const res = await api.getFinanceSummary();
      return res.data as FinanceSummaryData;
    },
  });
}

// ==========================================
// 5. ADVANCED PAYMENTS & GATEWAYS HOOKS
// ==========================================

export function usePaymentGateways() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['payment-gateways', workspaceId],
    queryFn: async () => {
      const res = await api.getPaymentGateways();
      return res.data || [];
    },
  });
}

export function usePaymentLinks(page = 1) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['payment-links', workspaceId, page],
    queryFn: async () => {
      const res = await api.getPaymentLinks(page);
      return res.data;
    },
  });
}

export function useCreatePaymentLink() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: any) => api.createPaymentLink(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['payment-links'] });
      addToast('Payment link generated successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create payment link', 'error');
    },
  });
}

export function useFinanceTransactions(params: any = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['finance-transactions', workspaceId, params],
    queryFn: async () => {
      const res = await api.getFinanceTransactions(params);
      return res.data;
    },
  });
}

export function useReconcileTransaction() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: { status: string; notes?: string } }) =>
      api.reconcileFinanceTransaction(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['finance-transactions'] });
      queryClient.invalidateQueries({ queryKey: ['comprehensive-finance-report'] });
      addToast('Transaction reconciled successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Reconciliation update failed', 'error');
    },
  });
}

// ==========================================
// 6. ACCOUNTS RECEIVABLE (AR) HOOKS
// ==========================================

export function useReceivablesAgeing(customerId?: number) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['receivables-ageing', workspaceId, customerId],
    queryFn: async () => {
      const res = await api.getReceivablesAgeing(customerId);
      return res.data;
    },
  });
}

export function useUpdatePromiseToPay() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ invoiceId, data }: { invoiceId: number; data: { promise_to_pay_date: string; notes?: string } }) =>
      api.updatePromiseToPay(invoiceId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['receivables-ageing'] });
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
      addToast('Promise-to-pay date updated', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to update promise to pay', 'error');
    },
  });
}

export function useSetInvoiceDispute() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ invoiceId, data }: { invoiceId: number; data: { is_disputed: boolean; reason?: string; status?: string } }) =>
      api.setInvoiceDispute(invoiceId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['receivables-ageing'] });
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
      addToast('Dispute status updated', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to update dispute status', 'error');
    },
  });
}

// ==========================================
// 7. ACCOUNTS PAYABLE (AP) & BILLS HOOKS
// ==========================================

export function useVendorBills(params: any = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['vendor-bills', workspaceId, params],
    queryFn: async () => {
      const res = await api.getVendorBills(params);
      return res.data;
    },
  });
}

export function useCreateVendorBill() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: any) => api.createVendorBill(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['vendor-bills'] });
      queryClient.invalidateQueries({ queryKey: ['payables-ageing'] });
      addToast('Vendor bill recorded successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to record vendor bill', 'error');
    },
  });
}

export function useApproveVendorBill() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (billId: number) => api.approveVendorBill(billId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['vendor-bills'] });
      addToast('Vendor bill approved for payment', 'success');
    },
  });
}

export function usePayablesAgeing(supplierId?: number) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['payables-ageing', workspaceId, supplierId],
    queryFn: async () => {
      const res = await api.getPayablesAgeing(supplierId);
      return res.data;
    },
  });
}

export function useRecordVendorPayment() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: any) => api.recordVendorPayment(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['vendor-bills'] });
      queryClient.invalidateQueries({ queryKey: ['payables-ageing'] });
      queryClient.invalidateQueries({ queryKey: ['comprehensive-finance-report'] });
      addToast('Vendor payment disbursed and recorded', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to disburse vendor payment', 'error');
    },
  });
}

// ==========================================
// 8. EXPENSES & REIMBURSEMENTS HOOKS
// ==========================================

export function useExpenses(params: any = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['expenses', workspaceId, params],
    queryFn: async () => {
      const res = await api.getExpenses(params);
      return res.data;
    },
  });
}

export function useCreateExpense() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: any) => api.createExpense(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['expenses'] });
      queryClient.invalidateQueries({ queryKey: ['expense-report'] });
      addToast('Expense recorded successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to record expense', 'error');
    },
  });
}

export function useApproveExpense() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.approveExpense(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['expenses'] });
      addToast('Expense approved', 'success');
    },
  });
}

export function useRejectExpense() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => api.rejectExpense(id, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['expenses'] });
      addToast('Expense rejected', 'info');
    },
  });
}

export function usePayExpense() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data?: any }) => api.payExpense(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['expenses'] });
      queryClient.invalidateQueries({ queryKey: ['comprehensive-finance-report'] });
      addToast('Expense marked as disbursed', 'success');
    },
  });
}

// ==========================================
// 9. COMPREHENSIVE FINANCIAL REPORT HOOK
// ==========================================

export function useComprehensiveFinanceReport() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['comprehensive-finance-report', workspaceId],
    queryFn: async () => {
      const res = await api.getComprehensiveFinanceReport();
      return res.data;
    },
  });
}
