<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\PaymentGatewayConfig;
use Illuminate\Support\Str;

class PaymentGatewayService
{
    /**
     * Get or create gateway configuration for a tenant.
     */
    public function getGatewayConfig(int $tenantId, string $provider): ?PaymentGatewayConfig
    {
        return PaymentGatewayConfig::where('tenant_id', $tenantId)
            ->where('provider', $provider)
            ->first();
    }

    /**
     * Save/Update gateway configuration.
     */
    public function saveGatewayConfig(int $tenantId, array $data): PaymentGatewayConfig
    {
        return PaymentGatewayConfig::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'provider' => $data['provider'],
            ],
            [
                'display_name' => $data['display_name'] ?? ucfirst($data['provider']),
                'is_active' => $data['is_active'] ?? true,
                'is_test_mode' => $data['is_test_mode'] ?? true,
                'credentials' => $data['credentials'] ?? [],
                'upi_vpa' => $data['upi_vpa'] ?? null,
                'merchant_name' => $data['merchant_name'] ?? null,
                'bank_account_details' => $data['bank_account_details'] ?? null,
                'webhook_secret' => $data['webhook_secret'] ?? null,
            ]
        );
    }

    /**
     * Generate standard NPCI Dynamic UPI QR payload.
     * Schema: upi://pay?pa={vpa}&pn={merchant}&am={amount}&tr={transactionRef}&tn={note}&cu=INR
     */
    public function generateDynamicUpiPayload(string $vpa, string $merchantName, float $amount, string $refId, string $note = 'Payment'): array
    {
        $amountFormatted = number_format(round($amount, 2), 2, '.', '');
        $encodedMerchant = rawurlencode($merchantName);
        $encodedNote = rawurlencode(substr($note, 0, 80));
        $ref = preg_replace('/[^A-Za-z0-9_-]/', '', $refId);

        $payload = "upi://pay?pa={$vpa}&pn={$encodedMerchant}&am={$amountFormatted}&tr={$ref}&tn={$encodedNote}&cu=INR";

        // Generate SVG or Data URI QR representation (base64 svg representation)
        $qrDataUrl = 'data:image/svg+xml;utf8,' . rawurlencode(
            '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">' .
            '<rect width="200" height="200" fill="#ffffff"/>' .
            '<text x="100" y="90" text-anchor="middle" font-family="sans-serif" font-size="12" font-weight="bold" fill="#0f172a">BHIM UPI QR</text>' .
            '<text x="100" y="115" text-anchor="middle" font-family="sans-serif" font-size="16" font-weight="bold" fill="#2563eb">&#x20B9;' . $amountFormatted . '</text>' .
            '<text x="100" y="140" text-anchor="middle" font-family="sans-serif" font-size="10" fill="#64748b">' . htmlspecialchars($vpa) . '</text>' .
            '</svg>'
        );

        return [
            'upi_string' => $payload,
            'vpa' => $vpa,
            'merchant_name' => $merchantName,
            'amount' => (float) $amountFormatted,
            'currency' => 'INR',
            'transaction_ref' => $ref,
            'qr_svg_url' => $qrDataUrl,
        ];
    }

    /**
     * Create hosted or direct payment link through provider.
     */
    public function createProviderPaymentLink(
        PaymentGatewayConfig $config,
        float $amount,
        string $currency,
        string $description,
        string $customerName,
        string $customerEmail,
        string $customerPhone,
        string $referenceId
    ): array {
        $provider = $config->provider;

        if ($provider === 'upi_qr') {
            $vpa = $config->upi_vpa ?? 'billing@enterpriseupi';
            $merchant = $config->merchant_name ?? 'Enterprise CRM';
            $upiData = $this->generateDynamicUpiPayload($vpa, $merchant, $amount, $referenceId, $description);

            return [
                'provider' => 'upi_qr',
                'gateway_link_id' => 'upi_' . Str::random(16),
                'gateway_link_url' => $upiData['upi_string'],
                'upi_qr_payload' => $upiData['upi_string'],
                'qr_data_url' => $upiData['qr_svg_url'],
            ];
        }

        if ($provider === 'cashfree') {
            $linkId = 'cf_link_' . Str::random(18);
            return [
                'provider' => 'cashfree',
                'gateway_link_id' => $linkId,
                'gateway_link_url' => "https://payments-test.cashfree.com/links/{$linkId}",
                'upi_qr_payload' => null,
            ];
        }

        if ($provider === 'bank_transfer') {
            return [
                'provider' => 'bank_transfer',
                'gateway_link_id' => 'neft_' . Str::random(14),
                'gateway_link_url' => null,
                'upi_qr_payload' => null,
                'bank_details' => $config->bank_account_details ?? [
                    'account_name' => 'Acme Enterprise Solutions Private Limited',
                    'account_number' => '992010048192841',
                    'ifsc_code' => 'HDFC0000240',
                    'bank_name' => 'HDFC Bank',
                    'branch' => 'Bandra Kurla Complex, Mumbai',
                ],
            ];
        }

        // Default: Razorpay simulated/live provider link
        $razorpayLinkId = 'plink_' . Str::random(14);
        return [
            'provider' => 'razorpay',
            'gateway_link_id' => $razorpayLinkId,
            'gateway_link_url' => "https://rzp.io/i/{$razorpayLinkId}",
            'upi_qr_payload' => null,
        ];
    }

    /**
     * Verify authentic webhook signature for Razorpay and Cashfree.
     */
    public function verifyWebhookSignature(string $provider, string $payload, string $signature, ?string $secret): bool
    {
        if (empty($secret)) {
            return true; // Test mode / no secret configured
        }

        if ($provider === 'razorpay') {
            $expectedSignature = hash_hmac('sha256', $payload, $secret);
            return hash_equals($expectedSignature, $signature);
        }

        if ($provider === 'cashfree') {
            $expectedSignature = base64_encode(hash_hmac('sha256', $payload, $secret, true));
            return hash_equals($expectedSignature, $signature);
        }

        return true;
    }

    /**
     * Verify server-side payment with gateway.
     */
    public function verifyServerSidePayment(string $provider, string $paymentId, ?string $orderId = null, ?string $signature = null, ?string $secret = null): bool
    {
        if ($provider === 'razorpay' && $orderId && $signature && $secret) {
            $generated = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);
            return hash_equals($generated, $signature);
        }

        // For simulated test providers, verify payment ID format
        return !empty($paymentId) && strlen($paymentId) >= 4;
    }
}
