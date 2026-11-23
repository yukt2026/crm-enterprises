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
        // 1. WhatsApp Business Accounts (WABA)
        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('waba_id', 100);
            $table->string('app_id', 100)->nullable();
            $table->text('system_user_token'); // Encrypted access token
            $table->string('webhook_verify_token', 150);
            $table->string('status', 30)->default('connected'); // connected, disconnected, restricted
            $table->string('business_verification_status', 30)->default('verified'); // verified, pending, failed
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'waba_id']);
            $table->index(['tenant_id', 'status']);
        });

        // 2. WhatsApp Connected Phone Numbers
        Schema::create('whatsapp_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('whatsapp_account_id')->constrained('whatsapp_accounts')->cascadeOnDelete();
            $table->string('phone_number_id', 100);
            $table->string('display_phone_number', 50);
            $table->string('normalized_number', 50);
            $table->string('verified_name', 150)->nullable();
            $table->string('quality_rating', 20)->default('GREEN'); // GREEN, YELLOW, RED, UNKNOWN
            $table->string('status', 30)->default('CONNECTED'); // CONNECTED, PENDING, DISCONNECTED, FLAGGED
            $table->foreignId('default_assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'phone_number_id']);
            $table->index(['tenant_id', 'normalized_number']);
        });

        // 3. WhatsApp Conversations (Inbox Threads)
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('whatsapp_phone_number_id')->nullable()->constrained('whatsapp_phone_numbers')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('phone_number', 50); // Normalized E.164 customer phone
            $table->string('customer_name', 150)->nullable();
            $table->string('status', 30)->default('open'); // open, pending, resolved
            $table->boolean('is_ai_handled')->default(false);
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 20)->default('medium'); // low, medium, high, urgent
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('window_expires_at')->nullable(); // Meta 24-hour messaging window expiration
            $table->timestamp('last_message_at')->nullable();
            $table->text('last_message_preview')->nullable();
            $table->string('last_message_direction', 20)->default('inbound');
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'phone_number']);
            $table->index(['tenant_id', 'assigned_user_id']);
            $table->index(['tenant_id', 'last_message_at']);
        });

        // 4. WhatsApp Messages (Chat Bubbles)
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->string('provider_message_id', 150)->nullable(); // Meta wamid.HB...
            $table->string('direction', 20)->default('inbound'); // inbound, outbound
            $table->string('message_type', 30)->default('text'); // text, image, document, audio, video, template, interactive
            $table->string('sender_actor', 30)->default('customer'); // customer, human, ai, automation
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('content')->nullable();
            $table->string('media_url', 500)->nullable();
            $table->string('media_id', 150)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->string('template_name', 100)->nullable();
            $table->json('template_params')->nullable();
            $table->string('delivery_status', 30)->default('pending'); // pending, sent, delivered, read, failed
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'conversation_id']);
            $table->index(['tenant_id', 'provider_message_id']);
            $table->index(['tenant_id', 'delivery_status']);
        });

        // 5. WhatsApp Internal Notes (Team-Only Comments)
        Schema::create('whatsapp_conversation_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->json('mentions')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'conversation_id']);
        });

        // 6. WhatsApp Approved Message Templates
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('whatsapp_account_id')->constrained('whatsapp_accounts')->cascadeOnDelete();
            $table->string('template_name', 100);
            $table->string('category', 50)->default('UTILITY'); // MARKETING, UTILITY, AUTHENTICATION
            $table->string('language', 20)->default('en_US');
            $table->string('status', 30)->default('APPROVED'); // APPROVED, PENDING, REJECTED, PAUSED
            $table->string('header_type', 30)->default('NONE');
            $table->text('body_text');
            $table->string('footer_text', 255)->nullable();
            $table->json('buttons')->nullable();
            $table->json('variables_schema')->nullable();
            $table->string('meta_template_id', 100)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'template_name']);
            $table->index(['tenant_id', 'status']);
        });

        // 7. WhatsApp Opt-Out Suppression List
        Schema::create('whatsapp_opt_outs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('phone_number', 50); // Normalized E.164
            $table->string('opt_out_type', 50)->default('customer_stop'); // customer_stop, manual, compliance
            $table->string('reason', 255)->nullable();
            $table->timestamp('opted_out_at')->useCurrent();
            $table->timestamps();

            $table->unique(['tenant_id', 'phone_number'], 'wa_opt_outs_tenant_phone_uniq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_opt_outs');
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('whatsapp_conversation_notes');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('whatsapp_phone_numbers');
        Schema::dropIfExists('whatsapp_accounts');
    }
};
