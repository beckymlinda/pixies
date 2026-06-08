<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DailyReport;
use App\Models\DailyStockEntry;
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
        $bars = Bar::all();

        // Get stock entries for the selected date
        $stockEntries = DailyStockEntry::with(['bar', 'user', 'cashReconciliation'])
            ->whereDate('date', $date)
            ->get();

        return view('reconciliation.index', compact('stockEntries', 'bars', 'date'));
    }

    public function verify($stockEntryId)
    {
        $user = Auth::user();
        
        // Only managers and directors can verify
        if ($user->isSeller()) {
            abort(403, 'Unauthorized access');
        }

        $stockEntry = DailyStockEntry::with(['bar', 'user', 'stockEntryItems', 'payments', 'cashReconciliation'])
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
            $cashInHand = $dailyReport->cash_in_hand;
            $electronicTotal = $dailyReport->payments()->sum('amount');
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
                'matched' => '✅ Cash reconciliation completed - Perfect match!',
                'shortage' => '❌ Cash reconciliation completed - Shortage detected',
                'excess' => '⚠️ Cash reconciliation completed - Excess cash detected',
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
