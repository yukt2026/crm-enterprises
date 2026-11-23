<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Extend Employees Table
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('tenant_id')->index();
            }
            if (!Schema::hasColumn('employees', 'probation_end_date')) {
                $table->date('probation_end_date')->nullable()->after('date_of_joining');
            }
            if (!Schema::hasColumn('employees', 'contract_end_date')) {
                $table->date('contract_end_date')->nullable()->after('probation_end_date');
            }
            if (!Schema::hasColumn('employees', 'notice_period_days')) {
                $table->unsignedSmallInteger('notice_period_days')->default(30)->after('contract_end_date');
            }
            if (!Schema::hasColumn('employees', 'pan_number')) {
                $table->string('pan_number', 50)->nullable()->after('work_location');
            }
            if (!Schema::hasColumn('employees', 'aadhaar_number')) {
                $table->string('aadhaar_number', 50)->nullable()->after('pan_number');
            }
            if (!Schema::hasColumn('employees', 'uan_number')) {
                $table->string('uan_number', 50)->nullable()->after('aadhaar_number');
            }
            if (!Schema::hasColumn('employees', 'esi_number')) {
                $table->string('esi_number', 50)->nullable()->after('uan_number');
            }
            if (!Schema::hasColumn('employees', 'bank_account_number')) {
                $table->string('bank_account_number', 100)->nullable()->after('esi_number');
            }
            if (!Schema::hasColumn('employees', 'bank_name')) {
                $table->string('bank_name', 100)->nullable()->after('bank_account_number');
            }
            if (!Schema::hasColumn('employees', 'bank_ifsc')) {
                $table->string('bank_ifsc', 50)->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('employees', 'salary_structure_id')) {
                $table->unsignedBigInteger('salary_structure_id')->nullable()->after('salary');
            }
        });

        // 2. Extend Attendances Table for Mobile & Field GPS
        Schema::table('attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('attendances', 'is_early_departure')) {
                $table->boolean('is_early_departure')->default(false)->after('is_half_day');
            }
            if (!Schema::hasColumn('attendances', 'early_minutes')) {
                $table->unsignedSmallInteger('early_minutes')->default(0)->after('is_early_departure');
            }
            if (!Schema::hasColumn('attendances', 'overtime_minutes')) {
                $table->unsignedSmallInteger('overtime_minutes')->default(0)->after('early_minutes');
            }
            if (!Schema::hasColumn('attendances', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('location');
            }
            if (!Schema::hasColumn('attendances', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (!Schema::hasColumn('attendances', 'accuracy_meters')) {
                $table->decimal('accuracy_meters', 8, 2)->nullable()->after('longitude');
            }
            if (!Schema::hasColumn('attendances', 'is_within_geofence')) {
                $table->boolean('is_within_geofence')->nullable()->after('accuracy_meters');
            }
            if (!Schema::hasColumn('attendances', 'selfie_url')) {
                $table->string('selfie_url', 255)->nullable()->after('is_within_geofence');
            }
            if (!Schema::hasColumn('attendances', 'verification_method')) {
                $table->string('verification_method', 50)->default('web')->after('selfie_url'); // web, mobile_gps, geofence, selfie, biometric, manual
            }
            if (!Schema::hasColumn('attendances', 'is_field_checkin')) {
                $table->boolean('is_field_checkin')->default(false)->after('verification_method');
            }
            if (!Schema::hasColumn('attendances', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('is_field_checkin')->index();
            }
        });

        // 3. Extend Company Holidays Table for Branch-specific calendar
        Schema::table('company_holidays', function (Blueprint $table) {
            if (!Schema::hasColumn('company_holidays', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('tenant_id')->index();
            }
        });

        // 4. Salary Structures Table (Configurable Indian Payroll Slabs)
        if (!Schema::hasTable('salary_structures')) {
            Schema::create('salary_structures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('code', 50)->nullable();
                $table->text('description')->nullable();
                $table->decimal('basic_percentage', 5, 2)->default(50.00); // % of monthly CTC
                $table->decimal('hra_percentage', 5, 2)->default(50.00); // % of basic
                $table->decimal('da_percentage', 5, 2)->default(0.00);
                $table->decimal('conveyance_allowance', 10, 2)->default(1600.00);
                $table->decimal('medical_allowance', 10, 2)->default(1250.00);
                $table->decimal('special_allowance', 10, 2)->default(0.00);
                
                // Statutory Settings
                $table->boolean('pf_enabled')->default(true);
                $table->decimal('pf_employee_percent', 5, 2)->default(12.00);
                $table->decimal('pf_employer_percent', 5, 2)->default(12.00);
                $table->decimal('pf_wage_ceiling', 10, 2)->default(15000.00); // statutory ₹15,000 threshold
                
                $table->boolean('esi_enabled')->default(true);
                $table->decimal('esi_employee_percent', 5, 2)->default(0.75);
                $table->decimal('esi_employer_percent', 5, 2)->default(3.25);
                $table->decimal('esi_gross_ceiling', 10, 2)->default(21000.00); // statutory ₹21,000 threshold
                
                $table->boolean('professional_tax_enabled')->default(true);
                $table->json('pt_slabs')->nullable(); // Configurable state slabs
                
                $table->string('tds_mode', 30)->default('slab'); // slab, fixed_percentage, exempt
                $table->json('rules')->nullable(); // Additional allowance & deduction formula hooks
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
            });
        }

        // 5. Employee Salaries (Assigned Compensation Records)
        if (!Schema::hasTable('employee_salaries')) {
            Schema::create('employee_salaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('salary_structure_id')->nullable()->constrained('salary_structures')->nullOnDelete();
                $table->decimal('monthly_ctc', 15, 2);
                $table->decimal('annual_ctc', 15, 2);
                $table->date('effective_date');
                
                // Monthly Computed Component Breakdown
                $table->decimal('basic_amount', 12, 2)->default(0);
                $table->decimal('hra_amount', 12, 2)->default(0);
                $table->decimal('conveyance_amount', 12, 2)->default(0);
                $table->decimal('special_allowance_amount', 12, 2)->default(0);
                
                // Statutory Deductions
                $table->decimal('pf_employee_amount', 12, 2)->default(0);
                $table->decimal('pf_employer_amount', 12, 2)->default(0);
                $table->decimal('esi_employee_amount', 12, 2)->default(0);
                $table->decimal('esi_employer_amount', 12, 2)->default(0);
                $table->decimal('professional_tax_amount', 12, 2)->default(0);
                $table->decimal('tds_amount', 12, 2)->default(0);
                
                $table->decimal('gross_salary', 15, 2)->default(0);
                $table->decimal('total_deductions', 15, 2)->default(0);
                $table->decimal('net_salary', 15, 2)->default(0);
                $table->boolean('is_current')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'employee_id', 'is_current']);
            });
        }

        // 6. Payroll Batches (Monthly Payroll Runs)
        if (!Schema::hasTable('payroll_batches')) {
            Schema::create('payroll_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('payroll_period', 20)->index(); // e.g. 2026-11
                $table->unsignedSmallInteger('month');
                $table->unsignedSmallInteger('year');
                $table->date('start_date');
                $table->date('end_date');
                $table->unsignedInteger('total_employees')->default(0);
                $table->decimal('gross_amount', 15, 2)->default(0);
                $table->decimal('total_deductions', 15, 2)->default(0);
                $table->decimal('net_amount', 15, 2)->default(0);
                $table->string('status', 30)->default('draft'); // draft, calculated, pending_approval, approved, disbursed, cancelled
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'year', 'month']);
            });
        }

        // 7. Payslips (Individual Line Item Records)
        if (!Schema::hasTable('payslips')) {
            Schema::create('payslips', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('payroll_batch_id')->constrained('payroll_batches')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('payslip_number', 50)->unique();
                
                // Attendance & Days Metrics
                $table->decimal('working_days', 4, 1)->default(30.0);
                $table->decimal('present_days', 4, 1)->default(0.0);
                $table->decimal('paid_leave_days', 4, 1)->default(0.0);
                $table->decimal('unpaid_leave_days', 4, 1)->default(0.0);
                $table->decimal('loss_of_pay_days', 4, 1)->default(0.0);
                $table->decimal('overtime_hours', 5, 2)->default(0.0);
                $table->decimal('overtime_amount', 10, 2)->default(0.0);
                
                // Earnings
                $table->decimal('basic_salary', 12, 2)->default(0);
                $table->decimal('hra', 12, 2)->default(0);
                $table->decimal('conveyance', 12, 2)->default(0);
                $table->decimal('special_allowance', 12, 2)->default(0);
                $table->decimal('incentives', 12, 2)->default(0);
                $table->decimal('commission_amount', 12, 2)->default(0);
                $table->decimal('reimbursements_amount', 12, 2)->default(0);
                $table->decimal('gross_earnings', 15, 2)->default(0);
                
                // Deductions
                $table->decimal('pf_employee', 12, 2)->default(0);
                $table->decimal('esi_employee', 12, 2)->default(0);
                $table->decimal('professional_tax', 12, 2)->default(0);
                $table->decimal('tds_deduction', 12, 2)->default(0);
                $table->decimal('other_deductions', 12, 2)->default(0);
                $table->decimal('total_deductions', 15, 2)->default(0);
                
                // Employer Contributions
                $table->decimal('pf_employer', 12, 2)->default(0);
                $table->decimal('esi_employer', 12, 2)->default(0);
                
                // Net Payable
                $table->decimal('net_pay', 15, 2)->default(0);
                $table->string('payment_status', 30)->default('unpaid'); // unpaid, processing, paid
                $table->string('payment_method', 50)->nullable();
                $table->string('payment_reference', 100)->nullable();
                $table->timestamp('paid_at')->nullable();
                
                // Audit & Calculation Trace
                $table->json('calculation_details')->nullable();
                $table->boolean('is_full_and_final')->default(false);
                $table->timestamps();

                $table->index(['tenant_id', 'employee_id']);
                $table->index(['tenant_id', 'payment_status']);
            });
        }

        // 8. Employee Documents Table (Compliance, Contracts & IDs)
        if (!Schema::hasTable('employee_documents')) {
            Schema::create('employee_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('document_type', 50); // contract, appointment_letter, id_proof, pan_card, aadhaar, qualification, certificate, policy_acknowledgement, other
                $table->string('title', 150);
                $table->string('document_number', 100)->nullable();
                $table->string('file_name', 255);
                $table->string('file_path', 500)->nullable();
                $table->unsignedInteger('file_size')->default(0);
                $table->string('mime_type', 100)->default('application/pdf');
                $table->date('issue_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->boolean('is_verified')->default(false);
                $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('verified_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'employee_id', 'document_type'], 'emp_docs_type_idx');
                $table->index(['tenant_id', 'expiry_date']);
            });
        }

        // 9. Employee Performance Reviews & Feedback
        if (!Schema::hasTable('employee_performance_reviews')) {
            Schema::create('employee_performance_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('review_period', 50); // 2026-Q1, 2026-Annual, etc.
                $table->date('review_date');
                $table->json('kpi_scores')->nullable(); // individual KPI breakdown
                $table->decimal('overall_rating', 3, 2)->default(5.00); // 1.00 to 5.00
                $table->text('strengths')->nullable();
                $table->text('improvements')->nullable();
                $table->text('goals')->nullable();
                $table->text('manager_feedback')->nullable();
                $table->string('status', 30)->default('draft'); // draft, submitted, acknowledged
                $table->timestamps();

                $table->index(['tenant_id', 'employee_id', 'review_period'], 'emp_perf_rev_idx');
            });
        }

        // 10. Employee Productivity Goals & Targets
        if (!Schema::hasTable('employee_productivity_goals')) {
            Schema::create('employee_productivity_goals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('metric_name', 100); // sales_revenue, leads_converted, calls_made, tickets_resolved, billable_hours
                $table->decimal('target_value', 15, 2);
                $table->decimal('achieved_value', 15, 2)->default(0);
                $table->date('period_start');
                $table->date('period_end');
                $table->string('status', 30)->default('on_track'); // on_track, achieved, missed
                $table->timestamps();

                $table->index(['tenant_id', 'employee_id'], 'emp_prod_goals_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_productivity_goals');
        Schema::dropIfExists('employee_performance_reviews');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_batches');
        Schema::dropIfExists('employee_salaries');
        Schema::dropIfExists('salary_structures');

        Schema::table('company_holidays', function (Blueprint $table) {
            if (Schema::hasColumn('company_holidays', 'branch_id')) {
                $table->dropColumn('branch_id');
            }
        });

        Schema::table('attendances', function (Blueprint $table) {
            $cols = [
                'is_early_departure', 'early_minutes', 'overtime_minutes',
                'latitude', 'longitude', 'accuracy_meters', 'is_within_geofence',
                'selfie_url', 'verification_method', 'is_field_checkin', 'branch_id'
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('attendances', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('employees', function (Blueprint $table) {
            $cols = [
                'branch_id', 'probation_end_date', 'contract_end_date', 'notice_period_days',
                'pan_number', 'aadhaar_number', 'uan_number', 'esi_number',
                'bank_account_number', 'bank_name', 'bank_ifsc', 'salary_structure_id'
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('employees', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
