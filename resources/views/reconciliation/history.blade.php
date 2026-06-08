@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .history-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: -1.5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }
    .stat-pill {
        background: white;
        border-radius: 12px;
        padding: 1.25rem;
        border: 1px solid #e2e8f0;
        height: 100%;
        text-align: center;
    }
    .history-table th {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        background: #f8fafc;
        padding: 12px 16px !important;
    }
    .history-table td {
        padding: 16px !important;
        vertical-align: middle !important;
        font-size: 0.875rem;
        color: #1e293b;
    }

    @media (max-width: 768px) {
        .history-header { flex-direction: column; align-items: flex-start !important; gap: 1rem; padding: 1rem; }
        .history-table thead { display: none; }
        .history-table tr { display: block; border-bottom: 2px solid #e2e8f0; padding: 15px; background: white; margin-bottom: 10px; border-radius: 12px; }
        .history-table td { display: flex; justify-content: space-between; align-items: center; border: none !important; padding: 8px 0 !important; width: 100%; }
        .history-table td::before { content: attr(data-label); font-weight: 700; font-size: 0.75rem; color: #64748b; text-transform: uppercase; }
    }
</style>

<div class="container-fluid p-0">
    <!-- Modern Header -->
    <div class="history-header d-flex align-items-center justify-content-between shadow-sm">
        <div>
            <h1 class="h4 fw-bold mb-0 text-dark">Audit Logs & History</h1>
            <p class="text-muted small mb-0">Complete record of cash reconciliations and variances.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <form method="GET" class="d-flex gap-2 align-items-center">
                <input type="date" name="start_date" value="{{ $startDate }}" class="form-control form-control-sm rounded-pill px-3">
                <span class="text-muted small">to</span>
                <input type="date" name="end_date" value="{{ $endDate }}" class="form-control form-control-sm rounded-pill px-3">
                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm">Filter</button>
            </form>
        </div>
    </div>

    <div class="px-4 pb-5">
        <!-- Stats Summary -->
        <div class="row g-3 mb-4">
            <div class="col-md">
                <div class="stat-pill shadow-sm">
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Total Audits</div>
                    <div class="h4 mb-0 fw-bold text-dark">{{ $summary['total'] }}</div>
                </div>
            </div>
            <div class="col-md">
                <div class="stat-pill shadow-sm border-success border-opacity-25">
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Matched</div>
                    <div class="h4 mb-0 fw-bold text-success">{{ $summary['matched'] }}</div>
                </div>
            </div>
            <div class="col-md">
                <div class="stat-pill shadow-sm border-danger border-opacity-25">
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Shortages</div>
                    <div class="h4 mb-0 fw-bold text-danger">{{ $summary['shortages'] }}</div>
                </div>
            </div>
            <div class="col-md">
                <div class="stat-pill shadow-sm bg-dark text-white border-0">
                    <div class="small text-uppercase fw-bold mb-1 opacity-50" style="font-size: 0.6rem;">Net Loss (Audited)</div>
                    <div class="h4 mb-0 fw-bold">MWK {{ number_format(abs($summary['total_shortage_amount'])) }}</div>
                </div>
            </div>
        </div>

        <!-- History Table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="table-responsive">
                <table class="table history-table mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Audit Date</th>
                            <th>Location</th>
                            <th>Seller</th>
                            <th class="text-end">Expected Bankable</th>
                            <th class="text-end">Counted</th>
                            <th class="text-center">Variance</th>
                            <th class="text-center">Status</th>
                            <th>Verified By</th>
                            <th class="pe-4">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reconciliations as $recon)
                            <tr class="{{ $recon->hasProblem() ? 'bg-danger bg-opacity-10' : '' }}">
                                <td data-label="Date" class="ps-4">
                                    <div class="fw-bold text-dark">{{ $recon->stockEntry->date->format('M d, Y') }}</div>
                                </td>
                                <td data-label="Bar">
                                    <div class="text-dark">{{ $recon->stockEntry->bar->name }}</div>
                                </td>
                                <td data-label="Seller">
                                    <div class="text-muted small">{{ $recon->stockEntry->user->name }}</div>
                                </td>
                                <td data-label="Expected Bankable" class="text-end fw-bold">
                                    {{ number_format($recon->expected_cash) }}
                                </td>
                                <td data-label="Bankable Counted" class="text-end fw-bold">
                                    {{ number_format($recon->bankable_counted) }}
                                </td>
                                @php
                                    if ($recon->variance > 0) {
                                        $cause = 'Excess from total/sales';
                                    } elseif ($recon->variance < 0) {
                                        $cause = 'Shortage from total/sales';
                                    } else {
                                        $cause = 'Balanced';
                                    }
                                @endphp
                                <td data-label="Variance" class="text-center fw-bold {{ $recon->variance < 0 ? 'text-danger' : ($recon->variance > 0 ? 'text-warning' : 'text-success') }}">
                                    {{ $recon->variance < 0 ? '-' : '+' }}{{ number_format(abs($recon->variance)) }}
                                    <div class="small text-muted mt-1">{{ $cause }}</div>
                                </td>
                                <td data-label="Status" class="text-center">
                                    <span class="badge rounded-pill px-3 {{ $recon->status === 'matched' ? 'bg-success bg-opacity-10 text-success' : ($recon->status === 'shortage' ? 'bg-danger bg-opacity-10 text-danger' : 'bg-warning bg-opacity-10 text-warning') }}">
                                        {{ strtoupper($recon->status) }}
                                    </span>
                                </td>
                                <td data-label="Verifier">
                                    <div class="small text-muted">{{ $recon->verifier->name ?? '—' }}</div>
                                </td>
                                <td data-label="Notes" class="pe-4">
                                    <div class="small text-muted">{{ $recon->notes ?: '—' }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <div class="opacity-25 display-4 mb-3">📂</div>
                                    <p class="text-muted">No audit logs found for this period.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-center mt-4">
            {{ $reconciliations->links() }}
        </div>
    </div>
</div>
@endsection
