@extends('layouts.app')

@section('content')
@php $pageTitle = 'Expiry Alerts'; @endphp
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .alert-header {
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
    .alert-card {
        border-radius: 12px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: white;
        padding: 1.5rem;
        margin-bottom: 1rem;
    }
    .alert-card.warning {
        border-left: 4px solid #f59e0b;
        background: #fffbeb;
    }
    .alert-card.danger {
        border-left: 4px solid #ef4444;
        background: #fef2f2;
    }
    .days-badge {
        font-size: 0.75rem;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: 600;
    }
</style>

<div class="container-fluid p-0">
    <div class="alert-header shadow-sm">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h1 class="h3 fw-bold mb-1 text-dark">Expiry Alerts</h1>
                <p class="text-muted small mb-0">Monitor items expiring soon or already expired.</p>
            </div>
            <a href="{{ route('warehouse.index') }}" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="bi bi-arrow-left me-2"></i>Back to Warehouse
            </a>
        </div>
    </div>

    <div class="px-4">
        @if($expiringItems->count() > 0)
            <div class="mb-4">
                <h4 class="fw-bold text-warning mb-3">
                    <i class="bi bi-exclamation-triangle me-2"></i>Expiring Soon ({{ $expiringItems->count() }})
                </h4>
                <div class="row">
                    @foreach($expiringItems as $item)
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="alert-card warning">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="fw-bold mb-0">{{ $item->item_name }}</h5>
                                    <span class="days-badge bg-warning text-dark">{{ $item->days_until_expiry }} days</span>
                                </div>
                                <div class="small text-muted mb-2">
                                    <i class="bi bi-calendar me-1"></i>{{ $item->expiry_date->format('M d, Y') }}
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="small text-muted">Stock: {{ $item->quantity }}</div>
                                        <div class="small text-muted">Value: MWK {{ number_format($item->quantity * $item->selling_price, 2) }}</div>
                                    </div>
                                    <a href="{{ route('warehouse.edit', $item) }}" class="btn btn-sm btn-outline-warning rounded-pill">
                                        <i class="bi bi-pencil me-1"></i>Edit
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="alert alert-success rounded-3 mb-4">
                <i class="bi bi-check-circle me-2"></i>No items expiring soon!
            </div>
        @endif

        @if($expiredItems->count() > 0)
            <div class="mb-4">
                <h4 class="fw-bold text-danger mb-3">
                    <i class="bi bi-x-circle me-2"></i>Already Expired ({{ $expiredItems->count() }})
                </h4>
                <div class="row">
                    @foreach($expiredItems as $item)
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="alert-card danger">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="fw-bold mb-0">{{ $item->item_name }}</h5>
                                    <span class="days-badge bg-danger text-white">Expired</span>
                                </div>
                                <div class="small text-muted mb-2">
                                    <i class="bi bi-calendar-x me-1"></i>{{ $item->expiry_date->format('M d, Y') }}
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="small text-muted">Stock: {{ $item->quantity }}</div>
                                        <div class="small text-muted">Value: MWK {{ number_format($item->quantity * $item->selling_price, 2) }}</div>
                                    </div>
                                    <a href="{{ route('warehouse.edit', $item) }}" class="btn btn-sm btn-outline-danger rounded-pill">
                                        <i class="bi bi-pencil me-1"></i>Edit
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="alert alert-success rounded-3 mb-4">
                <i class="bi bi-check-circle me-2"></i>No expired items!
            </div>
        @endif

        @if($expiringItems->count() === 0 && $expiredItems->count() === 0)
            <div class="text-center py-5">
                <div class="opacity-25 display-4 mb-3">✅</div>
                <p class="text-muted">All items are in good condition. No expiry alerts at this time.</p>
            </div>
        @endif
    </div>
</div>
@endsection
