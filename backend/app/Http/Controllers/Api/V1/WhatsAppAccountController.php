<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Tenancy\Services\TenantContext;
use App\Domains\WhatsApp\Models\WhatsAppAccount;
use App\Domains\WhatsApp\Models\WhatsAppPhoneNumber;
use App\Domains\WhatsApp\Services\WhatsAppAccountService;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppAccountController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected WhatsAppAccountService $accountService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * List WABA accounts
     */
    public function accounts(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $accounts = WhatsAppAccount::where('tenant_id', $tenantId)
            ->with('phoneNumbers')
            ->orderBy('id', 'desc')
            ->get();

        return $this->successResponse($accounts, 'WhatsApp accounts retrieved.');
    }

    /**
     * Create / connect WABA account
     */
    public function storeAccount(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'waba_id' => 'required|string|max:100',
            'app_id' => 'nullable|string|max:100',
            'system_user_token' => 'required|string',
            'webhook_verify_token' => 'nullable|string|max:150',
            'status' => 'nullable|string|in:connected,disconnected,restricted',
            'business_verification_status' => 'nullable|string|in:verified,pending,failed',
        ]);

        $account = $this->accountService->createAccount($tenantId, $validated, $request->user());

        return $this->successResponse($account, 'WhatsApp Business Account onboarded successfully.', 201);
    }

    /**
     * List connected phone numbers
     */
    public function phoneNumbers(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $numbers = WhatsAppPhoneNumber::where('tenant_id', $tenantId)
            ->with(['account', 'defaultUser'])
            ->orderBy('is_default', 'desc')
            ->get();

        return $this->successResponse($numbers, 'Phone numbers retrieved.');
    }

    /**
     * Register / connect a phone number
     */
    public function connectPhoneNumber(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'whatsapp_account_id' => 'required|integer',
            'phone_number_id' => 'required|string|max:100',
            'display_phone_number' => 'required|string|max:50',
            'normalized_number' => 'nullable|string|max:50',
            'verified_name' => 'nullable|string|max:150',
            'is_default' => 'nullable|boolean',
            'default_assigned_user_id' => 'nullable|integer',
        ]);

        $phone = $this->accountService->connectPhoneNumber(
            $tenantId,
            $validated['whatsapp_account_id'],
            $validated,
            $request->user()
        );

        return $this->successResponse($phone, 'Phone number connected successfully.', 201);
    }

    /**
     * Run connectivity diagnostic against Meta API
     */
    public function testConnection(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $result = $this->accountService->testConnection($tenantId, $id);

        return $this->successResponse($result, 'Connection diagnostics completed.');
    }

    /**
     * Update phone number settings (branch, dept, working hours, reply rules, limit tier)
     */
    public function updatePhoneNumber(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'branch_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'verified_name' => 'nullable|string|max:150',
            'messaging_limit_tier' => 'nullable|string|max:50',
            'channel_status' => 'nullable|string|in:active,inactive,warning,restricted',
            'working_hours' => 'nullable|array',
            'default_reply_rules' => 'nullable|array',
            'assigned_team_ids' => 'nullable|array',
            'is_default' => 'nullable|boolean',
            'default_assigned_user_id' => 'nullable|integer',
        ]);

        $phoneNumber = $this->accountService->updatePhoneNumber($tenantId, $id, $validated);

        return $this->successResponse($phoneNumber, 'Phone number settings updated.');
    }

    /**
     * Disconnect / remove phone number
     */
    public function disconnectPhoneNumber(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $phoneNumber = WhatsAppPhoneNumber::where('tenant_id', $tenantId)->findOrFail($id);
        $phoneNumber->update(['channel_status' => 'inactive']);

        return $this->successResponse(null, 'Phone number disconnected successfully.');
    }

    /**
     * Disconnect WABA Account
     */
    public function disconnectAccount(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $account = WhatsAppAccount::where('tenant_id', $tenantId)->findOrFail($id);
        $account->update(['status' => 'disconnected']);

        return $this->successResponse(null, 'WhatsApp Account disconnected.');
    }

    /**
     * Integration logs
     */
    public function integrationLogs(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $logs = \App\Domains\WhatsApp\Models\WhatsAppIntegrationLog::where('tenant_id', $tenantId)
            ->orderBy('id', 'desc')
            ->paginate($request->integer('per_page', 25));

        return $this->successResponse($logs, 'WhatsApp integration logs retrieved.');
    }
}
