@extends('layouts.app')

@section('title', __('AI Assistant') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div>
        <span class="badge info">{{ __('Advanced AI Assistant') }}</span>
        <h1 style="margin:12px 0 0;">{{ __('AI Assistant') }}</h1>
        @if($dataAccessEnabled)
            <p class="muted">{{ __('This assistant may use read-only, role-scoped LMS context authorized for your account. It cannot perform actions.') }}</p>
        @else
            <p class="muted">{{ __('General guidance only. This assistant cannot access private LMS records or perform actions.') }}</p>
        @endif
    </div>
    @if($role === 'admin')<a class="btn secondary" href="{{ route('admin.ai-assistant.settings') }}">{{ __('AI Settings') }}</a>@endif
</div>
<div style="margin:12px 0;">
    @if($role === 'student' && \Illuminate\Support\Facades\Route::has('student.ai-assistant.proposals.index'))
        <a class="btn secondary" href="{{ route('student.ai-assistant.proposals.index') }}">{{ __('My study plans & AI proposals') }}</a>
    @elseif($role === 'teacher' && \Illuminate\Support\Facades\Route::has('teacher.ai-assistant.proposals.index'))
        <a class="btn secondary" href="{{ route('teacher.ai-assistant.proposals.index') }}">{{ __('Review AI proposals') }}</a>
    @elseif($role === 'admin' && \Illuminate\Support\Facades\Route::has('admin.ai-assistant.proposals.index'))
        <a class="btn secondary" href="{{ route('admin.ai-assistant.proposals.index') }}">{{ __('AI approval inbox') }}</a>
    @endif
</div>
<div class="grid grid-2">
    <aside class="panel">
        <h2>{{ __('Conversation history') }}</h2>
        <a class="btn secondary" href="{{ route($indexRoute) }}">{{ __('New conversation') }}</a>
        <div id="ai-conversation-list" style="margin-top:14px;">
            @forelse($conversations as $item)
                <a href="{{ route($indexRoute, ['conversation_id' => $item->id]) }}" style="display:block;padding:9px 6px;border-bottom:1px solid var(--border-color, #ddd);">
                    {{ $item->title }}
                </a>
            @empty
                <p class="muted">{{ __('No saved conversations yet.') }}</p>
            @endforelse
        </div>
    </aside>
    <section class="panel">
        <div id="ai-chat-history" aria-live="polite" style="min-height:220px;max-height:480px;overflow:auto;margin-bottom:16px;">
            @forelse($messages as $message)
                <p style="white-space:pre-wrap;"><strong>{{ $message->message_role === 'assistant' ? 'AI' : __('You') }}:</strong> {{ $message->content }}</p>
            @empty
                <p class="muted">{{ __('Ask a general question in Bangla or English.') }}</p>
            @endforelse
        </div>
        <form id="ai-chat-form">
            @csrf
            <input type="hidden" id="ai-conversation-id" value="{{ $activeConversation->id ?? '' }}">
            <label for="ai-chat-message">{{ __('Your message') }}</label>
            <textarea id="ai-chat-message" rows="4" maxlength="4000" required></textarea>
            <button class="btn primary" id="ai-chat-submit" type="submit">{{ __('Send message') }}</button>
            <span id="ai-chat-status" class="muted" role="status"></span>
        </form>
    </section>
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
    const conversationList = document.getElementById('ai-conversation-list');
    const endpoint = @json(route($messageRoute));
    const indexUrl = @json(route($indexRoute));
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const message = input.value.trim();
        if (!message) return;
        submit.disabled = true;
        status.textContent = @json(__('Connecting…'));
        const addLine = (label, content = '') => {
            const line = document.createElement('p');
            const strong = document.createElement('strong');
            strong.textContent = label + ': ';
            line.style.whiteSpace = 'pre-wrap';
            const text = document.createTextNode(content);
            line.append(strong, text);
            history.appendChild(line);
            return text;
        };
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'text/event-stream', 'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value},
                body: JSON.stringify({message, conversation_id: conversation.value || null})
            });
            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.message || 'Request failed');
            }
            if (!response.body) throw new Error('Streaming is not supported by this browser.');
            addLine(@json(__('You')), message);
            const assistantText = addLine('AI');
            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            let completed = false;
            const handleLine = (line) => {
                if (!line.startsWith('data:')) return;
                const raw = line.slice(5).trim();
                if (!raw) return;
                const event = JSON.parse(raw);
                if (event.type === 'delta') {
                    assistantText.appendData(event.content || '');
                    history.scrollTop = history.scrollHeight;
                } else if (event.type === 'done') {
                    const isNewConversation = !conversation.value;
                    conversation.value = event.conversation_id;
                    if (isNewConversation && conversationList) {
                        const link = document.createElement('a');
                        const target = new URL(indexUrl, window.location.origin);
                        target.searchParams.set('conversation_id', String(event.conversation_id));
                        link.href = target.toString();
                        link.textContent = message.slice(0, 100);
                        link.style.display = 'block';
                        link.style.padding = '9px 6px';
                        conversationList.prepend(link);
                    }
                    const url = new URL(window.location.href);
                    url.searchParams.set('conversation_id', String(event.conversation_id));
                    window.history.replaceState({}, '', url);
                    completed = true;
                } else if (event.type === 'error') {
                    throw new Error(event.message || 'The AI stream failed.');
                }
            };
            while (true) {
                const {value, done} = await reader.read();
                if (done) break;
                buffer += decoder.decode(value, {stream: true});
                let newline;
                while ((newline = buffer.indexOf('\n')) !== -1) {
                    const line = buffer.slice(0, newline).replace(/\r$/, '');
                    buffer = buffer.slice(newline + 1);
                    handleLine(line);
                }
            }
            if (buffer.trim()) handleLine(buffer.trim());
            if (!completed) throw new Error('The AI stream ended before completion.');
            input.value = '';
            status.textContent = '';
        } catch (error) {
            status.textContent = error.message || 'Request failed';
        } finally {
            submit.disabled = false;
            history.scrollTop = history.scrollHeight;
        }
    });
})();
</script>
@endsection