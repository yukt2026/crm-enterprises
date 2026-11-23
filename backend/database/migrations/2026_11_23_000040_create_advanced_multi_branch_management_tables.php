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
        // 1. Extend Branches Table
        if (Schema::hasTable('branches')) {
            Schema::table('branches', function (Blueprint $table) {
                if (!Schema::hasColumn('branches', 'manager_user_id')) {
                    $table->foreignId('manager_user_id')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('branches', 'email')) {
                    $table->string('email', 150)->nullable()->after('manager_user_id');
                }
                if (!Schema::hasColumn('branches', 'phone')) {
                    $table->string('phone', 50)->nullable()->after('email');
                }
                if (!Schema::hasColumn('branches', 'address_line1')) {
                    $table->string('address_line1', 255)->nullable()->after('phone');
                }
                if (!Schema::hasColumn('branches', 'address_line2')) {
                    $table->string('address_line2', 255)->nullable()->after('address_line1');
                }
                if (!Schema::hasColumn('branches', 'pincode')) {
                    $table->string('pincode', 20)->nullable()->after('address_line2');
                }
                if (!Schema::hasColumn('branches', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable()->after('pincode');
                }
                if (!Schema::hasColumn('branches', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
                }
                if (!Schema::hasColumn('branches', 'business_hours')) {
                    $table->json('business_hours')->nullable()->after('longitude');
                }
                if (!Schema::hasColumn('branches', 'working_days')) {
                    $table->json('working_days')->nullable()->after('business_hours');
                }
                if (!Schema::hasColumn('branches', 'connected_channels')) {
                    $table->json('connected_channels')->nullable()->after('working_days');
                }
                if (!Schema::hasColumn('branches', 'settings')) {
                    $table->json('settings')->nullable()->after('connected_channels');
                }
                if (!Schema::hasColumn('branches', 'deleted_at')) {
                    $table->softDeletes()->after('updated_at');
                }
            });
        }

        // 2. Branch Departments Pivot Table
        if (!Schema::hasTable('branch_departments')) {
            Schema::create('branch_departments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['branch_id', 'department_id']);
            });
        }

        // 3. Branch Sales Targets Table
        if (!Schema::hasTable('branch_sales_targets')) {
            Schema::create('branch_sales_targets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->string('period_type', 30)->default('monthly'); // monthly, quarterly, annual
                $table->integer('period_year');
                $table->integer('period_number')->nullable(); // month 1-12 or quarter 1-4
                $table->decimal('target_amount', 15, 2)->default(0);
                $table->decimal('achieved_amount', 15, 2)->default(0);
                $table->integer('target_leads')->default(0);
                $table->integer('achieved_leads')->default(0);
                $table->integer('target_deals')->default(0);
                $table->integer('achieved_deals')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'branch_id', 'period_year']);
            });
        }

        // 4. Branch Transfers Audit & Workflow Table
        if (!Schema::hasTable('branch_transfers')) {
            Schema::create('branch_transfers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('transferable_type', 60); // lead, employee, project, ticket, meeting, inventory
                $table->unsignedBigInteger('transferable_id');
                $table->foreignId('from_branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('to_branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('initiated_by_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 30)->default('completed'); // pending, approved, rejected, completed
                $table->text('reason')->nullable();
                $table->text('approval_notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('transferred_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'transferable_type', 'transferable_id'], 'branch_transfers_target_idx');
                $table->index(['from_branch_id', 'to_branch_id'], 'branch_transfers_routes_idx');
            });
        }

        // 5. Ensure core tables have branch_id column
        // 5.1 Customers
        if (Schema::hasTable('customers') && !Schema::hasColumn('customers', 'branch_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained('branches')->nullOnDelete();
            });
        }

        // 5.2 Contacts
        if (Schema::hasTable('contacts') && !Schema::hasColumn('contacts', 'branch_id')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained('branches')->nullOnDelete();
            });
        }

        // 5.3 Meetings (Appointments)
        if (Schema::hasTable('meetings') && !Schema::hasColumn('meetings', 'branch_id')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained('branches')->nullOnDelete();
            });
        }

        // 5.4 Invoices
        if (Schema::hasTable('invoices') && !Schema::hasColumn('invoices', 'branch_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained('branches')->nullOnDelete();
            });
        }

        // 5.5 Payments
        if (Schema::hasTable('payments') && !Schema::hasColumn('payments', 'branch_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained('branches')->nullOnDelete();
            });
        }

        // 5.6 Warehouses
        if (Schema::hasTable('warehouses') && !Schema::hasColumn('warehouses', 'branch_id')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained('branches')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_transfers');
        Schema::dropIfExists('branch_sales_targets');
        Schema::dropIfExists('branch_departments');

        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'branch_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        if (Schema::hasTable('contacts') && Schema::hasColumn('contacts', 'branch_id')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        if (Schema::hasTable('meetings') && Schema::hasColumn('meetings', 'branch_id')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'branch_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'branch_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        if (Schema::hasTable('warehouses') && Schema::hasColumn('warehouses', 'branch_id')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }
    }
};
