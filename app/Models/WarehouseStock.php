<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehouseStock extends Model
{
    use HasFactory;

    protected $table = 'warehouse_stocks';

    protected $fillable = [
        'item_name',
        'quantity',
        'alert_quantity',
        'purchase_price',
        'selling_price',
        'expiry_date',
        'notes',
        'purchase_unit',
        'quantity_purchased',
        'total_purchase_cost',
        'calculated_base_unit_cost',
        'lifetime_quantity_purchased',
        'lifetime_quantity_sold',
        'lifetime_profit_estimate',
        'average_unit_cost',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'total_purchase_cost' => 'decimal:2',
        'calculated_base_unit_cost' => 'decimal:2',
        'lifetime_profit_estimate' => 'decimal:2',
        'average_unit_cost' => 'decimal:2',
    ];

    /**
     * Calculate profit percentage
     */
    public function getProfitPercentageAttribute(): float
    {
        if ($this->purchase_price == 0) {
            return 0;
        }
        return (($this->selling_price - $this->purchase_price) / $this->purchase_price) * 100;
    }

    /**
     * Calculate total purchase value
     */
    public function getTotalPurchaseValueAttribute(): float
    {
        return $this->quantity * $this->purchase_price;
    }

    /**
     * Calculate total selling value
     */
    public function getTotalSellingValueAttribute(): float
    {
        return $this->quantity * $this->selling_price;
    }

    /**
     * Check if item is expiring soon (within 7 days)
     */
    public function isExpiringsoon(): bool
    {
        if (!$this->expiry_date) {
            return false;
        }
        return $this->expiry_date->diffInDays(now()) <= 7 && $this->expiry_date->isPast() === false;
    }

    /**
     * Check if item has expired
     */
    public function isExpired(): bool
    {
        if (!$this->expiry_date) {
            return false;
        }
        return $this->expiry_date->isPast();
    }

    /**
     * Get days until expiry
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expiry_date) {
            return null;
        }
        return max(0, $this->expiry_date->diffInDays(now()));
    }

    /**
     * Check if stock is low
     */
    public function isLowStock(): bool
    {
        return $this->quantity <= $this->alert_quantity;
    }

    /**
     * Get the units for this warehouse stock
     */
    public function units()
    {
        return $this->hasMany(\App\Models\WarehouseUnit::class);
    }

    /**
     * Get the transactions for this warehouse stock
     */
    public function transactions()
    {
        return $this->hasMany(\App\Models\WarehouseTransaction::class)->orderBy('transaction_date', 'desc');
    }

    /**
     * Calculate weighted average cost
     */
    public function calculateWeightedAverageCost(): float
    {
        $purchaseTransactions = $this->transactions()->purchases()->get();
        
        if ($purchaseTransactions->isEmpty()) {
            return $this->purchase_price;
        }

        $totalCost = 0;
        $totalQuantity = 0;

        foreach ($purchaseTransactions as $transaction) {
            $totalCost += $transaction->total_cost;
            $totalQuantity += $transaction->quantity;
        }

        return $totalQuantity > 0 ? $totalCost / $totalQuantity : $this->purchase_price;
    }

    /**
     * Update weighted average cost
     */
    public function updateWeightedAverageCost(): void
    {
        $this->average_unit_cost = $this->calculateWeightedAverageCost();
        $this->save();
    }

    /**
     * Get base unit selling price for a specific branch.
     */
    public function getSellingPriceForBranch(?int $barId): ?float
    {
        if (! $barId) {
            return null;
        }

        $bar = Bar::find($barId);
        $baseUnit = $this->relationLoaded('units')
            ? $this->units->firstWhere('is_base_unit', true)
            : $this->units()->where('is_base_unit', true)->first();

        if ($baseUnit && $baseUnit->unit_name === 'Shot' && $bar) {
            $useBottlePrice = $bar->name === Bar::LIQUOR_SHOP || ! $bar->allowsWarehouseTransferUnit('Shot');
            if ($useBottlePrice) {
                $bottleUnit = $this->getBottleSellingUnit();
                if ($bottleUnit) {
                    $bottlePrice = $bottleUnit->relationLoaded('barPrices')
                        ? $bottleUnit->barPrices->firstWhere('bar_id', $barId)
                        : $bottleUnit->barPrices()->where('bar_id', $barId)->first();

                    if ($bottlePrice && (float) $bottlePrice->selling_price > 0) {
                        return (float) $bottlePrice->selling_price;
                    }
                }
            }
        }

        if (! $baseUnit) {
            return (float) $this->selling_price;
        }

        $barPrice = $baseUnit->relationLoaded('barPrices')
            ? $baseUnit->barPrices->firstWhere('bar_id', $barId)
            : $baseUnit->barPrices()->where('bar_id', $barId)->first();

        return $barPrice && (float) $barPrice->selling_price > 0
            ? (float) $barPrice->selling_price
            : ((float) $this->selling_price > 0 ? (float) $this->selling_price : null);
    }

    /**
     * Bottle selling unit for shot-based warehouse items.
     */
    public function getBottleSellingUnit(): ?\App\Models\WarehouseUnit
    {
        $units = $this->relationLoaded('units')
            ? $this->units
            : $this->units()->get();

        $bottleUnit = $units->first(fn ($unit) => $unit->unit_name === 'Bottle' && ! $unit->is_base_unit);

        if (! $bottleUnit && $this->purchase_unit === 'Bottle') {
            $bottleUnit = $units->firstWhere('unit_name', 'Bottle');
        }

        return $bottleUnit;
    }

    /**
     * Markup percentage for a specific branch (selling vs cost).
     */
    public function getProfitPercentageForBranch(?int $barId): float
    {
        $cost = $this->getUnitCostForBranch($barId);
        if ($cost <= 0 || ! $barId) {
            return 0;
        }

        $sellingPrice = $this->getSellingPriceForBranch($barId);
        if (! $sellingPrice) {
            return 0;
        }

        return (($sellingPrice - $cost) / $cost) * 100;
    }

    /**
     * Min/max effective selling prices across all branches (shot or bottle per bar).
     */
    public function getSellingPriceRange(): array
    {
        $prices = collect($this->getBranchSellingPricesForDisplay())
            ->pluck('price')
            ->filter(fn ($p) => $p > 0);

        if ($prices->isEmpty()) {
            $price = (float) $this->selling_price;

            return ['min' => $price, 'max' => $price, 'has_range' => false];
        }

        return [
            'min' => $prices->min(),
            'max' => $prices->max(),
            'has_range' => $prices->min() !== $prices->max(),
        ];
    }

    /**
     * Per-branch selling price with the unit each bar uses (shot vs bottle).
     */
    public function getBranchSellingPricesForDisplay(): array
    {
        $bars = Bar::listed()->orderBy('name')->get();
        $result = [];

        foreach ($bars as $bar) {
            $price = $this->getSellingPriceForBranch($bar->id);
            if ($price === null || $price <= 0) {
                continue;
            }

            $result[] = [
                'bar_id' => $bar->id,
                'bar_name' => $bar->name,
                'unit' => $this->getSellingUnitLabelForBranch($bar),
                'price' => $price,
            ];
        }

        return $result;
    }

    public function getSellingUnitLabelForBranch(Bar $bar): string
    {
        $baseUnit = $this->relationLoaded('units')
            ? $this->units->firstWhere('is_base_unit', true)
            : $this->units()->where('is_base_unit', true)->first();

        if ($baseUnit && $baseUnit->unit_name === 'Shot' && ! $bar->allowsWarehouseTransferUnit('Shot')) {
            return 'Bottle';
        }

        return $baseUnit?->unit_name ?? 'Bottle';
    }

    public function getUnitCostForBranch(?int $barId): float
    {
        $bar = $barId ? Bar::find($barId) : null;
        $baseUnit = $this->relationLoaded('units')
            ? $this->units->firstWhere('is_base_unit', true)
            : $this->units()->where('is_base_unit', true)->first();

        if ($baseUnit && $baseUnit->unit_name === 'Shot' && $bar && ! $bar->allowsWarehouseTransferUnit('Shot')) {
            $bottleUnit = $this->getBottleSellingUnit();
            if ($bottleUnit && (float) $bottleUnit->purchase_price > 0) {
                return (float) $bottleUnit->purchase_price;
            }

            return $this->getUnitCost() * $this->getShotsPerBottle();
        }

        return $this->getUnitCost();
    }

    /**
     * Unit cost used for profit calculations.
     */
    public function getUnitCost(): float
    {
        return (float) ($this->average_unit_cost > 0 ? $this->average_unit_cost : $this->purchase_price);
    }

    /**
     * Get current stock value for a specific branch.
     */
    public function getStockValueForBranch($barId): float
    {
        $sellingPrice = $this->getSellingPriceForBranch($barId) ?? $this->selling_price;

        return $this->quantity * $sellingPrice;
    }

    /**
     * Get item status
     */
    public function getItemStatusAttribute(): string
    {
        if ($this->quantity == 0) {
            return 'Out of Stock';
        }

        if ($this->isLowStock()) {
            return 'Low Stock';
        }

        if ($this->isExpiringsoon()) {
            return 'Expiring Soon';
        }

        if ($this->isExpired()) {
            return 'Expired';
        }

        return 'Good';
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->item_status) {
            'Out of Stock' => 'bg-danger',
            'Low Stock' => 'bg-warning text-dark',
            'Expiring Soon' => 'bg-warning text-dark',
            'Expired' => 'bg-secondary',
            default => 'bg-success',
        };
    }

    /**
     * Calculate potential profit for a specific bar
     */
    public function getPotentialProfitForBar($barId): float
    {
        $sellingPrice = $this->getSellingPriceForBranch($barId) ?? $this->selling_price;
        $cost = $this->getUnitCostForBranch($barId);

        return ($sellingPrice - $cost) * $this->quantity;
    }

    /**
     * Shots per bottle for liquor tracked in shot base units.
     */
    public function getShotsPerBottle(): int
    {
        $baseUnit = $this->relationLoaded('units')
            ? $this->units->firstWhere('is_base_unit', true)
            : $this->units()->where('is_base_unit', true)->first();

        if (! $baseUnit || $baseUnit->unit_name !== 'Shot') {
            return 1;
        }

        $bottleUnit = $this->relationLoaded('units')
            ? $this->units->first(fn ($u) => $u->unit_name === 'Bottle' && ! $u->is_base_unit)
            : $this->units()->where('unit_name', 'Bottle')->where('is_base_unit', false)->first();

        if (! $bottleUnit && $this->purchase_unit === 'Bottle') {
            $bottleUnit = $this->relationLoaded('units')
                ? $this->units->firstWhere('unit_name', 'Bottle')
                : $this->units()->where('unit_name', 'Bottle')->first();
        }

        return max(1, (int) ($bottleUnit?->conversion_factor ?? 25));
    }

    /**
     * On-hand stock expressed as whole bottles for display.
     */
    public function getStockInBottles(): int
    {
        $baseUnit = $this->relationLoaded('units')
            ? $this->units->firstWhere('is_base_unit', true)
            : $this->units()->where('is_base_unit', true)->first();

        if ($baseUnit && $baseUnit->unit_name === 'Shot') {
            return (int) floor($this->quantity / $this->getShotsPerBottle());
        }

        return (int) $this->quantity;
    }

    /**
     * Warehouse availability for stock requests, adjusted per destination bar.
     */
    public function getRequestAvailabilityForBar(Bar $bar): array
    {
        $baseUnit = $this->relationLoaded('units')
            ? $this->units->firstWhere('is_base_unit', true)
            : $this->units()->where('is_base_unit', true)->first();

        $baseQty = (float) $this->quantity;

        if ($baseUnit && $baseUnit->unit_name === 'Shot' && $bar->name === Bar::LIQUOR_SHOP) {
            $bottles = $this->getStockInBottles();

            return [
                'available_quantity' => $bottles,
                'available_quantity_base' => $baseQty,
                'stock_unit' => 'Bottles',
                'is_out_of_stock' => $bottles <= 0,
            ];
        }

        if ($baseUnit && $baseUnit->unit_name === 'Shot' && $bar->name === Bar::BAR_B) {
            return [
                'available_quantity' => (int) floor($baseQty),
                'available_quantity_base' => $baseQty,
                'stock_unit' => 'Shots',
                'is_out_of_stock' => $baseQty <= 0,
            ];
        }

        $unitLabel = $baseUnit?->unit_name ?? 'units';

        return [
            'available_quantity' => (int) floor($baseQty),
            'available_quantity_base' => $baseQty,
            'stock_unit' => $unitLabel,
            'is_out_of_stock' => $baseQty <= 0,
        ];
    }

    /**
     * Conversion factor for the configured purchase unit (base units per purchase unit).
     */
    public function resolvePurchaseConversionFactor(): int
    {
        if (! $this->purchase_unit) {
            return 1;
        }

        $baseUnit = $this->relationLoaded('units')
            ? $this->units->firstWhere('is_base_unit', true)
            : $this->units()->where('is_base_unit', true)->first();

        if ($baseUnit && $this->purchase_unit === $baseUnit->unit_name) {
            return 1;
        }

        $purchaseUnitRecord = $this->relationLoaded('units')
            ? $this->units->where('is_base_unit', false)->firstWhere('unit_name', $this->purchase_unit)
            : $this->units()->where('is_base_unit', false)->where('unit_name', $this->purchase_unit)->first();

        return max(1, (int) ($purchaseUnitRecord?->conversion_factor ?? 1));
    }

    /**
     * Add a transaction record
     */
    public function addTransaction(array $data): \App\Models\WarehouseTransaction
    {
        $transaction = $this->transactions()->create($data);

        $this->updateWeightedAverageCost();
        $this->syncLifetimeMetricsFromLedger();

        return $transaction;
    }

    /**
     * Recompute lifetime quantities and profit from the inventory ledger only.
     */
    public function syncLifetimeMetricsFromLedger(): void
    {
        if (! $this->relationLoaded('transactions')) {
            $this->load('transactions');
        }

        $this->lifetime_quantity_purchased = $this->sumLedgerPurchasedQuantity();
        $this->lifetime_quantity_sold = $this->sumLedgerIssuedQuantity();
        $this->lifetime_profit_estimate = $this->getNetInventoryPosition();
        $this->save();
    }

    public function sumLedgerPurchasedQuantity(): int
    {
        if (! $this->relationLoaded('transactions')) {
            $this->load('transactions');
        }

        return (int) $this->transactions
            ->filter(fn ($transaction) => in_array($transaction->transaction_type, ['purchase', 'restock'], true) && $transaction->quantity > 0)
            ->sum('quantity');
    }

    public function sumLedgerIssuedQuantity(): int
    {
        if (! $this->relationLoaded('transactions')) {
            $this->load('transactions');
        }

        $issued = 0;

        foreach ($this->transactions as $transaction) {
            if ($transaction->transaction_type === 'sale' && $transaction->quantity < 0) {
                $issued += abs($transaction->quantity);
            }

            if ($transaction->transaction_type === 'transfer') {
                if ($transaction->quantity < 0) {
                    $issued += abs($transaction->quantity);
                } else {
                    $issued = max(0, $issued - $transaction->quantity);
                }
            }
        }

        return (int) $issued;
    }

    /**
     * Profit from stock that has left the warehouse (sales only).
     */
    public function getRealizedProfit(): float
    {
        if (! $this->relationLoaded('transactions')) {
            $this->load('transactions');
        }

        $revenue = 0.0;
        $cogs = 0.0;

        foreach ($this->transactions as $transaction) {
            if ($transaction->transaction_type !== 'sale' || $transaction->quantity >= 0) {
                continue;
            }

            $quantity = abs($transaction->quantity);
            $cogs += (float) ($transaction->total_cost ?? ($quantity * (float) $transaction->unit_cost));
            $revenue += $quantity * (float) $this->selling_price;
        }

        return $revenue - $cogs;
    }

    /**
     * Markup potential on unsold stock still in the warehouse.
     */
    public function getUnrealizedProfit(): float
    {
        $unitCost = $this->getUnitCost();
        $stockValue = $this->quantity * (float) $this->selling_price;
        $costValue = $this->quantity * $unitCost;

        return $stockValue - $costValue;
    }

    public function getNetInventoryPosition(): float
    {
        return $this->getRealizedProfit() + $this->getUnrealizedProfit();
    }
}
