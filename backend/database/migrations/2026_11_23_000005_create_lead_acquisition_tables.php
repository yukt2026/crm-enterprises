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
        // 1. Connected Lead Sources & Ad Accounts
        Schema::create('lead_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('tenants')->onDelete('cascade');
            $table->string('provider', 50); // google_ads, meta_ads, youtube, website_form, hosted_form, google_forms, whatsapp, webhook, public_api
            $table->string('name', 150);
            $table->string('account_id', 150)->nullable(); // e.g. Google Customer ID / Meta Ad Account ID / Page ID
            $table->string('status', 50)->default('disconnected'); // connected, disconnected, error, pending_auth
            $table->text('credentials')->nullable(); // Encrypted credentials/tokens (access_token, refresh_token, client_secret, webhook_secret, etc.)
            $table->json('config')->nullable(); // Subscribed forms, campaigns, pages, webhook_verify_token, etc.
            $table->string('sync_status', 50)->default('idle'); // idle, syncing, failed, success
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['workspace_id', 'provider']);
            $table->index(['workspace_id', 'status']);
            $table->index('account_id');
        });

        // 2. Lead Forms (Hosted, Website Embedded, or Synced Provider Forms)
        Schema::create('lead_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('lead_source_id')->nullable()->constrained('lead_sources')->onDelete('cascade');
            $table->string('provider', 50)->default('website'); // google_ads, meta_ads, website, hosted, google_forms, whatsapp
            $table->string('name', 150);
            $table->string('external_form_id', 150)->nullable(); // Meta Form ID or Google Lead Form Asset ID
            $table->string('public_uuid', 64)->unique(); // Public submission token / hosted URL slug
            $table->json('fields_config')->nullable(); // Array of fields: [{name, label, type, required, options}]
            $table->boolean('is_active')->default(true);
            $table->string('campaign_id', 150)->nullable();
            $table->string('campaign_name', 150)->nullable();
            $table->string('ad_id', 150)->nullable();
            $table->unsignedInteger('submissions_count')->default(0);
            $table->timestamp('last_submission_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'provider']);
            $table->index('external_form_id');
        });

        // 3. Tenant Field Mappings
        Schema::create('lead_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('lead_source_id')->nullable()->constrained('lead_sources')->onDelete('cascade');
            $table->string('provider', 50); // google_ads, meta_ads, website, whatsapp, generic
            $table->string('source_field', 100); // Inbound field name (e.g. full_name, phone_number, user_email)
            $table->string('crm_field', 100); // CRM lead field (e.g. name, phone, email, company_name, requirement)
            $table->string('transform_rule', 50)->nullable(); // trim, lowercase, uppercase, phone_normalize, default
            $table->string('default_value')->nullable();
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->index(['workspace_id', 'provider']);
            $table->index(['workspace_id', 'lead_source_id']);
        });

        // 4. Ingestion Events Stream & Audit Trail
        Schema::create('lead_ingestion_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('lead_source_id')->nullable()->constrained('lead_sources')->onDelete('set null');
            $table->string('provider', 50);
            $table->string('event_id', 150); // External webhook event id or unique idempotency key
            $table->string('status', 50)->default('pending'); // pending, processing, processed, failed, duplicate, skipped
            $table->longText('raw_payload')->nullable();
            $table->json('normalized_payload')->nullable();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->onDelete('set null');
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->unsignedTinyInteger('max_retries')->default(3);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'provider']);
            $table->index(['workspace_id', 'event_id']);
            $table->index(['workspace_id', 'status']);
        });

        // 5. Ad Campaign Spend & Performance Metrics
        Schema::create('ad_campaign_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('lead_source_id')->nullable()->constrained('lead_sources')->onDelete('cascade');
            $table->string('provider', 50); // google_ads, meta_ads, youtube
            $table->string('campaign_id', 150);
            $table->string('campaign_name', 200);
            $table->string('ad_group_id', 150)->nullable();
            $table->string('ad_group_name', 200)->nullable();
            $table->string('ad_id', 150)->nullable();
            $table->string('ad_name', 200)->nullable();
            $table->date('date');
            $table->decimal('spend', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('leads_count')->default(0);
            $table->decimal('cost_per_lead', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'provider', 'date']);
            $table->index(['workspace_id', 'campaign_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_campaign_metrics');
        Schema::dropIfExists('lead_ingestion_events');
        Schema::dropIfExists('lead_field_mappings');
        Schema::dropIfExists('lead_forms');
        Schema::dropIfExists('lead_sources');
    }
};
