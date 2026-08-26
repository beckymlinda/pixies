@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .credit-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: -1.5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }
    .summary-card-modern {
        border-radius: 16px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: white;
        padding: 1.25rem;
        height: 100%;
        transition: transform 0.2s;
    }
    .summary-card-modern:hover {
        transform: translateY(-3px);
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
        padding: 16px !important;
        vertical-align: middle !important;
        font-size: 0.875rem;
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

    @media (max-width: 768px) {
        .credit-header { flex-direction: column; align-items: flex-start !important; gap: 1rem; padding: 1rem; }
        .data-table thead { display: none; }
        .data-table tr { display: block; border-bottom: 1px solid #e2e8f0; padding: 15px; }
        .data-table td { display: flex; justify-content: space-between; align-items: center; border: none !important; padding: 8px 0 !important; width: 100%; }
        .data-table td::before { content: attr(data-label); font-weight: 700; font-size: 0.75rem; color: #64748b; text-transform: uppercase; }
        .btn-action { width: auto; height: auto; padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; }
    }
</style>

<div class="container-fluid p-0">
    <!-- Modern Header -->
    <div class="credit-header d-flex align-items-center justify-content-between shadow-sm">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark">Credit Accounts</h1>
            <p class="text-muted small mb-0">Manage customer debts, payments, and outstanding balances.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('credit-customers.create') }}" class="btn btn-primary rounded-pill px-4 shadow-sm">
                <i class="bi bi-plus-lg me-2"></i>New Entry
            </a>
            <a href="{{ route('credit-customers.export', request()->only(['search', 'filter'])) }}" class="btn btn-light border rounded-pill px-4 shadow-sm bg-white">
                <i class="bi bi-download me-2"></i>Export CSV
            </a>
        </div>
    </div>

    <div class="px-4 pb-5">
        <!-- Summary Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="summary-card-modern shadow-sm border-0">
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Total Outstanding</div>
                    <div class="h4 mb-0 fw-bold text-danger">
                        <span class="small opacity-50">MWK</span> {{ number_format($totalOutstanding) }}
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card-modern shadow-sm border-0">
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Active Accounts</div>
                    <div class="h4 mb-0 fw-bold text-dark">{{ $totalCustomers }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card-modern shadow-sm border-0">
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Open (Unpaid)</div>
                    <div class="h4 mb-0 fw-bold text-warning">{{ $openAccounts }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card-modern shadow-sm border-0">
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Partial Payments</div>
                    <div class="h4 mb-0 fw-bold text-info">{{ $partialAccounts }}</div>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
            <div class="card-body p-3">
                <form method="GET" class="row g-2">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control border-0 bg-light" placeholder="Search by name or phone..." value="{{ $search }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="filter" class="form-select border-0 bg-light">
                            <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>All Statuses</option>
                            <option value="open" {{ $filter === 'open' ? 'selected' : '' }}>Open Debts</option>
                            <option value="partial" {{ $filter === 'partial' ? 'selected' : '' }}>Partial Paid</option>
                            <option value="paid" {{ $filter === 'paid' ? 'selected' : '' }}>Fully Paid</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary rounded-pill px-4 flex-grow-1">Filter</button>
                        <a href="{{ route('credit-customers.index') }}" class="btn btn-light rounded-pill px-3 border">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Table Card -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="table-responsive">
                <table class="table data-table mb-0">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            @if(auth()->user()->isAdmin())
                                <th>Bar</th>
                            @endif
                            <th>Phone</th>
                            <th class="text-end">Total Credit</th>
                            <th class="text-end">Total Paid</th>
                            <th class="text-end">Balance</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $customer)
                            <tr>
                                <td data-label="Customer">
                                    <div class="fw-bold text-dark">{{ $customer->customer_name }}</div>
                                    @if($customer->total_balance > 50000)
                                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2 py-1" style="font-size: 0.6rem;">High Balance</span>
                                    @endif
                                </td>
                                @if(auth()->user()->isAdmin())
                                    <td data-label="Bar">
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2 py-1" style="font-size: 0.75rem;">
                                            {{ $customer->bar->name ?? 'Global' }}
                                        </span>
                                    </td>
                                @endif
                                <td data-label="Phone">
                                    <div class="text-muted small">{{ $customer->phone ?: 'N/A' }}</div>
                                </td>
                                <td data-label="Total" class="text-end fw-semibold">
                                    {{ number_format($customer->total_amount) }}
                                </td>
                                <td data-label="Paid" class="text-end text-success fw-semibold">
                                    {{ number_format($customer->total_paid) }}
                                </td>
                                <td data-label="Balance" class="text-end">
                                    <div class="fw-bold {{ $customer->total_balance > 0 ? 'text-danger' : 'text-success' }}">
                                        MWK {{ number_format($customer->total_balance) }}
                                    </div>
                                </td>
                                <td data-label="Status" class="text-center">
                                    @if($customer->status === 'open')
                                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-1">Open</span>
                                    @elseif($customer->status === 'partial')
                                        <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-1">Partial</span>
                                    @else
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1">Paid</span>
                                    @endif
                                </td>
                                <td data-label="Actions" class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <a href="{{ route('credit-customers.show', $customer->customer_name) }}" class="btn-action shadow-sm" title="View History">
                                            <i class="bi bi-eye-fill"></i>
                                        </a>
                                        @if($customer->total_balance > 0)
                                            <a href="{{ route('credit-customers.payment', $customer->customer_name) }}" class="btn btn-sm btn-success rounded-pill px-3 py-1 shadow-sm" style="font-size: 0.75rem;">
                                                <i class="bi bi-cash me-1"></i>Pay
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="opacity-25 display-4 mb-3">👤</div>
                                    <p class="text-muted">No credit customers found.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
