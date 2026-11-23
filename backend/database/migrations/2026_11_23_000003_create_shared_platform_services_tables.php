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
        // 1. Expand Notifications Table with rich event metadata
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (!Schema::hasColumn('notifications', 'type')) {
                    $table->string('type')->default('general')->after('user_id');
                }
                if (!Schema::hasColumn('notifications', 'action_url')) {
                    $table->string('action_url')->nullable()->after('message');
                }
                if (!Schema::hasColumn('notifications', 'data')) {
                    $table->json('data')->nullable()->after('action_url');
                }
                if (!Schema::hasColumn('notifications', 'channel')) {
                    $table->string('channel')->default('in_app')->after('data');
                }
            });
        }

        // 2. Notification Preferences Table
        if (!Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('notification_type'); // e.g. lead.assigned, ticket.new, invoice.event
                $table->boolean('in_app_enabled')->default(true);
                $table->boolean('email_enabled')->default(true);
                $table->boolean('push_enabled')->default(false);
                $table->timestamps();

                $table->unique(['user_id', 'notification_type'], 'user_notif_pref_unique');
                $table->index(['tenant_id', 'notification_type']);
            });
        }

        // 3. Media & File Attachments Table (Tenant-isolated & Polymorphic)
        if (!Schema::hasTable('media_files')) {
            Schema::create('media_files', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->nullableMorphs('fileable'); // fileable_type, fileable_id
                $table->string('file_name');
                $table->string('original_name');
                $table->string('mime_type');
                $table->unsignedBigInteger('file_size'); // bytes
                $table->string('disk')->default('local');
                $table->string('storage_path');
                $table->string('hash', 64)->nullable(); // SHA256 checksum
                $table->boolean('is_private')->default(true);
                $table->unsignedInteger('retention_days')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'created_at']);
            });
        }

        // 4. Custom Field Definitions (EAV Scalable Metadata)
        if (!Schema::hasTable('custom_field_definitions')) {
            Schema::create('custom_field_definitions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('entity_type'); // lead, deal, contact, company, customer, project, task, ticket, employee, invoice
                $table->string('name');
                $table->string('field_key');
                $table->enum('type', [
                    'text',
                    'long_text',
                    'number',
                    'date',
                    'select',
                    'multi_select',
                    'checkbox',
                    'email',
                    'phone',
                    'url',
                    'currency',
                    'file'
                ])->default('text');
                $table->json('options')->nullable(); // Select options
                $table->boolean('is_required')->default(false);
                $table->string('default_value')->nullable();
                $table->integer('display_order')->default(0);
                $table->json('validation_rules')->nullable();
                $table->json('visibility_roles')->nullable(); // Array of permitted roles, or null for all
                $table->timestamps();

                $table->unique(['tenant_id', 'entity_type', 'field_key'], 'tenant_entity_field_unique');
                $table->index(['tenant_id', 'entity_type', 'display_order'], 'cf_def_tenant_order_idx');
            });
        }

        // 5. Custom Field Values
        if (!Schema::hasTable('custom_field_values')) {
            Schema::create('custom_field_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('custom_field_definition_id')->constrained('custom_field_definitions')->cascadeOnDelete();
                $table->string('entity_type');
                $table->unsignedBigInteger('entity_id');
                $table->text('value')->nullable(); // Value stored as string or JSON
                $table->timestamps();

                $table->unique(['custom_field_definition_id', 'entity_id'], 'field_entity_unique');
                $table->index(['tenant_id', 'entity_type', 'entity_id']);
            });
        }

        // 6. Immutable Audit Logs Table
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid');
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('user_name')->default('System');
                $table->string('role')->nullable();
                $table->string('action'); // created, updated, deleted, viewed, exported, login
                $table->nullableMorphs('auditable'); // auditable_type, auditable_id
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('url')->nullable();
                $table->string('method', 10)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['tenant_id', 'created_at']);
                $table->index(['tenant_id', 'action']);
                $table->index('uuid');
            });
        }

        // 7. Expand System Settings for Security, Retention & User Preferences
        if (Schema::hasTable('system_settings')) {
            Schema::table('system_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('system_settings', 'two_factor_required')) {
                    $table->boolean('two_factor_required')->default(false)->after('notification_preferences');
                }
                if (!Schema::hasColumn('system_settings', 'session_timeout_minutes')) {
                    $table->unsignedInteger('session_timeout_minutes')->default(120)->after('two_factor_required');
                }
                if (!Schema::hasColumn('system_settings', 'data_retention_days')) {
                    $table->unsignedInteger('data_retention_days')->default(0)->after('session_timeout_minutes'); // 0 = unlimited
                }
                if (!Schema::hasColumn('system_settings', 'favicon_url')) {
                    $table->string('favicon_url')->nullable()->after('logo_url');
                }
                if (!Schema::hasColumn('system_settings', 'email_notifications_enabled')) {
                    $table->boolean('email_notifications_enabled')->default(true)->after('data_retention_days');
                }
                if (!Schema::hasColumn('system_settings', 'browser_push_enabled')) {
                    $table->boolean('browser_push_enabled')->default(false)->after('email_notifications_enabled');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_field_definitions');
        Schema::dropIfExists('media_files');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('audit_logs');
    }
};
