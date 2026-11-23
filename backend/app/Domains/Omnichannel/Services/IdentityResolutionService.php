<?php

namespace App\Domains\Omnichannel\Services;

use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Customer;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Omnichannel\Models\IdentityMergePending;
use App\Domains\WhatsApp\Models\WhatsAppConversation;

class IdentityResolutionService
{
    /**
     * Resolve exact match against verified phone or email.
     */
    public function resolveExactIdentity(int $tenantId, ?string $email, ?string $phone): array
    {
        $cleanEmail = $email ? strtolower(trim($email)) : null;
        $cleanPhone = $phone ? preg_replace('/[^\+0-9]/', '', $phone) : null;

        if (!$cleanEmail && (!$cleanPhone || $cleanPhone === 'N/A')) {
            return ['customer' => null, 'contact' => null, 'lead' => null];
        }

        // 1. Search Customer
        $customer = Customer::where('tenant_id', $tenantId)
            ->where(function ($q) use ($cleanEmail, $cleanPhone) {
                if ($cleanEmail) $q->where('email', $cleanEmail);
                if ($cleanPhone && $cleanPhone !== 'N/A') $q->orWhere('phone', $cleanPhone);
            })
            ->first();

        // 2. Search Contact
        $contact = Contact::where('tenant_id', $tenantId)
            ->where(function ($q) use ($cleanEmail, $cleanPhone) {
                if ($cleanEmail) $q->where('email', $cleanEmail);
                if ($cleanPhone && $cleanPhone !== 'N/A') $q->orWhere('phone', $cleanPhone);
            })
            ->first();

        // 3. Search Lead
        $lead = Lead::where('tenant_id', $tenantId)
            ->where(function ($q) use ($cleanEmail, $cleanPhone) {
                if ($cleanEmail) $q->where('email', $cleanEmail);
                if ($cleanPhone && $cleanPhone !== 'N/A') $q->orWhere('phone', $cleanPhone);
            })
            ->first();

        return [
            'customer' => $customer,
            'contact' => $contact,
            'lead' => $lead,
        ];
    }

    /**
     * Check for uncertain/ambiguous matches. Keep uncertain matches pending review.
     * Never merge people merely because they share a name!
     */
    public function evaluateAmbiguousMatches(
        int $tenantId,
        string $primaryType,
        int $primaryId,
        string $name,
        ?string $email = null,
        ?string $phone = null
    ): ?IdentityMergePending {
        $cleanName = trim(strtolower($name));
        if (empty($cleanName)) {
            return null;
        }

        // Look for existing leads with same name but different contact information
        $potentialLeadMatch = Lead::where('tenant_id', $tenantId)
            ->where('id', '!=', $primaryId)
            ->whereRaw('LOWER(name) = ?', [$cleanName])
            ->first();

        if ($potentialLeadMatch) {
            // Check if phone and email are different (uncertain match)
            $phoneDiff = ($phone && $potentialLeadMatch->phone && $phone !== $potentialLeadMatch->phone);
            $emailDiff = ($email && $potentialLeadMatch->email && strtolower($email) !== strtolower($potentialLeadMatch->email));

            if ($phoneDiff || $emailDiff) {
                // Do NOT auto-merge! Record for human verification
                return IdentityMergePending::firstOrCreate([
                    'tenant_id' => $tenantId,
                    'primary_type' => $primaryType,
                    'primary_id' => $primaryId,
                    'secondary_type' => 'lead',
                    'secondary_id' => $potentialLeadMatch->id,
                ], [
                    'match_criteria' => 'name_similarity_different_identifiers',
                    'confidence_score' => 60,
                    'status' => 'pending_review',
                    'notes' => "Identical name '{$name}' detected with disparate contact coordinates. Pending manual verification.",
                ]);
            }
        }

        return null;
    }

    /**
     * Manual review action by authorized supervisor.
     */
    public function reviewMerge(IdentityMergePending $pendingMerge, string $decision, int $reviewerUserId, ?string $notes = null): IdentityMergePending
    {
        if ($decision === 'merged') {
            // Re-point conversations belonging to secondary entity to primary entity
            if ($pendingMerge->secondary_type === 'lead') {
                WhatsAppConversation::where('tenant_id', $pendingMerge->tenant_id)
                    ->where('lead_id', $pendingMerge->secondary_id)
                    ->update([
                        'lead_id' => $pendingMerge->primary_id,
                    ]);
            }

            $pendingMerge->update([
                'status' => 'merged',
                'reviewed_by_user_id' => $reviewerUserId,
                'reviewed_at' => now(),
                'notes' => $notes ?? 'Entities officially merged after supervisor audit.',
            ]);
        } else {
            $pendingMerge->update([
                'status' => 'rejected',
                'reviewed_by_user_id' => $reviewerUserId,
                'reviewed_at' => now(),
                'notes' => $notes ?? 'Duplicate merge proposal rejected by supervisor.',
            ]);
        }

        return $pendingMerge;
    }
}
