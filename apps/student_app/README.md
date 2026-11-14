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

Current screens: sign-in, OTP verification, Admin approval waiting/status screen, secure token persistence, server-side logout, searchable course catalog with instructor/title search, server-side sorting and paginated loading, course details, preview lesson access, enrolled-course progress overview, and a video player for privately hosted lesson media. Video streams use the authenticated API token, server-side enrollment/preview checks, private no-store responses and HTTP byte-range seeking. Course wishlist is available through the mobile API and Student app. Student assignments are available through a paginated list and detail screen, with due-date/late-policy visibility, text-answer or private file attachment submission (PDF, DOC, DOCX, JPG and PNG up to 10 MB), and returned grading status, score and feedback. Attachment uploads use authenticated multipart requests, private storage and owner/enrollment-checked downloads. Course purchase/checkout, cart/coupons, quizzes, reviews, notices and support screens remain pending.

The backend SMS service must be configured and the full OTP/admin-approval path must be tested on isolated staging before distributing the app.
