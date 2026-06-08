<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = ['stock_entry_id', 'type', 'amount', 'description', 'date', 'user_id'];

    public function stockEntry()
    {
        return $this->belongsTo(DailyStockEntry::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
