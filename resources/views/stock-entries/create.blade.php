@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .entry-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: -1.5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1rem 2rem;
        margin-bottom: 1.5rem;
    }

    @media (max-width: 768px) {
        .entry-header {
            margin-left: -1rem;
            margin-right: -1rem;
            padding: 1rem;
        }
    }
    .compact-table th {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #475569;
        background: #f8fafc;
        padding: 8px 12px !important;
        border-top: none !important;
    }
    .compact-table td {
        padding: 6px 12px !important;
        vertical-align: middle !important;
        color: #1e293b;
    }
    .item-input {
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        padding: 4px 8px;
        width: 100%;
        text-align: center;
        font-weight: 500;
        transition: all 0.2s;
        color: #1e293b;
    }
    .item-input:focus {
        border-color: var(--pixies-primary);
        box-shadow: 0 0 0 3px rgba(30, 41, 59, 0.1);
        outline: none;
    }
    .item-input[readonly] {
        background: #f1f5f9;
        color: #64748b;
        border-color: #e2e8f0;
    }
    .sticky-summary {
        position: sticky;
        bottom: 0;
        background: white;
        border-top: 2px solid #e2e8f0;
        padding: 1rem 2rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        margin-bottom: -1.5rem;
        z-index: 100;
        box-shadow: 0 -10px 15px -3px rgba(0, 0, 0, 0.05);
    }

    @media (max-width: 768px) {
        .sticky-summary {
            margin-left: -1rem;
            margin-right: -1rem;
            margin-bottom: -1rem;
            padding: 1rem;
        }
    }
    .badge-pill-custom {
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.75rem;
    }

    /* Mobile Responsive Table */
    @media (max-width: 768px) {
        .px-4 {
            padding-left: 1rem !important;
            padding-right: 1rem !important;
        }
        .compact-table thead { display: none; }
        .compact-table tr {
            display: block;
            border: 1px solid #e2e8f0;
            margin-bottom: 1rem;
            border-radius: 12px;
            padding: 10px;
            background: #fff;
        }
        .compact-table td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: none !important;
            padding: 8px 0 !important;
            padding-left: 12px !important;
            padding-right: 12px !important;
        }
        .compact-table td::before {
            content: attr(data-label);
            font-weight: 700;
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            margin-right: 8px;
        }
        .item-input { width: 100px; font-size: 0.9rem; padding: 6px 8px; }
        .sticky-summary {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start !important;
        }
        .sticky-summary .d-flex { width: 100%; justify-content: space-between; }
        .entry-header { flex-direction: column; align-items: flex-start !important; gap: 0.5rem; }
        .bg-light.p-3 {
            padding: 1rem !important;
            flex-direction: column;
            gap: 1rem;
        }
        .bg-light.p-3 .d-flex {
            width: 100%;
            justify-content: center;
        }
    }

    /* Reduce sticky footer height on small screens */
    @media (max-width: 576px) {
        .sticky-summary {
            padding: 0.6rem 0.9rem;
            box-shadow: 0 -6px 10px -3px rgba(0,0,0,0.04);
        }
        .sticky-summary .d-flex { gap: 0.5rem; }
        .sticky-summary .btn { padding: 0.45rem 0.9rem; font-size: 0.85rem; }
        .sticky-summary .btn-primary { padding: 0.5rem 1rem; }
        #total_sales_display, #net_cash_display { font-size: 0.95rem; }
        .sticky-summary h4 { font-size: 1.1rem; }
    }

    /* Better mobile header layout */
    @media (max-width: 576px) {
        .entry-header {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 1rem;
            padding: 1rem !important;
        }
        .entry-header .d-flex:first-child {
            width: 100%;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .entry-header .d-flex:last-child {
            width: 100%;
            flex-direction: column;
            align-items: stretch !important;
        }
        .entry-header .form-control-sm {
            width: 100% !important;
        }
        #create_table_search {
            margin-bottom: 0.5rem;
            width: 100% !important;
        }
        .entry-header .text-muted {
            font-size: 0.75rem;
        }
    }
</style>

<div class="container-fluid p-0">
    <!-- Compact Header -->
    <div class="entry-header d-flex align-items-center justify-content-between shadow-sm">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <h1 class="h4 fw-bold mb-0 text-dark">{{ auth()->user()->isSeller() ? 'Start Selling' : 'New Stock Entry' }}</h1>
            <div class="vr mx-2 d-none d-md-block"></div>
            @if(auth()->user()->bar)
                <span class="badge bg-dark text-white badge-pill-custom">📍 {{ auth()->user()->bar->name }}</span>
            @endif
            <span class="badge bg-light text-dark border border-secondary border-opacity-20 badge-pill-custom">📅 {{ now()->format('M d, Y') }}</span>
        </div>
        <div class="d-flex gap-2 align-items-center">
             <div class="d-flex align-items-center gap-2 w-100">
                 <input type="search" id="create_table_search" class="form-control form-control-sm" placeholder="Search items" style="width:100%">
                 <span class="text-muted small">Page {{ $currentPage }} of {{ $totalPages }}</span>
             </div>
        </div>
    </div>

    <div class="px-4">
        @if (session('success') || session('error') || $errors->any())
            <div class="mb-4">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger border-0 shadow-sm" role="alert">
                        <ul class="mb-0 small">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('stock-entries.store') }}" id="stock-entry-form">
            @csrf
            <input type="hidden" name="date" value="{{ now()->format('Y-m-d') }}">
            
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5" style="max-width: 100%;">
                <div class="table-responsive">
                    <table class="table compact-table mb-0">
                        <thead>
                            <tr>
                                <th style="width: 25%">Product / Item</th>
                                <th class="text-center" style="width: 12%">Unit</th>
                                <th class="text-center" style="width: 10%">Price</th>
                                <th class="text-center" style="width: 10%">Opening</th>
                                <th class="text-center" style="width: 10%">Orders</th>
                                <th class="text-center" style="width: 10%">Sales</th>
                                <th class="text-center" style="width: 10%">Closing</th>
                                <th class="text-end" style="width: 13%">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($paginatedItems as $index => $item)
                                <tr data-item-id="{{ $item['id'] }}">
                                    <td data-label="Product">
                                        <div class="fw-bold text-dark">{{ $item['name'] }}</div>
                                        <div class="small text-muted">{{ $item['category'] }}</div>
                                        <input type="hidden" name="items[{{ $index }}][item_id]" value="{{ $item['id'] }}">
                                        <input type="hidden" name="items[{{ $index }}][price]" value="{{ $item['price'] }}">
                                        <input type="hidden" name="items[{{ $index }}][purchase_price]" value="{{ $item['purchase_price'] ?? 0 }}">
                                        <input type="hidden" name="items[{{ $index }}][expiry_date]" value="">
                                    </td>
                                    <td data-label="Unit">
                                        <select name="items[{{ $index }}][unit_name]" class="item-input unit-select" onchange="updatePriceForUnit(this)">
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
                                    <td data-label="Price" class="text-center text-secondary fw-semibold">
                                        {{ number_format($item['price']) }}
                                    </td>
                                    <td data-label="Opening">
                                        <input type="number" name="items[{{ $index }}][opening_stock]" class="item-input opening-stock" value="{{ $item['opening_stock'] }}" readonly>
                                    </td>
                                    <td data-label="Orders">
                                        <input type="number" name="items[{{ $index }}][orders]" class="item-input orders" value="{{ $item['ordered_stock'] }}" readonly>
                                        @if($item['ordered_stock'] > 0)
                                            <div class="small text-success text-center" style="font-size:0.65rem;">✓ Approved</div>
                                        @endif
                                    </td>
                                    <td data-label="Sales">
                                        <input type="number" name="items[{{ $index }}][sales]" class="item-input sales" min="0" step="1" value="0">
                                    </td>
                                    <td data-label="Closing">
                                        <input type="number" name="items[{{ $index }}][closing_stock]" class="item-input closing-stock" value="{{ max(0, $item['opening_stock'] + $item['ordered_stock']) }}" readonly>
                                    </td>
                                    <td data-label="Subtotal" class="text-end">
                                        <input type="number" name="items[{{ $index }}][sales_amount]" class="item-input sales-amount bg-transparent border-0 text-end fw-bold text-primary" value="0" readonly>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <!-- Compact Pagination -->
                <div class="bg-light p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        @if($currentPage > 1)
                            <a href="{{ route('stock-entries.create', ['page' => $currentPage - 1]) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 bg-white">
                                <i class="bi bi-chevron-left me-1"></i> Previous
                            </a>
                        @endif
                    </div>
                    <div class="small text-muted fw-medium">
                        Showing {{ (($currentPage - 1) * $perPage) + 1 }} - {{ min($currentPage * $perPage, $items->count()) }} of {{ $items->count() }}
                    </div>
                    <div>
                        @if($currentPage < $totalPages)
                            <a href="{{ route('stock-entries.create', ['page' => $currentPage + 1]) }}" class="btn btn-sm btn-primary rounded-pill px-3">
                                Next <i class="bi bi-chevron-right ms-1"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Sticky Summary Footer -->
            <div class="sticky-summary d-flex align-items-center justify-content-between flex-wrap">
                <div class="d-flex gap-4">
                    <div class="text-center">
                        <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">Total Sales</div>
                        <div class="h4 mb-0 fw-bold text-primary">
                            <span class="small fs-6 opacity-50">MWK</span> <span id="total_sales_display">0.00</span>
                            <input type="hidden" id="total_sales" value="0">
                        </div>
                    </div>
                    <div class="vr d-none d-md-block"></div>
                    <div class="text-center">
                        <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">Net Cash</div>
                        <div class="h4 mb-0 fw-bold text-success">
                            <span class="small fs-6 opacity-50">MWK</span> <span id="net_cash_display">0.00</span>
                            <input type="hidden" id="net_cash" value="0">
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2 w-sm-100 mt-2 mt-md-0">
                    <a href="{{ route('stock-entries.index') }}" class="btn btn-outline-secondary rounded-pill px-4 bg-white">Cancel</a>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                        <i class="bi bi-check-lg me-2"></i>{{ auth()->user()->isSeller() ? 'Save Sales' : 'Complete Entry' }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('input', function(e) {
        if (e.target.matches('.sales')) {
            const row = e.target.closest('tr');
            if (!row) return;
            
            const openingStock = parseFloat(row.querySelector('.opening-stock').value) || 0;
            const orders = parseFloat(row.querySelector('.orders').value) || 0; // readonly, from director approval
            const sales = parseFloat(row.querySelector('.sales').value) || 0;
            const unitSelect = row.querySelector('.unit-select');
            const selectedOption = unitSelect.options[unitSelect.selectedIndex];
            const price = parseFloat(selectedOption.dataset.price) || parseFloat(row.querySelector('input[name*="[price]"]').value) || 0;
            const conversionFactor = parseFloat(selectedOption.dataset.conversion) || 1;
            
            const availableStock = openingStock + orders;
            
            // Convert sales to base units for stock calculation
            const salesInBaseUnits = sales * conversionFactor;
            
            if (salesInBaseUnits > availableStock) {
                const maxSalesInSelectedUnit = Math.floor(availableStock / conversionFactor);
                e.target.value = maxSalesInSelectedUnit;
                alert('⚠️ Insufficient stock! Max available: ' + maxSalesInSelectedUnit + ' ' + selectedOption.text);
                return;
            }
            
            const finalSales = parseFloat(row.querySelector('.sales').value) || 0;
            const finalSalesInBaseUnits = finalSales * conversionFactor;
            const closingStock = Math.max(0, availableStock - finalSalesInBaseUnits);
            row.querySelector('.closing-stock').value = closingStock.toFixed(1);
            
            const salesAmount = finalSales * price;
            row.querySelector('.sales-amount').value = salesAmount.toFixed(0);
            
            updateTotals();
        }
    });

    function updatePriceForUnit(selectElement) {
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
        const salesInput = row.querySelector('.sales');
        if (salesInput && parseFloat(salesInput.value) > 0) {
            const sales = parseFloat(salesInput.value);
            const salesAmount = sales * newPrice;
            row.querySelector('.sales-amount').value = salesAmount.toFixed(0);
            updateTotals();
        }
    }

    function updateTotals() {
        let totalSales = 0;
        document.querySelectorAll('.sales-amount').forEach(input => {
            totalSales += parseFloat(input.value) || 0;
        });
        
        document.getElementById('total_sales').value = totalSales;
        document.getElementById('total_sales_display').innerText = totalSales.toLocaleString();
        
        document.getElementById('net_cash').value = totalSales;
        document.getElementById('net_cash_display').innerText = totalSales.toLocaleString();
    }
});
</script>
<script>
// Client-side search/filter for current page
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('create_table_search');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();
            document.querySelectorAll('table.compact-table tbody tr').forEach(row => {
                const name = (row.querySelector('td[data-label="Product"] .fw-bold')?.innerText || '').toLowerCase();
                const cat = (row.querySelector('td[data-label="Product"] .small')?.innerText || '').toLowerCase();
                const matches = q === '' || name.includes(q) || cat.includes(q);
                row.style.display = matches ? '' : 'none';
            });
        });
    }
});
</script>
@endsection
