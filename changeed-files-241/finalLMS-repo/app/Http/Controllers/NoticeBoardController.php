<?php

namespace App\Http\Controllers;

use App\Models\DashboardAnnouncement;
use App\Models\DashboardAnnouncementRead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NoticeBoardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $visible = DashboardAnnouncement::currentlyActive()
            ->whereIn('audience', ['all', $user->role])
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $user->branch_id));

        $unreadCount = (clone $visible)
            ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', $user->id))
            ->count();

        $announcements = (clone $visible)
            ->with(['image', 'branch'])
            ->withExists(['reads as is_read' => fn ($query) => $query->where('user_id', $user->id)])
            ->orderByDesc('priority')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('notices.index', compact('announcements', 'unreadCount'));
    }

    public function markRead(Request $request, DashboardAnnouncement $announcement): RedirectResponse
    {
        $user = $request->user();
        $isVisible = DashboardAnnouncement::currentlyActive()
            ->whereKey($announcement->id)
            ->whereIn('audience', ['all', $user->role])
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $user->branch_id))
            ->exists();

        abort_unless($isVisible, 404);

        DashboardAnnouncementRead::query()->updateOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $user->id],
            ['read_at' => now()]
        );

        return back()->with('success', __('Notice marked as read.'));
    }
}
