@extends('layouts.app')

@section('title', 'Expenses | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ $isAccountant ? 'Branch expenses' : 'Organization expenses' }}</span>
            <h1 style="margin:12px 0 0;">Expense management</h1>
            <p class="muted">Record and review operating costs. Expenses are included in the finance report net balance.</p>
        </div>
    </div>

    <div class="dash-summary" style="grid-template-columns:repeat(2,minmax(0,1fr));">
        <div class="summary-box"><span class="muted">Filtered expenses</span><strong>৳{{ number_format($total, 2) }}</strong><small>{{ $expenses->total() }} records</small></div>
        <div class="summary-box"><span class="muted">Scope</span><strong>{{ $isAccountant ? 'My branch' : 'All branches' }}</strong><small>Branch restrictions applied on the server</small></div>
    </div>

    <section class="panel" style="margin-bottom:20px;">
        <h2>Record a new expense</h2>
        <form method="POST" action="{{ route($isAccountant ? 'accountant.expenses.store' : 'admin.expenses.store') }}" class="grid grid-2">
            @csrf
            <div><label for="expense-title">Expense title</label><input id="expense-title" name="title" maxlength="160" required value="{{ old('title') }}"></div>
            <div><label for="expense-category">Category</label><input id="expense-category" name="category" maxlength="80" required placeholder="Rent, salary, utilities..." value="{{ old('category') }}"></div>
            <div><label for="expense-amount">Amount (৳)</label><input id="expense-amount" type="number" name="amount" min="0.01" step="0.01" required value="{{ old('amount') }}"></div>
            <div><label for="expense-date">Expense date</label><input id="expense-date" type="date" name="expense_date" required value="{{ old('expense_date', now()->toDateString()) }}"></div>
            <div><label for="expense-method">Payment method</label><select id="expense-method" name="payment_method" required>@foreach(['cash','bkash','nagad','bank','card','other'] as $method)<option value="{{ $method }}">{{ ucfirst($method) }}</option>@endforeach</select></div>
            <div><label for="expense-reference">Reference (optional)</label><input id="expense-reference" name="reference" maxlength="120" value="{{ old('reference') }}"></div>
            @if(!$isAccountant)
                <div><label for="expense-branch">Branch</label><select id="expense-branch" name="branch_id"><option value="">Head office / Unassigned</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
            @endif
            <div class="grid" style="grid-column:1/-1;"><label for="expense-description">Description (optional)</label><textarea id="expense-description" name="description" rows="3">{{ old('description') }}</textarea></div>
            <div><button class="btn primary" type="submit">Save expense</button></div>
        </form>
        @if($errors->any())<div class="flash error" style="margin-top:14px;">{{ $errors->first() }}</div>@endif
    </section>

    <section class="panel" style="margin-bottom:20px;">
        <h2>Filter expenses</h2>
        <form method="GET" action="{{ route($isAccountant ? 'accountant.expenses.index' : 'admin.expenses.index') }}" class="grid grid-3">
            <div><label for="date_from">From</label><input id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
            <div><label for="date_to">To</label><input id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
            <div><label for="category">Category</label><select id="category" name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>@endforeach</select></div>
            <div><button class="btn primary" type="submit">Apply filters</button> <a class="btn secondary" href="{{ route($isAccountant ? 'accountant.expenses.index' : 'admin.expenses.index') }}">Clear</a></div>
        </form>
    </section>

    <section class="panel">
        <h2>Expense ledger</h2>
        @if($expenses->isEmpty())
            <p class="muted">No expenses found.</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;text-align:left;min-width:850px;">
                    <thead><tr><th style="padding:10px;">Date</th><th style="padding:10px;">Expense</th><th style="padding:10px;">Category</th><th style="padding:10px;">Branch</th><th style="padding:10px;">Method / Reference</th><th style="padding:10px;text-align:right;">Amount</th><th style="padding:10px;">Action</th></tr></thead>
                    <tbody>@foreach($expenses as $expense)
                        <tr>
                            <td style="padding:10px;border-top:1px solid var(--border);">{{ $expense->expense_date->format('d M Y') }}</td>
                            <td style="padding:10px;border-top:1px solid var(--border);"><strong>{{ $expense->title }}</strong><br><small class="muted">{{ $expense->description }}</small></td>
                            <td style="padding:10px;border-top:1px solid var(--border);">{{ $expense->category }}</td>
                            <td style="padding:10px;border-top:1px solid var(--border);">{{ $expense->branch?->name ?? 'Head office' }}</td>
                            <td style="padding:10px;border-top:1px solid var(--border);">{{ ucfirst($expense->payment_method) }}<br><small class="muted">{{ $expense->reference ?: '—' }}</small></td>
                            <td style="padding:10px;border-top:1px solid var(--border);text-align:right;">৳{{ number_format((float)$expense->amount, 2) }}</td>
                            <td style="padding:10px;border-top:1px solid var(--border);">
                                <details>
                                    <summary style="cursor:pointer;margin-bottom:8px;">Edit</summary>
                                    <form method="POST" action="{{ route($isAccountant ? 'accountant.expenses.update' : 'admin.expenses.update', $expense) }}" class="grid" style="min-width:260px;">
                                        @csrf @method('PUT')
                                        <label>Title<input name="title" maxlength="160" required value="{{ $expense->title }}"></label>
                                        <label>Category<input name="category" maxlength="80" required value="{{ $expense->category }}"></label>
                                        <label>Amount<input type="number" name="amount" min="0.01" step="0.01" required value="{{ $expense->amount }}"></label>
                                        <label>Date<input type="date" name="expense_date" required value="{{ $expense->expense_date->toDateString() }}"></label>
                                        <label>Method<select name="payment_method">@foreach(['cash','bkash','nagad','bank','card','other'] as $method)<option value="{{ $method }}" @selected($expense->payment_method === $method)>{{ ucfirst($method) }}</option>@endforeach</select></label>
                                        <label>Reference<input name="reference" maxlength="120" value="{{ $expense->reference }}"></label>
                                        <label>Description<textarea name="description" rows="2">{{ $expense->description }}</textarea></label>
                                        <button class="btn primary" type="submit">Update</button>
                                    </form>
                                </details>
                                <form method="POST" action="{{ route($isAccountant ? 'accountant.expenses.destroy' : 'admin.expenses.destroy', $expense) }}" onsubmit="return confirm('Delete this expense record?')">@csrf @method('DELETE')<button class="btn secondary" type="submit">Delete</button></form>
                            </td>
                        </tr>
                    @endforeach</tbody>
                </table>
            </div>
            <div style="margin-top:18px;">{{ $expenses->links() }}</div>
        @endif
    </section>
@endsection
