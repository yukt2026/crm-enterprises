import React, { useState } from 'react';
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
  FileCheck,
  ShieldAlert,
  RotateCcw,
  Building,
  CreditCard,
  Eye,
  Settings,
  Download,
  Upload,
  Check,
  X,
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
  useInventorySettings,
  useUpdateInventorySettings,
  useTransfers,
  useCreateTransferRequest,
  useApproveTransfer,
  useDispatchTransfer,
  useReceiveTransfer,
  useReconciliations,
  useCreateReconciliation,
  useApplyReconciliation,
  useReorderReport,
  useProfitabilityReport,
  usePurchaseRequests,
  useCreatePurchaseRequest,
  useApprovePurchaseRequest,
  useGoodsReceiptNotes,
  useCreateBillFromPO,
  useCustomerReturns,
  useCreateCustomerReturn,
  useInspectCustomerReturn,
  useApproveCustomerReturn,
  useCustomerReturnsReport,
} from '../../hooks/useInventory';
import { ProductCatalogItem, WarehouseItem } from '../../api/types';
import { api } from '../../api/client';
import { useCRMStore } from '../../store/crmStore';

interface ProductCatalogViewProps {
  theme?: 'light' | 'dark';
}

type CatalogTab =
  | 'products'
  | 'services'
  | 'inventory'
  | 'transfers'
  | 'reconciliation'
  | 'procurement'
  | 'vendors'
  | 'returns'
  | 'reports'
  | 'settings';

export default function ProductCatalogView({ theme = 'dark' }: ProductCatalogViewProps) {
  const [activeTab, setActiveTab] = useState<CatalogTab>('products');
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedCategoryId, setSelectedCategoryId] = useState<number | undefined>(undefined);
  const [onlyLowStock, setOnlyLowStock] = useState(false);
  const addToast = useCRMStore((s) => s.addToast);

  // Modals state
  const [showItemModal, setShowItemModal] = useState(false);
  const [showCategoryModal, setShowCategoryModal] = useState(false);
  const [showWarehouseModal, setShowWarehouseModal] = useState(false);
  const [showAdjustModal, setShowAdjustModal] = useState(false);
  const [showTransferModal, setShowTransferModal] = useState(false);
  const [showSupplierModal, setShowSupplierModal] = useState(false);
  const [showPoModal, setShowPoModal] = useState(false);
  const [showPrModal, setShowPrModal] = useState(false);
  const [showReconciliationModal, setShowReconciliationModal] = useState(false);
  const [showReturnModal, setShowReturnModal] = useState(false);
  const [selectedProductForVariant, setSelectedProductForVariant] = useState<ProductCatalogItem | null>(null);
  const [inspectingReturn, setInspectingReturn] = useState<any | null>(null);
  const [revealedBankDetails, setRevealedBankDetails] = useState<{ [supplierId: number]: any }>({});

  // Item Form State
  const [itemName, setItemName] = useState('');
  const [itemType, setItemType] = useState<'product' | 'service'>('product');
  const [itemSku, setItemSku] = useState('');
  const [itemCategoryId, setItemCategoryId] = useState<number | ''>('');
  const [itemBrand, setItemBrand] = useState('');
  const [itemUnit, setItemUnit] = useState('pcs');
  const [itemSecondaryUnit, setItemSecondaryUnit] = useState('');
  const [itemPrice, setItemPrice] = useState<number>(0);
  const [itemMinPrice, setItemMinPrice] = useState<number>(0);
  const [itemCostPrice, setItemCostPrice] = useState<number>(0);
  const [itemTaxRate, setItemTaxRate] = useState<number>(0);
  const [itemHsn, setItemHsn] = useState('');
  const [itemBarcode, setItemBarcode] = useState('');
  const [itemReorderLevel, setItemReorderLevel] = useState<number>(10);
  const [itemReorderQty, setItemReorderQty] = useState<number>(50);
  const [itemOpeningStock, setItemOpeningStock] = useState<number>(0);
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

  // Return Form State
  const [retCustomerId, setRetCustomerId] = useState<number | ''>('');
  const [retProductId, setRetProductId] = useState<number | ''>('');
  const [retQty, setRetQty] = useState<number>(1);
  const [retPrice, setRetPrice] = useState<number>(0);
  const [retReason, setRetReason] = useState('');
  const [retCondition, setRetCondition] = useState('opened_good');
  const [retRefundType, setRetRefundType] = useState('credit_note');

  // Supplier Form State
  const [supName, setSupName] = useState('');
  const [supContact, setSupContact] = useState('');
  const [supEmail, setSupEmail] = useState('');
  const [supPhone, setSupPhone] = useState('');
  const [supGstin, setSupGstin] = useState('');
  const [supPan, setSupPan] = useState('');
  const [supBankName, setSupBankName] = useState('');
  const [supBankAccount, setSupBankAccount] = useState('');
  const [supBankIfsc, setSupBankIfsc] = useState('');

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
  const { data: settings } = useInventorySettings();
  const { data: transfers = [] } = useTransfers();
  const { data: reconciliations = [] } = useReconciliations();
  const { data: purchaseRequests = [] } = usePurchaseRequests();
  const { data: grns = [] } = useGoodsReceiptNotes();
  const { data: returns = [] } = useCustomerReturns();
  const { data: reorderReport } = useReorderReport();
  const { data: profitabilityReport } = useProfitabilityReport();
  const { data: returnsReport } = useCustomerReturnsReport();

  // Mutations
  const createItemMutation = useCreateCatalogItem();
  const createCategoryMutation = useCreateCatalogCategory();
  const createWarehouseMutation = useCreateWarehouse();
  const adjustStockMutation = useAdjustStock();
  const transferStockMutation = useTransferStock();
  const createVariantMutation = useCreateProductVariant();
  const createSupplierMutation = useCreateSupplier();
  const createPoMutation = useCreatePurchaseOrder();
  const updateSettingsMutation = useUpdateInventorySettings();
  const createTransferReqMutation = useCreateTransferRequest();
  const approveTransferMutation = useApproveTransfer();
  const dispatchTransferMutation = useDispatchTransfer();
  const receiveTransferMutation = useReceiveTransfer();
  const createRecMutation = useCreateReconciliation();
  const applyRecMutation = useApplyReconciliation();
  const createPrMutation = useCreatePurchaseRequest();
  const approvePrMutation = useApprovePurchaseRequest();
  const createBillMutation = useCreateBillFromPO();
  const createReturnMutation = useCreateCustomerReturn();
  const inspectReturnMutation = useInspectCustomerReturn();
  const approveReturnMutation = useApproveCustomerReturn();

  // Handlers
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
        secondary_unit: itemSecondaryUnit.trim() || undefined,
        price: itemPrice,
        min_selling_price: itemMinPrice > 0 ? itemMinPrice : undefined,
        cost_price: itemCostPrice,
        tax_rate: itemTaxRate,
        hsn_sac_code: itemHsn.trim() || undefined,
        barcode: itemBarcode.trim() || undefined,
        reorder_level: itemReorderLevel,
        reorder_quantity: itemReorderQty,
        opening_stock: itemOpeningStock > 0 ? itemOpeningStock : undefined,
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
        },
      }
    );
  };

  const handleCreateSupplier = (e: React.FormEvent) => {
    e.preventDefault();
    if (!supName.trim()) return;

    createSupplierMutation.mutate(
      {
        name: supName.trim(),
        contact_name: supContact.trim() || undefined,
        email: supEmail.trim() || undefined,
        phone: supPhone.trim() || undefined,
        gstin: supGstin.trim() || undefined,
        pan_number: supPan.trim() || undefined,
        bank_name: supBankName.trim() || undefined,
        bank_account_number: supBankAccount.trim() || undefined,
        bank_ifsc: supBankIfsc.trim() || undefined,
      },
      {
        onSuccess: () => {
          setShowSupplierModal(false);
          setSupName('');
          setSupContact('');
          setSupEmail('');
          setSupPhone('');
          setSupGstin('');
          setSupPan('');
          setSupBankName('');
          setSupBankAccount('');
          setSupBankIfsc('');
        },
      }
    );
  };

  const handleRevealBankDetails = async (supplierId: number) => {
    try {
      const res = await api.getSupplierBankDetails(supplierId);
      setRevealedBankDetails((prev) => ({ ...prev, [supplierId]: res.data }));
      addToast('Secure vendor bank details unlocked under audit log', 'success');
    } catch (err: any) {
      addToast(err.message || 'Access restricted', 'error');
    }
  };

  const handleCreateReturn = (e: React.FormEvent) => {
    e.preventDefault();
    if (!retCustomerId || !retProductId || retQty <= 0) return;

    createReturnMutation.mutate(
      {
        customer_id: Number(retCustomerId),
        return_reason: retReason || 'Product return request',
        product_condition: retCondition,
        refund_type: retRefundType,
        items: [
          {
            product_id: Number(retProductId),
            quantity: retQty,
            unit_price: retPrice,
          },
        ],
      },
      {
        onSuccess: () => {
          setShowReturnModal(false);
          setRetCustomerId('');
          setRetProductId('');
          setRetQty(1);
          setRetPrice(0);
          setRetReason('');
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
              Product, Inventory, Procurement & Vendor Platform
            </h2>
            <p className="text-xs text-slate-400">
              Complete stock management, multi-warehouse transfers, procurement workflow, vendors, and RMA customer returns
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
              onClick={() => setShowReturnModal(true)}
              className="flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold border border-slate-700 transition-colors"
            >
              <RotateCcw className="w-4 h-4 text-rose-400" />
              RMA Return
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
            { id: 'products', label: 'Products & SKUs', icon: Package },
            { id: 'services', label: 'Services', icon: Layers },
            { id: 'inventory', label: 'Warehouse Stock', icon: WarehouseIcon },
            { id: 'transfers', label: 'Transfers', icon: ArrowRightLeft },
            { id: 'reconciliation', label: 'Physical Count', icon: FileCheck },
            { id: 'procurement', label: 'Purchase & GRN', icon: Truck },
            { id: 'vendors', label: 'Vendors', icon: Building },
            { id: 'returns', label: 'RMA Returns', icon: RotateCcw },
            { id: 'reports', label: 'Reports & Valuation', icon: BarChart3 },
            { id: 'settings', label: 'Module Settings', icon: Settings },
          ].map((tab) => {
            const Icon = tab.icon;
            const isActive = activeTab === tab.id;
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id as CatalogTab)}
                className={`flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-medium transition-all shrink-0 ${
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

      {/* Optional Feature Notice if Disabled */}
      {settings && !settings.is_inventory_enabled && (
        <div className="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <AlertTriangle className="w-6 h-6 text-amber-400 shrink-0" />
            <div>
              <p className="text-sm font-semibold text-amber-200">Inventory Module is Optional & Currently Deactivated</p>
              <p className="text-xs text-amber-300/80">
                You can operate sales and invoices without maintaining physical stock tracking. To enable full stock management, toggle activation in Settings.
              </p>
            </div>
          </div>
          <button
            onClick={() => updateSettingsMutation.mutate({ is_inventory_enabled: true })}
            className="px-3.5 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl transition-colors shrink-0"
          >
            Activate Inventory
          </button>
        </div>
      )}

      {/* KPI Strip */}
      {valuation && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 flex items-center gap-3">
            <div className="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 shrink-0">
              <DollarSign className="w-6 h-6" />
            </div>
            <div>
              <p className="text-xs text-slate-400 font-medium">Cost Valuation</p>
              <p className="text-lg font-bold text-slate-100 font-mono">₹{valuation.total_cost_valuation.toLocaleString()}</p>
              <p className="text-[10px] text-slate-500">{settings?.valuation_method?.replace('_', ' ').toUpperCase() || 'MOVING AVG'}</p>
            </div>
          </div>

          <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 flex items-center gap-3">
            <div className="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 shrink-0">
              <Package className="w-6 h-6" />
            </div>
            <div>
              <p className="text-xs text-slate-400 font-medium">Total Inventory Units</p>
              <p className="text-lg font-bold text-slate-100 font-mono">{valuation.total_units.toLocaleString()}</p>
              <p className="text-[10px] text-slate-500">{valuation.total_skus} Active SKUs</p>
            </div>
          </div>

          <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 flex items-center gap-3">
            <div className="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 shrink-0">
              <AlertTriangle className="w-6 h-6" />
            </div>
            <div>
              <p className="text-xs text-slate-400 font-medium">Low Stock Alerts</p>
              <p className="text-lg font-bold text-amber-300 font-mono">{lowStockAlerts.length}</p>
              <p className="text-[10px] text-slate-500">Need replenishment</p>
            </div>
          </div>

          <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 flex items-center gap-3">
            <div className="w-12 h-12 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-sky-400 shrink-0">
              <WarehouseIcon className="w-6 h-6" />
            </div>
            <div>
              <p className="text-xs text-slate-400 font-medium">Warehouses & Outlets</p>
              <p className="text-lg font-bold text-slate-100 font-mono">{warehouses.length}</p>
              <p className="text-[10px] text-slate-500">{transfers.length} Stock transfers</p>
            </div>
          </div>
        </div>
      )}

      {/* TAB 1 & 2: Products & Services */}
      {(activeTab === 'products' || activeTab === 'services') && (
        <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-4">
            <div className="flex items-center gap-2 flex-1 max-w-md">
              <div className="relative w-full">
                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
                <input
                  type="text"
                  placeholder="Search products by name, SKU, or brand..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="w-full bg-slate-800/80 border border-slate-700 rounded-xl pl-9 pr-3 py-2 text-xs text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>
            </div>

            <div className="flex items-center gap-2">
              <select
                value={selectedCategoryId || ''}
                onChange={(e) => setSelectedCategoryId(e.target.value ? Number(e.target.value) : undefined)}
                className="bg-slate-800 border border-slate-700 rounded-xl px-3 py-1.5 text-xs text-slate-300 focus:outline-none"
              >
                <option value="">All Categories</option>
                {categories.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>

              <button
                onClick={() => window.open('/api/v1/crm/catalog/export', '_blank')}
                className="flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold border border-slate-700"
              >
                <Download className="w-3.5 h-3.5" />
                Export CSV
              </button>
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse text-xs">
              <thead>
                <tr className="border-b border-slate-800 text-slate-400 font-semibold bg-slate-800/40">
                  <th className="p-3">Item Name & SKU</th>
                  <th className="p-3">Category & Brand</th>
                  <th className="p-3">Selling Price</th>
                  <th className="p-3">Cost Price</th>
                  <th className="p-3">Available Stock</th>
                  <th className="p-3">Reorder Level</th>
                  <th className="p-3">Status</th>
                  <th className="p-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/60">
                {allCatalog.map((prod) => (
                  <tr key={prod.id} className="hover:bg-slate-800/30 transition-colors">
                    <td className="p-3">
                      <div className="font-semibold text-slate-200">{prod.name}</div>
                      <div className="text-[11px] font-mono text-slate-400 flex items-center gap-2">
                        <span>{prod.sku}</span>
                        {prod.barcode && <span className="text-[10px] bg-slate-800 px-1.5 py-0.5 rounded">|| {prod.barcode}</span>}
                      </div>
                    </td>
                    <td className="p-3">
                      <div className="text-slate-300">{prod.category || 'General'}</div>
                      <div className="text-[10px] text-slate-500">{prod.brand || '—'}</div>
                    </td>
                    <td className="p-3 font-mono font-bold text-emerald-400">₹{prod.price.toLocaleString()}</td>
                    <td className="p-3 font-mono text-slate-400">₹{prod.cost_price.toLocaleString()}</td>
                    <td className="p-3">
                      {prod.track_inventory ? (
                        <span
                          className={`font-mono font-bold px-2 py-0.5 rounded text-[11px] ${
                            prod.available_stock <= (prod.reorder_level || 10)
                              ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30'
                              : 'bg-emerald-500/20 text-emerald-300'
                          }`}
                        >
                          {prod.available_stock} {prod.unit}
                        </span>
                      ) : (
                        <span className="text-slate-500">Service</span>
                      )}
                    </td>
                    <td className="p-3 font-mono text-slate-400">{prod.reorder_level || 10} {prod.unit}</td>
                    <td className="p-3">
                      <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        {prod.status}
                      </span>
                    </td>
                    <td className="p-3 text-right">
                      <button
                        onClick={() => setSelectedProductForVariant(prod)}
                        className="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded text-[11px] font-medium transition-colors"
                      >
                        + Variant
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 3: Warehouses & Stock Levels */}
      {activeTab === 'inventory' && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {warehouses.map((wh) => (
              <div key={wh.id} className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 space-y-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <WarehouseIcon className="w-5 h-5 text-indigo-400" />
                    <div>
                      <h4 className="font-bold text-slate-100 text-sm">{wh.name}</h4>
                      <p className="text-[10px] font-mono text-slate-400">{wh.code}</p>
                    </div>
                  </div>
                  {wh.is_primary && (
                    <span className="px-2 py-0.5 bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 rounded-full text-[10px] font-bold">
                      Primary Hub
                    </span>
                  )}
                </div>

                <div className="space-y-1 text-xs text-slate-400">
                  <p>Address: {wh.address || 'Central Premises'}, {wh.city || 'HQ'}</p>
                  <p>Manager: {wh.contact_name || wh.manager?.name || 'Assigned Officer'}</p>
                  {wh.capacity_units && (
                    <div className="pt-2">
                      <div className="flex justify-between text-[10px] text-slate-400 pb-1">
                        <span>Capacity: {wh.capacity_units} units</span>
                      </div>
                      <div className="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                        <div className="bg-indigo-500 h-full rounded-full" style={{ width: '45%' }} />
                      </div>
                    </div>
                  )}
                </div>
              </div>
            ))}
          </div>

          <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 space-y-3">
            <h3 className="font-bold text-slate-200 text-sm">Stock Levels across Warehouses</h3>
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse text-xs">
                <thead>
                  <tr className="border-b border-slate-800 text-slate-400 font-semibold bg-slate-800/40">
                    <th className="p-3">Product Name</th>
                    <th className="p-3">Warehouse</th>
                    <th className="p-3">Current Physical</th>
                    <th className="p-3">Reserved</th>
                    <th className="p-3">Incoming</th>
                    <th className="p-3">Net Available</th>
                    <th className="p-3">Damaged</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/60">
                  {allCatalog.map((prod) =>
                    (prod.stocks || []).map((stock) => (
                      <tr key={stock.id} className="hover:bg-slate-800/30">
                        <td className="p-3 font-semibold text-slate-200">{prod.name}</td>
                        <td className="p-3 text-slate-400">{stock.warehouse?.name || 'Warehouse'}</td>
                        <td className="p-3 font-mono text-slate-200">{stock.current_stock}</td>
                        <td className="p-3 font-mono text-amber-400">{stock.reserved_stock}</td>
                        <td className="p-3 font-mono text-sky-400">{stock.incoming_stock || 0}</td>
                        <td className="p-3 font-mono font-bold text-emerald-400">{stock.available_stock}</td>
                        <td className="p-3 font-mono text-rose-400">{stock.damaged_stock}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 4: Inter-Warehouse Transfers */}
      {activeTab === 'transfers' && (
        <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 space-y-4">
          <div className="flex items-center justify-between">
            <h3 className="font-bold text-slate-200 text-sm">Inter-Warehouse Transfers & Dispatch Tracking</h3>
            <button
              onClick={() => setShowTransferModal(true)}
              className="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold"
            >
              + New Transfer Request
            </button>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse text-xs">
              <thead>
                <tr className="border-b border-slate-800 text-slate-400 font-semibold bg-slate-800/40">
                  <th className="p-3">Transfer #</th>
                  <th className="p-3">Source Warehouse</th>
                  <th className="p-3">Destination Warehouse</th>
                  <th className="p-3">Status</th>
                  <th className="p-3">Damaged Quantity</th>
                  <th className="p-3 text-right">Lifecycle Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/60">
                {transfers.map((trf) => (
                  <tr key={trf.id} className="hover:bg-slate-800/30">
                    <td className="p-3 font-mono font-bold text-slate-200">{trf.transfer_number}</td>
                    <td className="p-3 text-slate-300">{trf.source_warehouse?.name || 'Source'}</td>
                    <td className="p-3 text-slate-300">{trf.destination_warehouse?.name || 'Destination'}</td>
                    <td className="p-3">
                      <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-800 text-slate-300">
                        {trf.status}
                      </span>
                    </td>
                    <td className="p-3 font-mono text-rose-400">{trf.damaged_quantity || 0} units</td>
                    <td className="p-3 text-right space-x-2">
                      {trf.status === 'draft' && (
                        <button
                          onClick={() => approveTransferMutation.mutate(trf.id)}
                          className="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-[11px] font-semibold"
                        >
                          Approve
                        </button>
                      )}
                      {(trf.status === 'draft' || trf.status === 'approved') && (
                        <button
                          onClick={() => dispatchTransferMutation.mutate(trf.id)}
                          className="px-2.5 py-1 bg-sky-600 hover:bg-sky-500 text-white rounded text-[11px] font-semibold"
                        >
                          Dispatch in Transit
                        </button>
                      )}
                      {trf.status === 'in_transit' && (
                        <button
                          onClick={() =>
                            receiveTransferMutation.mutate({
                              id: trf.id,
                              data: {
                                items: (trf.items || []).map((i) => ({
                                  item_id: i.id,
                                  accepted_quantity: i.quantity,
                                  damaged_quantity: 0,
                                })),
                              },
                            })
                          }
                          className="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-[11px] font-semibold"
                        >
                          Confirm Receipt
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 5: Physical Count & Reconciliations */}
      {activeTab === 'reconciliation' && (
        <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 space-y-4">
          <div className="flex items-center justify-between">
            <h3 className="font-bold text-slate-200 text-sm">Physical Stock Count & Variance Reconciliation</h3>
            <button
              onClick={() => setShowReconciliationModal(true)}
              className="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold"
            >
              + Start Physical Count
            </button>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse text-xs">
              <thead>
                <tr className="border-b border-slate-800 text-slate-400 font-semibold bg-slate-800/40">
                  <th className="p-3">Reconciliation #</th>
                  <th className="p-3">Warehouse</th>
                  <th className="p-3">Counted By</th>
                  <th className="p-3">Variance Cost</th>
                  <th className="p-3">Status</th>
                  <th className="p-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/60">
                {reconciliations.map((rec) => (
                  <tr key={rec.id} className="hover:bg-slate-800/30">
                    <td className="p-3 font-mono font-bold text-slate-200">{rec.reconciliation_number}</td>
                    <td className="p-3 text-slate-300">{rec.warehouse?.name}</td>
                    <td className="p-3 text-slate-400">{rec.counter?.name}</td>
                    <td className="p-3 font-mono font-bold text-amber-400">₹{rec.total_variance_cost?.toLocaleString()}</td>
                    <td className="p-3">
                      <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-800 text-slate-300">
                        {rec.status}
                      </span>
                    </td>
                    <td className="p-3 text-right">
                      {rec.status === 'in_progress' && (
                        <button
                          onClick={() => applyRecMutation.mutate(rec.id)}
                          className="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-[11px] font-semibold"
                        >
                          Apply Stock Adjustments
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 6: Purchases & Suppliers */}
      {activeTab === 'procurement' && (
        <div className="space-y-6">
          <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="font-bold text-slate-200 text-sm">Purchase Orders & GRN Inspection</h3>
              <div className="flex items-center gap-2">
                <button
                  onClick={() => setShowPrModal(true)}
                  className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold border border-slate-700"
                >
                  + Purchase Request (PR)
                </button>
                <button
                  onClick={() => setShowPoModal(true)}
                  className="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold"
                >
                  + Create Purchase Order
                </button>
              </div>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse text-xs">
                <thead>
                  <tr className="border-b border-slate-800 text-slate-400 font-semibold bg-slate-800/40">
                    <th className="p-3">PO Number</th>
                    <th className="p-3">Vendor</th>
                    <th className="p-3">Warehouse</th>
                    <th className="p-3">Total Amount</th>
                    <th className="p-3">Status</th>
                    <th className="p-3 text-right">Procurement Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/60">
                  {purchaseOrders.map((po) => (
                    <tr key={po.id} className="hover:bg-slate-800/30">
                      <td className="p-3 font-mono font-bold text-slate-200">
                        {po.po_number}
                        {po.revision_number ? <span className="text-[10px] text-amber-400 ml-1">(Rev {po.revision_number})</span> : null}
                      </td>
                      <td className="p-3 text-slate-300">{po.supplier?.name}</td>
                      <td className="p-3 text-slate-400">{po.warehouse?.name}</td>
                      <td className="p-3 font-mono font-bold text-emerald-400">₹{po.total_amount?.toLocaleString()}</td>
                      <td className="p-3">
                        <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-800 text-slate-300">
                          {po.status}
                        </span>
                      </td>
                      <td className="p-3 text-right space-x-2">
                        {po.status === 'ordered' && (
                          <button
                            onClick={() =>
                              api.receivePurchaseOrderWithQC(po.id, {
                                items: (po.items || []).map((i) => ({
                                  item_id: i.id,
                                  quantity: i.quantity,
                                  accepted_quantity: i.quantity,
                                  rejected_damaged_quantity: 0,
                                })),
                              }).then(() => addToast('GRN inspection recorded and stock updated', 'success'))
                            }
                            className="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-[11px] font-semibold"
                          >
                            Receive GRN
                          </button>
                        )}
                        {!po.bill_id && po.status !== 'cancelled' && (
                          <button
                            onClick={() => createBillMutation.mutate({ id: po.id })}
                            className="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-[11px] font-semibold"
                          >
                            Create Vendor Bill
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 7: Vendors & Suppliers */}
      {activeTab === 'vendors' && (
        <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 space-y-4">
          <div className="flex items-center justify-between">
            <h3 className="font-bold text-slate-200 text-sm">Vendor & Supplier Directory</h3>
            <button
              onClick={() => setShowSupplierModal(true)}
              className="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold"
            >
              + Add New Vendor
            </button>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {suppliers.map((sup) => (
              <div key={sup.id} className="p-4 rounded-2xl border bg-slate-800/40 border-slate-700/60 space-y-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Building className="w-5 h-5 text-indigo-400" />
                    <div>
                      <h4 className="font-bold text-slate-100 text-sm">{sup.name}</h4>
                      <p className="text-[10px] font-mono text-slate-400">{sup.code} | {sup.payment_terms}</p>
                    </div>
                  </div>
                  <span className="font-mono text-xs font-bold text-amber-400">
                    Due: ₹{(sup.outstanding_balance || 0).toLocaleString()}
                  </span>
                </div>

                <div className="grid grid-cols-2 gap-2 text-xs text-slate-300">
                  <p>Contact: {sup.contact_name || '—'}</p>
                  <p>Phone: {sup.phone || '—'}</p>
                  <p>GSTIN: {sup.gstin || 'Unregistered'}</p>
                  <p>PAN: {sup.pan_number || '—'}</p>
                </div>

                {/* Restricted Bank Details Strip */}
                <div className="p-2.5 rounded-xl bg-slate-900/80 border border-slate-800 text-xs flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <CreditCard className="w-4 h-4 text-slate-400" />
                    <div>
                      <span className="text-slate-400 text-[11px]">Bank: </span>
                      <span className="text-slate-200 font-mono text-[11px]">
                        {revealedBankDetails[sup.id]?.bank_account_number || sup.masked_bank_account || '••••••••'}
                      </span>
                    </div>
                  </div>

                  {!revealedBankDetails[sup.id] && (
                    <button
                      onClick={() => handleRevealBankDetails(sup.id)}
                      className="px-2 py-0.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded text-[10px] font-semibold flex items-center gap-1"
                    >
                      <Eye className="w-3 h-3" />
                      View Unmasked
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* TAB 8: Customer Returns (RMA) */}
      {activeTab === 'returns' && (
        <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 space-y-4">
          <div className="flex items-center justify-between">
            <h3 className="font-bold text-slate-200 text-sm">Customer RMA Returns & Restock / Quarantine</h3>
            <button
              onClick={() => setShowReturnModal(true)}
              className="px-3 py-1.5 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-semibold"
            >
              + Create RMA Request
            </button>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse text-xs">
              <thead>
                <tr className="border-b border-slate-800 text-slate-400 font-semibold bg-slate-800/40">
                  <th className="p-3">RMA #</th>
                  <th className="p-3">Customer</th>
                  <th className="p-3">Condition</th>
                  <th className="p-3">Restock Action</th>
                  <th className="p-3">Refund Amount</th>
                  <th className="p-3">Status</th>
                  <th className="p-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/60">
                {returns.map((ret) => (
                  <tr key={ret.id} className="hover:bg-slate-800/30">
                    <td className="p-3 font-mono font-bold text-slate-200">{ret.return_number}</td>
                    <td className="p-3 text-slate-300">{ret.customer?.name}</td>
                    <td className="p-3">
                      <span className="capitalize text-slate-300">{ret.product_condition.replace('_', ' ')}</span>
                    </td>
                    <td className="p-3">
                      <span className="capitalize text-slate-400">{ret.restock_action.replace('_', ' ')}</span>
                    </td>
                    <td className="p-3 font-mono font-bold text-rose-400">₹{ret.total_refund_amount?.toLocaleString()}</td>
                    <td className="p-3">
                      <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-800 text-slate-300">
                        {ret.status}
                      </span>
                    </td>
                    <td className="p-3 text-right space-x-2">
                      {ret.status !== 'completed' && (
                        <button
                          onClick={() => approveReturnMutation.mutate(ret.id)}
                          className="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-[11px] font-semibold"
                        >
                          Approve & Restock / Credit
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 9: Reports & Valuation */}
      {activeTab === 'reports' && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {/* Reorder Suggestions */}
            <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 space-y-3">
              <h4 className="font-bold text-slate-200 text-sm flex items-center gap-2">
                <AlertTriangle className="w-4 h-4 text-amber-400" />
                Automated Reorder Suggestions
              </h4>
              <p className="text-xs text-slate-400">
                Items currently at or below minimum threshold. Total estimated procurement investment:{' '}
                <span className="font-mono font-bold text-slate-200">
                  ₹{(reorderReport?.total_estimated_cost || 0).toLocaleString()}
                </span>
              </p>

              <div className="space-y-2 max-h-60 overflow-y-auto">
                {(reorderReport?.items || []).map((item: any, idx: number) => (
                  <div key={idx} className="p-2.5 rounded-xl bg-slate-800/60 flex items-center justify-between text-xs">
                    <div>
                      <p className="font-semibold text-slate-200">{item.product_name}</p>
                      <p className="text-[10px] text-slate-400">Stock: {item.current_stock} | Reorder: {item.suggested_reorder_quantity} units</p>
                    </div>
                    <span className="font-mono text-emerald-400 font-bold">₹{item.estimated_reorder_cost?.toLocaleString()}</span>
                  </div>
                ))}
              </div>
            </div>

            {/* Profitability */}
            <div className="p-4 rounded-2xl border bg-slate-900/60 border-slate-800 space-y-3">
              <h4 className="font-bold text-slate-200 text-sm flex items-center gap-2">
                <TrendingUp className="w-4 h-4 text-emerald-400" />
                Product Profitability & Margins
              </h4>
              <p className="text-xs text-slate-400">
                Average catalog margin: <span className="font-mono font-bold text-emerald-400">{profitabilityReport?.average_margin || 0}%</span>
              </p>

              <div className="space-y-2 max-h-60 overflow-y-auto">
                {(profitabilityReport?.products || []).slice(0, 5).map((p: any, idx: number) => (
                  <div key={idx} className="p-2.5 rounded-xl bg-slate-800/60 flex items-center justify-between text-xs">
                    <div>
                      <p className="font-semibold text-slate-200">{p.name}</p>
                      <p className="text-[10px] text-slate-400">Cost: ₹{p.cost_price} | Sell: ₹{p.selling_price}</p>
                    </div>
                    <span className="font-mono text-emerald-400 font-bold">{p.gross_margin_percent}% margin</span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* TAB 10: Settings */}
      {activeTab === 'settings' && settings && (
        <div className="p-6 rounded-2xl border bg-slate-900/60 border-slate-800 max-w-2xl space-y-6">
          <h3 className="font-bold text-slate-100 text-base">Inventory Feature Settings & Policies</h3>

          <div className="space-y-4">
            <div className="flex items-center justify-between p-3 rounded-xl bg-slate-800/40 border border-slate-700/60">
              <div>
                <p className="text-sm font-semibold text-slate-200">Enable Inventory & Stock Management</p>
                <p className="text-xs text-slate-400">Optional module. Disable if your company only provides non-stock services.</p>
              </div>
              <input
                type="checkbox"
                checked={settings.is_inventory_enabled}
                onChange={(e) => updateSettingsMutation.mutate({ is_inventory_enabled: e.target.checked })}
                className="w-5 h-5 accent-indigo-600 rounded"
              />
            </div>

            <div className="flex items-center justify-between p-3 rounded-xl bg-slate-800/40 border border-slate-700/60">
              <div>
                <p className="text-sm font-semibold text-slate-200">Stock Valuation Method</p>
                <p className="text-xs text-slate-400">Method used for balance sheet and inventory asset calculations.</p>
              </div>
              <select
                value={settings.valuation_method}
                onChange={(e) => updateSettingsMutation.mutate({ valuation_method: e.target.value as any })}
                className="bg-slate-900 border border-slate-700 rounded-xl px-3 py-1.5 text-xs text-slate-200"
              >
                <option value="moving_average">Moving Average Cost</option>
                <option value="fifo">FIFO (First-In, First-Out)</option>
                <option value="standard">Standard Cost</option>
              </select>
            </div>

            <div className="flex items-center justify-between p-3 rounded-xl bg-slate-800/40 border border-slate-700/60">
              <div>
                <p className="text-sm font-semibold text-slate-200">Auto-Reserve Stock on Order / Won Deal</p>
                <p className="text-xs text-slate-400">Automatically lock items so other sales agents do not double-sell.</p>
              </div>
              <input
                type="checkbox"
                checked={settings.auto_reserve_on_order}
                onChange={(e) => updateSettingsMutation.mutate({ auto_reserve_on_order: e.target.checked })}
                className="w-5 h-5 accent-indigo-600 rounded"
              />
            </div>

            <div className="flex items-center justify-between p-3 rounded-xl bg-slate-800/40 border border-slate-700/60">
              <div>
                <p className="text-sm font-semibold text-slate-200">Auto-Deduct Stock on Tax Invoice</p>
                <p className="text-xs text-slate-400">Deduct physical stock and generate immutable ledger record upon invoice issuance.</p>
              </div>
              <input
                type="checkbox"
                checked={settings.auto_deduct_on_invoice}
                onChange={(e) => updateSettingsMutation.mutate({ auto_deduct_on_invoice: e.target.checked })}
                className="w-5 h-5 accent-indigo-600 rounded"
              />
            </div>
          </div>
        </div>
      )}

      {/* New Product Modal */}
      {showItemModal && (
        <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-xl p-6 space-y-4 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between border-b border-slate-800 pb-3">
              <h3 className="font-bold text-slate-100 text-base">Create Product or Service</h3>
              <button onClick={() => setShowItemModal(false)} className="text-slate-400 hover:text-slate-200">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleCreateItem} className="space-y-4">
              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">Item Type</label>
                <div className="grid grid-cols-2 gap-2">
                  <button
                    type="button"
                    onClick={() => setItemType('product')}
                    className={`py-2 text-xs font-semibold rounded-xl border ${
                      itemType === 'product'
                        ? 'bg-indigo-600 text-white border-indigo-500'
                        : 'bg-slate-800 text-slate-400 border-slate-700'
                    }`}
                  >
                    Physical Product (Stock Tracked)
                  </button>
                  <button
                    type="button"
                    onClick={() => setItemType('service')}
                    className={`py-2 text-xs font-semibold rounded-xl border ${
                      itemType === 'service'
                        ? 'bg-indigo-600 text-white border-indigo-500'
                        : 'bg-slate-800 text-slate-400 border-slate-700'
                    }`}
                  >
                    Service / Retainer
                  </button>
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">Item Name *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Cisco SG350 Switch or IT Retainer"
                  value={itemName}
                  onChange={(e) => setItemName(e.target.value)}
                  className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">SKU</label>
                  <input
                    type="text"
                    placeholder="Auto-generated if empty"
                    value={itemSku}
                    onChange={(e) => setItemSku(e.target.value)}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Barcode</label>
                  <input
                    type="text"
                    placeholder="e.g. 890103038291"
                    value={itemBarcode}
                    onChange={(e) => setItemBarcode(e.target.value)}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Selling Price (₹) *</label>
                  <input
                    type="number"
                    step="0.01"
                    required
                    value={itemPrice || ''}
                    onChange={(e) => setItemPrice(Number(e.target.value))}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Cost Price (₹)</label>
                  <input
                    type="number"
                    step="0.01"
                    value={itemCostPrice || ''}
                    onChange={(e) => setItemCostPrice(Number(e.target.value))}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none"
                  />
                </div>
              </div>

              {itemType === 'product' && (
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-medium text-slate-300 mb-1">Opening Stock</label>
                    <input
                      type="number"
                      step="1"
                      value={itemOpeningStock || ''}
                      onChange={(e) => setItemOpeningStock(Number(e.target.value))}
                      className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-slate-300 mb-1">Reorder Level</label>
                    <input
                      type="number"
                      step="1"
                      value={itemReorderLevel}
                      onChange={(e) => setItemReorderLevel(Number(e.target.value))}
                      className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none"
                    />
                  </div>
                </div>
              )}

              <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
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
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold"
                >
                  {createItemMutation.isPending ? 'Saving...' : 'Create Item'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* New Vendor Modal */}
      {showSupplierModal && (
        <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg p-6 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between border-b border-slate-800 pb-3">
              <h3 className="font-bold text-slate-100 text-base">Add New Supplier / Vendor</h3>
              <button onClick={() => setShowSupplierModal(false)} className="text-slate-400 hover:text-slate-200">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleCreateSupplier} className="space-y-3">
              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">Vendor Company Name *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Foxconn Electronics India Ltd"
                  value={supName}
                  onChange={(e) => setSupName(e.target.value)}
                  className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Contact Person</label>
                  <input
                    type="text"
                    value={supContact}
                    onChange={(e) => setSupContact(e.target.value)}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Phone</label>
                  <input
                    type="text"
                    value={supPhone}
                    onChange={(e) => setSupPhone(e.target.value)}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">GSTIN</label>
                  <input
                    type="text"
                    placeholder="27AABCU9603R1ZM"
                    value={supGstin}
                    onChange={(e) => setSupGstin(e.target.value)}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">PAN</label>
                  <input
                    type="text"
                    placeholder="AABCU9603R"
                    value={supPan}
                    onChange={(e) => setSupPan(e.target.value)}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none"
                  />
                </div>
              </div>

              <div className="p-3 bg-slate-800/40 rounded-xl border border-slate-700 space-y-2">
                <p className="text-[11px] font-semibold text-slate-300">Bank Details (Restricted Access)</p>
                <div className="grid grid-cols-2 gap-2">
                  <input
                    type="text"
                    placeholder="Bank Name"
                    value={supBankName}
                    onChange={(e) => setSupBankName(e.target.value)}
                    className="bg-slate-900 border border-slate-700 rounded px-2.5 py-1.5 text-xs text-slate-100"
                  />
                  <input
                    type="text"
                    placeholder="Account Number"
                    value={supBankAccount}
                    onChange={(e) => setSupBankAccount(e.target.value)}
                    className="bg-slate-900 border border-slate-700 rounded px-2.5 py-1.5 text-xs text-slate-100 font-mono"
                  />
                </div>
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowSupplierModal(false)}
                  className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createSupplierMutation.isPending}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold"
                >
                  {createSupplierMutation.isPending ? 'Saving...' : 'Add Vendor'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Customer Return (RMA) Modal */}
      {showReturnModal && (
        <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg p-6 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between border-b border-slate-800 pb-3">
              <h3 className="font-bold text-slate-100 text-base">Create Customer RMA Return</h3>
              <button onClick={() => setShowReturnModal(false)} className="text-slate-400 hover:text-slate-200">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleCreateReturn} className="space-y-3">
              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">Customer ID *</label>
                <input
                  type="number"
                  required
                  placeholder="e.g. 1"
                  value={retCustomerId}
                  onChange={(e) => setRetCustomerId(Number(e.target.value))}
                  className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Product</label>
                  <select
                    value={retProductId}
                    onChange={(e) => {
                      const id = Number(e.target.value);
                      setRetProductId(id);
                      const p = allCatalog.find((x) => x.id === id);
                      if (p) setRetPrice(p.price);
                    }}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none"
                  >
                    <option value="">Select Item</option>
                    {allCatalog.map((p) => (
                      <option key={p.id} value={p.id}>
                        {p.name}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Quantity</label>
                  <input
                    type="number"
                    min="1"
                    value={retQty}
                    onChange={(e) => setRetQty(Number(e.target.value))}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 font-mono focus:outline-none"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">Return Reason *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Defective fan or wrong specifications delivered"
                  value={retReason}
                  onChange={(e) => setRetReason(e.target.value)}
                  className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Condition</label>
                  <select
                    value={retCondition}
                    onChange={(e) => setRetCondition(e.target.value)}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none"
                  >
                    <option value="unopened">Unopened / Sealed</option>
                    <option value="opened_good">Opened Good Condition</option>
                    <option value="defective">Defective / Malfunctioning</option>
                    <option value="damaged_in_transit">Damaged in Transit</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Refund Type</label>
                  <select
                    value={retRefundType}
                    onChange={(e) => setRetRefundType(e.target.value)}
                    className="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none"
                  >
                    <option value="credit_note">Credit Note (Finance Linked)</option>
                    <option value="replacement">Item Replacement</option>
                    <option value="bank_refund">Direct Bank Refund</option>
                  </select>
                </div>
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowReturnModal(false)}
                  className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createReturnMutation.isPending}
                  className="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-semibold"
                >
                  {createReturnMutation.isPending ? 'Submitting...' : 'Submit RMA'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
