@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .edit-expense-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: -1.5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }
    .form-card-modern {
        border-radius: 16px;
        background: white;
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    .input-group-text-modern {
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        color: #475569;
        font-weight: 600;
    }
    .form-control-modern {
        border: 1px solid #cbd5e1;
        padding: 0.75rem 1rem;
        border-radius: 8px;
    }
    .form-control-modern:focus {
        border-color: var(--pixies-primary);
        box-shadow: 0 0 0 3px rgba(30, 41, 59, 0.1);
    }
    
    @media (max-width: 768px) {
        .edit-expense-header { flex-direction: column; align-items: flex-start !important; gap: 1rem; padding: 1rem; }
    }
</style>

<div class="container-fluid p-0">
    <!-- Modern Header -->
    <div class="edit-expense-header d-flex align-items-center justify-content-between shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('expenses.index') }}" class="btn btn-sm btn-light rounded-circle border shadow-sm bg-white">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="h4 fw-bold mb-0 text-dark">Edit Expense</h1>
                <p class="text-muted small mb-0">Updating records for <strong>{{ $expense->type }}</strong> on {{ \Carbon\Carbon::parse($expense->date)->format('M d, Y') }}</p>
            </div>
        </div>
    </div>

    <div class="px-4 pb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="form-card-modern shadow-sm">
                    <div class="p-4 border-bottom bg-light bg-opacity-50">
                        <h5 class="fw-bold mb-0 text-dark">Expense Information</h5>
                    </div>
                    <div class="p-4">
                        <form method="POST" action="{{ route('expenses.update', $expense) }}" class="needs-validation" novalidate>
                            @csrf
                            @method('PUT')
                            
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Expense Type</label>
                                    <select name="type" class="form-select form-control-modern" required>
                                        <option value="">Select Type</option>
                                        @foreach(\App\Models\Expense::operationalTypes() as $value => $label)
                                            <option value="{{ $value }}" {{ $expense->type === $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text-modern px-3">MWK</span>
                                        <input type="number" name="amount" class="form-control form-control-modern fw-bold text-dark fs-5" 
                                               value="{{ $expense->amount }}" step="1" required>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold text-dark">Description</label>
                                    <textarea name="description" class="form-control form-control-modern" rows="3" 
                                              placeholder="Provide details about this expense...">{{ $expense->description }}</textarea>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold text-dark">Transaction Date</label>
                                    <input type="date" name="date" class="form-control form-control-modern" 
                                           value="{{ \Carbon\Carbon::parse($expense->date)->format('Y-m-d') }}" required>
                                </div>

                                <div class="col-12 text-end pt-3">
                                    <a href="{{ route('expenses.index') }}" class="btn btn-light rounded-pill px-4 border me-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary rounded-pill px-5 shadow-sm fw-bold">
                                        <i class="bi bi-check-circle-fill me-2"></i>Update Expense
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="mt-4 p-4 bg-dark text-white rounded-4 shadow-sm">
                    <h6 class="fw-bold mb-3"><i class="bi bi-shield-lock-fill text-warning me-2"></i>Audit Trail Notice</h6>
                    <p class="small opacity-75 mb-0">
                        Editing expenses affects your daily reconciliation and bankable balance. Ensure all changes match your physical receipts to prevent discrepancies during end-of-month audits.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Basic form validation
(function () {
  'use strict'
  var forms = document.querySelectorAll('.needs-validation')
  Array.prototype.slice.call(forms).forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!form.checkValidity()) {
        event.preventDefault()
        event.stopPropagation()
      }
      form.classList.add('was-validated')
    }, false)
  })
})()
</script>
@endsection
