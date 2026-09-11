<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        
        <title>{{ config('app.name', 'Pixies Bar Management') }}</title>

        <!-- Google Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
        
        <!-- Bootstrap & Icons -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        
        <!-- Custom Design System -->
        <link rel="stylesheet" href="{{ asset('css/pixies.css') }}">
        
        <style>
            :root {
                --sidebar-width: 280px;
                --header-height: 70px;
                --navy-deep: #0f172a;
                --slate-600: #475569;
                --slate-800: #1e293b;
            }

            body {
                font-family: 'Plus Jakarta Sans', sans-serif;
                background-color: #f8fafc;
                overflow-x: hidden;
            }

            /* Premium Sidebar */
            #sidebar {
                width: var(--sidebar-width);
                height: 100vh;
                background: var(--navy-deep);
                position: fixed;
                left: 0;
                top: 0;
                z-index: 1050;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                color: rgba(255, 255, 255, 0.7);
                border-right: 1px solid rgba(255, 255, 255, 0.05);
            }

            .sidebar-header {
                height: var(--header-height);
                display: flex;
                align-items: center;
                padding: 0 1.5rem;
                background: rgba(255, 255, 255, 0.02);
            }

            .nav-group-title {
                font-size: 0.65rem;
                text-transform: uppercase;
                letter-spacing: 0.1em;
                color: rgba(255, 255, 255, 0.4);
                padding: 1.5rem 1.5rem 0.5rem;
                font-weight: 700;
            }

            .sidebar-nav-link {
                display: flex;
                align-items: center;
                padding: 0.75rem 1.5rem;
                color: rgba(255, 255, 255, 0.7);
                text-decoration: none;
                transition: all 0.2s;
                font-weight: 500;
                font-size: 0.9rem;
                border-left: 3px solid transparent;
            }

            .sidebar-nav-link i {
                font-size: 1.1rem;
                margin-right: 0.75rem;
                transition: transform 0.2s;
            }

            .sidebar-nav-link:hover {
                color: white;
                background: rgba(255, 255, 255, 0.05);
            }

            .sidebar-nav-link:hover i {
                transform: scale(1.1);
            }

            .sidebar-nav-link.active {
                color: white;
                background: rgba(59, 130, 246, 0.1);
                border-left-color: #3b82f6;
            }

            /* Main Area */
            #main-wrapper {
                margin-left: var(--sidebar-width);
                min-height: 100vh;
                transition: all 0.3s ease;
            }

            .top-navbar {
                height: var(--header-height);
                background: white;
                border-bottom: 1px solid #e2e8f0;
                padding: 0 1.5rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
                position: sticky;
                top: 0;
                z-index: 1000;
            }

            /* Responsive */
            @media (max-width: 1024px) {
                #sidebar { transform: translateX(-100%); }
                #sidebar.active { transform: translateX(0); }
                #main-wrapper { margin-left: 0; }
                .mobile-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(15, 23, 42, 0.5);
                    backdrop-filter: blur(4px);
                    z-index: 1040;
                    display: none;
                }
                .mobile-overlay.active { display: block; }
            }

            /* User Profile Widget */
            .user-pill {
                padding: 0.5rem 1rem;
                border-radius: 999px;
                background: #f1f5f9;
                border: 1px solid #e2e8f0;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                cursor: pointer;
                transition: all 0.2s;
            }
            .user-pill:hover { background: #e2e8f0; }

            .avatar-sm {
                width: 32px;
                height: 32px;
                background: var(--slate-800);
                color: white;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                font-size: 0.8rem;
            }
        </style>
    </head>
    <body>
        <div class="mobile-overlay" id="sidebarOverlay"></div>

        <!-- Sidebar -->
        <aside id="sidebar">
            <div class="sidebar-header">
                <span class="h5 fw-bold mb-0 text-white"><i class="bi bi-pentagon-fill text-primary me-2"></i>Pixies Bar</span>
            </div>

            <div class="overflow-y-auto" style="height: calc(100vh - 150px);">
                @if(auth()->user()->isSeller())
                    <a href="{{ route('seller.dashboard') }}" class="sidebar-nav-link {{ request()->routeIs('seller.dashboard') ? 'active' : '' }}">
                        <i class="bi bi-grid-1x2"></i> Dashboard
                    </a>
                    <a href="{{ route('stock-entries.sell') }}" class="sidebar-nav-link {{ request()->routeIs('stock-entries.index', 'stock-entries.create', 'stock-entries.edit', 'stock-entries.sell') ? 'active' : '' }}">
                        <i class="bi bi-cart-check"></i> Sell
                    </a>
                    <a href="{{ route('stock-expiry.index') }}" class="sidebar-nav-link {{ request()->routeIs('stock-expiry.*') ? 'active' : '' }}">
                        <i class="bi bi-exclamation-triangle"></i> Stock Expiry
                    </a>
                    <a href="{{ route('seller.orders.create') }}" class="sidebar-nav-link {{ request()->routeIs('seller.orders.create') ? 'active' : '' }}">
                        <i class="bi bi-cart-plus"></i> Order
                    </a>
                    <a href="{{ route('seller.orders.index') }}" class="sidebar-nav-link {{ request()->routeIs('seller.orders.index') ? 'active' : '' }} d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-receipt"></i> Order History</span>
                            @php
                                $sellerUnseenCount = auth()->user()->bar_id
                                    ? \App\Models\OrderRequest::where('bar_id', auth()->user()->bar_id)
                                        ->where('status', '!=', 'pending')
                                        ->where('seller_notified', false)
                                        ->count()
                                    : 0;
                            @endphp
                        @if($sellerUnseenCount > 0)
                            <span class="badge bg-primary rounded-pill" style="font-size: 0.65rem;">{{ $sellerUnseenCount }}</span>
                        @endif
                    </a>
                    @php
                        $todayCastelCount = auth()->user()->bar_id
                            ? \App\Models\BottleCount::whereDate('date', now()->toDateString())
                                ->where('bar_id', auth()->user()->bar_id)
                                ->sum('counted')
                            : 0;
                    @endphp
                    <a href="{{ route('castel.index') }}" class="sidebar-nav-link {{ request()->routeIs('castel.*') ? 'active' : '' }} d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-bottle"></i> Castel</span>
                        @if($todayCastelCount > 0)
                            <span class="badge bg-success rounded-pill" style="font-size: 0.65rem;">{{ $todayCastelCount }}</span>
                        @endif
                    </a>
                    <a href="{{ route('reporting.index') }}" class="sidebar-nav-link {{ request()->routeIs('reporting.*') ? 'active' : '' }}">
                        <i class="bi bi-clipboard-check"></i> Balance
                    </a>
                    <a href="{{ route('credit-customers.index') }}" class="sidebar-nav-link {{ request()->routeIs('credit-customers.*') ? 'active' : '' }}">
                        <i class="bi bi-person-lines-fill"></i> Ngongole
                    </a>
                    <a href="{{ route('damaged-goods.index') }}" class="sidebar-nav-link {{ request()->routeIs('damaged-goods.*') ? 'active' : '' }}">
                        <i class="bi bi-bandaid"></i> Damaged Goods
                    </a>
                @elseif(auth()->user()->isManager() || auth()->user()->isDirector())
                    <a href="{{ auth()->user()->isDirector() ? route('director.dashboard') : route('manager.dashboard') }}" class="sidebar-nav-link {{ request()->routeIs(auth()->user()->isDirector() ? 'director.dashboard' : 'manager.dashboard') ? 'active' : '' }}">
                        <i class="bi bi-grid-1x2"></i> {{ auth()->user()->isDirector() ? 'Executive Overview' : 'Dashboard' }}
                    </a>
                    <a href="{{ route('stock.index') }}" class="sidebar-nav-link {{ request()->routeIs('stock.*') ? 'active' : '' }}">
                        <i class="bi bi-box-seam"></i> Stock
                    </a>
                    @php
                        $directorTodayCastel = \App\Models\BottleCount::whereDate('date', now()->toDateString())->sum('counted');
                    @endphp
                    <a href="{{ route('castel.index') }}" class="sidebar-nav-link {{ request()->routeIs('castel.*') ? 'active' : '' }} d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-bottle"></i> Castel Count</span>
                        @if($directorTodayCastel > 0)
                            <span class="badge bg-success rounded-pill" style="font-size: 0.65rem;">{{ $directorTodayCastel }}</span>
                        @endif
                    </a>
                    @if(auth()->user()->isDirector())
                        <a href="{{ route('director.orders.index') }}" class="sidebar-nav-link {{ request()->routeIs('director.orders.*') ? 'active' : '' }} d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-check-circle"></i> Stock Requests</span>
                            @php
                                $pendingOrdersCount = \App\Models\OrderRequest::unseenPendingCountForDirector();
                            @endphp
                            @if($pendingOrdersCount > 0)
                                <span class="badge bg-warning text-dark rounded-pill" style="font-size: 0.65rem;">{{ $pendingOrdersCount }}</span>
                            @endif
                        </a>
                    @endif
                    <a href="{{ route('warehouse.index') }}" class="sidebar-nav-link {{ request()->routeIs('warehouse.*') ? 'active' : '' }}">
                        <i class="bi bi-warehouse"></i> Stock Warehouse
                    </a>
                    <a href="{{ route('stock-expiry.index') }}" class="sidebar-nav-link {{ request()->routeIs('stock-expiry.*') ? 'active' : '' }}">
                        <i class="bi bi-exclamation-triangle"></i> Stock Expiry
                    </a>
                    <a href="{{ route('profit-loss.index') }}" class="sidebar-nav-link {{ request()->routeIs('profit-loss.*') ? 'active' : '' }}">
                        <i class="bi bi-graph-up"></i> Profit & Loss
                    </a>
                    <a href="{{ route('reconciliation.index') }}" class="sidebar-nav-link {{ request()->routeIs('reconciliation.*') ? 'active' : '' }}">
                        <i class="bi bi-shield-check"></i> Cash Audit
                    </a>
                    <a href="{{ route('expenses.index') }}" class="sidebar-nav-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}">
                        <i class="bi bi-receipt"></i> Expenses
                    </a>
                    <a href="{{ route('damaged-goods.index') }}" class="sidebar-nav-link {{ request()->routeIs('damaged-goods.*') ? 'active' : '' }}">
                        <i class="bi bi-bandaid"></i> Damaged Goods
                    </a>
                    <a href="{{ route('credit-customers.index') }}" class="sidebar-nav-link {{ request()->routeIs('credit-customers.*') ? 'active' : '' }}">
                        <i class="bi bi-person-lines-fill"></i> Credit Tabs
                    </a>
                    <a href="{{ route('reports.dashboard') }}" class="sidebar-nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                        <i class="bi bi-bar-chart-line"></i> Performance
                    </a>
                    <a href="{{ route('activity-logs.index') }}" class="sidebar-nav-link {{ request()->routeIs('activity-logs.*') ? 'active' : '' }}">
                        <i class="bi bi-clock-history"></i> Activity Log
                    </a>
                @endif
            </div>

            <div class="position-absolute bottom-0 w-100 p-3">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-danger w-100 rounded-pill py-2 shadow-sm">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </button>
                </form>
            </div>
        </aside>

        <!-- Content Area -->
        <div id="main-wrapper">
            <header class="top-navbar shadow-sm">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-link d-lg-none p-0 text-dark" id="sidebarToggle">
                        <i class="bi bi-list fs-3"></i>
                    </button>
                    <h5 class="fw-bold text-dark mb-0 d-none d-sm-block">{{ $pageTitle ?? 'Dashboard' }}</h5>
                </div>

                <div class="d-flex align-items-center gap-3">
                    @if(auth()->user()->isDirector())
                        @php
                            $headerPendingCount = \App\Models\OrderRequest::unseenPendingCountForDirector();
                        @endphp
                        <a href="{{ route('director.orders.index') }}" class="btn btn-link position-relative text-dark p-1 me-2" title="Pending Stock Requests" style="text-decoration: none;">
                            <i class="bi bi-bell fs-5"></i>
                            @if($headerPendingCount > 0)
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.55rem; padding: 0.25em 0.45em;">
                                    {{ $headerPendingCount }}
                                </span>
                            @endif
                        </a>
                    @endif
                    <div class="text-end d-none d-md-block">
                        <div class="small fw-bold text-dark">{{ auth()->user()->name }}</div>
                        <div class="small text-muted" style="font-size: 0.65rem;">{{ strtoupper(auth()->user()->role) }}</div>
                    </div>
                    <div class="dropdown">
                        <div class="user-pill dropdown-toggle" data-bs-toggle="dropdown">
                            <div class="avatar-sm">{{ substr(auth()->user()->name, 0, 1) }}</div>
                        </div>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 mt-2">
                            <li><a class="dropdown-item py-2" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>Profile Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item py-2 text-danger"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <main>
                @yield('content')
            </main>
        </div>

        <!-- Core JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                const toggle = document.getElementById('sidebarToggle');

                const toggleSidebar = () => {
                    sidebar.classList.toggle('active');
                    overlay.classList.toggle('active');
                }

                if(toggle) toggle.addEventListener('click', toggleSidebar);
                if(overlay) overlay.addEventListener('click', toggleSidebar);
            });
        </script>
    </body>
</html>
