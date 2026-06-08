@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .credit-edit-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        margin-top: -1.5rem;
        margin-left: -1.5rem;
        margin-right: -1.5rem;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
    }
    .form-card-modern {
        border-radius: 16px;
        background: white;
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    .input-group-text-modern {
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        color: #475569;
        font-weight: 600;
    }
    .form-control-modern {
        border: 1px solid #cbd5e1;
        padding: 0.75rem 1rem;
        border-radius: 8px;
    }
    .form-control-modern:focus {
        border-color: var(--pixies-primary);
        box-shadow: 0 0 0 3px rgba(30, 41, 59, 0.1);
    }
    
    @media (max-width: 768px) {
        .credit-edit-header { flex-direction: column; align-items: flex-start !important; gap: 1rem; padding: 1rem; }
    }
</style>

<div class="container-fluid p-0">
    <!-- Modern Header -->
    <div class="credit-edit-header d-flex align-items-center justify-content-between shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('credit-customers.show', $customerTab->customer_name) }}" class="btn btn-sm btn-light rounded-circle border shadow-sm bg-white">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="h4 fw-bold mb-0 text-dark">Edit Credit Entry</h1>
                <p class="text-muted small mb-0">Modifying record for <strong>{{ $customerTab->customer_name }}</strong></p>
            </div>
        </div>
    </div>

    <div class="px-4 pb-5">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="form-card-modern shadow-sm">
                    <div class="p-4 border-bottom bg-light bg-opacity-50">
                        <h5 class="fw-bold mb-0 text-dark">Adjust Entry Details</h5>
                    </div>
                    <div class="p-4">
                        <form action="{{ route('credit-customers.update', $customerTab) }}" method="POST">
                            @csrf
                            @method('PUT')
                            
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Customer Name</label>
                                    <input type="text" name="customer_name" class="form-control form-control-modern fw-bold" 
                                           value="{{ old('customer_name', $customerTab->customer_name) }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Phone Number</label>
                                    <input type="text" name="phone" class="form-control form-control-modern" 
                                           value="{{ old('phone', $customerTab->phone) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Consumption Date</label>
                                    <input type="date" name="date" class="form-control form-control-modern" 
                                           value="{{ old('date', $customerTab->date->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Credit Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text-modern px-3">MWK</span>
                                        <input type="number" name="amount" class="form-control form-control-modern fw-bold text-dark fs-5" 
                                               value="{{ old('amount', $customerTab->amount) }}" step="1" required>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold text-dark">Description</label>
                                    <textarea name="description" class="form-control form-control-modern" rows="3">{{ old('description', $customerTab->description) }}</textarea>
                                </div>
                                <div class="col-12">
                                    <div class="bg-light rounded-3 p-3 border d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small text-muted text-uppercase fw-bold" style="font-size: 0.6rem;">Currently Paid</div>
                                            <div class="fw-bold text-success">MWK {{ number_format($customerTab->paid_amount) }}</div>
                                        </div>
                                        <div>
                                            <div class="small text-muted text-uppercase fw-bold" style="font-size: 0.6rem;">Remaining Balance</div>
                                            <div class="fw-bold text-danger">MWK {{ number_format($customerTab->balance) }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 text-end pt-3">
                                    <a href="{{ route('credit-customers.show', $customerTab->customer_name) }}" class="btn btn-light rounded-pill px-4 border me-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary rounded-pill px-5 shadow-sm fw-bold">
                                        <i class="bi bi-check-circle-fill me-2"></i>Update Entry
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Audit Box -->
                <div class="form-card-modern shadow-sm p-4 mb-4">
                    <h6 class="fw-bold text-dark mb-4">Audit Information</h6>
                    <div class="small text-muted mb-1">Created By:</div>
                    <div class="fw-bold text-dark mb-3">{{ $customerTab->creator->name ?? 'System' }}</div>
                    
                    <div class="small text-muted mb-1">Last Updated:</div>
                    <div class="fw-bold text-dark mb-3">{{ \Carbon\Carbon::parse($customerTab->updated_at)->format('M d, Y H:i') }}</div>
                    
                    <div class="small text-muted mb-1">Current Status:</div>
                    <div>{!! $customerTab->status_badge !!}</div>
                </div>

                <!-- Danger Zone -->
                <div class="card border-0 bg-danger bg-opacity-10 text-danger rounded-4 p-4 shadow-sm border-danger border-opacity-25">
                    <h6 class="fw-bold mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i>Careful!</h6>
                    <p class="small mb-0">
                        Changing the <strong>Credit Amount</strong> will instantly update the customer's total debt. Ensure this change is authorized.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
