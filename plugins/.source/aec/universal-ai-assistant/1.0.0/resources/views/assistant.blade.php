@extends('layouts.app')

@section('title', __('AI Assistant') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Advanced AI add-on') }}</span><h1 style="margin:12px 0 0;">{{ __('AI Assistant') }}</h1><p class="muted">{{ __('General guidance only. This assistant cannot access private LMS records.') }}</p></div>
</div>
<div class="panel" style="max-width:900px;margin:0 auto;">
    <div id="ai-messages" aria-live="polite" style="min-height:220px;max-height:55vh;overflow:auto;padding:12px;">
        @foreach($messages as $message)
        <div style="margin:12px 0;"><strong>{{ $message->role === 'assistant' ? __('Assistant') : __('You') }}</strong><p style="white-space:pre-wrap;">{{ $message->content }}</p></div>
        @endforeach
    </div>
    <form id="ai-chat-form" data-url="{{ $chatUrl }}" data-conversation="{{ $conversation->id ?? '' }}">
        @csrf
        <label for="ai-message">{{ __('Your message') }}</label>
        <textarea id="ai-message" name="message" rows="3" maxlength="8000" required></textarea>
        <button class="btn primary" type="submit">{{ __('Send message') }}</button>
        <p id="ai-status" class="muted" role="status"></p>
    </form>
</div>
<script>
(() => {
    const form = document.getElementById('ai-chat-form');
    const messages = document.getElementById('ai-messages');
    const status = document.getElementById('ai-status');
    const input = document.getElementById('ai-message');
    if (!form || !messages || !status || !input) return;
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const message = input.value.trim();
        if (!message) return;
        status.textContent = 'Sending…';
        const token = form.querySelector('input[name="_token"]')?.value || '';
        try {
            const response = await fetch(form.dataset.url, {
                method: 'POST',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token},
                body: JSON.stringify({message, conversation_id: form.dataset.conversation || null})
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'The assistant is unavailable.');
            form.dataset.conversation = String(data.conversation_id);
            const add = (label, content) => {
                const wrapper = document.createElement('div');
                const heading = document.createElement('strong');
                const paragraph = document.createElement('p');
                heading.textContent = label;
                paragraph.textContent = content;
                paragraph.style.whiteSpace = 'pre-wrap';
                wrapper.append(heading, paragraph);
                messages.append(wrapper);
            };
            add('You', message);
            add('Assistant', data.reply);
            input.value = '';
            status.textContent = 'Reply received.';
            messages.scrollTop = messages.scrollHeight;
            const url = new URL(window.location.href);
            url.searchParams.set('conversation', String(data.conversation_id));
            window.history.replaceState({}, '', url);
        } catch (error) {
            status.textContent = error.message || 'The assistant is unavailable.';
        }
    });
})();
</script>
@endsection