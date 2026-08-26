@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<style>
    .credit-create-header {
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
    .quick-select-item {
        padding: 0.75rem 1rem;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        margin-bottom: 0.5rem;
        cursor: pointer;
        transition: all 0.2s;
        background: white;
    }
    .quick-select-item:hover {
        background: #f8fafc;
        border-color: var(--pixies-primary);
        transform: translateX(4px);
    }

    @media (max-width: 768px) {
        .credit-create-header { flex-direction: column; align-items: flex-start !important; gap: 1rem; padding: 1rem; }
    }
</style>

<div class="container-fluid p-0">
    <!-- Modern Header -->
    <div class="credit-create-header d-flex align-items-center justify-content-between shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('credit-customers.index') }}" class="btn btn-sm btn-light rounded-circle border shadow-sm bg-white">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="h4 fw-bold mb-0 text-dark">Add Credit Entry</h1>
                <p class="text-muted small mb-0">Record consumption to be paid later.</p>
            </div>
        </div>
    </div>

    <div class="px-4 pb-5">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="form-card-modern shadow-sm">
                    <div class="p-4 border-bottom bg-light bg-opacity-50">
                        <h5 class="fw-bold mb-0 text-dark">Customer & Consumption Details</h5>
                    </div>
                    <div class="p-4">
                        <form action="{{ route('credit-customers.store') }}" method="POST">
                            @csrf
                            <div class="row g-4">
                                @if(auth()->user()->isAdmin())
                                    <div class="col-12">
                                        <label class="form-label fw-bold text-dark">Target Bar</label>
                                        <select name="bar_id" class="form-select form-control-modern" required>
                                            <option value="">Select Bar...</option>
                                            @foreach($bars as $b)
                                                <option value="{{ $b->id }}" {{ (old('bar_id') == $b->id || (isset($bar) && $bar->id == $b->id)) ? 'selected' : '' }}>
                                                    {{ $b->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Customer Name</label>
                                    <input type="text" name="customer_name" id="customer_name" class="form-control form-control-modern" 
                                           value="{{ old('customer_name') }}" list="existing-customers" placeholder="Type or select name..." required>
                                    <datalist id="existing-customers">
                                        @foreach($existingCustomers as $customer)
                                            <option value="{{ $customer }}">
                                        @endforeach
                                    </datalist>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Phone Number (Optional)</label>
                                    <input type="text" name="phone" class="form-control form-control-modern" 
                                           value="{{ old('phone') }}" placeholder="e.g. 0999...">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Date of Consumption</label>
                                    <input type="date" name="date" class="form-control form-control-modern" 
                                           value="{{ old('date', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Credit Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text-modern px-3">MWK</span>
                                        <input type="number" name="amount" class="form-control form-control-modern fw-bold text-dark fs-5" 
                                               value="{{ old('amount') }}" step="1" required>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold text-dark">What was consumed?</label>
                                    <textarea name="description" class="form-control form-control-modern" rows="3" 
                                              placeholder="e.g. 5 Green, 1 Malawi Gin...">{{ old('description') }}</textarea>
                                </div>
                                <div class="col-12 text-end pt-3">
                                    <a href="{{ route('credit-customers.index') }}" class="btn btn-light rounded-pill px-4 border me-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary rounded-pill px-5 shadow-sm fw-bold">
                                        <i class="bi bi-plus-lg me-2"></i>Create Entry
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Recent Customers -->
                <div class="form-card-modern shadow-sm p-4 mb-4">
                    <h6 class="fw-bold text-dark mb-4">Recent Customers</h6>
                    <div id="recent-customers">
                        @forelse($existingCustomers->take(6) as $customer)
                            <div class="quick-select-item shadow-sm" onclick="selectCustomer('{{ $customer }}')">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-person-circle text-primary"></i>
                                    <span class="small fw-bold text-dark">{{ $customer }}</span>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted small">No recent customers found.</p>
                        @endforelse
                    </div>
                </div>

                <!-- Info Box -->
                <div class="card border-0 bg-dark text-white rounded-4 p-4 shadow-sm">
                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle-fill text-info me-2"></i>Note on Credit</h6>
                    <p class="small opacity-75 mb-0">
                        Recording credit items ensures your stock balances correctly even when money isn't received immediately.
                        <br><br>
                        These entries will appear in the customer's monthly tab for easy reconciliation.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function selectCustomer(name) {
    document.getElementById('customer_name').value = name;
    document.getElementById('customer_name').focus();
}
</script>
@endsection
