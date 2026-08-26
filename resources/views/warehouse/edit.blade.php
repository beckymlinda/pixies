@extends('layouts.app')

@section('content')
@php 
    $pageTitle = 'Edit Warehouse Item';
    $savedConversionFactor = old('conversion_factor', $warehouseStock->resolvePurchaseConversionFactor());
@endphp
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .form-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: -1.5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }
    .form-card {
        border-radius: 16px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: white;
        overflow: hidden;
    }
    .section-card {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    .section-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: #1e293b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #e2e8f0;
    }
    .form-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
    }
    .form-control {
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
    }
    .form-control:focus {
        border-color: var(--pixies-primary);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    .calculation-box {
        background: white;
        border-radius: 8px;
        padding: 1rem;
        border: 1px solid #e2e8f0;
        margin-top: 1rem;
    }
    .calculation-label {
        font-size: 0.75rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.25rem;
    }
    .calculation-value {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1e293b;
    }
    .calculation-value.highlight {
        color: #3b82f6;
    }
    .profit-positive {
        color: #10b981;
    }
    .profit-negative {
        color: #ef4444;
    }
    .profit-low {
        color: #f59e0b;
    }
    .unit-row {
        background: white;
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 1rem;
        border: 1px solid #e2e8f0;
    }
    .bar-price-row {
        background: white;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 0.75rem;
        border: 1px solid #e2e8f0;
    }
    .helper-text {
        font-size: 0.75rem;
        color: #64748b;
        margin-top: 0.25rem;
    }
</style>

<div class="container-fluid p-0">
    <div class="form-header shadow-sm">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h1 class="h3 fw-bold mb-1 text-dark">Edit Warehouse Item</h1>
                <p class="text-muted small mb-0">Edit {{ $warehouseStock->item_name }} in your warehouse inventory.</p>
            </div>
            <a href="{{ route('warehouse.index') }}" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="bi bi-arrow-left me-2"></i>Back to List
            </a>
        </div>
    </div>

    <div class="px-4">
        <div class="card form-card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('warehouse.update', $warehouseStock) }}" id="warehouseForm">
                    @csrf
                    @method('PUT')
                    
                    <!-- SECTION 1: Item Information -->
                    <div class="section-card">
                        <div class="section-title">
                            <i class="bi bi-box-seam me-2"></i>Item Information
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Item Name *</label>
                                <input type="text" name="item_name" class="form-control @error('item_name') is-invalid @enderror" value="{{ old('item_name', $warehouseStock->item_name) }}" required placeholder="e.g., Castle Lite">
                                @error('item_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Alert Quantity *</label>
                                <input type="number" name="alert_quantity" class="form-control @error('alert_quantity') is-invalid @enderror" value="{{ old('alert_quantity', $warehouseStock->alert_quantity) }}" required min="0" placeholder="Alert when stock falls below this">
                                @error('alert_quantity')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="helper-text">You will be alerted when stock reaches this quantity</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Expiry Date</label>
                                <input type="date" name="expiry_date" class="form-control @error('expiry_date') is-invalid @enderror" value="{{ old('expiry_date', $warehouseStock->expiry_date ? $warehouseStock->expiry_date->format('Y-m-d') : '') }}" min="{{ now()->format('Y-m-d') }}">
                                @error('expiry_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="helper-text">Optional - leave blank if item doesn't expire</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="1" placeholder="Add any additional notes...">{{ old('notes', $warehouseStock->notes) }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: Purchase Information -->
                    <div class="section-card">
                        <div class="section-title">
                            <i class="bi bi-cart me-2"></i>Purchase Information
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Purchase Unit *</label>
                                <select name="purchase_unit" id="purchase_unit" class="form-control @error('purchase_unit') is-invalid @enderror" required>
                                    <option value="">Select unit...</option>
                                    <option value="Crate" {{ old('purchase_unit', $warehouseStock->purchase_unit) == 'Crate' ? 'selected' : '' }}>Crate</option>
                                    <option value="Carton" {{ old('purchase_unit', $warehouseStock->purchase_unit) == 'Carton' ? 'selected' : '' }}>Carton</option>
                                    <option value="Box" {{ old('purchase_unit', $warehouseStock->purchase_unit) == 'Box' ? 'selected' : '' }}>Box</option>
                                    <option value="Bottle" {{ old('purchase_unit', $warehouseStock->purchase_unit) == 'Bottle' ? 'selected' : '' }}>Bottle</option>
                                    <option value="Keg" {{ old('purchase_unit', $warehouseStock->purchase_unit) == 'Keg' ? 'selected' : '' }}>Keg</option>
                                    <option value="Case" {{ old('purchase_unit', $warehouseStock->purchase_unit) == 'Case' ? 'selected' : '' }}>Case</option>
                                    <option value="Pack" {{ old('purchase_unit', $warehouseStock->purchase_unit) == 'Pack' ? 'selected' : '' }}>Pack</option>
                                    <option value="Other" {{ old('purchase_unit', $warehouseStock->purchase_unit) == 'Other' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('purchase_unit')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="helper-text">The unit you purchased the item in</div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Quantity Purchased *</label>
                                <input type="number" name="quantity_purchased" id="quantity_purchased" class="form-control @error('quantity_purchased') is-invalid @enderror" value="{{ old('quantity_purchased', $warehouseStock->quantity_purchased) }}" required min="1" placeholder="e.g., 3">
                                @error('quantity_purchased')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="helper-text">Number of purchase units bought</div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Total Purchase Cost (MWK) *</label>
                                <input type="number" name="total_purchase_cost" id="total_purchase_cost" class="form-control @error('total_purchase_cost') is-invalid @enderror" value="{{ old('total_purchase_cost', $warehouseStock->total_purchase_cost) }}" required min="0" step="0.01" placeholder="e.g., 108000">
                                @error('total_purchase_cost')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="helper-text">Total amount paid for this purchase</div>
                            </div>
                        </div>
                    </div>

                    @php
                        $baseUnitModel = $warehouseStock->units->firstWhere('is_base_unit', true);
                        $baseUnitName = $baseUnitModel?->unit_name ?? 'Bottle';
                        $bottleUnitModel = $warehouseStock->getBottleSellingUnit();
                        $shotsPerBottle = old('shots_per_bottle', $bottleUnitModel?->conversion_factor ?? 25);
                        $showShotsPerBottleField = $baseUnitName === 'Shot' && $warehouseStock->purchase_unit !== 'Bottle';
                    @endphp

                    <!-- SECTION 3: Units & Conversions -->
                    <div class="section-card">
                        <div class="section-title">
                            <i class="bi bi-calculator me-2"></i>Units & Conversions
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Base Unit *</label>
                                <select name="base_unit" id="base_unit" class="form-control" required>
                                    <option value="Bottle" {{ old('base_unit', $baseUnitName) == 'Bottle' ? 'selected' : '' }}>Bottle</option>
                                    <option value="Shot" {{ old('base_unit', $baseUnitName) == 'Shot' ? 'selected' : '' }}>Shot</option>
                                </select>
                                <div class="helper-text">The smallest unit for selling (e.g., Bottle, Shot)</div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label" id="conversion_label">Conversion Factor *</label>
                                <input type="number" name="conversion_factor" id="conversion_factor" class="form-control" value="{{ $savedConversionFactor }}" required min="1" step="1" placeholder="e.g., 24">
                                <div class="helper-text" id="conversion_hint">How many {{ strtolower($baseUnitName) }}s in 1 {{ old('purchase_unit', $warehouseStock->purchase_unit) }}?</div>
                            </div>

                            <div class="col-md-4 mb-3 {{ $showShotsPerBottleField ? '' : 'd-none' }}" id="shots_per_bottle_row">
                                <label class="form-label">Shots per Bottle *</label>
                                <input type="number" name="shots_per_bottle" id="shots_per_bottle" class="form-control"
                                       value="{{ $shotsPerBottle }}" min="1" step="1">
                                <div class="helper-text">For selling full bottles (e.g. 25)</div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Stock Quantity (Base Units) *</label>
                                <input type="number" name="quantity" id="quantity" class="form-control" value="{{ old('quantity', $warehouseStock->quantity) }}" required min="0" placeholder="Current stock">
                                <div class="helper-text">Current on-hand stock in {{ strtolower($baseUnitName) }}s</div>
                            </div>
                        </div>

                        <!-- Live Calculations -->
                        <div class="calculation-box">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="calculation-label">Current Stock ({{ strtolower($baseUnitName) }}s)</div>
                                    <div class="calculation-value" id="totalBaseUnits">{{ number_format($warehouseStock->quantity) }}</div>
                                </div>
                                <div class="col-md-3">
                                    <div class="calculation-label">Cost Per Base Unit</div>
                                    <div class="calculation-value highlight" id="costPerBaseUnit">MWK 0.00</div>
                                </div>
                                <div class="col-md-3">
                                    <div class="calculation-label">Cost Per {{ old('purchase_unit', $warehouseStock->purchase_unit) }}</div>
                                    <div class="calculation-value" id="costPerPurchaseUnit">MWK 0.00</div>
                                </div>
                                <div class="col-md-3">
                                    <div class="calculation-label">Estimated Stock Value</div>
                                    <div class="calculation-value" id="estimatedStockValue">MWK 0.00</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Branch Selling Prices -->
                    <div class="section-card">
                        <div class="section-title">
                            <i class="bi bi-shop me-2"></i>Branch Selling Prices
                        </div>
                        <p class="small text-muted mb-3">Sellers choose <strong>Shot</strong> or <strong>Bottle</strong> when recording sales. Stock is deducted in base units automatically.</p>

                        <div id="bottle-only-pricing" class="{{ $baseUnitName === 'Shot' ? 'd-none' : '' }}">
                            <p class="small fw-semibold mb-2">Bottle price per branch</p>
                            <div class="row">
                                @foreach($bars as $bar)
                                    @php
                                        $existingPrice = $baseUnitModel
                                            ? $baseUnitModel->barPrices->firstWhere('bar_id', $bar->id)?->selling_price
                                            : null;
                                    @endphp
                                    <div class="col-md-6 mb-3 bar-price-row" data-bar-id="{{ $bar->id }}">
                                        <div class="bar-price-row">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <label class="form-label mb-0">{{ $bar->name }}</label>
                                                <span class="badge bg-light text-dark" id="profit-badge-{{ $bar->id }}">--</span>
                                            </div>
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <label class="small text-muted">Bottle Price (MWK)</label>
                                                    <input type="number" name="bar_selling_prices[{{ $bar->id }}]" class="form-control bar-selling-price" data-bar-id="{{ $bar->id }}" data-unit-type="bottle" value="{{ old('bar_selling_prices.' . $bar->id, $existingPrice) }}" placeholder="0.00" min="0" step="0.01">
                                                </div>
                                                <div class="col-6">
                                                    <label class="small text-muted">Profit</label>
                                                    <div class="calculation-value" id="profit-{{ $bar->id }}">MWK 0.00</div>
                                                    <div class="small profit-percentage" id="profit-percent-{{ $bar->id }}">0%</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div id="shot-based-pricing" class="{{ $baseUnitName === 'Shot' ? '' : 'd-none' }}">
                            <p class="small text-warning mb-3"><i class="bi bi-info-circle me-1"></i>Bar B sells shots; Liquor Shop sells bottles. Set both prices below.</p>

                            <p class="small fw-semibold mb-2">Shot price (Bar B)</p>
                            <div class="row mb-4">
                                @foreach($bars as $bar)
                                    @php
                                        $existingShotPrice = $baseUnitModel
                                            ? $baseUnitModel->barPrices->firstWhere('bar_id', $bar->id)?->selling_price
                                            : null;
                                    @endphp
                                    <div class="col-md-6 mb-3 bar-price-row shot-price-row" data-bar-id="{{ $bar->id }}" data-supports-shot="{{ $bar->supportsWarehouseBaseUnit('Shot') ? '1' : '0' }}">
                                        <div class="bar-price-row">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <label class="form-label mb-0">{{ $bar->name }}</label>
                                                <span class="badge bg-light text-dark" id="shot-profit-badge-{{ $bar->id }}">--</span>
                                            </div>
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <label class="small text-muted">Shot Price (MWK)</label>
                                                    <input type="number" name="bar_selling_prices[{{ $bar->id }}]" class="form-control bar-selling-price" data-bar-id="{{ $bar->id }}" data-unit-type="shot" value="{{ old('bar_selling_prices.' . $bar->id, $existingShotPrice) }}" placeholder="0.00" min="0" step="0.01">
                                                </div>
                                                <div class="col-6">
                                                    <label class="small text-muted">Profit</label>
                                                    <div class="calculation-value" id="shot-profit-{{ $bar->id }}">MWK 0.00</div>
                                                    <div class="small profit-percentage" id="shot-profit-percent-{{ $bar->id }}">0%</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <p class="small fw-semibold mb-2">Bottle price per branch</p>
                            <div class="row">
                                @foreach($bars as $bar)
                                    @php
                                        $existingBottlePrice = $bottleUnitModel
                                            ? $bottleUnitModel->barPrices->firstWhere('bar_id', $bar->id)?->selling_price
                                            : null;
                                    @endphp
                                    <div class="col-md-6 mb-3 bottle-price-row" data-bar-id="{{ $bar->id }}">
                                        <div class="bar-price-row">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <label class="form-label mb-0">{{ $bar->name }}</label>
                                                <span class="badge bg-light text-dark" id="bottle-profit-badge-{{ $bar->id }}">--</span>
                                            </div>
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <label class="small text-muted">Bottle Price (MWK)</label>
                                                    <input type="number" name="bottle_bar_selling_prices[{{ $bar->id }}]" class="form-control bottle-bar-selling-price" data-bar-id="{{ $bar->id }}" value="{{ old('bottle_bar_selling_prices.' . $bar->id, $existingBottlePrice) }}" placeholder="0.00" min="0" step="0.01">
                                                </div>
                                                <div class="col-6">
                                                    <label class="small text-muted">Profit</label>
                                                    <div class="calculation-value" id="bottle-profit-{{ $bar->id }}">MWK 0.00</div>
                                                    <div class="small profit-percentage" id="bottle-profit-percent-{{ $bar->id }}">0%</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('warehouse.index') }}" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="bi bi-check-lg me-2"></i>Update Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Live calculation logic
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

const purchaseUnitInput = document.getElementById('purchase_unit');
const quantityPurchasedInput = document.getElementById('quantity_purchased');
const totalPurchaseCostInput = document.getElementById('total_purchase_cost');
const conversionFactorInput = document.getElementById('conversion_factor');
const quantityInput = document.getElementById('quantity');
const baseUnitInput = document.getElementById('base_unit');
const shotsPerBottleInput = document.getElementById('shots_per_bottle');
const shotsPerBottleRow = document.getElementById('shots_per_bottle_row');
const bottleOnlyPricing = document.getElementById('bottle-only-pricing');
const shotBasedPricing = document.getElementById('shot-based-pricing');

// Calculation displays
const totalBaseUnitsDisplay = document.getElementById('totalBaseUnits');
const costPerBaseUnitDisplay = document.getElementById('costPerBaseUnit');
const costPerPurchaseUnitDisplay = document.getElementById('costPerPurchaseUnit');
const estimatedStockValueDisplay = document.getElementById('estimatedStockValue');

const conversionHint = document.getElementById('conversion_hint');
const conversionLabel = document.getElementById('conversion_label');
let conversionManuallyEdited = false;

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
    const profitDisplay = document.getElementById(prefix + 'profit-' + barId);
    const profitPercentDisplay = document.getElementById(prefix + 'profit-percent-' + barId);
    const profitBadge = document.getElementById(prefix + 'profit-badge-' + barId);
    if (!profitDisplay || !profitBadge) return;

    const profit = sellingPrice - unitCost;
    const profitPercent = unitCost > 0 ? (profit / unitCost) * 100 : 0;

    profitDisplay.textContent = 'MWK ' + profit.toFixed(2);
    if (profitPercentDisplay) profitPercentDisplay.textContent = profitPercent.toFixed(1) + '%';

    if (sellingPrice > 0) {
        if (profitPercent < 0) {
            profitBadge.className = 'badge bg-danger';
            profitBadge.textContent = 'Loss';
        } else if (profitPercent < 10) {
            profitBadge.className = 'badge bg-warning text-dark';
            profitBadge.textContent = 'Low Profit';
        } else {
            profitBadge.className = 'badge bg-success';
            profitBadge.textContent = 'Good';
        }
    } else {
        profitBadge.className = 'badge bg-light text-dark';
        profitBadge.textContent = '--';
    }

    profitDisplay.className = 'calculation-value ' + (profitPercent >= 0 ? (profitPercent >= 10 ? 'profit-positive' : 'profit-low') : 'profit-negative');
    if (profitPercentDisplay) {
        profitPercentDisplay.className = 'small profit-percentage ' + (profitPercent >= 0 ? (profitPercent >= 10 ? 'profit-positive' : 'profit-low') : 'profit-negative');
    }
}

function syncConversionFactor(forcePreset = false) {
    const purchase = purchaseUnitInput.value;
    const base = baseUnitInput.value;
    if (purchase && base && purchase === base) {
        conversionFactorInput.value = 1;
        conversionFactorInput.readOnly = true;
        return;
    }
    conversionFactorInput.readOnly = false;
    if (forcePreset || (!conversionManuallyEdited && PURCHASE_UNIT_DEFAULTS[purchase])) {
        if (base === 'Shot' && purchase === 'Bottle') {
            conversionFactorInput.value = 25;
        } else {
            conversionFactorInput.value = PURCHASE_UNIT_DEFAULTS[purchase];
        }
    }
}

function updateConversionLabels() {
    const base = baseUnitInput.value || 'unit';
    const baseLower = base.toLowerCase() + 's';
    const purchase = purchaseUnitInput.value || 'purchase unit';
    const isShotBase = base === 'Shot';
    const buyingBottles = purchase === 'Bottle';

    if (conversionLabel) {
        if (isShotBase && buyingBottles) {
            conversionLabel.textContent = 'Shots per Bottle *';
        } else {
            conversionLabel.textContent = baseLower.charAt(0).toUpperCase() + baseLower.slice(1) + ' per ' + purchase + ' *';
        }
    }
    if (conversionHint) {
        if (isShotBase && buyingBottles) {
            conversionHint.textContent = 'How many shots in 1 bottle?';
        } else {
            conversionHint.textContent = 'How many ' + baseLower + ' in 1 ' + purchase + '?';
        }
    }
}

function calculateAll() {
    const purchaseUnit = purchaseUnitInput.value || 'Unit';
    const quantityPurchased = parseFloat(quantityPurchasedInput.value) || 0;
    const totalPurchaseCost = parseFloat(totalPurchaseCostInput.value) || 0;
    const conversionFactor = parseFloat(conversionFactorInput.value) || 1;
    const quantity = parseFloat(quantityInput.value) || 0;

    totalBaseUnitsDisplay.textContent = quantity.toLocaleString();

    const purchaseTotalBaseUnits = quantityPurchased * conversionFactor;

    // Calculate cost per base unit from purchase info
    const costPerBaseUnit = purchaseTotalBaseUnits > 0 ? totalPurchaseCost / purchaseTotalBaseUnits : 0;
    costPerBaseUnitDisplay.textContent = 'MWK ' + costPerBaseUnit.toFixed(2);

    // Calculate cost per purchase unit
    const costPerPurchaseUnit = quantityPurchased > 0 ? totalPurchaseCost / quantityPurchased : 0;
    costPerPurchaseUnitDisplay.textContent = 'MWK ' + costPerPurchaseUnit.toFixed(2);

    // Update helper text via updateConversionLabels()

    // Calculate estimated stock value
    const estimatedStockValue = quantity * costPerBaseUnit;
    estimatedStockValueDisplay.textContent = 'MWK ' + estimatedStockValue.toFixed(2);

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

// Edit page: stock quantity is actual on-hand — do not overwrite from purchase info.

// Add event listeners
purchaseUnitInput.addEventListener('change', () => {
    syncConversionFactor(true);
    updateUnitsFields();
    updatePricingSections();
    calculateAll();
});
quantityPurchasedInput.addEventListener('input', calculateAll);
totalPurchaseCostInput.addEventListener('input', calculateAll);
conversionFactorInput.addEventListener('input', () => {
    conversionManuallyEdited = true;
    calculateAll();
});
conversionFactorInput.addEventListener('change', () => {
    conversionManuallyEdited = true;
    calculateAll();
});
quantityInput.addEventListener('input', calculateAll);
baseUnitInput.addEventListener('change', () => {
    syncConversionFactor(true);
    updateUnitsFields();
    updatePricingSections();
    calculateAll();
});

if (shotsPerBottleInput) {
    shotsPerBottleInput.addEventListener('input', calculateAll);
}

document.querySelectorAll('.bar-selling-price, .bottle-bar-selling-price').forEach(input => {
    input.addEventListener('input', calculateAll);
});

// Initial calculation — preserve saved conversion factor and stock quantity
conversionManuallyEdited = true;
updateUnitsFields();
updatePricingSections();
calculateAll();
</script>
@endsection
