<?php

namespace App\Domains\Workflow\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowAutomationLimit extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'workflow_automation_limits';

    protected $fillable = [
        'tenant_id',
        'max_active_workflows',
        'max_monthly_executions',
        'current_month_executions',
        'max_external_api_calls',
        'max_whatsapp_actions',
        'max_email_actions',
        'max_sms_actions',
        'max_ai_actions',
        'max_delayed_tasks',
        'last_reset_at',
    ];

    protected $casts = [
        'max_active_workflows' => 'integer',
        'max_monthly_executions' => 'integer',
        'current_month_executions' => 'integer',
        'max_external_api_calls' => 'integer',
        'max_whatsapp_actions' => 'integer',
        'max_email_actions' => 'integer',
        'max_sms_actions' => 'integer',
        'max_ai_actions' => 'integer',
        'max_delayed_tasks' => 'integer',
        'last_reset_at' => 'datetime',
    ];

    /**
     * Get or initialize default limits for a tenant.
     */
    public static function getForTenant(int $tenantId): self
    {
        return static::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'max_active_workflows' => 50,
                'max_monthly_executions' => 50000,
                'current_month_executions' => 0,
                'max_external_api_calls' => 5000,
                'max_whatsapp_actions' => 10000,
                'max_email_actions' => 25000,
                'max_sms_actions' => 10000,
                'max_ai_actions' => 2000,
                'max_delayed_tasks' => 1000,
                'last_reset_at' => now(),
            ]
        );
    }
}
