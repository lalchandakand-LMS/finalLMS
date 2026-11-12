# Alom's English Center — Student App

Flutter source for the Student application.

## Configure and run

\`\`\`bash
flutter create --org com.alomsenglishcenter --project-name aloms_student_app --platforms android,ios,web .
flutter pub get
flutter run --dart-define=API_BASE_URL=https://alomsenglishcenter.com/api/v1
\`\`\`

The app stores its bearer token using \`flutter_secure_storage\`. Override the API URL for staging/local builds using \`--dart-define=API_BASE_URL=...\`.

Current screens: student sign-in, secure token persistence and server-side logout, course catalog and enrolled-course progress overview. Course purchase, lesson playback, quizzes, assignments, wishlist/cart, reviews, notices and support screens are still pending.
