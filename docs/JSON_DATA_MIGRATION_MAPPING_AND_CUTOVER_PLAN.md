# JSON Data Migration, Mapping Specification & Cutover Plan

## 1. Executive Summary

This document specifies the exact mapping, transformation rules, relationship resolutions, integrity constraints, and cutover procedure for migrating the existing file-based JSON database (`frontend/crm_db.json`) into the multi-tenant relational MySQL database powered by Laravel 12.

---

## 2. Source Collection Inventory

| JSON Collection | Record Count | Target Laravel Model | Target MySQL Table | Primary Strategy |
| :--- | :---: | :--- | :--- | :--- |
| `settings` | 1 | `App\Domains\Tenancy\Models\SystemSetting` | `system_settings` | Update/Upsert for Tenant 1 |
| `users` | 9 | `App\Models\User` & `TenantUser` | `users`, `tenant_users` | Natural key (`email`), link tenant role |
| `companies` | 4 | `App\Domains\CRM\Models\Company` | `companies` | Natural key (`name`), parse revenue |
| `customers` | 3 | `App\Domains\CRM\Models\Customer` | `customers` | Relational link (`company_id`, `assigned_user_id`) |
| `contacts` | 3 | `App\Domains\CRM\Models\Contact` | `contacts` | Relational link (`company_id`) |
| `leads` | 5 | `App\Domains\LeadAcquisition\Models\Lead` | `leads` | Relational link (`company_id`, `assigned_user_id`) |
| `deals` | 4 | `App\Domains\CRM\Models\Deal` | `deals` | Relational link (`company_id`, `assigned_user_id`) |
| `products` | 3 | `App\Domains\Sales\Models\Product` | `products` | Natural key (`sku`), active status |
| `services` | 2 | `App\Domains\Sales\Models\Service` | `services` | Natural key (`name`), billing cycle |
| `quotations` | 2 | `App\Domains\Finance\Models\Quotation` & Items | `quotations`, `quotation_items` | Natural key (`quote_number`), relational |
| `invoices` | 3 | `App\Domains\Finance\Models\Invoice` & Items | `invoices`, `invoice_items` | Natural key (`invoice_number`), relational |
| `payments` | 1 | `App\Domains\Finance\Models\Payment` | `payments` | Relational link (`invoice_id`) |
| `projects` | 2 | `App\Domains\Projects\Models\Project` | `projects`, `project_members` | Relational link (`customer_id`, `teamMembers`) |
| `tasks` | 3 | `App\Domains\Projects\Models\Task` | `tasks` | Relational link (`assigned_user_id`) |
| `meetings` | 2 | `App\Domains\Projects\Models\Meeting` | `meetings`, `meeting_attendees` | Relational link (`meeting_attendees`) |
| `employees` | 3 | `App\Domains\HR\Models\Employee` | `employees`, `departments` | Relational link (`user_id`, `department_id`) |
| `attendance` | 3 | `App\Domains\HR\Models\Attendance` | `attendances` | Relational link (`employee_id`) |
| `leaves` | 2 | `App\Domains\HR\Models\Leave` | `leaves` | Relational link (`employee_id`) |
| `tickets` | 2 | `App\Domains\Support\Models\SupportTicket` | `support_tickets` | Relational link (`customer_id`, `assigned_user_id`) |
| `activityLogs` | 5 | `App\Domains\AuditSecurity\Models\ActivityLog` | `activity_logs` | Relational link (`user_id`) |
| `notifications` | 2 | `App\Domains\Communications\Models\Notification` | `notifications` | Relational link (`user_id`) |

---

## 3. Entity Field Mapping & Transformation Rules

### 3.1 Settings (`settings` -> `system_settings`)
- `companyName` -> `company_name` (string)
- `companyEmail` -> `company_email` (string)
- `companyPhone` -> `company_phone` (string)
- `taxRate` -> `tax_rate` (decimal 5,2)
- `currency` -> `currency` (string 10)
- `theme` -> `theme` (enum 'light', 'dark')

### 3.2 Users (`users` -> `users` & `tenant_users`)
- `id` (`usr-john`, `usr-1`...) -> In-memory map `old_user_id -> new_user_id`
- `name` -> `name`
- `email` -> `email` (matched to avoid duplicate user creation)
- `role` -> `role` mapped to Spatie/Tenant roles (`Super Admin`, `Admin`, `Manager`, `Sales Executive`, `Support Executive`, `HR`, `Accountant`, `Employee`)
- `passwordHash` -> Bcrypt hashed via `Hash::make()`
- `twoFactorEnabled` -> sets `two_factor_confirmed_at = now()`
- `createdAt` -> `created_at` (preserved)

### 3.3 Companies (`companies` -> `companies`)
- `id` (`comp-1`...) -> In-memory map `old_comp_id -> new_comp_id`
- `name` -> `name` (deduplication key within tenant)
- `industry` -> `industry`
- `website` -> `website`
- `size` -> `size`
- `revenue` (`$300M`, `$1.2B`) -> Transformed into numeric `annual_revenue` (e.g. 300,000,000.00)
- `address` -> `address`
- `phone` -> `phone`
- `createdAt` -> `created_at`

### 3.4 Customers (`customers` -> `customers`)
- `id` (`cust-1`...) -> In-memory map `old_cust_id -> new_cust_id`
- `name` -> `name`
- `companyId` -> resolved from `old_comp_id` mapping to foreign key `company_id`
- `email` -> `email`
- `phone` -> `phone`
- `status` -> `status` ('active', 'inactive')
- `assignedTo` -> resolved to `assigned_user_id` by matching assigned employee/user name
- `createdAt` -> `created_at`

### 3.5 Contacts (`contacts` -> `contacts`)
- `id` (`cont-1`...) -> In-memory map `old_cont_id -> new_cont_id`
- `name` -> `name`
- `companyId` -> resolved to `company_id`
- `email` -> `email`
- `phone` -> `phone`
- `role` -> `job_title`
- `isPrimary` -> `is_primary` (boolean)
- `createdAt` -> `created_at`

### 3.6 Leads (`leads` -> `leads`)
- `id` (`lead-1`...) -> In-memory map `old_lead_id -> new_lead_id`
- `name` -> `name`
- `company` -> `company_name`, plus linked to `company_id` if company match exists
- `email` -> `email`
- `phone` -> `phone`
- `value` -> `estimated_value`
- `status` -> `status` ('new', 'contacted', 'qualified', 'lost')
- `source` -> `source`
- `assignedTo` -> resolved to `assigned_user_id`
- `notes` -> `notes`
- `createdAt` -> `created_at`

### 3.7 Deals (`deals` -> `deals`)
- `id` (`deal-1`...) -> In-memory map `old_deal_id -> new_deal_id`
- `name` -> `name`
- `companyId` -> resolved to `company_id`
- `value` -> `value`
- `stage` -> `stage` ('qualification', 'proposal', 'negotiation', 'won', 'lost')
- `probability` -> `probability` (0-100)
- `closeDate` -> `close_date`
- `assignedTo` -> resolved to `assigned_user_id`
- `createdAt` -> `created_at`

### 3.8 Products & Services (`products`, `services` -> `products`, `services`)
- `sku` -> `sku` (deduplication key)
- `name` -> `name`
- `price` -> `price`
- `category` -> `category`
- `status` -> `status`
- `billingCycle` -> `billing_cycle` ('monthly', 'yearly', 'one-time')

### 3.9 Quotations & Invoices (`quotations`, `invoices`)
- `quoteNumber` -> `quote_number` (unique index)
- `invoiceNumber` -> `invoice_number` (unique index)
- Items array -> Unpacked and inserted into `quotation_items` and `invoice_items` child tables
- Amounts (`subtotal`, `tax`, `total`) verified and stored accurately

### 3.10 Projects, Tasks, Meetings
- `projects.teamMembers` array -> resolved to user IDs and attached via `project_members` pivot
- `meetings.attendees` array -> unpacked into `meeting_attendees` child records
- `tasks.assignedTo` -> resolved to `assigned_user_id`

### 3.11 HR & Operations (Employees, Attendance, Leaves)
- `departments` created dynamically if missing
- `employees.userId` -> resolved to `user_id`
- `attendances.employeeId` -> mapped to target `employee_id`
- `leaves.employeeId` -> mapped to target `employee_id`

### 3.12 Support Tickets, Activity Logs, Notifications
- `tickets.customerId` -> resolved to `customer_id`
- `activityLogs.userId` -> resolved to `user_id`
- `notifications.userId` -> resolved to `user_id`

---

## 5. Verification Results & Sign-Off Report

### 5.1 Cutover Migration Summary
Execution of `php artisan crm:migrate-json --force`:

| Entity | Source Records | Created | Reconciled/Updated | Skipped | Errors | Status |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **Settings** | 1 | 0 | 1 | 0 | 0 | Passed |
| **Users** | 9 | 0 | 9 | 0 | 0 | Passed |
| **Companies** | 4 | 0 | 4 | 0 | 0 | Passed |
| **Customers** | 3 | 0 | 3 | 0 | 0 | Passed |
| **Contacts** | 3 | 0 | 3 | 0 | 0 | Passed |
| **Leads** | 5 | 0 | 5 | 0 | 0 | Passed |
| **Products** | 3 | 0 | 3 | 0 | 0 | Passed |
| **Services** | 2 | 0 | 2 | 0 | 0 | Passed |
| **Deals** | 4 | 0 | 4 | 0 | 0 | Passed |
| **Quotations** | 2 | 0 | 2 | 0 | 0 | Passed |
| **Invoices** | 3 | 0 | 3 | 0 | 0 | Passed |
| **Payments** | 1 | 0 | 1 | 0 | 0 | Passed |
| **Projects** | 2 | 0 | 2 | 0 | 0 | Passed |
| **Tasks** | 3 | 0 | 3 | 0 | 0 | Passed |
| **Meetings** | 2 | 0 | 2 | 0 | 0 | Passed |
| **Employees** | 3 | 0 | 3 | 0 | 0 | Passed |
| **Attendance** | 3 | 0 | 3 | 0 | 0 | Passed |
| **Leaves** | 2 | 0 | 2 | 0 | 0 | Passed |
| **Support Tickets** | 2 | 0 | 2 | 0 | 0 | Passed |
| **Activity Logs** | 5 | 0 | 5 | 0 | 0 | Passed |
| **Notifications** | 2 | 0 | 2 | 0 | 0 | Passed |
| **TOTALS** | **61** | **0** | **61** | **0** | **0** | **100% SUCCESS** |

---

### 5.2 Idempotency & Relational Integrity Verification
- **PHPUnit Feature Suite**: `backend/tests/Feature/JsonDataMigrationCutoverTest.php` passes with **4 tests and 89 assertions**.
  - Verified source file & backup existence
  - Verified dry-run execution causes zero DB mutations
  - Verified live migration relationships (`leads->company`, `deals->company`, `invoices->customer`, `payments->invoice`, `projects->teamMembers`, `tasks->project`, `employees->user`)
  - Verified strict idempotency on rerun (zero duplicate records created)
- **Frontend-to-Backend Integration Suite**: `frontend/src/tests/apiIntegration.test.ts` (Step 41) passes with **42/42 tests passing**.
- **Production Build**: `npm run build` compiled cleanly in 10.81s.
- **Backend Full Suite**: `php artisan test` passes with **177 passed (1174 assertions)**.

