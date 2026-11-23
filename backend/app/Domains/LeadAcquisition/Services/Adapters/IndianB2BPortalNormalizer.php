<?php

namespace App\Domains\LeadAcquisition\Services\Adapters;

class IndianB2BPortalNormalizer
{
    /**
     * Normalize Indian phone numbers:
     * - Strips country code +91 or leading 0 if 10-digit mobile follows
     * - Preserves valid E.164
     */
    public static function normalizeIndianPhone(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        // Remove non-digit characters except leading plus
        $clean = preg_replace('/[^\d+]/', '', $phone);

        // Remove leading +91 or 91 if followed by 10 digits
        if (preg_match('/^(\+91|91)?([6-9]\d{9})$/', $clean, $matches)) {
            return '+91' . $matches[2];
        }

        // If 10 digits starting with 6-9
        if (preg_match('/^[6-9]\d{9}$/', $clean)) {
            return '+91' . $clean;
        }

        // If leading 0 followed by 10 digits
        if (preg_match('/^0([6-9]\d{9})$/', $clean, $matches)) {
            return '+91' . $matches[1];
        }

        return $clean;
    }

    /**
     * Normalize IndiaMART push payload
     */
    public static function parseIndiaMart(array $payload): array
    {
        $phone = $payload['SENDER_MOBILE'] ?? $payload['MOB'] ?? $payload['mobile'] ?? '';
        $name = $payload['SENDER_NAME'] ?? $payload['NAME'] ?? $payload['name'] ?? 'IndiaMART Buyer';
        $email = $payload['SENDER_EMAIL'] ?? $payload['EMAIL'] ?? $payload['email'] ?? null;
        $company = $payload['GLUSR_USR_COMPANYNAME'] ?? $payload['COMPANY'] ?? $payload['company'] ?? null;
        $product = $payload['PRODUCT_NAME'] ?? $payload['SUBJECT'] ?? $payload['product'] ?? 'General Enquiry';
        $query = $payload['QUERY_MESSAGE'] ?? $payload['QUERY_DETAILS'] ?? $payload['message'] ?? $payload['SUBJECT'] ?? '';
        $city = $payload['SENDER_CITY'] ?? $payload['CITY'] ?? $payload['city'] ?? null;
        $state = $payload['SENDER_STATE'] ?? $payload['STATE'] ?? $payload['state'] ?? null;
        $country = $payload['SENDER_COUNTRY_ISO'] ?? $payload['COUNTRY'] ?? 'India';
        $externalId = $payload['UNIQUE_QUERY_ID'] ?? $payload['QUERY_ID'] ?? $payload['query_id'] ?? null;

        return [
            'name' => trim($name),
            'phone' => self::normalizeIndianPhone($phone),
            'email' => $email ? strtolower(trim($email)) : null,
            'company_name' => $company ? trim($company) : null,
            'product_interest' => trim($product),
            'requirement' => trim($query),
            'city' => $city ? trim($city) : null,
            'state' => $state ? trim($state) : null,
            'country' => $country ? trim($country) : 'India',
            'source' => 'indiamart',
            'external_lead_id' => $externalId ? (string) $externalId : null,
            'raw' => $payload,
        ];
    }

    /**
     * Normalize Justdial webhook payload
     */
    public static function parseJustdial(array $payload): array
    {
        $phone = $payload['mobile'] ?? $payload['phone'] ?? $payload['contact'] ?? '';
        $name = $payload['name'] ?? $payload['customer_name'] ?? 'Justdial Caller';
        $email = $payload['email'] ?? null;
        $category = $payload['category'] ?? $payload['parent_category'] ?? $payload['service'] ?? 'Local Enquiry';
        $city = $payload['city'] ?? null;
        $area = $payload['area'] ?? $payload['locality'] ?? null;
        $pincode = $payload['pincode'] ?? null;
        $externalId = $payload['leadid'] ?? $payload['lead_id'] ?? null;

        return [
            'name' => trim($name),
            'phone' => self::normalizeIndianPhone($phone),
            'email' => $email ? strtolower(trim($email)) : null,
            'company_name' => $payload['company'] ?? null,
            'product_interest' => trim($category),
            'requirement' => "Enquiry for {$category} in {$area}, {$city}",
            'city' => $city ? trim($city) : null,
            'state' => $payload['state'] ?? null,
            'country' => 'India',
            'postal_code' => $pincode ? (string) $pincode : null,
            'source' => 'justdial',
            'external_lead_id' => $externalId ? (string) $externalId : null,
            'raw' => $payload,
        ];
    }

    /**
     * Normalize TradeIndia inquiry payload
     */
    public static function parseTradeIndia(array $payload): array
    {
        $phone = $payload['sender_mobile'] ?? $payload['mobile'] ?? '';
        $name = $payload['sender_name'] ?? $payload['name'] ?? 'TradeIndia Buyer';
        $email = $payload['sender_email'] ?? $payload['email'] ?? null;
        $company = $payload['sender_co'] ?? $payload['company'] ?? null;
        $product = $payload['product_name'] ?? $payload['subject'] ?? 'B2B Trade Enquiry';
        $message = $payload['message'] ?? $payload['inquiry_details'] ?? '';
        $city = $payload['sender_city'] ?? $payload['city'] ?? null;
        $state = $payload['sender_state'] ?? $payload['state'] ?? null;
        $country = $payload['sender_country'] ?? 'India';
        $externalId = $payload['inquiry_id'] ?? $payload['r_id'] ?? null;

        return [
            'name' => trim($name),
            'phone' => self::normalizeIndianPhone($phone),
            'email' => $email ? strtolower(trim($email)) : null,
            'company_name' => $company ? trim($company) : null,
            'product_interest' => trim($product),
            'requirement' => trim($message),
            'city' => $city ? trim($city) : null,
            'state' => $state ? trim($state) : null,
            'country' => $country ? trim($country) : 'India',
            'source' => 'tradeindia',
            'external_lead_id' => $externalId ? (string) $externalId : null,
            'raw' => $payload,
        ];
    }
}
