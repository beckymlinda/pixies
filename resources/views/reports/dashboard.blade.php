@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<div class="container-fluid px-4 py-4">
    <!-- Success Messages -->
    @if(session('success'))
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-check-circle-fill me-3 fs-5"></i>
                        </div>
                        <div class="flex-grow-1">
                            {{ session('success') }}
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Page Header with Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm bg-gradient-primary">
                <div class="card-body py-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0">
                                    <div class="bg-white bg-opacity-20 rounded-3 p-3 me-3">
                                        <i class="bi bi-graph-up-arrow fs-3 text-white"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h1 class="h2 mb-1 text-white fw-bold">Reports Dashboard</h1>
                                    <p class="text-white-50 mb-0">
                                        @if($filter === 'today')
                                            Today's Performance Metrics
                                        @elseif($filter === 'week')
                                            This Week's Performance Overview
                                        @elseif($filter === 'month')
                                            This Month's Performance Analysis
                                        @elseif($filter === 'year')
                                            This Year's Performance Report
                                        @else
                                            Custom Period: {{ $startDate->format('M d, Y') }} - {{ $endDate->format('M d, Y') }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <span class="badge bg-white bg-opacity-25 text-white px-3 py-2">
                                    <i class="bi bi-calendar3 me-1"></i> {{ now()->format('F Y') }}
                                </span>
                                <span class="badge bg-white bg-opacity-25 text-white px-3 py-2">
                                    <i class="bi bi-building me-1"></i> {{ count($barBreakdown) }} Locations
                                </span>
                            </div>
                        </div>
                        
                        <!-- Filters -->
                        <div class="flex-shrink-0">
                            <form method="GET" class="d-flex gap-2 align-items-center">
                                <!-- Date Range -->
                                <div class="d-flex gap-2">
                                    <input type="date" 
                                           name="start_date" 
                                           value="{{ request('start_date') ?? $startDate->format('Y-m-d') }}" 
                                           class="form-control form-control-sm"
                                           style="width: 150px;">
                                    <input type="date" 
                                           name="end_date" 
                                           value="{{ request('end_date') ?? $endDate->format('Y-m-d') }}" 
                                           class="form-control form-control-sm"
                                           style="width: 150px;">
                                </div>
                                
                                <!-- Bar Filter -->
                                <select name="bar_id" class="form-select form-select-sm" style="width: 150px;">
                                    <option value="">All Bars</option>
                                    @foreach($barBreakdown as $barData)
                                        <option value="{{ $barData['bar']->id }}" 
                                                {{ request('bar_id') == $barData['bar']->id ? 'selected' : '' }}>
                                            {{ $barData['bar']->name }}
                                        </option>
                                    @endforeach
                                </select>
                                
                                <!-- Quick Filters -->
                                <div class="d-flex gap-1">
                                    <button type="submit" name="filter" value="today" 
                                            class="btn {{ $filter === 'today' ? 'btn-light' : 'btn-outline-light' }} btn-sm">
                                        Today
                                    </button>
                                    <button type="submit" name="filter" value="week" 
                                            class="btn {{ $filter === 'week' ? 'btn-light' : 'btn-outline-light' }} btn-sm">
                                        Week
                                    </button>
                                    <button type="submit" name="filter" value="month" 
                                            class="btn {{ $filter === 'month' ? 'btn-light' : 'btn-outline-light' }} btn-sm">
                                        Month
                                    </button>
                                    <button type="submit" name="filter" value="year" 
                                            class="btn {{ $filter === 'year' ? 'btn-light' : 'btn-outline-light' }} btn-sm">
                                        Year
                                    </button>
                                </div>
                                
                                <button type="submit" class="btn btn-light btn-sm">
                                    <i class="bi bi-funnel"></i> Apply
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN QUESTION: How much money did we make? -->
    <div class="row mb-4">
        <div class="col-12">
            <!-- Summary Cards - Modern Professional -->
            <div class="row g-3 mb-4">
                <!-- 📊 SALES -->
                <div class="col-12 col-md-6 col-lg">
                    <div class="card border-0 shadow-sm h-100 hover-lift">
                        <div class="card-body text-center py-3">
                            <div class="bg-primary bg-opacity-10 rounded-2 p-2 d-inline-flex mb-2">
                                <i class="bi bi-graph-up text-primary fs-4"></i>
                            </div>
                            <div class="text-primary fs-3 fw-bold">
                                <span class="small opacity-50">MWK</span> {{ number_format($summary['totalSales']) }}
                            </div>
                            <div class="text-dark small fw-bold">Total Sales</div>
                            <small class="text-muted">Source: Stock entries only</small>
                        </div>
                    </div>
                </div>
                
                <!-- 💰 COLLECTION -->
                <div class="col-12 col-md-6 col-lg">
                    <div class="card border-0 shadow-sm h-100 hover-lift">
                        <div class="card-body text-center py-3">
                            <div class="bg-primary bg-opacity-10 rounded-2 p-2 d-inline-flex mb-2">
                                <i class="bi bi-cash-stack text-primary fs-4"></i>
                            </div>
                            <div class="text-primary fs-3 fw-bold">
                                <span class="small opacity-50">MWK</span> {{ number_format($summary['totalCollected']) }}
                            </div>
                            <div class="text-dark small fw-bold">Total Collected</div>
                            <small class="text-muted">Cash + Mobile payments</small>
                        </div>
                    </div>
                </div>
                
                <!-- 📒 CREDIT -->
                <div class="col-12 col-md-6 col-lg">
                    <div class="card border-0 shadow-sm h-100 hover-lift">
                        <div class="card-body text-center py-3">
                            <div class="bg-purple bg-opacity-10 rounded-2 p-2 d-inline-flex mb-2">
                                <i class="bi bi-credit-card text-purple fs-4"></i>
                            </div>
                            <div class="text-purple fs-3 fw-bold">
                                <span class="small opacity-50">MWK</span> {{ number_format($summary['creditSales']) }}
                            </div>
                            <div class="text-dark small fw-bold">Credit Sales</div>
                            <small class="text-muted">Unpaid tabs</small>
                        </div>
                    </div>
                </div>
                
                <!-- ⚠️ RECONCILIATION -->
                <div class="col-12 col-md-6 col-lg">
                    <div class="card border-0 shadow-sm h-100 hover-lift">
                        <div class="card-body text-center py-3">
                            <div class="bg-{{ $summary['missingMoney'] < 0 ? 'warning' : ($summary['missingMoney'] > 0 ? 'info' : 'success') }} bg-opacity-10 rounded-2 p-2 d-inline-flex mb-2">
                                <i class="bi bi-{{ $summary['missingMoney'] < 0 ? 'exclamation-triangle' : ($summary['missingMoney'] > 0 ? 'arrow-up-circle' : 'check-circle') }} text-{{ $summary['missingMoney'] < 0 ? 'warning' : ($summary['missingMoney'] > 0 ? 'info' : 'success') }} fs-4"></i>
                            </div>
                            <div class="text-{{ $summary['missingMoney'] < 0 ? 'warning' : ($summary['missingMoney'] > 0 ? 'info' : 'success') }} fs-3 fw-bold">
                                <span class="small opacity-50">MWK</span> {{ number_format(abs($summary['missingMoney'])) }}
                            </div>
                            <div class="text-dark small fw-bold">{{ $summary['missingMoney'] < 0 ? 'Missing Money' : ($summary['missingMoney'] > 0 ? 'Surplus Recorded' : 'Balanced') }}</div>
                            <small class="text-muted">Collected - (Sales - Credit)</small>
                        </div>
                    </div>
                </div>
                
                <!-- 💸 EXPENSES -->
                <div class="col-12 col-md-6 col-lg">
                    <div class="card border-0 shadow-sm h-100 hover-lift">
                        <div class="card-body text-center py-3">
                            <div class="bg-danger bg-opacity-10 rounded-2 p-2 d-inline-flex mb-2">
                                <i class="bi bi-cash text-danger fs-4"></i>
                            </div>
                            <div class="text-danger fs-3 fw-bold">
                                <span class="small opacity-50">MWK</span> {{ number_format($summary['totalExpenses']) }}
                            </div>
                            <div class="text-dark small fw-bold">Total Expenses</div>
                            <small class="text-muted">Operating costs</small>
                        </div>
                    </div>
                </div>
            </div>

        <!-- 🏦 FINAL - Bankable Balance -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm h-100 hover-lift">
                        <div class="card-body text-center py-4">
                            <div class="bg-indigo bg-opacity-10 rounded-2 p-3 d-inline-flex mb-3">
                                <i class="bi bi-bank text-indigo fs-3"></i>
                            </div>
                            <div class="text-indigo fs-2 fw-bold">
                                <span class="small opacity-50">MWK</span> {{ number_format($summary['bankableBalance']) }}
                            </div>
                            <div class="text-dark fw-bold">Bankable Balance</div>
                            <small class="text-muted">Collected - Expenses = Available for banking</small>
                        </div>
                    </div>
                </div>
                
                <!-- VALIDATION CHECK -->
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm h-100 hover-lift">
                        <div class="card-body text-center py-4">
                            <div class="bg-{{ $summary['isAccurate'] ? 'success' : 'danger' }} bg-opacity-10 rounded-2 p-3 d-inline-flex mb-3">
                                <i class="bi bi-{{ $summary['isAccurate'] ? 'check-circle' : 'x-circle' }} text-{{ $summary['isAccurate'] ? 'success' : 'danger' }} fs-3"></i>
                            </div>
                            <div class="text-{{ $summary['isAccurate'] ? 'success' : 'danger' }} fs-4 fw-bold">
                                {{ $summary['isAccurate'] ? 'Financials Accurate' : 'Data Inconsistency' }}
                            </div>
                            <div class="text-muted small mt-2">
                                <div class="d-flex justify-content-center gap-1">
                                    <span>{{ number_format($summary['totalCollected']) }}</span>
                                    <span>+</span>
                                    <span>{{ number_format($summary['creditSales']) }}</span>
                                    <span>+</span>
                                    <span>{{ number_format($summary['missingMoney']) }}</span>
                                    <span>=</span>
                                    <span class="fw-semibold">{{ number_format($summary['totalSales']) }}</span>
                                </div>
                            </div>
                            @if(!$summary['isAccurate'])
                                <div class="text-danger small mt-2">
                                    Validation: {{ number_format($summary['validationCheck']) }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

        <!-- Bar Performance Cards -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom py-3">
                        <h3 class="h4 mb-0 fw-semibold">
                            <i class="bi bi-building me-2 text-primary"></i> Performance by Bar
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            @foreach($barBreakdown as $barData)
                                <div class="col-12 col-md-6 col-lg-4">
                                    <div class="card border-0 bg-light h-100 hover-lift">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="bg-primary bg-opacity-10 rounded-2 p-2 me-2">
                                                    <i class="bi bi-shop text-primary"></i>
                                                </div>
                                                <h5 class="card-title mb-0 fw-semibold">{{ $barData['bar']->name }}</h5>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-dark small fw-bold">Sales:</span>
                                                <span class="fw-semibold text-success">MWK {{ number_format($barData['sales']) }}</span>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-dark small fw-bold">Expenses:</span>
                                                <span class="fw-semibold text-danger">MWK {{ number_format($barData['expenses']) }}</span>
                                            </div>
                                            @if(isset($barData['castel_bottles']) && $barData['castel_bottles'] > 0)
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-dark small fw-bold">Bottles Counted:</span>
                                                <span class="fw-semibold text-primary">{{ $barData['castel_bottles'] }}</span>
                                            </div>
                                            @endif
                                            
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-dark small fw-bold">Profit:</span>
                                                <span class="fw-semibold {{ $barData['profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                                    MWK {{ number_format($barData['profit']) }}
                                                </span>
                                            </div>
                                            
                                            @if(!auth()->user()->isDirector())
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="text-muted small">Cash:</span>
                                                    <span class="fw-semibold text-primary">{{ number_format($barData['cash']) }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Breakdown (Director Focus) -->
        @if(auth()->user()->isDirector() || $paymentBreakdown['totalElectronic'] > 0)
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom py-3">
                            <h3 class="h4 mb-0 fw-semibold">
                                <i class="bi bi-credit-card me-2 text-primary"></i> Payment Breakdown
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                @if(!auth()->user()->isDirector())
                                    <div class="col-12 col-md-6 col-lg">
                                        <div class="card border-0 bg-success bg-opacity-10 h-100 hover-lift">
                                            <div class="card-body text-center py-3">
                                                <div class="bg-success bg-opacity-25 rounded-2 p-2 d-inline-flex mb-2">
                                                    <i class="bi bi-cash-stack text-success fs-4"></i>
                                                </div>
                                                <div class="text-success fs-3 fw-bold">
                                                    MWK {{ number_format($paymentBreakdown['cash']) }}
                                                </div>
                                                <div class="text-dark small fw-bold">Cash</div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                
                                @foreach($paymentBreakdown['payments'] as $payment)
                                    <div class="col-12 col-md-6 col-lg">
                                        <div class="card border-0 bg-primary bg-opacity-10 h-100 hover-lift">
                                            <div class="card-body text-center py-3">
                                                <div class="bg-primary bg-opacity-25 rounded-2 p-2 d-inline-flex mb-2">
                                                    <i class="bi bi-phone text-primary fs-4"></i>
                                                </div>
                                                <div class="text-primary fs-3 fw-bold">
                                                    MWK {{ number_format($payment->total_amount) }}
                                                </div>
                                                <div class="text-dark small fw-bold">{{ strtoupper($payment->type) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Best Selling Items -->
        @if($itemInsights->count() > 0)
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom py-3">
                            <h3 class="h4 mb-0 fw-semibold">
                                <i class="bi bi-star me-2 text-primary"></i> Best Selling Items
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="bg-light border-bottom">
                                        <tr>
                                            <th class="border-0 fw-semibold text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Item</th>
                                            <th class="border-0 fw-semibold text-muted text-uppercase text-center" style="font-size: 0.75rem; letter-spacing: 0.5px;">Category</th>
                                            <th class="border-0 fw-semibold text-muted text-uppercase text-center" style="font-size: 0.75rem; letter-spacing: 0.5px;">Qty Sold</th>
                                            <th class="border-0 fw-semibold text-muted text-uppercase text-end" style="font-size: 0.75rem; letter-spacing: 0.5px;">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($itemInsights->take(10) as $item)
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-primary bg-opacity-10 rounded-2 p-2 me-2">
                                                            <i class="bi bi-box text-primary"></i>
                                                        </div>
                                                        <span class="fw-medium">{{ $item->name }}</span>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-light text-dark">{{ $item->category }}</span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="fw-semibold">{{ $item->total_sold }}</span>
                                                </td>
                                                <td class="text-end">
                                                    <span class="fw-semibold text-success">{{ number_format($item->total_revenue) }}</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Expense Breakdown -->
        @if($expenseBreakdown->count() > 0)
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom py-3">
                            <h3 class="h4 mb-0 fw-semibold">
                                <i class="bi bi-receipt-cutoff me-2 text-primary"></i> Expense Breakdown
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                @foreach($expenseBreakdown as $expense)
                                    <div class="col-12 col-md-6 col-lg">
                                        <div class="card border-0 bg-danger bg-opacity-10 h-100 hover-lift">
                                            <div class="card-body text-center py-3">
                                                <div class="bg-danger bg-opacity-25 rounded-2 p-2 d-inline-flex mb-2">
                                                    <i class="bi bi-cash text-danger fs-4"></i>
                                                </div>
                                                <div class="text-danger fs-3 fw-bold">
                                                    {{ number_format($expense->total_amount) }}
                                                </div>
                                                <div class="text-muted small">{{ ucfirst($expense->type) }}</div>
                                                <div class="text-muted small">{{ $expense->count }} items</div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Debt Tracking -->
        @if($debtTracking['totalDebts'] > 0)
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom py-3">
                            <h3 class="h4 mb-0 fw-semibold">
                                <i class="bi bi-exclamation-triangle me-2 text-primary"></i> Debt Tracking
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 col-lg-6">
                                    <div class="card border-0 bg-light h-100">
                                        <div class="card-body">
                                            <h5 class="card-title fw-semibold mb-3">
                                                Total Outstanding: {{ number_format($debtTracking['totalDebts']) }}
                                            </h5>
                                            
                                            @foreach($debtTracking['debtsBySeller']->take(5) as $sellerDebt)
                                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                    <span class="fw-medium">{{ $sellerDebt->seller_name }}</span>
                                                    <span class="fw-semibold text-danger">{{ number_format($sellerDebt->total_debt) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-lg-6">
                                    <div class="card border-0 bg-light h-100">
                                        <div class="card-body">
                                            <h5 class="card-title fw-semibold mb-3">Recent Debts</h5>
                                            
                                            @foreach($debtTracking['recentDebts']->take(5) as $debt)
                                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                    <div>
                                                        <div class="fw-medium">{{ $debt->customer_name ?? 'Anonymous' }}</div>
                                                        <small class="text-muted">{{ $debt->seller->name }} - {{ $debt->stockEntry->bar->name }}</small>
                                                    </div>
                                                    <span class="fw-semibold text-danger">{{ number_format($debt->amount) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Modern CSS Styles -->
<style>
/* ======================================
   MODERN BOOTSTRAP REACT-LIKE STYLES
================================ */

:root {
    --primary: #1e293b;
    --primary-dark: #0f172a;
    --primary-light: #334155;
    --accent: #3b82f6;
    --warning: #f59e0b;
    --danger: #ef4444;
    --success: #10b981;
    --purple: #6366f1;
    --indigo: #4338ca;
    --bg: #f8fafc;
    --card: #ffffff;
    --border: #e2e8f0;
    --text: #0f172a;
    --muted: #64748b;
    --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
}

/* Page background */
body {
    background: var(--bg);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.875rem;
    line-height: 1.5;
    color: var(--text);
}

/* ================================
   GRADIENT BACKGROUNDS
================================ */

.bg-gradient-primary {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%) !important;
}

.bg-light.bg-gradient {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%) !important;
}

/* ================================
   CARDS
================================ */

.card {
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    transition: all 0.15s ease-in-out;
    box-shadow: var(--shadow);
}

.card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-1px);
}

.card-header {
    border-bottom: 1px solid var(--border);
    background-color: #ffffff;
}

/* ================================
   BUTTONS
================================ */

.btn {
    border-radius: 0.5rem;
    font-weight: 500;
    transition: all 0.15s ease-in-out;
    border: 1px solid transparent;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    line-height: 1.5rem;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.hover-lift:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* ================================
   TABLES
================================ */

.table {
    margin-bottom: 0;
}

.table thead th {
    border-bottom: 1px solid var(--border);
    font-weight: 600;
    color: var(--muted);
    background-color: #f8fafc;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.table tbody tr {
    transition: all 0.15s ease-in-out;
}

.table tbody tr:hover {
    background-color: #f8fafc;
}

.table td {
    vertical-align: middle;
    border-bottom: 1px solid var(--border);
    padding: 1rem;
}

/* ================================
   BADGES
================================ */

.badge {
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    padding: 0.25rem 0.75rem;
}

/* ================================
   ICONS
================================ */

.rounded-2 {
    border-radius: 0.5rem;
}

.p-2 {
    padding: 0.5rem;
}

.me-2 {
    margin-right: 0.5rem;
}

.me-3 {
    margin-right: 0.75rem;
}

/* ================================
   TYPOGRAPHY
================================ */

.fw-semibold {
    font-weight: 600;
}

.fw-medium {
    font-weight: 500;
}

.text-uppercase {
    text-transform: uppercase;
}

.text-capitalize {
    text-transform: capitalize;
}

.text-white-50 {
    color: rgba(255, 255, 255, 0.75) !important;
}

/* ================================
   COLORS
================================ */

.text-purple {
    color: var(--purple) !important;
}

.text-indigo {
    color: var(--indigo) !important;
}

.bg-purple {
    background-color: var(--purple) !important;
}

.bg-indigo {
    background-color: var(--indigo) !important;
}

.bg-purple.bg-opacity-10 {
    background-color: rgba(147, 51, 234, 0.1) !important;
}

.bg-indigo.bg-opacity-10 {
    background-color: rgba(99, 102, 241, 0.1) !important;
}

.bg-purple.bg-opacity-25 {
    background-color: rgba(147, 51, 234, 0.25) !important;
}

.bg-indigo.bg-opacity-25 {
    background-color: rgba(99, 102, 241, 0.25) !important;
}

/* ================================
   FLEX UTILITIES
================================ */

.flex-shrink-0 {
    flex-shrink: 0;
}

.flex-grow-1 {
    flex-grow: 1;
}

.gap-1 {
    gap: 0.25rem;
}

.gap-2 {
    gap: 0.5rem;
}

.gap-3 {
    gap: 0.75rem;
}

/* ================================
   ANIMATIONS
================================ */

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card {
    animation: fadeIn 0.25s ease;
}
</style>
@endsection
