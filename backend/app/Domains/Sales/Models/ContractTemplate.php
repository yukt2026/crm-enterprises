<?php

namespace App\Domains\Sales\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractTemplate extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'contract_templates';

    protected $fillable = [
        'tenant_id',
        'name',
        'type', // proposal, contract, nda, sla
        'content',
        'variables',
        'is_active',
        'created_by_user_id',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
