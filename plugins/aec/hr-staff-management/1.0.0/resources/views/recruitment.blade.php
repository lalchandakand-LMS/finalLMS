@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
    <header><h1 class="text-2xl font-bold">Recruitment Management</h1><p class="text-sm text-gray-600">Publish vacancies, securely review applications and convert successful candidates into staff profiles.</p></header>
    @if(session('success'))<p class="rounded bg-green-50 p-3 text-green-800">{{ session('success') }}</p>@endif
    @if($errors->any())<p class="rounded bg-red-50 p-3 text-red-800">{{ $errors->first() }}</p>@endif
    <section class="rounded border bg-white p-5">
        <h2 class="mb-3 font-semibold">Create job opening</h2>
        <form method="POST" action="{{ route('admin.hr.recruitment.openings.store') }}" class="grid gap-3 sm:grid-cols-2">@csrf
            <input required name="title" maxlength="180" placeholder="Job title" class="rounded border-gray-300">
            <input required name="department" maxlength="100" placeholder="Department" class="rounded border-gray-300">
            <input required name="designation" maxlength="120" placeholder="Designation" class="rounded border-gray-300">
            <select required name="employment_type" class="rounded border-gray-300"><option value="full_time">Full time</option><option value="part_time">Part time</option><option value="contract">Contract</option><option value="temporary">Temporary</option></select>
            <input name="location" maxlength="160" placeholder="Location / Branch" class="rounded border-gray-300">
            <label class="text-sm">Application deadline<input type="datetime-local" name="closes_at" min="{{ now()->format('Y-m-d\TH:i') }}" class="mt-1 w-full rounded border-gray-300"></label>
            <textarea required name="description" maxlength="12000" rows="5" placeholder="Responsibilities, requirements and benefits" class="rounded border-gray-300 sm:col-span-2"></textarea>
            <select required name="status" class="rounded border-gray-300"><option value="draft">Save as draft</option><option value="published">Publish immediately</option></select>
            <button class="rounded bg-blue-700 px-4 py-2 text-white">Create opening</button>
        </form>
    </section>
    <section class="overflow-x-auto rounded border bg-white">
        <h2 class="border-b p-4 font-semibold">Job openings</h2>
        <table class="min-w-full text-sm"><thead class="bg-gray-50"><tr><th class="p-3 text-left">Position</th><th class="p-3">Applications</th><th class="p-3">Deadline</th><th class="p-3">Status</th><th class="p-3">Action</th></tr></thead><tbody>
        @forelse($openings as $opening)<tr class="border-t"><td class="p-3">{{ $opening->title }}<small class="block text-gray-500">{{ $opening->department }} · {{ $opening->designation }}</small></td><td class="p-3 text-center">{{ $opening->applications_count }}</td><td class="p-3">{{ $opening->closes_at?->format('d M Y') ?? '—' }}</td><td class="p-3">{{ ucfirst($opening->status) }}</td><td class="p-3"><form method="POST" action="{{ route('admin.hr.recruitment.openings.status',$opening) }}" class="flex gap-2">@csrf @method('PATCH')<select name="status" class="rounded border-gray-300 text-xs"><option value="draft">Draft</option><option value="published">Published</option><option value="closed">Closed</option></select><button class="rounded bg-gray-800 px-2 py-1 text-xs text-white">Update</button></form></td></tr>@empty<tr><td colspan="5" class="p-4 text-center">No job openings yet.</td></tr>@endforelse</tbody></table>{{ $openings->links() }}
    </section>
    <section class="overflow-x-auto rounded border bg-white">
        <h2 class="border-b p-4 font-semibold">Applicant pipeline</h2>
        <table class="min-w-full text-sm"><thead class="bg-gray-50"><tr><th class="p-3 text-left">Applicant</th><th class="p-3">Position</th><th class="p-3">Resume</th><th class="p-3">Current status</th><th class="p-3">Review / hire</th></tr></thead><tbody>
        @forelse($applications as $application)<tr class="border-t align-top"><td class="p-3">{{ $application->applicant_name }}<small class="block">{{ $application->email }}</small><small class="block">{{ $application->phone }}</small><small class="mt-1 block text-gray-500">{{ $application->created_at->format('d M Y') }}</small></td><td class="p-3">{{ $application->opening?->title }}</td><td class="p-3"><a class="text-blue-700 underline" href="{{ route('admin.hr.recruitment.applications.resume',$application) }}">Private resume</a></td><td class="p-3">{{ ucfirst($application->status) }}@if($application->review_notes)<small class="block text-gray-500">{{ $application->review_notes }}</small>@endif</td><td class="p-3">
            @if(!in_array($application->status,['rejected','hired'],true))
            <form method="POST" action="{{ route('admin.hr.recruitment.applications.review',$application) }}" class="min-w-64 space-y-2">@csrf
                <select required name="status" class="w-full rounded border-gray-300 text-xs"><option value="shortlisted">Shortlist</option><option value="interview">Interview</option><option value="approved">Approve</option><option value="rejected">Reject</option><option value="hired">Hire & create staff profile</option></select>
                <textarea name="review_notes" maxlength="3000" placeholder="Review notes" class="w-full rounded border-gray-300 text-xs"></textarea>
                <input name="employee_code" maxlength="40" placeholder="Employee code (required to hire)" class="w-full rounded border-gray-300 text-xs">
                <input type="number" name="base_salary" min="0" step="0.01" placeholder="Monthly base salary (BDT)" class="w-full rounded border-gray-300 text-xs">
                <select name="branch_id" class="w-full rounded border-gray-300 text-xs"><option value="">Head office / no branch</option>@foreach(\App\Models\Branch::query()->where('is_active',true)->orderBy('name')->get() as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
                <button class="rounded bg-emerald-700 px-3 py-1.5 text-xs text-white">Save review</button>
            </form>
            @else <span class="text-gray-500">Closed</span> @endif
        </td></tr>@empty<tr><td colspan="5" class="p-4 text-center">No applications received.</td></tr>@endforelse</tbody></table>{{ $applications->links() }}
    </section>
</div>
@endsection
