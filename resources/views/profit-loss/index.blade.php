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

    .summary-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        border: 1px solid #e2e8f0;
        text-align: center;
    }

    .summary-card.positive {
        border-left: 4px solid #10b981;
    }

    .summary-card.negative {
        border-left: 4px solid #ef4444;
    }

    .summary-card.neutral {
        border-left: 4px solid #6366f1;
    }

    .summary-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .summary-value {
        font-size: 1.875rem;
        font-weight: bold;
    }

    .summary-card.positive .summary-value {
        color: #10b981;
    }

    .summary-card.negative .summary-value {
        color: #ef4444;
    }

    .summary-card.neutral .summary-value {
        color: #6366f1;
    }

    .date-filter {
        display: flex;
        gap: 1rem;
        align-items: flex-end;
        flex-wrap: wrap;
        margin-bottom: 2rem;
    }

    .date-filter-group {
        display: flex;
        flex-direction: column;
    }

    .date-filter-group label {
        font-size: 0.875rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.5rem;
    }

    .date-filter-group input,
    .date-filter-group select {
        padding: 0.5rem 1rem;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 0.875rem;
        width: 100%;
    }

    .report-table-container {
        background: white;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .report-table {
        width: 100%;
        margin-bottom: 0;
    }

    .mini-trend-bar {
        display: flex;
        align-items: flex-end;
        gap: 0.5rem;
        height: 130px;
        background: #f8fafc;
        border-radius: 18px;
        padding: 1rem;
    }

    .mini-trend-bar > div {
        flex: 1;
        min-width: 12px;
        background: #3b82f6;
        border-radius: 999px 999px 0 0;
        transition: transform 0.2s ease;
    }

    .mini-trend-bar > div:hover {
        transform: translateY(-3px);
    }

    .mini-trend-labels {
        display: flex;
        justify-content: space-between;
        font-size: 0.8rem;
        color: #64748b;
    }

    .report-table thead {
        background: #f9fafb;
        border-bottom: 2px solid #e5e7eb;
    }

    .report-table th {
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: #374151;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .report-table td {
        padding: 1rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .report-table tbody tr:hover {
        background: #f9fafb;
    }

    .report-table tbody tr.totals {
        background: #f3f4f6;
        font-weight: bold;
        border-top: 2px solid #e5e7eb;
        border-bottom: 2px solid #e5e7eb;
    }

    .profit-positive {
        color: #10b981;
        font-weight: bold;
    }

    .profit-negative {
        color: #ef4444;
        font-weight: bold;
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

    @media (max-width: 768px) {
        .report-header .d-flex {
            flex-direction: column;
            align-items: flex-start;
        }

        .report-header .d-flex .badge {
            width: 100%;
            justify-content: center;
            text-align: center;
        }

        .summary-card {
            min-height: auto;
        }
    }
</style>

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="report-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h1 class="h3 fw-bold mb-1 text-dark">Profit & Loss Report</h1>
                <p class="text-muted mb-0">Daily financial performance breakdown</p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">
                    📅 {{ $startDate->format('M d') }} - {{ $endDate->format('M d, Y') }}
                </span>
                @if(isset($selectedBar) && $selectedBar)
                    <span class="badge bg-primary text-white rounded-pill px-3 py-2">
                        📍 {{ $selectedBar->name }}
                    </span>
                @elseif(isset($barId) && $barId === 'all')
                    <span class="badge bg-secondary text-white rounded-pill px-3 py-2">
                        🌍 All Bars
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- Date Filter -->
    <form method="GET" class="row g-3 align-items-end mb-5">
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
        <div class="col-sm-6 col-md-3">
            <div class="date-filter-group">
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="{{ $startDate->format('Y-m-d') }}" class="form-control">
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="date-filter-group">
                <label for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date" value="{{ $endDate->format('Y-m-d') }}" class="form-control">
            </div>
        </div>
        @if(isset($bars) && $bars && $bars->count())
            <div class="col-sm-6 col-md-3">
                <div class="date-filter-group">
                    <label for="bar_id">Bar</label>
                    <select id="bar_id" name="bar_id" class="form-select">
                        <option value="all" {{ ($barId ?? 'all') === 'all' ? 'selected' : '' }}>All Bars</option>
                        @foreach($bars as $bar)
                            <option value="{{ $bar->id }}" {{ isset($selectedBar) && $selectedBar && $selectedBar->id == $bar->id ? 'selected' : '' }}>{{ $bar->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif
        <div class="col-sm-12 col-md-3">
            <button type="submit" class="btn btn-primary rounded-pill px-4 w-100">Apply Filter</button>
        </div>
    </form>

    <!-- Summary Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-6 col-lg-3">
            <div class="summary-card neutral">
                <div class="summary-label">Total Sales</div>
                <div class="summary-value">MWK {{ number_format($totals['sales']) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="summary-card">
                <div class="summary-label">Purchase Cost</div>
                <div class="summary-value" style="color: #6b7280;">MWK {{ number_format($totals['purchase_cost']) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="summary-card neutral">
                <div class="summary-label">Expenses</div>
                <div class="summary-value" style="color: #f59e0b;">MWK {{ number_format($totals['expenses']) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="summary-card {{ $totals['net_profit'] >= 0 ? 'positive' : 'negative' }}">
                <div class="summary-label">Net Profit</div>
                <div class="summary-value">MWK {{ number_format($totals['net_profit']) }}</div>
                <div class="small mt-2" style="opacity: 0.7;">
                    Margin: {{ $totals['profit_margin'] }}%
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Stats Row -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 bg-white">
                <div class="card-body p-4">
                    <div class="row text-center g-4">
                        <div class="col-md-3">
                            <div class="small text-muted text-uppercase fw-bold mb-2" style="font-size: 0.65rem;">Gross Profit</div>
                            <div class="h3 fw-bold text-dark">MWK {{ number_format($totals['gross_profit']) }}</div>
                            <div class="small text-muted">Sales - Purchase Cost</div>
                        </div>
                        <div class="col-md-3 border-start border-end">
                            <div class="small text-muted text-uppercase fw-bold mb-2" style="font-size: 0.65rem;">Profit Margin</div>
                            <div class="h3 fw-bold {{ $totals['profit_margin'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $totals['profit_margin'] }}%</div>
                            <div class="small text-muted">Net Profit / Sales</div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted text-uppercase fw-bold mb-2" style="font-size: 0.65rem;">Days Recorded</div>
                            <div class="h3 fw-bold text-dark">{{ count(array_filter($reportData, fn($d) => $d['sales'] > 0)) }}</div>
                            <div class="small text-muted">Active trading days</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Insight Dashboard -->
    @if(count($reportData) > 0)
        <div class="row g-4 mb-5">
            <div class="col-md-6 col-lg-4">
                <div class="summary-card neutral">
                    <div class="summary-label">Shortage (Missing Money)</div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-semibold" style="font-size: 1.5rem; {{ $shortage > 0 ? 'color: #dc2626;' : 'color: #059669;' }}">
                            MWK {{ number_format($shortage) }}
                        </span>
                    </div>
                    <div class="small text-muted mt-2">
                        Expected: MWK {{ number_format($totals['sales'] - $creditSalesOutstanding) }}<br>
                        Recorded: MWK {{ number_format(array_sum($paymentMethods)) }}
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="summary-card neutral">
                    <div class="summary-label">Credit Sales</div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small text-muted">Total Credit</span>
                        <span class="fw-semibold">MWK {{ number_format($creditSalesTotal) }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small text-muted">Paid</span>
                        <span class="fw-semibold">MWK {{ number_format($creditSalesPaid) }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted">Outstanding</span>
                        <span class="fw-semibold">MWK {{ number_format($creditSalesOutstanding) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-xl-8">
                <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
                    <h5 class="fw-bold mb-3">Sales Trend</h5>
                    <div class="mini-trend-bar mb-3">
                        @foreach($trendData as $point)
                            <div style="height: {{ $trendData->max('value') > 0 ? max(6, ($point['value'] / $trendData->max('value')) * 100) : 6 }}%;" title="{{ $point['label'] }}: MWK {{ number_format($point['value']) }}"></div>
                        @endforeach
                    </div>
                    <div class="mini-trend-labels">
                        <span>{{ $trendData->first()['label'] ?? '' }}</span>
                        <span>{{ $trendData->last()['label'] ?? '' }}</span>
                    </div>
                    <p class="small text-muted mt-3">This trend reflects sales movement in the selected date range. Rising bars indicate improving top-line performance.</p>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card border-0 shadow-sm rounded-4 bg-white p-4 h-100">
                    <h5 class="fw-bold mb-3">Top Items Sold</h5>
                    @if($topItems->isNotEmpty())
                        @foreach($topItems as $item)
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <div class="fw-semibold">{{ $item->item->name ?? 'Unknown Item' }}</div>
                                    <div class="small text-muted">{{ number_format($item->total_sold) }} units</div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-semibold">MWK {{ number_format($item->total_revenue) }}</div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="text-muted small">No sold item history found for this range.</div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Bar Performance Analysis -->
        @if(!empty($barAnalysis) && count($barAnalysis) > 0)
        <div class="row g-4 mb-5">
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
                    <h5 class="fw-bold mb-3">Bar Performance Analysis</h5>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Location</th>
                                    <th class="text-end">Sales</th>
                                    <th class="text-end">Expenses</th>
                                    <th class="text-end">Profit</th>
                                    <th class="text-end">Margin</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($barAnalysis as $bar)
                                <tr>
                                    <td class="fw-semibold">{{ $bar['bar_name'] }}</td>
                                    <td class="text-end">MWK {{ number_format($bar['sales']) }}</td>
                                    <td class="text-end text-warning">MWK {{ number_format($bar['expenses']) }}</td>
                                    <td class="text-end {{ $bar['profit'] >= 0 ? 'text-success' : 'text-danger' }} fw-semibold">MWK {{ number_format($bar['profit']) }}</td>
                                    <td class="text-end">{{ $bar['margin'] }}%</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
                    <h5 class="fw-bold mb-3">Profitability Interpretation</h5>
                    <p class="text-muted mb-0">{{ $interpretation }}</p>
                    <div class="mt-3">
                        <span class="badge bg-light text-dark">Expense ratio: {{ $expensesRatio }}%</span>
                        <span class="badge bg-light text-dark ms-2">Cash coverage: {{ $cashCoverage }}%</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
                    <h5 class="fw-bold mb-3">Credit Collection Impact</h5>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small text-muted">Outstanding Debt</span>
                            <span class="fw-semibold text-danger">MWK {{ number_format($creditSalesOutstanding) }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small text-muted">Impact on Cash</span>
                            <span class="fw-semibold">{{ $creditImpactOnCash }}% of sales</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted">Credit Paid</span>
                            <span class="fw-semibold text-success">MWK {{ number_format($creditSalesPaid) }}</span>
                        </div>
                    </div>
                    @if($creditSalesOutstanding > 0)
                    <p class="small text-muted mb-0">{{ $creditImpactOnCash }}% of revenue remains uncollected. Prioritize credit recovery to improve cash flow.</p>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="card border-0 shadow-sm">
            <div class="empty-state">
                <div class="empty-state-icon">📊</div>
                <h3 class="text-dark mb-2">No Data Available</h3>
                <p class="text-muted">No sales or expense data found for the selected date range.</p>
            </div>
        </div>
    @endif
</div>
@endsection
