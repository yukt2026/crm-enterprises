<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\CRM\Models\Customer;
use App\Domains\CustomerPortal\Models\BookableService;
use App\Domains\CustomerPortal\Models\CustomerAccount;
use App\Domains\CustomerPortal\Services\CustomerPortalService;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Payment;
use App\Domains\Projects\Models\Meeting;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectDocument;
use App\Domains\Finance\Models\Quotation;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerPortalController extends Controller
{
    public function __construct(
        protected CustomerPortalService $portalService
    ) {}

    /**
     * Resolve tenant ID from request header or body.
     */
    protected function resolveTenantId(Request $request): int
    {
        $tenantId = $request->header('X-Workspace-Id') ?? $request->input('tenant_id');
        if (!$tenantId) {
            $tenant = Tenant::first();
            $tenantId = $tenant ? $tenant->id : 1;
        }

        return (int) $tenantId;
    }

    /**
     * Customer Registration
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:30',
            'branch_id' => 'nullable|integer',
        ]);

        $tenantId = $this->resolveTenantId($request);
        $result = $this->portalService->registerCustomer($validated, $tenantId);

        return response()->json([
            'success' => true,
            'message' => 'Customer registration successful.',
            'data' => $result,
        ], 201);
    }

    /**
     * Customer Login
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $tenantId = $this->resolveTenantId($request);
        $result = $this->portalService->loginCustomer(
            $validated['email'],
            $validated['password'],
            $tenantId,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'message' => 'Customer authentication successful.',
            'data' => $result,
        ]);
    }

    /**
     * Customer Logout
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var CustomerAccount $account */
        $account = $request->user();
        if ($account && method_exists($account, 'currentAccessToken')) {
            $account->currentAccessToken()?->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Customer Dashboard Metrics
     */
    public function dashboard(Request $request): JsonResponse
    {
        /** @var CustomerAccount $account */
        $account = $request->user();
        $metrics = $this->portalService->getDashboardMetrics($account);

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    /**
     * Customer Profile & Settings
     */
    public function profile(Request $request): JsonResponse
    {
        /** @var CustomerAccount $account */
        $account = $request->user();
        $account->load([
            'customer.company:id,name',
            'customer.branch:id,name',
            'loginHistories' => fn($q) => $q->orderBy('created_at', 'desc')->limit(10),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'account' => $account,
                'customer' => $account->customer,
            ],
        ]);
    }

    /**
     * Update Profile Info
     */
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var CustomerAccount $account */
        $account = $request->user();
        $customer = $account->customer;

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:30',
            'billing_address' => 'nullable|string|max:500',
            'shipping_address' => 'nullable|string|max:500',
            'gstin' => 'nullable|string|max:20',
        ]);

        if (isset($validated['name'])) {
            $customer->name = $validated['name'];
        }
        if (isset($validated['phone'])) {
            $customer->phone = $validated['phone'];
            $account->phone = $validated['phone'];
            $account->save();
        }
        if (isset($validated['billing_address'])) {
            $customer->billing_address = $validated['billing_address'];
        }
        if (isset($validated['shipping_address'])) {
            $customer->shipping_address = $validated['shipping_address'];
        }
        if (isset($validated['gstin'])) {
            $customer->gstin = strtoupper($validated['gstin']);
        }

        $customer->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => $customer->fresh(),
        ]);
    }

    /**
     * Change Password
     */
    public function changePassword(Request $request): JsonResponse
    {
        /** @var CustomerAccount $account */
        $account = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($validated['current_password'], $account->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The provided current password does not match our records.',
            ]);
        }

        $account->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.',
        ]);
    }

    /**
     * List Customer Quotations
     */
    public function quotations(Request $request): JsonResponse
    {
        /** @var CustomerAccount $account */
        $account = $request->user();

        $quotations = Quotation::where('tenant_id', $account->tenant_id)
            ->where('customer_id', $account->customer_id)
            ->where('status', '!=', 'draft')
            ->orderBy('created_at', 'desc')
            ->with(['items'])
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $quotations,
        ]);
    }

    /**
     * Show Quotation Details
     */
    public function showQuotation(Request $request, int $id): JsonResponse
    {
        /** @var CustomerAccount $account */
        $account = $request->user();

        $quotation = Quotation::where('tenant_id', $account->tenant_id)
            ->where('customer_id', $account->customer_id)
            ->with(['items'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $quotation,
        ]);
    }

    /**
     * Accept Quotation
     */
    public function acceptQuotation(Request $request, int $id): JsonResponse
    {
        /** @var CustomerAccount $account */
        $account = $request->user();
        $notes = $request->input('notes');

        $quotation = $this->portalService->acceptQuotation($account, $id, $notes, $request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Quotation accepted successfully.',
            'data' => $quotation,
        ]);
    }

    /**
     * Reject Quotation
     */
    public function rejectQuotation(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);

        /** @var CustomerAccount $account */
        $account = $request->user();
        $quotation = $this->portalService->rejectQuotation($account, $id, $request->input('reason'), $request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Quotation declined.',
            'data' => $quotation,
        ]);
    }

    /**
     * Request Quotation Revision
     */
    public function requestQuotationChange(Request $request, int $id): JsonResponse
    {
        $request->validate(['notes' => 'required|string|max:1000']);

        /** @var CustomerAccount $account */
        $account = $request->user();
        $quotation = $this->portalService->requestQuotationChange($account, $id, $request->input('notes'), $request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Quotation revision request submitted.',
            'data' => $quotation,
        ]);
    }

    /**
     * List Customer Invoices
     */
    public function invoices(Request $request): JsonResponse
    {
        /** @var CustomerAccount $account */
        $account = $request->user();

        $invoices = Invoice::where('tenant_id', $account->tenant_id)
            ->where('customer_id', $account->customer_id)
            ->where('status', '!=', 'draft')
            ->orderBy('issued_at', 'desc')
            ->with(['items', 'payments'])
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    /**
     * Show Invoice Details
     */
    public function showInvoice(Request $request, int $id): JsonResponse
    {
        /** @var CustomerAccount $account */
        $account = $request->user();

        $invoice = Invoice::where('tenant_id', $account->tenant_id)
            ->where('customer_id', $account->customer_id)
            ->with(['items', 'payments'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $invoice,
        ]);
    }

    /**
     * Pay Invoice (Generate Payment or record payment entry)
     */
    public function payInvoice(Request $request, int $id): JsonResponse
    {
        /** @var CustomerAccount $account */
        $account = $request->user();

        $invoice = Invoice::where('tenant_id', $account->tenant_id)
            ->where('customer_id', $account->customer_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string|in:razorpay,cashfree,upi,card,netbanking',
            'transaction_reference' => 'nullable|string|max:100',
        ]);

        $amount = (float) $validated['amount'];
        $txnRef = $validated['transaction_reference'] ?? ('TXN-' . strtoupper(Str::random(10)));

        $payment = Payment::create([
            'tenant_id' => $account->tenant_id,
            'invoice_id' => $invoice->id,
            'customer_id' => $account->customer_id,
            'reference_number' => 'PAY-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
            'amount' => $amount,
            'currency' => $invoice->currency ?? 'INR',
            'payment_method' => $validated['payment_method'],
            'gateway_payment_id' => $txnRef,
            'status' => 'success',
            'paid_at' => Carbon::now(),
        ]);

        // Update invoice paid amounts
        $newPaid = (float) $invoice->paid_amount + $amount;
        $total = (float) $invoice->total_amount;
        $newOutstanding = max(0, $total - $newPaid);
        $status = $newOutstanding <= 0.01 ? 'paid' : 'partially_paid';

        $invoice->update([
            'paid_amount' => $newPaid,
            'outstanding_amount' => $newOutstanding,
            'status' => $status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment processed successfully.',
            'data' => [
                'payment' => $payment,
                'invoice' => $invoice->fresh(),
            ],
        ]);
    }

    /**
     * List Client-Visible Projects
     */
    public function projects(Request $request): JsonResponse
    {
        /** @var CustomerAccount $account */
        $account = $request->user();

        $projects = Project::where('tenant_id', $account->tenant_id)
            ->where('customer_id', $account->customer_id)
            ->where('client_portal_enabled', true)
            ->orderBy('created_at', 'desc')
            ->select(['id', 'name', 'status', 'start_date', 'deadline', 'description', 'created_at'])
            ->with(['milestones' => fn($q) => $q->select(['id', 'project_id', 'title', 'due_date', 'status', 'completion_percentage'])])
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $projects,
        ]);
    }

    /**
     * Show Project Details (Strictly client visible)
     */
    public function showProject(Request $request, int $id): JsonResponse
    {
        /** @var CustomerAccount $account */
        $account = $request->user();

        $project = Project::where('tenant_id', $account->tenant_id)
            ->where('customer_id', $account->customer_id)
            ->where('client_portal_enabled', true)
            ->select(['id', 'name', 'status', 'start_date', 'deadline', 'description', 'created_at'])
            ->with([
                'milestones' => fn($q) => $q->select(['id', 'project_id', 'title', 'due_date', 'status', 'completion_percentage']),
                'documents' => fn($q) => $q->where('is_client_visible', true)->select(['id', 'project_id', 'name', 'file_path', 'file_size', 'created_at']),
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $project,
        ]);
    }

    /**
     * Customer Documents Vault
     */
    public function documents(Request $request): JsonResponse
    {
        /** @var CustomerAccount $account */
        $account = $request->user();

        // Project documents shared with client
        $docs = ProjectDocument::where('tenant_id', $account->tenant_id)
            ->where('is_client_visible', true)
            ->whereHas('project', function ($q) use ($account) {
                $q->where('customer_id', $account->customer_id)->where('client_portal_enabled', true);
            })
            ->with('project:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $docs,
        ]);
    }

    /**
     * List Support Tickets
     */
    public function tickets(Request $request): JsonResponse
    {
        /** @var CustomerAccount $account */
        $account = $request->user();

        $tickets = SupportTicket::where('tenant_id', $account->tenant_id)
            ->where('customer_id', $account->customer_id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $tickets,
        ]);
    }

    /**
     * Create Support Ticket
     */
    public function createTicket(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'category_id' => 'nullable|integer',
        ]);

        /** @var CustomerAccount $account */
        $account = $request->user();
        $ticket = $this->portalService->createTicket($account, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Support ticket created successfully.',
            'data' => $ticket,
        ], 201);
    }

    /**
     * Show Support Ticket with Client-Visible Messages Only
     */
    public function showTicket(Request $request, int $id): JsonResponse
    {
        /** @var CustomerAccount $account */
        $account = $request->user();

        $ticket = SupportTicket::where('tenant_id', $account->tenant_id)
            ->where('customer_id', $account->customer_id)
            ->with([
                'messages' => fn($q) => $q->where('is_internal', false)->orderBy('created_at', 'asc'),
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $ticket,
        ]);
    }

    /**
     * Reply to Support Ticket
     */
    public function replyTicket(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'attachments' => 'nullable|array',
        ]);

        /** @var CustomerAccount $account */
        $account = $request->user();
        $msg = $this->portalService->replyToTicket($account, $id, $validated['message'], $validated['attachments'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Reply posted successfully.',
            'data' => $msg,
        ]);
    }

    /**
     * Submit Ticket CSAT Feedback
     */
    public function feedbackTicket(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'csat_score' => 'required|integer|between:1,5',
            'csat_feedback' => 'nullable|string|max:500',
        ]);

        /** @var CustomerAccount $account */
        $account = $request->user();
        $ticket = SupportTicket::where('tenant_id', $account->tenant_id)
            ->where('customer_id', $account->customer_id)
            ->findOrFail($id);

        $ticket->update([
            'csat_score' => $validated['csat_score'],
            'csat_feedback' => $validated['csat_feedback'] ?? null,
            'csat_submitted_at' => Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Thank you for your feedback.',
            'data' => $ticket->fresh(),
        ]);
    }

    /**
     * Customer Appointments
     */
    public function appointments(Request $request): JsonResponse
    {
        /** @var CustomerAccount $account */
        $account = $request->user();

        $meetings = Meeting::where('tenant_id', $account->tenant_id)
            ->where('customer_id', $account->customer_id)
            ->with(['service:id,name,duration_minutes,price', 'branch:id,name'])
            ->orderBy('meeting_date', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $meetings,
        ]);
    }

    /**
     * Book Appointment (Portal)
     */
    public function bookAppointment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|integer',
            'branch_id' => 'nullable|integer',
            'meeting_date' => 'required|date',
            'start_time' => 'required|string',
            'end_time' => 'nullable|string',
            'organizer_id' => 'nullable|integer',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'timezone' => 'nullable|string|max:50',
            'location_type' => 'nullable|string|in:physical,online,phone',
            'location_address' => 'nullable|string|max:255',
        ]);

        /** @var CustomerAccount $account */
        $account = $request->user();
        $meeting = $this->portalService->bookAppointment($validated, $account, $account->tenant_id);

        return response()->json([
            'success' => true,
            'message' => 'Appointment booked successfully.',
            'data' => $meeting,
        ], 201);
    }

    /**
     * Reschedule Appointment
     */
    public function rescheduleAppointment(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'meeting_date' => 'required|date',
            'start_time' => 'required|string',
        ]);

        /** @var CustomerAccount $account */
        $account = $request->user();
        $meeting = $this->portalService->rescheduleAppointment($id, $validated['meeting_date'], $validated['start_time'], $account);

        return response()->json([
            'success' => true,
            'message' => 'Appointment rescheduled successfully.',
            'data' => $meeting,
        ]);
    }

    /**
     * Cancel Appointment
     */
    public function cancelAppointment(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        /** @var CustomerAccount $account */
        $account = $request->user();
        $meeting = $this->portalService->cancelAppointment($id, $validated['reason'], $account);

        return response()->json([
            'success' => true,
            'message' => 'Appointment cancelled successfully.',
            'data' => $meeting,
        ]);
    }

    /**
     * List Available Bookable Services
     */
    public function bookableServices(Request $request): JsonResponse
    {
        /** @var CustomerAccount $account */
        $account = $request->user();

        $services = BookableService::where('tenant_id', $account->tenant_id)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $services,
        ]);
    }
}
