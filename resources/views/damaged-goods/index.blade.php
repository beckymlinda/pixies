@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .page-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin: -1.5rem -1.5rem 2rem;
        padding: 1.5rem 2rem;
    }
    .data-card {
        border-radius: 16px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: white;
        overflow: hidden;
    }
    .form-label-custom {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        font-weight: 700;
        margin-bottom: 0.35rem;
    }
    .form-control-custom {
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        padding: 0.65rem 0.9rem;
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
        padding: 14px 20px !important;
        vertical-align: middle !important;
    }
</style>

<div class="container-fluid p-0">
    <div class="page-header d-flex align-items-center justify-content-between shadow-sm flex-wrap gap-3">
        <div>
            <h1 class="h4 fw-bold mb-1 text-dark">Damaged Goods</h1>
            <p class="text-muted small mb-0">Damages recorded on the Balance page appear here.</p>
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

        @if(!auth()->user()->isSeller())
            <form method="GET" class="mb-3 d-flex gap-2 align-items-end flex-wrap">
                <div>
                    <label class="form-label-custom">Filter by bar</label>
                    <select name="bar_id" class="form-select form-control-custom" onchange="this.form.submit()">
                        <option value="">All bars</option>
                        @foreach($bars as $bar)
                            <option value="{{ $bar->id }}" {{ (string) request('bar_id') === (string) $bar->id ? 'selected' : '' }}>{{ $bar->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        @endif

        <div class="card data-card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table index-table mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Photo</th>
                            <th>Damaged Item</th>
                            <th>Bar</th>
                            <th>Recorded By</th>
                            <th class="text-end">Amount (MWK)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($damagedGoods as $entry)
                            <tr>
                                <td>{{ $entry->date->format('M d, Y') }}</td>
                                <td>
                                    @if($entry->photoUrl())
                                        <a href="{{ $entry->photoUrl() }}" target="_blank" rel="noopener">
                                            <img src="{{ $entry->photoUrl() }}" alt="Damaged goods" class="rounded border" style="width: 56px; height: 56px; object-fit: cover;">
                                        </a>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td class="fw-semibold text-dark">{{ $entry->description }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $entry->bar->name }}</span></td>
                                <td class="text-muted small">{{ $entry->user->name }}</td>
                                <td class="text-end fw-bold text-danger">{{ number_format($entry->amount, 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    No damaged goods yet. Record them on the
                                    <a href="{{ route('reporting.index') }}">Balance</a>
                                    page under Expenditure → Damages.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($damagedGoods->hasPages())
                <div class="card-footer bg-white border-top p-3">
                    {{ $damagedGoods->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
