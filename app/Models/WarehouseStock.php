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

        $baseUnit = $this->relationLoaded('units')
            ? $this->units->firstWhere('is_base_unit', true)
            : $this->units()->where('is_base_unit', true)->first();

        if (! $baseUnit) {
            return (float) $this->selling_price;
        }

        $barPrice = $baseUnit->relationLoaded('barPrices')
            ? $baseUnit->barPrices->firstWhere('bar_id', $barId)
            : $baseUnit->barPrices()->where('bar_id', $barId)->first();

        return $barPrice
            ? (float) $barPrice->selling_price
            : (float) $this->selling_price;
    }

    /**
     * Markup percentage for a specific branch (selling vs cost).
     */
    public function getProfitPercentageForBranch(?int $barId): float
    {
        $cost = (float) ($this->average_unit_cost > 0 ? $this->average_unit_cost : $this->purchase_price);
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
     * Min/max base-unit selling prices across all configured branches.
     */
    public function getSellingPriceRange(): array
    {
        $baseUnit = $this->relationLoaded('units')
            ? $this->units->firstWhere('is_base_unit', true)
            : $this->units()->where('is_base_unit', true)->with('barPrices')->first();

        if (! $baseUnit) {
            $price = (float) $this->selling_price;

            return ['min' => $price, 'max' => $price, 'has_range' => false];
        }

        $prices = ($baseUnit->relationLoaded('barPrices') ? $baseUnit->barPrices : $baseUnit->barPrices()->get())
            ->pluck('selling_price')
            ->filter(fn ($p) => $p > 0)
            ->map(fn ($p) => (float) $p);

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
        $cost = $this->getUnitCost();

        return ($sellingPrice - $cost) * $this->quantity;
    }

    /**
     * Add a transaction record
     */
    public function addTransaction(array $data): \App\Models\WarehouseTransaction
    {
        $transaction = $this->transactions()->create($data);

        // Update lifetime quantities
        if ($transaction->isAddition()) {
            $this->lifetime_quantity_purchased += $transaction->quantity;
        } elseif ($transaction->isDeduction()) {
            $this->lifetime_quantity_sold += abs($transaction->quantity);
        }

        // Update weighted average cost
        $this->updateWeightedAverageCost();

        // Update lifetime profit estimate
        $this->lifetime_profit_estimate = $this->calculateLifetimeProfitEstimate();
        
        $this->save();

        return $transaction;
    }

    /**
     * Calculate lifetime profit estimate
     */
    protected function calculateLifetimeProfitEstimate(): float
    {
        $totalRevenue = 0;
        $totalCost = 0;

        foreach ($this->transactions as $transaction) {
            if ($transaction->transaction_type === 'sale') {
                $totalRevenue += abs($transaction->quantity) * $this->selling_price;
            } elseif ($transaction->isAddition()) {
                $totalCost += $transaction->total_cost;
            }
        }

        return $totalRevenue - $totalCost;
    }
}
