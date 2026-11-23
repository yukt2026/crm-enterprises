<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Workflow\Models\Workflow;
use App\Domains\Workflow\Models\WorkflowTemplate;
use App\Domains\Workflow\Models\WorkflowVersion;
use App\Domains\Workflow\Services\WorkflowExecutionEngine;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function __construct(
        protected WorkflowExecutionEngine $engine
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $query = Workflow::where('tenant_id', $tenantId)
            ->with(['creator:id,name', 'currentVersionModel:id,workflow_id,version,definition,is_published'])
            ->latest();

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('trigger_type')) {
            $query->where('trigger_type', $request->trigger_type);
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%")
                    ->orWhere('trigger_type', 'like', "%{$s}%");
            });
        }

        $workflows = $query->paginate((int)$request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $workflows->items(),
            'meta' => [
                'current_page' => $workflows->currentPage(),
                'last_page' => $workflows->lastPage(),
                'total' => $workflows->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'category' => 'required|string|in:crm,sales,marketing,hr,billing,support',
            'trigger_type' => 'required|string|max:80',
            'definition' => 'nullable|array',
        ]);

        $workflow = Workflow::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'trigger_type' => $validated['trigger_type'],
            'status' => 'draft',
            'current_version' => 1,
            'is_active' => false,
            'created_by_user_id' => $request->user()->id,
        ]);

        $defaultDefinition = $validated['definition'] ?? [
            'nodes' => [
                ['id' => 'node_trigger', 'type' => 'trigger', 'position' => ['x' => 250, 'y' => 50], 'data' => ['label' => 'When ' . $validated['trigger_type']]],
            ],
            'edges' => [],
        ];

        $version = WorkflowVersion::create([
            'tenant_id' => $tenantId,
            'workflow_id' => $workflow->id,
            'version' => 1,
            'definition' => $defaultDefinition,
            'changelog' => 'Initial version',
            'is_published' => false,
            'created_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $workflow->load('currentVersionModel'),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $workflow = Workflow::where('tenant_id', $tenantId)
            ->with(['versions', 'currentVersionModel', 'creator:id,name'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $workflow,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $workflow = Workflow::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'category' => 'sometimes|string',
            'trigger_type' => 'sometimes|string',
            'definition' => 'nullable|array',
            'changelog' => 'nullable|string',
        ]);

        $workflow->update(array_intersect_key($validated, array_flip(['name', 'description', 'category', 'trigger_type'])));

        // If definition provided, save as version
        if (isset($validated['definition'])) {
            $nextVersion = $workflow->current_version + 1;
            WorkflowVersion::create([
                'tenant_id' => $tenantId,
                'workflow_id' => $workflow->id,
                'version' => $nextVersion,
                'definition' => $validated['definition'],
                'changelog' => $validated['changelog'] ?? "Version {$nextVersion} update",
                'is_published' => $workflow->status === 'published',
                'created_by_user_id' => $request->user()->id,
            ]);

            $workflow->update(['current_version' => $nextVersion]);
        }

        return response()->json([
            'success' => true,
            'data' => $workflow->fresh(['currentVersionModel', 'versions']),
        ]);
    }

    public function publish(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $workflow = Workflow::where('tenant_id', $tenantId)->findOrFail($id);

        $workflow->update([
            'status' => 'published',
            'is_active' => true,
        ]);

        WorkflowVersion::where('workflow_id', $workflow->id)
            ->where('version', $workflow->current_version)
            ->update(['is_published' => true]);

        return response()->json([
            'success' => true,
            'data' => $workflow,
            'message' => "Workflow [{$workflow->name}] published and active.",
        ]);
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $workflow = Workflow::where('tenant_id', $tenantId)->findOrFail($id);

        $workflow->update([
            'status' => 'paused',
            'is_active' => false,
        ]);

        return response()->json([
            'success' => true,
            'data' => $workflow,
            'message' => "Workflow [{$workflow->name}] paused.",
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $workflow = Workflow::where('tenant_id', $tenantId)->findOrFail($id);
        $workflow->delete();

        return response()->json([
            'success' => true,
            'message' => 'Workflow removed.',
        ]);
    }

    public function testSimulation(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $workflow = Workflow::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'definition' => 'nullable|array',
            'sample_payload' => 'required|array',
        ]);

        $definition = $validated['definition'] ?? ($workflow->currentVersionModel?->definition ?? []);

        $result = $this->engine->simulate($workflow, $definition, $validated['sample_payload']);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function templates(): JsonResponse
    {
        $templates = WorkflowTemplate::all();

        // If no templates seeded in database, provide standard templates
        if ($templates->isEmpty()) {
            $templates = collect([
                [
                    'id' => 1,
                    'name' => 'High-Score Lead Fast Track',
                    'category' => 'crm',
                    'description' => 'Automatically qualifies leads with score >= 75, notifies sales manager, and schedules follow-up task.',
                    'trigger_type' => 'lead.created',
                    'definition' => [
                        'nodes' => [
                            ['id' => 'n_trig', 'type' => 'trigger', 'position' => ['x' => 200, 'y' => 50], 'data' => ['label' => 'New Lead Ingested']],
                            ['id' => 'n_cond', 'type' => 'condition', 'position' => ['x' => 200, 'y' => 160], 'data' => [
                                'label' => 'Score >= 75?',
                                'conditions' => [
                                    'combinator' => 'and',
                                    'rules' => [
                                        ['field' => 'lead.score', 'operator' => 'greater_or_equal', 'value' => 75],
                                    ],
                                ],
                            ]],
                            ['id' => 'n_status', 'type' => 'action', 'position' => ['x' => 80, 'y' => 280], 'data' => [
                                'label' => 'Upgrade to Qualified',
                                'action_type' => 'crm.update_lead_status',
                                'params' => ['status' => 'qualified'],
                            ]],
                            ['id' => 'n_task', 'type' => 'action', 'position' => ['x' => 80, 'y' => 390], 'data' => [
                                'label' => 'Urgent VIP Callback Task',
                                'action_type' => 'crm.create_task',
                                'params' => ['title' => 'VIP 15-min Discovery Call', 'due_in_hours' => 2, 'priority' => 'urgent'],
                            ]],
                            ['id' => 'n_nurture', 'type' => 'action', 'position' => ['x' => 320, 'y' => 280], 'data' => [
                                'label' => 'Add Nurture Tag',
                                'action_type' => 'crm.add_tags',
                                'params' => ['tags' => ['cold_nurture']],
                            ]],
                        ],
                        'edges' => [
                            ['id' => 'e1', 'source' => 'n_trig', 'target' => 'n_cond'],
                            ['id' => 'e2', 'source' => 'n_cond', 'target' => 'n_status', 'sourceHandle' => 'true', 'label' => 'true'],
                            ['id' => 'e3', 'source' => 'n_status', 'target' => 'n_task'],
                            ['id' => 'e4', 'source' => 'n_cond', 'target' => 'n_nurture', 'sourceHandle' => 'false', 'label' => 'false'],
                        ],
                    ],
                ],
                [
                    'id' => 2,
                    'name' => 'Overdue Invoice Reminder & WhatsApp Notice',
                    'category' => 'billing',
                    'description' => 'Checks if customer is not suppressed, waits 2 days, and dispatches automated reminder.',
                    'trigger_type' => 'invoice.overdue',
                    'definition' => [
                        'nodes' => [
                            ['id' => 'n_trig', 'type' => 'trigger', 'position' => ['x' => 200, 'y' => 50], 'data' => ['label' => 'Invoice Overdue']],
                            ['id' => 'n_delay', 'type' => 'delay', 'position' => ['x' => 200, 'y' => 160], 'data' => ['label' => 'Wait 24 Hours', 'duration' => 24, 'unit' => 'hours']],
                            ['id' => 'n_email', 'type' => 'action', 'position' => ['x' => 200, 'y' => 270], 'data' => [
                                'label' => 'Send Dunning Email',
                                'action_type' => 'communication.send_email',
                                'params' => ['subject' => 'Payment Reminder: Invoice Overdue', 'content' => 'Kindly clear your outstanding balance.'],
                            ]],
                        ],
                        'edges' => [
                            ['id' => 'e1', 'source' => 'n_trig', 'target' => 'n_delay'],
                            ['id' => 'e2', 'source' => 'n_delay', 'target' => 'n_email'],
                        ],
                    ],
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    public function importTemplate(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'template_name' => 'required|string',
            'category' => 'required|string',
            'trigger_type' => 'required|string',
            'definition' => 'required|array',
        ]);

        $workflow = Workflow::create([
            'tenant_id' => $tenantId,
            'name' => $validated['template_name'],
            'description' => 'Imported from visual workflow template',
            'category' => $validated['category'],
            'trigger_type' => $validated['trigger_type'],
            'status' => 'draft',
            'current_version' => 1,
            'is_active' => false,
            'created_by_user_id' => $request->user()->id,
        ]);

        WorkflowVersion::create([
            'tenant_id' => $tenantId,
            'workflow_id' => $workflow->id,
            'version' => 1,
            'definition' => $validated['definition'],
            'changelog' => 'Imported template',
            'is_published' => false,
            'created_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $workflow->load('currentVersionModel'),
            'message' => 'Workflow template imported successfully.',
        ], 201);
    }
}
