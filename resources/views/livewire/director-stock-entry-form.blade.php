<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<div class="min-h-screen bg-gray-50">
    <!-- Connection Status Bar -->
    <div class="bg-white border-b px-4 py-2 flex justify-between items-center">
        <div class="text-sm">
            <span data-online-status class="font-medium">🌐 Online</span>
        </div>
        <div id="offline-notice" class="text-xs text-orange-600" style="display:none;">
            💾 Unsaved data available
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                try {
                    if (window.offlineHandler && typeof window.offlineHandler.hasUnsavedData === 'function' && window.offlineHandler.hasUnsavedData()) {
                        var el = document.getElementById('offline-notice');
                        if (el) el.style.display = 'block';
                    }
                } catch (e) {
                    // fail silently on server-render or missing handler
                }
            });
        </script>
    </div>

    <!-- Header Section -->
    <div class="bg-white shadow-sm border-b">
        <div class="px-4 py-4">
            <h1 class="text-xl font-bold text-gray-900">Director Daily Stock Entry</h1>
            
            <div class="mt-3 grid grid-cols-1 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Date</label>
                    <input type="date" wire:model="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-lg py-3">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Bar</label>
                        <div class="mt-1 text-lg font-semibold text-blue-600">{{ $bar->name }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Director</label>
                        <div class="mt-1 text-lg font-semibold text-gray-900">{{ Auth::user()->name }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Opening Stock Items Table -->
    <div class="px-4 py-4">
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-3 bg-yellow-50 border-b sticky top-0 z-10">
                <div class="flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-900">All Stock Items (Director Entry)</h2>
                    <div class="text-sm text-yellow-600">
                        <i class="bi bi-pencil-square me-1"></i> Editable Opening Stock
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="text-sm text-muted">
                            <i class="bi bi-box me-1"></i> Total Items: {{ count($stockItems) }}
                        </div>
                        <div class="d-flex gap-2">
                            <button wire:click="addNewStockItem" 
                                    class="btn btn-sm btn-success">
                                <i class="bi bi-plus-circle me-1"></i> Add Item
                            </button>
                            <button wire:click="refreshStockItems" 
                                    class="btn btn-sm btn-secondary">
                                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="divide-y divide-gray-200">
                @foreach($stockItems as $index => $item)
                    <div class="p-4 {{ $item['category'] == 'beer' ? 'bg-yellow-50' : ($item['category'] == 'spirit' ? 'bg-purple-50' : 'bg-blue-50') }}">
                        <!-- Item Header -->
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <h3 class="font-semibold text-gray-900">{{ $item['item_name'] }}</h3>
                                <div class="text-xs text-gray-500">{{ $item['category'] }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-lg font-bold text-blue-600">{{ number_format($item['price']) }}</div>
                                <div class="text-xs text-gray-500">Price</div>
                            </div>
                        </div>
                        
                        <!-- Director Opening Stock Input (Editable) -->
                        <div class="grid grid-cols-4 gap-3 mb-3">
                            <div class="text-center">
                                <label class="block text-xs text-gray-600 mb-1">Opening Stock</label>
                                <input type="number" 
                                       wire:model.live="stockItems.{{ $index }}.opening_stock"
                                       min="0"
                                       class="w-full text-center text-lg font-bold rounded-lg border-yellow-300 shadow-sm focus:border-yellow-500 focus:ring-yellow-500 py-2 bg-yellow-50">
                            </div>
                            <div class="text-center">
                                <label class="block text-xs text-gray-600 mb-1">Ordered</label>
                                <input type="number" 
                                       wire:model.live="stockItems.{{ $index }}.ordered_stock"
                                       min="0"
                                       class="w-full text-center text-lg font-bold rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2">
                            </div>
                            <div class="text-center">
                                <label class="block text-xs text-gray-600 mb-1">Sold</label>
                                <input type="number" 
                                       wire:model.live="stockItems.{{ $index }}.sold_quantity"
                                       min="0"
                                       class="w-full text-center text-lg font-bold rounded-lg border-green-300 shadow-sm focus:border-green-500 focus:ring-green-500 py-2">
                            </div>
                            <div class="text-center">
                                <label class="block text-xs text-gray-600 mb-1">Closing</label>
                                <div class="w-full text-center text-lg font-bold rounded-lg bg-gray-100 border-gray-300 py-2">
                                    {{ $this->getComputedClosingStock($index) }}
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    Auto: {{ $item['total_stock'] ?? 0 }} - {{ $item['sold_quantity'] ?? 0 }}
                                </div>
                            </div>
                        </div>

                        <!-- Cost and Expiry -->
                        <div class="grid grid-cols-2 gap-3 mt-3">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Purchase Price</label>
                                <input type="number"
                                       wire:model.lazy="stockItems.{{ $index }}.purchase_price"
                                       step="0.01"
                                       min="0"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Expiry Date</label>
                                <input type="date"
                                       wire:model.lazy="stockItems.{{ $index }}.expiry_date"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2">
                            </div>
                        </div>
                        
                        <!-- Results -->
                        <div class="grid grid-cols-3 gap-3 text-center">
                            <div class="bg-white rounded-lg p-2">
                                <div class="text-lg font-bold text-blue-600">{{ $item['total_stock'] }}</div>
                                <div class="text-xs text-gray-600">Total</div>
                            </div>
                            <div class="bg-white rounded-lg p-2">
                                <div class="text-lg font-bold text-green-600">{{ $item['sold_quantity'] }}</div>
                                <div class="text-xs text-gray-600">Sold</div>
                            </div>
                            <div class="bg-white rounded-lg p-2">
                                <div class="text-lg font-bold text-green-600">{{ number_format($item['sales_amount'], 0) }}</div>
                                <div class="text-xs text-gray-600">Sales</div>
                            </div>
                        </div>
                        
                        <!-- Item Actions -->
                        <div class="d-flex justify-end gap-2 mt-2">
                            <button wire:click="removeStockItem({{ $index }})" 
                                    class="btn btn-sm btn-danger">
                                <i class="bi bi-trash me-1"></i> Remove
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
            
            <!-- Total Sales Footer -->
            <div class="bg-gray-50 p-4 border-t">
                <div class="flex justify-between items-center">
                    <span class="text-lg font-bold text-gray-900">TOTAL SALES:</span>
                    <span class="text-2xl font-bold text-green-600">{{ number_format($this->getTotalSales(), 0) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Expenses Section -->
    <div class="px-4 py-2">
        <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-3 bg-gray-50 border-b flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900">Expenses</h2>
                <button wire:click="addExpense" class="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">
                    ➕ Add Expense
                </button>
            </div>
            
            <div class="p-4 space-y-3">
                @foreach($expenses as $index => $expense)
                    <div class="flex gap-3 items-center">
                        <select wire:model="expenses.{{ $index }}.type" 
                                class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="lunch">Lunch</option>
                            <option value="taxi">Taxi</option>
                            <option value="damage">Damage</option>
                            <option value="debt">Debt</option>
                            <option value="other">Other</option>
                        </select>
                        
                        <input type="number" 
                               wire:model="expenses.{{ $index }}.amount"
                               min="0"
                               step="0.01"
                               placeholder="Amount"
                               class="w-24 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        
                        <input type="text" 
                               wire:model="expenses.{{ $index }}.description"
                               placeholder="Note (optional)"
                               class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        
                        @if(count($expenses) > 1)
                            <button wire:click="removeExpense({{ $index }})" 
                                    class="px-2 py-1 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm">
                                🗑️
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
            
            <div class="px-4 pb-3 border-t">
                <div class="pt-3 text-right">
                    <span class="font-bold text-gray-900">Total Expenses: </span>
                    <span class="font-bold text-lg text-red-600">{{ number_format($this->getTotalExpenses()) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Electronic Payments Section -->
    <div class="px-4 py-2">
        <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-3 bg-gray-50 border-b">
                <h2 class="text-lg font-semibold text-gray-900">Electronic Payments (Director Tracking)</h2>
            </div>
            
            <div class="p-4 grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Mpamba</label>
                    <input type="number" 
                           wire:model="payments.mpamba"
                           min="0"
                           step="0.01"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700">Airtel Money</label>
                    <input type="number" 
                           wire:model="payments.airtel_money"
                           min="0"
                           step="0.01"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700">Bank</label>
                    <input type="number" 
                           wire:model="payments.bank"
                           min="0"
                           step="0.01"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700">POS</label>
                    <input type="number" 
                           wire:model="payments.pos"
                           min="0"
                           step="0.01"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-lg">
                </div>
            </div>
            
            <div class="px-4 pb-3 border-t">
                <div class="pt-3 grid grid-cols-2 gap-4 text-right">
                    <div>
                        <span class="font-bold text-gray-900">Total Electronic: </span>
                        <span class="font-bold text-lg text-blue-600">{{ number_format($this->getTotalElectronicPayments()) }}</span>
                    </div>
                    <div>
                        <span class="font-bold text-gray-900">Cash Sales: </span>
                        <span class="font-bold text-lg text-green-600">{{ number_format($this->getCashSales()) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary & Submit Section -->
    <div class="fixed bottom-0 left-0 right-0 bg-white border-t shadow-lg z-50">
        <div class="p-4">
            <div class="grid grid-cols-3 gap-2 mb-3">
                <div class="text-center p-2 bg-green-50 rounded-lg">
                    <div class="text-lg font-bold text-green-600">{{ number_format($this->getTotalSales()) }}</div>
                    <div class="text-xs text-gray-600">Sales</div>
                </div>
                <div class="text-center p-2 bg-red-50 rounded-lg">
                    <div class="text-lg font-bold text-red-600">{{ number_format($this->getTotalExpenses()) }}</div>
                    <div class="text-xs text-gray-600">Expenses</div>
                </div>
                <div class="text-center p-2 bg-blue-50 rounded-lg">
                    <div class="text-lg font-bold text-blue-600">{{ number_format($this->getTotalSales() - $this->getTotalExpenses()) }}</div>
                    <div class="text-xs text-gray-600">Profit</div>
                </div>
            </div>
            
            <form method="POST" action="{{ route('director-stock-entries.store') }}" data-stock-form data-redirect="{{ route('director-stock-entries.index') }}">
                @csrf
                <!-- Hidden fields for form data -->
                @foreach($stockItems as $index => $item)
                    <input type="hidden" name="stock_items[{{ $index }}][item_id]" value="{{ $item['item_id'] }}">
                    <input type="hidden" name="stock_items[{{ $index }}][opening_stock]" value="{{ $item['opening_stock'] }}">
                    <input type="hidden" name="stock_items[{{ $index }}][ordered_stock]" value="{{ $item['ordered_stock'] }}">
                    <input type="hidden" name="stock_items[{{ $index }}][total_stock]" value="{{ $item['total_stock'] }}">
                    <input type="hidden" name="stock_items[{{ $index }}][closing_stock]" value="{{ $item['closing_stock'] }}">
                    <input type="hidden" name="stock_items[{{ $index }}][sold_quantity]" value="{{ $item['sold_quantity'] }}">
                    <input type="hidden" name="stock_items[{{ $index }}][sales_amount]" value="{{ $item['sales_amount'] }}">
                    <input type="hidden" name="stock_items[{{ $index }}][purchase_price]" value="{{ $item['purchase_price'] ?? 0 }}">
                    <input type="hidden" name="stock_items[{{ $index }}][expiry_date]" value="{{ $item['expiry_date'] ?? '' }}">
                @endforeach
                
                @foreach($expenses as $index => $expense)
                    <input type="hidden" name="expenses[{{ $index }}][type]" value="{{ $expense['type'] }}">
                    <input type="hidden" name="expenses[{{ $index }}][amount]" value="{{ $expense['amount'] }}">
                    <input type="hidden" name="expenses[{{ $index }}][description]" value="{{ $expense['description'] }}">
                @endforeach
                
                @foreach($payments as $type => $amount)
                    <input type="hidden" name="payments[{{ $type }}]" value="{{ $amount }}">
                @endforeach
                
                <input type="hidden" name="date" value="{{ $date }}">
                <input type="hidden" name="is_director_entry" value="1">
                
                <div class="flex gap-2">
                    <button type="submit" 
                            class="flex-1 py-3 bg-green-600 text-white font-bold rounded-lg hover:bg-green-700 text-lg">
                        💾 Save Director Stock Entry
                    </button>
                    
                    <button type="button" 
                            data-retry-submit
                            class="hidden px-4 py-3 bg-orange-600 text-white font-bold rounded-lg hover:bg-orange-700 text-lg">
                        🔄 Retry
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Error Messages -->
    @if ($errors->any())
        <div class="fixed bottom-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg max-w-sm">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <!-- Success/Error Messages -->
    @if (session()->has('success'))
        <div class="fixed bottom-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="fixed bottom-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
            {{ session('error') }}
        </div>
    @endif
</div>

<!-- Modern CSS Styles -->
<style>
/* ======================================
   MODERN BOOTSTRAP REACT-LIKE STYLES
================================ */

:root {
    --primary: #2563eb;
    --primary-dark: #1e40af;
    --primary-light: #3b82f6;
    --warning: #f59e0b;
    --danger: #dc2626;
    --success: #16a34a;
    --purple: #9333ea;
    --indigo: #6366f1;
    --bg: #f8fafc;
    --card: #ffffff;
    --border: #e2e8f0;
    --text: #1e293b;
    --muted: #64748b;
    --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
}

/* Page background */
body {
    background: var(--bg);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.875rem;
    line-height: 1.5;
    color: var(--text);
}

/* ================================
   INPUT STYLING
================================ */

input[type="number"], input[type="text"], input[type="date"], select {
    border-radius: 0.5rem;
    transition: all 0.15s ease-in-out;
}

input[type="number"]:focus, input[type="text"]:focus, input[type="date"]:focus, select:focus {
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    transform: translateY(-1px);
}

/* ================================
   BUTTONS
================================ */

button {
    border-radius: 0.5rem;
    font-weight: 500;
    transition: all 0.15s ease-in-out;
}

button:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

/* ================================
   YELLOW HIGHLIGHT FOR DIRECTOR OPENING STOCK
================================ */

.bg-yellow-50 {
    background-color: rgba(251, 191, 36, 0.1) !important;
}

.border-yellow-300 {
    border-color: #fbbf24 !important;
}

.focus\:border-yellow-500:focus {
    border-color: #f59e0b !important;
}

.focus\:ring-yellow-500:focus {
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1) !important;
}

/* ================================
   ANIMATIONS
================================ */

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.bg-white {
    animation: fadeIn 0.25s ease;
}
</style>
