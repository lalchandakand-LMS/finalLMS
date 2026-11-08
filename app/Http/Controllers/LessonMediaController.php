<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LessonMediaController extends Controller
{
    public function show(Course $course, Lesson $lesson, string $type): BinaryFileResponse
    {
        abort_unless($lesson->course_id === $course->id, 404);
        abort_unless(in_array($type, ['attachment', 'video'], true), 404);

        $user = Auth::user();
        $canManage = $user && ($user->isAdmin() || ($user->isTeacher() && $course->teacher_id === $user->id));
        abort_unless($course->status === 'published' || $canManage, 404);

        $isEnrolled = $user && $user->isStudent()
            && $course->enrollments()->where('user_id', $user->id)
                ->whereIn('status', ['active', 'completed'])->exists();
        abort_unless($lesson->is_preview || $canManage || $isEnrolled, 403);

        $path = $type === 'video' ? $lesson->video_path : $lesson->attachment_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        $absolutePath = Storage::disk('local')->path($path);
        $mime = $type === 'video'
            ? (mime_content_type($absolutePath) ?: 'video/mp4')
            : ($lesson->attachment_mime ?: 'application/pdf');

        $response = response()->file($absolutePath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . ($type === 'video' ? 'lesson-video' : 'lesson-notes') . '"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'same-origin',
        ]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }
}