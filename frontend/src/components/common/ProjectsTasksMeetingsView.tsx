import React, { useState } from 'react';
import {
  FolderKanban,
  CheckSquare,
  Calendar,
  Clock,
  Plus,
  Search,
  Users,
  Milestone,
  AlertCircle,
  Trash2,
  Archive,
  RotateCcw,
  MessageSquare,
  Video,
  MapPin,
  ExternalLink,
  Filter,
  CheckCircle2,
  BarChart3,
  ListTodo,
  TrendingUp,
  Tag,
  DollarSign,
  ChevronRight,
  UserCheck,
  UserX,
  Clock3,
  Play,
  Square,
  Check,
  X,
  Layers,
  ShieldCheck,
} from 'lucide-react';
import {
  useProjects,
  useCreateProject,
  useUpdateProject,
  useArchiveProject,
  useRestoreProject,
  useDeleteProject,
  useProjectMilestones,
  useCreateProjectMilestone,
  useUpdateProjectMilestone,
  useDeleteProjectMilestone,
  useProjectComments,
  useCreateProjectComment,
  useProjectMetrics,
  useTasks,
  useCreateTask,
  useUpdateTask,
  useCompleteTask,
  useDeleteTask,
  useLogTaskTime,
  useTaskTimeLogs,
  useTaskDependencies,
  useAddTaskDependency,
  useTaskMetrics,
  useMeetings,
  useScheduleMeeting,
  useRescheduleMeeting,
  useUpdateMeetingRsvp,
  useCreateMeetingFollowUpTask,
  useMeetingCalendarEvents,
  useProjectFinancials,
  useProjectGantt,
  useProjectWorkload,
  useTimesheets,
  useStartTaskTimer,
  useStopTaskTimer,
  useApproveTimeLog,
  useRejectTimeLog,
  useTransitionProjectStatus,
} from '../../hooks/useProjectsTasksMeetings';
import { ProjectItem, TaskItem, MeetingItem } from '../../api/types';

export const ProjectsTasksMeetingsView: React.FC = () => {
  const [activeTab, setActiveTab] = useState<'projects' | 'tasks' | 'gantt' | 'profitability' | 'timesheets' | 'meetings' | 'reports'>('projects');

  // Enterprise Profitability, Gantt & Timesheets states
  const [selectedGanttProjectId, setSelectedGanttProjectId] = useState<number | null>(null);
  const [selectedProfitabilityProjectId, setSelectedProfitabilityProjectId] = useState<number | null>(null);
  const [timerSelectedTaskId, setTimerSelectedTaskId] = useState<number | null>(null);
  const [timesheetStatusFilter, setTimesheetStatusFilter] = useState<string>('all');

  // Project states
  const [showArchivedProjects, setShowArchivedProjects] = useState(false);
  const [projectSearch, setProjectSearch] = useState('');
  const [projectStatusFilter, setProjectStatusFilter] = useState('');
  const [isCreateProjectOpen, setIsCreateProjectOpen] = useState(false);
  const [selectedProjectId, setSelectedProjectId] = useState<number | null>(null);
  const [milestonesModalProject, setMilestonesModalProject] = useState<ProjectItem | null>(null);
  const [commentsModalProject, setCommentsModalProject] = useState<ProjectItem | null>(null);

  // Task states
  const [taskViewMode, setTaskViewMode] = useState<'kanban' | 'list'>('kanban');
  const [taskFilterView, setTaskFilterView] = useState<'all' | 'my_tasks' | 'today' | 'upcoming' | 'overdue'>('all');
  const [taskPriorityFilter, setTaskPriorityFilter] = useState('');
  const [isCreateTaskOpen, setIsCreateTaskOpen] = useState(false);
  const [timeLogModalTask, setTimeLogModalTask] = useState<TaskItem | null>(null);
  const [dependencyModalTask, setDependencyModalTask] = useState<TaskItem | null>(null);

  // Meeting states
  const [meetingViewMode, setMeetingViewMode] = useState<'list' | 'calendar'>('list');
  const [meetingStatusFilter, setMeetingStatusFilter] = useState('');
  const [isScheduleMeetingOpen, setIsScheduleMeetingOpen] = useState(false);
  const [rescheduleMeetingItem, setRescheduleMeetingItem] = useState<MeetingItem | null>(null);
  const [followUpTaskMeeting, setFollowUpTaskMeeting] = useState<MeetingItem | null>(null);

  // Queries
  const { data: projects = [], isLoading: isLoadingProjects } = useProjects({
    archived: showArchivedProjects,
    q: projectSearch || undefined,
    status: projectStatusFilter || undefined,
  });
  const { data: projectMetrics } = useProjectMetrics();

  const { data: tasks = [], isLoading: isLoadingTasks } = useTasks({
    view: taskFilterView === 'all' ? undefined : taskFilterView,
    priority: taskPriorityFilter || undefined,
  });
  const { data: taskMetrics } = useTaskMetrics();

  const { data: meetings = [], isLoading: isLoadingMeetings } = useMeetings({
    status: meetingStatusFilter || undefined,
  });
  const { data: calendarEvents = [] } = useMeetingCalendarEvents();

  // Enterprise Profitability, Gantt & Timesheets hooks
  const activeGanttId = selectedGanttProjectId || projects[0]?.id;
  const activeFinId = selectedProfitabilityProjectId || projects[0]?.id;
  const { data: ganttData, isLoading: isLoadingGantt } = useProjectGantt(activeGanttId);
  const { data: financialsData } = useProjectFinancials(activeFinId);
  const { data: workloadData = [] } = useProjectWorkload();
  const { data: timesheetsData = [], isLoading: isLoadingTimesheets } = useTimesheets(
    timesheetStatusFilter !== 'all' ? { status: timesheetStatusFilter } : undefined
  );

  const startTimerMutation = useStartTaskTimer();
  const stopTimerMutation = useStopTaskTimer();
  const approveTimeLogMutation = useApproveTimeLog();
  const rejectTimeLogMutation = useRejectTimeLog();
  const transitionStatusMutation = useTransitionProjectStatus();

  // Mutations
  const createProjectMutation = useCreateProject();
  const updateProjectMutation = useUpdateProject();
  const archiveProjectMutation = useArchiveProject();
  const restoreProjectMutation = useRestoreProject();
  const deleteProjectMutation = useDeleteProject();

  const createTaskMutation = useCreateTask();
  const updateTaskMutation = useUpdateTask();
  const completeTaskMutation = useCompleteTask();
  const deleteTaskMutation = useDeleteTask();
  const logTimeMutation = useLogTaskTime();

  const scheduleMeetingMutation = useScheduleMeeting();
  const rescheduleMeetingMutation = useRescheduleMeeting();
  const updateRsvpMutation = useUpdateMeetingRsvp();
  const createFollowUpMutation = useCreateMeetingFollowUpTask();

  // Project form state
  const [newProject, setNewProject] = useState({
    name: '',
    description: '',
    status: 'not_started' as const,
    priority: 'medium' as const,
    budget: 0,
    estimated_hours: 0,
    start_date: '',
    end_date: '',
  });

  // Task form state
  const [newTask, setNewTask] = useState({
    title: '',
    description: '',
    project_id: '' as string | number,
    priority: 'medium' as const,
    status: 'todo' as const,
    due_date: '',
    estimated_hours: 0,
  });

  // Meeting form state
  const [newMeeting, setNewMeeting] = useState({
    title: '',
    description: '',
    agenda: '',
    meeting_date: '',
    start_time: '10:00',
    end_time: '11:00',
    location_type: 'online' as const,
    meet_link: '',
    location_address: '',
    attendees: [] as Array<{ attendee_name: string; attendee_email: string }>,
  });
  const [attendeeInput, setAttendeeInput] = useState({ name: '', email: '' });

  // Time log form state
  const [timeLogForm, setTimeLogForm] = useState({
    duration_minutes: 60,
    description: '',
    is_billable: true,
  });

  // Reschedule form state
  const [rescheduleForm, setRescheduleForm] = useState({
    meeting_date: '',
    start_time: '10:00',
    end_time: '11:00',
    agenda: '',
  });

  // Milestone creation form state
  const [newMilestoneTitle, setNewMilestoneTitle] = useState('');
  const [newMilestoneDueDate, setNewMilestoneDueDate] = useState('');
  const { data: projectMilestones = [] } = useProjectMilestones(milestonesModalProject?.id);
  const createMilestoneMutation = useCreateProjectMilestone();
  const updateMilestoneMutation = useUpdateProjectMilestone();
  const deleteMilestoneMutation = useDeleteProjectMilestone();

  // Project comment form state
  const [commentText, setCommentText] = useState('');
  const { data: projectComments = [] } = useProjectComments(commentsModalProject?.id);
  const createCommentMutation = useCreateProjectComment();

  // Task dependencies state
  const { data: taskDeps } = useTaskDependencies(dependencyModalTask?.id);
  const [depTaskId, setDepTaskId] = useState('');
  const addDependencyMutation = useAddTaskDependency();

  // Handlers
  const handleCreateProject = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newProject.name) return;
    await createProjectMutation.mutateAsync({
      name: newProject.name,
      description: newProject.description,
      status: newProject.status,
      priority: newProject.priority,
      budget: Number(newProject.budget),
      estimated_hours: Number(newProject.estimated_hours),
      start_date: newProject.start_date || undefined,
      end_date: newProject.end_date || undefined,
    });
    setNewProject({
      name: '',
      description: '',
      status: 'not_started',
      priority: 'medium',
      budget: 0,
      estimated_hours: 0,
      start_date: '',
      end_date: '',
    });
    setIsCreateProjectOpen(false);
  };

  const handleCreateTask = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newTask.title) return;
    await createTaskMutation.mutateAsync({
      title: newTask.title,
      description: newTask.description,
      project_id: newTask.project_id ? Number(newTask.project_id) : undefined,
      priority: newTask.priority,
      status: newTask.status,
      due_date: newTask.due_date || undefined,
      estimated_hours: Number(newTask.estimated_hours),
    });
    setNewTask({
      title: '',
      description: '',
      project_id: '',
      priority: 'medium',
      status: 'todo',
      due_date: '',
      estimated_hours: 0,
    });
    setIsCreateTaskOpen(false);
  };

  const handleScheduleMeeting = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newMeeting.title || !newMeeting.meeting_date) return;
    await scheduleMeetingMutation.mutateAsync({
      title: newMeeting.title,
      description: newMeeting.description,
      agenda: newMeeting.agenda,
      meeting_date: newMeeting.meeting_date,
      start_time: newMeeting.start_time,
      end_time: newMeeting.end_time,
      location_type: newMeeting.location_type,
      meet_link: newMeeting.meet_link,
      location_address: newMeeting.location_address,
      attendees: newMeeting.attendees,
    });
    setNewMeeting({
      title: '',
      description: '',
      agenda: '',
      meeting_date: '',
      start_time: '10:00',
      end_time: '11:00',
      location_type: 'online',
      meet_link: '',
      location_address: '',
      attendees: [],
    });
    setIsScheduleMeetingOpen(false);
  };

  const handleLogTime = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!timeLogModalTask) return;
    await logTimeMutation.mutateAsync({
      taskId: timeLogModalTask.id,
      data: {
        duration_minutes: Number(timeLogForm.duration_minutes),
        description: timeLogForm.description,
        is_billable: timeLogForm.is_billable,
      },
    });
    setTimeLogForm({ duration_minutes: 60, description: '', is_billable: true });
    setTimeLogModalTask(null);
  };

  const handleReschedule = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!rescheduleMeetingItem || !rescheduleForm.meeting_date) return;
    await rescheduleMeetingMutation.mutateAsync({
      id: rescheduleMeetingItem.id,
      data: {
        meeting_date: rescheduleForm.meeting_date,
        start_time: rescheduleForm.start_time,
        end_time: rescheduleForm.end_time,
        agenda: rescheduleForm.agenda,
      },
    });
    setRescheduleMeetingItem(null);
  };

  const handleCompleteTask = async (task: TaskItem) => {
    try {
      await completeTaskMutation.mutateAsync(task.id);
    } catch (err: any) {
      alert(err?.message || 'Cannot complete task due to unsatisfied prerequisites.');
    }
  };

  return (
    <div className="flex flex-col h-full bg-slate-50 dark:bg-slate-950 p-6 space-y-6 overflow-y-auto">
      {/* Top Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-200 dark:border-slate-800 pb-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <FolderKanban className="w-7 h-7 text-indigo-600 dark:text-indigo-400" />
            Projects, Tasks & Operations
          </h1>
          <p className="text-sm text-slate-500 dark:text-slate-400">
            Enterprise project delivery, task boards with dependency guardrails, and executive meeting orchestration.
          </p>
        </div>

        {/* Global Action / View Switcher */}
        <div className="flex items-center gap-2 bg-slate-200 dark:bg-slate-800 p-1 rounded-lg">
          <button
            onClick={() => setActiveTab('projects')}
            className={`px-3 py-1.5 rounded-md text-xs font-medium transition ${
              activeTab === 'projects'
                ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            }`}
          >
            Projects
          </button>
          <button
            onClick={() => setActiveTab('tasks')}
            className={`px-3 py-1.5 rounded-md text-xs font-medium transition ${
              activeTab === 'tasks'
                ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            }`}
          >
            Tasks & Kanban
          </button>
          <button
            onClick={() => setActiveTab('gantt')}
            className={`px-3 py-1.5 rounded-md text-xs font-medium transition ${
              activeTab === 'gantt'
                ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            }`}
          >
            Gantt Timeline
          </button>
          <button
            onClick={() => setActiveTab('profitability')}
            className={`px-3 py-1.5 rounded-md text-xs font-medium transition ${
              activeTab === 'profitability'
                ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            }`}
          >
            Profitability
          </button>
          <button
            onClick={() => setActiveTab('timesheets')}
            className={`px-3 py-1.5 rounded-md text-xs font-medium transition ${
              activeTab === 'timesheets'
                ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            }`}
          >
            Timesheets & Timer
          </button>
          <button
            onClick={() => setActiveTab('meetings')}
            className={`px-3 py-1.5 rounded-md text-xs font-medium transition ${
              activeTab === 'meetings'
                ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            }`}
          >
            Meetings & Calendar
          </button>
          <button
            onClick={() => setActiveTab('reports')}
            className={`px-3 py-1.5 rounded-md text-xs font-medium transition ${
              activeTab === 'reports'
                ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-sm'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
            }`}
          >
            Analytics & Reports
          </button>
        </div>
      </div>

      {/* KPI Overview Strip */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-xl shadow-sm">
          <div className="flex items-center justify-between text-slate-500 text-xs">
            <span>Active Projects</span>
            <FolderKanban className="w-4 h-4 text-indigo-500" />
          </div>
          <div className="text-2xl font-bold text-slate-900 dark:text-white mt-1">
            {projectMetrics?.active_projects ?? projects.length}
          </div>
          <div className="text-xs text-slate-400 mt-1">
            Avg Progress: {projectMetrics?.average_progress ?? 0}%
          </div>
        </div>

        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-xl shadow-sm">
          <div className="flex items-center justify-between text-slate-500 text-xs">
            <span>Pending Tasks</span>
            <CheckSquare className="w-4 h-4 text-amber-500" />
          </div>
          <div className="text-2xl font-bold text-slate-900 dark:text-white mt-1">
            {taskMetrics?.pending_tasks ?? tasks.filter((t) => t.status !== 'done').length}
          </div>
          <div className="text-xs text-rose-500 mt-1">
            {taskMetrics?.overdue_tasks ?? 0} overdue
          </div>
        </div>

        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-xl shadow-sm">
          <div className="flex items-center justify-between text-slate-500 text-xs">
            <span>Hours Logged</span>
            <Clock className="w-4 h-4 text-emerald-500" />
          </div>
          <div className="text-2xl font-bold text-slate-900 dark:text-white mt-1">
            {taskMetrics?.total_hours_logged ?? 0} hrs
          </div>
          <div className="text-xs text-slate-400 mt-1">
            Billable task duration
          </div>
        </div>

        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-xl shadow-sm">
          <div className="flex items-center justify-between text-slate-500 text-xs">
            <span>Upcoming Meetings</span>
            <Calendar className="w-4 h-4 text-sky-500" />
          </div>
          <div className="text-2xl font-bold text-slate-900 dark:text-white mt-1">
            {meetings.filter((m) => m.status === 'scheduled').length}
          </div>
          <div className="text-xs text-slate-400 mt-1">
            Synced with calendar
          </div>
        </div>
      </div>

      {/* TAB 1: PROJECTS */}
      {activeTab === 'projects' && (
        <div className="space-y-4">
          {/* Controls Bar */}
          <div className="flex flex-col sm:flex-row justify-between items-center gap-3 bg-white dark:bg-slate-900 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
            <div className="flex items-center gap-2 w-full sm:w-auto">
              <div className="relative flex-1 sm:w-64">
                <Search className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                <input
                  type="text"
                  placeholder="Search projects..."
                  value={projectSearch}
                  onChange={(e) => setProjectSearch(e.target.value)}
                  className="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-indigo-500"
                />
              </div>

              <select
                value={projectStatusFilter}
                onChange={(e) => setProjectStatusFilter(e.target.value)}
                className="py-1.5 px-3 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-700 dark:text-slate-300"
              >
                <option value="">All Statuses</option>
                <option value="not_started">Not Started</option>
                <option value="in_progress">In Progress</option>
                <option value="on_hold">On Hold</option>
                <option value="completed">Completed</option>
              </select>

              <button
                onClick={() => setShowArchivedProjects(!showArchivedProjects)}
                className={`flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg border transition ${
                  showArchivedProjects
                    ? 'bg-amber-50 dark:bg-amber-950/40 border-amber-300 dark:border-amber-800 text-amber-700 dark:text-amber-400'
                    : 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400'
                }`}
              >
                <Archive className="w-3.5 h-3.5" />
                {showArchivedProjects ? 'Showing Archived' : 'Show Archived'}
              </button>
            </div>

            <button
              onClick={() => setIsCreateProjectOpen(true)}
              className="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium shadow-sm transition"
            >
              <Plus className="w-4 h-4" />
              New Project
            </button>
          </div>

          {/* Projects Grid */}
          {isLoadingProjects ? (
            <div className="py-12 text-center text-slate-400 text-sm">Loading projects...</div>
          ) : projects.length === 0 ? (
            <div className="py-12 text-center bg-white dark:bg-slate-900 rounded-xl border border-dashed border-slate-300 dark:border-slate-800">
              <FolderKanban className="w-10 h-10 text-slate-400 mx-auto mb-2 opacity-50" />
              <p className="text-sm text-slate-500">No projects found. Create one to organize operations!</p>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {projects.map((proj) => (
                <div
                  key={proj.id}
                  className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm flex flex-col justify-between hover:border-indigo-300 dark:hover:border-indigo-700 transition"
                >
                  <div>
                    <div className="flex items-center justify-between gap-2 mb-2">
                      <span
                        className={`text-[10px] font-semibold uppercase px-2 py-0.5 rounded-full ${
                          proj.priority === 'urgent'
                            ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400'
                            : proj.priority === 'high'
                            ? 'bg-orange-100 text-orange-700 dark:bg-orange-950/60 dark:text-orange-400'
                            : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                        }`}
                      >
                        {proj.priority} priority
                      </span>
                      <span
                        className={`text-[10px] font-medium px-2 py-0.5 rounded-full ${
                          proj.status === 'completed'
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400'
                            : proj.status === 'in_progress'
                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400'
                            : 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400'
                        }`}
                      >
                        {proj.status.replace('_', ' ')}
                      </span>
                    </div>

                    <h3 className="font-semibold text-slate-900 dark:text-white text-base">
                      {proj.name}
                    </h3>
                    {proj.description && (
                      <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 line-clamp-2">
                        {proj.description}
                      </p>
                    )}

                    {/* Progress Bar */}
                    <div className="mt-4">
                      <div className="flex justify-between text-xs text-slate-500 mb-1">
                        <span>Progress</span>
                        <span className="font-medium text-slate-800 dark:text-slate-200">{proj.progress}%</span>
                      </div>
                      <div className="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2 overflow-hidden">
                        <div
                          className="bg-indigo-600 h-2 rounded-full transition-all"
                          style={{ width: `${proj.progress}%` }}
                        />
                      </div>
                    </div>

                    {/* Meta info */}
                    <div className="mt-4 grid grid-cols-2 gap-2 text-xs text-slate-500 border-t border-slate-100 dark:border-slate-800 pt-3">
                      <div>
                        <span className="block text-[10px] text-slate-400">Budget</span>
                        <span className="font-medium text-slate-800 dark:text-slate-200">
                          ${Number(proj.budget).toLocaleString()}
                        </span>
                      </div>
                      <div>
                        <span className="block text-[10px] text-slate-400">Est. Hours</span>
                        <span className="font-medium text-slate-800 dark:text-slate-200">
                          {proj.estimated_hours} hrs
                        </span>
                      </div>
                    </div>
                  </div>

                  {/* Actions footer */}
                  <div className="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => setMilestonesModalProject(proj)}
                        className="flex items-center gap-1 text-slate-600 dark:text-slate-300 hover:text-indigo-600 font-medium"
                      >
                        <Milestone className="w-3.5 h-3.5 text-indigo-500" />
                        Milestones ({proj.milestones_count ?? proj.milestones?.length ?? 0})
                      </button>
                      <button
                        onClick={() => setCommentsModalProject(proj)}
                        className="flex items-center gap-1 text-slate-600 dark:text-slate-300 hover:text-indigo-600 font-medium ml-2"
                      >
                        <MessageSquare className="w-3.5 h-3.5 text-sky-500" />
                        Notes
                      </button>
                    </div>

                    <div className="flex items-center gap-1">
                      {proj.is_archived ? (
                        <button
                          onClick={() => restoreProjectMutation.mutate(proj.id)}
                          title="Restore"
                          className="p-1 hover:bg-slate-100 dark:hover:bg-slate-800 rounded text-slate-400 hover:text-emerald-500"
                        >
                          <RotateCcw className="w-3.5 h-3.5" />
                        </button>
                      ) : (
                        <button
                          onClick={() => archiveProjectMutation.mutate(proj.id)}
                          title="Archive"
                          className="p-1 hover:bg-slate-100 dark:hover:bg-slate-800 rounded text-slate-400 hover:text-amber-500"
                        >
                          <Archive className="w-3.5 h-3.5" />
                        </button>
                      )}
                      <button
                        onClick={() => {
                          if (confirm('Delete this project?')) deleteProjectMutation.mutate(proj.id);
                        }}
                        title="Delete"
                        className="p-1 hover:bg-slate-100 dark:hover:bg-slate-800 rounded text-slate-400 hover:text-rose-500"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* TAB 2: TASKS & KANBAN */}
      {activeTab === 'tasks' && (
        <div className="space-y-4">
          {/* Controls Bar */}
          <div className="flex flex-col sm:flex-row justify-between items-center gap-3 bg-white dark:bg-slate-900 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
            <div className="flex flex-wrap items-center gap-2">
              <div className="flex items-center bg-slate-100 dark:bg-slate-800 p-1 rounded-lg">
                <button
                  onClick={() => setTaskViewMode('kanban')}
                  className={`px-3 py-1 rounded text-xs font-medium ${
                    taskViewMode === 'kanban'
                      ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-sm'
                      : 'text-slate-600 dark:text-slate-400'
                  }`}
                >
                  Kanban Board
                </button>
                <button
                  onClick={() => setTaskViewMode('list')}
                  className={`px-3 py-1 rounded text-xs font-medium ${
                    taskViewMode === 'list'
                      ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-sm'
                      : 'text-slate-600 dark:text-slate-400'
                  }`}
                >
                  List View
                </button>
              </div>

              <select
                value={taskFilterView}
                onChange={(e) => setTaskFilterView(e.target.value as any)}
                className="py-1.5 px-3 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-700 dark:text-slate-300"
              >
                <option value="all">All Tasks</option>
                <option value="my_tasks">My Tasks</option>
                <option value="today">Due Today</option>
                <option value="upcoming">Upcoming (7d)</option>
                <option value="overdue">Overdue</option>
              </select>

              <select
                value={taskPriorityFilter}
                onChange={(e) => setTaskPriorityFilter(e.target.value)}
                className="py-1.5 px-3 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-700 dark:text-slate-300"
              >
                <option value="">All Priorities</option>
                <option value="urgent">Urgent</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
              </select>
            </div>

            <button
              onClick={() => setIsCreateTaskOpen(true)}
              className="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium shadow-sm transition"
            >
              <Plus className="w-4 h-4" />
              New Task
            </button>
          </div>

          {/* Kanban Board View */}
          {taskViewMode === 'kanban' ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              {[
                { key: 'todo', label: 'To Do', color: 'border-slate-300' },
                { key: 'in_progress', label: 'In Progress', color: 'border-blue-400' },
                { key: 'in_review', label: 'In Review', color: 'border-amber-400' },
                { key: 'done', label: 'Completed', color: 'border-emerald-400' },
              ].map((col) => {
                const colTasks = tasks.filter((t) =>
                  col.key === 'done' ? t.status === 'done' || t.status === 'completed' : t.status === col.key
                );

                return (
                  <div
                    key={col.key}
                    className="bg-slate-100 dark:bg-slate-900/60 rounded-xl p-3 flex flex-col border-t-4 border-slate-200 dark:border-slate-800 shadow-sm"
                  >
                    <div className="flex items-center justify-between mb-3 px-1">
                      <span className="font-semibold text-xs text-slate-700 dark:text-slate-300">
                        {col.label}
                      </span>
                      <span className="text-[10px] bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 px-2 py-0.5 rounded-full font-bold">
                        {colTasks.length}
                      </span>
                    </div>

                    <div className="space-y-2.5 overflow-y-auto max-h-[600px] pr-1">
                      {colTasks.map((t) => (
                        <div
                          key={t.id}
                          className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg p-3 shadow-sm hover:shadow transition"
                        >
                          <div className="flex items-start justify-between gap-2 mb-1.5">
                            <span
                              className={`text-[9px] uppercase font-bold px-1.5 py-0.5 rounded ${
                                t.priority === 'urgent'
                                  ? 'bg-rose-100 text-rose-700'
                                  : t.priority === 'high'
                                  ? 'bg-orange-100 text-orange-700'
                                  : 'bg-slate-100 text-slate-600'
                              }`}
                            >
                              {t.priority}
                            </span>
                            {t.due_date && (
                              <span className="text-[10px] text-slate-400 flex items-center gap-0.5">
                                <Clock className="w-3 h-3" />
                                {t.due_date}
                              </span>
                            )}
                          </div>

                          <h4 className="text-xs font-semibold text-slate-900 dark:text-white mb-1">
                            {t.title}
                          </h4>

                          {t.project && (
                            <span className="text-[10px] bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 px-1.5 py-0.5 rounded mb-2 inline-block">
                              {t.project.name}
                            </span>
                          )}

                          <div className="flex items-center justify-between mt-3 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
                            <div className="flex items-center gap-1.5">
                              <button
                                onClick={() => setTimeLogModalTask(t)}
                                title="Log Time"
                                className="text-slate-400 hover:text-indigo-600 p-0.5"
                              >
                                <Clock3 className="w-3.5 h-3.5" />
                              </button>
                              <button
                                onClick={() => setDependencyModalTask(t)}
                                title="Dependencies"
                                className="text-slate-400 hover:text-indigo-600 p-0.5"
                              >
                                <AlertCircle className="w-3.5 h-3.5" />
                              </button>
                            </div>

                            {t.status !== 'done' && t.status !== 'completed' && (
                              <button
                                onClick={() => handleCompleteTask(t)}
                                className="flex items-center gap-1 text-[11px] font-medium text-emerald-600 hover:text-emerald-700"
                              >
                                <CheckCircle2 className="w-3.5 h-3.5" />
                                Done
                              </button>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                );
              })}
            </div>
          ) : (
            /* List Table View */
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
              <table className="w-full text-left text-xs">
                <thead className="bg-slate-50 dark:bg-slate-800/60 text-slate-500 font-semibold border-b border-slate-200 dark:border-slate-800">
                  <tr>
                    <th className="py-3 px-4">Task</th>
                    <th className="py-3 px-4">Project</th>
                    <th className="py-3 px-4">Priority</th>
                    <th className="py-3 px-4">Status</th>
                    <th className="py-3 px-4">Due Date</th>
                    <th className="py-3 px-4 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {tasks.map((task) => (
                    <tr key={task.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                      <td className="py-3 px-4 font-medium text-slate-900 dark:text-white">
                        {task.title}
                      </td>
                      <td className="py-3 px-4 text-slate-500">
                        {task.project?.name || 'Standalone'}
                      </td>
                      <td className="py-3 px-4">
                        <span
                          className={`text-[10px] font-semibold px-2 py-0.5 rounded ${
                            task.priority === 'urgent'
                              ? 'bg-rose-100 text-rose-700'
                              : task.priority === 'high'
                              ? 'bg-orange-100 text-orange-700'
                              : 'bg-slate-100 text-slate-600'
                          }`}
                        >
                          {task.priority}
                        </span>
                      </td>
                      <td className="py-3 px-4">
                        <span
                          className={`text-[10px] font-medium px-2 py-0.5 rounded ${
                            task.status === 'done' || task.status === 'completed'
                              ? 'bg-emerald-100 text-emerald-700'
                              : task.status === 'in_progress'
                              ? 'bg-blue-100 text-blue-700'
                              : 'bg-slate-100 text-slate-700'
                          }`}
                        >
                          {task.status}
                        </span>
                      </td>
                      <td className="py-3 px-4 text-slate-500">{task.due_date || 'None'}</td>
                      <td className="py-3 px-4 text-right space-x-2">
                        <button
                          onClick={() => setTimeLogModalTask(task)}
                          className="text-slate-400 hover:text-indigo-600"
                          title="Log Time"
                        >
                          <Clock className="w-3.5 h-3.5 inline" />
                        </button>
                        {task.status !== 'done' && task.status !== 'completed' && (
                          <button
                            onClick={() => handleCompleteTask(task)}
                            className="text-emerald-600 hover:text-emerald-700 font-medium"
                          >
                            Complete
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* TAB 3: MEETINGS & CALENDAR */}
      {activeTab === 'meetings' && (
        <div className="space-y-4">
          {/* Controls Bar */}
          <div className="flex flex-col sm:flex-row justify-between items-center gap-3 bg-white dark:bg-slate-900 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
            <div className="flex items-center gap-2">
              <div className="flex items-center bg-slate-100 dark:bg-slate-800 p-1 rounded-lg">
                <button
                  onClick={() => setMeetingViewMode('list')}
                  className={`px-3 py-1 rounded text-xs font-medium ${
                    meetingViewMode === 'list'
                      ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-sm'
                      : 'text-slate-600 dark:text-slate-400'
                  }`}
                >
                  Meetings List
                </button>
                <button
                  onClick={() => setMeetingViewMode('calendar')}
                  className={`px-3 py-1 rounded text-xs font-medium ${
                    meetingViewMode === 'calendar'
                      ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-300 shadow-sm'
                      : 'text-slate-600 dark:text-slate-400'
                  }`}
                >
                  Calendar Timeline
                </button>
              </div>

              <select
                value={meetingStatusFilter}
                onChange={(e) => setMeetingStatusFilter(e.target.value)}
                className="py-1.5 px-3 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-700 dark:text-slate-300"
              >
                <option value="">All Statuses</option>
                <option value="scheduled">Scheduled</option>
                <option value="completed">Completed</option>
                <option value="rescheduled">Rescheduled</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>

            <button
              onClick={() => setIsScheduleMeetingOpen(true)}
              className="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium shadow-sm transition"
            >
              <Plus className="w-4 h-4" />
              Schedule Meeting
            </button>
          </div>

          {/* Meeting List or Calendar */}
          {meetingViewMode === 'list' ? (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {meetings.map((m) => (
                <div
                  key={m.id}
                  className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm flex flex-col justify-between"
                >
                  <div>
                    <div className="flex items-center justify-between text-xs mb-2">
                      <span className="font-semibold text-slate-500 flex items-center gap-1">
                        <Calendar className="w-3.5 h-3.5 text-indigo-500" />
                        {m.meeting_date} at {m.start_time}
                      </span>
                      <span
                        className={`text-[10px] font-medium px-2 py-0.5 rounded-full ${
                          m.status === 'completed'
                            ? 'bg-emerald-100 text-emerald-700'
                            : m.status === 'rescheduled'
                            ? 'bg-purple-100 text-purple-700'
                            : 'bg-blue-100 text-blue-700'
                        }`}
                      >
                        {m.status}
                      </span>
                    </div>

                    <h3 className="font-semibold text-slate-900 dark:text-white text-base">
                      {m.title}
                    </h3>

                    {m.agenda && (
                      <p className="text-xs text-slate-500 dark:text-slate-400 mt-2 whitespace-pre-line line-clamp-3">
                        {m.agenda}
                      </p>
                    )}

                    {m.location_type === 'online' && m.meet_link ? (
                      <a
                        href={m.meet_link}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline mt-3"
                      >
                        <Video className="w-3.5 h-3.5" />
                        Join Meeting Link
                        <ExternalLink className="w-3 h-3 ml-0.5" />
                      </a>
                    ) : m.location_address ? (
                      <p className="text-xs text-slate-500 flex items-center gap-1 mt-3">
                        <MapPin className="w-3.5 h-3.5 text-slate-400" />
                        {m.location_address}
                      </p>
                    ) : null}

                    {/* Attendees list */}
                    {m.attendees && m.attendees.length > 0 && (
                      <div className="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <span className="text-[10px] uppercase font-bold text-slate-400 block mb-1">
                          Attendees ({m.attendees.length})
                        </span>
                        <div className="flex flex-wrap gap-1">
                          {m.attendees.map((att) => (
                            <span
                              key={att.id}
                              className={`text-[10px] px-2 py-0.5 rounded-full border ${
                                att.status === 'accepted'
                                  ? 'bg-emerald-50 border-emerald-200 text-emerald-700'
                                  : att.status === 'declined'
                                  ? 'bg-rose-50 border-rose-200 text-rose-700'
                                  : 'bg-slate-50 border-slate-200 text-slate-600'
                              }`}
                            >
                              {att.attendee_name} ({att.status})
                            </span>
                          ))}
                        </div>
                      </div>
                    )}
                  </div>

                  {/* Meeting Actions */}
                  <div className="mt-5 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                    <button
                      onClick={() => setRescheduleMeetingItem(m)}
                      className="text-slate-600 hover:text-indigo-600 font-medium"
                    >
                      Reschedule
                    </button>
                    <button
                      onClick={() => setFollowUpTaskMeeting(m)}
                      className="text-indigo-600 hover:text-indigo-700 font-medium flex items-center gap-1"
                    >
                      <Plus className="w-3.5 h-3.5" />
                      Follow-up Task
                    </button>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            /* Calendar Timeline Cards */
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm space-y-4">
              <h3 className="font-semibold text-sm text-slate-800 dark:text-slate-200">
                Scheduled Timeline Events
              </h3>
              <div className="space-y-3">
                {calendarEvents.map((evt: any) => (
                  <div
                    key={evt.id}
                    className="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-slate-200 dark:border-slate-700 flex items-center justify-between"
                  >
                    <div>
                      <h4 className="font-semibold text-xs text-slate-900 dark:text-white">
                        {evt.title}
                      </h4>
                      <p className="text-[11px] text-slate-500 mt-0.5">
                        {evt.start} {evt.end ? `- ${evt.end}` : ''} | Organizer: {evt.organizer || 'System'}
                      </p>
                    </div>
                    {evt.meet_link && (
                      <a
                        href={evt.meet_link}
                        target="_blank"
                        rel="noreferrer"
                        className="text-xs text-indigo-600 hover:underline flex items-center gap-1"
                      >
                        <Video className="w-3.5 h-3.5" />
                        Join
                      </a>
                    )}
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}

      {/* TAB 4: REPORTS & PRODUCTIVITY */}
      {activeTab === 'reports' && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 rounded-xl shadow-sm">
            <h3 className="font-semibold text-sm text-slate-800 dark:text-slate-200 mb-4 flex items-center gap-2">
              <BarChart3 className="w-4 h-4 text-indigo-500" />
              Task Breakdown by Priority
            </h3>
            <div className="space-y-3 text-xs">
              {['urgent', 'high', 'medium', 'low'].map((p) => {
                const count = taskMetrics?.by_priority?.[p as keyof typeof taskMetrics.by_priority] ?? 0;
                return (
                  <div key={p}>
                    <div className="flex justify-between mb-1">
                      <span className="capitalize text-slate-600 dark:text-slate-400">{p}</span>
                      <span className="font-bold text-slate-800 dark:text-slate-200">{count} tasks</span>
                    </div>
                    <div className="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2">
                      <div
                        className={`h-2 rounded-full ${
                          p === 'urgent'
                            ? 'bg-rose-500'
                            : p === 'high'
                            ? 'bg-orange-500'
                            : p === 'medium'
                            ? 'bg-blue-500'
                            : 'bg-slate-400'
                        }`}
                        style={{ width: `${Math.min(100, count * 15)}%` }}
                      />
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 rounded-xl shadow-sm">
            <h3 className="font-semibold text-sm text-slate-800 dark:text-slate-200 mb-4 flex items-center gap-2">
              <TrendingUp className="w-4 h-4 text-emerald-500" />
              Project Milestones & Budget Overview
            </h3>
            <div className="space-y-4 text-xs">
              <div className="p-3 bg-slate-50 dark:bg-slate-800/40 rounded-lg">
                <span className="text-slate-500 block">Total Capital Allocated</span>
                <span className="text-xl font-bold text-slate-900 dark:text-white">
                  ${projectMetrics?.total_budget?.toLocaleString() ?? 0}
                </span>
              </div>
              <div className="p-3 bg-slate-50 dark:bg-slate-800/40 rounded-lg">
                <span className="text-slate-500 block">Overall Execution Rate</span>
                <span className="text-xl font-bold text-slate-900 dark:text-white">
                  {projectMetrics?.average_progress ?? 0}%
                </span>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* TAB: GANTT & CPM TIMELINE */}
      {activeTab === 'gantt' && (
        <div className="space-y-6">
          <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-xl shadow-sm">
            <div className="flex items-center gap-3">
              <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Select Project:</span>
              <select
                value={activeGanttId || ''}
                onChange={(e) => setSelectedGanttProjectId(Number(e.target.value))}
                className="text-xs font-medium bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg p-2 text-slate-800 dark:text-slate-200"
              >
                {projects.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.code ? `[${p.code}] ` : ''}{p.name}
                  </option>
                ))}
              </select>
            </div>
            {ganttData?.project && (
              <div className="flex items-center gap-4 text-xs text-slate-500">
                <span>Code: <strong className="text-slate-800 dark:text-slate-200">{ganttData.project.code || 'N/A'}</strong></span>
                <span>Timeline: <strong className="text-slate-800 dark:text-slate-200">{ganttData.project.start_date || 'Start'} → {ganttData.project.end_date || 'Deadline'}</strong></span>
                <span>Overall: <strong className="text-indigo-600 dark:text-indigo-400">{ganttData.project.progress}%</strong></span>
              </div>
            )}
          </div>

          {/* Milestones Flag Strip */}
          {ganttData?.milestones && ganttData.milestones.length > 0 && (
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-xl shadow-sm">
              <h4 className="text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                <Milestone className="w-4 h-4 text-amber-500" />
                Delivery Milestones & Client Sign-off Stage
              </h4>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                {ganttData.milestones.map((m) => (
                  <div key={m.id} className="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-lg border border-slate-200 dark:border-slate-700 text-xs flex justify-between items-center">
                    <div>
                      <p className="font-semibold text-slate-800 dark:text-slate-200">{m.title}</p>
                      <p className="text-slate-500 text-[11px]">Due: {m.due_date || 'TBD'} • ₹{Number(m.amount || 0).toLocaleString()}</p>
                    </div>
                    <span className={`px-2 py-0.5 rounded text-[10px] font-medium ${
                      m.approval_status === 'client_approved' ? 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400' :
                      m.approval_status === 'internal_approved' ? 'bg-blue-100 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400' :
                      'bg-amber-100 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400'
                    }`}>
                      {m.approval_status ? m.approval_status.replace('_', ' ') : m.status}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Gantt Timeline Table */}
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
            <div className="p-4 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
              <h3 className="font-semibold text-sm text-slate-800 dark:text-slate-200 flex items-center gap-2">
                <Layers className="w-4 h-4 text-indigo-500" />
                CPM Gantt Execution Schedule ({ganttData?.tasks?.length || 0} tasks)
              </h3>
            </div>
            {isLoadingGantt ? (
              <div className="p-8 text-center text-slate-500 text-xs">Loading Gantt schedule...</div>
            ) : !ganttData?.tasks || ganttData.tasks.length === 0 ? (
              <div className="p-8 text-center text-slate-500 text-xs">No tasks scheduled for this project.</div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs border-collapse">
                  <thead>
                    <tr className="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-slate-500">
                      <th className="p-3 font-semibold">Task</th>
                      <th className="p-3 font-semibold">Assignee</th>
                      <th className="p-3 font-semibold">Timeline</th>
                      <th className="p-3 font-semibold">Dependencies</th>
                      <th className="p-3 font-semibold">Status</th>
                      <th className="p-3 font-semibold">Progress Bar</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                    {ganttData.tasks.map((task) => (
                      <tr key={task.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                        <td className="p-3">
                          <div className="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                            {task.is_blocked && (
                              <span title={`Blocked: ${task.block_reason || 'Prerequisite incomplete'}`} className="px-1.5 py-0.5 rounded text-[10px] bg-rose-100 text-rose-600 font-bold">
                                BLOCKED
                              </span>
                            )}
                            {task.title}
                          </div>
                          {task.milestone_title && (
                            <span className="text-[10px] text-slate-400">Milestone: {task.milestone_title}</span>
                          )}
                        </td>
                        <td className="p-3 text-slate-600 dark:text-slate-400">
                          {task.assigned_user?.name || 'Unassigned'}
                        </td>
                        <td className="p-3 text-slate-500">
                          {task.start_date || 'Start'} → {task.due_date || 'Due'}
                          <span className="block text-[10px] text-slate-400">{task.estimated_hours}h estimated</span>
                        </td>
                        <td className="p-3">
                          {task.dependencies && task.dependencies.length > 0 ? (
                            <div className="flex flex-wrap gap-1">
                              {task.dependencies.map((d) => (
                                <span key={d.depends_on_task_id} className="px-1.5 py-0.5 bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 rounded text-[10px]">
                                  Prereq #{d.depends_on_task_id} (+{d.lag_days}d)
                                </span>
                              ))}
                            </div>
                          ) : (
                            <span className="text-slate-400 text-[11px]">—</span>
                          )}
                        </td>
                        <td className="p-3">
                          <span className={`px-2 py-0.5 rounded text-[10px] font-medium ${
                            task.status === 'done' || task.status === 'completed'
                              ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                              : task.status === 'in_progress'
                              ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-400'
                              : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                          }`}>
                            {task.status}
                          </span>
                        </td>
                        <td className="p-3 w-48">
                          <div className="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2.5">
                            <div
                              className={`h-2.5 rounded-full ${
                                task.status === 'done' || task.status === 'completed'
                                  ? 'bg-emerald-500'
                                  : task.is_blocked
                                  ? 'bg-rose-500'
                                  : 'bg-indigo-600'
                              }`}
                              style={{ width: task.status === 'done' || task.status === 'completed' ? '100%' : task.status === 'in_progress' ? '50%' : '10%' }}
                            />
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      )}

      {/* TAB: PROFITABILITY & REAL-TIME FINANCIALS */}
      {activeTab === 'profitability' && (
        <div className="space-y-6">
          <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-xl shadow-sm">
            <div className="flex items-center gap-3">
              <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Project Audit:</span>
              <select
                value={activeFinId || ''}
                onChange={(e) => setSelectedProfitabilityProjectId(Number(e.target.value))}
                className="text-xs font-medium bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg p-2 text-slate-800 dark:text-slate-200"
              >
                {projects.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.code ? `[${p.code}] ` : ''}{p.name}
                  </option>
                ))}
              </select>
            </div>
            {financialsData && (
              <span className={`px-3 py-1 rounded-full text-xs font-semibold ${
                financialsData.profit_margin_percentage >= 20
                  ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                  : financialsData.profit_margin_percentage >= 0
                  ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400'
                  : 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400'
              }`}>
                Margin: {financialsData.profit_margin_percentage}% ({financialsData.gross_profit >= 0 ? 'Profitable' : 'Loss-making'})
              </span>
            )}
          </div>

          {/* Financial Metrics Cards Strip */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-xl shadow-sm">
              <span className="text-slate-500 text-xs block">Contract Budget</span>
              <p className="text-lg font-bold text-slate-900 dark:text-white mt-1">
                ₹{Number(financialsData?.budget || 0).toLocaleString()}
              </p>
              <span className="text-[11px] text-slate-400">Baseline agreement</span>
            </div>

            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-xl shadow-sm">
              <span className="text-slate-500 text-xs block">Invoiced Revenue</span>
              <p className="text-lg font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                ₹{Number(financialsData?.invoiced_revenue || 0).toLocaleString()}
              </p>
              <span className="text-[11px] text-slate-400">Collected: ₹{Number(financialsData?.collected_revenue || 0).toLocaleString()}</span>
            </div>

            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-xl shadow-sm">
              <span className="text-slate-500 text-xs block">Total Project Costs</span>
              <p className="text-lg font-bold text-rose-600 dark:text-rose-400 mt-1">
                ₹{Number(financialsData?.total_costs || 0).toLocaleString()}
              </p>
              <span className="text-[11px] text-slate-400">
                Labor: ₹{Number(financialsData?.labor_costs || 0).toLocaleString()} • Exp: ₹{Number(financialsData?.actual_expenses || 0).toLocaleString()}
              </span>
            </div>

            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-xl shadow-sm">
              <span className="text-slate-500 text-xs block">Net Realized Margin</span>
              <p className={`text-lg font-bold mt-1 ${
                (financialsData?.gross_profit || 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600'
              }`}>
                ₹{Number(financialsData?.gross_profit || 0).toLocaleString()}
              </p>
              <span className="text-[11px] text-slate-400">Cost Variance: ₹{Number(financialsData?.cost_variance || 0).toLocaleString()}</span>
            </div>
          </div>

          {/* Team Workload & Labor Utilization Table */}
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
            <div className="p-4 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
              <h3 className="font-semibold text-sm text-slate-800 dark:text-slate-200 flex items-center gap-2">
                <Users className="w-4 h-4 text-indigo-500" />
                Team Workload & Capacity Utilization
              </h3>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs border-collapse">
                <thead>
                  <tr className="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-slate-500">
                    <th className="p-3 font-semibold">Employee</th>
                    <th className="p-3 font-semibold">Active Tasks</th>
                    <th className="p-3 font-semibold">Overdue Tasks</th>
                    <th className="p-3 font-semibold">Estimated Load</th>
                    <th className="p-3 font-semibold">Logged Hours</th>
                    <th className="p-3 font-semibold">Capacity (40h/wk)</th>
                    <th className="p-3 font-semibold">Utilization %</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {workloadData.map((w) => (
                    <tr key={w.user_id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                      <td className="p-3 font-semibold text-slate-800 dark:text-slate-200">{w.name}</td>
                      <td className="p-3">{w.active_tasks_count} active</td>
                      <td className="p-3 text-rose-500 font-semibold">{w.overdue_tasks_count}</td>
                      <td className="p-3">{w.estimated_hours}h</td>
                      <td className="p-3">{w.logged_hours}h</td>
                      <td className="p-3 text-slate-400">{w.capacity_hours}h</td>
                      <td className="p-3">
                        <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                          w.is_overloaded ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'
                        }`}>
                          {w.utilization_percentage}% {w.is_overloaded ? '(OVERLOADED)' : ''}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB: TIMESHEETS & LIVE TIMER */}
      {activeTab === 'timesheets' && (
        <div className="space-y-6">
          {/* Live Timer Banner Widget */}
          <div className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white p-5 rounded-2xl shadow-md flex flex-col sm:flex-row items-center justify-between gap-4">
            <div className="flex items-center gap-3">
              <div className="p-3 bg-white/10 rounded-xl backdrop-blur-sm">
                <Clock3 className="w-6 h-6 animate-pulse" />
              </div>
              <div>
                <h3 className="font-bold text-sm">Live Workstation Timer</h3>
                <p className="text-xs text-indigo-100">Accurately record billable employee delivery minutes in real-time.</p>
              </div>
            </div>
            <div className="flex items-center gap-2">
              <select
                value={timerSelectedTaskId || ''}
                onChange={(e) => setTimerSelectedTaskId(Number(e.target.value))}
                className="text-xs bg-white text-slate-800 rounded-lg p-2 font-medium"
              >
                <option value="">Select Task to Clock In...</option>
                {tasks.map((t) => (
                  <option key={t.id} value={t.id}>#{t.id} - {t.title}</option>
                ))}
              </select>
              <button
                disabled={!timerSelectedTaskId || startTimerMutation.isPending}
                onClick={async () => {
                  if (timerSelectedTaskId) {
                    await startTimerMutation.mutateAsync(timerSelectedTaskId);
                    alert('Live timer started for task #' + timerSelectedTaskId);
                  }
                }}
                className="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 rounded-lg text-xs font-bold flex items-center gap-1.5 transition disabled:opacity-50"
              >
                <Play className="w-3.5 h-3.5" />
                Start Timer
              </button>
            </div>
          </div>

          {/* Timesheets Log Table */}
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
            <div className="p-4 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
              <h3 className="font-semibold text-sm text-slate-800 dark:text-slate-200 flex items-center gap-2">
                <Clock className="w-4 h-4 text-indigo-500" />
                Employee Timesheet Registry & Manager Approval Queue
              </h3>
              <div className="flex items-center gap-2">
                {['all', 'pending', 'approved', 'rejected'].map((st) => (
                  <button
                    key={st}
                    onClick={() => setTimesheetStatusFilter(st)}
                    className={`px-2.5 py-1 rounded text-xs capitalize ${
                      timesheetStatusFilter === st
                        ? 'bg-indigo-600 text-white'
                        : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'
                    }`}
                  >
                    {st}
                  </button>
                ))}
              </div>
            </div>

            {isLoadingTimesheets ? (
              <div className="p-8 text-center text-slate-500 text-xs">Loading timesheets...</div>
            ) : timesheetsData.length === 0 ? (
              <div className="p-8 text-center text-slate-500 text-xs">No timesheets recorded yet.</div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs border-collapse">
                  <thead>
                    <tr className="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-slate-500">
                      <th className="p-3 font-semibold">User</th>
                      <th className="p-3 font-semibold">Task</th>
                      <th className="p-3 font-semibold">Duration</th>
                      <th className="p-3 font-semibold">Billable</th>
                      <th className="p-3 font-semibold">Amount</th>
                      <th className="p-3 font-semibold">Status</th>
                      <th className="p-3 font-semibold text-right">Manager Action</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                    {timesheetsData.map((log) => (
                      <tr key={log.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                        <td className="p-3 font-semibold text-slate-800 dark:text-slate-200">
                          {log.user?.name || `User #${log.user_id}`}
                        </td>
                        <td className="p-3 text-slate-600 dark:text-slate-400">
                          {log.task?.title || `Task #${log.task_id}`}
                        </td>
                        <td className="p-3">
                          {log.is_running ? (
                            <span className="px-2 py-0.5 rounded text-[10px] bg-indigo-100 text-indigo-700 font-bold animate-pulse">
                              RUNNING
                            </span>
                          ) : (
                            <span>{Math.floor((log.duration_minutes || 0) / 60)}h {(log.duration_minutes || 0) % 60}m</span>
                          )}
                        </td>
                        <td className="p-3">
                          <span className={`px-2 py-0.5 rounded text-[10px] ${
                            log.is_billable ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'
                          }`}>
                            {log.is_billable ? 'Billable' : 'Non-billable'}
                          </span>
                        </td>
                        <td className="p-3 font-semibold text-slate-800 dark:text-slate-200">
                          ₹{Number(log.billable_amount || 0).toLocaleString()}
                        </td>
                        <td className="p-3">
                          <span className={`px-2 py-0.5 rounded text-[10px] font-semibold ${
                            log.status === 'approved' ? 'bg-emerald-100 text-emerald-700' :
                            log.status === 'rejected' ? 'bg-rose-100 text-rose-700' :
                            'bg-amber-100 text-amber-700'
                          }`}>
                            {log.status || 'pending'}
                          </span>
                        </td>
                        <td className="p-3 text-right">
                          <div className="flex items-center justify-end gap-1.5">
                            {log.is_running ? (
                              <button
                                onClick={async () => {
                                  await stopTimerMutation.mutateAsync({ logId: log.id, description: 'Stopped by user' });
                                }}
                                className="px-2.5 py-1 bg-rose-500 hover:bg-rose-600 text-white rounded text-[10px] font-bold flex items-center gap-1"
                              >
                                <Square className="w-3 h-3" />
                                Stop
                              </button>
                            ) : log.status === 'pending' ? (
                              <>
                                <button
                                  onClick={async () => {
                                    await approveTimeLogMutation.mutateAsync(log.id);
                                  }}
                                  className="p-1 hover:bg-emerald-100 dark:hover:bg-emerald-950/40 text-emerald-600 rounded transition"
                                  title="Approve Timesheet"
                                >
                                  <Check className="w-4 h-4" />
                                </button>
                                <button
                                  onClick={async () => {
                                    const reason = prompt('Enter rejection reason:') || 'Hours unverified';
                                    await rejectTimeLogMutation.mutateAsync({ logId: log.id, reason });
                                  }}
                                  className="p-1 hover:bg-rose-100 dark:hover:bg-rose-950/40 text-rose-600 rounded transition"
                                  title="Reject Timesheet"
                                >
                                  <X className="w-4 h-4" />
                                </button>
                              </>
                            ) : (
                              <span className="text-slate-400 text-[10px]">Reviewed</span>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      )}

      {/* MODAL: CREATE PROJECT */}
      {isCreateProjectOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 dark:border-slate-800">
            <h2 className="text-lg font-bold text-slate-900 dark:text-white mb-4">
              Create New Project
            </h2>
            <form onSubmit={handleCreateProject} className="space-y-4 text-xs">
              <div>
                <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Project Name *</label>
                <input
                  type="text"
                  required
                  value={newProject.name}
                  onChange={(e) => setNewProject({ ...newProject, name: e.target.value })}
                  placeholder="e.g. ERP Cloud Integration"
                  className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Description</label>
                <textarea
                  rows={2}
                  value={newProject.description}
                  onChange={(e) => setNewProject({ ...newProject, description: e.target.value })}
                  placeholder="Goals, deliverables, and scope..."
                  className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Priority</label>
                  <select
                    value={newProject.priority}
                    onChange={(e) => setNewProject({ ...newProject, priority: e.target.value as any })}
                    className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                  >
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                  </select>
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Budget ($)</label>
                  <input
                    type="number"
                    value={newProject.budget}
                    onChange={(e) => setNewProject({ ...newProject, budget: Number(e.target.value) })}
                    className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Start Date</label>
                  <input
                    type="date"
                    value={newProject.start_date}
                    onChange={(e) => setNewProject({ ...newProject, start_date: e.target.value })}
                    className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">End Date</label>
                  <input
                    type="date"
                    value={newProject.end_date}
                    onChange={(e) => setNewProject({ ...newProject, end_date: e.target.value })}
                    className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                  />
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-4">
                <button
                  type="button"
                  onClick={() => setIsCreateProjectOpen(false)}
                  className="px-4 py-2 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-600 dark:text-slate-400"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createProjectMutation.isPending}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium"
                >
                  Create Project
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL: CREATE TASK */}
      {isCreateTaskOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 dark:border-slate-800">
            <h2 className="text-lg font-bold text-slate-900 dark:text-white mb-4">
              Create New Task
            </h2>
            <form onSubmit={handleCreateTask} className="space-y-4 text-xs">
              <div>
                <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Task Title *</label>
                <input
                  type="text"
                  required
                  value={newTask.title}
                  onChange={(e) => setNewTask({ ...newTask, title: e.target.value })}
                  placeholder="e.g. Conduct security penetration testing"
                  className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Link Project</label>
                  <select
                    value={newTask.project_id}
                    onChange={(e) => setNewTask({ ...newTask, project_id: e.target.value })}
                    className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                  >
                    <option value="">Standalone (No Project)</option>
                    {projects.map((p) => (
                      <option key={p.id} value={p.id}>
                        {p.name}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Priority</label>
                  <select
                    value={newTask.priority}
                    onChange={(e) => setNewTask({ ...newTask, priority: e.target.value as any })}
                    className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                  >
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Due Date</label>
                  <input
                    type="date"
                    value={newTask.due_date}
                    onChange={(e) => setNewTask({ ...newTask, due_date: e.target.value })}
                    className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Estimated Hours</label>
                  <input
                    type="number"
                    value={newTask.estimated_hours}
                    onChange={(e) => setNewTask({ ...newTask, estimated_hours: Number(e.target.value) })}
                    className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                  />
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-4">
                <button
                  type="button"
                  onClick={() => setIsCreateTaskOpen(false)}
                  className="px-4 py-2 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-600 dark:text-slate-400"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createTaskMutation.isPending}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium"
                >
                  Create Task
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL: SCHEDULE MEETING */}
      {isScheduleMeetingOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 dark:border-slate-800">
            <h2 className="text-lg font-bold text-slate-900 dark:text-white mb-4">
              Schedule Meeting
            </h2>
            <form onSubmit={handleScheduleMeeting} className="space-y-4 text-xs">
              <div>
                <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Meeting Title *</label>
                <input
                  type="text"
                  required
                  value={newMeeting.title}
                  onChange={(e) => setNewMeeting({ ...newMeeting, title: e.target.value })}
                  placeholder="e.g. Architecture Alignment & Roadmap"
                  className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Agenda</label>
                <textarea
                  rows={2}
                  value={newMeeting.agenda}
                  onChange={(e) => setNewMeeting({ ...newMeeting, agenda: e.target.value })}
                  placeholder="Key discussion points and deliverables..."
                  className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>

              <div className="grid grid-cols-3 gap-2">
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Date *</label>
                  <input
                    type="date"
                    required
                    value={newMeeting.meeting_date}
                    onChange={(e) => setNewMeeting({ ...newMeeting, meeting_date: e.target.value })}
                    className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Start Time</label>
                  <input
                    type="time"
                    value={newMeeting.start_time}
                    onChange={(e) => setNewMeeting({ ...newMeeting, start_time: e.target.value })}
                    className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">End Time</label>
                  <input
                    type="time"
                    value={newMeeting.end_time}
                    onChange={(e) => setNewMeeting({ ...newMeeting, end_time: e.target.value })}
                    className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                  />
                </div>
              </div>

              <div>
                <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Meeting URL / Link</label>
                <input
                  type="url"
                  value={newMeeting.meet_link}
                  onChange={(e) => setNewMeeting({ ...newMeeting, meet_link: e.target.value })}
                  placeholder="https://meet.google.com/xyz-abcd-efg"
                  className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>

              <div className="flex justify-end gap-2 pt-4">
                <button
                  type="button"
                  onClick={() => setIsScheduleMeetingOpen(false)}
                  className="px-4 py-2 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-600 dark:text-slate-400"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={scheduleMeetingMutation.isPending}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium"
                >
                  Schedule
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL: TIME LOGGING */}
      {timeLogModalTask && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-2xl max-w-sm w-full p-5 shadow-xl border border-slate-200 dark:border-slate-800">
            <h3 className="font-bold text-sm text-slate-900 dark:text-white mb-2">
              Log Time Spent
            </h3>
            <p className="text-xs text-slate-500 mb-4">{timeLogModalTask.title}</p>
            <form onSubmit={handleLogTime} className="space-y-3 text-xs">
              <div>
                <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Duration (Minutes)</label>
                <input
                  type="number"
                  required
                  min={1}
                  value={timeLogForm.duration_minutes}
                  onChange={(e) => setTimeLogForm({ ...timeLogForm, duration_minutes: Number(e.target.value) })}
                  className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>
              <div>
                <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Work Notes</label>
                <input
                  type="text"
                  value={timeLogForm.description}
                  onChange={(e) => setTimeLogForm({ ...timeLogForm, description: e.target.value })}
                  placeholder="Summary of completed activity..."
                  className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>
              <div className="flex items-center gap-2">
                <input
                  type="checkbox"
                  id="billable"
                  checked={timeLogForm.is_billable}
                  onChange={(e) => setTimeLogForm({ ...timeLogForm, is_billable: e.target.checked })}
                />
                <label htmlFor="billable" className="text-slate-700 dark:text-slate-300">
                  Billable to client
                </label>
              </div>

              <div className="flex justify-end gap-2 pt-3">
                <button
                  type="button"
                  onClick={() => setTimeLogModalTask(null)}
                  className="px-3 py-1.5 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-600"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium"
                >
                  Save Time Log
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL: RESCHEDULE MEETING */}
      {rescheduleMeetingItem && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-2xl max-w-sm w-full p-5 shadow-xl border border-slate-200 dark:border-slate-800">
            <h3 className="font-bold text-sm text-slate-900 dark:text-white mb-2">
              Reschedule Meeting
            </h3>
            <p className="text-xs text-slate-500 mb-4">{rescheduleMeetingItem.title}</p>
            <form onSubmit={handleReschedule} className="space-y-3 text-xs">
              <div>
                <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">New Date *</label>
                <input
                  type="date"
                  required
                  value={rescheduleForm.meeting_date}
                  onChange={(e) => setRescheduleForm({ ...rescheduleForm, meeting_date: e.target.value })}
                  className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>
              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Start Time</label>
                  <input
                    type="time"
                    value={rescheduleForm.start_time}
                    onChange={(e) => setRescheduleForm({ ...rescheduleForm, start_time: e.target.value })}
                    className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">End Time</label>
                  <input
                    type="time"
                    value={rescheduleForm.end_time}
                    onChange={(e) => setRescheduleForm({ ...rescheduleForm, end_time: e.target.value })}
                    className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                  />
                </div>
              </div>
              <div>
                <label className="block text-slate-600 dark:text-slate-400 mb-1 font-medium">Reason / Note</label>
                <input
                  type="text"
                  value={rescheduleForm.agenda}
                  onChange={(e) => setRescheduleForm({ ...rescheduleForm, agenda: e.target.value })}
                  placeholder="Reason for reschedule..."
                  className="w-full p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
              </div>

              <div className="flex justify-end gap-2 pt-3">
                <button
                  type="button"
                  onClick={() => setRescheduleMeetingItem(null)}
                  className="px-3 py-1.5 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-600"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium"
                >
                  Confirm Reschedule
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL: FOLLOW-UP TASK FROM MEETING */}
      {followUpTaskMeeting && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-2xl max-w-sm w-full p-5 shadow-xl border border-slate-200 dark:border-slate-800">
            <h3 className="font-bold text-sm text-slate-900 dark:text-white mb-2">
              Generate Follow-up Task
            </h3>
            <p className="text-xs text-slate-500 mb-4">
              From meeting: {followUpTaskMeeting.title}
            </p>
            <div className="space-y-3 text-xs">
              <button
                onClick={async () => {
                  await createFollowUpMutation.mutateAsync({
                    id: followUpTaskMeeting.id,
                    data: {
                      title: `Follow-up: ${followUpTaskMeeting.title}`,
                      description: followUpTaskMeeting.outcome || followUpTaskMeeting.agenda || 'Meeting outcome follow-up item.',
                      priority: 'medium',
                    },
                  });
                  setFollowUpTaskMeeting(null);
                  setActiveTab('tasks');
                }}
                className="w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium"
              >
                Create Follow-up Task
              </button>
              <button
                type="button"
                onClick={() => setFollowUpTaskMeeting(null)}
                className="w-full py-2 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-600 dark:text-slate-400"
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL: PROJECT MILESTONES */}
      {milestonesModalProject && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 dark:border-slate-800">
            <div className="flex items-center justify-between mb-4">
              <h3 className="font-bold text-base text-slate-900 dark:text-white">
                Milestones: {milestonesModalProject.name}
              </h3>
              <button
                onClick={() => setMilestonesModalProject(null)}
                className="text-slate-400 hover:text-slate-600 text-sm"
              >
                Close
              </button>
            </div>

            {/* Existing milestones */}
            <div className="space-y-2 mb-4 max-h-60 overflow-y-auto">
              {projectMilestones.length === 0 ? (
                <p className="text-xs text-slate-400">No milestones yet. Add one below!</p>
              ) : (
                projectMilestones.map((m) => (
                  <div
                    key={m.id}
                    className="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-lg border border-slate-200 dark:border-slate-700 flex items-center justify-between text-xs"
                  >
                    <div>
                      <span className="font-medium text-slate-900 dark:text-white block">{m.title}</span>
                      <span className="text-[10px] text-slate-400">
                        Due: {m.due_date || 'None'} | Progress: {m.progress}% | Status: {m.status}
                      </span>
                    </div>

                    <div className="flex items-center gap-1.5">
                      {m.status !== 'completed' && (
                        <button
                          onClick={() =>
                            updateMilestoneMutation.mutate({
                              projectId: milestonesModalProject.id,
                              milestoneId: m.id,
                              data: { status: 'completed', progress: 100 },
                            })
                          }
                          className="text-[10px] bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded font-medium"
                        >
                          Mark Done
                        </button>
                      )}
                      <button
                        onClick={() =>
                          deleteMilestoneMutation.mutate({
                            projectId: milestonesModalProject.id,
                            milestoneId: m.id,
                          })
                        }
                        className="text-slate-400 hover:text-rose-500 p-1"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                ))
              )}
            </div>

            {/* Add milestone inline form */}
            <div className="border-t border-slate-200 dark:border-slate-800 pt-3">
              <span className="text-xs font-semibold text-slate-700 dark:text-slate-300 block mb-2">
                Add Milestone
              </span>
              <div className="flex gap-2 text-xs">
                <input
                  type="text"
                  placeholder="Milestone title..."
                  value={newMilestoneTitle}
                  onChange={(e) => setNewMilestoneTitle(e.target.value)}
                  className="flex-1 p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
                <input
                  type="date"
                  value={newMilestoneDueDate}
                  onChange={(e) => setNewMilestoneDueDate(e.target.value)}
                  className="p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
                <button
                  type="button"
                  onClick={async () => {
                    if (!newMilestoneTitle) return;
                    await createMilestoneMutation.mutateAsync({
                      projectId: milestonesModalProject.id,
                      data: {
                        title: newMilestoneTitle,
                        due_date: newMilestoneDueDate || undefined,
                      },
                    });
                    setNewMilestoneTitle('');
                    setNewMilestoneDueDate('');
                  }}
                  className="px-3 py-2 bg-indigo-600 text-white rounded-lg font-medium text-xs"
                >
                  Add
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* MODAL: PROJECT COMMENTS */}
      {commentsModalProject && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 dark:border-slate-800">
            <div className="flex items-center justify-between mb-4">
              <h3 className="font-bold text-base text-slate-900 dark:text-white">
                Project Notes: {commentsModalProject.name}
              </h3>
              <button
                onClick={() => setCommentsModalProject(null)}
                className="text-slate-400 hover:text-slate-600 text-sm"
              >
                Close
              </button>
            </div>

            <div className="space-y-3 mb-4 max-h-60 overflow-y-auto">
              {projectComments.length === 0 ? (
                <p className="text-xs text-slate-400">No notes posted yet.</p>
              ) : (
                projectComments.map((c) => (
                  <div
                    key={c.id}
                    className="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-lg border border-slate-200 dark:border-slate-700 text-xs"
                  >
                    <div className="flex justify-between text-[10px] text-slate-400 mb-1">
                      <span className="font-bold text-slate-700 dark:text-slate-300">
                        {c.user?.name || 'User'}
                      </span>
                      <span>{c.created_at}</span>
                    </div>
                    <p className="text-slate-800 dark:text-slate-200 whitespace-pre-wrap">{c.comment}</p>
                  </div>
                ))
              )}
            </div>

            <div className="border-t border-slate-200 dark:border-slate-800 pt-3">
              <div className="flex gap-2 text-xs">
                <input
                  type="text"
                  placeholder="Write a project note / update..."
                  value={commentText}
                  onChange={(e) => setCommentText(e.target.value)}
                  className="flex-1 p-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                />
                <button
                  type="button"
                  onClick={async () => {
                    if (!commentText) return;
                    await createCommentMutation.mutateAsync({
                      projectId: commentsModalProject.id,
                      data: { comment: commentText },
                    });
                    setCommentText('');
                  }}
                  className="px-4 py-2 bg-indigo-600 text-white rounded-lg font-medium text-xs"
                >
                  Post
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
