<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseCombo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class CourseComboController extends Controller
{
    public function index(): View
    {
        $combos = CourseCombo::query()
            ->with(['courses.teacher'])
            ->withCount(['courses', 'payments'])
            ->latest()
            ->paginate(20);

        return view('admin.course-combos.index', compact('combos'));
    }

    public function create(): View
    {
        $courseCombo = new CourseCombo();
        $courses = $this->selectableCourses();

        return view('admin.course-combos.form', compact('courseCombo', 'courses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCombo($request);

        DB::transaction(function () use ($validated): void {
            $combo = CourseCombo::create([
                'title' => $validated['title'],
                'slug' => $this->uniqueSlug($validated['title']),
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'status' => $validated['status'],
            ]);
            $combo->courses()->sync($this->positionedCourses($validated['course_ids']));
        });

        return redirect()->route('admin.course-combos.index')->with('success', 'Course combo saved successfully.');
    }

    public function edit(CourseCombo $courseCombo): View
    {
        $courseCombo->load('courses');
        $courses = $this->selectableCourses();

        return view('admin.course-combos.form', compact('courseCombo', 'courses'));
    }

    public function update(Request $request, CourseCombo $courseCombo): RedirectResponse
    {
        $validated = $this->validateCombo($request);

        DB::transaction(function () use ($courseCombo, $validated): void {
            $courseCombo->update([
                'title' => $validated['title'],
                'slug' => $this->uniqueSlug($validated['title'], $courseCombo->id),
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'status' => $validated['status'],
            ]);
            $courseCombo->courses()->sync($this->positionedCourses($validated['course_ids']));
        });

        return redirect()->route('admin.course-combos.index')->with('success', 'Course combo updated successfully.');
    }

    public function destroy(CourseCombo $courseCombo): RedirectResponse
    {
        if ($courseCombo->payments()->exists()) {
            $courseCombo->update(['status' => 'archived']);

            return back()->with('info', 'This combo has payment history, so it was archived instead of deleted.');
        }

        $courseCombo->delete();

        return back()->with('success', 'Course combo deleted successfully.');
    }

    public function publicIndex(): View
    {
        $combos = CourseCombo::published()
            ->with(['courses' => fn ($courses) => $courses->withCount([
                'batches as open_batches_count' => fn ($batches) => $batches->where('status', 'open'),
            ])])
            ->latest()
            ->paginate(12);

        return view('course-combos.index', compact('combos'));
    }

    public function show(CourseCombo $courseCombo): View
    {
        abort_unless($courseCombo->status === 'published', 404);
        $courseCombo->load(['courses.teacher', 'courses' => fn ($courses) => $courses->withCount([
            'batches as open_batches_count' => fn ($batches) => $batches->where('status', 'open'),
        ])]);
        abort_unless($courseCombo->courses->count() >= 2
            && $courseCombo->courses->every(fn (Course $course) => $course->status === 'published'), 404);

        $purchaseAvailable = $courseCombo->courses->every(fn (Course $course) => (int) $course->open_batches_count === 0);

        return view('course-combos.show', compact('courseCombo', 'purchaseAvailable'));
    }

    private function validateCombo(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'status' => ['required', 'in:draft,published,archived'],
            'course_ids' => ['required', 'array', 'min:2', 'max:20'],
            'course_ids.*' => ['required', 'integer', 'distinct', 'exists:courses,id'],
        ]);

        $courseIds = array_map('intval', $validated['course_ids']);
        $eligibleCount = Course::query()
            ->whereIn('id', $courseIds)
            ->where('status', 'published')
            ->whereDoesntHave('batches', fn ($batches) => $batches->where('status', 'open'))
            ->count();

        if ($eligibleCount !== count($courseIds)) {
            throw ValidationException::withMessages([
                'course_ids' => 'Choose only published courses that do not have open batches. Combo checkout does not reserve individual batch seats.',
            ]);
        }

        $validated['course_ids'] = $courseIds;

        return $validated;
    }

    private function selectableCourses()
    {
        return Course::query()
            ->where('status', 'published')
            ->withCount(['batches as open_batches_count' => fn ($batches) => $batches->where('status', 'open')])
            ->orderBy('title')
            ->get();
    }

    private function positionedCourses(array $courseIds): array
    {
        $sync = [];
        foreach (array_values($courseIds) as $position => $courseId) {
            $sync[$courseId] = ['position' => $position + 1];
        }

        return $sync;
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'course-combo';
        $slug = $base;
        $suffix = 2;

        while (CourseCombo::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
