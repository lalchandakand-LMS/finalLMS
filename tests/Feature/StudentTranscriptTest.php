<?php

namespace Tests\\Feature;

use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class StudentTranscriptTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_and_print_their_own_transcript_page(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get(route('student.transcript'))
            ->assertOk()
            ->assertSee('Academic Transcript')
            ->assertSee($student->name)
            ->assertSee('Print / Save PDF');
    }

    public function test_non_student_cannot_open_student_transcript(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)
            ->get(route('student.transcript'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_for_transcript(): void
    {
        $this->get(route('student.transcript'))->assertRedirect(route('login'));
    }
}
