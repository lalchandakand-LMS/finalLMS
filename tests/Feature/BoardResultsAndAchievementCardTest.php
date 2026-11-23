<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardResultsAndAchievementCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_results_page_links_only_to_known_education_board_result_services(): void
    {
        $this->get(route('board-results.index'))
            ->assertOk()
            ->assertSee('https://www.educationboardresults.gov.bd/v2/home')
            ->assertSee('https://eboardresults.com/v2/home')
            ->assertSee('https://eduboardresults.gov.bd/')
            ->assertSee('SMS formats and short codes can differ');
    }

    public function test_issued_certificate_has_a_public_shareable_achievement_card_without_private_contact_details(): void
    {
        $student = User::factory()->create([
            'name' => 'Nusrat Jahan',
            'email' => 'nusrat.private@example.com',
            'role' => 'student',
        ]);
        $course = Course::factory()->create(['title' => 'Spoken English Foundation']);
        $certificate = Certificate::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'certificate_number' => 'AEC-TEST-9F8A',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $this->get(route('achievements.show', $certificate->certificate_number))
            ->assertOk()
            ->assertSee('Nusrat Jahan')
            ->assertSee('Spoken English Foundation')
            ->assertSee('AEC-TEST-9F8A')
            ->assertSee('Print / Save as PDF')
            ->assertDontSee('nusrat.private@example.com');
    }

    public function test_unissued_or_unknown_certificates_cannot_be_viewed_publicly(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create();
        $certificate = Certificate::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'certificate_number' => 'AEC-PENDING-1234',
            'status' => 'pending',
            'issued_at' => null,
        ]);

        $this->get(route('achievements.show', $certificate->certificate_number))->assertNotFound();
        $this->get(route('achievements.show', 'AEC-UNKNOWN-1234'))->assertNotFound();
    }
}
