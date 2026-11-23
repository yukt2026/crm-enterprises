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
        // 1. Tracking Settings Table
        if (!Schema::hasTable('tracking_settings')) {
            Schema::create('tracking_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('tracking_key', 64)->unique();
                $table->boolean('is_enabled')->default(true);
                $table->boolean('require_consent')->default(false); // GDPR / ePrivacy mode
                $table->json('allowed_domains')->nullable(); // Whitelisted hostnames
                $table->integer('data_retention_days')->default(90);
                $table->json('enabled_events')->nullable(); // Configured allowed events
                $table->boolean('anonymize_ip')->default(true);
                $table->boolean('bot_filtering')->default(true);

                // Google Analytics 4 Connection Config
                $table->string('ga4_property_id', 50)->nullable(); // e.g. properties/123456789
                $table->string('ga4_measurement_id', 50)->nullable(); // e.g. G-XXXXXXXXXX
                $table->text('ga4_api_secret')->nullable(); // Measurement Protocol API secret (encrypted)
                $table->text('ga4_oauth_credentials')->nullable(); // OAuth tokens (encrypted)
                $table->timestamp('ga4_connected_at')->nullable();
                $table->json('ga4_cached_properties')->nullable();

                $table->timestamps();

                $table->index(['tenant_id', 'is_enabled']);
            });
        }

        // 2. Tracking Events Table
        if (!Schema::hasTable('tracking_events')) {
            Schema::create('tracking_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('tracking_key', 64)->index();
                $table->string('session_id', 64)->index();
                $table->string('event_name', 50)->index(); // page_view, landing_page_view, form_view, form_submission, cta_click, campaign_parameters, referrer, conversion_event
                $table->string('event_id', 64)->nullable()->index(); // Client-side UUID for deduplication
                $table->text('page_url')->nullable();
                $table->string('page_title', 255)->nullable();
                $table->text('referrer')->nullable();
                $table->string('utm_source', 100)->nullable()->index();
                $table->string('utm_medium', 100)->nullable();
                $table->string('utm_campaign', 150)->nullable()->index();
                $table->string('utm_term', 150)->nullable();
                $table->string('utm_content', 150)->nullable();
                $table->boolean('consent_given')->default(true);
                $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->json('metadata')->nullable(); // CTA label, element ID, form meta, conversion value
                $table->string('ip_anonymized', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->boolean('is_bot')->default(false);
                $table->timestamps();

                $table->index(['tenant_id', 'created_at']);
                $table->index(['tenant_id', 'session_id']);
                $table->index(['session_id', 'event_id']);
                $table->index(['tenant_id', 'lead_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_events');
        Schema::dropIfExists('tracking_settings');
    }
};
