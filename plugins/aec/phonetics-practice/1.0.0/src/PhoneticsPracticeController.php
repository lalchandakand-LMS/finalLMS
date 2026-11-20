<?php
namespace Aloms\PhoneticsPractice;
use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
class PhoneticsPracticeController extends Controller {
 public function adminIndex(): View {$entries=PhoneticsEntry::with('course')->latest()->paginate(25);return view('phonetics-practice::admin.index',compact('entries'));}
 public function create(): View {$entry=new PhoneticsEntry(['entry_type'=>'word','status'=>'published']);$courses=Course::orderBy('title')->get(['id','title','price']);return view('phonetics-practice::admin.form',compact('entry','courses'));}
 public function edit(int $entry): View {$entry=PhoneticsEntry::findOrFail($entry);$courses=Course::orderBy('title')->get(['id','title','price']);return view('phonetics-practice::admin.form',compact('entry','courses'));}
 public function store(Request $request) {
  $data=$this->validated($request);
  $data['created_by']=$request->user()->id;
  $storedPaths=[];
  try {
   $data['uk_audio_path']=$this->storeAudio($request,'uk_audio');
   if($data['uk_audio_path'])$storedPaths[]=$data['uk_audio_path'];
   $data['us_audio_path']=$this->storeAudio($request,'us_audio');
   if($data['us_audio_path'])$storedPaths[]=$data['us_audio_path'];
   unset($data['uk_audio'],$data['us_audio']);
   PhoneticsEntry::create($data);
  } catch (\Throwable $exception) {
   foreach($storedPaths as $path) {
    if(!$this->deletePrivateAudio($path))Log::warning('Phonetics audio cleanup failed after entry creation error.',['path'=>$path]);
   }
   throw $exception;
  }
  return redirect()->route('admin.phonetics.index')->with('success','Entry created.');
 }
 public function update(Request $request,int $entry) {
  $entry=PhoneticsEntry::findOrFail($entry);
  $data=$this->validated($request);
  $newPaths=[];
  $oldPaths=[];
  try {
   foreach(['uk_audio','us_audio'] as $field) {
    if($request->hasFile($field)) {
     $column=$field.'_path';
     $new=$this->storeAudio($request,$field);
     if($new)$newPaths[]=$new;
     if(is_string($entry->{$column})&&$entry->{$column}!=='')$oldPaths[]=$entry->{$column};
     $data[$column]=$new;
    }
   }
   unset($data['uk_audio'],$data['us_audio']);
   $entry->update($data);
  } catch (\Throwable $exception) {
   foreach($newPaths as $path) {
    if(!$this->deletePrivateAudio($path))Log::warning('Phonetics replacement audio cleanup failed after update error.',['path'=>$path,'entry_id'=>$entry->id]);
   }
   throw $exception;
  }

  $cleanupPending=false;
  foreach($oldPaths as $path) {
   if(!$this->deletePrivateAudio($path)) {
    $cleanupPending=true;
    Log::warning('Phonetics replaced audio could not be removed from private storage.',['path'=>$path,'entry_id'=>$entry->id]);
   }
  }
  $message=$cleanupPending
   ? 'Entry updated. Previous audio cleanup needs administrator review.'
   : 'Entry updated.';
  return redirect()->route('admin.phonetics.index')->with('success',$message);
 }
 public function destroy(int $entry) {
  $entry=PhoneticsEntry::findOrFail($entry);
  foreach(array_unique([$entry->uk_audio_path,$entry->us_audio_path]) as $path) {
   if(!$this->deletePrivateAudio($path)) {
    return back()->withErrors(['audio'=>'Private audio cleanup failed. The entry was retained so deletion can be retried safely.']);
   }
  }
  $entry->delete();
  return redirect()->route('admin.phonetics.index')->with('success','Entry deleted.');
 }
 public function studentIndex(): View {return view('phonetics-practice::student.index');}
 public function search(Request $request) {$data=$request->validate(['q'=>['required','string','min:1','max:180']]);$term=mb_strtolower(trim($data['q']));$entries=$this->accessibleEntries($request)->whereRaw('LOWER(term) = ?',[$term])->limit(20)->get(['id','entry_type','term','translation','uk_ipa','us_ipa','uk_audio_path','us_audio_path','example_sentence','notes']);return response()->json(['results'=>$entries->map(fn($e)=>['id'=>$e->id,'type'=>$e->entry_type,'term'=>$e->term,'translation'=>$e->translation,'uk_ipa'=>$e->uk_ipa,'us_ipa'=>$e->us_ipa,'uk_audio'=>$e->uk_audio_path?route('student.phonetics.audio',[$e->id,'uk']):null,'us_audio'=>$e->us_audio_path?route('student.phonetics.audio',[$e->id,'us']):null,'example'=>$e->example_sentence,'notes'=>$e->notes])]);}
 public function audio(Request $request,int $entry,string $accent): BinaryFileResponse {abort_unless(in_array($accent,['uk','us'],true),404);$record=$this->accessibleEntries($request)->findOrFail($entry);$path=$accent==='uk'?$record->uk_audio_path:$record->us_audio_path;abort_unless(is_string($path)&&str_starts_with($path,'phonetics-practice/'),404);$disk=Storage::disk('local');abort_unless($disk->exists($path),404);return response()->file($disk->path($path),['Content-Type'=>$disk->mimeType($path)?:'application/octet-stream','X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store']);}
 private function accessibleEntries(Request $request) {$user=$request->user();abort_unless($user&&$user->isStudent(),403);return PhoneticsEntry::where('status','published')->where(function($q)use($user){$q->whereNull('course_id')->orWhereHas('course',fn($course)=>$course->where('status','published')->whereHas('enrollments',fn($enrollments)=>$enrollments->where('user_id',$user->id)->whereIn('status',['active','completed'])));});}
 private function validated(Request $request): array {return $request->validate(['course_id'=>['nullable','integer','exists:courses,id'],'entry_type'=>['required',Rule::in(['word','sentence'])],'term'=>['required','string','max:180'],'translation'=>['required','string','max:1200'],'uk_ipa'=>['nullable','string','max:180'],'us_ipa'=>['nullable','string','max:180'],'example_sentence'=>['nullable','string','max:1200'],'notes'=>['nullable','string','max:2000'],'status'=>['required',Rule::in(['draft','published'])],'uk_audio'=>['nullable','file','max:10240','mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/ogg,audio/mp4,audio/aac,audio/webm'],'us_audio'=>['nullable','file','max:10240','mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/ogg,audio/mp4,audio/aac,audio/webm']]);}
 private function storeAudio(Request $request,string $field): ?string {
  if(!$request->hasFile($field))return null;
  $path=$request->file($field)->store('phonetics-practice','local');
  if(!is_string($path)||$path==='')throw new \RuntimeException('Private phonetics audio could not be stored.');
  return $path;
 }
 private function deletePrivateAudio(?string $path): bool {
  if($path===null||$path==='')return true;
  if(preg_match('#^phonetics-practice/[A-Za-z0-9._-]{1,240}$#D',$path)!==1)return false;
  try {
   $disk=Storage::disk('local');
   return !$disk->exists($path)||$disk->delete($path);
  } catch (\Throwable $exception) {
   Log::warning('Private phonetics audio cleanup raised an exception.',['path'=>$path,'exception'=>get_class($exception)]);
   return false;
  }
 }
}
