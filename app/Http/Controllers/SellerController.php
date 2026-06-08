<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DailyStockEntry;
use App\Models\Expense;
use App\Models\StockEntryItem;
use Carbon\Carbon;

class SellerController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();
        $bar = $user->bar;

        // Get current week's start and end dates
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        // Get weekly expenses for this seller's bar
        $weeklyExpenses = Expense::where('user_id', $user->id)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->sum('amount');

        // Get recent stock entries for this seller's bar only
        $recentEntries = DailyStockEntry::where('bar_id', $bar->id)
            ->with(['bar', 'stockEntryItems'])
            ->orderBy('date', 'desc')
            ->limit(5)
            ->get();

        // Get total sales for this seller's bar.
        // Use bar-level scope to match one-entry-per-bar-per-day schema.
        $totalSales = DailyStockEntry::where('bar_id', $bar->id)
            ->with('stockEntryItems')
            ->get()
            ->sum(function($entry) {
                return $entry->stockEntryItems->sum('sales_amount');
            });

        // Get items about to expire (within 30 days)
        $expiringItemsCount = StockEntryItem::whereNotNull('expiry_date')
            ->where('expiry_date', '>=', Carbon::now())
            ->where('expiry_date', '<=', Carbon::now()->addDays(30))
            ->whereHas('stockEntry', function($query) use ($bar) {
                $query->where('bar_id', $bar->id);
            })
            ->count();

        return view('seller.dashboard', compact('weeklyExpenses', 'recentEntries', 'totalSales', 'expiringItemsCount'));
    }
}
