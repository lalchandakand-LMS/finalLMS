<?php

namespace Aloms\UniversalAiAssistant;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

final class AgentActionWorkflow
{
    public const STUDY_PLAN_ACTION = 'student.study_plan.create';

    public function proposeStudyPlan(User $student, array $payload, ?string $idempotencyKey = null): object
    {
        if ($student->role !== 'student') {
            throw new RuntimeException('Only an authenticated student can propose a personal study plan.');
        }
        $payload = Validator::make($payload, [
            'title' => ['required', 'string', 'max:120'],
            'goals' => ['required', 'string', 'max:1500'],
            'schedule' => ['required', 'array', 'min:1', 'max:14'],
            'schedule.*.day' => ['required', 'string', 'max:24'],
            'schedule.*.focus' => ['required', 'string', 'max:160'],
            'schedule.*.minutes' => ['required', 'integer', 'min:5', 'max:240'],
        ])->validate();

        $normalized = [
            'title' => trim((string) $payload['title']),
            'goals' => trim((string) $payload['goals']),
            'schedule' => array_values(array_map(static fn (array $item): array => [
                'day' => trim((string) $item['day']),
                'focus' => trim((string) $item['focus']),
                'minutes' => (int) $item['minutes'],
            ], $payload['schedule'])),
        ];
        $json = json_encode($this->canonicalize($normalized), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($idempotencyKey !== null && ! preg_match('/^[A-Za-z0-9._:-]{16,64}$/', $idempotencyKey)) {
            throw new RuntimeException('The idempotency key format is invalid.');
        }
        $key = $idempotencyKey ?? (string) Str::uuid();

        return DB::transaction(function () use ($student, $normalized, $json, $key): object {
            $existing = DB::table('ai_agent_action_proposals')->where('idempotency_key', $key)->first();
            if ($existing) {
                if ((int) $existing->requester_id !== (int) $student->id
                    || ! hash_equals((string) $existing->payload_hash, hash('sha256', self::STUDY_PLAN_ACTION.'|'.$student->id.'|'.$json))) {
                    throw new RuntimeException('The idempotency key was already used for a different request.');
                }
                return $existing;
            }

            $proposalId = DB::table('ai_agent_action_proposals')->insertGetId([
                'requester_id' => $student->id,
                'approver_id' => null,
                'agent_role' => 'student',
                'action_key' => self::STUDY_PLAN_ACTION,
                'status' => 'pending',
                'payload' => $json,
                'payload_hash' => hash('sha256', self::STUDY_PLAN_ACTION.'|'.$student->id.'|'.$json),
                'idempotency_key' => $key,
                'version' => 1,
                'expires_at' => now()->addDays(7),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            AssistantAudit::record('agent_action_proposed', (int) $student->id, null, null, 'student', 'internal', 'agent-workflow-v1', [
                'proposal_id' => $proposalId,
                'action_key' => self::STUDY_PLAN_ACTION,
                'payload_hash' => hash('sha256', $json),
            ]);
            foreach (DB::table('users')->where('role', 'admin')->pluck('id') as $adminId) {
                $this->notify((int) $adminId, 'agent_action_pending', 'AI action needs review', 'A student study plan is waiting for approval.', ['proposal_id' => $proposalId]);
            }

            return DB::table('ai_agent_action_proposals')->where('id', $proposalId)->first();
        }, 3);
    }

    public function approve(User $approver, int $proposalId, int $expectedVersion): object
    {
        if (! in_array($approver->role, ['admin', 'teacher'], true)) {
            throw new RuntimeException('Only an administrator or an authorized teacher can approve agent actions.');
        }

        $result = DB::transaction(function () use ($approver, $proposalId, $expectedVersion): object|array {
            $proposal = DB::table('ai_agent_action_proposals')->where('id', $proposalId)->lockForUpdate()->first();
            if (! $proposal || $proposal->status !== 'pending' || (int) $proposal->version !== $expectedVersion) {
                throw new RuntimeException('This proposal is no longer pending or has changed. Refresh the approval inbox.');
            }
            if (now()->greaterThanOrEqualTo($proposal->expires_at)) {
                DB::table('ai_agent_action_proposals')->where('id', $proposalId)->update([
                    'status' => 'expired', 'version' => DB::raw('version + 1'), 'updated_at' => now(),
                ]);
                AssistantAudit::record('agent_action_expired', (int) $approver->id, null, null, (string) $approver->role, 'internal', 'agent-workflow-v1', ['proposal_id' => $proposalId]);
                return ['expired' => true];
            }
            if ((int) $proposal->requester_id === (int) $approver->id) {
                throw new RuntimeException('Separation of duties: the requester cannot approve their own proposal.');
            }
            if ($proposal->action_key !== self::STUDY_PLAN_ACTION || $proposal->agent_role !== 'student') {
                throw new RuntimeException('No reviewed executor is registered for this action.');
            }
            $payload = json_decode((string) $proposal->payload, true, 16, JSON_THROW_ON_ERROR);
            // MySQL's native JSON storage may reorder object keys. Canonicalize both
            // the submitted and reloaded payload so integrity checks are driver-independent.
            $canonical = json_encode($this->canonicalize($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $expectedHash = hash('sha256', self::STUDY_PLAN_ACTION.'|'.$proposal->requester_id.'|'.$canonical);
            if (! hash_equals((string) $proposal->payload_hash, $expectedHash)) {
                throw new RuntimeException('Proposal integrity check failed. This action is blocked.');
            }
            if ($approver->role === 'teacher' && ! $this->teacherOwnsStudentCourse((int) $approver->id, (int) $proposal->requester_id)) {
                throw new RuntimeException('Teachers may approve study plans only for students enrolled in their own courses.');
            }

            $planId = DB::table('ai_learning_plans')->insertGetId([
                'student_id' => $proposal->requester_id,
                'approved_by' => $approver->id,
                'source_proposal_id' => $proposalId,
                'title' => $payload['title'],
                'goals' => $payload['goals'],
                'schedule' => json_encode($payload['schedule'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('ai_agent_action_proposals')->where('id', $proposalId)->update([
                'approver_id' => $approver->id,
                'status' => 'executed',
                'approved_at' => now(),
                'executed_at' => now(),
                'version' => DB::raw('version + 1'),
                'updated_at' => now(),
            ]);
            AssistantAudit::record('agent_action_executed', (int) $approver->id, null, null, (string) $approver->role, 'internal', 'agent-workflow-v1', [
                'proposal_id' => $proposalId,
                'action_key' => self::STUDY_PLAN_ACTION,
                'result_id' => $planId,
                'requester_id' => (int) $proposal->requester_id,
            ]);
            $this->notify((int) $proposal->requester_id, 'agent_action_executed', 'Study plan approved', 'Your study plan has been approved and is now active.', ['proposal_id' => $proposalId, 'plan_id' => $planId]);

            return DB::table('ai_agent_action_proposals')->where('id', $proposalId)->first();
        }, 3);
        if (is_array($result) && ($result['expired'] ?? false)) {
            throw new RuntimeException('This proposal has expired and must be submitted again.');
        }
        return $result;
    }

    public function reject(User $approver, int $proposalId, int $expectedVersion, ?string $note = null): object
    {
        if (! in_array($approver->role, ['admin', 'teacher'], true)) {
            throw new RuntimeException('Only an administrator or an authorized teacher can reject agent actions.');
        }

        return DB::transaction(function () use ($approver, $proposalId, $expectedVersion, $note): object {
            $proposal = DB::table('ai_agent_action_proposals')->where('id', $proposalId)->lockForUpdate()->first();
            if (! $proposal || $proposal->status !== 'pending' || (int) $proposal->version !== $expectedVersion) {
                throw new RuntimeException('This proposal is no longer pending or has changed. Refresh the approval inbox.');
            }
            if ((int) $proposal->requester_id === (int) $approver->id) {
                throw new RuntimeException('The requester cannot review their own proposal.');
            }
            if ($approver->role === 'teacher' && ! $this->teacherOwnsStudentCourse((int) $approver->id, (int) $proposal->requester_id)) {
                throw new RuntimeException('Teachers may review proposals only for students enrolled in their own courses.');
            }

            DB::table('ai_agent_action_proposals')->where('id', $proposalId)->update([
                'approver_id' => $approver->id,
                'status' => 'rejected',
                'review_note' => $note ? trim($note) : null,
                'version' => DB::raw('version + 1'),
                'updated_at' => now(),
            ]);
            AssistantAudit::record('agent_action_rejected', (int) $approver->id, null, null, (string) $approver->role, 'internal', 'agent-workflow-v1', [
                'proposal_id' => $proposalId,
                'action_key' => (string) $proposal->action_key,
                'requester_id' => (int) $proposal->requester_id,
            ]);
            $this->notify((int) $proposal->requester_id, 'agent_action_rejected', 'Study plan not approved', 'Your study plan proposal was not approved. Review the note in your AI workspace.', ['proposal_id' => $proposalId]);

            return DB::table('ai_agent_action_proposals')->where('id', $proposalId)->first();
        }, 3);
    }

    /** Recursively sort object keys while preserving the order of list values. */
    private function canonicalize(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalize($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }

    private function notify(int $userId, string $event, string $title, string $body, array $data): void
    {
        if (! DB::getSchemaBuilder()->hasTable('ai_agent_notifications')) return;
        DB::table('ai_agent_notifications')->insert([
            'user_id' => $userId, 'event' => $event, 'title' => $title, 'body' => $body,
            'data' => json_encode($data, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function teacherOwnsStudentCourse(int $teacherId, int $studentId): bool
    {
        return DB::table('enrollments')
            ->join('courses', 'courses.id', '=', 'enrollments.course_id')
            ->where('enrollments.user_id', $studentId)
            ->whereIn('enrollments.status', ['active', 'completed'])
            ->where('courses.teacher_id', $teacherId)
            ->exists();
    }
}
