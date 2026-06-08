<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderRequestItem extends Model
{
    protected $fillable = [
        'order_request_id',
        'item_id',
        'requested_quantity',
        'approved_quantity',
    ];

    public function orderRequest()
    {
        return $this->belongsTo(OrderRequest::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
