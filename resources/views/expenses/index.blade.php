@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .expenses-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: -1.5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }
    .expense-card {
        border-radius: 16px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: white;
        overflow: hidden;
    }
    .expense-table th {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        background: #f8fafc;
        padding: 14px 20px !important;
        border-top: none !important;
    }
    .expense-table td {
        padding: 16px 20px !important;
        vertical-align: middle !important;
        font-size: 0.9rem;
        color: #1e293b;
    }

    @media (max-width: 768px) {
        .expenses-header { flex-direction: column; align-items: flex-start !important; gap: 1rem; padding: 1rem; }
        .expense-table thead { display: none; }
        .expense-table tr { display: block; border-bottom: 1px solid #e2e8f0; padding: 15px; }
        .expense-table td { display: flex; justify-content: space-between; align-items: center; border: none !important; padding: 8px 0 !important; width: 100%; }
        .expense-table td::before { content: attr(data-label); font-weight: 700; font-size: 0.75rem; color: #64748b; text-transform: uppercase; }
    }
</style>

<div class="container-fluid p-0">
    <!-- Modern Header -->
    <div class="expenses-header d-flex align-items-center justify-content-between shadow-sm">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark">Operational Expenses</h1>
            <p class="text-muted small mb-0">Track daily spending, debts, and petty cash for your bar operations.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('expenses.create') }}" class="btn btn-primary rounded-pill px-4 shadow-sm">
                <i class="bi bi-plus-lg me-2"></i>Record Expense
            </a>
        </div>
    </div>

    <div class="px-4">
        <div class="card expense-card shadow-sm border-0 bg-white">
            <div class="table-responsive">
                <table class="table expense-table mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Total Amount</th>
                            <th>Transactions</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($expensesByDate as $dayExpenses)
                            <tr>
                                <td data-label="Date">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light rounded-3 p-2 me-3 d-none d-md-block">
                                            <i class="bi bi-calendar-check text-secondary"></i>
                                        </div>
                                        <div>
                                            @if($dayExpenses->date)
                                                <div class="fw-bold text-dark">{{ \Carbon\Carbon::parse($dayExpenses->date)->format('M d, Y') }}</div>
                                                <div class="small text-muted">{{ \Carbon\Carbon::parse($dayExpenses->date)->format('l') }}</div>
                                            @else
                                                <div class="fw-bold text-muted">No Date Set</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Total">
                                    <div class="fw-bold text-dark">MWK {{ number_format($dayExpenses->total_amount) }}</div>
                                </td>
                                <td data-label="Count">
                                    <span class="badge bg-light text-dark border border-secondary border-opacity-10 px-3 py-2 rounded-pill">
                                        {{ $dayExpenses->count }} entries
                                    </span>
                                </td>
                                <td data-label="Action" class="text-end">
                                    @if($dayExpenses->date)
                                        <a href="{{ route('expenses.daily', $dayExpenses->date) }}" class="btn btn-sm btn-light rounded-pill px-3 shadow-sm border bg-white">
                                            <i class="bi bi-eye me-1"></i>View Details
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-5">
                                    <div class="opacity-25 display-4 mb-3">🧾</div>
                                    <p class="text-muted">No expenses recorded yet. Track your spending to stay organized.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            @if($expensesByDate->hasPages())
                <div class="card-footer bg-white border-top p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="small text-muted">
                        Showing page {{ $expensesByDate->currentPage() }} of {{ $expensesByDate->lastPage() }}
                    </div>
                    <div class="shadow-sm bg-white">
                        {{ $expensesByDate->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
