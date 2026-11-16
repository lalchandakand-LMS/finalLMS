# Course Post & Content Studio

The Admin **Course Content Studio** is the single authoring surface for reusable course posts, lessons and paid PDF resources. Course-to-post assignment and ordering remain in the Course Curriculum Builder.

## Authoring

- Create, edit, publish, archive, bulk-update and delete content posts.
- Set title, slug, descriptions, SEO metadata, tags, categories and a safe HTTPS YouTube/Vimeo embed.
- Upload a thumbnail directly (JPG, PNG or WebP, up to 5 MB), replace/remove it, or select an existing Media Library image.
- Upload one private lesson file (PDF, MP4, MP3, JPG, PNG, WebP or ZIP, up to 100 MB). The file is not placed in public storage.
- Select an optional Academic Level. Posts without a level can be reused across levels.

## Access modes

| Mode | Access rule |
| --- | --- |
| Free | Published post and lesson file are publicly accessible. |
| Course enrollment | Student must have an active/completed enrollment in the assigned course, unless the curriculum item is marked as a free preview. |
| Paid PDF | Requires a PDF and a regular price greater than zero. The Studio creates/updates a linked eBook product and uses the existing checkout, manual/bKash payment, admin approval, order history and download-permission system. |

Paid PDF sale price must be lower than regular price. Paid PDF file replacement/removal is blocked after the first order to preserve the purchased file. Thumbnail and pricing changes remain available. Existing orders retain their order-item price snapshot.

## Previews and downloads

- Paid PDF sample pages are managed in the same Studio after the first save; up to 10 private preview images are supported (2 MB each).
- Full paid PDF downloads use the existing download permission, expiry, revocation and download-count enforcement.
- An exhausted, expired or revoked permission cannot download the PDF.
- Course-enrolled students may access course-assigned lesson resources through their course entitlement.
- Private files are delivered by an access-checked route with private/no-store response headers.

## Academic Level and Course assignment

The Studio filter scopes posts by Academic Level. The Course Curriculum Builder offers unassigned posts plus posts matching the course's Academic Level; existing attached posts remain visible so saving a curriculum does not silently detach older content. Use the Course Builder to assign posts to one or more courses and set module, order, duration and free-preview status.

## Operations note

The eBook Store remains the public storefront and the existing eBook Orders screen remains the sales/refund administration surface. Linked Course Studio products are hidden from the standalone eBook authoring list so their content, thumbnail, price and preview pages are edited from the Course Content Studio. Payment approval and order administration are unchanged.
