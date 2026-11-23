<?php

namespace App\Domains\CustomerPortal\Services;

use App\Domains\CRM\Models\Branch;
use App\Domains\CRM\Models\Customer;
use App\Domains\CustomerPortal\Models\BookableService;
use App\Domains\CustomerPortal\Models\CustomerAccount;
use App\Domains\CustomerPortal\Models\CustomerLoginHistory;
use App\Domains\CustomerPortal\Models\PortalSetting;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Payment;
use App\Domains\Projects\Models\Meeting;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectDocument;
use App\Domains\Finance\Models\Quotation;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Models\SupportTicketMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerPortalService
{
    /**
     * Register a new customer account (where self-registration is permitted).
     */
    public function registerCustomer(array $data, int $tenantId): array
    {
        $settings = $this->getPortalSettings($tenantId);

        if (!$settings->is_portal_enabled) {
            throw ValidationException::withMessages([
                'portal' => 'The customer self-service portal is currently disabled by administrator.',
            ]);
        }

        if (!$settings->allow_self_registration) {
            throw ValidationException::withMessages([
                'registration' => 'Public customer self-registration is disabled. Please request an account invitation.',
            ]);
        }

        return DB::transaction(function () use ($data, $tenantId) {
            // Find or create base CRM customer record
            $customer = Customer::where('tenant_id', $tenantId)
                ->where('email', strtolower($data['email']))
                ->first();

            if (!$customer) {
                $customer = Customer::create([
                    'tenant_id' => $tenantId,
                    'name' => $data['name'],
                    'email' => strtolower($data['email']),
                    'phone' => $data['phone'] ?? null,
                    'status' => 'active',
                    'branch_id' => $data['branch_id'] ?? null,
                ]);
            }

            // Ensure no existing account for this customer in tenant
            $existingAccount = CustomerAccount::where('tenant_id', $tenantId)
                ->where('email', strtolower($data['email']))
                ->first();

            if ($existingAccount) {
                throw ValidationException::withMessages([
                    'email' => 'A portal account already exists with this email address.',
                ]);
            }

            $account = CustomerAccount::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'is_active' => true,
                'email_verified_at' => Carbon::now(),
                'last_login_at' => Carbon::now(),
            ]);

            CustomerLoginHistory::create([
                'customer_account_id' => $account->id,
                'status' => 'success',
                'created_at' => Carbon::now(),
            ]);

            $token = $account->createToken('customer-portal-token', ['portal:customer'])->plainTextToken;

            return [
                'token' => $token,
                'account' => $account->load('customer'),
            ];
        });
    }

    /**
     * Authenticate customer with email/password and issue scoped Sanctum token.
     */
    public function loginCustomer(string $email, string $password, int $tenantId, ?string $ip = null, ?string $userAgent = null): array
    {
        $settings = $this->getPortalSettings($tenantId);

        if (!$settings->is_portal_enabled) {
            throw ValidationException::withMessages([
                'portal' => 'The customer self-service portal is currently disabled.',
            ]);
        }

        $account = CustomerAccount::where('tenant_id', $tenantId)
            ->where('email', strtolower($email))
            ->with('customer')
            ->first();

        if (!$account || !Hash::check($password, $account->password)) {
            if ($account) {
                CustomerLoginHistory::create([
                    'customer_account_id' => $account->id,
                    'ip_address' => $ip,
                    'user_agent' => $userAgent,
                    'status' => 'failed',
                    'created_at' => Carbon::now(),
                ]);
            }

            throw ValidationException::withMessages([
                'email' => 'Invalid email credentials or customer account does not exist.',
            ]);
        }

        if (!$account->is_active) {
            throw ValidationException::withMessages([
                'account' => 'This customer portal account has been deactivated. Please contact support.',
            ]);
        }

        $account->update([
            'last_login_at' => Carbon::now(),
            'last_login_ip' => $ip,
        ]);

        CustomerLoginHistory::create([
            'customer_account_id' => $account->id,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'status' => 'success',
            'created_at' => Carbon::now(),
        ]);

        $token = $account->createToken('customer-portal-token', ['portal:customer'])->plainTextToken;

        return [
            'token' => $token,
            'account' => $account,
        ];
    }

    /**
     * Customer Dashboard: aggregates only authorized customer information.
     */
    public function getDashboardMetrics(CustomerAccount $account): array
    {
        $tenantId = $account->tenant_id;
        $customerId = $account->customer_id;
        $customer = $account->customer;

        // Invoices and Financials
        $totalInvoiced = (float)Invoice::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('status', '!=', 'draft')
            ->sum('total_amount');

        $totalPaid = (float)Invoice::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('status', '!=', 'draft')
            ->sum('paid_amount');

        $outstandingDue = (float)Invoice::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->sum('outstanding_amount');

        // Quotations
        $pendingQuotesCount = Quotation::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('status', 'sent')
            ->count();

        // Projects
        $activeProjectsCount = Project::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('client_portal_enabled', true)
            ->whereIn('status', ['active', 'planned'])
            ->count();

        // Support Tickets
        $openTicketsCount = SupportTicket::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['new', 'open', 'in_progress', 'waiting_customer'])
            ->count();

        // Upcoming Appointments
        $upcomingAppointmentsCount = Meeting::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('meeting_date', '>=', Carbon::today())
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->count();

        // Recent Invoices
        $recentInvoices = Invoice::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('status', '!=', 'draft')
            ->orderBy('issued_at', 'desc')
            ->limit(5)
            ->get(['id', 'invoice_number', 'total_amount', 'paid_amount', 'outstanding_amount', 'status', 'due_date', 'issued_at']);

        // Recent Quotations
        $recentQuotations = Quotation::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('status', '!=', 'draft')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'quote_number', 'total_amount', 'status', 'valid_until', 'accepted_at', 'created_at']);

        // Upcoming Appointments List
        $recentAppointments = Meeting::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('meeting_date', '>=', Carbon::today())
            ->orderBy('meeting_date', 'asc')
            ->with(['service:id,name,duration_minutes'])
            ->limit(5)
            ->get();

        return [
            'profile' => [
                'customer_id' => $customer->id,
                'name' => $customer->name,
                'email' => $account->email,
                'phone' => $customer->phone,
                'tier' => $customer->tier,
                'company' => $customer->company ? ['id' => $customer->company->id, 'name' => $customer->company->name] : null,
                'branch' => $customer->branch ? ['id' => $customer->branch->id, 'name' => $customer->branch->name] : null,
            ],
            'financials' => [
                'total_invoiced' => $totalInvoiced,
                'total_paid' => $totalPaid,
                'outstanding_due' => $outstandingDue,
            ],
            'counts' => [
                'pending_quotations' => $pendingQuotesCount,
                'active_projects' => $activeProjectsCount,
                'open_support_tickets' => $openTicketsCount,
                'upcoming_appointments' => $upcomingAppointmentsCount,
            ],
            'recent_invoices' => $recentInvoices,
            'recent_quotations' => $recentQuotations,
            'recent_appointments' => $recentAppointments,
        ];
    }

    /**
     * Accept shared quotation.
     */
    public function acceptQuotation(CustomerAccount $account, int $quotationId, ?string $notes = null, ?string $ip = null): Quotation
    {
        $quotation = Quotation::where('tenant_id', $account->tenant_id)
            ->where('customer_id', $account->customer_id)
            ->findOrFail($quotationId);

        if ($quotation->status === 'accepted') {
            return $quotation;
        }

        $quotation->update([
            'status' => 'accepted',
            'accepted_at' => Carbon::now(),
            'customer_action_ip' => $ip,
            'customer_action_notes' => $notes,
        ]);

        return $quotation;
    }

    /**
     * Reject shared quotation.
     */
    public function rejectQuotation(CustomerAccount $account, int $quotationId, string $reason, ?string $ip = null): Quotation
    {
        $quotation = Quotation::where('tenant_id', $account->tenant_id)
            ->where('customer_id', $account->customer_id)
            ->findOrFail($quotationId);

        $quotation->update([
            'status' => 'rejected',
            'rejected_at' => Carbon::now(),
            'rejection_reason' => $reason,
            'customer_action_ip' => $ip,
        ]);

        return $quotation;
    }

    /**
     * Request quotation revision/changes.
     */
    public function requestQuotationChange(CustomerAccount $account, int $quotationId, string $notes, ?string $ip = null): Quotation
    {
        $quotation = Quotation::where('tenant_id', $account->tenant_id)
            ->where('customer_id', $account->customer_id)
            ->findOrFail($quotationId);

        $quotation->update([
            'status' => 'revision_requested',
            'customer_action_ip' => $ip,
            'customer_action_notes' => $notes,
        ]);

        return $quotation;
    }

    /**
     * Create customer support ticket from portal.
     */
    public function createTicket(CustomerAccount $account, array $data): SupportTicket
    {
        $count = SupportTicket::where('tenant_id', $account->tenant_id)->count() + 1;
        $ticketNumber = sprintf('TCK-%s-%04d', date('Y'), $count);

        return SupportTicket::create([
            'tenant_id' => $account->tenant_id,
            'customer_id' => $account->customer_id,
            'ticket_number' => $ticketNumber,
            'source_channel' => 'portal',
            'category_id' => $data['category_id'] ?? null,
            'subject' => $data['subject'],
            'description' => $data['description'],
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'new',
            'tags' => ['customer_portal'],
        ]);
    }

    /**
     * Customer post reply to support ticket.
     */
    public function replyToTicket(CustomerAccount $account, int $ticketId, string $message, ?array $attachments = null): SupportTicketMessage
    {
        $ticket = SupportTicket::where('tenant_id', $account->tenant_id)
            ->where('customer_id', $account->customer_id)
            ->findOrFail($ticketId);

        $msg = SupportTicketMessage::create([
            'tenant_id' => $account->tenant_id,
            'ticket_id' => $ticket->id,
            'sender_type' => 'customer',
            'sender_id' => $account->id,
            'message' => $message,
            'is_internal' => false, // strictly visible
            'channel' => 'portal',
            'attachments' => $attachments ?? [],
        ]);

        // Auto-reopen ticket if it was closed or waiting for customer
        if (in_array($ticket->status, ['waiting_customer', 'resolved'])) {
            $ticket->update(['status' => 'in_progress']);
        }

        return $msg;
    }

    /**
     * Transaction-safe Appointment Booking Engine.
     */
    public function bookAppointment(array $params, ?CustomerAccount $account = null, int $tenantId): Meeting
    {
        $service = BookableService::where('tenant_id', $tenantId)->findOrFail($params['service_id']);

        $meetingDate = Carbon::parse($params['meeting_date'])->toDateString();
        $startTime = $params['start_time'];
        $durationMinutes = $service->duration_minutes ?: 30;
        $endTime = $params['end_time'] ?? Carbon::parse("{$meetingDate} {$startTime}")->addMinutes($durationMinutes)->format('H:i:s');
        $organizerId = $params['organizer_id'] ?? ($service->assigned_staff_ids[0] ?? null);

        return DB::transaction(function () use ($params, $service, $meetingDate, $startTime, $endTime, $organizerId, $account, $tenantId) {
            // Prevent double-booking using transaction-safe row locks
            $conflictQuery = Meeting::where('tenant_id', $tenantId)
                ->whereDate('meeting_date', $meetingDate)
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
                });

            if ($organizerId) {
                $conflictQuery->where('organizer_id', $organizerId);
            } else {
                $conflictQuery->where('service_id', $service->id);
            }

            $hasConflict = $conflictQuery->lockForUpdate()->exists();

            if ($hasConflict) {
                throw ValidationException::withMessages([
                    'start_time' => 'This appointment time slot is already reserved. Please select another slot.',
                ]);
            }

            $bookingRef = sprintf('APT-%s-%s', date('Ymd'), strtoupper(Str::random(6)));

            $meeting = Meeting::create([
                'tenant_id' => $tenantId,
                'customer_id' => $account?->customer_id ?? $params['customer_id'] ?? null,
                'service_id' => $service->id,
                'branch_id' => $params['branch_id'] ?? $service->branch_id,
                'organizer_id' => $organizerId,
                'title' => $params['title'] ?? ("Appointment: " . $service->name),
                'description' => $params['notes'] ?? null,
                'meeting_date' => $meetingDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'timezone' => $params['timezone'] ?? 'Asia/Kolkata',
                'location_type' => $params['location_type'] ?? 'physical',
                'location_address' => $params['location_address'] ?? null,
                'booking_reference' => $bookingRef,
                'booking_fee' => (float)$service->price,
                'payment_status' => $service->requires_advance_payment ? 'unpaid' : 'paid',
                'is_customer_booked' => true,
                'status' => 'scheduled',
            ]);

            return $meeting->load(['service', 'organizer', 'branch']);
        });
    }

    /**
     * Reschedule appointment with slot availability re-check.
     */
    public function rescheduleAppointment(int $appointmentId, string $newDate, string $newStartTime, ?CustomerAccount $account = null): Meeting
    {
        $query = Meeting::query();
        if ($account) {
            $query->where('tenant_id', $account->tenant_id)->where('customer_id', $account->customer_id);
        }

        $meeting = $query->findOrFail($appointmentId);

        $service = $meeting->service ?: BookableService::find($meeting->service_id);
        $duration = $service ? $service->duration_minutes : 30;
        $newEndTime = Carbon::parse("{$newDate} {$newStartTime}")->addMinutes($duration)->format('H:i:s');

        return DB::transaction(function () use ($meeting, $newDate, $newStartTime, $newEndTime) {
            $conflictQuery = Meeting::where('tenant_id', $meeting->tenant_id)
                ->where('id', '!=', $meeting->id)
                ->whereDate('meeting_date', $newDate)
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->where(function ($q) use ($newStartTime, $newEndTime) {
                    $q->where('start_time', '<', $newEndTime)
                      ->where('end_time', '>', $newStartTime);
                });

            if ($meeting->organizer_id) {
                $conflictQuery->where('organizer_id', $meeting->organizer_id);
            } else {
                $conflictQuery->where('service_id', $meeting->service_id);
            }

            $conflict = $conflictQuery->lockForUpdate()->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    'start_time' => 'Requested new slot is occupied. Please select another time.',
                ]);
            }

            $meeting->update([
                'meeting_date' => $newDate,
                'start_time' => $newStartTime,
                'end_time' => $newEndTime,
                'rescheduled_at' => Carbon::now(),
                'status' => 'rescheduled',
            ]);

            return $meeting->fresh(['service', 'organizer']);
        });
    }

    /**
     * Cancel appointment.
     */
    public function cancelAppointment(int $appointmentId, string $reason, ?CustomerAccount $account = null): Meeting
    {
        $query = Meeting::query();
        if ($account) {
            $query->where('tenant_id', $account->tenant_id)->where('customer_id', $account->customer_id);
        }

        $meeting = $query->findOrFail($appointmentId);

        $meeting->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
            'cancelled_at' => Carbon::now(),
        ]);

        return $meeting;
    }

    /**
     * Get or create tenant portal settings.
     */
    public function getPortalSettings(int $tenantId): PortalSetting
    {
        return PortalSetting::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'is_portal_enabled' => true,
                'allow_self_registration' => false,
                'allow_invoice_access' => true,
                'allow_payments' => true,
                'allow_project_tracking' => true,
                'allow_ticket_creation' => true,
                'allow_appointment_booking' => true,
                'allow_document_downloads' => true,
                'public_booking_page_enabled' => true,
                'brand_color' => '#4F46E5',
            ]
        );
    }
}
