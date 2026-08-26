@extends('layouts.app')

@section('content')
@php
    $pageTitle = 'Restock Item';
    $restockConversionFactor = old('conversion_factor', $warehouseStock->resolvePurchaseConversionFactor());
    $baseUnitName = $warehouseStock->units->firstWhere('is_base_unit', true)?->unit_name ?? 'Bottle';
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
                <h1 class="h3 fw-bold mb-1 text-dark">Restock {{ $warehouseStock->item_name }}</h1>
                <p class="text-muted small mb-0">Add new stock to existing inventory. Current stock: {{ $warehouseStock->quantity }}</p>
            </div>
            <a href="{{ route('warehouse.show', $warehouseStock) }}" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="bi bi-arrow-left me-2"></i>Back to Item
            </a>
        </div>
    </div>

    <div class="px-4">
        <div class="card form-card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('warehouse.processRestock', $warehouseStock) }}">
                    @csrf
                    
                    <!-- Purchase Information -->
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
                                <input type="number" name="quantity_purchased" id="quantity_purchased" class="form-control @error('quantity_purchased') is-invalid @enderror" value="{{ old('quantity_purchased') }}" required min="1" placeholder="e.g., 3">
                                @error('quantity_purchased')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="helper-text">Number of purchase units bought</div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Total Purchase Cost (MWK) *</label>
                                <input type="number" name="total_purchase_cost" id="total_purchase_cost" class="form-control @error('total_purchase_cost') is-invalid @enderror" value="{{ old('total_purchase_cost') }}" required min="0" step="0.01" placeholder="e.g., 108000">
                                @error('total_purchase_cost')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="helper-text">Total amount paid for this purchase</div>
                            </div>
                        </div>
                    </div>

                    <!-- Units & Conversions -->
                    <div class="section-card">
                        <div class="section-title">
                            <i class="bi bi-calculator me-2"></i>Units & Conversions
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Conversion Factor *</label>
                                <input type="number" name="conversion_factor" id="conversion_factor" class="form-control" value="{{ $restockConversionFactor }}" required min="1" step="1" placeholder="e.g., 24">
                                <div class="helper-text" id="conversion_hint">How many {{ strtolower($baseUnitName) }}s in 1 {{ old('purchase_unit', $warehouseStock->purchase_unit) }}?</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Supplier</label>
                                <input type="text" name="supplier" class="form-control @error('supplier') is-invalid @enderror" value="{{ old('supplier') }}" placeholder="e.g., ABC Distributors">
                                @error('supplier')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Live Calculations -->
                        <div class="calculation-box">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="calculation-label">Total Base Units to Add</div>
                                    <div class="calculation-value" id="totalBaseUnits">0</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="calculation-label">Cost Per Base Unit</div>
                                    <div class="calculation-value highlight" id="costPerBaseUnit">MWK 0.00</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="calculation-label">New Total Stock</div>
                                    <div class="calculation-value" id="newTotalStock">{{ $warehouseStock->quantity }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="section-card">
                        <div class="section-title">
                            <i class="bi bi-info-circle me-2"></i>Additional Information
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Reference Number</label>
                                <input type="text" name="reference_number" class="form-control @error('reference_number') is-invalid @enderror" value="{{ old('reference_number') }}" placeholder="e.g., INV-2024-001">
                                @error('reference_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="helper-text">Invoice or reference number</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="1" placeholder="Add any additional notes...">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('warehouse.show', $warehouseStock) }}" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="bi bi-check-lg me-2"></i>Restock Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
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
const BASE_UNIT_NAME = @json($baseUnitName);

// Live calculation logic
const purchaseUnitInput = document.getElementById('purchase_unit');
const quantityPurchasedInput = document.getElementById('quantity_purchased');
const totalPurchaseCostInput = document.getElementById('total_purchase_cost');
const conversionFactorInput = document.getElementById('conversion_factor');
const conversionHint = document.getElementById('conversion_hint');
let conversionManuallyEdited = false;

function syncConversionFactor(forcePreset = false) {
    const purchase = purchaseUnitInput.value;
    if (purchase && purchase === BASE_UNIT_NAME) {
        conversionFactorInput.value = 1;
        conversionFactorInput.readOnly = true;
        return;
    }
    conversionFactorInput.readOnly = false;
    if (forcePreset || (!conversionManuallyEdited && PURCHASE_UNIT_DEFAULTS[purchase])) {
        conversionFactorInput.value = PURCHASE_UNIT_DEFAULTS[purchase];
    }
    if (conversionHint) {
        conversionHint.textContent = 'How many ' + BASE_UNIT_NAME.toLowerCase() + 's in 1 ' + (purchase || 'unit') + '?';
    }
}

// Calculation displays
const totalBaseUnitsDisplay = document.getElementById('totalBaseUnits');
const costPerBaseUnitDisplay = document.getElementById('costPerBaseUnit');
const newTotalStockDisplay = document.getElementById('newTotalStock');

const currentStock = {{ $warehouseStock->quantity }};

function calculateAll() {
    const quantityPurchased = parseFloat(quantityPurchasedInput.value) || 0;
    const totalPurchaseCost = parseFloat(totalPurchaseCostInput.value) || 0;
    const conversionFactor = parseFloat(conversionFactorInput.value) || 1;

    // Calculate total base units to add
    const totalBaseUnits = quantityPurchased * conversionFactor;
    totalBaseUnitsDisplay.textContent = totalBaseUnits.toLocaleString();

    // Calculate cost per base unit
    const costPerBaseUnit = totalBaseUnits > 0 ? totalPurchaseCost / totalBaseUnits : 0;
    costPerBaseUnitDisplay.textContent = 'MWK ' + costPerBaseUnit.toFixed(2);

    // Calculate new total stock
    const newTotalStock = currentStock + totalBaseUnits;
    newTotalStockDisplay.textContent = newTotalStock.toLocaleString();
}

// Add event listeners
purchaseUnitInput.addEventListener('change', () => {
    syncConversionFactor(true);
    calculateAll();
});
quantityPurchasedInput.addEventListener('input', calculateAll);
totalPurchaseCostInput.addEventListener('input', calculateAll);
conversionFactorInput.addEventListener('input', () => {
    conversionManuallyEdited = true;
    calculateAll();
});

syncConversionFactor(false);
calculateAll();
</script>
@endsection
