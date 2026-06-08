@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .recon-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: -1.5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }
    .recon-card {
        border-radius: 16px;
        background: white;
        border: 1px solid #e2e8f0;
        height: 100%;
        transition: transform 0.2s;
        overflow: hidden;
    }
    .recon-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px -10px rgba(0,0,0,0.1);
    }
    .metric-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
        border-bottom: 1px dashed #f1f5f9;
    }
    .metric-row:last-child { border-bottom: none; }
    
    @media (max-width: 768px) {
        .recon-header { flex-direction: column; align-items: flex-start !important; gap: 1rem; padding: 1rem; }
    }
</style>

<div class="container-fluid p-0">
    <!-- Modern Header -->
    <div class="recon-header d-flex align-items-center justify-content-between shadow-sm">
        <div>
            <h1 class="h4 fw-bold mb-0 text-dark">Cash Reconciliation</h1>
            <p class="text-muted small mb-0">Auditing daily revenue and bankable totals.</p>
        </div>
        <div class="d-flex gap-2">
            <form method="GET" class="d-flex gap-2">
                <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm rounded-pill px-3" onchange="this.form.submit()">
            </form>
            <a href="{{ route('reconciliation.history') }}" class="btn btn-sm btn-light border rounded-pill px-3">History</a>
        </div>
    </div>

    <div class="px-4 pb-5">
        <div class="row g-4">
            @foreach($bars as $bar)
                @php
                    $barEntry = $stockEntries->where('bar_id', $bar->id)->first();
                    $reconciliation = $barEntry?->cashReconciliation;
                    $hasProblem = $reconciliation?->hasProblem() ?? false;
                    $isVerified = $reconciliation?->isVerified() ?? false;
                @endphp
                
                <div class="col-md-6 col-lg-4">
                    <div class="recon-card shadow-sm {{ $hasProblem ? 'border-danger border-opacity-50' : ($isVerified ? 'border-success border-opacity-50' : '') }}">
                        <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-light bg-opacity-25">
                            <h6 class="fw-bold mb-0 text-dark">{{ $bar->name }}</h6>
                            @if($isVerified)
                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3">Verified</span>
                            @elseif($hasProblem)
                                <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3">Discrepancy</span>
                            @else
                                <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3">Pending</span>
                            @endif
                        </div>
                        
                        <div class="p-3">
                            @if($barEntry)
                                @php
                                    $totalSales = $barEntry->stockEntryItems->sum('sales_amount');
                                    $electronicTotal = \App\Models\DailyReportPayment::whereHas('dailyReport', function($query) use ($barEntry) {
                                        $query->where('bar_id', $barEntry->bar_id)->whereDate('date', $barEntry->date);
                                    })->sum('amount');
                                    
                                    $dailyReport = \App\Models\DailyReport::where('bar_id', $barEntry->bar_id)->where('date', $barEntry->date)->first();
                                    $collectedCash = $dailyReport?->cash_in_hand ?? 0;
                                    
                                    $expensesTotal = \App\Models\Expense::where('date', $barEntry->date)
                                                        ->whereHas('stockEntry', fn($q) => $q->where('bar_id', $barEntry->bar_id))
                                                        ->sum('amount') ?: \App\Models\Expense::where('date', $barEntry->date)->where('user_id', $barEntry->user_id)->sum('amount');
                                    
                                    $totalCollected = $collectedCash + $electronicTotal;
                                    $bankableBalance = $totalCollected - $expensesTotal;
                                @endphp

                                <div class="mb-4">
                                    <div class="metric-row">
                                        <span class="small text-muted">Stock Sales</span>
                                        <span class="fw-bold text-dark">{{ number_format($totalSales) }}</span>
                                    </div>
                                    <div class="metric-row">
                                        <span class="small text-muted">Electronic Pay</span>
                                        <span class="fw-bold text-primary">{{ number_format($electronicTotal) }}</span>
                                    </div>
                                    <div class="metric-row">
                                        <span class="small text-muted">Cash in Hand</span>
                                        <span class="fw-bold text-dark">{{ number_format($collectedCash) }}</span>
                                    </div>
                                    <div class="metric-row">
                                        <span class="small text-muted">Expenses Paid</span>
                                        <span class="fw-bold text-danger">{{ number_format($expensesTotal) }}</span>
                                    </div>
                                    <div class="metric-row border-top-0 pt-3">
                                        <span class="fw-bold text-dark">Bankable Total</span>
                                        <span class="h5 fw-bold mb-0 text-success">MWK {{ number_format($bankableBalance) }}</span>
                                    </div>
                                </div>

                                @if($reconciliation)
                                    <div class="bg-light rounded-3 p-2 mb-3">
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span class="text-muted">Cash Counted:</span>
                                            <span class="fw-bold text-dark">{{ number_format($reconciliation->cash_counted) }}</span>
                                        </div>
                                        @if($reconciliation->electronic_counted !== null)
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span class="text-muted">Electronic Counted:</span>
                                                <span class="fw-bold text-primary">{{ number_format($reconciliation->electronic_counted) }}</span>
                                            </div>
                                        @endif
                                        <div class="d-flex justify-content-between small">
                                            <span class="text-muted">Variance:</span>
                                            <span class="fw-bold {{ $reconciliation->variance < 0 ? 'text-danger' : 'text-success' }}">
                                                {{ $reconciliation->variance < 0 ? '-' : '+' }} {{ number_format(abs($reconciliation->variance)) }}
                                            </span>
                                        </div>
                                    </div>
                                @endif

                                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                    <div class="small">
                                        <div class="text-muted" style="font-size: 0.65rem;">SUBMITTED BY</div>
                                        <div class="fw-bold text-dark">{{ $barEntry->user->name }}</div>
                                    </div>
                                    @if(!$isVerified)
                                        <a href="{{ route('reconciliation.verify', $barEntry->id) }}" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">Verify Cash</a>
                                    @else
                                        <div class="text-end">
                                            <div class="text-muted" style="font-size: 0.65rem;">VERIFIED BY</div>
                                            <div class="fw-bold text-success">{{ $reconciliation->verifier->name ?? 'System' }}</div>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="text-center py-5">
                                    <i class="bi bi-slash-circle text-muted fs-2 mb-2 d-block"></i>
                                    <p class="text-muted small mb-0">No shift data recorded.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
