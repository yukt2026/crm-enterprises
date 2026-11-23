import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useCRMStore } from '../store/crmStore';
import {
  EmployeeItem,
  DepartmentItem,
  ShiftItem,
  CompanyHolidayItem,
  AttendanceItem,
  AttendanceCorrectionItem,
  LeaveItem,
  LeaveTypeItem,
  LeaveBalanceItem,
  SalaryStructureItem,
  EmployeeSalaryItem,
  PayrollBatchItem,
  PayslipItem,
  EmployeeDocumentItem,
  EmployeePerformanceReviewItem,
  EmployeeProductivityGoalItem,
  HrReminderItem,
  FullAndFinalSettlementResult,
  HrDashboardMetrics,
} from '../api/types';

// ==========================================
// 1. EMPLOYEES & HR DASHBOARD HOOKS
// ==========================================

export function useEmployees(params?: { status?: string; department_id?: number; search?: string }) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['employees', workspaceId, params],
    queryFn: async () => {
      const res = await api.getEmployees(params);
      return (res.data || []) as EmployeeItem[];
    },
  });
}

export function useEmployee(id: number | null | undefined) {
  return useQuery({
    queryKey: ['employee', id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getEmployee(id);
      return res.data as EmployeeItem;
    },
    enabled: Boolean(id),
  });
}

export function useCreateEmployee() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: Partial<EmployeeItem>) => {
      const res = await api.createEmployee(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['employees'] });
      queryClient.invalidateQueries({ queryKey: ['hr-dashboard'] });
    },
  });
}

export function useUpdateEmployee() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Partial<EmployeeItem> }) => {
      const res = await api.updateEmployee(id, data);
      return res.data;
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['employees'] });
      queryClient.invalidateQueries({ queryKey: ['employee', variables.id] });
      queryClient.invalidateQueries({ queryKey: ['hr-dashboard'] });
    },
  });
}

export function useDeleteEmployee() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.deleteEmployee(id);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['employees'] });
      queryClient.invalidateQueries({ queryKey: ['hr-dashboard'] });
    },
  });
}

export function useTransferEmployee() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: any }) => {
      const res = await api.transferEmployee(id, data);
      return res.data;
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['employees'] });
      queryClient.invalidateQueries({ queryKey: ['employee', variables.id] });
      queryClient.invalidateQueries({ queryKey: ['org-hierarchy'] });
    },
  });
}

export function useOrgHierarchy() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['org-hierarchy', workspaceId],
    queryFn: async () => {
      const res = await api.getOrgHierarchy();
      return res.data;
    },
  });
}

export function useHrDashboardMetrics() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['hr-dashboard', workspaceId],
    queryFn: async () => {
      const res = await api.getHrDashboardMetrics();
      return res.data as HrDashboardMetrics;
    },
  });
}

// ==========================================
// 2. DEPARTMENTS HOOKS
// ==========================================

export function useDepartments() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['departments', workspaceId],
    queryFn: async () => {
      const res = await api.getDepartments();
      return (res.data || []) as DepartmentItem[];
    },
  });
}

export function useCreateDepartment() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: Partial<DepartmentItem>) => {
      const res = await api.createDepartment(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['departments'] });
      queryClient.invalidateQueries({ queryKey: ['org-hierarchy'] });
    },
  });
}

export function useDeleteDepartment() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.deleteDepartment(id);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['departments'] });
      queryClient.invalidateQueries({ queryKey: ['org-hierarchy'] });
    },
  });
}

// ==========================================
// 3. SHIFTS & HOLIDAYS HOOKS
// ==========================================

export function useShifts() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['shifts', workspaceId],
    queryFn: async () => {
      const res = await api.getShifts();
      return (res.data || []) as ShiftItem[];
    },
  });
}

export function useCreateShift() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: Partial<ShiftItem>) => {
      const res = await api.createShift(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['shifts'] });
    },
  });
}

export function useCompanyHolidays(year?: number) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['company-holidays', workspaceId, year],
    queryFn: async () => {
      const res = await api.getCompanyHolidays(year);
      return (res.data || []) as CompanyHolidayItem[];
    },
  });
}

export function useCreateCompanyHoliday() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: Partial<CompanyHolidayItem>) => {
      const res = await api.createCompanyHoliday(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['company-holidays'] });
      queryClient.invalidateQueries({ queryKey: ['hr-dashboard'] });
    },
  });
}

// ==========================================
// 4. ATTENDANCE & CORRECTIONS HOOKS
// ==========================================

export function useAttendance(params?: { date?: string; start_date?: string; end_date?: string; employee_id?: number; status?: string }) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['attendance', workspaceId, params],
    queryFn: async () => {
      const res = await api.getAttendance(params);
      return (res.data || []) as AttendanceItem[];
    },
  });
}

export function useAttendanceCalendar(params?: { year?: number; month?: number; employee_id?: number; department_id?: number }) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['attendance-calendar', workspaceId, params],
    queryFn: async () => {
      const res = await api.getAttendanceCalendar(params);
      return res.data;
    },
  });
}

export function useClockIn() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data?: { date?: string; check_in?: string; location?: string; notes?: string }) => {
      const res = await api.checkInAttendance(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['attendance'] });
      queryClient.invalidateQueries({ queryKey: ['attendance-calendar'] });
      queryClient.invalidateQueries({ queryKey: ['hr-dashboard'] });
    },
  });
}

export function useClockOut() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data?: { date?: string; check_out?: string }) => {
      const res = await api.checkOutAttendance(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['attendance'] });
      queryClient.invalidateQueries({ queryKey: ['attendance-calendar'] });
      queryClient.invalidateQueries({ queryKey: ['hr-dashboard'] });
    },
  });
}

export function useAttendanceCorrections(status?: string) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['attendance-corrections', workspaceId, status],
    queryFn: async () => {
      const res = await api.getAttendanceCorrections(status);
      return (res.data || []) as AttendanceCorrectionItem[];
    },
  });
}

export function useRequestAttendanceCorrection() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: { date: string; requested_check_in?: string; requested_check_out?: string; reason: string }) => {
      const res = await api.requestAttendanceCorrection(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['attendance-corrections'] });
      queryClient.invalidateQueries({ queryKey: ['hr-dashboard'] });
    },
  });
}

export function useReviewAttendanceCorrection() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, status, reviewer_notes }: { id: number; status: 'approved' | 'rejected'; reviewer_notes?: string }) => {
      const res = await api.reviewAttendanceCorrection(id, status, reviewer_notes);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['attendance-corrections'] });
      queryClient.invalidateQueries({ queryKey: ['attendance'] });
      queryClient.invalidateQueries({ queryKey: ['attendance-calendar'] });
      queryClient.invalidateQueries({ queryKey: ['hr-dashboard'] });
    },
  });
}

// ==========================================
// 5. LEAVES & BALANCES HOOKS
// ==========================================

export function useLeaves(params?: { status?: string; employee_id?: number; year?: number }) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['leaves', workspaceId, params],
    queryFn: async () => {
      const res = await api.getLeaves(params);
      return (res.data || []) as LeaveItem[];
    },
  });
}

export function useLeaveTypes() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['leave-types', workspaceId],
    queryFn: async () => {
      const res = await api.getLeaveTypes();
      return (res.data || []) as LeaveTypeItem[];
    },
  });
}

export function useCreateLeaveType() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: Partial<LeaveTypeItem>) => {
      const res = await api.createLeaveType(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['leave-types'] });
    },
  });
}

export function useLeaveBalances(employeeId?: number, year?: number) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['leave-balances', workspaceId, employeeId, year],
    queryFn: async () => {
      const res = await api.getLeaveBalances(employeeId, year);
      return (res.data || []) as LeaveBalanceItem[];
    },
  });
}

export function useCreateLeave() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: Partial<LeaveItem>) => {
      const res = await api.createLeave(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['leaves'] });
      queryClient.invalidateQueries({ queryKey: ['leave-balances'] });
      queryClient.invalidateQueries({ queryKey: ['hr-dashboard'] });
    },
  });
}

export function useApproveLeave() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, manager_notes }: { id: number; manager_notes?: string }) => {
      const res = await api.approveLeave(id, manager_notes);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['leaves'] });
      queryClient.invalidateQueries({ queryKey: ['leave-balances'] });
      queryClient.invalidateQueries({ queryKey: ['hr-dashboard'] });
    },
  });
}

export function useRejectLeave() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, manager_notes }: { id: number; manager_notes?: string }) => {
      const res = await api.rejectLeave(id, manager_notes);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['leaves'] });
      queryClient.invalidateQueries({ queryKey: ['leave-balances'] });
      queryClient.invalidateQueries({ queryKey: ['hr-dashboard'] });
    },
  });
}

export function useCancelLeave() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.cancelLeave(id);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['leaves'] });
      queryClient.invalidateQueries({ queryKey: ['leave-balances'] });
      queryClient.invalidateQueries({ queryKey: ['hr-dashboard'] });
    },
  });
}

export function useAdjustLeaveBalance() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: { employee_id: number; leave_type_id: number; adjustment_days: number; reason: string }) => {
      const res = await api.adjustLeaveBalance(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['leave-balances'] });
      queryClient.invalidateQueries({ queryKey: ['hr-dashboard'] });
    },
  });
}

// ==========================================
// 5. PAYROLL & SALARY HOOKS
// ==========================================

export function useSalaryStructures() {
  return useQuery({
    queryKey: ['salary-structures'],
    queryFn: async () => {
      const res = await api.getSalaryStructures();
      return (res.data || []) as SalaryStructureItem[];
    },
  });
}

export function useCreateSalaryStructure() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: Partial<SalaryStructureItem>) => {
      const res = await api.createSalaryStructure(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['salary-structures'] });
    },
  });
}

export function useEmployeeSalary(employeeId: number | null | undefined) {
  return useQuery({
    queryKey: ['employee-salary', employeeId],
    queryFn: async () => {
      if (!employeeId) return null;
      const res = await api.getEmployeeSalary(employeeId);
      return res.data as EmployeeSalaryItem;
    },
    enabled: Boolean(employeeId),
  });
}

export function useAssignEmployeeSalary() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ employeeId, data }: { employeeId: number; data: any }) => {
      const res = await api.assignEmployeeSalary(employeeId, data);
      return res.data;
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['employee-salary', variables.employeeId] });
      queryClient.invalidateQueries({ queryKey: ['employee', variables.employeeId] });
    },
  });
}

export function usePayrollBatches(params?: { year?: number; status?: string }) {
  return useQuery({
    queryKey: ['payroll-batches', params],
    queryFn: async () => {
      const res = await api.getPayrollBatches(params);
      return (res.data || []) as PayrollBatchItem[];
    },
  });
}

export function useGeneratePayrollBatch() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: { month: number; year: number; branch_id?: number }) => {
      const res = await api.generatePayrollBatch(data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['payroll-batches'] });
    },
  });
}

export function usePayrollBatch(batchId: number | null | undefined) {
  return useQuery({
    queryKey: ['payroll-batch', batchId],
    queryFn: async () => {
      if (!batchId) return null;
      const res = await api.getPayrollBatch(batchId);
      return res.data as PayrollBatchItem;
    },
    enabled: Boolean(batchId),
  });
}

export function useApprovePayrollBatch() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (batchId: number) => {
      const res = await api.approvePayrollBatch(batchId);
      return res.data;
    },
    onSuccess: (_, batchId) => {
      queryClient.invalidateQueries({ queryKey: ['payroll-batches'] });
      queryClient.invalidateQueries({ queryKey: ['payroll-batch', batchId] });
    },
  });
}

export function useDisbursePayrollBatch() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (batchId: number) => {
      const res = await api.disbursePayrollBatch(batchId);
      return res.data;
    },
    onSuccess: (_, batchId) => {
      queryClient.invalidateQueries({ queryKey: ['payroll-batches'] });
      queryClient.invalidateQueries({ queryKey: ['payroll-batch', batchId] });
    },
  });
}

export function usePayslips(params?: { payroll_batch_id?: number; employee_id?: number; month?: number; year?: number }) {
  return useQuery({
    queryKey: ['payslips', params],
    queryFn: async () => {
      const res = await api.getPayslips(params);
      return (res.data || []) as PayslipItem[];
    },
  });
}

export function useCalculateFullAndFinal() {
  return useMutation({
    mutationFn: async (data: {
      employee_id: number;
      resignation_date?: string;
      last_working_date?: string;
      notice_required_days?: number;
      notice_served_days?: number;
      gratuity_eligible?: boolean;
      years_of_service?: number;
      other_recoveries?: number;
      remarks?: string;
    }) => {
      const res = await api.calculateFullAndFinal(data);
      return res.data as FullAndFinalSettlementResult;
    },
  });
}

// ==========================================
// 6. EMPLOYEE DOCUMENTS HOOKS
// ==========================================

export function useEmployeeDocuments(employeeId: number | null | undefined) {
  return useQuery({
    queryKey: ['employee-documents', employeeId],
    queryFn: async () => {
      if (!employeeId) return [];
      const res = await api.getEmployeeDocuments(employeeId);
      return (res.data || []) as EmployeeDocumentItem[];
    },
    enabled: Boolean(employeeId),
  });
}

export function useCreateEmployeeDocument() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ employeeId, data }: { employeeId: number; data: Partial<EmployeeDocumentItem> }) => {
      const res = await api.createEmployeeDocument(employeeId, data);
      return res.data;
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['employee-documents', variables.employeeId] });
      queryClient.invalidateQueries({ queryKey: ['hr-reminders'] });
    },
  });
}

export function useVerifyEmployeeDocument() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ employeeId, docId, data }: { employeeId: number; docId: number; data?: { notes?: string } }) => {
      const res = await api.verifyEmployeeDocument(employeeId, docId, data);
      return res.data;
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['employee-documents', variables.employeeId] });
    },
  });
}

export function useDeleteEmployeeDocument() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ employeeId, docId }: { employeeId: number; docId: number }) => {
      const res = await api.deleteEmployeeDocument(employeeId, docId);
      return res.data;
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['employee-documents', variables.employeeId] });
    },
  });
}

// ==========================================
// 7. PRODUCTIVITY & PERFORMANCE HOOKS
// ==========================================

export function useEmployeeProductivity(employeeId: number | null | undefined) {
  return useQuery({
    queryKey: ['employee-productivity', employeeId],
    queryFn: async () => {
      if (!employeeId) return null;
      const res = await api.getEmployeeProductivity(employeeId);
      return res.data;
    },
    enabled: Boolean(employeeId),
  });
}

export function useEmployeeReviews(employeeId: number | null | undefined) {
  return useQuery({
    queryKey: ['employee-reviews', employeeId],
    queryFn: async () => {
      if (!employeeId) return [];
      const res = await api.getEmployeeReviews(employeeId);
      return (res.data || []) as EmployeePerformanceReviewItem[];
    },
    enabled: Boolean(employeeId),
  });
}

export function useCreateEmployeeReview() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ employeeId, data }: { employeeId: number; data: Partial<EmployeePerformanceReviewItem> }) => {
      const res = await api.createEmployeeReview(employeeId, data);
      return res.data;
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['employee-reviews', variables.employeeId] });
      queryClient.invalidateQueries({ queryKey: ['employee-productivity', variables.employeeId] });
    },
  });
}

export function useEmployeeGoals(employeeId: number | null | undefined) {
  return useQuery({
    queryKey: ['employee-goals', employeeId],
    queryFn: async () => {
      if (!employeeId) return [];
      const res = await api.getEmployeeGoals(employeeId);
      return (res.data || []) as EmployeeProductivityGoalItem[];
    },
    enabled: Boolean(employeeId),
  });
}

export function useCreateEmployeeGoal() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ employeeId, data }: { employeeId: number; data: Partial<EmployeeProductivityGoalItem> }) => {
      const res = await api.createEmployeeGoal(employeeId, data);
      return res.data;
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['employee-goals', variables.employeeId] });
      queryClient.invalidateQueries({ queryKey: ['employee-productivity', variables.employeeId] });
    },
  });
}

// ==========================================
// 8. HR REMINDERS HOOKS
// ==========================================

export function useHrReminders() {
  return useQuery({
    queryKey: ['hr-reminders'],
    queryFn: async () => {
      const res = await api.getHrReminders();
      return res.data;
    },
  });
}
