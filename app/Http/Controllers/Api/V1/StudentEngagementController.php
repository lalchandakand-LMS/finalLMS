<?php

namespace App\\Http\\Controllers\\Api\\V1;

use App\\Http\\Controllers\\Controller;
use App\\Models\\Comment;
use App\\Models\\Course;
use App\\Models\\DashboardAnnouncement;
use App\\Models\\DashboardAnnouncementRead;
use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\Request;

class StudentEngagementController extends Controller
{
    public function notices(Request $request): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);

        $visible = DashboardAnnouncement::currentlyActive()
            ->whereIn('audience', ['all', 'student'])
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $student->branch_id));

        $unreadCount = (clone $visible)
            ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', $student->id))
            ->count();

        $page = (clone $visible)
            ->with(['course:id,title,slug'])
            ->withExists(['reads as is_read' => fn ($query) => $query->where('user_id', $student->id)])
            ->orderByDesc('priority')
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (DashboardAnnouncement $notice): array => [
                'id' => $notice->id,
                'title' => $notice->title,
                'body' => $notice->body,
                'content_type' => $notice->content_type,
                'course' => $notice->course ? [
                    'title' => $notice->course->title,
                    'slug' => $notice->course->slug,
                ] : null,
                'button_label' => $notice->button_label,
                'button_url' => $notice->button_url,
                'priority' => $notice->priority,
                'is_read' => (bool) $notice->is_read,
                'published_at' => $notice->created_at?->toIso8601String(),
                'ends_at' => $notice->ends_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'unread_count' => $unreadCount,
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function markNoticeRead(Request $request, DashboardAnnouncement $announcement): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);

        $visible = DashboardAnnouncement::currentlyActive()
            ->whereKey($announcement->id)
            ->whereIn('audience', ['all', 'student'])
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $student->branch_id))
            ->exists();

        abort_unless($visible, 404);

        DashboardAnnouncementRead::query()->updateOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $student->id],
            ['read_at' => now()]
        );

        return response()->json(['message' => 'Notice marked as read.']);
    }

    public function courseReviews(Course $course): JsonResponse
    {
        abort_unless($course->status === 'published', 404);

        $reviews = Comment::query()
            ->where('commentable_type', $course->getMorphClass())
            ->where('commentable_id', $course->id)
            ->where('status', 'approved')
            ->with('author:id,name')
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $reviews->getCollection()->map(fn (Comment $review): array => [
                'id' => $review->id,
                'body' => $review->body,
                'author' => $review->author?->name ?? 'Student',
                'created_at' => $review->created_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    public function submitCourseReview(Request $request, Course $course): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);
        abort_unless($course->status === 'published', 404);
        abort_unless(
            $course->enrollments()->where('user_id', $student->id)->whereIn('status', ['active', 'completed'])->exists(),
            403,
            'Only enrolled students can review this course.'
        );

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $pending = Comment::query()
            ->where('commentable_type', $course->getMorphClass())
            ->where('commentable_id', $course->id)
            ->where('author_id', $student->id)
            ->where('status', 'pending')
            ->exists();

        abort_if($pending, 409, 'You already have a review awaiting moderation.');

        $review = $course->comments()->create([
            'author_id' => $student->id,
            'body' => trim($validated['body']),
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Your review was submitted and is awaiting moderation.',
            'data' => ['id' => $review->id, 'status' => $review->status],
        ], 201);
    }
}
