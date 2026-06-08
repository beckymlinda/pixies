<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLedger extends Model
{
    protected $fillable = [
        'item_id',
        'bar_id',
        'warehouse_stock_id',
        'action_type',
        'quantity',
        'unit_cost',
        'total_cost',
        'balance_after',
        'reference_type',
        'reference_id',
        'notes',
        'transaction_date',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'balance_after' => 'integer',
        'transaction_date' => 'datetime',
    ];

    /**
     * Get the item that owns the ledger entry.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the bar that owns the ledger entry.
     */
    public function bar(): BelongsTo
    {
        return $this->belongsTo(Bar::class);
    }

    /**
     * Get the warehouse stock that owns the ledger entry.
     */
    public function warehouseStock(): BelongsTo
    {
        return $this->belongsTo(WarehouseStock::class);
    }

    /**
     * Scope for purchase actions.
     */
    public function scopePurchases($query)
    {
        return $query->where('action_type', 'purchase');
    }

    /**
     * Scope for sale actions.
     */
    public function scopeSales($query)
    {
        return $query->where('action_type', 'sale');
    }

    /**
     * Scope for transfer in actions.
     */
    public function scopeTransferIn($query)
    {
        return $query->where('action_type', 'transfer_in');
    }

    /**
     * Scope for transfer out actions.
     */
    public function scopeTransferOut($query)
    {
        return $query->where('action_type', 'transfer_out');
    }

    /**
     * Scope for adjustments.
     */
    public function scopeAdjustments($query)
    {
        return $query->where('action_type', 'adjustment');
    }

    /**
     * Check if this is an addition.
     */
    public function isAddition(): bool
    {
        return $this->quantity > 0;
    }

    /**
     * Check if this is a deduction.
     */
    public function isDeduction(): bool
    {
        return $this->quantity < 0;
    }
}
