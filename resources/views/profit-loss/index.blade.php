@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .report-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        padding: 2rem;
        margin-bottom: 2rem;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .pl-card {
        background: white;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    .pl-statement {
        padding: 1.5rem 2rem;
    }

    .pl-line {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.65rem 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .pl-line:last-child {
        border-bottom: none;
    }

    .pl-line.section-total {
        border-top: 2px solid #e2e8f0;
        border-bottom: 2px solid #e2e8f0;
        margin-top: 0.5rem;
        padding: 0.85rem 0;
        font-weight: 700;
    }

    .pl-line.grand-total {
        background: #f8fafc;
        margin: 1rem -2rem -1.5rem;
        padding: 1rem 2rem;
        border-top: 2px solid #cbd5e1;
        font-weight: 700;
        font-size: 1.1rem;
    }

    .pl-label {
        color: #475569;
    }

    .pl-label.indent {
        padding-left: 1.25rem;
        font-size: 0.9rem;
    }

    .pl-label .formula-hint {
        display: block;
        font-size: 0.7rem;
        color: #94a3b8;
        font-weight: 400;
        margin-top: 0.15rem;
    }

    .pl-value {
        font-weight: 600;
        color: #1e293b;
        white-space: nowrap;
    }

    .pl-value.positive { color: #059669; }
    .pl-value.negative { color: #dc2626; }
    .pl-value.muted { color: #64748b; }

    .metric-card {
        background: white;
        border-radius: 12px;
        padding: 1.25rem;
        border: 1px solid #e2e8f0;
        height: 100%;
    }

    .metric-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        font-weight: 600;
        margin-bottom: 0.35rem;
    }

    .metric-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }

    .metric-hint {
        font-size: 0.75rem;
        color: #94a3b8;
        margin-top: 0.25rem;
    }

    .date-filter-group label {
        font-size: 0.875rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.5rem;
    }

    .report-table thead {
        background: #f9fafb;
        border-bottom: 2px solid #e5e7eb;
    }

    .report-table th {
        padding: 0.85rem 1rem;
        font-weight: 600;
        color: #374151;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .report-table td {
        padding: 0.85rem 1rem;
        border-bottom: 1px solid #e5e7eb;
        font-size: 0.9rem;
    }

    .report-table tbody tr:hover {
        background: #f9fafb;
    }

    .report-table tbody tr.totals {
        background: #f1f5f9;
        font-weight: 700;
    }

    .mini-trend-bar {
        display: flex;
        align-items: flex-end;
        gap: 0.5rem;
        height: 120px;
        background: #f8fafc;
        border-radius: 12px;
        padding: 1rem;
    }

    .mini-trend-bar > div {
        flex: 1;
        min-width: 10px;
        background: #3b82f6;
        border-radius: 999px 999px 0 0;
    }

    .info-callout {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 10px;
        padding: 1rem 1.25rem;
        font-size: 0.85rem;
        color: #1e40af;
    }

    .empty-state {
        text-align: center;
        padding: 3rem;
    }

    @media (max-width: 768px) {
        .pl-statement { padding: 1rem; }
        .pl-line.grand-total { margin: 1rem -1rem -1rem; padding: 1rem; }
    }
</style>

<div class="container-fluid px-4 py-4">
    <div class="report-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h1 class="h3 fw-bold mb-1 text-dark">Profit & Loss Statement</h1>
                <p class="text-muted mb-0">Income statement for the selected period — costs align with warehouse base-unit pricing</p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">
                    {{ $startDate->format('M d') }} – {{ $endDate->format('M d, Y') }}
                </span>
                @if(isset($selectedBar) && $selectedBar)
                    <span class="badge bg-primary text-white rounded-pill px-3 py-2">{{ $selectedBar->name }}</span>
                @elseif(isset($barId) && $barId === 'all')
                    <span class="badge bg-secondary text-white rounded-pill px-3 py-2">All Locations</span>
                @endif
            </div>
        </div>
    </div>

    <form method="GET" class="row g-3 align-items-end mb-4">
        <div class="col-sm-6 col-md-3">
            <div class="date-filter-group">
                <label for="range">Range</label>
                <select id="range" name="range" class="form-select">
                    <option value="today" {{ ($range ?? 'today') === 'today' ? 'selected' : '' }}>Today</option>
                    <option value="yesterday" {{ ($range ?? '') === 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                    <option value="this_week" {{ ($range ?? '') === 'this_week' ? 'selected' : '' }}>This Week</option>
                    <option value="this_month" {{ ($range ?? '') === 'this_month' ? 'selected' : '' }}>This Month</option>
                    <option value="this_year" {{ ($range ?? '') === 'this_year' ? 'selected' : '' }}>This Year</option>
                    <option value="all" {{ ($range ?? '') === 'all' ? 'selected' : '' }}>All Time</option>
                </select>
            </div>
        </div>
        <div class="col-sm-6 col-md-2">
            <div class="date-filter-group">
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="{{ $startDate->format('Y-m-d') }}" class="form-control">
            </div>
        </div>
        <div class="col-sm-6 col-md-2">
            <div class="date-filter-group">
                <label for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date" value="{{ $endDate->format('Y-m-d') }}" class="form-control">
            </div>
        </div>
        @if(isset($bars) && $bars && $bars->count())
            <div class="col-sm-6 col-md-3">
                <div class="date-filter-group">
                    <label for="bar_id">Location</label>
                    <select id="bar_id" name="bar_id" class="form-select">
                        <option value="all" {{ ($barId ?? 'all') === 'all' ? 'selected' : '' }}>All Bars</option>
                        @foreach($bars as $bar)
                            <option value="{{ $bar->id }}" {{ isset($selectedBar) && $selectedBar && $selectedBar->id == $bar->id ? 'selected' : '' }}>{{ $bar->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif
        <div class="col-sm-12 col-md-2">
            <button type="submit" class="btn btn-primary rounded-pill px-4 w-100">Apply</button>
        </div>
    </form>

    @if(count($reportData) > 0)
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-label">Gross Margin</div>
                    <div class="metric-value {{ ($totals['gross_margin'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">{{ $totals['gross_margin'] ?? 0 }}%</div>
                    <div class="metric-hint">Gross Profit ÷ Revenue</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-label">Net Margin</div>
                    <div class="metric-value {{ $totals['profit_margin'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $totals['profit_margin'] }}%</div>
                    <div class="metric-hint">Net Profit ÷ Revenue</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-label">Expense Ratio</div>
                    <div class="metric-value text-warning">{{ $expensesRatio }}%</div>
                    <div class="metric-hint">Operating Expenses ÷ Revenue</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-label">Active Trading Days</div>
                    <div class="metric-value">{{ count(array_filter($reportData, fn($d) => $d['sales'] > 0)) }}</div>
                    <div class="metric-hint">Days with recorded sales</div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-7">
                <div class="pl-card">
                    <div class="px-4 pt-4 pb-2 border-bottom">
                        <h5 class="fw-bold mb-1">Income Statement</h5>
                        <p class="small text-muted mb-0">Standard P&amp;L format for {{ $startDate->format('M d') }} – {{ $endDate->format('M d, Y') }}</p>
                    </div>
                    <div class="pl-statement">
                        <div class="pl-line">
                            <span class="pl-label fw-semibold">Revenue (Sales)</span>
                            <span class="pl-value">MWK {{ number_format($totals['sales']) }}</span>
                        </div>
                        <div class="pl-line">
                            <span class="pl-label indent">
                                Less: Cost of Goods Sold
                                <span class="formula-hint">Base unit cost × units sold (same as warehouse cost per bottle)</span>
                            </span>
                            <span class="pl-value muted">(MWK {{ number_format($totals['purchase_cost']) }})</span>
                        </div>
                        <div class="pl-line section-total">
                            <span class="pl-label">
                                Gross Profit
                                <span class="formula-hint">Revenue − COGS · Margin: {{ $totals['gross_margin'] ?? 0 }}%</span>
                            </span>
                            <span class="pl-value {{ $totals['gross_profit'] >= 0 ? 'positive' : 'negative' }}">
                                MWK {{ number_format($totals['gross_profit']) }}
                            </span>
                        </div>
                        <div class="pl-line">
                            <span class="pl-label indent">
                                Less: Bar Shift Expenses
                                <span class="formula-hint">Shift till costs (lunch, taxi, etc.) — affects drawer</span>
                            </span>
                            <span class="pl-value muted">(MWK {{ number_format($totals['expenses']) }})</span>
                        </div>
                        <div class="pl-line section-total">
                            <span class="pl-label">
                                Net Bar Profit / (Loss)
                                <span class="formula-hint">Gross Profit − Bar Shift Expenses · Net Margin: {{ $totals['profit_margin'] }}%</span>
                            </span>
                            <span class="pl-value {{ $totals['net_profit'] >= 0 ? 'positive' : 'negative' }}">
                                MWK {{ number_format($totals['net_profit']) }}
                            </span>
                        </div>
                        <div class="pl-line">
                            <span class="pl-label indent">
                                Management Overhead (informational)
                                <span class="formula-hint">Manager/director costs — tracked separately, not subtracted from bar sales</span>
                            </span>
                            <span class="pl-value muted">MWK {{ number_format($totals['overhead_expenses'] ?? 0) }}</span>
                        </div>
                        <div class="pl-line grand-total" style="background: #f1f5f9;">
                            <span class="pl-label">
                                Combined View
                                <span class="formula-hint">Net bar profit minus management overhead (company-level)</span>
                            </span>
                            <span class="pl-value {{ ($totals['net_profit'] - ($totals['overhead_expenses'] ?? 0)) >= 0 ? 'positive' : 'negative' }}">
                                MWK {{ number_format($totals['net_profit'] - ($totals['overhead_expenses'] ?? 0)) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="info-callout mt-3">
                    <strong>How this matches warehouse pricing:</strong> When you add stock in the warehouse, cost per base unit = Total Purchase Cost ÷ (Units Purchased × Conversion Factor). P&amp;L uses that same base-unit cost multiplied by bottles/units actually sold. Warehouse shows <em>markup on cost</em> (profit ÷ cost); this report shows <em>margin on revenue</em> (profit ÷ sales) — both are correct, just different views.
                </div>
            </div>

            <div class="col-lg-5">
                <div class="pl-card p-4 mb-4">
                    <h6 class="fw-bold mb-3">Inventory Snapshot</h6>
                    <div class="pl-line">
                        <span class="pl-label">Opening Stock (units)</span>
                        <span class="pl-value">{{ number_format($openingStock) }}</span>
                    </div>
                    <div class="pl-line">
                        <span class="pl-label">Closing Stock (units)</span>
                        <span class="pl-value">{{ number_format($closingStock) }}</span>
                    </div>
                    <div class="pl-line">
                        <span class="pl-label">Stock at Cost</span>
                        <span class="pl-value muted">MWK {{ number_format($currentStockValue->purchase_value ?? 0) }}</span>
                    </div>
                    <div class="pl-line">
                        <span class="pl-label">Stock at Selling Price</span>
                        <span class="pl-value">MWK {{ number_format($currentStockValue->selling_value ?? 0) }}</span>
                    </div>
                </div>

                <div class="pl-card p-4">
                    <h6 class="fw-bold mb-3">Cash &amp; Collections</h6>
                    <div class="pl-line">
                        <span class="pl-label">Expected Cash</span>
                        <span class="pl-value">MWK {{ number_format($totals['sales'] - $creditSalesOutstanding) }}</span>
                    </div>
                    <div class="pl-line">
                        <span class="pl-label">Recorded Collections</span>
                        <span class="pl-value">MWK {{ number_format(array_sum($paymentMethods)) }}</span>
                    </div>
                    <div class="pl-line">
                        <span class="pl-label">Shortage</span>
                        <span class="pl-value {{ $shortage > 0 ? 'negative' : 'positive' }}">MWK {{ number_format($shortage) }}</span>
                    </div>
                    <div class="pl-line">
                        <span class="pl-label">Credit Outstanding</span>
                        <span class="pl-value negative">MWK {{ number_format($creditSalesOutstanding) }}</span>
                    </div>
                    @if(!empty($paymentMethods))
                        <div class="mt-3 pt-3 border-top">
                            <div class="small text-muted text-uppercase fw-bold mb-2">Payment Breakdown</div>
                            @foreach($paymentMethods as $method => $amount)
                                @if($amount > 0)
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="text-capitalize">{{ str_replace('_', ' ', $method) }}</span>
                                        <span class="fw-semibold">MWK {{ number_format($amount) }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="pl-card mb-4">
            <div class="px-4 pt-4 pb-2 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-1">Daily Breakdown</h5>
                    <p class="small text-muted mb-0">Day-by-day revenue, costs, and profit</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table report-table mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-end">Revenue</th>
                            <th class="text-end">COGS</th>
                            <th class="text-end">Gross Profit</th>
                            <th class="text-end">Expenses</th>
                            <th class="text-end">Net Profit</th>
                            <th class="text-end">Net Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportData as $day)
                            @if($day['sales'] > 0 || $day['expenses'] > 0)
                            <tr>
                                <td class="fw-medium">{{ $day['date'] }}</td>
                                <td class="text-end">MWK {{ number_format($day['sales']) }}</td>
                                <td class="text-end text-muted">MWK {{ number_format($day['purchase_cost']) }}</td>
                                <td class="text-end {{ $day['gross_profit'] >= 0 ? 'text-success' : 'text-danger' }}">MWK {{ number_format($day['gross_profit']) }}</td>
                                <td class="text-end text-warning">MWK {{ number_format($day['expenses']) }}</td>
                                <td class="text-end fw-semibold {{ $day['net_profit'] >= 0 ? 'text-success' : 'text-danger' }}">MWK {{ number_format($day['net_profit']) }}</td>
                                <td class="text-end">{{ $day['profit_margin'] }}%</td>
                            </tr>
                            @endif
                        @endforeach
                        <tr class="totals">
                            <td>Period Total</td>
                            <td class="text-end">MWK {{ number_format($totals['sales']) }}</td>
                            <td class="text-end">MWK {{ number_format($totals['purchase_cost']) }}</td>
                            <td class="text-end">MWK {{ number_format($totals['gross_profit']) }}</td>
                            <td class="text-end">MWK {{ number_format($totals['expenses']) }}</td>
                            <td class="text-end">MWK {{ number_format($totals['net_profit']) }}</td>
                            <td class="text-end">{{ $totals['profit_margin'] }}%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-8">
                <div class="pl-card p-4">
                    <h5 class="fw-bold mb-3">Revenue Trend</h5>
                    <div class="mini-trend-bar mb-2">
                        @foreach($trendData as $point)
                            <div style="height: {{ $trendData->max('value') > 0 ? max(6, ($point['value'] / $trendData->max('value')) * 100) : 6 }}%;" title="{{ $point['label'] }}: MWK {{ number_format($point['value']) }}"></div>
                        @endforeach
                    </div>
                    <div class="d-flex justify-content-between small text-muted">
                        <span>{{ $trendData->first()['label'] ?? '' }}</span>
                        <span>{{ $trendData->last()['label'] ?? '' }}</span>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="pl-card p-4 h-100">
                    <h5 class="fw-bold mb-3">Top Sellers</h5>
                    @if($topItems->isNotEmpty())
                        @foreach($topItems as $item)
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <div class="fw-semibold">{{ $item->item->name ?? 'Unknown' }}</div>
                                    <div class="small text-muted">{{ number_format($item->total_sold) }} units sold</div>
                                </div>
                                <div class="text-end fw-semibold">MWK {{ number_format($item->total_revenue) }}</div>
                            </div>
                        @endforeach
                    @else
                        <p class="text-muted small mb-0">No sales data for this period.</p>
                    @endif
                </div>
            </div>
        </div>

        @if(!empty($barAnalysis) && count($barAnalysis) > 0)
        <div class="pl-card mb-4">
            <div class="px-4 pt-4 pb-2 border-bottom">
                <h5 class="fw-bold mb-1">Location Comparison</h5>
                <p class="small text-muted mb-0">Performance by bar when viewing all locations</p>
            </div>
            <div class="table-responsive">
                <table class="table report-table mb-0">
                    <thead>
                        <tr>
                            <th>Location</th>
                            <th class="text-end">Revenue</th>
                            <th class="text-end">COGS</th>
                            <th class="text-end">Gross Profit</th>
                            <th class="text-end">Expenses</th>
                            <th class="text-end">Net Profit</th>
                            <th class="text-end">Gross Margin</th>
                            <th class="text-end">Net Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($barAnalysis as $bar)
                        <tr>
                            <td class="fw-semibold">{{ $bar['bar_name'] }}</td>
                            <td class="text-end">MWK {{ number_format($bar['sales']) }}</td>
                            <td class="text-end text-muted">MWK {{ number_format($bar['purchase_cost']) }}</td>
                            <td class="text-end">MWK {{ number_format($bar['gross_profit']) }}</td>
                            <td class="text-end text-warning">MWK {{ number_format($bar['expenses']) }}</td>
                            <td class="text-end fw-semibold {{ $bar['profit'] >= 0 ? 'text-success' : 'text-danger' }}">MWK {{ number_format($bar['profit']) }}</td>
                            <td class="text-end">{{ $bar['gross_margin'] }}%</td>
                            <td class="text-end">{{ $bar['margin'] }}%</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <div class="pl-card p-4">
            <h6 class="fw-bold mb-2">Analysis</h6>
            <p class="text-muted mb-2">{{ $interpretation }}</p>
            <div class="d-flex flex-wrap gap-2">
                <span class="badge bg-light text-dark border">Gross margin: {{ $totals['gross_margin'] ?? 0 }}%</span>
                <span class="badge bg-light text-dark border">Net margin: {{ $totals['profit_margin'] }}%</span>
                <span class="badge bg-light text-dark border">Expense ratio: {{ $expensesRatio }}%</span>
                <span class="badge bg-light text-dark border">Cash coverage: {{ $cashCoverage }}%</span>
                @if($creditSalesOutstanding > 0)
                    <span class="badge bg-warning text-dark">Credit at risk: {{ $creditImpactOnCash }}% of revenue</span>
                @endif
            </div>
        </div>
    @else
        <div class="pl-card">
            <div class="empty-state">
                <div style="font-size: 3rem; opacity: 0.3;">📊</div>
                <h3 class="text-dark mb-2">No Data Available</h3>
                <p class="text-muted">No sales or expense data found for the selected date range.</p>
            </div>
        </div>
    @endif
</div>
@endsection
