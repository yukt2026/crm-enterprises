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
        // 1. Tenant AI Provider Configurations & Settings
        Schema::create('tenant_ai_configurations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('provider_name')->default('openai'); // openai, anthropic, gemini, ollama, custom
            $table->string('display_name')->nullable();
            $table->text('api_key_encrypted')->nullable();
            $table->string('base_url')->nullable();
            $table->string('default_model')->default('gpt-4o-mini');
            $table->string('fallback_model')->nullable();
            $table->json('available_models')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->decimal('temperature', 3, 2)->default(0.7);
            $table->integer('max_tokens')->default(2048);
            $table->integer('rate_limit_rpm')->default(60);
            $table->integer('rate_limit_tpm')->default(60000);
            $table->decimal('cost_per_1k_input_tokens', 8, 5)->default(0.00150);
            $table->decimal('cost_per_1k_output_tokens', 8, 5)->default(0.00200);
            $table->string('health_status')->default('healthy'); // healthy, degraded, unavailable
            $table->timestamp('last_health_checked_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // 2. AI Conversations & Sessions (for Business Assistant & User Co-pilot)
        Schema::create('ai_assistant_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('title')->default('New AI Conversation');
            $table->string('category')->default('general'); // sales, business_analytics, operations, document, hr
            $table->json('context_parameters')->nullable(); // e.g. branch_id, department_id, active_lead_id
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 3. AI Assistant Messages
        Schema::create('ai_assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('session_id');
            $table->string('role'); // user, assistant, system, tool
            $table->text('content');
            $table->json('suggested_actions')->nullable(); // action proposals
            $table->json('executed_actions')->nullable();
            $table->json('citations_or_records')->nullable(); // real CRM records referenced
            $table->integer('prompt_tokens')->default(0);
            $table->integer('completion_tokens')->default(0);
            $table->decimal('estimated_cost', 8, 5)->default(0.00000);
            $table->timestamps();

            $table->index(['tenant_id', 'session_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('session_id')->references('id')->on('ai_assistant_sessions')->onDelete('cascade');
        });

        // 4. Proposed AI Action Execution & Human Confirmation Gate
        Schema::create('ai_proposed_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('session_id')->nullable();
            $table->string('action_type'); // create_task, assign_lead, draft_message, update_lead, create_followup, prepare_quote, create_appointment
            $table->string('target_entity_type')->nullable(); // lead, deal, contact, task, quote
            $table->unsignedBigInteger('target_entity_id')->nullable();
            $table->string('summary');
            $table->json('payload'); // action parameters
            $table->boolean('requires_confirmation')->default(true);
            $table->string('status')->default('pending_confirmation'); // pending_confirmation, confirmed, executed, rejected, expired
            $table->unsignedBigInteger('confirmed_by_user_id')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->json('execution_result')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 5. AI Sales Insights & Lead Intent Scoring
        Schema::create('ai_sales_insights', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('entity_type'); // lead, deal, contact, company
            $table->unsignedBigInteger('entity_id');
            $table->text('summary')->nullable();
            $table->json('extracted_requirements')->nullable();
            $table->string('qualification_status')->default('unqualified'); // qualified, unqualified, under_review
            $table->string('temperature')->default('warm'); // hot, warm, cold
            $table->integer('intent_score')->default(50); // 0-100
            $table->decimal('confidence_score', 4, 3)->default(0.750); // 0.000 to 1.000
            $table->text('confidence_rationale')->nullable();
            $table->string('suggested_next_action')->nullable();
            $table->json('objections_detected')->nullable();
            $table->json('deal_risk_factors')->nullable();
            $table->text('sales_pitch')->nullable();
            $table->text('suggested_follow_up_message')->nullable();
            $table->unsignedBigInteger('suggested_duplicate_lead_id')->nullable();
            $table->timestamp('analyzed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['tenant_id', 'entity_type', 'entity_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // 6. AI Meeting Intelligence
        Schema::create('ai_meeting_recordings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('meeting_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id');
            $table->string('title');
            $table->string('audio_file_path')->nullable();
            $table->integer('duration_seconds')->default(0);
            $table->string('language')->default('en'); // en, hi, Hinglish
            $table->string('processing_status')->default('uploaded'); // uploaded, transcribing, analyzed, completed, failed
            $table->longText('raw_transcript')->nullable();
            $table->text('meeting_summary')->nullable();
            $table->json('key_discussion_points')->nullable();
            $table->json('customer_requirements')->nullable();
            $table->json('objections_raised')->nullable();
            $table->json('action_items')->nullable(); // [{title, assignee, due_date}]
            $table->unsignedBigInteger('linked_contact_id')->nullable();
            $table->unsignedBigInteger('linked_deal_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'processing_status']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 7. AI Document Intelligence
        Schema::create('ai_document_extractions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('uploaded_by_user_id');
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('file_type'); // pdf, docx, png, jpeg
            $table->string('document_classification')->default('invoice'); // invoice, purchase_order, contract_agreement, quotation, identity, general
            $table->string('processing_status')->default('uploaded'); // uploaded, processing, extracted, verified, committed, failed
            $table->decimal('overall_confidence', 4, 3)->default(0.850);
            $table->json('extracted_fields')->nullable(); // {customer_name, invoice_no, gstin, amount, dates, due_dates, terms, po_number}
            $table->json('field_confidences')->nullable(); // confidence per field
            $table->json('user_corrections')->nullable(); // edited values before committing
            $table->unsignedBigInteger('committed_to_entity_id')->nullable(); // linked invoice_id or deal_id
            $table->string('committed_to_entity_type')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'processing_status']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('uploaded_by_user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 8. AI Content Generation History & Drafts
        Schema::create('ai_generated_contents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('content_type'); // whatsapp_reply, email_reply, sales_followup, quotation_intro, proposal, support_response, marketing_copy, social_post, internal_report
            $table->string('tone')->default('professional'); // professional, persuasive, friendly, urgent, empathetic
            $table->string('language')->default('en');
            $table->text('prompt_or_context');
            $table->longText('generated_text');
            $table->boolean('is_used')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'content_type']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 9. AI Usage Logs, Token Ledger & Metering
        Schema::create('ai_request_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('provider');
            $table->string('model');
            $table->string('feature_code'); // sales_assistant, business_bi, action_executor, meeting_intelligence, doc_intelligence, content_generator
            $table->integer('prompt_tokens')->default(0);
            $table->integer('completion_tokens')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->decimal('cost_incurred', 8, 5)->default(0.00000);
            $table->integer('latency_ms')->default(0);
            $table->string('status')->default('success'); // success, error, rate_limited, context_exceeded
            $table->text('error_message')->nullable();
            $table->date('recorded_date');
            $table->timestamps();

            $table->index(['tenant_id', 'recorded_date']);
            $table->index(['tenant_id', 'feature_code']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_request_logs');
        Schema::dropIfExists('ai_generated_contents');
        Schema::dropIfExists('ai_document_extractions');
        Schema::dropIfExists('ai_meeting_recordings');
        Schema::dropIfExists('ai_sales_insights');
        Schema::dropIfExists('ai_proposed_actions');
        Schema::dropIfExists('ai_assistant_messages');
        Schema::dropIfExists('ai_assistant_sessions');
        Schema::dropIfExists('tenant_ai_configurations');
    }
};
