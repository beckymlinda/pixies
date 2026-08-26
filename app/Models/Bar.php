<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bar extends Model
{
    public const EXCLUDED_NAMES = ['Pixies Njerwa'];

    public const LIQUOR_SHOP = 'Pixies Liquor Shop';

    public const BAR_B = 'Pixies Bar B';

    protected $fillable = ['name'];

    public function scopeListed($query)
    {
        return $query->whereNotIn('name', self::EXCLUDED_NAMES);
    }

    public function supportsWarehouseBaseUnit(string $baseUnit): bool
    {
        if ($baseUnit === 'Shot' && $this->name === self::LIQUOR_SHOP) {
            return false;
        }

        return true;
    }

    /**
     * Shot selling unit is only available for Bar B warehouse transfers.
     */
    public function allowsWarehouseTransferUnit(string $unitName): bool
    {
        if ($unitName === 'Shot' && $this->name !== self::BAR_B) {
            return false;
        }

        return true;
    }

    public function barItemPrices()
    {
        return $this->hasMany(BarItemPrice::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
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

