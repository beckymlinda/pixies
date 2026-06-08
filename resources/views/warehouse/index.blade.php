@extends('layouts.app')

@section('content')
@php $pageTitle = 'Warehouse Stock'; @endphp
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .index-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: -1.5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }
    .data-card {
        border-radius: 16px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: white;
        overflow: hidden;
    }
    .index-table th {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        background: #f8fafc;
        padding: 14px 20px !important;
        border-top: none !important;
    }
    .index-table td {
        padding: 16px 20px !important;
        vertical-align: middle !important;
        font-size: 0.9rem;
        color: #1e293b;
    }
    .btn-action {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        border: 1px solid #e2e8f0;
        background: white;
        color: #64748b;
    }
    .btn-action:hover {
        background: #f8fafc;
        color: var(--pixies-primary);
        border-color: var(--pixies-primary);
    }
    .alert-badge {
        font-size: 0.7rem;
        padding: 4px 10px;
        border-radius: 20px;
        font-weight: 600;
    }
    .low-stock-row {
        background-color: #fef2f2 !important;
    }
    .expiring-soon-row {
        background-color: #fffbeb !important;
    }

    @media (max-width: 768px) {
        .index-header { flex-direction: column; align-items: flex-start !important; gap: 1rem; padding: 1rem; }
        .index-table thead { display: none; }
        .index-table tr { display: block; border-bottom: 1px solid #e2e8f0; padding: 15px; }
        .index-table td { display: flex; justify-content: space-between; align-items: center; border: none !important; padding: 8px 0 !important; width: 100%; }
        .index-table td::before { content: attr(data-label); font-weight: 700; font-size: 0.75rem; color: #64748b; text-transform: uppercase; }
        .btn-action { width: auto; height: auto; padding: 6px 12px; border-radius: 20px; }
    }
</style>

<div class="container-fluid p-0">
    <!-- Modern Header with Alerts -->
    <div class="index-header shadow-sm">
        <div class="d-flex align-items-center justify-content-between w-100 mb-3">
            <div>
                <h1 class="h3 fw-bold mb-1 text-dark">Warehouse Stock</h1>
                <p class="text-muted small mb-0">Manage inventory, track expiry dates, and monitor stock levels.</p>
            </div>
            <a href="{{ route('warehouse.create') }}" class="btn btn-primary rounded-pill px-4 shadow-sm">
                <i class="bi bi-plus-lg me-2"></i>Add New Item
            </a>
        </div>

        <!-- Alert Tabs -->
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('warehouse.index') }}" class="btn btn-outline-secondary rounded-pill px-3 py-2 {{ !request()->has('tab') ? 'active' : '' }}">
                <i class="bi bi-box-seam me-1"></i>All Items ({{ $totalItemsCount }})
            </a>
            <a href="{{ route('warehouse.alerts.expiry') }}" class="btn btn-outline-warning rounded-pill px-3 py-2">
                <i class="bi bi-exclamation-triangle me-1"></i>Expiry Alert ({{ $expiryAlertsCount + $expiredCount }})
            </a>
            <a href="{{ route('warehouse.alerts.low-stock') }}" class="btn btn-outline-danger rounded-pill px-3 py-2">
                <i class="bi bi-graph-down me-1"></i>Low Stock ({{ $lowStockCount }})
            </a>
            @php
                $pendingTransferCount = \App\Models\WarehouseTransferRequest::where('status', 'pending')->count();
            @endphp
            <a href="{{ route('warehouse.transfer-requests') }}" class="btn btn-outline-primary rounded-pill px-3 py-2">
                <i class="bi bi-arrow-left-right me-1"></i>Transfer Requests
                @if($pendingTransferCount > 0)
                    <span class="badge bg-warning text-dark ms-1">{{ $pendingTransferCount }}</span>
                @endif
            </a>
        </div>
    </div>

    <div class="px-4">
        <!-- Search Bar -->
        <div class="card data-card shadow-sm border-0 mb-3">
            <div class="card-body p-3">
                <form method="GET" action="{{ route('warehouse.index') }}" class="d-flex gap-2">
                    <div class="flex-grow-1">
                        <input type="text" name="search" value="{{ $search ?? '' }}" class="form-control" placeholder="Search items by name...">
                    </div>
                    <div style="min-width: 200px;">
                        <select name="bar_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Branches</option>
                            @foreach($bars as $bar)
                                <option value="{{ $bar->id }}" {{ $selectedBarId == $bar->id ? 'selected' : '' }}>{{ $bar->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i>
                    </button>
                    @if($search ?? null || $selectedBarId ?? null)
                        <a href="{{ route('warehouse.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="card data-card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="small text-muted text-uppercase fw-bold mb-1">Total Stock Cost</div>
                        <div class="h4 fw-bold text-dark mb-0">MWK {{ number_format($totalStockCost, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card data-card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="small text-muted text-uppercase fw-bold mb-1">Total Stock Value</div>
                        <div class="h4 fw-bold text-primary mb-0">MWK {{ number_format($totalStockValue, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card data-card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="small text-muted text-uppercase fw-bold mb-1">Expected Profit</div>
                        <div class="h4 fw-bold text-success mb-0">MWK {{ number_format($totalExpectedProfit, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card data-card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="small text-muted text-uppercase fw-bold mb-1">Low Stock Items</div>
                        <div class="h4 fw-bold text-danger mb-0">{{ $lowStockCount }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stock Table -->
        <div class="card data-card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table index-table mb-0">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Stock</th>
                            <th>Cost Price</th>
                            <th>Selling Price</th>
                            <th>Profit %</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stocks as $stock)
                            <tr class="{{ $stock->isLowStock() ? 'low-stock-row' : '' }} {{ $stock->isExpiringsoon() ? 'expiring-soon-row' : '' }}">
                                <td data-label="Item Name">
                                    <div class="fw-bold text-dark">{{ $stock->item_name }}</div>
                                </td>
                                <td data-label="Stock">
                                    <div class="fw-bold {{ $stock->isLowStock() ? 'text-danger' : 'text-dark' }}">{{ $stock->quantity }}</div>
                                </td>
                                <td data-label="Cost Price">
                                    <div class="text-dark">MWK {{ number_format($stock->average_unit_cost > 0 ? $stock->average_unit_cost : $stock->purchase_price, 2) }}</div>
                                </td>
                                <td data-label="Selling Price">
                                    <div class="text-primary fw-bold">MWK {{ number_format($stock->selling_price, 2) }}</div>
                                </td>
                                <td data-label="Profit %">
                                    <div class="fw-bold {{ $stock->profit_percentage > 0 ? 'text-success' : 'text-danger' }}">
                                        {{ number_format($stock->profit_percentage, 2) }}%
                                    </div>
                                </td>
                                <td data-label="Status">
                                    <span class="alert-badge {{ $stock->status_badge_class }}">{{ $stock->item_status }}</span>
                                </td>
                                <td data-label="Actions" class="text-end">
                                    <a href="{{ route('warehouse.show', $stock) }}" class="btn-action" title="View">
                                        <i class="bi bi-eye-fill"></i>
                                    </a>
                                    <a href="{{ route('warehouse.edit', $stock) }}" class="btn-action" title="Edit">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <a href="{{ route('warehouse.restock', $stock) }}" class="btn-action" title="Restock">
                                        <i class="bi bi-plus-circle-fill"></i>
                                    </a>
                                    <form action="{{ route('warehouse.destroy', $stock) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this item?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-action ms-1" title="Delete">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="opacity-25 display-4 mb-3">📦</div>
                                    <p class="text-muted">No stock items found. Add your first item to get started.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            @if($stocks->hasPages())
                <div class="card-footer bg-white border-top p-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="small text-muted">
                            Showing {{ $stocks->firstItem() }} to {{ $stocks->lastItem() }} of {{ $stocks->total() }} items
                        </div>
                        <div class="shadow-sm bg-white">
                            {{ $stocks->appends(['search' => $search ?? null])->links() }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
