@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .reporting-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
        position: relative;
        z-index: 1;
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
    <div class="reporting-header d-flex align-items-center justify-content-between shadow-sm flex-wrap gap-3">
        <div>
            <h1 class="h4 fw-bold mb-1 text-dark">Balance</h1>
            <p class="text-muted small mb-0">Record payments and shift expenditure to close a day's shift.</p>
        </div>
        <form method="GET" action="{{ route('reporting.create') }}" class="d-flex align-items-center gap-2">
            <label for="balanceDate" class="small fw-bold text-muted mb-0 text-nowrap">
                <i class="bi bi-calendar3 me-1"></i>Balancing for
            </label>
            <input type="date" id="balanceDate" name="date" value="{{ $date }}" max="{{ now()->format('Y-m-d') }}"
                   class="form-control form-control-sm" style="width: auto;" onchange="this.form.submit()">
        </form>
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

        @if($existingReport)
            <div class="alert alert-info border-0 shadow-sm rounded-3 mb-4">
                <i class="bi bi-info-circle-fill me-2"></i>A report for {{ \Carbon\Carbon::parse($date)->format('l, M d, Y') }} already exists — saving will update it.
            </div>
        @else
            <div class="alert alert-light border shadow-sm rounded-3 mb-4">
                <i class="bi bi-calendar-check me-2"></i>Balancing shift for <strong>{{ \Carbon\Carbon::parse($date)->format('l, M d, Y') }}</strong>.
                <a href="#" onclick="document.getElementById('balanceDate').showPicker ? document.getElementById('balanceDate').showPicker() : document.getElementById('balanceDate').focus(); return false;" class="ms-1">Change day</a>
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

        <form method="POST" action="{{ $existingReport ? route('reporting.update', $existingReport) : route('reporting.store') }}" id="reportingForm" enctype="multipart/form-data">
            @csrf
            @if($existingReport) @method('PUT') @endif
            <input type="hidden" name="total_sales" value="{{ $totalSales }}">
            <input type="hidden" name="report_date" value="{{ $date }}">

            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">Pay Through</h5>
                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" onclick="addPaymentRow()">
                        <i class="bi bi-plus-lg me-1"></i>Add Method
                    </button>
                </div>
                <div class="card-body p-4">
                    <div id="paymentsContainer">
                        @if(count($balancePayments) > 0)
                            @foreach($balancePayments as $index => $payment)
                                <div class="payment-row-modern" data-index="{{ $index }}">
                                    <div class="row align-items-end g-3">
                                        <div class="col-md-4">
                                            <label class="small fw-bold text-secondary mb-1">Pay Through</label>
                                            <select name="payments[{{ $index }}][payment_method]" class="form-select form-control-modern payment-method" required>
                                                @foreach($paymentMethods as $value => $label)
                                                    <option value="{{ $value }}" {{ ($payment['payment_method'] ?? '') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-7">
                                            <label class="small fw-bold text-secondary mb-1">Amount</label>
                                            <div class="input-group">
                                                <span class="input-group-text-modern">MWK</span>
                                                <input type="text" name="payments[{{ $index }}][amount]" class="form-control form-control-modern payment-amount fw-bold"
                                                       value="{{ old("payments.{$index}.amount", $payment['amount_display'] ?? $payment['amount'] ?? '') }}"
                                                       placeholder="{{ ($payment['payment_method'] ?? '') === 'Cash' ? '10000' : '2000, 3000, 5000' }}" required>
                                            </div>
                                            <small class="payment-comma-hint text-muted {{ ($payment['payment_method'] ?? 'Cash') === 'Cash' ? 'd-none' : '' }}" style="font-size:0.7rem;">Comma-separate multiple receipts (e.g. 2000, 3000, 5000)</small>
                                            <div class="payment-line-total mt-1 d-none">
                                                <span class="badge bg-dark text-white fw-bold px-3 py-2 fs-6 shadow-sm border border-secondary">
                                                    <i class="bi bi-wallet2 text-warning me-1"></i> Total: MWK <span class="payment-line-total-val text-warning fw-bolder">0</span>
                                                </span>
                                            </div>
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
                                        <label class="small fw-bold text-secondary mb-1">Pay Through</label>
                                        <select name="payments[0][payment_method]" class="form-select form-control-modern payment-method" required>
                                            @foreach($paymentMethods as $value => $label)
                                                <option value="{{ $value }}" {{ $value === 'Cash' ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="small fw-bold text-secondary mb-1">Amount</label>
                                        <div class="input-group">
                                            <span class="input-group-text-modern">MWK</span>
                                            <input type="text" name="payments[0][amount]" class="form-control form-control-modern payment-amount fw-bold" placeholder="10000" required>
                                        </div>
                                        <small class="payment-comma-hint text-muted d-none" style="font-size:0.7rem;">Comma-separate multiple receipts (e.g. 2000, 3000, 5000)</small>
                                        <div class="payment-line-total mt-1 d-none">
                                            <span class="badge bg-dark text-white fw-bold px-3 py-2 fs-6 shadow-sm border border-secondary">
                                                <i class="bi bi-wallet2 text-warning me-1"></i> Total: MWK <span class="payment-line-total-val text-warning fw-bolder">0</span>
                                            </span>
                                        </div>
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

            <!-- Expenditure -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">Expenditure</h5>
                        <p class="small text-muted mb-0">Cash paid out during the shift. Ngongole and Damages are recorded as tabs, not cash expenses.</p>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="addExpenditureRow()">
                        <i class="bi bi-plus-lg me-1"></i>Add Line
                    </button>
                </div>
                <div class="card-body p-4">
                    <div id="expendituresContainer">
                        @forelse($shiftExpenditures as $index => $exp)
                            <div class="payment-row-modern expenditure-row" data-index="{{ $index }}">
                                <div class="row align-items-end g-3">
                                    <div class="col-md-3">
                                        <label class="small fw-bold text-secondary mb-1">Type</label>
                                        <select name="expenditures[{{ $index }}][type]" class="form-select form-control-modern expenditure-type">
                                            @foreach($expenditureTypes as $value => $label)
                                                <option value="{{ $value }}" {{ ($exp['type'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="small fw-bold text-secondary mb-1">Amount (MWK)</label>
                                        <input type="number" name="expenditures[{{ $index }}][amount]" class="form-control form-control-modern expenditure-amount fw-bold" value="{{ $exp['amount'] ?? '' }}" min="0" step="0.01">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="small fw-bold text-secondary mb-1">Notes</label>
                                        <input type="text" name="expenditures[{{ $index }}][notes]" class="form-control form-control-modern expenditure-notes" value="{{ $exp['notes'] ?? '' }}" placeholder="What was damaged?">
                                    </div>
                                    <div class="col-md-7 damage-item-wrap {{ ($exp['type'] ?? '') === 'damages' ? '' : 'd-none' }}">
                                        <label class="small fw-bold text-secondary mb-1">Item Damaged</label>
                                        <select name="expenditures[{{ $index }}][item_id]" class="form-select form-control-modern expenditure-item-select">
                                            <option value="">Select item...</option>
                                            @foreach($damageItems as $di)
                                                <option value="{{ $di['id'] }}" data-price="{{ $di['price'] }}" {{ (string) ($exp['item_id'] ?? '') === (string) $di['id'] ? 'selected' : '' }}>{{ $di['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-5 damage-item-wrap {{ ($exp['type'] ?? '') === 'damages' ? '' : 'd-none' }}">
                                        <label class="small fw-bold text-secondary mb-1">Quantity Damaged (base unit)</label>
                                        <input type="number" name="expenditures[{{ $index }}][quantity]" class="form-control form-control-modern expenditure-quantity" value="{{ $exp['quantity'] ?? '' }}" min="0" step="1" placeholder="e.g. 1">
                                    </div>
                                    <div class="col-md-12 damage-photo-wrap {{ ($exp['type'] ?? '') === 'damages' ? '' : 'd-none' }}">
                                        <label class="small fw-bold text-secondary mb-1">Photo of Damaged Goods</label>
                                        <input type="file" name="expenditures[{{ $index }}][photo]" class="form-control form-control-modern damage-photo-input" accept="image/*">
                                    </div>
                                    <div class="col-md-1 text-end">
                                        <button type="button" class="btn btn-outline-danger border-0 rounded-circle" onclick="removeExpenditureRow(this)"><i class="bi bi-trash3-fill"></i></button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted small mb-0" id="noExpenditureHint">No expenditure recorded yet. Add lunch, transport, damages, or Ngongole.</p>
                        @endforelse
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
                                    <div class="text-white-50 small text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Expected Collected</div>
                                    <div class="h5 mb-0 fw-bold"><span class="small opacity-50">MWK</span> <span id="expectedCollected">0</span></div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <div class="text-white-50 small text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Total Collected</div>
                                    <div class="h4 mb-0 fw-bold"><span class="small opacity-50">MWK</span> <span id="totalCollected">0</span></div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <div class="text-white-50 small text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Variance</div>
                                    <div class="h4 mb-0 fw-bold" id="varianceContainer"><span class="small opacity-50">MWK</span> <span id="missingAmount">0</span></div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <div class="text-white-50 small text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Operational Spend</div>
                                    <div class="h5 mb-0 fw-bold text-warning"><span class="small opacity-50">MWK</span> <span id="operationalSpend">0</span></div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <div class="text-white-50 small text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Ngongole</div>
                                    <div class="h5 mb-0 fw-bold text-info"><span class="small opacity-50">MWK</span> <span id="newDebt">0</span></div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <div class="text-white-50 small text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Bankable Balance</div>
                                    <div class="h5 mb-0 fw-bold text-success"><span class="small opacity-50">MWK</span> <span id="bankableBalance">0</span></div>
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

            <div class="mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <label class="form-label fw-bold text-dark mb-2">Final Shift Notes</label>
                    <textarea name="notes" class="form-control form-control-modern" rows="3" placeholder="Any issues or explanations for variances...">{{ old('notes', $existingReport->notes ?? '') }}</textarea>
                </div>
                <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">View Expense Tracker</a>
            </div>
        </form>
    </div>
</div>

<script>
let paymentRowIndex = {{ count($balancePayments) > 0 ? count($balancePayments) : 1 }};
let expenditureRowIndex = {{ count($shiftExpenditures) > 0 ? count($shiftExpenditures) : 0 }};
const totalSales = parseFloat('{{ $totalSales }}') || 0;
const existingCredit = parseFloat('{{ $baseCreditSales ?? $creditSales ?? 0 }}') || 0;
const expenditureTypes = @json($expenditureTypes);
const damageItems = @json($damageItems);

function addExpenditureRow() {
    const hint = document.getElementById('noExpenditureHint');
    if (hint) hint.remove();
    const container = document.getElementById('expendituresContainer');
    const idx = expenditureRowIndex++;
    const options = Object.entries(expenditureTypes).map(([v, l]) => `<option value="${v}">${l}</option>`).join('');
    const itemOptions = '<option value="">Select item...</option>' + damageItems.map(di => `<option value="${di.id}" data-price="${di.price}">${di.name}</option>`).join('');
    const div = document.createElement('div');
    div.className = 'payment-row-modern expenditure-row';
    div.innerHTML = `
        <div class="row align-items-end g-3">
            <div class="col-md-3">
                <label class="small fw-bold text-secondary mb-1">Type</label>
                <select name="expenditures[${idx}][type]" class="form-select form-control-modern expenditure-type">${options}</select>
            </div>
            <div class="col-md-3">
                <label class="small fw-bold text-secondary mb-1">Amount (MWK)</label>
                <input type="number" name="expenditures[${idx}][amount]" class="form-control form-control-modern expenditure-amount fw-bold" min="0" step="0.01">
            </div>
            <div class="col-md-5">
                <label class="small fw-bold text-secondary mb-1">Notes</label>
                <input type="text" name="expenditures[${idx}][notes]" class="form-control form-control-modern expenditure-notes" placeholder="Details or customer name">
            </div>
            <div class="col-md-7 damage-item-wrap d-none">
                <label class="small fw-bold text-secondary mb-1">Item Damaged</label>
                <select name="expenditures[${idx}][item_id]" class="form-select form-control-modern expenditure-item-select">${itemOptions}</select>
            </div>
            <div class="col-md-5 damage-item-wrap d-none">
                <label class="small fw-bold text-secondary mb-1">Quantity Damaged (base unit)</label>
                <input type="number" name="expenditures[${idx}][quantity]" class="form-control form-control-modern expenditure-quantity" min="0" step="1" placeholder="e.g. 1">
            </div>
            <div class="col-md-12 damage-photo-wrap d-none">
                <label class="small fw-bold text-secondary mb-1">Photo of Damaged Goods</label>
                <input type="file" name="expenditures[${idx}][photo]" class="form-control form-control-modern damage-photo-input" accept="image/*">
            </div>
            <div class="col-md-1 text-end">
                <button type="button" class="btn btn-outline-danger border-0 rounded-circle" onclick="removeExpenditureRow(this)"><i class="bi bi-trash3-fill"></i></button>
            </div>
        </div>`;
    container.insertBefore(div, container.firstChild);
    attachListeners();
    toggleDamageFields(div);
    div.querySelector('.expenditure-amount')?.focus();
}

function toggleDamageFields(row) {
    const type = row.querySelector('.expenditure-type')?.value;
    const wrap = row.querySelector('.damage-photo-wrap');
    const input = row.querySelector('.damage-photo-input');
    const itemWraps = row.querySelectorAll('.damage-item-wrap');
    const itemSelect = row.querySelector('.expenditure-item-select');
    const qtyInput = row.querySelector('.expenditure-quantity');
    const isDamages = type === 'damages';

    itemWraps.forEach(w => w.classList.toggle('d-none', !isDamages));
    if (itemSelect) itemSelect.required = isDamages;
    if (qtyInput) qtyInput.required = isDamages;
    if (!isDamages) {
        if (itemSelect) itemSelect.value = '';
        if (qtyInput) qtyInput.value = '';
    }

    if (!wrap) return;
    if (isDamages) {
        wrap.classList.remove('d-none');
        if (input) input.required = true;
    } else {
        wrap.classList.add('d-none');
        if (input) {
            input.required = false;
            input.value = '';
        }
    }
}

function updateDamageAmount(row) {
    const itemSelect = row.querySelector('.expenditure-item-select');
    const qtyInput = row.querySelector('.expenditure-quantity');
    const amountInput = row.querySelector('.expenditure-amount');
    if (!itemSelect || !qtyInput || !amountInput) return;

    const price = parseFloat(itemSelect.options[itemSelect.selectedIndex]?.dataset.price) || 0;
    const qty = parseFloat(qtyInput.value) || 0;
    if (price > 0 && qty > 0) {
        amountInput.value = (price * qty).toFixed(2);
    }
}

function removeExpenditureRow(btn) {
    btn.closest('.expenditure-row').remove();
    updateCalculations();
}

function parsePaymentInputValue(raw) {
    const text = String(raw || '').trim();
    if (text.includes(',')) {
        return text.split(',').reduce((sum, part) => sum + (parseFloat(part.trim().replace(/[^\d.]/g, '')) || 0), 0);
    }
    return parseFloat(text.replace(/[^\d.]/g, '')) || 0;
}

function updatePaymentRowHints(row) {
    const method = row.querySelector('.payment-method')?.value;
    const input = row.querySelector('.payment-amount');
    const hint = row.querySelector('.payment-comma-hint');
    const totalWrap = row.querySelector('.payment-line-total');
    const totalSpan = row.querySelector('.payment-line-total-val') || row.querySelector('.payment-line-total span');
    const isCash = method === 'Cash';
    if (input) {
        input.placeholder = isCash ? '10000' : '2000, 3000, 5000';
    }
    if (hint) {
        hint.classList.toggle('d-none', isCash);
    }
    if (totalWrap && totalSpan && input) {
        const textVal = String(input.value || '').trim();
        const total = parsePaymentInputValue(textVal);
        if (!isCash && (textVal.includes(',') || total > 0)) {
            totalWrap.classList.remove('d-none');
            totalSpan.textContent = total.toLocaleString();
        } else {
            totalWrap.classList.add('d-none');
        }
    }
}

function onPaymentMethodChange(e) {
    const row = e.target.closest('.payment-row-modern');
    if (row) {
        updatePaymentRowHints(row);
        updateCalculations();
    }
}

function addPaymentRow() {
    const container = document.getElementById('paymentsContainer');
    const paymentMethods = @json($paymentMethods);
    
    const div = document.createElement('div');
    div.className = 'payment-row-modern';
    div.dataset.index = paymentRowIndex;
    div.innerHTML = `
        <div class="row align-items-end g-3">
            <div class="col-md-4">
                <label class="small fw-bold text-secondary mb-1">Pay Through</label>
                <select name="payments[${paymentRowIndex}][payment_method]" class="form-select form-control-modern payment-method" required>
                    ${Object.entries(paymentMethods).map(([v, l]) => `<option value="${v}">${l}</option>`).join('')}
                </select>
            </div>
            <div class="col-md-7">
                <label class="small fw-bold text-secondary mb-1">Amount</label>
                <div class="input-group">
                    <span class="input-group-text-modern">MWK</span>
                    <input type="text" name="payments[${paymentRowIndex}][amount]" class="form-control form-control-modern payment-amount fw-bold" placeholder="2000, 3000, 5000" required>
                </div>
                <small class="payment-comma-hint text-muted" style="font-size:0.7rem;">Comma-separate multiple receipts (e.g. 2000, 3000, 5000)</small>
                <div class="payment-line-total mt-1 d-none">
                    <span class="badge bg-dark text-white fw-bold px-3 py-2 fs-6 shadow-sm border border-secondary">
                        <i class="bi bi-wallet2 text-warning me-1"></i> Total: MWK <span class="payment-line-total-val text-warning fw-bolder">0</span>
                    </span>
                </div>
            </div>
            <div class="col-md-1 text-end">
                <button type="button" class="btn btn-outline-danger border-0 rounded-circle" onclick="removePaymentRow(this)">
                    <i class="bi bi-trash3-fill"></i>
                </button>
            </div>
        </div>
    `;
    container.insertBefore(div, container.firstChild);
    paymentRowIndex++;
    attachListeners();
    updatePaymentRowHints(div);
    div.querySelector('.payment-amount')?.focus();
}

function removePaymentRow(btn) {
    const rows = document.querySelectorAll('#paymentsContainer .payment-row-modern');
    if (rows.length > 1) {
        btn.closest('.payment-row-modern').remove();
        updateCalculations();
    }
}

function updateCalculations() {
    let totalCollected = 0;
    document.querySelectorAll('.payment-amount').forEach(input => {
        totalCollected += parsePaymentInputValue(input.value);
        const row = input.closest('.payment-row-modern');
        if (row) updatePaymentRowHints(row);
    });

    let operationalSpend = 0;
    let newDebt = 0;
    document.querySelectorAll('.expenditure-row').forEach(row => {
        const type = row.querySelector('.expenditure-type')?.value;
        const amount = parseFloat(row.querySelector('.expenditure-amount')?.value) || 0;
        if (type === 'debt') newDebt += amount;
        else operationalSpend += amount;
    });

    const totalCredit = existingCredit + newDebt;
    // Expected collected is raw receipts from customers (sales minus credit);
    // expenses are only removed in the bankable figure below.
    const expectedCollected = totalSales - totalCredit;
    const variance = totalCollected - expectedCollected;
    const bankable = totalCollected - operationalSpend;

    document.getElementById('expectedCollected').innerText = expectedCollected.toLocaleString();
    document.getElementById('totalCollected').innerText = totalCollected.toLocaleString();
    document.getElementById('missingAmount').innerText = Math.abs(variance).toLocaleString();
    document.getElementById('operationalSpend').innerText = operationalSpend.toLocaleString();
    document.getElementById('newDebt').innerText = newDebt.toLocaleString();
    document.getElementById('bankableBalance').innerText = bankable.toLocaleString();

    const container = document.getElementById('varianceContainer');
    if (variance < 0) {
        container.className = 'h4 mb-0 fw-bold text-danger';
    } else if (variance > 0) {
        container.className = 'h4 mb-0 fw-bold text-info';
    } else {
        container.className = 'h4 mb-0 fw-bold text-success';
    }
}

function attachListeners() {
    document.querySelectorAll('.payment-amount, .expenditure-amount, .expenditure-type').forEach(input => {
        input.removeEventListener('input', updateCalculations);
        input.removeEventListener('change', updateCalculations);
        input.addEventListener('input', updateCalculations);
        input.addEventListener('change', updateCalculations);
    });
    document.querySelectorAll('.expenditure-type').forEach(select => {
        select.removeEventListener('change', onExpenditureTypeChange);
        select.addEventListener('change', onExpenditureTypeChange);
    });
    document.querySelectorAll('.expenditure-item-select, .expenditure-quantity').forEach(input => {
        input.removeEventListener('change', onDamageItemChange);
        input.removeEventListener('input', onDamageItemChange);
        input.addEventListener('change', onDamageItemChange);
        input.addEventListener('input', onDamageItemChange);
    });
    document.querySelectorAll('.payment-method').forEach(select => {
        select.removeEventListener('change', onPaymentMethodChange);
        select.addEventListener('change', onPaymentMethodChange);
    });
}

function onExpenditureTypeChange(e) {
    const row = e.target.closest('.expenditure-row');
    if (row) toggleDamageFields(row);
}

function onDamageItemChange(e) {
    const row = e.target.closest('.expenditure-row');
    if (row) updateDamageAmount(row);
}

document.addEventListener('DOMContentLoaded', () => {
    attachListeners();
    document.querySelectorAll('#paymentsContainer .payment-row-modern').forEach(updatePaymentRowHints);
    document.querySelectorAll('.expenditure-row').forEach(toggleDamageFields);
    updateCalculations();
});
</script>
@endsection
