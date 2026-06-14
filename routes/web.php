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
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\OrderRequestController;
use App\Http\Controllers\WarehouseStockController;

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
    Route::get('/create', [StockEntryController::class, 'create'])->name('create');
    Route::post('/', [StockEntryController::class, 'store'])->name('store');
    Route::get('/{stockEntry}', [StockEntryController::class, 'show'])->name('show');
    Route::get('/{stockEntry}/edit', [StockEntryController::class, 'edit'])->name('edit');
    Route::put('/{stockEntry}', [StockEntryController::class, 'update'])->name('update');
});

// Director Stock Entry Routes (Director only)
Route::middleware(['auth', 'role:director,manager'])->prefix('director-stock-entries')->name('director-stock-entries.')->group(function () {
    Route::get('/', [StockEntryController::class, 'directorIndex'])->name('index');
    Route::get('/create', [StockEntryController::class, 'directorCreate'])->name('create');
    Route::post('/', [StockEntryController::class, 'directorStore'])->name('store');
});

// Manager / Director Stock Overview
Route::middleware(['auth', 'role:manager,director'])->prefix('stock')->name('stock.')->group(function () {
    Route::get('/', [StockEntryController::class, 'stockOverview'])->name('index');
});

// Simple Routes for Item Management
Route::middleware(['auth', 'role:director'])->prefix('items')->name('items.')->group(function () {
    Route::post('/create', [StockEntryController::class, 'createItem'])->name('create');
    Route::get('/{itemId}/edit', [StockEntryController::class, 'editItem'])->name('edit');
    Route::put('/{itemId}', [StockEntryController::class, 'updateItem'])->name('update');
    Route::delete('/{itemId}', [StockEntryController::class, 'deleteItem'])->name('delete');
    Route::post('/{itemId}/restock', [StockEntryController::class, 'restockItem'])->name('restock');
    Route::get('/{itemId}/ledger', [StockEntryController::class, 'getItemLedger'])->name('ledger');
});

// Stock History Routes
Route::middleware(['auth', 'role:director'])->prefix('items')->name('items.')->group(function () {
    Route::get('/{itemId}/stock-history', [StockEntryController::class, 'getStockHistory'])->name('stock-history');
});

// Warehouse Transfer Request Routes
Route::middleware(['auth', 'role:director'])->prefix('warehouse-transfers')->name('warehouse-transfers.')->group(function () {
    Route::post('/request', [StockEntryController::class, 'createWarehouseRequest'])->name('create');
});

// API Routes for Item Management
Route::middleware(['auth', 'role:director'])->prefix('api')->name('api.')->group(function () {
    Route::get('/items/by-bar/{barId}', [StockEntryController::class, 'getItemsByBar'])->name('items.by-bar');
    Route::get('/items/{itemId}', [StockEntryController::class, 'getItem'])->name('items.show');
    Route::post('/items', [StockEntryController::class, 'createItem'])->name('items.create');
    Route::put('/items/{itemId}', [StockEntryController::class, 'updateItem'])->name('items.update');
    Route::delete('/items/{itemId}', [StockEntryController::class, 'deleteItem'])->name('items.delete');
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
    Route::get('/', [ReportsController::class, 'index'])->name('dashboard');
});

// Reporting Routes (Bar Seller, Manager, Director only)
 Route::middleware('auth')->prefix('reporting')->name('reporting.')->group(function () {
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
    Route::get('/create', [CreditCustomersController::class, 'create'])->name('create');
    Route::post('/', [CreditCustomersController::class, 'store'])->name('store');
    Route::get('/{customerName}', [CreditCustomersController::class, 'show'])->name('show');
    Route::get('/{customerName}/payment', [CreditCustomersController::class, 'payment'])->name('payment');
    Route::post('/{customerName}/payment', [CreditCustomersController::class, 'recordPayment'])->name('record-payment');
    Route::get('/{customerTab}/edit', [CreditCustomersController::class, 'edit'])->name('edit');
    Route::put('/{customerTab}', [CreditCustomersController::class, 'update'])->name('update');
    Route::delete('/{customerTab}', [CreditCustomersController::class, 'destroy'])->name('destroy');
    Route::get('/export', [CreditCustomersController::class, 'export'])->name('export');
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

require __DIR__.'/auth.php';
