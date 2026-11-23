<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\CRM\Models\Branch;
use App\Domains\CRM\Models\Customer;
use App\Domains\CustomerPortal\Models\BookableService;
use App\Domains\CustomerPortal\Models\PortalSetting;
use App\Domains\CustomerPortal\Services\CustomerPortalService;
use App\Domains\Projects\Models\Meeting;
use App\Domains\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PublicBookingController extends Controller
{
    public function __construct(
        protected CustomerPortalService $portalService
    ) {}

    /**
     * Resolve Tenant from slug or id
     */
    protected function resolveTenant(string $slug): Tenant
    {
        $setting = PortalSetting::where('booking_slug', $slug)->first();
        if ($setting) {
            return Tenant::findOrFail($setting->tenant_id);
        }

        if (is_numeric($slug)) {
            return Tenant::findOrFail((int) $slug);
        }

        $tenant = Tenant::where('slug', $slug)->orWhere('uuid', $slug)->first();
        if ($tenant) {
            return $tenant;
        }

        abort(404, 'Booking page not found or invalid slug.');
    }

    /**
     * Public Booking Page Configuration & Branding
     */
    public function getBookingConfig(Request $request, string $slug): JsonResponse
    {
        $tenant = $this->resolveTenant($slug);
        $setting = $this->portalService->getPortalSettings($tenant->id);

        if (!$setting->public_booking_page_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Online public booking is currently disabled for this organization.',
            ], 403);
        }

        $services = BookableService::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get();

        $branches = Branch::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get(['id', 'name', 'code', 'city', 'state']);

        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                ],
                'branding' => [
                    'brand_color' => $setting->brand_color ?: '#4F46E5',
                    'company_logo_url' => $setting->company_logo_url,
                    'booking_terms' => $setting->booking_terms,
                    'booking_rules' => $setting->booking_rules,
                ],
                'services' => $services,
                'branches' => $branches,
            ],
        ]);
    }

    /**
     * Available Time Slots for Service on Date
     */
    public function getSlots(Request $request, string $slug): JsonResponse
    {
        $tenant = $this->resolveTenant($slug);

        $request->validate([
            'service_id' => 'required|integer',
            'date' => 'required|date',
            'branch_id' => 'nullable|integer',
            'staff_id' => 'nullable|integer',
        ]);

        $service = BookableService::where('tenant_id', $tenant->id)->findOrFail($request->service_id);
        $date = Carbon::parse($request->date)->toDateString();
        $duration = $service->duration_minutes ?: 30;

        // Generate standard business hours slots: 09:00 to 18:00
        $dayStart = Carbon::parse("{$date} 09:00:00");
        $dayEnd = Carbon::parse("{$date} 18:00:00");

        $existingMeetings = Meeting::where('tenant_id', $tenant->id)
            ->where('meeting_date', $date)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->get(['id', 'start_time', 'end_time', 'organizer_id']);

        $slots = [];
        $current = $dayStart->copy();

        while ($current->copy()->addMinutes($duration)->lte($dayEnd)) {
            $slotStart = $current->format('H:i:s');
            $slotEnd = $current->copy()->addMinutes($duration)->format('H:i:s');

            $isBooked = $existingMeetings->contains(function ($meeting) use ($slotStart, $slotEnd) {
                return ($meeting->start_time < $slotEnd && $meeting->end_time > $slotStart);
            });

            $slots[] = [
                'start_time' => $slotStart,
                'end_time' => $slotEnd,
                'available' => !$isBooked,
            ];

            $current->addMinutes($duration);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'service' => $service,
                'slots' => $slots,
            ],
        ]);
    }

    /**
     * Submit Public Appointment Booking
     */
    public function book(Request $request, string $slug): JsonResponse
    {
        $tenant = $this->resolveTenant($slug);

        $validated = $request->validate([
            'service_id' => 'required|integer',
            'branch_id' => 'nullable|integer',
            'meeting_date' => 'required|date',
            'start_time' => 'required|string',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:30',
            'notes' => 'nullable|string|max:1000',
            'organizer_id' => 'nullable|integer',
        ]);

        // Find or create customer
        $customer = Customer::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'email' => strtolower($validated['customer_email']),
            ],
            [
                'name' => $validated['customer_name'],
                'phone' => $validated['customer_phone'] ?? null,
                'branch_id' => $validated['branch_id'] ?? null,
                'status' => 'active',
            ]
        );

        $bookingParams = [
            'customer_id' => $customer->id,
            'service_id' => $validated['service_id'],
            'branch_id' => $validated['branch_id'] ?? null,
            'meeting_date' => $validated['meeting_date'],
            'start_time' => $validated['start_time'],
            'notes' => $validated['notes'] ?? null,
            'organizer_id' => $validated['organizer_id'] ?? null,
            'location_type' => 'physical',
        ];

        $meeting = $this->portalService->bookAppointment($bookingParams, null, $tenant->id);

        return response()->json([
            'success' => true,
            'message' => 'Appointment booked successfully. Your confirmation reference is ' . $meeting->booking_reference,
            'data' => [
                'meeting' => $meeting,
                'booking_reference' => $meeting->booking_reference,
            ],
        ], 201);
    }
}
