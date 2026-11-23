<?php

namespace Aloms\UniversalAiAssistant;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

final class AgentContentWorkflow
{
    private const DRAFT_TYPES = ['question_set', 'lesson_plan', 'feedback_template', 'rubric'];

    public function saveTeacherDraft(User $teacher, array $input): object
    {
        if ($teacher->role !== 'teacher') {
            throw new RuntimeException('Only teachers can save teaching drafts.');
        }
        $data = Validator::make($input, [
            'draft_type' => ['required', 'in:question_set,lesson_plan,feedback_template,rubric'],
            'title' => ['required', 'string', 'max:180'],
            'content' => ['required', 'string', 'max:30000'],
            'submit_for_review' => ['nullable', 'boolean'],
        ])->validate();

        $id = DB::table('ai_teacher_drafts')->insertGetId([
            'author_id' => $teacher->id,
            'draft_type' => $data['draft_type'],
            'title' => trim($data['title']),
            'content' => trim($data['content']),
            'status' => ! empty($data['submit_for_review']) ? 'pending_review' : 'draft',
            'submitted_at' => ! empty($data['submit_for_review']) ? now() : null,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if (! empty($data['submit_for_review'])) {
            $this->notifyAdmins('teacher_draft_submitted', 'Teacher draft submitted', 'A teaching draft is waiting for review.', ['draft_id' => $id]);
        }
        return DB::table('ai_teacher_drafts')->where('id', $id)->first();
    }

    public function reviewTeacherDraft(User $admin, int $draftId, int $version, bool $approve, ?string $note = null): object
    {
        if ($admin->role !== 'admin') {
            throw new RuntimeException('Only an administrator can approve or return teaching drafts.');
        }
        return DB::transaction(function () use ($admin, $draftId, $version, $approve, $note): object {
            $draft = DB::table('ai_teacher_drafts')->where('id', $draftId)->lockForUpdate()->first();
            if (! $draft || $draft->status !== 'pending_review' || (int) $draft->version !== $version) {
                throw new RuntimeException('The draft is no longer awaiting review or has changed.');
            }
            if ((int) $draft->author_id === (int) $admin->id) {
                throw new RuntimeException('The author cannot approve their own draft.');
            }
            DB::table('ai_teacher_drafts')->where('id', $draftId)->update([
                'status' => $approve ? 'approved' : 'changes_requested',
                'reviewer_id' => $admin->id,
                'review_note' => $note ? trim($note) : null,
                'reviewed_at' => now(),
                'version' => DB::raw('version + 1'),
                'updated_at' => now(),
            ]);
            $this->notifyUser((int) $draft->author_id, $approve ? 'teacher_draft_approved' : 'teacher_draft_changes_requested',
                $approve ? 'Teaching draft approved' : 'Changes requested',
                $approve ? 'Your teaching draft has been approved.' : 'An administrator requested changes to your teaching draft.',
                ['draft_id' => $draftId]);
            return DB::table('ai_teacher_drafts')->where('id', $draftId)->first();
        }, 3);
    }

    public function saveKnowledge(User $admin, array $input): object
    {
        if ($admin->role !== 'admin') {
            throw new RuntimeException('Only administrators can publish support knowledge.');
        }
        $data = Validator::make($input, [
            'category' => ['required', 'string', 'max:60'],
            'question' => ['required', 'string', 'max:240'],
            'answer' => ['required', 'string', 'max:12000'],
            'source_label' => ['nullable', 'string', 'max:180'],
            'source_url' => ['nullable', 'url', 'max:500'],
        ])->validate();
        $id = DB::table('ai_support_knowledge')->insertGetId([
            'created_by' => $admin->id, 'updated_by' => $admin->id,
            'category' => trim($data['category']), 'question' => trim($data['question']),
            'answer' => trim($data['answer']), 'source_label' => $data['source_label'] ?? null,
            'source_url' => $data['source_url'] ?? null, 'status' => 'published', 'version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return DB::table('ai_support_knowledge')->where('id', $id)->first();
    }

    public function searchKnowledge(string $query, int $limit = 8): array
    {
        $terms = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($terms === []) return [];
        $builder = DB::table('ai_support_knowledge')->where('status', 'published');
        $builder->where(function ($outer) use ($terms): void {
            foreach (array_slice($terms, 0, 8) as $term) {
                $needle = '%'.addcslashes($term, '%_\\').'%';
                $outer->orWhere('question', 'like', $needle)->orWhere('answer', 'like', $needle)->orWhere('category', 'like', $needle);
            }
        });
        return $builder->orderByDesc('updated_at')->limit(max(1, min(20, $limit)))
            ->get(['id', 'category', 'question', 'answer', 'source_label', 'source_url', 'version'])->all();
    }

    private function notifyAdmins(string $event, string $title, string $body, array $data): void
    {
        foreach (DB::table('users')->where('role', 'admin')->pluck('id') as $adminId) {
            $this->notifyUser((int) $adminId, $event, $title, $body, $data);
        }
    }

    private function notifyUser(int $userId, string $event, string $title, string $body, array $data): void
    {
        DB::table('ai_agent_notifications')->insert([
            'user_id' => $userId, 'event' => $event, 'title' => $title, 'body' => $body,
            'data' => json_encode($data, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
