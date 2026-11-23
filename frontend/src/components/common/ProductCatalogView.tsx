import React, { useState, useMemo } from 'react';
import {
  Package,
  Layers,
  Building2,
  TrendingUp,
  DollarSign,
  AlertTriangle,
  Plus,
  ArrowRightLeft,
  Sliders,
  Search,
  ShoppingCart,
  Truck,
  FileText,
  Boxes,
  CheckCircle2,
  Clock,
  ShieldCheck,
  Tag,
  Warehouse as WarehouseIcon,
  Filter,
  BarChart3,
  Archive,
  RefreshCw,
  PlusCircle,
  HelpCircle,
} from 'lucide-react';
import {
  useCatalog,
  useCreateCatalogItem,
  useCatalogCategories,
  useCreateCatalogCategory,
  useWarehouses,
  useCreateWarehouse,
  useProductStocks,
  useAdjustStock,
  useTransferStock,
  useStockLedger,
  useInventoryValuation,
  useLowStockAlerts,
  useSuppliers,
  useCreateSupplier,
  usePurchaseOrders,
  useCreatePurchaseOrder,
  useReceivePurchaseOrderGoods,
  useCreateProductVariant,
} from '../../hooks/useInventory';
import { ProductCatalogItem, WarehouseItem } from '../../api/types';

interface ProductCatalogViewProps {
  theme?: 'light' | 'dark';
}

type CatalogTab = 'products' | 'services' | 'inventory' | 'ledger' | 'procurement';

export default function ProductCatalogView({ theme = 'dark' }: ProductCatalogViewProps) {
  const [activeTab, setActiveTab] = useState<CatalogTab>('products');
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedCategoryId, setSelectedCategoryId] = useState<number | undefined>(undefined);
  const [onlyLowStock, setOnlyLowStock] = useState(false);

  // Modals state
  const [showItemModal, setShowItemModal] = useState(false);
  const [showCategoryModal, setShowCategoryModal] = useState(false);
  const [showWarehouseModal, setShowWarehouseModal] = useState(false);
  const [showAdjustModal, setShowAdjustModal] = useState(false);
  const [showTransferModal, setShowTransferModal] = useState(false);
  const [showSupplierModal, setShowSupplierModal] = useState(false);
  const [showPoModal, setShowPoModal] = useState(false);
  const [receivingPo, setReceivingPo] = useState<any | null>(null);
  const [selectedProductForVariant, setSelectedProductForVariant] = useState<ProductCatalogItem | null>(null);

  // Item Form State
  const [itemName, setItemName] = useState('');
  const [itemType, setItemType] = useState<'product' | 'service'>('product');
  const [itemSku, setItemSku] = useState('');
  const [itemCategoryId, setItemCategoryId] = useState<number | ''>('');
  const [itemBrand, setItemBrand] = useState('');
  const [itemUnit, setItemUnit] = useState('pcs');
  const [itemPrice, setItemPrice] = useState<number>(0);
  const [itemCostPrice, setItemCostPrice] = useState<number>(0);
  const [itemTaxRate, setItemTaxRate] = useState<number>(0);
  const [itemOpeningStock, setItemOpeningStock] = useState<number>(0);
  const [itemDuration, setItemDuration] = useState('');
  const [itemDeliveryType, setItemDeliveryType] = useState('remote');
  const [itemDescription, setItemDescription] = useState('');

  // Adjustment Form State
  const [adjWarehouseId, setAdjWarehouseId] = useState<number | ''>('');
  const [adjProductId, setAdjProductId] = useState<number | ''>('');
  const [adjType, setAdjType] = useState<'increase' | 'decrease' | 'damage'>('increase');
  const [adjQty, setAdjQty] = useState<number>(1);
  const [adjReason, setAdjReason] = useState('');

  // Transfer Form State
  const [trfSourceId, setTrfSourceId] = useState<number | ''>('');
  const [trfDestId, setTrfDestId] = useState<number | ''>('');
  const [trfProductId, setTrfProductId] = useState<number | ''>('');
  const [trfQty, setTrfQty] = useState<number>(1);
  const [trfNotes, setTrfNotes] = useState('');

  // Variant Form State
  const [varName, setVarName] = useState('');
  const [varSku, setVarSku] = useState('');
  const [varPrice, setVarPrice] = useState<number>(0);
  const [varCost, setVarCost] = useState<number>(0);

  // Queries
  const { data: allCatalog = [], isLoading: loadingCatalog } = useCatalog({
    type: activeTab === 'services' ? 'service' : activeTab === 'products' ? 'product' : undefined,
    category_id: selectedCategoryId,
    search: searchQuery || undefined,
  });
  const { data: categories = [] } = useCatalogCategories();
  const { data: warehouses = [] } = useWarehouses();
  const { data: valuation } = useInventoryValuation();
  const { data: lowStockAlerts = [] } = useLowStockAlerts();
  const { data: stockLedger = [] } = useStockLedger();
  const { data: suppliers = [] } = useSuppliers();
  const { data: purchaseOrders = [] } = usePurchaseOrders();

  // Mutations
  const createItemMutation = useCreateCatalogItem();
  const createCategoryMutation = useCreateCatalogCategory();
  const createWarehouseMutation = useCreateWarehouse();
  const adjustStockMutation = useAdjustStock();
  const transferStockMutation = useTransferStock();
  const createVariantMutation = useCreateProductVariant();
  const createSupplierMutation = useCreateSupplier();
  const createPoMutation = useCreatePurchaseOrder();
  const receiveGoodsMutation = useReceivePurchaseOrderGoods();

  const handleCreateItem = (e: React.FormEvent) => {
    e.preventDefault();
    if (!itemName.trim() || itemPrice < 0) return;

    createItemMutation.mutate(
      {
        name: itemName.trim(),
        type: itemType,
        sku: itemSku.trim() || undefined,
        category_id: itemCategoryId ? Number(itemCategoryId) : undefined,
        brand: itemBrand.trim() || undefined,
        unit: itemUnit,
        price: itemPrice,
        cost_price: itemCostPrice,
        tax_rate: itemTaxRate,
        opening_stock: itemOpeningStock > 0 ? itemOpeningStock : undefined,
        service_duration: itemType === 'service' ? itemDuration : undefined,
        service_delivery_type: itemType === 'service' ? itemDeliveryType : undefined,
        description: itemDescription.trim() || undefined,
      },
      {
        onSuccess: () => {
          setShowItemModal(false);
          setItemName('');
          setItemSku('');
          setItemPrice(0);
          setItemCostPrice(0);
          setItemOpeningStock(0);
          setItemDescription('');
        },
      }
    );
  };

  const handleAdjustStock = (e: React.FormEvent) => {
    e.preventDefault();
    if (!adjWarehouseId || !adjProductId || adjQty <= 0) return;

    adjustStockMutation.mutate(
      {
        warehouse_id: Number(adjWarehouseId),
        reason: adjReason || 'Physical inventory reconciliation',
        items: [
          {
            product_id: Number(adjProductId),
            adjustment_type: adjType,
            quantity: adjQty,
          },
        ],
      },
      {
        onSuccess: () => {
          setShowAdjustModal(false);
          setAdjQty(1);
          setAdjReason('');
        },
      }
    );
  };

  const handleTransferStock = (e: React.FormEvent) => {
    e.preventDefault();
    if (!trfSourceId || !trfDestId || !trfProductId || trfQty <= 0) return;

    transferStockMutation.mutate(
      {
        source_warehouse_id: Number(trfSourceId),
        destination_warehouse_id: Number(trfDestId),
        notes: trfNotes || undefined,
        items: [
          {
            product_id: Number(trfProductId),
            quantity: trfQty,
          },
        ],
      },
      {
        onSuccess: () => {
          setShowTransferModal(false);
          setTrfQty(1);
          setTrfNotes('');
        },
      }
    );
  };

  const handleCreateVariant = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedProductForVariant || !varName.trim()) return;

    createVariantMutation.mutate(
      {
        productId: selectedProductForVariant.id,
        data: {
          name: varName.trim(),
          sku: varSku.trim() || undefined,
          sale_price: varPrice > 0 ? varPrice : selectedProductForVariant.price,
          cost_price: varCost > 0 ? varCost : selectedProductForVariant.cost_price,
        },
      },
      {
        onSuccess: () => {
          setSelectedProductForVariant(null);
          setVarName('');
          setVarSku('');
          setVarPrice(0);
          setVarCost(0);
        },
      }
    );
  };

  return (
    <div className="space-y-6">
      {/* Top Header & Metrics */}
      <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div className="space-y-1">
            <h2 className="text-xl font-bold text-slate-100 flex items-center gap-2">
              <Boxes className="w-6 h-6 text-indigo-400" />
              Product, Service Catalog & Inventory
            </h2>
            <p className="text-xs text-slate-400">
              Manage items, prices, multi-warehouse stock levels, transfers, procurement, and ledger history
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <button
              onClick={() => setShowAdjustModal(true)}
              className="flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold border border-slate-700 transition-colors"
            >
              <Sliders className="w-4 h-4 text-amber-400" />
              Adjust Stock
            </button>
            <button
              onClick={() => setShowTransferModal(true)}
              className="flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold border border-slate-700 transition-colors"
            >
              <ArrowRightLeft className="w-4 h-4 text-sky-400" />
              Transfer Stock
            </button>
            <button
              onClick={() => setShowItemModal(true)}
              className="flex items-center gap-1.5 px-3.5 py-1.5 bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white rounded-xl text-xs font-semibold shadow-sm transition-all"
            >
              <Plus className="w-4 h-4" />
              New Item
            </button>
          </div>
        </div>

        {/* Tab Navigation */}
        <div className="flex items-center gap-2 overflow-x-auto pb-1 border-t border-slate-800/80 pt-3">
          {[
            { id: 'products', label: 'Products & Hardware', icon: Package },
            { id: 'services', label: 'Services & Consulting', icon: Layers },
            { id: 'inventory', label: 'Warehouse Stock', icon: WarehouseIcon },
            { id: 'ledger', label: 'Stock Movement Ledger', icon: FileText },
            { id: 'procurement', label: 'Purchases & Suppliers', icon: Truck },
          ].map((tab) => {
            const Icon = tab.icon;
            const isActive = activeTab === tab.id;
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id as CatalogTab)}
                className={`flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-medium transition-all shrink-0 ${
                  isActive
                    ? 'bg-indigo-600 text-white font-bold shadow-sm'
                    : 'bg-slate-800/80 text-slate-400 hover:text-slate-200 hover:bg-slate-800'
                }`}
              >
                <Icon className={`w-3.5 h-3.5 ${isActive ? 'text-white' : 'text-slate-400'}`} />
                {tab.label}
              </button>
            );
          })}
        </div>
      </div>

      {/* KPI Strip */}
      {valuation && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 flex items-center gap-3">
            <div className="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 shrink-0">
              <DollarSign className="w-6 h-6" />
            </div>
            <div>
              <p className="text-xs text-slate-400 font-medium">Inventory Cost Valuation</p>
              <p className="text-lg font-bold font-mono text-emerald-300">
                ${Number(valuation.total_cost_valuation || 0).toLocaleString()}
              </p>
            </div>
          </div>

          <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 flex items-center gap-3">
            <div className="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400 shrink-0">
              <Package className="w-6 h-6" />
            </div>
            <div>
              <p className="text-xs text-slate-400 font-medium">Total Stocked Units</p>
              <p className="text-lg font-bold font-mono text-purple-300">
                {Number(valuation.total_units || 0).toLocaleString()}
              </p>
            </div>
          </div>

          <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 flex items-center gap-3">
            <div className="w-12 h-12 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400 shrink-0">
              <TrendingUp className="w-6 h-6" />
            </div>
            <div>
              <p className="text-xs text-slate-400 font-medium">Retail Valuation</p>
              <p className="text-lg font-bold font-mono text-blue-300">
                ${Number(valuation.total_retail_valuation || 0).toLocaleString()}
              </p>
            </div>
          </div>

          <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 flex items-center gap-3">
            <div className="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 shrink-0">
              <AlertTriangle className="w-6 h-6" />
            </div>
            <div>
              <p className="text-xs text-slate-400 font-medium">Low Stock Alerts</p>
              <p className="text-lg font-bold font-mono text-amber-300">
                {lowStockAlerts.length}
              </p>
            </div>
          </div>
        </div>
      )}

      {/* PRODUCTS OR SERVICES TAB */}
      {(activeTab === 'products' || activeTab === 'services') && (
        <div className="space-y-4">
          {/* Filter Bar */}
          <div className="p-3.5 rounded-2xl border bg-slate-900/40 border-slate-800 flex flex-wrap items-center justify-between gap-3">
            <div className="flex items-center gap-3 flex-1">
              <div className="relative flex-1 max-w-xs">
                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
                <input
                  type="text"
                  placeholder="Search by name, SKU, brand..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="w-full bg-slate-800 border border-slate-700/80 rounded-xl pl-9 pr-3 py-1.5 text-xs text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                />
              </div>

              <select
                value={selectedCategoryId || ''}
                onChange={(e) => setSelectedCategoryId(e.target.value ? Number(e.target.value) : undefined)}
                className="bg-slate-800 border border-slate-700 text-xs text-slate-300 rounded-xl px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              >
                <option value="">All Categories</option>
                {categories.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </div>

            <div className="flex items-center gap-2">
              <button
                onClick={() => setShowCategoryModal(true)}
                className="text-xs text-indigo-400 hover:text-indigo-300 font-medium px-2 py-1"
              >
                Manage Categories
              </button>
            </div>
          </div>

          {/* Catalog Table */}
          <div className="rounded-2xl border bg-slate-900/60 border-slate-800 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="border-b border-slate-800 bg-slate-800/40 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                    <th className="py-3 px-4">Item & Code</th>
                    <th className="py-3 px-4">Category</th>
                    <th className="py-3 px-4 text-right">Sale Price</th>
                    <th className="py-3 px-4 text-right">Cost Price</th>
                    <th className="py-3 px-4 text-right">Stock (Available / Total)</th>
                    <th className="py-3 px-4 text-center">Status</th>
                    <th className="py-3 px-4 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800 text-xs">
                  {loadingCatalog ? (
                    <tr>
                      <td colSpan={7} className="py-16 text-center text-slate-500">
                        Loading catalog...
                      </td>
                    </tr>
                  ) : allCatalog.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="py-16 text-center text-slate-500">
                        No {activeTab} found matching criteria.
                      </td>
                    </tr>
                  ) : (
                    allCatalog.map((item) => {
                      const isProduct = item.type === 'product';
                      const isLowStock = isProduct && item.track_inventory && item.available_stock <= 5;

                      return (
                        <tr key={item.id} className="hover:bg-slate-800/40 transition-colors">
                          <td className="py-3.5 px-4">
                            <div className="space-y-0.5">
                              <p className="font-semibold text-slate-100">{item.name}</p>
                              <div className="flex items-center gap-2 text-[10px] text-slate-500 font-mono">
                                <span>SKU: {item.sku}</span>
                                {item.brand && <span>&bull; {item.brand}</span>}
                                {item.unit && <span>&bull; ({item.unit})</span>}
                              </div>
                            </div>
                          </td>

                          <td className="py-3.5 px-4 text-slate-400">
                            {item.category_model?.name || item.category || 'General'}
                          </td>

                          <td className="py-3.5 px-4 text-right font-mono font-bold text-emerald-400">
                            ${Number(item.price || 0).toLocaleString()}
                          </td>

                          <td className="py-3.5 px-4 text-right font-mono text-slate-400">
                            ${Number(item.cost_price || 0).toLocaleString()}
                          </td>

                          <td className="py-3.5 px-4 text-right font-mono">
                            {isProduct ? (
                              <span
                                className={`font-bold ${
                                  isLowStock ? 'text-amber-400' : 'text-slate-200'
                                }`}
                              >
                                {item.available_stock} / {item.total_stock} {item.unit}
                              </span>
                            ) : (
                              <span className="text-slate-500 italic">Service (Unlimited)</span>
                            )}
                          </td>

                          <td className="py-3.5 px-4 text-center">
                            <span
                              className={`px-2 py-0.5 rounded text-[10px] font-semibold uppercase ${
                                item.status === 'active'
                                  ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                  : 'bg-slate-700/40 text-slate-400'
                              }`}
                            >
                              {item.status}
                            </span>
                          </td>

                          <td className="py-3.5 px-4 text-right">
                            {isProduct && (
                              <button
                                onClick={() => setSelectedProductForVariant(item)}
                                className="text-[11px] text-indigo-400 hover:text-indigo-300 font-medium px-2 py-1"
                              >
                                + Variant ({item.variants?.length || 0})
                              </button>
                            )}
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* WAREHOUSE INVENTORY TAB */}
      {activeTab === 'inventory' && (
        <div className="space-y-6">
          <div className="flex items-center justify-between">
            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400">
              Warehouses & Distribution Depots ({warehouses.length})
            </h4>
            <button
              onClick={() => setShowWarehouseModal(true)}
              className="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold"
            >
              <Plus className="w-3.5 h-3.5" />
              Add Warehouse
            </button>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {warehouses.map((wh) => (
              <div
                key={wh.id}
                className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 space-y-3"
              >
                <div className="flex items-start justify-between">
                  <div className="space-y-0.5">
                    <div className="flex items-center gap-2">
                      <h5 className="font-bold text-sm text-slate-100">{wh.name}</h5>
                      {wh.is_primary && (
                        <span className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/40">
                          PRIMARY
                        </span>
                      )}
                    </div>
                    <p className="text-[11px] font-mono text-slate-400">{wh.code}</p>
                  </div>
                </div>

                <div className="text-xs text-slate-400 space-y-1">
                  {wh.address && <p>{wh.address}, {wh.city || ''}</p>}
                  {wh.contact_name && <p>Contact: {wh.contact_name}</p>}
                </div>
              </div>
            ))}
          </div>

          {/* Low Stock Alerts Table */}
          {lowStockAlerts.length > 0 && (
            <div className="space-y-3 pt-4">
              <h4 className="text-xs font-bold uppercase tracking-wider text-amber-400 flex items-center gap-2">
                <AlertTriangle className="w-4 h-4" />
                Items Below Minimum Reorder Level ({lowStockAlerts.length})
              </h4>
              <div className="rounded-2xl border border-amber-500/30 bg-slate-900/60 overflow-hidden">
                <table className="w-full text-left border-collapse text-xs">
                  <thead>
                    <tr className="border-b border-slate-800 bg-amber-500/10 text-amber-300 font-semibold text-[11px]">
                      <th className="py-2.5 px-4">Item Name</th>
                      <th className="py-2.5 px-4">SKU</th>
                      <th className="py-2.5 px-4">Warehouse</th>
                      <th className="py-2.5 px-4 text-right">Current Stock</th>
                      <th className="py-2.5 px-4 text-right">Min Threshold</th>
                      <th className="py-2.5 px-4 text-right">Deficit</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-800">
                    {lowStockAlerts.map((alt) => (
                      <tr key={alt.stock_id}>
                        <td className="py-2.5 px-4 font-semibold text-slate-200">{alt.product_name}</td>
                        <td className="py-2.5 px-4 font-mono text-slate-400">{alt.sku}</td>
                        <td className="py-2.5 px-4 text-slate-400">{alt.warehouse_name}</td>
                        <td className="py-2.5 px-4 text-right font-mono font-bold text-rose-400">{alt.current_stock}</td>
                        <td className="py-2.5 px-4 text-right font-mono text-slate-400">{alt.minimum_stock}</td>
                        <td className="py-2.5 px-4 text-right font-mono font-bold text-amber-400">+{alt.deficit}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </div>
      )}

      {/* STOCK MOVEMENT LEDGER TAB */}
      {activeTab === 'ledger' && (
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400">
              Audit-Immutable Stock Movement Ledger ({stockLedger.length})
            </h4>
          </div>

          <div className="rounded-2xl border bg-slate-900/60 border-slate-800 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse text-xs">
                <thead>
                  <tr className="border-b border-slate-800 bg-slate-800/40 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                    <th className="py-3 px-4">Timestamp</th>
                    <th className="py-3 px-4">Movement Type</th>
                    <th className="py-3 px-4">Product & SKU</th>
                    <th className="py-3 px-4">Warehouse</th>
                    <th className="py-3 px-4 text-right">Qty</th>
                    <th className="py-3 px-4 text-right">Before &rarr; After</th>
                    <th className="py-3 px-4">Reference</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800">
                  {stockLedger.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="py-16 text-center text-slate-500">
                        No stock movement records logged yet.
                      </td>
                    </tr>
                  ) : (
                    stockLedger.map((mov) => {
                      const isIncrease = mov.after_stock > mov.before_stock;
                      return (
                        <tr key={mov.id} className="hover:bg-slate-800/40 transition-colors">
                          <td className="py-3 px-4 font-mono text-[11px] text-slate-400">
                            {new Date(mov.created_at).toLocaleString()}
                          </td>
                          <td className="py-3 px-4">
                            <span
                              className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                                isIncrease
                                  ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                  : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'
                              }`}
                            >
                              {mov.movement_type.replace(/_/g, ' ')}
                            </span>
                          </td>
                          <td className="py-3 px-4">
                            <p className="font-semibold text-slate-200">{mov.product?.name}</p>
                            <p className="text-[10px] font-mono text-slate-500">{mov.product?.sku}</p>
                          </td>
                          <td className="py-3 px-4 text-slate-300">
                            {mov.warehouse?.name}
                          </td>
                          <td className="py-3 px-4 text-right font-mono font-bold text-slate-100">
                            {isIncrease ? `+${mov.quantity}` : `-${mov.quantity}`}
                          </td>
                          <td className="py-3 px-4 text-right font-mono text-slate-400">
                            {mov.before_stock} &rarr; <strong className="text-slate-200">{mov.after_stock}</strong>
                          </td>
                          <td className="py-3 px-4 text-slate-400">
                            {mov.reference_type || mov.notes || '-'}
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* PROCUREMENT & SUPPLIERS TAB */}
      {activeTab === 'procurement' && (
        <div className="space-y-6">
          <div className="flex items-center justify-between">
            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400">
              Procurement & Purchase Orders ({purchaseOrders.length})
            </h4>
            <div className="flex items-center gap-2">
              <button
                onClick={() => setShowSupplierModal(true)}
                className="flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold border border-slate-700"
              >
                <Plus className="w-3.5 h-3.5" />
                Add Supplier
              </button>
              <button
                onClick={() => setShowPoModal(true)}
                className="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold"
              >
                <Plus className="w-3.5 h-3.5" />
                New Purchase Order
              </button>
            </div>
          </div>

          <div className="rounded-2xl border bg-slate-900/60 border-slate-800 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse text-xs">
                <thead>
                  <tr className="border-b border-slate-800 bg-slate-800/40 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                    <th className="py-3 px-4">PO Code</th>
                    <th className="py-3 px-4">Supplier</th>
                    <th className="py-3 px-4">Warehouse</th>
                    <th className="py-3 px-4 text-right">Total Amount</th>
                    <th className="py-3 px-4 text-center">Status</th>
                    <th className="py-3 px-4 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800">
                  {purchaseOrders.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="py-16 text-center text-slate-500">
                        No purchase orders recorded.
                      </td>
                    </tr>
                  ) : (
                    purchaseOrders.map((po) => (
                      <tr key={po.id} className="hover:bg-slate-800/40 transition-colors">
                        <td className="py-3 px-4 font-mono font-bold text-slate-200">
                          {po.po_number}
                        </td>
                        <td className="py-3 px-4 text-slate-300">
                          {po.supplier?.name}
                        </td>
                        <td className="py-3 px-4 text-slate-400">
                          {po.warehouse?.name}
                        </td>
                        <td className="py-3 px-4 text-right font-mono font-bold text-emerald-400">
                          ${Number(po.total_amount || 0).toLocaleString()}
                        </td>
                        <td className="py-3 px-4 text-center">
                          <span
                            className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                              po.status === 'received'
                                ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                : po.status === 'partially_received'
                                ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                                : 'bg-blue-500/10 text-blue-400 border border-blue-500/20'
                            }`}
                          >
                            {po.status}
                          </span>
                        </td>
                        <td className="py-3 px-4 text-right">
                          {po.status !== 'received' && (
                            <button
                              onClick={() => {
                                // Fast GRN for items
                                const items = (po.items || []).map((it) => ({
                                  item_id: it.id,
                                  quantity: Number(it.quantity) - Number(it.received_quantity || 0),
                                }));
                                receiveGoodsMutation.mutate({ id: po.id, items });
                              }}
                              className="px-2.5 py-1 bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 border border-emerald-500/30 rounded-lg text-[11px] font-semibold transition"
                            >
                              Receive Goods (GRN)
                            </button>
                          )}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* CREATE ITEM MODAL */}
      {showItemModal && (
        <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg p-6 space-y-4 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between">
              <h3 className="text-base font-bold text-slate-100 flex items-center gap-2">
                <Package className="w-5 h-5 text-indigo-400" />
                Add Catalog Item
              </h3>
              <button
                onClick={() => setShowItemModal(false)}
                className="text-slate-400 hover:text-slate-200"
              >
                &times;
              </button>
            </div>

            <form onSubmit={handleCreateItem} className="space-y-4">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Item Type</label>
                  <select
                    value={itemType}
                    onChange={(e) => setItemType(e.target.value as any)}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  >
                    <option value="product">Physical Product (Inventory)</option>
                    <option value="service">Service / Subscription</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Category</label>
                  <select
                    value={itemCategoryId}
                    onChange={(e) => setItemCategoryId(e.target.value ? Number(e.target.value) : '')}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  >
                    <option value="">No Category</option>
                    {categories.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">
                  Item Name <span className="text-rose-400">*</span>
                </label>
                <input
                  type="text"
                  placeholder="e.g. Precision Workstation Tower..."
                  value={itemName}
                  onChange={(e) => setItemName(e.target.value)}
                  required
                  className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">SKU / Code</label>
                  <input
                    type="text"
                    placeholder="Auto-generated if empty"
                    value={itemSku}
                    onChange={(e) => setItemSku(e.target.value)}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Brand</label>
                  <input
                    type="text"
                    placeholder="e.g. Dell, HP"
                    value={itemBrand}
                    onChange={(e) => setItemBrand(e.target.value)}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">UOM Unit</label>
                  <input
                    type="text"
                    value={itemUnit}
                    onChange={(e) => setItemUnit(e.target.value)}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
              </div>

              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">
                    Sale Price ($) <span className="text-rose-400">*</span>
                  </label>
                  <input
                    type="number"
                    step="0.01"
                    value={itemPrice}
                    onChange={(e) => setItemPrice(Number(e.target.value))}
                    required
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Cost Price ($)</label>
                  <input
                    type="number"
                    step="0.01"
                    value={itemCostPrice}
                    onChange={(e) => setItemCostPrice(Number(e.target.value))}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Tax Rate (%)</label>
                  <input
                    type="number"
                    step="0.1"
                    value={itemTaxRate}
                    onChange={(e) => setItemTaxRate(Number(e.target.value))}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
              </div>

              {itemType === 'product' && (
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Opening Stock (Initial Units)</label>
                  <input
                    type="number"
                    min="0"
                    value={itemOpeningStock}
                    onChange={(e) => setItemOpeningStock(Number(e.target.value))}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
              )}

              <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowItemModal(false)}
                  className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createItemMutation.isPending}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold disabled:opacity-50"
                >
                  {createItemMutation.isPending ? 'Saving...' : 'Create Item'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ADJUST STOCK MODAL */}
      {showAdjustModal && (
        <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between">
              <h3 className="text-base font-bold text-slate-100 flex items-center gap-2">
                <Sliders className="w-5 h-5 text-amber-400" />
                Manual Stock Adjustment
              </h3>
              <button
                onClick={() => setShowAdjustModal(false)}
                className="text-slate-400 hover:text-slate-200"
              >
                &times;
              </button>
            </div>

            <form onSubmit={handleAdjustStock} className="space-y-4">
              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">Warehouse</label>
                <select
                  value={adjWarehouseId}
                  onChange={(e) => setAdjWarehouseId(Number(e.target.value))}
                  required
                  className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                  <option value="">Select warehouse...</option>
                  {warehouses.map((w) => (
                    <option key={w.id} value={w.id}>
                      {w.name} ({w.code})
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">Product</label>
                <select
                  value={adjProductId}
                  onChange={(e) => setAdjProductId(Number(e.target.value))}
                  required
                  className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                  <option value="">Select product...</option>
                  {allCatalog.filter((p) => p.type === 'product').map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.name} ({p.sku})
                    </option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Adjustment Type</label>
                  <select
                    value={adjType}
                    onChange={(e) => setAdjType(e.target.value as any)}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  >
                    <option value="increase">Increase Stock (+)</option>
                    <option value="decrease">Decrease Stock (-)</option>
                    <option value="damage">Damage / Write-off</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Quantity</label>
                  <input
                    type="number"
                    min="1"
                    value={adjQty}
                    onChange={(e) => setAdjQty(Number(e.target.value))}
                    required
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">Reason / Notes</label>
                <input
                  type="text"
                  placeholder="e.g. Physical inventory discrepancy..."
                  value={adjReason}
                  onChange={(e) => setAdjReason(e.target.value)}
                  required
                  className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowAdjustModal(false)}
                  className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={adjustStockMutation.isPending}
                  className="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-xl text-xs font-semibold disabled:opacity-50"
                >
                  {adjustStockMutation.isPending ? 'Applying...' : 'Apply Adjustment'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* TRANSFER STOCK MODAL */}
      {showTransferModal && (
        <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between">
              <h3 className="text-base font-bold text-slate-100 flex items-center gap-2">
                <ArrowRightLeft className="w-5 h-5 text-sky-400" />
                Inter-Warehouse Stock Transfer
              </h3>
              <button
                onClick={() => setShowTransferModal(false)}
                className="text-slate-400 hover:text-slate-200"
              >
                &times;
              </button>
            </div>

            <form onSubmit={handleTransferStock} className="space-y-4">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Source Warehouse</label>
                  <select
                    value={trfSourceId}
                    onChange={(e) => setTrfSourceId(Number(e.target.value))}
                    required
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  >
                    <option value="">From...</option>
                    {warehouses.map((w) => (
                      <option key={w.id} value={w.id}>
                        {w.name}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Destination</label>
                  <select
                    value={trfDestId}
                    onChange={(e) => setTrfDestId(Number(e.target.value))}
                    required
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  >
                    <option value="">To...</option>
                    {warehouses.filter((w) => w.id !== trfSourceId).map((w) => (
                      <option key={w.id} value={w.id}>
                        {w.name}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">Product</label>
                <select
                  value={trfProductId}
                  onChange={(e) => setTrfProductId(Number(e.target.value))}
                  required
                  className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                  <option value="">Select product...</option>
                  {allCatalog.filter((p) => p.type === 'product').map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.name} ({p.sku})
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">Quantity to Transfer</label>
                <input
                  type="number"
                  min="1"
                  value={trfQty}
                  onChange={(e) => setTrfQty(Number(e.target.value))}
                  required
                  className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowTransferModal(false)}
                  className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={transferStockMutation.isPending}
                  className="px-4 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-semibold disabled:opacity-50"
                >
                  {transferStockMutation.isPending ? 'Transferring...' : 'Execute Transfer'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* CREATE VARIANT MODAL */}
      {selectedProductForVariant && (
        <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between">
              <h3 className="text-base font-bold text-slate-100 flex items-center gap-2">
                <Tag className="w-5 h-5 text-indigo-400" />
                Add Product Variant
              </h3>
              <button
                onClick={() => setSelectedProductForVariant(null)}
                className="text-slate-400 hover:text-slate-200"
              >
                &times;
              </button>
            </div>

            <p className="text-xs text-slate-400">
              Parent Product: <strong className="text-slate-200">{selectedProductForVariant.name}</strong>
            </p>

            <form onSubmit={handleCreateVariant} className="space-y-4">
              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">
                  Variant Name <span className="text-rose-400">*</span>
                </label>
                <input
                  type="text"
                  placeholder="e.g. Size: Large, Color: Midnight Blue"
                  value={varName}
                  onChange={(e) => setVarName(e.target.value)}
                  required
                  className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">Variant SKU</label>
                <input
                  type="text"
                  placeholder="Auto-generated if empty"
                  value={varSku}
                  onChange={(e) => setVarSku(e.target.value)}
                  className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Sale Price ($)</label>
                  <input
                    type="number"
                    step="0.01"
                    placeholder={`Default: $${selectedProductForVariant.price}`}
                    value={varPrice || ''}
                    onChange={(e) => setVarPrice(Number(e.target.value))}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Cost Price ($)</label>
                  <input
                    type="number"
                    step="0.01"
                    placeholder={`Default: $${selectedProductForVariant.cost_price}`}
                    value={varCost || ''}
                    onChange={(e) => setVarCost(Number(e.target.value))}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
              </div>

              <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setSelectedProductForVariant(null)}
                  className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createVariantMutation.isPending}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold disabled:opacity-50"
                >
                  {createVariantMutation.isPending ? 'Adding...' : 'Save Variant'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
