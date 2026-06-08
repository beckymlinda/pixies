@extends('layouts.app')

@section('content')
@php $pageTitle = 'Warehouse Transfer Requests'; @endphp
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .header-section {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: -1.5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }
    .request-card {
        border-radius: 16px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: white;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }
    .request-card-header {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 1rem 1.5rem;
    }
    .request-card-body {
        padding: 1.5rem;
    }
    .item-table th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        background: #f8fafc;
        padding: 10px 15px !important;
    }
    .item-table td {
        padding: 12px 15px !important;
        vertical-align: middle !important;
        font-size: 0.9rem;
        color: #1e293b;
    }
    .status-badge {
        font-size: 0.75rem;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
    }
    .history-card {
        border-radius: 16px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: white;
        overflow: hidden;
    }

    @media (max-width: 768px) {
        .request-card-header { flex-direction: column; align-items: flex-start !important; gap: 0.5rem; }
        .item-table thead { display: none; }
        .item-table tr { display: block; border-bottom: 1px solid #e2e8f0; padding: 10px 0; }
        .item-table td { display: flex; justify-content: space-between; align-items: center; border: none !important; padding: 6px 0 !important; width: 100%; }
        .item-table td::before { content: attr(data-label); font-weight: 700; font-size: 0.75rem; color: #64748b; text-transform: uppercase; }
        .action-buttons { flex-direction: column; gap: 0.5rem; width: 100%; }
        .action-buttons button, .action-buttons a { width: 100%; }
    }
</style>

<div class="container-fluid p-0">
    <!-- Header -->
    <div class="header-section shadow-sm">
        <div class="d-flex align-items-center justify-content-between w-100 mb-3">
            <div>
                <h1 class="h3 fw-bold mb-1 text-dark">Transfer Requests</h1>
                <p class="text-muted small mb-0">Review and approve stock transfer requests from bars to the warehouse.</p>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('warehouse.index') }}" class="btn btn-outline-secondary rounded-pill px-3 py-2">
                <i class="bi bi-box-seam me-1"></i>All Items
            </a>
            <a href="{{ route('warehouse.transfer-requests') }}" class="btn btn-primary rounded-pill px-3 py-2 active">
                <i class="bi bi-arrow-left-right me-1"></i>Transfer Requests ({{ count($pendingRequests) }})
            </a>
        </div>
    </div>

    <div class="px-4">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="row">
            <!-- Pending Requests Section -->
            <div class="col-lg-8 mb-4">
                <h4 class="fw-bold mb-3 text-dark d-flex align-items-center">
                    <span class="badge bg-warning text-dark me-2">{{ count($pendingRequests) }}</span> Pending Requests
                </h4>

                @forelse($pendingRequests as $request)
                    <div class="card request-card border-0">
                        <div class="request-card-header d-flex justify-content-between align-items-center flex-wrap">
                            <div>
                                <span class="badge bg-primary text-uppercase px-3 py-2 rounded-pill fw-bold">{{ $request->bar->name }}</span>
                                <span class="text-muted ms-2 small">Requested by: <strong>{{ $request->requestedBy->name ?? 'Unknown' }}</strong></span>
                            </div>
                            <div class="text-muted small">
                                <i class="bi bi-calendar3 me-1"></i>{{ $request->requested_at->format('M d, Y h:i A') }}
                            </div>
                        </div>
                        <div class="request-card-body">
                            @if($request->notes)
                                <div class="bg-light p-3 rounded-3 mb-3 border-start border-primary border-3">
                                    <div class="small fw-bold text-muted text-uppercase mb-1">Director's Notes</div>
                                    <p class="mb-0 text-dark small">{{ $request->notes }}</p>
                                </div>
                            @endif

                            <form action="{{ route('warehouse.transfer-requests.approve', $request) }}" method="POST" id="approveForm-{{ $request->id }}">
                                @csrf
                                <div class="table-responsive">
                                    <table class="table item-table align-middle">
                                        <thead>
                                            <tr>
                                                <th>Item Name</th>
                                                <th>Requested</th>
                                                <th>Warehouse Available</th>
                                                <th style="width: 150px;">Approve Qty</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($request->items as $index => $item)
                                                @php
                                                    $warehouseStock = $item->warehouseStock;
                                                    $requestedBaseUnits = $item->quantity_requested * $item->conversion_factor;
                                                    $isOutOfStock = $warehouseStock->quantity < $requestedBaseUnits;
                                                    $maxApprove = floor($warehouseStock->quantity / $item->conversion_factor);
                                                @endphp
                                                <tr class="{{ $isOutOfStock ? 'table-danger' : '' }}">
                                                    <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                                                    <td data-label="Item Name">
                                                        <div class="fw-bold text-dark">{{ $warehouseStock->item_name }}</div>
                                                        <small class="text-muted">Unit: {{ $item->unit_name ?? 'Base' }} (x{{ $item->conversion_factor }})</small>
                                                    </td>
                                                    <td data-label="Requested">
                                                        <span class="badge bg-secondary">{{ $item->quantity_requested }} {{ $item->unit_name ?? 'units' }}</span>
                                                        <div class="text-muted small" style="font-size: 0.75rem;">({{ $requestedBaseUnits }} base units)</div>
                                                    </td>
                                                    <td data-label="Warehouse Available">
                                                        <span class="fw-bold {{ $warehouseStock->quantity <= 0 ? 'text-danger' : 'text-success' }}">
                                                            {{ $warehouseStock->quantity }} base units
                                                        </span>
                                                        <div class="text-muted small" style="font-size: 0.75rem;">(approx. {{ $maxApprove }} {{ $item->unit_name ?? 'units' }})</div>
                                                    </td>
                                                    <td data-label="Approve Qty">
                                                        <input type="number" 
                                                               name="items[{{ $index }}][quantity_approved]" 
                                                               value="{{ min($item->quantity_requested, max(0, $maxApprove)) }}" 
                                                               class="form-control form-control-sm text-center fw-bold" 
                                                               min="0" 
                                                               max="{{ $item->quantity_requested }}" 
                                                               required>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-3">
                                    <label class="form-label fw-bold text-muted small text-uppercase">Manager Approval/Rejection Notes</label>
                                    <textarea class="form-control" name="notes" rows="2" placeholder="Write any notes about approval or rejection reason..."></textarea>
                                </div>

                                <div class="mt-4 d-flex justify-content-end gap-2 action-buttons">
                                    <!-- Reject Button triggers rejection modal or submits directly -->
                                    <button type="button" 
                                            class="btn btn-outline-danger px-4 rounded-pill" 
                                            onclick="rejectRequest({{ $request->id }})">
                                        <i class="bi bi-x-circle me-1"></i>Reject Entire Request
                                    </button>
                                    <button type="submit" class="btn btn-success px-4 rounded-pill">
                                        <i class="bi bi-check-circle me-1"></i>Process Approval
                                    </button>
                                </div>
                            </form>

                            <!-- Hidden Rejection Form -->
                            <form action="{{ route('warehouse.transfer-requests.reject', $request) }}" method="POST" id="rejectForm-{{ $request->id }}" class="d-none">
                                @csrf
                                <input type="hidden" name="rejection_reason" id="rejectionReason-{{ $request->id }}">
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="card request-card border-0 p-5 text-center">
                        <div class="opacity-25 display-4 mb-3">📦</div>
                        <h5 class="text-muted fw-bold">No Pending Transfer Requests</h5>
                        <p class="text-muted mb-0 small">When the director requests stock, it will appear here for your approval.</p>
                    </div>
                @endforelse
            </div>

            <!-- Transfer History Section -->
            <div class="col-lg-4">
                <h4 class="fw-bold mb-3 text-dark">
                    <i class="bi bi-clock-history me-2 text-muted"></i>Recent History
                </h4>

                <div class="card history-card border-0 shadow-sm">
                    <div class="list-group list-group-flush">
                        @forelse($completedRequests as $history)
                            <div class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-secondary text-uppercase fw-bold" style="font-size: 0.7rem;">{{ $history->bar->name }}</span>
                                    <span class="badge {{ $history->isApproved() ? 'bg-success' : 'bg-danger' }} status-badge py-1">
                                        {{ strtoupper($history->status) }}
                                    </span>
                                </div>
                                <div class="text-dark fw-bold small">
                                    @php
                                        $itemsSummary = $history->items->map(function($item) {
                                            return ($item->warehouseStock->item_name ?? 'Unknown') . ' (' . ($item->quantity_approved > 0 ? $item->quantity_approved : $item->quantity_requested) . ' ' . ($item->unit_name ?? 'units') . ')';
                                        })->implode(', ');
                                    @endphp
                                    {{ Str::limit($itemsSummary, 60) }}
                                </div>
                                <div class="text-muted small mt-1" style="font-size: 0.75rem;">
                                    <i class="bi bi-calendar3 me-1"></i>{{ $history->approved_at ? $history->approved_at->format('M d, Y h:i A') : $history->updated_at->format('M d, Y h:i A') }}
                                </div>
                                <div class="text-muted small" style="font-size: 0.75rem;">
                                    By: {{ $history->approvedBy->name ?? 'Manager' }}
                                </div>
                                @if($history->isRejected() && $history->rejection_reason)
                                    <div class="text-danger small mt-2 bg-light p-2 rounded border-start border-danger border-2" style="font-size: 0.75rem;">
                                        <strong>Reason:</strong> {{ $history->rejection_reason }}
                                    </div>
                                @elseif($history->notes)
                                    <div class="text-dark small mt-2 bg-light p-2 rounded border-start border-success border-2" style="font-size: 0.75rem;">
                                        <strong>Notes:</strong> {{ $history->notes }}
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="p-4 text-center text-muted small">
                                No completed transfer history found.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function rejectRequest(requestId) {
        const reason = prompt("Please enter the reason for rejecting this transfer request:");
        if (reason === null) return; // cancelled
        
        if (reason.trim() === '') {
            alert("A rejection reason is required to reject a transfer request.");
            return;
        }
        
        document.getElementById('rejectionReason-' + requestId).value = reason;
        document.getElementById('rejectForm-' + requestId).submit();
    }
</script>
@endsection
