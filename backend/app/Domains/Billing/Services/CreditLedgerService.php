<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\CreditLedgerEntry;
use App\Domains\Billing\Models\TenantCreditWallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreditLedgerService
{
    /**
     * Get or initialize tenant credit wallet
     */
    public function getOrCreateWallet(int $tenantId, string $currency = 'USD'): TenantCreditWallet
    {
        return TenantCreditWallet::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'balance' => 0.0000,
                'reserved_balance' => 0.0000,
                'currency' => $currency,
            ]
        );
    }

    /**
     * Top-up / Recharge credits with an immutable ledger entry
     */
    public function rechargeCredits(int $tenantId, float $amount, string $referenceId, string $description = 'Credit Top-up', array $metadata = []): CreditLedgerEntry
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Recharge amount must be strictly positive.');
        }

        return DB::transaction(function () use ($tenantId, $amount, $referenceId, $description, $metadata) {
            $wallet = TenantCreditWallet::where('tenant_id', $tenantId)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = $this->getOrCreateWallet($tenantId);
                $wallet = TenantCreditWallet::where('tenant_id', $tenantId)->lockForUpdate()->first();
            }

            $newBalance = round((float) $wallet->balance + $amount, 4);
            $wallet->update([
                'balance' => $newBalance,
                'last_recharged_at' => Carbon::now(),
            ]);

            return CreditLedgerEntry::create([
                'entry_id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'entry_type' => 'recharge',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference_id' => $referenceId,
                'description' => $description,
                'metadata' => $metadata,
                'created_at' => Carbon::now(),
            ]);
        });
    }

    /**
     * Controlled manual credit adjustment (credit or debit) by authorized platform admin
     */
    public function adjustCredits(int $tenantId, float $amount, string $reason, array $metadata = []): CreditLedgerEntry
    {
        return DB::transaction(function () use ($tenantId, $amount, $reason, $metadata) {
            $wallet = TenantCreditWallet::where('tenant_id', $tenantId)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = $this->getOrCreateWallet($tenantId);
                $wallet = TenantCreditWallet::where('tenant_id', $tenantId)->lockForUpdate()->first();
            }

            $newBalance = round((float) $wallet->balance + $amount, 4);
            if ($newBalance < 0) {
                throw new \RuntimeException('Insufficient credits: balance cannot drop below zero.');
            }

            $wallet->update(['balance' => $newBalance]);

            return CreditLedgerEntry::create([
                'entry_id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'entry_type' => 'adjustment',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference_id' => 'adj_' . Str::random(12),
                'description' => $reason,
                'metadata' => $metadata,
                'created_at' => Carbon::now(),
            ]);
        });
    }

    /**
     * Get chronologically ordered credit ledger entries
     */
    public function getLedgerHistory(int $tenantId, int $limit = 50)
    {
        return CreditLedgerEntry::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->take($limit)
            ->get();
    }

    /**
     * Mathematically reconcile immutable ledger against current wallet balance
     */
    public function verifyLedgerIntegrity(int $tenantId): array
    {
        $wallet = $this->getOrCreateWallet($tenantId);
        $entries = CreditLedgerEntry::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $calculatedBalance = 0.0000;
        $discrepancyFound = false;

        foreach ($entries as $entry) {
            $calculatedBalance += (float) $entry->amount;
            if (abs($calculatedBalance - (float) $entry->balance_after) > 0.0001) {
                $discrepancyFound = true;
                break;
            }
        }

        $calculatedBalance = round($calculatedBalance, 4);
        $currentWalletBalance = round((float) $wallet->balance, 4);
        $isBalanced = !$discrepancyFound && abs($calculatedBalance - $currentWalletBalance) < 0.0001;

        return [
            'is_valid' => $isBalanced,
            'entries_count' => $entries->count(),
            'current_wallet_balance' => $currentWalletBalance,
            'calculated_ledger_sum' => $calculatedBalance,
            'difference' => round($currentWalletBalance - $calculatedBalance, 4),
        ];
    }
}
