# Student Tracking & Monitoring Plugin

Plugin key: \`aec.student-tracking\`

This optional plugin gives Admin, Branch Manager, Teacher, Student and Guardian portals a unified view of existing LMS student records. It is deliberately read-only: it does not replace attendance marking, course progress recording, enrollment, guardian linking or financial workflows.

## Included views

- Student directory with search and branch/batch/course filters.
- Active enrollment count and attendance recorded today.
- Per-student attendance rate over the latest 30 days.
- Completed legacy lessons and Course Content Studio curriculum posts.
- Last recorded learning activity.
- A review signal when attendance is below 75% after at least five records, or no learning activity has been recorded in 14 days.
- Student detail view with enrollments, recent attendance, lesson progress and Course Content Studio progress.

The "Needs attention" indicator is an administrative review prompt, not an automated disciplinary decision. It must not be used as the sole basis for sanctions, exclusion or fee decisions.

## Access isolation

All student queries are scoped through the LMS \`PluginResourceAccess::scopeQuery()\` policy:

- Admin: all students.
- Branch Manager: students in the manager's active assigned branch.
- Teacher: students enrolled in courses or batches assigned to that teacher.
- Guardian: only students with an approved active guardian relationship.
- Student: only their own record.

The plugin's \`student-tracking.view\` permission must be granted by the Admin in Plugin Manager. Portal routes also require authentication, email verification, the declared role and active plugin status.

## Data sources

The plugin reads existing core records only:

- \`attendances\`
- \`enrollments\`
- \`lesson_progress\`
- \`course_content_progress\`
- \`guardian_student_links\`
- \`users\`, \`courses\`, \`batches\` and \`branches\`

No new database tables are required and no existing records are copied into a second tracking database. Deactivating or uninstalling the plugin does not delete attendance, enrollment or learning progress.

## Privacy boundary

This plugin does **not** collect GPS coordinates, live location, device microphone/camera data, keystrokes or background surveillance signals. It reports only learning and attendance information already recorded by the LMS. No new tracking cookie or external analytics provider is introduced.

## Release requirements

The source package is not activated merely by being present under \`plugins/\`. It must pass review, be packaged, signed by the trusted release authority, uploaded through Plugin Manager, have its permission granted, and then be activated. The repository does not contain a release private key; never commit one.
