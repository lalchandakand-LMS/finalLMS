<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
class PublicInstructorController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $published = fn ($query) => $query->where('status', 'published');
        $teachers = User::query()->where('role', 'teacher')->whereHas('courses', $published)
            ->with(['courses' => fn ($query) => $published($query)->select(['id','teacher_id','title','slug','short_description','image_path','price'])->orderBy('title')])
            ->withCount(['courses as published_courses_count' => $published])
            ->when($validated['q'] ?? null, function ($query, string $search) use ($published): void {
                $query->where(function ($builder) use ($search, $published): void {
                    $builder->where('name', 'like', '%'.$search.'%')
                        ->orWhereHas('courses', function ($courses) use ($search, $published): void {
                            $published($courses);
                            $courses->where('title', 'like', '%'.$search.'%');
                        });
                });
            })->orderBy('name')->paginate(12)->withQueryString();
        return view('instructors.index', compact('teachers'));
    }
}
