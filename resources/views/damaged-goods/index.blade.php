@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .damaged-header {
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
        padding: 14px 20px !important;
        border-top: none !important;
    }
    .data-table td {
        padding: 16px 20px !important;
        vertical-align: middle !important;
        font-size: 0.875rem;
        color: #1e293b;
    }

    @media (max-width: 768px) {
        .damaged-header { flex-direction: column; align-items: flex-start !important; gap: 1rem; padding: 1rem; }
        .data-table thead { display: none; }
        .data-table tr { display: block; border-bottom: 1px solid #e2e8f0; padding: 15px; }
        .data-table td { display: flex; justify-content: space-between; align-items: center; border: none !important; padding: 8px 0 !important; width: 100%; }
        .data-table td::before { content: attr(data-label); font-weight: 700; font-size: 0.75rem; color: #64748b; text-transform: uppercase; }
    }
</style>

<div class="container-fluid p-0">
    <!-- Modern Header -->
    <div class="damaged-header d-flex align-items-center justify-content-between shadow-sm">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark">Damaged Goods</h1>
            <p class="text-muted small mb-0">Stock write-offs recorded on the Balance page appear here.</p>
        </div>
        @if(auth()->user()->bar)
            <span class="badge bg-dark text-white rounded-pill px-3 py-2">📍 {{ auth()->user()->bar->name }}</span>
        @endif
    </div>

    <div class="px-4 pb-5">
        @if(auth()->user()->isSeller() && !auth()->user()->bar_id)
            <div class="alert alert-warning border-0 shadow-sm rounded-3 mb-3">
                You are not assigned to a bar. Damaged goods for your branch will appear here once recorded on Balance.
            </div>
        @endif

        <!-- Summary Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="summary-card-modern shadow-sm border-0">
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Total Damages</div>
                    <div class="h4 mb-0 fw-bold text-danger">
                        <span class="small opacity-50">MWK</span> {{ number_format($totalAmount) }}
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card-modern shadow-sm border-0">
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Total Incidents</div>
                    <div class="h4 mb-0 fw-bold text-dark">{{ $totalCount }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card-modern shadow-sm border-0">
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">This Month</div>
                    <div class="h4 mb-0 fw-bold text-warning">
                        <span class="small opacity-50">MWK</span> {{ number_format($thisMonthTotal) }}
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card-modern shadow-sm border-0">
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">With Photo Evidence</div>
                    <div class="h4 mb-0 fw-bold text-info">{{ $withPhoto }}</div>
                </div>
            </div>
        </div>

        @if(!auth()->user()->isSeller())
            <!-- Filter Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2">
                        <div class="col-md-4">
                            <select name="bar_id" class="form-select border-0 bg-light" onchange="this.form.submit()">
                                <option value="">All bars</option>
                                @foreach($bars as $bar)
                                    <option value="{{ $bar->id }}" {{ (string) request('bar_id') === (string) $bar->id ? 'selected' : '' }}>{{ $bar->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <a href="{{ route('damaged-goods.index') }}" class="btn btn-light rounded-pill px-3 border">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Table Card -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="table-responsive">
                <table class="table data-table mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Photo</th>
                            <th>Damaged Item</th>
                            <th>Bar</th>
                            <th>Recorded By</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($damagedGoods as $entry)
                            <tr>
                                <td data-label="Date">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light rounded-3 p-2 me-3 d-none d-md-block">
                                            <i class="bi bi-calendar-x text-secondary"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark">{{ $entry->date->format('M d, Y') }}</div>
                                            <div class="small text-muted">{{ $entry->date->format('l') }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Photo">
                                    @if($entry->photoUrl())
                                        <a href="{{ $entry->photoUrl() }}" target="_blank" rel="noopener">
                                            <img src="{{ $entry->photoUrl() }}" alt="Damaged goods" class="rounded border" style="width: 48px; height: 48px; object-fit: cover;">
                                        </a>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td data-label="Item">
                                    <div class="fw-bold text-dark">{{ $entry->description }}</div>
                                </td>
                                <td data-label="Bar">
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2 py-1" style="font-size: 0.75rem;">
                                        {{ $entry->bar->name ?? 'Global' }}
                                    </span>
                                </td>
                                <td data-label="Recorded By">
                                    <div class="text-muted small">{{ $entry->user->name ?? 'N/A' }}</div>
                                </td>
                                <td data-label="Amount" class="text-end">
                                    <div class="fw-bold text-danger">MWK {{ number_format($entry->amount) }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="opacity-25 display-4 mb-3">📦</div>
                                    <p class="text-muted mb-0">No damaged goods recorded yet.</p>
                                    <p class="text-muted small">Record them on the <a href="{{ route('reporting.index') }}">Balance</a> page under Expenditure → Damages.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($damagedGoods->hasPages())
                <div class="card-footer bg-white border-top p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="small text-muted">
                        Showing page {{ $damagedGoods->currentPage() }} of {{ $damagedGoods->lastPage() }}
                    </div>
                    <div class="shadow-sm bg-white">
                        {{ $damagedGoods->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
