@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .entry-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        padding: 1rem 2rem;
        margin-bottom: 1.5rem;
        position: relative;
        z-index: 1;
    }

    @media (max-width: 768px) {
        .entry-header {
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
        cursor: not-allowed;
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
            <h1 class="h4 fw-bold mb-0 text-dark">{{ auth()->user()->isSeller() ? 'Selling' : 'New Sale Record' }}</h1>
            <div class="vr mx-2 d-none d-md-block"></div>
            @if(auth()->user()->bar)
                <span class="badge bg-dark text-white badge-pill-custom">📍 {{ auth()->user()->bar->name }}</span>
            @endif
            <form method="GET" action="{{ route('stock-entries.create') }}" class="d-flex align-items-center gap-2">
                <label for="sheetDate" class="small fw-bold text-muted mb-0 text-nowrap">📅 Sheet date</label>
                <input type="date" id="sheetDate" name="date" value="{{ $date }}" max="{{ now()->format('Y-m-d') }}"
                       class="form-control form-control-sm" style="width: auto;" onchange="this.form.submit()">
            </form>
        </div>
        <div class="d-flex gap-2 align-items-center">
             <div class="d-flex align-items-center gap-2 w-100">
                 <input type="search" id="create_table_search" class="form-control form-control-sm" placeholder="Search items" style="width:100%">
                 <span class="text-muted small text-nowrap">{{ $paginatedItems->count() }} items</span>
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
            <input type="hidden" name="date" value="{{ $date }}">
            
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5" style="max-width: 100%;">
                <div class="table-responsive">
                    <table class="table compact-table mb-0">
                        <thead>
                            <tr>
                                <th style="width: 35%">Product / Item</th>
                                <th class="text-center" style="width: 12%">Price</th>
                                <th class="text-center" style="width: 10%">Opening</th>
                                <th class="text-center" style="width: 10%">Orders</th>
                                <th class="text-center" style="width: 10%">Closing</th>
                                <th class="text-center" style="width: 10%">Sales</th>
                                <th class="text-end" style="width: 13%">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($paginatedItems as $index => $item)
                                @php
                                    $baseUnit = ($item['product_units'] ?? collect())->firstWhere('is_base_unit', true)
                                        ?? ($item['product_units'] ?? collect())->first();
                                    $baseUnitName = $baseUnit->unit_name ?? 'Bottle';
                                    $baseUnitPrice = $baseUnit->price?->selling_price ?? $item['price'];
                                @endphp
                                <tr data-item-id="{{ $item['id'] }}">
                                    <td data-label="Product">
                                        <div class="fw-bold text-dark">{{ $item['name'] }}</div>
                                        <div class="small text-muted">{{ $item['category'] }}</div>
                                        <input type="hidden" name="items[{{ $index }}][item_id]" value="{{ $item['id'] }}">
                                        <input type="hidden" name="items[{{ $index }}][price]" value="{{ $baseUnitPrice }}">
                                        <input type="hidden" name="items[{{ $index }}][unit_name]" value="{{ $baseUnitName }}">
                                        <input type="hidden" name="items[{{ $index }}][purchase_price]" value="{{ $item['purchase_price'] ?? 0 }}">
                                        <input type="hidden" name="items[{{ $index }}][expiry_date]" value="">
                                    </td>
                                    <td data-label="Price" class="text-center text-secondary fw-semibold">
                                        <span class="price-display">{{ number_format($baseUnitPrice) }}</span>
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
                                        <input type="hidden" name="items[{{ $index }}][closing_stock]" class="closing-stock" value="{{ $item['closing_stock'] ?? max(0, $item['opening_stock'] + $item['ordered_stock']) }}">
                                        <span class="closing-display fw-semibold">{{ number_format($item['closing_stock_display'] ?? $item['closing_stock'] ?? max(0, $item['opening_stock'] + $item['ordered_stock'])) }}</span>
                                        <div class="small text-muted">{{ $item['stock_unit'] ?? 'units' }}</div>
                                    </td>
                                    <td data-label="Sales" class="text-center">
                                        @if(!($item['can_sell'] ?? true))
                                            <div class="small text-danger mb-1" style="font-size:0.65rem;">Out of stock — <a href="{{ route('seller.orders.create') }}">request stock</a></div>
                                            <input type="hidden" name="items[{{ $index }}][sales]" value="0">
                                            <input type="number" class="item-input sales bg-light" min="0" step="1" value="" placeholder="0" readonly tabindex="-1">
                                        @else
                                            <div class="d-flex flex-column align-items-center gap-1">
                                                <input type="number" name="items[{{ $index }}][sales]" class="item-input sales" min="0" step="1" value="" placeholder="0" data-available-base="{{ $item['available_stock'] ?? ($item['opening_stock'] + $item['ordered_stock']) }}" data-available-display="{{ $item['available_stock_display'] ?? ($item['opening_stock_display'] ?? $item['opening_stock']) + ($item['ordered_stock_display'] ?? $item['ordered_stock']) }}">
                                                <button type="button" class="btn btn-sm btn-outline-danger delete-sale-btn" title="Delete sale">Clear</button>
                                            </div>
                                        @endif
                                    </td>
                                    <td data-label="Subtotal" class="text-end">
                                        <input type="number" name="items[{{ $index }}][sales_amount]" class="item-input sales-amount bg-transparent border-0 text-end fw-bold text-primary" value="0" readonly>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
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
            updateSaleRow(e.target.closest('tr'));
        }
    });

    document.addEventListener('click', function(e) {
        if (e.target.matches('.delete-sale-btn')) {
            const row = e.target.closest('tr');
            if (!row) return;
            const salesInput = row.querySelector('.sales');
            if (!salesInput) return;
            salesInput.value = '';
            updateSaleRow(row);
        }
    });

    function updateSaleRow(row) {
        if (!row) return;

        const openingStock = parseFloat(row.querySelector('.opening-stock').value) || 0;
        const orders = parseFloat(row.querySelector('.orders').value) || 0;
        const sales = parseFloat(row.querySelector('.sales').value) || 0;
        const price = parseFloat(row.querySelector('input[name*="[price]"]').value) || 0;

        const availableStock = openingStock + orders;

        if (sales > availableStock) {
            const maxSales = Math.floor(availableStock);
            row.querySelector('.sales').value = maxSales;
            alert('Insufficient stock. Max available: ' + maxSales + '.');
        }

        const finalSales = parseFloat(row.querySelector('.sales').value) || 0;
        const closingStock = Math.max(0, availableStock - finalSales);
        row.querySelector('.closing-stock').value = Number.isInteger(closingStock) ? closingStock : closingStock.toFixed(1);

        const closingDisplay = row.querySelector('.closing-display');
        if (closingDisplay) {
            closingDisplay.textContent = Number(closingStock).toLocaleString();
        }

        const salesAmount = finalSales * price;
        const salesAmountInput = row.querySelector('.sales-amount');
        if (salesAmountInput) {
            salesAmountInput.value = salesAmount.toFixed(0);
        }

        updateTotals();
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
