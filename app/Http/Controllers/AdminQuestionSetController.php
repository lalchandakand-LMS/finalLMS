<?php

namespace App\Http\Controllers;

use App\Models\QuestionSet;
use App\Models\QuestionBankQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminQuestionSetController extends Controller
{
    public function index(): View
    {
        $sets = QuestionSet::with(['creator','questions'])->latest()->paginate(20);
        $questions = QuestionBankQuestion::with('teacher')->latest()->limit(500)->get();
        return view('admin.question-sets.index', compact('sets','questions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $ids = $data['question_ids'];
        unset($data['question_ids']);
        $data['created_by'] = $request->user()->id;
        $set = QuestionSet::create($data);
        $set->questions()->sync($this->pivot($ids));
        return back()->with('success','Question set created.');
    }

    public function update(Request $request, QuestionSet $questionSet): RedirectResponse
    {
        $data = $this->validated($request);
        $ids = $data['question_ids'];
        unset($data['question_ids']);
        $questionSet->update($data);
        $questionSet->questions()->sync($this->pivot($ids));
        return back()->with('success','Question set updated.');
    }

    public function destroy(QuestionSet $questionSet): RedirectResponse
    {
        $questionSet->questions()->detach();
        $questionSet->delete();
        return back()->with('success','Question set deleted; question bank entries remain intact.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title'=>['required','string','max:180'],
            'description'=>['nullable','string','max:5000'],
            'status'=>['required','in:draft,published'],
            'question_ids'=>['required','array','min:1','max:200'],
            'question_ids.*'=>['required','integer','distinct','exists:question_bank_questions,id'],
        ]);
    }

    private function pivot(array $ids): array
    {
        $pivot = [];
        foreach (array_values($ids) as $index => $id) $pivot[$id] = ['position'=>$index+1];
        return $pivot;
    }
}
