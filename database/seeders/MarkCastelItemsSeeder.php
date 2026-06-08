<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Item;

class MarkCastelItemsSeeder extends Seeder
{
    public function run()
    {
        $keywords = [
            'green', 'special', 'doppel', 'chill', 'kucheminerals', 'sapitwa', 'castel', 'pome', 'breezer', 'gin', 'brandy'
        ];

        $items = Item::all();
        foreach ($items as $item) {
            $name = strtolower($item->name);
            foreach ($keywords as $k) {
                if (str_contains($name, $k)) {
                    $item->is_castel = true;
                    $item->save();
                    break;
                }
            }
        }
    }
}
