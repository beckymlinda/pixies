@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<div class="container-fluid px-4 py-4">
    <!-- Success Messages -->
    @if(session('success'))
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-check-circle-fill me-3 fs-5"></i>
                        </div>
                        <div class="flex-grow-1">
                            {{ session('success') }}
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Error Messages -->
    @if(session('error'))
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-exclamation-triangle-fill me-3 fs-5"></i>
                        </div>
                        <div class="flex-grow-1">
                            {{ session('error') }}
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm bg-gradient-primary">
                <div class="card-body py-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0">
                                    <div class="bg-white bg-opacity-20 rounded-3 p-3 me-3">
                                        <i class="bi bi-graph-up-arrow fs-3 text-white"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h1 class="h2 mb-1 text-white fw-bold">Daily Reports</h1>
                                    <p class="text-white-50 mb-0">
                                        @if(auth()->user()->isSeller())
                                            Your daily cash and payment reports
                                        @else
                                            View and manage daily cash reports across all locations
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <span class="badge bg-white bg-opacity-25 text-white px-3 py-2">
                                    <i class="bi bi-calendar3 me-1"></i> {{ now()->format('F Y') }}
                                </span>
                                <span class="badge bg-white bg-opacity-25 text-white px-3 py-2">
                                    <i class="bi bi-building me-1"></i> {{ $reports->count() }} Reports
                                </span>
                            </div>
                        </div>
                        @if(auth()->user()->isSeller() || auth()->user()->isManager() || auth()->user()->isDirector())
                            <div class="flex-shrink-0">
                                <a href="{{ route('reporting.create') }}" 
                                   class="btn btn-light btn-lg shadow-sm hover-lift">
                                    <i class="bi bi-plus-circle me-2"></i> New Report
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reports List -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h3 class="h4 mb-1 fw-semibold">
                                <i class="bi bi-table me-2 text-primary"></i> Report History
                            </h3>
                            <small class="text-muted">Daily cash and payment reports across all locations</small>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-filter me-1"></i> Filter
                            </button>
                            <button class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-download me-1"></i> Export
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    @if($reports->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light border-bottom">
                                    <tr>
                                        <th class="border-0 fw-semibold text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Date</th>
                                        <th class="border-0 fw-semibold text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Location</th>
                                        @if(!auth()->user()->isSeller())
                                            <th class="border-0 fw-semibold text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">User</th>
                                        @endif
                                        <th class="border-0 fw-semibold text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Cash</th>
                                        <th class="border-0 fw-semibold text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Sales</th>
                                        <th class="border-0 fw-semibold text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Mobile</th>
                                        <th class="border-0 fw-semibold text-muted text-uppercase text-end" style="font-size: 0.75rem; letter-spacing: 0.5px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($reports as $report)
                                        <tr>
                                            <td data-label="Date">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-primary bg-gradient rounded-2 p-2 me-3">
                                                        <div class="fs-6 text-white"><i class="bi bi-calendar3"></i></div>
                                                    </div>
                                                    <div>
                                                        <div class="fw-semibold text-dark">
                                                            {{ $report->date->format('M d, Y') }}
                                                        </div>
                                                        <small class="text-muted">
                                                            {{ $report->date->format('l') }}
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td data-label="Location">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-light bg-gradient rounded-2 p-2 me-2">
                                                        <i class="bi bi-shop text-primary"></i>
                                                    </div>
                                                    <span class="fw-medium">{{ $report->bar->name }}</span>
                                                </div>
                                            </td>
                                            @if(!auth()->user()->isSeller())
                                                <td data-label="User">
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-light bg-gradient rounded-circle p-2 me-2" style="width: 36px; height: 36px;">
                                                            <span class="fw-semibold text-primary small">{{ strtoupper(substr($report->user->name, 0, 1)) }}</span>
                                                        </div>
                                                        <div>
                                                            <div class="fw-medium">{{ $report->user->name }}</div>
                                                            <small class="text-muted text-capitalize">{{ $report->user->role }}</small>
                                                        </div>
                                                    </div>
                                                </td>
                                            @endif
                                            <td data-label="Cash">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-success bg-opacity-10 rounded-2 p-2 me-2">
                                                        <i class="bi bi-cash-stack text-success"></i>
                                                    </div>
                                                    <span class="fw-semibold text-success">{{ number_format($report->cash_in_hand, 0) }}</span>
                                                </div>
                                            </td>
                                            <td data-label="Sales">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-primary bg-opacity-10 rounded-2 p-2 me-2">
                                                        <i class="bi bi-graph-up text-primary"></i>
                                                    </div>
                                                    <span class="fw-semibold text-primary">{{ number_format($report->real_time_sales, 0) }}</span>
                                                </div>
                                            </td>
                                            <td data-label="Mobile">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-warning bg-opacity-10 rounded-2 p-2 me-2">
                                                        <i class="bi bi-phone text-warning"></i>
                                                    </div>
                                                    <span class="fw-semibold text-warning">{{ number_format($report->total_payments, 0) }}</span>
                                                </div>
                                            </td>
                                            <td data-label="Actions" class="text-end">
                                                <div class="d-flex gap-1 justify-content-end">
                                                    <a href="{{ route('reporting.show', $report) }}" 
                                                       class="btn btn-sm btn-outline-primary btn-icon" 
                                                       title="View Report">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    @if((auth()->user()->isSeller() && $report->user_id === auth()->id() && $report->date->format('Y-m-d') === now()->format('Y-m-d')) || auth()->user()->isManager() || auth()->user()->isDirector())
                                                        <a href="{{ route('reporting.edit', $report) }}" 
                                                           class="btn btn-sm btn-outline-secondary btn-icon"
                                                           title="Edit Report">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <form method="POST" action="{{ route('reporting.destroy', $report) }}" 
                                                              style="display: inline;"
                                                              onsubmit="return confirm('Are you sure you want to delete this report?')">
                                                            @csrf
                                                            <input type="hidden" name="_method" value="DELETE">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger btn-icon" 
                                                                    title="Delete Report">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="d-flex justify-content-center mt-4">
                            {{ $reports->links() }}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <div class="mb-4">
                                <div class="bg-light bg-gradient rounded-circle d-inline-flex p-4">
                                    <i class="bi bi-clipboard-data fs-1 text-primary"></i>
                                </div>
                            </div>
                            <h3 class="h4 mb-3 fw-semibold text-dark">No Reports Yet</h3>
                            <p class="text-muted mb-4">Start by creating your first daily cash report to track your financial data.</p>
                            @if(auth()->user()->isSeller() || auth()->user()->isManager() || auth()->user()->isDirector())
                                <a href="{{ route('reporting.create') }}" 
                                   class="btn btn-primary btn-lg shadow-sm hover-lift">
                                    <i class="bi bi-plus-circle me-2"></i> Create Your First Report
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ======================================
   MODERN BOOTSTRAP REACT-LIKE STYLES
================================ */

:root {
    --primary: #2563eb;
    --primary-dark: #1e40af;
    --primary-light: #3b82f6;
    --warning: #f59e0b;
    --danger: #dc2626;
    --success: #16a34a;
    --bg: #f8fafc;
    --card: #ffffff;
    --border: #e2e8f0;
    --text: #1e293b;
    --muted: #64748b;
    --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
}

/* Page background */
body {
    background: var(--bg);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.875rem;
    line-height: 1.5;
    color: var(--text);
}

/* ================================
   GRADIENT BACKGROUNDS
================================ */

.bg-gradient-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%) !important;
}

.bg-light.bg-gradient {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%) !important;
}

.bg-success.bg-gradient {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
}

.bg-primary.bg-gradient {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%) !important;
}

/* ================================
   CARDS
================================ */

.card {
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    transition: all 0.15s ease-in-out;
    box-shadow: var(--shadow);
}

.card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-1px);
}

.card-header {
    border-bottom: 1px solid var(--border);
    background-color: #ffffff;
}

/* ================================
   BUTTONS
================================ */

.btn {
    border-radius: 0.5rem;
    font-weight: 500;
    transition: all 0.15s ease-in-out;
    border: 1px solid transparent;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    line-height: 1.5rem;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.375rem;
}

.btn-lg {
    padding: 0.75rem 1.5rem;
    font-size: 0.875rem;
    line-height: 1.25rem;
}

.btn-light {
    background-color: #ffffff;
    color: var(--text);
    border-color: var(--border);
}

.btn-light:hover {
    background-color: var(--bg);
    border-color: var(--border);
}

.hover-lift:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* ================================
   TABLES
================================ */

.table {
    margin-bottom: 0;
}

.table thead th {
    border-bottom: 1px solid var(--border);
    font-weight: 600;
    color: var(--muted);
    background-color: #f8fafc;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.table tbody tr {
    transition: all 0.15s ease-in-out;
}

.table tbody tr:hover {
    background-color: #f8fafc;
}

.table td {
    vertical-align: middle;
    border-bottom: 1px solid var(--border);
    padding: 1rem;
}

/* ================================
   AVATARS
================================ */

.user-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: var(--primary);
    color: white;
    font-weight: 600;
    font-size: 0.875rem;
}

/* ================================
   ALERTS
================================ */

.alert {
    border: none;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1rem;
    box-shadow: var(--shadow);
}

.alert-success {
    background-color: #f0fdf4;
    color: #166534;
}

.alert-danger {
    background-color: #fef2f2;
    color: #dc2626;
}

/* ================================
   BADGES
================================ */

.badge {
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    padding: 0.25rem 0.75rem;
}

/* ================================
   ICONS
================================ */

.rounded-2 {
    border-radius: 0.5rem;
}

.rounded-circle {
    border-radius: 50%;
}

.p-2 {
    padding: 0.5rem;
}

.me-2 {
    margin-right: 0.5rem;
}

.me-3 {
    margin-right: 0.75rem;
}

/* ================================
   TYPOGRAPHY
================================ */

.fw-semibold {
    font-weight: 600;
}

.fw-medium {
    font-weight: 500;
}

.text-uppercase {
    text-transform: uppercase;
}

.text-capitalize {
    text-transform: capitalize;
}

.text-white-50 {
    color: rgba(255, 255, 255, 0.75) !important;
}

/* ================================
   RESPONSIVE
================================ */

@media (max-width: 768px) {
    .container-fluid {
        padding: 12px !important;
    }

    .table-responsive {
        overflow-x: visible;
    }

    table, thead, tbody, tr {
        display: block;
        width: 100%;
    }

    thead {
        display: none;
    }

    tbody tr {
        background: #fff;
        margin-bottom: 16px;
        border-radius: 12px;
        padding: 16px;
        box-shadow: var(--shadow);
        border: 1px solid var(--border);
    }

    tbody tr td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border: none;
        font-size: 14px;
    }

    tbody tr td::before {
        content: attr(data-label);
        font-weight: 600;
        color: var(--muted);
        flex-shrink: 0;
        margin-right: 12px;
    }

    .btn-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
        width: 100%;
    }

    .btn-group .btn {
        width: 100%;
    }

    .d-flex.align-items-center {
        flex-direction: column;
        align-items: flex-start !important;
    }

    .d-flex.align-items-center > div {
        width: 100%;
    }
}

/* ================================
   ANIMATIONS
================================ */

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card {
    animation: fadeIn 0.25s ease;
}

/* ================================
   FLEX UTILITIES
================================ */

.flex-shrink-0 {
    flex-shrink: 0;
}

.flex-grow-1 {
    flex-grow: 1;
}

.gap-1 {
    gap: 0.25rem;
}

.gap-2 {
    gap: 0.5rem;
}

.gap-3 {
    gap: 0.75rem;
}

/* ================================
   MOBILE OPTIMIZATION
================================ */

@media (max-width: 768px) {
    .container-fluid {
        padding: 12px !important;
    }

    /* Convert table to cards on mobile */
    .table-responsive {
        overflow-x: visible;
    }

    table, thead, tbody, tr {
        display: block;
        width: 100%;
    }

    thead {
        display: none;
    }

    tbody tr {
        background: #fff;
        margin-bottom: 16px;
        border-radius: 12px;
        padding: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border: 1px solid #e5e7eb;
    }

    tbody tr td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border: none;
        font-size: 14px;
    }

    tbody tr td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #6b7280;
        flex-shrink: 0;
        margin-right: 12px;
    }

    /* Hide date icon on mobile */
    .bg-primary.bg-gradient {
        display: none;
    }

    /* Adjust mobile layout */
    .d-flex.align-items-center {
        flex-direction: column;
        align-items: flex-start !important;
    }

    .d-flex.align-items-center > div {
        width: 100%;
    }

    /* Buttons full width on mobile */
    .btn-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
        width: 100%;
    }

    .btn-group .btn {
        width: 100%;
    }

    /* Reduce padding */
    h1 {
        font-size: 20px;
    }

    /* Badge adjustments */
    .badge {
        width: 100%;
        text-align: center;
        padding: 8px 12px;
    }

    /* Empty state adjustments */
    .text-center.py-5 {
        padding: 2rem 1rem !important;
    }

    .display-1 {
        font-size: 3rem;
    }
}

@media (min-width: 769px) {
    /* Add data-labels for desktop (hidden) */
    td::before {
        display: none;
    }
}

/* ================================
   ANIMATIONS
================================ */

.card {
    animation: fadeIn 0.25s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
@endsection
