# Feature Inventory & Status Matrix

**Date**: November 2026  
**Status Key**:
- ✅ **Working**: Fully functional with working frontend state and backend JSON persistence.
- 🟡 **Partially working**: Functional UI with minor limitations, mock responses, or missing validation.
- 🎨 **Frontend only**: Complete UI rendered on the client, but lacks persistent backend endpoints or database logic.
- 📦 **Dummy/static**: Hardcoded static data or mocked simulations.
- 🔴 **Broken**: UI element exists or triggers code that fails, crashes, or produces empty views.
- ⚪ **Not implemented**: Control, route, or feature referenced in requirements or types, but lacking frontend UI/implementation.

---

## 1. Authentication & Session Management

| Feature | Screen / Component | Current Implementation | Status | Notes / Limitations |
|---|---|---|---|---|
| **User Sign-In** | `App.tsx` | Form submitting to `/api/auth/login` | ✅ Working | Authenticates against `crm_db.json`. Saves token to `localStorage`. |
| **Two-Factor Authentication (2FA)** | `App.tsx` | Code prompt submitting to `/api/auth/verify-2fa` | 🟡 Partially working | Only checks hardcoded code `123456`. |
| **User Registration** | `App.tsx` | Form creating user and company | ✅ Working | Appends new user with `passwordHash` to `crm_db.json`. |
| **Forgot Password** | `App.tsx` | Form submitting to `/api/auth/forgot-password` | 📦 Dummy/static | Returns simulated success message without email dispatch. |
| **Reset Password** | None | Backend endpoint `/api/auth/reset-password` exists | ⚪ Not implemented | No frontend UI screen or route exists for entering reset token and new password. |
| **Session Logout** | `Layout.tsx` | Clears `localStorage` and resets store | ✅ Working | Clears `crm_token` and `crm_user` immediately. |
| **Profile Drawer View** | `Layout.tsx` | Slideout panel displaying user details | ✅ Working | Displays avatar, role badge, email, and 2FA status indicator. |
| **Profile Edit Form** | `Layout.tsx` | `crmStore.updateProfile` exists in store | ⚪ Not implemented | Drawer displays read-only data; input fields to edit details are absent. |
| **Change Password** | `Layout.tsx` | `crmStore.changePassword` exists in store | ⚪ Not implemented | No UI form inside profile drawer to submit password change. |

---

## 2. Navigation, Shell & System Controls

| Feature | Screen / Component | Current Implementation | Status | Notes / Limitations |
|---|---|---|---|---|
| **Responsive Sidebar** | `Layout.tsx` | Collapsible sidebar with mobile overlay | ✅ Working | Smooth width transitions, hamburger toggle, and backdrop on mobile. |
| **Sidebar Navigation Links** | `Layout.tsx` | 17 navigation menu items | ✅ Working | Switches active module via Zustand and `App.tsx`. |
| **Theme Toggle** | `Layout.tsx` | Dark/light mode switcher with persistence | ✅ Working | Toggles `.dark` class on root HTML element and saves to `localStorage`. |
| **Live UTC Clock** | `Layout.tsx` | Header digital clock updating every second | ✅ Working | High precision UTC time readout. |
| **Quick Clock-In** | `Layout.tsx` | Header button posting to `/api/crm/attendance/checkin` | ✅ Working | Adds or updates attendance entry for current user in `crm_db.json`. |
| **Quick Clock-Out** | `Layout.tsx` | Header button posting to `/api/crm/attendance/checkout` | ✅ Working | Sets `checkOut` time for today's attendance record. |
| **Notifications Dropdown** | `Layout.tsx` | Header bell icon with badge and popover | 🟡 Partially working | Uses latest 5 activity logs as notifications; mark-as-read updates store. |
| **Global Toast Alerts** | `App.tsx` / `Layout.tsx` | Fixed overlay displaying success/error/info alerts | ✅ Working | Auto-dismisses, custom remove buttons, animated slide-in. |

---

## 3. Dashboard & Analytics

| Feature | Screen / Component | Current Implementation | Status | Notes / Limitations |
|---|---|---|---|---|
| **KPI Metric Cards (4 cards)** | `ModulesView.tsx` | Revenue, Active Pipeline, Leads, Tickets | ✅ Working | Calculated dynamically from current module data. |
| **Monthly Revenue Chart** | `ModulesView.tsx` | Recharts `AreaChart` with gradient fill | ✅ Working | Responsive container, tooltip, currency formatting. |
| **Lead Sources Chart** | `ModulesView.tsx` | Recharts `PieChart` with color legend | ✅ Working | Renders breakdown of leads by acquisition channel. |
| **Pipeline Stage Bar Chart** | `ModulesView.tsx` | Recharts `BarChart` for deal stages | ✅ Working | Shows count of deals per pipeline stage. |
| **Recent Deals Quick Table** | `ModulesView.tsx` | Mini table with deal status badges | ✅ Working | Displays top 5 recent deals with direct links. |
| **Embedded DB Exporter** | `ModulesView.tsx` | Tabs for ERD, migrations, seeders, controllers | ✅ Working | Renders code blocks with copy-to-clipboard functionality. |

---

## 4. CRM Core Modules (Leads, Deals, Companies, Customers, Contacts)

| Feature | Screen / Component | Current Implementation | Status | Notes / Limitations |
|---|---|---|---|---|
| **Leads Table View** | `ModulesView.tsx` / `ModuleTable.tsx` | Paginated table with search & filter | ✅ Working | Filter by status (`new`, `contacted`, `qualified`, `lost`). |
| **Leads Kanban Board View** | `ModulesView.tsx` | Kanban columns grouped by lead status | ✅ Working | Visual cards showing lead values and contact details. |
| **Add / Edit Lead Modal** | `ModuleModals.tsx` | Form with name, company, email, phone, value | ✅ Working | Full CRUD support through store and `crm_db.json`. |
| **AI Lead Scorer & Insights** | `ModulesView.tsx` | "AI Score & Insights" modal button | ✅ Working | Calls Gemini API or fallback to generate lead win probability and recommendations. |
| **Deals Pipeline View** | `ModulesView.tsx` | Pipeline columns for 5 stages | ✅ Working | Calculates total value per stage (`qualification` to `won`/`lost`). |
| **Deals Table View** | `ModulesView.tsx` / `ModuleTable.tsx` | Reusable table with company and stage columns | ✅ Working | Search and filter by probability and stage. |
| **Add / Edit Deal Modal** | `ModuleModals.tsx` | Form with deal name, company select, value, probability | ✅ Working | Connects company dropdown to `companies` state. |
| **Companies Table View** | `ModulesView.tsx` / `ModuleTable.tsx` | Reusable table showing industry, size, revenue | ✅ Working | Full search, pagination, and sorting. |
| **Add / Edit Company Modal** | `ModuleModals.tsx` | Form with name, industry, size, revenue, website | ✅ Working | Saves to `companies` collection. |
| **Customers Table View** | `ModulesView.tsx` / `ModuleTable.tsx` | Table showing status, company, assigned agent | ✅ Working | Filter by active/inactive status. |
| **Add / Edit Customer Modal** | `ModuleModals.tsx` | Form with customer name, email, phone, company | ✅ Working | Connects to company lookup. |
| **Contacts Table View** | `ModulesView.tsx` / `ModuleTable.tsx` | Table showing contact name, company, role, email | ✅ Working | Lists contacts with primary badge. |
| **Add / Edit Contact Modal** | `ModuleModals.tsx` | Modal trigger for `contacts` | 🔴 Broken / Incomplete | `ModuleModals.tsx` lacks `module === 'contacts'` form; modal opens empty with only submit button. |

---

## 5. Sales & Finance (Products, Services, Quotations, Invoices, Payments)

| Feature | Screen / Component | Current Implementation | Status | Notes / Limitations |
|---|---|---|---|---|
| **Products Table View** | `ModulesView.tsx` / `ModuleTable.tsx` | Table showing SKU, name, price, category | ✅ Working | Filtering by category. |
| **Add / Edit Product Modal** | `ModuleModals.tsx` | Form with SKU, name, price, description | ✅ Working | Saves to `products` collection. |
| **Services Table View** | `ModulesView.tsx` / `ModuleTable.tsx` | Reusable table with billing cycle | ✅ Working | Viewable via products module tab. |
| **Add / Edit Service Modal** | `ModuleModals.tsx` | Modal trigger for `services` | 🔴 Broken / Incomplete | Lacks dedicated form fields for billing cycle and service tiers. |
| **Quotations Table View** | `ModulesView.tsx` / `ModuleTable.tsx` | Table showing quote number, customer, total | ✅ Working | Displays status badges (`draft`, `sent`, `accepted`). |
| **Add / Edit Quotation Modal**| `ModuleModals.tsx` | Modal trigger for `quotations` | 🔴 Broken / Incomplete | Lacks multi-item line builder and tax calculation inputs. |
| **Invoices Table View** | `ModulesView.tsx` / `ModuleTable.tsx` | Table showing invoice #, customer, total, due date | ✅ Working | Displays status (`draft`, `sent`, `paid`, `overdue`). |
| **Add / Edit Invoice Modal** | `ModuleModals.tsx` | Form with customer select, amount, due date | 🟡 Partially working | Basic total input; lacks dynamic itemized row addition in modal. |
| **Payments Table View** | `ModulesView.tsx` / `ModuleTable.tsx` | Sub-view under Invoices & Payments | ✅ Working | Shows payment method, amount, status. |
| **Record Payment Modal** | `ModuleModals.tsx` | Modal trigger for `payments` | 🔴 Broken / Incomplete | Lacks dedicated payment recording form modal. |

---

## 6. Projects, Tasks & Collaboration

| Feature | Screen / Component | Current Implementation | Status | Notes / Limitations |
|---|---|---|---|---|
| **Projects Table View** | `ModulesView.tsx` / `ModuleTable.tsx` | Table showing project name, customer, progress | ✅ Working | Renders interactive visual progress bar. |
| **Add / Edit Project Modal** | `ModuleModals.tsx` | Form with project name, customer select, dates, progress | ✅ Working | Full CRUD persistence. |
| **Tasks Manager View** | `ModulesView.tsx` / `ModuleTable.tsx` | Table showing title, priority, status, assignee | ✅ Working | Filter by priority (`low`, `medium`, `high`) and status (`todo`, `in_progress`, `done`). |
| **Add / Edit Task Modal** | `ModuleModals.tsx` | Form with title, description, priority, due date | ✅ Working | Full CRUD persistence. |
| **Meetings Calendar View** | `ModulesView.tsx` / `ModuleTable.tsx` | Table showing title, date, time, attendees, meet link | ✅ Working | Shows clickable external Google Meet link. |
| **Schedule Meeting Modal** | `ModuleModals.tsx` | Form with title, date, time, attendees, meet link | ✅ Working | Saves to `meetings` collection. |

---

## 7. HR, Staff & Workforce Operations

| Feature | Screen / Component | Current Implementation | Status | Notes / Limitations |
|---|---|---|---|---|
| **Staff Roster Table** | `ModulesView.tsx` | Tabbed view displaying employees, department, salary | ✅ Working | Filter by department (`Sales`, `Engineering`, etc.). |
| **Add / Edit Employee Modal** | `ModuleModals.tsx` | Form with name, email, department, designation, salary | ✅ Working | Full CRUD persistence. |
| **Attendance Log Table** | `ModulesView.tsx` | Tabbed sub-view showing check-in/out records | ✅ Working | Displays timestamps and attendance status. |
| **Leave Requests Table** | `ModulesView.tsx` | Tabbed sub-view showing leave submissions | ✅ Working | Shows status (`pending`, `approved`, `rejected`) and reasons. |
| **Approve / Reject Leave Action** | `ModulesView.tsx` | Action buttons calling `/api/crm/leaves/:id/approve` | ✅ Working | Updates leave status and triggers activity audit log. |
| **Submit Leave Request Modal** | `ModuleModals.tsx` | Form with employee select, leave type, dates, reason | ✅ Working | Saves new leave request to `leaves` collection. |

---

## 8. Support Helpdesk & AI Automation

| Feature | Screen / Component | Current Implementation | Status | Notes / Limitations |
|---|---|---|---|---|
| **Support Tickets Table** | `ModulesView.tsx` / `ModuleTable.tsx` | Table showing customer, subject, priority, status | ✅ Working | Filter by priority and ticket status. |
| **Create Ticket Modal** | `ModuleModals.tsx` | Form with customer select, subject, priority, description | ✅ Working | Full CRUD persistence. |
| **AI Ticket Auto-Reply Co-Pilot** | `ModulesView.tsx` | "AI Draft Reply" button & modal preview | ✅ Working | Uses Gemini 3.5 Flash or simulated fallback to write professional email drafts. |
| **Copy / Apply AI Draft** | `ModulesView.tsx` | One-click copy draft to clipboard | ✅ Working | Copies generated markdown draft with feedback toast. |

---

## 9. Security, Administration & Data Utilities

| Feature | Screen / Component | Current Implementation | Status | Notes / Limitations |
|---|---|---|---|---|
| **Roles & Permissions Matrix** | `ModulesView.tsx` | Grid of cards displaying 8 user roles & badges | ✅ Working | Read-only presentation of role permissions. |
| **Audit Log Trail** | `ModulesView.tsx` | Table showing IP address, user, action, details | ✅ Working | Auto-populated by server middleware on all mutating requests. |
| **System Settings View** | `ModulesView.tsx` | Form for company name, email, tax rate, currency | ✅ Working | Updates settings in `crm_db.json` and updates global store. |
| **Export to PDF** | `ModuleTable.tsx` / `exportUtils.ts` | Multi-page branded PDF generator via jsPDF | ✅ Working | Includes company header, table columns, date, and page footer. |
| **Export to CSV** | `ModuleTable.tsx` / `exportUtils.ts` | Browser-generated CSV file download | ✅ Working | Encodes columns and values into downloadable `.csv`. |
| **Data Import (JSON)** | `ModuleTable.tsx` | Hidden file input triggering `/api/crm/import` | 🟡 Partially working | Basic JSON array upload; lacks CSV parser, schema mapper, and error row reporting. |
| **Bulk Delete Records** | `ModuleTable.tsx` | Selection checkboxes and bulk delete button | ✅ Working | Calls `/api/crm/:module/bulk-delete` with selected IDs. |
| **Table Column Sorting** | `ModuleTable.tsx` | Header click toggle (`asc` / `desc`) | ✅ Working | Client-side and server query sorting. |
| **Search Debounce** | `ModuleTable.tsx` | 400ms debounce input | ✅ Working | Avoids rapid consecutive API calls during typing. |
