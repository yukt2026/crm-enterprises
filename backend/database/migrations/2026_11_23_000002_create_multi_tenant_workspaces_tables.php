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
        // 1. Add current_tenant_id and is_platform_admin to users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_tenant_id')->nullable()->after('tenant_id')->constrained('tenants')->nullOnDelete();
            $table->boolean('is_platform_admin')->default(false)->after('role');
        });

        // 2. Tenant Users (Workspace Memberships) - Many-to-Many between Users and Tenants
        Schema::create('tenant_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('Employee'); // Business Owner, Business Admin, Manager, Sales Executive, etc.
            $table->enum('status', ['active', 'invited', 'suspended'])->default('active');
            $table->boolean('is_owner')->default(false);
            $table->timestamp('joined_at')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'status']);
        });

        // 3. Workspace Invitations
        Schema::create('workspace_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('email');
            $table->string('role')->default('Employee');
            $table->string('token', 64)->unique();
            $table->enum('status', ['pending', 'accepted', 'expired', 'revoked'])->default('pending');
            $table->timestamp('expires_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'email']);
            $table->index(['token', 'status']);
        });

        // 4. Login History & Auditing
        Schema::create('login_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type')->nullable();
            $table->enum('status', ['success', 'failed', 'blocked_mfa'])->default('success');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });

        // 5. Expand System Settings for Comprehensive Business Configuration
        Schema::table('system_settings', function (Blueprint $table) {
            $table->string('logo_url')->nullable()->after('company_phone');
            $table->text('address')->nullable()->after('logo_url');
            $table->string('gstin_tax_id')->nullable()->after('address');
            $table->string('timezone')->default('UTC')->after('gstin_tax_id');
            $table->string('locale')->default('en')->after('timezone');
            $table->string('date_format')->default('YYYY-MM-DD')->after('locale');
            $table->string('time_format')->default('24h')->after('date_format');
            $table->json('working_days')->nullable()->after('time_format');
            $table->string('business_hours_start')->default('09:00')->after('working_days');
            $table->string('business_hours_end')->default('18:00')->after('business_hours_start');
            $table->string('default_country')->default('US')->after('business_hours_end');
            $table->string('phone_format')->default('E164')->after('default_country');
            $table->string('brand_primary_color')->default('#10b981')->after('phone_format');
            $table->string('brand_secondary_color')->default('#0f172a')->after('brand_primary_color');
            $table->json('notification_preferences')->nullable()->after('brand_secondary_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'logo_url',
                'address',
                'gstin_tax_id',
                'timezone',
                'locale',
                'date_format',
                'time_format',
                'working_days',
                'business_hours_start',
                'business_hours_end',
                'default_country',
                'phone_format',
                'brand_primary_color',
                'brand_secondary_color',
                'notification_preferences',
            ]);
        });

        Schema::dropIfExists('login_histories');
        Schema::dropIfExists('workspace_invitations');
        Schema::dropIfExists('tenant_users');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_tenant_id']);
            $table->dropColumn(['current_tenant_id', 'is_platform_admin']);
        });
    }
};
