<?php

namespace App\Domains\Finance\Models;

use App\Domains\CRM\Models\Company;
use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Deal;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'project_id',
        'invoice_number',
        'quotation_id',
        'deal_id',
        'customer_id',
        'contact_id',
        'company_id',
        'currency',
        'subtotal',
        'discount_type',
        'discount_rate',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'shipping_amount',
        'is_tax_inclusive',
        'total_amount',
        'paid_amount',
        'outstanding_amount',
        'status', // draft, issued, partially_paid, paid, overdue, void, cancelled
        'due_date',
        'payment_terms',
        'billing_name',
        'billing_email',
        'billing_phone',
        'billing_address',
        'billing_tax_id',
        'notes',
        'issued_at',
        'paid_at',
        'voided_at',
        'razorpay_order_id',
        'razorpay_payment_link',
        'promise_to_pay_date',
        'customer_commitment_notes',
        'is_disputed',
        'dispute_reason',
        'dispute_status',
        'collection_user_id',
        'recovery_status',
        // GST & Indian Compliance
        'gst_registration_id',
        'document_type',
        'seller_gstin',
        'seller_legal_name',
        'seller_state_code',
        'buyer_gstin',
        'buyer_legal_name',
        'buyer_trade_name',
        'buyer_state_code',
        'buyer_gst_treatment',
        'place_of_supply_state_code',
        'place_of_supply_state_name',
        'supply_type',
        'is_reverse_charge',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'cess_amount',
        'round_off_amount',
        'amount_in_words',
        'original_invoice_id',
        'original_invoice_number',
        'original_invoice_date',
        'reason_for_issuance',
        'export_type',
        'shipping_bill_number',
        'shipping_bill_date',
        'port_code',
        'bank_name',
        'bank_account_number',
        'bank_ifsc',
        'bank_branch',
        'terms_and_conditions',
        // E-Invoice
        'irn',
        'ack_number',
        'ack_date',
        'signed_qr_code',
        'signed_invoice',
        'einvoice_status',
        'einvoice_error_logs',
        'einvoice_cancelled_at',
        'einvoice_cancel_reason',
        // E-Way Bill
        'eway_bill_number',
        'eway_bill_date',
        'eway_bill_valid_until',
        'distance_km',
        'transporter_id',
        'transporter_name',
        'transporter_doc_no',
        'vehicle_number',
        'vehicle_type',
        'transport_mode',
        'eway_bill_status',
        'eway_bill_cancel_reason',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_rate' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'is_tax_inclusive' => 'boolean',
        'is_reverse_charge' => 'boolean',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'cess_amount' => 'decimal:2',
        'round_off_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
        'due_date' => 'date',
        'promise_to_pay_date' => 'date',
        'original_invoice_date' => 'date',
        'shipping_bill_date' => 'date',
        'is_disputed' => 'boolean',
        'issued_at' => 'datetime',
        'paid_at' => 'datetime',
        'ack_date' => 'datetime',
        'einvoice_cancelled_at' => 'datetime',
        'eway_bill_date' => 'datetime',
        'eway_bill_valid_until' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\CRM\Models\Branch::class, 'branch_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->where('status', 'success');
    }

    public function allPayments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function financeTransactions(): HasMany
    {
        return $this->hasMany(FinanceTransaction::class);
    }

    public function paymentLinks(): HasMany
    {
        return $this->hasMany(PaymentLink::class, 'payable_id')->where('payable_type', self::class);
    }

    public function collectionUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collection_user_id');
    }

    public function gstRegistration(): BelongsTo
    {
        return $this->belongsTo(GstRegistration::class, 'gst_registration_id');
    }

    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_invoice_id');
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query->whereIn('status', ['issued', 'partially_paid', 'overdue']);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('outstanding_amount', '>', 0)->whereNotIn('status', ['void', 'cancelled', 'draft']);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('due_date', '<', now()->toDateString())
            ->where('outstanding_amount', '>', 0)
            ->whereNotIn('status', ['void', 'cancelled', 'draft', 'paid']);
    }
}
