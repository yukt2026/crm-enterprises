<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

// Models
use App\Models\User;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tenancy\Models\TenantUser;
use App\Domains\Tenancy\Models\SystemSetting;
use App\Domains\Tenancy\Services\TenantContext;
use App\Domains\CRM\Models\Company;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Deal;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Sales\Models\Product;
use App\Domains\Sales\Models\Service;
use App\Domains\Finance\Models\Quotation;
use App\Domains\Finance\Models\QuotationItem;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\InvoiceItem;
use App\Domains\Finance\Models\Payment;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\Meeting;
use App\Domains\Projects\Models\MeetingAttendee;
use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Employee;
use App\Domains\HR\Models\Attendance;
use App\Domains\HR\Models\Leave;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\AuditSecurity\Models\ActivityLog;
use App\Domains\Identity\Models\Notification;

class MigrateJsonDataCommand extends Command
{
    protected $signature = 'crm:migrate-json 
                            {--file= : Path to crm_db.json file} 
                            {--tenant-id=1 : Target tenant ID for migrated records} 
                            {--dry-run : Run validation and mapping without saving changes} 
                            {--force : Force execution without interactive confirmation}';

    protected $description = 'Migrate legacy file-based JSON CRM database to multi-tenant MySQL schema';

    /**
     * ID Mappings between old JSON IDs and new database integer IDs
     */
    protected array $userIdMap = [];
    protected array $userNameMap = [];
    protected array $userEmailMap = [];
    protected array $companyIdMap = [];
    protected array $customerIdMap = [];
    protected array $customerNameMap = [];
    protected array $contactIdMap = [];
    protected array $productIdMap = [];
    protected array $serviceIdMap = [];
    protected array $dealIdMap = [];
    protected array $quotationIdMap = [];
    protected array $invoiceIdMap = [];
    protected array $projectIdMap = [];
    protected array $employeeIdMap = [];

    protected array $stats = [];
    protected array $errorReport = [];

    public function handle(): int
    {
        $this->info('===========================================================');
        $this->info('  NEXUS ENTERPRISE CRM - JSON DATABASE CUTOVER ENGINE      ');
        $this->info('===========================================================');

        $filePath = $this->resolveFilePath();
        if (!$filePath || !File::exists($filePath)) {
            $this->error("Target JSON file not found at: " . ($filePath ?? 'null'));
            return 1;
        }

        $tenantId = (int) $this->option('tenant-id');
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            $tenant = Tenant::first();
            if ($tenant) {
                $tenantId = $tenant->id;
                $this->warn("Tenant {$this->option('tenant-id')} not found. Using primary tenant: {$tenant->name} (ID: {$tenantId})");
            } else {
                $this->error("No tenants exist in the database. Please initialize multi-tenancy first.");
                return 1;
            }
        }

        $isDryRun = (bool) $this->option('dry-run');
        TenantContext::setTenantId($tenantId);
        if ($isDryRun) {
            $this->warn(">>> DRY-RUN MODE ACTIVE: Changes will NOT be committed to the database. <<<");
        } elseif (!$this->option('force') && !$this->confirm("Are you ready to migrate JSON data to Tenant ID {$tenantId} ({$tenant->name})?", true)) {
            $this->info("Migration cancelled by user.");
            return 0;
        }

        $rawJson = File::get($filePath);
        $data = json_decode($rawJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Invalid JSON format: " . json_last_error_msg());
            return 1;
        }

        $this->info("Loaded JSON file: {$filePath} (" . number_format(strlen($rawJson)) . " bytes)");

        // Execute migration inside a managed transaction
        DB::beginTransaction();

        try {
            $this->migrateSettings($data['settings'] ?? [], $tenantId, $isDryRun);
            $this->migrateUsers($data['users'] ?? [], $tenantId, $isDryRun);
            $this->migrateCompanies($data['companies'] ?? [], $tenantId, $isDryRun);
            $this->migrateCustomers($data['customers'] ?? [], $tenantId, $isDryRun);
            $this->migrateContacts($data['contacts'] ?? [], $tenantId, $isDryRun);
            $this->migrateLeads($data['leads'] ?? [], $tenantId, $isDryRun);
            $this->migrateProducts($data['products'] ?? [], $tenantId, $isDryRun);
            $this->migrateServices($data['services'] ?? [], $tenantId, $isDryRun);
            $this->migrateDeals($data['deals'] ?? [], $tenantId, $isDryRun);
            $this->migrateQuotations($data['quotations'] ?? [], $tenantId, $isDryRun);
            $this->migrateInvoices($data['invoices'] ?? [], $tenantId, $isDryRun);
            $this->migratePayments($data['payments'] ?? [], $tenantId, $isDryRun);
            $this->migrateProjects($data['projects'] ?? [], $tenantId, $isDryRun);
            $this->migrateTasks($data['tasks'] ?? [], $tenantId, $isDryRun);
            $this->migrateMeetings($data['meetings'] ?? [], $tenantId, $isDryRun);
            $this->migrateEmployees($data['employees'] ?? [], $tenantId, $isDryRun);
            $this->migrateAttendance($data['attendance'] ?? [], $tenantId, $isDryRun);
            $this->migrateLeaves($data['leaves'] ?? [], $tenantId, $isDryRun);
            $this->migrateTickets($data['tickets'] ?? [], $tenantId, $isDryRun);
            $this->migrateActivityLogs($data['activityLogs'] ?? [], $tenantId, $isDryRun);
            $this->migrateNotifications($data['notifications'] ?? [], $tenantId, $isDryRun);

            if ($isDryRun) {
                DB::rollBack();
                $this->info("\nDry run completed successfully. All changes rolled back.");
            } else {
                DB::commit();
                $this->info("\nMigration committed successfully to MySQL database.");
            }

            $this->printSummaryTable();

            if (!empty($this->errorReport)) {
                $this->warn("\nMigration Diagnostic Warnings (" . count($this->errorReport) . "):");
                foreach ($this->errorReport as $warning) {
                    $this->line("  [!] " . $warning);
                }
            }

            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("\nMigration aborted due to unhandled exception: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Resolve path to crm_db.json
     */
    protected function resolveFilePath(): ?string
    {
        $candidate = $this->option('file');
        if ($candidate && is_file($candidate)) {
            return realpath($candidate);
        }

        $candidates = [
            $candidate ? base_path($candidate) : null,
            base_path('../frontend/crm_db.json'),
            base_path('frontend/crm_db.json'),
            base_path('../crm_db.json'),
            base_path('crm_db.json'),
            dirname(base_path()) . '/frontend/crm_db.json',
        ];

        foreach ($candidates as $path) {
            if ($path && is_file($path)) {
                return realpath($path);
            }
        }

        return null;
    }

    protected function initStats(string $name, int $sourceCount): void
    {
        $this->stats[$name] = [
            'entity' => $name,
            'source' => $sourceCount,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
    }

    /**
     * 1. Settings Migration
     */
    protected function migrateSettings(array $settings, int $tenantId, bool $isDryRun): void
    {
        $this->initStats('Settings', empty($settings) ? 0 : 1);
        if (empty($settings)) return;

        try {
            $systemSetting = SystemSetting::firstOrNew(['tenant_id' => $tenantId]);
            $isNew = !$systemSetting->exists;

            $systemSetting->company_name = $settings['companyName'] ?? $systemSetting->company_name ?? 'Acme Enterprise Inc.';
            $systemSetting->company_email = $settings['companyEmail'] ?? $systemSetting->company_email ?? 'ops@acme.com';
            $systemSetting->company_phone = $settings['companyPhone'] ?? $systemSetting->company_phone ?? '+1 (555) 019-2834';
            $systemSetting->tax_rate = isset($settings['taxRate']) ? (float)$settings['taxRate'] : ($systemSetting->tax_rate ?? 15.00);
            $systemSetting->currency = $settings['currency'] ?? $systemSetting->currency ?? 'USD';
            $systemSetting->theme = in_array($settings['theme'] ?? '', ['light', 'dark']) ? $settings['theme'] : 'light';

            if (!$isDryRun) {
                $systemSetting->save();
            }

            if ($isNew) {
                $this->stats['Settings']['created']++;
            } else {
                $this->stats['Settings']['updated']++;
            }
        } catch (\Throwable $e) {
            $this->stats['Settings']['errors']++;
            $this->errorReport[] = "Settings: " . $e->getMessage();
        }
    }

    /**
     * 2. Users Migration
     */
    protected function migrateUsers(array $users, int $tenantId, bool $isDryRun): void
    {
        $this->initStats('Users', count($users));

        foreach ($users as $u) {
            try {
                $email = trim($u['email'] ?? '');
                if (!$email) {
                    $this->stats['Users']['skipped']++;
                    continue;
                }

                $user = User::where('email', $email)->first();
                $isNew = false;
                if (!$user) {
                    $user = new User();
                    $user->email = $email;
                    $isNew = true;
                }

                $user->name = $u['name'] ?? explode('@', $email)[0];
                $user->role = $this->normalizeRole($u['role'] ?? 'Employee');
                $user->legacy_id = $u['id'] ?? null;
                $user->department = $u['department'] ?? null;
                $user->status = ($u['status'] ?? 'active') === 'active' ? 'active' : 'inactive';
                $user->two_factor_enabled = !empty($u['twoFactorEnabled']);
                
                // Password handling
                if ($isNew || !empty($u['passwordHash'])) {
                    $rawPass = $u['passwordHash'] ?? 'NexusSecurePass2026!';
                    $user->password = Hash::needsRehash($rawPass) ? Hash::make($rawPass) : $rawPass;
                }

                if (!empty($u['lastActive'])) {
                    $user->last_active_at = Carbon::parse($u['lastActive']);
                }

                if (!empty($u['createdAt'])) {
                    $user->created_at = Carbon::parse($u['createdAt']);
                }

                if (!$isDryRun) {
                    $user->save();
                    
                    // Attach to TenantUser pivot if not already associated
                    TenantUser::firstOrCreate(
                        ['tenant_id' => $tenantId, 'user_id' => $user->id],
                        ['role' => $user->role, 'is_active' => ($u['status'] ?? 'active') === 'active']
                    );
                }

                // Map old ID and attributes
                $resolvedId = $user->id ?? (9000 + count($this->userIdMap));
                if (!empty($u['id'])) {
                    $this->userIdMap[$u['id']] = $resolvedId;
                }
                $this->userNameMap[$user->name] = $resolvedId;
                $this->userEmailMap[$user->email] = $resolvedId;

                if ($isNew) {
                    $this->stats['Users']['created']++;
                } else {
                    $this->stats['Users']['updated']++;
                }
            } catch (\Throwable $e) {
                $this->stats['Users']['errors']++;
                $this->errorReport[] = "User ({$u['email']}): " . $e->getMessage();
            }
        }
    }

    /**
     * 3. Companies Migration
     */
    protected function migrateCompanies(array $companies, int $tenantId, bool $isDryRun): void
    {
        $this->initStats('Companies', count($companies));

        foreach ($companies as $comp) {
            try {
                $name = trim($comp['name'] ?? '');
                if (!$name) {
                    $this->stats['Companies']['skipped']++;
                    continue;
                }

                $company = Company::where('tenant_id', $tenantId)->where('name', $name)->first();
                $isNew = false;
                if (!$company) {
                    $company = new Company();
                    $company->tenant_id = $tenantId;
                    $company->name = $name;
                    $isNew = true;
                }

                $company->industry = $comp['industry'] ?? null;
                $company->website = $comp['website'] ?? null;
                $company->size = $comp['size'] ?? null;
                $company->annual_revenue = $this->parseRevenue($comp['revenue'] ?? 0);
                $company->address = $comp['address'] ?? null;
                $company->phone = $comp['phone'] ?? null;

                if (!empty($comp['createdAt'])) {
                    $company->created_at = Carbon::parse($comp['createdAt']);
                }

                if (!$isDryRun) {
                    $company->save();
                }

                $resolvedId = $company->id ?? (8000 + count($this->companyIdMap));
                if (!empty($comp['id'])) {
                    $this->companyIdMap[$comp['id']] = $resolvedId;
                }

                if ($isNew) {
                    $this->stats['Companies']['created']++;
                } else {
                    $this->stats['Companies']['updated']++;
                }
            } catch (\Throwable $e) {
                $this->stats['Companies']['errors']++;
                $this->errorReport[] = "Company ({$comp['name']}): " . $e->getMessage();
            }
        }
    }

    /**
     * 4. Customers Migration
     */
    protected function migrateCustomers(array $customers, int $tenantId, bool $isDryRun): void
    {
        $this->initStats('Customers', count($customers));

        foreach ($customers as $c) {
            try {
                $name = trim($c['name'] ?? '');
                $email = trim($c['email'] ?? '');
                
                $customer = Customer::where('tenant_id', $tenantId)
                    ->where(function ($q) use ($email, $name) {
                        if ($email) $q->where('email', $email);
                        else $q->where('name', $name);
                    })->first();

                $isNew = false;
                if (!$customer) {
                    $customer = new Customer();
                    $customer->tenant_id = $tenantId;
                    $customer->name = $name;
                    $isNew = true;
                }

                $customer->email = $email ?: null;
                $customer->phone = $c['phone'] ?? null;
                $customer->status = ($c['status'] ?? 'active') === 'active' ? 'active' : 'inactive';

                // Resolve company
                if (!empty($c['companyId']) && isset($this->companyIdMap[$c['companyId']])) {
                    $customer->company_id = $this->companyIdMap[$c['companyId']];
                } elseif (!empty($c['companyName'])) {
                    $matchComp = Company::where('tenant_id', $tenantId)->where('name', $c['companyName'])->value('id');
                    if ($matchComp) $customer->company_id = $matchComp;
                }

                // Resolve assigned user
                if (!empty($c['assignedTo'])) {
                    $customer->assigned_user_id = $this->resolveUserId($c['assignedTo']);
                }

                if (!empty($c['createdAt'])) {
                    $customer->created_at = Carbon::parse($c['createdAt']);
                }

                if (!$isDryRun) {
                    $customer->save();
                }

                $resolvedId = $customer->id ?? (7000 + count($this->customerIdMap));
                if (!empty($c['id'])) {
                    $this->customerIdMap[$c['id']] = $resolvedId;
                }
                $this->customerNameMap[$customer->name] = $resolvedId;

                if ($isNew) {
                    $this->stats['Customers']['created']++;
                } else {
                    $this->stats['Customers']['updated']++;
                }
            } catch (\Throwable $e) {
                $this->stats['Customers']['errors']++;
                $this->errorReport[] = "Customer ({$c['name']}): " . $e->getMessage();
            }
        }
    }

    /**
     * 5. Contacts Migration
     */
    protected function migrateContacts(array $contacts, int $tenantId, bool $isDryRun): void
    {
        $this->initStats('Contacts', count($contacts));

        foreach ($contacts as $cnt) {
            try {
                $name = trim($cnt['name'] ?? '');
                $email = trim($cnt['email'] ?? '');

                $contact = Contact::where('tenant_id', $tenantId)
                    ->where(function ($q) use ($email, $name) {
                        if ($email) $q->where('email', $email);
                        else $q->where('name', $name);
                    })->first();

                $isNew = false;
                if (!$contact) {
                    $contact = new Contact();
                    $contact->tenant_id = $tenantId;
                    $contact->name = $name;
                    $isNew = true;
                }

                $contact->email = $email ?: null;
                $contact->phone = $cnt['phone'] ?? null;
                $contact->job_title = $cnt['role'] ?? $cnt['jobTitle'] ?? null;
                $contact->is_primary = !empty($cnt['isPrimary']);

                if (!empty($cnt['companyId']) && isset($this->companyIdMap[$cnt['companyId']])) {
                    $contact->company_id = $this->companyIdMap[$cnt['companyId']];
                }

                if (!empty($cnt['createdAt'])) {
                    $contact->created_at = Carbon::parse($cnt['createdAt']);
                }

                if (!$isDryRun) {
                    $contact->save();
                }

                $resolvedId = $contact->id ?? (6000 + count($this->contactIdMap));
                if (!empty($cnt['id'])) {
                    $this->contactIdMap[$cnt['id']] = $resolvedId;
                }

                if ($isNew) {
                    $this->stats['Contacts']['created']++;
                } else {
                    $this->stats['Contacts']['updated']++;
                }
            } catch (\Throwable $e) {
                $this->stats['Contacts']['errors']++;
                $this->errorReport[] = "Contact ({$cnt['name']}): " . $e->getMessage();
            }
        }
    }

    /**
     * 6. Leads Migration
     */
    protected function migrateLeads(array $leads, int $tenantId, bool $isDryRun): void
    {
        $this->initStats('Leads', count($leads));

        foreach ($leads as $l) {
            try {
                $name = trim($l['name'] ?? '');
                $email = trim($l['email'] ?? '');

                $lead = Lead::where('tenant_id', $tenantId)
                    ->where(function ($q) use ($email, $name) {
                        if ($email) $q->where('email', $email);
                        else $q->where('name', $name);
                    })->first();

                $isNew = false;
                if (!$lead) {
                    $lead = new Lead();
                    $lead->tenant_id = $tenantId;
                    $lead->name = $name;
                    $isNew = true;
                }

                $lead->company_name = $l['company'] ?? null;
                $lead->email = $email ?: null;
                $lead->phone = $l['phone'] ?? null;
                $lead->estimated_value = (float)($l['value'] ?? 0);
                $lead->status = strtolower($l['status'] ?? 'new');
                $lead->source = $l['source'] ?? 'Direct Inbound';
                $lead->notes = $l['notes'] ?? null;

                if (!empty($lead->company_name)) {
                    $compMatch = Company::where('tenant_id', $tenantId)->where('name', $lead->company_name)->value('id');
                    if ($compMatch) $lead->company_id = $compMatch;
                }

                if (!empty($l['assignedTo'])) {
                    $lead->assigned_user_id = $this->resolveUserId($l['assignedTo']);
                }

                if (!empty($l['createdAt'])) {
                    $lead->created_at = Carbon::parse($l['createdAt']);
                }

                if (!$isDryRun) {
                    $lead->save();
                }

                if ($isNew) {
                    $this->stats['Leads']['created']++;
                } else {
                    $this->stats['Leads']['updated']++;
                }
            } catch (\Throwable $e) {
                $this->stats['Leads']['errors']++;
                $this->errorReport[] = "Lead ({$l['name']}): " . $e->getMessage();
            }
        }
    }

    /**
     * 7. Products Migration
     */
    protected function migrateProducts(array $products, int $tenantId, bool $isDryRun): void
    {
        $this->initStats('Products', count($products));

        foreach ($products as $p) {
            try {
                $sku = trim($p['sku'] ?? '');
                $name = trim($p['name'] ?? '');

                $product = Product::where('tenant_id', $tenantId)
                    ->where(function ($q) use ($sku, $name) {
                        if ($sku) $q->where('sku', $sku);
                        else $q->where('name', $name);
                    })->first();

                $isNew = false;
                if (!$product) {
                    $product = new Product();
                    $product->tenant_id = $tenantId;
                    $product->name = $name;
                    $isNew = true;
                }

                $product->sku = $sku ?: null;
                $product->price = (float)($p['price'] ?? 0);
                $product->category = $p['category'] ?? 'General';
                $product->status = ($p['status'] ?? 'active') === 'active' ? 'active' : 'inactive';
                $product->description = $p['description'] ?? null;

                if (!$isDryRun) {
                    $product->save();
                }

                $resolvedId = $product->id ?? (5000 + count($this->productIdMap));
                if (!empty($p['id'])) {
                    $this->productIdMap[$p['id']] = $resolvedId;
                }

                if ($isNew) {
                    $this->stats['Products']['created']++;
                } else {
                    $this->stats['Products']['updated']++;
                }
            } catch (\Throwable $e) {
                $this->stats['Products']['errors']++;
                $this->errorReport[] = "Product ({$p['name']}): " . $e->getMessage();
            }
        }
    }

    /**
     * 8. Services Migration
     */
    protected function migrateServices(array $services, int $tenantId, bool $isDryRun): void
    {
        $this->initStats('Services', count($services));

        foreach ($services as $s) {
            try {
                $name = trim($s['name'] ?? '');

                $service = Service::where('tenant_id', $tenantId)->where('name', $name)->first();
                $isNew = false;
                if (!$service) {
                    $service = new Service();
                    $service->tenant_id = $tenantId;
                    $service->name = $name;
                    $isNew = true;
                }

                $service->billing_cycle = in_array($s['billingCycle'] ?? '', ['monthly', 'yearly', 'one-time']) ? $s['billingCycle'] : 'monthly';
                $service->price = (float)($s['price'] ?? 0);
                $service->category = $s['category'] ?? 'Subscription';
                $service->status = ($s['status'] ?? 'active') === 'active' ? 'active' : 'inactive';
                $service->description = $s['description'] ?? null;

                if (!$isDryRun) {
                    $service->save();
                }

                $resolvedId = $service->id ?? (4500 + count($this->serviceIdMap));
                if (!empty($s['id'])) {
                    $this->serviceIdMap[$s['id']] = $resolvedId;
                }

                if ($isNew) {
                    $this->stats['Services']['created']++;
                } else {
                    $this->stats['Services']['updated']++;
                }
            } catch (\Throwable $e) {
                $this->stats['Services']['errors']++;
                $this->errorReport[] = "Service ({$s['name']}): " . $e->getMessage();
            }
        }
    }

    /**
     * 9. Deals Migration
     */
    protected function migrateDeals(array $deals, int $tenantId, bool $isDryRun): void
    {
        $this->initStats('Deals', count($deals));

        foreach ($deals as $d) {
            try {
                $name = trim($d['name'] ?? '');

                $deal = Deal::where('tenant_id', $tenantId)->where('name', $name)->first();
                $isNew = false;
                if (!$deal) {
                    $deal = new Deal();
                    $deal->tenant_id = $tenantId;
                    $deal->name = $name;
                    $isNew = true;
                }

                $deal->value = (float)($d['value'] ?? 0);
                $deal->stage = in_array(strtolower($d['stage'] ?? ''), ['qualification', 'proposal', 'negotiation', 'won', 'lost'])
                    ? strtolower($d['stage'])
                    : 'qualification';
                $deal->probability = (int)($d['probability'] ?? 20);
                $deal->close_date = !empty($d['closeDate']) ? Carbon::parse($d['closeDate'])->toDateString() : null;

                if (!empty($d['companyId']) && isset($this->companyIdMap[$d['companyId']])) {
                    $deal->company_id = $this->companyIdMap[$d['companyId']];
                }

                if (!empty($d['assignedTo'])) {
                    $deal->assigned_user_id = $this->resolveUserId($d['assignedTo']);
                }

                if (!empty($d['createdAt'])) {
                    $deal->created_at = Carbon::parse($d['createdAt']);
                }

                if (!$isDryRun) {
                    $deal->save();
                }

                $resolvedId = $deal->id ?? (4000 + count($this->dealIdMap));
                if (!empty($d['id'])) {
                    $this->dealIdMap[$d['id']] = $resolvedId;
                }

                if ($isNew) {
                    $this->stats['Deals']['created']++;
                } else {
                    $this->stats['Deals']['updated']++;
                }
            } catch (\Throwable $e) {
                $this->stats['Deals']['errors']++;
                $this->errorReport[] = "Deal ({$d['name']}): " . $e->getMessage();
            }
        }
    }

    /**
     * 10. Quotations Migration
     */
    protected function migrateQuotations(array $quotations, int $tenantId, bool $isDryRun): void
    {
        $this->initStats('Quotations', count($quotations));

        foreach ($quotations as $q) {
            try {
                $num = trim($q['quoteNumber'] ?? '');
                if (!$num) {
                    $this->stats['Quotations']['skipped']++;
                    continue;
                }

                $quotation = Quotation::where('quote_number', $num)->first();
                $isNew = false;
                if (!$quotation) {
                    $quotation = new Quotation();
                    $quotation->tenant_id = $tenantId;
                    $quotation->quote_number = $num;
                    $isNew = true;
                }

                if (!empty($q['dealId']) && isset($this->dealIdMap[$q['dealId']])) {
                    $quotation->deal_id = $this->dealIdMap[$q['dealId']];
                }
                if (!empty($q['customerId']) && isset($this->customerIdMap[$q['customerId']])) {
                    $quotation->customer_id = $this->customerIdMap[$q['customerId']];
                }

                $quotation->subtotal = (float)($q['subtotal'] ?? 0);
                $quotation->tax_amount = (float)($q['tax'] ?? 0);
                $quotation->tax_rate = $quotation->subtotal > 0 ? round(($quotation->tax_amount / $quotation->subtotal) * 100, 2) : 15.00;
                $quotation->total_amount = (float)($q['total'] ?? 0);
                $quotation->status = strtolower($q['status'] ?? 'draft');
                $quotation->valid_until = !empty($q['validUntil']) ? Carbon::parse($q['validUntil'])->toDateString() : null;

                if (!empty($q['createdAt'])) {
                    $quotation->created_at = Carbon::parse($q['createdAt']);
                }

                if (!$isDryRun) {
                    $quotation->save();

                    // Migrate quotation line items
                    if (!empty($q['items']) && is_array($q['items'])) {
                        QuotationItem::where('quotation_id', $quotation->id)->delete();
                        foreach ($q['items'] as $item) {
                            $qi = new QuotationItem();
                            $qi->quotation_id = $quotation->id;
                            $qi->item_name = $item['name'] ?? 'Item';
                            $qi->quantity = (float)($item['quantity'] ?? 1);
                            $qi->unit_price = (float)($item['price'] ?? 0);
                            $qi->total_price = (float)($item['total'] ?? ($qi->quantity * $qi->unit_price));
                            if (!empty($item['id']) && isset($this->productIdMap[$item['id']])) {
                                $qi->product_id = $this->productIdMap[$item['id']];
                            }
                            $qi->save();
                        }
                    }
                }

                $resolvedId = $quotation->id ?? (3500 + count($this->quotationIdMap));
                if (!empty($q['id'])) {
                    $this->quotationIdMap[$q['id']] = $resolvedId;
                }

                if ($isNew) {
                    $this->stats['Quotations']['created']++;
                } else {
                    $this->stats['Quotations']['updated']++;
                }
            } catch (\Throwable $e) {
                $this->stats['Quotations']['errors']++;
                $this->errorReport[] = "Quotation ({$q['quoteNumber']}): " . $e->getMessage();
            }
        }
    }

    /**
     * 11. Invoices Migration
     */
    protected function migrateInvoices(array $invoices, int $tenantId, bool $isDryRun): void
    {
        $this->initStats('Invoices', count($invoices));

        foreach ($invoices as $inv) {
            try {
                $num = trim($inv['invoiceNumber'] ?? '');
                if (!$num) {
                    $this->stats['Invoices']['skipped']++;
                    continue;
                }

                $invoice = Invoice::where('invoice_number', $num)->first();
                $isNew = false;
                if (!$invoice) {
                    $invoice = new Invoice();
                    $invoice->tenant_id = $tenantId;
                    $invoice->invoice_number = $num;
                    $isNew = true;
                }

                if (!empty($inv['customerId']) && isset($this->customerIdMap[$inv['customerId']])) {
                    $invoice->customer_id = $this->customerIdMap[$inv['customerId']];
                }

                $invoice->subtotal = (float)($inv['subtotal'] ?? 0);
                $invoice->tax_amount = (float)($inv['tax'] ?? 0);
                $invoice->tax_rate = $invoice->subtotal > 0 ? round(($invoice->tax_amount / $invoice->subtotal) * 100, 2) : 15.00;
                $invoice->total_amount = (float)($inv['total'] ?? 0);
                $invoice->status = strtolower($inv['status'] ?? 'draft');
                $invoice->due_date = !empty($inv['dueDate']) ? Carbon::parse($inv['dueDate'])->toDateString() : null;

                if ($invoice->status === 'paid') {
                    $invoice->paid_amount = $invoice->total_amount;
                    $invoice->outstanding_amount = 0;
                } else {
                    $invoice->outstanding_amount = $invoice->total_amount;
                }

                if (!empty($inv['createdAt'])) {
                    $invoice->created_at = Carbon::parse($inv['createdAt']);
                }

                if (!$isDryRun) {
                    $invoice->save();

                    // Migrate invoice line items
                    if (!empty($inv['items']) && is_array($inv['items'])) {
                        InvoiceItem::where('invoice_id', $invoice->id)->delete();
                        foreach ($inv['items'] as $item) {
                            $ii = new InvoiceItem();
                            $ii->invoice_id = $invoice->id;
                            $ii->item_name = $item['name'] ?? 'Item';
                            $ii->quantity = (float)($item['quantity'] ?? 1);
                            $ii->unit_price = (float)($item['price'] ?? 0);
                            $ii->total_price = (float)($item['total'] ?? ($ii->quantity * $ii->unit_price));
                            if (!empty($item['id']) && isset($this->productIdMap[$item['id']])) {
                                $ii->product_id = $this->productIdMap[$item['id']];
                            }
                            $ii->save();
                        }
                    }
                }

                $resolvedId = $invoice->id ?? (3000 + count($this->invoiceIdMap));
                if (!empty($inv['id'])) {
                    $this->invoiceIdMap[$inv['id']] = $resolvedId;
                }

                if ($isNew) {
                    $this->stats['Invoices']['created']++;
                } else {
                    $this->stats['Invoices']['updated']++;
                }
            } catch (\Throwable $e) {
                $this->stats['Invoices']['errors']++;
                $this->errorReport[] = "Invoice ({$inv['invoiceNumber']}): " . $e->getMessage();
            }
        }
    }

    /**
     * 12. Payments Migration
     */
    protected function migratePayments(array $payments, int $tenantId, bool $isDryRun): void
    {
        $this->initStats('Payments', count($payments));

        foreach ($payments as $pay) {
            try {
                $invId = null;
                if (!empty($pay['invoiceId']) && isset($this->invoiceIdMap[$pay['invoiceId']])) {
                    $invId = $this->invoiceIdMap[$pay['invoiceId']];
                } elseif (!empty($pay['invoiceNumber'])) {
                    $invId = Invoice::where('invoice_number', $pay['invoiceNumber'])->value('id');
                }

                $amount = (float)($pay['amount'] ?? 0);
                $payment = Payment::where('tenant_id', $tenantId)
                    ->where('invoice_id', $invId)
                    ->where('amount', $amount)
                    ->first();

                $isNew = false;
                if (!$payment) {
                    $payment = new Payment();
                    $payment->tenant_id = $tenantId;
                    $payment->invoice_id = $invId;
                    $isNew = true;
                }

                $payment->amount = $amount;
                $payment->payment_method = $pay['method'] ?? 'Bank Transfer';
                $payment->status = in_array(strtolower($pay['status'] ?? ''), ['success', 'successful']) ? 'success' : 'pending';
                $payment->reference_number = $pay['referenceNumber'] ?? ('TXN-' . ($pay['id'] ?? uniqid()));
                $payment->paid_at = !empty($pay['paymentDate']) ? Carbon::parse($pay['paymentDate']) : now();

                if (!empty($pay['customerName']) && isset($this->customerNameMap[$pay['customerName']])) {
                    $payment->customer_id = $this->customerNameMap[$pay['customerName']];
                }

                if (!$isDryRun) {
                    $payment->save();
                }

                if ($isNew) {
                    $this->stats['Payments']['created']++;
                } else {
                    $this->stats['Payments']['updated']++;
                }
            } catch (\Throwable $e) {
                $this->stats['Payments']['errors']++;
                $this->errorReport[] = "Payment: " . $e->getMessage();
            }
        }
    }

    /**
     * 13. Projects Migration
     */
    protected function migrateProjects(array $projects, int $tenantId, bool $isDryRun): void
    {
        $this->initStats('Projects', count($projects));

        foreach ($projects as $proj) {
            try {
                $name = trim($proj['name'] ?? '');

                $project = Project::where('tenant_id', $tenantId)->where('name', $name)->first();
                $isNew = false;
                if (!$project) {
                    $project = new Project();
                    $project->tenant_id = $tenantId;
                    $project->name = $name;
                    $isNew = true;
                }

                if (!empty($proj['customerId']) && isset($this->customerIdMap[$proj['customerId']])) {
                    $project->customer_id = $this->customerIdMap[$proj['customerId']];
                }

                $project->status = in_array($proj['status'] ?? '', ['not_started', 'in_progress', 'completed', 'on_hold'])
                    ? $proj['status']
                    : 'in_progress';
                $project->start_date = !empty($proj['startDate']) ? Carbon::parse($proj['startDate'])->toDateString() : null;
                $project->end_date = !empty($proj['endDate']) ? Carbon::parse($proj['endDate'])->toDateString() : null;
                $project->progress = (int)($proj['progress'] ?? 0);

                if (!empty($proj['createdAt'])) {
                    $project->created_at = Carbon::parse($proj['createdAt']);
                }

                if (!$isDryRun) {
                    $project->save();

                    // Project members sync
                    if (!empty($proj['teamMembers']) && is_array($proj['teamMembers'])) {
                        $memberUserIds = [];
                        foreach ($proj['teamMembers'] as $memberName) {
                            $uid = $this->resolveUserId($memberName);
                            if ($uid) $memberUserIds[] = $uid;
                        }
                        if (!empty($memberUserIds)) {
                            $project->teamMembers()->sync($memberUserIds);
                        }
                    }
                }

                $resolvedId = $project->id ?? (2500 + count($this->projectIdMap));
                if (!empty($proj['id'])) {
                    $this->projectIdMap[$proj['id']] = $resolvedId;
                }

                if ($isNew) {
                    $this->stats['Projects']['created']++;
                } else {
                    $this->stats['Projects']['updated']++;
                }
            } catch (\Throwable $e) {
                $this->stats['Projects']['errors']++;
                $this->errorReport[] = "Project ({$proj['name']}): " . $e->getMessage();
            }
        }
    }

    /**
     * 14. Tasks Migration
     */
    protected function migrateTasks(array $tasks, int $tenantId, bool $isDryRun): void
    {
        $this->initStats('Tasks', count($tasks));

        foreach ($tasks as $t) {
            try {
                $title = trim($t['title'] ?? '');

                $task = Task::where('tenant_id', $tenantId)->where('title', $title)->first();
                $isNew = false;
                if (!$task) {
                    $task = new Task();
                    $task->tenant_id = $tenantId;
                    $task->title = $title;
                    $isNew = true;
                }

                $task->description = $t['description'] ?? null;
                $task->priority = in_array(strtolower($t['priority'] ?? ''), ['low', 'medium', 'high']) ? strtolower($t['priority']) : 'medium';
                $task->status = in_array(strtolower($t['status'] ?? ''), ['todo', 'in_progress', 'done']) ? strtolower($t['status']) : 'todo';
                $task->due_date = !empty($t['dueDate']) ? Carbon::parse($t['dueDate'])->toDateString() : null;

                if (!empty($t['projectId']) && isset($this->projectIdMap[$t['projectId']])) {
                    $task->project_id = $this->projectIdMap[$t['projectId']];
                }

                if (!empty($t['assignedTo'])) {
                    $task->assigned_user_id = $this->resolveUserId($t['assignedTo']);
                }

                if (!empty($t['createdAt'])) {
                    $task->created_at = Carbon::parse($t['createdAt']);
                }

                if (!$isDryRun) {
                    $task->save();
                }

                if ($isNew) {
                    $this->stats['Tasks']['created']++;
                } else {
                    $this->stats['Tasks']['updated']++;
                }
            } catch (\Throwable $e) {
                $this->stats['Tasks']['errors']++;
                $this->errorReport[] = "Task ({$t['title']}): " . $e->getMessage();
            }
        }
    }

    /**
     * 15. Meetings Migration
     */
    protected function migrateMeetings(array $meetings, int $tenantId, bool $isDryRun): void
    {
        $this->initStats('Meetings', count($meetings));

        foreach ($meetings as $m) {
            try {
                $title = trim($m['title'] ?? '');

                $meeting = Meeting::where('tenant_id', $tenantId)->where('title', $title)->first();
                $isNew = false;
                if (!$meeting) {
                    $meeting = new Meeting();
                    $meeting->tenant_id = $tenantId;
                    $meeting->title = $title;
                    $isNew = true;
                }

                $meeting->description = $m['description'] ?? null;
                $meeting->meeting_date = !empty($m['date']) ? Carbon::parse($m['date'])->toDateString() : now()->toDateString();
                $meeting->start_time = $m['time'] ?? '10:00';
                $meeting->meet_link = $m['meetLink'] ?? null;
                $meeting->status = 'scheduled';

                if (!empty($m['createdAt'])) {
                    $meeting->created_at = Carbon::parse($m['createdAt']);
                }

                if (!$isDryRun) {
                    $meeting->save();

                    // Meeting attendees
                    if (!empty($m['attendees']) && is_array($m['attendees'])) {
                        MeetingAttendee::where('meeting_id', $meeting->id)->delete();
                        foreach ($m['attendees'] as $att) {
                            MeetingAttendee::create([
                                'meeting_id' => $meeting->id,
                                'attendee_name' => explode('@', $att)[0],
                                'attendee_email' => filter_var($att, FILTER_VALIDATE_EMAIL) ? $att : null,
                            ]);
                        }
                    }
                }

                if ($isNew) {
                    $this->stats['Meetings']['created']++;
                } else {
                    $this->stats['Meetings']['updated']++;
                }
            } catch (\Throwable $e) {
                $this->stats['Meetings']['errors']++;
                $this->errorReport[] = "Meeting ({$m['title']}): " . $e->getMessage();
            }
        }
    }

    /**
     * 16. Employees Migration
     */
    protected function migrateEmployees(array $employees, int $tenantId, bool $isDryRun): void
    {
        $this->initStats('Employees', count($employees));

        foreach ($employees as $emp) {
            try {
                $email = trim($emp['email'] ?? '');
                $name = trim($emp['name'] ?? '');

                $employee = Employee::where('tenant_id', $tenantId)
                    ->where(function ($q) use ($email, $name) {
                        if ($email) $q->where('email', $email);
                        else $q->where('name', $name);
                    })->first();

                $isNew = false;
                if (!$employee) {
                    $employee = new Employee();
                    $employee->tenant_id = $tenantId;
                    $employee->name = $name;
                    $isNew = true;
                }

                $employee->email = $email ?: null;
                $employee->department = $emp['department'] ?? 'Operations';
                $employee->designation = $emp['designation'] ?? 'Executive';
                $employee->date_of_joining = !empty($emp['dateOfJoining']) ? Carbon::parse($emp['dateOfJoining'])->toDateString() : null;
                $employee->salary = (float)($emp['salary'] ?? 0);
                $employee->status = 'active';

                // Map department to department_id
                if (!empty($employee->department)) {
                    $dept = Department::firstOrCreate(
                        ['tenant_id' => $tenantId, 'name' => $employee->department],
                        ['code' => strtoupper(substr($employee->department, 0, 4)), 'status' => 'active']
                    );
                    $employee->department_id = $dept->id;
                }

                if (!empty($emp['userId']) && isset($this->userIdMap[$emp['userId']])) {
                    $employee->user_id = $this->userIdMap[$emp['userId']];
                }

                if (!$isDryRun) {
                    $employee->save();
                }

                $resolvedId = $employee->id ?? (2000 + count($this->employeeIdMap));
                if (!empty($emp['id'])) {
                    $this->employeeIdMap[$emp['id']] = $resolvedId;
                }

                if ($isNew) {
                    $this->stats['Employees']['created']++;
                } else {
                    $this->stats['Employees']['updated']++;
                }
            } catch (\Throwable $e) {
                $this->stats['Employees']['errors']++;
                $this->errorReport[] = "Employee ({$emp['name']}): " . $e->getMessage();
            }
        }
    }

    /**
     * 17. Attendance Migration
     */
    protected function migrateAttendance(array $attendance, int $tenantId, bool $isDryRun): void
    {
        $this->initStats('Attendance', count($attendance));

        foreach ($attendance as $att) {
            try {
                $empId = null;
                if (!empty($att['employeeId']) && isset($this->employeeIdMap[$att['employeeId']])) {
                    $empId = $this->employeeIdMap[$att['employeeId']];
                }

                $date = !empty($att['date']) ? Carbon::parse($att['date'])->toDateString() : now()->toDateString();
                
                $attendanceRecord = Attendance::where('tenant_id', $tenantId)
                    ->where('employee_id', $empId)
                    ->where('date', $date)
                    ->first();

                $isNew = false;
                if (!$attendanceRecord) {
                    $attendanceRecord = new Attendance();
                    $attendanceRecord->tenant_id = $tenantId;
                    $attendanceRecord->employee_id = $empId;
                    $attendanceRecord->date = $date;
                    $isNew = true;
                }

                $attendanceRecord->check_in = $att['checkIn'] ?? null;
                $attendanceRecord->check_out = $att['checkOut'] ?? null;
                $attendanceRecord->status = in_array(strtolower($att['status'] ?? ''), ['present', 'absent', 'late', 'leave'])
                    ? strtolower($att['status'])
                    : 'present';

                if (!$isDryRun && $empId) {
                    $attendanceRecord->save();
                }

                if ($isNew) {
                    $this->stats['Attendance']['created']++;
                } else {
                    $this->stats['Attendance']['updated']++;
                }
            } catch (\Throwable $e) {
                $this->stats['Attendance']['errors']++;
                $this->errorReport[] = "Attendance: " . $e->getMessage();
            }
        }
    }

    /**
     * 18. Leaves Migration
     */
    protected function migrateLeaves(array $leaves, int $tenantId, bool $isDryRun): void
    {
        $this->initStats('Leaves', count($leaves));

        foreach ($leaves as $l) {
            try {
                $empId = null;
                if (!empty($l['employeeId']) && isset($this->employeeIdMap[$l['employeeId']])) {
                    $empId = $this->employeeIdMap[$l['employeeId']];
                }

                $startDate = !empty($l['startDate']) ? Carbon::parse($l['startDate'])->toDateString() : now()->toDateString();
                $endDate = !empty($l['endDate']) ? Carbon::parse($l['endDate'])->toDateString() : $startDate;

                $leave = Leave::where('tenant_id', $tenantId)
                    ->where('employee_id', $empId)
                    ->where('start_date', $startDate)
                    ->first();

                $isNew = false;
                if (!$leave) {
                    $leave = new Leave();
                    $leave->tenant_id = $tenantId;
                    $leave->employee_id = $empId;
                    $leave->start_date = $startDate;
                    $isNew = true;
                }

                $leave->end_date = $endDate;
                $leave->type = in_array($l['type'] ?? '', ['Casual', 'Sick', 'Unpaid', 'Maternity', 'Paternity']) ? $l['type'] : 'Casual';
                $leave->status = in_array(strtolower($l['status'] ?? ''), ['pending', 'approved', 'rejected']) ? strtolower($l['status']) : 'pending';
                $leave->reason = $l['reason'] ?? null;

                if (!empty($l['createdAt'])) {
                    $leave->created_at = Carbon::parse($l['createdAt']);
                }

                if (!$isDryRun && $empId) {
                    $leave->save();
                }

                if ($isNew) {
                    $this->stats['Leaves']['created']++;
                } else {
                    $this->stats['Leaves']['updated']++;
                }
            } catch (\Throwable $e) {
                $this->stats['Leaves']['errors']++;
                $this->errorReport[] = "Leave: " . $e->getMessage();
            }
        }
    }

    /**
     * 19. Support Tickets Migration
     */
    protected function migrateTickets(array $tickets, int $tenantId, bool $isDryRun): void
    {
        $this->initStats('Support Tickets', count($tickets));

        foreach ($tickets as $tkt) {
            try {
                $subject = trim($tkt['subject'] ?? '');

                $ticket = SupportTicket::where('tenant_id', $tenantId)->where('subject', $subject)->first();
                $isNew = false;
                if (!$ticket) {
                    $ticket = new SupportTicket();
                    $ticket->tenant_id = $tenantId;
                    $ticket->subject = $subject;
                    $ticket->ticket_number = 'TKT-' . strtoupper($tkt['id'] ?? uniqid());
                    $isNew = true;
                }

                $ticket->description = $tkt['description'] ?? null;
                $ticket->priority = in_array(strtolower($tkt['priority'] ?? ''), ['low', 'medium', 'high']) ? strtolower($tkt['priority']) : 'medium';
                $ticket->status = in_array(strtolower($tkt['status'] ?? ''), ['open', 'in_progress', 'resolved', 'closed']) ? strtolower($tkt['status']) : 'open';

                if (!empty($tkt['customerId']) && isset($this->customerIdMap[$tkt['customerId']])) {
                    $ticket->customer_id = $this->customerIdMap[$tkt['customerId']];
                }

                if (!empty($tkt['assignedTo'])) {
                    $ticket->assigned_user_id = $this->resolveUserId($tkt['assignedTo']);
                }

                if (!empty($tkt['createdAt'])) {
                    $ticket->created_at = Carbon::parse($tkt['createdAt']);
                }

                if (!$isDryRun) {
                    $ticket->save();
                }

                if ($isNew) {
                    $this->stats['Support Tickets']['created']++;
                } else {
                    $this->stats['Support Tickets']['updated']++;
                }
            } catch (\Throwable $e) {
                $this->stats['Support Tickets']['errors']++;
                $this->errorReport[] = "Ticket ({$tkt['subject']}): " . $e->getMessage();
            }
        }
    }

    /**
     * 20. Activity Logs Migration
     */
    protected function migrateActivityLogs(array $activityLogs, int $tenantId, bool $isDryRun): void
    {
        $this->initStats('Activity Logs', count($activityLogs));

        foreach ($activityLogs as $act) {
            try {
                $action = $act['action'] ?? 'ACTIVITY';
                $createdAt = !empty($act['createdAt']) ? Carbon::parse($act['createdAt']) : now();

                $log = ActivityLog::where('tenant_id', $tenantId)
                    ->where('action', $action)
                    ->where('created_at', $createdAt)
                    ->first();

                $isNew = false;
                if (!$log) {
                    $log = new ActivityLog();
                    $log->tenant_id = $tenantId;
                    $log->action = $action;
                    $log->created_at = $createdAt;
                    $isNew = true;
                }

                $log->module = $act['module'] ?? 'System';
                $log->details = $act['details'] ?? null;
                $log->ip_address = $act['ipAddress'] ?? null;
                $log->user_name = $act['userName'] ?? 'System';

                if (!empty($act['userId']) && isset($this->userIdMap[$act['userId']])) {
                    $log->user_id = $this->userIdMap[$act['userId']];
                }

                if (!$isDryRun) {
                    $log->save();
                }

                if ($isNew) {
                    $this->stats['Activity Logs']['created']++;
                } else {
                    $this->stats['Activity Logs']['updated']++;
                }
            } catch (\Throwable $e) {
                $this->stats['Activity Logs']['errors']++;
                $this->errorReport[] = "ActivityLog: " . $e->getMessage();
            }
        }
    }

    /**
     * 21. Notifications Migration
     */
    protected function migrateNotifications(array $notifications, int $tenantId, bool $isDryRun): void
    {
        $this->initStats('Notifications', count($notifications));

        foreach ($notifications as $notif) {
            try {
                $title = $notif['title'] ?? 'Notification';
                $createdAt = !empty($notif['createdAt']) ? Carbon::parse($notif['createdAt']) : now();

                $notification = Notification::where('tenant_id', $tenantId)
                    ->where('title', $title)
                    ->where('created_at', $createdAt)
                    ->first();

                $isNew = false;
                if (!$notification) {
                    $notification = new Notification();
                    $notification->tenant_id = $tenantId;
                    $notification->title = $title;
                    $notification->created_at = $createdAt;
                    $isNew = true;
                }

                $notification->message = $notif['message'] ?? '';
                $notification->is_read = !empty($notif['read']);
                $notification->type = 'system';
                $notification->channel = 'in_app';

                if (!empty($notif['userId']) && isset($this->userIdMap[$notif['userId']])) {
                    $notification->user_id = $this->userIdMap[$notif['userId']];
                }

                if (!$isDryRun) {
                    $notification->save();
                }

                if ($isNew) {
                    $this->stats['Notifications']['created']++;
                } else {
                    $this->stats['Notifications']['updated']++;
                }
            } catch (\Throwable $e) {
                $this->stats['Notifications']['errors']++;
                $this->errorReport[] = "Notification: " . $e->getMessage();
            }
        }
    }

    /**
     * Resolve User ID by name or email
     */
    protected function resolveUserId(string $identifier): ?int
    {
        if (isset($this->userNameMap[$identifier])) {
            return $this->userNameMap[$identifier];
        }
        if (isset($this->userEmailMap[$identifier])) {
            return $this->userEmailMap[$identifier];
        }
        $match = User::where('name', $identifier)->orWhere('email', $identifier)->value('id');
        if ($match) {
            $this->userNameMap[$identifier] = $match;
            return $match;
        }
        return null;
    }

    /**
     * Parse human-readable revenue strings (e.g. "$300M", "$1.2B") to numeric decimal
     */
    protected function parseRevenue($value): float
    {
        if (is_numeric($value)) {
            return (float)$value;
        }
        if (!is_string($value)) {
            return 0.0;
        }

        $clean = strtoupper(trim(str_replace(['$', ',', '+', ' '], '', $value)));
        if (str_ends_with($clean, 'B')) {
            return (float)rtrim($clean, 'B') * 1000000000;
        }
        if (str_ends_with($clean, 'M')) {
            return (float)rtrim($clean, 'M') * 1000000;
        }
        if (str_ends_with($clean, 'K')) {
            return (float)rtrim($clean, 'K') * 1000;
        }
        return (float)$clean;
    }

    /**
     * Normalize role to match RBAC policies
     */
    protected function normalizeRole(string $role): string
    {
        return match (strtolower(trim($role))) {
            'super admin' => 'Super Admin',
            'admin' => 'Admin',
            'manager' => 'Manager',
            'sales executive' => 'Sales Executive',
            'support executive' => 'Support Executive',
            'hr' => 'HR',
            'accountant' => 'Accountant',
            default => 'Employee',
        };
    }

    /**
     * Print summary report table
     */
    protected function printSummaryTable(): void
    {
        $headers = ['Entity', 'Source Records', 'Created', 'Updated/Reconciled', 'Skipped', 'Errors', 'Status'];
        $rows = [];

        foreach ($this->stats as $s) {
            $status = $s['errors'] > 0 ? '<fg=yellow>Warnings</>' : '<fg=green>Passed</>';
            $rows[] = [
                $s['entity'],
                $s['source'],
                $s['created'],
                $s['updated'],
                $s['skipped'],
                $s['errors'],
                $status,
            ];
        }

        $this->table($headers, $rows);
    }
}
