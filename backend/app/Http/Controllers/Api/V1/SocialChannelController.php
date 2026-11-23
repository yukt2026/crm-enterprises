<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Omnichannel\Adapters\FacebookMessengerChannelAdapter;
use App\Domains\Omnichannel\Adapters\InstagramDmChannelAdapter;
use App\Domains\Omnichannel\Models\CustomerSocialIdentity;
use App\Domains\Omnichannel\Models\SocialChannelAccount;
use App\Domains\Omnichannel\Models\SocialWebhookEvent;
use App\Domains\Omnichannel\Services\SocialMessagingService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialChannelController extends Controller
{
    public function __construct(
        protected SocialMessagingService $socialService,
        protected FacebookMessengerChannelAdapter $messengerAdapter,
        protected InstagramDmChannelAdapter $instagramAdapter
    ) {}

    protected function getTenantId(): int
    {
        return auth()->user()?->tenant_id ?? (TenantContext::getTenantId() ?? 1);
    }

    /**
     * Get connected social accounts (Facebook Pages & Instagram Accounts)
     */
    public function getAccounts(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $provider = $request->query('provider');
        $accounts = $this->socialService->getAccounts($tenantId, $provider);

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    /**
     * Connect or update a social account
     */
    public function connectAccount(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'provider' => 'required|string|in:facebook,instagram',
            'page_id' => 'required|string',
            'page_name' => 'nullable|string',
            'page_access_token' => 'required|string',
            'instagram_business_id' => 'nullable|string',
            'instagram_username' => 'nullable|string',
            'permissions' => 'nullable|array',
            'ai_enabled' => 'nullable|boolean',
            'ai_agent_id' => 'nullable|integer',
            'ai_instructions' => 'nullable|string',
        ]);

        $account = $this->socialService->connectAccount($tenantId, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Social account connected successfully.',
            'data' => $account,
        ], 201);
    }

    /**
     * Disconnect a social account
     */
    public function disconnectAccount(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $this->socialService->disconnectAccount($tenantId, $id);

        return response()->json([
            'success' => true,
            'message' => 'Social account disconnected successfully.',
        ]);
    }

    /**
     * Send outbound reply over Facebook Messenger or Instagram DM
     */
    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $conversation = WhatsAppConversation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $validated = $request->validate([
            'content' => 'required_without:media_url|string|nullable',
            'media_url' => 'nullable|string',
            'message_type' => 'nullable|string|in:text,image,video,audio,file',
            'tag' => 'nullable|string',
            'message_tag' => 'nullable|string',
        ]);

        if (empty($validated['tag']) && !empty($validated['message_tag'])) {
            $validated['tag'] = $validated['message_tag'];
        }

        $adapter = $conversation->channel === 'instagram' ? $this->instagramAdapter : $this->messengerAdapter;
        $message = $adapter->send($conversation, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Message dispatched successfully.',
            'data' => $message,
        ], 201);
    }

    /**
     * Get social customer identities
     */
    public function getIdentities(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $provider = $request->query('provider');
        $identities = $this->socialService->getIdentities($tenantId, $provider);

        return response()->json([
            'success' => true,
            'data' => $identities,
        ]);
    }

    /**
     * Link social identity to CRM Lead or Customer
     */
    public function linkIdentity(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'lead_id' => 'nullable|integer',
            'customer_id' => 'nullable|integer',
        ]);

        if (!empty($validated['lead_id'])) {
            $identity = $this->socialService->linkIdentityToLead($tenantId, $id, $validated['lead_id']);
        } elseif (!empty($validated['customer_id'])) {
            $identity = $this->socialService->linkIdentityToCustomer($tenantId, $id, $validated['customer_id']);
        } else {
            return response()->json(['error' => 'Either lead_id or customer_id is required.'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Social identity linked successfully.',
            'data' => $identity,
        ]);
    }

    /**
     * Merge multiple social identities for a single customer
     */
    public function mergeIdentities(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $primaryId = $request->input('primary_id', $request->input('primary_identity_id'));
        $secondaryId = $request->input('secondary_id', $request->input('secondary_identity_id'));

        if (!$primaryId || !$secondaryId) {
            return response()->json([
                'success' => false,
                'message' => 'Both primary_id and secondary_id are required.',
            ], 422);
        }

        $merged = $this->socialService->mergeIdentities($tenantId, (int) $primaryId, (int) $secondaryId);

        return response()->json([
            'success' => true,
            'message' => 'Identities merged successfully.',
            'data' => $merged,
        ]);
    }

    /**
     * Get Webhook Delivery & Event Audit Logs
     */
    public function getLogs(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $logs = SocialWebhookEvent::where(fn($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))
            ->when($request->query('provider'), fn($q) => $q->where('provider', $request->query('provider')))
            ->when($request->query('status'), fn($q) => $q->where('status', $request->query('status')))
            ->latest('id')
            ->paginate($request->query('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Social Channel Connection Diagnostics & Eligibility
     */
    public function getDiagnostics(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $accounts = SocialChannelAccount::where('tenant_id', $tenantId)->get();

        $fbConnected = $accounts->where('provider', 'facebook')->where('is_connected', true)->count();
        $igConnected = $accounts->where('provider', 'instagram')->where('is_connected', true)->count();

        $activeFbPages = $accounts->where('provider', 'facebook')->where('is_connected', true)->pluck('page_name', 'page_id');
        $activeIgAccounts = $accounts->where('provider', 'instagram')->where('is_connected', true)->pluck('instagram_username', 'instagram_business_id');

        $activeConversations = WhatsAppConversation::where('tenant_id', $tenantId)
            ->whereIn('channel', ['facebook', 'instagram'])
            ->whereIn('status', ['open', 'pending'])
            ->count();

        $totalIdentities = CustomerSocialIdentity::where('tenant_id', $tenantId)->count();
        $linkedIdentities = CustomerSocialIdentity::where('tenant_id', $tenantId)
            ->where(fn($q) => $q->whereNotNull('lead_id')->orWhereNotNull('customer_id'))
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'connected_accounts_count' => $fbConnected + $igConnected,
                'facebook_pages_connected' => $fbConnected,
                'instagram_accounts_connected' => $igConnected,
                'facebook_pages_count' => $fbConnected,
                'instagram_accounts_count' => $igConnected,
                'total_identities' => $totalIdentities,
                'linked_identities' => $linkedIdentities,
                'active_facebook_pages' => $activeFbPages,
                'active_instagram_accounts' => $activeIgAccounts,
                'active_social_conversations' => $activeConversations,
                'webhook_endpoint' => url('/api/v1/webhooks/meta/social'),
                'webhook_verify_token' => config('services.meta.webhook_verify_token', 'crm_meta_webhook_secret_2026'),
                'meta_eligibility' => [
                    'facebook_pages' => 'Supported (Requires Admin / Editor permissions and pages_messaging permission)',
                    'instagram_accounts' => 'Supported for Professional / Business accounts connected to a Facebook Page (Personal accounts not supported by Meta)',
                ],
            ],
        ]);
    }
}
