@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .details-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: -1.5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }
    .stat-card-modern {
        border-radius: 16px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: white;
        padding: 1.5rem;
        transition: transform 0.2s;
        min-height: 220px;
        width: 100%;
    }
    .stat-card-modern:hover {
        transform: translateY(-3px);
    }
    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        font-size: 20px;
    }
    .data-table th {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        background: #f8fafc;
        padding: 12px 16px !important;
    }
    .data-table td {
        padding: 12px 16px !important;
        vertical-align: middle !important;
        font-size: 0.875rem;
        color: #1e293b;
    }
    .status-badge {
        padding: 0.4rem 0.8rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.7rem;
    }

    .stock-entry-content {
        padding: 0 1.5rem;
    }

    .table-card {
        width: 100%;
    }

    @media (max-width: 768px) {
        .stock-entry-content {
            padding: 0 0.75rem;
        }
        .details-header { flex-direction: column; align-items: flex-start !important; gap: 1rem; padding: 1rem; }
        .data-table thead { display: none; }
        .data-table tr { display: block; border-bottom: 1px solid #e2e8f0; padding: 12px 0; }
        .data-table td { display: flex; justify-content: space-between; align-items: center; border: none !important; padding: 10px 0 !important; width: 100%; }
        .data-table td::before { content: attr(data-label); font-weight: 700; font-size: 0.75rem; color: #64748b; text-transform: uppercase; margin-right: 0.75rem; }
        .stat-card-modern { padding: 1rem; }
        .card.border-0 { width: 100%; }
        .table-responsive { margin-left: -0.75rem; margin-right: -0.75rem; padding: 0 0.75rem; }
    }
</style>

<div class="container-fluid p-0">
    <!-- Modern Header -->
    <div class="details-header d-flex align-items-center justify-content-between shadow-sm">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <a href="{{ route('stock-entries.index') }}" class="btn btn-sm btn-light rounded-circle shadow-sm border">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="h4 fw-bold mb-0 text-dark">{{ auth()->user()->isSeller() ? 'Today\'s Sales' : 'Sales Report' }}</h1>
                <p class="text-muted small mb-0">Recorded for {{ $stockEntry->date->format('l, F d, Y') }}</p>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @php $canDeleteSale = auth()->user()->isDirector() || (auth()->user()->isSeller() && $stockEntry->bar_id === auth()->user()->bar_id && $stockEntry->date->format('Y-m-d') === now()->format('Y-m-d')); @endphp
            @if($canDeleteSale)
                <form method="POST" action="{{ route('stock-entries.destroy', $stockEntry) }}" onsubmit="return confirm('Delete this sale and all associated records?');" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger rounded-pill px-4 shadow-sm">
                        <i class="bi bi-trash me-2"></i>Delete Sale
                    </button>
                </form>
            @endif
            @if(auth()->user()->isSeller() && $stockEntry->date->format('Y-m-d') === now()->format('Y-m-d'))
                <a href="{{ route('stock-entries.sell') }}" class="btn btn-primary rounded-pill px-4 shadow-sm">
                    <i class="bi bi-cart-check me-2"></i>Sell
                </a>
            @endif
            @if(auth()->user()->bar)
                <span class="badge bg-dark text-white rounded-pill px-3 py-2">📍 {{ auth()->user()->bar->name }}</span>
            @endif
        </div>
    </div>

    <div class="stock-entry-content px-4">
        <!-- Summary Stats -->
        <div class="row g-3 g-md-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card-modern shadow-sm">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">Total Revenue</div>
                    <div class="h3 mb-0 fw-bold text-dark">
                        <span class="small fs-6 opacity-50">MWK</span> {{ number_format($stockEntry->stockEntryItems->sum('sales_amount')) }}
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card-modern shadow-sm">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">Total Expenses</div>
                    <div class="h3 mb-0 fw-bold text-dark">
                        <span class="small fs-6 opacity-50">MWK</span> {{ number_format($stockEntry->expenses->sum('amount')) }}
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card-modern shadow-sm">
                    @php 
                        $net = $stockEntry->stockEntryItems->sum('sales_amount') - $stockEntry->expenses->sum('amount');
                    @endphp
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-bank"></i>
                    </div>
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">Net Bankable</div>
                    <div class="h3 mb-0 fw-bold text-success">
                        <span class="small fs-6 opacity-50">MWK</span> {{ number_format($net) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Details Card -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5 bg-white">
            <div class="card-header bg-white py-3 border-bottom-0">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="fw-bold mb-0 text-dark">Inventory Breakdown</h5>
                    <div class="small text-muted">
                        Page {{ $allItems->currentPage() }} of {{ $allItems->lastPage() }}
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table data-table mb-0">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-center">Price</th>
                            <th class="text-center">Opening</th>
                            <th class="text-center">Orders</th>
                            <th class="text-center">Closing</th>
                            <th class="text-center">Qty Sold</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($itemsData as $item)
                            <tr class="{{ $item['has_data'] ? '' : 'opacity-50' }}">
                                <td data-label="Product">
                                    <div class="fw-bold text-dark">{{ $item['name'] }}</div>
                                    <div class="small text-muted">{{ $item['category'] }}</div>
                                </td>
                                <td data-label="Price" class="text-center text-secondary">
                                    @if(isset($item['product_units']) && $item['product_units']->count() > 0)
                                        @foreach($item['product_units'] as $unit)
                                            @if($unit->price && $unit->price->selling_price > 0)
                                                <div>{{ $unit->unit_name }}: {{ number_format($unit->price->selling_price) }}</div>
                                            @endif
                                        @endforeach
                                    @else
                                        {{ number_format($item['price']) }}
                                    @endif
                                </td>
                                <td data-label="Opening" class="text-center">
                                    <div>
                                        <span class="badge bg-light text-dark border border-secondary border-opacity-10 status-badge">
                                            {{ $item['opening_stock'] }}
                                        </span>
                                        @if($item['stock_entry_item'] && $item['stock_entry_item']->item_id)
                                            <div class="small text-muted" style="font-size: 0.7rem;">
                                                {{ $item['stock_entry_item']->getHumanReadableStock() }}
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="Orders" class="text-center">
                                    <span class="badge {{ $item['ordered_stock'] > 0 ? 'bg-primary text-white' : 'bg-light text-muted' }} status-badge">
                                        {{ $item['ordered_stock'] }}
                                    </span>
                                </td>
                                <td data-label="Closing" class="text-center">
                                    <div>
                                        <span class="badge bg-light text-dark status-badge">
                                            {{ $item['closing_stock'] }}
                                        </span>
                                        @if($item['stock_entry_item'] && $item['stock_entry_item']->item_id)
                                            <div class="small text-muted" style="font-size: 0.7rem;">
                                                {{ $item['stock_entry_item']->getHumanReadableStock() }}
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="Sold" class="text-center">
                                    <div class="fw-bold {{ $item['sold_quantity'] > 0 ? 'text-dark' : 'text-muted' }}">
                                        {{ $item['sold_quantity'] }}
                                    </div>
                                </td>
                                <td data-label="Subtotal" class="text-end fw-bold text-primary">
                                    {{ number_format($item['sales_amount']) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Table Pagination -->
            @if($allItems->hasPages())
                <div class="card-footer bg-white p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="small text-muted">
                        Showing {{ $allItems->firstItem() }} to {{ $allItems->lastItem() }}
                    </div>
                    <div class="btn-group shadow-sm bg-white">
                        @if($allItems->previousPageUrl())
                            <a href="{{ $allItems->previousPageUrl() }}" class="btn btn-sm btn-white border px-3">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        @endif
                        @if($allItems->nextPageUrl())
                            <a href="{{ $allItems->nextPageUrl() }}" class="btn btn-sm btn-white border px-3">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
