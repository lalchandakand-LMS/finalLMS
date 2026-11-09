# Lesson video storage and deployment

Uploaded lesson videos are stored on the dedicated `lesson_videos` filesystem disk. The disk root is `storage/app/private/course-videos`, outside the public web root. Do not create a public symlink to this directory.

Playback is served through the authenticated/preview-aware route `courses.lessons.video`; keep this route behind the application authorization checks.

## cPanel deployment checklist

1. Deploy the application code and run the new database migration with the normal release procedure.
2. Ensure the PHP/web-server account can write to `storage/app/private/course-videos` and `storage/framework`.
3. Ensure `upload_max_filesize`, `post_max_size`, the web-server request-body limit, and available disk quota are all large enough for the intended upload size. The application validation ceiling is 512 MB; hosting limits may be lower.
4. Keep `storage/app/private/course-videos` private. Only `storage/app/public` should be exposed through Laravel's public storage link.
5. Test upload, replacement, deletion, and playback using an enrolled student account and a non-enrolled account before enabling large uploads in production.

For large-scale delivery, use a private object-storage bucket/CDN with signed playback URLs and resumable uploads rather than relying on large requests to shared cPanel hosting.
