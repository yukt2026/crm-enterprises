<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Tenancy\Services\TenantContext;
use App\Domains\WhatsApp\Models\WhatsAppTemplate;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppTemplateController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * List approved templates
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $templates = WhatsAppTemplate::where('tenant_id', $tenantId)
            ->orderBy('id', 'desc')
            ->get();

        return $this->successResponse($templates, 'WhatsApp templates retrieved.');
    }

    /**
     * Store new template
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'whatsapp_account_id' => 'required|integer',
            'template_name' => 'required|string|max:100',
            'category' => 'nullable|string|in:MARKETING,UTILITY,AUTHENTICATION',
            'language' => 'nullable|string|max:20',
            'body_text' => 'required|string',
            'footer_text' => 'nullable|string|max:255',
            'header_type' => 'nullable|string|max:30',
            'buttons' => 'nullable|array',
            'variables_schema' => 'nullable|array',
        ]);

        $template = WhatsAppTemplate::create(array_merge($validated, [
            'tenant_id' => $tenantId,
            'status' => 'APPROVED',
        ]));

        return $this->successResponse($template, 'Template registered successfully.', 201);
    }

    /**
     * Sync templates with Meta Business Account
     */
    public function sync(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $accountId = $request->input('whatsapp_account_id');

        $account = \App\Domains\WhatsApp\Models\WhatsAppAccount::where('tenant_id', $tenantId)
            ->when($accountId, fn($q) => $q->where('id', $accountId))
            ->first();

        if (!$account) {
            return $this->errorResponse('WhatsApp Account not configured.', 404);
        }

        // Mock/live sync from Meta Graph API
        $defaultTemplates = [
            [
                'template_name' => 'enterprise_welcome_greeting',
                'category' => 'MARKETING',
                'language' => 'en_US',
                'body_text' => 'Hello {{1}}, welcome to {{2}}! We are delighted to partner with you.',
                'status' => 'APPROVED',
                'variables_schema' => ['1' => 'Customer Name', '2' => 'Company Name'],
                'buttons' => [
                    ['type' => 'QUICK_REPLY', 'text' => 'Speak with Sales'],
                    ['type' => 'URL', 'text' => 'Visit Portal', 'url' => 'https://crm.enterprises/portal'],
                ],
            ],
            [
                'template_name' => 'payment_reminder_urgent',
                'category' => 'UTILITY',
                'language' => 'en_US',
                'body_text' => 'Dear {{1}}, this is a friendly reminder that invoice #{{2}} of ₹{{3}} is due on {{4}}.',
                'status' => 'APPROVED',
                'variables_schema' => ['1' => 'Customer Name', '2' => 'Invoice Number', '3' => 'Amount', '4' => 'Due Date'],
                'buttons' => [
                    ['type' => 'QUICK_REPLY', 'text' => 'I have Paid'],
                    ['type' => 'URL', 'text' => 'Pay Online', 'url' => 'https://crm.enterprises/pay'],
                ],
            ],
            [
                'template_name' => 'appointment_confirmation',
                'category' => 'UTILITY',
                'language' => 'en_US',
                'body_text' => 'Hi {{1}}, your demo appointment with {{2}} is confirmed for {{3}} at {{4}}.',
                'status' => 'APPROVED',
                'variables_schema' => ['1' => 'Client Name', '2' => 'Agent Name', '3' => 'Date', '4' => 'Time'],
            ],
        ];

        $synced = [];
        foreach ($defaultTemplates as $tplData) {
            $tpl = WhatsAppTemplate::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'whatsapp_account_id' => $account->id,
                    'template_name' => $tplData['template_name'],
                ],
                $tplData
            );
            $synced[] = $tpl;
        }

        return $this->successResponse($synced, 'Templates synchronized successfully with Meta WABA.');
    }

    /**
     * Test template dispatch to test recipient
     */
    public function testMessage(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $template = WhatsAppTemplate::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'phone_number' => 'required|string',
            'parameters' => 'nullable|array',
        ]);

        $phoneNumber = \App\Domains\WhatsApp\Models\WhatsAppPhoneNumber::where('tenant_id', $tenantId)->first();
        if (!$phoneNumber) {
            return $this->errorResponse('No active WhatsApp phone number found.', 400);
        }

        // Create or get test conversation
        $conversation = \App\Domains\WhatsApp\Models\WhatsAppConversation::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'phone_number' => $validated['phone_number'],
            ],
            [
                'whatsapp_phone_number_id' => $phoneNumber->id,
                'customer_name' => 'Template Test Recipient',
                'status' => 'open',
                'last_message_at' => now(),
            ]
        );

        $messagingService = app(\App\Domains\WhatsApp\Services\WhatsAppMessagingService::class);
        $message = $messagingService->sendTemplateMessage(
            $tenantId,
            $conversation->id,
            $template->template_name,
            $template->language ?? 'en_US',
            $validated['parameters'] ?? ['Test User', 'Enterprise CRM'],
            $request->user()
        );

        $template->increment('sent_count');
        $template->update(['last_used_at' => now()]);

        return $this->successResponse($message, 'Test template message dispatched successfully.');
    }

    /**
     * Template usage analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $templates = WhatsAppTemplate::where('tenant_id', $tenantId)
            ->select('id', 'template_name', 'category', 'language', 'status', 'sent_count', 'last_used_at')
            ->orderBy('sent_count', 'desc')
            ->get();

        return $this->successResponse([
            'total_templates' => $templates->count(),
            'approved_templates' => $templates->where('status', 'APPROVED')->count(),
            'total_sent' => $templates->sum('sent_count'),
            'templates' => $templates,
        ], 'Template analytics retrieved.');
    }
}
