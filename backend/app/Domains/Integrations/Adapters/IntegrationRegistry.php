<?php

namespace App\Domains\Integrations\Adapters;

use App\Domains\Integrations\Adapters\Providers\GenericProviderAdapter;
use App\Domains\Integrations\Adapters\Providers\GoogleAdsAdapter;
use App\Domains\Integrations\Adapters\Providers\MetaAdsAdapter;
use App\Domains\Integrations\Adapters\Providers\RazorpayAdapter;

class IntegrationRegistry
{
    /**
     * @var array<string, IntegrationAdapterInterface>
     */
    protected array $adapters = [];

    public function __construct()
    {
        $this->registerDefaultAdapters();
    }

    protected function registerDefaultAdapters(): void
    {
        // 1. Google Ads
        $this->register(new GoogleAdsAdapter());

        // 2. Meta Ads
        $this->register(new MetaAdsAdapter());

        // 3. Razorpay Payment Gateway
        $this->register(new RazorpayAdapter());

        // 4. Google Analytics 4 (GA4)
        $this->register(new GenericProviderAdapter(
            'ga4',
            'Google Analytics 4',
            'analytics',
            'Stream CRM offline conversions, purchase events, and lead lifecycle milestones into GA4 measurement properties.',
            ['Measurement Protocol Server Ingestion', 'UTM Event Attribution', 'User ID Cross-Device Stitching'],
            ['https://www.googleapis.com/auth/analytics.readonly'],
            'Enter your GA4 Measurement ID and API Secret from Google Analytics Admin -> Data Streams -> Measurement Protocol API secrets.',
            ['Conversion events may take 24-48 hours to appear in standard GA4 reports.']
        ));

        // 5. Google Forms
        $this->register(new GenericProviderAdapter(
            'google_forms',
            'Google Forms Lead Capture',
            'marketing',
            'Capture lead submissions from public Google Forms and automatically ingest them into the CRM Lead Acquisition pipeline.',
            ['Automated Form Response Ingestion', 'Instant Lead Creation', 'Form Field Mapping'],
            ['https://www.googleapis.com/auth/forms.responses.readonly'],
            'Link your Google account or install our Google Workspace Marketplace Form Add-on to stream form submit responses via webhook.',
            ['Requires public Google Form or authorized domain access.']
        ));

        // 6. WhatsApp Business Cloud API
        $this->register(new GenericProviderAdapter(
            'whatsapp_cloud',
            'WhatsApp Business Cloud API',
            'communication',
            'Official Meta Cloud API integration for template broadcasts, unified messaging, and AI support agent responses.',
            ['Interactive Button Templates', 'Two-Way Messaging', 'AI Qualification Agent', '24h Window Tracking'],
            ['whatsapp_business_messaging', 'whatsapp_business_management'],
            'Provide your Phone Number ID, WhatsApp Business Account ID (WABA), and System User Permanent Access Token.',
            ['Template messaging is strictly subject to Meta Category Pricing and 24-hour service session rules.']
        ));

        // 7. Email Providers (SMTP / SES)
        $this->register(new GenericProviderAdapter(
            'email_providers',
            'Corporate Email Providers (SMTP / SES / SendGrid)',
            'communication',
            'Send personalized CRM email campaigns, transactional notifications, invoice PDFs, and support ticket notifications.',
            ['Custom SMTP/IMAP Credentials', 'Amazon SES & SendGrid API Support', 'Bounce & Open Tracking', 'DKIM/SPF Validation'],
            ['mail.send', 'mail.read'],
            'Enter your SMTP host, port, authentication credentials, and verified sender email address.',
            ['Requires proper SPF and DKIM DNS configuration on your sending domain.']
        ));

        // 8. SMS Providers (Twilio / India DLT Providers)
        $this->register(new GenericProviderAdapter(
            'sms_providers',
            'SMS Gateway & DLT Providers',
            'communication',
            'Deliver high-priority SMS alerts, OTP authentications, and automated marketing messages via configured SMS routes.',
            ['Twilio International SMS', 'India TRAI/DLT Template Compliance', 'Delivery Receipts (DLR)', 'Sender ID Masking'],
            ['sms.send', 'sms.reports'],
            'Enter your Provider Account SID, Auth Token, Sender ID / Header, and TRAI Entity ID where applicable.',
            ['Domestic India messaging strictly mandates pre-approved DLT Header and Content Template registrations.']
        ));

        // 9. Telephony Providers (Exotel / Twilio Voice)
        $this->register(new GenericProviderAdapter(
            'telephony_providers',
            'Cloud Telephony Providers (Exotel / Twilio Voice)',
            'telephony',
            'Connect programmable voice calling, AI calling agents, inbound IVR routing, and call recording capabilities.',
            ['Inbound IVR & Call Queues', 'AI Real-time Calling Agent', 'Call Recording & Transcription', 'India Virtual Numbers'],
            ['voice.call', 'voice.record', 'voice.stream'],
            'Configure your Telephony Provider Virtual Number, API Key, Token, and App ID in Telephony Settings.',
            ['Telephony features depend on telecom regulatory approvals and regional provider support.']
        ));

        // 10. Calendar Providers (Google / Outlook Calendar)
        $this->register(new GenericProviderAdapter(
            'calendar_providers',
            'Calendar Providers (Google Calendar / Microsoft 365)',
            'productivity',
            'Bi-directionally sync CRM client meetings, follow-up calls, task deadlines, and team schedule availability.',
            ['Two-Way Meeting Synchronization', 'Automatic Google Meet / Teams Links', 'Conflict Detection', 'Attendee RSVP Tracking'],
            ['https://www.googleapis.com/auth/calendar.events', 'Calendars.ReadWrite'],
            'Authorize via Google or Microsoft OAuth to grant calendar read and event creation permissions.',
            ['Sync intervals operate on 5-minute background polling plus instant push notifications.']
        ));

        // 11. Cloud Storage (AWS S3 / Cloudflare R2)
        $this->register(new GenericProviderAdapter(
            'cloud_storage',
            'Cloud Object Storage (AWS S3 / Cloudflare R2)',
            'storage',
            'Secure enterprise asset storage for client documents, invoice PDFs, voice call audio recordings, and media attachments.',
            ['Direct S3 Multipart Uploads', 'Cloudflare R2 Zero Egress', 'Pre-signed Secure URLs', 'Automated Daily Backups'],
            ['s3:PutObject', 's3:GetObject', 's3:DeleteObject'],
            'Provide your AWS/R2 Access Key, Secret Key, Bucket Name, and Region Endpoint.',
            ['Bucket CORS policy must permit pre-signed upload origins.']
        ));

        // 12. Future Custom Adapters
        $this->register(new GenericProviderAdapter(
            'custom_adapter',
            'Custom Webhook & API Adapter',
            'developer',
            'Connect proprietary enterprise ERPs, legacy databases, or custom internal systems using standard HTTP adapters.',
            ['Custom REST Ingestion', 'JSON Schema Mapping', 'Webhook Signature Verification', 'Configurable Polling Intervals'],
            ['custom.api.access'],
            'Configure your custom endpoint URL, authentication headers, and request payload schemas.',
            ['Payload processing requires valid JSON and adherence to tenant rate limits.']
        ));
    }

    public function register(IntegrationAdapterInterface $adapter): void
    {
        $this->adapters[$adapter->getProvider()] = $adapter;
    }

    public function get(string $provider): ?IntegrationAdapterInterface
    {
        return $this->adapters[$provider] ?? null;
    }

    /**
     * @return IntegrationAdapterInterface[]
     */
    public function all(): array
    {
        return $this->adapters;
    }
}
