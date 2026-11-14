<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InstructorLessonController extends Controller
{
    public function index(Request $request, Course $course): JsonResponse
    {
        $teacher = $this->teacher($request);
        abort_unless((int) $course->teacher_id === (int) $teacher->id, 404);

        $lessons = $course->lessons()->orderBy('order')->orderBy('id')->get();

        return response()->json([
            'course' => ['id' => $course->id, 'title' => $course->title, 'slug' => $course->slug],
            'data' => $lessons->map(fn (Lesson $lesson) => $this->payload($lesson)),
            'meta' => ['total' => $lessons->count()],
        ]);
    }

    public function store(Request $request, Course $course): JsonResponse
    {
        $teacher = $this->teacher($request);
        abort_unless((int) $course->teacher_id === (int) $teacher->id, 404);

        $validated = $this->validated($request);
        $lesson = $course->lessons()->create($validated);

        return response()->json([
            'message' => 'Lesson created successfully.',
            'lesson' => $this->payload($lesson),
        ], 201);
    }

    public function update(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $teacher = $this->teacher($request);
        abort_unless((int) $course->teacher_id === (int) $teacher->id, 404);
        abort_unless((int) $lesson->course_id === (int) $course->id, 404);

        $validated = $this->validated($request);
        $lesson->update($validated);

        return response()->json([
            'message' => 'Lesson updated successfully.',
            'lesson' => $this->payload($lesson->refresh()),
        ]);
    }

    public function destroy(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $teacher = $this->teacher($request);
        abort_unless((int) $course->teacher_id === (int) $teacher->id, 404);
        abort_unless((int) $lesson->course_id === (int) $course->id, 404);

        $videoPath = $lesson->video_path;
        $thumbnailPath = $lesson->thumbnail_path;
        $attachmentPath = $lesson->attachment_path;

        $lesson->delete();

        if ($videoPath) {
            Storage::disk('lesson_videos')->delete($videoPath);
        }
        if ($thumbnailPath) {
            Storage::disk('public')->delete($thumbnailPath);
        }
        if ($attachmentPath) {
            Storage::disk('local')->delete($attachmentPath);
        }

        return response()->json(['message' => 'Lesson deleted successfully.']);
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:50000'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'order' => ['required', 'integer', 'min:1', 'max:10000'],
            'video_url' => ['nullable', 'url', 'max:2048', 'regex:/^https:\/\/(?:www\.)?(?:youtube\.com|youtu\.be|youtube-nocookie\.com|vimeo\.com)(?:\/|$)/i'],
            'is_preview' => ['sometimes', 'boolean'],
        ]);

        $validated['is_preview'] = (bool) ($validated['is_preview'] ?? false);

        return $validated;
    }

    private function payload(Lesson $lesson): array
    {
        return [
            'id' => $lesson->id,
            'title' => $lesson->title,
            'content' => $lesson->content,
            'duration_minutes' => $lesson->duration_minutes,
            'order' => $lesson->order,
            'is_preview' => (bool) $lesson->is_preview,
            'video_url' => $lesson->video_url,
            'has_uploaded_video' => (bool) $lesson->video_path,
            'has_attachment' => (bool) $lesson->attachment_path,
        ];
    }

    private function teacher(Request $request): \App\Models\User
    {
        $teacher = $request->user();
        abort_unless($teacher && $teacher->isTeacher(), 403);

        return $teacher;
    }
}
