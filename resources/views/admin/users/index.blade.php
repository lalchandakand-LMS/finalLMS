@extends('layouts.app')

@section('title', __('User management') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">Admin</span>
            <h1 style="margin: 12px 0 0;">{{ __('User management') }}</h1>
            <p class="muted">{{ __('Search and manage accounts. The list is paginated to keep large student records responsive.') }}</p>
        </div>
        <a href="{{ route('admin.courses.index') }}" class="btn secondary">{{ __('Back to courses') }}</a>
    </div>

    <div class="panel" style="margin-bottom:20px">
        <h2>{{ __('Link guardian to student') }}</h2>
        <p class="muted">{{ __('Only an administrator can create or remove a guardian relationship. Guardians can view linked students’ learning and fee summaries.') }}</p>
        <form method="POST" action="{{ route('admin.guardians.link') }}" class="grid grid-3">
            @csrf
            <div><label for="guardian_id">{{ __('Guardian account') }}</label><select id="guardian_id" name="guardian_id" required><option value="">{{ __('Select guardian') }}</option>@foreach($guardians as $guardian)<option value="{{ $guardian->id }}">{{ $guardian->name }} — {{ $guardian->email }}</option>@endforeach</select></div>
            <div><label for="student_id">{{ __('Student account') }}</label><select id="student_id" name="student_id" required><option value="">{{ __('Select student') }}</option>@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->name }} — {{ $student->email }}</option>@endforeach</select></div>
            <div style="display:flex;align-items:end"><button class="btn primary" type="submit">{{ __('Link accounts') }}</button></div>
        </form>
        <div style="overflow-x:auto;margin-top:18px"><table style="width:100%;border-collapse:collapse"><thead><tr><th>{{ __('Guardian') }}</th><th>{{ __('Student') }}</th><th>{{ __('Action') }}</th></tr></thead><tbody>
        @foreach(\App\Models\GuardianStudent::with(['guardian','student'])->latest()->limit(50)->get() as $link)
            <tr><td>{{ $link->guardian?->name }} ({{ $link->guardian?->email }})</td><td>{{ $link->student?->name }} ({{ $link->student?->email }})</td><td><form method="POST" action="{{ route('admin.guardians.unlink', [$link->guardian_id, $link->student_id]) }}" onsubmit="return confirm('{{ __('Remove this guardian link?') }}')">@csrf @method('DELETE')<button class="btn secondary" type="submit">{{ __('Unlink') }}</button></form></td></tr>
        @endforeach
        </tbody></table></div>
    </div>

    <div class="panel">
        <form method="GET" action="{{ route('admin.users.index') }}" class="grid grid-2" style="margin-bottom:20px;">
            <div>
                <label for="search">{{ __('Search name, email or phone') }}</label>
                <input id="search" name="search" value="{{ request('search') }}" maxlength="120" placeholder="{{ __('Type a name, email or phone number') }}">
            </div>
            <div>
                <label for="role">{{ __('Role') }}</label>
                <select id="role" name="role">
                    <option value="">{{ __('All roles') }}</option>
                    @foreach(['admin' => 'Admin', 'branch_manager' => 'Branch Manager', 'accountant' => 'Accountant', 'teacher' => 'Teacher', 'student' => 'Student', 'guardian' => 'Guardian'] as $roleValue => $roleLabel)
                        <option value="{{ $roleValue }}" @selected(request('role') === $roleValue)>{{ __($roleLabel) }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;gap:10px;align-items:end;">
                <button type="submit" class="btn primary">{{ __('Search users') }}</button>
                <a href="{{ route('admin.users.index') }}" class="btn secondary">{{ __('Reset') }}</a>
            </div>
        </form>

        <p class="muted">{{ __('Showing') }} {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} {{ __('of') }} {{ $users->total() }} {{ __('accounts') }}</p>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;min-width:900px;">
                <thead><tr style="text-align:left;border-bottom:1px solid var(--border);">
                    <th style="padding:12px 10px;">{{ __('Name') }}</th><th style="padding:12px 10px;">{{ __('Email') }}</th>
                    <th style="padding:12px 10px;">{{ __('Role') }}</th><th style="padding:12px 10px;">{{ __('Branch') }}</th>
                    <th style="padding:12px 10px;">{{ __('Courses') }}</th><th style="padding:12px 10px;">{{ __('Enrollments') }}</th><th style="padding:12px 10px;">{{ __('Action') }}</th>
                </tr></thead>
                <tbody>
                @forelse($users as $user)
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:12px 10px;">{{ $user->name }}</td><td style="padding:12px 10px;">{{ $user->email }}</td>
                        <td style="padding:12px 10px;"><span class="badge {{ $user->role === 'admin' ? 'info' : (in_array($user->role, ['teacher', 'branch_manager'], true) ? 'success' : 'warning') }}">{{ ucwords(str_replace('_', ' ', $user->role)) }}</span></td>
                        <td style="padding:12px 10px;">{{ $user->branch?->name ?? '—' }}</td><td style="padding:12px 10px;">{{ $user->courses_count }}</td><td style="padding:12px 10px;">{{ $user->enrollments_count }}</td>
                        <td style="padding:12px 10px;">
                            <form method="POST" action="{{ route('admin.users.updateRole', $user) }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                @csrf
                                <select name="role" style="width:auto;min-width:130px;">
                                    @foreach(['student' => 'Student', 'guardian' => 'Guardian', 'branch_manager' => 'Branch Manager', 'accountant' => 'Accountant', 'teacher' => 'Teacher', 'admin' => 'Admin'] as $roleValue => $roleLabel)
                                        <option value="{{ $roleValue }}" @selected($user->role === $roleValue)>{{ __($roleLabel) }}</option>
                                    @endforeach
                                </select>
                                <select name="branch_id" style="width:auto;min-width:130px;" aria-label="{{ __('Assigned branch for this account') }}">
                                    <option value="">{{ __('No branch') }}</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected($user->branch_id === $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn secondary" style="padding:9px 12px;">{{ __('Save') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted" style="padding:18px;">{{ __('No accounts match this search.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:18px;">{{ $users->links() }}</div>
    </div>
@endsection
