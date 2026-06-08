<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderRequest extends Model
{
    protected $fillable = [
        'user_id',
        'bar_id',
        'status', // pending, approved, partially_approved, denied
        'notes',
        'seller_notified',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
        'seller_notified' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bar()
    {
        return $this->belongsTo(Bar::class);
    }

    public function items()
    {
        return $this->hasMany(OrderRequestItem::class);
    }
}
