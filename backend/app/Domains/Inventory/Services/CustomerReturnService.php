<?php

namespace App\Domains\Inventory\Services;

use App\Domains\CRM\Models\Customer;
use App\Domains\Finance\Models\CreditNote;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Inventory\Models\CustomerReturn;
use App\Domains\Inventory\Models\CustomerReturnItem;
use App\Domains\Sales\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerReturnService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Create a customer return request (RMA).
     */
    public function createReturnRequest(int $tenantId, array $data, User $user): CustomerReturn
    {
        return DB::transaction(function () use ($tenantId, $data, $user) {
            $returnNumber = 'RMA-' . date('Ymd') . '-' . strtoupper(Str::random(5));
            $itemsData = $data['items'] ?? [];

            if (empty($itemsData)) {
                throw ValidationException::withMessages([
                    'items' => 'At least one item is required for a return request.',
                ]);
            }

            $totalRefund = 0.0;
            foreach ($itemsData as $item) {
                $qty = abs((float) $item['quantity']);
                $price = (float) ($item['unit_price'] ?? 0);
                $taxRate = (float) ($item['tax_rate'] ?? 0);
                $totalRefund += ($qty * $price * (1 + ($taxRate / 100)));
            }

            $warehouseId = (int) ($data['warehouse_id'] ?? $this->inventoryService->ensureDefaultWarehouse($tenantId)->id);

            $customerReturn = CustomerReturn::create([
                'tenant_id' => $tenantId,
                'return_number' => $returnNumber,
                'customer_id' => (int) $data['customer_id'],
                'invoice_id' => $data['invoice_id'] ?? null,
                'order_reference' => $data['order_reference'] ?? null,
                'warehouse_id' => $warehouseId,
                'status' => 'requested',
                'return_reason' => $data['return_reason'],
                'product_condition' => $data['product_condition'] ?? 'opened_good',
                'restock_action' => $data['restock_action'] ?? 'restock_available',
                'refund_type' => $data['refund_type'] ?? 'credit_note',
                'replacement_product_id' => $data['replacement_product_id'] ?? null,
                'total_refund_amount' => round($totalRefund, 2),
                'inspection_notes' => $data['inspection_notes'] ?? null,
            ]);

            foreach ($itemsData as $item) {
                $isDamaged = in_array($customerReturn->product_condition, ['defective', 'damaged_in_transit']);

                CustomerReturnItem::create([
                    'customer_return_id' => $customerReturn->id,
                    'product_id' => (int) $item['product_id'],
                    'product_variant_id' => !empty($item['product_variant_id']) ? (int) $item['product_variant_id'] : null,
                    'quantity' => abs((float) $item['quantity']),
                    'unit_price' => (float) ($item['unit_price'] ?? 0),
                    'tax_rate' => (float) ($item['tax_rate'] ?? 0),
                    'restock_warehouse_id' => $warehouseId,
                    'is_restocked' => false,
                    'is_damaged' => $isDamaged,
                    'condition_notes' => $item['condition_notes'] ?? null,
                ]);
            }

            return $customerReturn->load(['customer', 'invoice', 'warehouse', 'items.product']);
        });
    }

    /**
     * Inspect returned product.
     */
    public function inspectReturn(
        int $tenantId,
        int $returnId,
        string $condition,
        string $restockAction,
        ?string $notes,
        User $inspector
    ): CustomerReturn {
        return DB::transaction(function () use ($tenantId, $returnId, $condition, $restockAction, $notes, $inspector) {
            $return = CustomerReturn::where('tenant_id', $tenantId)->findOrFail($returnId);

            $return->product_condition = $condition;
            $return->restock_action = $restockAction;
            $return->inspection_notes = $notes;
            $return->inspected_by = $inspector->id;
            $return->status = 'inspecting';
            $return->save();

            return $return->load(['customer', 'invoice', 'warehouse', 'items.product']);
        });
    }

    /**
     * Approve return and execute restock / quarantine & credit note issuance.
     * Crucial rule: Do not automatically return damaged products to available stock!
     */
    public function approveReturnAndRestock(int $tenantId, int $returnId, User $approver): CustomerReturn
    {
        return DB::transaction(function () use ($tenantId, $returnId, $approver) {
            $return = CustomerReturn::where('tenant_id', $tenantId)
                ->with('items.product')
                ->lockForUpdate()
                ->findOrFail($returnId);

            if ($return->status === 'completed') {
                return $return;
            }

            $restockAction = $return->restock_action; // restock_available, quarantine_damage, scrap, return_to_vendor

            foreach ($return->items as $item) {
                $qty = (float) $item->quantity;
                $cost = (float) ($item->product?->cost_price ?? 0);

                if ($restockAction === 'restock_available') {
                    // Sound item: return to warehouse available stock
                    $this->inventoryService->recordStockMovement([
                        'tenant_id' => $tenantId,
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'warehouse_id' => $item->restock_warehouse_id,
                        'movement_type' => 'sale_return',
                        'quantity' => $qty,
                        'unit_cost' => $cost,
                        'reference_type' => 'customer_return',
                        'reference_id' => $return->id,
                        'notes' => "Customer RMA #{$return->return_number} restocked to available inventory",
                    ], $approver);

                    $item->is_restocked = true;
                    $item->is_damaged = false;
                } else {
                    // Damaged / scrap item: Quarantine into damaged stock, do NOT add to available stock!
                    $this->inventoryService->recordStockMovement([
                        'tenant_id' => $tenantId,
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'warehouse_id' => $item->restock_warehouse_id,
                        'movement_type' => 'damage_writeoff',
                        'quantity' => $qty,
                        'unit_cost' => $cost,
                        'reference_type' => 'customer_return',
                        'reference_id' => $return->id,
                        'notes' => "Customer RMA #{$return->return_number} quarantined as damaged stock ({$restockAction})",
                    ], $approver);

                    $item->is_restocked = false;
                    $item->is_damaged = true;
                }
                $item->save();
            }

            // Issue Credit Note if refund_type is credit_note
            if ($return->refund_type === 'credit_note' && $return->total_refund_amount > 0) {
                $creditNote = CreditNote::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $return->customer_id,
                    'invoice_id' => $return->invoice_id,
                    'credit_note_number' => 'CN-' . date('Ymd') . '-' . strtoupper(Str::random(5)),
                    'amount' => $return->total_refund_amount,
                    'currency' => 'INR',
                    'reason' => "Refund for return RMA #{$return->return_number}: {$return->return_reason}",
                    'status' => 'issued',
                    'user_id' => $approver->id,
                    'notes' => "Auto-generated credit note for approved customer return",
                ]);

                $return->credit_note_id = $creditNote->id;
            }

            $return->approved_by = $approver->id;
            $return->status = 'completed';
            $return->save();

            return $return->load(['customer', 'invoice', 'warehouse', 'creditNote', 'items.product']);
        });
    }
}
