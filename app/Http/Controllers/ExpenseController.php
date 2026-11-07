<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Expense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    private function branchScope(Request $request, $query): void
    {
        if ($request->user()->isAccountant()) {
            $branchId = $request->user()->branch_id;
            abort_unless($branchId && Branch::whereKey($branchId)->where('is_active', true)->exists(), 403);
            $query->where('branch_id', $branchId);
        }
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'category' => ['nullable', 'string', 'max:80'],
        ]);

        $query = Expense::with(['branch', 'creator'])->latest('expense_date');
        $this->branchScope($request, $query);

        if (! empty($validated['date_from'])) {
            $query->whereDate('expense_date', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->whereDate('expense_date', '<=', $validated['date_to']);
        }
        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        $expenses = (clone $query)->paginate(25)->withQueryString();
        $total = (clone $query)->sum('amount');
        $categories = Expense::query()->when(
            $request->user()->isAccountant(),
            fn ($items) => $items->where('branch_id', $request->user()->branch_id)
        )->select('category')->distinct()->orderBy('category')->pluck('category');

        $branches = $request->user()->isAdmin()
            ? Branch::orderBy('name')->get()
            : collect();

        return view('admin.expenses.index', [
            'expenses' => $expenses,
            'total' => (float) $total,
            'categories' => $categories,
            'branches' => $branches,
            'filters' => $validated,
            'isAccountant' => $request->user()->isAccountant(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $isAccountant = $request->user()->isAccountant();
        if ($isAccountant) {
            $branchId = $request->user()->branch_id;
            abort_unless($branchId && Branch::whereKey($branchId)->where('is_active', true)->exists(), 403);
        }

        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'category' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'payment_method' => ['required', 'in:cash,bkash,nagad,bank,card,other'],
            'reference' => ['nullable', 'string', 'max:120'],
            'expense_date' => ['required', 'date'],
        ]);

        if ($isAccountant) {
            $validated['branch_id'] = $request->user()->branch_id;
        } elseif (! empty($validated['branch_id'])) {
            abort_unless(Branch::whereKey($validated['branch_id'])->where('is_active', true)->exists(), 422);
        }

        Expense::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Expense recorded successfully.');
    }

    public function update(Expense $expense, Request $request): RedirectResponse
    {
        $this->authorizeExpense($expense, $request);
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'payment_method' => ['required', 'in:cash,bkash,nagad,bank,card,other'],
            'reference' => ['nullable', 'string', 'max:120'],
            'expense_date' => ['required', 'date'],
        ]);

        $expense->update($validated);

        return back()->with('success', 'Expense updated successfully.');
    }

    public function destroy(Expense $expense, Request $request): RedirectResponse
    {
        $this->authorizeExpense($expense, $request);
        $expense->delete();

        return back()->with('success', 'Expense deleted successfully.');
    }

    private function authorizeExpense(Expense $expense, Request $request): void
    {
        if ($request->user()->isAccountant()) {
            $branchId = $request->user()->branch_id;
            abort_unless(
                $branchId && Branch::whereKey($branchId)->where('is_active', true)->exists()
                    && (int) $expense->branch_id === (int) $branchId,
                404
            );
        } else {
            abort_unless($request->user()->isAdmin(), 403);
        }
    }
}
