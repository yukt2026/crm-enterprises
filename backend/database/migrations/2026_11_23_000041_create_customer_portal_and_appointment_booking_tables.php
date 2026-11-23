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
        // 1. Customer Accounts Table (External Portal Users)
        if (!Schema::hasTable('customer_accounts')) {
            Schema::create('customer_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->string('email', 150);
                $table->string('phone', 50)->nullable();
                $table->string('password');
                $table->boolean('is_active')->default(true);
                $table->timestamp('email_verified_at')->nullable();
                $table->boolean('two_factor_enabled')->default(false);
                $table->string('two_factor_secret', 255)->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->string('last_login_ip', 50)->nullable();
                $table->string('invite_token', 100)->nullable();
                $table->timestamp('invite_expires_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'email']);
                $table->index(['customer_id']);
                $table->index(['invite_token']);
            });
        }

        // 2. Customer Login Histories
        if (!Schema::hasTable('customer_login_histories')) {
            Schema::create('customer_login_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_account_id')->constrained('customer_accounts')->cascadeOnDelete();
                $table->string('ip_address', 50)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->string('status', 30)->default('success'); // success, failed
                $table->timestamp('created_at')->useCurrent();

                $table->index(['customer_account_id', 'created_at']);
            });
        }

        // 3. Customer Portal Organization Settings
        if (!Schema::hasTable('portal_settings')) {
            Schema::create('portal_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete()->unique();
                $table->boolean('is_portal_enabled')->default(true);
                $table->boolean('allow_self_registration')->default(false);
                $table->boolean('allow_invoice_access')->default(true);
                $table->boolean('allow_payments')->default(true);
                $table->boolean('allow_project_tracking')->default(true);
                $table->boolean('allow_ticket_creation')->default(true);
                $table->boolean('allow_appointment_booking')->default(true);
                $table->boolean('allow_document_downloads')->default(true);
                $table->boolean('public_booking_page_enabled')->default(true);
                $table->string('booking_slug', 100)->nullable();
                $table->string('company_logo_url', 255)->nullable();
                $table->string('brand_color', 30)->default('#4F46E5');
                $table->text('booking_terms')->nullable();
                $table->json('booking_rules')->nullable();
                $table->timestamps();

                $table->index(['booking_slug']);
            });
        }

        // 4. Bookable Services Catalog
        if (!Schema::hasTable('bookable_services')) {
            Schema::create('bookable_services', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('name', 150);
                $table->string('code', 50)->nullable();
                $table->text('description')->nullable();
                $table->integer('duration_minutes')->default(30);
                $table->decimal('price', 15, 2)->default(0);
                $table->string('currency', 10)->default('INR');
                $table->boolean('requires_advance_payment')->default(false);
                $table->decimal('advance_amount', 15, 2)->default(0);
                $table->json('assigned_staff_ids')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'is_active']);
            });
        }

        // 5. Enhance Meetings (Appointments) for Customer Self-Service & Booking
        if (Schema::hasTable('meetings')) {
            Schema::table('meetings', function (Blueprint $table) {
                if (!Schema::hasColumn('meetings', 'customer_id')) {
                    $table->foreignId('customer_id')->nullable()->after('tenant_id')->constrained('customers')->nullOnDelete();
                }
                if (!Schema::hasColumn('meetings', 'service_id')) {
                    $table->foreignId('service_id')->nullable()->after('customer_id')->constrained('bookable_services')->nullOnDelete();
                }
                if (!Schema::hasColumn('meetings', 'booking_reference')) {
                    $table->string('booking_reference', 60)->nullable()->after('service_id');
                }
                if (!Schema::hasColumn('meetings', 'payment_status')) {
                    $table->string('payment_status', 30)->default('unpaid')->after('booking_reference');
                }
                if (!Schema::hasColumn('meetings', 'booking_fee')) {
                    $table->decimal('booking_fee', 15, 2)->default(0)->after('payment_status');
                }
                if (!Schema::hasColumn('meetings', 'cancellation_reason')) {
                    $table->text('cancellation_reason')->nullable()->after('booking_fee');
                }
                if (!Schema::hasColumn('meetings', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
                }
                if (!Schema::hasColumn('meetings', 'rescheduled_at')) {
                    $table->timestamp('rescheduled_at')->nullable()->after('cancelled_at');
                }
                if (!Schema::hasColumn('meetings', 'is_customer_booked')) {
                    $table->boolean('is_customer_booked')->default(false)->after('rescheduled_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('meetings')) {
            Schema::table('meetings', function (Blueprint $table) {
                $columns = [
                    'customer_id', 'service_id', 'booking_reference', 'payment_status',
                    'booking_fee', 'cancellation_reason', 'cancelled_at', 'rescheduled_at', 'is_customer_booked',
                ];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('meetings', $col)) {
                        if (in_array($col, ['customer_id', 'service_id'])) {
                            $table->dropForeign([$col]);
                        }
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::dropIfExists('bookable_services');
        Schema::dropIfExists('portal_settings');
        Schema::dropIfExists('customer_login_histories');
        Schema::dropIfExists('customer_accounts');
    }
};
