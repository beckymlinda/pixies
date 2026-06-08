<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnitPrice extends Model
{
    protected $fillable = [
        'item_id',
        'unit_name',
        'selling_price',
        'purchase_price',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
    ];

    /**
     * Get the item that owns the unit price.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the unit for this price.
     */
    public function unit()
    {
        return $this->hasOne(ProductUnit::class, 'item_id', 'item_id')
            ->where('unit_name', $this->unit_name);
    }

    /**
     * Calculate profit percentage.
     */
    public function getProfitPercentageAttribute(): float
    {
        if ($this->purchase_price == 0) {
            return 0;
        }
        return (($this->selling_price - $this->purchase_price) / $this->purchase_price) * 100;
    }
}
