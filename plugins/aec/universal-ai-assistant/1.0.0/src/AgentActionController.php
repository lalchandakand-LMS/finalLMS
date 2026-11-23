<?php

namespace Aloms\UniversalAiAssistant;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

final class AgentActionController
{
    public function index(Request $request): View
    {
        $role = (string) $request->user()->role;
        abort_unless(in_array($role, ['admin', 'teacher', 'student'], true), 403);
        $this->assertAgentEnabled($role);

        $proposals = DB::table('ai_agent_action_proposals as proposals')
            ->join('users as requester', 'requester.id', '=', 'proposals.requester_id')
            ->where('proposals.action_key', AgentActionWorkflow::STUDY_PLAN_ACTION)
            ->when($role === 'student', fn ($query) => $query->where('proposals.requester_id', $request->user()->id))
            ->when($role === 'teacher', function ($query) use ($request): void {
                $query->whereIn('proposals.requester_id', DB::table('enrollments')
                    ->join('courses', 'courses.id', '=', 'enrollments.course_id')
                    ->where('courses.teacher_id', $request->user()->id)
                    ->whereIn('enrollments.status', ['active', 'completed'])
                    ->select('enrollments.user_id'));
            })
            ->when(in_array($role, ['admin', 'teacher'], true), fn ($query) => $query->where('proposals.status', 'pending'))
            ->select([
                'proposals.id', 'proposals.requester_id', 'proposals.action_key', 'proposals.status',
                'proposals.payload', 'proposals.version', 'proposals.review_note', 'proposals.expires_at',
                'proposals.created_at', 'requester.name as requester_name',
            ])
            ->latest('proposals.created_at')
            ->limit(100)
            ->get();

        return view('universal-ai-assistant::proposals', [
            'role' => $role,
            'proposals' => $proposals,
            'canReview' => in_array($role, ['admin', 'teacher'], true),
            'learningPlans' => $role === 'student' ? DB::table('ai_learning_plans')->where('student_id', $request->user()->id)->latest('created_at')->limit(50)->get() : collect(),
        ]);
    }

    public function storeStudyPlan(Request $request, AgentActionWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->role === 'student', 403);
        $this->assertAgentEnabled('student');
        $validated = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:120'],
            'goals' => ['required', 'string', 'max:1500'],
            'schedule' => ['required', 'array', 'min:1', 'max:14'],
            'schedule.*.day' => ['required', 'string', 'max:24'],
            'schedule.*.focus' => ['required', 'string', 'max:160'],
            'schedule.*.minutes' => ['required', 'integer', 'min:5', 'max:240'],
            'idempotency_key' => ['nullable', 'string', 'regex:/^[A-Za-z0-9._:-]{16,64}$/'],
        ])->validate();

        try {
            $workflow->proposeStudyPlan($request->user(), $validated, $validated['idempotency_key'] ?? $request->header('Idempotency-Key'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['proposal' => $exception->getMessage()]);
        } catch (Throwable) {
            return back()->withErrors(['proposal' => __('The proposal could not be processed. No action was executed; contact an administrator if the problem continues.')]);
        }

        return redirect()->route('student.ai-assistant.proposals.index')->with('success', __('Your study plan has been sent for teacher or admin approval.'));
    }

    public function approve(Request $request, int $proposal, AgentActionWorkflow $workflow): RedirectResponse
    {
        abort_unless(in_array($request->user()->role, ['admin', 'teacher'], true), 403);
        $this->assertAgentEnabled((string) $request->user()->role);
        $validated = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        try {
            $workflow->approve($request->user(), $proposal, (int) $validated['version']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['proposal' => $exception->getMessage()]);
        } catch (Throwable) {
            return back()->withErrors(['proposal' => __('The proposal could not be processed. No action was executed; contact an administrator if the problem continues.')]);
        }

        return back()->with('success', __('The approved action was executed and recorded in the audit trail.'));
    }

    public function reject(Request $request, int $proposal, AgentActionWorkflow $workflow): RedirectResponse
    {
        abort_unless(in_array($request->user()->role, ['admin', 'teacher'], true), 403);
        $this->assertAgentEnabled((string) $request->user()->role);
        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);
        try {
            $workflow->reject($request->user(), $proposal, (int) $validated['version'], $validated['review_note'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['proposal' => $exception->getMessage()]);
        } catch (Throwable) {
            return back()->withErrors(['proposal' => __('The proposal could not be processed. No action was executed; contact an administrator if the problem continues.')]);
        }

        return back()->with('success', __('The proposal was rejected and audited.'));
    }
    private function assertAgentEnabled(string $role): void
    {
        $settings = AssistantSettings::record();
        abort_unless(
            ($settings['global_enabled'] ?? false)
            && ($settings['role_enabled'][$role] ?? false)
            && filled($settings['api_key_encrypted'] ?? null),
            404
        );
    }

}
