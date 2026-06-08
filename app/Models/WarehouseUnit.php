<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_stock_id',
        'unit_name',
        'conversion_factor',
        'is_base_unit',
        'purchase_price',
    ];

    protected $casts = [
        'conversion_factor' => 'integer',
        'is_base_unit' => 'boolean',
        'purchase_price' => 'decimal:2',
    ];

    public function warehouseStock(): BelongsTo
    {
        return $this->belongsTo(WarehouseStock::class);
    }

    public function barPrices(): HasMany
    {
        return $this->hasMany(WarehouseUnitBarPrice::class);
    }

    /**
     * Calculate profit percentage for a specific bar
     */
    public function getProfitPercentageForBar($barId): float
    {
        $barPrice = $this->barPrices()->where('bar_id', $barId)->first();
        if (!$barPrice || $this->purchase_price == 0) {
            return 0;
        }
        return (($barPrice->selling_price - $this->purchase_price) / $this->purchase_price) * 100;
    }

    /**
     * Calculate potential profit for a specific bar
     */
    public function getPotentialProfitForBar($barId): float
    {
        $barPrice = $this->barPrices()->where('bar_id', $barId)->first();
        if (!$barPrice) {
            return 0;
        }
        return ($barPrice->selling_price - $this->purchase_price) * $this->conversion_factor;
    }
}
