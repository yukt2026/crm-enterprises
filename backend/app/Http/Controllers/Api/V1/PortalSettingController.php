<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\CRM\Models\Customer;
use App\Domains\CustomerPortal\Models\CustomerAccount;
use App\Domains\CustomerPortal\Models\PortalSetting;
use App\Domains\CustomerPortal\Services\CustomerPortalService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PortalSettingController extends Controller
{
    public function __construct(
        protected CustomerPortalService $portalService
    ) {}

    protected function getTenantId(): int
    {
        return TenantContext::getTenantId() ?? 1;
    }

    /**
     * Get Portal Settings for Tenant
     */
    public function show(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $settings = $this->portalService->getPortalSettings($tenantId);

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Update Portal Settings
     */
    public function update(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $settings = $this->portalService->getPortalSettings($tenantId);

        $validated = $request->validate([
            'is_portal_enabled' => 'nullable|boolean',
            'allow_self_registration' => 'nullable|boolean',
            'allow_invoice_access' => 'nullable|boolean',
            'allow_payments' => 'nullable|boolean',
            'allow_project_tracking' => 'nullable|boolean',
            'allow_ticket_creation' => 'nullable|boolean',
            'allow_appointment_booking' => 'nullable|boolean',
            'allow_document_downloads' => 'nullable|boolean',
            'public_booking_page_enabled' => 'nullable|boolean',
            'booking_slug' => 'nullable|string|max:100',
            'company_logo_url' => 'nullable|string|max:500',
            'brand_color' => 'nullable|string|max:20',
            'booking_terms' => 'nullable|string',
            'booking_rules' => 'nullable|array',
        ]);

        $settings->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Portal settings updated successfully.',
            'data' => $settings->fresh(),
        ]);
    }

    /**
     * Generate / Send Customer Portal Account Invitation
     */
    public function inviteCustomer(Request $request, int $customerId): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $customer = Customer::where('tenant_id', $tenantId)->findOrFail($customerId);

        $account = CustomerAccount::firstOrNew([
            'tenant_id' => $tenantId,
            'customer_id' => $customer->id,
        ]);

        $inviteToken = Str::random(40);
        $account->email = $customer->email;
        $account->phone = $customer->phone;
        if (!$account->password) {
            $account->password = Hash::make(Str::random(16));
        }
        $account->invite_token = $inviteToken;
        $account->invite_expires_at = Carbon::now()->addDays(7);
        $account->is_active = true;
        $account->save();

        return response()->json([
            'success' => true,
            'message' => 'Customer portal invitation generated successfully.',
            'data' => [
                'customer' => $customer,
                'account' => $account,
                'invite_token' => $inviteToken,
                'invite_url' => url("/portal/accept-invite?token={$inviteToken}"),
                'expires_at' => $account->invite_expires_at,
            ],
        ]);
    }
}
