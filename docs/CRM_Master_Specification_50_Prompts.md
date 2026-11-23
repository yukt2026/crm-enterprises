# Complete CRM Master Specification — 50 Prompt Scope

**Document type:** Consolidated product and development specification  
**Project:** Existing CRM — continued development, not a greenfield rebuild  
**Scope:** Core CRM (Prompts 1–30) + Advanced CRM and SaaS features (Prompts 31–50)  
**Audience:** Product owner, developer, QA engineer, AI coding agent and deployment team

---

## Important scope and continuity note

This document consolidates the CRM modules and functionality discussed for the existing project into one 50-prompt master scope. The detailed continuation from Prompt 31 to Prompt 50 follows the advanced feature plan prepared in this conversation. The exact verbatim text of the original Prompts 1–30 is not present in the current visible conversation, so Prompts 1–30 below are written as a comprehensive specification of the existing core CRM modules and requirements already identified—not represented as a word-for-word transcript of those earlier prompts.

The project must remain the **existing CRM application**. These requirements are for auditing, extending, completing and integrating its current codebase. They are not permission to replace the product with a new application.

---

# 1. Product vision

Build an India-first, AI-powered, modular business CRM and business operations platform. It should help small businesses, agencies, startups, service providers and larger organizations manage customers, leads, sales, communication, finance, employees, projects, support and business performance from one system.

The product must provide:

- A complete core CRM for customer and sales operations.
- Centralized, granular role-based access control (RBAC).
- Organization, team, department and branch-level data access.
- Omnichannel communication and official WhatsApp integration.
- AI assistance, calling and business intelligence.
- Marketing, sales, finance and GST workflows.
- HR, project, support, appointment and inventory operations.
- No-code automation and configurable workflows.
- Multi-tenant SaaS subscriptions, feature entitlements and platform Super Admin.
- Responsive, mobile-first user experience and PWA capabilities.

## Product principles

1. **Preserve the existing application:** audit before changing; reuse working modules and components.
2. **Real functionality only:** no decorative buttons, fake API responses, hardcoded dashboard metrics or permanent dummy data.
3. **One source of truth:** avoid duplicate modules, records, finance ledgers, customer profiles and services.
4. **Permission-first:** every module, action, record, report, attachment and integration must enforce authorization on the backend.
5. **Tenant isolation:** one organization must never access another organization's data.
6. **Configurable business rules:** stages, fields, templates, tax settings, approval limits, working hours and automation rules should be configurable where appropriate.
7. **Traceability:** sensitive actions and important state changes must be auditable.
8. **Safe financial operations:** use server-side calculations, transaction integrity, idempotency and reversal/correction records.
9. **Provider-aware integrations:** support real APIs and clearly show provider limitations; never pretend an unsupported provider feature is connected.
10. **Test before declaring complete:** frontend, backend, database, integrations, permissions and end-to-end flows must be tested.

---

# 2. Global technical and development requirements

## 2.1 Existing architecture discovery

Before implementation, inspect the actual repository and confirm its architecture from source files, package manifests, route definitions, database configuration and deployment files. Do not assume the stack if the repository shows otherwise.

The discussed target direction is:

- Frontend: existing React 19 + TypeScript + Vite + Tailwind CSS, preserving the current design system where present.
- Frontend state: existing Zustand setup where present.
- Backend: Laravel REST API as the application backend/source of truth, subject to the current repository audit.
- Database: MySQL for production persistence.
- Queues/cache: Redis and Laravel queue workers where supported by deployment.
- Real-time events: Laravel Reverb/WebSockets or the existing compatible real-time implementation.
- API communication: authenticated, versioned REST APIs with consistent validation and error handling.
- Files: private object/file storage with authorization-checked downloads.

Do not introduce a second backend or database without a documented migration reason. If legacy Express/JSON functionality exists, audit and migrate it safely rather than silently deleting it.

## 2.2 Required implementation pattern for every module

Every module must include, as applicable:

- Database schema, relationships, indexes and safe migrations.
- Backend routes, controllers, request validation, policies and service logic.
- Frontend pages, reusable components, forms, tables, filters and detail views.
- Search, sorting, filtering, pagination and bulk actions where relevant.
- Loading, empty, success, error and access-denied states.
- Server-side authorization and data scoping.
- Activity history and audit events for sensitive operations.
- Notifications and real-time updates where relevant.
- File validation and secure storage where attachments exist.
- Unit, feature, API, integration and end-to-end tests appropriate to the feature.
- User-facing and developer documentation.

## 2.3 Global permission and data-scope requirements

The organization must be able to assign roles and granular permissions. Every backend request must validate authentication, tenant membership, subscription entitlement where applicable, module/action permission and record-level access scope.

Supported data scopes should include:

- All records in the organization.
- Own records.
- Assigned records.
- Team records.
- Department records.
- Branch records.
- Selected employee/resource records.

These scopes must apply to list and detail APIs, search, dashboard aggregates, exports, reports, notifications, WebSocket events, attachments, background jobs and automation execution—not only to the visible menu.

## 2.4 Quality and security baseline

- Never rely only on hiding frontend buttons for security.
- Validate all input on the backend.
- Protect against IDOR, cross-user and cross-tenant access.
- Use CSRF/session protections or secure token handling as applicable.
- Store secrets only in secure server-side configuration.
- Verify webhook signatures where providers support them.
- Use idempotency for payment, message and event processing.
- Use database transactions for multi-step financial and inventory changes.
- Add indexes and prevent avoidable N+1 queries.
- Apply rate limits and upload restrictions.
- Use least-privilege access for background workers and integration credentials.
- Avoid unnecessary collection of sensitive employee/customer data.
- Provide backup, restore and rollback procedures.

---

# 3. Prompts 1–30: Core CRM foundation and existing business modules

## PROMPT 01 — Existing project setup, architecture preservation and development standards

**Purpose:** Establish a safe development baseline for the existing CRM.

**Functionality and requirements:**

- Inspect the repository, folder structure, frontend application, backend application, database, environment configuration and deployment workflow.
- Identify the current authentication mechanism, routing structure, API conventions, state management, UI library and reusable components.
- Identify existing modules and their dependencies before changing code.
- Define consistent naming, API response, error handling, validation and logging conventions.
- Preserve current working screens, user flows, theme, routes and data relationships.
- Separate configuration and secrets from source code.
- Establish safe migrations, rollback guidance and development/staging environment practices.
- Create a project architecture document and module dependency map.

**Acceptance criteria:** The repository builds successfully, existing routes remain available, current data is preserved, and developers have a verified map of the current architecture.

## PROMPT 02 — Authentication, login, logout and account security

**Purpose:** Provide secure access to the CRM for authorized users.

**Functionality:**

- Login and logout.
- Password reset and change password.
- Forgot-password flow using a verified email or configured OTP service.
- Account invitation and activation where enabled.
- Email/phone verification where configured.
- Session expiration and device/session management.
- Active/inactive account handling.
- Login history and failed-login events.
- Optional two-factor authentication.
- Safe redirect and protected-route handling.
- Account lockout/rate limiting for repeated failed attempts.

**Acceptance criteria:** Unauthenticated users cannot access private CRM APIs or screens; inactive users cannot continue using the system; credentials are stored using secure password hashing; reset tokens expire and cannot be reused.

## PROMPT 03 — Organization profile and business workspace setup

**Purpose:** Maintain the business identity and operating configuration used throughout the CRM.

**Functionality:**

- Organization name, legal name, logo, website and contact details.
- Business address, country, state, city and postal code.
- Default currency, date format and timezone.
- Business type/industry and organization description.
- Financial year and default invoice preferences where applicable.
- Organization-wide working days and business hours.
- Default notification preferences.
- Organization branding for customer-facing documents and pages.
- Organization activation status and account owner association.
- Change history for important organization settings.

**Acceptance criteria:** Organization settings are persisted in the database and consistently used by dashboard, reports, documents, notifications and connected modules.

## PROMPT 04 — User and employee account management

**Purpose:** Allow authorized administrators to create and manage CRM users.

**Functionality:**

- Add, invite, edit, activate, deactivate and archive users.
- Employee/user profile with name, email, phone, employee ID and profile image.
- Assign department, designation, branch, reporting manager and team.
- Assign roles and permissions through the centralized permission system.
- Track joining date and account status.
- Resend invitations and handle invitation expiry.
- Prevent duplicate email/user accounts within the applicable organization rules.
- Display last login and account activity where permitted.
- Safe account deactivation and session revocation.

**Acceptance criteria:** Only authorized administrators can manage users; deactivated users lose access; references to historical activity remain intact.

## PROMPT 05 — Department, team and reporting hierarchy management

**Purpose:** Organize the organization structure used for ownership and access control.

**Functionality:**

- Create and edit departments and teams.
- Assign department heads and team managers.
- Assign employees to departments and teams.
- Maintain reporting manager relationships.
- Transfer employees between teams/departments with history.
- Configure team membership and status.
- Use department/team relationships in lead routing, task assignment, reports and permissions.
- Prevent invalid circular reporting relationships.

**Acceptance criteria:** Organization structure is usable by user management, RBAC, lead assignment, HR and reporting modules.

## PROMPT 06 — Main dashboard and role-specific overview

**Purpose:** Give each authorized user a useful view of their work and permitted business metrics.

**Functionality:**

- Overview cards for applicable leads, customers, deals, tasks, projects, tickets and financial metrics.
- Date-range filters and comparison periods.
- Recent activity and upcoming tasks.
- Follow-up reminders and overdue items.
- Sales pipeline snapshot.
- Recent customers and leads.
- Role/branch/team-aware widgets.
- Quick actions such as create lead, contact, task or meeting where permitted.
- Configurable dashboard widgets and layout where supported.
- Loading, no-data and error states.

**Acceptance criteria:** All metrics must come from actual authorized backend data; users must not see values derived from records outside their scope.

## PROMPT 07 — Notifications and reminder center

**Purpose:** Provide a central notification system for CRM events and user tasks.

**Functionality:**

- In-app notifications for new assignments, mentions, task due dates, approvals and relevant status changes.
- Read/unread state and mark-all-read.
- Notification preferences by event/category.
- Deep links to records, with permission rechecked when opened.
- Email notifications through a configured provider where enabled.
- Browser/mobile push support where configured.
- Reminder scheduling and delivery logs.
- Notification deduplication and retry handling.
- Role-aware and record-scope-aware delivery.

**Acceptance criteria:** Notifications are reliable, do not leak restricted record details and open only records the recipient can access.

## PROMPT 08 — Activity timeline, audit log and record history

**Purpose:** Maintain traceability across CRM records and important user actions.

**Functionality:**

- Timeline of calls, emails, messages, meetings, notes, tasks and status changes.
- Created/updated/deleted/assigned/approved events where appropriate.
- Before/after values for important changes where safe.
- Actor, timestamp, source and related record.
- Search and filters with permissions.
- Export only for authorized roles.
- Tamper-resistant approach for security and financial audit events.
- Retention configuration and access restrictions.

**Acceptance criteria:** Important activity is recorded consistently and restricted audit details are visible only to authorized users.

## PROMPT 09 — Contacts and customer directory

**Purpose:** Maintain people and customer contact records centrally.

**Functionality:**

- Create, view, edit, archive and restore contacts.
- Name, phone, email, job title, company and addresses.
- Billing/shipping details where relevant.
- Contact owner, team and branch.
- Tags, source, category and custom fields.
- Notes, attachments and communication preferences.
- Related leads, deals, invoices, projects, tickets and conversations.
- Duplicate detection and controlled merge.
- Search, filters, sorting, pagination and import/export.
- Contact history and ownership history.

**Acceptance criteria:** A contact is a reusable master record and is not duplicated every time a new enquiry or deal is created.

## PROMPT 10 — Companies and business accounts

**Purpose:** Maintain company/customer account records and link multiple contacts to a business.

**Functionality:**

- Company name, legal name, domain, website, industry and size.
- Business address and contact details.
- GST/business identifiers where applicable and permission-controlled.
- Company owner, branch and account manager.
- Multiple associated contacts.
- Related leads, deals, projects, invoices, tickets and activities.
- Account notes, tags, documents and custom fields.
- Duplicate detection and merge workflow.
- Company search, filters, import/export and account history.

**Acceptance criteria:** Company and contact relationships remain consistent and are protected by tenant and record scopes.

## PROMPT 11 — Core lead management

**Purpose:** Capture, organize, assign and track prospective customers.

**Functionality:**

- Lead creation from manual entry and available existing forms/APIs.
- Lead details: name, phone, email, company, location, requirement, source, status, priority and owner.
- Lead assignment and reassignment.
- Lead status history.
- Notes, tags, attachments and custom fields.
- Lead search, filters, sorting and pagination.
- Follow-up date and next action.
- Duplicate detection.
- Lead conversion into contact/company/deal with history preservation.
- Lost reason and recycled lead handling.
- Assigned/own/team/branch access scopes.

**Acceptance criteria:** Lead CRUD, assignment, conversion, history and permissions work end-to-end with real database records.

## PROMPT 12 — Lead source, forms, import and capture management

**Purpose:** Track where leads originate and capture external enquiries.

**Functionality:**

- Configurable lead sources and source categories.
- Campaign/source attribution fields.
- Website enquiry forms and embedded form endpoints where present.
- CSV/Excel import with preview, validation and error report.
- Duplicate checks before import.
- API/webhook lead capture with authentication and request validation.
- Source-wise and campaign-wise lead reports.
- Mapping of external fields to CRM fields.
- Import history and rollback strategy where feasible.

**Acceptance criteria:** Lead source is preserved through conversion and reports use actual source attribution.

## PROMPT 13 — Sales pipelines and stage configuration

**Purpose:** Represent the organization's sales process.

**Functionality:**

- One or more sales pipelines.
- Configurable stages and order.
- Stage probability and expected close logic.
- Drag-and-drop Kanban view where permitted.
- List view and filters.
- Stage transition history.
- Required fields or approval rules for selected transitions.
- Pipeline-specific permissions and ownership.
- Stage-wise deal count/value reports.

**Acceptance criteria:** Pipeline changes persist and do not corrupt existing deal relationships or stage history.

## PROMPT 14 — Deals and opportunity management

**Purpose:** Track potential revenue and opportunities through closure.

**Functionality:**

- Deal name, value, customer, company, source lead and pipeline.
- Stage, probability, expected close date and owner.
- Product/service interest, notes, tags and attachments.
- Activities and next follow-up.
- Won/lost status and lost reason.
- Deal reassignment and history.
- Related quotations and invoices where available.
- Search, filters and deal reports.
- Forecast support using stage probability and expected close date.

**Acceptance criteria:** Deal creation, stage updates, conversion and reports work with correct ownership and financial references.

## PROMPT 15 — Sales activities, calls, notes and follow-ups

**Purpose:** Keep a complete record of sales engagement.

**Functionality:**

- Log call, email, meeting, note and other sales activity.
- Set follow-up date/time and reminder.
- Assign activity owner.
- Record outcome and next action.
- Link activities to leads, contacts, companies and deals.
- View activity timeline.
- Mark complete, reschedule or cancel.
- Overdue activity alerts.
- Team/employee follow-up reports.

**Acceptance criteria:** Every activity has a valid owner and linked record where required; activity history remains available after lead conversion.

## PROMPT 16 — Tasks, checklist and personal work management

**Purpose:** Help employees manage their assigned work.

**Functionality:**

- Create tasks and subtasks.
- Assign one or more authorized users where supported.
- Priority, status, start date, due date and estimated effort.
- Related lead/contact/deal/project/ticket.
- Checklist, comments, attachments and activity history.
- List, Kanban and calendar views.
- Task reminders and overdue alerts.
- Recurring tasks where configured.
- Team workload view.
- Permission-controlled reassignment and deletion.

**Acceptance criteria:** Tasks appear in the assigned user's workspace and unauthorized users cannot view or modify restricted tasks.

## PROMPT 17 — Calendar, meetings and scheduling

**Purpose:** Coordinate internal and customer meetings.

**Functionality:**

- Calendar day/week/month views.
- Create and edit meetings.
- Attendees and organizer.
- Customer/lead/deal association.
- Meeting location or conferencing link.
- Agenda and notes.
- Start/end time and timezone handling.
- Reminders and notifications.
- Reschedule and cancellation history.
- Optional Google Calendar/other calendar integration where configured.
- Meeting outcome and follow-up task creation.

**Acceptance criteria:** Scheduling uses correct timezone rules and prevents or warns about conflicts according to configured policy.

## PROMPT 18 — Projects and delivery tracking (core)

**Purpose:** Track client work after a deal is won or a project is manually created.

**Functionality:**

- Project name, client, owner, team, dates, status and description.
- Basic milestones and tasks.
- Project progress and deadlines.
- Project comments and documents.
- Project status history.
- Link to originating deal and customer.
- Project dashboard and delayed-project report.
- Role/team-based access.

**Acceptance criteria:** Project information is persisted and related to customer and sales records without duplicate customer data.

## PROMPT 19 — File and document management (core)

**Purpose:** Attach and organize business documents throughout the CRM.

**Functionality:**

- Upload, preview and download supported file types.
- Link files to contacts, companies, leads, deals, projects, tickets and invoices.
- File category, description, uploader and timestamps.
- File size/type validation and malware scanning where available.
- Private storage and access-controlled download URLs.
- Replacement/version history where appropriate.
- Delete/archive permissions and audit history.

**Acceptance criteria:** Files are never publicly exposed by guessable URLs and authorization is checked on every download.

## PROMPT 20 — Support tickets (core)

**Purpose:** Record and track customer issues.

**Functionality:**

- Create ticket with customer, subject, description, category and priority.
- Assign to team/employee.
- Status lifecycle.
- Internal notes and customer replies.
- Attachments.
- Ticket history and related customer context.
- Due date and basic SLA fields.
- Resolve, close and reopen.
- Search, filters and support dashboard.

**Acceptance criteria:** Ticket records are connected to the customer and only authorized employees can access internal details.

## PROMPT 21 — Finance dashboard and financial records (core)

**Purpose:** Provide basic financial visibility inside the CRM.

**Functionality:**

- Finance overview based on recorded invoices, payments and expenses.
- Revenue, collected amount and outstanding amount.
- Date-range filters.
- Customer-wise and period-wise financial summaries.
- Financial record links to customers/deals/projects.
- Currency and decimal-safe amount handling.
- Restricted access to financial data.

**Acceptance criteria:** Financial dashboard totals reconcile with source records and respect user data permissions.

## PROMPT 22 — Invoice and billing management (core)

**Purpose:** Create and manage business invoices.

**Functionality:**

- Invoice number and date.
- Customer billing information.
- Line items, quantity, rate, discount and tax fields.
- Subtotal, tax and grand total calculations.
- Due date and payment terms.
- Draft, issued, paid, partially paid, overdue and cancelled/void states as appropriate.
- PDF generation and authorized download.
- Invoice reminders.
- Invoice history and related payments.
- Link invoice to customer/deal/project.

**Acceptance criteria:** Server-side calculations are authoritative, invoice numbers are unique within their defined series and issued financial records are not silently overwritten.

## PROMPT 23 — Payment records and collection tracking (core)

**Purpose:** Track payments against CRM invoices and customers.

**Functionality:**

- Manual payment entry with method/reference.
- Payment date and amount.
- Link payment to invoice/customer.
- Partial payment and remaining balance.
- Receipt generation.
- Payment status and history.
- Payment reminders.
- Refund reference and notes where supported.
- Collection reports.
- Role-controlled payment edits and exports.

**Acceptance criteria:** Payment allocation and invoice balance remain consistent; changes are audited and duplicate payments are prevented.

## PROMPT 24 — Expense management (core)

**Purpose:** Record business expenses.

**Functionality:**

- Expense category.
- Amount, date, vendor and description.
- Receipt attachment.
- Employee, department and branch allocation.
- Project/customer reference where relevant.
- Draft/submitted/approved/rejected/paid states.
- Manager approval.
- Expense reports and exports.

**Acceptance criteria:** Approval and payment status are traceable and expense data is visible only to authorized roles.

## PROMPT 25 — HR employee records (core)

**Purpose:** Maintain basic employee information for CRM operations.

**Functionality:**

- Employee profile and ID.
- Contact and employment details.
- Department, designation, manager and branch.
- Joining date and employment status.
- Employee documents.
- Emergency/contact details where appropriate.
- Employee search and filters.
- Employee activity and assignment references.

**Acceptance criteria:** HR records use secure access scopes and remain linked to CRM user accounts where appropriate.

## PROMPT 26 — Attendance and leave (core)

**Purpose:** Record basic employee attendance and leave requests.

**Functionality:**

- Check-in/check-out.
- Attendance date and status.
- Working hours where calculable.
- Leave types and balances.
- Leave request and approval/rejection.
- Holiday calendar.
- Attendance correction request.
- Employee and manager views.
- Attendance and leave reports.

**Acceptance criteria:** Attendance and leave changes are auditable and manager approval rules are enforced.

## PROMPT 27 — Reports and exports (core)

**Purpose:** Provide operational reports across the existing CRM.

**Functionality:**

- Leads, contacts, companies, deals, tasks, projects, tickets and employee reports.
- Date, status, source, employee, team and branch filters where available.
- Tables and basic charts.
- Search and sort.
- CSV/Excel/PDF export where supported.
- Saved filters/views where available.
- Scheduled reports where configured.
- Data-scope-aware aggregate calculations.

**Acceptance criteria:** Reports reconcile with underlying records and never reveal records or fields outside the viewer's permissions.

## PROMPT 28 — CRM settings, custom fields and configurable options

**Purpose:** Make the CRM configurable for different business needs.

**Functionality:**

- Organization settings.
- User preferences.
- Status and category settings.
- Tags.
- Lead sources.
- Task priorities.
- Ticket categories.
- Custom fields for supported modules.
- Required/optional field rules.
- Dropdown, text, number, date and other supported field types.
- Field visibility and editability controls.
- Form validation rules.
- Default values and safe changes to existing records.

**Acceptance criteria:** Configuration changes are validated, permission-controlled and do not break existing records or API contracts.

## PROMPT 29 — Global search, filters, import/export and bulk operations

**Purpose:** Make CRM records easy to locate and manage efficiently.

**Functionality:**

- Global search across authorized modules.
- Module-specific search.
- Advanced filters and saved views.
- Sorting and pagination.
- CSV/Excel import with field mapping and validation.
- Import preview and row-level error report.
- Duplicate detection.
- Permission-controlled export.
- Bulk assignment, tagging, status updates and archive where supported.
- Background processing for large imports/exports.
- Import/export history.

**Acceptance criteria:** Search results and bulk actions obey RBAC and data scopes; large jobs are queued and have status/error reporting.

## PROMPT 30 — Core integrations, email, API and webhook foundation

**Purpose:** Provide a consistent integration layer for existing and future CRM services.

**Functionality:**

- Email provider configuration and outbound email where supported.
- Inbound email integration where configured.
- API authentication and API key/token management.
- Versioned API conventions.
- Webhook creation, signing and delivery logs.
- Retry and failure handling.
- Integration status and health checks.
- External ID mapping.
- Provider credential storage in secure backend configuration.
- Integration permissions and audit logs.
- Documentation for API endpoints and webhook events.

**Acceptance criteria:** Integrations use verified events, protect secrets, prevent duplicate processing and expose clear error states.

---

# 4. Prompts 31–50: Advanced CRM, AI, automation and SaaS expansion

## PROMPT 31 — Complete existing CRM audit and gap analysis

**Purpose:** Inspect the CRM after the first 30 development prompts and establish what is actually complete before adding advanced modules.

**Required audit:**

- Inspect frontend routes, screens, reusable components and state management.
- Inspect backend routes, controllers, services, validation and authorization.
- Inspect database schema, migrations, indexes and relationships.
- Inspect authentication, RBAC, tenant/branch scoping and activity logs.
- Inspect all core modules from Prompts 1–30.
- Inspect current integrations, queues, scheduled jobs and real-time events.
- Test CRUD, search, filters, pagination, validation, exports and reports.
- Identify frontend-only, backend-only, partially implemented, broken, duplicated and insecure features.
- Identify missing functions and reusable components for Prompts 32–50.
- Check legacy data and compatibility requirements.

**Required gap categories:** complete and working; partially implemented; frontend only; backend only; missing; broken; implemented but insecure.

**Deliverable:** A module-wise audit report and a persistent `CRM_ADVANCED_IMPLEMENTATION_STATUS.md` checklist. Do not duplicate features that already work. Do not make broad destructive changes in this audit step.

## PROMPT 32 — Enterprise RBAC, custom roles and granular data permissions

**Purpose:** Establish the centralized permission foundation for all existing and future modules.

**Role management:**

- Create, edit, duplicate, activate, deactivate and safely delete custom roles.
- Protect required system roles and organization ownership.
- Assign roles to users and show effective permissions.
- Compare roles and copy permissions from another role.
- Provide default role presets: Organization Owner, Organization Admin, Branch Manager, Department Manager, Sales Manager, Sales Executive, Accountant, HR Manager, HR Executive, Marketing Manager, Marketing Executive, Support Manager, Support Agent, Project Manager, Employee and Customer Portal User.
- Keep platform Super Admin separate from tenant roles.

**Granular actions:**

- View, create, edit, delete, restore, permanent delete, import, export, download, print, assign, reassign, approve, reject, archive, share, manage settings, manage templates, view reports, manage integrations and execute actions.

**Record scopes:**

- All organization records, own records, assigned records, team records, department records, branch records and selected employee/resource records.

**Permission UI:**

- Module-wise expandable permission matrix.
- Select all/deselect all.
- Permission search.
- Role comparison.
- Copy permissions.
- Data-scope selector.
- Branch/department scope selector.
- Effective permission summary and unsaved-change warning.

**Backend enforcement:**

- Central authorization service using backend policies/gates/middleware and service-level checks.
- Check authentication, organization membership, feature entitlement, module permission, action permission and record scope on every protected API.
- Apply checks to searches, dashboard totals, exports, reports, attachments, notifications, WebSocket events, jobs and webhooks.
- Revoke or refresh access after role changes and safely invalidate permission cache.
- Prevent IDOR and cross-user/cross-branch/cross-tenant access.

**Audit and tests:** Log role/permission changes and denied sensitive access. Test every role/action/scope combination and verify unauthorized operations fail on the backend.

## PROMPT 33 — Omnichannel communication hub

**Purpose:** Provide one unified inbox for customer communications from supported channels.

**Channels:** Official WhatsApp Business Platform, Facebook Messenger, Instagram Direct Messages, Website Live Chat, Email, SMS, cloud telephony call records and Telegram only where supported by an authorized integration.

**Inbox experience:**

- All, unread, assigned to me, unassigned, team, priority, pending, closed and archived conversations.
- Conversation timeline and message composer.
- Supported text, image, document, audio and other channel-specific attachments.
- Delivery/read status only where the provider supplies it.
- Customer profile sidebar with permission-controlled contact, company, lead, deal, ticket and payment context.
- Internal notes, mentions, tags, priority and assignment history.

**Team operations:** Assign/transfer conversations; route by round-robin, least-active agent, branch, department, language, source or business hours; detect agent collision; close/reopen and escalate conversations.

**SLA:** Configure first response and resolution targets, working hours, holidays, pause conditions and escalation rules.

**Technical requirements:** Provider adapters, webhook signature verification, event deduplication, idempotent message handling, queue-based outbound delivery, retry/failure recovery, secure attachments and authenticated real-time channels.

**Reports:** Conversations by channel, agent, team and branch; first response time; resolution time; unassigned items; SLA breaches and customer satisfaction.

**Permissions:** Separate inbox visibility, assigned/team/all conversations, send, assign, transfer, close, export, customer detail, routing and integration settings.

## PROMPT 34 — Official WhatsApp Business CRM, templates, campaigns and automation

**Purpose:** Implement a full official WhatsApp Business Platform integration, not unofficial WhatsApp Web automation.

**Connection:** Meta business connection, WhatsApp Business Account/phone mapping, multiple numbers, secure credentials, webhook setup, token expiry monitoring, connection health and error logs.

**Messaging:** Supported text, image, document, PDF, audio/video and interactive messages; approved templates; template variables; delivery/read/failure states from provider events.

**Template manager:** Sync templates, show category/language/approval status, preview variables, map fields, test messages and view usage.

**Campaign manager:** Create campaigns, select number/template/audience, segment contacts, import CSV with validation and opt-in checking, map variables, test, schedule, pause, approve and launch. Report recipients, queued, sent, delivered, read, failed, replied, opted out and attributable conversions where available.

**Automation:** Welcome messages, keyword responses, lead qualification, appointment confirmation, payment reminders, follow-ups, onboarding and service updates.

**Compliance:** Maintain consent source and timestamp, opt-out/suppression list, approved templates, applicable messaging windows and frequency controls. Respect Meta policies and applicable Indian requirements.

**Billing/usage:** Track provider-reported usage and charges where available, number/campaign/organization usage and internal plan limits. Do not invent rates.

**Permissions:** Inbox, send, campaign creation/approval/launch, templates, imports, bulk messaging, number configuration, credentials and usage/billing reports.

**Testing:** Real inbound/outbound flows, webhook retries, duplicate events, template variables, opt-outs, provider errors and unauthorized operations.

## PROMPT 35 — AI CRM assistant, AI sales agent and document intelligence

**Purpose:** Create a centralized, provider-independent AI service that works across permitted CRM data.

**AI provider layer:** Provider/model configuration, secure API keys, organization preferences, fallback rules where configured, token/credit/cost usage, limits, logs, retries and provider health.

**AI sales assistant:** Lead summaries, qualification, configurable hot/warm/cold scoring, buying intent, customer requirement extraction, next-best-action suggestions, personalized follow-up drafts, sales pitch, objection suggestions, deal risk and lost-deal analysis.

**AI business assistant:** Natural-language questions such as today's leads, collected revenue, overdue follow-ups, customer receivables, likely closing deals and branch conversion. Queries must use actual authorized records and explain selected periods/filters.

**AI action proposals:** Draft tasks, messages, permitted field updates, reports, quotation drafts and reminders. Require explicit confirmation for sensitive changes or external communication. Never bypass RBAC.

**Meeting intelligence:** Audio upload or supported meeting integration, transcription, summary, requirements, objections, action items and suggested CRM tasks. Require review before committing important details.

**Document intelligence:** Extract customer details, GSTIN where present, invoice numbers, amounts, dates, due dates, purchase-order terms, agreements and renewal dates from supported PDFs/images/documents. Show confidence and require human verification before saving sensitive fields.

**Content generation:** Draft WhatsApp/email replies, follow-ups, proposals, support responses, campaign copy and internal summaries in configurable languages and tone.

**Security:** Permission-aware retrieval, prompt-injection defenses for uploaded/customer content, no cross-tenant context leakage, no secret exposure and approval for refunds, financial changes, discounts and campaign sends.

**Permissions:** Use AI, view insights, analyze leads, generate content, read transcripts/documents, create AI tasks, execute approved actions, manage providers/credits and view usage.

## PROMPT 36 — Advanced lead generation, distribution and leakage prevention

**Purpose:** Upgrade the existing Leads module without creating a duplicate lead database.

**Sources:** Website forms, landing pages, Facebook/Instagram Lead Ads, Google Ads tracking, WhatsApp, live chat, incoming calls, CSV/Excel, API/webhooks, referrals and authorized integrations with IndiaMART/Justdial/TradeIndia when available.

**Lead capture:** Validate and normalize data; match existing contacts/leads; detect duplicates; preserve original source/campaign; create or update records according to configurable rules; assign owner/team/branch; create follow-up task and notify the assignee.

**Lead data:** Contact details, company, location, product/service interest, source, campaign, branch, status, priority, score, owner, last contact, next follow-up, notes and custom fields.

**Duplicate handling:** Similarity checks by phone/email/company/name/external ID; show merge suggestions; allow controlled merge with field conflict resolution and history. Never silently merge unrelated customers.

**Assignment engine:** Round-robin, least-active, availability, branch/location, department, service, source and campaign routing; fallback if assignee is unavailable; prevent concurrent duplicate assignments.

**Scoring and lifecycle:** Configurable scoring based on source, budget, requirement, timeline, location, interaction and custom fields; hot/warm/cold thresholds; configurable stages and required transitions.

**Follow-ups:** Call/WhatsApp/email/meeting tasks, due dates, outcome, rescheduling and overdue views.

**Leakage prevention:** Configure first-contact SLA, maximum idle time, follow-up deadlines, working hours, holiday exclusions, manager alerts and optional reassignment after escalation. Preserve full alert/reassignment history.

**Conversion:** Convert lead into existing/new contact, company and deal; optionally start project onboarding; prevent duplicate contacts and preserve history.

**Reports:** Source and campaign conversion, employee and branch conversion, ageing, response time, missed follow-ups, lost reasons, recycled leads and leakage.

**Permissions:** Own/assigned/team/branch/all scopes plus import/export/merge/assign/reassign/convert/scoring/automation/report permissions.

## PROMPT 37 — Cloud telephony, call centre and AI calling agent

**Purpose:** Integrate real business calling with Leads, Contacts, Deals, Omnichannel and AI.

**Provider architecture:** Provider-independent cloud telephony/SIP/VoIP adapter with secure credentials, phone numbers, webhooks, connection health, call settings and provider logs. Feature availability must reflect each provider's capabilities.

**Calling:** Click-to-call from lead/contact/company/deal/customer profiles; incoming call popup; caller matching; call transfer, queue and IVR where supported; callback scheduling; missed call capture.

**Call logs:** Provider/internal IDs, contact, employee, direction, phone, start/end, duration, disposition, linked records, follow-up and recording reference. Deduplicate repeated webhook events.

**Recording and quality:** Recording only where lawful and provider-supported, with appropriate notice, access control, retention and download permission. Do not secretly record calls.

**AI agent configuration:** Agent purpose, name, language, voice, greeting, conversation instructions, knowledge base, allowed actions, duration limits, calling hours and escalation rules.

**Use cases:** Lead qualification, follow-up, appointment confirmation, renewal, service enquiry, payment reminder and feedback collection.

**AI capabilities:** Hindi, Hinglish, English and supported regional languages; ask approved questions; capture answers; identify intent; summarize; create follow-up drafts; transfer to a human on request, low confidence or sensitive issue.

**Compliance:** Respect applicable telecom, consent, DND and calling restrictions; enforce attempts and time windows.

**Reports:** Incoming/outgoing/answered/missed calls, duration, conversion, callback completion, agent/AI performance and provider usage/cost where available.

**Permissions:** Initiate/receive calls, view logs, play/download recordings, use/manage AI agents, manage phone numbers/IVR, configure providers and view reports.

## PROMPT 38 — Marketing automation and campaign manager

**Purpose:** Plan, launch, automate and measure customer marketing activities.

**Channels:** Connected WhatsApp, email, SMS, Facebook/Instagram and Google Ads tracking, website forms and approved integrations.

**Campaign setup:** Name, type, objective, channel, audience, owner, budget, dates, status and approval state. Support draft, review, approved, scheduled, active, paused, completed and archived.

**Segmentation:** Filter by customer type, lead status/source, location, branch, industry, interest, purchase history, last interaction, tags and custom fields. Preview audience and exclusions.

**Content builder:** Templates, subject, body, personalization variables, CTA, supported attachments, preview, test and version history. AI can draft but cannot launch without approval when approval is required.

**Drip journeys:** Welcome, lead nurture, abandoned enquiry recovery, onboarding, renewal, birthday/anniversary, payment and re-engagement sequences. Support triggers, delays, conditions, stop rules and customer reply handling.

**Scheduling:** Timezone-aware sends, business hours, frequency limits, queueing, pause/resume, cancel, provider rate limits and failure retries.

**Approval:** Configurable submit/review/approve/launch process with full history.

**Analytics:** Provider-reported reach/delivery/opens/clicks where available, replies, leads, conversions, attributed revenue, spend, CPL, CAC and ROI. Clearly label estimates and attribution limitations.

**Compliance:** Consent, opt-in source, unsubscribe, suppression list and channel-specific policies.

**Permissions:** View/create/edit/approve/launch/pause, manage audience/import/export, templates, integrations, budgets and analytics.

## PROMPT 39 — Advanced sales, deals, quotations, proposals and commissions

**Purpose:** Complete the sales lifecycle from opportunity to approved quotation and closed deal.

**Pipelines:** Multiple pipelines, stages, probability, required fields, transitions, approval rules, owner, active state and Kanban/list views.

**Deal details:** Customer/company/contact/source lead, value, stage, expected close, probability, product/service, owner/team/branch, competitor, notes, attachments and next follow-up. Track status and ownership history.

**Forecasting:** Expected revenue based on deal value, stage probability, close date and historical conversion; label forecast separately from actual collected revenue.

**Quotation builder:** Unique number, customer, line items, quantity, unit price, discounts, tax, terms, payment conditions, validity, branding and multiple templates. Perform server-side calculations.

**Quotation workflow:** Draft, approval, PDF, email/WhatsApp share through connected channels, customer accept/reject/change request, expiry reminder and revision/version history. Preserve customer response evidence.

**Conversion:** Approved quotation to invoice using the existing finance/tax engine; prevent duplicate invoice generation.

**Proposals/contracts:** Templates, versioning, customer approval, authorized e-signature integration, expiry and renewal reminders. Do not represent internal approval as a valid digital signature.

**Discount controls:** Maximum discount by role/product/deal value, approval levels and complete audit history.

**Commission:** Fixed, percentage, tiered, team and employee commission; calculate on deal closure or payment basis as configured; approvals, refund reversals and reports.

**Reports:** Pipeline, won/lost, conversion, average deal size, sales cycle, forecast, employee/branch/product sales, quotation acceptance and commissions.

**Permissions:** Deal view/edit/transfer, pipeline configuration, quotation creation/approval, discount approval, contracts, commission calculation/approval and reports.

## PROMPT 40 — Finance, payments, receivables, payables and UPI

**Purpose:** Complete the existing finance system; do not introduce a duplicate ledger.

**Payment providers:** Razorpay, Cashfree and other configured providers; support UPI links/QR where provider and payment flow allow, bank transfer and cash entries.

**Payment request:** Link payment to customer, invoice, quotation, deal, project or service. Support fixed/partial/advance amounts, expiry, description, notification and provider status.

**Verification:** Confirm payment using signed provider webhooks or server-side provider verification; never trust only the browser success page. Use idempotency and duplicate-event handling.

**Reconciliation:** Store internal/provider transaction IDs, customer, invoice, amount, method, date, provider/internal statuses, fees if known, refund reference and reconciliation status.

**Partial/advance payments:** Multiple payments per invoice, unallocated funds, allocation, overpayment and balance handling using precise decimal types and database transactions.

**Receivables:** Customer outstanding, pending/overdue invoices, ageing buckets, promise-to-pay, disputes, collection owner, tasks and automated reminders.

**Payables:** Vendor bills, due dates, payment requests/approvals, partial payments, outstanding and vendor ageing.

**Refunds:** Full/partial request, approval, provider API, status/failure, credit note integration and audit history.

**Expenses:** Category, vendor, amount, date, receipt, employee, project/branch, approval, reimbursement and reports.

**Reports:** Revenue, collections, outstanding, receivables, payables, expenses, refunds, methods, customer/vendor and branch comparisons. Distinguish invoiced, collected and forecast revenue.

**Financial safeguards:** Posted transactions must not be silently deleted; use reversals/corrections. Validate calculations, webhooks, idempotency, balances and authorization.

**Permissions:** View own/assigned/all finance records, create/edit unposted payments, approve, refund, reconcile, manage vendors/expenses, approve expenses, export and configure gateways.

## PROMPT 41 — Indian GST invoicing and business compliance

**Purpose:** Provide India-specific tax-document and compliance support connected to Finance, Invoices, Customers and Vendors.

**Organization GST settings:** Legal/trade name, GSTIN, PAN where relevant, address, state/code, registration type/status, place-of-supply settings, tax preferences, invoice series and financial year. Support multiple GST registrations only when applicable and map them accurately to business locations.

**Customer/vendor tax profile:** GSTIN, legal/trade name, billing/shipping address, state code, registration type, place of supply and validation status. Use authorized validation services where configured; do not label unverified data as verified.

**GST invoices:** Invoice/date/due date, seller/buyer details, GSTIN, place of supply, HSN/SAC, descriptions, quantity, rate, discount, taxable value, CGST/SGST/IGST, applicable cess, total tax, total amount, terms, payment details and branding.

**Tax engine:** Tax-inclusive/exclusive prices, intra/inter-state treatment, rate-wise line items, discounts, rounding, credit/debit notes and configurable effective-dated tax rules. Avoid assuming all transactions receive identical treatment.

**Document types:** Tax invoice, bill of supply where applicable, proforma invoice, credit note, debit note, export invoice and delivery challan where appropriate.

**E-invoice:** Authorized IRP integration where eligible; eligibility validation, submission, IRN, acknowledgement, QR code, cancellation and status/error logs. Never fabricate government-issued IRNs or QR codes.

**E-way bill:** Authorized integration where applicable; Part A/B data, transporter/vehicle, distance, generation, expiry, updates and cancellation/status.

**Reports:** Sales/purchase registers, tax summaries, HSN/SAC summary, tax-rate reports, credit/debit notes and accounting exports. Do not claim internal exports are official filings.

**Compliance calendar:** Configurable GST/TDS-related dates, licence/contract/document expiry, owner, reminders and completion status. Due dates and statutory values must be maintained from verified current rules.

**Document vault:** Secure GST certificates, licences, agreements, invoices and audit documents with expiry, versioning and permissions.

**Permissions:** View GST, create/edit/finalize/cancel eligible invoices, notes, access reports, submit e-invoices, generate e-way bills and manage tax settings.

## PROMPT 42 — Customer support, help desk, SLA and customer success

**Purpose:** Expand ticketing into a complete customer service and retention system.

**Ticket intake:** Manual, WhatsApp, email, website form/live chat, customer portal and authorized API.

**Ticket fields:** Ticket number, subject, description, customer/contact/company, related deal/project, category, priority, status, team, owner, branch, dates, attachments and internal/customer-visible content.

**Lifecycle:** New, open, in progress, waiting for customer/internal team, escalated, resolved, closed and reopened; allow configurable statuses and maintain transition history.

**Team operations:** Assign/transfer, internal notes, public replies, mentions, attachments, customer context, bulk actions and supervisor view. Clearly distinguish private notes from customer messages.

**SLA:** First response and resolution targets, priority-based rules, working hours, holidays, pause conditions, escalation, countdown and breach reports.

**Customer success:** CSAT/NPS, feedback, onboarding status, health indicators, renewal dates, customer success owner, check-in tasks and churn-risk indicators with transparent contributing factors.

**Warranty/AMC:** Purchase reference, coverage, start/end dates, agreement, service history, preventive maintenance and renewal reminders.

**Knowledge base:** Articles, FAQ, categories, search, draft/approval/published state, audience visibility, attachments, versioning and approved AI-assisted answers.

**Reports:** New/open/overdue tickets, SLA breach, first response, resolution, agent performance, customer feedback and trends.

**Automation:** Assignment, acknowledgement, priority, SLA alerts, escalation, pending reminders, reopening and follow-up.

**Permissions:** View all/assigned/team tickets, reply, internal notes, assignment, escalation, close/delete, SLA, knowledge base, feedback, customer health and exports.

## PROMPT 43 — Advanced HR, attendance, leave and payroll support

**Purpose:** Expand existing HR into workforce administration while protecting sensitive employee information.

**Employee records:** Employee ID, name, official contacts, department, designation, reporting manager, branch, employment type/status, joining date, work location and controlled documents.

**Organization hierarchy:** Departments, teams, designations, managers, reporting lines, transfers and validation against circular reporting relationships.

**Attendance:** Check-in/out, hours, breaks, late/early, half-day, overtime records, correction requests and approvals.

**Mobile/field attendance:** Mobile check-in, optional GPS verification/geofence and optional selfie verification with transparency and purpose limitation. No hidden continuous tracking.

**Shifts/holidays:** Multiple shifts, rotations, assignments, weekly offs and branch-specific holiday calendars.

**Leave:** Categories, policies, balances, applications, partial days where configured, approval/rejection, holiday checks and leave reports.

**Payroll records:** Salary components, allowances, deductions, incentives, overtime, reimbursements, payroll period, payslip, approval and payment status. Support Indian PF/ESI/professional tax/TDS fields where applicable using verified, effective-dated and configurable rules. Do not hardcode outdated statutory rates.

**Documents:** Contracts, appointment letters, identity/qualification documents, certificates and acknowledgements with expiry, private storage and access controls.

**Productivity:** Targets, assigned work, completion, sales performance, timesheets, expense claims, reviews, training and manager feedback. Do not equate raw activity counts with true performance.

**Permissions:** Separate employee profile, salary, payroll, attendance, leave, documents, expenses, reviews and reports. Managers see only their authorized reporting scope; employees access their own records unless granted more.

## PROMPT 44 — Advanced project, task, time and delivery management

**Purpose:** Expand core projects into end-to-end service delivery management.

**Project creation:** Manual, won deal, approved quotation, customer request or authorized API. Store customer, deal, manager, team, branch, start/deadline, priority, budget, status, milestones and description.

**Lifecycle:** Draft, planned, active, on hold, at risk, completed, cancelled and archived; maintain history.

**Tasks:** Subtasks, assignees, dependencies, priority, dates, labels, status, comments, checklists, attachments, estimates and actual effort.

**Views:** List, Kanban, calendar, timeline, Gantt, milestones, my tasks and team workload.

**Milestones and client approval:** Deliverables, dates, internal review, customer review, approval/rejection, revisions and history.

**Time tracking:** Manual/timer entries, timesheets, billable/non-billable hours, approval and protection against invalid overlapping/duplicate entries.

**Files:** Deliverables, client assets, agreements, revisions, previews, internal/customer-visible classification and secure access.

**Project finance:** Budget, approved costs, actual expenses, billable hours, linked invoices, collections and profitability estimates. Reuse existing finance records.

**Automation:** Won deal to project draft; approval to create tasks; overdue alerts; milestone approval; completion to customer update or invoice workflow with appropriate approval.

**Reports:** Progress, delays, workload, task completion, time, budget versus actual and profitability.

**Permissions:** View assigned/all projects, create/edit/archive, assign team, view financials, timesheets, approve milestones, share documents and reports.

## PROMPT 45 — Multi-branch and multi-location management

**Purpose:** Allow one organization to operate multiple locations with centralized oversight and scoped access.

**Branch setup:** Name, address, manager, employees, departments, operating hours, workdays, timezone and settings.

**Branch operations:** Link branch to leads, customer interactions, deals, projects, tickets, appointments, employees, expenses, sales, payments and inventory where appropriate. Shared customer master records should not be duplicated merely because a customer interacts with multiple branches.

**Transfers:** Lead, employee, ticket, appointment, project and inventory transfer where supported; require approval where configured and retain previous/new branch, actor, date, reason and history.

**Branch dashboard:** Leads, customers, deals, sales, collections, outstanding, employee performance, tickets, appointments, projects, expenses and targets.

**Owner dashboard:** Consolidated and comparative branch reporting with filters for dates, teams and departments.

**Branch configuration:** Working hours, holidays, routing, booking availability, channel mapping, targets and local preferences.

**Security:** Enforce branch scoping in APIs, reports, aggregates, search, exports, WebSockets, notifications and attachments. Cross-branch access is explicitly granted, never assumed.

**Permissions:** Manage branches, view own/all authorized branches, create/edit/deactivate, assign employees, transfer records, view branch/consolidated reports and manage branch settings.

## PROMPT 46 — Customer portal and appointment booking

**Purpose:** Provide a secure self-service area for customers and a configurable appointment engine.

**Authentication:** Customer invitation/self-registration if enabled, email/password, supported OTP, verification, password reset, session controls, account disable and login history.

**Customer dashboard:** Profile, enquiries, shared quotations, invoices, payments, projects, approved milestones, tickets, appointments, documents and notifications. Never expose internal notes, internal costs or private employee discussions.

**Profile:** Customer updates allowed fields, billing information and notification preferences; sensitive changes may require verification/approval.

**Quotations:** View/download, accept, reject, request changes, view expiry/revision and submit comments with timestamps.

**Invoices/payments:** View/download invoices, due balances, partial payments and receipts; use secure payment links and provider-verified statuses; view permitted refund information.

**Projects:** Show customer-visible progress, milestones, deliverables, documents and approval requests only.

**Tickets:** Create tickets, upload attachments, view/reply, track status and submit feedback.

**Appointments:** Service, branch, employee, duration, availability, time slots, holidays, booking capacity, confirmation, reschedule, cancellation and history. Prevent double booking through transactional availability checks.

**Public booking page:** Organization branding, services, descriptions, contact, required fields, branch/staff choices, availability and shareable URL; validate all submissions server-side.

**Reminders/payments:** Optional deposit/full payment and configured WhatsApp/email/SMS reminders.

**Permissions:** Independently configure portal enablement, invoice/document/project/ticket visibility, appointments, profile editing and booking page. Enforce strict customer-to-record ownership checks.

## PROMPT 47 — Product, inventory, warehouse and vendor management

**Purpose:** Provide an optional inventory/procurement module for businesses that handle physical products.

**Product catalogue:** Name, SKU, barcode, category, brand, description, images, unit, HSN, tax, cost/selling price, status, variants and stock tracking. Support import/export and search.

**Stock ledger:** Opening, available, reserved, incoming, damaged and returned quantities; adjustments, transfers, movements, reorder levels and valuation. Do not overwrite quantities without a movement record.

**Warehouses:** Multiple warehouses, address, manager, stock-by-location, transfers, dispatch, receipt and damage handling.

**Procurement:** Purchase request, approval, vendor selection, purchase order, approval, partial/full goods receipt, quality information, stock update, bill link and vendor payment tracking.

**Vendors:** Company/contact, phone/email, address, GSTIN, permitted PAN/bank details, supplied products, terms, outstanding, history and documents. Restrict bank details separately.

**Operations:** Stock issue/receipt/transfer/adjustment/return/reservation/release/count/reconciliation. Use database transactions and concurrency safety.

**Sales integration:** Add products to quotation/order, check availability, reserve stock, invoice, fulfil and deduct stock according to configured policy.

**Returns:** Request, reason, condition, inspection, restock approval, damaged goods, replacement, refund and credit note link. Do not put damaged stock back into available stock automatically.

**Reports:** Current stock, low stock, movement, warehouse, purchase, vendor outstanding, valuation, returns and product profitability.

**Permissions:** Products, stock view/adjustment/transfer, warehouse, purchasing, purchase approval, vendors, bank details, costs, returns and reports.

## PROMPT 48 — Visual no-code workflow builder and automation engine

**Purpose:** Allow authorized business users to build real automated processes without coding.

**Builder UI:** Drag/drop trigger, condition, delay, action, approval and branch nodes; AND/OR logic; configuration panel; validation; preview; draft/test/publish/pause/resume; version history.

**Triggers:** New/updated/assigned/qualified/converted lead; customer/contact changes; new deal/stage/won/lost; quotation approval/expiry; payment received/failed; invoice overdue; WhatsApp/email reply; missed call; ticket lifecycle; project/task/milestone; attendance/leave/document expiry; appointment booking/reschedule/cancel.

**Conditions:** Source, score, status, value, date, branch, department, employee, customer, payment, product, tags and custom fields; support comparison and empty/not-empty operators.

**Actions:** Create task, assign employee, update permitted record, add tag, change status, create ticket/appointment, send approved WhatsApp/email/SMS, notify, call authorized webhook, request approval, create project draft and generate report.

**Delay/scheduling:** Minutes/hours/days, specified time, working hours, holidays, timezone, wait for condition/reply and reminder-before-due.

**Approvals:** Discounts, refunds, expenses, sensitive updates, campaigns and transfers; multi-level rules, approver roles, expiry, rejection reason and history.

**Execution engine:** Persistent instances, queues, idempotency, retries, timeout, failure handling, dead-letter/failure state, pause/resume, duplicate-event prevention, loop protection, concurrency safety and usage limits.

**Monitoring:** Active/paused workflows, successful/failed/pending runs, duration, retry counts and failure reasons; inspect logs only with permission.

**Test mode:** Clearly identify test records/events and prevent real bulk sends or external financial actions unless explicitly confirmed.

**Security:** Execute in an authorized context, revalidate tenant, subscription, RBAC and record scope at execution time. Workflow configuration must never become a privilege-escalation route.

**Permissions:** View/create/edit/delete/test/publish/pause/execute/approve workflows, view logs and manage usage.

## PROMPT 49 — Advanced analytics, AI business intelligence, India-first experience and field sales

**Purpose:** Create role-aware business intelligence and improve usability for Indian businesses and mobile field teams.

**Owner dashboard:** Leads, customers, deals, pipeline, won revenue, collections, outstanding, invoices, expenses, retention, branch/employee performance, campaigns, tickets and projects. Distinguish actuals from forecasts.

**Dashboard customization:** Add/remove/rearrange/resize widgets, set filters/date/branch/department/employee and save personal layouts, subject to permissions.

**Custom report builder:** Choose module/fields/authorized relationships, filters, grouping, sort, date range, chart/table, saved report, schedule and PDF/Excel/CSV export. Apply field and row-level scopes.

**Sales analytics:** Lead/deal conversion, deal size, pipeline, forecast, cycle length, win/loss, employee/team/branch/product revenue.

**Marketing analytics:** Source, campaign, CPL, CAC, conversions, attribution, revenue, channel and ROI. Mark estimates and missing provider attribution clearly.

**Finance analytics:** Revenue, collections, receivables, payables, ageing, expenses, refunds, payment trends and branch comparisons, with restricted salary/bank/tax data.

**AI insights:** Explain supported trend changes, missed follow-ups, overdue payments, branch target gaps, approaching deals and support SLA changes. Show supporting data, date range, authorized records, explanation and suggested action; label uncertainty.

**Alerts:** Configurable lead conversion, overdue payment, missed follow-up, ticket backlog, sales/expense, branch target and usage alerts.

**India-first experience:** INR formatting, Indian digit grouping, Asia/Kolkata default timezone, date presentation, Indian phone validation, GST-aware presentation, Hindi/English interface, Hinglish AI, regional-language architecture, holiday settings and UPI-oriented flows.

**Mobile/PWA:** Installable PWA, responsive navigation, push where supported, mobile uploads/camera where permitted, voice input, low-data mode and offline drafts. Never treat unsynchronized offline financial activity as completed.

**Field sales:** Daily visit plan, booking, route planning through authorized map provider, transparent location check-in/out, visit notes/outcome, follow-up tasks, travel expenses and reports. No hidden continuous tracking.

**Permissions:** Dashboard/report visibility, finance/employee/branch analytics, exports, AI insights, alert management, field sales and location access.

## PROMPT 50 — SaaS Super Admin, subscriptions, industry templates and final production audit

**Purpose:** Prepare the existing CRM for commercial multi-tenant SaaS operation.

**Multi-tenancy:** Audit existing tenant model before changes. Implement organization registration, onboarding, owner, members, organization settings, branding, timezone, status, suspension/reactivation and strict tenant resolution. Never trust unverified organization IDs from the frontend.

**Onboarding:** Register organization, create owner, verify contact, choose business type/timezone, select plan, configure profile, invite employees, assign roles and connect optional integrations.

**Separate platform Super Admin:** Registered organizations, status, subscription, plans, add-ons, feature catalogue, revenue, payment failures, renewals, usage, integrations, support, system health, announcements and audit logs. Platform Super Admin must be distinct from tenant Organization Admin.

**Plans:** Monthly/annual pricing, currency, trial duration, modules, seats, storage, API, workflows, AI credits, calling minutes, messaging allowances, add-on prices and plan status. Support free trial and custom enterprise plans.

**Feature entitlement engine:** A feature is usable only if organization subscription permits it, subscription status is valid, user permission is granted, record scope permits access and usage limit is not exceeded. Subscription access and employee RBAC are separate controls.

**Billing:** Monthly/yearly, trial expiry, upgrade/downgrade, add-ons, renewal, cancellation, grace period, failure retry, suspension/reactivation, refunds and subscription history. Verify payment provider webhooks and use idempotency.

**Usage:** Track users, storage, API calls, AI credits/requests, calling minutes, WhatsApp use, campaigns and workflow executions. Provide warnings and accurate provider data; never invent charges.

**SaaS invoices:** Customer billing identity, GSTIN where applicable, plan, period, amount, taxes, discounts, coupon, payment state and PDF, reusing the tax/finance engine.

**Industry templates:** Real Estate, Education, Healthcare/Clinics, IT Agency, Digital Marketing, Automobile, Insurance, Travel, Manufacturing, Retail, Consultancy and Coaching. Each template can configure module presets, fields, pipelines, widgets, automation recipes, notifications, reports, forms and permission presets. Templates must not overwrite customer records or unexpectedly broaden access.

**Security:** Tenant boundaries, separate admin roles, optional MFA, sessions, login/audit logs, secure secrets, API authentication, rate limits, encrypted credentials, private files, backup/restore, retention, privacy, webhook verification and controlled support access. Ordinary support employees must not have unrestricted tenant data access.

**Final integration tests:**

1. Website enquiry → lead → assignment → WhatsApp → follow-up → quotation → approval → payment → invoice → project → customer portal.
2. Complaint → ticket → assignment → SLA → escalation → resolution → feedback.
3. Organization onboarding → subscription → employee → role → permissions → feature use → usage tracking → renewal.
4. Campaign → audience → approval → delivery → lead → deal → revenue attribution.

**Final security tests:** Cross-tenant, cross-branch, restricted employee/finance records, portal ownership, unauthorized export/download, API manipulation, expired subscription, entitlement bypass, role changes, session revocation, webhook replay, duplicate payment and automation privilege escalation.

**Performance/deployment:** Query/index review, N+1 checks, queue and WebSocket health, cache invalidation, file storage, logs, backup/restore, environment secrets, migrations and rollback plan.

**Final report:** Module completion, partial/missing features, failed tests, security findings, performance issues, database changes, API documentation, integrations, subscription configuration and deployment checklist. Do not declare production-ready while critical security, tenant, payment or data-integrity issues remain unresolved.

---

# 5. Cross-module role and permission matrix — recommended starting point

This is a suggested default, not a fixed permission assignment. Organization administrators must be able to customize roles and data scopes.

| Role | Typical access | Typical restrictions |
|---|---|---|
| Platform Super Admin | Platform organizations, plans, subscription and system administration | Must remain separate from ordinary tenant access; tenant data access requires controlled, audited support process |
| Organization Owner | All organization modules and settings | Cannot bypass platform-level rules or applicable security boundaries |
| Organization Admin | Configured organization-wide administration | Access to highly sensitive records can still be separately restricted |
| Branch Manager | Branch operations, staff, sales and branch reports | Other branches are hidden unless explicitly granted |
| Department Manager | Department records, team work and department reports | Other departments and sensitive HR/finance data remain restricted |
| Sales Manager | Team leads, deals, pipeline, sales reports and follow-ups | No automatic access to payroll, private HR or unrestricted finance |
| Sales Executive | Own/assigned leads, contacts, deals, activities and follow-ups | Cannot browse all leads, export full database or approve restricted discounts without permission |
| Accountant | Authorized invoices, payments, receivables, payables and tax records | No automatic access to HR salary files or unrelated customer conversations |
| HR Manager | Employee, attendance, leave and payroll data within authorized scope | Sensitive data and salary fields still require appropriate permissions |
| HR Executive | Assigned HR operational records | Restricted payroll and sensitive employee information unless granted |
| Marketing Manager | Campaigns, audiences, templates, approvals and campaign reports | Cannot access unrelated financial/HR records by default |
| Marketing Executive | Draft campaigns and assigned audience/tasks | Launching campaigns and exporting broad audiences may require approval |
| Support Manager | Support teams, tickets, SLA and support reports | Financial and HR details remain permission-controlled |
| Support Agent | Assigned/team conversations and tickets | Internal finance, private HR, unrestricted exports and other teams are restricted |
| Project Manager | Assigned projects, team tasks, milestones and delivery reports | Project financials and other projects require separate access |
| Employee | Own tasks, profile, attendance and permitted records | Cannot browse organization-wide private data |
| Customer Portal User | Only records explicitly shared with that customer account | No access to internal notes, other customers or internal business operations |

## Permission enforcement rule

For every request, the backend must check:

1. Is the user authenticated and active?
2. Is the user an active member of this organization?
3. Is the requested feature included in the organization's subscription?
4. Does the user's role have the module permission?
5. Does the user have the requested action permission?
6. Is the requested record inside the user's allowed data scope?
7. Is the record in an authorized branch/department/team where applicable?
8. Is the requested field, attachment, report or action separately restricted?

If any required check fails, deny the operation. Frontend menu hiding is not sufficient security.

---

# 6. Core end-to-end business journeys

These journeys should work across multiple modules and are essential acceptance tests.

## Journey A — Lead to customer and project

1. A prospect submits a website/advertising enquiry.
2. CRM validates the source and creates or matches the lead.
3. Lead is assigned using branch/team rules.
4. Sales employee receives a notification and follow-up task.
5. Employee contacts the prospect through an authorized channel.
6. Communication history is added to the customer timeline.
7. Lead is qualified and converted to contact/company/deal without duplicate master records.
8. Employee creates quotation and requests approval if required.
9. Customer receives and accepts the approved quotation.
10. Invoice is generated through the existing finance/tax system.
11. Customer pays through a connected gateway or recorded payment method.
12. Verified payment updates invoice balance.
13. A project is created from the won deal/approved quotation.
14. Customer sees only shared project progress through the portal.
15. Delivery, support and feedback are recorded.

## Journey B — Customer complaint to resolution

1. Customer messages through a connected channel or portal.
2. CRM matches the customer and creates a ticket where configured.
3. Ticket is assigned by category/branch/availability.
4. SLA timer starts based on configured working hours.
5. Agent replies publicly and adds private notes separately.
6. Overdue ticket escalates to the appropriate manager.
7. Ticket is resolved and customer is notified.
8. Customer feedback is captured and reports are updated.

## Journey C — Subscription and organization access

1. New organization registers.
2. Organization owner completes onboarding and selects plan.
3. Payment/trial status is verified.
4. Feature entitlements are assigned.
5. Owner invites employees and creates custom roles.
6. Employees see only authorized modules and records.
7. Usage is tracked against plan limits.
8. Upgrade, renewal, payment failure or suspension changes entitlements according to policy.
9. Role permissions remain distinct from subscription entitlements.

## Journey D — Campaign to measurable revenue

1. Marketing user creates a campaign.
2. Audience is built using permitted segments and consent.
3. Campaign is reviewed and approved where required.
4. Messages are sent through connected provider queues.
5. Delivery/reply events are recorded from provider callbacks.
6. Replies generate or update leads using deduplication rules.
7. Sales team works leads and creates deals.
8. Won deals link back to source/campaign attribution.
9. Reports compare costs, leads, conversions and attributed revenue with attribution limitations clearly stated.

---

# 7. Global definition of done

A prompt/module is **not complete** merely because a page or menu item exists. Mark a feature complete only when all applicable items are satisfied:

- [ ] Existing code inspected and reusable functionality identified.
- [ ] Frontend screens and interactions implemented.
- [ ] Backend APIs and business logic implemented.
- [ ] Database schema and relationships implemented safely.
- [ ] Server-side validation implemented.
- [ ] Authentication, RBAC, tenant and data-scope checks implemented.
- [ ] Subscription feature entitlement checked where applicable.
- [ ] Real provider integration connected or clearly marked as not configured.
- [ ] Loading, empty, success, error and unauthorized states handled.
- [ ] Search, filters, sorting and pagination tested where relevant.
- [ ] Notifications/activity history implemented where relevant.
- [ ] File and data export access protected where relevant.
- [ ] Unit and backend feature tests pass.
- [ ] Frontend/API integration tests pass.
- [ ] End-to-end user journey passes.
- [ ] Existing modules/regression tests still pass.
- [ ] Documentation and implementation status updated.
- [ ] Known limitations and external provider dependencies documented.

---

# 8. Recommended implementation order

| Stage | Prompts | Focus |
|---|---:|---|
| Core foundation | 1–8 | Architecture, authentication, organization, users, teams, dashboard, notifications, activity history |
| Core CRM | 9–17 | Contacts, companies, leads, sources, pipelines, deals, activities, tasks and meetings |
| Operations | 18–30 | Projects, documents, support, finance, invoices, payments, expenses, HR, attendance, reports, settings, import/export and integrations |
| Audit and security | 31–32 | Gap audit and centralized RBAC/data scopes |
| Communication and AI | 33–38 | Omnichannel, WhatsApp, AI, advanced leads, telephony and marketing |
| Commercial operations | 39–42 | Sales/quotations, finance, GST and customer support success |
| Workforce and delivery | 43–47 | HR, projects, branches, customer portal, appointments and inventory |
| Automation and SaaS | 48–50 | Workflow builder, analytics, India-first experience, SaaS, Super Admin and final audit |

## Execution rule

Run prompts sequentially. Start with an audit of the real repository. Complete and test RBAC before exposing advanced modules to employees. After each prompt, update the implementation checklist and report:

- What was already present and reused.
- What was newly implemented.
- Files and database migrations changed.
- APIs added or modified.
- Permissions registered.
- Tests executed and results.
- Remaining issues and external setup required.

Do not submit all prompts to an autonomous coding queue without dependency control and verification. A later prompt must not assume a previous module is complete unless its implementation status and tests confirm that it is.

---

**End of CRM Master Specification — 50 Prompt Scope**
