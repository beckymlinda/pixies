<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
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
use App\Models\WarehouseStock;
use App\Models\WarehouseUnit;
use App\Models\WarehouseUnitBarPrice;
use App\Models\WarehouseTransferRequest;
use App\Models\WarehouseTransferRequestItem;
use App\Models\ActivityLog;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StockEntryController extends Controller
{

    public function index()
    {
        $user = Auth::user();
        
        if ($user->isSeller()) {
            $entries = Sale::where('bar_id', $user->bar_id)
                ->with(['bar', 'stockEntryItems.item'])
                ->orderBy('date', 'desc')
                ->paginate(10);

            $todayEntry = Sale::where('bar_id', $user->bar_id)
                ->where('status', 'pending')
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->first();
        } else {
            $entries = Sale::with(['bar', 'user', 'stockEntryItems.item'])
                ->orderBy('date', 'desc')
                ->paginate(10);
            $todayEntry = null;
        }

        return view('stock-entries.index', compact('entries', 'todayEntry'));
    }

    /**
     * Seller shortcut: always load the stock entries index so the seller
     * can choose to open a new stock sheet or continue an old / pending one.
     * (Previously this redirected straight to the latest pending entry's edit
     * page, which returns 403 when that entry is not from today.)
     */
    public function sell()
    {
        $user = Auth::user();

        if (!$user->isSeller()) {
            return redirect()->route('stock-entries.index');
        }

        if (!$user->bar_id) {
            return redirect()->route('seller.dashboard')
                ->with('error', 'You must be assigned to a bar to sell.');
        }

        return redirect()->route('stock-entries.index');
    }

    public function create(Request $request)
    {
        $user = Auth::user();

        // Creating a stock sheet always requires a bar context. A seller
        // without a bar, or a director/manager without an assigned bar,
        // is redirected instead of crashing on a null bar_id later.
        if (!$user->bar_id) {
            return redirect()->route('stock-entries.index')
                ->with('error', 'You must be assigned to a bar to create a stock sheet.');
        }

        // If there is an active pending sheet, continue it unless a new stock sheet is requested.
        $pendingEntry = Sale::where('bar_id', $user->bar_id)
            ->where('status', 'pending')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $today = now()->format('Y-m-d');
        $todayEntry = Sale::where('date', $today)
            ->where('bar_id', $user->bar_id)
            ->first();

        if (! $request->query('new_sheet') && $pendingEntry) {
            return redirect()->route('stock-entries.edit', $pendingEntry);
        }

        if ($todayEntry) {
            return redirect()->route('stock-entries.edit', $todayEntry)
                ->with('info', 'Today\'s stock sheet already exists. Continue the current sheet or verify it before creating another.');
        }

        // Get all items with their units in database insertion order
        $items = Item::with('productUnits')->where('is_hidden', false)->orderBy('id')->get();
        
        // Get approved order requests sum for this bar and date
        $today = now()->format('Y-m-d');
        $todayApprovedOrders = $this->getApprovedOrderQuantities($user->bar_id, $today);
        $pendingOrderItemIds = $this->getPendingOrderItemIds($user->bar_id, $today);

        $previousClosingStock = $this->getLatestClosingStockForBar($user->bar_id);

        $todayEntry = Sale::where('date', $today)
            ->where('bar_id', $user->bar_id)
            ->with('stockEntryItems')
            ->first();
        $todayStockItems = $todayEntry
            ? $todayEntry->stockEntryItems->keyBy('item_id')
            : collect();
        
        // Prepare items data with latest stock and units
        $itemsData = $items->map(function($item) use ($user, $todayApprovedOrders, $previousClosingStock, $todayStockItems, $pendingOrderItemIds) {
            // Get bar-specific price
            $barItemPrice = BarItemPrice::where('bar_id', $user->bar_id)
                ->where('item_id', $item->id)
                ->first();
            
            $visibleUnits = $this->resolveSellerProductUnits($item, $user->bar);
            $this->attachBarUnitPrices($item, $user->bar_id, $visibleUnits);

            $baseUnitCost = $item->average_unit_cost ?? 0;
            if ($baseUnitCost <= 0) {
                $baseUnit = $item->productUnits->firstWhere('is_base_unit', true);
                if ($baseUnit && $baseUnit->price) {
                    $baseUnitCost = $baseUnit->price->purchase_price ?? 0;
                }
            }
            
            $stockItem = $todayStockItems->get($item->id);
            $previousClosing = (float) ($previousClosingStock[$item->id] ?? 0);
            $approvedQty = (float) ($todayApprovedOrders[$item->id] ?? 0);
            $figures = $this->resolveSellerStockFigures($item, $stockItem, $previousClosing, $approvedQty);
            $figures = $this->enrichSellerItemStockDisplay($item, $user->bar, $figures);
            
            return [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category,
                'price' => $barItemPrice ? $barItemPrice->price : $item->price,
                'purchase_price' => $baseUnitCost,
                'opening_stock' => $figures['opening_stock'],
                'ordered_stock' => $figures['ordered_stock'],
                'closing_stock' => $figures['closing_stock'],
                'available_stock' => $figures['available_stock'],
                'opening_stock_display' => $figures['opening_stock_display'],
                'ordered_stock_display' => $figures['ordered_stock_display'],
                'closing_stock_display' => $figures['closing_stock_display'],
                'available_stock_display' => $figures['available_stock_display'],
                'stock_unit' => $figures['stock_unit'],
                'can_sell' => $figures['available_stock'] > 0,
                'has_pending_request' => in_array($item->id, $pendingOrderItemIds, true),
                'product_units' => $visibleUnits,
                'bar_item_price' => $barItemPrice ? $barItemPrice->price : null,
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
            $stockEntry = Sale::firstOrCreate([
                'bar_id' => $user->bar_id,
                'date' => $entryDate,
            ], [
                'user_id' => $user->id,
            ]);

            \Log::info('Daily Stock Entry created with ID: ' . $stockEntry->id);

            // Fetch approved orders for this bar and date
            $approvedOrders = $this->getApprovedOrderQuantities($user->bar_id, $entryDate);

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
                $stockItem = StockEntryItem::where('stock_entry_id', $stockEntry->id)
                    ->where('item_id', $itemId)
                    ->first();
                $approvedOrderedQty = $this->resolveOrderedQuantity($itemId, $stockItem, $approvedOrders);
                $unitName = $itemData['unit_name'] ?? null;
                
                $salesInSelectedUnit = (float) ($itemData['sales'] ?? 0);
                $salesQuantity = $salesInSelectedUnit;
                if ($unitName && $salesInSelectedUnit > 0) {
                    $salesQuantity = $inventoryService->convertToBaseUnits($itemId, $salesInSelectedUnit, $unitName);
                }
                
                // Get correct unit price if unit_name is provided
                $unitPrice = $itemData['price'];
                if ($unitName) {
                    $unitPriceModel = $inventoryService->getUnitPrice($itemId, $unitName);
                    if ($unitPriceModel) {
                        $unitPrice = $unitPriceModel->selling_price;
                    }
                }

                $previousSold = $stockItem ? (float) $stockItem->sold_quantity : 0;
                $available = (float) $itemData['opening_stock'] + $approvedOrderedQty - $previousSold;
                $this->assertSufficientStock($inventoryService, $itemId, $salesInSelectedUnit, $unitName, $available);
                
                try {
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
                            'sales_amount' => $stockItem->sales_amount + ($salesInSelectedUnit * $unitPrice),
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
                            'sales_amount' => $salesInSelectedUnit * $unitPrice,
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
                return redirect()->route('stock-entries.edit', $stockEntry)
                    ->with('success', 'Sales saved.');
            }
            
            return redirect()->route('stock-entries.show', $stockEntry)
                ->with('success', 'Stock entry saved successfully!');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()
                ->with('error', 'Error saving stock entry: ' . $e->getMessage());
        }
    }

    public function show(Sale $stockEntry, Request $request)
    {
        $user = Auth::user();
        
        // Sellers can only view entries for their own bar.
        if ($user->isSeller() && $stockEntry->bar_id !== $user->bar_id) {
            abort(403);
        }

        $stockEntry->load(['bar', 'user', 'stockEntryItems.item', 'expenses', 'payments']);

        // Get all items with pagination and units
        $allItems = Item::with('productUnits.price')->orderBy('id')->paginate(10, ['*'], 'page', $request->get('page', 1));

        // Carry forward the closing stock from the most recent prior stock
        // entry (any date before this one) so the displayed opening stock
        // matches the director stock overview instead of only the previous
        // calendar day, which can be empty when entries are a day apart.
        $entryDateStr = $stockEntry->date->format('Y-m-d');
        $previousClosingStock = $this->getPreviousClosingStockForBar(
            $stockEntry->bar_id,
            $entryDateStr,
            $stockEntry->id
        );
        
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
        
        // Merge existing data with all items, always showing price and the
        // most recent prior closing stock.
        $itemsData = $allItems->map(function($item) use ($existingItems, $previousClosingStock, $barPrices, $approvedOrders, $stockEntry) {
            $stockItem = $existingItems->get($item->id);

            $visibleUnits = $this->resolveSellerProductUnits($item, $stockEntry->bar);
            $this->attachBarUnitPrices($item, $stockEntry->bar_id, $visibleUnits);
            
            // Use bar-specific price first, then stock snapshot price, then item master price.
            $price = $barPrices[$item->id] ?? ($stockItem ? $stockItem->price : ($item->price ?? 0));
            
            // Use the most recent prior closing stock as opening stock,
            // falling back to the stock item's own opening, then 0.
            $openingStock = $previousClosingStock[$item->id] ?? ($stockItem ? $stockItem->opening_stock : 0);
            
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
                'has_previous_data' => isset($previousClosingStock[$item->id]),
                'stock_entry_item' => $stockItem,
                'product_units' => $visibleUnits,
            ];
        });

        return view('stock-entries.show', compact('stockEntry', 'itemsData', 'allItems'));
    }

    public function edit(Sale $stockEntry)
    {
        $user = Auth::user();
        
        // Sellers can edit only today's entry for their own bar.
        if ($user->isSeller() && ($stockEntry->bar_id !== $user->bar_id || $stockEntry->date->format('Y-m-d') !== now()->format('Y-m-d'))) {
            abort(403);
        }

        $stockEntry->load(['bar', 'user', 'stockEntryItems.item', 'expenses', 'payments']);

        // Get all items first (without pagination) to prepare data
        $allItems = Item::with('productUnits')->where('is_hidden', false)->orderBy('id')->get();

        // Carry forward the closing stock from the most recent prior stock
        // entry (any date before this one) so the seller's opening stock and
        // "can sell" flag match the director stock overview, which also uses
        // the latest entry regardless of how many days ago it was recorded.
        $entryDateStr = $stockEntry->date->format('Y-m-d');
        $previousClosingStock = $this->getPreviousClosingStockForBar(
            $stockEntry->bar_id,
            $entryDateStr,
            $stockEntry->id
        );

        // Get existing stock entry items data
        $existingItems = $stockEntry->stockEntryItems->keyBy('item_id');
        $barPrices = BarItemPrice::where('bar_id', $stockEntry->bar_id)->pluck('price', 'item_id');
        
        // Get approved order requests sum for this date
        $entryDate = $stockEntry->date->format('Y-m-d');
        $approvedOrders = $this->getApprovedOrderQuantities($stockEntry->bar_id, $entryDate);
        $pendingOrderItemIds = $this->getPendingOrderItemIds($stockEntry->bar_id, $entryDate);
        
        // Merge existing data with all items, always showing price and the
        // most recent prior closing stock.
        $itemsData = $allItems->map(function($item) use ($existingItems, $previousClosingStock, $barPrices, $approvedOrders, $pendingOrderItemIds, $stockEntry) {
            $stockItem = $existingItems->get($item->id);
            
            // Use bar-specific price first, then stock snapshot price, then item master price.
            $price = $barPrices[$item->id] ?? ($stockItem ? $stockItem->price : ($item->price ?? 0));
            
            $visibleUnits = $this->resolveSellerProductUnits($item, $stockEntry->bar);
            $this->attachBarUnitPrices($item, $stockEntry->bar_id, $visibleUnits);

            $previousClosing = (float) ($previousClosingStock[$item->id] ?? 0);
            $approvedQty = (float) ($approvedOrders[$item->id] ?? 0);
            $figures = $this->resolveSellerStockFigures($item, $stockItem, $previousClosing, $approvedQty);
            $figures = $this->enrichSellerItemStockDisplay($item, $stockEntry->bar, $figures);
            $salesAmount = $stockItem ? (float) $stockItem->sales_amount : ($figures['sold_quantity'] * $price);
            
            return [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category,
                'price' => $price,
                'opening_stock' => $figures['opening_stock'],
                'ordered_stock' => $figures['ordered_stock'],
                'total_stock' => $figures['opening_stock'] + $figures['ordered_stock'],
                'closing_stock' => $figures['closing_stock'],
                'sold_quantity' => $figures['sold_quantity'],
                'opening_stock_display' => $figures['opening_stock_display'],
                'ordered_stock_display' => $figures['ordered_stock_display'],
                'closing_stock_display' => $figures['closing_stock_display'],
                'available_stock_display' => $figures['available_stock_display'],
                'sold_quantity_display' => $figures['sold_quantity_display'],
                'stock_unit' => $figures['stock_unit'],
                'product_units' => $visibleUnits,
                'sales_amount' => $salesAmount,
                'available_stock' => $figures['available_stock'],
                'can_sell' => $figures['available_stock'] > 0,
                'has_pending_request' => in_array($item->id, $pendingOrderItemIds, true),
                'has_data' => $stockItem ? true : false,
                'has_previous_data' => isset($previousClosingStock[$item->id]),
            ];
        });
        
        $paginatedItems = $itemsData; // Pass all items directly

        return view('stock-entries.edit', compact('stockEntry', 'paginatedItems'));
    }

    public function update(Request $request, Sale $stockEntry)
    {
        $user = Auth::user();
        
        // Sellers can edit only today's entry for their own bar.
        if ($user->isSeller() && ($stockEntry->bar_id !== $user->bar_id || $stockEntry->date->format('Y-m-d') !== now()->format('Y-m-d'))) {
            abort(403);
        }

        // Validation rules
        // Per-row numeric fields are nullable on purpose: on shared hosting the
        // POST can be truncated by max_input_vars, dropping the hidden fields of
        // the last rows. Rows without sales are skipped below anyway, so a
        // truncated row must never block saving the rows that do have sales.
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.opening_stock' => 'nullable|numeric|min:0',
            'items.*.orders' => 'nullable|numeric|min:0',
            'items.*.sales' => 'nullable|numeric|min:0',
            'items.*.closing_stock' => 'nullable|numeric|min:0',
            'items.*.sales_amount' => 'nullable|numeric|min:0',
            'items.*.unit_name' => 'sometimes|string|nullable', // For multi-unit support
            'items.*.clear_sales' => 'sometimes|in:0,1', // "x" button flag -> wipe this item's saved sales
        ], [
            'items.required' => 'You must have at least one stock item',
            'items.min' => 'You must have at least one stock item',
            'items.*.item_id.required' => 'Please select an item for each row',
            'items.*.item_id.exists' => 'Selected item is invalid',
        ]);

        DB::beginTransaction();
        
        try {
            $inventoryService = new InventoryService();
            
            // Fetch approved orders for this bar and date
            $entryDate = $stockEntry->date->format('Y-m-d');
            $approvedOrders = $this->getApprovedOrderQuantities($stockEntry->bar_id, $entryDate);

            // Update or Create stock entry items (using updateOrCreate to avoid deleting items on other pages)
            foreach ($validated['items'] as $itemData) {
                $itemId = $itemData['item_id'];
                $existingStockItem = StockEntryItem::where('stock_entry_id', $stockEntry->id)
                    ->where('item_id', $itemId)
                    ->first();
                $approvedOrderedQty = $this->resolveOrderedQuantity($itemId, $existingStockItem, $approvedOrders);
                $unitName = $itemData['unit_name'] ?? null;
                
                $salesInSelectedUnit = (float) ($itemData['sales'] ?? 0);
                $newSalesQuantity = $salesInSelectedUnit;
                if ($unitName && $salesInSelectedUnit > 0) {
                    $newSalesQuantity = $inventoryService->convertToBaseUnits($itemId, $salesInSelectedUnit, $unitName);
                }

                // "clear_sales" is set when the seller pressed the (x) button on a row:
                // it removes ALL of this item's sales from the shift (quantity + money),
                // putting the stock back to the full available amount.
                $clearSales = filter_var($itemData['clear_sales'] ?? false, FILTER_VALIDATE_BOOLEAN);

                // Skip untouched rows (no new sales to record and nothing to clear).
                if (!$clearSales && $newSalesQuantity <= 0) {
                    continue;
                }

                $previousSold = $existingStockItem ? (float) $existingStockItem->sold_quantity : 0;
                $available = (float) ($itemData['opening_stock'] ?? 0) + $approvedOrderedQty - $previousSold;

                // No stock is being sold when clearing, so skip the stock check.
                if (!$clearSales) {
                    $this->assertSufficientStock($inventoryService, $itemId, $salesInSelectedUnit, $unitName, $available);
                }
                
                // Get correct unit price if unit_name is provided
                $unitPrice = $itemData['price'] ?? ($existingStockItem->price ?? 0);
                if ($unitName) {
                    $unitPriceModel = $inventoryService->getUnitPrice($itemId, $unitName);
                    if ($unitPriceModel) {
                        $unitPrice = $unitPriceModel->selling_price;
                    }
                }

                $openingStock = (float) ($itemData['opening_stock'] ?? 0);
                $totalStock = $openingStock + $approvedOrderedQty;

                if ($clearSales) {
                    // Remove this item's sales entirely: sold 0, money 0, and the full
                    // opening + ordered quantity is restored to closing stock.
                    $totalSoldQuantity = 0;
                    $closingStock = $totalStock;
                    $itemSalesAmount = 0;
                } else {
                    $totalSoldQuantity = $previousSold + $newSalesQuantity;
                    $closingStock = isset($itemData['closing_stock'])
                        ? (float) $itemData['closing_stock']
                        : max(0, $totalStock - $totalSoldQuantity);
                    $previousSalesAmount = $existingStockItem ? (float) $existingStockItem->sales_amount : 0;
                    $itemSalesAmount = $previousSalesAmount + ($salesInSelectedUnit * $unitPrice);
                }

                $stockItem = StockEntryItem::updateOrCreate(
                    [
                        'stock_entry_id' => $stockEntry->id,
                        'item_id' => $itemId,
                    ],
                    [
                        'opening_stock' => $openingStock,
                        'ordered_stock' => $approvedOrderedQty,
                        'total_stock' => $totalStock,
                        'closing_stock' => $closingStock,
                        'sold_quantity' => $totalSoldQuantity,
                        'sales_amount' => $itemSalesAmount,
                        'price' => $unitPrice,
                        'purchase_price' => $itemData['purchase_price'] ?? ($existingStockItem->purchase_price ?? 0),
                        'expiry_date' => !empty($itemData['expiry_date']) ? $itemData['expiry_date'] : ($existingStockItem->expiry_date ?? null),
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
                            'counted' => (int) $stockItem->sold_quantity,
                        ]
                    );
                }

                // Sync the closing stock back to the Item's unified director_stock
                $item = Item::find($itemId);
                if ($item) {
                    $item->update(['director_stock' => $stockItem->closing_stock]);
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
                return redirect()->route('stock-entries.edit', $stockEntry)
                    ->with('success', 'Sales saved.');
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

    public function destroy(Sale $stockEntry)
    {
        $user = Auth::user();

        if ($user->isSeller()) {
            if ($stockEntry->bar_id !== $user->bar_id || $stockEntry->date->format('Y-m-d') !== now()->format('Y-m-d')) {
                abort(403);
            }
        } elseif (!$user->isDirector() && !$user->isManager()) {
            abort(403);
        }

        DB::beginTransaction();

        try {
            $stockEntry->delete();
            DB::commit();

            return redirect()->route('stock-entries.index')
                ->with('success', 'Sale deleted successfully.');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Error deleting sale: ' . $e->getMessage());
        }
    }

    // Director-specific methods
    public function directorIndex()
    {
        $user = Auth::user();
        
        // Directors can see all stock entries
        $entries = Sale::with(['bar', 'user', 'stockEntryItems.item'])
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
        $search = trim($request->query('search', ''));
        $bars = Bar::listed()->orderBy('name')->get();

        $stockEntries = StockEntryItem::join('sales', 'stock_entry_items.stock_entry_id', '=', 'sales.id')
            ->when($selectedBarId, function ($query) use ($selectedBarId) {
                $query->where('sales.bar_id', $selectedBarId);
            })
            ->orderBy('sales.date', 'desc')
            ->orderBy('stock_entry_items.updated_at', 'desc')
            ->orderBy('stock_entry_items.id', 'desc')
            ->get([
                'stock_entry_items.*',
                'sales.bar_id',
                'sales.date as stock_date',
            ]);

        $latestStocks = $stockEntries->groupBy(function ($row) {
            return $row->bar_id . '_' . $row->item_id;
        })->map->first()->values();

        $itemIds = $latestStocks->pluck('item_id')->unique()->all();
        $barIds = $latestStocks->pluck('bar_id')->unique()->all();

        $items = Item::with(['productUnits'])->where('is_hidden', false)->whereIn('id', $itemIds)->get()->keyBy('id');
        $barNames = Bar::whereIn('id', $barIds)->pluck('name', 'id');

        $productUnitPrices = ProductUnitPrice::whereIn('item_id', $itemIds)
            ->get()
            ->groupBy('item_id')
            ->map(fn ($group) => $group->keyBy('unit_name'));

        // Drop rows whose item has been hidden (deleted), so it no longer appears
        // in the director stock overview.
        $latestStocks = $latestStocks->filter(function ($stock) use ($items) {
            return $items->has($stock->item_id);
        })->values();

        $barItemPrices = BarItemPrice::whereIn('bar_id', $barIds)
            ->whereIn('item_id', $itemIds)
            ->get()
            ->groupBy('bar_id')
            ->map(function ($group) {
                return $group->keyBy('item_id');
            });

        $stockRows = $latestStocks->map(function ($stock) use ($items, $barNames, $barItemPrices, $productUnitPrices) {
            $item = $items->get($stock->item_id);
            $sellingPrice = $barItemPrices->get($stock->bar_id)?->get($stock->item_id)?->price ?? ($item?->price ?? 0);
            $purchasePrice = $stock->purchase_price > 0
                ? $stock->purchase_price
                : ($item?->average_unit_cost ?? 0);
            $baseUnit = $item?->productUnits->firstWhere('is_base_unit', true);
            $unit = $baseUnit?->unit_name
                ?? $item?->productUnits->first()?->unit_name
                ?? 'Bottle';

            $markupPercentage = $purchasePrice > 0
                ? (($sellingPrice - $purchasePrice) / $purchasePrice) * 100
                : 0;

            $itemPrices = $productUnitPrices->get($item?->id);
            $productUnits = $item?->productUnits->map(function ($u) use ($itemPrices) {
                $price = $itemPrices?->get($u->unit_name);

                return [
                    'unit_name' => $u->unit_name,
                    'selling_price' => (float) ($price?->selling_price ?? 0),
                    'purchase_price' => (float) ($price?->purchase_price ?? 0),
                    'conversion_factor' => (int) $u->conversion_factor,
                    'is_base_unit' => (bool) $u->is_base_unit,
                ];
            })->values()->all() ?? [];

            return [
                'bar_id' => $stock->bar_id,
                'item_id' => $stock->item_id,
                'bar_name' => $barNames->get($stock->bar_id, 'Unknown'),
                'item_name' => $item?->name ?? 'Unknown',
                'category' => $item?->category ?? 'Unknown',
                'stock' => $stock->closing_stock,
                'price' => $purchasePrice,
                'unit' => $unit,
                'selling_price' => $sellingPrice,
                'markup_percentage' => round($markupPercentage, 2),
                'last_updated' => $stock->stock_date,
                'product_units' => $productUnits,
            ];
        })->sortBy([['bar_name', 'asc'], ['item_name', 'asc']])->values();

        if (!empty($search)) {
            $stockRows = $stockRows->filter(function ($row) use ($search) {
                return stripos($row['item_name'], $search) !== false ||
                       stripos($row['category'], $search) !== false ||
                       stripos($row['bar_name'], $search) !== false;
            })->values();
        }

        return view('stock.index', compact('bars', 'stockRows', 'selectedBarId', 'search'));
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
            $stockEntry = Sale::where('date', $date)
                ->where('bar_id', $barId)
                ->first();

            if ($stockEntry) {
                $stockEntry->updated_at = now();
                $stockEntry->save();
            } else {
                $stockEntry = Sale::create([
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
            
            return redirect()->route('stock.index')
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
            $currentStock = StockEntryItem::join('sales', 'stock_entry_items.stock_entry_id', '=', 'sales.id')
                ->where('sales.bar_id', $barId)
                ->where('stock_entry_items.item_id', $item->id)
                ->orderBy('sales.date', 'desc')
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
            $stockHistory = StockEntryItem::join('sales', 'stock_entry_items.stock_entry_id', '=', 'sales.id')
                ->join('bars', 'sales.bar_id', '=', 'bars.id')
                ->where('stock_entry_items.item_id', $itemId)
                ->orderBy('sales.date', 'desc')
                ->orderBy('sales.created_at', 'desc')
                ->select([
                    'sales.date',
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
                    'sales.created_at'
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
            $bar = Bar::findOrFail($request->bar_id);

            $transferRequest = WarehouseTransferRequest::create([
                'bar_id' => $request->bar_id,
                'requested_by' => auth()->id(),
                'status' => 'pending',
                'notes' => $request->notes,
                'requested_at' => now(),
            ]);

            foreach ($request->items as $itemData) {
                $warehouseStock = \App\Models\WarehouseStock::findOrFail($itemData['warehouse_stock_id']);

                if (! empty($itemData['unit_name']) && ! $bar->allowsWarehouseTransferUnit($itemData['unit_name'])) {
                    throw new \InvalidArgumentException(
                        "Unit \"{$itemData['unit_name']}\" is not allowed for {$bar->name}. Shot units are only available for Bar B."
                    );
                }

                $availability = $warehouseStock->getRequestAvailabilityForBar($bar);
                if ($availability['is_out_of_stock']) {
                    throw new \InvalidArgumentException(
                        "{$warehouseStock->item_name} is out of stock for {$bar->name}."
                    );
                }

                $conversionFactor = (int) ($itemData['conversion_factor'] ?? 1);
                $requestedBase = (int) $itemData['quantity_requested'] * max(1, $conversionFactor);
                if ($requestedBase > $availability['available_quantity_base']) {
                    throw new \InvalidArgumentException(
                        "Not enough warehouse stock for {$warehouseStock->item_name}. Available: {$availability['available_quantity']} {$availability['stock_unit']}."
                    );
                }
                
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
private function getApprovedOrderQuantities(int $barId, string $date): array
{
    $rows = DB::table('order_request_items')
        ->join('order_requests', 'order_request_items.order_request_id', '=', 'order_requests.id')
        ->where('order_requests.bar_id', $barId)
        ->where('order_requests.date', $date)
        ->whereIn('order_requests.status', ['approved', 'partially_approved'])
        ->groupBy('order_request_items.item_id')
        ->select(
            'order_request_items.item_id',
            DB::raw('SUM(order_request_items.approved_quantity) as total_approved')
        )
        ->get();

    $inventoryService = new InventoryService();
    $quantities = [];

    foreach ($rows as $row) {
        $quantities[$row->item_id] = $inventoryService->convertSellerOrderQuantityToBaseUnits(
            (int) $row->item_id,
            (float) $row->total_approved
        );
    }

    return $quantities;
}

private function getPendingOrderItemIds(int $barId, string $date): array
{
    return DB::table('order_request_items')
        ->join('order_requests', 'order_request_items.order_request_id', '=', 'order_requests.id')
        ->where('order_requests.bar_id', $barId)
        ->where('order_requests.date', $date)
        ->where('order_requests.status', 'pending')
        ->where('order_request_items.requested_quantity', '>', 0)
        ->pluck('order_request_items.item_id')
        ->unique()
        ->values()
        ->all();
}

private function resolveSellerStockFigures(
    Item $item,
    ?StockEntryItem $stockItem,
    float $previousClosing,
    float $approvedOrderQty
): array {
    if ($stockItem) {
        $figures = [
            'opening_stock' => (float) $stockItem->opening_stock,
            'ordered_stock' => (float) $stockItem->ordered_stock,
            'sold_quantity' => (float) $stockItem->sold_quantity,
        ];

        $figures = $this->upgradeLegacyShotStockFigures(
            $item,
            $figures,
            $approvedOrderQty
        );

        $figures['ordered_stock'] = max(
            $figures['ordered_stock'],
            $approvedOrderQty
        );

        $opening = $figures['opening_stock'];
        $ordered = $figures['ordered_stock'];
        $sold = $figures['sold_quantity'];
        $closing = max(0, $opening + $ordered - $sold);

        return [
            'opening_stock' => $opening,
            'ordered_stock' => $ordered,
            'closing_stock' => $closing,
            'sold_quantity' => $sold,
            'available_stock' => max(0, $closing),
        ];
    }

    $opening = max(0, $previousClosing);
    $ordered = $approvedOrderQty;
    $sold = 0.0;
    $closing = max(0, $opening + $ordered - $sold);

    return [
        'opening_stock' => $opening,
        'ordered_stock' => $ordered,
        'closing_stock' => $closing,
        'sold_quantity' => $sold,
        'available_stock' => max(0, $opening + $ordered),
    ];
}

private function getLatestClosingStockForBar(int $barId): array
{
    return $this->latestClosingPerItemForBar($barId);
}

/**
 * Closing stock carried forward from the entries recorded BEFORE the given
 * entry. This keeps the seller's opening stock in sync with the director stock
 * overview (which always shows the latest recorded value per item regardless of
 * date) instead of only looking at the previous calendar day, which can be empty
 * when entries are a day or more apart.
 */
private function getPreviousClosingStockForBar(int $barId, string $currentDate, ?int $currentId = null): array
{
    return $this->latestClosingPerItemForBar($barId, $currentDate, $currentId);
}

/**
 * Latest closing_stock PER ITEM across all of a bar's stock entries.
 *
 * This mirrors the director Stock Overview (stockOverview), which groups every
 * recorded stock_entry_item by bar + item and keeps the most recent one no
 * matter how far back it was recorded.
 *
 * The previous implementation only inspected the single most-recent Sale entry.
 * Because a daily entry stores rows ONLY for the items that had activity that
 * day (untouched rows are skipped on save), every item missing from that one
 * entry was carried over as 0 on the seller sheet even though the director still
 * showed stock for it - the exact "seller sees 0 for almost everything, director
 * sees full stock" mismatch. Scanning per item across all entries fixes it.
 *
 * When $beforeDate (and optionally $beforeId) is provided, only entries strictly
 * prior to that entry are considered.
 *
 * @return array<int, float> map of item_id => latest recorded closing_stock
 */
private function latestClosingPerItemForBar(int $barId, ?string $beforeDate = null, ?int $beforeId = null): array
{
    $query = StockEntryItem::query()
        ->join('sales', 'stock_entry_items.stock_entry_id', '=', 'sales.id')
        ->where('sales.bar_id', $barId);

    if ($beforeDate !== null) {
        $query->where(function ($q) use ($beforeDate, $beforeId) {
            $q->where('sales.date', '<', $beforeDate);
            if ($beforeId) {
                $q->orWhere(function ($q2) use ($beforeDate, $beforeId) {
                    $q2->where('sales.date', $beforeDate)
                        ->where('sales.id', '<', $beforeId);
                });
            }
        });
    }

    $rows = $query
        ->orderBy('sales.date', 'desc')
        ->orderBy('stock_entry_items.updated_at', 'desc')
        ->orderBy('stock_entry_items.id', 'desc')
        ->get(['stock_entry_items.item_id', 'stock_entry_items.closing_stock']);

    $latestPerItem = [];
    foreach ($rows as $row) {
        // Rows come back newest-first, so the first time an item appears is its
        // latest recorded closing stock. Older rows for the same item are
        // ignored, matching the director overview's groupBy()->map->first().
        if (! array_key_exists($row->item_id, $latestPerItem)) {
            $latestPerItem[$row->item_id] = (float) $row->closing_stock;
        }
    }

    return $latestPerItem;
}

private function upgradeLegacyShotStockFigures(
    Item $item,
    array $figures,
    float $approvedBaseQty
): array {
    $inventoryService = new InventoryService();

    $baseUnit = $inventoryService->getBaseUnit($item->id);

    if (! $baseUnit || $baseUnit->unit_name !== 'Shot') {
        return $figures;
    }

    $factor = $inventoryService->resolveBottleConversionFactor($item->id);

    if ($factor <= 1) {
        return $figures;
    }

    $dbOrdered = (float) ($figures['ordered_stock'] ?? 0);
    $dbOpening = (float) ($figures['opening_stock'] ?? 0);

    $looksLikeBottleStorage = $factor > 1
        && $dbOrdered > 0
        && $dbOrdered < $factor
        && (
            ($approvedBaseQty > 0
                && abs($approvedBaseQty - ($dbOrdered * $factor)) < 0.01)
            || ($dbOpening > 0 && $dbOpening < $factor)
        );

    if (! $looksLikeBottleStorage) {
        if ($approvedBaseQty > $dbOrdered) {
            $figures['ordered_stock'] = $approvedBaseQty;

            $figures['closing_stock'] = max(
                0,
                $figures['opening_stock']
                    + $approvedBaseQty
                    - ($figures['sold_quantity'] ?? 0)
            );

            $figures['available_stock'] = max(
                0,
                $figures['closing_stock']
            );
        }

        return $figures;
    }

    foreach (
        ['opening_stock', 'ordered_stock', 'closing_stock', 'sold_quantity']
        as $key
    ) {
        $value = (float) ($figures[$key] ?? 0);

        if ($value > 0 && $value < $factor) {
            $figures[$key] = $value * $factor;
        }
    }

    $figures['available_stock'] = max(
        0,
        $figures['opening_stock']
            + $figures['ordered_stock']
            - ($figures['sold_quantity'] ?? 0)
    );

    $figures['closing_stock'] = $figures['available_stock'];

    return $figures;
}
    private function resolveOrderedQuantity(int $itemId, ?StockEntryItem $stockItem, array $approvedOrders): float
    {
        $fromOrders = (float) ($approvedOrders[$itemId] ?? 0);
        $fromEntry = $stockItem ? (float) $stockItem->ordered_stock : 0;

        return max($fromOrders, $fromEntry);
    }

    private function resolveSellerProductUnits(Item $item, Bar $bar)
    {
        $allowed = ['Bottle', 'Shot', 'bottle', 'shot'];
        $units = $item->productUnits->filter(fn ($unit) => in_array($unit->unit_name, $allowed, true));

        // If no units found, create default bottle unit
        if ($units->isEmpty()) {
            $defaultUnit = new \App\Models\ProductUnit([
                'unit_name' => 'Bottle',
                'conversion_factor' => 1,
                'is_base_unit' => true,
            ]);
            $defaultUnit->price = (object) ['selling_price' => $item->price];
            return collect([$defaultUnit]);
        }

        // Always show all available units for the item, prioritizing base unit
        return $units->sortByDesc(function ($unit) {
            return $unit->is_base_unit ? 1 : 0;
        })->values();
    }

    private function attachBarUnitPrices(Item $item, int $barId, $units): void
    {
        // Cache bar-level price as a fallback (legacy single-price override)
        $barItemPrice = BarItemPrice::where('bar_id', $barId)
            ->where('item_id', $item->id)
            ->first();

        // Pre-load all ProductUnitPrice records for this item so we don't
        // hit the database once per unit inside the loop.
        $unitPrices = ProductUnitPrice::where('item_id', $item->id)
            ->get()
            ->keyBy(fn ($p) => strtolower($p->unit_name));

        foreach ($units as $unit) {
            $price = null;
            $unitKey = strtolower($unit->unit_name);

            // Priority 1: Per-unit price from ProductUnitPrice (most specific —
            // this is what the director sets when defining each selling unit,
            // so it must win to show the correct price per Bottle vs Shot).
            if ($unitPrices->has($unitKey)) {
                $price = $unitPrices->get($unitKey);
            }
            // Priority 2: Bar-level price (legacy single-price override)
            elseif ($barItemPrice && $barItemPrice->price > 0) {
                $price = (object) [
                    'selling_price' => $barItemPrice->price,
                    'purchase_price' => $item->average_unit_cost ?? 0,
                ];
            }
            // Priority 3: Item's base price
            elseif ($item->price > 0) {
                $price = (object) [
                    'selling_price' => $item->price,
                    'purchase_price' => $item->average_unit_cost ?? 0,
                ];
            }
            // Priority 4: Zero fallback
            else {
                $price = (object) [
                    'selling_price' => 0,
                    'purchase_price' => $item->average_unit_cost ?? 0,
                ];
            }

            $unit->price = $price;
        }
    }

    /**
     * Update stock quantity for director
     */
    public function updateStock(Request $request)
    {
        $user = Auth::user();
        if (!$user->isDirector() && !$user->isManager()) {
            abort(403);
        }

        $request->validate([
            'item_name' => 'required|string',
            'bar_name' => 'required|string',
            'new_stock' => 'required|integer|min:0',
            'category' => 'nullable|string|max:255',
            'units' => 'required|array|min:1',
            'units.*.unit_name' => 'required|string|in:Bottle,Shot,Glass,Can,Crate',
            'units.*.selling_price' => 'required|numeric|min:0',
            'units.*.purchase_price' => 'nullable|numeric|min:0',
            'units.*.conversion_factor' => 'nullable|integer|min:1',
            'units.*.is_base' => 'nullable|in:0,1',
        ]);

        DB::beginTransaction();
        try {
            $itemName = $request->input('item_name');
            $barName = $request->input('bar_name');
            $newStock = (int) $request->input('new_stock');
            $category = $request->input('category');
            $units = $request->input('units');

            // Find the item and bar
            $item = Item::where('name', $itemName)->first();
            $bar = Bar::where('name', $barName)->first();

            if (!$item || !$bar) {
                return back()->with('error', 'Item or bar not found');
            }

            // Identify the base unit (first row, or the one flagged is_base=1)
            $baseUnitIndex = 0;
            foreach ($units as $i => $u) {
                if (!empty($u['is_base']) && $u['is_base'] == '1') {
                    $baseUnitIndex = $i;
                    break;
                }
            }
            $baseUnit = $units[$baseUnitIndex];
            $baseSellingPrice = (float) $baseUnit['selling_price'];
            $basePurchasePrice = (float) ($baseUnit['purchase_price'] ?? 0);

            $oldStock = $item->director_stock;
            $oldPrice = $item->price;

            // Update item master data
            $item->director_stock = $newStock;
            $item->price = $baseSellingPrice;
            if (!empty($category)) {
                $item->category = $category;
            }
            if ($basePurchasePrice > 0) {
                $item->average_unit_cost = $basePurchasePrice;
            }
            $item->save();

            // Find this item's own most recent stock entry row for this bar,
            // regardless of which day's sheet it lives in. A day's sheet only
            // stores rows for items that had activity that day, so the bar's
            // latest SALE can easily be a sheet that has never touched this
            // particular item - looking at the latest sale first (as before)
            // silently skipped the update whenever that happened, so the
            // director's edit never showed up for the seller.
            $stockEntryItem = StockEntryItem::join('sales', 'stock_entry_items.stock_entry_id', '=', 'sales.id')
                ->where('sales.bar_id', $bar->id)
                ->where('stock_entry_items.item_id', $item->id)
                ->orderBy('sales.date', 'desc')
                ->orderBy('stock_entry_items.updated_at', 'desc')
                ->orderBy('stock_entry_items.id', 'desc')
                ->select('stock_entry_items.*')
                ->first();

            if ($stockEntryItem) {
                // Apply the director's new quantity WITHOUT turning the difference
                // into sales. The model computes closing_stock as
                // opening_stock + ordered_stock - sold_quantity, so we pin the
                // ordered amount to make the resulting stock equal $newStock while
                // leaving sold_quantity (and recorded sales) untouched.
                $openingStock = (float) $stockEntryItem->opening_stock;
                $soldQuantity = (float) $stockEntryItem->sold_quantity;
                $newOrdered = max(0, $newStock + $soldQuantity - $openingStock);

                $stockEntryItem->ordered_stock = $newOrdered;
                $stockEntryItem->price = $baseSellingPrice;
                $stockEntryItem->purchase_price = $basePurchasePrice;
                $stockEntryItem->save();
            } else {
                // This item has never had a stock entry for this bar. Create one
                // on today's sheet (creating the sheet if needed) so the seller
                // sees the new stock immediately, the same way restockStock()
                // bootstraps a missing row.
                $todaySale = Sale::firstOrCreate(
                    ['bar_id' => $bar->id, 'date' => now()->format('Y-m-d')],
                    ['user_id' => $user->id]
                );

                StockEntryItem::create([
                    'stock_entry_id' => $todaySale->id,
                    'item_id' => $item->id,
                    'opening_stock' => 0,
                    'ordered_stock' => $newStock,
                    'price' => $baseSellingPrice,
                    'purchase_price' => $basePurchasePrice,
                ]);
            }

            // Update bar item price (this should take priority)
            BarItemPrice::updateOrCreate(
                [
                    'bar_id' => $bar->id,
                    'item_id' => $item->id,
                ],
                [
                    'price' => $baseSellingPrice,
                ]
            );

            // Update product units and their prices
            foreach ($units as $i => $unitData) {
                $isBase = ($i === $baseUnitIndex);
                $conversionFactor = $isBase ? 1 : (int) ($unitData['conversion_factor'] ?? 1);

                ProductUnit::updateOrCreate(
                    [
                        'item_id' => $item->id,
                        'unit_name' => $unitData['unit_name'],
                    ],
                    [
                        'conversion_factor' => $conversionFactor,
                        'is_base_unit' => $isBase,
                    ]
                );

                ProductUnitPrice::updateOrCreate(
                    [
                        'item_id' => $item->id,
                        'unit_name' => $unitData['unit_name'],
                    ],
                    [
                        'selling_price' => (float) $unitData['selling_price'],
                        'purchase_price' => (float) ($unitData['purchase_price'] ?? 0),
                    ]
                );
            }

            // Ensure only the submitted units exist for this item
            $submittedUnitNames = collect($units)->pluck('unit_name')->map(fn ($n) => strtolower($n))->toArray();
            ProductUnit::where('item_id', $item->id)
                ->whereRaw('LOWER(unit_name) NOT IN (' . implode(',', array_fill(0, count($submittedUnitNames), '?')) . ')', $submittedUnitNames)
                ->delete();
            ProductUnitPrice::where('item_id', $item->id)
                ->whereRaw('LOWER(unit_name) NOT IN (' . implode(',', array_fill(0, count($submittedUnitNames), '?')) . ')', $submittedUnitNames)
                ->delete();

            // Sync with warehouse - delete warehouse unit bar prices so BarItemPrice takes priority
            $warehouseStock = WarehouseStock::where('item_name', $itemName)
                ->with(['units.barPrices'])
                ->first();

            if ($warehouseStock) {
                $warehouseStock->selling_price = $baseSellingPrice;
                if ($basePurchasePrice > 0) {
                    $warehouseStock->purchase_price = $basePurchasePrice;
                }
                $warehouseStock->save();

                // Get all warehouse unit IDs for this warehouse stock
                $warehouseUnitIds = $warehouseStock->units->pluck('id')->toArray();

                // Delete warehouse unit bar prices for this bar so BarItemPrice takes priority
                if (!empty($warehouseUnitIds)) {
                    WarehouseUnitBarPrice::whereIn('warehouse_unit_id', $warehouseUnitIds)
                        ->where('bar_id', $bar->id)
                        ->delete();
                }
            }

            // Log the activity
            $unitSummary = collect($units)->map(fn ($u) => $u['unit_name'] . ' @ MWK ' . number_format((float) $u['selling_price'], 2))->implode(', ');
            ActivityLog::log([
                'action' => 'stock_updated',
                'description' => "Updated stock for {$item->name} at {$bar->name} from {$oldStock} to {$newStock} and price from {$oldPrice} to {$baseSellingPrice}. Units: {$unitSummary}",
                'subject_type' => Item::class,
                'subject_id' => $item->id,
                'old_values' => ['stock' => $oldStock, 'bar' => $bar->name, 'price' => $oldPrice],
                'new_values' => ['stock' => $newStock, 'bar' => $bar->name, 'price' => $baseSellingPrice, 'units' => $units],
            ]);

            DB::commit();
            return redirect()->route('stock.index')->with('success', 'Stock updated successfully!');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Error updating stock: ' . $e->getMessage());
        }
    }

    /**
     * Add new stock for director
     */
    public function addStock(Request $request)
    {
        $user = Auth::user();
        if (!$user->isDirector() && !$user->isManager()) {
            abort(403);
        }

        $request->validate([
            'bar_id' => 'required|exists:bars,id',
            'stock_quantity' => 'required|integer|min:0',
            'item_name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'units' => 'required|array|min:1',
            'units.*.unit_name' => 'required|string|in:Bottle,Shot,Glass,Can,Crate',
            'units.*.selling_price' => 'required|numeric|min:0',
            'units.*.purchase_price' => 'nullable|numeric|min:0',
            'units.*.conversion_factor' => 'nullable|integer|min:1',
            'units.*.is_base' => 'nullable|in:0,1',
        ]);

        DB::beginTransaction();
        try {
            $barId = $request->input('bar_id');
            $stockQuantity = (int) $request->input('stock_quantity');
            $itemName = trim($request->input('item_name'));
            $category = $request->input('category', 'Other') ?: 'Other';
            $units = $request->input('units');

            $bar = Bar::find($barId);

            // Identify the base unit (first row, or the one flagged is_base=1)
            $baseUnitIndex = 0;
            foreach ($units as $i => $u) {
                if (!empty($u['is_base']) && $u['is_base'] == '1') {
                    $baseUnitIndex = $i;
                    break;
                }
            }
            $baseUnit = $units[$baseUnitIndex];
            $basePrice = (float) $baseUnit['selling_price'];
            $baseCostPrice = (float) ($baseUnit['purchase_price'] ?? 0);

            // Find existing item by name or create a new one
            $item = Item::where('name', $itemName)->first();
            $isNewItem = false;
            
            if (!$item) {
                $isNewItem = true;
                $item = Item::create([
                    'name' => $itemName,
                    'category' => $category,
                    'price' => $basePrice,
                    'director_stock' => $stockQuantity,
                    'average_unit_cost' => $baseCostPrice > 0 ? $baseCostPrice : $basePrice,
                    'lifetime_quantity_purchased' => $stockQuantity,
                ]);

                // Create ProductUnit + ProductUnitPrice for each unit
                foreach ($units as $i => $unitData) {
                    $isBase = ($i === $baseUnitIndex);
                    $conversionFactor = $isBase ? 1 : (int) ($unitData['conversion_factor'] ?? 1);

                    ProductUnit::create([
                        'item_id' => $item->id,
                        'unit_name' => $unitData['unit_name'],
                        'conversion_factor' => $conversionFactor,
                        'is_base_unit' => $isBase,
                    ]);

                    ProductUnitPrice::create([
                        'item_id' => $item->id,
                        'unit_name' => $unitData['unit_name'],
                        'selling_price' => (float) $unitData['selling_price'],
                        'purchase_price' => (float) ($unitData['purchase_price'] ?? 0),
                    ]);
                }
            } else {
                $item->director_stock = max($item->director_stock, $stockQuantity);
                $item->price = $basePrice;
                $item->save();

                // For existing items, add any NEW units that don't already exist
                $existingUnits = ProductUnit::where('item_id', $item->id)
                    ->pluck('unit_name')
                    ->map(fn ($n) => strtolower($n))
                    ->toArray();

                foreach ($units as $i => $unitData) {
                    $unitNameLower = strtolower($unitData['unit_name']);
                    if (in_array($unitNameLower, $existingUnits)) {
                        // Update existing price
                        ProductUnitPrice::updateOrCreate(
                            ['item_id' => $item->id, 'unit_name' => $unitData['unit_name']],
                            [
                                'selling_price' => (float) $unitData['selling_price'],
                                'purchase_price' => (float) ($unitData['purchase_price'] ?? 0),
                            ]
                        );
                        continue;
                    }

                    $isBase = ($i === $baseUnitIndex);
                    $conversionFactor = $isBase ? 1 : (int) ($unitData['conversion_factor'] ?? 1);

                    ProductUnit::create([
                        'item_id' => $item->id,
                        'unit_name' => $unitData['unit_name'],
                        'conversion_factor' => $conversionFactor,
                        'is_base_unit' => $isBase,
                    ]);

                    ProductUnitPrice::create([
                        'item_id' => $item->id,
                        'unit_name' => $unitData['unit_name'],
                        'selling_price' => (float) $unitData['selling_price'],
                        'purchase_price' => (float) ($unitData['purchase_price'] ?? 0),
                    ]);
                }
            }

            // Create or update today's stock entry for the selected bar
            $today = now()->format('Y-m-d');
            $stockEntry = Sale::firstOrCreate([
                'bar_id' => $barId,
                'date' => $today,
            ], [
                'user_id' => $user->id,
            ]);

            // Update or create stock entry item
            $stockEntryItem = StockEntryItem::updateOrCreate(
                [
                    'stock_entry_id' => $stockEntry->id,
                    'item_id' => $item->id,
                ],
                [
                    'opening_stock' => $stockQuantity,
                    'ordered_stock' => 0,
                    'total_stock' => $stockQuantity,
                    'closing_stock' => $stockQuantity,
                    'sold_quantity' => 0,
                    'sales_amount' => 0,
                    'price' => $basePrice,
                    'purchase_price' => $baseCostPrice,
                ]
            );

            // Update bar item price (use base unit's selling price)
            BarItemPrice::updateOrCreate(
                [
                    'bar_id' => $barId,
                    'item_id' => $item->id,
                ],
                [
                    'price' => $basePrice,
                ]
            );

            // Log activity
            $unitSummary = collect($units)->map(fn ($u) => $u['unit_name'] . ' @ MWK ' . number_format((float) $u['selling_price'], 2))->implode(', ');
            ActivityLog::log([
                'action' => $isNewItem ? 'item_created' : 'stock_added',
                'description' => "Added stock for {$item->name} at {$bar->name}: {$stockQuantity} units. Units: {$unitSummary}",
                'subject_type' => Item::class,
                'subject_id' => $item->id,
                'old_values' => ['bar' => $bar->name],
                'new_values' => ['stock' => $stockQuantity, 'base_price' => $basePrice, 'bar' => $bar->name, 'units' => $units],
            ]);

            DB::commit();
            return redirect()->route('stock.index')->with('success', "Stock added successfully for {$item->name} at {$bar->name}!");
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Error adding stock: ' . $e->getMessage());
        }
    }

    /**
     * Restock existing item for director (auto-adds to previous stock)
     */
    public function restockStock(Request $request)
    {
        $user = Auth::user();
        if (!$user->isDirector() && !$user->isManager()) {
            abort(403);
        }

        $request->validate([
            'item_id' => 'required|integer|exists:items,id',
            'bar_id' => 'required|integer|exists:bars,id',
            'item_name' => 'required|string',
            'bar_name' => 'required|string',
            'additional_stock' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $itemId = (int) $request->input('item_id');
            $barId = (int) $request->input('bar_id');
            $additionalStockRaw = (float) $request->input('additional_stock');
            $price = (float) $request->input('price');

            $item = Item::find($itemId);
            $bar = Bar::find($barId);

            if (!$item || !$bar) {
                return back()->with('error', 'Item or bar not found');
            }

            // The director enters the restock quantity in bottles (just like
            // seller order quantities), but ordered_stock / closing_stock are
            // stored in the item's BASE unit (e.g. Shot for spirits). Convert to
            // base units so the stored stock is consistent with how sales are
            // validated. Without this, a restock of N bottles is stored as N
            // base units and selling a single bottle (which converts to
            // N*factor base units) is rejected as "insufficient stock".
            $inventoryService = new InventoryService();
            $additionalStock = $inventoryService->convertSellerOrderQuantityToBaseUnits(
                $itemId,
                $additionalStockRaw
            );

            $oldDirectorStock = (float) $item->director_stock;
            $newDirectorStock = $oldDirectorStock + $additionalStock;
            $item->director_stock = $newDirectorStock;
            $item->price = $price;
            $item->save();

            // Fetch or create latest daily stock entry
            $today = now()->format('Y-m-d');
            $stockEntry = Sale::firstOrCreate([
                'bar_id' => $bar->id,
                'date' => $today,
            ], [
                'user_id' => $user->id,
            ]);

            $stockEntryItem = StockEntryItem::where('stock_entry_id', $stockEntry->id)
                ->where('item_id', $item->id)
                ->first();

            if ($stockEntryItem) {
                // The director sees "stock" as the current closing_stock, so the new
                // stock must be EXACTLY the current closing + the added quantity.
                $oldClosing = (float) $stockEntryItem->closing_stock;
                $newClosing = $oldClosing + $additionalStock;
                $opening = (float) $stockEntryItem->opening_stock;
                $soldQuantity = (float) $stockEntryItem->sold_quantity;

                // Derive ordered so the identities
                //   total_stock = opening_stock + ordered_stock
                //   closing_stock = total_stock - sold_quantity
                // stay valid while opening / sold are left untouched.
                $newOrdered = max(0, ($newClosing + $soldQuantity) - $opening);

                // Write the stock fields straight into the model attributes to bypass
                // the StockEntryItem mutators (which were double-counting the added
                // quantity / re-deriving sales). This guarantees the new stock equals
                // old stock + additional, exactly as expected, without creating sales.
                // NOTE: We MUST use setRawAttributes() here. Manually editing
                // $stockEntryItem->attributes[...] intends the same thing but fails,
                // because "attributes" is a protected Eloquent property - accessing
                // it returns a copy, so the writes throw an "Indirect modification of
                // overloaded property ... $attributes" error (or silently do nothing).
                $stockEntryItem->setRawAttributes(array_merge(
                    $stockEntryItem->getAttributes(),
                    [
                        'opening_stock' => $opening,
                        'ordered_stock' => $newOrdered,
                        'total_stock'   => $opening + $newOrdered,
                        'closing_stock' => $newClosing,
                        'sold_quantity' => $soldQuantity,
                        'sales_amount'  => (float) $stockEntryItem->sales_amount,
                    ]
                ), false);
                $stockEntryItem->price = $price;
                $stockEntryItem->save();
            } else {
                // No stock entry item for today yet: carry yesterday's closing over as
                // today's opening so the restock shows as previous stock + added qty
                // instead of resetting to zero.
                $previousClosingMap = Sale::where('bar_id', $bar->id)
                    ->where('date', '<', $today)
                    ->orderBy('date', 'desc')
                    ->orderBy('id', 'desc')
                    ->with('stockEntryItems')
                    ->first()?->stockEntryItems->pluck('closing_stock', 'item_id')->toArray() ?? [];
                $oldClosing = (float) ($previousClosingMap[$item->id] ?? 0);
                $newClosing = $oldClosing + $additionalStock;

                $stockEntryItem = StockEntryItem::create([
                    'stock_entry_id' => $stockEntry->id,
                    'item_id' => $item->id,
                    'opening_stock' => $oldClosing,
                    'ordered_stock' => $additionalStock,
                    'total_stock' => $oldClosing + $additionalStock,
                    'closing_stock' => $newClosing,
                    'sold_quantity' => 0,
                    'sales_amount' => 0,
                    'price' => $price,
                    'purchase_price' => 0,
                ]);
            }

            // Update bar item price
            BarItemPrice::updateOrCreate(
                ['bar_id' => $bar->id, 'item_id' => $item->id],
                ['price' => $price]
            );

            ProductUnitPrice::where('item_id', $item->id)->update(['selling_price' => $price]);

            // Log activity
            ActivityLog::log([
                'action' => 'stock_restocked',
                'description' => "Restocked {$item->name} at {$bar->name}: added {$additionalStock} units (previous: {$oldClosing}, new stock: {$newClosing}) at MWK {$price}",
                'subject_type' => Item::class,
                'subject_id' => $item->id,
                'old_values' => ['stock' => $oldClosing, 'bar' => $bar->name],
                'new_values' => ['added_stock' => $additionalStock, 'new_stock' => $newClosing, 'price' => $price, 'bar' => $bar->name],
            ]);

            DB::commit();
            return redirect()->route('stock.index')->with('success', "Added {$additionalStock} units to {$item->name} at {$bar->name}!");
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Error restocking item: ' . $e->getMessage());
        }
    }

    /**
     * Delete stock for director
     */
    public function deleteStock(Request $request)
    {
        $user = Auth::user();
        if (!$user->isDirector() && !$user->isManager()) {
            abort(403);
        }

        $itemName = $request->query('item');
        $barName = $request->query('bar');

        if (!$itemName || !$barName) {
            return back()->with('error', 'Missing item or bar information');
        }

        DB::beginTransaction();
        try {
            $item = Item::where('name', $itemName)->first();
            $bar = Bar::where('name', $barName)->first();

            if (!$item || !$bar) {
                return back()->with('error', 'Item or bar not found');
            }

            // Delete stock entry items for this item and bar
            $stockEntryItems = StockEntryItem::where('item_id', $item->id)
                ->whereHas('stockEntry', function($query) use ($bar) {
                    $query->where('bar_id', $bar->id);
                })
                ->get();

            foreach ($stockEntryItems as $stockEntryItem) {
                $oldStock = $stockEntryItem->closing_stock;
                $stockEntryItem->delete();

                // Log the activity
                ActivityLog::log([
                    'action' => 'stock_deleted',
                    'description' => "Deleted stock for {$item->name} at {$bar->name}",
                    'subject_type' => Item::class,
                    'subject_id' => $item->id,
                    'old_values' => ['stock' => $oldStock, 'bar' => $bar->name],
                    'new_values' => ['stock' => 0, 'bar' => $bar->name],
                ]);
            }

            // Hide the item so it is removed from the seller UI and the director's
            // stock overview. Historical records are preserved (the item row is not
            // deleted), keeping past reports and reconciliations intact.
            $item->is_hidden = true;
            $item->save();

            // Delete bar item price
            BarItemPrice::where('bar_id', $bar->id)
                ->where('item_id', $item->id)
                ->delete();

            DB::commit();
            return redirect()->route('stock.index')->with('success', 'Stock deleted successfully!');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Error deleting stock: ' . $e->getMessage());
        }
    }

    private function enrichSellerItemStockDisplay(Item $item, Bar $bar, array $figures): array
    {
        $stockUnit = $this->resolveSellerProductUnits($item, $bar)->first()?->unit_name ?? 'Bottle';
        $inventoryService = new InventoryService();

        $toDisplay = fn (float $base) => $inventoryService->convertBaseUnitsToSellerDisplay($item->id, $base, $stockUnit);

        $figures['stock_unit'] = $stockUnit;
        $figures['opening_stock_display'] = $toDisplay($figures['opening_stock']);
        $figures['ordered_stock_display'] = $toDisplay($figures['ordered_stock']);
        $figures['closing_stock_display'] = $toDisplay($figures['closing_stock']);
        $figures['available_stock_display'] = $toDisplay($figures['available_stock']);
        $figures['sold_quantity_display'] = $toDisplay($figures['sold_quantity'] ?? 0);

        return $figures;
    }

    private function assertSufficientStock(
        InventoryService $inventoryService,
        int $itemId,
        float $salesInSelectedUnit,
        ?string $unitName,
        float $availableBase
    ): void {
        if ($salesInSelectedUnit <= 0) {
            return;
        }

        $salesBase = $unitName
            ? $inventoryService->convertToBaseUnits($itemId, $salesInSelectedUnit, $unitName)
            : $salesInSelectedUnit;

        if ($salesBase > $availableBase + 0.0001) {
            $unit = $unitName ?: 'unit';
            $baseUnit = $inventoryService->getBaseUnit($itemId);
            $baseName = $baseUnit ? $baseUnit->unit_name : 'unit';

            // Build a message that tells the seller exactly how much stock
            // is available and how much the attempted sale needs, in both
            // the selected unit and the base (storage) unit.
            if ($unitName && $unitName !== $baseName) {
                $availableInUnit = (int) floor(
                    $inventoryService->convertFromBaseUnits($itemId, max(0, $availableBase), $unitName)
                );
                $message = "Cannot sell {$salesInSelectedUnit} {$unit} — only "
                    . number_format(max(0, $availableBase)) . " {$baseName}(s) available "
                    . "(need " . number_format($salesBase) . " {$baseName}(s) for {$salesInSelectedUnit} {$unit}). "
                    . "Max you can sell: {$availableInUnit} {$unit}(s).";
            } else {
                $availableWhole = (int) floor(max(0, $availableBase));
                $message = "Cannot sell {$salesInSelectedUnit} {$unit} — only {$availableWhole} {$baseName}(s) available.";
            }

            throw \Illuminate\Validation\ValidationException::withMessages([
                'items' => [$message],
            ]);
        }
    }
}

