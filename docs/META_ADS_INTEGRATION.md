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
- `ads_management`: create paused campaign objects, pause campaigns, and resume campaigns only after an explicit admin confirmation.
- `business_management`: discover business assets where granted.
- `pages_read_engagement`: read Page engagement details.
- `pages_show_list`: list Pages available to the connected user.

Meta may require App Review and Standard Access before non-app-role users can use Marketing API capabilities. Actual availability depends on the connected user's asset roles and Meta approval.

## Security and spending

- OAuth state is random, stored in the authenticated session and checked on callback.
- Long-lived access tokens are encrypted with Laravel's application key before storage in the existing settings table.
- Never expose app secrets or access tokens in chat, logs, screenshots or source control.
- The integration can create a website-traffic campaign, ad set, creative and ad, with every object initially set to `PAUSED`. Creation alone does not activate delivery or spend budget.
- Campaign creation currently supports BDT ad accounts, Bangladesh targeting, Facebook/Instagram Feed placements, an age range of 18–65, a public image URL and an LMS landing-page URL.
- Resuming an LMS-created campaign activates the ad and ad set first and the campaign last. Resuming can incur spend up to the configured daily budget and requires a separate explicit admin confirmation.
- Disconnect removes the stored encrypted token and selected account.

After configuring server environment values, clear cached configuration using the deployment's normal Laravel process.


## Server setup checklist

1. Set `APP_URL` to the public HTTPS LMS domain.
2. Set `META_ADS_APP_ID` and `META_ADS_APP_SECRET` in the server secret manager or deployment environment. Never add the app secret to Git or send it in chat.
3. Set `META_ADS_REDIRECT_URI` to `https://YOUR-LMS-DOMAIN/admin/marketing/meta-ads/callback` (or leave it blank to use the `APP_URL`-based default in `config/services.php`).
4. Add that exact URL to the Meta app's Valid OAuth Redirect URIs and enable the required Facebook Login and Marketing API products/permissions.
5. Clear Laravel's cached configuration using the deployment's normal process and sign in as an LMS administrator.
6. Open **Admin → Marketing → Meta Ads**, connect the Meta account, and select an ad account and Page. The connected user must have advertising access to both assets; the Page must expose the `ADVERTISE` task.

## Creating a paused LMS boost

1. Open **Admin → Marketing → Meta Ads** and select a BDT ad account.
2. Use the boost form to enter a campaign name, ad message, public image URL, LMS landing-page URL, daily budget in BDT and age range. Select an accessible Facebook Page.
3. Submit **Create paused campaign**. The LMS creates the campaign, ad set, creative and ad with every object set to `PAUSED`.
4. Review the draft in Meta Ads Manager. Only when ready, use **Resume campaign** in the LMS and confirm the spend warning. For LMS-created campaigns, the ad and ad set are activated before the campaign.

This first release creates website-traffic campaigns from a manually supplied landing-page URL. It does not yet provide a dropdown picker for LMS courses/notices/offers, custom audiences, lead forms, sales/conversion objectives, schedule controls or automatic budget optimization. Meta may reject requests until the developer app and Marketing API permissions have the required review and access level. No production deployment is implied by merging this code.
