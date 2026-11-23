<?php

namespace App\Domains\WhatsApp\AI\Tools;

use App\Domains\CRM\Models\Contact;
use App\Domains\Inventory\Models\Product;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Projects\Models\Task;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Domains\WhatsApp\Models\WhatsAppConversationNote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CrmToolRegistry
{
    /**
     * Get tool definitions schema for LLM function calling.
     */
    public function getAvailableToolsSchema(array $allowedToolNames = []): array
    {
        $allTools = [
            [
                'name' => 'find_or_create_lead',
                'description' => 'Find existing lead or create a new CRM lead from WhatsApp conversation.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Customer full name'],
                        'requirement' => ['type' => 'string', 'description' => 'Product/service interest or requirement'],
                        'budget' => ['type' => 'number', 'description' => 'Estimated budget amount'],
                        'timeline' => ['type' => 'string', 'description' => 'Purchase timeline (e.g. immediate, 1 month)'],
                        'location' => ['type' => 'string', 'description' => 'City or location of customer'],
                        'notes' => ['type' => 'string', 'description' => 'Initial inquiry notes'],
                    ],
                    'required' => ['requirement']
                ]
            ],
            [
                'name' => 'update_lead_fields',
                'description' => 'Update authorized qualification fields on the linked CRM lead.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'stage' => ['type' => 'string', 'description' => 'Lifecycle stage (new, contacted, qualified, proposal, negotiation)'],
                        'budget' => ['type' => 'number', 'description' => 'Updated budget'],
                        'requirement' => ['type' => 'string', 'description' => 'Specific requirement details'],
                        'qualification_status' => ['type' => 'string', 'description' => 'qualified or unqualified'],
                        'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Tags to attach'],
                    ],
                ]
            ],
            [
                'name' => 'add_crm_note',
                'description' => 'Add an internal CRM note or interaction summary for sales team visibility.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'content' => ['type' => 'string', 'description' => 'Note text summarizing customer need or sentiment'],
                    ],
                    'required' => ['content']
                ]
            ],
            [
                'name' => 'create_follow_up_task',
                'description' => 'Schedule a follow-up task or reminder for sales representative.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Task subject/title'],
                        'due_date' => ['type' => 'string', 'description' => 'Due date (YYYY-MM-DD)'],
                        'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent']],
                        'description' => ['type' => 'string', 'description' => 'Task details and context'],
                    ],
                    'required' => ['title']
                ]
            ],
            [
                'name' => 'assign_lead',
                'description' => 'Assign the linked CRM lead to a specific user or team.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'user_id' => ['type' => 'integer', 'description' => 'Target user ID'],
                        'reason' => ['type' => 'string', 'description' => 'Assignment justification'],
                    ],
                    'required' => ['user_id']
                ]
            ],
            [
                'name' => 'get_product_catalog',
                'description' => 'Look up approved products, pricing, and services from catalog.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Search term or SKU'],
                        'category' => ['type' => 'string', 'description' => 'Category filter'],
                    ],
                ]
            ],
            [
                'name' => 'request_appointment',
                'description' => 'Record customer requested meeting or appointment time.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'preferred_date' => ['type' => 'string', 'description' => 'Preferred date/time'],
                        'meeting_topic' => ['type' => 'string', 'description' => 'Agenda or topic'],
                    ],
                    'required' => ['preferred_date']
                ]
            ],
            [
                'name' => 'escalate_to_human',
                'description' => 'Trigger immediate handover from AI to human agent.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'reason' => ['type' => 'string', 'description' => 'Reason for escalation'],
                        'priority' => ['type' => 'string', 'enum' => ['normal', 'urgent']],
                    ],
                    'required' => ['reason']
                ]
            ],
        ];

        if (empty($allowedToolNames)) {
            return $allTools;
        }

        return array_values(array_filter($allTools, function ($tool) use ($allowedToolNames) {
            return in_array($tool['name'], $allowedToolNames);
        }));
    }

    /**
     * Execute a specific tool safely under tenant isolation and permission boundaries.
     */
    public function executeTool(
        string $toolName,
        array $arguments,
        WhatsAppConversation $conversation,
        array $allowedTools = []
    ): array {
        // Enforce allowed tools policy
        if (!empty($allowedTools) && !in_array($toolName, $allowedTools)) {
            return [
                'success' => false,
                'error' => "Action [{$toolName}] is not permitted for this AI Agent configuration.",
            ];
        }

        // Security Barrier: Reject sensitive unauthorized actions
        if (in_array($toolName, ['issue_refund', 'give_discount', 'delete_lead', 'execute_sql'])) {
            return [
                'success' => false,
                'error' => "Security Policy Violation: Sensitive action [{$toolName}] requires human manager authorization.",
            ];
        }

        $tenantId = $conversation->tenant_id;

        try {
            switch ($toolName) {
                case 'find_or_create_lead':
                    return $this->executeFindOrCreateLead($arguments, $conversation, $tenantId);

                case 'update_lead_fields':
                    return $this->executeUpdateLeadFields($arguments, $conversation, $tenantId);

                case 'add_crm_note':
                    return $this->executeAddCrmNote($arguments, $conversation, $tenantId);

                case 'create_follow_up_task':
                    return $this->executeCreateFollowUpTask($arguments, $conversation, $tenantId);

                case 'assign_lead':
                    return $this->executeAssignLead($arguments, $conversation, $tenantId);

                case 'get_product_catalog':
                    return $this->executeGetProductCatalog($arguments, $tenantId);

                case 'request_appointment':
                    return $this->executeRequestAppointment($arguments, $conversation, $tenantId);

                case 'escalate_to_human':
                    return $this->executeEscalateToHuman($arguments, $conversation);

                default:
                    return [
                        'success' => false,
                        'error' => "Unknown tool: {$toolName}",
                    ];
            }
        } catch (\Exception $e) {
            Log::error("CRM Tool execution error [{$toolName}]: " . $e->getMessage());
            return [
                'success' => false,
                'error' => "Execution failed: " . $e->getMessage(),
            ];
        }
    }

    protected function executeFindOrCreateLead(array $args, WhatsAppConversation $conv, int $tenantId): array
    {
        $phone = $conv->phone_number;
        $name = $args['name'] ?? ($conv->customer_name ?: 'WhatsApp Prospect ' . substr($phone, -4));

        $lead = null;
        if ($conv->lead_id) {
            $lead = Lead::where('tenant_id', $tenantId)->find($conv->lead_id);
        }

        if (!$lead) {
            $lead = Lead::where('tenant_id', $tenantId)
                ->where(function ($q) use ($phone) {
                    $q->where('phone', $phone)->orWhere('alternate_phone', $phone);
                })
                ->first();
        }

        if (!$lead) {
            $lead = Lead::create([
                'tenant_id' => $tenantId,
                'name' => $name,
                'phone' => $phone,
                'status' => 'new',
                'source' => 'whatsapp',
                'requirement' => $args['requirement'] ?? 'Inquired via WhatsApp AI',
                'estimated_budget' => $args['budget'] ?? 0,
                'city' => $args['location'] ?? null,
                'notes' => $args['notes'] ?? 'Auto-created by WhatsApp AI Agent',
                'score' => 50,
            ]);

            $conv->lead_id = $lead->id;
            $conv->save();
        } else {
            // Update existing lead requirement if newly captured
            if (!empty($args['requirement'])) {
                $lead->requirement = $args['requirement'];
            }
            if (!empty($args['budget'])) {
                $lead->estimated_budget = $args['budget'];
            }
            $lead->save();
        }

        return [
            'success' => true,
            'lead_id' => $lead->id,
            'lead_name' => $lead->name,
            'status' => $lead->status,
            'action' => 'lead_captured',
        ];
    }

    protected function executeUpdateLeadFields(array $args, WhatsAppConversation $conv, int $tenantId): array
    {
        if (!$conv->lead_id) {
            return ['success' => false, 'error' => 'No CRM lead linked to this conversation.'];
        }

        $lead = Lead::where('tenant_id', $tenantId)->find($conv->lead_id);
        if (!$lead) {
            return ['success' => false, 'error' => 'Linked lead not found in tenant.'];
        }

        if (!empty($args['stage'])) {
            $lead->status = $args['stage'];
        }
        if (!empty($args['budget'])) {
            $lead->estimated_budget = $args['budget'];
        }
        if (!empty($args['requirement'])) {
            $lead->requirement = $args['requirement'];
        }
        if (!empty($args['qualification_status'])) {
            $lead->score = ($args['qualification_status'] === 'qualified') ? 80 : 30;
        }

        $lead->save();

        return [
            'success' => true,
            'lead_id' => $lead->id,
            'updated_fields' => array_keys($args),
        ];
    }

    protected function executeAddCrmNote(array $args, WhatsAppConversation $conv, int $tenantId): array
    {
        $userId = $conv->assigned_user_id ?: \App\Models\User::where('current_tenant_id', $tenantId)->value('id') ?: 1;

        WhatsAppConversationNote::create([
            'tenant_id' => $tenantId,
            'conversation_id' => $conv->id,
            'user_id' => $userId,
            'content' => "[AI Auto-Note] " . ($args['content'] ?? ''),
        ]);

        return [
            'success' => true,
            'action' => 'note_added',
        ];
    }

    protected function executeCreateFollowUpTask(array $args, WhatsAppConversation $conv, int $tenantId): array
    {
        $dueDate = !empty($args['due_date']) ? Carbon::parse($args['due_date']) : Carbon::now()->addDay();

        $task = Task::create([
            'tenant_id' => $tenantId,
            'title' => $args['title'] ?? 'Follow up with WhatsApp Customer',
            'description' => $args['description'] ?? "Follow up regarding WhatsApp conversation #{$conv->id}",
            'due_date' => $dueDate,
            'priority' => $args['priority'] ?? 'high',
            'status' => 'todo',
            'lead_id' => $conv->lead_id,
            'assigned_user_id' => $conv->assigned_user_id,
            'created_by' => $conv->assigned_user_id ?: 1,
        ]);

        return [
            'success' => true,
            'task_id' => $task->id,
            'task_title' => $task->title,
            'due_date' => $task->due_date->toIso8601String(),
        ];
    }

    protected function executeAssignLead(array $args, WhatsAppConversation $conv, int $tenantId): array
    {
        $userId = $args['user_id'] ?? null;
        if ($userId) {
            $conv->assigned_user_id = $userId;
            $conv->save();

            if ($conv->lead_id) {
                Lead::where('tenant_id', $tenantId)
                    ->where('id', $conv->lead_id)
                    ->update(['assigned_user_id' => $userId]);
            }
        }

        return [
            'success' => true,
            'assigned_user_id' => $userId,
            'reason' => $args['reason'] ?? 'Assigned by AI Agent',
        ];
    }

    protected function executeGetProductCatalog(array $args, int $tenantId): array
    {
        $query = Product::where('tenant_id', $tenantId)->where('is_active', true);

        if (!empty($args['category'])) {
            $query->where('category', 'like', "%{$args['category']}%");
        }
        if (!empty($args['query'])) {
            $term = $args['query'];
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('sku', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%");
            });
        }

        $products = $query->limit(5)->get(['id', 'name', 'sku', 'sale_price', 'category', 'description']);

        return [
            'success' => true,
            'count' => $products->count(),
            'products' => $products->toArray(),
        ];
    }

    protected function executeRequestAppointment(array $args, WhatsAppConversation $conv, int $tenantId): array
    {
        $topic = $args['meeting_topic'] ?? 'WhatsApp Consultation Meeting';
        $timeStr = $args['preferred_date'] ?? 'Tomorrow 11:00 AM';
        $userId = $conv->assigned_user_id ?: \App\Models\User::where('current_tenant_id', $tenantId)->value('id') ?: 1;

        WhatsAppConversationNote::create([
            'tenant_id' => $tenantId,
            'conversation_id' => $conv->id,
            'user_id' => $userId,
            'content' => "[Meeting Request] Customer requested an appointment: {$timeStr} (Topic: {$topic})",
        ]);

        return [
            'success' => true,
            'status' => 'appointment_logged',
            'preferred_time' => $timeStr,
            'topic' => $topic,
        ];
    }

    protected function executeEscalateToHuman(array $args, WhatsAppConversation $conv): array
    {
        $conv->is_ai_handled = false;
        $conv->priority = ($args['priority'] ?? 'normal') === 'urgent' ? 'urgent' : 'high';
        $conv->save();

        $userId = $conv->assigned_user_id ?: \App\Models\User::where('current_tenant_id', $conv->tenant_id)->value('id') ?: 1;

        WhatsAppConversationNote::create([
            'tenant_id' => $conv->tenant_id,
            'conversation_id' => $conv->id,
            'user_id' => $userId,
            'content' => "[AI Handover Triggered] Reason: " . ($args['reason'] ?? 'Customer requested human assistance.'),
        ]);

        return [
            'success' => true,
            'status' => 'handover_completed',
            'reason' => $args['reason'] ?? 'Human specialist requested',
        ];
    }
}
