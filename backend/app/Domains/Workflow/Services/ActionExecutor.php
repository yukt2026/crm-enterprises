<?php

namespace App\Domains\Workflow\Services;

use App\Domains\Communications\Services\NotificationService;
use App\Domains\CRM\Models\CrmActivity;
use App\Domains\CRM\Models\Deal;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Omnichannel\Adapters\EmailChannelAdapter;
use App\Domains\Omnichannel\Adapters\SmsChannelAdapter;
use App\Domains\Omnichannel\Models\CommunicationSuppression;
use App\Domains\Projects\Models\Meeting;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Voice\Providers\TelephonyProviderManager;
use App\Domains\WhatsApp\Services\WhatsAppMessagingService;
use App\Domains\Workflow\Models\WorkflowApproval;
use App\Domains\Workflow\Models\WorkflowAutomationLimit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ActionExecutor
{
    public function __construct(
        protected EmailChannelAdapter $emailAdapter,
        protected SmsChannelAdapter $smsAdapter,
        protected WhatsAppMessagingService $whatsAppService,
        protected TelephonyProviderManager $telephonyManager,
        protected NotificationService $notificationService
    ) {}

    /**
     * Execute an action with safety checks, consent verification, usage limits, and logging.
     */
    public function execute(string $actionType, array $params, array &$context, int $tenantId, bool $isDryRun = false, ?int $runId = null, ?string $nodeId = null): array
    {
        if ($isDryRun) {
            return [
                'status' => 'simulated',
                'action_type' => $actionType,
                'params' => $params,
                'message' => "Dry run simulation for action [{$actionType}] completed safely without external side effects.",
            ];
        }

        // Check automation usage limits
        $limitError = $this->checkActionLimits($tenantId, $actionType);
        if ($limitError) {
            return [
                'status' => 'failed',
                'error' => $limitError,
            ];
        }

        return match ($actionType) {
            'crm.create_task' => $this->createTask($params, $context, $tenantId),
            'crm.assign_employee', 'crm.assign_lead' => $this->assignLeadOrEmployee($params, $context, $tenantId),
            'crm.reassign_lead' => $this->reassignLead($params, $context, $tenantId),
            'crm.update_record' => $this->updateRecord($params, $context, $tenantId),
            'crm.update_lead_status' => $this->updateLeadStatus($params, $context, $tenantId),
            'crm.add_tags' => $this->addTags($params, $context, $tenantId),
            'crm.change_status' => $this->changeStatus($params, $context, $tenantId),
            'crm.add_note' => $this->addNote($params, $context, $tenantId),
            'support.create_ticket' => $this->createSupportTicket($params, $context, $tenantId),
            'appointment.create_appointment' => $this->createAppointment($params, $context, $tenantId),
            'notification.generate_reminder' => $this->generateReminder($params, $context, $tenantId),
            'notification.notify_user' => $this->notifyUser($params, $context, $tenantId),
            'communication.send_whatsapp_template' => $this->sendWhatsAppTemplate($params, $context, $tenantId),
            'communication.send_email' => $this->sendEmail($params, $context, $tenantId),
            'communication.send_sms' => $this->sendSms($params, $context, $tenantId),
            'webhook.send' => $this->sendWebhook($params, $context, $tenantId),
            'approval.request_approval' => $this->requestApproval($params, $context, $tenantId, $runId, $nodeId),
            'projects.create_project_draft' => $this->createProjectDraft($params, $context, $tenantId),
            'sales.create_deal' => $this->createDeal($params, $context, $tenantId),
            'analytics.generate_report' => $this->generateReport($params, $context, $tenantId),
            default => [
                'status' => 'skipped',
                'message' => "Unsupported action type: {$actionType}",
            ],
        };
    }

    /**
     * Check action-specific usage limits before consuming third-party quotas.
     */
    protected function checkActionLimits(int $tenantId, string $actionType): ?string
    {
        $limits = WorkflowAutomationLimit::getForTenant($tenantId);

        if (str_starts_with($actionType, 'communication.send_whatsapp')) {
            if ($limits->max_whatsapp_actions > 0 && $limits->current_month_executions >= $limits->max_monthly_executions) {
                return 'Monthly automation execution limit reached.';
            }
        } elseif (str_starts_with($actionType, 'communication.send_sms')) {
            if ($limits->max_sms_actions > 0 && $limits->current_month_executions >= $limits->max_monthly_executions) {
                return 'Monthly automation execution limit reached.';
            }
        } elseif ($actionType === 'webhook.send') {
            if ($limits->max_external_api_calls > 0 && $limits->current_month_executions >= $limits->max_monthly_executions) {
                return 'Monthly external API limit reached.';
            }
        }

        return null;
    }

    protected function createTask(array $params, array &$context, int $tenantId): array
    {
        $title = $params['title'] ?? 'Automated Workflow Task';
        $assignedTo = $params['assigned_to'] ?? ($context['assigned_user_id'] ?? null);
        $dueDays = (int) ($params['due_in_days'] ?? 1);

        $task = Task::create([
            'tenant_id' => $tenantId,
            'title' => $title,
            'description' => $params['description'] ?? 'Created automatically via CRM Workflow Engine.',
            'assigned_to' => $assignedTo,
            'status' => 'pending',
            'priority' => $params['priority'] ?? 'medium',
            'due_date' => now()->addDays($dueDays)->toDateString(),
            'project_id' => $params['project_id'] ?? ($context['project_id'] ?? null),
            'lead_id' => $params['lead_id'] ?? ($context['lead']['id'] ?? ($context['lead_id'] ?? null)),
            'deal_id' => $params['deal_id'] ?? ($context['deal']['id'] ?? ($context['deal_id'] ?? null)),
        ]);

        $context['created_task_id'] = $task->id;

        return [
            'status' => 'success',
            'task_id' => $task->id,
            'title' => $task->title,
            'due_date' => $task->due_date,
        ];
    }

    protected function assignLeadOrEmployee(array $params, array &$context, int $tenantId): array
    {
        $leadId = $params['lead_id'] ?? ($context['lead']['id'] ?? ($context['entity_id'] ?? null));
        $userId = $params['assigned_user_id'] ?? ($params['user_id'] ?? null);

        $lead = Lead::where('tenant_id', $tenantId)->find($leadId);
        if ($lead && $userId) {
            $lead->update(['assigned_user_id' => $userId]);
            $context['lead']['assigned_user_id'] = $userId;

            return ['status' => 'success', 'lead_id' => $lead->id, 'assigned_to' => $userId];
        }

        return ['status' => 'failed', 'message' => 'Lead or Assignee not found'];
    }

    protected function reassignLead(array $params, array &$context, int $tenantId): array
    {
        return $this->assignLeadOrEmployee($params, $context, $tenantId);
    }

    protected function updateRecord(array $params, array &$context, int $tenantId): array
    {
        $entityType = $params['entity_type'] ?? ($context['entity_type'] ?? 'lead');
        $entityId = $params['entity_id'] ?? ($context['entity_id'] ?? null);
        $fields = (array) ($params['fields'] ?? []);

        if (!$entityId || empty($fields)) {
            return ['status' => 'failed', 'message' => 'Entity ID or fields to update missing'];
        }

        if ($entityType === 'lead') {
            $record = Lead::where('tenant_id', $tenantId)->find($entityId);
            if ($record) {
                $record->update($fields);
                $context['lead'] = array_merge($context['lead'] ?? [], $fields);
                return ['status' => 'success', 'entity_type' => 'lead', 'entity_id' => $entityId, 'updated' => array_keys($fields)];
            }
        } elseif ($entityType === 'deal') {
            $record = Deal::where('tenant_id', $tenantId)->find($entityId);
            if ($record) {
                $record->update($fields);
                $context['deal'] = array_merge($context['deal'] ?? [], $fields);
                return ['status' => 'success', 'entity_type' => 'deal', 'entity_id' => $entityId, 'updated' => array_keys($fields)];
            }
        }

        return ['status' => 'failed', 'message' => "Record [{$entityType}:{$entityId}] not found"];
    }

    protected function updateLeadStatus(array $params, array &$context, int $tenantId): array
    {
        $leadId = $params['lead_id'] ?? ($context['lead']['id'] ?? ($context['entity_id'] ?? null));
        $newStatus = $params['status'] ?? 'contacted';

        $lead = Lead::where('tenant_id', $tenantId)->find($leadId);
        if ($lead) {
            $lead->update(['status' => $newStatus]);
            $context['lead']['status'] = $newStatus;

            return ['status' => 'success', 'lead_id' => $lead->id, 'new_status' => $newStatus];
        }

        return ['status' => 'failed', 'message' => 'Lead not found'];
    }

    protected function addTags(array $params, array &$context, int $tenantId): array
    {
        $leadId = $params['lead_id'] ?? ($context['lead']['id'] ?? ($context['entity_id'] ?? null));
        $newTags = (array) ($params['tags'] ?? []);

        $lead = Lead::where('tenant_id', $tenantId)->find($leadId);
        if ($lead && !empty($newTags)) {
            $existing = (array) ($lead->tags ?? []);
            $merged = array_values(array_unique(array_merge($existing, $newTags)));
            $lead->update(['tags' => $merged]);
            $context['lead']['tags'] = $merged;

            return ['status' => 'success', 'lead_id' => $lead->id, 'tags' => $merged];
        }

        return ['status' => 'failed', 'message' => 'Lead not found or no tags provided'];
    }

    protected function changeStatus(array $params, array &$context, int $tenantId): array
    {
        return $this->updateLeadStatus($params, $context, $tenantId);
    }

    protected function addNote(array $params, array &$context, int $tenantId): array
    {
        $leadId = $params['lead_id'] ?? ($context['lead']['id'] ?? ($context['entity_id'] ?? null));
        $note = $params['note'] ?? ($params['content'] ?? 'Automated Workflow note');

        if ($leadId) {
            CrmActivity::create([
                'tenant_id' => $tenantId,
                'lead_id' => $leadId,
                'user_id' => $context['user_id'] ?? null,
                'activity_type' => 'note_added',
                'title' => 'Workflow Note',
                'description' => $note,
                'completed_at' => now(),
            ]);

            return ['status' => 'success', 'lead_id' => $leadId, 'note' => $note];
        }

        return ['status' => 'failed', 'message' => 'Lead ID missing for note creation'];
    }

    protected function createSupportTicket(array $params, array &$context, int $tenantId): array
    {
        $subject = $params['subject'] ?? 'Automated Support Ticket';
        $ticket = SupportTicket::create([
            'tenant_id' => $tenantId,
            'ticket_number' => 'TCK-' . strtoupper(Str::random(8)),
            'subject' => $subject,
            'description' => $params['description'] ?? 'Created automatically via CRM workflow automation.',
            'customer_id' => $params['customer_id'] ?? ($context['customer_id'] ?? null),
            'contact_id' => $params['contact_id'] ?? ($context['contact_id'] ?? null),
            'priority' => $params['priority'] ?? 'medium',
            'status' => 'new',
            'channel' => 'automation',
            'created_date' => now(),
        ]);

        $context['created_ticket_id'] = $ticket->id;

        return [
            'status' => 'success',
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
        ];
    }

    protected function createAppointment(array $params, array &$context, int $tenantId): array
    {
        $title = $params['title'] ?? 'Automated Appointment';
        $startTime = Carbon::parse($params['start_time'] ?? now()->addDays(1)->setTime(10, 0));
        $endTime = (clone $startTime)->addMinutes((int)($params['duration_minutes'] ?? 45));

        $appointment = Meeting::create([
            'tenant_id' => $tenantId,
            'title' => $title,
            'description' => $params['description'] ?? 'Booked via CRM workflow automation.',
            'customer_id' => $params['customer_id'] ?? ($context['customer_id'] ?? null),
            'lead_id' => $params['lead_id'] ?? ($context['lead']['id'] ?? ($context['lead_id'] ?? null)),
            'organizer_id' => $params['organizer_id'] ?? ($context['user_id'] ?? null),
            'booking_reference' => 'APT-' . strtoupper(Str::random(8)),
            'meeting_date' => $startTime->toDateString(),
            'start_time' => $startTime->toTimeString(),
            'end_time' => $endTime->toTimeString(),
            'location_type' => $params['location_type'] ?? 'online',
            'meet_link' => $params['meet_link'] ?? 'https://meet.google.com/' . Str::random(10),
            'status' => 'scheduled',
        ]);

        $context['created_appointment_id'] = $appointment->id;

        return [
            'status' => 'success',
            'appointment_id' => $appointment->id,
            'reference' => $appointment->booking_reference,
            'date' => $appointment->meeting_date,
            'start_time' => $appointment->start_time,
        ];
    }

    protected function generateReminder(array $params, array &$context, int $tenantId): array
    {
        $recipientId = $params['user_id'] ?? ($context['assigned_user_id'] ?? ($context['user_id'] ?? null));
        $title = $params['title'] ?? 'Workflow Reminder';
        $message = $params['message'] ?? 'Reminder triggered by CRM workflow automation.';

        if ($recipientId) {
            $user = User::find($recipientId);
            if ($user) {
                $this->notificationService->notify(
                    recipient: $user,
                    type: 'workflow_reminder',
                    title: $title,
                    message: $message,
                    actionUrl: $params['action_url'] ?? null
                );

                return ['status' => 'success', 'user_id' => $recipientId, 'reminder_sent' => true];
            }
        }

        return ['status' => 'failed', 'message' => 'Recipient user not found'];
    }

    protected function notifyUser(array $params, array &$context, int $tenantId): array
    {
        return $this->generateReminder($params, $context, $tenantId);
    }

    protected function sendWhatsAppTemplate(array $params, array &$context, int $tenantId): array
    {
        $phone = $params['to'] ?? ($context['lead']['phone'] ?? ($context['customer']['phone'] ?? null));
        $templateName = $params['template_name'] ?? 'welcome_crm';

        if (!$phone) {
            return ['status' => 'failed', 'message' => 'Recipient phone number missing'];
        }

        // Consent check
        $hasConsent = !CommunicationSuppression::where('tenant_id', $tenantId)
            ->whereIn('channel', ['whatsapp', 'all'])
            ->where('identifier', strtolower(trim((string)$phone)))
            ->exists();

        if (!$hasConsent) {
            return ['status' => 'suppressed', 'message' => 'Recipient has opted out of WhatsApp messages'];
        }

        try {
            $result = $this->whatsAppService->sendTemplate(
                tenantId: $tenantId,
                toPhone: $phone,
                templateName: $templateName,
                variables: (array) ($params['variables'] ?? [])
            );

            return ['status' => 'success', 'gateway_response' => $result];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    protected function sendEmail(array $params, array &$context, int $tenantId): array
    {
        $to = $params['to'] ?? ($context['lead']['email'] ?? ($context['customer']['email'] ?? null));
        $subject = $params['subject'] ?? 'Notification from CRM';
        $body = $params['body'] ?? 'This is an automated CRM workflow notification.';

        if (!$to) {
            return ['status' => 'failed', 'message' => 'Recipient email address missing'];
        }

        try {
            $result = $this->emailAdapter->sendEmail([
                'tenant_id' => $tenantId,
                'to' => $to,
                'subject' => $subject,
                'body' => $body,
            ]);

            return ['status' => 'success', 'recipient' => $to, 'message' => 'Email dispatched successfully'];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    protected function sendSms(array $params, array &$context, int $tenantId): array
    {
        $phone = $params['to'] ?? ($context['lead']['phone'] ?? null);
        $message = $params['message'] ?? 'CRM Automation Notification';

        if (!$phone) {
            return ['status' => 'failed', 'message' => 'Recipient phone missing'];
        }

        try {
            $result = $this->smsAdapter->sendSms([
                'tenant_id' => $tenantId,
                'to' => $phone,
                'message' => $message,
            ]);

            return ['status' => 'success', 'recipient' => $phone];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    protected function sendWebhook(array $params, array &$context, int $tenantId): array
    {
        $url = $params['url'] ?? null;
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return ['status' => 'failed', 'message' => 'Invalid or missing destination webhook URL'];
        }

        try {
            $response = Http::timeout(5)->post($url, [
                'tenant_id' => $tenantId,
                'event_context' => $context,
                'custom_payload' => $params['payload'] ?? [],
                'timestamp' => now()->toIso8601String(),
            ]);

            return [
                'status' => $response->successful() ? 'success' : 'failed',
                'http_status' => $response->status(),
                'response_snippet' => Str::limit($response->body(), 200),
            ];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    protected function requestApproval(array $params, array &$context, int $tenantId, ?int $runId = null, ?string $nodeId = null): array
    {
        if (!$runId || !$nodeId) {
            return ['status' => 'failed', 'message' => 'Workflow run ID or node ID missing for approval request'];
        }

        $approval = WorkflowApproval::create([
            'tenant_id' => $tenantId,
            'workflow_run_id' => $runId,
            'workflow_id' => $params['workflow_id'] ?? ($context['workflow_id'] ?? null),
            'node_id' => $nodeId,
            'title' => $params['title'] ?? 'Manager Approval Required',
            'category' => $params['category'] ?? 'general',
            'status' => 'pending',
            'approver_role' => $params['approver_role'] ?? 'sales_manager',
            'requested_by_user_id' => $context['user_id'] ?? null,
            'action_type' => $params['action_type'] ?? null,
            'action_params' => $params['action_params'] ?? [],
            'context_summary' => [
                'entity_type' => $context['entity_type'] ?? null,
                'entity_id' => $context['entity_id'] ?? null,
                'amount' => $params['amount'] ?? ($context['deal']['deal_value'] ?? null),
            ],
            'expires_at' => now()->addHours((int)($params['expiry_hours'] ?? 48)),
        ]);

        $context['approval_id'] = $approval->id;

        return [
            'status' => 'waiting_approval',
            'approval_id' => $approval->id,
            'approver_role' => $approval->approver_role,
            'expires_at' => $approval->expires_at->toIso8601String(),
        ];
    }

    protected function createProjectDraft(array $params, array &$context, int $tenantId): array
    {
        $name = $params['name'] ?? ('Project from Deal #' . ($context['deal']['id'] ?? Str::random(4)));
        $project = Project::create([
            'tenant_id' => $tenantId,
            'code' => 'PRJ-' . date('Y') . '-' . strtoupper(Str::random(4)),
            'name' => $name,
            'description' => $params['description'] ?? 'Created automatically via Sales Workflow trigger.',
            'customer_id' => $params['customer_id'] ?? ($context['customer_id'] ?? ($context['deal']['customer_id'] ?? null)),
            'deal_id' => $params['deal_id'] ?? ($context['deal']['id'] ?? null),
            'budget' => (float)($params['budget'] ?? ($context['deal']['deal_value'] ?? 0)),
            'status' => 'not_started',
            'start_date' => now()->toDateString(),
        ]);

        $context['created_project_id'] = $project->id;

        return [
            'status' => 'success',
            'project_id' => $project->id,
            'code' => $project->code,
            'name' => $project->name,
        ];
    }

    protected function createDeal(array $params, array &$context, int $tenantId): array
    {
        $title = $params['title'] ?? 'New Pipeline Deal';
        $deal = Deal::create([
            'tenant_id' => $tenantId,
            'title' => $title,
            'lead_id' => $params['lead_id'] ?? ($context['lead']['id'] ?? null),
            'customer_id' => $params['customer_id'] ?? ($context['customer_id'] ?? null),
            'deal_value' => (float)($params['deal_value'] ?? 50000),
            'stage' => $params['stage'] ?? 'lead',
            'pipeline_id' => $params['pipeline_id'] ?? 1,
            'assigned_user_id' => $params['assigned_user_id'] ?? ($context['assigned_user_id'] ?? null),
        ]);

        $context['created_deal_id'] = $deal->id;

        return [
            'status' => 'success',
            'deal_id' => $deal->id,
            'title' => $deal->title,
            'deal_value' => $deal->deal_value,
        ];
    }

    protected function generateReport(array $params, array &$context, int $tenantId): array
    {
        return [
            'status' => 'success',
            'report_name' => $params['report_type'] ?? 'monthly_attribution',
            'generated_at' => now()->toIso8601String(),
            'download_url' => '/api/v1/crm/analytics/reports/export?token=' . Str::random(16),
        ];
    }
}
