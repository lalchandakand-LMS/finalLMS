@extends('layouts.app')
@section('title', 'Teacher registration | '.config('app.name'))
@section('content')
<div class="auth-box" style="max-width:720px;">
    <h1>Teacher registration / শিক্ষক নিবন্ধন</h1>
    <p class="muted">Submit your identity and current institution documents. Your account is created only after admin approval.</p>
    @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('teacher.apply.store') }}" enctype="multipart/form-data">
        @csrf
        <label>Full name<input name="name" value="{{ old('name') }}" required maxlength="160"></label>
        <label>Email<input type="email" name="email" value="{{ old('email') }}" required></label>
        <label>Mobile number (+8801XXXXXXXXX)<input type="tel" name="phone_number" value="{{ old('phone_number') }}" placeholder="+8801712345678" required></label>
        <label>Institution / প্রতিষ্ঠানের নাম<input name="institution_name" value="{{ old('institution_name') }}" required></label>
        <label>Position / পদবি<input name="designation" value="{{ old('designation') }}" required></label>
        <label>NID number<input name="nid_number" inputmode="numeric" pattern="[0-9]{10}|[0-9]{13}|[0-9]{17}" required></label>
        <label>NID document (JPG, PNG, PDF; max 5 MB)<input type="file" name="nid_document" accept=".jpg,.jpeg,.png,.pdf" required></label>
        <label>Institution proof (ID card / appointment letter / employment certificate; JPG, PNG, PDF; max 5 MB)<input type="file" name="institution_document" accept=".jpg,.jpeg,.png,.pdf" required></label>
        <label>Password<input type="password" name="password" required autocomplete="new-password"></label>
        <label>Confirm password<input type="password" name="password_confirmation" required autocomplete="new-password"></label>
        <label style="display:flex;gap:10px;align-items:flex-start;font-weight:500;"><input type="checkbox" name="consent" value="1" required style="width:auto;margin-top:4px;"> I confirm these documents are genuine and consent to their confidential verification by academy administrators.</label>
        <button class="btn primary" type="submit" style="width:100%;">Submit and verify mobile</button>
    </form>
</div>
@endsection