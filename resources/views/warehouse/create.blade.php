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
                <p class="text-muted small mb-0">Four simple steps — costs and prices are calculated for you.</p>
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
                                       value="{{ old('conversion_factor', 24) }}" required min="1" step="1">
                                <div class="helper-text" id="conversion_hint">How many bottles in 1 crate?</div>
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
                        <p class="small text-muted mb-3">Set the selling price per {{ old('base_unit', 'bottle') }} for each location. Sellers will see these prices in stock entry.</p>
                        <div class="table-responsive">
                            <table class="table table-sm branch-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Branch</th>
                                        <th style="width:180px">Selling Price (MWK)</th>
                                        <th>Profit per Unit</th>
                                        <th style="width:90px">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bars as $bar)
                                    <tr>
                                        <td class="fw-semibold">{{ $bar->name }}</td>
                                        <td>
                                            <input type="number" name="bar_selling_prices[{{ $bar->id }}]"
                                                   class="form-control form-control-sm bar-selling-price"
                                                   data-bar-id="{{ $bar->id }}" placeholder="0" min="0" step="0.01">
                                        </td>
                                        <td>
                                            <span class="fw-semibold" id="profit-{{ $bar->id }}">—</span>
                                            <span class="small text-muted" id="profit-percent-{{ $bar->id }}"></span>
                                        </td>
                                        <td><span class="badge bg-light text-dark" id="profit-badge-{{ $bar->id }}">—</span></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- STEP 4: Additional units (after branch pricing) --}}
                    <div class="step-card">
                        <div class="step-title"><span class="step-number">4</span>Other ways to sell? <span class="fw-normal text-muted">(optional)</span></div>
                        <p class="small text-muted mb-2">e.g. 6-pack, 4-pack — these appear in seller stock entry with their own prices.</p>
                        <div id="additionalUnitsContainer"></div>
                        <button type="button" id="addUnitBtn" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-plus-lg me-1"></i>Add Selling Unit
                        </button>
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

<script>
const BARS = @json($bars->map(function ($b) { return ['id' => $b->id, 'name' => $b->name]; })->values());
const UNIT_PRESETS = {
    '6 Pack': 6,
    '4 Pack': 4,
    '12 Pack': 12,
    'Half Crate': 12,
    'Custom': 0
};

const purchaseUnitInput = document.getElementById('purchase_unit');
const quantityPurchasedInput = document.getElementById('quantity_purchased');
const totalPurchaseCostInput = document.getElementById('total_purchase_cost');
const conversionFactorInput = document.getElementById('conversion_factor');
const quantityInput = document.getElementById('quantity');
const baseUnitInput = document.getElementById('base_unit');
const conversionLabel = document.getElementById('conversion_label');
const conversionHint = document.getElementById('conversion_hint');

function getBaseUnitLabel() {
    return baseUnitInput.value || 'Unit';
}

function updateConversionLabels() {
    const base = getBaseUnitLabel().toLowerCase() + 's';
    const purchase = purchaseUnitInput.value || 'purchase unit';
    conversionLabel.textContent = base.charAt(0).toUpperCase() + base.slice(1) + ' per ' + purchase + ' *';
    conversionHint.textContent = 'How many ' + base + ' in 1 ' + purchase + '?';
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

    document.querySelectorAll('.bar-selling-price').forEach(input => {
        const barId = input.dataset.barId;
        const sellingPrice = parseFloat(input.value) || 0;
        const profit = sellingPrice - costPerBaseUnit;
        const profitPercent = costPerBaseUnit > 0 ? (profit / costPerBaseUnit) * 100 : 0;
        const profitEl = document.getElementById('profit-' + barId);
        const percentEl = document.getElementById('profit-percent-' + barId);
        const badge = document.getElementById('profit-badge-' + barId);

        if (sellingPrice > 0) {
            profitEl.textContent = 'MWK ' + profit.toFixed(0);
            percentEl.textContent = ' (' + profitPercent.toFixed(0) + '% markup)';
            profitEl.className = 'fw-semibold ' + (profitPercent >= 10 ? 'profit-positive' : profitPercent >= 0 ? 'profit-low' : 'profit-negative');
            if (profitPercent < 0) { badge.className = 'badge bg-danger'; badge.textContent = 'Loss'; }
            else if (profitPercent < 10) { badge.className = 'badge bg-warning text-dark'; badge.textContent = 'Low'; }
            else { badge.className = 'badge bg-success'; badge.textContent = 'Good'; }
        } else {
            profitEl.textContent = '—';
            percentEl.textContent = '';
            badge.className = 'badge bg-light text-dark';
            badge.textContent = '—';
        }
    });
}

function autoCalculateQuantity() {
    const qty = (parseFloat(quantityPurchasedInput.value) || 0) * (parseFloat(conversionFactorInput.value) || 1);
    quantityInput.value = qty;
}

[purchaseUnitInput, quantityPurchasedInput, totalPurchaseCostInput, conversionFactorInput, baseUnitInput].forEach(el => {
    el.addEventListener('input', () => { updateConversionLabels(); calculateAll(); autoCalculateQuantity(); });
    el.addEventListener('change', () => { updateConversionLabels(); calculateAll(); autoCalculateQuantity(); });
});
document.querySelectorAll('.bar-selling-price').forEach(el => el.addEventListener('input', calculateAll));

updateConversionLabels();
calculateAll();
autoCalculateQuantity();

// Additional units — expanded immediately, preset dropdown
const additionalUnitsContainer = document.getElementById('additionalUnitsContainer');
let additionalUnitIndex = 0;

function createUnitRow(index) {
    const wrapper = document.createElement('div');
    wrapper.className = 'unit-block';
    wrapper.dataset.index = index;

    const presetOptions = Object.keys(UNIT_PRESETS).map(k =>
        `<option value="${k}">${k}</option>`
    ).join('');

    wrapper.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <strong class="text-dark">Selling Unit #${index + 1}</strong>
            <button type="button" class="btn btn-sm btn-outline-danger remove-unit-btn">Remove</button>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Unit Type *</label>
                <select class="form-select unit-preset-select" data-index="${index}">
                    <option value="">Choose a unit...</option>
                    ${presetOptions}
                </select>
            </div>
            <div class="col-md-4 unit-name-col">
                <label class="form-label">Unit Name *</label>
                <input type="text" name="additional_units[${index}][unit_name]" class="form-control unit-name-input" data-index="${index}" placeholder="e.g. 6 Pack" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Contains (base units) *</label>
                <input type="number" name="additional_units[${index}][conversion_factor]" class="form-control unit-cf-input" data-index="${index}" value="6" min="1" required>
                <div class="helper-text">How many ${getBaseUnitLabel().toLowerCase()}s in 1 of this unit</div>
            </div>
        </div>
        <div class="mt-3 pt-3 border-top">
            <label class="form-label mb-2">Selling Price per Branch (MWK)</label>
            <div class="row g-2">
                ${BARS.map(bar => `
                    <div class="col-md-6 col-lg-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text" style="min-width:110px;font-size:0.75rem">${bar.name}</span>
                            <input type="number" name="additional_units[${index}][bar_selling_prices][${bar.id}]"
                                   class="form-control" placeholder="Price" min="0" step="0.01">
                        </div>
                    </div>
                `).join('')}
            </div>
            <div class="helper-text mt-1">Leave blank to use base unit price × conversion factor</div>
        </div>
    `;

    const presetSelect = wrapper.querySelector('.unit-preset-select');
    const nameInput = wrapper.querySelector('.unit-name-input');
    const cfInput = wrapper.querySelector('.unit-cf-input');
    const nameCol = wrapper.querySelector('.unit-name-col');

    presetSelect.addEventListener('change', () => {
        const preset = presetSelect.value;
        if (!preset) return;
        if (preset === 'Custom') {
            nameInput.value = '';
            nameInput.readOnly = false;
            nameCol.style.display = '';
            cfInput.value = 1;
        } else {
            nameInput.value = preset;
            nameInput.readOnly = true;
            cfInput.value = UNIT_PRESETS[preset];
        }
    });

    wrapper.querySelector('.remove-unit-btn').addEventListener('click', () => wrapper.remove());
    return wrapper;
}

document.getElementById('addUnitBtn').addEventListener('click', () => {
    const row = createUnitRow(additionalUnitIndex++);
    additionalUnitsContainer.appendChild(row);
    const preset = row.querySelector('.unit-preset-select');
    preset.value = '6 Pack';
    preset.dispatchEvent(new Event('change'));
    preset.focus();
});
</script>
@endsection
