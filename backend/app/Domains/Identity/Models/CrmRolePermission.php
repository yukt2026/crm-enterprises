<?php

namespace App\Domains\Identity\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmRolePermission extends Model
{
    use HasFactory;

    protected $table = 'crm_role_permissions';

    protected $fillable = [
        'role_id',
        'module',
        'action',
        'data_scope',
        'scope_constraints',
    ];

    protected $casts = [
        'scope_constraints' => 'array',
    ];

    /**
     * Parent role relationship.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(CrmRole::class, 'role_id');
    }
}
