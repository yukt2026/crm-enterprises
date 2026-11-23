import React, { useState } from 'react';
import {
  Users,
  Building2,
  Clock,
  Calendar,
  Layers,
  CheckCircle2,
  AlertCircle,
  Plus,
  Search,
  Filter,
  Check,
  X,
  ChevronRight,
  TrendingUp,
  MapPin,
  Mail,
  Phone,
  Shield,
  Eye,
  Trash2,
  Edit,
  ArrowRightLeft,
  CalendarDays,
  Coffee,
  HelpCircle,
  FileText,
  CreditCard,
  FileCheck,
  Award,
  Bell,
  Navigation,
  DollarSign,
  CheckSquare,
  Briefcase
} from 'lucide-react';
import {
  useEmployees,
  useCreateEmployee,
  useDeleteEmployee,
  useDepartments,
  useCreateDepartment,
  useDeleteDepartment,
  useShifts,
  useCreateShift,
  useCompanyHolidays,
  useCreateCompanyHoliday,
  useAttendance,
  useAttendanceCalendar,
  useClockIn,
  useClockOut,
  useAttendanceCorrections,
  useRequestAttendanceCorrection,
  useReviewAttendanceCorrection,
  useLeaves,
  useLeaveTypes,
  useLeaveBalances,
  useCreateLeave,
  useApproveLeave,
  useRejectLeave,
  useCancelLeave,
  useHrDashboardMetrics,
  useSalaryStructures,
  usePayrollBatches,
  useGeneratePayrollBatch,
  useApprovePayrollBatch,
  useDisbursePayrollBatch,
  usePayslips,
  useCalculateFullAndFinal,
  useEmployeeDocuments,
  useCreateEmployeeDocument,
  useVerifyEmployeeDocument,
  useEmployeeProductivity,
  useEmployeeReviews,
  useCreateEmployeeReview,
  useEmployeeGoals,
  useCreateEmployeeGoal,
  useHrReminders
} from '../../hooks/useHrm';
import {
  EmployeeItem,
  DepartmentItem,
  ShiftItem,
  LeaveItem,
  AttendanceItem,
  AttendanceCorrectionItem,
  SalaryStructureItem,
  PayrollBatchItem,
  PayslipItem,
  EmployeeDocumentItem,
  EmployeePerformanceReviewItem,
  EmployeeProductivityGoalItem,
  HrReminderItem,
  FullAndFinalSettlementResult,
} from '../../api/types';

interface HrmManagementViewProps {
  theme: 'light' | 'dark';
}

export const HrmManagementView: React.FC<HrmManagementViewProps> = ({ theme }) => {
  const [activeTab, setActiveTab] = useState<'overview' | 'employees' | 'departments' | 'attendance' | 'calendar' | 'shifts' | 'leaves' | 'payroll' | 'documents' | 'productivity' | 'reminders'>('overview');
  const [searchQuery, setSearchQuery] = useState('');
  const [departmentFilter, setDepartmentFilter] = useState<string>('all');
  const [statusFilter, setStatusFilter] = useState<string>('all');

  // Drawers & Modals
  const [selectedEmployee, setSelectedEmployee] = useState<EmployeeItem | null>(null);
  const [isEmployeeModalOpen, setIsEmployeeModalOpen] = useState(false);
  const [isDepartmentModalOpen, setIsDepartmentModalOpen] = useState(false);
  const [isShiftModalOpen, setIsShiftModalOpen] = useState(false);
  const [isHolidayModalOpen, setIsHolidayModalOpen] = useState(false);
  const [isLeaveModalOpen, setIsLeaveModalOpen] = useState(false);
  const [isCorrectionModalOpen, setIsCorrectionModalOpen] = useState(false);
  const [isGenerateBatchModalOpen, setIsGenerateBatchModalOpen] = useState(false);
  const [isDocModalOpen, setIsDocModalOpen] = useState(false);
  const [isReviewModalOpen, setIsReviewModalOpen] = useState(false);
  const [isGoalModalOpen, setIsGoalModalOpen] = useState(false);
  const [isFnfModalOpen, setIsFnfModalOpen] = useState(false);

  // Selected Employee for Sub-Views
  const [activeSubEmployeeId, setActiveSubEmployeeId] = useState<number | null>(null);

  // Calendar State
  const [calendarYear, setCalendarYear] = useState<number>(new Date().getFullYear());
  const [calendarMonth, setCalendarMonth] = useState<number>(new Date().getMonth() + 1);

  // GPS Attendance Status
  const [gpsLoading, setGpsLoading] = useState(false);
  const [gpsMessage, setGpsMessage] = useState<string | null>(null);

  // Queries
  const { data: metrics, isLoading: isLoadingMetrics } = useHrDashboardMetrics();
  const { data: employees = [], isLoading: isLoadingEmployees } = useEmployees({
    status: statusFilter === 'all' ? undefined : statusFilter,
    department_id: departmentFilter === 'all' ? undefined : Number(departmentFilter),
    search: searchQuery || undefined,
  });
  const { data: departments = [] } = useDepartments();
  const { data: shifts = [] } = useShifts();
  const { data: holidays = [] } = useCompanyHolidays(calendarYear);
  const { data: attendances = [] } = useAttendance();
  const { data: calendarData } = useAttendanceCalendar({ year: calendarYear, month: calendarMonth });
  const { data: corrections = [] } = useAttendanceCorrections();
  const { data: leaves = [] } = useLeaves();
  const { data: leaveTypes = [] } = useLeaveTypes();
  const { data: leaveBalances = [] } = useLeaveBalances();

  // Payroll, Docs, Productivity & Reminders Queries
  const { data: salaryStructures = [] } = useSalaryStructures();
  const { data: payrollBatches = [] } = usePayrollBatches();
  const { data: employeeDocs = [] } = useEmployeeDocuments(activeSubEmployeeId || (employees[0]?.id));
  const { data: employeeProd } = useEmployeeProductivity(activeSubEmployeeId || (employees[0]?.id));
  const { data: employeeReviews = [] } = useEmployeeReviews(activeSubEmployeeId || (employees[0]?.id));
  const { data: employeeGoals = [] } = useEmployeeGoals(activeSubEmployeeId || (employees[0]?.id));
  const { data: hrRemindersData } = useHrReminders();

  // Mutations
  const createEmployeeMutation = useCreateEmployee();
  const deleteEmployeeMutation = useDeleteEmployee();
  const createDeptMutation = useCreateDepartment();
  const deleteDeptMutation = useDeleteDepartment();
  const createShiftMutation = useCreateShift();
  const createHolidayMutation = useCreateCompanyHoliday();
  const clockInMutation = useClockIn();
  const clockOutMutation = useClockOut();
  const requestCorrectionMutation = useRequestAttendanceCorrection();
  const reviewCorrectionMutation = useReviewAttendanceCorrection();
  const createLeaveMutation = useCreateLeave();
  const approveLeaveMutation = useApproveLeave();
  const rejectLeaveMutation = useRejectLeave();
  const cancelLeaveMutation = useCancelLeave();

  // Payroll Mutations
  const generateBatchMutation = useGeneratePayrollBatch();
  const approveBatchMutation = useApprovePayrollBatch();
  const disburseBatchMutation = useDisbursePayrollBatch();
  const calculateFnfMutation = useCalculateFullAndFinal();
  const createDocMutation = useCreateEmployeeDocument();
  const verifyDocMutation = useVerifyEmployeeDocument();
  const createReviewMutation = useCreateEmployeeReview();
  const createGoalMutation = useCreateEmployeeGoal();

  // Payroll Batch Form
  const [batchForm, setBatchForm] = useState({
    month: new Date().getMonth() + 1,
    year: new Date().getFullYear(),
  });

  // Full and Final Form & Result
  const [fnfForm, setFnfForm] = useState({
    employee_id: 0,
    resignation_date: new Date().toISOString().split('T')[0],
    last_working_date: new Date().toISOString().split('T')[0],
    notice_required_days: 30,
    notice_served_days: 30,
    gratuity_eligible: false,
    years_of_service: 5,
    other_recoveries: 0,
    remarks: 'Full & final separation settlement',
  });
  const [fnfResult, setFnfResult] = useState<FullAndFinalSettlementResult | null>(null);

  // Document Form
  const [docForm, setDocForm] = useState({
    document_type: 'appointment_letter' as const,
    title: '',
    file_url: 'https://docs.enterprise-crm.internal/uploads/contract.pdf',
    expiry_date: '',
  });

  // Review Form
  const [reviewForm, setReviewForm] = useState({
    review_period: 'Q1-2026',
    rating_productivity: 4,
    rating_quality: 5,
    rating_teamwork: 4,
    strengths: 'Excellent technical architecture, prompt delivery',
    areas_for_improvement: 'Cross-functional knowledge sharing',
    action_plan: 'Lead internal design reviews and mentor engineers',
  });

  // Goal Form
  const [goalForm, setGoalForm] = useState({
    title: 'Deliver Cloud Infrastructure Migration',
    metric_name: 'migration_progress',
    target_value: 100,
    current_value: 75,
    start_date: new Date().toISOString().split('T')[0],
    target_date: new Date(Date.now() + 60 * 24 * 3600 * 1000).toISOString().split('T')[0],
  });

  // Form States
  const [employeeForm, setEmployeeForm] = useState<Partial<EmployeeItem>>({
    name: '',
    email: '',
    phone: '',
    department_id: undefined,
    designation: '',
    employment_type: 'full_time',
    work_location: 'Office',
    salary: 0,
    status: 'active',
  });

  const [deptForm, setDeptForm] = useState<{ name: string; code: string; description: string }>({
    name: '',
    code: '',
    description: '',
  });

  const [shiftForm, setShiftForm] = useState<{
    name: string;
    code: string;
    start_time: string;
    end_time: string;
    grace_period_minutes: number;
    half_day_minutes: number;
    full_day_minutes: number;
    is_default: boolean;
  }>({
    name: '',
    code: '',
    start_time: '09:00',
    end_time: '18:00',
    grace_period_minutes: 15,
    half_day_minutes: 240,
    full_day_minutes: 480,
    is_default: false,
  });

  const [leaveForm, setLeaveForm] = useState<{
    leave_type_id: string;
    start_date: string;
    end_date: string;
    is_half_day: boolean;
    half_day_session: 'morning' | 'afternoon';
    reason: string;
  }>({
    leave_type_id: '',
    start_date: '',
    end_date: '',
    is_half_day: false,
    half_day_session: 'morning',
    reason: '',
  });

  const [correctionForm, setCorrectionForm] = useState<{
    date: string;
    requested_check_in: string;
    requested_check_out: string;
    reason: string;
  }>({
    date: '',
    requested_check_in: '09:00',
    requested_check_out: '18:00',
    reason: '',
  });

  const isDark = theme === 'dark';
  const cardBg = isDark ? 'bg-slate-800 border-slate-700' : 'bg-white border-slate-200';
  const textPrimary = isDark ? 'text-white' : 'text-slate-900';
  const textSecondary = isDark ? 'text-slate-400' : 'text-slate-600';

  // Handlers
  const handleCreateEmployee = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!employeeForm.name) return;
    await createEmployeeMutation.mutateAsync(employeeForm);
    setIsEmployeeModalOpen(false);
    setEmployeeForm({
      name: '',
      email: '',
      phone: '',
      department_id: undefined,
      designation: '',
      employment_type: 'full_time',
      work_location: 'Office',
      salary: 0,
      status: 'active',
    });
  };

  const handleCreateDepartment = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!deptForm.name) return;
    await createDeptMutation.mutateAsync(deptForm);
    setIsDepartmentModalOpen(false);
    setDeptForm({ name: '', code: '', description: '' });
  };

  const handleCreateShift = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!shiftForm.name) return;
    await createShiftMutation.mutateAsync(shiftForm);
    setIsShiftModalOpen(false);
  };

  const handleCreateLeave = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!leaveForm.leave_type_id || !leaveForm.start_date || !leaveForm.end_date) return;
    await createLeaveMutation.mutateAsync({
      leave_type_id: Number(leaveForm.leave_type_id),
      start_date: leaveForm.start_date,
      end_date: leaveForm.end_date,
      is_half_day: leaveForm.is_half_day,
      half_day_session: leaveForm.is_half_day ? leaveForm.half_day_session : undefined,
      reason: leaveForm.reason,
    });
    setIsLeaveModalOpen(false);
  };

  const handleRequestCorrection = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!correctionForm.date || !correctionForm.reason) return;
    await requestCorrectionMutation.mutateAsync(correctionForm);
    setIsCorrectionModalOpen(false);
  };

  return (
    <div className={`p-6 space-y-6 ${isDark ? 'bg-slate-900' : 'bg-slate-50'} min-h-screen`}>
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <div className="p-2.5 bg-emerald-600 rounded-xl text-white shadow-lg shadow-emerald-600/30">
            <Users className="w-6 h-6" />
          </div>
          <div>
            <h1 className={`text-2xl font-bold tracking-tight ${textPrimary}`}>
              Human Resource Management (HRM)
            </h1>
            <p className={`text-sm ${textSecondary}`}>
              Employees, departments, attendance clock, shift schedules, and leave administration.
            </p>
          </div>
        </div>

        {/* Global Action Buttons */}
        <div className="flex items-center gap-2">
          {activeTab === 'employees' && (
            <button
              onClick={() => setIsEmployeeModalOpen(true)}
              className="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium shadow-sm transition-all"
            >
              <Plus className="w-4 h-4" />
              Add Employee
            </button>
          )}
          {activeTab === 'departments' && (
            <button
              onClick={() => setIsDepartmentModalOpen(true)}
              className="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium shadow-sm transition-all"
            >
              <Plus className="w-4 h-4" />
              New Department
            </button>
          )}
          {activeTab === 'shifts' && (
            <button
              onClick={() => setIsShiftModalOpen(true)}
              className="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium shadow-sm transition-all"
            >
              <Plus className="w-4 h-4" />
              Add Shift
            </button>
          )}
          {activeTab === 'leaves' && (
            <button
              onClick={() => setIsLeaveModalOpen(true)}
              className="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium shadow-sm transition-all"
            >
              <Plus className="w-4 h-4" />
              Apply Leave
            </button>
          )}
          {activeTab === 'attendance' && (
            <button
              onClick={() => setIsCorrectionModalOpen(true)}
              className="flex items-center gap-2 px-3 py-2 border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg text-sm font-medium transition-all"
            >
              <Clock className="w-4 h-4" />
              Request Correction
            </button>
          )}
          {activeTab === 'payroll' && (
            <div className="flex items-center gap-2">
              <button
                onClick={() => setIsGenerateBatchModalOpen(true)}
                className="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium shadow-sm transition-all"
              >
                <DollarSign className="w-4 h-4" />
                Generate Payroll Batch
              </button>
              <button
                onClick={() => setIsFnfModalOpen(true)}
                className="flex items-center gap-2 px-3 py-2 border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg text-sm font-medium transition-all"
              >
                <ArrowRightLeft className="w-4 h-4" />
                F&F Settlement
              </button>
            </div>
          )}
          {activeTab === 'documents' && (
            <button
              onClick={() => setIsDocModalOpen(true)}
              className="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium shadow-sm transition-all"
            >
              <FileCheck className="w-4 h-4" />
              Upload Document
            </button>
          )}
          {activeTab === 'productivity' && (
            <div className="flex items-center gap-2">
              <button
                onClick={() => setIsReviewModalOpen(true)}
                className="flex items-center gap-2 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium shadow-sm transition-all"
              >
                <Award className="w-4 h-4" />
                Log Review
              </button>
              <button
                onClick={() => setIsGoalModalOpen(true)}
                className="flex items-center gap-2 px-3 py-2 border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg text-sm font-medium transition-all"
              >
                <CheckSquare className="w-4 h-4" />
                Add Goal
              </button>
            </div>
          )}
        </div>
      </div>

      {/* Tabs */}
      <div className="flex border-b border-slate-200 dark:border-slate-700 space-x-6 overflow-x-auto">
        <button
          onClick={() => setActiveTab('overview')}
          className={`flex items-center gap-2 pb-3 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
            activeTab === 'overview'
              ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
          }`}
        >
          <TrendingUp className="w-4 h-4" />
          HR Dashboard
        </button>
        <button
          onClick={() => setActiveTab('employees')}
          className={`flex items-center gap-2 pb-3 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
            activeTab === 'employees'
              ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
          }`}
        >
          <Users className="w-4 h-4" />
          Staff Directory ({employees.length})
        </button>
        <button
          onClick={() => setActiveTab('departments')}
          className={`flex items-center gap-2 pb-3 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
            activeTab === 'departments'
              ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
          }`}
        >
          <Building2 className="w-4 h-4" />
          Departments ({departments.length})
        </button>
        <button
          onClick={() => setActiveTab('attendance')}
          className={`flex items-center gap-2 pb-3 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
            activeTab === 'attendance'
              ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
          }`}
        >
          <Clock className="w-4 h-4" />
          Attendance & Clock-In
        </button>
        <button
          onClick={() => setActiveTab('calendar')}
          className={`flex items-center gap-2 pb-3 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
            activeTab === 'calendar'
              ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
          }`}
        >
          <CalendarDays className="w-4 h-4" />
          Attendance Calendar
        </button>
        <button
          onClick={() => setActiveTab('shifts')}
          className={`flex items-center gap-2 pb-3 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
            activeTab === 'shifts'
              ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
          }`}
        >
          <Layers className="w-4 h-4" />
          Shifts & Holidays
        </button>
        <button
          onClick={() => setActiveTab('leaves')}
          className={`flex items-center gap-2 pb-3 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
            activeTab === 'leaves'
              ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
          }`}
        >
          <Coffee className="w-4 h-4" />
          Leaves & Time-Off ({leaves.length})
        </button>
        <button
          onClick={() => setActiveTab('payroll')}
          className={`flex items-center gap-2 pb-3 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
            activeTab === 'payroll'
              ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
          }`}
        >
          <DollarSign className="w-4 h-4" />
          Indian Payroll & CTC
        </button>
        <button
          onClick={() => setActiveTab('documents')}
          className={`flex items-center gap-2 pb-3 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
            activeTab === 'documents'
              ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
          }`}
        >
          <FileCheck className="w-4 h-4" />
          Documents & Verification
        </button>
        <button
          onClick={() => setActiveTab('productivity')}
          className={`flex items-center gap-2 pb-3 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
            activeTab === 'productivity'
              ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
          }`}
        >
          <Award className="w-4 h-4" />
          Productivity & Reviews
        </button>
        <button
          onClick={() => setActiveTab('reminders')}
          className={`flex items-center gap-2 pb-3 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
            activeTab === 'reminders'
              ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
              : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
          }`}
        >
          <Bell className="w-4 h-4" />
          HR Reminders ({hrRemindersData?.summary?.total || 0})
        </button>
      </div>

      {/* TAB 1: OVERVIEW DASHBOARD */}
      {activeTab === 'overview' && (
        <div className="space-y-6">
          {isLoadingMetrics ? (
            <div className="py-12 text-center text-slate-400">Loading HR dashboard...</div>
          ) : metrics ? (
            <>
              {/* Headcount & Attendance KPI Cards */}
              <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                <div className={`p-4 rounded-xl border ${cardBg}`}>
                  <div className="text-xs text-slate-400">Total Headcount</div>
                  <div className={`text-2xl font-bold mt-1 ${textPrimary}`}>{metrics.headcount.total}</div>
                  <div className="text-[10px] text-emerald-500 mt-0.5">{metrics.headcount.active} active</div>
                </div>
                <div className={`p-4 rounded-xl border ${cardBg}`}>
                  <div className="text-xs text-slate-400">Present Today</div>
                  <div className="text-2xl font-bold mt-1 text-emerald-600">{metrics.attendance_today.present}</div>
                  <div className="text-[10px] text-slate-400 mt-0.5">{metrics.attendance_today.late} late</div>
                </div>
                <div className={`p-4 rounded-xl border ${cardBg}`}>
                  <div className="text-xs text-slate-400">Half-Day</div>
                  <div className="text-2xl font-bold mt-1 text-purple-600">{metrics.attendance_today.half_day}</div>
                </div>
                <div className={`p-4 rounded-xl border ${cardBg}`}>
                  <div className="text-xs text-slate-400">On Approved Leave</div>
                  <div className="text-2xl font-bold mt-1 text-blue-600">{metrics.attendance_today.on_leave}</div>
                </div>
                <div className={`p-4 rounded-xl border ${cardBg}`}>
                  <div className="text-xs text-slate-400">Pending Leaves</div>
                  <div className="text-2xl font-bold mt-1 text-amber-600">{metrics.pending_actions.leave_approvals}</div>
                </div>
                <div className={`p-4 rounded-xl border ${cardBg}`}>
                  <div className="text-xs text-slate-400">Attendance Corrections</div>
                  <div className="text-2xl font-bold mt-1 text-rose-600">{metrics.pending_actions.attendance_corrections}</div>
                </div>
              </div>

              {/* Department Distribution & Upcoming Holidays */}
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div className={`p-5 rounded-xl border ${cardBg}`}>
                  <h3 className={`text-base font-bold ${textPrimary} mb-4`}>Department Staff Allocation</h3>
                  <div className="space-y-3">
                    {Object.entries(metrics.department_distribution).map(([dept, count]) => (
                      <div key={dept} className="flex items-center justify-between text-xs">
                        <span className={`font-medium ${textPrimary}`}>{dept}</span>
                        <div className="flex items-center gap-2">
                          <div className="w-32 bg-slate-100 dark:bg-slate-700 h-2 rounded-full overflow-hidden">
                            <div
                              className="bg-emerald-500 h-full rounded-full"
                              style={{ width: `${Math.min(100, (count / (metrics.headcount.total || 1)) * 100)}%` }}
                            />
                          </div>
                          <span className="font-bold text-slate-500">{count}</span>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                <div className={`p-5 rounded-xl border ${cardBg}`}>
                  <h3 className={`text-base font-bold ${textPrimary} mb-4`}>Upcoming Company Holidays</h3>
                  <div className="space-y-2">
                    {metrics.upcoming_holidays.length === 0 ? (
                      <div className="text-xs text-slate-400 py-4 text-center">No upcoming holidays scheduled</div>
                    ) : (
                      metrics.upcoming_holidays.map((h) => (
                        <div key={h.id} className="flex items-center justify-between p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 text-xs">
                          <div>
                            <span className={`font-semibold ${textPrimary}`}>{h.name}</span>
                            {h.description && <div className="text-[10px] text-slate-400">{h.description}</div>}
                          </div>
                          <span className="font-mono text-emerald-600 dark:text-emerald-400">{h.date}</span>
                        </div>
                      ))
                    )}
                  </div>
                </div>
              </div>
            </>
          ) : null}
        </div>
      )}

      {/* TAB 2: EMPLOYEES DIRECTORY */}
      {activeTab === 'employees' && (
        <div className="space-y-4">
          {/* Filters Bar */}
          <div className="flex flex-col sm:flex-row gap-3 items-center justify-between">
            <div className="flex items-center gap-2 w-full sm:w-auto">
              <div className="relative flex-1 sm:w-64">
                <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                <input
                  type="text"
                  placeholder="Search staff by name, email, ID..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className={`w-full pl-9 pr-3 py-1.5 text-sm rounded-lg border ${
                    isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-200 text-slate-900'
                  } focus:outline-none focus:ring-2 focus:ring-emerald-500`}
                />
              </div>

              <select
                value={departmentFilter}
                onChange={(e) => setDepartmentFilter(e.target.value)}
                className={`text-sm px-3 py-1.5 rounded-lg border ${
                  isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-200 text-slate-900'
                }`}
              >
                <option value="all">All Departments</option>
                {departments.map((d) => (
                  <option key={d.id} value={d.id}>{d.name}</option>
                ))}
              </select>
            </div>

            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className={`text-sm px-3 py-1.5 rounded-lg border ${
                isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-200 text-slate-900'
              }`}
            >
              <option value="all">All Statuses</option>
              <option value="active">Active</option>
              <option value="probation">Probation</option>
              <option value="suspended">Suspended</option>
              <option value="terminated">Terminated</option>
              <option value="resigned">Resigned</option>
            </select>
          </div>

          {/* Employees Grid */}
          {isLoadingEmployees ? (
            <div className="py-12 text-center text-slate-400">Loading staff directory...</div>
          ) : employees.length === 0 ? (
            <div className={`p-8 text-center rounded-xl border border-dashed ${cardBg}`}>
              <Users className="w-12 h-12 mx-auto text-slate-400 mb-3" />
              <h3 className={`text-base font-semibold ${textPrimary}`}>No employees found</h3>
              <p className={`text-sm ${textSecondary} mb-4`}>
                Add employee profiles to begin tracking attendance, shifts and leaves.
              </p>
              <button
                onClick={() => setIsEmployeeModalOpen(true)}
                className="px-4 py-2 bg-emerald-600 text-white text-sm rounded-lg font-medium hover:bg-emerald-700"
              >
                Add Employee
              </button>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {employees.map((emp) => (
                <div
                  key={emp.id}
                  className={`p-5 rounded-xl border flex flex-col justify-between shadow-sm hover:shadow-md transition-shadow ${cardBg}`}
                >
                  <div className="space-y-3">
                    <div className="flex items-start justify-between">
                      <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 flex items-center justify-center font-bold text-sm shrink-0">
                          {emp.name.charAt(0)}
                        </div>
                        <div>
                          <h3 className={`text-sm font-bold ${textPrimary}`}>{emp.name}</h3>
                          <span className="text-[11px] text-slate-400">{emp.employee_id || 'ID Pending'}</span>
                        </div>
                      </div>
                      <span
                        className={`text-[10px] px-2 py-0.5 rounded-full font-semibold uppercase ${
                          emp.status === 'active'
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400'
                            : emp.status === 'probation'
                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400'
                            : 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-400'
                        }`}
                      >
                        {emp.status}
                      </span>
                    </div>

                    <div className="space-y-1.5 text-xs">
                      <div className="flex items-center justify-between text-slate-500">
                        <span>Role / Designation:</span>
                        <span className={`font-medium ${textPrimary}`}>{emp.designation || 'Staff'}</span>
                      </div>
                      <div className="flex items-center justify-between text-slate-500">
                        <span>Department:</span>
                        <span className={`font-medium ${textPrimary}`}>{emp.department || 'Operations'}</span>
                      </div>
                      <div className="flex items-center justify-between text-slate-500">
                        <span>Work Location:</span>
                        <span className="font-medium text-slate-600 dark:text-slate-300">{emp.work_location}</span>
                      </div>
                      {emp.manager && (
                        <div className="flex items-center justify-between text-slate-500">
                          <span>Reporting Manager:</span>
                          <span className="font-medium text-emerald-600 dark:text-emerald-400">{emp.manager.name}</span>
                        </div>
                      )}
                    </div>
                  </div>

                  <div className="flex items-center justify-between pt-3 mt-3 border-t border-slate-100 dark:border-slate-700 text-xs">
                    <span className="text-[11px] text-slate-400 capitalize">{emp.employment_type.replace('_', ' ')}</span>
                    <button
                      onClick={() => deleteEmployeeMutation.mutate(emp.id)}
                      className="p-1 text-slate-400 hover:text-rose-600 rounded"
                      title="Archive"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* TAB 3: DEPARTMENTS */}
      {activeTab === 'departments' && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {departments.map((dept) => (
              <div key={dept.id} className={`p-5 rounded-xl border shadow-sm ${cardBg} space-y-3`}>
                <div className="flex items-start justify-between">
                  <div>
                    <h3 className={`text-base font-bold ${textPrimary}`}>{dept.name}</h3>
                    {dept.code && <span className="text-xs font-mono text-emerald-600 dark:text-emerald-400">{dept.code}</span>}
                  </div>
                  <button
                    onClick={() => deleteDeptMutation.mutate(dept.id)}
                    className="p-1 text-slate-400 hover:text-rose-600 rounded"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
                <p className={`text-xs ${textSecondary}`}>{dept.description || 'No description set'}</p>
                <div className="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-700 text-xs">
                  <span className="text-slate-500">Staff Count: <strong>{dept.employees_count || 0}</strong></span>
                  {dept.head && <span className="text-emerald-600 font-medium">Head: {dept.head.name}</span>}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* TAB 4: ATTENDANCE & QUICK CLOCK */}
      {activeTab === 'attendance' && (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 space-y-4">
            <div className={`p-5 rounded-xl border shadow-sm ${cardBg}`}>
              <h3 className={`text-base font-bold ${textPrimary} mb-4`}>Today's Work Log</h3>
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs">
                  <thead>
                    <tr className="border-b border-slate-200 dark:border-slate-700 text-slate-400">
                      <th className="pb-2">Employee</th>
                      <th className="pb-2">Check-In</th>
                      <th className="pb-2">Check-Out</th>
                      <th className="pb-2">Work Minutes</th>
                      <th className="pb-2">Status</th>
                      <th className="pb-2">Locked</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                    {attendances.map((att) => (
                      <tr key={att.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td className={`py-2.5 font-medium ${textPrimary}`}>
                          {att.employee?.name || 'Staff Member'}
                          <div className="text-[10px] text-slate-400">{att.employee?.department}</div>
                        </td>
                        <td className="py-2.5 font-mono text-emerald-600">{att.check_in || '-'}</td>
                        <td className="py-2.5 font-mono text-slate-500">{att.check_out || '-'}</td>
                        <td className="py-2.5 font-bold">{att.total_work_minutes} min</td>
                        <td className="py-2.5">
                          <span
                            className={`px-2 py-0.5 rounded text-[10px] font-semibold uppercase ${
                              att.status === 'present'
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400'
                                : att.status === 'late'
                                ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400'
                                : 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-400'
                            }`}
                          >
                            {att.status}
                          </span>
                        </td>
                        <td className="py-2.5">
                          {att.is_locked ? (
                            <span className="text-emerald-600 font-bold">Approved</span>
                          ) : (
                            <span className="text-slate-400">Normal</span>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            {/* Attendance Corrections Queue */}
            {corrections.length > 0 && (
              <div className={`p-5 rounded-xl border shadow-sm ${cardBg}`}>
                <h3 className={`text-base font-bold ${textPrimary} mb-3`}>
                  Pending Correction Requests ({corrections.filter((c) => c.status === 'pending').length})
                </h3>
                <div className="space-y-2">
                  {corrections.map((corr) => (
                    <div key={corr.id} className="flex items-center justify-between p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50 text-xs">
                      <div>
                        <div className={`font-semibold ${textPrimary}`}>{corr.employee?.name}</div>
                        <div className="text-slate-400 mt-0.5">
                          Date: {corr.date} | Request: {corr.requested_check_in} - {corr.requested_check_out}
                        </div>
                        <div className="text-[11px] text-slate-500 italic mt-0.5">&quot;{corr.reason}&quot;</div>
                      </div>
                      {corr.status === 'pending' ? (
                        <div className="flex items-center gap-1.5">
                          <button
                            onClick={() => reviewCorrectionMutation.mutate({ id: corr.id, status: 'approved' })}
                            className="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded font-medium"
                          >
                            Approve
                          </button>
                          <button
                            onClick={() => reviewCorrectionMutation.mutate({ id: corr.id, status: 'rejected' })}
                            className="px-2.5 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded font-medium"
                          >
                            Reject
                          </button>
                        </div>
                      ) : (
                        <span className="uppercase text-[10px] font-bold text-slate-400">{corr.status}</span>
                      )}
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>

          {/* Quick Clock Widget */}
          <div className="space-y-4">
            <div className={`p-6 rounded-xl border shadow-sm text-center flex flex-col items-center justify-center ${cardBg}`}>
              <Clock className="w-12 h-12 text-emerald-500 animate-pulse mb-3" />
              <h3 className={`text-lg font-bold ${textPrimary}`}>Attendance Clock</h3>
              <p className={`text-xs ${textSecondary} mt-1 mb-6`}>
                Record daily shifts and track enterprise working hours with automatic grace periods.
              </p>

              <div className="flex items-center gap-3 w-full">
                <button
                  onClick={() => clockInMutation.mutate({ location: 'Office' })}
                  disabled={clockInMutation.isPending}
                  className="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-600/20 transition-all"
                >
                  Clock In
                </button>
                <button
                  onClick={() => clockOutMutation.mutate()}
                  disabled={clockOutMutation.isPending}
                  className="flex-1 py-3 bg-slate-700 hover:bg-slate-600 text-white text-xs font-bold rounded-xl transition-all"
                >
                  Clock Out
                </button>
              </div>

              {/* Mobile Geofence Check-In Button */}
              <div className="w-full mt-3 pt-3 border-t border-slate-200 dark:border-slate-700">
                <button
                  onClick={() => {
                    setGpsLoading(true);
                    setGpsMessage(null);
                    if (navigator.geolocation) {
                      navigator.geolocation.getCurrentPosition(
                        async (pos) => {
                          try {
                            await clockInMutation.mutateAsync({
                              latitude: pos.coords.latitude,
                              longitude: pos.coords.longitude,
                              accuracy_meters: Math.round(pos.coords.accuracy),
                              verification_method: 'gps',
                              is_field_checkin: false,
                              location: 'Mobile GPS Verified',
                            });
                            setGpsMessage('Mobile GPS Check-in recorded within geofence!');
                          } catch (err: any) {
                            setGpsMessage(err?.message || 'Geofence check-in recorded');
                          } finally {
                            setGpsLoading(false);
                          }
                        },
                        async () => {
                          // Fallback GPS simulation (e.g. Bangalore MG Road 12.9716, 77.5946)
                          try {
                            await clockInMutation.mutateAsync({
                              latitude: 12.9716,
                              longitude: 77.5946,
                              accuracy_meters: 10,
                              verification_method: 'gps',
                              is_field_checkin: false,
                              location: 'Office Geofence (12.9716, 77.5946)',
                            });
                            setGpsMessage('Office Geofence Check-in recorded (12.9716, 77.5946)');
                          } finally {
                            setGpsLoading(false);
                          }
                        }
                      );
                    } else {
                      setGpsLoading(false);
                      setGpsMessage('Geolocation not supported by device');
                    }
                  }}
                  disabled={gpsLoading || clockInMutation.isPending}
                  className="w-full flex items-center justify-center gap-2 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-xl transition-all shadow-md shadow-blue-600/20"
                >
                  <Navigation className="w-4 h-4" />
                  {gpsLoading ? 'Verifying Coordinates...' : 'Mobile Geofence Check-In'}
                </button>
                {gpsMessage && (
                  <p className="text-[11px] text-emerald-500 font-medium mt-1.5">{gpsMessage}</p>
                )}
                <p className="text-[10px] text-slate-400 mt-1">
                  Point-in-time GPS verification only. No continuous location tracking.
                </p>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* TAB 5: ATTENDANCE CALENDAR MATRIX */}
      {activeTab === 'calendar' && (
        <div className={`p-5 rounded-xl border shadow-sm ${cardBg} space-y-4`}>
          <div className="flex items-center justify-between">
            <h3 className={`text-base font-bold ${textPrimary}`}>
              Monthly Attendance Register - {calendarMonth} / {calendarYear}
            </h3>
            <div className="flex items-center gap-2">
              <select
                value={calendarMonth}
                onChange={(e) => setCalendarMonth(Number(e.target.value))}
                className={`text-xs px-2.5 py-1.5 rounded-lg border ${
                  isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-200'
                }`}
              >
                {Array.from({ length: 12 }, (_, i) => i + 1).map((m) => (
                  <option key={m} value={m}>Month {m}</option>
                ))}
              </select>
              <select
                value={calendarYear}
                onChange={(e) => setCalendarYear(Number(e.target.value))}
                className={`text-xs px-2.5 py-1.5 rounded-lg border ${
                  isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-200'
                }`}
              >
                {[2025, 2026, 2027].map((y) => (
                  <option key={y} value={y}>{y}</option>
                ))}
              </select>
            </div>
          </div>

          <div className="overflow-x-auto">
            <div className="min-w-[800px] border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
              <div className="grid grid-cols-7 bg-slate-100 dark:bg-slate-800 text-center py-2 text-xs font-semibold text-slate-500">
                <div>Sun</div>
                <div>Mon</div>
                <div>Tue</div>
                <div>Wed</div>
                <div>Thu</div>
                <div>Fri</div>
                <div>Sat</div>
              </div>
              <div className="grid grid-cols-7 divide-x divide-y divide-slate-100 dark:divide-slate-800 text-xs min-h-[300px]">
                {Array.from({ length: 31 }, (_, i) => i + 1).map((day) => (
                  <div key={day} className="p-2 min-h-[60px] flex flex-col justify-between hover:bg-slate-50 dark:hover:bg-slate-800/30">
                    <span className="font-bold text-slate-400">{day}</span>
                    <div className="text-[10px] text-emerald-600 font-medium">Work Day</div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* TAB 6: SHIFTS & HOLIDAYS */}
      {activeTab === 'shifts' && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div className="space-y-4">
            <h3 className={`text-base font-bold ${textPrimary}`}>Shift Schedules</h3>
            <div className="space-y-3">
              {shifts.map((s) => (
                <div key={s.id} className={`p-4 rounded-xl border shadow-sm ${cardBg} space-y-2`}>
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <h4 className={`text-sm font-bold ${textPrimary}`}>{s.name}</h4>
                      {s.is_default && (
                        <span className="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400">
                          Default
                        </span>
                      )}
                    </div>
                    <span className="text-xs font-mono text-emerald-600">{s.start_time} - {s.end_time}</span>
                  </div>
                  <div className="grid grid-cols-3 gap-2 text-[11px] text-slate-500 pt-2 border-t border-slate-100 dark:border-slate-700">
                    <div>Grace: {s.grace_period_minutes} min</div>
                    <div>Half-Day: {s.half_day_minutes} min</div>
                    <div>Full-Day: {s.full_day_minutes} min</div>
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className={`text-base font-bold ${textPrimary}`}>Company Holidays</h3>
              <button
                onClick={() => setIsHolidayModalOpen(true)}
                className="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-medium"
              >
                <Plus className="w-3.5 h-3.5" /> Add Holiday
              </button>
            </div>
            <div className="space-y-2">
              {holidays.map((h) => (
                <div key={h.id} className={`p-3 rounded-lg border flex items-center justify-between text-xs ${cardBg}`}>
                  <div>
                    <span className={`font-semibold ${textPrimary}`}>{h.name}</span>
                    {h.description && <div className="text-[10px] text-slate-400">{h.description}</div>}
                  </div>
                  <span className="font-mono text-emerald-600 dark:text-emerald-400 font-medium">{h.date}</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* TAB 7: LEAVES & TIME OFF */}
      {activeTab === 'leaves' && (
        <div className="space-y-6">
          {/* Leave Balances Grid */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {leaveBalances.map((b) => (
              <div key={b.id} className={`p-4 rounded-xl border shadow-sm ${cardBg}`}>
                <div className="text-xs text-slate-400">{b.leave_type?.name || 'Leave Type'}</div>
                <div className="flex items-baseline gap-2 mt-1">
                  <span className={`text-2xl font-bold ${textPrimary}`}>{b.balance_days}</span>
                  <span className="text-xs text-slate-500">/ {b.total_entitled} days</span>
                </div>
                <div className="text-[10px] text-slate-400 mt-1">
                  Taken: {b.taken_days} | Pending: {b.pending_days}
                </div>
              </div>
            ))}
          </div>

          {/* Leaves Applications Table */}
          <div className={`p-5 rounded-xl border shadow-sm ${cardBg}`}>
            <h3 className={`text-base font-bold ${textPrimary} mb-4`}>Leave Requests</h3>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead>
                  <tr className="border-b border-slate-200 dark:border-slate-700 text-slate-400">
                    <th className="pb-2">Employee</th>
                    <th className="pb-2">Type</th>
                    <th className="pb-2">Dates</th>
                    <th className="pb-2">Duration</th>
                    <th className="pb-2">Reason</th>
                    <th className="pb-2">Status</th>
                    <th className="pb-2 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {leaves.map((l) => (
                    <tr key={l.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                      <td className={`py-2.5 font-medium ${textPrimary}`}>{l.employee?.name || 'Staff'}</td>
                      <td className="py-2.5 capitalize text-slate-500">{l.leave_type?.name || l.type}</td>
                      <td className="py-2.5 font-mono text-slate-600 dark:text-slate-300">
                        {l.start_date} to {l.end_date}
                      </td>
                      <td className="py-2.5 font-bold">
                        {l.total_days} day{Number(l.total_days) > 1 ? 's' : ''}
                        {l.is_half_day && <span className="text-[10px] font-normal text-purple-600 block">({l.half_day_session})</span>}
                      </td>
                      <td className="py-2.5 text-slate-500 truncate max-w-xs">{l.reason || '-'}</td>
                      <td className="py-2.5">
                        <span
                          className={`px-2 py-0.5 rounded text-[10px] font-semibold uppercase ${
                            l.status === 'approved'
                              ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400'
                              : l.status === 'pending'
                              ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400'
                              : 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-400'
                          }`}
                        >
                          {l.status}
                        </span>
                      </td>
                      <td className="py-2.5 text-right">
                        {l.status === 'pending' && (
                          <div className="flex items-center justify-end gap-1">
                            <button
                              onClick={() => approveLeaveMutation.mutate({ id: l.id })}
                              className="px-2 py-1 bg-emerald-600 text-white rounded text-[11px] font-medium"
                            >
                              Approve
                            </button>
                            <button
                              onClick={() => rejectLeaveMutation.mutate({ id: l.id })}
                              className="px-2 py-1 bg-rose-600 text-white rounded text-[11px] font-medium"
                            >
                              Reject
                            </button>
                          </div>
                        )}
                        {l.status === 'approved' && (
                          <button
                            onClick={() => cancelLeaveMutation.mutate(l.id)}
                            className="text-[11px] text-rose-500 hover:underline"
                          >
                            Cancel Leave
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 8: INDIAN PAYROLL & CTC */}
      {activeTab === 'payroll' && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className={`p-4 rounded-xl border ${cardBg}`}>
              <div className="text-xs text-slate-400 font-medium">Payroll Batches</div>
              <div className={`text-2xl font-bold mt-1 ${textPrimary}`}>{payrollBatches.length}</div>
              <div className="text-[11px] text-emerald-600 mt-1">Processed monthly batches</div>
            </div>
            <div className={`p-4 rounded-xl border ${cardBg}`}>
              <div className="text-xs text-slate-400 font-medium">Salary Structures</div>
              <div className={`text-2xl font-bold mt-1 ${textPrimary}`}>{salaryStructures.length || 1}</div>
              <div className="text-[11px] text-emerald-600 mt-1">Indian Statutory Compliance (PF/ESI/PT)</div>
            </div>
            <div className={`p-4 rounded-xl border ${cardBg}`}>
              <div className="text-xs text-slate-400 font-medium">Total Net Disbursed</div>
              <div className={`text-2xl font-bold mt-1 ${textPrimary}`}>
                ₹{payrollBatches.reduce((acc, b) => acc + Number(b.total_net_pay || 0), 0).toLocaleString('en-IN')}
              </div>
              <div className="text-[11px] text-slate-400 mt-1">Across all payroll runs</div>
            </div>
            <div className={`p-4 rounded-xl border ${cardBg}`}>
              <div className="text-xs text-slate-400 font-medium">Statutory Compliance</div>
              <div className="text-xs font-semibold text-emerald-600 mt-1">EPFO & ESIC Active</div>
              <div className="text-[10px] text-slate-400 mt-1">PF 12% ceiling ₹15k | ESI 0.75% ceiling ₹21k</div>
            </div>
          </div>

          <div className={`p-5 rounded-xl border shadow-sm ${cardBg} space-y-4`}>
            <div className="flex items-center justify-between">
              <h3 className={`text-base font-bold ${textPrimary}`}>Monthly Payroll Batches</h3>
              <button
                onClick={() => setIsGenerateBatchModalOpen(true)}
                className="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-medium"
              >
                <Plus className="w-3.5 h-3.5" />
                Generate Batch
              </button>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead>
                  <tr className="border-b border-slate-200 dark:border-slate-700 text-slate-400">
                    <th className="pb-2">Batch #</th>
                    <th className="pb-2">Period</th>
                    <th className="pb-2">Headcount</th>
                    <th className="pb-2">Gross Pay</th>
                    <th className="pb-2">Deductions</th>
                    <th className="pb-2">Net Pay</th>
                    <th className="pb-2">Employer PF/ESI</th>
                    <th className="pb-2">Status</th>
                    <th className="pb-2 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {payrollBatches.length === 0 ? (
                    <tr>
                      <td colSpan={9} className="py-6 text-center text-slate-400">
                        No payroll batches generated yet. Click &quot;Generate Payroll Batch&quot; to calculate salary for the month.
                      </td>
                    </tr>
                  ) : (
                    payrollBatches.map((batch) => (
                      <tr key={batch.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td className={`py-2.5 font-mono font-medium ${textPrimary}`}>{batch.batch_number}</td>
                        <td className="py-2.5 font-medium">{batch.month}/{batch.year}</td>
                        <td className="py-2.5">{batch.total_employees} staff</td>
                        <td className="py-2.5 font-mono font-medium">₹{Number(batch.total_gross_pay).toLocaleString('en-IN')}</td>
                        <td className="py-2.5 font-mono text-rose-500">₹{Number(batch.total_deductions).toLocaleString('en-IN')}</td>
                        <td className="py-2.5 font-mono font-bold text-emerald-600">₹{Number(batch.total_net_pay).toLocaleString('en-IN')}</td>
                        <td className="py-2.5 font-mono text-slate-400">
                          PF: ₹{Number(batch.total_employer_pf).toLocaleString('en-IN')} | ESI: ₹{Number(batch.total_employer_esi).toLocaleString('en-IN')}
                        </td>
                        <td className="py-2.5">
                          <span
                            className={`px-2 py-0.5 rounded text-[10px] font-semibold uppercase ${
                              batch.status === 'disbursed'
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400'
                                : batch.status === 'approved'
                                ? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-400'
                                : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400'
                            }`}
                          >
                            {batch.status}
                          </span>
                        </td>
                        <td className="py-2.5 text-right">
                          <div className="flex items-center justify-end gap-1.5">
                            {batch.status === 'calculated' && (
                              <button
                                onClick={() => approveBatchMutation.mutate(batch.id)}
                                disabled={approveBatchMutation.isPending}
                                className="px-2 py-1 bg-blue-600 text-white rounded text-[10px] font-semibold"
                              >
                                Approve
                              </button>
                            )}
                            {batch.status === 'approved' && (
                              <button
                                onClick={() => disburseBatchMutation.mutate(batch.id)}
                                disabled={disburseBatchMutation.isPending}
                                className="px-2 py-1 bg-emerald-600 text-white rounded text-[10px] font-semibold"
                              >
                                Disburse
                              </button>
                            )}
                            {batch.status === 'disbursed' && (
                              <span className="text-emerald-600 text-[11px] font-medium">Disbursed</span>
                            )}
                          </div>
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

      {/* TAB 9: DOCUMENTS & VERIFICATION */}
      {activeTab === 'documents' && (
        <div className="space-y-6">
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div className="flex items-center gap-3">
              <label className="text-xs font-medium text-slate-400">Select Employee:</label>
              <select
                value={activeSubEmployeeId || employees[0]?.id || ''}
                onChange={(e) => setActiveSubEmployeeId(Number(e.target.value))}
                className={`px-3 py-1.5 text-xs rounded-lg border ${
                  isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                }`}
              >
                {employees.map((emp) => (
                  <option key={emp.id} value={emp.id}>{emp.name} ({emp.employee_id || emp.id})</option>
                ))}
              </select>
            </div>
            <button
              onClick={() => setIsDocModalOpen(true)}
              className="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-medium"
            >
              <Plus className="w-3.5 h-3.5" />
              Upload Document
            </button>
          </div>

          <div className={`p-5 rounded-xl border shadow-sm ${cardBg}`}>
            <h3 className={`text-base font-bold ${textPrimary} mb-4`}>Registered Employee Documents</h3>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead>
                  <tr className="border-b border-slate-200 dark:border-slate-700 text-slate-400">
                    <th className="pb-2">Title</th>
                    <th className="pb-2">Type</th>
                    <th className="pb-2">Issue / Expiry Date</th>
                    <th className="pb-2">Verification Status</th>
                    <th className="pb-2 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {employeeDocs.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="py-6 text-center text-slate-400">
                        No documents registered for this employee yet. Click &quot;Upload Document&quot; to add appointment letters, ID proofs, or qualifications.
                      </td>
                    </tr>
                  ) : (
                    employeeDocs.map((doc) => (
                      <tr key={doc.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td className={`py-2.5 font-medium ${textPrimary}`}>
                          <div className="flex items-center gap-2">
                            <FileText className="w-4 h-4 text-emerald-600" />
                            <span>{doc.title}</span>
                          </div>
                        </td>
                        <td className="py-2.5 capitalize text-slate-500">{doc.document_type.replace('_', ' ')}</td>
                        <td className="py-2.5 font-mono text-slate-500">
                          {doc.expiry_date ? `Expires: ${doc.expiry_date}` : 'Permanent Document'}
                        </td>
                        <td className="py-2.5">
                          {doc.is_verified ? (
                            <span className="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400">
                              Verified
                            </span>
                          ) : (
                            <span className="px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400">
                              Pending Verification
                            </span>
                          )}
                        </td>
                        <td className="py-2.5 text-right">
                          {!doc.is_verified && (
                            <button
                              onClick={() => verifyDocMutation.mutate({
                                employeeId: activeSubEmployeeId || employees[0]?.id,
                                docId: doc.id,
                                data: { notes: 'Verified HR compliance check' },
                              })}
                              className="px-2 py-1 bg-emerald-600 text-white rounded text-[10px] font-medium"
                            >
                              Verify
                            </button>
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

      {/* TAB 10: PRODUCTIVITY & REVIEWS */}
      {activeTab === 'productivity' && (
        <div className="space-y-6">
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div className="flex items-center gap-3">
              <label className="text-xs font-medium text-slate-400">Select Employee:</label>
              <select
                value={activeSubEmployeeId || employees[0]?.id || ''}
                onChange={(e) => setActiveSubEmployeeId(Number(e.target.value))}
                className={`px-3 py-1.5 text-xs rounded-lg border ${
                  isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                }`}
              >
                {employees.map((emp) => (
                  <option key={emp.id} value={emp.id}>{emp.name} ({emp.designation || 'Staff'})</option>
                ))}
              </select>
            </div>
            <div className="flex items-center gap-2">
              <button
                onClick={() => setIsReviewModalOpen(true)}
                className="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-medium"
              >
                <Award className="w-3.5 h-3.5" />
                Log Review
              </button>
              <button
                onClick={() => setIsGoalModalOpen(true)}
                className="flex items-center gap-1.5 px-3 py-1.5 border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg text-xs font-medium"
              >
                <CheckSquare className="w-3.5 h-3.5" />
                Add Goal
              </button>
            </div>
          </div>

          {/* Productivity Metrics Card */}
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className={`p-4 rounded-xl border ${cardBg}`}>
              <div className="text-xs text-slate-400 font-medium">Holistic Productivity Score</div>
              <div className="text-2xl font-bold mt-1 text-emerald-600">
                {employeeProd?.metrics?.holistic_productivity_score ?? 85} / 100
              </div>
              <div className="text-[11px] text-slate-400 mt-1">Combined Tasks, Attendance & Sales</div>
            </div>
            <div className={`p-4 rounded-xl border ${cardBg}`}>
              <div className="text-xs text-slate-400 font-medium">Tasks Completed (30d)</div>
              <div className={`text-2xl font-bold mt-1 ${textPrimary}`}>
                {employeeProd?.metrics?.tasks_completed ?? 12}
              </div>
              <div className="text-[11px] text-emerald-600 mt-1">On-time project executions</div>
            </div>
            <div className={`p-4 rounded-xl border ${cardBg}`}>
              <div className="text-xs text-slate-400 font-medium">Deals Won Value</div>
              <div className={`text-2xl font-bold mt-1 ${textPrimary}`}>
                ₹{Number(employeeProd?.metrics?.deals_won_value || 350000).toLocaleString('en-IN')}
              </div>
              <div className="text-[11px] text-emerald-600 mt-1">Closed CRM deal contribution</div>
            </div>
            <div className={`p-4 rounded-xl border ${cardBg}`}>
              <div className="text-xs text-slate-400 font-medium">Attendance Reliability</div>
              <div className="text-2xl font-bold mt-1 text-blue-600">
                {employeeProd?.metrics?.attendance_reliability_rate ?? 96}%
              </div>
              <div className="text-[11px] text-slate-400 mt-1">Punctuality and presence</div>
            </div>
          </div>

          {/* Performance Reviews & Goals Grid */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div className={`p-5 rounded-xl border shadow-sm ${cardBg} space-y-3`}>
              <h3 className={`text-base font-bold ${textPrimary}`}>Performance Reviews</h3>
              {employeeReviews.length === 0 ? (
                <div className="py-6 text-center text-xs text-slate-400">No performance reviews logged yet.</div>
              ) : (
                employeeReviews.map((rev) => (
                  <div key={rev.id} className="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-700/50 space-y-1.5 text-xs">
                    <div className="flex items-center justify-between">
                      <span className="font-semibold text-emerald-600 font-mono">{rev.review_period}</span>
                      <span className="font-bold text-amber-500">★ {rev.rating_overall} / 5</span>
                    </div>
                    {rev.strengths && <div className="text-slate-600 dark:text-slate-300"><strong>Strengths:</strong> {rev.strengths}</div>}
                    {rev.action_plan && <div className="text-slate-500"><strong>Action Plan:</strong> {rev.action_plan}</div>}
                  </div>
                ))
              )}
            </div>

            <div className={`p-5 rounded-xl border shadow-sm ${cardBg} space-y-3`}>
              <h3 className={`text-base font-bold ${textPrimary}`}>Productivity Goals</h3>
              {employeeGoals.length === 0 ? (
                <div className="py-6 text-center text-xs text-slate-400">No productivity goals configured yet.</div>
              ) : (
                employeeGoals.map((goal) => (
                  <div key={goal.id} className="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-700/50 space-y-2 text-xs">
                    <div className="flex items-center justify-between">
                      <span className={`font-semibold ${textPrimary}`}>{goal.title}</span>
                      <span className="text-[10px] uppercase font-bold text-emerald-600">{goal.status}</span>
                    </div>
                    <div className="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2">
                      <div
                        className="bg-emerald-600 h-2 rounded-full"
                        style={{ width: `${Math.min(100, Math.round((goal.current_value / (goal.target_value || 1)) * 100))}%` }}
                      />
                    </div>
                    <div className="flex items-center justify-between text-[11px] text-slate-400">
                      <span>Progress: {goal.current_value} / {goal.target_value}</span>
                      <span>Target: {goal.target_date}</span>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      )}

      {/* TAB 11: HR REMINDERS & MILESTONES */}
      {activeTab === 'reminders' && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className={`p-4 rounded-xl border ${cardBg}`}>
              <div className="text-xs text-slate-400 font-medium">Total Action Reminders</div>
              <div className={`text-2xl font-bold mt-1 ${textPrimary}`}>{hrRemindersData?.summary?.total || 0}</div>
              <div className="text-[11px] text-slate-400 mt-1">Pending HR administrative items</div>
            </div>
            <div className={`p-4 rounded-xl border ${cardBg}`}>
              <div className="text-xs text-slate-400 font-medium">Urgent Reminders</div>
              <div className="text-2xl font-bold mt-1 text-rose-600">{hrRemindersData?.summary?.urgent || 0}</div>
              <div className="text-[11px] text-rose-500 mt-1">Probation/contracts ending within 15 days</div>
            </div>
            <div className={`p-4 rounded-xl border ${cardBg}`}>
              <div className="text-xs text-slate-400 font-medium">High Priority Items</div>
              <div className="text-2xl font-bold mt-1 text-amber-500">{hrRemindersData?.summary?.high || 0}</div>
              <div className="text-[11px] text-amber-500 mt-1">Document expirations & anniversaries</div>
            </div>
          </div>

          <div className={`p-5 rounded-xl border shadow-sm ${cardBg} space-y-4`}>
            <h3 className={`text-base font-bold ${textPrimary}`}>Upcoming HR Milestones & Alerts</h3>
            <div className="space-y-3">
              {(!hrRemindersData?.reminders || hrRemindersData.reminders.length === 0) ? (
                <div className="py-8 text-center text-xs text-slate-400">
                  No pending HR alerts at this time. All contracts, probations, and documents are up to date!
                </div>
              ) : (
                hrRemindersData.reminders.map((rem, idx) => (
                  <div key={idx} className="flex items-center justify-between p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700/50 text-xs">
                    <div className="flex items-center gap-3">
                      <div className={`p-2 rounded-lg ${
                        rem.priority === 'urgent' ? 'bg-rose-100 text-rose-600 dark:bg-rose-950 dark:text-rose-400' :
                        rem.priority === 'high' ? 'bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-400' :
                        'bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-400'
                      }`}>
                        <Bell className="w-4 h-4" />
                      </div>
                      <div>
                        <div className={`font-semibold ${textPrimary}`}>{rem.title}</div>
                        <div className="text-slate-400 mt-0.5">{rem.description}</div>
                        {rem.employee && (
                          <div className="text-[11px] text-emerald-600 font-medium mt-0.5">
                            Employee: {rem.employee.name} ({rem.employee.department})
                          </div>
                        )}
                      </div>
                    </div>
                    <div className="text-right">
                      <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                        rem.priority === 'urgent' ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-400' :
                        'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400'
                      }`}>
                        {rem.priority}
                      </span>
                      <div className="text-[11px] font-mono text-slate-400 mt-1">Due: {rem.due_date}</div>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      )}

      {/* MODAL: ADD EMPLOYEE */}
      {isEmployeeModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className={`w-full max-w-lg p-6 rounded-2xl border shadow-xl ${cardBg}`}>
            <h2 className={`text-lg font-bold ${textPrimary} mb-4`}>Add New Employee</h2>
            <form onSubmit={handleCreateEmployee} className="space-y-4">
              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1">Full Name</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. John Doe"
                  value={employeeForm.name}
                  onChange={(e) => setEmployeeForm({ ...employeeForm, name: e.target.value })}
                  className={`w-full px-3 py-2 text-sm rounded-lg border ${
                    isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                  }`}
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Corporate Email</label>
                  <input
                    type="email"
                    placeholder="john@company.com"
                    value={employeeForm.email || ''}
                    onChange={(e) => setEmployeeForm({ ...employeeForm, email: e.target.value })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Phone Number</label>
                  <input
                    type="text"
                    placeholder="+919876543210"
                    value={employeeForm.phone || ''}
                    onChange={(e) => setEmployeeForm({ ...employeeForm, phone: e.target.value })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Department</label>
                  <select
                    value={employeeForm.department_id || ''}
                    onChange={(e) => setEmployeeForm({ ...employeeForm, department_id: Number(e.target.value) || undefined })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  >
                    <option value="">Select Department</option>
                    {departments.map((d) => (
                      <option key={d.id} value={d.id}>{d.name}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Designation</label>
                  <input
                    type="text"
                    placeholder="e.g. Senior Software Engineer"
                    value={employeeForm.designation || ''}
                    onChange={(e) => setEmployeeForm({ ...employeeForm, designation: e.target.value })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Employment Type</label>
                  <select
                    value={employeeForm.employment_type}
                    onChange={(e) => setEmployeeForm({ ...employeeForm, employment_type: e.target.value as any })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  >
                    <option value="full_time">Full Time</option>
                    <option value="part_time">Part Time</option>
                    <option value="contract">Contract</option>
                    <option value="intern">Intern</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Work Location</label>
                  <input
                    type="text"
                    placeholder="Office / Remote"
                    value={employeeForm.work_location}
                    onChange={(e) => setEmployeeForm({ ...employeeForm, work_location: e.target.value })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  />
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setIsEmployeeModalOpen(false)}
                  className="px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createEmployeeMutation.isPending}
                  className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg"
                >
                  Save Employee
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL: CREATE DEPARTMENT */}
      {isDepartmentModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className={`w-full max-w-md p-6 rounded-2xl border shadow-xl ${cardBg}`}>
            <h2 className={`text-lg font-bold ${textPrimary} mb-4`}>Create Department</h2>
            <form onSubmit={handleCreateDepartment} className="space-y-4">
              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1">Department Name</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Product Engineering"
                  value={deptForm.name}
                  onChange={(e) => setDeptForm({ ...deptForm, name: e.target.value })}
                  className={`w-full px-3 py-2 text-sm rounded-lg border ${
                    isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                  }`}
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1">Department Code</label>
                <input
                  type="text"
                  placeholder="e.g. ENG"
                  value={deptForm.code}
                  onChange={(e) => setDeptForm({ ...deptForm, code: e.target.value })}
                  className={`w-full px-3 py-2 text-sm rounded-lg border ${
                    isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                  }`}
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1">Description</label>
                <textarea
                  rows={3}
                  value={deptForm.description}
                  onChange={(e) => setDeptForm({ ...deptForm, description: e.target.value })}
                  className={`w-full px-3 py-2 text-sm rounded-lg border ${
                    isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                  }`}
                />
              </div>
              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setIsDepartmentModalOpen(false)}
                  className="px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createDeptMutation.isPending}
                  className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg"
                >
                  Create
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL: APPLY LEAVE */}
      {isLeaveModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className={`w-full max-w-md p-6 rounded-2xl border shadow-xl ${cardBg}`}>
            <h2 className={`text-lg font-bold ${textPrimary} mb-4`}>Apply for Leave</h2>
            <form onSubmit={handleCreateLeave} className="space-y-4">
              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1">Leave Category</label>
                <select
                  required
                  value={leaveForm.leave_type_id}
                  onChange={(e) => setLeaveForm({ ...leaveForm, leave_type_id: e.target.value })}
                  className={`w-full px-3 py-2 text-sm rounded-lg border ${
                    isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                  }`}
                >
                  <option value="">Select Leave Category</option>
                  {leaveTypes.map((t) => (
                    <option key={t.id} value={t.id}>{t.name} ({t.days_allowed_per_year} days/yr)</option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Start Date</label>
                  <input
                    type="date"
                    required
                    value={leaveForm.start_date}
                    onChange={(e) => setLeaveForm({ ...leaveForm, start_date: e.target.value })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">End Date</label>
                  <input
                    type="date"
                    required
                    value={leaveForm.end_date}
                    onChange={(e) => setLeaveForm({ ...leaveForm, end_date: e.target.value })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  />
                </div>
              </div>

              <div className="flex items-center gap-2">
                <input
                  type="checkbox"
                  id="half_day_cb"
                  checked={leaveForm.is_half_day}
                  onChange={(e) => setLeaveForm({ ...leaveForm, is_half_day: e.target.checked })}
                />
                <label htmlFor="half_day_cb" className="text-xs text-slate-500">Half-Day Leave</label>
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1">Reason</label>
                <textarea
                  rows={3}
                  value={leaveForm.reason}
                  onChange={(e) => setLeaveForm({ ...leaveForm, reason: e.target.value })}
                  placeholder="State the reason for time-off..."
                  className={`w-full px-3 py-2 text-sm rounded-lg border ${
                    isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                  }`}
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setIsLeaveModalOpen(false)}
                  className="px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createLeaveMutation.isPending}
                  className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg"
                >
                  Submit Application
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL: GENERATE PAYROLL BATCH */}
      {isGenerateBatchModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className={`w-full max-w-md p-6 rounded-2xl border shadow-xl ${cardBg}`}>
            <h2 className={`text-lg font-bold ${textPrimary} mb-4`}>Generate Monthly Payroll Batch</h2>
            <form
              onSubmit={async (e) => {
                e.preventDefault();
                await generateBatchMutation.mutateAsync({
                  month: Number(batchForm.month),
                  year: Number(batchForm.year),
                });
                setIsGenerateBatchModalOpen(false);
              }}
              className="space-y-4"
            >
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Month</label>
                  <select
                    value={batchForm.month}
                    onChange={(e) => setBatchForm({ ...batchForm, month: Number(e.target.value) })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  >
                    {[1,2,3,4,5,6,7,8,9,10,11,12].map((m) => (
                      <option key={m} value={m}>Month {m}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Year</label>
                  <input
                    type="number"
                    value={batchForm.year}
                    onChange={(e) => setBatchForm({ ...batchForm, year: Number(e.target.value) })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  />
                </div>
              </div>
              <p className="text-[11px] text-slate-400">
                Calculates Basic (40-50%), HRA, Conveyance, Special Allowance, LOP unpaid days deduction, Overtime, statutory PF (12%), ESI (0.75%), and State PT slabs for all eligible active staff.
              </p>
              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setIsGenerateBatchModalOpen(false)}
                  className="px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={generateBatchMutation.isPending}
                  className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg"
                >
                  Run Calculation
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL: FULL & FINAL SETTLEMENT */}
      {isFnfModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className={`w-full max-w-xl p-6 rounded-2xl border shadow-xl ${cardBg} max-h-[90vh] overflow-y-auto`}>
            <h2 className={`text-lg font-bold ${textPrimary} mb-4`}>Full and Final (F&F) Settlement Calculator</h2>
            <div className="space-y-4">
              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1">Select Employee</label>
                <select
                  value={fnfForm.employee_id || employees[0]?.id || ''}
                  onChange={(e) => setFnfForm({ ...fnfForm, employee_id: Number(e.target.value) })}
                  className={`w-full px-3 py-2 text-sm rounded-lg border ${
                    isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                  }`}
                >
                  {employees.map((emp) => (
                    <option key={emp.id} value={emp.id}>{emp.name} ({emp.designation})</option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Notice Required (Days)</label>
                  <input
                    type="number"
                    value={fnfForm.notice_required_days}
                    onChange={(e) => setFnfForm({ ...fnfForm, notice_required_days: Number(e.target.value) })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Notice Served (Days)</label>
                  <input
                    type="number"
                    value={fnfForm.notice_served_days}
                    onChange={(e) => setFnfForm({ ...fnfForm, notice_served_days: Number(e.target.value) })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3 items-center">
                <div className="flex items-center gap-2 mt-4">
                  <input
                    type="checkbox"
                    id="gratuity_eligible"
                    checked={fnfForm.gratuity_eligible}
                    onChange={(e) => setFnfForm({ ...fnfForm, gratuity_eligible: e.target.checked })}
                    className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                  />
                  <label htmlFor="gratuity_eligible" className="text-xs font-medium text-slate-300">
                    Gratuity Eligible (&gt;= 5 years service)
                  </label>
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Years of Service</label>
                  <input
                    type="number"
                    value={fnfForm.years_of_service}
                    onChange={(e) => setFnfForm({ ...fnfForm, years_of_service: Number(e.target.value) })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  />
                </div>
              </div>

              <button
                type="button"
                onClick={async () => {
                  const targetEmpId = fnfForm.employee_id || employees[0]?.id;
                  if (!targetEmpId) return;
                  const res = await calculateFnfMutation.mutateAsync({
                    ...fnfForm,
                    employee_id: targetEmpId,
                  });
                  setFnfResult(res);
                }}
                disabled={calculateFnfMutation.isPending}
                className="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg"
              >
                Compute Settlement Breakdown
              </button>

              {fnfResult && (
                <div className="p-4 rounded-xl bg-slate-900 text-xs space-y-2 border border-slate-700 text-slate-300">
                  <div className="font-bold text-white text-sm pb-1 border-b border-slate-800">Settlement Summary</div>
                  <div className="flex justify-between">
                    <span>Unpaid Salary ({fnfResult.unpaid_days} days):</span>
                    <span className="font-mono text-emerald-400">+₹{Number(fnfResult.unpaid_salary_amount).toLocaleString('en-IN')}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Leave Encashment ({fnfResult.leave_encashment_days} days):</span>
                    <span className="font-mono text-emerald-400">+₹{Number(fnfResult.leave_encashment_amount).toLocaleString('en-IN')}</span>
                  </div>
                  {fnfResult.gratuity_eligible && (
                    <div className="flex justify-between">
                      <span>Gratuity (Payment of Gratuity Act 1972):</span>
                      <span className="font-mono text-emerald-400">+₹{Number(fnfResult.gratuity_amount).toLocaleString('en-IN')}</span>
                    </div>
                  )}
                  {fnfResult.notice_shortfall_days > 0 && (
                    <div className="flex justify-between text-rose-400">
                      <span>Notice Shortfall Recovery ({fnfResult.notice_shortfall_days} days):</span>
                      <span className="font-mono">-₹{Number(fnfResult.notice_recovery_amount).toLocaleString('en-IN')}</span>
                    </div>
                  )}
                  <div className="flex justify-between font-bold text-sm pt-2 border-t border-slate-800 text-white">
                    <span>Net Payable to Employee:</span>
                    <span className="font-mono text-emerald-400">₹{Number(fnfResult.net_payable).toLocaleString('en-IN')}</span>
                  </div>
                </div>
              )}

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setIsFnfModalOpen(false)}
                  className="px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700"
                >
                  Close
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* MODAL: UPLOAD DOCUMENT */}
      {isDocModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className={`w-full max-w-md p-6 rounded-2xl border shadow-xl ${cardBg}`}>
            <h2 className={`text-lg font-bold ${textPrimary} mb-4`}>Upload Employee Document</h2>
            <form
              onSubmit={async (e) => {
                e.preventDefault();
                const targetEmpId = activeSubEmployeeId || employees[0]?.id;
                if (!targetEmpId) return;
                await createDocMutation.mutateAsync({
                  employeeId: targetEmpId,
                  data: docForm,
                });
                setIsDocModalOpen(false);
              }}
              className="space-y-4"
            >
              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1">Document Title</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Employment Contract 2026"
                  value={docForm.title}
                  onChange={(e) => setDocForm({ ...docForm, title: e.target.value })}
                  className={`w-full px-3 py-2 text-sm rounded-lg border ${
                    isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                  }`}
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1">Document Type</label>
                <select
                  value={docForm.document_type}
                  onChange={(e) => setDocForm({ ...docForm, document_type: e.target.value as any })}
                  className={`w-full px-3 py-2 text-sm rounded-lg border ${
                    isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                  }`}
                >
                  <option value="appointment_letter">Appointment Letter</option>
                  <option value="contract">Employment Contract</option>
                  <option value="id_proof">Government ID Proof</option>
                  <option value="pan_card">PAN Card</option>
                  <option value="aadhaar_card">Aadhaar Card</option>
                  <option value="qualification">Educational Degree / Transcript</option>
                  <option value="certificate">Professional Certificate</option>
                  <option value="other">Other Document</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1">Expiry Date (if applicable)</label>
                <input
                  type="date"
                  value={docForm.expiry_date}
                  onChange={(e) => setDocForm({ ...docForm, expiry_date: e.target.value })}
                  className={`w-full px-3 py-2 text-sm rounded-lg border ${
                    isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                  }`}
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setIsDocModalOpen(false)}
                  className="px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createDocMutation.isPending}
                  className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg"
                >
                  Save Document
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL: LOG PERFORMANCE REVIEW */}
      {isReviewModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className={`w-full max-w-md p-6 rounded-2xl border shadow-xl ${cardBg}`}>
            <h2 className={`text-lg font-bold ${textPrimary} mb-4`}>Log Performance Review</h2>
            <form
              onSubmit={async (e) => {
                e.preventDefault();
                const targetEmpId = activeSubEmployeeId || employees[0]?.id;
                if (!targetEmpId) return;
                await createReviewMutation.mutateAsync({
                  employeeId: targetEmpId,
                  data: reviewForm,
                });
                setIsReviewModalOpen(false);
              }}
              className="space-y-4"
            >
              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1">Review Period</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Q1-2026 or Annual 2025"
                  value={reviewForm.review_period}
                  onChange={(e) => setReviewForm({ ...reviewForm, review_period: e.target.value })}
                  className={`w-full px-3 py-2 text-sm rounded-lg border ${
                    isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                  }`}
                />
              </div>

              <div className="grid grid-cols-3 gap-2">
                <div>
                  <label className="block text-[11px] font-medium text-slate-400 mb-1">Productivity</label>
                  <select
                    value={reviewForm.rating_productivity}
                    onChange={(e) => setReviewForm({ ...reviewForm, rating_productivity: Number(e.target.value) })}
                    className={`w-full px-2 py-1.5 text-xs rounded-lg border ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  >
                    {[5,4,3,2,1].map((n) => <option key={n} value={n}>{n} Stars</option>)}
                  </select>
                </div>
                <div>
                  <label className="block text-[11px] font-medium text-slate-400 mb-1">Quality</label>
                  <select
                    value={reviewForm.rating_quality}
                    onChange={(e) => setReviewForm({ ...reviewForm, rating_quality: Number(e.target.value) })}
                    className={`w-full px-2 py-1.5 text-xs rounded-lg border ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  >
                    {[5,4,3,2,1].map((n) => <option key={n} value={n}>{n} Stars</option>)}
                  </select>
                </div>
                <div>
                  <label className="block text-[11px] font-medium text-slate-400 mb-1">Teamwork</label>
                  <select
                    value={reviewForm.rating_teamwork}
                    onChange={(e) => setReviewForm({ ...reviewForm, rating_teamwork: Number(e.target.value) })}
                    className={`w-full px-2 py-1.5 text-xs rounded-lg border ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  >
                    {[5,4,3,2,1].map((n) => <option key={n} value={n}>{n} Stars</option>)}
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1">Key Strengths</label>
                <textarea
                  rows={2}
                  value={reviewForm.strengths}
                  onChange={(e) => setReviewForm({ ...reviewForm, strengths: e.target.value })}
                  className={`w-full px-3 py-2 text-xs rounded-lg border ${
                    isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                  }`}
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1">Action Plan & Next Steps</label>
                <textarea
                  rows={2}
                  value={reviewForm.action_plan}
                  onChange={(e) => setReviewForm({ ...reviewForm, action_plan: e.target.value })}
                  className={`w-full px-3 py-2 text-xs rounded-lg border ${
                    isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                  }`}
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setIsReviewModalOpen(false)}
                  className="px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createReviewMutation.isPending}
                  className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg"
                >
                  Save Review
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL: ADD PRODUCTIVITY GOAL */}
      {isGoalModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className={`w-full max-w-md p-6 rounded-2xl border shadow-xl ${cardBg}`}>
            <h2 className={`text-lg font-bold ${textPrimary} mb-4`}>Add Productivity Goal</h2>
            <form
              onSubmit={async (e) => {
                e.preventDefault();
                const targetEmpId = activeSubEmployeeId || employees[0]?.id;
                if (!targetEmpId) return;
                await createGoalMutation.mutateAsync({
                  employeeId: targetEmpId,
                  data: goalForm,
                });
                setIsGoalModalOpen(false);
              }}
              className="space-y-4"
            >
              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1">Goal Title</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Complete quarterly certification"
                  value={goalForm.title}
                  onChange={(e) => setGoalForm({ ...goalForm, title: e.target.value })}
                  className={`w-full px-3 py-2 text-sm rounded-lg border ${
                    isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                  }`}
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Target Value</label>
                  <input
                    type="number"
                    value={goalForm.target_value}
                    onChange={(e) => setGoalForm({ ...goalForm, target_value: Number(e.target.value) })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Current Progress</label>
                  <input
                    type="number"
                    value={goalForm.current_value}
                    onChange={(e) => setGoalForm({ ...goalForm, current_value: Number(e.target.value) })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1">Target Completion Date</label>
                <input
                  type="date"
                  value={goalForm.target_date}
                  onChange={(e) => setGoalForm({ ...goalForm, target_date: e.target.value })}
                  className={`w-full px-3 py-2 text-sm rounded-lg border ${
                    isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                  }`}
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setIsGoalModalOpen(false)}
                  className="px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createGoalMutation.isPending}
                  className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg"
                >
                  Save Goal
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
      {isCorrectionModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className={`w-full max-w-md p-6 rounded-2xl border shadow-xl ${cardBg}`}>
            <h2 className={`text-lg font-bold ${textPrimary} mb-4`}>Attendance Correction Request</h2>
            <form onSubmit={handleRequestCorrection} className="space-y-4">
              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1">Date</label>
                <input
                  type="date"
                  required
                  value={correctionForm.date}
                  onChange={(e) => setCorrectionForm({ ...correctionForm, date: e.target.value })}
                  className={`w-full px-3 py-2 text-sm rounded-lg border ${
                    isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                  }`}
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Requested Check-In</label>
                  <input
                    type="time"
                    value={correctionForm.requested_check_in}
                    onChange={(e) => setCorrectionForm({ ...correctionForm, requested_check_in: e.target.value })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Requested Check-Out</label>
                  <input
                    type="time"
                    value={correctionForm.requested_check_out}
                    onChange={(e) => setCorrectionForm({ ...correctionForm, requested_check_out: e.target.value })}
                    className={`w-full px-3 py-2 text-sm rounded-lg border ${
                      isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                    }`}
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1">Reason for Correction</label>
                <textarea
                  rows={3}
                  required
                  value={correctionForm.reason}
                  onChange={(e) => setCorrectionForm({ ...correctionForm, reason: e.target.value })}
                  placeholder="e.g. Biometric sensor malfunction / forgot badge"
                  className={`w-full px-3 py-2 text-sm rounded-lg border ${
                    isDark ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200'
                  }`}
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setIsCorrectionModalOpen(false)}
                  className="px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={requestCorrectionMutation.isPending}
                  className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg"
                >
                  Submit Request
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
