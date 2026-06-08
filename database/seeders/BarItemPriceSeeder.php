<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Bar;
use App\Models\Item;
use App\Models\BarItemPrice;

class BarItemPriceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bars = Bar::all();
        $items = Item::all();
        
        // Prices from paper sheet (in MWK - Malawian Kwacha)
        $prices = [
            'GREEN' => 3500,
            'SPECIAL' => 3500,
            'DOPPEL' => 3200,
            'CHILL' => 4000,
            'KUCHE' => 3200,
            'MINERALS' => 1300,
            'PETRODA SAPITWE' => 3200,
            'CASTEL' => 3200,
            'POME BREEZER' => 4000,
            'SMALL GIN' => 10500,
            'SMALL BRANDY' => 12000,
            'WATER' => 1000,
            'ICE' => 3000,
            'HEINEKEN BOTTLE' => 45000,
            'HEINEKEN TIN' => 47000,
            'LITE TIN/CASTLE LAGAR' => 45000,
            'LITE BOTTLE' => 45000,
            'AMSTEL' => 45000,
            'SAVANNA' => 45000,
            'HUNTERS DRY' => 45000,
            'HUNTERS GOLD' => 45000,
        ];

        // Create prices for each bar (same prices across all bars for now)
        foreach ($bars as $bar) {
            foreach ($items as $item) {
                $price = $prices[$item->name] ?? 1000; // Default price if not found
                
                BarItemPrice::create([
                    'bar_id' => $bar->id,
                    'item_id' => $item->id,
                    'price' => $price,
                ]);
            }
        }
    }
}
