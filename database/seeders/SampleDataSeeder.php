<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DailyStockEntry;
use App\Models\StockEntryItem;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Debt;
use App\Models\User;
use App\Models\Bar;
use App\Models\Item;
use App\Models\BarItemPrice;
use Carbon\Carbon;

class SampleDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $seller = User::where('role', 'seller')->first();
        $bars = Bar::all();
        $items = Item::all();

        // Create sample stock entries for today and yesterday
        for ($day = 0; $day < 2; $day++) {
            $date = Carbon::now()->subDays($day);
            
            foreach ($bars as $bar) {
                // Create daily stock entry
                $stockEntry = DailyStockEntry::create([
                    'bar_id' => $bar->id,
                    'user_id' => $seller->id,
                    'date' => $date,
                ]);

                // Create sample stock items
                foreach ($items->take(10) as $index => $item) {
                    $openingStock = rand(10, 50);
                    $orderedStock = rand(0, 20);
                    $closingStock = rand(5, 40);
                    $totalStock = $openingStock + $orderedStock;
                    $soldQuantity = $totalStock - $closingStock;
                    
                    $price = BarItemPrice::where('bar_id', $bar->id)
                                        ->where('item_id', $item->id)
                                        ->first()?->price ?? 1000;
                    
                    $salesAmount = $soldQuantity * $price;

                    StockEntryItem::create([
                        'stock_entry_id' => $stockEntry->id,
                        'item_id' => $item->id,
                        'opening_stock' => $openingStock,
                        'ordered_stock' => $orderedStock,
                        'total_stock' => $totalStock,
                        'closing_stock' => $closingStock,
                        'sold_quantity' => $soldQuantity,
                        'sales_amount' => $salesAmount,
                    ]);
                }

                // Create sample expenses
                $expenseTypes = ['lunch', 'taxi', 'damage', 'debt', 'other'];
                for ($i = 0; $i < rand(2, 5); $i++) {
                    Expense::create([
                        'stock_entry_id' => $stockEntry->id,
                        'type' => $expenseTypes[array_rand($expenseTypes)],
                        'amount' => rand(1000, 15000),
                        'description' => 'Sample expense ' . ($i + 1),
                    ]);
                }

                // Create sample electronic payments
                if (rand(0, 1)) {
                    Payment::create([
                        'stock_entry_id' => $stockEntry->id,
                        'type' => 'mpamba',
                        'amount' => rand(5000, 50000),
                    ]);
                }
                
                if (rand(0, 1)) {
                    Payment::create([
                        'stock_entry_id' => $stockEntry->id,
                        'type' => 'airtel_money',
                        'amount' => rand(5000, 50000),
                    ]);
                }

                // Create sample debts
                if (rand(0, 1)) {
                    Debt::create([
                        'stock_entry_id' => $stockEntry->id,
                        'item_id' => $items->first()->id,
                        'amount' => rand(2000, 20000),
                        'customer_name' => 'Customer ' . ($bar->id),
                        'seller_id' => $seller->id,
                    ]);
                }
            }
        }

        $this->command->info('Sample data created successfully!');
    }
}
