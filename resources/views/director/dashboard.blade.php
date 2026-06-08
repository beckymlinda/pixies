@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .director-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: -1.5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }
    .director-card-modern {
        border-radius: 16px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: white;
        padding: 1.5rem;
        height: 100%;
        transition: transform 0.2s;
    }
    .director-card-modern:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 20px -5px rgba(0, 0, 0, 0.1);
    }
    .payment-pill {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1rem;
        text-align: center;
    }
    
    @media (max-width: 768px) {
        .director-header { flex-direction: column; align-items: flex-start !important; gap: 1rem; padding: 1rem; }
    }
</style>

<div class="container-fluid p-0">
    <!-- Modern Header -->
    <div class="director-header d-flex align-items-center justify-content-between shadow-sm">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark">Executive Dashboard</h1>
            <p class="text-muted small mb-0">High-level overview of electronic revenue and operational metrics.</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">
                📅 {{ now()->format('l, M d, Y') }}
            </span>
        </div>
    </div>

    <div class="px-4 pb-5">
        <!-- Stock Expiry Summary -->
        <div class="row g-4 mb-5">
            <div class="col-12 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                    <div class="card-body p-4" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h3 class="h5 fw-bold mb-1 text-dark">📦 Stock Expiry</h3>
                                <p class="text-dark small mb-0">Items expiring soon across all locations.</p>
                            </div>
                            <div class="display-6 fw-bold text-dark">{{ $expiringItemsCount ?? 0 }}</div>
                        </div>
                        <div class="mt-3">
                            <a href="{{ route('stock-expiry.index') }}" class="btn btn-warning btn-sm rounded-pill">View Stock Expiry</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @php
            $today = \Carbon\Carbon::today();
            $todayBottles = \App\Models\BottleCount::whereDate('date', $today)->sum('counted');
            $barBottleCounts = \App\Models\BottleCount::whereDate('date', $today)
                ->join('bars', 'bottle_counts.bar_id', '=', 'bars.id')
                ->select('bars.name', \DB::raw('SUM(bottle_counts.counted) as total_counted'))
                ->groupBy('bars.name')
                ->orderByDesc('total_counted')
                ->get();
        @endphp

        <div class="row g-4 mb-5">
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 bg-white">
                    <div class="card-body p-4">
                        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
                            <div>
                                <h3 class="h5 fw-bold mb-1 text-dark">Castel Bottle Counts</h3>
                                <p class="text-muted small mb-0">Counted bottles recorded today across all bars.</p>
                            </div>
                            <div class="text-end">
                                <div class="small text-muted text-uppercase fw-bold mb-1">Total Counted</div>
                                <div class="h3 fw-bold text-primary">{{ number_format($todayBottles) }}</div>
                            </div>
                        </div>

                        <div class="row g-3 mt-4">
                            @forelse($barBottleCounts as $barCount)
                                <div class="col-12 col-md-4">
                                    <div class="payment-pill shadow-sm bg-white">
                                        <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">{{ $barCount->name }}</div>
                                        <div class="h4 mb-0 fw-bold text-dark">{{ number_format($barCount->total_counted) }}</div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12">
                                    <div class="payment-pill shadow-sm bg-white text-center">
                                        <div class="h5 fw-semibold text-dark mb-1">No counts recorded yet</div>
                                        <div class="text-muted small">Castel bottle counts will appear here once today's stock entries are saved.</div>
                                    </div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
