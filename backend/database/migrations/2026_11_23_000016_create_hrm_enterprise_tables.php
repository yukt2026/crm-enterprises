<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Departments Table
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('department_head_id')->nullable();
            $table->foreignId('parent_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });

        // 2. Designations Table
        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'department_id']);
        });

        // 3. Shifts Table
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 50)->nullable();
            $table->time('start_time')->default('09:00:00');
            $table->time('end_time')->default('18:00:00');
            $table->unsignedSmallInteger('grace_period_minutes')->default(15);
            $table->unsignedSmallInteger('half_day_minutes')->default(240);
            $table->unsignedSmallInteger('full_day_minutes')->default(480);
            $table->unsignedSmallInteger('break_duration_minutes')->default(60);
            $table->json('working_days')->nullable(); // ['Monday', 'Tuesday', ...]
            $table->boolean('is_default')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['tenant_id', 'is_default']);
        });

        // 4. Company Holidays Table
        Schema::create('company_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->date('date');
            $table->text('description')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'date']);
        });

        // 5. Leave Types Table
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 50)->nullable();
            $table->decimal('days_allowed_per_year', 5, 2)->default(12.0);
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_attachment')->default(false);
            $table->unsignedSmallInteger('carry_forward_days')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // 6. Extend Employees Table
        Schema::table('employees', function (Blueprint $table) {
            $table->string('employee_id', 50)->nullable()->after('tenant_id');
            $table->string('profile_image', 255)->nullable()->after('name');
            $table->string('phone', 50)->nullable()->after('email');
            $table->foreignId('department_id')->nullable()->after('department')->constrained('departments')->nullOnDelete();
            $table->unsignedBigInteger('reporting_manager_id')->nullable()->after('designation');
            $table->foreignId('shift_id')->nullable()->after('reporting_manager_id')->constrained('shifts')->nullOnDelete();
            $table->string('employment_type', 50)->default('full_time')->after('status'); // full_time, part_time, contract, intern
            $table->string('work_location', 100)->default('Office')->after('employment_type');
            $table->json('emergency_contact')->nullable()->after('work_location');
            $table->json('skills')->nullable()->after('emergency_contact');
            $table->json('documents')->nullable()->after('skills');
            $table->text('notes')->nullable()->after('documents');

            $table->index(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'department_id']);
            $table->index(['tenant_id', 'reporting_manager_id']);
        });

        // Add foreign key for department_head_id after employees table extended
        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('department_head_id')->references('id')->on('employees')->nullOnDelete();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('reporting_manager_id')->references('id')->on('employees')->nullOnDelete();
        });

        // 7. Employee Transfers Table
        Schema::create('employee_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('from_designation', 100)->nullable();
            $table->string('to_designation', 100)->nullable();
            $table->unsignedBigInteger('from_manager_id')->nullable();
            $table->unsignedBigInteger('to_manager_id')->nullable();
            $table->date('transfer_date');
            $table->text('reason')->nullable();
            $table->foreignId('transferred_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id']);
        });

        // 8. Extend Attendances Table
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('employee_id')->constrained('shifts')->nullOnDelete();
            $table->time('break_start')->nullable()->after('check_out');
            $table->time('break_end')->nullable()->after('break_start');
            $table->unsignedSmallInteger('total_work_minutes')->default(0)->after('break_end');
            $table->unsignedSmallInteger('total_break_minutes')->default(0)->after('total_work_minutes');
            $table->boolean('is_late')->default(false)->after('status');
            $table->unsignedSmallInteger('late_minutes')->default(0)->after('is_late');
            $table->boolean('is_half_day')->default(false)->after('late_minutes');
            $table->string('location', 100)->nullable()->after('ip_address');
            $table->text('notes')->nullable()->after('location');
            $table->boolean('is_locked')->default(false)->after('notes');
            $table->foreignId('approved_by_user_id')->nullable()->after('is_locked')->constrained('users')->nullOnDelete();
        });

        // 9. Attendance Corrections Table
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained('attendances')->nullOnDelete();
            $table->date('date');
            $table->time('requested_check_in')->nullable();
            $table->time('requested_check_out')->nullable();
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reviewer_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id', 'status']);
        });

        // 10. Leave Balances Table
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('total_entitled', 5, 2)->default(0);
            $table->decimal('taken_days', 5, 2)->default(0);
            $table->decimal('pending_days', 5, 2)->default(0);
            $table->decimal('balance_days', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'employee_id', 'leave_type_id', 'year'], 'emp_leave_bal_unique');
        });

        // 11. Leave Balance Adjustments Table
        Schema::create('leave_balance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->decimal('previous_balance', 5, 2);
            $table->decimal('new_balance', 5, 2);
            $table->decimal('adjustment_days', 5, 2);
            $table->string('reason');
            $table->foreignId('adjusted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id']);
        });

        // 12. Extend Leaves Table
        Schema::table('leaves', function (Blueprint $table) {
            $table->foreignId('leave_type_id')->nullable()->after('employee_id')->constrained('leave_types')->nullOnDelete();
            $table->boolean('is_half_day')->default(false)->after('end_date');
            $table->string('half_day_session', 20)->nullable()->after('is_half_day'); // morning, afternoon
            $table->decimal('total_days', 4, 1)->default(1.0)->after('half_day_session');
            $table->string('attachment_url', 255)->nullable()->after('reason');
            $table->text('manager_notes')->nullable()->after('approved_by_user_id');
            $table->timestamp('approved_at')->nullable()->after('manager_notes');
            $table->timestamp('cancelled_at')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropForeign(['leave_type_id']);
            $table->dropColumn(['leave_type_id', 'is_half_day', 'half_day_session', 'total_days', 'attachment_url', 'manager_notes', 'approved_at', 'cancelled_at']);
        });

        Schema::dropIfExists('leave_balance_adjustments');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('attendance_corrections');

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropForeign(['approved_by_user_id']);
            $table->dropColumn(['shift_id', 'break_start', 'break_end', 'total_work_minutes', 'total_break_minutes', 'is_late', 'late_minutes', 'is_half_day', 'location', 'notes', 'is_locked', 'approved_by_user_id']);
        });

        Schema::dropIfExists('employee_transfers');

        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['department_head_id']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['reporting_manager_id']);
            $table->dropForeign(['shift_id']);
            $table->dropColumn([
                'employee_id',
                'profile_image',
                'phone',
                'department_id',
                'reporting_manager_id',
                'shift_id',
                'employment_type',
                'work_location',
                'emergency_contact',
                'skills',
                'documents',
                'notes'
            ]);
        });

        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('company_holidays');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('designations');
        Schema::dropIfExists('departments');
    }
};
