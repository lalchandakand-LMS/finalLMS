<?php

namespace Aloms\UniversalAiAssistant;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

final class AgentContentController
{
    public function drafts(Request $request): View
    {
        $role = (string) $request->user()->role;
        abort_unless(in_array($role, ['teacher', 'admin'], true), 403);
        $drafts = DB::table('ai_teacher_drafts')
            ->when($role === 'teacher', fn ($q) => $q->where('author_id', $request->user()->id))
            ->when($role === 'admin', fn ($q) => $q->where('status', 'pending_review'))
            ->latest()->limit(100)->get();
        return view('universal-ai-assistant::drafts', compact('role', 'drafts'));
    }

    public function saveDraft(Request $request, AgentContentWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->role === 'teacher', 403);
        try {
            $workflow->saveTeacherDraft($request->user(), $request->all());
        } catch (RuntimeException $e) {
            return back()->withErrors(['draft' => $e->getMessage()]);
        } catch (Throwable) {
            return back()->withErrors(['draft' => __('The draft could not be saved.')]);
        }
        return back()->with('success', __('Your draft was saved.'));
    }

    public function reviewDraft(Request $request, int $draft, AgentContentWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'decision' => ['required', 'in:approve,request_changes'], 'review_note' => ['nullable', 'string', 'max:500']]);
        try {
            $workflow->reviewTeacherDraft($request->user(), $draft, (int) $data['version'], $data['decision'] === 'approve', $data['review_note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['draft' => $e->getMessage()]);
        }
        return back()->with('success', __('Draft review was recorded.'));
    }

    public function knowledge(Request $request): View
    {
        abort_unless(in_array($request->user()->role, ['admin', 'support_staff'], true), 403);
        $items = DB::table('ai_support_knowledge')->where('status', 'published')->latest()->limit(100)->get();
        return view('universal-ai-assistant::knowledge', ['items' => $items, 'canManage' => $request->user()->role === 'admin']);
    }

    public function saveKnowledge(Request $request, AgentContentWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        try {
            $workflow->saveKnowledge($request->user(), $request->all());
        } catch (RuntimeException $e) {
            return back()->withErrors(['knowledge' => $e->getMessage()]);
        } catch (Throwable) {
            return back()->withErrors(['knowledge' => __('The knowledge entry could not be saved.')]);
        }
        return back()->with('success', __('Support knowledge published.'));
    }

    public function notifications(Request $request): View
    {
        $items = DB::table('ai_agent_notifications')->where('user_id', $request->user()->id)->latest()->limit(100)->get();
        $routePrefix = match ((string) $request->user()->role) { 'admin' => 'admin.ai-assistant.', 'branch_manager' => 'branch.ai-assistant.', 'teacher' => 'teacher.ai-assistant.', 'student' => 'student.ai-assistant.', 'guardian' => 'guardian.ai-assistant.', default => 'support.ai-assistant.' };
        return view('universal-ai-assistant::notifications', compact('items', 'routePrefix'));
    }

    public function markRead(Request $request, int $notification): RedirectResponse
    {
        DB::table('ai_agent_notifications')->where('id', $notification)->where('user_id', $request->user()->id)
            ->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);
        return back();
    }
}
