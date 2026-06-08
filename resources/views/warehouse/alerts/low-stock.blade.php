@extends('layouts.app')

@section('content')
@php $pageTitle = 'Low Stock Alerts'; @endphp
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
        border-left: 4px solid #ef4444;
        background: #fef2f2;
    }
    .stock-badge {
        font-size: 0.75rem;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: 600;
    }
    .progress-bar-custom {
        height: 8px;
        border-radius: 4px;
        background: #e2e8f0;
        overflow: hidden;
    }
    .progress-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.3s ease;
    }
</style>

<div class="container-fluid p-0">
    <div class="alert-header shadow-sm">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h1 class="h3 fw-bold mb-1 text-dark">Low Stock Alerts</h1>
                <p class="text-muted small mb-0">Items that have fallen below their alert quantity threshold.</p>
            </div>
            <a href="{{ route('warehouse.index') }}" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="bi bi-arrow-left me-2"></i>Back to Warehouse
            </a>
        </div>
    </div>

    <div class="px-4">
        @if($lowStockItems->count() > 0)
            <div class="alert alert-warning rounded-3 mb-4">
                <i class="bi bi-exclamation-triangle me-2"></i>{{ $lowStockItems->count() }} item(s) need restocking
            </div>

            <div class="row">
                @foreach($lowStockItems as $item)
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="alert-card">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="fw-bold mb-0">{{ $item->item_name }}</h5>
                                <span class="stock-badge bg-danger text-white">{{ $item->quantity }} left</span>
                            </div>
                            
                            <div class="mb-2">
                                <div class="d-flex justify-content-between small text-muted mb-1">
                                    <span>Stock Level</span>
                                    <span>Alert at: {{ $item->alert_quantity }}</span>
                                </div>
                                <div class="progress-bar-custom">
                                    @php
                                        $percentage = min(100, ($item->quantity / max(1, $item->alert_quantity * 2)) * 100);
                                        $color = $percentage < 25 ? 'bg-danger' : ($percentage < 50 ? 'bg-warning' : 'bg-success');
                                    @endphp
                                    <div class="progress-fill {{ $color }}" style="width: {{ $percentage }}%"></div>
                                </div>
                            </div>

                            <div class="small text-muted mb-2">
                                <div><i class="bi bi-currency-dollar me-1"></i>Cost: MWK {{ number_format($item->purchase_price, 2) }}</div>
                                <div><i class="bi bi-tag me-1"></i>Selling: MWK {{ number_format($item->selling_price, 2) }}</div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <div class="small text-muted">
                                    Restock needed: <strong>{{ max(0, $item->alert_quantity * 2 - $item->quantity) }}</strong>
                                </div>
                                <a href="{{ route('warehouse.edit', $item) }}" class="btn btn-sm btn-outline-danger rounded-pill">
                                    <i class="bi bi-pencil me-1"></i>Edit
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-5">
                <div class="opacity-25 display-4 mb-3">✅</div>
                <div class="alert alert-success rounded-3 d-inline-block">
                    <i class="bi bi-check-circle me-2"></i>All stock levels are healthy!
                </div>
                <p class="text-muted mt-3">No items are below their alert quantity threshold.</p>
            </div>
        @endif
    </div>
</div>
@endsection
