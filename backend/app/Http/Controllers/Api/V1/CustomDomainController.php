<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\LandingPages\Models\CustomDomain;
use App\Domains\LandingPages\Services\LandingPagePublishingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomDomainController extends Controller
{
    public function __construct(
        protected LandingPagePublishingService $publishingService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $domains = CustomDomain::where('tenant_id', $tenantId)
            ->withCount('landingPages')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $domains,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => 'required|string|max:255|unique:custom_domains,domain',
        ]);

        $tenantId = $request->user()->current_tenant_id;
        $domainName = strtolower(trim($validated['domain']));
        $token = 'crm-verify-' . Str::random(24);

        $customDomain = CustomDomain::create([
            'tenant_id' => $tenantId,
            'domain' => $domainName,
            'verification_token' => $token,
            'verification_type' => 'txt',
            'verification_status' => 'pending',
            'ssl_status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Custom domain added. Please add the DNS TXT verification record.',
            'data' => $customDomain,
        ], 201);
    }

    public function verify(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $customDomain = CustomDomain::where('tenant_id', $tenantId)->findOrFail($id);

        $verifiedDomain = $this->publishingService->verifyCustomDomain($customDomain);

        return response()->json([
            'success' => $verifiedDomain->verification_status === 'verified',
            'message' => $verifiedDomain->verification_status === 'verified'
                ? 'Domain verified successfully and SSL provisioned.'
                : ($verifiedDomain->last_verification_error ?? 'Verification failed.'),
            'data' => $verifiedDomain,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $customDomain = CustomDomain::where('tenant_id', $tenantId)->findOrFail($id);
        $customDomain->delete();

        return response()->json([
            'success' => true,
            'message' => 'Custom domain removed.',
        ]);
    }
}
