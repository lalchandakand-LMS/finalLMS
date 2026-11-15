<?php

namespace App\\Http\\Controllers\\Api\\V1;

use App\\Http\\Controllers\\Controller;
use App\\Models\\DashboardAnnouncement;
use App\\Models\\DashboardAnnouncementRead;
use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\Request;

class StudentNoticeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);

        $visible = DashboardAnnouncement::currentlyActive()
            ->whereIn('audience', ['all', 'student'])
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $student->branch_id));

        $unreadCount = (clone $visible)
            ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', $student->id))
            ->count();

        $announcements = (clone $visible)
            ->with('branch:id,name')
            ->withExists(['reads as is_read' => fn ($query) => $query->where('user_id', $student->id)])
            ->orderByDesc('priority')
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $announcements->getCollection()->map(fn (DashboardAnnouncement $notice): array => [
                'id' => $notice->id,
                'title' => $notice->title,
                'body' => $notice->body,
                'content_type' => $notice->content_type,
                'button_label' => $notice->button_label,
                'button_url' => $notice->button_url,
                'branch' => $notice->branch?->name,
                'priority' => $notice->priority,
                'is_read' => (bool) $notice->is_read,
                'starts_at' => $notice->starts_at?->toIso8601String(),
                'ends_at' => $notice->ends_at?->toIso8601String(),
                'created_at' => $notice->created_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $announcements->currentPage(),
                'last_page' => $announcements->lastPage(),
                'per_page' => $announcements->perPage(),
                'total' => $announcements->total(),
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    public function markRead(Request $request, DashboardAnnouncement $announcement): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);

        $isVisible = DashboardAnnouncement::currentlyActive()
            ->whereKey($announcement->id)
            ->whereIn('audience', ['all', 'student'])
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $student->branch_id))
            ->exists();

        abort_unless($isVisible, 404);

        $read = DashboardAnnouncementRead::query()->updateOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $student->id],
            ['read_at' => now()]
        );

        return response()->json([
            'message' => 'Notice marked as read.',
            'data' => [
                'id' => $announcement->id,
                'is_read' => true,
                'read_at' => $read->read_at?->toIso8601String(),
            ],
        ]);
    }
}
