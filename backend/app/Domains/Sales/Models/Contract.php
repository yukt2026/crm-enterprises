<?php

namespace App\Domains\Sales\Models;

use App\Domains\CRM\Models\Company;
use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Deal;
use App\Domains\Finance\Models\Quotation;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'contracts';

    protected $fillable = [
        'tenant_id',
        'deal_id',
        'quotation_id',
        'customer_id',
        'company_id',
        'contact_id',
        'title',
        'contract_type', // proposal, contract, nda, sla
        'contract_number',
        'status', // draft, pending_approval, sent, signed, rejected, active, expired, terminated
        'document_version',
        'terms_conditions',
        'contract_value',
        'start_date',
        'end_date',
        'auto_renew',
        'renewal_notice_days',
        'e_signature_provider', // aadhaar_esign, docusign, internal_otp, clickwrap
        'signature_status', // unsigned, sent_for_signature, signed, declined
        'signer_name',
        'signer_email',
        'signed_at',
        'audit_trail_hash',
        'created_by_user_id',
    ];

    protected $casts = [
        'document_version' => 'integer',
        'contract_value' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'auto_renew' => 'boolean',
        'renewal_notice_days' => 'integer',
        'signed_at' => 'datetime',
    ];

    protected $appends = ['audit_hash'];

    public function getAuditHashAttribute(): ?string
    {
        return $this->attributes['audit_trail_hash'] ?? null;
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
