<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\Invoice;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class EWayBillService
{
    /**
     * Check if invoice is eligible for E-Way Bill (Consignment value > ₹50,000 for inter-state goods).
     */
    public function checkEligibility(Invoice $invoice): array
    {
        $hasServicesOnly = $invoice->items()->where('is_service', false)->count() === 0;
        $isOverThreshold = (float) $invoice->total_amount >= 50000;

        $isEligible = !$hasServicesOnly && $isOverThreshold;

        return [
            'is_eligible' => $isEligible,
            'total_amount' => (float) $invoice->total_amount,
            'has_physical_goods' => !$hasServicesOnly,
            'threshold_met' => $isOverThreshold,
        ];
    }

    /**
     * Generate E-Way Bill for an Invoice or Delivery Challan.
     */
    public function generateEWayBill(int $tenantId, int $invoiceId, array $data, ?User $user = null): Invoice
    {
        $invoice = Invoice::where('tenant_id', $tenantId)->findOrFail($invoiceId);

        $distanceKm = max(1, (int) ($data['distance_km'] ?? 100));
        $vehicleNumber = strtoupper(trim($data['vehicle_number'] ?? 'MH02CB1234'));
        $transporterName = $data['transporter_name'] ?? 'Blue Dart Express';
        $transporterId = $data['transporter_id'] ?? '27AABCB2222E1Z8';
        $transportMode = $data['transport_mode'] ?? 'road';
        $vehicleType = $data['vehicle_type'] ?? 'regular';

        // Validity: 1 day per 200 KM for regular cargo (min 1 day)
        $validityDays = max(1, (int) ceil($distanceKm / 200));
        $validUntil = now()->addDays($validityDays)->endOfDay();

        // 12-digit E-Way Bill Number (State Code 2 digits + Date + Sequence)
        $stateCode = $invoice->seller_state_code ?: '27';
        $ewbNumber = sprintf('%02d%s%06d', (int) $stateCode, date('ym'), rand(100000, 999999));

        $invoice->eway_bill_number = $ewbNumber;
        $invoice->eway_bill_date = now();
        $invoice->eway_bill_valid_until = $validUntil;
        $invoice->distance_km = $distanceKm;
        $invoice->transporter_id = $transporterId;
        $invoice->transporter_name = $transporterName;
        $invoice->transporter_doc_no = $data['transporter_doc_no'] ?? ('LR-' . rand(10000, 99999));
        $invoice->vehicle_number = $vehicleNumber;
        $invoice->vehicle_type = $vehicleType;
        $invoice->transport_mode = $transportMode;
        $invoice->eway_bill_status = 'generated';
        $invoice->save();

        return $invoice;
    }

    /**
     * Update vehicle details (Part B update for in-transit breakdown or transshipment).
     */
    public function updateVehicle(int $tenantId, int $invoiceId, array $data, ?User $user = null): Invoice
    {
        $invoice = Invoice::where('tenant_id', $tenantId)->findOrFail($invoiceId);

        if (!$invoice->eway_bill_number || $invoice->eway_bill_status !== 'generated') {
            throw ValidationException::withMessages([
                'eway_bill' => 'Cannot update vehicle: No active E-Way Bill found for this invoice.',
            ]);
        }

        $newVehicle = strtoupper(trim($data['vehicle_number'] ?? ''));
        if (empty($newVehicle)) {
            throw ValidationException::withMessages([
                'vehicle_number' => 'New vehicle registration number is required.',
            ]);
        }

        $invoice->vehicle_number = $newVehicle;
        $invoice->eway_bill_status = 'updated';
        $invoice->save();

        return $invoice;
    }

    /**
     * Cancel an active E-Way Bill.
     */
    public function cancelEWayBill(int $tenantId, int $invoiceId, string $reason, ?User $user = null): Invoice
    {
        $invoice = Invoice::where('tenant_id', $tenantId)->findOrFail($invoiceId);

        if (empty($invoice->eway_bill_number)) {
            throw ValidationException::withMessages([
                'eway_bill' => 'Cannot cancel E-Way Bill: None has been generated.',
            ]);
        }

        $invoice->eway_bill_status = 'cancelled';
        $invoice->eway_bill_cancel_reason = $reason;
        $invoice->save();

        return $invoice;
    }
}
