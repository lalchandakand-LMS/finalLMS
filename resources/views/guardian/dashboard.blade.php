@extends('layouts.app')
@section('title', __('Guardian dashboard'))
@section('content')
<div class="container" style="max-width:1120px;margin:32px auto;padding:0 16px">
    <div class="page-header"><div><span class="badge info">{{ __('Guardian portal') }}</span><h1>{{ __('My linked students') }}</h1><p class="muted">{{ __('A student must approve your request before any learning or account information is shared.') }}</p></div></div>
    <div id="guardian-message" role="status" aria-live="polite"></div>
    <section class="panel" style="padding:22px;margin:18px 0">
        <h2>{{ __('Request access to a student') }}</h2>
        <form id="guardian-link-form" method="POST" action="{{ route('guardian.links.store') }}" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px;align-items:end">
            @csrf
            <label>{{ __('Student verified email') }}<input type="email" name="student_email" required maxlength="190" autocomplete="email" style="width:100%"></label>
            <label>{{ __('Relationship') }}<input type="text" name="relationship" maxlength="60" placeholder="{{ __('Parent / Guardian') }}" style="width:100%"></label>
            <button class="btn btn-primary" type="submit">{{ __('Send request') }}</button>
        </form>
    </section>

    <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px">
    @forelse($links as $link)
        <article class="panel" style="padding:20px">
            <div style="display:flex;justify-content:space-between;gap:10px;align-items:start">
                <div><h3 style="margin:0 0 4px">{{ $link->student?->name ?? __('Student account') }}</h3><p class="muted" style="margin:0">{{ $link->student?->email }}</p></div>
                <span class="badge {{ $link->status === 'active' ? 'success' : 'warning' }}">{{ __($link->status) }}</span>
            </div>
            <p>{{ __('Relationship') }}: {{ $link->relationship }}</p>
            @if($link->status === 'active')
                <button class="btn btn-primary guardian-summary" type="button" data-url="{{ route('guardian.students.summary',$link->student_id) }}">{{ __('View student summary') }}</button>
            @else
                <p class="muted">{{ __('Waiting for the student to approve. No student data is visible yet.') }}</p>
            @endif
            <form class="guardian-revoke-form" method="POST" action="{{ route('guardian.links.revoke',$link) }}" style="margin-top:10px">
                @csrf<button class="btn btn-secondary" type="submit">{{ __('Revoke access') }}</button>
            </form>
        </article>
    @empty
        <div class="panel" style="padding:22px"><p>{{ __('No student links yet. Send a request using the form above.') }}</p></div>
    @endforelse
    </div>
    <section id="guardian-summary" class="panel" hidden style="padding:20px;margin-top:20px">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center"><h2>{{ __('Student learning summary') }}</h2><button type="button" id="close-summary" class="btn btn-secondary">{{ __('Close') }}</button></div>
        <pre id="guardian-summary-data" style="white-space:pre-wrap;overflow-wrap:anywhere;max-height:560px;overflow:auto"></pre>
    </section>
</div>
<script>
(() => {
 const message=document.getElementById('guardian-message');
 const show=(text,ok=false)=>{message.className=ok?'flash success':'flash error';message.textContent=text;};
 async function send(form,extra={}) {
  const data=new FormData(form); Object.entries(extra).forEach(([k,v])=>data.set(k,v));
  const response=await fetch(form.action,{method:'POST',body:data,headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
  const payload=await response.json().catch(()=>({message:'Unexpected server response.'}));
  if(!response.ok) throw new Error(payload.message || Object.values(payload.errors||{}).flat().join(' ') || 'Request failed.');
  return payload;
 }
 document.getElementById('guardian-link-form').addEventListener('submit',async e=>{e.preventDefault();try{const p=await send(e.currentTarget);show(p.message||'Request sent.',true);e.currentTarget.reset();setTimeout(()=>location.reload(),700);}catch(err){show(err.message);}});
 document.querySelectorAll('.guardian-revoke-form').forEach(form=>form.addEventListener('submit',async e=>{e.preventDefault();if(!confirm('Revoke this guardian relationship?'))return;try{await send(e.currentTarget);show('Access revoked.',true);setTimeout(()=>location.reload(),500);}catch(err){show(err.message);}}));
 document.querySelectorAll('.guardian-summary').forEach(button=>button.addEventListener('click',async()=>{try{const r=await fetch(button.dataset.url,{headers:{'Accept':'application/json'}});const p=await r.json();if(!r.ok)throw new Error(p.message||'Could not load student summary.');document.getElementById('guardian-summary-data').textContent=JSON.stringify(p.data,null,2);document.getElementById('guardian-summary').hidden=false;document.getElementById('guardian-summary').scrollIntoView({behavior:'smooth'});}catch(err){show(err.message);}}));
 document.getElementById('close-summary').addEventListener('click',()=>document.getElementById('guardian-summary').hidden=true);
})();
</script>
@endsection
