@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-2xl font-bold">Staff Leave Calendar</h1><p class="text-sm text-gray-600">Monthly leave schedule, approval status and detailed leave report.</p></div>
        <a href="{{ route(request()->routeIs('branch.*') ? 'branch.hr.index' : 'admin.hr.index') }}" class="rounded border px-3 py-2 text-sm">Back to HR</a>
    </div>
    <form method="GET" class="flex flex-wrap items-end gap-3 rounded border bg-white p-4">
        <label class="text-sm">Month<input type="month" name="month" value="{{ $month }}" class="mt-1 block rounded border-gray-300"></label>
        <label class="text-sm">Status<select name="status" class="mt-1 block rounded border-gray-300"><option value="">All statuses</option>@foreach(['pending','approved','rejected'] as $value)<option value="{{ $value }}" @selected($status === $value)>{{ ucfirst($value) }}</option>@endforeach</select></label>
        @if(request()->routeIs('admin.*'))<label class="text-sm">Branch<select name="branch_id" class="mt-1 block rounded border-gray-300"><option value="">All branches</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) $selectedBranch === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</select></label>@endif
        <button class="rounded bg-blue-700 px-4 py-2 text-white">Apply filters</button>
        <a href="{{ route(request()->routeIs('branch.*') ? 'branch.hr.leave-reports.export' : 'admin.hr.leave-reports.export', request()->query()) }}" class="rounded bg-emerald-700 px-4 py-2 text-white">Export detailed CSV</a>
    </form>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([['Requests',$totals['requests']],['Pending',$totals['pending']],['Approved',$totals['approved']],['Rejected',$totals['rejected']]] as [$label,$value])
            <div class="rounded border bg-white p-4"><p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-1 text-xl font-semibold">{{ $value }}</p></div>
        @endforeach
    </div>
    <div class="grid grid-cols-7 gap-1 sm:gap-2">
        @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $weekday)<div class="py-2 text-center text-xs font-semibold text-gray-500">{{ $weekday }}</div>@endforeach
        @for($blank=1; $blank < $calendarDays[0]['date']->dayOfWeekIso; $blank++)<div class="min-h-24 rounded bg-gray-50"></div>@endfor
        @foreach($calendarDays as $day)
            <div class="min-h-24 overflow-hidden rounded border bg-white p-1 sm:p-2">
                <div class="mb-1 text-xs font-semibold">{{ $day['date']->day }}</div>
                @foreach($day['leaves'] as $leave)
                    <div class="mb-1 rounded px-1 py-1 text-[10px] leading-tight sm:text-xs {{ $leave->status === 'approved' ? 'bg-emerald-100 text-emerald-900' : ($leave->status === 'rejected' ? 'bg-red-100 text-red-900' : 'bg-amber-100 text-amber-900') }}">
                        <span class="font-semibold">{{ $leave->staff?->full_name ?? 'Staff' }}</span><br>{{ ucfirst($leave->leave_type) }} · {{ ucfirst($leave->status) }}
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
    <div class="overflow-x-auto rounded border bg-white">
        <div class="border-b p-4 font-semibold">Detailed leave register</div>
        <table class="min-w-full text-sm"><thead class="bg-gray-50"><tr><th class="p-3 text-left">Employee</th><th class="p-3">Type</th><th class="p-3">Dates</th><th class="p-3">Days</th><th class="p-3">Status</th><th class="p-3 text-left">Reason / Review</th></tr></thead>
        <tbody>@forelse($leaves as $leave)<tr class="border-t"><td class="p-3">{{ $leave->staff?->full_name }}<small class="block text-gray-500">{{ $leave->staff?->employee_code }}</small></td><td class="p-3">{{ ucfirst($leave->leave_type) }}</td><td class="p-3">{{ $leave->starts_on->format('d M Y') }} – {{ $leave->ends_on->format('d M Y') }}</td><td class="p-3">{{ $leave->starts_on->diffInDays($leave->ends_on) + 1 }}</td><td class="p-3">{{ ucfirst($leave->status) }}</td><td class="p-3">{{ $leave->reason }}<small class="block text-gray-500">{{ $leave->review_note ?? '—' }}</small></td></tr>@empty<tr><td colspan="6" class="p-5 text-center">No leave records for this month.</td></tr>@endforelse</tbody></table>
    </div>
    @if($leaves->count() >= 2000)<p class="text-xs text-amber-700">Calendar display is limited to 2,000 leave records for this month. Use the export for the full filtered report.</p>@endif
</div>
@endsection
