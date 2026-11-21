# Alom's English Center — Instructor App

Flutter source for the Instructor application.

## Configure and run

\`\`\`bash
flutter create --org com.alomsenglishcenter --project-name aloms_instructor_app --platforms android,ios,web .
flutter pub get
flutter run --dart-define=API_BASE_URL=https://alomsenglishcenter.com/api/v1
\`\`\`

The app stores its bearer token using \`flutter_secure_storage\`. Override the API URL for staging/local builds using \`--dart-define=API_BASE_URL=...\`.

Current screens: instructor sign-in, secure token persistence and server-side logout, assigned course list, authorized enrolled-student list, batch roster, assignment creation/grading, live-class scheduling/management, lesson authoring, academy notices with branch/audience-scoped unread/read state, and assessment authoring/results. From a course action menu, instructors can list, create, edit, reorder, mark preview, and delete lessons. Mobile lesson authoring supports text content and HTTPS YouTube/Vimeo links; uploaded video, thumbnail and PDF attachment management remains in the secured web lesson editor. Instructors can create published four-option MCQ assessments for their own batches, set schedules/duration/pass marks, and review submitted results and pass-rate summaries. Assessment authoring currently does not include question-bank selection, editing existing assessments, or non-MCQ question types. Broader course/lesson progress analytics and instructor-to-student messaging are not yet available. Signed release configuration, real-device QA and app-store publication remain release activities.


## Mobile sign-in security

- Instructor sign-in uses a stable 256-bit installation ID stored in Flutter secure storage.
- A new device must complete SMS OTP verification; a second or previously revoked device waits for Admin approval before the API issues a bearer token.
- Existing legacy Instructor tokens without an approved trusted-device record are rejected and revoked on their next authenticated API request.
- Admin password login remains the web recovery path and is tracked in the Admin Device Sessions dashboard; Admin SMS MFA is not enabled by this release.
