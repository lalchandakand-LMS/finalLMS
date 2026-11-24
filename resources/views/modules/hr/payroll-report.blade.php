@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-2xl font-bold">Monthly Payroll Report</h1><p class="text-sm text-gray-600">Payroll totals and downloadable, filterable payroll ledger.</p></div>
        <a href="{{ route(request()->routeIs('branch.*') ? 'branch.hr.index' : 'admin.hr.index') }}" class="rounded border px-3 py-2 text-sm">Back to HR</a>
    </div>
    <form method="GET" class="flex flex-wrap items-end gap-3 rounded border bg-white p-4">
        <label class="text-sm">Payroll month<input type="month" name="month" value="{{ $month }}" class="mt-1 block rounded border-gray-300"></label>
        <label class="text-sm">Status<select name="status" class="mt-1 block rounded border-gray-300"><option value="">All statuses</option><option value="pending" @selected($status === 'pending')>Pending</option><option value="paid" @selected($status === 'paid')>Paid</option></select></label>
        @if(request()->routeIs('admin.*'))<label class="text-sm">Branch<select name="branch_id" class="mt-1 block rounded border-gray-300"><option value="">All branches</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) $selectedBranch === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</select></label>@endif
        <button class="rounded bg-blue-700 px-4 py-2 text-white">Apply filters</button>
        <a href="{{ route(request()->routeIs('branch.*') ? 'branch.hr.payroll-reports.export' : 'admin.hr.payroll-reports.export', request()->query()) }}" class="rounded bg-emerald-700 px-4 py-2 text-white">Export CSV</a>
    </form>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([['Payroll records',$totals['records']],['Gross salary','৳'.number_format($totals['gross'],2)],['Deductions','৳'.number_format($totals['deductions'],2)],['Net payable','৳'.number_format($totals['net'],2)]] as [$label,$value])
            <div class="rounded border bg-white p-4"><p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-1 text-xl font-semibold">{{ $value }}</p></div>
        @endforeach
    </div>
    <div class="flex flex-wrap gap-4 text-sm"><span>Paid: <strong>{{ $totals['paid'] }}</strong></span><span>Pending: <strong>{{ $totals['pending'] }}</strong></span><span>Bonus: <strong>৳{{ number_format($totals['bonus'],2) }}</strong></span></div>
    <div class="overflow-x-auto rounded border bg-white">
        <table class="min-w-full text-sm"><thead class="bg-gray-50"><tr><th class="p-3 text-left">Employee</th><th class="p-3">Month</th><th class="p-3">Gross</th><th class="p-3">Bonus</th><th class="p-3">Deductions</th><th class="p-3">Net Pay</th><th class="p-3">Status</th><th class="p-3">Payslip</th></tr></thead>
        <tbody>@forelse($payrolls as $pay)<tr class="border-t"><td class="p-3">{{ $pay->staff?->full_name ?? 'Staff #'.$pay->staff_profile_id }}<small class="block text-gray-500">{{ $pay->staff?->employee_code }}</small></td><td class="p-3">{{ $pay->payroll_month }}</td><td class="p-3">৳{{ number_format((float)$pay->gross_salary,2) }}</td><td class="p-3">৳{{ number_format((float)$pay->bonus,2) }}</td><td class="p-3">৳{{ number_format((float)$pay->deductions,2) }}</td><td class="p-3 font-semibold">৳{{ number_format((float)$pay->net_paid,2) }}</td><td class="p-3">{{ ucfirst($pay->status) }}</td><td class="p-3"><a target="_blank" rel="noopener" class="text-blue-700 underline" href="{{ route(request()->routeIs('branch.*') ? 'branch.hr.payrolls.slip' : 'admin.hr.payrolls.slip', $pay->id) }}">View slip</a></td></tr>@empty<tr><td colspan="8" class="p-5 text-center">No payroll records for this filter.</td></tr>@endforelse</tbody></table>
        <div class="p-3">{{ $payrolls->links() }}</div>
    </div>
</div>
@endsection
