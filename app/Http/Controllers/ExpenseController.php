<?php

namespace App\Http\Controllers;

use App\Models\CustomerTab;
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

        $expenseRows = Expense::query()
            ->when($user->isSeller(), fn ($q) => $q->where('user_id', $user->id)->barOperating())
            ->when($user->isAdmin(), fn ($q) => $q->overhead())
            ->selectRaw('date, SUM(amount) as expense_total, COUNT(*) as expense_count')
            ->whereNotNull('date')
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($row) => is_string($row->date) ? $row->date : $row->date->format('Y-m-d'));

        $debtRows = CustomerTab::query()
            ->where('description', 'like', Expense::SHIFT_DEBT_PREFIX . '%')
            ->when($user->isSeller(), fn ($q) => $q->where('created_by', $user->id))
            ->when($user->bar_id, fn ($q) => $q->where('bar_id', $user->bar_id))
            ->selectRaw('date, SUM(amount) as debt_total, COUNT(*) as debt_count')
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($row) => $row->date->format('Y-m-d'));

        $dates = $expenseRows->keys()->merge($debtRows->keys())->unique()->sortDesc()->values();

        $all = $dates->map(fn ($date) => (object) [
            'date' => $date,
            'total_amount' => ($expenseRows[$date]->expense_total ?? 0) + ($debtRows[$date]->debt_total ?? 0),
            'count' => ($expenseRows[$date]->expense_count ?? 0) + ($debtRows[$date]->debt_count ?? 0),
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

        if ($user->isSeller()) {
            $expenses = Expense::where('user_id', $user->id)
                ->barOperating()
                ->where('date', $date)
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get();

            $debtEntries = CustomerTab::whereDate('date', $date)
                ->where('created_by', $user->id)
                ->where('description', 'like', Expense::SHIFT_DEBT_PREFIX . '%')
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $expenses = Expense::overhead()
                ->where('date', $date)
                ->with(['user', 'bar'])
                ->orderBy('created_at', 'desc')
                ->get();

            $debtEntries = CustomerTab::whereDate('date', $date)
                ->where('description', 'like', Expense::SHIFT_DEBT_PREFIX . '%')
                ->orderBy('created_at', 'desc')
                ->get();
        }

        $totalAmount = $expenses->sum('amount') + $debtEntries->sum('amount');

        return view('expenses.daily', compact('expenses', 'debtEntries', 'date', 'totalAmount'));
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
        $this->authorizeExpenseAccess($expense);
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

    private function authorizeExpenseAccess(Expense $expense): void
    {
        $user = auth()->user();
        if ($user->isSeller() && $expense->user_id !== $user->id) {
            abort(403, 'Unauthorized access to expense.');
        }
        if ($user->isAdmin() && !$expense->is_overhead) {
            abort(403, 'Shift expenses are managed through daily shift reports.');
        }
    }
}
