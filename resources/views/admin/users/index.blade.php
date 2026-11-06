@extends('layouts.app')

@section('title', 'User Management | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">Admin</span>
            <h1 style="margin: 12px 0 0;">User management</h1>
            <p class="muted">Search and manage accounts. The list is paginated to keep large student records responsive.</p>
        </div>
        <a href="{{ route('admin.courses.index') }}" class="btn secondary">Back to courses</a>
    </div>

    <div class="panel">
        <form method="GET" action="{{ route('admin.users.index') }}" class="grid grid-2" style="margin-bottom:20px;">
            <div>
                <label for="search">Search name, email or phone</label>
                <input id="search" name="search" value="{{ request('search') }}" maxlength="120" placeholder="Type a name, email or phone number">
            </div>
            <div>
                <label for="role">Role</label>
                <select id="role" name="role">
                    <option value="">All roles</option>
                    <option value="admin" @selected(request('role') === 'admin')>Admin</option>
                    <option value="teacher" @selected(request('role') === 'teacher')>Teacher</option>
                    <option value="student" @selected(request('role') === 'student')>Student</option>
                </select>
            </div>
            <div style="display:flex;gap:10px;align-items:end;">
                <button type="submit" class="btn primary">Search users</button>
                <a href="{{ route('admin.users.index') }}" class="btn secondary">Reset</a>
            </div>
        </form>

        <p class="muted">Showing {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} of {{ $users->total() }} accounts</p>
        <div style="overflow-x:auto;">
            <table style="width: 100%; border-collapse: collapse; min-width:760px;">
                <thead>
                    <tr style="text-align:left; border-bottom:1px solid var(--border);">
                        <th style="padding: 12px 10px;">Name</th>
                        <th style="padding: 12px 10px;">Email</th>
                        <th style="padding: 12px 10px;">Role</th>
                        <th style="padding: 12px 10px;">Courses</th>
                        <th style="padding: 12px 10px;">Enrollments</th>
                        <th style="padding: 12px 10px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr style="border-bottom:1px solid var(--border);">
                            <td style="padding: 12px 10px;">{{ $user->name }}</td>
                            <td style="padding: 12px 10px;">{{ $user->email }}</td>
                            <td style="padding: 12px 10px;">
                                <span class="badge {{ $user->role === 'admin' ? 'info' : ($user->role === 'teacher' ? 'success' : 'warning') }}">
                                    {{ ucfirst($user->role) }}
                                </span>
                            </td>
                            <td style="padding: 12px 10px;">{{ $user->courses_count }}</td>
                            <td style="padding: 12px 10px;">{{ $user->enrollments_count }}</td>
                            <td style="padding: 12px 10px;">
                                <form method="POST" action="{{ route('admin.users.updateRole', $user) }}" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                    @csrf
                                    <select name="role" style="width: auto; min-width: 120px;">
                                        <option value="student" @selected($user->role === 'student')>Student</option>
                                        <option value="teacher" @selected($user->role === 'teacher')>Teacher</option>
                                        <option value="admin" @selected($user->role === 'admin')>Admin</option>
                                    </select>
                                    <button type="submit" class="btn secondary" style="padding: 9px 12px;">Save</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted" style="padding:18px;">No accounts match this search.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:18px;">{{ $users->links() }}</div>
    </div>
@endsection
