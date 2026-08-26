<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashReconciliation extends Model
{
    protected $fillable = [
        'stock_entry_id',
        'expected_cash',
        'cash_counted',
        'electronic_counted',
        'difference',
        'status',
        'verified_by',
        'notes',
        'verified_at',
    ];

    protected $casts = [
        'expected_cash' => 'decimal:2',
        'cash_counted' => 'decimal:2',
        'electronic_counted' => 'decimal:2',
        'difference' => 'decimal:2',
        'verified_at' => 'datetime',
    ];

    public function stockEntry()
    {
        return $this->belongsTo(Sale::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isVerified()
    {
        return $this->verified_at !== null;
    }

    public function hasProblem()
    {
        return $this->status !== 'matched';
    }

    public function getStatusColor()
    {
        return match($this->status) {
            'matched' => 'green',
            'shortage' => 'red',
            'excess' => 'orange',
            default => 'gray'
        };
    }

    public function getStatusIcon()
    {
        return match($this->status) {
            'matched' => 'âœ…',
            'shortage' => 'âŒ',
            'excess' => 'âš ï¸',
            default => 'â“'
        };
    }

    public function getExpectedBankableAttribute()
    {
        return $this->expected_cash ?? 0;
    }

    public function getBankableCountedAttribute()
    {
        if ($this->difference !== null && $this->expected_cash !== null) {
            return $this->expected_cash + $this->difference;
        }

        return ($this->cash_counted ?? 0) + ($this->electronic_counted ?? 0) - $this->resolveExpensesTotal($this->stockEntry);
    }

    protected function resolveExpensesTotal($stockEntry)
    {
        if (!$stockEntry) {
            return 0;
        }

        $expensesTotal = $stockEntry->expenses()->sum('amount');
        if ($expensesTotal == 0) {
            $expensesTotal = Expense::where('date', $stockEntry->date)
                ->where(function ($query) use ($stockEntry) {
                    $query->where('stock_entry_id', $stockEntry->id)
                          ->orWhere('user_id', $stockEntry->user_id);
                })
                ->sum('amount');
        }

        return $expensesTotal;
    }

    public function getVarianceAttribute()
    {
        return $this->difference ?? 0;
    }

    public static function calculateExpectedCash($stockEntryId)
    {
        $stockEntry = Sale::find($stockEntryId);
        
        if (!$stockEntry) {
            return 0;
        }

        // Total sales from stock items
        $totalSales = $stockEntry->stockEntryItems()->sum('sales_amount');

        // Prefer daily report payments when available for the current shift.
        $dailyReport = DailyReport::where('bar_id', $stockEntry->bar_id)
            ->where('date', $stockEntry->date)
            ->first();

        if ($dailyReport) {
            $electronicTotal = $dailyReport->payments()->sum('amount');
        } else {
            $electronicTotal = $stockEntry->payments()->sum('amount');
        }

        // Credit sales (unpaid tabs) for the same date and bar
        $creditSales = CustomerTab::where('date', $stockEntry->date)
            ->where('bar_id', $stockEntry->bar_id)
            ->where('status', '!=', 'paid')
            ->sum('balance');

        // Expenses linked to this stock entry (payments out during the shift)
        $expensesTotal = $stockEntry->expenses()->sum('amount');

        // Expected bankable amount = total sales minus credit sales and operating expenses.
        // This is the amount that should be available to bank after all non-bankable deductions.
        $expectedCash = $totalSales - $creditSales - $expensesTotal;

        return $expectedCash;
    }

    public static function calculateExpectedCollected($stockEntryId)
    {
        $stockEntry = Sale::find($stockEntryId);

        if (!$stockEntry) {
            return 0;
        }

        $totalSales = $stockEntry->stockEntryItems()->sum('sales_amount');
        $creditSales = CustomerTab::where('date', $stockEntry->date)
            ->where('bar_id', $stockEntry->bar_id)
            ->where('status', '!=', 'paid')
            ->sum('balance');

        return $totalSales - $creditSales;
    }

    public static function createReconciliation($stockEntryId, $cashCounted, $verifiedBy, $notes = null, $electronicCounted = null)
    {
        $stockEntry = Sale::find($stockEntryId);
        if (!$stockEntry) {
            throw new \Exception('Stock entry not found');
        }

        // Prefer DailyReport values when available
        $dailyReport = DailyReport::where('bar_id', $stockEntry->bar_id)
            ->where('date', $stockEntry->date)
            ->first();

        // Total sales from stock items
        $totalSales = $stockEntry->stockEntryItems()->sum('sales_amount');

        // Electronic payments: prefer daily report payments if present.
        // Exclude the "Cash" payment row here because the physical cash count
        // ($cashCounted) already represents cash; including the row would double count.
        if ($dailyReport) {
            $electronicTotal = $dailyReport->payments()->where('payment_method', '!=', 'Cash')->sum('amount');
        } else {
            $electronicTotal = $stockEntry->payments()->sum('amount');
        }

        // Credit sales (unpaid tabs)
        $creditSales = CustomerTab::where('date', $stockEntry->date)
            ->where('bar_id', $stockEntry->bar_id)
            ->where('status', '!=', 'paid')
            ->sum('balance');

        // Expenses linked to this stock entry or fallback by date/user
        $expensesTotal = $stockEntry->expenses()->sum('amount');
        if ($expensesTotal == 0) {
            $expensesTotal = Expense::where('date', $stockEntry->date)
                ->where(function ($query) use ($stockEntry) {
                    $query->where('stock_entry_id', $stockEntry->id)
                          ->orWhere('user_id', $stockEntry->user_id);
                })
                ->sum('amount');
        }

        // Actual electronic count uses entered value when available, otherwise fallback to recorded total.
        $actualElectronic = (is_null($electronicCounted) || $electronicCounted === '')
            ? $electronicTotal
            : (float) $electronicCounted;
        $cashCounted = (float) $cashCounted;

        // Bankable amount should include raw cash, electronic receipts, and then remove expense cash outflows.
        $actualBankable = $cashCounted + $actualElectronic - $expensesTotal;

        // Expected bankable = total sales - credit sales - expenses.
        $expectedCash = $totalSales - $creditSales - $expensesTotal;

        $difference = $actualBankable - $expectedCash;

        // Determine status based on bankable variance.
        if ($difference == 0) {
            $status = 'matched';
        } elseif ($difference < 0) {
            $status = 'shortage';
        } else {
            $status = 'excess';
        }

        return self::updateOrCreate(
            ['stock_entry_id' => $stockEntryId],
            [
                'expected_cash' => $expectedCash,
                'cash_counted' => $cashCounted,
                'electronic_counted' => $actualElectronic,
                'difference' => $difference,
                'status' => $status,
                'verified_by' => $verifiedBy,
                'notes' => $notes,
                'verified_at' => now(),
            ]
        );
    }
}

