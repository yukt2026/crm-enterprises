<?php

namespace App\Domains\Support\Models;

use App\Domains\CRM\Models\Customer;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerFeedback extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'customer_feedback';

    protected $fillable = [
        'tenant_id',
        'ticket_id',
        'customer_id',
        'csat_score',
        'nps_score',
        'rating_category',
        'feedback_text',
        'channel',
        'submitted_at',
    ];

    protected $casts = [
        'csat_score' => 'integer',
        'nps_score' => 'integer',
        'submitted_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
