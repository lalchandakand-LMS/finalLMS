@extends('layouts.app')
@section('title', __('Guardian access requests'))
@section('content')
<div class="container" style="max-width:980px;margin:32px auto;padding:0 16px">
    <div class="page-header"><div><span class="badge info">{{ __('Student privacy') }}</span><h1>{{ __('Guardian access requests') }}</h1><p class="muted">{{ __('Only approve a person you recognize. Approval shares a limited read-only summary; you can revoke access later.') }}</p></div></div>
    <div id="student-message" role="status" aria-live="polite"></div>
    <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px">
    @forelse($requests as $request)
        <article class="panel" style="padding:20px">
            <h3>{{ $request->guardian?->name ?? __('Guardian account') }}</h3>
            <p class="muted">{{ $request->guardian?->email }}</p>
            <p>{{ __('Relationship') }}: {{ $request->relationship }}</p>
            <p class="muted">{{ __('Requested') }}: {{ optional($request->requested_at)->format('d M Y, h:i A') }}</p>
            <form class="guardian-response-form" method="POST" action="{{ route('student.guardian-requests.respond',$request) }}" style="display:flex;gap:10px;flex-wrap:wrap">
                @csrf
                <button class="btn primary" name="decision" value="accept" type="submit">{{ __('Approve access') }}</button>
                <button class="btn secondary" name="decision" value="reject" type="submit">{{ __('Reject request') }}</button>
            </form>
        </article>
    @empty
        <div class="panel" style="padding:22px"><p>{{ __('You have no pending guardian access requests.') }}</p></div>
    @endforelse
    </div>
</div>
<script>
(() => {
 const message=document.getElementById('student-message');
 const show=(text,ok=false)=>{message.className=ok?'flash success':'flash error';message.textContent=text;};
 document.querySelectorAll('.guardian-response-form').forEach(form=>form.addEventListener('submit',async e=>{
  e.preventDefault();const button=e.submitter;if(!button)return;
  const decision=button.value;
  if(decision==='accept'&&!confirm('Approve this guardian? They will be able to view a limited read-only summary of your learning and account records.'))return;
  const data=new FormData(form);data.set('decision',decision);
  try{const r=await fetch(form.action,{method:'POST',body:data,headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});const p=await r.json().catch(()=>({}));if(!r.ok)throw new Error(p.message||Object.values(p.errors||{}).flat().join(' ')||'Request failed.');show(decision==='accept'?'Guardian access approved.':'Request rejected.',true);setTimeout(()=>location.reload(),600);}catch(err){show(err.message);}
 }));
})();
</script>
@endsection
