<?php

namespace App\Domains\AuditSecurity\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

class AuditLog extends Model
{
    use HasFactory, BelongsToTenant;

    public $timestamps = false; // Only created_at exists, no updated_at

    protected $fillable = [
        'uuid',
        'tenant_id',
        'user_id',
        'user_name',
        'role',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public static function boot()
    {
        parent::boot();

        // Enforce immutability: Audit records can never be updated or deleted
        static::updating(function () {
            throw new RuntimeException('Audit records are immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new RuntimeException('Audit records are immutable and cannot be deleted.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
