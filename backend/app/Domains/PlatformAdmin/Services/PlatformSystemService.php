<?php

namespace App\Domains\PlatformAdmin\Services;

use App\Domains\PlatformAdmin\Models\PlatformAuditLog;
use App\Domains\PlatformAdmin\Models\PlatformFeatureFlag;
use App\Domains\PlatformAdmin\Models\PlatformSystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformSystemService
{
    public function __construct(
        protected PlatformAdminService $adminService
    ) {}

    /**
     * Get Queue Statistics & Failed Jobs
     */
    public function getQueueStats(): array
    {
        $failedJobs = [];
        try {
            $failedJobs = DB::table('failed_jobs')
                ->orderBy('failed_at', 'desc')
                ->limit(20)
                ->get();
        } catch (\Throwable $e) {}

        $pendingJobsCount = 0;
        try {
            $pendingJobsCount = DB::table('jobs')->count();
        } catch (\Throwable $e) {}

        return [
            'pending_jobs_count' => $pendingJobsCount,
            'failed_jobs_count' => count($failedJobs),
            'failed_jobs' => $failedJobs,
        ];
    }

    /**
     * Retry a Failed Queue Job
     */
    public function retryFailedJob(string $jobId, User $admin, ?string $ip = null, ?string $userAgent = null): bool
    {
        Artisan::call('queue:retry', ['id' => [$jobId]]);

        $this->adminService->logAudit(
            $admin,
            'queue.retry_job',
            'QueueJob',
            $jobId,
            [],
            $ip,
            $userAgent
        );

        return true;
    }

    /**
     * Purge All Failed Queue Jobs
     */
    public function purgeFailedJobs(User $admin, ?string $ip = null, ?string $userAgent = null): int
    {
        $count = DB::table('failed_jobs')->count();
        Artisan::call('queue:flush');

        $this->adminService->logAudit(
            $admin,
            'queue.purge_failed_jobs',
            'QueueJob',
            null,
            ['purged_count' => $count],
            $ip,
            $userAgent
        );

        return $count;
    }

    /**
     * Get Feature Flags (seeds defaults if empty)
     */
    public function getFeatureFlags(): Collection
    {
        $flags = PlatformFeatureFlag::orderBy('flag_key')->get();
        if ($flags->isEmpty()) {
            $this->seedDefaultFeatureFlags();
            $flags = PlatformFeatureFlag::orderBy('flag_key')->get();
        }
        return $flags;
    }

    /**
     * Seed Default Platform Feature Flags
     */
    public function seedDefaultFeatureFlags(): void
    {
        $defaults = [
            [
                'flag_key' => 'ai_copilot_chat',
                'name' => 'Enterprise AI Co-pilot Chat',
                'description' => 'Enables embedded sidebar generative assistant across CRM views.',
                'is_enabled' => true,
                'rules' => ['rollout_percentage' => 100],
            ],
            [
                'flag_key' => 'multilingual_voice_agents',
                'name' => 'Multilingual Voice AI Agents',
                'description' => 'Real-time multi-language translation and synthesized speech over Exotel/Twilio.',
                'is_enabled' => true,
                'rules' => ['rollout_percentage' => 100],
            ],
            [
                'flag_key' => 'whatsapp_flow_builder',
                'name' => 'Visual WhatsApp Flow Builder',
                'description' => 'Drag-and-drop Meta interactive flow designer.',
                'is_enabled' => false,
                'rules' => ['plan_whitelist' => ['enterprise']],
            ],
            [
                'flag_key' => 'high_frequency_webhooks',
                'name' => 'High-Frequency Real-Time Webhooks',
                'description' => 'Permits outbound webhooks at sub-second latency for enterprise integrations.',
                'is_enabled' => true,
                'rules' => ['rollout_percentage' => 100],
            ],
        ];

        foreach ($defaults as $d) {
            PlatformFeatureFlag::firstOrCreate(['flag_key' => $d['flag_key']], $d);
        }
    }

    /**
     * Toggle or Update Feature Flag
     */
    public function updateFeatureFlag(string $flagKey, array $data, User $admin, ?string $ip = null, ?string $userAgent = null): PlatformFeatureFlag
    {
        $flag = PlatformFeatureFlag::where('flag_key', $flagKey)->firstOrFail();
        $flag->update([
            'is_enabled' => isset($data['is_enabled']) ? (bool) $data['is_enabled'] : $flag->is_enabled,
            'name' => $data['name'] ?? $flag->name,
            'description' => $data['description'] ?? $flag->description,
            'rules' => $data['rules'] ?? $flag->rules,
        ]);

        $this->adminService->logAudit(
            $admin,
            'feature_flag.update',
            'PlatformFeatureFlag',
            $flagKey,
            ['is_enabled' => $flag->is_enabled],
            $ip,
            $userAgent
        );

        return $flag;
    }

    /**
     * Get Platform Maintenance Mode Status
     */
    public function getMaintenanceMode(): array
    {
        $setting = PlatformSystemSetting::where('setting_key', 'maintenance_mode')->first();
        if (!$setting) {
            return [
                'is_enabled' => false,
                'message' => 'System is operational.',
                'bypass_secret' => null,
                'updated_at' => null,
            ];
        }

        $val = $setting->setting_value ?? [];
        return [
            'is_enabled' => (bool) ($val['is_enabled'] ?? false),
            'message' => (string) ($val['message'] ?? 'Platform scheduled maintenance in progress.'),
            'bypass_secret' => $val['bypass_secret'] ?? null,
            'updated_at' => $setting->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Set Platform Maintenance Mode
     */
    public function setMaintenanceMode(bool $enabled, ?string $message, ?string $bypassSecret, User $admin, ?string $ip = null, ?string $userAgent = null): array
    {
        $secret = $bypassSecret ?: Str::random(32);
        $val = [
            'is_enabled' => $enabled,
            'message' => $message ?: 'System scheduled maintenance is currently in progress. We will be back online shortly.',
            'bypass_secret' => $secret,
            'enabled_at' => $enabled ? Carbon::now()->toIso8601String() : null,
        ];

        PlatformSystemSetting::updateOrCreate(
            ['setting_key' => 'maintenance_mode'],
            [
                'setting_value' => $val,
                'description' => 'Platform-wide maintenance mode status and bypass key',
                'updated_by' => $admin->id,
            ]
        );

        $this->adminService->logAudit(
            $admin,
            $enabled ? 'system.maintenance_enable' : 'system.maintenance_disable',
            'PlatformSystemSetting',
            'maintenance_mode',
            ['message' => $val['message']],
            $ip,
            $userAgent
        );

        return $val;
    }

    /**
     * Get Platform Audit Logs with Filtering
     */
    public function getAuditLogs(array $filters = [], int $perPage = 50)
    {
        $query = PlatformAuditLog::with('user:id,name,email');

        if (!empty($filters['action'])) {
            $query->where('action', 'like', "%{$filters['action']}%");
        }

        if (!empty($filters['admin_id'])) {
            $query->where('user_id', $filters['admin_id']);
        }

        if (!empty($filters['target_type'])) {
            $query->where('target_type', $filters['target_type']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }
}
