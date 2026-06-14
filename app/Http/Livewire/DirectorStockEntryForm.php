<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Bar;
use App\Models\Item;
use App\Models\BarItemPrice;
use App\Models\DailyStockEntry;
use App\Models\StockEntryItem;
use App\Models\Expense;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DirectorStockEntryForm extends Component
{
    public $date;
    public $bar;
    public $stockItems = [];
    public $expenses = [];
    public $payments = [
        'mpamba' => 0,
        'airtel_money' => 0,
        'bank' => 0,
        'pos' => 0,
    ];

    protected $rules = [
        'date' => 'required|date',
        'stockItems.*.opening_stock' => 'required|integer|min:0',
        'stockItems.*.purchase_price' => 'nullable|numeric|min:0',
        'stockItems.*.expiry_date' => 'nullable|date',
        'stockItems.*.ordered_stock' => 'required|integer|min:0',
        'stockItems.*.sold_quantity' => 'required|integer|min:0',
        'expenses.*.type' => 'required|in:lunch,taxi,damage,debt,other',
        'expenses.*.amount' => 'required|numeric|min:0',
        'payments.*' => 'required|numeric|min:0',
    ];

    public function mount()
    {
        $user = Auth::user();
        
        // Directors can select any bar or fallback to the first bar if not assigned.
        $this->bar = $user->bar ?: Bar::listed()->orderBy('name')->first();
        $this->date = now()->format('Y-m-d');

        if (! $this->bar) {
            abort(404, 'No bar configured for director stock entry.');
        }

        // Get all items (director can see all items, not seller-specific)
        $items = Item::orderBy('category')->orderBy('name')->get();
        
        // Check if there's existing stock entry for today
        $existingStockEntry = DailyStockEntry::where('date', $this->date)
            ->where('bar_id', $this->bar->id)
            ->with(['stockEntryItems'])
            ->first();

        // Initialize stock items for director (All items available)
        foreach ($items as $item) {
            $price = BarItemPrice::where('bar_id', $this->bar->id)
                                ->where('item_id', $item->id)
                                ->first()?->price ?? 0;

            $existingItem = $existingStockEntry ? 
                $existingStockEntry->stockEntryItems->where('item_id', $item->id)->first() : null;

            $this->stockItems[] = [
                'item_id' => $item->id,
                'item_name' => $item->name,
                'category' => $item->category,
                'price' => $price,
                'purchase_price' => 0,
                'expiry_date' => '',
                'opening_stock' => $existingItem ? $existingItem->opening_stock : 0,
                'ordered_stock' => $existingItem ? $existingItem->ordered_stock : 0,
                'total_stock' => $existingItem ? $existingItem->total_stock : 0,
                'closing_stock' => $existingItem ? $existingItem->closing_stock : 0,
                'sold_quantity' => $existingItem ? $existingItem->sold_quantity : 0,
                'sales_amount' => $existingItem ? $existingItem->sales_amount : 0,
                'can_edit_opening' => true, // Director can edit opening stock
            ];
        }

        // Load existing expenses if any
        if ($existingStockEntry) {
            $this->expenses = $existingStockEntry->expenses->map(function ($expense) {
                return [
                    'type' => $expense->type,
                    'amount' => $expense->amount,
                    'description' => $expense->description ?? '',
                ];
            })->toArray() ?: [];
        }

        // Load existing payments if any
        if ($existingStockEntry) {
            $existingPayments = $existingStockEntry->payments->keyBy('type');
            foreach ($this->payments as $type => &$amount) {
                $amount = $existingPayments->get($type)?->amount ?? 0;
            }
        }

        // Initialize with one empty expense if none exist
        if (empty($this->expenses)) {
            $this->expenses[] = [
                'type' => 'lunch',
                'amount' => 0,
                'description' => '',
            ];
        }
        
        // Initialize closing stock for all items
        $this->initializeClosingStock();
    }
    
    public function initializeClosingStock()
    {
        foreach ($this->stockItems as $index => $item) {
            $this->recalculateItemStock($index);
        }
    }
    
    public function recalculateItemStock($index)
    {
        // Calculate total stock (director can edit opening stock)
        $this->stockItems[$index]['total_stock'] = 
            $this->stockItems[$index]['opening_stock'] + 
            $this->stockItems[$index]['ordered_stock'];
            
        // Ensure sold quantity doesn't exceed total stock
        if ($this->stockItems[$index]['sold_quantity'] > $this->stockItems[$index]['total_stock']) {
            $this->stockItems[$index]['sold_quantity'] = $this->stockItems[$index]['total_stock'];
        }
            
        // Auto-calculate closing stock based on sold quantity
        $this->stockItems[$index]['closing_stock'] = 
            $this->stockItems[$index]['total_stock'] - 
            $this->stockItems[$index]['sold_quantity'];
            
        // Recalculate sales amount
        $this->stockItems[$index]['sales_amount'] = 
            $this->stockItems[$index]['sold_quantity'] * 
            $this->stockItems[$index]['price'];
    }
    
    public function getComputedClosingStock($index)
    {
        $totalStock = $this->stockItems[$index]['opening_stock'] + $this->stockItems[$index]['ordered_stock'];
        $soldQuantity = min($this->stockItems[$index]['sold_quantity'], $totalStock);
        return $totalStock - $soldQuantity;
    }
    
    public function updatedStockItems($value, $key)
    {
        $keys = explode('.', $key);
        $index = $keys[1];
        $field = $keys[2];

        if ($field === 'opening_stock' || $field === 'ordered_stock' || $field === 'sold_quantity') {
            // Use the unified recalculation method
            $this->recalculateItemStock($index);
        }
    }

    public function addExpense()
    {
        $this->expenses[] = [
            'type' => 'lunch',
            'amount' => 0,
            'description' => '',
        ];
    }

    public function removeExpense($index)
    {
        unset($this->expenses[$index]);
        $this->expenses = array_values($this->expenses);
    }

    public function getTotalSales()
    {
        return collect($this->stockItems)->sum('sales_amount');
    }

    public function getTotalExpenses()
    {
        return collect($this->expenses)->sum('amount');
    }

    public function getTotalElectronicPayments()
    {
        return collect($this->payments)->sum();
    }

    public function getCashSales()
    {
        return $this->getTotalSales() - $this->getTotalElectronicPayments();
    }

    public function save()
    {
        $this->validate();

        // Additional validation
        foreach ($this->stockItems as $item) {
            if ($item['closing_stock'] > $item['total_stock']) {
                $this->addError('stock_items', 'Closing stock cannot be greater than total stock for ' . $item['item_name']);
                return;
            }
        }

        DB::beginTransaction();
        
        try {
            $user = Auth::user();

            // Check if stock entry already exists for today
            $stockEntry = DailyStockEntry::where('date', $this->date)
                ->where('bar_id', $this->bar->id)
                ->first();

            if ($stockEntry) {
                // Update existing stock entry
                $stockEntry->updated_at = now();
                $stockEntry->save();
            } else {
                // Create new daily stock entry
                $stockEntry = DailyStockEntry::create([
                    'bar_id' => $this->bar->id,
                    'user_id' => $user->id,
                    'date' => $this->date,
                ]);
            }

            // Update/accumulate stock entry items
            foreach ($this->stockItems as $itemData) {
                // Find existing item for this stock entry
                $existingItem = $stockEntry->stockEntryItems()
                    ->where('item_id', $itemData['item_id'])
                    ->first();

                if ($existingItem) {
                    // Update existing item
                    $existingItem->opening_stock = $itemData['opening_stock'];
                    $existingItem->sold_quantity += $itemData['sold_quantity'];
                    $existingItem->sales_amount += $itemData['sales_amount'];
                    $existingItem->ordered_stock += $itemData['ordered_stock'];
                    $existingItem->total_stock = $existingItem->opening_stock + $existingItem->ordered_stock;
                    $existingItem->closing_stock = $existingItem->total_stock - $existingItem->sold_quantity;
                    $existingItem->purchase_price = $itemData['purchase_price'] ?? $existingItem->purchase_price;
                    $existingItem->expiry_date = !empty($itemData['expiry_date']) ? $itemData['expiry_date'] : $existingItem->expiry_date;
                    $existingItem->save();
                } else {
                    // Create new item
                    StockEntryItem::create([
                        'stock_entry_id' => $stockEntry->id,
                        'item_id' => $itemData['item_id'],
                        'opening_stock' => $itemData['opening_stock'],
                        'ordered_stock' => $itemData['ordered_stock'],
                        'total_stock' => $itemData['total_stock'],
                        'closing_stock' => $itemData['closing_stock'],
                        'sold_quantity' => $itemData['sold_quantity'],
                        'sales_amount' => $itemData['sales_amount'],
                        'purchase_price' => $itemData['purchase_price'] ?? 0,
                        'expiry_date' => !empty($itemData['expiry_date']) ? $itemData['expiry_date'] : null,
                    ]);
                }
            }

            // Handle expenses - accumulate new expenses
            foreach ($this->expenses as $expenseData) {
                if ($expenseData['amount'] > 0) {
                    Expense::create([
                        'stock_entry_id' => $stockEntry->id,
                        'type' => $expenseData['type'],
                        'amount' => $expenseData['amount'],
                        'description' => $expenseData['description'] ?? null,
                    ]);
                }
            }

            // Handle payments - accumulate new payments
            foreach ($this->payments as $paymentType => $amount) {
                if ($amount > 0) {
                    Payment::create([
                        'stock_entry_id' => $stockEntry->id,
                        'type' => $paymentType,
                        'amount' => $amount,
                    ]);
                }
            }

            DB::commit();
            
            return redirect()->route('stock-entries.show', $stockEntry)
                ->with('success', 'Director stock entry saved successfully!');

        } catch (\Exception $e) {
            DB::rollback();
            session()->flash('error', 'Error saving stock entry: ' . $e->getMessage());
        }
    }

    public function addNewStockItem()
    {
        $user = Auth::user();
        
        // Get all items (director can see all items, not seller-specific)
        $items = Item::orderBy('category')->orderBy('name')->get();
        
        // Add a new empty item to the stock items array
        $this->stockItems[] = [
            'item_id' => '',
            'item_name' => 'Select Item',
            'category' => '',
            'price' => 0,
            'purchase_price' => 0,
            'expiry_date' => '',
            'opening_stock' => 0,
            'ordered_stock' => 0,
            'total_stock' => 0,
            'closing_stock' => 0,
            'sold_quantity' => 0,
            'sales_amount' => 0,
            'can_edit_opening' => true,
        ];
        
        // Re-render to show the new item in the dropdown
        $this->render();
    }

    public function refreshStockItems()
    {
        $user = Auth::user();
        
        // Get all items and reinitialize
        $items = Item::orderBy('category')->orderBy('name')->get();
        
        // Check if there's existing stock entry for today
        $existingStockEntry = DailyStockEntry::where('date', $this->date)
            ->where('bar_id', $this->bar->id)
            ->with(['stockEntryItems'])
            ->first();

        // Initialize stock items for director (All items available)
        foreach ($items as $item) {
            $price = BarItemPrice::where('bar_id', $this->bar->id)
                                ->where('item_id', $item->id)
                                ->first()?->price ?? 0;

            $existingItem = $existingStockEntry ? 
                $existingStockEntry->stockEntryItems->where('item_id', $item->id)->first() : null;

            $this->stockItems[] = [
                'item_id' => $item->id,
                'item_name' => $item->name,
                'category' => $item->category,
                'price' => $price,
                'purchase_price' => $existingItem ? ($existingItem->purchase_price ?? 0) : 0,
                'expiry_date' => $existingItem ? ($existingItem->expiry_date?->format('Y-m-d') ?? '') : '',
                'opening_stock' => $existingItem ? $existingItem->opening_stock : 0,
                'ordered_stock' => $existingItem ? $existingItem->ordered_stock : 0,
                'total_stock' => $existingItem ? $existingItem->total_stock : 0,
                'closing_stock' => $existingItem ? $existingItem->closing_stock : 0,
                'sold_quantity' => $existingItem ? $existingItem->sold_quantity : 0,
                'sales_amount' => $existingItem ? $existingItem->sales_amount : 0,
                'can_edit_opening' => true,
            ];
        }

        // Initialize with one empty expense if none exist
        if (empty($this->expenses)) {
            $this->expenses[] = [
                'type' => 'lunch',
                'amount' => 0,
                'description' => '',
            ];
        }
        
        // Initialize closing stock for all items
        $this->initializeClosingStock();
    }

    public function removeStockItem($index)
    {
        unset($this->stockItems[$index]);
        $this->stockItems = array_values($this->stockItems);
        
        // Recalculate closing stock for all items
        $this->initializeClosingStock();
    }

    public function render()
    {
        return view('livewire.director-stock-entry-form');
    }
}
