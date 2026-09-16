@foreach($paginatedItems as $index => $item)
    @php
        $baseUnit = ($item['product_units'] ?? collect())->firstWhere('is_base_unit', true)
            ?? ($item['product_units'] ?? collect())->first();
        $baseUnitName = $baseUnit->unit_name ?? 'Bottle';
        $baseUnitPrice = $baseUnit->price?->selling_price ?? $item['price'];
    @endphp
    <tr data-item-id="{{ $item['id'] }}">
        <td data-label="#" class="text-center text-muted fw-semibold">{{ $loop->iteration }}</td>
        <td data-label="Item" class="ps-4">
            <div class="fw-bold text-dark">{{ $item['name'] }}</div>
            <div class="text-muted" style="font-size: 0.7rem;">{{ $item['category'] }}</div>
            <input type="hidden" name="items[{{ $index }}][item_id]" value="{{ $item['id'] }}">
            <input type="hidden" name="items[{{ $index }}][price]" value="{{ $baseUnitPrice }}">
            <input type="hidden" name="items[{{ $index }}][unit_name]" value="{{ $baseUnitName }}">
        </td>
        <td data-label="Price" class="text-center text-muted small">
            <span class="price-display">{{ number_format($baseUnitPrice) }}</span>
        </td>
        <td data-label="Opening" class="text-center">
            <input type="hidden" name="items[{{ $index }}][opening_stock]" class="opening-stock" value="{{ $item['opening_stock'] }}">
            <span class="stock-display fw-semibold">{{ number_format($item['opening_stock_display'] ?? $item['opening_stock']) }}</span>
            <div class="small text-muted">{{ $item['stock_unit'] ?? 'units' }}</div>
        </td>
        <td data-label="Orders" class="text-center">
            <input type="hidden" name="items[{{ $index }}][orders]" class="orders" value="{{ $item['ordered_stock'] }}">
            <span class="stock-display fw-semibold">{{ number_format($item['ordered_stock_display'] ?? $item['ordered_stock']) }}</span>
            <div class="small text-muted">{{ $item['stock_unit'] ?? 'units' }}</div>
            @if(($item['ordered_stock_display'] ?? $item['ordered_stock']) > 0)
                <div class="small text-success" style="font-size:0.65rem;">✓ Approved</div>
            @elseif($item['has_pending_request'] ?? false)
                <div class="small text-warning" style="font-size:0.65rem;">⏳ Awaiting approval</div>
            @endif
        </td>
        <td data-label="Closing" class="text-center">
            <input type="hidden" name="items[{{ $index }}][closing_stock]" class="closing-stock" value="{{ $item['closing_stock'] }}">
            <span class="closing-display fw-semibold">{{ number_format($item['closing_stock_display'] ?? $item['closing_stock']) }}</span>
            <div class="small text-muted">{{ $item['stock_unit'] ?? 'units' }}</div>
        </td>
        <td data-label="Sales" class="text-center">
            <div class="small text-muted mb-1">Previous: <strong class="sold-prev-display">{{ number_format($item['sold_quantity_display'] ?? $item['sold_quantity']) }}</strong> {{ $item['stock_unit'] ?? '' }}</div>
            <input type="hidden" class="previous-sales" value="{{ $item['sold_quantity'] }}">
            @if(!($item['can_sell'] ?? true))
                <div class="small text-danger mb-1" style="font-size:0.65rem;">Out of stock</div>
                <div class="d-flex align-items-center justify-content-center gap-1">
                    <input type="number" class="form-control-stock new-sales text-primary" min="0" value="" placeholder="0" readonly disabled>
                    <input type="hidden" name="items[{{ $index }}][sales]" class="sales" value="0">
                </div>
            @else
                <div class="d-flex align-items-center justify-content-center gap-1">
                    <input type="number" class="form-control-stock new-sales text-primary" min="0" value="" placeholder="0" data-available-base="{{ $item['available_stock'] }}" data-available-display="{{ $item['available_stock_display'] ?? $item['available_stock'] }}">
                    <input type="hidden" name="items[{{ $index }}][sales]" class="sales" value="0">
                    <input type="hidden" name="items[{{ $index }}][clear_sales]" class="clear-sales" value="0">
                    <button type="button" class="btn btn-clear-sale" title="Clear sales for this item" onclick="clearItemSales(this)">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            @endif
        </td>
        <td data-label="Total MWK" class="text-end pe-4 fw-bold text-dark">
            <input type="hidden" class="sales-amount-val" name="items[{{ $index }}][sales_amount]" value="{{ $item['sales_amount'] }}" data-base="{{ $item['sales_amount'] }}">
            <span class="sales-amount-display">{{ number_format($item['sales_amount']) }}</span>
        </td>
    </tr>
@endforeach
