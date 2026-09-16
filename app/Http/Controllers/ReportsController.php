<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\StockEntryItem;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Bar;
use App\Models\Item;
use App\Models\BarItemPrice;
use App\Models\CustomerTab;
use App\Models\DamagedGood;
use App\Models\Debt;
use App\Models\DailyReport;
use App\Models\DailyReportPayment;
use App\Models\User;
use App\Models\BottleCount;
use App\Models\ActivityLog;
use App\Support\CsvExport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportsController extends Controller
{
    
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Sellers cannot access reports
        if ($user->isSeller()) {
            abort(403, 'Unauthorized access');
        }

        $filter = $request->get('filter', 'today');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $barId = $request->get('bar_id');

        // Determine date range
        [$startDate, $endDate] = $this->getDateRange($filter, $startDate, $endDate);

        // Get all report data
        $data = [
            'summary' => $this->getSummaryData($startDate, $endDate, $barId),
            'barBreakdown' => $this->getBarBreakdown($startDate, $endDate, $barId),
            'itemInsights' => $this->getItemInsights($startDate, $endDate, $barId),
            'expenseBreakdown' => $this->getExpenseBreakdown($startDate, $endDate, $barId),
            'paymentBreakdown' => $this->getPaymentBreakdown($startDate, $endDate, $barId),
            'debtTracking' => $this->getDebtTracking($startDate, $endDate, $barId),
            'managementOverhead' => (float) Expense::overheadBetween($startDate, $endDate, $barId ? (int) $barId : null)->sum('amount'),
            'filter' => $filter,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'barId' => $barId,
            'bottleCounts' => $this->getBottleCounts($startDate, $endDate, $barId),
        ];

        return view('reports.dashboard', $data);
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        if ($user->isSeller()) {
            abort(403, 'Unauthorized access');
        }

        $filter = $request->get('filter', 'today');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $barId = $request->get('bar_id');

        [$startDate, $endDate] = $this->getDateRange($filter, $startDate, $endDate);

        $summary = $this->getSummaryData($startDate, $endDate, $barId);
        $barBreakdown = $this->getBarBreakdown($startDate, $endDate, $barId);
        $itemInsights = $this->getItemInsights($startDate, $endDate, $barId);
        $expenseBreakdown = $this->getExpenseBreakdown($startDate, $endDate, $barId);
        $managementOverhead = (float) Expense::overheadBetween($startDate, $endDate, $barId ? (int) $barId : null)->sum('amount');

        $rows = [];
        $rows[] = ['Pixies Performance Report'];
        $rows[] = ['Period', $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d')];
        $rows[] = ['Bar Filter', $barId ? (Bar::find($barId)?->name ?? $barId) : 'All Bars'];
        $rows[] = [];
        $rows[] = ['Summary Metric', 'Amount (MWK)'];
        $rows[] = ['Total Sales', $summary['totalSales']];
        $rows[] = ['Total Collected', $summary['totalCollected']];
        $rows[] = ['Cash Collected', $summary['cashPayments']];
        $rows[] = ['Mobile Collected', $summary['mobilePayments']];
        $rows[] = ['Credit Sales (Outstanding)', $summary['creditSales']];
        $rows[] = ['Bar Shift Expenses', $summary['totalExpenses']];
        $rows[] = ['Management Overhead (informational)', $managementOverhead];
        $rows[] = ['Expected Collected', $summary['expectedCollected']];
        $rows[] = ['Variance', $summary['missingMoney']];
        $rows[] = ['Bankable Balance', $summary['bankableBalance']];
        $rows[] = ['Reconciliation Accurate', $summary['isAccurate'] ? 'Yes' : 'No'];
        $rows[] = [];
        $rows[] = ['Bar', 'Sales', 'Shift Expenses', 'Electronic', 'Cash (derived)', 'Net'];
        foreach ($barBreakdown as $barData) {
            $rows[] = [
                $barData['bar']->name,
                $barData['sales'],
                $barData['expenses'],
                $barData['electronic'],
                $barData['cash'],
                $barData['profit'],
            ];
        }
        $rows[] = [];
        $rows[] = ['Top Items', 'Category', 'Units Sold', 'Revenue'];
        foreach ($itemInsights as $item) {
            $rows[] = [$item->name, $item->category, $item->total_sold, $item->total_revenue];
        }
        $rows[] = [];
        $rows[] = ['Expense Type', 'Count', 'Total'];
        foreach ($expenseBreakdown as $expense) {
            $rows[] = [Expense::typeLabel($expense->type), $expense->count, $expense->total_amount];
        }

        $filename = 'performance_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.csv';

        return CsvExport::download($rows, $filename);
    }

    public function reportingExport()
    {
        $user = Auth::user();
        if (!$user->isSeller() && !$user->isManager() && !$user->isDirector()) {
            abort(403, 'Unauthorized access to reporting');
        }

        $query = DailyReport::with(['user', 'bar', 'payments'])->orderBy('date', 'desc');
        if ($user->isSeller()) {
            $query->where('user_id', $user->id);
        }
        $reports = $query->get();

        $rows = [];
        $rows[] = ['Date', 'Bar', 'User', 'Cash In Hand', 'Total Sales', 'Mobile Payments', 'Total Payments', 'Notes'];
        foreach ($reports as $report) {
            $stockEntry = Sale::where('date', $report->date)
                ->where('bar_id', $report->bar_id)
                ->orderByDesc('updated_at')
                ->first();
            $sales = $stockEntry ? $stockEntry->stockEntryItems->sum('sales_amount') : 0;
            $mobile = $report->payments->whereIn('payment_method', ['Airtel Money', 'Mpamba', 'MO', 'MO626'])->sum('amount');

            $rows[] = [
                $report->date->format('Y-m-d'),
                $report->bar->name ?? '',
                $report->user->name ?? '',
                $report->cash_in_hand,
                $sales,
                $mobile,
                $report->total_payments,
                $report->notes,
            ];
        }

        return CsvExport::download($rows, 'shift_reports_' . now()->format('Y-m-d') . '.csv');
    }

    private function getDateRange($filter, $startDate, $endDate)
    {
        $now = Carbon::now();

        switch ($filter) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
            
            case 'week':
                return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()];
            
            case 'month':
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
            
            case 'year':
                return [$now->copy()->startOfYear(), $now->copy()->endOfYear()];
            
            case 'custom':
                return [
                    $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfDay(),
                    $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay()
                ];
            
            default:
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
        }
    }

    private function getSummaryData($startDate, $endDate, $barId = null)
    {
        // SOURCE OF TRUTH: Total Sales ONLY from stock entries
        // This already includes cash, mobile, and credit sales
        $totalSalesQuery = StockEntryItem::whereHas('stockEntry', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        });
        
        if ($barId) {
            $totalSalesQuery->whereHas('stockEntry', function ($query) use ($barId) {
                $query->where('bar_id', $barId);
            });
        }
        
        $totalSales = $totalSalesQuery->sum('sales_amount');

        // COLLECTION: Collected Money from daily_reports and daily_report_payments
        // Cash payments from daily_reports.cash_in_hand
        $cashPaymentsQuery = DailyReport::whereBetween('date', [$startDate, $endDate]);
        if ($barId) {
            $cashPaymentsQuery->where('bar_id', $barId);
        }
        $cashPayments = $cashPaymentsQuery->sum('cash_in_hand');

        // Mobile payments from daily_report_payments table
        $mobilePaymentsQuery = DailyReportPayment::whereHas('dailyReport', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        })->whereIn('payment_method', ['Airtel Money', 'Mpamba', 'MO']);
        
        if ($barId) {
            $mobilePaymentsQuery->whereHas('dailyReport', function ($query) use ($barId) {
                $query->where('bar_id', $barId);
            });
        }
        $mobilePayments = $mobilePaymentsQuery->sum('amount');

        // "Collected" includes cash in hand + all recorded payment methods.
        $totalCollected = $cashPayments + $mobilePayments;

        // CREDIT: Credit Sales ONLY from unpaid tabs
        $creditSalesQuery = CustomerTab::whereBetween('date', [$startDate, $endDate])
            ->where('status', '!=', 'paid');
        if ($barId) {
            $creditSalesQuery->where('bar_id', $barId);
        }
        $creditSales = $creditSalesQuery->sum('balance');

            // EXPENSES: Bar shift expenses only (exclude management overhead).
            $totalExpenses = (float) Expense::barOperatingBetween($startDate, $endDate, $barId ? (int) $barId : null)->sum('amount');

        // Business rule: bankable is visible collected money after expenses.
        $debtCollections = DailyReportPayment::whereHas('dailyReport', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        })->where('payment_method', 'Debt Collection')
            ->when($barId, fn ($q) => $q->whereHas('dailyReport', fn ($dq) => $dq->where('bar_id', $barId)))
            ->sum('amount');

        $reconciliation = $this->shiftCollectionMath(
            (float) $totalSales,
            (float) $creditSales,
            (float) $totalCollected,
            (float) $totalExpenses,
            (float) $debtCollections
        );
        $expectedCollected = $reconciliation['expectedCollected'];
        $missingMoney = $reconciliation['missingMoney'];
        $bankableBalance = $reconciliation['bankableBalance'];

        // Sales should equal collected + credit + operating expenses (minus debt repayments counted in collected).
        $validationCheck = $totalCollected + $creditSales + $totalExpenses - $debtCollections;
        $isAccurate = abs($validationCheck - $totalSales) < 0.01;

        return [
            'totalSales' => $totalSales,
            'cashPayments' => $cashPayments,
            'mobilePayments' => $mobilePayments,
            'totalCollected' => $totalCollected,
            'creditSales' => $creditSales,
            'missingMoney' => $missingMoney,
            'totalExpenses' => $totalExpenses,
            'bankableBalance' => $bankableBalance,
            'isAccurate' => $isAccurate,
            'validationCheck' => $validationCheck,
            'expectedCollected' => $expectedCollected,
        ];
    }

    private function getBarBreakdown($startDate, $endDate, $barId = null)
    {
        $barsQuery = Bar::with(['sales' => function ($query) use ($startDate, $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }]);
        
        if ($barId) {
            $barsQuery->where('id', $barId);
        }
        
        $bars = $barsQuery->get();

        $barData = [];

        foreach ($bars as $bar) {
            $sales = StockEntryItem::whereHas('stockEntry', function ($query) use ($bar, $startDate, $endDate) {
                $query->where('bar_id', $bar->id)->whereBetween('date', [$startDate, $endDate]);
            })->sum('sales_amount');

            $expenses = (float) Expense::barOperatingBetween($startDate, $endDate, $bar->id)->sum('amount');

            $electronic = Payment::whereHas('stockEntry', function ($query) use ($bar, $startDate, $endDate) {
                $query->where('bar_id', $bar->id)->whereBetween('date', [$startDate, $endDate]);
            })->sum('amount');

            $cash = $sales - $electronic;
            $profit = $sales - $expenses;

            $barData[] = [
                'bar' => $bar,
                'sales' => $sales,
                'expenses' => $expenses,
                'electronic' => $electronic,
                'cash' => $cash,
                'profit' => $profit,
                'castel_bottles' => BottleCount::whereBetween('date', [$startDate, $endDate])->where('bar_id', $bar->id)->sum('counted'),
            ];
        }

        return $barData;
    }

    private function getItemInsights($startDate, $endDate, $barId = null)
    {
        $query = StockEntryItem::select([
                'items.name',
                'items.category',
                \DB::raw('SUM(stock_entry_items.sold_quantity) as total_sold'),
                \DB::raw('SUM(stock_entry_items.sales_amount) as total_revenue')
            ])
            ->join('items', 'stock_entry_items.item_id', '=', 'items.id')
            ->join('sales', 'stock_entry_items.stock_entry_id', '=', 'sales.id')
            ->whereBetween('sales.date', [$startDate, $endDate])
            ->where('stock_entry_items.sold_quantity', '>', 0);
            
        if ($barId) {
            $query->where('sales.bar_id', $barId);
        }
        
        return $query->groupBy('items.id', 'items.name', 'items.category')
            ->orderBy('total_revenue', 'desc')
            ->get();
    }

    private function getExpenseBreakdown($startDate, $endDate, $barId = null)
    {
        $query = Expense::barOperatingBetween($startDate, $endDate, $barId ? (int) $barId : null)
            ->select([
                'type',
                \DB::raw('SUM(amount) as total_amount'),
                \DB::raw('COUNT(*) as count'),
            ]);
        
        return $query->groupBy('type')
            ->orderBy('total_amount', 'desc')
            ->get();
    }

    private function getPaymentBreakdown($startDate, $endDate, $barId = null)
    {
        $paymentsQuery = Payment::select([
                'type',
                \DB::raw('SUM(amount) as total_amount')
            ])
            ->whereHas('stockEntry', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            });
            
        if ($barId) {
            $paymentsQuery->whereHas('stockEntry', function ($query) use ($barId) {
                $query->where('bar_id', $barId);
            });
        }
        
        $payments = $paymentsQuery->groupBy('type')
            ->orderBy('total_amount', 'desc')
            ->get();

        // Get total sales for cash calculation
        $totalSalesQuery = StockEntryItem::whereHas('stockEntry', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        });
        
        if ($barId) {
            $totalSalesQuery->whereHas('stockEntry', function ($query) use ($barId) {
                $query->where('bar_id', $barId);
            });
        }
        
        $totalSales = $totalSalesQuery->sum('sales_amount');

        $totalElectronic = $payments->sum('total_amount');
        $cash = $totalSales - $totalElectronic;

        return [
            'payments' => $payments,
            'totalElectronic' => $totalElectronic,
            'cash' => $cash,
        ];
    }

    private function getDebtTracking($startDate, $endDate, $barId = null)
    {
        $totalDebtsQuery = Debt::whereHas('stockEntry', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        });
        
        if ($barId) {
            $totalDebtsQuery->whereHas('stockEntry', function ($query) use ($barId) {
                $query->where('bar_id', $barId);
            });
        }
        
        $totalDebts = $totalDebtsQuery->sum('amount');

        $debtsBySellerQuery = Debt::select([
                'users.name as seller_name',
                \DB::raw('SUM(debts.amount) as total_debt'),
                \DB::raw('COUNT(*) as debt_count')
            ])
            ->join('users', 'debts.seller_id', '=', 'users.id')
            ->join('sales', 'debts.stock_entry_id', '=', 'sales.id')
            ->whereBetween('sales.date', [$startDate, $endDate]);
            
        if ($barId) {
            $debtsBySellerQuery->where('sales.bar_id', $barId);
        }
        
        $debtsBySeller = $debtsBySellerQuery->groupBy('users.id', 'users.name')
            ->orderBy('total_debt', 'desc')
            ->get();

        $recentDebtsQuery = Debt::with(['seller', 'stockEntry.bar'])
            ->whereHas('stockEntry', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            });
            
        if ($barId) {
            $recentDebtsQuery->whereHas('stockEntry', function ($query) use ($barId) {
                $query->where('bar_id', $barId);
            });
        }
        
        $recentDebts = $recentDebtsQuery->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return [
            'totalDebts' => $totalDebts,
            'debtsBySeller' => $debtsBySeller,
            'recentDebts' => $recentDebts,
        ];
    }

    private function getBottleCounts($startDate, $endDate, $barId = null)
    {
        $query = BottleCount::whereBetween('date', [$startDate, $endDate]);
        if ($barId) {
            $query->where('bar_id', $barId);
        }

        return $query->select([
            'bar_id',
            'item_id',
            \DB::raw('SUM(counted) as total_counted')
        ])->groupBy('bar_id','item_id')
          ->get();
    }

    // ================================
    // DAILY REPORTING FUNCTIONALITY
    // ================================

    /**
     * Display a listing of daily reports.
     */
    public function reportingIndex()
    {
        $user = Auth::user();
        
        // Authorization: Only bar seller, manager, director can access
        if (!$user->isSeller() && !$user->isManager() && !$user->isDirector()) {
            abort(403, 'Unauthorized access to reporting');
        }

        $query = DailyReport::with(['user', 'bar', 'payments'])
            ->orderBy('date', 'desc');

        // Sellers can only see their own reports
        if ($user->isSeller()) {
            $query->where('user_id', $user->id);
        }

        $reports = $query->paginate(15);

        // Calculate real-time sales for each report
        foreach ($reports as $report) {
            // Get the most recent stock entry for this report's date and bar
            $stockEntry = Sale::where('date', $report->date)
                ->where('bar_id', $report->bar_id)
                ->orderBy('updated_at', 'desc')
                ->first();
                
            // Calculate real-time sales
            $realTimeSales = $stockEntry ? $stockEntry->stockEntryItems->sum('sales_amount') : 0;
            
            // Add real-time sales to report object
            $report->real_time_sales = $realTimeSales;
        }

        return view('reporting.index', compact('reports'));
    }

    /**
     * Show the form for creating a new daily report.
     */
    public function reportingCreate(Request $request)
    {
        $user = Auth::user();

        // Authorization: Only bar seller, manager, director can access
        if (!$user->isSeller() && !$user->isManager() && !$user->isDirector()) {
            abort(403, 'Unauthorized access to reporting');
        }

        // Sellers must be assigned to a bar
        if ($user->isSeller() && !$user->bar_id) {
            abort(403, 'Sellers must be assigned to a bar to create reports');
        }

        // Sellers sell today and balance the next morning, so "today" would
        // show an empty shift. Let them pick which day they're balancing,
        // defaulting to yesterday (the most recently completed shift).
        $today = now()->format('Y-m-d');
        $date = $request->query('date');
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
            $date = now()->subDay()->format('Y-m-d');
        }
        if ($date > $today) {
            $date = $today;
        }

        // Check if a report for the chosen date already exists
        $existingReport = null;
        if ($user->bar_id) {
            $existingReport = DailyReport::where('user_id', $user->id)
                ->where('bar_id', $user->bar_id)
                ->where('date', $date)
                ->first();
        }

        // Get the chosen day's stock entry to calculate total sales
        $stockEntry = null;
        $totalSales = 0;

        if ($user->bar_id) {
            // Try to get the most recent stock entry for that day
            $stockEntry = Sale::where('date', $date)
                ->where('bar_id', $user->bar_id)
                ->orderBy('updated_at', 'desc') // Get the most recently updated entry
                ->first();

            if ($stockEntry) {
                $totalSales = $stockEntry->stockEntryItems->sum('sales_amount');
            }

            // Fallback: If no stock entry found, try to get any stock entry for that day
            if ($totalSales == 0) {
                $allStockEntries = Sale::where('date', $date)
                    ->where('bar_id', $user->bar_id)
                    ->get();

                foreach ($allStockEntries as $entry) {
                    $totalSales += $entry->stockEntryItems->sum('sales_amount');
                }
            }
        }

        // Get expenses for that day to include in calculations
        $expenses = Expense::where('date', $date)
                           ->whereHas('stockEntry', function ($query) use ($user) {
                               if ($user->isSeller()) {
                                   $query->where('bar_id', $user->bar_id)
                                         ->where('user_id', $user->id);
                               } else {
                                   $query->where('bar_id', $user->bar_id);
                               }
                           })
                           ->get();

        // Fallback queries if no expenses found
        if ($expenses->isEmpty()) {
            $expenses = Expense::where('date', $date)
                               ->where('user_id', $user->id)
                               ->get();
        }

        if ($expenses->isEmpty()) {
            $expenses = Expense::where('date', $date)->get();
        }

        // Calculate financial values
        $totalExpenses = $expenses->sum('amount');
        $creditSales = CustomerTab::where('date', $date)
            ->where('bar_id', $user->bar_id)
            ->where('status', '!=', 'paid')
            ->sum('balance');

        $paymentMethods = DailyReportPayment::getPaymentMethods();
        $expenditureTypes = Expense::balanceExpenditureTypes();
        $shiftExpenditures = $this->filterBalanceExpenditures(
            $this->getShiftExpenditures($user, $date, $stockEntry)
        );
        $shiftDebtInForm = collect($shiftExpenditures)->where('type', 'debt')->sum('amount');
        $baseCreditSales = max(0, $creditSales - $shiftDebtInForm);
        $balancePayments = $existingReport ? $this->balancePaymentsForForm($existingReport) : [];
        $damageItems = $this->getDamageItemsForBar($user->bar_id);

        return view('reporting.create', compact(
            'date',
            'existingReport',
            'stockEntry',
            'totalSales',
            'totalExpenses',
            'creditSales',
            'baseCreditSales',
            'expenses',
            'paymentMethods',
            'expenditureTypes',
            'shiftExpenditures',
            'balancePayments',
            'damageItems'
        ));
    }

    /**
     * Store a newly created daily report in storage.
     */
    public function reportingStore(Request $request)
    {
        $user = Auth::user();
        
        // Authorization: Only bar seller, manager, director can access
        if (!$user->isSeller() && !$user->isManager() && !$user->isDirector()) {
            abort(403, 'Unauthorized access to reporting');
        }

        $request->merge([
            'payments' => $this->normalizeBalancePayments($request->input('payments', [])),
        ]);

        $validated = $request->validate([
            'report_date' => 'required|date|before_or_equal:today',
            'payments' => 'required|array|min:1',
            'payments.*.payment_method' => 'required|in:' . DailyReportPayment::paymentMethodKeys(),
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.description' => 'nullable|string|max:500',
            'expenditures' => 'nullable|array',
            'expenditures.*.type' => 'nullable|in:' . implode(',', array_keys(Expense::balanceExpenditureTypes())),
            'expenditures.*.amount' => 'nullable|numeric|min:0',
            'expenditures.*.notes' => 'nullable|string|max:500',
            'expenditures.*.photo' => 'nullable|image|max:5120',
            'expenditures.*.item_id' => 'nullable|integer|exists:items,id',
            'expenditures.*.quantity' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $date = $validated['report_date'];
            $stockEntry = Sale::where('date', $date)
                ->where('bar_id', $user->bar_id)
                ->orderBy('updated_at', 'desc')
                ->first();

            ['cashInHand' => $cashInHand, 'totalPayments' => $totalPayments] = $this->resolveCashFromPayments($validated['payments']);

            // Create or update daily report
            $dailyReport = DailyReport::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'bar_id' => $user->bar_id,
                    'date' => $date,
                ],
                [
                    'cash_in_hand' => $cashInHand,
                    'total_sales' => $request->input('total_sales', 0),
                    'total_payments' => $totalPayments,
                    'notes' => $validated['notes'] ?? null,
                ]
            );

            // Delete existing payments if updating
            $dailyReport->payments()->delete();

            // Create new payments
            foreach ($validated['payments'] as $payment) {
                DailyReportPayment::create([
                    'daily_report_id' => $dailyReport->id,
                    'payment_method' => $payment['payment_method'],
                    'amount' => $payment['amount'],
                    'description' => $payment['description'] ?? null,
                ]);
            }

            $this->syncShiftExpenditures($request, $user, $stockEntry, $date);

            ActivityLog::log([
                'user_id' => $user->id,
                'action' => $dailyReport->wasRecentlyCreated ? 'daily_report_submitted' : 'daily_report_resubmitted',
                'description' => "{$user->name} balanced {$date} at ".($user->bar->name ?? 'their bar').": cash MWK ".number_format($cashInHand, 0).", total collected MWK ".number_format($totalPayments, 0),
                'subject_type' => DailyReport::class,
                'subject_id' => $dailyReport->id,
                'new_values' => [
                    'date' => $date,
                    'bar' => $user->bar->name ?? null,
                    'cash_in_hand' => $cashInHand,
                    'total_payments' => $totalPayments,
                ],
            ]);

            DB::commit();

            return redirect()
                ->route('reporting.index')
                ->with('success', 'Daily report saved successfully!');

        } catch (\Exception $e) {
            DB::rollback();
            
            return back()
                ->withInput()
                ->with('error', 'Error saving daily report: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified daily report.
     */
    public function reportingShow(DailyReport $dailyReport)
    {
        $user = Auth::user();
        
        // Authorization: Only bar seller, manager, director can access
        if (!$user->isSeller() && !$user->isManager() && !$user->isDirector()) {
            abort(403, 'Unauthorized access to reporting');
        }

        // Sellers can only view their own reports
        if ($user->isSeller() && $dailyReport->user_id !== $user->id) {
            abort(403, 'Unauthorized access to this report');
        }

        $dailyReport->load(['user', 'bar', 'payments']);
        $paymentsByMethod = $dailyReport->getPaymentsByMethod();

        // Get expenses for this day
        $expenses = Expense::where('date', $dailyReport->date)
                           ->whereHas('stockEntry', function ($query) use ($dailyReport) {
                               $query->where('bar_id', $dailyReport->bar_id);
                           })
                           ->get();

        // Also try fallback query if no expenses found through stockEntry relationship
        if ($expenses->isEmpty()) {
            $expenses = Expense::where('date', $dailyReport->date)
                               ->where('user_id', $dailyReport->user_id)
                               ->get();
        }

        // Final fallback - get all expenses for this date (for debugging)
        if ($expenses->isEmpty()) {
            $expenses = Expense::where('date', $dailyReport->date)->get();
        }

        // ================================
        // FINANCIAL CALCULATIONS
        // ================================
        
        // SOURCE OF TRUTH: Total Sales ONLY from stock entries
        // This already includes cash, mobile, and credit sales
        $stockEntry = Sale::where('date', $dailyReport->date)
            ->where('bar_id', $dailyReport->bar_id)
            ->orderBy('updated_at', 'desc')
            ->first();
            
        $totalSales = $stockEntry ? $stockEntry->stockEntryItems->sum('sales_amount') : 0;
        
        // COLLECTION: Collected money from pay-through rows (Cash lives in payments now)
        $cashPayments = (float) $dailyReport->payments()->where('payment_method', 'Cash')->sum('amount');
        if ($cashPayments <= 0) {
            $cashPayments = (float) $dailyReport->cash_in_hand;
        }
        $mobilePayments = $dailyReport->payments()->whereIn('payment_method', ['Airtel Money', 'Mpamba', 'MO626', 'MO', 'POS'])->sum('amount');
        $debtCollections = $dailyReport->payments()->where('payment_method', 'Debt Collection')->sum('amount');
        
        $totalCollected = $dailyReport->payments()->where('payment_method', 'Cash')->exists()
            ? (float) $dailyReport->payments()->sum('amount')
            : $cashPayments + $mobilePayments + $debtCollections;
        
        $totalPayments = $totalCollected; 
        
        // CREDIT: New Credit Sales from today ONLY
        $creditSales = CustomerTab::where('date', $dailyReport->date)
            ->where('bar_id', $dailyReport->bar_id)
            ->where('status', '!=', 'paid')
            ->sum('balance');
        
        // EXPENSES (operational only â€” debt is tracked as credit sales)
        $totalExpenses = $expenses->sum('amount');

        // Shift expenditure totals + details shown in the Payment Breakdown table
        $lunchTotal = (float) $expenses->where('type', 'lunch')->sum('amount');
        $lunchBreakdown = $expenses->where('type', 'lunch')
            ->pluck('description')
            ->filter()
            ->implode(', ');

        $transportTotal = (float) $expenses->where('type', 'taxi')->sum('amount');
        $transportBreakdown = $expenses->where('type', 'taxi')
            ->pluck('description')
            ->filter()
            ->implode(', ');

        // Damages are recorded as a DamagedGood + tab, not an Expense (see
        // syncShiftExpenditures), so they're never part of $totalExpenses.
        $damageEntries = DamagedGood::where('bar_id', $dailyReport->bar_id)
            ->where('date', $dailyReport->date)
            ->where('from_balance', true)
            ->get();
        $damagesTotal = (float) $damageEntries->sum('amount');
        $damagesBreakdown = $damageEntries
            ->filter(fn ($e) => $e->item_id && (float) $e->quantity > 0)
            ->map(function ($e) {
                $name = $e->item?->name ?? 'Item';
                $qty = rtrim(rtrim(number_format((float) $e->quantity, 2), '0'), '.');
                return "{$name} x{$qty}";
            })
            ->implode(', ');

        $ngongoleEntries = CustomerTab::where('bar_id', $dailyReport->bar_id)
            ->where('date', $dailyReport->date)
            ->where('description', 'like', Expense::SHIFT_DEBT_PREFIX . '%')
            ->get();
        $ngongoleTotal = (float) $ngongoleEntries->sum('amount');
        $ngongoleBreakdown = $ngongoleEntries
            ->map(fn ($tab) => $tab->customer_name . ' (' . number_format((float) $tab->amount, 0) . ')')
            ->implode(', ');

        // Grand Total reconciles against Total Sales: money actually collected,
        // plus what was sold on credit (Ngongole) but not yet paid in cash.
        // Lunch, Transport, and Damages are shown for visibility only, never
        // added in here - Lunch/Transport are cash taken OUT of what was
        // already collected (not extra sales), and Damages never generated a
        // sale at all, so folding any of them in here would overstate the day.
        $grandTotal = $totalCollected + $ngongoleTotal;
        $grandTotalVariance = $grandTotal - $totalSales;

        $reconciliation = $this->shiftCollectionMath(
            (float) $totalSales,
            (float) $creditSales,
            (float) $totalCollected,
            (float) $totalExpenses,
            (float) $debtCollections
        );
        $bankableBalance = $reconciliation['bankableBalance'];
        $expectedCollected = $reconciliation['expectedCollected'];
        $missingMoney = $reconciliation['missingMoney'];

        $validationCheck = $totalCollected + $creditSales + $totalExpenses - $debtCollections;
        $isAccurate = abs($validationCheck - $totalSales) < 0.01;
        
        // Calculate payments by method for the table
        $allRegisteredPayments = $totalCollected;
        $paymentsByMethod = $dailyReport->payments()
            ->get()
            ->groupBy('payment_method')
            ->reject(fn ($group, $method) => $method === 'Cash')
            ->mapWithKeys(function ($group, $method) use ($allRegisteredPayments) {
                $amount = (float) $group->sum('amount');
                $breakdown = $group->pluck('description')->filter()->implode(' | ');

                return [
                    $method => [
                        'amount' => $amount,
                        'percentage' => $allRegisteredPayments > 0 ? ($amount / $allRegisteredPayments) * 100 : 0,
                        'breakdown' => $breakdown,
                    ],
                ];
            });

        // Keep legacy variables for compatibility with existing views
        $cashInHand = $dailyReport->cash_in_hand;
        $otherPayments = $mobilePayments; // Mobile payments as "other payments"
        $collectedMoney = $totalCollected;
        $netProfit = $totalSales - $totalExpenses;
        
        // Determine if there's missing money
        $hasSurplusMoney = $missingMoney > 0;
        $hasMissingMoney = $missingMoney < 0;

        return view('reporting.show', compact(
            'dailyReport', 
            'paymentsByMethod',
            'expenses', 
            'totalExpenses',
            'totalSales',
            'cashPayments',
            'mobilePayments',
            'totalCollected',
            'creditSales',
            'missingMoney',
            'bankableBalance',
            'isAccurate',
            'validationCheck',
            'netProfit',
            'cashInHand',
            'otherPayments',
            'collectedMoney',
            'hasMissingMoney',
            'hasSurplusMoney',
            'expectedCollected',
            'totalPayments',
            'lunchTotal',
            'lunchBreakdown',
            'transportTotal',
            'transportBreakdown',
            'damagesTotal',
            'damagesBreakdown',
            'ngongoleTotal',
            'ngongoleBreakdown',
            'grandTotal',
            'grandTotalVariance'
        ));
    }

    /**
     * Show the form for editing the specified daily report.
     */
    public function reportingEdit(DailyReport $dailyReport)
    {
        $user = Auth::user();
        
        // Authorization: Only bar seller, manager, director can access
        if (!$user->isSeller() && !$user->isManager() && !$user->isDirector()) {
            abort(403, 'Unauthorized access to reporting');
        }

        // Sellers can only edit their own reports and only today's report
        if ($user->isSeller() && !$dailyReport->isEditableBy($user)) {
            abort(403, 'You can only edit your own report within 48 hours of submitting it');
        }

        $dailyReport->load('payments');
        
        // Get expenses for this report date
        $expenses = Expense::where('date', $dailyReport->date)
                           ->whereHas('stockEntry', function ($query) use ($dailyReport) {
                               $query->where('bar_id', $dailyReport->bar_id);
                           })
                           ->get();

        // Fallback queries if no expenses found
        if ($expenses->isEmpty()) {
            $expenses = Expense::where('date', $dailyReport->date)
                               ->where('user_id', $dailyReport->user_id)
                               ->get();
        }

        // SOURCE OF TRUTH: Total Sales ONLY from stock entries
        $stockEntry = Sale::where('date', $dailyReport->date)
            ->where('bar_id', $dailyReport->bar_id)
            ->orderBy('updated_at', 'desc')
            ->first();
            
        $totalSales = $stockEntry ? $stockEntry->stockEntryItems->sum('sales_amount') : 0;
        
        // COLLECTION: Collected Money ONLY from payments table
        // Try multiple variations for cash payments
        $cashPayments = $dailyReport->cash_in_hand;
        $mobilePayments = $dailyReport->payments()->whereIn('payment_method', ['Airtel Money', 'Mpamba', 'MO626', 'MO', 'POS'])->sum('amount');
        $totalCollected = $cashPayments + $mobilePayments;
        
        // CREDIT: Credit Sales ONLY from unpaid tabs
        $creditSales = CustomerTab::where('date', $dailyReport->date)
            ->where('bar_id', $dailyReport->bar_id)
            ->where('status', '!=', 'paid')
            ->sum('balance');
        
        // EXPENSES
        $totalExpenses = $expenses->sum('amount');

        $debtCollections = $dailyReport->payments()->where('payment_method', 'Debt Collection')->sum('amount');
        $reconciliation = $this->shiftCollectionMath(
            (float) $totalSales,
            (float) $creditSales,
            (float) $totalCollected,
            (float) $totalExpenses,
            (float) $debtCollections
        );
        $bankableBalance = $reconciliation['bankableBalance'];
        $expectedCollected = $reconciliation['expectedCollected'];
        $missingMoney = $reconciliation['missingMoney'];
        
        $paymentMethods = DailyReportPayment::getPaymentMethods();
        $expenditureTypes = Expense::balanceExpenditureTypes();
        $stockEntry = Sale::where('date', $dailyReport->date)
            ->where('bar_id', $dailyReport->bar_id)
            ->orderBy('updated_at', 'desc')
            ->first();
        $shiftExpenditures = $this->filterBalanceExpenditures(
            $this->getShiftExpenditures($user, $dailyReport->date->format('Y-m-d'), $stockEntry)
        );
        $shiftDebtInForm = collect($shiftExpenditures)->where('type', 'debt')->sum('amount');
        $baseCreditSales = max(0, $creditSales - $shiftDebtInForm);
        $balancePayments = $this->balancePaymentsForForm($dailyReport);
        $damageItems = $this->getDamageItemsForBar($dailyReport->bar_id);

        return view('reporting.edit', compact(
            'dailyReport',
            'paymentMethods',
            'expenses',
            'totalExpenses',
            'totalSales',
            'cashPayments',
            'mobilePayments',
            'totalCollected',
            'creditSales',
            'baseCreditSales',
            'missingMoney',
            'bankableBalance',
            'expenditureTypes',
            'shiftExpenditures',
            'balancePayments',
            'damageItems'
        ));
    }

    /**
     * Update the specified daily report in storage.
     */
    public function reportingUpdate(Request $request, DailyReport $dailyReport)
    {
        $user = Auth::user();
        
        // Authorization: Only bar seller, manager, director can access
        if (!$user->isSeller() && !$user->isManager() && !$user->isDirector()) {
            abort(403, 'Unauthorized access to reporting');
        }

        // Sellers can only update their own reports and only today's report
        if ($user->isSeller() && !$dailyReport->isEditableBy($user)) {
            abort(403, 'You can only update your own report within 48 hours of submitting it');
        }

        $request->merge([
            'payments' => $this->normalizeBalancePayments($request->input('payments', [])),
        ]);

        $validated = $request->validate([
            'payments' => 'required|array|min:1',
            'payments.*.payment_method' => 'required|in:' . DailyReportPayment::paymentMethodKeys(),
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.description' => 'nullable|string|max:500',
            'expenditures' => 'nullable|array',
            'expenditures.*.type' => 'nullable|in:' . implode(',', array_keys(Expense::balanceExpenditureTypes())),
            'expenditures.*.amount' => 'nullable|numeric|min:0',
            'expenditures.*.notes' => 'nullable|string|max:500',
            'expenditures.*.photo' => 'nullable|image|max:5120',
            'expenditures.*.item_id' => 'nullable|integer|exists:items,id',
            'expenditures.*.quantity' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $stockEntry = Sale::where('date', $dailyReport->date)
                ->where('bar_id', $dailyReport->bar_id)
                ->orderBy('updated_at', 'desc')
                ->first();

            ['cashInHand' => $cashInHand, 'totalPayments' => $totalPayments] = $this->resolveCashFromPayments($validated['payments']);

            $oldCashInHand = $dailyReport->cash_in_hand;
            $oldTotalPayments = $dailyReport->total_payments;

            $dailyReport->update([
                'cash_in_hand' => $cashInHand,
                'total_payments' => $totalPayments,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Delete existing payments
            $dailyReport->payments()->delete();

            // Create new payments
            foreach ($validated['payments'] as $payment) {
                DailyReportPayment::create([
                    'daily_report_id' => $dailyReport->id,
                    'payment_method' => $payment['payment_method'],
                    'amount' => $payment['amount'],
                    'description' => $payment['description'] ?? null,
                ]);
            }

            $this->syncShiftExpenditures($request, $user, $stockEntry, $dailyReport->date->format('Y-m-d'));

            ActivityLog::log([
                'user_id' => $user->id,
                'action' => 'daily_report_updated',
                'description' => "{$user->name} updated the balance for {$dailyReport->date->format('Y-m-d')} at ".($dailyReport->bar->name ?? 'their bar').": cash MWK ".number_format($cashInHand, 0).", total collected MWK ".number_format($totalPayments, 0),
                'subject_type' => DailyReport::class,
                'subject_id' => $dailyReport->id,
                'old_values' => [
                    'cash_in_hand' => $oldCashInHand,
                    'total_payments' => $oldTotalPayments,
                ],
                'new_values' => [
                    'cash_in_hand' => $cashInHand,
                    'total_payments' => $totalPayments,
                ],
            ]);

            DB::commit();

            return redirect()
                ->route('reporting.show', $dailyReport)
                ->with('success', 'Daily report updated successfully!');

        } catch (\Exception $e) {
            DB::rollback();
            
            return back()
                ->withInput()
                ->with('error', 'Error updating daily report: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified daily report from storage.
     */
    public function reportingDestroy(DailyReport $dailyReport)
    {
        $user = Auth::user();
        
        // Authorization: Only bar seller, manager, director can access
        if (!$user->isSeller() && !$user->isManager() && !$user->isDirector()) {
            abort(403, 'Unauthorized access to reporting');
        }

        // Sellers can only delete their own reports and only today's report
        if ($user->isSeller() && !$dailyReport->isEditableBy($user)) {
            abort(403, 'You can only delete your own report within 48 hours of submitting it');
        }

        try {
            DB::beginTransaction();

            $reportDate = $dailyReport->date->format('Y-m-d');
            $barName = $dailyReport->bar->name ?? 'their bar';

            $dailyReport->payments()->delete();
            $dailyReport->delete();

            ActivityLog::log([
                'user_id' => $user->id,
                'action' => 'daily_report_deleted',
                'description' => "{$user->name} deleted the balance for {$reportDate} at {$barName}",
                'subject_type' => DailyReport::class,
                'subject_id' => $dailyReport->id,
                'old_values' => ['date' => $reportDate, 'bar' => $barName],
            ]);

            DB::commit();

            return redirect()
                ->route('reporting.index')
                ->with('success', 'Daily report deleted successfully!');

        } catch (\Exception $e) {
            DB::rollback();
            
            return back()
                ->with('error', 'Error deleting daily report: ' . $e->getMessage());
        }
    }

    private function getShiftExpenditures(User $user, string $date, ?Sale $stockEntry): array
    {
        $items = [];

        // Damages are recorded as a DamagedGood + CustomerTab (see
        // syncShiftExpenditures), not as an Expense - excluded here so a
        // damages row never appears twice in the prefilled form.
        $expenseQuery = Expense::where('date', $date)->where('type', '!=', 'damages');
        if ($user->isSeller()) {
            $expenseQuery->where('user_id', $user->id);
        } elseif ($stockEntry) {
            $expenseQuery->where(function ($query) use ($stockEntry, $user) {
                $query->where('stock_entry_id', $stockEntry->id)
                    ->orWhere('user_id', $user->id);
            });
        }

        foreach ($expenseQuery->get() as $expense) {
            $items[] = [
                'type' => $expense->type,
                'amount' => $expense->amount,
                'notes' => $expense->description ?? '',
            ];
        }

        $debtQuery = CustomerTab::whereDate('date', $date)
            ->where('description', 'like', Expense::SHIFT_DEBT_PREFIX . '%');

        if ($user->bar_id) {
            $debtQuery->where('bar_id', $user->bar_id);
        }
        if ($user->isSeller()) {
            $debtQuery->where('created_by', $user->id);
        }

        foreach ($debtQuery->get() as $tab) {
            $items[] = [
                'type' => 'debt',
                'amount' => $tab->amount,
                'notes' => $tab->customer_name,
            ];
        }

        $damageQuery = DamagedGood::whereDate('date', $date)->where('from_balance', true);
        if ($user->bar_id) {
            $damageQuery->where('bar_id', $user->bar_id);
        }
        if ($user->isSeller()) {
            $damageQuery->where('user_id', $user->id);
        }

        foreach ($damageQuery->get() as $damage) {
            $items[] = [
                'type' => 'damages',
                'amount' => $damage->amount,
                'notes' => $damage->description ?? '',
                'item_id' => $damage->item_id,
                'quantity' => $damage->quantity,
            ];
        }

        return $items;
    }

    /**
     * Items a seller can pick as "what got damaged", with the bar-specific
     * selling price used to auto-suggest a loss amount on the Balance form.
     */
    private function getDamageItemsForBar(?int $barId): array
    {
        $barPrices = $barId
            ? BarItemPrice::where('bar_id', $barId)->pluck('price', 'item_id')
            : collect();

        return Item::where('is_hidden', false)
            ->orderBy('id')
            ->get(['id', 'name', 'price'])
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'price' => (float) ($barPrices[$item->id] ?? $item->price ?? 0),
            ])
            ->values()
            ->all();
    }

    private function normalizeBalancePayments(array $payments): array
    {
        return collect($payments)->map(function ($payment) {
            $method = (string) ($payment['payment_method'] ?? '');
            $parsed = DailyReportPayment::parseAmountInput($method, (string) ($payment['amount'] ?? ''));

            return [
                'payment_method' => $method,
                'amount' => $parsed['amount'],
                'description' => $parsed['description'],
            ];
        })->all();
    }

    private function filterBalanceExpenditures(array $items): array
    {
        $allowed = array_keys(Expense::balanceExpenditureTypes());

        return collect($items)
            ->filter(fn ($row) => in_array($row['type'] ?? '', $allowed, true))
            ->values()
            ->all();
    }

    private function balancePaymentsForForm(DailyReport $report): array
    {
        $report->loadMissing('payments');

        $items = $report->payments->map(fn ($payment) => [
            'payment_method' => $payment->payment_method,
            'amount' => $payment->amount,
            'amount_display' => DailyReportPayment::amountDisplayForForm(
                $payment->payment_method,
                $payment->amount,
                $payment->description
            ),
            'description' => $payment->description,
        ])->values()->all();

        $hasCash = collect($items)->contains(fn ($row) => ($row['payment_method'] ?? '') === 'Cash');
        if (!$hasCash && (float) $report->cash_in_hand > 0) {
            array_unshift($items, [
                'payment_method' => 'Cash',
                'amount' => $report->cash_in_hand,
                'amount_display' => (string) $report->cash_in_hand,
                'description' => null,
            ]);
        }

        return $items;
    }

    private function resolveCashFromPayments(array $payments): array
    {
        $collection = collect($payments);

        return [
            'cashInHand' => (float) $collection->where('payment_method', 'Cash')->sum('amount'),
            'totalPayments' => (float) $collection->sum('amount'),
        ];
    }

    private function syncShiftExpenditures(Request $request, User $user, ?Sale $stockEntry, ?string $date = null): void
    {
        $date = $date ?? now()->format('Y-m-d');
        $barId = $user->bar_id;
        $expenditures = $request->input('expenditures', []);

        Expense::where('user_id', $user->id)->where('date', $date)->delete();

        $balanceDamageQuery = DamagedGood::where('user_id', $user->id)
            ->whereDate('date', $date)
            ->where('from_balance', true);
        if ($barId) {
            $balanceDamageQuery->where('bar_id', $barId);
        }
        $balanceDamageQuery->get()->each(function (DamagedGood $item) use ($barId) {
            // This save is being re-submitted (edit/resave): before wiping the
            // previously recorded damage, give its stock deduction back so it
            // isn't double-deducted once the (possibly changed) new entries
            // below are applied.
            if ($barId && $item->item_id && (float) $item->quantity > 0) {
                $this->adjustStockForDamage($barId, (int) $item->item_id, (float) $item->quantity, $item->date->format('Y-m-d'), $item->user_id, reverse: true);
            }

            DamagedGoodController::deleteDamagePhoto($item->photo_path);
            $item->delete();
        });

        if ($barId) {
            // Only Ngongole (debt) tabs get re-synced here - damages have
            // their own dedicated page (DamagedGood, handled above) and are
            // never mirrored into Credit Tabs, so there's nothing of that
            // kind to clear before re-creating below.
            CustomerTab::where('bar_id', $barId)
                ->whereDate('date', $date)
                ->where('created_by', $user->id)
                ->where('description', 'like', Expense::SHIFT_DEBT_PREFIX . '%')
                ->delete();
        }

        foreach ($expenditures as $index => $row) {
            $amount = (float) ($row['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $type = $row['type'] ?? '';
            $notes = trim($row['notes'] ?? '');
            $damageItemId = ($type === 'damages' && !empty($row['item_id'])) ? (int) $row['item_id'] : null;
            $damageQuantity = $damageItemId ? (float) ($row['quantity'] ?? 0) : 0.0;

            if ($type === 'debt' && $barId) {
                $customerName = $notes ?: 'Credit Customer';
                CustomerTab::create([
                    'customer_name' => $customerName,
                    'phone' => null,
                    'bar_id' => $barId,
                    'date' => $date,
                    'amount' => $amount,
                    'paid_amount' => 0,
                    'status' => 'open',
                    'description' => Expense::SHIFT_DEBT_PREFIX . ' ' . $notes,
                    'created_by' => $user->id,
                ]);
            } elseif ($type === 'damages' && $barId) {
                // Damages are NOT an expense (no cash was paid out) and NOT a
                // credit tab (nobody owes this money) - they're their own
                // thing, tracked only via DamagedGood, with its own
                // dedicated page (item, photo, quantity evidence). Kept out
                // of Expenses and Credit Tabs entirely, same as this page's
                // own "Total Expenses"/"Credit Sales" figures never count it.
                DamagedGood::create([
                    'date' => $date,
                    'description' => $notes ?: 'Damaged goods',
                    'amount' => $amount,
                    'photo_path' => DamagedGoodController::storeDamagePhoto(
                        $request->file("expenditures.{$index}.photo")
                    ),
                    'from_balance' => true,
                    'bar_id' => $barId,
                    'item_id' => $damageItemId,
                    'quantity' => $damageItemId ? $damageQuantity : null,
                    'user_id' => $user->id,
                ]);

                // Damages are treated like a sale for stock purposes: the
                // broken unit(s) come off the shelf immediately, the same
                // way a sold unit would, but without counting as revenue
                // (the loss is tracked separately via Damaged Goods above).
                if ($damageItemId && $damageQuantity > 0) {
                    $this->adjustStockForDamage($barId, $damageItemId, $damageQuantity, $date, $user->id);
                }
            } elseif (array_key_exists($type, Expense::operationalTypes())) {
                Expense::create([
                    'stock_entry_id' => $stockEntry?->id,
                    'type' => $type,
                    'amount' => $amount,
                    'description' => $notes ?: null,
                    'date' => $date,
                    'user_id' => $user->id,
                ]);
            }
        }
    }

    /**
     * Treat a damaged quantity like a sale for stock purposes: it comes off
     * the shelf (closing_stock drops, sold_quantity rises) the same way a
     * sold unit would, but WITHOUT adding to sales_amount, since it wasn't
     * actually sold for money (that loss is tracked separately as a tab).
     *
     * Pass reverse: true to give a previously-deducted quantity back, used
     * when a Balance save is re-submitted and the old damage rows (already
     * deducted once) are about to be replaced by the newly submitted set.
     */
    private function adjustStockForDamage(int $barId, int $itemId, float $quantity, string $date, int $userId, bool $reverse = false): void
    {
        if ($quantity <= 0) {
            return;
        }

        $delta = $reverse ? -$quantity : $quantity;

        // Find this item's own most recent stock entry row for this bar,
        // regardless of which day's sheet it lives in - mirrors the director
        // Stock Overview's "latest per item" lookup (see updateStock()).
        $stockEntryItem = StockEntryItem::join('sales', 'stock_entry_items.stock_entry_id', '=', 'sales.id')
            ->where('sales.bar_id', $barId)
            ->where('stock_entry_items.item_id', $itemId)
            ->orderBy('sales.date', 'desc')
            ->orderBy('stock_entry_items.updated_at', 'desc')
            ->orderBy('stock_entry_items.id', 'desc')
            ->select('stock_entry_items.*')
            ->first();

        if (!$stockEntryItem) {
            if ($reverse) {
                // Nothing to restore - a deduction always creates a row first.
                return;
            }

            $todaySale = Sale::firstOrCreate(
                ['bar_id' => $barId, 'date' => $date],
                ['user_id' => $userId]
            );

            StockEntryItem::create([
                'stock_entry_id' => $todaySale->id,
                'item_id' => $itemId,
                'opening_stock' => 0,
                'ordered_stock' => 0,
                'sold_quantity' => $quantity,
                'price' => Item::find($itemId)?->price ?? 0,
                'purchase_price' => 0,
            ]);

            return;
        }

        // Write sold_quantity/closing_stock directly, bypassing the model's
        // mutators - they recompute sales_amount from sold_quantity, which
        // would wrongly book the damaged quantity as revenue. total_stock
        // (opening + ordered) is left untouched.
        $totalStock = (float) $stockEntryItem->total_stock;
        $newSold = max(0, (float) $stockEntryItem->sold_quantity + $delta);
        $newClosing = max(0, $totalStock - $newSold);

        $stockEntryItem->setRawAttributes(array_merge(
            $stockEntryItem->getAttributes(),
            [
                'sold_quantity' => $newSold,
                'closing_stock' => $newClosing,
            ]
        ), false);
        $stockEntryItem->save();
    }

    /**
     * Shift till reconciliation: operational spend reduces what should remain in the drawer.
     */
    private function shiftCollectionMath(
        float $totalSales,
        float $creditSales,
        float $totalCollected,
        float $operationalExpenses,
        float $debtCollections = 0
    ): array {
        // Expected collected is the money that should have been received from
        // customers (sales minus credit), plus any debt repayments recorded as
        // collections. Expenses are NOT subtracted here because $totalCollected is
        // the raw pre-expense receipts; they are only removed in the bankable figure.
        $expectedCollected = ($totalSales - $creditSales) + $debtCollections;
        $missingMoney = $totalCollected - $expectedCollected;

        return [
            'expectedCollected' => $expectedCollected,
            'missingMoney' => $missingMoney,
            'expectedBankable' => $totalSales - $creditSales - $operationalExpenses,
            'bankableBalance' => $totalCollected - $operationalExpenses,
        ];
    }
}

