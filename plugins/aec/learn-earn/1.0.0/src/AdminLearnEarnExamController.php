<?php

namespace Aloms\LearnEarn;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminLearnEarnExamController extends Controller
{
    public function index(): View
    {
        $exams = LearnEarnExam::query()
            ->withCount('questions')
            ->latest('id')
            ->paginate(20);

        return view('learn-earn::admin.exams.index', compact('exams'));
    }

    public function create(): View
    {
        $questions = LearnEarnQuestion::query()
            ->with('category')
            ->where('status', 'published')
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->orWhere(function ($query): void {
                $query->where('status', 'published')->whereNull('category_id');
            })
            ->orderBy('id')
            ->get();

        return view('learn-earn::admin.exams.form', compact('questions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'pass_marks' => ['required', 'integer', 'min:0', 'max:100000'],
            'questions_per_attempt' => ['nullable', 'integer', 'min:1', 'max:100'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:100'],
            'randomize_questions' => ['nullable', 'boolean'],
            'access_type' => ['required', Rule::in(['free', 'paid'])],
            'price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'currency' => ['required', Rule::in(['BDT'])],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'show_results' => ['nullable', 'boolean'],
            'question_ids' => ['required', 'array', 'min:1', 'max:100'],
            'question_ids.*' => ['required', 'integer', 'distinct', 'exists:learn_earn_questions,id'],
        ]);

        if ($data['access_type'] === 'paid' && (float) ($data['price'] ?? 0) <= 0) {
            return back()->withErrors(['price' => __('Paid exams must have a price greater than zero.')])->withInput();
        }

        $exam = DB::transaction(function () use ($data, $request): LearnEarnExam {
            $ids = array_map('intval', $data['question_ids']);
            $selected = LearnEarnQuestion::query()
                ->with('category')
                ->whereIn('id', $ids)
                ->where('status', 'published')
                ->where(function ($query): void {
                    $query->whereHas('category', fn ($category) => $category->where('is_active', true))
                        ->orWhereNull('category_id');
                })
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            abort_unless($selected->count() === count($ids), 422, __('One or more selected questions are no longer available.'));

            $orderedQuestions = collect($ids)->map(fn (int $id) => $selected->get($id));
            $totalMarks = (int) $orderedQuestions->sum('marks');
            abort_if((int) $data['pass_marks'] > $totalMarks, 422, __('Pass marks cannot exceed the total positive marks.'));

            $questionCount = $data['questions_per_attempt'] ?? $orderedQuestions->count();
            abort_if($questionCount > $orderedQuestions->count(), 422, __('Questions per attempt cannot exceed the selected question count.'));

            $baseSlug = Str::slug($data['title']) ?: 'exam';
            $slug = $baseSlug;
            $suffix = 2;
            while (LearnEarnExam::query()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }

            $exam = LearnEarnExam::query()->create([
                'title' => $data['title'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'duration_minutes' => $data['duration_minutes'],
                'pass_marks' => $data['pass_marks'],
                'questions_per_attempt' => $data['questions_per_attempt'] ?? null,
                'max_attempts' => $data['max_attempts'],
                'randomize_questions' => $request->boolean('randomize_questions'),
                'access_type' => $data['access_type'],
                'price' => $data['access_type'] === 'paid' ? $data['price'] : 0,
                'currency' => $data['currency'],
                'status' => $data['status'],
                'show_results' => $request->boolean('show_results'),
                'created_by' => $request->user()->id,
            ]);

            foreach ($orderedQuestions as $position => $question) {
                $exam->questions()->create([
                    'source_question_id' => $question->id,
                    'question' => $question->question,
                    'options' => $question->options,
                    'correct_option' => $question->correct_option,
                    'marks' => $question->marks,
                    'negative_marks' => $question->negative_marks,
                    'explanation' => $question->explanation,
                    'category_label' => $question->category?->name,
                    'position' => $position + 1,
                ]);
            }

            ActivityLog::query()->create([
                'user_id' => $request->user()->id,
                'event' => 'admin.learn-earn.exam_created',
                'description' => 'Created Learn & Earn exam #'.$exam->id,
                'method' => $request->method(),
                'route' => optional($request->route())->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            ]);

            return $exam;
        });

        return redirect()->route('admin.learn-earn.exams.index')->with('success', __('Exam created with immutable question snapshots.'));
    }

    public function archive(Request $request, LearnEarnExam $exam): RedirectResponse
    {
        $exam->update(['status' => 'archived']);

        ActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'event' => 'admin.learn-earn.exam_archived',
            'description' => 'Archived Learn & Earn exam #'.$exam->id,
            'method' => $request->method(),
            'route' => optional($request->route())->getName(),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);

        return back()->with('success', __('Exam archived. Existing attempts and question snapshots are retained.'));
    }
}
