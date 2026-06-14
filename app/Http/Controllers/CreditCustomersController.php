<?php

namespace App\Http\Controllers;

use App\Models\CustomerTab;
use App\Models\Bar;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CreditCustomersController extends Controller
{
    /**
     * Display a listing of credit customers.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $bar = $user->bar;

        // Bypass bar check for Director
        if (!$bar && !$user->isDirector()) {
            abort(403, 'You must be assigned to a bar to access credit customers.');
        }

        $filter = $request->get('filter', 'all');
        $search = $request->get('search', '');

        // If director and no bar, get global customers, otherwise bar specific
        if ($user->isDirector() && !$bar) {
            $query = CustomerTab::selectRaw('
                customer_name,
                phone,
                bar_id,
                SUM(amount) as total_amount,
                SUM(paid_amount) as total_paid,
                SUM(balance) as total_balance,
                MAX(date) as last_activity,
                GROUP_CONCAT(DISTINCT status) as statuses
            ')
            ->with('bar')
            ->groupBy('customer_name', 'phone', 'bar_id')
            ->orderBy('total_balance', 'desc')
            ->get()
            ->map(function ($customer) {
                $customer->status = $customer->total_balance <= 0 ? 'paid' : 
                                 ($customer->total_paid > 0 ? 'partial' : 'open');
                return $customer;
            });
        } else {
            $query = CustomerTab::getCustomersWithBalances($bar->id);
        }

        // Apply search filter
        if ($search) {
            $query = $query->filter(function ($customer) use ($search) {
                return stripos($customer->customer_name, $search) !== false ||
                       stripos($customer->phone, $search) !== false;
            });
        }

        // Apply status filter
        if ($filter !== 'all') {
            $query = $query->filter(function ($customer) use ($filter) {
                return $customer->status === $filter;
            });
        }

        $customers = $query->values();

        // Calculate totals
        $totalOutstanding = $customers->sum('total_balance');
        $totalCustomers = $customers->count();
        $openAccounts = $customers->where('status', 'open')->count();
        $partialAccounts = $customers->where('status', 'partial')->count();

        return view('credit-customers.index', compact(
            'customers',
            'totalOutstanding',
            'totalCustomers',
            'openAccounts',
            'partialAccounts',
            'filter',
            'search'
        ));
    }

    /**
     * Show the form for creating a new credit entry.
     */
    public function create()
    {
        $user = Auth::user();
        $bar = $user->bar;

        if (!$bar && !$user->isDirector()) {
            abort(403, 'You must be assigned to a bar to create credit entries.');
        }

        // Get existing customers for autocomplete
        $query = CustomerTab::query();
        if ($bar) {
            $query->forBar($bar->id);
        }
        
        $existingCustomers = $query->distinct()
            ->pluck('customer_name')
            ->sort()
            ->values();

        $bars = $user->isDirector() ? Bar::listed()->get() : [];

        return view('credit-customers.create', compact('existingCustomers', 'bars', 'bar'));
    }

    /**
     * Store a newly created credit entry.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $bar = $user->bar;

        if (!$bar && !$user->isDirector()) {
            abort(403, 'You must be assigned to a bar to create credit entries.');
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:255',
            'bar_id' => 'required_if:is_director,1|exists:bars,id',
        ]);

        try {
            DB::beginTransaction();

            $targetBarId = $user->isDirector() ? $request->bar_id : $bar->id;

            // Create new credit entry
            CustomerTab::create([
                'customer_name' => $validated['customer_name'],
                'phone' => $validated['phone'],
                'bar_id' => $targetBarId,
                'date' => $validated['date'],
                'amount' => $validated['amount'],
                'description' => $validated['description'],
                'created_by' => $user->id,
            ]);

            DB::commit();

            return redirect()
                ->route('credit-customers.index')
                ->with('success', 'Credit entry created successfully!');

        } catch (\Exception $e) {
            DB::rollback();
            
            return back()
                ->withInput()
                ->with('error', 'Error creating credit entry: ' . $e->getMessage());
        }
    }

    /**
     * Show customer details with full history.
     */
    public function show($customerName)
    {
        $user = Auth::user();
        $bar = $user->bar;

        if (!$bar && !$user->isDirector()) {
            abort(403, 'You must be assigned to a bar to view customer details.');
        }

        // Get customer's full history (across all bars for director if no specific bar)
        $query = CustomerTab::where('customer_name', $customerName);
        if ($bar) {
            $query->where('bar_id', $bar->id);
        }
        
        $tabs = $query->orderBy('date', 'desc')->get();

        if ($tabs->isEmpty()) {
            abort(404, 'Customer not found.');
        }

        // Calculate totals
        $totalAmount = $tabs->sum('amount');
        $totalPaid = $tabs->sum('paid_amount');
        $totalBalance = $tabs->sum('balance');

        $customer = (object) [
            'name' => $customerName,
            'phone' => $tabs->first()->phone,
            'total_amount' => $totalAmount,
            'total_paid' => $totalPaid,
            'total_balance' => $totalBalance,
            'status' => $totalBalance <= 0 ? 'paid' : ($totalPaid > 0 ? 'partial' : 'open'),
            'first_entry_date' => $tabs->min('date'),
            'last_entry_date' => $tabs->max('date'),
        ];

        return view('credit-customers.show', compact('customer', 'tabs'));
    }

    /**
     * Show the form for recording a payment.
     */
    public function payment($customerName)
    {
        $user = Auth::user();
        $bar = $user->bar;

        if (!$bar && !$user->isDirector()) {
            abort(403, 'You must be assigned to a bar to record payments.');
        }

        // Get customer's unpaid tabs
        $unpaidTabs = CustomerTab::forBar($bar->id)
            ->where('customer_name', $customerName)
            ->unpaid()
            ->orderBy('date', 'asc')
            ->get();

        if ($unpaidTabs->isEmpty()) {
            return redirect()
                ->route('credit-customers.show', $customerName)
                ->with('info', 'This customer has no outstanding balance.');
        }

        $totalBalance = $unpaidTabs->sum('balance');

        return view('credit-customers.payment', compact(
            'customerName',
            'unpaidTabs',
            'totalBalance'
        ));
    }

    /**
     * Record a payment for the customer.
     */
    public function recordPayment(Request $request, $customerName)
    {
        $user = Auth::user();
        $bar = $user->bar;

        if (!$bar && !$user->isDirector()) {
            abort(403, 'You must be assigned to a bar to record payments.');
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,bank_transfer,mobile_money,other',
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            // Get customer's unpaid tabs
            $unpaidTabs = CustomerTab::forBar($bar->id)
                ->where('customer_name', $customerName)
                ->unpaid()
                ->orderBy('date', 'asc')
                ->get();

            if ($unpaidTabs->isEmpty()) {
                throw new \Exception('No outstanding balance found for this customer.');
            }

            $paymentAmount = $validated['amount'];
            $totalBalance = $unpaidTabs->sum('balance');

            if ($paymentAmount > $totalBalance) {
                throw new \Exception('Payment amount cannot exceed outstanding balance of ' . number_format($totalBalance, 0));
            }

            // Distribute payment across tabs (oldest first)
            $remainingPayment = $paymentAmount;
            foreach ($unpaidTabs as $tab) {
                if ($remainingPayment <= 0) break;

                $tabBalance = $tab->balance;
                $paymentToApply = min($remainingPayment, $tabBalance);
                
                $tab->addPayment($paymentToApply);
                $remainingPayment -= $paymentToApply;
            }

            // Automate Integration with Daily Report
            $today = now()->format('Y-m-d');
            $dailyReport = \App\Models\DailyReport::where('bar_id', $targetBarId)
                ->where('date', $today)
                ->first();

            if ($dailyReport) {
                // Add a payment record to today's report
                \App\Models\DailyReportPayment::create([
                    'daily_report_id' => $dailyReport->id,
                    'payment_method' => 'Debt Collection',
                    'amount' => $paymentAmount,
                    'description' => "Debt payment from {$customerName} ({$validated['payment_method']})",
                ]);

                // Update the total payments in the report
                $dailyReport->increment('total_payments', $paymentAmount);
            }

            DB::commit();

            return redirect()
                ->route('credit-customers.show', $customerName)
                ->with('success', 'Payment of ' . number_format($paymentAmount, 0) . ' recorded successfully!');

        } catch (\Exception $e) {
            DB::rollback();
            
            return back()
                ->withInput()
                ->with('error', 'Error recording payment: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing a credit entry.
     */
    public function edit(CustomerTab $customerTab)
    {
        $user = Auth::user();
        
        // Authorization: Only creator or manager/director can edit
        if ($customerTab->created_by !== $user->id && !$user->isManager() && !$user->isDirector()) {
            abort(403, 'You can only edit your own entries.');
        }

        return view('credit-customers.edit', compact('customerTab'));
    }

    /**
     * Update the credit entry.
     */
    public function update(Request $request, CustomerTab $customerTab)
    {
        $user = Auth::user();
        
        // Authorization: Only creator or manager/director can edit
        if ($customerTab->created_by !== $user->id && !$user->isManager() && !$user->isDirector()) {
            abort(403, 'You can only edit your own entries.');
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            // Update the credit entry
            $customerTab->update($validated);
            $customerTab->updateStatus();

            DB::commit();

            return redirect()
                ->route('credit-customers.show', $customerTab->customer_name)
                ->with('success', 'Credit entry updated successfully!');

        } catch (\Exception $e) {
            DB::rollback();
            
            return back()
                ->withInput()
                ->with('error', 'Error updating credit entry: ' . $e->getMessage());
        }
    }

    /**
     * Delete a credit entry.
     */
    public function destroy(CustomerTab $customerTab)
    {
        $user = Auth::user();
        
        // Authorization: Only creator or manager/director can delete
        if ($customerTab->created_by !== $user->id && !$user->isManager() && !$user->isDirector()) {
            abort(403, 'You can only delete your own entries.');
        }

        try {
            DB::beginTransaction();

            $customerName = $customerTab->customer_name;
            $customerTab->delete();

            DB::commit();

            return redirect()
                ->route('credit-customers.index')
                ->with('success', 'Credit entry deleted successfully!');

        } catch (\Exception $e) {
            DB::rollback();
            
            return back()
                ->with('error', 'Error deleting credit entry: ' . $e->getMessage());
        }
    }

    /**
     * Export customer data for month-end billing.
     */
    public function export(Request $request)
    {
        $user = Auth::user();
        $bar = $user->bar;

        if (!$bar) {
            abort(403, 'You must be assigned to a bar to export data.');
        }

        $filter = $request->get('filter', 'all');
        $month = $request->get('month', now()->format('Y-m'));

        $query = CustomerTab::forBar($bar->id);

        // Apply date filter
        if ($filter === 'month') {
            $query->whereRaw('DATE_FORMAT(date, "%Y-%m") = ?', [$month]);
        }

        $customers = CustomerTab::getCustomersWithBalances($bar->id)
            ->filter(function ($customer) use ($filter, $month) {
                if ($filter === 'month') {
                    // Check if customer has entries in the specified month
                    return CustomerTab::forBar($bar->id)
                        ->where('customer_name', $customer->customer_name)
                        ->whereRaw('DATE_FORMAT(date, "%Y-%m") = ?', [$month])
                        ->exists();
                }
                return true;
            });

        $csvData = [];
        $csvData[] = ['Customer Name', 'Phone', 'Total Amount', 'Total Paid', 'Balance', 'Status', 'Last Activity'];

        foreach ($customers as $customer) {
            $csvData[] = [
                $customer->customer_name,
                $customer->phone,
                $customer->total_amount,
                $customer->total_paid,
                $customer->total_balance,
                $customer->status,
                $customer->last_activity,
            ];
        }

        $filename = 'credit_customers_' . $month . '.csv';
        
        return response()->streamDownload(function () use ($csvData) {
            $file = fopen('php://output', 'w');
            foreach ($csvData as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
