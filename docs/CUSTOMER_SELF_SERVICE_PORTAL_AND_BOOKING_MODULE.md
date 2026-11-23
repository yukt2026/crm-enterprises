# Customer Self-Service Portal & Online Appointment Booking System (Prompt 46)

## Overview & Architecture

The **Customer Self-Service Portal & Online Appointment Booking Module** delivers an authenticated, highly secure, customer-facing gateway connected directly to the enterprise CRM. Business customers can independently manage their commercial proposals, access GST invoices, execute partial or full online payments via multi-gateway integration (Razorpay, Cashfree, UPI QR codes, cards, net banking), track client-enabled projects and approved deliverables, submit support tickets with verified message threads, and schedule business appointments with transaction-safe double-booking prevention.

Importantly, the customer portal enforces strict architectural boundaries:
- **Authentication Separation**: Portal accounts are handled via `App\Domains\CustomerPortal\Models\CustomerAccount`, issuing scoped Sanctum tokens (`['portal:customer']`), completely separated from internal CRM employee/user accounts.
- **Strict Data Confidentiality**: Customer queries strictly filter out internal employee discussions, private team notes (`is_internal = false`), internal developer tasks, and labor rates/budgets.
- **Tenant & Customer Scoping**: Every portal endpoint strictly asserts both `tenant_id` and `customer_id`. Direct ID tampering or cross-customer access yields immediate 404/403 errors.
- **Transaction-Safe Booking Engine**: Appointment slots are locked using database-level `lockForUpdate()` within atomic transactions, preventing race-condition double-booking across concurrent browser requests.

---

## 1. Database Schema & Data Models

### 1.1 `customer_accounts` Table
- `id`: Primary key.
- `tenant_id`: Foreign key to `tenants`.
- `customer_id`: Foreign key to `customers`.
- `email`: Customer official email address (unique per tenant).
- `phone`: Mobile phone number.
- `password`: Bcrypt hashed password.
- `is_active`: Boolean status (active / suspended).
- `email_verified_at`: Timestamp for email verification.
- `two_factor_enabled`: Boolean flag.
- `two_factor_secret`: Encrypted TOTP secret.
- `last_login_at`, `last_login_ip`: Security audit timestamps.
- `invite_token`, `invite_expires_at`: Admin-generated onboarding invitations.
- `remember_token`, `timestamps`, `softDeletes`.

### 1.2 `customer_login_histories` Table
- `id`: Primary key.
- `customer_account_id`: Foreign key to `customer_accounts`.
- `ip_address`: Request remote address.
- `user_agent`: Browser/device user agent string.
- `status`: `success` | `failed`.
- `created_at`: Audit log timestamp.

### 1.3 `portal_settings` Table
- `id`: Primary key.
- `tenant_id`: Unique foreign key to `tenants`.
- `is_portal_enabled`: Master switch for customer portal.
- `allow_self_registration`: Controls whether customers can self-register or require admin invitations.
- `allow_invoice_access`: Controls client invoice visibility.
- `allow_payments`: Enables online payment checkout.
- `allow_project_tracking`: Enables project milestone visibility.
- `allow_ticket_creation`: Enables portal support desk.
- `allow_appointment_booking`: Enables meeting scheduling.
- `allow_document_downloads`: Enables client document vault.
- `public_booking_page_enabled`: Enables public booking slug.
- `booking_slug`: Organization public vanity URL slug (e.g. `/public/booking/acme-corp`).
- `company_logo_url`, `brand_color`: Custom branding tokens.
- `booking_terms`, `booking_rules`: Business policies and cancellation rules.

### 1.4 `bookable_services` Table
- `id`: Primary key.
- `tenant_id`: Foreign key to `tenants`.
- `branch_id`: Optional branch assignment.
- `name`: Service title (e.g., "Architecture Review Session").
- `code`: Unique alphanumeric identifier.
- `description`: Service agenda and deliverables.
- `duration_minutes`: Consultation duration (5-480 minutes).
- `price`, `currency`: Consultation fee.
- `requires_advance_payment`, `advance_amount`: Deposit requirements.
- `assigned_staff_ids`: JSON array of eligible staff organizers.
- `is_active`: Availability status.

### 1.5 `meetings` Table (Extended)
- `customer_id`: Attached customer account.
- `service_id`: Attached bookable service.
- `booking_reference`: Public confirmation token (e.g., `APT-20261201-XYZ789`).
- `booking_fee`: Fee captured for appointment.
- `payment_status`: `unpaid` | `paid` | `refunded`.
- `cancellation_reason`, `cancelled_at`: Cancellation audit fields.
- `rescheduled_at`: Timestamp of slot adjustments.
- `is_customer_booked`: Boolean flag differentiating customer portal appointments.

---

## 2. API Endpoints

### 2.1 Customer Portal Authentication & Public Routes
| Method | Path | Description | Access |
|---|---|---|---|
| `POST` | `/api/v1/portal/auth/register` | Customer self-registration (when enabled in settings) | Public |
| `POST` | `/api/v1/portal/auth/login` | Authenticate customer & issue `portal:customer` token | Public |
| `POST` | `/api/v1/portal/auth/logout` | Revoke current customer session token | Customer |
| `GET` | `/api/v1/public/booking/{slug}` | Public booking page configuration & branding | Public |
| `GET` | `/api/v1/public/booking/{slug}/slots`| Real-time available booking time slots | Public |
| `POST` | `/api/v1/public/booking/{slug}/book` | Transaction-safe public appointment booking | Public |

### 2.2 Customer Portal Authenticated Resources (`auth:sanctum` with `portal:customer`)
| Method | Path | Description | Access |
|---|---|---|---|
| `GET` | `/api/v1/portal/dashboard` | Aggregated customer financials, active counts & recent records | Customer |
| `GET` | `/api/v1/portal/profile` | View profile, GSTIN, billing address & login history | Customer |
| `PUT` | `/api/v1/portal/profile` | Update contact info & billing address | Customer |
| `POST` | `/api/v1/portal/change-password` | Update portal account password | Customer |
| `GET` | `/api/v1/portal/quotations` | List shared quotations (`status != draft`) | Customer |
| `GET` | `/api/v1/portal/quotations/{id}` | View quotation line items & commercial details | Customer |
| `POST` | `/api/v1/portal/quotations/{id}/accept` | Digital quotation acceptance with IP/timestamp | Customer |
| `POST` | `/api/v1/portal/quotations/{id}/reject` | Decline quotation with formal reason | Customer |
| `POST` | `/api/v1/portal/quotations/{id}/request-revision` | Request commercial quote adjustments | Customer |
| `GET` | `/api/v1/portal/invoices` | List customer tax invoices | Customer |
| `GET` | `/api/v1/portal/invoices/{id}` | View invoice line items & payment breakdown | Customer |
| `POST` | `/api/v1/portal/invoices/{id}/pay` | Execute online payment via Razorpay/Cashfree/UPI | Customer |
| `GET` | `/api/v1/portal/projects` | List projects where `client_portal_enabled = true` | Customer |
| `GET` | `/api/v1/portal/projects/{id}` | View milestones & client deliverables | Customer |
| `GET` | `/api/v1/portal/documents` | Customer document vault (`is_client_visible = true`) | Customer |
| `GET` | `/api/v1/portal/tickets` | List customer support tickets | Customer |
| `POST` | `/api/v1/portal/tickets` | Create support ticket (`source_channel = portal`) | Customer |
| `GET` | `/api/v1/portal/tickets/{id}` | View ticket messages (`is_internal = false`) | Customer |
| `POST` | `/api/v1/portal/tickets/{id}/reply`| Reply to ticket thread | Customer |
| `POST` | `/api/v1/portal/tickets/{id}/feedback`| Submit CSAT 1-5 rating & feedback | Customer |
| `GET` | `/api/v1/portal/services` | Active bookable services catalog | Customer |
| `GET` | `/api/v1/portal/appointments` | Customer appointments history | Customer |
| `POST` | `/api/v1/portal/appointments` | Book appointment with atomic conflict check | Customer |
| `POST` | `/api/v1/portal/appointments/{id}/reschedule` | Reschedule appointment date/time | Customer |
| `POST` | `/api/v1/portal/appointments/{id}/cancel` | Cancel appointment with audit reason | Customer |

### 2.3 CRM Admin Configuration Endpoints
| Method | Path | Description | Access |
|---|---|---|---|
| `GET` | `/api/v1/crm/portal-settings` | Retrieve tenant portal configuration | Business Admin |
| `PUT` | `/api/v1/crm/portal-settings` | Update portal permissions, branding, terms | Business Admin |
| `POST` | `/api/v1/crm/customers/{id}/invite-portal` | Generate secure onboarding invite link | Business Admin |
| `apiResource` | `/api/v1/crm/bookable-services` | Manage bookable services catalog | Business Admin |

---

## 3. Frontend Architecture

### 3.1 Types (`frontend/src/api/types.ts`)
- `CustomerAccountItem`: Account attributes, contact data, customer relations.
- `CustomerLoginHistoryItem`: IP, device, status, timestamp logs.
- `PortalSettingItem`: Master toggles, registration policies, branding tokens.
- `BookableServiceItem`: Duration, pricing, advance deposit, assigned staff.
- `CustomerDashboardData`: Scoped financials, counts, recent invoices, recent quotes, upcoming meetings.
- `PublicBookingConfig` & `PublicBookingSlot`: Vanity config, working hours slot matrix.

### 3.2 Client Methods (`frontend/src/api/client.ts`)
- Full typed integration methods for customer auth (`portalRegister`, `portalLogin`, `portalLogout`), dashboard, quotations, invoices, payments, project deliverables, support tickets, appointment scheduling, and admin configurations.

### 3.3 React Query Hook (`frontend/src/hooks/useCustomerPortal.ts`)
- Caching, optimistic updates, and automatic query invalidation across all portal mutations.

### 3.4 Responsive View Component (`frontend/src/components/common/CustomerPortalView.tsx`)
- Tabbed, mobile-first design with:
  1. **Dashboard Overview**: Financial KPIs (outstanding balance, invoices, quotations, projects).
  2. **Quotations**: Review, digital accept, decline, revise modals.
  3. **Invoices & Payments**: Multi-method gateway checkout simulation (Razorpay, Cashfree, UPI).
  4. **Projects & Deliverables**: Milestone progress bars, shared client vault.
  5. **Support Help Desk**: Ticket submission, client message thread, CSAT rating.
  6. **Appointments & Online Booking**: Bookable service selection, time picker, reschedule & cancel controls.
  7. **Profile & Security**: GSTIN, registered billing address, credential updates.
  8. **CRM Admin Settings**: Switch self-registration, customize public slug, manage bookable services.

---

## 4. Testing & Verification

1. **PHPUnit Suite (`backend/tests/Feature/CustomerPortalAndAppointmentBookingTest.php`)**:
   - 11 Feature tests verifying self-registration controls, scoped Sanctum authentication, dashboard financial isolation, quotation acceptance, invoice payment, client-visible project masking, ticket messaging, transaction-safe appointment booking (`lockForUpdate` race-condition test), appointment rescheduling/cancellation, public booking page, and cross-customer/cross-tenant isolation.
   - **Result**: 11 passed, 65 assertions, 0 errors.

2. **Frontend Integration Test Step 61 (`frontend/src/tests/apiIntegration.test.ts`)**:
   - End-to-end API test covering portal settings configuration, service catalog creation, customer self-registration & login, scoped dashboard retrieval, support ticket submission & customer reply, public booking configuration & slot calculation, appointment booking with unique reference, rescheduling, and cancellation.
