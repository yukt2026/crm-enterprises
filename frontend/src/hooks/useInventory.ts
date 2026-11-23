import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useCRMStore } from '../store/crmStore';
import {
  ProductCatalogItem,
  ProductCategoryItem,
  ProductVariantItem,
  WarehouseItem,
  ProductStockItem,
  StockMovementItem,
  SupplierItem,
  PurchaseOrderRecord,
  InventoryValuationData,
  LowStockAlertItem,
} from '../api/types';

// ==========================================
// 1. PRODUCT & SERVICE CATALOG HOOKS
// ==========================================

export function useCatalog(params: {
  type?: 'product' | 'service';
  category_id?: number;
  status?: string;
  search?: string;
} = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['product-catalog', workspaceId, params],
    queryFn: async () => {
      const res = await api.getCatalog(params);
      return (res.data || []) as ProductCatalogItem[];
    },
  });
}

export function useCreateCatalogItem() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<ProductCatalogItem> & { opening_stock?: number; warehouse_id?: number }) =>
      api.createCatalogItem(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['product-catalog'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-stocks'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-valuation'] });
      addToast('Item added to catalog', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create item', 'error');
    },
  });
}

export function useUpdateCatalogItem() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<ProductCatalogItem> }) =>
      api.updateCatalogItem(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['product-catalog'] });
      addToast('Item updated', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to update item', 'error');
    },
  });
}

export function useDeleteCatalogItem() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.deleteCatalogItem(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['product-catalog'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-stocks'] });
      addToast('Item removed from catalog', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to delete item', 'error');
    },
  });
}

export function useCatalogCategories() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['catalog-categories', workspaceId],
    queryFn: async () => {
      const res = await api.getCatalogCategories();
      return (res.data || []) as ProductCategoryItem[];
    },
  });
}

export function useCreateCatalogCategory() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: { name: string; parent_id?: number; description?: string }) =>
      api.createCatalogCategory(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['catalog-categories'] });
      addToast('Category created', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create category', 'error');
    },
  });
}

export function useCreateProductVariant() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ productId, data }: { productId: number; data: Partial<ProductVariantItem> }) =>
      api.createProductVariant(productId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['product-catalog'] });
      addToast('Product variant added', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create variant', 'error');
    },
  });
}

// ==========================================
// 2. INVENTORY & WAREHOUSES HOOKS
// ==========================================

export function useWarehouses() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['inventory-warehouses', workspaceId],
    queryFn: async () => {
      const res = await api.getWarehouses();
      return (res.data || []) as WarehouseItem[];
    },
  });
}

export function useCreateWarehouse() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<WarehouseItem>) => api.createWarehouse(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['inventory-warehouses'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-valuation'] });
      addToast('Warehouse created', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create warehouse', 'error');
    },
  });
}

export function useProductStocks(params: {
  warehouse_id?: number;
  product_id?: number;
  low_stock?: boolean;
} = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['inventory-stocks', workspaceId, params],
    queryFn: async () => {
      const res = await api.getProductStocks(params);
      return (res.data || []) as ProductStockItem[];
    },
  });
}

export function useAdjustStock() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: {
      warehouse_id: number;
      reason: string;
      items: Array<{
        product_id: number;
        product_variant_id?: number;
        adjustment_type: 'increase' | 'decrease' | 'damage';
        quantity: number;
        unit_cost?: number;
        notes?: string;
      }>;
    }) => api.adjustStock(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['inventory-stocks'] });
      queryClient.invalidateQueries({ queryKey: ['product-catalog'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-ledger'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-valuation'] });
      queryClient.invalidateQueries({ queryKey: ['low-stock-alerts'] });
      addToast('Stock adjustment applied successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to apply adjustment', 'error');
    },
  });
}

export function useTransferStock() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: {
      source_warehouse_id: number;
      destination_warehouse_id: number;
      notes?: string;
      items: Array<{
        product_id: number;
        product_variant_id?: number;
        quantity: number;
        notes?: string;
      }>;
    }) => api.transferStock(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['inventory-stocks'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-ledger'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-valuation'] });
      addToast('Stock transferred successfully', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to transfer stock', 'error');
    },
  });
}

export function useStockLedger(params: {
  product_id?: number;
  warehouse_id?: number;
  movement_type?: string;
} = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['inventory-ledger', workspaceId, params],
    queryFn: async () => {
      const res = await api.getStockLedger(params);
      return (res.data || []) as StockMovementItem[];
    },
  });
}

export function useInventoryValuation(warehouseId?: number) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['inventory-valuation', workspaceId, warehouseId],
    queryFn: async () => {
      const res = await api.getInventoryValuation(warehouseId);
      return res.data as InventoryValuationData;
    },
  });
}

export function useLowStockAlerts(warehouseId?: number) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['low-stock-alerts', workspaceId, warehouseId],
    queryFn: async () => {
      const res = await api.getLowStockAlerts(warehouseId);
      return (res.data || []) as LowStockAlertItem[];
    },
  });
}

// ==========================================
// 3. PROCUREMENT & SUPPLIERS HOOKS
// ==========================================

export function useSuppliers() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['procurement-suppliers', workspaceId],
    queryFn: async () => {
      const res = await api.getSuppliers();
      return (res.data || []) as SupplierItem[];
    },
  });
}

export function useCreateSupplier() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: Partial<SupplierItem>) => api.createSupplier(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['procurement-suppliers'] });
      addToast('Supplier created', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create supplier', 'error');
    },
  });
}

export function usePurchaseOrders(params: {
  status?: string;
  supplier_id?: number;
} = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['procurement-purchase-orders', workspaceId, params],
    queryFn: async () => {
      const res = await api.getPurchaseOrders(params);
      return (res.data || []) as PurchaseOrderRecord[];
    },
  });
}

export function useCreatePurchaseOrder() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: any) => api.createPurchaseOrder(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['procurement-purchase-orders'] });
      addToast('Purchase order created', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create purchase order', 'error');
    },
  });
}

export function useReceivePurchaseOrderGoods() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, items }: { id: number; items: Array<{ item_id: number; quantity: number }> }) =>
      api.receivePurchaseOrderGoods(id, items),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['procurement-purchase-orders'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-stocks'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-ledger'] });
      queryClient.invalidateQueries({ queryKey: ['product-catalog'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-valuation'] });
      addToast('Goods received and stock incremented', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to receive goods', 'error');
    },
  });
}
