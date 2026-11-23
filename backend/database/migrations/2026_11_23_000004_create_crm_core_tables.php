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
        // 1. Enhance Leads Table
        Schema::table('leads', function (Blueprint $table) {
            $table->string('alternate_phone')->nullable()->after('phone');
            $table->string('designation')->nullable()->after('company_name');
            $table->string('address')->nullable()->after('designation');
            $table->string('city')->nullable()->after('address');
            $table->string('state')->nullable()->after('city');
            $table->string('country')->nullable()->default('US')->after('state');
            $table->string('postal_code')->nullable()->after('country');
            
            $table->text('requirement')->nullable()->after('postal_code');
            $table->string('product_interest')->nullable()->after('requirement');
            $table->date('expected_purchase_date')->nullable()->after('product_interest');
            
            $table->string('lifecycle_stage')->default('lead')->after('status');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->after('lifecycle_stage');
            $table->enum('qualification_status', ['unqualified', 'in_qualification', 'qualified', 'disqualified'])->default('unqualified')->after('priority');
            
            $table->integer('score')->default(0)->after('qualification_status');
            $table->json('score_breakdown')->nullable()->after('score');
            
            $table->unsignedBigInteger('team_id')->nullable()->after('assigned_user_id');
            $table->string('source_account')->nullable()->after('source');
            $table->string('campaign')->nullable()->after('source_account');
            $table->string('ad_details')->nullable()->after('campaign');
            
            $table->string('utm_source')->nullable()->after('ad_details');
            $table->string('utm_medium')->nullable()->after('utm_source');
            $table->string('utm_campaign')->nullable()->after('utm_medium');
            $table->string('utm_term')->nullable()->after('utm_campaign');
            $table->string('utm_content')->nullable()->after('utm_term');
            
            $table->json('first_touch_attribution')->nullable()->after('utm_content');
            $table->json('last_touch_attribution')->nullable()->after('first_touch_attribution');
            $table->string('landing_page_url')->nullable()->after('last_touch_attribution');
            
            $table->json('tags')->nullable()->after('landing_page_url');
            
            $table->timestamp('next_followup_at')->nullable()->after('tags');
            $table->timestamp('reminder_at')->nullable()->after('next_followup_at');
            
            $table->text('lost_reason')->nullable()->after('reminder_at');
            $table->text('disqualification_reason')->nullable()->after('lost_reason');
            
            $table->boolean('is_archived')->default(false)->after('disqualification_reason');
            $table->timestamp('converted_at')->nullable()->after('is_archived');
            $table->unsignedBigInteger('converted_contact_id')->nullable()->after('converted_at');
            $table->unsignedBigInteger('converted_company_id')->nullable()->after('converted_contact_id');
            $table->unsignedBigInteger('converted_deal_id')->nullable()->after('converted_company_id');

            $table->index(['tenant_id', 'priority'], 'leads_tenant_priority_idx');
            $table->index(['tenant_id', 'score'], 'leads_tenant_score_idx');
            $table->index(['tenant_id', 'assigned_user_id'], 'leads_tenant_assigned_idx');
            $table->index(['tenant_id', 'is_archived'], 'leads_tenant_archived_idx');
        });

        // 2. Enhance Contacts Table
        Schema::table('contacts', function (Blueprint $table) {
            $table->json('phone_numbers')->nullable()->after('phone');
            $table->json('email_addresses')->nullable()->after('email');
            $table->string('department')->nullable()->after('job_title');
            $table->string('address')->nullable()->after('department');
            $table->string('city')->nullable()->after('address');
            $table->string('state')->nullable()->after('city');
            $table->string('country')->nullable()->default('US')->after('state');
            $table->string('postal_code')->nullable()->after('country');
            
            $table->json('communication_preferences')->nullable()->after('postal_code');
            $table->enum('consent_status', ['opted_in', 'opted_out', 'pending'])->default('opted_in')->after('communication_preferences');
            $table->timestamp('consent_recorded_at')->nullable()->after('consent_status');
            
            $table->json('tags')->nullable()->after('consent_recorded_at');
            $table->unsignedBigInteger('lead_id')->nullable()->after('tags');
            
            $table->index(['tenant_id', 'lead_id'], 'contacts_tenant_lead_idx');
        });

        // 3. Enhance Companies Table
        Schema::table('companies', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('gstin_tax_id')->nullable()->after('legal_name');
            $table->string('registration_number')->nullable()->after('gstin_tax_id');
            
            $table->string('billing_address')->nullable()->after('address');
            $table->string('billing_city')->nullable()->after('billing_address');
            $table->string('billing_state')->nullable()->after('billing_city');
            $table->string('billing_country')->nullable()->default('US')->after('billing_state');
            $table->string('billing_postal_code')->nullable()->after('billing_country');
            
            $table->string('delivery_address')->nullable()->after('billing_postal_code');
            $table->string('delivery_city')->nullable()->after('delivery_address');
            $table->string('delivery_state')->nullable()->after('delivery_city');
            $table->string('delivery_country')->nullable()->default('US')->after('delivery_state');
            $table->string('delivery_postal_code')->nullable()->after('delivery_country');
            
            $table->unsignedBigInteger('account_owner_id')->nullable()->after('delivery_postal_code');
            $table->enum('account_status', ['prospect', 'active', 'churned', 'hold'])->default('prospect')->after('account_owner_id');
            $table->json('tags')->nullable()->after('account_status');
            $table->unsignedBigInteger('lead_id')->nullable()->after('tags');
            
            $table->index(['tenant_id', 'account_status'], 'companies_tenant_status_idx');
        });

        // 4. Enhance Deals Table
        Schema::table('deals', function (Blueprint $table) {
            $table->unsignedBigInteger('lead_id')->nullable()->after('company_id');
            $table->unsignedBigInteger('contact_id')->nullable()->after('lead_id');
            $table->unsignedInteger('pipeline_id')->default(1)->after('contact_id');
            $table->text('lost_reason')->nullable()->after('probability');
            $table->json('tags')->nullable()->after('lost_reason');
            
            $table->index(['tenant_id', 'lead_id'], 'deals_tenant_lead_idx');
            $table->index(['tenant_id', 'contact_id'], 'deals_tenant_contact_idx');
        });

        // 5. Enhance Customers Table
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('contact_id')->nullable()->after('company_id');
            $table->unsignedBigInteger('lead_id')->nullable()->after('contact_id');
            $table->string('tier')->default('standard')->after('status');
            
            $table->index(['tenant_id', 'contact_id'], 'cust_tenant_contact_idx');
            $table->index(['tenant_id', 'lead_id'], 'cust_tenant_lead_idx');
        });

        // 6. CRM Chronological Activities Table (Customer 360 Spine)
        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->unsignedBigInteger('lead_id')->nullable()->index();
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('deal_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            
            $table->string('activity_type', 50); // note, call, email, sms, whatsapp, meeting, task, status_change, assignment, conversion, audit
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // duration, recording_url, utm, diff, etc.
            
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'activity_type'], 'crm_act_tenant_type_idx');
            $table->index(['tenant_id', 'created_at'], 'crm_act_tenant_created_idx');
            $table->index(['tenant_id', 'lead_id'], 'crm_act_tenant_lead_idx');
            $table->index(['tenant_id', 'contact_id'], 'crm_act_tenant_contact_idx');
            $table->index(['tenant_id', 'customer_id'], 'crm_act_tenant_cust_idx');
        });

        // 6. Lead Assignment Records (History & Audit)
        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->enum('assignment_type', ['manual', 'round_robin', 'rule_based'])->default('manual');
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['tenant_id', 'lead_id'], 'lead_asgn_tenant_lead_idx');
            $table->index(['tenant_id', 'assigned_to_user_id'], 'lead_asgn_tenant_user_idx');
        });

        // 7. Lead Status Configurations per Tenant
        Schema::create('lead_status_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('status_key', 50);
            $table->string('label', 100);
            $table->string('color', 30)->default('#10b981');
            $table->unsignedInteger('display_order')->default(1);
            $table->boolean('is_converted')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->json('allowed_transitions')->nullable();
            $table->timestamps();
            
            $table->unique(['tenant_id', 'status_key'], 'lead_status_tenant_key_uniq');
        });

        // 8. Duplicate Merge Audit Logs
        Schema::create('duplicate_merge_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entity_type', 50); // lead, contact, company
            $table->unsignedBigInteger('master_record_id');
            $table->unsignedBigInteger('merged_record_id');
            $table->json('preserved_fields')->nullable();
            $table->json('merged_data')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['tenant_id', 'entity_type'], 'dup_merge_tenant_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duplicate_merge_logs');
        Schema::dropIfExists('lead_status_configs');
        Schema::dropIfExists('lead_assignments');
        Schema::dropIfExists('crm_activities');

        Schema::table('deals', function (Blueprint $table) {
            $table->dropIndex('deals_tenant_lead_idx');
            $table->dropIndex('deals_tenant_contact_idx');
            $table->dropColumn(['lead_id', 'contact_id', 'pipeline_id', 'lost_reason', 'tags']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('cust_tenant_contact_idx');
            $table->dropIndex('cust_tenant_lead_idx');
            $table->dropColumn(['contact_id', 'lead_id', 'tier']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('companies_tenant_status_idx');
            $table->dropColumn([
                'legal_name', 'gstin_tax_id', 'registration_number',
                'billing_address', 'billing_city', 'billing_state', 'billing_country', 'billing_postal_code',
                'delivery_address', 'delivery_city', 'delivery_state', 'delivery_country', 'delivery_postal_code',
                'account_owner_id', 'account_status', 'tags', 'lead_id'
            ]);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('contacts_tenant_lead_idx');
            $table->dropColumn([
                'phone_numbers', 'email_addresses', 'department',
                'address', 'city', 'state', 'country', 'postal_code',
                'communication_preferences', 'consent_status', 'consent_recorded_at', 'tags', 'lead_id'
            ]);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_tenant_priority_idx');
            $table->dropIndex('leads_tenant_score_idx');
            $table->dropIndex('leads_tenant_assigned_idx');
            $table->dropIndex('leads_tenant_archived_idx');
            $table->dropColumn([
                'alternate_phone', 'designation', 'address', 'city', 'state', 'country', 'postal_code',
                'requirement', 'product_interest', 'expected_purchase_date',
                'lifecycle_stage', 'priority', 'qualification_status', 'score', 'score_breakdown',
                'team_id', 'source_account', 'campaign', 'ad_details',
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
                'first_touch_attribution', 'last_touch_attribution', 'landing_page_url', 'tags',
                'next_followup_at', 'reminder_at', 'lost_reason', 'disqualification_reason',
                'is_archived', 'converted_at', 'converted_contact_id', 'converted_company_id', 'converted_deal_id'
            ]);
        });
    }
};
