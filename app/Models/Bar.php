<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bar extends Model
{
    public const EXCLUDED_NAMES = ['Pixies Njerwa'];

    protected $fillable = ['name'];

    public function scopeListed($query)
    {
        return $query->whereNotIn('name', self::EXCLUDED_NAMES);
    }

    public function barItemPrices()
    {
        return $this->hasMany(BarItemPrice::class);
    }

    public function dailyStockEntries()
    {
        return $this->hasMany(DailyStockEntry::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function items()
    {
        return $this->belongsToMany(Item::class, 'bar_item_prices')
                    ->withPivot('price');
    }
}
