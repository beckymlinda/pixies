<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Item;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            // Beers (from paper sheet)
            ['name' => 'GREEN', 'category' => 'beer', 'price' => 3500],
            ['name' => 'SPECIAL', 'category' => 'beer', 'price' => 3500],
            ['name' => 'DOPPEL', 'category' => 'beer', 'price' => 3200],
            ['name' => 'CHILL', 'category' => 'beer', 'price' => 4000],
            ['name' => 'KUCHE', 'category' => 'beer', 'price' => 3200],
            ['name' => 'CASTEL', 'category' => 'beer', 'price' => 3200],
            ['name' => 'HEINEKEN BOTTLE', 'category' => 'beer', 'price' => 45000],
            ['name' => 'HEINEKEN TIN', 'category' => 'beer', 'price' => 47000],
            ['name' => 'LITE TIN/CASTLE LAGAR', 'category' => 'beer', 'price' => 45000],
            ['name' => 'LITE BOTTLE', 'category' => 'beer', 'price' => 45000],
            ['name' => 'AMSTEL', 'category' => 'beer', 'price' => 45000],
            ['name' => 'SAVANNA', 'category' => 'beer', 'price' => 45000],
            ['name' => 'HUNTERS DRY', 'category' => 'beer', 'price' => 45000],
            ['name' => 'HUNTERS GOLD', 'category' => 'beer', 'price' => 45000],
            
            // Minerals/Sodas
            ['name' => 'MINERALS', 'category' => 'soda', 'price' => 1300],
            ['name' => 'POME BREEZER', 'category' => 'soda', 'price' => 4000],
            ['name' => 'WATER', 'category' => 'soda', 'price' => 1000],
            
            // Spirits
            ['name' => 'SMALL GIN', 'category' => 'spirit', 'price' => 10500],
            ['name' => 'SMALL BRANDY', 'category' => 'spirit', 'price' => 12000],
            
            // Other
            ['name' => 'ICE', 'category' => 'other', 'price' => 3000],
            ['name' => 'PETRODA SAPITWE', 'category' => 'other', 'price' => 3200],
        ];

        foreach ($items as $item) {
            Item::create($item);
        }
    }
}
