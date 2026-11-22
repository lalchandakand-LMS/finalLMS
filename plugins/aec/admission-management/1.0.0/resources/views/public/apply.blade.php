@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl space-y-6 p-4 sm:p-6">
    <div class="rounded-2xl bg-gradient-to-r from-blue-950 to-blue-700 p-6 text-white sm:p-8">
        <p class="text-sm font-semibold uppercase tracking-widest text-blue-200">Alom's English Center</p>
        <h1 class="mt-2 text-3xl font-bold">Online Admission Application</h1>
        <p class="mt-2 max-w-2xl text-blue-100">Complete your information carefully and attach your supporting documents. You will receive an application tracking ID after submission.</p>
    </div>
    @if(session('status'))<div class="rounded-lg bg-green-50 p-4 text-green-800">{{ session('status') }}</div>@endif
    @if(isset($errors) && $errors->any())<div class="rounded-lg bg-red-50 p-4 text-red-800"><strong>Please correct the following:</strong><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <form method="POST" enctype="multipart/form-data" action="{{ route('admission.public.apply.store') }}" class="space-y-6 rounded-2xl border bg-white p-5 shadow-sm sm:p-7">
        @csrf
        <section class="space-y-4">
            <div><h2 class="text-xl font-bold">Applicant information</h2><p class="text-sm text-gray-500">Fields marked * are required.</p></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-1 text-sm font-medium">Applicant full name *<input required maxlength="160" name="applicant_name" value="{{ old('applicant_name') }}" autocomplete="name" class="rounded-lg border-gray-300"></label>
                <label class="grid gap-1 text-sm font-medium">Email address *<input required type="email" maxlength="160" name="email" value="{{ old('email') }}" autocomplete="email" class="rounded-lg border-gray-300"></label>
                <label class="grid gap-1 text-sm font-medium">Mobile number *<input required maxlength="40" name="phone" value="{{ old('phone') }}" autocomplete="tel" placeholder="01XXXXXXXXX" class="rounded-lg border-gray-300"></label>
                <label class="grid gap-1 text-sm font-medium">Guardian mobile number<input maxlength="40" name="guardian_phone" value="{{ old('guardian_phone') }}" class="rounded-lg border-gray-300"></label>
                <label class="grid gap-1 text-sm font-medium">Date of birth<input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" class="rounded-lg border-gray-300"></label>
                <label class="grid gap-1 text-sm font-medium">Previous education<input maxlength="160" name="previous_education" value="{{ old('previous_education') }}" placeholder="e.g. HSC / University" class="rounded-lg border-gray-300"></label>
            </div>
        </section>
        <section class="space-y-4 border-t pt-5">
            <h2 class="text-xl font-bold">Course & branch preference</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-1 text-sm font-medium">Desired course *<input required maxlength="160" name="desired_course" value="{{ old('desired_course') }}" placeholder="e.g. Spoken English" class="rounded-lg border-gray-300"></label>
                <label class="grid gap-1 text-sm font-medium">Preferred batch<input maxlength="80" name="preferred_batch" value="{{ old('preferred_batch') }}" placeholder="e.g. Evening batch" class="rounded-lg border-gray-300"></label>
                @if($branches->isNotEmpty())
                    <label class="grid gap-1 text-sm font-medium">Preferred branch<select name="branch_id" class="rounded-lg border-gray-300"><option value="">Not selected</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string)old('branch_id')===(string)$branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
                @endif
                <label class="grid gap-1 text-sm font-medium sm:col-span-2">Present address<textarea name="address" maxlength="2000" rows="3" class="rounded-lg border-gray-300">{{ old('address') }}</textarea></label>
            </div>
        </section>
        <section class="space-y-4 border-t pt-5">
            <div><h2 class="text-xl font-bold">Supporting documents</h2><p class="text-sm text-gray-500">Optional at application time. PDF, JPG or PNG; maximum 10 MB each. Uploaded files remain private and are reviewed by admission staff.</p></div>
            <div class="grid gap-3 sm:grid-cols-2">
                @php($documentLabels = ['photo' => 'Recent photo', 'birth_certificate' => 'Birth certificate', 'nid' => 'NID / identity document', 'previous_certificate' => 'Previous education certificate', 'other' => 'Other supporting document'])
                @foreach($documentLabels as $type => $label)
                    <label class="grid gap-2 rounded-lg border border-dashed p-3 text-sm font-medium">{{ $label }}<input type="hidden" name="document_types[{{ $loop->index }}]" value="{{ $type }}"><input type="file" name="documents[{{ $loop->index }}]" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="block w-full text-sm text-gray-600"></label>
                @endforeach
            </div>
        </section>
        <div class="flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-gray-500">By submitting, you confirm that the information is accurate and consent to its use for admission processing.</p>
            <button class="rounded-lg bg-blue-700 px-6 py-3 font-semibold text-white hover:bg-blue-800">Submit application</button>
        </div>
    </form>
    <p class="text-center text-sm"><a class="font-semibold text-blue-700 underline" href="{{ route('admission.public.track') }}">Already applied? Track your application</a></p>
</div>
@endsection
