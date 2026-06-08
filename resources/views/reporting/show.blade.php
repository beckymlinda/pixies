@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<div class="container-fluid px-4 py-3">
    
    <!-- Financial Summary - Compact Grid -->
    <div class="row g-3 mb-4">
        <!-- Total Sales -->
        <div class="col-12 col-md-3">
            <div class="card border-0 bg-light h-100">
                <div class="card-body text-center py-3">
                    <div class="text-success fs-5 fw-bold">Total Sales</div>
                    <div class="text-success fs-3">{{ number_format($totalSales, 0) }}</div>
                    <small class="text-muted">All sales revenue</small>
                </div>
            </div>
        </div>
        
        <!-- Total Collected -->
        <div class="col-12 col-md-3">
            <div class="card border-0 bg-light h-100">
                <div class="card-body text-center py-3">
                    <div class="text-primary fs-5 fw-bold">Collected</div>
                    <div class="text-primary fs-3">{{ number_format($totalCollected, 0) }}</div>
                    <small class="text-muted">Cash + all payment methods recorded</small>
                </div>
            </div>
        </div>
        
        <!-- Credit Sales -->
        <div class="col-12 col-md-3">
            <div class="card border-0 bg-light h-100">
                <div class="card-body text-center py-3">
                    <div class="text-purple fs-5 fw-bold">Credit</div>
                    <div class="text-purple fs-3">{{ number_format($creditSales, 0) }}</div>
                    <small class="text-muted">Unpaid tabs</small>
                </div>
            </div>
        </div>
        
        <!-- Bankable Balance -->
        <div class="col-12 col-md-3">
            <div class="card border-0 bg-light h-100">
                <div class="card-body text-center py-3">
                    <div class="text-indigo fs-5 fw-bold">Bankable</div>
                    <div class="text-indigo fs-3">{{ number_format($bankableBalance, 0) }}</div>
                    <small class="text-muted">Available for banking</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Reconciliation & Expenses -->
    <div class="row g-3 mb-4">
        <!-- Missing Money -->
        <div class="col-12 col-md-6">
            <div class="card border-0 {{ $hasMissingMoney ? 'bg-warning bg-opacity-10' : ($hasSurplusMoney ? 'bg-info bg-opacity-10' : 'bg-success bg-opacity-10') }} h-100">
                <div class="card-body text-center py-3">
                    <div class="{{ $hasMissingMoney ? 'text-warning' : ($hasSurplusMoney ? 'text-info' : 'text-success') }} fs-5 fw-bold">
                        {{ $hasMissingMoney ? 'Missing Money' : ($hasSurplusMoney ? 'Surplus Recorded' : 'Balanced') }}
                    </div>
                    <div class="{{ $hasMissingMoney ? 'text-warning' : ($hasSurplusMoney ? 'text-info' : 'text-success') }} fs-3">
                        {{ number_format(abs($missingMoney), 0) }}
                    </div>
                    <small class="text-muted">
                        Collected - ((Sales - Credit) + Old Debt Collected)
                    </small>
                </div>
            </div>
        </div>
        
        <!-- Expenses -->
        <div class="col-12 col-md-6">
            <div class="card border-0 bg-danger bg-opacity-10 h-100">
                <div class="card-body text-center py-3">
                    <div class="text-danger fs-5 fw-bold">Expenses</div>
                    <div class="text-danger fs-3">{{ number_format($totalExpenses, 0) }}</div>
                    <small class="text-muted">Operating costs</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Breakdown -->
    <div class="row g-3">
        <div class="col-12">
            <div class="card border-0 bg-light">
                <div class="card-header bg-light py-2">
                    <h6 class="mb-0"><i class="bi bi-credit-card me-2"></i> Payment Breakdown</h6>
                </div>
                <div class="card-body py-2">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Payment Method</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Cash Payment -->
                                <tr>
                                    <td>
                                        <span class="badge bg-success text-white">Cash</span>
                                    </td>
                                    <td class="text-end fw-bold text-success">{{ number_format($dailyReport->cash_in_hand, 0) }}</td>
                                    <td class="text-end">{{ number_format($totalCollected > 0 ? ($dailyReport->cash_in_hand / $totalCollected) * 100 : 0, 1) }}%</td>
                                </tr>
                                @foreach($paymentsByMethod as $method => $data)
                                <tr>
                                    <td>
                                        <span class="badge {{ $method === 'Debt Collection' ? 'bg-indigo' : 'bg-primary' }} text-white">{{ $method }}</span>
                                    </td>
                                    <td class="text-end fw-bold">{{ number_format($data['amount'], 0) }}</td>
                                    <td class="text-end">{{ number_format($data['percentage'], 1) }}%</td>
                                </tr>
                                @endforeach
                                <tr class="table-active fw-bold">
                                    <td>TOTAL COLLECTED:</td>
                                    <td class="text-end text-primary">{{ number_format($totalCollected, 0) }}</td>
                                    <td class="text-end">100%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="row g-3 mt-4">
        <div class="col-12">
            <div class="d-flex justify-content-between gap-2">
                <a href="{{ route('reporting.index') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Reports
                </a>
                
                @if((auth()->user()->isSeller() && $dailyReport->user_id === auth()->id() && $dailyReport->date->format('Y-m-d') === now()->format('Y-m-d')) || auth()->user()->isManager() || auth()->user()->isDirector())
                    <a href="{{ route('reporting.edit', $dailyReport) }}" class="btn btn-warning">
                        <i class="bi bi-pencil me-1"></i> Edit Report
                    </a>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection
