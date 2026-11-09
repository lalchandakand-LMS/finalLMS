# Course Management, Flash Sale, Question Sets ও PDF Store — পূর্ণাঙ্গ চাহিদাপত্র

প্রকল্প: **Alom's English Center LMS**  
ইস্যু: [#73 — Course Content Studio & Digital Products](https://github.com/lalchandakand-LMS/finalLMS/issues/73)  
প্রাথমিক বাস্তবায়ন PR: [#74](https://github.com/lalchandakand-LMS/finalLMS/pull/74)

এই নথিটি মালিকের দেওয়া নির্দেশনার পূর্ণাঙ্গ, যাচাইযোগ্য product requirement। এটি কেবল UI mockup নয়; প্রতিটি action-এ database persistence, server-side validation, authorization, audit trail এবং automated tests থাকতে হবে।

---

## ১. Course Management — Admin workspace

Course Management-এর মধ্যে আলাদা navigation/tab থাকবে:

1. Content Posts
2. Course Categories
3. Course Builder
4. Flash Sales
5. Question & Answer Sets
6. PDF/eBook Products
7. Orders & Enrollments
8. Reports & Analytics

Admin একই workspace থেকে সব content, course, category, campaign, question set এবং digital product পরিচালনা করতে পারবেন। বিদ্যমান Branch Panel আলাদা থাকবে; branch-level access থাকলে তা বিদ্যমান role/permission policy অনুযায়ী সীমাবদ্ধ হবে।

প্রতিটি listing screen-এ search, filters, status badge, pagination, sort, bulk selection/action, create/edit/view এবং confirmation-সহ delete থাকবে। Delete করার আগে সংশ্লিষ্ট enrollment, payment, exam attempt, order ও download entitlement-এর dependency যাচাই করতে হবে। ঐতিহাসিক transaction বা শিক্ষার্থীর progress cascade করে মুছে ফেলা যাবে না।

## ২. Content Post / Video Post তৈরি

প্রতিটি post-এ নিচের field ও action থাকবে:

| Field | Requirement |
|---|---|
| Title | আবশ্যিক; সর্বোচ্চ দৈর্ঘ্য ও unique/SEO-friendly slug validation |
| Short Description | সংক্ষিপ্ত পরিচিতি; course card ও listing-এ দেখাবে |
| Long Description | পূর্ণ বিবরণ; নিরাপদ rich-text content |
| Video Link | YouTube/Vimeo/অনুমোদিত provider URL অথবা সমর্থিত private video asset |
| Post Thumbnail | JPG/PNG/WebP; MIME, dimension ও file-size validation |
| SEO Title | ঐচ্ছিক; না দিলে title fallback |
| SEO Description | ঐচ্ছিক; search/social preview-তে ব্যবহার |
| Tags | একাধিক tag; search/filter-এ ব্যবহার |
| Course Category | Admin তৈরি করা category থেকে এক বা একাধিক category নির্বাচন |
| Status | Draft, Published, Archived |
| Publish Schedule | নির্দিষ্ট তারিখ/সময়ে publish; scheduling infrastructure থাকলে queued publish |

### Post actions
- Create, Save Draft, Preview, Publish, Unpublish, Edit, Update, Archive এবং Delete।
- Draft বা scheduled post public listing/API-তে প্রকাশ করা যাবে না।
- Post edit করলে সংশ্লিষ্ট course-এর section/order অক্ষত থাকবে; course curriculum-এ পোস্টটি দৃশ্যমান/অদৃশ্য হওয়ার নিয়ম স্পষ্টভাবে নিয়ন্ত্রিত হবে।
- Thumbnail/video বদলালে পুরোনো file reference নিরাপদভাবে পরিষ্কার করতে হবে; অন্য post/course-এ ব্যবহৃত shared media মুছবে না।
- External video URL allowlist দিয়ে যাচাই করতে হবে; arbitrary iframe/HTML গ্রহণ করা যাবে না।
- Post একাধিক category-তে যুক্ত করলে প্রত্যেক নির্বাচিত category-র content listing-এ পোস্টটি আসবে।

## ৩. Course Category তৈরি ও পরিচালনা

প্রতিটি category-তে থাকবে:
- Category name, slug, short/long description
- Category thumbnail/banner
- SEO title/description
- Sort order / display priority
- Active/Hidden status
- Parent category (ঐচ্ছিক; hierarchy সমর্থন করলে cycle validation)
- Category-wise post/course count

Admin category create/edit/update/activate/deactivate/delete করতে পারবেন। Category delete হলে সম্পর্ক detach বা অন্য category-তে reassign হবে; post, course, payment, enrollment বা history মুছে যাবে না। Course ও post—দুটিই একাধিক category-তে থাকতে পারবে।

## ৪. Course Builder / Course Create

Admin course তৈরির সময় বিদ্যমান Content Post/Video Post library থেকে ইচ্ছামতো এক বা একাধিক published post নির্বাচন করবেন। নির্বাচিত পোস্ট দিয়ে course curriculum তৈরি হবে।

### Course information
- Course title, slug, short/long description
- Course thumbnail/cover/banner
- এক বা একাধিক category
- Teacher/Instructor বা course owner
- Regular price, currency, optional offer price
- Learning outcomes, prerequisites, target audience
- Estimated duration, level, language
- SEO title/description, tags
- Status: Draft, Preview, Published, Archived

### Curriculum builder
- Section/module create, rename, reorder ও delete
- Post/lesson নির্বাচন, drag-and-drop ordering
- একই post একাধিক course-এ reuse করা
- Free preview/sample lesson চিহ্নিত করা
- Section ও lesson duration
- Course-level drip/release schedule (পরবর্তী উন্নয়ন)
- Course publish-এর আগে required information ও valid lesson যাচাই

### Course actions
Create, Save Draft, Preview, Publish, Edit, Update, Unpublish, Archive, Duplicate এবং Delete। Delete operation-এ enrollment/payment/attempt/history থাকলে hard delete নয়—archive/soft-delete বা controlled detach ব্যবহার করতে হবে। Existing Course, Lesson, Enrollment, Payment ও LessonProgress record/ID backward compatible রাখতে হবে।

## ৫. Flash Sale — Course ও Category discount campaign

Admin Flash Sale তৈরি করার সময়:
- Campaign name/title, banner/description
- এক বা একাধিক নির্দিষ্ট course নির্বাচন
- অথবা নির্বাচিত category-র eligible course target করা
- Discount type: percentage অথবা fixed amount
- Discount value এবং প্রয়োজনে minimum payable price
- Start date/time ও end date/time (timezone-aware)
- Per-student purchase limit
- Total redemption/sales cap
- Campaign priority
- Draft, Scheduled, Active, Paused, Expired, Archived status
- Homepage Flash Sale tab/section show-hide এবং display order

### Pricing and checkout safety
- Campaign eligible কি না server-side-এ checkout-এর সময় যাচাই হবে।
- Start time-এর আগে, end time-এর পরে, paused/draft/expired/cap-full campaign discount দিতে পারবে না।
- Client/browser থেকে পাঠানো final price বিশ্বাস করা যাবে না।
- একাধিক campaign match করলে নির্ধারিত priority ও একটিমাত্র discount policy প্রয়োগ হবে; unintended stacking নিষিদ্ধ।
- Discount কখনো base price-এর বেশি হতে পারবে না; final payable negative/zero অনিচ্ছাকৃতভাবে হতে পারবে না।
- Order/payment record-এ base price, campaign ID, discount type/value, discount amount ও final price snapshot হিসেবে সংরক্ষণ করতে হবে।
- Refund/chargeback/duplicate callback হলে redemption count ও enrollment entitlement idempotentভাবে reconcile করতে হবে।

## ৬. Question & Answer Set Management

Admin reusable question set তৈরি করবেন। প্রতিটি set-এ:
- Set title, description, subject/category, tags, difficulty
- Course/module/lesson attachment
- Draft, Preview, Published, Archived status
- Time limit, pass mark, attempt limit (যেখানে প্রযোজ্য)
- Question/option randomization এবং প্রশ্নের সংখ্যা বাছাই

প্রশ্নের ধরন:
1. MCQ — এক বা একাধিক option configuration; প্রথম release-এ single-correct MCQ বাধ্যতামূলক।
2. True/False
3. Short Answer — manual বা configured answer checking
4. ভবিষ্যৎ extension: matching, fill-in-the-blank, file response

প্রতিটি প্রশ্নে থাকবে prompt, options, correct answer/key, marks, explanation, difficulty, tags এবং order/position। Admin প্রশ্ন add/edit/update/delete/reorder এবং question bank থেকে reuse করতে পারবেন।

### Assessment security
- Student-এর response API/UI-তে correct answer বা grading key প্রকাশ করা যাবে না।
- Attempt শুরু হলে question selection, order, marks ও assessment rules snapshot/freeze হবে।
- Question set edit করলে চলমান/পুরোনো attempt-এর historical result বদলানো যাবে না।
- Attempt ownership, time window, attempt count, duplicate submission ও grading server-side enforce করতে হবে।
- Exam/question-bank-এর বিদ্যমান teacher ownership ও authorization policy দুর্বল করা যাবে না।

## ৭. PDF / eBook Store

Admin PDF product তৈরি করবেন। Product fields:
- Title, slug
- Short description, long description
- Cover image
- PDF file (private storage)
- Optional public sample/preview PDF বা সীমিত preview pages
- Product category/tags
- Regular price, optional discount/flash-sale eligibility
- SEO title/description
- Author/publisher, language, page count, edition (ঐচ্ছিক)
- Draft, Published, Archived status

### PDF actions and commerce
- Create, Save Draft, Preview, Publish, Edit, Update, Replace File, Archive, Delete।
- PDF file public web directory-তে raw public URL হিসেবে রাখা যাবে না।
- Verified successful payment/authorized entitlement ছাড়া full PDF view/download করা যাবে না।
- Protected reader/download controller প্রতিবার user, order/payment, entitlement, product state এবং download limit যাচাই করবে।
- Signed/short-lived URL বা authenticated stream; permanent public storage URL নয়।
- Download audit: user, product, order, timestamp, IP/device metadata (privacy policy ও retention rules মেনে)।
- Admin entitlement revoke/restore, refund policy, download limit ও sales report পরিচালনা করতে পারবেন।
- Payment callback idempotent হতে হবে; একই transaction থেকে duplicate entitlement তৈরি হবে না।
- Admin-এর upload MIME, extension, file-size এবং storage quota যাচাই হবে।

## ৮. Homepage, Search ও Student Experience

- Public course catalog-এ published course ও category-ভিত্তিক browsing।
- Course detail page-এ thumbnail, description, instructor, price/discount, curriculum, preview lesson, category, FAQ/related courses।
- Active Flash Sale-এর countdown ও discount badge; সময় শেষ হলে স্বয়ংক্রিয়ভাবে regular price দেখাবে।
- Student dashboard-এ purchased/enrolled course, progress, lesson completion, purchased PDF/eBooks ও assessment results।
- Search/filter: title, category, tag, level, price range এবং content type।
- Draft/unpublished content কখনো public search, sitemap বা student API-তে ফাঁস হবে না।
- Bengali ও English UI/validation message/labels সমর্থন করতে হবে।

## ৯. Reports & Analytics

Admin dashboard/report-এ:
- Course-wise enrollment, revenue, completion rate
- Category-wise course/post count ও revenue
- Post/lesson views ও engagement (privacy-aware)
- Flash Sale redemption, discount cost, conversion, campaign performance
- Question set attempt, average score, pass rate
- PDF/eBook sales, refunds, downloads, revoked entitlements
- Date range, category, course, branch এবং payment status filter
- CSV export (permission-controlled)

## ১০. Security, data integrity ও operations

- Admin-only create/update/delete/publish actions; explicit authorization policy/middleware।
- CSRF, validation, rate limiting, safe output escaping, audit logs।
- File upload validation by MIME + extension + size; random storage names।
- Private PDFs ও protected media public storage path-এ expose করা যাবে না।
- Database transactions for course assembly, publish, campaign redemption, payment entitlement ও bulk actions।
- Foreign key/index/unique constraints; safe rollback; additive migrations।
- Soft-delete/archive where historical references exist।
- Existing payment gateway, bKash/Nagad records, enrollment, exam attempts, certificates ও lesson progress অক্ষত রাখা।
- PHP 8.3 এবং cPanel shared hosting compatible implementation; required storage link/permissions/cron/queue fallback লিখিতভাবে document করতে হবে।
- Error logs-এ secret, payment credential বা private PDF path leak করা যাবে না।

## ১১. Required automated acceptance tests

### Posts/categories
- Admin CRUD and validation
- Multi-category assignment and category filtering
- Draft/private post cannot be accessed publicly
- Invalid video URL and unsafe upload rejected
- Shared media is not deleted while referenced

### Course builder
- Select and order posts across multiple sections
- Draft/preview/publish state transitions
- Empty/invalid course cannot be published
- Existing enrollment/payment/progress survives edits/archive
- Student sees only entitled lessons

### Flash sale
- Start/end boundary and timezone behavior
- Paused/expired/capped campaign excluded
- Percentage/fixed discount calculation
- No price tampering or unintended stacking
- Per-student/total cap concurrency and idempotency
- Order stores immutable price snapshot

### Question sets
- Question CRUD and ordering
- Correct answer never leaked to student before grading
- Ownership/authorization enforced
- Randomized attempt snapshot stable after question edits
- Time limit, attempt cap and duplicate submission enforced

### PDF/eBook
- Product CRUD and private file storage
- Unpaid/unauthorized user denied full file
- Paid user receives protected access
- Revoked/refunded entitlement denied
- Duplicate payment callback idempotent
- Download rate limit/audit event verified

### Release
- Laravel feature/unit tests pass on exact PR HEAD
- cPanel/package build passes
- CodeQL passes
- Staging migration/installation verified
- Desktop/tablet/mobile browser acceptance and accessibility checks recorded

---

## ১২. Completion definition

কোনো module-কে Complete বলা যাবে না যতক্ষণ না:
1. Database schema, model, controller/service, routes/policies এবং সম্পূর্ণ admin UI তৈরি হয়েছে।
2. Public/student workflow end-to-end কাজ করে।
3. CRUD, draft, preview, publish, edit, update, archive/delete বাস্তব database operation-এ কাজ করে।
4. Authorization, validation, audit, file/payment security কার্যকর।
5. সংশ্লিষ্ট automated tests exact current commit-এ pass করে।
6. Laravel package/CI এবং CodeQL pass করে।
7. Staging/browser acceptance সম্পন্ন অথবা স্পষ্টভাবে outstanding হিসেবে নথিভুক্ত।

**গুরুত্বপূর্ণ:** এই নথি হলো পূর্ণাঙ্গ scope ও acceptance contract। কেবল এই ফাইল যোগ হওয়া মানে feature implementation সম্পন্ন নয়। প্রতিটি module-এর বাস্তব কোড, পরীক্ষা ও staging verification আলাদাভাবে সম্পন্ন করতে হবে।
