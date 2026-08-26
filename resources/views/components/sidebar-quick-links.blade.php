<div class="sidebar-quick-links">
    <style>
        .sidebar-quick-links {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .sidebar-title {
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .sidebar-links {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            color: #374151;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .sidebar-link:hover {
            background: #f3f4f6;
            color: var(--pixies-primary, #0066cc);
            border-left-color: var(--pixies-primary, #0066cc);
        }

        .sidebar-link.active {
            background: var(--pixies-primary-light, #e3f2fd);
            color: var(--pixies-primary, #0066cc);
            border-left-color: var(--pixies-primary, #0066cc);
        }

        .sidebar-link-icon {
            font-size: 1.25rem;
            min-width: 24px;
            text-align: center;
        }

        .sidebar-link-text {
            flex: 1;
        }

        .sidebar-link-badge {
            background: #fbbf24;
            color: #78350f;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: bold;
        }

        .sidebar-divider {
            height: 1px;
            background: #f1f5f9;
            margin: 1rem 0;
        }

        @media (max-width: 768px) {
            .sidebar-quick-links {
                margin-bottom: 2rem;
            }
        }
    </style>

    <div class="sidebar-title">📊 Reports & Tools</div>
    
    <div class="sidebar-links">
        <a href="{{ route('stock-expiry.index') }}" class="sidebar-link {{ request()->routeIs('stock-expiry.*') ? 'active' : '' }}">
            <div class="sidebar-link-icon">📦</div>
            <div class="sidebar-link-text">Stock Expiry</div>
            @php
                $expiringCount = \App\Models\StockEntryItem::whereNotNull('expiry_date')
                    ->where('expiry_date', '>=', \Carbon\Carbon::now())
                    ->where('expiry_date', '<=', \Carbon\Carbon::now()->addDays(30))
                    ->count();
            @endphp
            @if($expiringCount > 0)
                <div class="sidebar-link-badge">{{ $expiringCount }}</div>
            @endif
        </a>

        @if(auth()->user()->isManager() || auth()->user()->isDirector())
        <a href="{{ route('activity-logs.index') }}" class="sidebar-link {{ request()->routeIs('activity-logs.*') ? 'active' : '' }}">
            <div class="sidebar-link-icon">📋</div>
            <div class="sidebar-link-text">Activity Log</div>
        </a>
        @endif

        <a href="{{ route('profit-loss.index') }}" class="sidebar-link {{ request()->routeIs('profit-loss.*') ? 'active' : '' }}">
            <div class="sidebar-link-icon">📈</div>
            <div class="sidebar-link-text">Profit & Loss</div>
        </a>
    </div>
</div>
