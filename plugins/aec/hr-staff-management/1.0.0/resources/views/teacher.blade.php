@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-5xl space-y-6 p-4 sm:p-6">
    <header><h1 class="text-2xl font-bold">My HR & Leave</h1><p class="text-sm text-gray-600">Your employment details, attendance history and leave applications.</p></header>
    @if(session('success'))<p class="rounded bg-green-50 p-3 text-green-800">{{ session('success') }}</p>@endif
    @if(isset($errors) && $errors->any())<p class="rounded bg-red-50 p-3 text-red-800">{{ $errors->first() }}</p>@endif
    <section class="rounded border bg-white p-5">
        <h2 class="font-semibold">My staff profile</h2>
        <dl class="mt-3 grid gap-3 sm:grid-cols-2">
            <div><dt class="text-sm text-gray-500">Employee code</dt><dd>{{ $profile->employee_code }}</dd></div>
            <div><dt class="text-sm text-gray-500">Name</dt><dd>{{ $profile->full_name }}</dd></div>
            <div><dt class="text-sm text-gray-500">Department / Designation</dt><dd>{{ $profile->department }} / {{ $profile->designation }}</dd></div>
            <div><dt class="text-sm text-gray-500">Employment status</dt><dd>{{ ucfirst(str_replace('_',' ',$profile->status)) }}</dd></div>
        </dl>
    </section>
    <section class="rounded border bg-white p-5">
        <h2 class="mb-3 font-semibold">Apply for leave</h2>
        <form method="POST" action="{{ route('teacher.hr.leave.store') }}" class="grid gap-3 sm:grid-cols-2">@csrf
            <select required name="leave_type" class="rounded border-gray-300"><option value="annual">Annual</option><option value="sick">Sick</option><option value="casual">Casual</option><option value="unpaid">Unpaid</option><option value="other">Other</option></select>
            <input required type="date" name="starts_on" min="{{ now()->toDateString() }}" class="rounded border-gray-300">
            <input required type="date" name="ends_on" min="{{ now()->toDateString() }}" class="rounded border-gray-300">
            <textarea required name="reason" maxlength="2000" placeholder="Reason for leave" class="rounded border-gray-300 sm:col-span-2"></textarea>
            <button class="rounded bg-blue-700 px-4 py-2 text-white sm:col-span-2">Submit leave request</button>
        </form>
    </section>
    <section class="overflow-x-auto rounded border bg-white"><h2 class="border-b p-4 font-semibold">My leave history</h2><table class="min-w-full text-sm"><thead class="bg-gray-50"><tr><th class="p-3 text-left">Type</th><th class="p-3">Dates</th><th class="p-3">Status</th><th class="p-3">Review note</th></tr></thead><tbody>@forelse($leaveRequests as $leave)<tr class="border-t"><td class="p-3">{{ ucfirst($leave->leave_type) }}</td><td class="p-3">{{ $leave->starts_on->format('d M Y') }} – {{ $leave->ends_on->format('d M Y') }}</td><td class="p-3">{{ ucfirst($leave->status) }}</td><td class="p-3">{{ $leave->review_note ?? '—' }}</td></tr>@empty<tr><td colspan="4" class="p-4 text-center">No leave applications yet.</td></tr>@endforelse</tbody></table></section>
    <section class="overflow-x-auto rounded border bg-white"><h2 class="border-b p-4 font-semibold">My attendance history</h2><table class="min-w-full text-sm"><thead class="bg-gray-50"><tr><th class="p-3 text-left">Date</th><th class="p-3">Status</th><th class="p-3">Check in</th><th class="p-3">Check out</th></tr></thead><tbody>@forelse($attendances as $row)<tr class="border-t"><td class="p-3">{{ $row->attendance_date->format('d M Y') }}</td><td class="p-3">{{ ucfirst(str_replace('_',' ',$row->status)) }}</td><td class="p-3">{{ $row->check_in_at ?? '—' }}</td><td class="p-3">{{ $row->check_out_at ?? '—' }}</td></tr>@empty<tr><td colspan="4" class="p-4 text-center">No attendance records yet.</td></tr>@endforelse</tbody></table></section>
</div>
@endsection
