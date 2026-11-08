@extends('layouts.app')
@section('title', $lesson->title . ' | ' . $course->title)
@section('content')
<div class="page-header"><div><a href="{{ route('courses.show', $course) }}" class="muted">← {{ __('Back to course') }}</a><h1 style="margin:12px 0 0;">{{ $lesson->title }}</h1><p class="muted">{{ $course->title }} · {{ $lesson->duration_minutes }} {{ __('minutes') }}</p></div>@if($isCompleted)<span class="badge success">{{ __('Completed') }}</span>@endif</div>
<div class="panel protected-learning-content" oncontextmenu="return false" ondragstart="return false">
<div aria-hidden="true" style="position:fixed;inset:35% 5% auto;z-index:5;pointer-events:none;opacity:.10;transform:rotate(-24deg);font-size:clamp(18px,4vw,42px);font-weight:800;text-align:center;word-break:break-all;">{{ auth()->user()?->email }} · Alom’s English Center · {{ now()->format('d/m/Y') }}</div>
@if($privateVideoUrl)
<div class="protected-media-frame" style="position:relative;overflow:hidden;border-radius:12px;background:#0f172a;">
<video controls controlsList="nodownload noplaybackrate" disablePictureInPicture playsinline preload="metadata" style="display:block;width:100%;max-height:70vh;" oncontextmenu="return false"><source src="{{ $privateVideoUrl }}"></video>
<div class="dynamic-media-watermark" aria-hidden="true">{{ auth()->user()?->email ?? 'Preview visitor' }} · Alom’s English Center · {{ now()->format('d/m/Y H:i') }}</div>
</div>
@elseif($videoEmbedUrl)
<div class="protected-media-frame" style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:12px;background:#0f172a;">
<iframe src="{{ $videoEmbedUrl }}" title="{{ $lesson->title }}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen style="position:absolute;inset:0;width:100%;height:100%;border:0;"></iframe>
<div class="dynamic-media-watermark" aria-hidden="true">{{ auth()->user()?->email ?? 'Preview visitor' }} · Alom’s English Center · {{ now()->format('d/m/Y H:i') }}</div>
</div>
@elseif($lesson->video_url)<p class="muted">{{ __('This video provider is not supported for embedded playback.') }}</p>@endif
@if($privateAttachmentUrl)
<div class="protected-media-frame" style="position:relative;margin-top:22px;overflow:hidden;">
<h3>{{ __('Protected lesson notes') }} — {{ $lesson->attachment_name }}</h3>
@if(str_starts_with((string) $lesson->attachment_mime, 'image/'))
<img src="{{ $privateAttachmentUrl }}" alt="{{ __('Protected lesson notes') }}" style="max-width:100%;height:auto;">
@else
<iframe src="{{ $privateAttachmentUrl }}#toolbar=0&navpanes=0&scrollbar=0" title="{{ __('Protected lesson notes') }}" style="width:100%;height:75vh;border:0;"></iframe>
@endif
<div class="dynamic-media-watermark" aria-hidden="true">{{ auth()->user()?->email ?? 'Preview visitor' }} · Alom’s English Center · {{ now()->format('d/m/Y H:i') }}</div>
</div>
@endif
<div style="margin-top:24px;line-height:1.8;">{!! nl2br(e($lesson->content)) !!}</div>
@auth @if(auth()->user()->isStudent() && ! $isCompleted && ! $lesson->is_preview)<form method="POST" action="{{ route('courses.lessons.complete', [$course, $lesson]) }}" style="margin-top:24px;">@csrf<button type="submit" class="btn primary">{{ __('Mark lesson complete') }}</button></form>@endif @endauth
</div>
@push('scripts')
<style>
@media print { body * { visibility:hidden !important; } body:after { content:'Printing is disabled for protected course content.'; visibility:visible; position:fixed; inset:40% 5%; text-align:center; font-size:24px; } }
.protected-learning-content { position:relative; user-select:none; -webkit-user-select:none; }
.protected-learning-content iframe { user-select:auto; }
.protected-media-frame { isolation:isolate; }
.dynamic-media-watermark { position:absolute; z-index:4; left:5%; right:5%; top:42%; transform:rotate(-22deg); color:#fff; text-shadow:0 1px 4px #000; opacity:.24; font-size:clamp(14px,2.4vw,28px); font-weight:800; text-align:center; pointer-events:none; overflow-wrap:anywhere; user-select:none; }
</style>
<script>
document.addEventListener('contextmenu', function(e){ if(e.target.closest('.protected-learning-content')) e.preventDefault(); });
document.addEventListener('keydown', function(e){
    if(!e.target.closest('.protected-learning-content')) return;
    const key=e.key.toLowerCase();
    if((e.ctrlKey||e.metaKey) && ['s','u','p','c'].includes(key)){ e.preventDefault(); }
});
document.addEventListener('copy', function(e){ if(window.getSelection()?.anchorNode?.parentElement?.closest('.protected-learning-content')) e.preventDefault(); });
document.addEventListener('dragstart', function(e){ if(e.target.closest('.protected-learning-content')) e.preventDefault(); });
const protectedWatermarks = document.querySelectorAll('.dynamic-media-watermark');
const watermarkSpots = [{top:'18%',left:'4%'},{top:'68%',left:'12%'},{top:'38%',left:'38%'},{top:'72%',left:'52%'},{top:'22%',left:'58%'}];
let watermarkIndex = 0;
window.setInterval(function(){
    watermarkIndex = (watermarkIndex + 1) % watermarkSpots.length;
    protectedWatermarks.forEach(function(mark){
        const spot = watermarkSpots[watermarkIndex];
        mark.style.top = spot.top;
        mark.style.left = spot.left;
        mark.style.right = '4%';
        mark.textContent = mark.textContent.split(' · Alom')[0] + ' · Alom’s English Center · ' + new Date().toLocaleString();
    });
}, 7000);
</script>
@endpush
@endsection
