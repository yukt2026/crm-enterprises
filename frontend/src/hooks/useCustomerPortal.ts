import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import {
  PortalSettingItem,
  BookableServiceItem,
  CustomerDashboardData,
  PublicBookingConfig,
  CustomerAccountItem,
} from '../api/types';

// ==========================================
// Customer Portal Hooks (Authenticated Client)
// ==========================================

export function usePortalDashboard() {
  return useQuery({
    queryKey: ['portal-dashboard'],
    queryFn: () => api.getPortalDashboard(),
  });
}

export function usePortalProfile() {
  return useQuery({
    queryKey: ['portal-profile'],
    queryFn: () => api.getPortalProfile(),
  });
}

export function useUpdatePortalProfile() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: { name?: string; phone?: string; billing_address?: string; shipping_address?: string; gstin?: string }) =>
      api.updatePortalProfile(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['portal-profile'] });
      queryClient.invalidateQueries({ queryKey: ['portal-dashboard'] });
    },
  });
}

export function usePortalQuotations(params: { page?: number; per_page?: number } = {}) {
  return useQuery({
    queryKey: ['portal-quotations', params],
    queryFn: () => api.getPortalQuotations(params),
  });
}

export function useAcceptPortalQuotation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, notes }: { id: number; notes?: string }) => api.acceptPortalQuotation(id, notes),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['portal-quotations'] });
      queryClient.invalidateQueries({ queryKey: ['portal-dashboard'] });
    },
  });
}

export function useRejectPortalQuotation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => api.rejectPortalQuotation(id, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['portal-quotations'] });
      queryClient.invalidateQueries({ queryKey: ['portal-dashboard'] });
    },
  });
}

export function useRequestPortalQuotationRevision() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, notes }: { id: number; notes: string }) => api.requestPortalQuotationRevision(id, notes),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['portal-quotations'] });
    },
  });
}

export function usePortalInvoices(params: { page?: number; per_page?: number } = {}) {
  return useQuery({
    queryKey: ['portal-invoices', params],
    queryFn: () => api.getPortalInvoices(params),
  });
}

export function usePayPortalInvoice() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: { amount: number; payment_method: string; transaction_reference?: string } }) =>
      api.payPortalInvoice(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['portal-invoices'] });
      queryClient.invalidateQueries({ queryKey: ['portal-dashboard'] });
    },
  });
}

export function usePortalProjects(params: { page?: number; per_page?: number } = {}) {
  return useQuery({
    queryKey: ['portal-projects', params],
    queryFn: () => api.getPortalProjects(params),
  });
}

export function usePortalDocuments() {
  return useQuery({
    queryKey: ['portal-documents'],
    queryFn: () => api.getPortalDocuments(),
  });
}

export function usePortalTickets(params: { page?: number; per_page?: number } = {}) {
  return useQuery({
    queryKey: ['portal-tickets', params],
    queryFn: () => api.getPortalTickets(params),
  });
}

export function useCreatePortalTicket() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: { subject: string; description: string; priority?: string; category_id?: number }) =>
      api.createPortalTicket(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['portal-tickets'] });
      queryClient.invalidateQueries({ queryKey: ['portal-dashboard'] });
    },
  });
}

export function useReplyPortalTicket() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, message, attachments }: { id: number; message: string; attachments?: any[] }) =>
      api.replyPortalTicket(id, message, attachments),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['portal-ticket', variables.id] });
      queryClient.invalidateQueries({ queryKey: ['portal-tickets'] });
    },
  });
}

export function usePortalAppointments(params: { page?: number; per_page?: number } = {}) {
  return useQuery({
    queryKey: ['portal-appointments', params],
    queryFn: () => api.getPortalAppointments(params),
  });
}

export function useBookPortalAppointment() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: {
      service_id: number;
      branch_id?: number;
      meeting_date: string;
      start_time: string;
      end_time?: string;
      organizer_id?: number;
      title?: string;
      notes?: string;
      timezone?: string;
      location_type?: string;
      location_address?: string;
    }) => api.bookPortalAppointment(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['portal-appointments'] });
      queryClient.invalidateQueries({ queryKey: ['portal-dashboard'] });
    },
  });
}

export function useReschedulePortalAppointment() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: { meeting_date: string; start_time: string } }) =>
      api.reschedulePortalAppointment(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['portal-appointments'] });
      queryClient.invalidateQueries({ queryKey: ['portal-dashboard'] });
    },
  });
}

export function useCancelPortalAppointment() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => api.cancelPortalAppointment(id, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['portal-appointments'] });
      queryClient.invalidateQueries({ queryKey: ['portal-dashboard'] });
    },
  });
}

export function usePortalServices() {
  return useQuery({
    queryKey: ['portal-services'],
    queryFn: () => api.getPortalServices(),
  });
}

// ==========================================
// Public Online Appointment Booking Hooks
// ==========================================

export function usePublicBookingConfig(slug: string) {
  return useQuery({
    queryKey: ['public-booking-config', slug],
    queryFn: () => api.getPublicBookingConfig(slug),
    enabled: !!slug,
  });
}

export function usePublicBookingSlots(slug: string, params: { service_id?: number; date?: string; branch_id?: number; staff_id?: number }) {
  return useQuery({
    queryKey: ['public-booking-slots', slug, params],
    queryFn: () =>
      params.service_id && params.date
        ? api.getPublicBookingSlots(slug, { service_id: params.service_id, date: params.date, branch_id: params.branch_id, staff_id: params.staff_id })
        : null,
    enabled: !!slug && !!params.service_id && !!params.date,
  });
}

export function useSubmitPublicBooking(slug: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: {
      service_id: number;
      branch_id?: number;
      meeting_date: string;
      start_time: string;
      customer_name: string;
      customer_email: string;
      customer_phone?: string;
      notes?: string;
    }) => api.submitPublicBooking(slug, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['public-booking-slots', slug] });
    },
  });
}

// ==========================================
// CRM Admin Portal Management Hooks
// ==========================================

export function usePortalSettings() {
  return useQuery({
    queryKey: ['crm-portal-settings'],
    queryFn: () => api.getPortalSettings(),
  });
}

export function useUpdatePortalSettings() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: Partial<PortalSettingItem>) => api.updatePortalSettings(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['crm-portal-settings'] });
    },
  });
}

export function useBookableServices(params: { branch_id?: number; is_active?: boolean } = {}) {
  return useQuery({
    queryKey: ['crm-bookable-services', params],
    queryFn: () => api.getBookableServices(params),
  });
}

export function useCreateBookableService() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: Partial<BookableServiceItem>) => api.createBookableService(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['crm-bookable-services'] });
    },
  });
}

export function useUpdateBookableService() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<BookableServiceItem> }) =>
      api.updateBookableService(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['crm-bookable-services'] });
    },
  });
}

export function useDeleteBookableService() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.deleteBookableService(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['crm-bookable-services'] });
    },
  });
}
