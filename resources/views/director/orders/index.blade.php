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
    .stat-pill {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1rem;
        text-align: center;
    }
    .status-btn {
        border-radius: 50px !important;
        padding: 0.4rem 1.2rem !important;
        font-weight: 600;
        font-size: 0.8rem;
    }
    .status-btn.active {
        background-color: var(--pixies-primary) !important;
        border-color: var(--pixies-primary) !important;
        color: white !important;
    }
</style>

<div class="container-fluid p-0">
    <!-- Header -->
    <div class="director-header d-flex align-items-center justify-content-between shadow-sm">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark">Stock Request Approvals</h1>
            <p class="text-muted small mb-0">Approve, partially approve, or deny stock requests from sellers.</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">
                📅 {{ now()->format('l, M d, Y') }}
            </span>
        </div>
    </div>

    <div class="px-4 pb-5">
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

        <!-- Quick Stats -->
        @php
            $pendingCount = \App\Models\OrderRequest::where('status', 'pending')->count();
            $approvedCount = \App\Models\OrderRequest::whereIn('status', ['approved', 'partially_approved'])->count();
            $deniedCount = \App\Models\OrderRequest::where('status', 'denied')->count();
            $totalCount = \App\Models\OrderRequest::count();
        @endphp
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stat-pill shadow-sm bg-white border-warning border-opacity-25">
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Pending Requests</div>
                    <div class="h4 mb-0 fw-bold text-warning">{{ $pendingCount }}</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-pill shadow-sm bg-white border-success border-opacity-25">
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Approved/Partial</div>
                    <div class="h4 mb-0 fw-bold text-success">{{ $approvedCount }}</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-pill shadow-sm bg-white border-danger border-opacity-25">
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Denied Requests</div>
                    <div class="h4 mb-0 fw-bold text-danger">{{ $deniedCount }}</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-pill shadow-sm bg-white">
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Total Requests</div>
                    <div class="h4 mb-0 fw-bold text-dark">{{ $totalCount }}</div>
                </div>
            </div>
        </div>

        <!-- Filter buttons -->
        <div class="d-flex gap-2 mb-4 flex-wrap">
            <a href="?status=all" class="btn btn-outline-secondary status-btn {{ $statusFilter === 'all' ? 'active' : '' }}">All Requests</a>
            <a href="?status=pending" class="btn btn-outline-secondary status-btn {{ $statusFilter === 'pending' ? 'active' : '' }}">⏳ Pending ({{ $pendingCount }})</a>
            <a href="?status=approved" class="btn btn-outline-secondary status-btn {{ $statusFilter === 'approved' ? 'active' : '' }}">✅ Approved</a>
            <a href="?status=partially_approved" class="btn btn-outline-secondary status-btn {{ $statusFilter === 'partially_approved' ? 'active' : '' }}">ℹ️ Partially Approved</a>
            <a href="?status=denied" class="btn btn-outline-secondary status-btn {{ $statusFilter === 'denied' ? 'active' : '' }}">❌ Denied</a>
        </div>

        <!-- Requests list table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white py-3 border-bottom-0">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history text-primary me-2"></i>Stock Requests</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase font-semibold text-muted" style="font-size: 0.75rem;">
                        <tr>
                            <th class="ps-4">Date</th>
                            <th>Bar Location</th>
                            <th>Requested By</th>
                            <th>Total Bottles</th>
                            <th>Status</th>
                            <th class="pe-4 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark">{{ $req->date->format('M d, Y') }}</div>
                                    <div class="small text-muted">{{ $req->created_at->format('H:i A') }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border border-secondary border-opacity-10 px-3 py-2 rounded-pill fw-semibold">
                                        📍 {{ $req->bar->name }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $req->user->name }}</div>
                                    <div class="small text-muted">{{ strtoupper($req->user->role) }}</div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $req->items->sum('requested_quantity') }} Bottles</div>
                                    <div class="small text-muted">{{ $req->items->count() }} unique items</div>
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
                                            'pending' => '⏳ Pending Approval',
                                            'approved' => '✅ Approved',
                                            'partially_approved' => 'ℹ️ Partially Approved',
                                            'denied' => '❌ Denied',
                                            default => $req->status
                                        };
                                    @endphp
                                    <span class="badge px-3 py-2 rounded-pill fw-semibold {{ $badgeClass }}">{{ $statusText }}</span>
                                </td>
                                <td class="pe-4 text-end">
                                    @if($req->status === 'pending')
                                        <a href="{{ route('director.orders.show', $req) }}" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold shadow-sm">
                                            <i class="bi bi-check2-circle me-1"></i> Review &amp; Approve
                                        </a>
                                    @else
                                        <a href="{{ route('director.orders.show', $req) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-semibold">
                                            <i class="bi bi-arrow-repeat me-1"></i> Review / Toggle
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <div class="opacity-25 display-4 mb-3">📡</div>
                                    <p class="mb-0">No stock requests found matching this status.</p>
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
