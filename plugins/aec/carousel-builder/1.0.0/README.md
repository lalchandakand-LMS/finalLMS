# Carousel Builder

A reusable website carousel plugin for Alom's English Center.

## Features
- Admin CRUD for multiple named carousels, with independent publication status, schedule and display order.
- Content sources: manually curated cards, published courses, teacher profiles and approved course testimonials.
- Manual card editor with add/remove and drag-to-reorder interactions.
- Responsive desktop/mobile card counts, maximum item count, autoplay interval, arrows and page dots.
- Accessible native horizontal scrolling, keyboard arrow navigation, reduced-motion support and pause-on-hover/focus.
- HTTPS-only external images/links; site-relative URLs are supported. Blade escapes all text and attributes.
- Dynamic content is queried from the LMS on render; unpublished courses and unapproved testimonials are excluded.

## Installation
Build and sign this package through the repository's trusted plugin release process. Install the signed archive from Admin → Plugin Manager, activate it, and grant the `carousels.manage` permission. The Git source package itself is not an installed/activated release. Clear Laravel route cache before plugin activation as required by the plugin runtime.

## Homepage placement
When active, the plugin supplies published/scheduled carousels to the homepage view composer. The homepage includes the plugin's public view only when the package runtime has loaded it.
