@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    :root { --purple: #6366f1; --indigo: #4338ca; }
    .text-purple { color: var(--purple) !important; }
    .text-indigo { color: var(--indigo) !important; }
    .bg-indigo { background-color: var(--indigo) !important; }
    .bg-indigo.bg-opacity-10 { background-color: rgba(67, 56, 202, 0.1) !important; }
    .border-indigo { border-color: var(--indigo) !important; }
</style>
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
                        Collected − (Sales − Credit)
                        @if(isset($expectedCollected))
                            <br><span class="text-dark">Expected collected: {{ number_format($expectedCollected, 0) }}</span>
                        @endif
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
            <div class="card pixies-card shadow-soft rounded-xl border-0">
                <div class="card-header bg-white border-0 rounded-xl pt-3 pb-2">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-credit-card me-2 text-primary"></i>Payment Breakdown</h6>
                    <small class="text-muted">How money came in, and what went out during the shift</small>
                </div>
                <div class="card-body pt-0 pb-3">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-uppercase text-muted small">
                                    <th class="fw-semibold border-0">Method</th>
                                    <th class="fw-semibold border-0">Recorded Values</th>
                                    <th class="fw-semibold border-0 text-end">Amount</th>
                                    <th class="fw-semibold border-0 text-end">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Section: Money Collected -->
                                <tr>
                                    <td colspan="4" class="pt-2 pb-1 border-0">
                                        <span class="small fw-bold text-success text-uppercase"><i class="bi bi-arrow-down-circle-fill me-1"></i>Money Collected</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><i class="bi bi-cash-stack me-1"></i>Cash</span>
                                    </td>
                                    <td class="text-muted small">—</td>
                                    <td class="text-end fw-bold text-success">{{ number_format($dailyReport->cash_in_hand, 0) }}</td>
                                    <td class="text-end text-muted">{{ number_format($totalCollected > 0 ? ($dailyReport->cash_in_hand / $totalCollected) * 100 : 0, 1) }}%</td>
                                </tr>
                                @foreach($paymentsByMethod as $method => $data)
                                <tr>
                                    <td>
                                        <span class="badge {{ $method === 'Debt Collection' ? 'bg-indigo bg-opacity-10 text-indigo border border-indigo' : 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25' }}">
                                            <i class="bi {{ $method === 'Debt Collection' ? 'bi-arrow-return-left' : 'bi-phone-fill' }} me-1"></i>{{ $method }}
                                        </span>
                                    </td>
                                    <td class="text-muted small">{{ $data['breakdown'] ?: '—' }}</td>
                                    <td class="text-end fw-bold">{{ number_format($data['amount'], 0) }}</td>
                                    <td class="text-end text-muted">{{ number_format($data['percentage'], 1) }}%</td>
                                </tr>
                                @endforeach
                                <tr class="bg-primary bg-opacity-10 fw-bold">
                                    <td class="rounded-start">Total Collected</td>
                                    <td></td>
                                    <td class="text-end text-primary">{{ number_format($totalCollected, 0) }}</td>
                                    <td class="text-end rounded-end">100%</td>
                                </tr>

                                <!-- Section: Shift Expenditures -->
                                <tr>
                                    <td colspan="4" class="pt-3 pb-1 border-0">
                                        <span class="small fw-bold text-danger text-uppercase"><i class="bi bi-arrow-up-circle-fill me-1"></i>Shift Expenditures</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <span class="badge bg-warning bg-opacity-25 text-dark border border-warning"><i class="bi bi-cup-hot-fill me-1"></i>Lunch</span>
                                    </td>
                                    <td class="text-muted small">—</td>
                                    <td class="text-end fw-bold">{{ number_format($lunchTotal, 0) }}</td>
                                    <td class="text-end text-muted">—</td>
                                </tr>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25"><i class="bi bi-people-fill me-1"></i>Ngongole</span>
                                    </td>
                                    <td class="text-muted small">—</td>
                                    <td class="text-end fw-bold">{{ number_format($ngongoleTotal, 0) }}</td>
                                    <td class="text-end text-muted">—</td>
                                </tr>
                                <tr>
                                    <td>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25"><i class="bi bi-exclamation-triangle-fill me-1"></i>Damages</span>
                                    </td>
                                    <td class="text-muted small">—</td>
                                    <td class="text-end fw-bold">{{ number_format($damagesTotal, 0) }}</td>
                                    <td class="text-end text-muted">—</td>
                                </tr>

                                <!-- Grand Total -->
                                <tr>
                                    <td colspan="4" class="pt-2 border-0"></td>
                                </tr>
                                <tr class="fw-bold" style="background-color: var(--pixies-text); color: #fff;">
                                    <td class="rounded-start py-3">Grand Total</td>
                                    <td></td>
                                    <td class="text-end py-3">{{ number_format($grandTotal, 0) }}</td>
                                    <td class="text-end rounded-end py-3">—</td>
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
                
                @if($dailyReport->isEditableBy(auth()->user()))
                    <a href="{{ route('reporting.edit', $dailyReport) }}" class="btn btn-warning">
                        <i class="bi bi-pencil me-1"></i> Edit Report
                    </a>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection
