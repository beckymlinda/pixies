@extends('layouts.app')

@section('content')
<link href="{{ asset('css/pixies.css') }}" rel="stylesheet">
<div class="container-fluid px-4 py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card page-header">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h1 class="h2 mb-2">✏️ Edit Stock Entry</h1>
                            <p class="text-muted mb-0">Update today's stock sheet with opening stock, orders, and closing stock.</p>
                            @if(auth()->user()->bar)
                                <div class="d-flex align-items-center mt-3 gap-2">
                                    <span class="badge bg-primary px-3 py-2">
                                        📍 {{ auth()->user()->bar->name }}
                                    </span>
                                    <span class="badge bg-danger px-3 py-2">
                                        📅 {{ $stockEntry->date->format('M d, Y') }}
                                    </span>
                                </div>
                            @endif
                        </div>
                        <a href="{{ route('stock-entries.show', $stockEntry) }}" 
                           class="btn pixies-btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock Entry Form -->
    <div class="row">
        <div class="col-12">
            <div class="card pixies-card stock-entry-card">
                <div class="card-header bg-gradient text-white" style="background: linear-gradient(135deg, var(--pixies-primary), var(--pixies-primary-dark)) !important;">
                    <h3 class="h4 mb-1">
                        <i class="bi bi-clipboard-data me-2"></i> Edit Stock Entry Form
                    </h3>
                    <small class="opacity-75">Update your inventory data below - track every item carefully</small>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('stock-entries.update', $stockEntry) }}" class="needs-validation" novalidate>
                        @csrf
                        @method('PUT')
                        
                        <!-- Basic Information -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="date" class="form-label fw-semibold">Date</label>
                                <input type="date" id="date" name="date" class="form-control" value="{{ $stockEntry->date->format('Y-m-d') }}" required>
                                <div class="invalid-feedback">
                                    Please select a date.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="bar_id" class="form-label fw-semibold">Location</label>
                                <select id="bar_id" name="bar_id" class="form-select" required>
                                    @if(auth()->user()->bar)
                                        <option value="{{ auth()->user()->bar->id }}" selected>{{ auth()->user()->bar->name }}</option>
                                    @else
                                        <option value="">Select Location</option>
                                        @foreach($bars ?? [] as $bar)
                                            <option value="{{ $bar->id }}" {{ $bar->id == $stockEntry->bar_id ? 'selected' : '' }}>{{ $bar->name }}</option>
                                        @endforeach
                                    @endif
                                </select>
                                <div class="invalid-feedback">
                                    Please select a location.
                                </div>
                            </div>
                        </div>

                        <!-- Stock Entry Items Table -->
                        <div class="mb-4">
                            <h5 class="fw-semibold mb-3">
                                <i class="bi bi-box-seam me-2"></i> Stock Items
                            </h5>
                            <div class="table-responsive">
                                <table class="table table-hover" id="stockItemsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Item</th>
                                            <th>Price</th>
                                            <th>Opening Stock</th>
                                            <th>Orders</th>
                                            <th>Sales</th>
                                            <th>Closing Stock</th>
                                            <th>Sales Amount</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="stockItemsBody">
                                        @foreach($stockEntry->stockEntryItems as $index => $item)
                                            <tr class="stock-item-row">
                                                <td>
                                                    <select name="items[{{ $index }}][item_id]" class="form-select item-select" required>
                                                        <option value="">Select Item</option>
                                                        @foreach($items as $itemOption)
                                                            <option value="{{ $itemOption->id }}" data-price="{{ $itemOption->price }}" {{ $itemOption->id == $item->item_id ? 'selected' : '' }}>{{ $itemOption->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="number" name="items[{{ $index }}][price]" class="form-control price" min="0" step="0.01" value="{{ $item->price }}" readonly required>
                                                </td>
                                                <td>
                                                    <input type="number" name="items[{{ $index }}][opening_stock]" class="form-control opening-stock" min="0" step="0.01" value="{{ $item->opening_stock }}" required>
                                                </td>
                                                <td>
                                                    <input type="number" name="items[{{ $index }}][orders]" class="form-control orders" min="0" step="0.01" value="{{ $item->ordered_stock }}" required>
                                                </td>
                                                <td>
                                                    <input type="number" name="items[{{ $index }}][sales]" class="form-control sales" min="0" step="0.01" value="{{ $item->sold_quantity }}" required>
                                                </td>
                                                <td>
                                                    <input type="number" name="items[{{ $index }}][closing_stock]" class="form-control closing-stock" min="0" step="0.01" value="{{ $item->closing_stock }}" readonly>
                                                </td>
                                                <td>
                                                    <input type="number" name="items[{{ $index }}][sales_amount]" class="form-control sales-amount" min="0" step="0.01" value="{{ $item->sales_amount }}" readonly>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-item" onclick="removeItem(this)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            
                            <button type="button" class="btn pixies-btn-secondary mt-3" onclick="addNewItem()">
                                <i class="bi bi-plus-circle me-1"></i> Add Item
                            </button>
                        </div>


                        <!-- Form Actions -->
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="{{ route('stock-entries.show', $stockEntry) }}" class="btn pixies-btn-secondary">
                                <i class="bi bi-x-circle me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn pixies-btn-primary">
                                <i class="bi bi-check-circle me-1"></i> Update Stock Entry
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
   /* ================================
   PIXIES CORE DESIGN SYSTEM
================================ */

:root {
    --pixies-primary: #d32f2f;
    --pixies-primary-dark: #9a0007;
    --pixies-secondary: #1976d2;
    --pixies-success: #2e7d32;
    --pixies-warning: #f9a825;
    --pixies-bg: #f4f6f9;
    --pixies-card: #ffffff;
    --pixies-border: #e5e7eb;
    --pixies-text: #2c2c2c;
    --pixies-muted: #6b7280;
}

/* ================================
   BASE
================================ */

body {
    background: var(--pixies-bg);
    font-family: 'Inter', system-ui, sans-serif;
    color: var(--pixies-text);
}

/* ================================
   HEADER
================================ */

.page-header {
    border-radius: 16px;
    border: 1px solid var(--pixies-border);
    background: linear-gradient(135deg, #ffffff, #fafafa);
}

/* ================================
   CARD
================================ */

.pixies-card {
    border-radius: 16px;
    border: 1px solid var(--pixies-border);
    box-shadow: 0 6px 18px rgba(0,0,0,0.05);
    background: var(--pixies-card);
}

.stock-entry-card {
    border-left: 6px solid var(--pixies-primary);
}

/* ================================
   CARD HEADER
================================ */

.card-header.bg-gradient {
    background: linear-gradient(135deg, var(--pixies-primary), var(--pixies-primary-dark));
    border-top-left-radius: 16px;
    border-top-right-radius: 16px;
}

/* ================================
   FORM CONTROLS (VERY IMPORTANT)
================================ */

.form-control,
.form-select {
    height: 48px;
    border-radius: 10px;
    border: 1px solid var(--pixies-border);
    font-size: 15px;
    padding: 10px 12px;
    transition: all 0.2s ease;
}

.form-control:focus,
.form-select:focus {
    border-color: var(--pixies-primary);
    box-shadow: 0 0 0 3px rgba(211, 47, 47, 0.1);
}

/* Readonly fields */
input[readonly] {
    background-color: #f9fafb;
    font-weight: 600;
}

/* Price fields specifically */
input.price[readonly] {
    background-color: #e8f5e9;
    border: 1px solid #16a34a;
    color: #16a34a;
    font-weight: 700;
}

/* ================================
   TABLE (CORE SECTION)
================================ */

#stockItemsTable {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}

#stockItemsTable thead {
    background: #f1f5f9;
    position: sticky;
    top: 0;
    z-index: 5;
}

#stockItemsTable th {
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--pixies-muted);
    padding: 12px;
}

#stockItemsTable td {
    padding: 8px;
    vertical-align: middle;
}

/* Row styling */
.stock-item-row {
    background: #fff;
    border-bottom: 1px solid var(--pixies-border);
    transition: background 0.2s;
}

.stock-item-row:hover {
    background: #f9fafb;
}

/* ================================
   INPUTS INSIDE TABLE
================================ */

#stockItemsTable .form-control,
#stockItemsTable .form-select {
    height: 44px;
    font-size: 14px;
}

/* Highlight calculated fields */
.sales-amount {
    background: #e8f5e9 !important;
    color: var(--pixies-success);
    font-weight: 700;
}

.closing-stock {
    background: #eef2ff !important;
    font-weight: 600;
}

/* ================================
   BUTTONS
================================ */

.pixies-btn-primary {
    background: var(--pixies-primary);
    border: none;
    border-radius: 12px;
    color: #fff;
    padding: 12px;
    font-weight: 600;
    transition: 0.2s;
}

.pixies-btn-primary:hover {
    background: var(--pixies-primary-dark);
}

.pixies-btn-secondary {
    background: #eef2f7;
    border: none;
    border-radius: 12px;
    padding: 10px 14px;
    font-weight: 500;
}

.pixies-btn-secondary:hover {
    background: #e2e8f0;
}

/* Remove button */
.remove-item {
    border-radius: 10px;
}

/* ================================
   MOBILE OPTIMIZATION (CRITICAL)
================================ */

@media (max-width: 768px) {

    .container-fluid {
        padding: 10px !important;
    }

    /* Table becomes scrollable */
    .table-responsive {
        overflow-x: auto;
    }

    #stockItemsTable {
        min-width: 900px;
    }

    /* Bigger inputs for touch */
    .form-control,
    .form-select {
        height: 52px;
        font-size: 16px;
    }

    /* Sticky submit bar */
    .d-flex.justify-content-end {
        position: sticky;
        bottom: 0;
        background: #fff;
        padding: 10px;
        border-top: 1px solid var(--pixies-border);
        z-index: 10;
    }

    .pixies-btn-primary {
        width: 100%;
        font-size: 16px;
        padding: 14px;
    }

    .pixies-btn-secondary {
        width: 100%;
    }

    /* Stack buttons */
    .d-flex.gap-2 {
        flex-direction: column;
    }
}

/* ================================
   ANIMATIONS
================================ */

.card {
    animation: fadeIn 0.25s ease;
}

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
</style>
<script>
// Stock Entry Form JavaScript
let itemIndex = {{ $stockEntry->stockEntryItems->count() }};

function addNewItem() {
    const tbody = document.getElementById('stockItemsBody');
    const newRow = document.createElement('tr');
    newRow.className = 'stock-item-row';
    newRow.innerHTML = `
        <td>
            <select name="items[${itemIndex}][item_id]" class="form-select item-select" required>
                <option value="">Select Item</option>
                @foreach($items as $item)
                    <option value="{{ $item->id }}" data-price="{{ $item->price }}">{{ $item->name }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="number" name="items[${itemIndex}][price]" class="form-control price" min="0" step="0.01" readonly required>
        </td>
        <td>
            <input type="number" name="items[${itemIndex}][opening_stock]" class="form-control opening-stock" min="0" step="0.01" required>
        </td>
        <td>
            <input type="number" name="items[${itemIndex}][orders]" class="form-control orders" min="0" step="0.01" required>
        </td>
        <td>
            <input type="number" name="items[${itemIndex}][sales]" class="form-control sales" min="0" step="0.01" required>
        </td>
        <td>
            <input type="number" name="items[${itemIndex}][closing_stock]" class="form-control closing-stock" min="0" step="0.01" readonly>
        </td>
        <td>
            <input type="number" name="items[${itemIndex}][sales_amount]" class="form-control sales-amount" min="0" step="0.01" readonly>
        </td>
        <td>
            <button type="button" class="btn btn-sm btn-outline-danger remove-item" onclick="removeItem(this)">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(newRow);
    itemIndex++;
    
    // Add event listeners to new row
    addRowEventListeners(newRow);
}

function removeItem(button) {
    const row = button.closest('tr');
    const tbody = document.getElementById('stockItemsBody');
    
    if (tbody.children.length > 1) {
        row.remove();
        calculateTotals();
        
        // Update dropdowns to restore the removed item (only on create page)
        if (!window.isEditMode) {
            updateAllDropdowns();
        }
    } else {
        alert('You must have at least one item.');
    }
}

function calculateClosingStock(row) {
    const openingStock = parseFloat(row.querySelector('.opening-stock').value) || 0;
    const orders = parseFloat(row.querySelector('.orders').value) || 0;
    const sales = parseFloat(row.querySelector('.sales').value) || 0;
    const closingStock = openingStock + orders - sales;
    
    row.querySelector('.closing-stock').value = closingStock.toFixed(2);
}

function calculateSalesAmount(row) {
    const sales = parseFloat(row.querySelector('.sales').value) || 0;
    const price = parseFloat(row.querySelector('.price').value) || 0;
    const salesAmount = sales * price;
    
    row.querySelector('.sales-amount').value = salesAmount.toFixed(2);
}

function calculateTotals() {
    let totalSales = 0;
    
    document.querySelectorAll('.sales-amount').forEach(input => {
        totalSales += parseFloat(input.value) || 0;
    });
    
    // Update any total calculations if needed
}

function addRowEventListeners(row) {
    // Auto-populate price when item is selected
    row.querySelector('.item-select').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const price = selectedOption.getAttribute('data-price');
        const selectedItemId = this.value;
        
        if (price) {
            row.querySelector('.price').value = price;
            calculateSalesAmount(row);
            calculateTotals();
        }
        
        // Update dropdowns to remove selected item (only on create page)
        if (!window.isEditMode) {
            updateAllDropdowns();
        }
    });
    
    // Calculate closing stock when opening stock, orders, or sales change
    ['opening-stock', 'orders', 'sales'].forEach(className => {
        row.querySelector(`.${className}`).addEventListener('input', () => {
            calculateClosingStock(row);
            calculateSalesAmount(row);
            calculateTotals();
        });
    });
}

// Function to update all dropdowns to remove already selected items
function updateAllDropdowns() {
    const allSelects = document.querySelectorAll('.item-select');
    const selectedItems = new Set();
    
    // Collect all selected item IDs
    allSelects.forEach(select => {
        if (select.value) {
            selectedItems.add(select.value);
        }
    });
    
    // Update each dropdown
    allSelects.forEach(select => {
        const currentValue = select.value;
        const options = select.querySelectorAll('option');
        
        options.forEach(option => {
            const itemId = option.value;
            
            if (itemId === '') {
                // Keep the "Select Item" option
                option.style.display = 'block';
                option.disabled = false;
            } else if (itemId === currentValue) {
                // Keep the current selection
                option.style.display = 'block';
                option.disabled = false;
            } else if (selectedItems.has(itemId)) {
                // Hide already selected items
                option.style.display = 'none';
                option.disabled = true;
            } else {
                // Show available items
                option.style.display = 'block';
                option.disabled = false;
            }
        });
    });
}

// Function to check for duplicate items and show user-friendly message
function checkForDuplicatesAndShowMessage() {
    if (hasDuplicateItems()) {
        // Find the first duplicate and highlight it
        const allSelects = document.querySelectorAll('.item-select');
        const selectedItems = new Set();
        let duplicateFound = false;
        
        for (const select of allSelects) {
            const value = select.value;
            if (value) {
                if (selectedItems.has(value)) {
                    // Highlight the duplicate
                    select.classList.add('is-invalid');
                    select.focus();
                    
                    // Show user-friendly message
                    const row = select.closest('tr');
                    const message = document.createElement('div');
                    message.className = 'alert alert-warning mt-2';
                    message.innerHTML = '<strong>Duplicate Item!</strong> This item is already selected above. Please choose a different item.';
                    
                    // Remove any existing message
                    const existingMessage = row.querySelector('.alert');
                    if (existingMessage) {
                        existingMessage.remove();
                    }
                    
                    row.appendChild(message);
                    
                    // Remove highlight after 3 seconds
                    setTimeout(() => {
                        select.classList.remove('is-invalid');
                        if (message.parentNode) {
                            message.remove();
                        }
                    }, 3000);
                    
                    duplicateFound = true;
                    break;
                }
                selectedItems.add(value);
            }
        }
        
        return duplicateFound;
    }
    
    return false;
}

// Function to restore all dropdowns (for edit mode or when item is removed)
function restoreAllDropdowns() {
    const allSelects = document.querySelectorAll('.item-select');
    
    allSelects.forEach(select => {
        const options = select.querySelectorAll('option');
        
        options.forEach(option => {
            option.style.display = 'block';
            option.disabled = false;
        });
    });
}

// Initialize event listeners for existing rows
document.addEventListener('DOMContentLoaded', function() {
    // Determine if we're in edit mode (check URL or existing data)
    window.isEditMode = window.location.pathname.includes('/edit') || 
                       document.querySelector('form').getAttribute('action').includes('/edit');
    
    document.querySelectorAll('.stock-item-row').forEach(row => {
        addRowEventListeners(row);
    });
    
    // Initialize dropdowns based on mode
    if (!window.isEditMode) {
        updateAllDropdowns();
    }
    
    // Form validation
    const form = document.querySelector('.needs-validation');
    form.addEventListener('submit', function(event) {
        // Check for duplicate items before submission (only on create page)
        if (!window.isEditMode && checkForDuplicatesAndShowMessage()) {
            event.preventDefault();
            event.stopPropagation();
            return;
        }
        
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    }, false);
});

// Function to check for duplicate items
function hasDuplicateItems() {
    const selectedItems = new Set();
    const allSelects = document.querySelectorAll('.item-select');
    
    for (const select of allSelects) {
        const value = select.value;
        if (value) {
            if (selectedItems.has(value)) {
                return true; // Found duplicate
            }
            selectedItems.add(value);
        }
    }
    
    return false; // No duplicates found
}
</script>
@endsection
