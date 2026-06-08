@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .welcome-card {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: white;
        border-radius: 20px;
        border: none;
        overflow: hidden;
        position: relative;
    }
    .welcome-card::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
        z-index: 0;
    }
    .welcome-content {
        position: relative;
        z-index: 1;
    }
    .action-card {
        border-radius: 16px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: white;
    }
    .action-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 20px -5px rgba(0, 0, 0, 0.1);
    }
    .icon-box {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin-bottom: 16px;
    }
    .progress-track {
        height: 8px;
        background: #f1f5f9;
        border-radius: 4px;
        overflow: hidden;
    }
    .progress-fill {
        height: 100%;
        background: var(--pixies-primary);
        border-radius: 4px;
    }
    .stat-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        font-weight: 600;
    }
    .recent-entry-item {
        border-radius: 12px;
        padding: 12px;
        transition: background 0.2s;
    }
    .recent-entry-item:hover {
        background: #f8fafc;
    }
</style>

<div class="container-fluid px-4 py-4">
    <!-- Welcome Header -->
    <div class="card welcome-card mb-4 shadow-lg">
        <div class="card-body p-4 p-md-5">
            <div class="welcome-content d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <h1 class="display-5 fw-bold mb-2">Hello, {{ auth()->user()->name }}!</h1>
                    <p class="lead opacity-75 mb-0">Manage your bar operations efficiently today.</p>
                    @if(auth()->user()->bar)
                        <div class="mt-3 d-inline-flex align-items-center bg-white bg-opacity-10 rounded-pill px-3 py-1">
                            <span class="me-2 small">📍</span>
                            <span class="small fw-semibold">{{ auth()->user()->bar->name }}</span>
                        </div>
                    @endif
                </div>
                <div class="mt-4 mt-md-0 d-flex gap-2">
                    <div class="text-end d-none d-md-block">
                        <div class="small opacity-75">Today is</div>
                        <div class="fw-bold fs-5 text-white">{{ now()->format('l, M d') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content with Sidebar -->
    <div class="row g-4 mb-4">
        <!-- Left Column: Main Content -->
        <div class="col-lg-9">
            <!-- Quick Actions Grid -->
            <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card h-100 action-card shadow-sm">
                <div class="card-body p-4">
                    <div class="icon-box bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <h3 class="h5 fw-bold mb-2">Daily Inventory</h3>
                    <p class="text-muted small mb-4">Record opening stock, daily orders, and closing stock for today.</p>
                    <a href="{{ route('stock-entries.create') }}" class="btn btn-primary w-100 rounded-pill py-2">
                        <i class="bi bi-plus-lg me-2"></i>Create New Entry
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card h-100 action-card shadow-sm">
                <div class="card-body p-4">
                    <div class="icon-box bg-slate-100 text-slate-800">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <h3 class="h5 fw-bold mb-2">Expenses</h3>
                    <p class="text-muted small mb-2">Track daily petty cash and bar operational costs.</p>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">This Week</span>
                            <span class="fw-bold">MWK {{ number_format($weeklyExpenses ?? 0) }}</span>
                        </div>
                    </div>
                    <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary w-100 rounded-pill py-2">
                        Manage Expenses
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 action-card shadow-sm">
                <div class="card-body p-4">
                    <div class="icon-box bg-emerald-50 text-emerald-600" style="background: #ecfdf5; color: #059669;">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <h3 class="h5 fw-bold mb-2">Sales Analytics</h3>
                    <p class="text-muted small mb-2">Monitor your sales performance across your location.</p>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Lifetime Sales</span>
                            <span class="fw-bold text-success">MWK {{ number_format($totalSales ?? 0) }}</span>
                        </div>
                    </div>
                    <a href="{{ route('stock-entries.index') }}" class="btn btn-outline-secondary w-100 rounded-pill py-2">
                        View History
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 action-card shadow-sm">
                <div class="card-body p-4">
                    <div class="icon-box" style="background: #fef3c7; color: #d97706;">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <h3 class="h5 fw-bold mb-2">Stock Expiry</h3>
                    <p class="text-muted small mb-4">Monitor items approaching expiration dates to avoid waste.</p>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Items expiring soon</span>
                            <span class="badge" style="background: #fbbf24; color: #78350f; font-size: 1.1em;">{{ $expiringItemsCount ?? 0 }}</span>
                        </div>
                    </div>
                    <a href="{{ route('stock-expiry.index') }}" class="btn btn-warning w-100 rounded-pill py-2">
                        View Details
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Performance Table -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom-0">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h4 class="h5 fw-bold mb-0 text-dark">Recent Stock Activity</h4>
                        <a href="{{ route('stock-entries.index') }}" class="btn btn-link btn-sm text-decoration-none p-0">View All</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 stat-label py-3">Date</th>
                                    <th class="stat-label py-3">Location</th>
                                    <th class="stat-label py-3">Total Sales</th>
                                    <th class="pe-4 text-end stat-label py-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentEntries as $entry)
                                    <tr class="recent-entry-item">
                                        <td class="ps-4 py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-light rounded-3 p-2 me-3 d-none d-sm-block">
                                                    <i class="bi bi-calendar-event text-secondary"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark">{{ $entry->date->format('D, M d') }}</div>
                                                    <div class="small text-muted">{{ $entry->date->format('Y') }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3">
                                            <span class="badge bg-light text-dark border border-secondary border-opacity-10 px-3 py-2 rounded-pill">
                                                {{ $entry->bar->name }}
                                            </span>
                                        </td>
                                        <td class="py-3">
                                            <div class="fw-bold text-primary">MWK {{ number_format($entry->stockEntryItems->sum('sales_amount')) }}</div>
                                        </td>
                                        <td class="pe-4 text-end py-3">
                                            <a href="{{ route('stock-entries.show', $entry) }}" class="btn btn-sm btn-light rounded-circle shadow-sm">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-5">
                                            <div class="opacity-25 display-4 mb-3">📝</div>
                                            <p class="text-muted">No stock entries found for this location.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
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
@endsection
