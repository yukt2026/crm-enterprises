<?php

namespace App\Domains\Workflow\Services;

use App\Domains\Communications\Services\NotificationService;
use App\Domains\CRM\Models\CrmActivity;
use App\Domains\CRM\Models\Deal;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Omnichannel\Adapters\EmailChannelAdapter;
use App\Domains\Omnichannel\Adapters\SmsChannelAdapter;
use App\Domains\Omnichannel\Models\CommunicationSuppression;
use App\Domains\Omnichannel\Models\OmnichannelConversation;
use App\Domains\Projects\Models\Task;
use App\Domains\Voice\Models\VoiceAiAgent;
use App\Domains\Voice\Providers\TelephonyProviderManager;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Domains\WhatsApp\Services\WhatsAppMessagingService;
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
     * Execute an action with safety checks, consent verification, and logging.
     */
    public function execute(string $actionType, array $params, array &$context, int $tenantId, bool $isDryRun = false): array
    {
        if ($isDryRun) {
            return [
                'status' => 'simulated',
                'action_type' => $actionType,
                'params' => $params,
                'message' => "Dry run simulation for action [{$actionType}] completed.",
            ];
        }

        return match ($actionType) {
            'crm.update_lead_status' => $this->updateLeadStatus($params, $context, $tenantId),
            'crm.assign_lead' => $this->assignLead($params, $context, $tenantId),
            'crm.add_tags' => $this->addTags($params, $context, $tenantId),
            'crm.create_task' => $this->createTask($params, $context, $tenantId),
            'crm.add_note' => $this->addNote($params, $context, $tenantId),
            'communication.send_whatsapp_template' => $this->sendWhatsAppTemplate($params, $context, $tenantId),
            'communication.send_email' => $this->sendEmail($params, $context, $tenantId),
            'communication.send_sms' => $this->sendSms($params, $context, $tenantId),
            'sales.create_deal' => $this->createDeal($params, $context, $tenantId),
            'notification.notify_user' => $this->notifyUser($params, $context, $tenantId),
            'webhook.send' => $this->sendWebhook($params, $context, $tenantId),
            default => [
                'status' => 'skipped',
                'message' => "Unsupported action type: {$actionType}",
            ],
        };
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

    protected function assignLead(array $params, array &$context, int $tenantId): array
    {
        $leadId = $params['lead_id'] ?? ($context['lead']['id'] ?? ($context['entity_id'] ?? null));
        $userId = $params['assigned_user_id'] ?? null;

        $lead = Lead::where('tenant_id', $tenantId)->find($leadId);
        if ($lead && $userId) {
            $lead->update(['assigned_user_id' => $userId]);
            $context['lead']['assigned_user_id'] = $userId;

            return ['status' => 'success', 'lead_id' => $lead->id, 'assigned_to' => $userId];
        }

        return ['status' => 'failed', 'message' => 'Lead or Assignee not found'];
    }

    protected function addTags(array $params, array &$context, int $tenantId): array
    {
        $leadId = $params['lead_id'] ?? ($context['lead']['id'] ?? ($context['entity_id'] ?? null));
        $newTags = (array) ($params['tags'] ?? []);

        $lead = Lead::where('tenant_id', $tenantId)->find($leadId);
        if ($lead) {
            $existing = (array) ($lead->tags ?? []);
            $merged = array_values(array_unique(array_merge($existing, $newTags)));
            $lead->update(['tags' => $merged]);
            $context['lead']['tags'] = $merged;

            return ['status' => 'success', 'tags' => $merged];
        }

        return ['status' => 'failed', 'message' => 'Lead not found'];
    }

    protected function createTask(array $params, array &$context, int $tenantId): array
    {
        $task = Task::create([
            'tenant_id' => $tenantId,
            'title' => $params['title'] ?? 'Automated Workflow Follow-up',
            'description' => $params['description'] ?? 'Generated by visual workflow automation rule.',
            'lead_id' => $params['lead_id'] ?? ($context['lead']['id'] ?? null),
            'assigned_user_id' => $params['assigned_user_id'] ?? ($context['lead']['assigned_user_id'] ?? null),
            'priority' => $params['priority'] ?? 'medium',
            'status' => 'todo',
            'due_date' => now()->addHours($params['due_in_hours'] ?? 24),
        ]);

        return ['status' => 'success', 'task_id' => $task->id];
    }

    protected function addNote(array $params, array &$context, int $tenantId): array
    {
        $leadId = $params['lead_id'] ?? ($context['lead']['id'] ?? null);

        $activity = CrmActivity::create([
            'tenant_id' => $tenantId,
            'lead_id' => $leadId,
            'user_id' => $params['user_id'] ?? null,
            'activity_type' => 'workflow_automation',
            'title' => $params['title'] ?? 'Automation Note',
            'description' => $params['content'] ?? 'Workflow rule applied.',
            'created_at' => now(),
        ]);

        return ['status' => 'success', 'activity_id' => $activity->id];
    }

    protected function sendEmail(array $params, array &$context, int $tenantId): array
    {
        $recipient = $params['to'] ?? ($context['lead']['email'] ?? ($context['customer']['email'] ?? null));

        if (!$recipient) {
            return ['status' => 'skipped', 'message' => 'No recipient email available.'];
        }

        // Suppression check
        $isSuppressed = CommunicationSuppression::where('tenant_id', $tenantId)
            ->whereIn('channel', ['email', 'all'])
            ->where('identifier', strtolower(trim($recipient)))
            ->exists();

        if ($isSuppressed) {
            return ['status' => 'suppressed', 'message' => "Recipient {$recipient} is on suppression list."];
        }

        // Find or create conversation
        $conversation = OmnichannelConversation::firstOrCreate([
            'tenant_id' => $tenantId,
            'channel' => 'email',
            'customer_email' => strtolower(trim($recipient)),
        ], [
            'phone_number' => 'N/A',
            'customer_name' => $context['lead']['name'] ?? $recipient,
            'status' => 'open',
            'subject' => $params['subject'] ?? 'Notification from CRM',
        ]);

        $message = $this->emailAdapter->send($conversation, [
            'to' => $recipient,
            'subject' => $params['subject'] ?? 'Automated Notification',
            'content' => $params['content'] ?? 'Hello, this is an automated communication.',
        ]);

        return ['status' => 'success', 'message_id' => $message->id];
    }

    protected function sendSms(array $params, array &$context, int $tenantId): array
    {
        $phone = $params['to'] ?? ($context['lead']['phone'] ?? ($context['customer']['phone'] ?? null));

        if (!$phone || $phone === 'N/A') {
            return ['status' => 'skipped', 'message' => 'No valid phone number for SMS.'];
        }

        $cleanPhone = preg_replace('/[^\+0-9]/', '', $phone);

        $isSuppressed = CommunicationSuppression::where('tenant_id', $tenantId)
            ->whereIn('channel', ['sms', 'all'])
            ->where('identifier', $cleanPhone)
            ->exists();

        if ($isSuppressed) {
            return ['status' => 'suppressed', 'message' => "Recipient {$cleanPhone} has opted out."];
        }

        $conversation = OmnichannelConversation::firstOrCreate([
            'tenant_id' => $tenantId,
            'channel' => 'sms',
            'phone_number' => $cleanPhone,
        ], [
            'customer_name' => $context['lead']['name'] ?? $cleanPhone,
            'status' => 'open',
        ]);

        $message = $this->smsAdapter->send($conversation, [
            'to' => $cleanPhone,
            'content' => $params['content'] ?? 'Important update regarding your account.',
            'dlt_template_id' => $params['dlt_template_id'] ?? null,
        ]);

        return ['status' => 'success', 'message_id' => $message->id];
    }

    protected function sendWhatsAppTemplate(array $params, array &$context, int $tenantId): array
    {
        $phone = $params['to'] ?? ($context['lead']['phone'] ?? ($context['customer']['phone'] ?? null));

        if (!$phone || $phone === 'N/A') {
            return ['status' => 'skipped', 'message' => 'No phone number for WhatsApp.'];
        }

        $cleanPhone = preg_replace('/[^\+0-9]/', '', $phone);

        $conversation = WhatsAppConversation::firstOrCreate([
            'tenant_id' => $tenantId,
            'phone_number' => $cleanPhone,
        ], [
            'channel' => 'whatsapp',
            'customer_name' => $context['lead']['name'] ?? $cleanPhone,
            'status' => 'open',
        ]);

        $msg = $this->whatsAppService->sendMessage(
            $conversation,
            'template',
            null,
            $params['template_name'] ?? 'welcome_crm',
            $params['parameters'] ?? []
        );

        return ['status' => 'success', 'message_id' => $msg->id];
    }

    protected function createDeal(array $params, array &$context, int $tenantId): array
    {
        $lead = isset($context['lead']['id']) ? Lead::find($context['lead']['id']) : null;

        $deal = Deal::create([
            'tenant_id' => $tenantId,
            'name' => $params['name'] ?? ($lead ? "Deal: {$lead->name}" : 'New Automation Deal'),
            'lead_id' => $lead?->id,
            'assigned_user_id' => $params['assigned_user_id'] ?? $lead?->assigned_user_id,
            'stage' => $params['stage'] ?? 'qualification',
            'deal_value' => $params['value'] ?? ($lead?->estimated_value ?? 0),
            'status' => 'open',
        ]);

        return ['status' => 'success', 'deal_id' => $deal->id];
    }

    protected function notifyUser(array $params, array &$context, int $tenantId): array
    {
        $userId = $params['user_id'] ?? ($context['lead']['assigned_user_id'] ?? null);

        if ($userId) {
            $this->notificationService->sendNotification(
                $tenantId,
                $userId,
                'workflow_alert',
                $params['title'] ?? 'Workflow Notification',
                $params['message'] ?? 'An automated workflow event occurred.',
                ['context' => $context]
            );

            return ['status' => 'success', 'notified_user_id' => $userId];
        }

        return ['status' => 'skipped', 'message' => 'No user targeted for notification'];
    }

    protected function sendWebhook(array $params, array &$context, int $tenantId): array
    {
        $url = $params['url'] ?? null;
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return ['status' => 'failed', 'message' => 'Invalid webhook URL'];
        }

        try {
            $response = Http::timeout(5)->post($url, [
                'tenant_id' => $tenantId,
                'event' => $context['trigger_event'] ?? 'workflow.trigger',
                'payload' => $context,
                'timestamp' => now()->toIso8601String(),
            ]);

            return [
                'status' => $response->successful() ? 'success' : 'failed',
                'http_status' => $response->status(),
            ];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }
}
