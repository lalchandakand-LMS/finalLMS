# WooCommerce-Inspired Commerce Architecture for Alom's English Center LMS

**Decision:** Course Management, Flash Sales and PDF/eBook selling must follow the proven **WooCommerce product-commerce model**: a central product catalog, taxonomies, product types, cart, checkout, orders, order items, coupons/discount rules, payment lifecycle, customer entitlements and downloadable-product permissions. This is an architectural reference, not a requirement to install WordPress or copy WooCommerce source code. Implement natively in the existing Laravel application and preserve the LMS domain model.

## 1. Core principle: separate learning content from sellable products

Use three distinct concepts:

1. **Content Post / Learning Content** — reusable video/article/lesson content with title, excerpt, body, video provider/URL, thumbnail, SEO, tags and categories.
2. **Course** — a learning programme with instructor, curriculum sections and ordered references to reusable content/lessons. Existing `courses`, `lessons`, enrollments and progress remain authoritative for learning.
3. **Commerce Product** — the purchasable catalog item. A product may represent a course, an eBook/PDF, or a future service/bundle. A course or eBook is not forced into the content-post table.

A product links to its underlying resource using a typed, validated relation (for example `product_type=course` + `course_id`, or `product_type=ebook` + `digital_asset_id`). Avoid unsafe polymorphic relations without foreign-key/ownership validation. One course can have one primary product initially; architecture may later support product variations/bundles.

## 2. WooCommerce-style product catalog

Create a Product Management workspace with these product fields:

- Name/title, slug, SKU (unique when supplied), product type, short description, long description.
- Featured image/gallery and SEO title/description.
- Product categories (many-to-many), tags (many-to-many), featured flag, catalog visibility.
- Regular price, sale price, currency, tax status/class if tax is enabled.
- Status: draft, pending review (optional), published, private, archived/trashed.
- Virtual flag and downloadable flag.
- Linked course or private digital file, product-specific purchase limits and download permissions.
- Created/updated by, published timestamps and audit metadata.

Initial product types:
- **Course (virtual):** checkout grants course enrollment/access after verified payment.
- **Downloadable eBook/PDF:** checkout grants a protected download entitlement.
- **Bundle (later extension):** one product grants multiple course/product entitlements.

Catalog operations: create, edit, update, duplicate, preview, publish, unpublish, archive/trash, restore, bulk edit, bulk delete (with dependency safeguards), search, filtering, sorting and pagination. A product cannot be published unless its linked resource exists, is valid and is available for sale. Product deletion must not cascade-delete courses, lessons, orders, payment history, exam attempts or existing entitlements.

## 3. Taxonomies and content categories

Keep Course Categories, Content Categories and Product Categories semantically clear. Reuse a shared taxonomy engine only if each taxonomy has an explicit taxonomy/type discriminator and policies prevent accidental cross-type mixing.

Each taxonomy term supports name, slug, description, image, parent term (optional), SEO, display order and active state. Products can have multiple categories and tags. Admin can create categories while editing a product, and the new term must be persisted and immediately selectable. Deleting a term detaches its relationships; it never deletes products or course content.

## 4. Product data and price model

Keep prices as decimal database values with explicit currency; never use binary floating-point for money calculations. Define a single server-side pricing service used by product pages, cart, checkout, order creation and payment verification.

Pricing precedence:
1. Valid scheduled product sale price.
2. Eligible coupon/flash-sale promotion according to explicit stacking policy.
3. Regular price.

Show regular price, active sale price, discount amount/percentage and final price transparently. Validate sale price is non-negative and below regular price. Recalculate all totals server-side at checkout; ignore client-submitted totals. Persist immutable order-item snapshots (product name/SKU/type, linked resource reference, quantity, regular price, sale price, coupon/campaign discount, tax if applicable, final line total). Later edits to product price/title must not rewrite historical orders.

## 5. Cart and checkout lifecycle

Implement a persistent authenticated cart (and optional guest cart only if guest checkout is deliberately enabled):
- Add/remove product, update quantity, validate availability and purchase limits.
- Prevent duplicate course purchases where the student already owns/enrolled in the course, unless admin explicitly allows renewal/gifting.
- Revalidate product status, linked resource, stock/download limits, coupon and promotion on every cart refresh and checkout.
- Course products are normally quantity 1. Digital file products may have quantity 1 by default; configurable quantity only where business rules allow.
- Checkout collects the minimum required customer details, displays itemized subtotal, discount, tax (if configured) and grand total.
- Use database transactions and idempotency keys to prevent duplicate orders/enrollments/entitlements.
- Do not mark an order paid from a browser redirect alone; require verified gateway callback/server-to-server confirmation or audited admin verification for manual payment.

Order states (WooCommerce-inspired, adapted to LMS): pending payment, on hold, processing, completed, failed, cancelled, refunded, partially refunded. Payment transaction state remains separately auditable. Order state transitions must be validated and logged.

## 6. Orders and order items

An order contains customer/student, order number, billing/contact snapshot, currency, item subtotal, discounts, tax, total, payment method, payment reference, status, timestamps and audit metadata.

Each order item stores its own product/resource snapshot, quantity, unit price, discount, tax and line total. Never calculate old invoices from the current product record. Admin order workspace supports search/filter, order details, notes, manual status transitions with permission checks, refund records and resend receipt/entitlement notification.

Payment providers (bKash, Nagad and future gateway) must implement a common gateway contract. Keep provider credentials in protected configuration, verify signatures/tokens, validate amount/currency/order reference, prevent replay, and process callbacks idempotently. Manual payment approval must record approving admin and evidence/reference.

## 7. Coupons and Flash Sales

Use a reusable promotion engine compatible with WooCommerce coupon concepts:
- Coupon code, percentage/fixed-cart/fixed-product discount, start/end, minimum/maximum spend, product/category inclusion/exclusion, per-user usage limit, total usage limit, individual-use flag and active state.
- Flash Sale is a scheduled campaign with banner/title, start/end, priority, course/product/category targets, percentage/fixed discount, per-customer and global redemption limits, homepage placement and status.
- Campaign can target selected courses directly or all eligible course products in selected course/product categories. Resolve the target to eligible products at pricing time.
- Define deterministic priority and no-stacking defaults. If stacking is later enabled, explicitly define calculation order and caps.
- Reserve/reconcile usage safely under concurrent checkout; do not exceed redemption limits.
- Save promotion/coupon identifiers and discount snapshots on order items/order. Expired or paused campaigns must not change already-paid order totals.
- Homepage Flash Sale section reads only active, published, currently eligible products and displays an accurate countdown.

## 8. Digital downloads / PDF-eBook permissions

Follow WooCommerce downloadable-product permission concepts, but apply stronger LMS entitlement checks:
- Store original PDF in private disk/storage outside public web root. Public preview/sample is a separate asset.
- Successful verified payment creates a download permission/entitlement tied to order item, customer, product and private asset version.
- Permission includes allowed downloads, expiry (optional), revoked state and download count.
- Every file view/download rechecks authenticated owner, paid/refunded order status, product/asset state, permission expiry/count and rate limits.
- Deliver via authenticated streaming or short-lived signed URL. Never expose a permanent storage path or public bucket URL for paid files.
- Record download audit events. Refund/chargeback revokes access according to configured policy; admin can grant/revoke with an audit reason.
- Replacing an eBook file creates a new asset version; preserve historical order snapshot and define whether previous purchasers receive the updated version.

## 9. LMS fulfilment and access control

On verified paid order:
- Course product: idempotently create/activate enrollment for the correct student and course, attach order/payment references, and grant access based on course policy.
- eBook product: create download permission/entitlement for the purchased asset.
- Bundle: fulfil each included resource idempotently.
On refund/cancellation/chargeback: apply configured revocation/suspension policy and preserve progress/history. Do not silently delete enrollment, lesson progress, exam attempts or certificates. Manual admin grants/revocations require a reason and audit trail.

## 10. Admin and customer experience

Admin menus should include Products, Product Categories, Tags, Coupons, Flash Sales, Orders, Payments, Downloads/Entitlements and Reports. Use a WooCommerce-like list-table workflow: search, filters, bulk actions, status badges, quick edit where safe, detail/edit screens and clear empty/error/success states.

Student-facing shop should include catalog search, category filters, product detail, cart, checkout, order history, receipts, course library and My Downloads. Course product pages should clearly explain learning outcomes, curriculum, instructor, previews and access terms. eBook pages should show cover, metadata, sample preview and file/access policy.

## 11. Reporting

Provide sales by date/product/category, gross sales, discounts, net sales, refunds, payment method, top products, course enrollments generated by orders, eBook downloads, coupon/flash-sale usage and outstanding/failed orders. Reports must be based on immutable order/order-item/payment records, not current product prices.

## 12. Security, quality and migration gates

- Admin-only catalog/promotion/order mutation with policies, validation, CSRF, rate limits and audit logs.
- Student can access only their own cart, orders, course entitlements and downloads.
- Protect against IDOR, price tampering, duplicate callbacks, coupon brute force, overselling, path traversal, malicious uploads and arbitrary video/embed URLs.
- Use transactions and row locks for checkout, coupon usage, redemption limits and entitlement fulfilment.
- Add database indexes and foreign keys; soft-delete/archival strategy must preserve financial and learning history.
- Preserve existing `courses`, `lessons`, `enrollments`, `payments`, flash-sale and question-bank data. Use additive migrations and a reversible/backward-compatible rollout.
- Add feature/unit tests for catalog permissions, taxonomy, cart, price calculations, coupon rules, flash-sale boundaries, concurrent redemption, payment verification/idempotency, refunds, course fulfilment, private downloads, entitlement revocation and order snapshots.
- Add Bengali/English translations and responsive admin/student screens.
- Verify Laravel CI, CodeQL, packaged cPanel installer and PHP 8.3 compatibility. Staging/browser acceptance is a separate release gate.

## 13. Explicit non-goals

- Do not install WordPress or make the LMS dependent on WordPress.
- Do not copy WooCommerce code, database schema wholesale, branding or GPL code into this proprietary project.
- Do not treat a blog post or lesson as a purchasable product by default.
- Do not expose paid PDFs in public storage.
- Do not grant course/download access based only on a client redirect or unverified payment status.

**Acceptance principle:** the feature is complete only when catalog → cart → checkout → verified payment → immutable order → course enrollment/PDF entitlement → refund/revocation is demonstrably working end to end with automated tests and staging verification.
