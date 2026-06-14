<?php

namespace App\Http\Controllers;

use App\Models\WarehouseStock;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WarehouseStockController extends Controller
{
    /**
     * Display the warehouse stock index with summary
     */
    public function index(Request $request): View
    {
        $search = $request->input('search');
        $selectedBarId = $request->input('bar_id') ?: null;

        $query = WarehouseStock::with('units.barPrices');

        if ($search) {
            $query->where('item_name', 'like', "%{$search}%");
        }

        $stocks = $query->orderBy('item_name')->paginate(15);

        $bars = \App\Models\Bar::listed()->orderBy('name')->get();
        $selectedBar = $selectedBarId ? $bars->firstWhere('id', (int) $selectedBarId) : null;

        $totalStockCost = WarehouseStock::sum(\DB::raw('quantity * purchase_price'));

        if ($selectedBarId) {
            $totalStockValue = 0;
            $totalExpectedProfit = 0;

            foreach ($stocks as $stock) {
                $totalStockValue += $stock->getStockValueForBranch($selectedBarId);
                $totalExpectedProfit += $stock->getPotentialProfitForBar($selectedBarId);
            }
        } else {
            $totalStockValue = 0;
            $totalExpectedProfit = 0;

            foreach ($stocks as $stock) {
                foreach ($bars as $bar) {
                    $totalStockValue += $stock->getStockValueForBranch($bar->id);
                    $totalExpectedProfit += $stock->getPotentialProfitForBar($bar->id);
                }
            }
        }

        $lowStockCount = WarehouseStock::whereColumn('quantity', '<=', 'alert_quantity')->count();

        $expiryAlertsCount = WarehouseStock::whereNotNull('expiry_date')
            ->where('expiry_date', '>=', now())
            ->where('expiry_date', '<=', now()->addDays(7))
            ->count();

        $expiredCount = WarehouseStock::whereNotNull('expiry_date')
            ->where('expiry_date', '<', now())
            ->count();

        $totalItemsCount = WarehouseStock::count();

        return view('warehouse.index', compact(
            'stocks',
            'totalStockCost',
            'totalStockValue',
            'totalExpectedProfit',
            'lowStockCount',
            'expiryAlertsCount',
            'expiredCount',
            'totalItemsCount',
            'search',
            'bars',
            'selectedBarId',
            'selectedBar'
        ));
    }

    /**
     * Show create form
     */
    public function create(): View
    {
        $bars = \App\Models\Bar::listed()->orderBy('name')->get();

        return view('warehouse.create', compact('bars'));
    }

    /**
     * Show warehouse stock details
     */
    public function show(WarehouseStock $warehouseStock): View
    {
        $warehouseStock->load(['units.barPrices', 'transactions' => function ($query) {
            $query->orderBy('transaction_date', 'desc');
        }]);
        
        $bars = \App\Models\Bar::listed()->orderBy('name')->get();
        
        return view('warehouse.show', compact('warehouseStock', 'bars'));
    }

    /**
     * Show restock form
     */
    public function restock(WarehouseStock $warehouseStock): View
    {
        return view('warehouse.restock', compact('warehouseStock'));
    }

    /**
     * Process restock
     */
    public function processRestock(Request $request, WarehouseStock $warehouseStock): RedirectResponse
    {
        $validated = $request->validate([
            'quantity_purchased' => 'required|integer|min:1',
            'total_purchase_cost' => 'required|numeric|min:0',
            'purchase_unit' => 'required|string|max:255',
            'conversion_factor' => 'required|integer|min:1',
            'supplier' => 'nullable|string|max:255',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            // Calculate cost per base unit
            $totalBaseUnits = $validated['quantity_purchased'] * $validated['conversion_factor'];
            $calculatedBaseUnitCost = $totalBaseUnits > 0 ? $validated['total_purchase_cost'] / $totalBaseUnits : 0;

            // Add transaction record
            $warehouseStock->addTransaction([
                'transaction_type' => 'restock',
                'quantity' => $totalBaseUnits,
                'unit_cost' => $calculatedBaseUnitCost,
                'total_cost' => $validated['total_purchase_cost'],
                'supplier' => $validated['supplier'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'transaction_date' => now(),
            ]);

            // Update current stock
            $warehouseStock->quantity += $totalBaseUnits;
            $warehouseStock->save();

            DB::commit();
            return redirect()->route('warehouse.show', $warehouseStock)
                ->with('success', 'Stock restocked successfully!');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error restocking item: ' . $e->getMessage());
        }
    }

    /**
     * Store new warehouse stock
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_name' => 'required|string|max:255',
            'quantity' => 'required|integer|min:0',
            'alert_quantity' => 'required|integer|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'expiry_date' => 'nullable|date|after_or_equal:today',
            'notes' => 'nullable|string|max:500',
            'purchase_unit' => 'required|string|max:255',
            'quantity_purchased' => 'required|integer|min:1',
            'total_purchase_cost' => 'required|numeric|min:0',
            'base_unit' => 'required|string|in:Bottle,Shot',
            'conversion_factor' => 'required|integer|min:1',
            'bar_selling_prices' => 'nullable|array',
            'additional_units' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            // Calculate cost per base unit
            $totalBaseUnits = $validated['quantity_purchased'] * $validated['conversion_factor'];
            $calculatedBaseUnitCost = $totalBaseUnits > 0 ? $validated['total_purchase_cost'] / $totalBaseUnits : 0;

            // Set purchase_price to calculated base unit cost for backward compatibility
            $validated['purchase_price'] = $calculatedBaseUnitCost;
            $validated['calculated_base_unit_cost'] = $calculatedBaseUnitCost;
            $validated['average_unit_cost'] = $calculatedBaseUnitCost;
            $validated['lifetime_quantity_purchased'] = $totalBaseUnits;

            // Set default selling price if not provided (use first bar price)
            if (empty($validated['selling_price']) && !empty($validated['bar_selling_prices'])) {
                $firstBarPrice = reset($validated['bar_selling_prices']);
                if ($firstBarPrice > 0) {
                    $validated['selling_price'] = $firstBarPrice;
                }
            }

            $warehouseStock = WarehouseStock::create($validated);

            // Create initial purchase transaction
            $warehouseStock->addTransaction([
                'transaction_type' => 'purchase',
                'quantity' => $totalBaseUnits,
                'unit_cost' => $calculatedBaseUnitCost,
                'total_cost' => $validated['total_purchase_cost'],
                'transaction_date' => now(),
            ]);

            // Create warehouse unit record
            $warehouseUnit = \App\Models\WarehouseUnit::create([
                'warehouse_stock_id' => $warehouseStock->id,
                'unit_name' => $validated['base_unit'],
                'conversion_factor' => 1, // Base unit always has conversion factor of 1
                'is_base_unit' => true,
                'purchase_price' => $calculatedBaseUnitCost,
            ]);

            // Create purchase unit record
            $purchaseUnit = \App\Models\WarehouseUnit::create([
                'warehouse_stock_id' => $warehouseStock->id,
                'unit_name' => $validated['purchase_unit'],
                'conversion_factor' => $validated['conversion_factor'],
                'is_base_unit' => false,
                'purchase_price' => $validated['quantity_purchased'] > 0 ? $validated['total_purchase_cost'] / $validated['quantity_purchased'] : 0,
            ]);

            // Handle bar-specific selling prices
            if ($request->has('bar_selling_prices') && is_array($request->bar_selling_prices)) {
                foreach ($request->bar_selling_prices as $barId => $sellingPrice) {
                    if ($sellingPrice > 0) {
                        \App\Models\WarehouseUnitBarPrice::create([
                            'warehouse_unit_id' => $warehouseUnit->id,
                            'bar_id' => $barId,
                            'selling_price' => $sellingPrice,
                        ]);
                    }
                }
            }

            // Handle additional units (created by manager)
            if ($request->has('additional_units') && is_array($request->additional_units)) {
                foreach ($request->additional_units as $unitData) {
                    if (empty($unitData['unit_name'])) continue;

                    $conv = isset($unitData['conversion_factor']) ? intval($unitData['conversion_factor']) : 1;
                    $purchasePrice = $calculatedBaseUnitCost * $conv;

                    $newUnit = \App\Models\WarehouseUnit::create([
                        'warehouse_stock_id' => $warehouseStock->id,
                        'unit_name' => $unitData['unit_name'],
                        'conversion_factor' => $conv,
                        'is_base_unit' => false,
                        'purchase_price' => $purchasePrice,
                    ]);

                    // Create per-bar prices if provided
                    if (!empty($unitData['bar_selling_prices']) && is_array($unitData['bar_selling_prices'])) {
                        foreach ($unitData['bar_selling_prices'] as $barId => $price) {
                            if ($price > 0) {
                                try {
                                    \App\Models\WarehouseUnitBarPrice::create([
                                        'warehouse_unit_id' => $newUnit->id,
                                        'bar_id' => $barId,
                                        'selling_price' => $price,
                                    ]);
                                } catch (\Exception $e) {
                                    \Log::warning('Failed to create warehouse unit bar price', ['error' => $e->getMessage()]);
                                }
                            }
                        }
                    }
                }
            }

            DB::commit();
            return redirect()->route('warehouse.index')
                ->with('success', 'Warehouse item added successfully!');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error adding warehouse item: ' . $e->getMessage());
        }
    }

    /**
     * Show edit form
     */
    public function edit(WarehouseStock $warehouseStock): View
    {
        $bars = \App\Models\Bar::listed()->orderBy('name')->get();
        return view('warehouse.edit', compact('warehouseStock', 'bars'));
    }

    /**
     * Update warehouse stock
     */
    public function update(Request $request, WarehouseStock $warehouseStock): RedirectResponse
    {
        $validated = $request->validate([
            'item_name' => 'required|string|max:255',
            'quantity' => 'required|integer|min:0',
            'alert_quantity' => 'required|integer|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'expiry_date' => 'nullable|date|after_or_equal:today',
            'notes' => 'nullable|string|max:500',
            'purchase_unit' => 'required|string|max:255',
            'quantity_purchased' => 'required|integer|min:1',
            'total_purchase_cost' => 'required|numeric|min:0',
            'base_unit' => 'required|string|in:Bottle,Shot',
            'conversion_factor' => 'required|integer|min:1',
            'bar_selling_prices' => 'nullable|array',
            'additional_units' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            // Calculate cost per base unit
            $totalBaseUnits = $validated['quantity_purchased'] * $validated['conversion_factor'];
            $calculatedBaseUnitCost = $totalBaseUnits > 0 ? $validated['total_purchase_cost'] / $totalBaseUnits : 0;

            // Set purchase_price to calculated base unit cost for backward compatibility
            $validated['purchase_price'] = $calculatedBaseUnitCost;
            $validated['calculated_base_unit_cost'] = $calculatedBaseUnitCost;
            $validated['average_unit_cost'] = $calculatedBaseUnitCost;

            // Set default selling price if not provided (use first bar price)
            if (empty($validated['selling_price']) && !empty($validated['bar_selling_prices'])) {
                $firstBarPrice = reset($validated['bar_selling_prices']);
                if ($firstBarPrice > 0) {
                    $validated['selling_price'] = $firstBarPrice;
                }
            }

            $warehouseStock->update($validated);

            // Update or create base unit
            $baseUnit = $warehouseStock->units()->where('is_base_unit', true)->first();
            if ($baseUnit) {
                $baseUnit->update([
                    'unit_name' => $validated['base_unit'],
                    'purchase_price' => $calculatedBaseUnitCost,
                ]);
            } else {
                $baseUnit = \App\Models\WarehouseUnit::create([
                    'warehouse_stock_id' => $warehouseStock->id,
                    'unit_name' => $validated['base_unit'],
                    'conversion_factor' => 1,
                    'is_base_unit' => true,
                    'purchase_price' => $calculatedBaseUnitCost,
                ]);
            }

            // Update or create purchase unit
            $purchaseUnit = $warehouseStock->units()->where('unit_name', $validated['purchase_unit'])->where('is_base_unit', false)->first();
            if ($purchaseUnit) {
                $purchaseUnit->update([
                    'conversion_factor' => $validated['conversion_factor'],
                    'purchase_price' => $validated['quantity_purchased'] > 0 ? $validated['total_purchase_cost'] / $validated['quantity_purchased'] : 0,
                ]);
            } else {
                \App\Models\WarehouseUnit::create([
                    'warehouse_stock_id' => $warehouseStock->id,
                    'unit_name' => $validated['purchase_unit'],
                    'conversion_factor' => $validated['conversion_factor'],
                    'is_base_unit' => false,
                    'purchase_price' => $validated['quantity_purchased'] > 0 ? $validated['total_purchase_cost'] / $validated['quantity_purchased'] : 0,
                ]);
            }

            // Handle bar-specific selling prices
            if ($request->has('bar_selling_prices') && is_array($request->bar_selling_prices)) {
                foreach ($request->bar_selling_prices as $barId => $sellingPrice) {
                    if ($sellingPrice > 0) {
                        $barPrice = $baseUnit->barPrices()->where('bar_id', $barId)->first();
                        if ($barPrice) {
                            $barPrice->update(['selling_price' => $sellingPrice]);
                        } else {
                            \App\Models\WarehouseUnitBarPrice::create([
                                'warehouse_unit_id' => $baseUnit->id,
                                'bar_id' => $barId,
                                'selling_price' => $sellingPrice,
                            ]);
                        }
                    }
                }
            }

            // Handle additional units
            // First, get all existing additional units for this warehouse stock
            $existingAdditionalUnits = $warehouseStock->units()->where('is_base_unit', false)->get();
            $existingUnitIds = [];

            // Process submitted additional units
            if ($request->has('additional_units') && is_array($request->additional_units)) {
                foreach ($request->additional_units as $unitData) {
                    if (empty($unitData['unit_name'])) continue;

                    $conv = isset($unitData['conversion_factor']) ? intval($unitData['conversion_factor']) : 1;
                    $purchasePrice = $calculatedBaseUnitCost * $conv;

                    // Check if this is an existing unit (has unit_id in data)
                    if (isset($unitData['unit_id']) && $unitData['unit_id']) {
                        // Update existing unit
                        $existingUnit = $existingAdditionalUnits->where('id', $unitData['unit_id'])->first();
                        if ($existingUnit) {
                            $existingUnit->update([
                                'unit_name' => $unitData['unit_name'],
                                'conversion_factor' => $conv,
                                'purchase_price' => $purchasePrice,
                            ]);
                            $existingUnitIds[] = $existingUnit->id;

                            // Update per-bar prices if provided
                            if (!empty($unitData['bar_selling_prices']) && is_array($unitData['bar_selling_prices'])) {
                                foreach ($unitData['bar_selling_prices'] as $barId => $price) {
                                    if ($price > 0) {
                                        $barPrice = $existingUnit->barPrices()->where('bar_id', $barId)->first();
                                        if ($barPrice) {
                                            $barPrice->update(['selling_price' => $price]);
                                        } else {
                                            \App\Models\WarehouseUnitBarPrice::create([
                                                'warehouse_unit_id' => $existingUnit->id,
                                                'bar_id' => $barId,
                                                'selling_price' => $price,
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        // Create new additional unit
                        $newUnit = \App\Models\WarehouseUnit::create([
                            'warehouse_stock_id' => $warehouseStock->id,
                            'unit_name' => $unitData['unit_name'],
                            'conversion_factor' => $conv,
                            'is_base_unit' => false,
                            'purchase_price' => $purchasePrice,
                        ]);
                        $existingUnitIds[] = $newUnit->id;

                        // Create per-bar prices if provided
                        if (!empty($unitData['bar_selling_prices']) && is_array($unitData['bar_selling_prices'])) {
                            foreach ($unitData['bar_selling_prices'] as $barId => $price) {
                                if ($price > 0) {
                                    try {
                                        \App\Models\WarehouseUnitBarPrice::create([
                                            'warehouse_unit_id' => $newUnit->id,
                                            'bar_id' => $barId,
                                            'selling_price' => $price,
                                        ]);
                                    } catch (\Exception $e) {
                                        \Log::warning('Failed to create warehouse unit bar price', ['error' => $e->getMessage()]);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Delete additional units that were not submitted (removed by user)
            foreach ($existingAdditionalUnits as $existingUnit) {
                if (!in_array($existingUnit->id, $existingUnitIds)) {
                    // Delete bar prices first
                    $existingUnit->barPrices()->delete();
                    // Then delete the unit
                    $existingUnit->delete();
                }
            }

            DB::commit();
            return redirect()->route('warehouse.index')
                ->with('success', 'Warehouse item updated successfully!');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error updating warehouse item: ' . $e->getMessage());
        }
    }

    /**
     * Delete warehouse stock
     */
    public function destroy(WarehouseStock $warehouseStock): RedirectResponse
    {
        $warehouseStock->delete();

        return redirect()->route('warehouse.index')
            ->with('success', 'Warehouse item deleted successfully!');
    }

    /**
     * Show expiry alerts
     */
    public function expiryAlerts(): View
    {
        $expiringItems = WarehouseStock::whereNotNull('expiry_date')
            ->where('expiry_date', '>=', now())
            ->where('expiry_date', '<=', now()->addDays(7))
            ->orderBy('expiry_date')
            ->get();

        $expiredItems = WarehouseStock::whereNotNull('expiry_date')
            ->where('expiry_date', '<', now())
            ->orderBy('expiry_date', 'desc')
            ->get();

        return view('warehouse.alerts.expiry', compact('expiringItems', 'expiredItems'));
    }

    /**
     * Show low stock alerts
     */
    public function lowStockAlerts(): View
    {
        $lowStockItems = WarehouseStock::whereColumn('quantity', '<=', 'alert_quantity')
            ->orderBy('quantity', 'asc')
            ->get();

        return view('warehouse.alerts.low-stock', compact('lowStockItems'));
    }

    /**
     * Show pending warehouse transfer requests
     */
    public function transferRequests(): View
    {
        $pendingRequests = \App\Models\WarehouseTransferRequest::with(['bar', 'requestedBy', 'items.warehouseStock', 'items.item'])
            ->where('status', 'pending')
            ->orderBy('requested_at', 'desc')
            ->get();

        $completedRequests = \App\Models\WarehouseTransferRequest::with(['bar', 'requestedBy', 'approvedBy', 'items.warehouseStock', 'items.item'])
            ->whereIn('status', ['approved', 'partially_approved', 'rejected'])
            ->orderBy('approved_at', 'desc')
            ->take(50)
            ->get();

        return view('warehouse.transfer-requests', compact('pendingRequests', 'completedRequests'));
    }

    /**
     * Approve warehouse transfer request
     */
    public function approveTransfer(Request $request, \App\Models\WarehouseTransferRequest $transferRequest): RedirectResponse
    {
        \Log::info('=== TRANSFER APPROVAL STARTED ===', [
            'transfer_request_id' => $transferRequest->id,
            'bar_id' => $transferRequest->bar_id,
        ]);
        
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:warehouse_transfer_request_items,id',
            'items.*.quantity_approved' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        
        try {
            $totalApproved = 0;
            
            foreach ($request->items as $itemData) {
                \Log::info('Processing transfer item', ['item_data' => $itemData]);
                
                $transferItem = \App\Models\WarehouseTransferRequestItem::findOrFail($itemData['id']);
                $warehouseStock = $transferItem->warehouseStock;
                $quantityApproved = $itemData['quantity_approved'];
                
                \Log::info('Transfer item found', [
                    'warehouse_stock_id' => $warehouseStock->id,
                    'item_name' => $warehouseStock->item_name,
                    'quantity_approved' => $quantityApproved,
                ]);
                
                // Calculate base units approved
                $baseUnitsApproved = $quantityApproved * ($transferItem->conversion_factor ?? 1);
                
                // Check if warehouse has enough stock
                if ($baseUnitsApproved > $warehouseStock->quantity) {
                    throw new \Exception("Not enough stock in warehouse for {$warehouseStock->item_name}. Available: {$warehouseStock->quantity}, Requested (in base units): {$baseUnitsApproved}");
                }
                
                // Update transfer item
                $transferItem->update([
                    'quantity_approved' => $quantityApproved,
                ]);
                
                if ($quantityApproved > 0) {
                    $totalApproved += $quantityApproved;
                    
                    // Decrease warehouse stock
                    $warehouseStock->decrement('quantity', $baseUnitsApproved);

                    // Add warehouse transaction for auditing
                    $warehouseStock->addTransaction([
                        'transaction_type' => 'transfer',
                        'quantity' => -$baseUnitsApproved,
                        'unit_cost' => $warehouseStock->purchase_price,
                        'total_cost' => $baseUnitsApproved * $warehouseStock->purchase_price,
                        'notes' => "Transfer to bar: " . ($transferRequest->bar->name ?? 'Unknown'),
                        'transaction_date' => now(),
                    ]);
                    
                    // Increase director/bar stock for the corresponding item
                    // Use the item_id from the transfer request item
                    $item = \App\Models\Item::find($transferItem->item_id);
                    
                    \Log::info('Item lookup result', [
                        'transfer_item_id' => $transferItem->id,
                        'transfer_item_item_id' => $transferItem->item_id,
                        'warehouse_stock_item_name' => $warehouseStock->item_name,
                        'item_found' => $item !== null,
                        'item_id' => $item?->id,
                        'item_name' => $item?->name,
                    ]);
                    
                    if ($item) {
                        $item->increment('director_stock', $baseUnitsApproved);
                        
                        // Create ledger entry for transfer (wrap in try-catch in case table doesn't exist)
                        try {
                            $item->addLedgerEntry([
                                'bar_id' => $transferRequest->bar_id,
                                'action_type' => 'transfer_in',
                                'quantity' => $baseUnitsApproved,
                                'unit_cost' => $warehouseStock->purchase_price,
                                'total_cost' => $baseUnitsApproved * $warehouseStock->purchase_price,
                                'balance_after' => $item->director_stock,
                                'reference_type' => 'warehouse_transfer',
                                'reference_id' => $transferRequest->id,
                                'transaction_date' => now(),
                            ]);
                        } catch (\Exception $ledgerError) {
                            \Log::warning('Ledger entry failed (table may not exist)', [
                                'error' => $ledgerError->getMessage(),
                            ]);
                            // Continue processing even if ledger fails
                        }

                        // Ensure the transferred quantity is reflected in today's bar stock entry as well.
                        \Log::info('Transfer approval - Creating stock entry', [
                            'bar_id' => $transferRequest->bar_id,
                            'item_id' => $item->id,
                            'item_name' => $item->name,
                            'quantity' => $baseUnitsApproved,
                        ]);

                        $todayEntry = \App\Models\DailyStockEntry::firstOrCreate([
                            'bar_id' => $transferRequest->bar_id,
                            'date' => now()->format('Y-m-d'),
                        ], [
                            'user_id' => auth()->id(),
                        ]);

                        \Log::info('DailyStockEntry created/found', [
                            'id' => $todayEntry->id,
                            'bar_id' => $todayEntry->bar_id,
                            'date' => $todayEntry->date,
                        ]);

                        $stockEntryItem = $todayEntry->stockEntryItems()
                            ->where('item_id', $item->id)
                            ->first();

                        $barPrice = \App\Models\BarItemPrice::where('bar_id', $transferRequest->bar_id)
                            ->where('item_id', $item->id)
                            ->first()?->price ?? $item->price;

                        \Log::info('Bar price resolved', ['price' => $barPrice]);

                        if ($stockEntryItem) {
                            // Determine purchase price based on warehouse unit or warehouse stock
                            $unitPurchasePrice = $warehouseStock->purchase_price;
                            
                            // Try to get unit-specific purchase price if unit_name is available
                            if ($transferItem->unit_name) {
                                $warehouseUnit = $warehouseStock->units()
                                    ->where('unit_name', $transferItem->unit_name)
                                    ->first();
                                if ($warehouseUnit && $warehouseUnit->purchase_price) {
                                    $unitPurchasePrice = $warehouseUnit->purchase_price;
                                }
                            }

                            $stockEntryItem->ordered_stock += $baseUnitsApproved;
                            $stockEntryItem->total_stock += $baseUnitsApproved;
                            $stockEntryItem->closing_stock += $baseUnitsApproved;
                            $stockEntryItem->price = $barPrice;
                            $stockEntryItem->purchase_price = $unitPurchasePrice;
                            $stockEntryItem->save();
                            
                            \Log::info('StockEntryItem updated', [
                                'id' => $stockEntryItem->id,
                                'closing_stock' => $stockEntryItem->closing_stock,
                                'purchase_price' => $unitPurchasePrice,
                            ]);
                        } else {
                            // Refresh to get the updated director_stock after increment
                            $item->refresh();
                            $openingStock = max(0, $item->director_stock - $baseUnitsApproved);
                            $totalStock = $openingStock + $baseUnitsApproved;

                            // Determine purchase price based on warehouse unit or warehouse stock
                            $unitPurchasePrice = $warehouseStock->purchase_price;
                            
                            // Try to get unit-specific purchase price if unit_name is available
                            if ($transferItem->unit_name) {
                                $warehouseUnit = $warehouseStock->units()
                                    ->where('unit_name', $transferItem->unit_name)
                                    ->first();
                                if ($warehouseUnit && $warehouseUnit->purchase_price) {
                                    $unitPurchasePrice = $warehouseUnit->purchase_price;
                                }
                            }

                            \Log::info('Setting purchase price for StockEntryItem', [
                                'warehouse_stock_purchase_price' => $warehouseStock->purchase_price,
                                'unit_name' => $transferItem->unit_name,
                                'final_purchase_price' => $unitPurchasePrice,
                            ]);

                            $createdItem = \App\Models\StockEntryItem::create([
                                'stock_entry_id' => $todayEntry->id,
                                'item_id' => $item->id,
                                'opening_stock' => $openingStock,
                                'ordered_stock' => $baseUnitsApproved,
                                'total_stock' => $totalStock,
                                'closing_stock' => $totalStock,
                                'sold_quantity' => 0,
                                'sales_amount' => 0,
                                'price' => $barPrice,
                                'purchase_price' => $unitPurchasePrice,
                                'expiry_date' => null,
                            ]);
                            
                            \Log::info('StockEntryItem created', [
                                'id' => $createdItem->id,
                                'stock_entry_id' => $todayEntry->id,
                                'item_id' => $item->id,
                                'closing_stock' => $totalStock,
                                'purchase_price' => $unitPurchasePrice,
                            ]);
                        }
                    }
                }
            }
            
            // Update transfer request status
            if ($totalApproved > 0) {
                $transferRequest->update([
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                    'notes' => $request->notes,
                ]);
            } else {
                $transferRequest->update([
                    'status' => 'rejected',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                    'rejection_reason' => 'No items were approved',
                ]);
            }
            
            DB::commit();
            
            \Log::info('=== TRANSFER APPROVAL COMPLETED ===', [
                'transfer_request_id' => $transferRequest->id,
                'total_approved' => $totalApproved,
            ]);
            
            return redirect()->route('warehouse.transfer-requests')
                ->with('success', 'Transfer request processed successfully!');
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('=== TRANSFER APPROVAL FAILED ===', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error processing transfer request: ' . $e->getMessage());
        }
    }

    /**
     * Reject warehouse transfer request
     */
    public function rejectTransfer(Request $request, \App\Models\WarehouseTransferRequest $transferRequest): RedirectResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);
        
        $transferRequest->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);
        
        return redirect()->route('warehouse.transfer-requests')
            ->with('success', 'Transfer request rejected successfully!');
    }

    /**
     * API: Get available warehouse items with units and branch-specific prices
     */
    public function getAvailableItems(Request $request): \Illuminate\Http\JsonResponse
    {
        $barId = $request->input('bar_id');
        if (!$barId) {
            return response()->json(['error' => 'Bar ID is required'], 400);
        }

        // Fetch all warehouse stocks with units and their prices for the specified bar
        $stocks = WarehouseStock::with(['units'])->get();
        
        $items = $stocks->map(function ($stock) use ($barId) {
            // Find base unit and its bar price
            $baseUnit = $stock->units->firstWhere('is_base_unit', true);
            $basePrice = $stock->selling_price; // default fallback
            
            if ($baseUnit) {
                $barPrice = \App\Models\WarehouseUnitBarPrice::where('warehouse_unit_id', $baseUnit->id)
                    ->where('bar_id', $barId)
                    ->first();
                if ($barPrice) {
                    $basePrice = $barPrice->selling_price;
                }
            }

            // Map units and calculate selling price based on conversion factor relative to base unit
            $units = $stock->units->map(function ($unit) use ($basePrice) {
                return [
                    'id' => $unit->id,
                    'unit_name' => $unit->unit_name,
                    'conversion_factor' => $unit->conversion_factor,
                    'is_base_unit' => $unit->is_base_unit,
                    'price' => (float)($basePrice * $unit->conversion_factor),
                ];
            });

            return [
                'id' => $stock->id,
                'item_name' => $stock->item_name,
                'available_quantity' => $stock->quantity, // in base units
                'purchase_price' => $stock->purchase_price,
                'units' => $units,
            ];
        });

        return response()->json($items);
    }
}
