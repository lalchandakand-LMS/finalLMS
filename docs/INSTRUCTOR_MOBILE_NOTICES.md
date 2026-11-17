# Instructor mobile academy notices

The instructor mobile app's Academy notices screen uses the existing authenticated instructor notice endpoints:

- \`GET /instructor/notices\` to load published notices and read state.
- \`POST /instructor/notices/{announcement}/read\` to mark a notice as read.

The server remains authoritative for instructor role checks, audience targeting (\`all\` and \`teacher\`) and branch scope (global or the instructor's branch). The mobile client must not infer access from local menu visibility.

The client should only update the unread count after the server confirms a successful read action. Prevent duplicate in-flight read requests and show a recoverable error when the request fails.

Release checklist:
- Run Flutter format, analyze and tests.
- Build the Android APK on the exact release commit.
- Test sign-in, notice loading, empty state, mark-as-read, offline/error recovery and branch-scoped accounts on physical Android devices.
- Store signing and publication are separate release steps.
