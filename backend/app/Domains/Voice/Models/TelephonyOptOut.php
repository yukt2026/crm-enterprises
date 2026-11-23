<?php

namespace App\Domains\Voice\Models;

use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelephonyOptOut extends Model
{
    protected $table = 'telephony_opt_outs';

    protected $fillable = [
        'tenant_id',
        'phone_number',
        'opt_out_type',
        'reason',
        'opted_out_at',
    ];

    protected $casts = [
        'opted_out_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
