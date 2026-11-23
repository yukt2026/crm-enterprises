<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Inventory\Models\ProductCategory;
use App\Domains\Inventory\Models\ProductVariant;
use App\Domains\Inventory\Services\InventoryService;
use App\Domains\Sales\Models\Product;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductCatalogController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected InventoryService $inventoryService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * List products and services with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $query = Product::where('tenant_id', $tenantId)
            ->with(['categoryModel', 'stocks.warehouse', 'variants'])
            ->orderBy('name');

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $term = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('sku', 'like', $term)
                  ->orWhere('brand', 'like', $term);
            });
        }

        $perPage = min((int) $request->query('per_page', 25), 100);
        $products = $query->paginate($perPage);

        return $this->successResponse(
            $products->items(),
            'Products retrieved successfully.',
            200,
            [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ]
        );
    }

    /**
     * Create a new product or service.
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|in:product,service',
            'sku' => 'nullable|string|max:100',
            'category_id' => 'nullable|exists:product_categories,id',
            'category' => 'nullable|string|max:100',
            'brand' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:50',
            'secondary_unit' => 'nullable|string|max:50',
            'unit_conversion_factor' => 'nullable|numeric|min:0.0001',
            'price' => 'required|numeric|min:0',
            'min_selling_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'hsn_sac_code' => 'nullable|string|max:50',
            'barcode' => 'nullable|string|max:100',
            'reorder_level' => 'nullable|numeric|min:0',
            'reorder_quantity' => 'nullable|numeric|min:0',
            'track_inventory' => 'nullable|boolean',
            'allow_negative_stock' => 'nullable|boolean',
            'service_duration' => 'nullable|string|max:100',
            'service_delivery_type' => 'nullable|string|max:100',
            'status' => 'nullable|in:active,inactive',
            'description' => 'nullable|string',
            'opening_stock' => 'nullable|numeric|min:0',
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        $sku = $validated['sku'] ?? ('SKU-' . strtoupper(Str::random(8)));
        $type = $validated['type'] ?? 'product';

        $product = Product::create([
            'tenant_id' => $tenantId,
            'type' => $type,
            'name' => $validated['name'],
            'sku' => $sku,
            'category_id' => $validated['category_id'] ?? null,
            'category' => $validated['category'] ?? 'General',
            'brand' => $validated['brand'] ?? null,
            'unit' => $validated['unit'] ?? ($type === 'service' ? 'hrs' : 'pcs'),
            'secondary_unit' => $validated['secondary_unit'] ?? null,
            'unit_conversion_factor' => $validated['unit_conversion_factor'] ?? 1.0000,
            'price' => $validated['price'],
            'min_selling_price' => $validated['min_selling_price'] ?? null,
            'cost_price' => $validated['cost_price'] ?? 0,
            'tax_rate' => $validated['tax_rate'] ?? 0,
            'hsn_sac_code' => $validated['hsn_sac_code'] ?? null,
            'barcode' => $validated['barcode'] ?? null,
            'reorder_level' => $validated['reorder_level'] ?? 10.00,
            'reorder_quantity' => $validated['reorder_quantity'] ?? 50.00,
            'track_inventory' => $type === 'service' ? false : ($validated['track_inventory'] ?? true),
            'allow_negative_stock' => $validated['allow_negative_stock'] ?? false,
            'is_archived' => false,
            'service_duration' => $validated['service_duration'] ?? null,
            'service_delivery_type' => $validated['service_delivery_type'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'description' => $validated['description'] ?? null,
        ]);

        // Record opening stock if provided for tangible product
        if ($type === 'product' && !empty($validated['opening_stock']) && $validated['opening_stock'] > 0) {
            $warehouseId = $validated['warehouse_id'] ?? $this->inventoryService->ensureDefaultWarehouse($tenantId)->id;

            $this->inventoryService->recordStockMovement([
                'tenant_id' => $tenantId,
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'movement_type' => 'opening_stock',
                'quantity' => (float) $validated['opening_stock'],
                'unit_cost' => (float) ($validated['cost_price'] ?? 0),
                'notes' => 'Initial opening stock upon item creation',
            ], $request->user());
        }

        return $this->successResponse(
            $product->load(['categoryModel', 'stocks.warehouse', 'variants']),
            'Product created successfully.',
            201
        );
    }

    /**
     * Get single product details.
     */
    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $product = Product::where('tenant_id', $tenantId)
            ->with(['categoryModel', 'stocks.warehouse', 'variants', 'movements.warehouse'])
            ->findOrFail($id);

        return $this->successResponse($product);
    }

    /**
     * Update product or service.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $product = Product::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'sku' => 'sometimes|nullable|string|max:100',
            'category_id' => 'nullable|exists:product_categories,id',
            'brand' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:50',
            'price' => 'sometimes|required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'hsn_sac_code' => 'nullable|string|max:50',
            'barcode' => 'nullable|string|max:100',
            'track_inventory' => 'nullable|boolean',
            'allow_negative_stock' => 'nullable|boolean',
            'service_duration' => 'nullable|string|max:100',
            'service_delivery_type' => 'nullable|string|max:100',
            'status' => 'nullable|in:active,inactive',
            'description' => 'nullable|string',
        ]);

        $product->update($validated);

        return $this->successResponse($product->fresh(['categoryModel', 'stocks.warehouse', 'variants']), 'Product updated.');
    }

    /**
     * Delete product.
     */
    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $product = Product::where('tenant_id', $tenantId)->findOrFail($id);
        $product->delete();

        return $this->successResponse(null, 'Product deleted.');
    }

    /**
     * List product categories.
     */
    public function categories(): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $categories = ProductCategory::where('tenant_id', $tenantId)
            ->withCount('products')
            ->orderBy('name')
            ->get();

        return $this->successResponse($categories);
    }

    /**
     * Create product category.
     */
    public function createCategory(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'parent_id' => 'nullable|exists:product_categories,id',
            'description' => 'nullable|string',
        ]);

        $category = ProductCategory::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'parent_id' => $validated['parent_id'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return $this->successResponse($category, 'Category created.', 201);
    }

    /**
     * Create product variant.
     */
    public function createVariant(Request $request, int $productId): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $product = Product::where('tenant_id', $tenantId)->findOrFail($productId);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'sku' => 'nullable|string|max:100',
            'attribute_values' => 'nullable|array',
            'sale_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'barcode' => 'nullable|string|max:100',
        ]);

        $variant = ProductVariant::create([
            'tenant_id' => $tenantId,
            'product_id' => $product->id,
            'name' => $validated['name'],
            'sku' => $validated['sku'] ?? ($product->sku . '-' . strtoupper(Str::random(4))),
            'attribute_values' => $validated['attribute_values'] ?? [],
            'sale_price' => $validated['sale_price'] ?? $product->price,
            'cost_price' => $validated['cost_price'] ?? $product->cost_price,
            'barcode' => $validated['barcode'] ?? null,
        ]);

        return $this->successResponse($variant, 'Variant created.', 201);
    }

    /**
     * Archive product.
     */
    public function archive(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $product = Product::where('tenant_id', $tenantId)->findOrFail($id);
        $product->is_archived = true;
        $product->save();

        return $this->successResponse($product, 'Product archived successfully.');
    }

    /**
     * Unarchive product.
     */
    public function unarchive(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $product = Product::where('tenant_id', $tenantId)->findOrFail($id);
        $product->is_archived = false;
        $product->save();

        return $this->successResponse($product, 'Product unarchived successfully.');
    }

    /**
     * Check real-time stock availability for quotation / order line items.
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_variant_id' => 'nullable',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.warehouse_id' => 'nullable|exists:warehouses,id',
            'preferred_warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        $res = $this->inventoryService->checkStockAvailability(
            $tenantId,
            $validated['items'],
            $validated['preferred_warehouse_id'] ?? null
        );

        return $this->successResponse($res, 'Stock availability computed successfully.');
    }

    /**
     * Export products to CSV format.
     */
    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $tenantId = $this->getTenantId();
        $products = Product::where('tenant_id', $tenantId)->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="products_export_' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($products) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Name', 'Type', 'SKU', 'Barcode', 'Category', 'Brand', 'Unit', 'Price', 'Min Selling Price', 'Cost Price', 'Tax Rate', 'HSN/SAC', 'Track Inventory', 'Reorder Level', 'Reorder Quantity', 'Status']);

            foreach ($products as $p) {
                fputcsv($handle, [
                    $p->id,
                    $p->name,
                    $p->type,
                    $p->sku,
                    $p->barcode,
                    $p->category,
                    $p->brand,
                    $p->unit,
                    $p->price,
                    $p->min_selling_price,
                    $p->cost_price,
                    $p->tax_rate,
                    $p->hsn_sac_code,
                    $p->track_inventory ? 'Yes' : 'No',
                    $p->reorder_level,
                    $p->reorder_quantity,
                    $p->status,
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Import products from CSV data.
     */
    public function importCsv(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'products' => 'required|array|min:1',
            'products.*.name' => 'required|string|max:255',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.sku' => 'nullable|string',
            'products.*.cost_price' => 'nullable|numeric',
            'products.*.type' => 'nullable|in:product,service',
        ]);

        $imported = [];
        foreach ($validated['products'] as $row) {
            $sku = !empty($row['sku']) ? $row['sku'] : ('SKU-' . strtoupper(Str::random(8)));
            $product = Product::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'sku' => $sku,
                ],
                [
                    'name' => $row['name'],
                    'type' => $row['type'] ?? 'product',
                    'category' => $row['category'] ?? 'General',
                    'brand' => $row['brand'] ?? null,
                    'unit' => $row['unit'] ?? 'pcs',
                    'price' => $row['price'],
                    'cost_price' => $row['cost_price'] ?? 0,
                    'tax_rate' => $row['tax_rate'] ?? 0,
                    'hsn_sac_code' => $row['hsn_sac_code'] ?? null,
                    'barcode' => $row['barcode'] ?? null,
                    'track_inventory' => ($row['type'] ?? 'product') !== 'service',
                    'status' => 'active',
                ]
            );
            $imported[] = $product;
        }

        return $this->successResponse([
            'imported_count' => count($imported),
            'products' => $imported,
        ], 'Products imported successfully.');
    }
}
