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
        // 1. Commercial Subscription Plans
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('code', 64)->unique(); // starter, pro, enterprise, custom
            $table->text('description')->nullable();
            $table->string('billing_cycle', 32)->default('monthly'); // monthly, annual
            $table->decimal('price', 10, 2)->default(0.00);
            $table->string('currency', 3)->default('USD');
            $table->integer('trial_days')->default(14);
            $table->integer('grace_period_days')->default(7);
            $table->json('features')->nullable(); // ['whatsapp_ai', 'voice_calling', 'workflows', 'landing_pages']
            $table->json('limits')->nullable(); // seats, leads, whatsapp_numbers, ai_agents, workflows, storage_mb, api_rpm
            $table->json('included_allowance')->nullable(); // llm_tokens, ai_call_minutes, telephony_minutes, whatsapp_messages, sms_segments, email_volume
            $table->json('overage_pricing')->nullable(); // per-unit overage rates charged to tenant
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Tenant Subscriptions
        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('plan_id')->index();
            $table->string('status', 32)->default('trialing')->index(); // trialing, active, past_due, grace_period, cancelled, expired
            $table->timestamp('trial_starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable()->useCurrent();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('grace_period_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('razorpay_subscription_id', 128)->nullable()->index();
            $table->string('razorpay_customer_id', 128)->nullable();
            $table->string('billing_contact_name', 255)->nullable();
            $table->string('billing_email', 255)->nullable();
            $table->text('billing_address')->nullable();
            $table->string('tax_id', 64)->nullable(); // GSTIN, VAT, TIN
            $table->boolean('auto_renew')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('plan_id')->references('id')->on('subscription_plans')->onDelete('cascade');
        });

        // 3. Tenant Credit Wallets (Pre-funded credits)
        Schema::create('tenant_credit_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->unique();
            $table->decimal('balance', 14, 4)->default(0.0000);
            $table->decimal('reserved_balance', 14, 4)->default(0.0000);
            $table->string('currency', 3)->default('USD');
            $table->timestamp('last_recharged_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // 4. Immutable Credit Ledger Entries
        Schema::create('credit_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('entry_id')->unique();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('entry_type', 32); // recharge, reservation, settlement, release, refund, adjustment
            $table->decimal('amount', 14, 4); // signed amount (+ or -)
            $table->decimal('balance_after', 14, 4); // wallet snapshot after entry
            $table->string('reference_id', 128)->nullable()->index();
            $table->string('description', 255);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // 5. Usage Reservations (Pre-action hold lifecycle)
        Schema::create('usage_reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('reservation_id')->unique();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('meter_type', 64)->index(); // llm_tokens, ai_call_minutes, telephony_minutes, whatsapp_messages, sms_segments, email_volume, storage_mb
            $table->decimal('estimated_units', 12, 4);
            $table->decimal('reserved_credit_amount', 14, 4);
            $table->string('status', 32)->default('reserved')->index(); // reserved, settled, partially_settled, released, expired
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // 6. Actual Usage Records (Meters & Provider Cost Reconciliation)
        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->uuid('reservation_id')->nullable()->index();
            $table->string('meter_type', 64)->index();
            $table->decimal('units', 12, 4);
            $table->decimal('tenant_charge_amount', 14, 4)->default(0.0000);
            $table->decimal('provider_cost_amount', 14, 4)->default(0.0000); // Tracked separately from tenant charge
            $table->boolean('is_overage')->default(false);
            $table->date('billing_period_start');
            $table->date('billing_period_end');
            $table->json('metadata')->nullable(); // provider model, call sid, twilio sid, token breakdown
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // 7. Cost Protection & Spending Caps
        Schema::create('tenant_cost_protection_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->unique();
            $table->decimal('daily_spending_cap', 10, 2)->default(100.00);
            $table->decimal('monthly_spending_cap', 10, 2)->default(2000.00);
            $table->decimal('ai_provider_spending_cap', 10, 2)->default(500.00);
            $table->boolean('auto_pause_on_breach')->default(true);
            $table->decimal('low_balance_alert_threshold', 10, 2)->default(20.00);
            $table->boolean('is_ai_calling_unlimited_allowed')->default(false); // No unlimited calling without explicit platform rule
            $table->boolean('is_paused')->default(false);
            $table->string('pause_reason', 255)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // 8. Subscription Invoices & Billing Statements
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('subscription_id')->nullable()->index();
            $table->string('invoice_number', 64)->unique();
            $table->decimal('amount', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2);
            $table->string('status', 32)->default('pending')->index(); // pending, paid, failed, void
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->string('razorpay_payment_id', 128)->nullable();
            $table->json('line_items')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('subscription_id')->references('id')->on('tenant_subscriptions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('tenant_cost_protection_rules');
        Schema::dropIfExists('usage_records');
        Schema::dropIfExists('usage_reservations');
        Schema::dropIfExists('credit_ledger_entries');
        Schema::dropIfExists('tenant_credit_wallets');
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
