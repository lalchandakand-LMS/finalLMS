@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl space-y-6 p-4 sm:p-6">
<h1 class="text-2xl font-bold">Admission Application</h1>
@if(session('status'))<div class="rounded bg-green-50 p-3 text-green-800">{{ session('status') }}</div>@endif
@if(isset($errors) && $errors->any())<div class="rounded bg-red-50 p-3 text-red-800">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('admission.public.apply.store') }}" class="grid gap-4 rounded border bg-white p-5 sm:grid-cols-2">
@csrf
<label class="grid gap-1">Applicant name<input required maxlength="160" name="applicant_name" value="{{ old('applicant_name') }}" class="rounded border-gray-300"></label>
<label class="grid gap-1">Email<input required type="email" maxlength="160" name="email" value="{{ old('email') }}" class="rounded border-gray-300"></label>
<label class="grid gap-1">Phone<input required maxlength="40" name="phone" value="{{ old('phone') }}" class="rounded border-gray-300"></label>
<label class="grid gap-1">Guardian phone<input maxlength="40" name="guardian_phone" value="{{ old('guardian_phone') }}" class="rounded border-gray-300"></label>
<label class="grid gap-1 sm:col-span-2">Desired course<input required maxlength="160" name="desired_course" value="{{ old('desired_course') }}" class="rounded border-gray-300"></label>
@if($branches->isNotEmpty())<label class="grid gap-1">Preferred branch<select required name="branch_id" class="rounded border-gray-300"><option value="">Select a branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string)old('branch_id')===(string)$branch->id)>{{ $branch->name }}</option>@endforeach</select></label>@endif
<label class="grid gap-1">Preferred batch<input maxlength="80" name="preferred_batch" value="{{ old('preferred_batch') }}" class="rounded border-gray-300"></label>
<label class="grid gap-1">Previous education<input maxlength="160" name="previous_education" value="{{ old('previous_education') }}" class="rounded border-gray-300"></label>
<label class="grid gap-1">Date of birth<input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" class="rounded border-gray-300"></label>
<label class="grid gap-1 sm:col-span-2">Address<textarea name="address" maxlength="2000" rows="3" class="rounded border-gray-300">{{ old('address') }}</textarea></label>
<div class="sm:col-span-2"><button class="rounded bg-blue-700 px-5 py-2 text-white">Submit application</button></div>
</form>
<a class="text-blue-700 underline" href="{{ route('admission.public.track') }}">Already applied? Track application</a>
</div>
@endsection
