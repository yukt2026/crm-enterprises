<?php

namespace Database\Seeders;

use App\Domains\CRM\Models\Company;
use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Deal;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Tenancy\Models\SystemSetting;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tenancy\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed All Enterprise Roles
        $roles = [
            'Platform Super Admin',
            'Business Owner',
            'Business Admin',
            'Manager',
            'Sales Executive',
            'Support Executive',
            'HR',
            'Accountant',
            'Employee',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // Seed 18 Advanced Enterprise CRM Role Presets with Granular Permissions
        app(\App\Domains\Identity\Services\RbacService::class)->seedSystemRolePresets();

        // 2. Seed Primary Organization: Acme Enterprise Inc.
        $tenant1 = Tenant::updateOrCreate(
            ['slug' => 'acme-enterprise'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Acme Enterprise Inc.',
                'subscription_tier' => 'enterprise',
                'status' => 'active',
            ]
        );

        // 3. Seed Secondary Organization: Starlight Logistics (for isolation tests)
        $tenant2 = Tenant::updateOrCreate(
            ['slug' => 'starlight-logistics'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Starlight Logistics Hub',
                'subscription_tier' => 'pro',
                'status' => 'active',
            ]
        );

        // 4. Seed Primary User: John Nexus (Platform Super Admin & Owner of Acme)
        $john = User::updateOrCreate(
            ['email' => 'john@nexus.com'],
            [
                'tenant_id' => $tenant1->id,
                'current_tenant_id' => $tenant1->id,
                'legacy_id' => 'usr-john',
                'name' => 'John Nexus',
                'password' => Hash::make('AdminSecurePass2026!'),
                'role' => 'Business Owner',
                'is_platform_admin' => true,
                'platform_role' => 'super_admin',
                'status' => 'active',
                'department' => 'Executive',
                'two_factor_enabled' => true,
                'two_factor_secret' => '123456',
                'email_verified_at' => now(),
            ]
        );
        $john->assignRole('Platform Super Admin');

        // Membership in Acme (Owner)
        TenantUser::updateOrCreate(
            ['tenant_id' => $tenant1->id, 'user_id' => $john->id],
            ['role' => 'Business Owner', 'status' => 'active', 'is_owner' => true, 'joined_at' => now()]
        );

        // John is ALSO invited and accepted in Starlight Logistics as a Consultant Manager (Multi-workspace user!)
        TenantUser::updateOrCreate(
            ['tenant_id' => $tenant2->id, 'user_id' => $john->id],
            ['role' => 'Manager', 'status' => 'active', 'is_owner' => false, 'joined_at' => now()]
        );

        // 5. Seed Secondary User: Marcus Vance (Owner of Starlight Logistics ONLY - strictly Tenant 2)
        $marcus = User::updateOrCreate(
            ['email' => 'marcus@starlight.io'],
            [
                'tenant_id' => $tenant2->id,
                'current_tenant_id' => $tenant2->id,
                'legacy_id' => 'usr-marcus',
                'name' => 'Marcus Vance',
                'password' => Hash::make('AdminSecurePass2026!'),
                'role' => 'Business Owner',
                'is_platform_admin' => false,
                'status' => 'active',
                'department' => 'Operations',
                'two_factor_enabled' => false,
                'email_verified_at' => now(),
            ]
        );
        $marcus->assignRole('Business Owner');

        TenantUser::updateOrCreate(
            ['tenant_id' => $tenant2->id, 'user_id' => $marcus->id],
            ['role' => 'Business Owner', 'status' => 'active', 'is_owner' => true, 'joined_at' => now()]
        );

        // 6. Seed Acme Sales Executive
        $elena = User::updateOrCreate(
            ['email' => 'elena@nexus.com'],
            [
                'tenant_id' => $tenant1->id,
                'current_tenant_id' => $tenant1->id,
                'legacy_id' => 'usr-elena',
                'name' => 'Elena Rostova',
                'password' => Hash::make('AdminSecurePass2026!'),
                'role' => 'Sales Executive',
                'is_platform_admin' => false,
                'status' => 'active',
                'department' => 'Sales',
                'two_factor_enabled' => false,
                'email_verified_at' => now(),
            ]
        );
        $elena->assignRole('Sales Executive');

        TenantUser::updateOrCreate(
            ['tenant_id' => $tenant1->id, 'user_id' => $elena->id],
            ['role' => 'Sales Executive', 'status' => 'active', 'is_owner' => false, 'joined_at' => now()]
        );

        // 7. Seed Companies per Tenant
        $compAcme = Company::updateOrCreate(
            ['name' => 'Acme Global Corp'],
            [
                'tenant_id' => $tenant1->id,
                'industry' => 'Manufacturing',
                'website' => 'https://acmecorp.com',
                'size' => '200+',
                'annual_revenue' => 45000000.00,
                'address' => '742 Evergreen Terrace, Springfield',
                'phone' => '+1 (555) 019-2834',
            ]
        );

        $compStarlight = Company::updateOrCreate(
            ['name' => 'Starlight Fleet Ops'],
            [
                'tenant_id' => $tenant2->id,
                'industry' => 'Logistics',
                'website' => 'https://starlight.io',
                'size' => '51-200',
                'annual_revenue' => 12500000.00,
                'address' => '100 Terminal Way, Seattle, WA',
                'phone' => '+1 (555) 482-1920',
            ]
        );

        // 8. Seed Leads per Tenant
        Lead::updateOrCreate(
            ['email' => 'tech-lead@client.com'],
            [
                'tenant_id' => $tenant1->id,
                'company_id' => $compAcme->id,
                'company_name' => 'Acme Global Corp',
                'name' => 'Sarah Connor',
                'phone' => '+1 (555) 839-2019',
                'estimated_value' => 75000.00,
                'status' => 'qualified',
                'source' => 'Direct Inbound',
                'assigned_user_id' => $elena->id,
                'notes' => 'Tenant 1 Confidential Lead.',
            ]
        );

        Lead::updateOrCreate(
            ['email' => 'ops@cargo-hub.com'],
            [
                'tenant_id' => $tenant2->id,
                'company_id' => $compStarlight->id,
                'company_name' => 'Starlight Fleet Ops',
                'name' => 'Dave Bowman',
                'phone' => '+1 (555) 991-0022',
                'estimated_value' => 180000.00,
                'status' => 'new',
                'source' => 'Cold Email',
                'assigned_user_id' => $marcus->id,
                'notes' => 'Tenant 2 Confidential Logistics Lead.',
            ]
        );

        // 9. Seed Business Settings for both Tenants
        SystemSetting::updateOrCreate(
            ['tenant_id' => $tenant1->id],
            [
                'company_name' => 'Acme Enterprise Inc.',
                'company_email' => 'ops@acme.com',
                'company_phone' => '+1 (555) 019-2834',
                'tax_rate' => 18.00,
                'currency' => 'USD',
                'theme' => 'dark',
                'gstin_tax_id' => '27AABCU9603R1ZM',
                'timezone' => 'America/New_York',
                'brand_primary_color' => '#10b981',
            ]
        );

        SystemSetting::updateOrCreate(
            ['tenant_id' => $tenant2->id],
            [
                'company_name' => 'Starlight Logistics Hub',
                'company_email' => 'dispatch@starlight.io',
                'company_phone' => '+1 (555) 482-1920',
                'tax_rate' => 12.00,
                'currency' => 'EUR',
                'theme' => 'light',
                'gstin_tax_id' => '07AAGCS1234F1ZQ',
                'timezone' => 'Europe/London',
                'brand_primary_color' => '#6366f1',
            ]
        );
    }
}
