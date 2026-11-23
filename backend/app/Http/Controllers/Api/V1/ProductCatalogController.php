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
            'price' => 'required|numeric|min:0',
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
            'price' => $validated['price'],
            'cost_price' => $validated['cost_price'] ?? 0,
            'tax_rate' => $validated['tax_rate'] ?? 0,
            'hsn_sac_code' => $validated['hsn_sac_code'] ?? null,
            'barcode' => $validated['barcode'] ?? null,
            'track_inventory' => $type === 'service' ? false : ($validated['track_inventory'] ?? true),
            'allow_negative_stock' => $validated['allow_negative_stock'] ?? false,
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
}
