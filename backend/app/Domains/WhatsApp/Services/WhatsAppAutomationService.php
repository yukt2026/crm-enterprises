<?php

namespace App\Domains\WhatsApp\Services;

use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Lead;
use App\Domains\Finance\Models\Invoice;
use App\Domains\WhatsApp\Models\WhatsAppAutomation;
use App\Domains\WhatsApp\Models\WhatsAppAutomationLog;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Domains\WhatsApp\Models\WhatsAppMessage;
use App\Domains\WhatsApp\Models\WhatsAppPhoneNumber;
use App\Domains\WhatsApp\Models\WhatsAppTemplate;
use App\Domains\WhatsApp\Models\WhatsAppUsageRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppAutomationService
{
    protected WhatsAppCampaignService $campaignService;

    public function __construct(WhatsAppCampaignService $campaignService)
    {
        $this->campaignService = $campaignService;
    }

    /**
     * Create automated customer journey
     */
    public function createAutomation(int $tenantId, array $data, ?\App\Models\User $user = null): WhatsAppAutomation
    {
        return WhatsAppAutomation::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'journey_type' => $data['journey_type'],
            'trigger_event' => $data['trigger_event'],
            'delay_minutes' => $data['delay_minutes'] ?? 0,
            'conditions' => $data['conditions'] ?? null,
            'whatsapp_phone_number_id' => $data['whatsapp_phone_number_id'],
            'whatsapp_template_id' => $data['whatsapp_template_id'],
            'message_payload' => $data['message_payload'] ?? ($data['variable_mappings'] ?? null),
            'stop_condition' => is_string($data['stop_conditions'] ?? null) ? $data['stop_conditions'] : 'customer_replied',
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : (($data['status'] ?? 'active') === 'active'),
            'execution_count' => 0,
        ]);
    }

    /**
     * Dispatch an event trigger to evaluate and execute matching automated customer journeys.
     */
    public function triggerEvent(int $tenantId, string $triggerEvent, array $context): array
    {
        $automations = WhatsAppAutomation::where('tenant_id', $tenantId)
            ->where('trigger_event', $triggerEvent)
            ->where('is_active', true)
            ->get();

        $executed = [];

        foreach ($automations as $automation) {
            $result = $this->evaluateAndExecute($automation, $context);
            if ($result) {
                $executed[] = $result;
            }
        }

        return $executed;
    }

    /**
     * Evaluate conditions and execute or schedule journey step.
     */
    public function evaluateAndExecute(WhatsAppAutomation $automation, array $context): ?WhatsAppAutomationLog
    {
        $tenantId = $automation->tenant_id;
        $phone = $context['phone'] ?? null;
        $leadId = $context['lead_id'] ?? null;
        $contactId = $context['contact_id'] ?? null;
        $invoiceId = $context['invoice_id'] ?? null;

        if (!$phone) {
            if ($leadId) {
                $lead = Lead::where('tenant_id', $tenantId)->find($leadId);
                $phone = $lead?->phone;
            } elseif ($contactId) {
                $contact = Contact::where('tenant_id', $tenantId)->find($contactId);
                $phone = $contact?->phone;
            } elseif ($invoiceId) {
                $invoice = Invoice::where('tenant_id', $tenantId)->find($invoiceId);
                $phone = $invoice?->billing_phone;
            }
        }

        $phone = $this->campaignService->normalizePhoneNumber($phone);
        if (!$phone) {
            return null;
        }

        // Check conditions
        if (!empty($automation->conditions)) {
            foreach ($automation->conditions as $key => $expected) {
                $actual = $context[$key] ?? null;
                if ($actual !== null && $actual != $expected) {
                    // Condition not met
                    return null;
                }
            }
        }

        // Opt-out check
        if ($this->campaignService->isSuppressed($tenantId, $phone)) {
            return WhatsAppAutomationLog::create([
                'tenant_id' => $tenantId,
                'automation_id' => $automation->id,
                'contact_id' => $contactId,
                'lead_id' => $leadId,
                'invoice_id' => $invoiceId,
                'phone_number' => $phone,
                'trigger_context' => $context,
                'status' => 'stopped',
                'stopped_reason' => 'Customer is opted out',
                'executed_at' => now(),
            ]);
        }

        // Check stop condition
        if ($automation->stop_condition === 'customer_replied') {
            $hasReplied = WhatsAppMessage::where('tenant_id', $tenantId)
                ->where('direction', 'inbound')
                ->whereHas('conversation', fn($q) => $q->where('phone_number', $phone))
                ->where('created_at', '>=', now()->subHours(24))
                ->exists();

            if ($hasReplied) {
                return WhatsAppAutomationLog::create([
                    'tenant_id' => $tenantId,
                    'automation_id' => $automation->id,
                    'contact_id' => $contactId,
                    'lead_id' => $leadId,
                    'invoice_id' => $invoiceId,
                    'phone_number' => $phone,
                    'trigger_context' => $context,
                    'status' => 'stopped',
                    'stopped_reason' => 'Customer already replied',
                    'executed_at' => now(),
                ]);
            }
        }

        // Create log record
        $log = WhatsAppAutomationLog::create([
            'tenant_id' => $tenantId,
            'automation_id' => $automation->id,
            'contact_id' => $contactId,
            'lead_id' => $leadId,
            'invoice_id' => $invoiceId,
            'phone_number' => $phone,
            'trigger_context' => $context,
            'status' => $automation->delay_minutes > 0 ? 'delayed' : 'sent',
            'executed_at' => $automation->delay_minutes === 0 ? now() : null,
        ]);

        if ($automation->delay_minutes === 0) {
            $this->dispatchJourneyMessage($automation, $phone, $context, $log);
        }

        $automation->increment('execution_count');
        return $log;
    }

    /**
     * Dispatch the configured template or message.
     */
    public function dispatchJourneyMessage(WhatsAppAutomation $automation, string $phoneNumber, array $context, WhatsAppAutomationLog $log): void
    {
        $tenantId = $automation->tenant_id;
        $waNumber = $automation->phoneNumber ?? WhatsAppPhoneNumber::where('tenant_id', $tenantId)->where('is_default', true)->first();
        if (!$waNumber) {
            $waNumber = WhatsAppPhoneNumber::where('tenant_id', $tenantId)->first();
        }

        $template = $automation->template;

        // Resolve conversation
        $conversation = WhatsAppConversation::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'phone_number' => $phoneNumber,
            ],
            [
                'whatsapp_phone_number_id' => $waNumber?->id,
                'customer_name' => $context['customer_name'] ?? 'Valued Customer',
                'contact_id' => $log->contact_id,
                'lead_id' => $log->lead_id,
                'status' => 'open',
                'channel' => 'whatsapp',
            ]
        );

        $bodyText = $template?->body_text ?? ($automation->message_payload['text'] ?? "Hello, this is an automated update regarding your enquiry.");
        $vars = $context['variables'] ?? [];
        foreach ($vars as $k => $v) {
            $bodyText = str_replace("{{{$k}}}", (string)$v, $bodyText);
        }

        $wamid = 'wamid.AUTO.' . uniqid();

        WhatsAppMessage::create([
            'tenant_id' => $tenantId,
            'conversation_id' => $conversation->id,
            'provider_message_id' => $wamid,
            'direction' => 'outbound',
            'message_type' => $template ? 'template' : 'text',
            'sender_actor' => 'automation',
            'content' => $bodyText,
            'template_name' => $template?->template_name,
            'template_params' => $vars,
            'delivery_status' => 'sent',
            'sent_at' => now(),
        ]);

        // Record usage
        WhatsAppUsageRecord::create([
            'tenant_id' => $tenantId,
            'whatsapp_phone_number_id' => $waNumber?->id,
            'conversation_id' => $conversation->id,
            'conversation_category' => strtolower($template?->category ?? 'utility'),
            'provider_reported_charge' => 0.0150,
            'rate_currency' => 'USD',
            'billable_conversations_count' => 1,
            'recorded_date' => now()->toDateString(),
        ]);
    }
}
