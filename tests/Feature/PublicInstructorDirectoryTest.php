<?php
namespace Tests\Feature;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class PublicInstructorDirectoryTest extends TestCase
{
    use RefreshDatabase;
    public function test_directory_lists_only_teachers_with_published_courses_and_never_exposes_private_contact_fields(): void
    {
        $teacher = User::factory()->create(['name'=>'Ayesha English','email'=>'private.teacher@example.com','phone_number'=>'+8801712345678','role'=>'teacher']);
        Course::factory()->create(['teacher_id'=>$teacher->id,'title'=>'Spoken English Foundation','status'=>'published']);
        $hiddenTeacher = User::factory()->create(['name'=>'Hidden Teacher','role'=>'teacher']);
        Course::factory()->create(['teacher_id'=>$hiddenTeacher->id,'title'=>'Draft Course','status'=>'draft']);
        $this->get(route('instructors.index'))->assertOk()->assertSee('Ayesha English')->assertSee('Spoken English Foundation')
            ->assertDontSee('Hidden Teacher')->assertDontSee('private.teacher@example.com')->assertDontSee('+8801712345678');
    }
    public function test_directory_search_matches_teacher_names_and_published_course_titles(): void
    {
        $teacher = User::factory()->create(['name'=>'Rahim Ahmed','role'=>'teacher']);
        Course::factory()->create(['teacher_id'=>$teacher->id,'title'=>'Business English','status'=>'published']);
        $this->get(route('instructors.index',['q'=>'Business']))->assertOk()->assertSee('Rahim Ahmed')->assertSee('Business English');
        $this->get(route('instructors.index',['q'=>'No matching teacher']))->assertOk()->assertSee('No instructors found')->assertDontSee('Rahim Ahmed');
    }
    public function test_learn_entry_point_sends_students_to_their_learning_space_and_visitors_to_catalog(): void
    {
        $student = User::factory()->create(['role'=>'student','email_verified_at'=>now()]);
        $this->get(route('learn.index'))->assertRedirect(route('courses.index'));
        $this->actingAs($student)->get(route('learn.index'))->assertRedirect(route('student.courses.index'));
    }
}
