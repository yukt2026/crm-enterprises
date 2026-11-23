<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Communications\Services\NotificationService;
use App\Domains\Inventory\Models\ProductStock;
use App\Domains\Inventory\Models\StockAdjustment;
use App\Domains\Inventory\Models\StockAdjustmentItem;
use App\Domains\Inventory\Models\StockMovement;
use App\Domains\Inventory\Models\StockReconciliation;
use App\Domains\Inventory\Models\StockReconciliationItem;
use App\Domains\Inventory\Models\StockTransfer;
use App\Domains\Inventory\Models\StockTransferItem;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Sales\Models\Product;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(
        protected NotificationService $notificationService,
        protected InventorySettingService $settingService
    ) {}

    /**
     * Ensure a default warehouse exists for the tenant.
     */
    public function ensureDefaultWarehouse(int $tenantId): Warehouse
    {
        $warehouse = Warehouse::where('tenant_id', $tenantId)->where('is_primary', true)->first();
        if ($warehouse) {
            return $warehouse;
        }

        $existing = Warehouse::where('tenant_id', $tenantId)->first();
        if ($existing) {
            return $existing;
        }

        return Warehouse::create([
            'tenant_id' => $tenantId,
            'name' => 'Main Central Warehouse',
            'code' => 'WH-MAIN',
            'is_primary' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Record a stock movement with pessimistic locking and create an immutable ledger entry.
     * NEVER updates stock without a corresponding ledger entry.
     */
    public function recordStockMovement(array $params, ?User $user = null): StockMovement
    {
        return DB::transaction(function () use ($params, $user) {
            $tenantId = (int) $params['tenant_id'];
            $productId = (int) $params['product_id'];
            $variantId = !empty($params['product_variant_id']) ? (int) $params['product_variant_id'] : null;
            $warehouseId = (int) $params['warehouse_id'];
            $destWarehouseId = !empty($params['destination_warehouse_id']) ? (int) $params['destination_warehouse_id'] : null;
            $type = (string) $params['movement_type'];
            $quantity = abs((float) $params['quantity']);
            $unitCost = isset($params['unit_cost']) ? (float) $params['unit_cost'] : 0.0;
            $refType = $params['reference_type'] ?? null;
            $refId = $params['reference_id'] ?? null;
            $notes = $params['notes'] ?? null;
            $userId = $user?->id ?? ($params['user_id'] ?? null);

            $product = Product::where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($productId);

            // Skip inventory tracking if it is a service or untracked product
            if ($product->type === 'service' || !$product->track_inventory) {
                return new StockMovement([
                    'tenant_id' => $tenantId,
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'movement_type' => $type,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'total_cost' => round($quantity * $unitCost, 2),
                    'before_stock' => 0,
                    'after_stock' => 0,
                    'reference_type' => $refType,
                    'reference_id' => $refId,
                    'user_id' => $userId,
                    'notes' => $notes ?: 'Non-inventory item transaction',
                ]);
            }

            if ($unitCost <= 0 && $product->cost_price > 0) {
                $unitCost = (float) $product->cost_price;
            }

            // Check tenant setting for negative stock
            $setting = $this->settingService->getSettings($tenantId);
            $allowNegativeStock = $product->allow_negative_stock || $setting->allow_negative_stock;

            // Pessimistic lock on ProductStock
            $stockQuery = ProductStock::where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId);

            if ($variantId) {
                $stockQuery->where('product_variant_id', $variantId);
            } else {
                $stockQuery->whereNull('product_variant_id');
            }

            $stock = $stockQuery->lockForUpdate()->first();

            if (!$stock) {
                $stock = new ProductStock([
                    'tenant_id' => $tenantId,
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'warehouse_id' => $warehouseId,
                    'current_stock' => 0,
                    'reserved_stock' => 0,
                    'minimum_stock' => $product->reorder_level ?? 10,
                    'reorder_level' => $product->reorder_level ?? 10,
                    'damaged_stock' => 0,
                    'returned_stock' => 0,
                ]);
            }

            $beforeStock = (float) $stock->current_stock;

            // Handle operation categories
            switch ($type) {
                case 'opening_stock':
                case 'purchase_receipt':
                case 'sale_return':
                case 'adjustment_increase':
                case 'transfer_in':
                case 'stock_reconciliation_adj':
                    $stock->current_stock = $beforeStock + $quantity;
                    if ($type === 'purchase_receipt' && $stock->incoming_stock > 0) {
                        $stock->incoming_stock = max(0.0, (float) $stock->incoming_stock - $quantity);
                    }
                    if ($type === 'sale_return') {
                        $stock->returned_stock = (float) $stock->returned_stock + $quantity;
                    }
                    break;

                case 'sale_deduction':
                case 'adjustment_decrease':
                case 'transfer_out':
                case 'internal_issue':
                    if ($beforeStock < $quantity && !$allowNegativeStock) {
                        throw ValidationException::withMessages([
                            'quantity' => "Insufficient stock for product '{$product->name}'. Available: {$beforeStock}, requested: {$quantity}",
                        ]);
                    }
                    $stock->current_stock = $beforeStock - $quantity;
                    break;

                case 'damage_writeoff':
                    if ($beforeStock < $quantity && !$allowNegativeStock) {
                        throw ValidationException::withMessages([
                            'quantity' => "Insufficient stock to record damage. Available: {$beforeStock}, requested: {$quantity}",
                        ]);
                    }
                    $stock->current_stock = $beforeStock - $quantity;
                    $stock->damaged_stock = (float) $stock->damaged_stock + $quantity;
                    break;

                case 'stock_reserved':
                    $available = (float) $stock->current_stock - (float) $stock->reserved_stock;
                    if ($available < $quantity && !$allowNegativeStock) {
                        throw ValidationException::withMessages([
                            'quantity' => "Insufficient available stock to reserve for '{$product->name}'. Available: {$available}, requested: {$quantity}",
                        ]);
                    }
                    $stock->reserved_stock = (float) $stock->reserved_stock + $quantity;
                    break;

                case 'stock_released':
                    $stock->reserved_stock = max(0.0, (float) $stock->reserved_stock - $quantity);
                    break;

                default:
                    throw new \InvalidArgumentException("Unsupported stock movement type: {$type}");
            }

            $afterStock = (float) $stock->current_stock;
            $stock->save();

            // Create immutable stock movement record
            $movement = StockMovement::create([
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'warehouse_id' => $warehouseId,
                'destination_warehouse_id' => $destWarehouseId,
                'movement_type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => round($quantity * $unitCost, 2),
                'before_stock' => $beforeStock,
                'after_stock' => $afterStock,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'user_id' => $userId,
                'notes' => $notes,
            ]);

            // Low stock trigger alert
            $minThreshold = $stock->minimum_stock > 0 ? $stock->minimum_stock : ($product->reorder_level ?? 0);
            if ($minThreshold > 0 && $afterStock <= $minThreshold && $user && $setting->low_stock_notification) {
                $this->notificationService->notify(
                    recipient: $user,
                    type: 'inventory.low_stock',
                    title: "Low Stock Alert: {$product->name}",
                    message: "Product '{$product->name}' (SKU: {$product->sku}) is at {$afterStock} units in warehouse (threshold: {$minThreshold}).",
                    actionUrl: "/catalog",
                    data: ['product_id' => $product->id, 'warehouse_id' => $warehouseId, 'stock' => $afterStock]
                );
            }

            return $movement;
        });
    }

    /**
     * Reserve stock for a quotation or sales order.
     */
    public function reserveStock(
        int $tenantId,
        int $productId,
        ?int $variantId,
        int $warehouseId,
        float $quantity,
        ?string $refType = null,
        ?int $refId = null,
        ?User $user = null
    ): StockMovement {
        return $this->recordStockMovement([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
            'movement_type' => 'stock_reserved',
            'quantity' => $quantity,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'notes' => "Stock reserved for {$refType} #{$refId}",
        ], $user);
    }

    /**
     * Release reserved stock when a quotation is expired/rejected or order cancelled.
     */
    public function releaseStock(
        int $tenantId,
        int $productId,
        ?int $variantId,
        int $warehouseId,
        float $quantity,
        ?string $refType = null,
        ?int $refId = null,
        ?User $user = null
    ): StockMovement {
        return $this->recordStockMovement([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
            'movement_type' => 'stock_released',
            'quantity' => $quantity,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'notes' => "Stock released for {$refType} #{$refId}",
        ], $user);
    }

    /**
     * Fulfill and deduct stock when an order is dispatched or invoice finalized.
     * Also releases any reserved quantity previously held for that item.
     */
    public function fulfillStock(
        int $tenantId,
        int $productId,
        ?int $variantId,
        int $warehouseId,
        float $quantity,
        ?string $refType = null,
        ?int $refId = null,
        ?User $user = null
    ): StockMovement {
        return DB::transaction(function () use ($tenantId, $productId, $variantId, $warehouseId, $quantity, $refType, $refId, $user) {
            // First release reservation if held
            $this->recordStockMovement([
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'warehouse_id' => $warehouseId,
                'movement_type' => 'stock_released',
                'quantity' => $quantity,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'notes' => "Reservation cleared upon fulfillment",
            ], $user);

            // Deduct from actual physical stock
            return $this->recordStockMovement([
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'warehouse_id' => $warehouseId,
                'movement_type' => 'sale_deduction',
                'quantity' => $quantity,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'notes' => "Order fulfillment deduction for {$refType} #{$refId}",
            ], $user);
        });
    }

    /**
     * Issue stock for internal consumption, work order, or sample testing.
     */
    public function issueStock(
        int $tenantId,
        int $productId,
        ?int $variantId,
        int $warehouseId,
        float $quantity,
        string $reason,
        ?User $user = null
    ): StockMovement {
        return $this->recordStockMovement([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
            'movement_type' => 'internal_issue',
            'quantity' => $quantity,
            'reference_type' => 'internal_issue',
            'notes' => $reason,
        ], $user);
    }

    /**
     * Check real-time stock availability for a list of items across warehouses.
     */
    public function checkStockAvailability(int $tenantId, array $items, ?int $preferredWarehouseId = null): array
    {
        $defaultWh = $preferredWarehouseId ?? $this->ensureDefaultWarehouse($tenantId)->id;
        $results = [];
        $allAvailable = true;

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $variantId = !empty($item['product_variant_id']) ? (int) $item['product_variant_id'] : null;
            $requestedQty = abs((float) ($item['quantity'] ?? 1));
            $warehouseId = !empty($item['warehouse_id']) ? (int) $item['warehouse_id'] : $defaultWh;

            $product = Product::where('tenant_id', $tenantId)->find($productId);
            if (!$product || $product->type === 'service' || !$product->track_inventory) {
                $results[] = [
                    'product_id' => $productId,
                    'product_name' => $product?->name ?? 'Unknown Item',
                    'is_service' => true,
                    'available' => true,
                    'current_stock' => 99999,
                    'available_stock' => 99999,
                    'requested_quantity' => $requestedQty,
                    'deficit' => 0,
                ];
                continue;
            }

            $stock = ProductStock::where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->when($variantId, fn($q) => $q->where('product_variant_id', $variantId))
                ->when(!$variantId, fn($q) => $q->whereNull('product_variant_id'))
                ->first();

            $currentStock = (float) ($stock?->current_stock ?? 0);
            $reservedStock = (float) ($stock?->reserved_stock ?? 0);
            $availableStock = max(0.0, $currentStock - $reservedStock);
            $isSufficient = $availableStock >= $requestedQty || $product->allow_negative_stock;

            if (!$isSufficient) {
                $allAvailable = false;
            }

            $results[] = [
                'product_id' => $productId,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'warehouse_id' => $warehouseId,
                'is_service' => false,
                'available' => $isSufficient,
                'current_stock' => $currentStock,
                'reserved_stock' => $reservedStock,
                'available_stock' => $availableStock,
                'requested_quantity' => $requestedQty,
                'deficit' => max(0.0, $requestedQty - $availableStock),
            ];
        }

        return [
            'is_fully_available' => $allAvailable,
            'items' => $results,
        ];
    }

    /**
     * Process multi-item manual stock adjustment.
     */
    public function processAdjustment(int $tenantId, int $warehouseId, string $reason, array $items, ?User $user = null): StockAdjustment
    {
        return DB::transaction(function () use ($tenantId, $warehouseId, $reason, $items, $user) {
            $adjustmentNumber = 'ADJ-' . date('Ymd') . '-' . strtoupper(Str::random(5));

            $adjustment = StockAdjustment::create([
                'tenant_id' => $tenantId,
                'warehouse_id' => $warehouseId,
                'adjustment_number' => $adjustmentNumber,
                'reason' => $reason,
                'status' => 'approved',
                'user_id' => $user?->id,
                'approved_by' => $user?->id,
            ]);

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $variantId = !empty($item['product_variant_id']) ? (int) $item['product_variant_id'] : null;
                $adjType = (string) $item['adjustment_type']; // increase, decrease, damage
                $qty = abs((float) $item['quantity']);
                $unitCost = (float) ($item['unit_cost'] ?? 0);

                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'adjustment_type' => $adjType,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'notes' => $item['notes'] ?? null,
                ]);

                $movementType = match ($adjType) {
                    'increase' => 'adjustment_increase',
                    'decrease' => 'adjustment_decrease',
                    'damage' => 'damage_writeoff',
                };

                $this->recordStockMovement([
                    'tenant_id' => $tenantId,
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'warehouse_id' => $warehouseId,
                    'movement_type' => $movementType,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'reference_type' => 'stock_adjustment',
                    'reference_id' => $adjustment->id,
                    'notes' => "Manual adjustment: {$reason}",
                ], $user);
            }

            return $adjustment->load('items.product');
        });
    }

    /**
     * Inter-warehouse Stock Transfer Workflow:
     * 1. Request / Create Transfer (Draft / Pending Approval)
     * 2. Approve Transfer
     * 3. Dispatch Transfer (In Transit, Deducts source stock)
     * 4. Receive Transfer (Receipt Confirmation + Damaged Quarantine Handling)
     */
    public function createTransferRequest(
        int $tenantId,
        int $sourceWarehouseId,
        int $destWarehouseId,
        array $items,
        ?User $user = null,
        ?string $notes = null
    ): StockTransfer {
        if ($sourceWarehouseId === $destWarehouseId) {
            throw ValidationException::withMessages([
                'destination_warehouse_id' => 'Destination warehouse cannot be the same as source warehouse.',
            ]);
        }

        return DB::transaction(function () use ($tenantId, $sourceWarehouseId, $destWarehouseId, $items, $user, $notes) {
            $transferNumber = 'TRF-' . date('Ymd') . '-' . strtoupper(Str::random(5));

            $transfer = StockTransfer::create([
                'tenant_id' => $tenantId,
                'source_warehouse_id' => $sourceWarehouseId,
                'destination_warehouse_id' => $destWarehouseId,
                'transfer_number' => $transferNumber,
                'status' => 'draft',
                'user_id' => $user?->id,
                'notes' => $notes,
            ]);

            foreach ($items as $item) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => (int) $item['product_id'],
                    'product_variant_id' => !empty($item['product_variant_id']) ? (int) $item['product_variant_id'] : null,
                    'quantity' => abs((float) $item['quantity']),
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $transfer->load(['sourceWarehouse', 'destinationWarehouse', 'items.product']);
        });
    }

    /**
     * Approve stock transfer.
     */
    public function approveTransfer(int $tenantId, int $transferId, User $approver): StockTransfer
    {
        return DB::transaction(function () use ($tenantId, $transferId, $approver) {
            $transfer = StockTransfer::where('tenant_id', $tenantId)->findOrFail($transferId);
            $transfer->approved_by = $approver->id;
            $transfer->approved_at = now();
            $transfer->status = 'approved';
            $transfer->save();

            return $transfer->load(['sourceWarehouse', 'destinationWarehouse', 'items.product']);
        });
    }

    /**
     * Dispatch stock transfer: Deducts source stock and marks status 'in_transit'.
     */
    public function dispatchTransfer(int $tenantId, int $transferId, User $dispatcher): StockTransfer
    {
        return DB::transaction(function () use ($tenantId, $transferId, $dispatcher) {
            $transfer = StockTransfer::where('tenant_id', $tenantId)
                ->with('items.product')
                ->lockForUpdate()
                ->findOrFail($transferId);

            if ($transfer->status === 'in_transit' || $transfer->status === 'completed') {
                return $transfer;
            }

            foreach ($transfer->items as $item) {
                // Deduct from source warehouse
                $this->recordStockMovement([
                    'tenant_id' => $tenantId,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $transfer->source_warehouse_id,
                    'destination_warehouse_id' => $transfer->destination_warehouse_id,
                    'movement_type' => 'transfer_out',
                    'quantity' => (float) $item->quantity,
                    'reference_type' => 'stock_transfer',
                    'reference_id' => $transfer->id,
                    'notes' => "Transfer dispatched to destination warehouse #{$transfer->destination_warehouse_id}",
                ], $dispatcher);
            }

            $transfer->dispatched_by = $dispatcher->id;
            $transfer->shipped_at = now();
            $transfer->status = 'in_transit';
            $transfer->save();

            return $transfer;
        });
    }

    /**
     * Receive stock transfer at destination warehouse.
     * Records transfer_in for sound units and damage_writeoff for damaged units (quarantined).
     */
    public function receiveTransfer(
        int $tenantId,
        int $transferId,
        array $receivedItems,
        ?float $totalDamaged = null,
        ?string $damageNotes = null,
        ?User $receiver = null
    ): StockTransfer {
        return DB::transaction(function () use ($tenantId, $transferId, $receivedItems, $totalDamaged, $damageNotes, $receiver) {
            $transfer = StockTransfer::where('tenant_id', $tenantId)
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($transferId);

            // If not dispatched yet, dispatch now
            if ($transfer->status !== 'in_transit') {
                $this->dispatchTransfer($tenantId, $transferId, $receiver ?? $transfer->user);
            }

            $overallDamaged = 0.0;

            foreach ($receivedItems as $rec) {
                $itemId = (int) $rec['item_id'];
                $acceptedQty = abs((float) ($rec['accepted_quantity'] ?? $rec['quantity']));
                $damagedQty = abs((float) ($rec['damaged_quantity'] ?? 0));

                $item = $transfer->items->firstWhere('id', $itemId);
                if (!$item) continue;

                $item->received_quantity = $acceptedQty;
                $item->damaged_quantity = $damagedQty;
                $item->save();

                $overallDamaged += $damagedQty;

                // 1. Add accepted quantity to destination warehouse available stock
                if ($acceptedQty > 0) {
                    $this->recordStockMovement([
                        'tenant_id' => $tenantId,
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'warehouse_id' => $transfer->destination_warehouse_id,
                        'destination_warehouse_id' => $transfer->source_warehouse_id,
                        'movement_type' => 'transfer_in',
                        'quantity' => $acceptedQty,
                        'reference_type' => 'stock_transfer',
                        'reference_id' => $transfer->id,
                        'notes' => "Transfer received from source warehouse #{$transfer->source_warehouse_id}",
                    ], $receiver);
                }

                // 2. Quarantine damaged units: Add to damaged stock at destination, do not make available!
                if ($damagedQty > 0) {
                    $this->recordStockMovement([
                        'tenant_id' => $tenantId,
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'warehouse_id' => $transfer->destination_warehouse_id,
                        'movement_type' => 'transfer_in',
                        'quantity' => $damagedQty,
                        'reference_type' => 'stock_transfer',
                        'reference_id' => $transfer->id,
                        'notes' => "Transit damaged units received into quarantine",
                    ], $receiver);

                    $this->recordStockMovement([
                        'tenant_id' => $tenantId,
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'warehouse_id' => $transfer->destination_warehouse_id,
                        'movement_type' => 'damage_writeoff',
                        'quantity' => $damagedQty,
                        'reference_type' => 'stock_transfer',
                        'reference_id' => $transfer->id,
                        'notes' => "Quarantine damaged transfer write-off: {$damageNotes}",
                    ], $receiver);
                }
            }

            $transfer->received_by = $receiver?->id;
            $transfer->received_at = now();
            $transfer->damaged_quantity = $overallDamaged;
            $transfer->damage_notes = $damageNotes;
            $transfer->status = 'completed';
            $transfer->save();

            return $transfer->load(['sourceWarehouse', 'destinationWarehouse', 'items.product']);
        });
    }

    /**
     * Physical Stock Count & Reconciliation.
     */
    public function createStockReconciliation(
        int $tenantId,
        int $warehouseId,
        array $items,
        User $counter,
        ?string $notes = null
    ): StockReconciliation {
        return DB::transaction(function () use ($tenantId, $warehouseId, $items, $counter, $notes) {
            $reconciliationNumber = 'REC-' . date('Ymd') . '-' . strtoupper(Str::random(5));

            $reconciliation = StockReconciliation::create([
                'tenant_id' => $tenantId,
                'reconciliation_number' => $reconciliationNumber,
                'warehouse_id' => $warehouseId,
                'counted_by' => $counter->id,
                'status' => 'in_progress',
                'notes' => $notes,
            ]);

            $totalVarianceCost = 0.0;

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $variantId = !empty($item['product_variant_id']) ? (int) $item['product_variant_id'] : null;
                $physicalQty = abs((float) $item['physical_quantity']);

                $stock = ProductStock::where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->when($variantId, fn($q) => $q->where('product_variant_id', $variantId))
                    ->first();

                $systemQty = (float) ($stock?->current_stock ?? 0);
                $varianceQty = $physicalQty - $systemQty;

                $product = Product::find($productId);
                $unitCost = (float) ($product?->cost_price ?? 0);
                $varianceCost = round($varianceQty * $unitCost, 2);
                $totalVarianceCost += $varianceCost;

                StockReconciliationItem::create([
                    'stock_reconciliation_id' => $reconciliation->id,
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'system_quantity' => $systemQty,
                    'physical_quantity' => $physicalQty,
                    'variance_quantity' => $varianceQty,
                    'unit_cost' => $unitCost,
                    'variance_cost' => $varianceCost,
                    'reconciliation_action' => $item['reconciliation_action'] ?? 'adjusted',
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            $reconciliation->total_variance_cost = $totalVarianceCost;
            $reconciliation->save();

            return $reconciliation->load('items.product');
        });
    }

    /**
     * Apply stock reconciliation adjustments to actual stock.
     */
    public function applyStockReconciliation(int $tenantId, int $reconciliationId, User $reconciler): StockReconciliation
    {
        return DB::transaction(function () use ($tenantId, $reconciliationId, $reconciler) {
            $reconciliation = StockReconciliation::where('tenant_id', $tenantId)
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($reconciliationId);

            if ($reconciliation->status === 'completed') {
                return $reconciliation;
            }

            foreach ($reconciliation->items as $item) {
                if ($item->reconciliation_action !== 'adjusted' || $item->variance_quantity == 0) {
                    continue;
                }

                $variance = (float) $item->variance_quantity;
                $movementType = $variance > 0 ? 'adjustment_increase' : 'adjustment_decrease';

                $this->recordStockMovement([
                    'tenant_id' => $tenantId,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $reconciliation->warehouse_id,
                    'movement_type' => $movementType,
                    'quantity' => abs($variance),
                    'unit_cost' => (float) $item->unit_cost,
                    'reference_type' => 'stock_reconciliation',
                    'reference_id' => $reconciliation->id,
                    'notes' => "Physical count reconciliation #{$reconciliation->reconciliation_number}",
                ], $reconciler);
            }

            $reconciliation->reconciled_by = $reconciler->id;
            $reconciliation->reconciled_at = now();
            $reconciliation->status = 'completed';
            $reconciliation->save();

            return $reconciliation->load('items.product');
        });
    }

    /**
     * Current Stock Valuation & Metrics Report based on configured valuation method.
     */
    public function getValuationReport(int $tenantId, ?int $warehouseId = null): array
    {
        $setting = $this->settingService->getSettings($tenantId);
        $method = $setting->valuation_method; // moving_average, fifo, standard

        $query = ProductStock::where('product_stocks.tenant_id', $tenantId)
            ->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->where('products.track_inventory', true)
            ->where('products.is_archived', false);

        if ($warehouseId) {
            $query->where('product_stocks.warehouse_id', $warehouseId);
        }

        $stocks = $query->select(
            'product_stocks.*',
            'products.cost_price',
            'products.price as sale_price',
            'products.name as product_name',
            'products.sku as product_sku',
            'products.category'
        )->get();

        $totalUnits = (float) $stocks->sum('current_stock');
        $totalReserved = (float) $stocks->sum('reserved_stock');
        $totalDamaged = (float) $stocks->sum('damaged_stock');
        $totalValuation = 0.0;
        $totalRetailValuation = 0.0;

        foreach ($stocks as $s) {
            $qty = (float) $s->current_stock;
            $cost = (float) $s->cost_price;
            $sale = (float) $s->sale_price;

            $totalValuation += ($qty * $cost);
            $totalRetailValuation += ($qty * $sale);
        }

        // Warehouse breakdown
        $warehouseBreakdown = Warehouse::where('tenant_id', $tenantId)
            ->withCount(['stocks as total_skus'])
            ->get()
            ->map(function ($wh) use ($tenantId) {
                $whStocks = ProductStock::where('product_stocks.tenant_id', $tenantId)
                    ->where('product_stocks.warehouse_id', $wh->id)
                    ->with('product')
                    ->get();

                $whUnits = (float) $whStocks->sum('current_stock');
                $whValuation = (float) $whStocks->sum(fn($s) => (float) $s->current_stock * (float) ($s->product?->cost_price ?? 0));

                return [
                    'warehouse_id' => $wh->id,
                    'name' => $wh->name,
                    'code' => $wh->code,
                    'is_primary' => $wh->is_primary,
                    'capacity_sqft' => (float) $wh->capacity_sqft,
                    'capacity_units' => (float) $wh->capacity_units,
                    'utilization_percent' => $wh->capacity_units > 0 ? round(($whUnits / $wh->capacity_units) * 100, 1) : null,
                    'total_units' => $whUnits,
                    'total_valuation' => round($whValuation, 2),
                ];
            });

        return [
            'valuation_method' => $method,
            'total_skus' => count($stocks),
            'total_units' => $totalUnits,
            'total_reserved' => $totalReserved,
            'total_damaged' => $totalDamaged,
            'total_cost_valuation' => round($totalValuation, 2),
            'total_retail_valuation' => round($totalRetailValuation, 2),
            'unrealized_margin' => $totalRetailValuation > 0
                ? round((($totalRetailValuation - $totalValuation) / $totalRetailValuation) * 100, 1)
                : 0,
            'warehouses' => $warehouseBreakdown,
        ];
    }

    /**
     * Get Low Stock Alert items.
     */
    public function getLowStockAlerts(int $tenantId, ?int $warehouseId = null): Collection
    {
        $query = ProductStock::where('product_stocks.tenant_id', $tenantId)
            ->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->where('products.track_inventory', true)
            ->where('products.is_archived', false)
            ->where(function ($q) {
                $q->whereColumn('product_stocks.current_stock', '<=', 'product_stocks.minimum_stock')
                  ->orWhereColumn('product_stocks.current_stock', '<=', 'products.reorder_level');
            })
            ->with(['product', 'warehouse', 'variant']);

        if ($warehouseId) {
            $query->where('product_stocks.warehouse_id', $warehouseId);
        }

        return $query->select('product_stocks.*')->get()->map(function ($stock) {
            $threshold = max((float) $stock->minimum_stock, (float) ($stock->product?->reorder_level ?? 10));
            return [
                'stock_id' => $stock->id,
                'product_id' => $stock->product_id,
                'product_name' => $stock->product?->name,
                'sku' => $stock->variant?->sku ?? $stock->product?->sku,
                'warehouse_id' => $stock->warehouse_id,
                'warehouse_name' => $stock->warehouse?->name,
                'current_stock' => (float) $stock->current_stock,
                'minimum_stock' => $threshold,
                'reorder_quantity' => (float) ($stock->product?->reorder_quantity ?? 50),
                'deficit' => max(0.0, $threshold - (float) $stock->current_stock),
            ];
        });
    }

    /**
     * Get Reorder Report with suggested purchase order quantities.
     */
    public function getReorderReport(int $tenantId, ?int $warehouseId = null): array
    {
        $lowStocks = $this->getLowStockAlerts($tenantId, $warehouseId);
        $totalEstimatedReorderCost = 0.0;

        $items = $lowStocks->map(function ($item) use (&$totalEstimatedReorderCost) {
            $product = Product::find($item['product_id']);
            $cost = (float) ($product?->cost_price ?? 0);
            $suggestedQty = (float) $item['reorder_quantity'];
            $estCost = round($suggestedQty * $cost, 2);
            $totalEstimatedReorderCost += $estCost;

            return array_merge($item, [
                'unit_cost' => $cost,
                'suggested_reorder_quantity' => $suggestedQty,
                'estimated_reorder_cost' => $estCost,
            ]);
        });

        return [
            'total_items_to_reorder' => count($items),
            'total_estimated_cost' => round($totalEstimatedReorderCost, 2),
            'items' => $items,
        ];
    }

    /**
     * Product Profitability Report.
     */
    public function getProductProfitabilityReport(int $tenantId): array
    {
        $products = Product::where('tenant_id', $tenantId)
            ->where('is_archived', false)
            ->select('id', 'name', 'sku', 'category', 'type', 'cost_price', 'price as selling_price')
            ->get()
            ->map(function ($p) {
                $cost = (float) $p->cost_price;
                $selling = (float) $p->selling_price;
                $grossProfit = $selling - $cost;
                $marginPercent = $selling > 0 ? round(($grossProfit / $selling) * 100, 2) : 0;

                return [
                    'product_id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'category' => $p->category,
                    'type' => $p->type,
                    'cost_price' => $cost,
                    'selling_price' => $selling,
                    'gross_profit' => round($grossProfit, 2),
                    'gross_margin_percent' => $marginPercent,
                ];
            });

        return [
            'total_products' => count($products),
            'average_margin' => $products->count() > 0 ? round($products->avg('gross_margin_percent'), 1) : 0,
            'products' => $products,
        ];
    }

    /**
     * Query paginated immutable stock ledger.
     */
    public function getStockLedger(int $tenantId, array $filters = []): LengthAwarePaginator
    {
        $query = StockMovement::where('tenant_id', $tenantId)
            ->with(['product:id,name,sku,unit', 'variant:id,name,sku', 'warehouse:id,name,code', 'user:id,name,email'])
            ->orderByDesc('id');

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (!empty($filters['movement_type'])) {
            $query->where('movement_type', $filters['movement_type']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 25), 100);
        return $query->paginate($perPage);
    }
}
