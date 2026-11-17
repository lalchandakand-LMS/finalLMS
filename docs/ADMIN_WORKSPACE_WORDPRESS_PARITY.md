# Alom's LMS Admin Workspace — WordPress-inspired Functionality Contract

**Product:** Alom's English Center LMS  
**Stack:** Native Laravel, PHP 8.3+, MySQL/MariaDB  
**Decision:** WordPress-inspired administration, not WordPress installation, fork, runtime, database, branding or dependency.

## Product requirement

The unified Alom's LMS Admin must provide the useful management capabilities administrators expect from WordPress as real, working Laravel features—not just a similar sidebar. All core and plugin pages share one admin shell, navigation model, design system, responsive behavior, translations and permission rules. Every feature is marked complete only after implementation and acceptance tests.

## Admin capability map

| Familiar capability | Alom's LMS module | Required behavior |
|---|---|---|
| Dashboard | Dashboard | Role-scoped KPIs, activity, tasks, quick actions, configurable widgets |
| Posts | Blog & Articles | Draft/publish/schedule, categories, tags, SEO, author, featured media, revisions |
| Pages | Website Pages | Slugs, templates, status, SEO, preview, revision history |
| Media | Media Manager | Upload, folders, search, filters, metadata, alt text, preview, usage references, safe delete |
| Appearance | Website Appearance | Header/footer, homepage sections, layout presets, typography, colors, branding |
| Themes | LMS Themes | Signed install, preview, activate, compatibility, update and rollback |
| Customize | Theme Customizer | Live preview, save draft, publish/discard |
| Widgets/Blocks | Content Blocks & Widgets | Reusable blocks, placement, visibility, ordering |
| Menus | Navigation Manager | Drag/reorder, nested items, visibility, safe URL validation, protected system links |
| Plugins | Plugin Manager | Discover, validate, install, configure, activate, deactivate, update, health, uninstall trusted packages |
| Users | User & Role Manager | Search, filters, create/invite, status, roles, scoped permissions, bulk actions, audit |
| Profile | My Profile | Profile, avatar, locale, password, sessions, security |
| Comments | Reviews & Discussions | Moderate course reviews, article comments, reports and abuse |
| Tools | LMS Tools | Safe import/export, data checks, maintenance and diagnostics |
| Settings | System Settings | General, localization, timezone, email, security, integrations, LMS defaults |
| Updates | Update Center | Signed updates, preflight, backup, progress, verify, recovery |
| Site Health | System Health | Database, storage, queue, cron, mail, cache, uptime, diagnostics |
| Export/Import | Data Transfer | Scoped CSV/archive transfer, preview, validation |
| Revisions | Revision History | Compare, restore and audit content/configuration versions |
| Search | Admin Quick Search | Search only records/actions the current user may access |
| Help | Help & Documentation | Contextual help, guides, release notes, support |

## LMS-native modules (in addition to CMS functions)

- Academy: students, guardians, teachers, branches, batches, courses, subjects, categories, admission and enrollment.
- Learning: curriculum, lessons, recorded videos, live classes, downloads, assignments, question bank, exams, grading, certificates and progress.
- Attendance: online/offline capture, QR sessions, corrections, review queue, reports and guardian read-only views.
- Finance: fee plans, invoices, payments, bKash, Nagad, approved gateways, refunds, reconciliation and reports.
- Communication: notice board, in-app notifications, SMS, email, OTP, templates, delivery logs and targeting.
- Growth: coupons, campaigns, affiliate/referral, SEO, reviews and marketing integrations.
- AI: shared AI Core and separately permissioned assistant/tools.
- Operations: backups, restore, storage, security, audit, health, updates and support.

## Shared UI/UX contract

All core and plugin pages use reusable Laravel Blade components/design tokens for app shell, grouped sidebar, toolbar, breadcrumbs, page headers, KPI cards, charts, tables, search/filter/pagination, bulk actions, forms, validation, dialogs, alerts, toasts, badges, tabs, accordions and empty/error states. Keep consistent typography, spacing, colors, icons, focus states and interaction patterns. Support desktop/tablet/mobile, keyboard accessibility, Bangla/English, consistent date/time/currency and RTL readiness. Plugins cannot inject global CSS/JS or replace the admin theme; plugin assets are namespaced and loaded only on authorized pages.

## Native visual content builder

Support reusable sections/blocks (text, headings, images, video embeds, buttons, banners, course grids, teacher profiles, testimonials, FAQ, notices, pricing and CTA), drag-and-drop ordering, desktop/tablet/mobile preview, templates, homepage variants, header/footer layouts, draft/preview/schedule/publish/unpublish, revisions, SEO metadata and media picker. Use safe rich text and allowlisted embeds; never expose arbitrary PHP, SQL or unrestricted script execution. Pages must render safely when builder JavaScript is unavailable.

## Media/storage

Support local storage and approved Drive/S3-compatible providers through a common storage plugin contract. Show usage and provider health; validate MIME/type/size; protect private lesson and assignment files; authorize every download; check references before replace/delete; never expose private files with public URLs.

## Plugin and theme boundaries

Follow `docs/UNIVERSAL_PLUGIN_PLATFORM.md` and `docs/PLUGIN_MANAGER.md`. Plugin Manager owns package trust, permissions, dependencies, lifecycle, health and audit; plugin settings own only their business configuration. Navigation appears only for active plugins and authorized users. Deactivation must block routes, APIs, jobs, schedules, listeners and widgets—not just hide a menu. Themes are signed and cannot override authorization, core routes, private storage or security. Core login, authorization, data integrity and backup/recovery cannot be disabled.

## Security and operational requirements

- Laravel policies/gates and server-side query scoping for every page, API, search, export, download and bulk action.
- CSRF, validation, output escaping, rate limits, secure sessions and audit events.
- Menu visibility never substitutes for authorization; never trust client-side role fields.
- Encrypt secrets at rest; redact them from forms, logs and exports.
- Destructive operations require confirmation and retention/restore checks.
- Student/guardian/teacher data remains private and branch-scoped.
- Stage, preview and validate imports; make reversible where possible.
- Signed updates/packages, verified backup and documented rollback.
- Shared-cPanel compatibility: bounded requests, cron-aware background work, no runtime Composer/shell dependency.
- No production rollout before staging, backup/restore, rollback, security and browser/device acceptance.

## Definition of done

A visible menu item is not an implemented feature. Each capability needs:
1. Authorized Laravel route/controller and policy tests.
2. Model/service and validated migrations where persistence is needed.
3. Working create/read/update/delete or lifecycle workflow.
4. Search/filter/pagination and safe loading/empty/error states where relevant.
5. Bangla/English labels and responsive UI.
6. Audit events for sensitive/destructive actions.
7. Feature tests for allowed/denied roles, branch isolation and invalid input.
8. Desktop/mobile browser, keyboard and accessibility acceptance.
9. Documentation plus fresh-install/upgrade/backup/restore compatibility.

Delivery phases: (A) shared shell/design system; (B) content, media, menus, appearance; (C) plugin/theme lifecycle and Update Center; (D) LMS operations and communication; (E) analytics, tools, health and polish. Phases may overlap when dependencies permit, but every capability needs evidence before being marked done.

## Explicit exclusions

No WordPress installation, WordPress database schema/runtime, compatibility layer or WordPress branding; no copying proprietary themes/plugins; no arbitrary code execution or unreviewed package upload; no weakening LMS permissions, branch isolation, privacy or release security to imitate a WordPress convenience feature. Implement relevant WordPress-like administration capabilities plus Alom's academy-specific workflows.
