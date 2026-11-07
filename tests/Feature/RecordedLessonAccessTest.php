<?php
namespace Tests\Feature;
use App\Models\Course; use App\Models\Enrollment; use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class RecordedLessonAccessTest extends TestCase {
 use RefreshDatabase;
 public function test_active_student_can_watch_youtube_lesson_with_privacy_enhanced_embed(): void { [$c,$l,$s]=$this->fixture('https://youtu.be/abcdefghijk',true); $this->actingAs($s)->get(route('courses.lessons.show',[$c,$l]))->assertOk()->assertSee('https://www.youtube-nocookie.com/embed/abcdefghijk',false); }
 public function test_unenrolled_student_cannot_watch_private_lesson(): void { [$c,$l,$s]=$this->fixture('https://www.youtube.com/watch?v=abcdefghijk',false); $this->actingAs($s)->get(route('courses.lessons.show',[$c,$l]))->assertForbidden(); }
 public function test_public_user_can_watch_preview_lesson(): void { [$c,$l]=$this->fixture('https://www.youtube.com/watch?v=abcdefghijk',false,true); $this->get(route('courses.lessons.show',[$c,$l]))->assertOk()->assertSee('youtube-nocookie.com/embed/abcdefghijk',false); }
 public function test_cross_course_lesson_access_returns_not_found(): void { [$c,$l,$s]=$this->fixture('https://vimeo.com/123456789',true); $other=Course::factory()->create(['status'=>'published']); $this->actingAs($s)->get(route('courses.lessons.show',[$other,$l]))->assertNotFound(); }
 private function fixture(string $url,bool $enroll,bool $preview=false): array { $t=User::factory()->create(['role'=>'teacher']); $s=User::factory()->create(['role'=>'student']); $c=Course::factory()->create(['teacher_id'=>$t->id,'status'=>'published']); $l=$c->lessons()->create(['title'=>'Recorded lesson','content'=>'Lesson notes','video_url'=>$url,'duration_minutes'=>20,'order'=>1,'is_preview'=>$preview]); if($enroll) Enrollment::create(['user_id'=>$s->id,'course_id'=>$c->id,'status'=>'active']); return [$c,$l,$s]; }
}
