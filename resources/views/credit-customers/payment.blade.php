@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .payment-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: -1.5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }
    .payment-card-modern {
        border-radius: 16px;
        background: white;
        border: 1px solid #e2e8f0;
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
    .quick-btn {
        border: 1px solid #e2e8f0;
        background: white;
        border-radius: 12px;
        padding: 0.75rem;
        transition: all 0.2s;
        text-align: left;
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        margin-bottom: 0.5rem;
        color: #1e293b;
        font-weight: 500;
    }
    .quick-btn:hover {
        border-color: var(--pixies-primary);
        background: #f8fafc;
        transform: translateX(4px);
    }
    .quick-btn span {
        color: var(--pixies-primary);
        font-weight: 700;
    }

    @media (max-width: 768px) {
        .payment-header { flex-direction: column; align-items: flex-start !important; gap: 1rem; padding: 1rem; }
    }
</style>

<div class="container-fluid p-0">
    <!-- Modern Header -->
    <div class="payment-header d-flex align-items-center justify-content-between shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('credit-customers.show', $customerName) }}" class="btn btn-sm btn-light rounded-circle border shadow-sm bg-white">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="h4 fw-bold mb-0 text-dark">Record Payment</h1>
                <p class="text-muted small mb-0">Receiving money from <strong>{{ $customerName }}</strong></p>
            </div>
        </div>
        <div class="d-none d-md-block">
            <div class="small text-muted text-uppercase fw-bold" style="font-size: 0.6rem;">Balance Due</div>
            <div class="h5 fw-bold text-danger mb-0">MWK {{ number_format($totalBalance) }}</div>
        </div>
    </div>

    <div class="px-4 pb-5">
        <div class="row g-4">
            <div class="col-lg-8">
                <!-- Payment Form -->
                <div class="payment-card-modern shadow-sm overflow-hidden mb-4">
                    <div class="p-4 border-bottom bg-light bg-opacity-50">
                        <h5 class="fw-bold mb-0 text-dark">Payment Details</h5>
                    </div>
                    <div class="p-4">
                        <form action="{{ route('credit-customers.record-payment', $customerName) }}" method="POST">
                            @csrf
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Amount to Pay</label>
                                    <div class="input-group">
                                        <span class="input-group-text-modern px-3">MWK</span>
                                        <input type="number" id="amount" name="amount" class="form-control form-control-modern fw-bold text-dark fs-5" 
                                               value="{{ old('amount') }}" max="{{ $totalBalance }}" step="1" required>
                                    </div>
                                    <small class="text-muted mt-2 d-block">Max allowed: {{ number_format($totalBalance) }}</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Payment Method</label>
                                    <select name="payment_method" class="form-select form-control-modern" required>
                                        <option value="">Select Method</option>
                                        <option value="cash">Cash</option>
                                        <option value="mobile_money">Mobile Money (Airtel/Mpamba)</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold text-dark">Notes / Reference</label>
                                    <textarea name="notes" class="form-control form-control-modern" rows="3" placeholder="Add any details about this transaction...">{{ old('notes') }}</textarea>
                                </div>
                                <div class="col-12">
                                    <div class="bg-indigo bg-opacity-10 rounded-3 p-3 border border-indigo border-opacity-10 d-flex gap-3">
                                        <i class="bi bi-info-circle-fill text-indigo fs-5"></i>
                                        <p class="text-indigo small mb-0 fw-medium">
                                            This payment will be automatically applied to the <strong>oldest entries first</strong> to clear debts sequentially.
                                        </p>
                                    </div>
                                </div>
                                <div class="col-12 text-end">
                                    <a href="{{ route('credit-customers.show', $customerName) }}" class="btn btn-light rounded-pill px-4 border me-2">Cancel</a>
                                    <button type="submit" class="btn btn-success rounded-pill px-5 shadow-sm fw-bold">
                                        <i class="bi bi-check-circle-fill me-2"></i>Record Payment
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Unpaid Items List -->
                <div class="payment-card-modern shadow-sm overflow-hidden">
                    <div class="p-4 border-bottom bg-light bg-opacity-50">
                        <h5 class="fw-bold mb-0 text-dark">Pending Debts ({{ $unpaidTabs->count() }})</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="table-light">
                                <tr class="small text-uppercase text-muted fw-bold">
                                    <th class="px-4 border-0">Date</th>
                                    <th class="border-0">Description</th>
                                    <th class="text-end border-0">Total</th>
                                    <th class="text-end px-4 border-0">Remaining</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($unpaidTabs as $tab)
                                    <tr>
                                        <td class="px-4 text-dark">{{ \Carbon\Carbon::parse($tab->date)->format('M d, Y') }}</td>
                                        <td class="text-muted small">{{ $tab->description ?: 'Bar Tab' }}</td>
                                        <td class="text-end text-dark">{{ number_format($tab->amount) }}</td>
                                        <td class="text-end px-4 fw-bold text-danger">MWK {{ number_format($tab->balance) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Quick Payments -->
                <div class="payment-card-modern shadow-sm p-4 mb-4">
                    <h6 class="fw-bold text-dark mb-4"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Quick Amounts</h6>
                    <button class="quick-btn" onclick="setPaymentAmount({{ $totalBalance }})">
                        Clear Total Balance <span>MWK {{ number_format($totalBalance) }}</span>
                    </button>
                    @if($totalBalance > 5000)
                        <button class="quick-btn" onclick="setPaymentAmount({{ round($totalBalance / 2) }})">
                            Pay Half <span>MWK {{ number_format(round($totalBalance / 2)) }}</span>
                        </button>
                    @endif
                    <button class="quick-btn" onclick="setPaymentAmount(1000)">
                        Pay 1,000 <span>MWK 1,000</span>
                    </button>
                    <button class="quick-btn" onclick="setPaymentAmount(5000)">
                        Pay 5,000 <span>MWK 5,000</span>
                    </button>
                    <button class="quick-btn" onclick="setPaymentAmount(10000)">
                        Pay 10,000 <span>MWK 10,000</span>
                    </button>
                </div>

                <!-- Guidance -->
                <div class="card border-0 bg-dark text-white rounded-4 p-4 shadow-sm">
                    <h6 class="fw-bold mb-3">Reconciliation Help</h6>
                    <ul class="list-unstyled small mb-0 opacity-75">
                        <li class="mb-3 d-flex gap-2">
                            <i class="bi bi-check2-circle text-success"></i>
                            Payments made via Mobile Money should be cross-checked with the phone confirmation.
                        </li>
                        <li class="mb-3 d-flex gap-2">
                            <i class="bi bi-check2-circle text-success"></i>
                            Once recorded, the customer balance updates instantly across all dashboards.
                        </li>
                        <li class="d-flex gap-2">
                            <i class="bi bi-check2-circle text-success"></i>
                            Ensure you add a reference note for large payments to aid auditing.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function setPaymentAmount(amount) {
    document.getElementById('amount').value = amount;
    // Highlight the input briefly
    const input = document.getElementById('amount');
    input.classList.add('border-primary');
    setTimeout(() => input.classList.remove('border-primary'), 500);
}

document.addEventListener('DOMContentLoaded', () => {
    const amountInput = document.getElementById('amount');
    const maxVal = parseFloat(amountInput.getAttribute('max'));
    
    amountInput.addEventListener('input', function() {
        if (parseFloat(this.value) > maxVal) {
            this.value = maxVal;
        }
    });
});
</script>
@endsection
