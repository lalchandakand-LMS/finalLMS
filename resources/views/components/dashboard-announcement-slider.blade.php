@if(isset($announcementSlides) && $announcementSlides->isNotEmpty())
<section class="panel" aria-label="{{ __('Latest academy announcements') }}" style="margin-bottom:22px;padding:0;overflow:hidden;">
    <div id="aec-announcement-slider" style="position:relative;min-height:180px;background:linear-gradient(115deg,#102b63,#1769e8);color:#fff;">
        @foreach($announcementSlides as $index => $slide)
            <article class="aec-announcement-slide" data-slide="{{ $index }}" @if($index !== 0) hidden @endif style="display:grid;grid-template-columns:minmax(0,1.3fr) minmax(180px,.7fr);gap:18px;align-items:center;padding:24px 58px 24px 26px;min-height:180px;">
                <div>
                    <span style="display:inline-block;background:rgba(255,255,255,.16);padding:5px 10px;border-radius:30px;font-size:.78rem;font-weight:700;">{{ $slide->content_type === 'course' ? __('New course') : ($slide->content_type === 'post' ? __('New post') : __('Announcement')) }}</span>
                    <h2 style="margin:10px 0 7px;color:#fff;">{{ $slide->title }}</h2>
                    @if($slide->body)<p style="margin:0 0 14px;color:#e6efff;white-space:pre-line;">{{ $slide->body }}</p>@endif
                    @if($slide->button_url)<a class="btn" style="display:inline-block;background:#fff;color:#12336d;text-decoration:none;" href="{{ $slide->button_url }}">{{ $slide->button_label ?: __('View details') }}</a>@endif
                </div>
                @if($slide->image)<img src="{{ $slide->image->url }}" alt="{{ $slide->title }}" loading="lazy" style="width:100%;max-height:150px;object-fit:cover;border-radius:14px;">@endif
            </article>
        @endforeach
        @if($announcementSlides->count() > 1)
            <button type="button" data-slide-prev aria-label="{{ __('Previous announcement') }}" style="position:absolute;left:8px;top:45%;border:0;border-radius:50%;width:36px;height:36px;background:rgba(255,255,255,.2);color:#fff;font-size:22px;">‹</button>
            <button type="button" data-slide-next aria-label="{{ __('Next announcement') }}" style="position:absolute;right:8px;top:45%;border:0;border-radius:50%;width:36px;height:36px;background:rgba(255,255,255,.2);color:#fff;font-size:22px;">›</button>
            <div style="position:absolute;bottom:8px;left:0;right:0;display:flex;justify-content:center;gap:6px;">@foreach($announcementSlides as $index => $slide)<button type="button" data-slide-dot="{{ $index }}" aria-label="{{ __('Go to announcement') }} {{ $index + 1 }}" style="width:8px;height:8px;padding:0;border:0;border-radius:50%;background:{{ $index === 0 ? '#fff' : 'rgba(255,255,255,.4)' }};"></button>@endforeach</div>
        @endif
    </div>
</section>
@if($announcementSlides->count() > 1)
<script>
(function(){
 const root=document.getElementById('aec-announcement-slider');if(!root)return;
 const slides=[...root.querySelectorAll('.aec-announcement-slide')],dots=[...root.querySelectorAll('[data-slide-dot]')];
 let current=0,timer;
 function show(index){current=(index+slides.length)%slides.length;slides.forEach((s,i)=>{s.hidden=i!==current;s.style.display=i===current?'grid':'none';});dots.forEach((d,i)=>{d.style.background=i===current?'#fff':'rgba(255,255,255,.4)';});}
 function restart(){clearInterval(timer);timer=setInterval(()=>show(current+1),6000);}
 root.querySelector('[data-slide-prev]')?.addEventListener('click',()=>{show(current-1);restart();});
 root.querySelector('[data-slide-next]')?.addEventListener('click',()=>{show(current+1);restart();});
 dots.forEach(d=>d.addEventListener('click',()=>{show(Number(d.dataset.slideDot));restart();}));
 root.addEventListener('mouseenter',()=>clearInterval(timer));root.addEventListener('mouseleave',restart);
 show(0);restart();
})();
</script>
@endif
<style>@media(max-width:620px){#aec-announcement-slider .aec-announcement-slide{grid-template-columns:1fr!important;padding:20px 48px 26px 18px!important}#aec-announcement-slider .aec-announcement-slide img{max-height:110px!important}}</style>
@endif
