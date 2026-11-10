@extends('layouts.app')
@section('title', __('Guardian relationships'))
@section('content')
<div class="page-header">
    <div>
        <span class="badge info">{{ __('Admin') }}</span>
        <h1>{{ __('Guardian relationships') }}</h1>
        <p class="muted">{{ __('Only administrator-verified guardian links can reveal a student summary.') }}</p>
    </div>
    <a href="{{ route('dashboard') }}" class="btn secondary">{{ __('Dashboard') }}</a>
</div>

<div class="panel" style="margin-bottom:20px;">
    <h2>{{ __('Verify and link a guardian') }}</h2>
    @if($guardians->isEmpty())
        <p class="muted">{{ __('First assign the Guardian role to an account in User management.') }}</p>
    @endif
    @if($students->isEmpty())
        <p class="muted">{{ __('No student accounts are available to link.') }}</p>
    @endif
    <form method="POST" action="{{ route('admin.guardians.links.store') }}" class="grid grid-2">
        @csrf
        <div>
            <label for="guardian_id">{{ __('Guardian account') }}</label>
            <select id="guardian_id" name="guardian_id" required>
                <option value="">{{ __('Select guardian') }}</option>
                @foreach($guardians as $guardian)
                    <option value="{{ $guardian->id }}" @selected(old('guardian_id') == $guardian->id)>{{ $guardian->name }} — {{ $guardian->email }}</option>
                @endforeach
            </select>
            @error('guardian_id')<small class="error">{{ $message }}</small>@enderror
        </div>
        <div>
            <label for="student_id">{{ __('Student account') }}</label>
            <select id="student_id" name="student_id" required>
                <option value="">{{ __('Select student') }}</option>
                @foreach($students as $student)
                    <option value="{{ $student->id }}" @selected(old('student_id') == $student->id)>{{ $student->name }} — {{ $student->email }}</option>
                @endforeach
            </select>
            @error('student_id')<small class="error">{{ $message }}</small>@enderror
        </div>
        <div>
            <label for="relationship">{{ __('Relationship') }}</label>
            <select id="relationship" name="relationship" required>
                <option value="father" @selected(old('relationship') === 'father')>{{ __('Father') }}</option>
                <option value="mother" @selected(old('relationship') === 'mother')>{{ __('Mother') }}</option>
                <option value="legal_guardian" @selected(old('relationship') === 'legal_guardian')>{{ __('Legal guardian') }}</option>
                <option value="other" @selected(old('relationship') === 'other')>{{ __('Other') }}</option>
            </select>
        </div>
        <div>
            <label for="notes">{{ __('Verification notes (optional)') }}</label>
            <input id="notes" name="notes" maxlength="1000" value="{{ old('notes') }}" placeholder="{{ __('Internal verification reference') }}">
        </div>
        <div style="grid-column:1/-1;">
            <p class="muted">{{ __('Confirm the relationship using the institution’s approved identity and consent process before granting access. The action is recorded against the administrator account.') }}</p>
            <button class="btn primary" type="submit" @disabled($guardians->isEmpty() || $students->isEmpty())>{{ __('Verify and activate access') }}</button>
        </div>
    </form>
</div>

<div class="panel">
    <h2>{{ __('Verified and revoked links') }}</h2>
    <div style="overflow-x:auto;">
        <table style="width:100%;min-width:850px;border-collapse:collapse;">
            <thead><tr style="text-align:left;border-bottom:1px solid var(--border);">
                <th style="padding:12px;">{{ __('Guardian') }}</th>
                <th style="padding:12px;">{{ __('Student') }}</th>
                <th style="padding:12px;">{{ __('Relationship') }}</th>
                <th style="padding:12px;">{{ __('Status') }}</th>
                <th style="padding:12px;">{{ __('Verified by') }}</th>
                <th style="padding:12px;">{{ __('Action') }}</th>
            </tr></thead>
            <tbody>
            @forelse($links as $link)
                <tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:12px;">{{ $link->guardian?->name ?? '—' }}<br><small class="muted">{{ $link->guardian?->email }}</small></td>
                    <td style="padding:12px;">{{ $link->student?->name ?? '—' }}<br><small class="muted">{{ $link->student?->email }}</small></td>
                    <td style="padding:12px;">{{ __(ucwords(str_replace('_', ' ', $link->relationship))) }}</td>
                    <td style="padding:12px;"><span class="badge {{ $link->status === 'approved' ? 'success' : 'warning' }}">{{ __(ucfirst($link->status)) }}</span></td>
                    <td style="padding:12px;">{{ $link->verifier?->name ?? '—' }}<br><small class="muted">{{ $link->verified_at?->format('d M Y H:i') }}</small></td>
                    <td style="padding:12px;">
                        @if($link->status === 'approved')
                            <form method="POST" action="{{ route('admin.guardians.links.revoke', $link) }}" onsubmit="return confirm('{{ __('Revoke this guardian’s access to the student?') }}')">
                                @csrf
                                <button type="submit" class="btn danger">{{ __('Revoke access') }}</button>
                            </form>
                        @else
                            <small class="muted">{{ __('Revoked') }} {{ $link->revoked_at?->format('d M Y H:i') }}</small>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted" style="padding:18px;">{{ __('No guardian relationships have been created yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:18px;">{{ $links->links() }}</div>
</div>
@endsection
