@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl space-y-5 p-4 sm:p-6">
    <div class="rounded-2xl bg-blue-950 p-6 text-white"><p class="text-xs font-semibold uppercase tracking-widest text-blue-200">Alom's English Center</p><h1 class="mt-2 text-2xl font-bold">Track Admission Application</h1><p class="mt-1 text-sm text-blue-100">Enter your application tracking ID and the email address used during application.</p></div>
    @if(session('status'))<p class="rounded-lg bg-green-50 p-4 text-green-800">{{ session('status') }}</p>@endif
    @if(isset($errors) && $errors->any())<p class="rounded-lg bg-red-50 p-4 text-red-800">{{ $errors->first() }}</p>@endif
    <form method="POST" action="{{ route('admission.public.track.submit') }}" class="grid gap-3 rounded-xl border bg-white p-5">@csrf
        <label class="grid gap-1 text-sm font-medium">Application tracking ID<input required maxlength="40" name="application_code" value="{{ old('application_code') }}" placeholder="ADM-XXXXXXXXXX" class="rounded-lg border-gray-300"></label>
        <label class="grid gap-1 text-sm font-medium">Applicant email<input required type="email" maxlength="160" name="email" value="{{ old('email') }}" class="rounded-lg border-gray-300"></label>
        <button class="w-fit rounded-lg bg-blue-700 px-5 py-2.5 font-semibold text-white">Check application status</button>
    </form>
    @if($searched)
        @if($application)
            @php($statusLabels = ['pending' => 'Application received', 'screening' => 'Under screening', 'shortlisted' => 'Shortlisted', 'waitlisted' => 'Waitlisted', 'approved' => 'Approved', 'rejected' => 'Rejected'])
            <div class="space-y-4 rounded-xl border bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs uppercase tracking-wide text-gray-500">Applicant</p><h2 class="text-xl font-bold">{{ $application->applicant_name }}</h2><p class="text-sm text-gray-500">{{ $application->application_code }}</p></div><span class="rounded-full bg-blue-50 px-3 py-1 text-sm font-semibold text-blue-800">{{ $statusLabels[$application->status] ?? ucfirst($application->status) }}</span></div>
                <div class="grid gap-3 border-t pt-4 sm:grid-cols-2"><div><p class="text-xs text-gray-500">Course</p><p class="font-semibold">{{ $application->desired_course }}</p></div><div><p class="text-xs text-gray-500">Submitted</p><p class="font-semibold">{{ $application->created_at?->format('d M Y') }}</p></div></div>
                @if($application->interview_at)<div class="rounded-lg bg-amber-50 p-3 text-amber-900"><strong>Interview schedule:</strong> {{ $application->interview_at->format('d M Y, h:i A') }}</div>@endif
                <div class="border-t pt-4"><h3 class="font-bold">Document verification</h3><p class="mt-1 text-sm text-gray-600">Overall status: <strong>{{ str($application->document_verification_status)->replace('_', ' ')->title() }}</strong></p>
                    @if($application->documents->isNotEmpty())
                        <ul class="mt-2 space-y-2">@foreach($application->documents as $document)<li class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 p-3 text-sm"><span>{{ str($document->document_type)->replace('_', ' ')->title() }}</span><span class="font-semibold {{ $document->review_status === 'verified' ? 'text-green-700' : ($document->review_status === 'rejected' ? 'text-red-700' : 'text-amber-700') }}">{{ str($document->review_status)->title() }}</span></li>@endforeach</ul>
                    @else<p class="mt-2 text-sm text-gray-500">No supporting documents have been submitted yet.</p>@endif
                </div>
                <p class="border-t pt-4 text-sm text-gray-600">For changes or document corrections, contact the admission office of your selected branch. Internal reviewer notes are not shown on this public page.</p>
            </div>
        @else
            <p class="rounded-lg bg-amber-50 p-4 text-amber-900">No application matched those details. Check your tracking ID and email address.</p>
        @endif
    @endif
    <p class="text-center text-sm"><a class="text-blue-700 underline" href="{{ route('admission.public.apply') }}">Submit a new application</a></p>
</div>
@endsection
