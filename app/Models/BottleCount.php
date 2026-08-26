<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BottleCount extends Model
{
    protected $fillable = ['stock_entry_id', 'bar_id', 'item_id', 'counted', 'recorded_by', 'date'];

    protected $casts = [
        'date' => 'date',
    ];

    public function stockEntry()
    {
        return $this->belongsTo(Sale::class, 'stock_entry_id');
    }

    public function bar()
    {
        return $this->belongsTo(Bar::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

