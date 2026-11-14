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

 public function test_guardian_dashboard_lists_only_the_guardians_own_pending_and_active_links(): void {
  $g=User::factory()->create(['role'=>'guardian','email_verified_at'=>now()]);
  $other=User::factory()->create(['role'=>'guardian','email_verified_at'=>now()]);
  $student=User::factory()->create(['role'=>'student','email_verified_at'=>now()]);
  $otherStudent=User::factory()->create(['role'=>'student','email_verified_at'=>now()]);
  GuardianStudentLink::create(['guardian_id'=>$g->id,'student_id'=>$student->id,'relationship'=>'Parent','status'=>'pending','requested_by'=>$g->id,'requested_at'=>now()]);
  GuardianStudentLink::create(['guardian_id'=>$other->id,'student_id'=>$otherStudent->id,'relationship'=>'Parent','status'=>'pending','requested_by'=>$other->id,'requested_at'=>now()]);
  $this->actingAs($g)->get(route('guardian.dashboard'))->assertOk()->assertSee($student->name)->assertDontSee($otherStudent->name);
 }

 public function test_student_consent_inbox_shows_only_requests_for_the_signed_in_student(): void {
  $student=User::factory()->create(['role'=>'student','email_verified_at'=>now()]);
  $otherStudent=User::factory()->create(['role'=>'student','email_verified_at'=>now()]);
  $guardian=User::factory()->create(['role'=>'guardian','email_verified_at'=>now()]);
  $otherGuardian=User::factory()->create(['role'=>'guardian','email_verified_at'=>now()]);
  GuardianStudentLink::create(['guardian_id'=>$guardian->id,'student_id'=>$student->id,'relationship'=>'Parent','status'=>'pending','requested_by'=>$guardian->id,'requested_at'=>now()]);
  GuardianStudentLink::create(['guardian_id'=>$otherGuardian->id,'student_id'=>$otherStudent->id,'relationship'=>'Parent','status'=>'pending','requested_by'=>$otherGuardian->id,'requested_at'=>now()]);
  $this->actingAs($student)->get(route('student.guardian-requests.page'))->assertOk()->assertSee($guardian->name)->assertDontSee($otherGuardian->name);
 }


 public function test_guardian_can_switch_between_multiple_consented_children_without_accessing_unlinked_students(): void {
  $guardian=User::factory()->create(['role'=>'guardian','email_verified_at'=>now()]);
  $first=User::factory()->create(['role'=>'student','email_verified_at'=>now()]);
  $second=User::factory()->create(['role'=>'student','email_verified_at'=>now()]);
  $unlinked=User::factory()->create(['role'=>'student','email_verified_at'=>now()]);
  $otherGuardian=User::factory()->create(['role'=>'guardian','email_verified_at'=>now()]);

  foreach ([$first,$second] as $child) {
   GuardianStudentLink::create([
    'guardian_id'=>$guardian->id,'student_id'=>$child->id,'relationship'=>'Parent',
    'status'=>'active','requested_by'=>$guardian->id,'reviewed_by'=>$child->id,
    'requested_at'=>now(),'reviewed_at'=>now(),
   ]);
  }
  GuardianStudentLink::create([
   'guardian_id'=>$otherGuardian->id,'student_id'=>$unlinked->id,'relationship'=>'Parent',
   'status'=>'active','requested_by'=>$otherGuardian->id,'reviewed_by'=>$unlinked->id,
   'requested_at'=>now(),'reviewed_at'=>now(),
  ]);

  $linksResponse=$this->actingAs($guardian)->getJson(route('guardian.links.index'));
  $linksResponse->assertOk()->assertJsonCount(2,'data');
  $listedStudentIds=array_map(fn (array $row): int => (int) $row['student']['id'], $linksResponse->json('data'));
  $this->assertEqualsCanonicalizing([$first->id,$second->id],$listedStudentIds);

  $this->actingAs($guardian)->getJson(route('guardian.students.summary',$first))
   ->assertOk()->assertJsonPath('data.student.id',$first->id);
  $this->actingAs($guardian)->getJson(route('guardian.students.summary',$second))
   ->assertOk()->assertJsonPath('data.student.id',$second->id);
  $this->actingAs($guardian)->getJson(route('guardian.students.summary',$unlinked))
   ->assertNotFound();
 }

}
