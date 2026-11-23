# Complete HR Management, Employee Productivity & Indian Payroll Support Module

## Overview
This module enhances the Enterprise CRM system with an enterprise-grade Human Resources Management (HRM), Employee Productivity Intelligence, Mobile Geofenced Attendance, and Indian Statutory Payroll Architecture compliant with EPFO, ESIC, State Professional Tax (PT), Payment of Gratuity Act 1972, and statutory Full & Final (F&F) settlements.

---

## 1. Architecture & Enterprise Capabilities

### 1.1 Employee Management & Sensitive Masking
- **Comprehensive Profile**: Tracks Employee ID, Corporate Email, Phone, Department, Designation, Reporting Manager, Branch, Joining Date, Employment Type (`full_time`, `part_time`, `contract`, `intern`), Work Location, Probation End Date, Contract Expiry Date, Notice Period Days, and Salary Structure Reference.
- **Sensitive Data Isolation & Masking**: Statutory identifiers (`pan_number`, `aadhaar_number`, `uan_number`, `esi_number`, `bank_account_number`, `bank_name`, `bank_ifsc`) and `salary` are strictly masked with `null` values for unauthorized users (e.g. general staff or peer team members). Only `Business Owner`, `Business Admin`, `HR`, `Platform Super Admin`, or the employee themselves can view these values.
- **Reporting Hierarchy & Department Transfers**: Full audit trail of employee transfers across departments, designations, and managers (`api/v1/crm/employees/{id}/transfer`).

### 1.2 Shifts, Holidays & Attendance Geofencing
- **Multi-Shift Management**: Flexible shifts with configurable grace periods, half-day minutes, and full-day minutes.
- **Official Holiday Calendar**: Branch-specific and company-wide official holidays. The leave application workflow actively prevents leave deductions on recognized company holidays.
- **Mobile Point-in-Time Geofence Attendance**:
  - Validates employee GPS coordinates (Latitude, Longitude, Accuracy) against company branch coordinates using the Haversine spherical distance formula.
  - Automatically flags whether check-in occurred within the configured geofence radius (`is_within_geofence`).
  - Supports verification methods: `gps`, `selfie_gps`, and `manual`.
  - **Privacy Guarantee**: Operates strictly on point-in-time check-in and check-out events; continuous background location tracking is never performed.
- **Overtime & Early Departure**:
  - Calculates late arrival minutes, early departure minutes, overtime minutes, and half-day status based on shift rules.
  - Automated attendance correction request and approval workflow (`/api/v1/crm/attendance/corrections`).

### 1.3 Indian Payroll & Statutory CTC Architecture
- **Configurable Salary Structures**:
  - **Basic Pay**: 40% to 50% of monthly CTC.
  - **House Rent Allowance (HRA)**: Configured at 50% of Basic Pay (statutory standard for metro/non-metro rules).
  - **Standard Allowances**: Conveyance, Medical, and balancing Special Allowance.
  - **Employees' Provident Fund (EPFO)**: 12% employee contribution, 12% employer contribution, capped at the statutory ₹15,000 wage ceiling.
  - **Employees' State Insurance (ESIC)**: 0.75% employee contribution, 3.25% employer contribution, applicable if gross earnings are within the statutory ₹21,000 threshold.
  - **State Professional Tax (PT)**: Tiered state tax slabs (e.g. Karnataka ₹200 for gross > ₹15,000; Maharashtra ₹200/₹300; West Bengal).
  - **Income Tax (TDS)**: Estimated monthly tax deductions.
- **Monthly Payroll Batch Processing**:
  - Auto-calculates earnings, deductions, Loss of Pay (LOP) for unpaid leaves, and overtime earnings across all active staff.
  - Full batch lifecycle: `calculated` -> `approved` -> `disbursed` -> `locked`.
  - Itemized payslip generation with complete calculation trace auditability.
- **Full & Final (F&F) Separation Settlement**:
  - Calculates unpaid monthly days salary.
  - Computes Leave Encashment based on accrued unutilized leaves and last drawn basic salary.
  - Evaluates statutory Gratuity under the Payment of Gratuity Act 1972: `(15 * Last Drawn Basic * Tenure Years) / 26` for employees with 5 or more years of continuous service.
  - Recovers notice period shortfall where served notice days fall short of contractual required notice days.

### 1.4 Document Lifecycle Management
- Tracks employee contracts, appointment letters, government IDs, PAN cards, Aadhaar proofs, academic qualifications, and professional certificates.
- Expiration tracking with automated compliance reminders.
- HR document verification workflow (`/api/v1/crm/employees/{id}/documents/{docId}/verify`).

### 1.5 Employee Productivity Intelligence
- **Holistic Productivity Scoring (0-100)**:
  - Balances Task execution completion rate (35%), Attendance reliability and punctuality (25%), Target goal progress (25%), and Manager performance appraisals (15%).
  - Does not penalize employees for high-complexity, low-velocity deliverables.
- **Performance Reviews**: Periodic appraisal scoring across Productivity, Quality, and Teamwork with qualitative strengths and action plans.
- **Target Goals**: Progress monitoring of measurable metric targets (e.g., system uptime, deal volume, project milestones).

### 1.6 HR Reminders & Action Milestones
- Proactive alerts for probation completions, expiring contracts, expiring identity documents, work anniversaries, and pending leave/attendance approvals.
- Prioritized queue (`urgent`, `high`, `medium`, `low`) accessible via `/api/v1/crm/hr/reminders`.

---

## 2. API Endpoints Reference

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/crm/employees` | List employees with department & status filters |
| `POST` | `/api/v1/crm/employees` | Create employee profile with branch, probation & statutory data |
| `GET` | `/api/v1/crm/employees/{id}` | Retrieve employee profile (sensitive data masked for unauthorized staff) |
| `POST` | `/api/v1/crm/employees/{id}/transfer` | Transfer employee to department/designation/manager |
| `GET` | `/api/v1/crm/payroll/structures` | List Indian salary structures |
| `POST` | `/api/v1/crm/payroll/structures` | Create customized salary structure |
| `GET` | `/api/v1/crm/payroll/employees/{id}/salary` | Get assigned salary breakdown and CTC components |
| `POST` | `/api/v1/crm/payroll/employees/{id}/salary` | Assign salary structure and calculate CTC breakdown |
| `GET` | `/api/v1/crm/payroll/batches` | List monthly payroll batches |
| `POST` | `/api/v1/crm/payroll/batches/generate` | Run monthly payroll calculation batch |
| `POST` | `/api/v1/crm/payroll/batches/{id}/approve` | Approve payroll batch |
| `POST` | `/api/v1/crm/payroll/batches/{id}/disburse` | Disburse payroll batch and record payments |
| `GET` | `/api/v1/crm/payroll/payslips` | List payslips with filtering by batch/employee/month |
| `POST` | `/api/v1/crm/payroll/full-and-final` | Compute F&F settlement (unpaid days, encashment, gratuity, notice) |
| `GET` | `/api/v1/crm/employees/{id}/documents` | List employee credentials and agreements |
| `POST` | `/api/v1/crm/employees/{id}/documents` | Register employee contract/ID proof |
| `POST` | `/api/v1/crm/employees/{id}/documents/{docId}/verify` | Verify employee document |
| `GET` | `/api/v1/crm/employees/{id}/productivity` | Get holistic employee productivity metrics |
| `GET` | `/api/v1/crm/employees/{id}/reviews` | List performance appraisals |
| `POST` | `/api/v1/crm/employees/{id}/reviews` | Log performance appraisal review |
| `GET` | `/api/v1/crm/employees/{id}/goals` | List employee productivity targets |
| `POST` | `/api/v1/crm/employees/{id}/goals` | Set employee productivity goal |
| `GET` | `/api/v1/crm/hr/reminders` | Query upcoming HR administrative milestones and alerts |

---

## 3. Verification & Compliance
- **PHPUnit Feature Tests**: `backend/tests/Feature/EnterpriseHrAndPayrollTest.php` passes with **11 / 11 tests (100% passing)**.
- **Frontend Integration Suite**: `frontend/src/tests/apiIntegration.test.ts` passes with **58 / 58 steps (100% passing)**.
- **Production Bundle**: Clean build compiled via `npm run build` in 8.68 seconds without compilation errors.
