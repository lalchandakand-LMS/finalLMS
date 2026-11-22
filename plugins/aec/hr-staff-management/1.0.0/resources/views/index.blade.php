@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
<div><h1 class="text-2xl font-bold">HR & Staff Management</h1><p class="text-sm text-gray-600">Staff profiles and monthly payroll ledger.</p></div>
@if(session('success'))<p class="rounded bg-green-50 p-3 text-green-800">{{ session('success') }}</p>@endif
@if($errors->any())<p class="rounded bg-red-50 p-3 text-red-800">{{ $errors->first() }}</p>@endif
<div class="grid gap-6 lg:grid-cols-2">
<section class="rounded border bg-white p-5"><h2 class="mb-3 font-semibold">Register staff</h2><form method="POST" action="{{ route(request()->routeIs('branch.*')?'branch.hr.staff.store':'admin.hr.staff.store') }}" class="grid gap-3 sm:grid-cols-2">@csrf
<input required name="employee_code" maxlength="40" placeholder="Employee code" class="rounded border-gray-300">
<input required name="full_name" maxlength="160" placeholder="Full name" class="rounded border-gray-300">
<input required name="designation" maxlength="120" placeholder="Designation" class="rounded border-gray-300">
<input required name="department" maxlength="100" value="Academic" placeholder="Department" class="rounded border-gray-300">
<input name="phone" maxlength="40" placeholder="Phone" class="rounded border-gray-300">
<input name="email" type="email" maxlength="160" placeholder="Email" class="rounded border-gray-300">
<input required type="number" min="0" step="0.01" name="base_salary" placeholder="Base salary (BDT)" class="rounded border-gray-300">
<select name="status" class="rounded border-gray-300"><option value="active">Active</option><option value="on_leave">On leave</option><option value="inactive">Inactive</option></select>
@if(!request()->routeIs('branch.*'))<select name="branch_id" class="rounded border-gray-300"><option value="">No branch</option>@foreach(\App\Models\Branch::query()->where('is_active',true)->orderBy('name')->get() as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>@endif
<button class="rounded bg-blue-700 px-4 py-2 text-white sm:col-span-2">Save staff profile</button></form></section>
<section class="rounded border bg-white p-5"><h2 class="mb-3 font-semibold">Prepare monthly payroll</h2><form method="POST" action="{{ route(request()->routeIs('branch.*')?'branch.hr.payrolls.store':'admin.hr.payrolls.store') }}" class="grid gap-3">@csrf
<select required name="staff_profile_id" class="rounded border-gray-300"><option value="">Select staff member</option>@foreach($staff as $member)<option value="{{ $member->id }}">{{ $member->full_name }} — {{ $member->employee_code }} (৳{{ $member->base_salary }})</option>@endforeach</select>
<input required type="month" name="payroll_month" value="{{ now()->format('Y-m') }}" class="rounded border-gray-300">
<div class="grid grid-cols-2 gap-3"><input type="number" min="0" step="0.01" name="bonus" value="0" placeholder="Bonus" class="rounded border-gray-300"><input type="number" min="0" step="0.01" name="deductions" value="0" placeholder="Deductions" class="rounded border-gray-300"></div>
<button class="rounded bg-emerald-700 px-4 py-2 text-white">Record pending payroll</button></form><p class="mt-2 text-xs text-gray-500">Only a central administrator can confirm a payment reference and mark payroll as paid.</p></section>
</div>
<section class="overflow-x-auto rounded border bg-white"><div class="border-b p-4 font-semibold">Staff directory</div><table class="min-w-full text-sm"><thead class="bg-gray-50"><tr><th class="p-3 text-left">Employee</th><th class="p-3 text-left">Department / Designation</th><th class="p-3 text-left">Branch</th><th class="p-3 text-left">Salary</th><th class="p-3 text-left">Status</th></tr></thead><tbody>@forelse($staff as $member)<tr class="border-t"><td class="p-3">{{ $member->full_name }}<small class="block text-gray-500">{{ $member->employee_code }}</small></td><td class="p-3">{{ $member->department }} / {{ $member->designation }}</td><td class="p-3">{{ $member->branch_id ?? '—' }}</td><td class="p-3">৳{{ $member->base_salary }}</td><td class="p-3">{{ $member->status }}</td></tr>@empty<tr><td colspan="5" class="p-5 text-center">No staff profiles.</td></tr>@endforelse</tbody></table>{{ $staff->links() }}</section>
<section class="overflow-x-auto rounded border bg-white"><div class="border-b p-4 font-semibold">Recent payroll ledger</div><table class="min-w-full text-sm"><thead class="bg-gray-50"><tr><th class="p-3 text-left">Staff</th><th class="p-3 text-left">Month</th><th class="p-3 text-left">Gross / Bonus / Deduction</th><th class="p-3 text-left">Net</th><th class="p-3 text-left">Status / Disbursement</th></tr></thead><tbody>@forelse($payrolls as $pay)<tr class="border-t"><td class="p-3">{{ $pay->staff?->full_name ?? 'Staff #'.$pay->staff_profile_id }}</td><td class="p-3">{{ $pay->payroll_month }}</td><td class="p-3">৳{{ $pay->gross_salary }} / ৳{{ $pay->bonus }} / ৳{{ $pay->deductions }}</td><td class="p-3 font-semibold">৳{{ $pay->net_paid }}</td><td class="p-3">{{ $pay->status }} @if($pay->status==='paid')<small class="block text-gray-500">{{ $pay->paid_at?->format('d M Y') }} · {{ $pay->payment_reference }}</small>@elseif(request()->routeIs('admin.*'))<form method="POST" action="{{ route('admin.hr.payroll.mark-paid',$pay->id) }}" class="mt-2 flex gap-2">@csrf<input required maxlength="120" name="payment_reference" placeholder="Payment reference" class="min-w-0 rounded border-gray-300 text-xs"><button class="rounded bg-emerald-700 px-2 py-1 text-xs text-white">Mark paid</button></form>@endif</td></tr>@empty<tr><td colspan="5" class="p-5 text-center">No payroll entries.</td></tr>@endforelse</tbody></table></section>
</div>
@endsection
