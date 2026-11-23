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
        // 1. Branches Table (for branch-based routing & channel assignment)
        if (!Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('name', 150);
                $table->string('code', 50)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('state', 100)->nullable();
                $table->string('country', 10)->default('IN');
                $table->string('timezone', 60)->default('Asia/Kolkata');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
            });
        }

        // 2. Telegram Accounts Table (official Telegram Bot API)
        if (!Schema::hasTable('telegram_accounts')) {
            Schema::create('telegram_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('bot_name', 150);
                $table->string('bot_username', 100)->nullable();
                $table->text('bot_token'); // Encrypted at rest
                $table->boolean('is_connected')->default(false);
                $table->string('webhook_url', 255)->nullable();
                $table->string('webhook_secret_token', 100)->nullable();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->json('business_hours')->nullable();
                $table->boolean('ai_enabled')->default(false);
                $table->foreignId('ai_agent_id')->nullable()->constrained('whatsapp_ai_agents')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'is_connected']);
                $table->index(['tenant_id', 'bot_username']);
            });
        }

        // 3. Centralized Channel Settings & Health Tracking Table
        if (!Schema::hasTable('omnichannel_channel_settings')) {
            Schema::create('omnichannel_channel_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('channel', 30); // whatsapp, facebook, instagram, email, sms, webchat, call, telegram
                $table->string('channel_account_id', 100)->nullable();
                $table->string('name', 150)->nullable();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->json('business_hours')->nullable(); // e.g. { timezone: "Asia/Kolkata", days: [1,2,3,4,5], start: "09:00", end: "18:00" }
                $table->boolean('is_active')->default(true);
                $table->string('health_status', 30)->default('healthy'); // healthy, degraded, failed, disconnected
                $table->timestamp('last_health_check_at')->nullable();
                $table->json('last_error')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'channel', 'channel_account_id'], 'chan_tenant_channel_acc_uniq');
                $table->index(['tenant_id', 'channel']);
                $table->index(['tenant_id', 'health_status']);
            });
        }

        // 4. Omnichannel Routing Rules Table
        if (!Schema::hasTable('omnichannel_routing_rules')) {
            Schema::create('omnichannel_routing_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('name', 150);
                $table->string('rule_type', 50); // round_robin, least_active, branch, department, language, lead_source, product, working_hours
                $table->string('channel', 30)->default('all'); // all or specific channel
                $table->json('conditions')->nullable(); // e.g. { branch_id: 1, language: "en", lead_source: "google_ads" }
                $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('target_team_id')->nullable()->constrained('sales_teams')->nullOnDelete();
                $table->integer('priority')->default(10); // Lower number = higher evaluation priority
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'is_active', 'priority']);
                $table->index(['tenant_id', 'channel']);
            });
        }

        // 5. Omnichannel SLA Policies Table
        if (!Schema::hasTable('omnichannel_sla_policies')) {
            Schema::create('omnichannel_sla_policies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('name', 150);
                $table->string('priority', 30)->default('all'); // all, low, medium, high, urgent
                $table->string('channel', 30)->default('all'); // all or specific channel
                $table->unsignedInteger('first_response_target_minutes')->default(60);
                $table->unsignedInteger('resolution_target_minutes')->default(1440); // 24 hours
                $table->boolean('business_hours_enabled')->default(true);
                $table->json('business_hours')->nullable();
                $table->unsignedInteger('escalation_target_minutes')->nullable();
                $table->foreignId('escalate_to_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'priority', 'channel']);
                $table->index(['tenant_id', 'is_active']);
            });
        }

        // 6. Extend whatsapp_conversations for Locking, SLA tracking, Archival and Branch/Department
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_conversations', 'locked_by_user_id')) {
                $table->foreignId('locked_by_user_id')->nullable()->after('assigned_team_id')->constrained('users')->nullOnDelete();
                $table->timestamp('locked_at')->nullable()->after('locked_by_user_id');
                $table->foreignId('sla_policy_id')->nullable()->after('resolution_time_seconds')->constrained('omnichannel_sla_policies')->nullOnDelete();
                $table->string('sla_status', 30)->default('on_track')->after('sla_policy_id'); // on_track, warning, breached
                $table->timestamp('first_response_due_at')->nullable()->after('sla_status');
                $table->timestamp('resolution_due_at')->nullable()->after('first_response_due_at');
                $table->boolean('is_archived')->default(false)->after('status');
                $table->foreignId('branch_id')->nullable()->after('is_archived')->constrained('branches')->nullOnDelete();
                $table->foreignId('department_id')->nullable()->after('branch_id')->constrained('departments')->nullOnDelete();
                $table->string('language', 20)->nullable()->after('department_id');

                $table->index(['tenant_id', 'sla_status']);
                $table->index(['tenant_id', 'is_archived']);
                $table->index(['tenant_id', 'branch_id']);
                $table->index(['tenant_id', 'department_id']);
            }
        });

        // 7. Omnichannel Agent Presences & Collision Tracking Table
        if (!Schema::hasTable('omnichannel_agent_presences')) {
            Schema::create('omnichannel_agent_presences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('conversation_id')->nullable()->constrained('whatsapp_conversations')->cascadeOnDelete();
                $table->boolean('is_typing')->default(false);
                $table->boolean('is_online')->default(true);
                $table->timestamp('last_seen_at')->useCurrent();
                $table->timestamps();

                $table->unique(['tenant_id', 'user_id', 'conversation_id'], 'presence_tenant_user_conv_unique');
                $table->index(['tenant_id', 'conversation_id']);
                $table->index(['tenant_id', 'last_seen_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('omnichannel_agent_presences');

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_conversations', 'locked_by_user_id')) {
                $table->dropForeign(['locked_by_user_id']);
                $table->dropForeign(['sla_policy_id']);
                $table->dropForeign(['branch_id']);
                $table->dropForeign(['department_id']);
                $table->dropColumn([
                    'locked_by_user_id',
                    'locked_at',
                    'sla_policy_id',
                    'sla_status',
                    'first_response_due_at',
                    'resolution_due_at',
                    'is_archived',
                    'branch_id',
                    'department_id',
                    'language',
                ]);
            }
        });

        Schema::dropIfExists('omnichannel_sla_policies');
        Schema::dropIfExists('omnichannel_routing_rules');
        Schema::dropIfExists('omnichannel_channel_settings');
        Schema::dropIfExists('telegram_accounts');
        Schema::dropIfExists('branches');
    }
};
