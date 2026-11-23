<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\PlatformAdmin\Services\SaasOnboardingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaasOnboardingController extends Controller
{
    public function __construct(
        protected SaasOnboardingService $onboardingService
    ) {}

    /**
     * Start Organization Onboarding (Public or Authenticated)
     */
    public function register(Request $request): JsonResponse
    {
        if (!$request->has('owner_email') && $request->has('email')) {
            $request->merge(['owner_email' => $request->input('email')]);
        }

        $validated = $request->validate([
            'organization_name' => 'required|string|max:150',
            'owner_name' => 'required|string|max:120',
            'owner_email' => 'required|email|max:150',
            'password' => 'nullable|string|min:6',
            'plan_code' => 'nullable|string',
            'industry_template_code' => 'nullable|string',
            'timezone' => 'nullable|string',
            'gstin' => 'nullable|string|max:20',
            'pan' => 'nullable|string|max:20',
            'billing_address' => 'nullable|string',
            'billing_state_code' => 'nullable|string|max:10',
        ]);

        $result = $this->onboardingService->registerOrganizationAndOwner($validated);

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'Organization registered and onboarded successfully.',
        ], 201);
    }

    /**
     * Save Step Progress (1 to 11)
     */
    public function saveStep(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'step_number' => 'required|integer|min:1|max:11',
            'step_data' => 'nullable|array',
        ]);

        $tenant = $request->user()->tenant;
        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'No active tenant.'], 400);
        }

        $updated = $this->onboardingService->updateOnboardingStep(
            $tenant,
            $validated['step_number'],
            $validated['step_data'] ?? []
        );

        return response()->json([
            'success' => true,
            'data' => $updated,
            'message' => "Onboarding step [{$validated['step_number']}] saved.",
        ]);
    }

    /**
     * Get Current Onboarding Status
     */
    public function getStatus(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'No active tenant.'], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'tenant_id' => $tenant->id,
                'name' => $tenant->name,
                'onboarding_status' => $tenant->onboarding_status ?? 'completed',
                'onboarding_step' => $tenant->onboarding_step ?? 11,
                'industry_template_code' => $tenant->industry_template_code,
                'timezone' => $tenant->timezone,
                'branding' => $tenant->branding,
            ],
        ]);
    }
}
