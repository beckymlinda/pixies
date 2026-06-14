@extends('layouts.app')

@section('content')
@php $pageTitle = 'Stock Requests'; @endphp
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    /* ── Page Header ── */
    .page-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: -1.5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }

    /* ── Stat Cards ── */
    .stat-card {
        border-radius: 16px;
        padding: 1.25rem;
        border: 1px solid #e2e8f0;
        background: white;
        transition: all 0.2s;
    }
    .stat-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
    .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }
    .stat-value { font-size: 1.5rem; font-weight: 700; color: #1e293b; }
    .stat-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }

    /* ── Request Cards ── */
    .request-card {
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        background: white;
        overflow: hidden;
        margin-bottom: 1rem;
        transition: all 0.2s;
    }
    .request-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
    .request-card-header {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.875rem 1.25rem;
    }
    .request-card-body { padding: 1rem 1.25rem; }

    /* ── Status Badges ── */
    .status-badge {
        font-size: 0.7rem;
        padding: 5px 12px;
        border-radius: 20px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .status-pending { background: #fef3c7; color: #92400e; }
    .status-approved { background: #d1fae5; color: #065f46; }
    .status-rejected { background: #fee2e2; color: #991b1b; }

    /* ── Item Chips ── */
    .item-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 0.8rem;
        color: #334155;
        font-weight: 500;
    }
    .item-chip .qty { color: #2563eb; font-weight: 700; }

    /* ── Modal Items ── */
    .warehouse-item-row {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.875rem 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        margin-bottom: 0.5rem;
        background: white;
        transition: all 0.15s;
    }
    .warehouse-item-row:hover { border-color: #3b82f6; background: #f0f7ff; }
    .warehouse-item-row.selected { border-color: #3b82f6; background: #eff6ff; box-shadow: 0 0 0 2px rgba(59,130,246,0.15); }

    .item-check { flex-shrink: 0; }
    .item-details { flex: 1; min-width: 0; }
    .item-controls { display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; }
    .item-controls select,
    .item-controls input { font-size: 0.85rem; }

    /* ── Empty State ── */
    .empty-state {
        text-align: center;
        padding: 3rem 1.5rem;
        color: #94a3b8;
    }
    .empty-state i { font-size: 3rem; margin-bottom: 1rem; display: block; opacity: 0.4; }

    /* ── Mobile ── */
    @media (max-width: 768px) {
        .page-header { padding: 1rem 1.25rem; }
        .page-header h1 { font-size: 1.25rem; }
        .stat-card { padding: 1rem; }
        .stat-value { font-size: 1.2rem; }
        .warehouse-item-row {
            flex-direction: column;
            align-items: stretch;
            gap: 0.75rem;
        }
        .item-controls { flex-wrap: wrap; }
        .item-controls select, .item-controls input { width: 100%; }
        .request-card-header {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 0.5rem;
        }
    }
</style>

<div class="container-fluid p-0">
    {{-- ═══════════════════════════════════════ HEADER ═══════════════════════════════════════ --}}
    <div class="page-header shadow-sm">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
            <div>
                <h1 class="h3 fw-bold mb-1 text-dark">Stock Requests</h1>
                <p class="text-muted small mb-0">Request stock from the warehouse and track your request history.</p>
            </div>
            <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" onclick="openRequestModal()">
                <i class="bi bi-plus-lg me-2"></i>New Stock Request
            </button>
        </div>
    </div>

    <div class="px-4">
        {{-- Alerts --}}
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-3 mb-3" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- ═══════════════ STATS ═══════════════ --}}
        @php
            $allRequests = $transferRequests ?? collect();
            $pendingCount = $allRequests->where('status', 'pending')->count();
            $approvedCount = $allRequests->whereIn('status', ['approved', 'partially_approved'])->count();
            $rejectedCount = $allRequests->where('status', 'rejected')->count();
        @endphp

        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-send"></i></div>
                        <div>
                            <div class="stat-value">{{ $allRequests->count() }}</div>
                            <div class="stat-label">Total Requests</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-clock"></i></div>
                        <div>
                            <div class="stat-value">{{ $pendingCount }}</div>
                            <div class="stat-label">Pending</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle"></i></div>
                        <div>
                            <div class="stat-value">{{ $approvedCount }}</div>
                            <div class="stat-label">Approved</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-x-circle"></i></div>
                        <div>
                            <div class="stat-value">{{ $rejectedCount }}</div>
                            <div class="stat-label">Rejected</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════ FILTER TABS ═══════════════ --}}
        <div class="d-flex gap-2 mb-3 flex-wrap">
            <button class="btn btn-sm rounded-pill px-3 filter-btn active" data-filter="all" onclick="filterRequests('all', this)">
                All ({{ $allRequests->count() }})
            </button>
            <button class="btn btn-sm btn-outline-warning rounded-pill px-3 filter-btn" data-filter="pending" onclick="filterRequests('pending', this)">
                <i class="bi bi-clock me-1"></i>Pending ({{ $pendingCount }})
            </button>
            <button class="btn btn-sm btn-outline-success rounded-pill px-3 filter-btn" data-filter="approved" onclick="filterRequests('approved', this)">
                <i class="bi bi-check-circle me-1"></i>Approved ({{ $approvedCount }})
            </button>
            <button class="btn btn-sm btn-outline-danger rounded-pill px-3 filter-btn" data-filter="rejected" onclick="filterRequests('rejected', this)">
                <i class="bi bi-x-circle me-1"></i>Rejected ({{ $rejectedCount }})
            </button>
        </div>

        {{-- ═══════════════ REQUESTS LIST ═══════════════ --}}
        @forelse($allRequests as $req)
            <div class="request-card" data-status="{{ $req->status }}">
                <div class="request-card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge bg-primary text-uppercase px-3 py-2 rounded-pill fw-bold" style="font-size:0.7rem">{{ $req->resolveBarName() }}</span>
                        <span class="status-badge status-{{ $req->status === 'partially_approved' ? 'approved' : $req->status }}">
                            {{ $req->status === 'partially_approved' ? 'Partially Approved' : ucfirst(str_replace('_', ' ', $req->status)) }}
                        </span>
                        @if($req->items->count() > 0)
                            <span class="badge bg-light text-dark border">{{ $req->items->count() }} items · {{ $req->items->sum('quantity_requested') }} requested</span>
                        @endif
                    </div>
                    <div class="text-muted small text-end">
                        <div><i class="bi bi-calendar3 me-1"></i>{{ $req->requested_at ? $req->requested_at->format('M d, Y h:i A') : $req->created_at->format('M d, Y h:i A') }}</div>
                        @if($req->requestedBy)
                            <div><i class="bi bi-person me-1"></i>{{ $req->requestedBy->name }}</div>
                        @endif
                    </div>
                </div>
                <div class="request-card-body">
                    @if($req->items->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 align-middle" style="font-size:0.85rem">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Item</th>
                                        <th class="text-center">Unit</th>
                                        <th class="text-center">Requested</th>
                                        <th class="text-center pe-3">Approved</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($req->items as $item)
                                        <tr>
                                            <td class="ps-3 fw-semibold">{{ $item->display_name }}</td>
                                            <td class="text-center text-muted">{{ $item->unit_name ?? 'units' }}</td>
                                            <td class="text-center fw-bold text-primary">{{ number_format($item->quantity_requested) }}</td>
                                            <td class="text-center pe-3 fw-bold {{ $item->quantity_approved > 0 ? 'text-success' : 'text-muted' }}">
                                                @if(in_array($req->status, ['approved', 'partially_approved', 'rejected']))
                                                    {{ number_format($item->quantity_approved) }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="table-light">
                                    <tr class="fw-bold">
                                        <td class="ps-3">Total</td>
                                        <td></td>
                                        <td class="text-center">{{ number_format($req->items->sum('quantity_requested')) }}</td>
                                        <td class="text-center pe-3 text-success">{{ number_format($req->items->sum('quantity_approved')) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-light border small mb-0 text-muted">
                            <i class="bi bi-info-circle me-1"></i>No line items were recorded for this request.
                        </div>
                    @endif

                    {{-- Notes / Rejection Reason --}}
                    @if($req->notes && $req->status !== 'rejected')
                        <div class="bg-light p-2 rounded-2 border-start border-3 border-primary mt-2" style="font-size:0.8rem">
                            <strong class="text-muted">Notes:</strong> {{ $req->notes }}
                        </div>
                    @endif
                    @if($req->status === 'rejected' && $req->rejection_reason)
                        <div class="bg-light p-2 rounded-2 border-start border-3 border-danger mt-2" style="font-size:0.8rem">
                            <strong class="text-danger">Rejection Reason:</strong> {{ $req->rejection_reason }}
                        </div>
                    @endif
                    @if(in_array($req->status, ['approved', 'partially_approved']) && $req->approvedBy)
                        <div class="text-muted mt-2" style="font-size:0.75rem">
                            <i class="bi bi-person-check me-1"></i>Approved by <strong>{{ $req->approvedBy->name }}</strong>
                            @if($req->approved_at) on {{ $req->approved_at->format('M d, Y h:i A') }}@endif
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h5 class="fw-bold text-muted">No Stock Requests Yet</h5>
                <p class="text-muted mb-3">Click "New Stock Request" to request items from the warehouse.</p>
                <button type="button" class="btn btn-primary rounded-pill px-4" onclick="openRequestModal()">
                    <i class="bi bi-plus-lg me-2"></i>Create Your First Request
                </button>
            </div>
        @endforelse
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════════════════════ --}}
{{-- ═══════════════════════════ NEW STOCK REQUEST MODAL ═══════════════════════════════════════ --}}
{{-- ═══════════════════════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="requestModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 bg-primary text-white" style="border-radius:0.5rem 0.5rem 0 0">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-left-right me-2"></i>Request Stock from Warehouse</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                {{-- Step 1: Select Bar --}}
                <div class="mb-4">
                    <label class="form-label fw-bold small text-uppercase text-muted">Select Target Bar</label>
                    <select id="requestBarSelect" class="form-select" onchange="loadWarehouseItems()">
                        <option value="">-- Choose a bar --</option>
                        @foreach(\App\Models\Bar::listed()->orderBy('name')->get() as $bar)
                            <option value="{{ $bar->id }}">{{ $bar->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Step 2: Items from warehouse --}}
                <div id="warehouseItemsContainer" class="d-none">
                    <label class="form-label fw-bold small text-uppercase text-muted mb-2">
                        Available Warehouse Items
                    </label>
                    <div class="mb-3">
                        <input type="text" class="form-control form-control-sm" id="itemSearchInput" placeholder="🔍 Search items..." oninput="filterModalItems()">
                    </div>
                    <div id="warehouseItemsList" style="max-height: 400px; overflow-y: auto;">
                        <div class="text-center py-4">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            <p class="small text-muted mt-2">Loading warehouse items...</p>
                        </div>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="mt-4" id="requestNotesSection" style="display:none">
                    <label class="form-label fw-bold small text-uppercase text-muted">Notes (optional)</label>
                    <textarea class="form-control" id="requestNotes" rows="2" placeholder="Any additional notes for the manager..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary rounded-pill px-4" id="submitRequestBtn" onclick="submitStockRequest()" disabled>
                    <i class="bi bi-send me-2"></i>Submit Request
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ── State ──
let warehouseItems = [];
let selectedItems = {};

// ── Open Modal ──
function openRequestModal() {
    selectedItems = {};
    document.getElementById('requestBarSelect').value = '';
    document.getElementById('warehouseItemsContainer').classList.add('d-none');
    document.getElementById('requestNotesSection').style.display = 'none';
    document.getElementById('submitRequestBtn').disabled = true;
    document.getElementById('requestNotes').value = '';
    new bootstrap.Modal(document.getElementById('requestModal')).show();
}

// ── Load Items from API ──
function loadWarehouseItems() {
    const barId = document.getElementById('requestBarSelect').value;
    if (!barId) {
        document.getElementById('warehouseItemsContainer').classList.add('d-none');
        return;
    }

    const container = document.getElementById('warehouseItemsContainer');
    const list = document.getElementById('warehouseItemsList');
    container.classList.remove('d-none');
    document.getElementById('requestNotesSection').style.display = 'block';

    list.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
            <p class="small text-muted mt-2">Loading warehouse items...</p>
        </div>
    `;

    fetch(`/warehouse/api/available-items?bar_id=${barId}`)
        .then(r => r.json())
        .then(data => {
            warehouseItems = data;
            renderWarehouseItems(data);
        })
        .catch(err => {
            list.innerHTML = `<div class="alert alert-danger">Error loading items: ${err.message}</div>`;
        });
}

// ── Render Items ──
function renderWarehouseItems(items) {
    const list = document.getElementById('warehouseItemsList');

    if (!items || items.length === 0) {
        list.innerHTML = `
            <div class="empty-state py-4">
                <i class="bi bi-inbox" style="font-size:2rem"></i>
                <p class="text-muted mt-2">No items available in the warehouse.</p>
            </div>
        `;
        return;
    }

    let html = '';
    items.forEach(item => {
        const isSelected = selectedItems[item.id] !== undefined;
        const units = item.units || [];
        // Default to first unit
        const defaultUnit = units.length > 0 ? units[0] : null;
        const selectedData = selectedItems[item.id] || {};

        html += `
            <div class="warehouse-item-row ${isSelected ? 'selected' : ''}" id="wh-item-${item.id}" data-name="${item.item_name.toLowerCase()}">
                <div class="item-check">
                    <input type="checkbox" class="form-check-input" 
                           id="check-${item.id}" 
                           ${isSelected ? 'checked' : ''} 
                           onchange="toggleItem(${item.id})">
                </div>
                <div class="item-details">
                    <div class="fw-bold text-dark" style="font-size:0.9rem">${item.item_name}</div>
                    <div class="text-muted" style="font-size:0.75rem">
                        Available: <strong class="${item.available_quantity <= 0 ? 'text-danger' : 'text-success'}">${item.available_quantity.toLocaleString()}</strong> base units
                    </div>
                </div>
                <div class="item-controls">
                    <select class="form-select form-select-sm" id="unit-${item.id}" 
                            onchange="updateItemSelection(${item.id})" style="min-width:120px">
                        ${units.map(u => `
                            <option value="${u.unit_name}" 
                                    data-conversion="${u.conversion_factor}" 
                                    data-price="${u.price}"
                                    ${selectedData.unit_name === u.unit_name ? 'selected' : ''}>
                                ${u.unit_name} ${u.price > 0 ? '(MWK ' + u.price.toLocaleString() + ')' : ''}
                            </option>
                        `).join('')}
                    </select>
                    <input type="number" class="form-control form-control-sm" id="qty-${item.id}" 
                           min="1" value="${selectedData.quantity || 1}" 
                           onchange="updateItemSelection(${item.id})"
                           style="width:80px" placeholder="Qty"
                           ${!isSelected ? 'disabled' : ''}>
                </div>
            </div>
        `;
    });

    list.innerHTML = html;
}

// ── Toggle Item Selection ──
function toggleItem(itemId) {
    const checkbox = document.getElementById(`check-${itemId}`);
    const row = document.getElementById(`wh-item-${itemId}`);
    const qtyInput = document.getElementById(`qty-${itemId}`);

    if (checkbox.checked) {
        row.classList.add('selected');
        qtyInput.disabled = false;
        updateItemSelection(itemId);
    } else {
        row.classList.remove('selected');
        qtyInput.disabled = true;
        delete selectedItems[itemId];
    }

    updateSubmitButton();
}

// ── Update Item Data ──
function updateItemSelection(itemId) {
    const checkbox = document.getElementById(`check-${itemId}`);
    if (!checkbox || !checkbox.checked) return;

    const unitSelect = document.getElementById(`unit-${itemId}`);
    const qtyInput = document.getElementById(`qty-${itemId}`);
    const selectedOption = unitSelect.options[unitSelect.selectedIndex];

    selectedItems[itemId] = {
        warehouse_stock_id: itemId,
        quantity: parseInt(qtyInput.value) || 1,
        unit_name: unitSelect.value,
        conversion_factor: parseInt(selectedOption.dataset.conversion) || 1,
    };

    updateSubmitButton();
}

// ── Update Submit Button ──
function updateSubmitButton() {
    const btn = document.getElementById('submitRequestBtn');
    const count = Object.keys(selectedItems).length;
    btn.disabled = count === 0;
    btn.innerHTML = count > 0
        ? `<i class="bi bi-send me-2"></i>Submit Request (${count} item${count > 1 ? 's' : ''})`
        : `<i class="bi bi-send me-2"></i>Submit Request`;
}

// ── Filter Items in Modal ──
function filterModalItems() {
    const query = document.getElementById('itemSearchInput').value.toLowerCase();
    document.querySelectorAll('.warehouse-item-row').forEach(row => {
        const name = row.dataset.name || '';
        row.style.display = name.includes(query) ? '' : 'none';
    });
}

// ── Submit Request ──
function submitStockRequest() {
    const barId = document.getElementById('requestBarSelect').value;
    const notes = document.getElementById('requestNotes').value;

    if (!barId) {
        alert('Please select a bar.');
        return;
    }

    const items = Object.entries(selectedItems).map(([id, data]) => ({
        warehouse_stock_id: parseInt(id),
        quantity_requested: data.quantity,
        unit_name: data.unit_name,
        conversion_factor: data.conversion_factor,
    }));

    if (items.length === 0) {
        alert('Please select at least one item.');
        return;
    }

    const btn = document.getElementById('submitRequestBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';

    fetch('/warehouse-transfers/request', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
        },
        body: JSON.stringify({ bar_id: barId, items: items, notes: notes }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('requestModal')).hide();
            // Show success and reload
            location.reload();
        } else {
            alert(data.message || 'Error submitting request.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send me-2"></i>Submit Request';
        }
    })
    .catch(err => {
        alert('Network error: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-2"></i>Submit Request';
    });
}

// ── Filter Request History ──
function filterRequests(status, btn) {
    // Update active state
    document.querySelectorAll('.filter-btn').forEach(b => {
        b.classList.remove('active', 'btn-primary');
        b.classList.add('btn-outline-secondary');
    });
    btn.classList.remove('btn-outline-secondary', 'btn-outline-warning', 'btn-outline-success', 'btn-outline-danger');
    btn.classList.add('active', 'btn-primary');

    // Filter cards
    document.querySelectorAll('.request-card').forEach(card => {
        if (status === 'all' || card.dataset.status === status) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>
@endsection
