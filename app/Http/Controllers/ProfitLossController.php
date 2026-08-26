<?php

namespace App\Http\Controllers;

use App\Models\Bar;
use App\Models\CustomerTab;
use App\Models\DailyReport;
use App\Models\DailyReportPayment;
use App\Models\Sale;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\ProductUnit;
use App\Models\ProductUnitPrice;
use App\Models\StockEntryItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProfitLossController extends Controller
{
    /**
     * Resolve cost per base unit for COGS.
     * sold_quantity is always stored in base units; costs must match warehouse formula:
     * total_purchase_cost / (quantity_purchased * conversion_factor).
     */
    private function resolveBaseUnitCost(StockEntryItem $item): float
    {
        $item->loadMissing('item');

        $baseUnit = ProductUnit::where('item_id', $item->item_id)
            ->where('is_base_unit', true)
            ->first();

        if ($baseUnit) {
            $unitPrice = ProductUnitPrice::where('item_id', $item->item_id)
                ->where('unit_name', $baseUnit->unit_name)
                ->first();
            if ($unitPrice && $unitPrice->purchase_price > 0) {
                return (float) $unitPrice->purchase_price;
            }
        }

        if ($item->item && ($item->item->average_unit_cost ?? 0) > 0) {
            return (float) $item->item->average_unit_cost;
        }

        $cost = (float) ($item->purchase_price ?? 0);
        if ($cost > 0 && $item->unit_name) {
            $unit = ProductUnit::where('item_id', $item->item_id)
                ->where('unit_name', $item->unit_name)
                ->first();
            if ($unit && ! $unit->is_base_unit && $unit->conversion_factor > 0) {
                return $cost / $unit->conversion_factor;
            }
        }

        return $cost;
    }

    private function calculatePurchaseCost(Collection $stockItems): float
    {
        $purchaseCost = 0;
        foreach ($stockItems as $item) {
            $purchaseCost += $this->resolveBaseUnitCost($item) * $item->sold_quantity;
        }

        return $purchaseCost;
    }

    private function stockItemsQuery(Carbon $date, $bar = null, ?Bar $selectedBar = null): Builder
    {
        return StockEntryItem::whereHas('stockEntry', function ($q) use ($date, $bar, $selectedBar) {
            $q->whereDate('date', $date);
            if ($bar) {
                $q->where('bar_id', $bar->id);
            } elseif ($selectedBar) {
                $q->where('bar_id', $selectedBar->id);
            }
        })->with('item');
    }

    private function stockItemsRangeQuery(Carbon $startDate, Carbon $endDate, Bar $bar): Builder
    {
        return StockEntryItem::whereHas('stockEntry', function ($q) use ($startDate, $endDate, $bar) {
            $q->whereBetween('date', [$startDate, $endDate])->where('bar_id', $bar->id);
        })->with('item');
    }

    private function marginPercent(float $profit, float $sales): float
    {
        return $sales > 0 ? round(($profit / $sales) * 100, 2) : 0;
    }

    /**
     * Display profit and loss report
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $range = $request->query('range', 'today');
        $barId = $request->query('bar_id', 'all');
        $selectedBar = null;
        $bars = null;
        $barAnalysis = [];

        if (! $user->isSeller()) {
            $bars = Bar::listed()->orderBy('name')->get();
            if ($barId !== 'all') {
                $selectedBar = Bar::find($barId);
                if (! $selectedBar) {
                    $barId = 'all';
                }
            }
        }

        if ($range === 'today') {
            $startDate = Carbon::today();
            $endDate = Carbon::today();
        } elseif ($range === 'yesterday') {
            $startDate = Carbon::yesterday();
            $endDate = Carbon::yesterday();
        } elseif ($range === 'this_week') {
            $startDate = Carbon::now()->startOfWeek();
            $endDate = Carbon::now();
        } elseif ($range === 'this_month') {
            $startDate = Carbon::now()->startOfMonth();
            $endDate = Carbon::now();
        } elseif ($range === 'this_year') {
            $startDate = Carbon::now()->startOfYear();
            $endDate = Carbon::now();
        } elseif ($range === 'all') {
            $earliestDate = Sale::when($selectedBar, fn($query) => $query->where('bar_id', $selectedBar->id))->min('date');
            $startDate = $earliestDate ? Carbon::parse($earliestDate) : Carbon::now()->startOfYear();
            $endDate = Carbon::now();
        } else {
            $startDate = $request->query('start_date') ? Carbon::createFromFormat('Y-m-d', $request->query('start_date')) : Carbon::today();
            $endDate = $request->query('end_date') ? Carbon::createFromFormat('Y-m-d', $request->query('end_date')) : Carbon::today();
        }

        $reportData = [];

        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');

            if ($user->hasRole('seller')) {
                $bar = $user->bar;

                $salesAmount = $this->stockItemsQuery($currentDate, $bar)->sum('sales_amount');
                $purchaseCost = $this->calculatePurchaseCost($this->stockItemsQuery($currentDate, $bar)->get());

                $expenses = Expense::where('user_id', $user->id)
                    ->barOperating()
                    ->whereDate('date', $currentDate)
                    ->sum('amount');

                $overheadExpenses = 0;
                $locationName = $bar->name;
            } else {
                $salesAmount = $this->stockItemsQuery($currentDate, null, $selectedBar)->sum('sales_amount');
                $purchaseCost = $this->calculatePurchaseCost($this->stockItemsQuery($currentDate, null, $selectedBar)->get());

                $expenses = (float) Expense::barOperatingBetween($currentDate, $currentDate, $selectedBar?->id)->sum('amount');
                $overheadExpenses = (float) Expense::overheadBetween($currentDate, $currentDate, $selectedBar?->id)->sum('amount');

                $locationName = $selectedBar ? $selectedBar->name : 'All Locations';
            }

            $grossProfit = $salesAmount - $purchaseCost;
            $netProfit = $grossProfit - $expenses;

            $reportData[$dateStr] = [
                'date' => $currentDate->format('M d, Y'),
                'bar' => $locationName,
                'sales' => $salesAmount,
                'purchase_cost' => $purchaseCost,
                'gross_profit' => $grossProfit,
                'gross_margin' => $this->marginPercent($grossProfit, $salesAmount),
                'expenses' => $expenses,
                'overhead_expenses' => $overheadExpenses,
                'net_profit' => $netProfit,
                'profit_margin' => $this->marginPercent($netProfit, $salesAmount),
            ];

            $currentDate->addDay();
        }

        // Calculate totals early for use in subsequent calculations
        $totals = [
            'sales' => array_sum(array_column($reportData, 'sales')),
            'purchase_cost' => array_sum(array_column($reportData, 'purchase_cost')),
            'gross_profit' => array_sum(array_column($reportData, 'gross_profit')),
            'expenses' => array_sum(array_column($reportData, 'expenses')),
            'overhead_expenses' => array_sum(array_column($reportData, 'overhead_expenses')),
            'net_profit' => array_sum(array_column($reportData, 'net_profit')),
        ];

        // Calculate credit sales early for use in payment methods fallback
        $creditSalesTotal = CustomerTab::whereBetween('date', [$startDate, $endDate])
            ->when($selectedBar, fn($query) => $query->where('bar_id', $selectedBar->id))
            ->sum('amount');

        $creditSalesPaid = CustomerTab::whereBetween('date', [$startDate, $endDate])
            ->when($selectedBar, fn($query) => $query->where('bar_id', $selectedBar->id))
            ->sum('paid_amount');

        $creditSalesOutstanding = CustomerTab::whereBetween('date', [$startDate, $endDate])
            ->when($selectedBar, fn($query) => $query->where('bar_id', $selectedBar->id))
            ->where('status', '!=', 'paid')
            ->sum('balance');

        $paymentMethods = collect(Payment::whereHas('stockEntry', function($query) use ($startDate, $endDate, $selectedBar) {
            $query->whereBetween('date', [$startDate, $endDate]);
            if ($selectedBar) {
                $query->where('bar_id', $selectedBar->id);
            }
        })->selectRaw('type, SUM(amount) as total')->groupBy('type')->get())
            ->pluck('total', 'type')
            ->toArray();

        // Prefer daily report collections when available, since those are the audited cash counts.
        $dailyReportPaymentMethods = DailyReportPayment::whereHas('dailyReport', function($query) use ($startDate, $endDate, $selectedBar) {
            $query->whereBetween('date', [$startDate, $endDate]);
            if ($selectedBar) {
                $query->where('bar_id', $selectedBar->id);
            }
        })->selectRaw('payment_method, SUM(amount) as total')->groupBy('payment_method')->pluck('total', 'payment_method')->toArray();

        $dailyReportCash = DailyReport::whereBetween('date', [$startDate, $endDate])
            ->when($selectedBar, fn($query) => $query->where('bar_id', $selectedBar->id))
            ->sum('cash_in_hand');

        if (!empty($dailyReportPaymentMethods) || $dailyReportCash > 0) {
            $paymentMethods = $dailyReportPaymentMethods;
            $paymentMethods['Cash'] = ($paymentMethods['Cash'] ?? 0) + $dailyReportCash;
        }

        // If no payments exist, try to derive from sales (cash default)
        if (empty($paymentMethods)) {
            $totalSales = $totals['sales'];
            if ($totalSales > 0) {
                $paymentMethods = [
                    'cash' => $totalSales - $creditSalesPaid,
                ];
            }
        }

        // Bar-by-bar analysis
        $barAnalysis = [];
        if (!$selectedBar && !$user->hasRole('seller')) {
            $bars = Bar::listed()->get();
            foreach ($bars as $bar) {
                $barSales = $this->stockItemsRangeQuery($startDate, $endDate, $bar)->sum('sales_amount');
                $barPurchaseCost = $this->calculatePurchaseCost($this->stockItemsRangeQuery($startDate, $endDate, $bar)->get());

                $barExpenses = (float) Expense::barOperatingBetween($startDate, $endDate, $bar->id)->sum('amount');
                $barOverhead = (float) Expense::overheadBetween($startDate, $endDate, $bar->id)->sum('amount');
                
                if ($barSales > 0) {
                    $barGrossProfit = $barSales - $barPurchaseCost;
                    $barNetProfit = $barGrossProfit - $barExpenses;
                    $barAnalysis[] = [
                        'bar_name' => $bar->name,
                        'sales' => $barSales,
                        'purchase_cost' => $barPurchaseCost,
                        'gross_profit' => $barGrossProfit,
                        'expenses' => $barExpenses,
                        'overhead_expenses' => $barOverhead,
                        'profit' => $barNetProfit,
                        'gross_margin' => $this->marginPercent($barGrossProfit, $barSales),
                        'margin' => $this->marginPercent($barNetProfit, $barSales),
                    ];
                }
            }
            usort($barAnalysis, function($a, $b) {
                return $b['profit'] <=> $a['profit'];
            });
        }

        // Credit collection impact
        $creditCollectionLoss = $creditSalesOutstanding ?? 0;
        $creditImpactOnCash = $totals['sales'] > 0 ? round(($creditCollectionLoss / $totals['sales']) * 100, 2) : 0;

        $openingStock = StockEntryItem::whereHas('stockEntry', function($query) use ($startDate, $selectedBar) {
            $query->whereDate('date', $startDate);
            if ($selectedBar) {
                $query->where('bar_id', $selectedBar->id);
            }
        })->sum('opening_stock');

        $closingStock = StockEntryItem::whereHas('stockEntry', function($query) use ($endDate, $selectedBar) {
            $query->whereDate('date', $endDate);
            if ($selectedBar) {
                $query->where('bar_id', $selectedBar->id);
            }
        })->sum('closing_stock');

        $latestEntryDate = Sale::when($selectedBar, fn($query) => $query->where('bar_id', $selectedBar->id))->max('date');
        $currentStockValue = (object) ['purchase_value' => 0, 'selling_value' => 0];
        if ($latestEntryDate) {
            $currentStockValue = StockEntryItem::whereHas('stockEntry', function($query) use ($latestEntryDate, $selectedBar) {
                $query->whereDate('date', $latestEntryDate);
                if ($selectedBar) {
                    $query->where('bar_id', $selectedBar->id);
                }
            })->selectRaw('SUM(closing_stock * purchase_price) as purchase_value, SUM(closing_stock * price) as selling_value')->first();
        }

        $topItems = StockEntryItem::whereHas('stockEntry', function($query) use ($startDate, $endDate, $selectedBar) {
            $query->whereBetween('date', [$startDate, $endDate]);
            if ($selectedBar) {
                $query->where('bar_id', $selectedBar->id);
            }
        })->selectRaw('item_id, SUM(sold_quantity) as total_sold, SUM(sales_amount) as total_revenue')
            ->groupBy('item_id')
            ->with('item')
            ->orderByDesc('total_sold')
            ->take(5)
            ->get();

        $totals['gross_margin'] = $this->marginPercent($totals['gross_profit'], $totals['sales']);
        $totals['profit_margin'] = $this->marginPercent($totals['net_profit'], $totals['sales']);

        // Calculate shortage (missing money)
        $expectedCash = $totals['sales'] - $creditSalesOutstanding;
        $recordedCash = array_sum($paymentMethods);
        $shortage = max(0, $expectedCash - $recordedCash);

        $trendData = collect($reportData)->map(fn($data) => ['label' => $data['date'], 'value' => $data['sales']])->values();
        $cashCoverage = $totals['sales'] > 0 ? round(($totals['sales'] - $creditSalesOutstanding) / $totals['sales'] * 100, 2) : 0;
        $expensesRatio = $totals['sales'] > 0 ? round($totals['expenses'] / $totals['sales'] * 100, 2) : 0;
        $interpretation = 'Sales are too low for an accurate profitability assessment.';
        if ($totals['sales'] > 0) {
            if ($totals['net_profit'] >= 0) {
                $interpretation = "Profitable with a margin of {$totals['profit_margin']}%. " .
                    ($expensesRatio <= 30
                        ? 'Expense control is strong.'
                        : 'Expenses are elevated and should be monitored closely.');
            } else {
                $interpretation = "Loss-making with negative net profit. Expenses are {$expensesRatio}% of sales; review cost lines and pricing.";
            }
        }

        $reportData = array_reverse($reportData, true);

        return view('profit-loss.index', compact('reportData', 'totals', 'startDate', 'endDate', 'range', 'bars', 'selectedBar', 'barId', 'paymentMethods', 'openingStock', 'closingStock', 'currentStockValue', 'topItems', 'creditSalesTotal', 'creditSalesPaid', 'creditSalesOutstanding', 'trendData', 'cashCoverage', 'expensesRatio', 'interpretation', 'barAnalysis', 'creditCollectionLoss', 'creditImpactOnCash', 'shortage'));
    }

    /**
     * Get profit/loss data for API/dashboard widget
     */
    public function getDaily(Request $request)
    {
        $date = $request->query('date') ? Carbon::createFromFormat('Y-m-d', $request->query('date')) : Carbon::now();
        $user = auth()->user();

        if ($user->hasRole('seller')) {
            $bar = $user->bar;
            
            $salesAmount = $this->stockItemsQuery($date, $bar)->sum('sales_amount');
            $purchaseCost = $this->calculatePurchaseCost($this->stockItemsQuery($date, $bar)->get());

            $expenses = Expense::where('user_id', $user->id)
                ->whereDate('date', $date)
                ->sum('amount');
        } else {
            $salesAmount = StockEntryItem::whereHas('stockEntry', function($q) use ($date) {
                $q->whereDate('date', $date);
            })->sum('sales_amount');

            $purchaseCost = $this->calculatePurchaseCost(
                StockEntryItem::whereHas('stockEntry', function($q) use ($date) {
                    $q->whereDate('date', $date);
                })->with('item')->get()
            );

            $expenses = Expense::whereDate('date', $date)->sum('amount');
        }

        $grossProfit = $salesAmount - $purchaseCost;
        $netProfit = $grossProfit - $expenses;

        return [
            'sales' => $salesAmount,
            'purchase_cost' => $purchaseCost,
            'gross_profit' => $grossProfit,
            'gross_margin' => $this->marginPercent($grossProfit, $salesAmount),
            'expenses' => $expenses,
            'net_profit' => $netProfit,
            'profit_margin' => $this->marginPercent($netProfit, $salesAmount),
        ];
    }
}

