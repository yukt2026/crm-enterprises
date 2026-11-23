<?php

namespace App\Domains\WhatsApp\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppOptOut extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'whatsapp_opt_outs';

    protected $fillable = [
        'tenant_id',
        'phone_number',
        'opt_out_type', // customer_stop, manual, compliance
        'reason',
        'opted_out_at',
    ];

    protected $casts = [
        'opted_out_at' => 'datetime',
    ];
}
