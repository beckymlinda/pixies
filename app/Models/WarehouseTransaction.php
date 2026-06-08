<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseTransaction extends Model
{
    protected $fillable = [
        'warehouse_stock_id',
        'transaction_type',
        'quantity',
        'unit_cost',
        'total_cost',
        'supplier',
        'reference_number',
        'notes',
        'transaction_date',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    /**
     * Get the warehouse stock that owns the transaction
     */
    public function warehouseStock(): BelongsTo
    {
        return $this->belongsTo(WarehouseStock::class);
    }

    /**
     * Scope for purchase transactions
     */
    public function scopePurchases($query)
    {
        return $query->where('transaction_type', 'purchase');
    }

    /**
     * Scope for sale transactions
     */
    public function scopeSales($query)
    {
        return $query->where('transaction_type', 'sale');
    }

    /**
     * Scope for restock transactions
     */
    public function scopeRestocks($query)
    {
        return $query->where('transaction_type', 'restock');
    }

    /**
     * Check if transaction is an addition (positive quantity)
     */
    public function isAddition(): bool
    {
        return $this->quantity > 0;
    }

    /**
     * Check if transaction is a deduction (negative quantity)
     */
    public function isDeduction(): bool
    {
        return $this->quantity < 0;
    }
}
