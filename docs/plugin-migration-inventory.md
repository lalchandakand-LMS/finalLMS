# Universal Plugin Platform — Existing Feature Migration Inventory

This inventory prevents duplicate integrations and distinguishes existing application features from completed plugin-runtime extraction. A feature being present in Laravel does not mean it is already independently installable or isolated as a plugin.

## Existing implementations to preserve

| Capability | Existing implementation evidence | Migration target | Current boundary |
|---|---|---|---|
| Payment providers | `PaymentGateway` contract, `PaymentGatewayManager`, bKash adapter and payment tests | Provider-neutral Payment Plugins | bKash is registered; other providers require their own merchant sandbox acceptance |
| Meta Pixel | Existing marketing settings, consent-gated registration event and SEO/marketing tests | Meta Pixel integration plugin | Existing tracking must be preserved; consent remains mandatory |
| Meta Ads / Boost | Meta OAuth/Graph API controller, boost campaign model, campaign hierarchy and integration tests | Meta Ads + Boost plugin | Provider API works only when server credentials and an authorized Meta token are configured |
| SEO | `SeoController` sitemap and robots endpoints | SEO plugin | Sitemap/robots exist; route ownership is mapped; verify provider-specific Search Console APIs separately |
| Search Console | Admin verification token stored in settings and rendered as the verification meta tag | Search Console verification plugin | Site verification token exists; do not claim Search Console API submission/sync without separate implementation |
| SMS | Twilio-backed `SmsGateway`, campaign/recipient jobs and delivery-report tests | SMS provider plugin | Twilio is the current adapter; additional providers need independent adapters and tests |
| Email verification | Existing email verification flow and Laravel mail configuration | Email delivery plugin | Mail delivery depends on server mail configuration; Gmail is not a separate first-party provider adapter yet |
| Notice Board | Persistent notices, branch targeting and dashboard announcement/slider tests | Notice Board plugin | Existing notice data and targeting must remain intact |
| Website/Page Builder | Website Builder, custom pages, homepage configuration and page builder views/tests | Website Builder + Page Builder plugins | Existing homepage design and page/post experience must be preserved; manifests alone are not extraction |
| Course Builder / Content Studio | Admin Course Builder, course curriculum/content and course catalog tests | Course Builder + Content Studio plugins | Existing course data remains in core-owned tables during migration |
| Slider / Carousel | Existing homepage/announcement slider implementation and separate Slider/Carousel manifests | Slider Builder and Carousel Builder plugins | Slider metadata exists; Carousel manifest is declarative only until its reviewed implementation is extracted |
| AI Developer | Admin AI Developer with provider allow-listing, secret redaction, response bounds and daily limits | AI Developer / AI Builder plugin | AI output is advisory/draft-only; it must not autonomously edit, deploy or publish code |
| Academic Levels | Academic Levels model/controller/migration and scoped Course/Batch/Exam/Page integration | Academic Levels plugin | First-party module is mapped; preserve level-specific content relationships |

## Runtime foundation now present

- First-party module catalogue and route ownership gates are separate from signed third-party package records.
- Signed ZIP upload validates manifest shape, trusted Ed25519 key, package SHA-256, private staging and archive safety before extraction.
- Installed package files have a recorded integrity manifest and health check.
- Compatibility/dependency/conflict checks run before package staging.
- Plugin settings are encrypted at rest; setting values are not rendered, logged or flashed back into validation sessions.
- Deactivation is dependency-aware. Uninstall requires an inactive plugin, verifies file integrity, checks dependents and moves code into a private backup without deleting registry or customer data.
- Uploaded PHP entrypoints are never loaded or executed.

## Remaining acceptance gaps — do not mark issue complete yet

1. A reviewed, allow-listed runtime for activation with complete web/API/job/event/navigation gating and per-portal permission grants. Third-party activation remains disabled intentionally.
2. Safe package update and rollback/restore flow, immutable version history and update compatibility checks.
3. Declarative hook/event and job registry with disable-time revocation tests.
4. Explicit role/branch/guardian permission scopes and tenant isolation for each plugin-provided endpoint.
5. Plugin-specific settings schemas, provider-specific secret rotation and configuration validation; generic encrypted key/value storage is only a foundation.
6. Actual Carousel Builder extraction and first-party plugin implementation migration beyond manifests.
7. Integration acceptance for each provider (Meta Pixel consent, Meta Ads OAuth/Graph API, Search Console API where supported, SMS/email providers, storage and payment adapters).
8. AI Builder contract integration with draft preview, human approval/publish, prompt/output audit and cost controls.
9. cPanel staging install/upgrade/restore/browser acceptance. CI packaging is not a substitute for staging acceptance.

## Migration rule

Keep the current production implementation working while each capability is moved behind a reviewed module contract. Do not create parallel tables or duplicate builders just to make a manifest look complete. Migrate one feature at a time, add route/API/job/event ownership tests, preserve data and prove disabled behavior before declaring the module isolated.
