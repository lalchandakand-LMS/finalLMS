<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
class GuardianStudentLink extends Model {
 protected $fillable=['guardian_id','student_id','relationship','status','requested_by','reviewed_by','requested_at','reviewed_at'];
 protected function casts(): array { return ['requested_at'=>'datetime','reviewed_at'=>'datetime']; }
 protected static function booted(): void {
  static::saving(function(self $link): void {
   $guardian=User::query()->find($link->guardian_id); $student=User::query()->find($link->student_id);
   if(!$guardian?->isGuardian()) throw ValidationException::withMessages(['guardian_id'=>'The linked account must have the guardian role.']);
   if(!$student?->isStudent() || $student->id===$guardian->id) throw ValidationException::withMessages(['student_id'=>'The linked account must be a different student account.']);
   if((int)$link->requested_by!==(int)$guardian->id) throw ValidationException::withMessages(['requested_by'=>'The guardian must create their own link request.']);
   if(!in_array($link->status,['pending','active','rejected','revoked'],true)) throw ValidationException::withMessages(['status'=>'The guardian relationship status is invalid.']);
   if($link->status==='pending' && ($link->reviewed_by!==null || $link->reviewed_at!==null)) throw ValidationException::withMessages(['status'=>'Pending relationships cannot contain review metadata.']);
   if(in_array($link->status,['active','rejected'],true) && ((int)$link->reviewed_by!==(int)$student->id || !$link->reviewed_at)) throw ValidationException::withMessages(['reviewed_by'=>'Only the linked student can accept or reject this request.']);
   if($link->status==='revoked' && (!in_array((int)$link->reviewed_by,[(int)$guardian->id,(int)$student->id],true) || !$link->reviewed_at)) throw ValidationException::withMessages(['reviewed_by'=>'Only either linked account can revoke this relationship.']);
   if($guardian->branch_id!==$student->branch_id) throw ValidationException::withMessages(['student_id'=>'Guardian and student must belong to the same branch.']);
   if($guardian->branch_id!==null && !Branch::query()->whereKey($guardian->branch_id)->where('is_active',true)->exists()) throw ValidationException::withMessages(['student_id'=>'The assigned branch is inactive.']);
  });
 }
 public function guardian(): BelongsTo { return $this->belongsTo(User::class,'guardian_id'); }
 public function student(): BelongsTo { return $this->belongsTo(User::class,'student_id'); }
 public function reviewer(): BelongsTo { return $this->belongsTo(User::class,'reviewed_by'); }
 public function isApproved(): bool { return $this->status==='active' && $this->reviewed_at!==null; }
 public function respondByStudent(User $student,bool $accept): void {
  if(!$student->isStudent() || (int)$student->id!==(int)$this->student_id || $this->status!=='pending') throw ValidationException::withMessages(['guardian_link'=>'Only the linked student can respond to a pending request.']);
  $this->forceFill(['status'=>$accept?'active':'rejected','reviewed_by'=>$student->id,'reviewed_at'=>now()])->save();
 }
 public function revokeBy(User $actor): void {
  $g=$actor->isGuardian() && (int)$actor->id===(int)$this->guardian_id;
  $s=$actor->isStudent() && (int)$actor->id===(int)$this->student_id;
  if((!$g&&!$s)||!in_array($this->status,['pending','active'],true)) throw ValidationException::withMessages(['guardian_link'=>'Only a linked guardian or student can revoke a pending or active relationship.']);
  $this->forceFill(['status'=>'revoked','reviewed_by'=>$actor->id,'reviewed_at'=>now()])->save();
 }
}
