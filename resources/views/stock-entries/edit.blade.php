@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .edit-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: 0;
        margin-left: 0;
        margin-right: 0;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }
    .stock-table th {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        background: #f8fafc;
        padding: 12px 8px !important;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .stock-table td {
        padding: 12px 8px !important;
        vertical-align: middle !important;
        font-size: 0.875rem;
    }
    .form-control-stock {
        border: 1px solid #cbd5e1;
        text-align: center;
        padding: 0.4rem;
        border-radius: 6px;
        font-weight: 600;
        width: 100%;
    }
    .form-control-stock:focus {
        border-color: var(--pixies-primary);
        box-shadow: 0 0 0 3px rgba(30, 41, 59, 0.1);
    }
    .sticky-footer-summary {
        position: sticky;
        bottom: 0;
        background: #1e293b;
        color: white;
        margin-left: 0;
        margin-right: 0;
        padding: 1rem 2rem;
        z-index: 100;
        border-top: 2px solid #334155;
    }
    .table-search { width: 220px; }
    .btn-clear-sale {
        width: 28px;
        height: 28px;
        min-width: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        border: 1px solid #fecaca;
        background: #fef2f2;
        color: #dc2626;
        font-size: 0.85rem;
        font-weight: 700;
        line-height: 1;
        transition: all 0.2s;
        cursor: pointer;
        flex-shrink: 0;
    }
    .btn-clear-sale:hover {
        background: #fee2e2;
        border-color: #f87171;
        color: #b91c1c;
        transform: scale(1.1);
    }
    .btn-clear-sale:active {
        transform: scale(0.95);
    }
    @media (max-width: 768px) {
        .edit-header { flex-direction: column; align-items: flex-start !important; gap: 1rem; padding: 1rem; }
        .table-search { width: 100%; }
        .stock-table thead { display: none; }
        .stock-table tr { display: block; border-bottom: 2px solid #e2e8f0; padding: 15px 0; }
        .stock-table td { display: flex; justify-content: space-between; align-items: center; border: none !important; padding: 6px 0 !important; }
        .stock-table td::before { content: attr(data-label); font-weight: 700; font-size: 0.75rem; color: #64748b; text-transform: uppercase; }
        .stock-table td .form-control-stock { width: 120px; }
        .sticky-footer-summary { padding: 1rem; flex-direction: column; gap: 0.75rem; padding: 1rem 2rem; }
    }

    /* Reduce sticky footer height on small screens */
    @media (max-width: 576px) {
        .sticky-footer-summary {
            padding: 0.6rem 0.9rem;
            border-top-width: 1px;
        }
        .sticky-footer-summary .h4 { font-size: 1rem; }
        .sticky-footer-summary .btn { padding: 0.45rem 0.9rem; }
    }
</style>

<div class="container-fluid p-0">
    <!-- Modern Header -->
    <div class="edit-header d-flex align-items-center justify-content-between shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('stock-entries.index') }}" class="btn btn-sm btn-light rounded-circle border shadow-sm bg-white">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="h4 fw-bold mb-0 text-dark">{{ auth()->user()->isSeller() ? 'Selling' : 'Edit Sale Record' }}</h1>
                <p class="text-muted small mb-0">{{ auth()->user()->isSeller() ? 'Recording sales for' : 'Adjusting records for' }} <strong>{{ $stockEntry->date->format('M d, Y') }}</strong></p>
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <input type="search" id="edit_table_search" class="form-control form-control-sm table-search me-3" placeholder="Search items">
            <span class="badge bg-primary rounded-pill px-3 py-2">📍 {{ auth()->user()->bar->name ?? 'Main Bar' }}</span>
        </div>
    </div>

    <div class="px-4 pb-5">
        @if(session('success'))
            <div class="alert alert-success border-0 shadow-sm rounded-3 mb-3">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-3">{{ session('error') }}</div>
        @endif
        @if(session('info'))
            <div class="alert alert-info border-0 shadow-sm rounded-3 mb-3">{{ session('info') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-3">
                <strong>Please fix the following:</strong>
                <ul class="mb-0 mt-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form method="POST" action="{{ route('stock-entries.update', $stockEntry) }}" id="editStockForm">
            @csrf @method('PUT')
            
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">Stock Sheet</h5>
                    <span class="text-muted small">Page {{ $currentPage ?? 1 }} of 2</span>
                </div>
                
                <div class="table-responsive">
                    <table class="table stock-table mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Item Details</th>
                                <th class="text-center">Unit</th>
                                <th class="text-center">Price</th>
                                <th class="text-center">Opening</th>
                                <th class="text-center">Orders</th>
                                <th class="text-center">Closing</th>
                                <th class="text-center">Sales</th>
                                <th class="text-end pe-4">Sales Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($paginatedItems as $index => $item)
                                <tr>
                                    <td data-label="Item" class="ps-4">
                                        <div class="fw-bold text-dark">{{ $item['name'] }}</div>
                                        <div class="text-muted" style="font-size: 0.7rem;">{{ $item['category'] }}</div>
                                        <input type="hidden" name="items[{{ $index }}][item_id]" value="{{ $item['id'] }}">
                                        <input type="hidden" name="items[{{ $index }}][price]" value="{{ $item['price'] }}">
                                    </td>
                                    <td data-label="Unit" class="text-center">
                                        <select name="items[{{ $index }}][unit_name]" class="form-control-stock unit-select">
                                            @if(isset($item['product_units']) && $item['product_units']->count() > 0)
                                                @foreach($item['product_units'] as $unit)
                                                    <option value="{{ $unit->unit_name }}" data-price="{{ $unit->price?->selling_price ?? $item['price'] }}" data-conversion="{{ $unit->conversion_factor }}">
                                                        {{ $unit->unit_name }} @if($unit->is_base_unit)(base)@endif
                                                    </option>
                                                @endforeach
                                            @else
                                                <option value="unit" data-price="{{ $item['price'] }}" data-conversion="1">Unit</option>
                                            @endif
                                        </select>
                                    </td>
                                    <td data-label="Price" class="text-center text-muted small">
                                        <span class="price-display">{{ number_format($item['price']) }}</span>
                                    </td>
                                    <td data-label="Opening" class="text-center">
                                        <input type="hidden" name="items[{{ $index }}][opening_stock]" class="opening-stock" value="{{ $item['opening_stock'] }}">
                                        <span class="stock-display fw-semibold">{{ number_format($item['opening_stock_display'] ?? $item['opening_stock']) }}</span>
                                        <div class="small text-muted">{{ $item['stock_unit'] ?? 'units' }}</div>
                                    </td>
                                    <td data-label="Orders" class="text-center">
                                        <input type="hidden" name="items[{{ $index }}][orders]" class="orders" value="{{ $item['ordered_stock'] }}">
                                        <span class="stock-display fw-semibold">{{ number_format($item['ordered_stock_display'] ?? $item['ordered_stock']) }}</span>
                                        <div class="small text-muted">{{ $item['stock_unit'] ?? 'units' }}</div>
                                        @if(($item['ordered_stock_display'] ?? $item['ordered_stock']) > 0)
                                            <div class="small text-success" style="font-size:0.65rem;">✓ Approved</div>
                                        @elseif($item['has_pending_request'] ?? false)
                                            <div class="small text-warning" style="font-size:0.65rem;">⏳ Awaiting approval</div>
                                        @endif
                                    </td>
                                    <td data-label="Closing" class="text-center">
                                        <input type="hidden" name="items[{{ $index }}][closing_stock]" class="closing-stock" value="{{ $item['closing_stock'] }}">
                                        <span class="closing-display fw-semibold">{{ number_format($item['closing_stock_display'] ?? $item['closing_stock']) }}</span>
                                        <div class="small text-muted">{{ $item['stock_unit'] ?? 'units' }}</div>
                                    </td>
                                    <td data-label="Sales" class="text-center">
                                        <div class="small text-muted mb-1">Previous: <strong class="sold-prev-display">{{ number_format($item['sold_quantity_display'] ?? $item['sold_quantity']) }}</strong> {{ $item['stock_unit'] ?? '' }}</div>
                                        <input type="hidden" class="previous-sales" value="{{ $item['sold_quantity'] }}">
                                        @if(!($item['can_sell'] ?? true))
                                            <div class="small text-danger mb-1" style="font-size:0.65rem;">Out of stock</div>
                                            <div class="d-flex align-items-center justify-content-center gap-1">
                                                <input type="number" class="form-control-stock new-sales text-primary" min="0" value="0" placeholder="0" readonly disabled>
                                                <input type="hidden" name="items[{{ $index }}][sales]" class="sales" value="0">
                                            </div>
                                        @else
                                            <div class="d-flex align-items-center justify-content-center gap-1">
                                                <input type="number" class="form-control-stock new-sales text-primary" min="0" value="0" placeholder="0" data-available-base="{{ $item['available_stock'] }}" data-available-display="{{ $item['available_stock_display'] ?? $item['available_stock'] }}">
                                                <input type="hidden" name="items[{{ $index }}][sales]" class="sales" value="0">
                                                <input type="hidden" name="items[{{ $index }}][clear_sales]" class="clear-sales" value="0">
                                                <button type="button" class="btn btn-clear-sale" title="Clear sales for this item" onclick="clearItemSales(this)">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            </div>
                                        @endif
                                    </td>
                                    <td data-label="Total MWK" class="text-end pe-4 fw-bold text-dark">
                                        <input type="hidden" class="sales-amount-val" name="items[{{ $index }}][sales_amount]" value="{{ $item['sales_amount'] }}" data-base="{{ $item['sales_amount'] }}">
                                        <span class="sales-amount-display">{{ number_format($item['sales_amount']) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile-friendly Navigation -->
            <div class="d-flex justify-content-between align-items-center mb-5 mt-3">
                <div>
                    @if(($currentPage ?? 1) > 1)
                        <a href="{{ route('stock-entries.edit', ['stockEntry' => $stockEntry, 'page' => (($currentPage ?? 1) - 1)]) }}" class="btn btn-outline-dark rounded-pill px-4">
                            <i class="bi bi-arrow-left me-1"></i> Previous
                        </a>
                    @endif
                </div>
                <div class="text-muted small">
                    @if(isset($allItemsForPagination))
                        Items {{ $allItemsForPagination->firstItem() }} - {{ $allItemsForPagination->lastItem() }}
                    @else
                        Items {{ $paginatedItems->count() ? 1 : 0 }} - {{ $paginatedItems->count() }}
                    @endif
                </div>
                <div>
                    @if(isset($allItemsForPagination) && $allItemsForPagination->hasMorePages())
                        <a href="{{ route('stock-entries.edit', ['stockEntry' => $stockEntry, 'page' => (($currentPage ?? 1) + 1)]) }}" class="btn btn-primary rounded-pill px-4 shadow-sm">
                            Next <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    @endif
                </div>
            </div>

            <!-- Sticky Summary Footer -->
            <div class="sticky-footer-summary d-flex align-items-center justify-content-between shadow-lg">
                <div class="d-flex gap-4">
                    <div>
                        <div class="small opacity-50 text-uppercase fw-bold" style="font-size: 0.6rem;">Shift Total Sales</div>
                        <div class="h4 mb-0 fw-bold">MWK <span id="total_sales_display">0</span></div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('stock-entries.index') }}" class="btn btn-outline-light rounded-pill px-4 border-opacity-25">Cancel</a>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow">
                        <i class="bi bi-check-circle-fill me-2"></i>{{ auth()->user()->isSeller() ? 'Save Sales' : 'Update Shift Records' }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function updateRow(row) {
    const opening = parseFloat(row.querySelector('.opening-stock').value) || 0;
    const orders = parseFloat(row.querySelector('.orders').value) || 0;
    const prevSalesInBase = parseFloat(row.querySelector('.previous-sales').value) || 0;
    const newSalesInput = row.querySelector('.new-sales');
    const newSales = newSalesInput ? (parseFloat(newSalesInput.value) || 0) : 0;
    const unitSelect = row.querySelector('.unit-select');
    const selectedOption = unitSelect.options[unitSelect.selectedIndex];
    const price = parseFloat(selectedOption.dataset.price) || parseFloat(row.querySelector('input[name*="[price]"]').value) || 0;
    const conversionFactor = parseFloat(selectedOption.dataset.conversion) || 1;
    
    const newSalesInBase = newSales * conversionFactor;
    const totalSalesInBaseUnits = prevSalesInBase + newSalesInBase;
    const availableStock = opening + orders;
    
    if (newSalesInput && !newSalesInput.disabled && newSalesInBase > Math.max(0, availableStock - prevSalesInBase)) {
        const remainingBase = Math.max(0, availableStock - prevSalesInBase);
        const maxNewInSelectedUnit = Math.floor(remainingBase / conversionFactor);
        newSalesInput.value = maxNewInSelectedUnit;

        // Find the base unit name from the select options (conversion_factor = 1)
        const unitName = selectedOption.text.replace('(base)', '').trim();
        let baseUnitName = unitName;
        for (let i = 0; i < unitSelect.options.length; i++) {
            const opt = unitSelect.options[i];
            const conv = parseFloat(opt.dataset.conversion) || 1;
            if (opt.text.includes('(base)') || conv <= 1) {
                baseUnitName = opt.text.replace('(base)', '').trim();
                break;
            }
        }

        const remainingBaseRounded = Math.floor(remainingBase);
        const neededBase = Math.ceil(newSalesInBase);

        let message;
        if (unitName !== baseUnitName) {
            message = 'Cannot sell ' + newSales + ' ' + unitName + ' — only ' + remainingBaseRounded + ' ' + baseUnitName + '(s) available (need ' + neededBase + ' ' + baseUnitName + '(s) for ' + newSales + ' ' + unitName + '). Max you can sell: ' + maxNewInSelectedUnit + ' ' + unitName + '(s).';
        } else {
            message = 'Cannot sell ' + newSales + ' ' + unitName + ' — only ' + remainingBaseRounded + ' ' + baseUnitName + '(s) available.';
        }
        alert(message);
    }
    
    const finalNewSales = newSalesInput ? (parseFloat(newSalesInput.value) || 0) : 0;
    const finalNewSalesInBase = finalNewSales * conversionFactor;
    const finalTotalSalesInBase = prevSalesInBase + finalNewSalesInBase;
    
    const salesHidden = row.querySelector('.sales');
    if (salesHidden) {
        salesHidden.value = finalNewSales;
    }
    
    const closing = Math.max(0, availableStock - finalTotalSalesInBase);
    row.querySelector('.closing-stock').value = Number.isInteger(closing) ? closing : closing.toFixed(1);
    
    const closingDisplay = row.querySelector('.closing-display');
    if (closingDisplay) {
        const displayClosing = conversionFactor > 1 && selectedOption.value === 'Bottle'
            ? Math.floor(closing / conversionFactor)
            : closing;
        closingDisplay.textContent = Number(displayClosing).toLocaleString();
    }
    
    const baseAmount = parseFloat(row.querySelector('.sales-amount-val')?.dataset.base) || 0;
    const amount = baseAmount + (finalNewSales * price);
    if (row.querySelector('.sales-amount-val')) {
        row.querySelector('.sales-amount-val').value = amount;
    }
    if (row.querySelector('.sales-amount-display')) {
        row.querySelector('.sales-amount-display').innerText = amount.toLocaleString();
    }
    
    updateGrandTotal();
}

function updatePriceForUnitEdit(selectElement) {
    const row = selectElement.closest('tr');
    if (!row) return;

    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const newPrice = parseFloat(selectedOption.dataset.price) || 0;

    const priceInput = row.querySelector('input[name*="[price]"]');
    if (priceInput) {
        priceInput.value = newPrice;
    }

    const priceDisplay = row.querySelector('.price-display');
    if (priceDisplay) {
        priceDisplay.textContent = newPrice.toLocaleString();
    }

    updateRow(row);
}

function updateGrandTotal() {
    let total = 0;
    document.querySelectorAll('.sales-amount-val').forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    document.getElementById('total_sales_display').innerText = total.toLocaleString();
}

document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('change', (e) => {
        if (e.target.matches('.unit-select')) {
            updatePriceForUnitEdit(e.target);
        }
    });

    document.querySelectorAll('tbody tr').forEach(row => {
        row.querySelectorAll('.new-sales').forEach(input => {
            input.addEventListener('input', () => {
                // If the seller types a new amount after clearing, treat it as a normal add.
                const clearSalesInput = row.querySelector('.clear-sales');
                if (clearSalesInput && parseFloat(input.value) > 0) {
                    clearSalesInput.value = '0';
                }
                updateRow(row);
            });
        });
        updateRow(row);
    });
    updateGrandTotal();
    // Client-side search for edit table
    const editSearch = document.getElementById('edit_table_search');
    if (editSearch) {
        editSearch.addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();
            document.querySelectorAll('table.stock-table tbody tr').forEach(row => {
                const name = (row.querySelector('td[data-label="Item"] .fw-bold')?.innerText || '').toLowerCase();
                const cat = (row.querySelector('td[data-label="Item"] .text-muted')?.innerText || '').toLowerCase();
                const matches = q === '' || name.includes(q) || cat.includes(q);
                row.style.display = matches ? '' : 'none';
            });
            updateGrandTotal();
        });
    }
});

// Clear individual item sales — resets the sale count for a single item to 0
function clearItemSales(btn) {
    const row = btn.closest('tr');
    if (!row) return;
    const newSalesInput = row.querySelector('.new-sales');
    if (!newSalesInput || newSalesInput.disabled) return;

    // Confirm - this removes this item's saved sales (quantity + money) for the shift.
    if (!confirm('Clear ALL sales for this item? This removes the saved quantity and money for this shift.')) {
        return;
    }

    // Flag the row so the backend wipes the already-saved sales for it on Save.
    const clearSalesInput = row.querySelector('.clear-sales');
    if (clearSalesInput) clearSalesInput.value = '1';

    // Reset the local inputs so the screen immediately shows zero sales.
    newSalesInput.value = 0;
    const salesHidden = row.querySelector('.sales');
    if (salesHidden) salesHidden.value = 0;

    const prev = row.querySelector('.previous-sales');
    if (prev) prev.value = 0;
    const prevDisplay = row.querySelector('.sold-prev-display');
    if (prevDisplay) prevDisplay.textContent = '0';

    const amtVal = row.querySelector('.sales-amount-val');
    if (amtVal) {
        amtVal.value = 0;
        amtVal.dataset.base = 0;
    }
    const amtDisplay = row.querySelector('.sales-amount-display');
    if (amtDisplay) amtDisplay.textContent = '0';

    updateRow(row);
    updateGrandTotal();
}
</script>
@endsection
