<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Models\PurchaseOrder;
use App\Domains\Inventory\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Create a new purchase order with line items.
     */
    public function createPurchaseOrder(int $tenantId, array $data, ?User $user = null): PurchaseOrder
    {
        return DB::transaction(function () use ($tenantId, $data, $user) {
            $poNumber = 'PO-' . date('Ymd') . '-' . strtoupper(Str::random(5));

            $itemsData = $data['items'] ?? [];
            if (empty($itemsData)) {
                throw ValidationException::withMessages([
                    'items' => 'At least one line item is required to create a purchase order.',
                ]);
            }

            $subtotal = 0.0;
            $taxAmount = 0.0;

            foreach ($itemsData as $item) {
                $qty = abs((float) $item['quantity']);
                $unitCost = (float) $item['unit_cost'];
                $taxPct = (float) ($item['tax_percent'] ?? 0);

                $itemSubtotal = $qty * $unitCost;
                $itemTax = $itemSubtotal * ($taxPct / 100);

                $subtotal += $itemSubtotal;
                $taxAmount += $itemTax;
            }

            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            $totalAmount = max(0.0, ($subtotal + $taxAmount) - $discountAmount);

            $po = PurchaseOrder::create([
                'tenant_id' => $tenantId,
                'po_number' => $poNumber,
                'supplier_id' => (int) $data['supplier_id'],
                'warehouse_id' => (int) $data['warehouse_id'],
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'currency' => $data['currency'] ?? 'USD',
                'subtotal' => round($subtotal, 2),
                'tax_amount' => round($taxAmount, 2),
                'discount_amount' => round($discountAmount, 2),
                'total_amount' => round($totalAmount, 2),
                'user_id' => $user?->id,
                'notes' => $data['notes'] ?? null,
                'terms_conditions' => $data['terms_conditions'] ?? null,
            ]);

            foreach ($itemsData as $item) {
                $qty = abs((float) $item['quantity']);
                $unitCost = (float) $item['unit_cost'];
                $taxPct = (float) ($item['tax_percent'] ?? 0);
                $lineTotal = round(($qty * $unitCost) * (1 + ($taxPct / 100)), 2);

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => (int) $item['product_id'],
                    'product_variant_id' => !empty($item['product_variant_id']) ? (int) $item['product_variant_id'] : null,
                    'quantity' => $qty,
                    'received_quantity' => 0,
                    'unit_cost' => $unitCost,
                    'tax_percent' => $taxPct,
                    'total_cost' => $lineTotal,
                ]);
            }

            return $po->load(['supplier', 'warehouse', 'items.product']);
        });
    }

    /**
     * Goods Receipt Note (GRN): Receive goods into warehouse, incrementing stock and updating PO.
     */
    public function receiveGoods(int $tenantId, int $purchaseOrderId, array $receivedItems, ?User $user = null): PurchaseOrder
    {
        return DB::transaction(function () use ($tenantId, $purchaseOrderId, $receivedItems, $user) {
            $po = PurchaseOrder::where('tenant_id', $tenantId)
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($purchaseOrderId);

            if (in_array($po->status, ['received', 'cancelled'])) {
                throw ValidationException::withMessages([
                    'status' => "Cannot receive goods for purchase order with status '{$po->status}'.",
                ]);
            }

            $allCompleted = true;

            foreach ($receivedItems as $rec) {
                $itemId = (int) $rec['item_id'];
                $receivedQty = abs((float) $rec['quantity']);

                $poItem = $po->items->firstWhere('id', $itemId);
                if (!$poItem) continue;

                $newTotalReceived = (float) $poItem->received_quantity + $receivedQty;
                $poItem->received_quantity = $newTotalReceived;
                $poItem->save();

                // Automatically increment stock and record immutable stock movement
                if ($receivedQty > 0) {
                    $this->inventoryService->recordStockMovement([
                        'tenant_id' => $tenantId,
                        'product_id' => $poItem->product_id,
                        'product_variant_id' => $poItem->product_variant_id,
                        'warehouse_id' => $po->warehouse_id,
                        'movement_type' => 'purchase_receipt',
                        'quantity' => $receivedQty,
                        'unit_cost' => (float) $poItem->unit_cost,
                        'reference_type' => 'purchase_order',
                        'reference_id' => $po->id,
                        'notes' => "Goods receipt for {$po->po_number}",
                    ], $user);
                }

                if ($newTotalReceived < (float) $poItem->quantity) {
                    $allCompleted = false;
                }
            }

            $po->status = $allCompleted ? 'received' : 'partially_received';
            if ($allCompleted) {
                $po->received_at = now();
            }
            $po->save();

            return $po->load(['supplier', 'warehouse', 'items.product']);
        });
    }
}
