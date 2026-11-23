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
        // 1. Social Channel Accounts (Facebook Pages & Instagram Professional Accounts)
        if (!Schema::hasTable('social_channel_accounts')) {
            Schema::create('social_channel_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('provider', 30); // 'facebook' or 'instagram'
                $table->string('page_id', 100)->index();
                $table->string('page_name', 200);
                $table->text('page_access_token'); // Encrypted Page Access Token
                $table->string('instagram_business_id', 100)->nullable()->index();
                $table->string('instagram_username', 100)->nullable();
                $table->boolean('is_connected')->default(true);
                $table->timestamp('connected_at')->nullable();
                $table->timestamp('token_expires_at')->nullable();
                $table->boolean('webhook_subscribed')->default(false);
                $table->string('webhook_verify_token', 100)->nullable();
                $table->json('permissions')->nullable();
                $table->json('metadata')->nullable(); // category, picture_url, followers_count

                // AI Omnichannel Support Configuration
                $table->boolean('ai_enabled')->default(true);
                $table->foreignId('ai_agent_id')->nullable()->constrained('whatsapp_ai_agents')->nullOnDelete();
                $table->text('ai_instructions')->nullable();
                $table->json('ai_handover_keywords')->nullable();

                $table->timestamps();

                $table->index(['tenant_id', 'provider', 'is_connected']);
                $table->index(['page_id', 'is_connected']);
            });
        }

        // 2. Customer Social Identities (Connecting PSID and IGSID to CRM Customer/Lead)
        if (!Schema::hasTable('customer_social_identities')) {
            Schema::create('customer_social_identities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
                $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
                $table->string('provider', 30); // 'facebook' or 'instagram'
                $table->string('scoped_id', 100)->index(); // PSID or IGSID
                $table->string('display_name', 200)->nullable();
                $table->string('username', 100)->nullable();
                $table->text('profile_pic_url')->nullable();
                $table->boolean('is_verified')->default(false);
                $table->foreignId('merged_with_id')->nullable()->constrained('customer_social_identities')->nullOnDelete();
                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->index(['tenant_id', 'scoped_id']);
                $table->index(['tenant_id', 'customer_id']);
                $table->index(['tenant_id', 'lead_id']);
            });
        }

        // 3. Social Webhook Event Audit & Idempotency Log
        if (!Schema::hasTable('social_webhook_events')) {
            Schema::create('social_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->string('provider', 30); // 'facebook' or 'instagram'
                $table->string('page_id', 100)->index();
                $table->string('event_id', 150)->nullable()->index(); // Meta message mid or delivery watermark
                $table->string('event_type', 50)->index(); // messages, messaging_postbacks, message_deliveries, message_reads
                $table->json('payload');
                $table->string('status', 30)->default('processed'); // processed, ignored, failed, duplicate
                $table->text('error_message')->nullable();

                $table->timestamps();

                $table->index(['tenant_id', 'page_id']);
                $table->index(['page_id', 'event_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_webhook_events');
        Schema::dropIfExists('customer_social_identities');
        Schema::dropIfExists('social_channel_accounts');
    }
};
