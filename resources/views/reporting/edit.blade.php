@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<div class="container-fluid px-4 py-2">
    <!-- Flash Messages -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Financial Summary -->
    <div class="row g-3 mb-4">
        <!-- Total Sales -->
        <div class="col-12 col-md-3">
            <div class="card border-0 bg-light h-100">
                <div class="card-body text-center py-3">
                    <div class="text-primary fs-5 fw-bold">Total Sales</div>
                    <div class="text-primary fs-3">{{ number_format($totalSales, 0) }}</div>
                    <small class="text-muted">All sales revenue</small>
                </div>
            </div>
        </div>
        
        <!-- Credit Sales -->
        <div class="col-12 col-md-6">
            <div class="card border-0 bg-light h-100">
                <div class="card-body text-center py-3">
                    <div class="text-purple fs-5 fw-bold">Credit</div>
                    <div class="text-purple fs-3">{{ number_format($creditSales, 0) }}</div>
                    <small class="text-muted">Unpaid tabs</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Form -->
    <div class="card pixies-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-pencil me-1"></i> Edit Daily Report</h5>
            <small class="text-muted">Updating report for {{ $dailyReport->date->format('M d, Y') }}</small>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('reporting.update', $dailyReport) }}" id="reportingForm">
                @csrf
                @method('PUT')
                
                <input type="hidden" name="total_sales" value="{{ $dailyReport->total_sales }}">

                <!-- Cash Information -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="cash_in_hand" class="form-label fw-semibold">
                            <i class="bi bi-cash-stack me-1"></i> Cash in Hand
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-currency-exchange"></i>
                            </span>
                            <input type="number" 
                                   id="cash_in_hand" 
                                   name="cash_in_hand" 
                                   class="form-control @error('cash_in_hand') is-invalid @enderror" 
                                   min="0" 
                                   step="0.01" 
                                   value="{{ old('cash_in_hand', $dailyReport->cash_in_hand) }}"
                                   placeholder="0.00" 
                                   required>
                        </div>
                        @error('cash_in_hand')
                            <div class="alert alert-danger alert-sm mt-2">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                                    </div>

                <!-- Payments Section -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-semibold mb-0">
                            <i class="bi bi-credit-card me-1"></i> Payment Methods
                        </label>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPaymentRow()">
                            <i class="bi bi-plus-circle me-1"></i> Add Payment
                        </button>
                    </div>
                    
                    <div id="paymentsContainer">
                        <!-- Payment rows will be added here -->
                        @foreach($dailyReport->payments as $index => $payment)
                            <div class="payment-row row g-2 mb-2" data-index="{{ $index }}">
                                <div class="col-md-4">
                                    <select name="payments[{{ $index }}][payment_method]" class="form-select payment-method" required>
                                        <option value="">Select Method</option>
                                        @foreach($paymentMethods as $value => $label)
                                            <option value="{{ $value }}" {{ $payment->payment_method == $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="number" 
                                           name="payments[{{ $index }}][amount]" 
                                           class="form-control payment-amount" 
                                           min="0" 
                                           step="0.01" 
                                           value="{{ old("payments.{$index}.amount", $payment->amount) }}"
                                           placeholder="0.00" 
                                           required>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" 
                                           name="payments[{{ $index }}][description]" 
                                           class="form-control" 
                                           value="{{ old("payments.{$index}.description", $payment->description ?? '') }}"
                                           placeholder="Description (optional)">
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removePaymentRow(this)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Real-time Summary -->
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card bg-light">
                            <div class="card-body py-2">
                                <div class="row text-center">
                                    <div class="col-3">
                                        <small class="text-muted">Cash in Hand</small>
                                        <div class="fw-bold text-success" id="cashInHand">{{ number_format($dailyReport->cash_in_hand, 2) }}</div>
                                        <small class="text-muted">Physical cash</small>
                                    </div>
                                    <div class="col-3">
                                        <small class="text-muted">Other Payments</small>
                                        <div class="fw-bold text-info" id="otherPayments">{{ number_format($totalCollected - $dailyReport->cash_in_hand, 2) }}</div>
                                        <small class="text-muted">Mobile money</small>
                                    </div>
                                    <div class="col-3">
                                        <small class="text-muted">Total Collected</small>
                                        <div class="fw-bold text-primary" id="totalCollected">{{ number_format($totalCollected, 2) }}</div>
                                        <small class="text-muted">Cash + Other</small>
                                    </div>
                                    <div class="col-3">
                                        <small class="text-muted">Missing Amount</small>
                                        <div class="fw-bold text-danger" id="missingAmount">{{ number_format($missingMoney, 2) }}</div>
                                        <small class="text-muted">Collected - (Sales - Credit)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="row mb-3">
                    <div class="col-12">
                        <label for="notes" class="form-label fw-semibold">
                            <i class="bi bi-text-paragraph me-1"></i> Notes (Optional)
                        </label>
                        <textarea id="notes" 
                                  name="notes" 
                                  class="form-control" 
                                  rows="2" 
                                  placeholder="Add any notes about today's cash handling...">{{ old('notes', $dailyReport->notes ?? '') }}</textarea>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="d-flex gap-2 justify-content-end">
                    <a href="{{ route('reporting.show', $dailyReport) }}" class="btn btn-secondary">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Update Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* =========================
   BASE
========================= */
body {
    background: #f6f7fb;
    font-family: system-ui, sans-serif;
}

/* =========================
   COMPACT CARDS
========================= */
.pixies-compact-card .card-body {
    padding: 8px 10px;
}

.pixies-compact-title {
    font-size: 12px;
    margin-bottom: 2px;
    color: #6b7280;
}

.pixies-compact-value {
    font-size: 16px;
    font-weight: 700;
}

/* =========================
   FORM STYLING
========================= */
.payment-row {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 10px;
    border: 1px solid #e9ecef;
}

.payment-row:hover {
    background: #f1f3f4;
}

/* =========================
   MOBILE RESPONSIVE
========================= */
@media (max-width: 768px) {
    /* Section Headers */
    .card .card-body h5 {
        font-size: 1rem !important;
        margin-bottom: 1rem !important;
    }
    
    /* Compact Cards Mobile */
    .pixies-compact-card .card-body {
        padding: 12px 8px;
    }
    
    .pixies-compact-title {
        font-size: 11px;
        margin-bottom: 4px;
    }
    
    .pixies-compact-value {
        font-size: 18px;
        font-weight: 600;
    }
    
    .pixies-compact-value.fs-4 {
        font-size: 20px !important;
    }
    
    .pixies-compact-value.fs-1 {
        font-size: 24px !important;
    }
    
    /* Alert Boxes Mobile */
    .alert {
        padding: 0.75rem;
        font-size: 0.875rem;
    }
    
    .alert h6 {
        font-size: 0.9rem !important;
        margin-bottom: 0.5rem !important;
    }
    
    .alert .fs-4 {
        font-size: 1.25rem !important;
    }
    
    .alert .fs-3 {
        font-size: 1.5rem !important;
    }
    
    /* Form Elements Mobile */
    .form-control {
        font-size: 0.875rem;
        padding: 0.5rem 0.75rem;
    }
    
    .form-label {
        font-size: 0.875rem;
        margin-bottom: 0.25rem;
    }
    
    .btn {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
    }
    
    /* Payment Rows Mobile */
    .payment-row {
        padding: 0.5rem;
    }
    
    .payment-row .col-md-4,
    .payment-row .col-md-1 {
        margin-bottom: 0.5rem;
    }
    
    /* Card Body Mobile */
    .card-body {
        padding: 0.75rem;
    }
    
    /* Spacing Mobile */
    .row.g-2 > * {
        padding: 0.25rem;
    }
    
    .row.g-3 > * {
        padding: 0.5rem;
    }
    
    /* Financial Summary Cards Mobile */
    .col-md-4 .pixies-compact-card .card-body {
        padding: 1rem 0.5rem;
    }
    
    .col-md-3 .pixies-compact-card .card-body {
        padding: 0.75rem 0.25rem;
    }
    
    /* Bankable Balance Card Mobile */
    .border-success .card-body {
        padding: 1.5rem 0.75rem;
    }
    
    /* Small text adjustments */
    small {
        font-size: 0.75rem;
    }
    
    .text-muted {
        font-size: 0.75rem;
    }
    
    /* Hide some visual elements on very small screens */
    @media (max-width: 480px) {
        .pixies-compact-title {
            font-size: 10px;
        }
        
        .pixies-compact-value {
            font-size: 16px;
        }
        
        .pixies-compact-value.fs-4 {
            font-size: 18px !important;
        }
        
        .pixies-compact-value.fs-1 {
            font-size: 20px !important;
        }
        
        .alert {
            padding: 0.5rem;
        }
        
        .form-control {
            font-size: 0.8rem;
            padding: 0.375rem 0.5rem;
        }
        
        .btn {
            font-size: 0.8rem;
            padding: 0.375rem 0.75rem;
        }
    }
}

/* =========================
   TABLET RESPONSIVE
========================= */
@media (min-width: 769px) and (max-width: 1024px) {
    .pixies-compact-card .card-body {
        padding: 10px 8px;
    }
    
    .pixies-compact-value.fs-4 {
        font-size: 22px !important;
    }
    
    .pixies-compact-value.fs-1 {
        font-size: 28px !important;
    }
}
</style>

<script>
let paymentRowIndex = {{ $dailyReport->payments->count() }};

function addPaymentRow() {
    const container = document.getElementById('paymentsContainer');
    const paymentMethods = @json($paymentMethods);
    
    const row = document.createElement('div');
    row.className = 'payment-row row g-2 mb-2';
    row.dataset.index = paymentRowIndex;
    
    row.innerHTML = `
        <div class="col-md-4">
            <select name="payments[${paymentRowIndex}][payment_method]" class="form-select payment-method" required>
                <option value="">Select Method</option>
                ${Object.entries(paymentMethods).map(([value, label]) => 
                    `<option value="${value}">${label}</option>`
                ).join('')}
            </select>
        </div>
        <div class="col-md-4">
            <input type="number" name="payments[${paymentRowIndex}][amount]" class="form-control payment-amount" min="0" step="0.01" placeholder="0.00" required>
        </div>
        <div class="col-md-3">
            <input type="text" name="payments[${paymentRowIndex}][description]" class="form-control" placeholder="Description (optional)">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removePaymentRow(this)">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    
    // Insert at the top (after the first child if exists, otherwise as first child)
    if (container.firstChild) {
        container.insertBefore(row, container.firstChild);
    } else {
        container.appendChild(row);
    }
    
    paymentRowIndex++;
    
    // Add event listeners to new inputs
    row.querySelector('.payment-amount').addEventListener('input', updateCalculations);
}

function removePaymentRow(button) {
    const row = button.closest('.payment-row');
    const container = document.getElementById('paymentsContainer');
    
    // Don't remove the last row
    if (container.children.length > 1) {
        row.remove();
        updateCalculations();
    } else {
        alert('You must have at least one payment method.');
    }
}

function updateCalculations() {
    const cashInHand = parseFloat(document.getElementById('cash_in_hand').value) || 0;
    const totalSales = parseFloat('{{ $totalSales }}') || 0;
    const creditSales = parseFloat('{{ $creditSales }}') || 0;
    const totalExpenses = parseFloat('{{ $totalExpenses }}') || 0;
    
    let otherPayments = 0;
    document.querySelectorAll('.payment-amount').forEach(input => {
        otherPayments += parseFloat(input.value) || 0;
    });
    
    // Total Collected includes Cash in Hand + Other Payments (Mobile)
    const totalCollected = cashInHand + otherPayments;
    
    // Reconciliation variance:
    // +ve = surplus, -ve = missing.
    const expectedCollected = totalSales - creditSales;
    const missingAmount = totalCollected - expectedCollected;
    
    // Update summary values
    document.getElementById('cashInHand').textContent = cashInHand.toFixed(2);
    document.getElementById('otherPayments').textContent = otherPayments.toFixed(2);
    document.getElementById('totalCollected').textContent = totalCollected.toFixed(2);
    document.getElementById('missingAmount').textContent = Math.abs(missingAmount).toFixed(2);
    
    // Update colors based on values
    const cashInHandEl = document.getElementById('cashInHand');
    const missingAmountEl = document.getElementById('missingAmount');
    
    cashInHandEl.className = cashInHand >= 0 ? 'fw-bold text-success' : 'fw-bold text-danger';
    missingAmountEl.className = missingAmount < 0 ? 'fw-bold text-danger' : (missingAmount > 0 ? 'fw-bold text-info' : 'fw-bold text-success');
}

// Initialize calculations on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to existing inputs
    document.getElementById('cash_in_hand').addEventListener('input', updateCalculations);
    document.querySelectorAll('.payment-amount').forEach(input => {
        input.addEventListener('input', updateCalculations);
    });
    
    // Initial calculation
    updateCalculations();
});
</script>
@endsection
