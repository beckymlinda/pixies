<?php

use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\CreditCustomersController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\DirectorController;
use App\Http\Controllers\StockEntryController;
use App\Http\Controllers\StockExpiryController;
use App\Http\Controllers\ProfitLossController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ActivityLogController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\OrderRequestController;
use App\Http\Controllers\WarehouseStockController;
use App\Http\Controllers\CastelController;
use App\Http\Controllers\DamagedGoodController;
use Illuminate\Support\Facades\Artisan;


Route::get('/run-migrate-fresh-seed', function () {
    try {
        Artisan::call('migrate:fresh', [
            '--seed' => true,
            '--force' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Migration fresh and seeding completed.',
            'output' => Artisan::output(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});

// Castel Bottle Count Routes
Route::middleware('auth')->prefix('castel')->name('castel.')->group(function () {
    Route::get('/', [CastelController::class, 'index'])->name('index');
    Route::post('/reset', [CastelController::class, 'reset'])->name('reset');
});



Route::get('/storage-link', function () {
    Artisan::call('storage:link');

    return Artisan::output() ?: 'Storage link created successfully.';
});
Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->get('/account/pending', function () {
    return view('auth.pending');
})->name('account.pending');

Route::get('/dashboard', function () {
    // This route will redirect based on user role via middleware
    return redirect()->route('seller.dashboard');
})->middleware(['auth', 'verified', 'role.redirect'])->name('dashboard');

// Role-based routes
Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/dashboard', [SellerController::class, 'dashboard'])->name('dashboard');
    Route::get('/orders', [OrderRequestController::class, 'sellerIndex'])->name('orders.index');
    Route::get('/orders/create', [OrderRequestController::class, 'sellerCreate'])->name('orders.create');
    Route::post('/orders', [OrderRequestController::class, 'sellerStore'])->name('orders.store');
});

Route::middleware(['auth', 'role:manager'])->prefix('manager')->name('manager.')->group(function () {
    Route::get('/dashboard', [ManagerController::class, 'dashboard'])->name('dashboard');
});

Route::middleware(['auth', 'role:director'])->prefix('director')->name('director.')->group(function () {
    Route::get('/dashboard', [DirectorController::class, 'dashboard'])->name('dashboard');
    Route::get('/orders', [OrderRequestController::class, 'directorIndex'])->name('orders.index');
    Route::get('/orders/{orderRequest}', [OrderRequestController::class, 'directorShow'])->name('orders.show');
    Route::post('/orders/{orderRequest}/approve', [OrderRequestController::class, 'directorApprove'])->name('orders.approve');
});

// Stock Entry Routes
Route::middleware('auth')->prefix('stock-entries')->name('stock-entries.')->group(function () {
    Route::get('/', [StockEntryController::class, 'index'])->name('index');
    Route::get('/sell', [StockEntryController::class, 'sell'])->name('sell');
    Route::get('/create', [StockEntryController::class, 'create'])->name('create');
    Route::post('/', [StockEntryController::class, 'store'])->name('store');
    Route::get('/{stockEntry}', [StockEntryController::class, 'show'])->name('show');
    Route::get('/{stockEntry}/edit', [StockEntryController::class, 'edit'])->name('edit');
    Route::put('/{stockEntry}', [StockEntryController::class, 'update'])->name('update');
    Route::delete('/{stockEntry}', [StockEntryController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'role:director'])->prefix('director-stock-entries')->name('director-stock-entries.')->group(function () {
    Route::get('/', [StockEntryController::class, 'directorIndex'])->name('index');
    Route::get('/create', [StockEntryController::class, 'directorCreate'])->name('create');
    Route::post('/', [StockEntryController::class, 'directorStore'])->name('store');
});

// Manager / Director Stock Overview
Route::middleware(['auth', 'role:manager,director'])->prefix('stock')->name('stock.')->group(function () {
    Route::get('/', [StockEntryController::class, 'stockOverview'])->name('index');
    Route::post('/update', [StockEntryController::class, 'updateStock'])->name('update');
    Route::post('/add', [StockEntryController::class, 'addStock'])->name('add');
    Route::post('/restock', [StockEntryController::class, 'restockStock'])->name('restock');
    Route::get('/delete', [StockEntryController::class, 'deleteStock'])->name('delete');
});

// Warehouse Transfer Request Routes
Route::middleware(['auth', 'role:director'])->prefix('warehouse-transfers')->name('warehouse-transfers.')->group(function () {
    Route::post('/request', [StockEntryController::class, 'createWarehouseRequest'])->name('create');
});

// Expenses Routes
Route::middleware('auth')->prefix('expenses')->name('expenses.')->group(function () {
    Route::get('/', [ExpenseController::class, 'index'])->name('index');
    Route::get('/date/{date}', [ExpenseController::class, 'daily'])->name('daily');
    Route::get('/create', [ExpenseController::class, 'create'])->name('create');
    Route::post('/', [ExpenseController::class, 'store'])->name('store');
    Route::get('/{expense}', [ExpenseController::class, 'show'])->name('show');
    Route::get('/{expense}/edit', [ExpenseController::class, 'edit'])->name('edit');
    Route::put('/{expense}', [ExpenseController::class, 'update'])->name('update');
    Route::delete('/{expense}', [ExpenseController::class, 'destroy'])->name('destroy');
});

// Stock Expiry Routes
Route::middleware('auth')->prefix('stock-expiry')->name('stock-expiry.')->group(function () {
    Route::get('/', [StockExpiryController::class, 'index'])->name('index');
});

// Profit & Loss Routes
Route::middleware('auth')->prefix('profit-loss')->name('profit-loss.')->group(function () {
    Route::get('/', [ProfitLossController::class, 'index'])->name('index');
    Route::get('/daily', [ProfitLossController::class, 'getDaily'])->name('daily');
});

// Reports Routes (Manager and Director only)
Route::middleware('auth')->prefix('reports')->name('reports.')->group(function () {
    Route::get('/export', [ReportsController::class, 'export'])->name('export');
    Route::get('/', [ReportsController::class, 'index'])->name('dashboard');
});

// Damaged Goods (read-only log from Balance page)
Route::middleware('auth')->prefix('damaged-goods')->name('damaged-goods.')->group(function () {
    Route::get('/', [DamagedGoodController::class, 'index'])->name('index');
    Route::get('/{damagedGood}/photo', [DamagedGoodController::class, 'photo'])->name('photo');
});

// Reporting Routes (Bar Seller, Manager, Director only)
 Route::middleware('auth')->prefix('reporting')->name('reporting.')->group(function () {
    Route::get('/export', [ReportsController::class, 'reportingExport'])->name('export');
    Route::get('/', [ReportsController::class, 'reportingIndex'])->name('index');
    Route::get('/create', [ReportsController::class, 'reportingCreate'])->name('create');
    Route::post('/', [ReportsController::class, 'reportingStore'])->name('store');
    Route::get('/{dailyReport}', [ReportsController::class, 'reportingShow'])->name('show')->where('dailyReport', '[0-9]+');
    Route::get('/{dailyReport}/edit', [ReportsController::class, 'reportingEdit'])->name('edit')->where('dailyReport', '[0-9]+');
    Route::put('/{dailyReport}', [ReportsController::class, 'reportingUpdate'])->name('update')->where('dailyReport', '[0-9]+');
    Route::delete('/{dailyReport}', [ReportsController::class, 'reportingDestroy'])->name('destroy')->where('dailyReport', '[0-9]+');
});
// Credit Customers Routes (Bar Seller, Manager, Director only)
Route::middleware('auth')->prefix('credit-customers')->name('credit-customers.')->group(function () {
    Route::get('/', [CreditCustomersController::class, 'index'])->name('index');
    Route::get('/export', [CreditCustomersController::class, 'export'])->name('export');
    Route::get('/create', [CreditCustomersController::class, 'create'])->name('create');
    Route::post('/', [CreditCustomersController::class, 'store'])->name('store');
    Route::get('/{customerName}', [CreditCustomersController::class, 'show'])->name('show');
    Route::get('/{customerName}/payment', [CreditCustomersController::class, 'payment'])->name('payment');
    Route::post('/{customerName}/payment', [CreditCustomersController::class, 'recordPayment'])->name('record-payment');
    Route::get('/{customerTab}/edit', [CreditCustomersController::class, 'edit'])->name('edit');
    Route::put('/{customerTab}', [CreditCustomersController::class, 'update'])->name('update');
    Route::delete('/{customerTab}', [CreditCustomersController::class, 'destroy'])->name('destroy');
});

// Reconciliation Routes (Manager and Director only)
Route::middleware('auth')->prefix('reconciliation')->name('reconciliation.')->group(function () {
    Route::get('/', [ReconciliationController::class, 'index'])->name('index');
    Route::get('/verify/{stockEntry}', [ReconciliationController::class, 'verify'])->name('verify');
    Route::post('/verify/{stockEntry}', [ReconciliationController::class, 'store'])->name('store');
    Route::get('/history', [ReconciliationController::class, 'history'])->name('history');
});

// Warehouse Stock Routes
Route::middleware('auth')->prefix('warehouse')->name('warehouse.')->group(function () {
    Route::get('/', [WarehouseStockController::class, 'index'])->name('index');
    Route::get('/create', [WarehouseStockController::class, 'create'])->name('create');
    Route::post('/', [WarehouseStockController::class, 'store'])->name('store');
    Route::get('/alerts/expiry', [WarehouseStockController::class, 'expiryAlerts'])->name('alerts.expiry');
    Route::get('/alerts/low-stock', [WarehouseStockController::class, 'lowStockAlerts'])->name('alerts.low-stock');
    Route::get('/api/available-items', [WarehouseStockController::class, 'getAvailableItems'])->name('api-available-items');
    Route::get('/transfer-requests', [WarehouseStockController::class, 'transferRequests'])->name('transfer-requests');
    Route::post('/transfer-requests/{transferRequest}/approve', [WarehouseStockController::class, 'approveTransfer'])->name('transfer-requests.approve');
    Route::post('/transfer-requests/{transferRequest}/reject', [WarehouseStockController::class, 'rejectTransfer'])->name('transfer-requests.reject');
    Route::post('/transfer-requests/{transferRequest}/revert', [WarehouseStockController::class, 'revertTransfer'])->name('transfer-requests.revert');
    Route::post('/transfer-requests/{transferRequest}/quick-approve', [WarehouseStockController::class, 'quickApproveTransfer'])->name('transfer-requests.quick-approve');
    Route::get('/{warehouseStock}', [WarehouseStockController::class, 'show'])->name('show');
    Route::get('/{warehouseStock}/edit', [WarehouseStockController::class, 'edit'])->name('edit');
    Route::get('/{warehouseStock}/restock', [WarehouseStockController::class, 'restock'])->name('restock');
    Route::post('/{warehouseStock}/restock', [WarehouseStockController::class, 'processRestock'])->name('processRestock');
    Route::put('/{warehouseStock}', [WarehouseStockController::class, 'update'])->name('update');
    Route::delete('/{warehouseStock}', [WarehouseStockController::class, 'destroy'])->name('destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Activity Log Routes (Manager and Director only)
Route::middleware(['auth', 'role:manager,director'])->prefix('activity-logs')->name('activity-logs.')->group(function () {
    Route::get('/', [ActivityLogController::class, 'index'])->name('index');
});

require __DIR__.'/auth.php';
