<?php

namespace Database\Seeders;

use App\Domains\LandingPages\Models\LandingPageTemplate;
use Illuminate\Database\Seeder;

class LandingPageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        if (LandingPageTemplate::count() > 0) {
            return;
        }

        LandingPageTemplate::create([
            'name' => 'High-Converting B2B Lead Magnet & Consultation',
            'category' => 'lead_generation',
            'description' => 'A structured landing page template designed for agencies, B2B SaaS, and consultancies to capture enterprise inquiries.',
            'branding' => [
                'primary_color' => '#4f46e5',
                'secondary_color' => '#06b6d4',
                'font_family' => 'Inter, sans-serif',
                'logo_url' => '/brand/logo.png',
            ],
            'sections' => [
                [
                    'id' => 'sec_hero',
                    'type' => 'hero',
                    'headline' => 'Scale Your Business With Automated Enterprise Intelligence',
                    'subheadline' => 'Capture, qualify and convert high-intent prospects on autopilot with our end-to-end sales platform.',
                    'cta_text' => 'Book Free Consultation',
                    'cta_target' => '#form_section',
                    'badge' => '2026 Enterprise Edition',
                    'is_visible' => true,
                ],
                [
                    'id' => 'sec_features',
                    'type' => 'features',
                    'headline' => 'Built for Modern Revenue Operations',
                    'features' => [
                        ['title' => 'Real-Time Ingestion', 'desc' => 'Connect Google Ads, Meta Lead Ads, and WhatsApp into one unified CRM pipeline.'],
                        ['title' => 'AI Voice Calling', 'desc' => 'Qualify leads in seconds with conversational telephony automation.'],
                        ['title' => 'Visual Workflows', 'desc' => 'Automate reminders, lead scoring, assignments, and deal stage progressions.'],
                    ],
                    'is_visible' => true,
                ],
                [
                    'id' => 'sec_form',
                    'type' => 'form_embed',
                    'headline' => 'Get in Touch with our Strategy Team',
                    'subheadline' => 'Fill out the form below and an advisor will reach out within 15 minutes.',
                    'is_visible' => true,
                ],
                [
                    'id' => 'sec_faq',
                    'type' => 'faq',
                    'headline' => 'Frequently Asked Questions',
                    'faqs' => [
                        ['q' => 'How does lead distribution work?', 'a' => 'Leads are automatically routed based on round-robin assignment rules, geographic territories, or qualification scores.'],
                        ['q' => 'Is data encrypted and tenant-isolated?', 'a' => 'Yes, all databases enforce row-level tenant boundaries and TLS 1.3 in-transit encryption.'],
                    ],
                    'is_visible' => true,
                ],
                [
                    'id' => 'sec_footer',
                    'type' => 'footer',
                    'copyright' => '© 2026 Enterprise CRM SaaS. All rights reserved.',
                    'is_visible' => true,
                ],
            ],
            'is_system' => true,
        ]);

        LandingPageTemplate::create([
            'name' => 'SaaS Product Launch & Free Trial',
            'category' => 'product_launch',
            'description' => 'Optimized for software products, product-led growth (PLG), and self-serve trial signups.',
            'branding' => [
                'primary_color' => '#10b981',
                'secondary_color' => '#3b82f6',
                'font_family' => 'Plus Jakarta Sans, sans-serif',
            ],
            'sections' => [
                [
                    'id' => 'sec_hero',
                    'type' => 'hero',
                    'headline' => 'The Operating System for Enterprise Customer Relationships',
                    'subheadline' => 'Unify WhatsApp, Voice calling, Kanban pipelines, and billing in one fast dashboard.',
                    'cta_text' => 'Start 14-Day Free Trial',
                    'is_visible' => true,
                ],
                [
                    'id' => 'sec_form',
                    'type' => 'form_embed',
                    'headline' => 'Start Your Trial In 60 Seconds',
                    'is_visible' => true,
                ],
            ],
            'is_system' => true,
        ]);
    }
}
