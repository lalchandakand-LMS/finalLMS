<?php

namespace Aloms\UniversalAiAssistant;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

final class AssistantController
{
    public function index(Request $request, AssistantService $assistant): View
    {
        $assistant->pruneExpiredConversations();
        $user = $request->user();
        $role = $this->roleKey((string) $user->role);
        $settings = \\DB::table('ai_assistant_settings')->where('id', 1)->first();
        $roleSettings = is_string($settings?->role_settings) ? json_decode($settings->role_settings, true) : [];
        abort_unless($settings && (bool) $settings->enabled && is_array($roleSettings) && ($roleSettings[$role] ?? false) === true, 403,
            __('The AI Assistant is not enabled for your account role.'));
        $routePrefix = Str::beforeLast((string) $request->route()->getName(), '.');
        $conversation = null;
        $messages = [];

        $id = $request->integer('conversation');
        if ($id > 0) {
            $conversation = \DB::table('ai_assistant_conversations')
                ->where('id', $id)->where('user_id', $user->id)->where('role', $role)->first();
            if ($conversation) {
                $messages = \DB::table('ai_assistant_messages')->where('conversation_id', $id)
                    ->orderBy('id')->limit(100)->get();
            }
        }

        return view('universal-ai-assistant::assistant', [
            'chatUrl' => route($routePrefix.'.chat'),
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    public function chat(Request $request, AssistantService $assistant): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:8000'],
            'conversation_id' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $result = $assistant->reply(
                $request->user(),
                trim($validated['message']),
                isset($validated['conversation_id']) ? (int) $validated['conversation_id'] : null,
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['message' => __('The AI service is temporarily unavailable. No private LMS records were shared.')], 503);
        }

        return response()->json($result);
    }

    private function roleKey(string $role): string
    {
        return $role === 'accountant' ? 'support_staff' : $role;
    }
}