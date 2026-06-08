@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .manager-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: -1.5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }
    .action-card-modern {
        border-radius: 16px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: white;
        padding: 2rem;
        transition: transform 0.3s;
        height: 100%;
    }
    .action-card-modern:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 20px -5px rgba(0, 0, 0, 0.1);
    }
    .stat-pill {
        background: #f8fafc;
        border-radius: 16px;
        padding: 1.5rem;
        text-align: center;
        border: 1px solid #e2e8f0;
    }
</style>

<div class="container-fluid p-0">
    <!-- Modern Header -->
    <div class="manager-header d-flex align-items-center justify-content-between shadow-sm">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark">Manager Command Center</h1>
            <p class="text-muted small mb-0">Oversee operations, financial health, and inventory across all bar locations.</p>
        </div>
        <div class="text-end d-none d-md-block">
            <div class="small text-muted">Business Date</div>
            <div class="fw-bold text-dark">{{ now()->format('l, M d, Y') }}</div>
        </div>
    </div>

    <div class="px-4">
        <!-- Main Content with Sidebar -->
        <div class="row g-4 mb-5">
            <!-- Left Column -->
            <div class="col-lg-9">
                <!-- Quick Actions -->
                <div class="row g-4 mb-5">
                    <div class="col-md-6">
                        <div class="action-card-modern shadow-sm">
                            <div class="icon-box bg-primary bg-opacity-10 text-primary mb-4">
                                <i class="bi bi-cash-coin fs-4"></i>
                            </div>
                            <h3 class="h5 fw-bold mb-3 text-dark">Cash Reconciliation</h3>
                            <p class="text-secondary small mb-4">Verify daily cash counts from sellers and investigate any discrepancies immediately.</p>
                            <a href="{{ route('reconciliation.index') }}" class="btn btn-dark w-100 rounded-pill py-2">
                                Verify Daily Cash
                            </a>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="action-card-modern shadow-sm">
                            <div class="icon-box bg-success bg-opacity-10 text-success mb-4">
                                <i class="bi bi-bar-chart-line fs-4"></i>
                            </div>
                            <h3 class="h5 fw-bold mb-3 text-dark">Financial Performance</h3>
                            <p class="text-secondary small mb-4">Access deep-dive reports on sales, expenses, and net profit margins per location.</p>
                            <a href="{{ route('reports.dashboard') }}" class="btn btn-primary w-100 rounded-pill py-2">
                                Open Reports
                            </a>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="action-card-modern shadow-sm">
                            <div class="icon-box bg-secondary bg-opacity-10 text-secondary mb-4">
                                <i class="bi bi-clipboard-check fs-4"></i>
                            </div>
                            <h3 class="h5 fw-bold mb-3 text-dark">Stock Oversight</h3>
                            <p class="text-secondary small mb-4">Review and audit all daily stock entries submitted by sellers across the network.</p>
                            <a href="{{ route('stock-entries.index') }}" class="btn btn-outline-dark w-100 rounded-pill py-2">
                                Review Entries
                            </a>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="action-card-modern shadow-sm">
                            <div class="icon-box bg-info bg-opacity-10 text-info mb-4">
                                <i class="bi bi-arrow-left-right fs-4"></i>
                            </div>
                            <h3 class="h5 fw-bold mb-3 text-dark">Warehouse Transfers</h3>
                            <p class="text-secondary small mb-4">Review and approve pending warehouse transfer requests from directors.</p>
                            @php
                                $pendingTransferCount = \App\Models\WarehouseTransferRequest::where('status', 'pending')->count();
                            @endphp
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-secondary small">Pending requests</span>
                                    <span class="badge bg-info">{{ $pendingTransferCount }}</span>
                                </div>
                            </div>
                            <a href="{{ route('warehouse.transfer-requests') }}" class="btn btn-info w-100 rounded-pill py-2">
                                Review Transfers
                            </a>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="action-card-modern shadow-sm">
                            <div class="icon-box" style="background: #fef3c7; color: #d97706;">
                                <i class="bi bi-exclamation-triangle fs-4"></i>
                            </div>
                            <h3 class="h5 fw-bold mb-3 text-dark">Stock Expiry</h3>
                            <p class="text-secondary small mb-4">Monitor items approaching expiration dates across all locations to prevent waste.</p>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-secondary small">Items expiring soon</span>
                                    <span class="badge" style="background: #fbbf24; color: #78350f; font-size: 0.9em;">{{ $expiringItemsCount ?? 0 }}</span>
                                </div>
                            </div>
                            <a href="{{ route('stock-expiry.index') }}" class="btn btn-warning w-100 rounded-pill py-2">
                                View Details
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Today's Live Stats -->
                @php
                    $today = \Carbon\Carbon::today();
                    $todaySales = \App\Models\StockEntryItem::whereHas('stockEntry', function($query) use ($today) {
                        $query->whereDate('date', $today);
                    })->sum('sales_amount');

                    $todayExpenses = \App\Models\Expense::whereHas('stockEntry', function($query) use ($today) {
                        $query->whereDate('date', $today);
                    })->sum('amount');

                    $todayProfit = $todaySales - $todayExpenses;
                @endphp

                <div class="row g-4 mb-5">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                            <div class="card-header bg-white py-3">
                                <h5 class="fw-bold mb-0 text-dark">Today's Real-time Overview</h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-4">
                                    <div class="col-md-4">
                                        <div class="stat-pill">
                                            <div class="small text-muted text-uppercase fw-bold mb-2" style="font-size: 0.65rem;">Today's Sales</div>
                                            <div class="h2 mb-0 fw-bold text-dark"><span class="small fs-6 opacity-50">MWK</span> {{ number_format($todaySales) }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="stat-pill">
                                            <div class="small text-muted text-uppercase fw-bold mb-2" style="font-size: 0.65rem;">Today's Expenses</div>
                                            <div class="h2 mb-0 fw-bold text-danger"><span class="small fs-6 opacity-50">MWK</span> {{ number_format($todayExpenses) }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="stat-pill border-primary border-opacity-25">
                                            <div class="small text-muted text-uppercase fw-bold mb-2" style="font-size: 0.65rem;">Today's Net Profit</div>
                                            <div class="h2 mb-0 fw-bold text-success"><span class="small fs-6 opacity-50">MWK</span> {{ number_format($todayProfit) }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Sidebar -->
            <div class="col-lg-3">
                @include('components.sidebar-quick-links')
            </div>
        </div>
    </div>
</div>
@endsection
