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
        // 1. Tenant Connected Integrations
        Schema::create('tenant_integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('provider', 64)->index(); // google_ads, meta_ads, ga4, google_forms, whatsapp_cloud, smtp_email, twilio_sms, exotel_voice, twilio_voice, razorpay, google_calendar, aws_s3
            $table->string('name', 255);
            $table->string('status', 32)->default('connected')->index(); // connected, disconnected, expired, error
            $table->string('auth_type', 32)->default('oauth2'); // oauth2, api_key, credentials, webhook_only
            $table->text('encrypted_credentials'); // encrypted JSON payload containing tokens, keys, secrets
            $table->json('scopes')->nullable(); // granted permissions / scopes
            $table->json('metadata')->nullable(); // provider account id, currency, external email, webhook endpoint, etc.
            $table->unsignedBigInteger('connected_by_user_id')->nullable();
            $table->timestamp('connected_at')->useCurrent();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('next_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->boolean('is_sync_enabled')->default(true);
            $table->integer('sync_frequency_minutes')->default(60);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('connected_by_user_id')->references('id')->on('users')->onDelete('set null');
        });

        // 2. Integration Data Synchronization Logs
        Schema::create('integration_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('integration_id')->index();
            $table->string('job_id', 128)->nullable();
            $table->string('sync_type', 32)->default('incremental'); // full, incremental, webhook_triggered, manual
            $table->string('status', 32)->default('pending')->index(); // pending, running, success, failed, partial
            $table->integer('items_processed')->default(0);
            $table->integer('items_failed')->default(0);
            $table->text('error_details')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('integration_id')->references('id')->on('tenant_integrations')->onDelete('cascade');
        });

        // 3. Tenant Public API Keys
        Schema::create('tenant_api_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name', 255);
            $table->string('key_prefix', 16)->index(); // e.g., crm_live_abc123
            $table->string('key_hash', 64)->unique(); // sha256 of complete token
            $table->text('encrypted_secret')->nullable(); // encrypted raw key for one-time recovery if authorized
            $table->json('scopes'); // ['leads:read', 'leads:write', 'deals:read', 'webhooks:manage']
            $table->json('allowed_ips')->nullable(); // IP whitelist
            $table->integer('rate_limit_per_minute')->default(60);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->boolean('is_revoked')->default(false)->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        // 4. API Request Logs (Audit & Rate Limiting)
        Schema::create('tenant_api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('api_key_id')->nullable()->index();
            $table->string('method', 10);
            $table->string('endpoint', 255);
            $table->integer('status_code');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->integer('response_time_ms')->default(0);
            $table->text('request_payload_summary')->nullable(); // Sanitized, never logs keys
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('api_key_id')->references('id')->on('tenant_api_keys')->onDelete('set null');
        });

        // 5. Outbound Webhook Subscriptions
        Schema::create('outbound_webhooks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('name', 255);
            $table->string('url', 1024); // Must be HTTPS destination
            $table->string('signing_secret', 128); // HMAC secret for X-CRM-Signature
            $table->json('events'); // ['lead.created', 'deal.won', 'invoice.paid']
            $table->boolean('is_active')->default(true)->index();
            $table->integer('retry_limit')->default(5);
            $table->integer('timeout_seconds')->default(10);
            $table->json('headers')->nullable();
            $table->timestamp('last_delivery_at')->nullable();
            $table->string('last_delivery_status', 32)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // 6. Outbound Webhook Delivery Logs & Retries
        Schema::create('webhook_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('outbound_webhook_id')->index();
            $table->string('event_id', 64)->index(); // UUID replay protection
            $table->string('event_type', 64);
            $table->json('payload');
            $table->string('destination_url', 1024);
            $table->integer('attempt_number')->default(1);
            $table->string('status', 32)->default('pending')->index(); // pending, success, failed, retrying
            $table->integer('response_status_code')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('outbound_webhook_id')->references('id')->on('outbound_webhooks')->onDelete('cascade');
        });

        // 7. OAuth State Storage (CSRF & State Validation)
        Schema::create('oauth_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id');
            $table->string('provider', 64);
            $table->string('state_token', 128)->unique();
            $table->string('redirect_url', 1024);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_states');
        Schema::dropIfExists('webhook_delivery_logs');
        Schema::dropIfExists('outbound_webhooks');
        Schema::dropIfExists('tenant_api_request_logs');
        Schema::dropIfExists('tenant_api_keys');
        Schema::dropIfExists('integration_sync_logs');
        Schema::dropIfExists('tenant_integrations');
    }
};
