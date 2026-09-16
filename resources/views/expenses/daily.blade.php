@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .daily-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: -1.5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }
    .stat-card-modern {
        border-radius: 16px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: white;
        padding: 1.5rem;
        transition: transform 0.2s;
    }
    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        font-size: 20px;
    }
    .data-table th {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        background: #f8fafc;
        padding: 12px 16px !important;
        border-top: none !important;
    }
    .data-table td {
        padding: 16px 16px !important;
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
    .btn-action:hover {
        background: #f8fafc;
        color: var(--pixies-primary);
        border-color: var(--pixies-primary);
    }

    @media (max-width: 768px) {
        .daily-header { flex-direction: column; align-items: flex-start !important; gap: 1rem; padding: 1rem; }
        .data-table thead { display: none; }
        .data-table tr { display: block; border-bottom: 1px solid #e2e8f0; padding: 15px; }
        .data-table td { display: flex; justify-content: space-between; align-items: center; border: none !important; padding: 8px 0 !important; width: 100%; }
        .data-table td::before { content: attr(data-label); font-weight: 700; font-size: 0.75rem; color: #64748b; text-transform: uppercase; }
    }
</style>

<div class="container-fluid p-0">
    <!-- Modern Header -->
    <div class="daily-header d-flex align-items-center justify-content-between shadow-sm">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <a href="{{ route('expenses.index') }}" class="btn btn-sm btn-light rounded-circle shadow-sm border bg-white">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="h4 fw-bold mb-0 text-dark">Daily Expenses</h1>
                <p class="text-muted small mb-0">Records for {{ \Carbon\Carbon::parse($date)->format('l, M d, Y') }}</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            @if(!auth()->user()->isSeller())
                <a href="{{ route('expenses.create') }}" class="btn btn-primary rounded-pill px-4 shadow-sm">
                    <i class="bi bi-plus-lg me-2"></i>Record New
                </a>
            @endif
        </div>
    </div>

    <div class="px-4 pb-5">
        <!-- Summary Card -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card-modern shadow-sm border-0 bg-white">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">Total Spent</div>
                    <div class="h3 mb-0 fw-bold text-dark">
                        <span class="small fs-6 opacity-50">MWK</span> {{ number_format($totalAmount) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Expense Details List -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="card-header bg-white py-3 border-bottom-0">
                <h5 class="fw-bold mb-0 text-dark">Expense Breakdown</h5>
            </div>
            <div class="table-responsive">
                <table class="table data-table mb-0">
                    <thead>
                        <tr>
                            @if(!auth()->user()->isSeller())
                                <th>Recorded By</th>
                            @endif
                            <th>Category</th>
                            <th>Description</th>
                            <th>Time</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($expenses as $expense)
                            <tr>
                                @if(!auth()->user()->isSeller())
                                    <td data-label="Seller">
                                        <div class="fw-semibold text-dark">{{ $expense->user->name }}</div>
                                        <div class="small text-muted">{{ $expense->user->role }}</div>
                                    </td>
                                @endif
                                <td data-label="Category">
                                    <span class="badge bg-light text-dark border border-secondary border-opacity-10 px-3 py-2 rounded-pill">
                                        {{ \App\Models\Expense::typeLabel($expense->type) }}
                                    </span>
                                </td>
                                <td data-label="Details">
                                    <div class="text-secondary small">{{ $expense->description ?: 'No details provided' }}</div>
                                </td>
                                <td data-label="Time">
                                    <div class="text-muted small">{{ $expense->created_at->format('h:i A') }}</div>
                                </td>
                                <td data-label="Amount" class="text-end fw-bold text-dark">
                                    MWK {{ number_format($expense->amount) }}
                                </td>
                                <td data-label="Actions" class="text-end">
                                    <div class="d-flex justify-content-end align-items-center gap-1">
                                        <a href="{{ route('expenses.show', $expense) }}" class="btn-action shadow-sm" title="View">
                                            <i class="bi bi-eye-fill"></i>
                                        </a>
                                        @if(auth()->user()->isAdmin() && $expense->is_overhead)
                                            <a href="{{ route('expenses.edit', $expense) }}" class="btn-action shadow-sm" title="Edit">
                                                <i class="bi bi-pencil-fill"></i>
                                            </a>
                                            <form method="POST" action="{{ route('expenses.destroy', $expense) }}" class="d-inline" onsubmit="return confirm('Delete this expense record?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-action text-danger shadow-sm" title="Delete">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </form>
                                        @elseif(auth()->user()->isAdmin())
                                            <span class="small text-muted">Via shift report</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        @if($expenses->isEmpty())
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="opacity-25 display-4 mb-3">🧾</div>
                                    <p class="text-muted">No records for this date. Add expenditure in your shift report.</p>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
