import React, { useState } from 'react';
import {
  CheckSquare,
  Square,
  Clock,
  Calendar,
  AlertTriangle,
  Plus,
  Trash2,
  CheckCircle2,
  User,
  Building2,
  TrendingUp,
  Tag,
  Repeat,
  Check,
  ChevronDown,
  Filter,
} from 'lucide-react';
import {
  useTasks,
  useCreateTask,
  useCompleteTask,
  useToggleTaskChecklist,
  useDeleteTask,
} from '../../hooks/useSalesPipeline';
import { TaskItem, TaskChecklistItem } from '../../api/types';

interface TasksManagerViewProps {
  theme?: 'light' | 'dark';
}

type TaskViewType = 'my_tasks' | 'team_tasks' | 'today' | 'upcoming' | 'overdue' | 'completed';

export default function TasksManagerView({ theme = 'dark' }: TasksManagerViewProps) {
  const [activeView, setActiveView] = useState<TaskViewType>('my_tasks');
  const [showCreateModal, setShowCreateModal] = useState(false);

  // Filter params
  const { data: tasks = [], isLoading } = useTasks({
    view: activeView,
  });

  // Mutations
  const createTaskMutation = useCreateTask();
  const completeTaskMutation = useCompleteTask();
  const toggleChecklistMutation = useToggleTaskChecklist();
  const deleteTaskMutation = useDeleteTask();

  // Create form state
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [dueDate, setDueDate] = useState('');
  const [startDate, setStartDate] = useState('');
  const [priority, setPriority] = useState<'low' | 'medium' | 'high' | 'urgent'>('medium');
  const [isRecurring, setIsRecurring] = useState(false);
  const [recurringRule, setRecurringRule] = useState<'daily' | 'weekly' | 'monthly'>('weekly');
  const [checklistInputs, setChecklistInputs] = useState<string[]>(['']);

  const handleAddChecklistInput = () => {
    setChecklistInputs([...checklistInputs, '']);
  };

  const handleUpdateChecklistInput = (index: number, val: string) => {
    const updated = [...checklistInputs];
    updated[index] = val;
    setChecklistInputs(updated);
  };

  const handleRemoveChecklistInput = (index: number) => {
    setChecklistInputs(checklistInputs.filter((_, i) => i !== index));
  };

  const handleCreateTask = (e: React.FormEvent) => {
    e.preventDefault();
    if (!title.trim()) return;

    const items: TaskChecklistItem[] = checklistInputs
      .filter((txt) => txt.trim().length > 0)
      .map((txt, idx) => ({
        id: `item-${Date.now()}-${idx}`,
        text: txt.trim(),
        completed: false,
      }));

    createTaskMutation.mutate(
      {
        title: title.trim(),
        description: description.trim() || undefined,
        due_date: dueDate || undefined,
        start_date: startDate || undefined,
        priority,
        is_recurring: isRecurring,
        recurring_rule: isRecurring ? recurringRule : undefined,
        checklist_items: items.length > 0 ? items : undefined,
      },
      {
        onSuccess: () => {
          setShowCreateModal(false);
          setTitle('');
          setDescription('');
          setDueDate('');
          setStartDate('');
          setPriority('medium');
          setIsRecurring(false);
          setChecklistInputs(['']);
        },
      }
    );
  };

  const handleToggleChecklist = (taskId: number, itemId: string, currentCompleted: boolean) => {
    toggleChecklistMutation.mutate({
      taskId,
      itemId,
      completed: !currentCompleted,
    });
  };

  const handleComplete = (taskId: number) => {
    completeTaskMutation.mutate(taskId);
  };

  const handleDelete = (taskId: number) => {
    deleteTaskMutation.mutate(taskId);
  };

  const viewTabs: { key: TaskViewType; label: string; icon: any }[] = [
    { key: 'my_tasks', label: 'My Tasks', icon: User },
    { key: 'team_tasks', label: 'Team Tasks', icon: Building2 },
    { key: 'today', label: 'Today', icon: Calendar },
    { key: 'upcoming', label: 'Upcoming', icon: Clock },
    { key: 'overdue', label: 'Overdue', icon: AlertTriangle },
    { key: 'completed', label: 'Completed', icon: CheckCircle2 },
  ];

  return (
    <div className="space-y-4">
      {/* Top Header & View Tabs */}
      <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 className="text-lg font-bold text-slate-100 flex items-center gap-2">
              <CheckSquare className="w-5 h-5 text-indigo-400" />
              Sales Tasks & Follow-ups
            </h3>
            <p className="text-xs text-slate-400">
              Manage day-to-day sales commitments, meetings, checklists, and automated reminders
            </p>
          </div>

          <button
            onClick={() => setShowCreateModal(true)}
            className="flex items-center gap-2 px-3.5 py-1.5 bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white rounded-xl text-xs font-semibold shadow-sm transition-all"
          >
            <Plus className="w-4 h-4" />
            New Task
          </button>
        </div>

        {/* View Selection Tabs */}
        <div className="flex items-center gap-2 overflow-x-auto pb-1 border-t border-slate-800/80 pt-3">
          {viewTabs.map((tab) => {
            const Icon = tab.icon;
            const isActive = activeView === tab.key;
            return (
              <button
                key={tab.key}
                onClick={() => setActiveView(tab.key)}
                className={`flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-medium transition-all shrink-0 ${
                  isActive
                    ? 'bg-indigo-600 text-white font-bold shadow-sm'
                    : 'bg-slate-800/80 text-slate-400 hover:text-slate-200 hover:bg-slate-800'
                }`}
              >
                <Icon className={`w-3.5 h-3.5 ${isActive ? 'text-white' : 'text-slate-400'}`} />
                {tab.label}
              </button>
            );
          })}
        </div>
      </div>

      {/* Tasks List */}
      <div className="space-y-3">
        {isLoading ? (
          <div className="py-20 text-center text-xs text-slate-500">Loading tasks...</div>
        ) : tasks.length === 0 ? (
          <div className="py-20 text-center text-xs text-slate-500 rounded-2xl border bg-slate-900/40 border-slate-800">
            No tasks found in this view.
          </div>
        ) : (
          tasks.map((task) => {
            const isCompleted = task.status === 'done';
            const isOverdue =
              task.due_date &&
              new Date(task.due_date) < new Date(new Date().setHours(0, 0, 0, 0)) &&
              !isCompleted;

            return (
              <div
                key={task.id}
                className={`p-4 rounded-2xl border transition-all ${
                  isCompleted
                    ? 'bg-slate-900/30 border-slate-800/50 opacity-70'
                    : isOverdue
                    ? 'bg-slate-900/70 border-rose-500/40 hover:border-rose-500/80 ring-1 ring-rose-500/20'
                    : 'bg-slate-900/60 border-slate-800 hover:border-slate-700'
                }`}
              >
                <div className="flex items-start justify-between gap-4">
                  {/* Left: Checkbox + Title + Description */}
                  <div className="flex items-start gap-3 flex-1">
                    <button
                      onClick={() => handleComplete(task.id)}
                      disabled={isCompleted}
                      className="mt-0.5 text-slate-400 hover:text-indigo-400 disabled:opacity-50 transition-colors"
                    >
                      {isCompleted ? (
                        <CheckCircle2 className="w-5 h-5 text-emerald-400" />
                      ) : (
                        <Square className="w-5 h-5 text-slate-500" />
                      )}
                    </button>

                    <div className="space-y-1 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <h4
                          className={`text-sm font-semibold text-slate-100 ${
                            isCompleted ? 'line-through text-slate-500' : ''
                          }`}
                        >
                          {task.title}
                        </h4>

                        {/* Priority Badge */}
                        <span
                          className={`px-2 py-0.5 rounded text-[10px] font-semibold uppercase ${
                            task.priority === 'urgent'
                              ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20'
                              : task.priority === 'high'
                              ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                              : task.priority === 'medium'
                              ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20'
                              : 'bg-slate-700/50 text-slate-400'
                          }`}
                        >
                          {task.priority}
                        </span>

                        {/* Recurrence Badge */}
                        {task.is_recurring && (
                          <span className="flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                            <Repeat className="w-3 h-3" />
                            {task.recurring_rule}
                          </span>
                        )}
                      </div>

                      {task.description && (
                        <p className="text-xs text-slate-400">{task.description}</p>
                      )}

                      {/* Context Links (Deal, Lead, Contact) */}
                      <div className="flex flex-wrap items-center gap-3 pt-1 text-xs text-slate-400">
                        {task.deal && (
                          <span className="flex items-center gap-1 text-indigo-300">
                            <TrendingUp className="w-3.5 h-3.5" />
                            Deal: {task.deal.name}
                          </span>
                        )}
                        {task.lead && (
                          <span className="flex items-center gap-1 text-teal-300">
                            <User className="w-3.5 h-3.5" />
                            Lead: {task.lead.name}
                          </span>
                        )}
                        {task.company && (
                          <span className="flex items-center gap-1 text-slate-300">
                            <Building2 className="w-3.5 h-3.5" />
                            {task.company.name}
                          </span>
                        )}
                      </div>

                      {/* Checklist Items */}
                      {task.checklist_items && task.checklist_items.length > 0 && (
                        <div className="mt-3 p-3 rounded-xl bg-slate-800/40 border border-slate-700/60 space-y-1.5">
                          <p className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                            Subtasks Checklist (
                            {task.checklist_items.filter((c) => c.completed).length}/
                            {task.checklist_items.length})
                          </p>
                          <div className="space-y-1">
                            {task.checklist_items.map((item) => (
                              <label
                                key={item.id}
                                className="flex items-center gap-2 text-xs text-slate-300 cursor-pointer hover:text-slate-100"
                              >
                                <input
                                  type="checkbox"
                                  checked={item.completed}
                                  onChange={() =>
                                    handleToggleChecklist(task.id, item.id, item.completed)
                                  }
                                  className="rounded border-slate-700 text-indigo-600 focus:ring-0 bg-slate-900"
                                />
                                <span
                                  className={item.completed ? 'line-through text-slate-500' : ''}
                                >
                                  {item.text}
                                </span>
                              </label>
                            ))}
                          </div>
                        </div>
                      )}
                    </div>
                  </div>

                  {/* Right: Due Date & Actions */}
                  <div className="flex items-center gap-3 shrink-0">
                    <div className="text-right">
                      {task.due_date && (
                        <span
                          className={`text-xs font-medium flex items-center gap-1 justify-end ${
                            isOverdue ? 'text-rose-400 font-bold' : 'text-slate-400'
                          }`}
                        >
                          <Calendar className="w-3.5 h-3.5" />
                          {task.due_date}
                        </span>
                      )}
                      {task.assigned_user && (
                        <span className="text-[11px] text-slate-500 block">
                          Assigned: {task.assigned_user.name}
                        </span>
                      )}
                    </div>

                    <button
                      onClick={() => handleDelete(task.id)}
                      className="p-1.5 rounded-lg text-slate-500 hover:text-rose-400 hover:bg-slate-800 transition-colors"
                      title="Delete task"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                </div>
              </div>
            );
          })
        )}
      </div>

      {/* Create Task Modal */}
      {showCreateModal && (
        <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg p-6 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between">
              <h3 className="text-base font-bold text-slate-100 flex items-center gap-2">
                <CheckSquare className="w-5 h-5 text-indigo-400" />
                Create New Sales Task
              </h3>
              <button
                onClick={() => setShowCreateModal(false)}
                className="text-slate-400 hover:text-slate-200"
              >
                &times;
              </button>
            </div>

            <form onSubmit={handleCreateTask} className="space-y-4">
              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">
                  Task Title <span className="text-rose-400">*</span>
                </label>
                <input
                  type="text"
                  placeholder="e.g. Schedule discovery call with ACME..."
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  required
                  className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">
                  Description
                </label>
                <textarea
                  rows={2}
                  placeholder="Task details and instructions..."
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Due Date</label>
                  <input
                    type="date"
                    value={dueDate}
                    onChange={(e) => setDueDate(e.target.value)}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Priority</label>
                  <select
                    value={priority}
                    onChange={(e) => setPriority(e.target.value as any)}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  >
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                  </select>
                </div>
              </div>

              {/* Recurrence Option */}
              <div className="flex items-center gap-3">
                <label className="flex items-center gap-2 text-xs text-slate-300 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={isRecurring}
                    onChange={(e) => setIsRecurring(e.target.checked)}
                    className="rounded border-slate-700 text-indigo-600 bg-slate-800"
                  />
                  <span>Repeating Schedule</span>
                </label>
                {isRecurring && (
                  <select
                    value={recurringRule}
                    onChange={(e) => setRecurringRule(e.target.value as any)}
                    className="bg-slate-800 border border-slate-700 rounded-lg px-2 py-1 text-xs text-slate-200"
                  >
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                  </select>
                )}
              </div>

              {/* Checklist / Subtasks */}
              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-medium text-slate-300">Checklist Items</label>
                  <button
                    type="button"
                    onClick={handleAddChecklistInput}
                    className="text-[11px] text-indigo-400 hover:text-indigo-300 flex items-center gap-1 font-semibold"
                  >
                    <Plus className="w-3 h-3" />
                    Add Item
                  </button>
                </div>
                <div className="space-y-1.5 max-h-32 overflow-y-auto">
                  {checklistInputs.map((input, idx) => (
                    <div key={idx} className="flex items-center gap-2">
                      <input
                        type="text"
                        placeholder={`Subtask ${idx + 1}`}
                        value={input}
                        onChange={(e) => handleUpdateChecklistInput(idx, e.target.value)}
                        className="flex-1 bg-slate-800 border border-slate-700 rounded-lg px-2.5 py-1 text-xs text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                      />
                      {checklistInputs.length > 1 && (
                        <button
                          type="button"
                          onClick={() => handleRemoveChecklistInput(idx)}
                          className="text-slate-500 hover:text-rose-400 p-1"
                        >
                          &times;
                        </button>
                      )}
                    </div>
                  ))}
                </div>
              </div>

              <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowCreateModal(false)}
                  className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createTaskMutation.isPending}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold disabled:opacity-50"
                >
                  {createTaskMutation.isPending ? 'Creating...' : 'Create Task'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
