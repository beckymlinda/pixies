@extends('layouts.app')

@section('content')
@php $pageTitle = 'Warehouse Stock'; @endphp
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .wh-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin: -1.5rem -1.5rem 1.5rem;
        padding: 1.25rem 1.5rem;
    }
    .wh-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        overflow: hidden;
    }
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
    }
    @media (min-width: 768px) {
        .summary-grid { grid-template-columns: repeat(4, 1fr); }
    }
    .summary-tile {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.85rem 1rem;
    }
    .summary-tile .lbl {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #94a3b8;
        font-weight: 600;
    }
    .summary-tile .val {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1e293b;
        line-height: 1.3;
    }
    .filter-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: stretch;
    }
    .filter-bar .form-control,
    .filter-bar .form-select {
        border-radius: 10px;
        font-size: 0.9rem;
    }
    .stock-card {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: white;
        padding: 1rem;
        margin-bottom: 0.75rem;
        transition: box-shadow 0.15s;
    }
    .stock-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
    .stock-card.low-stock { border-left: 4px solid #ef4444; background: #fffbfb; }
    .stock-card.expiring { border-left: 4px solid #f59e0b; background: #fffef7; }
    .stock-card .item-name { font-size: 1rem; font-weight: 700; color: #1e293b; }
    .stock-card .meta { font-size: 0.78rem; color: #64748b; }
    .metric-pill {
        background: #f8fafc;
        border-radius: 8px;
        padding: 0.5rem 0.65rem;
        text-align: center;
    }
    .metric-pill .lbl { font-size: 0.6rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; }
    .metric-pill .val { font-size: 0.9rem; font-weight: 700; color: #1e293b; }
    .metric-pill .val.price { color: #2563eb; }
    .metric-pill .val.profit { color: #059669; }
    .metric-pill .val.loss { color: #dc2626; }
    .status-badge {
        font-size: 0.65rem;
        padding: 3px 8px;
        border-radius: 20px;
        font-weight: 600;
    }
    .action-row {
        display: flex;
        gap: 0.4rem;
        flex-wrap: wrap;
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid #f1f5f9;
    }
    .action-btn {
        flex: 1;
        min-width: 70px;
        font-size: 0.75rem;
        padding: 0.4rem 0.5rem;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: white;
        color: #475569;
        text-decoration: none;
        text-align: center;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.25rem;
    }
    .action-btn:hover { background: #f8fafc; color: #1e293b; }
    .action-btn.primary { background: #1e293b; color: white; border-color: #1e293b; }
    .action-btn.danger { color: #dc2626; border-color: #fecaca; }
    .action-btn.danger:hover { background: #fef2f2; color: #b91c1c; }
    .branch-context {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 10px;
        padding: 0.6rem 0.85rem;
        font-size: 0.82rem;
        color: #1e40af;
    }
    /* Desktop table */
    .desktop-table { display: none; }
    .mobile-cards { display: block; }
    @media (min-width: 992px) {
        .desktop-table { display: block; }
        .mobile-cards { display: none; }
    }
    .desk-table th {
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
        background: #f8fafc;
        padding: 0.75rem 1rem;
        border: none;
    }
    .desk-table td {
        padding: 0.85rem 1rem;
        vertical-align: middle;
        font-size: 0.88rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .desk-table tr:hover td { background: #fafbfc; }
    .desk-table tr.low-stock td { background: #fffbfb; }
    .desk-table tr.expiring td { background: #fffef7; }
    .tab-pills .btn { font-size: 0.8rem; }
</style>

<div class="container-fluid p-0">
    <div class="wh-header shadow-sm">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
            <div>
                <h1 class="h4 fw-bold mb-1">Warehouse Stock</h1>
                <p class="text-muted small mb-0">Inventory overview with branch-specific pricing</p>
            </div>
            <a href="{{ route('warehouse.create') }}" class="btn btn-primary btn-sm rounded-pill px-3">
                <i class="bi bi-plus-lg me-1"></i>Add Item
            </a>
        </div>
        <div class="tab-pills d-flex gap-2 flex-wrap">
            <a href="{{ route('warehouse.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">All ({{ $totalItemsCount }})</a>
            <a href="{{ route('warehouse.alerts.expiry') }}" class="btn btn-sm btn-outline-warning rounded-pill">Expiry ({{ $expiryAlertsCount + $expiredCount }})</a>
            <a href="{{ route('warehouse.alerts.low-stock') }}" class="btn btn-sm btn-outline-danger rounded-pill">Low Stock ({{ $lowStockCount }})</a>
            @php $pendingTransferCount = \App\Models\WarehouseTransferRequest::where('status', 'pending')->count(); @endphp
            <a href="{{ route('warehouse.transfer-requests') }}" class="btn btn-sm btn-outline-primary rounded-pill">
                Transfers @if($pendingTransferCount > 0)<span class="badge bg-warning text-dark ms-1">{{ $pendingTransferCount }}</span>@endif
            </a>
        </div>
    </div>

    <div class="px-3 px-md-4 pb-4">
        {{-- Filters --}}
        <div class="wh-card p-3 mb-3">
            <form method="GET" action="{{ route('warehouse.index') }}" class="filter-bar">
                <input type="text" name="search" value="{{ $search ?? '' }}" class="form-control flex-grow-1" placeholder="Search items..." style="min-width:140px">
                <select name="bar_id" class="form-select" style="min-width:160px;max-width:220px" onchange="this.form.submit()">
                    <option value="">All Branches</option>
                    @foreach($bars as $bar)
                        <option value="{{ $bar->id }}" {{ (int)$selectedBarId === $bar->id ? 'selected' : '' }}>{{ $bar->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary btn-sm px-3"><i class="bi bi-search"></i></button>
                @if($search || $selectedBarId)
                    <a href="{{ route('warehouse.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                @endif
            </form>
            @if($selectedBar)
                <div class="branch-context mt-2">
                    <i class="bi bi-shop me-1"></i>Showing prices for <strong>{{ $selectedBar->name }}</strong>
                </div>
            @else
                <div class="branch-context mt-2" style="background:#f8fafc;border-color:#e2e8f0;color:#64748b">
                    <i class="bi bi-info-circle me-1"></i>Select a branch to see exact selling prices, or view price ranges below
                </div>
            @endif
        </div>

        {{-- Summary --}}
        <div class="summary-grid mb-3">
            <div class="summary-tile">
                <div class="lbl">Stock Cost</div>
                <div class="val">MWK {{ number_format($totalStockCost, 0) }}</div>
            </div>
            <div class="summary-tile">
                <div class="lbl">{{ $selectedBar ? $selectedBar->name . ' Value' : 'Total Value' }}</div>
                <div class="val text-primary">MWK {{ number_format($totalStockValue, 0) }}</div>
            </div>
            <div class="summary-tile">
                <div class="lbl">Expected Profit</div>
                <div class="val text-success">MWK {{ number_format($totalExpectedProfit, 0) }}</div>
            </div>
            <div class="summary-tile">
                <div class="lbl">Low Stock</div>
                <div class="val text-danger">{{ $lowStockCount }}</div>
            </div>
        </div>

        @if($stocks->isEmpty())
            <div class="wh-card text-center py-5">
                <div class="opacity-25 display-4 mb-2">📦</div>
                <p class="text-muted mb-0">No items found.</p>
            </div>
        @else
        @php $stockItems = $stocks->items(); @endphp
        <div class="mobile-cards">
        @foreach($stockItems as $stock)
            @php
                $baseUnit = $stock->units->firstWhere('is_base_unit', true);
                $unitCost = $stock->getUnitCost();
                $branchPrices = $stock->getBranchSellingPricesForDisplay();
                if ($selectedBarId) {
                    $sellPrice = $stock->getSellingPriceForBranch((int) $selectedBarId);
                    $profitPct = $stock->getProfitPercentageForBranch((int) $selectedBarId);
                    $unitLabel = $selectedBar ? strtolower($stock->getSellingUnitLabelForBranch($selectedBar)) : 'unit';
                    $priceLabel = 'MWK ' . number_format($sellPrice ?? 0, 0) . '/' . $unitLabel;
                } else {
                    $priceLabel = collect($branchPrices)->map(function ($bp) {
                        return $bp['bar_name'] . ': MWK ' . number_format($bp['price'], 0) . '/' . strtolower($bp['unit']);
                    })->implode("\n");
                    if ($priceLabel === '') {
                        $range = $stock->getSellingPriceRange();
                        $priceLabel = $range['has_range']
                            ? 'MWK ' . number_format($range['min'], 0) . ' – ' . number_format($range['max'], 0)
                            : 'MWK ' . number_format($range['min'], 0);
                    }
                    $profitPct = null;
                    $sellPrice = null;
                }
                $rowClass = $stock->isLowStock() ? 'low-stock' : ($stock->isExpiringsoon() ? 'expiring' : '');
            @endphp
                <div class="stock-card {{ $rowClass }}">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="item-name">{{ $stock->item_name }}</div>
                            <div class="meta">Bottles · bought as {{ $stock->purchase_unit ?? '—' }}</div>
                        </div>
                        <span class="status-badge {{ $stock->status_badge_class }}">{{ $stock->item_status }}</span>
                    </div>
                    <div class="row g-2">
                        <div class="col-4">
                            <div class="metric-pill">
                                <div class="lbl">Bottles</div>
                                <div class="val {{ $stock->isLowStock() ? 'loss' : '' }}">{{ number_format($stock->getStockInBottles()) }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="metric-pill">
                                <div class="lbl">Cost</div>
                                <div class="val">{{ number_format($unitCost, 0) }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="metric-pill">
                                <div class="lbl">{{ $selectedBar ? 'Sell' : 'Prices' }}</div>
                                <div class="val price" style="font-size:0.72rem; white-space:pre-line; line-height:1.3">{{ $selectedBarId ? $priceLabel : e($priceLabel) }}</div>
                            </div>
                        </div>
                    </div>
                    @if($selectedBarId && $sellPrice)
                        <div class="small mt-2 {{ $profitPct >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($profitPct, 0) }}% markup at {{ $selectedBar->name }}
                        </div>
                    @endif
                    <div class="action-row">
                        <a href="{{ route('warehouse.show', $stock) }}" class="action-btn primary"><i class="bi bi-eye"></i> View</a>
                        <a href="{{ route('warehouse.edit', $stock) }}" class="action-btn"><i class="bi bi-pencil"></i> Edit</a>
                        <a href="{{ route('warehouse.restock', $stock) }}" class="action-btn"><i class="bi bi-plus-circle"></i> Restock</a>
                        <form action="{{ route('warehouse.destroy', $stock) }}" method="POST" class="d-inline flex-fill" style="flex:1;min-width:70px" onsubmit="return confirm('Delete {{ addslashes($stock->item_name) }} from warehouse? This cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="action-btn danger w-100"><i class="bi bi-trash"></i> Delete</button>
                        </form>
                    </div>
                </div>
        @endforeach
        </div>

        {{-- Desktop table (single loop, rendered inside table) --}}
        <div class="desktop-table">
            <div class="wh-card">
                <div class="table-responsive">
                    <table class="table desk-table mb-0">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Bottles Left</th>
                                <th class="text-end">Cost</th>
                                <th class="text-end">{{ $selectedBar ? 'Sell (' . $selectedBar->name . ')' : 'Sell Price' }}</th>
                                <th class="text-end">Markup</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stockItems as $stock)
                                @php
                                    $baseUnit = $stock->units->firstWhere('is_base_unit', true);
                                    $unitCost = $stock->getUnitCost();
                                    $branchPrices = $stock->getBranchSellingPricesForDisplay();
                                    if ($selectedBarId) {
                                        $sellPrice = $stock->getSellingPriceForBranch((int) $selectedBarId);
                                        $profitPct = $stock->getProfitPercentageForBranch((int) $selectedBarId);
                                        $unitLabel = $selectedBar ? strtolower($stock->getSellingUnitLabelForBranch($selectedBar)) : 'unit';
                                        $priceLabel = number_format($sellPrice ?? 0, 0) . '/' . $unitLabel;
                                    } else {
                                        $priceLabel = collect($branchPrices)->map(function ($bp) {
                                            return $bp['bar_name'] . ': ' . number_format($bp['price'], 0) . '/' . strtolower($bp['unit']);
                                        })->implode('<br>');
                                        if ($priceLabel === '') {
                                            $range = $stock->getSellingPriceRange();
                                            $priceLabel = $range['has_range']
                                                ? number_format($range['min'], 0) . ' – ' . number_format($range['max'], 0)
                                                : number_format($range['min'], 0);
                                        }
                                        $profitPct = null;
                                    }
                                    $rowClass = $stock->isLowStock() ? 'low-stock' : ($stock->isExpiringsoon() ? 'expiring' : '');
                                @endphp
                                <tr class="{{ $rowClass }}">
                                    <td>
                                        <div class="fw-bold">{{ $stock->item_name }}</div>
                                        <div class="small text-muted">Bought as {{ $stock->purchase_unit ?? '—' }}</div>
                                    </td>
                                    <td class="fw-semibold {{ $stock->isLowStock() ? 'text-danger' : '' }}">{{ number_format($stock->getStockInBottles()) }}</td>
                                    <td class="text-end">MWK {{ number_format($unitCost, 0) }}</td>
                                    <td class="text-end fw-semibold text-primary small">
                                        @if($selectedBarId)
                                            MWK {{ $priceLabel }}
                                        @else
                                            @foreach($branchPrices as $bp)
                                                <div>{{ $bp['bar_name'] }}: MWK {{ number_format($bp['price'], 0) }}/{{ strtolower($bp['unit']) }}</div>
                                            @endforeach
                                            @if(count($branchPrices) === 0)
                                                MWK {!! $priceLabel !!}
                                            @endif
                                        @endif
                                    </td>
                                    <td class="text-end {{ $profitPct !== null ? ($profitPct >= 0 ? 'text-success' : 'text-danger') : 'text-muted' }}">
                                        {{ $profitPct !== null ? number_format($profitPct, 0) . '%' : '—' }}
                                    </td>
                                    <td><span class="status-badge {{ $stock->status_badge_class }}">{{ $stock->item_status }}</span></td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('warehouse.show', $stock) }}" class="btn btn-sm btn-outline-secondary py-0 px-2" title="View"><i class="bi bi-eye"></i></a>
                                        <a href="{{ route('warehouse.edit', $stock) }}" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <a href="{{ route('warehouse.restock', $stock) }}" class="btn btn-sm btn-outline-primary py-0 px-2" title="Restock"><i class="bi bi-plus-circle"></i></a>
                                        <form action="{{ route('warehouse.destroy', $stock) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete {{ addslashes($stock->item_name) }} from warehouse? This cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center py-5 text-muted">No items found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        @if($stocks->hasPages())
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                <div class="small text-muted">
                    {{ $stocks->firstItem() }}–{{ $stocks->lastItem() }} of {{ $stocks->total() }}
                </div>
                <div>
                    {{ $stocks->withQueryString()->links() }}
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
