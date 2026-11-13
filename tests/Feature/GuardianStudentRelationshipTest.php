<?php
namespace Tests\Feature;
use App\Models\Branch; use App\Models\GuardianStudentLink; use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class GuardianStudentRelationshipTest extends TestCase {
 use RefreshDatabase;
 public function test_student_consent_is_required_before_guardian_can_view_any_student_summary(): void {
  $g=User::factory()->create(['role'=>'guardian','email_verified_at'=>now()]); $s=User::factory()->create(['role'=>'student','email_verified_at'=>now()]);
  $this->actingAs($g)->postJson(route('guardian.links.store'),['student_email'=>$s->email,'relationship'=>'Parent'])->assertCreated()->assertJsonPath('data.status','pending');
  $link=GuardianStudentLink::firstOrFail(); $this->actingAs($g)->getJson(route('guardian.students.summary',$s))->assertNotFound();
  $this->actingAs($s)->getJson(route('student.guardian-requests.index'))->assertOk()->assertJsonPath('data.0.guardian.id',$g->id);
  $this->actingAs($s)->postJson(route('student.guardian-requests.respond',$link),['decision'=>'accept'])->assertOk()->assertJsonPath('data.status','active');
  $this->actingAs($g)->getJson(route('guardian.students.summary',$s))->assertOk()->assertJsonPath('data.student.id',$s->id);
  $this->assertDatabaseHas('guardian_student_links',['id'=>$link->id,'status'=>'active','reviewed_by'=>$s->id]);
 }
 public function test_guardian_cannot_request_student_from_another_branch(): void {
  $a=Branch::create(['name'=>'Branch A','slug'=>'branch-a','is_active'=>true]); $b=Branch::create(['name'=>'Branch B','slug'=>'branch-b','is_active'=>true]);
  $g=User::factory()->create(['role'=>'guardian','branch_id'=>$a->id,'email_verified_at'=>now()]); $s=User::factory()->create(['role'=>'student','branch_id'=>$b->id,'email_verified_at'=>now()]);
  $this->actingAs($g)->postJson(route('guardian.links.store'),['student_email'=>$s->email])->assertForbidden(); $this->assertDatabaseCount('guardian_student_links',0);
 }
 public function test_only_linked_student_can_respond_and_either_party_can_revoke(): void {
  $g=User::factory()->create(['role'=>'guardian','email_verified_at'=>now()]); $s=User::factory()->create(['role'=>'student','email_verified_at'=>now()]);
  $otherS=User::factory()->create(['role'=>'student','email_verified_at'=>now()]); $otherG=User::factory()->create(['role'=>'guardian','email_verified_at'=>now()]);
  $this->actingAs($g)->postJson(route('guardian.links.store'),['student_email'=>$s->email])->assertCreated(); $link=GuardianStudentLink::firstOrFail();
  $this->actingAs($otherS)->postJson(route('student.guardian-requests.respond',$link),['decision'=>'accept'])->assertNotFound();
  $this->actingAs($s)->postJson(route('student.guardian-requests.respond',$link),['decision'=>'accept'])->assertOk();
  $this->actingAs($otherG)->getJson(route('guardian.students.summary',$s))->assertNotFound();
  $this->actingAs($s)->postJson(route('student.guardian-requests.revoke',$link))->assertOk()->assertJsonPath('data.status','revoked');
  $this->actingAs($g)->getJson(route('guardian.students.summary',$s))->assertNotFound();
 }
 public function test_rejected_link_can_be_requested_again_but_pending_request_cannot_be_duplicated(): void {
  $g=User::factory()->create(['role'=>'guardian','email_verified_at'=>now()]); $s=User::factory()->create(['role'=>'student','email_verified_at'=>now()]);
  $this->actingAs($g)->postJson(route('guardian.links.store'),['student_email'=>$s->email])->assertCreated();
  $this->actingAs($g)->postJson(route('guardian.links.store'),['student_email'=>$s->email])->assertStatus(409); $link=GuardianStudentLink::firstOrFail();
  $this->actingAs($s)->postJson(route('student.guardian-requests.respond',$link),['decision'=>'reject'])->assertOk();
  $this->actingAs($g)->postJson(route('guardian.links.store'),['student_email'=>$s->email])->assertCreated()->assertJsonPath('data.status','pending');
  $this->assertDatabaseCount('guardian_student_links',1); $this->assertDatabaseHas('guardian_student_links',['id'=>$link->id,'status'=>'pending','reviewed_by'=>null,'reviewed_at'=>null]);
 }
 public function test_guardian_relationships_are_unique_and_role_validated(): void {
  $g=User::factory()->create(['role'=>'guardian']); $s=User::factory()->create(['role'=>'student']);
  GuardianStudentLink::create(['guardian_id'=>$g->id,'student_id'=>$s->id,'relationship'=>'Parent','status'=>'pending','requested_by'=>$g->id,'requested_at'=>now()]);
  $this->expectException(\Illuminate\Database\QueryException::class);
  GuardianStudentLink::create(['guardian_id'=>$g->id,'student_id'=>$s->id,'relationship'=>'Parent','status'=>'pending','requested_by'=>$g->id,'requested_at'=>now()]);
 }
}
