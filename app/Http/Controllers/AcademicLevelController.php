<?php

namespace App\Http\Controllers;

use App\Models\AcademicLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AcademicLevelController extends Controller
{
    public function index(): View
    {
        $levels = AcademicLevel::query()->withCount(['courses', 'pages'])->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.academic-levels.index', compact('levels'));
    }

    public function show(AcademicLevel $academicLevel): View
    {
        $academicLevel->load([
            'courses.teacher',
            'courses.batches.branch',
            'courses.batches.teacher',
            'courses.batches.exams',
            'pages',
        ]);

        return view('admin.academic-levels.show', compact('academicLevel'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['name']);
        AcademicLevel::create($data);

        return back()->with('success', 'Academic level created.');
    }

    public function update(Request $request, AcademicLevel $academicLevel): RedirectResponse
    {
        $data = $this->validated($request, $academicLevel);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['name'], $academicLevel->id);
        $academicLevel->update($data);

        return back()->with('success', 'Academic level updated.');
    }

    public function destroy(AcademicLevel $academicLevel): RedirectResponse
    {
        if ($academicLevel->courses()->exists() || $academicLevel->pages()->exists()) {
            return back()->with('error', 'This level is in use. Reassign its courses and pages before deleting it.');
        }

        $academicLevel->delete();

        return back()->with('success', 'Academic level deleted.');
    }

    private function validated(Request $request, ?AcademicLevel $level = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('academic_levels', 'slug')->ignore($level?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active'), 'sort_order' => (int) $request->input('sort_order', 0)];
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'level';
        $slug = $base;
        $suffix = 2;
        while (AcademicLevel::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
