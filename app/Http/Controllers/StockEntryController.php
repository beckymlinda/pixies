<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DailyStockEntry;
use App\Models\StockEntryItem;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Bar;
use App\Models\BarItemPrice;
use App\Models\Item;
use App\Models\BottleCount;
use App\Models\ProductUnit;
use App\Models\ProductUnitPrice;
use App\Models\ProductPurchaseHistory;
use App\Models\InventoryLedger;
use App\Models\WarehouseTransferRequest;
use App\Models\WarehouseTransferRequestItem;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StockEntryController extends Controller
{

    public function index()
    {
        $user = Auth::user();
        
        if ($user->isSeller()) {
            $entries = DailyStockEntry::where('bar_id', $user->bar_id)
                ->with(['bar', 'stockEntryItems.item'])
                ->orderBy('date', 'desc')
                ->paginate(10);

            $todayEntry = DailyStockEntry::where('bar_id', $user->bar_id)
                ->whereDate('date', now()->toDateString())
                ->first();
        } else {
            $entries = DailyStockEntry::with(['bar', 'user', 'stockEntryItems.item'])
                ->orderBy('date', 'desc')
                ->paginate(10);
            $todayEntry = null;
        }

        return view('stock-entries.index', compact('entries', 'todayEntry'));
    }

    public function create()
    {
        $user = Auth::user();
        
        if ($user->isSeller() && !$user->bar_id) {
            abort(403, 'Sellers must be assigned to a bar');
        }

        // Check if entry already exists for today
        $today = now()->format('Y-m-d');
        $existingEntry = DailyStockEntry::where('date', $today)
            ->where('bar_id', $user->bar_id)
            ->first();

        if ($existingEntry) {
            return redirect()->route('stock-entries.edit', $existingEntry)
                ->with('info', 'Continue selling for today.');
        }

        // Get all items with their units
        $items = Item::with('productUnits')->orderBy('category')->orderBy('name')->get();
        
        // Get approved order requests sum for this bar and date
        $today = now()->format('Y-m-d');
        $todayApprovedOrders = DB::table('order_request_items')
            ->join('order_requests', 'order_request_items.order_request_id', '=', 'order_requests.id')
            ->where('order_requests.bar_id', $user->bar_id)
            ->where('order_requests.date', $today)
            ->whereIn('order_requests.status', ['approved', 'partially_approved'])
            ->groupBy('order_request_items.item_id')
            ->select('order_request_items.item_id', DB::raw('SUM(order_request_items.approved_quantity) as total_approved'))
            ->pluck('total_approved', 'item_id')
            ->toArray();

        // Get latest stock for each item for this bar (using unified director_stock)
        $latestStockData = [];
        foreach ($items as $item) {
            $latestStockData[$item->id] = $item->director_stock;
        }
        
        // Prepare items data with latest stock and units
        $itemsData = $items->map(function($item) use ($latestStockData, $user, $todayApprovedOrders) {
            // Get bar-specific price
            $barItemPrice = BarItemPrice::where('bar_id', $user->bar_id)
                ->where('item_id', $item->id)
                ->first();
            
            // Load unit prices for this item
            $unitPrices = [];
            foreach ($item->productUnits as $unit) {
                $unitPrice = ProductUnitPrice::where('item_id', $item->id)
                    ->where('unit_name', $unit->unit_name)
                    ->first();
                $unit->price = $unitPrice;
            }

            $baseUnitCost = $item->average_unit_cost ?? 0;
            if ($baseUnitCost <= 0) {
                $baseUnit = $item->productUnits->firstWhere('is_base_unit', true);
                if ($baseUnit && $baseUnit->price) {
                    $baseUnitCost = $baseUnit->price->purchase_price ?? 0;
                }
            }
            
            return [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category,
                'price' => $barItemPrice ? $barItemPrice->price : $item->price,
                'purchase_price' => $baseUnitCost,
                'opening_stock' => $latestStockData[$item->id] ?? 0,
                'ordered_stock' => $todayApprovedOrders[$item->id] ?? 0,
                'product_units' => $item->productUnits,
            ];
        });
        
        $bar = $user->bar;

        $currentPage = max(1, (int) request()->query('page', 1));
        $perPage = 10;
        $totalItems = $itemsData->count();
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $currentPage = min($currentPage, $totalPages);

        $paginatedItems = $itemsData->slice(($currentPage - 1) * $perPage, $perPage)->values();

        return view('stock-entries.create', compact('paginatedItems', 'bar', 'items', 'currentPage', 'totalPages', 'perPage'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        
        // Validation rules for all items approach
        $validated = $request->validate([
            'date' => 'required|date',
            'items' => 'required|array',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.opening_stock' => 'required|numeric|min:0',
            'items.*.orders' => 'sometimes|numeric|min:0',
            'items.*.sales' => 'sometimes|numeric|min:0',
            'items.*.closing_stock' => 'sometimes|numeric|min:0',
            'items.*.sales_amount' => 'sometimes|numeric|min:0',
            'items.*.unit_name' => 'sometimes|string|nullable', // For multi-unit support
        ], [
            'items.required' => 'Stock items data is required',
            'items.*.item_id.required' => 'Item ID is required',
            'items.*.item_id.exists' => 'Selected item is invalid',
            'items.*.price.required' => 'Price is required for each item',
            'items.*.opening_stock.required' => 'Opening stock is required for each item',
        ]);
        
        // DEBUG: Dump all request data
        \Log::info('Stock Entry Request Data:', $request->all());
        \Log::info('Validated Items Data:', $validated['items']);
        
        DB::beginTransaction();
        
        try {
            $entryDate = $request->date ?? now()->format('Y-m-d');
            $inventoryService = new InventoryService();

            // Use one daily entry per bar/date (matches DB unique key).
            $stockEntry = DailyStockEntry::firstOrCreate([
                'bar_id' => $user->bar_id,
                'date' => $entryDate,
            ], [
                'user_id' => $user->id,
            ]);

            \Log::info('Daily Stock Entry created with ID: ' . $stockEntry->id);

            // Fetch approved orders for this bar and date
            $approvedOrders = DB::table('order_request_items')
                ->join('order_requests', 'order_request_items.order_request_id', '=', 'order_requests.id')
                ->where('order_requests.bar_id', $user->bar_id)
                ->where('order_requests.date', $entryDate)
                ->whereIn('order_requests.status', ['approved', 'partially_approved'])
                ->groupBy('order_request_items.item_id')
                ->select('order_request_items.item_id', DB::raw('SUM(order_request_items.approved_quantity) as total_approved'))
                ->pluck('total_approved', 'item_id')
                ->toArray();

            // Create stock entry items - only process items with actual activity
            $activeItems = collect($validated['items'])->filter(function($itemData) use ($approvedOrders) {
                $approvedQty = $approvedOrders[$itemData['item_id']] ?? 0;
                return $approvedQty > 0 || 
                       ($itemData['sales'] ?? 0) > 0 || 
                       ($itemData['closing_stock'] ?? 0) != $itemData['opening_stock'];
            });

            foreach ($activeItems as $index => $itemData) {
                \Log::info("Processing active item {$index}:", $itemData);
                $itemId = $itemData['item_id'];
                $approvedOrderedQty = $approvedOrders[$itemId] ?? 0;
                $unitName = $itemData['unit_name'] ?? null;
                
                // Convert sales to base units if unit_name is provided
                $salesQuantity = $itemData['sales'] ?? 0;
                if ($unitName && $salesQuantity > 0) {
                    $salesQuantity = $inventoryService->convertToBaseUnits($itemId, $salesQuantity, $unitName);
                }
                
                // Get correct unit price if unit_name is provided
                $unitPrice = $itemData['price'];
                if ($unitName) {
                    $unitPriceModel = $inventoryService->getUnitPrice($itemId, $unitName);
                    if ($unitPriceModel) {
                        $unitPrice = $unitPriceModel->selling_price;
                    }
                }
                
                try {
                    $stockItem = StockEntryItem::where('stock_entry_id', $stockEntry->id)
                        ->where('item_id', $itemId)
                        ->first();

                    if ($stockItem) {
                        $orderedStock = $approvedOrderedQty;
                        $newSoldQuantity = $salesQuantity;
                        $soldQuantity = $stockItem->sold_quantity + $newSoldQuantity;
                        $totalStock = $stockItem->opening_stock + $orderedStock;

                        $resolvedPurchasePrice = $itemData['purchase_price'] ?? 0;
                        if ($resolvedPurchasePrice <= 0) {
                            $resolvedPurchasePrice = $stockItem->purchase_price;
                        }
                        if ($resolvedPurchasePrice <= 0) {
                            $catalogItem = Item::find($itemId);
                            $resolvedPurchasePrice = $catalogItem?->average_unit_cost ?? 0;
                        }
                        
                        // Use the closing_stock from the form if provided (already converted by JavaScript)
                        $closingStock = isset($itemData['closing_stock']) 
                            ? $itemData['closing_stock'] 
                            : max(0, $totalStock - $soldQuantity);

                        $stockItem->update([
                            'ordered_stock' => $orderedStock,
                            'total_stock' => $totalStock,
                            'sold_quantity' => $soldQuantity,
                            'closing_stock' => $closingStock,
                            // Preserve historical pricing: add only the new sale value
                            // using the current transaction price.
                            'sales_amount' => $stockItem->sales_amount + ($newSoldQuantity * $unitPrice),
                            'price' => $unitPrice,
                            'purchase_price' => $resolvedPurchasePrice,
                            'expiry_date' => !empty($itemData['expiry_date']) ? $itemData['expiry_date'] : $stockItem->expiry_date,
                            'unit_name' => $unitName,
                        ]);
                    } else {
                        $resolvedPurchasePrice = $itemData['purchase_price'] ?? 0;
                        if ($resolvedPurchasePrice <= 0) {
                            $resolvedPurchasePrice = Item::find($itemId)?->average_unit_cost ?? 0;
                        }

                        $stockItem = StockEntryItem::create([
                            'stock_entry_id' => $stockEntry->id,
                            'item_id' => $itemId,
                            'opening_stock' => $itemData['opening_stock'],
                            'ordered_stock' => $approvedOrderedQty,
                            'total_stock' => $itemData['opening_stock'] + $approvedOrderedQty,
                            // Use the closing_stock from the form if provided (already converted by JavaScript)
                            'closing_stock' => isset($itemData['closing_stock']) 
                                ? $itemData['closing_stock'] 
                                : max(0, ($itemData['opening_stock'] + $approvedOrderedQty) - $salesQuantity),
                            'sold_quantity' => $salesQuantity,
                            'sales_amount' => $salesQuantity * $unitPrice,
                            'price' => $unitPrice,
                            'purchase_price' => $resolvedPurchasePrice,
                            'expiry_date' => !empty($itemData['expiry_date']) ? $itemData['expiry_date'] : null,
                            'unit_name' => $unitName,
                        ]);
                    }
                    
                    // Sync the closing stock back to the Item's unified director_stock
                    $item = Item::find($itemId);
                    if ($item) {
                        $item->update(['director_stock' => $stockItem->closing_stock]);

                        // If this is a Castel-marked item, record counted bottles exactly from sold quantity
                        if ($item->is_castel) {
                            BottleCount::updateOrCreate(
                                [
                                    'stock_entry_id' => $stockEntry->id,
                                    'item_id' => $itemId,
                                ],
                                [
                                    'bar_id' => $stockEntry->bar_id,
                                    'date' => $entryDate,
                                    'recorded_by' => $user->id,
                                    'counted' => (int) ($stockItem->sold_quantity ?? ($itemData['sales'] ?? 0)),
                                ]
                            );
                        }
                    }

                    \Log::info("Stock Item created/updated with ID: " . $stockItem->id);
                    
                } catch (\Exception $e) {
                    \Log::error("Failed to create stock item {$index}: " . $e->getMessage());
                    throw $e;
                }
            }

            // Create expenses
            if ($request->expenses) {
                foreach ($request->expenses as $expenseData) {
                    Expense::create([
                        'stock_entry_id' => $stockEntry->id,
                        'type' => $expenseData['type'],
                        'amount' => $expenseData['amount'],
                        'description' => $expenseData['description'] ?? null,
                    ]);
                }
            }

            // Create payments
            if ($request->payments) {
                foreach ($request->payments as $paymentType => $amount) {
                    if ($amount > 0) {
                        Payment::create([
                            'stock_entry_id' => $stockEntry->id,
                            'type' => $paymentType,
                            'amount' => $amount,
                        ]);
                    }
                }
            }

            DB::commit();

            if ($user->isSeller() && $entryDate === now()->format('Y-m-d')) {
                return redirect()->route('stock-entries.index')
                    ->with('success', 'Sales saved. Tap Continue Selling to record more.');
            }
            
            return redirect()->route('stock-entries.show', $stockEntry)
                ->with('success', 'Stock entry saved successfully!');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()
                ->with('error', 'Error saving stock entry: ' . $e->getMessage());
        }
    }

    public function show(DailyStockEntry $stockEntry, Request $request)
    {
        $user = Auth::user();
        
        // Sellers can only view entries for their own bar.
        if ($user->isSeller() && $stockEntry->bar_id !== $user->bar_id) {
            abort(403);
        }

        $stockEntry->load(['bar', 'user', 'stockEntryItems.item', 'expenses', 'payments']);

        // Get all items with pagination and units
        $allItems = Item::with('productUnits.price')->orderBy('category')->orderBy('name')->paginate(10, ['*'], 'page', $request->get('page', 1));
        
        // Get yesterday's closing stock for all items
        $yesterday = $stockEntry->date->copy()->subDay()->format('Y-m-d');
        $yesterdayEntry = DailyStockEntry::where('date', $yesterday)
            ->where('bar_id', $stockEntry->bar_id)
            ->first();
        
        $yesterdayClosingStock = [];
        if ($yesterdayEntry) {
            $yesterdayClosingStock = $yesterdayEntry->stockEntryItems->pluck('closing_stock', 'item_id')->toArray();
        }
        
        // Get existing stock entry items data
        $existingItems = $stockEntry->stockEntryItems->keyBy('item_id');
        $barPrices = BarItemPrice::where('bar_id', $stockEntry->bar_id)->pluck('price', 'item_id');
        
        // Get approved order requests sum for this date
        $entryDate = $stockEntry->date->format('Y-m-d');
        $approvedOrders = DB::table('order_request_items')
            ->join('order_requests', 'order_request_items.order_request_id', '=', 'order_requests.id')
            ->where('order_requests.bar_id', $stockEntry->bar_id)
            ->where('order_requests.date', $entryDate)
            ->whereIn('order_requests.status', ['approved', 'partially_approved'])
            ->groupBy('order_request_items.item_id')
            ->select('order_request_items.item_id', DB::raw('SUM(order_request_items.approved_quantity) as total_approved'))
            ->pluck('total_approved', 'item_id')
            ->toArray();
        
        // Merge existing data with all items, always showing price and yesterday's closing stock
        $itemsData = $allItems->map(function($item) use ($existingItems, $yesterdayClosingStock, $barPrices, $approvedOrders) {
            $stockItem = $existingItems->get($item->id);
            
            // Use bar-specific price first, then stock snapshot price, then item master price.
            $price = $barPrices[$item->id] ?? ($stockItem ? $stockItem->price : ($item->price ?? 0));
            
            // Use yesterday's closing stock as opening stock, fallback to stock item data, then 0
            $openingStock = $yesterdayClosingStock[$item->id] ?? ($stockItem ? $stockItem->opening_stock : 0);
            
            // Calculate values based on approved order requests
            $approvedQty = $approvedOrders[$item->id] ?? null;
            $orderedStock = $approvedQty !== null ? $approvedQty : ($stockItem ? $stockItem->ordered_stock : 0);
            
            $totalStock = $openingStock + $orderedStock;
            $soldQuantity = $stockItem ? $stockItem->sold_quantity : 0;
            $closingStock = $stockItem ? $stockItem->closing_stock : ($openingStock + $orderedStock - $soldQuantity);
            $salesAmount = $stockItem ? $stockItem->sales_amount : ($soldQuantity * $price);
            
            return [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category,
                'price' => $price,
                'opening_stock' => $openingStock,
                'ordered_stock' => $orderedStock,
                'total_stock' => $totalStock,
                'closing_stock' => max(0, $closingStock), // Ensure no negative closing stock
                'sold_quantity' => $soldQuantity,
                'sales_amount' => $salesAmount,
                'has_data' => $stockItem ? true : false,
                'has_yesterday_data' => isset($yesterdayClosingStock[$item->id]),
                'stock_entry_item' => $stockItem,
                'product_units' => $item->productUnits,
            ];
        });

        return view('stock-entries.show', compact('stockEntry', 'itemsData', 'allItems'));
    }

    public function edit(DailyStockEntry $stockEntry)
    {
        $user = Auth::user();
        
        // Sellers can edit only today's entry for their own bar.
        if ($user->isSeller() && ($stockEntry->bar_id !== $user->bar_id || $stockEntry->date->format('Y-m-d') !== now()->format('Y-m-d'))) {
            abort(403);
        }

        $stockEntry->load(['bar', 'user', 'stockEntryItems.item', 'expenses', 'payments']);

        // Get all items first (without pagination) to prepare data
        $allItems = Item::with('productUnits')->orderBy('category')->orderBy('name')->get();
        
        // Get yesterday's closing stock for all items
        $yesterday = $stockEntry->date->copy()->subDay()->format('Y-m-d');
        $yesterdayEntry = DailyStockEntry::where('date', $yesterday)
            ->where('bar_id', $stockEntry->bar_id)
            ->first();
        
        $yesterdayClosingStock = [];
        if ($yesterdayEntry) {
            $yesterdayClosingStock = $yesterdayEntry->stockEntryItems->pluck('closing_stock', 'item_id')->toArray();
        }
        
        // Get existing stock entry items data
        $existingItems = $stockEntry->stockEntryItems->keyBy('item_id');
        $barPrices = BarItemPrice::where('bar_id', $stockEntry->bar_id)->pluck('price', 'item_id');
        
        // Get approved order requests sum for this date
        $entryDate = $stockEntry->date->format('Y-m-d');
        $approvedOrders = DB::table('order_request_items')
            ->join('order_requests', 'order_request_items.order_request_id', '=', 'order_requests.id')
            ->where('order_requests.bar_id', $stockEntry->bar_id)
            ->where('order_requests.date', $entryDate)
            ->whereIn('order_requests.status', ['approved', 'partially_approved'])
            ->groupBy('order_request_items.item_id')
            ->select('order_request_items.item_id', DB::raw('SUM(order_request_items.approved_quantity) as total_approved'))
            ->pluck('total_approved', 'item_id')
            ->toArray();
        
        // Merge existing data with all items, always showing price and yesterday's closing stock
        $itemsData = $allItems->map(function($item) use ($existingItems, $yesterdayClosingStock, $barPrices, $approvedOrders) {
            $stockItem = $existingItems->get($item->id);
            
            // Use bar-specific price first, then stock snapshot price, then item master price.
            $price = $barPrices[$item->id] ?? ($stockItem ? $stockItem->price : ($item->price ?? 0));
            
            // Load unit prices for this item
            foreach ($item->productUnits as $unit) {
                $unitPrice = ProductUnitPrice::where('item_id', $item->id)
                    ->where('unit_name', $unit->unit_name)
                    ->first();
                $unit->price = $unitPrice;
            }
            
            // Use unified director_stock as opening stock if no stock entry item is recorded yet
            $openingStock = $stockItem ? $stockItem->opening_stock : $item->director_stock;
            
            // Calculate values based on approved order requests
            $approvedQty = $approvedOrders[$item->id] ?? null;
            $orderedStock = $approvedQty !== null ? $approvedQty : ($stockItem ? $stockItem->ordered_stock : 0);
            
            $totalStock = $openingStock + $orderedStock;
            $soldQuantity = $stockItem ? $stockItem->sold_quantity : 0;
            $closingStock = $stockItem ? $stockItem->closing_stock : ($openingStock + $orderedStock - $soldQuantity);
            $salesAmount = $stockItem ? $stockItem->sales_amount : ($soldQuantity * $price);
            
            return [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category,
                'price' => $price,
                'opening_stock' => $openingStock,
                'ordered_stock' => $orderedStock,
                'total_stock' => $totalStock,
                'closing_stock' => max(0, $closingStock), // Ensure no negative closing stock
                'sold_quantity' => $soldQuantity,
                'product_units' => $item->productUnits,
                'sales_amount' => $salesAmount,
                'has_data' => $stockItem ? true : false,
                'has_yesterday_data' => isset($yesterdayClosingStock[$item->id]),
            ];
        });
        
        $paginatedItems = $itemsData; // Pass all items directly

        return view('stock-entries.edit', compact('stockEntry', 'paginatedItems'));
    }

    public function update(Request $request, DailyStockEntry $stockEntry)
    {
        $user = Auth::user();
        
        // Sellers can edit only today's entry for their own bar.
        if ($user->isSeller() && ($stockEntry->bar_id !== $user->bar_id || $stockEntry->date->format('Y-m-d') !== now()->format('Y-m-d'))) {
            abort(403);
        }

        // Validation rules
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.opening_stock' => 'required|numeric|min:0',
            'items.*.orders' => 'required|numeric|min:0',
            'items.*.sales' => 'required|numeric|min:0',
            'items.*.closing_stock' => 'required|numeric|min:0',
            'items.*.sales_amount' => 'required|numeric|min:0',
            'items.*.unit_name' => 'sometimes|string|nullable', // For multi-unit support
        ], [
            'items.required' => 'You must have at least one stock item',
            'items.min' => 'You must have at least one stock item',
            'items.*.item_id.required' => 'Please select an item for each row',
            'items.*.item_id.exists' => 'Selected item is invalid',
            'items.*.price.required' => 'Price is required for each item',
            'items.*.opening_stock.required' => 'Opening stock is required for each item',
            'items.*.orders.required' => 'Orders quantity is required for each item',
            'items.*.sales.required' => 'Sales quantity is required for each item',
            'items.*.closing_stock.required' => 'Closing stock is required for each item',
        ]);

        DB::beginTransaction();
        
        try {
            $inventoryService = new InventoryService();
            
            // Fetch approved orders for this bar and date
            $entryDate = $stockEntry->date->format('Y-m-d');
            $approvedOrders = DB::table('order_request_items')
                ->join('order_requests', 'order_request_items.order_request_id', '=', 'order_requests.id')
                ->where('order_requests.bar_id', $stockEntry->bar_id)
                ->where('order_requests.date', $entryDate)
                ->whereIn('order_requests.status', ['approved', 'partially_approved'])
                ->groupBy('order_request_items.item_id')
                ->select('order_request_items.item_id', DB::raw('SUM(order_request_items.approved_quantity) as total_approved'))
                ->pluck('total_approved', 'item_id')
                ->toArray();

            // Update or Create stock entry items (using updateOrCreate to avoid deleting items on other pages)
            foreach ($validated['items'] as $itemData) {
                $itemId = $itemData['item_id'];
                $approvedOrderedQty = $approvedOrders[$itemId] ?? 0;
                $unitName = $itemData['unit_name'] ?? null;
                
                // Convert sales to base units if unit_name is provided
                $salesQuantity = $itemData['sales'] ?? 0;
                if ($unitName && $salesQuantity > 0) {
                    $salesQuantity = $inventoryService->convertToBaseUnits($itemId, $salesQuantity, $unitName);
                }
                
                // Get correct unit price if unit_name is provided
                $unitPrice = $itemData['price'];
                if ($unitName) {
                    $unitPriceModel = $inventoryService->getUnitPrice($itemId, $unitName);
                    if ($unitPriceModel) {
                        $unitPrice = $unitPriceModel->selling_price;
                    }
                }

                $stockItem = StockEntryItem::updateOrCreate(
                    [
                        'stock_entry_id' => $stockEntry->id,
                        'item_id' => $itemId,
                    ],
                    [
                        'opening_stock' => $itemData['opening_stock'],
                        'ordered_stock' => $approvedOrderedQty,
                        'total_stock' => $itemData['opening_stock'] + $approvedOrderedQty,
                        // Use the closing_stock from the form if provided (already converted by JavaScript)
                        'closing_stock' => isset($itemData['closing_stock']) 
                            ? $itemData['closing_stock'] 
                            : max(0, ($itemData['opening_stock'] + $approvedOrderedQty) - $salesQuantity),
                        'sold_quantity' => $salesQuantity,
                        'sales_amount' => $salesQuantity * $unitPrice,
                        'price' => $unitPrice,
                        'purchase_price' => $itemData['purchase_price'] ?? 0,
                        'expiry_date' => !empty($itemData['expiry_date']) ? $itemData['expiry_date'] : null,
                        'unit_name' => $unitName,
                    ]
                );

                // If this is a castel item, sync bottle counts exactly to the current sold quantity
                $item = Item::find($itemId);
                if ($item && $item->is_castel) {
                    BottleCount::updateOrCreate(
                        [
                            'stock_entry_id' => $stockEntry->id,
                            'item_id' => $itemId,
                        ],
                        [
                            'bar_id' => $stockEntry->bar_id,
                            'date' => $stockEntry->date,
                            'recorded_by' => $user->id,
                            'counted' => (int) $itemData['sales'],
                        ]
                    );
                }

                // Sync the closing stock back to the Item's unified director_stock
                $item = Item::find($itemId);
                if ($item) {
                    $item->update(['director_stock' => $itemData['closing_stock']]);
                }
            }

            // Sync Expenses (if provided)
            if ($request->has('expenses')) {
                // For simplicity in update, we'll replace them if sent, or just add new ones
                // Better approach: remove existing and re-add to match the current form state
                $stockEntry->expenses()->delete();
                foreach ($request->expenses as $expenseData) {
                    if (!empty($expenseData['amount'])) {
                        Expense::create([
                            'stock_entry_id' => $stockEntry->id,
                            'type' => $expenseData['type'],
                            'amount' => $expenseData['amount'],
                            'description' => $expenseData['description'] ?? null,
                            'date' => $stockEntry->date,
                            'user_id' => $user->id,
                        ]);
                    }
                }
            }

            // Sync Payments (if provided)
            if ($request->has('payments')) {
                $stockEntry->payments()->delete();
                foreach ($request->payments as $paymentType => $amount) {
                    if ($amount > 0) {
                        Payment::create([
                            'stock_entry_id' => $stockEntry->id,
                            'type' => $paymentType,
                            'amount' => $amount,
                        ]);
                    }
                }
            }

            DB::commit();

            if ($user->isSeller() && $stockEntry->date->format('Y-m-d') === now()->format('Y-m-d')) {
                return redirect()->route('stock-entries.index')
                    ->with('success', 'Sales updated. Tap Continue Selling to record more.');
            }
            
            return redirect()->route('stock-entries.show', $stockEntry)
                ->with('success', 'Stock entry updated successfully!');

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Stock entry update failed: ' . $e->getMessage());
            return back()->withInput()
                ->with('error', 'Error updating stock entry: ' . $e->getMessage());
        }
    }

    // Director-specific methods
    public function directorIndex()
    {
        $user = Auth::user();
        
        // Directors can see all stock entries
        $entries = DailyStockEntry::with(['bar', 'user', 'stockEntryItems.item'])
            ->orderBy('date', 'desc')
            ->paginate(10);

        return view('stock-entries.director-index', compact('entries'));
    }

    public function stockOverview(Request $request)
    {
        $user = Auth::user();
        if (!$user->isDirector() && !$user->isManager()) {
            abort(403);
        }

        $selectedBarId = $request->query('bar_id');
        $bars = Bar::listed()->orderBy('name')->get();

        $stockEntries = StockEntryItem::join('daily_stock_entries', 'stock_entry_items.stock_entry_id', '=', 'daily_stock_entries.id')
            ->when($selectedBarId, function ($query) use ($selectedBarId) {
                $query->where('daily_stock_entries.bar_id', $selectedBarId);
            })
            ->orderBy('daily_stock_entries.date', 'desc')
            ->orderBy('stock_entry_items.updated_at', 'desc')
            ->orderBy('stock_entry_items.id', 'desc')
            ->get([
                'stock_entry_items.*',
                'daily_stock_entries.bar_id',
                'daily_stock_entries.date as stock_date',
            ]);

        $latestStocks = $stockEntries->groupBy(function ($row) {
            return $row->bar_id . '_' . $row->item_id;
        })->map->first()->values();

        $itemIds = $latestStocks->pluck('item_id')->unique()->all();
        $barIds = $latestStocks->pluck('bar_id')->unique()->all();

        $items = Item::whereIn('id', $itemIds)->get()->keyBy('id');
        $barNames = Bar::whereIn('id', $barIds)->pluck('name', 'id');

        $barItemPrices = BarItemPrice::whereIn('bar_id', $barIds)
            ->whereIn('item_id', $itemIds)
            ->get()
            ->groupBy('bar_id')
            ->map(function ($group) {
                return $group->keyBy('item_id');
            });

        $stockRows = $latestStocks->map(function ($stock) use ($items, $barNames, $barItemPrices) {
            $item = $items->get($stock->item_id);
            $price = $barItemPrices->get($stock->bar_id)?->get($stock->item_id)?->price ?? ($item?->price ?? 0);

            return [
                'bar_name' => $barNames->get($stock->bar_id, 'Unknown'),
                'item_name' => $item?->name ?? 'Unknown',
                'category' => $item?->category ?? 'Unknown',
                'stock' => $stock->closing_stock,
                'price' => $price,
                'last_updated' => $stock->stock_date,
            ];
        })->sortBy([['bar_name', 'asc'], ['item_name', 'asc']])->values();

        return view('stock.index', compact('bars', 'stockRows', 'selectedBarId'));
    }

    public function directorCreate()
    {
        $transferRequests = WarehouseTransferRequest::with([
            'bar',
            'requestedBy',
            'approvedBy',
            'items.warehouseStock',
            'items.item',
        ])
            ->orderBy('requested_at', 'desc')
            ->get();

        return view('stock-entries.director-create', compact('transferRequests'));
    }

    public function directorStore(Request $request)
    {
        $user = Auth::user();
        
        $request->validate([
            'bar_id' => 'required|exists:bars,id',
            'date' => 'required|date',
            'stock_items' => 'required|array',
            'stock_items.*.item_id' => 'required|exists:items,id',
            'stock_items.*.opening_stock' => 'required|integer|min:0',
            'stock_items.*.ordered_stock' => 'required|integer|min:0',
            'stock_items.*.sold_quantity' => 'required|integer|min:0',
            'stock_items.*.closing_stock' => 'required|integer|min:0',
            'stock_items.*.sales_amount' => 'required|numeric|min:0',
            'stock_items.*.purchase_price' => 'nullable|numeric|min:0',
            'stock_items.*.expiry_date' => 'nullable|date',
        ]);

        DB::beginTransaction();
        
        try {
            $barId = $request->input('bar_id');
            $date = $request->input('date');
            
            // Create or update daily stock entry
            $stockEntry = DailyStockEntry::where('date', $date)
                ->where('bar_id', $barId)
                ->first();

            if ($stockEntry) {
                $stockEntry->updated_at = now();
                $stockEntry->save();
            } else {
                $stockEntry = DailyStockEntry::create([
                    'bar_id' => $barId,
                    'user_id' => $user->id,
                    'date' => $date,
                ]);
            }

            // Process stock items with adjustments
            $stockItems = $request->input('stock_items', []);
            foreach ($stockItems as $itemData) {
                if (!empty($itemData['item_id'])) {
                    $existingItem = $stockEntry->stockEntryItems()
                        ->where('item_id', $itemData['item_id'])
                        ->first();

                    if ($existingItem) {
                        // Update existing item with new values
                        $existingItem->opening_stock = $itemData['opening_stock'];
                        $existingItem->ordered_stock = $itemData['ordered_stock'];
                        $existingItem->total_stock = $itemData['opening_stock'] + $itemData['ordered_stock'];
                        $existingItem->sold_quantity = $itemData['sold_quantity'];
                        $existingItem->closing_stock = $existingItem->total_stock - $existingItem->sold_quantity;
                        $existingItem->sales_amount = $itemData['sales_amount'];
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
                            'total_stock' => $itemData['opening_stock'] + $itemData['ordered_stock'],
                            'closing_stock' => ($itemData['opening_stock'] + $itemData['ordered_stock']) - $itemData['sold_quantity'],
                            'sold_quantity' => $itemData['sold_quantity'],
                            'sales_amount' => $itemData['sales_amount'],
                            'purchase_price' => $itemData['purchase_price'] ?? 0,
                            'expiry_date' => !empty($itemData['expiry_date']) ? $itemData['expiry_date'] : null,
                        ]);
                    }
                }
            }

            DB::commit();
            
            return redirect()->route('director-stock-entries.index')
                ->with('success', 'Director stock entry saved successfully!');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()
                ->with('error', 'Error saving stock entry: ' . $e->getMessage());
        }
    }

    // API Methods for Item Management
    public function getItemsByBar($barId)
    {
        // Get all items with their bar-specific prices
        $items = Item::with(['barItemPrices' => function($query) use ($barId) {
            $query->where('bar_id', $barId);
        }])->get();

        $itemsWithStock = $items->map(function($item) use ($barId) {
            // Get current stock for this item and bar from the most recent stock entry
            $currentStock = StockEntryItem::join('daily_stock_entries', 'stock_entry_items.stock_entry_id', '=', 'daily_stock_entries.id')
                ->where('daily_stock_entries.bar_id', $barId)
                ->where('stock_entry_items.item_id', $item->id)
                ->orderBy('daily_stock_entries.date', 'desc')
                ->orderBy('stock_entry_items.updated_at', 'desc')
                ->orderBy('stock_entry_items.id', 'desc')
                ->value('stock_entry_items.closing_stock') ?? 0;

            // If no stock entry found, check if there's any stock entry at all
            if ($currentStock == 0) {
                $anyStock = StockEntryItem::where('item_id', $item->id)->exists();
                if (!$anyStock) {
                    // No stock entries exist for this item, set to 0
                    $currentStock = 0;
                }
            }

            return [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category,
                'description' => $item->description ?? '',
                'price' => $item->barItemPrices->first()?->price ?? 0,
                'stock' => $currentStock,
                'opening_stock' => 0,
            ];
        });

        return response()->json($itemsWithStock);
    }

    public function getItem($itemId)
    {
        $item = Item::findOrFail($itemId);
        
        return response()->json([
            'id' => $item->id,
            'name' => $item->name,
            'category' => $item->category,
            'description' => $item->description,
            'price' => $item->barItemPrices->first()?->price ?? 0,
            'stock' => 0,
            'opening_stock' => 0,
        ]);
    }

    public function createItem(Request $request)
    {
        // Decode additional_units if sent as JSON string
        $additionalUnits = $request->additional_units;
        if (is_string($additionalUnits)) {
            $additionalUnits = json_decode($additionalUnits, true);
        }
        
        $request->merge(['additional_units' => $additionalUnits]);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|in:beer,spirit,soda,other',
            'description' => 'nullable|string',
            'expiry_date' => 'nullable|date|after_or_equal:today',
            'purchase_unit' => 'required|string|max:255',
            'quantity_purchased' => 'required|integer|min:1',
            'total_purchase_cost' => 'required|numeric|min:0',
            'base_unit' => 'required|string|max:255',
            'conversion_factor' => 'required|integer|min:1',
            'base_unit_selling_price' => 'required|numeric|min:0',
            'additional_units' => 'nullable|array',
            'bar_id' => 'required|exists:bars,id',
        ]);

        DB::beginTransaction();
        
        try {
            // Calculate cost per base unit
            $totalBaseUnits = $request->quantity_purchased * $request->conversion_factor;
            $calculatedBaseUnitCost = $totalBaseUnits > 0 ? $request->total_purchase_cost / $totalBaseUnits : 0;

            // Create the item
            $item = Item::create([
                'name' => $request->name,
                'category' => $request->category,
                'description' => $request->description,
                'expiry_date' => $request->expiry_date,
                'director_stock' => $totalBaseUnits, // Initial stock is calculated from purchase
                'average_unit_cost' => $calculatedBaseUnitCost,
                'lifetime_quantity_purchased' => $totalBaseUnits,
            ]);

            // Create purchase history record
            $item->addPurchaseHistory([
                'purchase_unit' => $request->purchase_unit,
                'quantity_purchased' => $request->quantity_purchased,
                'total_purchase_cost' => $request->total_purchase_cost,
                'calculated_base_unit_cost' => $calculatedBaseUnitCost,
                'purchase_date' => now()->format('Y-m-d'),
            ]);

            // Create base unit
            $baseUnit = ProductUnit::create([
                'item_id' => $item->id,
                'unit_name' => $request->base_unit,
                'conversion_factor' => 1,
                'is_base_unit' => true,
            ]);

            // Create base unit price
            ProductUnitPrice::create([
                'item_id' => $item->id,
                'unit_name' => $request->base_unit,
                'selling_price' => $request->base_unit_selling_price,
                'purchase_price' => $calculatedBaseUnitCost,
            ]);

            // Create additional units if provided
            if ($request->has('additional_units') && is_array($request->additional_units)) {
                foreach ($request->additional_units as $unitData) {
                    if (!empty($unitData['unit_name'])) {
                        $additionalUnit = ProductUnit::create([
                            'item_id' => $item->id,
                            'unit_name' => $unitData['unit_name'],
                            'conversion_factor' => $unitData['conversion_factor'] ?? 1,
                            'is_base_unit' => false,
                        ]);

                        // Create unit price if selling price is provided
                        if (isset($unitData['selling_price']) && $unitData['selling_price'] > 0) {
                            $unitCost = $calculatedBaseUnitCost * $unitData['conversion_factor'];
                            ProductUnitPrice::create([
                                'item_id' => $item->id,
                                'unit_name' => $unitData['unit_name'],
                                'selling_price' => $unitData['selling_price'],
                                'purchase_price' => $unitCost,
                            ]);
                        }
                    }
                }
            }

            // Create bar item price using base unit selling price
            BarItemPrice::create([
                'bar_id' => $request->bar_id,
                'item_id' => $item->id,
                'price' => $request->base_unit_selling_price,
            ]);

            // Create ledger entry for initial stock
            $item->addLedgerEntry([
                'bar_id' => $request->bar_id,
                'action_type' => 'purchase',
                'quantity' => $totalBaseUnits,
                'unit_cost' => $calculatedBaseUnitCost,
                'total_cost' => $request->total_purchase_cost,
                'balance_after' => $totalBaseUnits,
                'reference_type' => 'purchase_history',
                'reference_id' => $item->purchaseHistory->first()->id,
                'transaction_date' => now(),
            ]);

            DB::commit();
            
            return response()->json(['success' => true, 'message' => 'Item created successfully']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => 'Error creating item: ' . $e->getMessage()], 500);
        }
    }

    public function editItem($itemId)
    {
        $barId = request('bar_id', Bar::listed()->orderBy('name')->value('id'));

        $item = Item::with(['barItemPrices' => function($query) use ($barId) {
            $query->where('bar_id', $barId);
        }, 'productUnits.price', 'purchaseHistory'])->findOrFail($itemId);

        // Format product units with their prices
        $productUnits = $item->productUnits->map(function($unit) {
            return [
                'id' => $unit->id,
                'item_id' => $unit->item_id,
                'unit_name' => $unit->unit_name,
                'conversion_factor' => $unit->conversion_factor,
                'is_base_unit' => $unit->is_base_unit,
                'price' => $unit->price ? [
                    'id' => $unit->price->id,
                    'item_id' => $unit->price->item_id,
                    'unit_name' => $unit->price->unit_name,
                    'selling_price' => $unit->price->selling_price,
                    'purchase_price' => $unit->price->purchase_price,
                ] : null,
            ];
        })->toArray();

        // Get latest purchase history
        $latestPurchase = $item->purchaseHistory->first();

        return response()->json([
            'id' => $item->id,
            'name' => $item->name,
            'category' => $item->category,
            'description' => $item->description,
            'expiry_date' => $item->expiry_date ? $item->expiry_date->format('Y-m-d') : null,
            'director_stock' => $item->director_stock,
            'average_unit_cost' => $item->average_unit_cost,
            'bar_item_prices' => $item->barItemPrices->toArray(),
            'product_units' => $productUnits,
            'purchase_unit' => $latestPurchase->purchase_unit ?? null,
            'quantity_purchased' => $latestPurchase->quantity_purchased ?? null,
            'total_purchase_cost' => $latestPurchase->total_purchase_cost ?? null,
            'base_unit' => $item->baseUnit->unit_name ?? 'Bottle',
            'conversion_factor' => $latestPurchase ? ($latestPurchase->quantity_purchased * ($item->baseUnit->conversion_factor ?? 1) / $latestPurchase->quantity_purchased) : 24,
        ]);
    }

    public function updateItem(Request $request, $itemId)
    {
        // Decode additional_units if sent as JSON string
        $additionalUnits = $request->additional_units;
        if (is_string($additionalUnits)) {
            $additionalUnits = json_decode($additionalUnits, true);
        }
        
        $request->merge(['additional_units' => $additionalUnits]);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|in:beer,spirit,soda,other',
            'description' => 'nullable|string',
            'expiry_date' => 'nullable|date|after_or_equal:today',
            'base_unit' => 'required|string|max:255',
            'base_unit_selling_price' => 'required|numeric|min:0',
            'additional_units' => 'nullable|array',
            'bar_id' => 'required|exists:bars,id',
        ]);

        DB::beginTransaction();
        
        try {
            $item = Item::findOrFail($itemId);
            
            // Update the item basic info
            $item->update([
                'name' => $request->name,
                'category' => $request->category,
                'description' => $request->description,
                'expiry_date' => $request->expiry_date,
            ]);

            // Update or create bar item price
            BarItemPrice::updateOrCreate(
                ['bar_id' => $request->bar_id, 'item_id' => $item->id],
                ['price' => $request->base_unit_selling_price]
            );

            // Update base unit
            $baseUnit = $item->baseUnit;
            if ($baseUnit) {
                $baseUnit->update([
                    'unit_name' => $request->base_unit,
                ]);
            } else {
                $baseUnit = ProductUnit::create([
                    'item_id' => $item->id,
                    'unit_name' => $request->base_unit,
                    'conversion_factor' => 1,
                    'is_base_unit' => true,
                ]);
            }

            // Update base unit price
            ProductUnitPrice::updateOrCreate(
                ['item_id' => $item->id, 'unit_name' => $request->base_unit],
                [
                    'selling_price' => $request->base_unit_selling_price,
                    'purchase_price' => $item->average_unit_cost ?? 0,
                ]
            );

            // Handle additional units - delete non-base units and recreate
            ProductUnit::where('item_id', $item->id)
                ->where('is_base_unit', false)
                ->delete();
            
            ProductUnitPrice::where('item_id', $item->id)
                ->where('unit_name', '!=', $request->base_unit)
                ->delete();
            
            // Create new additional units
            if ($request->has('additional_units') && is_array($request->additional_units)) {
                foreach ($request->additional_units as $unitData) {
                    if (!empty($unitData['unit_name'])) {
                        $additionalUnit = ProductUnit::create([
                            'item_id' => $item->id,
                            'unit_name' => $unitData['unit_name'],
                            'conversion_factor' => $unitData['conversion_factor'] ?? 1,
                            'is_base_unit' => false,
                        ]);

                        // Create unit price if selling price is provided
                        if (isset($unitData['selling_price']) && $unitData['selling_price'] > 0) {
                            $unitCost = ($item->average_unit_cost ?? 0) * $unitData['conversion_factor'];
                            ProductUnitPrice::create([
                                'item_id' => $item->id,
                                'unit_name' => $unitData['unit_name'],
                                'selling_price' => $unitData['selling_price'],
                                'purchase_price' => $unitCost,
                            ]);
                        }
                    }
                }
            }

            DB::commit();
            
            return response()->json(['success' => true, 'message' => 'Item updated successfully']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => 'Error updating item: ' . $e->getMessage()], 500);
        }
    }

    public function deleteItem($itemId)
    {
        DB::beginTransaction();
        
        try {
            $item = Item::findOrFail($itemId);
            
            // Check if item has any stock entries
            $hasStockEntries = StockEntryItem::where('item_id', $itemId)->exists();
            
            if ($hasStockEntries) {
                return response()->json(['success' => false, 'message' => 'Cannot delete item with existing stock entries'], 400);
            }
            
            // Delete related bar item prices
            BarItemPrice::where('item_id', $itemId)->delete();
            
            // Delete related units and prices
            ProductUnit::where('item_id', $itemId)->delete();
            ProductUnitPrice::where('item_id', $itemId)->delete();
            
            // Delete related ledger entries
            InventoryLedger::where('item_id', $itemId)->delete();
            
            // Delete related purchase history
            ProductPurchaseHistory::where('item_id', $itemId)->delete();
            
            // Delete the item
            $item->delete();

            DB::commit();
            
            return response()->json(['success' => true, 'message' => 'Item deleted successfully']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => 'Error deleting item: ' . $e->getMessage()], 500);
        }
    }

    public function restockItem(Request $request, $itemId)
    {
        $request->validate([
            'purchase_unit' => 'required|string|max:255',
            'quantity_purchased' => 'required|integer|min:1',
            'total_purchase_cost' => 'required|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        
        try {
            $item = Item::findOrFail($itemId);
            $barId = $request->bar_id ?? auth()->user()->bar_id;
            
            // Get conversion factor from base unit
            $baseUnit = $item->baseUnit;
            $conversionFactor = $baseUnit ? $baseUnit->conversion_factor : 1;
            
            // Calculate cost per base unit
            $totalBaseUnits = $request->quantity_purchased * $conversionFactor;
            $calculatedBaseUnitCost = $totalBaseUnits > 0 ? $request->total_purchase_cost / $totalBaseUnits : 0;

            // Create purchase history record
            $purchaseHistory = $item->addPurchaseHistory([
                'purchase_unit' => $request->purchase_unit,
                'quantity_purchased' => $request->quantity_purchased,
                'total_purchase_cost' => $request->total_purchase_cost,
                'calculated_base_unit_cost' => $calculatedBaseUnitCost,
                'supplier' => $request->supplier,
                'reference_number' => $request->reference_number,
                'notes' => $request->notes,
                'purchase_date' => now()->format('Y-m-d'),
            ]);

            // Update item stock and metrics
            $item->update([
                'director_stock' => $item->director_stock + $totalBaseUnits,
                'lifetime_quantity_purchased' => $item->lifetime_quantity_purchased + $totalBaseUnits,
            ]);

            // Recalculate weighted average cost
            $item->updateWeightedAverageCost();

            // Create ledger entry for restock
            $item->addLedgerEntry([
                'bar_id' => $barId,
                'action_type' => 'purchase',
                'quantity' => $totalBaseUnits,
                'unit_cost' => $calculatedBaseUnitCost,
                'total_cost' => $request->total_purchase_cost,
                'balance_after' => $item->director_stock,
                'reference_type' => 'purchase_history',
                'reference_id' => $purchaseHistory->id,
                'transaction_date' => now(),
            ]);

            DB::commit();
            
            return response()->json(['success' => true, 'message' => 'Item restocked successfully']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => 'Error restocking item: ' . $e->getMessage()], 500);
        }
    }

    public function getItemLedger($itemId)
    {
        $item = Item::with(['ledger' => function($query) {
            $query->orderBy('transaction_date', 'desc')->with('bar');
        }])->findOrFail($itemId);

        return response()->json([
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category,
            ],
            'ledger' => $item->ledger->map(function($entry) {
                return [
                    'id' => $entry->id,
                    'action_type' => $entry->action_type,
                    'quantity' => $entry->quantity,
                    'unit_cost' => $entry->unit_cost,
                    'total_cost' => $entry->total_cost,
                    'balance_after' => $entry->balance_after,
                    'transaction_date' => $entry->transaction_date->format('Y-m-d H:i'),
                    'bar_name' => $entry->bar ? $entry->bar->name : 'Warehouse',
                    'notes' => $entry->notes,
                ];
            }),
        ]);
    }

    public function getStockHistory($itemId)
    {
        try {
            $selectedBarId = request('bar_id', Bar::listed()->orderBy('name')->value('id'));
            
            // Get stock history for this item and bar - include all bars for comparison
            $stockHistory = StockEntryItem::join('daily_stock_entries', 'stock_entry_items.stock_entry_id', '=', 'daily_stock_entries.id')
                ->join('bars', 'daily_stock_entries.bar_id', '=', 'bars.id')
                ->where('stock_entry_items.item_id', $itemId)
                ->orderBy('daily_stock_entries.date', 'desc')
                ->orderBy('daily_stock_entries.created_at', 'desc')
                ->select([
                    'daily_stock_entries.date',
                    'bars.name as bar_name',
                    'bars.id as bar_id',
                    'stock_entry_items.opening_stock',
                    'stock_entry_items.ordered_stock',
                    'stock_entry_items.total_stock',
                    'stock_entry_items.closing_stock',
                    'stock_entry_items.sold_quantity',
                    'stock_entry_items.sales_amount',
                    'stock_entry_items.price',
                    'stock_entry_items.purchase_price',
                    'stock_entry_items.expiry_date',
                    'daily_stock_entries.created_at'
                ])
                ->get();

            return response()->json($stockHistory);
        } catch (\Exception $e) {
            \Log::error('Stock history error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load stock history'], 500);
        }
    }

    public function createWarehouseRequest(Request $request)
    {
        $request->validate([
            'bar_id' => 'required|exists:bars,id',
            'items' => 'required|array',
            'items.*.warehouse_stock_id' => 'required|exists:warehouse_stocks,id',
            'items.*.quantity_requested' => 'required|integer|min:1',
            'items.*.unit_name' => 'nullable|string',
            'items.*.conversion_factor' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        
        try {
            $transferRequest = WarehouseTransferRequest::create([
                'bar_id' => $request->bar_id,
                'requested_by' => auth()->id(),
                'status' => 'pending',
                'notes' => $request->notes,
                'requested_at' => now(),
            ]);

            foreach ($request->items as $itemData) {
                $warehouseStock = \App\Models\WarehouseStock::findOrFail($itemData['warehouse_stock_id']);
                
                // Find or create the Item based on warehouse stock's item_name
                $item = \App\Models\Item::where('name', $warehouseStock->item_name)->first();
                if (!$item) {
                    // If item doesn't exist, create it
                    $item = \App\Models\Item::create([
                        'name' => $warehouseStock->item_name,
                        'category' => 'Imported',
                        'price' => $warehouseStock->selling_price ?? 0,
                        'director_stock' => 0,
                    ]);
                    \Log::info('Item created during warehouse transfer request', [
                        'item_id' => $item->id,
                        'item_name' => $item->name,
                    ]);
                }
                
                // Ensure product units exist for this item by copying from warehouse units
                $existingUnits = \App\Models\ProductUnit::where('item_id', $item->id)->count();
                if ($existingUnits == 0) {
                    $warehouseUnits = $warehouseStock->units()->get();
                    foreach ($warehouseUnits as $wunit) {
                        try {
                            \App\Models\ProductUnit::create([
                                'item_id' => $item->id,
                                'unit_name' => $wunit->unit_name,
                                'conversion_factor' => $wunit->conversion_factor ?? 1,
                                'is_base_unit' => $wunit->is_base_unit ?? false,
                            ]);
                        
                            // Create ProductUnitPrice for this unit using per-bar warehouse price if available
                            try {
                                $barPrice = $wunit->barPrices()->where('bar_id', $transferRequest->bar_id)->first();
                                $sellingPrice = $barPrice ? $barPrice->selling_price : ($warehouseStock->selling_price ? $warehouseStock->selling_price * ($wunit->conversion_factor ?? 1) : null);
                                if ($sellingPrice !== null) {
                                    \App\Models\ProductUnitPrice::create([
                                        'item_id' => $item->id,
                                        'unit_name' => $wunit->unit_name,
                                        'selling_price' => $sellingPrice,
                                        'purchase_price' => $wunit->purchase_price ?? ($warehouseStock->purchase_price * ($wunit->conversion_factor ?? 1)),
                                    ]);
                                }
                            } catch (\Exception $e) {
                                \Log::warning('Failed to create product unit price', ['item_id' => $item->id, 'unit' => $wunit->unit_name, 'error' => $e->getMessage()]);
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Failed to create product unit', ['item_id' => $item->id, 'unit' => $wunit->unit_name, 'error' => $e->getMessage()]);
                        }
                    }
                    \Log::info('Product units populated from warehouse units', ['item_id' => $item->id, 'copied' => $warehouseUnits->count()]);
                }
                
                WarehouseTransferRequestItem::create([
                    'warehouse_transfer_request_id' => $transferRequest->id,
                    'warehouse_stock_id' => $itemData['warehouse_stock_id'],
                    'item_id' => $item->id,
                    'quantity_requested' => $itemData['quantity_requested'],
                    'quantity_approved' => 0,
                    'unit_name' => $itemData['unit_name'] ?? null,
                    'conversion_factor' => $itemData['conversion_factor'] ?? 1,
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            DB::commit();
            
            return response()->json(['success' => true, 'message' => 'Warehouse transfer request created successfully']);
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error creating warehouse request', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json(['success' => false, 'message' => 'Error creating warehouse request: ' . $e->getMessage()], 500);
        }
    }
}
