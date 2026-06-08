<?php

namespace App\Http\Controllers;

use App\Models\StockEntryItem;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ManagerController extends Controller
{
    public function dashboard()
    {
        // Get items about to expire (within 30 days) across all bars
        $expiringItemsCount = StockEntryItem::whereNotNull('expiry_date')
            ->where('expiry_date', '>=', Carbon::now())
            ->where('expiry_date', '<=', Carbon::now()->addDays(30))
            ->count();

        return view('manager.dashboard', compact('expiringItemsCount'));
    }
}
