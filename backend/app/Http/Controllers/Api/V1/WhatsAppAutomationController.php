<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Tenancy\Services\TenantContext;
use App\Domains\WhatsApp\Models\WhatsAppAutomation;
use App\Domains\WhatsApp\Models\WhatsAppAutomationLog;
use App\Domains\WhatsApp\Services\WhatsAppAutomationService;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppAutomationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected WhatsAppAutomationService $automationService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * List automation journeys
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $automations = WhatsAppAutomation::where('tenant_id', $tenantId)
            ->with(['template', 'phoneNumber'])
            ->orderBy('id', 'desc')
            ->get();

        return $this->successResponse($automations, 'WhatsApp automations retrieved.');
    }

    /**
     * Create automation journey
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'journey_type' => 'required|string|in:new_enquiry_welcome,lead_qualification,appointment_confirmation,payment_reminder,invoice_reminder,renewal_reminder,customer_onboarding,service_update,sales_followup,abandoned_enquiry',
            'trigger_event' => 'required|string|max:100',
            'whatsapp_phone_number_id' => 'required|integer',
            'whatsapp_template_id' => 'required|integer',
            'delay_minutes' => 'nullable|integer|min:0',
            'conditions' => 'nullable|array',
            'stop_conditions' => 'nullable',
            'variable_mappings' => 'nullable|array',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $automation = $this->automationService->createAutomation($tenantId, $validated, $request->user());

        return $this->successResponse($automation, 'WhatsApp automation journey created.', 201);
    }

    /**
     * Get automation journey details
     */
    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $automation = WhatsAppAutomation::where('tenant_id', $tenantId)
            ->with(['template', 'phoneNumber', 'logs' => fn($q) => $q->latest()->limit(20)])
            ->findOrFail($id);

        return $this->successResponse($automation, 'WhatsApp automation details retrieved.');
    }

    /**
     * Update automation journey
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $automation = WhatsAppAutomation::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:150',
            'whatsapp_phone_number_id' => 'nullable|integer',
            'whatsapp_template_id' => 'nullable|integer',
            'delay_minutes' => 'nullable|integer|min:0',
            'conditions' => 'nullable|array',
            'stop_conditions' => 'nullable',
            'variable_mappings' => 'nullable|array',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $automation->update($validated);

        return $this->successResponse($automation, 'WhatsApp automation journey updated.');
    }

    /**
     * Toggle automation active status
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $automation = WhatsAppAutomation::where('tenant_id', $tenantId)->findOrFail($id);
        $automation->update(['is_active' => !$automation->is_active]);

        return $this->successResponse($automation, "Automation status updated.");
    }

    /**
     * Delete automation journey
     */
    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $automation = WhatsAppAutomation::where('tenant_id', $tenantId)->findOrFail($id);
        $automation->delete();

        return $this->successResponse(null, 'Automation journey deleted.');
    }

    /**
     * Manually trigger an event to test the automation pipeline
     */
    public function triggerTest(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'event' => 'required|string',
            'phone_number' => 'required|string',
            'payload' => 'nullable|array',
        ]);

        $logs = $this->automationService->triggerEvent(
            $tenantId,
            $validated['event'],
            array_merge($validated['payload'] ?? [], ['phone' => $validated['phone_number']])
        );

        return $this->successResponse($logs, 'Automation pipeline evaluated and executed.');
    }

    /**
     * Execution logs
     */
    public function logs(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $query = WhatsAppAutomationLog::where('tenant_id', $tenantId)
            ->with(['automation'])
            ->orderBy('id', 'desc');

        if ($request->has('automation_id')) {
            $query->where('automation_id', $request->automation_id);
        }

        $logs = $query->paginate($request->integer('per_page', 25));

        return $this->successResponse($logs, 'Automation execution logs retrieved.');
    }
}
