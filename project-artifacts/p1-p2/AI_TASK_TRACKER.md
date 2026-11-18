# Alom’s English Center LMS — Master AI Task Tracker

Last audited: 2026-11-17
Repository: `https://github.com/lalchandakand-LMS/finalLMS`
Default branch: `main`

## Status Definitions
- **COMPLETE**: Implementation and acceptance tests verified on exact branch head
- **MERGED**: Merged to `main` and post-merge `main` CI verified
- **STAGING VERIFIED**: Tested on isolated HTTPS cPanel staging environment
- **PRODUCTION RELEASED**: Deployed and verified in production
- **BLOCKED**: Waiting on dependency, credentials, or environment gate

## Core UX/UI Roadmap (Issue #306 — Strict Delivery Order)

| Priority | Task ID & Title | Related Issue / PR | Current Status | Missing Functionality | Dependencies | Security Risks | Required Code Changes | Required Automated Tests | Acceptance Criteria |
|---|---|---|---|---|---|---|---|---|---|
| 1 | **Theme Manager** | Issue #306 / PR [#307](https://github.com/lalchandakand-LMS/finalLMS/pull/307) (`feat/theme-manager-foundation` @ `7a95ed2d`) | **In progress** | Pre-read ZIP entry size/symlink validation before `getFromName`; `Schema::hasTable('themes')` guard in `layouts/app.blade.php`; isolated iframe preview + controller-served `preview.png`; `ActivityLog` audit entries; inactive theme delete; expanded negative security tests. | None | Memory exhaustion via oversized compressed entry read before `statIndex`; preview CSS leaking into Admin layout; missing table guard during pre-migration render. | Patch `ThemeManagerController`, `routes/web.php`, `layouts/app.blade.php`, `admin/themes/*.blade.php`, `lang/*.json`, `ThemeManagerTest.php`. | `ThemeManagerTest` covering untrusted signature, prohibited CSS, unexpected files, tampered preview/assets, public-only CSS application, inactive delete, and `ActivityLog` writes. | Exact-head `Laravel CI` & `CodeQL` green; cPanel staging install/preview/activate/reset/rollback verified. |
| 2 | **Visual Page Builder** | Issue #306 (`feat/visual-page-builder`) | **In progress** (patch generated; blocked on Priority 1 merge + CI) | Run migration, PHPUnit (`WebsitePageBuilderTest`), `Laravel CI`, `CodeQL`, and manual drag/drop + mobile browser acceptance after Priority 1 merges. | Priority 1 (Theme Manager) | Stored XSS, unsafe block URLs, cross-page revision IDOR. | Added `draft_payload` & `website_page_revisions`, multi-column block grid, drag-and-drop + keyboard controls, isolated responsive preview, `ActivityLog`. | `WebsitePageBuilderTest` covering columns/blocks, URL sanitization, draft/preview/publish/rollback, cross-page IDOR 404, role checks. | Priority 1 merged first; exact-head `Laravel CI` & `CodeQL` green; staging browser check passed. |
| 3 | **Header/Footer Builder** | Issue #306 | **Blocked** (on Priority 2) | Visual no-code header/footer layout controls, CTA buttons, contact/social links, desktop/mobile controls, preview/publish/reset. | Priority 2 | Unsafe external URLs/schemes (`javascript:`). | Build on existing `header_links`, `footer_title`, `footer_text`, `footer_links` settings. | Validation, safe URL checks, role checks, preview/publish/reset tests. | Bilingual, responsive, rollback-safe, CI green. |
| 4 | **LMS Block Library** | Issue #306 | **Blocked** (on Priority 2–3) | Reusable LMS blocks (Courses, Teachers, Students, Testimonials, generic blocks) with ordering, visibility, presets. | Priority 2, 3 | Leaking unpublished courses or private student/teacher data in public blocks. | Reuse `WebsiteBuilderController` block foundation & public course queries. | Visibility/privacy scoping, invalid config, ordering tests. | No private data exposure; responsive; CI green. |
| 5 | **Media Library** | Issue #306 | **Blocked** (on Priority 2–4) | Search, folders/filters, thumbnail grid, picker modal integration, alt/metadata editing, private vs public disk isolation. | Priority 2–4 | Public exposure of private lesson/student files; unsafe MIME/extension uploads. | Extend `MediaLibraryController` & `MediaAsset`. | Upload validation, search/filter, in-use delete protection, private-file isolation tests. | Safe cPanel storage delivery; CI green. |
| 6 | **Navigation Builder** | Issue #306 | **Blocked** (on Priority 3) | Multi-level nested menus, visibility/role rules, protected system links, safe URL validation, desktop/mobile support. | Priority 3 | Open redirects or `javascript:` links; broken system auth/portal links. | Extend `AdminWebsiteMenuController`. | Nesting, cycle prevention, role visibility, URL sanitization tests. | Keyboard-accessible sorting; bilingual; CI green. |
| 7 | **Global Design System** | Issue #306 | **Blocked** (on Priority 1–6) | Unified design tokens (colors, typography, spacing, buttons, cards, forms, breakpoints), contrast checks, preview & reset. | Priority 1–6 | CSS injection via unvalidated token values. | Extend existing `site_*` tokens in `layouts/app.blade.php` & `WebsiteBuilderController`. | Token allowlist validation, contrast/fallback defaults, reset tests. | Theme-aware defaults; bilingual; CI green. |
| 8 | **Dashboard Builder** | Issue #306 | **Blocked** (on Priority 1–7) | Role-scoped widget add/remove/reorder, persisted layouts, server-side widget authorization & branch scoping. | Priority 1–7 | Privilege escalation / cross-branch or cross-portal data leakage via widget IDs. | Refactor `DashboardController` into authorized widget registry + layout persistence. | Role & branch isolation tests for Admin, Teacher, Student, Branch Manager, Guardian. | Server-side scoped widgets; responsive; CI green. |

## Open PR & Issue Inventory (Supplied tracker snapshot)

| Item | Branch / Link | Current Status | Notes / Blockers |
|---|---|---|---|
| PR #307 | `feat/theme-manager-foundation` (`7a95ed2d`) | **In progress** (Draft) | Tracker snapshot lists `Laravel CI` (`44072465278`) & `CodeQL` (`44072465333`) green; re-check exact head before relying on this. |
| PR #305 | `sync/universal-ai-assistant-main-2026-11-17` | Draft | Reconcile with PR #274 (Issue #257 atomic budgets). |
| PR #303 | `sync/plugin-runtime-academic-levels-main-2026-11-17` | Draft | Tracker snapshot lists CI & CodeQL green; review before merge. |
| PR #301 | `sync/offline-attendance-main-2026-11-17` | Draft | Issue #282 offline attendance sync port onto `main`. |
| PR #300 | `sync/student-tracking-main-2026-11-17` | Draft | Issue #281 student tracking plugin port onto `main`. |
| PR #299 | `sync/website-health-main-2026-11-17` | Draft | Website health monitoring sync onto `main`. |
| PR #277 | `feat/learn-earn-flash-sale-plugins` | Draft | Negative marking & Flash Sale page control. |
| PR #274 | `fix/issue-257-ai-budget-rebased` | Draft | Mergeability `unknown`; overlaps with PR #305. |
| PR #36 | `feature/community-ai-platform` | Draft | Targets `feature/complete-homepage-builder` (not `main`). |
| Issues #241, #254, #257, #281, #282 | Plugin platform & plugins | Open | Reconcile synced PRs after Priority 1–8 / plugin runtime checks. |
| Issues #3, #13, #28, #30, #40, #41, #73 | Core LMS & release readiness | Open | Require staged cPanel install/upgrade, payment/SMS sandbox, mobile QA, and release gates. |
