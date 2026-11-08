@extends('layouts.app')
@section('title', __('Admission profile'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ $profile->admission_number }}</span><h1>{{ __('Update admission profile') }}</h1><p>{{ __('Your referral code') }}: <strong>{{ $profile->referral_code }}</strong></p></div></div>
@if($errors->any())<div class="flash error">{{ $errors->first() }}</div>@endif
<div class="panel"><form method="POST" action="{{ route('student.admission.update') }}" enctype="multipart/form-data">@csrf
<div class="grid grid-2">
<label>{{ __('School / institution') }}<input name="school_name" value="{{ old('school_name',$profile->school_name) }}" required></label><label>{{ __('Class / grade') }}<input name="class_name" value="{{ old('class_name',$profile->class_name) }}" required></label><label>{{ __('School roll') }}<input name="school_roll" value="{{ old('school_roll',$profile->school_roll) }}"></label>
<label>{{ __('Interested course') }}<select name="course_id" required>@foreach($courses as $course)<option value="{{ $course->id }}" @selected(old('course_id',$profile->interested_course_id)==$course->id)>{{ $course->title }} — ৳{{ number_format((float)$course->price,2) }}</option>@endforeach</select></label>
<label>{{ __('Current address') }}<textarea name="current_address" required>{{ old('current_address',$profile->current_address) }}</textarea></label><label>{{ __('Permanent address') }}<textarea name="permanent_address" required>{{ old('permanent_address',$profile->permanent_address) }}</textarea></label>
@foreach(['current','permanent'] as $kind)@foreach(['post_office','district','division','country'] as $field)<label>{{ __(ucfirst($kind).' '.$field) }}<input name="{{ $kind }}_{{ $field }}" value="{{ old($kind.'_'.$field,$profile->{$kind.'_'.$field}) }}" required></label>@endforeach @endforeach
<label>{{ __('Living arrangement') }}<select name="living_type"><option value="residential" @selected($profile->living_type==='residential')>{{ __('Residential') }}</option><option value="non_residential" @selected($profile->living_type==='non_residential')>{{ __('Non-residential') }}</option></select></label>
<label>{{ __('Special quota') }}<select name="special_quota">@foreach(['none','disability','freedom_fighter','ethnic_minority','other'] as $q)<option value="{{ $q }}" @selected($profile->special_quota===$q)>{{ __(ucwords(str_replace('_',' ',$q))) }}</option>@endforeach</select></label>
<label>{{ __('Quota details') }}<textarea name="special_quota_notes">{{ old('special_quota_notes',$profile->special_quota_notes) }}</textarea></label><label>{{ __('Replace profile photo') }}<input type="file" name="profile_photo" accept="image/*" capture="user"></label><label>{{ __('Upload quota evidence') }}<input type="file" name="quota_document" accept="image/jpeg,image/png,application/pdf"></label>
<label>{{ __('Primary guardian full name') }}<input name="guardian_name" value="{{ old('guardian_name',$user->studentGuardians->firstWhere('is_primary',true)?->name) }}" required></label>
<label>{{ __('Guardian relationship') }}<input name="guardian_relationship" value="{{ old('guardian_relationship',$user->studentGuardians->firstWhere('is_primary',true)?->relationship) }}" required></label>
<label>{{ __('Guardian mobile number') }}<input type="tel" name="guardian_mobile" value="{{ old('guardian_mobile',$user->studentGuardians->firstWhere('is_primary',true)?->mobile_number) }}" required pattern="[+][1-9][0-9]{7,14}"></label>
<label>{{ __('Guardian email') }}<input type="email" name="guardian_email" value="{{ old('guardian_email',$user->studentGuardians->firstWhere('is_primary',true)?->email) }}"></label>
<label>{{ __('Guardian occupation') }}<input name="guardian_occupation" value="{{ old('guardian_occupation',$user->studentGuardians->firstWhere('is_primary',true)?->occupation) }}"></label>
<label>{{ __('Guardian NID number') }}<input name="guardian_nid_number" value="{{ old('guardian_nid_number',$user->studentGuardians->firstWhere('is_primary',true)?->nid_number) }}"></label>
<label>{{ __('Guardian NID front') }}<input type="file" name="guardian_nid_front" accept="image/jpeg,image/png,application/pdf"></label>
<label>{{ __('Guardian NID back') }}<input type="file" name="guardian_nid_back" accept="image/jpeg,image/png,application/pdf"></label>
</div>
@php($primaryGuardian=$profile->guardians->firstWhere('is_primary',true))
<div class="panel" style="margin-top:16px"><h2>{{ __('Parent / guardian contact') }}</h2><div class="grid grid-2">
<label>{{ __('Guardian name') }}<input name="guardian_name" value="{{ old('guardian_name',$primaryGuardian?->name) }}" required></label>
<label>{{ __('Relationship') }}<input name="guardian_relationship" value="{{ old('guardian_relationship',$primaryGuardian?->relationship) }}" required></label>
<label>{{ __('Mobile number') }}<input type="tel" name="guardian_mobile" value="{{ old('guardian_mobile',$primaryGuardian?->mobile_number) }}" required pattern="[+][1-9][0-9]{7,14}"></label>
<label>{{ __('Email') }}<input type="email" name="guardian_email" value="{{ old('guardian_email',$primaryGuardian?->email) }}"></label>
<label>{{ __('Occupation') }}<input name="guardian_occupation" value="{{ old('guardian_occupation',$primaryGuardian?->occupation) }}"></label>
<label>{{ __('NID number') }}<input name="guardian_nid_number" value="" placeholder="{{ __('Leave blank to keep current encrypted number') }}"></label>
</div></div>
<label><input type="checkbox" name="course_updates_opt_in" value="1" style="width:auto" @checked($profile->course_updates_opt_in)> {{ __('Receive course updates') }}</label><button class="btn primary" style="margin-top:12px">{{ __('Save changes') }}</button></form></div>
<div class="panel" style="margin-top:18px"><h2>{{ __('Private documents') }}</h2>@foreach($profile->documents as $doc)<p><a href="{{ route('student.documents.show',$doc) }}">{{ __(ucwords(str_replace('_',' ',$doc->document_type))) }} — {{ $doc->original_name }}</a></p>@endforeach</div>
<div class="panel" style="margin-top:18px"><h2>{{ __('Request profile deletion') }}</h2><p class="muted">{{ __('The academy reviews deletion requests and removes personal admission documents while retaining legally necessary academic and financial audit records in anonymized form.') }}</p><form method="POST" action="{{ route('student.admission.delete-request') }}">@csrf<label><input type="checkbox" name="confirm" value="1" required style="width:auto"> {{ __('I confirm I want to request deletion of my personal profile.') }}</label><button class="btn secondary" style="margin-top:10px">{{ __('Submit deletion request') }}</button></form></div>
@endsection