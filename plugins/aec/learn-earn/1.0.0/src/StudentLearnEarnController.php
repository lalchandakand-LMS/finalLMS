<?php

namespace Aloms\LearnEarn;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Throwable;

class StudentLearnEarnController extends Controller
{
    public function publicIndex(): View
    {
        $exams = LearnEarnExam::query()
            ->where('status', 'published')
            ->where('ends_at', '>=', now())
            ->orderBy('starts_at')
            ->paginate(12);

        return view('learn-earn::public.index', compact('exams'));
    }

    public function publicShow(LearnEarnExam $exam): View
    {
        abort_unless($exam->status === 'published' && $exam->ends_at->gte(now()), 404);

        return view('learn-earn::public.show', compact('exam'));
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()?->isStudent(), 403);

        $studentId = $request->user()->id;
        $exams = LearnEarnExam::query()
            ->where('status', 'published')
            ->where('ends_at', '>=', now())
            ->withCount(['attempts as my_attempts_count' => fn ($query) => $query->where('student_id', $studentId)])
            ->orderBy('starts_at')
            ->paginate(12);

        $recentAttempts = LearnEarnExamAttempt::query()
            ->with('exam:id,title,slug,show_results')
            ->where('student_id', $studentId)
            ->latest('id')
            ->limit(5)
            ->get();

        return view('learn-earn::student.index', compact('exams', 'recentAttempts'));
    }

    public function show(Request $request, LearnEarnExam $exam, PaymentGatewayManager $gateways): View
    {
        abort_unless($request->user()?->isStudent(), 403);
        abort_unless($exam->status === 'published' && $exam->ends_at->gte(now()), 404);

        $studentId = (int) $request->user()->id;
        $attempt = LearnEarnExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', $studentId)
            ->latest('attempt_number')
            ->first();
        $hasEntitlement = $this->hasVerifiedEntitlement((int) $exam->id, $studentId);
        $pendingPayment = Payment::query()
            ->where('learn_earn_exam_id', $exam->id)
            ->where('user_id', $studentId)
            ->where('status', 'pending')
            ->latest('id')
            ->first();
        $bkashAvailable = $exam->access_type === 'paid' && $gateways->isAvailable('bkash');

        return view('learn-earn::student.show', compact(
            'exam',
            'attempt',
            'hasEntitlement',
            'pendingPayment',
            'bkashAvailable'
        ));
    }

    public function checkoutBkash(Request $request, LearnEarnExam $exam, PaymentGatewayManager $gateways): RedirectResponse
    {
        abort_unless($request->user()?->isStudent(), 403);
        abort_unless($gateways->isAvailable('bkash'), 503, __('bKash checkout is not enabled or configured.'));

        $student = $request->user();
        $payment = DB::transaction(function () use ($exam, $student): Payment {
            $lockedExam = LearnEarnExam::query()->lockForUpdate()->findOrFail($exam->id);
            abort_unless(
                $lockedExam->status === 'published'
                    && $lockedExam->access_type === 'paid'
                    && (float) $lockedExam->price > 0
                    && strtoupper((string) $lockedExam->currency) === 'BDT'
                    && $lockedExam->ends_at->gte(now()),
                422,
                __('This paid exam is not available for checkout.')
            );

            $owned = LearnEarnExamEntitlement::query()
                ->where('exam_id', $lockedExam->id)
                ->where('student_id', $student->id)
                ->where('status', 'active')
                ->exists();
            abort_if($owned, 409, __('You already have access to this exam.'));

            $pending = Payment::query()
                ->where('learn_earn_exam_id', $lockedExam->id)
                ->where('user_id', $student->id)
                ->where('status', 'pending')
                ->exists();
            abort_if($pending, 409, __('A payment for this exam is already awaiting verification. Do not pay again.'));

            return Payment::query()->create([
                'user_id' => $student->id,
                'course_id' => null,
                'order_id' => null,
                'learn_earn_exam_id' => $lockedExam->id,
                'method' => 'bkash',
                'gateway_provider' => 'bkash',
                'amount' => number_format((float) $lockedExam->price, 2, '.', ''),
                'currency' => 'BDT',
                'transaction_reference' => 'LEARN-EARN-'.Str::upper(Str::random(24)),
                'status' => 'pending',
            ]);
        });

        try {
            $checkout = $gateways->driver('bkash')->createCheckout(
                $payment,
                route('payments.bkash.callback'),
                (string) $payment->user_id
            );
            $payment->update(['gateway_payment_id' => $checkout['payment_id']]);

            return redirect()->away($checkout['checkout_url']);
        } catch (Throwable) {
            $payment->update([
                'status' => 'rejected',
                'admin_note' => 'bKash checkout could not be initialized for the paid Learn & Earn exam. The student may retry.',
            ]);

            return redirect()->route('student.learn-earn.exams.show', $exam)
                ->with('error', __('bKash checkout could not be started. Please try again or contact the academy.'));
        }
    }

    public function start(Request $request, LearnEarnExam $exam): RedirectResponse
    {
        abort_unless($request->user()?->isStudent(), 403);

        $attempt = DB::transaction(function () use ($request, $exam): LearnEarnExamAttempt {
            $lockedExam = LearnEarnExam::query()->lockForUpdate()->findOrFail($exam->id);
            abort_unless(
                $lockedExam->status === 'published'
                    && now()->between($lockedExam->starts_at, $lockedExam->ends_at),
                403,
                __('This exam is not currently available.')
            );

            if ($lockedExam->access_type === 'paid') {
                $hasEntitlement = $this->hasVerifiedEntitlement((int) $lockedExam->id, (int) $request->user()->id);
                abort_unless($hasEntitlement, 403, __('Verified payment is required before this paid exam can be started.'));
            }

            $studentId = (int) $request->user()->id;
            $existing = LearnEarnExamAttempt::query()
                ->where('exam_id', $lockedExam->id)
                ->where('student_id', $studentId)
                ->where('status', 'in_progress')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existing && $existing->ends_at->gte(now())) {
                return $existing;
            }

            if ($existing) {
                $existing->update(['status' => 'expired', 'submitted_at' => now()]);
            }

            $attemptCount = LearnEarnExamAttempt::query()
                ->where('exam_id', $lockedExam->id)
                ->where('student_id', $studentId)
                ->count();
            abort_if($attemptCount >= (int) $lockedExam->max_attempts, 403, __('You have used all attempts for this exam.'));

            $questionQuery = $lockedExam->questions()->reorder();
            $questionQuery = $lockedExam->randomize_questions
                ? $questionQuery->inRandomOrder()
                : $questionQuery->orderBy('position');

            if ($lockedExam->questions_per_attempt) {
                $questionQuery->limit((int) $lockedExam->questions_per_attempt);
            }

            $questions = $questionQuery->get();
            abort_if($questions->isEmpty(), 422, __('This exam has no available questions.'));

            $startedAt = now();
            $deadline = $startedAt->copy()->addMinutes((int) $lockedExam->duration_minutes);
            if ($deadline->gt($lockedExam->ends_at)) {
                $deadline = $lockedExam->ends_at->copy();
            }

            $attempt = LearnEarnExamAttempt::query()->create([
                'exam_id' => $lockedExam->id,
                'student_id' => $studentId,
                'attempt_number' => $attemptCount + 1,
                'status' => 'in_progress',
                'started_at' => $startedAt,
                'ends_at' => $deadline,
                'max_score' => (int) $questions->sum('marks'),
            ]);

            $attempt->attemptQuestions()->createMany($questions->values()->map(
                fn (LearnEarnExamQuestion $question, int $position) => [
                    'exam_question_id' => $question->id,
                    'position' => $position + 1,
                ]
            )->all());

            return $attempt;
        });

        return redirect()->route('student.learn-earn.attempts.show', $attempt);
    }

    public function attempt(Request $request, LearnEarnExamAttempt $attempt): View
    {
        $this->assertOwner($request, $attempt);
        abort_unless($attempt->status === 'in_progress', 404);
        if ($attempt->ends_at->lte(now())) {
            $this->finalize($attempt, [], true);
            return redirect()->route('student.learn-earn.attempts.result', $attempt);
        }

        $attempt->load(['exam', 'attemptQuestions.examQuestion', 'answers']);

        return view('learn-earn::student.attempt', compact('attempt'));
    }

    public function submit(Request $request, LearnEarnExamAttempt $attempt): RedirectResponse
    {
        $this->assertOwner($request, $attempt);
        $validated = $request->validate([
            'answers' => ['nullable', 'array', 'max:100'],
            'answers.*' => ['nullable', 'integer', 'between:0,3'],
        ]);

        $this->finalize($attempt, $validated['answers'] ?? [], false);

        return redirect()->route('student.learn-earn.attempts.result', $attempt);
    }

    public function result(Request $request, LearnEarnExamAttempt $attempt): View
    {
        $this->assertOwner($request, $attempt);
        abort_unless($attempt->status !== 'in_progress', 404);

        $attempt->load('exam');
        $canViewScore = $attempt->exam->show_results || $attempt->exam->ends_at->lt(now());

        return view('learn-earn::student.result', compact('attempt', 'canViewScore'));
    }

    public function history(Request $request): View
    {
        abort_unless($request->user()?->isStudent(), 403);

        $attempts = LearnEarnExamAttempt::query()
            ->with('exam:id,title,slug,show_results,ends_at')
            ->where('student_id', $request->user()->id)
            ->latest('id')
            ->paginate(20);

        return view('learn-earn::student.history', compact('attempts'));
    }

    private function hasVerifiedEntitlement(int $examId, int $studentId): bool
    {
        return LearnEarnExamEntitlement::query()
            ->where('exam_id', $examId)
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->whereHas('payment', fn ($query) => $query
                ->where('learn_earn_exam_id', $examId)
                ->where('status', 'approved')
                ->where('currency', 'BDT')
                ->where('gateway_provider', 'bkash'))
            ->exists();
    }

    private function finalize(LearnEarnExamAttempt $attempt, array $submittedAnswers, bool $expired): void
    {
        DB::transaction(function () use ($attempt, $submittedAnswers, $expired): void {
            $locked = LearnEarnExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            abort_unless($locked->status === 'in_progress', 409, __('This attempt has already been finalized.'));

            // A late submission must never receive marks for answers sent after the server deadline.
            if ($locked->ends_at->lte(now())) {
                $expired = true;
                $submittedAnswers = [];
            }

            if ($submittedAnswers !== []) {
                $allowedIds = $locked->attemptQuestions()->pluck('exam_question_id')->map(fn ($id) => (string) $id)->all();
                $submittedIds = array_map('strval', array_keys($submittedAnswers));
                abort_unless(array_diff($submittedIds, $allowedIds) === [], 422, __('An answer references a question outside this attempt.'));

                foreach ($submittedAnswers as $questionId => $selectedOption) {
                    if ($selectedOption === null || $selectedOption === '') {
                        continue;
                    }
                    $locked->answers()->updateOrCreate(
                        ['exam_question_id' => (int) $questionId],
                        ['selected_option' => (int) $selectedOption]
                    );
                }
            }

            $locked->load(['attemptQuestions.examQuestion', 'answers']);
            $answerMap = $locked->answers->mapWithKeys(
                fn (LearnEarnExamAnswer $answer) => [(int) $answer->exam_question_id => $answer->selected_option]
            )->all();

            $score = app(LearnEarnScorer::class)->score($locked->attemptQuestions, $answerMap);

            foreach ($score['details'] as $questionId => $detail) {
                $locked->answers()->updateOrCreate(
                    ['exam_question_id' => $questionId],
                    $detail
                );
            }

            $locked->update([
                'status' => $expired || $locked->ends_at->lte(now()) ? 'expired' : 'submitted',
                'submitted_at' => now(),
                'score' => $score['score'],
                'max_score' => $score['max_score'],
            ]);
        });
    }

    private function assertOwner(Request $request, LearnEarnExamAttempt $attempt): void
    {
        abort_unless($request->user()?->isStudent(), 403);
        abort_unless((int) $attempt->student_id === (int) $request->user()->id, 404);
    }
}
