<?php

namespace App\Http\Controllers;

use App\Models\Bar;
use App\Models\Item;
use App\Models\ProductUnit;
use App\Models\ProductUnitPrice;
use App\Models\WarehouseStock;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
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
        $warehouseStock->load([
            'units.barPrices',
            'transactions' => fn ($query) => $query->with('destinationBar')->orderByDesc('transaction_date'),
        ]);

        $warehouseStock->syncLifetimeMetricsFromLedger();
        
        $bars = \App\Models\Bar::listed()->orderBy('name')->get();
        
        return view('warehouse.show', compact('warehouseStock', 'bars'));
    }

    /**
     * Show restock form
     */
    public function restock(WarehouseStock $warehouseStock): View
    {
        $warehouseStock->load('units');

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
            $baseUnitName = $warehouseStock->units()->where('is_base_unit', true)->value('unit_name') ?? 'Bottle';
            $validated['conversion_factor'] = $this->normalizePurchaseConversionFactor(
                $validated['purchase_unit'],
                $baseUnitName,
                (int) $validated['conversion_factor']
            );

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

            \App\Models\ActivityLog::log([
                'action' => 'warehouse_stock_restocked',
                'description' => "Restocked warehouse item '{$warehouseStock->item_name}': added {$totalBaseUnits} units (new total: {$warehouseStock->quantity})",
                'subject_type' => WarehouseStock::class,
                'subject_id' => $warehouseStock->id,
                'new_values' => [
                    'item_name' => $warehouseStock->item_name,
                    'added_quantity' => $totalBaseUnits,
                    'total_quantity' => $warehouseStock->quantity,
                ],
            ]);

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
            'bottle_bar_selling_prices' => 'nullable|array',
            'shots_per_bottle' => [
                'nullable',
                'integer',
                'min:1',
                Rule::requiredIf(fn () => $request->input('base_unit') === 'Shot' && $request->input('purchase_unit') !== 'Bottle'),
            ],
        ]);

        DB::beginTransaction();
        try {
            $validated['conversion_factor'] = $this->normalizePurchaseConversionFactor(
                $validated['purchase_unit'],
                $validated['base_unit'],
                (int) $validated['conversion_factor']
            );

            // Calculate cost per base unit
            $totalBaseUnits = $validated['quantity_purchased'] * $validated['conversion_factor'];
            $calculatedBaseUnitCost = $totalBaseUnits > 0 ? $validated['total_purchase_cost'] / $totalBaseUnits : 0;

            // Set purchase_price to calculated base unit cost for backward compatibility
            $validated['purchase_price'] = $calculatedBaseUnitCost;
            $validated['calculated_base_unit_cost'] = $calculatedBaseUnitCost;
            $validated['average_unit_cost'] = $calculatedBaseUnitCost;
            $validated['lifetime_quantity_purchased'] = 0;
            $validated['lifetime_quantity_sold'] = 0;
            $validated['lifetime_profit_estimate'] = 0;

            // Set default selling price if not provided (use first bar price)
            if (empty($validated['selling_price'])) {
                $firstBottlePrice = collect($this->filterBottleBarSellingPrices($request->input('bottle_bar_selling_prices', [])))->first();
                if ($firstBottlePrice > 0) {
                    $validated['selling_price'] = $firstBottlePrice;
                } elseif (! empty($validated['bar_selling_prices'])) {
                    $firstBarPrice = reset($validated['bar_selling_prices']);
                    if ($firstBarPrice > 0) {
                        $validated['selling_price'] = $firstBarPrice;
                    }
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

            // Create purchase unit record when different from base unit
            if ($validated['purchase_unit'] !== $validated['base_unit']) {
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
                foreach ($this->filterBarSellingPrices($request->bar_selling_prices, $validated['base_unit']) as $barId => $sellingPrice) {
                    \App\Models\WarehouseUnitBarPrice::create([
                        'warehouse_unit_id' => $warehouseUnit->id,
                        'bar_id' => $barId,
                        'selling_price' => $sellingPrice,
                    ]);
                }
            }

            $this->removeUnsupportedBarPrices($warehouseUnit, $validated['base_unit']);

            $this->syncBottleSellingUnit(
                $warehouseStock,
                $validated['base_unit'],
                $calculatedBaseUnitCost,
                $this->filterBottleBarSellingPrices($request->input('bottle_bar_selling_prices', [])),
                $this->resolveShotsPerBottle($request, $validated)
            );

            $this->removeLegacySellingUnits($warehouseStock);
            $this->syncLinkedItemProductUnits($warehouseStock);

            \App\Models\ActivityLog::log([
                'action' => 'warehouse_stock_added',
                'description' => "Added new warehouse stock item '{$warehouseStock->item_name}': {$totalBaseUnits} units",
                'subject_type' => WarehouseStock::class,
                'subject_id' => $warehouseStock->id,
                'new_values' => [
                    'item_name' => $warehouseStock->item_name,
                    'quantity' => $totalBaseUnits,
                    'purchase_price' => $calculatedBaseUnitCost,
                ],
            ]);

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
        $warehouseStock->load(['units.barPrices']);

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
            'bottle_bar_selling_prices' => 'nullable|array',
            'shots_per_bottle' => [
                'nullable',
                'integer',
                'min:1',
                Rule::requiredIf(fn () => $request->input('base_unit') === 'Shot' && $request->input('purchase_unit') !== 'Bottle'),
            ],
        ]);

        DB::beginTransaction();
        try {
            $validated['conversion_factor'] = $this->normalizePurchaseConversionFactor(
                $validated['purchase_unit'],
                $validated['base_unit'],
                (int) $validated['conversion_factor']
            );

            // Calculate cost per base unit
            $totalBaseUnits = $validated['quantity_purchased'] * $validated['conversion_factor'];
            $calculatedBaseUnitCost = $totalBaseUnits > 0 ? $validated['total_purchase_cost'] / $totalBaseUnits : 0;

            // Set purchase_price to calculated base unit cost for backward compatibility
            $validated['purchase_price'] = $calculatedBaseUnitCost;
            $validated['calculated_base_unit_cost'] = $calculatedBaseUnitCost;
            $validated['average_unit_cost'] = $calculatedBaseUnitCost;

            // Set default selling price if not provided (use first bar price)
            if (empty($validated['selling_price'])) {
                $firstBottlePrice = collect($this->filterBottleBarSellingPrices($request->input('bottle_bar_selling_prices', [])))->first();
                if ($firstBottlePrice > 0) {
                    $validated['selling_price'] = $firstBottlePrice;
                } elseif (! empty($validated['bar_selling_prices'])) {
                    $firstBarPrice = reset($validated['bar_selling_prices']);
                    if ($firstBarPrice > 0) {
                        $validated['selling_price'] = $firstBarPrice;
                    }
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

            // Update or create purchase unit (only when different from base unit)
            if ($validated['purchase_unit'] !== $validated['base_unit']) {
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
            } else {
                // Remove stale duplicate purchase-unit row when buying in base units
                $warehouseStock->units()
                    ->where('is_base_unit', false)
                    ->where('unit_name', $validated['purchase_unit'])
                    ->delete();
            }

            // Handle bar-specific selling prices
            if ($request->has('bar_selling_prices') && is_array($request->bar_selling_prices)) {
                foreach ($this->filterBarSellingPrices($request->bar_selling_prices, $validated['base_unit']) as $barId => $sellingPrice) {
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

            $this->removeUnsupportedBarPrices($baseUnit, $validated['base_unit']);

            $this->syncBottleSellingUnit(
                $warehouseStock,
                $validated['base_unit'],
                $calculatedBaseUnitCost,
                $this->filterBottleBarSellingPrices($request->input('bottle_bar_selling_prices', [])),
                $this->resolveShotsPerBottle($request, $validated)
            );

            $this->removeLegacySellingUnits($warehouseStock);
            $this->syncLinkedItemProductUnits($warehouseStock);

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
        $allRequests = \App\Models\WarehouseTransferRequest::with(['bar', 'requestedBy', 'approvedBy', 'items.warehouseStock.units', 'items.item'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('requested_at', 'desc')
            ->get();

        $pendingRequests = $allRequests->where('status', 'pending')->values();
        $completedRequests = $allRequests->whereIn('status', ['approved', 'partially_approved', 'rejected'])->values();

        return view('warehouse.transfer-requests', compact('pendingRequests', 'completedRequests', 'allRequests'));
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
            $bar = Bar::findOrFail($transferRequest->bar_id);
            $totalApproved = 0;
            
            foreach ($request->items as $itemData) {
                \Log::info('Processing transfer item', ['item_data' => $itemData]);
                
                $transferItem = \App\Models\WarehouseTransferRequestItem::findOrFail($itemData['id']);
                $warehouseStock = $transferItem->warehouseStock;
                $quantityApproved = $itemData['quantity_approved'];

                if ($quantityApproved > 0 && $transferItem->unit_name && ! $bar->allowsWarehouseTransferUnit($transferItem->unit_name)) {
                    throw new \Exception("Unit \"{$transferItem->unit_name}\" is not allowed for {$bar->name}. Only Bar B can receive Shot units.");
                }
                
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
                        'destination_bar_id' => $transferRequest->bar_id,
                        'notes' => 'Transfer to bar: '.($transferRequest->bar->name ?? 'Unknown'),
                        'transaction_date' => now(),
                    ]);
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

                \App\Models\ActivityLog::log([
                    'action' => 'warehouse_transfer_approved',
                    'description' => "Approved warehouse transfer request #{$transferRequest->id} for bar: ".($transferRequest->bar->name ?? 'Unknown'),
                    'subject_type' => \App\Models\WarehouseTransferRequest::class,
                    'subject_id' => $transferRequest->id,
                    'new_values' => ['bar' => $transferRequest->bar->name ?? 'Unknown', 'approved_items' => $totalApproved],
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
     * Revert an approved warehouse transfer â€” return stock to warehouse and deduct from bar.
     */
    public function revertTransfer(Request $request, \App\Models\WarehouseTransferRequest $transferRequest): RedirectResponse
    {
        if (! $transferRequest->isApproved()) {
            return redirect()->back()->with('error', 'Only approved transfers can be reverted.');
        }

        $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            $transferRequest->load(['bar', 'items.warehouseStock', 'items.item']);

            foreach ($transferRequest->items as $transferItem) {
                $quantityApproved = (int) $transferItem->quantity_approved;
                if ($quantityApproved <= 0) {
                    continue;
                }

                $baseUnits = $quantityApproved * ($transferItem->conversion_factor ?? 1);
                $warehouseStock = $transferItem->warehouseStock;
                $item = $transferItem->item;

                $warehouseStock->increment('quantity', $baseUnits);
                $warehouseStock->addTransaction([
                    'transaction_type' => 'transfer',
                    'quantity' => $baseUnits,
                    'unit_cost' => $warehouseStock->purchase_price,
                    'total_cost' => $baseUnits * $warehouseStock->purchase_price,
                    'destination_bar_id' => $transferRequest->bar_id,
                    'notes' => 'Revert transfer from bar: '.($transferRequest->bar->name ?? 'Unknown'),
                    'transaction_date' => now(),
                ]);

                if ($item) {
                    $newDirectorStock = max(0, (float) $item->director_stock - $baseUnits);
                    $item->update(['director_stock' => $newDirectorStock]);

                    try {
                        $item->addLedgerEntry([
                            'bar_id' => $transferRequest->bar_id,
                            'action_type' => 'transfer_out',
                            'quantity' => -$baseUnits,
                            'unit_cost' => $warehouseStock->purchase_price,
                            'total_cost' => $baseUnits * $warehouseStock->purchase_price,
                            'balance_after' => $newDirectorStock,
                            'reference_type' => 'warehouse_transfer_revert',
                            'reference_id' => $transferRequest->id,
                            'notes' => 'Transfer reverted',
                            'transaction_date' => now(),
                        ]);
                    } catch (\Exception $ledgerError) {
                        \Log::warning('Ledger revert entry failed', ['error' => $ledgerError->getMessage()]);
                    }

                    $this->deductBarStockAfterRevert($transferRequest->bar_id, $item->id, $baseUnits);
                }

                $transferItem->update(['quantity_approved' => 0]);
            }

            $transferRequest->update([
                'status' => 'rejected',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'rejection_reason' => $request->input('rejection_reason', 'Transfer reverted / disapproved'),
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Transfer reverted. Stock returned to warehouse and deducted from bar.');
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->with('error', 'Error reverting transfer: '.$e->getMessage());
        }
    }

    /**
     * Quick-approve all requested quantities on a pending transfer.
     */
    public function quickApproveTransfer(\App\Models\WarehouseTransferRequest $transferRequest): RedirectResponse
    {
        if (! $transferRequest->isPending()) {
            return redirect()->back()->with('error', 'Only pending transfers can be approved.');
        }

        $items = $transferRequest->items->map(fn ($item) => [
            'id' => $item->id,
            'quantity_approved' => $item->quantity_requested,
        ])->values()->all();

        return $this->approveTransfer(
            request()->merge(['items' => $items, 'notes' => 'Quick approved']),
            $transferRequest
        );
    }

    private function deductBarStockAfterRevert(int $barId, int $itemId, float $baseUnits): void
    {
        $entry = \App\Models\Sale::where('bar_id', $barId)
            ->where('date', now()->format('Y-m-d'))
            ->first();

        if (! $entry) {
            $entry = \App\Models\Sale::where('bar_id', $barId)
                ->whereHas('stockEntryItems', fn ($q) => $q->where('item_id', $itemId))
                ->orderByDesc('date')
                ->first();
        }

        if (! $entry) {
            return;
        }

        $stockItem = $entry->stockEntryItems()->where('item_id', $itemId)->first();
        if (! $stockItem) {
            return;
        }

        $stockItem->ordered_stock = max(0, (float) $stockItem->ordered_stock - $baseUnits);
        $stockItem->total_stock = max(0, (float) $stockItem->total_stock - $baseUnits);
        $stockItem->closing_stock = max(0, (float) $stockItem->closing_stock - $baseUnits);

        if ((float) $stockItem->opening_stock > (float) $stockItem->closing_stock + (float) $stockItem->sold_quantity) {
            $excess = (float) $stockItem->opening_stock - ((float) $stockItem->closing_stock + (float) $stockItem->sold_quantity);
            $stockItem->opening_stock = max(0, (float) $stockItem->opening_stock - min($excess, $baseUnits));
        }

        $stockItem->save();
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

        $bar = Bar::findOrFail($barId);

        // Fetch all warehouse stocks with units and their prices for the specified bar
        $stocks = WarehouseStock::with(['units'])->get();
        
        $items = $stocks->map(function ($stock) use ($barId, $bar) {
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

            $allowedUnits = $this->filterUnitsForBarRequest($stock, $bar);
            $availability = $stock->getRequestAvailabilityForBar($bar);

            // Map units and calculate selling price based on conversion factor relative to base unit
            $units = $allowedUnits->map(function ($unit) use ($basePrice, $availability) {
                $maxInUnit = $unit->conversion_factor > 0
                    ? (int) floor($availability['available_quantity_base'] / $unit->conversion_factor)
                    : (int) $availability['available_quantity_base'];

                return [
                    'id' => $unit->id,
                    'unit_name' => $unit->unit_name,
                    'conversion_factor' => $unit->conversion_factor,
                    'is_base_unit' => $unit->is_base_unit,
                    'price' => (float)($basePrice * $unit->conversion_factor),
                    'max_quantity' => $maxInUnit,
                ];
            })->values();

            return [
                'id' => $stock->id,
                'item_name' => $stock->item_name,
                'available_quantity' => $availability['available_quantity'],
                'available_quantity_base' => $availability['available_quantity_base'],
                'stock_unit' => $availability['stock_unit'],
                'is_out_of_stock' => $availability['is_out_of_stock'],
                'purchase_price' => $stock->purchase_price,
                'units' => $units,
            ];
        });

        return response()->json($items);
    }

    private function filterUnitsForBarRequest(WarehouseStock $stock, Bar $bar)
    {
        return $stock->units->filter(function ($unit) use ($bar) {
            return $bar->allowsWarehouseTransferUnit($unit->unit_name);
        })->values();
    }

    /**
     * When purchase unit matches base unit, conversion is always 1:1.
     */
    private function normalizePurchaseConversionFactor(string $purchaseUnit, string $baseUnit, int $conversionFactor): int
    {
        if ($purchaseUnit === $baseUnit) {
            return 1;
        }

        return max(1, $conversionFactor);
    }

    private function filterBarSellingPrices(array $barSellingPrices, string $baseUnit): array
    {
        $bars = Bar::listed()->get()->keyBy('id');

        return collect($barSellingPrices)
            ->filter(function ($price, $barId) use ($baseUnit, $bars) {
                $bar = $bars->get($barId);

                return $bar
                    && $bar->supportsWarehouseBaseUnit($baseUnit)
                    && is_numeric($price)
                    && $price > 0;
            })
            ->all();
    }

    private function removeUnsupportedBarPrices(\App\Models\WarehouseUnit $unit, string $baseUnit): void
    {
        $unsupportedBarIds = Bar::listed()
            ->get()
            ->filter(fn (Bar $bar) => ! $bar->supportsWarehouseBaseUnit($baseUnit))
            ->pluck('id');

        if ($unsupportedBarIds->isNotEmpty()) {
            $unit->barPrices()->whereIn('bar_id', $unsupportedBarIds)->delete();
        }
    }

    private function resolveShotsPerBottle(Request $request, array $validated): int
    {
        if (($validated['base_unit'] ?? '') !== 'Shot') {
            return 1;
        }

        if (($validated['purchase_unit'] ?? '') === 'Bottle') {
            return max(1, (int) $validated['conversion_factor']);
        }

        return max(1, (int) ($request->input('shots_per_bottle') ?? 25));
    }

    private function syncBottleSellingUnit(
        WarehouseStock $warehouseStock,
        string $baseUnit,
        float $baseUnitCost,
        array $bottleBarPrices,
        int $shotsPerBottle
    ): void {
        if ($baseUnit !== 'Shot') {
            $this->removeBottleSellingUnit($warehouseStock);

            return;
        }

        $shotsPerBottle = max(1, $shotsPerBottle);
        $bottlePurchasePrice = $baseUnitCost * $shotsPerBottle;

        $warehouseStock->refresh();
        $warehouseStock->load('units.barPrices');

        $bottleUnit = $warehouseStock->getBottleSellingUnit();

        if ($bottleUnit) {
            $bottleUnit->update([
                'conversion_factor' => $shotsPerBottle,
                'purchase_price' => $bottlePurchasePrice,
            ]);
        } else {
            $bottleUnit = \App\Models\WarehouseUnit::create([
                'warehouse_stock_id' => $warehouseStock->id,
                'unit_name' => 'Bottle',
                'conversion_factor' => $shotsPerBottle,
                'is_base_unit' => false,
                'purchase_price' => $bottlePurchasePrice,
            ]);
        }

        $submittedBarIds = array_map('intval', array_keys($bottleBarPrices));

        foreach ($bottleBarPrices as $barId => $price) {
            $barPrice = $bottleUnit->barPrices()->where('bar_id', $barId)->first();
            if ($barPrice) {
                $barPrice->update(['selling_price' => $price]);
            } else {
                \App\Models\WarehouseUnitBarPrice::create([
                    'warehouse_unit_id' => $bottleUnit->id,
                    'bar_id' => $barId,
                    'selling_price' => $price,
                ]);
            }
        }

        if ($submittedBarIds !== []) {
            $bottleUnit->barPrices()
                ->whereNotIn('bar_id', $submittedBarIds)
                ->delete();
        }
    }

    private function filterBottleBarSellingPrices(array $prices): array
    {
        $bars = Bar::listed()->get()->keyBy('id');

        return collect($prices)
            ->filter(function ($price, $barId) use ($bars) {
                return $bars->has((int) $barId)
                    && is_numeric($price)
                    && (float) $price > 0;
            })
            ->map(fn ($price) => (float) $price)
            ->all();
    }

    private function removeBottleSellingUnit(WarehouseStock $warehouseStock): void
    {
        if ($warehouseStock->purchase_unit === 'Bottle') {
            return;
        }

        $warehouseStock->units()
            ->where('unit_name', 'Bottle')
            ->where('is_base_unit', false)
            ->each(function (\App\Models\WarehouseUnit $unit) {
                $unit->barPrices()->delete();
                $unit->delete();
            });
    }

    private function removeLegacySellingUnits(WarehouseStock $warehouseStock): void
    {
        $keepUnits = array_values(array_unique(array_filter([
            $warehouseStock->purchase_unit,
            'Bottle',
            'Shot',
        ])));

        $warehouseStock->units()
            ->where('is_base_unit', false)
            ->whereNotIn('unit_name', $keepUnits)
            ->each(function (\App\Models\WarehouseUnit $unit) {
                $unit->barPrices()->delete();
                $unit->delete();
            });
    }

    private function syncLinkedItemProductUnits(WarehouseStock $warehouseStock): void
    {
        $item = Item::where('name', $warehouseStock->item_name)->first();
        if (! $item) {
            return;
        }

        $warehouseStock->load('units.barPrices');

        ProductUnitPrice::where('item_id', $item->id)->delete();
        ProductUnit::where('item_id', $item->id)->delete();

        foreach ($warehouseStock->units as $wUnit) {
            if (! in_array($wUnit->unit_name, ['Bottle', 'Shot'], true) && ! $wUnit->is_base_unit) {
                continue;
            }

            ProductUnit::create([
                'item_id' => $item->id,
                'unit_name' => $wUnit->unit_name,
                'conversion_factor' => $wUnit->conversion_factor,
                'is_base_unit' => $wUnit->is_base_unit,
            ]);

            $sellingPrice = $wUnit->barPrices->first()?->selling_price
                ?? ($warehouseStock->selling_price * ($wUnit->is_base_unit ? 1 : $wUnit->conversion_factor));

            if ($sellingPrice > 0) {
                ProductUnitPrice::create([
                    'item_id' => $item->id,
                    'unit_name' => $wUnit->unit_name,
                    'selling_price' => $sellingPrice,
                    'purchase_price' => $wUnit->purchase_price,
                ]);
            }
        }
    }
}

