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
        // 1. Dashboard Widget Layouts
        if (!Schema::hasTable('dashboard_widget_layouts')) {
            Schema::create('dashboard_widget_layouts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->string('role', 50)->nullable(); // owner, admin, sales_manager, finance_manager, employee
                $table->string('name', 120);
                $table->boolean('is_default')->default(false);
                $table->json('widgets'); // array of widget configs
                $table->json('filters')->nullable(); // date_range, branch_id, department_id, employee_id
                $table->timestamps();

                $table->index(['tenant_id', 'user_id']);
                $table->index(['tenant_id', 'role']);
            });
        }

        // 2. Custom Analytics Alerts
        if (!Schema::hasTable('custom_analytics_alerts')) {
            Schema::create('custom_analytics_alerts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('name', 150);
                $table->string('metric_type', 60); // low_lead_conversion, overdue_payments, missed_followups, high_ticket_backlog, low_sales, high_expenses, branch_target_shortfall, subscription_usage_limit
                $table->string('condition_operator', 10)->default('>'); // >, <, >=, <=, ==
                $table->decimal('threshold_value', 14, 2);
                $table->json('notification_channels'); // ['in_app', 'email', 'whatsapp', 'webhook']
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_triggered_at')->nullable();
                $table->json('last_payload')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
                $table->index(['tenant_id', 'metric_type']);
            });
        }

        // 3. Field Sales Visits & Route Management
        if (!Schema::hasTable('field_sales_visits')) {
            Schema::create('field_sales_visits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('lead_id')->nullable();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->string('title', 180);
                $table->string('status', 40)->default('scheduled'); // scheduled, checked_in, completed, cancelled, missed
                $table->dateTime('scheduled_at');
                $table->dateTime('check_in_at')->nullable();
                $table->decimal('check_in_lat', 10, 7)->nullable();
                $table->decimal('check_in_lng', 10, 7)->nullable();
                $table->string('check_in_address', 255)->nullable();
                $table->dateTime('check_out_at')->nullable();
                $table->decimal('check_out_lat', 10, 7)->nullable();
                $table->decimal('check_out_lng', 10, 7)->nullable();
                $table->string('check_out_address', 255)->nullable();
                $table->decimal('distance_travelled_km', 8, 2)->default(0.00);
                $table->text('visit_notes')->nullable();
                $table->string('outcome', 60)->nullable(); // deal_closed, followup_required, not_interested, product_demo, payment_collected, rescheduled
                $table->date('follow_up_date')->nullable();
                $table->unsignedBigInteger('follow_up_task_id')->nullable();
                $table->decimal('travel_expense_amount', 12, 2)->default(0.00);
                $table->unsignedBigInteger('travel_expense_id')->nullable();
                $table->json('route_waypoints')->nullable();
                $table->boolean('is_offline_synced')->default(false);
                $table->string('offline_client_uuid', 64)->nullable()->unique();
                $table->timestamps();

                $table->index(['tenant_id', 'employee_id']);
                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'scheduled_at']);
                $table->index(['tenant_id', 'branch_id']);
            });
        }

        // 4. Enhance saved_reports table with module and advanced grouping/sorting
        if (Schema::hasTable('saved_reports')) {
            Schema::table('saved_reports', function (Blueprint $table) {
                if (!Schema::hasColumn('saved_reports', 'module')) {
                    $table->string('module', 50)->default('leads')->after('report_type');
                }
                if (!Schema::hasColumn('saved_reports', 'group_by')) {
                    $table->string('group_by', 60)->nullable()->after('module');
                }
                if (!Schema::hasColumn('saved_reports', 'sort_by')) {
                    $table->string('sort_by', 60)->nullable()->after('group_by');
                }
                if (!Schema::hasColumn('saved_reports', 'sort_direction')) {
                    $table->string('sort_direction', 10)->default('desc')->after('sort_by');
                }
                if (!Schema::hasColumn('saved_reports', 'aggregations')) {
                    $table->json('aggregations')->nullable()->after('selected_columns');
                }
                if (!Schema::hasColumn('saved_reports', 'exported_at')) {
                    $table->timestamp('exported_at')->nullable()->after('last_run_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_sales_visits');
        Schema::dropIfExists('custom_analytics_alerts');
        Schema::dropIfExists('dashboard_widget_layouts');

        if (Schema::hasTable('saved_reports')) {
            Schema::table('saved_reports', function (Blueprint $table) {
                $columns = ['module', 'group_by', 'sort_by', 'sort_direction', 'aggregations', 'exported_at'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('saved_reports', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
