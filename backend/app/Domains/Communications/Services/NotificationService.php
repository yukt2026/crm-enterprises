<?php

namespace App\Domains\Communications\Services;

use App\Domains\Communications\Models\NotificationPreference;
use App\Domains\Identity\Models\Notification;
use App\Domains\Tenancy\Services\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Supported notification event keys
     */
    public const TYPES = [
        'lead.assigned' => 'New Lead Assignment',
        'lead.reassigned' => 'Lead Reassignment',
        'followup.due' => 'Follow-up Due / Overdue',
        'message.new' => 'New Inbound Message',
        'call.missed' => 'Missed Call Alert',
        'ai.handover' => 'AI Agent Handover to Human Rep',
        'ticket.new' => 'New Support Ticket Created',
        'task.assigned' => 'Task Assignment & Deadline',
        'meeting.reminder' => 'Upcoming Meeting Reminder',
        'leave.status' => 'Leave Approval / Rejection Update',
        'invoice.event' => 'Invoice Created / Payment Received',
        'workflow.failed' => 'Automation Workflow Failure',
        'subscription.warning' => 'Subscription Limit Warning',
        'integration.failed' => 'Third-Party Integration Error',
    ];

    /**
     * Dispatch notification to a user respecting preferences
     */
    public function notify(
        User $recipient,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        array $data = []
    ): ?Notification {
        $tenantId = TenantContext::getTenantId() ?? $recipient->current_tenant_id ?? $recipient->tenant_id;

        // Resolve user's notification preferences
        $pref = NotificationPreference::where('user_id', $recipient->id)
            ->where('notification_type', $type)
            ->first();

        $inAppEnabled = $pref ? $pref->in_app_enabled : true;
        $emailEnabled = $pref ? $pref->email_enabled : true;

        $createdNotification = null;

        // 1. In-App Notification
        if ($inAppEnabled) {
            $createdNotification = Notification::create([
                'tenant_id' => $tenantId,
                'user_id' => $recipient->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'action_url' => $actionUrl,
                'data' => $data,
                'channel' => 'in_app',
                'is_read' => false,
            ]);
        }

        // 2. Email Notification Log / Dispatch
        if ($emailEnabled && $recipient->email) {
            Log::info("Notification dispatched via email to {$recipient->email}", [
                'type' => $type,
                'title' => $title,
                'recipient_id' => $recipient->id,
                'tenant_id' => $tenantId,
            ]);
        }

        return $createdNotification;
    }

    /**
     * Get or initialize all default preferences for a user
     */
    public function getUserPreferences(User $user): array
    {
        $tenantId = TenantContext::getTenantId() ?? $user->current_tenant_id ?? $user->tenant_id;
        $existing = NotificationPreference::where('user_id', $user->id)
            ->get()
            ->keyBy('notification_type');

        $result = [];
        foreach (self::TYPES as $typeKey => $typeLabel) {
            if ($existing->has($typeKey)) {
                $pref = $existing->get($typeKey);
                $result[] = [
                    'notification_type' => $typeKey,
                    'label' => $typeLabel,
                    'in_app_enabled' => $pref->in_app_enabled,
                    'email_enabled' => $pref->email_enabled,
                    'push_enabled' => $pref->push_enabled,
                ];
            } else {
                $result[] = [
                    'notification_type' => $typeKey,
                    'label' => $typeLabel,
                    'in_app_enabled' => true,
                    'email_enabled' => true,
                    'push_enabled' => false,
                ];
            }
        }

        return $result;
    }

    /**
     * Save/update user preferences
     */
    public function savePreferences(User $user, array $preferences): void
    {
        $tenantId = TenantContext::getTenantId() ?? $user->current_tenant_id ?? $user->tenant_id;

        foreach ($preferences as $pref) {
            if (isset($pref['notification_type'])) {
                NotificationPreference::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'notification_type' => $pref['notification_type'],
                    ],
                    [
                        'tenant_id' => $tenantId,
                        'in_app_enabled' => $pref['in_app_enabled'] ?? true,
                        'email_enabled' => $pref['email_enabled'] ?? true,
                        'push_enabled' => $pref['push_enabled'] ?? false,
                    ]
                );
            }
        }
    }
}
