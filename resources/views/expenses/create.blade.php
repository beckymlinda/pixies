@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .expense-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: -1.5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }
    .form-section-card {
        border-radius: 16px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: white;
        overflow: hidden;
    }
    .input-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #475569;
        font-weight: 700;
        margin-bottom: 0.5rem;
        display: block;
    }
    .custom-input, .custom-select, .custom-textarea {
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        padding: 0.75rem 1rem;
        width: 100%;
        transition: all 0.2s;
        background: #f8fafc;
        color: #1e293b;
    }
    .custom-input:focus, .custom-select:focus, .custom-textarea:focus {
        border-color: var(--pixies-primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(30, 41, 59, 0.1);
        outline: none;
    }
    .input-icon-group {
        position: relative;
    }
    .input-icon-group i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #64748b;
    }
    .input-icon-group .custom-input, 
    .input-icon-group .custom-select {
        padding-left: 2.75rem;
    }
    .btn-submit {
        background: var(--pixies-primary);
        color: white;
        border: none;
        border-radius: 10px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        transition: all 0.2s;
    }
    .btn-submit:hover {
        background: var(--pixies-primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    @media (max-width: 768px) {
        .expense-header { flex-direction: column; align-items: flex-start !important; gap: 1rem; padding: 1rem; }
        .form-section-card { border-radius: 0; margin-left: -1rem; margin-right: -1rem; border: none; border-bottom: 1px solid #e2e8f0; }
        .card-body { padding: 1.5rem !important; }
    }
</style>

<div class="container-fluid p-0">
    <!-- Clean Header -->
    <div class="expense-header d-flex align-items-center justify-content-between shadow-sm">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark">Record Expense</h1>
            <p class="text-muted small mb-0">Track your daily bar operational costs and petty cash.</p>
        </div>
        <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary rounded-pill px-4 btn-sm bg-white border shadow-sm">
            <i class="bi bi-arrow-left me-2"></i>Back to List
        </a>
    </div>

    <div class="px-4 pb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                @if(session('success') || session('error') || $errors->any())
                    <div class="mb-4">
                        @if(session('success'))
                            <div class="alert alert-success border-0 shadow-sm rounded-3">
                                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                            </div>
                        @endif
                        @if(session('error'))
                            <div class="alert alert-danger border-0 shadow-sm rounded-3">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
                            </div>
                        @endif
                        @if($errors->any())
                            <div class="alert alert-danger border-0 shadow-sm rounded-3">
                                <ul class="mb-0 small text-dark">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="card form-section-card shadow-sm border-0 bg-white">
                    <div class="card-body p-4 p-md-5">
                        <form method="POST" action="{{ route('expenses.store') }}">
                            @csrf
                            
                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <label class="input-label">Expense Category</label>
                                    <div class="input-icon-group">
                                        <i class="bi bi-tag-fill"></i>
                                        <select name="type" class="custom-select" required>
                                            <option value="">Select Category</option>
                                            @foreach(\App\Models\Expense::operationalTypes() as $value => $label)
                                                <option value="{{ $value }}" {{ old('type') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="input-label">Amount (MWK)</label>
                                    <div class="input-icon-group">
                                        <i class="bi bi-cash-stack"></i>
                                        <input type="number" name="amount" class="custom-input" step="0.01" min="0" placeholder="0.00" value="{{ old('amount') }}" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="input-label">Expense Date</label>
                                <div class="input-icon-group">
                                    <i class="bi bi-calendar-event-fill"></i>
                                    <input type="date" name="date" class="custom-input" value="{{ old('date', now()->format('Y-m-d')) }}" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="input-label">Details / Remarks</label>
                                <textarea name="description" class="custom-textarea" rows="4" placeholder="Describe what this expense was for...">{{ old('description') }}</textarea>
                            </div>

                            <div class="d-grid mt-5">
                                <button type="submit" class="btn-submit shadow-sm">
                                    <i class="bi bi-plus-lg me-2"></i>Record Daily Expense
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="mt-4 text-center">
                    <p class="text-muted small">
                        <i class="bi bi-shield-lock me-1"></i>
                        All expenses are logged and verified against the daily cash count.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
