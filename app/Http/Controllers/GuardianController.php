<?php
namespace App\Http\Controllers;
use App\Models\ActivityLog; use App\Models\Attendance; use App\Models\Branch; use App\Models\Certificate;
use App\Models\Enrollment; use App\Models\ExamAttempt; use App\Models\GuardianStudentLink; use App\Models\Invoice; use App\Models\LessonProgress; use App\Models\Payment; use App\Models\User;
use Illuminate\Http\JsonResponse; use Illuminate\Http\Request; use Illuminate\Support\Facades\DB; use Illuminate\Validation\ValidationException;
class GuardianController extends Controller {
 public function dashboard(Request $request) {
  $g=$request->user(); abort_unless($g?->isGuardian(),403);
  $links=GuardianStudentLink::where('guardian_id',$g->id)->whereIn('status',['pending','active'])
   ->with('student:id,name,email,branch_id')->latest('requested_at')->get();
  return view('guardian.dashboard',compact('links'));
 }
 public function requestsPage(Request $request) {
  $s=$request->user(); abort_unless($s?->isStudent(),403);
  $requests=GuardianStudentLink::where('student_id',$s->id)->where('status','pending')
   ->with('guardian:id,name,email')->latest('requested_at')->get();
  return view('student.guardian-requests.index',compact('requests'));
 }
 public function links(Request $request): JsonResponse {
  $g=$request->user(); abort_unless($g?->isGuardian(),403);
  $rows=GuardianStudentLink::where('guardian_id',$g->id)->whereIn('status',['pending','active'])->with('student:id,name,email,branch_id')->latest('requested_at')->get(['id','guardian_id','student_id','relationship','status','requested_at','reviewed_at']);
  return response()->json(['data'=>$rows]);
 }
 public function requestLink(Request $request): JsonResponse {
  $g=$request->user(); abort_unless($g?->isGuardian(),403);
  $d=$request->validate(['student_email'=>['required','email:rfc','max:190'],'relationship'=>['nullable','string','min:1','max:60']]);
  $s=User::where('email',strtolower(trim($d['student_email'])))->where('role','student')->whereNotNull('email_verified_at')->first();
  if(!$s) throw ValidationException::withMessages(['student_email'=>'A verified student account with that email was not found.']);
  $this->sameBranch($g,$s);
  $link=DB::transaction(function() use($request,$g,$s,$d): GuardianStudentLink {
   User::whereKey($g->id)->lockForUpdate()->firstOrFail();
   $old=GuardianStudentLink::where('guardian_id',$g->id)->where('student_id',$s->id)->lockForUpdate()->first();
   if($old && in_array($old->status,['pending','active'],true)) abort(409,'A pending or active request already exists.');
   $link=$old??new GuardianStudentLink();
   $link->fill(['guardian_id'=>$g->id,'student_id'=>$s->id,'relationship'=>trim((string)($d['relationship']??'Guardian'))?:'Guardian','status'=>'pending','requested_by'=>$g->id,'reviewed_by'=>null,'requested_at'=>now(),'reviewed_at'=>null])->save();
   $this->audit($request,$g,'guardian_link.requested',$link,'Guardian requested a student relationship.');
   return $link;
  });
  return response()->json(['data'=>['id'=>$link->id,'status'=>$link->status],'message'=>'Request sent. The student must accept it before any records are shared.'],201);
 }
 public function incomingRequests(Request $request): JsonResponse {
  $s=$request->user(); abort_unless($s?->isStudent(),403);
  $rows=GuardianStudentLink::where('student_id',$s->id)->where('status','pending')->with('guardian:id,name,email')->latest('requested_at')->get(['id','guardian_id','student_id','relationship','status','requested_at']);
  return response()->json(['data'=>$rows]);
 }
 public function respond(Request $request,GuardianStudentLink $link): JsonResponse {
  $s=$request->user(); abort_unless($s?->isStudent() && (int)$link->student_id===(int)$s->id,404);
  $d=$request->validate(['decision'=>['required','in:accept,reject']]);
  DB::transaction(function() use($request,$s,$link,$d): void {
   $row=GuardianStudentLink::whereKey($link->id)->lockForUpdate()->firstOrFail(); if($row->status!=='pending') abort(409);
   $g=User::findOrFail($row->guardian_id); $this->sameBranch($g,$s); $row->respondByStudent($s,$d['decision']==='accept');
   $this->audit($request,$s,$d['decision']==='accept'?'guardian_link.accepted':'guardian_link.rejected',$row,'Student responded to a guardian request.');
  });
  return response()->json(['data'=>['id'=>$link->id,'status'=>$d['decision']==='accept'?'active':'rejected']]);
 }
 public function revoke(Request $request,GuardianStudentLink $link): JsonResponse {
  $a=$request->user(); abort_unless($a && (($a->isGuardian()&&(int)$link->guardian_id===(int)$a->id)||($a->isStudent()&&(int)$link->student_id===(int)$a->id)),404);
  DB::transaction(function() use($request,$a,$link): void { $row=GuardianStudentLink::whereKey($link->id)->lockForUpdate()->firstOrFail(); $row->revokeBy($a); $this->audit($request,$a,'guardian_link.revoked',$row,'Guardian relationship was revoked.'); });
  return response()->json(['data'=>['id'=>$link->id,'status'=>'revoked']]);
 }
 public function summary(Request $request,User $student): JsonResponse {
  $g=$request->user(); abort_unless($g?->isGuardian(),403);
  $link=GuardianStudentLink::where('guardian_id',$g->id)->where('student_id',$student->id)->where('status','active')->whereNotNull('reviewed_at')->first();
  abort_unless($link && $student->isStudent(),404); $this->sameBranch($g,$student);
  return response()->json(['data'=>[
   'student'=>$student->only(['id','name','email']),
   'courses'=>Enrollment::where('user_id',$student->id)->with('course:id,title')->latest()->limit(30)->get(['id','course_id','status','completed_at','created_at']),
   'progress'=>LessonProgress::where('user_id',$student->id)->whereNotNull('completed_at')->with('lesson:id,course_id,title')->latest('completed_at')->limit(50)->get(['id','lesson_id','completed_at']),
   'attendance'=>Attendance::where('student_id',$student->id)->with('batch.course:id,title')->orderByDesc('attendance_date')->limit(30)->get(['id','batch_id','attendance_date','status']),
   'exams'=>ExamAttempt::where('student_id',$student->id)->where('status','submitted')->with('exam:id,batch_id,title')->latest('submitted_at')->limit(30)->get(['id','exam_id','score','status','submitted_at']),
   'payments'=>Payment::where('user_id',$student->id)->with('course:id,title')->latest()->limit(30)->get(['id','course_id','amount','currency','method','status','created_at']),
   'invoices'=>Invoice::where('user_id',$student->id)->with('course:id,title')->withSum('payments','amount')->latest()->limit(30)->get(['id','course_id','invoice_number','title','amount','due_date','status']),
   'certificates'=>Certificate::where('user_id',$student->id)->with('course:id,title')->latest('issued_at')->limit(30)->get(['id','course_id','certificate_number','status','issued_at']),
  ]]);
 }
 private function sameBranch(User $g,User $s): void {
  if(($g->branch_id===null)!==($s->branch_id===null) || ($g->branch_id!==null && (int)$g->branch_id!==(int)$s->branch_id)) abort(403,'Guardian and student must belong to the same branch.');
  if($g->branch_id!==null && !Branch::whereKey($g->branch_id)->where('is_active',true)->exists()) abort(403,'The assigned branch is inactive.');
 }
 private function audit(Request $request,User $actor,string $event,GuardianStudentLink $link,string $description): void {
  ActivityLog::create(['user_id'=>$actor->id,'event'=>$event,'description'=>substr($description.' Link #'.$link->id,0,255),'method'=>strtoupper($request->method()),'route'=>substr($request->route()?->getName()??$request->path(),0,190),'ip_address'=>$request->ip(),'user_agent'=>substr((string)$request->userAgent(),0,1000)]);
 }
}
