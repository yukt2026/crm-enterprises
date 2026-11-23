<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Custom Domains
        Schema::create('custom_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->string('verification_token')->index();
            $table->string('verification_type')->default('txt'); // txt, cname
            $table->string('verification_status')->default('pending')->index(); // pending, verified, failed
            $table->string('ssl_status')->default('pending')->index(); // pending, active, failed
            $table->timestamp('verified_at')->nullable();
            $table->text('last_verification_error')->nullable();
            $table->timestamps();
        });

        // 2. Landing Pages
        Schema::create('landing_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->index();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index(); // draft, published, unpublished, archived
            $table->foreignId('custom_domain_id')->nullable()->constrained('custom_domains')->nullOnDelete();
            $table->integer('current_version')->default(1);
            $table->json('branding')->nullable(); // colors, fonts, logo, favicon
            $table->json('seo_metadata')->nullable(); // title, description, og_image, keywords
            $table->json('sections')->nullable(); // array of modular sections
            $table->json('settings')->nullable(); // custom css/js, tracking IDs
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('submission_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
        });

        // 3. Landing Page Revisions
        Schema::create('landing_page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('landing_page_id')->constrained('landing_pages')->cascadeOnDelete();
            $table->integer('version');
            $table->json('snapshot'); // sections, branding, seo
            $table->string('changelog')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['landing_page_id', 'version']);
        });

        // 4. Custom Lead Capture Forms
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->index();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index(); // active, inactive
            $table->json('schema'); // fields, conditional_rules, validations
            $table->json('settings')->nullable(); // submit_btn_text, success_msg, redirect_url, consent_text, spam_protection
            $table->unsignedBigInteger('submission_count')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
        });

        // 5. Form Submissions
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('landing_page_id')->nullable()->constrained('landing_pages')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->json('data'); // raw submitted data
            $table->json('utm_data')->nullable(); // utm_source, medium, campaign, term, content
            $table->string('referrer')->nullable();
            $table->boolean('consent_given')->default(true);
            $table->text('consent_text')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->decimal('spam_score', 3, 2)->default(0.00);
            $table->timestamps();

            $table->index(['tenant_id', 'form_id']);
        });

        // 6. Landing Page Analytics Events
        Schema::create('landing_page_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('landing_page_id')->constrained('landing_pages')->cascadeOnDelete();
            $table->string('event_type')->index(); // page_view, cta_click, form_view
            $table->string('device_type')->nullable(); // desktop, mobile, tablet
            $table->string('referrer')->nullable();
            $table->string('utm_source')->nullable()->index();
            $table->string('utm_medium')->nullable()->index();
            $table->string('utm_campaign')->nullable()->index();
            $table->json('meta')->nullable(); // target cta, element id, etc.
            $table->timestamps();

            $table->index(['tenant_id', 'landing_page_id', 'event_type'], 'lpa_tenant_page_event_idx');
        });

        // 7. Landing Page Templates
        Schema::create('landing_page_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->index(); // lead_generation, product_launch, webinar, consultation
            $table->text('description')->nullable();
            $table->string('preview_image')->nullable();
            $table->json('branding')->nullable();
            $table->json('sections');
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landing_page_templates');
        Schema::dropIfExists('landing_page_analytics');
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('forms');
        Schema::dropIfExists('landing_page_revisions');
        Schema::dropIfExists('landing_pages');
        Schema::dropIfExists('custom_domains');
    }
};
