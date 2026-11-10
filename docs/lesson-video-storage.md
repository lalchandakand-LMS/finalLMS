# Lesson video storage and deployment

Uploaded lesson videos use the dedicated `lesson_videos` filesystem disk at `storage/app/private/course-videos`, outside the public web root. Never expose this directory through a public symlink or direct web-server alias.

Playback uses the `courses.lessons.video` route. The controller verifies course/lesson ownership, publication state, enrollment or free-preview status, and file existence before serving the file with private no-store headers. Keep this route behind the controller authorization logic.

## cPanel deployment checklist

1. Deploy the reviewed application release and run the migration through the normal backup-gated release process.
2. Ensure the PHP/web-server account can write to `storage/app/private/course-videos` and Laravel's required storage/cache directories.
3. Check `upload_max_filesize`, `post_max_size`, web-server request-body limits and account disk quota. Application validation permits up to 512 MB, but shared hosting limits may be lower.
4. Keep the lesson video directory private. Only `storage/app/public` should be exposed through Laravel's public storage link.
5. Test uploads, replacements, deletion, browser playback, preview access and enrolled/non-enrolled access on a staging copy before enabling large uploads on production.

For high-volume delivery, prefer private object storage/CDN with short-lived signed playback URLs and resumable uploads rather than large requests to shared cPanel hosting.
