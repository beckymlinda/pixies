@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .order-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: -1.5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1rem 2rem;
        margin-bottom: 1.5rem;
    }
    .badge-pill-custom {
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.75rem;
    }
    .status-badge {
        padding: 0.4rem 0.8rem;
        font-weight: 600;
        border-radius: 50px;
    }
    .new-badge {
        animation: pulse-glowing 2s infinite;
        font-size: 0.65rem;
        text-transform: uppercase;
        font-weight: bold;
    }
    @keyframes pulse-glowing {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(59, 130, 246, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
    }
</style>

<div class="container-fluid p-0">
    <!-- Header -->
    <div class="order-header d-flex align-items-center justify-content-between shadow-sm flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <h1 class="h4 fw-bold mb-0 text-dark">Request History</h1>
            <div class="vr mx-2 d-none d-md-block"></div>
            <span class="badge bg-light text-dark border border-secondary border-opacity-20 badge-pill-custom">📋 Stock Requests</span>
        </div>
        <div>
            <a href="{{ route('seller.orders.create') }}" class="btn btn-primary rounded-pill px-4 fw-bold">
                <i class="bi bi-plus-lg me-1"></i> New Request
            </a>
        </div>
    </div>

    <div class="px-4">
        @if (session('success') || session('error'))
            <div class="mb-4">
                @if (session('success'))
                    <div class="alert alert-success border-0 shadow-sm rounded-3" role="alert">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger border-0 shadow-sm rounded-3" role="alert">
                        {{ session('error') }}
                    </div>
                @endif
            </div>
        @endif

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-list-task me-2 text-primary"></i>Past Stock Requests</h5>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light text-uppercase font-semibold text-muted" style="font-size: 0.75rem;">
                        <tr>
                            <th class="ps-4">Request Date</th>
                            <th>Status</th>
                            <th>Items Requested</th>
                            <th>Requested By</th>
                            <th>Notes</th>
                            <th class="pe-4 text-end">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                            <tr class="{{ !$req->seller_notified && $req->status != 'pending' ? 'bg-light bg-opacity-50' : '' }}">
                                <td class="ps-4">
                                    <div class="fw-bold text-dark">{{ $req->date->format('M d, Y') }}</div>
                                    <div class="small text-muted">{{ $req->created_at->format('H:i A') }}</div>
                                </td>
                                <td>
                                    @php
                                        $badgeClass = match($req->status) {
                                            'pending' => 'bg-warning text-warning bg-opacity-10 border border-warning border-opacity-20',
                                            'approved' => 'bg-success text-success bg-opacity-10 border border-success border-opacity-20',
                                            'partially_approved' => 'bg-info text-info bg-opacity-10 border border-info border-opacity-20',
                                            'denied' => 'bg-danger text-danger bg-opacity-10 border border-danger border-opacity-20',
                                            default => 'bg-secondary text-secondary bg-opacity-10 border border-secondary border-opacity-20'
                                        };
                                        $statusText = match($req->status) {
                                            'pending' => '⏳ Pending',
                                            'approved' => '✅ Approved',
                                            'partially_approved' => 'ℹ️ Partially Approved',
                                            'denied' => '❌ Denied',
                                            default => $req->status
                                        };
                                    @endphp
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge status-badge {{ $badgeClass }}">{{ $statusText }}</span>
                                        @if(!$req->seller_notified && $req->status != 'pending')
                                            <span class="badge bg-primary new-badge">New Update</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="small fw-semibold text-dark">
                                        {{ $req->items->count() }} unique items
                                    </div>
                                    <div class="text-muted small">
                                        {{ $req->items->sum('requested_quantity') }} requested bottles
                                        @if($req->status == 'approved' || $req->status == 'partially_approved')
                                            / <span class="text-success fw-bold">{{ $req->items->sum('approved_quantity') }} approved</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="small">{{ $req->user->name }}</div>
                                </td>
                                <td>
                                    <span class="text-secondary small">{{ Str::limit($req->notes, 40) ?: '-' }}</span>
                                </td>
                                <td class="pe-4 text-end">
                                    <button class="btn btn-sm btn-light border rounded-pill px-3" data-bs-toggle="collapse" data-bs-target="#details-{{ $req->id }}">
                                        View Items <i class="bi bi-chevron-down ms-1"></i>
                                    </button>
                                </td>
                            </tr>
                            <!-- Details Row (Collapsible) -->
                            <tr class="collapse border-0" id="details-{{ $req->id }}" style="background: #fdfdfd;">
                                <td colspan="6" class="p-0 border-0">
                                    <div class="p-4 border-top border-bottom bg-light bg-opacity-30">
                                        <h6 class="fw-bold mb-3"><i class="bi bi-info-circle text-primary me-2"></i>Request Details</h6>
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="card border border-secondary border-opacity-10 shadow-none rounded-3 overflow-hidden">
                                                    <table class="table table-sm mb-0">
                                                        <thead class="table-light small">
                                                            <tr>
                                                                <th class="ps-3">Item</th>
                                                                <th class="text-center">Requested Qty</th>
                                                                <th class="text-center">Approved Qty</th>
                                                                <th class="pe-3 text-end">Status</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="small">
                                                            @foreach($req->items as $item)
                                                                <tr>
                                                                    <td class="ps-3 fw-bold">{{ $item->item->name }}</td>
                                                                    <td class="text-center">{{ $item->requested_quantity }}</td>
                                                                    <td class="text-center fw-bold {{ $item->approved_quantity > 0 ? 'text-success' : 'text-muted' }}">
                                                                        {{ $item->approved_quantity }}
                                                                    </td>
                                                                    <td class="pe-3 text-end">
                                                                        @if($req->status == 'pending')
                                                                            <span class="text-warning">Pending</span>
                                                                        @elseif($item->approved_quantity == $item->requested_quantity)
                                                                            <span class="text-success fw-bold">Fully Approved</span>
                                                                        @elseif($item->approved_quantity > 0)
                                                                            <span class="text-info fw-bold">Partially Approved</span>
                                                                        @else
                                                                            <span class="text-danger">Not Approved</span>
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="p-3 border rounded-3 bg-white h-100">
                                                    <div class="small fw-bold text-muted mb-1">Director's Response / Notes</div>
                                                    <div class="small">{{ $req->notes ?: 'No notes provided.' }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <div class="opacity-25 display-4 mb-3">📋</div>
                                    <p class="mb-0">You have not submitted any stock requests yet.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($requests->hasPages())
                <div class="card-footer bg-white py-3 d-flex justify-content-center">
                    {{ $requests->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
