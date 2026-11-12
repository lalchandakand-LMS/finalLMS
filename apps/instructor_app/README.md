# Alom's English Center — Instructor App

Flutter source for the Instructor application.

## Configure and run

\`\`\`bash
flutter create --org com.alomsenglishcenter --project-name aloms_instructor_app --platforms android,ios,web .
flutter pub get
flutter run --dart-define=API_BASE_URL=https://alomsenglishcenter.com/api/v1
\`\`\`

The app stores its bearer token using \`flutter_secure_storage\`. Override the API URL for staging/local builds using \`--dart-define=API_BASE_URL=...\`.

Current screens: instructor sign-in, secure token persistence and server-side logout, assigned course list and authorized enrolled-student list. Lesson authoring, batch/live-class management, assignments, assessments, messaging, notices, analytics and release configuration remain pending.
