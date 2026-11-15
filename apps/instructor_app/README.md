# Alom's English Center — Instructor App

Flutter source for the Instructor application.

## Configure and run

\`\`\`bash
flutter create --org com.alomsenglishcenter --project-name aloms_instructor_app --platforms android,ios,web .
flutter pub get
flutter run --dart-define=API_BASE_URL=https://alomsenglishcenter.com/api/v1
\`\`\`

The app stores its bearer token using \`flutter_secure_storage\`. Override the API URL for staging/local builds using \`--dart-define=API_BASE_URL=...\`.

Current screens: instructor sign-in, secure token persistence and server-side logout, assigned course list, authorized enrolled-student list, batch roster, assignment creation/grading, live-class scheduling/management, and lesson authoring. From a course action menu, instructors can list, create, edit, reorder, mark preview, and delete lessons. Mobile lesson authoring supports text content and HTTPS YouTube/Vimeo links; uploaded video, thumbnail and PDF attachment management remains in the secured web lesson editor. Quiz/assessment authoring, messaging, notices, analytics, signed release configuration, real-device QA and app-store publication remain pending.
