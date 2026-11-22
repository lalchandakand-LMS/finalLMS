@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl space-y-5 p-4 sm:p-6">
<h1 class="text-2xl font-bold">Track Admission Application</h1>
@if(session('status'))<p class="rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</p>@endif
<form method="POST" action="{{ route('admission.public.track.submit') }}" class="grid gap-3 rounded border bg-white p-5">@csrf
<label class="grid gap-1">Tracking ID<input required maxlength="40" name="application_code" value="{{ old('application_code') }}" class="rounded border-gray-300"></label>
<label class="grid gap-1">Applicant email<input required type="email" maxlength="160" name="email" value="{{ old('email') }}" class="rounded border-gray-300"></label>
<button class="w-fit rounded bg-blue-700 px-5 py-2 text-white">Check status</button></form>
@if($searched)@if($application)
<div class="rounded border bg-white p-5"><p><strong>{{ $application->applicant_name }}</strong></p><p>Course: {{ $application->desired_course }}</p><p>Status: <strong>{{ ucfirst($application->status) }}</strong></p><p>Submitted: {{ $application->created_at?->format('d M Y') }}</p>@if($application->interview_at)<p>Interview: {{ $application->interview_at->format('d M Y, h:i A') }}</p>@endif</div>
@else<p class="rounded bg-amber-50 p-3 text-amber-900">No application matched those details.</p>@endif @endif
</div>
@endsection
