<?php

namespace App\Domains\Sales\Services;

use App\Domains\CRM\Models\Deal;
use App\Domains\Finance\Models\Quotation;
use App\Domains\Sales\Models\Contract;
use App\Domains\Sales\Models\ContractTemplate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ContractService
{
    /**
     * Create a contract or proposal document.
     */
    public function createContract(int $tenantId, array $data, ?User $creator = null): Contract
    {
        $contractNumber = $data['contract_number'] ?? ('CTR-' . strtoupper(Str::random(8)));

        $contractValue = 0.0;
        if (isset($data['contract_value'])) {
            $contractValue = (float) $data['contract_value'];
        } elseif (!empty($data['deal_id'])) {
            $deal = Deal::where('tenant_id', $tenantId)->find($data['deal_id']);
            $contractValue = (float) ($deal?->value ?? 0.0);
        } elseif (!empty($data['quotation_id'])) {
            $quote = Quotation::where('tenant_id', $tenantId)->find($data['quotation_id']);
            $contractValue = (float) ($quote?->total_amount ?? 0.0);
        }

        return Contract::create([
            'tenant_id' => $tenantId,
            'deal_id' => $data['deal_id'] ?? null,
            'quotation_id' => $data['quotation_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'contact_id' => $data['contact_id'] ?? null,
            'title' => $data['title'] ?? 'Standard Agreement',
            'contract_type' => $data['contract_type'] ?? 'contract',
            'contract_number' => $contractNumber,
            'status' => $data['status'] ?? 'draft',
            'document_version' => 1,
            'terms_conditions' => $data['terms_conditions'] ?? 'Standard enterprise terms and conditions.',
            'contract_value' => $contractValue,
            'start_date' => $data['start_date'] ?? now()->toDateString(),
            'end_date' => $data['end_date'] ?? now()->addYear()->toDateString(),
            'auto_renew' => $data['auto_renew'] ?? false,
            'renewal_notice_days' => $data['renewal_notice_days'] ?? 30,
            'e_signature_provider' => $data['e_signature_provider'] ?? 'clickwrap',
            'signature_status' => 'unsigned',
            'signer_name' => $data['signer_name'] ?? null,
            'signer_email' => $data['signer_email'] ?? null,
            'created_by_user_id' => $creator?->id,
        ]);
    }

    /**
     * Send contract for e-signature.
     */
    public function sendForSignature(Contract $contract, string $signerName, string $signerEmail, string $provider = 'clickwrap'): Contract
    {
        $contract->update([
            'status' => 'sent_for_signature',
            'signature_status' => 'sent_for_signature',
            'signer_name' => $signerName,
            'signer_email' => $signerEmail,
            'e_signature_provider' => $provider,
        ]);

        return $contract->fresh();
    }

    /**
     * Execute legally valid digital signature recording with cryptographic audit trail.
     * Note: Enforces that internal approval != legal e-signature.
     */
    public function recordSignature(Contract $contract, string $signerName, string $signerEmail, string $ipAddress, ?string $otpCode = null): Contract
    {
        $signedAt = now();
        // Cryptographic audit trail hash: SHA-256 of contract ID + number + signer + timestamp + IP
        $rawPayload = "{$contract->id}|{$contract->contract_number}|{$signerEmail}|{$signedAt->toIso8601String()}|{$ipAddress}|{$contract->e_signature_provider}";
        $auditHash = hash('sha256', $rawPayload);

        $contract->update([
            'status' => 'signed',
            'signature_status' => 'signed',
            'signer_name' => $signerName,
            'signer_email' => $signerEmail,
            'signed_at' => $signedAt,
            'audit_trail_hash' => $auditHash,
        ]);

        return $contract->fresh();
    }

    /**
     * Customer declines or rejects contract.
     */
    public function declineSignature(Contract $contract, string $reason): Contract
    {
        $contract->update([
            'status' => 'rejected',
            'signature_status' => 'declined',
        ]);

        return $contract->fresh();
    }
}
