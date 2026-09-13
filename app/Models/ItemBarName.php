<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemBarName extends Model
{
    protected $fillable = [
        'bar_id',
        'item_id',
        'name',
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
