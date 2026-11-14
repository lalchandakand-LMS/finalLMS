<?php

namespace App\Http\Controllers;

use App\Models\QuestionSetAttempt;
use Illuminate\View\View;

class StudentQuestionSetAttemptHistoryController extends Controller
{
    public function index(): View
    {
        $student = auth()->user();
        abort_unless($student?->isStudent(), 403);

        $attempts = QuestionSetAttempt::query()
            ->where('student_id', $student->id)
            ->with(['course', 'questionSet'])
            ->latest('started_at')
            ->paginate(15);

        return view('student.question-sets.history', compact('attempts'));
    }
}
