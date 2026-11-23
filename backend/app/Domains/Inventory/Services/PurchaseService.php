<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Finance\Models\VendorBill;
use App\Domains\Inventory\Models\GoodsReceiptNote;
use App\Domains\Inventory\Models\GoodsReceiptNoteItem;
use App\Domains\Inventory\Models\ProductStock;
use App\Domains\Inventory\Models\PurchaseOrder;
use App\Domains\Inventory\Models\PurchaseOrderItem;
use App\Domains\Inventory\Models\PurchaseRequest;
use App\Domains\Inventory\Models\PurchaseRequestItem;
use App\Domains\Inventory\Models\Supplier;
use App\Domains\Sales\Models\Product;
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
     * Create a new purchase request (PR).
     */
    public function createPurchaseRequest(int $tenantId, array $data, User $requester): PurchaseRequest
    {
        return DB::transaction(function () use ($tenantId, $data, $requester) {
            $prNumber = 'PR-' . date('Ymd') . '-' . strtoupper(Str::random(5));
            $itemsData = $data['items'] ?? [];

            if (empty($itemsData)) {
                throw ValidationException::withMessages([
                    'items' => 'At least one line item is required for a purchase request.',
                ]);
            }

            $totalEstimated = 0.0;
            foreach ($itemsData as $item) {
                $qty = abs((float) $item['quantity']);
                $unitCost = (float) ($item['estimated_unit_cost'] ?? 0);
                $totalEstimated += ($qty * $unitCost);
            }

            $pr = PurchaseRequest::create([
                'tenant_id' => $tenantId,
                'pr_number' => $prNumber,
                'requested_by' => $requester->id,
                'department_id' => $data['department_id'] ?? null,
                'warehouse_id' => (int) $data['warehouse_id'],
                'status' => 'pending_approval',
                'total_estimated_amount' => round($totalEstimated, 2),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($itemsData as $item) {
                $qty = abs((float) $item['quantity']);
                $unitCost = (float) ($item['estimated_unit_cost'] ?? 0);

                PurchaseRequestItem::create([
                    'purchase_request_id' => $pr->id,
                    'product_id' => (int) $item['product_id'],
                    'product_variant_id' => !empty($item['product_variant_id']) ? (int) $item['product_variant_id'] : null,
                    'quantity' => $qty,
                    'estimated_unit_cost' => $unitCost,
                    'total_cost' => round($qty * $unitCost, 2),
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $pr->load(['requester', 'warehouse', 'items.product']);
        });
    }

    /**
     * Approve purchase request.
     */
    public function approvePurchaseRequest(int $tenantId, int $prId, User $approver): PurchaseRequest
    {
        return DB::transaction(function () use ($tenantId, $prId, $approver) {
            $pr = PurchaseRequest::where('tenant_id', $tenantId)->findOrFail($prId);
            $pr->status = 'approved';
            $pr->approved_by = $approver->id;
            $pr->approved_at = now();
            $pr->save();

            return $pr->load(['requester', 'approver', 'warehouse', 'items.product']);
        });
    }

    /**
     * Reject purchase request.
     */
    public function rejectPurchaseRequest(int $tenantId, int $prId, string $reason, User $approver): PurchaseRequest
    {
        return DB::transaction(function () use ($tenantId, $prId, $reason, $approver) {
            $pr = PurchaseRequest::where('tenant_id', $tenantId)->findOrFail($prId);
            $pr->status = 'rejected';
            $pr->approved_by = $approver->id;
            $pr->approved_at = now();
            $pr->rejection_reason = $reason;
            $pr->save();

            return $pr->load(['requester', 'approver', 'warehouse', 'items.product']);
        });
    }

    /**
     * Create a new purchase order with line items.
     * Optionally links to an approved purchase request.
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
                'revision_number' => 0,
                'purchase_request_id' => $data['purchase_request_id'] ?? null,
                'supplier_id' => (int) $data['supplier_id'],
                'warehouse_id' => (int) $data['warehouse_id'],
                'branch_id' => $data['branch_id'] ?? null,
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'status' => $data['status'] ?? 'ordered',
                'approval_status' => $data['approval_status'] ?? 'approved',
                'approved_by' => $user?->id,
                'approved_at' => now(),
                'currency' => $data['currency'] ?? 'INR',
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

                // Track incoming stock on warehouse product_stock
                $stock = ProductStock::firstOrCreate([
                    'tenant_id' => $tenantId,
                    'product_id' => (int) $item['product_id'],
                    'product_variant_id' => !empty($item['product_variant_id']) ? (int) $item['product_variant_id'] : null,
                    'warehouse_id' => (int) $data['warehouse_id'],
                ]);
                $stock->incoming_stock = (float) $stock->incoming_stock + $qty;
                $stock->save();
            }

            // Update linked PR status to ordered if applicable
            if (!empty($data['purchase_request_id'])) {
                PurchaseRequest::where('tenant_id', $tenantId)
                    ->where('id', $data['purchase_request_id'])
                    ->update(['status' => 'ordered']);
            }

            return $po->load(['supplier', 'warehouse', 'items.product']);
        });
    }

    /**
     * Approve purchase order.
     */
    public function approvePurchaseOrder(int $tenantId, int $poId, User $approver): PurchaseOrder
    {
        return DB::transaction(function () use ($tenantId, $poId, $approver) {
            $po = PurchaseOrder::where('tenant_id', $tenantId)->findOrFail($poId);
            $po->approval_status = 'approved';
            $po->approved_by = $approver->id;
            $po->approved_at = now();
            $po->status = 'ordered';
            $po->save();

            return $po->load(['supplier', 'warehouse', 'items.product']);
        });
    }

    /**
     * Revise purchase order.
     */
    public function revisePurchaseOrder(int $tenantId, int $poId, array $data, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($tenantId, $poId, $data, $user) {
            $po = PurchaseOrder::where('tenant_id', $tenantId)
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($poId);

            if ($po->status === 'received') {
                throw ValidationException::withMessages([
                    'status' => 'Cannot revise a completed received purchase order.',
                ]);
            }

            $po->revision_number = (int) $po->revision_number + 1;
            if (isset($data['notes'])) {
                $po->notes = $data['notes'];
            }
            if (isset($data['expected_delivery_date'])) {
                $po->expected_delivery_date = $data['expected_delivery_date'];
            }
            $po->save();

            return $po->load(['supplier', 'warehouse', 'items.product']);
        });
    }

    /**
     * Create Goods Receipt Note (GRN) & Inspect Incoming Stock.
     * Separates accepted units from rejected/damaged units.
     * Increments available stock only for accepted units.
     */
    public function receiveGoods(int $tenantId, int $purchaseOrderId, array $receivedData, User $user): GoodsReceiptNote
    {
        return DB::transaction(function () use ($tenantId, $purchaseOrderId, $receivedData, $user) {
            $po = PurchaseOrder::where('tenant_id', $tenantId)
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($purchaseOrderId);

            if ($po->status === 'received' || $po->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'status' => "Cannot receive goods for purchase order with status '{$po->status}'.",
                ]);
            }

            $grnNumber = 'GRN-' . date('Ymd') . '-' . strtoupper(Str::random(5));
            $receivedItems = $receivedData['items'] ?? [];

            $grn = GoodsReceiptNote::create([
                'tenant_id' => $tenantId,
                'grn_number' => $grnNumber,
                'purchase_order_id' => $po->id,
                'warehouse_id' => $po->warehouse_id,
                'received_by' => $user->id,
                'status' => 'accepted',
                'received_date' => $receivedData['received_date'] ?? now()->toDateString(),
                'challan_number' => $receivedData['challan_number'] ?? null,
                'invoice_reference' => $receivedData['invoice_reference'] ?? null,
                'qc_notes' => $receivedData['qc_notes'] ?? null,
            ]);

            $hasRejected = false;

            foreach ($receivedItems as $rec) {
                $itemId = (int) $rec['item_id'];
                $receivedQty = abs((float) $rec['quantity']);
                $acceptedQty = isset($rec['accepted_quantity']) ? abs((float) $rec['accepted_quantity']) : $receivedQty;
                $rejectedQty = isset($rec['rejected_damaged_quantity']) ? abs((float) $rec['rejected_damaged_quantity']) : max(0.0, $receivedQty - $acceptedQty);

                if ($rejectedQty > 0) {
                    $hasRejected = true;
                }

                $poItem = $po->items->firstWhere('id', $itemId);
                if (!$poItem) continue;

                // Update PO item received quantity
                $poItem->received_quantity = (float) $poItem->received_quantity + $receivedQty;
                $poItem->save();

                // Create GRN Item record
                GoodsReceiptNoteItem::create([
                    'goods_receipt_note_id' => $grn->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $poItem->product_id,
                    'product_variant_id' => $poItem->product_variant_id,
                    'ordered_quantity' => (float) $poItem->quantity,
                    'received_quantity' => $receivedQty,
                    'accepted_quantity' => $acceptedQty,
                    'rejected_damaged_quantity' => $rejectedQty,
                    'unit_cost' => (float) $poItem->unit_cost,
                    'rejection_reason' => $rec['rejection_reason'] ?? ($rejectedQty > 0 ? 'QC rejection/transit damage' : null),
                    'notes' => $rec['notes'] ?? null,
                ]);

                // 1. Add ACCEPTED quantity into physical stock and immutable movement ledger
                if ($acceptedQty > 0) {
                    $this->inventoryService->recordStockMovement([
                        'tenant_id' => $tenantId,
                        'product_id' => $poItem->product_id,
                        'product_variant_id' => $poItem->product_variant_id,
                        'warehouse_id' => $po->warehouse_id,
                        'movement_type' => 'purchase_receipt',
                        'quantity' => $acceptedQty,
                        'unit_cost' => (float) $poItem->unit_cost,
                        'reference_type' => 'goods_receipt_note',
                        'reference_id' => $grn->id,
                        'notes' => "GRN #{$grn->grn_number} for PO #{$po->po_number}",
                    ], $user);
                }

                // 2. Quarantine REJECTED/DAMAGED quantity: Log write-off, NEVER add to available stock
                if ($rejectedQty > 0) {
                    $damagedStock = ProductStock::firstOrCreate([
                        'tenant_id' => $tenantId,
                        'product_id' => $poItem->product_id,
                        'product_variant_id' => $poItem->product_variant_id,
                        'warehouse_id' => $po->warehouse_id,
                    ]);
                    $damagedStock->damaged_stock = (float) $damagedStock->damaged_stock + $rejectedQty;
                    $damagedStock->save();

                    \App\Domains\Inventory\Models\StockMovement::create([
                        'tenant_id' => $tenantId,
                        'product_id' => $poItem->product_id,
                        'product_variant_id' => $poItem->product_variant_id,
                        'warehouse_id' => $po->warehouse_id,
                        'movement_type' => 'damage_writeoff',
                        'quantity' => $rejectedQty,
                        'unit_cost' => (float) $poItem->unit_cost,
                        'total_cost' => round($rejectedQty * (float) $poItem->unit_cost, 2),
                        'before_stock' => (float) $damagedStock->current_stock,
                        'after_stock' => (float) $damagedStock->current_stock,
                        'reference_type' => 'goods_receipt_note',
                        'reference_id' => $grn->id,
                        'user_id' => $user->id,
                        'notes' => "Quarantine rejected goods from GRN #{$grn->grn_number}",
                    ]);
                }
            }

            // Check if PO is completely fulfilled
            $allCompleted = true;
            foreach ($po->items as $item) {
                if ((float) $item->received_quantity < (float) $item->quantity) {
                    $allCompleted = false;
                    break;
                }
            }

            $po->status = $allCompleted ? 'received' : 'partially_received';
            if ($allCompleted) {
                $po->received_at = now();
            }
            $po->save();

            $grn->status = $hasRejected ? 'partially_accepted' : 'accepted';
            $grn->save();

            return $grn->load(['purchaseOrder.supplier', 'warehouse', 'items.product']);
        });
    }

    /**
     * Generate Vendor Bill from Purchase Order and link to Finance module.
     */
    public function generateVendorBill(int $tenantId, int $purchaseOrderId, array $billData, User $user): VendorBill
    {
        return DB::transaction(function () use ($tenantId, $purchaseOrderId, $billData, $user) {
            $po = PurchaseOrder::where('tenant_id', $tenantId)->findOrFail($purchaseOrderId);

            if ($po->bill_id) {
                return VendorBill::findOrFail($po->bill_id);
            }

            $billNumber = $billData['bill_number'] ?? ('BILL-' . date('Ymd') . '-' . strtoupper(Str::random(5)));
            $dueDate = $billData['due_date'] ?? now()->addDays(30)->toDateString();

            $bill = VendorBill::create([
                'tenant_id' => $tenantId,
                'bill_number' => $billNumber,
                'supplier_id' => $po->supplier_id,
                'purchase_order_id' => $po->id,
                'bill_date' => $billData['bill_date'] ?? now()->toDateString(),
                'due_date' => $dueDate,
                'subtotal' => $po->subtotal,
                'tax_amount' => $po->tax_amount,
                'total_amount' => $po->total_amount,
                'paid_amount' => 0,
                'outstanding_amount' => $po->total_amount,
                'currency' => $po->currency,
                'status' => 'approved',
                'approved_by_user_id' => $user->id,
                'approved_at' => now(),
                'notes' => $billData['notes'] ?? "Auto-generated vendor bill from PO #{$po->po_number}",
            ]);

            $po->bill_id = $bill->id;
            $po->save();

            // Increment vendor outstanding balance
            $supplier = Supplier::where('tenant_id', $tenantId)->find($po->supplier_id);
            if ($supplier) {
                $supplier->outstanding_balance = (float) $supplier->outstanding_balance + (float) $po->total_amount;
                $supplier->save();
            }

            return $bill;
        });
    }
}
