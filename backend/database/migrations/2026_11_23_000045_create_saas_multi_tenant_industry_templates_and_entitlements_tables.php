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
        // 1. Enhance tenants table with branding, onboarding, and suspension metadata
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'timezone')) {
                $table->string('timezone', 50)->default('Asia/Kolkata')->after('domain');
            }
            if (!Schema::hasColumn('tenants', 'branding')) {
                $table->json('branding')->nullable()->after('timezone');
            }
            if (!Schema::hasColumn('tenants', 'onboarding_status')) {
                $table->string('onboarding_status', 30)->default('completed')->after('status');
            }
            if (!Schema::hasColumn('tenants', 'onboarding_step')) {
                $table->unsignedTinyInteger('onboarding_step')->default(11)->after('onboarding_status');
            }
            if (!Schema::hasColumn('tenants', 'industry_template_code')) {
                $table->string('industry_template_code', 50)->nullable()->after('onboarding_step');
            }
            if (!Schema::hasColumn('tenants', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('industry_template_code');
            }
            if (!Schema::hasColumn('tenants', 'suspension_reason')) {
                $table->text('suspension_reason')->nullable()->after('suspended_at');
            }
            if (!Schema::hasColumn('tenants', 'gstin')) {
                $table->string('gstin', 20)->nullable()->after('suspension_reason');
            }
            if (!Schema::hasColumn('tenants', 'pan')) {
                $table->string('pan', 20)->nullable()->after('gstin');
            }
            if (!Schema::hasColumn('tenants', 'billing_address')) {
                $table->text('billing_address')->nullable()->after('pan');
            }
            if (!Schema::hasColumn('tenants', 'billing_state_code')) {
                $table->string('billing_state_code', 10)->nullable()->after('billing_address');
            }
        });

        // 2. Industry Specific Starter Templates
        if (!Schema::hasTable('industry_templates')) {
            Schema::create('industry_templates', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique(); // real_estate, education, healthcare, it_agencies, etc.
                $table->string('name', 120);
                $table->string('category', 60)->default('Services');
                $table->text('description')->nullable();
                $table->string('icon', 50)->default('Building2');
                $table->json('module_presets')->nullable();
                $table->json('pipeline_stages')->nullable();
                $table->json('custom_fields')->nullable();
                $table->json('dashboard_widgets')->nullable();
                $table->json('automation_recipes')->nullable();
                $table->json('report_definitions')->nullable();
                $table->json('notification_templates')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 3. Tenant Applied Industry Templates
        if (!Schema::hasTable('tenant_applied_templates')) {
            Schema::create('tenant_applied_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('template_id')->constrained('industry_templates')->cascadeOnDelete();
                $table->string('template_code', 50);
                $table->json('customizations')->nullable();
                $table->timestamp('applied_at')->useCurrent();
                $table->string('status', 30)->default('active'); // active, customized, disabled
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
            });
        }

        // 4. Platform Announcements
        if (!Schema::hasTable('platform_announcements')) {
            Schema::create('platform_announcements', function (Blueprint $table) {
                $table->id();
                $table->string('title', 180);
                $table->text('content');
                $table->string('type', 30)->default('info'); // info, warning, maintenance, critical
                $table->string('target_audience', 40)->default('all'); // all, paid_only, specific_tenants
                $table->json('target_tenant_ids')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['is_active', 'starts_at', 'expires_at']);
            });
        }

        // 5. Platform Backup & Restore Records
        if (!Schema::hasTable('platform_backup_records')) {
            Schema::create('platform_backup_records', function (Blueprint $table) {
                $table->id();
                $table->string('backup_type', 40)->default('database'); // database, storage, full
                $table->string('file_path')->nullable();
                $table->unsignedBigInteger('file_size_bytes')->nullable();
                $table->string('status', 30)->default('completed'); // pending, in_progress, completed, failed
                $table->timestamp('verified_at')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_backup_records');
        Schema::dropIfExists('platform_announcements');
        Schema::dropIfExists('tenant_applied_templates');
        Schema::dropIfExists('industry_templates');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'timezone',
                'branding',
                'onboarding_status',
                'onboarding_step',
                'industry_template_code',
                'suspended_at',
                'suspension_reason',
                'gstin',
                'pan',
                'billing_address',
                'billing_state_code',
            ]);
        });
    }
};
