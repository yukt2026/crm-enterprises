<?php

namespace App\Domains\Voice\Models;

use App\Models\User;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallRecordingAccessLog extends Model
{
    protected $table = 'call_recording_access_logs';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'voice_call_id',
        'user_id',
        'action',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(VoiceCall::class, 'voice_call_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
