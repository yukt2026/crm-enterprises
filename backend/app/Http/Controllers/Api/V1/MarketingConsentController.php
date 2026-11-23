<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Marketing\Models\MarketingConsent;
use App\Domains\Marketing\Services\CampaignConsentService;
use App\Domains\Marketing\Services\MarketingConsentManager;
use App\Domains\Omnichannel\Models\CommunicationSuppression;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingConsentController extends Controller
{
    public function __construct(
        protected MarketingConsentManager $consentManager,
        protected CampaignConsentService $consentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $query = MarketingConsent::where('tenant_id', $tenantId);

        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }
        if ($request->filled('consent_status')) {
            $query->where('consent_status', $request->consent_status);
        }
        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }

        $consents = $query->orderByDesc('updated_at')->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $consents,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => 'required|in:lead,contact,customer',
            'entity_id' => 'required|integer',
            'channel' => 'required|in:whatsapp,email,sms,voice,all',
            'consent_status' => 'required|in:opted_in,opted_out,pending',
            'source' => 'nullable|string',
            'reason' => 'nullable|string',
        ]);

        $tenantId = $request->user()->current_tenant_id;

        $consent = $this->consentManager->recordConsent(
            $tenantId,
            $validated['entity_type'],
            $validated['entity_id'],
            $validated['channel'],
            $validated['consent_status'],
            $validated['source'] ?? 'manual_crm',
            $validated['reason'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Consent preference recorded.',
            'data' => $consent,
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $validated = $request->validate([
            'channel' => 'required|in:whatsapp,email,sms,voice',
            'address' => 'required|string',
        ]);

        $permission = $this->consentService->checkPermission(
            $tenantId,
            $validated['channel'],
            $validated['address']
        );

        return response()->json([
            'success' => true,
            'data' => $permission,
        ]);
    }

    public function suppressions(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $query = CommunicationSuppression::where('tenant_id', $tenantId);

        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('identifier', 'like', "%{$search}%");
        }

        $suppressions = $query->orderByDesc('suppressed_at')->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $suppressions,
        ]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $validated = $request->validate([
            'channel' => 'required|in:whatsapp,email,sms,voice,all',
            'address' => 'required|string',
            'entity_id' => 'nullable|integer',
            'entity_type' => 'nullable|string',
        ]);

        $this->consentManager->handleUnsubscribeKeyword(
            $tenantId,
            $validated['channel'],
            $validated['address'],
            $validated['entity_id'] ?? null,
            $validated['entity_type'] ?? 'contact'
        );

        return response()->json([
            'success' => true,
            'message' => "Address {$validated['address']} successfully added to suppression list for channel {$validated['channel']}.",
        ]);
    }
}
