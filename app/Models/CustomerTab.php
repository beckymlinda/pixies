<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class CustomerTab extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_name',
        'phone',
        'bar_id',
        'date',
        'amount',
        'description',
        'paid_amount',
        'status',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    /**
     * Get the bar that owns the customer tab.
     */
    public function bar(): BelongsTo
    {
        return $this->belongsTo(Bar::class);
    }

    /**
     * Get the user who created the customer tab.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Update the status based on paid amount.
     */
    public function updateStatus(): void
    {
        if ($this->paid_amount >= $this->amount) {
            $this->status = 'paid';
        } elseif ($this->paid_amount > 0) {
            $this->status = 'partial';
        } else {
            $this->status = 'open';
        }
        $this->save();
    }

    /**
     * Add payment to the tab.
     */
    public function addPayment(float $amount): void
    {
        $this->paid_amount += $amount;
        $this->updateStatus();
    }

    /**
     * Get the remaining balance.
     */
    public function getRemainingBalanceAttribute(): float
    {
        return $this->amount - $this->paid_amount;
    }

    /**
     * Scope to get tabs for a specific bar.
     */
    public function scopeForBar($query, $barId)
    {
        return $query->where('bar_id', $barId);
    }

    /**
     * Scope to get unpaid tabs.
     */
    public function scopeUnpaid($query)
    {
        return $query->where('status', '!=', 'paid');
    }

    /**
     * Scope to get tabs by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Get customer's total balance across all tabs.
     */
    public static function getCustomerBalance($customerName, $barId): float
    {
        return self::forBar($barId)
            ->where('customer_name', $customerName)
            ->unpaid()
            ->sum('balance');
    }

    /**
     * Get all customers with their total balances.
     */
    public static function getCustomersWithBalances($barId): \Illuminate\Support\Collection
    {
        return self::forBar($barId)
            ->selectRaw('
                customer_name,
                phone,
                SUM(amount) as total_amount,
                SUM(paid_amount) as total_paid,
                SUM(balance) as total_balance,
                MAX(date) as last_activity,
                GROUP_CONCAT(DISTINCT status) as statuses
            ')
            ->groupBy('customer_name', 'phone')
            ->orderBy('total_balance', 'desc')
            ->get()
            ->map(function ($customer) {
                $customer->status = $customer->total_balance <= 0 ? 'paid' : 
                                 ($customer->total_paid > 0 ? 'partial' : 'open');
                return $customer;
            });
    }

    /**
     * Get daily credit sales for a specific date.
     */
    public static function getDailyCreditSales($date, $barId): float
    {
        return self::forBar($barId)
            ->whereDate('date', $date)
            ->sum('amount');
    }

    /**
     * Get total outstanding credit for a bar.
     */
    public static function getTotalOutstandingCredit($barId): float
    {
        return self::forBar($barId)
            ->unpaid()
            ->sum('balance');
    }

    /**
     * Format amount for display.
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 0);
    }

    /**
     * Format paid amount for display.
     */
    public function getFormattedPaidAmountAttribute(): string
    {
        return number_format($this->paid_amount, 0);
    }

    /**
     * Format balance for display.
     */
    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->balance, 0);
    }

    /**
     * Get status badge HTML.
     */
    public function getStatusBadgeAttribute(): string
    {
        $badges = [
            'open' => '<span class="badge bg-danger">Open</span>',
            'partial' => '<span class="badge bg-warning">Partial</span>',
            'paid' => '<span class="badge bg-success">Paid</span>',
        ];

        return $badges[$this->status] ?? $badges['open'];
    }
}
