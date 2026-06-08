@extends('layouts.app')

@section('content')
@php 
    $pageTitle = 'Edit Warehouse Item'; 
    $bars = \App\Models\Bar::orderBy('name')->get();
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

                    <!-- SECTION 3: Units & Conversions -->
                    <div class="section-card">
                        <div class="section-title">
                            <i class="bi bi-calculator me-2"></i>Units & Conversions
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Base Unit *</label>
                                <input type="text" name="base_unit" id="base_unit" class="form-control" value="{{ old('base_unit', 'Bottle') }}" required placeholder="e.g., Bottle">
                                <div class="helper-text">The smallest unit for selling (e.g., Bottle, Shot)</div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Conversion Factor *</label>
                                <input type="number" name="conversion_factor" id="conversion_factor" class="form-control" value="{{ old('conversion_factor', 24) }}" required min="1" step="1" placeholder="e.g., 24">
                                <div class="helper-text">How many base units in 1 {{ old('purchase_unit', $warehouseStock->purchase_unit) }}?</div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Stock Quantity (Base Units) *</label>
                                <input type="number" name="quantity" id="quantity" class="form-control" value="{{ old('quantity', $warehouseStock->quantity) }}" required min="0" placeholder="Auto-calculated">
                                <div class="helper-text">Total stock in base units</div>
                            </div>
                        </div>

                        <!-- Live Calculations -->
                        <div class="calculation-box">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="calculation-label">Total Base Units</div>
                                    <div class="calculation-value" id="totalBaseUnits">0</div>
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

                    <!-- SECTION 4: Additional Units -->
                    <div class="section-card">
                        <div class="section-title">
                            <i class="bi bi-list-ul me-2"></i>Additional Units
                        </div>

                        <div id="additionalUnitsContainer"></div>

                        <div class="d-flex justify-content-end mt-2">
                            <button type="button" id="addUnitBtn" class="btn btn-sm btn-outline-primary">Add Unit</button>
                        </div>
                        <div class="helper-text mt-2">Add alternative selling units (e.g., 6 pack) and set their conversion and default selling prices.</div>
                    </div>

                    <!-- SECTION 5: Branch Selling Prices -->
                    <div class="section-card">
                        <div class="section-title">
                            <i class="bi bi-shop me-2"></i>Branch Selling Prices
                        </div>
                        <div class="row">
                            @foreach($bars as $bar)
                                @php
                                    $existingPrice = null;
                                    $baseUnit = $warehouseStock->units()->where('is_base_unit', true)->first();
                                    if ($baseUnit) {
                                        $barPrice = $baseUnit->barPrices()->where('bar_id', $bar->id)->first();
                                        $existingPrice = $barPrice ? $barPrice->selling_price : null;
                                    }
                                @endphp
                                <div class="col-md-6 mb-3">
                                    <div class="bar-price-row">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label mb-0">{{ $bar->name }}</label>
                                            <span class="badge bg-light text-dark" id="profit-badge-{{ $bar->id }}">--</span>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <label class="small text-muted">Selling Price (MWK)</label>
                                                <input type="number" name="bar_selling_prices[{{ $bar->id }}]" class="form-control bar-selling-price" data-bar-id="{{ $bar->id }}" value="{{ old('bar_selling_prices.' . $bar->id, $existingPrice) }}" placeholder="0.00" min="0" step="0.01">
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
const purchaseUnitInput = document.getElementById('purchase_unit');
const quantityPurchasedInput = document.getElementById('quantity_purchased');
const totalPurchaseCostInput = document.getElementById('total_purchase_cost');
const conversionFactorInput = document.getElementById('conversion_factor');
const quantityInput = document.getElementById('quantity');
const baseUnitInput = document.getElementById('base_unit');

// Calculation displays
const totalBaseUnitsDisplay = document.getElementById('totalBaseUnits');
const costPerBaseUnitDisplay = document.getElementById('costPerBaseUnit');
const costPerPurchaseUnitDisplay = document.getElementById('costPerPurchaseUnit');
const estimatedStockValueDisplay = document.getElementById('estimatedStockValue');

function calculateAll() {
    const purchaseUnit = purchaseUnitInput.value || 'Unit';
    const quantityPurchased = parseFloat(quantityPurchasedInput.value) || 0;
    const totalPurchaseCost = parseFloat(totalPurchaseCostInput.value) || 0;
    const conversionFactor = parseFloat(conversionFactorInput.value) || 1;
    const quantity = parseFloat(quantityInput.value) || 0;

    // Calculate total base units
    const totalBaseUnits = quantityPurchased * conversionFactor;
    totalBaseUnitsDisplay.textContent = totalBaseUnits.toLocaleString();

    // Calculate cost per base unit
    const costPerBaseUnit = totalBaseUnits > 0 ? totalPurchaseCost / totalBaseUnits : 0;
    costPerBaseUnitDisplay.textContent = 'MWK ' + costPerBaseUnit.toFixed(2);

    // Calculate cost per purchase unit
    const costPerPurchaseUnit = quantityPurchased > 0 ? totalPurchaseCost / quantityPurchased : 0;
    costPerPurchaseUnitDisplay.textContent = 'MWK ' + costPerPurchaseUnit.toFixed(2);

    // Update helper text
    const helperText = conversionFactorInput.nextElementSibling;
    if (helperText) {
        helperText.textContent = `How many ${baseUnitInput.value}s in 1 ${purchaseUnit}?`;
    }

    // Calculate estimated stock value
    const estimatedStockValue = quantity * costPerBaseUnit;
    estimatedStockValueDisplay.textContent = 'MWK ' + estimatedStockValue.toFixed(2);

    // Calculate profits for each bar
    document.querySelectorAll('.bar-selling-price').forEach(input => {
        const barId = input.dataset.barId;
        const sellingPrice = parseFloat(input.value) || 0;
        const profit = sellingPrice - costPerBaseUnit;
        const profitPercent = costPerBaseUnit > 0 ? (profit / costPerBaseUnit) * 100 : 0;

        const profitDisplay = document.getElementById(`profit-${barId}`);
        const profitPercentDisplay = document.getElementById(`profit-percent-${barId}`);
        const profitBadge = document.getElementById(`profit-badge-${barId}`);

        profitDisplay.textContent = 'MWK ' + profit.toFixed(2);
        profitPercentDisplay.textContent = profitPercent.toFixed(1) + '%';

        // Update profit badge
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

        // Update profit display color
        profitDisplay.className = 'calculation-value ' + (profitPercent >= 0 ? (profitPercent >= 10 ? 'profit-positive' : 'profit-low') : 'profit-negative');
        profitPercentDisplay.className = 'small profit-percentage ' + (profitPercent >= 0 ? (profitPercent >= 10 ? 'profit-positive' : 'profit-low') : 'profit-negative');
    });
}

// Auto-calculate quantity when purchase info changes
function autoCalculateQuantity() {
    const quantityPurchased = parseFloat(quantityPurchasedInput.value) || 0;
    const conversionFactor = parseFloat(conversionFactorInput.value) || 1;
    quantityInput.value = quantityPurchased * conversionFactor;
}

// Add event listeners
purchaseUnitInput.addEventListener('change', () => {
    calculateAll();
    autoCalculateQuantity();
});
quantityPurchasedInput.addEventListener('input', () => {
    calculateAll();
    autoCalculateQuantity();
});
totalPurchaseCostInput.addEventListener('input', calculateAll);
conversionFactorInput.addEventListener('input', () => {
    calculateAll();
    autoCalculateQuantity();
});
quantityInput.addEventListener('input', calculateAll);
baseUnitInput.addEventListener('input', calculateAll);

document.querySelectorAll('.bar-selling-price').forEach(input => {
    input.addEventListener('input', calculateAll);
});

// Initial calculation
calculateAll();
autoCalculateQuantity();

// Additional units dynamic UI
const BARS = @json($bars->map(function($b){ return ['id' => $b->id, 'name' => $b->name]; })->toArray());
const additionalUnitsContainer = document.getElementById('additionalUnitsContainer');
const addUnitBtn = document.getElementById('addUnitBtn');
let additionalUnitIndex = 0;

// Load existing additional units
@php
    $existingUnits = $warehouseStock->units()->where('is_base_unit', false)->get()->map(function($unit) use ($bars) {
        $barPrices = [];
        foreach ($bars as $bar) {
            $barPrice = $unit->barPrices()->where('bar_id', $bar->id)->first();
            if ($barPrice) {
                $barPrices[$bar->id] = $barPrice->selling_price;
            }
        }
        return [
            'id' => $unit->id,
            'unit_name' => $unit->unit_name,
            'conversion_factor' => $unit->conversion_factor,
            'purchase_price' => $unit->purchase_price,
            'selling_price' => $unit->purchase_price, // Default selling price
            'bar_selling_prices' => $barPrices,
        ];
    })->toArray();
@endphp
const EXISTING_UNITS = @json($existingUnits);

function createUnitRow(index, data = {}) {
    const uniqueId = 'unit-' + index;
    const wrapper = document.createElement('div');
    wrapper.className = 'card border-0 shadow-sm mb-3';
    wrapper.dataset.index = index;
    if (data.id) {
        wrapper.dataset.unitId = data.id;
    }

    const unitName = data.unit_name || 'New Unit';
    
    wrapper.innerHTML = `
        <div class="card-header bg-light border-0">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-link text-decoration-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#${uniqueId}" aria-expanded="false">
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <strong id="unit-label-${index}" class="mb-0">${unitName}</strong>
                    <span class="badge bg-secondary" id="unit-cf-${index}">CF: 1</span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger remove-unit-btn">Remove</button>
            </div>
        </div>
        <div class="collapse" id="${uniqueId}">
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Unit Name *</label>
                        <input type="text" name="additional_units[${index}][unit_name]" class="form-control unit-name-input" data-index="${index}" value="${data.unit_name || ''}" placeholder="e.g., 6 pack" required>
                        <input type="hidden" name="additional_units[${index}][unit_id]" value="${data.id || ''}">
                        <div class="helper-text">The display name for this unit</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Conversion Factor *</label>
                        <input type="number" name="additional_units[${index}][conversion_factor]" class="form-control unit-cf-input" data-index="${index}" value="${data.conversion_factor || 1}" min="1" required>
                        <div class="helper-text">How many base units in 1 of this unit?</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Default Selling Price (MWK)</label>
                        <input type="number" name="additional_units[${index}][selling_price]" class="form-control" value="${data.selling_price || ''}" min="0" step="0.01">
                        <div class="helper-text">Optional - leave blank to use base price</div>
                    </div>
                </div>

                <div class="mt-3 pt-3 border-top">
                    <h6 class="mb-3"><i class="bi bi-shop me-2"></i>Per-Branch Selling Prices (Optional)</h6>
                    <div class="row g-2" id="bar-prices-${index}">
                        ${BARS.map(bar => `
                            <div class="col-md-6 col-lg-4">
                                <div class="card border-light bg-light">
                                    <div class="card-body p-2">
                                        <label class="form-label small fw-bold mb-2">
                                            <span class="badge bg-info text-dark">${bar.name}</span>
                                        </label>
                                        <input type="number" 
                                               name="additional_units[${index}][bar_selling_prices][${bar.id}]" 
                                               class="form-control form-control-sm" 
                                               value="${(data.bar_selling_prices && data.bar_selling_prices[bar.id]) ? data.bar_selling_prices[bar.id] : ''}" 
                                               placeholder="MWK" 
                                               min="0" 
                                               step="0.01">
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                    <div class="helper-text mt-2">Set custom selling prices for each branch. Leave blank to use default price.</div>
                </div>
            </div>
        </div>
    `;

    // Update label and CF display when inputs change
    const nameInput = wrapper.querySelector('.unit-name-input');
    const cfInput = wrapper.querySelector('.unit-cf-input');
    
    nameInput.addEventListener('input', () => {
        document.getElementById(`unit-label-${index}`).textContent = nameInput.value || 'New Unit';
    });
    
    cfInput.addEventListener('input', () => {
        document.getElementById(`unit-cf-${index}`).textContent = 'CF: ' + (cfInput.value || '1');
    });

    // Attach remove handler
    wrapper.querySelector('.remove-unit-btn').addEventListener('click', () => {
        wrapper.remove();
    });

    return wrapper;
}

// Load existing units
EXISTING_UNITS.forEach(unitData => {
    const row = createUnitRow(additionalUnitIndex++, unitData);
    additionalUnitsContainer.appendChild(row);
});

addUnitBtn.addEventListener('click', () => {
    const row = createUnitRow(additionalUnitIndex++);
    additionalUnitsContainer.appendChild(row);
});
</script>
@endsection
