@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .index-header {
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
        padding: 16px 20px !important;
        vertical-align: middle !important;
        font-size: 0.9rem;
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
        .index-header { flex-direction: column; align-items: flex-start !important; gap: 1rem; padding: 1rem; }
        .index-table thead { display: none; }
        .index-table tr { display: block; border-bottom: 1px solid #e2e8f0; padding: 15px; }
        .index-table td { display: flex; justify-content: space-between; align-items: center; border: none !important; padding: 8px 0 !important; width: 100%; }
        .index-table td::before { content: attr(data-label); font-weight: 700; font-size: 0.75rem; color: #64748b; text-transform: uppercase; }
        .btn-action { width: auto; height: auto; padding: 6px 12px; border-radius: 20px; }
    }
</style>

<div class="container-fluid p-0">
    <!-- Modern Header -->
    <div class="index-header d-flex align-items-center justify-content-between shadow-sm">
        <div>
            @if(auth()->user()->isSeller())
                <h1 class="h3 fw-bold mb-1 text-dark">Sell</h1>
                <p class="text-muted small mb-0">Record today's sales and track recent selling sessions.</p>
            @else
                <h1 class="h3 fw-bold mb-1 text-dark">Stock Entries</h1>
                <p class="text-muted small mb-0">Manage and track daily inventory logs for all bar locations.</p>
            @endif
        </div>
        <div class="d-flex gap-2">
            @if(auth()->user()->isSeller())
                @if($todayEntry ?? null)
                    <a href="{{ route('stock-entries.edit', $todayEntry) }}" class="btn btn-primary rounded-pill px-4 shadow-sm">
                        <i class="bi bi-play-fill me-2"></i>Continue Selling
                    </a>
                @else
                    <a href="{{ route('stock-entries.create') }}" class="btn btn-primary rounded-pill px-4 shadow-sm">
                        <i class="bi bi-plus-lg me-2"></i>Start Selling
                    </a>
                @endif
            @endif
        </div>
    </div>

    <div class="px-4">
        @if(session('success'))
            <div class="alert alert-success border-0 shadow-sm rounded-3 mb-3">{{ session('success') }}</div>
        @endif
        @if(session('info'))
            <div class="alert alert-info border-0 shadow-sm rounded-3 mb-3">{{ session('info') }}</div>
        @endif
        <div class="card data-card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table index-table mb-0">
                    <thead>
                        <tr>
                            <th>Entry Date</th>
                            <th>Location / Bar</th>
                            @if(!auth()->user()->isSeller())
                                <th>Recorded By</th>
                            @endif
                            <th>Total Revenue</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $entry)
                            <tr>
                                <td data-label="Date">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light rounded-3 p-2 me-3 d-none d-md-block">
                                            <i class="bi bi-calendar-check text-secondary"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark">{{ $entry->date->format('M d, Y') }}</div>
                                            <div class="small text-muted">{{ $entry->date->format('l') }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Location">
                                    <span class="badge bg-light text-dark border border-secondary border-opacity-10 px-3 py-2 rounded-pill">
                                        📍 {{ $entry->bar->name }}
                                    </span>
                                </td>
                                @if(!auth()->user()->isSeller())
                                    <td data-label="Seller">
                                        <div class="small fw-semibold text-dark">{{ $entry->user->name }}</div>
                                    </td>
                                @endif
                                <td data-label="Revenue">
                                    <div class="fw-bold text-primary">MWK {{ number_format($entry->stockEntryItems->sum('sales_amount')) }}</div>
                                </td>
                                <td data-label="Actions" class="text-end">
                                    <a href="{{ route('stock-entries.show', $entry) }}" class="btn-action" title="View Details">
                                        <i class="bi bi-eye-fill"></i>
                                    </a>
                                    @if(auth()->user()->isAdmin() || (auth()->user()->isSeller() && $entry->date->isToday()))
                                        <a href="{{ route('stock-entries.edit', $entry) }}" class="btn-action ms-1" title="{{ auth()->user()->isSeller() ? 'Continue Selling' : 'Edit Entry' }}">
                                            <i class="bi {{ auth()->user()->isSeller() ? 'bi-play-fill' : 'bi-pencil-fill' }}"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="opacity-25 display-4 mb-3">📂</div>
                                    <p class="text-muted">{{ auth()->user()->isSeller() ? 'No selling sessions yet. Start selling to record today\'s sales.' : 'No stock entries found. Record your first entry to get started.' }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            @if($entries->hasPages())
                <div class="card-footer bg-white border-top p-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="small text-muted">
                            Showing {{ $entries->firstItem() }} to {{ $entries->lastItem() }} of {{ $entries->total() }} entries
                        </div>
                        <div class="shadow-sm bg-white">
                            {{ $entries->links() }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection