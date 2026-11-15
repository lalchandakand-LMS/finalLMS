# Alom's English Center — Student App

Flutter source for the Student application.

## Configure and run

```bash
flutter create --org com.alomsenglishcenter --project-name aloms_student_app --platforms android,ios,web .
flutter pub get
flutter run --dart-define=API_BASE_URL=https://alomsenglishcenter.com/api/v1
```

Override the API URL for staging/local builds using `--dart-define=API_BASE_URL=...`.

## Student sign-in security

- The app generates a cryptographically random 32-byte installation ID and stores it in `flutter_secure_storage` (Android Keystore / iOS Keychain).
- The first installation sends a six-digit SMS OTP to the mobile number registered by the academy. No bearer token is stored or issued before successful verification.
- Additional or previously revoked installations require OTP and Admin approval. The app checks approval status every 15 seconds and receives its bearer token only after approval.
- Bearer tokens are stored in secure storage, validated when restoring a session, and revoked server-side on sign-out.
- If an Admin revokes the device, its next API request is rejected and the app returns to sign-in.

Current screens: sign-in, OTP verification, Admin approval waiting/status screen, secure token persistence, server-side logout, searchable course catalog with instructor/title search, server-side sorting and paginated loading, course details, preview lesson access, enrolled-course progress overview, and a video player for privately hosted lesson media. Video streams use the authenticated API token, server-side enrollment/preview checks, private no-store responses and HTTP byte-range seeking. Course wishlist is available through the mobile API and Student app. Student assignments are available through a paginated list and detail screen, with due-date/late-policy visibility, text-answer or private file attachment submission (PDF, DOC, DOCX, JPG and PNG up to 10 MB), and returned grading status, score and feedback. Attachment uploads use authenticated multipart requests, private storage and owner/enrollment-checked downloads. Course checkout now supports open-batch selection, server-calculated Flash Sale totals, free enrollment, manual payment with optional private proof upload, secure bKash checkout launch and student-owned payment history/details. Persistent cart is now available in the Student app with server-owned cross-device storage, add/remove/clear actions and server-calculated cart totals. The cart now includes coupon validation and manual single-/multi-course checkout with server-priced totals; printable invoices/receipts, course reviews/follow-up UI remains pending. Student notices and support tickets are available in the mobile app; support requests are private to the student and can be answered and closed by Admin. The Student App now includes a Notice Board backed by a student-only API that returns active academy and assigned-branch notices, unread counts, and per-student read status; notice visibility is rechecked server-side when marking an item read.

The backend SMS service must be configured and the full OTP/admin-approval path must be tested on isolated staging before distributing the app.


## Student exam API (backend workflow)

The authenticated API exposes the following student-only endpoints:

- `GET /my/exams` — paginated published exams for active batch enrollments.
- `GET /exams/{exam}` — exam details and the student's own attempt/result.
- `POST /exams/{exam}/start` — starts or resumes the student's single attempt and returns its frozen randomized question set.
- `PUT /exam-attempts/{attempt}/answers` — saves selected option indexes for questions assigned to that attempt.
- `POST /exam-attempts/{attempt}/submit` — submits answers and returns the server-calculated score.

Correct answer indexes are never included in the question payload. The API checks student role, active enrollment, attempt ownership, assigned question IDs and the server-side deadline; scoring is performed on the server. The Student app includes an exam list and timed attempt screen with start/resume, saved answer selection, explicit answer saving, server-timed countdown, submission and result display. The server remains authoritative for deadlines and scoring.


## Assignment attachments

- Uses `file_picker ^13.3.3 and flutter_secure_storage ^10.3.4` with in-memory bytes so the same picker works on Android, iOS and web.
- The app accepts PDF, DOC, DOCX, JPG and PNG files up to 10 MB; the Laravel API independently validates MIME type and size.
- Uploads use authenticated multipart requests and private server storage.


## Course checkout and payments

- Course details opens a checkout screen backed by `GET /checkout/courses/{course_slug}`; available batch seats and prices come from the server.
- Free courses can be enrolled without payment details. Paid manual checkout accepts the enabled method, transaction reference, optional sender account and optional JPG/PNG/PDF proof (maximum 5 MB).
- Online bKash checkout is initiated by the server. The app only opens HTTPS URLs on the allow-listed bKash gateway hosts; payment completion is determined by the server-side callback, not by the app or a browser return URL.
- A pending payment is shown and blocks duplicate submissions until Admin review. The Payment History screen lists the student's own course payments and fetches owner-scoped details.
- Cart checkout supports enabled manual payment methods and optional private proof upload; bKash cart checkout is not included yet. Printable invoices/receipts and real bKash sandbox acceptance remain separate release tasks.


## Persistent cart

- Add a published course to the account-owned cart from Course Details.
- Open My Cart from the Student home toolbar to view saved courses and current server-calculated BDT totals.
- Remove an individual course or clear the cart. Cart contents persist across approved devices because they are stored against the authenticated student account.
- A cart does not reserve a batch seat or lock a price. Batch capacity, Flash Sale eligibility and payable amount are checked again at checkout. Coupon codes are validated server-side, cannot stack with Flash Sales, and enforce date, minimum spend, course eligibility and usage limits. Multi-course cart checkout creates one manual payment; coupon use is reserved until Admin approval and released if payment is rejected. Free carts enroll immediately. bKash cart checkout and printable invoices/receipts are not included yet.


## Student invoices and receipts

- Open **My invoices & receipts** from the Student app toolbar.
- View invoice totals, discounts, paid amounts, approved refunds, outstanding balance and payment references.
- Print or save an invoice and each individual payment receipt as PDF using a short-lived signed print link. The print page opens in the system browser and provides the native Print / Save as PDF action.
- Invoice APIs return only records owned by the signed-in student. Print links expire after 10 minutes and return no-store responses.
