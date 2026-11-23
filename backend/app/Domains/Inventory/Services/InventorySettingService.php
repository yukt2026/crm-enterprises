<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Models\InventorySetting;
use App\Domains\Inventory\Models\Warehouse;

class InventorySettingService
{
    /**
     * Get or create tenant inventory settings.
     */
    public function getSettings(int $tenantId): InventorySetting
    {
        $setting = InventorySetting::where('tenant_id', $tenantId)->first();
        if ($setting) {
            return $setting;
        }

        $defaultWarehouse = Warehouse::where('tenant_id', $tenantId)->where('is_primary', true)->first();

        return InventorySetting::create([
            'tenant_id' => $tenantId,
            'is_inventory_enabled' => true,
            'valuation_method' => 'moving_average',
            'default_warehouse_id' => $defaultWarehouse?->id,
            'allow_negative_stock' => false,
            'auto_reserve_on_order' => true,
            'auto_deduct_on_invoice' => true,
            'low_stock_notification' => true,
            'default_reorder_lead_days' => 7,
        ]);
    }

    /**
     * Update tenant inventory settings.
     */
    public function updateSettings(int $tenantId, array $data): InventorySetting
    {
        $setting = $this->getSettings($tenantId);

        $setting->update([
            'is_inventory_enabled' => $data['is_inventory_enabled'] ?? $setting->is_inventory_enabled,
            'valuation_method' => $data['valuation_method'] ?? $setting->valuation_method,
            'default_warehouse_id' => $data['default_warehouse_id'] ?? $setting->default_warehouse_id,
            'allow_negative_stock' => $data['allow_negative_stock'] ?? $setting->allow_negative_stock,
            'auto_reserve_on_order' => $data['auto_reserve_on_order'] ?? $setting->auto_reserve_on_order,
            'auto_deduct_on_invoice' => $data['auto_deduct_on_invoice'] ?? $setting->auto_deduct_on_invoice,
            'low_stock_notification' => $data['low_stock_notification'] ?? $setting->low_stock_notification,
            'default_reorder_lead_days' => $data['default_reorder_lead_days'] ?? $setting->default_reorder_lead_days,
        ]);

        return $setting->fresh('defaultWarehouse');
    }

    /**
     * Check if inventory module is active for tenant.
     */
    public function isInventoryEnabled(int $tenantId): bool
    {
        return (bool) $this->getSettings($tenantId)->is_inventory_enabled;
    }
}
