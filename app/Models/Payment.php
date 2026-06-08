<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['stock_entry_id', 'type', 'amount'];

    public function stockEntry()
    {
        return $this->belongsTo(DailyStockEntry::class);
    }
}
