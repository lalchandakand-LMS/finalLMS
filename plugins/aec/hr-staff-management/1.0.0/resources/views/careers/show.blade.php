@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-3xl space-y-6 p-4 sm:p-6">
    <a href="{{ route('careers.index') }}" class="text-sm text-blue-700">← All careers</a>
    <article class="rounded-xl border bg-white p-6">
        <h1 class="text-3xl font-bold">{{ $opening->title }}</h1>
        <p class="mt-2 text-gray-600">{{ $opening->department }} · {{ $opening->designation }} · {{ str_replace('_',' ',ucfirst($opening->employment_type)) }}</p>
        @if($opening->location)<p class="mt-1 text-sm text-gray-500">{{ $opening->location }}</p>@endif
        <div class="prose mt-5 max-w-none">{!! nl2br(e($opening->description)) !!}</div>
        <p class="mt-5 text-sm text-gray-500">Deadline: {{ $opening->closes_at?->format('d M Y, h:i A') ?? 'Until filled' }}</p>
    </article>
    <section class="rounded-xl border bg-white p-6">
        <h2 class="text-xl font-semibold">Apply for this position</h2>
        @if(session('success'))<p class="mt-3 rounded bg-green-50 p-3 text-green-800">{{ session('success') }}</p>@endif
        @php($validationErrors = session()->get('errors'))
        @if($validationErrors?->any())<p class="mt-3 rounded bg-red-50 p-3 text-red-800">{{ $validationErrors->first() }}</p>@endif
        <form method="POST" enctype="multipart/form-data" action="{{ route('careers.apply', $opening) }}" class="mt-4 grid gap-4 sm:grid-cols-2">@csrf
            <label class="text-sm">Full name<input required name="applicant_name" maxlength="160" value="{{ old('applicant_name') }}" autocomplete="name" class="mt-1 w-full rounded border-gray-300"></label>
            <label class="text-sm">Email<input required type="email" name="email" maxlength="160" value="{{ old('email') }}" autocomplete="email" class="mt-1 w-full rounded border-gray-300"></label>
            <label class="text-sm sm:col-span-2">Phone<input required name="phone" maxlength="40" value="{{ old('phone') }}" autocomplete="tel" class="mt-1 w-full rounded border-gray-300"></label>
            <label class="text-sm sm:col-span-2">Cover letter<textarea name="cover_letter" maxlength="5000" rows="5" class="mt-1 w-full rounded border-gray-300">{{ old('cover_letter') }}</textarea></label>
            <label class="text-sm sm:col-span-2">Resume (PDF, DOC or DOCX; max 10 MB)<input required type="file" name="resume" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="mt-1 block w-full rounded border-gray-300"></label>
            <button class="rounded bg-blue-700 px-5 py-3 font-semibold text-white sm:col-span-2">Submit application securely</button>
        </form>
    </section>
</div>
@endsection
