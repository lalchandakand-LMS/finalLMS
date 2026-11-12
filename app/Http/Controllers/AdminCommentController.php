<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminCommentController extends Controller
{
    public function index(): View
    {
        $comments = Comment::with(['author', 'commentable'])
            ->latest()->paginate(30);

        return view('admin.comments.index', compact('comments'));
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
