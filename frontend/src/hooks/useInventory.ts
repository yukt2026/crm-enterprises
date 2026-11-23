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

// ==========================================
// 4. ADVANCED INVENTORY, TRANSFERS & RECONCILIATIONS
// ==========================================

export function useInventorySettings() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['inventory-settings', workspaceId],
    queryFn: async () => {
      const res = await api.getInventorySettings();
      return res.data;
    },
  });
}

export function useUpdateInventorySettings() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: any) => api.updateInventorySettings(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['inventory-settings'] });
      addToast('Inventory settings updated', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to update settings', 'error');
    },
  });
}

export function useTransfers(params: { status?: string } = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['inventory-transfers', workspaceId, params],
    queryFn: async () => {
      const res = await api.getTransfers(params);
      return res.data || [];
    },
  });
}

export function useCreateTransferRequest() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: any) => api.createTransferRequest(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['inventory-transfers'] });
      addToast('Stock transfer request created', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create transfer request', 'error');
    },
  });
}

export function useApproveTransfer() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.approveTransfer(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['inventory-transfers'] });
      addToast('Transfer approved', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to approve transfer', 'error');
    },
  });
}

export function useDispatchTransfer() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.dispatchTransfer(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['inventory-transfers'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-stocks'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-ledger'] });
      addToast('Transfer dispatched into transit', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to dispatch transfer', 'error');
    },
  });
}

export function useReceiveTransfer() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: any }) => api.receiveTransfer(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['inventory-transfers'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-stocks'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-ledger'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-valuation'] });
      addToast('Transfer received and processed', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to receive transfer', 'error');
    },
  });
}

export function useReconciliations() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['inventory-reconciliations', workspaceId],
    queryFn: async () => {
      const res = await api.getReconciliations();
      return res.data || [];
    },
  });
}

export function useCreateReconciliation() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: any) => api.createReconciliation(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['inventory-reconciliations'] });
      addToast('Stock reconciliation count initiated', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create reconciliation', 'error');
    },
  });
}

export function useApplyReconciliation() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.applyReconciliation(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['inventory-reconciliations'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-stocks'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-ledger'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-valuation'] });
      addToast('Reconciliation adjustments applied to stock', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to apply reconciliation', 'error');
    },
  });
}

export function useReorderReport(warehouseId?: number) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['inventory-reorder-report', workspaceId, warehouseId],
    queryFn: async () => {
      const res = await api.getReorderReport(warehouseId);
      return res.data;
    },
  });
}

export function useProfitabilityReport() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['inventory-profitability', workspaceId],
    queryFn: async () => {
      const res = await api.getProductProfitabilityReport();
      return res.data;
    },
  });
}

// ==========================================
// 5. PROCUREMENT: PURCHASE REQUESTS & GRN
// ==========================================

export function usePurchaseRequests(params: { status?: string } = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['procurement-purchase-requests', workspaceId, params],
    queryFn: async () => {
      const res = await api.getPurchaseRequests(params);
      return res.data || [];
    },
  });
}

export function useCreatePurchaseRequest() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: any) => api.createPurchaseRequest(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['procurement-purchase-requests'] });
      addToast('Purchase request submitted for approval', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to submit purchase request', 'error');
    },
  });
}

export function useApprovePurchaseRequest() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.approvePurchaseRequest(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['procurement-purchase-requests'] });
      addToast('Purchase request approved', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to approve request', 'error');
    },
  });
}

export function useGoodsReceiptNotes() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['procurement-grns', workspaceId],
    queryFn: async () => {
      const res = await api.getGoodsReceiptNotes();
      return res.data || [];
    },
  });
}

export function useCreateBillFromPO() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data?: any }) => api.createBillFromPO(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['procurement-purchase-orders'] });
      queryClient.invalidateQueries({ queryKey: ['vendor-bills'] });
      addToast('Vendor bill created and linked to Finance', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to generate vendor bill', 'error');
    },
  });
}

// ==========================================
// 6. CUSTOMER RETURNS & RMA
// ==========================================

export function useCustomerReturns(params: { status?: string } = {}) {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['customer-returns', workspaceId, params],
    queryFn: async () => {
      const res = await api.getCustomerReturns(params);
      return res.data || [];
    },
  });
}

export function useCreateCustomerReturn() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (data: any) => api.createCustomerReturn(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['customer-returns'] });
      addToast('Customer RMA return request created', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to create return request', 'error');
    },
  });
}

export function useInspectCustomerReturn() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: any }) => api.inspectCustomerReturn(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['customer-returns'] });
      addToast('Return inspection completed', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to submit inspection', 'error');
    },
  });
}

export function useApproveCustomerReturn() {
  const queryClient = useQueryClient();
  const addToast = useCRMStore((s) => s.addToast);

  return useMutation({
    mutationFn: (id: number) => api.approveCustomerReturn(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['customer-returns'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-stocks'] });
      queryClient.invalidateQueries({ queryKey: ['inventory-ledger'] });
      queryClient.invalidateQueries({ queryKey: ['credit-notes'] });
      addToast('Return approved and restocked / quarantined', 'success');
    },
    onError: (err: any) => {
      addToast(err.message || 'Failed to approve return', 'error');
    },
  });
}

export function useCustomerReturnsReport() {
  const currentWorkspace = useCRMStore((s) => s.currentWorkspace);
  const workspaceId = currentWorkspace?.id || 'default';

  return useQuery({
    queryKey: ['customer-returns-report', workspaceId],
    queryFn: async () => {
      const res = await api.getCustomerReturnsReport();
      return res.data;
    },
  });
}
