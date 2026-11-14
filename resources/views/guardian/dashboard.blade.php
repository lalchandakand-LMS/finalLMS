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
            <button class="btn primary" type="submit">{{ __('Send request') }}</button>
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
                <button class="btn primary guardian-summary" type="button" data-url="{{ route('guardian.students.summary',$link->student_id) }}">{{ __('View student summary') }}</button>
            @else
                <p class="muted">{{ __('Waiting for the student to approve. No student data is visible yet.') }}</p>
            @endif
            <form class="guardian-revoke-form" method="POST" action="{{ route('guardian.links.revoke',$link) }}" style="margin-top:10px">
                @csrf<button class="btn secondary" type="submit">{{ __('Revoke access') }}</button>
            </form>
        </article>
    @empty
        <div class="panel" style="padding:22px"><p>{{ __('No student links yet. Send a request using the form above.') }}</p></div>
    @endforelse
    </div>
    <section id="guardian-summary" class="panel" hidden style="padding:20px;margin-top:20px" aria-live="polite">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap"><h2 id="guardian-summary-title">{{ __('Student learning summary') }}</h2><button type="button" id="close-summary" class="btn secondary">{{ __('Close') }}</button></div>
        <div id="guardian-summary-data"></div>
    </section>
</div>
<script>
(() => {
 const message=document.getElementById('guardian-message');
 const summary=document.getElementById('guardian-summary');
 const summaryData=document.getElementById('guardian-summary-data');
 const show=(text,ok=false)=>{message.className=ok?'flash success':'flash error';message.textContent=text;};
 const labels={
  courses:@json(__('Courses')),progress:@json(__('Lesson progress')),attendance:@json(__('Attendance')),
  exams:@json(__('Exam results')),payments:@json(__('Payments')),invoices:@json(__('Invoices')),
  certificates:@json(__('Certificates')),empty:@json(__('No records available.'))
 };
 const columns={
  courses:[['course.title',@json(__('Course'))],['status',@json(__('Status'))],['completed_at',@json(__('Completed'))],['created_at',@json(__('Enrolled'))]],
  progress:[['lesson.title',@json(__('Lesson'))],['completed_at',@json(__('Completed'))]],
  attendance:[['batch.course.title',@json(__('Course'))],['attendance_date',@json(__('Date'))],['status',@json(__('Status'))]],
  exams:[['exam.title',@json(__('Exam'))],['score',@json(__('Score'))],['submitted_at',@json(__('Submitted'))]],
  payments:[['course.title',@json(__('Course'))],['amount',@json(__('Amount'))],['currency',@json(__('Currency'))],['method',@json(__('Method'))],['status',@json(__('Status'))],['created_at',@json(__('Date'))]],
  invoices:[['invoice_number',@json(__('Invoice'))],['title',@json(__('Description'))],['amount',@json(__('Amount'))],['payments_sum_amount',@json(__('Paid'))],['due_date',@json(__('Due date'))],['status',@json(__('Status'))]],
  certificates:[['course.title',@json(__('Course'))],['certificate_number',@json(__('Certificate'))],['status',@json(__('Status'))],['issued_at',@json(__('Issued'))]]
 };
 const valueAt=(row,path)=>path.split('.').reduce((value,key)=>value&&typeof value==='object'?value[key]:null,row);
 const node=(tag,text,className='')=>{const el=document.createElement(tag);if(className)el.className=className;if(text!==null&&text!==undefined)el.textContent=String(text);return el;};
 function renderSummary(data){
  summaryData.replaceChildren();
  const identity=node('div',null,'guardian-student-identity');
  identity.append(node('h3',data.student?.name||@json(__('Student account'))));
  identity.append(node('p',data.student?.email||''));
  summaryData.append(identity);
  Object.entries(columns).forEach(([key,fields])=>{
   const section=node('section',null,'guardian-summary-section');
   section.append(node('h3',labels[key]));
   const rows=Array.isArray(data[key])?data[key]:[];
   if(!rows.length){section.append(node('p',labels.empty,'muted'));summaryData.append(section);return;}
   const wrapper=node('div',null,'table-responsive');
   const table=node('table');const head=node('thead');const header=node('tr');
   fields.forEach(([,label])=>header.append(node('th',label)));
   head.append(header);table.append(head);
   const body=node('tbody');
   rows.forEach(row=>{const tr=node('tr');fields.forEach(([path])=>{let value=valueAt(row,path);if(value===null||value===undefined||value==='')value='—';tr.append(node('td',value));});body.append(tr);});
   table.append(body);wrapper.append(table);section.append(wrapper);summaryData.append(section);
  });
 }
 async function send(form) {
  const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
  const payload=await response.json().catch(()=>({message:'Unexpected server response.'}));
  if(!response.ok)throw new Error(payload.message||Object.values(payload.errors||{}).flat().join(' ')||'Request failed.');
  return payload;
 }
 document.getElementById('guardian-link-form').addEventListener('submit',async event=>{event.preventDefault();try{const payload=await send(event.currentTarget);show(payload.message||@json(__('Request sent.')),true);event.currentTarget.reset();setTimeout(()=>location.reload(),700);}catch(error){show(error.message);}});
 document.querySelectorAll('.guardian-revoke-form').forEach(form=>form.addEventListener('submit',async event=>{event.preventDefault();if(!confirm(@json(__('Revoke this guardian relationship?'))))return;try{await send(event.currentTarget);show(@json(__('Access revoked.')),true);setTimeout(()=>location.reload(),500);}catch(error){show(error.message);}}));
 document.querySelectorAll('.guardian-summary').forEach(button=>button.addEventListener('click',async()=>{button.disabled=true;try{const response=await fetch(button.dataset.url,{headers:{'Accept':'application/json'}});const payload=await response.json();if(!response.ok)throw new Error(payload.message||@json(__('Could not load student summary.')));renderSummary(payload.data);summary.hidden=false;summary.scrollIntoView({behavior:'smooth',block:'start'});}catch(error){show(error.message);}finally{button.disabled=false;}}));
 document.getElementById('close-summary').addEventListener('click',()=>{summary.hidden=true;summaryData.replaceChildren();});
})();
</script>
@endsection
