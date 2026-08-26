<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DailyReport;
use App\Models\Sale;
use App\Models\CashReconciliation;
use App\Models\Bar;
use App\Models\CustomerTab;
use App\Models\Expense;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReconciliationController extends Controller
{
    
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Only managers and directors can access reconciliation
        if ($user->isSeller()) {
            abort(403, 'Unauthorized access');
        }

        $date = $request->get('date', now()->format('Y-m-d'));
        $bars = Bar::listed()->get();

        $stockEntries = Sale::with(['bar', 'user', 'cashReconciliation', 'stockEntryItems', 'payments'])
            ->whereDate('date', $date)
            ->get();

        $paymentBreakdowns = [];
        $expenditureBreakdowns = [];
        $creditSalesByBar = [];
        foreach ($stockEntries as $entry) {
            $paymentBreakdowns[$entry->bar_id] = $this->getPaymentBreakdown($entry);
            $expenditureBreakdowns[$entry->bar_id] = $this->getExpenditureBreakdown($entry);
            $creditSalesByBar[$entry->bar_id] = $this->getCreditSales($entry);
        }

        return view('reconciliation.index', compact(
            'stockEntries',
            'bars',
            'date',
            'paymentBreakdowns',
            'expenditureBreakdowns',
            'creditSalesByBar'
        ));
    }

    /**
     * Payment amounts grouped by method (daily report preferred, stock entry payments as fallback).
     */
    private function getPaymentBreakdown(Sale $entry): array
    {
        $dailyReport = DailyReport::where('bar_id', $entry->bar_id)
            ->where('date', $entry->date)
            ->with('payments')
            ->first();

        if ($dailyReport) {
            $breakdown = $dailyReport->payments
                ->groupBy('payment_method')
                ->map(fn ($items) => (float) $items->sum('amount'))
                ->toArray();

            // The stored "Cash" payment row and cash_in_hand represent the SAME cash.
            // Only fall back to cash_in_hand when there is no explicit Cash row,
            // otherwise cash would be counted twice.
            $hasCashRow = $dailyReport->payments->contains('payment_method', 'Cash');
            if (!$hasCashRow && $dailyReport->cash_in_hand > 0) {
                $breakdown['Cash'] = ($breakdown['Cash'] ?? 0) + (float) $dailyReport->cash_in_hand;
            }

            return $breakdown;
        }

        $labels = [
            'mpamba' => 'Mpamba',
            'airtel_money' => 'Airtel Money',
            'mo626' => 'MO626',
            'bank' => 'Bank',
            'pos' => 'POS',
            'cash' => 'Cash',
        ];

        $breakdown = [];
        foreach ($entry->payments as $payment) {
            $label = $labels[$payment->type] ?? ucfirst(str_replace('_', ' ', $payment->type));
            $breakdown[$label] = ($breakdown[$label] ?? 0) + (float) $payment->amount;
        }

        return $breakdown;
    }

    private function getExpenditureBreakdown(Sale $entry): array
    {
        $expenses = Expense::where('date', $entry->date)
            ->where(function ($query) use ($entry) {
                $query->where('stock_entry_id', $entry->id)
                    ->orWhere('user_id', $entry->user_id);
            })
            ->get();

        $breakdown = [];
        foreach ($expenses as $expense) {
            $label = Expense::typeLabel($expense->type);
            $breakdown[$label] = ($breakdown[$label] ?? 0) + (float) $expense->amount;
        }

        $shiftDebt = CustomerTab::where('date', $entry->date)
            ->where('bar_id', $entry->bar_id)
            ->where('description', 'like', Expense::SHIFT_DEBT_PREFIX . '%')
            ->sum('amount');

        if ($shiftDebt > 0) {
            $breakdown['Debt (Credit Sale)'] = (float) $shiftDebt;
        }

        return $breakdown;
    }

    private function getCreditSales(Sale $entry): float
    {
        return (float) CustomerTab::where('date', $entry->date)
            ->where('bar_id', $entry->bar_id)
            ->where('status', '!=', 'paid')
            ->sum('balance');
    }

    public function verify($stockEntryId)
    {
        $user = Auth::user();
        
        // Only managers and directors can verify
        if ($user->isSeller()) {
            abort(403, 'Unauthorized access');
        }

        $stockEntry = Sale::with(['bar', 'user', 'stockEntryItems', 'payments', 'cashReconciliation'])
            ->findOrFail($stockEntryId);

        // Check if already verified
        if ($stockEntry->cashReconciliation && $stockEntry->cashReconciliation->isVerified()) {
            return redirect()->route('reconciliation.index')
                ->with('info', 'This entry has already been verified.');
        }

        // Load the sales source and matching daily report
        $totalSales = $stockEntry->stockEntryItems->sum('sales_amount');
        $dailyReport = DailyReport::where('bar_id', $stockEntry->bar_id)
            ->where('date', $stockEntry->date)
            ->first();

        if ($dailyReport) {
            // Cash row and cash_in_hand are the same cash; prefer the row when present
            // and keep electronic to non-cash methods to avoid double counting.
            $cashRowTotal = (float) $dailyReport->payments()->where('payment_method', 'Cash')->sum('amount');
            $cashInHand = $cashRowTotal > 0 ? $cashRowTotal : (float) $dailyReport->cash_in_hand;
            $electronicTotal = (float) $dailyReport->payments()->where('payment_method', '!=', 'Cash')->sum('amount');
        } else {
            $cashInHand = 0;
            $electronicTotal = $stockEntry->payments->sum('amount');
        }

        $totalCollected = $cashInHand + $electronicTotal;
        // Compute expected cash using the same rules as CashReconciliation (sales - electronic - credit - expenses)
        $expectedCash = CashReconciliation::calculateExpectedCash($stockEntry->id);
        $expectedCollected = CashReconciliation::calculateExpectedCollected($stockEntry->id);

        $creditSales = CustomerTab::where('date', $stockEntry->date)
            ->where('bar_id', $stockEntry->bar_id)
            ->where('status', '!=', 'paid')
            ->sum('balance');

        $totalExpenses = Expense::where('stock_entry_id', $stockEntry->id)
            ->sum('amount');

        if ($totalExpenses == 0) {
            $totalExpenses = Expense::where('date', $stockEntry->date)
                ->where(function ($query) use ($stockEntry) {
                    $query->where('stock_entry_id', $stockEntry->id)
                          ->orWhere('user_id', $stockEntry->user_id);
                })
                ->sum('amount');
        }

        $bankableBalance = $totalCollected - $totalExpenses;

        return view('reconciliation.verify', compact(
            'stockEntry',
            'totalSales',
            'electronicTotal',
            'expectedCash',
            'expectedCollected',
            'creditSales',
            'totalExpenses',
            'bankableBalance',
            'cashInHand',
            'dailyReport'
        ));
    }

    public function store(Request $request, $stockEntryId)
    {
        $user = Auth::user();
        
        // Only managers and directors can verify
        if ($user->isSeller()) {
            abort(403, 'Unauthorized access');
        }

        $request->validate([
            'cash_counted' => 'required|numeric|min:0',
            'electronic_counted' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        
        try {
            // Create or update reconciliation (pass electronic_counted when provided)
            $reconciliation = CashReconciliation::createReconciliation(
                $stockEntryId,
                $request->cash_counted,
                $user->id,
                $request->notes,
                $request->electronic_counted ?? null
            );

            DB::commit();
            
            $message = match($reconciliation->status) {
                'matched' => 'âœ… Cash reconciliation completed - Perfect match!',
                'shortage' => 'âŒ Cash reconciliation completed - Shortage detected',
                'excess' => 'âš ï¸ Cash reconciliation completed - Excess cash detected',
                default => 'Cash reconciliation completed'
            };

            return redirect()->route('reconciliation.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()
                ->with('error', 'Error saving reconciliation: ' . $e->getMessage());
        }
    }

    public function history(Request $request)
    {
        $user = Auth::user();
        
        // Only managers and directors can view history
        if ($user->isSeller()) {
            abort(403, 'Unauthorized access');
        }

        $startDate = $request->get('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $reconciliations = CashReconciliation::with(['stockEntry.bar', 'stockEntry.user', 'verifier'])
            ->whereHas('stockEntry', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Summary statistics
        $summary = [
            'total' => $reconciliations->total(),
            'matched' => $reconciliations->where('status', 'matched')->count(),
            'shortages' => $reconciliations->where('status', 'shortage')->count(),
            'excess' => $reconciliations->where('status', 'excess')->count(),
            'total_shortage_amount' => $reconciliations->where('status', 'shortage')->sum('difference'),
            'total_excess_amount' => $reconciliations->where('status', 'excess')->sum('difference'),
        ];

        return view('reconciliation.history', compact('reconciliations', 'summary', 'startDate', 'endDate'));
    }
}

