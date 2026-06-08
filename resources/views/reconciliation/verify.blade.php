@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .verify-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: -1.5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }
    .verify-card-modern {
        border-radius: 16px;
        background: white;
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    .metric-box {
        padding: 1.25rem;
        border-radius: 12px;
        text-align: center;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }
    .form-control-verify {
        border: 2px solid #cbd5e1;
        padding: 1rem;
        border-radius: 12px;
        font-size: 1.5rem;
        font-weight: 800;
        text-align: center;
        color: #1e293b;
    }
    .form-control-verify:focus {
        border-color: var(--pixies-primary);
        box-shadow: 0 0 0 4px rgba(30, 41, 59, 0.1);
    }
    
    @media (max-width: 768px) {
        .verify-header { flex-direction: column; align-items: flex-start !important; gap: 1rem; padding: 1rem; }
    }
</style>

<div class="container-fluid p-0">
    <!-- Modern Header -->
    <div class="verify-header d-flex align-items-center justify-content-between shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('reconciliation.index') }}" class="btn btn-sm btn-light rounded-circle border shadow-sm bg-white">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="h4 fw-bold mb-0 text-dark">Verify Cash Count</h1>
                <p class="text-muted small mb-0">{{ $stockEntry->bar->name }} • {{ $stockEntry->date->format('M d, Y') }}</p>
            </div>
        </div>
    </div>

    <div class="px-4 pb-5">
        <div class="row g-4">
            <div class="col-lg-8">
                <!-- Summary Metrics -->
                <div class="verify-card-modern shadow-sm p-4 mb-4">
                    <h6 class="fw-bold text-dark mb-4">Shift Financial Summary</h6>
                    <div class="row g-3">
                        <div class="col-md-6 col-lg-3">
                            <div class="metric-box border-success border-opacity-25">
                                <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Total Sales</div>
                                <div class="h5 mb-0 fw-bold text-success">{{ number_format($totalSales) }}</div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="metric-box border-warning border-opacity-25">
                                <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Credit Sales</div>
                                <div class="h5 mb-0 fw-bold text-warning">{{ number_format($creditSales) }}</div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="metric-box border-danger border-opacity-25">
                                <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Expenses</div>
                                <div class="h5 mb-0 fw-bold text-danger">{{ number_format($totalExpenses) }}</div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="metric-box bg-dark text-white border-0">
                                <div class="small text-uppercase fw-bold mb-1 opacity-50" style="font-size: 0.6rem;">Bankable</div>
                                <div class="h5 mb-0 fw-bold">MWK {{ number_format($bankableBalance) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-top">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="metric-box border-primary border-opacity-25">
                                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Electronic Total</div>
                                    <div class="h5 mb-0 fw-bold text-primary">{{ number_format($electronicTotal) }}</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="metric-box bg-light border border-secondary border-opacity-25">
                                    <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Recorded Cash</div>
                                    <div class="h5 mb-0 fw-bold">MWK {{ number_format($cashInHand) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($stockEntry->payments->count() > 0)
                        <div class="mt-4 pt-3 border-top">
                            <p class="small text-muted fw-bold text-uppercase mb-2" style="font-size: 0.65rem;">Non-Cash Breakdown</p>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($stockEntry->payments as $payment)
                                    <span class="badge bg-light text-dark border px-3 py-2 rounded-pill small fw-medium">
                                        {{ $payment->type }}: {{ number_format($payment->amount) }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Input Form -->
                <div class="verify-card-modern shadow-sm mb-4">
                    <div class="p-4 border-bottom bg-light bg-opacity-50">
                        <h5 class="fw-bold mb-0 text-dark">Verification Form</h5>
                    </div>
                    <div class="p-4">
                        <form method="POST" action="{{ route('reconciliation.store', $stockEntry->id) }}">
                            @csrf
                            <div class="mb-4 row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark d-block text-center mb-3">Actual Cash Counted (MWK)</label>
                                    <input type="number" id="cash_counted" name="cash_counted" step="1" min="0" required
                                           class="form-control form-control-verify" placeholder="Enter cash amount...">
                                    <p class="text-center text-muted small mt-2">Enter the physical cash received for this shift.</p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark d-block text-center mb-3">Electronic Counted (MWK)</label>
                                    <input type="number" id="electronic_counted" name="electronic_counted" step="1" min="0"
                                           class="form-control form-control-verify" placeholder="Enter electronic amount...">
                                    <p class="text-center text-muted small mt-2">This should match the recorded total electronic amount: MWK {{ number_format($electronicTotal) }}.</p>
                                </div>
                            </div>

                            <!-- Live Result -->
                            <div id="resultPreview" class="d-none mb-5 p-4 rounded-4 bg-light border">
                                <div class="row g-3 text-center">
                                    <div class="col-md-4">
                                        <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">Bankable Variance</div>
                                        <div id="differenceAmount" class="h4 fw-bold mb-0">0</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">Electronic Match</div>
                                        <div id="electronicStatus" class="h4 fw-bold mb-0">-</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">Bankable Counted</div>
                                        <div id="combinedStatus" class="h4 fw-bold mb-0">-</div>
                                    </div>
                                </div>
                                <div class="text-center mt-2">
                                    <div id="shortfallExplanation" class="small text-muted"></div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-dark">Observation / Audit Notes</label>
                                <textarea name="notes" rows="3" class="form-control" placeholder="Explain any shortages or excesses here..."></textarea>
                            </div>

                            <div class="d-flex justify-content-between pt-3">
                                <a href="{{ route('reconciliation.index') }}" class="btn btn-light rounded-pill px-4 border">Cancel</a>
                                <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                                    <i class="bi bi-shield-check me-2"></i>Verify & Close Shift
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Shift Context -->
                <div class="verify-card-modern shadow-sm p-4 mb-4">
                    <h6 class="fw-bold text-dark mb-4">Shift Details</h6>
                    <div class="d-flex gap-3 mb-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 h-fit">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <div>
                            <div class="small text-muted">Seller in Charge</div>
                            <div class="fw-bold text-dark">{{ $stockEntry->user->name }}</div>
                        </div>
                    </div>
                    <div class="d-flex gap-3">
                        <div class="bg-purple bg-opacity-10 text-purple rounded-3 p-2 h-fit">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div>
                            <div class="small text-muted">Items Handled</div>
                            <div class="fw-bold text-dark">{{ $stockEntry->stockEntryItems->sum('sold_quantity') }} units sold</div>
                        </div>
                    </div>
                </div>

                <!-- Best Sellers -->
                @php
                    $topItems = $stockEntry->stockEntryItems()->with('item')->where('sold_quantity', '>', 0)
                                ->orderBy('sales_amount', 'desc')->take(5)->get();
                @endphp
                @if($topItems->count() > 0)
                <div class="verify-card-modern shadow-sm p-4">
                    <h6 class="fw-bold text-dark mb-3">Top Shift Items</h6>
                    @foreach($topItems as $ti)
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-light">
                            <div class="small text-dark fw-medium">{{ $ti->item->name }}</div>
                            <div class="small fw-bold text-primary">{{ number_format($ti->sold_quantity) }} sold</div>
                        </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const cashInput = document.getElementById('cash_counted');
    const electronicInput = document.getElementById('electronic_counted');
    const preview = document.getElementById('resultPreview');
    const diffDisplay = document.getElementById('differenceAmount');
    const electronicStatus = document.getElementById('electronicStatus');
    const combinedStatus = document.getElementById('combinedStatus');
    const expectedCash = {{ $expectedCash }};
    const expectedElectronic = {{ $electronicTotal }};
    const expectedCollected = {{ $expectedCollected }};
    const totalSales = {{ $totalSales }};
    const totalExpenses = {{ $totalExpenses }};
    const creditSales = {{ $creditSales ?? 0 }};

    const updatePreview = () => {
        const cashVal = parseFloat(cashInput.value) || 0;
        const electronicVal = parseFloat(electronicInput.value);
        const electronicCounted = Number.isFinite(electronicVal) ? electronicVal : expectedElectronic;
        const expectedBankable = totalSales - creditSales - totalExpenses;
        const bankableTotal = cashVal + electronicCounted - totalExpenses;
        const bankableDiff = bankableTotal - expectedBankable;
        const electronicDiff = electronicCounted - expectedElectronic;
        const combinedTotal = cashVal + electronicCounted;
        const combinedDiff = combinedTotal - expectedCollected;

        preview.classList.remove('d-none');

        diffDisplay.innerText = (bankableDiff >= 0 ? '+' : '') + bankableDiff.toLocaleString();
        diffDisplay.className = `h2 fw-bold mb-0 ${bankableDiff === 0 ? 'text-success' : bankableDiff < 0 ? 'text-danger' : 'text-warning'}`;

        electronicStatus.innerText = electronicDiff === 0 ? 'MATCHED' : (electronicDiff < 0 ? 'SHORTAGE' : 'EXCESS');
        electronicStatus.className = `h4 fw-bold mb-0 ${electronicDiff === 0 ? 'text-success' : electronicDiff < 0 ? 'text-danger' : 'text-warning'}`;

        combinedStatus.innerText = bankableTotal.toLocaleString();
        combinedStatus.className = 'h4 fw-bold mb-0 text-dark';

        // Explain shortfall using credit sales if applicable
        const explanationEl = document.getElementById('shortfallExplanation');
        if (combinedDiff < 0) {
            const absCombined = Math.abs(combinedDiff);
            const explainedByCredit = Math.min(absCombined, parseFloat(creditSales) || 0);
            const remaining = absCombined - explainedByCredit;

            if (explainedByCredit === absCombined && explainedByCredit > 0) {
                explanationEl.innerText = `Shortfall fully explained by credit sales: MWK ${explainedByCredit.toLocaleString()}`;
            } else if (explainedByCredit > 0) {
                explanationEl.innerText = `Credit sales explain MWK ${explainedByCredit.toLocaleString()}; Remaining shortage MWK ${remaining.toLocaleString()}`;
            } else {
                explanationEl.innerText = `Shortfall not explained by credit sales (Credit sales: MWK ${parseFloat(creditSales || 0).toLocaleString()}).`;
            }
            explanationEl.style.display = 'block';
        } else {
            explanationEl.innerText = '';
            explanationEl.style.display = 'none';
        }
    };

    cashInput.addEventListener('input', updatePreview);
    electronicInput.addEventListener('input', updatePreview);
});
</script>
@endsection
