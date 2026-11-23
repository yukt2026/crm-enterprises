import React, { useState, useMemo } from 'react';
import {
  GitBranch,
  Play,
  Pause,
  Plus,
  Trash2,
  RefreshCw,
  Search,
  CheckCircle2,
  XCircle,
  Clock,
  Layers,
  ArrowRight,
  Sliders,
  Sparkles,
  Zap,
  Activity,
  History,
  FileText,
  AlertTriangle,
  ChevronRight,
  ExternalLink,
  HelpCircle,
  Copy,
  Settings,
  Send,
  Eye,
  Check,
  X,
  RotateCcw,
  ShieldCheck,
  CheckCircle,
  UserCheck,
  ShieldAlert,
  AlertCircle,
  TrendingUp,
  Cpu,
} from 'lucide-react';
import {
  useWorkflows,
  useWorkflow,
  useCreateWorkflow,
  useUpdateWorkflow,
  usePublishWorkflow,
  usePauseWorkflow,
  useDeleteWorkflow,
  useSimulateWorkflow,
  useWorkflowTemplates,
  useImportWorkflowTemplate,
  useWorkflowRuns,
  useWorkflowRun,
  useRetryWorkflowRun,
  useCancelWorkflowRun,
  useTriggerManualWorkflow,
  useWorkflowApprovals,
  useDecideWorkflowApproval,
  useWorkflowLimits,
  useWorkflowMetrics,
  useWorkflowTriggersCatalog,
} from '../../hooks/useWorkflowAutomation';
import {
  WorkflowItem,
  WorkflowNode,
  WorkflowEdge,
  WorkflowDefinition,
  WorkflowRunItem,
  WorkflowSimulationResult,
  WorkflowApprovalItem,
  WorkflowAutomationLimitItem,
  WorkflowMetricsItem,
} from '../../api/types';

interface VisualWorkflowBuilderViewProps {
  theme?: 'light' | 'dark';
}

const TRIGGER_OPTIONS = [
  // LEADS
  { value: 'lead.created', label: 'Lead Received', category: 'leads' },
  { value: 'lead.updated', label: 'Lead Updated', category: 'leads' },
  { value: 'lead.assigned', label: 'Lead Assigned', category: 'leads' },
  { value: 'lead.score_changed', label: 'Lead Score Changed', category: 'leads' },
  { value: 'lead.status_changed', label: 'Lead Status Changed', category: 'leads' },
  { value: 'lead.followup_overdue', label: 'Lead Follow-up Overdue', category: 'leads' },
  { value: 'lead.converted', label: 'Lead Converted', category: 'leads' },

  // CUSTOMERS
  { value: 'contact.created', label: 'Contact Added', category: 'customers' },
  { value: 'customer.updated', label: 'Customer Updated', category: 'customers' },
  { value: 'customer.tagged', label: 'Customer Tagged', category: 'customers' },
  { value: 'customer.inactive', label: 'Customer Inactive', category: 'customers' },
  { value: 'customer.birthday', label: 'Customer Birthday', category: 'customers' },

  // SALES
  { value: 'deal.created', label: 'Deal Created', category: 'sales' },
  { value: 'deal.stage_changed', label: 'Deal Stage Changed', category: 'sales' },
  { value: 'deal.won', label: 'Deal Won', category: 'sales' },
  { value: 'deal.lost', label: 'Deal Lost', category: 'sales' },
  { value: 'quotation.approved', label: 'Quotation Approved', category: 'sales' },
  { value: 'quotation.expired', label: 'Quotation Expired', category: 'sales' },

  // FINANCE
  { value: 'payment.received', label: 'Payment Received', category: 'finance' },
  { value: 'payment.failed', label: 'Payment Failed', category: 'finance' },
  { value: 'invoice.created', label: 'Invoice Created', category: 'finance' },
  { value: 'invoice.overdue', label: 'Invoice Overdue', category: 'finance' },
  { value: 'refund.initiated', label: 'Refund Initiated', category: 'finance' },
  { value: 'refund.completed', label: 'Refund Completed', category: 'finance' },

  // COMMUNICATION
  { value: 'whatsapp.message_received', label: 'WhatsApp Message Received', category: 'communication' },
  { value: 'email.received', label: 'Email Received', category: 'communication' },
  { value: 'communication.customer_replied', label: 'Customer Replied', category: 'communication' },
  { value: 'telephony.missed_call', label: 'Missed Call', category: 'communication' },
  { value: 'conversation.created', label: 'Conversation Created', category: 'communication' },

  // SUPPORT
  { value: 'ticket.created', label: 'Support Ticket Created', category: 'support' },
  { value: 'ticket.assigned', label: 'Ticket Assigned', category: 'support' },
  { value: 'ticket.overdue', label: 'Ticket SLA Overdue', category: 'support' },
  { value: 'ticket.escalated', label: 'Ticket Escalated', category: 'support' },
  { value: 'ticket.resolved', label: 'Ticket Resolved', category: 'support' },

  // PROJECTS
  { value: 'project.created', label: 'Project Created', category: 'projects' },
  { value: 'project.milestone_completed', label: 'Milestone Completed', category: 'projects' },
  { value: 'task.assigned', label: 'Task Assigned', category: 'projects' },
  { value: 'task.overdue', label: 'Task Overdue', category: 'projects' },
  { value: 'project.completed', label: 'Project Completed', category: 'projects' },

  // HR
  { value: 'attendance.marked', label: 'Attendance Marked', category: 'hr' },
  { value: 'attendance.irregularity', label: 'Attendance Irregularity', category: 'hr' },
  { value: 'leave.requested', label: 'Leave Requested', category: 'hr' },
  { value: 'leave.approved', label: 'Leave Approved', category: 'hr' },
  { value: 'document.expiry_approaching', label: 'Document Expiry Approaching', category: 'hr' },

  // APPOINTMENTS
  { value: 'appointment.booked', label: 'Appointment Booked', category: 'appointments' },
  { value: 'appointment.rescheduled', label: 'Appointment Rescheduled', category: 'appointments' },
  { value: 'appointment.cancelled', label: 'Appointment Cancelled', category: 'appointments' },
  { value: 'appointment.reminder_due', label: 'Appointment Reminder Due', category: 'appointments' },
];

const ACTION_OPTIONS = [
  { value: 'crm.create_task', label: 'Create Follow-up Task', description: 'Creates a pending task with priority & due date' },
  { value: 'crm.assign_lead', label: 'Assign Lead / Employee', description: 'Assigns lead to an executive or round-robin' },
  { value: 'crm.update_lead_status', label: 'Update Lead Status', description: 'Sets status of associated lead' },
  { value: 'crm.add_tags', label: 'Add Tags', description: 'Applies categorization tags to record' },
  { value: 'crm.add_note', label: 'Add CRM Timeline Note', description: 'Logs internal note on customer activity' },
  { value: 'support.create_ticket', label: 'Create Support Ticket', description: 'Generates a helpdesk ticket with priority' },
  { value: 'appointment.create_appointment', label: 'Schedule Appointment', description: 'Creates a calendar meeting or booking' },
  { value: 'notification.generate_reminder', label: 'Generate User Reminder', description: 'Dispatches internal reminder notification' },
  { value: 'communication.send_whatsapp_template', label: 'Send WhatsApp Template', description: 'Official Meta Cloud API message' },
  { value: 'communication.send_email', label: 'Send Email Notification', description: 'Dispatches email via SMTP adapter' },
  { value: 'communication.send_sms', label: 'Send SMS Alert', description: 'Dispatches SMS via telecom gateway' },
  { value: 'sales.create_deal', label: 'Create Pipeline Deal', description: 'Opens new opportunity in deal pipeline' },
  { value: 'projects.create_project_draft', label: 'Create Delivery Project', description: 'Spawns onboarding project from deal' },
  { value: 'approval.request_approval', label: 'Request Human Approval', description: 'Pauses workflow until manager approves' },
  { value: 'webhook.send', label: 'Dispatch Webhook', description: 'Sends HTTP POST payload to approved external URL' },
  { value: 'analytics.generate_report', label: 'Generate BI Report', description: 'Compiles attribution & sales summary' },
];

export const VisualWorkflowBuilderView: React.FC<VisualWorkflowBuilderViewProps> = ({ theme = 'dark' }) => {
  const isDark = theme === 'dark';

  // Navigation states
  const [activeTab, setActiveTab] = useState<'workflows' | 'canvas' | 'history' | 'approvals' | 'monitoring'>('workflows');
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedWorkflowId, setSelectedWorkflowId] = useState<number | null>(null);

  // Approvals & Monitoring states
  const [approvalStatusFilter, setApprovalStatusFilter] = useState<'all' | 'pending' | 'approved' | 'rejected'>('pending');
  const [selectedApproval, setSelectedApproval] = useState<WorkflowApprovalItem | null>(null);
  const [approvalDecision, setApprovalDecision] = useState<'approve' | 'reject'>('approve');
  const [approvalComments, setApprovalComments] = useState('');
  const [showApprovalModal, setShowApprovalModal] = useState(false);

  // Canvas builder state
  const [canvasNodes, setCanvasNodes] = useState<WorkflowNode[]>([]);
  const [canvasEdges, setCanvasEdges] = useState<WorkflowEdge[]>([]);
  const [canvasWorkflowName, setCanvasWorkflowName] = useState('New Workflow Automation');
  const [canvasWorkflowDesc, setCanvasWorkflowDesc] = useState('');
  const [canvasCategory, setCanvasCategory] = useState<'crm' | 'sales' | 'marketing' | 'support' | 'billing'>('crm');
  const [selectedNodeId, setSelectedNodeId] = useState<string | null>(null);
  const [canvasConnectingSourceId, setCanvasConnectingSourceId] = useState<string | null>(null);

  // Modals / Drawers
  const [showTemplatesModal, setShowTemplatesModal] = useState(false);
  const [showSimulatorModal, setShowSimulatorModal] = useState(false);
  const [simulatorPayload, setSimulatorPayload] = useState(
    JSON.stringify(
      {
        lead: {
          id: 101,
          name: 'Vikram Mehta',
          email: 'vikram.mehta@enterprise.com',
          score: 85,
          estimated_value: 450000,
          status: 'new',
          tags: ['high-budget', 'enterprise'],
        },
      },
      null,
      2
    )
  );
  const [simulationResult, setSimulationResult] = useState<WorkflowSimulationResult | null>(null);
  const [selectedRunId, setSelectedRunId] = useState<number | null>(null);
  const [showNewWorkflowModal, setShowNewWorkflowModal] = useState(false);
  const [newWorkflowForm, setNewWorkflowForm] = useState({
    name: '',
    description: '',
    category: 'crm' as 'crm' | 'sales' | 'marketing' | 'support' | 'billing',
    trigger_type: 'lead.created',
  });

  // Data Queries
  const { data: workflows = [], isLoading: workflowsLoading, refetch: refetchWorkflows } = useWorkflows({
    category: selectedCategory === 'all' ? undefined : selectedCategory,
    search: searchQuery || undefined,
  });

  const { data: activeWorkflow, refetch: refetchActiveWorkflow } = useWorkflow(selectedWorkflowId);
  const { data: templates = [] } = useWorkflowTemplates();
  const { data: workflowRuns = [], isLoading: runsLoading, refetch: refetchRuns } = useWorkflowRuns({
    workflow_id: selectedWorkflowId || undefined,
  });
  const { data: activeRun } = useWorkflowRun(selectedRunId);

  // Approvals & Limits Queries
  const { data: approvals = [], refetch: refetchApprovals, isLoading: approvalsLoading } = useWorkflowApprovals({
    status: approvalStatusFilter === 'all' ? undefined : approvalStatusFilter,
  });
  const decideApprovalMut = useDecideWorkflowApproval();
  const { data: metrics, refetch: refetchMetrics, isLoading: metricsLoading } = useWorkflowMetrics();
  const { data: limitsData } = useWorkflowLimits();
  const pendingApprovalsCount = useMemo(() => approvals.filter((a) => a.status === 'pending').length, [approvals]);

  // Mutations
  const createWorkflowMut = useCreateWorkflow();
  const updateWorkflowMut = useUpdateWorkflow();
  const publishWorkflowMut = usePublishWorkflow();
  const pauseWorkflowMut = usePauseWorkflow();
  const deleteWorkflowMut = useDeleteWorkflow();
  const simulateWorkflowMut = useSimulateWorkflow();
  const importTemplateMut = useImportWorkflowTemplate();
  const retryRunMut = useRetryWorkflowRun();
  const cancelRunMut = useCancelWorkflowRun();
  const triggerManualMut = useTriggerManualWorkflow();

  // Load workflow onto canvas when selected
  const handleOpenCanvas = (workflow: WorkflowItem) => {
    setSelectedWorkflowId(workflow.id);
    setCanvasWorkflowName(workflow.name);
    setCanvasWorkflowDesc(workflow.description || '');
    setCanvasCategory(workflow.category as any);

    const definition = workflow.current_version_rel?.definition;
    if (definition && Array.isArray(definition.nodes)) {
      setCanvasNodes(definition.nodes);
      setCanvasEdges(definition.edges || []);
    } else {
      // Default initial layout
      setCanvasNodes([
        {
          id: 'n_trig',
          type: 'trigger',
          position: { x: 250, y: 50 },
          data: { label: 'Start Trigger: ' + workflow.trigger_type },
        },
        {
          id: 'n_act1',
          type: 'action',
          position: { x: 250, y: 200 },
          data: { label: 'Assign & Tag Lead', action_type: 'crm.add_tags', params: { tags: ['automated'] } },
        },
      ]);
      setCanvasEdges([{ id: 'e1', source: 'n_trig', target: 'n_act1' }]);
    }

    setSelectedNodeId(null);
    setActiveTab('canvas');
  };

  // Add new node to canvas
  const handleAddNode = (type: 'trigger' | 'condition' | 'action' | 'delay' | 'wait' | 'stop' | 'approval') => {
    const newId = `node_${Date.now()}`;
    const nextY = canvasNodes.length > 0 ? Math.max(...canvasNodes.map((n) => n.position.y)) + 140 : 100;
    const defaultLabels: Record<string, string> = {
      trigger: 'Trigger Event',
      condition: 'Evaluate Criteria (If / Else)',
      action: 'Execute CRM Action',
      delay: 'Delay Timer',
      wait: 'Wait for Customer Event',
      stop: 'End Workflow',
      approval: 'Human Manager Approval',
    };

    const newNode: WorkflowNode = {
      id: newId,
      type,
      position: { x: 250, y: nextY },
      data: {
        label: defaultLabels[type] || 'New Node',
        action_type: type === 'action' ? 'crm.create_task' : undefined,
        params: type === 'action' ? { title: 'Follow-up Task' } : {},
        conditions:
          type === 'condition'
            ? {
                logical_operator: 'and',
                rules: [{ field: 'lead.score', operator: 'greater_than', value: 50 }],
              }
            : undefined,
        duration: type === 'delay' ? 2 : undefined,
        unit: type === 'delay' ? 'hours' : undefined,
        working_hours_only: type === 'delay' ? true : undefined,
        title: type === 'approval' ? 'Manager Approval Required' : undefined,
        category: type === 'approval' ? 'discount' : undefined,
        approver_role: type === 'approval' ? 'sales_manager' : undefined,
        expiry_hours: type === 'approval' ? 48 : undefined,
        event: type === 'wait' ? 'customer.response' : undefined,
        timeout_hours: type === 'wait' ? 24 : undefined,
      },
    };

    setCanvasNodes((prev) => [...prev, newNode]);

    // Automatically connect with previous node if one exists
    if (canvasNodes.length > 0) {
      const lastNode = canvasNodes[canvasNodes.length - 1];
      const newEdge: WorkflowEdge = {
        id: `e_${lastNode.id}_${newId}`,
        source: lastNode.id,
        target: newId,
        label: lastNode.type === 'condition' ? 'true' : undefined,
        sourceHandle: lastNode.type === 'condition' ? 'true' : undefined,
      };
      setCanvasEdges((prev) => [...prev, newEdge]);
    }

    setSelectedNodeId(newId);
  };

  // Delete node and its edges
  const handleDeleteNode = (nodeId: string) => {
    setCanvasNodes((prev) => prev.filter((n) => n.id !== nodeId));
    setCanvasEdges((prev) => prev.filter((e) => e.source !== nodeId && e.target !== nodeId));
    if (selectedNodeId === nodeId) setSelectedNodeId(null);
  };

  // Save workflow canvas
  const handleSaveCanvas = async () => {
    if (!selectedWorkflowId) return;
    const definition: WorkflowDefinition = {
      nodes: canvasNodes,
      edges: canvasEdges,
    };

    await updateWorkflowMut.mutateAsync({
      id: selectedWorkflowId,
      data: {
        name: canvasWorkflowName,
        description: canvasWorkflowDesc,
        category: canvasCategory,
        definition,
        changelog: `Updated definition at ${new Date().toLocaleTimeString()}`,
      },
    });

    refetchActiveWorkflow();
    refetchWorkflows();
  };

  // Run dry run simulation
  const handleRunSimulation = async () => {
    if (!selectedWorkflowId) return;
    try {
      const parsedPayload = JSON.parse(simulatorPayload);
      const definition: WorkflowDefinition = {
        nodes: canvasNodes,
        edges: canvasEdges,
      };

      const result = await simulateWorkflowMut.mutateAsync({
        id: selectedWorkflowId,
        samplePayload: parsedPayload,
        definition,
      });

      setSimulationResult(result);
    } catch (e: any) {
      alert(`Invalid JSON payload: ${e.message}`);
    }
  };

  // Currently selected node object
  const activeNode = useMemo(
    () => canvasNodes.find((n) => n.id === selectedNodeId) || null,
    [canvasNodes, selectedNodeId]
  );

  // Update selected node data
  const updateActiveNodeData = (updates: Partial<WorkflowNode['data']>) => {
    if (!selectedNodeId) return;
    setCanvasNodes((prev) =>
      prev.map((n) => {
        if (n.id === selectedNodeId) {
          return {
            ...n,
            data: {
              ...n.data,
              ...updates,
            },
          };
        }
        return n;
      })
    );
  };

  // Metrics
  const workflowCounts = useMemo(() => {
    const total = workflows.length;
    const published = workflows.filter((w) => w.status === 'published').length;
    const totalRuns = workflows.reduce((acc, w) => acc + (w.total_runs_count || 0), 0);
    return { total, published, totalRuns };
  }, [workflows]);

  return (
    <div className={`h-full flex flex-col ${isDark ? 'bg-slate-950 text-slate-100' : 'bg-slate-50 text-slate-800'}`}>
      {/* Top Header */}
      <div className={`px-6 py-4 border-b flex items-center justify-between ${isDark ? 'border-slate-800 bg-slate-900/60' : 'border-slate-200 bg-white'}`}>
        <div className="flex items-center space-x-3">
          <div className="p-2.5 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white shadow-md shadow-indigo-500/20">
            <GitBranch className="w-5 h-5" />
          </div>
          <div>
            <div className="flex items-center space-x-2">
              <h1 className="text-xl font-bold tracking-tight">Visual Workflow Automation</h1>
              <span className="px-2 py-0.5 text-xs font-semibold rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                Rule Engine
              </span>
            </div>
            <p className="text-xs text-slate-400 mt-0.5">
              Drag-and-drop orchestration across CRM, Sales, Marketing & Omnichannel triggers
            </p>
          </div>
        </div>

        {/* Tab Switcher & Action Buttons */}
        <div className="flex items-center space-x-3">
          <div className={`flex rounded-lg p-1 border ${isDark ? 'bg-slate-900 border-slate-800' : 'bg-slate-100 border-slate-200'}`}>
            <button
              onClick={() => setActiveTab('workflows')}
              className={`px-3 py-1.5 text-xs font-medium rounded-md transition-all flex items-center space-x-1.5 ${
                activeTab === 'workflows'
                  ? isDark
                    ? 'bg-slate-800 text-white shadow'
                    : 'bg-white text-slate-900 shadow'
                  : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              <Layers className="w-3.5 h-3.5" />
              <span>All Workflows</span>
            </button>
            <button
              onClick={() => setActiveTab('canvas')}
              disabled={!selectedWorkflowId}
              className={`px-3 py-1.5 text-xs font-medium rounded-md transition-all flex items-center space-x-1.5 ${
                activeTab === 'canvas'
                  ? isDark
                    ? 'bg-slate-800 text-white shadow'
                    : 'bg-white text-slate-900 shadow'
                  : selectedWorkflowId
                  ? 'text-slate-400 hover:text-slate-200'
                  : 'text-slate-600 cursor-not-allowed opacity-50'
              }`}
            >
              <GitBranch className="w-3.5 h-3.5" />
              <span>Visual Canvas</span>
            </button>
            <button
              onClick={() => setActiveTab('approvals')}
              className={`px-3 py-1.5 text-xs font-medium rounded-md transition-all flex items-center space-x-1.5 ${
                activeTab === 'approvals'
                  ? isDark
                    ? 'bg-slate-800 text-white shadow'
                    : 'bg-white text-slate-900 shadow'
                  : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              <ShieldCheck className="w-3.5 h-3.5 text-rose-400" />
              <span>Approvals</span>
              {pendingApprovalsCount > 0 && (
                <span className="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-rose-500 text-white">
                  {pendingApprovalsCount}
                </span>
              )}
            </button>
            <button
              onClick={() => setActiveTab('monitoring')}
              className={`px-3 py-1.5 text-xs font-medium rounded-md transition-all flex items-center space-x-1.5 ${
                activeTab === 'monitoring'
                  ? isDark
                    ? 'bg-slate-800 text-white shadow'
                    : 'bg-white text-slate-900 shadow'
                  : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              <Activity className="w-3.5 h-3.5 text-emerald-400" />
              <span>Monitoring</span>
            </button>
            <button
              onClick={() => setActiveTab('history')}
              className={`px-3 py-1.5 text-xs font-medium rounded-md transition-all flex items-center space-x-1.5 ${
                activeTab === 'history'
                  ? isDark
                    ? 'bg-slate-800 text-white shadow'
                    : 'bg-white text-slate-900 shadow'
                  : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              <History className="w-3.5 h-3.5" />
              <span>Execution Runs</span>
            </button>
          </div>

          <button
            onClick={() => setShowTemplatesModal(true)}
            className={`px-3 py-1.5 text-xs font-medium rounded-lg border flex items-center space-x-1.5 transition-all ${
              isDark
                ? 'border-indigo-500/30 text-indigo-300 hover:bg-indigo-500/10'
                : 'border-indigo-200 text-indigo-700 hover:bg-indigo-50'
            }`}
          >
            <Sparkles className="w-3.5 h-3.5" />
            <span>Templates</span>
          </button>

          <button
            onClick={() => setShowNewWorkflowModal(true)}
            className="px-3.5 py-1.5 text-xs font-medium rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white flex items-center space-x-1.5 shadow-sm transition-all"
          >
            <Plus className="w-4 h-4" />
            <span>Create Workflow</span>
          </button>
        </div>
      </div>

      {/* -------------------------------------------------------------------- */}
      {/* TAB 1: WORKFLOWS LIST VIEW */}
      {/* -------------------------------------------------------------------- */}
      {activeTab === 'workflows' && (
        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          {/* Quick Metrics */}
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className={`p-4 rounded-xl border ${isDark ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200'}`}>
              <div className="text-xs text-slate-400 font-medium">Total Automations</div>
              <div className="text-2xl font-bold mt-1 text-indigo-400">{workflowCounts.total}</div>
              <div className="text-xs text-slate-500 mt-1">Configured for tenant</div>
            </div>
            <div className={`p-4 rounded-xl border ${isDark ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200'}`}>
              <div className="text-xs text-slate-400 font-medium">Active & Published</div>
              <div className="text-2xl font-bold mt-1 text-emerald-400">{workflowCounts.published}</div>
              <div className="text-xs text-slate-500 mt-1">Listening to real-time events</div>
            </div>
            <div className={`p-4 rounded-xl border ${isDark ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200'}`}>
              <div className="text-xs text-slate-400 font-medium">Total Graph Runs</div>
              <div className="text-2xl font-bold mt-1 text-amber-400">{workflowCounts.totalRuns}</div>
              <div className="text-xs text-slate-500 mt-1">Dispatches executed</div>
            </div>
            <div className={`p-4 rounded-xl border ${isDark ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200'}`}>
              <div className="text-xs text-slate-400 font-medium">Engine Mode</div>
              <div className="text-sm font-semibold mt-1 text-sky-400 flex items-center space-x-1.5">
                <CheckCircle2 className="w-4 h-4 text-emerald-400" />
                <span>Deterministic Sandboxed</span>
              </div>
              <div className="text-xs text-slate-500 mt-1">Zero arbitrary code execution</div>
            </div>
          </div>

          {/* Filter Bar */}
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex items-center space-x-2">
              {['all', 'crm', 'sales', 'marketing', 'support', 'billing'].map((cat) => (
                <button
                  key={cat}
                  onClick={() => setSelectedCategory(cat)}
                  className={`px-3 py-1.5 rounded-lg text-xs font-medium uppercase tracking-wider transition-all ${
                    selectedCategory === cat
                      ? 'bg-indigo-600 text-white'
                      : isDark
                      ? 'bg-slate-900 text-slate-400 hover:text-slate-200 border border-slate-800'
                      : 'bg-white text-slate-600 hover:text-slate-900 border border-slate-200'
                  }`}
                >
                  {cat}
                </button>
              ))}
            </div>

            <div className="relative w-72">
              <Search className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
              <input
                type="text"
                placeholder="Search workflows by name..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className={`w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border outline-none transition-all ${
                  isDark
                    ? 'bg-slate-900 border-slate-800 focus:border-indigo-500 text-white'
                    : 'bg-white border-slate-200 focus:border-indigo-500 text-slate-900'
                }`}
              />
            </div>
          </div>

          {/* Workflows Grid */}
          {workflowsLoading ? (
            <div className="text-center py-12 text-slate-400 text-xs">Loading workflow definitions...</div>
          ) : workflows.length === 0 ? (
            <div className={`p-12 text-center rounded-2xl border ${isDark ? 'bg-slate-900/30 border-slate-800' : 'bg-white border-slate-200'}`}>
              <GitBranch className="w-12 h-12 text-slate-500 mx-auto mb-3 opacity-40" />
              <h3 className="text-sm font-semibold">No Workflows Configured</h3>
              <p className="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                Create custom trigger-action automations or choose from pre-built templates for automated follow-ups, deal alerts, and WhatsApp workflows.
              </p>
              <div className="flex justify-center space-x-3 mt-4">
                <button
                  onClick={() => setShowTemplatesModal(true)}
                  className="px-3.5 py-1.5 text-xs font-medium rounded-lg border border-indigo-500/30 text-indigo-400 hover:bg-indigo-500/10"
                >
                  Browse Templates
                </button>
                <button
                  onClick={() => setShowNewWorkflowModal(true)}
                  className="px-3.5 py-1.5 text-xs font-medium rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white"
                >
                  Create from Scratch
                </button>
              </div>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {workflows.map((wf) => {
                const isPublished = wf.status === 'published';
                const isPaused = wf.status === 'paused';

                return (
                  <div
                    key={wf.id}
                    className={`rounded-xl border p-5 transition-all hover:border-indigo-500/50 flex flex-col justify-between ${
                      isDark ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200'
                    }`}
                  >
                    <div>
                      <div className="flex items-center justify-between mb-2">
                        <span
                          className={`px-2 py-0.5 text-xs rounded-full font-medium ${
                            isPublished
                              ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                              : isPaused
                              ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                              : 'bg-slate-500/10 text-slate-400 border border-slate-500/20'
                          }`}
                        >
                          {wf.status.toUpperCase()}
                        </span>
                        <span className="text-[10px] font-mono uppercase tracking-wider text-slate-400">
                          v{wf.current_version} • {wf.category}
                        </span>
                      </div>

                      <h3 className="font-semibold text-sm line-clamp-1">{wf.name}</h3>
                      <p className="text-xs text-slate-400 mt-1 line-clamp-2 min-h-[32px]">
                        {wf.description || 'No description provided.'}
                      </p>

                      <div className={`mt-3 p-2.5 rounded-lg border text-xs space-y-1 ${isDark ? 'bg-slate-950/50 border-slate-800/80' : 'bg-slate-50 border-slate-200'}`}>
                        <div className="flex items-center justify-between text-slate-400">
                          <span>Trigger:</span>
                          <span className="font-mono text-indigo-400 font-medium">{wf.trigger_type}</span>
                        </div>
                        <div className="flex items-center justify-between text-slate-400">
                          <span>Executions:</span>
                          <span className="text-slate-300 font-semibold">{wf.total_runs_count} runs</span>
                        </div>
                      </div>
                    </div>

                    <div className="mt-4 pt-3 border-t flex items-center justify-between border-slate-800/60">
                      <div className="flex items-center space-x-1.5">
                        {isPublished ? (
                          <button
                            title="Pause Workflow"
                            onClick={() => pauseWorkflowMut.mutate(wf.id)}
                            className="p-1.5 text-xs rounded-md bg-amber-500/10 text-amber-400 hover:bg-amber-500/20 transition-all"
                          >
                            <Pause className="w-3.5 h-3.5" />
                          </button>
                        ) : (
                          <button
                            title="Publish Workflow"
                            onClick={() => publishWorkflowMut.mutate(wf.id)}
                            className="p-1.5 text-xs rounded-md bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 transition-all"
                          >
                            <Play className="w-3.5 h-3.5" />
                          </button>
                        )}

                        <button
                          title="Manual Trigger Run"
                          onClick={async () => {
                            await triggerManualMut.mutateAsync({ workflowId: wf.id });
                            alert('Triggered manual workflow execution!');
                            setActiveTab('history');
                          }}
                          className="p-1.5 text-xs rounded-md bg-indigo-500/10 text-indigo-400 hover:bg-indigo-500/20 transition-all"
                        >
                          <Zap className="w-3.5 h-3.5" />
                        </button>

                        <button
                          title="Delete Workflow"
                          onClick={() => {
                            if (confirm(`Are you sure you want to delete "${wf.name}"?`)) {
                              deleteWorkflowMut.mutate(wf.id);
                            }
                          }}
                          className="p-1.5 text-xs rounded-md bg-red-500/10 text-red-400 hover:bg-red-500/20 transition-all"
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </button>
                      </div>

                      <button
                        onClick={() => handleOpenCanvas(wf)}
                        className="px-3 py-1.5 text-xs font-medium rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white flex items-center space-x-1 transition-all"
                      >
                        <span>Open Canvas</span>
                        <ChevronRight className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      )}

      {/* -------------------------------------------------------------------- */}
      {/* TAB 2: VISUAL CANVAS BUILDER */}
      {/* -------------------------------------------------------------------- */}
      {activeTab === 'canvas' && (
        <div className="flex-1 flex overflow-hidden">
          {/* Main Visual Canvas Area */}
          <div className="flex-1 flex flex-col relative overflow-hidden">
            {/* Canvas Sub-header */}
            <div className={`px-5 py-2.5 border-b flex items-center justify-between ${isDark ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200'}`}>
              <div className="flex items-center space-x-3">
                <input
                  type="text"
                  value={canvasWorkflowName}
                  onChange={(e) => setCanvasWorkflowName(e.target.value)}
                  className={`text-sm font-bold bg-transparent border-b border-transparent hover:border-slate-500 focus:border-indigo-500 outline-none px-1 py-0.5 ${
                    isDark ? 'text-white' : 'text-slate-900'
                  }`}
                />
                <span className="text-xs px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 font-mono">
                  {canvasCategory}
                </span>
              </div>

              <div className="flex items-center space-x-2">
                <button
                  onClick={() => setShowSimulatorModal(true)}
                  className={`px-3 py-1.5 text-xs font-medium rounded-lg border flex items-center space-x-1.5 transition-all ${
                    isDark
                      ? 'border-slate-700 hover:bg-slate-800 text-slate-200'
                      : 'border-slate-200 hover:bg-slate-50 text-slate-700'
                  }`}
                >
                  <Play className="w-3 h-3 text-emerald-400" />
                  <span>Dry-Run Simulation</span>
                </button>

                <button
                  onClick={handleSaveCanvas}
                  disabled={updateWorkflowMut.isPending}
                  className="px-3.5 py-1.5 text-xs font-medium rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white flex items-center space-x-1.5 shadow-sm transition-all"
                >
                  <Check className="w-3.5 h-3.5" />
                  <span>{updateWorkflowMut.isPending ? 'Saving...' : 'Save Workflow'}</span>
                </button>

                <button
                  onClick={() => setActiveTab('workflows')}
                  className={`p-1.5 rounded-lg border text-slate-400 hover:text-slate-200 ${
                    isDark ? 'border-slate-800 hover:bg-slate-800' : 'border-slate-200 hover:bg-slate-100'
                  }`}
                >
                  <X className="w-4 h-4" />
                </button>
              </div>
            </div>

            {/* Canvas Node Toolbar */}
            <div className={`p-3 border-b flex items-center space-x-2 overflow-x-auto ${isDark ? 'bg-slate-900/40 border-slate-800/80' : 'bg-slate-50 border-slate-200'}`}>
              <span className="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mr-2">
                Add Step:
              </span>
              <button
                onClick={() => handleAddNode('condition')}
                className="px-2.5 py-1 text-xs rounded-md bg-amber-500/10 text-amber-400 border border-amber-500/20 hover:bg-amber-500/20 flex items-center space-x-1 transition-all"
              >
                <GitBranch className="w-3 h-3" />
                <span>If/Else Condition</span>
              </button>
              <button
                onClick={() => handleAddNode('action')}
                className="px-2.5 py-1 text-xs rounded-md bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 hover:bg-indigo-500/20 flex items-center space-x-1 transition-all"
              >
                <Zap className="w-3 h-3" />
                <span>CRM Action</span>
              </button>
              <button
                onClick={() => handleAddNode('delay')}
                className="px-2.5 py-1 text-xs rounded-md bg-sky-500/10 text-sky-400 border border-sky-500/20 hover:bg-sky-500/20 flex items-center space-x-1 transition-all"
              >
                <Clock className="w-3 h-3" />
                <span>Delay Timer</span>
              </button>
              <button
                onClick={() => handleAddNode('approval')}
                className="px-2.5 py-1 text-xs rounded-md bg-rose-500/10 text-rose-400 border border-rose-500/20 hover:bg-rose-500/20 flex items-center space-x-1 transition-all"
              >
                <ShieldCheck className="w-3 h-3" />
                <span>Manager Approval</span>
              </button>
              <button
                onClick={() => handleAddNode('wait')}
                className="px-2.5 py-1 text-xs rounded-md bg-purple-500/10 text-purple-400 border border-purple-500/20 hover:bg-purple-500/20 flex items-center space-x-1 transition-all"
              >
                <Activity className="w-3 h-3" />
                <span>Wait for Event</span>
              </button>
              <button
                onClick={() => handleAddNode('stop')}
                className="px-2.5 py-1 text-xs rounded-md bg-red-500/10 text-red-400 border border-red-500/20 hover:bg-red-500/20 flex items-center space-x-1 transition-all"
              >
                <XCircle className="w-3 h-3" />
                <span>Stop Node</span>
              </button>
            </div>

            {/* Interactive Graph Canvas Area */}
            <div
              className={`flex-1 overflow-auto p-12 relative flex flex-col items-center select-none ${
                isDark ? 'bg-slate-950' : 'bg-slate-100/60'
              }`}
              style={{
                backgroundImage: isDark
                  ? 'radial-gradient(#1e293b 1px, transparent 1px)'
                  : 'radial-gradient(#cbd5e1 1px, transparent 1px)',
                backgroundSize: '24px 24px',
              }}
            >
              {canvasNodes.map((node, index) => {
                const isSelected = selectedNodeId === node.id;
                const isTrigger = node.type === 'trigger';
                const isCondition = node.type === 'condition';
                const isAction = node.type === 'action';
                const isDelay = node.type === 'delay';
                const isWait = node.type === 'wait';
                const isStop = node.type === 'stop';
                const isApproval = node.type === 'approval';

                // Look for downstream edges
                const downstreamEdges = canvasEdges.filter((e) => e.source === node.id);

                return (
                  <React.Fragment key={node.id}>
                    {/* Node Card */}
                    <div
                      onClick={() => setSelectedNodeId(node.id)}
                      className={`w-80 rounded-xl border p-4 shadow-lg cursor-pointer transition-all relative ${
                        isSelected
                          ? 'ring-2 ring-indigo-500 border-indigo-500'
                          : isDark
                          ? 'border-slate-800 hover:border-slate-700 bg-slate-900'
                          : 'border-slate-200 hover:border-slate-300 bg-white'
                      }`}
                    >
                      <div className="flex items-center justify-between mb-2">
                        <div className="flex items-center space-x-2">
                          {isTrigger && <Zap className="w-4 h-4 text-emerald-400" />}
                          {isCondition && <GitBranch className="w-4 h-4 text-amber-400" />}
                          {isAction && <Activity className="w-4 h-4 text-indigo-400" />}
                          {isDelay && <Clock className="w-4 h-4 text-sky-400" />}
                          {isWait && <Sparkles className="w-4 h-4 text-purple-400" />}
                          {isStop && <XCircle className="w-4 h-4 text-red-400" />}
                          {isApproval && <ShieldCheck className="w-4 h-4 text-rose-400" />}
                          <span className="text-xs font-bold uppercase tracking-wider text-slate-400">
                            {node.type}
                          </span>
                        </div>

                        {!isTrigger && (
                          <button
                            onClick={(e) => {
                              e.stopPropagation();
                              handleDeleteNode(node.id);
                            }}
                            className="text-slate-500 hover:text-red-400 p-1"
                          >
                            <Trash2 className="w-3.5 h-3.5" />
                          </button>
                        )}
                      </div>

                      <div className="font-semibold text-sm line-clamp-1">{node.data.label}</div>

                      {/* Node summary description */}
                      {isCondition && (
                        <div className="text-xs text-amber-300/80 mt-1">
                          {node.data.conditions?.rules?.length || 0} rule criteria configured
                        </div>
                      )}
                      {isAction && (
                        <div className="text-xs text-indigo-300/80 mt-1 font-mono">
                          {node.data.action_type || 'Unspecified action'}
                        </div>
                      )}
                      {isDelay && (
                        <div className="text-xs text-sky-300/80 mt-1">
                          Wait {node.data.duration} {node.data.unit} {node.data.working_hours_only ? '(Working hours)' : ''}
                        </div>
                      )}
                      {isWait && (
                        <div className="text-xs text-purple-300/80 mt-1">
                          Until {node.data.event || 'response'} (timeout {node.data.timeout_hours}h)
                        </div>
                      )}
                      {isApproval && (
                        <div className="text-xs text-rose-300/80 mt-1">
                          Role: {node.data.approver_role || 'sales_manager'} ({node.data.expiry_hours || 48}h expiry)
                        </div>
                      )}

                      {/* Ports */}
                      {isCondition ? (
                        <div className="flex justify-between mt-3 pt-2 border-t border-slate-800 text-[10px] font-bold">
                          <span className="text-emerald-400">Branch: TRUE</span>
                          <span className="text-red-400">Branch: FALSE</span>
                        </div>
                      ) : isApproval ? (
                        <div className="flex justify-between mt-3 pt-2 border-t border-slate-800 text-[10px] font-bold">
                          <span className="text-emerald-400">Branch: APPROVED</span>
                          <span className="text-rose-400">Branch: REJECTED</span>
                        </div>
                      ) : null}
                    </div>

                    {/* Arrow to Next Node */}
                    {index < canvasNodes.length - 1 && (
                      <div className="flex flex-col items-center my-2 text-slate-500">
                        <div className="w-0.5 h-6 bg-slate-700" />
                        <ArrowRight className="w-4 h-4 rotate-90 text-indigo-400" />
                      </div>
                    )}
                  </React.Fragment>
                );
              })}
            </div>
          </div>

          {/* Right Configuration Drawer */}
          {activeNode && (
            <div className={`w-96 border-l flex flex-col ${isDark ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'}`}>
              <div className="p-4 border-b flex items-center justify-between border-slate-800">
                <div className="flex items-center space-x-2">
                  <Sliders className="w-4 h-4 text-indigo-400" />
                  <h3 className="font-bold text-sm">Node Configuration</h3>
                </div>
                <button onClick={() => setSelectedNodeId(null)} className="text-slate-400 hover:text-white">
                  <X className="w-4 h-4" />
                </button>
              </div>

              <div className="flex-1 overflow-y-auto p-4 space-y-4">
                <div>
                  <label className="text-xs font-semibold text-slate-400">Node Title</label>
                  <input
                    type="text"
                    value={activeNode.data.label}
                    onChange={(e) => updateActiveNodeData({ label: e.target.value })}
                    className={`w-full mt-1 px-3 py-1.5 text-xs rounded-lg border outline-none ${
                      isDark ? 'bg-slate-950 border-slate-800 text-white' : 'bg-slate-50 border-slate-200 text-slate-900'
                    }`}
                  />
                </div>

                {/* Trigger Config */}
                {activeNode.type === 'trigger' && (
                  <div>
                    <label className="text-xs font-semibold text-slate-400">Trigger Event</label>
                    <select
                      value={activeNode.data.action_type || 'lead.created'}
                      onChange={(e) => updateActiveNodeData({ action_type: e.target.value, label: 'Trigger: ' + e.target.value })}
                      className={`w-full mt-1 px-3 py-1.5 text-xs rounded-lg border outline-none ${
                        isDark ? 'bg-slate-950 border-slate-800 text-white' : 'bg-slate-50 border-slate-200 text-slate-900'
                      }`}
                    >
                      {TRIGGER_OPTIONS.map((opt) => (
                        <option key={opt.value} value={opt.value}>
                          [{opt.category.toUpperCase()}] {opt.label}
                        </option>
                      ))}
                    </select>
                    <p className="text-[11px] text-slate-500 mt-1">
                      This workflow will automatically execute whenever this event fires in your tenant.
                    </p>
                  </div>
                )}

                {/* Condition Config */}
                {activeNode.type === 'condition' && (
                  <div className="space-y-3">
                    <div className="flex items-center justify-between">
                      <label className="text-xs font-semibold text-slate-400">Rule Logic</label>
                      <select
                        value={activeNode.data.conditions?.logical_operator || 'and'}
                        onChange={(e) =>
                          updateActiveNodeData({
                            conditions: {
                              ...activeNode.data.conditions,
                              logical_operator: e.target.value as any,
                            },
                          })
                        }
                        className="px-2 py-0.5 text-xs rounded bg-slate-800 border border-slate-700 text-white"
                      >
                        <option value="and">ALL match (AND)</option>
                        <option value="or">ANY match (OR)</option>
                      </select>
                    </div>

                    {(activeNode.data.conditions?.rules || []).map((rule, idx) => (
                      <div key={idx} className={`p-3 rounded-lg border space-y-2 ${isDark ? 'bg-slate-950 border-slate-800' : 'bg-slate-50 border-slate-200'}`}>
                        <div className="flex items-center justify-between">
                          <span className="text-[10px] font-bold text-slate-500 uppercase">Rule #{idx + 1}</span>
                          <button
                            onClick={() => {
                              const newRules = (activeNode.data.conditions?.rules || []).filter((_, i) => i !== idx);
                              updateActiveNodeData({
                                conditions: {
                                  ...activeNode.data.conditions,
                                  rules: newRules,
                                },
                              });
                            }}
                            className="text-red-400 hover:text-red-300 text-[10px]"
                          >
                            Remove
                          </button>
                        </div>
                        <input
                          type="text"
                          placeholder="Field (e.g. lead.score, lead.status)"
                          value={rule.field}
                          onChange={(e) => {
                            const newRules = [...(activeNode.data.conditions?.rules || [])];
                            newRules[idx].field = e.target.value;
                            updateActiveNodeData({
                              conditions: { ...activeNode.data.conditions, rules: newRules },
                            });
                          }}
                          className="w-full px-2 py-1 text-xs rounded bg-slate-900 border border-slate-800 text-white"
                        />
                        <div className="grid grid-cols-2 gap-2">
                          <select
                            value={rule.operator}
                            onChange={(e) => {
                              const newRules = [...(activeNode.data.conditions?.rules || [])];
                              newRules[idx].operator = e.target.value as any;
                              updateActiveNodeData({
                                conditions: { ...activeNode.data.conditions, rules: newRules },
                              });
                            }}
                            className="px-2 py-1 text-xs rounded bg-slate-900 border border-slate-800 text-white"
                          >
                            <option value="equals">equals</option>
                            <option value="not_equals">not equals</option>
                            <option value="greater_than">&gt; greater than</option>
                            <option value="less_than">&lt; less than</option>
                            <option value="contains">contains</option>
                            <option value="in">in list</option>
                          </select>
                          <input
                            type="text"
                            placeholder="Value"
                            value={rule.value}
                            onChange={(e) => {
                              const newRules = [...(activeNode.data.conditions?.rules || [])];
                              newRules[idx].value = e.target.value;
                              updateActiveNodeData({
                                conditions: { ...activeNode.data.conditions, rules: newRules },
                              });
                            }}
                            className="px-2 py-1 text-xs rounded bg-slate-900 border border-slate-800 text-white"
                          />
                        </div>
                      </div>
                    ))}

                    <button
                      onClick={() => {
                        const newRules = [
                          ...(activeNode.data.conditions?.rules || []),
                          { field: 'lead.status', operator: 'equals' as any, value: 'qualified' },
                        ];
                        updateActiveNodeData({
                          conditions: {
                            ...activeNode.data.conditions,
                            rules: newRules,
                          },
                        });
                      }}
                      className="w-full py-1 text-xs rounded border border-dashed border-slate-700 text-slate-300 hover:border-slate-500"
                    >
                      + Add Rule Criterion
                    </button>
                  </div>
                )}

                {/* Action Config */}
                {activeNode.type === 'action' && (
                  <div className="space-y-3">
                    <div>
                      <label className="text-xs font-semibold text-slate-400">Action Type</label>
                      <select
                        value={activeNode.data.action_type || 'crm.create_task'}
                        onChange={(e) => updateActiveNodeData({ action_type: e.target.value })}
                        className={`w-full mt-1 px-3 py-1.5 text-xs rounded-lg border outline-none ${
                          isDark ? 'bg-slate-950 border-slate-800 text-white' : 'bg-slate-50 border-slate-200 text-slate-900'
                        }`}
                      >
                        {ACTION_OPTIONS.map((act) => (
                          <option key={act.value} value={act.value}>
                            {act.label}
                          </option>
                        ))}
                      </select>
                    </div>

                    {/* Action parameters based on type */}
                    {activeNode.data.action_type === 'crm.create_task' && (
                      <div className="space-y-2">
                        <label className="text-xs font-semibold text-slate-400">Task Title</label>
                        <input
                          type="text"
                          value={activeNode.data.params?.title || ''}
                          onChange={(e) =>
                            updateActiveNodeData({
                              params: { ...activeNode.data.params, title: e.target.value },
                            })
                          }
                          className="w-full px-2 py-1.5 text-xs rounded bg-slate-950 border border-slate-800 text-white"
                          placeholder="e.g. Follow up on Enterprise Lead"
                        />
                      </div>
                    )}

                    {activeNode.data.action_type === 'crm.update_lead_status' && (
                      <div className="space-y-2">
                        <label className="text-xs font-semibold text-slate-400">New Lead Status</label>
                        <select
                          value={activeNode.data.params?.status || 'contacted'}
                          onChange={(e) =>
                            updateActiveNodeData({
                              params: { ...activeNode.data.params, status: e.target.value },
                            })
                          }
                          className="w-full px-2 py-1.5 text-xs rounded bg-slate-950 border border-slate-800 text-white"
                        >
                          <option value="new">New</option>
                          <option value="contacted">Contacted</option>
                          <option value="qualified">Qualified</option>
                          <option value="proposal_sent">Proposal Sent</option>
                          <option value="unqualified">Unqualified</option>
                        </select>
                      </div>
                    )}

                    {activeNode.data.action_type === 'crm.add_tags' && (
                      <div className="space-y-2">
                        <label className="text-xs font-semibold text-slate-400">Tags (comma separated)</label>
                        <input
                          type="text"
                          value={(activeNode.data.params?.tags || []).join(', ')}
                          onChange={(e) =>
                            updateActiveNodeData({
                              params: {
                                ...activeNode.data.params,
                                tags: e.target.value.split(',').map((t) => t.trim()).filter(Boolean),
                              },
                            })
                          }
                          className="w-full px-2 py-1.5 text-xs rounded bg-slate-950 border border-slate-800 text-white"
                          placeholder="high-priority, automated, hot"
                        />
                      </div>
                    )}

                    {activeNode.data.action_type?.startsWith('communication.') && (
                      <div className="p-3 rounded-lg bg-indigo-500/10 border border-indigo-500/20 text-xs text-indigo-300">
                        <ShieldCheck className="w-4 h-4 inline mr-1 text-emerald-400" />
                        <strong>Compliance Guard:</strong> Communication suppression, opt-out status, and customer consent preferences are automatically re-checked before message dispatch.
                      </div>
                    )}
                  </div>
                )}

                {/* Delay Config */}
                {activeNode.type === 'delay' && (
                  <div className="space-y-3">
                    <label className="text-xs font-semibold text-slate-400">Delay Duration</label>
                    <div className="grid grid-cols-2 gap-2">
                      <input
                        type="number"
                        min="1"
                        value={activeNode.data.duration || 1}
                        onChange={(e) => updateActiveNodeData({ duration: parseInt(e.target.value) || 1 })}
                        className="px-2 py-1.5 text-xs rounded bg-slate-950 border border-slate-800 text-white"
                      />
                      <select
                        value={activeNode.data.unit || 'hours'}
                        onChange={(e) => updateActiveNodeData({ unit: e.target.value as any })}
                        className="px-2 py-1.5 text-xs rounded bg-slate-950 border border-slate-800 text-white"
                      >
                        <option value="minutes">Minutes</option>
                        <option value="hours">Hours</option>
                        <option value="days">Days</option>
                      </select>
                    </div>
                    <label className="flex items-center space-x-2 text-xs text-slate-300 cursor-pointer pt-1">
                      <input
                        type="checkbox"
                        checked={Boolean(activeNode.data.working_hours_only)}
                        onChange={(e) => updateActiveNodeData({ working_hours_only: e.target.checked })}
                        className="rounded bg-slate-950 border-slate-700 text-indigo-600 focus:ring-indigo-500"
                      />
                      <span>Respect Working Hours (09:00 - 18:00 IST)</span>
                    </label>
                  </div>
                )}

                {/* Approval Config */}
                {activeNode.type === 'approval' && (
                  <div className="space-y-3">
                    <div>
                      <label className="text-xs font-semibold text-slate-400">Approval Title</label>
                      <input
                        type="text"
                        value={activeNode.data.title || ''}
                        onChange={(e) => updateActiveNodeData({ title: e.target.value })}
                        placeholder="e.g. Manager Discount Signoff"
                        className="w-full mt-1 px-2 py-1.5 text-xs rounded bg-slate-950 border border-slate-800 text-white"
                      />
                    </div>
                    <div>
                      <label className="text-xs font-semibold text-slate-400">Category</label>
                      <select
                        value={activeNode.data.category || 'discount'}
                        onChange={(e) => updateActiveNodeData({ category: e.target.value })}
                        className="w-full mt-1 px-2 py-1.5 text-xs rounded bg-slate-950 border border-slate-800 text-white"
                      >
                        <option value="discount">High-Value Discount</option>
                        <option value="refund">Refund Request</option>
                        <option value="expense">Large Expense Claim</option>
                        <option value="customer_update">Sensitive Customer Update</option>
                        <option value="campaign">Marketing Campaign Launch</option>
                        <option value="record_transfer">Important Record Transfer</option>
                        <option value="financial">Financial Adjustment</option>
                        <option value="general">General Approval</option>
                      </select>
                    </div>
                    <div>
                      <label className="text-xs font-semibold text-slate-400">Approver Role</label>
                      <select
                        value={activeNode.data.approver_role || 'sales_manager'}
                        onChange={(e) => updateActiveNodeData({ approver_role: e.target.value })}
                        className="w-full mt-1 px-2 py-1.5 text-xs rounded bg-slate-950 border border-slate-800 text-white"
                      >
                        <option value="sales_manager">Sales Manager</option>
                        <option value="finance_manager">Finance Manager</option>
                        <option value="branch_manager">Branch Manager</option>
                        <option value="admin">Business Administrator</option>
                      </select>
                    </div>
                    <div>
                      <label className="text-xs font-semibold text-slate-400">Expiry (Hours)</label>
                      <input
                        type="number"
                        min="1"
                        value={activeNode.data.expiry_hours || 48}
                        onChange={(e) => updateActiveNodeData({ expiry_hours: parseInt(e.target.value) || 48 })}
                        className="w-full mt-1 px-2 py-1.5 text-xs rounded bg-slate-950 border border-slate-800 text-white"
                      />
                    </div>
                    <div className="p-2.5 rounded-lg bg-rose-500/10 border border-rose-500/20 text-xs text-rose-300">
                      <ShieldCheck className="w-4 h-4 inline mr-1 text-rose-400" />
                      <strong>Human-in-the-Loop Gate:</strong> Pauses workflow in <code>waiting_approval</code> state until designated approver explicitly approves or rejects.
                    </div>
                  </div>
                )}

                {/* Wait Config */}
                {activeNode.type === 'wait' && (
                  <div className="space-y-3">
                    <label className="text-xs font-semibold text-slate-400">Wait For Event</label>
                    <input
                      type="text"
                      value={activeNode.data.event || 'customer.response'}
                      onChange={(e) => updateActiveNodeData({ event: e.target.value })}
                      className="w-full px-2 py-1.5 text-xs rounded bg-slate-950 border border-slate-800 text-white"
                      placeholder="e.g. customer.response, payment.received"
                    />
                    <label className="text-xs font-semibold text-slate-400">Timeout (Hours)</label>
                    <input
                      type="number"
                      value={activeNode.data.timeout_hours || 48}
                      onChange={(e) => updateActiveNodeData({ timeout_hours: parseInt(e.target.value) || 48 })}
                      className="w-full px-2 py-1.5 text-xs rounded bg-slate-950 border border-slate-800 text-white"
                    />
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      )}

      {/* -------------------------------------------------------------------- */}
      {/* TAB 3: EXECUTION HISTORY */}
      {/* -------------------------------------------------------------------- */}
      {activeTab === 'history' && (
        <div className="flex-1 overflow-y-auto p-6 space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-base font-bold">Workflow Execution Audit Trail</h2>
              <p className="text-xs text-slate-400">Step-by-step logs, idempotency protection, and manual retry controls</p>
            </div>
            <button
              onClick={() => refetchRuns()}
              className="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-700 hover:bg-slate-800 text-slate-300 flex items-center space-x-1.5"
            >
              <RefreshCw className="w-3.5 h-3.5" />
              <span>Refresh Runs</span>
            </button>
          </div>

          {runsLoading ? (
            <div className="text-center py-12 text-slate-400 text-xs">Loading execution runs...</div>
          ) : workflowRuns.length === 0 ? (
            <div className={`p-8 text-center rounded-xl border ${isDark ? 'bg-slate-900/40 border-slate-800' : 'bg-white border-slate-200'}`}>
              <History className="w-10 h-10 text-slate-500 mx-auto mb-2 opacity-50" />
              <p className="text-xs text-slate-400">No execution runs recorded yet. Trigger a workflow to inspect step logs.</p>
            </div>
          ) : (
            <div className={`rounded-xl border overflow-hidden ${isDark ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200'}`}>
              <table className="w-full text-xs text-left">
                <thead className={`border-b text-slate-400 uppercase font-medium ${isDark ? 'border-slate-800 bg-slate-900' : 'border-slate-200 bg-slate-50'}`}>
                  <tr>
                    <th className="px-4 py-3">Run ID</th>
                    <th className="px-4 py-3">Workflow</th>
                    <th className="px-4 py-3">Trigger Event</th>
                    <th className="px-4 py-3">Status</th>
                    <th className="px-4 py-3">Duration / Wait</th>
                    <th className="px-4 py-3">Started</th>
                    <th className="px-4 py-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800">
                  {workflowRuns.map((run) => (
                    <tr key={run.id} className="hover:bg-slate-800/30 transition-all">
                      <td className="px-4 py-3 font-mono text-slate-300 font-medium">#{run.id}</td>
                      <td className="px-4 py-3 font-medium text-slate-200">{run.workflow?.name || `Workflow #${run.workflow_id}`}</td>
                      <td className="px-4 py-3">
                        <span className="font-mono px-2 py-0.5 rounded bg-slate-800 text-indigo-400 border border-slate-700">
                          {run.trigger_event}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`px-2 py-0.5 rounded-full font-medium text-[11px] ${
                            run.status === 'completed'
                              ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                              : run.status === 'waiting'
                              ? 'bg-sky-500/10 text-sky-400 border border-sky-500/20'
                              : run.status === 'failed'
                              ? 'bg-red-500/10 text-red-400 border border-red-500/20'
                              : 'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                          }`}
                        >
                          {run.status.toUpperCase()}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-slate-400">
                        {run.resume_at ? `Resumes at ${new Date(run.resume_at).toLocaleTimeString()}` : 'Instant execution'}
                      </td>
                      <td className="px-4 py-3 text-slate-400">{new Date(run.created_at).toLocaleString()}</td>
                      <td className="px-4 py-3 text-right space-x-2">
                        <button
                          onClick={() => setSelectedRunId(run.id)}
                          className="px-2 py-1 rounded bg-indigo-500/10 text-indigo-300 hover:bg-indigo-500/20 text-xs"
                        >
                          Step Logs
                        </button>
                        {run.status === 'failed' && (
                          <button
                            onClick={() => retryRunMut.mutate(run.id)}
                            className="px-2 py-1 rounded bg-amber-500/10 text-amber-300 hover:bg-amber-500/20 text-xs"
                          >
                            Retry
                          </button>
                        )}
                        {run.status === 'waiting' && (
                          <button
                            onClick={() => cancelRunMut.mutate(run.id)}
                            className="px-2 py-1 rounded bg-red-500/10 text-red-300 hover:bg-red-500/20 text-xs"
                          >
                            Cancel
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

      {/* -------------------------------------------------------------------- */}
      {/* TAB 4: HUMAN APPROVALS INBOX */}
      {/* -------------------------------------------------------------------- */}
      {activeTab === 'approvals' && (
        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <div className="flex items-center space-x-2">
                <h2 className="text-base font-bold">Manager Approvals Inbox</h2>
                {pendingApprovalsCount > 0 && (
                  <span className="px-2 py-0.5 text-xs font-bold rounded-full bg-rose-500/10 text-rose-400 border border-rose-500/20">
                    {pendingApprovalsCount} Pending
                  </span>
                )}
              </div>
              <p className="text-xs text-slate-400 mt-0.5">
                Human-in-the-loop review for high-value discounts, refunds, large expenses, campaigns, and record transfers
              </p>
            </div>

            <div className="flex items-center space-x-2">
              <div className={`flex rounded-lg p-1 border ${isDark ? 'bg-slate-900 border-slate-800' : 'bg-slate-100 border-slate-200'}`}>
                {(['all', 'pending', 'approved', 'rejected'] as const).map((st) => (
                  <button
                    key={st}
                    onClick={() => setApprovalStatusFilter(st)}
                    className={`px-3 py-1 text-xs font-medium rounded-md capitalize transition-all ${
                      approvalStatusFilter === st
                        ? isDark
                          ? 'bg-slate-800 text-white shadow'
                          : 'bg-white text-slate-900 shadow'
                        : 'text-slate-400 hover:text-slate-200'
                    }`}
                  >
                    {st}
                  </button>
                ))}
              </div>

              <button
                onClick={() => refetchApprovals()}
                className="p-1.5 rounded-lg border border-slate-700 hover:bg-slate-800 text-slate-300"
                title="Refresh Approvals"
              >
                <RefreshCw className="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          {approvalsLoading ? (
            <div className="text-center py-12 text-slate-400 text-xs">Loading approvals inbox...</div>
          ) : approvals.length === 0 ? (
            <div className={`p-12 text-center rounded-xl border ${isDark ? 'bg-slate-900/40 border-slate-800' : 'bg-white border-slate-200'}`}>
              <ShieldCheck className="w-12 h-12 text-slate-500 mx-auto mb-3 opacity-40" />
              <h3 className="text-sm font-semibold text-slate-300">No Approvals Found</h3>
              <p className="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                No automation approval requests match the selected filter. Approvals are generated automatically when a workflow reaches an approval node.
              </p>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {approvals.map((appr) => {
                const isPending = appr.status === 'pending';
                const isApproved = appr.status === 'approved';
                const isRejected = appr.status === 'rejected';

                return (
                  <div
                    key={appr.id}
                    className={`p-5 rounded-xl border flex flex-col justify-between transition-all ${
                      isDark ? 'bg-slate-900/60 border-slate-800 hover:border-slate-700' : 'bg-white border-slate-200 hover:border-slate-300'
                    }`}
                  >
                    <div>
                      <div className="flex items-center justify-between mb-2">
                        <span className="text-[10px] font-mono px-2 py-0.5 rounded uppercase font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                          {appr.category}
                        </span>

                        <span
                          className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${
                            isApproved
                              ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                              : isRejected
                              ? 'bg-red-500/10 text-red-400 border border-red-500/20'
                              : 'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                          }`}
                        >
                          {appr.status}
                        </span>
                      </div>

                      <h3 className="text-sm font-bold text-slate-100">{appr.title}</h3>
                      <p className="text-xs text-slate-400 mt-0.5">
                        Workflow: <span className="text-slate-300 font-medium">{appr.workflow?.name || `#${appr.workflow_id}`}</span> (Run #{appr.workflow_run_id})
                      </p>

                      <div className="mt-3 p-3 rounded-lg bg-slate-950/60 border border-slate-800/80 space-y-1 text-xs">
                        <div className="flex justify-between">
                          <span className="text-slate-400">Required Role:</span>
                          <span className="font-medium text-slate-300 font-mono">{appr.approver_role || 'Manager'}</span>
                        </div>
                        {appr.context_summary?.amount && (
                          <div className="flex justify-between">
                            <span className="text-slate-400">Transaction Value:</span>
                            <span className="font-semibold text-emerald-400 font-mono">₹{Number(appr.context_summary.amount).toLocaleString('en-IN')}</span>
                          </div>
                        )}
                        <div className="flex justify-between">
                          <span className="text-slate-400">Requested:</span>
                          <span className="text-slate-300">{new Date(appr.created_at).toLocaleString()}</span>
                        </div>
                        {appr.expires_at && (
                          <div className="flex justify-between">
                            <span className="text-slate-400">Expires:</span>
                            <span className="text-rose-400">{new Date(appr.expires_at).toLocaleString()}</span>
                          </div>
                        )}
                      </div>

                      {appr.comments && (
                        <div className="mt-2.5 text-xs text-slate-400 italic bg-slate-800/40 p-2 rounded">
                          "{appr.comments}"
                        </div>
                      )}
                    </div>

                    {isPending ? (
                      <div className="mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-end space-x-2">
                        <button
                          onClick={() => {
                            setSelectedApproval(appr);
                            setApprovalDecision('reject');
                            setApprovalComments('');
                            setShowApprovalModal(true);
                          }}
                          className="px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 transition-all flex items-center space-x-1"
                        >
                          <X className="w-3.5 h-3.5" />
                          <span>Reject</span>
                        </button>
                        <button
                          onClick={() => {
                            setSelectedApproval(appr);
                            setApprovalDecision('approve');
                            setApprovalComments('');
                            setShowApprovalModal(true);
                          }}
                          className="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm transition-all flex items-center space-x-1"
                        >
                          <Check className="w-3.5 h-3.5" />
                          <span>Approve & Continue</span>
                        </button>
                      </div>
                    ) : (
                      <div className="mt-4 pt-3 border-t border-slate-800/80 text-[11px] text-slate-500 flex items-center justify-between">
                        <span>Decided by {appr.decisionBy?.name || 'Administrator'}</span>
                        <span>{appr.decision_at ? new Date(appr.decision_at).toLocaleString() : ''}</span>
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          )}
        </div>
      )}

      {/* -------------------------------------------------------------------- */}
      {/* TAB 5: AUTOMATION MONITORING & METRICS */}
      {/* -------------------------------------------------------------------- */}
      {activeTab === 'monitoring' && (
        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          <div className="flex items-center justify-between">
            <div>
              <div className="flex items-center space-x-2">
                <h2 className="text-base font-bold">Automation Execution Engine Monitoring</h2>
                <span className="flex items-center space-x-1 px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
                  <span>Real-time Active</span>
                </span>
              </div>
              <p className="text-xs text-slate-400 mt-0.5">
                Execution throughput, loop protection telemetry, error rate, and monthly quota limits
              </p>
            </div>
            <button
              onClick={() => refetchMetrics()}
              className="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-700 hover:bg-slate-800 text-slate-300 flex items-center space-x-1.5"
            >
              <RefreshCw className="w-3.5 h-3.5" />
              <span>Refresh Metrics</span>
            </button>
          </div>

          {/* Metric Cards Grid */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div className={`p-4 rounded-xl border ${isDark ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200'}`}>
              <div className="flex items-center justify-between">
                <span className="text-xs text-slate-400 font-medium">Workflows</span>
                <Layers className="w-4 h-4 text-indigo-400" />
              </div>
              <div className="text-2xl font-bold mt-2 text-indigo-400">{metrics?.total_workflows || 0}</div>
              <div className="text-xs text-slate-500 mt-1">{metrics?.active_workflows || 0} active & running</div>
            </div>

            <div className={`p-4 rounded-xl border ${isDark ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200'}`}>
              <div className="flex items-center justify-between">
                <span className="text-xs text-slate-400 font-medium">Total Graph Runs</span>
                <Activity className="w-4 h-4 text-sky-400" />
              </div>
              <div className="text-2xl font-bold mt-2 text-sky-400">{metrics?.total_runs || 0}</div>
              <div className="text-xs text-slate-500 mt-1">{metrics?.completed_runs || 0} successful completions</div>
            </div>

            <div className={`p-4 rounded-xl border ${isDark ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200'}`}>
              <div className="flex items-center justify-between">
                <span className="text-xs text-slate-400 font-medium">Success Rate</span>
                <CheckCircle2 className="w-4 h-4 text-emerald-400" />
              </div>
              <div className="text-2xl font-bold mt-2 text-emerald-400">{metrics?.success_rate || 100}%</div>
              <div className="text-xs text-slate-500 mt-1">{metrics?.failed_runs || 0} failed runs recorded</div>
            </div>

            <div className={`p-4 rounded-xl border ${isDark ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200'}`}>
              <div className="flex items-center justify-between">
                <span className="text-xs text-slate-400 font-medium">Avg Execution Time</span>
                <Clock className="w-4 h-4 text-amber-400" />
              </div>
              <div className="text-2xl font-bold mt-2 text-amber-400">{metrics?.average_duration_ms || 0} ms</div>
              <div className="text-xs text-slate-500 mt-1">Sub-second low latency</div>
            </div>
          </div>

          {/* Monthly Quota Consumption */}
          <div className={`p-5 rounded-xl border ${isDark ? 'bg-slate-900/60 border-slate-800' : 'bg-white border-slate-200'}`}>
            <div className="flex items-center justify-between mb-2">
              <div>
                <h3 className="text-sm font-bold text-slate-200">Organization Monthly Automation Quota</h3>
                <p className="text-xs text-slate-400">Monthly execution budget allocated to this CRM workspace</p>
              </div>
              <span className="text-xs font-mono font-bold text-indigo-400">
                {metrics?.monthly_executions || 0} / {metrics?.monthly_limit || 50000} runs
              </span>
            </div>
            <div className="w-full bg-slate-800 rounded-full h-2.5 overflow-hidden">
              <div
                className="bg-indigo-600 h-2.5 rounded-full transition-all"
                style={{
                  width: `${Math.min(
                    100,
                    metrics?.monthly_limit ? ((metrics.monthly_executions || 0) / metrics.monthly_limit) * 100 : 0
                  )}%`,
                }}
              />
            </div>
          </div>

          {/* Engine Reliability Architecture Status */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className={`p-4 rounded-xl border ${isDark ? 'bg-slate-900/40 border-slate-800' : 'bg-white border-slate-200'}`}>
              <div className="flex items-center space-x-2 text-emerald-400 mb-1">
                <ShieldCheck className="w-4 h-4" />
                <span className="text-xs font-bold uppercase tracking-wider">Loop Protection Active</span>
              </div>
              <p className="text-xs text-slate-400">
                Recursion depth capped at 10. Entity run rate limited to $\le$ 10 runs per 15 minutes to prevent infinite loops.
              </p>
            </div>

            <div className={`p-4 rounded-xl border ${isDark ? 'bg-slate-900/40 border-slate-800' : 'bg-white border-slate-200'}`}>
              <div className="flex items-center space-x-2 text-sky-400 mb-1">
                <Zap className="w-4 h-4" />
                <span className="text-xs font-bold uppercase tracking-wider">Idempotency Guard</span>
              </div>
              <p className="text-xs text-slate-400">
                Webhook and external event hashes deduplicated automatically to prevent double-firing side effects.
              </p>
            </div>

            <div className={`p-4 rounded-xl border ${isDark ? 'bg-slate-900/40 border-slate-800' : 'bg-white border-slate-200'}`}>
              <div className="flex items-center space-x-2 text-purple-400 mb-1">
                <Clock className="w-4 h-4" />
                <span className="text-xs font-bold uppercase tracking-wider">Business Hours Scheduler</span>
              </div>
              <p className="text-xs text-slate-400">
                09:00 - 18:00 IST Monday through Saturday. Weekend and after-hours delays roll over to next morning 9 AM.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* -------------------------------------------------------------------- */}
      {/* MODAL: APPROVAL DECISION PROMPT */}
      {/* -------------------------------------------------------------------- */}
      {showApprovalModal && selectedApproval && (
        <div className="fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-50">
          <div className={`w-full max-w-md rounded-2xl border shadow-2xl p-6 ${isDark ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'}`}>
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center space-x-2">
                {approvalDecision === 'approve' ? (
                  <CheckCircle className="w-5 h-5 text-emerald-400" />
                ) : (
                  <AlertCircle className="w-5 h-5 text-red-400" />
                )}
                <h3 className="text-sm font-bold capitalize">{approvalDecision} Automation Request</h3>
              </div>
              <button onClick={() => setShowApprovalModal(false)} className="text-slate-400 hover:text-white">
                <X className="w-4 h-4" />
              </button>
            </div>

            <p className="text-xs text-slate-400 mb-3">
              {approvalDecision === 'approve'
                ? `You are approving "${selectedApproval.title}". The workflow will proceed along the approved path.`
                : `You are rejecting "${selectedApproval.title}". The workflow will halt or take the rejected branch.`}
            </p>

            <div className="space-y-2 mb-4">
              <label className="text-xs font-semibold text-slate-300">
                {approvalDecision === 'approve' ? 'Optional Manager Notes' : 'Rejection Reason (Required)'}
              </label>
              <textarea
                value={approvalComments}
                onChange={(e) => setApprovalComments(e.target.value)}
                placeholder={approvalDecision === 'approve' ? 'e.g. Approved per quarterly sales guideline' : 'e.g. Exceeds permissible discount ceiling'}
                rows={3}
                className={`w-full px-3 py-2 text-xs rounded-lg border outline-none ${
                  isDark ? 'bg-slate-950 border-slate-800 text-white' : 'bg-slate-50 border-slate-200 text-slate-900'
                }`}
              />
            </div>

            <div className="flex justify-end space-x-2">
              <button
                onClick={() => setShowApprovalModal(false)}
                className="px-3 py-1.5 text-xs rounded-lg border border-slate-700 text-slate-300 hover:bg-slate-800"
              >
                Cancel
              </button>
              <button
                onClick={async () => {
                  if (approvalDecision === 'reject' && !approvalComments.trim()) {
                    alert('Please enter a rejection reason.');
                    return;
                  }
                  await decideApprovalMut.mutateAsync({
                    id: selectedApproval.id,
                    decision: approvalDecision,
                    comments: approvalComments,
                  });
                  setShowApprovalModal(false);
                  refetchApprovals();
                }}
                disabled={decideApprovalMut.isPending}
                className={`px-4 py-1.5 text-xs font-semibold rounded-lg text-white ${
                  approvalDecision === 'approve'
                    ? 'bg-emerald-600 hover:bg-emerald-500'
                    : 'bg-red-600 hover:bg-red-500'
                }`}
              >
                {decideApprovalMut.isPending ? 'Processing...' : `Confirm ${approvalDecision === 'approve' ? 'Approval' : 'Rejection'}`}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* -------------------------------------------------------------------- */}
      {/* MODAL: TEMPLATES GALLERY */}
      {/* -------------------------------------------------------------------- */}
      {showTemplatesModal && (
        <div className="fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-50">
          <div className={`w-full max-w-3xl rounded-2xl border shadow-2xl flex flex-col max-h-[85vh] ${isDark ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'}`}>
            <div className="p-5 border-b flex items-center justify-between border-slate-800">
              <div className="flex items-center space-x-2">
                <Sparkles className="w-5 h-5 text-indigo-400" />
                <h3 className="text-base font-bold">Pre-built Automation Templates</h3>
              </div>
              <button onClick={() => setShowTemplatesModal(false)} className="text-slate-400 hover:text-white">
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
              {templates.map((tpl) => (
                <div
                  key={tpl.id}
                  className={`p-4 rounded-xl border flex flex-col justify-between ${
                    isDark ? 'bg-slate-950/60 border-slate-800' : 'bg-slate-50 border-slate-200'
                  }`}
                >
                  <div>
                    <div className="flex items-center justify-between mb-2">
                      <span className="text-[10px] font-mono px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 uppercase">
                        {tpl.category}
                      </span>
                      <span className="text-xs text-slate-400">Trigger: {tpl.trigger_type}</span>
                    </div>
                    <h4 className="font-semibold text-sm">{tpl.name}</h4>
                    <p className="text-xs text-slate-400 mt-1">{tpl.description}</p>
                  </div>

                  <button
                    onClick={async () => {
                      const imported = await importTemplateMut.mutateAsync({ templateId: tpl.id });
                      setShowTemplatesModal(false);
                      handleOpenCanvas(imported as any);
                    }}
                    disabled={importTemplateMut.isPending}
                    className="mt-4 w-full py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition-all"
                  >
                    Use This Template
                  </button>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* -------------------------------------------------------------------- */}
      {/* MODAL: DRY-RUN SIMULATOR */}
      {/* -------------------------------------------------------------------- */}
      {showSimulatorModal && (
        <div className="fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-50">
          <div className={`w-full max-w-4xl rounded-2xl border shadow-2xl flex flex-col max-h-[90vh] ${isDark ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'}`}>
            <div className="p-5 border-b flex items-center justify-between border-slate-800">
              <div className="flex items-center space-x-2">
                <Play className="w-5 h-5 text-emerald-400" />
                <h3 className="text-base font-bold">Dry-Run Test Simulation</h3>
              </div>
              <button onClick={() => setShowSimulatorModal(false)} className="text-slate-400 hover:text-white">
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="flex-1 overflow-hidden grid grid-cols-1 md:grid-cols-2 divide-x divide-slate-800">
              {/* Input Payload */}
              <div className="p-5 flex flex-col">
                <label className="text-xs font-semibold text-slate-400 mb-2">Sample Trigger Event JSON</label>
                <textarea
                  value={simulatorPayload}
                  onChange={(e) => setSimulatorPayload(e.target.value)}
                  className="flex-1 w-full font-mono text-xs p-3 rounded-lg bg-slate-950 border border-slate-800 text-emerald-400 outline-none resize-none"
                  rows={14}
                />
                <button
                  onClick={handleRunSimulation}
                  disabled={simulateWorkflowMut.isPending}
                  className="mt-3 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white flex items-center justify-center space-x-1.5 transition-all"
                >
                  <Play className="w-4 h-4" />
                  <span>{simulateWorkflowMut.isPending ? 'Simulating...' : 'Run Simulation Trace'}</span>
                </button>
              </div>

              {/* Simulation Result Trace */}
              <div className="p-5 flex flex-col overflow-y-auto">
                <label className="text-xs font-semibold text-slate-400 mb-2">Step Execution Trace</label>
                {simulationResult ? (
                  <div className="space-y-3">
                    <div className="flex items-center justify-between text-xs p-2 rounded bg-slate-950 border border-slate-800">
                      <span className="text-slate-400">Total Steps: {simulationResult.steps_executed}</span>
                      <span className="text-emerald-400 font-bold uppercase">{simulationResult.status}</span>
                    </div>

                    {simulationResult.trace.map((step, idx) => (
                      <div key={idx} className="p-3 rounded-lg border border-slate-800 bg-slate-950 text-xs space-y-1">
                        <div className="flex items-center justify-between">
                          <span className="font-semibold text-slate-200">
                            {idx + 1}. {step.node_name} ({step.node_type})
                          </span>
                          <span className="text-[10px] text-slate-400">{step.duration_ms}ms</span>
                        </div>
                        {step.branch_taken && (
                          <div className="text-[11px] text-amber-400">Branch taken: {step.branch_taken.toUpperCase()}</div>
                        )}
                        <pre className="text-[10px] text-slate-400 overflow-x-auto bg-slate-900 p-1.5 rounded">
                          {JSON.stringify(step.output, null, 2)}
                        </pre>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="text-center py-16 text-slate-500 text-xs">
                    Press "Run Simulation Trace" to see step-by-step path traversal without modifying database records.
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* -------------------------------------------------------------------- */}
      {/* DRAWER: RUN STEP LOGS DETAILS */}
      {/* -------------------------------------------------------------------- */}
      {selectedRunId && activeRun && (
        <div className="fixed inset-0 bg-black/60 flex justify-end z-50">
          <div className={`w-full max-w-xl h-full border-l shadow-2xl flex flex-col ${isDark ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'}`}>
            <div className="p-5 border-b flex items-center justify-between border-slate-800">
              <div>
                <h3 className="font-bold text-sm">Execution Run #{activeRun.id}</h3>
                <p className="text-xs text-slate-400">{activeRun.workflow?.name} • {activeRun.trigger_event}</p>
              </div>
              <button onClick={() => setSelectedRunId(null)} className="text-slate-400 hover:text-white">
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto p-5 space-y-4">
              <div className="grid grid-cols-2 gap-3 text-xs">
                <div className="p-3 rounded-lg bg-slate-950 border border-slate-800">
                  <div className="text-slate-400">Status</div>
                  <div className="font-bold text-emerald-400 uppercase mt-0.5">{activeRun.status}</div>
                </div>
                <div className="p-3 rounded-lg bg-slate-950 border border-slate-800">
                  <div className="text-slate-400">Idempotency Key</div>
                  <div className="font-mono text-slate-300 truncate mt-0.5">{activeRun.idempotency_key || 'None'}</div>
                </div>
              </div>

              {activeRun.error_message && (
                <div className="p-3 rounded-lg bg-red-500/10 border border-red-500/20 text-xs text-red-300">
                  <strong>Error:</strong> {activeRun.error_message}
                </div>
              )}

              <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400">Step Execution Logs</h4>

              <div className="space-y-3">
                {(activeRun.step_logs || []).map((step) => (
                  <div key={step.id} className="p-3.5 rounded-xl border border-slate-800 bg-slate-950 text-xs space-y-2">
                    <div className="flex items-center justify-between">
                      <div className="font-semibold text-slate-200">
                        {step.node_name} <span className="text-slate-500">({step.node_type})</span>
                      </div>
                      <span className="text-[10px] text-slate-400">{step.execution_duration_ms}ms</span>
                    </div>

                    {step.branch_taken && (
                      <div className="text-[11px] text-amber-400">Branch taken: {step.branch_taken.toUpperCase()}</div>
                    )}

                    {step.output_state && (
                      <div>
                        <div className="text-[10px] text-slate-500 mb-1">Output State:</div>
                        <pre className="text-[10px] text-slate-300 bg-slate-900/80 p-2 rounded overflow-x-auto">
                          {JSON.stringify(step.output_state, null, 2)}
                        </pre>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* -------------------------------------------------------------------- */}
      {/* MODAL: CREATE WORKFLOW */}
      {/* -------------------------------------------------------------------- */}
      {showNewWorkflowModal && (
        <div className="fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-50">
          <div className={`w-full max-w-md rounded-2xl border shadow-2xl p-6 ${isDark ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'}`}>
            <h3 className="text-base font-bold mb-4">Create New Workflow</h3>
            <div className="space-y-3">
              <div>
                <label className="text-xs font-semibold text-slate-400">Workflow Name</label>
                <input
                  type="text"
                  placeholder="e.g. Lead Qualification & Assignment"
                  value={newWorkflowForm.name}
                  onChange={(e) => setNewWorkflowForm({ ...newWorkflowForm, name: e.target.value })}
                  className="w-full mt-1 px-3 py-1.5 text-xs rounded-lg bg-slate-950 border border-slate-800 text-white outline-none"
                />
              </div>

              <div>
                <label className="text-xs font-semibold text-slate-400">Category</label>
                <select
                  value={newWorkflowForm.category}
                  onChange={(e) => setNewWorkflowForm({ ...newWorkflowForm, category: e.target.value as any })}
                  className="w-full mt-1 px-3 py-1.5 text-xs rounded-lg bg-slate-950 border border-slate-800 text-white outline-none"
                >
                  <option value="crm">CRM</option>
                  <option value="sales">Sales Pipeline</option>
                  <option value="marketing">Marketing & Messaging</option>
                  <option value="billing">Billing & Finance</option>
                  <option value="support">Customer Support</option>
                </select>
              </div>

              <div>
                <label className="text-xs font-semibold text-slate-400">Initial Trigger Event</label>
                <select
                  value={newWorkflowForm.trigger_type}
                  onChange={(e) => setNewWorkflowForm({ ...newWorkflowForm, trigger_type: e.target.value })}
                  className="w-full mt-1 px-3 py-1.5 text-xs rounded-lg bg-slate-950 border border-slate-800 text-white outline-none"
                >
                  {TRIGGER_OPTIONS.map((t) => (
                    <option key={t.value} value={t.value}>
                      [{t.category.toUpperCase()}] {t.label}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="text-xs font-semibold text-slate-400">Description</label>
                <textarea
                  rows={3}
                  placeholder="Briefly describe what this workflow accomplishes..."
                  value={newWorkflowForm.description}
                  onChange={(e) => setNewWorkflowForm({ ...newWorkflowForm, description: e.target.value })}
                  className="w-full mt-1 px-3 py-1.5 text-xs rounded-lg bg-slate-950 border border-slate-800 text-white outline-none"
                />
              </div>
            </div>

            <div className="flex justify-end space-x-3 mt-6">
              <button
                onClick={() => setShowNewWorkflowModal(false)}
                className="px-3.5 py-1.5 text-xs rounded-lg text-slate-400 hover:text-white"
              >
                Cancel
              </button>
              <button
                onClick={async () => {
                  if (!newWorkflowForm.name.trim()) {
                    alert('Please provide a workflow name.');
                    return;
                  }
                  const created = await createWorkflowMut.mutateAsync({
                    name: newWorkflowForm.name,
                    description: newWorkflowForm.description,
                    category: newWorkflowForm.category,
                    trigger_type: newWorkflowForm.trigger_type,
                    definition: {
                      nodes: [
                        {
                          id: 'node_trig',
                          type: 'trigger',
                          position: { x: 250, y: 50 },
                          data: { label: 'Start: ' + newWorkflowForm.trigger_type },
                        },
                      ],
                      edges: [],
                    },
                  });
                  setShowNewWorkflowModal(false);
                  handleOpenCanvas(created as any);
                }}
                disabled={createWorkflowMut.isPending}
                className="px-4 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white"
              >
                Create & Open Builder
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
