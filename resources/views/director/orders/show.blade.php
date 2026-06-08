@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .director-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: -1.5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }
    .stock-shortage {
        background: #fff7ed;
        border: 1px solid #fed7aa;
        color: #c2410c;
        border-radius: 8px;
        padding: 0.4rem 0.75rem;
        font-size: 0.78rem;
        font-weight: 600;
    }
    .stock-ok {
        color: #16a34a;
        font-weight: 700;
    }
    .stock-empty {
        color: #dc2626;
        font-weight: 700;
    }
    .approved-qty-input {
        border: 2px solid #3b82f6;
        border-radius: 8px;
        text-align: center;
        font-weight: 700;
        color: #1e40af;
        width: 90px;
        padding: 0.35rem 0.5rem;
        transition: border-color 0.2s;
    }
    .approved-qty-input:focus {
        outline: none;
        border-color: #1d4ed8;
        box-shadow: 0 0 0 3px rgba(59,130,246,0.2);
    }
    .approved-qty-input.over-stock {
        border-color: #dc2626;
        color: #dc2626;
    }
    .stock-alert-banner {
        background: linear-gradient(135deg, #fef3c7, #fef9eb);
        border: 2px solid #fbbf24;
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
    }
    .action-btn-approve {
        background: linear-gradient(135deg, #059669, #10b981);
        border: none;
        color: white;
        font-weight: 700;
        border-radius: 50px;
        padding: 0.6rem 1.75rem;
        transition: all 0.2s;
        box-shadow: 0 4px 12px rgba(16,185,129,0.3);
    }
    .action-btn-approve:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(16,185,129,0.4);
        color: white;
    }
    .action-btn-deny {
        background: transparent;
        border: 2px solid #dc2626;
        color: #dc2626;
        font-weight: 700;
        border-radius: 50px;
        padding: 0.6rem 1.75rem;
        transition: all 0.2s;
    }
    .action-btn-deny:hover {
        background: #dc2626;
        color: white;
    }
    .action-btn-reopen {
        background: transparent;
        border: 2px solid #7c3aed;
        color: #7c3aed;
        font-weight: 700;
        border-radius: 50px;
        padding: 0.55rem 1.5rem;
        transition: all 0.2s;
        font-size: 0.875rem;
    }
    .action-btn-reopen:hover {
        background: #7c3aed;
        color: white;
    }
    .status-ribbon {
        position: relative;
        overflow: hidden;
    }
    .decision-panel {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.5rem;
    }
</style>

<div class="container-fluid p-0">
    <!-- Header -->
    <div class="director-header d-flex align-items-center justify-content-between shadow-sm flex-wrap gap-3">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark">Review Stock Request</h1>
            <p class="text-muted small mb-0">Authorize inventory transfer — you can approve, deny, or reopen at any time.</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            @php
                $badgeClass = match($orderRequest->status) {
                    'pending' => 'bg-warning text-dark',
                    'approved' => 'bg-success text-white',
                    'partially_approved' => 'bg-info text-white',
                    'denied' => 'bg-danger text-white',
                    default => 'bg-secondary text-white'
                };
                $statusText = match($orderRequest->status) {
                    'pending' => '⏳ Pending',
                    'approved' => '✅ Approved',
                    'partially_approved' => 'ℹ️ Partially Approved',
                    'denied' => '❌ Denied',
                    default => $orderRequest->status
                };
            @endphp
            <span class="badge {{ $badgeClass }} px-3 py-2 rounded-pill fw-semibold fs-6">{{ $statusText }}</span>
            <a href="{{ route('director.orders.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Back to Requests
            </a>
        </div>
    </div>

    <div class="px-4 pb-5">
        @if (session('success') || session('error'))
            <div class="mb-4">
                @if (session('success'))
                    <div class="alert alert-success border-0 shadow-sm rounded-3" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger border-0 shadow-sm rounded-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
                    </div>
                @endif
            </div>
        @endif

        @php
            // Check if any item has insufficient stock
            $hasStockShortage = false;
            $allOutOfStock = true;
            foreach($orderRequest->items as $reqItem) {
                if ($reqItem->item->director_stock < $reqItem->requested_quantity) {
                    $hasStockShortage = true;
                }
                if ($reqItem->item->director_stock > 0) {
                    $allOutOfStock = false;
                }
            }
        @endphp

        {{-- ⚠️ Stock Shortage Warning Banner --}}
        @if($hasStockShortage)
            <div class="stock-alert-banner mb-4">
                <div class="d-flex align-items-start gap-3">
                    <div style="font-size: 2rem; line-height: 1;">⚠️</div>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-warning-emphasis mb-1" style="font-size: 1rem;">Warehouse Stock Shortage Detected</div>
                        <p class="mb-2 text-dark small">
                            One or more requested items have <strong>insufficient warehouse stock</strong>.
                            You can still approve available quantities (partial approval),
                            or add stock first before approving the full request.
                        </p>
                        <a href="{{ route('director-stock-entries.create') }}" class="btn btn-sm btn-warning rounded-pill fw-bold px-3">
                            <i class="bi bi-box-seam me-1"></i> Go to Stock Management to Add Stock
                        </a>
                    </div>
                </div>
            </div>
        @endif

        <div class="row g-4">
            <!-- Request Info Panel -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-info-circle text-primary me-2"></i>Request Info</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">Current Status</label>
                            <div>
                                <span class="badge px-3 py-2 rounded-pill fw-semibold {{ $badgeClass }}">{{ $statusText }}</span>
                            </div>
                        </div>

                        <div class="mb-3 border-top pt-3">
                            <label class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">Requested By</label>
                            <div class="fw-bold text-dark">{{ $orderRequest->user->name }}</div>
                            <div class="small text-muted">Bar Seller</div>
                        </div>

                        <div class="mb-3 border-top pt-3">
                            <label class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">Destination Bar</label>
                            <div class="fw-bold text-primary">📍 {{ $orderRequest->bar->name }}</div>
                        </div>

                        <div class="mb-3 border-top pt-3">
                            <label class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">Request Date</label>
                            <div class="fw-bold text-dark">{{ $orderRequest->date->format('l, M d, Y') }}</div>
                            <div class="small text-muted">Submitted: {{ $orderRequest->created_at->format('H:i A') }}</div>
                        </div>

                        @if($orderRequest->notes)
                            <div class="mb-3 border-top pt-3">
                                <label class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">Director's Notes</label>
                                <div class="p-3 border rounded bg-light small text-secondary">
                                    {{ $orderRequest->notes }}
                                </div>
                            </div>
                        @endif

                        {{-- Reopen button for non-pending requests --}}
                        @if($orderRequest->status !== 'pending')
                            <div class="border-top pt-3">
                                <form action="{{ route('director.orders.approve', $orderRequest) }}" method="POST" id="reopen-form">
                                    @csrf
                                    <input type="hidden" name="action" value="reopen">
                                    <input type="hidden" name="notes" value="{{ $orderRequest->notes }}">
                                    <button type="button" class="action-btn-reopen w-100" onclick="confirmReopen()">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reopen & Reset to Pending
                                    </button>
                                    <div class="small text-muted text-center mt-2" style="font-size: 0.7rem;">
                                        This will refund approved stock back to the warehouse.
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Items + Approval Panel -->
            <div class="col-lg-8">
                <form action="{{ route('director.orders.approve', $orderRequest) }}" method="POST" id="approval-form">
                    @csrf
                    <input type="hidden" name="action" id="action-input" value="approve">

                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0 text-dark">
                                <i class="bi bi-box-seam text-primary me-2"></i>Requested Items & Warehouse Stock
                            </h5>
                            <span class="small text-muted">
                                Set approval quantities per item below
                            </span>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light text-uppercase font-semibold text-muted" style="font-size: 0.72rem;">
                                    <tr>
                                        <th class="ps-4" style="width: 35%">Item / Product</th>
                                        <th class="text-center" style="width: 18%">Requested</th>
                                        <th class="text-center" style="width: 22%">Warehouse Stock</th>
                                        <th class="text-center" style="width: 25%">Approve Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($orderRequest->items as $reqItem)
                                        @php
                                            $warehouseStock = $reqItem->item->director_stock;
                                            $isShort = $warehouseStock < $reqItem->requested_quantity;
                                            $isZeroStock = $warehouseStock === 0;
                                            // For re-approval, we need to account for currently approved qty being "returned"
                                            // The available stock is current director_stock + what was already approved for this item
                                            $effectiveAvailable = $warehouseStock + ($orderRequest->status !== 'pending' && in_array($orderRequest->status, ['approved','partially_approved']) ? $reqItem->approved_quantity : 0);
                                            $defaultQty = min($reqItem->requested_quantity, $effectiveAvailable);
                                        @endphp
                                        <tr class="{{ $isShort ? 'table-warning table-warning bg-opacity-25' : '' }}">
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark">{{ $reqItem->item->name }}</div>
                                                <div class="small text-muted">{{ ucfirst($reqItem->item->category) }}</div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-light text-dark fs-6 px-3 py-1 border fw-semibold">
                                                    {{ $reqItem->requested_quantity }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="{{ $isZeroStock ? 'stock-empty' : ($isShort ? 'text-warning fw-bold' : 'stock-ok') }}">
                                                    {{ $warehouseStock }} bottles
                                                </div>
                                                @if($isZeroStock)
                                                    <div class="small text-danger" style="font-size:0.7rem;">❌ Out of Stock</div>
                                                @elseif($isShort)
                                                    <div class="small text-warning" style="font-size:0.7rem;">
                                                        ⚠️ Short by {{ $reqItem->requested_quantity - $warehouseStock }}
                                                    </div>
                                                @else
                                                    <div class="small text-success" style="font-size:0.7rem;">✓ Sufficient</div>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex flex-column align-items-center gap-1">
                                                    <input type="number"
                                                        name="items[{{ $reqItem->item_id }}][approved_quantity]"
                                                        class="approved-qty-input"
                                                        value="{{ $reqItem->approved_quantity > 0 ? $reqItem->approved_quantity : $defaultQty }}"
                                                        min="0"
                                                        data-item-name="{{ $reqItem->item->name }}"
                                                        data-max-stock="{{ $effectiveAvailable }}"
                                                        data-requested="{{ $reqItem->requested_quantity }}"
                                                        oninput="validateQty(this)">
                                                    <div class="small text-muted" id="qty-hint-{{ $reqItem->item_id }}" style="font-size:0.68rem;">
                                                        exceeding dynamically increases stock
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Decision Panel -->
                    <div class="decision-panel mb-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small">Director's Notes / Reason (optional)</label>
                            <textarea name="notes" class="form-control rounded-3" border rows="2"
                                placeholder="e.g. 'Approved with auto-stock adjustments'"
                                >{{ $orderRequest->notes }}</textarea>
                        </div>

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="small text-muted">
                                <i class="bi bi-info-circle me-1 text-primary"></i>
                                Set quantities above then approve or deny.
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="button" class="action-btn-deny" onclick="submitDecision('deny')">
                                    <i class="bi bi-x-circle me-1"></i> Deny
                                </button>
                                <button type="button" class="action-btn-approve" onclick="submitDecision('approve')">
                                    <i class="bi bi-check-circle-fill me-1"></i>
                                    {{ $orderRequest->status === 'pending' ? 'Approve Transfer' : 'Re-Approve / Update' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function validateQty(input) {
    const val = parseInt(input.value) || 0;
    const maxStock = parseInt(input.getAttribute('data-max-stock')) || 0;

    if (val > maxStock) {
        input.style.borderColor = '#d97706'; // Amber highlight for auto-increase
        input.style.color = '#d97706';
    } else {
        input.style.borderColor = '#3b82f6';
        input.style.color = '#1e40af';
    }
}

function submitDecision(action) {
    document.getElementById('action-input').value = action;

    if (action === 'deny') {
        if (confirm('❌ Are you sure you want to DENY this stock request?')) {
            document.getElementById('approval-form').submit();
        }
        return;
    }

    // Approve — validate quantities first
    let totalApproved = 0;
    let autoIncreaseNotice = false;

    document.querySelectorAll('.approved-qty-input').forEach(input => {
        const val = parseInt(input.value) || 0;
        const maxStock = parseInt(input.getAttribute('data-max-stock')) || 0;
        if (val > maxStock) {
            autoIncreaseNotice = true;
        }
        totalApproved += val;
    });

    if (totalApproved === 0) {
        if (!confirm('⚠️ All approved quantities are 0.\n\nThis will effectively DENY the request.\n\nContinue?')) {
            return;
        }
    }

    let msg = `✅ Approve transfer of ${totalApproved} bottles to {{ $orderRequest->bar->name }}?`;
    if (autoIncreaseNotice) {
        msg += `\n\nNote: Approving will automatically increase the Warehouse/Bar Stock for items that exceed current availability.`;
    }

    if (confirm(msg)) {
        document.getElementById('approval-form').submit();
    }
}

function confirmReopen() {
    if (confirm('🔄 Reset this request to Pending?\n\nThe director can re-review and re-approve when ready.')) {
        document.getElementById('reopen-form').submit();
    }
}

// Validate all inputs on page load to highlight any over-stock items
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.approved-qty-input').forEach(input => {
        validateQty(input);
    });
});
</script>
@endsection
