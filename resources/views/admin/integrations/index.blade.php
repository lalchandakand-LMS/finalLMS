@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div><h1 class="h3 mb-1">API Integrations</h1><p class="text-muted mb-0">Secure provider setup and connection lifecycle.</p></div>
        <span class="badge bg-warning text-dark">Provider verification required</span>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="row g-3 mb-4">
        @foreach($providers as $key => $label)
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 shadow-sm"><div class="card-body">
                <h2 class="h6">{{ $label }}</h2>
                <form method="POST" action="{{ route('admin.integrations.store') }}">
                    @csrf
                    <input type="hidden" name="provider_key" value="{{ $key }}">
                    <div class="mb-2"><label class="form-label">Connection name</label><input class="form-control" name="display_name" maxlength="120" required></div>
                    <div class="mb-2"><label class="form-label">External account ID</label><input class="form-control" name="external_account_id" maxlength="190"></div>
                    <div class="mb-2"><label class="form-label">Credential / token</label><input class="form-control" type="password" name="credentials[token]" autocomplete="new-password" required></div>
                    <div class="mb-2"><label class="form-label">Granted scopes (comma separated)</label><input class="form-control" name="scope_input" placeholder="Only scopes actually approved"></div>
                    <button class="btn btn-primary btn-sm" type="submit">Save securely</button>
                </form>
            </div></div>
        </div>
        @endforeach
    </div>

    <div class="card shadow-sm"><div class="card-header"><strong>Saved connections</strong></div><div class="table-responsive">
        <table class="table table-hover align-middle mb-0"><thead><tr><th>Provider</th><th>Account</th><th>Status</th><th>Webhook</th><th>Last check</th><th>Actions</th></tr></thead><tbody>
        @forelse($connections as $connection)
            <tr><td>{{ $connection->display_name }}<div class="small text-muted">{{ $connection->provider_key }}</div></td><td>{{ $connection->external_account_id ?: '—' }}</td><td><span class="badge bg-secondary">{{ str_replace('_',' ', $connection->status) }}</span></td><td>{{ $connection->webhook_verified ? 'Verified' : 'Not verified' }}</td><td>{{ $connection->last_credential_check_at?->format('Y-m-d H:i') ?: '—' }}</td><td class="d-flex gap-1">
                <form method="POST" action="{{ route('admin.integrations.pause', $connection) }}">@csrf<button class="btn btn-outline-warning btn-sm">Pause</button></form>
                <form method="POST" action="{{ route('admin.integrations.disconnect', $connection) }}" onsubmit="return confirm('Remove stored credentials?')">@csrf<button class="btn btn-outline-danger btn-sm">Disconnect</button></form>
            </td></tr>
        @empty<tr><td colspan="6" class="text-center text-muted py-4">No provider connections configured.</td></tr>@endforelse
        </tbody></table></div><div class="card-footer">{{ $connections->links() }}</div>
    </div>
    <p class="small text-muted mt-3">A saved credential is never considered connected. Credential validation, provider permissions, webhook signature verification and provider-specific adapters must pass before production activation.</p>
</div>
@endsection