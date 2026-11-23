# Redwan's Method Benchmark Audit — 2026-11-23

**Target LMS:** Alom's English Center  
**Compared public platform:** https://www.redwansmethod.com/  
**Exam subdomain:** https://exams.redwansmethod.com/  
**Repository snapshot reviewed:** `main` at `dfb8dd4ac746a7fcfd06708d4eaa70410e2eb8dc`  
**Homepage statistics:** PR #426 is in progress; do not treat its ten-metric homepage as merged until its exact-head Laravel CI passes and the PR is merged.  
**Production status:** no deployment or live payment activation was performed.

## 1. Executive summary

Alom's English Center LMS already has substantial learning and administration foundations: public course discovery, course detail and preview lessons, enrollment and payment review, course combos, flash sales, eBook sales, student learning progress, timed MCQ exams, question banks, assignments, attendance, live-class scheduling, certificates, quiz contests, a CMS and a configurable navigation menu.

The largest visible differences from Redwan's Method are not the core LMS foundations. They are the public-facing discovery and engagement surfaces around them:

1. A public instructor directory with subject expertise and profile details.
2. A dedicated Learn landing page that explains learning paths and leads students into their courses.
3. A public exam/leaderboard experience, plus optional OMR scanning for paper exams.
4. A shareable achievement/result-card generator in addition to completion certificates.
5. A physical-products storefront, if Alom's intends to sell printed books or merchandise.
6. A protected online eBook reader; the current LMS eBook flow provides sample-page previews and protected PDF downloads.
7. Better default public navigation to already-existing eBooks, combos, contests and teacher applications.
8. A complete set of default policy pages, even though custom CMS pages can already be created.

Do not copy Redwan's academic/SSC-HSC product strategy blindly. Alom's English Center should adapt these ideas to English learning: Spoken English, Grammar, IELTS, Pronunciation, Vocabulary, Business English, placement assessments and verified course achievements.

## 2. Page-by-page comparison

| Redwan's Method public surface | Evidence on Redwan's Method | Alom's LMS current source evidence | Assessment |
|---|---|---|---|
| Home | Featured courses, ongoing online batches, trust statistics, learning-path cards, student reviews and company information. | `resources/views/landing.blade.php` includes featured courses, open batches, learning paths and student comments; CMS Website Builder and custom sections are available. PR #426 adds ten database-backed aggregate metrics, pending CI/merge. | **Strong parity.** Finish PR #426 and ensure all home sections have real admin-managed content and useful mobile layouts. |
| Courses | Public course catalog with class/year filters, top picks, free and paid courses, student counts, discounts, combo bundles, coupon messaging and pre-book countdowns. | `CourseController@index`, `resources/views/courses/index.blade.php`, course categories/academic levels, free/paid and duration filters, popularity sort, course combos, flash sales and wishlist. | **Strong core parity.** Review course-card hierarchy, sale-price display, search/filter discoverability and the web checkout coupon journey. |
| Course detail | Course description, demo class, multiple course instructors, student count, coupon entry, enrollment state, and live-class/exam inclusion indicators. | `CourseController@show`, `resources/views/courses/show.blade.php`: demo lesson, primary teacher, course content, lesson previews, enrollment, batch selection, price/flash-sale display, student count and quiz/question-set links. | **Mostly covered.** Current course model has one primary `teacher_id`; multi-instructor course assignment and a clear live-class/exam feature summary are not verified on the public detail page. |
| Learn | A dedicated top-level Learn navigation item exists on Redwan's Method. Its body was not fully exposed by the public crawler, so its exact internal workflow is not independently confirmed here. | Student learning is available through `/my/courses`, `CourseController::myCourses`, lesson/content progress and resume tracking. No dedicated public `/learn` landing route was found. | **Partial.** Core learning exists after login; add a public learning hub explaining the method, learning paths, sample lessons and how to resume study. |
| Instructors | Public directory with separate instructor panels, subject labels, qualifications and affiliations. | Teacher application/verification and admin review exist through `TeacherApplicationController`; teachers have a separate portal and course ownership. No public instructor-directory route or profile view was found. | **Gap.** Build public teacher profiles with approved biography, subject, qualifications, profile image, courses and safe contact/CTA fields. Never expose teacher KYC/NID documents. |
| Exams | Separate exam platform promotes live exams, digital participation, OMR sheet scanning, exam analytics and student rankings/leaderboards. | `ExamController` supports timed MCQ exams, one-attempt controls, question banks, randomized questions, automatic scoring and teacher result reports. `QuizContestController` supports scored contests and ranked published winners. | **Partial.** Core online exams and contest rankings exist. OMR scanning and a public exam hub with broader student analytics/leaderboards were not found. OMR is optional unless paper-based exams are part of Alom's operations. |
| Products | Public physical-products shop with merchandise, prices, stock status and signed-in ordering. | `Product` and `AdminProductController` currently scope public/admin product workflows to `type=ebook`; no public physical-products route was found. | **Gap / business decision.** Add inventory, shipping address, delivery status, stock management and order fulfillment only if Alom's plans to sell physical books or merchandise. |
| E-Books | Digital library with free/premium eBooks, sample access and protected online reading; individual pages advertise lifetime access and protected PDF viewing. | `EbookStoreController` and `resources/views/ebooks/*` provide a public catalog, product detail, private sample-page previews, orders, protected PDF downloads, download limits/expiry and payment verification. | **Good foundation; partial parity.** Add a protected browser reader with session authorization and optional per-user watermarking. Preserve current download entitlement controls. |
| Result | Public board-result information page links students to official Education Board result portals. | Student exam results, teacher result reports, course-completion certificates and quiz-contest results exist. No public board-result landing page was found. | **Different domain fit.** For an English center, prioritize placement-test and course-assessment result lookup; add official board-result links only if school-exam support is intended. |
| Achievement Card | SSC result poster generator with photo upload, premium design, certificate/achievement styling and Facebook sharing; one poster per result. | `Certificate` model and `resources/views/certificates/show.blade.php` provide a course-completion certificate. No achievement-card/poster generator was found. | **Gap.** Build shareable course-completion, speaking-challenge and assessment cards from verified LMS results, with QR verification and controlled regeneration. |
| About / Contact / FAQ / Policies | Public About, Contact, Terms, Privacy and Refund pages are linked from the competitor footer. | `/about`, `/contact`, `/faq`, published custom pages and CMS are present. No dedicated default Terms, Privacy or Refund route was found in `routes/web.php`. | **Partial.** Publish default policy pages and put them in the footer; retain admin editing through the CMS. |
| Teacher recruitment | Public teacher directory and platform identity. | Alom's has a teacher application form, mobile verification, document submission and administrator approval. | **Strong recruitment workflow, missing discovery.** Add approved public profiles after teacher consent. |

## 3. Navigation and discovery findings

The current public navigation component is `resources/views/partials/public-navigation.blade.php`. It includes Home and Courses, optional Flash Sale, CMS pages, admin-configured menu items, and authenticated portal links. This is flexible, but the default menu does not clearly expose all existing public modules.

Recommended default menu structure:

- Home
- Courses
- Learning Paths
- Instructors
- Live Classes / Batches
- Exams & Practice
- eBook Library
- Course Combos
- Results & Achievements
- About
- Contact

Only show links when the corresponding module is enabled and has published content. Keep the menu editable through the existing Main Menu Builder. On mobile, retain the accessible drawer and make the most important actions visible without requiring several taps.

## 4. Design and conversion improvements

### Already present in Alom's LMS

- Responsive landing page and course cards.
- Admin-managed homepage content and custom pages.
- Course categories and academic-level metadata.
- Course search, filters, popularity sorting and pagination.
- Free/paid courses, course combos, flash sales and wishlist.
- Demo lesson and preview-only content.
- Student, teacher and admin workflows.
- English/Bangla locale selector.

### Recommended refinements

1. **Course cards:** make the title, learning outcome, level, duration, instructor, student count and current price readable at a glance. Display original price, sale price and savings consistently when a real active discount exists.
2. **Course detail:** add a compact course-facts panel (instructor(s), total lessons, duration, live-class availability, exam availability, certificate eligibility and access period). Never display an availability claim unless the underlying course/batch data confirms it.
3. **Learning paths:** make paths actionable. Each card should lead to a filtered course list rather than a decorative section only.
4. **Instructor profiles:** use approved profile photos, subject/expertise tags, biography, qualifications and linked published courses. Keep KYC documents private.
5. **Trust metrics:** use database-backed aggregate metrics only. Do not use fabricated numbers or competitor statistics. PR #426 is the current implementation candidate for ten homepage metrics.
6. **Reviews:** show only approved, attributable learner reviews and provide moderation controls.
7. **Mobile conversion:** test the full journey from home → course → registration/login → batch selection → payment → course access on narrow screens.
8. **Accessibility:** verify keyboard navigation, visible focus, semantic headings, contrast, descriptive image alt text, form labels and reduced-motion behavior.

## 5. Prioritized implementation roadmap

### P0 — Finish and validate the current release work

- Merge PR #426 only after exact-head Laravel CI and CodeQL pass.
- Verify homepage statistics on a fresh MySQL migration and confirm the guest homepage still redirects authenticated users to the dashboard.
- Add default navigation links for existing public modules (eBooks, combos, contests and teacher application), guarded by module/content availability.
- Publish editable Terms, Privacy and Refund policy pages.
- Complete the current Bangla translation audit for all public catalog and enrollment screens.

### P1 — Highest-value public learning features

- Public instructor directory and safe instructor profile pages.
- Public Learn hub with English-learning paths and sample lessons.
- Course detail facts panel and multi-instructor assignment, if operationally needed.
- Protected online eBook reader, retaining sample previews and download entitlements.
- Shareable achievement-card generator backed by verified course/exam results.
- Public exam hub with practice tests, exam schedule and learner performance insights.

### P2 — Optional commercial/assessment extensions

- Physical product storefront with inventory, shipping and order tracking, only if physical merchandise is part of the business.
- OMR scanning pipeline for offline paper exams, including image-quality checks, answer-key versioning, confidence thresholds, human review and audit logs.
- Official board-result links only if Alom's students need SSC/HSC board-result support.
- Advanced leaderboards with privacy controls, consent, anti-cheating review and configurable publication.

## 6. Security and trust requirements for any new feature

- Never copy competitor user data, content, images, logos, text or proprietary design assets. Use only the high-level product ideas.
- Public instructor profiles must be opt-in and must not expose teacher KYC, NID, phone OTP or private documents.
- Achievement cards must use verified LMS records, unique verification IDs/QR codes, rate limits and an auditable regeneration policy. Do not let users publish unverified marks as official results.
- eBook reading/download must verify purchase entitlement on every request; do not expose private storage paths or permanent public PDF URLs.
- Physical product orders require server-side price/stock validation and a clear refund/delivery workflow.
- OMR results require confidence scoring and human review for ambiguous marks; never silently finalize low-confidence scans.
- Public leaderboards must support privacy/opt-out settings and avoid exposing sensitive student records.
- Payment-provider activation requires sandbox verification, signed callbacks, idempotency and a tested refund/reconciliation process.

## 7. Evidence and limitations

Repository evidence reviewed:

- `routes/web.php`
- `app/Http/Controllers/CourseController.php`
- `resources/views/courses/index.blade.php`
- `resources/views/courses/show.blade.php`
- `app/Http/Controllers/CourseComboController.php`
- `app/Http/Controllers/ExamController.php`
- `app/Http/Controllers/QuizContestController.php`
- `app/Http/Controllers/EbookStoreController.php`
- `app/Http/Controllers/AdminProductController.php`
- `app/Http/Controllers/TeacherApplicationController.php`
- `resources/views/partials/public-navigation.blade.php`
- `resources/views/certificates/show.blade.php`

Public competitor pages inspected:

- https://www.redwansmethod.com/
- https://www.redwansmethod.com/courses
- https://www.redwansmethod.com/instructors
- https://www.redwansmethod.com/ebooks
- https://www.redwansmethod.com/products
- https://www.redwansmethod.com/board-result
- https://www.redwansmethod.com/result-card
- https://exams.redwansmethod.com/

Some competitor pages are JavaScript-rendered and were not fully readable through public text extraction. The `/learn`, `/products` and `/ebooks` routes were therefore assessed using the available indexed page content; the exact logged-in Learn workflow and checkout steps were not independently tested. This is a public-site feature benchmark, not a penetration test or a claim that every competitor workflow is error-free.

**Release status remains unchanged:** GitHub CI/CodeQL is not staging acceptance. No production deployment, hosting-file change or live payment activation was performed.
