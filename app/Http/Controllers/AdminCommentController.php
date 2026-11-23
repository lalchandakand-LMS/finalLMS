<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\BlogPost;
use App\Models\Course;
use Illuminate\View\View;

class AdminCommentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
            'content_type' => ['nullable', Rule::in(['post', 'course'])],
            'search' => ['nullable', 'string', 'max:120'],
        ]);
        $typeMap = ['post' => BlogPost::class, 'course' => Course::class];
        $counts = Comment::query()->select('status', DB::raw('COUNT(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status');
        $query = Comment::with(['author', 'commentable'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['content_type'] ?? null, fn ($query, $type) => $query->where('commentable_type', $typeMap[$type]))
            ->when(trim($filters['search'] ?? '') !== '', function ($query) use ($filters): void {
                $term = trim($filters['search']);
                $query->where(function ($nested) use ($term): void {
                    $nested->where('body', 'like', '%'.$term.'%')->orWhereHas('author', fn ($author) => $author->where('name', 'like', '%'.$term.'%'));
                });
            });
        $comments = $query->latest()->paginate(30)->withQueryString();
        return view('admin.comments.index', compact('comments', 'counts', 'filters'));
    }

    public function update(Request $request, Comment $comment): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', 'in:approved,rejected,pending']]);
        $comment->update([
            'status' => $validated['status'],
            'moderated_by' => $request->user()->id,
            'moderated_at' => now(),
        ]);

        return back()->with('success', __('Comment moderation updated.'));
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        $comment->delete();
        return back()->with('success', __('Comment deleted.'));
    }
}
