<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseTransferRequestItem extends Model
{
    protected $fillable = [
        'warehouse_transfer_request_id',
        'warehouse_stock_id',
        'item_id',
        'quantity_requested',
        'quantity_approved',
        'unit_name',
        'conversion_factor',
        'notes',
    ];

    protected $casts = [
        'quantity_requested' => 'integer',
        'quantity_approved' => 'integer',
        'conversion_factor' => 'integer',
    ];

    /**
     * Get the transfer request.
     */
    public function transferRequest(): BelongsTo
    {
        return $this->belongsTo(WarehouseTransferRequest::class, 'warehouse_transfer_request_id');
    }

    /**
     * Get the warehouse stock.
     */
    public function warehouseStock(): BelongsTo
    {
        return $this->belongsTo(WarehouseStock::class);
    }

    /**
     * Get the item.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Check if item is fully approved.
     */
    public function isFullyApproved(): bool
    {
        return $this->quantity_approved >= $this->quantity_requested;
    }

    /**
     * Check if item is partially approved.
     */
    public function isPartiallyApproved(): bool
    {
        return $this->quantity_approved > 0 && $this->quantity_approved < $this->quantity_requested;
    }

    /**
     * Check if item is rejected.
     */
    public function isRejected(): bool
    {
        return $this->quantity_approved === 0;
    }
}
