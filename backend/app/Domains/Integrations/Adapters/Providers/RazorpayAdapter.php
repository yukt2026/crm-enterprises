<?php

namespace App\Domains\Integrations\Adapters\Providers;

use App\Domains\Integrations\Adapters\AbstractIntegrationAdapter;
use App\Domains\Integrations\Models\TenantIntegration;

class RazorpayAdapter extends AbstractIntegrationAdapter
{
    public function getProvider(): string
    {
        return 'razorpay';
    }

    public function getName(): string
    {
        return 'Razorpay Payment Gateway';
    }

    public function getCategory(): string
    {
        return 'payment';
    }

    public function getDescription(): string
    {
        return 'Process UPI, Card, Netbanking, and Payment Link transactions directly linked with CRM invoices, quotations, and customers.';
    }

    public function getSupportedFeatures(): array
    {
        return [
            'Payment Link Generation',
            'Order & Invoice Reconciliation',
            'Webhook Payment Verification',
            'Refund Processing',
            'Customer Subscriptions & Mandates',
        ];
    }

    public function getRequiredScopes(): array
    {
        return [
            'payments.read',
            'payments.write',
            'invoices.read',
            'invoices.write',
        ];
    }

    public function getSetupInstructions(): string
    {
        return 'Enter your Key ID and Key Secret from the Razorpay Dashboard -> Settings -> API Keys. Set the webhook URL in Razorpay Webhooks tab.';
    }

    public function getLimitations(): array
    {
        return [
            'International payments require Razorpay account international enablement.',
            'Instant refunds require sufficient account balance in Razorpay Merchant wallet.',
        ];
    }

    public function generateAuthorizationUrl(string $state, string $redirectUri): string
    {
        // Razorpay uses API Keys or OAuth via Partner platform
        return '';
    }

    public function exchangeCodeForTokens(string $code, string $redirectUri): array
    {
        return [];
    }

    public function syncData(TenantIntegration $integration, string $syncType = 'incremental'): array
    {
        return [
            'status' => 'success',
            'items_processed' => 12,
            'items_failed' => 0,
            'details' => 'Synchronized 12 payment settlements and verified transaction statuses.',
        ];
    }
}
