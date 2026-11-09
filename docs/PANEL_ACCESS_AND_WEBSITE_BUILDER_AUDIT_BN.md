# Alom's English Center LMS — Panel & Website Builder Audit

এই নথিটি `feature/course-content-studio` শাখার কোড ও Feature Test-ভিত্তিক অডিট। Production server-এ বাস্তব browser/device acceptance এখনো আলাদাভাবে করতে হবে।

## ১. প্যানেল ও Role Matrix

| Panel / Role | Menu ও কাজ | Access boundary |
|---|---|---|
| Admin | Course, lesson, users/roles, branch/batch, orders, products/eBooks, coupons, flash sale, exams/question sets, live classes, payments, invoices, refunds, expenses, reports, CMS, media, backups, activity logs, SMS, settings, theme | Admin middleware; last admin self-demotion/lockout protection |
| Guardian | Linked children, course enrollment, attendance, invoice/payment summary, certificates | Admin-created guardian-student link; guardian can only open their own linked student; read-only |
| Student | My courses/progress, attendance, exams/results, assignments, invoices, store orders/downloads, cart, live classes | Student role middleware; lesson completion requires active enrollment |
| Teacher | Assigned courses, own batches, lessons/sections/content posts, attendance, assignments/grading, question bank/exams/results, live classes | Teacher role middleware and ownership checks |
| Branch Manager | Own branch batches and branch students; create/update batches in assigned branch | Branch scoping; cross-branch batch update returns 404 |
| Accountant | Branch-scoped invoices, refunds, expenses and finance reports | Accountant role middleware and branch-scoped finance access |

## ২. Guardian Portal

- নতুন `guardian` role এবং `guardian_student` pivot relation যোগ হয়েছে।
- Admin User Management থেকে Guardian account ও Student account link/unlink করতে পারে।
- Guardian dashboard-এ linked learner-এর course, attendance, invoice/payment এবং certificate summary দেখা যায়।
- Guardian শুধু নিজের linked learner-এর report খুলতে পারে; অন্য guardian-এর link ID দিলে 404।
- Student account role পরিবর্তন হয়ে student না থাকলে guardian report থেকে সেটি বাদ পড়ে।
- Guardian-এর জন্য public self-registration বা guardian নিজে student link করার অনুমতি নেই; admin-ই link নিয়ন্ত্রণ করে।

## ৩. Global Orange Admin Theme

Admin Settings → Custom color system থেকে পাঁচটি color control:
- Primary: `#C2410C`
- Secondary: `#9A3412`
- Accent: `#FED7AA`
- Sidebar top: `#7C2D12`
- Sidebar bottom: `#431407`

সব authenticated panel-এর CSS variables একই saved palette ব্যবহার করে। Color input-এ ছয় অঙ্কের HEX validation আছে; white text-এর জন্য primary, secondary ও sidebar color-এ WCAG AA 4.5:1 contrast check করা হয়। Website Builder-এর public-site brand color আলাদা; তাই public website-এর design বদলালেও admin/teacher/student/guardian/branch/finance panel-এর theme বদলায় না।

## ৪. Website Builder — Deep Feature Review

Website Builder-এ এখন ১৫টি responsive homepage preset:
1. Academy Modern
2. Bold Academy
3. Minimal Learning
4. Editorial
5. Gradient Studio
6. Corporate Training
7. Playful Classroom
8. Midnight Campus
9. Split Screen
10. Classic Institute
11. Glass Campus
12. Vibrant Skills
13. Focus Learning
14. Elegant Academy
15. Community Campus

### Admin controls
- Website name, logo, favicon ও public-site primary color।
- Public background color/style, font family এবং heading/body scale।
- Up to 3 hero slider slides; image assets Media Library থেকে নির্বাচন।
- Up to 12 homepage sections; title/body/image/button, safe URL, background/text color, spacing ও layout।
- Header/footer custom links, footer title/text।
- Reusable custom section block library; save, insert-to-editor and delete.
- Draft save: public homepage না বদলিয়েই work-in-progress সংরক্ষণ।
- Draft preview: separate preview route; desktop/tablet/mobile viewport preview।
- Publish draft: validated draft live homepage-এ প্রকাশ।
- Homepage revisions snapshot এবং rollback (সর্বশেষ 20টি revision)।
- Invalid JavaScript URLs/unsupported colors/layout options reject বা strip করা হয়; HTML/scripts user content হিসেবে render হয় escaped text।

### Public rendering
- Selected preset, hero slides, flash-sale strip, course highlights, custom homepage sections, brand colors, typography, favicon/logo, header/footer links render হয়।
- Media selector image-only assets নেয়; ব্যবহৃত homepage media asset delete করা যায় না।
- Draft preview admin-only; non-admin draft save/preview/custom-block routes-এ forbidden।

## ৫. Automated test coverage added/ported

- Guardian portal: own linked learner visibility, foreign guardian link denial, admin link/unlink, non-admin denial।
- Theme settings: save and render custom colors, invalid low-contrast color rejection, non-admin denial।
- Panel navigation: role-specific sidebar checks।
- Website Builder: 15 presets, theme options, branding, slider, sections, safe links, drafts, preview, publish, revisions, rollback, custom blocks, non-admin denial।
- Existing branch/teacher/student/payment/commerce workflows remain in the same PR test suite.

## ৬. Website Maker content audit note

Website homepage copy-র text-only check-এ overall score 69/100 পাওয়া যায়; SEO 70, content 85, conversion 55। Tool-এর text-only parser hero CTA-গুলোকে detect করেনি এবং target phrase 'English learning courses' copy-তে পায়নি। এটি rendered HTML audit নয়; final pre-launch SEO/accessibility score নিশ্চিত করতে live rendered HTML ও real mobile browser test প্রয়োজন।

## ৭. Release blockers / Manual acceptance

- [ ] Chrome/Firefox/Safari + Android real-device responsive sidebar test।
- [ ] Guardian account দিয়ে actual linked/unlinked learner privacy acceptance।
- [ ] Website Builder preset → draft → preview → publish → rollback flow browser acceptance।
- [ ] bKash merchant sandbox real credential test; callback and amount verification।
- [ ] Nagad merchant integration only after official merchant API credentials/specification.
- [ ] Staging database migration and cPanel install/upgrade test।
- [ ] Production deploy only after exact-head CI/CodeQL success and owner approval.

**Important:** automated tests passing মানেই production payment gateway, SMS delivery, cPanel hosting বা physical-device browser acceptance সম্পন্ন হয়েছে—এমন নয়।
