<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Platform Super Admin Console
     */
    public function up(): void
    {
        // 1. Update tenants table status to include 'cancelled' if not already present
        try {
            DB::statement("ALTER TABLE `tenants` MODIFY COLUMN `status` ENUM('active', 'trialing', 'past_due', 'suspended', 'cancelled') NOT NULL DEFAULT 'active'");
        } catch (\Throwable $e) {
            // Ignore if already modified or SQLite
        }

        // 2. Add platform_role column to users table if missing
        if (!Schema::hasColumn('users', 'platform_role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('platform_role')->nullable()->after('is_platform_admin');
                $table->timestamp('platform_mfa_verified_at')->nullable()->after('platform_role');
            });
        }

        // 3. Platform Audit Logs
        Schema::create('platform_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('admin_name')->nullable();
            $table->string('admin_email')->nullable();
            $table->string('action', 100)->index();
            $table->string('target_type', 100)->nullable()->index();
            $table->string('target_id', 100)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // 4. Platform Support Access Logs (Breakglass Session)
        Schema::create('platform_support_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->text('justification_reason');
            $table->string('token_hash', 64)->unique();
            $table->enum('status', ['active', 'expired', 'revoked'])->default('active');
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->json('actions_performed')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // 5. Platform AI & Infrastructure Providers
        Schema::create('platform_ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('provider_key', 50)->unique();
            $table->string('name', 100);
            $table->enum('category', ['llm', 'voice', 'telephony', 'messaging']);
            $table->boolean('is_active')->default(true);
            $table->enum('health_status', ['healthy', 'degraded', 'unhealthy', 'unconfigured'])->default('unconfigured');
            $table->text('encrypted_credentials')->nullable();
            $table->json('available_models')->nullable();
            $table->string('default_model', 100)->nullable();
            $table->json('routing_policy')->nullable();
            $table->integer('rate_limit_rpm')->default(1200);
            $table->integer('rate_limit_tpm')->default(1000000);
            $table->timestamp('last_health_check_at')->nullable();
            $table->string('health_check_message')->nullable();
            $table->timestamps();
        });

        // 6. Platform Feature Flags
        Schema::create('platform_feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('flag_key', 100)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->json('rules')->nullable();
            $table->timestamps();
        });

        // 7. Platform Global System Settings (e.g. Maintenance Mode, IP Allowlist)
        Schema::create('platform_system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 100)->unique();
            $table->json('setting_value')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_system_settings');
        Schema::dropIfExists('platform_feature_flags');
        Schema::dropIfExists('platform_ai_providers');
        Schema::dropIfExists('platform_support_access_logs');
        Schema::dropIfExists('platform_audit_logs');

        if (Schema::hasColumn('users', 'platform_role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['platform_role', 'platform_mfa_verified_at']);
            });
        }
    }
};
