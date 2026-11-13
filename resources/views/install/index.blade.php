<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install {{ config('app.name') }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f2f5fb;color:#172033;font-family:Arial,sans-serif}.wrap{max-width:760px;margin:36px auto;padding:20px}.panel{background:#fff;border:1px solid #dce4f1;border-radius:18px;padding:28px;box-shadow:0 12px 32px #19345b12}h1{margin:0 0 8px;color:#143b83}.muted{color:#58657a;line-height:1.6}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field{margin:12px 0}.field.full{grid-column:1/-1}label{display:block;font-weight:700;margin-bottom:6px}input{width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:9px;font-size:16px}button{background:#1746a2;color:#fff;border:0;border-radius:9px;padding:14px 20px;font-weight:700;font-size:16px;cursor:pointer;width:100%;margin-top:18px}.error{background:#fff0f0;color:#9c1c1c;border:1px solid #ffd2d2;padding:12px;border-radius:9px;margin:14px 0}.hint{font-size:13px;color:#68758a}.section{border-top:1px solid #e6ebf3;margin-top:24px;padding-top:18px}.section h2{font-size:18px;margin:0 0 12px}@media(max-width:600px){.grid{grid-template-columns:1fr}.field.full{grid-column:auto}.panel{padding:20px}.wrap{padding:12px;margin:12px auto}}
    </style>
</head>
<body>
<div class="wrap">
    <main class="panel">
        <h1>Alom’s English Center LMS</h1>
        <p class="muted">Secure first-time setup. Use a new, empty MySQL database. Your database password and administrator password are written to the private .env file and are not stored in the database as plain text.</p>

        @if ($errors->any())
            <div class="error">
                @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('install.store') }}">
            @csrf
            <div class="section">
                <h2>Website settings</h2>
                <div class="grid">
                    <div class="field full"><label for="site_name">Center / website name</label><input id="site_name" name="site_name" required maxlength="120" value="{{ old('site_name', 'Alom’s English Center') }}"></div>
                    <div class="field full"><label for="app_url">Website URL (HTTPS)</label><input id="app_url" name="app_url" type="url" required placeholder="https://example.com" value="{{ old('app_url') }}"></div>
                </div>
            </div>
            <div class="section">
                <h2>MySQL database</h2>
                <div class="grid">
                    <div class="field"><label for="db_host">Database host</label><input id="db_host" name="db_host" required value="{{ old('db_host', 'localhost') }}"></div>
                    <div class="field"><label for="db_port">Port</label><input id="db_port" name="db_port" type="number" min="1" max="65535" required value="{{ old('db_port', '3306') }}"></div>
                    <div class="field"><label for="db_database">Database name</label><input id="db_database" name="db_database" required value="{{ old('db_database') }}"></div>
                    <div class="field"><label for="db_username">Database username</label><input id="db_username" name="db_username" required value="{{ old('db_username') }}"></div>
                    <div class="field full"><label for="db_password">Database password</label><input id="db_password" name="db_password" type="password" autocomplete="new-password"></div>
                </div>
            </div>
            <div class="section">
                <h2>Administrator account</h2>
                <div class="grid">
                    <div class="field"><label for="admin_name">Full name</label><input id="admin_name" name="admin_name" required value="{{ old('admin_name') }}"></div>
                    <div class="field"><label for="admin_email">Email address</label><input id="admin_email" name="admin_email" type="email" required value="{{ old('admin_email') }}"></div>
                    <div class="field"><label for="admin_password">Password (minimum 12 characters)</label><input id="admin_password" name="admin_password" type="password" minlength="12" required autocomplete="new-password"></div>
                    <div class="field"><label for="admin_password_confirmation">Confirm password</label><input id="admin_password_confirmation" name="admin_password_confirmation" type="password" minlength="12" required autocomplete="new-password"></div>
                </div>
            </div>
            <p class="hint">Installation refuses any database that already contains a table or view. Use a brand-new, completely empty database; the installer never drops existing data.</p>
            <button type="submit">Install LMS securely</button>
        </form>
    </main>
</div>
</body>
</html>
