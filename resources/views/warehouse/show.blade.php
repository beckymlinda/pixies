@extends('layouts.app')

@section('content')
@php 
    $pageTitle = 'Warehouse Item Details'; 
@endphp
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .form-header {
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
    .section-card {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    .section-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: #1e293b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #e2e8f0;
    }
    .info-label {
        font-size: 0.75rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.25rem;
    }
    .info-value {
        font-size: 1rem;
        font-weight: 600;
        color: #1e293b;
    }
    .ledger-table th {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        background: #f8fafc;
        padding: 12px 16px !important;
        border-top: none !important;
    }
    .ledger-table td {
        padding: 14px 16px !important;
        vertical-align: middle !important;
        font-size: 0.9rem;
        color: #1e293b;
    }
    .transaction-addition {
        color: #10b981;
    }
    .transaction-deduction {
        color: #ef4444;
    }
</style>

<div class="container-fluid p-0">
    <div class="form-header shadow-sm">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h1 class="h3 fw-bold mb-1 text-dark">{{ $warehouseStock->item_name }}</h1>
                <p class="text-muted small mb-0">View item details, inventory history, and branch pricing.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('warehouse.restock', $warehouseStock) }}" class="btn btn-primary rounded-pill px-4">
                    <i class="bi bi-plus-circle me-2"></i>Restock
                </a>
                <a href="{{ route('warehouse.index') }}" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="bi bi-arrow-left me-2"></i>Back to List
                </a>
            </div>
        </div>
    </div>

    <div class="px-4">
        <!-- SECTION A: Item Information -->
        <div class="section-card">
            <div class="section-title">
                <i class="bi bi-box-seam me-2"></i>Item Information
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="info-label">Item Name</div>
                    <div class="info-value">{{ $warehouseStock->item_name }}</div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="info-label">Current Stock</div>
                    <div class="info-value {{ $warehouseStock->isLowStock() ? 'text-danger' : 'text-dark' }}">{{ $warehouseStock->quantity }}</div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="info-label">Alert Quantity</div>
                    <div class="info-value">{{ $warehouseStock->alert_quantity }}</div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="info-label">Status</div>
                    <span class="badge {{ $warehouseStock->status_badge_class }}">{{ $warehouseStock->item_status }}</span>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="info-label">Purchase Unit</div>
                    <div class="info-value">{{ $warehouseStock->purchase_unit ?? 'N/A' }}</div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="info-label">Base Unit</div>
                    <div class="info-value">{{ $warehouseStock->units->firstWhere('is_base_unit', true)?->unit_name ?? 'N/A' }}</div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="info-label">Conversion</div>
                    <div class="info-value">{{ $warehouseStock->units->where('is_base_unit', false)->firstWhere('unit_name', $warehouseStock->purchase_unit)?->conversion_factor ?? '—' }} per {{ $warehouseStock->purchase_unit ?? 'unit' }}</div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="info-label">Expiry Date</div>
                    <div class="info-value">{{ $warehouseStock->expiry_date ? $warehouseStock->expiry_date->format('M d, Y') : 'N/A' }}</div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="info-label">Date Created</div>
                    <div class="info-value">{{ $warehouseStock->created_at->format('M d, Y') }}</div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="info-label">Notes</div>
                    <div class="info-value">{{ $warehouseStock->notes ?? 'N/A' }}</div>
                </div>
            </div>
        </div>

        <!-- SECTION B: Units & Pricing -->
        <div class="section-card">
            <div class="section-title">
                <i class="bi bi-list-ul me-2"></i>Units &amp; Selling Prices
            </div>
            <div class="table-responsive">
                <table class="table ledger-table mb-0">
                    <thead>
                        <tr>
                            <th>Unit</th>
                            <th>Type</th>
                            <th>Contains</th>
                            <th>Cost</th>
                            @foreach($bars as $bar)
                                <th>{{ $bar->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($warehouseStock->units as $unit)
                            <tr>
                                <td class="fw-semibold">{{ $unit->unit_name }}</td>
                                <td>
                                    @if($unit->is_base_unit)
                                        <span class="badge bg-primary">Base</span>
                                    @elseif($unit->unit_name === $warehouseStock->purchase_unit)
                                        <span class="badge bg-secondary">Purchase</span>
                                    @else
                                        <span class="badge bg-info text-dark">Selling</span>
                                    @endif
                                </td>
                                <td>{{ $unit->is_base_unit ? '1' : $unit->conversion_factor . ' ' . ($warehouseStock->units->firstWhere('is_base_unit', true)?->unit_name ?? 'units') }}</td>
                                <td>MWK {{ number_format($unit->purchase_price, 0) }}</td>
                                @foreach($bars as $bar)
                                    @php
                                        $barPrice = $unit->barPrices->firstWhere('bar_id', $bar->id);
                                        $showDash = $unit->is_base_unit
                                            && ($unit->unit_name === 'Shot')
                                            && ($bar->name === \App\Models\Bar::LIQUOR_SHOP || ! $bar->allowsWarehouseTransferUnit('Shot'));
                                    @endphp
                                    <td>
                                        @if($showDash)
                                            <span class="text-muted">—</span>
                                        @elseif($barPrice && (float) $barPrice->selling_price > 0)
                                            <span class="fw-semibold text-primary">MWK {{ number_format($barPrice->selling_price, 0) }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ 4 + $bars->count() }}" class="text-center text-muted py-3">No units configured.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mb-0 mt-2">Additional unit prices flow to seller sales records when items are transferred.</p>
        </div>

        <!-- SECTION C: Cost Summary -->
        <div class="section-card">
            <div class="section-title">
                <i class="bi bi-currency-dollar me-2"></i>Cost Summary
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="info-label">Current Unit Cost</div>
                    <div class="info-value">MWK {{ number_format($warehouseStock->average_unit_cost > 0 ? $warehouseStock->average_unit_cost : $warehouseStock->purchase_price, 2) }}</div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="info-label">Current Selling Price</div>
                    <div class="info-value text-primary">MWK {{ number_format($warehouseStock->selling_price, 2) }}</div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="info-label">Current Profit Per Unit</div>
                    <div class="info-value {{ $warehouseStock->profit_percentage >= 0 ? 'text-success' : 'text-danger' }}">
                        MWK {{ number_format($warehouseStock->selling_price - ($warehouseStock->average_unit_cost > 0 ? $warehouseStock->average_unit_cost : $warehouseStock->purchase_price), 2) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION D: Branch Pricing -->
        <div class="section-card">
            <div class="section-title">
                <i class="bi bi-shop me-2"></i>Branch Pricing
            </div>
            <div class="table-responsive">
                <table class="table ledger-table mb-0">
                    <thead>
                        <tr>
                            <th>Branch</th>
                            <th>Selling Price</th>
                            <th>Profit Per Unit</th>
                            <th>Current Stock Value</th>
                            <th>Potential Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bars as $bar)
                            @php
                                $branchPrice = $warehouseStock->getSellingPriceForBranch($bar->id);
                                $branchCost = $warehouseStock->getUnitCost();
                                if ($baseUnit = $warehouseStock->units->firstWhere('is_base_unit', true)) {
                                    if ($baseUnit->unit_name === 'Shot' && ($bar->name === \App\Models\Bar::LIQUOR_SHOP || ! $bar->allowsWarehouseTransferUnit('Shot'))) {
                                        $bottleUnit = $warehouseStock->getBottleSellingUnit();
                                        $branchCost = $bottleUnit ? (float) $bottleUnit->purchase_price : $branchCost;
                                    }
                                }
                                $branchProfit = $branchPrice !== null ? $branchPrice - $branchCost : null;
                            @endphp
                            <tr>
                                <td>{{ $bar->name }}</td>
                                <td>{{ $branchPrice !== null ? 'MWK ' . number_format($branchPrice, 2) : '—' }}</td>
                                <td class="{{ $branchProfit !== null ? ($branchProfit >= 0 ? 'text-success' : 'text-danger') : 'text-muted' }}">
                                    {{ $branchProfit !== null ? 'MWK ' . number_format($branchProfit, 2) : '—' }}
                                </td>
                                <td>{{ $branchPrice !== null ? 'MWK ' . number_format($warehouseStock->quantity * $branchPrice, 2) : '—' }}</td>
                                <td class="{{ $branchProfit !== null && $warehouseStock->quantity > 0 ? ($branchProfit * $warehouseStock->quantity >= 0 ? 'text-success' : 'text-danger') : 'text-muted' }}">
                                    {{ $branchProfit !== null && $warehouseStock->quantity > 0 ? 'MWK ' . number_format($branchProfit * $warehouseStock->quantity, 2) : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION E: Inventory Ledger -->
        <div class="section-card">
            <div class="section-title">
                <i class="bi bi-journal-text me-2"></i>Inventory Ledger
            </div>
            <div class="table-responsive">
                <table class="table ledger-table mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Action</th>
                            <th>Quantity</th>
                            <th>Destination Bar</th>
                            <th>Unit Cost</th>
                            <th>Total Cost</th>
                            <th>Supplier</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($warehouseStock->transactions as $transaction)
                            <tr>
                                <td>{{ $transaction->transaction_date->format('M d, Y') }}</td>
                                <td>
                                    <span class="badge {{ $transaction->transaction_type === 'purchase' ? 'bg-primary' : ($transaction->transaction_type === 'restock' ? 'bg-success' : 'bg-secondary') }}">
                                        {{ ucfirst($transaction->transaction_type) }}
                                    </span>
                                </td>
                                <td class="{{ $transaction->isAddition() ? 'transaction-addition' : 'transaction-deduction' }}">
                                    {{ $transaction->isAddition() ? '+' : '' }}{{ $transaction->quantity }}
                                </td>
                                <td>
                                    @if($transaction->destinationBar)
                                        <span class="badge bg-primary">{{ $transaction->destinationBar->name }}</span>
                                    @elseif($transaction->transaction_type === 'transfer' && $transaction->notes)
                                        <span class="text-muted small">{{ $transaction->notes }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $transaction->unit_cost ? 'MWK ' . number_format($transaction->unit_cost, 2) : 'N/A' }}</td>
                                <td>{{ $transaction->total_cost ? 'MWK ' . number_format($transaction->total_cost, 2) : 'N/A' }}</td>
                                <td>{{ $transaction->supplier ?? 'N/A' }}</td>
                                <td>{{ $transaction->reference_number ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <p class="text-muted mb-0">No transaction history found.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION F: Audit Summary -->
        <div class="section-card">
            <div class="section-title">
                <i class="bi bi-graph-up me-2"></i>Audit Summary
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="info-label">Lifetime Quantity Purchased</div>
                    <div class="info-value">{{ number_format($warehouseStock->lifetime_quantity_purchased) }}</div>
                    <div class="small text-muted">From purchase &amp; restock ledger entries only</div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="info-label">Lifetime Quantity Issued</div>
                    <div class="info-value">{{ number_format($warehouseStock->lifetime_quantity_sold) }}</div>
                    <div class="small text-muted">Transfers to bars and warehouse sales</div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="info-label">Current Stock On Hand</div>
                    <div class="info-value">{{ number_format($warehouseStock->quantity) }}</div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="info-label">Realized Profit</div>
                    <div class="info-value {{ $warehouseStock->getRealizedProfit() >= 0 ? 'text-success' : 'text-danger' }}">
                        MWK {{ number_format($warehouseStock->getRealizedProfit(), 2) }}
                    </div>
                    <div class="small text-muted">Sales revenue minus cost of goods sold</div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="info-label">Unrealized Profit</div>
                    <div class="info-value {{ $warehouseStock->getUnrealizedProfit() >= 0 ? 'text-success' : 'text-danger' }}">
                        MWK {{ number_format($warehouseStock->getUnrealizedProfit(), 2) }}
                    </div>
                    <div class="small text-muted">Current stock value minus current cost value</div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="info-label">Net Inventory Position</div>
                    <div class="info-value {{ $warehouseStock->getNetInventoryPosition() >= 0 ? 'text-success' : 'text-danger' }}">
                        MWK {{ number_format($warehouseStock->getNetInventoryPosition(), 2) }}
                    </div>
                    <div class="small text-muted">Realized + unrealized profit</div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="info-label">Current Stock Value</div>
                    <div class="info-value text-primary">MWK {{ number_format($warehouseStock->quantity * $warehouseStock->selling_price, 2) }}</div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="info-label">Current Cost Value</div>
                    <div class="info-value">MWK {{ number_format($warehouseStock->quantity * $warehouseStock->getUnitCost(), 2) }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
