@extends('layouts.app')

@section('content')
@php($branchPortal = request()->routeIs('branch.*'))
@php($routePrefix = $branchPortal ? 'branch.admissions.' : 'admin.admissions.')
@php($statusLabels = ['pending'=>'Pending','screening'=>'Screening','shortlisted'=>'Shortlisted','waitlisted'=>'Waitlisted','approved'=>'Approved','rejected'=>'Rejected'])
<div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
    <header class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-widest text-blue-700">Alom's English Center</p><h1 class="text-2xl font-bold">Admission Reports & Analytics</h1><p class="text-sm text-gray-600">Application volume, decisions, document verification and student conversion.</p></div><a href="{{ route($routePrefix.'index') }}" class="rounded-lg border bg-white px-4 py-2 text-sm">← Admission workbench</a></header>
    <form method="GET" class="grid gap-3 rounded-xl border bg-white p-4 sm:grid-cols-2 lg:grid-cols-5">
        <label class="grid gap-1 text-xs">From date<input type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}" class="rounded border-gray-300"></label>
        <label class="grid gap-1 text-xs">To date<input type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}" class="rounded border-gray-300"></label>
        <label class="grid gap-1 text-xs">Application status<select name="status" class="rounded border-gray-300"><option value="">All</option>@foreach($statusLabels as $key=>$label)<option value="{{ $key }}" @selected(($filters['status'] ?? '')===$key)>{{ $label }}</option>@endforeach</select></label>
        <label class="grid gap-1 text-xs">Application source<select name="source" class="rounded border-gray-300"><option value="">All</option><option value="website" @selected(($filters['source'] ?? '')==='website')>Online</option><option value="walk_in" @selected(($filters['source'] ?? '')==='walk_in')>Walk-in</option></select></label>
        <label class="grid gap-1 text-xs">Course contains<input name="course" value="{{ $filters['course'] ?? '' }}" maxlength="160" class="rounded border-gray-300"></label>
        @unless($branchPortal)<label class="grid gap-1 text-xs">Branch<select name="branch_id" class="rounded border-gray-300"><option value="">All branches</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string)($filters['branch_id'] ?? '')===(string)$branch->id)>{{ $branch->name }}</option>@endforeach</select></label>@endunless
        <div class="flex items-end gap-2"><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Run report</button><a href="{{ route($routePrefix.'reports') }}" class="rounded-lg border px-4 py-2 text-sm">Reset</a></div>
        <div class="flex items-end"><a href="{{ route($routePrefix.'reports.export', request()->query()) }}" class="w-full rounded-lg border border-green-700 px-4 py-2 text-center text-sm font-semibold text-green-800">Export filtered CSV ↓</a></div>
    </form>
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-xl border bg-white p-5"><p class="text-xs uppercase text-gray-500">Total applications</p><p class="mt-1 text-3xl font-bold">{{ number_format($total) }}</p></div>
        <div class="rounded-xl border bg-white p-5"><p class="text-xs uppercase text-gray-500">Approved</p><p class="mt-1 text-3xl font-bold">{{ number_format($statusCounts['approved'] ?? 0) }}</p></div>
        <div class="rounded-xl border bg-white p-5"><p class="text-xs uppercase text-gray-500">Converted students</p><p class="mt-1 text-3xl font-bold">{{ number_format($converted) }}</p></div>
        <div class="rounded-xl border bg-white p-5"><p class="text-xs uppercase text-gray-500">Conversion rate</p><p class="mt-1 text-3xl font-bold">{{ number_format($conversionRate,1) }}%</p></div>
    </section>
    <section class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-xl border bg-white p-5"><h2 class="font-bold">Application pipeline</h2><div class="mt-3 space-y-3">@foreach($statusLabels as $key=>$label)@php($count=(int)($statusCounts[$key] ?? 0))<div><div class="flex justify-between text-sm"><span>{{ $label }}</span><strong>{{ number_format($count) }}</strong></div><div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-blue-600" style="width:{{ $total ? min(100,($count/$total)*100) : 0 }}%"></div></div></div>@endforeach</div></div>
        <div class="rounded-xl border bg-white p-5"><h2 class="font-bold">Application sources</h2><div class="mt-3 space-y-3">@forelse($sourceCounts as $source=>$count)<div class="flex justify-between border-b py-2 text-sm"><span>{{ $source==='website'?'Online applications':'Walk-in applications' }}</span><strong>{{ number_format($count) }}</strong></div>@empty<p class="text-sm text-gray-500">No data for this period.</p>@endforelse</div></div>
        <div class="rounded-xl border bg-white p-5"><h2 class="font-bold">Document verification</h2><div class="mt-3 space-y-3">@foreach(['missing'=>'Missing','pending'=>'Pending review','verified'=>'Verified','revision_required'=>'Correction required'] as $key=>$label)<div class="flex justify-between border-b py-2 text-sm"><span>{{ $label }}</span><strong>{{ number_format($documentCounts[$key] ?? 0) }}</strong></div>@endforeach</div></div>
    </section>
    <section class="rounded-xl border bg-white p-5"><h2 class="font-bold">Monthly application trend</h2><div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-gray-50"><tr><th class="p-3 text-left">Month</th><th class="p-3 text-right">Applications received</th></tr></thead><tbody>@forelse($monthly as $row)<tr class="border-t"><td class="p-3">{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $row->month)->format('F Y') }}</td><td class="p-3 text-right font-semibold">{{ number_format($row->total) }}</td></tr>@empty<tr><td colspan="2" class="p-4 text-center text-gray-500">No applications in the selected period.</td></tr>@endforelse</tbody></table></div></section>
    <p class="text-xs text-gray-500">Reports respect branch-manager data scope. CSV exports contain applicant contact details and are available only to authorized admission staff.</p>
</div>
@endsection
