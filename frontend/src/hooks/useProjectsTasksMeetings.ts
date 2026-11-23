import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useCRMStore } from '../store/crmStore';
import {
  ProjectItem,
  ProjectMilestoneItem,
  ProjectCommentItem,
  ProjectTeamMember,
  ProjectMetricsData,
  TaskItem,
  TaskDependencyItem,
  TaskTimeLogItem,
  TaskMetricsData,
  MeetingItem,
  MeetingAttendeeItem,
} from '../api/types';

// ==========================================
// 1. PROJECTS HOOKS
// ==========================================

export function useProjects(params?: {
  status?: string;
  priority?: string;
  archived?: boolean;
  customer_id?: number;
  company_id?: number;
  owner_id?: number;
  q?: string;
  page?: number;
  per_page?: number;
}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['projects', workspaceId, params],
    queryFn: async () => {
      const res = await api.getProjects(params);
      return (res.data || []) as ProjectItem[];
    },
  });
}

export function useProject(id: number | null | undefined) {
  return useQuery({
    queryKey: ['project', id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getProject(id);
      return res.data as ProjectItem;
    },
    enabled: Boolean(id),
  });
}

export function useCreateProject() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: Partial<ProjectItem> & { team_members?: any[]; milestones?: any[] }) => {
      const res = await api.createProject(data);
      return res.data as ProjectItem;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['projects'] });
      queryClient.invalidateQueries({ queryKey: ['projectMetrics'] });
    },
  });
}

export function useUpdateProject() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Partial<ProjectItem> & { team_members?: any[] } }) => {
      const res = await api.updateProject(id, data);
      return res.data as ProjectItem;
    },
    onSuccess: (updated) => {
      queryClient.invalidateQueries({ queryKey: ['projects'] });
      queryClient.invalidateQueries({ queryKey: ['project', updated.id] });
      queryClient.invalidateQueries({ queryKey: ['projectMetrics'] });
    },
  });
}

export function useArchiveProject() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.archiveProject(id);
      return res.data as ProjectItem;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['projects'] });
      queryClient.invalidateQueries({ queryKey: ['projectMetrics'] });
    },
  });
}

export function useRestoreProject() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.restoreProject(id);
      return res.data as ProjectItem;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['projects'] });
      queryClient.invalidateQueries({ queryKey: ['projectMetrics'] });
    },
  });
}

export function useDeleteProject() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.deleteProject(id);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['projects'] });
      queryClient.invalidateQueries({ queryKey: ['projectMetrics'] });
    },
  });
}

export function useProjectMilestones(projectId: number | null | undefined) {
  return useQuery({
    queryKey: ['projectMilestones', projectId],
    queryFn: async () => {
      if (!projectId) return [];
      const res = await api.getProjectMilestones(projectId);
      return (res.data || []) as ProjectMilestoneItem[];
    },
    enabled: Boolean(projectId),
  });
}

export function useCreateProjectMilestone() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ projectId, data }: { projectId: number; data: Partial<ProjectMilestoneItem> }) => {
      const res = await api.createProjectMilestone(projectId, data);
      return res.data as ProjectMilestoneItem;
    },
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['projectMilestones', vars.projectId] });
      queryClient.invalidateQueries({ queryKey: ['project', vars.projectId] });
      queryClient.invalidateQueries({ queryKey: ['projects'] });
    },
  });
}

export function useUpdateProjectMilestone() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ projectId, milestoneId, data }: { projectId: number; milestoneId: number; data: Partial<ProjectMilestoneItem> }) => {
      const res = await api.updateProjectMilestone(projectId, milestoneId, data);
      return res.data as ProjectMilestoneItem;
    },
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['projectMilestones', vars.projectId] });
      queryClient.invalidateQueries({ queryKey: ['project', vars.projectId] });
      queryClient.invalidateQueries({ queryKey: ['projects'] });
    },
  });
}

export function useDeleteProjectMilestone() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ projectId, milestoneId }: { projectId: number; milestoneId: number }) => {
      const res = await api.deleteProjectMilestone(projectId, milestoneId);
      return res.data;
    },
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['projectMilestones', vars.projectId] });
      queryClient.invalidateQueries({ queryKey: ['project', vars.projectId] });
      queryClient.invalidateQueries({ queryKey: ['projects'] });
    },
  });
}

export function useProjectComments(projectId: number | null | undefined) {
  return useQuery({
    queryKey: ['projectComments', projectId],
    queryFn: async () => {
      if (!projectId) return [];
      const res = await api.getProjectComments(projectId);
      return (res.data || []) as ProjectCommentItem[];
    },
    enabled: Boolean(projectId),
  });
}

export function useCreateProjectComment() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ projectId, data }: { projectId: number; data: { comment: string; attachments?: any[] } }) => {
      const res = await api.createProjectComment(projectId, data);
      return res.data as ProjectCommentItem;
    },
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['projectComments', vars.projectId] });
      queryClient.invalidateQueries({ queryKey: ['project', vars.projectId] });
    },
  });
}

export function useProjectMetrics() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['projectMetrics', workspaceId],
    queryFn: async () => {
      const res = await api.getProjectMetrics();
      return res.data as ProjectMetricsData;
    },
  });
}

// ==========================================
// 2. ENHANCED TASKS, DEPENDENCIES & TIME LOGS
// ==========================================

export function useTasks(params?: {
  view?: 'my_tasks' | 'team_tasks' | 'today' | 'upcoming' | 'overdue' | 'completed';
  status?: string;
  priority?: string;
  project_id?: number;
  deal_id?: number;
  lead_id?: number;
  page?: number;
  per_page?: number;
}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['tasks', workspaceId, params],
    queryFn: async () => {
      const res = await api.getTasks(params as any);
      return (res.data || []) as TaskItem[];
    },
  });
}

export function useCreateTask() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: Partial<TaskItem> & { depends_on_task_ids?: number[] }) => {
      const res = await api.createTask(data);
      return res.data as TaskItem;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
      queryClient.invalidateQueries({ queryKey: ['taskMetrics'] });
      queryClient.invalidateQueries({ queryKey: ['projects'] });
    },
  });
}

export function useUpdateTask() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Partial<TaskItem> }) => {
      const res = await api.updateTask(id, data);
      return res.data as TaskItem;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
      queryClient.invalidateQueries({ queryKey: ['taskMetrics'] });
      queryClient.invalidateQueries({ queryKey: ['projects'] });
    },
  });
}

export function useCompleteTask() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.completeTask(id);
      return res.data as TaskItem;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
      queryClient.invalidateQueries({ queryKey: ['taskMetrics'] });
      queryClient.invalidateQueries({ queryKey: ['projects'] });
    },
  });
}

export function useDeleteTask() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.deleteTask(id);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
      queryClient.invalidateQueries({ queryKey: ['taskMetrics'] });
    },
  });
}

export function useLogTaskTime() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ taskId, data }: { taskId: number; data: { duration_minutes?: number; start_time?: string; end_time?: string; description?: string; is_billable?: boolean } }) => {
      const res = await api.logTaskTime(taskId, data);
      return res.data as TaskTimeLogItem;
    },
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['taskTimeLogs', vars.taskId] });
      queryClient.invalidateQueries({ queryKey: ['taskMetrics'] });
    },
  });
}

export function useTaskTimeLogs(taskId: number | null | undefined) {
  return useQuery({
    queryKey: ['taskTimeLogs', taskId],
    queryFn: async () => {
      if (!taskId) return [];
      const res = await api.getTaskTimeLogs(taskId);
      return (res.data || []) as TaskTimeLogItem[];
    },
    enabled: Boolean(taskId),
  });
}

export function useTaskDependencies(taskId: number | null | undefined) {
  return useQuery({
    queryKey: ['taskDependencies', taskId],
    queryFn: async () => {
      if (!taskId) return { depends_on: [], blocking: [] };
      const res = await api.getTaskDependencies(taskId);
      return res.data as { depends_on: TaskItem[]; blocking: TaskItem[] };
    },
    enabled: Boolean(taskId),
  });
}

export function useAddTaskDependency() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ taskId, data }: { taskId: number; data: { depends_on_task_id: number; dependency_type?: string } }) => {
      const res = await api.addTaskDependency(taskId, data);
      return res.data as TaskDependencyItem;
    },
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['taskDependencies', vars.taskId] });
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
    },
  });
}

export function useRemoveTaskDependency() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ taskId, dependsOnTaskId }: { taskId: number; dependsOnTaskId: number }) => {
      const res = await api.removeTaskDependency(taskId, dependsOnTaskId);
      return res.data;
    },
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['taskDependencies', vars.taskId] });
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
    },
  });
}

export function useTaskMetrics(params?: { my_metrics?: boolean }) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['taskMetrics', workspaceId, params],
    queryFn: async () => {
      const res = await api.getTaskMetrics(params);
      return res.data as TaskMetricsData;
    },
  });
}

// ==========================================
// 3. MEETINGS & CALENDAR HOOKS
// ==========================================

export function useMeetings(params?: {
  status?: string;
  date_from?: string;
  date_to?: string;
  project_id?: number;
  lead_id?: number;
  deal_id?: number;
  q?: string;
  view?: 'all' | 'upcoming';
  page?: number;
  per_page?: number;
}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['meetings', workspaceId, params],
    queryFn: async () => {
      const res = await api.getMeetings(params);
      return (res.data || []) as MeetingItem[];
    },
  });
}

export function useMeeting(id: number | null | undefined) {
  return useQuery({
    queryKey: ['meeting', id],
    queryFn: async () => {
      if (!id) return null;
      const res = await api.getMeeting(id);
      return res.data as MeetingItem;
    },
    enabled: Boolean(id),
  });
}

export function useScheduleMeeting() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data: Partial<MeetingItem> & { attendees?: any[] }) => {
      const res = await api.scheduleMeeting(data);
      return res.data as MeetingItem;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['meetings'] });
      queryClient.invalidateQueries({ queryKey: ['meetingCalendar'] });
    },
  });
}

export function useUpdateMeeting() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Partial<MeetingItem> & { attendees?: any[] } }) => {
      const res = await api.updateMeeting(id, data);
      return res.data as MeetingItem;
    },
    onSuccess: (updated) => {
      queryClient.invalidateQueries({ queryKey: ['meetings'] });
      queryClient.invalidateQueries({ queryKey: ['meeting', updated.id] });
      queryClient.invalidateQueries({ queryKey: ['meetingCalendar'] });
    },
  });
}

export function useRescheduleMeeting() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: { meeting_date: string; start_time: string; end_time?: string; agenda?: string; meet_link?: string; location_type?: string; location_address?: string } }) => {
      const res = await api.rescheduleMeeting(id, data);
      return res.data as MeetingItem;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['meetings'] });
      queryClient.invalidateQueries({ queryKey: ['meetingCalendar'] });
    },
  });
}

export function useUpdateMeetingRsvp() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: { attendee_identifier: number | string; status: string; notes?: string } }) => {
      const res = await api.updateMeetingRsvp(id, data);
      return res.data as MeetingAttendeeItem;
    },
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['meeting', vars.id] });
      queryClient.invalidateQueries({ queryKey: ['meetings'] });
    },
  });
}

export function useCreateMeetingFollowUpTask() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: { title?: string; description?: string; due_date?: string; assigned_user_id?: number; priority?: string } }) => {
      const res = await api.createMeetingFollowUpTask(id, data);
      return res.data as TaskItem;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
      queryClient.invalidateQueries({ queryKey: ['taskMetrics'] });
    },
  });
}

export function useMeetingCalendarEvents(params?: { start_date?: string; end_date?: string; my_calendar?: boolean }) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['meetingCalendar', workspaceId, params],
    queryFn: async () => {
      const res = await api.getMeetingCalendarEvents(params);
      return res.data || [];
    },
  });
}

// ==========================================
// 4. ENTERPRISE PROJECT PROFITABILITY & ADVANCED OPERATIONS
// ==========================================

export function useProjectFinancials(projectId: number | null | undefined) {
  return useQuery({
    queryKey: ['projectFinancials', projectId],
    queryFn: async () => {
      if (!projectId) return null;
      const res = await api.getProjectFinancials(projectId);
      return res.data;
    },
    enabled: Boolean(projectId),
  });
}

export function useProjectGantt(projectId: number | null | undefined) {
  return useQuery({
    queryKey: ['projectGantt', projectId],
    queryFn: async () => {
      if (!projectId) return null;
      const res = await api.getProjectGantt(projectId);
      return res.data;
    },
    enabled: Boolean(projectId),
  });
}

export function useProjectWorkload(branchId?: number) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['projectWorkload', workspaceId, branchId],
    queryFn: async () => {
      const res = await api.getProjectWorkload(branchId);
      return res.data || [];
    },
  });
}

export function useProjectDocuments(projectId: number | null | undefined, clientOnly: boolean = false) {
  return useQuery({
    queryKey: ['projectDocuments', projectId, clientOnly],
    queryFn: async () => {
      if (!projectId) return [];
      const res = await api.getProjectDocuments(projectId, clientOnly);
      return res.data || [];
    },
    enabled: Boolean(projectId),
  });
}

export function useUploadProjectDocument() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ projectId, data }: { projectId: number; data: any }) => {
      const res = await api.uploadProjectDocument(projectId, data);
      return res.data;
    },
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['projectDocuments', vars.projectId] });
      queryClient.invalidateQueries({ queryKey: ['project', vars.projectId] });
    },
  });
}

export function useToggleProjectDocumentVisibility() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ documentId, isClientVisible, projectId }: { documentId: number; isClientVisible: boolean; projectId?: number }) => {
      const res = await api.toggleProjectDocumentVisibility(documentId, isClientVisible);
      return res.data;
    },
    onSuccess: (_, vars) => {
      if (vars.projectId) {
        queryClient.invalidateQueries({ queryKey: ['projectDocuments', vars.projectId] });
      }
    },
  });
}

export function useTransitionProjectStatus() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, status, reason }: { id: number; status: string; reason?: string }) => {
      const res = await api.transitionProjectStatus(id, status, reason);
      return res.data;
    },
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['project', vars.id] });
      queryClient.invalidateQueries({ queryKey: ['projects'] });
      queryClient.invalidateQueries({ queryKey: ['projectMetrics'] });
    },
  });
}

export function useSubmitMilestoneReview() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ milestoneId, summary }: { milestoneId: number; summary?: string }) => {
      const res = await api.submitMilestoneForReview(milestoneId, summary);
      return res.data;
    },
    onSuccess: (data: any) => {
      queryClient.invalidateQueries({ queryKey: ['projectMilestones', data?.project_id] });
      queryClient.invalidateQueries({ queryKey: ['project', data?.project_id] });
    },
  });
}

export function useApproveMilestoneInternal() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ milestoneId, notes }: { milestoneId: number; notes?: string }) => {
      const res = await api.approveMilestoneInternal(milestoneId, notes);
      return res.data;
    },
    onSuccess: (data: any) => {
      queryClient.invalidateQueries({ queryKey: ['projectMilestones', data?.project_id] });
      queryClient.invalidateQueries({ queryKey: ['project', data?.project_id] });
    },
  });
}

export function useSendMilestoneToClient() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ milestoneId, notes }: { milestoneId: number; notes?: string }) => {
      const res = await api.sendMilestoneToClient(milestoneId, notes);
      return res.data;
    },
    onSuccess: (data: any) => {
      queryClient.invalidateQueries({ queryKey: ['projectMilestones', data?.project_id] });
      queryClient.invalidateQueries({ queryKey: ['project', data?.project_id] });
    },
  });
}

export function useTimesheets(params?: { user_id?: number; project_id?: number; status?: string; is_running?: boolean }) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['timesheets', workspaceId, params],
    queryFn: async () => {
      const res = await api.getTimesheets(params);
      return res.data || [];
    },
  });
}

export function useStartTaskTimer() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (taskId: number) => {
      const res = await api.startTaskTimer(taskId);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['timesheets'] });
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
    },
  });
}

export function useStopTaskTimer() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ logId, description }: { logId: number; description?: string }) => {
      const res = await api.stopTaskTimer(logId, description);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['timesheets'] });
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
    },
  });
}

export function useApproveTimeLog() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (logId: number) => {
      const res = await api.approveTimeLog(logId);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['timesheets'] });
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
    },
  });
}

export function useRejectTimeLog() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ logId, reason }: { logId: number; reason: string }) => {
      const res = await api.rejectTimeLog(logId, reason);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['timesheets'] });
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
    },
  });
}
