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


## Creating a paused LMS boost

1. Connect a Meta account and choose an ad account whose currency is BDT.
2. Select an accessible Facebook Page with the Advertise task.
3. Enter the campaign name, ad copy, public image URL, LMS landing-page URL, daily BDT budget and age range.
4. Submit **Create paused campaign**. The LMS creates all four Meta objects (campaign, ad set, creative and ad) with status `PAUSED`.
5. Review the draft in Meta Ads Manager. Only when ready, use **Resume campaign** in the LMS and confirm the spend warning. For LMS-created campaigns, the ad and ad set are activated before the campaign.

The first release is a website-traffic campaign targeting Bangladesh; custom audiences, lead forms, sales/conversion objectives, schedule controls and automatic budget optimization are not included yet. Meta may reject requests until the developer app and Marketing API permissions have the required review and access level.
