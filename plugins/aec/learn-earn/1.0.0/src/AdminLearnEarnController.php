<?php

namespace Aloms\LearnEarn;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminLearnEarnController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'exists:learn_earn_categories,id'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'archived'])],
        ]);

        $questions = LearnEarnQuestion::query()
            ->with('category')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('question', 'like', '%'.$search.'%')
                        ->orWhere('subject', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['category_id'] ?? null, fn ($query, int $id) => $query->where('category_id', $id))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $categories = LearnEarnCategory::query()->withCount('questions')->orderBy('name')->get();
        $summary = [
            'total' => LearnEarnQuestion::query()->count(),
            'published' => LearnEarnQuestion::query()->where('status', 'published')->count(),
            'draft' => LearnEarnQuestion::query()->where('status', 'draft')->count(),
        ];

        return view('learn-earn::admin.index', compact('questions', 'categories', 'summary', 'filters'));
    }

    public function create(): View
    {
        return view('learn-earn::admin.form', [
            'question' => new LearnEarnQuestion(['marks' => 1, 'negative_marks' => 0, 'difficulty' => 'medium', 'status' => 'draft']),
            'categories' => LearnEarnCategory::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedQuestion($request);
        $data['created_by'] = $request->user()->id;
        $question = LearnEarnQuestion::query()->create($data);

        $this->audit($request, 'admin.learn-earn.question_created', 'Created Learn & Earn question #'.$question->id);

        return redirect()->route('admin.learn-earn.questions.index')->with('success', __('Question saved.'));
    }

    public function edit(LearnEarnQuestion $question): View
    {
        return view('learn-earn::admin.form', [
            'question' => $question,
            'categories' => LearnEarnCategory::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, LearnEarnQuestion $question): RedirectResponse
    {
        $data = $this->validatedQuestion($request);
        $question->update($data);

        $this->audit($request, 'admin.learn-earn.question_updated', 'Updated Learn & Earn question #'.$question->id);

        return redirect()->route('admin.learn-earn.questions.index')->with('success', __('Question updated.'));
    }

    public function archive(Request $request, LearnEarnQuestion $question): RedirectResponse
    {
        $question->update(['status' => 'archived']);
        $this->audit($request, 'admin.learn-earn.question_archived', 'Archived Learn & Earn question #'.$question->id);

        return back()->with('success', __('Question archived. Historical exam snapshots will remain unchanged.'));
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:learn_earn_categories,name'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        $base = Str::slug($data['name']) ?: 'category';
        $slug = $base;
        $suffix = 2;
        while (LearnEarnCategory::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        $category = LearnEarnCategory::query()->create([
            ...$data,
            'slug' => $slug,
            'is_active' => true,
        ]);
        $this->audit($request, 'admin.learn-earn.category_created', 'Created Learn & Earn category #'.$category->id);

        return back()->with('success', __('Category created.'));
    }

    public function updateCategory(Request $request, LearnEarnCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('learn_earn_categories', 'name')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $base = Str::slug($data['name']) ?: 'category';
        $slug = $base;
        $suffix = 2;
        while (LearnEarnCategory::query()->where('slug', $slug)->whereKeyNot($category->id)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        $category->update([
            ...$data,
            'slug' => $slug,
            'is_active' => $request->boolean('is_active'),
        ]);
        $this->audit($request, 'admin.learn-earn.category_updated', 'Updated Learn & Earn category #'.$category->id);

        return back()->with('success', __('Category updated.'));
    }

    public function archiveCategory(Request $request, LearnEarnCategory $category): RedirectResponse
    {
        $category->update(['is_active' => false]);
        $this->audit($request, 'admin.learn-earn.category_archived', 'Archived Learn & Earn category #'.$category->id);

        return back()->with('success', __('Category archived. Existing questions have been preserved.'));
    }

    private function validatedQuestion(Request $request): array
    {
        return $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:learn_earn_categories,id'],
            'subject' => ['nullable', 'string', 'max:120'],
            'difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])],
            'question' => ['required', 'string', 'max:5000'],
            'options' => ['required', 'array', 'size:4'],
            'options.*' => ['required', 'string', 'max:500', 'distinct'],
            'correct_option' => ['required', 'integer', 'between:0,3'],
            'marks' => ['required', 'integer', 'min:1', 'max:100'],
            'negative_marks' => ['required', 'integer', 'min:0', 'max:100'],
            'explanation' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ]);
    }

    private function audit(Request $request, string $event, string $description): void
    {
        ActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'event' => $event,
            'description' => $description,
            'method' => $request->method(),
            'route' => optional($request->route())->getName(),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);
    }
}
