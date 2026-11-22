@extends('layouts.app')

@section('content')
@php
    $branchPortal = request()->routeIs('branch.*');
    $routePrefix = $branchPortal ? 'branch.admissions.' : 'admin.admissions.';
    $documentLabels = ['photo' => 'Photo', 'birth_certificate' => 'Birth certificate', 'nid' => 'NID / identity', 'previous_certificate' => 'Previous certificate', 'other' => 'Other'];
    $statusLabels = ['pending' => 'Pending', 'screening' => 'Screening', 'shortlisted' => 'Shortlisted', 'waitlisted' => 'Waitlisted', 'approved' => 'Approved', 'rejected' => 'Rejected'];
@endphp
<div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div><p class="text-xs font-bold uppercase tracking-widest text-blue-700">Alom's English Center · Admissions</p><h1 class="text-2xl font-bold">Admission Management</h1><p class="text-sm text-gray-600">Online and walk-in applications, document verification, screening, decisions and student conversion.</p></div>
        <a href="{{ route($routePrefix.'reports', request()->query()) }}" class="rounded-lg border bg-white px-4 py-2 text-sm font-semibold">Admission reports →</a>
    </header>
    @if(session('success'))<p class="rounded-lg bg-green-50 p-3 text-green-800">{{ session('success') }}</p>@endif
    @if(isset($errors) && $errors->any())<div class="rounded-lg bg-red-50 p-3 text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach($statuses as $status)<div class="rounded-xl border bg-white p-4"><p class="text-xs uppercase text-gray-500">{{ $statusLabels[$status] ?? $status }}</p><p class="mt-1 text-2xl font-bold">{{ $statusCounts[$status] ?? 0 }}</p></div>@endforeach
    </section>

    <details class="rounded-xl border bg-white p-4">
        <summary class="cursor-pointer font-semibold">＋ Register walk-in / offline admission</summary>
        <form method="POST" action="{{ route($routePrefix.'store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf
            <label class="grid gap-1 text-sm">Applicant name *<input required maxlength="160" name="applicant_name" value="{{ old('applicant_name') }}" class="rounded border-gray-300"></label>
            <label class="grid gap-1 text-sm">Email *<input required type="email" maxlength="160" name="email" value="{{ old('email') }}" class="rounded border-gray-300"></label>
            <label class="grid gap-1 text-sm">Phone *<input required maxlength="40" name="phone" value="{{ old('phone') }}" class="rounded border-gray-300"></label>
            <label class="grid gap-1 text-sm">Guardian phone<input maxlength="40" name="guardian_phone" class="rounded border-gray-300"></label>
            <label class="grid gap-1 text-sm">Desired course *<input required maxlength="160" name="desired_course" class="rounded border-gray-300"></label>
            <label class="grid gap-1 text-sm">Preferred batch<input maxlength="80" name="preferred_batch" class="rounded border-gray-300"></label>
            @unless($branchPortal)<label class="grid gap-1 text-sm">Branch<select name="branch_id" class="rounded border-gray-300"><option value="">Main / unassigned</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label>@endunless
            <label class="grid gap-1 text-sm">Previous education<input maxlength="160" name="previous_education" class="rounded border-gray-300"></label>
            <label class="grid gap-1 text-sm sm:col-span-2">Address<textarea name="address" rows="2" maxlength="2000" class="rounded border-gray-300"></textarea></label>
            <div class="sm:col-span-2"><button class="rounded-lg bg-blue-700 px-5 py-2 text-white">Create walk-in application</button></div>
        </form>
    </details>

    <form method="GET" class="grid gap-2 rounded-xl border bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
        <input name="q" value="{{ request('q') }}" maxlength="120" placeholder="Name, email, phone, ID or course" class="rounded-lg border-gray-300">
        <select name="status" class="rounded-lg border-gray-300"><option value="">All application statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ $statusLabels[$status] ?? $status }}</option>@endforeach</select>
        <select name="document_status" class="rounded-lg border-gray-300"><option value="">All document statuses</option>@foreach(['missing','pending','verified','revision_required'] as $docStatus)<option value="{{ $docStatus }}" @selected(request('document_status')===$docStatus)>{{ str($docStatus)->replace('_',' ')->title() }}</option>@endforeach</select>
        <select name="source" class="rounded-lg border-gray-300"><option value="">All sources</option><option value="website" @selected(request('source')==='website')>Online</option><option value="walk_in" @selected(request('source')==='walk_in')>Walk-in</option></select>
        @unless($branchPortal)<select name="branch_id" class="rounded-lg border-gray-300"><option value="">All branches</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string)request('branch_id')===(string)$branch->id)>{{ $branch->name }}</option>@endforeach</select>@endunless
        <input type="date" name="from_date" value="{{ request('from_date') }}" aria-label="From date" class="rounded-lg border-gray-300">
        <input type="date" name="to_date" value="{{ request('to_date') }}" aria-label="To date" class="rounded-lg border-gray-300">
        <input name="course" value="{{ request('course') }}" maxlength="160" placeholder="Course filter" class="rounded-lg border-gray-300">
        <div class="flex gap-2"><button class="rounded-lg bg-gray-900 px-4 py-2 text-white">Apply filters</button><a href="{{ route($routePrefix.'index') }}" class="rounded-lg border px-4 py-2">Reset</a></div>
    </form>

    <div class="space-y-4">
        @forelse($applications as $application)
            <article class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b bg-gray-50 p-4">
                    <div><div class="flex flex-wrap items-center gap-2"><h2 class="text-lg font-bold">{{ $application->applicant_name }}</h2><span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800">{{ $statusLabels[$application->status] ?? ucfirst($application->status) }}</span><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $application->document_verification_status==='verified'?'bg-green-100 text-green-800':($application->document_verification_status==='revision_required'?'bg-red-100 text-red-800':'bg-amber-100 text-amber-800') }}">Docs: {{ str($application->document_verification_status)->replace('_',' ')->title() }}</span></div><p class="text-xs text-gray-500">{{ $application->application_code }} · {{ $application->created_at?->format('d M Y, h:i A') }} · {{ $application->application_source==='website'?'Online':'Walk-in' }}</p></div>
                    <div class="text-right text-sm"><p class="font-semibold">{{ $application->desired_course }}</p><p class="text-gray-500">{{ $application->branch?->name ?? 'Main / unassigned branch' }}</p></div>
                </div>
                <div class="grid gap-5 p-4 lg:grid-cols-3">
                    <section class="space-y-2 text-sm">
                        <h3 class="font-bold">Applicant details</h3>
                        <p>{{ $application->email }}</p><p>{{ $application->phone }} @if($application->guardian_phone) · Guardian: {{ $application->guardian_phone }} @endif</p>
                        <p>Batch: {{ $application->preferred_batch ?: 'Not specified' }}</p><p>Education: {{ $application->previous_education ?: 'Not specified' }}</p><p>DOB: {{ $application->date_of_birth?->format('d M Y') ?? 'Not specified' }}</p>
                        @if($application->address)<p class="text-gray-600">{{ $application->address }}</p>@endif
                        @if($application->reviewer_notes)<div class="rounded-lg bg-amber-50 p-3"><strong>Latest reviewer note</strong><p>{{ $application->reviewer_notes }}</p></div>@endif
                        @if($application->assignee)<p class="text-xs text-gray-500">Assigned to: {{ $application->assignee->name }}</p>@endif
                    </section>
                    <section class="space-y-3">
                        <h3 class="font-bold">Application review</h3>
                        <form method="POST" action="{{ route($routePrefix.'review',$application->id) }}" class="grid gap-2">@csrf
                            <label class="grid gap-1 text-xs">Decision / stage<select name="status" class="rounded-lg border-gray-300 text-sm">@foreach($statuses as $status)<option value="{{ $status }}" @selected($application->status===$status)>{{ $statusLabels[$status] ?? ucfirst($status) }}</option>@endforeach</select></label>
                            <label class="grid gap-1 text-xs">Assign reviewer<select name="assigned_to" class="rounded-lg border-gray-300 text-sm"><option value="">No assignment change</option>@foreach($reviewers as $reviewer)<option value="{{ $reviewer->id }}" @selected((int)$application->assigned_to===(int)$reviewer->id)>{{ $reviewer->name }} · {{ str($reviewer->role)->replace('_',' ')->title() }}</option>@endforeach</select></label>
                            <label class="grid gap-1 text-xs">Interview schedule<input type="datetime-local" name="interview_at" value="{{ $application->interview_at?->format('Y-m-d\TH:i') }}" class="rounded-lg border-gray-300 text-sm"></label>
                            <label class="grid gap-1 text-xs">Internal review note<textarea name="reviewer_notes" rows="3" maxlength="2000" placeholder="Required when rejecting; never shown publicly." class="rounded-lg border-gray-300 text-sm">{{ $application->reviewer_notes }}</textarea></label>
                            <button class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white">Save review decision</button>
                        </form>
                        @if($application->status==='approved' && !$application->converted_student_id && !$branchPortal)
                            <form method="POST" action="{{ route('admin.admissions.convert',$application->id) }}">@csrf<button class="w-full rounded-lg bg-green-700 px-3 py-2 text-sm font-semibold text-white">Convert approved applicant to student</button></form>
                        @elseif($application->converted_student_id)<p class="rounded-lg bg-green-50 p-2 text-sm text-green-800">Student account created: #{{ $application->converted_student_id }}</p>@endif
                    </section>
                    <section class="space-y-3">
                        <h3 class="font-bold">Documents & verification</h3>
                        @forelse($application->documents as $document)
                            <div class="rounded-lg border p-3 text-sm">
                                <div class="flex items-start justify-between gap-2"><div><a class="font-semibold text-blue-700 underline" href="{{ route($routePrefix.'documents.download',[$application->id,$document->id]) }}">{{ $documentLabels[$document->document_type] ?? 'Document' }}</a><p class="text-xs text-gray-500">{{ $document->original_name }} · {{ number_format($document->size_bytes/1024,0) }} KB</p></div><span class="text-xs font-semibold {{ $document->review_status==='verified'?'text-green-700':($document->review_status==='rejected'?'text-red-700':'text-amber-700') }}">{{ ucfirst($document->review_status) }}</span></div>
                                @if($document->review_notes)<p class="mt-1 text-xs text-gray-600">{{ $document->review_notes }}</p>@endif
                                <form method="POST" action="{{ route($routePrefix.'documents.review',[$application->id,$document->id]) }}" class="mt-2 grid gap-2">@csrf
                                    <select name="review_status" class="rounded border-gray-300 text-xs"><option value="verified" @selected($document->review_status==='verified')>Verify document</option><option value="rejected" @selected($document->review_status==='rejected')>Reject / request correction</option></select>
                                    <input name="review_notes" maxlength="1500" value="{{ $document->review_notes }}" placeholder="Correction reason (required on rejection)" class="rounded border-gray-300 text-xs">
                                    <button class="rounded border px-2 py-1 text-xs">Save document review</button>
                                </form>
                            </div>
                        @empty<p class="text-sm text-gray-500">No documents uploaded.</p>@endforelse
                        <form method="POST" enctype="multipart/form-data" action="{{ route($routePrefix.'documents.store',$application->id) }}" class="grid gap-2 rounded-lg border border-dashed p-3">@csrf
                            <select name="document_type" class="rounded border-gray-300 text-xs">@foreach($documentLabels as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                            <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required class="w-full text-xs">
                            <button class="rounded border px-2 py-1 text-xs">Upload private document</button>
                        </form>
                    </section>
                </div>
                @if($application->events->isNotEmpty())
                    <details class="border-t px-4 py-3"><summary class="cursor-pointer text-sm font-semibold">Application audit timeline ({{ $application->events->count() }})</summary><ol class="mt-3 space-y-2 border-l pl-4">@foreach($application->events as $event)<li class="text-xs"><strong>{{ str($event->event_key)->replace('_',' ')->title() }}</strong> · {{ $event->created_at?->format('d M Y, h:i A') }} · {{ $event->actor?->name ?? 'Applicant / System' }}@if($event->note)<p class="text-gray-600">{{ $event->note }}</p>@endif</li>@endforeach</ol></details>
                @endif
            </article>
        @empty
            <div class="rounded-xl border bg-white p-10 text-center text-gray-500">No admission applications match these filters.</div>
        @endforelse
    </div>
    {{ $applications->links() }}
</div>
@endsection
