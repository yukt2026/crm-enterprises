<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\HR\Services\HrNotificationService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HrReminderController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected HrNotificationService $notificationService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * Get active HR alerts, reminders and upcoming milestones.
     */
    public function getReminders(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $reminders = $this->notificationService->getActiveReminders($tenantId);

        return response()->json([
            'status' => 'success',
            'data' => $reminders,
        ]);
    }
}
