<?php

namespace App\Http\Controllers;

use App\Models\ExamAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamAnswerController extends Controller
{
    public function save(ExamAttempt $attempt, Request $request): JsonResponse
    {
        abort_unless((int) $attempt->student_id === (int) Auth::id(), 403);
        abort_unless($attempt->status === 'in_progress', 403);

        $exam = $attempt->exam;
        $deadline = min(
            $exam->ends_at->getTimestamp(),
            $attempt->started_at->copy()->addMinutes($exam->duration_minutes)->getTimestamp()
        );
        abort_if(now()->getTimestamp() >= $deadline, 403, 'The exam submission window has closed.');

        $validated = $request->validate([
            'answers' => ['sometimes', 'array', 'max:100'],
            'answers.*' => ['required', 'integer', 'between:0,3'],
        ]);

        $attemptQuestions = $attempt->attemptQuestions()
            ->whereIn('exam_question_id', array_keys($validated['answers']))
            ->get()
            ->keyBy('exam_question_id');

        abort_unless($attemptQuestions->count() === count($validated['answers']), 422, 'An answer references a question outside this attempt.');

        foreach (($validated['answers'] ?? []) as $questionId => $selectedOption) {
            $attempt->answers()->updateOrCreate(
                ['exam_question_id' => $questionId],
                ['selected_option' => $selectedOption, 'is_correct' => false, 'marks_awarded' => 0]
            );
        }

        return response()->json(['saved' => count($validated['answers'] ?? [])]);
    }
}
