<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarItemPrice extends Model
{
    protected $fillable = ['bar_id', 'item_id', 'price'];

    public function bar()
    {
        return $this->belongsTo(Bar::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
