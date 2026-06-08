<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Bar;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bars = Bar::all();

        // Seller for Pixies Njerwa
        User::firstOrCreate(
            ['email' => 'seller@pixies.com'],
            [
                'name' => 'John Seller',
                'password' => bcrypt('password'),
                'role' => 'seller',
                'bar_id' => $bars[0]->id,
            ]
        );

        // Seller for Pixies Bar B
        if (isset($bars[1])) {
            User::firstOrCreate(
                ['email' => 'seller2@pixies.com'],
                [
                    'name' => 'Alice Seller',
                    'password' => bcrypt('password'),
                    'role' => 'seller',
                    'bar_id' => $bars[1]->id,
                ]
            );
        }

        // Seller for Pixies Liquor Shop
        if (isset($bars[2])) {
            User::firstOrCreate(
                ['email' => 'seller3@pixies.com'],
                [
                    'name' => 'Mike Seller',
                    'password' => bcrypt('password'),
                    'role' => 'seller',
                    'bar_id' => $bars[2]->id,
                ]
            );
        }

        // Manager (no specific bar)
        User::firstOrCreate(
            ['email' => 'manager@pixies.com'],
            [
                'name' => 'Jane Manager',
                'password' => bcrypt('password'),
                'role' => 'manager',
                'bar_id' => null,
            ]
        );

        // Director (no specific bar)
        User::firstOrCreate(
            ['email' => 'director@pixies.com'],
            [
                'name' => 'Bob Director',
                'password' => bcrypt('password'),
                'role' => 'director',
                'bar_id' => null,
            ]
        );
    }
}
