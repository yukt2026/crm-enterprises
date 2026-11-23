<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\CreditLedgerEntry;
use App\Domains\Billing\Models\SubscriptionPlan;
use App\Domains\Billing\Models\TenantCreditWallet;
use App\Domains\Billing\Models\TenantSubscription;
use App\Domains\Billing\Services\CreditLedgerService;
use App\Domains\Billing\Services\SubscriptionBillingService;
use App\Domains\Billing\Services\UsageMeteringService;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaaSCommercialBillingMeteringTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $adminUserA;
    protected User $adminUserB;
    protected SubscriptionPlan $starterPlan;
    protected SubscriptionPlan $proPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Acme SaaS Enterprise',
            'domain' => 'acme-saas.test',
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Apex Competitor SaaS',
            'domain' => 'apex-saas.test',
            'status' => 'active',
        ]);

        $this->adminUserA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'role' => 'Business Admin',
            'name' => 'Alice Billing Admin',
            'email' => 'alice@acme-saas.test',
        ]);
        $this->adminUserA->workspaces()->attach($this->tenantA->id, ['role' => 'Business Admin', 'status' => 'active']);

        $this->adminUserB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'current_tenant_id' => $this->tenantB->id,
            'role' => 'Business Admin',
            'name' => 'Bob Competitor Admin',
            'email' => 'bob@apex-saas.test',
        ]);
        $this->adminUserB->workspaces()->attach($this->tenantB->id, ['role' => 'Business Admin', 'status' => 'active']);

        // Seed commercial plans
        $billingService = app(SubscriptionBillingService::class);
        $billingService->seedDefaultPlans();

        $this->starterPlan = SubscriptionPlan::where('code', 'starter')->first();
        $this->proPlan = SubscriptionPlan::where('code', 'pro')->first();
    }

    public function test_plans_directory_returns_active_commercial_plans_with_features_and_allowances(): void
    {
        Sanctum::actingAs($this->adminUserA);

        $response = $this->getJson('/api/v1/crm/billing/plans');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $plans = $response->json('data');
        $this->assertGreaterThanOrEqual(3, count($plans));

        $codes = collect($plans)->pluck('code')->all();
        $this->assertContains('starter', $codes);
        $this->assertContains('pro', $codes);
        $this->assertContains('enterprise', $codes);

        $starter = collect($plans)->firstWhere('code', 'starter');
        $this->assertArrayHasKey('limits', $starter);
        $this->assertArrayHasKey('included_allowance', $starter);
        $this->assertArrayHasKey('overage_pricing', $starter);
        $this->assertEquals(49.00, $starter['price']);
    }

    public function test_trial_lifecycle_and_plan_upgrade_with_proration_credit(): void
    {
        Sanctum::actingAs($this->adminUserA);

        $billingService = app(SubscriptionBillingService::class);

        // 1. Start free trial on Starter plan
        $sub = $billingService->startTrial($this->tenantA->id, $this->starterPlan->id);
        $this->assertEquals('trialing', $sub->status);
        $this->assertTrue($sub->isTrialing());

        // 2. Query subscription endpoint
        $queryResponse = $this->getJson('/api/v1/crm/billing/subscription');
        $queryResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'trialing')
            ->assertJsonPath('data.plan.code', 'starter');

        // 3. Upgrade plan to Professional ($149/mo) with proration
        $upgradeResponse = $this->postJson('/api/v1/crm/billing/subscribe', [
            'plan_id' => $this->proPlan->id,
            'prorate' => true,
        ]);

        $upgradeResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subscription.status', 'active')
            ->assertJsonPath('data.subscription.plan.code', 'pro');

        $this->assertDatabaseHas('tenant_subscriptions', [
            'tenant_id' => $this->tenantA->id,
            'plan_id' => $this->proPlan->id,
            'status' => 'active',
        ]);

        // Verify generated invoice
        $this->assertDatabaseHas('subscription_invoices', [
            'tenant_id' => $this->tenantA->id,
            'subscription_id' => $sub->id,
            'amount' => 149.00,
        ]);
    }

    public function test_usage_reservation_settlement_and_release_lifecycle(): void
    {
        Sanctum::actingAs($this->adminUserA);

        $ledgerService = app(CreditLedgerService::class);
        $meteringService = app(UsageMeteringService::class);

        // Top up wallet with $50.00
        $ledgerService->rechargeCredits($this->tenantA->id, 50.00, 'sim_pay_1', 'Initial Recharge');

        // Verify wallet
        $wallet = $ledgerService->getOrCreateWallet($this->tenantA->id);
        $this->assertEquals(50.00, $wallet->balance);

        // 1. Reserve usage for 100 AI calling minutes (unit rate $0.12 = $12.00 hold)
        $reservation = $meteringService->reserveUsage($this->tenantA->id, 'ai_call_minutes', 100.0);
        $this->assertEquals('reserved', $reservation->status);
        $this->assertEquals(12.00, $reservation->reserved_credit_amount);

        $wallet->refresh();
        $this->assertEquals(50.00, $wallet->balance);
        $this->assertEquals(12.00, $wallet->reserved_balance);
        $this->assertEquals(38.00, $wallet->available_balance);

        // 2. Reject reservation when estimated cost exceeds available balance ($38 available, requesting 500 mins = $60 hold)
        $this->expectException(\RuntimeException::class);
        $meteringService->reserveUsage($this->tenantA->id, 'ai_call_minutes', 500.0);
    }

    public function test_usage_settlement_separates_provider_cost_from_tenant_charge_and_releases_unused_hold(): void
    {
        Sanctum::actingAs($this->adminUserA);

        $ledgerService = app(CreditLedgerService::class);
        $meteringService = app(UsageMeteringService::class);

        // Top up wallet with $100.00
        $ledgerService->rechargeCredits($this->tenantA->id, 100.00, 'sim_pay_2', 'Initial Test Funds');

        // Reserve 50 AI calling minutes ($0.12 * 50 = $6.00 hold)
        $reservation = $meteringService->reserveUsage($this->tenantA->id, 'ai_call_minutes', 50.0);
        $this->assertEquals(6.00, $reservation->reserved_credit_amount);

        // Settle actual usage of only 30 minutes ($0.12 * 30 = $3.60 tenant charge, provider cost = $1.50)
        $settleResult = $meteringService->settleUsage(
            $reservation->reservation_id,
            30.0,
            1.50, // Provider cost!
            ['call_sid' => 'CA_test_123', 'provider' => 'exotel']
        );

        $this->assertTrue($settleResult['success']);
        $this->assertEquals(3.60, $settleResult['tenant_charge']);
        $this->assertEquals(1.50, $settleResult['provider_cost']);
        $this->assertEquals(2.40, $settleResult['released_hold']); // 6.00 - 3.60 = 2.40 released

        // Check wallet balances: $100.00 - $3.60 = $96.40, reserved_balance = 0.00
        $wallet = $ledgerService->getOrCreateWallet($this->tenantA->id);
        $this->assertEquals(96.40, (float) $wallet->balance);
        $this->assertEquals(0.00, (float) $wallet->reserved_balance);

        // Check UsageRecord in DB: verify provider cost is stored separately from tenant charge
        $this->assertDatabaseHas('usage_records', [
            'tenant_id' => $this->tenantA->id,
            'meter_type' => 'ai_call_minutes',
            'units' => 30.0,
            'tenant_charge_amount' => 3.60,
            'provider_cost_amount' => 1.50,
        ]);
    }

    public function test_release_reservation_returns_entire_hold_on_cancellation(): void
    {
        Sanctum::actingAs($this->adminUserA);

        $ledgerService = app(CreditLedgerService::class);
        $meteringService = app(UsageMeteringService::class);

        $ledgerService->rechargeCredits($this->tenantA->id, 40.00, 'pay_3', 'Funds');

        // Hold $12.00
        $res = $meteringService->reserveUsage($this->tenantA->id, 'ai_call_minutes', 100.0);
        $wallet = $ledgerService->getOrCreateWallet($this->tenantA->id);
        $this->assertEquals(12.00, (float) $wallet->reserved_balance);

        // Release reservation
        $released = $meteringService->releaseReservation($res->reservation_id, 'Call dropped before connect');
        $this->assertTrue($released);

        $wallet->refresh();
        $this->assertEquals(0.00, (float) $wallet->reserved_balance);
        $this->assertEquals(40.00, (float) $wallet->balance);
    }

    public function test_immutable_credit_ledger_records_and_mathematical_integrity_verification(): void
    {
        Sanctum::actingAs($this->adminUserA);

        $ledgerService = app(CreditLedgerService::class);

        // 1. Multiple credit transactions
        $ledgerService->rechargeCredits($this->tenantA->id, 100.00, 'ref_1', 'Recharge 1');
        $ledgerService->rechargeCredits($this->tenantA->id, 50.00, 'ref_2', 'Recharge 2');
        $ledgerService->adjustCredits($this->tenantA->id, -20.00, 'Manual SLA Compensation');

        $report = $ledgerService->verifyLedgerIntegrity($this->tenantA->id);
        $this->assertTrue($report['is_valid']);
        $this->assertEquals(130.00, $report['current_wallet_balance']);
        $this->assertEquals(130.00, $report['calculated_ledger_sum']);
        $this->assertEquals(0.00, $report['difference']);

        // 2. Verify immutability: attempting to update a ledger entry directly throws RuntimeException
        $entry = CreditLedgerEntry::where('tenant_id', $this->tenantA->id)->first();
        $this->expectException(\RuntimeException::class);
        $entry->update(['amount' => 999.00]);
    }

    public function test_razorpay_billing_webhook_signature_verification_and_idempotency(): void
    {
        $ledgerService = app(CreditLedgerService::class);
        $secret = 'rzp_whsec_test_secret_12345';
        config(['services.razorpay.webhook_secret' => $secret]);

        $payload = [
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_rzp_unique_849201',
                        'amount' => 5000, // 5000 paise = 50.00 USD/INR
                        'status' => 'captured',
                        'notes' => [
                            'type' => 'credit_recharge',
                            'tenant_id' => $this->tenantA->id,
                        ],
                    ],
                ],
            ],
        ];

        $payloadJson = json_encode($payload);
        $signature = hash_hmac('sha256', $payloadJson, $secret);

        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
        ];

        // 1. First webhook execution -> recharges wallet by $50.00
        $response1 = $this->call(
            'POST',
            '/api/v1/public/webhooks/razorpay/billing',
            [],
            [],
            [],
            $headers,
            $payloadJson
        );

        $response1->assertStatus(200)
            ->assertJsonPath('success', true);

        $wallet = $ledgerService->getOrCreateWallet($this->tenantA->id);
        $this->assertEquals(50.00, (float) $wallet->balance);

        // 2. Duplicate webhook dispatch -> idempotent ignore, does not double recharge
        $response2 = $this->call(
            'POST',
            '/api/v1/public/webhooks/razorpay/billing',
            [],
            [],
            [],
            $headers,
            $payloadJson
        );

        $response2->assertStatus(200)
            ->assertJsonPath('success', true);

        $wallet->refresh();
        $this->assertEquals(50.00, (float) $wallet->balance); // Still 50.00, not 100.00!
    }

    public function test_strict_cross_tenant_billing_and_ledger_isolation(): void
    {
        Sanctum::actingAs($this->adminUserA);

        $ledgerService = app(CreditLedgerService::class);
        $ledgerService->rechargeCredits($this->tenantA->id, 500.00, 'ref_a', 'Tenant A Secret Vault');

        // Tenant B queries wallet & ledger
        Sanctum::actingAs($this->adminUserB);

        $walletResB = $this->getJson('/api/v1/crm/billing/wallet');
        $walletResB->assertStatus(200)
            ->assertJsonPath('data.balance', 0); // Tenant B balance is 0, not 500!

        $ledgerResB = $this->getJson('/api/v1/crm/billing/wallet/ledger');
        $ledgerResB->assertStatus(200)
            ->assertJsonCount(0, 'data'); // Tenant B cannot see Tenant A's ledger entries!
    }
}
