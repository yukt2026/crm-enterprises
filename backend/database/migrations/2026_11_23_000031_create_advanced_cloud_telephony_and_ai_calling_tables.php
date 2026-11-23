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
        // 1. Extend `voice_calls` with Deals, Companies, Branches, Quality Reviews & Idempotency
        Schema::table('voice_calls', function (Blueprint $table) {
            if (!Schema::hasColumn('voice_calls', 'deal_id')) {
                $table->foreignId('deal_id')->nullable()->after('contact_id')->constrained('deals')->onDelete('set null');
            }
            if (!Schema::hasColumn('voice_calls', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('deal_id')->constrained('companies')->onDelete('set null');
            }
            if (!Schema::hasColumn('voice_calls', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('tenant_id')->index();
            }
            if (!Schema::hasColumn('voice_calls', 'call_type')) {
                $table->string('call_type', 32)->default('human')->after('direction'); // 'human', 'ai_voice', 'ivr'
            }
            if (!Schema::hasColumn('voice_calls', 'quality_score')) {
                $table->unsignedTinyInteger('quality_score')->nullable()->after('qualification_result'); // 1-5 rating
            }
            if (!Schema::hasColumn('voice_calls', 'manager_feedback')) {
                $table->text('manager_feedback')->nullable()->after('quality_score');
            }
            if (!Schema::hasColumn('voice_calls', 'recording_notice_played')) {
                $table->boolean('recording_notice_played')->default(true)->after('recording_duration_seconds');
            }
            if (!Schema::hasColumn('voice_calls', 'idempotency_key')) {
                $table->string('idempotency_key', 128)->nullable()->after('provider_call_id')->index();
            }
            if (!Schema::hasColumn('voice_calls', 'follow_up_required')) {
                $table->boolean('follow_up_required')->default(false)->after('next_follow_up_at');
            }
            if (!Schema::hasColumn('voice_calls', 'follow_up_notes')) {
                $table->text('follow_up_notes')->nullable()->after('follow_up_required');
            }
            if (!Schema::hasColumn('voice_calls', 'hangup_cause')) {
                $table->string('hangup_cause', 64)->nullable()->after('status');
            }
            if (!Schema::hasColumn('voice_calls', 'transfer_to_user_id')) {
                $table->foreignId('transfer_to_user_id')->nullable()->after('human_transferred')->constrained('users')->onDelete('set null');
            }
        });

        // 2. Extend `voice_ai_agents` with Purpose, Escalation Rules, Knowledge Base, Regional Dialects
        Schema::table('voice_ai_agents', function (Blueprint $table) {
            if (!Schema::hasColumn('voice_ai_agents', 'business_purpose')) {
                $table->string('business_purpose', 64)->default('lead_qualification')->after('description');
                // 'lead_qualification', 'customer_follow_up', 'appointment_confirmation', 'renewal_reminder', 'service_enquiry', 'payment_reminder', 'feedback_collection', 'incoming_enquiry'
            }
            if (!Schema::hasColumn('voice_ai_agents', 'escalation_rules')) {
                $table->json('escalation_rules')->nullable()->after('allowed_crm_actions');
            }
            if (!Schema::hasColumn('voice_ai_agents', 'business_hours')) {
                $table->json('business_hours')->nullable()->after('escalation_rules');
            }
            if (!Schema::hasColumn('voice_ai_agents', 'knowledge_base')) {
                $table->longText('knowledge_base')->nullable()->after('call_opening');
            }
            if (!Schema::hasColumn('voice_ai_agents', 'confidence_threshold')) {
                $table->decimal('confidence_threshold', 3, 2)->default(0.65)->after('max_call_duration_seconds');
            }
            if (!Schema::hasColumn('voice_ai_agents', 'regional_dialects')) {
                $table->json('regional_dialects')->nullable()->after('language');
            }
        });

        // 3. IVR Flows & DTMF Keypad Menus
        if (!Schema::hasTable('call_ivr_flows')) {
            Schema::create('call_ivr_flows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
                $table->foreignId('phone_number_id')->nullable()->constrained('telephony_phone_numbers')->onDelete('set null');
                $table->string('name', 128);
                $table->text('description')->nullable();
                $table->text('welcome_audio_text');
                $table->string('language', 32)->default('hi'); // 'hi', 'en', 'hinglish', etc.
                $table->json('business_hours')->nullable(); // e.g. {"open": "09:00", "close": "18:00", "days": [1,2,3,4,5,6], "timezone": "Asia/Kolkata"}
                $table->json('holiday_rules')->nullable(); // dates list
                $table->json('menu_options')->nullable(); // [{"key": "1", "action": "route_queue", "target_queue_id": 1, "label": "Sales"}]
                $table->enum('unanswered_action', ['voicemail', 'callback', 'transfer_phone', 'hangup'])->default('voicemail');
                $table->string('unanswered_target', 128)->nullable();
                $table->string('voicemail_box', 64)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
            });
        }

        // 4. Call Queues & Agent Hunting Strategies
        if (!Schema::hasTable('call_queues')) {
            Schema::create('call_queues', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
                $table->string('name', 128);
                $table->string('department', 64)->nullable();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->enum('strategy', ['round_robin', 'simultaneous_ring', 'least_recent', 'linear'])->default('round_robin');
                $table->unsignedInteger('max_wait_time_seconds')->default(120);
                $table->string('music_on_hold', 255)->nullable();
                $table->json('assigned_agent_ids')->nullable(); // User IDs in queue
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'department']);
            });
        }

        // 5. Call Quality Reviews (Manager Feedback & Auditing)
        if (!Schema::hasTable('call_quality_reviews')) {
            Schema::create('call_quality_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
                $table->foreignId('voice_call_id')->constrained('voice_calls')->onDelete('cascade');
                $table->foreignId('reviewer_user_id')->constrained('users')->onDelete('cascade');
                $table->unsignedTinyInteger('score'); // 1-5 rating
                $table->json('criteria_scores')->nullable(); // {"greeting": 5, "clarity": 4, "compliance": 5}
                $table->text('feedback')->nullable();
                $table->json('tags')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'voice_call_id']);
            });
        }

        // 6. Call Recording Access & Download Audit Logs
        if (!Schema::hasTable('call_recording_access_logs')) {
            Schema::create('call_recording_access_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
                $table->foreignId('voice_call_id')->constrained('voice_calls')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->enum('action', ['listen', 'download', 'delete'])->default('listen');
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['tenant_id', 'voice_call_id', 'action']);
            });
        }

        // 7. Telephony Provider Diagnostic & Event Logs
        if (!Schema::hasTable('telephony_provider_logs')) {
            Schema::create('telephony_provider_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
                $table->string('provider', 32);
                $table->string('provider_call_id', 191)->nullable()->index();
                $table->string('event_type', 64);
                $table->enum('status', ['success', 'error', 'warning'])->default('success');
                $table->json('payload')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'provider', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telephony_provider_logs');
        Schema::dropIfExists('call_recording_access_logs');
        Schema::dropIfExists('call_quality_reviews');
        Schema::dropIfExists('call_queues');
        Schema::dropIfExists('call_ivr_flows');

        Schema::table('voice_ai_agents', function (Blueprint $table) {
            $table->dropColumn([
                'business_purpose',
                'escalation_rules',
                'business_hours',
                'knowledge_base',
                'confidence_threshold',
                'regional_dialects',
            ]);
        });

        Schema::table('voice_calls', function (Blueprint $table) {
            $table->dropColumn([
                'deal_id',
                'company_id',
                'branch_id',
                'call_type',
                'quality_score',
                'manager_feedback',
                'recording_notice_played',
                'idempotency_key',
                'follow_up_required',
                'follow_up_notes',
                'hangup_cause',
                'transfer_to_user_id',
            ]);
        });
    }
};
