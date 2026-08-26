<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\InventoryService;

class StockEntryItem extends Model
{
    protected $fillable = [
        'stock_entry_id', 'item_id', 'opening_stock', 
        'ordered_stock', 'total_stock', 'closing_stock', 
        'sold_quantity', 'sales_amount', 'price', 'purchase_price', 'expiry_date',
        'unit_name' // Track which unit was used for sales
    ];

    protected $casts = [
        'expiry_date' => 'date',
    ];

    public function stockEntry()
    {
        return $this->belongsTo(Sale::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    // Auto-calculate total_stock when opening_stock or ordered_stock changes
    public function setOpeningStockAttribute($value)
    {
        $this->attributes['opening_stock'] = $value;
        $this->calculateTotalStock();
    }

    public function setOrderedStockAttribute($value)
    {
        $this->attributes['ordered_stock'] = $value;
        $this->calculateTotalStock();
    }

    public function setClosingStockAttribute($value)
    {
        // Allow manual override of closing stock if needed
        $this->attributes['closing_stock'] = $value;
        $this->calculateSoldQuantity();
    }

    protected function calculateTotalStock()
    {
        $this->attributes['total_stock'] = $this->opening_stock + $this->ordered_stock;
        $this->autoCalculateClosingStock();
    }

    protected function autoCalculateClosingStock()
    {
        $opening = (float) ($this->attributes['opening_stock'] ?? 0);
        $ordered = (float) ($this->attributes['ordered_stock'] ?? 0);
        $total = $opening + $ordered;
        $sold = (float) ($this->attributes['sold_quantity'] ?? 0);

        if (!isset($this->attributes['sold_quantity']) || $this->attributes['sold_quantity'] === null) {
            $this->attributes['sold_quantity'] = 0;
            $sold = 0;
        }

        $this->attributes['closing_stock'] = max(0, $total - $sold);
        $this->calculateSalesAmount();
    }

    protected function calculateSoldQuantity()
    {
        // If closing stock is being set manually, calculate sold quantity
        if (isset($this->attributes['closing_stock'])) {
            $this->attributes['sold_quantity'] = $this->total_stock - $this->attributes['closing_stock'];
        } else {
            // Auto-calculate based on available data
            $this->autoCalculateClosingStock();
        }
        
        $this->calculateSalesAmount();
    }

    protected function calculateSalesAmount()
    {
        // Use unit price if unit_name is set, otherwise use default price
        if ($this->unit_name && $this->item_id) {
            $inventoryService = new InventoryService();
            $unitPrice = $inventoryService->getUnitPrice($this->item_id, $this->unit_name);
            if ($unitPrice) {
                $this->attributes['sales_amount'] = $this->sold_quantity * $unitPrice->selling_price;
                return;
            }
        }
        
        // Fallback to default price
        $this->attributes['sales_amount'] = $this->sold_quantity * ($this->price ?? 0);
    }

    // Add method to manually record sales with unit support
    public function recordSales($soldQuantity, $unitName = null)
    {
        $this->attributes['sold_quantity'] = max(0, $soldQuantity);
        $this->attributes['closing_stock'] = $this->total_stock - $this->attributes['sold_quantity'];
        
        if ($unitName) {
            $this->attributes['unit_name'] = $unitName;
        }
        
        $this->calculateSalesAmount();
    }

    // Add method to get current stock status
    public function getStockStatus()
    {
        return [
            'opening_stock' => $this->opening_stock,
            'ordered_stock' => $this->ordered_stock,
            'total_stock' => $this->total_stock,
            'sold_quantity' => $this->sold_quantity,
            'closing_stock' => $this->closing_stock,
            'sales_amount' => $this->sales_amount,
            'price' => $this->price,
            'unit_name' => $this->unit_name
        ];
    }

    /**
     * Get human-readable stock format
     */
    public function getHumanReadableStock()
    {
        if (!$this->item_id) {
            return $this->closing_stock . ' units';
        }

        $inventoryService = new InventoryService();
        $humanReadable = $inventoryService->convertToHumanReadable($this->item_id, $this->closing_stock);
        
        $parts = [];
        foreach ($humanReadable as $unit => $qty) {
            $parts[] = $qty . ' ' . $unit;
        }
        
        return implode(' + ', $parts);
    }
}

