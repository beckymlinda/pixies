<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseUnitBarPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_unit_id',
        'bar_id',
        'selling_price',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
    ];

    public function warehouseUnit(): BelongsTo
    {
        return $this->belongsTo(WarehouseUnit::class);
    }

    public function bar(): BelongsTo
    {
        return $this->belongsTo(Bar::class);
    }
}
