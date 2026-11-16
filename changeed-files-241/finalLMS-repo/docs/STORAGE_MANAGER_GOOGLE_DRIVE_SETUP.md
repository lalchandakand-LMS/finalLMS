# Google Drive shared storage connection

This integration connects one dedicated Google account to the LMS Storage Manager and reads its account-wide storage quota. Google account storage is shared across Drive, Gmail and Google Photos; this does **not** mean the LMS can read Gmail messages or Photos.

## Google Cloud setup

1. Create or select a Google Cloud project dedicated to Alom's English Center.
2. Enable the **Google Drive API**.
3. Configure the OAuth consent screen and add the intended administrator Google account as a test user while the app is in testing.
4. Create an OAuth client ID for a **Web application**.
5. Add the exact authorized redirect URI:
   `https://YOUR-LMS-DOMAIN/admin/storage/google/callback`
6. Add these values to the server's private environment:
   - `GOOGLE_DRIVE_CLIENT_ID`
   - `GOOGLE_DRIVE_CLIENT_SECRET`
   - `GOOGLE_DRIVE_REDIRECT_URI` (must exactly match the Google Cloud Console redirect URI)
7. Clear cached config after changing server environment values.

The application requests only `openid`, `email` and `https://www.googleapis.com/auth/drive.file`. The Drive file scope is limited to files created by the app or explicitly opened/shared with it; the application does not request broad access to every personal Drive file or the Gmail API. The Drive `about.get` endpoint is used with an explicit `fields` mask to read `storageQuota`.

## Token security

- OAuth state is random, session-bound and checked on callback.
- Access and refresh tokens are encrypted with Laravel's application key before database storage.
- Tokens are hidden from model serialization and never rendered in the admin UI.
- Disconnect attempts to revoke the refresh token and clears local tokens. It does not delete files already stored in Google Drive.
- Keep `APP_KEY`, OAuth client secret and database backups private. Rotating `APP_KEY` makes previously encrypted Google tokens unreadable; reconnect the Google account afterward.

## Storage behavior and limits

- The Google quota shown is account-wide shared storage, not only the LMS folder.
- Google storage is shared by Drive, Gmail and Photos. A full account can affect Drive uploads and Gmail send/receive.
- This tranche connects the account and displays quota only. It does not yet upload, migrate or delete LMS files in Drive. File migration must be implemented separately with per-file records, checksums, retry/resume behavior and verified rollback before it is enabled.
- Use a dedicated institutional Google account rather than a staff member's personal account.
