# cPanel storage quota setup

The Storage Manager reads the hosting account's real disk quota through cPanel UAPI. It does not estimate account-wide use by scanning only the LMS upload directory.

## Server configuration

1. Sign in to cPanel.
2. Open **Security → Manage API Tokens** and create a dedicated API token for quota reporting.
3. Configure these values in the server environment or private `.env` file (never commit real values):
   - `CPANEL_API_HOST`: hostname only, such as `server.example.com` (no scheme, path or port).
   - `CPANEL_API_USERNAME`: cPanel account username.
   - `CPANEL_API_TOKEN`: the API token, not the cPanel account password.
4. Set `LMS_LOCAL_STORAGE_QUOTA_BYTES=10737418240` for the 10 GiB LMS planning threshold if that is the hosting package allowance.
5. Clear cached config after changing environment values: `php artisan config:clear`, then rebuild config cache if used.

The application calls `https://HOST:2083/execute/Quota/get_quota_info` with a 10-second timeout. It validates the host before making a request, handles unlimited quotas, and reports unavailable status if credentials or cPanel UAPI are unavailable. It never displays the token.

## Important distinction

- cPanel quota is the authoritative account-wide usage figure (website files, mailboxes, databases and other account data, according to the host's quota accounting).
- The 10 GiB LMS planning quota is a configured target, not a live measurement.
- Google Drive is a separate future integration. Google account storage is shared by Drive, Gmail and Photos. This PR does not connect or migrate files to Google Drive.

Do not add credentials to GitHub, screenshots, issue comments or chat. Rotate the API token if it is exposed.
