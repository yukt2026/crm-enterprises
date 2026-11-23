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
        // 1. CRM Roles Table (Supports both System Presets and Custom Tenant Roles)
        Schema::create('crm_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false); // System presets cannot be deleted
            $table->boolean('is_active')->default(true);
            $table->string('color', 20)->default('#4F46E5');
            $table->foreignId('parent_role_id')->nullable()->constrained('crm_roles')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['is_system']);
        });

        // 2. CRM Role Permissions with Granular Action and Row-Level Data Scopes
        Schema::create('crm_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('crm_roles')->cascadeOnDelete();
            $table->string('module', 60); // leads, deals, contacts, invoices, hrm, support, etc.
            $table->string('action', 60); // view, create, edit, delete, export, approve, etc.
            $table->string('data_scope', 40)->default('all'); // all, own, assigned, team, department, branch, selected_employees, selected_resources
            $table->json('scope_constraints')->nullable(); // Optional constraint IDs e.g. branch_ids, department_ids
            $table->timestamps();

            $table->unique(['role_id', 'module', 'action'], 'crm_role_perm_role_mod_act_uniq');
            $table->index(['role_id', 'module'], 'crm_role_perm_role_mod_idx');
        });

        // 3. User-Role Assignment with Multi-Role and Primary Role Support
        Schema::create('crm_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('crm_roles')->cascadeOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'role_id'], 'crm_user_role_tenant_user_role_uniq');
            $table->index(['tenant_id', 'user_id'], 'crm_user_role_tenant_user_idx');
        });

        // 4. Add branch_id and department_id to users if not present
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('tenant_id');
            }
            if (!Schema::hasColumn('users', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('department');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'branch_id')) {
                $table->dropColumn('branch_id');
            }
            if (Schema::hasColumn('users', 'department_id')) {
                $table->dropColumn('department_id');
            }
        });

        Schema::dropIfExists('crm_user_roles');
        Schema::dropIfExists('crm_role_permissions');
        Schema::dropIfExists('crm_roles');
    }
};
