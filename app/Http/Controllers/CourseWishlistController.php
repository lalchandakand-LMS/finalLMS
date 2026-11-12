<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CourseWishlistController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        abort_unless($user?->isStudent(), 403);

        $courses = Course::query()
            ->join('course_wishlists', function ($join) use ($user): void {
                $join->on('courses.id', '=', 'course_wishlists.course_id')
                    ->where('course_wishlists.user_id', '=', $user->id);
            })
            ->where('courses.status', 'published')
            ->with('teacher')
            ->orderByDesc('course_wishlists.created_at')
            ->select('courses.*')
            ->paginate(12);

        return view('student.wishlist.index', compact('courses'));
    }

    public function store(Course $course): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user?->isStudent(), 403);
        abort_unless($course->status === 'published', 404);

        DB::table('course_wishlists')->insertOrIgnore([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Course added to your wishlist.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user?->isStudent(), 403);

        DB::table('course_wishlists')
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->delete();

        return back()->with('success', 'Course removed from your wishlist.');
    }
}
