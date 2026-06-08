@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .reporting-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: -1.5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }
    .summary-pill {
        background: #f8fafc;
        border-radius: 12px;
        padding: 1rem;
        text-align: center;
        border: 1px solid #e2e8f0;
        height: 100%;
    }
    .payment-row-modern {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1rem;
        transition: all 0.2s;
    }
    .payment-row-modern:hover {
        border-color: var(--pixies-primary);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    .input-group-text-modern {
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        color: #475569;
        font-weight: 600;
        font-size: 0.75rem;
    }
    .form-control-modern {
        border: 1px solid #cbd5e1;
        padding: 0.6rem 1rem;
        border-radius: 8px;
    }
    .form-control-modern:focus {
        border-color: var(--pixies-primary);
        box-shadow: 0 0 0 3px rgba(30, 41, 59, 0.1);
    }
    
    @media (max-width: 768px) {
        .reporting-header { flex-direction: column; align-items: flex-start !important; gap: 1rem; padding: 1rem; }
        .payment-row-modern .row > div { margin-bottom: 1rem; }
        .payment-row-modern .row > div:last-child { margin-bottom: 0; }
    }
</style>

<div class="container-fluid p-0">
    <!-- Modern Header -->
    <div class="reporting-header d-flex align-items-center justify-content-between shadow-sm">
        <div>
            <h1 class="h4 fw-bold mb-1 text-dark">Daily Cash Reconciliation</h1>
            <p class="text-muted small mb-0">Record physical cash and other payments to balance your shift.</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-light text-dark border border-secondary border-opacity-10 px-3 py-2 rounded-pill">
                📅 {{ now()->format('M d, Y') }}
            </span>
        </div>
    </div>

    <div class="px-4 pb-5">
        @if(session('success'))
            <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            </div>
        @endif
        
        @if(session('error') || $errors->any())
            <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                @if(session('error')) {{ session('error') }} @else Please correct the errors below. @endif
            </div>
        @endif

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="summary-pill shadow-sm bg-white">
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">Total Expected Sales</div>
                    <div class="h3 mb-0 fw-bold text-dark">
                        <span class="small fs-6 opacity-50">MWK</span> {{ number_format($totalSales) }}
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="summary-pill shadow-sm bg-white">
                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">Cash to Collect</div>
                    <div class="h3 mb-0 fw-bold text-success">
                        <span class="small fs-6 opacity-50">MWK</span> {{ number_format($totalSales - ($creditSales ?? 0)) }}
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ $existingReport ? route('reporting.update', $existingReport) : route('reporting.store') }}" id="reportingForm">
            @csrf
            @if($existingReport) @method('PUT') @endif
            <input type="hidden" name="total_sales" value="{{ $totalSales }}">

            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark">Cash & Payments</h5>
                </div>
                <div class="card-body p-4">
                    <!-- Physical Cash -->
                    <div class="row mb-5">
                        <div class="col-md-5">
                            <label class="form-label fw-bold text-dark mb-2">Physical Cash in Hand</label>
                            <div class="input-group">
                                <span class="input-group-text-modern px-3">MWK</span>
                                <input type="number" id="cash_in_hand" name="cash_in_hand" class="form-control form-control-modern fw-bold text-dark" 
                                       value="{{ old('cash_in_hand', $existingReport->cash_in_hand ?? 0) }}" required>
                            </div>
                            <small class="text-muted mt-2 d-block">The actual physical cash you have counted in the drawer.</small>
                        </div>
                    </div>

                    <!-- Other Payment Methods -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="fw-bold text-dark mb-0">Non-Cash Payments (Mobile Money, etc.)</h6>
                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" onclick="addPaymentRow()">
                            <i class="bi bi-plus-lg me-1"></i>Add Method
                        </button>
                    </div>

                    <div id="paymentsContainer">
                        @if($existingReport && $existingReport->payments->count() > 0)
                            @foreach($existingReport->payments as $index => $payment)
                                <div class="payment-row-modern" data-index="{{ $index }}">
                                    <div class="row align-items-end g-3">
                                        <div class="col-md-4">
                                            <label class="small fw-bold text-secondary mb-1">Method</label>
                                            <select name="payments[{{ $index }}][payment_method]" class="form-select form-control-modern payment-method" required>
                                                @foreach($paymentMethods as $value => $label)
                                                    <option value="{{ $value }}" {{ $payment->payment_method == $value ? 'selected' : '' }}>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="small fw-bold text-secondary mb-1">Amount</label>
                                            <div class="input-group">
                                                <span class="input-group-text-modern">MWK</span>
                                                <input type="number" name="payments[{{ $index }}][amount]" class="form-control form-control-modern payment-amount fw-bold" 
                                                       value="{{ $payment->amount }}" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="small fw-bold text-secondary mb-1">Reference/Note</label>
                                            <input type="text" name="payments[{{ $index }}][description]" class="form-control form-control-modern" 
                                                   value="{{ $payment->description }}" placeholder="e.g. Transaction ID">
                                        </div>
                                        <div class="col-md-1 text-end">
                                            <button type="button" class="btn btn-outline-danger border-0 rounded-circle" onclick="removePaymentRow(this)">
                                                <i class="bi bi-trash3-fill"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                             <div class="payment-row-modern" data-index="0">
                                <div class="row align-items-end g-3">
                                    <div class="col-md-4">
                                        <label class="small fw-bold text-secondary mb-1">Method</label>
                                        <select name="payments[0][payment_method]" class="form-select form-control-modern payment-method" required>
                                            <option value="">Select Method</option>
                                            @foreach($paymentMethods as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="small fw-bold text-secondary mb-1">Amount</label>
                                        <div class="input-group">
                                            <span class="input-group-text-modern">MWK</span>
                                            <input type="number" name="payments[0][amount]" class="form-control form-control-modern payment-amount fw-bold" placeholder="0" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="small fw-bold text-secondary mb-1">Reference/Note</label>
                                        <input type="text" name="payments[0][description]" class="form-control form-control-modern" placeholder="Optional notes">
                                    </div>
                                    <div class="col-md-1 text-end">
                                        <button type="button" class="btn btn-outline-danger border-0 rounded-circle" onclick="removePaymentRow(this)">
                                            <i class="bi bi-trash3-fill"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Real-time Reconciliation Logic -->
            <div class="card border-0 shadow-sm rounded-4 bg-dark text-white mb-4">
                <div class="card-body p-4">
                    <div class="row align-items-center g-4">
                        <div class="col-md-8">
                            <div class="row g-4 text-center text-md-start">
                                <div class="col-6 col-md-4">
                                    <div class="text-white-50 small text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Total Collected</div>
                                    <div class="h4 mb-0 fw-bold"><span class="small opacity-50">MWK</span> <span id="totalCollected">0</span></div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <div class="text-white-50 small text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Difference</div>
                                    <div class="h4 mb-0 fw-bold" id="varianceContainer"><span class="small opacity-50">MWK</span> <span id="missingAmount">0</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end">
                             <button type="submit" class="btn btn-primary rounded-pill px-5 py-2 fw-bold shadow">
                                <i class="bi bi-check-circle-fill me-2"></i>{{ $existingReport ? 'Update Report' : 'Submit Report' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold text-dark mb-2">Final Shift Notes</label>
                <textarea name="notes" class="form-control form-control-modern" rows="3" placeholder="Any issues or explanations for variances...">{{ old('notes', $existingReport->notes ?? '') }}</textarea>
            </div>
        </form>
    </div>
</div>

<script>
let paymentRowIndex = {{ $existingReport ? $existingReport->payments->count() : 1 }};

function addPaymentRow() {
    const container = document.getElementById('paymentsContainer');
    const paymentMethods = @json($paymentMethods);
    
    const div = document.createElement('div');
    div.className = 'payment-row-modern';
    div.dataset.index = paymentRowIndex;
    div.innerHTML = `
        <div class="row align-items-end g-3">
            <div class="col-md-4">
                <label class="small fw-bold text-secondary mb-1">Method</label>
                <select name="payments[${paymentRowIndex}][payment_method]" class="form-select form-control-modern payment-method" required>
                    <option value="">Select Method</option>
                    ${Object.entries(paymentMethods).map(([v, l]) => `<option value="${v}">${l}</option>`).join('')}
                </select>
            </div>
            <div class="col-md-3">
                <label class="small fw-bold text-secondary mb-1">Amount</label>
                <div class="input-group">
                    <span class="input-group-text-modern">MWK</span>
                    <input type="number" name="payments[${paymentRowIndex}][amount]" class="form-control form-control-modern payment-amount fw-bold" required>
                </div>
            </div>
            <div class="col-md-4">
                <label class="small fw-bold text-secondary mb-1">Reference/Note</label>
                <input type="text" name="payments[${paymentRowIndex}][description]" class="form-control form-control-modern" placeholder="Optional notes">
            </div>
            <div class="col-md-1 text-end">
                <button type="button" class="btn btn-outline-danger border-0 rounded-circle" onclick="removePaymentRow(this)">
                    <i class="bi bi-trash3-fill"></i>
                </button>
            </div>
        </div>
    `;
    container.appendChild(div);
    paymentRowIndex++;
    attachListeners();
}

function removePaymentRow(btn) {
    const rows = document.querySelectorAll('.payment-row-modern');
    if (rows.length > 1) {
        btn.closest('.payment-row-modern').remove();
        updateCalculations();
    }
}

function updateCalculations() {
    const cashInHand = parseFloat(document.getElementById('cash_in_hand').value) || 0;
    const expected = parseFloat('{{ $totalSales - ($creditSales ?? 0) }}') || 0;
    
    let otherPayments = 0;
    document.querySelectorAll('.payment-amount').forEach(input => {
        otherPayments += parseFloat(input.value) || 0;
    });
    
    const totalCollected = cashInHand + otherPayments;
    const variance = totalCollected - expected;
    
    document.getElementById('totalCollected').innerText = totalCollected.toLocaleString();
    document.getElementById('missingAmount').innerText = Math.abs(variance).toLocaleString();
    
    const container = document.getElementById('varianceContainer');
    if (variance < 0) {
        container.className = 'h4 mb-0 fw-bold text-danger';
        container.title = 'Shortfall detected';
    } else if (variance > 0) {
        container.className = 'h4 mb-0 fw-bold text-info';
        container.title = 'Surplus detected';
    } else {
        container.className = 'h4 mb-0 fw-bold text-success';
        container.title = 'Perfectly balanced';
    }
}

function attachListeners() {
    document.querySelectorAll('#cash_in_hand, .payment-amount').forEach(input => {
        input.removeEventListener('input', updateCalculations);
        input.addEventListener('input', updateCalculations);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    attachListeners();
    updateCalculations();
});
</script>
@endsection
