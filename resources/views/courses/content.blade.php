@extends('layouts.app')

@section('title', ($post->seo_title ?: $post->title) . ' | ' . $course->title)
@section('meta_description', $post->seo_description ?: $post->short_description ?: \Illuminate\Support\Str::limit(strip_tags($post->long_description), 155))

@section('content')
    <style>
        .lesson-heading{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:18px}
        .lesson-heading h1{margin:7px 0;font-size:clamp(1.5rem,3vw,2.2rem)}
        .lesson-layout{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:18px;align-items:start}
        .lesson-player{position:relative;aspect-ratio:16/9;overflow:hidden;border-radius:15px;background:#101d32}
        .lesson-player iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
        .lesson-article{padding:clamp(18px,3vw,30px)}
        .lesson-copy{white-space:pre-line;line-height:1.85;color:#26374a}
        .lesson-categories{display:flex;flex-wrap:wrap;gap:7px;margin:12px 0}
        .lesson-categories span{padding:5px 10px;border-radius:20px;background:#edf4fb;color:#31536c;font-size:.75rem;font-weight:700}
        .lesson-sidebar{position:sticky;top:18px;overflow:hidden}
        .lesson-sidebar__head{padding:17px 18px;background:#102f55;color:#fff}
        .lesson-sidebar__head h2{margin:0;color:#fff;font-size:1.05rem}
        .lesson-sidebar__head p{margin:6px 0 0;color:#d3e4f3;font-size:.8rem}
        .lesson-list{max-height:620px;overflow-y:auto;padding:8px}
        .lesson-list-item{display:flex;align-items:flex-start;gap:10px;padding:11px;border-radius:10px;color:#26374a;text-decoration:none}
        .lesson-list-item:hover{background:#f1f6fb}
        .lesson-list-item.is-current{background:#e8f2ff;color:#174b8a;font-weight:800}
        .lesson-list-item.is-locked{color:#8491a0}
        .lesson-list-number{display:grid;place-items:center;flex:0 0 27px;height:27px;border-radius:8px;background:#eef3f8;font-size:.75rem;font-weight:800}
        .lesson-list-item.is-current .lesson-list-number{color:#fff;background:#2466b2}
        .lesson-list-title{font-size:.82rem;line-height:1.45}
        .lesson-navigation{display:flex;justify-content:space-between;gap:12px;margin-top:18px}
        @media(max-width:900px){.lesson-layout{grid-template-columns:1fr}.lesson-sidebar{position:static}.lesson-list{max-height:360px}}
        @media(max-width:600px){.lesson-heading{align-items:flex-start;flex-direction:column}.lesson-navigation{flex-direction:column}.lesson-navigation .btn{width:100%;text-align:center}}
    </style>

    <div class="lesson-heading">
        <div>
            <span class="badge info">{{ __('Course lesson') }}</span>
            <h1>{{ $post->title }}</h1>
            <p class="muted" style="margin:0;">{{ $course->title }} · {{ $item->duration_minutes ? $item->duration_minutes.' '.__('minutes') : __('Self-paced lesson') }}</p>
        </div>
        <a class="btn secondary" href="{{ route('courses.show', $course) }}">← {{ __('Back to course') }}</a>
    </div>

    @if(session('success'))
        <div class="alert success" role="status">{{ session('success') }}</div>
    @endif

    <div class="lesson-layout">
        <main>
            <article class="panel lesson-article">
                @if($contentProgress && $post->content_file_path && str_starts_with($post->content_file_mime ?? '', 'video/'))
                    <div class="lesson-player" style="margin-bottom:22px;"><video id="lesson-video" controls playsinline preload="metadata" style="width:100%;height:100%;" data-progress-url="{{ route('student.courses.content.progress', [$course, $post]) }}" data-position="{{ $contentProgress->last_position_seconds ?? 0 }}"><source src="{{ route('courses.content.file', [$course, $post]) }}" type="{{ $post->content_file_mime }}"></video></div>
                @elseif($post->video_embed_url)
                    <div class="lesson-player" style="margin-bottom:22px;">
                        <iframe id="lesson-video-embed" src="{{ $post->video_embed_url }}" title="{{ $post->title }}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen @if($contentProgress) data-progress-url="{{ route('student.courses.content.progress', [$course, $post]) }}" @endif></iframe>
                    </div>
                @elseif($post->thumbnail_path)
                    <img src="{{ asset('storage/'.$post->thumbnail_path) }}" alt="{{ $post->title }}" loading="lazy" style="width:100%;max-height:360px;object-fit:cover;border-radius:14px;margin-bottom:20px;">
                @elseif($post->thumbnail)
                    <img src="{{ $post->thumbnail->url }}" alt="{{ $post->thumbnail->alt_text ?: $post->title }}" loading="lazy" style="width:100%;max-height:360px;object-fit:cover;border-radius:14px;margin-bottom:20px;">
                @endif

                @if($post->categories->isNotEmpty())
                    <div class="lesson-categories">
                        @foreach($post->categories as $category)
                            <span>{{ $category->name }}</span>
                        @endforeach
                    </div>
                @endif

                @if($post->short_description)
                    <p class="muted" style="font-size:1rem;line-height:1.7;">{{ $post->short_description }}</p>
                @endif

                <div class="lesson-copy">{{ $post->long_description }}</div>

                @if($post->content_file_path)
                    <div class="panel" style="margin-top:20px;padding:16px;">
                        <strong>{{ __('Lesson resource') }}</strong>
                        <p class="muted">{{ $post->content_file_name }}</p>
                        <a class="btn primary" href="{{ route('courses.content.file', [$course, $post]) }}">{{ $post->access_type === 'paid' ? __('Download purchased PDF') : __('Open / download lesson file') }}</a>
                    </div>
                @endif


                @if($contentProgress)
                    <div class="meta-row" style="margin-top:22px;padding-top:16px;border-top:1px solid var(--border);justify-content:space-between;">
                        @if($contentProgress->completed_at)
                            <span class="badge success">{{ __('Lesson completed') }}</span>
                        @else
                            <form method="POST" action="{{ route('student.courses.content.complete', [$course, $post]) }}">
                                @csrf
                                <button type="submit" class="btn primary">{{ __('Mark lesson complete') }}</button>
                            </form>
                        @endif
                    </div>
                @endif

                @if($post->tags)
                    <div class="muted" style="margin-top:22px;padding-top:15px;border-top:1px solid var(--border);">
                        {{ __('Tags') }}: {{ implode(', ', $post->tags) }}
                    </div>
                @endif
            </article>

            <nav class="lesson-navigation" aria-label="{{ __('Lesson navigation') }}">
                @if($canAccessPrevious)
                    <a class="btn secondary" href="{{ route('courses.content.show', [$course, $previousItem->post]) }}">← {{ __('Previous lesson') }}</a>
                @else
                    <span></span>
                @endif
                @if($canAccessNext)
                    <a class="btn primary" href="{{ route('courses.content.show', [$course, $nextItem->post]) }}">{{ __('Next lesson') }} →</a>
                @elseif($nextItem)
                    <a class="btn secondary" href="{{ route('courses.show', $course) }}">{{ __('Continue course') }} · {{ __('Enroll to unlock next lesson') }}</a>
                @endif
            </nav>
        </main>

        <aside class="panel lesson-sidebar">
            <div class="lesson-sidebar__head">
                <h2>{{ __('Course content') }}</h2>
                <p>{{ $curriculumItems->count() }} {{ __('lessons') }} · {{ $course->title }}</p>
            </div>
            <div class="lesson-list">
                @foreach($curriculumItems as $index => $curriculumItem)
                    @php
                        $isCurrent = $curriculumItem->id === $item->id;
                        $isAccessible = $curriculumItem->is_preview || $canViewAllLessons;
                    @endphp
                    @if($isAccessible)
                        <a class="lesson-list-item {{ $isCurrent ? 'is-current' : '' }}" href="{{ route('courses.content.show', [$course, $curriculumItem->post]) }}" aria-current="{{ $isCurrent ? 'page' : 'false' }}">
                            <span class="lesson-list-number">{{ $index + 1 }}</span>
                            <span class="lesson-list-title">{{ $curriculumItem->post->title }}@if($curriculumItem->is_preview)<small class="muted" style="display:block;">{{ __('Free preview') }}</small>@endif</span>
                        </a>
                    @else
                        <div class="lesson-list-item is-locked">
                            <span class="lesson-list-number">🔒</span>
                            <span class="lesson-list-title">{{ $curriculumItem->post->title }}<small style="display:block;">{{ __('Enroll to unlock') }}</small></span>
                        </div>
                    @endif
                @endforeach
            </div>
        </aside>
    </div>
    @if($contentProgress)
    <script>
    (() => {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const save = (url, position, duration) => {
            if (!url || !csrf || !Number.isFinite(position) || !Number.isFinite(duration) || duration <= 0) return;
            fetch(url, {method:'POST', credentials:'same-origin', keepalive:true, headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf}, body:JSON.stringify({position_seconds:Math.floor(position),duration_seconds:Math.floor(duration)})}).catch(() => {});
        };
        const video = document.getElementById('lesson-video');
        if (video instanceof HTMLVideoElement) {
            const resumeAt = Number(video.dataset.position || 0);
            video.addEventListener('loadedmetadata', () => { if (resumeAt > 0 && resumeAt < video.duration - 2) video.currentTime = resumeAt; }, {once:true});
            let lastSent = 0;
            const sync = () => save(video.dataset.progressUrl, video.currentTime, video.duration);
            video.addEventListener('timeupdate', () => { if (video.currentTime - lastSent >= 15) { lastSent = video.currentTime; sync(); } });
            video.addEventListener('pause', sync); video.addEventListener('ended', sync); window.addEventListener('pagehide', sync);
        }
        const iframe = document.getElementById('lesson-video-embed');
        if (iframe?.dataset.progressUrl && /youtube(?:-nocookie)?\.com/.test(new URL(iframe.src).hostname)) {
            const url = new URL(iframe.src); url.searchParams.set('enablejsapi','1'); url.searchParams.set('origin',window.location.origin); iframe.src = url.toString();
            window.onYouTubeIframeAPIReady = () => new YT.Player(iframe, {events:{
                onReady: event => { let lastSent=0; window.setInterval(() => { if(event.target.getPlayerState()!==YT.PlayerState.PLAYING)return; const pos=event.target.getCurrentTime(); if(pos-lastSent>=15){lastSent=pos;save(iframe.dataset.progressUrl,pos,event.target.getDuration());}},5000); },
                onStateChange: event => { if(event.data===YT.PlayerState.PAUSED||event.data===YT.PlayerState.ENDED)save(iframe.dataset.progressUrl,event.target.getCurrentTime(),event.target.getDuration()); }
            }});
            const api=document.createElement('script'); api.src='https://www.youtube.com/iframe_api'; document.head.appendChild(api);
        }
    })();
    </script>
    @endif
@endsection
