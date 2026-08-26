<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bar;
use App\Models\Sale;
use App\Models\Expense;
use App\Models\StockEntryItem;
use Carbon\Carbon;

class SellerController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();
        $bar = $user->bar;

        if (! $bar && $user->isSeller()) {
            $bar = Bar::listed()->orderBy('name')->first();
        }

        if (! $bar) {
            return view('seller.dashboard', [
                'weeklyExpenses' => 0,
                'recentEntries' => collect(),
                'totalSales' => 0,
                'expiringItemsCount' => 0,
                'noBarAssigned' => true,
            ]);
        }

        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        $weeklyExpenses = Expense::where('user_id', $user->id)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->sum('amount');

        $recentEntries = Sale::where('bar_id', $bar->id)
            ->with(['bar', 'stockEntryItems'])
            ->orderBy('date', 'desc')
            ->limit(5)
            ->get();

        $todayEntry = Sale::where('bar_id', $bar->id)
            ->whereDate('date', Carbon::today())
            ->first();

        $totalSales = Sale::where('bar_id', $bar->id)
            ->with('stockEntryItems')
            ->get()
            ->sum(function ($entry) {
                return $entry->stockEntryItems->sum('sales_amount');
            });

        $expiringItemsCount = StockEntryItem::whereNotNull('expiry_date')
            ->where('expiry_date', '>=', Carbon::now())
            ->where('expiry_date', '<=', Carbon::now()->addDays(30))
            ->whereHas('stockEntry', function ($query) use ($bar) {
                $query->where('bar_id', $bar->id);
            })
            ->count();

        return view('seller.dashboard', compact(
            'weeklyExpenses',
            'recentEntries',
            'todayEntry',
            'totalSales',
            'expiringItemsCount'
        ));
    }
}

