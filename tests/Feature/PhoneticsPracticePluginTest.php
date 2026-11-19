<?php
namespace Tests\Feature;
use Aloms\PhoneticsPractice\PhoneticsEntry;
use Aloms\PhoneticsPractice\PhoneticsPracticeController;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class PhoneticsPracticePluginTest extends TestCase {
 use RefreshDatabase;
 protected function setUp(): void {
  parent::setUp();
  require_once base_path('plugins/aec/phonetics-practice/1.0.0/src/PhoneticsEntry.php');
  require_once base_path('plugins/aec/phonetics-practice/1.0.0/src/PhoneticsPracticeController.php');
  if(!Schema::hasTable('phonetics_entries')){$migration=require base_path('plugins/aec/phonetics-practice/1.0.0/migrations/2026_11_18_010000_create_phonetics_entries_table.php');$migration->up();}
  Route::get('/__test-phonetics/search',[PhoneticsPracticeController::class,'search']);
  Route::delete('/__test-phonetics/{entry}',[PhoneticsPracticeController::class,'destroy']);
 }
 public function test_student_can_search_shared_and_enrolled_course_practice_entries_only(): void {
  $student=User::factory()->create(['role'=>'student']);
  $other=User::factory()->create(['role'=>'student']);
  $course=Course::factory()->create(['price'=>0]);
  $paidCourse=Course::factory()->create(['price'=>3000]);
  $otherCourse=Course::factory()->create(['price'=>5000]);
  Enrollment::create(['user_id'=>$student->id,'course_id'=>$course->id,'status'=>'active']);
  Enrollment::create(['user_id'=>$student->id,'course_id'=>$paidCourse->id,'status'=>'active']);
  Enrollment::create(['user_id'=>$other->id,'course_id'=>$otherCourse->id,'status'=>'active']);
  PhoneticsEntry::create(['course_id'=>null,'entry_type'=>'word','term'=>'water','translation'=>'পানি','status'=>'published']);
  PhoneticsEntry::create(['course_id'=>$course->id,'entry_type'=>'sentence','term'=>'Good morning','translation'=>'সুপ্রভাত','status'=>'published']);
  PhoneticsEntry::create(['course_id'=>$paidCourse->id,'entry_type'=>'sentence','term'=>'I am ready','translation'=>'আমি প্রস্তুত','status'=>'published']);
  PhoneticsEntry::create(['course_id'=>$otherCourse->id,'entry_type'=>'word','term'=>'water','translation'=>'অন্য কোর্সের গোপন অর্থ','status'=>'published']);
  PhoneticsEntry::create(['course_id'=>null,'entry_type'=>'word','term'=>'draft word','translation'=>'খসড়া','status'=>'draft']);
  $this->actingAs($student)->getJson('/__test-phonetics/search?q=water')->assertOk()->assertJsonCount(1,'results')->assertJsonPath('results.0.translation','পানি');
  $this->actingAs($student)->getJson('/__test-phonetics/search?q=Good%20morning')->assertOk()->assertJsonPath('results.0.translation','সুপ্রভাত');
  $this->actingAs($student)->getJson('/__test-phonetics/search?q=I%20am%20ready')->assertOk()->assertJsonPath('results.0.translation','আমি প্রস্তুত');
  $this->actingAs($student)->getJson('/__test-phonetics/search?q=draft%20word')->assertOk()->assertJsonCount(0,'results');
 }
 public function test_private_audio_delete_failure_preserves_the_entry_for_retry(): void {
  Storage::fake('local');
  $admin=User::factory()->create(['role'=>'admin']);
  $path='phonetics-practice/example-audio.mp3';
  Storage::disk('local')->put($path,'private audio bytes');
  $realDisk=Storage::disk('local');
  $diskMock=\Mockery::mock($realDisk)->makePartial();
  $diskMock->shouldReceive('delete')->once()->with($path)->andReturn(false);
  Storage::shouldReceive('disk')->with('local')->andReturn($diskMock);

  $entry=PhoneticsEntry::create([
   'entry_type'=>'word','term'=>'retry audio','translation'=>'অডিও পুনরায় চেষ্টা',
   'uk_audio_path'=>$path,'status'=>'published',
  ]);

  $this->actingAs($admin)->delete('/__test-phonetics/'.$entry->id)
   ->assertRedirect()->assertSessionHasErrors('audio');

  $this->assertDatabaseHas('phonetics_entries',['id'=>$entry->id,'uk_audio_path'=>$path]);
  $realDisk->assertExists($path);
 }

 public function test_non_student_cannot_use_practice_search(): void {
  $admin=User::factory()->create(['role'=>'admin']);
  $this->actingAs($admin)->getJson('/__test-phonetics/search?q=hello')->assertForbidden();
 }
}
