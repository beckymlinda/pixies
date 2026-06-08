@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">

<div class="container-fluid px-4 py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
                        <div>
                            <h1 class="h3 fw-bold mb-1 text-dark">Stock</h1>
                            <p class="text-muted small mb-0">View current bar stock across all locations and filter by bar or item.</p>
                        </div>
                        <form method="GET" action="{{ route('stock.index') }}" class="w-100 w-md-auto">
                            <div class="input-group shadow-sm rounded-pill overflow-hidden border border-secondary border-opacity-10">
                                <select name="bar_id" class="form-select border-0">
                                    <option value="">All Bars</option>
                                    @foreach($bars as $bar)
                                        <option value="{{ $bar->id }}" {{ $selectedBarId == $bar->id ? 'selected' : '' }}>{{ $bar->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-primary px-4">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light border-bottom">
                            <tr>
                                <th class="border-0 text-uppercase text-muted small">Bar</th>
                                <th class="border-0 text-uppercase text-muted small">Item</th>
                                <th class="border-0 text-uppercase text-muted small">Category</th>
                                <th class="border-0 text-uppercase text-muted small">Stock</th>
                                <th class="border-0 text-uppercase text-muted small">Price</th>
                                <th class="border-0 text-uppercase text-muted small">Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stockRows as $row)
                                <tr>
                                    <td class="align-middle">{{ $row['bar_name'] }}</td>
                                    <td class="align-middle">{{ $row['item_name'] }}</td>
                                    <td class="align-middle text-capitalize">{{ $row['category'] }}</td>
                                    <td class="align-middle">{{ number_format($row['stock']) }}</td>
                                    <td class="align-middle">MWK {{ number_format($row['price'], 2) }}</td>
                                    <td class="align-middle">{{ \Carbon\Carbon::parse($row['last_updated'])->format('M d, Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="opacity-25 display-4 mb-3">📦</div>
                                        <p class="text-muted mb-0">No stock records available for this selection.</p>
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
@endsection
