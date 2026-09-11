@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .credit-show-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }
    .stat-card-modern {
        border-radius: 16px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: white;
        padding: 1.25rem;
        height: 100%;
        transition: transform 0.2s;
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

    @media (max-width: 768px) {
        .credit-show-header { flex-direction: column; align-items: flex-start !important; gap: 1rem; padding: 1rem; }
        .data-table thead { display: none; }
        .data-table tr { display: block; border-bottom: 1px solid #e2e8f0; padding: 15px; }
        .data-table td { display: flex; justify-content: space-between; align-items: center; border: none !important; padding: 8px 0 !important; width: 100%; }
        .data-table td::before { content: attr(data-label); font-weight: 700; font-size: 0.75rem; color: #64748b; text-transform: uppercase; }
    }
</style>

<div class="container-fluid p-0">
    <!-- Modern Header -->
    <div class="credit-show-header d-flex align-items-center justify-content-between shadow-sm">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <a href="{{ route('credit-customers.index') }}" class="btn btn-sm btn-light rounded-circle shadow-sm border bg-white">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="h4 fw-bold mb-0 text-dark">{{ $customer->name }}</h1>
                <p class="text-muted small mb-0">{{ $customer->phone ?: 'No phone number' }} • Customer History</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            @unless(auth()->user()->isSeller())
                @if($customer->total_balance > 0)
                    <a href="{{ route('credit-customers.payment', $customer->name) }}" class="btn btn-success rounded-pill px-4 shadow-sm">
                        <i class="bi bi-cash me-2"></i>Record Payment
                    </a>
                @endif
                <a href="{{ route('credit-customers.create') }}?customer={{ urlencode($customer->name) }}" class="btn btn-primary rounded-pill px-4 shadow-sm">
                    <i class="bi bi-plus-lg me-2"></i>New Entry
                </a>
            @endunless
        </div>
    </div>

    <div class="px-4 pb-5">
        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card-modern shadow-sm border-0">
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Total Credit Taken</div>
                    <div class="h4 mb-0 fw-bold text-dark">
                        <span class="small opacity-50">MWK</span> {{ number_format($customer->total_amount) }}
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card-modern shadow-sm border-0">
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Total Paid</div>
                    <div class="h4 mb-0 fw-bold text-success">
                        <span class="small opacity-50">MWK</span> {{ number_format($customer->total_paid) }}
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card-modern shadow-sm border-0">
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Current Balance</div>
                    <div class="h4 mb-0 fw-bold {{ $customer->total_balance > 0 ? 'text-danger' : 'text-success' }}">
                        <span class="small opacity-50">MWK</span> {{ number_format($customer->total_balance) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- History Table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="fw-bold mb-0 text-dark">Transaction History</h5>
            </div>
            <div class="table-responsive">
                <table class="table data-table mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-end">Credit Amount</th>
                            <th class="text-end">Paid Amount</th>
                            <th class="text-end">Row Balance</th>
                            <th class="text-center">Status</th>
                            <th>Description</th>
                            @unless(auth()->user()->isSeller())
                                <th class="text-end">Actions</th>
                            @endunless
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tabs as $tab)
                            <tr>
                                <td data-label="Date">
                                    <div class="fw-bold text-dark">{{ \Carbon\Carbon::parse($tab->date)->format('M d, Y') }}</div>
                                    <div class="small text-muted">{{ \Carbon\Carbon::parse($tab->created_at)->format('h:i A') }}</div>
                                </td>
                                <td data-label="Credit" class="text-end fw-semibold">
                                    {{ number_format($tab->amount) }}
                                </td>
                                <td data-label="Paid" class="text-end text-success">
                                    {{ number_format($tab->paid_amount) }}
                                </td>
                                <td data-label="Balance" class="text-end">
                                    <div class="fw-bold {{ $tab->balance > 0 ? 'text-danger' : 'text-success' }}">
                                        MWK {{ number_format($tab->balance) }}
                                    </div>
                                </td>
                                <td data-label="Status" class="text-center">
                                    {!! $tab->status_badge !!}
                                </td>
                                <td data-label="Note">
                                    <div class="text-muted small">{{ $tab->description ?: '—' }}</div>
                                </td>
                                @unless(auth()->user()->isSeller())
                                    <td data-label="Actions" class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            <a href="{{ route('credit-customers.edit', $tab) }}" class="btn-action shadow-sm" title="Edit">
                                                <i class="bi bi-pencil-fill"></i>
                                            </a>
                                            <form action="{{ route('credit-customers.destroy', $tab) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this record?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn-action text-danger shadow-sm" title="Delete">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                @endunless
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="opacity-25 display-4 mb-3">📜</div>
                                    <p class="text-muted">No transaction history found.</p>
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
