<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Item extends Model
{
    protected $fillable = ['name', 'category', 'price', 'director_stock', 'is_castel', 'is_hidden', 'description', 'expiry_date', 'average_unit_cost', 'lifetime_quantity_purchased', 'lifetime_quantity_sold', 'lifetime_profit_estimate'];

    protected $casts = [
        'price' => 'decimal:2',
        'average_unit_cost' => 'decimal:2',
        'lifetime_profit_estimate' => 'decimal:2',
        'expiry_date' => 'date',
        'is_castel' => 'boolean',
    ];

    protected static function booted()
    {
        static::saving(function ($item) {
            if (!empty($item->name)) {
                $keywords = ['green', 'special', 'doppel', 'chill', 'kucheminerals', 'sapitwa', 'castel', 'pome', 'breezer', 'gin', 'brandy', 'carlsberg'];
                $nameLower = strtolower($item->name);
                foreach ($keywords as $k) {
                    if (str_contains($nameLower, $k)) {
                        $item->is_castel = true;
                        break;
                    }
                }
            }
        });
    }

    public function barItemPrices()
    {
        return $this->hasMany(BarItemPrice::class);
    }

    public function barNames(): HasMany
    {
        return $this->hasMany(ItemBarName::class);
    }

    /**
     * The name to display for this item at a given bar - a bar's own
     * override if it has renamed this item, otherwise the shared catalog
     * name used for warehouse/transfer matching and reporting.
     */
    public function displayNameForBar(?int $barId): string
    {
        if ($barId) {
            $override = $this->relationLoaded('barNames')
                ? $this->barNames->firstWhere('bar_id', $barId)
                : $this->barNames()->where('bar_id', $barId)->first();

            if ($override) {
                return $override->name;
            }
        }

        return $this->name;
    }

    public function stockEntryItems()
    {
        return $this->hasMany(StockEntryItem::class);
    }

    public function bars()
    {
        return $this->belongsToMany(Bar::class, 'bar_item_prices')
                    ->withPivot('price');
    }

    public function debts()
    {
        return $this->hasMany(Debt::class);
    }

    /**
     * Get all units for this item.
     */
    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    /**
     * Get the base unit for this item.
     */
    public function baseUnit(): HasOne
    {
        return $this->hasOne(ProductUnit::class)->where('is_base_unit', true);
    }

    /**
     * Get all unit prices for this item.
     */
    public function productUnitPrices(): HasMany
    {
        return $this->hasMany(ProductUnitPrice::class);
    }

    /**
     * Get all purchase history for this item.
     */
    public function purchaseHistory(): HasMany
    {
        return $this->hasMany(ProductPurchaseHistory::class)->orderBy('purchase_date', 'desc');
    }

    /**
     * Get all inventory ledger entries for this item.
     */
    public function ledger(): HasMany
    {
        return $this->hasMany(InventoryLedger::class)->orderBy('transaction_date', 'desc');
    }

    /**
     * Get ledger entries for a specific bar.
     */
    public function ledgerForBar($barId): HasMany
    {
        return $this->ledger()->where('bar_id', $barId);
    }

    /**
     * Get ledger entries for warehouse.
     */
    public function warehouseLedger(): HasMany
    {
        return $this->ledger()->whereNotNull('warehouse_stock_id');
    }

    /**
     * Add a purchase history record.
     */
    public function addPurchaseHistory(array $data): ProductPurchaseHistory
    {
        return $this->purchaseHistory()->create($data);
    }

    /**
     * Add a ledger entry.
     */
    public function addLedgerEntry(array $data): InventoryLedger
    {
        return $this->ledger()->create($data);
    }

    /**
     * Calculate weighted average unit cost.
     */
    public function calculateWeightedAverageCost(): float
    {
        $totalCost = $this->purchaseHistory()->sum('total_purchase_cost');
        $totalQuantity = $this->purchaseHistory()->sum(\DB::raw('quantity_purchased * (SELECT conversion_factor FROM product_units WHERE product_units.item_id = product_purchase_history.item_id AND product_units.is_base_unit = true LIMIT 1)'));
        
        if ($totalQuantity > 0) {
            return $totalCost / $totalQuantity;
        }
        
        return $this->average_unit_cost ?? 0;
    }

    /**
     * Update weighted average cost.
     */
    public function updateWeightedAverageCost(): void
    {
        $this->average_unit_cost = $this->calculateWeightedAverageCost();
        $this->save();
    }

    /**
     * Get current stock value.
     */
    public function getStockValueAttribute(): float
    {
        return $this->director_stock * ($this->average_unit_cost ?? $this->price);
    }

    /**
     * Get profit per base unit.
     */
    public function getProfitPerUnitAttribute(): float
    {
        $cost = $this->average_unit_cost ?? $this->price;
        $baseUnit = $this->baseUnit;
        
        if ($baseUnit) {
            $sellingPrice = $baseUnit->prices()->first()?->selling_price ?? $this->price;
            return $sellingPrice - $cost;
        }
        
        return $this->price - $cost;
    }

    /**
     * Get profit percentage.
     */
    public function getProfitPercentageAttribute(): float
    {
        $cost = $this->average_unit_cost ?? $this->price;
        
        if ($cost > 0) {
            return ($this->profit_per_unit / $cost) * 100;
        }
        
        return 0;
    }

    /**
     * Get current balance from ledger.
     */
    public function getCurrentBalance($barId = null): int
    {
        $query = $this->ledger();
        
        if ($barId) {
            $query->where('bar_id', $barId);
        } else {
            $query->whereNull('bar_id');
        }
        
        $latestEntry = $query->latest('transaction_date')->first();
        
        return $latestEntry ? $latestEntry->balance_after : 0;
    }

    /**
     * Record stock addition.
     */
    public function recordStockAddition(int $quantity, string $actionType, array $data = []): void
    {
        $currentBalance = $this->getCurrentBalance($data['bar_id'] ?? null);
        $newBalance = $currentBalance + $quantity;
        
        $this->addLedgerEntry(array_merge($data, [
            'action_type' => $actionType,
            'quantity' => $quantity,
            'balance_after' => $newBalance,
            'transaction_date' => now(),
        ]));
    }

    /**
     * Record stock deduction.
     */
    public function recordStockDeduction(int $quantity, string $actionType, array $data = []): void
    {
        $currentBalance = $this->getCurrentBalance($data['bar_id'] ?? null);
        $newBalance = $currentBalance - $quantity;
        
        $this->addLedgerEntry(array_merge($data, [
            'action_type' => $actionType,
            'quantity' => -$quantity,
            'balance_after' => $newBalance,
            'transaction_date' => now(),
        ]));
    }
}
