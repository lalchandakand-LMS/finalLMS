@extends('layouts.app')

@section('title', __('AI Assistant') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Advanced AI Assistant') }}</span><h1 style="margin:12px 0 0;">{{ __('AI Assistant') }}</h1><p class="muted">{{ __('General guidance only. This assistant cannot access private LMS records or perform actions.') }}</p></div>
    @if($role === 'admin')<a class="btn secondary" href="{{ route('admin.ai-assistant.settings') }}">{{ __('AI Settings') }}</a>@endif
</div>
<div class="panel">
    <div id="ai-chat-history" aria-live="polite" style="min-height:220px;max-height:480px;overflow:auto;margin-bottom:16px;"><p class="muted">{{ __('Ask a general question in Bangla or English.') }}</p></div>
    <form id="ai-chat-form">
        @csrf
        <input type="hidden" id="ai-conversation-id" value="">
        <label for="ai-chat-message">{{ __('Your message') }}</label>
        <textarea id="ai-chat-message" rows="4" maxlength="4000" required></textarea>
        <button class="btn primary" id="ai-chat-submit" type="submit">{{ __('Send message') }}</button>
        <span id="ai-chat-status" class="muted" role="status"></span>
    </form>
</div>
<script>
(() => {
    const form = document.getElementById('ai-chat-form');
    if (!form) return;
    const history = document.getElementById('ai-chat-history');
    const input = document.getElementById('ai-chat-message');
    const status = document.getElementById('ai-chat-status');
    const submit = document.getElementById('ai-chat-submit');
    const conversation = document.getElementById('ai-conversation-id');
    const endpoint = @json(route($messageRoute));
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const message = input.value.trim();
        if (!message) return;
        submit.disabled = true;
        status.textContent = @json(__('Sending…'));
        const userLine = document.createElement('p');
        userLine.textContent = 'You: ' + message;
        history.appendChild(userLine);
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value},
                body: JSON.stringify({message, conversation_id: conversation.value || null})
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Request failed');
            conversation.value = data.conversation_id;
            const answer = document.createElement('p');
            answer.textContent = 'AI: ' + data.response;
            history.appendChild(answer);
            input.value = '';
            status.textContent = '';
        } catch (error) {
            status.textContent = error.message;
        } finally {
            submit.disabled = false;
            history.scrollTop = history.scrollHeight;
        }
    });
})();
</script>
@endsection
