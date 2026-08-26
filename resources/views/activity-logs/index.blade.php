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
                            <h1 class="h3 fw-bold mb-1 text-dark">Activity Log</h1>
                            <p class="text-muted small mb-0">A simple history of what was changed, by whom, and when.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="GET" action="{{ route('activity-logs.index') }}" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Type of Change</label>
                            <select name="action" class="form-select">
                                <option value="">All Changes</option>
                                @foreach($availableActions as $action)
                                    <option value="{{ $action }}" {{ request()->action == $action ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $action)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">From Date</label>
                            <input type="date" name="date_from" class="form-control" value="{{ request()->date_from }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">To Date</label>
                            <input type="date" name="date_to" class="form-control" value="{{ request()->date_to }}">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-filter me-1"></i> Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Timeline -->
    <div class="row">
        <div class="col-12">
            @forelse($activityLogs as $log)
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-3 p-md-4">
                        <div class="d-flex align-items-start gap-3">
                            {{-- Icon --}}
                            <div class="flex-shrink-0 rounded-circle d-flex align-items-center justify-content-center bg-{{ $log->action_color }} bg-opacity-10"
                                 style="width: 48px; height: 48px;">
                                <i class="bi {{ $log->action_icon }} fs-4 text-{{ $log->action_color }}"></i>
                            </div>

                            {{-- Body --}}
                            <div class="flex-grow-1">
                                <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-2">
                                    <div>
                                        <div class="fw-bold text-dark">{{ $log->action_label }}</div>
                                        <div class="small text-muted">
                                            by <span class="fw-semibold">{{ $log->user->name ?? 'System' }}</span>
                                            &bull; {{ $log->created_at->diffForHumans() }}
                                            &bull; {{ $log->created_at->format('M d, Y \a\t H:i') }}
                                        </div>
                                    </div>
                                    <span class="badge bg-{{ $log->action_color }} {{ in_array($log->action_color, ['warning', 'info']) ? 'text-dark' : 'text-white' }}">
                                        {{ $log->action_label }}
                                    </span>
                                </div>

                                @if($log->description)
                                    <p class="mb-0 mt-2 text-dark">{{ $log->description }}</p>
                                @endif

                                {{-- What changed --}}
                                @if(count($log->friendly_changes) > 0 || $log->units_summary)
                                    <div class="mt-3 p-3 rounded-3 bg-light">
                                        <div class="small text-muted text-uppercase fw-bold mb-2" style="font-size: 0.65rem;">What Changed</div>

                                        @foreach($log->friendly_changes as $change)
                                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2 small">
                                                <span class="fw-semibold text-dark">{{ $change['label'] }}:</span>
                                                @if($change['old'] !== '—')
                                                    <span class="text-danger text-decoration-line-through">{{ $change['old'] }}</span>
                                                    <i class="bi bi-arrow-right text-muted"></i>
                                                @endif
                                                <span class="fw-bold text-success">{{ $change['new'] }}</span>
                                            </div>
                                        @endforeach

                                        @if($log->units_summary)
                                            <div class="d-flex flex-wrap align-items-center gap-2 small">
                                                <span class="fw-semibold text-dark">Selling units:</span>
                                                <span class="fw-bold text-success">{{ $log->units_summary }}</span>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <div class="opacity-25 display-4 mb-3">📋</div>
                        <p class="text-muted mb-0">No activity found for this selection.</p>
                    </div>
                </div>
            @endforelse

            <!-- Pagination -->
            @if($activityLogs->hasPages())
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        {{ $activityLogs->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
