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
        // 1. AI Providers (Gemini, OpenAI, Custom endpoints)
        Schema::create('whatsapp_ai_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->onDelete('cascade');
            $table->string('provider', 64); // 'gemini', 'openai', 'anthropic', 'custom'
            $table->string('name', 128);
            $table->text('api_key'); // Encrypted
            $table->string('api_endpoint', 255)->nullable();
            $table->string('default_model', 128);
            $table->json('available_models')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(1);
            $table->decimal('monthly_budget_usd', 8, 2)->nullable();
            $table->decimal('spent_usd_this_month', 8, 2)->default(0.00);
            $table->unsignedInteger('timeout_seconds')->default(30);
            $table->unsignedInteger('max_retries')->default(2);
            $table->timestamps();

            $table->index(['tenant_id', 'provider']);
        });

        // 2. WhatsApp AI Agents
        Schema::create('whatsapp_ai_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('whatsapp_phone_number_id')->nullable()->constrained('whatsapp_phone_numbers')->onDelete('set null');
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'paused', 'draft'])->default('draft');
            $table->string('primary_language', 32)->default('multi'); // 'en', 'hi', 'hinglish', 'multi'
            $table->json('supported_languages')->nullable();
            $table->enum('tone', ['professional', 'friendly', 'consultative', 'assertive'])->default('consultative');
            $table->longText('system_prompt');
            $table->json('business_hours')->nullable();
            $table->text('welcome_message')->nullable();
            $table->text('fallback_message')->nullable();
            $table->json('human_handover_conditions')->nullable();
            $table->json('conversation_rules')->nullable();
            $table->json('allowed_tools')->nullable(); // tool names permitted for execution
            $table->string('model_provider', 64)->default('gemini');
            $table->string('model_name', 128)->default('gemini-1.5-flash');
            $table->decimal('temperature', 3, 2)->default(0.30);
            $table->unsignedInteger('max_tokens')->default(1024);
            $table->unsignedBigInteger('monthly_token_budget')->default(500000);
            $table->unsignedBigInteger('used_tokens_this_month')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });

        // 3. WhatsApp AI Agent Versions (Audit & Rollback)
        Schema::create('whatsapp_ai_agent_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('agent_id')->constrained('whatsapp_ai_agents')->onDelete('cascade');
            $table->unsignedInteger('version');
            $table->longText('system_prompt');
            $table->text('welcome_message')->nullable();
            $table->text('fallback_message')->nullable();
            $table->json('allowed_tools')->nullable();
            $table->json('settings_snapshot')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('change_summary', 255)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'agent_id', 'version']);
        });

        // 4. WhatsApp Knowledge Bases (Document store)
        Schema::create('whatsapp_knowledge_bases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->string('category', 64)->default('faqs'); // 'faqs', 'catalog', 'policies', 'manuals', 'web_crawl'
            $table->enum('source_type', ['file', 'url', 'manual_text'])->default('manual_text');
            $table->string('file_path', 255)->nullable();
            $table->string('file_type', 32)->nullable(); // 'pdf', 'docx', 'txt', 'csv', 'url', 'text'
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->enum('status', ['pending', 'indexing', 'ready', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->unsignedInteger('total_chunks')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // 5. WhatsApp Knowledge Chunks (Vector & Text Retrieval)
        Schema::create('whatsapp_knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('knowledge_base_id')->constrained('whatsapp_knowledge_bases')->onDelete('cascade');
            $table->unsignedInteger('chunk_index')->default(0);
            $table->longText('content');
            $table->json('embedding')->nullable(); // Vector coordinates array
            $table->string('source_citation', 255)->nullable();
            $table->unsignedInteger('token_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'knowledge_base_id']);
            if (\Illuminate\Support\Facades\DB::getDriverName() === 'mysql') {
                $table->fullText('content');
            }
        });

        // 6. WhatsApp AI Execution Logs & Traces
        Schema::create('whatsapp_ai_execution_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('agent_id')->nullable()->constrained('whatsapp_ai_agents')->onDelete('set null');
            $table->foreignId('conversation_id')->nullable()->constrained('whatsapp_conversations')->onDelete('set null');
            $table->string('provider', 64);
            $table->string('model', 128);
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 8, 6)->default(0.000000);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->json('tool_calls')->nullable();
            $table->json('knowledge_citations')->nullable();
            $table->json('decision_trace')->nullable();
            $table->text('user_query');
            $table->longText('ai_response')->nullable();
            $table->enum('status', ['success', 'failed', 'fallback_used', 'handover_triggered'])->default('success');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'agent_id']);
            $table->index(['tenant_id', 'conversation_id']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_ai_execution_logs');
        Schema::dropIfExists('whatsapp_knowledge_chunks');
        Schema::dropIfExists('whatsapp_knowledge_bases');
        Schema::dropIfExists('whatsapp_ai_agent_versions');
        Schema::dropIfExists('whatsapp_ai_agents');
        Schema::dropIfExists('whatsapp_ai_providers');
    }
};
