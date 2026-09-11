<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamagedGood extends Model
{
    protected $fillable = [
        'date',
        'description',
        'amount',
        'photo_path',
        'from_balance',
        'bar_id',
        'item_id',
        'quantity',
        'user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'quantity' => 'decimal:2',
        'from_balance' => 'boolean',
    ];

    public function photoUrl(): ?string
    {
        // Serve through a route instead of asset('storage/...') so the
        // image works even when the public/storage symlink is missing
        // or blocked (common on cPanel shared hosting, returns 403).
        return $this->photo_path ? route('damaged-goods.photo', $this) : null;
    }

    public function bar(): BelongsTo
    {
        return $this->belongsTo(Bar::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
