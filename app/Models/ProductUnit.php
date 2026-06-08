<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnit extends Model
{
    protected $fillable = [
        'item_id',
        'unit_name',
        'conversion_factor',
        'is_base_unit',
    ];

    protected $casts = [
        'conversion_factor' => 'integer',
        'is_base_unit' => 'boolean',
    ];

    /**
     * Get the item that owns the unit.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the price for this unit.
     */
    public function price()
    {
        return $this->hasOne(ProductUnitPrice::class, 'item_id', 'item_id')
            ->where('unit_name', $this->unit_name);
    }

    /**
     * Scope to get only base units.
     */
    public function scopeBaseUnits($query)
    {
        return $query->where('is_base_unit', true);
    }

    /**
     * Scope to get only non-base units.
     */
    public function scopeNonBaseUnits($query)
    {
        return $query->where('is_base_unit', false);
    }
}
