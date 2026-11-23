<?php

namespace Tests\Feature;

use App\Domains\LandingPages\Models\CustomDomain;
use App\Domains\LandingPages\Models\Form;
use App\Domains\LandingPages\Models\FormSubmission;
use App\Domains\LandingPages\Models\LandingPage;
use App\Domains\LandingPages\Models\LandingPageRevision;
use App\Domains\LandingPages\Services\FormIngestionService;
use App\Domains\LandingPages\Services\LandingPagePublishingService;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageAndFormBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userAdmin;
    protected string $tokenAdmin;
    protected LandingPagePublishingService $publishingService;
    protected FormIngestionService $formIngestionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Apex Digital Solutions',
            'slug' => 'apex-digital',
            'subscription_tier' => 'enterprise',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Zenith Corp',
            'slug' => 'zenith-corp',
            'subscription_tier' => 'professional',
        ]);

        $this->userAdmin = User::create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'name' => 'Aarav Patel',
            'email' => 'aarav@apexdigital.in',
            'password' => bcrypt('SecurePass2026!'),
            'role' => 'Business Admin',
        ]);
        $this->userAdmin->workspaces()->attach($this->tenantA->id, ['role' => 'Business Admin', 'status' => 'active']);
        $this->tokenAdmin = $this->userAdmin->createToken('test_token')->plainTextToken;

        $this->publishingService = app(LandingPagePublishingService::class);
        $this->formIngestionService = app(FormIngestionService::class);
    }

    /** @test */
    public function it_creates_and_manages_landing_pages_with_modular_sections()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->postJson('/api/v1/crm/landing-pages', [
                'name' => 'AI Enterprise Consulting Launch',
                'title' => 'Transform Operations with Enterprise AI',
                'description' => 'Dedicated landing page for high-intent B2B consulting inquiries.',
                'branding' => [
                    'primary_color' => '#6366f1',
                    'font_family' => 'Inter, sans-serif',
                ],
                'sections' => [
                    [
                        'id' => 'hero_1',
                        'type' => 'hero',
                        'headline' => 'AI Automation for Modern Enterprises',
                        'subheadline' => 'Connect WhatsApp, Voice calling and CRM in one platform.',
                        'cta_text' => 'Book Strategy Session',
                        'is_visible' => true,
                    ],
                    [
                        'id' => 'features_1',
                        'type' => 'features',
                        'features' => [
                            ['title' => '24/7 Calling AI', 'desc' => 'Inbound & outbound qualification.'],
                        ],
                        'is_visible' => true,
                    ],
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'AI Enterprise Consulting Launch');
        $response->assertJsonPath('data.slug', 'ai-enterprise-consulting-launch');
        $response->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('landing_pages', [
            'tenant_id' => $this->tenantA->id,
            'name' => 'AI Enterprise Consulting Launch',
            'slug' => 'ai-enterprise-consulting-launch',
        ]);
    }

    /** @test */
    public function it_creates_versioned_revisions_on_publishing_and_supports_rollback()
    {
        $page = LandingPage::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'SaaS Product Tour',
            'slug' => 'saas-product-tour',
            'title' => 'Original Title v1',
            'status' => 'draft',
            'current_version' => 1,
            'sections' => [
                ['id' => 's1', 'type' => 'hero', 'headline' => 'Version 1 Hero Headline'],
            ],
            'created_by_user_id' => $this->userAdmin->id,
        ]);

        // 1. Publish page (creates revision snapshot)
        $publishRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->postJson("/api/v1/crm/landing-pages/{$page->id}/publish", [
                'changelog' => 'Initial public launch release',
            ]);

        $publishRes->assertStatus(200);
        $publishRes->assertJsonPath('data.status', 'published');
        $publishRes->assertJsonPath('data.current_version', 2);

        $this->assertDatabaseHas('landing_page_revisions', [
            'tenant_id' => $this->tenantA->id,
            'landing_page_id' => $page->id,
            'version' => 2,
            'changelog' => 'Initial public launch release',
        ]);

        // 2. Modify page to v3
        $page->update([
            'sections' => [
                ['id' => 's1', 'type' => 'hero', 'headline' => 'Version 2 Modified Headline'],
            ],
        ]);
        $this->publishingService->publish($page, 'Second release v2');

        $this->assertEquals(3, $page->fresh()->current_version);

        // 3. Rollback to version 2
        $rollbackRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->postJson("/api/v1/crm/landing-pages/{$page->id}/rollback", [
                'version' => 2,
            ]);

        $rollbackRes->assertStatus(200);
        $this->assertEquals(4, $rollbackRes->json('data.current_version'));
        $this->assertEquals('Version 1 Hero Headline', $rollbackRes->json('data.sections.0.headline'));
    }

    /** @test */
    public function it_enforces_dns_verification_before_linking_custom_domain_to_published_page()
    {
        // 1. Create custom domain (pending verification)
        $domain = CustomDomain::create([
            'tenant_id' => $this->tenantA->id,
            'domain' => 'consulting.apexdigital.in',
            'verification_token' => 'crm-verify-unverified99',
            'verification_status' => 'pending',
            'ssl_status' => 'pending',
        ]);

        $page = LandingPage::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Custom Domain Page',
            'slug' => 'custom-domain-page',
            'status' => 'draft',
            'custom_domain_id' => $domain->id,
            'sections' => [
                ['id' => 's1', 'type' => 'hero', 'headline' => 'Hello World'],
            ],
        ]);

        // Attempt to publish with unverified custom domain -> must fail with 422
        $res = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->postJson("/api/v1/crm/landing-pages/{$page->id}/publish");

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['custom_domain']);

        // 2. Verify domain via verification endpoint
        $verifyRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->postJson("/api/v1/crm/custom-domains/{$domain->id}/verify");

        $verifyRes->assertStatus(200);
        $this->assertEquals('verified', $domain->fresh()->verification_status);

        // 3. Now publishing succeeds
        $publishRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->postJson("/api/v1/crm/landing-pages/{$page->id}/publish");

        $publishRes->assertStatus(200);
        $publishRes->assertJsonPath('data.status', 'published');
    }

    /** @test */
    public function it_creates_custom_forms_with_validation_rules()
    {
        $schema = [
            'fields' => [
                ['name' => 'full_name', 'label' => 'Full Name', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'label' => 'Business Email', 'type' => 'email', 'required' => true],
                ['name' => 'phone', 'label' => 'Phone Number', 'type' => 'phone', 'required' => false],
                ['name' => 'company_size', 'label' => 'Company Size', 'type' => 'dropdown', 'options' => ['1-10', '11-50', '50+']],
            ],
            'conditional_rules' => [
                ['show_field' => 'company_size', 'when_field' => 'email', 'operator' => 'contains', 'value' => '@'],
            ],
        ];

        $res = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->postJson('/api/v1/crm/forms', [
                'name' => 'Enterprise Consultation Form',
                'title' => 'Book Your Strategy Call',
                'schema' => $schema,
                'settings' => [
                    'submit_button_text' => 'Confirm Booking',
                    'require_consent' => true,
                    'consent_text' => 'I agree to terms and privacy policy.',
                ],
            ]);

        $res->assertStatus(201);
        $res->assertJsonPath('data.name', 'Enterprise Consultation Form');
        $res->assertJsonPath('data.slug', 'enterprise-consultation-form');
        $this->assertCount(4, $res->json('data.schema.fields'));
    }

    /** @test */
    public function it_validates_form_submissions_and_creates_crm_lead_with_utm_attribution()
    {
        $form = Form::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Public Contact Form',
            'slug' => 'contact-us',
            'status' => 'active',
            'schema' => [
                'fields' => [
                    ['name' => 'name', 'label' => 'Your Name', 'type' => 'text', 'required' => true],
                    ['name' => 'email', 'label' => 'Email Address', 'type' => 'email', 'required' => true],
                    ['name' => 'phone', 'label' => 'Mobile Phone', 'type' => 'phone', 'required' => true],
                    ['name' => 'company', 'label' => 'Organization', 'type' => 'text', 'required' => false],
                    ['name' => 'requirement', 'label' => 'Project Scope', 'type' => 'text', 'required' => false],
                ],
            ],
            'settings' => [
                'require_consent' => true,
                'spam_protection' => [
                    'honeypot_enabled' => true,
                    'min_time_seconds' => 0, // 0 for instant test execution
                ],
            ],
        ]);

        $page = LandingPage::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Marketing Page',
            'slug' => 'marketing-offer',
            'status' => 'published',
            'sections' => [['id' => 's1', 'type' => 'hero', 'headline' => 'Special Offer']],
        ]);

        // Submit via public endpoint
        $submitRes = $this->postJson("/api/v1/public/forms/{$form->slug}/submit-custom", [
            'name' => 'Rajesh Sharma',
            'email' => 'rajesh.sharma@tata.com',
            'phone' => '+919876543210',
            'company' => 'Tata Communications',
            'requirement' => 'Interested in Omnichannel CRM and WhatsApp AI Agent deployment.',
            'consent_agreed' => true,
            'utm_source' => 'google_ads',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'q4_enterprise_search',
            'utm_term' => 'crm automation software',
            'landing_page_slug' => $page->slug,
        ]);

        $submitRes->assertStatus(200);
        $submitRes->assertJsonPath('data.status', 'success');

        // Verify form submission persisted
        $this->assertDatabaseHas('form_submissions', [
            'tenant_id' => $this->tenantA->id,
            'form_id' => $form->id,
            'consent_given' => true,
        ]);

        // Verify Lead automatically created in central CRM pipeline with UTM attribution
        $lead = Lead::where('tenant_id', $this->tenantA->id)
            ->where('email', 'rajesh.sharma@tata.com')
            ->first();

        $this->assertNotNull($lead);
        $this->assertEquals('Rajesh Sharma', $lead->name);
        $this->assertEquals('Tata Communications', $lead->company_name);
        $this->assertEquals('google_ads', $lead->utm_source);
        $this->assertEquals('cpc', $lead->utm_medium);
        $this->assertEquals('q4_enterprise_search', $lead->utm_campaign);
        $this->assertContains('web-form', $lead->tags);
        $this->assertContains('form:contact-us', $lead->tags);
        $this->assertContains('page:marketing-offer', $lead->tags);

        // Verify submission counter incremented
        $this->assertEquals(1, $form->fresh()->submission_count);
        $this->assertEquals(1, $page->fresh()->submission_count);
    }

    /** @test */
    public function it_blocks_bot_submissions_via_honeypot_spam_protection()
    {
        $form = Form::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Spam Protected Form',
            'slug' => 'spam-check-form',
            'status' => 'active',
            'schema' => [
                'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                ],
            ],
            'settings' => [
                'spam_protection' => [
                    'honeypot_enabled' => true,
                    'honeypot_field_name' => '_hp_verify',
                    'min_time_seconds' => 0,
                ],
            ],
        ]);

        // Bot fills hidden honeypot field `_hp_verify`
        $submitRes = $this->postJson("/api/v1/public/forms/{$form->slug}/submit-custom", [
            'name' => 'Scraper Bot',
            'email' => 'bot@automated-spam.xyz',
            '_hp_verify' => 'I am an automated spam bot',
        ]);

        $submitRes->assertStatus(200);
        $submitRes->assertJsonPath('data.status', 'spam_blocked');

        // Must NOT create Lead in CRM
        $this->assertDatabaseMissing('leads', [
            'tenant_id' => $this->tenantA->id,
            'email' => 'bot@automated-spam.xyz',
        ]);

        // Must record spam submission with spam_score = 1.00
        $this->assertDatabaseHas('form_submissions', [
            'tenant_id' => $this->tenantA->id,
            'form_id' => $form->id,
            'spam_score' => 1.00,
        ]);
    }

    /** @test */
    public function it_tracks_lawful_telemetry_events_and_calculates_conversion_rate()
    {
        $page = LandingPage::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Webinar Masterclass 2026',
            'slug' => 'webinar-masterclass-2026',
            'status' => 'published',
            'sections' => [
                ['id' => 's1', 'type' => 'hero', 'headline' => 'Join Free Masterclass'],
            ],
            'submission_count' => 10,
        ]);

        // 1. Public Page View
        $viewRes = $this->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)'])
            ->getJson("/api/v1/public/landing-pages/{$page->slug}?utm_source=linkedin&utm_campaign=webinar");

        $viewRes->assertStatus(200);
        $this->assertEquals(1, $page->fresh()->view_count);

        // 2. CTA Click Event
        $ctaRes = $this->postJson("/api/v1/public/landing-pages/{$page->slug}/cta-click", [
            'cta_text' => 'Reserve Seat Now',
            'cta_target' => '#registration_form',
            'device_type' => 'mobile',
        ]);
        $ctaRes->assertStatus(200);

        // 3. Analytics Endpoint
        $analyticsRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->getJson("/api/v1/crm/landing-pages/{$page->id}/analytics");

        $analyticsRes->assertStatus(200);
        $analyticsRes->assertJsonPath('data.metrics.views', 1);
        $analyticsRes->assertJsonPath('data.metrics.cta_clicks', 1);
        $analyticsRes->assertJsonPath('data.metrics.submissions', 10);
        // Conversion rate = (10 / 1) * 100 = 1000%
        $this->assertGreaterThan(0, $analyticsRes->json('data.metrics.conversion_rate'));
    }

    /** @test */
    public function it_strictly_enforces_cross_tenant_isolation_on_landing_pages_and_forms()
    {
        $pageA = LandingPage::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Secret Page A',
            'slug' => 'secret-page-a',
            'status' => 'draft',
            'sections' => [['id' => 's1', 'type' => 'hero', 'headline' => 'A']],
        ]);

        $userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'current_tenant_id' => $this->tenantB->id,
            'name' => 'Zenith User',
            'email' => 'user@zenith.com',
            'password' => bcrypt('Pass123!'),
            'role' => 'Business Admin',
        ]);
        $tokenB = $userB->createToken('token_b')->plainTextToken;

        // User B cannot access Tenant A's landing page (Forbidden or Not Found)
        $res = $this->withHeader('Authorization', "Bearer {$tokenB}")
            ->getJson("/api/v1/crm/landing-pages/{$pageA->id}");

        $this->assertContains($res->status(), [403, 404]);

        // User B cannot publish Tenant A's landing page
        $publishRes = $this->withHeader('Authorization', "Bearer {$tokenB}")
            ->postJson("/api/v1/crm/landing-pages/{$pageA->id}/publish");

        $this->assertContains($publishRes->status(), [403, 404]);
    }
}
