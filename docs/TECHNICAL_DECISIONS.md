# Architecture Decision Records (ADR) & Technical Decisions

**Date**: November 2026  
**Context**: Migration of existing Enterprise CRM to a production-grade multi-tenant AI CRM SaaS.

---

## ADR 01: Retain Existing React 19 Frontend & Migrate Backend Progressively

### Context
The existing frontend is built with React 19, Vite 6, Tailwind CSS 4, and Zustand. The UI components (tables, modals, KPI charts, dark/light modes) are well-crafted, responsive, and closely matched to enterprise CRM domain requirements.

### Decision
**Retain the entire existing React frontend.** Do not perform a rewrite. Maintain all component hierarchies and Zustand store structure. Progressively route API calls from the local Express server to the Laravel 12 backend using Vite's development proxy (`/api` -> `http://127.0.0.1:8000`).

### Consequences
- **Positive**: Zero wasted UI effort; fast time-to-market; existing workflows and user visual expectations remain unbroken.
- **Negative**: Requires careful testing to ensure JSON payload schemas returned by Laravel match what Zustand expects.

---

## ADR 02: Laravel 12 REST API as Production Backend

### Context
The current backend is a prototype Express script (`server.ts`) persisting to a single JSON file (`crm_db.json`). For a multi-tenant enterprise SaaS supporting payment processing, background AI jobs, role permissions, and compliance, a robust framework is required.

### Decision
**Adopt Laravel 12 with PHP 8.2+ as the core API framework.**

### Rationale
1. **Batteries Included**: Out-of-the-box support for database migrations, seeders, Eloquent ORM, form validation, job queues, task scheduling, and mailers.
2. **Enterprise Security**: Built-in CSRF protection, secure password hashing (Argon2id/Bcrypt), rate limiting, and SQL injection prevention via PDO parameter binding.
3. **Ecosystem Strength**: Seamless integration with Laravel Sanctum (auth), Spatie Laravel Permission (RBAC), and Laravel Horizon (Redis queue monitoring).

---

## ADR 03: Multi-Tenancy Architecture (Shared Database with Tenant Isolation)

### Context
The SaaS needs to support multiple customer organizations (tenants), ensuring complete data isolation while keeping server resource consumption and operational maintenance manageable.

### Decision
**Implement a Single Database Multi-Tenant Architecture with a mandatory `tenant_id` foreign key on all tenant-scoped tables, enforced by an Eloquent Global Scope (`TenantScope`).**

### Rationale
- **Resource Efficiency**: A single MySQL database with indexes on `(tenant_id, id)` easily scales to thousands of small-to-mid organizations without the connection overhead of thousands of distinct database pools.
- **Maintenance**: Database migrations and schema updates can be executed in a single atomic transaction rather than running sequential migrations across hundreds of tenant databases.
- **Security Safeguard**: The `TenantScope` automatically injects `WHERE tenant_id = <current_tenant>` on all queries, preventing accidental cross-tenant data leaks at the ORM level.

---

## ADR 04: Authentication via Laravel Sanctum & Spatie Permission

### Context
Currently, the prototype uses an unhashed token string stored in `localStorage` and a hardcoded 2FA PIN (`123456`).

### Decision
1. **Laravel Sanctum**: Issue cryptographically secure Bearer tokens with configurable expiration and token abilities.
2. **Two-Factor Authentication**: Integrate Google Authenticator / TOTP standards (via `pragmarx/google2fa` or Laravel Fortify) with backup recovery codes.
3. **Spatie Laravel Permission**: Implement Spatie's role and permission tables (`roles`, `permissions`, `model_has_roles`, `role_has_permissions`) with cache tags in Redis for sub-millisecond authorization checks.

---

## ADR 05: Asynchronous AI Jobs & Queue Architecture (Redis + Laravel Horizon)

### Context
Calls to Google Gemini API (lead scoring, support ticket reply drafts) take between 1.5 and 5 seconds. In the current prototype, these calls are made synchronously inside Express request handlers. If multiple users request AI generation simultaneously, request timeouts and thread blocking occur.

### Decision
**Offload all AI analysis, report generation, and third-party webhook dispatches to asynchronous background jobs managed by Redis and Laravel Horizon.**

### Architecture:
1. User clicks "AI Score & Insights".
2. Frontend dispatches `POST /api/v1/crm/ai/analyze-lead`.
3. Laravel pushes `AnalyzeLeadWithGeminiJob` to the Redis `ai-high-priority` queue and immediately returns `{ status: "processing", job_id: "..." }`.
4. A Horizon worker executes the Gemini API call, stores the generated markdown analysis in `leads.ai_analysis_cache`, and broadcasts a `LeadAnalyzedEvent` over WebSockets (or updates the record for the next poll).
5. Frontend renders the finished insight card without blocking the browser.

---

## ADR 06: Real-Time Updates via WebSockets (Laravel Reverb)

### Context
The existing app simulates real-time activity logs and notifications by polling or slicing local arrays in memory. Future features require real-time support chat, WhatsApp incoming messages, and live task status changes.

### Decision
**Integrate Laravel Reverb (first-party WebSocket server for Laravel 11/12) coupled with Laravel Echo on the React frontend.**

### Benefits
- Zero reliance on external paid services (Pusher/Ably) for local and self-hosted deployments.
- Instant push notifications for incoming support tickets, leave approvals, and WhatsApp conversations.

---

## ADR 07: Client-Side vs Server-Side Export Architecture

### Context
`ModuleTable.tsx` currently invokes `exportUtils.ts` which uses `jsPDF` on the client side to render PDF documents.

### Decision
**Hybrid Export Strategy:**
1. **Quick Table Exports (Client-Side)**: Retain `jsPDF` and client-side CSV generation for immediate on-screen tabular data (fast, zero server load).
2. **Formal Business Documents (Server-Side)**: For formal Invoices, Quotations, and Tax Statements, implement Laravel server-side PDF rendering (e.g. `barryvdh/laravel-dompdf` or `spatie/browsershot`) to guarantee pixel-perfect print typography, legal compliance, and server-side PDF archiving.

---

## ADR 08: Frontend Routing Migration (Component State to React Router 7)

### Context
`react-router-dom` v7 is installed in `frontend/package.json`, but `App.tsx` switches views using an `activeModule` state string. This prevents browser forward/back buttons from working and disallows deep-linking (e.g. bookmarks to `/leads?status=qualified` or `/deals/12`).

### Decision
**Migrate `App.tsx` to a declarative React Router 7 setup (`<BrowserRouter>`, `<Routes>`, `<Route>`):**
- `/` -> Redirect to `/dashboard`
- `/login`, `/register`, `/forgot-password`, `/2fa` -> Auth routes
- `/dashboard` -> Dashboard view
- `/leads`, `/deals`, `/companies`, `/customers`, `/contacts` -> CRM routes
- `/products`, `/quotations`, `/invoices` -> Sales & Finance routes
- `/employees`, `/attendance`, `/leaves` -> HR routes
- `/tickets` -> Support Helpdesk
- `/roles`, `/audit`, `/settings` -> Administration routes
- Deep routes: `/deals/:id`, `/invoices/:id` for dedicated detail views.
