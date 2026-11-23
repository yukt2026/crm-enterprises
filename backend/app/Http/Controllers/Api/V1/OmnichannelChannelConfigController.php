<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Omnichannel\Models\CommunicationSuppression;
use App\Domains\Omnichannel\Models\EmailAccount;
use App\Domains\Omnichannel\Models\IdentityMergePending;
use App\Domains\Omnichannel\Models\OmnichannelChannelSetting;
use App\Domains\Omnichannel\Models\SmsConfiguration;
use App\Domains\Omnichannel\Models\SocialChannelAccount;
use App\Domains\Omnichannel\Models\TelegramAccount;
use App\Domains\Omnichannel\Models\WebsiteChatWidget;
use App\Domains\Omnichannel\Services\IdentityResolutionService;
use App\Domains\Omnichannel\Services\OmnichannelInboxService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OmnichannelChannelConfigController extends Controller
{
    public function __construct(
        protected IdentityResolutionService $identityService,
        protected OmnichannelInboxService $inboxService
    ) {}

    // ==========================================
    // 1. EMAIL ACCOUNTS
    // ==========================================

    public function getEmailAccounts(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $accounts = EmailAccount::where('tenant_id', $tenantId)->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    public function saveEmailAccount(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email_address' => 'required|email|max:150',
            'provider' => 'nullable|string|in:smtp_imap,sendgrid,mailgun,ses,gmail_oauth',
            'smtp_host' => 'nullable|string',
            'smtp_port' => 'nullable|integer',
            'smtp_encryption' => 'nullable|string',
            'smtp_username' => 'nullable|string',
            'smtp_password' => 'nullable|string',
            'imap_host' => 'nullable|string',
            'imap_port' => 'nullable|integer',
            'imap_encryption' => 'nullable|string',
            'imap_username' => 'nullable|string',
            'imap_password' => 'nullable|string',
            'signature_html' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        $account = EmailAccount::updateOrCreate(
            ['tenant_id' => $tenantId, 'email_address' => $validated['email_address']],
            array_merge($validated, ['tenant_id' => $tenantId])
        );

        return response()->json([
            'success' => true,
            'data' => $account,
        ]);
    }

    // ==========================================
    // 2. SMS CONFIGURATIONS
    // ==========================================

    public function getSmsConfigs(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $configs = SmsConfiguration::where('tenant_id', $tenantId)->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $configs,
        ]);
    }

    public function saveSmsConfig(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'provider' => 'required|string|in:twilio,exotel,fast2sms,custom_http',
            'account_sid' => 'nullable|string',
            'auth_token' => 'nullable|string',
            'api_key' => 'nullable|string',
            'sender_id' => 'nullable|string|max:20',
            'dlt_entity_id' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        $config = SmsConfiguration::updateOrCreate(
            ['tenant_id' => $tenantId, 'provider' => $validated['provider']],
            array_merge($validated, ['tenant_id' => $tenantId])
        );

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    // ==========================================
    // 3. WEBSITE CHAT WIDGETS
    // ==========================================

    public function getWebChatWidgets(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $widgets = WebsiteChatWidget::where('tenant_id', $tenantId)->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $widgets,
        ]);
    }

    public function saveWebChatWidget(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'name' => 'required|string|max:150',
            'brand_color' => 'nullable|string|max:30',
            'brand_title' => 'nullable|string|max:150',
            'brand_logo_url' => 'nullable|string',
            'welcome_message' => 'nullable|string',
            'pre_chat_fields' => 'nullable|array',
            'agent_availability_mode' => 'nullable|string|in:always_ai,hybrid,business_hours',
            'is_active' => 'nullable|boolean',
            'allowed_domains' => 'nullable|array',
        ]);

        $widgetKey = $validated['id']
            ? WebsiteChatWidget::where('tenant_id', $tenantId)->find($validated['id'])?->widget_key
            : 'widget_' . Str::random(32);

        $widget = WebsiteChatWidget::updateOrCreate(
            ['tenant_id' => $tenantId, 'id' => $validated['id'] ?? null],
            array_merge($validated, [
                'tenant_id' => $tenantId,
                'widget_key' => $widgetKey,
            ])
        );

        return response()->json([
            'success' => true,
            'data' => $widget,
        ]);
    }

    // ==========================================
    // 4. SUPPRESSION LIST
    // ==========================================

    public function getSuppressions(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $list = CommunicationSuppression::where('tenant_id', $tenantId)->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $list,
        ]);
    }

    public function addSuppression(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'channel' => 'required|string|in:email,sms,whatsapp,all',
            'identifier' => 'required|string|max:150',
            'reason' => 'required|string|in:unsubscribe,bounce,spam_complaint,dnd',
        ]);

        $suppression = CommunicationSuppression::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'channel' => $validated['channel'],
                'identifier' => strtolower(trim($validated['identifier'])),
            ],
            [
                'reason' => $validated['reason'],
                'suppressed_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $suppression,
        ]);
    }

    public function removeSuppression(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        CommunicationSuppression::where('tenant_id', $tenantId)->where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Suppression entry removed.',
        ]);
    }

    // ==========================================
    // 5. IDENTITY RESOLUTION & PENDING MERGES
    // ==========================================

    public function getPendingMerges(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $merges = IdentityMergePending::where('tenant_id', $tenantId)
            ->with('reviewedByUser:id,name')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $merges,
        ]);
    }

    public function reviewMerge(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $pendingMerge = IdentityMergePending::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'decision' => 'required|in:merged,rejected',
            'notes' => 'nullable|string',
        ]);

        $result = $this->identityService->reviewMerge(
            $pendingMerge,
            $validated['decision'],
            $request->user()->id,
            $validated['notes'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    // ==========================================
    // 6. TELEGRAM BOT CHANNEL INTEGRATION
    // ==========================================

    public function getTelegramAccounts(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $accounts = TelegramAccount::where('tenant_id', $tenantId)
            ->with(['branch:id,name', 'department:id,name', 'aiAgent:id,name'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    public function saveTelegramAccount(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $validated = $request->validate([
            'id' => 'nullable|exists:telegram_accounts,id',
            'bot_name' => 'required|string|max:150',
            'bot_username' => 'nullable|string|max:100',
            'bot_token' => 'required|string',
            'branch_id' => 'nullable|exists:branches,id',
            'department_id' => 'nullable|exists:departments,id',
            'business_hours' => 'nullable|array',
            'ai_enabled' => 'nullable|boolean',
            'ai_agent_id' => 'nullable|exists:whatsapp_ai_agents,id',
        ]);

        $account = TelegramAccount::updateOrCreate(
            ['id' => $validated['id'] ?? null, 'tenant_id' => $tenantId],
            [
                'bot_name' => $validated['bot_name'],
                'bot_username' => ltrim($validated['bot_username'] ?? '', '@'),
                'bot_token' => $validated['bot_token'],
                'branch_id' => $validated['branch_id'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'business_hours' => $validated['business_hours'] ?? null,
                'ai_enabled' => $validated['ai_enabled'] ?? false,
                'ai_agent_id' => $validated['ai_agent_id'] ?? null,
                'is_connected' => true,
                'webhook_url' => url("/api/v1/webhooks/telegram/{$tenantId}"),
                'webhook_secret_token' => Str::random(32),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Telegram Bot configuration saved and connected.',
            'data' => $account->load(['branch:id,name', 'department:id,name']),
        ]);
    }

    public function disconnectTelegramAccount(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $account = TelegramAccount::where('tenant_id', $tenantId)->findOrFail($id);
        $account->update(['is_connected' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Telegram account disconnected.',
        ]);
    }

    // ==========================================
    // 7. CHANNEL SETTINGS (BRANCH/DEPT/HOURS) & HEALTH
    // ==========================================

    public function getChannelSettings(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $settings = OmnichannelChannelSetting::where('tenant_id', $tenantId)
            ->with(['branch:id,name', 'department:id,name'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    public function saveChannelSettings(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $validated = $request->validate([
            'channel' => 'required|string|in:whatsapp,facebook,instagram,email,sms,webchat,call,telegram',
            'channel_account_id' => 'nullable|string',
            'name' => 'nullable|string|max:150',
            'branch_id' => 'nullable|exists:branches,id',
            'department_id' => 'nullable|exists:departments,id',
            'business_hours' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $setting = OmnichannelChannelSetting::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'channel' => $validated['channel'],
                'channel_account_id' => $validated['channel_account_id'] ?? null,
            ],
            [
                'name' => $validated['name'] ?? ucfirst($validated['channel']) . ' Channel',
                'branch_id' => $validated['branch_id'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'business_hours' => $validated['business_hours'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'health_status' => 'healthy',
                'last_health_check_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "Channel settings for '{$validated['channel']}' updated.",
            'data' => $setting->load(['branch:id,name', 'department:id,name']),
        ]);
    }

    public function testConnection(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $channel = $request->input('channel', 'whatsapp');
        $channelAccountId = $request->input('channel_account_id');

        $result = $this->inboxService->testChannelConnection($tenantId, $channel, $channelAccountId);

        // Update health status
        OmnichannelChannelSetting::where('tenant_id', $tenantId)
            ->where('channel', $channel)
            ->update([
                'health_status' => $result['status'] === 'connected' ? 'healthy' : 'disconnected',
                'last_health_check_at' => now(),
                'last_error' => $result['status'] !== 'connected' ? ['message' => $result['message']] : null,
            ]);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function getChannelHealth(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $channels = ['whatsapp', 'facebook', 'instagram', 'email', 'sms', 'webchat', 'call', 'telegram'];
        $healthOverview = [];

        foreach ($channels as $chan) {
            $test = $this->inboxService->testChannelConnection($tenantId, $chan);
            $healthOverview[$chan] = [
                'channel' => $chan,
                'status' => $test['status'],
                'message' => $test['message'],
                'last_checked_at' => now()->toIso8601String(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $healthOverview,
        ]);
    }
}
