<?php

namespace App\Domains\HR\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    use HasFactory, BelongsToTenant;

    protected $attributes = [
        'is_verified' => false,
    ];

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'document_type',
        'title',
        'document_number',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'issue_date',
        'expiry_date',
        'is_verified',
        'verified_by_user_id',
        'verified_at',
        'notes',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
