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
                <h1 class="h4 fw-bold mb-0 text-dark">{{ auth()->user()->isSeller() ? 'Continue Selling' : 'Edit Stock Entry' }}</h1>
                <p class="text-muted small mb-0">{{ auth()->user()->isSeller() ? 'Recording sales for' : 'Adjusting records for' }} <strong>{{ $stockEntry->date->format('M d, Y') }}</strong></p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <input type="search" id="edit_table_search" class="form-control form-control-sm table-search me-3" placeholder="Search items">
            <span class="badge bg-primary rounded-pill px-3 py-2">📍 {{ auth()->user()->bar->name ?? 'Main Bar' }}</span>
        </div>
    </div>

    <div class="px-4 pb-5">
        <form method="POST" action="{{ route('stock-entries.update', $stockEntry) }}" id="editStockForm">
            @csrf @method('PUT')
            
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">Stock Item Adjustment</h5>
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
                                <th class="text-center">Sales</th>
                                <th class="text-center">Closing</th>
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
                                        <select name="items[{{ $index }}][unit_name]" class="form-control-stock unit-select" onchange="updatePriceForUnitEdit(this)">
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
                                        {{ number_format($item['price']) }}
                                    </td>
                                    <td data-label="Opening" class="text-center">
                                        <input type="number" name="items[{{ $index }}][opening_stock]" class="form-control-stock border-0 bg-light opening-stock" value="{{ $item['opening_stock'] }}" readonly>
                                    </td>
                                    <td data-label="Orders" class="text-center">
                                        <input type="number" name="items[{{ $index }}][orders]" class="form-control-stock orders border-0 bg-light" value="{{ $item['ordered_stock'] }}" readonly>
                                        @if($item['ordered_stock'] > 0)
                                            <div class="small text-success text-center" style="font-size:0.65rem;">✓ Director Approved</div>
                                        @endif
                                    </td>
                                    <td data-label="Sales" class="text-center">
                                        <div class="small text-muted mb-1">Previous: <strong>{{ $item['sold_quantity'] }}</strong></div>
                                        <input type="hidden" class="previous-sales" value="{{ $item['sold_quantity'] }}">
                                        <input type="number" class="form-control-stock new-sales text-primary" min="0" value="0" placeholder="0">
                                        <input type="hidden" name="items[{{ $index }}][sales]" class="sales" value="{{ $item['sold_quantity'] }}">
                                    </td>
                                    <td data-label="Closing" class="text-center">
                                        <input type="number" name="items[{{ $index }}][closing_stock]" class="form-control-stock border-0 bg-light closing-stock" value="{{ $item['closing_stock'] }}" readonly>
                                    </td>
                                    <td data-label="Total MWK" class="text-end pe-4 fw-bold text-dark">
                                        <input type="hidden" class="sales-amount-val" name="items[{{ $index }}][sales_amount]" value="{{ $item['sales_amount'] }}">
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
    const prevSales = parseFloat(row.querySelector('.previous-sales').value) || 0;
    const newSales = parseFloat(row.querySelector('.new-sales').value) || 0;
    const unitSelect = row.querySelector('.unit-select');
    const selectedOption = unitSelect.options[unitSelect.selectedIndex];
    const price = parseFloat(selectedOption.dataset.price) || parseFloat(row.querySelector('input[name*="[price]"]').value) || 0;
    const conversionFactor = parseFloat(selectedOption.dataset.conversion) || 1;
    
    // Total sales is previous sales plus any new sales entered
    const totalSales = prevSales + newSales;
    
    // Convert total sales to base units for stock calculation
    const totalSalesInBaseUnits = totalSales * conversionFactor;
    
    // Update the hidden sales input that gets submitted to the controller
    row.querySelector('.sales').value = totalSales;
    
    const closing = Math.max(0, opening + orders - totalSalesInBaseUnits);
    const amount = totalSales * price;
    
    row.querySelector('.closing-stock').value = closing;
    row.querySelector('.sales-amount-val').value = amount;
    row.querySelector('.sales-amount-display').innerText = amount.toLocaleString();
    
    updateGrandTotal();
}

function updatePriceForUnitEdit(selectElement) {
    const row = selectElement.closest('tr');
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const newPrice = parseFloat(selectedOption.dataset.price) || 0;
    
    // Update the hidden price field
    const priceInput = row.querySelector('input[name*="[price]"]');
    if (priceInput) {
        priceInput.value = newPrice;
    }
    
    // Update the displayed price
    const priceCell = row.querySelector('td[data-label="Price"]');
    if (priceCell) {
        priceCell.textContent = newPrice.toLocaleString();
    }
    
    // Recalculate sales amount if there's already a sales value
    const newSalesInput = row.querySelector('.new-sales');
    const prevSalesInput = row.querySelector('.previous-sales');
    if (newSalesInput && prevSalesInput) {
        const prevSales = parseFloat(prevSalesInput.value) || 0;
        const newSales = parseFloat(newSalesInput.value) || 0;
        const totalSales = prevSales + newSales;
        const salesAmount = totalSales * newPrice;
        row.querySelector('.sales-amount-val').value = salesAmount;
        row.querySelector('.sales-amount-display').innerText = salesAmount.toLocaleString();
        updateGrandTotal();
    }
}

function updateGrandTotal() {
    let total = 0;
    document.querySelectorAll('.sales-amount-val').forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    document.getElementById('total_sales_display').innerText = total.toLocaleString();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('tbody tr').forEach(row => {
        row.querySelectorAll('.new-sales').forEach(input => {
            input.addEventListener('input', () => updateRow(row));
        });
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
</script>
@endsection
