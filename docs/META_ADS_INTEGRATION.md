# Meta Ads Admin Integration

This integration connects an administrator's Meta account to the LMS using OAuth and the Meta Graph / Marketing API.

## Server environment

Configure these values in the deployment secret manager or server environment (never commit them):

- `META_ADS_APP_ID`: Meta developer app ID.
- `META_ADS_APP_SECRET`: Meta developer app secret.
- `META_ADS_REDIRECT_URI`: exact callback URL, normally `https://YOUR-LMS-DOMAIN/admin/marketing/meta-ads/callback`.

The callback URL must be added to the Meta app's Valid OAuth Redirect URIs. The app must be configured for the relevant Marketing API permissions and reviewed/approved by Meta for the intended users.

## Permissions requested

- `ads_read`: read ad accounts, campaigns and insights.
- `ads_management`: manage advertising objects; this integration can pause campaigns and resume them only after an explicit admin confirmation.
- `business_management`: discover business assets where granted.
- `pages_read_engagement`: read Page engagement details.
- `pages_show_list`: list Pages available to the connected user.

Meta may require App Review and Standard Access before non-app-role users can use Marketing API capabilities. Actual availability depends on the connected user's asset roles and Meta approval.

## Security and spending

- OAuth state is random, stored in the authenticated session and checked on callback.
- Long-lived access tokens are encrypted with Laravel's application key before storage in the existing settings table.
- Never expose app secrets or access tokens in chat, logs, screenshots or source control.
- The integration does not create or publish ads. It can read accounts/pages/campaigns/insights, pause campaigns and resume a paused campaign only after a spend warning and explicit admin confirmation.
- Paid ad creation is deliberately excluded. Resuming a paused campaign can incur spend and therefore requires an explicit confirmation on the campaign action.
- Disconnect removes the stored encrypted token and selected account.

After configuring server environment values, clear cached configuration using the deployment's normal Laravel process.
