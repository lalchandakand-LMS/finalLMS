# Lesson video storage and deployment

Uploaded lesson videos use the dedicated `lesson_videos` filesystem disk at `storage/app/private/course-videos`, outside the public web root. Never expose this directory through a public symlink or direct web-server alias.

Playback uses the `courses.lessons.video` route. The controller verifies course/lesson ownership, publication state, enrollment or free-preview status, and file existence before serving the file with private no-store headers.

## cPanel deployment checklist

1. Deploy the reviewed application release and run the migration through the normal backup-gated release process.
2. Ensure PHP can write to `storage/app/private/course-videos` and Laravel storage/cache directories.
3. Check `upload_max_filesize`, `post_max_size`, request-body limits and disk quota. App validation allows up to 512 MB, but shared-host limits may be lower.
4. Keep lesson videos private; expose only `storage/app/public` through the public storage link.
5. Test uploads, replacement, deletion, playback, preview and enrolled/non-enrolled access on staging before enabling production uploads.

For high-volume delivery, prefer private object storage/CDN with short-lived signed playback URLs and resumable uploads.
