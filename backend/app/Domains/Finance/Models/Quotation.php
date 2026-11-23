<?php

namespace App\Domains\Finance\Models;

use App\Domains\CRM\Models\Company;
use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Deal;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $appends = ['quotation_number'];

    protected $fillable = [
        'tenant_id',
        'quote_number',
        'quotation_number',
        'version',
        'parent_quotation_id',
        'currency',
        'deal_id',
        'lead_id',
        'contact_id',
        'company_id',
        'customer_id',
        'subtotal',
        'discount_type',
        'discount_rate',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'is_tax_inclusive',
        'total_amount',
        'status', // draft, sent, viewed, accepted, rejected, expired
        'template_name',
        'approval_status',
        'approved_by_user_id',
        'approved_at',
        'rejection_reason',
        'converted_to_invoice_id',
        'converted_to_invoice_at',
        'sent_via',
        'sent_at',
        'customer_action_ip',
        'customer_action_notes',
        'valid_until',
        'terms_conditions',
        'notes',
        'accepted_at',
        'rejected_at',
        'viewed_at',
    ];

    public function getQuotationNumberAttribute(): ?string
    {
        return $this->attributes['quote_number'] ?? ($this->attributes['quotation_number'] ?? null);
    }

    public function setQuotationNumberAttribute(?string $value): void
    {
        $this->attributes['quote_number'] = $value;
    }

    protected $casts = [
        'version' => 'integer',
        'subtotal' => 'decimal:2',
        'discount_rate' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'is_tax_inclusive' => 'boolean',
        'total_amount' => 'decimal:2',
        'valid_until' => 'date',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'viewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'converted_to_invoice_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by_user_id');
    }

    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_to_invoice_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function parentQuotation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_quotation_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_quotation_id')->orderBy('version', 'desc');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', 'accepted');
    }
}
