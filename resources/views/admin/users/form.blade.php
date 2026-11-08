@extends('layouts.app')
@section('title', ($user->exists ? __('Edit account') : __('Create account')).' | '.config('app.name'))
@section('content')
<div class="page-header"><div><span class="badge info">Super Admin</span><h1>{{ $user->exists ? __('Edit account profile') : __('Create new account') }}</h1><p class="muted">Manage identity, contact details, role, branch assignment and login credentials.</p></div><a href="{{ route('admin.users.index') }}" class="btn secondary">{{ __('Back to users') }}</a></div>
<div class="panel" style="max-width:850px;">
<form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
@csrf
@if($user->exists) @method('PUT') @endif
<div class="grid grid-2">
<div><label for="name">{{ __('Full name') }}</label><input id="name" name="name" required maxlength="255" value="{{ old('name', $user->name) }}"></div>
<div><label for="email">{{ __('Email address') }}</label><input id="email" name="email" type="email" required maxlength="255" value="{{ old('email', $user->email) }}"></div>
<div><label for="phone_number">{{ __('Phone number') }}</label><input id="phone_number" name="phone_number" maxlength="40" value="{{ old('phone_number', $user->phone_number) }}"></div>
<div><label for="role">{{ __('Account role') }}</label><select id="role" name="role" required>@foreach(['admin'=>'Admin','branch_manager'=>'Branch Manager','accountant'=>'Accountant','teacher'=>'Teacher','student'=>'Student'] as $value=>$label)<option value="{{ $value }}" @selected(old('role', $user->role ?: 'student') === $value)>{{ __($label) }}</option>@endforeach</select></div>
<div><label for="branch_id">{{ __('Assigned branch') }}</label><select id="branch_id" name="branch_id"><option value="">{{ __('No branch assigned') }}</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id', $user->branch_id) === (string) $branch->id)>{{ $branch->name }}{{ $branch->is_active ? '' : ' (Inactive)' }}</option>@endforeach</select><small class="muted">Branch Manager and Accountant accounts require an active branch.</small></div>
<div><label for="password">{{ $user->exists ? __('New password (optional)') : __('Password') }}</label><input id="password" name="password" type="password" minlength="12" {{ $user->exists ? '' : 'required' }} autocomplete="new-password"><small class="muted">Minimum 12 characters. Leave blank to keep the current password.</small></div>
<div><label for="password_confirmation">{{ __('Confirm password') }}</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password"></div>
</div>
<div style="display:flex;gap:10px;margin-top:22px;"><button class="btn primary" type="submit">{{ $user->exists ? __('Save profile changes') : __('Create account') }}</button><a class="btn secondary" href="{{ route('admin.users.index') }}">{{ __('Cancel') }}</a></div>
</form>
</div>
@endsection
