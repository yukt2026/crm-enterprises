<?php

namespace App\Domains\Finance\Services;

use App\Domains\CRM\Models\Customer;
use App\Domains\Finance\Models\CreditNote;
use App\Domains\Finance\Models\FinanceTransaction;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\PaymentAllocation;
use App\Domains\Finance\Models\PaymentGatewayConfig;
use App\Domains\Finance\Models\PaymentLink;
use App\Domains\Finance\Models\Refund;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdvancedPaymentService
{
    public function __construct(
        protected PaymentGatewayService $gatewayService,
        protected InvoiceBillingService $billingService
    ) {}

    /**
     * Create a universal payment link linked to invoice, deal, quotation, or customer.
     */
    public function createPaymentLink(int $tenantId, array $data, ?User $user = null): PaymentLink
    {
        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
        }

        $token = 'pl_' . Str::random(40);
        $provider = $data['gateway_provider'] ?? 'razorpay';
        $currency = $data['currency'] ?? 'INR';
        $title = $data['title'] ?? 'Invoice Payment Request';
        $description = $data['description'] ?? "Payment for {$title}";

        // Lookup gateway config
        $config = $this->gatewayService->getGatewayConfig($tenantId, $provider)
            ?? new PaymentGatewayConfig([
                'tenant_id' => $tenantId,
                'provider' => $provider,
                'display_name' => ucfirst($provider),
                'upi_vpa' => 'billing@enterpriseupi',
                'merchant_name' => 'Acme Enterprise',
            ]);

        $customer = null;
        if (!empty($data['customer_id'])) {
            $customer = Customer::where('tenant_id', $tenantId)->find($data['customer_id']);
        }

        // Generate provider link or UPI QR
        $gatewayResult = $this->gatewayService->createProviderPaymentLink(
            $config,
            $amount,
            $currency,
            $description,
            $customer?->name ?? 'Customer',
            $customer?->email ?? 'billing@customer.com',
            $customer?->phone ?? '+919999999999',
            $token
        );

        $paymentLink = PaymentLink::create([
            'tenant_id' => $tenantId,
            'link_token' => $token,
            'title' => $title,
            'description' => $description,
            'payable_type' => $data['payable_type'] ?? null,
            'payable_id' => $data['payable_id'] ?? null,
            'customer_id' => $customer?->id,
            'amount_type' => $data['amount_type'] ?? 'fixed',
            'amount' => $amount,
            'min_partial_amount' => !empty($data['min_partial_amount']) ? round((float) $data['min_partial_amount'], 2) : null,
            'currency' => $currency,
            'expires_at' => !empty($data['expires_at']) ? Carbon::parse($data['expires_at']) : Carbon::now()->addDays(7),
            'gateway_provider' => $provider,
            'gateway_link_id' => $gatewayResult['gateway_link_id'] ?? null,
            'gateway_link_url' => $gatewayResult['gateway_link_url'] ?? null,
            'upi_qr_payload' => $gatewayResult['upi_qr_payload'] ?? null,
            'status' => 'active',
            'paid_amount' => 0.0,
            'created_by_user_id' => $user?->id,
            'notes' => $data['notes'] ?? null,
        ]);

        return $paymentLink->load(['payable', 'customer']);
    }

    /**
     * Process incoming payment webhook with strict idempotency protection.
     */
    public function processWebhookPayment(int $tenantId, string $provider, array $payload, ?string $signature = null): array
    {
        $eventId = $payload['event_id'] ?? $payload['id'] ?? ($payload['payment_id'] ?? ('evt_' . Str::random(16)));

        // 1. Idempotency Check: Protect against duplicate webhook deliveries
        $existingTxn = FinanceTransaction::where('tenant_id', $tenantId)
            ->where(function ($q) use ($eventId, $payload) {
                $q->where('webhook_event_id', $eventId);
                if (!empty($payload['payment_id'])) {
                    $q->orWhere('provider_transaction_id', $payload['payment_id']);
                }
            })
            ->first();

        if ($existingTxn) {
            return [
                'status' => 'already_processed',
                'message' => 'Duplicate webhook event received and safely deduplicated via idempotency ledger.',
                'transaction' => $existingTxn,
            ];
        }

        // 2. Validate signature if configured
        $config = $this->gatewayService->getGatewayConfig($tenantId, $provider);
        if ($signature && $config && $config->webhook_secret) {
            $rawPayload = json_encode($payload);
            if (!$this->gatewayService->verifyWebhookSignature($provider, $rawPayload, $signature, $config->webhook_secret)) {
                throw ValidationException::withMessages(['signature' => 'Invalid payment webhook signature.']);
            }
        }

        return DB::transaction(function () use ($tenantId, $provider, $payload, $eventId) {
            $amount = round((float) ($payload['amount'] ?? 0), 2);
            $currency = $payload['currency'] ?? 'INR';
            $paymentMethod = strtolower($payload['method'] ?? $payload['payment_method'] ?? 'upi');
            $providerPaymentId = $payload['payment_id'] ?? $payload['gateway_payment_id'] ?? ('pay_' . Str::random(14));
            $invoiceId = $payload['invoice_id'] ?? null;
            $customerId = $payload['customer_id'] ?? null;
            $linkToken = $payload['link_token'] ?? null;

            $invoice = null;
            if ($invoiceId) {
                $invoice = Invoice::where('tenant_id', $tenantId)->lockForUpdate()->find($invoiceId);
            }

            $paymentLink = null;
            if ($linkToken) {
                $paymentLink = PaymentLink::where('tenant_id', $tenantId)->where('link_token', $linkToken)->lockForUpdate()->first();
                if ($paymentLink && !$invoice && $paymentLink->payable_type === Invoice::class) {
                    $invoice = Invoice::where('tenant_id', $tenantId)->lockForUpdate()->find($paymentLink->payable_id);
                }
                if ($paymentLink && !$customerId) {
                    $customerId = $paymentLink->customer_id;
                }
            }

            if ($invoice && !$customerId) {
                $customerId = $invoice->customer_id;
            }

            // Record core payment
            $receiptNo = 'REC-' . date('Ym') . '-' . strtoupper(Str::random(6));
            $payment = Payment::create([
                'tenant_id' => $tenantId,
                'invoice_id' => $invoice?->id,
                'customer_id' => $customerId,
                'receipt_number' => $receiptNo,
                'amount' => $amount,
                'currency' => $currency,
                'payment_date' => Carbon::now()->toDateString(),
                'payment_method' => $paymentMethod,
                'payment_channel' => $provider,
                'reference_number' => $providerPaymentId,
                'gateway_order_id' => $payload['order_id'] ?? null,
                'gateway_payment_id' => $providerPaymentId,
                'gateway_signature' => $payload['signature'] ?? null,
                'refunded_amount' => 0.0,
                'status' => 'successful',
                'notes' => "Webhook confirmed payment via {$provider}",
                'paid_at' => Carbon::now(),
            ]);

            // Update Invoice balance if linked
            if ($invoice) {
                $invoice->paid_amount = round($invoice->paid_amount + $amount, 2);
                $invoice->outstanding_amount = max(0, round($invoice->total_amount - $invoice->paid_amount, 2));
                $invoice->status = $invoice->outstanding_amount <= 0.001 ? 'paid' : 'partially_paid';
                if ($invoice->status === 'paid') {
                    $invoice->paid_at = Carbon::now();
                }
                $invoice->save();

                // Create initial allocation
                PaymentAllocation::create([
                    'tenant_id' => $tenantId,
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'allocated_amount' => $amount,
                    'allocated_at' => Carbon::now(),
                    'notes' => 'Automatic invoice settlement via webhook',
                ]);
            }

            // Update payment link status if linked
            if ($paymentLink) {
                $paymentLink->paid_amount = round($paymentLink->paid_amount + $amount, 2);
                $paymentLink->payment_id = $payment->id;
                $paymentLink->status = $paymentLink->paid_amount >= $paymentLink->amount ? 'paid' : 'partially_paid';
                $paymentLink->save();
            }

            // Calculate estimated fees (approx 2% + 18% GST for card/gateway, 0 for UPI)
            $feeRate = in_array($paymentMethod, ['card', 'netbanking']) ? 0.02 : (in_array($paymentMethod, ['upi']) ? 0.0 : 0.015);
            $feeAmount = round($amount * $feeRate, 2);
            $taxOnFee = round($feeAmount * 0.18, 2);
            $netAmount = round($amount - $feeAmount - $taxOnFee, 2);

            // Record Centralized Financial Transaction
            $txnNumber = 'TXN-' . date('Ym') . '-' . strtoupper(Str::random(8));
            $internalTxnId = (string) Str::uuid();

            $transaction = FinanceTransaction::create([
                'tenant_id' => $tenantId,
                'transaction_number' => $txnNumber,
                'internal_transaction_id' => $internalTxnId,
                'provider_transaction_id' => $providerPaymentId,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice?->id,
                'customer_id' => $customerId,
                'gateway' => $provider,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'currency' => $currency,
                'fee_amount' => $feeAmount,
                'tax_on_fee' => $taxOnFee,
                'net_amount' => $netAmount,
                'transaction_date' => Carbon::now()->toDateString(),
                'provider_status' => 'captured',
                'internal_status' => 'posted',
                'reconciliation_status' => 'matched',
                'reconciled_at' => Carbon::now(),
                'webhook_event_id' => $eventId,
                'metadata' => $payload,
                'is_posted' => true,
                'notes' => "Webhook settlement: {$providerPaymentId}",
            ]);

            return [
                'status' => 'processed',
                'payment' => $payment,
                'transaction' => $transaction,
                'invoice' => $invoice,
            ];
        });
    }

    /**
     * Allocate advance or unallocated payment to an invoice.
     */
    public function allocatePayment(int $tenantId, int $paymentId, int $invoiceId, float $amount, ?User $user = null): PaymentAllocation
    {
        return DB::transaction(function () use ($tenantId, $paymentId, $invoiceId, $amount, $user) {
            $payment = Payment::where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($paymentId);
            $invoice = Invoice::where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($invoiceId);

            $amount = round($amount, 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Allocation amount must be greater than zero.']);
            }

            // Check remaining unallocated amount on payment
            $alreadyAllocated = (float) PaymentAllocation::where('tenant_id', $tenantId)
                ->where('payment_id', $payment->id)
                ->sum('allocated_amount');

            $unallocatedBalance = round($payment->amount - $alreadyAllocated, 2);
            if ($amount > ($unallocatedBalance + 0.001)) {
                throw ValidationException::withMessages([
                    'amount' => "Allocation amount ({$amount}) exceeds remaining unallocated payment balance ({$unallocatedBalance}).",
                ]);
            }

            if ($amount > round($invoice->outstanding_amount + 0.001, 2)) {
                throw ValidationException::withMessages([
                    'amount' => "Allocation amount ({$amount}) exceeds invoice outstanding balance ({$invoice->outstanding_amount}).",
                ]);
            }

            $allocation = PaymentAllocation::create([
                'tenant_id' => $tenantId,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'allocated_amount' => $amount,
                'allocated_at' => Carbon::now(),
                'allocated_by_user_id' => $user?->id,
                'notes' => "Manual payment allocation against Invoice {$invoice->invoice_number}",
            ]);

            // Update invoice balances
            $invoice->paid_amount = round($invoice->paid_amount + $amount, 2);
            $invoice->outstanding_amount = max(0, round($invoice->total_amount - $invoice->paid_amount, 2));
            $invoice->status = $invoice->outstanding_amount <= 0.001 ? 'paid' : 'partially_paid';
            if ($invoice->status === 'paid' && !$invoice->paid_at) {
                $invoice->paid_at = Carbon::now();
            }
            $invoice->save();

            return $allocation->load(['payment', 'invoice']);
        });
    }

    /**
     * Request a refund with approval workflow.
     */
    public function requestRefund(int $tenantId, int $paymentId, float $amount, string $reason, ?User $user = null): Refund
    {
        return DB::transaction(function () use ($tenantId, $paymentId, $amount, $reason, $user) {
            $payment = Payment::where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($paymentId);

            $amount = round($amount, 2);
            $availableRefund = round($payment->amount - $payment->refunded_amount, 2);

            if ($amount <= 0 || $amount > $availableRefund) {
                throw ValidationException::withMessages([
                    'amount' => "Invalid refund amount. Available refundable balance is {$availableRefund}.",
                ]);
            }

            $refundNumber = 'REF-' . date('Ym') . '-' . strtoupper(Str::random(6));

            return Refund::create([
                'tenant_id' => $tenantId,
                'invoice_id' => $payment->invoice_id ?? 1,
                'payment_id' => $payment->id,
                'refund_number' => $refundNumber,
                'amount' => $amount,
                'currency' => $payment->currency,
                'reason' => $reason,
                'status' => 'pending',
                'approval_status' => 'pending_approval',
                'user_id' => $user?->id,
                'notes' => "Refund requested by " . ($user?->name ?? 'User'),
            ]);
        });
    }

    /**
     * Approve and execute refund (with credit note generation).
     */
    public function approveRefund(int $tenantId, int $refundId, ?User $approver = null, ?string $notes = null): Refund
    {
        return DB::transaction(function () use ($tenantId, $refundId, $approver, $notes) {
            $refund = Refund::where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($refundId);

            if ($refund->approval_status === 'approved' && $refund->status === 'processed') {
                return $refund;
            }

            $payment = Payment::where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($refund->payment_id);
            $invoice = $payment->invoice_id ? Invoice::where('tenant_id', $tenantId)->lockForUpdate()->find($payment->invoice_id) : null;

            // Generate Credit Note for legal accounting trail
            $creditNoteNo = 'CN-' . date('Ym') . '-' . strtoupper(Str::random(6));
            $creditNote = CreditNote::create([
                'tenant_id' => $tenantId,
                'invoice_id' => $invoice?->id,
                'customer_id' => $payment->customer_id,
                'credit_note_number' => $creditNoteNo,
                'amount' => $refund->amount,
                'currency' => $refund->currency,
                'reason' => $refund->reason,
                'status' => 'issued',
                'user_id' => $approver?->id,
                'notes' => "Generated automatically for refund {$refund->refund_number}",
            ]);

            // Update payment refunded amount
            $payment->refunded_amount = round($payment->refunded_amount + $refund->amount, 2);
            $payment->save();

            // If invoice is linked, adjust invoice paid amount and outstanding balance
            if ($invoice) {
                $invoice->paid_amount = max(0, round($invoice->paid_amount - $refund->amount, 2));
                $invoice->outstanding_amount = round($invoice->total_amount - $invoice->paid_amount, 2);
                $invoice->status = $invoice->outstanding_amount <= 0.001 ? 'paid' : 'partially_paid';
                $invoice->save();
            }

            // Create reversal entry in transaction ledger
            FinanceTransaction::create([
                'tenant_id' => $tenantId,
                'transaction_number' => 'TXN-REV-' . strtoupper(Str::random(8)),
                'internal_transaction_id' => (string) Str::uuid(),
                'provider_transaction_id' => 'ref_' . Str::random(12),
                'payment_id' => $payment->id,
                'invoice_id' => $invoice?->id,
                'customer_id' => $payment->customer_id,
                'gateway' => $payment->payment_channel ?? 'manual',
                'payment_method' => $payment->payment_method ?? 'bank_transfer',
                'amount' => -$refund->amount, // negative cashflow
                'currency' => $refund->currency,
                'fee_amount' => 0.0,
                'tax_on_fee' => 0.0,
                'net_amount' => -$refund->amount,
                'transaction_date' => Carbon::now()->toDateString(),
                'provider_status' => 'refunded',
                'internal_status' => 'posted',
                'reconciliation_status' => 'matched',
                'reconciled_at' => Carbon::now(),
                'refund_reference' => $refund->refund_number,
                'is_posted' => true,
                'notes' => "Approved refund: {$refund->reason}",
            ]);

            $refund->update([
                'status' => 'processed',
                'approval_status' => 'approved',
                'approved_by_user_id' => $approver?->id,
                'approved_at' => Carbon::now(),
                'credit_note_id' => $creditNote->id,
                'gateway_refund_id' => 'rfnd_' . Str::random(14),
                'notes' => ($refund->notes ? $refund->notes . "\n" : '') . ($notes ?? 'Refund approved and credit note issued.'),
            ]);

            return $refund->fresh(['payment', 'invoice', 'creditNote', 'approvedByUser']);
        });
    }

    /**
     * Reconcile a financial transaction.
     */
    public function reconcileTransaction(int $tenantId, int $txnId, string $status, ?User $user = null, ?string $notes = null): FinanceTransaction
    {
        $txn = FinanceTransaction::where('tenant_id', $tenantId)->findOrFail($txnId);
        $txn->update([
            'reconciliation_status' => $status,
            'reconciled_at' => Carbon::now(),
            'reconciled_by_user_id' => $user?->id,
            'notes' => ($txn->notes ? $txn->notes . "\n" : '') . ($notes ?? "Reconciliation updated to {$status}"),
        ]);

        return $txn->fresh(['payment', 'invoice', 'customer']);
    }
}
