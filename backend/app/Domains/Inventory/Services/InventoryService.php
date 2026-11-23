<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Communications\Services\NotificationService;
use App\Domains\Inventory\Models\ProductStock;
use App\Domains\Inventory\Models\StockAdjustment;
use App\Domains\Inventory\Models\StockAdjustmentItem;
use App\Domains\Inventory\Models\StockMovement;
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
        protected NotificationService $notificationService
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
            'name' => 'Main Warehouse',
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
                    'minimum_stock' => 0,
                    'damaged_stock' => 0,
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
                    $stock->current_stock = $beforeStock + $quantity;
                    break;

                case 'sale_deduction':
                case 'adjustment_decrease':
                case 'transfer_out':
                    if ($beforeStock < $quantity && !$product->allow_negative_stock) {
                        throw ValidationException::withMessages([
                            'quantity' => "Insufficient stock for product '{$product->name}'. Available: {$beforeStock}, requested: {$quantity}",
                        ]);
                    }
                    $stock->current_stock = $beforeStock - $quantity;
                    break;

                case 'damage_writeoff':
                    if ($beforeStock < $quantity && !$product->allow_negative_stock) {
                        throw ValidationException::withMessages([
                            'quantity' => "Insufficient stock to record damage. Available: {$beforeStock}, requested: {$quantity}",
                        ]);
                    }
                    $stock->current_stock = $beforeStock - $quantity;
                    $stock->damaged_stock = (float) $stock->damaged_stock + $quantity;
                    break;

                case 'stock_reserved':
                    $available = (float) $stock->current_stock - (float) $stock->reserved_stock;
                    if ($available < $quantity && !$product->allow_negative_stock) {
                        throw ValidationException::withMessages([
                            'quantity' => "Insufficient available stock to reserve. Available: {$available}, requested: {$quantity}",
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
            if ($stock->minimum_stock > 0 && $afterStock <= $stock->minimum_stock && $user) {
                $this->notificationService->notify(
                    user: $user,
                    type: 'inventory.low_stock',
                    title: "Low Stock Alert: {$product->name}",
                    message: "Product '{$product->name}' (SKU: {$product->sku}) is at {$afterStock} units in warehouse (threshold: {$stock->minimum_stock}).",
                    actionUrl: "/products",
                    data: ['product_id' => $product->id, 'warehouse_id' => $warehouseId, 'stock' => $afterStock]
                );
            }

            return $movement;
        });
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
     * Process inter-warehouse stock transfer.
     */
    public function processTransfer(
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
                'status' => 'completed',
                'user_id' => $user?->id,
                'shipped_at' => now(),
                'received_at' => now(),
                'notes' => $notes,
            ]);

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $variantId = !empty($item['product_variant_id']) ? (int) $item['product_variant_id'] : null;
                $qty = abs((float) $item['quantity']);

                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'quantity' => $qty,
                    'notes' => $item['notes'] ?? null,
                ]);

                // 1. Deduct from source warehouse
                $this->recordStockMovement([
                    'tenant_id' => $tenantId,
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'warehouse_id' => $sourceWarehouseId,
                    'destination_warehouse_id' => $destWarehouseId,
                    'movement_type' => 'transfer_out',
                    'quantity' => $qty,
                    'reference_type' => 'stock_transfer',
                    'reference_id' => $transfer->id,
                    'notes' => "Transfer out to destination warehouse #{$destWarehouseId}",
                ], $user);

                // 2. Add to destination warehouse
                $this->recordStockMovement([
                    'tenant_id' => $tenantId,
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'warehouse_id' => $destWarehouseId,
                    'destination_warehouse_id' => $sourceWarehouseId,
                    'movement_type' => 'transfer_in',
                    'quantity' => $qty,
                    'reference_type' => 'stock_transfer',
                    'reference_id' => $transfer->id,
                    'notes' => "Transfer in from source warehouse #{$sourceWarehouseId}",
                ], $user);
            }

            return $transfer->load(['sourceWarehouse', 'destinationWarehouse', 'items.product']);
        });
    }

    /**
     * Current Stock Valuation & Metrics Report.
     */
    public function getValuationReport(int $tenantId, ?int $warehouseId = null): array
    {
        $query = ProductStock::where('product_stocks.tenant_id', $tenantId)
            ->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->where('products.track_inventory', true);

        if ($warehouseId) {
            $query->where('product_stocks.warehouse_id', $warehouseId);
        }

        $stocks = $query->select(
            'product_stocks.*',
            'products.cost_price',
            'products.price as sale_price',
            'products.name as product_name',
            'products.sku as product_sku'
        )->get();

        $totalUnits = (float) $stocks->sum('current_stock');
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
                    'total_units' => $whUnits,
                    'total_valuation' => round($whValuation, 2),
                ];
            });

        return [
            'total_skus' => count($stocks),
            'total_units' => $totalUnits,
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
        $query = ProductStock::where('tenant_id', $tenantId)
            ->lowStock()
            ->with(['product', 'warehouse', 'variant']);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->get()->map(function ($stock) {
            return [
                'stock_id' => $stock->id,
                'product_id' => $stock->product_id,
                'product_name' => $stock->product?->name,
                'sku' => $stock->variant?->sku ?? $stock->product?->sku,
                'warehouse_id' => $stock->warehouse_id,
                'warehouse_name' => $stock->warehouse?->name,
                'current_stock' => (float) $stock->current_stock,
                'minimum_stock' => (float) $stock->minimum_stock,
                'deficit' => (float) ($stock->minimum_stock - $stock->current_stock),
            ];
        });
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

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);
        return $query->paginate($perPage);
    }
}
