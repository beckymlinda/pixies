@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">

@php
    $canManageStock = auth()->user()->isDirector() || auth()->user()->isManager();
@endphp

<div class="container-fluid px-4 py-4">
    <!-- Header with Search & Filter -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
                        <div>
                            <h1 class="h3 fw-bold mb-1 text-dark">Stock Management</h1>
                            <p class="text-muted small mb-0">
                                @if($selectedBarId && $bars->firstWhere('id', $selectedBarId))
                                    Viewing stock for <strong>{{ $bars->firstWhere('id', $selectedBarId)->name }}</strong>.
                                @else
                                    Viewing stock across all bars. Pick a bar from the Stock menu in the sidebar to narrow this down.
                                @endif
                            </p>
                        </div>
                        <div class="d-flex flex-column flex-sm-row gap-2 w-100 w-md-auto">
                            @if($canManageStock)
                                <button type="button" class="btn btn-success text-nowrap" data-bs-toggle="modal" data-bs-target="#addStockModal">
                                    <i class="bi bi-plus-circle me-1"></i> Add New Stock
                                </button>
                            @endif
                            <form method="GET" action="{{ route('stock.index') }}" class="d-flex gap-2 flex-grow-1">
                                {{-- Bar selection lives in the sidebar's Stock dropdown now - this
                                     form only searches within whatever bar that link carried in. --}}
                                <input type="hidden" name="bar_id" value="{{ $selectedBarId }}">
                                <div class="input-group shadow-sm rounded-pill overflow-hidden border border-secondary border-opacity-10">
                                    <span class="input-group-text bg-white border-0 ps-3"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" name="search" class="form-control border-0" placeholder="Search item or category..." value="{{ request('search') }}">
                                    <button type="submit" class="btn btn-primary px-4">Search</button>
                                </div>
                                @if(request('search'))
                                    <a href="{{ route('stock.index', ['bar_id' => $selectedBarId]) }}" class="btn btn-outline-secondary rounded-pill px-3 d-flex align-items-center" title="Clear Search">
                                        <i class="bi bi-x-circle me-1"></i> Clear
                                    </a>
                                @endif
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @php
        // Keyed by "bar_id_item_id", not just item_id - product_units carries
        // each bar's OWN prices now, and the same item appears once per bar
        // in $stockRows, so keying by item_id alone would collapse every
        // bar's row onto whichever one happened to be last, showing (and on
        // save, overwriting) the wrong bar's prices in the Edit modal.
        $stockItemsData = $stockRows->keyBy(fn($r) => $r['bar_id'] . '_' . $r['item_id'])->map(fn($r) => [
            'item_name' => $r['item_name'],
            'category' => $r['category'],
            'product_units' => $r['product_units'],
        ]);
    @endphp

    <script>
        window.stockItems = @json($stockItemsData);
    </script>

    <!-- Stock Table -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light border-bottom">
                            <tr>
                                <th class="border-0 text-uppercase text-muted small">#</th>
                                <th class="border-0 text-uppercase text-muted small">Bar</th>
                                <th class="border-0 text-uppercase text-muted small">Item</th>
                                <th class="border-0 text-uppercase text-muted small">Category</th>
                                <th class="border-0 text-uppercase text-muted small">Stock</th>
                                <th class="border-0 text-uppercase text-muted small">Purchase Price</th>
                                <th class="border-0 text-uppercase text-muted small">Selling Price</th>
                                <th class="border-0 text-uppercase text-muted small">Markup</th>
                                <th class="border-0 text-uppercase text-muted small">Last Updated</th>
                                @if($canManageStock)
                                    <th class="border-0 text-uppercase text-muted small">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody id="stockTableBody">
                            @include('stock._rows')
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@if($canManageStock)
<!-- Restock Modal (Auto-adds to previous stock) -->
<div class="modal fade" id="restockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-seam me-2"></i>Restock Item</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('stock.restock') }}">
                @csrf
                <input type="hidden" name="filter_bar_id" value="{{ $selectedBarId }}">
                <input type="hidden" name="filter_search" value="{{ $search }}">
                <div class="modal-body">
                    <input type="hidden" name="item_id" id="restockItemId">
                    <input type="hidden" name="bar_id" id="restockBarId">
                    <input type="hidden" name="item_name" id="restockItemName">
                    <input type="hidden" name="bar_name" id="restockBarName">
                    
                    <div class="mb-3">
                        <label class="form-label small text-muted text-uppercase fw-bold mb-1">Item</label>
                        <input type="text" class="form-control bg-light fw-bold" id="restockItemDisplay" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted text-uppercase fw-bold mb-1">Bar Location</label>
                        <input type="text" class="form-control bg-light fw-semibold" id="restockBarDisplay" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted text-uppercase fw-bold mb-1">Current Stock</label>
                        <input type="number" class="form-control bg-light fw-bold text-primary" id="restockCurrentStock" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Additional Quantity to Add</label>
                        <input type="number" name="additional_stock" class="form-control form-control-lg" id="restockAdditionalStock" min="1" required placeholder="e.g. 24" oninput="calculateNewTotalStock()">
                        <small class="text-muted">This quantity will be auto-added to the current stock.</small>
                    </div>
                    <div class="alert alert-info py-2 mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small fw-semibold">New Total Stock:</span>
                            <span class="fw-bold fs-5 text-dark" id="restockNewTotalDisplay">0</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Price (MWK)</label>
                        <input type="number" name="price" class="form-control" id="restockPrice" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4 fw-bold">Confirm Restock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Stock Modal -->
<div class="modal fade" id="editStockModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Stock & Prices</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('stock.update') }}" id="editStockForm">
                @csrf
                <input type="hidden" name="filter_bar_id" value="{{ $selectedBarId }}">
                <input type="hidden" name="filter_search" value="{{ $search }}">
                <div class="modal-body">
                    <input type="hidden" name="item_id" id="editItemId">
                    <input type="hidden" name="bar_name" id="editBarName">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Item Name</label>
                        <input type="text" name="item_name" class="form-control fw-bold" id="editItemDisplay" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted text-uppercase fw-bold mb-1">Bar</label>
                        <input type="text" class="form-control bg-light fw-semibold" id="editBarDisplay" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Category</label>
                        <input type="text" name="category" class="form-control" id="editCategory" list="categoryList" placeholder="e.g. Beer, Spirits, Wine...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">New Total Stock Quantity <span class="text-muted fw-normal small">(in the base unit)</span></label>
                        <input type="number" name="new_stock" class="form-control" id="editNewStock" min="0" required>
                    </div>

                    <!-- Multi-unit section -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Selling Units</label>
                        <div class="small text-muted mb-2">The first unit is the <strong>base unit</strong> — stock is counted in this unit. For each additional unit, set the conversion factor (how many base units equal 1 of this unit). Example: if base is Shot, a Bottle with factor 28 means 1 Bottle = 28 Shots.</div>

                        <div id="editUnitsContainer">
                            <!-- Rows populated by JS -->
                        </div>

                        <button type="button" class="btn btn-sm btn-outline-primary" id="editAddUnitBtn">
                            <i class="bi bi-plus-lg me-1"></i>Add Another Unit
                        </button>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Update Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Stock Modal (Independent Creation — multi-unit) -->
<div class="modal fade" id="addStockModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Add New Bar Stock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('stock.add') }}" id="addStockForm">
                @csrf
                <input type="hidden" name="filter_bar_id" value="{{ $selectedBarId }}">
                <input type="hidden" name="filter_search" value="{{ $search }}">
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Select Bar</label>
                            <select name="bar_id" class="form-select" required id="barSelect">
                                <option value="">Choose a bar...</option>
                                @foreach($bars as $bar)
                                    <option value="{{ $bar->id }}" {{ count($bars) == 1 ? 'selected' : '' }}>{{ $bar->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Category</label>
                            <input type="text" name="category" class="form-control" list="categoryList" placeholder="e.g. Beer, Spirits, Wine...">
                            <datalist id="categoryList">
                                <option value="Beer">
                                <option value="Spirits">
                                <option value="Wine">
                                <option value="Soft Drinks">
                                <option value="Food">
                                <option value="Other">
                            </datalist>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Item Name</label>
                        <input type="text" name="item_name" class="form-control" required placeholder="e.g. Carlsberg Green 330ml">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Initial Stock Quantity <span class="text-muted fw-normal small">(in the base unit)</span></label>
                        <input type="number" name="stock_quantity" class="form-control" min="0" required placeholder="e.g. 50">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Selling Unit</label>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label small mb-0">Unit Name</label>
                                <select name="unit_name" class="form-select form-select-sm">
                                    <option value="Bottle" selected>Bottle</option>
                                    <option value="Shot">Shot</option>
                                    <option value="Glass">Glass</option>
                                    <option value="Can">Can</option>
                                    <option value="Crate">Crate</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-0">Selling Price</label>
                                <input type="number" name="selling_price" class="form-control form-control-sm" step="0.01" min="0" placeholder="0.00" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-0">Cost Price</label>
                                <input type="number" name="purchase_price" class="form-control form-control-sm" step="0.01" min="0" placeholder="0.00">
                            </div>
                        </div>
                        <div class="small text-muted mt-1">For an existing item, this must match a unit it already sells in — use Edit Stock to introduce a new unit with its conversion factor.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Stock Expiry <span class="text-muted fw-normal small">(optional)</span></label>
                        <input type="date" name="expiry_date" class="form-control" min="{{ now()->format('Y-m-d') }}">
                        <div class="small text-muted mt-1">Set this to have the batch show up on the Stock Expiry page.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4 fw-bold">Create Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit modal unit row management
(function() {
    const container = document.getElementById('editUnitsContainer');
    const addBtn = document.getElementById('editAddUnitBtn');

    if (!container || !addBtn) return;

    addBtn.addEventListener('click', function() {
        const rows = container.querySelectorAll('.edit-unit-row');
        const firstRow = rows[0];
        if (!firstRow) {
            container.appendChild(createEditUnitRow(0, {
                unit_name: 'Bottle',
                selling_price: 0,
                purchase_price: 0,
                conversion_factor: 1,
                is_base_unit: true
            }));
            refreshEditUnitRows();
            return;
        }

        const clone = firstRow.cloneNode(true);
        clone.querySelectorAll('input[type="number"]').forEach(function(input) {
            if (input.name.includes('selling_price') || input.name.includes('purchase_price')) {
                input.value = '';
                input.required = false;
            } else if (input.name.includes('conversion_factor')) {
                input.value = '1';
                input.readOnly = false;
            }
        });
        clone.querySelector('select').selectedIndex = 0;

        container.appendChild(clone);
        refreshEditUnitRows();
    });

    container.addEventListener('click', function(e) {
        const btn = e.target.closest('.edit-remove-unit-btn');
        if (btn) {
            btn.closest('.edit-unit-row').remove();
            refreshEditUnitRows();
        }
    });
})();
</script>
@endif

<script>
function restockStock(itemId, barId, itemName, barName, currentStock, currentPrice) {
    document.getElementById('restockItemId').value = itemId;
    document.getElementById('restockBarId').value = barId;
    document.getElementById('restockItemName').value = itemName;
    document.getElementById('restockBarName').value = barName;
    document.getElementById('restockItemDisplay').value = itemName;
    document.getElementById('restockBarDisplay').value = barName;
    document.getElementById('restockCurrentStock').value = currentStock;
    document.getElementById('restockAdditionalStock').value = '';
    document.getElementById('restockPrice').value = currentPrice;
    document.getElementById('restockNewTotalDisplay').innerText = currentStock;
    
    const modal = new bootstrap.Modal(document.getElementById('restockModal'));
    modal.show();
}

function calculateNewTotalStock() {
    const current = parseFloat(document.getElementById('restockCurrentStock').value) || 0;
    const additional = parseFloat(document.getElementById('restockAdditionalStock').value) || 0;
    document.getElementById('restockNewTotalDisplay').innerText = (current + additional).toLocaleString();
}

function editStock(itemId, barId, itemName, barName, currentStock) {
    const data = window.stockItems[barId + '_' + itemId] || {};
    const category = data.category || '';
    let productUnits = data.product_units || [];

    document.getElementById('editItemId').value = itemId;
    document.getElementById('editBarName').value = barName;
    document.getElementById('editItemDisplay').value = itemName;
    document.getElementById('editBarDisplay').value = barName;
    document.getElementById('editNewStock').value = currentStock;
    document.getElementById('editCategory').value = category;

    const container = document.getElementById('editUnitsContainer');
    container.innerHTML = '';

    if (!productUnits || productUnits.length === 0) {
        productUnits = [{
            unit_name: 'Bottle',
            selling_price: 0,
            purchase_price: 0,
            conversion_factor: 1,
            is_base_unit: true
        }];
    }

    productUnits.forEach(function(unit, index) {
        container.appendChild(createEditUnitRow(index, unit));
    });

    refreshEditUnitRows();

    const modal = new bootstrap.Modal(document.getElementById('editStockModal'));
    modal.show();
}

function createEditUnitRow(index, unit) {
    const div = document.createElement('div');
    div.className = 'edit-unit-row border rounded p-2 mb-2 bg-light';
    const isBase = unit.is_base_unit || index === 0;
    const unitOptions = ['Bottle', 'Shot', 'Glass', 'Can', 'Crate'].map(function(u) {
        return '<option value="' + u + '"' + (unit.unit_name === u ? ' selected' : '') + '>' + u + '</option>';
    }).join('');

    div.innerHTML =
        '<div class="row g-2 align-items-end">' +
            '<div class="col-md-3">' +
                '<label class="form-label small mb-0">Unit Name</label>' +
                '<select name="units[' + index + '][unit_name]" class="form-select form-select-sm unit-name-select">' + unitOptions + '</select>' +
            '</div>' +
            '<div class="col-md-3">' +
                '<label class="form-label small mb-0">Selling Price</label>' +
                '<input type="number" name="units[' + index + '][selling_price]" class="form-control form-control-sm" step="0.01" min="0" placeholder="0.00" value="' + (unit.selling_price || 0) + '" required>' +
            '</div>' +
            '<div class="col-md-3">' +
                '<label class="form-label small mb-0">Cost Price</label>' +
                '<input type="number" name="units[' + index + '][purchase_price]" class="form-control form-control-sm" step="0.01" min="0" placeholder="0.00" value="' + (unit.purchase_price || 0) + '">' +
            '</div>' +
            '<div class="col-md-2 conversion-field' + (isBase ? ' d-none' : '') + '">' +
                '<label class="form-label small mb-0">Factor</label>' +
                '<input type="number" name="units[' + index + '][conversion_factor]" class="form-control form-control-sm" min="1" value="' + (unit.conversion_factor || 1) + '"' + (isBase ? ' readonly' : '') + '>' +
            '</div>' +
            '<div class="col-md-1 text-end">' +
                '<button type="button" class="btn btn-sm btn-outline-danger edit-remove-unit-btn" title="Remove unit">&times;</button>' +
            '</div>' +
        '</div>' +
        '<input type="hidden" name="units[' + index + '][is_base]" value="' + (isBase ? '1' : '0') + '" class="is-base-input">' +
        '<div class="small text-success mt-1 base-label"' + (isBase ? '' : ' style="display:none;"') + '>Base unit</div>';

    return div;
}

function refreshEditUnitRows() {
    const container = document.getElementById('editUnitsContainer');
    const rows = container.querySelectorAll('.edit-unit-row');
    rows.forEach(function(row, i) {
        row.querySelectorAll('[name]').forEach(function(input) {
            input.name = input.name.replace(/\[\d+\]/, '[' + i + ']');
        });

        const baseInput = row.querySelector('.is-base-input');
        const baseLabel = row.querySelector('.base-label');
        const convField = row.querySelector('.conversion-field');
        const convInput = convField ? convField.querySelector('input') : null;
        const removeBtn = row.querySelector('.edit-remove-unit-btn');

        if (i === 0) {
            if (baseInput) baseInput.value = '1';
            if (baseLabel) baseLabel.style.display = '';
            if (convField) convField.classList.add('d-none');
            if (convInput) convInput.readOnly = true;
        } else {
            if (baseInput) baseInput.value = '0';
            if (baseLabel) baseLabel.style.display = 'none';
            if (convField) convField.classList.remove('d-none');
            if (convInput) convInput.readOnly = false;
        }

        if (removeBtn) {
            removeBtn.style.display = rows.length > 1 ? '' : 'none';
        }
    });
}

function deleteStock(itemId, barId, itemName, barName) {
    if (confirm(`Are you sure you want to delete stock for ${itemName} at ${barName}?`)) {
        const filterBarId = @json((string) $selectedBarId);
        const filterSearch = @json((string) $search);
        let url = `/stock/delete?item_id=${encodeURIComponent(itemId)}&bar_id=${encodeURIComponent(barId)}`;
        if (filterBarId) url += `&filter_bar_id=${encodeURIComponent(filterBarId)}`;
        if (filterSearch) url += `&filter_search=${encodeURIComponent(filterSearch)}`;
        window.location.href = url;
    }
}

// Live reload: quietly re-fetch this same view every few seconds so changes
// made elsewhere (another director editing/restocking/deleting an item)
// show up without a manual refresh. Skipped entirely while any modal is
// open, so it never disturbs an in-progress Edit/Restock/Delete action.
(function () {
    const POLL_INTERVAL_MS = 7000;

    function anyModalOpen() {
        return document.querySelector('.modal.show') !== null;
    }

    function poll() {
        if (anyModalOpen()) {
            return;
        }

        const url = new URL(window.location.href);
        url.searchParams.set('poll', '1');

        fetch(url.toString(), { headers: { 'Accept': 'application/json' } })
            .then(r => (r.ok ? r.json() : null))
            .then(data => {
                if (!data) return;
                const tbody = document.getElementById('stockTableBody');
                if (tbody) tbody.innerHTML = data.html;
                window.stockItems = data.stockItems;
            })
            .catch(() => { /* a missed poll is harmless - the next interval retries */ });
    }

    setInterval(poll, POLL_INTERVAL_MS);
})();
</script>
@endsection
