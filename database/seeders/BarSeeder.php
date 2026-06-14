<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Bar;

class BarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bars = [
            ['name' => 'Pixies Bar B'],
            ['name' => 'Pixies Liquor Shop'],
        ];

        foreach ($bars as $bar) {
            Bar::create($bar);
        }
    }
}
