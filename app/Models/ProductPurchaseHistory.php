<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPurchaseHistory extends Model
{
    protected $fillable = [
        'item_id',
        'purchase_unit',
        'quantity_purchased',
        'total_purchase_cost',
        'calculated_base_unit_cost',
        'supplier',
        'reference_number',
        'notes',
        'purchase_date',
    ];

    protected $casts = [
        'total_purchase_cost' => 'decimal:2',
        'calculated_base_unit_cost' => 'decimal:2',
        'purchase_date' => 'date',
    ];

    /**
     * Get the item that owns the purchase history.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
