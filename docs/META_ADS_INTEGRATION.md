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
- `ads_management`: manage advertising objects; this integration currently only pauses campaigns.
- `business_management`: discover business assets where granted.
- `pages_read_engagement`: read Page engagement details.
- `pages_show_list`: list Pages available to the connected user.

Meta may require App Review and Standard Access before non-app-role users can use Marketing API capabilities. Actual availability depends on the connected user's asset roles and Meta approval.

## Security and spending

- OAuth state is random, stored in the authenticated session and checked on callback.
- Long-lived access tokens are encrypted with Laravel's application key before storage in the existing settings table.
- Never expose app secrets or access tokens in chat, logs, screenshots or source control.
- The integration does not create, activate, resume or publish ads. It can read accounts/pages/campaigns/insights and pause a campaign.
- Campaign activation and paid ad creation are deliberately excluded; a separate explicit approval and implementation are required before any action that can incur spend.
- Disconnect removes the stored encrypted token and selected account.

After configuring server environment values, clear cached configuration using the deployment's normal Laravel process.
