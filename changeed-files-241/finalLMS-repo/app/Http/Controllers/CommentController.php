<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Comment;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function storeCourse(Request $request, Course $course): RedirectResponse
    {
        abort_unless($course->status === 'published', 404);
        return $this->store($request, $course);
    }

    public function storePost(Request $request, BlogPost $post): RedirectResponse
    {
        abort_unless($post->status === 'published' && $post->published_at && $post->published_at->lte(now()), 404);
        return $this->store($request, $post);
    }

    private function store(Request $request, Course|BlogPost $target): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $target->comments()->create([
            'author_id' => $request->user()->id,
            'body' => trim($validated['body']),
            'status' => 'pending',
        ]);

        return back()->with('success', __('Your comment was submitted and is awaiting admin approval.'));
    }
}
