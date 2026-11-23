import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import {
  GstRegistrationItem,
  GstVerificationResult,
  GstTaxCalculationResult,
  GstTaxSummaryReport,
  Gstr1SalesRegister,
  HsnSacSummaryItem,
  ComplianceReminderItem,
  ComplianceDocumentItem,
} from '../api/types';

export const GST_KEYS = {
  config: ['gst', 'config'] as const,
  invoices: (params: any) => ['gst', 'invoices', params] as const,
  taxSummary: (params: any) => ['gst', 'taxSummary', params] as const,
  salesRegister: (params: any) => ['gst', 'salesRegister', params] as const,
  hsnSummary: ['gst', 'hsnSummary'] as const,
  reminders: (params: any) => ['gst', 'reminders', params] as const,
  documents: (params: any) => ['gst', 'documents', params] as const,
};

export function useGstRegistrations() {
  return useQuery({
    queryKey: GST_KEYS.config,
    queryFn: async () => {
      const res = await api.getGstRegistrations();
      return res.data;
    },
  });
}

export function useSaveGstRegistration() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: Partial<GstRegistrationItem>) => api.saveGstRegistration(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: GST_KEYS.config });
    },
  });
}

export function useVerifyGstin() {
  return useMutation({
    mutationFn: (gstin: string) => api.verifyGstin(gstin),
  });
}

export function useCalculateGstTax() {
  return useMutation({
    mutationFn: (data: any) => api.calculateGstTax(data),
  });
}

export function useGstInvoices(params: any = {}) {
  return useQuery({
    queryKey: GST_KEYS.invoices(params),
    queryFn: async () => {
      const res = await api.getGstInvoices(params);
      return res.data;
    },
  });
}

export function useCreateGstInvoice() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: any) => api.createGstInvoice(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['gst', 'invoices'] });
      queryClient.invalidateQueries({ queryKey: ['gst', 'taxSummary'] });
      queryClient.invalidateQueries({ queryKey: ['gst', 'salesRegister'] });
    },
  });
}

export function useFinalizeGstInvoice() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.finalizeGstInvoice(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['gst', 'invoices'] });
      queryClient.invalidateQueries({ queryKey: ['gst', 'taxSummary'] });
    },
  });
}

export function useCreateGstCreditNote() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: any }) => api.createGstCreditNote(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['gst', 'invoices'] });
    },
  });
}

export function useGenerateEInvoice() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.generateEInvoice(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['gst', 'invoices'] });
    },
  });
}

export function useCancelEInvoice() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => api.cancelEInvoice(id, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['gst', 'invoices'] });
    },
  });
}

export function useGenerateEWayBill() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: any }) => api.generateEWayBill(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['gst', 'invoices'] });
    },
  });
}

export function useUpdateEWayBillVehicle() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, vehicleNumber }: { id: number; vehicleNumber: string }) =>
      api.updateEWayBillVehicle(id, vehicleNumber),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['gst', 'invoices'] });
    },
  });
}

export function useCancelEWayBill() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => api.cancelEWayBill(id, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['gst', 'invoices'] });
    },
  });
}

export function useGstTaxSummary(params: any = {}) {
  return useQuery({
    queryKey: GST_KEYS.taxSummary(params),
    queryFn: async () => {
      const res = await api.getGstTaxSummary(params);
      return res.data;
    },
  });
}

export function useGstr1SalesRegister(params: any = {}) {
  return useQuery({
    queryKey: GST_KEYS.salesRegister(params),
    queryFn: async () => {
      const res = await api.getGstr1SalesRegister(params);
      return res.data;
    },
  });
}

export function useHsnSummary() {
  return useQuery({
    queryKey: GST_KEYS.hsnSummary,
    queryFn: async () => {
      const res = await api.getHsnSummary();
      return res.data;
    },
  });
}

export function useComplianceReminders(params: any = {}) {
  return useQuery({
    queryKey: GST_KEYS.reminders(params),
    queryFn: async () => {
      const res = await api.getComplianceReminders(params);
      return res.data;
    },
  });
}

export function useCreateComplianceReminder() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: Partial<ComplianceReminderItem>) => api.createComplianceReminder(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['gst', 'reminders'] });
    },
  });
}

export function useCompleteComplianceReminder() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.completeComplianceReminder(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['gst', 'reminders'] });
    },
  });
}

export function useComplianceDocuments(params: any = {}) {
  return useQuery({
    queryKey: GST_KEYS.documents(params),
    queryFn: async () => {
      const res = await api.getComplianceDocuments(params);
      return res.data;
    },
  });
}

export function useStoreComplianceDocument() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: Partial<ComplianceDocumentItem>) => api.storeComplianceDocument(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['gst', 'documents'] });
    },
  });
}
