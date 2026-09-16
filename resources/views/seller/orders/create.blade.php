@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .order-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        padding: 1rem 2rem;
        margin-bottom: 1.5rem;
    }
    .qty-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px !important;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        border: 1px solid #cbd5e1;
        background: white;
        color: #475569;
        font-weight: 600;
    }
    .qty-btn:hover {
        background: #f1f5f9;
    }
    .qty-input {
        width: 56px;
        text-align: center;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-weight: 600;
        margin: 0 8px;
    }
    .category-tab {
        border-radius: 8px !important;
        padding: 0.4rem 1.1rem !important;
        font-weight: 600;
        font-size: 0.875rem;
        border: 1px solid transparent !important;
        margin-right: 0.4rem;
        color: #64748b !important;
    }
    .category-tab.active {
        background-color: #f1f5f9 !important;
        color: var(--pixies-primary) !important;
        border-color: #e2e8f0 !important;
    }
    .search-container {
        max-width: 420px;
    }

    /* Plain, solid floating bottom bar for mobile - no blur/transparency */
    .mobile-floating-action {
        position: fixed;
        bottom: 1rem;
        left: 1rem;
        right: 1rem;
        z-index: 1050;
        transform: translateY(150%);
        transition: transform 0.2s ease;
    }
    .mobile-action-card {
        background: white;
        border: 1px solid #e2e8f0 !important;
        border-radius: 14px;
    }

    @media (max-width: 991.98px) {
        /* Extra padding at the bottom so the floating button doesn't block content */
        .content-padding-bottom {
            padding-bottom: 7.5rem !important;
        }
    }
</style>

<div class="container-fluid p-0 content-padding-bottom">
    <!-- Header -->
    <div class="order-header d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h1 class="h4 fw-bold mb-1 text-dark">Request Stock</h1>
            <div class="small text-muted">
                @if($bar)
                    <i class="bi bi-geo-alt me-1"></i>{{ $bar->name }}
                    <span class="mx-2">·</span>
                @endif
                <i class="bi bi-calendar3 me-1"></i>{{ now()->format('M d, Y') }}
            </div>
        </div>
        <div>
            @php
                $sellerUnseenCount = auth()->user()->bar_id
                    ? \App\Models\OrderRequest::where('bar_id', auth()->user()->bar_id)
                        ->where('status', '!=', 'pending')
                        ->where('seller_notified', false)
                        ->count()
                    : 0;
            @endphp
            <a href="{{ route('seller.orders.index') }}" class="btn btn-outline-secondary btn-sm position-relative">
                <i class="bi bi-clock-history me-1"></i> Order History
                @if($sellerUnseenCount > 0)
                    <span class="badge bg-primary rounded-pill ms-1" style="font-size: 0.65rem;">{{ $sellerUnseenCount }}</span>
                @endif
            </a>
        </div>
    </div>

    <div class="px-4">
        @if (session('success') || session('error') || $errors->any())
            <div class="mb-4">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger border-0 shadow-sm rounded-3" role="alert">
                        <ul class="mb-0 small">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        <!-- Search Bar Section -->
        <div class="search-container mb-4">
            <div class="input-group bg-white border rounded-3">
                <span class="input-group-text border-0 bg-transparent ps-3"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="item-search" class="form-control border-0 shadow-none" placeholder="Search items by name or category..." onkeyup="filterItems()">
                <button class="btn border-0 text-muted pe-3 shadow-none" type="button" onclick="clearSearch()" id="clear-search-btn" style="display:none;">
                    <i class="bi bi-x-circle-fill text-secondary"></i>
                </button>
            </div>
        </div>

        <form method="POST" action="{{ route('seller.orders.store') }}" id="order-request-form">
            @csrf
            
            <div class="row g-4">
                <!-- Items list -->
                <div class="col-lg-8">
                    <!-- Category Tabs -->
                    <ul class="nav nav-pills mb-4 border-0" id="pills-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link category-tab active" id="pills-all-tab" data-bs-toggle="pill" data-bs-target="#pills-all" type="button" role="tab" aria-selected="true">All Items</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link category-tab" id="pills-beer-tab" data-bs-toggle="pill" data-bs-target="#pills-beer" type="button" role="tab" aria-selected="false">Beers</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link category-tab" id="pills-spirit-tab" data-bs-toggle="pill" data-bs-target="#pills-spirit" type="button" role="tab" aria-selected="false">Spirits</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link category-tab" id="pills-soda-tab" data-bs-toggle="pill" data-bs-target="#pills-soda" type="button" role="tab" aria-selected="false">Sodas</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="pills-tabContent">
                        @php
                            $categories = ['all', 'beer', 'spirit', 'soda'];
                        @endphp
                        
                        @foreach($categories as $cat)
                            <div class="tab-pane fade {{ $cat === 'all' ? 'show active' : '' }}" id="pills-{{ $cat }}" role="tabpanel">
                                <div class="card border rounded-3 mb-5">
                                    <div class="table-responsive">
                                        <table class="table align-middle mb-0">
                                            <thead class="table-light text-uppercase text-muted" style="font-size: 0.75rem;">
                                                <tr>
                                                    <th class="ps-4" style="width: 50%">Item / Product</th>
                                                    <th class="text-center" style="width: 20%">Category</th>
                                                    <th class="text-end pe-4" style="width: 30%">Request Quantity (Bottles)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    $filteredItems = $cat === 'all' ? $items : $items->where('category', $cat);
                                                @endphp
                                                @forelse($filteredItems as $item)
                                                    <tr class="item-row" data-item-id="{{ $item->id }}">
                                                        <td class="ps-4 py-3">
                                                            <div class="fw-bold text-dark item-name">{{ $item->name }}</div>
                                                            <div class="small text-muted">{{ $item->description ?? 'No description' }}</div>
                                                            <div class="small text-muted">Stock: {{ $item->director_stock }}</div>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="small text-muted text-capitalize item-category-badge">{{ $item->category }}</span>
                                                        </td>
                                                        <td class="pe-4">
                                                            <div class="d-flex align-items-center justify-content-end">
                                                                <button type="button" class="btn qty-btn dec-btn" data-item-id="{{ $item->id }}">-</button>
                                                                <input type="number"
                                                                    name="items[{{ $item->id }}][quantity]"
                                                                    id="qty-input-{{ $item->id }}"
                                                                    class="form-control qty-input"
                                                                    value="0"
                                                                    min="0"
                                                                    step="1"
                                                                    data-item-name="{{ $item->name }}"
                                                                    onchange="updateTotalRequested()">
                                                                <input type="hidden" name="items[{{ $item->id }}][item_id]" value="{{ $item->id }}">
                                                                <button type="button" class="btn qty-btn inc-btn" data-item-id="{{ $item->id }}">+</button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr class="empty-state-row">
                                                        <td colspan="3" class="text-center text-muted py-5">
                                                            No items in this category.
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Side Summary Panel (Desktop Only) -->
                <div class="col-lg-4 d-none d-lg-block">
                    <div class="card border rounded-3 position-sticky" style="top: 90px;">
                        <div class="card-header bg-white border-bottom py-3">
                            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-receipt me-2 text-primary"></i>Request Summary</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="mb-4">
                                <label class="form-label fw-bold text-secondary">Notes (Optional)</label>
                                <textarea name="notes" id="notes-desktop" class="form-control rounded-3" rows="3" placeholder="Provide extra context, e.g., 'For weekend stock top-up'" onkeyup="syncNotes('desktop')"></textarea>
                            </div>

                            <div class="border-top pt-3 mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Total Unique Items</span>
                                    <span class="fw-bold text-dark" id="summary-unique-items">0</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Total Bottles</span>
                                    <span class="fw-bold text-primary fs-5" id="summary-total-bottles">0</span>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary fw-bold" id="submit-btn" disabled>
                                    <i class="bi bi-send-fill me-2"></i>Submit Stock Request
                                </button>
                                <a href="{{ route('seller.dashboard') }}" class="btn btn-light">
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile/Tablet Floating Submit Action Bar -->
            <div class="mobile-floating-action d-lg-none" id="mobile-floating-panel">
                <div class="card mobile-action-card shadow-sm">
                    <div class="card-body p-3">
                        <!-- Notes Accordion/Collapse in Mobile Floating Panel -->
                        <div class="collapse mb-2" id="mobile-notes-collapse">
                            <div class="pt-1 pb-2">
                                <label class="form-label small fw-bold text-secondary">Notes (Optional)</label>
                                <textarea name="notes_mobile" id="notes-mobile" class="form-control rounded-3 small" rows="2" placeholder="Provide extra context..." onkeyup="syncNotes('mobile')"></textarea>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between gap-3">
                            <div class="ps-2">
                                <span class="small text-muted d-block fw-semibold" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px;">Requested</span>
                                <span class="h5 mb-0 fw-bold text-primary">
                                    <span id="mobile-summary-total-bottles">0</span>
                                    <span class="small text-muted fs-6" style="font-weight: 500;">btls</span>
                                </span>
                            </div>
                            <div class="vr my-1 bg-secondary bg-opacity-20" style="width: 1px; height: 28px;"></div>
                            <button type="button" class="btn btn-light rounded-circle p-2" data-bs-toggle="collapse" data-bs-target="#mobile-notes-collapse" title="Add Notes">
                                <i class="bi bi-pencil-square text-secondary fs-5"></i>
                            </button>
                            <div class="flex-grow-1">
                                <button type="submit" class="btn btn-primary w-100 fw-bold" id="mobile-submit-btn" disabled>
                                    <i class="bi bi-send-fill me-2"></i>Send Request
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set up quantity button handlers
    document.querySelectorAll('.inc-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const itemId = this.getAttribute('data-item-id');
            const input = document.getElementById('qty-input-' + itemId);
            let val = parseInt(input.value) || 0;
            input.value = val + 1;
            
            // Sync with all categories inputs if duplicated (e.g. in "All Items" and "Beers" tabs)
            syncDuplicateInputs(itemId, val + 1);
            updateTotalRequested();
        });
    });

    document.querySelectorAll('.dec-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const itemId = this.getAttribute('data-item-id');
            const input = document.getElementById('qty-input-' + itemId);
            let val = parseInt(input.value) || 0;
            if (val > 0) {
                input.value = val - 1;
                syncDuplicateInputs(itemId, val - 1);
                updateTotalRequested();
            }
        });
    });

    function syncDuplicateInputs(itemId, value) {
        document.querySelectorAll('input[id="qty-input-' + itemId + '"]').forEach(inp => {
            inp.value = value;
        });
    }

    // Live sync notes between desktop and mobile fields
    window.syncNotes = function(source) {
        const desktopNotes = document.getElementById('notes-desktop');
        const mobileNotes = document.getElementById('notes-mobile');
        
        if (source === 'desktop' && mobileNotes) {
            mobileNotes.value = desktopNotes.value;
        } else if (source === 'mobile' && desktopNotes) {
            desktopNotes.value = mobileNotes.value;
        }
    }

    window.updateTotalRequested = function() {
        let totalBottles = 0;
        let uniqueItems = 0;
        
        const inputs = Array.from(document.querySelectorAll('.qty-input'));
        const processed = new Set();
        
        inputs.forEach(input => {
            const id = input.id;
            if (processed.has(id)) return;
            processed.add(id);
            
            const val = parseInt(input.value) || 0;
            if (val > 0) {
                totalBottles += val;
                uniqueItems++;
            }
        });

        // Update Desktop displays
        document.getElementById('summary-unique-items').innerText = uniqueItems;
        document.getElementById('summary-total-bottles').innerText = totalBottles;

        // Update Mobile displays
        document.getElementById('mobile-summary-total-bottles').innerText = totalBottles;

        // Sync buttons state
        const submitBtn = document.getElementById('submit-btn');
        const mobileSubmitBtn = document.getElementById('mobile-submit-btn');
        const mobileFloatingPanel = document.getElementById('mobile-floating-panel');

        if (totalBottles > 0) {
            submitBtn.removeAttribute('disabled');
            mobileSubmitBtn.removeAttribute('disabled');
            
            // Slide in mobile floating panel elegantly
            if (mobileFloatingPanel) {
                mobileFloatingPanel.style.transform = 'translateY(0)';
            }
        } else {
            submitBtn.setAttribute('disabled', 'disabled');
            mobileSubmitBtn.setAttribute('disabled', 'disabled');
            
            // Slide out mobile floating panel cleanly
            if (mobileFloatingPanel) {
                mobileFloatingPanel.style.transform = 'translateY(150%)';
            }
        }
    }

    // Dynamic Filter/Search Functionality
    window.filterItems = function() {
        const query = document.getElementById('item-search').value.toLowerCase().trim();
        const clearBtn = document.getElementById('clear-search-btn');
        
        if (query.length > 0) {
            clearBtn.style.display = 'block';
        } else {
            clearBtn.style.display = 'none';
        }

        // Loop through all tables and filter rows
        document.querySelectorAll('.tab-pane').forEach(pane => {
            const rows = pane.querySelectorAll('tbody tr.item-row');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const name = row.querySelector('.item-name').innerText.toLowerCase();
                const description = row.querySelector('.text-muted').innerText.toLowerCase();
                const category = row.querySelector('.item-category-badge').innerText.toLowerCase();
                
                if (name.includes(query) || description.includes(query) || category.includes(query)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Handle empty state inside this tab
            const tbody = pane.querySelector('tbody');
            let emptyState = tbody.querySelector('.no-results-row');
            
            if (visibleCount === 0) {
                if (!emptyState) {
                    emptyState = document.createElement('tr');
                    emptyState.className = 'no-results-row';
                    emptyState.innerHTML = `
                        <td colspan="3" class="text-center py-5 text-muted">
                            <i class="bi bi-search fs-4 mb-2 d-block"></i>
                            <div class="small fw-semibold">No items match your search</div>
                            <div class="text-muted small">Try checking spelling or changing tabs</div>
                        </td>
                    `;
                    tbody.appendChild(emptyState);
                }
            } else {
                if (emptyState) {
                    emptyState.remove();
                }
            }
        });
    }

    window.clearSearch = function() {
        document.getElementById('item-search').value = '';
        filterItems();
    }
});
</script>
@endsection
