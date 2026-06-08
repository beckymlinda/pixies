<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\DailyStockEntry;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    /**
     * Display a listing of expenses.
     */
    public function index(): View
    {
        $user = auth()->user();
        
        // Get expenses grouped by date
        if ($user->isSeller()) {
            // Sellers can only see their own expenses
            $expensesByDate = Expense::where('user_id', $user->id)
                ->selectRaw('date as date, SUM(amount) as total_amount, COUNT(*) as count')
                ->whereNotNull('date')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->paginate(10);
        } else {
            // Managers and Directors can see all expenses
            $expensesByDate = Expense::selectRaw('date as date, SUM(amount) as total_amount, COUNT(*) as count')
                ->whereNotNull('date')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->paginate(10);
        }

        return view('expenses.index', compact('expensesByDate'));
    }

    public function daily($date): View
    {
        $user = auth()->user();
        
        // Get expenses for specific date
        if ($user->isSeller()) {
            $expenses = Expense::where('user_id', $user->id)
                ->where('date', $date)
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get();
                
            $totalAmount = Expense::where('user_id', $user->id)
                ->where('date', $date)
                ->sum('amount');
        } else {
            $expenses = Expense::where('date', $date)
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get();
                
            $totalAmount = Expense::where('date', $date)
                ->sum('amount');
        }

        return view('expenses.daily', compact('expenses', 'date', 'totalAmount'));
    }

    /**
     * Show the form for creating a new expense.
     */
    public function create(): View
    {
        return view('expenses.create');
    }

    /**
     * Store a newly created expense in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:Debt,Lunch,Other',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:255',
            'date' => 'required|date',
        ]);

        $validated['user_id'] = auth()->id();

        $expense = Expense::create($validated);

        return redirect()
            ->route('expenses.index')
            ->with('success', 'Expense created successfully!');
    }

    /**
     * Display the specified expense.
     */
    public function show(Expense $expense): View
    {
        // Check if user can view this expense
        $this->authorizeExpenseAccess($expense);

        $expense->load('user');

        return view('expenses.show', compact('expense'));
    }

    /**
     * Show the form for editing the specified expense.
     */
    public function edit(Expense $expense): View
    {
        // Check if user can edit this expense
        $this->authorizeExpenseAccess($expense);

        return view('expenses.edit', compact('expense'));
    }

    /**
     * Update the specified expense in storage.
     */
    public function update(Request $request, Expense $expense): RedirectResponse
    {
        // Check if user can edit this expense
        $this->authorizeExpenseAccess($expense);

        $validated = $request->validate([
            'type' => 'required|in:Debt,Lunch,Other',
            'amount' => 'required|numeric|min:0',
            'description' => 'required|string|max:255',
            'date' => 'required|date',
        ]);

        $expense->update($validated);

        return redirect()
            ->route('expenses.index')
            ->with('success', 'Expense updated successfully!');
    }

    /**
     * Remove the specified expense from storage.
     */
    public function destroy(Expense $expense): RedirectResponse
    {
        // Check if user can delete this expense
        $this->authorizeExpenseAccess($expense);

        $expense->delete();

        return redirect()
            ->route('expenses.index')
            ->with('success', 'Expense deleted successfully!');
    }

    /**
     * Check if user can access the expense.
     */
    private function authorizeExpenseAccess(Expense $expense): void
    {
        $user = auth()->user();
        
        // Sellers can only access their own expenses
        if ($user->isSeller() && $expense->user_id !== $user->id) {
            abort(403, 'Unauthorized access to expense.');
        }
        
        // Managers and Directors can access all expenses
        // No additional checks needed for them
    }
}
