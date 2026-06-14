<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseTransferRequest extends Model
{
    protected $fillable = [
        'bar_id',
        'requested_by',
        'approved_by',
        'status',
        'notes',
        'rejection_reason',
        'requested_at',
        'approved_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the bar that requested the transfer.
     */
    public function bar(): BelongsTo
    {
        return $this->belongsTo(Bar::class);
    }

    /**
     * Get the user who requested the transfer.
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the user who approved/rejected the transfer.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the items in this transfer request.
     */
    public function items(): HasMany
    {
        return $this->hasMany(WarehouseTransferRequestItem::class);
    }

    /**
     * Scope for pending requests.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for approved requests.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for partially approved requests.
     */
    public function scopePartiallyApproved($query)
    {
        return $query->where('status', 'partially_approved');
    }

    /**
     * Scope for rejected requests.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Check if request is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if request is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if request is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function resolveBarName(): string
    {
        if ($this->relationLoaded('bar') && $this->bar) {
            return $this->bar->name;
        }

        return Bar::find($this->bar_id)?->name ?? 'Unknown Bar';
    }
}
