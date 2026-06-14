<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DailyStockEntry;
use App\Models\StockEntryItem;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Bar;
use App\Models\Item;
use App\Models\CustomerTab;
use App\Models\Debt;
use App\Models\DailyReport;
use App\Models\DailyReportPayment;
use App\Models\User;
use App\Models\BottleCount;
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
            'filter' => $filter,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'barId' => $barId,
            'bottleCounts' => $this->getBottleCounts($startDate, $endDate, $barId),
        ];

        return view('reports.dashboard', $data);
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

            // EXPENSES: Prefer expenses linked to stock entries within the date range.
            // Also include expenses recorded by users assigned to the bar for the same date range
            // to handle cases where expenses were created without a stock_entry_id.
            $totalExpenses = Expense::where(function ($q) use ($startDate, $endDate, $barId) {
                // Expenses explicitly linked to stock entries in the date range
                $q->whereHas('stockEntry', function ($sq) use ($startDate, $endDate, $barId) {
                    $sq->whereBetween('date', [$startDate, $endDate]);
                    if ($barId) {
                        $sq->where('bar_id', $barId);
                    }
                });

                // OR expenses recorded on the same date range by users belonging to the bar
                $q->orWhere(function ($uq) use ($startDate, $endDate, $barId) {
                    $uq->whereBetween('date', [$startDate, $endDate]);
                    if ($barId) {
                        $uq->whereHas('user', function ($userQ) use ($barId) {
                            $userQ->where('bar_id', $barId);
                        });
                    }
                });
            })->sum('amount');

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
        $barsQuery = Bar::with(['dailyStockEntries' => function ($query) use ($startDate, $endDate) {
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

            $expenses = Expense::where(function($q) use ($bar, $startDate, $endDate) {
                    $q->whereHas('stockEntry', function ($sq) use ($bar, $startDate, $endDate) {
                        $sq->where('bar_id', $bar->id)->whereBetween('date', [$startDate, $endDate]);
                    })
                    ->orWhere(function($uq) use ($bar, $startDate, $endDate) {
                        $uq->whereBetween('date', [$startDate, $endDate])
                           ->whereHas('user', function($userQ) use ($bar) {
                               $userQ->where('bar_id', $bar->id);
                           });
                    });
                })->sum('amount');

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
            ->join('daily_stock_entries', 'stock_entry_items.stock_entry_id', '=', 'daily_stock_entries.id')
            ->whereBetween('daily_stock_entries.date', [$startDate, $endDate])
            ->where('stock_entry_items.sold_quantity', '>', 0);
            
        if ($barId) {
            $query->where('daily_stock_entries.bar_id', $barId);
        }
        
        return $query->groupBy('items.id', 'items.name', 'items.category')
            ->orderBy('total_revenue', 'desc')
            ->get();
    }

    private function getExpenseBreakdown($startDate, $endDate, $barId = null)
    {
        $query = Expense::select([
                'type',
                \DB::raw('SUM(amount) as total_amount'),
                \DB::raw('COUNT(*) as count')
            ])
            ->whereHas('stockEntry', function ($stockQuery) use ($startDate, $endDate) {
                $stockQuery->whereBetween('date', [$startDate, $endDate]);
            });
            
        if ($barId) {
            $query->whereHas('stockEntry', function ($stockQuery) use ($barId) {
                $stockQuery->where('bar_id', $barId);
            });
        }
        
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
            ->join('daily_stock_entries', 'debts.stock_entry_id', '=', 'daily_stock_entries.id')
            ->whereBetween('daily_stock_entries.date', [$startDate, $endDate]);
            
        if ($barId) {
            $debtsBySellerQuery->where('daily_stock_entries.bar_id', $barId);
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
            $stockEntry = DailyStockEntry::where('date', $report->date)
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
    public function reportingCreate()
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

        // Check if today's report already exists
        $existingReport = DailyReport::getTodayReport();
        
        // Get today's stock entry to calculate total sales
        $today = now()->format('Y-m-d');
        $stockEntry = null;
        $totalSales = 0;

        if ($user->bar_id) {
            // Try to get the most recent stock entry for today
            $stockEntry = DailyStockEntry::where('date', $today)
                ->where('bar_id', $user->bar_id)
                ->orderBy('updated_at', 'desc') // Get the most recently updated entry
                ->first();

            if ($stockEntry) {
                $totalSales = $stockEntry->stockEntryItems->sum('sales_amount');
            }
            
            // Fallback: If no stock entry found, try to get any stock entry for today
            if ($totalSales == 0) {
                $allStockEntries = DailyStockEntry::where('date', $today)
                    ->where('bar_id', $user->bar_id)
                    ->get();
                    
                foreach ($allStockEntries as $entry) {
                    $totalSales += $entry->stockEntryItems->sum('sales_amount');
                }
            }
        }

        // Get expenses for today to include in calculations
        $expenses = Expense::where('date', $today)
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
            $expenses = Expense::where('date', $today)
                               ->where('user_id', $user->id)
                               ->get();
        }

        if ($expenses->isEmpty()) {
            $expenses = Expense::where('date', $today)->get();
        }

        // Calculate financial values
        $totalExpenses = $expenses->sum('amount');
        $creditSales = CustomerTab::where('date', $today)
            ->where('bar_id', $user->bar_id)
            ->where('status', '!=', 'paid')
            ->sum('balance');

        $paymentMethods = DailyReportPayment::getPaymentMethods();
        $expenditureTypes = Expense::expenditureTypes();
        $shiftExpenditures = $this->getShiftExpenditures($user, $today, $stockEntry);
        $shiftDebtInForm = collect($shiftExpenditures)->where('type', 'debt')->sum('amount');
        $baseCreditSales = max(0, $creditSales - $shiftDebtInForm);

        return view('reporting.create', compact(
            'existingReport',
            'stockEntry',
            'totalSales',
            'totalExpenses',
            'creditSales',
            'baseCreditSales',
            'expenses',
            'paymentMethods',
            'expenditureTypes',
            'shiftExpenditures'
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

        $validated = $request->validate([
            'cash_in_hand' => 'required|numeric|min:0',
            'payments' => 'required|array|min:1',
            'payments.*.payment_method' => 'required|in:' . DailyReportPayment::paymentMethodKeys(),
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.description' => 'nullable|string|max:255',
            'expenditures' => 'nullable|array',
            'expenditures.*.type' => 'nullable|in:' . implode(',', array_keys(Expense::expenditureTypes())),
            'expenditures.*.amount' => 'nullable|numeric|min:0',
            'expenditures.*.notes' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $today = now()->format('Y-m-d');
            $stockEntry = DailyStockEntry::where('date', $today)
                ->where('bar_id', $user->bar_id)
                ->orderBy('updated_at', 'desc')
                ->first();

            // Calculate total payments
            $totalPayments = collect($validated['payments'])->sum('amount');

            // Create or update daily report
            $dailyReport = DailyReport::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'bar_id' => $user->bar_id,
                    'date' => $today,
                ],
                [
                    'cash_in_hand' => $validated['cash_in_hand'],
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

            $this->syncShiftExpenditures($request, $user, $stockEntry);

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
        $stockEntry = DailyStockEntry::where('date', $dailyReport->date)
            ->where('bar_id', $dailyReport->bar_id)
            ->orderBy('updated_at', 'desc')
            ->first();
            
        $totalSales = $stockEntry ? $stockEntry->stockEntryItems->sum('sales_amount') : 0;
        
        // COLLECTION: Collected Money ONLY from payments table
        // Cash is stored in daily_reports.cash_in_hand
        $cashPayments = $dailyReport->cash_in_hand;
        $mobilePayments = $dailyReport->payments()->whereIn('payment_method', ['Airtel Money', 'Mpamba', 'MO626', 'MO', 'POS'])->sum('amount');
        $debtCollections = $dailyReport->payments()->where('payment_method', 'Debt Collection')->sum('amount');
        
        // "Collected" includes cash + mobile + debt collections.
        $totalCollected = $cashPayments + $mobilePayments + $debtCollections;
        
        $totalPayments = $totalCollected; 
        
        // CREDIT: New Credit Sales from today ONLY
        $creditSales = CustomerTab::where('date', $dailyReport->date)
            ->where('bar_id', $dailyReport->bar_id)
            ->where('status', '!=', 'paid')
            ->sum('balance');
        
        // EXPENSES (operational only — debt is tracked as credit sales)
        $totalExpenses = $expenses->sum('amount');

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
        $allRegisteredPayments = $dailyReport->cash_in_hand + $dailyReport->payments()->sum('amount');
        $paymentsByMethod = $dailyReport->payments()
            ->selectRaw('payment_method, SUM(amount) as total_amount')
            ->groupBy('payment_method')
            ->get()
            ->mapWithKeys(function ($payment) use ($allRegisteredPayments) {
                return [
                    $payment->payment_method => [
                        'amount' => $payment->total_amount,
                        'percentage' => $allRegisteredPayments > 0 ? ($payment->total_amount / $allRegisteredPayments) * 100 : 0
                    ]
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
            'totalPayments'
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
        if ($user->isSeller() && ($dailyReport->user_id !== $user->id || $dailyReport->date->format('Y-m-d') !== now()->format('Y-m-d'))) {
            abort(403, 'You can only edit your own today\'s report');
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
        $stockEntry = DailyStockEntry::where('date', $dailyReport->date)
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
        $expenditureTypes = Expense::expenditureTypes();
        $stockEntry = DailyStockEntry::where('date', $dailyReport->date)
            ->where('bar_id', $dailyReport->bar_id)
            ->orderBy('updated_at', 'desc')
            ->first();
        $shiftExpenditures = $this->getShiftExpenditures($user, $dailyReport->date->format('Y-m-d'), $stockEntry);
        $shiftDebtInForm = collect($shiftExpenditures)->where('type', 'debt')->sum('amount');
        $baseCreditSales = max(0, $creditSales - $shiftDebtInForm);

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
            'shiftExpenditures'
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
        if ($user->isSeller() && ($dailyReport->user_id !== $user->id || $dailyReport->date->format('Y-m-d') !== now()->format('Y-m-d'))) {
            abort(403, 'You can only update your own today\'s report');
        }

        $validated = $request->validate([
            'cash_in_hand' => 'required|numeric|min:0',
            'payments' => 'required|array|min:1',
            'payments.*.payment_method' => 'required|in:' . DailyReportPayment::paymentMethodKeys(),
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.description' => 'nullable|string|max:255',
            'expenditures' => 'nullable|array',
            'expenditures.*.type' => 'nullable|in:' . implode(',', array_keys(Expense::expenditureTypes())),
            'expenditures.*.amount' => 'nullable|numeric|min:0',
            'expenditures.*.notes' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $stockEntry = DailyStockEntry::where('date', $dailyReport->date)
                ->where('bar_id', $dailyReport->bar_id)
                ->orderBy('updated_at', 'desc')
                ->first();

            // Calculate total payments
            $totalPayments = collect($validated['payments'])->sum('amount');

            // Update daily report
            $dailyReport->update([
                'cash_in_hand' => $validated['cash_in_hand'],
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
        if ($user->isSeller() && ($dailyReport->user_id !== $user->id || $dailyReport->date->format('Y-m-d') !== now()->format('Y-m-d'))) {
            abort(403, 'You can only delete your own today\'s report');
        }

        try {
            DB::beginTransaction();
            
            $dailyReport->payments()->delete();
            $dailyReport->delete();
            
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

    private function getShiftExpenditures(User $user, string $date, ?DailyStockEntry $stockEntry): array
    {
        $items = [];

        $expenseQuery = Expense::where('date', $date);
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

        return $items;
    }

    private function syncShiftExpenditures(Request $request, User $user, ?DailyStockEntry $stockEntry, ?string $date = null): void
    {
        $date = $date ?? now()->format('Y-m-d');
        $barId = $user->bar_id;
        $expenditures = $request->input('expenditures', []);

        Expense::where('user_id', $user->id)->where('date', $date)->delete();

        if ($barId) {
            CustomerTab::where('bar_id', $barId)
                ->whereDate('date', $date)
                ->where('created_by', $user->id)
                ->where('description', 'like', Expense::SHIFT_DEBT_PREFIX . '%')
                ->delete();
        }

        foreach ($expenditures as $row) {
            $amount = (float) ($row['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $type = $row['type'] ?? '';
            $notes = trim($row['notes'] ?? '');

            if ($type === 'debt' && $barId) {
                $customerName = $notes ?: 'Credit Customer';
                CustomerTab::create([
                    'customer_name' => $customerName,
                    'phone' => null,
                    'bar_id' => $barId,
                    'date' => $date,
                    'amount' => $amount,
                    'paid_amount' => 0,
                    'balance' => $amount,
                    'status' => 'open',
                    'description' => Expense::SHIFT_DEBT_PREFIX . ' ' . $notes,
                    'created_by' => $user->id,
                ]);
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
     * Shift till reconciliation: operational spend reduces what should remain in the drawer.
     */
    private function shiftCollectionMath(
        float $totalSales,
        float $creditSales,
        float $totalCollected,
        float $operationalExpenses,
        float $debtCollections = 0
    ): array {
        $expectedCollected = ($totalSales - $creditSales - $operationalExpenses) + $debtCollections;
        $missingMoney = $totalCollected - $expectedCollected;

        return [
            'expectedCollected' => $expectedCollected,
            'missingMoney' => $missingMoney,
            'bankableBalance' => $totalCollected - $operationalExpenses,
        ];
    }
}
