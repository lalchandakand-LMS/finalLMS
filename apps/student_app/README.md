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

Current screens: sign-in, OTP verification, Admin approval waiting/status screen, secure token persistence, server-side logout, searchable course catalog with instructor/title search, server-side sorting and paginated loading, course details, preview lesson access and enrolled-course progress overview. Course purchase, full video playback, quizzes, assignments, wishlist/cart, reviews, notices and support screens are still pending.

The backend SMS service must be configured and the full OTP/admin-approval path must be tested on isolated staging before distributing the app.
