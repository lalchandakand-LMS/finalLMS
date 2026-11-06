@extends('layouts.app')

@section('title', 'User Management | LMS Academy')

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">Admin</span>
            <h1 style="margin: 12px 0 0;">User management</h1>
        </div>
        <a href="{{ route('admin.courses.index') }}" class="btn secondary">Back to courses</a>
    </div>

    <div class="panel">
        <table style="width: 100%; border-collapse: collapse;">
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
                @foreach($users as $user)
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
                                    <option value="student" {{ $user->role === 'student' ? 'selected' : '' }}>Student</option>
                                    <option value="teacher" {{ $user->role === 'teacher' ? 'selected' : '' }}>Teacher</option>
                                    <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Admin</option>
                                </select>
                                <button type="submit" class="btn secondary" style="padding: 9px 12px;">Save</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
