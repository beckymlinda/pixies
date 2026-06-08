@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<div class="container-fluid px-4 py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card page-header">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h1 class="h2 mb-2"><i class="bi bi-eye me-2"></i> Expense Details</h1>
                            <p class="text-muted mb-0">View expense information</p>
                        </div>
                        <div class="d-flex gap-2">
                            @if(auth()->user()->isManager() || auth()->user()->isDirector() || (auth()->user()->isSeller() && $expense->user_id === auth()->id()))
                                <a href="{{ route('expenses.edit', $expense) }}" 
                                   class="btn pixies-btn-secondary">
                                    <i class="bi bi-pencil me-1"></i> Edit
                                </a>
                            @endif
                            <a href="{{ route('expenses.index') }}" 
                               class="btn pixies-btn-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Back to Expenses
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Expense Details -->
    <div class="row">
        <div class="col-12">
            <div class="card pixies-card">
                <div class="card-header bg-gradient text-white" style="background: linear-gradient(135deg, var(--warning), #d97706) !important;">
                    <h3 class="h4 mb-1">
                        <i class="bi bi-receipt me-2"></i> Expense Information
                    </h3>
                    <small class="opacity-75">Complete expense details</small>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-muted">Type</label>
                                <div>
                                    <span class="badge bg-{{ $expense->type === 'Debt' ? 'danger' : 'warning' }} px-3 py-2 fs-6">
                                        @if($expense->type === 'Debt')
                                            <i class="bi bi-cash-stack me-1"></i> Debt
                                        @else
                                            <i class="bi bi-cup-hot me-1"></i> {{ $expense->type }}
                                        @endif
                                    </span>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold text-muted">Amount</label>
                                <div class="h5 fw-bold text-{{ $expense->type === 'Debt' ? 'danger' : 'warning' }}">
                                    <i class="bi bi-cash me-1"></i>{{ number_format($expense->amount, 2) }}
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold text-muted">Date</label>
                                <div class="h5">
                                    <i class="bi bi-calendar3 me-1"></i>{{ $expense->date ? \Carbon\Carbon::parse($expense->date)->format('M d, Y') : 'Not set' }}
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-muted">Description</label>
                                <div class="h5">
                                    <i class="bi bi-text-paragraph me-1"></i>{{ $expense->description }}
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold text-muted">Created By</label>
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-2" style="width: 32px; height: 32px; font-size: 12px;">
                                        {{ strtoupper(substr($expense->user->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold">{{ $expense->user->name }}</div>
                                        <small class="text-muted">{{ $expense->user->role }}</small>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold text-muted">Created At</label>
                                <div class="h5">
                                    <i class="bi bi-clock me-1"></i>{{ $expense->created_at->format('M d, Y h:i A') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="border-top pt-4">
                        <div class="d-flex gap-2 justify-content-end">
                            @if(auth()->user()->isManager() || auth()->user()->isDirector() || (auth()->user()->isSeller() && $expense->user_id === auth()->id()))
                                <a href="{{ route('expenses.edit', $expense) }}" 
                                   class="btn pixies-btn-primary" style="background: linear-gradient(135deg, var(--warning), #d97706) !important;">
                                    <i class="bi bi-pencil me-1"></i> Edit Expense
                                </a>
                            @endif
                            <a href="{{ route('expenses.index') }}" 
                               class="btn pixies-btn-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Back to Expenses
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ======================================
   PIXIES EXPENSE SHOW UI
================================ */

:root {
    --warning: #f59e0b;
    --warning-dark: #d97706;
    --primary: #2563eb;
    --primary-dark: #1e40af;
    --success: #16a34a;
    --danger: #dc2626;
    --bg: #f5f7fb;
    --card: #ffffff;
    --border: #e5e7eb;
    --text: #1f2937;
    --muted: #6b7280;
}

/* Page background */
body {
    background: var(--bg);
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
}

/* ================================
   HEADER
================================ */

h1 {
    font-weight: 700;
    letter-spacing: -0.3px;
}

p {
    color: var(--muted);
}

/* ================================
   BUTTON
================================ */

a.bg-blue-600 {
    background: var(--primary) !important;
    border-radius: 10px;
    font-weight: 500;
    transition: all 0.2s ease;
}

a.bg-blue-600:hover {
    background: var(--primary-dark) !important;
    transform: translateY(-1px);
}

/* ================================
   CARD CONTAINER
================================ */

.bg-white.shadow {
    background: var(--card);
    border-radius: 14px;
    border: 1px solid var(--border);
    box-shadow: 0 8px 24px rgba(0,0,0,0.05);
}

/* ================================
   BADGES
================================ */

.badge {
    border-radius: 999px;
    font-size: 12px;
    font-weight: 500;
}

/* ================================
   MOBILE OPTIMIZATION
================================ */

@media (max-width: 768px) {
    .container-fluid {
        padding: 12px !important;
    }

    /* Stack columns on mobile */
    .row .col-md-6 {
        width: 100%;
        margin-bottom: 1rem;
    }

    /* Adjust field spacing */
    .mb-4 {
        margin-bottom: 1.5rem !important;
    }

    /* Make labels more prominent */
    .form-label {
        font-size: 14px;
        margin-bottom: 0.5rem;
    }

    /* Adjust field values */
    .h5 {
        font-size: 16px;
        margin-bottom: 0;
    }

    /* Badge adjustments */
    .badge {
        display: inline-block;
        width: auto;
        padding: 8px 12px;
        font-size: 13px;
    }

    /* User avatar adjustments */
    .user-avatar {
        width: 28px !important;
        height: 28px !important;
        font-size: 11px !important;
    }

    /* Stack user info */
    .d-flex.align-items-center {
        flex-direction: column;
        align-items: flex-start !important;
    }

    .d-flex.align-items-center .user-avatar {
        margin-bottom: 8px;
        margin-right: 0;
    }

    /* Reduce padding */
    h1 {
        font-size: 20px;
    }

    /* Button full width */
    .pixies-btn-primary,
    .pixies-btn-secondary {
        width: 100%;
        font-size: 16px;
        padding: 14px;
        margin-bottom: 8px;
    }

    /* Stack buttons */
    .d-flex.gap-2 {
        flex-direction: column;
        width: 100%;
    }

    .d-flex.gap-2 .btn {
        width: 100%;
    }

    /* Action buttons section */
    .border-top.pt-4 {
        padding-top: 1.5rem !important;
        margin-top: 2rem;
    }

    .d-flex.justify-content-end {
        flex-direction: column;
        align-items: stretch;
    }

    /* Card adjustments */
    .card-body {
        padding: 1.5rem 1rem;
    }

    .card-header {
        padding: 1rem;
    }

    .card-header h4 {
        font-size: 18px;
    }

    .card-header small {
        font-size: 12px;
    }
}

@media (min-width: 769px) {
    /* Ensure proper desktop layout */
    .row .col-md-6 {
        flex: 0 0 auto;
        width: 50%;
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
