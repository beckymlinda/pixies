<?php

namespace App\Services;

use App\Models\ProductUnit;
use App\Models\ProductUnitPrice;
use App\Models\Item;

class InventoryService
{
    /**
     * Convert quantity from a specific unit to base units.
     * 
     * @param int $itemId
     * @param float $quantity
     * @param string $unitName
     * @return float
     */
    public function convertToBaseUnits(int $itemId, float $quantity, string $unitName): float
    {
        $unit = ProductUnit::where('item_id', $itemId)
            ->where('unit_name', $unitName)
            ->first();

        if (!$unit) {
            // If unit doesn't exist, assume it's already base unit (conversion factor = 1)
            return $quantity;
        }

        if ($unit->is_base_unit) {
            return $quantity;
        }

        return $quantity * $unit->conversion_factor;
    }

    /**
     * Convert quantity from base units to a specific unit.
     * 
     * @param int $itemId
     * @param float $baseQuantity
     * @param string $unitName
     * @return float
     */
    public function convertFromBaseUnits(int $itemId, float $baseQuantity, string $unitName): float
    {
        $unit = ProductUnit::where('item_id', $itemId)
            ->where('unit_name', $unitName)
            ->first();

        if (!$unit) {
            // If unit doesn't exist, assume it's base unit (conversion factor = 1)
            return $baseQuantity;
        }

        if ($unit->is_base_unit) {
            return $baseQuantity;
        }

        return $baseQuantity / $unit->conversion_factor;
    }

    /**
     * Calculate sale amount based on unit and quantity.
     * 
     * @param int $itemId
     * @param float $quantity
     * @param string $unitName
     * @return float
     */
    public function calculateSaleAmount(int $itemId, float $quantity, string $unitName): float
    {
        $price = ProductUnitPrice::where('item_id', $itemId)
            ->where('unit_name', $unitName)
            ->first();

        if (!$price) {
            // Fallback to item price if unit price doesn't exist
            $item = Item::find($itemId);
            if ($item && $item->price) {
                return $quantity * $item->price;
            }
            return 0;
        }

        return $quantity * $price->selling_price;
    }

    /**
     * Get base unit for an item.
     * 
     * @param int $itemId
     * @return ProductUnit|null
     */
    public function getBaseUnit(int $itemId): ?ProductUnit
    {
        return ProductUnit::where('item_id', $itemId)
            ->where('is_base_unit', true)
            ->first();
    }

    /**
     * Get all units for an item.
     * 
     * @param int $itemId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getItemUnits(int $itemId)
    {
        return ProductUnit::where('item_id', $itemId)->get();
    }

    /**
     * Convert mixed units to base units.
     * 
     * @param int $itemId
     * @param array $mixedQuantities ['bottle' => 3, 'pack' => 2]
     * @return float
     */
    public function convertMixedToBaseUnits(int $itemId, array $mixedQuantities): float
    {
        $totalBaseUnits = 0;

        foreach ($mixedQuantities as $unitName => $quantity) {
            $totalBaseUnits += $this->convertToBaseUnits($itemId, $quantity, $unitName);
        }

        return $totalBaseUnits;
    }

    /**
     * Convert base units to human-readable format.
     * 
     * @param int $itemId
     * @param float $baseQuantity
     * @return array
     */
    public function convertToHumanReadable(int $itemId, float $baseQuantity): array
    {
        $units = $this->getItemUnits($itemId);
        $nonBaseUnits = $units->where('is_base_unit', false)->sortByDesc('conversion_factor');
        
        $result = [];
        $remaining = $baseQuantity;

        foreach ($nonBaseUnits as $unit) {
            if ($remaining >= $unit->conversion_factor) {
                $quantity = floor($remaining / $unit->conversion_factor);
                $result[$unit->unit_name] = $quantity;
                $remaining -= $quantity * $unit->conversion_factor;
            }
        }

        // Add remaining base units
        if ($remaining > 0) {
            $baseUnit = $this->getBaseUnit($itemId);
            if ($baseUnit) {
                $result[$baseUnit->unit_name] = $remaining;
            } else {
                $result['unit'] = $remaining;
            }
        }

        return $result;
    }

    /**
     * Get price for a specific unit.
     * 
     * @param int $itemId
     * @param string $unitName
     * @return ProductUnitPrice|null
     */
    public function getUnitPrice(int $itemId, string $unitName): ?ProductUnitPrice
    {
        return ProductUnitPrice::where('item_id', $itemId)
            ->where('unit_name', $unitName)
            ->first();
    }
}
