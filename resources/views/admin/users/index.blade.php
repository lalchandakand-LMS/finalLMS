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
                    <th style="padding:12px 10px;">{{ __('Name') }}</th><th style="padding:12px 10px;">{{ __('Email') }}</th><th style="padding:12px 10px;">Mobile</th>
                    <th style="padding:12px 10px;">{{ __('Role') }}</th><th style="padding:12px 10px;">{{ __('Branch') }}</th>
                    <th style="padding:12px 10px;">{{ __('Courses') }}</th><th style="padding:12px 10px;">{{ __('Enrollments') }}</th><th style="padding:12px 10px;">{{ __('Action') }}</th>
                </tr></thead>
                <tbody>
                @forelse($users as $user)
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:12px 10px;">{{ $user->name }}</td><td style="padding:12px 10px;">{{ $user->email }}</td><td style="padding:12px 10px;">{{ $user->phone_number ?? '—' }}<br><small class="muted">{{ $user->phone_verified_at ? 'Verified' : 'Not verified' }}</small></td>
                        <td style="padding:12px 10px;"><span class="badge {{ $user->role === 'admin' ? 'info' : (in_array($user->role, ['teacher', 'branch_manager'], true) ? 'success' : 'warning') }}">{{ ucwords(str_replace('_', ' ', $user->role)) }}</span></td>
                        <td style="padding:12px 10px;">{{ $user->branch?->name ?? '—' }}</td><td style="padding:12px 10px;">{{ $user->courses_count }}</td><td style="padding:12px 10px;">{{ $user->enrollments_count }}</td>
                        <td style="padding:12px 10px;">
                            <form method="POST" action="{{ route('admin.users.updateRole', $user) }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                @csrf
                                <input type="tel" name="phone_number" value="{{ $user->phone_number }}" placeholder="+8801XXXXXXXXX" pattern="[+][1-9][0-9]{7,14}" style="width:150px;" aria-label="Student mobile number (E.164)">
                                <select name="role" style="width:auto;min-width:130px;">
                                    @foreach(['student' => 'Student', 'branch_manager' => 'Branch Manager', 'accountant' => 'Accountant', 'teacher' => 'Teacher', 'admin' => 'Admin', 'guardian' => 'Guardian'] as $roleValue => $roleLabel)
                                        <option value="{{ $roleValue }}" @selected($user->role === $roleValue)>{{ __($roleLabel) }}</option>
                                    @endforeach
                                </select>
                                <select name="branch_id" style="width:auto;min-width:130px;" aria-label="{{ __('Assigned branch for this account') }}">
                                    <option value="">{{ __('No branch') }}</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected($user->branch_id === $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                <fieldset style="flex-basis:100%;border:1px solid var(--border);border-radius:10px;padding:10px;">
                                    <legend style="padding:0 6px;font-size:.82rem;font-weight:700;">Staff module permissions</legend>
                                    <input type="hidden" name="admin_permissions_present" value="1">
                                    <small class="muted" style="display:block;margin-bottom:8px;">These permissions apply only to Branch Manager and Accountant roles. Unchecked modules are denied server-side.</small>
                                    <div style="display:flex;gap:12px;flex-wrap:wrap;">
                                        <label style="display:flex;gap:6px;align-items:center;"><input type="checkbox" name="admin_permissions[]" value="branch.batches" @checked(in_array('branch.batches', $user->admin_permissions ?? [], true))> Branch batches</label>
                                        <label style="display:flex;gap:6px;align-items:center;"><input type="checkbox" name="admin_permissions[]" value="branch.students" @checked(in_array('branch.students', $user->admin_permissions ?? [], true))> Branch students</label>
                                        <label style="display:flex;gap:6px;align-items:center;"><input type="checkbox" name="admin_permissions[]" value="finance.invoices" @checked(in_array('finance.invoices', $user->admin_permissions ?? [], true))> Finance invoices</label>
                                        <label style="display:flex;gap:6px;align-items:center;"><input type="checkbox" name="admin_permissions[]" value="finance.refunds" @checked(in_array('finance.refunds', $user->admin_permissions ?? [], true))> Finance refunds</label>
                                        <label style="display:flex;gap:6px;align-items:center;"><input type="checkbox" name="admin_permissions[]" value="finance.reports" @checked(in_array('finance.reports', $user->admin_permissions ?? [], true))> Finance reports</label>
                                        <label style="display:flex;gap:6px;align-items:center;"><input type="checkbox" name="admin_permissions[]" value="finance.expenses" @checked(in_array('finance.expenses', $user->admin_permissions ?? [], true))> Finance expenses</label>
                                    </div>
                                </fieldset>
                                <button type="submit" class="btn secondary" style="padding:9px 12px;">{{ __('Save') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted" style="padding:18px;">{{ __('No accounts match this search.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:18px;">{{ $users->links() }}</div>
    </div>
@endsection
