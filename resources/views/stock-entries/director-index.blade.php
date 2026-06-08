@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<div class="container-fluid px-4 py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm bg-gradient-primary">
                <div class="card-body py-4">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-white bg-opacity-20 rounded-3 p-3 me-3">
                                <i class="bi bi-clipboard-data fs-3 text-white"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h1 class="h2 mb-1 text-white fw-bold">Director Stock Entries</h1>
                            <p class="text-white-50 mb-0">Manage and view all stock entries across all bars</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock Entries Table -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h3 class="h4 mb-0 fw-semibold">
                        <i class="bi bi-table me-2 text-primary"></i> All Stock Entries
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light border-bottom">
                                <tr>
                                    <th class="border-0 fw-semibold text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Date</th>
                                    <th class="border-0 fw-semibold text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Bar</th>
                                    <th class="border-0 fw-semibold text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Seller</th>
                                    <th class="border-0 fw-semibold text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Total Sales</th>
                                    <th class="border-0 fw-semibold text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Status</th>
                                    <th class="border-0 fw-semibold text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($entries as $entry)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary bg-opacity-10 rounded-2 p-1 me-2">
                                                    <i class="bi bi-calendar3 text-primary small"></i>
                                                </div>
                                                <span>{{ $entry->date->format('M d, Y') }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-light rounded-2 p-1 me-2">
                                                    <i class="bi bi-shop small"></i>
                                                </div>
                                                <span class="fw-semibold">{{ $entry->bar->name }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-success bg-opacity-10 rounded-2 p-1 me-2">
                                                    <i class="bi bi-person text-success small"></i>
                                                </div>
                                                <span>{{ $entry->user->name }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-success">{{ number_format($entry->stockEntryItems->sum('sales_amount')) }}</span>
                                        </td>
                                        <td>
                                            @if($entry->reconciliation)
                                                <span class="badge bg-success bg-opacity-10 text-success">
                                                    <i class="bi bi-check-circle me-1"></i> Verified
                                                </span>
                                            @else
                                                <span class="badge bg-warning bg-opacity-10 text-warning">
                                                    <i class="bi bi-clock me-1"></i> Pending
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="{{ route('stock-entries.show', $entry) }}" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="bi bi-eye me-1"></i> View
                                                </a>
                                                <a href="{{ route('stock-entries.edit', $entry) }}" 
                                                   class="btn btn-sm btn-secondary">
                                                    <i class="bi bi-pencil me-1"></i> Edit
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <div class="text-muted">
                                                <div class="bg-light rounded-3 p-3 d-inline-flex mb-2">
                                                    <i class="bi bi-inbox fs-4 text-muted"></i>
                                                </div>
                                                <div class="small">No stock entries found</div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="d-flex justify-content-center mt-4">
                        {{ $entries->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted">
                            <i class="bi bi-clipboard-data me-1"></i> Showing all stock entries
                        </div>
                        
                        <div class="d-flex gap-2">
                            <a href="{{ route('director-stock-entries.create') }}" 
                               class="btn btn-success">
                                <i class="bi bi-plus-circle me-1"></i> New Director Entry
                            </a>
                            
                            <a href="{{ route('manager.dashboard') }}" 
                               class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modern CSS Styles -->
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
    --purple: #9333ea;
    --indigo: #6366f1;
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

.rounded-3 {
    border-radius: 0.75rem;
}

.p-1 {
    padding: 0.25rem;
}

.p-2 {
    padding: 0.5rem;
}

.p-3 {
    padding: 0.75rem;
}

.me-1 {
    margin-right: 0.25rem;
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

.text-white-50 {
    color: rgba(255, 255, 255, 0.75) !important;
}

/* ================================
   COLORS
================================ */

.text-purple {
    color: var(--purple) !important;
}

.bg-purple {
    background-color: var(--purple) !important;
}

.bg-purple.bg-opacity-10 {
    background-color: rgba(147, 51, 234, 0.1) !important;
}

.bg-purple.bg-opacity-25 {
    background-color: rgba(147, 51, 234, 0.25) !important;
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
</style>
@endsection
