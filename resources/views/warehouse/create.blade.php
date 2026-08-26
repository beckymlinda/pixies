@extends('layouts.app')

@section('content')
@php $pageTitle = 'Add Warehouse Item'; @endphp
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .form-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin: -1.5rem -1.5rem 2rem;
        padding: 1.5rem 2rem;
    }
    .form-card {
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        background: white;
    }
    .step-card {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.25rem;
    }
    .step-number {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #1e293b;
        color: white;
        font-size: 0.8rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.5rem;
    }
    .step-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 1rem;
    }
    .form-label {
        font-size: 0.78rem;
        font-weight: 600;
        color: #475569;
        margin-bottom: 0.35rem;
    }
    .form-control, .form-select {
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        padding: 0.65rem 0.85rem;
        font-size: 0.95rem;
    }
    .helper-text {
        font-size: 0.72rem;
        color: #94a3b8;
        margin-top: 0.2rem;
    }
    .calc-strip {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.85rem 1rem;
        display: flex;
        flex-wrap: wrap;
        gap: 1.5rem;
    }
    .calc-strip .item .lbl { font-size: 0.65rem; color: #94a3b8; text-transform: uppercase; }
    .calc-strip .item .val { font-weight: 700; color: #1e293b; }
    .calc-strip .item .val.hl { color: #2563eb; }
    .branch-table th {
        font-size: 0.7rem;
        text-transform: uppercase;
        color: #64748b;
        background: #f1f5f9;
        padding: 0.6rem 0.75rem;
    }
    .branch-table td { padding: 0.6rem 0.75rem; vertical-align: middle; }
    .unit-block {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 0.75rem;
    }
    .profit-positive { color: #059669; }
    .profit-negative { color: #dc2626; }
    .profit-low { color: #d97706; }
</style>

<div class="container-fluid p-0">
    <div class="form-header shadow-sm">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="h3 fw-bold mb-1">Add Warehouse Item</h1>
                <p class="text-muted small mb-0">Three simple steps — costs and prices are calculated for you.</p>
            </div>
            <a href="{{ route('warehouse.index') }}" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
        </div>
    </div>

    <div class="px-4 pb-4">
        <div class="card form-card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('warehouse.store') }}" id="warehouseForm">
                    @csrf

                    {{-- STEP 1: Item --}}
                    <div class="step-card">
                        <div class="step-title"><span class="step-number">1</span>What is the item?</div>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Item Name *</label>
                                <input type="text" name="item_name" class="form-control @error('item_name') is-invalid @enderror"
                                       value="{{ old('item_name') }}" required placeholder="e.g. Castle Lite">
                                @error('item_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Low Stock Alert *</label>
                                <input type="number" name="alert_quantity" class="form-control @error('alert_quantity') is-invalid @enderror"
                                       value="{{ old('alert_quantity', 10) }}" required min="0">
                                <div class="helper-text">Alert when stock falls below this</div>
                            </div>
                        </div>
                        <div class="mt-2">
                            <a class="small text-primary text-decoration-none" data-bs-toggle="collapse" href="#optionalFields">+ Optional: expiry date &amp; notes</a>
                            <div class="collapse mt-2" id="optionalFields">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Expiry Date</label>
                                        <input type="date" name="expiry_date" class="form-control" value="{{ old('expiry_date') }}" min="{{ now()->format('Y-m-d') }}">
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label">Notes</label>
                                        <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="Any extra notes">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- STEP 2: Purchase & units --}}
                    <div class="step-card">
                        <div class="step-title"><span class="step-number">2</span>What did you buy?</div>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Bought As *</label>
                                <select name="purchase_unit" id="purchase_unit" class="form-select" required>
                                    <option value="">Select...</option>
                                    @foreach(['Crate','Carton','Box','Case','Pack','Keg','Bottle','Other'] as $unit)
                                        <option value="{{ $unit }}" {{ old('purchase_unit') == $unit ? 'selected' : '' }}>{{ $unit }}</option>
                                    @endforeach
                                </select>
                                <div class="helper-text">The unit you paid for</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">How Many? *</label>
                                <input type="number" name="quantity_purchased" id="quantity_purchased" class="form-control"
                                       value="{{ old('quantity_purchased') }}" required min="1" placeholder="e.g. 3">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Total Paid (MWK) *</label>
                                <input type="number" name="total_purchase_cost" id="total_purchase_cost" class="form-control"
                                       value="{{ old('total_purchase_cost') }}" required min="0" step="0.01" placeholder="e.g. 108000">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Sell As (Base Unit) *</label>
                                <select name="base_unit" id="base_unit" class="form-select" required>
                                    <option value="Bottle" {{ old('base_unit', 'Bottle') == 'Bottle' ? 'selected' : '' }}>Bottle</option>
                                    <option value="Shot" {{ old('base_unit') == 'Shot' ? 'selected' : '' }}>Shot</option>
                                </select>
                                <div class="helper-text">Smallest unit sold at the bar</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" id="conversion_label">Bottles per purchase unit *</label>
                                <input type="number" name="conversion_factor" id="conversion_factor" class="form-control"
                                       value="{{ old('conversion_factor', 1) }}" required min="1" step="1">
                                <div class="helper-text" id="conversion_hint">How many bottles in 1 purchase unit?</div>
                            </div>
                            <div class="col-md-4 d-none" id="shots_per_bottle_row">
                                <label class="form-label">Shots per Bottle *</label>
                                <input type="number" name="shots_per_bottle" id="shots_per_bottle" class="form-control"
                                       value="{{ old('shots_per_bottle', 25) }}" min="1" step="1">
                                <div class="helper-text">For selling full bottles (e.g. 25)</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Total Stock (auto)</label>
                                <input type="number" name="quantity" id="quantity" class="form-control bg-light" required min="0" readonly>
                                <div class="helper-text">Calculated from purchase above</div>
                            </div>
                        </div>
                        <div class="calc-strip mt-3">
                            <div class="item"><div class="lbl">Total Units</div><div class="val" id="totalBaseUnits">0</div></div>
                            <div class="item"><div class="lbl">Cost per Unit</div><div class="val hl" id="costPerBaseUnit">MWK 0</div></div>
                            <div class="item"><div class="lbl">Cost per Purchase Unit</div><div class="val" id="costPerPurchaseUnit">MWK 0</div></div>
                        </div>
                    </div>

                    {{-- STEP 3: Branch prices --}}
                    <div class="step-card">
                        <div class="step-title"><span class="step-number">3</span>What price at each branch?</div>
                        <p class="small text-muted mb-3">Sellers choose <strong>Shot</strong> or <strong>Bottle</strong> when recording sales. Stock is deducted in base units automatically.</p>

                        {{-- Bottle-only pricing (base unit = Bottle) --}}
                        <div id="bottle-only-pricing">
                            <p class="small fw-semibold mb-2">Bottle price per branch</p>
                            <div class="table-responsive">
                                <table class="table table-sm branch-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Branch</th>
                                            <th style="width:180px">Bottle Price (MWK)</th>
                                            <th>Profit per Bottle</th>
                                            <th style="width:90px">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($bars as $bar)
                                        <tr class="bar-price-row" data-bar-id="{{ $bar->id }}">
                                            <td class="fw-semibold">{{ $bar->name }}</td>
                                            <td>
                                                <input type="number" name="bar_selling_prices[{{ $bar->id }}]"
                                                       class="form-control form-control-sm bar-selling-price"
                                                       data-bar-id="{{ $bar->id }}" data-unit-type="bottle" placeholder="0" min="0" step="0.01">
                                            </td>
                                            <td>
                                                <span class="fw-semibold bottle-profit" id="profit-{{ $bar->id }}">—</span>
                                                <span class="small text-muted" id="profit-percent-{{ $bar->id }}"></span>
                                            </td>
                                            <td><span class="badge bg-light text-dark" id="profit-badge-{{ $bar->id }}">—</span></td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Shot + Bottle pricing (base unit = Shot) --}}
                        <div id="shot-based-pricing" class="d-none">
                            <p class="small text-warning mb-3"><i class="bi bi-info-circle me-1"></i>Bar B sells shots; Liquor Shop sells bottles. Set both prices below.</p>

                            <p class="small fw-semibold mb-2">Shot price (Bar B)</p>
                            <div class="table-responsive mb-4">
                                <table class="table table-sm branch-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Branch</th>
                                            <th style="width:180px">Shot Price (MWK)</th>
                                            <th>Profit per Shot</th>
                                            <th style="width:90px">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($bars as $bar)
                                        <tr class="bar-price-row shot-price-row" data-bar-id="{{ $bar->id }}" data-supports-shot="{{ $bar->supportsWarehouseBaseUnit('Shot') ? '1' : '0' }}">
                                            <td class="fw-semibold">{{ $bar->name }}</td>
                                            <td>
                                                <input type="number" name="bar_selling_prices[{{ $bar->id }}]"
                                                       class="form-control form-control-sm bar-selling-price"
                                                       data-bar-id="{{ $bar->id }}" data-unit-type="shot" placeholder="0" min="0" step="0.01">
                                            </td>
                                            <td>
                                                <span class="fw-semibold" id="shot-profit-{{ $bar->id }}">—</span>
                                                <span class="small text-muted" id="shot-profit-percent-{{ $bar->id }}"></span>
                                            </td>
                                            <td><span class="badge bg-light text-dark" id="shot-profit-badge-{{ $bar->id }}">—</span></td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <p class="small fw-semibold mb-2">Bottle price per branch</p>
                            <div class="table-responsive">
                                <table class="table table-sm branch-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Branch</th>
                                            <th style="width:180px">Bottle Price (MWK)</th>
                                            <th>Profit per Bottle</th>
                                            <th style="width:90px">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($bars as $bar)
                                        <tr class="bottle-price-row" data-bar-id="{{ $bar->id }}">
                                            <td class="fw-semibold">{{ $bar->name }}</td>
                                            <td>
                                                <input type="number" name="bottle_bar_selling_prices[{{ $bar->id }}]"
                                                       class="form-control form-control-sm bottle-bar-selling-price"
                                                       data-bar-id="{{ $bar->id }}" placeholder="0" min="0" step="0.01">
                                            </td>
                                            <td>
                                                <span class="fw-semibold" id="bottle-profit-{{ $bar->id }}">—</span>
                                                <span class="small text-muted" id="bottle-profit-percent-{{ $bar->id }}"></span>
                                            </td>
                                            <td><span class="badge bg-light text-dark" id="bottle-profit-badge-{{ $bar->id }}">—</span></td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-3">
                        <a href="{{ route('warehouse.index') }}" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="bi bi-check-lg me-1"></i>Save Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@php
    $barsForJs = $bars->map(fn ($b) => [
        'id' => $b->id,
        'name' => $b->name,
        'supportsShot' => $b->supportsWarehouseBaseUnit('Shot'),
    ])->values();
@endphp

<script>
const BARS = @json($barsForJs);
const PURCHASE_UNIT_DEFAULTS = {
    Crate: 24,
    Carton: 24,
    Case: 24,
    Box: 12,
    Pack: 6,
    Keg: 1,
    Bottle: 1,
    Shot: 1,
    Other: 1
};
const UNIT_PRESETS = {};

const purchaseUnitInput = document.getElementById('purchase_unit');
const quantityPurchasedInput = document.getElementById('quantity_purchased');
const totalPurchaseCostInput = document.getElementById('total_purchase_cost');
const conversionFactorInput = document.getElementById('conversion_factor');
const quantityInput = document.getElementById('quantity');
const baseUnitInput = document.getElementById('base_unit');
const shotsPerBottleInput = document.getElementById('shots_per_bottle');
const shotsPerBottleRow = document.getElementById('shots_per_bottle_row');
const conversionLabel = document.getElementById('conversion_label');
const conversionHint = document.getElementById('conversion_hint');
const bottleOnlyPricing = document.getElementById('bottle-only-pricing');
const shotBasedPricing = document.getElementById('shot-based-pricing');
let conversionManuallyEdited = false;

function syncConversionFactor(forcePreset = false) {
    const purchase = purchaseUnitInput.value;
    const base = baseUnitInput.value;
    if (purchase && base && purchase === base) {
        conversionFactorInput.value = 1;
        conversionFactorInput.readOnly = true;
        conversionFactorInput.classList.add('bg-light');
        return;
    }
    conversionFactorInput.readOnly = false;
    conversionFactorInput.classList.remove('bg-light');
    if (forcePreset || (!conversionManuallyEdited && PURCHASE_UNIT_DEFAULTS[purchase])) {
        if (base === 'Shot' && purchase === 'Bottle') {
            conversionFactorInput.value = 25;
        } else {
            conversionFactorInput.value = PURCHASE_UNIT_DEFAULTS[purchase];
        }
    }
}

function updateUnitsFields() {
    const baseUnit = baseUnitInput.value;
    const purchase = purchaseUnitInput.value;
    const isShotBase = baseUnit === 'Shot';
    const buyingBottles = purchase === 'Bottle';

    if (shotsPerBottleRow) {
        shotsPerBottleRow.classList.toggle('d-none', !isShotBase || buyingBottles);
    }
    if (shotsPerBottleInput) {
        shotsPerBottleInput.required = isShotBase && !buyingBottles;
        if (isShotBase && buyingBottles) {
            shotsPerBottleInput.removeAttribute('name');
        } else if (isShotBase) {
            shotsPerBottleInput.setAttribute('name', 'shots_per_bottle');
        }
    }

    updateConversionLabels();
}

function updatePricingSections() {
    const baseUnit = baseUnitInput.value;
    const isShotBase = baseUnit === 'Shot';

    bottleOnlyPricing.classList.toggle('d-none', isShotBase);
    shotBasedPricing.classList.toggle('d-none', !isShotBase);

    bottleOnlyPricing.querySelectorAll('input, select, textarea').forEach(el => {
        el.disabled = isShotBase;
    });
    shotBasedPricing.querySelectorAll('input, select, textarea').forEach(el => {
        el.disabled = !isShotBase;
    });

    document.querySelectorAll('.shot-price-row').forEach(row => {
        const supportsShot = row.dataset.supportsShot === '1';
        row.style.display = isShotBase && !supportsShot ? 'none' : '';
        if (isShotBase && !supportsShot) {
            const input = row.querySelector('.bar-selling-price');
            if (input) input.value = '';
        }
    });

    calculateAll();
}

function updateProfitDisplay(prefix, barId, sellingPrice, unitCost) {
    const profitEl = document.getElementById(prefix + 'profit-' + barId);
    const percentEl = document.getElementById(prefix + 'profit-percent-' + barId);
    const badge = document.getElementById(prefix + 'profit-badge-' + barId);
    if (!profitEl || !badge) return;

    const profit = sellingPrice - unitCost;
    const profitPercent = unitCost > 0 ? (profit / unitCost) * 100 : 0;

    if (sellingPrice > 0) {
        profitEl.textContent = 'MWK ' + profit.toFixed(0);
        if (percentEl) percentEl.textContent = ' (' + profitPercent.toFixed(0) + '% markup)';
        profitEl.className = 'fw-semibold ' + (profitPercent >= 10 ? 'profit-positive' : profitPercent >= 0 ? 'profit-low' : 'profit-negative');
        if (profitPercent < 0) { badge.className = 'badge bg-danger'; badge.textContent = 'Loss'; }
        else if (profitPercent < 10) { badge.className = 'badge bg-warning text-dark'; badge.textContent = 'Low'; }
        else { badge.className = 'badge bg-success'; badge.textContent = 'Good'; }
    } else {
        profitEl.textContent = '—';
        if (percentEl) percentEl.textContent = '';
        badge.className = 'badge bg-light text-dark';
        badge.textContent = '—';
    }
}

function getBaseUnitLabel() {
    return baseUnitInput.value || 'Unit';
}

function updateConversionLabels() {
    const base = getBaseUnitLabel();
    const baseLower = base.toLowerCase() + 's';
    const purchase = purchaseUnitInput.value || 'purchase unit';
    const isShotBase = base === 'Shot';
    const buyingBottles = purchase === 'Bottle';

    if (isShotBase && buyingBottles) {
        conversionLabel.textContent = 'Shots per Bottle *';
        conversionHint.textContent = 'How many shots in 1 bottle?';
    } else {
        conversionLabel.textContent = baseLower.charAt(0).toUpperCase() + baseLower.slice(1) + ' per ' + purchase + ' *';
        conversionHint.textContent = 'How many ' + baseLower + ' in 1 ' + purchase + '?';
    }
}

function calculateAll() {
    const quantityPurchased = parseFloat(quantityPurchasedInput.value) || 0;
    const totalPurchaseCost = parseFloat(totalPurchaseCostInput.value) || 0;
    const conversionFactor = parseFloat(conversionFactorInput.value) || 1;
    const totalBaseUnits = quantityPurchased * conversionFactor;
    const costPerBaseUnit = totalBaseUnits > 0 ? totalPurchaseCost / totalBaseUnits : 0;
    const costPerPurchaseUnit = quantityPurchased > 0 ? totalPurchaseCost / quantityPurchased : 0;

    document.getElementById('totalBaseUnits').textContent = totalBaseUnits.toLocaleString();
    document.getElementById('costPerBaseUnit').textContent = 'MWK ' + costPerBaseUnit.toFixed(0);
    document.getElementById('costPerPurchaseUnit').textContent = 'MWK ' + costPerPurchaseUnit.toFixed(0);

    const shotsPerBottle = (baseUnitInput.value === 'Shot' && purchaseUnitInput.value === 'Bottle')
        ? (parseFloat(conversionFactorInput.value) || 1)
        : (parseFloat(shotsPerBottleInput?.value) || 25);
    const costPerBottle = costPerBaseUnit * shotsPerBottle;

    document.querySelectorAll('.bar-selling-price').forEach(input => {
        const barId = input.dataset.barId;
        const sellingPrice = parseFloat(input.value) || 0;
        const prefix = input.dataset.unitType === 'shot' ? 'shot-' : '';
        updateProfitDisplay(prefix, barId, sellingPrice, costPerBaseUnit);
    });

    document.querySelectorAll('.bottle-bar-selling-price').forEach(input => {
        const barId = input.dataset.barId;
        const sellingPrice = parseFloat(input.value) || 0;
        updateProfitDisplay('bottle-', barId, sellingPrice, costPerBottle);
    });

    document.querySelectorAll('#bottle-only-pricing .bar-selling-price').forEach(input => {
        const barId = input.dataset.barId;
        const sellingPrice = parseFloat(input.value) || 0;
        updateProfitDisplay('', barId, sellingPrice, costPerBaseUnit);
    });
}

function autoCalculateQuantity() {
    const qty = (parseFloat(quantityPurchasedInput.value) || 0) * (parseFloat(conversionFactorInput.value) || 1);
    quantityInput.value = qty;
}

[purchaseUnitInput, quantityPurchasedInput, totalPurchaseCostInput, baseUnitInput].forEach(el => {
    el.addEventListener('input', () => { updateUnitsFields(); updatePricingSections(); calculateAll(); autoCalculateQuantity(); });
    el.addEventListener('change', () => {
        if (el === purchaseUnitInput || el === baseUnitInput) {
            syncConversionFactor(el === purchaseUnitInput);
        }
        updateUnitsFields();
        updatePricingSections();
        calculateAll();
        autoCalculateQuantity();
    });
});
if (shotsPerBottleInput) {
    shotsPerBottleInput.addEventListener('input', calculateAll);
}
conversionFactorInput.addEventListener('input', () => {
    if (!conversionFactorInput.readOnly) {
        conversionManuallyEdited = true;
    }
    calculateAll();
    autoCalculateQuantity();
});
conversionFactorInput.addEventListener('change', () => {
    calculateAll();
    autoCalculateQuantity();
});
document.querySelectorAll('.bar-selling-price, .bottle-bar-selling-price').forEach(el => el.addEventListener('input', calculateAll));

syncConversionFactor(true);
updateUnitsFields();
updatePricingSections();
calculateAll();
autoCalculateQuantity();
</script>
@endsection
