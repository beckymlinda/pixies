<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Bar;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Pagination\LengthAwarePaginator;

class ExpenseController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        // Debt (Ngongole) belongs on the Credit Customers page, not here -
        // this listing is genuine cash expenses only. Managers/directors see
        // every cash expense (their own overhead entries plus whatever
        // sellers recorded on their shift reports), not just their own.
        $expenseRows = Expense::query()
            ->when($user->isSeller(), fn ($q) => $q->where('user_id', $user->id)->barOperating())
            ->selectRaw('date, SUM(amount) as expense_total, COUNT(*) as expense_count')
            ->whereNotNull('date')
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($row) => is_string($row->date) ? $row->date : $row->date->format('Y-m-d'));

        $dates = $expenseRows->keys()->unique()->sortDesc()->values();

        $all = $dates->map(fn ($date) => (object) [
            'date' => $date,
            'total_amount' => $expenseRows[$date]->expense_total ?? 0,
            'count' => $expenseRows[$date]->expense_count ?? 0,
        ]);

        $page = max(1, (int) request('page', 1));
        $perPage = 10;
        $expensesByDate = new LengthAwarePaginator(
            $all->slice(($page - 1) * $perPage, $perPage)->values(),
            $all->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('expenses.index', compact('expensesByDate'));
    }

    public function daily($date): View
    {
        $user = auth()->user();

        // Debt (Ngongole) belongs on the Credit Customers page, not here -
        // this listing is genuine cash expenses only. Managers/directors see
        // every cash expense for the day (their own overhead entries plus
        // whatever sellers recorded on their shift reports), not just their own.
        if ($user->isSeller()) {
            $expenses = Expense::where('user_id', $user->id)
                ->barOperating()
                ->where('date', $date)
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $expenses = Expense::where('date', $date)
                ->with(['user', 'bar'])
                ->orderBy('created_at', 'desc')
                ->get();
        }

        $totalAmount = $expenses->sum('amount');

        return view('expenses.daily', compact('expenses', 'date', 'totalAmount'));
    }

    public function create(): View|RedirectResponse
    {
        if (auth()->user()->isSeller()) {
            return redirect()->route('reporting.index')
                ->with('info', 'Record expenditure in your shift report.');
        }

        return view('expenses.create', [
            'bars' => auth()->user()->isAdmin() ? Bar::listed()->orderBy('name')->get() : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (auth()->user()->isSeller()) {
            return redirect()->route('reporting.index')
                ->with('info', 'Record expenditure in your shift report.');
        }
        $user = auth()->user();
        $validated = $request->validate([
            'type' => 'required|in:' . implode(',', array_keys(Expense::operationalTypes())),
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:500',
            'date' => 'required|date',
            'bar_id' => $user->isAdmin() ? 'required|exists:bars,id' : 'nullable',
        ]);

        Expense::create([
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? null,
            'date' => $validated['date'],
            'user_id' => $user->id,
            'bar_id' => $user->isAdmin() ? $validated['bar_id'] : null,
            'is_overhead' => $user->isAdmin(),
        ]);

        return redirect()->route('expenses.index')->with('success', 'Expense recorded successfully.');
    }

    public function show(Expense $expense): View
    {
        $this->authorizeExpenseView($expense);
        $expense->load('user');

        return view('expenses.show', compact('expense'));
    }

    public function edit(Expense $expense): View
    {
        $this->authorizeExpenseAccess($expense);

        return view('expenses.edit', compact('expense'));
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorizeExpenseAccess($expense);

        $validated = $request->validate([
            'type' => 'required|in:' . implode(',', array_keys(Expense::operationalTypes())),
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:500',
            'date' => 'required|date',
        ]);

        $expense->update($validated);

        return redirect()->route('expenses.index')->with('success', 'Expense updated successfully.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorizeExpenseAccess($expense);
        $expense->delete();

        return redirect()->route('expenses.index')->with('success', 'Expense deleted successfully.');
    }

    /**
     * Viewing is allowed for anything the user can see on the index/daily
     * listings - sellers only their own, admins everything.
     */
    private function authorizeExpenseView(Expense $expense): void
    {
        $user = auth()->user();
        if ($user->isSeller() && $expense->user_id !== $user->id) {
            abort(403, 'Unauthorized access to expense.');
        }
    }

    /**
     * Editing/deleting a shift-recorded expense here would just get
     * silently overwritten the next time that shift report is resaved, so
     * that's blocked - those are managed through daily shift reports.
     */
    private function authorizeExpenseAccess(Expense $expense): void
    {
        $this->authorizeExpenseView($expense);
        $user = auth()->user();
        if ($user->isAdmin() && !$expense->is_overhead) {
            abort(403, 'Shift expenses are managed through daily shift reports.');
        }
    }
}
