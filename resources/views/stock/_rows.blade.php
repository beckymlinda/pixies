@forelse($stockRows as $row)
    <tr>
        <td class="align-middle text-muted">{{ $loop->iteration }}</td>
        <td class="align-middle fw-semibold">{{ $row['bar_name'] }}</td>
        <td class="align-middle fw-bold text-dark">{{ $row['item_name'] }}</td>
        <td class="align-middle text-capitalize"><span class="badge bg-secondary bg-opacity-10 text-dark border-0 px-2 py-1">{{ $row['category'] }}</span></td>
        <td class="align-middle">
            @if($canManageStock)
                <span class="editable-stock fw-bold text-primary" onclick="editStock({{ $row['item_id'] }}, {{ $row['bar_id'] }}, '{{ $row['item_name'] }}', '{{ $row['bar_name'] }}', {{ $row['stock'] }})" style="cursor: pointer;" title="Click to edit stock">
                    {{ number_format($row['stock']) }}
                    <i class="bi bi-pencil-square small ms-1 opacity-75"></i>
                </span>
            @else
                <span class="fw-bold">{{ number_format($row['stock']) }}</span>
            @endif
        </td>
        <td class="align-middle fw-semibold">MWK {{ number_format($row['price'], 2) }}</td>
        <td class="align-middle fw-semibold">MWK {{ number_format($row['selling_price'], 2) }}</td>
        <td class="align-middle fw-semibold">
            @if($row['markup_percentage'] <= 0)
                <span class="text-danger">{{ number_format($row['markup_percentage'], 2) }}%</span>
            @else
                <span class="text-success">{{ number_format($row['markup_percentage'], 2) }}%</span>
            @endif
        </td>
        <td class="align-middle text-muted small">{{ \Carbon\Carbon::parse($row['last_updated'])->format('M d, Y') }}</td>
        @if($canManageStock)
            <td class="align-middle">
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline-success" onclick="restockStock({{ $row['item_id'] }}, {{ $row['bar_id'] }}, '{{ $row['item_name'] }}', '{{ $row['bar_name'] }}', {{ $row['stock'] }}, {{ $row['selling_price'] }})">
                        <i class="bi bi-plus-lg me-1"></i> Restock
                    </button>
                    <button class="btn btn-sm btn-outline-primary" onclick="editStock({{ $row['item_id'] }}, {{ $row['bar_id'] }}, '{{ $row['item_name'] }}', '{{ $row['bar_name'] }}', {{ $row['stock'] }})">
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteStock({{ $row['item_id'] }}, {{ $row['bar_id'] }}, '{{ $row['item_name'] }}', '{{ $row['bar_name'] }}')">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </div>
            </td>
        @endif
    </tr>
@empty
    <tr>
        <td colspan="{{ $canManageStock ? 10 : 9 }}" class="text-center py-5">
            <div class="opacity-25 display-4 mb-3">📦</div>
            <p class="text-muted mb-0">No stock records available for this selection.</p>
        </td>
    </tr>
@endforelse
