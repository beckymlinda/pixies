<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnitBarPrice extends Model
{
    protected $fillable = [
        'bar_id',
        'item_id',
        'unit_name',
        'selling_price',
        'purchase_price',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
    ];

    public function bar(): BelongsTo
    {
        return $this->belongsTo(Bar::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
