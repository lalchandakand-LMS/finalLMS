<?php

namespace App\Http\Controllers;

use App\Models\ExamAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExamAnswerController extends Controller
{
    public function save(ExamAttempt $attempt, Request $request): JsonResponse
    {
        return DB::transaction(function () use ($attempt, $request): JsonResponse {
            $lockedAttempt = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            abort_unless((int) $lockedAttempt->student_id === (int) Auth::id(), 403);
            abort_unless($lockedAttempt->status === 'in_progress', 403);

            $exam = $lockedAttempt->exam;
            $deadline = min(
                $exam->ends_at->getTimestamp(),
                $lockedAttempt->started_at->copy()->addMinutes($exam->duration_minutes)->getTimestamp()
            );
            abort_if(now()->getTimestamp() >= $deadline, 403, 'The exam submission window has closed.');

            $validated = $request->validate([
                'answers' => ['sometimes', 'array', 'max:100'],
                'answers.*' => ['required', 'integer', 'between:0,3'],
            ]);
            $answers = $validated['answers'] ?? [];

            $attemptQuestions = $lockedAttempt->attemptQuestions()
                ->whereIn('exam_question_id', array_keys($answers))
                ->get()
                ->keyBy('exam_question_id');

            abort_unless($attemptQuestions->count() === count($answers), 422, 'An answer references a question outside this attempt.');

            foreach ($answers as $questionId => $selectedOption) {
                $lockedAttempt->answers()->updateOrCreate(
                    ['exam_question_id' => $questionId],
                    ['selected_option' => $selectedOption, 'is_correct' => false, 'marks_awarded' => 0]
                );
            }

            return response()->json(['saved' => count($answers)]);
        });
    }
}
