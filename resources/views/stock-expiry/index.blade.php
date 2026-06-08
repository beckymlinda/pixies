@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .expiry-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        padding: 2rem;
        margin-bottom: 2rem;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }
    
    .expiry-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .expiry-critical {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .expiry-warning {
        background: #fef08a;
        color: #854d0e;
    }
    
    .expiry-ok {
        background: #dcfce7;
        color: #166534;
    }
    
    .expiry-item {
        border-left: 4px solid;
        padding: 1.25rem;
        margin-bottom: 1rem;
        border-radius: 8px;
        background: white;
        transition: all 0.2s;
    }
    
    .expiry-item:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transform: translateX(4px);
    }
    
    .expiry-item.critical {
        border-left-color: #dc2626;
        background: #fef2f2;
    }
    
    .expiry-item.warning {
        border-left-color: #f59e0b;
        background: #fffbeb;
    }
    
    .expiry-item.ok {
        border-left-color: #10b981;
        background: #f0fdf4;
    }
    
    .days-remaining {
        font-size: 1.5rem;
        font-weight: bold;
        min-width: 80px;
        text-align: right;
    }
    
    .item-info {
        flex: 1;
    }
    
    .item-name {
        font-size: 1.125rem;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 0.5rem;
    }
    
    .item-meta {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    
    .meta-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #6b7280;
        font-size: 0.875rem;
    }

    @media (max-width: 768px) {
        .expiry-header {
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .expiry-item {
            padding: 1rem;
            margin-bottom: 0.75rem;
        }

        .item-name {
            font-size: 1rem;
        }

        .item-meta {
            gap: 0.5rem;
            flex-direction: column;
        }

        .meta-item {
            font-size: 0.75rem;
        }

        .days-remaining {
            font-size: 1.25rem;
            min-width: 60px;
        }

        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
        }

        .filter-tab {
            padding: 0.4rem 1rem;
            font-size: 0.75rem;
            white-space: nowrap;
        }

        .expiry-header .d-flex {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 1rem !important;
        }

        .expiry-header .badge {
            width: 100%;
            justify-content: center;
        }
    }
    
    .empty-state {
        text-align: center;
        padding: 3rem;
    }
    
    .empty-state-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.3;
    }
    
    .filter-tabs {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }
    
    .filter-tab {
        padding: 0.5rem 1.25rem;
        border-radius: 20px;
        border: 2px solid #e5e7eb;
        background: white;
        cursor: pointer;
        transition: all 0.2s;
        font-weight: 500;
        color: #6b7280;
    }
    
    .filter-tab.active {
        background: var(--pixies-primary);
        color: white;
        border-color: var(--pixies-primary);
    }
    
    .filter-tab:hover {
        border-color: var(--pixies-primary);
    }
</style>

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="expiry-header mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h1 class="h3 fw-bold mb-1 text-dark">Stock Expiry Management</h1>
                <p class="text-muted mb-0">Track items approaching their expiry dates</p>
            </div>
            <div class="d-flex gap-2">
                <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">
                    📅 {{ now()->format('l, M d, Y') }}
                </span>
                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold">
                    {{ $expiryItems->count() }} Item{{ $expiryItems->count() !== 1 ? 's' : '' }}
                </span>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <button class="filter-tab active" onclick="filterItems('all')">All Items</button>
        <button class="filter-tab" onclick="filterItems('critical')">🔴 Critical (0-7 days)</button>
        <button class="filter-tab" onclick="filterItems('warning')">🟡 Warning (8-30 days)</button>
        <button class="filter-tab" onclick="filterItems('ok')">🟢 OK (30+ days)</button>
    </div>

    <!-- Items List -->
    @if($expiryItems->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="empty-state">
                <div class="empty-state-icon">✅</div>
                <h3 class="text-dark mb-2">No Items Expiring Soon</h3>
                <p class="text-muted">All your stock items are fresh and safe to sell.</p>
            </div>
        </div>
    @else
        @php
            $critical = [];
            $warning = [];
            $ok = [];
            $now = \Carbon\Carbon::now();
            
            foreach($expiryItems as $item) {
                $daysRemaining = (int) $item->expiry_date->diffInDays($now);
                
                if ($daysRemaining <= 7) {
                    $critical[] = compact('item', 'daysRemaining');
                } elseif ($daysRemaining <= 30) {
                    $warning[] = compact('item', 'daysRemaining');
                } else {
                    $ok[] = compact('item', 'daysRemaining');
                }
            }
        @endphp

        <div id="all-container">
            <!-- Critical Items -->
            @if(!empty($critical))
                <div class="mb-4">
                    <h5 class="text-danger fw-bold mb-3">🔴 Critical - Expiring Soon (0-7 days)</h5>
                    @foreach($critical as $data)
                        @php $item = $data['item']; $daysRemaining = $data['daysRemaining']; @endphp
                        <div class="expiry-item critical" data-filter="critical">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="item-info flex-grow-1">
                                    <div class="item-name">{{ $item->item->name }}</div>
                                    <div class="item-meta">
                                        <div class="meta-item">
                                            {{ $item->stockEntry->bar->name ?? 'N/A' }}
                                        </div>
                                        <div class="meta-item">
                                            Expires: <strong>{{ $item->expiry_date->format('M d') }}</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="expiry-badge expiry-critical">
                                        {{ $daysRemaining }}d
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- Warning Items -->
            @if(!empty($warning))
                <div class="mb-4">
                    <h5 class="text-warning fw-bold mb-3">🟡 Warning - Expiring Soon (8-30 days)</h5>
                    @foreach($warning as $data)
                        @php $item = $data['item']; $daysRemaining = $data['daysRemaining']; @endphp
                        <div class="expiry-item warning" data-filter="warning">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="item-info flex-grow-1">
                                    <div class="item-name">{{ $item->item->name }}</div>
                                    <div class="item-meta">
                                        <div class="meta-item">
                                            {{ $item->stockEntry->bar->name ?? 'N/A' }}
                                        </div>
                                        <div class="meta-item">
                                            Expires: <strong>{{ $item->expiry_date->format('M d') }}</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="expiry-badge expiry-warning">
                                        {{ $daysRemaining }}d
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- OK Items -->
            @if(!empty($ok))
                <div class="mb-4">
                    <h5 class="text-success fw-bold mb-3">🟢 OK - Fresh Stock (30+ days)</h5>
                    @foreach($ok as $data)
                        @php $item = $data['item']; $daysRemaining = $data['daysRemaining']; @endphp
                        <div class="expiry-item ok" data-filter="ok">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="item-info flex-grow-1">
                                    <div class="item-name">{{ $item->item->name }}</div>
                                    <div class="item-meta">
                                        <div class="meta-item">
                                            {{ $item->stockEntry->bar->name ?? 'N/A' }}
                                        </div>
                                        <div class="meta-item">
                                            Expires: <strong>{{ $item->expiry_date->format('M d') }}</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="expiry-badge expiry-ok">
                                        {{ $daysRemaining }}d
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Critical-only view -->
        <div id="critical-container" style="display: none;">
            @foreach($critical as $data)
                @php $item = $data['item']; $daysRemaining = $data['daysRemaining']; @endphp
                <div class="expiry-item critical">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="item-info">
                            <div class="item-name">{{ $item->item->name }}</div>
                            <div class="item-meta">
                                <div class="meta-item">
                                    📦 Stock Entry: {{ $item->stockEntry->bar->name ?? 'N/A' }}
                                </div>
                                <div class="meta-item">
                                    📅 Expiry: <strong>{{ $item->expiry_date->format('M d, Y') }}</strong>
                                </div>
                                <div class="meta-item">
                                    ⏱️ Date Entered: {{ $item->created_at->format('M d, Y') }}
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="expiry-badge expiry-critical">
                                {{ $daysRemaining }} day{{ $daysRemaining !== 1 ? 's' : '' }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
            @if(empty($critical))
                <div class="card border-0 shadow-sm">
                    <div class="empty-state">
                        <div class="empty-state-icon">✅</div>
                        <h3 class="text-dark mb-2">No Critical Items</h3>
                        <p class="text-muted">No items expiring within 7 days.</p>
                    </div>
                </div>
            @endif
        </div>

        <!-- Warning-only view -->
        <div id="warning-container" style="display: none;">
            @foreach($warning as $data)
                @php $item = $data['item']; $daysRemaining = $data['daysRemaining']; @endphp
                <div class="expiry-item warning">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="item-info">
                            <div class="item-name">{{ $item->item->name }}</div>
                            <div class="item-meta">
                                <div class="meta-item">
                                    📦 Stock Entry: {{ $item->stockEntry->bar->name ?? 'N/A' }}
                                </div>
                                <div class="meta-item">
                                    📅 Expiry: <strong>{{ $item->expiry_date->format('M d, Y') }}</strong>
                                </div>
                                <div class="meta-item">
                                    ⏱️ Date Entered: {{ $item->created_at->format('M d, Y') }}
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="expiry-badge expiry-warning">
                                {{ $daysRemaining }} day{{ $daysRemaining !== 1 ? 's' : '' }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
            @if(empty($warning))
                <div class="card border-0 shadow-sm">
                    <div class="empty-state">
                        <div class="empty-state-icon">✅</div>
                        <h3 class="text-dark mb-2">No Warning Items</h3>
                        <p class="text-muted">No items expiring within 8-30 days.</p>
                    </div>
                </div>
            @endif
        </div>

        <!-- OK-only view -->
        <div id="ok-container" style="display: none;">
            @foreach($ok as $data)
                @php $item = $data['item']; $daysRemaining = $data['daysRemaining']; @endphp
                <div class="expiry-item ok">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="item-info">
                            <div class="item-name">{{ $item->item->name }}</div>
                            <div class="item-meta">
                                <div class="meta-item">
                                    📦 Stock Entry: {{ $item->stockEntry->bar->name ?? 'N/A' }}
                                </div>
                                <div class="meta-item">
                                    📅 Expiry: <strong>{{ $item->expiry_date->format('M d, Y') }}</strong>
                                </div>
                                <div class="meta-item">
                                    ⏱️ Date Entered: {{ $item->created_at->format('M d, Y') }}
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="expiry-badge expiry-ok">
                                {{ $daysRemaining }} day{{ $daysRemaining !== 1 ? 's' : '' }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
            @if(empty($ok))
                <div class="card border-0 shadow-sm">
                    <div class="empty-state">
                        <div class="empty-state-icon">✅</div>
                        <h3 class="text-dark mb-2">No Fresh Stock</h3>
                        <p class="text-muted">No items with 30+ days until expiry.</p>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>

<script>
    function filterItems(filter) {
        // Update active tab
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        event.target.classList.add('active');

        // Show/hide containers
        document.getElementById('all-container').style.display = filter === 'all' ? 'block' : 'none';
        document.getElementById('critical-container').style.display = filter === 'critical' ? 'block' : 'none';
        document.getElementById('warning-container').style.display = filter === 'warning' ? 'block' : 'none';
        document.getElementById('ok-container').style.display = filter === 'ok' ? 'block' : 'none';
    }
</script>
@endsection
