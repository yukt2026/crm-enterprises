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
        // 1. Attribution Touchpoints Table
        if (!Schema::hasTable('attribution_touchpoints')) {
            Schema::create('attribution_touchpoints', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
                $table->string('touchpoint_type', 60)->default('web_visit'); // ad_click, form_submit, landing_page_visit, email_open, whatsapp_inbound, call_completed, deal_created
                $table->string('channel', 60)->default('direct'); // google_ads, meta_ads, organic_search, whatsapp, email, direct, referral
                $table->foreignId('campaign_id')->nullable()->constrained('marketing_campaigns')->nullOnDelete();
                $table->string('utm_source', 100)->nullable();
                $table->string('utm_medium', 100)->nullable();
                $table->string('utm_campaign', 150)->nullable();
                $table->string('utm_term', 150)->nullable();
                $table->string('utm_content', 150)->nullable();
                $table->foreignId('landing_page_id')->nullable()->constrained('landing_pages')->nullOnDelete();
                $table->foreignId('form_id')->nullable()->constrained('forms')->nullOnDelete();
                $table->timestamp('touchpoint_timestamp');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'lead_id']);
                $table->index(['tenant_id', 'deal_id']);
                $table->index(['tenant_id', 'channel']);
                $table->index(['tenant_id', 'touchpoint_timestamp']);
            });
        }

        // 2. Saved & Scheduled Reports Table
        if (!Schema::hasTable('saved_reports')) {
            Schema::create('saved_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->string('report_type', 50)->default('executive'); // executive, leads, sales, marketing, attribution, operations, communications
                $table->json('filters')->nullable(); // date_range, comparison, employee_id, team_id, channels, etc.
                $table->json('selected_columns')->nullable();
                $table->string('chart_type', 30)->default('bar'); // bar, line, pie, area, table
                $table->boolean('is_scheduled')->default(false);
                $table->string('schedule_frequency', 30)->nullable(); // daily, weekly, monthly
                $table->json('schedule_recipients')->nullable(); // array of email addresses
                $table->timestamp('last_run_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'report_type']);
            });
        }

        // 3. Enhance Marketing Campaigns with spend type & calculated ratios
        Schema::table('marketing_campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('marketing_campaigns', 'spend_type')) {
                $table->string('spend_type', 30)->default('manual_estimate')->after('spend_amount'); // provider_reported, manual_estimate
            }
            if (!Schema::hasColumn('marketing_campaigns', 'cpl')) {
                $table->decimal('cpl', 10, 2)->nullable()->after('spend_type');
            }
            if (!Schema::hasColumn('marketing_campaigns', 'roas')) {
                $table->decimal('roas', 8, 2)->nullable()->after('cpl');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_campaigns', function (Blueprint $table) {
            $table->dropColumn(['spend_type', 'cpl', 'roas']);
        });

        Schema::dropIfExists('saved_reports');
        Schema::dropIfExists('attribution_touchpoints');
    }
};
