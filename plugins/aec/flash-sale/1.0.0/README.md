# Flash Sale plugin

This is the first-party \`aec.flash-sale\` module for Alom's LMS. It has no external AI-builder branding or third-party developer branding.

## Plugin-owned features

- Signed plugin manifest and Admin/Public portal declaration.
- Admin campaign create, edit, delete/archive-safe management.
- Course and course-category targeting.
- Percentage and fixed-price discounts.
- Start/end scheduling, campaign priority, maximum redemptions and per-student limits.
- Public Flash Sale page and homepage promotion.
- Admin audit events for campaign changes.
- Route and permission gating through the LMS plugin runtime.
- When the plugin is inactive, public/admin plugin routes are not registered and core checkout pricing returns no Flash Sale discount.

## Shared LMS compatibility schema

The current LMS already owns the legacy \`flash_sales\`, \`flash_sale_course\`, \`flash_sale_course_category\`, \`payments.flash_sale_id\` and \`enrollments.flash_sale_id\` tables/columns. The plugin migration validates that schema and does not recreate, drop or rewrite it. This preserves existing campaign and redemption history during activation, deactivation, upgrade and uninstall.

## Release gates

- Configure the trusted release signing key and produce a signed distributable package.
- Verify plugin install/activate/deactivate/uninstall and data retention in disposable staging.
- Run real browser QA for Admin campaign management, public page, homepage promotion and payment checkout.
- The existing global \`flash_sale_page_enabled\` setting remains an independent switch for the public page and homepage promotion.
