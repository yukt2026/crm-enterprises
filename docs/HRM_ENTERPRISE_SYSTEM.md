# HRM Enterprise Architecture: Employees, Departments, Attendance, Shifts & Leave Management

## 1. Executive Summary

The Human Resource Management (HRM) module in **Enterprise AI CRM SaaS** provides workforce administration directly connected with CRM assignments, tenant permissions, and operational calendars while strictly separating **Employee Profiles** from **User Accounts & Login Identities**.

Key architecture highlights:
- **Employee Directory & Lifecycle**: Auto-generated Employee IDs (`EMP-001`), departmental hierarchy, reporting managers, employment statuses (`active`, `probation`, `suspended`, `terminated`, `resigned`), and employment types (`full_time`, `part_time`, `contract`, `intern`).
- **Organization Structure**: Hierarchical department mapping with Department Heads, parent-child departments, designations, and audited employee transfer history.
- **Attendance & Shift Enforcement**: Timezone-aware check-in/out engine supporting shift grace periods (e.g. 15-minute threshold before marking `late` status with duration tracking), automated work-duration and half-day thresholds, location logging, and tamper-resistant lock states.
- **Correction Workflow**: Disallows direct unauthorized edits on attendance records. Missing or incorrect clock-in/out records require formal correction requests evaluated and authorized by managers or HR.
- **Leave Administration**: Multi-category configurable leave types (`Paid Time Off`, `Sick Leave`, `Casual Leave`, etc.), annual entitlement balances, automatic reservation of `pending_days` upon application, instant deduction upon approval, balance restoration upon cancellation, and audited balance adjustments.
- **HR Dashboards**: Real-time headcount metrics, department breakdown, today's attendance summary (present, late, half-day, on leave, absent), pending approval queues, and upcoming company holidays.

---

## 2. Database Architecture

### `departments`
- `id`: Primary key
- `tenant_id`: Multi-tenant workspace reference
- `name`: Department title (e.g., Engineering, Sales, Operations)
- `code`: Unique short-code (e.g., `ENG`, `SALES`)
- `description`: Scope of operations
- `department_head_id`: Foreign key referencing `employees.id`
- `parent_department_id`: Self-referencing foreign key for nested hierarchy
- `status`: `active` or `archived`
- `timestamps()`, `softDeletes()`

### `shifts`
- `id`: Primary key
- `tenant_id`: Multi-tenant workspace reference
- `name`: Shift label (e.g., Standard Day Shift)
- `code`: Shift identifier
- `start_time`, `end_time`: Shift operational hours
- `grace_period_minutes`: Allowable late margin before status becomes `late`
- `half_day_minutes`: Minimum threshold required for half-day credit
- `full_day_minutes`: Minimum threshold required for full-day credit
- `break_duration_minutes`: Allocated break time
- `working_days`: JSON array of working days (`['Monday', 'Tuesday', ...]`)
- `is_default`: Flag identifying the default tenant shift
- `status`: `active` or `inactive`

### `attendances`
- `id`: Primary key
- `tenant_id`: Multi-tenant reference
- `employee_id`: Foreign key referencing `employees`
- `shift_id`: Foreign key referencing `shifts`
- `date`: Attendance date
- `check_in`, `check_out`: Recorded time stamps
- `break_start`, `break_end`: Break intervals
- `total_work_minutes`: Calculated active duty time
- `total_break_minutes`: Calculated break time
- `status`: `present`, `absent`, `late`, `half_day`, `on_leave`
- `is_late`, `late_minutes`: Lateness indicators
- `is_half_day`: Half-day indicator
- `ip_address`, `location`: Geo-location or work setting (`Office`, `Remote`)
- `notes`: Employee or manager comments
- `is_locked`: Prevents direct tampering once finalized/approved
- `approved_by_user_id`: Authorized manager/HR user reference

### `attendance_corrections`
- `id`: Primary key
- `tenant_id`: Multi-tenant reference
- `employee_id`: Foreign key referencing `employees`
- `attendance_id`: Foreign key referencing `attendances` (nullable if creating for past missing day)
- `date`: Target date
- `requested_check_in`, `requested_check_out`: Corrected times
- `reason`: Justification for adjustment
- `status`: `pending`, `approved`, `rejected`
- `reviewed_by_user_id`: Reviewer reference
- `reviewer_notes`: Manager decision notes
- `reviewed_at`: Approval/rejection timestamp

### `leave_types` & `leave_balances`
- `leave_types`: Category definition (`days_allowed_per_year`, `is_paid`, `requires_attachment`, `carry_forward_days`).
- `leave_balances`: Annual employee entitlement tracking (`year`, `total_entitled`, `taken_days`, `pending_days`, `balance_days`). Unique on `['tenant_id', 'employee_id', 'leave_type_id', 'year']`.
- `leaves`: Leave applications with half-day session choices (`morning`, `afternoon`), date ranges, status (`pending`, `approved`, `rejected`, `cancelled`), manager notes, and attachments.
- `leave_balance_adjustments`: Audit trail for manual balance credits or debits with reviewer ID and reason.

### `employee_transfers`
- `id`: Primary key
- `tenant_id`: Multi-tenant reference
- `employee_id`: Target employee
- `from_department_id`, `to_department_id`: Department movement
- `from_designation`, `to_designation`: Title transition
- `from_manager_id`, `to_manager_id`: Supervisor re-assignment
- `transfer_date`: Effective date
- `reason`: Transfer context
- `transferred_by_user_id`: Admin/HR user executing the move

---

## 3. Security & Permission Guardrails

- **Sensitive Data Masking**: Fields such as `salary` and `emergency_contact` are restricted to `HR`, `Business Admin`, `Business Owner`, and `Platform Super Admin`. Regular employees can only view their own confidential details.
- **Tenant Isolation**: Every database interaction enforces `BelongsToTenant` scope preventing cross-workspace data leakage.
- **Immutability of Finalized Attendance**: Approved attendance records cannot be updated directly; all changes must route through the authorized `AttendanceCorrection` workflow.
- **Statutory Boundary**: Explicitly focuses on human resources, time-tracking, and leave administration without introducing unverified payroll disbursements or simulated tax compliances.

---

## 4. API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/crm/hr/dashboard` | Headcount, department allocation & attendance stats |
| `GET` | `/api/v1/crm/hr/hierarchy` | Organization reporting hierarchy tree |
| `GET` | `/api/v1/crm/employees` | List employees with filters and data masking |
| `POST` | `/api/v1/crm/employees` | Create employee profile & seed leave balances |
| `GET` | `/api/v1/crm/employees/{id}` | Detailed profile with shift, manager & reports |
| `POST` | `/api/v1/crm/employees/{id}/transfer` | Transfer department/manager with audit log |
| `GET` | `/api/v1/crm/departments` | List departments with headcount & manager |
| `POST` | `/api/v1/crm/departments` | Create new department |
| `GET` | `/api/v1/crm/shifts` | List shift definitions and working days |
| `POST` | `/api/v1/crm/shifts` | Create shift schedule |
| `GET` | `/api/v1/crm/shifts/holidays` | Company holidays calendar |
| `POST` | `/api/v1/crm/shifts/holidays` | Create company holiday |
| `GET` | `/api/v1/crm/attendance` | Daily work logs with status & minutes |
| `GET` | `/api/v1/crm/attendance/calendar` | Monthly day-by-day attendance register |
| `POST` | `/api/v1/crm/attendance/checkin` | Check in with grace-period lateness detection |
| `POST` | `/api/v1/crm/attendance/checkout` | Check out with work-hours calculation |
| `POST` | `/api/v1/crm/attendance/corrections` | Submit manual attendance correction request |
| `POST` | `/api/v1/crm/attendance/corrections/{id}/review` | Approve/reject attendance correction |
| `GET` | `/api/v1/crm/leaves` | List leave requests |
| `POST` | `/api/v1/crm/leaves` | Apply for leave with balance verification |
| `POST` | `/api/v1/crm/leaves/{id}/approve` | Approve leave & deduct balance |
| `POST` | `/api/v1/crm/leaves/{id}/reject` | Reject leave & release pending balance |
| `POST` | `/api/v1/crm/leaves/{id}/cancel` | Cancel leave & restore balance |
| `GET` | `/api/v1/crm/leaves/balances/{employeeId?}` | Check annual leave balances |
| `POST` | `/api/v1/crm/leaves/balances/adjust` | Adjust balance with audit trail |
