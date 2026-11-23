<?php

namespace App\Domains\LeadAcquisition\Services\Adapters;

use App\Domains\LeadAcquisition\Models\LeadSource;

interface SourceAdapterInterface
{
    /**
     * Verify authenticity (signature, token, or secret) of the incoming event.
     */
    public function verifyAuthentication(LeadSource $source, array $payload, array $headers): bool;

    /**
     * Extract or generate an idempotent unique event identifier.
     */
    public function extractEventId(array $payload, array $headers): string;

    /**
     * Normalize the raw provider payload into standardized key-value pairs.
     */
    public function normalizePayload(LeadSource $source, array $payload): array;

    /**
     * Extract campaign, ad, form, UTM and click identifier attribution.
     */
    public function extractAttribution(LeadSource $source, array $payload): array;
}
