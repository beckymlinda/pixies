<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\BottleCount;
use App\Models\Bar;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CastelController extends Controller
{
    /**
     * Display Castel Bottle Count Page
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $bars = Bar::listed()->orderBy('name')->get();

        $selectedBarId = $request->query('bar_id');
        if ($user->isSeller()) {
            $selectedBarId = $user->bar_id;
        } elseif (!$selectedBarId && $bars->isNotEmpty()) {
            $selectedBarId = $bars->first()->id;
        }

        // Auto-mark any items matching Castel keywords if not marked yet
        $keywords = ['green', 'special', 'doppel', 'chill', 'kucheminerals', 'sapitwa', 'castel', 'pome', 'breezer', 'gin', 'brandy', 'carlsberg'];
        Item::where('is_castel', false)->chunk(100, function ($items) use ($keywords) {
            foreach ($items as $item) {
                $nameLower = strtolower($item->name);
                foreach ($keywords as $k) {
                    if (str_contains($nameLower, $k)) {
                        $item->update(['is_castel' => true]);
                        break;
                    }
                }
            }
        });

        $today = now()->toDateString();

        // Get total count for today
        $todayTotalCount = BottleCount::whereDate('date', $today)
            ->when($selectedBarId, function($q) use ($selectedBarId) {
                $q->where('bar_id', $selectedBarId);
            })
            ->sum('counted');

        // Itemized breakdown for Castel marked items
        $castelItems = Item::where('is_castel', true)->orderBy('name')->get();

        $itemCounts = BottleCount::whereDate('date', $today)
            ->when($selectedBarId, function($q) use ($selectedBarId) {
                $q->where('bar_id', $selectedBarId);
            })
            ->get()
            ->groupBy('item_id');

        $breakdown = $castelItems->map(function ($item) use ($itemCounts) {
            $counts = $itemCounts->get($item->id);
            $counted = $counts ? (int) $counts->sum('counted') : 0;
            return [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category,
                'price' => $item->price,
                'counted' => $counted,
            ];
        })->sortByDesc('counted')->values();

        $selectedBar = $selectedBarId ? Bar::find($selectedBarId) : null;

        $barCounts = BottleCount::whereDate('date', $today)
            ->join('bars', 'bottle_counts.bar_id', '=', 'bars.id')
            ->select('bars.id', 'bars.name', DB::raw('SUM(bottle_counts.counted) as total_counted'))
            ->groupBy('bars.id', 'bars.name')
            ->orderByDesc('total_counted')
            ->get();

        return view('castel.index', compact('bars', 'selectedBarId', 'selectedBar', 'todayTotalCount', 'breakdown', 'barCounts'));
    }

    /**
     * Reset Castel bottle count for today (starting all over)
     */
    public function reset(Request $request)
    {
        $user = Auth::user();
        $barId = $request->input('bar_id');

        if ($user->isSeller()) {
            $barId = $user->bar_id;
        }

        if (!$barId) {
            return back()->with('error', 'Bar location is required to reset counter.');
        }

        $bar = Bar::find($barId);
        $today = now()->toDateString();

        $previousTotal = (int) BottleCount::whereDate('date', $today)
            ->where('bar_id', $barId)
            ->sum('counted');

        // Reset all bottle counts for today at this bar to 0
        BottleCount::whereDate('date', $today)
            ->where('bar_id', $barId)
            ->update(['counted' => 0]);

        ActivityLog::log([
            'action' => 'castel_count_reset',
            'description' => "Reset Castel bottle counter for bar '".($bar->name ?? 'Unknown')."' from {$previousTotal} to 0",
            'subject_type' => Bar::class,
            'subject_id' => $barId,
            'old_values' => ['counted' => $previousTotal, 'bar' => $bar?->name],
            'new_values' => ['counted' => 0, 'bar' => $bar?->name],
        ]);

        return redirect()->route('castel.index', ['bar_id' => $barId])
            ->with('success', "Castel bottle counter reset to 0 for ".($bar->name ?? 'selected bar').". Ready to start fresh!");
    }
}
