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
        // 1. Extend whatsapp_conversations for Omnichannel (WhatsApp, Email, SMS, Calls, WebChat)
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_conversations', 'whatsapp_phone_number_id')) {
                $table->foreignId('whatsapp_phone_number_id')->nullable()->change();
            }

            if (!Schema::hasColumn('whatsapp_conversations', 'channel')) {
                $table->string('channel', 30)->default('whatsapp')->after('tenant_id');
                $table->string('customer_email', 150)->nullable()->after('phone_number');
                $table->string('subject', 255)->nullable()->after('customer_name');
                $table->string('channel_account_id', 100)->nullable()->after('subject');
                $table->foreignId('assigned_team_id')->nullable()->after('assigned_user_id')->constrained('sales_teams')->nullOnDelete();
                $table->json('metadata')->nullable()->after('tags');

                $table->index(['tenant_id', 'channel']);
                $table->index(['tenant_id', 'customer_email']);
                $table->index(['tenant_id', 'assigned_team_id']);
            }
        });

        // 2. Extend whatsapp_messages for Omnichannel Messaging & Email Threading
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_messages', 'channel')) {
                $table->string('channel', 30)->nullable()->after('conversation_id');
                $table->string('subject', 255)->nullable()->after('message_type');
                $table->string('sender_identity', 150)->nullable()->after('user_id');
                $table->string('recipient_identity', 150)->nullable()->after('sender_identity');
                $table->string('in_reply_to', 255)->nullable()->after('provider_message_id');
                $table->json('attachments')->nullable()->after('media_url');
                $table->json('channel_metadata')->nullable()->after('raw_payload');

                $table->index(['tenant_id', 'channel']);
                $table->index(['tenant_id', 'in_reply_to']);
            }
        });

        // 3. Email Accounts (SMTP / IMAP / SendGrid / SES)
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('email_address', 150);
            $table->string('provider', 50)->default('smtp_imap'); // smtp_imap, sendgrid, mailgun, ses, gmail_oauth
            $table->string('smtp_host', 150)->nullable();
            $table->unsignedSmallInteger('smtp_port')->default(587);
            $table->string('smtp_encryption', 20)->default('tls'); // tls, ssl, none
            $table->string('smtp_username', 150)->nullable();
            $table->text('smtp_password')->nullable(); // Encrypted at rest
            $table->string('imap_host', 150)->nullable();
            $table->unsignedSmallInteger('imap_port')->default(993);
            $table->string('imap_encryption', 20)->default('ssl');
            $table->string('imap_username', 150)->nullable();
            $table->text('imap_password')->nullable(); // Encrypted at rest
            $table->text('signature_html')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'email_address']);
            $table->index(['tenant_id', 'is_active']);
        });

        // 4. SMS Provider Configurations (Twilio, Exotel, Fast2SMS, Custom HTTP)
        Schema::create('sms_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('provider', 50)->default('twilio'); // twilio, exotel, fast2sms, custom_http
            $table->string('account_sid', 150)->nullable();
            $table->text('auth_token')->nullable(); // Encrypted at rest
            $table->string('api_key', 150)->nullable();
            $table->string('sender_id', 20)->nullable(); // 6-character DLT Header ID in India
            $table->string('dlt_entity_id', 50)->nullable(); // DLT Principal Entity ID
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'provider']);
            $table->index(['tenant_id', 'is_active']);
        });

        // 5. Website Chat Widgets
        Schema::create('website_chat_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('widget_key', 64)->unique();
            $table->string('brand_color', 30)->default('#4f46e5');
            $table->string('brand_title', 150)->default('Customer Support');
            $table->string('brand_logo_url', 500)->nullable();
            $table->text('welcome_message')->nullable();
            $table->json('pre_chat_fields')->nullable(); // name, email, phone, requirement
            $table->string('agent_availability_mode', 30)->default('hybrid'); // always_ai, hybrid, business_hours
            $table->boolean('is_active')->default(true);
            $table->json('allowed_domains')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'widget_key']);
            $table->index(['tenant_id', 'is_active']);
        });

        // 6. Communication Suppressions (Unsubscribe, Bounces, Spam, DND)
        Schema::create('communication_suppressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('channel', 30)->default('all'); // email, sms, whatsapp, all
            $table->string('identifier', 150); // email or phone
            $table->string('reason', 50)->default('unsubscribe'); // unsubscribe, bounce, spam_complaint, dnd
            $table->timestamp('suppressed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['tenant_id', 'channel', 'identifier'], 'comm_suppress_unique');
            $table->index(['tenant_id', 'identifier']);
        });

        // 7. Customer Identity Resolution & Merges Pending Review
        Schema::create('identity_merges_pending', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('primary_type', 30); // lead, contact, customer
            $table->unsignedBigInteger('primary_id');
            $table->string('secondary_type', 30); // lead, contact, customer
            $table->unsignedBigInteger('secondary_id');
            $table->string('match_criteria', 50); // email, phone, name_similarity
            $table->unsignedTinyInteger('confidence_score')->default(80); // 0 - 100
            $table->string('status', 30)->default('pending_review'); // pending_review, merged, rejected
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'primary_type', 'primary_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('identity_merges_pending');
        Schema::dropIfExists('communication_suppressions');
        Schema::dropIfExists('website_chat_widgets');
        Schema::dropIfExists('sms_configurations');
        Schema::dropIfExists('email_accounts');

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_messages', 'channel')) {
                $table->dropIndex(['tenant_id', 'channel']);
                $table->dropIndex(['tenant_id', 'in_reply_to']);
                $table->dropColumn(['channel', 'subject', 'sender_identity', 'recipient_identity', 'in_reply_to', 'attachments', 'channel_metadata']);
            }
        });

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_conversations', 'channel')) {
                $table->dropIndex(['tenant_id', 'channel']);
                $table->dropIndex(['tenant_id', 'customer_email']);
                $table->dropIndex(['tenant_id', 'assigned_team_id']);
                $table->dropConstrainedForeignId('assigned_team_id');
                $table->dropColumn(['channel', 'customer_email', 'subject', 'channel_account_id', 'metadata']);
            }
        });
    }
};
