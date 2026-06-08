<?php

namespace App\Http\Controllers;

use App\Models\StockEntryItem;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StockExpiryController extends Controller
{
    /**
     * Get items about to expire (within 30 days)
     */
    public function getExpiringItems($barId = null)
    {
        $query = StockEntryItem::whereNotNull('expiry_date')
            ->where('expiry_date', '>=', Carbon::now())
            ->where('expiry_date', '<=', Carbon::now()->addDays(30))
            ->with(['item', 'stockEntry.bar'])
            ->orderBy('expiry_date', 'asc');

        if ($barId) {
            $query->whereHas('stockEntry', function($q) use ($barId) {
                $q->where('bar_id', $barId);
            });
        }

        return $query->get();
    }

    /**
     * Get count of items about to expire
     */
    public function getExpiringCount($barId = null)
    {
        return $this->getExpiringItems($barId)->count();
    }

    /**
     * Show all expiring items
     */
    public function index(Request $request)
    {
        $barId = $request->query('bar_id');
        
        // Get all items with expiry dates, ordered by expiry date
        $query = StockEntryItem::whereNotNull('expiry_date')
            ->where('expiry_date', '>=', Carbon::now()->subDays(1)) // Include recently expired
            ->with(['item', 'stockEntry.bar'])
            ->orderBy('expiry_date', 'asc');

        if ($barId) {
            $query->whereHas('stockEntry', function($q) use ($barId) {
                $q->where('bar_id', $barId);
            });
        }

        $expiryItems = $query->get();

        return view('stock-expiry.index', compact('expiryItems'));
    }

    /**
     * Get items expiring for seller dashboard
     */
    public function sellerExpiringItems()
    {
        $user = auth()->user();
        $bar = $user->bar;

        return $this->getExpiringItems($bar->id);
    }

    /**
     * Get expiring items count for seller dashboard
     */
    public function sellerExpiringCount()
    {
        $user = auth()->user();
        $bar = $user->bar;

        return $this->getExpiringCount($bar->id);
    }

    /**
     * Get all expiring items for manager dashboard (all bars)
     */
    public function managerExpiringItems()
    {
        return $this->getExpiringItems();
    }

    /**
     * Get expiring items count for manager dashboard
     */
    public function managerExpiringCount()
    {
        return $this->getExpiringCount();
    }
}
