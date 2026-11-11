<?php

namespace App\Http\Controllers;

use App\Models\CommunityPost;
use App\Models\CommunityPostComment;
use App\Models\CommunityPostLike;
use App\Models\TeacherFollow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CommunityController extends Controller
{
    public function index(Request $request): View
    {
        $followedTeacherIds = TeacherFollow::query()
            ->where('student_id', $request->user()->id)
            ->pluck('teacher_id')
            ->all();

        $posts = CommunityPost::query()
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->whereHas('user')
            ->with(['user:id,name,role,profile_photo_path', 'comments' => fn ($query) => $query->where('status', 'published')->with('user:id,name,role')->latest()->limit(3)])
            ->withCount(['likes', 'comments' => fn ($query) => $query->where('status', 'published')])
            ->when($followedTeacherIds, fn ($query) => $query->orderByRaw(
                'CASE WHEN user_id IN ('.implode(',', array_fill(0, count($followedTeacherIds), '?')).') THEN 0 ELSE 1 END',
                $followedTeacherIds
            ))
            ->latest()
            ->paginate(20);

        $likedPostIds = CommunityPostLike::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('post_id', $posts->getCollection()->pluck('id'))
            ->pluck('post_id')
            ->all();

        $followedIds = $followedTeacherIds;

        return view('community.index', compact('posts', 'likedPostIds', 'followedIds'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        CommunityPost::create([
            'user_id' => $request->user()->id,
            'body' => trim($validated['body']),
            'visibility' => 'public',
            'status' => 'published',
        ]);

        return redirect()->route('community.index')->with('success', 'Your post has been published.');
    }

    public function toggleLike(Request $request, CommunityPost $post): RedirectResponse
    {
        abort_unless($post->status === 'published' && $post->visibility === 'public', 404);

        $like = CommunityPostLike::query()
            ->where('post_id', $post->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($like) {
            $like->delete();
        } else {
            CommunityPostLike::create([
                'post_id' => $post->id,
                'user_id' => $request->user()->id,
            ]);
        }

        return back();
    }

    public function comment(Request $request, CommunityPost $post): RedirectResponse
    {
        abort_unless($post->status === 'published' && $post->visibility === 'public', 404);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        CommunityPostComment::create([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
            'body' => trim($validated['body']),
            'status' => 'published',
        ]);

        return back()->with('success', 'Comment added.');
    }

    public function followTeacher(Request $request, int $teacherId): RedirectResponse
    {
        abort_unless($request->user()->isStudent(), 403);
        abort_unless($teacherId !== $request->user()->id, 422, 'You cannot follow your own account.');

        $teacher = \App\Models\User::query()->whereKey($teacherId)->where('role', 'teacher')->whereNull('deleted_at')->firstOrFail();

        TeacherFollow::query()->firstOrCreate([
            'teacher_id' => $teacher->id,
            'student_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Teacher followed.');
    }

    public function unfollowTeacher(Request $request, int $teacherId): RedirectResponse
    {
        abort_unless($request->user()->isStudent(), 403);

        TeacherFollow::query()
            ->where('teacher_id', $teacherId)
            ->where('student_id', $request->user()->id)
            ->delete();

        return back()->with('success', 'Teacher unfollowed.');
    }
}
