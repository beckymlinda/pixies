@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
                        <div>
                            <h1 class="h3 fw-bold mb-1 text-dark"><i class="bi bi-bottle text-success me-2"></i>Castel Bottle Count</h1>
                            <p class="text-muted small mb-0">Track real-time bottle sales count for all Castel products.</p>
                        </div>
                        @if(!auth()->user()->isSeller())
                            <form method="GET" action="{{ route('castel.index') }}" class="d-flex gap-2">
                                <select name="bar_id" class="form-select shadow-sm rounded-pill border border-secondary border-opacity-10 px-3" onchange="this.form.submit()">
                                    @foreach($bars as $bar)
                                        <option value="{{ $bar->id }}" {{ $selectedBarId == $bar->id ? 'selected' : '' }}>{{ $bar->name }}</option>
                                    @endforeach
                                </select>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Counter Display Card -->
    <div class="row mb-4">
        <div class="col-md-8 col-lg-6 mx-auto">
            <div class="card border-0 shadow-sm rounded-4 text-center p-4 bg-white position-relative overflow-hidden border-top border-4 border-success">
                <div class="text-uppercase small fw-bold text-muted mb-2 tracking-wider">
                    Today's Total Bottle Count @if($selectedBar) ({{ $selectedBar->name }}) @endif
                </div>
                <div class="display-1 fw-bolder text-dark my-2 font-monospace">
                    {{ number_format($todayTotalCount) }}
                </div>
                <div class="text-muted small mb-3">Total Castel bottles sold today</div>
                <div>
                    <button type="button" class="btn btn-outline-danger rounded-pill px-4 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#resetCounterModal">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Counter (Start All Over)
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if(!auth()->user()->isSeller())
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 bg-white">
                    <div class="card-body p-4">
                        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-3">
                            <div>
                                <h5 class="fw-bold mb-1 text-dark">Castel Counts by Bar</h5>
                                <p class="text-muted small mb-0">Director view of how many Castel bottles each bar has recorded today.</p>
                            </div>
                        </div>

                        <div class="row g-3">
                            @forelse($barCounts as $barCount)
                                <div class="col-12 col-md-4">
                                    <div class="card border-0 shadow-sm rounded-4 p-3">
                                        <div class="small text-muted text-uppercase fw-bold mb-2">{{ $barCount->name }}</div>
                                        <div class="h3 fw-bold text-primary">{{ number_format($barCount->total_counted) }}</div>
                                        <div class="text-muted small">Castel bottles counted today</div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12">
                                    <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
                                        <div class="h5 fw-semibold text-dark mb-1">No bar counts yet</div>
                                        <p class="text-muted small mb-0">Castel bottle counts will appear here once today's sales are saved.</p>
                                    </div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Castel Items Breakdown Table -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">Castel Products Breakdown</h5>
                    <span class="badge bg-success rounded-pill px-3 py-2 fs-6">{{ count($breakdown) }} Castel Items</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light border-bottom">
                            <tr>
                                <th class="border-0 text-uppercase text-muted small">Item Name</th>
                                <th class="border-0 text-uppercase text-muted small">Category</th>
                                <th class="border-0 text-uppercase text-muted small">Price</th>
                                <th class="border-0 text-uppercase text-muted small text-end">Today's Bottle Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($breakdown as $item)
                                <tr class="{{ $item['counted'] > 0 ? 'table-success bg-opacity-10' : '' }}">
                                    <td class="align-middle fw-bold text-dark">
                                        <i class="bi bi-bottle me-2 {{ $item['counted'] > 0 ? 'text-success' : 'text-muted opacity-50' }}"></i>
                                        {{ $item['name'] }}
                                    </td>
                                    <td class="align-middle text-capitalize">
                                        <span class="badge bg-secondary bg-opacity-10 text-dark border-0 px-2 py-1">{{ $item['category'] }}</span>
                                    </td>
                                    <td class="align-middle fw-semibold">MWK {{ number_format($item['price'], 2) }}</td>
                                    <td class="align-middle text-end fw-bolder fs-5 {{ $item['counted'] > 0 ? 'text-success' : 'text-muted opacity-50' }}">
                                        {{ number_format($item['counted']) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-5">
                                        <div class="opacity-25 display-4 mb-3">🍺</div>
                                        <p class="text-muted mb-0">No Castel items found in inventory. Run the Castel seeder to tag items.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reset Confirmation Modal -->
<div class="modal fade" id="resetCounterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>Reset Castel Bottle Counter</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('castel.reset') }}">
                @csrf
                <input type="hidden" name="bar_id" value="{{ $selectedBarId }}">
                <div class="modal-body text-center py-4">
                    <div class="display-4 text-danger mb-3"><i class="bi bi-arrow-counterclockwise"></i></div>
                    <h5 class="fw-bold text-dark mb-2">Are you sure you want to reset?</h5>
                    <p class="text-muted small mb-0">This will reset today's Castel bottle count back to <strong>0</strong> for @if($selectedBar) <strong>{{ $selectedBar->name }}</strong> @else selected bar @endif so you can start all over.</p>
                </div>
                <div class="modal-footer bg-light justify-content-center">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4 fw-bold">Yes, Reset to 0</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
