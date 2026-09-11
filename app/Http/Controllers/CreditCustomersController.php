<?php

namespace App\Http\Controllers;

use App\Models\CustomerTab;
use App\Models\Bar;
use App\Models\User;
use App\Support\CsvExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class CreditCustomersController extends Controller
{
    /**
     * Sellers can view their bar's Ngongole (credit) tabs and totals, but
     * only managers/directors can create, edit, delete, or record a payment
     * against one - "clearing off" a Ngongole is a manager/director action.
     */
    private function assertCanManageCredit(): void
    {
        if (Auth::user()->isSeller()) {
            abort(403, 'Only managers and directors can manage credit entries.');
        }
    }

    /**
     * Display a listing of credit customers.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $bar = $user->bar;

        // Managers and directors can access credit tabs without a personal bar assignment.
        if (!$bar && !$user->isAdmin()) {
            abort(403, 'You must be assigned to a bar to access credit customers.');
        }

        $filter = $request->get('filter', 'all');
        $search = $request->get('search', '');

        // Global view across bars for managers/directors without a bar assignment.
        if ($user->isAdmin() && !$bar) {
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
        $this->assertCanManageCredit();

        $user = Auth::user();
        $bar = $user->bar;

        if (!$bar && !$user->isAdmin()) {
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

        $bars = $user->isAdmin() ? Bar::listed()->get() : [];

        return view('credit-customers.create', compact('existingCustomers', 'bars', 'bar'));
    }

    /**
     * Store a newly created credit entry.
     */
    public function store(Request $request)
    {
        $this->assertCanManageCredit();

        $user = Auth::user();
        $bar = $user->bar;

        if (!$bar && !$user->isAdmin()) {
            abort(403, 'You must be assigned to a bar to create credit entries.');
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:255',
            'bar_id' => ['nullable', 'exists:bars,id', Rule::requiredIf($user->isAdmin() && !$bar)],
        ]);

        try {
            DB::beginTransaction();

            $targetBarId = $user->isAdmin()
                ? ($validated['bar_id'] ?? $bar?->id)
                : $bar->id;

            if (!$targetBarId) {
                throw new \InvalidArgumentException('A target bar is required for this credit entry.');
            }

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

        if (!$bar && !$user->isAdmin()) {
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
        $this->assertCanManageCredit();

        $user = Auth::user();
        $bar = $user->bar;

        if (!$bar && !$user->isAdmin()) {
            abort(403, 'You must be assigned to a bar to record payments.');
        }

        // Get customer's unpaid tabs
        $unpaidTabsQuery = CustomerTab::where('customer_name', $customerName)->unpaid();
        if ($bar) {
            $unpaidTabsQuery->forBar($bar->id);
        }
        $unpaidTabs = $unpaidTabsQuery->orderBy('date', 'asc')->get();

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
        $this->assertCanManageCredit();

        $user = Auth::user();
        $bar = $user->bar;

        if (!$bar && !$user->isAdmin()) {
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
            $unpaidTabsQuery = CustomerTab::where('customer_name', $customerName)->unpaid();
            if ($bar) {
                $unpaidTabsQuery->forBar($bar->id);
            }
            $unpaidTabs = $unpaidTabsQuery->orderBy('date', 'asc')->get();

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
            $paymentBarId = $bar?->id ?? $unpaidTabs->first()?->bar_id;
            $dailyReport = $paymentBarId
                ? \App\Models\DailyReport::where('bar_id', $paymentBarId)
                    ->where('date', $today)
                    ->first()
                : null;

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
        
        // Authorization: Only managers/directors can clear off or edit a Ngongole
        if (!$user->isManager() && !$user->isDirector()) {
            abort(403, 'Only managers and directors can edit credit entries.');
        }

        return view('credit-customers.edit', compact('customerTab'));
    }

    /**
     * Update the credit entry.
     */
    public function update(Request $request, CustomerTab $customerTab)
    {
        $user = Auth::user();
        
        // Authorization: Only managers/directors can clear off or edit a Ngongole
        if (!$user->isManager() && !$user->isDirector()) {
            abort(403, 'Only managers and directors can edit credit entries.');
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
        
        // Authorization: Only managers/directors can delete a credit entry
        if (!$user->isManager() && !$user->isDirector()) {
            abort(403, 'Only managers and directors can delete credit entries.');
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

        if (!$bar && !$user->isAdmin()) {
            abort(403, 'You must be assigned to a bar to export data.');
        }

        $filter = $request->get('filter', 'all');
        $search = $request->get('search', '');
        $exportBarId = $bar?->id ?? $request->get('bar_id');

        if ($user->isAdmin() && !$exportBarId) {
            $customers = CustomerTab::selectRaw('
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
            if (!$exportBarId) {
                abort(422, 'Select a bar to export credit customer data.');
            }
            $customers = CustomerTab::getCustomersWithBalances((int) $exportBarId);
        }

        if ($search) {
            $customers = $customers->filter(function ($customer) use ($search) {
                return stripos($customer->customer_name, $search) !== false
                    || stripos($customer->phone ?? '', $search) !== false;
            });
        }

        if ($filter !== 'all') {
            $customers = $customers->filter(fn ($customer) => $customer->status === $filter);
        }

        $includeBar = $user->isAdmin() && !$exportBarId;
        $rows = [];
        $headers = ['Customer Name', 'Phone'];
        if ($includeBar) {
            $headers[] = 'Bar';
        }
        $rows[] = array_merge($headers, ['Total Credit', 'Total Paid', 'Balance', 'Status', 'Last Activity']);

        foreach ($customers->values() as $customer) {
            $row = [$customer->customer_name, $customer->phone ?? ''];
            if ($includeBar) {
                $row[] = $customer->bar->name ?? '';
            }
            $rows[] = array_merge($row, [
                $customer->total_amount,
                $customer->total_paid,
                $customer->total_balance,
                $customer->status,
                $customer->last_activity,
            ]);
        }

        $filename = 'credit_customers_' . now()->format('Y-m-d') . '.csv';

        return CsvExport::download($rows, $filename);
    }
}
